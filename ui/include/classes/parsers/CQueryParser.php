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
	 * Parser for item additional filter
	 *
	 * @var CFilterAttributeParser
	 */
	private $item_filter_parser;

	/**
	 * Parsed data.
	 *
	 * @var CQueryParserResult
	 */
	public $result;

	/**
	 * @param array $options
	 */
	public function __construct() {
		$this->item_key_parser = new CItemKey();
		$this->host_name_parser = new CHostNameParser();
		$this->item_filter_parser = new CFilterAttributeParser();
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
		$this->result = new CQueryParserResult();
		$start_pos = $pos;

		if (!isset($source[$pos]) || $source[$pos] !== '/') {
			return CParser::PARSE_FAIL;
		}
		$pos++;

		if ($this->host_name_parser->parse($source, $pos) == self::PARSE_FAIL) {
			return CParser::PARSE_FAIL;
		}
		$pos += $this->host_name_parser->getLength();

		if (!isset($source[$pos]) || $source[$pos] !== '/') {
			return CParser::PARSE_FAIL;
		}
		$pos++;

		if ($this->item_key_parser->parse($source, $pos) == self::PARSE_FAIL) {
			return CParser::PARSE_FAIL;
		}
		$pos += $this->item_key_parser->getLength();

		if ($this->item_filter_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->item_filter_parser->getLength();
		}

		$this->length = $pos - $start_pos;
		$this->result->match = substr($source, $start_pos, $this->length);
		$this->result->host = $this->host_name_parser->getMatch();
		$this->result->item = $this->item_key_parser->getMatch();
		$this->result->item_filter = $this->item_filter_parser->getMatch();
		$this->result->length = $this->length;
		$this->result->pos = $start_pos;

		return isset($source[$pos]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Returns host.
	 *
	 * @return string
	 */
	public function getHost(): string {
		return $this->host;
	}

	/**
	 * Returns item.
	 *
	 * @return string
	 */
	public function getItem(): string {
		return $this->item;
	}
}
