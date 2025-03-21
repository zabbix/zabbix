<?php declare(strict_types=0);
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
 * Class for validating port number or port number range.
 */
class CPortParser extends CParser {

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
		'lldmacros' => false
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
	}

	/**
	 * Parse the given port number.
	 *
	 * @param string $source Source string that needs to be parsed.
	 * @param int $pos Position offset.
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0): int {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		if ($this->checkPortNumber($source)) {
			$p += strlen($source);
		}
		else {
			foreach ($this->macro_parsers as $macro_parser) {
				if ($macro_parser->parse($source, $p) != self::PARSE_FAIL) {
					$p += $macro_parser->getLength();
					break;
				}
			}
		}

		$length = $p - $pos;

		if ($length == 0) {
			$this->errorPos($source, 0);

			return self::PARSE_FAIL;
		}

		$this->length = $length;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	private function checkPortNumber($port_number): bool {
		$min_port = ZBX_MIN_PORT_NUMBER;
		$max_port = ZBX_MAX_PORT_NUMBER;

		if (!zbx_is_int($port_number)) {
			return false;
		}

		if ($port_number < $min_port) {
			return false;
		}

		if ($port_number > $max_port) {
			return false;
		}

		return true;
	}
}
