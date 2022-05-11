<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	 * @var CUserMacroParser
	 */
	private $user_macro_parser;

	/**
	 * @var CLLDMacroParser
	 */
	private $lld_macro_parser;

	/**
	 * @var CLLDMacroFunctionParser
	 */
	private $lld_macro_function_parser;

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
		if (array_key_exists('v6', $options)) {
			$this->options['v6'] = $options['v6'];
		}
		if (array_key_exists('usermacros', $options)) {
			$this->options['usermacros'] = $options['usermacros'];
		}
		if (array_key_exists('lldmacros', $options)) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}
		if (array_key_exists('macros', $options)) {
			$this->options['macros'] = $options['macros'];
		}

		if ($this->options['v6']) {
			$this->ipv6_parser = new CIPv6Parser();
		}
		if ($this->options['usermacros']) {
			$this->user_macro_parser = new CUserMacroParser();
		}
		if ($this->options['lldmacros']) {
			$this->lld_macro_parser = new CLLDMacroParser();
			$this->lld_macro_function_parser = new CLLDMacroFunctionParser();
		}
		if ($this->options['macros']) {
			$this->macro_parser = new CMacroParser(['macros' => $this->options['macros']]);
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
		elseif ($this->options['usermacros'] && $this->user_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$this->length = $this->user_macro_parser->getLength();
			$this->match = $this->user_macro_parser->getMatch();
		}
		elseif ($this->options['lldmacros'] && $this->lld_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$this->length = $this->lld_macro_parser->getLength();
			$this->match = $this->lld_macro_parser->getMatch();
		}
		elseif ($this->options['lldmacros']
				&& $this->lld_macro_function_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$this->length = $this->lld_macro_function_parser->getLength();
			$this->match = $this->lld_macro_function_parser->getMatch();
		}
		elseif ($this->options['macros'] && $this->macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$this->length = $this->macro_parser->getLength();
			$this->match = $this->macro_parser->getMatch();
		}
		else {
			return self::PARSE_FAIL;
		}

		return isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}
}
