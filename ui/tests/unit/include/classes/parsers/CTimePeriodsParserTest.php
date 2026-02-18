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

class CTimePeriodsParserTest extends TestCase {

	/**
	 * An array of time periods and parsed results.
	 */
	public static function dataProvider() {
		return [
			// success
			[
				'1-3,00:01-00:02', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '1-3,00:01-00:02',
					'periods_parts' => [
						'1-3,00:01-00:02' => [
							'wd_from' => '1',
							'wd_till' => '3',
							'h_from' => '00',
							'm_from' => '01',
							'h_till' => '00',
							'm_till' => '02'
						]
					]
				]
			],
			[
				'3-4,00:05-00:06;4-5,00:07-00:08', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '3-4,00:05-00:06;4-5,00:07-00:08',
					'periods_parts' => [
						'3-4,00:05-00:06' => [
							'wd_from' => '3',
							'wd_till' => '4',
							'h_from' => '00',
							'm_from' => '05',
							'h_till' => '00',
							'm_till' => '06'
						],
						'4-5,00:07-00:08' => [
							'wd_from' => '4',
							'wd_till' => '5',
							'h_from' => '00',
							'm_from' => '07',
							'h_till' => '00',
							'm_till' => '08'
						]
					]
				]
			],
			[
				'1-7,00:00-24:00;{$MACRO}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '1-7,00:00-24:00;{$MACRO}',
					'periods_parts' => [
						'1-7,00:00-24:00' => [
							'wd_from' => '1',
							'wd_till' => '7',
							'h_from' => '00',
							'm_from' => '00',
							'h_till' => '24',
							'm_till' => '00'
						],
						'{$MACRO}' => []
					]
				]
			],
			[
				'{$MACRO1};{$MACRO2}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$MACRO1};{$MACRO2}',
					'periods_parts' => [
						'{$MACRO1}' => [],
						'{$MACRO2}' => []
					]
				]
			],
			[
				'{$MACRO1: ";"};{$MACRO2: ";"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$MACRO1: ";"};{$MACRO2: ";"}',
					'periods_parts' => [
						'{$MACRO1: ";"}' => [],
						'{$MACRO2: ";"}' => []
					]
				]
			],
			// fail
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'periods_parts' => []
				]
			],
			[
				';', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'periods_parts' => []
				]
			],
			[
				';;', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'periods_parts' => []
				]
			],
			[
				';;1-7,00:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'periods_parts' => []
				]
			],
			[
				'1-7,00:00-24:00;;', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'periods_parts' => []
				]
			],
			[
				'1-7,00:00-24:00;{$MACRO}', 0, ['user_macros' => false],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'periods_parts' => []
				]
			],
			[
				'1-3,00:01-00:02;', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'periods_parts' => []
				]
			],
			[
				'{$MACRO};1-7,00:00-24:00;', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'periods_parts' => []
				]
			],
			[
				'5-6,00:09-00:10;6-7,00:11-00:12a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'periods_parts' => []
				]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	*/
	public function testParse($source, $pos, $options, $expected) {
		$parser = new CTimePeriodsParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'periods_parts' => $parser->getPeriodsParts()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
