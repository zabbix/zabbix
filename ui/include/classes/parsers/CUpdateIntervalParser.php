<?php
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


/**
 * A parser for update intervals.
 */
class CUpdateIntervalParser extends CParser {

	private $simple_interval_parser;
	private $flexible_interval_parser;
	private $scheduling_interval_parser;

	private $delay;
	private $intervals = [];
	private $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	public function __construct($options = []) {
		if (array_key_exists('usermacros', $options)) {
			$this->options['usermacros'] = $options['usermacros'];
		}
		if (array_key_exists('lldmacros', $options)) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}

		$this->simple_interval_parser = new CSimpleIntervalParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros']
		]);
		$this->flexible_interval_parser = new CFlexibleIntervalParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros']
		]);
		$this->scheduling_interval_parser = new CSchedulingIntervalParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros']
		]);
	}

	/**
	 * Parse the given source string. The string must contain simple interval and possibly more multiple intervals of
	 * two types - flexible and scheduling - separated by a semicolon.
	 *
	 * (simple|{$M}|{#M});(flexible|scheduled|{$M}|{#M});...
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->delay = '';
		$this->intervals = [];

		$p = $pos;

		// First interval must be simple interval (or macro). Other intervals may be mixed and repeat multiple times.
		if ($this->simple_interval_parser->parse($source, $p) == self::PARSE_FAIL) {
			$this->errorPos($source, $p);

			return self::PARSE_FAIL;
		}
		$p += $this->simple_interval_parser->getLength();
		$this->delay = $this->simple_interval_parser->getMatch();

		while (isset($source[$p]) && $source[$p] === ';') {
			$p++;

			if ($this->flexible_interval_parser->parse($source, $p) != self::PARSE_FAIL) {
				$p += $this->flexible_interval_parser->getLength();

				$this->intervals[] = [
					'type' => ITEM_DELAY_FLEXIBLE,
					'interval' => $this->flexible_interval_parser->getMatch(),
					'update_interval' => $this->flexible_interval_parser->getUpdateInterval(),
					'time_period' => $this->flexible_interval_parser->getTimePeriod()
				];
			}
			elseif ($this->scheduling_interval_parser->parse($source, $p) != self::PARSE_FAIL) {
				$p += $this->scheduling_interval_parser->getLength();

				$this->intervals[] = [
					'type' => ITEM_DELAY_SCHEDULING,
					'interval' => $this->scheduling_interval_parser->getMatch()
				];
			}
			else {
				$p--;
				break;
			}
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		if (!isset($source[$p])) {
			return self::PARSE_SUCCESS;
		}

		$this->errorPos($source, $p);

		return self::PARSE_SUCCESS_CONT;
	}

	/**
	 * Get delay value from source string.
	 *
	 * @return string
	 */
	public function getDelay() {
		return $this->delay;
	}

	/**
	 * Get all intervals, or specifically flexible or scheduling ones.
	 *
	 * @param int|null $type  ITEM_DELAY_FLEXIBLE, ITEM_DELAY_SCHEDULING, or null for both.
	 *
	 * @return array
	 */
	public function getIntervals(?int $type = null) {
		if ($type === null) {
			return $this->intervals;
		}

		$intervals = [];

		foreach ($this->intervals as $interval) {
			if ($interval['type'] == $type) {
				$intervals[] = $interval['interval'];
			}
		}

		return $intervals;
	}
}
