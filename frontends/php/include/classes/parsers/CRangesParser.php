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
 * A parser for ranges separated by a comma.
 */
class CRangesParser extends CParser {

	/**
	 * Status code range parser.
	 *
	 * @var CRangeParser
	 */
	private $range_parser;

	/**
	 * Array of status code ranges.
	 *
	 * @var array
	 */
	private $ranges = [];

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
	 * @param array $options   An array of options to initialize other parsers.
	 */
	public function __construct($options = []) {
		if (array_key_exists('usermacros', $options)) {
			$this->options['usermacros'] = $options['usermacros'];
		}
		if (array_key_exists('lldmacros', $options)) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}

		$this->range_parser = new CRangeParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros']
		]);
	}

	/**
	 * Parse the given status codes separated by a comma.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->ranges = [];

		$ranges = [];
		$p = $pos;

		while (isset($source[$p])) {
			if ($this->range_parser->parse($source, $p) != self::PARSE_FAIL) {
				if ($ranges && $source[$p - 1] !== ',') {
					break;
				}

				$p += $this->range_parser->getLength();
				$ranges[] = $this->range_parser->getRanges();
			}
			elseif ($source[$p] === ',' && $p != $pos && isset($source[$p - 1]) && $source[$p - 1] !== ',') {
				$p++;
			}
			else {
				break;
			}
		}

		if ($p == $pos) {
			return self::PARSE_FAIL;
		}

		if (isset($source[$p - 1]) && $source[$p - 1] === ',') {
			$p--;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);
		$this->ranges = $ranges;

		return (isset($source[$p])) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Retrieve the status code ranges.
	 *
	 * @return array
	 */
	public function getRanges() {
		return $this->ranges;
	}
}
