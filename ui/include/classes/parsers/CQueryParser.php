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
 * Class is used to parse a query in trigger function in form of "/host/key".
 */
class CQueryParser extends CParser {

	// Wildcard character to define host or item key.
	const HOST_ITEMKEY_WILDCARD = '*';

	private $host_name_parser;
	private $host_macro_parser;
	private $item_key_parser;
	private $filter_parser;

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'calculated' => false  Parse calculated item formula instead of trigger expression.
	 *   'host_macro' => false  Allow {HOST.HOST} macro as host name part in the query.
	 *
	 * @var array
	 */
	private $options = [
		'calculated' => false,
		'host_macro' => false
	];

	/**
	 * @var string
	 */
	private $host = '';

	/**
	 * @var string
	 */
	private $item = '';

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->host_name_parser = new CHostNameParser();
		if ($this->options['host_macro']) {
			$this->host_macro_parser = new CMacroParser(['macros' => ['{HOST.HOST}']]);
		}
		$this->item_key_parser = new CItemKey();
		if ($this->options['calculated']) {
			$this->filter_parser = new CFilterParser();
		}
	}

	/**
	 * Parse a trigger query.
	 *
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0): int {
		$this->match = '';
		$this->length = 0;
		$this->host = '';
		$this->item = '';

		$p = $pos;

		if (!isset($source[$p]) || $source[$p] !== '/') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		if ($this->options['host_macro'] && $this->host_macro_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->host_macro_parser->getLength();
			$host = $this->host_macro_parser->getMatch();
		}
		// Allow wildcard for calculated item formula.
		elseif ($this->options['calculated'] && isset($source[$p])
				&& $source[$p] === self::HOST_ITEMKEY_WILDCARD) {
			$p++;
			$host = self::HOST_ITEMKEY_WILDCARD;
		}
		elseif ($this->host_name_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->host_name_parser->getLength();
			$host = $this->host_name_parser->getMatch();
		}
		// Allow an empty hostname for calculated item formula.
		elseif ($this->options['calculated']) {
			$host = '';
		}
		else {
			return CParser::PARSE_FAIL;
		}

		if (!isset($source[$p]) || $source[$p] !== '/') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		// Allow wildcard for calculated item formula.
		if ($this->options['calculated'] && isset($source[$p])
				&& $source[$p] === self::HOST_ITEMKEY_WILDCARD) {
			$p++;
			$item = self::HOST_ITEMKEY_WILDCARD;
		}
		elseif ($this->item_key_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->item_key_parser->getLength();
			$item = $this->item_key_parser->getMatch();
		}
		else {
			return CParser::PARSE_FAIL;
		}

		if ($this->options['calculated'] && $this->filter_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->filter_parser->getLength();
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);
		$this->host = $host;
		$this->item = $item;

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Returns the hostname.
	 *
	 * @return string
	 */
	public function getHost(): string {
		return $this->host;
	}

	/**
	 * Returns the item key.
	 *
	 * @return string
	 */
	public function getItem(): string {
		return $this->item;
	}
}


