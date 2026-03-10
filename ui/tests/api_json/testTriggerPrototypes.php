<?php
/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once __DIR__.'/../include/CAPITest.php';

class testTriggerPrototypes extends CAPITest {

	public static function triggerprototype_get_data() {
		$host = 'with_lld_discovery';
		$triggerids = ['30001', '30003'];

		return [
			'Basic filter by ID' => [
				'params' => [
					'output' => ['triggerid'],
					'triggerids' => $triggerids
				],
				'expect' => [
					'error' => null,
					'triggerids' => $triggerids
				]
			],
			'Host parameter matched' => [
				'params' => [
					'output' => ['triggerid'],
					'triggerids' => $triggerids,
					'host' => $host
				],
				'expect' => [
					'error' => null,
					'triggerids' => $triggerids
				]
			],
			'Host parameter not matched' => [
				'params' => [
					'output' => ['triggerid'],
					'triggerids' => $triggerids,
					'host' => 'Not '.$host
				],
				'expect' => [
					'error' => null,
					'triggerids' => []
				]
			],
			'Filter host parameter matched' => [
				'params' => [
					'output' => ['triggerid'],
					'triggerids' => $triggerids,
					'filter' => [
						'host' => $host
					]
				],
				'expect' => [
					'error' => null,
					'triggerids' => $triggerids
				]
			],
			'One of filter host parameters matched' => [
				'params' => [
					'output' => ['triggerid'],
					'triggerids' => $triggerids,
					'filter' => [
						'host' => ['Not '.$host, $host]
					]
				],
				'expect' => [
					'error' => null,
					'triggerids' => $triggerids
				]
			],
			'Filter host parameter not matched' => [
				'params' => [
					'output' => ['triggerid'],
					'triggerids' => $triggerids,
					'filter' => [
						'host' => 'Not '.$host
					]
				],
				'expect' => [
					'error' => null,
					'triggerids' => []
				]
			]
		];
	}

	/**
	* @dataProvider triggerprototype_get_data
	*/
	public function testTriggerPrototype_Get($params, $expect) {
		$response = $this->call('triggerprototype.get', $params, $expect['error']);

		if ($expect['error'] !== null) {
			return;
		}

		$triggerids = array_column($response['result'], 'triggerid');
		sort($triggerids);
		sort($expect['triggerids']);

		$this->assertSame($expect['triggerids'], $triggerids);
	}
}
