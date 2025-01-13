<?php declare(strict_types=0);
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


/**
 * Class for validating port number or port number range.
 */
class CPortRangeParser extends CParser {

	/**
	 * Parse the given port number range.
	 *
	 * @param string $source Source string that needs to be parsed.
	 * @param int $pos Position offset.
	 * @return int
	 */
	public function parse($source, $pos = 0): int {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		if ($this->validatePortNumberRange($source)) {
			$p += strlen($source);
		}

		$length = $p - $pos;

		if ($length == 0) {
			$this->errorPos($source, 0);

			return self::PARSE_FAIL;
		}

		$this->length = $length;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Validate port number range(s).
	 */
	private function validatePortNumberRange(string $value): bool {
		$port_parser = new CPortParser();

		foreach (explode(',', $value) as $port_range) {
			$port_range = explode('-', $port_range);

			if (count($port_range) > 2) {
				return false;
			}

			foreach ($port_range as $port) {
				if ($port_parser->parse($port) !== CParser::PARSE_SUCCESS) {
					return false;
				}
			}
		}

		return true;
	}
}
