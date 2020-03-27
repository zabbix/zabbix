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


<<<<<<< HEAD
define('_ZBX_TEBIBYTE',	'1099511627776');

// Time suffixes and multipliers.
define('_ZBX_TIME_SUFFIXES', 'smhdw');
define('_ZBX_TIME_SUFFIXES_WITH_YEAR', 'smhdwMy');
define('_ZBX_TIME_SUFFIX_MULTIPLIERS', [
	's' => 1,
	'm' => SEC_PER_MIN,
	'h' => SEC_PER_HOUR,
	'd' => SEC_PER_DAY,
	'w' => SEC_PER_WEEK,
	'M' => SEC_PER_MONTH,
	'y' => SEC_PER_YEAR
]);

// Byte suffixes and multipliers.
define('_ZBX_BYTE_SUFFIXES', 'KMGT');
define('_ZBX_BYTE_SUFFIX_MULTIPLIERS', [
	'K' => ZBX_KIBIBYTE,
	'M' => ZBX_MEBIBYTE,
	'G' => ZBX_GIBIBYTE,
	'T' => _ZBX_TEBIBYTE
]);

define('_ZBX_PREG_NUMBER', '(?<number>-?\d+(\.\d+)?([Ee][+-]?\d+)?)');

=======
>>>>>>> 07266a2eb54eeb012cfecea7d25371b42ffcd32d
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
<<<<<<< HEAD
	private static $suffixes = _ZBX_TIME_SUFFIXES._ZBX_BYTE_SUFFIXES;
=======
	private static $suffixes = ZBX_TIME_SUFFIXES.ZBX_BYTE_SUFFIXES;
>>>>>>> 07266a2eb54eeb012cfecea7d25371b42ffcd32d

	/**
	 * Suffix multiplier table for value calculation.
	 *
	 * @var array
	 */
<<<<<<< HEAD
	private static $suffix_multipliers = _ZBX_BYTE_SUFFIX_MULTIPLIERS + _ZBX_TIME_SUFFIX_MULTIPLIERS;
=======
	private static $suffix_multipliers = ZBX_BYTE_SUFFIX_MULTIPLIERS + ZBX_TIME_SUFFIX_MULTIPLIERS;
>>>>>>> 07266a2eb54eeb012cfecea7d25371b42ffcd32d

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
<<<<<<< HEAD
			? '/^'._ZBX_PREG_NUMBER.'(?<suffix>['.self::$suffixes.'])?/'
			: '/^'._ZBX_PREG_NUMBER.'/';
=======
			? '/^'.ZBX_PREG_NUMBER.'(?<suffix>['.self::$suffixes.'])?/'
			: '/^'.ZBX_PREG_NUMBER.'/';
>>>>>>> 07266a2eb54eeb012cfecea7d25371b42ffcd32d

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
