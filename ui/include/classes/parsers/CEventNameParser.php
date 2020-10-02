<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * A parser for event names for triggers and trigger prototypes.
 */
class CEventNameParser extends CParser {

	/**
	 * Array of parser instances.
	 *
	 * @var array
	 */
	protected $parsers = [];

	public function __construct() {
		$this->parsers = [
			new CExpressionMacroFunctionParser(),
			new CExpressionMacroParser(),
			new CUserMacroParser(),
			new CMacroFunctionParser(
				['{ITEM.LASTVALUE}', '{ITEM.VALUE}'],
				['ref_type' => CMacroParser::REFERENCE_NUMERIC]
			),
			new CMacroParser([
				'{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}',
				'{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}',
				'{ITEM.LASTVALUE}', '{ITEM.VALUE}',
				'{ITEM.LOG.DATE}', '{ITEM.LOG.TIME}', '{ITEM.LOG.AGE}', '{ITEM.LOG.SOURCE}', '{ITEM.LOG.SEVERITY}',
				'{ITEM.LOG.NSEVERITY}', '{ITEM.LOG.EVENTID}'
			], ['ref_type' => CMacroParser::REFERENCE_NUMERIC]),
			new CMacroFunctionParser(['{TIME}']),
			new CMacroParser(['{TIME}'])
		];
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
			if ($source[$p] !== '{') {
				$p++;

				continue;
			}

			$macro_parser = null;

			foreach ($this->parsers as $parser) {
				if ($parser->parse($source, $p) !== CParser::PARSE_FAIL) {
					$macro_parser = $parser;

					break;
				}
			}

			if ($macro_parser === null) {
				return CParser::PARSE_FAIL;
			}

			$p += $macro_parser->getLength();
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return CParser::PARSE_SUCCESS;
	}
}
