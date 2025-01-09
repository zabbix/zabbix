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
 * A parser for IPv4 address.
 */
class CIPv4Parser extends CParser {

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		if (!preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)\.([0-9]+)/', substr($source, $pos), $matches)) {
			return self::PARSE_FAIL;
		}

		for ($i = 1; $i <= 4; $i++) {
			if (strlen($matches[$i]) > 3 || $matches[$i] > 255) {
				return self::PARSE_FAIL;
			}
		}

		$this->match = $matches[0];
		$this->length = strlen($this->match);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}
}
