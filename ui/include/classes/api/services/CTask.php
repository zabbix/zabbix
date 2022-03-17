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


/**
 * Class containing methods for operations with tasks.
 */
class CTask extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'task';
	protected $tableAlias = 't';
	protected $sortColumns = ['taskid'];

	private $item_cache = [];

	const RESULT_STATUS_ERROR = -1;
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
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$output_fields = ['taskid', 'type', 'status', 'clock', 'ttl', 'proxy_hostid', 'request', 'result'];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'taskids' =>		['type' => API_IDS, 'flags' => API_NORMALIZE | API_ALLOW_NULL, 'default' => null],
			// output
			'output' =>			['type' => API_OUTPUT, 'in' => implode(',', $output_fields), 'default' => API_OUTPUT_EXTEND],
			// flags
			'preservekeys' =>	['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$options += [
			'sortfield' => 'taskid',
			'sortorder' => ZBX_SORT_DOWN,
			'limit'		=> CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT)
		];

		$sql_parts = [
			'select'	=> ['task' => 't.taskid'],
			'from'		=> ['task' => 'task t'],
			'where'		=> [
				'type'		=> 't.type='.ZBX_TM_TASK_DATA
			],
			'order'     => [],
			'group'     => []
		];

		if ($options['taskids'] !== null) {
			$sql_parts['where']['taskid'] = dbConditionInt('t.taskid', $options['taskids']);
		}

		$db_tasks = [];

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$result = DBselect($this->createSelectQueryFromParts($sql_parts), $options['limit']);

		while ($row = DBfetch($result, false)) {
			if ($this->outputIsRequested('request', $options['output'])) {
				$row['request'] = json_decode($row['request_data']);
				unset($row['request_data']);
			}

			if ($this->outputIsRequested('result', $options['output'])) {
				if ($row['result_status'] === null) {
					$row['result'] = null;
				}
				else {
					if ($row['result_status'] == self::RESULT_STATUS_ERROR) {
						$result_data = $row['result_info'];
					}
					else {
						$result_data = $row['result_info'] ? json_decode($row['result_info']) : [];
					}

					$row['result'] = [
						'data' => $result_data,
						'status' => $row['result_status']
					];
				}

				unset($row['result_info'], $row['result_status']);
			}

			$db_tasks[$row['taskid']] = $row;
		}

		if ($db_tasks) {
			$db_tasks = $this->unsetExtraFields($db_tasks, ['taskid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_tasks = array_values($db_tasks);
			}
		}

		return $db_tasks;
	}

	/**
	 * Create tasks.
	 *
	 * @param array        $tasks                               Tasks to create.
	 * @param string|array $tasks[]['type']                     Type of task.
	 * @param string       $tasks[]['request']['itemid']        Must be set for ZBX_TM_TASK_CHECK_NOW task.
	 * @param array        $tasks[]['request']['historycache']  (optional) object of history cache data request.
	 * @param array        $tasks[]['request']['valuecache']    (optional) object of value cache data request.
	 * @param array        $tasks[]['request']['preprocessing'] (optional) object of preprocessing data request.
	 * @param array        $tasks[]['request']['alerting']      (optional) object of alerting data request.
	 * @param array        $tasks[]['request']['lld']           (optional) object of lld cache data request.
	 * @param array        $tasks[]['proxy_hostid']             (optional) Proxy to get diagnostic data about.
	 *
	 * @return array
	 */
	public function create(array $tasks): array {
		$check_now_itemids = $this->validateCreate($tasks);
		$check_now_tasks = [];

		// Get diagnostic info tasks from API request.
		$diaginfo_tasks = array_filter($tasks, function($task) {
			if ($task['type'] == ZBX_TM_DATA_TYPE_DIAGINFO) {
				return $task;
			}
		});

		// Re-create check now task array from the valid item IDs.
		foreach ($check_now_itemids as $itemid) {
			$check_now_tasks[] = [
				'type' => ZBX_TM_DATA_TYPE_CHECK_NOW,
				'request' => [
					'itemid' => $itemid,
				]
			];
		}

		// Put the array back together.
		$tasks_by_types = [
			ZBX_TM_DATA_TYPE_CHECK_NOW => $check_now_tasks,
			ZBX_TM_DATA_TYPE_DIAGINFO => $diaginfo_tasks
		];

		$return = $this->createTasksCheckNow($tasks_by_types[ZBX_TM_DATA_TYPE_CHECK_NOW]);
		$return = array_merge($return, $this->createTasksDiagInfo($tasks_by_types[ZBX_TM_DATA_TYPE_DIAGINFO]));

		ksort($return);

		return ['taskids' => array_values($return)];
	}

	/**
	 * Validates the input for create method and return valid item IDs. Meaning that if dependent item ID was given, try
	 * to find a valid master item. If valid master items were found return those item IDs..
	 *
	 * @param array $tasks  Tasks to validate.
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	protected function validateCreate(array &$tasks): array {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'type' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_TM_DATA_TYPE_DIAGINFO, ZBX_TM_DATA_TYPE_CHECK_NOW])],
			'request' =>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
								['if' => ['field' => 'type', 'in' => ZBX_TM_DATA_TYPE_DIAGINFO], 'type' => API_OBJECT, 'fields' => [
				'historycache' =>	['type' => API_OBJECT, 'fields' => [
					'stats' =>			['type' => API_OUTPUT, 'in' => implode(',', ['items', 'values', 'memory', 'memory.data', 'memory.index']), 'default' => API_OUTPUT_EXTEND],
					'top' =>			['type' => API_OBJECT, 'fields' => [
						'values' =>			['type' => API_INT32]
					]]
				]],
				'valuecache' =>		['type' => API_OBJECT, 'fields' => [
					'stats' =>			['type' => API_OUTPUT, 'in' => implode(',', ['items', 'values', 'memory', 'mode']), 'default' => API_OUTPUT_EXTEND],
					'top' =>			['type' => API_OBJECT, 'fields' => [
						'values' =>			['type' => API_INT32],
						'request.values' =>	['type' => API_INT32]
					]]
				]],
				'preprocessing' =>	['type' => API_OBJECT, 'fields' => [
					'stats' =>			['type' => API_OUTPUT, 'in' => implode(',', ['values', 'preproc.values']), 'default' => API_OUTPUT_EXTEND],
					'top' =>			['type' => API_OBJECT, 'fields' => [
						'values' =>			['type' => API_INT32]
					]]
				]],
				'alerting' =>		['type' => API_OBJECT, 'fields' => [
					'stats' =>			['type' => API_OUTPUT, 'in' => 'alerts', 'default' => API_OUTPUT_EXTEND],
					'top' =>			['type' => API_OBJECT, 'fields' => [
						'media.alerts' =>	['type' => API_INT32],
						'source.alerts' =>	['type' => API_INT32]
					]]
				]],
				'lld' =>			['type' => API_OBJECT, 'fields' => [
					'stats' =>			['type' => API_OUTPUT, 'in' => implode(',', ['rules', 'values']), 'default' => API_OUTPUT_EXTEND],
					'top' =>			['type' => API_OBJECT, 'fields' => [
						'values' =>			['type' => API_INT32]
					]]
				]]
								]],
								['if' => ['field' => 'type', 'in' => ZBX_TM_DATA_TYPE_CHECK_NOW], 'type' => API_OBJECT, 'fields' => [
				'itemid' => ['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY]
								]]
			]],
			'proxy_hostid' => ['type' => API_ID, 'default' => 0]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $tasks, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$min_permissions = USER_TYPE_ZABBIX_USER;
		$itemids_editable = [];
		$proxy_hostids = [];

		foreach ($tasks as $task) {
			switch ($task['type']) {
				case ZBX_TM_DATA_TYPE_DIAGINFO:
					$min_permissions = USER_TYPE_SUPER_ADMIN;

					$proxy_hostids[$task['proxy_hostid']] = true;
					break;

				case ZBX_TM_DATA_TYPE_CHECK_NOW:
					$itemids_editable[$task['request']['itemid']] = true;
					break;
			}
		}

		unset($proxy_hostids[0]);

		if (self::$userData['type'] < $min_permissions) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$this->checkProxyHostids(array_keys($proxy_hostids));
		$itemids_editable = $this->checkEditableItems(array_keys($itemids_editable));

		return $itemids_editable;
	}

	/**
	 * Create ZBX_TM_TASK_CHECK_NOW tasks.
	 *
	 * @param array        $tasks                          Request object for tasks to create.
	 * @param string|array $tasks[]['request']['itemid']   Item or LLD rule IDs to create tasks for.
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	protected function createTasksCheckNow(array $tasks): array {
		if (!$tasks) {
			return [];
		}

		$itemids = [];
		$return = [];

		foreach ($tasks as $index => $task) {
			$itemids[$index] = $task['request']['itemid'];
		}

		// Check if tasks for items and LLD rules already exist.
		$db_tasks = DBselect(
			'SELECT t.taskid, tcn.itemid'.
			' FROM task t, task_check_now tcn'.
			' WHERE t.taskid=tcn.taskid'.
				' AND t.type='.ZBX_TM_TASK_CHECK_NOW.
				' AND t.status='.ZBX_TM_STATUS_NEW.
				' AND '.dbConditionId('tcn.itemid', $itemids)
		);

		while ($db_task = DBfetch($db_tasks)) {
			foreach (array_keys($itemids, $db_task['itemid']) as $index) {
				$return[$index] = $db_task['taskid'];
				unset($itemids[$index]);
			}
		}

		// Create new tasks.
		if ($itemids) {
			$taskid = DB::reserveIds('task', count($itemids));
			$task_rows = [];
			$task_check_now_rows = [];
			$time = time();

			foreach ($itemids as $index => $itemid) {
				$task_rows[] = [
					'taskid' => $taskid,
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'status' => ZBX_TM_STATUS_NEW,
					'clock' => $time,
					'ttl' => SEC_PER_HOUR
				];
				$task_check_now_rows[] = [
					'taskid' => $taskid,
					'itemid' => $itemid,
					'parent_taskid' => $taskid
				];

				$return[$index] = $taskid;
				$taskid = bcadd($taskid, 1, 0);
			}

			DB::insertBatch('task', $task_rows, false);
			DB::insertBatch('task_check_now', $task_check_now_rows, false);
		}

		return $return;
	}

	/**
	 * Create ZBX_TM_DATA_TYPE_DIAGINFO tasks.
	 *
	 * @param array    $tasks[]
	 * @param array    $tasks[]['request']['historycache']  (optional) object of history cache data request.
	 * @param array    $tasks[]['request']['valuecache']    (optional) object of value cache data request.
	 * @param array    $tasks[]['request']['preprocessing'] (optional) object of preprocessing data request.
	 * @param array    $tasks[]['request']['alerting']      (optional) object of alerting data request.
	 * @param array    $tasks[]['request']['lld']           (optional) object of lld cache data request.
	 * @param array    $tasks[]['proxy_hostid']             Proxy to get diagnostic data about.
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	protected function createTasksDiagInfo(array $tasks): array {
		$task_rows = [];
		$task_data_rows = [];
		$return = [];
		$taskid = DB::reserveIds('task', count($tasks));

		foreach ($tasks as $index => $task) {
			$task_rows[] = [
				'taskid' => $taskid,
				'type' => ZBX_TM_TASK_DATA,
				'status' => ZBX_TM_STATUS_NEW,
				'clock' => time(),
				'ttl' => SEC_PER_HOUR,
				'proxy_hostid' => $task['proxy_hostid']
			];

			$task_data_rows[] = [
				'taskid' => $taskid,
				'type' => $task['type'],
				'data' => json_encode($task['request']),
				'parent_taskid' => $taskid
			];

			$return[$index] = $taskid;
			$taskid = bcadd($taskid, 1, 0);
		}

		DB::insertBatch('task', $task_rows, false);
		DB::insertBatch('task_data', $task_data_rows, false);

		return $return;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sql_parts) {
		$sql_parts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sql_parts);

		if ($this->outputIsRequested('request', $options['output'])) {
			$sql_parts['left_join'][] = ['alias' => 'req', 'table' => 'task_data', 'using' => 'parent_taskid'];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName()];

			$sql_parts = $this->addQuerySelect('req.data AS request_data', $sql_parts);
		}

		if ($this->outputIsRequested('result', $options['output'])) {
			$sql_parts['left_join'][] = ['alias' => 'resp', 'table' => 'task_result', 'using' => 'parent_taskid'];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName()];

			$sql_parts = $this->addQuerySelect('resp.info AS result_info', $sql_parts);
			$sql_parts = $this->addQuerySelect('resp.status AS result_status', $sql_parts);
		}

		return $sql_parts;
	}

	/**
	 * Validate user permissions to items and LLD rules;
	 * Check if requested items are allowed to make 'check now' operation;
	 * Check if items are monitored and they belong to monitored hosts;
	 * Check if given items are dependent items. If so, find valid master items. Master item must also be monitored
	 * and of valid type. Don't return the same dependent item IDs, but return the top level master item IDs.
	 *
	 * @param array $itemids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	protected function checkEditableItems(array $itemids): array {
		if (!$itemids) {
			return [];
		}

		// Create backup of item IDs, so that later the order can be maintained.
		$itemids_og = $itemids;

		// Check permissions.
		$items = API::Item()->get([
			'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
			'selectHosts' => ['name', 'status'],
			'itemids' => $itemids,
			'editable' => !self::checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW),
			'preservekeys' => true
		]);

		$itemids_cnt = count($itemids);

		if (count($items) != $itemids_cnt) {
			$items += API::DiscoveryRule()->get([
				'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
				'selectHosts' => ['name', 'status'],
				'itemids' => $itemids,
				'editable' => !self::checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW),
				'preservekeys' => true
			]);

			if (count($items) != $itemids_cnt) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		// Validate item and LLD rule type and status.
		$allowed_types = checkNowAllowedTypes();
		$master_itemids = [];

		// Real item IDs that will be returned.
		$itemids = [];

		// Check item and LLD rule first level. Collect master item IDs if type is dependent.
		foreach ($items as $itemid => $item) {
			if (!in_array($item['type'], $allowed_types)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot send request: %1$s.',
						($item['flags'] == ZBX_FLAG_DISCOVERY_RULE)
							? _('wrong discovery rule type')
							: _('wrong item type')
					)
				);
			}

			if ($item['status'] != ITEM_STATUS_ACTIVE || $item['hosts'][0]['status'] != HOST_STATUS_MONITORED) {
				$host_name = $item['hosts'][0]['name'];
				$problem = ($item['flags'] == ZBX_FLAG_DISCOVERY_RULE)
					? _s('discovery rule "%1$s" on host "%2$s" is not monitored', $item['name'], $host_name)
					: _s('item "%1$s" on host "%2$s" is not monitored', $item['name'], $host_name);
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot send request: %1$s.', $problem));
			}

			// Collect master item IDs and real item IDs.
			if ($item['type'] == ITEM_TYPE_DEPENDENT) {
				$master_itemids[$item['master_itemid']] = true;
			}
			else {
				$itemids[$itemid] = true;
			}
		}

		// Put already collected items in cache.
		if ($master_itemids) {
			$this->item_cache = $items;

			// Get master items.
			while ($master_itemids) {
				// Get already known items from cache or DB.
				$master_items = $this->getMasterItems(array_keys($master_itemids));
				$master_itemids = [];

				// Check the master item type and status.
				foreach ($master_items as $itemid => $item) {
					if (!in_array($item['type'], $allowed_types)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Cannot send request: %1$s.', _('wrong master item type'))
						);
					}

					if ($item['status'] != ITEM_STATUS_ACTIVE || $item['hosts'][0]['status'] != HOST_STATUS_MONITORED) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot send request: %1$s.',
							_s('item "%1$s" on host "%2$s" is not monitored', $item['name'], $item['hosts'][0]['name'])
						));
					}

					/*
					 * If the master item was also another dependent item, add master item IDs for next loop, otherwise
					 * store the item ID. This will replace the original item ID from request.
					 */
					if ($item['type'] == ITEM_TYPE_DEPENDENT) {
						$master_itemids[$item['master_itemid']] = true;
					}
					else {
						$itemids[$itemid] = true;
					}
				}
			}
		}

		// Try to maintain order of originally passed item IDs.
		uksort($itemids, function($key1, $key2) use ($itemids_og) {
			return (array_search($key1, $itemids_og) > array_search($key2, $itemids_og));
		});

		// Returns real item IDs (not the ependent item IDs, but top level master item IDs).
		return array_keys($itemids);
	}

	/**
	 * Find master items by given item IDs either stored in cache or DB. Returns the item if found.
	 *
	 * @param array $itemids  An array of master item IDs.
	 *
	 * @throws APIException if item is not found or user has no permissions.
	 *
	 * @return array
	 */
	private function getMasterItems(array $itemids): array {
		$items = [];

		// First try get items from cache if possible.
		foreach ($itemids as $num => $itemid) {
			if (array_key_exists($itemid, $this->item_cache)) {
				$item = $this->item_cache[$itemid];
				$items[$itemid] = $item;
				unset($itemids[$num]);
			}
		}

		// If some items were not found in cache, select them from DB.
		if ($itemids) {
			$items = API::Item()->get([
				'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
				'selectHosts' => ['name', 'status'],
				'itemids' => $itemids,
				'editable' => !self::checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW),
				'preservekeys' => true
			]);

			if (!$items) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			// Add newly found items to cache.
			$this->item_cache += $items;
		}

		// Return only requested items either from cache or DB.
		return $items;
	}

	/**
	 * Function to check if specified proxies exists.
	 *
	 * @param array $proxy_hostids  Proxy IDs to check.
	 *
	 * @throws Exception if proxy doesn't exist.
	 */
	protected function checkProxyHostids(array $proxy_hostids): void {
		if (!$proxy_hostids) {
			return;
		}

		$proxies = API::Proxy()->get([
			'countOutput' => true,
			'proxyids' => $proxy_hostids
		]);

		if ($proxies != count($proxy_hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}
}
