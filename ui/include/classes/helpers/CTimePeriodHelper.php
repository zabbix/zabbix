<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CTimePeriodHelper {

	/**
	 * Get minimal supported time period.
	 *
	 * @return int
	 */
	public static function getMinPeriod(): int {
		return ZBX_MIN_PERIOD;
	}

	/**
	 * Get maximal supported time period.
	 *
	 * @param DateTimeZone|null $timezone
	 *
	 * @return int
	 */
	public static function getMaxPeriod(?DateTimeZone $timezone = null): int {
		static $max_period;

		if ($max_period === null) {
			$range_time_parser = new CRangeTimeParser();
			$range_time_parser->parse('now-'.CSettingsHelper::get(CSettingsHelper::MAX_PERIOD));
			$max_period = time() - $range_time_parser->getDateTime(true, $timezone)->getTimestamp();
		}

		return $max_period;
	}

	/**
	 * Increment the time period.
	 *
	 * @param array $time_period
	 *        string $time_period['from']     Absolute or relative start date time.
	 *        int    $time_period['from_ts']  Timestamp of the start date time.
	 *        string $time_period['to']       Absolute or relative end date time.
	 *        int    $time_period['to_ts']    Timestamp of the ending date time.
	 */
	public static function increment(array &$time_period): void {
		$period = $time_period['to_ts'] - $time_period['from_ts'] + 1;
		$offset = min($period, time() - $time_period['to_ts']);

		$time_period['from_ts'] += $offset;
		$time_period['to_ts'] += $offset;

		$time_period['from'] = date(ZBX_FULL_DATE_TIME, $time_period['from_ts']);
		$time_period['to'] = date(ZBX_FULL_DATE_TIME, $time_period['to_ts']);
	}

	/**
	 * Decrement the time period.
	 *
	 * @param array $time_period
	 *        string $time_period['from']     Absolute or relative start date time.
	 *        int    $time_period['from_ts']  Timestamp of the start date time.
	 *        string $time_period['to']       Absolute or relative end date time.
	 *        int    $time_period['to_ts']    Timestamp of the ending date time.
	 */
	public static function decrement(array &$time_period): void {
		$period = $time_period['to_ts'] - $time_period['from_ts'] + 1;
		$offset = min($period, $time_period['from_ts']);

		$time_period['from_ts'] -= $offset;
		$time_period['to_ts'] -= $offset;

		$time_period['from'] = date(ZBX_FULL_DATE_TIME, $time_period['from_ts']);
		$time_period['to'] = date(ZBX_FULL_DATE_TIME, $time_period['to_ts']);
	}

	/**
	 * Zoom out the time period.
	 *
	 * @param array $time_period
	 *        string $time_period['from']     Absolute or relative start date time.
	 *        int    $time_period['from_ts']  Timestamp of the start date time.
	 *        string $time_period['to']       Absolute or relative end date time.
	 *        int    $time_period['to_ts']    Timestamp of the ending date time.
	 */
	public static function zoomOut(array &$time_period): void {
		$period = $time_period['to_ts'] - $time_period['from_ts'] + 1;

		$to_offset = min((int) ($period / 2), time() - $time_period['to_ts']);
		$from_offset = min($period - $to_offset, $time_period['from_ts']);

		$time_period['from_ts'] -= $from_offset;
		$time_period['to_ts'] += $to_offset;

		$max_period = self::getMaxPeriod();

		if ($time_period['to_ts'] - $time_period['from_ts'] + 1 > $max_period) {
			$time_period['from_ts'] = $time_period['to_ts'] - $max_period;
		}

		$time_period['from'] = date(ZBX_FULL_DATE_TIME, $time_period['from_ts']);
		$time_period['to'] = date(ZBX_FULL_DATE_TIME, $time_period['to_ts']);
	}

	/**
	 * Apply correct formatting to the time period after modifications made by user.
	 *
	 * @param array $time_period
	 *        string $time_period['from']     Absolute or relative start date time.
	 *        int    $time_period['from_ts']  Timestamp of the start date time.
	 *        string $time_period['to']       Absolute or relative end date time.
	 *        int    $time_period['to_ts']    Timestamp of the ending date time.
	 */
	public static function rangeChange(array &$time_period): void {
		$absolute_time_parser = new CAbsoluteTimeParser();

		foreach (['from' => 'from_ts', 'to' => 'to_ts'] as $field => $field_ts) {
			if ($absolute_time_parser->parse($time_period[$field]) === CParser::PARSE_SUCCESS) {
				$time_period[$field] = date(ZBX_FULL_DATE_TIME, $time_period[$field_ts]);
			}
		}
	}

	/**
	 * Apply relative offsets to the time period.
	 *
	 * @param array $time_period
	 *        string $time_period['from']     Absolute or relative start date time.
	 *        int    $time_period['from_ts']  Timestamp of the start date time.
	 *        string $time_period['to']       Absolute or relative end date time.
	 *        int    $time_period['to_ts']    Timestamp of the ending date time.
	 *
	 * @param int    $from_offset             Relative offset in seconds from the start date time.
	 * @param int    $to_offset               Relative offset in seconds from the end date time.
	 */
	public static function rangeOffset(array &$time_period, int $from_offset, int $to_offset): void {
		$time_period['from_ts'] += $from_offset;
		$time_period['to_ts'] -= $to_offset;

		$time_period['from'] = date(ZBX_FULL_DATE_TIME, $time_period['from_ts']);
		$time_period['to'] = date(ZBX_FULL_DATE_TIME, $time_period['to_ts']);
	}
}
