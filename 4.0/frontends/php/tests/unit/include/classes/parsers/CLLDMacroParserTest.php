<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CLLDMacroParserTest extends CParserTest {

	protected function getParser() {
		return new CLLDMacroParser();
	}

	public function testProvider() {
		return [
			['{#M}', 0, CParser::PARSE_SUCCESS, '{#M}'],
			['{#MACRO12.A_Z}', 0, CParser::PARSE_SUCCESS, '{#MACRO12.A_Z}'],
			['{#MACRO} = 0', 0, CParser::PARSE_SUCCESS_CONT, '{#MACRO}'],
			['not {#MACRO} = 0', 4, CParser::PARSE_SUCCESS_CONT, '{#MACRO}'],

			['', 0, CParser::PARSE_FAIL, ''],
			['A', 0, CParser::PARSE_FAIL, ''],
			['{A', 0, CParser::PARSE_FAIL, ''],
			['{#', 0, CParser::PARSE_FAIL, ''],
			['{#}', 0, CParser::PARSE_FAIL, ''],
			['{#A', 0, CParser::PARSE_FAIL, ''],
			['{#a}', 0, CParser::PARSE_FAIL, ''],
			['{#+}', 0, CParser::PARSE_FAIL, '']
		];
	}
}
