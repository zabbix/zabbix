<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * Class containing methods for status codes parsing.
 */
class CStatusCodesParser {

	/**
	 * @var CUserMacroParser
	 */
	private $user_macro_parser;

	/**
	 * Supported options:
	 *   usermacros     allow usermacros syntax
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		if (array_key_exists('usermacros', $options)) {
			$this->options['usermacros'] = $options['usermacros'];

			if ($options['usermacros']) {
				$this->user_macro_parser = new CUserMacroParser();
			}
		}

	}

	/**
	 * Validate comma-separated status code ranges.
	 * Will return CParser::PARSE_SUCCESS or CParser::PARSE_FAIL values.
	 *
	 * @param string $ranges
	 *
	 * @return int
	 */
	public function parse($ranges) {
		foreach (explode(',', $ranges) as $range) {
			$range = trim($range, " \t\r\n");
			$range = explode('-', $range);

			if (count($range) > 2) {
				return CParser::PARSE_FAIL;
			}

			foreach ($range as $part) {
				if (!ctype_digit($part) && !($this->options['usermacros']
						&& $this->user_macro_parser->parse($part) == CParser::PARSE_SUCCESS)) {
					return CParser::PARSE_FAIL;
				}
			}
		}

		return CParser::PARSE_SUCCESS;
	}
}
