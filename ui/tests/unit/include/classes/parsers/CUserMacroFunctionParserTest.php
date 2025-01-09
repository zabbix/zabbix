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


class CUserMacroFunctionParserTest extends CParserTest {

	protected function getParser() {
		return new CUserMacroFunctionParser();
	}

	public function dataProvider() {
		return [
			[
				'{{$M}.regsub("^([0-9]+)", "{$M}: \1")}',
				0,
				CParser::PARSE_SUCCESS,
				'{{$M}.regsub("^([0-9]+)", "{$M}: \1")}'
			],
			[
				'{{$MACRO12.A_Z}.last()}',
				0,
				CParser::PARSE_SUCCESS,
				'{{$MACRO12.A_Z}.last()}'
			],
			[
				'{{$M}.somefunc()}',
				0,
				CParser::PARSE_SUCCESS,
				'{{$M}.somefunc()}'
			],
			[
				'not {{$M}.iregsub("^([0-9]+)", "{$M}: \1")} = ',
				4,
				CParser::PARSE_SUCCESS_CONT,
				'{{$M}.iregsub("^([0-9]+)", "{$M}: \1")}'
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
				'{{$M}',
				0,
				CParser::PARSE_FAIL,
				''
			],
			[
				'{{$M}.f()',
				0,
				CParser::PARSE_FAIL,
				''
			],
			[
				'{$M}',
				0,
				CParser::PARSE_FAIL,
				''
			],
			[
				'{$M}.regsub("^([0-9]+)", "{$M}: \1")',
				0,
				CParser::PARSE_FAIL,
				''
			],
			[
				'{{$M}.somefunc(/host/key["a", "b"])}',
				0,
				CParser::PARSE_FAIL,
				''
			]
		];
	}
}
