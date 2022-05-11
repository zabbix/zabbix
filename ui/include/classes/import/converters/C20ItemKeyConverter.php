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
 * Convert net.tcp.service to net.udp.service.
 */
class C20ItemKeyConverter extends CConverter {

	/**
	 * Item key parser.
	 *
	 * @var CItemKey
	 */
	protected $item_key_parser;

	public function __construct() {
		$this->item_key_parser = new CItemKey();
	}

	/**
	 * Convert item key.
	 *
	 * @param string $value  Item key.
	 *
	 * @return string  Converted item key.
	 */
	public function convert($value) {
		if ($this->item_key_parser->parse($value) != CParser::PARSE_SUCCESS) {
			return $value;
		}

		$item_key = $this->item_key_parser->getKey();
		if ($item_key !== 'net.tcp.service' && $item_key !== 'net.tcp.service.perf') {
			return $value;
		}

		if ($this->item_key_parser->getParamsNum() == 0 || $this->item_key_parser->getParam(0) !== 'ntp') {
			return $value;
		}

		return substr_replace($value, 'udp', 4, 3);
	}
}
