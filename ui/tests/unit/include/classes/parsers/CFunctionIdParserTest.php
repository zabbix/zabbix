<?php declare(strict_types = 0);
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


class CFunctionIdParserTest extends CParserTest {

	protected function getParser() {
		return new CFunctionIdParser();
	}

	public function dataProvider() {
		return [
			['{1}', 0, CParser::PARSE_SUCCESS, '{1}'],
			['{12345}', 0, CParser::PARSE_SUCCESS, '{12345}'],
			['{9223372036854775807} = 0', 0, CParser::PARSE_SUCCESS_CONT, '{9223372036854775807}'],
			['not {34356} = 0', 4, CParser::PARSE_SUCCESS_CONT, '{34356}'],

			['', 0, CParser::PARSE_FAIL, ''],
			['1', 0, CParser::PARSE_FAIL, ''],
			['{2Q', 0, CParser::PARSE_FAIL, ''],
			['{0}', 0, CParser::PARSE_FAIL, ''],
			['{9223372036854775808}', 0, CParser::PARSE_FAIL, '']
		];
	}
}
