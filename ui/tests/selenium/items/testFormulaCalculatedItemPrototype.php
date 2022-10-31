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


require_once dirname(__FILE__).'/../common/testCalculatedFormula.php';

/**
 * @backup items
 */
class testFormulaCalculatedItemPrototype extends testCalculatedFormula {

	public $url = 'disc_prototypes.php?form=create&parent_discoveryid=44000&context=host';

	public function getItemPrototypeValidationData() {
		return [
			[
				[
					'formula' => 'avg(/host/trap,{#LLD})'
				]
			],
			[
				[
					'formula' => 'avg(/host/trap,"{#LLD}")'
				]
			],
			[
				[
					'formula' => 'avg(/host/trap,"{#LLD}h")'
				]
			],
			[
				[
					'formula' => 'bitand(last(/host/key,"{#LLD}:now-24h"),123)'
				]
			],
			[
				[
					'formula' => 'count(/host/trap,"{#LLD}m",,"0")'
				]
			],
			[
				[
					'formula' => 'count(/host/trap,"{#LLD}:now-5h","eq")'
				]
			],
			[
				[
					'formula' => 'logsource(/Trapper/trap[4],"{#LLD}:now-1h","^error")'
				]
			],
			[
				[
					'formula' => "min(//trap,\"#4:now-{#LLD}m\")"
				]
			],
			[
				[
					'formula' => 'sum(/host/trap,"5:{#LLD}")'
				]
			],
			[
				[
					'formula' => 'sum(/host/trap,"5:now/{#LLD}")'
				]
			],
			[
				[
					'formula' => 'sum(/host/trap,"{#LLD}:now/d")'
				]
			],
			[
				[
					'formula' => 'timeleft(/host/trap,"{#LLD}:now-6h",20G,"power")'
				]
			],
			[
				[
					'formula' => 'trendavg(/host/item,"1M:now/M-{#LLD}")'
				]
			],
			[
				[
					'formula' => 'trendavg(/host/item,1M:now/M-{#LLD})'
				]
			],
			[
				[
					'formula' => 'trendmin(/host/key,"3600:{#LLD}-3600")'
				]
			],
			[
				[
					'formula' => 'trendcount(/host/key,"3600:now-{#LLD}")'
				]
			],
			[
				[
					'formula' => 'trendcount(/host/key,3600:now-{#LLD})'
				]
			],
			[
				[
					'formula' => "stddevsamp(//trap,{#LLD})"
				]
			],
			[
				[
					'formula' => "sqrt(last(//trap,\"{#LLD}\"))"
				]
			],
			[
				[
					'formula' => 'between(5,(last(/host/trap)),{#LLD})'
				]
			],
			[
				[
					'formula' => 'in(5,(last(/host/trap)),{#LLD},5,10)'
				]
			],
			[
				[
					'formula' => 'bitrshift(last(/host/trap),{#LLD})'
				]
			],
			[
				[
					'formula' => "max(min_foreach(/*/trap?[group=\"Servers\"],{#LLD}))+avg(count_foreach(/*/trap?[tag=\"tag1\"],\"{#LLD}h\"))-bitrshift".
							"(last(//trap),1)/between(5,(last(//trap)),10)*fuzzytime(/host/trap,60)>=trendsum(/host/item,\"{#LLD}:now/h\")"
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'formula' => 'bitand(last(/host/key,{#LLD}:now-24h),123)',
					'error' => 'Invalid parameter "/1/params": invalid second parameter in function "last".'
				]
			]
		];
	}

	/**
	 * Test for checking formula field in calculated item prototype.
	 *
	 * @dataProvider getCommonValidationData
	 * @dataProvider getItemPrototypeValidationData
	 */
	public function testFormulaCalculatedItemPrototype_Validation($data) {
		$this->executeValidation($data, true);
	}
}
