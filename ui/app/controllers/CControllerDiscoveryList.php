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


class CControllerDiscoveryList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'sort' =>			'in name',
			'sortorder' =>		'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>		'in 1',
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'filter_status' =>	'in -1,'.DRULE_STATUS_ACTIVE.','.DRULE_STATUS_DISABLED,
			'page' =>			'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY);
	}

	protected function doAction(): void {
		$sort_field = $this->getInput('sort', CProfile::get('web.discoveryconf.php.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.discoveryconf.php.sortorder', ZBX_SORT_UP));
		CProfile::update('web.discoveryconf.php.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.discoveryconf.php.sortorder', $sort_order, PROFILE_TYPE_STR);

		// filter
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.discoveryconf.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.discoveryconf.filter_status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.discoveryconf.filter_name');
			CProfile::delete('web.discoveryconf.filter_status');
		}

		$filter = [
			'name' => CProfile::get('web.discoveryconf.filter_name', ''),
			'status' => CProfile::get('web.discoveryconf.filter_status', -1)
		];

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.discoveryconf.filter',
			'active_tab' => CProfile::get('web.discoveryconf.filter.active', 1)
		];

		// Get discovery rules.
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['drules'] = API::DRule()->get([
			'output' => ['proxyid', 'name', 'status', 'iprange', 'delay', 'error'],
			'selectDChecks' => ['type'],
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'filter' => [
				'status' => ($filter['status'] == -1) ? null : $filter['status']
			],
			'editable' => true,
			'limit' => $limit
		]);

		if ($data['drules']) {
			$proxyids = [];

			foreach ($data['drules'] as $drule) {
				if ($drule['proxyid'] != 0) {
					$proxyids[$drule['proxyid']] = true;
				}
			}

			$proxies = $proxyids
				? API::Proxy()->get([
					'output' => ['name'],
					'proxyids' => array_keys($proxyids),
					'preservekeys' => true
				])
				: [];

			foreach ($data['drules'] as &$drule) {
				$drule['proxy'] = array_key_exists($drule['proxyid'], $proxies)
					? $drule['proxy'] = $proxies[$drule['proxyid']]['name']
					: '';

				$checks = [];

				foreach ($drule['dchecks'] as $check) {
					$checks[$check['type']] = discovery_check_type2str($check['type']);
				}

				order_result($checks);

				$drule['checks'] = $checks;
			}
			unset($drule);

			CArrayHelper::sort($data['drules'], [['field' => $sort_field, 'order' => $sort_order]]);
		}

		// pager
		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('discovery.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['drules'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of discovery rules'));
		$this->setResponse($response);
	}
}
