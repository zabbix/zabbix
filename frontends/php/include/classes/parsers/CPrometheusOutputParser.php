<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * A parser for Prometheus output.
 */
class CPrometheusOutputParser extends CParser {

	/**
	 * Parse the given source string.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$trim = " \t\n\r";
		$p = $pos;

		while (isset($source[$p]) && strpos($trim, $source[$p]) !== false) {
			$p++;
		}

		if ($this->parseLabelName($source, $p) === true) {
			while (isset($source[$p]) && strpos($trim, $source[$p]) !== false) {
				$p++;
			}
		}
		else {
			return self::PARSE_FAIL;
		}

		if ($pos == $p) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse label names. It must follow the [a-zA-Z_][a-zA-Z0-9_]* regular expression.
	 *
	 * @param string $source  [IN]      Source string that needs to be parsed.
	 * @param int    $pos     [IN/OUT]  Position offset.
	 *
	 * @return bool
	 */
	private function parseLabelName($source, &$pos) {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*/', substr($source, $pos), $matches)) {
			return false;
		}

		$pos += strlen($matches[0]);

		return true;
	}
}
