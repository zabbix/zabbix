<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Parses a time period in notation 00:00 - 24:00.
 */
class CTimeRangeParser extends CParser {

	private string $time_from = '';
	private string $time_till = '';

	public function parse($source, $pos = 0): int {
		$size = strlen($source);

		$time_from_pos = $pos;
		if (self::PARSE_FAIL == $this->parseTime($source, $pos)) {
			$this->errorPos($source, $pos);

			return self::PARSE_FAIL;
		}
		else {
			$this->time_from = substr($source, $time_from_pos, $pos - $time_from_pos);
		}

		if (self::PARSE_FAIL == $this->parseSeparator($source, $pos, $size)) {
			$this->errorPos($source, $pos);

			return self::PARSE_FAIL;
		}

		$time_till_pos = $pos;
		if (self::PARSE_FAIL == $this->parseTime($source, $pos)) {
			$this->errorPos($source, $pos);

			return self::PARSE_FAIL;
		}
		else {
			$this->time_till = substr($source, $time_till_pos, $pos - $time_till_pos);
		}

		return $size > $pos ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	private function parseHours(string $source, int &$pos, ?string &$hours = null): int {
		$hours = substr($source, $pos, 1);
		$hour_lookahead = substr($source, $pos + 1, 1);

		if (is_numeric($hour_lookahead)) {
			$hours = $hours.$hour_lookahead;
			$pos += 2;
		}
		else {
			$pos += 1;
		}

		return $hours <= 24 && $hours >= 0 ? self::PARSE_SUCCESS : self::PARSE_FAIL;
	}

	private function parseMinutes(string $source, int &$pos, string $hours): int {
		$minutes = substr($source, $pos, 2);
		$length = strlen($minutes);
		$pos += $length;

		if ($length < 2) {
			return self::PARSE_FAIL;
		}

		if ($hours == 24 && $minutes > 0) {
			return self::PARSE_FAIL;
		}

		return $minutes < 60 && $minutes >= 0 ? self::PARSE_SUCCESS : self::PARSE_FAIL;
	}

	private function parseTime(string $source, int &$pos): int {
		if (self::PARSE_FAIL == $this->parseHours($source, $pos, $hours)) {
			return self::PARSE_FAIL;
		}

		if (substr($source, $pos, 1) !== ':') {
			return self::PARSE_FAIL;
		}

		$pos ++;

		if (self::PARSE_FAIL == $this->parseMinutes($source, $pos, $hours)) {
			return self::PARSE_FAIL;
		}

		return self::PARSE_SUCCESS;
	}

	private function parseSeparator(string $source, int &$pos, int $size): int {
		while ($size > $pos) {
			$char = $source[$pos];

			if ($char === ' ') {
				$pos ++;
				continue;
			}
			elseif ($char === '-') {
				$pos ++;
				break;
			}
			else {
				return self::PARSE_FAIL;
			}
		}

		while ($size > $pos) {
			$char = $source[$pos];

			if ($char === ' ') {
				$pos ++;
				continue;
			}
			else {
				return self::PARSE_SUCCESS_CONT;
			}
		}

		return self::PARSE_SUCCESS;
	}

	public function getTokens(): array {
		[$h_from, $m_from] = explode(':', $this->time_from);
		[$h_till, $m_till] = explode(':', $this->time_till);

		return [$h_from, $m_from, $h_till, $m_till];
	}
}
