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


use PHPUnit\Framework\TestCase;

class CExpressionMacroParserTest extends TestCase {

	public static function dataProvider() {
		return [
			['', [], 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['{', [], 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['{?', [], 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['text {?}', [], 5, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['text {?1+1}', [], 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{?1+1}',
				'length' => 6
			]],
			['text {?1+1} text', [], 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{?1+1}',
				'length' => 6
			]],
			['text {? 1 + 1   }', [], 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{? 1 + 1   }',
				'length' => 12
			]],
			['text {?last(/'.'/system.cpu.load)}', [], 5, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['text {?last(/'.'/system.cpu.load)}', ['empty_host' => true], 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{?last(/'.'/system.cpu.load)}',
				'length' => 26
			]],
			['text {? last(/{HOST.HOST}/key, #25) } text', [], 5, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['text {? last(/{HOST.HOST6}/key, #25) } text', ['host_macro' => true], 5, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['text {? last(/{HOST.HOST}/key, #25) } text', ['host_macro' => true], 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{? last(/{HOST.HOST}/key, #25) }',
				'length' => 32
			]],
			['text {? last(/{HOST.HOST}/key, #25) } text', ['host_macro_n' => true], 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{? last(/{HOST.HOST}/key, #25) }',
				'length' => 32
			]],
			['text {? last(/{HOST.HOST6}/key, #25) } text', ['host_macro_n' => true], 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{? last(/{HOST.HOST6}/key, #25) }',
				'length' => 33
			]],
			['text {? last(/host/key, #25) + max(sum(/host/key, 1d:now/d), sum(/host/key, 1d:now/d-1d)) } text', [], 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{? last(/host/key, #25) + max(sum(/host/key, 1d:now/d), sum(/host/key, 1d:now/d-1d)) }',
				'length' => 86
			]],
			['text {?last(/Zabbix server/system.cpu.load, {#LLD})}', [], 5, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['text {?last(/Zabbix server/system.cpu.load, {#LLD})}', ['lldmacros' => true], 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{?last(/Zabbix server/system.cpu.load, {#LLD})}',
				'length' => 47
			]],
			['text {?last(/Zabbix server/system.cpu.load, {$MACRO})}', [], 5, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['text {?last(/Zabbix server/system.cpu.load, {$MACRO})}', ['usermacros' => true], 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{?last(/Zabbix server/system.cpu.load, {$MACRO})}',
				'length' => 49
			]],
			['text {? 1 + 1   text', [], 5, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['text {?nodata(/Zabbix server/system.cpu.load, "\\\\")}', [], 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{?nodata(/Zabbix server/system.cpu.load, "\\\\")}',
				'length' => 47
			]],
			['text {?nodata(/Zabbix server/system.cpu.load, "\\\\")}', ['escape_backslashes' => false], 5, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0
			]],
			['text {?nodata(/Zabbix server/system.cpu.load, "\\ ")}', ['escape_backslashes' => false], 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{?nodata(/Zabbix server/system.cpu.load, "\\ ")}',
				'length' => 47
			]],
			['text {? min({FUNCTION.VALUE}, {FUNCTION.VALUE9}) / {FUNCTION.RECOVERY.VALUE1} } text', ['macros_n' => ['{FUNCTION.VALUE}', '{FUNCTION.RECOVERY.VALUE}']], 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{? min({FUNCTION.VALUE}, {FUNCTION.VALUE9}) / {FUNCTION.RECOVERY.VALUE1} }',
				'length' => 74
			]],
			['text {? min({FUNCTION.VALUE}, {FUNCTION.VALUE9}) / {FUNCTION.RECOVERY.VALUE1} } text', ['macros_n' => ['{FUNCTION.VALUE}']], 5, [
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
	public function testExpressionMacroParser(string $source, array $options, int $pos, array $result) {
		$expression_macro_parser = new CExpressionMacroParser($options);

		$this->assertSame($result, [
			'rc' => $expression_macro_parser->parse($source, $pos),
			'match' => $expression_macro_parser->getMatch(),
			'length' => $expression_macro_parser->getLength()
		]);
		$this->assertTrue($expression_macro_parser->getExpressionParser() instanceof CExpressionParser);
	}
}
