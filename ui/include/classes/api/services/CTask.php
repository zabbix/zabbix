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

		$output_fields = ['taskid', 'type', 'status', 'clock', 'ttl', 'proxyid', 'request', 'result'];

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
	 * @param array        $tasks[]['proxyid']                  (optional) Proxy to get diagnostic data about.
	 *
	 * @return array
	 */
	public function create(array $tasks): array {
		$check_now_itemids = $this->validateCreate($tasks);

		$tasks_by_types = [
			ZBX_TM_DATA_TYPE_DIAGINFO => [],
			ZBX_TM_DATA_TYPE_PROXYIDS => [],
			ZBX_TM_TASK_CHECK_NOW => []
		];

		foreach ($tasks as $index => $task) {
			if ($task['type'] == ZBX_TM_TASK_CHECK_NOW) {
				$tasks_by_types[$task['type']][$index] = [
					'type' => $task['type'],
					'request' => [
						'itemid' => $check_now_itemids[$task['request']['itemid']]
					]
				];
			}
			else {
				$tasks_by_types[$task['type']][$index] = $task;
			}
		}

		$return = $this->createTasksDiagInfo($tasks_by_types[ZBX_TM_DATA_TYPE_DIAGINFO])
			+ $this->createTasksProxyIds($tasks_by_types[ZBX_TM_DATA_TYPE_PROXYIDS])
			+ $this->createTasksCheckNow($tasks_by_types[ZBX_TM_TASK_CHECK_NOW]);

		ksort($return);

		return ['taskids' => array_values($return)];
	}

	/**
	 * Validates the input for create method and return valid item IDs. Meaning that if dependent item ID was given, try
	 * to find a valid master item. If valid master items were found return those item IDs.
	 *
	 * @param array $tasks  Tasks to validate.
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	protected function validateCreate(array &$tasks): array {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_TM_DATA_TYPE_DIAGINFO, ZBX_TM_DATA_TYPE_PROXYIDS, ZBX_TM_TASK_CHECK_NOW])],
			'request' =>		['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
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
									['if' => ['field' => 'type', 'in' => ZBX_TM_DATA_TYPE_PROXYIDS], 'type' => API_OBJECT, 'fields' => [
										'proxyids' =>	['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => true]
									]],
									['if' => ['field' => 'type', 'in' => ZBX_TM_TASK_CHECK_NOW], 'type' => API_OBJECT, 'fields' => [
										'itemid' => ['type' => API_ID, 'flags' => API_REQUIRED]
									]]
								]],
			'proxyid' =>	['type' => API_ID, 'default' => 0]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $tasks, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$min_permissions = USER_TYPE_ZABBIX_USER;
		$itemids_editable = [];
		$proxyids = [];

		foreach ($tasks as $task) {
			switch ($task['type']) {
				case ZBX_TM_DATA_TYPE_DIAGINFO:
					$min_permissions = USER_TYPE_SUPER_ADMIN;

					$proxyids[$task['proxyid']] = true;
					break;

				case ZBX_TM_DATA_TYPE_PROXYIDS:
					$min_permissions = USER_TYPE_SUPER_ADMIN;

					$proxyids = array_fill_keys($task['request']['proxyids'], true);
					break;

				case ZBX_TM_TASK_CHECK_NOW:
					$itemids_editable[$task['request']['itemid']] = true;
					break;
			}
		}
		unset($proxyids[0]);

		if (self::$userData['type'] < $min_permissions) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$this->checkProxyIds(array_keys($proxyids));
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
			'SELECT t.taskid,tcn.itemid'.
			' FROM task t,task_check_now tcn'.
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
			$item_cnt = count(array_keys(array_flip($itemids)));
			$taskid = DB::reserveIds('task', $item_cnt);
			$task_rows = [];
			$task_check_now_rows = [];
			$time = time();
			$itemids_taskids = [];

			foreach ($itemids as $index => $itemid) {
				// Use already existing task ID and do not create a duplicate record in DB.
				if (array_key_exists($itemid, $itemids_taskids)) {
					$return[$index] = $itemids_taskids[$itemid];
				}
				else {
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
					$itemids_taskids[$itemid] = $taskid;
					$taskid = bcadd($taskid, 1, 0);
				}
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
	 * @param array    $tasks[]['proxyid']                  Proxy to get diagnostic data about.
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
				'proxyid' => $task['proxyid']
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

	/**
	 * @param array $tasks
	 *
	 * @return array
	 */
	protected function createTasksProxyIds(array $tasks): array {
		$task_rows = [];
		$task_data_rows = [];
		$proxyids = [];
		$return = [];
		$taskid = DB::reserveIds('task', count($tasks));

		foreach ($tasks as $index => $task) {
			$task_rows[] = [
				'taskid' => $taskid,
				'type' => ZBX_TM_TASK_DATA,
				'status' => ZBX_TM_STATUS_NEW,
				'clock' => time(),
				'ttl' => SEC_PER_HOUR,
				'proxyid' => null
			];

			$task_data_rows[] = [
				'taskid' => $taskid,
				'type' => ZBX_TM_DATA_TYPE_PROXYIDS,
				'data' => json_encode([
					'proxyids' => $task['request']['proxyids']
				]),
				'parent_taskid' => $taskid
			];

			$proxyids += array_flip($task['request']['proxyids']);

			$return[$index] = $taskid;
			$taskid = bcadd($taskid, 1, 0);
		}

		DB::insertBatch('task', $task_rows, false);
		DB::insertBatch('task_data', $task_data_rows, false);

		if ($proxyids) {
			$proxies = API::Proxy()->get([
				'output' => ['name'],
				'proxyids' => array_keys($proxyids),
				'preservekeys' => true
			]);

			foreach ($proxies as $proxyid => $proxy) {
				self::addAuditLog(CAudit::ACTION_CONFIG_REFRESH, CAudit::RESOURCE_PROXY, [
					$proxyid => [
						'proxyid' => $proxyid,
						'name' => $proxy['name']
					]
				]);
			}
		}

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

		/*
		 * Array keys are the original item IDs given by user, but values are item IDs than can change whether item
		 * is dependent or not. If item is dependent key remains the same, but value changes to master item ID. Until
		 * the value is changed to top most master item ID.
		 */
		$itemid_mapping = array_combine($itemids, $itemids);

		// Check permissions.
		$items = CArrayHelper::renameObjectsKeys(API::Item()->get([
			'output' => ['type', 'name_resolved', 'status', 'flags', 'master_itemid'],
			'selectHosts' => ['name', 'status'],
			'itemids' => $itemids,
			'templated' => false,
			'editable' => !self::checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW),
			'preservekeys' => true
		]), ['name_resolved' => 'name']);

		$itemids_cnt = count($itemids);

		if (count($items) != $itemids_cnt) {
			$items += API::DiscoveryRule()->get([
				'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
				'selectHosts' => ['name', 'status'],
				'itemids' => $itemids,
				'templated' => false,
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
				$master_itemids[$item['master_itemid']] = $itemid;

				// Replace the dependent item IDs with master item ID.
				$itemid_mapping[$itemid] = $item['master_itemid'];
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
						// Look matching items and replace once again with master item ID.
						foreach ($itemid_mapping as &$itemid_new) {
							if (bccomp($itemid, $itemid_new) == 0) {
								$itemid_new = $item['master_itemid'];
							}
						}
						unset($itemid_new);

						$master_itemids[$item['master_itemid']] = $itemid;
					}
				}
			}
		}

		// Returns both original item IDs as keys and real item IDs as values.
		return $itemid_mapping;
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
			$db_items = CArrayHelper::renameObjectsKeys(API::Item()->get([
				'output' => ['type', 'name_resolved', 'status', 'flags', 'master_itemid'],
				'selectHosts' => ['name', 'status'],
				'itemids' => $itemids,
				'editable' => !self::checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW),
				'preservekeys' => true
			]), ['name_resolved' => 'name']);

			if (!$db_items) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			// Add newly found items to cache.
			$this->item_cache += $db_items;

			// Append newly found items to items requested from cache.
			$items += $db_items;
		}

		// Return only requested items either from cache or DB.
		return $items;
	}

	/**
	 * Function to check if specified proxies exist.
	 *
	 * @param array $proxyids  Proxy IDs to check.
	 *
	 * @throws Exception
	 */
	protected function checkProxyIds(array $proxyids): void {
		if (!$proxyids) {
			return;
		}

		$proxies = API::Proxy()->get([
			'countOutput' => true,
			'proxyids' => $proxyids
		]);

		if ($proxies != count($proxyids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}
}
