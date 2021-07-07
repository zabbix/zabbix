<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

class testCalculatedFormula extends CWebTest {

	public $url;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	public function getCommonValidationData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'formula' => '',
					'error' => 'Incorrect value for field "Formula": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'something',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "something".'
				]
			],
			// abs() function.
			[
				[
					'formula' => 'abs(change(/Trapper/trap[1]))'
				]
			],
			[
				[
					'formula' => "abs(change(//trap[1]))"
				]
			],
			// acos() function.
			[
				[
					'formula' => "acos(last(//trap))"
				]
			],
			// asin() function.
			[
				[
					'formula' => "asin(last(//trap))"
				]
			],
			// avg() function.
			[
				[
					'formula' => 'avg(/host/trap,99h)'
				]
			],
			[
				[
					'formula' => 'avg(/host/trap,"{$USERMACRO}h")'
				]
			],
			[
				[
					'formula' => 'avg(/host/trap,{$USERMACRO})'
				]
			],
			[
				[
					'formula' => "avg(//trap,19)"
				]
			],
			// atan() function.
			[
				[
					'formula' => "atan(last(//trap))"
				]
			],
			// bitand() function.
			[
				[
					'formula' => "bitand(last(//key,#5:now-24h),123)"
				]
			],
			[
				[
					'formula' => 'bitand(last(/host/key,"{$USERMACRO}:now-24h"),123)'
				]
			],
			// cbrt() function.
			[
				[
					'formula' => "cbrt(last(//trap, {\$USERMACRO}))"
				]
			],
			// ceil() function.
			[
				[
					'formula' => "ceil(last(//trap,{\$USERMACRO}))"
				]
			],
			// change() function.
			[
				[
					'formula' => "change(//trap[1])"
				]
			],
			// count() function.
			[
				[
					'formula' => 'count(/host/trap,5:now/d)'
				]
			],
			[
				[
					'formula' => 'count(/host/trap,#4:now-5h,"eq")'
				]
			],
			[
				[
					'formula' => 'count(/host/trap,1m,,"0")'
				]
			],
			[
				[
					'formula' => 'count(/host/trap,"{$USERMACRO}m",,"0")'
				]
			],
			[
				[
					'formula' => 'count(/host/trap,#10,"like",7)'
				]
			],
			[
				[
					'formula' => 'count(/host/trap,#10,"ge","99")'
				]
			],
			// cos() function.
			[
				[
					'formula' => "cos(last(//trap,{\$USERMACRO}))"
				]
			],
			// cosh() function.
			[
				[
					'formula' => "cosh(last(//trap))"
				]
			],
			// cot() function.
			[
				[
					'formula' => "cot(last(//trap))"
				]
			],
			// degrees() function.
			[
				[
					'formula' => "degrees(last(//trap))"
				]
			],
			// time and date functions.
			[
				[
					'formula' => 'date()'
				]
			],
			[
				[
					'formula' => 'dayofmonth()'
				]
			],
			[
				[
					'formula' => 'dayofweek()'
				]
			],
			[
				[
					'formula' => 'time()'
				]
			],
			// e() function.
			[
				[
					'formula' => 'e()'
				]
			],
			// exp() function.
			[
				[
					'formula' => "exp(last(//trap))"
				]
			],
			// expm1() function.
			[
				[
					'formula' => "expm1(last(//trap,{\$USERMACRO}))"
				]
			],
			// find() function.
			[
				[
					'formula' => 'find(/host/trap,,"iregexp",7)'
				]
			],
			[
				[
					'formula' => 'find(/host/trap,,"iregexp")'
				]
			],
			[
				[
					'formula' => 'find(/host/trap,,,"5")'
				]
			],
			[
				[
					'formula' => 'find(/host/trap,#10,"ne","test")'
				]
			],
			[
				[
					'formula' => 'find(/host/trap,#10,"gt",10)'
				]
			],
			[
				[
					'formula' => 'find(/host/trap,#10,"lt",4)'
				]
			],
			[
				[
					'formula' => 'find(/host/trap,#10,"le","5")'
				]
			],
			[
				[
					'formula' => 'find(/host/trap,#10,"bitand",777)'
				]
			],
			[
				[
					'formula' => 'find(/host/trap,#10,"regexp","expression")'
				]
			],
			[
				[
					'formula' => 'find(/host/trap,#10,"iregexp","20")'
				]
			],
			// floor() function.
			[
				[
					'formula' => "floor(last(//trap,{\$USERMACRO}))"
				]
			],
			// forecast() function.
			[
				[
					'formula' => 'forecast(/host/trap,#5,25h)'
				]
			],
			[
				[
					'formula' => 'forecast(/host/trap,#5,"25h","linear")'
				]
			],
			[
				[
					'formula' => 'forecast(/host/trap,#4:now-5h,25h,"polynomial6")'
				]
			],
			[
				[
					'formula' => 'forecast(/host/trap,#5,25h,"exponential", "value")'
				]
			],
			[
				[
					'formula' => 'forecast(/host/trap,"{$USERMACRO}:now/d",25h,"logarithmic","min")'
				]
			],
			[
				[
					'formula' => 'forecast(/host/trap,#5,25h,"power","delta")'
				]
			],
			[
				[
					'formula' => 'forecast(/host/trap,5:now/d,25h, ,"avg")'
				]
			],
			[
				[
					'formula' => 'forecast(/host/trap,#5,25h,,)'
				]
			],
			[
				[
					'formula' => 'forecast(/host/trap,#1,0)'
				]
			],
			[
				[
					'formula' => "forecast(//trap,#1,0)"
				]
			],
			// fuzzytime() function.
			[
				[
					'formula' => 'fuzzytime(/host/trap,"{$USERMACRO}s")'
				]
			],
			[
				[
					'formula' => 'fuzzytime(/host/trap,"5h")'
				]
			],
			[
				[
					'formula' => 'fuzzytime(/host/trap,5h)'
				]
			],
			// last() function.
			[
				[
					'formula' => 'last(/host/trap)'
				]
			],
			[
				[
					'formula' => 'last(/host/trap,#3)'
				]
			],
			[
				[
					'formula' => 'last(/host/trap,{$USERMACRO})'
				]
			],
			// log() function.
			[
				[
					'formula' => "log(last(//trap))"
				]
			],
			// log10() function.
			[
				[
					'formula' => "log10(last(//trap,{\$USERMACRO}))"
				]
			],
			// length() function
			[
				[
					'formula' => 'length(last(/host/key))'
				]
			],
			// logeventid() function.
			[
				[
					'formula' => 'logeventid(/host/trap)'
				]
			],
			[
				[
					'formula' => 'logeventid(/Trapper/trap[4],,"^error")'
				]
			],
			// logseverity() function.
			[
				[
					'formula' => 'logseverity(/Trapper/trap[4],)'
				]
			],
			// logsource() function.
			[
				[
					'formula' => 'logsource(/Trapper/trap[4],#3:now-1h,"^error")'
				]
			],
			[
				[
					'formula' => 'logsource(/Trapper/trap[4],"{$USERMACRO}:now-1h","^error")'
				]
			],
			// max() function.
			[
				[
					'formula' => 'max(/host/trap,1w)'
				]
			],
			// min() function.
			[
				[
					'formula' => "min(//trap,\"#4:now-{\$USERMACRO}m\")"
				]
			],
			// mod() function.
			[
				[
					'formula' => "mod(last(//trap),2s)"
				]
			],
			// nodata() function.
			[
				[
					'formula' => 'nodata(/host/trap,30d)'
				]
			],
			[
				[
					'formula' => 'nodata(/host/trap,"30d")'
				]
			],
			// now() function.
			[
				[
					'formula' => 'now()'
				]
			],
			// percentile() function.
			[
				[
					'formula' => 'percentile(/host/trap,#4:now-5h,0)'
				]
			],
			// pi() function.
			[
				[
					'formula' => 'pi()'
				]
			],
			// power() function.
			[
				[
					'formula' => "power(last(//trap,#1),2)"
				]
			],
			// rand()function.
			[
				[
					'formula' => 'rand()'
				]
			],
			// radians()function.
			[
				[
					'formula' => "radians(last(//trap))"
				]
			],
			// round()function.
			[
				[
					'formula' => "round(last(//trap),2)"
				]
			],
			// sum() function.
			[
				[
					'formula' => 'sum(/host/trap,5:now/d)'
				]
			],
			[
				[
					'formula' => 'sum(/host/trap,"5:{$USERMACRO}")'
				]
			],
			[
				[
					'formula' => 'sum(/host/trap,"5:now/{$USERMACRO}")'
				]
			],
			// signum()function.
			[
				[
					'formula' => "signum(last(//trap,{\$USERMACRO}))"
				]
			],
			// sin()function.
			[
				[
					'formula' => "sin(last(//trap))"
				]
			],
			// sinh()function.
			[
				[
					'formula' => "sinh(last(//trap))"
				]
			],
			// sqrt()function.
			[
				[
					'formula' => "sqrt(last(//trap,{\$USERMACRO}))"
				]
			],
			// tan()function.
			[
				[
					'formula' => "tan(last(//trap,{\$USERMACRO}))"
				]
			],
			// timeleft() function.
			[
				[
					'formula' => 'timeleft(/host/trap,19s,0)'
				]
			],
			[
				[
					'formula' => 'timeleft(/host/trap,"#6:now-{$USERMACRO}",20G,"exponential")'
				]
			],
			[
				[
					'formula' => 'timeleft(/host/trap,#6:now-6h,20G,"logarithmic")'
				]
			],
			[
				[
					'formula' => 'timeleft(/host/trap,"{$USERMACRO}:now-6h",20G,"power")'
				]
			],
			// truncate() function
			[
				[
					'formula' => "truncate(last(//trap),6)"
				]
			],
			// trendavg() function.
			[
				[
					'formula' => 'trendavg(/host/item,"1M:now/M-{$USERMACRO}")'
				]
			],
			[
				[
					'formula' => 'trendavg(/host/key,3600:now-3600)'
				]
			],
			// trendcount() function.
			[
				[
					'formula' => 'trendcount(/host/item,1h:now/h)'
				]
			],
			[
				[
					'formula' => 'trendcount(/host/key,3600:now-3600)'
				]
			],
			// trendmax() function.
			[
				[
					'formula' => 'trendmax(/host/item,7d:now/w)'
				]
			],
			[
				[
					'formula' => 'trendmax(/host/key,3600:now-3600)'
				]
			],
			// trendmin() function.
			[
				[
					'formula' => 'trendmin(/host/item,7d:now/w-1w)'
				]
			],
			[
				[
					'formula' => 'trendmin(/host/key,3600:now-3600)'
				]
			],
			[
				[
					'formula' => 'trendmin(/host/key,"3600:{$USERMACRO}-3600")'
				]
			],
			// trendsum() function.
			[
				[
					'formula' => 'trendsum(/host/item,60m:now/h)'
				]
			],
			[
				[
					'formula' => 'trendsum(/host/key,3600:now-3600)'
				]
			],
			[
				[
					'formula' => 'trendsum(/host/key,"3600:{$USERMACRO}")'
				]
			],
			// Functions validation.
			// abs() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'abs',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "abs".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "Abs(change(/Trapper/trap[1]))",
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "Abs(change(/Trapper/trap[1]))".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'abs(/test/key)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "abs".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "abs(change(//trap[1]),1h:now/h)",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"abs(change(//trap[1]),1h:now/h)\"."
				]
			],
			// acos() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => "Acos(last(//trap))",
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "Acos(last(//trap))".'
				]
			],
			// avg() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'avg()',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "avg".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'avg(/host/trap,{#LLD}h)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "avg(/host/trap,{#LLD}h)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'avg(/host/trap,"30")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "avg".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'avg(/host/trap,"30s")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "avg".'
				]
			],
			// bitand() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'bitand(last(/*/key,1h:now/h))',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "bitand".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "bitand(last(/host/key,#5:now-#5),123)",
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "bitand(last(/host/key,#5:now-#5),123)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "bitand(last(//key,:now-24h),123)",
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "bitand(last(//key,:now-24h),123)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'bitand(/*/key,1h:now/h,123)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "bitand".'
				]
			],
			// change() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'change(/Trapper/trap[1],,)',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "change".'
				]
			],
			// count() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'count(/host/trap,999999999999999)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "count".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'count(/host/trap,"99m")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "count".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'count(/host/key,"3600:now-3600")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "count".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'count(/host/trap,:now/d)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "count(/host/trap,:now/d)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'count(/host/trap,:now-5h,"eq")',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "count(/host/trap,:now-5h,"eq")".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'count(/host/trap,#4:now-5h,"1","eq")',
					'error' => 'Invalid parameter "/1/params": invalid third parameter in function "count".'
				]
			],
			// cot() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => "coT(last(//trap))",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"coT(last(//trap))\"."
				]
			],
			// degrees() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => "Degrees(last(//trap))",
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "Degrees(last(//trap))".'
				]
			],
			// date() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'date(/host/trap)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "date".'
				]
			],
			// dayofmonth() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'dayofmonth(1)',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "dayofmonth".'
				]
			],
			// dayofweek() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'dayofweek',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "dayofweek".'
				]
			],
			// find() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'find(/host/trap,1M)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "find".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'FIND(/host/trap,#10,"le","5")',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "FIND(/host/trap,#10,"le","5")".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'find(/host/trap,#4:now-5h,eq,1)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "find(/host/trap,#4:now-5h,eq,1)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'find(/host/trap,#4:now-5h,"test",1)',
					'error' => 'Invalid parameter "/1/params": invalid third parameter in function "find".'
				]
			],
			// forecast() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,#77)',
					'error' => 'Invalid parameter "/1/params": mandatory parameter is missing in function "forecast".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,:now/d,25h,"logarithmic","min")',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "forecast(/host/trap,:now/d,25h,"logarithmic","min")".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,":now/d",25h,"logarithmic","min")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "forecast".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,#7,,)',
					'error' => 'Invalid parameter "/1/params": invalid third parameter in function "forecast".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,0)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "forecast".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,#1,"test")',
					'error' => 'Invalid parameter "/1/params": invalid third parameter in function "forecast".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,#5,25h,"")',
					'error' => 'Invalid parameter "/1/params": invalid fourth parameter in function "forecast".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,#5,25h,"polynomial7")',
					'error' => 'Invalid parameter "/1/params": invalid fourth parameter in function "forecast".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,#5,25h,"polynomial1","test")',
					'error' => 'Invalid parameter "/1/params": invalid fifth parameter in function "forecast".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,#5,25h,"polynomial1","")',
					'error' => 'Invalid parameter "/1/params": invalid fifth parameter in function "forecast".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/*/trap,#1,0)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "forecast".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,"#4:now-5h",25h)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "forecast".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'forecast(/host/trap,"5:now/d","25h")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "forecast".'
				]
			],
			// fuzzytime() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'fuzzytime(/host/trap)',
					'error' => 'Invalid parameter "/1/params": mandatory parameter is missing in function "fuzzytime".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'fuzzytime(/host/trap,test)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "fuzzytime(/host/trap,test)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'fuzzytime(/*/trap,65w)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "fuzzytime".'
				]
			],
			// last() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'last()',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "last".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'last(/host/trap,7)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "last".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'last(/host/trap,7s)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "last".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'last(/host/trap,"#3")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "last".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'last(/*/trap,#3:now-1d)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "last".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'last(/host/trap,,)',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "last".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'last(/*/trap,#3:now-1d)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "last".'
				]
			],
			// length() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'length(/host/trap)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "length".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'length(/host/trap,7d)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "length".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'length(last(/host/trap,7s))',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "last".'
				]
			],
			// logeventid() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logeventid(/Trapper/trap[4],^error)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "logeventid(/Trapper/trap[4],^error)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logeventid(/Trapper/trap[4],1)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "logeventid".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logeventid(/*/trap)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "logeventid".'
				]
			],
			// logseverity() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logseverity(/host/key,123)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "logseverity".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logseverity(/Trapper/trap[4],^error)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "logseverity(/Trapper/trap[4],^error)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logseverity(/Trapper/trap[4],,"^error")',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "logseverity".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logseverity(/Trapper/trap[4],1)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "logseverity".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logseverity(/*/key)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "logseverity".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logseverity(/Trapper/trap[4],"High")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "logseverity".'
				]
			],
			// logsource() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logsource(/*/trap)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "logsource".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logsource(/Trapper/trap[4],#3:now-#3,"^error")',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "logsource(/Trapper/trap[4],#3:now-#3,"^error")".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logsource(/Trapper/trap[4],^error)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "logsource(/Trapper/trap[4],^error)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'logsource(/Trapper/trap[4],#2,^error)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "logsource(/Trapper/trap[4],#2,^error)".'
				]
			],
			// max() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'max(/host/trap,,)',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "max".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'max(/host/trap,#3d:now-d)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "max(/host/trap,#3d:now-d)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'max(/host/trap,#3d:now-{$USERMACRO})',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "max(/host/trap,#3d:now-{$USERMACRO})".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'max(/host/trap,#3d:now-{#LLD})',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "max(/host/trap,#3d:now-{#LLD})".'
				]
			],
			// min() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'min(/host/trap,1M)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "min".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'min(/*/trap,#4:now-1m)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "min".'
				]
			],
			// nodata() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'nodata',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "nodata".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'nodata()',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "nodata".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'nodata(/host/trap,0s)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "nodata".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'nodata(/*/trap,30)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "nodata".'
				]
			],
			// now() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'now',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "now".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'now(/host/trap)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "now".'
				]
			],
			// percentile() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'percentile(/host/trap,,5)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "percentile".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'percentile(/host/trap,test,test)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "percentile(/host/trap,test,test)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'percentile(/*/trap,#5,100)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "percentile".'
				]
			],
			// sum() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'sum(/host/trap)',
					'error' => 'Invalid parameter "/1/params": mandatory parameter is missing in function "sum".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'sum(/host/trap,":now/d")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "sum".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'sum(/host/trap,:now/d)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "sum(/host/trap,:now/d)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'sum(/host/trap,,)',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "sum".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'sum(/host/trap,#3d:now-d)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "sum(/host/trap,#3d:now-d)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'sum(/host/trap,60:now/60)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "sum(/host/trap,60:now/60)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'sum(/host/trap,a)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "sum(/host/trap,a)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'sum(/host/trap,1Y)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "sum(/host/trap,1Y)".'
				]
			],
			// time() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'Date()',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "Date()".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'Dayofweek()',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "Dayofweek()".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'time(/host/trap)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "time".'
				]
			],
			// timeleft() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'timeleft(/host/trap,5,,"logarithmic")',
					'error' => 'Invalid parameter "/1/params": invalid third parameter in function "timeleft".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'timeleft(/host/trap,,20G,"power")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "timeleft".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'timeleft(/host/trap,5M,"20G","power")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "timeleft".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'timeleft(/host/trap,5,20G,"test")',
					'error' => 'Invalid parameter "/1/params": invalid fourth parameter in function "timeleft".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'timeleft(/*/trap,#100,1M)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "timeleft".'
				]
			],
			// trendavg() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendavg(/host/item)',
					'error' => 'Invalid parameter "/1/params": mandatory parameter is missing in function "trendavg".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendavg(/host/key,:now-3600)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "trendavg(/host/key,:now-3600)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendavg(/host/key,":now-3600")',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendavg".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendavg(/host/key,30m:now-30m)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendavg".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendavg(/host/item,,)',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "trendavg".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendavg(/host/item,0)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendavg".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendavg(/host/item,-1h)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "trendavg(/host/item,-1h)".'
				]
			],
			// trendcount() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendcount(/host/item)',
					'error' => 'Invalid parameter "/1/params": mandatory parameter is missing in function "trendcount".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendcount(/host/key,30:now-30)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendcount".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendcount(/host/key,0:now-0h)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendcount".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendcount(/host/item,0)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendcount".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendcount(/host/item,-1h)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "trendcount(/host/item,-1h)".'
				]
			],
			// trendmax() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmax(/host/item,1h)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendmax".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmax(/host/key,30s:now-30s)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendmax".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmax(/host/item,:now/w)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "trendmax(/host/item,:now/w)".'
				]
			],

			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmax(/host/item,0d:now/d)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendmax".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmax(/host/item,0)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendmax".'
				]
			],
			// trendmin() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmin(/host/item)',
					'error' => 'Invalid parameter "/1/params": mandatory parameter is missing in function "trendmin".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmin(/host/key,59m:now-59m)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendmin".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmin(/host/key,now/d-2d)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "trendmin(/host/key,now/d-2d)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmin(/host/item,,)',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "trendmin".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmin(/host/item,1h)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendmin".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmin(/host/item,0)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendmin".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmin(/host/item,-1h)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "trendmin(/host/item,-1h)".'
				]
			],
			// trendsum() function validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendsum(/host/item)',
					'error' => 'Invalid parameter "/1/params": mandatory parameter is missing in function "trendsum".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendsum(/host/key,59:now-59)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendsum".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendsum(/host/key,:now/d-2d)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "trendsum(/host/key,:now/d-2d)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendsum(/host/item,,)',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "trendsum".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendsum(/host/item,1h)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "trendsum".'
				]
			],
			// Deprecated functions validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => "abschange(//trap)",
					'error' => 'Invalid parameter "/1/params": unknown function "abschange".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'regexp(/*/trap,"test")',
					'error' => 'Invalid parameter "/1/params": unknown function "regexp".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'regexp(/*/trap)',
					'error' => 'Invalid parameter "/1/params": unknown function "regexp".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'iregexp(/*/trap,"test")',
					'error' => 'Invalid parameter "/1/params": unknown function "iregexp".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'iregexp(/*/trap)',
					'error' => 'Invalid parameter "/1/params": unknown function "iregexp".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'prev(/*/trap)',
					'error' => 'Invalid parameter "/1/params": unknown function "prev".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'regexp(/*/trap)',
					'error' => 'Invalid parameter "/1/params": unknown function "regexp".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'regexp(/*/trap,"pattern",50s)',
					'error' => 'Invalid parameter "/1/params": unknown function "regexp".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'str(/*/trap)',
					'error' => 'Invalid parameter "/1/params": unknown function "str".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'str(/*/trap,"pattern",50s)',
					'error' => 'Invalid parameter "/1/params": unknown function "str".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'strlen(/*/trap)',
					'error' => 'Invalid parameter "/1/params": unknown function "strlen".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'strlen(/*/trap,"pattern",#5)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "strlen(/*/trap,"pattern",#5)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trenddelta(/*/trap)',
					'error' => 'Invalid parameter "/1/params": unknown function "trenddelta".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trenddelta(/*/trap,1h,now/h)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "trenddelta(/*/trap,1h,now/h)".'
				]
			],
			// foreach() aggregated functions.
			[
				[
					'formula' => 'sum(last_foreach(/*/trap))'
				]
			],
			[
				[
					'formula' => 'avg(sum_foreach(/*/trap,20s))'
				]
			],
			[
				[
					'formula' => 'min(avg_foreach(/host/key[*,param],{$USERMACRO}))'
				]
			],
			[
				[
					'formula' => 'avg(count_foreach(/*/trap?[tag="tag1"],99h))'
				]
			],
			[
				[
					'formula' => 'max(min_foreach(/*/trap?[group="Servers"],6))'
				]
			],
			[
				[
					'formula' => 'min(max_foreach(/*/trap,20s))'
				]
			],
			// foreach() aggregated functions validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'last_foreach(/*/trap)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "last_foreach".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'sum_foreach(/*/trap,20s)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "sum_foreach".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'avg_foreach(/host/key[*,param],19)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "avg_foreach".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'min(Avg_foreach(/host/key[*,param],{$USERMACRO}))',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "min(Avg_foreach(/host/key[*,param],{$USERMACRO}))".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'count_foreach(/*/trap?[tag="tag1"],99h)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "count_foreach".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'min_foreach(/*/trap?[group="Servers"],6)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "min_foreach".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'max_foreach(/*/trap,20s)',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "max_foreach".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'last_foreach(/host/key,{$PERIOD}:now-1d)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "last_foreach".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'last_foreach(/host/key,"{$PERIOD}:now-1d")',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "last_foreach".'
				]
			],
			// Aggregated math functions.
			[
				[
					'formula' => 'countunique(/host/trap,60s)'
				]
			],
			[
				[
					'formula' => 'countunique(/host/trap,60s,"eq",1)'
				]
			],
			[
				[
					'formula' => 'countunique(/host/trap,60s,"like","test")'
				]
			],
			[
				[
					'formula' => "first(//trap,60)"
				]
			],
			[
				[
					'formula' => "kurtosis(//trap,60d)"
				]
			],
			[
				[
					'formula' => "mad(//trap,60w)"
				]
			],
			[
				[
					'formula' => "skewness(//trap,60h)"
				]
			],
			[
				[
					'formula' => "stddevpop(//trap,{\$USERMACRO})"
				]
			],
			[
				[
					'formula' => "stddevsamp(//trap,{\$USERMACRO})"
				]
			],
			[
				[
					'formula' => "sumofsquares(//trap,#6)"
				]
			],
			[
				[
					'formula' => "varpop(//trap,#1:now-1d)"
				]
			],
			[
				[
					'formula' => "varsamp(//trap,{\$USERMACRO})"
				]
			],
			// Aggregated math functions validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => "countunique(//trap)",
					'error' => 'Invalid parameter "/1/params": mandatory parameter is missing in function "countunique".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'countunique(/host/trap,60s,"test",1)',
					'error' => 'Invalid parameter "/1/params": invalid third parameter in function "countunique".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'countunique(/host/trap,60s,like,1)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "countunique(/host/trap,60s,like,1)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "first(//trap,60s,)",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "first".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "Kurtosis(//trap,60d)",
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "Kurtosis(//trap,60d)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'kurtosis(/*/trap,60s)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "kurtosis".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'mad()',
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "mad".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "Mad(//trap,60w)",
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "Mad(//trap,60w)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'skewness(/trap)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "skewness(/trap)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'stddevpop(trap)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "stddevpop(trap)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "sumofsquares(//trap,{TEST})",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"sumofsquares(//trap,{TEST})\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "varpop(//trap,1M:now/M-1y)",
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "varpop".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "varpop(//trap,#1:now-#1)",
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "varpop(//trap,#1:now-#1)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "varsamp(//trap,{TEST})",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"varsamp(//trap,{TEST})\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "varsamp(//trap)",
					'error' => 'Invalid parameter "/1/params": mandatory parameter is missing in function "varsamp".'
				]
			],
			// String  functions.
			[
				[
					'formula' => "ascii(last(//trap_text))"
				]
			],
			[
				[
					'formula' => "bitlength(last(//trap_text))"
				]
			],
			[
				[
					'formula' => "char(last(//trap))=\"d\""
				]
			],
			[
				[
					'formula' => "concat(last(//trap_text),\"test\")=\"testtest\""
				]
			],
			[
				[
					'formula' => "concat(last(//trap_text),123)=\"test123\""
				]
			],
			[
				[
					'formula' => "concat(last(//trap_text),\"#1\")=\"test123\""
				]
			],
			[
				[
					'formula' => "insert(last(//trap_text),2,1,\"ab\")=\"Zabbix\""
				]
			],
			[
				[
					'formula' => "left(last(//trap_text),3)=\"Zab\""
				]
			],
			[
				[
					'formula' => "ltrim(last(//trap_text),\"T\")=\"Zabbix\""
				]
			],
			[
				[
					'formula' => "ltrim(last(//trap_text))=\"Zabbix\""
				]
			],
			[
				[
					'formula' => "bytelength(last(//trap_text))"
				]
			],
			[
				[
					'formula' => "repeat(last(//trap_text),2)=\"ZabbixZabbix\""
				]
			],
			[
				[
					'formula' => "replace(last(//trap_text),\"ix\",\"aaah\")=\"Zabbaaah\""
				]
			],
			[
				[
					'formula' => "replace(last(//trap_text),\"\",\"\")=\"Zabbaaah\""
				]
			],
			[
				[
					'formula' => "right(last(//trap_text),3)=\"bix\""
				]
			],
			[
				[
					'formula' => "rtrim(last(//trap_text),\"z\")=\"Test\""
				]
			],
			[
				[
					'formula' => "rtrim(last(//trap_text))=\"Test\""
				]
			],
			[
				[
					'formula' => "mid(last(//trap_text),2,4)=\"abbi\""
				]
			],
			[
				[
					'formula' => "trim(last(//trap_text))=\"Zabbix\""
				]
			],
			[
				[
					'formula' => "trim(last(//trap_text),\"t\")=\"Zabbix\""
				]
			],
			// String  functions validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => "ascii(//trap_text)",
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "ascii".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "ASCII(//trap_text)",
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "ASCII(//trap_text)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "bitlength(//trap_text)",
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "bitlength".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "char(//trap)=\"d\"",
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "char".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "concat(last(//trap_text))=\"testtest\"",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "concat".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "concat(last(//trap_text),#1)=\"testtest\"",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"concat(last(//trap_text),#1)=\"testtest\"\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "insert(last(//trap_text),2)=\"Zabbix\"",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "insert".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "insert(last(//trap_text),2,1)=\"Zabbix\"",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "insert".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "insert(last(//trap_text),2,1,test)=\"Zabbix\"",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"insert(last(//trap_text),2,1,test)=\"Zabbix\"\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "insert(last(//trap_text),,,\"test\")=\"Zabbix\"",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"insert(last(//trap_text),,,\"test\")=\"Zabbix\"\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "Insert(last(//trap_text),2,1,\"ab\")=\"Zabbix\"",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"Insert(last(//trap_text),2,1,\"ab\")=\"Zabbix\"\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "left(last(//trap_text))=\"Zab\"",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "left".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "left(last(//trap_text),test)=\"Zab\"",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"left(last(//trap_text),test)=\"Zab\"\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "left(last(//trap_text),#1)=\"Zab\"",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"left(last(//trap_text),#1)=\"Zab\"\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "ltrim(last(//trap_text),test)=\"Zabbix\"",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"ltrim(last(//trap_text),test)=\"Zabbix\"\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "bytelength(//trap_text)",
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "bytelength".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "repeat(last(//trap_text))=\"ZabbixZabbix\"",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "repeat".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "replace(last(//trap_text))=\"Zabbaaah\"",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "replace".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "replace(last(//trap_text),\"Zab\")=\"Zabbaaah\"",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "replace".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "replace(last(//trap_text),,\"Zab\")=\"Zabbaaah\"",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"replace(last(//trap_text),,\"Zab\")=\"Zabbaaah\"\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "right(last(//trap_text))=\"bix\"",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "right".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "rtrim(//trap_text)=\"bix\"",
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "rtrim".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "mid(last(//trap_text),\"1\",\"2\",\"3\")=\"bix\"",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "mid".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "mid(last(//trap_text),,)=\"bix\"",
					'error' => "Invalid parameter \"/1/params\": incorrect expression starting from \"mid(last(//trap_text),,)=\"bix\"\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "trim(last(//trap_text),\"1\",\"2\")=\"bix\"",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "trim".'
				]
			],
			// Operator functions.
			[
				[
					'formula' => 'between(5,(last(/host/trap)),{$USERMACRO})'
				]
			],
			[
				[
					'formula' => 'in(5,(last(/host/trap)),{$USERMACRO},5,10)'
				]
			],
			// Operator functions validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => "between(5,(last(//trap)),10,1)",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "between".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "between(5,(last(//trap)))",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "between".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "in(last(//trap))",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "in".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'in(5,(last(/host/trap)),,6,10)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "in(5,(last(/host/trap)),,6,10)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'In(5,(last(/host/trap)),{$USERMACRO},5,10)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "In(5,(last(/host/trap)),{$USERMACRO},5,10)".'
				]
			],
			// Bitwise functions.
			[
				[
					'formula' => 'bitor(last(/host/trap),7)'
				]
			],
			[
				[
					'formula' => 'bitxor(last(/host/trap),7)'
				]
			],
			[
				[
					'formula' => 'bitnot(last(/host/trap))'
				]
			],
			[
				[
					'formula' => 'bitrshift(last(/host/trap),{$USERMACRO})'
				]
			],
			// Bitwise functions validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => "bitor(last(//trap))",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "bitor".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'BITOR(last(/host/trap),7)',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "BITOR(last(/host/trap),7)".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "bitxor(last(//trap),7,9)",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "bitxor".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "bitnot(last(//trap),1)",
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "bitnot".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'Bitnot(last(/host/trap))',
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from "Bitnot(last(/host/trap))".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'bitlshift()',
					'error' => 'Invalid parameter "/1/params": invalid number of parameters in function "bitlshift".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'bitrshift(last(/*/trap),1)',
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "last".'
				]
			],
			// Complex calculations.
			[
				[
					'formula' => 'last(/host/trap,#3)+min(/host/trap,5)-max(/host/trap,5:now/m)/avg(/host/trap,#6:now-6h)'.
							'*count(/host/trap,#4:now-5h,"eq","0")'
				]
			],
			[
				[
					'formula' => 'sum(last(/host/trap,#3)+min(/host/trap,5)-max(/host/trap,5:now/m)/avg(/host/trap,#6:now-6h)'.
							'*count(/host/trap,#4:now-5h,"eq","0"))'
				]
			],
			[
				[
					'formula' => 'sum(last(/host/trap,#3)+min(/host/trap,5)-max(/host/trap,5:now/m))/percentile(/host/trap,#5,5)'
				]
			],
			[
				[
					'formula' => "max(min_foreach(/*/trap?[group=\"Servers\"],{\$USERMACRO}))+avg(count_foreach(/*/trap?[tag=\"tag1\"],\"{\$USERMACRO}h\"))-bitrshift".
							"(last(//trap),1)/between(5,(last(//trap)),10)*fuzzytime(/host/trap,60)>=trendsum(/host/item,\"{\$USERMACRO}:now/h\")"
				]
			],
			[
				[
					'formula' => 'avg(avg(sum(max(min(trendmax(/host/item,1h:now-1h),2),3),12d)))'
				]
			],
			[
				[
					'formula' => 'truncate(bitxor(bytelength(cbrt(cosh(bytelength(cos(count(/host/key,1s)))))),1), 2)'
				]
			],
			// Complex calculations validation.
			[
				[
					'expected' => TEST_BAD,
					'formula' => "max(min_foreach(/*/trap?[group=\"Servers\"],6))+avg(count_foreach(/*/trap?[tag=\"tag1\"],99h))-".
							"bitrshift(last(/*/trap),1)/between(5,(last(//trap)),10)*fuzzytime(/host/trap,60)>=trendsum(/host/item,60m:now/h)",
					'error' => 'Invalid parameter "/1/params": invalid first parameter in function "last".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "min_foreach(/*/trap?[group=\"Servers\"],6)+avg(count_foreach(/*/trap?[tag=\"tag1\"],99h))-bitrshift(last(//trap),1)".
							"/between(5,(last(//trap)),10)*fuzzytime(/host/trap,60)>=trendsum(/host/item,60m:now/h)",
					'error' => 'Invalid parameter "/1/params": incorrect usage of function "min_foreach".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => "max(min_foreach(/*/trap?[group=\"Servers\"],6))+avg(count_foreach(/*/trap?[tag=\"tag1\"],99h))-bitrshift(last(//trap),1)".
							"/between(5,(last(//trap)),10)*fuzzytime(/host/trap,60)=>trendsum(/host/item,60m:now/h)",
					'error' => 'Invalid parameter "/1/params": incorrect expression starting from ">trendsum(/host/item,60m:now/h)".'
				]
			]
		];
	}

	/**
	 * Function for testing formula field in calculated item or item prototype.
	 *
	 * @param    array    $data       data provider
	 * @param    boolean  $prototype  false if item, true if item prototype
	 */
	public function executeValidation($data, $prototype = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM items ORDER BY itemid');
		}

		$this->page->login()->open($this->url)->waitUntilReady();
		$form = $this->query('name:itemForm')->asForm()->waitUntilVisible()->one();
		$key = 'calc'.microtime(true);

		$form->fill([
			'Name' => 'Calc',
			'Type' => 'Calculated',
			'Key' => $key,
			'Formula' => $data['formula']
		]);

		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$title = (CTestArrayHelper::get($data, 'formula') === '')
				? 'Page received incorrect data'
				: ($prototype ? 'Cannot add item prototype' : 'Cannot add item');

			$this->assertMessage(TEST_BAD, $title, $data['error']);
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM items WHERE key_='.zbx_dbstr($key)));
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM items ORDER BY itemid'));
		}
		else {
			$this->assertMessage(TEST_GOOD, ($prototype ? 'Item prototype added' : 'Item added'));
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM items WHERE key_='.zbx_dbstr($key)));
		}
	}
}
