<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

	/**
	 * @param array        $tasks             Array of tasks to create.
	 * @param string|array $tasks['itemids']  Array of item and LLD rule IDs to create tasks for.
	 *
	 * @return array
	 */
	public function create(array $tasks) {
		$tasks = $this->validateCreate($tasks);

		$tasks_to_create = [];
		$time = time();

		foreach ($tasks as $task) {
			foreach ($task['itemids'] as $itemid) {
				$tasks_to_create[] = [
					'type' => $task['type'],
					'status' => ZBX_TM_STATUS_NEW,
					'clock' => $time,
					'ttl' => SEC_PER_DAY
				];
			}
		}

		$taskids = DB::insert('task', $tasks_to_create);

		$check_now_to_create = [];

		foreach ($tasks as $idx => $task) {
			foreach ($task['itemids'] as $itemid) {
				$check_now_to_create[] = [
					'taskid' => $taskids[$idx],
					'itemid' => $itemid
				];
			}
		}

		DB::insert('task_check_now', $check_now_to_create);

		return ['taskids' => $taskids];
	}

	/**
	 * Validates the input for create method. Checks if user is at least a regular admin, validates user input, checks
	 * if user has permissions to given items and LLD rules, checks item and LLD rule types, checks if items and
	 * LLD rules are enabled, checks if host is monitored and it's not a template. And checks if tasks don't exist.
	 *
	 * @param array $tasks  Array of tasks to validate.
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array  Returns an array of valid tasks.
	 */
	protected function validateCreate(array $tasks) {
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', [ZBX_TM_TASK_CHECK_NOW])],
			'itemids' =>		['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $tasks, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check if user has permissions to items and LLD rules.
		$itemids = [];

		foreach ($tasks as $task) {
			$itemids = array_merge($itemids, $task['itemids']);
		}

		$items = API::Item()->get([
			'output' => ['itemid', 'type', 'hostid', 'status'],
			'itemids' => $itemids,
			'templated' => false,
			'editable' => true
		]);

		$discovery_rules = [];
		$itemids_cnt = count($itemids);
		$items_cnt = count($items);

		if ($items_cnt != $itemids_cnt) {
			$discovery_rules = API::DiscoveryRule()->get([
				'output' => ['itemid', 'type', 'hostid', 'status'],
				'itemids' => $itemids,
				'templated' => false,
				'editable' => true
			]);

			if (count($discovery_rules) + $items_cnt != $itemids_cnt) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		// Validate item and LLD rule types and statuses, and collect host IDs for later.
		$hostids = [];

		foreach ($items as $item) {
			if (!in_array($item['type'], checkNowAllowedTypes())) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot send request: %1$s.', _('wrong item type')));
			}

			if ($item['status'] != ITEM_STATUS_ACTIVE) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot send request: %1$s.', _('item is disabled')));
			}

			$hostids[$item['hostid']] = true;
		}

		foreach ($discovery_rules as $discovery_rule) {
			if (!in_array($discovery_rule['type'], checkNowAllowedTypes())) {
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
			'nopermissions' => true
		]);

		// Check host status. Allow only monitored hosts.
		foreach ($hosts as $host) {
			if ($host['status'] != HOST_STATUS_MONITORED) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot send request: %1$s.', _('host is not monitored')));
			}
		}

		// Check if task already exists.
		$itemids = zbx_objectValues($items, 'itemid');
		$discovery_ruleids = zbx_objectValues($discovery_rules, 'itemid');

		foreach ($items as $item) {
			if (DBfetch(DBselect(
					'SELECT t.taskid'.
					' FROM task t'.
					' LEFT JOIN task_check_now tcn ON t.taskid = tcn.taskid'.
					' WHERE t.type = '.ZBX_TM_TASK_CHECK_NOW.
						' AND '.dbConditionId('tcn.itemid', $itemids).
						' AND (t.status = '.ZBX_TM_STATUS_NEW.' OR t.status = '.ZBX_TM_STATUS_INPROGRESS.')'))) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot send request: %1$s.', _('item is already being checked'))
				);
			}
		}

		foreach ($discovery_rules as $discovery_rule) {
			if (DBfetch(DBselect(
					'SELECT t.taskid'.
					' FROM task t'.
					' LEFT JOIN task_check_now tcn ON t.taskid = tcn.taskid'.
					' WHERE t.type = '.ZBX_TM_TASK_CHECK_NOW.
						' AND '.dbConditionId('tcn.itemid', $discovery_ruleids).
						' AND (t.status = '.ZBX_TM_STATUS_NEW.' OR t.status = '.ZBX_TM_STATUS_INPROGRESS.')'))) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot send request: %1$s.', _('discovery rule is already being checked'))
				);
			}
		}

		return $tasks;
	}
}
