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


class CReferenceParserTest extends CParserTest {

	protected function getParser() {
		return new CReferenceParser();
	}

	public function dataProvider() {
		return [
			['$1', 0, CParser::PARSE_SUCCESS, '$1'],
			['$2', 0, CParser::PARSE_SUCCESS, '$2'],
			['$3', 0, CParser::PARSE_SUCCESS, '$3'],
			['$4', 0, CParser::PARSE_SUCCESS, '$4'],
			['$5', 0, CParser::PARSE_SUCCESS, '$5'],
			['$6', 0, CParser::PARSE_SUCCESS, '$6'],
			['$7', 0, CParser::PARSE_SUCCESS, '$7'],
			['$8', 0, CParser::PARSE_SUCCESS, '$8'],
			['$9', 0, CParser::PARSE_SUCCESS, '$9'],
			['abc$5def', 3, CParser::PARSE_SUCCESS_CONT, '$5'],

			['', 0, CParser::PARSE_FAIL, ''],
			['1', 0, CParser::PARSE_FAIL, ''],
			['$a', 0, CParser::PARSE_FAIL, ''],
			['$0', 0, CParser::PARSE_FAIL, ''],
			['$', 0, CParser::PARSE_FAIL, '']
		];
	}
}
