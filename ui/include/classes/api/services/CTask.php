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


/**
 * Class containing methods for operations with task.
 */
class CTask extends CApiService {

	protected $tableName = 'task';
	protected $tableAlias = 't';
	protected $sortColumns = ['taskid'];

	/*
	 * String constant to retrieve all possible fields for dyagnostic requested.
	 * Must be synchronized with server.
	 */
	const FIELD_ALL = 'all';

	/**
	 * Get results of requested ZBX_TM_TASK_DATA task.
	 *
	 * @param array         $options
	 * @param string|array  $options['output']
	 * @param string|array  $options['taskids']       Task IDs to select data about.
	 * @param bool          $options['preservekeys']  Use IDs as keys in the resulting array.
	 *
	 * @return array | boolean
	 */
	public function get(array $options): array {
		if (self::$userData['type'] < USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'taskids' =>		['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true],
			// output
			'output' =>			['type' => API_OUTPUT, 'in' => implode(',', ['taskid', 'type', 'status', 'clock', 'ttl', 'proxy_hostid', 'request', 'response']), 'default' => API_OUTPUT_EXTEND],
			// flags
			'preservekeys' =>	['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$options += [
			'sortfield' => 'taskid',
			'sortorder' => ZBX_SORT_DOWN,
			'limit'		=> select_config()['search_limit']
		];

		$sql_parts = [
			'select'	=> ['task' => 't.taskid'],
			'from'		=> ['task' => 'task t'],
			'where'		=> [
				'type'		=> 't.type='.ZBX_TM_TASK_DATA,
				'taskid'	=> dbConditionInt('t.taskid', $options['taskids'])
			]
		];

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$output_request = $this->outputIsRequested('request', $options['output']);
		$output_response = $this->outputIsRequested('response', $options['output']);
		$tasks = [];

		$result = DBselect($this->createSelectQueryFromParts($sql_parts), $options['limit']);

		while ($row = DBfetch($result)) {
			if ($output_request) {
				$row['request']['data'] = json_decode($row['request_data']);
				unset($row['request_data']);
			}

			if ($output_response) {
				$row['result'] = [
					'data' => $row['response_info'] ? json_decode($row['response_info']) : [],
					'status' => $row['response_status']
				];
				unset($row['response_info'], $row['response_status']);
			}

			$tasks[$row['taskid']] = $row;
		}

		if ($tasks) {
			$tasks = $this->unsetExtraFields($tasks, ['taskid', 'clock'], $options['output']);

			if (!$options['preservekeys']) {
				$tasks = array_values($tasks);
			}
		}

		return $tasks;
	}

	/**
	 * Create tasks.
	 *
	 * @param array        $tasks                                       Tasks to create.
	 * @param string|array $tasks[]['type']                             Type of task.
	 * @param string|array $tasks[]['request']
	 * @param array        $tasks[]['request']['data']                  Request object for task to create.
	 * @param string|array $tasks[]['request']['data']['itemids']       (optional) ZBX_TM_TASK_CHECK_NOW task items.
	 * @param array        $tasks[]['request']['data']['historycache']  (optional) object of history cache data request.
	 * @param array        $tasks[]['request']['data']['valuecache']    (optional) object of value cache data request.
	 * @param array        $tasks[]['request']['data']['preprocessing'] (optional) object of preprocessing data request.
	 * @param array        $tasks[]['request']['data']['alerting']      (optional) object of alerting data request.
	 * @param array        $tasks[]['request']['data']['lld']           (optional) object of lld cache data request.
	 * @param array        $tasks[]['proxy_hostid']                     (optional) Proxy to get diagnostic data about.
	 *
	 * @return array
	 */
	public function create(array $tasks): array {
		$this->validateCreate($tasks);

		foreach ($tasks as $task) {
			switch ($task['type']) {
				case ZBX_TM_DATA_TYPE_CHECK_NOW:
					return $this->createTaskCheckNow($task['request']);

				case ZBX_TM_DATA_TYPE_DIAGINFO:
					return $this->createTaskDiagInfo($task);
			}
		}
	}

	/**
	 * Validates the input for create method.
	 *
	 * @param array $tasks  Tasks to validate.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$tasks) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
			'type' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_TM_DATA_TYPE_DIAGINFO, ZBX_TM_DATA_TYPE_CHECK_NOW])],
			'request' =>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
								['if' => ['field' => 'type', 'in' => implode(',', [ZBX_TM_DATA_TYPE_DIAGINFO])], 'type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
					'data' => ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
						'historycache' =>	['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
							'stats' =>			['type' => API_STRINGS_UTF8, 'in' => implode(',', [self::FIELD_ALL, 'items', 'values', 'memory', 'memory.data', 'memory.index'])],
							'top' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
								'values' =>			['type' => API_INT32]
							]]
						]],
						'valuecache' =>		['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
							'stats' =>			['type' => API_STRINGS_UTF8, 'in' => implode(',', [self::FIELD_ALL, 'items', 'values', 'memory', 'mode'])],
							'top' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
								'values' =>			['type' => API_INT32, 'flags' => API_ALLOW_NULL],
								'request.values' =>	['type' => API_INT32, 'flags' => API_ALLOW_NULL]
							]]
						]],
						'preprocessing' =>	['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
							'stats' =>			['type' => API_STRINGS_UTF8, 'in' => implode(',', [self::FIELD_ALL, 'values', 'preproc.values'])],
							'top' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
								'values' =>			['type' => API_INT32]
							]]
						]],
						'alerting' =>		['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
							'stats' =>			['type' => API_STRINGS_UTF8, 'in' => implode(',', [self::FIELD_ALL, 'alerts'])],
							'top' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
								'media.alerts' =>	['type' => API_INT32, 'flags' => API_ALLOW_NULL],
								'source.alerts' =>	['type' => API_INT32, 'flags' => API_ALLOW_NULL]
							]]
						]],
						'lld' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
							'stats' =>			['type' => API_STRINGS_UTF8, 'in' => implode(',', [self::FIELD_ALL, 'rules', 'values'])],
							'top' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_NULL | API_NOT_EMPTY, 'fields' => [
								'values' =>			['type' => API_INT32]
							]]
						]]
					]]
								]],
								['if' => ['field' => 'type', 'in' => implode(',', [ZBX_TM_DATA_TYPE_CHECK_NOW])], 'type' => API_OBJECT, 'fields' => [
					'data' => ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
						'itemids' => ['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true]
					]]
								]]
				]
			],
			'proxy_hostid' => ['type' => API_ID, 'default' => 0]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $tasks, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$proxy_hostids = [];

		foreach ($tasks as $task) {
			switch ($task['type']) {
				case ZBX_TM_DATA_TYPE_DIAGINFO:
					$this->validateTaskDiagInfo();

					if ($task['proxy_hostid'] != 0) {
						$proxy_hostids[$task['proxy_hostid']] = $task['proxy_hostid'];
					}
					break;

				case ZBX_TM_DATA_TYPE_CHECK_NOW:
					$this->validateCreateTaskCheckNow($task['request']);
					break;
			}
		}

		// Check if specified proxies exists.
		if ($proxy_hostids) {
			$proxies = API::Proxy()->get([
				'countOutput' => true,
				'proxyids' => $proxy_hostids
			]);

			if ($proxies != count($proxy_hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Special validation for ZBX_TM_DATA_TYPE_CHECK_NOW create.
	 *
	 * @param array        $data             Request object for tasks to create.
	 * @param string|array $data['itemids']  Array of item and LLD rule IDs to create tasks for.
	 *
	 * @throws APIException
	 */
	protected function validateCreateTaskCheckNow(array $data) {
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		// Check if user has permissions to items and LLD rules.
		$items = API::Item()->get([
			'output' => ['itemid', 'type', 'hostid', 'status'],
			'itemids' => $data['data']['itemids'],
			'editable' => true
		]);

		$discovery_rules = [];
		$itemids_cnt = count($data['data']['itemids']);
		$items_cnt = count($items);

		if ($items_cnt != $itemids_cnt) {
			$discovery_rules = API::DiscoveryRule()->get([
				'output' => ['itemid', 'type', 'hostid', 'status'],
				'itemids' => $data['data']['itemids'],
				'editable' => true
			]);

			if (count($discovery_rules) + $items_cnt != $itemids_cnt) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		// Validate item and LLD rule types and statuses, and collect host IDs for later.
		$hostids = [];
		$allowed_types = checkNowAllowedTypes();

		foreach ($items as $item) {
			if (!in_array($item['type'], $allowed_types)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot send request: %1$s.', _('wrong item type')));
			}

			if ($item['status'] != ITEM_STATUS_ACTIVE) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot send request: %1$s.', _('item is disabled')));
			}

			$hostids[$item['hostid']] = $item['hostid'];
		}

		foreach ($discovery_rules as $discovery_rule) {
			if (!in_array($discovery_rule['type'], $allowed_types)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot send request: %1$s.', _('wrong discovery rule type'))
				);
			}

			if ($discovery_rule['status'] != ITEM_STATUS_ACTIVE) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot send request: %1$s.', _('discovery rule is disabled'))
				);
			}

			$hostids[$discovery_rule['hostid']] = $discovery_rule['hostid'];
		}

		// Check if those are actually hosts because given hostids could actually be templateids.
		$hosts = API::Host()->get([
			'output' => ['status'],
			'hostids' => $hostids,
			'templated_hosts' => true,
			'nopermissions' => true
		]);

		// Check host status. Allow only monitored hosts.
		foreach ($hosts as $host) {
			if ($host['status'] != HOST_STATUS_MONITORED) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot send request: %1$s.', _('host is not monitored')));
			}
		}
	}

	/**
	 * Special validation for ZBX_TM_DATA_TYPE_DIAGINFO create.
	 *
	 * @throws APIException
	 */
	protected function validateTaskDiagInfo() {
		if (self::$userData['type'] < USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}
	}

	/**
	 * Create ZBX_TM_TASK_CHECK_NOW task.
	 *
	 * @param array        $data             Request object for tasks to create.
	 * @param string|array $data['itemids']  Array of item and LLD rule IDs to create tasks for.
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	protected function createTaskCheckNow(array $data): array {
		// Check if tasks for items and LLD rules already exist.
		$db_tasks = DBselect(
			'SELECT t.taskid,tcn.itemid'.
			' FROM task t, task_check_now tcn'.
			' WHERE t.taskid=tcn.taskid'.
				' AND t.type='.ZBX_TM_TASK_CHECK_NOW.
				' AND t.status='.ZBX_TM_STATUS_NEW.
				' AND '.dbConditionId('tcn.itemid', $data['data']['itemids'])
		);

		$item_tasks = [];

		foreach ($data['data']['itemids'] as $itemid) {
			$item_tasks[$itemid] = 0;
		}

		while ($db_task = DBfetch($db_tasks)) {
			$item_tasks[$db_task['itemid']] = $db_task['taskid'];
		}

		$itemids = [];

		foreach ($item_tasks as $itemid => $taskid) {
			if ($taskid == 0) {
				$itemids[] = $itemid;
			}
		}

		if ($itemids) {
			$taskid = DB::reserveIds('task', count($itemids));
			$ins_tasks = [];
			$ins_check_now_tasks = [];
			$time = time();

			foreach ($itemids as $i => $itemid) {
				$ins_tasks[] = [
					'taskid' => $taskid,
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'status' => ZBX_TM_STATUS_NEW,
					'clock' => $time,
					'ttl' => SEC_PER_HOUR
				];
				$ins_check_now_tasks[] = [
					'taskid' => $taskid,
					'itemid' => $itemid
				];

				$item_tasks[$itemid] = (string) $taskid++;
			}

			DB::insertBatch('task', $ins_tasks, false);
			DB::insertBatch('task_check_now', $ins_check_now_tasks, false);
		}

		return ['taskids' => array_values($item_tasks)];
	}

	/**
	 * Create ZBX_TM_DATA_TYPE_DIAGINFO task.
	 *
	 * @param array    $task
	 * @param array    $task['request']
	 * @param array    $task['request']['data']                  Request object for task to create.
	 * @param array    $task['request']['data']['historycache']  (optional) object of history cache data request.
	 * @param array    $task['request']['data']['valuecache']    (optional) object of value cache data request.
	 * @param array    $task['request']['data']['preprocessing'] (optional) object of preprocessing data request.
	 * @param array    $task['request']['data']['alerting']      (optional) object of alerting data request.
	 * @param array    $task['request']['data']['lld']	         (optional) object of lld cache data request.
	 * @param array    $task['proxy_hostid']	                 (optional) object of lld cache data request.
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	protected function createTaskDiagInfo(array $task): array {
		$taskid = DB::reserveIds('task', 1);

		$ins_task = [
			'taskid' => $taskid,
			'type' => ZBX_TM_TASK_DATA,
			'status' => ZBX_TM_STATUS_NEW,
			'clock' => time(),
			'ttl' => SEC_PER_HOUR,
			'proxy_hostid' => $task['proxy_hostid']
		];

		$ins_task_data = [
			'taskid' => $taskid,
			'type' => $task['type'],
			'data' => json_encode($task['request']['data']),
			'parent_taskid' => $taskid
		];

		DB::insert('task', [$ins_task], false);
		DB::insert('task_data', [$ins_task_data], false);

		return ['taskids' => [$taskid]];
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sql_parts) {
		$sql_parts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sql_parts);

		if ($this->outputIsRequested('request', $options['output'])) {
			$sql_parts['left_join'][] = ['alias' => 'req', 'table' => 'task_data', 'using' => 'parent_taskid'];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName()];

			$sql_parts = $this->addQuerySelect('req.data AS request_data', $sql_parts);
		}

		if ($this->outputIsRequested('response', $options['output'])) {
			$sql_parts['left_join'][] = ['alias' => 'resp', 'table' => 'task_result', 'using' => 'parent_taskid'];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName()];

			$sql_parts = $this->addQuerySelect('resp.info AS response_info', $sql_parts);
			$sql_parts = $this->addQuerySelect('resp.status AS response_status', $sql_parts);
		}

		return $sql_parts;
	}
}
