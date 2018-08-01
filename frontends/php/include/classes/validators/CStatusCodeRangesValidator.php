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
 * Comma separated list of numeric strings, user macroses, lld macroses.
 *
 * Example: '100-199, 301, 404, 500-550, {$MACRO}-200, {$MACRO}-{$MACRO}, {#SCODE}-{$MACRO}'
 */
class CStatusCodeRangesValidator extends CValidator {

	/**
	 * @var CStatusCodeRangesParser
	 */
	private $status_code_ranges_parser;

	/**
	 * Options to initialize other parsers.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	/**
	 * Error message if the status codes range string is invalid.
	 *
	 * @var string
	 */
	public $messageInvalid;

	public function __construct(array $options = []) {
		if (array_key_exists('usermacros', $options)) {
			$this->options['usermacros'] = $options['usermacros'];
			unset($options['usermacros']);
		}
		if (array_key_exists('lldmacros', $options)) {
			$this->options['lldmacros'] = $options['lldmacros'];
			unset($options['lldmacros']);
		}

		$this->status_code_ranges_parser = new CStatusCodeRangesParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros']
		]);

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

		// Because trim(" \t\r\n") doesn't work.
		$value = str_replace([' ', "\t", "\r", "\n"], '', $value);

		if ($this->status_code_ranges_parser->parse($value) == CParser::PARSE_SUCCESS) {
			$ranges = $this->status_code_ranges_parser->getRanges();

			// If status codes are not macros, make sure the first status code is smaller than second one.
			foreach ($ranges as $range) {
				if (count($range) > 1 && ctype_digit($range[0]) && ctype_digit($range[1]) && $range[0] > $range[1]) {
					$this->error($this->messageInvalid, $value);

					return false;
				}
			}
		}
		else {
			$this->error($this->messageInvalid, $value);

			return false;
		}

		return true;
	}
}
