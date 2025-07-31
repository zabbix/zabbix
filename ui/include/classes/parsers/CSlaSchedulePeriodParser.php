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
 * A parser for time periods.
 */
class CSlaSchedulePeriodParser extends CParser {

	private string $error;

	/**
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->match = '';
		$this->length = 0;
		$this->error = '';

		$source = trim($source);

		foreach (explode(',', $source) as $schedule_period) {
			if (!preg_match('/^\s*(?<from_h>\d{1,2}):(?<from_m>\d{2})\s*-\s*(?<to_h>\d{1,2}):(?<to_m>\d{2})\s*$/',
					$schedule_period, $matches)) {
				$this->error = _('a time period is expected');

				return self::PARSE_FAIL;
			}

			$from_h = $matches['from_h'];
			$from_m = $matches['from_m'];
			$to_h = $matches['to_h'];
			$to_m = $matches['to_m'];

			$day_period_from = SEC_PER_HOUR * $from_h + SEC_PER_MIN * $from_m;
			$day_period_to = SEC_PER_HOUR * $to_h + SEC_PER_MIN * $to_m;

			if ($from_m > 59 || $to_m > 59 || $day_period_to > SEC_PER_DAY) {
				$this->error = _('a time period is expected');

				return self::PARSE_FAIL;
			}

			if ($day_period_from >= $day_period_to) {
				$this->error = _('start time must be less than end time');

				return self::PARSE_FAIL;
			}

			// Validate period uniqueness.
			$result[] = [
				'period_from' => SEC_PER_DAY + $day_period_from,
				'period_to' => SEC_PER_DAY + $day_period_to
			];

			if (count($result) !== count(array_unique(array_map('json_encode', $result)))) {
				$this->error = _('periods must be unique');

				return self::PARSE_FAIL;
			}
		}

		$this->match = substr($source, $pos, strlen($source));
		$this->length = strlen($source) - $pos;

		return self::PARSE_SUCCESS;
	}

	public function getError(): string {
		return $this->error;
	}
}
