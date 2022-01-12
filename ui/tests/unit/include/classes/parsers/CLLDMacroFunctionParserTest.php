<?php declare(strict_types=1);
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


class CLLDMacroFunctionParserTest extends CParserTest {

	protected function getParser() {
		return new CLLDMacroFunctionParser();
	}

	public function dataProvider() {
		return [
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
				0,
				CParser::PARSE_SUCCESS,
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}'
			],
			[
				'{{#MACRO12.A_Z}.last()}',
				0,
				CParser::PARSE_SUCCESS,
				'{{#MACRO12.A_Z}.last()}'
			],
			[
				'{{#M}.somefunc()}',
				0,
				CParser::PARSE_SUCCESS,
				'{{#M}.somefunc()}'
			],
			[
				'not {{#M}.iregsub("^([0-9]+)", "{#M}: \1")} = ',
				4,
				CParser::PARSE_SUCCESS_CONT,
				'{{#M}.iregsub("^([0-9]+)", "{#M}: \1")}'
			],
			[
				'',
				0,
				CParser::PARSE_FAIL,
				''
			],
			[
				'{',
				0,
				CParser::PARSE_FAIL,
				''
			],
			[
				'{{#M}',
				0,
				CParser::PARSE_FAIL,
				''
			],
			[
				'{{#M}.f()',
				0,
				CParser::PARSE_FAIL,
				''
			],
			[
				'{#M}',
				0,
				CParser::PARSE_FAIL,
				''
			],
			[
				'{#M}.regsub("^([0-9]+)", "{#M}: \1")',
				0,
				CParser::PARSE_FAIL,
				''
			],
			[
				'{{#M}.somefunc(/host/key["a", "b"])}',
				0,
				CParser::PARSE_FAIL,
				''
			]
		];
	}
}
