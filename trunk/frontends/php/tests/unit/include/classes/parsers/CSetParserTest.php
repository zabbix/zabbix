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


class CSetParserTest extends CParserTest {

	protected function getParser() {
		return new CSetParser(['<', '>', '<>', 'and', 'or']);
	}

	public function validProvider() {
		return [
			['<', 0, '<', 1],
			['<=', 0, '<', 1],
			['>', 0, '>', 1],
			['>=', 0, '>', 1],
			['<>', 0, '<>', 2],
			['<>=', 0, '<>', 2],
			['and', 0, 'and', 3],
			['and this', 0, 'and', 3],
			['or', 0, 'or', 2],
			['or this', 0, 'or', 2],

			['prefix<', 6, '<', 1],
			['prefix<=', 6, '<', 1],
			['prefix>', 6, '>', 1],
			['prefix>=', 6, '>', 1],
			['prefix<>', 6, '<>', 2],
			['prefix<>=', 6, '<>', 2],
			['prefixand', 6, 'and', 3],
			['prefixand this', 6, 'and', 3],
			['prefixor', 6, 'or', 2],
			['prefixor this', 6, 'or', 2],

			['><', 0, '>', 1],
		];
	}

	public function invalidProvider() {
		return [
			['', 0, 0],
			['an', 0, 2],
			['anor', 0, 4],
			['+<', 0, 0],

			['prefixand', 5, 5],
			['prefixand', 7, 9],
		];
	}
}
