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

class CFlexibleIntervalParserTest extends TestCase {

	/**
	 * An array of flexible intervals and parsed results.
	 */
	public static function dataProvider() {
		return [
			// success
			[
				'10s/7-7,23:59-24:00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '10s/7-7,23:59-24:00',
					'update_interval' => '10s',
					'time_period' => '7-7,23:59-24:00'
				]
			],
			[
				'10/7,0:00-0:01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '10/7,0:00-0:01',
					'update_interval' => '10',
					'time_period' => '7,0:00-0:01'
				]
			],
			[
				'52w/7,00:00-0:01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '52w/7,00:00-0:01',
					'update_interval' => '52w',
					'time_period' => '7,00:00-0:01'
				]
			],
			[
				'{$M}/{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}/{$M}',
					'update_interval' => '{$M}',
					'time_period' => '{$M}'
				]
			],
			[
				'{$M:"context"}/{$M:"context"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M:"context"}/{$M:"context"}',
					'update_interval' => '{$M:"context"}',
					'time_period' => '{$M:"context"}'
				]
			],
			[
				'{$M:";"}/{$M:";"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M:";"}/{$M:";"}',
					'update_interval' => '{$M:";"}',
					'time_period' => '{$M:";"}'
				]
			],
			[
				'{$M:"/"}/{$M:"/"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M:"/"}/{$M:"/"}',
					'update_interval' => '{$M:"/"}',
					'time_period' => '{$M:"/"}'
				]
			],
			[
				'{#A}/{#B}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#A}/{#B}',
					'update_interval' => '{#A}',
					'time_period' => '{#B}'
				]
			],
			[
				'{{#A}.regsub("^([0-9]+)", "{#A}: \1")}/{#B}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#A}.regsub("^([0-9]+)", "{#A}: \1")}/{#B}',
					'update_interval' => '{{#A}.regsub("^([0-9]+)", "{#A}: \1")}',
					'time_period' => '{#B}'
				]
			],
			[
				'{#A}/{{#B}.regsub("^([0-9]+)", "{#B}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#A}/{{#B}.regsub("^([0-9]+)", "{#B}: \1")}',
					'update_interval' => '{#A}',
					'time_period' => '{{#B}.regsub("^([0-9]+)", "{#B}: \1")}'
				]
			],
			[
				'{{#A}.regsub("^([0-9]+)", "{#A}: \1")}/{{#B}.regsub("^([0-9]+)", "{#B}: \1")}', 0,
					['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#A}.regsub("^([0-9]+)", "{#A}: \1")}/{{#B}.regsub("^([0-9]+)", "{#B}: \1")}',
					'update_interval' => '{{#A}.regsub("^([0-9]+)", "{#A}: \1")}',
					'time_period' => '{{#B}.regsub("^([0-9]+)", "{#B}: \1")}'
				]
			],
			[
				'{{#A}.regsub("^([0-9]+)", "{#A}: \1")}/{#B}.regsub("^([0-9]+)", "{#B}: \1")}', 0,
					['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{#A}.regsub("^([0-9]+)", "{#A}: \1")}/{#B}',
					'update_interval' => '{{#A}.regsub("^([0-9]+)", "{#A}: \1")}',
					'time_period' => '{#B}'
				]
			],
			// partial success
			[
				'random text.....52w/7,00:00-0:01....text', 16, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '52w/7,00:00-0:01',
					'update_interval' => '52w',
					'time_period' => '7,00:00-0:01'
				]
			],
			[
				'0/2,0:00-9:000', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '0/2,0:00-9:00',
					'update_interval' => '0',
					'time_period' => '2,0:00-9:00'
				]
			],
			[
				'5/2,1:00-9:20;', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '5/2,1:00-9:20',
					'update_interval' => '5',
					'time_period' => '2,1:00-9:20'
				]
			],
			[
				'0/2,1:00-9:20a', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '0/2,1:00-9:20',
					'update_interval' => '0',
					'time_period' => '2,1:00-9:20'
				]
			],
			[
				'52w/7,00:00-0:010', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '52w/7,00:00-0:01',
					'update_interval' => '52w',
					'time_period' => '7,00:00-0:01'
				]
			],
			[
				'{$M}/{$M}}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M}/{$M}',
					'update_interval' => '{$M}',
					'time_period' => '{$M}'
				]
			],
			[
				'{$M:"context"}/{$M:"context"}}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M:"context"}/{$M:"context"}',
					'update_interval' => '{$M:"context"}',
					'time_period' => '{$M:"context"}'
				]
			],
			[
				'{$M:";"}/{$M:";"}}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M:";"}/{$M:";"}',
					'update_interval' => '{$M:";"}',
					'time_period' => '{$M:";"}'
				]
			],
			[
				'{$M:"/"}/{$M:"/"}}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M:"/"}/{$M:"/"}',
					'update_interval' => '{$M:"/"}',
					'time_period' => '{$M:"/"}'
				]
			],
			// fail
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'10s/', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'10ss/7-7,23:59-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'52w,7,00:00-0:01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'69s', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'69s/', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'20m,7,00:00-001', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1,23:59-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'7-7,23:59-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				';5/1,1:00-9:20', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5a/7,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5 7,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5/ 7,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5 /7,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5/z7,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5\/7,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5/77,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1/1-000,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1/1-1 ,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1/1 ,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1/01,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1/1-07,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1/1-7,,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1 1-7,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1/00-7,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1/1-3-7,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1/001-7,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'1/1--7,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5/7+6,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5/7/6,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5/6a,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5/1-6a,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5/1-60,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5/1-6, 0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5/1-6,,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5/1-6,:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,000:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,00:0-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,0:0-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,0::00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,00::00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,00:000-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,00:0024:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,00:00--24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,00:00 -24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,00:00- 24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,23:59-2400', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,0:00-9:2', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,0:00-111:2', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,1:00-9::20', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,1:00-09::20', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'0/2,00:00-024:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'50/1-?,00:00-23:59', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/11', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-11', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/12-34', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-3', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-,', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1 -3', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1- 3', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-3 ', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/ 1-3', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1,3', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/d-d', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/9-9', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/6-1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-77', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7 ,a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7 , a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1--7,00:01-0:02', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/10-7,23:59-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/77,0:00-0:01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/7-99,00:00-0:01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1 -7,00:00-00:01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1- 7,00:00-00:01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1.7,00:00-00:01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/7,000:00-00:01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,00::00-00:01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,00:,00-00:01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,0:001-0:02', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,0:01 -0:02', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,0:01- 0:02', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/7,0:01-000:02', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/7,0:01-00::02', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/9-7,0:01-00:02', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,00:00-00:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,00:00-24:13', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,25:00-20:13', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,11:60-20:13', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,9:00-7:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,09:00-07:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-7,09:00-07:99', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/7,23:59-23:59', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/7-7,23:59-23:59', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/8-9,23:59-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/8-9,23:59-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/8-9,23:59-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/7-9,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/7-6,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/0,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/1-0,0:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/2,00:00-00:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/2,00:01-00:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/2,5:00-29:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/2,24:00-24:01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/2,24:00-23:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/2,99:00-24:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'5d/7-7,99:99-99:99', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'{/1-7,10:00-11:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'{$/1-7,10:00-11:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'{#/1-7,10:00-11:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'{$M/1-7,10:00-11:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'{$M./1-7,10:00-11:00', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'{$M}/{', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'{$M}/{$', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'{$M}/{$M', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
				]
			],
			[
				'{$M}}/{$M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'update_interval' => '',
					'time_period' => ''
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
		$parser = new CFlexibleIntervalParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'update_interval' => $parser->getUpdateInterval(),
			'time_period' => $parser->getTimePeriod()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
