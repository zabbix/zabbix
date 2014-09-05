<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CSetParserTest extends CParserTest {

	protected function getParser() {
		return new CSetParser(array('<', '>', '<>', 'and', 'or'));
	}

	public function validProvider() {
		return array(
			array('<', 0, '<', 1),
			array('<=', 0, '<', 1),
			array('>', 0, '>', 1),
			array('>=', 0, '>', 1),
			array('<>', 0, '<>', 2),
			array('<>=', 0, '<>', 2),
			array('and', 0, 'and', 3),
			array('and this', 0, 'and', 3),
			array('or', 0, 'or', 2),
			array('or this', 0, 'or', 2),

			array('prefix<', 6, '<', 1),
			array('prefix<=', 6, '<', 1),
			array('prefix>', 6, '>', 1),
			array('prefix>=', 6, '>', 1),
			array('prefix<>', 6, '<>', 2),
			array('prefix<>=', 6, '<>', 2),
			array('prefixand', 6, 'and', 3),
			array('prefixand this', 6, 'and', 3),
			array('prefixor', 6, 'or', 2),
			array('prefixor this', 6, 'or', 2),

			array('><', 0, '>', 1),
		);
	}

	public function invalidProvider() {
		return array(
			array('', 0, 0),
			array('an', 0, 2),
			array('anor', 0, 4),
			array('+<', 0, 0),

			array('prefixand', 5, 5),
			array('prefixand', 7, 9),
		);
	}
}
