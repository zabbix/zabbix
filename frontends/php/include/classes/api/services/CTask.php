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
 * Class containing methods for operations with task.
 */
class CTask extends CApiService {

	/**
	 * @param array        $task             Task to create.
	 * @param string|array $task['itemids']  Array of item and LLD rule IDs to create tasks for.
	 *
	 * @return array
	 */
	public function create(array $task) {
		$this->validateCreate($task);

		// Check if tasks for items and LLD rules already exist.
		$db_tasks = DBselect(
			'SELECT t.taskid,tcn.itemid'.
			' FROM task t,task_check_now tcn'.
			' WHERE t.taskid=tcn.taskid'.
				' AND t.type='.ZBX_TM_TASK_CHECK_NOW.
				' AND t.status='.ZBX_TM_STATUS_NEW.
				' AND '.dbConditionId('tcn.itemid', $task['itemids'])
		);

		$item_tasks = [];

		foreach ($task['itemids'] as $itemid) {
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
					'type' => $task['type'],
					'status' => ZBX_TM_STATUS_NEW,
					'clock' => $time,
					'ttl' => SEC_PER_DAY
				];
				$ins_check_now_tasks[] = [
					'taskid' => $taskid,
					'itemid' => $itemid
				];

				$item_tasks[$itemid] = $taskid++;
			}

			DB::insert('task', $ins_tasks, false);
			DB::insert('task_check_now', $ins_check_now_tasks);
		}

		return ['taskids' => array_values($item_tasks)];
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
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'type' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_TM_TASK_CHECK_NOW])],
			'itemids' =>	['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $task, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check if user has permissions to items and LLD rules.
		$items = API::Item()->get([
			'output' => ['itemid', 'type', 'hostid', 'status'],
			'itemids' => $task['itemids'],
			'templated' => false,
			'editable' => true
		]);

		$discovery_rules = [];
		$itemids_cnt = count($task['itemids']);
		$items_cnt = count($items);

		if ($items_cnt != $itemids_cnt) {
			$discovery_rules = API::DiscoveryRule()->get([
				'output' => ['itemid', 'type', 'hostid', 'status'],
				'itemids' => $task['itemids'],
				'templated' => false,
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
			'nopermissions' => true
		]);

		// Check host status. Allow only monitored hosts.
		foreach ($hosts as $host) {
			if ($host['status'] != HOST_STATUS_MONITORED) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot send request: %1$s.', _('host is not monitored')));
			}
		}
	}
}
