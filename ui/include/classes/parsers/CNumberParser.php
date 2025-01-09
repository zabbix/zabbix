<?php declare(strict_types = 0);
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
		'with_float' => true,
		'with_size_suffix' => false,
		'with_time_suffix' => false,
		'with_year' => false,
		'is_binary_size' => true
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
	private $suffixes = '';

	/**
	 * Suffix multiplier table for value calculation.
	 *
	 * @var array
	 */
	private $suffix_multipliers = [];

	public function __construct(array $options = []) {
		$this->options = array_replace($this->options, array_intersect_key($options, $this->options));

		if (!$this->options['with_time_suffix'] && $this->options['with_year']) {
			throw new Exception('Ambiguous options.');
		}

		if ($this->options['with_size_suffix']) {
			$this->suffixes .= ZBX_SIZE_SUFFIXES;

			$this->suffix_multipliers += $this->options['is_binary_size']
				? ZBX_SIZE_SUFFIX_MULTIPLIERS_BINARY
				: ZBX_SIZE_SUFFIX_MULTIPLIERS;
		}

		if ($this->options['with_time_suffix']) {
			$this->suffixes .= $this->options['with_year'] ? ZBX_TIME_SUFFIXES_WITH_YEAR : ZBX_TIME_SUFFIXES;
			$this->suffix_multipliers += ZBX_TIME_SUFFIX_MULTIPLIERS;
		}
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

		$pattern = $this->options['with_float'] ? ZBX_PREG_NUMBER : ZBX_PREG_INT;
		$pattern = ($this->options['with_size_suffix'] || $this->options['with_time_suffix'])
			? '/^'.$pattern.'(?<suffix>['.$this->suffixes.'])?/'
			: '/^'.$pattern.'/';

		if (!preg_match($pattern, $fragment, $matches)) {
			return self::PARSE_FAIL;
		}

		$number = $this->options['with_float'] ? $matches['number'] : $matches['int'];

		if ($number[0] === '-' && !$this->options['with_minus']) {
			return self::PARSE_FAIL;
		}

		$this->length = strlen($matches[0]);
		$this->match = $matches[0];

		$this->number = $number;
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
			$number *= $this->suffix_multipliers[$this->suffix];
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
