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
 * A parser for IPv4 and IPv6 addresses
 */
class CIPParser extends CParser {

	/**
	 * @var CIPv4Parser
	 */
	private $ipv4_parser;

	/**
	 * @var CIPv6Parser
	 */
	private $ipv6_parser;

	/**
	 * @var array
	 */
	private $macro_parsers = [];

	/**
	 * Supported options:
	 *   'v6' => true          Enabled support of IPv6 addresses;
	 *   'usermacros' => true  Enabled support of user macros;
	 *   'lldmacros' => true   Enabled support of LLD macros;
	 *   'macros' => true      Enabled support of all macros. Allows array with list of macros.
	 *
	 * @var array
	 */
	private $options = [
		'v6' => true,
		'usermacros' => false,
		'lldmacros' => false,
		'macros' => []
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options) {
		$this->options = $options + $this->options;

		if ($this->options['v6']) {
			$this->ipv6_parser = new CIPv6Parser();
		}
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

		$this->ipv4_parser = new CIPv4Parser();
	}

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return bool
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		if ($this->ipv4_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$this->length = $this->ipv4_parser->getLength();
			$this->match = $this->ipv4_parser->getMatch();
		}
		elseif ($this->options['v6'] && $this->ipv6_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$this->length = $this->ipv6_parser->getLength();
			$this->match = $this->ipv6_parser->getMatch();
		}
		else {
			foreach ($this->macro_parsers as $macro_parser) {
				if ($macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
					$this->length = $macro_parser->getLength();
					$this->match = $macro_parser->getMatch();
					break;
				}
			}
		}

		if ($this->length == 0) {
			return self::PARSE_FAIL;
		}

		return isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}
}
