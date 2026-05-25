<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

class CTimePeriodParserTest extends TestCase {

	/**
	 * An array of time periods and parsed results.
	 */
	public static function dataProvider() {
		return [
			// success
			[
				'1-7,00:00-23:00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '1-7,00:00-23:00',
					'period_parts' => [
						'wd_from' => '1',
						'wd_till' => '7',
						'h_from' => '00',
						'm_from' => '00',
						'h_till' => '23',
						'm_till' => '00'
					]
				]
			],
			[
				'5-5,00:00-23:00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '5-5,00:00-23:00',
					'period_parts' => [
						'wd_from' => '5',
						'wd_till' => '5',
						'h_from' => '00',
						'm_from' => '00',
						'h_till' => '23',
						'm_till' => '00'
					]
				]
			],
			[
				'7,00:00-23:00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '7,00:00-23:00',
					'period_parts' => [
						'wd_from' => '7',
						'wd_till' => '7',
						'h_from' => '00',
						'm_from' => '00',
						'h_till' => '23',
						'm_till' => '00'
					]
				]
			],
			[
				'7,23:59-24:00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '7,23:59-24:00',
					'period_parts' => [
						'wd_from' => '7',
						'wd_till' => '7',
						'h_from' => '23',
						'm_from' => '59',
						'h_till' => '24',
						'm_till' => '00'
					]
				]
			],
			[
				'7,0:00-0:01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '7,0:00-0:01',
					'period_parts' => [
						'wd_from' => '7',
						'wd_till' => '7',
						'h_from' => '0',
						'm_from' => '00',
						'h_till' => '0',
						'm_till' => '01'
					]
				]
			],
			[
				'7,0:00-00:01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '7,0:00-00:01',
					'period_parts' => [
						'wd_from' => '7',
						'wd_till' => '7',
						'h_from' => '0',
						'm_from' => '00',
						'h_till' => '00',
						'm_till' => '01'
					]
				]
			],
			[
				'7,00:00-0:01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '7,00:00-0:01',
					'period_parts' => [
						'wd_from' => '7',
						'wd_till' => '7',
						'h_from' => '00',
						'm_from' => '00',
						'h_till' => '0',
						'm_till' => '01'
					]
				]
			],
			[
				'{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}',
					'period_parts' => []
				]
			],
			[
				'{{$M}.regsub("^([0-9]+)", \1)}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{$M}.regsub("^([0-9]+)", \1)}',
					'period_parts' => []
				]
			],
			[
				'{$M: "context"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M: "context"}',
					'period_parts' => []
				]
			],
			[
				'{$M: ";"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M: ";"}',
					'period_parts' => []
				]
			],
			[
				'{$M: "/"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M: "/"}',
					'period_parts' => []
				]
			],
			[
				'{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M}',
					'period_parts' => []
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'period_parts' => []
				]
			],
			// partial success
			[
				'random text.....1-7,00:00-00:01....text', 16, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '1-7,00:00-00:01',
					'period_parts' => [
						'wd_from' => '1',
						'wd_till' => '7',
						'h_from' => '00',
						'm_from' => '00',
						'h_till' => '00',
						'm_till' => '01'
					]
				]
			],
			[
				'1-7,00:00-00:011', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '1-7,00:00-00:01',
					'period_parts' => [
						'wd_from' => '1',
						'wd_till' => '7',
						'h_from' => '00',
						'm_from' => '00',
						'h_till' => '00',
						'm_till' => '01'
					]
				]
			],
			[
				'1-7,00:00-00:01a', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '1-7,00:00-00:01',
					'period_parts' => [
						'wd_from' => '1',
						'wd_till' => '7',
						'h_from' => '00',
						'm_from' => '00',
						'h_till' => '00',
						'm_till' => '01'
					]
				]
			],
			[
				'1-7,00:00-00:01;', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '1-7,00:00-00:01',
					'period_parts' => [
						'wd_from' => '1',
						'wd_till' => '7',
						'h_from' => '00',
						'm_from' => '00',
						'h_till' => '00',
						'm_till' => '01'
					]
				]
			],
			// fail
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'11', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1--', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1- ', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1 -', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1 ', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1, ', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,1a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:11', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:11-', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:11 ', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:11- ', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:11--', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:11-1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:11-11', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:11-11:', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:11-11:1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-1,11:11-11:11', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'8,11:11-11:12', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'7-3,11:11-11:12', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-7,00:00-24:01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-7,12:00-11:59', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-7,00:00-24:0', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'1-7,00:0-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			// User macros are not enabled.
			[
				'{$M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'{$M: "context"}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'{$M: ";"}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'{$M: "/"}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			// LLD macros are not enabled.
			[
				'{#M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'period_parts' => []
				]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $options
	 * @param array  $expected
	 */
	public function testParse($source, $pos, $options, $expected) {
		$parser = new CTimePeriodParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'period_parts' => $parser->getPeriodParts()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
