<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/CAPITest.php';

class testHistory extends CAPITest {

	public static function history_get_data() {
		return [
			// Test item history of value_type == ITEM_VALUE_TYPE_STR ('history' => 1).
			[
				'api_request' => [
					'output' => 'extend',
					'history' => 1,
					'itemids' => ['133760']
				],
				'expected_result' => [
					[
						'itemid' => '133760',
						'clock' => '1549350960',
						'value' => '1',
						'ns' => '754460948'
					],
					[
						'itemid' => '133760',
						'clock' => '1549350962',
						'value' => '1',
						'ns' => '919404393'
					],
					[
						'itemid' => '133760',
						'clock' => '1549350965',
						'value' => '1',
						'ns' => '512878374'
					]
				],
				'expected_error' => false
			],
			// Test item history of value_type == ITEM_VALUE_TYPE_LOG ('history' => 2).
			[
				'api_request' => [
					'output' => ['value', 'severity'],
					'history' => 2,
					'itemids' => ['133761']
				],
				'expected_result' => [
					[
						'value' => '1',
						'severity' =>  '0'
					],
					[
						'value' => '2',
						'severity' => '0'
					],
					[
						'value' => '3',
						'severity' => '0'
					],
					[
						'value' => '4',
						'severity' => '0'
					],
					[
						'value' => '5',
						'severity' => '0'
					]
				],
				'expected_error' => false
			],
			// Get last 5 values of item of value_type == ITEM_VALUE_TYPE_FLOAT ('history' => 0).
			[
				'api_request' => [
					'output' => ['value', 'clock'],
					'history' => 0,
					'itemids' => ['133759'],
					'sortorder' => 'DESC',
					'sortfield' => 'clock',
					'limit' => 5
				],
				'expected_result' => [
					[
						'value' => '1.0000',
						'clock' => '1549350957'
					],
					[
						'value' => '1.0000',
						'clock' => '1549350955'
					],
					[
						'value' => '1.0000',
						'clock' => '1549350953'
					],
					[
						'value' => '0.0000',
						'clock' => '1549350950'
					],
					[
						'value' => '0.0000',
						'clock' => '1549350948'
					]
				],
				'expected_error' => false
			],
			// Get values of item of value_type == ITEM_VALUE_TYPE_UINT64 ('history' => 3) using time range selector.
			[
				'api_request' => [
					'output' => ['value', 'clock'],
					'history' => 3,
					'itemids' => ['133758'],
					'time_from' => 1549350908,
					'time_till' => 1549350909
				],
				'expected_result' => [
					[
						'value' => '3',
						'clock' => '1549350908'
					],
					[
						'value' => '4',
						'clock' => '1549350909'
					]
				],
				'expected_error' => false
			],
			// Get count of values of item of value_type == ITEM_VALUE_TYPE_TEXT ('history' => 4).
			[
				'api_request' => [
					'countOutput' => true,
					'history' => 4,
					'itemids' => ['133762']
				],
				'expected_result' => '3',
				'expected_error' => false
			],
			// Get number of history records filtering records by itemid.
			[
				'api_request' => [
					'countOutput' => true,
					'filter' => [
						'itemid' => ['133758']
					]
				],
				'expected_result' => '14',
				'expected_error' => false
			]
		];
	}

	/**
	 * @dataProvider history_get_data
	 */
	public function testHistory_Get($api_request, $expected_result, $expected_error) {
		$result = $this->call('history.get', $api_request);

		if ($expected_error === false) {
			$this->assertSame($result['result'], $expected_result);
		}
		else {
			$this->assertSame($result['data'], $expected_error);
		}
	}
}
