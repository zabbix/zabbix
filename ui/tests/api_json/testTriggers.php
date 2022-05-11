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


require_once dirname(__FILE__).'/../include/CAPITest.php';

class testTriggers extends CAPITest {

	public static function trigger_get_data() {
		$triggerids = ['134118', '134000', '134001', '134002', '134003', '134004', '134005'];
		$dependent_triggerids = ['134004', '134005'];

		return [
			[
				'params' => [
					'output' => ['triggerids'],
					'triggerids' => $triggerids,
					'dependent' => null
				],
				'expect' => [
					'error' => null,
					'triggerids' => $triggerids
				]
			],
			[
				'params' => [
					'output' => ['triggerids'],
					'triggerids' => $triggerids,
					'dependent' => true
				],
				'expect' => [
					'error' => null,
					'triggerids' => $dependent_triggerids
				]
			],
			[
				'params' => [
					'output' => ['triggerids'],
					'triggerids' => $triggerids,
					'dependent' => false
				],
				'expect' => [
					'error' => null,
					'triggerids' => array_diff($triggerids, $dependent_triggerids)
				]
			],
			[
				'params' => [
					'output' => ['triggerids'],
					'hostids' => ['130000'],
					'dependent' => true
				],
				'expect' => [
					'error' => null,
					'triggerids' => $dependent_triggerids
				]
			],
			[
				'params' => [
					'output' => ['triggerids'],
					'hostids' => ['130000'],
					'dependent' => false
				],
				'expect' => [
					'error' => null,
					'triggerids' => array_diff($triggerids, $dependent_triggerids)
				]
			]
		];
	}

	/**
	* @dataProvider trigger_get_data
	*/
	public function testTrigger_Get($params, $expect) {
		$response = $this->call('trigger.get', $params, $expect['error']);
		if ($expect['error'] !== null) {
			return;
		}

		$triggerids = array_column($response['result'], 'triggerid');
		sort($triggerids);
		sort($expect['triggerids']);
		$this->assertSame($expect['triggerids'], $triggerids);
	}
}
