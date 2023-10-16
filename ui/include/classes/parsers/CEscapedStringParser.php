<?php declare(strict_types = 0);
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
 * Parser is meant to check and extract the string with all backslases escaped.
 */
class CEscapedStringParser extends CParser {

	/**
	 * An error message if string is invalid.
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * Options for parser customization.
	 *
	 * @var array
	 */
	private $options = [
		'characters' => ''
	];

	public function __construct($options = []) {
		if (!array_key_exists('characters', $options)) {
			$options['characters'] = '';
		}

		if (strpos($options['characters'], '\\') === false) {
			$options['characters'] .= '\\';
		}

		$this->options['characters'] = $options['characters'];
	}

	/**
	 * @param string $source
	 * @param int    $offset
	 *
	 * @return int
	 */
	public function parse($source, $offset = 0) {
		$this->length = 0;
		$this->match = '';
		$this->error = '';

		$result = self::PARSE_SUCCESS;

		// Check if all backslash characters in given string are escaped.
		for ($pos = strpos($source, '\\', $offset); $pos !== false; $pos = strpos($source, '\\', $pos + 2)) {
			if (!isset($source[$pos + 1]) || strpos($this->options['characters'], $source[$pos + 1]) === false) {
				$result = $pos > $offset ? self::PARSE_SUCCESS_CONT : self::PARSE_FAIL;
				$this->error = _s('value contains unescaped character at position %1$d', $pos + 1 - $offset);

				break;
			}
		}

		if ($result != self::PARSE_FAIL) {
			$this->length = $result == self::PARSE_SUCCESS ? strlen($source) - $offset : $pos - $offset;
			$this->match = substr($source, $offset, $this->length);
		}

		return $result;
	}

	/**
	 * Returns the error message if string is invalid.
	 *
	 * @return string
	 */
	public function getError(): string {
		return $this->error;
	}
}
