<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Class is used to validate if given string is valid HashiCorp Vault secret.
 *
 * Valid token must match format:
 * - <namespace>/<path/to/secret>:<key>
 * - <namespace>/<secret>:<key>
 * - <path/to/secret>:<key>
 * - <path/to/secret>
 */
class CVaultSecretParser extends CParser {

	private $options = [
		'with_key' => true
	];

	/**
	 * Parser constructor.
	 *
	 * @param array  $options
	 * @param bool   $options['with_key']  (optional) Validated string must contain key.
	 */
	public function __construct(array $options = []) {
		if (array_key_exists('with_key', $options)) {
			$this->options['with_key'] = $options['with_key'];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function parse($source, $pos = 0) {
		$this->max = strlen($source);
		$this->start = $pos;
		$this->length = 0;
		$this->match = '';
		$this->errorClear();

		$end = $this->parseMountpoint($source, $pos);
		if ($end == $pos || $source[$end] !== '/') {
			$this->errorPos($source, $end);

			return self::PARSE_FAIL;
		}

		// Cursor to the next char.
		$pos = $end + 1;

		// Parse secret path. Path is mandatory and it's nodes cannot be empty.
		$end = $this->parseSecretPath($source, $pos);
		if ($end == $pos || $source[$pos] === '/' || ($this->max > $end && $source[$end] === '/')) {
			$this->errorPos($source, ($source[$pos] === '/') ? $pos : $end);

			return self::PARSE_FAIL;
		}

		// If not expected key it must be exhausted.
		if (!$this->options['with_key']) {
			$this->match = substr($source, $this->start, $end);
			$this->length = $end - $this->start;

			if ($end != $this->max) {
				$this->errorPos($source, $end);

				return self::PARSE_SUCCESS_CONT;
			}

			return self::PARSE_SUCCESS;
		}

		// Expected key seperator.
		if ($end == $this->max || $source[$end] !== ':') {
			$this->errorPos($source, $end);

			return self::PARSE_FAIL;
		}

		// Cursor to next char.
		$pos = $end + 1;

		// Expected key value.
		$end = $this->parseKeyPath($source, $pos);
		if ($end == $pos) {
			$this->errorPos($source, $end);

			return self::PARSE_FAIL;
		}

		// Expected parser being exhausted.
		if ($end != $this->max) {
			$this->match = substr($source, $this->start, $end);
			$this->errorPos($source, $end);
			$this->length = $end - $this->start;

			return self::PARSE_SUCCESS_CONT;
		}

		$this->match = substr($source, $this->start, $end);
		$this->length = $end - $this->start;

		return self::PARSE_SUCCESS;
	}

	/**
	 * Parse name-space part of the input string.
	 *
	 * @param string  $source String to parse.
	 * @param int     $start  Position to start parse.
	 *
	 * @return int
	 */
	protected function parseMountpoint(string $source, int $start = 0): int {
		while ($start < $this->max && $source[$start] !== '/' && $source[$start] !== ':') {
			$start++;
		}

		return $start;
	}

	/**
	 * Parse secret path part of the input string.
	 *
	 * @param string  $source String to parse.
	 * @param int     $start  Position to start parsing.
	 *
	 * @return int
	 */
	protected function parseSecretPath(string $source, int $start = 0): int {
		$prev_char = null;
		while ($start < $this->max && (preg_match('/\w/u', $source[$start]) == 1 || $source[$start] === '/')) {
			$char = $source[$start];
			if ($char === '/' && $prev_char === '/') {
				return $start - 1;
			}

			$prev_char = $char;
			$start++;
		}

		return $start;
	}

	/**
	 * Parse key part of the input string.
	 *
	 * @param string  $source String to parse.
	 * @param int     $start  Position to start parsing.
	 *
	 * @return bool
	 */
	protected function parseKeyPath($source, $start = 0): int {
		while ($start < $this->max && preg_match('/\w/u', $source[$start]) == 1) {
			$start++;
		}

		return $start;
	}
}
