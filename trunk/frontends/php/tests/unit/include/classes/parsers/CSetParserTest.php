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


class CSetParserTest extends CParserTest {

	protected function getParser() {
		return new CSetParser(['<', '>', '<>', 'and', 'or']);
	}

	public function testProvider() {
		return [
			['<', 0, CParser::PARSE_SUCCESS, '<'],
			['<=', 0, CParser::PARSE_SUCCESS_CONT, '<'],
			['>', 0, CParser::PARSE_SUCCESS, '>'],
			['>=', 0, CParser::PARSE_SUCCESS_CONT, '>'],
			['<>', 0, CParser::PARSE_SUCCESS, '<>'],
			['<>=', 0, CParser::PARSE_SUCCESS_CONT, '<>'],
			['and', 0, CParser::PARSE_SUCCESS, 'and'],
			['and this', 0, CParser::PARSE_SUCCESS_CONT, 'and'],
			['or', 0, CParser::PARSE_SUCCESS, 'or'],
			['or this', 0, CParser::PARSE_SUCCESS_CONT, 'or'],

			['prefix<', 6, CParser::PARSE_SUCCESS, '<'],
			['prefix<=', 6, CParser::PARSE_SUCCESS_CONT, '<'],
			['prefix>', 6, CParser::PARSE_SUCCESS, '>'],
			['prefix>=', 6, CParser::PARSE_SUCCESS_CONT, '>'],
			['prefix<>', 6, CParser::PARSE_SUCCESS, '<>'],
			['prefix<>=', 6, CParser::PARSE_SUCCESS_CONT, '<>'],
			['prefixand', 6, CParser::PARSE_SUCCESS, 'and'],
			['prefixand this', 6, CParser::PARSE_SUCCESS_CONT, 'and'],
			['prefixor', 6, CParser::PARSE_SUCCESS, 'or'],
			['prefixor this', 6, CParser::PARSE_SUCCESS_CONT, 'or'],

			['><', 0, CParser::PARSE_SUCCESS_CONT, '>'],

			['', 0, CParser::PARSE_FAIL, ''],
			['an', 0, CParser::PARSE_FAIL, ''],
			['anor', 0, CParser::PARSE_FAIL, ''],
			['+<', 0, CParser::PARSE_FAIL, ''],

			['prefixand', 5, CParser::PARSE_FAIL, ''],
			['prefixand', 7, CParser::PARSE_FAIL, '']
		];
	}
}
