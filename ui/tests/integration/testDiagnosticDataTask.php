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
				'request' => [],
				'expected_error' => 'Invalid parameter "/1/request": cannot be empty.'
			],
			[
				'request' => [
					'unsupported_key' => null
				],
				'expected_error' => 'Invalid parameter "/1/request": unexpected parameter "unsupported_key".'
			],
			[
				'request' => [
					'historycache' => []
				],
				'expected_error' => 'Invalid parameter "/1/request/historycache": cannot be empty.'
			],
			[
				'request' => [
					'historycache' => [
						'stats' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/request/historycache/stats": an array is expected.'
			],
			[
				'request' => [
					'historycache' => [
						'top' => [
							'all' => 10
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/request/historycache/top": unexpected parameter "all".'
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
						'stats' => ['all']
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
				$this->waitUntilTaskIsDone($taskid, 3);

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
	 * @param int $sleep   Seconds to wait before make first check.
	 *                     First timeout may differ because in most cases task is executed very quickly.
	 *
	 * @throws Exception
	 */
	protected function waitUntilTaskIsDone(int $taskid, int $sleep = 10) {
		$max = 150;
		$idle = 0;

		do {
			sleep((int) $sleep);
			$idle += $sleep;
			$sleep = 10;

			$is_done = (bool) CDBHelper::getCount(
				'SELECT NULL'.
				' FROM task'.
				' WHERE taskid='.$taskid.
					' AND status > '.ZBX_TM_STATUS_INPROGRESS
			);

			if (!$is_done && $idle >= $max) {
				throw new Exception('Failed to wait for task '.$taskid.' to be executed.');
			}
		}
		while (!$is_done);
	}
}
