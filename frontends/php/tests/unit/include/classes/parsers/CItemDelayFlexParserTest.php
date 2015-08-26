<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
			[
				'86400/7-7,23:59-24:00',
				[[
					'interval' => '86400/7-7,23:59-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'86400/7-7,23:59-24:00'
				]
			],
			[
				'00000000000/7-7,23:59-24:00',
				[[
					'interval' => '00000000000/7-7,23:59-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'00000000000/7-7,23:59-24:00'
				]
			],
			[
				'9999/7-7,23:59-24:00',
				[[
					'interval' => '9999/7-7,23:59-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'9999/7-7,23:59-24:00'
				]
			],
			[
				'50/7,0:00-0:01',
				[[
					'interval' => '50/7,0:00-0:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'50/7,0:00-0:01'
				]
			],
			[
				'50/7,00:00-0:01',
				[[
					'interval' => '50/7,00:00-0:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'50/7,00:00-0:01'
				]
			],
			[
				'50/7,00:00-00:01',
				[[
					'interval' => '50/7,00:00-00:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'50/7,00:00-00:01'
				]
			],
			[
				'50/7,0:00-00:01',
				[[
					'interval' => '50/7,0:00-00:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'50/7,0:00-00:01'
				]
			],
			[
				'50/1-7,0:00-00:01',
				[[
					'interval' => '50/1-7,0:00-00:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'50/1-7,0:00-00:01'
				]
			],
			[
				'50/1-7,0:01-0:02',
				[[
					'interval' => '50/1-7,0:01-0:02',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'50/1-7,0:01-0:02'
				]
			],
			[
				'50/7,0:01-0:02',
				[[
					'interval' => '50/7,0:01-0:02',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'50/7,0:01-0:02'
				]
			],
			[
				'50/7,0:01-0:02;50/4-7,00:01-0:59',
				[[
					'interval' => '50/7,0:01-0:02',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				],
				[
					'interval' => '50/4-7,00:01-0:59',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'50/7,0:01-0:02',
					'50/4-7,00:01-0:59'
				]
			],
			[
				'000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000/7,0:01-0:02',
				[[
					'interval' => '000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000/7,0:01-0:02',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
				]],
				[
					'000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000/7,0:01-0:02'
				]
			]
		];
	}

	/**
	 * An array of invalid flexible intervals and error messages.
	 */
	public function testInvalidFlexibleProvider() {
		return [
			['0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000/7,0:01-0:02', 'incorrect syntax near "/7,0:01-0:02"'],
			['86401/7,23:59-24:00', 'incorrect syntax near "1/7,23:59-24:00"'],
			['86400/7-7,23:59-23:59', 'unexpected end of interval'],
			['86400/8-9,23:59-24:00', 'incorrect syntax near "8-9,23:59-24:00"'],
			['9/8,23:59-24:00', 'incorrect syntax near "8,23:59-24:00"'],
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
			['5/7-9,0:00-24:00', 'incorrect syntax near ",0:00-24:00"'],
			['5/7-6,0:00-24:00', 'incorrect syntax near "6,0:00-24:00"'],
			['0/0,0:00-24:00', 'incorrect syntax near "0,0:00-24:00"'],
			['0/1-0,0:00-24:00', 'incorrect syntax near "0,0:00-24:00"'],
			['1/1-000,0:00-24:00', 'incorrect syntax near "000,0:00-24:00"'],
			['1/1-1 ,0:00-24:00', 'incorrect syntax near " ,0:00-24:00"'],
			['1/1 ,0:00-24:00', 'incorrect syntax near " ,0:00-24:00"'],
			['1/01,0:00-24:00', 'incorrect syntax near "01,0:00-24:00"'],
			['1/1-07,0:00-24:00', 'incorrect syntax near "07,0:00-24:00"'],
			['1/1-7,,0:00-24:00', 'incorrect syntax near ",0:00-24:00"'],
			['1 1-7,0:00-24:00', 'incorrect syntax near " 1-7,0:00-24:00"'],
			['1/00-7,0:00-24:00', 'incorrect syntax near "00-7,0:00-24:00"'],
			['1/1-7-7,0:00-24:00', 'incorrect syntax near "-7,0:00-24:00"'],
			['1/001-7,0:00-24:00', 'incorrect syntax near "001-7,0:00-24:00"'],
			['1/1--7,0:00-24:00', 'incorrect syntax near "-7,0:00-24:00"'],
			['5/7+6,0:00-24:00', 'incorrect syntax near "+6,0:00-24:00"'],
			['5/7/6,0:00-24:00', 'incorrect syntax near "/6,0:00-24:00"'],
			['5/6a,0:00-24:00', 'incorrect syntax near "a,0:00-24:00"'],
			['5/1-6a,0:00-24:00', 'incorrect syntax near "a,0:00-24:00"'],
			['5/1-60,0:00-24:00', 'incorrect syntax near "0,0:00-24:00"'],
			['5/1-6, 0:00-24:00', 'incorrect syntax near " 0:00-24:00"'],
			['5/1-6,,0:00-24:00', 'incorrect syntax near ",0:00-24:00"'],
			['5/1-6,:00-24:00', 'incorrect syntax near ":00-24:00"'],
			['0/2,99:00-24:00', 'incorrect syntax near "9:00-24:00"'],
			['0/2,000:00-24:00', 'incorrect syntax near ":00-24:00"'],
			['0/2,00:0-24:00', 'incorrect syntax near "-24:00"'],
			['0/2,0:0-24:00', 'incorrect syntax near "-24:00"'],
			['0/2,0::00-24:00', 'incorrect syntax near ":00-24:00"'],
			['0/2,00::00-24:00', 'incorrect syntax near ":00-24:00"'],
			['0/2,00:000-24:00', 'incorrect syntax near "-24:00"'],
			['0/2,00:0024:00', 'incorrect syntax near ":00"'],
			['0/2,00:00--24:00', 'incorrect syntax near "-24:00"'],
			['0/2,00:00 -24:00', 'incorrect syntax near " -24:00"'],
			['0/2,00:00- 24:00', 'incorrect syntax near " 24:00"'],
			['0/2,00-24:00', 'incorrect syntax near "-24:00"'],
			['0/2,23:59-2400', 'incorrect syntax near "00"'],
			['0/2,00:00-00:00', 'unexpected end of interval'],
			['0/2,00:01-00:00', 'unexpected end of interval'],
			['0/2,5:00-29:00', 'incorrect syntax near "9:00"'],
			['0/2,0:00-9:000', 'unexpected end of interval'],
			['0/2,0:00-9:2', 'unexpected end of interval'],
			['0/2,0:00-111:2', 'incorrect syntax near "1:2"'],
			['0/2,1:00-9::20', 'incorrect syntax near ":20"'],
			['0/2,1:00-09::20', 'incorrect syntax near ":20"'],
			['0/2,1:00-9:20a', 'incorrect syntax near "a"'],
			['0/2,24:00-24:01', 'incorrect syntax near "4:00-24:01"'],
			['0/2,24:00-23:00', 'incorrect syntax near "4:00-23:00"'],
			['0/2,00:00-024:00', 'incorrect syntax near ":00"'],
			['50/7,0:01-0:02;0/2,00-24:00', 'incorrect syntax near "-24:00"'],
			['50/1-�,00:00-23:59', 'incorrect syntax near "�,00:00-23:59"']
		];
	}

	/**
	 * An array of valid scheduling intervals parsed results.
	 */
	public function testValidSchedulingProvider() {
		return [
			// scheduling months
			[
				'md/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30'
				]],
				[
					'md/30'
				]
			],
			[
				'md1-31/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31/30'
				]],
				[
					'md1-31/30'
				]
			],
			[
				'md1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-1'
				]],
				[
					'md1-1'
				]
			],
			[
				'md28-30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md28-30'
				]],
				[
					'md28-30'
				]
			],
			[
				'md01-31',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-31'
				]],
				[
					'md01-31'
				]
			],
			[
				'md01-31/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-31/30'
				]],
				[
					'md01-31/30'
				]
			],
			[
				'md1-5,8-31',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-5,8-31'
				]],
				[
					'md1-5,8-31'
				]
			],
			[
				'md/30,1-5/4,8-31/23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30,1-5/4,8-31/23'
				]],
				[
					'md/30,1-5/4,8-31/23'
				]
			],
			[
				'md1-5/4,8-31/23,/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-5/4,8-31/23,/30'
				]],
				[
					'md1-5/4,8-31/23,/30'
				]
			],
			[
				'md31-31',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md31-31'
				]],
				[
					'md31-31'
				]
			],
			[
				'md01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01'

				]],
				[
					'md01'
				]
			],
			[
				'md1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1'
				]],
				[
					'md1'
				]
			],
			[
				'md10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md10'
				]],
				[
					'md10'
				]
			],
			[
				'md31',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md31'
				]],
				[
					'md31'
				]
			],
			[
				'md1,10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1,10'
				]],
				[
					'md1,10'
				]
			],
			[
				'md01,10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01,10'
				]],
				[
					'md01,10'
				]
			],
			[
				'md01;md1;md31;md1,10;md01,10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md31'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1,10'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01,10'
				]],
				[
					'md01',
					'md1',
					'md31',
					'md1,10',
					'md01,10'
				]
			],
			[
				'md01-10/9',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-10/9'
				]],
				[
					'md01-10/9'
				]
			],
			[
				'md1-10/9',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-10/9'
				]],
				[
					'md1-10/9'
				]
			],
			[
				'md/9',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/9'
				]],
				[
					'md/9'
				]
			],
			[
				'md/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30'
				]],
				[
					'md/30'
				]
			],
			[
				'md/30;md/25',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/25'
				]],
				[
					'md/30',
					'md/25'
				]
			],
			[
				'md1-31/30;md/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31/30'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30'
				]],
				[
					'md1-31/30',
					'md/30'
				]
			],
			[
				'md01-31/30;md/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-31/30'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30'
				]],
				[
					'md01-31/30',
					'md/30'
				]
			],
			[
				'md01-31;md/30',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01-31'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/30'
				]],
				[
					'md01-31',
					'md/30'
				]
			],
			[
				'md1-31/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31/01'
				]],
				[
					'md1-31/01'
				]
			],
			// scheduling weeks
			[
				'wd/6',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6'
				]],
				[
					'wd/6'
				]
			],
			[
				'wd1-7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7'
				]],
				[
					'wd1-7'
				]
			],
			[
				'wd1-7/6',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6'
				]],
				[
					'wd1-7/6'
				]
			],
			[
				'wd1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-1'
				]],
				[
					'wd1-1'
				]
			],
			[
				'wd7-7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-7'
				]],
				[
					'wd7-7'
				]
			],
			[
				'wd1-5,6-7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-5,6-7'
				]],
				[
					'wd1-5,6-7'
				]
			],
			[
				'wd/6,1-5/4,2-7/5',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6,1-5/4,2-7/5'
				]],
				[
					'wd/6,1-5/4,2-7/5'
				]
			],
			[
				'wd1-5/4,3-7/4,/6',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-5/4,3-7/4,/6'
				]],
				[
					'wd1-5/4,3-7/4,/6'
				]
			],
			[
				'wd1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1'
				]],
				[
					'wd1'
				]
			],
			[
				'wd7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7'
				]],
				[
					'wd7'
				]
			],
			[
				'wd1,7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,7'
				]],
				[
					'wd1,7'
				]
			],
			[
				'wd1,7,6,2',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,7,6,2'
				]],
				[
					'wd1,7,6,2'
				]
			],
			[
				'wd1;wd7;wd1,4,5-7;wd1,7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,4,5-7'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,7'
				]],
				[
					'wd1',
					'wd7',
					'wd1,4,5-7',
					'wd1,7'
				]
			],
			[
				'wd1-3/2',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-3/2'
				]],
				[
					'wd1-3/2'
				]
			],
			[
				'wd/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/1'
				]],
				[
					'wd/1'
				]
			],
			[
				'wd/6;wd/4',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/4'
				]],
				[
					'wd/6',
					'wd/4'
				]
			],
			[
				'wd1-7/6;wd/6;wd/2;wd1-2/1,3-5/2',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/2'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-2/1,3-5/2'
				]],
				[
					'wd1-7/6',
					'wd/6',
					'wd/2',
					'wd1-2/1,3-5/2'
				]
			],
			[
				'wd1-7,1-1;wd1-7;wd7-9;wd1,3,5-7',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7,1-1'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-9'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,3,5-7'
				]],
				[
					'wd1-7,1-1',
					'wd1-7',
					'wd7-9',
					'wd1,3,5-7'
				]
			],
			[
				'wd1-7/6;wd/6',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6'
				]],
				[
					'wd1-7/6',
					'wd/6'
				]
			],
			[
				'wd1-7/6',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6'
				]],
				[
					'wd1-7/6'
				]
			],
			// mixed scheduling months and weeks
			[
				'wd1-7/6;md1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1'
				]],
				[
					'wd1-7/6',
					'md1'
				]
			],
			[
				'wd1-7/6;md1-31',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31'
				]],
				[
					'wd1-7/6',
					'md1-31'
				]
			],
			[
				'wd1-7/6;md1-31,1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31,1-1'
				]],
				[
					'wd1-7/6',
					'md1-31,1-1'
				]
			],
			[
				'wd1-7/6;md/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md/1'
				]],
				[
					'wd1-7/6',
					'md/1'
				]
			],
			[
				'md1-31wd1-7;wd/6;md05-10wd5',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1-31wd1-7'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md05-10wd5'
				]],
				[
					'md1-31wd1-7',
					'wd/6',
					'md05-10wd5'
				]
			],
			// scheduling hours
			[
				'h/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/1'
				]],
				[
					'h/1'
				]
			],
			[
				'h/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/01'
				]],
				[
					'h/01'
				]
			],
			[
				'h/02',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/02'
				]],
				[
					'h/02'
				]
			],
			[
				'h/1;h23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/1'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h23'
				]],
				[
					'h/1',
					'h23'
				]
			],
			[
				'h1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-1'
				]],
				[
					'h1-1'
				]
			],
			[
				'h1-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-23'
				]],
				[
					'h1-23'
				]
			],
			[
				'h01-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h01-1'
				]],
				[
					'h01-1'
				]
			],
			[
				'h01-01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h01-01'
				]],
				[
					'h01-01'
				]
			],
			[
				'h01-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h01-23'
				]],
				[
					'h01-23'
				]
			],
			[
				'h0-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h0-23'
				]],
				[
					'h0-23'
				]
			],
			[
				'h00-23/23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h00-23/23'
				]],
				[
					'h00-23/23'
				]
			],
			[
				'h01-23/22',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h01-23/22'
				]],
				[
					'h01-23/22'
				]
			],
			[
				'h1-5,7-10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10'
				]],
				[
					'h1-5,7-10'
				]
			],
			[
				'h1-5,7-10/3',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/3'
				]],
				[
					'h1-5,7-10/3'
				]
			],
			[
				'h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03'
				]],
				[
					'h1-5,7-10/03'
				]
			],
			[
				'h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03'
				]],
				[
					'h/02',
					'h1-5,7-10/03'
				]
			],
			[
				'h0-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h0-0'
				]],
				[
					'h0-0'
				]
			],
			[
				'h0-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h0-00'
				]],
				[
					'h0-00'
				]
			],
			[
				'h00-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h00-0'
				]],
				[
					'h00-0'
				]
			],
			[
				'h00-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h00-00'
				]],
				[
					'h00-00'
				]
			],
			// mixed scheduling weeks and hours
			[
				'wd/6,1-5/4,2-7/5h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6,1-5/4,2-7/5h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03'
				]],
				[
					'wd/6,1-5/4,2-7/5h/02',
					'h1-5,7-10/03'
				]
			],
			[
				'wd1-5/4,3-7/4,/6h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-5/4,3-7/4,/6h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03'
				]],
				[
					'wd1-5/4,3-7/4,/6h/02',
					'h1-5,7-10/03'
				]
			],
			[
				'wd1-7/6;wd/6;wd/2h/02;wd1-2/1,1-2/1h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7/6'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/2h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-2/1,1-2/1h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03'
				]],
				[
					'wd1-7/6',
					'wd/6',
					'wd/2h/02',
					'wd1-2/1,1-2/1h/02',
					'h1-5,7-10/03'
				]
			],
			[
				'wd1-7,1-1;wd1-7;wd1-7h/02;wd7-9;wd1,3,5-7h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7,1-1'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-9'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,3,5-7h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03'
				]],
				[
					'wd1-7,1-1',
					'wd1-7',
					'wd1-7h/02',
					'wd7-9',
					'wd1,3,5-7h/02',
					'h1-5,7-10/03'
				]
			],
			// mixed scheduling months and hours
			[
				'md01;md1h/02;md31;md1,10h/02;h1-5,7-10/03;md01,10',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md31'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1,10h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01,10'
				]],
				[
					'md01',
					'md1h/02',
					'md31',
					'md1,10h/02',
					'h1-5,7-10/03',
					'md01,10'
				]
			],
			// mixed scheduling months, weeks and hours
			[
				'md01;md1h/02;wd1-7,1-1;wd1-7;wd1-7h/02;wd7-9;wd1,3,5-7h/02;md31;md1,10h/02;md01,10wd1-7,1-1;wd1-7;wd1-7h/02;wd7-9;wd1,3,5-7h/02;h1-5,7-10/03',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7,1-1'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-9'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,3,5-7h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md31'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md1,10h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01,10wd1-7,1-1'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-7h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-9'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1,3,5-7h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03'
				]],
				[
					'md01',
					'md1h/02',
					'wd1-7,1-1',
					'wd1-7',
					'wd1-7h/02',
					'wd7-9',
					'wd1,3,5-7h/02',
					'md31',
					'md1,10h/02',
					'md01,10wd1-7,1-1',
					'wd1-7',
					'wd1-7h/02',
					'wd7-9',
					'wd1,3,5-7h/02',
					'h1-5,7-10/03'
				]
			],
			[
				'm/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/1'
				]],
				[
					'm/1'
				]
			],
			[
				'm/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/01'
				]],
				[
					'm/01'
				]
			],
			[
				'm/02',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/02'
				]],
				[
					'm/02'
				]
			],
			[
				'm/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/59'
				]],
				[
					'm/59'
				]
			],
			[
				'm/1;m59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/1'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm59'
				]],
				[
					'm/1',
					'm59'
				]
			],
			[
				'm1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-1'
				]],
				[
					'm1-1'
				]
			],
			[
				'm1-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-23'
				]],
				[
					'm1-23'
				]
			],
			[
				'm01-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm01-1'
				]],
				[
					'm01-1'
				]
			],
			[
				'm01-01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm01-01'
				]],
				[
					'm01-01'
				]
			],
			[
				'm01-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm01-23'
				]],
				[
					'm01-23'
				]
			],
			[
				'm0-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm0-23'
				]],
				[
					'm0-23'
				]
			],
			[
				'm00-59/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm00-59/59'
				]],
				[
					'm00-59/59'
				]
			],
			[
				'm01-59/58',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm01-59/58'
				]],
				[
					'm01-59/58'
				]
			],
			[
				'm1-5,33-59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,33-59'
				]],
				[
					'm1-5,33-59'
				]
			],
			[
				'm1-5,28-45/17',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,28-45/17'
				]],
				[
					'm1-5,28-45/17'
				]
			],
			[
				'm1-5,44-45/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,44-45/01'
				]],
				[
					'm1-5,44-45/01'
				]
			],
			[
				'm1-5,44-45/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,44-45/1'
				]],
				[
					'm1-5,44-45/1'
				]
			],
			[
				'm/02;m1-5,44-45/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,44-45/1'
				]],
				[
					'm/02',
					'm1-5,44-45/1'
				]
			],
			[
				'm0-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm0-0'
				]],
				[
					'm0-0'
				]
			],
			[
				'm0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm0'
				]],
				[
					'm0'
				]
			],
			[
				'm0-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm0-00'
				]],
				[
					'm0-00'
				]
			],
			[
				'm00-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm00-0'
				]],
				[
					'm00-0'
				]
			],
			[
				'm00-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm00-00'
				]],
				[
					'm00-00'
				]
			],
			[
				'm1-1,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-1,58-59/1,/1,/59'
				]],
				[
					'm1-1,58-59/1,/1,/59'
				]
			],
			[
				'm/30,1-4,05-09,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/30,1-4,05-09,58-59/1,/1,/59'
				]],
				[
					'm/30,1-4,05-09,58-59/1,/1,/59'
				]
			],
			// mixed scheduling minutes, hours and weeks
			[
				'wd/6,1-5/4,2-7/5h/02;h1-5,7-10/03m1-1,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6,1-5/4,2-7/5h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03m1-1,58-59/1,/1,/59'
				]],
				[
					'wd/6,1-5/4,2-7/5h/02',
					'h1-5,7-10/03m1-1,58-59/1,/1,/59'
				]
			],
			// scheduling seconds
			[
				's/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/1'
				]],
				[
					's/1'
				]
			],
			[
				's/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/01'
				]],
				[
					's/01'
				]
			],
			[
				's/02',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/02'
				]],
				[
					's/02'
				]
			],
			[
				's/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/59'
				]],
				[
					's/59'
				]
			],
			[
				's/1;m59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/1'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm59'
				]],
				[
					's/1',
					'm59'
				]
			],
			[
				's1-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-1'
				]],
				[
					's1-1'
				]
			],
			[
				's1-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-23'
				]],
				[
					's1-23'
				]
			],
			[
				's01-1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's01-1'
				]],
				[
					's01-1'
				]
			],
			[
				's01-01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's01-01'
				]],
				[
					's01-01'
				]
			],
			[
				's01-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's01-23'
				]],
				[
					's01-23'
				]
			],
			[
				's0-23',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's0-23'
				]],
				[
					's0-23'
				]
			],
			[
				's00-59/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's00-59/59'
				]],
				[
					's00-59/59'
				]
			],
			[
				's01-59/58',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's01-59/58'
				]],
				[
					's01-59/58'
				]
			],
			[
				's1-5,33-59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-5,33-59'
				]],
				[
					's1-5,33-59'
				]
			],
			[
				's1-5,28-45/17',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-5,28-45/17'
				]],
				[
					's1-5,28-45/17'
				]
			],
			[
				's1-5,44-45/01',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-5,44-45/01'
				]],
				[
					's1-5,44-45/01'
				]
			],
			[
				's1-5,44-45/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-5,44-45/1'
				]],
				[
					's1-5,44-45/1'
				]
			],
			[
				's/02;m1-5,44-45/1',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm1-5,44-45/1'
				]],
				[
					's/02',
					'm1-5,44-45/1'
				]
			],
			[
				's0-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's0-0'
				]],
				[
					's0-0'
				]
			],
			[
				's0-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's0-00'
				]],
				[
					's0-00'
				]
			],
			[
				's00-0',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's00-0'
				]],
				[
					's00-0'
				]
			],
			[
				's00-00',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's00-00'
				]],
				[
					's00-00'
				]
			],
			[
				's1-1,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's1-1,58-59/1,/1,/59'
				]],
				[
					's1-1,58-59/1,/1,/59'
				]
			],
			[
				's/30,1-4,05-09,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/30,1-4,05-09,58-59/1,/1,/59'
				]],
				[
					's/30,1-4,05-09,58-59/1,/1,/59'
				]
			],
			// mixed scheduling seconds, weeks, minutes and hours
			[
				'wd/6,1-5/4,2-7/5h/02;h1-5,7-10/03m1-1,58-59/1,/1,/59s/30,1-4,05-09,58-59/1,/1,/59',
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/6,1-5/4,2-7/5h/02'
				],
				[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03m1-1,58-59/1,/1,/59s/30,1-4,05-09,58-59/1,/1,/59'
				]],
				[
					'wd/6,1-5/4,2-7/5h/02',
					'h1-5,7-10/03m1-1,58-59/1,/1,/59s/30,1-4,05-09,58-59/1,/1,/59'
				]
			]
		];
	}

	/**
	 * An array of invalid scheduling intervals and error messages.
	 */
	public function testInvalidSchedulingProvider() {
		return [
			// scheduling months
			['md31-30', 'incorrect syntax near "0"'],
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
			['md30/5', 'incorrect syntax near "/5"'],
			['md30-32/5', 'unexpected end of interval'],
			['md32-28/4', 'incorrect syntax near "2-28/4"'],
			['md0-1/5', 'incorrect syntax near "-1/5"'],
			['md0', 'unexpected end of interval'],
			['md0/0', 'incorrect syntax near "/0"'],
			['md1-31/0000', 'incorrect syntax near "000"'],
			['md1-31/001', 'incorrect syntax near "01"'],
			['md1/1', 'incorrect syntax near "/1"'],
			['md0-1000/5', 'incorrect syntax near "-1000/5"'],
			['md0000-1000/5', 'incorrect syntax near "000-1000/5"'],
			['md0001-1000/5', 'incorrect syntax near "001-1000/5"'],
			['md01-0031/1', 'incorrect syntax near "031/1"'],
			['md01--31/1', 'incorrect syntax near "-31/1"'],
			['md1--31/1', 'incorrect syntax near "-31/1"'],
			['md01- 31/1', 'incorrect syntax near " 31/1"'],
			['md01 -31/1', 'incorrect syntax near " -31/1"'],
			['md01-31//1', 'incorrect syntax near "/1"'],
			['md1-31//1', 'incorrect syntax near "/1"'],
			['md/31', 'incorrect syntax near "1"'],
			['md/30;', 'unexpected end of interval'],
			['md/3;', 'unexpected end of interval'],
			['md/003;', 'incorrect syntax near "03;"'],
			['md/003', 'incorrect syntax near "03"'],
			['md/000', 'incorrect syntax near "00"'],
			['md/00-31', 'incorrect syntax near "0-31"'],
			['md/000-31', 'incorrect syntax near "00-31"'],
			['md/1-31/31', 'incorrect syntax near "-31/31"'],
			['md28-31/', 'unexpected end of interval'],
			['md28-31/;', 'incorrect syntax near ";"'],
			['md1-5/4,', 'unexpected end of interval'],
			['md1-5/4,/', 'unexpected end of interval'],
			['md1-5/4,9-7', 'unexpected end of interval'],
			['md1-2/3', 'unexpected end of interval'],
			['md/', 'unexpected end of interval'],
			['md', 'unexpected end of interval'],
			// scheduling weeks
			['wd7-6', 'unexpected end of interval'],
			['wd01-7', 'incorrect syntax near "1-7"'],
			['wd001-7', 'incorrect syntax near "01-7"'],
			['wd1-07', 'incorrect syntax near "7"'],
			['wd1-01', 'incorrect syntax near "1"'],
			['wd1-001', 'incorrect syntax near "01"'],
			['wd1-7/06', 'incorrect syntax near "6"'],
			['wd1-7/006', 'incorrect syntax near "06"'],
			['wd1,2/1', 'incorrect syntax near "/1"'],
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
			['wd7/1', 'incorrect syntax near "/1"'],
			['wd6-7/5', 'unexpected end of interval'],
			['wd0-1/5', 'incorrect syntax near "-1/5"'],
			['wd1-0', 'unexpected end of interval'],
			['wd0', 'unexpected end of interval'],
			['wd0/0', 'incorrect syntax near "/0"'],
			['wd1-7/0000', 'incorrect syntax near "000"'],
			['wd1-7/001', 'incorrect syntax near "01"'],
			['wd1/1', 'incorrect syntax near "/1"'],
			['wd0-7/5', 'incorrect syntax near "-7/5"'],
			['wd0000-1000/5', 'incorrect syntax near "000-1000/5"'],
			['wd0001-1000/5', 'incorrect syntax near "001-1000/5"'],
			['wd01-007/1', 'incorrect syntax near "1-007/1"'],
			['wd01--7/1', 'incorrect syntax near "1--7/1"'],
			['wd1--7/1', 'incorrect syntax near "-7/1"'],
			['wd01- 7/1', 'incorrect syntax near "1- 7/1"'],
			['wd01 -7/1', 'incorrect syntax near "1 -7/1"'],
			['wd01-7//1', 'incorrect syntax near "1-7//1"'],
			['wd1-7//1', 'incorrect syntax near "/1"'],
			['wd/7', 'unexpected end of interval'],
			['wd/7;', 'incorrect syntax near ";"'],
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
			['wd1-5/4,9-7', 'incorrect syntax near "-7"'],
			['wd1-2/3', 'unexpected end of interval'],
			// mixed scheduling months and weeks
			['wd1-7/6md', 'incorrect syntax near "md"'],
			['wd1-7/6m', 'incorrect syntax near "m"'],
			['wd1-7/6md1', 'incorrect syntax near "md1"'],
			['wd1-7/6md1-31', 'incorrect syntax near "md1-31"'],
			['wd1-7/6md1-31,1-1', 'incorrect syntax near "md1-31,1-1"'],
			['wd1-7/6md/1', 'incorrect syntax near "md/1"'],
			['wd/', 'unexpected end of interval'],
			['wd', 'unexpected end of interval'],
			// scheduling hours
			['h24', 'incorrect syntax near "4"'],
			['h000', 'unexpected end of interval'],
			['h001', 'unexpected end of interval'],
			['h100', 'incorrect syntax near "0"'],
			['h230', 'incorrect syntax near "0"'],
			['h023', 'unexpected end of interval'],
			['h00-24', 'incorrect syntax near "4"'],
			['h0-24', 'incorrect syntax near "4"'],
			['h0-023', 'unexpected end of interval'],
			['h0-000', 'unexpected end of interval'],
			['h000-0', 'incorrect syntax near "-0"'],
			['hd', 'incorrect syntax near "d"'],
			['h;', 'incorrect syntax near ";"'],
			['h/', 'unexpected end of interval'],
			['h/0', 'unexpected end of interval'],
			[';h0-0', 'incorrect syntax near ";h0-0"'],
			[';h00-23', 'incorrect syntax near ";h00-23"'],
			['h0-0/0', 'unexpected end of interval'],
			['h00-23/24', 'incorrect syntax near "4"'],
			['h00-23//23', 'incorrect syntax near "/23"'],
			['h00-23/ 23', 'incorrect syntax near " 23"'],
			['h00-23 /23', 'incorrect syntax near " /23"'],
			['h00--23/23', 'incorrect syntax near "-23/23"'],
			['h000-23/23', 'incorrect syntax near "-23/23"'],
			['h00-230/23', 'incorrect syntax near "0/23"'],
			['h00-003/3', 'incorrect syntax near "/3"'],
			['h00-23/0', 'unexpected end of interval'],
			['h00-23/00', 'incorrect syntax near "0"'],
			['h00-23/023', 'unexpected end of interval'],
			['h00-23/', 'unexpected end of interval'],
			['h00-23/;', 'incorrect syntax near ";"'],
			['h00 -23/01', 'incorrect syntax near " -23/01"'],
			['h00- 23/01', 'incorrect syntax near " 23/01"'],
			['h0023/01', 'incorrect syntax near "/01"'],
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
			['m60', 'incorrect syntax near "0"'],
			['m000', 'unexpected end of interval'],
			['m001', 'unexpected end of interval'],
			['m100', 'incorrect syntax near "0"'],
			['m590', 'incorrect syntax near "0"'],
			['m059', 'unexpected end of interval'],
			['m00-60', 'incorrect syntax near "0"'],
			['m0-60', 'incorrect syntax near "0"'],
			['m0-059', 'unexpected end of interval'],
			['m0-000', 'unexpected end of interval'],
			['m000-0', 'incorrect syntax near "-0"'],
			['m', 'incorrect syntax near "m"'],
			['m;', 'incorrect syntax near ";"'],
			['m,', 'incorrect syntax near ","'],
			['m', 'incorrect syntax near "m"'],
			['m-', 'incorrect syntax near "-"'],
			['m1-', 'unexpected end of interval'],
			['ms', 'incorrect syntax near "s"'],
			['m/', 'unexpected end of interval'],
			['m/0', 'unexpected end of interval'],
			[';m0-0', 'incorrect syntax near ";m0-0"'],
			[';m00-59', 'incorrect syntax near ";m00-59"'],
			['m0-0/0', 'unexpected end of interval'],
			['m00-59/60', 'incorrect syntax near "0"'],
			['m00-59//59', 'incorrect syntax near "/59"'],
			['m00-59/ 59', 'incorrect syntax near " 59"'],
			['m00-59 /59', 'incorrect syntax near " /59"'],
			['m00--59/59', 'incorrect syntax near "-59/59"'],
			['m000-59/59', 'incorrect syntax near "-59/59"'],
			['m00-590/59', 'incorrect syntax near "0/59"'],
			['m00-003/3', 'incorrect syntax near "/3"'],
			['m00-59/0', 'unexpected end of interval'],
			['m00-59/00', 'incorrect syntax near "0"'],
			['m00-59/059', 'unexpected end of interval'],
			['m00-59/', 'unexpected end of interval'],
			['m00-59/;', 'incorrect syntax near ";"'],
			['m00 -59/01', 'incorrect syntax near " -59/01"'],
			['m00- 59/01', 'incorrect syntax near " 59/01"'],
			['m0059/01', 'incorrect syntax near "/01"'],
			['m00-23;m', 'incorrect syntax near "m"'],
			['m00-23;m;', 'incorrect syntax near ";"'],
			['m00-23;m,', 'incorrect syntax near ","'],
			['m00-23;m-', 'incorrect syntax near "-"'],
			['m00-23;m', 'incorrect syntax near "m"'],
			// mixed scheduling minutes, months, weeks and hours
			['m00-59md1-31', 'incorrect syntax near "md1-31"'],
			['m00-59wd1-7', 'incorrect syntax near "wd1-7"'],
			['md1-31m00-59h00-23wd1-7', 'incorrect syntax near "h00-23wd1-7"'],
			// scheduling seconds
			['s60', 'incorrect syntax near "0"'],
			['s000', 'unexpected end of interval'],
			['s001', 'unexpected end of interval'],
			['s100', 'incorrect syntax near "0"'],
			['s590', 'incorrect syntax near "0"'],
			['s059', 'unexpected end of interval'],
			['s00-60', 'incorrect syntax near "0"'],
			['s0-60', 'incorrect syntax near "0"'],
			['s0-059', 'unexpected end of interval'],
			['s0-000', 'unexpected end of interval'],
			['s000-0', 'incorrect syntax near "-0"'],
			['s', 'unexpected end of interval'],
			['s;', 'incorrect syntax near ";"'],
			['s/', 'unexpected end of interval'],
			['s/0', 'unexpected end of interval'],
			[';s0-0', 'incorrect syntax near ";s0-0"'],
			[';s00-59', 'incorrect syntax near ";s00-59"'],
			['s0-0/0', 'unexpected end of interval'],
			['s00-59/60', 'incorrect syntax near "0"'],
			['s00-59//59', 'incorrect syntax near "/59"'],
			['s00-59/ 59', 'incorrect syntax near " 59"'],
			['s00-59 /59', 'incorrect syntax near " /59"'],
			['s00--59/59', 'incorrect syntax near "-59/59"'],
			['s000-59/59', 'incorrect syntax near "-59/59"'],
			['s00-590/59', 'incorrect syntax near "0/59"'],
			['s00-003/3', 'incorrect syntax near "/3"'],
			['s00-59/0', 'unexpected end of interval'],
			['s00-59/00', 'incorrect syntax near "0"'],
			['s00-59/059', 'unexpected end of interval'],
			['s00-59/', 'unexpected end of interval'],
			['s00-59/;', 'incorrect syntax near ";"'],
			['s00 -59/01', 'incorrect syntax near " -59/01"'],
			// mixed scheduling seconds, weeks, minutes and hours
			['s00- 59/01', 'incorrect syntax near " 59/01"'],
			['s0059/01', 'incorrect syntax near "/01"'],
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
		$this->assertEmpty($parser->getSchedulingIntervals());
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
		$this->assertEquals($result, $parser->getSchedulingIntervals());
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
}
