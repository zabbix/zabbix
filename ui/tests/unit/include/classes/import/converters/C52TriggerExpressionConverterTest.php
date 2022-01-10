<?php declare(strict_types = 1);
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

class C52TriggerExpressionConverterTest extends TestCase {

	/**
	 * @var C52TriggerExpressionConverter
	 */
	private $converter;

	protected function setUp(): void {
		$this->converter = new C52TriggerExpressionConverter();
	}

	protected function tearDown(): void {
		$this->converter = null;
	}

	public function simpleProviderData() {
		return [
			[
				'{host:key.last(0)} or {host:key.last(10)}',
				'last(/host/key) or last(/host/key)'
			],
			[
				'{host:key.band(,9)}',
				'bitand(last(/host/key),9)'
			],
			[
				'{host:key.logeventid()} or {host:key.logeventid("")}',
				'logeventid(/host/key) or logeventid(/host/key,,"")'
			],
			[
				'{host:key.logsource()} or {host:key.logsource("")}',
				'logsource(/host/key) or logsource(/host/key,,"")'
			],
			[
				'{host:key.strlen()} or {host:key.strlen(123)} or {host:key.strlen("123")} or {host:key.strlen({$M})} or {host:key.strlen("{$M}")}',
				'length(last(/host/key)) or length(last(/host/key)) or length(last(/host/key)) or length(last(/host/key,{$M})) or length(last(/host/key,{$M}))'
			],
			[
				'{host:key.regexp("","")}',
				'find(/host/key,,"regexp","")'
			],
			[
				'{host:key.iregexp("","")}',
				'find(/host/key,,"iregexp","")'
			],
			[
				'{t:trap[{#IF}].last({#PERIOD},{#TIMESHIFT})}',
				'last(/t/trap[{#IF}],{#PERIOD}:now-{#TIMESHIFT})'
			],
			[
				'{t:trap[{#IF}].band({#PERIOD},123,{#TIMESHIFT})}',
				'bitand(last(/t/trap[{#IF}],{#PERIOD}:now-{#TIMESHIFT}),123)'
			],
			[
				'{t:trap[{#IF}].band("{{#PERIOD}.regsub(\"^[0-9]+\", \1)}",123,{#TIMESHIFT})}',
				'bitand(last(/t/trap[{#IF}],{{#PERIOD}.regsub("^[0-9]+", \1)}:now-{#TIMESHIFT}),123)'
			],
			[
				'{t:log.logeventid()} or {t:log.logeventid( )} or {t:log.logeventid( "" )} or {t:log.logeventid( " " )} or {t:log.logeventid( "pattern" )} or {t:log.logeventid(pattern)} or {t:log.logeventid( pattern)}',
				'logeventid(/t/log) or logeventid(/t/log) or logeventid(/t/log,,"") or logeventid(/t/log,," ") or logeventid(/t/log,,"pattern") or logeventid(/t/log,,"pattern") or logeventid(/t/log,,"pattern")'
			],
			[
				'{t:log.logseverity()} or {t:log.logseverity( abc)} or {t:log.logseverity( "" )} or {t:log.logseverity( " " )} or {t:log.logseverity( "abc" )}',
				'logseverity(/t/log) or logseverity(/t/log) or logseverity(/t/log) or logseverity(/t/log) or logseverity(/t/log)'
			],
			[
				'{t:log.logsource()} or {t:log.logsource( )} or {t:log.logsource( "" )} or {t:log.logsource( " " )} or {t:log.logsource( "pattern" )}',
				'logsource(/t/log) or logsource(/t/log) or logsource(/t/log,,"") or logsource(/t/log,," ") or logsource(/t/log,,"pattern")'
			],
			[
				'{t:str.iregexp(abc)} or {t:str.iregexp( abc)} or {t:str.iregexp("abc")} or {t:str.iregexp( "abc")} or {t:str.iregexp( "abc" )} or {t:str.iregexp(abc,)} or {t:str.iregexp( abc, )} or {t:str.iregexp("abc","")} or {t:str.iregexp( "abc", "")} or {t:str.iregexp( "abc" , "" )} or {t:str.iregexp(abc,#1)} or {t:str.iregexp( abc, #1)} or {t:str.iregexp("abc","#1")} or {t:str.iregexp( "abc", "#1")} or {t:str.iregexp( "abc" , "#1" )} or {t:str.iregexp(abc,5)} or {t:str.iregexp( abc, 5)} or {t:str.iregexp("abc","5")} or {t:str.iregexp( "abc", "5")} or {t:str.iregexp( "abc" , "5" )} or {t:str.iregexp(abc,10m)} or {t:str.iregexp( abc, 10m)} or {t:str.iregexp("abc","10m")} or {t:str.iregexp( "abc", "10m")} or {t:str.iregexp( "abc" , "10m" )} or {t:str.iregexp({$VAL},{$M: ctx})} or {t:str.iregexp( {$VAL}, {$M: ctx})} or {t:str.iregexp("{$VAL}","{$M: ctx}")} or {t:str.iregexp( "{$VAL}", "{$M: ctx}")} or {t:str.iregexp( "{$VAL}" , "{$M: ctx}" )}',
				'find(/t/str,,"iregexp","abc") or find(/t/str,,"iregexp","abc") or find(/t/str,,"iregexp","abc") or find(/t/str,,"iregexp","abc") or find(/t/str,,"iregexp","abc") or find(/t/str,,"iregexp","abc") or find(/t/str,,"iregexp","abc") or find(/t/str,,"iregexp","abc") or find(/t/str,,"iregexp","abc") or find(/t/str,,"iregexp","abc") or find(/t/str,#1,"iregexp","abc") or find(/t/str,#1,"iregexp","abc") or find(/t/str,#1,"iregexp","abc") or find(/t/str,#1,"iregexp","abc") or find(/t/str,#1,"iregexp","abc") or find(/t/str,5s,"iregexp","abc") or find(/t/str,5s,"iregexp","abc") or find(/t/str,5s,"iregexp","abc") or find(/t/str,5s,"iregexp","abc") or find(/t/str,5s,"iregexp","abc") or find(/t/str,10m,"iregexp","abc") or find(/t/str,10m,"iregexp","abc") or find(/t/str,10m,"iregexp","abc") or find(/t/str,10m,"iregexp","abc") or find(/t/str,10m,"iregexp","abc") or find(/t/str,{$M: ctx},"iregexp","{$VAL}") or find(/t/str,{$M: ctx},"iregexp","{$VAL}") or find(/t/str,{$M: ctx},"iregexp","{$VAL}") or find(/t/str,{$M: ctx},"iregexp","{$VAL}") or find(/t/str,{$M: ctx},"iregexp","{$VAL}")'
			],
			[
				'{t:str.regexp(abc)} or {t:str.regexp( abc)} or {t:str.regexp("abc")} or {t:str.regexp( "abc")} or {t:str.regexp( "abc" )} or {t:str.regexp(abc,)} or {t:str.regexp( abc, )} or {t:str.regexp("abc","")} or {t:str.regexp( "abc", "")} or {t:str.regexp( "abc" , "" )} or {t:str.regexp(abc,#1)} or {t:str.regexp( abc, #1)} or {t:str.regexp("abc","#1")} or {t:str.regexp( "abc", "#1")} or {t:str.regexp( "abc" , "#1" )} or {t:str.regexp(abc,5)} or {t:str.regexp( abc, 5)} or {t:str.regexp("abc","5")} or {t:str.regexp( "abc", "5")} or {t:str.regexp( "abc" , "5" )} or {t:str.regexp(abc,10m)} or {t:str.regexp( abc, 10m)} or {t:str.regexp("abc","10m")} or {t:str.regexp( "abc", "10m")} or {t:str.regexp( "abc" , "10m" )} or {t:str.regexp({$VAL},{$M: ctx})} or {t:str.regexp( {$VAL}, {$M: ctx})} or {t:str.regexp("{$VAL}","{$M: ctx}")} or {t:str.regexp( "{$VAL}", "{$M: ctx}")} or {t:str.regexp( "{$VAL}" , "{$M: ctx}" )}',
				'find(/t/str,,"regexp","abc") or find(/t/str,,"regexp","abc") or find(/t/str,,"regexp","abc") or find(/t/str,,"regexp","abc") or find(/t/str,,"regexp","abc") or find(/t/str,,"regexp","abc") or find(/t/str,,"regexp","abc") or find(/t/str,,"regexp","abc") or find(/t/str,,"regexp","abc") or find(/t/str,,"regexp","abc") or find(/t/str,#1,"regexp","abc") or find(/t/str,#1,"regexp","abc") or find(/t/str,#1,"regexp","abc") or find(/t/str,#1,"regexp","abc") or find(/t/str,#1,"regexp","abc") or find(/t/str,5s,"regexp","abc") or find(/t/str,5s,"regexp","abc") or find(/t/str,5s,"regexp","abc") or find(/t/str,5s,"regexp","abc") or find(/t/str,5s,"regexp","abc") or find(/t/str,10m,"regexp","abc") or find(/t/str,10m,"regexp","abc") or find(/t/str,10m,"regexp","abc") or find(/t/str,10m,"regexp","abc") or find(/t/str,10m,"regexp","abc") or find(/t/str,{$M: ctx},"regexp","{$VAL}") or find(/t/str,{$M: ctx},"regexp","{$VAL}") or find(/t/str,{$M: ctx},"regexp","{$VAL}") or find(/t/str,{$M: ctx},"regexp","{$VAL}") or find(/t/str,{$M: ctx},"regexp","{$VAL}")'
			],
			[
				'{t:str.str(abc)} or {t:str.str( abc)} or {t:str.str("abc")} or {t:str.str( "abc")} or {t:str.str( "abc" )} or {t:str.str(abc,)} or {t:str.str( abc, )} or {t:str.str("abc","")} or {t:str.str( "abc", "")} or {t:str.str( "abc" , "" )} or {t:str.str(abc,#1)} or {t:str.str( abc, #1)} or {t:str.str("abc","#1")} or {t:str.str( "abc", "#1")} or {t:str.str( "abc" , "#1" )} or {t:str.str(abc,5)} or {t:str.str( abc, 5)} or {t:str.str("abc","5")} or {t:str.str( "abc", "5")} or {t:str.str( "abc" , "5" )} or {t:str.str(abc,10m)} or {t:str.str( abc, 10m)} or {t:str.str("abc","10m")} or {t:str.str( "abc", "10m")} or {t:str.str( "abc" , "10m" )} or {t:str.str({$VAL},{$M: ctx})} or {t:str.str( {$VAL}, {$M: ctx})} or {t:str.str("{$VAL}","{$M: ctx}")} or {t:str.str( "{$VAL}", "{$M: ctx}")} or {t:str.str( "{$VAL}" , "{$M: ctx}" )}',
				'find(/t/str,,"like","abc") or find(/t/str,,"like","abc") or find(/t/str,,"like","abc") or find(/t/str,,"like","abc") or find(/t/str,,"like","abc") or find(/t/str,,"like","abc") or find(/t/str,,"like","abc") or find(/t/str,,"like","abc") or find(/t/str,,"like","abc") or find(/t/str,,"like","abc") or find(/t/str,#1,"like","abc") or find(/t/str,#1,"like","abc") or find(/t/str,#1,"like","abc") or find(/t/str,#1,"like","abc") or find(/t/str,#1,"like","abc") or find(/t/str,5s,"like","abc") or find(/t/str,5s,"like","abc") or find(/t/str,5s,"like","abc") or find(/t/str,5s,"like","abc") or find(/t/str,5s,"like","abc") or find(/t/str,10m,"like","abc") or find(/t/str,10m,"like","abc") or find(/t/str,10m,"like","abc") or find(/t/str,10m,"like","abc") or find(/t/str,10m,"like","abc") or find(/t/str,{$M: ctx},"like","{$VAL}") or find(/t/str,{$M: ctx},"like","{$VAL}") or find(/t/str,{$M: ctx},"like","{$VAL}") or find(/t/str,{$M: ctx},"like","{$VAL}") or find(/t/str,{$M: ctx},"like","{$VAL}")'
			],
			[
				'{t:str.strlen()} or {t:str.strlen( )} or {t:str.strlen("")} or {t:str.strlen( "")} or {t:str.strlen( "" )} or {t:str.strlen(#1)} or {t:str.strlen( #1)} or {t:str.strlen("#1")} or {t:str.strlen( "#1")} or {t:str.strlen( "#1" )} or {t:str.strlen(5)} or {t:str.strlen( 5)} or {t:str.strlen("5")} or {t:str.strlen( "5")} or {t:str.strlen( "5" )} or {t:str.strlen(10m)} or {t:str.strlen( 10m)} or {t:str.strlen("10m")} or {t:str.strlen( "10m")} or {t:str.strlen( "10m" )} or {t:str.strlen(10m,1h)} or {t:str.strlen(10m, 1h)} or {t:str.strlen(10m,"1h")} or {t:str.strlen(10m, "1h")} or {t:str.strlen(10m, "1h" )} or {t:str.strlen({$PERIOD},{$TIMESHIFT})} or {t:str.strlen( {$PERIOD}, {$TIMESHIFT})} or {t:str.strlen("{$PERIOD}","{$TIMESHIFT}")} or {t:str.strlen( "{$PERIOD}", "{$TIMESHIFT}")} or {t:str.strlen( "{$PERIOD}" , "{$TIMESHIFT}" )}',
				'length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str,#1)) or length(last(/t/str,#1)) or length(last(/t/str,#1)) or length(last(/t/str,#1)) or length(last(/t/str,#1)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str)) or length(last(/t/str,#1:now-1h)) or length(last(/t/str,#1:now-1h)) or length(last(/t/str,#1:now-1h)) or length(last(/t/str,#1:now-1h)) or length(last(/t/str,#1:now-1h)) or length(last(/t/str,{$PERIOD}:now-{$TIMESHIFT})) or length(last(/t/str,{$PERIOD}:now-{$TIMESHIFT})) or length(last(/t/str,{$PERIOD}:now-{$TIMESHIFT})) or length(last(/t/str,{$PERIOD}:now-{$TIMESHIFT})) or length(last(/t/str,{$PERIOD}:now-{$TIMESHIFT}))'
			],
			[
				'{t:uint64.abschange()} or {t:uint64.abschange( )} or {t:uint64.abschange( "" )} or {t:uint64.abschange( " " )} or {t:uint64.abschange( "text" )}',
				'abs(change(/t/uint64)) or abs(change(/t/uint64)) or abs(change(/t/uint64)) or abs(change(/t/uint64)) or abs(change(/t/uint64))'
			],
			[
				'{t:uint64.avg(#1)} or {t:uint64.avg( #1)} or {t:uint64.avg("#1")} or {t:uint64.avg( "#1")} or {t:uint64.avg( "#1" )} or {t:uint64.avg(5)} or {t:uint64.avg( 5)} or {t:uint64.avg("5")} or {t:uint64.avg( "5")} or {t:uint64.avg( "5" )} or {t:uint64.avg(10m)} or {t:uint64.avg( 10m)} or {t:uint64.avg("10m")} or {t:uint64.avg( "10m")} or {t:uint64.avg( "10m" )} or {t:uint64.avg({$M: ctx})} or {t:uint64.avg( {$M: ctx})} or {t:uint64.avg("{$M: ctx}")} or {t:uint64.avg( "{$M: ctx}")} or {t:uint64.avg( "{$M: ctx}" )} or {t:uint64.avg(#1,)} or {t:uint64.avg(#1,3600)} or {t:uint64.avg(#1, 3600)} or {t:uint64.avg(#1,"3600")} or {t:uint64.avg(#1, "3600")} or {t:uint64.avg(#1, "3600" )} or {t:uint64.avg(#1,1h)} or {t:uint64.avg(#1, 1h)} or {t:uint64.avg(#1,"1h")} or {t:uint64.avg(#1, "1h")} or {t:uint64.avg(#1, "1h" )} or {t:uint64.avg(#1,{$M: ctx})} or {t:uint64.avg(#1, {$M: ctx})} or {t:uint64.avg(#1,"{$M: ctx}")} or {t:uint64.avg(#1, "{$M: ctx}")} or {t:uint64.avg(#1, "{$M: ctx}" )}',
				'avg(/t/uint64,#1) or avg(/t/uint64,#1) or avg(/t/uint64,#1) or avg(/t/uint64,#1) or avg(/t/uint64,#1) or avg(/t/uint64,5s) or avg(/t/uint64,5s) or avg(/t/uint64,5s) or avg(/t/uint64,5s) or avg(/t/uint64,5s) or avg(/t/uint64,10m) or avg(/t/uint64,10m) or avg(/t/uint64,10m) or avg(/t/uint64,10m) or avg(/t/uint64,10m) or avg(/t/uint64,{$M: ctx}) or avg(/t/uint64,{$M: ctx}) or avg(/t/uint64,{$M: ctx}) or avg(/t/uint64,{$M: ctx}) or avg(/t/uint64,{$M: ctx}) or avg(/t/uint64,#1) or avg(/t/uint64,#1:now-3600s) or avg(/t/uint64,#1:now-3600s) or avg(/t/uint64,#1:now-3600s) or avg(/t/uint64,#1:now-3600s) or avg(/t/uint64,#1:now-3600s) or avg(/t/uint64,#1:now-1h) or avg(/t/uint64,#1:now-1h) or avg(/t/uint64,#1:now-1h) or avg(/t/uint64,#1:now-1h) or avg(/t/uint64,#1:now-1h) or avg(/t/uint64,#1:now-{$M: ctx}) or avg(/t/uint64,#1:now-{$M: ctx}) or avg(/t/uint64,#1:now-{$M: ctx}) or avg(/t/uint64,#1:now-{$M: ctx}) or avg(/t/uint64,#1:now-{$M: ctx})'
			],
			[
				'{t:uint64.band(#1,256)} or {t:uint64.band( #1, 256)} or {t:uint64.band("#1","256")} or {t:uint64.band( "#1", "256")} or {t:uint64.band( "#1" , "256" )} or {t:uint64.band(5,{$M: ctx})} or {t:uint64.band( 5, {$M: ctx})} or {t:uint64.band("5","{$M: ctx}")} or {t:uint64.band( "5", "{$M: ctx}")} or {t:uint64.band( "5" , "{$M: ctx}" )} or {t:uint64.band(10m,1024)} or {t:uint64.band( 10m, 1024)} or {t:uint64.band("10m","1024")} or {t:uint64.band( "10m", "1024")} or {t:uint64.band( "10m" , "1024" )} or {t:uint64.band({$M: ctx},18446744073709551615)} or {t:uint64.band( {$M: ctx}, 18446744073709551615)} or {t:uint64.band("{$M: ctx}","18446744073709551615")} or {t:uint64.band( "{$M: ctx}", "18446744073709551615")} or {t:uint64.band( "{$M: ctx}" , "18446744073709551615" )} or {t:uint64.band(#1,256,)} or {t:uint64.band(#1,1,3600)} or {t:uint64.band(#1,1, 3600)} or {t:uint64.band(#1,1,"3600")} or {t:uint64.band(#1,1, "3600")} or {t:uint64.band(#1,1, "3600" )} or {t:uint64.band(#1,1,1h)} or {t:uint64.band(#1,1, 1h)} or {t:uint64.band(#1,1,"1h")} or {t:uint64.band(#1,1, "1h")} or {t:uint64.band(#1,1, "1h" )} or {t:uint64.band(#1,1,{$M: ctx})} or {t:uint64.band(#1,1, {$M: ctx})} or {t:uint64.band(#1,1,"{$M: ctx}")} or {t:uint64.band(#1,1, "{$M: ctx}")} or {t:uint64.band(#1,1, "{$M: ctx}" )}',
				'bitand(last(/t/uint64,#1),256) or bitand(last(/t/uint64,#1),256) or bitand(last(/t/uint64,#1),256) or bitand(last(/t/uint64,#1),256) or bitand(last(/t/uint64,#1),256) or bitand(last(/t/uint64),{$M: ctx}) or bitand(last(/t/uint64),{$M: ctx}) or bitand(last(/t/uint64),{$M: ctx}) or bitand(last(/t/uint64),{$M: ctx}) or bitand(last(/t/uint64),{$M: ctx}) or bitand(last(/t/uint64),1024) or bitand(last(/t/uint64),1024) or bitand(last(/t/uint64),1024) or bitand(last(/t/uint64),1024) or bitand(last(/t/uint64),1024) or bitand(last(/t/uint64,{$M: ctx}),18446744073709551615) or bitand(last(/t/uint64,{$M: ctx}),18446744073709551615) or bitand(last(/t/uint64,{$M: ctx}),18446744073709551615) or bitand(last(/t/uint64,{$M: ctx}),18446744073709551615) or bitand(last(/t/uint64,{$M: ctx}),18446744073709551615) or bitand(last(/t/uint64,#1),256) or bitand(last(/t/uint64,#1:now-3600s),1) or bitand(last(/t/uint64,#1:now-3600s),1) or bitand(last(/t/uint64,#1:now-3600s),1) or bitand(last(/t/uint64,#1:now-3600s),1) or bitand(last(/t/uint64,#1:now-3600s),1) or bitand(last(/t/uint64,#1:now-1h),1) or bitand(last(/t/uint64,#1:now-1h),1) or bitand(last(/t/uint64,#1:now-1h),1) or bitand(last(/t/uint64,#1:now-1h),1) or bitand(last(/t/uint64,#1:now-1h),1) or bitand(last(/t/uint64,#1:now-{$M: ctx}),1) or bitand(last(/t/uint64,#1:now-{$M: ctx}),1) or bitand(last(/t/uint64,#1:now-{$M: ctx}),1) or bitand(last(/t/uint64,#1:now-{$M: ctx}),1) or bitand(last(/t/uint64,#1:now-{$M: ctx}),1)'
			],
			[
				'{t:uint64.change()} or {t:uint64.change( )} or {t:uint64.change( "" )} or {t:uint64.change( " " )} or {t:uint64.change( "text" )} or {t:uint64.change(text)} or {t:uint64.change( text)}',
				'change(/t/uint64) or change(/t/uint64) or change(/t/uint64) or change(/t/uint64) or change(/t/uint64) or change(/t/uint64) or change(/t/uint64)'
			],
			[
				'{t:uint64.count(#1,256)} or {t:uint64.count( #1, 256)} or {t:uint64.count("#1","256")} or {t:uint64.count( "#1", "256")} or {t:uint64.count( "#1" , "256" )} or {t:uint64.count(5,{$M: ctx})} or {t:uint64.count( 5, {$M: ctx})} or {t:uint64.count("5","{$M: ctx}")} or {t:uint64.count( "5", "{$M: ctx}")} or {t:uint64.count( "5" , "{$M: ctx}" )} or {t:uint64.count(10m,abc)} or {t:uint64.count( 10m, abc)} or {t:uint64.count("10m","abc")} or {t:uint64.count( "10m", "abc")} or {t:uint64.count( "10m" , "abc" )} or {t:uint64.count({$M: ctx},18446744073709551615)} or {t:uint64.count( {$M: ctx}, 18446744073709551615)} or {t:uint64.count("{$M: ctx}","18446744073709551615")} or {t:uint64.count( "{$M: ctx}", "18446744073709551615")} or {t:uint64.count( "{$M: ctx}" , "18446744073709551615" )} or {t:uint64.count(#1,256,)} or {t:uint64.count(#1,1,eq,3600)} or {t:uint64.count(#1,1, ne, 3600)} or {t:uint64.count(#1,1,"gt","3600")} or {t:uint64.count(#1,1, "ge", "3600")} or {t:uint64.count(#1,1, "lt" , "3600" )} or {t:uint64.count(#1,1,le,1h)} or {t:uint64.count(#1,1, like, 1h)} or {t:uint64.count(#1,1,"band","1h")} or {t:uint64.count(#1,1, "regexp", "1h")} or {t:uint64.count(#1,1, "iregexp" , "1h" )} or {t:uint64.count(#1,1,eq,{$M: ctx})} or {t:uint64.count(#1,1,eq, {$M: ctx})} or {t:uint64.count(#1,1,eq,"{$M: ctx}")} or {t:uint64.count(#1,1,eq, "{$M: ctx}")} or {t:uint64.count(#1,1,eq, "{$M: ctx}" )} or {t:uint64.count(#1,256,,)} or {t:uint64.count(#1,256,,1d)} or {t:uint64.count(#5,256,,1d)} or {t:uint64.count({$NUM},256,,{$TIMESHIFT})}',
				'count(/t/uint64,#1,,"256") or count(/t/uint64,#1,,"256") or count(/t/uint64,#1,,"256") or count(/t/uint64,#1,,"256") or count(/t/uint64,#1,,"256") or count(/t/uint64,5s,,"{$M: ctx}") or count(/t/uint64,5s,,"{$M: ctx}") or count(/t/uint64,5s,,"{$M: ctx}") or count(/t/uint64,5s,,"{$M: ctx}") or count(/t/uint64,5s,,"{$M: ctx}") or count(/t/uint64,10m,,"abc") or count(/t/uint64,10m,,"abc") or count(/t/uint64,10m,,"abc") or count(/t/uint64,10m,,"abc") or count(/t/uint64,10m,,"abc") or count(/t/uint64,{$M: ctx},,"18446744073709551615") or count(/t/uint64,{$M: ctx},,"18446744073709551615") or count(/t/uint64,{$M: ctx},,"18446744073709551615") or count(/t/uint64,{$M: ctx},,"18446744073709551615") or count(/t/uint64,{$M: ctx},,"18446744073709551615") or count(/t/uint64,#1,,"256") or count(/t/uint64,#1:now-3600s,"eq","1") or count(/t/uint64,#1:now-3600s,"ne","1") or count(/t/uint64,#1:now-3600s,"gt","1") or count(/t/uint64,#1:now-3600s,"ge","1") or count(/t/uint64,#1:now-3600s,"lt","1") or count(/t/uint64,#1:now-1h,"le","1") or count(/t/uint64,#1:now-1h,"like","1") or count(/t/uint64,#1:now-1h,"bitand","1") or count(/t/uint64,#1:now-1h,"regexp","1") or count(/t/uint64,#1:now-1h,"iregexp","1") or count(/t/uint64,#1:now-{$M: ctx},"eq","1") or count(/t/uint64,#1:now-{$M: ctx},"eq","1") or count(/t/uint64,#1:now-{$M: ctx},"eq","1") or count(/t/uint64,#1:now-{$M: ctx},"eq","1") or count(/t/uint64,#1:now-{$M: ctx},"eq","1") or count(/t/uint64,#1,,"256") or count(/t/uint64,#1:now-1d,,"256") or count(/t/uint64,#5:now-1d,,"256") or count(/t/uint64,{$NUM}:now-{$TIMESHIFT},,"256")'
			],
			[
				'{t:uint64.date()} or {t:uint64.date( )} or {t:uint64.date( "" )} or {t:uint64.date( " " )} or {t:uint64.date( "text" )}',
				'(date() or date() or date() or date() or date()) or (last(/t/uint64)<>last(/t/uint64))'
			],
			[
				'{t:uint64.dayofmonth()} or {t:uint64.dayofmonth( )} or {t:uint64.dayofmonth( "" )} or {t:uint64.dayofmonth( " " )} or {t:uint64.dayofmonth( "text" )}',
				'(dayofmonth() or dayofmonth() or dayofmonth() or dayofmonth() or dayofmonth()) or (last(/t/uint64)<>last(/t/uint64))'
			],
			[
				'{t:uint64.dayofweek()} or {t:uint64.dayofweek( )} or {t:uint64.dayofweek( "" )} or {t:uint64.dayofweek( " " )} or {t:uint64.dayofweek( "text" )}',
				'(dayofweek() or dayofweek() or dayofweek() or dayofweek() or dayofweek()) or (last(/t/uint64)<>last(/t/uint64))'
			],
			[
				'{t:uint64.delta(#1)} or {t:uint64.delta( #1)} or {t:uint64.delta("#1")} or {t:uint64.delta( "#1")} or {t:uint64.delta( "#1" )} or {t:uint64.delta(5)} or {t:uint64.delta( 5)} or {t:uint64.delta("5")} or {t:uint64.delta( "5")} or {t:uint64.delta( "5" )} or {t:uint64.delta(10m)} or {t:uint64.delta( 10m)} or {t:uint64.delta("10m")} or {t:uint64.delta( "10m")} or {t:uint64.delta( "10m" )} or {t:uint64.delta({$M: ctx})} or {t:uint64.delta( {$M: ctx})} or {t:uint64.delta("{$M: ctx}")} or {t:uint64.delta( "{$M: ctx}")} or {t:uint64.delta( "{$M: ctx}" )} or {t:uint64.delta(#1,)} or {t:uint64.delta(#1,3600)} or {t:uint64.delta(#1, 3600)} or {t:uint64.delta(#1,"3600")} or {t:uint64.delta(#1, "3600")} or {t:uint64.delta(#1, "3600" )} or {t:uint64.delta(#1,1h)} or {t:uint64.delta(#1, 1h)} or {t:uint64.delta(#1,"1h")} or {t:uint64.delta(#1, "1h")} or {t:uint64.delta(#1, "1h" )} or {t:uint64.delta(#1,{$M: ctx})} or {t:uint64.delta(#1, {$M: ctx})} or {t:uint64.delta(#1,"{$M: ctx}")} or {t:uint64.delta(#1, "{$M: ctx}")} or {t:uint64.delta(#1, "{$M: ctx}" )}',
				'(max(/t/uint64,#1)-min(/t/uint64,#1)) or (max(/t/uint64,#1)-min(/t/uint64,#1)) or (max(/t/uint64,#1)-min(/t/uint64,#1)) or (max(/t/uint64,#1)-min(/t/uint64,#1)) or (max(/t/uint64,#1)-min(/t/uint64,#1)) or (max(/t/uint64,5s)-min(/t/uint64,5s)) or (max(/t/uint64,5s)-min(/t/uint64,5s)) or (max(/t/uint64,5s)-min(/t/uint64,5s)) or (max(/t/uint64,5s)-min(/t/uint64,5s)) or (max(/t/uint64,5s)-min(/t/uint64,5s)) or (max(/t/uint64,10m)-min(/t/uint64,10m)) or (max(/t/uint64,10m)-min(/t/uint64,10m)) or (max(/t/uint64,10m)-min(/t/uint64,10m)) or (max(/t/uint64,10m)-min(/t/uint64,10m)) or (max(/t/uint64,10m)-min(/t/uint64,10m)) or (max(/t/uint64,{$M: ctx})-min(/t/uint64,{$M: ctx})) or (max(/t/uint64,{$M: ctx})-min(/t/uint64,{$M: ctx})) or (max(/t/uint64,{$M: ctx})-min(/t/uint64,{$M: ctx})) or (max(/t/uint64,{$M: ctx})-min(/t/uint64,{$M: ctx})) or (max(/t/uint64,{$M: ctx})-min(/t/uint64,{$M: ctx})) or (max(/t/uint64,#1)-min(/t/uint64,#1)) or (max(/t/uint64,#1:now-3600s)-min(/t/uint64,#1:now-3600s)) or (max(/t/uint64,#1:now-3600s)-min(/t/uint64,#1:now-3600s)) or (max(/t/uint64,#1:now-3600s)-min(/t/uint64,#1:now-3600s)) or (max(/t/uint64,#1:now-3600s)-min(/t/uint64,#1:now-3600s)) or (max(/t/uint64,#1:now-3600s)-min(/t/uint64,#1:now-3600s)) or (max(/t/uint64,#1:now-1h)-min(/t/uint64,#1:now-1h)) or (max(/t/uint64,#1:now-1h)-min(/t/uint64,#1:now-1h)) or (max(/t/uint64,#1:now-1h)-min(/t/uint64,#1:now-1h)) or (max(/t/uint64,#1:now-1h)-min(/t/uint64,#1:now-1h)) or (max(/t/uint64,#1:now-1h)-min(/t/uint64,#1:now-1h)) or (max(/t/uint64,#1:now-{$M: ctx})-min(/t/uint64,#1:now-{$M: ctx})) or (max(/t/uint64,#1:now-{$M: ctx})-min(/t/uint64,#1:now-{$M: ctx})) or (max(/t/uint64,#1:now-{$M: ctx})-min(/t/uint64,#1:now-{$M: ctx})) or (max(/t/uint64,#1:now-{$M: ctx})-min(/t/uint64,#1:now-{$M: ctx})) or (max(/t/uint64,#1:now-{$M: ctx})-min(/t/uint64,#1:now-{$M: ctx}))'
			],
			[
				'{t:uint64.diff()} or {t:uint64.diff( )} or {t:uint64.diff( "" )} or {t:uint64.diff( " " )} or {t:uint64.diff( "text" )}',
				'(last(/t/uint64,#1)<>last(/t/uint64,#2)) or (last(/t/uint64,#1)<>last(/t/uint64,#2)) or (last(/t/uint64,#1)<>last(/t/uint64,#2)) or (last(/t/uint64,#1)<>last(/t/uint64,#2)) or (last(/t/uint64,#1)<>last(/t/uint64,#2))'
			],
			[
				'{t:uint64.forecast(#1,,1w)} or {t:uint64.forecast( #1, , 1w)} or {t:uint64.forecast("#1","","1w")} or {t:uint64.forecast( "#1", "", "1w")} or {t:uint64.forecast( "#1" , "" , "1w" )} or {t:uint64.forecast(5,,86400)} or {t:uint64.forecast( 5, , 86400)} or {t:uint64.forecast("5","","86400")} or {t:uint64.forecast( "5", "", "86400")} or {t:uint64.forecast( "5" , "" , "86400" )} or {t:uint64.forecast(10m,,{$M: ctx})} or {t:uint64.forecast( 10m, , {$M: ctx})} or {t:uint64.forecast("10m","","{$M: ctx}")} or {t:uint64.forecast( "10m", "", "{$M: ctx}")} or {t:uint64.forecast( "10m" , "" , "{$M: ctx}" )} or {t:uint64.forecast({$M: ctx},,1d,polynomial1,value)} or {t:uint64.forecast( {$M: ctx},,1d, polynomial2,avg)} or {t:uint64.forecast("{$M: ctx}",,1d,"polynomial3",max)} or {t:uint64.forecast( "{$M: ctx}",,1d, "polynomial4",min)} or {t:uint64.forecast( "{$M: ctx}" ,,1d, "polynomial5" ,delta)} or {t:uint64.forecast(#1,,30d,,)} or {t:uint64.forecast(#1,3600,1d,polynomial6)} or {t:uint64.forecast(#1, 3600,1d, linear)} or {t:uint64.forecast(#1,"3600",1d,"exponential")} or {t:uint64.forecast(#1, "3600",1d, "logarithmic")} or {t:uint64.forecast(#1, "3600" ,1d, "power" )} or {t:uint64.forecast(#1,1h,1d)} or {t:uint64.forecast(#1, 1h,1d)} or {t:uint64.forecast(#1,"1h",1d)} or {t:uint64.forecast(#1, "1h",1d)} or {t:uint64.forecast(#1, "1h" ,1d)} or {t:uint64.forecast(#1,{$M: ctx},1d)} or {t:uint64.forecast(#1, {$M: ctx},1d)} or {t:uint64.forecast(#1,"{$M: ctx}",1d)} or {t:uint64.forecast(#1, "{$M: ctx}",1d)} or {t:uint64.forecast(#1, "{$M: ctx}" ,1d)}',
				'forecast(/t/uint64,#1,1w) or forecast(/t/uint64,#1,1w) or forecast(/t/uint64,#1,1w) or forecast(/t/uint64,#1,1w) or forecast(/t/uint64,#1,1w) or forecast(/t/uint64,5s,86400s) or forecast(/t/uint64,5s,86400s) or forecast(/t/uint64,5s,86400s) or forecast(/t/uint64,5s,86400s) or forecast(/t/uint64,5s,86400s) or forecast(/t/uint64,10m,{$M: ctx}) or forecast(/t/uint64,10m,{$M: ctx}) or forecast(/t/uint64,10m,{$M: ctx}) or forecast(/t/uint64,10m,{$M: ctx}) or forecast(/t/uint64,10m,{$M: ctx}) or forecast(/t/uint64,{$M: ctx},1d,"polynomial1","value") or forecast(/t/uint64,{$M: ctx},1d,"polynomial2","avg") or forecast(/t/uint64,{$M: ctx},1d,"polynomial3","max") or forecast(/t/uint64,{$M: ctx},1d,"polynomial4","min") or forecast(/t/uint64,{$M: ctx},1d,"polynomial5","delta") or forecast(/t/uint64,#1,30d) or forecast(/t/uint64,#1:now-3600s,1d,"polynomial6") or forecast(/t/uint64,#1:now-3600s,1d,"linear") or forecast(/t/uint64,#1:now-3600s,1d,"exponential") or forecast(/t/uint64,#1:now-3600s,1d,"logarithmic") or forecast(/t/uint64,#1:now-3600s,1d,"power") or forecast(/t/uint64,#1:now-1h,1d) or forecast(/t/uint64,#1:now-1h,1d) or forecast(/t/uint64,#1:now-1h,1d) or forecast(/t/uint64,#1:now-1h,1d) or forecast(/t/uint64,#1:now-1h,1d) or forecast(/t/uint64,#1:now-{$M: ctx},1d) or forecast(/t/uint64,#1:now-{$M: ctx},1d) or forecast(/t/uint64,#1:now-{$M: ctx},1d) or forecast(/t/uint64,#1:now-{$M: ctx},1d) or forecast(/t/uint64,#1:now-{$M: ctx},1d)'
			],
			[
				'{t:uint64.fuzzytime(3600)} or {t:uint64.fuzzytime( 1h)} or {t:uint64.fuzzytime("24h")} or {t:uint64.fuzzytime( "1d")} or {t:uint64.fuzzytime( "1w" )}',
				'fuzzytime(/t/uint64,3600s) or fuzzytime(/t/uint64,1h) or fuzzytime(/t/uint64,24h) or fuzzytime(/t/uint64,1d) or fuzzytime(/t/uint64,1w)'
			],
			[
				'{t:uint64.last(#1)} or {t:uint64.last( #1)} or {t:uint64.last("#1")} or {t:uint64.last( "#1")} or {t:uint64.last( "#1" )} or {t:uint64.last(5)} or {t:uint64.last( 5)} or {t:uint64.last("5")} or {t:uint64.last( "5")} or {t:uint64.last( "5" )} or {t:uint64.last(10m)} or {t:uint64.last( 10m)} or {t:uint64.last("10m")} or {t:uint64.last( "10m")} or {t:uint64.last( "10m" )} or {t:uint64.last({$M: ctx})} or {t:uint64.last( {$M: ctx})} or {t:uint64.last("{$M: ctx}")} or {t:uint64.last( "{$M: ctx}")} or {t:uint64.last( "{$M: ctx}" )} or {t:uint64.last(#1,)} or {t:uint64.last(#1,3600)} or {t:uint64.last(#1, 3600)} or {t:uint64.last(#1,"3600")} or {t:uint64.last(#1, "3600")} or {t:uint64.last(#1, "3600" )} or {t:uint64.last(#1,1h)} or {t:uint64.last(#1, 1h)} or {t:uint64.last(#1,"1h")} or {t:uint64.last(#1, "1h")} or {t:uint64.last(#1, "1h" )} or {t:uint64.last(#1,{$M: ctx})} or {t:uint64.last(#1, {$M: ctx})} or {t:uint64.last(#1,"{$M: ctx}")} or {t:uint64.last(#1, "{$M: ctx}")} or {t:uint64.last(#1, "{$M: ctx}" )} or {t:uint64.last(,)} or {t:uint64.last(,1d)} or {t:uint64.last(,{$TIMESHIFT})} or {t:uint64.last(#5,)} or {t:uint64.last(#5,1d)} or {t:uint64.last(#5,{$TIMESHIFT})} or {t:uint64.last({$PERIOD},{$TIMESHIFT})}',
				'last(/t/uint64,#1) or last(/t/uint64,#1) or last(/t/uint64,#1) or last(/t/uint64,#1) or last(/t/uint64,#1) or last(/t/uint64) or last(/t/uint64) or last(/t/uint64) or last(/t/uint64) or last(/t/uint64) or last(/t/uint64) or last(/t/uint64) or last(/t/uint64) or last(/t/uint64) or last(/t/uint64) or last(/t/uint64,{$M: ctx}) or last(/t/uint64,{$M: ctx}) or last(/t/uint64,{$M: ctx}) or last(/t/uint64,{$M: ctx}) or last(/t/uint64,{$M: ctx}) or last(/t/uint64,#1) or last(/t/uint64,#1:now-3600s) or last(/t/uint64,#1:now-3600s) or last(/t/uint64,#1:now-3600s) or last(/t/uint64,#1:now-3600s) or last(/t/uint64,#1:now-3600s) or last(/t/uint64,#1:now-1h) or last(/t/uint64,#1:now-1h) or last(/t/uint64,#1:now-1h) or last(/t/uint64,#1:now-1h) or last(/t/uint64,#1:now-1h) or last(/t/uint64,#1:now-{$M: ctx}) or last(/t/uint64,#1:now-{$M: ctx}) or last(/t/uint64,#1:now-{$M: ctx}) or last(/t/uint64,#1:now-{$M: ctx}) or last(/t/uint64,#1:now-{$M: ctx}) or last(/t/uint64) or last(/t/uint64,#1:now-1d) or last(/t/uint64,#1:now-{$TIMESHIFT}) or last(/t/uint64,#5) or last(/t/uint64,#5:now-1d) or last(/t/uint64,#5:now-{$TIMESHIFT}) or last(/t/uint64,{$PERIOD}:now-{$TIMESHIFT})'
			],
			[
				'{t:uint64.max(#1)} or {t:uint64.max( #1)} or {t:uint64.max("#1")} or {t:uint64.max( "#1")} or {t:uint64.max( "#1" )} or {t:uint64.max(5)} or {t:uint64.max( 5)} or {t:uint64.max("5")} or {t:uint64.max( "5")} or {t:uint64.max( "5" )} or {t:uint64.max(10m)} or {t:uint64.max( 10m)} or {t:uint64.max("10m")} or {t:uint64.max( "10m")} or {t:uint64.max( "10m" )} or {t:uint64.max({$M: ctx})} or {t:uint64.max( {$M: ctx})} or {t:uint64.max("{$M: ctx}")} or {t:uint64.max( "{$M: ctx}")} or {t:uint64.max( "{$M: ctx}" )} or {t:uint64.max(#1,)} or {t:uint64.max(#1,3600)} or {t:uint64.max(#1, 3600)} or {t:uint64.max(#1,"3600")} or {t:uint64.max(#1, "3600")} or {t:uint64.max(#1, "3600" )} or {t:uint64.max(#1,1h)} or {t:uint64.max(#1, 1h)} or {t:uint64.max(#1,"1h")} or {t:uint64.max(#1, "1h")} or {t:uint64.max(#1, "1h" )} or {t:uint64.max(#1,{$M: ctx})} or {t:uint64.max(#1, {$M: ctx})} or {t:uint64.max(#1,"{$M: ctx}")} or {t:uint64.max(#1, "{$M: ctx}")} or {t:uint64.max(#1, "{$M: ctx}" )}',
				'max(/t/uint64,#1) or max(/t/uint64,#1) or max(/t/uint64,#1) or max(/t/uint64,#1) or max(/t/uint64,#1) or max(/t/uint64,5s) or max(/t/uint64,5s) or max(/t/uint64,5s) or max(/t/uint64,5s) or max(/t/uint64,5s) or max(/t/uint64,10m) or max(/t/uint64,10m) or max(/t/uint64,10m) or max(/t/uint64,10m) or max(/t/uint64,10m) or max(/t/uint64,{$M: ctx}) or max(/t/uint64,{$M: ctx}) or max(/t/uint64,{$M: ctx}) or max(/t/uint64,{$M: ctx}) or max(/t/uint64,{$M: ctx}) or max(/t/uint64,#1) or max(/t/uint64,#1:now-3600s) or max(/t/uint64,#1:now-3600s) or max(/t/uint64,#1:now-3600s) or max(/t/uint64,#1:now-3600s) or max(/t/uint64,#1:now-3600s) or max(/t/uint64,#1:now-1h) or max(/t/uint64,#1:now-1h) or max(/t/uint64,#1:now-1h) or max(/t/uint64,#1:now-1h) or max(/t/uint64,#1:now-1h) or max(/t/uint64,#1:now-{$M: ctx}) or max(/t/uint64,#1:now-{$M: ctx}) or max(/t/uint64,#1:now-{$M: ctx}) or max(/t/uint64,#1:now-{$M: ctx}) or max(/t/uint64,#1:now-{$M: ctx})'
			],
			[
				'{t:uint64.min(#1)} or {t:uint64.min( #1)} or {t:uint64.min("#1")} or {t:uint64.min( "#1")} or {t:uint64.min( "#1" )} or {t:uint64.min(5)} or {t:uint64.min( 5)} or {t:uint64.min("5")} or {t:uint64.min( "5")} or {t:uint64.min( "5" )} or {t:uint64.min(10m)} or {t:uint64.min( 10m)} or {t:uint64.min("10m")} or {t:uint64.min( "10m")} or {t:uint64.min( "10m" )} or {t:uint64.min({$M: ctx})} or {t:uint64.min( {$M: ctx})} or {t:uint64.min("{$M: ctx}")} or {t:uint64.min( "{$M: ctx}")} or {t:uint64.min( "{$M: ctx}" )} or {t:uint64.min(#1,)} or {t:uint64.min(#1,3600)} or {t:uint64.min(#1, 3600)} or {t:uint64.min(#1,"3600")} or {t:uint64.min(#1, "3600")} or {t:uint64.min(#1, "3600" )} or {t:uint64.min(#1,1h)} or {t:uint64.min(#1, 1h)} or {t:uint64.min(#1,"1h")} or {t:uint64.min(#1, "1h")} or {t:uint64.min(#1, "1h" )} or {t:uint64.min(#1,{$M: ctx})} or {t:uint64.min(#1, {$M: ctx})} or {t:uint64.min(#1,"{$M: ctx}")} or {t:uint64.min(#1, "{$M: ctx}")} or {t:uint64.min(#1, "{$M: ctx}" )}',
				'min(/t/uint64,#1) or min(/t/uint64,#1) or min(/t/uint64,#1) or min(/t/uint64,#1) or min(/t/uint64,#1) or min(/t/uint64,5s) or min(/t/uint64,5s) or min(/t/uint64,5s) or min(/t/uint64,5s) or min(/t/uint64,5s) or min(/t/uint64,10m) or min(/t/uint64,10m) or min(/t/uint64,10m) or min(/t/uint64,10m) or min(/t/uint64,10m) or min(/t/uint64,{$M: ctx}) or min(/t/uint64,{$M: ctx}) or min(/t/uint64,{$M: ctx}) or min(/t/uint64,{$M: ctx}) or min(/t/uint64,{$M: ctx}) or min(/t/uint64,#1) or min(/t/uint64,#1:now-3600s) or min(/t/uint64,#1:now-3600s) or min(/t/uint64,#1:now-3600s) or min(/t/uint64,#1:now-3600s) or min(/t/uint64,#1:now-3600s) or min(/t/uint64,#1:now-1h) or min(/t/uint64,#1:now-1h) or min(/t/uint64,#1:now-1h) or min(/t/uint64,#1:now-1h) or min(/t/uint64,#1:now-1h) or min(/t/uint64,#1:now-{$M: ctx}) or min(/t/uint64,#1:now-{$M: ctx}) or min(/t/uint64,#1:now-{$M: ctx}) or min(/t/uint64,#1:now-{$M: ctx}) or min(/t/uint64,#1:now-{$M: ctx})'
			],
			[
				'{t:uint64.nodata(3600)} or {t:uint64.nodata( 1h)} or {t:uint64.nodata("24h",strict)} or {t:uint64.nodata( "1d", strict)} or {t:uint64.nodata( "1w" , "strict" )}',
				'nodata(/t/uint64,3600s) or nodata(/t/uint64,1h) or nodata(/t/uint64,24h,"strict") or nodata(/t/uint64,1d,"strict") or nodata(/t/uint64,1w,"strict")'
			],
			[
				'{t:uint64.now()} or {t:uint64.now( )} or {t:uint64.now( "" )} or {t:uint64.now( " " )} or {t:uint64.now( "text" )}',
				'(now() or now() or now() or now() or now()) or (last(/t/uint64)<>last(/t/uint64))'
			],
			[
				'{t:uint64.percentile(#1,,10)} or {t:uint64.percentile( #1, , 20)} or {t:uint64.percentile("#1","","30")} or {t:uint64.percentile( "#1", "", "40")} or {t:uint64.percentile( "#1" , "" , "50" )} or {t:uint64.percentile(5,,5.1234)} or {t:uint64.percentile( 5, , 6.2345)} or {t:uint64.percentile("5","","7.3456")} or {t:uint64.percentile( "5", "", "8.4567")} or {t:uint64.percentile( "5" , "" , "9.5678" )} or {t:uint64.percentile(10m,,{$M: ctx})} or {t:uint64.percentile( 10m, , {$M: ctx})} or {t:uint64.percentile("10m","","{$M: ctx}")} or {t:uint64.percentile( "10m", "", "{$M: ctx}")} or {t:uint64.percentile( "10m" , "" , "{$M: ctx}" )} or {t:uint64.percentile({$M: ctx},,1)} or {t:uint64.percentile( {$M: ctx},,1)} or {t:uint64.percentile("{$M: ctx}",,1)} or {t:uint64.percentile( "{$M: ctx}",,1)} or {t:uint64.percentile( "{$M: ctx}" ,,1)} or {t:uint64.percentile(#1,,30)} or {t:uint64.percentile(#1,3600,1)} or {t:uint64.percentile(#1, 3600,1)} or {t:uint64.percentile(#1,"3600",1)} or {t:uint64.percentile(#1, "3600",1)} or {t:uint64.percentile(#1, "3600" ,1)} or {t:uint64.percentile(#1,1h,1)} or {t:uint64.percentile(#1, 1h,1)} or {t:uint64.percentile(#1,"1h",1)} or {t:uint64.percentile(#1, "1h",1)} or {t:uint64.percentile(#1, "1h" ,1)} or {t:uint64.percentile(#1,{$M: ctx},1)} or {t:uint64.percentile(#1, {$M: ctx},1)} or {t:uint64.percentile(#1,"{$M: ctx}",1)} or {t:uint64.percentile(#1, "{$M: ctx}",1)} or {t:uint64.percentile(#1, "{$M: ctx}" ,1)}',
				'percentile(/t/uint64,#1,10) or percentile(/t/uint64,#1,20) or percentile(/t/uint64,#1,30) or percentile(/t/uint64,#1,40) or percentile(/t/uint64,#1,50) or percentile(/t/uint64,5s,5.1234) or percentile(/t/uint64,5s,6.2345) or percentile(/t/uint64,5s,7.3456) or percentile(/t/uint64,5s,8.4567) or percentile(/t/uint64,5s,9.5678) or percentile(/t/uint64,10m,{$M: ctx}) or percentile(/t/uint64,10m,{$M: ctx}) or percentile(/t/uint64,10m,{$M: ctx}) or percentile(/t/uint64,10m,{$M: ctx}) or percentile(/t/uint64,10m,{$M: ctx}) or percentile(/t/uint64,{$M: ctx},1) or percentile(/t/uint64,{$M: ctx},1) or percentile(/t/uint64,{$M: ctx},1) or percentile(/t/uint64,{$M: ctx},1) or percentile(/t/uint64,{$M: ctx},1) or percentile(/t/uint64,#1,30) or percentile(/t/uint64,#1:now-3600s,1) or percentile(/t/uint64,#1:now-3600s,1) or percentile(/t/uint64,#1:now-3600s,1) or percentile(/t/uint64,#1:now-3600s,1) or percentile(/t/uint64,#1:now-3600s,1) or percentile(/t/uint64,#1:now-1h,1) or percentile(/t/uint64,#1:now-1h,1) or percentile(/t/uint64,#1:now-1h,1) or percentile(/t/uint64,#1:now-1h,1) or percentile(/t/uint64,#1:now-1h,1) or percentile(/t/uint64,#1:now-{$M: ctx},1) or percentile(/t/uint64,#1:now-{$M: ctx},1) or percentile(/t/uint64,#1:now-{$M: ctx},1) or percentile(/t/uint64,#1:now-{$M: ctx},1) or percentile(/t/uint64,#1:now-{$M: ctx},1)'
			],
			[
				'{t:uint64.prev()} or {t:uint64.prev( )} or {t:uint64.prev( "" )} or {t:uint64.prev( " " )} or {t:uint64.prev( "text" )}',
				'last(/t/uint64,#2) or last(/t/uint64,#2) or last(/t/uint64,#2) or last(/t/uint64,#2) or last(/t/uint64,#2)'
			],
			[
				'{t:uint64.sum(#1)} or {t:uint64.sum( #1)} or {t:uint64.sum("#1")} or {t:uint64.sum( "#1")} or {t:uint64.sum( "#1" )} or {t:uint64.sum(5)} or {t:uint64.sum( 5)} or {t:uint64.sum("5")} or {t:uint64.sum( "5")} or {t:uint64.sum( "5" )} or {t:uint64.sum(10m)} or {t:uint64.sum( 10m)} or {t:uint64.sum("10m")} or {t:uint64.sum( "10m")} or {t:uint64.sum( "10m" )} or {t:uint64.sum({$M: ctx})} or {t:uint64.sum( {$M: ctx})} or {t:uint64.sum("{$M: ctx}")} or {t:uint64.sum( "{$M: ctx}")} or {t:uint64.sum( "{$M: ctx}" )} or {t:uint64.sum(#1,)} or {t:uint64.sum(#1,3600)} or {t:uint64.sum(#1, 3600)} or {t:uint64.sum(#1,"3600")} or {t:uint64.sum(#1, "3600")} or {t:uint64.sum(#1, "3600" )} or {t:uint64.sum(#1,1h)} or {t:uint64.sum(#1, 1h)} or {t:uint64.sum(#1,"1h")} or {t:uint64.sum(#1, "1h")} or {t:uint64.sum(#1, "1h" )} or {t:uint64.sum(#1,{$M: ctx})} or {t:uint64.sum(#1, {$M: ctx})} or {t:uint64.sum(#1,"{$M: ctx}")} or {t:uint64.sum(#1, "{$M: ctx}")} or {t:uint64.sum(#1, "{$M: ctx}" )}',
				'sum(/t/uint64,#1) or sum(/t/uint64,#1) or sum(/t/uint64,#1) or sum(/t/uint64,#1) or sum(/t/uint64,#1) or sum(/t/uint64,5s) or sum(/t/uint64,5s) or sum(/t/uint64,5s) or sum(/t/uint64,5s) or sum(/t/uint64,5s) or sum(/t/uint64,10m) or sum(/t/uint64,10m) or sum(/t/uint64,10m) or sum(/t/uint64,10m) or sum(/t/uint64,10m) or sum(/t/uint64,{$M: ctx}) or sum(/t/uint64,{$M: ctx}) or sum(/t/uint64,{$M: ctx}) or sum(/t/uint64,{$M: ctx}) or sum(/t/uint64,{$M: ctx}) or sum(/t/uint64,#1) or sum(/t/uint64,#1:now-3600s) or sum(/t/uint64,#1:now-3600s) or sum(/t/uint64,#1:now-3600s) or sum(/t/uint64,#1:now-3600s) or sum(/t/uint64,#1:now-3600s) or sum(/t/uint64,#1:now-1h) or sum(/t/uint64,#1:now-1h) or sum(/t/uint64,#1:now-1h) or sum(/t/uint64,#1:now-1h) or sum(/t/uint64,#1:now-1h) or sum(/t/uint64,#1:now-{$M: ctx}) or sum(/t/uint64,#1:now-{$M: ctx}) or sum(/t/uint64,#1:now-{$M: ctx}) or sum(/t/uint64,#1:now-{$M: ctx}) or sum(/t/uint64,#1:now-{$M: ctx})'
			],
			[
				'{t:uint64.time()} or {t:uint64.time( )} or {t:uint64.time( "" )} or {t:uint64.time( " " )} or {t:uint64.time( "text" )}',
				'(time() or time() or time() or time() or time()) or (last(/t/uint64)<>last(/t/uint64))'
			],
			[
				'{t:uint64.timeleft(#1,,1)} or {t:uint64.timeleft( #1, , 1)} or {t:uint64.timeleft("#1","","1")} or {t:uint64.timeleft( "#1", "", "1")} or {t:uint64.timeleft( "#1" , "" , "1" )} or {t:uint64.timeleft(5,,86400)} or {t:uint64.timeleft( 5, , 86400)} or {t:uint64.timeleft("5","","86400")} or {t:uint64.timeleft( "5", "", "86400")} or {t:uint64.timeleft( "5" , "" , "86400" )} or {t:uint64.timeleft(10m,,{$M: ctx})} or {t:uint64.timeleft( 10m, , {$M: ctx})} or {t:uint64.timeleft("10m","","{$M: ctx}")} or {t:uint64.timeleft( "10m", "", "{$M: ctx}")} or {t:uint64.timeleft( "10m" , "" , "{$M: ctx}" )} or {t:uint64.timeleft({$M: ctx},,1.2345,polynomial1)} or {t:uint64.timeleft( {$M: ctx},,1.7653, polynomial2)} or {t:uint64.timeleft("{$M: ctx}",,1.3456,"polynomial3")} or {t:uint64.timeleft( "{$M: ctx}",,1.45, "polynomial4")} or {t:uint64.timeleft( "{$M: ctx}" ,,1.45, "polynomial5" )} or {t:uint64.timeleft(#1,,30,)} or {t:uint64.timeleft(#1,3600,1,polynomial6)} or {t:uint64.timeleft(#1, 3600,1, linear)} or {t:uint64.timeleft(#1,"3600",1,"exponential")} or {t:uint64.timeleft(#1, "3600",1, "logarithmic")} or {t:uint64.timeleft(#1, "3600" ,1, "power" )} or {t:uint64.timeleft(#1,1h,1)} or {t:uint64.timeleft(#1, 1h,1)} or {t:uint64.timeleft(#1,"1h",1)} or {t:uint64.timeleft(#1, "1h",1)} or {t:uint64.timeleft(#1, "1h" ,1)} or {t:uint64.timeleft(#1,{$M: ctx},1)} or {t:uint64.timeleft(#1, {$M: ctx},1)} or {t:uint64.timeleft(#1,"{$M: ctx}",1)} or {t:uint64.timeleft(#1, "{$M: ctx}",1)} or {t:uint64.timeleft(#1, "{$M: ctx}" ,1d)}',
				'timeleft(/t/uint64,#1,1) or timeleft(/t/uint64,#1,1) or timeleft(/t/uint64,#1,1) or timeleft(/t/uint64,#1,1) or timeleft(/t/uint64,#1,1) or timeleft(/t/uint64,5s,86400) or timeleft(/t/uint64,5s,86400) or timeleft(/t/uint64,5s,86400) or timeleft(/t/uint64,5s,86400) or timeleft(/t/uint64,5s,86400) or timeleft(/t/uint64,10m,{$M: ctx}) or timeleft(/t/uint64,10m,{$M: ctx}) or timeleft(/t/uint64,10m,{$M: ctx}) or timeleft(/t/uint64,10m,{$M: ctx}) or timeleft(/t/uint64,10m,{$M: ctx}) or timeleft(/t/uint64,{$M: ctx},1.2345,"polynomial1") or timeleft(/t/uint64,{$M: ctx},1.7653,"polynomial2") or timeleft(/t/uint64,{$M: ctx},1.3456,"polynomial3") or timeleft(/t/uint64,{$M: ctx},1.45,"polynomial4") or timeleft(/t/uint64,{$M: ctx},1.45,"polynomial5") or timeleft(/t/uint64,#1,30) or timeleft(/t/uint64,#1:now-3600s,1,"polynomial6") or timeleft(/t/uint64,#1:now-3600s,1,"linear") or timeleft(/t/uint64,#1:now-3600s,1,"exponential") or timeleft(/t/uint64,#1:now-3600s,1,"logarithmic") or timeleft(/t/uint64,#1:now-3600s,1,"power") or timeleft(/t/uint64,#1:now-1h,1) or timeleft(/t/uint64,#1:now-1h,1) or timeleft(/t/uint64,#1:now-1h,1) or timeleft(/t/uint64,#1:now-1h,1) or timeleft(/t/uint64,#1:now-1h,1) or timeleft(/t/uint64,#1:now-{$M: ctx},1) or timeleft(/t/uint64,#1:now-{$M: ctx},1) or timeleft(/t/uint64,#1:now-{$M: ctx},1) or timeleft(/t/uint64,#1:now-{$M: ctx},1) or timeleft(/t/uint64,#1:now-{$M: ctx},1d)'
			],
			[
				'{t:uint64.trendavg(1h,now/h)} or {t:uint64.trendavg( 1d, now/d)} or {t:uint64.trendavg("1w","now/w")} or {t:uint64.trendavg( "1M", "now/M")} or {t:uint64.trendavg( "1y" , "now/y")} or {t:uint64.trendavg({$M: ctx},{$M: ctx})} or {t:uint64.trendavg( {$M: ctx}, {$M: ctx})} or {t:uint64.trendavg("{$M: ctx}","{$M: ctx}")} or {t:uint64.trendavg( "{$M: ctx}", "{$M: ctx}")} or {t:uint64.trendavg( "{$M: ctx}" , "{$M: ctx}" )}',
				'trendavg(/t/uint64,1h:now/h) or trendavg(/t/uint64,1d:now/d) or trendavg(/t/uint64,1w:now/w) or trendavg(/t/uint64,1M:now/M) or trendavg(/t/uint64,1y:now/y) or trendavg(/t/uint64,{$M: ctx}:{$M: ctx}) or trendavg(/t/uint64,{$M: ctx}:{$M: ctx}) or trendavg(/t/uint64,{$M: ctx}:{$M: ctx}) or trendavg(/t/uint64,{$M: ctx}:{$M: ctx}) or trendavg(/t/uint64,{$M: ctx}:{$M: ctx})'
			],
			[
				'{t:uint64.trendcount(1h,now/h)} or {t:uint64.trendcount( 1d, now/d)} or {t:uint64.trendcount("1w","now/w")} or {t:uint64.trendcount( "1M", "now/M")} or {t:uint64.trendcount( "1y" , "now/y")} or {t:uint64.trendcount({$M: ctx},{$M: ctx})} or {t:uint64.trendcount( {$M: ctx}, {$M: ctx})} or {t:uint64.trendcount("{$M: ctx}","{$M: ctx}")} or {t:uint64.trendcount( "{$M: ctx}", "{$M: ctx}")} or {t:uint64.trendcount( "{$M: ctx}" , "{$M: ctx}" )}',
				'trendcount(/t/uint64,1h:now/h) or trendcount(/t/uint64,1d:now/d) or trendcount(/t/uint64,1w:now/w) or trendcount(/t/uint64,1M:now/M) or trendcount(/t/uint64,1y:now/y) or trendcount(/t/uint64,{$M: ctx}:{$M: ctx}) or trendcount(/t/uint64,{$M: ctx}:{$M: ctx}) or trendcount(/t/uint64,{$M: ctx}:{$M: ctx}) or trendcount(/t/uint64,{$M: ctx}:{$M: ctx}) or trendcount(/t/uint64,{$M: ctx}:{$M: ctx})'
			],
			[
				'{t:uint64.trenddelta(1h,now/h)} or {t:uint64.trenddelta( 1d, now/d)} or {t:uint64.trenddelta("1w","now/w")} or {t:uint64.trenddelta( "1M", "now/M")} or {t:uint64.trenddelta( "1y" , "now/y")} or {t:uint64.trenddelta({$M: ctx},{$M: ctx})} or {t:uint64.trenddelta( {$M: ctx}, {$M: ctx})} or {t:uint64.trenddelta("{$M: ctx}","{$M: ctx}")} or {t:uint64.trenddelta( "{$M: ctx}", "{$M: ctx}")} or {t:uint64.trenddelta( "{$M: ctx}" , "{$M: ctx}" )}',
				'(trendmax(/t/uint64,1h:now/h)-trendmin(/t/uint64,1h:now/h)) or (trendmax(/t/uint64,1d:now/d)-trendmin(/t/uint64,1d:now/d)) or (trendmax(/t/uint64,1w:now/w)-trendmin(/t/uint64,1w:now/w)) or (trendmax(/t/uint64,1M:now/M)-trendmin(/t/uint64,1M:now/M)) or (trendmax(/t/uint64,1y:now/y)-trendmin(/t/uint64,1y:now/y)) or (trendmax(/t/uint64,{$M: ctx}:{$M: ctx})-trendmin(/t/uint64,{$M: ctx}:{$M: ctx})) or (trendmax(/t/uint64,{$M: ctx}:{$M: ctx})-trendmin(/t/uint64,{$M: ctx}:{$M: ctx})) or (trendmax(/t/uint64,{$M: ctx}:{$M: ctx})-trendmin(/t/uint64,{$M: ctx}:{$M: ctx})) or (trendmax(/t/uint64,{$M: ctx}:{$M: ctx})-trendmin(/t/uint64,{$M: ctx}:{$M: ctx})) or (trendmax(/t/uint64,{$M: ctx}:{$M: ctx})-trendmin(/t/uint64,{$M: ctx}:{$M: ctx}))'
			],
			[
				'{t:uint64.trendmax(1h,now/h)} or {t:uint64.trendmax( 1d, now/d)} or {t:uint64.trendmax("1w","now/w")} or {t:uint64.trendmax( "1M", "now/M")} or {t:uint64.trendmax( "1y" , "now/y")} or {t:uint64.trendmax({$M: ctx},{$M: ctx})} or {t:uint64.trendmax( {$M: ctx}, {$M: ctx})} or {t:uint64.trendmax("{$M: ctx}","{$M: ctx}")} or {t:uint64.trendmax( "{$M: ctx}", "{$M: ctx}")} or {t:uint64.trendmax( "{$M: ctx}" , "{$M: ctx}" )}',
				'trendmax(/t/uint64,1h:now/h) or trendmax(/t/uint64,1d:now/d) or trendmax(/t/uint64,1w:now/w) or trendmax(/t/uint64,1M:now/M) or trendmax(/t/uint64,1y:now/y) or trendmax(/t/uint64,{$M: ctx}:{$M: ctx}) or trendmax(/t/uint64,{$M: ctx}:{$M: ctx}) or trendmax(/t/uint64,{$M: ctx}:{$M: ctx}) or trendmax(/t/uint64,{$M: ctx}:{$M: ctx}) or trendmax(/t/uint64,{$M: ctx}:{$M: ctx})'
			],
			[
				'{t:uint64.trendmin(1h,now/h)} or {t:uint64.trendmin( 1d, now/d)} or {t:uint64.trendmin("1w","now/w")} or {t:uint64.trendmin( "1M", "now/M")} or {t:uint64.trendmin( "1y" , "now/y")} or {t:uint64.trendmin({$M: ctx},{$M: ctx})} or {t:uint64.trendmin( {$M: ctx}, {$M: ctx})} or {t:uint64.trendmin("{$M: ctx}","{$M: ctx}")} or {t:uint64.trendmin( "{$M: ctx}", "{$M: ctx}")} or {t:uint64.trendmin( "{$M: ctx}" , "{$M: ctx}" )}',
				'trendmin(/t/uint64,1h:now/h) or trendmin(/t/uint64,1d:now/d) or trendmin(/t/uint64,1w:now/w) or trendmin(/t/uint64,1M:now/M) or trendmin(/t/uint64,1y:now/y) or trendmin(/t/uint64,{$M: ctx}:{$M: ctx}) or trendmin(/t/uint64,{$M: ctx}:{$M: ctx}) or trendmin(/t/uint64,{$M: ctx}:{$M: ctx}) or trendmin(/t/uint64,{$M: ctx}:{$M: ctx}) or trendmin(/t/uint64,{$M: ctx}:{$M: ctx})'
			],
			[
				'{t:uint64.trendsum(1h,now/h)} or {t:uint64.trendsum( 1d, now/d)} or {t:uint64.trendsum("1w","now/w")} or {t:uint64.trendsum( "1M", "now/M")} or {t:uint64.trendsum( "1y" , "now/y")} or {t:uint64.trendsum({$M: ctx},{$M: ctx})} or {t:uint64.trendsum( {$M: ctx}, {$M: ctx})} or {t:uint64.trendsum("{$M: ctx}","{$M: ctx}")} or {t:uint64.trendsum( "{$M: ctx}", "{$M: ctx}")} or {t:uint64.trendsum( "{$M: ctx}" , "{$M: ctx}" )}',
				'trendsum(/t/uint64,1h:now/h) or trendsum(/t/uint64,1d:now/d) or trendsum(/t/uint64,1w:now/w) or trendsum(/t/uint64,1M:now/M) or trendsum(/t/uint64,1y:now/y) or trendsum(/t/uint64,{$M: ctx}:{$M: ctx}) or trendsum(/t/uint64,{$M: ctx}:{$M: ctx}) or trendsum(/t/uint64,{$M: ctx}:{$M: ctx}) or trendsum(/t/uint64,{$M: ctx}:{$M: ctx}) or trendsum(/t/uint64,{$M: ctx}:{$M: ctx})'
			],
			[
				'{Trapper:trap[1].abschange()} > 10'.
				' and {Trapper:trap[1].abschange()} <> "{20727}"',

				'abs(change(/Trapper/trap[1])) > 10'.
				' and abs(change(/Trapper/trap[1])) <> "{20727}"'
			],
			[
				'{Trapper:trap[1].avg(30m)} > 0'.
				' and {Trapper:trap[1].avg(60)} > 1'.
				' and {Trapper:trap[1].avg(#10)} > 3'.
				' and {Trapper:trap[1].avg(60,3600)} > 4'.
				' and {Trapper:trap[1].avg(1m,1h)} > 5',

				'avg(/Trapper/trap[1],30m) > 0'.
				' and avg(/Trapper/trap[1],60s) > 1'.
				' and avg(/Trapper/trap[1],#10) > 3'.
				' and avg(/Trapper/trap[1],60s:now-3600s) > 4'.
				' and avg(/Trapper/trap[1],1m:now-1h) > 5'
			],
			[
				'{Trapper:trap[1].change()} = 10',
				'change(/Trapper/trap[1]) = 10'
			],
			[
				'{Trapper:trap[1].date()} > 0'.
				' and {Trapper:trap[2].last()} > 0',

				'date() > 0'.
				' and last(/Trapper/trap[2]) > 0'
			],
			[
				'{Trapper:trap[1].dayofmonth()} > 0 and {Trapper2:trap[1].last()} > 0',
				'(dayofmonth() > 0 and last(/Trapper2/trap[1]) > 0) or (last(/Trapper/trap[1])<>last(/Trapper/trap[1]))'
			],
			[
				'{Trapper:trap[1].delta(30m)} > 0'.
				' and {Trapper:trap[1].delta(60)} > 1'.
				' and {Trapper:trap[1].delta(#10)} > 3'.
				' and {Trapper:trap[1].delta(60,3600)} > 4'.
				' and {Trapper:trap[1].delta(1m,1h)} > 5',

				'(max(/Trapper/trap[1],30m)-min(/Trapper/trap[1],30m)) > 0'.
				' and (max(/Trapper/trap[1],60s)-min(/Trapper/trap[1],60s)) > 1'.
				' and (max(/Trapper/trap[1],#10)-min(/Trapper/trap[1],#10)) > 3'.
				' and (max(/Trapper/trap[1],60s:now-3600s)-min(/Trapper/trap[1],60s:now-3600s)) > 4'.
				' and (max(/Trapper/trap[1],1m:now-1h)-min(/Trapper/trap[1],1m:now-1h)) > 5'
			],
			[
				'{Trapper:trap[1].diff()} = 0',
				'(last(/Trapper/trap[1],#1)<>last(/Trapper/trap[1],#2)) = 0'
			],
			[
				'{Trapper:trap[1].fuzzytime(60)} > 0',
				'fuzzytime(/Trapper/trap[1],60s) > 0'
			],
			[
				'{Trapper:trap[1].max(30m)} > 0'.
				' and {Trapper:trap[1].max(60)} > 1'.
				' and {Trapper:trap[1].max(#10)} > 3'.
				' and {Trapper:trap[1].max(60,3600)} > 4'.
				' and {Trapper:trap[1].max(1m,1h)} > 5',

				'max(/Trapper/trap[1],30m) > 0'.
				' and max(/Trapper/trap[1],60s) > 1'.
				' and max(/Trapper/trap[1],#10) > 3'.
				' and max(/Trapper/trap[1],60s:now-3600s) > 4'.
				' and max(/Trapper/trap[1],1m:now-1h) > 5'
			],
			[
				'{Trapper:trap[1].min(30m)} > 0'.
				' and {Trapper:trap[1].min(60)} > 1'.
				' and {Trapper:trap[1].min(#10)} > 3'.
				' and {Trapper:trap[1].min(60,3600)} > 4'.
				' and {Trapper:trap[1].min(1m,1h)} > 5',

				'min(/Trapper/trap[1],30m) > 0'.
				' and min(/Trapper/trap[1],60s) > 1'.
				' and min(/Trapper/trap[1],#10) > 3'.
				' and min(/Trapper/trap[1],60s:now-3600s) > 4'.
				' and min(/Trapper/trap[1],1m:now-1h) > 5'
			],
			[
				'{Trapper:trap[1].nodata(60)} > 0 and {Trapper:trap[1].nodata(5m)} > 0',
				'nodata(/Trapper/trap[1],60s) > 0 and nodata(/Trapper/trap[1],5m) > 0'
			],
			[
				'{Trapper:trap[1].now()} > 0 and {Trapper2:trap[1].now()} > 0',

				'(now() > 0 and now() > 0)'.
				' or (last(/Trapper/trap[1])<>last(/Trapper/trap[1])) or (last(/Trapper2/trap[1])<>last(/Trapper2/trap[1]))'
			],
			[
				'{Trapper:trap[1].percentile(30m,,50)} > 0'.
				' and {Trapper:trap[1].percentile(60, ,60)} > 1'.
				' and {Trapper:trap[1].percentile(#10, ,70)} > 3'.
				' and {Trapper:trap[1].percentile(60,3600,80)} > 4'.
				' and {Trapper:trap[1].percentile(1m,1h,90)} > 5',

				'percentile(/Trapper/trap[1],30m,50) > 0'.
				' and percentile(/Trapper/trap[1],60s,60) > 1'.
				' and percentile(/Trapper/trap[1],#10,70) > 3'.
				' and percentile(/Trapper/trap[1],60s:now-3600s,80) > 4'.
				' and percentile(/Trapper/trap[1],1m:now-1h,90) > 5'
			],
			[
				'{Trapper:trap[1].sum(30m)} > 0'.
				' and {Trapper:trap[1].sum(60)} > 1'.
				' and {Trapper:trap[1].sum(#10)} > 3'.
				' and {Trapper:trap[1].sum(60,3600)} > 4'.
				' and {Trapper:trap[1].sum(1m,1h)} > 5',

				'sum(/Trapper/trap[1],30m) > 0'.
				' and sum(/Trapper/trap[1],60s) > 1'.
				' and sum(/Trapper/trap[1],#10) > 3'.
				' and sum(/Trapper/trap[1],60s:now-3600s) > 4'.
				' and sum(/Trapper/trap[1],1m:now-1h) > 5'
			],
			[
				'{Trapper:trap[1].time()} > 0 and {Trapper:trap[1].last()} <> 0',
				'time() > 0 and last(/Trapper/trap[1]) <> 0'
			],
			[
				'{Trapper:trap[1].trendavg(1h, now/h-1d)} > 0',
				'trendavg(/Trapper/trap[1],1h:now/h-1d) > 0'
			],
			[
				'{Trapper:trap[1].trendcount(1h, now/h-1d)} > 0',
				'trendcount(/Trapper/trap[1],1h:now/h-1d) > 0'
			],
			[
				'1 and {Trapper:trap[1].trenddelta(1h, now/h-1d)} > 0',
				'1 and (trendmax(/Trapper/trap[1],1h:now/h-1d)-trendmin(/Trapper/trap[1],1h:now/h-1d)) > 0'
			],
			[
				'{Trapper:trap[1].trendmax(1h, now/h-1d)} > 0',
				'trendmax(/Trapper/trap[1],1h:now/h-1d) > 0'
			],
			[
				'{Trapper:trap[1].trendmin(1h, now/h-1d)} > 0',
				'trendmin(/Trapper/trap[1],1h:now/h-1d) > 0'
			],
			[
				'{Trapper:trap[1].trendsum(1h, now/h-1d)} > 0',
				'trendsum(/Trapper/trap[1],1h:now/h-1d) > 0'
			],
			[
				'{Trapper:trap[2].band(#1, 32)} > 0 and {Trapper:trap[2].band(#2, 64, 1h)} > 0',
				'bitand(last(/Trapper/trap[2],#1),32) > 0 and bitand(last(/Trapper/trap[2],#2:now-1h),64) > 0'
			],
			[
				'{Trapper:trap[2].forecast(#10,,100)} > 0'.
				' and {Trapper:trap[2].forecast(3600,7200,600,linear,avg)} > 0'.
				' and {Trapper:trap[2].forecast(30m,1d,600,,avg)} > 0',

				'forecast(/Trapper/trap[2],#10,100s) > 0'.
				' and forecast(/Trapper/trap[2],3600s:now-7200s,600s,"linear","avg") > 0'.
				' and forecast(/Trapper/trap[2],30m:now-1d,600s,,"avg") > 0'
			],

			[
				'{Trapper:trap[2].timeleft(#10,,100)} > 0'.
				' and {Trapper:trap[2].timeleft(3600,7200,600,linear)} > 0'.
				' and {Trapper:trap[2].timeleft(30m,1d,600)} > 0',

				'timeleft(/Trapper/trap[2],#10,100) > 0'.
				' and timeleft(/Trapper/trap[2],3600s:now-7200s,600,"linear") > 0'.
				' and timeleft(/Trapper/trap[2],30m:now-1d,600) > 0'
			],
			[
				'{Trapper:trap[3].count(#1, 0, eq)} > 0'.
				' and {Trapper:trap[3].count(#1,0,eq)} > 0'.
				' and {Trapper:trap[3].count(5m, "xyz", regexp, 2h)} > 0'.
				' and {Trapper:trap[3].count(5m,"xyz",regexp,2h)} > 0'.
				' and {Trapper:trap[2].count(5m, 10, iregexp, 1h)} > 0'.
				' and {Trapper:trap[1].count(5m, 100, gt, 2d)} > 0'.
				' and {Trapper:trap[1].count(1m, 32, band)} > 0'.
				' and {Trapper:trap[1].count(1m, 32/8, band)} > 0'.
				' and {Trapper:trap[1].count(10m)} > 0',

				'count(/Trapper/trap[3],#1,"eq","0") > 0'.
				' and count(/Trapper/trap[3],#1,"eq","0") > 0'.
				' and count(/Trapper/trap[3],5m:now-2h,"regexp","xyz") > 0'.
				' and count(/Trapper/trap[3],5m:now-2h,"regexp","xyz") > 0'.
				' and count(/Trapper/trap[2],5m:now-1h,"iregexp","10") > 0'.
				' and count(/Trapper/trap[1],5m:now-2d,"gt","100") > 0'.
				' and count(/Trapper/trap[1],1m,"bitand","32") > 0'.
				' and count(/Trapper/trap[1],1m,"bitand","32/8") > 0'.
				' and count(/Trapper/trap[1],10m) > 0'
			],
			[
				'{Trapper:trap[3].iregexp("^error", #10)} > 0'.
				' and {Trapper:trap[3].iregexp("^critical", 60)} > 0'.
				' and {Trapper:trap[3].iregexp("^warning", 5m)} > 0',

				'find(/Trapper/trap[3],#10,"iregexp","^error") > 0'.
				' and find(/Trapper/trap[3],60s,"iregexp","^critical") > 0'.
				' and find(/Trapper/trap[3],5m,"iregexp","^warning") > 0'
			],
			[
				'{Trapper:trap[3].last()} > 0'.
				' and {Trapper:trap[3].last(#5)} > 0'.
				' and {Trapper:trap[3].last(#10,3600)} > 0'.
				' and {Trapper:trap[3].last(#1,1d)} > 0',

				'last(/Trapper/trap[3]) > 0'.
				' and last(/Trapper/trap[3],#5) > 0'.
				' and last(/Trapper/trap[3],#10:now-3600s) > 0'.
				' and last(/Trapper/trap[3],#1:now-1d) > 0'
			],
			[
				'{Trapper:trap[3].prev()} > 0',
				'last(/Trapper/trap[3],#2) > 0'
			],
			[
				'{Trapper:trap[3].regexp("^error", #10)} > 0'.
				' and {Trapper:trap[3].regexp("^critical", 60)} > 0'.
				' and {Trapper:trap[3].regexp("^warning", 5m)} > 0',

				'find(/Trapper/trap[3],#10,"regexp","^error") > 0'.
				' and find(/Trapper/trap[3],60s,"regexp","^critical") > 0'.
				' and find(/Trapper/trap[3],5m,"regexp","^warning") > 0'
			],
			[
				'{Trapper:trap[3].str("^error", #10)} > 0'.
				' and {Trapper:trap[3].str("^critical", 60)} > 0'.
				' and {Trapper:trap[3].str("^warning", 5m)} > 0',

				'find(/Trapper/trap[3],#10,"like","^error") > 0'.
				' and find(/Trapper/trap[3],60s,"like","^critical") > 0'.
				' and find(/Trapper/trap[3],5m,"like","^warning") > 0'
			],
			[
				'{Trapper:trap[3].strlen(30m)} > 0'.
				' and {Trapper:trap[3].strlen(60)} > 1'.
				' and {Trapper:trap[3].strlen(#10)} > 3'.
				' and {Trapper:trap[3].strlen(60,3600)} > 4'.
				' and {Trapper:trap[3].strlen(1m,1h)} > 5',

				'length(last(/Trapper/trap[3])) > 0'.
				' and length(last(/Trapper/trap[3])) > 1'.
				' and length(last(/Trapper/trap[3],#10)) > 3'.
				' and length(last(/Trapper/trap[3],#1:now-3600s)) > 4'.
				' and length(last(/Trapper/trap[3],#1:now-1h)) > 5'
			],
			[
				'{Trapper:trap[4].logeventid("^error")} > 0',
				'logeventid(/Trapper/trap[4],,"^error") > 0'
			],
			[
				'{Trapper:trap[4].logseverity()} > 0',
				'logseverity(/Trapper/trap[4]) > 0'
			],
			[
				'{Trapper:trap[4].logsource("^system$")} > 0',
				'logsource(/Trapper/trap[4],,"^system$") > 0'
			],
			[
				'{Trapper:trap[1].change()} = 10'.
				' or {Trapper:trap[2].change()} = 100'.
				' or {Trapper:trap[3].str(error)} <> 0',

				'change(/Trapper/trap[1]) = 10'.
				' or change(/Trapper/trap[2]) = 100'.
				' or find(/Trapper/trap[3],,"like","error") <> 0'
			],
			[
				'{Trapper:trap[1].dayofweek()} > 0'.
				' or {Trapper:trap[2].last()} > 0',

				'dayofweek() > 0'.
				' or last(/Trapper/trap[2]) > 0'
			],
			[
				'{Trapper:trap[1].dayofweek()} > 0',
				'(dayofweek() > 0) or (last(/Trapper/trap[1])<>last(/Trapper/trap[1]))'
			],
			[
				'{Trapper:trap[1].dayofweek()} > 0'.
				' and {Host:trap[1].last()} > 0',

				'(dayofweek() > 0 and last(/Host/trap[1]) > 0)'.
				' or (last(/Trapper/trap[1])<>last(/Trapper/trap[1]))'
			]
		];
	}

	public function shortExpressionProvideData() {
		return [
			'enrich simple trigger expression' => [
				[
					'expression' => '{dayofweek()}=0',
					'host' => 'Zabbix server',
					'item' => 'trap'
				],
				'expression' => '(dayofweek()=0) or (last(/Zabbix server/trap)<>last(/Zabbix server/trap))'
			]
		];
	}

	/**
	 * @dataProvider simpleProviderData
	 *
	 * @param string $old_expression
	 * @param string $new_expression
	 */
	public function testSimpleConversion(string $old_expression, string $new_expression) {
		$this->assertSame($new_expression, $this->converter->convert(['expression' => $old_expression]));
	}

	/**
	 * @dataProvider shortExpressionProvideData
	 *
	 * @param array  $old_expression
	 * @param string $new_expression
	 */
	public function testShortExpressionConversion(array $old_expression, string $new_expression) {
		$this->assertSame($new_expression, $this->converter->convert($old_expression));
	}
}
