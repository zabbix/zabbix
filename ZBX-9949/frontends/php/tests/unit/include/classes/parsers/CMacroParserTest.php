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


class CMacroParserTest extends CParserTest {

	protected function getParser() {
		return new CMacroParser('#');
	}

	public function validProvider() {
		return [
			['{#M}', 0, '{#M}', 4],
			['{#MACRO12.A_Z}', 0, '{#MACRO12.A_Z}', 14],
			['{#MACRO} = 0', 0, '{#MACRO}', 8],
			['not {#MACRO} = 0', 4, '{#MACRO}', 8],
		];
	}

	public function invalidProvider() {
		return [
			['', 0, 0],
			['A', 0, 0],
			['{A', 0, 1],
			['{#', 0, 2],
			['{#}', 0, 2],
			['{#A', 0, 3],
			['{#a}', 0, 2],
			['{#+}', 0, 2],
		];
	}
}
