<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
	 * Array of ranges strings. Range value with suffix will be stored as string of calculated value, value "1K"
	 * will be stored as "1024".
	 *
	 * @var array
	 */
	private $ranges = [];

	/**
	 * Options to initialize other parsers.
	 *
	 * usermacros   Allow usermacros in ranges.
	 * lldmacros    Allow lldmacros in ranges.
	 * with_minus   Allow negative ranges.
	 * with_float   Allow float number ranges.
	 * with_suffix  Allow number ranges with suffix, supported suffixes see CNumberParser::$suffixes.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false,
		'with_minus' => false,
		'with_float' => false,
		'with_suffix' => false
	];

	/**
	 * @param array $options   An array of options to initialize other parsers.
	 */
	public function __construct($options = []) {
		$this->options = $options + $this->options;

		$this->range_parser = new CRangeParser($this->options);
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

		$p = $pos;

		while (true) {
			if ($this->range_parser->parse($source, $p) == self::PARSE_FAIL) {
				break;
			}

			$p += $this->range_parser->getLength();
			$this->ranges[] = $this->range_parser->getRange();

			if (!isset($source[$p]) || $source[$p] !== ',') {
				break;
			}

			$p++;
		}

		if ($p == $pos) {
			return self::PARSE_FAIL;
		}

		if ($source[$p - 1] === ',') {
			$p--;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
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
