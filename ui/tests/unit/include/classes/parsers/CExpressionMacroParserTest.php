<?php declare(strict_types=1);
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

class CExpressionMacroParserTest extends TestCase {

	public static function dataProvider() {
		return [
			['', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['{?', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['text {?}', 5, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['text {?1+1}', 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{?1+1}',
				'length' => 6
			]],
			['text {?1+1} text', 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{?1+1}',
				'length' => 6
			]],
			['text {? 1 + 1   }', 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{? 1 + 1   }',
				'length' => 12
			]],
			['text {?last(/'.'/system.cpu.load)}', 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{?last(/'.'/system.cpu.load)}',
				'length' => 26
			]],
			['text {? last(/{HOST.HOST}/key, #25) } text', 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{? last(/{HOST.HOST}/key, #25) }',
				'length' => 32
			]],
			['text {? last(/{HOST.HOST6}/key, #25) } text', 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{? last(/{HOST.HOST6}/key, #25) }',
				'length' => 33
			]],
			['text {? last(/host/key, #25) + max(sum(/host/key, 1d:now/d), sum(/host/key, 1d:now/d-1d)) } text', 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{? last(/host/key, #25) + max(sum(/host/key, 1d:now/d), sum(/host/key, 1d:now/d-1d)) }',
				'length' => 86
			]],
			['text {? 1 + 1   text', 5, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $result
	 */
	public function testExpressionMacroParser(string $source, int $pos, array $result) {
		$expression_macro_parser = new CExpressionMacroParser();

		$this->assertSame($result, [
			'rc' => $expression_macro_parser->parse($source, $pos),
			'match' => $expression_macro_parser->getMatch(),
			'length' => $expression_macro_parser->getLength()
		]);
	}
}
