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
 * A parser for DNS address.
 */
class CDnsParser extends CParser {

	/**
	 * @var array
	 */
	private $macro_parsers = [];

	/**
	 * Supported options:
	 *   'usermacros' => true  Enabled support of user macros;
	 *   'lldmacros' => true   Enabled support of LLD macros;
	 *   'macros' => true      Enabled support of all macros;
	 *   'macros' => []        Allows array with list of macros. Empty array or false means no macros supported.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false,
		'macros' => []
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		if ($this->options['usermacros']) {
			array_push($this->macro_parsers, new CUserMacroParser, new CUserMacroFunctionParser);
		}
		if ($this->options['lldmacros']) {
			array_push($this->macro_parsers, new CLLDMacroParser, new CLLDMacroFunctionParser);
		}
		if ($this->options['macros']) {
			array_push($this->macro_parsers,
				new CMacroParser(['macros' => $this->options['macros']]),
				new CMacroFunctionParser(['macros' => $this->options['macros']])
			);
		}
	}

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		while (isset($source[$p])) {
			if (preg_match('/^[a-z0-9][a-z0-9_-]*(\.[a-z0-9_-]+)*\.?/i', substr($source, $p), $matches)) {
				$p += strlen($matches[0]);
			}
			else {
				foreach ($this->macro_parsers as $macro_parser) {
					if ($macro_parser->parse($source, $p) != self::PARSE_FAIL) {
						$p += $macro_parser->getLength();
						continue 2;
					}
				}

				break;
			}
		}

		$length = $p - $pos;

		if ($length == 0 || $length > 255) {
			return self::PARSE_FAIL;
		}

		$this->length = $length;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}
}
