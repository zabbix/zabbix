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


class CPeriodTimeParser extends CParser {

	private const REGEX_PATTERN = '/^\s*(?<from_h>\d{1,2}):(?<from_m>\d{2})\s*-\s*(?<to_h>\d{1,2}):(?<to_m>\d{2})\s*$/';
	private array $matches = [];
	private int $day_period_from;
	private int $day_period_to;

	public function parse($source, $pos = 0): int {
		if (strlen($source) == 0) {
			$this->errorPos($source, 0);

			return self::PARSE_FAIL;
		}

		$this->length = 0;
		$this->match = '';

		$p = $pos;

		if (preg_match(self::REGEX_PATTERN, $source, $this->matches)) {
			$p += strlen($this->matches[0]);
		}
		else {
			return self::PARSE_FAIL;
		}

		if ($p == $pos) {
			return self::PARSE_FAIL;
		}

		$expected_keys = ['from_h', 'from_m', 'to_h', 'to_m'];

		if (array_diff($expected_keys, array_keys($this->matches))) {
			return self::PARSE_FAIL;
		}
		else {
			$this->day_period_from = $this->prepareDayPeriod(
				(int) $this->matches['from_h'], (int) $this->matches['from_m']
			);
			$this->day_period_to = $this->prepareDayPeriod(
				(int) $this->matches['to_h'], (int) $this->matches['to_m']
			);
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	public function getMatches(): array {
		return $this->matches;
	}

	public function getDayPeriodFrom(): int {
		return $this->day_period_from;
	}

	public function getDayPeriodTo(): int {
		return $this->day_period_to;
	}

	private function prepareDayPeriod(int $h, int $m): int {
		return SEC_PER_HOUR * $h + SEC_PER_MIN * $m;
	}
}
