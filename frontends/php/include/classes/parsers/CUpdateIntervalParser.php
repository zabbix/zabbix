<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


/**
 * A parser for update intervals.
 */
class CUpdateIntervalParser extends CParser {

	private $simple_interval_parser;
	private $flexible_interval_parser;
	private $scheduling_interval_parser;
	private $user_macro_parser;
	private $delay;
	private $intervals = [];

	public function __construct($options = []) {
		$this->simple_interval_parser = new CSimpleIntervalParser();
		$this->flexible_interval_parser = new CFlexibleIntervalParser();
		$this->scheduling_interval_parser = new CSchedulingIntervalParser();
		$this->user_macro_parser = new CUserMacroParser();
	}

	/**
	 * Parse the given source string. The string must contain simple interval and possibly more multiple intervals of
	 * two types - flexible and scheduling - separated by a semicolon.
	 *
	 * @param string $source	Source string that needs to be parsed.
	 * @param int    $pos		Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;
		$i = 0;

		// First interval must be simple interval (or macro). Other intervals may be mixed and repeat multiple times.
		if ($this->simple_interval_parser->parse($source, $p) !== self::PARSE_FAIL) {
			$p += $this->simple_interval_parser->getLength();
			$this->delay = $this->simple_interval_parser->getmatch();
		}
		elseif ($this->user_macro_parser->parse($source, $p) !== self::PARSE_FAIL) {
			$p += $this->user_macro_parser->getLength();
			$this->delay = $this->user_macro_parser->getmatch();
		}
		else {
			return self::PARSE_FAIL;
		}

		if (isset($source[$p]) && $source[$p] !== ';' && $source[$p] !== '') {
			$this->length = $p - $pos;
			$this->match = substr($source, $pos, $this->length);

			return self::PARSE_SUCCESS_CONT;
		}

		$p++;

		for (; isset($source[$p]); $p++) {
			if ($this->flexible_interval_parser->parse($source, $p) !== self::PARSE_FAIL) {
				$p += $this->flexible_interval_parser->getLength();

				/*
				 * It's possible that flexible interval consists of two macros
				 * {$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD} but it still counts as flexible interval.
				 */
				$this->intervals[$i++] = [
					'type' => ITEM_DELAY_FLEXIBLE,
					'interval' => $this->flexible_interval_parser->getMatch()
				];

				if (isset($source[$p]) && $source[$p] !== ';' && $source[$p] !== '') {
					$this->length = $p - $pos - 1;
					$this->match = substr($source, $pos, $this->length);

					return self::PARSE_SUCCESS_CONT;
				}
				else {
					continue;
				}
			}
			elseif ($this->scheduling_interval_parser->parse($source, $p) !== self::PARSE_FAIL) {
				$p += $this->scheduling_interval_parser->getLength();

				$this->intervals[$i++] = [
					'type' => ITEM_DELAY_SCHEDULING,
					'interval' => $this->scheduling_interval_parser->getMatch()
				];

				if (isset($source[$p]) && $source[$p] !== ';' && $source[$p] !== '') {
					$this->length = $p - $pos;
					$this->match = substr($source, $pos, $this->length);

					return self::PARSE_SUCCESS_CONT;
				}
				else {
					continue;
				}
			}
			elseif ($this->user_macro_parser->parse($source, $p) !== self::PARSE_FAIL) {
				// Scheduling interval as macro.

				$p += $this->user_macro_parser->getLength();

				$this->intervals[$i++] = [
					'type' => ITEM_DELAY_SCHEDULING,
					'interval' => $this->user_macro_parser->getMatch()
				];

				if (isset($source[$p]) && $source[$p] !== ';' && $source[$p] !== '') {
					$this->length = $p - $pos - 1;
					$this->match = substr($source, $pos, $this->length);

					return self::PARSE_SUCCESS_CONT;
				}
				else {
					continue;
				}
			}
			else {
				return self::PARSE_FAIL;
			}
		}

		$this->length = $p - $pos - 1;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
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
	 * Get all intervals or specificly flexible or scheduling intervals.
	 *
	 * @param int $type			If null get both types, else either ITEM_DELAY_FLEXIBLE or ITEM_DELAY_SCHEDULING
	 *
	 * @return array
	 */
	public function getIntervals($type = null) {
		if ($type === null) {
			return $this->intervals;
		}
		else {
			$intervals = [];

			foreach ($this->intervals as $interval) {
				if ($interval['type'] == $type) {
					$intervals[] = $interval['interval'];
				}
			}

			return $intervals;
		}
	}
}
