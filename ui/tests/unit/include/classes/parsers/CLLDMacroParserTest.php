<?php declare(strict_types = 0);
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


class CLLDMacroParserTest extends CParserTest {

	protected function getParser() {
		return new CLLDMacroParser();
	}

	public function dataProvider() {
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
