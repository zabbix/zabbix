<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * A parser for time period.
 */
class CTimePeriodParser extends CParser {

	// Possible parsing states.
	const STATE_NEW = 0;
	const STATE_WEEK_DAY_FROM = 1;
	const STATE_WEEK_DAY_TILL = 2;
	const STATE_HOUR_FROM = 3;
	const STATE_MINUTE_FROM = 4;
	const STATE_HOUR_TILL = 5;
	const STATE_MINUTE_TILL = 6;

	/**
	 * Parse the given period.
	 *
	 * @param string $source	Source string that needs to be parsed.
	 * @param int    $pos		Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		if (!isset($source[$p])) {
			return self::PARSE_FAIL;
		}

		$week_day_from = '';
		$week_day_till = '';
		$hours_from = '';
		$hours_till = '';
		$minutes_from = '';
		$minutes_till = '';

		$state = self::STATE_NEW;

		while (isset($source[$p])) {
			switch ($state) {
				case self::STATE_NEW:
					if (!is_numeric($source[$p])) {
						return self::PARSE_FAIL;
					}

					$week_day_from = $source[$p];

					$state = self::STATE_WEEK_DAY_FROM;
					break;

				case self::STATE_WEEK_DAY_FROM:
					switch ($source[$p]) {
						case '-':
							$state = self::STATE_WEEK_DAY_TILL;
							break;

						case ',':
							$state = self::STATE_HOUR_FROM;
							break;

						default:
							return self::PARSE_FAIL;
					}
					break;

				case self::STATE_WEEK_DAY_TILL:
					switch ($source[$p]) {
						case ',':
							$state = self::STATE_HOUR_FROM;
							break;

						default:
							if (!is_numeric($source[$p])) {
								return self::PARSE_FAIL;
							}

							$week_day_till .= $source[$p];
							break;
					}
					break;

				case self::STATE_HOUR_FROM:
					switch ($source[$p]) {
						case ':':
							$state = self::STATE_MINUTE_FROM;
							break;

						default:
							if (!is_numeric($source[$p])) {
								return self::PARSE_FAIL;
							}

							$hours_from .= $source[$p];
					}
					break;

				case self::STATE_MINUTE_FROM:
					switch ($source[$p]) {
						case '-':
							$state = self::STATE_HOUR_TILL;
							break;

						default:
							if (!is_numeric($source[$p])) {
								return self::PARSE_FAIL;
							}

							$minutes_from .= $source[$p];
					}
					break;

				case self::STATE_HOUR_TILL:
					switch ($source[$p]) {
						case ':':
							$state = self::STATE_MINUTE_TILL;
							break;

						default:
							if (!is_numeric($source[$p])) {
								return self::PARSE_FAIL;
							}

							$hours_till .= $source[$p];
					}
					break;

				case self::STATE_MINUTE_TILL:
					if (is_numeric($source[$p])) {
						$minutes_till .= $source[$p];

						if (strlen($minutes_till) > 2) {
							$this->length = $p - $pos;
							$this->match = substr($source, $pos, $this->length);

							return self::PARSE_SUCCESS_CONT;
						}

						if (strlen($minutes_till) != 1 && strlen($minutes_till) != 2) {
							return self::PARSE_FAIL;
						}
					}
					else {
						if (strlen($minutes_till) >= 2) {
							$this->length = $p - $pos;
							$this->match = substr($source, $pos, $this->length);

							return self::PARSE_SUCCESS_CONT;
						}

						return self::PARSE_FAIL;
					}
			}

			$p++;
		}

		// String can end at any state. Validate the last entered characters depeding on the last state once more.
		switch ($state) {
			case self::STATE_MINUTE_TILL:
				if ($week_day_from < 1 || $week_day_from > 7 || strlen($week_day_till) > 1 || $week_day_till > 7
						|| (strlen($week_day_till) == 1 && $week_day_from > $week_day_till)
						|| (strlen($hours_from) != 1 && strlen($hours_from) != 2) || $hours_from > 24
						|| (strlen($hours_till) != 1 && strlen($hours_till) != 2) || $hours_till > 24
						|| $hours_from > $hours_till || strlen($minutes_from) != 2 || $minutes_from > 59
						|| strlen($minutes_till) != 2 || $minutes_till > 59
						|| ($hours_from == $hours_till && $minutes_from >= $minutes_till)
						|| ($hours_till == 24 && $minutes_till != 0)) {
					return self::PARSE_FAIL;
				}
				break;

			default:
				return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}
}
