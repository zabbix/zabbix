<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once __DIR__.'/../common/testCalculatedFormula.php';

/**
 * @backup items
 *
 * TODO: remove ignoreBrowserErrors after DEV-4233
 * @ignoreBrowserErrors
 */
class testFormulaCalculatedItem extends testCalculatedFormula {

	public $url = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]=40001';

	public function getItemValidationData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'avg(/host/trap,{#LLD})',
					'inline_error' => [
						'Formula' => 'Incorrect expression starting from "avg(/host/trap,{#LLD})".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'avg(/host/trap,"{#LLD}")',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "avg".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'avg(/host/trap,"{#LLD}h")',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "avg".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'bitand(last(/host/key,"{#LLD}:now-24h"),123)',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "last".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'count(/host/trap,"{#LLD}m",,"0")',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "count".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'min(//trap,"#4:now-{#LLD}m")',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "min".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'sum(/host/trap,"5:{#LLD}")',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "sum".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'sum(/host/trap,"5:now/{#LLD}")',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "sum".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'timeleft(/host/trap,"{#LLD}:now-6h",20G,"power")',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "timeleft".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendavg(/host/item,"1M:now/M-{#LLD}")',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "trendavg".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendavg(/host/item,1M:now/M-{#LLD})',
					'inline_error' => [
						'Formula' => 'Incorrect expression starting from "trendavg(/host/item,1M:now/M-{#LLD})".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmin(/host/key,"3600:{#LLD}-3600")',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "trendmin".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'trendmin(/host/key,3600:{#LLD}-3600)',
					'inline_error' => [
						'Formula' => 'Incorrect expression starting from "trendmin(/host/key,3600:{#LLD}-3600)".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'stddevsamp(//trap,{#LLD})',
					'inline_error' => [
						'Formula' => 'Incorrect expression starting from "stddevsamp(//trap,{#LLD})".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'stddevsamp(//trap,"{#LLD}")',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "stddevsamp".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'between(5,(last(/host/trap)),{#LLD})',
					'inline_error' => [
						'Formula' => 'Incorrect expression starting from "between(5,(last(/host/trap)),{#LLD})".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'in(5,(last(/host/trap)),{#LLD},5,10)',
					'inline_error' => [
						'Formula' => 'Incorrect expression starting from "in(5,(last(/host/trap)),{#LLD},5,10)".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'bitrshift(last(/host/trap),{#LLD})',
					'inline_error' => [
						'Formula' => 'Incorrect expression starting from "bitrshift(last(/host/trap),{#LLD})".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'max(min_foreach(/*/trap?[group="Servers"],{#LLD}))+avg(count_foreach(/*/trap?[tag='.
							'"tag1"],"{#LLD}h"))-bitrshift(last(/host/trap),1)/between(5,(last(/host/trap)),10)*'.
							'fuzzytime(/host/trap,60)>=trendsum(/host/item,"{#LLD}:now/h")',
					'inline_error' => [
						'Formula' => 'Incorrect expression starting from "max(min_foreach(/*/trap?[group="Servers"],'.
								'{#LLD}))+avg(count_foreach(/*/trap?[tag="tag1"],"{#LLD}h"))-bitrshift(last(/host/trap)'.
								',1)/between(5,(last(/host/trap)),10)*fuzzytime(/host/trap,60)>=trendsum(/host/item,'.
								'"{#LLD}:now/h")".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'bitand(last(/host/key,{#LLD}:now-24h),123)',
					'inline_error' => [
						'Formula' => 'Incorrect expression starting from "bitand(last(/host/key,{#LLD}:now-24h),123)".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'avg(/host/trap,"{#LLD}h")',
					'inline_error' => [
						'Formula' => 'Invalid second parameter in function "avg".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'jsonpath(last(/Simple form test host/test-item-form4,#10:{#LLD}),"$.[0].last_name",'.
							'"LastName")',
					'inline_error' => [
						'Formula' => 'Incorrect expression starting from "jsonpath(last(/Simple form test'.
								' host/test-item-form4,#10:{#LLD}),"$.[0].last_name","LastName")".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'xmlxpath(last(/Simple form test host/test-item-form4,#4:{#LLD}),"/zabbix_export/'.
							'version/text()",5.0)',
					'inline_error' => [
						'Formula' => 'Incorrect expression starting from "xmlxpath(last(/Simple form test'.
								' host/test-item-form4,#4:{#LLD}),"/zabbix_export/version/text()",5.0)".'
					]
				]
			]
		];
	}

	/**
	 * Test for checking formula field in calculated item.
	 *
	 * @dataProvider getCommonValidationData
	 * @dataProvider getItemValidationData
	 */
	public function testFormulaCalculatedItem_Validation($data) {
		$this->executeValidation($data);
	}
}
