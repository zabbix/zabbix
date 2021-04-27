<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


use PHPUnit\Framework\TestCase;

class CFilterParserTest extends TestCase {

	public function dataProvider() {
		return [
			[
				'?[tag="name"]', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '?[tag="name"]'
				]
			],
			[
				'?[ group = "\\"string1\\"" and tag = "\\"string2\\"" ]', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '?[ group = "\"string1\"" and tag = "\"string2\"" ]'
				]
			],
			[
				'?[((tag="tag1" or group="name") and tag="tag2")]', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '?[((tag="tag1" or group="name") and tag="tag2")]'
				]
			],
			[
				'?[tag="name"] text', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '?[tag="name"]'
				]
			],
			[
				'?[tag=tag]', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'?["string1" = "string2"]', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'?[()]', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'?[(tag = "tag"]', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testFilterParser($source, $pos, $expected) {
		$filter_parser = new CFilterParser();

		$this->assertSame($expected, [
			'rc' => $filter_parser->parse($source, $pos),
			'match' => $filter_parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $filter_parser->getLength());
	}
}
