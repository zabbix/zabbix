<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CReplacementParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of user macros and parsed results.
	 */
	public function testProvider() {
		return [
			// Normal macros without context.
			['\\0', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\0'
			]],
			['\\1', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\1'
			]],
			['\\2', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\2'
			]],
			['\\3', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\3'
			]],
			['\\4', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\4'
			]],
			['\\5', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\5'
			]],
			['\\6', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\6'
			]],
			['\\7', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\7'
			]],
			['\\8', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\8'
			]],
			['\\9', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\9'
			]],
			['\\9\\0', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '\\9'
			]],
			['\\a\\0', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => ''
			]],
			['\\a\\0', 2, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\0'
			]]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	 */
	public function testParse($source, $pos, $expected) {
		static $replacement_parser = null;

		if ($replacement_parser === null) {
			$replacement_parser = new CReplacementParser();
		}

		$this->assertSame($expected, [
			'rc' => $replacement_parser->parse($source, $pos),
			'match' => $replacement_parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $replacement_parser->getLength());
	}
}
