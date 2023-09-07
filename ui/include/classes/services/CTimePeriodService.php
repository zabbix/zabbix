<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CTimePeriodService {

	private const DATE_TIME_FORMAT = ZBX_FULL_DATE_TIME;

	private int $now;

	private string $from;
	private int $from_ts;
	private int $from_type;
	private string $to;
	private int $to_ts;
	private int $to_type;

	private array $errors = [];

	public function __construct($from, $to) {
		$this->now = time();

		$this->from = (string) $from;
		$this->to = (string) $to;

		$this->validate();
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public function getData(): array {
		if ($this->errors) {
			return [];
		}

		return [
			'from' => $this->from,
			'from_ts' => $this->from_ts,
			'to' => $this->to,
			'to_ts' => $this->to_ts
		];
	}

	public function increment(): void {
		if ($this->errors) {
			return;
		}

		$period = $this->to_ts - $this->from_ts + 1;
		$offset = min($period, $this->now - $this->to_ts);

		$this->from_ts += $offset;
		$this->to_ts += $offset;

		$this->from = date(self::DATE_TIME_FORMAT, $this->from_ts);
		$this->to = date(self::DATE_TIME_FORMAT, $this->to_ts);
	}

	public function decrement(): void {
		if ($this->errors) {
			return;
		}

		$period = $this->to_ts - $this->from_ts + 1;
		$offset = min($period, $this->from_ts);

		$this->from_ts -= $offset;
		$this->to_ts -= $offset;

		$this->from = date(self::DATE_TIME_FORMAT, $this->from_ts);
		$this->to = date(self::DATE_TIME_FORMAT, $this->to_ts);
	}

	public function zoomOut(): void {
		if ($this->errors) {
			return;
		}

		$period = $this->to_ts - $this->from_ts + 1;

		$to_offset = min((int) ($period / 2), $this->now - $this->to_ts);
		$from_offset = min($period - $to_offset, $this->from_ts);

		$this->from_ts -= $from_offset;
		$this->to_ts += $to_offset;

		$max_period = $this->getMaxPeriod();

		if ($this->to_ts - $this->from_ts + 1 > $max_period) {
			$this->from_ts = $this->to_ts - $max_period + 1;
		}

		$this->from = date(self::DATE_TIME_FORMAT, $this->from_ts);
		$this->to = date(self::DATE_TIME_FORMAT, $this->to_ts);
	}

	public function rangeChange(): void {
		if ($this->errors) {
			return;
		}

		if ($this->from_type === CRangeTimeParser::ZBX_TIME_ABSOLUTE) {
			$this->from = date(self::DATE_TIME_FORMAT, $this->from_ts);
		}

		if ($this->to_type === CRangeTimeParser::ZBX_TIME_ABSOLUTE) {
			$this->to = date(self::DATE_TIME_FORMAT, $this->to_ts);
		}
	}

	public function rangeOffset(int $from_offset, int $to_offset): void {
		if ($this->errors) {
			return;
		}

		$this->from_ts += $from_offset;
		$this->to_ts -= $to_offset;

		$this->validatePeriod();

		if (!$this->errors) {
			$this->from = date(self::DATE_TIME_FORMAT, $this->from_ts);
			$this->to = date(self::DATE_TIME_FORMAT, $this->to_ts);
		}
	}

	private function validate(): void {
		if ($this->errors) {
			return;
		}

		$range_time_parser = new CRangeTimeParser();

		if ($range_time_parser->parse($this->from) === CParser::PARSE_SUCCESS) {
			$this->from_ts = $range_time_parser->getDateTime(true)->getTimestamp();
			$this->from_type = $range_time_parser->getTimeType();
		}
		else {
			$this->errors['from'] = _('Invalid date.');
		}

		if ($range_time_parser->parse($this->to) === CParser::PARSE_SUCCESS) {
			$this->to_ts = $range_time_parser->getDateTime(false)->getTimestamp();
			$this->to_type = $range_time_parser->getTimeType();
		}
		else {
			$this->errors['to'] = _('Invalid date.');
		}

		if (!$this->errors) {
			$this->validatePeriod();
		}
	}

	private function validatePeriod(): void {
		$period = $this->to_ts - $this->from_ts + 1;

		$min_period = $this->getMinPeriod();
		$max_period = $this->getMaxPeriod();

		if ($period < $min_period) {
			$this->errors['from'] = _n('Minimum time period to display is %1$s minute.',
				'Minimum time period to display is %1$s minutes.', (int) ($min_period / SEC_PER_MIN)
			);
		}
		elseif ($period > $max_period + 1) {
			$this->errors['to'] = _n('Maximum time period to display is %1$s day.',
				'Maximum time period to display is %1$s days.', (int) round($max_period / SEC_PER_DAY)
			);
		}
	}

	private function getMinPeriod(): int {
		return ZBX_MIN_PERIOD;
	}

	private function getMaxPeriod(): int {
		static $max_period;

		if ($max_period === null) {
			$range_time_parser = new CRangeTimeParser();
			$range_time_parser->parse('now-'.CSettingsHelper::get(CSettingsHelper::MAX_PERIOD));
			$max_period = 1 + $this->now - $range_time_parser->getDateTime(true)->getTimestamp();
		}

		return $max_period;
	}
}
