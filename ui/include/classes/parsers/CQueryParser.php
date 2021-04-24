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

	/**
	 * Parser for item keys.
	 *
	 * @var CItemKey
	 */
	private $item_key_parser;

	/**
	 * Parser for host names.
	 *
	 * @var CHostNameParser
	 */
	private $host_name_parser;

	/**
	 * Parser for the {HOST.HOST} macro.
	 *
	 * @var CSetParser
	 */
	private $host_macro_parser;

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'calculated' => false  Parse calculated item formula instead of trigger expression.
	 *   'host_macro'           Array of macros supported as host name part in the query.
	 *
	 * @var array
	 */
	private $options = [
		'calculated' => false,
		'host_macro' => []
	];

	/**
	 * Parsed data.
	 *
	 * @var CQueryParserResult
	 */
	public $result;

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		if ($this->options['calculated']) {
			$this->item_key_parser = new CItemKey(['with_filter' => true, 'allow_wildcard' => true]);
			$this->host_name_parser = new CHostNameParser([
				'allow_host_all' => true,
				'allow_host_current' => true
			]);
		}
		else {
			$this->item_key_parser = new CItemKey();
			$this->host_name_parser = new CHostNameParser();
		}

		if ($this->options['host_macro']) {
			$this->host_macro_parser = new CMacroParser(['macros' => $this->options['host_macro']]);
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
		$this->errorClear();
		$this->result = new CQueryParserResult();
		$start_pos = $pos;

		if (!isset($source[$pos]) || $source[$pos] !== '/') {
			$this->errorPos($source, $pos);

			return CParser::PARSE_FAIL;
		}
		$pos++;

		if ($this->options['host_macro'] && $this->host_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->host_macro_parser->getLength();
			$host = $this->host_macro_parser->getMatch();
		}
		elseif ($this->host_name_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->host_name_parser->getLength();
			$host = $this->host_name_parser->getMatch();
		}
		else {
			$error = $this->host_name_parser->getErrorDetails();

			if ($error) {
				$this->errorPos($error[0], $error[1]);
			}

			return CParser::PARSE_FAIL;
		}

		if (!isset($source[$pos]) || $source[$pos] !== '/') {
			$this->errorPos($source, $pos);

			return CParser::PARSE_FAIL;
		}
		$pos++;

		if ($this->item_key_parser->parse($source, $pos) == self::PARSE_FAIL) {
			[$source, $pos] = $this->item_key_parser->getErrorDetails();
			$this->errorPos($source, $pos);

			return CParser::PARSE_FAIL;
		}
		$pos += $this->item_key_parser->getLength();

		$this->length = $pos - $start_pos;
		$this->result->match = substr($source, $start_pos, $this->length);
		$this->result->host = $host;
		$this->result->item = $this->item_key_parser->getMatch();
		$this->result->length = $this->length;
		$this->result->pos = $start_pos;

		return isset($source[$pos]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}
}
