<?php declare(strict_types = 0);
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


class CControllerActionList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'eventsource'=> 'in '.implode(',', [
					EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY,
					EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL,
					EVENT_SOURCE_SERVICE
				]),
			'g_actionid' => 'array_id',
			'filter_set' => 'string',
			'filter_rst' =>	'string',
			'filter_name' =>'string',
			'filter_status' =>'in '.implode(',', [-1, ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);

		//		$eventsource = getRequest('eventsource', EVENT_SOURCE_TRIGGERS);
//		$check_actionids = false;

//		if (hasRequest('actionid')) {
//			$check_actionids[getRequest('actionid')] = true;
//		}

//		if ($check_actionids) {
//			$actions = API::Action()->get([
//				'output' => [],
//				'actionids' => array_keys($check_actionids),
//				'filter' => [
//					'eventsource' => $eventsource
//				],
//				'editable' => true
//			]);

//			if (count($actions) != count($check_actionids)) {
//				access_deny();
//			}

//			unset($check_actionids, $actions);
//		}
	}

	protected function doAction(): void {
		$page['file'] = 'zabbix.php';
		$eventsource = getRequest('eventsource');

		$filter = [
			'name' => CProfile::get('web.actionconf.filter_name', ''),
			'status' => CProfile::get('web.actionconf.filter_status', -1)
		];

		$active_tab = 'web.service_actions.filter.active';
		$profile = 'web.actionconf.filter';

		$sortField = getRequest('sort', CProfile::get('web.actionconf.php.sort', 'name'));
		$sortOrder = getRequest('sortorder', CProfile::get('web.actionconf.php.sortorder', ZBX_SORT_UP));

		$data = [
			'action' => $this->getAction(),
			'eventsource' => $eventsource,
			'active_tab' => CProfile::get($active_tab, 1),
			'profileIdx' => $profile,
			'filter' => $filter,
			'sort' => $sortField,
			'sortorder' => $sortOrder
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['actions'] = API::Action()->get([
			'output' => API_OUTPUT_EXTEND,
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'filter' => [
				'eventsource' => $data['eventsource'],
				'status' => ($filter['status'] == -1) ? null : $filter['status']
			],
			'selectFilter' => ['formula', 'conditions', 'evaltype'],
			'selectOperations' => API_OUTPUT_EXTEND,
			'editable' => true,
			'sortfield' => $sortField,
			'limit' => $limit
		]);
		order_result($data['actions'], $sortField, $sortOrder);

		// pager
		if (hasRequest('page')) {
			$page_num = getRequest('page');
		}
		elseif (isRequestMethod('get') && !hasRequest('cancel')) {
			$page_num = 1;
		}
		else {
			$page_num = CPagerHelper::loadPage($page['file']);
		}

		CPagerHelper::savePage($page['file'], $page_num);

		$data['paging'] = CPagerHelper::paginate($page_num, $data['actions'], $sortOrder, (new CUrl('zabbix.php'))
			->setArgument('action', 'action.list')
			->setArgument('eventsource', $eventsource)
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of actions'));

		$this->setResponse($response);
	}
}
