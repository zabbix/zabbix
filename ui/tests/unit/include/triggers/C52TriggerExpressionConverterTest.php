<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class C52TriggerExpressionConverterTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var C52TriggerExpressionConverter
	 */
	private $converter;

	protected function setUp() {
		$this->converter = new C52TriggerExpressionConverter();
	}

	protected function tearDown() {
		$this->converter = null;
	}

	public function simpleProviderData() {
		return [
			[
				'{Trapper:trap[1].abschange()} > 10'.
				' and {Trapper:trap[1].abschange()} <> "{20727}"',

				'abs(last(/Trapper/trap[1],1)-last(/Trapper/trap[1],2)) > 10'.
				' and abs(last(/Trapper/trap[1],1)-last(/Trapper/trap[1],2)) <> "{20727}"'
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
				'(last(/Trapper/trap[1],1)<>last(/Trapper/trap[1],2)) = 0'
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
				'band(/Trapper/trap[2],#1,32) > 0 and band(/Trapper/trap[2],#2:now-1h,64) > 0'
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
				' and {Trapper:trap[3].count(5m, "xyz", regexp, 2h)} > 0'.
				' and {Trapper:trap[2].count(5m, 10, iregexp, 1h)} > 0'.
				' and {Trapper:trap[1].count(5m, 100, gt, 2d)} > 0'.
				' and {Trapper:trap[1].count(1m, 32, band)} > 0'.
				' and {Trapper:trap[1].count(1m, 32/8, band)} > 0'.
				' and {Trapper:trap[1].count(1m)} > 0',

				'count(/Trapper/trap[3],#1,"eq","0") > 0'.
				' and count(/Trapper/trap[3],5m:now-2h,"regexp","xyz") > 0'.
				' and count(/Trapper/trap[2],5m:now-1h,"iregexp","10") > 0'.
				' and count(/Trapper/trap[1],5m:now-2d,"gt","100") > 0'.
				' and count(/Trapper/trap[1],1m,"band","32") > 0'.
				' and count(/Trapper/trap[1],1m,"band","32/8") > 0'.
				' and count(/Trapper/trap[1],1m) > 0'
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
				'last(/Trapper/trap[3],2) > 0'
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
				'{Trapper:trap[3].count(#1,0,eq)} > 0'.
				' and {Trapper:trap[3].count(5m,"xyz",regexp,2h)} > 0',

				'count(/Trapper/trap[3],#1,"eq","0") > 0'.
				' and count(/Trapper/trap[3],5m:now-2h,"regexp","xyz") > 0'
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

				'length(last(/Trapper/trap[3],30m)) > 0'.
				' and length(last(/Trapper/trap[3],60s)) > 1'.
				' and length(last(/Trapper/trap[3],#10)) > 3'.
				' and length(last(/Trapper/trap[3],60s:now-3600s)) > 4'.
				' and length(last(/Trapper/trap[3],1m:now-1h)) > 5'
			],
			[
				'{Trapper:trap[4].logeventid("^error")} > 0',
				'logeventid(/Trapper/trap[4],"^error") > 0'
			],
			[
				'{Trapper:trap[4].logseverity()} > 0',
				'logseverity(/Trapper/trap[4]) > 0'
			],
			[
				'{Trapper:trap[4].logsource("^system$")} > 0',
				'logsource(/Trapper/trap[4],"^system$") > 0'
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

	public function twoFieldExpressionProvideData() {
		return [
			'no repeating missing references' => [
				[
					'expression' => '{Trapper:trap[1].dayofweek()} > 0'.
									' and {Host:trap[1].last()} > 0',
					'recovery_expression' => '{Trapper:trap[1].dayofweek()} > 0'.
									' and {Host:trap[1].last()} > 0'
				],
				[
					'expression' => '(dayofweek() > 0 and last(/Host/trap[1]) > 0)'.
									' or (last(/Trapper/trap[1])<>last(/Trapper/trap[1]))',
					'recovery_expression' => 'dayofweek() > 0 and last(/Host/trap[1]) > 0'
				]
			],
			'are references gathered in expression field' => [
				[
					'expression' => '{Trapper:trap[1].dayofweek()} > 0',
					'recovery_expression' => '{Trapper2:trap[1].dayofweek()} > 0'
				],
				[
					'expression' => '(dayofweek() > 0)'.
									' or (last(/Trapper/trap[1])<>last(/Trapper/trap[1]))'.
									' or (last(/Trapper2/trap[1])<>last(/Trapper2/trap[1]))',
					'recovery_expression' => 'dayofweek() > 0'
				]
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
				[
					'expression' => '(dayofweek()=0) or (last(/Zabbix server/trap)<>last(/Zabbix server/trap))',
				]
			],
			'two short expressions' => [
				[
					'expression' => '{dayofweek()}=0',
					'recovery_expression' => '{dayofweek()}=0',
					'host' => 'Zabbix server',
					'item' => 'trap'
				],
				[
					'expression' => '(dayofweek()=0) or (last(/Zabbix server/trap)<>last(/Zabbix server/trap))',
					'recovery_expression' => 'dayofweek()=0'
				]
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
		$this->assertSame($new_expression, $this->converter->convert(['expression' => $old_expression])['expression']);
	}

	/**
	 * @dataProvider twoFieldExpressionProvideData
	 *
	 * @param array $old_expressions
	 * @param array $new_expressions
	 */
	public function testTwoExpressionConversion(array $old_expressions, array $new_expressions) {
		$this->assertSame($new_expressions, $this->converter->convert($old_expressions));
	}

	/**
	 * @dataProvider shortExpressionProvideData
	 *
	 * @param array $old_expression
	 * @param array $new_expression
	 */
	public function testShortExpressionConversion(array $old_expression, array $new_expression) {
		$this->assertSame($new_expression, $this->converter->convert($old_expression));
	}
}
