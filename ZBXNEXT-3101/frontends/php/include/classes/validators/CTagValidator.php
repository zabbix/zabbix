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


class CTagValidator {

	/**
	 * Parser for function macros.
	 *
	 * @var CMacroFunctionParser
	 */
	private $macro_function_parser;

	/**
	 * Validation errors.
	 *
	 * @var string
	 */
	private $error;

	public function __construct() {
		$this->macro_function_parser = new CMacroFunctionParser(['{ITEM.VALUE}'], ['allow_reference' => true]);
	}

	/**
	 * Checks if the given trigger tag is a valid. If tag is invalid, sets an error and returns false.
	 * Otherwise returns true.
	 *
	 * @param string $source
	 *
	 * @return bool
	 */
	public function validate($source) {
		$p = 0;

		if (!isset($source[$p])) {
			$this->error = _('cannot be empty');

			return false;
		}

		do {
			if ($source[$p] == '/') {
				$this->error = _('unacceptable characters are used');

				return false;
			}
			elseif ($this->macro_function_parser->parse($source, $p) != CParser::PARSE_FAIL) {
				$p += $this->macro_function_parser->getLength();
			}
			else {
				$p++;
			}
		}
		while (isset($source[$p]));

		$this->error = '';

		return true;
	}

	/**
	 * Get validation error.
	 *
	 * @return string
	 */
	public function getError() {
		return $this->error;
	}
}
