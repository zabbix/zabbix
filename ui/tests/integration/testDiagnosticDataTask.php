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
			[
				'request' => [
					'valuecache' => [
						'stats' => ['items', 'values', 'memory', 'mode'],
						'top' => ['values' => 2, 'request.values' => 2]
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
				$params = [
					'output' => ['status'],
					'taskids' => $taskid
				];

				$is_done = $this->callUntilDataIsPresent('task.get', $params, null, null,
					'testDiagnosticDataTask::checkIfTaskIsCompleted'
				);

				$this->assertTrue((bool) $is_done, 'Task '.$taskid.' is not complete.');
			}
		}
	}

	public static function checkIfTaskIsCompleted(array $response = []): bool {
		return (isset($response['result'][0]) && $response['result'][0]['status'] > ZBX_TM_STATUS_INPROGRESS);
	}
}
