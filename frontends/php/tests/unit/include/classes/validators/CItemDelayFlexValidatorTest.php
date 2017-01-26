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


class CItemDelayFlexValidatorTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of valid flexible intervals.
	 */
	public function testValidFlexibleProvider() {
		return [
			[[[
				'interval' => '86400/7-7,23:59-24:00',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '86400',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '00000000000/7-7,23:59-24:00',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '9999/7-7,23:59-24:00',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '50/7,0:00-0:01',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '50/7,00:00-0:01',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '50/7,00:00-00:01',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '50/7,0:00-00:01',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '50/1-7,0:00-00:01',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '50/1-7,0:01-0:02',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '50/7,0:01-0:02',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '50/7,0:01-0:02',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '50/4-7,00:01-0:59',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]],
			[[[
				'interval' => '000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000/7,0:01-0:02',
				'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
				'delay' => '00000000000',
				'period' => '7-7,23:59-24:00'
			]]]
		];
	}

	/**
	 * An array of invalid flexible intervals.
	 */
	public function testInvalidFlexibleProvider() {
		return [
			[
				[[
					'interval' => '86401/7,0:01-0:02',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '86401',
					'period' => '7,0:01-0:02'
				]],
				'Invalid flexible interval delay: "86401" exceeds maximum delay of "86400".'
			],
			[
				[[
					'interval' => '99999/7,23:59-23:59',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '99999',
					'period' => '7,0:01-0:02'
				]],
				'Invalid flexible interval delay: "99999" exceeds maximum delay of "86400".'
			],
			[
				[[
					'interval' => '86400/7-7,23:59-23:59',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '86400',
					'period' => '7-7,23:59-23:59'
				]],
				'Incorrect time period "7-7,23:59-23:59" start time must be less than end time.'
			],
			[
				[[
					'interval' => '86400/8-9,23:59-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '8640',
					'period' => '8-9,23:59-24:00'
				]],
				'Incorrect time period "8-9,23:59-24:00".'
			],
			[
				[[
					'interval' => '9/8,23:59-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '9',
					'period' => '8,23:59-24:00'
				]],
				'Incorrect time period "8,23:59-24:00".'
			],
			[
				[[
					'interval' => '5/7-9,0:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '5',
					'period' => '7-9,0:00-24:00'
				]],
				'Incorrect time period "7-9,0:00-24:00".'
			],
			[
				[[
					'interval' => '5/7-6,0:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '5',
					'period' => '7-6,0:00-24:00'
				]],
				'Incorrect time period "7-6,0:00-24:00" start day must be less or equal to end day.'
			],
			[
				[[
					'interval' => '0/0,0:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '0,0:00-24:00'
				]],
				'Incorrect time period "0,0:00-24:00".'
			],
			[
				[[
					'interval' => '0/0-0,0:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '0-0,0:00-24:00'
				]],
				'Incorrect time period "0-0,0:00-24:00".'
			],
			[
				[[
					'interval' => '0/1-0,0:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '1-0,0:00-24:00'
				]],
				'Incorrect time period "1-0,0:00-24:00".'
			],
			[
				[[
					'interval' => '0/2,00:00-00:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,00:00-00:00'
				]],
				'Incorrect time period "2,00:00-00:00" start time must be less than end time.'
			],
			[
				[[
					'interval' => '0/2,00:01-00:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,00:01-00:00'
				]],
				'Incorrect time period "2,00:01-00:00" start time must be less than end time.'
			],
			[
				[[
					'interval' => '0/2,5:00-29:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,5:00-29:00'
				]],
				'Incorrect time period "2,5:00-29:00".'
			],
			[
				[[
					'interval' => '0/2,24:00-24:01',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,24:00-24:01'
				]],
				'Incorrect time period "2,24:00-24:01".'
			],
			[
				[[
					'interval' => '0/2,24:00-23:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,24:00-23:00'
				]],
				'Incorrect time period "2,24:00-23:00" start time must be less than end time.'
			],
			[
				[[
					'interval' => '0/2,99:00-24:00',
					'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
					'delay' => '0',
					'period' => '2,99:00-24:00'
				]],
				'Incorrect time period "2,99:00-24:00".'
			]
		];
	}

	/**
	 * An array of valid scheduling intervals.
	 */
	public function testValidSchedulingProvider() {
		return [
			// Scheduling month days.
			[[[
				'interval' => 'md1-5,8-31',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '8', 'till' => '31', 'step' => '']
				]
			]]],
			[[[
				'interval' => 'md/30,1-5/4,8-31/23',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '', 'till' => '', 'step' => '30'],
					['from' => '1', 'till' => '5', 'step' => '4'],
					['from' => '8', 'till' => '31', 'step' => '23']
				]
			]]],
			[[[
				'interval' => 'md1-5/4,8-31/23,/30',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '1', 'till' => '5', 'step' => '4'],
					['from' => '8', 'till' => '31', 'step' => '23'],
					['from' => '', 'till' => '', 'step' => '30']
				]
			]]],
			[[[
				'interval' => 'md31-31',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '31', 'till' => '31', 'step' => '']
				]
			]]],
			[[[
				'interval' => 'md01',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '01', 'till' => '', 'step' => '']
				]
			]]],
			[[[
				'interval' => 'md1',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '1', 'till' => '', 'step' => '']
				]
			]]],
			[[[
				'interval' => 'md10',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '10', 'till' => '', 'step' => '']
				]
			]]],
			[[[
				'interval' => 'md31',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '31', 'till' => '', 'step' => '']
				]
			]]],
			[[[
				'interval' => 'md1,10',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '1', 'till' => '', 'step' => ''],
					['from' => '10', 'till' => '', 'step' => '']
				]
			]]],
			[[[
				'interval' => 'md01,10',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '01', 'till' => '', 'step' => ''],
					['from' => '10', 'till' => '', 'step' => '']
				]
			]]],
			[[[
				'interval' => 'md01-10/9',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '01', 'till' => '10', 'step' => '9']
				]
			]]],
			[[[
				'interval' => 'md1-10/9',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '1', 'till' => '10', 'step' => '9']
				]
			]]],
			[[[
				'interval' => 'md/9',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '', 'till' => '', 'step' => '9']
				]
			]]],
			[[[
				'interval' => 'md/30',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '', 'till' => '', 'step' => '30']
				]
			]]],
			[[[
				'interval' => 'md1-31/30',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '1', 'till' => '31', 'step' => '30']
				]
			]]],
			[[[
				'interval' => 'md01-31/30',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '01', 'till' => '31', 'step' => '30']
				]
			]]],
			[[[
				'interval' => 'md1-31/01',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '1', 'till' => '31', 'step' => '01']
				]
			]]],
			[[[
				'interval' => 'md1,/1',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '1', 'till' => '', 'step' => ''],
					['from' => '', 'till' => '', 'step' => '1']
				]
			]]],
			[[[
				'interval' => 'md01-01',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '01', 'till' => '01', 'step' => '']
				]
			]]],
			[[[
				'interval' => 'md01-02/01',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '01', 'till' => '02', 'step' => '01']
				]
			]]],
			[[[
				'interval' => 'md02-02',
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'md' => [
					['from' => '02', 'till' => '02', 'step' => '']
				]
			]]],
			// Scheduling week days.
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd/6',
				'wd' => [
					['from' => '', 'till' => '', 'step' => '6']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd1-7',
				'wd' => [
					['from' => '1', 'till' => '7', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd1-7/6',
				'wd' => [
					['from' => '1', 'till' => '7', 'step' => '6']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd1-1',
				'wd' => [
					['from' => '1', 'till' => '1', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd7-7',
				'wd' => [
					['from' => '7', 'till' => '7', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd1-5,6-7',
				'wd' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '6', 'till' => '7', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd/6,1-5/4,2-7/5',
				'wd' => [
					['from' => '', 'till' => '', 'step' => '6'],
					['from' => '1', 'till' => '5', 'step' => '4'],
					['from' => '2', 'till' => '7', 'step' => '5']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd1-5/4,3-7/4,/6',
				'wd' => [
					['from' => '1', 'till' => '5', 'step' => '4'],
					['from' => '3', 'till' => '7', 'step' => '4'],
					['from' => '', 'till' => '', 'step' => '6']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd1',
				'wd' => [
					['from' => '1', 'till' => '', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd7',
				'wd' => [
					['from' => '7', 'till' => '', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd1,7',
				'wd' => [
					['from' => '1', 'till' => '', 'step' => ''],
					['from' => '7', 'till' => '', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd1,7,6,2',
				'wd' => [
					['from' => '1', 'till' => '', 'step' => ''],
					['from' => '7', 'till' => '', 'step' => ''],
					['from' => '6', 'till' => '', 'step' => ''],
					['from' => '2', 'till' => '', 'step' => '']
				]
			]]],
			[[
				[
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
				]
			]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd1-3/2',
				'wd' => [
					['from' => '1', 'till' => '3', 'step' => '2']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd/1',
				'wd' => [
					['from' => '', 'till' => '', 'step' => '1']
				]
			]]],
			[[
				[
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
				]
			]],
			[[
				[
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
				]
			]],
			[[
				[
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
				]
			]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'wd1-7/6',
				'wd' => [
					['from' => '1', 'till' => '7', 'step' => '6']
				]
			]]],
			// Mixed scheduling month days and week days.
			[[
				[
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
				]
			]],
			[[
				[
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
				]
			]],
			[[
				[
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
				]
			]],
			[[
				[
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
						['from' => '1', 'till' => '', 'step' => '']
					]
				]
			]],
			[[
				[
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
						['from' => '1', 'till' => '7', 'step' => '6']
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
				]
			]],
			// Scheduling hours.
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h/1',
				'h' => [
					['from' => '', 'till' => '', 'step' => '1']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h/01',
				'h' => [
					['from' => '', 'till' => '', 'step' => '01']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h/02',
				'h' => [
					['from' => '', 'till' => '', 'step' => '02']
				]
			]]],
			[[
				[
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
				]
			]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h1-1',
				'h' => [
					['from' => '1', 'till' => '1', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h1-23',
				'h' => [
					['from' => '1', 'till' => '23', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h01-1',
				'h' => [
					['from' => '01', 'till' => '1', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h01-01',
				'h' => [
					['from' => '01', 'till' => '01', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h01-23',
				'h' => [
					['from' => '01', 'till' => '23', 'step' => '']
				]
			]]],
			[[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h0-23',
					'h' => [
						['from' => '0', 'till' => '23', 'step' => '']
					]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h00-23/23',
				'h' => [
					['from' => '00', 'till' => '23', 'step' => '23']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h01-23/22',
				'h' => [
					['from' => '01', 'till' => '23', 'step' => '22']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h1-5,7-10',
				'h' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '7', 'till' => '10', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h1-5,7-10/3',
				'h' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '7', 'till' => '10', 'step' => '3']
				]
			]]],
			[[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h1-5,7-10/03',
					'h' => [
						['from' => '1', 'till' => '5', 'step' => ''],
						['from' => '7', 'till' => '10', 'step' => '03']
					]
			]]],
			[[
				[
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
				]
			]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h0-0',
				'h' => [
					['from' => '0', 'till' => '0', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h0-00',
				'h' => [
					['from' => '0', 'till' => '00', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h00-0',
				'h' => [
					['from' => '00', 'till' => '0', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'h00-00',
				'h' => [
					['from' => '00', 'till' => '00', 'step' => '']
				]
			]]],
			// Mixed scheduling weeks and hours. All values are correct.
			[[
				[
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
				]
			]],
			[[
				[
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
				]
			]],
			[[
				[
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
				]
			]],
			// Mixed scheduling months and hours. All values are correct.
			[[
				[
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
						['from' => '1', 'till' => '10', 'step' => '']
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
				]
			]],
			// Scheduling minutes.
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm/1',
				'm' => [
					['from' => '', 'till' => '', 'step' => '1']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm/01',
				'm' => [
					['from' => '', 'till' => '', 'step' => '01']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm/02',
				'm' => [
					['from' => '', 'till' => '', 'step' => '02']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm/58',
				'm' => [
					['from' => '', 'till' => '', 'step' => '58']
				]
			]]],
			[[
				[
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
				]
			]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm1-1',
				'm' => [
					['from' => '1', 'till' => '1', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm1-23',
				'm' => [
					['from' => '1', 'till' => '23', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm01-1',
				'm' => [
					['from' => '01', 'till' => '1', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm01-01',
				'm' => [
					['from' => '01', 'till' => '01', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm01-23',
				'm' => [
					['from' => '01', 'till' => '23', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm0-23',
				'm' => [
					['from' => '0', 'till' => '23', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm00-59/59',
				'm' => [
					['from' => '00', 'till' => '59', 'step' => '59']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm01-59/58',
				'm' => [
					['from' => '01', 'till' => '59', 'step' => '58']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm1-5,33-59',
				'm' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '33', 'till' => '59', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm1-5,28-45/17',
				'm' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '28', 'till' => '45', 'step' => '17']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm1-5,44-45/01',
				'm' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '44', 'till' => '45', 'step' => '01']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm1-5,44-45/1',
				'm' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '44', 'till' => '45', 'step' => '1']
				]
			]]],
			[[
				[
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
				]
			]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm0-0',
				'm' => [
					['from' => '0', 'till' => '0', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm0',
				'm' => [
					['from' => '0', 'till' => '', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm0-00',
				'm' => [
					['from' => '0', 'till' => '00', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm00-0',
				'm' => [
					['from' => '00', 'till' => '0', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm00-00',
				'm' => [
					['from' => '00', 'till' => '00', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 'm1-1,58-59/1,/1,/59',
				'm' => [
					['from' => '1', 'till' => '1', 'step' => ''],
					['from' => '58', 'till' => '59', 'step' => '1'],
					['from' => '', 'till' => '', 'step' => '1'],
					['from' => '', 'till' => '', 'step' => '59']
				]
			]]],
			[[[
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
			]]],
			// Mixed scheduling minutes, hours and weeks.
			[[
				[
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
				]
			]],
			[[
				[
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
				]
			]],
			// Scheduling seconds.
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's/1',
				's' => [
					['from' => '', 'till' => '', 'step' => '1']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's/01',
				's' => [
					['from' => '', 'till' => '', 'step' => '01']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's/02',
				's' => [
					['from' => '', 'till' => '', 'step' => '02']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's/59',
				's' => [
					['from' => '', 'till' => '', 'step' => '59']
				]
			]]],
			[[
				[
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
				]
			]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's1-1',
				's' => [
					['from' => '1', 'till' => '1', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's1-23',
				's' => [
					['from' => '1', 'till' => '23', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's01-1',
				's' => [
					['from' => '01', 'till' => '1', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's01-01',
				's' => [
					['from' => '01', 'till' => '01', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's01-23',
				's' => [
					['from' => '01', 'till' => '23', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's0-23',
				's' => [
					['from' => '0', 'till' => '23', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's00-59/59',
				's' => [
					['from' => '00', 'till' => '59', 'step' => '59']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's01-59/58',
				's' => [
					['from' => '01', 'till' => '59', 'step' => '58']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's1-5,33-59',
				's' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '33', 'till' => '59', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's1-5,28-45/17',
				's' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '28', 'till' => '45', 'step' => '17']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's1-5,44-45/01',
				's' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '44', 'till' => '45', 'step' => '01']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's1-5,44-45/1',
				's' => [
					['from' => '1', 'till' => '5', 'step' => ''],
					['from' => '44', 'till' => '45', 'step' => '1']
				]
			]]],
			[[
				[
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
				]
			]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's0-0',
				's' => [
					['from' => '0', 'till' => '0', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's0-00',
				's' => [
					['from' => '0', 'till' => '00', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's00-0',
				's' => [
					['from' => '00', 'till' => '0', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's00-00',
				's' => [
					['from' => '00', 'till' => '00', 'step' => '']
				]
			]]],
			[[[
				'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
				'interval' => 's1-1,58-59/1,/1,/59',
				's' => [
					['from' => '1', 'till' => '1', 'step' => ''],
					['from' => '58', 'till' => '59', 'step' => '1'],
					['from' => '', 'till' => '', 'step' => '1'],
					['from' => '', 'till' => '', 'step' => '59']
				]
			]]],
			[[[
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
			]]],
			// Mixed scheduling seconds, weeks, minutes and hours. All values are correct.
			[[
				[
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
				]
			]]
		];
	}

	/**
	 * An array of invalid scheduling intervals.
	 */
	public function testInvalidSchedulingProvider() {
		return [
			// Invalid scheduling month days.
			[
				[[
					'interval' => 'md03-02/01',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '03', 'till' => '02', 'step' => '01']
					]
				]],
				'Invalid interval "md03-02/01": starting month day must be less or equal to ending month day.'
			],
			[
				[[
					'interval' => 'md03-02/1',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '03', 'till' => '02', 'step' => '1']
					]
				]],
				'Invalid interval "md03-02/1": starting month day must be less or equal to ending month day.'
			],
			[
				[[
					'interval' => 'md03-02/10',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '03', 'till' => '02', 'step' => '10']
					]
				]],
				'Invalid interval "md03-02/10": starting month day must be less or equal to ending month day.'
			],
			[
				[[
					'interval' => 'md03-02/99',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '03', 'till' => '02', 'step' => '99']
					]
				]],
				'Invalid interval "md03-02/99": starting month day must be less or equal to ending month day.'
			],
			[
				[[
					'interval' => 'md03-02',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '03', 'till' => '02', 'step' => '']
					]
				]],
				'Invalid interval "md03-02": starting month day must be less or equal to ending month day.'
			],
			[
				[[
					'interval' => 'md00-00',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '00', 'till' => '00', 'step' => '']
					]
				]],
				'Invalid interval "md00-00": invalid month day "00".'
			],
			[
				[[
					'interval' => 'md00-99',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '00', 'till' => '99', 'step' => '']
					]
				]],
				'Invalid interval "md00-99": invalid month day "00".'
			],
			[
				[[
					'interval' => 'md1-00',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '1', 'till' => '00', 'step' => '']
					]
				]],
				'Invalid interval "md1-00": invalid month day "00".'
			],
			[
				[[
					'interval' => 'md1-99',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '1', 'till' => '99', 'step' => '']
					]
				]],
				'Invalid interval "md1-99": invalid month day "99".'
			],
			[
				[[
					'interval' => 'md99-99',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '99', 'till' => '99', 'step' => '']
					]
				]],
				'Invalid interval "md99-99": invalid month day "99".'
			],
			[
				[[
					'interval' => 'md99-99/88',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '99', 'till' => '99', 'step' => '88']
					]
				]],
				'Invalid interval "md99-99/88": invalid month day "99".'
			],
			[
				[[
					'interval' => 'md1-31/88',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '1', 'till' => '31', 'step' => '88']
					]
				]],
				'Invalid interval "md1-31/88": invalid month day step "88".'
			],
			[
				[[
					'interval' => 'md99,99-99/88',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '99', 'till' => '', 'step' => ''],
						['from' => '99', 'till' => '99', 'step' => '88']
					]
				]],
				'Invalid interval "md99,99-99/88": invalid month day "99".'
			],
			[
				[[
					'interval' => 'md0,0-0/0',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '0', 'till' => '', 'step' => ''],
						['from' => '0', 'till' => '0', 'step' => '0']
					]
				]],
				'Invalid interval "md0,0-0/0": invalid month day "0".'
			],
			[
				[[
					'interval' => 'md1,1-31/0',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '1', 'till' => '', 'step' => ''],
						['from' => '1', 'till' => '31', 'step' => '0']
					]
				]],
				'Invalid interval "md1,1-31/0": invalid month day step "0".'
			],
			[
				[[
					'interval' => 'md/99',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '', 'till' => '', 'step' => '99']
					]
				]],
				'Invalid interval "md/99": invalid month day step "99".'
			],
			[
				[[
					'interval' => 'md/0',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '', 'till' => '', 'step' => '0']
					]
				]],
				'Invalid interval "md/0": invalid month day step "0".'
			],
			[
				[[
					'interval' => 'md01-01/01',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '01', 'till' => '01', 'step' => '01']
					]
				]],
				'Invalid interval "md01-01/01": invalid month day step "01".'
			],
			[
				[[
					'interval' => 'md02-02/01',
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'md' => [
						['from' => '02', 'till' => '02', 'step' => '01']
					]
				]],
				'Invalid interval "md02-02/01": invalid month day step "01".'
			],
			// Invalid scheduling week days.
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd0-0/0',
					'wd' => [
						['from' => '0', 'till' => '0', 'step' => '0']
					]
				]],
				'Invalid interval "wd0-0/0": invalid week day "0".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd1-1/1',
					'wd' => [
						['from' => '1', 'till' => '1', 'step' => '1']
					]
				]],
				'Invalid interval "wd1-1/1": invalid week day step "1".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd9-9/9',
					'wd' => [
						['from' => '9', 'till' => '9', 'step' => '9']
					]
				]],
				'Invalid interval "wd9-9/9": invalid week day "9".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd9-9',
					'wd' => [
						['from' => '9', 'till' => '9', 'step' => '']
					]
				]],
				'Invalid interval "wd9-9": invalid week day "9".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/9',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '9']
					]
				]],
				'Invalid interval "wd/9": invalid week day step "9".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/0',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '0']
					]
				]],
				'Invalid interval "wd/0": invalid week day step "0".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd9-7',
					'wd' => [
						['from' => '9', 'till' => '7', 'step' => '']
					]
				]],
				'Invalid interval "wd9-7": invalid week day "9".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd7-7/7',
					'wd' => [
						['from' => '7', 'till' => '7', 'step' => '7']
					]
				]],
				'Invalid interval "wd7-7/7": invalid week day step "7".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd/7',
					'wd' => [
						['from' => '', 'till' => '', 'step' => '7']
					]
				]],
				'Invalid interval "wd/7": invalid week day step "7".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'wd6-1/0',
					'wd' => [
						['from' => '6', 'till' => '1', 'step' => '0']
					]
				]],
				'Invalid interval "wd6-1/0": starting week day must be less or equal to ending week day.'
			],
			// Invalid scheduling hours.
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h99',
					'h' => [
						['from' => '99', 'till' => '', 'step' => '']
					]
				]],
				'Invalid interval "h99": invalid hours "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h99-99',
					'h' => [
						['from' => '99', 'till' => '99', 'step' => '']
					]
				]],
				'Invalid interval "h99-99": invalid hours "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/99',
					'h' => [
						['from' => '', 'till' => '', 'step' => '99']
					]
				]],
				'Invalid interval "h/99": invalid hour step "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h99-99/99',
					'h' => [
						['from' => '99', 'till' => '99', 'step' => '99']
					]
				]],
				'Invalid interval "h99-99/99": invalid hours "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h7-0/99',
					'h' => [
						['from' => '7', 'till' => '0', 'step' => '99']
					]
				]],
				'Invalid interval "h7-0/99": starting hour must be less or equal to ending hour.'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h00-24/23',
					'h' => [
						['from' => '00', 'till' => '24', 'step' => '23']
					]
				]],
				'Invalid interval "h00-24/23": invalid hours "24".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h/24',
					'h' => [
						['from' => '', 'till' => '', 'step' => '24']
					]
				]],
				'Invalid interval "h/24": invalid hour step "24".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h23-15/1',
					'h' => [
						['from' => '23', 'till' => '15', 'step' => '1']
					]
				]],
				'Invalid interval "h23-15/1": starting hour must be less or equal to ending hour.'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h00-00/00',
					'h' => [
						['from' => '00', 'till' => '00', 'step' => '00']
					]
				]],
				'Invalid interval "h00-00/00": invalid hour step "00".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'h01-01/01',
					'h' => [
						['from' => '01', 'till' => '01', 'step' => '01']
					]
				]],
				'Invalid interval "h01-01/01": invalid hour step "01".'
			],
			// Mixed week days and hours.
			[
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
				'Invalid interval "wd7-9": invalid week day "9".'
			],
			// Mixed scheduling months, weeks and hours.
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'md01',
					'h' => [
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
				'Invalid interval "wd7-9": invalid week day "9".'
			],
			// Invalid scheduling minutes.
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm99',
					'm' => [
						['from' => '99', 'till' => '', 'step' => '']
					]
				]],
				'Invalid interval "m99": invalid minutes "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm99-99',
					'm' => [
						['from' => '99', 'till' => '99', 'step' => '']
					]
				]],
				'Invalid interval "m99-99": invalid minutes "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm99-99/99',
					'm' => [
						['from' => '99', 'till' => '99', 'step' => '99']
					]
				]],
				'Invalid interval "m99-99/99": invalid minutes "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/99',
					'm' => [
						['from' => '', 'till' => '', 'step' => '99']
					]
				]],
				'Invalid interval "m/99": invalid minute step "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm60-0/99',
					'm' => [
						['from' => '60', 'till' => '0', 'step' => '99']
					]
				]],
				'Invalid interval "m60-0/99": invalid minutes "60".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm59-0/99',
					'm' => [
						['from' => '59', 'till' => '0', 'step' => '99']
					]
				]],
				'Invalid interval "m59-0/99": starting minute must be less or equal to ending minute.'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm00-60',
					'm' => [
						['from' => '00', 'till' => '60', 'step' => '']
					]
				]],
				'Invalid interval "m00-60": invalid minutes "60".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm/60',
					'm' => [
						['from' => '', 'till' => '', 'step' => '60']
					]
				]],
				'Invalid interval "m/60": invalid minute step "60".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm0-2/60',
					'm' => [
						['from' => '0', 'till' => '2', 'step' => '60']
					]
				]],
				'Invalid interval "m0-2/60": invalid minute step "60".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm59-15/1',
					'm' => [
						['from' => '59', 'till' => '15', 'step' => '1']
					]
				]],
				'Invalid interval "m59-15/1": starting minute must be less or equal to ending minute.'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm00-00/00',
					'm' => [
						['from' => '00', 'till' => '00', 'step' => '00']
					]
				]],
				'Invalid interval "m00-00/00": invalid minute step "00".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 'm01-01/01',
					'm' => [
						['from' => '01', 'till' => '01', 'step' => '01']
					]
				]],
				'Invalid interval "m01-01/01": invalid minute step "01".'
			],
			// Invalid scheduling seconds.
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's99',
					's' => [
						['from' => '99', 'till' => '', 'step' => '']
					]
				]],
				'Invalid interval "s99": invalid seconds "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's99-99',
					's' => [
						['from' => '99', 'till' => '99', 'step' => '']
					]
				]],
				'Invalid interval "s99-99": invalid seconds "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's99-99/99',
					's' => [
						['from' => '99', 'till' => '99', 'step' => '99']
					]
				]],
				'Invalid interval "s99-99/99": invalid seconds "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/99',
					's' => [
						['from' => '', 'till' => '', 'step' => '99']
					]
				]],
				'Invalid interval "s/99": invalid second step "99".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's60-0/99',
					's' => [
						['from' => '60', 'till' => '0', 'step' => '99']
					]
				]],
				'Invalid interval "s60-0/99": invalid seconds "60".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's59-0/99',
					's' => [
						['from' => '59', 'till' => '0', 'step' => '99']
					]
				]],
				'Invalid interval "s59-0/99": starting second must be less or equal to ending second.'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's00-60',
					's' => [
						['from' => '00', 'till' => '60', 'step' => '']
					]
				]],
				'Invalid interval "s00-60": invalid seconds "60".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's/60',
					's' => [
						['from' => '', 'till' => '', 'step' => '60']
					]
				]],
				'Invalid interval "s/60": invalid second step "60".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's0-2/60',
					's' => [
						['from' => '0', 'till' => '2', 'step' => '60']
					]
				]],
				'Invalid interval "s0-2/60": invalid second step "60".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's59-15/1',
					's' => [
						['from' => '59', 'till' => '15', 'step' => '1']
					]
				]],
				'Invalid interval "s59-15/1": starting second must be less or equal to ending second.'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's00-00/00',
					's' => [
						['from' => '00', 'till' => '00', 'step' => '00']
					]
				]],
				'Invalid interval "s00-00/00": invalid second step "00".'
			],
			[
				[[
					'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING,
					'interval' => 's01-01/01',
					's' => [
						['from' => '01', 'till' => '01', 'step' => '01']
					]
				]],
				'Invalid interval "s01-01/01": invalid second step "01".'
			]
		];
	}

	/**
	 * @dataProvider testValidFlexibleProvider
	 *
	 * @param array $intervals		An array of valid flexible intervals.
	 */
	public function testParseFlexibleValid(array $intervals) {
		$item_delay_flex_validator = new CItemDelayFlexValidator();
		$this->assertTrue($item_delay_flex_validator->validate($intervals));
	}

	/**
	 * @dataProvider testInvalidFlexibleProvider
	 *
	 * @param array $intervals		An array of invalid flexible intervals to validate.
	 * @param string $error			Error message returned by validator
	 */
	public function testParseFlexibleInvalid(array $intervals, $error) {
		$item_delay_flex_validator = new CItemDelayFlexValidator();
		$this->assertFalse($item_delay_flex_validator->validate($intervals));
		$this->assertEquals($error, $item_delay_flex_validator->getError());
	}

	/**
	 * @dataProvider testValidSchedulingProvider
	 *
	 * @param array $intervals		An array of valid scheduling intervals.
	 */
	public function testParseSchedulingValid(array $intervals) {
		$item_delay_flex_validator = new CItemDelayFlexValidator();
		$this->assertTrue($item_delay_flex_validator->validate($intervals));
	}

	/**
	 * @dataProvider testInvalidSchedulingProvider
	 *
	 * @param array $intervals		An array of invalid flexible intervals to validate.
	 * @param string $error			Error message returned by validator
	 */
	public function testParseSchedulingInvalid(array $intervals, $error) {
		$item_delay_flex_validator = new CItemDelayFlexValidator();
		$this->assertFalse($item_delay_flex_validator->validate($intervals));
		$this->assertEquals($error, $item_delay_flex_validator->getError());
	}
}
