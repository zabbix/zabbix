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


require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test task.create and task.get API methods with ZBX_TM_TASK_DATA task.
 */
class testDiagnosticDataTask extends CIntegrationTest {

	public static function dataTask_dataProvider() {
		return [
			// Invalid format cases.
			[
				'request' => null,
				'expected_error' => 'Invalid parameter "/1": the parameter "request" is missing.'
			],
			[
				'request' => [
					'unsupported_key' => null
				],
				'expected_error' => 'Invalid parameter "/1/request": unexpected parameter "unsupported_key".'
			],
			[
				'request' => [
					'historycache' => [
						'top' => [
							'extend' => 10
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/request/historycache/top": unexpected parameter "extend".'
			],

			// Valid cases.
			[
				'request' => [
					'historycache' => [
						'stats' => ['items', 'values', 'memory', 'memory.data', 'memory.index'],
						'top' => ['values' => 2]
					]
				],
				'expected_error' => null
			],
			[
				'request' => [
					'valuecache' => [
						'stats' => ['items', 'values', 'memory', 'mode'],
						'top' => ['values' => 2, 'request.values' => 2]
					]
				],
				'expected_error' => null
			],
			[
				'request' => [
						'preprocessing' => [
							'stats' => ['values', 'preproc.values'],
							'top' => ['values' => 2]
						]
				],
				'expected_error' => null
			],
			[
				'request' => [
					'alerting' => [
						'stats' => ['alerts'],
						'top' => ['media.alerts' => 2, 'source.alerts' => 2]
					]
				],
				'expected_error' => null
			],
			[
				'request' => [
					'lld' => [
						'stats' => ['rules', 'values'],
						'top' => ['values' => 2]
					]
				],
				'expected_error' => null
			],
			[
				'request' => [
					'lld' => [
						'stats' => ['rules', 'values']
					]
				],
				'expected_error' => null
			],
			[
				'request' => [
					'lld' => [
						'top' => ['values' => 2]
					]
				],
				'expected_error' => null
			],
			[
				'request' => [
					'valuecache' => [
						'stats' => 'extend'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider dataTask_dataProvider
	 */
	public function testDataTask($request, $expected_error) {
		$api_request = ['type' => ZBX_TM_DATA_TYPE_DIAGINFO];
		if ($request !== null) {
			$api_request['request'] = $request;
		}

		$result = $this->call('task.create', $api_request, $expected_error);

		if ($expected_error === null && isset($result['result']['taskids'])) {
			foreach ($result['result']['taskids'] as $taskid) {
				$this->waitUntilTaskIsDone($taskid);

				$response = $this->call('task.get', [
					'output' => ['status'],
					'taskids' => $taskid
				], null);

				$this->assertTrue((bool) $response['result'], 'Task '.$taskid.' not found.');
				$this->assertTrue((isset($response['result'][0]['status'])
						&& $response['result'][0]['status'] > ZBX_TM_STATUS_INPROGRESS),
					'Task '.$taskid.' is not completed.'
				);
			}
		}
	}

	/**
	 * Wait for server to complete particular task.
	 *
	 * @param int $taskid  Task ID to wait when it's completed.
	 *
	 * @throws Exception
	 */
	protected function waitUntilTaskIsDone(int $taskid) {
		$max_wait_time = 60;
		$idle = 0;

		$params = [
			'output' => ['status'],
			'taskids' => $taskid,
			'preservekeys' => true
		];

		do {
			$ret = $this->callUntilDataIsPresent('task.get', $params, 1, 0);
			$is_done = (isset($ret['result'][$taskid]) && $ret['result'][$taskid]['status'] > ZBX_TM_STATUS_INPROGRESS);

			if (!$is_done) {
				$idle += 5;
				sleep(5);
			}
		}
		while (!$is_done && $max_wait_time > $idle);

		if (!$is_done) {
			throw new Exception('Failed to wait for task '.$taskid.' to be executed.');
		}
	}
}
