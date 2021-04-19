<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Class is used to parse <sec|#num>:<time_shift> trigger parameter.
 */
class CPeriodParser extends CParser {

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'lldmacros' => true    Enable low-level discovery macros usage in trigger expression.
	 *
	 * @var array
	 */
	protected $options = [
		'lldmacros' => true
	];

	/**
	 * User macro parser.
	 *
	 * @var CUserMacroParser
	 */
	private $user_macro_parser;

	/**
	 * LLD macro parser.
	 *
	 * @var CLLDMacroParser
	 */
	private $lld_macro_parser;

	/**
	 * LLD macro function parser.
	 *
	 * @var CLLDMacroFunctionParser
	 */
	private $lld_macro_function_parser;

	/**
	 * Parsed data.
	 *
	 * @var CPeriodParserResult
	 */
	public $result;

	/**
	 * @param array $options
	 * @param bool  $options['lldmacros']
	 */
	public function __construct(array $options) {
		$this->options = $options + $this->options;

		$this->user_macro_parser = new CUserMacroParser();
		if ($this->options['lldmacros']) {
			$this->lld_macro_parser = new CLLDMacroParser();
			$this->lld_macro_function_parser = new CLLDMacroFunctionParser();
		}
	}

	/**
	 * Parse period.
	 *
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0): int {
		$start_pos = $pos;
		$break_chars = [',', ')'];
		$parts = [
			0 => ''
		];
		$contains_macros = [
			0 => ''
		];
		$num = 0;

		while (isset($source[$pos])) {
			if (in_array($source[$pos], $break_chars)) {
				break;
			}
			elseif ($source[$pos] === ':') {
				$parts[++$num] = '';
				$contains_macros[$num] = '';
				$pos++;
			}
			elseif ($this->user_macro_parser->parse($source, $pos) !== CParser::PARSE_FAIL) {
				$pos += $this->user_macro_parser->length;
				$parts[$num] .= $this->user_macro_parser->match;
				$contains_macros[$num] = $this->user_macro_parser->match;
			}
			elseif ($this->options['lldmacros']
					&& $this->lld_macro_function_parser->parse($source, $pos) != CParser::PARSE_FAIL) {
				$pos += $this->lld_macro_function_parser->length;
				$parts[$num] .= $this->lld_macro_function_parser->match;
				$contains_macros[$num] = $this->lld_macro_function_parser->match;
			}
			elseif ($this->options['lldmacros']
					&& $this->lld_macro_parser->parse($source, $pos) !== CParser::PARSE_FAIL) {
				$pos += $this->lld_macro_parser->length;
				$parts[$num] .= $this->lld_macro_parser->match;
				$contains_macros[$num] = $this->lld_macro_parser->match;
			}
			else {
				$parts[$num] .= $source[$pos];
				$pos++;
			}
		}

		// Valid period consists of 1 or 2 non-empty parts.
		if (count($parts) > 2 || $parts[0] === '' || (array_key_exists(1, $parts) && $parts[1] === '')) {
			return CParser::PARSE_FAIL;
		}

		// First part can contain only raw value or single macro but not mixed raw value and macro.
		if ($contains_macros[0] && $contains_macros[0] !== $parts[0]) {
			return CParser::PARSE_FAIL;
		}

		// Second part can contain macro only at the end. E.g., now-{$TWO_WEEKS}
		if (array_key_exists(1, $contains_macros) && $contains_macros[1] !== ''
				&& substr($parts[1], strlen($contains_macros[1]) * -1) !== $contains_macros[1]) {
			return CParser::PARSE_FAIL;
		}

		// Check format. Otherwise, almost anything can be period.
		$is_valid_num = (substr($parts[0], 0, 1) === '#' && ctype_digit(substr($parts[0], 1)));
		$is_valid_sec = preg_match('/^'.ZBX_PREG_INT.'(?<suffix>['.ZBX_TIME_SUFFIXES_WITH_YEAR.'])$/', $parts[0]);
		if (!$is_valid_num && !$is_valid_sec && !$contains_macros[0]) {
			return CParser::PARSE_FAIL;
		}

		$this->result = new CPeriodParserResult();
		$this->length = $pos - $start_pos;
		$this->result->match = substr($source, $start_pos, $this->length);
		$this->result->sec_num = $parts[0];
		$this->result->time_shift = (array_key_exists(1, $parts) && $parts[1] !== '') ? $parts[1] : null;
		$this->result->sec_num_contains_macros = ($contains_macros[0] !== '');
		$this->result->time_shift_contains_macros = array_key_exists(1, $contains_macros)
			? (bool) ($contains_macros[1] !== '')
			: false;
		$this->result->length = $this->length;
		$this->result->pos = $start_pos;

		return isset($source[$pos]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}
}
