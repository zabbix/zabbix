<?php
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


use PHPUnit\Framework\TestCase;

class CUpdateIntervalParserTest extends TestCase {

	/**
	 * An array of update intervals and parsed results.
	 */
	public static function dataProvider() {
		return [
			// success
			[
				'333h', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '333h',
					'delay' => '333h',
					'intervals' => []
				]
			],
			[
				'0;5m/1-5,09:00-18:00;wd6-7h9', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0;5m/1-5,09:00-18:00;wd6-7h9',
					'delay' => '0',
					'intervals' => [
						[
							'type' => ITEM_DELAY_FLEXIBLE,
							'interval' => '5m/1-5,09:00-18:00',
							'update_interval' => '5m',
							'time_period' => '1-5,09:00-18:00'
						],
						['type' => ITEM_DELAY_SCHEDULING, 'interval' => 'wd6-7h9']
					]
				]
			],
			[
				'{$SIMPLE_INTERVAL1}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$SIMPLE_INTERVAL1}',
					'delay' => '{$SIMPLE_INTERVAL1}',
					'intervals' => []
				]
			],
			[
				'{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD};{$SCHEDULING_INTERVAL}', 0,
					['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD};'.
							'{$SCHEDULING_INTERVAL}',
					'delay' => '{$SIMPLE_INTERVAL}',
					'intervals' => [
						[
							'type' => ITEM_DELAY_FLEXIBLE,
							'interval' => '{$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD}',
							'update_interval' => '{$FLEXIBLE_INTERVAL_DELAY}',
							'time_period' => '{$FLEXIBLE_INTERVAL_PERIOD}'
						],
						[
							'type' => ITEM_DELAY_SCHEDULING,
							'interval' => '{$SCHEDULING_INTERVAL}'
						]
					]
				]
			],
			[
				'0;{$UPDATE_AT_NINE_A_M};{$UPDATE_EVERY_TEN_MINUTES}/{$ON_MONDAYS}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0;{$UPDATE_AT_NINE_A_M};{$UPDATE_EVERY_TEN_MINUTES}/{$ON_MONDAYS}',
					'delay' => '0',
					'intervals' => [
						['type' => ITEM_DELAY_SCHEDULING, 'interval' => '{$UPDATE_AT_NINE_A_M}'],
						[
							'type' => ITEM_DELAY_FLEXIBLE,
							'interval' => '{$UPDATE_EVERY_TEN_MINUTES}/{$ON_MONDAYS}',
							'update_interval' => '{$UPDATE_EVERY_TEN_MINUTES}',
							'time_period' => '{$ON_MONDAYS}'
						]
					]
				]
			],
			[
				'{#SIMPLE_INTERVAL};{#FLEXIBLE_INTERVAL_DELAY}/{#FLEXIBLE_INTERVAL_PERIOD};{#SCHEDULING_INTERVAL}', 0,
					['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#SIMPLE_INTERVAL};{#FLEXIBLE_INTERVAL_DELAY}/{#FLEXIBLE_INTERVAL_PERIOD};'.
							'{#SCHEDULING_INTERVAL}',
					'delay' => '{#SIMPLE_INTERVAL}',
					'intervals' => [
						[
							'type' => ITEM_DELAY_FLEXIBLE,
							'interval' => '{#FLEXIBLE_INTERVAL_DELAY}/{#FLEXIBLE_INTERVAL_PERIOD}',
							'update_interval' => '{#FLEXIBLE_INTERVAL_DELAY}',
							'time_period' => '{#FLEXIBLE_INTERVAL_PERIOD}'
						],
						[
							'type' => ITEM_DELAY_SCHEDULING,
							'interval' => '{#SCHEDULING_INTERVAL}'
						]
					]
				]
			],
			[
				'{{#SIMPLE_INTERVAL}.regsub("^([0-9]+)", "{#SIMPLE_INTERVAL}: \1")};'.
						'{{#FLEXIBLE_INTERVAL_DELAY}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_DELAY}: \1")}/'.
						'{{#FLEXIBLE_INTERVAL_PERIOD}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_PERIOD}: \1")};'.
						'{{#SCHEDULING_INTERVAL}.regsub("^([0-9]+)", "{#SCHEDULING_INTERVAL}: \1")}', 0,
					['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#SIMPLE_INTERVAL}.regsub("^([0-9]+)", "{#SIMPLE_INTERVAL}: \1")};'.
							'{{#FLEXIBLE_INTERVAL_DELAY}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_DELAY}: \1")}/'.
							'{{#FLEXIBLE_INTERVAL_PERIOD}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_PERIOD}: \1")};'.
							'{{#SCHEDULING_INTERVAL}.regsub("^([0-9]+)", "{#SCHEDULING_INTERVAL}: \1")}',
					'delay' => '{{#SIMPLE_INTERVAL}.regsub("^([0-9]+)", "{#SIMPLE_INTERVAL}: \1")}',
					'intervals' => [
						[
							'type' => ITEM_DELAY_FLEXIBLE,
							'interval' =>
								'{{#FLEXIBLE_INTERVAL_DELAY}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_DELAY}: \1")}/'.
								'{{#FLEXIBLE_INTERVAL_PERIOD}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_PERIOD}: \1")}',
							'update_interval' =>
								'{{#FLEXIBLE_INTERVAL_DELAY}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_DELAY}: \1")}',
							'time_period' =>
								'{{#FLEXIBLE_INTERVAL_PERIOD}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_PERIOD}: \1")}'
						],
						[
							'type' => ITEM_DELAY_SCHEDULING,
							'interval' => '{{#SCHEDULING_INTERVAL}.regsub("^([0-9]+)", "{#SCHEDULING_INTERVAL}: \1")}'
						]
					]
				]
			],
			[
				'0;{$M: ";"}/{$M: "/"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0;{$M: ";"}/{$M: "/"}',
					'delay' => '0',
					'intervals' => [
						[
							'type' => ITEM_DELAY_FLEXIBLE,
							'interval' => '{$M: ";"}/{$M: "/"}',
							'update_interval' => '{$M: ";"}',
							'time_period' => '{$M: "/"}'
						]
					]
				]
			],
			// partial success
			[
				'00h;5m/1-5,09:00-18:00;wd6-7h9', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '0',
					'delay' => '0',
					'intervals' => []
				]
			],
			[
				'random text.....10s....text', 16, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '10s',
					'delay' => '10s',
					'intervals' => []
				]
			],
			[
				'11s;md1-31a', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '11s;md1-31',
					'delay' => '11s',
					'intervals' => [
						['type' => ITEM_DELAY_SCHEDULING, 'interval' => 'md1-31']
					]
				]
			],
			[
				'30m;5h/1-7,09:00-18:00;600s/7-7,00:00-18:00;md30;wd5;md1-31h18m59s59;', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '30m;5h/1-7,09:00-18:00;600s/7-7,00:00-18:00;md30;wd5;md1-31h18m59s59',
					'delay' => '30m',
					'intervals' => [
						[
							'type' => ITEM_DELAY_FLEXIBLE,
							'interval' => '5h/1-7,09:00-18:00',
							'update_interval' => '5h',
							'time_period' => '1-7,09:00-18:00'
						],
						[
							'type' => ITEM_DELAY_FLEXIBLE,
							'interval' => '600s/7-7,00:00-18:00',
							'update_interval' => '600s',
							'time_period' => '7-7,00:00-18:00'
						],
						['type' => ITEM_DELAY_SCHEDULING, 'interval' => 'md30'],
						['type' => ITEM_DELAY_SCHEDULING, 'interval' => 'wd5'],
						['type' => ITEM_DELAY_SCHEDULING, 'interval' => 'md1-31h18m59s59']
					]
				]
			],
			[
				'3;md1;', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '3;md1',
					'delay' => '3',
					'intervals' => [
						['type' => ITEM_DELAY_SCHEDULING, 'interval' => 'md1']
					]
				]
			],
			[
				'12s;md1-31wd', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '12s;md1-31',
					'delay' => '12s',
					'intervals' => [
						['type' => ITEM_DELAY_SCHEDULING, 'interval' => 'md1-31']
					]
				]
			],
			[
				'4;5;6', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '4',
					'delay' => '4',
					'intervals' => []
				]
			],
			[
				'{$SIMPLE_INTERVAL};{#FLEXIBLE_INTERVAL_DELAY}/{#FLEXIBLE_INTERVAL_PERIOD};{#SCHEDULING_INTERVAL}', 0,
					['usermacros' => true, 'lldmacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$SIMPLE_INTERVAL}',
					'delay' => '{$SIMPLE_INTERVAL}',
					'intervals' => []
				]
			],
			[
				'{$SIMPLE_INTERVAL};'.
						'{{#FLEXIBLE_INTERVAL_DELAY}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_DELAY}: \1")}/'.
						'{#FLEXIBLE_INTERVAL_PERIOD};{#SCHEDULING_INTERVAL}', 0,
					['usermacros' => true, 'lldmacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$SIMPLE_INTERVAL}',
					'delay' => '{$SIMPLE_INTERVAL}',
					'intervals' => []
				]
			],
			[
				'{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}/{#FLEXIBLE_INTERVAL_PERIOD};{#SCHEDULING_INTERVAL}', 0,
					['usermacros' => true, 'lldmacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}',
					'delay' => '{$SIMPLE_INTERVAL}',
					'intervals' => [
						['type' => ITEM_DELAY_SCHEDULING, 'interval' => '{$FLEXIBLE_INTERVAL_DELAY}']
					]
				]
			],
			[
				'{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}/'.
						'{{#FLEXIBLE_INTERVAL_PERIOD}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_PERIOD}: \1")};'.
						'{#SCHEDULING_INTERVAL}',
					0,
					['usermacros' => true, 'lldmacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}',
					'delay' => '{$SIMPLE_INTERVAL}',
					'intervals' => [
						['type' => ITEM_DELAY_SCHEDULING, 'interval' => '{$FLEXIBLE_INTERVAL_DELAY}']
					]
				]
			],
			[
				'{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD};{#SCHEDULING_INTERVAL}', 0,
					['usermacros' => true, 'lldmacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD}',
					'delay' => '{$SIMPLE_INTERVAL}',
					'intervals' => [[
						'type' => ITEM_DELAY_FLEXIBLE,
						'interval' => '{$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD}',
						'update_interval' => '{$FLEXIBLE_INTERVAL_DELAY}',
						'time_period' => '{$FLEXIBLE_INTERVAL_PERIOD}'
					]]
				]
			],
			[
				'{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD};'.
						'{{#SCHEDULING_INTERVAL}.regsub("^([0-9]+)", "{#SCHEDULING_INTERVAL}: \1")}',
					0,
					['usermacros' => true, 'lldmacros' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$SIMPLE_INTERVAL};{$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD}',
					'delay' => '{$SIMPLE_INTERVAL}',
					'intervals' => [[
						'type' => ITEM_DELAY_FLEXIBLE,
						'interval' => '{$FLEXIBLE_INTERVAL_DELAY}/{$FLEXIBLE_INTERVAL_PERIOD}',
						'update_interval' => '{$FLEXIBLE_INTERVAL_DELAY}',
						'time_period' => '{$FLEXIBLE_INTERVAL_PERIOD}'
					]]
				]
			],
			// fail
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'delay' => '',
					'intervals' => []
				]
			],
			[
				's', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'delay' => '',
					'intervals' => []
				]
			],
			[
				'{#SIMPLE_INTERVAL};{#FLEXIBLE_INTERVAL_DELAY}/{#FLEXIBLE_INTERVAL_PERIOD};{#SCHEDULING_INTERVAL}', 0,
					['lldmacros' => false],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'delay' => '',
					'intervals' => []
				]
			],
			[
				'{{#SIMPLE_INTERVAL}.regsub("^([0-9]+)", "{#SIMPLE_INTERVAL}: \1")};'.
						'{{#FLEXIBLE_INTERVAL_DELAY}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_DELAY}: \1")}/'.
						'{{#FLEXIBLE_INTERVAL_PERIOD}.regsub("^([0-9]+)", "{#FLEXIBLE_INTERVAL_PERIOD}: \1")};'.
						'{{#SCHEDULING_INTERVAL}.regsub("^([0-9]+)", "{#SCHEDULING_INTERVAL}: \1")}',
					0,
					['lldmacros' => false],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'delay' => '',
					'intervals' => []
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
		$parser = new CUpdateIntervalParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'delay' => $parser->getDelay(),
			'intervals' => $parser->getIntervals()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
