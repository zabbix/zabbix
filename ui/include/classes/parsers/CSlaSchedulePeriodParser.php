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
 * A parser for absolute time in "YYYY[-MM[-DD]][ hh[:mm[:ss]]]" format.
 */
class CSlaSchedulePeriodParser extends CParser {

	/**
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$source = trim($source);

		foreach (explode(',', $source) as $schedule_period) {
			if (!preg_match('/^\s*(?<from_h>\d{1,2}):(?<from_m>\d{2})\s*-\s*(?<to_h>\d{1,2}):(?<to_m>\d{2})\s*$/',
					$schedule_period, $matches)) {
				return self::PARSE_FAIL;
			}

			$from_h = $matches['from_h'];
			$from_m = $matches['from_m'];
			$to_h = $matches['to_h'];
			$to_m = $matches['to_m'];

			$day_period_from = SEC_PER_HOUR * $from_h + SEC_PER_MIN * $from_m;
			$day_period_to = SEC_PER_HOUR * $to_h + SEC_PER_MIN * $to_m;

			if ($from_m > 59 || $to_m > 59 || $day_period_from >= $day_period_to || $day_period_to > SEC_PER_DAY) {
				return self::PARSE_FAIL;
			}
		}

		return  self::PARSE_SUCCESS;
	}
}
