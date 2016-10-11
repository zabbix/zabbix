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
	 * An options array
	 *
	 * Supported options:
	 *   'item_macros' => true  allow {{ITEM.VALUE}.func()} macros
	 *
	 * @var array
	 */
	public $options = ['item_macros' => true];

	/**
	 * Parser for function macros.
	 *
	 * @var CMacroFunctionParser
	 */
	private $macro_function_parser;

	/**
	 * Parser for user macros.
	 *
	 * @var CUserMacroParser
	 */
	protected $user_macro_parser;

	/**
	 * Validation errors.
	 *
	 * @var string
	 */
	private $error;

	/**
	 * @param array $options
	 * @param bool $options['item_macros']
	 */
	public function __construct(array $options = []) {
		if (array_key_exists('item_macros', $options)) {
			$this->options['item_macros'] = $options['item_macros'];
		}

		if ($this->options['item_macros']) {
			$this->macro_function_parser = new CMacroFunctionParser(['{ITEM.VALUE}'], ['allow_reference' => true]);
		}
		$this->user_macro_parser = new CUserMacroParser();
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
			elseif ($this->options['item_macros']
					&& $this->macro_function_parser->parse($source, $p) != CParser::PARSE_FAIL) {
				$p += $this->macro_function_parser->getLength();
			}
			elseif ($this->user_macro_parser->parse($source, $p) != CParser::PARSE_FAIL) {
				$p += $this->user_macro_parser->getLength();
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
