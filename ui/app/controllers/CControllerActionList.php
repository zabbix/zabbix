<?php declare(strict_types = 0);
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


class CControllerActionList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'eventsource' =>	'required|db actions.eventsource|in '.implode(',', [
									EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
									EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
								]),
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'filter_status' =>	'in '.implode(',', [-1, ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED]),
			'sort' =>			'in '.implode(',', ['name', 'status']),
			'sortorder' =>		'in '.implode(',', [ZBX_SORT_UP, ZBX_SORT_DOWN]),
			'page' =>			'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		switch ($this->getInput('eventsource')) {
			case EVENT_SOURCE_TRIGGERS:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS);

			case EVENT_SOURCE_DISCOVERY:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS);

			case EVENT_SOURCE_AUTOREGISTRATION:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS);

			case EVENT_SOURCE_INTERNAL:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS);

			case EVENT_SOURCE_SERVICE:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS);
		}

		return false;
	}

	protected function doAction(): void {
		$eventsource = $this->getInput('eventsource', EVENT_SOURCE_TRIGGERS);
		$sort_field = $this->getInput('sort', CProfile::get('web.action.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.action.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.action.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.action.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.action.list.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.action.list.filter_status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.action.list.filter_name');
			CProfile::delete('web.action.list.filter_status');
		}

		$filter = [
			'name' => CProfile::get('web.action.list.filter_name', ''),
			'status' => CProfile::get('web.action.list.filter_status', -1)
		];

		$data = [
			'eventsource' => $eventsource,
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.action.list.filter',
			'active_tab' => CProfile::get('web.action.list.filter.active', 1)
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['actions'] = API::Action()->get([
			'output' => ['actionid', 'name', 'status'],
			'search' => [
				'name' => $filter['name'] === '' ? null : $filter['name']
			],
			'filter' => [
				'eventsource' => $data['eventsource'],
				'status' => $filter['status'] == -1 ? null : $filter['status']
			],
			'sortfield' => $sort_field,
			'sortorder' => $sort_order,
			'limit' => $limit
		]);
		CArrayHelper::sort($data['actions'], [['field' => $sort_field, 'order' => $sort_order]]);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('action.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['actions'], $sort_order, (new CUrl('zabbix.php'))
			->setArgument('action', 'action.list')
			->setArgument('eventsource', $eventsource)
		);

		$db_actions = API::Action()->get([
			'output' => [],
			'selectFilter' => ['conditions'],
			'selectOperations' => ['operationtype', 'esc_step_from', 'esc_step_to', 'esc_period', 'evaltype',
				'opcommand', 'opcommand_grp', 'opcommand_hst', 'opgroup', 'opmessage', 'optemplate', 'opinventory',
				'opconditions', 'opmessage_usr', 'opmessage_grp', 'optag'
			],
			'actionids' => array_column($data['actions'], 'actionid'),
			'preservekeys' => true
		]);

		foreach ($data['actions'] as &$action) {
			$db_action = $db_actions[$action['actionid']];

			$action['filter'] = $db_action['filter'];
			$action['operations'] = $db_action['operations'];

			sortOperations($eventsource, $action['operations']);
		}
		unset($action);

		$data['actionConditionStringValues'] = actionConditionValueToString($data['actions']);

		$operations = [];
		foreach ($data['actions'] as $action) {
			foreach ($action['operations'] as $operation) {
				$operations[] = $operation;
			}
		}
		$data['operation_descriptions'] = getActionOperationData($operations);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of actions'));
		$this->setResponse($response);
	}
}
