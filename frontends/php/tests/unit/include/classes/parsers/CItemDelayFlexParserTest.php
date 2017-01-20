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


class CItemDelayFlexParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of valid flexible intervals parsed results.
	 */
	public function testValidFlexibleProvider() {
		return [
			// Syntax is correct and all time periods are valid.
			[
				'86400/7-7,23:59-24:00',
				[[
					'interval' => '86400/7-7,23:59-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '86400',
					'period' => '7-7,23:59-24:00'
				]],
				['86400/7-7,23:59-24:00']
			],
			[
				'00000000000/7-7,23:59-24:00',
				[[
					'interval' => '00000000000/7-7,23:59-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '00000000000',
					'period' => '7-7,23:59-24:00'
				]],
				['00000000000/7-7,23:59-24:00']
			],
			[
				'9999/7-7,23:59-24:00',
				[[
					'interval' => '9999/7-7,23:59-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '9999',
					'period' => '7-7,23:59-24:00'
				]],
				['9999/7-7,23:59-24:00']
			],
			[
				'50/7,0:00-0:01',
				[[
					'interval' => '50/7,0:00-0:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '50',
					'period' => '7,0:00-0:01'
				]],
				['50/7,0:00-0:01']
			],
			[
				'50/7,00:00-0:01',
				[[
					'interval' => '50/7,00:00-0:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '50',
					'period' => '7,00:00-0:01'
				]],
				['50/7,00:00-0:01']
			],
			[
				'50/7,00:00-00:01',
				[[
					'interval' => '50/7,00:00-00:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '50',
					'period' => '7,00:00-00:01'
				]],
				['50/7,00:00-00:01']
			],
			[
				'50/7,0:00-00:01',
				[[
					'interval' => '50/7,0:00-00:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '50',
					'period' => '7,0:00-00:01'
				]],
				['50/7,0:00-00:01']
			],
			[
				'50/1-7,0:00-00:01',
				[[
					'interval' => '50/1-7,0:00-00:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '50',
					'period' => '1-7,0:00-00:01'
				]],
				['50/1-7,0:00-00:01']
			],
			[
				'50/1-7,0:01-0:02',
				[[
					'interval' => '50/1-7,0:01-0:02',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '50',
					'period' => '1-7,0:01-0:02'
				]],
				['50/1-7,0:01-0:02']
			],
			[
				'50/7,0:01-0:02',
				[[
					'interval' => '50/7,0:01-0:02',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '50',
					'period' => '7,0:01-0:02'
				]],
				['50/7,0:01-0:02']
			],
			[
				'50/7,0:01-0:02;50/4-7,00:01-0:59',
				[[
					'interval' => '50/7,0:01-0:02',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '50',
					'period' => '7,0:01-0:02'
				],
				[
					'interval' => '50/4-7,00:01-0:59',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '50',
					'period' => '4-7,00:01-0:59'
				]],
				['50/7,0:01-0:02', '50/4-7,00:01-0:59']
			],
			[
				'000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000/7,0:01-0:02',
				[[
					'interval' => '000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000/7,0:01-0:02',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000',
					'period' => '7,0:01-0:02'
				]],
				['000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000/7,0:01-0:02']
			],
			// Syntax is correct, but time delays and periods are not valid.
			[
				'86401/7,0:01-0:02',
				[[
					'interval' => '86401/7,0:01-0:02',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '86401',
					'period' => '7,0:01-0:02'
				]],
				['86401/7,0:01-0:02']
			],
			[
				'99999/7,23:59-23:59',
				[[
					'interval' => '99999/7,23:59-23:59',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '99999',
					'period' => '7,23:59-23:59'
				]],
				['99999/7,23:59-23:59']
			],
			[
				'86400/7-7,23:59-23:59',
				[[
					'interval' => '86400/7-7,23:59-23:59',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '86400',
					'period' => '7-7,23:59-23:59'
				]],
				['86400/7-7,23:59-23:59']
			],
			[
				'86400/8-9,23:59-24:00',
				[[
					'interval' => '86400/8-9,23:59-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '86400',
					'period' => '8-9,23:59-24:00'
				]],
				['86400/8-9,23:59-24:00']
			],
			[
				'9/8,23:59-24:00',
				[[
					'interval' => '9/8,23:59-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '9',
					'period' => '8,23:59-24:00'
				]],
				['9/8,23:59-24:00']
			],
			[
				'5/7-9,0:00-24:00',
				[[
					'interval' => '5/7-9,0:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '5',
					'period' => '7-9,0:00-24:00'
				]],
				['5/7-9,0:00-24:00']
			],
			[
				'5/7-6,0:00-24:00',
				[[
					'interval' => '5/7-6,0:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '5',
					'period' => '7-6,0:00-24:00'
				]],
				['5/7-6,0:00-24:00']
			],
			[
				'0/0,0:00-24:00',
				[[
					'interval' => '0/0,0:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '0,0:00-24:00'
				]],
				['0/0,0:00-24:00']
			],
			[
				'0/1-0,0:00-24:00',
				[[
					'interval' => '0/1-0,0:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '1-0,0:00-24:00'
				]],
				['0/1-0,0:00-24:00']
			],
			[
				'0/1-0,0:00-24:00',
				[[
					'interval' => '0/1-0,0:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '1-0,0:00-24:00'
				]],
				['0/1-0,0:00-24:00']
			],
			[
				'0/2,00:00-00:00',
				[[
					'interval' => '0/2,00:00-00:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,00:00-00:00'
				]],
				['0/2,00:00-00:00']
			],
			[
				'0/2,00:01-00:00',
				[[
					'interval' => '0/2,00:01-00:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,00:01-00:00'
				]],
				['0/2,00:01-00:00']
			],
			[
				'0/2,5:00-29:00',
				[[
					'interval' => '0/2,5:00-29:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,5:00-29:00'
				]],
				['0/2,5:00-29:00']
			],
			[
				'0/2,24:00-24:01',
				[[
					'interval' => '0/2,24:00-24:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,24:00-24:01'
				]],
				['0/2,24:00-24:01']
			],
			[
				'0/2,24:00-23:00',
				[[
					'interval' => '0/2,24:00-23:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,24:00-23:00'
				]],
				['0/2,24:00-23:00']
			],
			[
				'0/2,99:00-24:00',
				[[
					'interval' => '0/2,99:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,99:00-24:00'
				]],
				['0/2,99:00-24:00']
			],
			[
				'0/2,99:99-99:99',
				[[
					'interval' => '0/2,99:99-99:99',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,99:99-99:99'
				]],
				['0/2,99:99-99:99']
			]
		];
	}

	/**
	 * An array of invalid flexible intervals and error messages.
	 */
	public function testInvalidFlexibleProvider() {
		return [
			['1,23:59-24:00', 'incorrect syntax near ",23:59-24:00"'],
			['7-7,23:59-24:00', 'incorrect syntax near "-7,23:59-24:00"'],
			['5/2,1:00-9:20;', 'unexpected end of interval'],
			[';5/1,1:00-9:20', 'incorrect syntax near ";5/1,1:00-9:20"'],
			['5a/7,0:00-24:00', 'incorrect syntax near "a/7,0:00-24:00"'],
			['5//7,0:00-24:00', 'incorrect syntax near "/7,0:00-24:00"'],
			['5 7,0:00-24:00', 'incorrect syntax near " 7,0:00-24:00"'],
			['5/ 7,0:00-24:00', 'incorrect syntax near " 7,0:00-24:00"'],
			['5 /7,0:00-24:00', 'incorrect syntax near " /7,0:00-24:00"'],
			['5/z7,0:00-24:00', 'incorrect syntax near "z7,0:00-24:00"'],
			['5\/7,0:00-24:00', 'incorrect syntax near "\/7,0:00-24:00"'],
			['5/77,0:00-24:00', 'incorrect syntax near "7,0:00-24:00"'],
			['1/1-000,0:00-24:00', 'incorrect syntax near "00,0:00-24:00"'],
			['1/1-1 ,0:00-24:00', 'incorrect syntax near " ,0:00-24:00"'],
			['1/1 ,0:00-24:00', 'incorrect syntax near " ,0:00-24:00"'],
			['1/01,0:00-24:00', 'incorrect syntax near "1,0:00-24:00"'],
			['1/1-07,0:00-24:00', 'incorrect syntax near "7,0:00-24:00"'],
			['1/1-7,,0:00-24:00', 'incorrect syntax near ",0:00-24:00"'],
			['1 1-7,0:00-24:00', 'incorrect syntax near " 1-7,0:00-24:00"'],
			['1/00-7,0:00-24:00', 'incorrect syntax near "0-7,0:00-24:00"'],
			['1/1-7-7,0:00-24:00', 'incorrect syntax near "-7,0:00-24:00"'],
			['1/001-7,0:00-24:00', 'incorrect syntax near "01-7,0:00-24:00"'],
			['1/1--7,0:00-24:00', 'incorrect syntax near "-7,0:00-24:00"'],
			['5/7+6,0:00-24:00', 'incorrect syntax near "+6,0:00-24:00"'],
			['5/7/6,0:00-24:00', 'incorrect syntax near "/6,0:00-24:00"'],
			['5/6a,0:00-24:00', 'incorrect syntax near "a,0:00-24:00"'],
			['5/1-6a,0:00-24:00', 'incorrect syntax near "a,0:00-24:00"'],
			['5/1-60,0:00-24:00', 'incorrect syntax near "0,0:00-24:00"'],
			['5/1-6, 0:00-24:00', 'incorrect syntax near " 0:00-24:00"'],
			['5/1-6,,0:00-24:00', 'incorrect syntax near ",0:00-24:00"'],
			['5/1-6,:00-24:00', 'incorrect syntax near ":00-24:00"'],
			['0/2,000:00-24:00', 'incorrect syntax near "0:00-24:00"'],
			['0/2,00:0-24:00', 'incorrect syntax near "-24:00"'],
			['0/2,0:0-24:00', 'incorrect syntax near "-24:00"'],
			['0/2,0::00-24:00', 'incorrect syntax near ":00-24:00"'],
			['0/2,00::00-24:00', 'incorrect syntax near ":00-24:00"'],
			['0/2,00:000-24:00', 'incorrect syntax near "0-24:00"'],
			['0/2,00:0024:00', 'incorrect syntax near "24:00"'],
			['0/2,00:00--24:00', 'incorrect syntax near "-24:00"'],
			['0/2,00:00 -24:00', 'incorrect syntax near " -24:00"'],
			['0/2,00:00- 24:00', 'incorrect syntax near " 24:00"'],
			['0/2,00-24:00', 'incorrect syntax near "-24:00"'],
			['0/2,23:59-2400', 'incorrect syntax near "00"'],
			['0/2,0:00-9:000', 'incorrect syntax near "0"'],
			['0/2,0:00-9:2', 'unexpected end of interval'],
			['0/2,0:00-111:2', 'incorrect syntax near "1:2"'],
			['0/2,1:00-9::20', 'incorrect syntax near ":20"'],
			['0/2,1:00-09::20', 'incorrect syntax near ":20"'],
			['0/2,1:00-9:20a', 'incorrect syntax near "a"'],
			['0/2,00:00-024:00', 'incorrect syntax near "4:00"'],
			['50/7,0:01-0:02;0/2,00-24:00', 'incorrect syntax near "-24:00"'],
			['50/1-�,00:00-23:59', 'incorrect syntax near "�,00:00-23:59"']
		];
	}

	/**
	 * An array of valid scheduling intervals parsed results.
	 */
	public function testValidSchedulingProvider() {
		return [
			// Scheduling months. Syntax is valid and values are valid.
			[
				'md/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30',
					'md' => [
						['from' => '', 'till' => '', 'step' => '30']
					]
				]],
				['md/30']
			],
			[
				'md1-31/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31/30',
					'md' => [
						['from' => '1', 'till' => '31', 'step' => '30']
					]
				]],
				['md1-31/30']
			],
			[
				'md1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-1',
					'md' => [
						['from' => '1', 'till' => '1', 'step' => '']
					]
				]],
				['md1-1']
			],
			[
				'md28-30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md28-30',
					'md' => [
						['from' => '28', 'till' => '30', 'step' => '']
					]
				]],
				['md28-30']
			],
			[
				'md01-31',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-31',
					'md' => [
						['from' => '01', 'till' => '31', 'step' => '']
					]
				]],
				['md01-31']
			],
			[
				'md01-31/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-31/30',
					'md' => [
						['from' => '01', 'till' => '31', 'step' => '30']
					]
				]],
				['md01-31/30']
			],
			[
				'md1-5,8-31',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-5,8-31',
					'md' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '8', 'till' => '31', 'step' => '']
					]
				]],
				['md1-5,8-31']
			],
			[
				'md/30,1-5/4,8-31/23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30,1-5/4,8-31/23',
					'md' => [
						['from' => '', 'till' => '', 'step' => '30'],
						['from' => '1', 'till' => '5', 'step' => '4'],
						['from' => '8', 'till' => '31', 'step' => '23']
					]
				]],
				['md/30,1-5/4,8-31/23']
			],
			[
				'md1-5/4,8-31/23,/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-5/4,8-31/23,/30',
					'md' => [
						['from' => '1', 'till' => '5', 'step' => '4'],
						['from' => '8', 'till' => '31', 'step' => '23'],
						['from' => '', 'till' => '', 'step' => '30']
					]
				]],
				['md1-5/4,8-31/23,/30']
			],
			[
				'md31-31',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md31-31',
					'md' => [
						['from' => '31', 'till' => '31', 'step' => '']
					]
				]],
				['md31-31']
			],
			[
				'md01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01',
					'md' => [
						['from' => '01', 'till' => '', 'step' => '']
					]
				]],
				['md01']
			],
			[
				'md1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1',
					'md' => [
						['from' => '1', 'till' => '', 'step' => '']
					]
				]],
				['md1']
			],
			[
				'md10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md10',
					'md' => [
						['from' => '10', 'till' => '', 'step' => '']
					]
				]],
				['md10']
			],
			[
				'md31',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md31',
					'md' => [
						['from' => '31', 'till' => '', 'step' => '']
					]
				]],
				['md31']
			],
			[
				'md1,10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1,10',
					'md' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '10', 'till' => '', 'step' => '']
					]
				]],
				['md1,10']
			],
			[
				'md01,10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01,10',
					'md' => [
						['from' => '01', 'till' => '', 'step' => ''],
						['from' => '10', 'till' => '', 'step' => '']
					]
				]],
				['md01,10']
			],
			[
				'md01;md1;md31;md1,10;md01,10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01',
					'md' => [
						['from' => '01', 'till' => '', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1',
					'md' => [
						['from' => '1', 'till' => '', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md31',
					'md' => [
						['from' => '31', 'till' => '', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1,10',
					'md' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '10', 'till' => '', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01,10',
					'md' => [
						['from' => '01', 'till' => '', 'step' => ''],
						['from' => '10', 'till' => '', 'step' => '']
					]
				]],
				['md01', 'md1', 'md31', 'md1,10', 'md01,10']
			],
			[
				'md01-10/9',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-10/9',
					'md' => [
						['from' => '01', 'till' => '10', 'step' => '9']
					]
				]],
				['md01-10/9']
			],
			[
				'md1-10/9',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-10/9',
					'md' => [
						['from' => '1', 'till' => '10', 'step' => '9']
					]
				]],
				['md1-10/9']
			],
			[
				'md/9',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/9',
					'md' => [
						['from' => '', 'till' => '', 'step' => '9']
					]
				]],
				['md/9']
			],
			[
				'md/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30',
					'md' => [
						['from' => '', 'till' => '', 'step' => '30']
					]
				]],
				['md/30']
			],
			[
				'md/30;md/25',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30',
					'md' => [
						['from' => '', 'till' => '', 'step' => '30']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/25',
					'md' => [
						['from' => '', 'till' => '', 'step' => '25']
					]
				]],
				['md/30', 'md/25']
			],
			[
				'md1-31/30;md/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31/30',
					'md' => [
						['from' => '1', 'till' => '31', 'step' => '30']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30',
					'md' => [
						['from' => '', 'till' => '', 'step' => '30']
					]
				]],
				['md1-31/30', 'md/30']
			],
			[
				'md01-31/30;md/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-31/30',
					'md' => [
						['from' => '01', 'till' => '31', 'step' => '30']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30',
					'md' => [
						['from' => '', 'till' => '', 'step' => '30']
					]
				]],
				['md01-31/30', 'md/30']
			],
			[
				'md01-31;md/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-31',
					'md' => [
						['from' => '01', 'till' => '31', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30',
					'md' => [
						['from' => '', 'till' => '', 'step' => '30']
					]
				]],
				['md01-31', 'md/30']
			],
			[
				'md1-31/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31/01',
					'md' => [
						['from' => '1', 'till' => '31', 'step' => '01']
					]
				]],
				['md1-31/01']
			],
			[
				'md1,/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1,/1',
					'md' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '', 'till' => '', 'step' => '1']
					]
				]],
				['md1,/1']
			],
			// Scheduling months. Syntax is valid, but month days are incorrect.
			[
				'md01-01/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-01/01',
					'md' => [
						['from' => '01', 'till' => '01', 'step' => '01']
					]
				]],
				['md01-01/01']
			],
			[
				'md01-02/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-02/01',
					'md' => [
						['from' => '01', 'till' => '02', 'step' => '01']
					]
				]],
				['md01-02/01']
			],
			[
				'md02-02/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md02-02/01',
					'md' => [
						['from' => '02', 'till' => '02', 'step' => '01']
					]
				]],
				['md02-02/01']
			],
			[
				'md03-02/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md03-02/01',
					'md' => [
						['from' => '03', 'till' => '02', 'step' => '01']
					]
				]],
				['md03-02/01']
			],
			[
				'md03-02/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md03-02/1',
					'md' => [
						['from' => '03', 'till' => '02', 'step' => '1']
					]
				]],
				['md03-02/1']
			],
			[
				'md03-02/10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md03-02/10',
					'md' => [
						['from' => '03', 'till' => '02', 'step' => '10']
					]
				]],
				['md03-02/10']
			],
			[
				'md03-02/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md03-02/99',
					'md' => [
						['from' => '03', 'till' => '02', 'step' => '99']
					]
				]],
				['md03-02/99']
			],
			[
				'md03-02',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md03-02',
					'md' => [
						['from' => '03', 'till' => '02', 'step' => '']
					]
				]],
				['md03-02']
			],
			[
				'md00-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md00-00',
					'md' => [
						['from' => '00', 'till' => '00', 'step' => '']
					]
				]],
				['md00-00']
			],
			[
				'md00-99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md00-99',
					'md' => [
						['from' => '00', 'till' => '99', 'step' => '']
					]
				]],
				['md00-99']
			],
			[
				'md99-99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md99-99',
					'md' => [
						['from' => '99', 'till' => '99', 'step' => '']
					]
				]],
				['md99-99']
			],
			[
				'md99-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md99-00',
					'md' => [
						['from' => '99', 'till' => '00', 'step' => '']
					]
				]],
				['md99-00']
			],
			[
				'md99-99/88',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md99-99/88',
					'md' => [
						['from' => '99', 'till' => '99', 'step' => '88']
					]
				]],
				['md99-99/88']
			],
			[
				'md99,99-99/88',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md99,99-99/88',
					'md' => [
						['from' => '99', 'till' => '', 'step' => ''],
						['from' => '99', 'till' => '99', 'step' => '88']
					]
				]],
				['md99,99-99/88']
			],
			[
				'md0,0-0/0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md0,0-0/0',
					'md' => [
						['from' => '0', 'till' => '', 'step' => ''],
						['from' => '0', 'till' => '0', 'step' => '0'],
					]
				]],
				['md0,0-0/0']
			],
			[
				'md/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/99',
					'md' => [
						['from' => '', 'till' => '', 'step' => '99']
					]
				]],
				['md/99']
			],
			[
				'md/0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/0',
					'md' => [
						['from' => '', 'till' => '', 'step' => '0']
					]
				]],
				['md/0']
			],
			// Scheduling weeks. Syntax is valid and all values are valid.
			[
				'wd/6',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '6']
					]
				]],
				['wd/6']
			],
			[
				'wd1-7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '']
					]
				]],
				['wd1-7']
			],
			[
				'wd1-7/6',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '6']
					]
				]],
				['wd1-7/6']
			],
			[
				'wd1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-1',
					'wd' => [
						['from' => '1', 'till' => '1', 'step' => '']
					]
				]],
				['wd1-1']
			],
			[
				'wd7-7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-7',
					'wd' => [
						['from' => '7', 'till' => '7', 'step' => '']
					]
				]],
				['wd7-7']
			],
			[
				'wd1-5,6-7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-5,6-7',
					'wd' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '6', 'till' => '7', 'step' => '']
					]
				]],
				['wd1-5,6-7']
			],
			[
				'wd/6,1-5/4,2-7/5',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6,1-5/4,2-7/5',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '6'],
						['from' => '1', 'till' => '5', 'step' => '4'],
						['from' => '2', 'till' => '7', 'step' => '5']
					]
				]],
				['wd/6,1-5/4,2-7/5']
			],
			[
				'wd1-5/4,3-7/4,/6',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-5/4,3-7/4,/6',
					'wd' => [
						['from' => '1', 'till' => '5', 'step' => '4'],
						['from' => '3', 'till' => '7', 'step' => '4'],
						['from' => '', 'till' => '', 'step' => '6']
					]
				]],
				['wd1-5/4,3-7/4,/6']
			],
			[
				'wd1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1',
					'wd' => [
						['from' => '1', 'till' => '', 'step' => '']
					]
				]],
				['wd1']
			],
			[
				'wd7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7',
					'wd' => [
						['from' => '7', 'till' => '', 'step' => '']
					]
				]],
				['wd7']
			],
			[
				'wd1,7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,7',
					'wd' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '7', 'till' => '', 'step' => '']
					]
				]],
				['wd1,7']
			],
			[
				'wd1,7,6,2',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,7,6,2',
					'wd' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '7', 'till' => '', 'step' => ''],
						['from' => '6', 'till' => '', 'step' => ''],
						['from' => '2', 'till' => '', 'step' => '']
					]
				]],
				['wd1,7,6,2']
			],
			[
				'wd1;wd7;wd1,4,5-7;wd1,7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1',
					'wd' => [
						['from' => '1', 'till' => '', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7',
					'wd' => [
						['from' => '7', 'till' => '', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,4,5-7',
					'wd' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '4', 'till' => '', 'step' => ''],
						['from' => '5', 'till' => '7', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,7',
					'wd' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '7', 'till' => '', 'step' => '']
					]
				]],
				['wd1', 'wd7', 'wd1,4,5-7', 'wd1,7']
			],
			[
				'wd1-3/2',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-3/2',
					'wd' => [
						['from' => '1', 'till' => '3', 'step' => '2']
					]
				]],
				['wd1-3/2']
			],
			[
				'wd/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/1',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '1']
					]
				]],
				['wd/1']
			],
			[
				'wd/6;wd/4',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '6']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/4',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '4']
					]
				]],
				['wd/6', 'wd/4']
			],
			[
				'wd1-7/6;wd/6;wd/2;wd1-2/1,3-5/2',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '6']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '6']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/2',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '2']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-2/1,3-5/2',
					'wd' => [
						['from' => '1', 'till' => '2', 'step' => '1'],
						['from' => '3', 'till' => '5', 'step' => '2']
					]
				]],
				['wd1-7/6', 'wd/6', 'wd/2', 'wd1-2/1,3-5/2']
			],
			[
				'wd1-7,1-1;wd1-7;wd7-9;wd1,3,5-7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7,1-1',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => ''],
						['from' => '1', 'till' => '1', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-9',
					'wd' => [
						['from' => '7', 'till' => '9', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,3,5-7',
					'wd' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '3', 'till' => '', 'step' => ''],
						['from' => '5', 'till' => '7', 'step' => '']
					]
				]],
				['wd1-7,1-1', 'wd1-7', 'wd7-9', 'wd1,3,5-7']
			],
			[
				'wd1-7/6;wd/6',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '6']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '6']
					]
				]],
				['wd1-7/6', 'wd/6']
			],
			[
				'wd1-7/6',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '6']
					]
				]],
				['wd1-7/6']
			],
			// Scheduling weeks. Syntax is valid, but week days are not valid.
			[
				'wd0-0/0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd0-0/0',
					'wd' => [
						['from' => '0', 'till' => '0', 'step' => '0']
					]
				]],
				['wd0-0/0']
			],
			[
				'wd1-1/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-1/1',
					'wd' => [
						['from' => '1', 'till' => '1', 'step' => '1']
					]
				]],
				['wd1-1/1']
			],
			[
				'wd9-9/9',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd9-9/9',
					'wd' => [
						['from' => '9', 'till' => '9', 'step' => '9']
					]
				]],
				['wd9-9/9']
			],
			[
				'wd9-9',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd9-9',
					'wd' => [
						['from' => '9', 'till' => '9', 'step' => '']
					]
				]],
				['wd9-9']
			],
			[
				'wd/9',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/9',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '9']
					]
				]],
				['wd/9']
			],
			[
				'wd/0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/0',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '0']
					]
				]],
				['wd/0']
			],
			[
				'wd9-7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd9-7',
					'wd' => [
						['from' => '9', 'till' => '7', 'step' => '']
					]
				]],
				['wd9-7']
			],
			[
				'wd7-7/7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-7/7',
					'wd' => [
						['from' => '7', 'till' => '7', 'step' => '7']
					]
				]],
				['wd7-7/7']
			],
			[
				'wd/7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/7',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '7']
					]
				]],
				['wd/7']
			],
			[
				'wd6-1/0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd6-1/0',
					'wd' => [
						['from' => '6', 'till' => '1', 'step' => '0']
					]
				]],
				['wd6-1/0']
			],
			// Mixed scheduling months and weeks. All values are valid.
			[
				'wd1-7/6;md1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '6']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1',
					'md' => [
						['from' => '1', 'till' => '', 'step' => '']
					]
				]],
				['wd1-7/6', 'md1']
			],
			[
				'wd1-7/6;md1-31',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '6']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31',
					'md' => [
						['from' => '1', 'till' => '31', 'step' => '']
					]
				]],
				['wd1-7/6', 'md1-31']
			],
			[
				'wd1-7/6;md1-31,1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '6']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31,1-1',
					'md' => [
						['from' => '1', 'till' => '31', 'step' => ''],
						['from' => '1', 'till' => '1', 'step' => '']
					]
				]],
				['wd1-7/6', 'md1-31,1-1']
			],
			[
				'wd1-7/6;md/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '6']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/1',
					'md' => [
						['from' => '', 'till' => '', 'step' => '1']
					]
				]],
				['wd1-7/6', 'md/1']
			],
			[
				'md1-31wd1-7;wd/6;md05-10wd5',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31wd1-7',
					'md' => [
						['from' => '1', 'till' => '31', 'step' => '']
					],
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '6']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md05-10wd5',
					'md' => [
						['from' => '05', 'till' => '10', 'step' => '']
					],
					'wd' => [
						['from' => '5', 'till' => '', 'step' => '']
					]
				]],
				['md1-31wd1-7', 'wd/6', 'md05-10wd5']
			],
			// Scheduling hours. Syntax ir correct and all values are correct.
			[
				'h/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/1',
					'h' => [
						['from' => '', 'till' => '', 'step' => '1']
					]
				]],
				['h/1']
			],
			[
				'h/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/01',
					'h' => [
						['from' => '', 'till' => '', 'step' => '01']
					]
				]],
				['h/01']
			],
			[
				'h/02',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/02',
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				]],
				['h/02']
			],
			[
				'h/1;h23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/1',
					'h' => [
						['from' => '', 'till' => '', 'step' => '1']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h23',
					'h' => [
						['from' => '23', 'till' => '', 'step' => '']
					]
				]],
				['h/1', 'h23']
			],
			[
				'h1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-1',
					'h' => [
						['from' => '1', 'till' => '1', 'step' => '']
					]
				]],
				['h1-1']
			],
			[
				'h1-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-23',
					'h' => [
						['from' => '1', 'till' => '23', 'step' => '']
					]
				]],
				['h1-23']
			],
			[
				'h01-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h01-1',
					'h' => [
						['from' => '01', 'till' => '1', 'step' => '']
					]
				]],
				['h01-1']
			],
			[
				'h01-01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h01-01',
					'h' => [
						['from' => '01', 'till' => '01', 'step' => '']
					]
				]],
				['h01-01']
			],
			[
				'h01-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h01-23',
					'h' => [
						['from' => '01', 'till' => '23', 'step' => '']
					]
				]],
				['h01-23']
			],
			[
				'h0-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h0-23',
					'h' => [
						['from' => '0', 'till' => '23', 'step' => '']
					]
				]],
				['h0-23']
			],
			[
				'h00-23/23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h00-23/23',
					'h' => [
						['from' => '00', 'till' => '23', 'step' => '23']
					]
				]],
				['h00-23/23']
			],
			[
				'h01-23/22',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h01-23/22',
					'h' => [
						['from' => '01', 'till' => '23', 'step' => '22']
					]
				]],
				['h01-23/22']
			],
			[
				'h1-5,7-10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '']
					]
				]],
				['h1-5,7-10']
			],
			[
				'h1-5,7-10/3',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/3',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '3']
					]
				]],
				['h1-5,7-10/3']
			],
			[
				'h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '03']
					]
				]],
				['h1-5,7-10/03']
			],
			[
				'h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/02',
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '03']
					]
				]],
				['h/02', 'h1-5,7-10/03']
			],
			[
				'h0-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h0-0',
					'h' => [
						['from' => '0', 'till' => '0', 'step' => '']
					]
				]],
				['h0-0']
			],
			[
				'h0-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h0-00',
					'h' => [
						['from' => '0', 'till' => '00', 'step' => '']
					]
				]],
				['h0-00']
			],
			[
				'h00-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h00-0',
					'h' => [
						['from' => '00', 'till' => '0', 'step' => '']
					]
				]],
				['h00-0']
			],
			[
				'h00-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h00-00',
					'h' => [
						['from' => '00', 'till' => '00', 'step' => '']
					]
				]],
				['h00-00']
			],
			// Scheduling hours. Syntax is correct, but values are not.
			[
				'h99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h99',
					'h' => [
						['from' => '99', 'till' => '', 'step' => '']
					]
				]],
				['h99']
			],
			[
				'h99-99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h99-99',
					'h' => [
						['from' => '99', 'till' => '99', 'step' => '']
					]
				]],
				['h99-99']
			],
			[
				'h/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/99',
					'h' => [
						['from' => '', 'till' => '', 'step' => '99']
					]
				]],
				['h/99']
			],
			[
				'h99-99/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h99-99/99',
					'h' => [
						['from' => '99', 'till' => '99', 'step' => '99']
					]
				]],
				['h99-99/99']
			],
			[
				'h7-0/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h7-0/99',
					'h' => [
						['from' => '7', 'till' => '0', 'step' => '99']
					]
				]],
				['h7-0/99']
			],
			[
				'h00-24/23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h00-24/23',
					'h' => [
						['from' => '00', 'till' => '24', 'step' => '23']
					]
				]],
				['h00-24/23']
			],
			[
				'h/24',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/24',
					'h' => [
						['from' => '', 'till' => '', 'step' => '24']
					]
				]],
				['h/24']
			],
			[
				'h23-15/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h23-15/1',
					'h' => [
						['from' => '23', 'till' => '15', 'step' => '1']
					]
				]],
				['h23-15/1']
			],
			[
				'h00-00/00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h00-00/00',
					'h' => [
						['from' => '00', 'till' => '00', 'step' => '00']
					]
				]],
				['h00-00/00']
			],
			[
				'h01-01/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h01-01/01',
					'h' => [
						['from' => '01', 'till' => '01', 'step' => '01']
					]
				]],
				['h01-01/01']
			],
			// Mixed scheduling weeks and hours. All values are correct.
			[
				'wd/6,1-5/4,2-7/5h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6,1-5/4,2-7/5h/02',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '6'],
						['from' => '1', 'till' => '5', 'step' => '4'],
						['from' => '2', 'till' => '7', 'step' => '5']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '03']
					]
				]],
				['wd/6,1-5/4,2-7/5h/02', 'h1-5,7-10/03']
			],
			[
				'wd1-5/4,3-7/4,/6h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-5/4,3-7/4,/6h/02',
					'wd' => [
						['from' => '1', 'till' => '5', 'step' => '4'],
						['from' => '3', 'till' => '7', 'step' => '4'],
						['from' => '', 'till' => '', 'step' => '6']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '03']
					]
				]],
				['wd1-5/4,3-7/4,/6h/02', 'h1-5,7-10/03']
			],
			[
				'wd1-7/6;wd/6;wd/2h/02;wd1-2/1,1-2/1h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '6']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '6']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/2h/02',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '2']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-2/1,1-2/1h/02',
					'wd' => [
						['from' => '1', 'till' => '2', 'step' => '1'],
						['from' => '1', 'till' => '2', 'step' => '1']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '03']
					]
				]],
				['wd1-7/6', 'wd/6', 'wd/2h/02', 'wd1-2/1,1-2/1h/02', 'h1-5,7-10/03']
			],
			// Mixed scheduling months and hours. All values are correct.
			[
				'md01;md1h/02;md31;md1,10h/02;h1-5,7-10/03;md01,10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01',
					'md' => [
						['from' => '01', 'till' => '', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1h/02',
					'md' => [
						['from' => '1', 'till' => '', 'step' => '']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md31',
					'md' => [
						['from' => '31', 'till' => '', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1,10h/02',
					'md' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '10', 'till' => '', 'step' => '']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '03']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01,10',
					'md' => [
						['from' => '01', 'till' => '', 'step' => ''],
						['from' => '10', 'till' => '', 'step' => '']
					]
				]],
				['md01', 'md1h/02', 'md31', 'md1,10h/02', 'h1-5,7-10/03', 'md01,10']
			],
			// Mixed week days and hours. Syntax is correct, but values are not.
			[
				'wd1-7,1-1;wd1-7;wd1-7h/02;wd7-9;wd1,3,5-7h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7,1-1',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => ''],
						['from' => '1', 'till' => '1', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7h/02',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-9',
					'wd' => [
						['from' => '7', 'till' => '9', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,3,5-7h/02',
					'wd' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '3', 'till' => '', 'step' => ''],
						['from' => '5', 'till' => '7', 'step' => '']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '03']
					]
				]],
				['wd1-7,1-1', 'wd1-7', 'wd1-7h/02', 'wd7-9', 'wd1,3,5-7h/02', 'h1-5,7-10/03']
			],
			// Mixed scheduling months, weeks and hours. Syntax is correct, but values are not.
			[
				'md01;md1h/02;wd1-7,1-1;wd1-7;wd1-7h/02;wd7-9;wd1,3,5-7h/02;md31;md1,10h/02;md01,10wd1-7,1-1;wd1-7;wd1-7h/02;wd7-9;wd1,3,5-7h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01',
					'md' => [
						['from' => '01', 'till' => '', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1h/02',
					'md' => [
						['from' => '1', 'till' => '', 'step' => '']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7,1-1',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => ''],
						['from' => '1', 'till' => '1', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7h/02',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-9',
					'wd' => [
						['from' => '7', 'till' => '9', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,3,5-7h/02',
					'wd' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '3', 'till' => '', 'step' => ''],
						['from' => '5', 'till' => '7', 'step' => '']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md31',
					'md' => [
						['from' => '31', 'till' => '', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1,10h/02',
					'md' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '10', 'till' => '', 'step' => '']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01,10wd1-7,1-1',
					'md' => [
						['from' => '01', 'till' => '', 'step' => ''],
						['from' => '10', 'till' => '', 'step' => '']
					],
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => ''],
						['from' => '1', 'till' => '1', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7h/02',
					'wd' => [
						['from' => '1', 'till' => '7', 'step' => '']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-9',
					'wd' => [
						['from' => '7', 'till' => '9', 'step' => '']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,3,5-7h/02',
					'wd' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '3', 'till' => '', 'step' => ''],
						['from' => '5', 'till' => '7', 'step' => '']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '03']
					]
				]],
				['md01', 'md1h/02', 'wd1-7,1-1', 'wd1-7', 'wd1-7h/02', 'wd7-9', 'wd1,3,5-7h/02', 'md31', 'md1,10h/02',
					'md01,10wd1-7,1-1', 'wd1-7', 'wd1-7h/02', 'wd7-9', 'wd1,3,5-7h/02', 'h1-5,7-10/03'
				]
			],
			// Scheduling minutes. All values are correct.
			[
				'm/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/1',
					'm' => [
						['from' => '', 'till' => '', 'step' => '1']
					]
				]],
				['m/1']
			],
			[
				'm/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/01',
					'm' => [
						['from' => '', 'till' => '', 'step' => '01']
					]
				]],
				['m/01']
			],
			[
				'm/02',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/02',
					'm' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				]],
				['m/02']
			],
			[
				'm/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/59',
					'm' => [
						['from' => '', 'till' => '', 'step' => '59']
					]
				]],
				['m/59']
			],
			[
				'm/1;m59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/1',
					'm' => [
						['from' => '', 'till' => '', 'step' => '1']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm59',
					'm' => [
						['from' => '59', 'till' => '', 'step' => '']
					]
				]],
				['m/1', 'm59']
			],
			[
				'm1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-1',
					'm' => [
						['from' => '1', 'till' => '1', 'step' => '']
					]
				]],
				['m1-1']
			],
			[
				'm1-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-23',
					'm' => [
						['from' => '1', 'till' => '23', 'step' => '']
					]
				]],
				['m1-23']
			],
			[
				'm01-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm01-1',
					'm' => [
						['from' => '01', 'till' => '1', 'step' => '']
					]
				]],
				['m01-1']
			],
			[
				'm01-01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm01-01',
					'm' => [
						['from' => '01', 'till' => '01', 'step' => '']
					]
				]],
				['m01-01']
			],
			[
				'm01-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm01-23',
					'm' => [
						['from' => '01', 'till' => '23', 'step' => '']
					]
				]],
				['m01-23']
			],
			[
				'm0-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm0-23',
					'm' => [
						['from' => '0', 'till' => '23', 'step' => '']
					]
				]],
				['m0-23']
			],
			[
				'm00-59/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm00-59/59',
					'm' => [
						['from' => '00', 'till' => '59', 'step' => '59']
					]
				]],
				['m00-59/59']
			],
			[
				'm01-59/58',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm01-59/58',
					'm' => [
						['from' => '01', 'till' => '59', 'step' => '58']
					]
				]],
				['m01-59/58']
			],
			[
				'm1-5,33-59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,33-59',
					'm' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '33', 'till' => '59', 'step' => '']
					]
				]],
				['m1-5,33-59']
			],
			[
				'm1-5,28-45/17',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,28-45/17',
					'm' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '28', 'till' => '45', 'step' => '17']
					]
				]],
				['m1-5,28-45/17']
			],
			[
				'm1-5,44-45/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,44-45/01',
					'm' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '44', 'till' => '45', 'step' => '01']
					]
				]],
				['m1-5,44-45/01']
			],
			[
				'm1-5,44-45/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,44-45/1',
					'm' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '44', 'till' => '45', 'step' => '1']
					]
				]],
				['m1-5,44-45/1']
			],
			[
				'm/02;m1-5,44-45/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/02',
					'm' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,44-45/1',
					'm' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '44', 'till' => '45', 'step' => '1']
					]
				]],
				['m/02', 'm1-5,44-45/1']
			],
			[
				'm0-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm0-0',
					'm' => [
						['from' => '0', 'till' => '0', 'step' => '']
					]
				]],
				['m0-0']
			],
			[
				'm0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm0',
					'm' => [
						['from' => '0', 'till' => '', 'step' => '']
					]
				]],
				['m0']
			],
			[
				'm0-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm0-00',
					'm' => [
						['from' => '0', 'till' => '00', 'step' => '']
					]
				]],
				['m0-00']
			],
			[
				'm00-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm00-0',
					'm' => [
						['from' => '00', 'till' => '0', 'step' => '']
					]
				]],
				['m00-0']
			],
			[
				'm00-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm00-00',
					'm' => [
						['from' => '00', 'till' => '00', 'step' => '']
					]
				]],
				['m00-00']
			],
			[
				'm1-1,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-1,58-59/1,/1,/59',
					'm' => [
						['from' => '1', 'till' => '1', 'step' => ''],
						['from' => '58', 'till' => '59', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '59']
					]
				]],
				['m1-1,58-59/1,/1,/59']
			],
			[
				'm/30,1-4,05-09,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/30,1-4,05-09,58-59/1,/1,/59',
					'm' => [
						['from' => '', 'till' => '', 'step' => '30'],
						['from' => '1', 'till' => '4', 'step' => ''],
						['from' => '05', 'till' => '09', 'step' => ''],
						['from' => '58', 'till' => '59', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '59']
					]
				]],
				['m/30,1-4,05-09,58-59/1,/1,/59']
			],
			// Scheduling minutes. Syntax is correct, but values are not.
			[
				'm99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm99',
					'm' => [
						['from' => '99', 'till' => '', 'step' => '']
					]
				]],
				['m99']
			],
			[
				'm99-99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm99-99',
					'm' => [
						['from' => '99', 'till' => '99', 'step' => '']
					]
				]],
				['m99-99']
			],
			[
				'm99-99/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm99-99/99',
					'm' => [
						['from' => '99', 'till' => '99', 'step' => '99']
					]
				]],
				['m99-99/99']
			],
			[
				'm/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/99',
					'm' => [
						['from' => '', 'till' => '', 'step' => '99']
					]
				]],
				['m/99']
			],
			[
				'm60-0/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm60-0/99',
					'm' => [
						['from' => '60', 'till' => '0', 'step' => '99']
					]
				]],
				['m60-0/99']
			],
			[
				'm59-0/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm59-0/99',
					'm' => [
						['from' => '59', 'till' => '0', 'step' => '99']
					]
				]],
				['m59-0/99']
			],
			[
				'm00-60',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm00-60',
					'm' => [
						['from' => '00', 'till' => '60', 'step' => '']
					]
				]],
				['m00-60']
			],
			[
				'm/60',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/60',
					'm' => [
						['from' => '', 'till' => '', 'step' => '60']
					]
				]],
				['m/60']
			],
			[
				'm0-2/60',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm0-2/60',
					'm' => [
						['from' => '0', 'till' => '2', 'step' => '60']
					]
				]],
				['m0-2/60']
			],
			[
				'm59-15/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm59-15/1',
					'm' => [
						['from' => '59', 'till' => '15', 'step' => '1']
					]
				]],
				['m59-15/1']
			],
			[
				'm00-00/00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm00-00/00',
					'm' => [
						['from' => '00', 'till' => '00', 'step' => '00']
					]
				]],
				['m00-00/00']
			],
			[
				'm01-01/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm01-01/01',
					'm' => [
						['from' => '01', 'till' => '01', 'step' => '01']
					]
				]],
				['m01-01/01']
			],
			// Mixed scheduling minutes, hours and weeks. All values are correct.
			[
				'wd/6,1-5/4,2-7/5h/02;h1-5,7-10/03m1-1,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6,1-5/4,2-7/5h/02',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '6'],
						['from' => '1', 'till' => '5', 'step' => '4'],
						['from' => '2', 'till' => '7', 'step' => '5']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03m1-1,58-59/1,/1,/59',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '03']
					],
					'm' => [
						['from' => '1', 'till' => '1', 'step' => ''],
						['from' => '58', 'till' => '59', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '59']
					]
				]],
				['wd/6,1-5/4,2-7/5h/02', 'h1-5,7-10/03m1-1,58-59/1,/1,/59']
			],
			// Scheduling seconds. All values are correct.
			[
				's/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/1',
					's' => [
						['from' => '', 'till' => '', 'step' => '1']
					]
				]],
				['s/1']
			],
			[
				's/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/01',
					's' => [
						['from' => '', 'till' => '', 'step' => '01']
					]
				]],
				['s/01']
			],
			[
				's/02',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/02',
					's' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				]],
				['s/02']
			],
			[
				's/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/59',
					's' => [
						['from' => '', 'till' => '', 'step' => '59']
					]
				]],
				['s/59']
			],
			[
				's/1;m59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/1',
					's' => [
						['from' => '', 'till' => '', 'step' => '1']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm59',
					'm' => [
						['from' => '59', 'till' => '', 'step' => '']
					]
				]],
				['s/1', 'm59']
			],
			[
				's1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-1',
					's' => [
						['from' => '1', 'till' => '1', 'step' => '']
					]
				]],
				['s1-1']
			],
			[
				's1-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-23',
					's' => [
						['from' => '1', 'till' => '23', 'step' => '']
					]
				]],
				['s1-23']
			],
			[
				's01-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's01-1',
					's' => [
						['from' => '01', 'till' => '1', 'step' => '']
					]
				]],
				['s01-1']
			],
			[
				's01-01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's01-01',
					's' => [
						['from' => '01', 'till' => '01', 'step' => '']
					]
				]],
				['s01-01']
			],
			[
				's01-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's01-23',
					's' => [
						['from' => '01', 'till' => '23', 'step' => '']
					]
				]],
				['s01-23']
			],
			[
				's0-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's0-23',
					's' => [
						['from' => '0', 'till' => '23', 'step' => '']
					]
				]],
				['s0-23']
			],
			[
				's00-59/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's00-59/59',
					's' => [
						['from' => '00', 'till' => '59', 'step' => '59']
					]
				]],
				['s00-59/59']
			],
			[
				's01-59/58',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's01-59/58',
					's' => [
						['from' => '01', 'till' => '59', 'step' => '58']
					]
				]],
				['s01-59/58']
			],
			[
				's1-5,33-59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-5,33-59',
					's' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '33', 'till' => '59', 'step' => '']
					]
				]],
				['s1-5,33-59']
			],
			[
				's1-5,28-45/17',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-5,28-45/17',
					's' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '28', 'till' => '45', 'step' => '17']
					]
				]],
				['s1-5,28-45/17']
			],
			[
				's1-5,44-45/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-5,44-45/01',
					's' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '44', 'till' => '45', 'step' => '01']
					]
				]],
				['s1-5,44-45/01']
			],
			[
				's1-5,44-45/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-5,44-45/1',
					's' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '44', 'till' => '45', 'step' => '1']
					]
				]],
				['s1-5,44-45/1']
			],
			[
				's/02;m1-5,44-45/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/02',
					's' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,44-45/1',
					'm' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '44', 'till' => '45', 'step' => '1']
					]
				]],
				['s/02', 'm1-5,44-45/1']
			],
			[
				's0-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's0-0',
					's' => [
						['from' => '0', 'till' => '0', 'step' => '']
					]
				]],
				['s0-0']
			],
			[
				's0-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's0-00',
					's' => [
						['from' => '0', 'till' => '00', 'step' => '']
					]
				]],
				['s0-00']
			],
			[
				's00-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's00-0',
					's' => [
						['from' => '00', 'till' => '0', 'step' => '']
					]
				]],
				['s00-0']
			],
			[
				's00-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's00-00',
					's' => [
						['from' => '00', 'till' => '00', 'step' => '']
					]
				]],
				['s00-00']
			],
			[
				's1-1,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-1,58-59/1,/1,/59',
					's' => [
						['from' => '1', 'till' => '1', 'step' => ''],
						['from' => '58', 'till' => '59', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '59']
					]
				]],
				['s1-1,58-59/1,/1,/59']
			],
			[
				's/30,1-4,05-09,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/30,1-4,05-09,58-59/1,/1,/59',
					's' => [
						['from' => '', 'till' => '', 'step' => '30'],
						['from' => '1', 'till' => '4', 'step' => ''],
						['from' => '05', 'till' => '09', 'step' => ''],
						['from' => '58', 'till' => '59', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '59']
					]
				]],
				['s/30,1-4,05-09,58-59/1,/1,/59']
			],
			// Scheduling seconds. Syntax is correct, but values are not.
			[
				's99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's99',
					's' => [
						['from' => '99', 'till' => '', 'step' => '']
					]
				]],
				['s99']
			],
			[
				's99-99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's99-99',
					's' => [
						['from' => '99', 'till' => '99', 'step' => '']
					]
				]],
				['s99-99']
			],
			[
				's99-99/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's99-99/99',
					's' => [
						['from' => '99', 'till' => '99', 'step' => '99']
					]
				]],
				['s99-99/99']
			],
			[
				's/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/99',
					's' => [
						['from' => '', 'till' => '', 'step' => '99']
					]
				]],
				['s/99']
			],
			[
				's60-0/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's60-0/99',
					's' => [
						['from' => '60', 'till' => '0', 'step' => '99']
					]
				]],
				['s60-0/99']
			],
			[
				's59-0/99',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's59-0/99',
					's' => [
						['from' => '59', 'till' => '0', 'step' => '99']
					]
				]],
				['s59-0/99']
			],
			[
				's00-60',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's00-60',
					's' => [
						['from' => '00', 'till' => '60', 'step' => '']
					]
				]],
				['s00-60']
			],
			[
				's/60',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/60',
					's' => [
						['from' => '', 'till' => '', 'step' => '60']
					]
				]],
				['s/60']
			],
			[
				's0-2/60',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's0-2/60',
					's' => [
						['from' => '0', 'till' => '2', 'step' => '60']
					]
				]],
				['s0-2/60']
			],
			[
				's59-15/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's59-15/1',
					's' => [
						['from' => '59', 'till' => '15', 'step' => '1']
					]
				]],
				['s59-15/1']
			],
			[
				's00-00/00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's00-00/00',
					's' => [
						['from' => '00', 'till' => '00', 'step' => '00']
					]
				]],
				['s00-00/00']
			],
			[
				's01-01/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's01-01/01',
					's' => [
						['from' => '01', 'till' => '01', 'step' => '01']
					]
				]],
				['s01-01/01']
			],
			// Mixed scheduling seconds, weeks, minutes and hours. All values are correct.
			[
				'wd/6,1-5/4,2-7/5h/02;h1-5,7-10/03m1-1,58-59/1,/1,/59s/30,1-4,05-09,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6,1-5/4,2-7/5h/02',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '6'],
						['from' => '1', 'till' => '5', 'step' => '4'],
						['from' => '2', 'till' => '7', 'step' => '5']
					],
					'h' => [
						['from' => '', 'till' => '', 'step' => '02']
					]
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03m1-1,58-59/1,/1,/59s/30,1-4,05-09,58-59/1,/1,/59',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '03']
					],
					'm' => [
						['from' => '1', 'till' => '1', 'step' => ''],
						['from' => '58', 'till' => '59', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '59']
					],
					's' => [
						['from' => '', 'till' => '', 'step' => '30'],
						['from' => '1', 'till' => '4', 'step' => ''],
						['from' => '05', 'till' => '09', 'step' => ''],
						['from' => '58', 'till' => '59', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '1'],
						['from' => '', 'till' => '', 'step' => '59']
					]
				]],
				['wd/6,1-5/4,2-7/5h/02', 'h1-5,7-10/03m1-1,58-59/1,/1,/59s/30,1-4,05-09,58-59/1,/1,/59']
			]
		];
	}

	/**
	 * An array of invalid scheduling intervals and error messages.
	 */
	public function testInvalidSchedulingProvider() {
		return [
			// Invalid scheduling months.
			['md999999', 'incorrect syntax near "9999"'],
			['md000000', 'incorrect syntax near "0000"'],
			['md001', 'incorrect syntax near "1"'],
			['md1,2/1', 'incorrect syntax near "/1"'],
			['md;', 'incorrect syntax near ";"'],
			[';md', 'incorrect syntax near ";md"'],
			['md\/', 'incorrect syntax near "\/"'],
			['mdm', 'incorrect syntax near "m"'],
			['mdw', 'incorrect syntax near "w"'],
			['mds', 'incorrect syntax near "s"'],
			['md,', 'incorrect syntax near ","'],
			['mdmd', 'incorrect syntax near "md"'],
			['md/', 'unexpected end of interval'],
			['md/a', 'incorrect syntax near "a"'],
			['md/ 1', 'incorrect syntax near " 1"'],
			['md/ 30', 'incorrect syntax near " 30"'],
			['md /30', 'incorrect syntax near " /30"'],
			['md0/0', 'incorrect syntax near "/0"'],
			['md1-31/0000', 'incorrect syntax near "00"'],
			['md1-31/001', 'incorrect syntax near "1"'],
			['md1/1', 'incorrect syntax near "/1"'],
			['md0-1000/5', 'incorrect syntax near "00/5"'],
			['md0000-1000/5', 'incorrect syntax near "00-1000/5"'],
			['md0001-1000/5', 'incorrect syntax near "01-1000/5"'],
			['md01-0031/1', 'incorrect syntax near "31/1"'],
			['md01--31/1', 'incorrect syntax near "-31/1"'],
			['md1--31/1', 'incorrect syntax near "-31/1"'],
			['md01- 31/1', 'incorrect syntax near " 31/1"'],
			['md01 -31/1', 'incorrect syntax near " -31/1"'],
			['md01-31//1', 'incorrect syntax near "/1"'],
			['md1-31//1', 'incorrect syntax near "/1"'],
			['md/30;', 'unexpected end of interval'],
			['md/3;', 'unexpected end of interval'],
			['md/003;', 'incorrect syntax near "3;"'],
			['md/003', 'incorrect syntax near "3"'],
			['md/000', 'incorrect syntax near "0"'],
			['md/00-31', 'incorrect syntax near "-31"'],
			['md/000-31', 'incorrect syntax near "0-31"'],
			['md/1-31/31', 'incorrect syntax near "-31/31"'],
			['md28-31/', 'unexpected end of interval'],
			['md28-31/;', 'incorrect syntax near ";"'],
			['md1-5/4,', 'unexpected end of interval'],
			['md1-5/4,/', 'unexpected end of interval'],
			['md/', 'unexpected end of interval'],
			['md', 'unexpected end of interval'],
			// Invalid scheduling weeks.
			['wd01-7', 'incorrect syntax near "1-7"'],
			['wd001-7', 'incorrect syntax near "01-7"'],
			['wd1-07', 'incorrect syntax near "7"'],
			['wd1-01', 'incorrect syntax near "1"'],
			['wd1-001', 'incorrect syntax near "01"'],
			['wd1-7/06', 'incorrect syntax near "6"'],
			['wd1-7/006', 'incorrect syntax near "06"'],
			['wd;', 'incorrect syntax near ";"'],
			[';wd', 'incorrect syntax near ";wd"'],
			['wd\/', 'incorrect syntax near "\/"'],
			['wdm', 'incorrect syntax near "m"'],
			['wdw', 'incorrect syntax near "w"'],
			['wds', 'incorrect syntax near "s"'],
			['wd,', 'incorrect syntax near ","'],
			['wdwd', 'incorrect syntax near "wd"'],
			['wd/', 'unexpected end of interval'],
			['wd/a', 'incorrect syntax near "a"'],
			['wd/ 1', 'incorrect syntax near " 1"'],
			['wd/10', 'incorrect syntax near "0"'],
			['wd/1000', 'incorrect syntax near "000"'],
			['wd/ 6', 'incorrect syntax near " 6"'],
			['wd /6', 'incorrect syntax near " /6"'],
			['wd1-7/0000', 'incorrect syntax near "000"'],
			['wd1-7/001', 'incorrect syntax near "01"'],
			['wd1/1', 'incorrect syntax near "/1"'],
			['wd0000-1000/5', 'incorrect syntax near "000-1000/5"'],
			['wd0001-1000/5', 'incorrect syntax near "001-1000/5"'],
			['wd01-007/1', 'incorrect syntax near "1-007/1"'],
			['wd01--7/1', 'incorrect syntax near "1--7/1"'],
			['wd1--7/1', 'incorrect syntax near "-7/1"'],
			['wd01- 7/1', 'incorrect syntax near "1- 7/1"'],
			['wd01 -7/1', 'incorrect syntax near "1 -7/1"'],
			['wd01-7//1', 'incorrect syntax near "1-7//1"'],
			['wd1-7//1', 'incorrect syntax near "/1"'],
			['wd/7;', 'unexpected end of interval'],
			['wd/007;', 'incorrect syntax near "07;"'],
			['wd/007', 'incorrect syntax near "07"'],
			['wd/00-07', 'incorrect syntax near "0-07"'],
			['wd/00-0007', 'incorrect syntax near "0-0007"'],
			['wd/000-7', 'incorrect syntax near "00-7"'],
			['wd/000-70', 'incorrect syntax near "00-70"'],
			['wd/1-7/7', 'incorrect syntax near "-7/7"'],
			['wd1-7/', 'unexpected end of interval'],
			['wd1-7/;', 'incorrect syntax near ";"'],
			['wd1-5/4,', 'unexpected end of interval'],
			['wd1-5/4,/', 'unexpected end of interval'],
			// Invalid scheduling week days due to month days appearing after week days.
			['wd1-7/6md', 'incorrect syntax near "md"'],
			['wd1-7/6m', 'incorrect syntax near "m"'],
			['wd1-7/6md1', 'incorrect syntax near "md1"'],
			['wd1-7/6md1-31', 'incorrect syntax near "md1-31"'],
			['wd1-7/6md1-31,1-1', 'incorrect syntax near "md1-31,1-1"'],
			['wd1-7/6md/1', 'incorrect syntax near "md/1"'],
			['wd/', 'unexpected end of interval'],
			['wd', 'unexpected end of interval'],
			// Invalid scheduling hours.
			['h000', 'incorrect syntax near "0"'],
			['h001', 'incorrect syntax near "1"'],
			['h100', 'incorrect syntax near "0"'],
			['h230', 'incorrect syntax near "0"'],
			['h023', 'incorrect syntax near "3"'],
			['h0-023', 'incorrect syntax near "3"'],
			['h0-000', 'incorrect syntax near "0"'],
			['h000-0', 'incorrect syntax near "0-0"'],
			['hd', 'incorrect syntax near "d"'],
			['h;', 'incorrect syntax near ";"'],
			['h/', 'unexpected end of interval'],
			[';h0-0', 'incorrect syntax near ";h0-0"'],
			[';h00-23', 'incorrect syntax near ";h00-23"'],
			['h00-23//23', 'incorrect syntax near "/23"'],
			['h00-23/ 23', 'incorrect syntax near " 23"'],
			['h00-23 /23', 'incorrect syntax near " /23"'],
			['h00--23/23', 'incorrect syntax near "-23/23"'],
			['h000-23/23', 'incorrect syntax near "0-23/23"'],
			['h00-230/23', 'incorrect syntax near "0/23"'],
			['h00-003/3', 'incorrect syntax near "3/3"'],
			['h00-23/023', 'incorrect syntax near "3"'],
			['h00-23/', 'unexpected end of interval'],
			['h00-23/;', 'incorrect syntax near ";"'],
			['h00 -23/01', 'incorrect syntax near " -23/01"'],
			['h00- 23/01', 'incorrect syntax near " 23/01"'],
			['h0023/01', 'incorrect syntax near "23/01"'],
			['h,', 'incorrect syntax near ","'],
			['h', 'unexpected end of interval'],
			['h-', 'incorrect syntax near "-"'],
			['h00-23;h', 'unexpected end of interval'],
			['h00-23;h;', 'incorrect syntax near ";"'],
			['h00-23;h,', 'incorrect syntax near ","'],
			['h00-23;h-', 'incorrect syntax near "-"'],
			['h1-', 'unexpected end of interval'],
			// mixed scheduling hours, months and weeks
			['h00-23md1-31', 'incorrect syntax near "md1-31"'],
			['h00-23wd1-7', 'incorrect syntax near "wd1-7"'],
			['md1-31h00-23wd1-7', 'incorrect syntax near "wd1-7"'],
			['h00-23;md', 'unexpected end of interval'],
			['h00-23;wd', 'unexpected end of interval'],
			// scheduling minutes
			['m000', 'incorrect syntax near "0"'],
			['m001', 'incorrect syntax near "1"'],
			['m100', 'incorrect syntax near "0"'],
			['m590', 'incorrect syntax near "0"'],
			['m059', 'incorrect syntax near "9"'],
			['m0-059', 'incorrect syntax near "9"'],
			['m0-000', 'incorrect syntax near "0"'],
			['m000-0', 'incorrect syntax near "0-0"'],
			['m', 'incorrect syntax near "m"'],
			['m;', 'incorrect syntax near ";"'],
			['m,', 'incorrect syntax near ","'],
			['m', 'incorrect syntax near "m"'],
			['m-', 'incorrect syntax near "-"'],
			['m1-', 'unexpected end of interval'],
			['ms', 'incorrect syntax near "s"'],
			['m/', 'unexpected end of interval'],
			[';m0-0', 'incorrect syntax near ";m0-0"'],
			[';m00-59', 'incorrect syntax near ";m00-59"'],
			['m00-59//59', 'incorrect syntax near "/59"'],
			['m00-59/ 59', 'incorrect syntax near " 59"'],
			['m00-59 /59', 'incorrect syntax near " /59"'],
			['m00--59/59', 'incorrect syntax near "-59/59"'],
			['m000-59/59', 'incorrect syntax near "0-59/59"'],
			['m00-590/59', 'incorrect syntax near "0/59"'],
			['m00-003/3', 'incorrect syntax near "3/3"'],
			['m00-59/059', 'incorrect syntax near "9"'],
			['m00-59/', 'unexpected end of interval'],
			['m00-59/;', 'incorrect syntax near ";"'],
			['m00 -59/01', 'incorrect syntax near " -59/01"'],
			['m00- 59/01', 'incorrect syntax near " 59/01"'],
			['m0059/01', 'incorrect syntax near "59/01"'],
			['m00-23;m', 'incorrect syntax near "m"'],
			['m00-23;m;', 'incorrect syntax near ";"'],
			['m00-23;m,', 'incorrect syntax near ","'],
			['m00-23;m-', 'incorrect syntax near "-"'],
			['m00-23;m', 'incorrect syntax near "m"'],
			// Mixed scheduling minutes, months, weeks and hours.
			['m00-59md1-31', 'incorrect syntax near "md1-31"'],
			['m00-59wd1-7', 'incorrect syntax near "wd1-7"'],
			['md1-31m00-59h00-23wd1-7', 'incorrect syntax near "h00-23wd1-7"'],
			// Invalid scheduling seconds.
			['s000', 'incorrect syntax near "0"'],
			['s001', 'incorrect syntax near "1"'],
			['s100', 'incorrect syntax near "0"'],
			['s590', 'incorrect syntax near "0"'],
			['s059', 'incorrect syntax near "9"'],
			['s0-059', 'incorrect syntax near "9"'],
			['s0-000', 'incorrect syntax near "0"'],
			['s000-0', 'incorrect syntax near "0-0"'],
			['s', 'unexpected end of interval'],
			['s;', 'incorrect syntax near ";"'],
			['s/', 'unexpected end of interval'],
			[';s0-0', 'incorrect syntax near ";s0-0"'],
			[';s00-59', 'incorrect syntax near ";s00-59"'],
			['s00-59//59', 'incorrect syntax near "/59"'],
			['s00-59/ 59', 'incorrect syntax near " 59"'],
			['s00-59 /59', 'incorrect syntax near " /59"'],
			['s00--59/59', 'incorrect syntax near "-59/59"'],
			['s000-59/59', 'incorrect syntax near "0-59/59"'],
			['s00-590/59', 'incorrect syntax near "0/59"'],
			['s00-003/3', 'incorrect syntax near "3/3"'],
			['s00-59/059', 'incorrect syntax near "9"'],
			['s00-59/', 'unexpected end of interval'],
			['s00-59/;', 'incorrect syntax near ";"'],
			['s00 -59/01', 'incorrect syntax near " -59/01"'],
			// Mixed scheduling seconds, weeks, minutes and hours.
			['s00- 59/01', 'incorrect syntax near " 59/01"'],
			['s0059/01', 'incorrect syntax near "59/01"'],
			['s,', 'incorrect syntax near ","'],
			['s', 'unexpected end of interval'],
			['s-', 'incorrect syntax near "-"'],
			['s00-23;s', 'unexpected end of interval'],
			['s00-23;s;', 'incorrect syntax near ";"'],
			['s00-23;s,', 'incorrect syntax near ","'],
			['s00-23;s-', 'incorrect syntax near "-"'],
			['s00-23;s', 'unexpected end of interval'],
			['s1-', 'unexpected end of interval'],
			['s00-59md1-31', 'incorrect syntax near "md1-31"'],
			['s00-59wd1-7', 'incorrect syntax near "wd1-7"'],
			['s00-59m00-59h00-23wd1-7', 'incorrect syntax near "m00-59h00-23wd1-7"']
		];
	}

	/**
	 * @dataProvider testValidFlexibleProvider
	 *
	 * @param string $source		source string to parse
	 * @param array $result_all		expected resulting array of all intervals
	 * @param array $result			expected resulting array of only flexible intervals
	 */
	public function testParseFlexibleValid($source, $result_all, $result) {
		$parser = new CItemDelayFlexParser($source);

		$this->assertTrue($parser->isValid());
		$this->assertEmpty($parser->getError());
		$this->assertEquals($result_all, $parser->getIntervals());
		$this->assertEquals($result, $parser->getFlexibleIntervals());
		$this->assertEmpty($this->getSchedulingIntervals($parser->getIntervals()));
	}

	/**
	 * @dataProvider testInvalidFlexibleProvider
	 *
	 * @param string $source	source string to parse
	 * @param string $error		expected error message
	 */
	public function testParseFlexibleInvalid($source, $error) {
		$parser = new CItemDelayFlexParser($source);

		$this->assertFalse($parser->isValid());
		$this->assertEquals($error, $parser->getError());
	}

	/**
	 * @dataProvider testValidSchedulingProvider
	 *
	 * @param string $source		source string to parse
	 * @param array $result_all		expected resulting array of all intervals
	 * @param array $result			expected resulting array of only scheduling intervals
	 */
	public function testParseSchedulingValid($source, $result_all, $result) {
		$parser = new CItemDelayFlexParser($source);

		$this->assertTrue($parser->isValid());
		$this->assertEmpty($parser->getError());
		$this->assertEquals($result_all, $parser->getIntervals());
		$this->assertEquals($result, $this->getSchedulingIntervals($parser->getIntervals()));
		$this->assertEmpty($parser->getFlexibleIntervals());
	}

	/**
	 * @dataProvider testInvalidSchedulingProvider
	 *
	 * @param string $source	source string to parse
	 * @param string $error		expected error message
	 */
	public function testParseSchedulingInvalid($source, $error) {
		$parser = new CItemDelayFlexParser($source);

		$this->assertFalse($parser->isValid());
		$this->assertEquals($error, $parser->getError());
	}

	/**
	 * Get array of scheduling intervals.
	 *
	 * @param array $intervals
	 *
	 * @return array
	 */
	protected function getSchedulingIntervals($intervals) {
		$scheduling_intervals = [];

		foreach ($intervals as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEX_TYPE_SCHEDULING) {
				$scheduling_intervals[] = $interval['interval'];
			}
		}

		return $scheduling_intervals;
	}

}
