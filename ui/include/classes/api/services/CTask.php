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

	/*
	 * String constant to retrieve all possible fields for dyagnostic requested.
	 * Must be synchronized with server.
	 */
	const FIELD_ALL = 'all';

	/**
	 * Get results of requested ZBX_TM_TASK_DATA task.
	 *
	 * @param array $options
	 * @param array $options['taskids']  Task IDs to select data about.
	 *
	 * @return array | boolean
	 */
	public function get(array $options) {
		$this->validateGet($options);

		zbx_value2array($options['taskids']);

		$sql_parts = [
			'select'	=> ['task' => 't.taskid'],
			'from'		=> ['task' => 'task t'],
			'where'		=> [
				'type' => 't.type='.ZBX_TM_TASK_DATA,
				'taskid' => dbConditionInt('t.taskid', $options['taskids'])
			]
		];

		$limit = select_config()['search_limit'];
		$result = DBselect($this->createSelectQueryFromParts($sql_parts), $limit);

		$return = [];
		while ($row = DBfetch($result)) {
			$return[$row['taskid']] = [
				'taskid' => $row['taskid'],
				'data' => null
			];
		}

		if (count($return) != count($options['taskids'])) {
			return false;
		}

		$sql_parts = [
			'select'	=> [
				'parent_taskid' => 'tr.parent_taskid',
				'info' => 'tr.info'
			],
			'from'		=> ['task_result' => 'task_result tr'],
			'where'		=> [
				'parent_taskid' => dbConditionInt('tr.parent_taskid', array_keys($return))
			]
		];

		$result = DBselect($this->createSelectQueryFromParts($sql_parts), $limit);
		while ($row = DBfetch($result)) {
			$return[$row['parent_taskid']]['data'] = $row['info'];
		}

		return array_values($return);
	}

	/**
	 * Validate get method input values.
	 *
	 * @param array        $options             Request object for tasks to create.
	 * @param string|array $options['taskids']  Task IDs to return data about.
	 *
	 * @throws APIException
	 */
	protected function validateGet(array $options) {
		if (self::$userData['type'] < USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'taskids' => ['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * @param array        $task             Task to create.
	 * @param string|array $task['itemids']  Array of item and LLD rule IDs to create tasks for.
	 * @param array        $task['request']  Diagnostic data request.
	 *
	 * @return array
	 */
	public function create(array $task) {
		$this->validateCreate($task);

		switch ($task['type']) {
			case ZBX_TM_TASK_CHECK_NOW:
				return $this->createTaskCheckNow($task['request']);

			case ZBX_TM_TASK_DATA:
				return $this->createTaskData($task['request']);
		}
	}

	/**
	 * Validates the input for create method. Checks if user is at least a regular admin, validates user input, checks
	 * if user has permissions to given items and LLD rules, checks item and LLD rule types, checks if items and
	 * LLD rules are enabled, checks if host is monitored and it's not a template.
	 *
	 * @param array $task  Task to validate.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$task) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'type' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_TM_TASK_CHECK_NOW, ZBX_TM_TASK_DATA])],
			'request' =>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
								['if' => ['field' => 'type', 'in' => implode(',', [ZBX_TM_TASK_DATA])], 'type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
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
								]],
								['if' => ['field' => 'type', 'in' => implode(',', [ZBX_TM_TASK_CHECK_NOW])], 'type' => API_OBJECT, 'fields' => [
				'itemids' =>		['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true]
								]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $task, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		switch ($task['type']) {
			case ZBX_TM_TASK_CHECK_NOW:
				$this->validateCreateTaskCheckNow($task['request']);
				break;

			case ZBX_TM_TASK_DATA:
				$this->validateCreateTaskData();
				break;
		}
	}

	/**
	 * Special validation for ZBX_TM_TASK_CHECK_NOW create.
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
			'itemids' => $data['itemids'],
			'editable' => true
		]);

		$discovery_rules = [];
		$itemids_cnt = count($data['itemids']);
		$items_cnt = count($items);

		if ($items_cnt != $itemids_cnt) {
			$discovery_rules = API::DiscoveryRule()->get([
				'output' => ['itemid', 'type', 'hostid', 'status'],
				'itemids' => $data['itemids'],
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

			$hostids[$item['hostid']] = true;
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

			$hostids[$discovery_rule['hostid']] = true;
		}

		// Check if those are actually hosts because given hostids could actually be templateids.
		$hosts = API::Host()->get([
			'output' => ['status'],
			'hostids' => array_keys($hostids),
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
	 * Special validation for ZBX_TM_TASK_DATA create.
	 *
	 * @throws APIException
	 */
	protected function validateCreateTaskData() {
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
	 */
	protected function createTaskCheckNow(array $data): array {
		// Check if tasks for items and LLD rules already exist.
		$db_tasks = DBselect(
			'SELECT t.taskid,tcn.itemid'.
			' FROM task t, task_check_now tcn'.
			' WHERE t.taskid=tcn.taskid'.
				' AND t.type='.ZBX_TM_TASK_CHECK_NOW.
				' AND t.status='.ZBX_TM_STATUS_NEW.
				' AND '.dbConditionId('tcn.itemid', $data['itemids'])
		);

		$item_tasks = [];

		foreach ($data['itemids'] as $itemid) {
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
	 * Create ZBX_TM_TASK_DATA task.
	 *
	 * @param array    $data                  Request object for task to create.
	 * @param array    $data['historycache']  (optional) object of history cache data request.
	 * @param array    $data['valuecache']    (optional) object of value cache data request.
	 * @param array    $data['preprocessing'] (optional) object of preprocessing data request.
	 * @param array    $data['alerting']      (optional) object of alerting data request.
	 * @param array    $data['lld']	          (optional) object of lld cache data request.
	 *
	 * @throws APIException
	 */
	protected function createTaskData(array $data): array {
		$taskid = DB::reserveIds('task', 1);

		$ins_task = [
			'taskid' => $taskid,
			'type' => ZBX_TM_TASK_DATA,
			'status' => ZBX_TM_STATUS_NEW,
			'clock' => time(),
			'ttl' => SEC_PER_HOUR
		];

		$ins_data_task = [
			'taskid' => $taskid,
			'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
			'data' => json_encode($data),
			'parent_taskid' => $taskid
		];

		DB::insert('task', [$ins_task], false);
		DB::insert('task_data', [$ins_data_task], false);

		return ['taskids' => [$taskid]];
	}
}
