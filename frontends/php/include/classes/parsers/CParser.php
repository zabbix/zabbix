<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

abstract class CParser {

	const PARSE_FAIL = -1;
	const PARSE_SUCCESS = 0;
	const PARSE_SUCCESS_CONT = 1;

	protected $length = 0;
	protected $match = '';

	/**
	 * Try to parse the string starting from the given position.
	 *
	 * @param string	$source		string to parse
	 * @param int 		$pos		position to start from
	 *
	 * @return int
	 */
	abstract public function parse($source, $pos = 0);

	/**
	 * Returns length of the parsed element.
	 *
	 * @return int
	 */
	public function getLength() {
		return $this->length;
	}

	/**
	 * Returns parsed element.
	 *
	 * @return string
	 */
	public function getMatch() {
		return $this->match;
	}
}
