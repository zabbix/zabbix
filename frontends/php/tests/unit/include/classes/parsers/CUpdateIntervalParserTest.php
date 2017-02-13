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


class CUpdateIntervalParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of update intervals and parsed results.
	 */
	public static function testProvider() {
		return [
			// success
			[
				'333h', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '333h'
				]
			],
			[
				'0;5m/1-5,09:00-18:00;wd6-7h9', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0;5m/1-5,09:00-18:00;wd6-7h9'
				]
			],
			[
				'{$SIMPLE_INTERVAL1}', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$SIMPLE_INTERVAL1}'
				]
			],
			[
				'{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD};{$SCHEDULING_INTERVAL}', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD};{$SCHEDULING_INTERVAL}'
				]
			],
			[
				'0;{$UPDATE_AT_NINE_A_M};{$UPDATE_EVERY_TEN_MINUTES}/{$ON_MONDAYS}', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0;{$UPDATE_AT_NINE_A_M};{$UPDATE_EVERY_TEN_MINUTES}/{$ON_MONDAYS}'
				]
			],
			[
				'{#SIMPLE_INTERVAL};{#FLEXIBLE_INTERVAL_DELAY}/{#FLEXIBLE_INTERVAL_PERIOD};{#SCHEDULING_INTERVAL}', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#SIMPLE_INTERVAL};{#FLEXIBLE_INTERVAL_DELAY}/{#FLEXIBLE_INTERVAL_PERIOD};{#SCHEDULING_INTERVAL}'
				]
			],
			// partial success
			[
				'random text.....10s....text', 16,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '10s'
				]
			],
			[
				'11s;md1-31a', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '11s;md1-31'
				]
			],
			[
				'30m;5h/1-7,09:00-18:00;600s/7-7,00:00-18:00;md30;wd5;md1-31h18m59s59;', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '30m;5h/1-7,09:00-18:00;600s/7-7,00:00-18:00;md30;wd5;md1-31h18m59s59'
				]
			],
			[
				'3;md1;', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '3;md1'
				]
			],
			// fail
			[
				'', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'12s;md1-31wd', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'4;5;6', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			]
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
		static $parser = null;

		if ($parser === null) {
			$parser = new CUpdateIntervalParser();
		}

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
