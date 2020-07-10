<?php declare(strict_types=1);
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


class CControllerApplicationList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort'            => 'in name',
			'sortorder'       => 'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck'         => 'in 1',
			'filter_set'      => 'in 1',
			'filter_rst'      => 'in 1',
			'filter_hostids'  => 'array_db hosts.hostid',
			'filter_groupids' => 'array_db hstgrp.groupid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		$sort_field = $this->getInput('sort', CProfile::get('web.applications.php.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.applications.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.applications.php.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.applications.php.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::updateArray('web.applications.filter_groups', $this->getInput('filter_groupids', []), PROFILE_TYPE_ID);
			CProfile::updateArray('web.applications.filter_hostids', $this->getInput('filter_hostids', []), PROFILE_TYPE_ID);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::deleteIdx('web.applications.filter_groups');

			$filter_hostids = $this->getInput('filter_hostids', CProfile::getArray('web.applications.filter_hostids', []));
			if (count($filter_hostids) != 1) {
				CProfile::deleteIdx('web.applications.filter_hostids');
			}
		}

		$filter = [
			'groups' => CProfile::getArray('web.applications.filter_groups', null),
			'hosts' => CProfile::getArray('web.applications.filter_hostids', null)
		];

		// Get host groups.
		$filter['groups'] = $filter['groups']
			? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groups'],
				'with_hosts_and_templates' => true,
				'editable' => true,
				'preservekeys' => true
			]), ['groupid' => 'id'])
			: [];

		$filter_groupids = $filter['groups'] ? array_keys($filter['groups']) : null;
		if ($filter_groupids) {
			$filter_groupids = getSubGroups($filter_groupids);
		}

		$filter['hosts'] = $filter['hosts']
			? CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter['hosts'],
				'templated_hosts' => true,
				'editable' => true,
				'preservekeys' => true
			]), ['hostid' => 'id'])
			: [];

		$hostid = (count($filter['hosts']) == 1) ? reset($filter['hosts'])['id'] : getRequest('hostid', 0);

		$data = [
			'filter' => $filter,
			'sort' => $sort_field,
			'uncheck' => $this->hasInput('uncheck'),
			'sortorder' => $sort_order,
			'hostid' => $hostid,
			'showInfoColumn' => false,
			'profileIdx' => 'web.applications.filter',
			'active_tab' => CProfile::get('web.applications.filter.active', 1),
		];

		$config = select_config();

		// Get applications.
		$data['applications'] = API::Application()->get([
			'output' => ['applicationid', 'hostid', 'name', 'flags', 'templateids'],
			'hostids' => $filter['hosts'] ? array_keys($filter['hosts']) : null,
			'groupids' => $filter_groupids,
			'selectHost' => ['hostid', 'name'],
			'selectItems' => ['itemid'],
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectApplicationDiscovery' => ['ts_delete'],
			'editable' => true,
			'sortfield' => $sort_field,
			'limit' => $config['search_limit'] + 1
		]);

		order_result($data['applications'], $sort_field, $sort_order);

		$data['parent_templates'] = getApplicationParentTemplates($data['applications']);

		/*
		 * Calculate the 'ts_delete' which will display the of warning icon and hint telling when application will be
		 * deleted. Also we need only 'ts_delete' for view, so get rid of the multidimensional array inside
		 * 'applicationDiscovery' property.
		 */
		foreach ($data['applications'] as &$application) {
			if ($application['applicationDiscovery']) {
				if (count($application['applicationDiscovery']) > 1) {
					$ts_delete = zbx_objectValues($application['applicationDiscovery'], 'ts_delete');

					if (min($ts_delete) == 0) {
						// One rule stops discovering application, but other rule continues to discover it.
						unset($application['applicationDiscovery']);
						$application['applicationDiscovery']['ts_delete'] = 0;
					}
					else {
						// Both rules stop discovering application. Find maximum clock.
						unset($application['applicationDiscovery']);
						$application['applicationDiscovery']['ts_delete'] = max($ts_delete);
					}
				}
				else {
					// Application is discovered by one rule.
					$ts_delete = $application['applicationDiscovery'][0]['ts_delete'];
					unset($application['applicationDiscovery']);
					$application['applicationDiscovery']['ts_delete'] = $ts_delete;
				}
			}
		}
		unset($application);

		// Info column is show when all hosts are selected or current host is not a template.
		if ($data['hostid'] > 0) {
			$hosts = API::Host()->get([
				'output' => ['status'],
				'hostids' => [$data['hostid']]
			]);

			$data['showInfoColumn'] = $hosts
				&& ($hosts[0]['status'] == HOST_STATUS_MONITORED || $hosts[0]['status'] == HOST_STATUS_NOT_MONITORED);
		}
		else {
			$data['showInfoColumn'] = true;
		}

		// pager
		if (hasRequest('page')) {
			$page_num = getRequest('page');
		}
		elseif (isRequestMethod('get') && !hasRequest('cancel')) {
			$page_num = 1;
		}
		else {
			$page_num = CPagerHelper::loadPage('application.list');
		}

		CPagerHelper::savePage('application.list', $page_num);

		$data['paging'] = CPagerHelper::paginate($page_num, $data['applications'], $sort_order, (new CUrl('zabbix.php'))->setArgument('action', 'application.list'));

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of proxies'));
		$this->setResponse($response);
	}
}
