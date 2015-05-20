<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

	/**
	 * Current cursor position.
	 *
	 * @var int
	 */
	protected $pos;

	/**
	 * Returns the current cursor position.
	 *
	 * @return int
	 */
	public function getPos() {
		return $this->pos;
	}

	/**
	 * Try to parse the string starting from the given position.
	 *
	 * @param string	$source		string to parse
	 * @param int 		$startPos	position to start from
	 *
	 * @return CParserResult|bool   returns a CParserResult object if a match has been found or false otherwise
	 */
	abstract public function parse($source, $startPos = 0);
}
