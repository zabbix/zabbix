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
class CAddressParser extends CParser {

	/**
	 * @var CIPParser
	 */
	private $ip_parser;

	/**
	 * @var CDnsParser
	 */
	private $dns_parser;

	/**
	 * @var string
	 */
	private $type = null;

	/**
	 * Supported options:
	 *   'usermacros' => true  Enabled support of user macros;
	 *   'lldmacros' => true   Enabled support of LLD macros;
	 *   'macros' => true      Enabled support of all macros;
	 *   'macros' => []        Allows array with list of macros. Empty array or false means no macros supported.
	 *
	 * @param array $options
	 */
	public function __construct(array $options) {
		$this->ip_parser = new CIPParser($options);
		$this->dns_parser = new CDnsParser($options);
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

		if ($this->ip_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$this->length = $this->ip_parser->getLength();
			$this->match = $this->ip_parser->getMatch();
			$this->type = INTERFACE_USE_IP;
		}
		elseif ($this->dns_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$this->length = $this->dns_parser->getLength();
			$this->match = $this->dns_parser->getMatch();
			$this->type = INTERFACE_USE_DNS;
		}

		if ($this->length == 0) {
			return self::PARSE_FAIL;
		}

		return isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	public function getAddressType() {
		return $this->type;
	}
}
