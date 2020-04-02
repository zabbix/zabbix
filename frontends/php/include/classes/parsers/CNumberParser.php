<?php declare(strict_types = 1);
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
 * A parser for numbers with optional time or byte suffix.
 */
class CNumberParser extends CParser {

	/**
	* Parser options.
	*
	* @var array
	*/
	private $options = [
		'with_minus' => true,
		'with_suffix' => false
	];

	/**
	 * Parsed number without time or byte suffix.
	 *
	 * @var string
	 */
	private $number;

	/**
	 * Parsed time or byte suffix, or null, if wasn't found.
	 *
	 * @var string|null
	 */
	private $suffix;

	/**
	 * Acceptable time and byte suffixes.
	 *
	 * @var string
	 */
	private static $suffixes = ZBX_TIME_SUFFIXES.ZBX_BYTE_SUFFIXES;

	/**
	 * Suffix multiplier table for value calculation.
	 *
	 * @var array
	 */
	private static $suffix_multipliers = ZBX_BYTE_SUFFIX_MULTIPLIERS + ZBX_TIME_SUFFIX_MULTIPLIERS;

	public function __construct(array $options = []) {
		$this->options = array_replace($this->options, array_intersect_key($options, $this->options));
	}

	/**
	 * Parse number with optional time or byte suffix.
	 *
	 * !!! Don't forget sync code with C !!!
	 *
	 * @param string $source  string to parse
	 * @param int    $pos     position to start from
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0): int {
		$this->length = 0;
		$this->match = '';
		$this->number = null;
		$this->suffix = null;

		$fragment = substr($source, $pos);

		$pattern = $this->options['with_suffix']
			? '/^'.ZBX_PREG_NUMBER.'(?<suffix>['.self::$suffixes.'])?/'
			: '/^'.ZBX_PREG_NUMBER.'/';

		if (!preg_match($pattern, $fragment, $matches)) {
			return self::PARSE_FAIL;
		}

		if ($matches['number'][0] === '-' && !$this->options['with_minus']) {
			return self::PARSE_FAIL;
		}

		$this->length = strlen($matches[0]);
		$this->match = $matches[0];

		$this->number = $matches['number'];
		$this->suffix = array_key_exists('suffix', $matches) ? $matches['suffix'] : null;

		return ($pos + $this->length < strlen($source)) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Calculate value of parsed number in a decimal notation.
	 *
	 * @return float
	 */
	public function calcValue(): float {
		$number = (float) $this->number;

		if ($this->suffix !== null) {
			$number *= self::$suffix_multipliers[$this->suffix];
		}

		return $number;
	}

	/**
	 * Get suffix of parsed number.
	 *
	 * @return string|null
	 */
	public function getSuffix(): ?string {
		return $this->suffix;
	}
}
