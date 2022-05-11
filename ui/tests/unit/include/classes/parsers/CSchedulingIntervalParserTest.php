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

class CSchedulingIntervalParserTest extends TestCase {

	/**
	 * An array of time periods and parsed results.
	 */
	public static function dataProvider() {
		return [
			// success
			[
				'md/30', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md/30'
				]
			],
			[
				'md1-31/30', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md1-31/30'
				]
			],
			[
				'md1-1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md1-1'
				]
			],
			[
				'md28-30', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md28-30'
				]
			],
			[
				'md15-30/4', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md15-30/4'
				]
			],
			[
				'md01-31', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md01-31'
				]
			],
			[
				'md1-5,8-31', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md1-5,8-31'
				]
			],
			[
				'md1-5,8-31', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md1-5,8-31'
				]
			],
			[
				'md/30,1-5/4,8-31/23', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md/30,1-5/4,8-31/23'
				]
			],
			[
				'md1-5/4,8-31/23,/30', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md1-5/4,8-31/23,/30'
				]
			],
			[
				'md31-31', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md31-31'
				]
			],
			[
				'md01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md01'
				]
			],
			[
				'md1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md1'
				]
			],
			[
				'md10', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md10'
				]
			],
			[
				'md1,10', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md1,10'
				]
			],
			[
				'md1-31wd1-7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md1-31wd1-7'
				]
			],
			[
				'md05-10wd5', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md05-10wd5'
				]
			],
			[
				'wd/6', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd/6'
				]
			],
			[
				'wd1-7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd1-7'
				]
			],
			[
				'wd1-7/6', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd1-7/6'
				]
			],
			[
				'wd1-1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd1-1'
				]
			],
			[
				'wd7-7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd7-7'
				]
			],
			[
				'wd1-5,6-7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd1-5,6-7'
				]
			],
			[
				'wd/6,1-5/4,2-7/5', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd/6,1-5/4,2-7/5'
				]
			],
			[
				'wd1-5/4,3-7/4,/6', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd1-5/4,3-7/4,/6'
				]
			],
			[
				'wd1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd1'
				]
			],
			[
				'wd7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd7'
				]
			],
			[
				'wd1,7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd1,7'
				]
			],
			[
				'wd1,7,6,2', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd1,7,6,2'
				]
			],
			[
				'h/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h/1'
				]
			],
			[
				'h/01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h/01'
				]
			],
			[
				'h1-1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h1-1'
				]
			],
			[
				'h23-23', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h23-23'
				]
			],
			[
				'h01-1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h01-1'
				]
			],
			[
				'h01-01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h01-01'
				]
			],
			[
				'h01-23', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h01-23'
				]
			],
			[
				'h0-23', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h0-23'
				]
			],
			[
				'h00-23/23', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h00-23/23'
				]
			],
			[
				'h01-23/22', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h01-23/22'
				]
			],
			[
				'h1-5,7-10', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h1-5,7-10'
				]
			],
			[
				'h1-5,7-10/3', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h1-5,7-10/3'
				]
			],
			[
				'h1-5,7-10/03', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h1-5,7-10/03'
				]
			],
			[
				'h0-0', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h0-0'
				]
			],
			[
				'h0-00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h0-00'
				]
			],
			[
				'h00-0', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h00-0'
				]
			],
			[
				'h00-00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h00-00'
				]
			],
			[
				'm/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm/1'
				]
			],
			[
				'm/01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm/01'
				]
			],
			[
				'm/59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm/59'
				]
			],
			[
				'm1-1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm1-1'
				]
			],
			[
				'm59-59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm59-59'
				]
			],
			[
				'm01-1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm01-1'
				]
			],
			[
				'm01-01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm01-01'
				]
			],
			[
				'm01-59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm01-59'
				]
			],
			[
				'm00-59/59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm00-59/59'
				]
			],
			[
				'm01-59/58', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm01-59/58'
				]
			],
			[
				'm1-5,33-59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm1-5,33-59'
				]
			],
			[
				'm1-5,28-45/17', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm1-5,28-45/17'
				]
			],
			[
				'm1-5,44-45/01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm1-5,44-45/01'
				]
			],
			[
				'm1-5,44-45/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm1-5,44-45/1'
				]
			],
			[
				'm0-0', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm0-0'
				]
			],
			[
				'm0', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm0'
				]
			],
			[
				'm0-00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm0-00'
				]
			],
			[
				'm00-0', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm00-0'
				]
			],
			[
				'm00-00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm00-00'
				]
			],
			[
				'm1-1,58-59/1,/1,/59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm1-1,58-59/1,/1,/59'
				]
			],
			[
				'm/30,1-4,05-09,58-59/1,/1,/59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm/30,1-4,05-09,58-59/1,/1,/59'
				]
			],
			[
				's/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's/1'
				]
			],
			[
				's/01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's/01'
				]
			],
			[
				's/59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's/59'
				]
			],
			[
				's1-1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's1-1'
				]
			],
			[
				's59-59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's59-59'
				]
			],
			[
				's01-1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's01-1'
				]
			],
			[
				's01-01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's01-01'
				]
			],
			[
				's01-23', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's01-23'
				]
			],
			[
				's00-59/59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's00-59/59'
				]
			],
			[
				's01-59/58', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's01-59/58'
				]
			],
			[
				's1-5,33-59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's1-5,33-59'
				]
			],
			[
				's1-5,28-45/17', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's1-5,28-45/17'
				]
			],
			[
				's1-5,44-45/01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's1-5,44-45/01'
				]
			],
			[
				's1-5,44-45/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's1-5,44-45/1'
				]
			],
			[
				's0-0', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's0-0'
				]
			],
			[
				's0-00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's0-00'
				]
			],
			[
				's00-0', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's00-0'
				]
			],
			[
				's00-00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's00-00'
				]
			],
			[
				's1-1,58-59/1,/1,/59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's1-1,58-59/1,/1,/59'
				]
			],
			[
				's/30,1-4,05-09,58-59/1,/1,/59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 's/30,1-4,05-09,58-59/1,/1,/59'
				]
			],
			[
				'md01wd1-5/4,3-7/4,/6h1-5,7-10/03', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md01wd1-5/4,3-7/4,/6h1-5,7-10/03'
				]
			],
			[
				'wd1-7/6h1-5,7-10/03', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd1-7/6h1-5,7-10/03'
				]
			],
			[
				'wd/2h/02m1-5,44-45/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd/2h/02m1-5,44-45/1'
				]
			],
			[
				'wd1-2/1,1-2/1h/02', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd1-2/1,1-2/1h/02'
				]
			],
			[
				'md1h1-5,7-10/03', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md1h1-5,7-10/03'
				]
			],
			[
				'md31h/02s/02', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md31h/02s/02'
				]
			],
			[
				'md1,10h/02m/02', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md1,10h/02m/02'
				]
			],
			[
				'md01,10wd1-7,1-1h1-5,7-10/03m59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md01,10wd1-7,1-1h1-5,7-10/03m59'
				]
			],
			[
				'wd1,3,5-7h1-5,7-10/03', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd1,3,5-7h1-5,7-10/03'
				]
			],
			[
				'md01wd1-7,1-1h/02m/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'md01wd1-7,1-1h/02m/1'
				]
			],
			[
				'wd/6,1-5/4,2-7/5h/02', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd/6,1-5/4,2-7/5h/02'
				]
			],
			[
				'h1-5,7-10/03m1-1,58-59/1,/1,/59s/30,1-4,05-09,58-59/1,/1,/59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h1-5,7-10/03m1-1,58-59/1,/1,/59s/30,1-4,05-09,58-59/1,/1,/59'
				]
			],
			[
				'm1-5,44-45/1s/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'm1-5,44-45/1s/1'
				]
			],
			[
				'wd/6,1-5/4,2-7/5h/02m59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'wd/6,1-5/4,2-7/5h/02m59'
				]
			],
			[
				'h1-5,7-10/03m1-1,58-59/1,/1,/59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'h1-5,7-10/03m1-1,58-59/1,/1,/59'
				]
			],
			[
				'{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}'
				]
			],
			[
				'{$M: "context"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M: "context"}'
				]
			],
			[
				'{$M: ";"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M: ";"}'
				]
			],
			[
				'{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#M}'
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}'
				]
			],
			[
				'{$M: "/"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M: "/"}'
				]
			],
			// partial success
			[
				'random text.....md01-31....text', 16, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md01-31'
				]
			],
			[
				'md/30;', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md/30'
				]
			],
			[
				'md/3;', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md/3'
				]
			],
			[
				'md/1-31/31', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md/1'
				]
			],
			[
				'md1000', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md10'
				]
			],
			[
				'wd10', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd1'
				]
			],
			[
				'h000', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h00'
				]
			],
			[
				'h230', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h23'
				]
			],
			[
				'h0-023', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h0-02'
				]
			],
			[
				'h0-000', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h0-00'
				]
			],
			[
				'h000-0', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h00'
				]
			],
			[
				'h00-23 /23', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h00-23'
				]
			],
			[
				'h00-23;h', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h00-23'
				]
			],
			[
				'h00-23wd1-7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h00-23'
				]
			],
			[
				'md1-31h00-23wd1-7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1-31h00-23'
				]
			],
			[
				'm000', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm00'
				]
			],
			[
				'm590', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm59'
				]
			],
			[
				'm0-059', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm0-05'
				]
			],
			[
				'm0-000', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm0-00'
				]
			],
			[
				'm000-0', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm00'
				]
			],
			[
				'm00-59 /59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm00-59'
				]
			],
			[
				'm00-59;m', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm00-59'
				]
			],
			[
				'm00-59md1-31', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm00-59'
				]
			],
			[
				'm00-59wd1-7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm00-59'
				]
			],
			[
				'md1-31m00-59h00-23wd1-7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1-31m00-59'
				]
			],
			[
				's000', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's00'
				]
			],
			[
				's590', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's59'
				]
			],
			[
				's0-000', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's0-00'
				]
			],
			[
				's000-0', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's00'
				]
			],
			[
				's00-59 /59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's00-59'
				]
			],
			[
				's00-59;s', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's00-59'
				]
			],
			[
				's00-59md1-31', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's00-59'
				]
			],
			[
				's00-59wd1-7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's00-59'
				]
			],
			[
				's00-59m00-59h00-23wd1-7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's00-59'
				]
			],
			[
				'md1-3-31/30', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1-3'
				]
			],
			[
				'md/30,1-5/4,8-3100/23', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md/30,1-5/4,8-31'
				]
			],
			[
				'md/30,1-5/4,8 -31/23', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md/30,1-5/4,8'
				]
			],
			[
				'md/30,1-5/4,8+31/23', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md/30,1-5/4,8'
				]
			],
			[
				'md/30;md/25', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md/30'
				]
			],
			[
				'wd/6,1-5/4,2-7/5h/02;h1-5,7-10/03', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd/6,1-5/4,2-7/5h/02'
				]
			],
			[
				'md01-31wd/6,1-5/4,2-7/5h1-5,7-10/03m1-1,58-59/1,/1,/59s/30,1-4,05-09,58-59/1,/1,/59;', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md01-31wd/6,1-5/4,2-7/5h1-5,7-10/03m1-1,58-59/1,/1,/59s/30,1-4,05-09,58-59/1,/1,/59'
				]
			],
			[
				'md31w7h23m59s99', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md31'
				]
			],
			[
				'md31w7h23m99', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md31'
				]
			],
			[
				'md4h23m99', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md4h23'
				]
			],
			[
				'md1-31wd7h', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1-31wd7'
				]
			],
			[
				'md1-31wd9', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1-31'
				]
			],
			[
				'md/30,1-5/4,8888-31/23', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md/30,1-5/4'
				]
			],
			[
				'md1-5,8--31', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1-5,8'
				]
			],
			[
				'md1-5,,8-31', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1-5'
				]
			],
			[
				'm00-59/', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm00-59'
				]
			],
			[
				's00-59/', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's00-59'
				]
			],
			[
				'h00-23/', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h00-23'
				]
			],
			[
				'h1-', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h1'
				]
			],
			[
				'h00-23md1-31', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h00-23'
				]
			],
			[
				'wd1-5/4,/', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd1-5/4'
				]
			],
			[
				'wd1-7/6md', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd1-7/6'
				]
			],
			[
				'wd1--7/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd1'
				]
			],
			[
				'wd1-7/', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd1-7'
				]
			],
			[
				'md1-5/4,', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1-5/4'
				]
			],
			[
				'md1-5/4,/', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1-5/4'
				]
			],
			[
				'wd1-5/4,', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd1-5/4'
				]
			],
			[
				'md28-31/', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md28-31'
				]
			],
			[
				'md01--31/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md01'
				]
			],
			[
				'md1/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1'
				]
			],
			[
				'md01-01/01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md01-01'
				]
			],
			[
				'h01-01/01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h01-01'
				]
			],
			[
				'm01-01/01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm01-01'
				]
			],
			[
				's01-01/01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's01-01'
				]
			],
			[
				'h00-00/00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'h00-00'
				]
			],
			[
				'm00-00/00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm00-00'
				]
			],
			[
				's00-00/00', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's00-00'
				]
			],
			[
				'wd1-7/06', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd1-7'
				]
			],
			[
				'wd1-1/1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd1-1'
				]
			],
			[
				'md1-31/0000', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1-31'
				]
			],
			[
				'md1-31/001', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1-31'
				]
			],
			[
				'wd5md7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd5'
				]
			],
			[
				'm5md7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm5'
				]
			],
			[
				's5m7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's5'
				]
			],
			[
				's6w7', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's6'
				]
			],
			[
				's7md31', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's7'
				]
			],
			[
				'm6wd1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm6'
				]
			],
			[
				'md1md2', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1'
				]
			],
			[
				'wd1wd2', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd1'
				]
			],
			[
				'm1m2', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm1'
				]
			],
			[
				's1s2', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's1'
				]
			],
			[
				'md1,2md3', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'md1,2'
				]
			],
			[
				'wd1,2md3', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd1,2'
				]
			],
			[
				'wd3,4wd5', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'wd3,4'
				]
			],
			[
				'm10,20wd5', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm10,20'
				]
			],
			[
				'm30,40md1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm30,40'
				]
			],
			[
				's10,20m15', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's10,20'
				]
			],
			[
				's30,40wd2', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's30,40'
				]
			],
			[
				's50,59md1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's50,59'
				]
			],
			[
				's50,55s59', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 's50,55'
				]
			],
			[
				'm10,20m30', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'm10,20'
				]
			],
			// fail
			[
				'md', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wd', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'h', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'm', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				's', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md;', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'mdm', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'mdw', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md/a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md/', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md /30', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md0/0', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md03-02/1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md00-99', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md99-99', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md99-99/88', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md0,0-0/0', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md/0', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md/99', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wd;', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wdm', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wd/', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wd/a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'ha', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'h;', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'h/', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'h,', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'ma', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'm;', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'm/', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'ss', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				's;', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				's/', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'zmd28-30', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md999999', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md000000', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md01-0031/1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md0001-1000/5', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md0-1000/5', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md001', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md/003;', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md/000', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'md/00-31', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wd0-0/0', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wd9-9/9', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wd/9', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wd/0', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wd/7', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wd001-7', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'wd0000-1000/5', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'h99', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'h99-99', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'h7-0', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'h00-24/23', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'h/24', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'h23-15/1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'm99', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'm99-99', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'm7-0', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'm00-60/59', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'm/60', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'm23-15/1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				's99', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				's99-99', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				's7-0', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				's00-60/59', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				's/60', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				's23-15/1', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			// User macros are not enabled.
			[
				'{$M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{$M: "context"}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{$M: ";"}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{$M: "/"}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			// Lld macros are not enabled.
			[
				'{#M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
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
		$parser = new CSchedulingIntervalParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
