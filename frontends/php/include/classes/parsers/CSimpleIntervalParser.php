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


/**
 * A parser for simple intervals.
 */
class CSimpleIntervalParser extends CParser {

	const STATE_NEW = 0;
	const STATE_NUM_FOUND = 1;
	const STATE_LETTER_FOUND = 2;

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'suffixes' => 'smhdw'		Allowed time suffixes.
	 *
	 * @var array
	 */
	public $options = ['suffixes' => ZBX_TIME_SUFFIXES];

	public function __construct($options = []) {
		if (array_key_exists('suffixes', $options)) {
			$this->options['suffixes'] = $options['suffixes'];
		}
	}

	/**
	 * Parse the given source string.
	 *
	 * @param string $source	Source string that needs to be parsed.
	 * @param int    $pos		Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;
		$state = self::STATE_NEW;

		if (!isset($source[$p])) {
			return self::PARSE_FAIL;
		}

		while (isset($source[$p])) {
			switch ($state) {
				case self::STATE_NEW:
					if (is_numeric($source[$p])) {
						$state = self::STATE_NUM_FOUND;
					}
					else {
						return self::PARSE_FAIL;
					}
					break;

				case self::STATE_NUM_FOUND:
					if (!is_numeric($source[$p])) {
						if (strpos($this->options['suffixes'], $source[$p]) === false) {
							break 2;
						}
						else {
							$state = self::STATE_LETTER_FOUND;
						}
					}
					break;

				case self::STATE_LETTER_FOUND:
					break 2;
			}

			$p++;
		}

		$this->length = $p - $pos;

		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}
}
