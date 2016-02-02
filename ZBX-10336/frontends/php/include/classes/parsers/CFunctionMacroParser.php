<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 *  A parser for function macros like "{host.item.func()}".
 */
class CFunctionMacroParser extends CParser {

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   '18_simple_checks' => true		with support for old-style simple checks like "ftp,{$PORT}"
	 *
	 * @var array
	 */
	private $options = ['18_simple_checks' => false];

	/**
	 * Parser for item keys.
	 *
	 * @var CItemkey
	 */
	private $item_key_parser;

	/**
	 * Parser for trigger functions.
	 *
	 * @var CFunctionParser
	 */
	private $function_parser;

	private $host = '';
	private $item = '';
	private $function = '';

	/**
	 * @param array $options
	 */
	public function __construct($options = []) {
		if (array_key_exists('18_simple_checks', $options)) {
			$this->options['18_simple_checks'] = $options['18_simple_checks'];
		}

		$this->item_key_parser = new CItemKey(['18_simple_checks' => $this->options['18_simple_checks']]);
		$this->function_parser = new CFunctionParser();
	}

	/**
	 * @param string    $source
	 * @param int       $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->host = '';
		$this->item = '';
		$this->function = '';

		$p = $pos;

		if (!isset($source[$p]) || $source[$p] !== '{') {
			return self::PARSE_FAIL;
		}
		$p++;

		if (!$this->parseHost($source, $p)) {
			return self::PARSE_FAIL;
		}

		if (!isset($source[$p]) || $source[$p] !== ':') {
			return self::PARSE_FAIL;
		}
		$p++;

		$p2 = $p;

		if ($this->item_key_parser->parse($source, $p) == CParser::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$p += $this->item_key_parser->getLength();

		// for instance, agent.ping.last(0)
		if ($this->item_key_parser->getParamsNum() == 0 && isset($source[$p]) && $source[$p] == '(') {
			for (; $p > $p2 && $source[$p] != '.'; $p--) {
				// Code is not missing here.
			}

			if ($p == $p2) {
				return self::PARSE_FAIL;
			}
		}
		$p3 = $p;

		if (!isset($source[$p]) || $source[$p] !== '.') {
			return self::PARSE_FAIL;
		}
		$p++;

		if ($this->function_parser->parse($source, $p) == CParser::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$p += $this->function_parser->getLength();

		if (!isset($source[$p]) || $source[$p] !== '}') {
			return self::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);
		$this->host = substr($source, $pos + 1, $p2 - $pos - 2);
		$this->item = substr($source, $p2, $p3 - $p2);
		$this->function = $this->function_parser->getMatch();

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}

	/**
	 * Parses a host in a trigger function macro constant and moves a position ($pos) on a next symbol after the host.
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	protected function parseHost($source, &$pos) {
		$p = $pos;

		for (; isset($source[$p]) && $this->isHostChar($source[$p]); $p++) {
			// Code is not missing here.
		}

		// is host empty?
		if ($p == $pos) {
			return false;
		}

		$pos = $p;

		return true;
	}

	/**
	 * Returns true if the char is allowed in the host name, false otherwise.
	 *
	 * @param string $c
	 *
	 * @return bool
	 */
	protected function isHostChar($c) {
		return (($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9')
			|| $c == '.' || $c == ' ' || $c == '_' || $c == '-');
	}

	/**
	 * Returns parsed host.
	 *
	 * @return string
	 */
	public function getHost() {
		return $this->host;
	}

	/**
	 * Returns parsed item.
	 *
	 * @return string
	 */
	public function getItem() {
		return $this->item;
	}

	/**
	 * Returns parsed function.
	 *
	 * @return string
	 */
	public function getFunction() {
		return $this->function;
	}
}
