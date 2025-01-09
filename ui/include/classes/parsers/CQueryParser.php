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
	 *   'usermacros' => false    Enable user macros usage in filter expression.
	 *   'lldmacros' => false     Enable low-level discovery macros usage in filter expression.
	 *   'calculated' => false    Allow wildcards to be used in place of hostname and item key. Allow filter expression
	 *                            for item.
	 *   'host_macro' => false    Allow {HOST.HOST} macro as host name part in the query.
	 *   'host_macro_n' => false  Allow {HOST.HOST} and {HOST.HOST<1-9>} macros as host name part in the query.
	 *   'empty_host' => false    Allow empty hostname.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false,
		'calculated' => false,
		'host_macro' => false,
		'host_macro_n' => false,
		'empty_host' => false
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
	 * @var array
	 */
	private $filter = [
		'match' => '',
		'tokens' => []
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->host_name_parser = new CHostNameParser();
		if ($this->options['host_macro'] || $this->options['host_macro_n']) {
			$this->host_macro_parser = new CMacroParser([
				'macros' => ['{HOST.HOST}'],
				'ref_type' => $this->options['host_macro_n']
					? CMacroParser::REFERENCE_NUMERIC
					: CMacroParser::REFERENCE_NONE
			]);
		}
		$this->item_key_parser = new CItemKey();
		if ($this->options['calculated']) {
			$this->filter_parser = new CFilterParser([
				'usermacros' => $this->options['usermacros'],
				'lldmacros' => $this->options['lldmacros']
			]);
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
		$this->filter = [
			'match' => '',
			'tokens' => []
		];

		$p = $pos;

		if (!isset($source[$p]) || $source[$p] !== '/') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		if (($this->options['host_macro'] || $this->options['host_macro_n'])
				&& $this->host_macro_parser->parse($source, $p) != self::PARSE_FAIL) {
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
		elseif ($this->options['empty_host']) {
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
			$this->filter = [
				'match' => $this->filter_parser->getMatch(),
				'tokens' => $this->filter_parser->getTokens()
			];
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

	/**
	 * Returns the filter.
	 *
	 * @return array
	 */
	public function getFilter(): array {
		return $this->filter;
	}
}
