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
 * Class to validate status code ranges.
 * Ranges can be list of comma separated numeric strings or user macroses.
 *
 * Examples: '100-199, 301, 404, 500-550, {$MACRO}-200, {$MACRO}-{$MACRO}'
 */
class CStatusCodeRangesValidator extends CValidator {

	/**
	 * @var CUserMacroParser
	 */
	private $user_macro_parser;

	/**
	 * If set to true, user macros can be used as part or value in status code ranges.
	 *
	 * @var bool
	 */
	public $usermacros = false;

	/**
	 * Error message if the status codes range string is invalid.
	 *
	 * @var string
	 */
	public $messageInvalid;

	public function __construct(array $options = []) {
		if (array_key_exists('usermacros', $options)) {
			$this->usermacros = $options['usermacros'];
			$this->user_macro_parser = new CUserMacroParser();
			unset($options['usermacros']);
		}
		parent::__construct($options);
	}

	/**
	 * Validate status code ranges string.
	 *
	 * @param string $value  String to validate.
	 *
	 * @return bool
	 */
	public function validate($value) {
		if (!is_string($value) || $value === '') {
			$this->error($this->messageInvalid, $this->stringify($value));

			return false;
		}

		foreach (explode(',', $value) as $range) {
			$range_parts = explode('-', $range);

			if (count($range_parts) != 2 && strpos($range, '-')) {
				$this->error($this->messageInvalid, $value);

				return false;
			}

			foreach ($range_parts as $part) {
				$part = trim($part, " \t\r\n");

				if (!ctype_digit($part) && !($this->usermacros
						&& $this->user_macro_parser->parse($part) == CParser::PARSE_SUCCESS)) {
					$this->error($this->messageInvalid, $value);

					return false;
				}
			}
		}

		return true;
	}
}
