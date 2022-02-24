<?php declare(strict_types = 1);
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


class CControllerHostList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'page'                => 'ge 1',
			'filter_set'          => 'in 1',
			'filter_rst'          => 'in 1',
			'filter_host'         => 'string',
			'filter_templates'    => 'array_db hosts.hostid',
			'filter_groups'       => 'array_db hosts_groups.groupid',
			'filter_ip'           => 'string',
			'filter_dns'          => 'string',
			'filter_port'         => 'string',
			'filter_monitored_by' => 'in '.ZBX_MONITORED_BY_ANY.','.ZBX_MONITORED_BY_SERVER.','.ZBX_MONITORED_BY_PROXY,
			'filter_proxyids'     => 'array_db hosts.proxy_hostid',
			'filter_evaltype'     => 'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags'         => 'array',
			'sort'                => 'in name,status',
			'sortorder'           => 'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck'             => 'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.hosts.filter_ip', $this->getInput('filter_ip', ''), PROFILE_TYPE_STR);
			CProfile::update('web.hosts.filter_dns', $this->getInput('filter_dns', ''), PROFILE_TYPE_STR);
			CProfile::update('web.hosts.filter_host', $this->getInput('filter_host', ''), PROFILE_TYPE_STR);
			CProfile::update('web.hosts.filter_port', $this->getInput('filter_port', ''), PROFILE_TYPE_STR);
			CProfile::update('web.hosts.filter_monitored_by',
				$this->getInput('filter_monitored_by', ZBX_MONITORED_BY_ANY), PROFILE_TYPE_INT
			);
			CProfile::updateArray('web.hosts.filter_templates',
				$this->getInput('filter_templates', []), PROFILE_TYPE_ID
			);
			CProfile::updateArray('web.hosts.filter_groups', $this->getInput('filter_groups', []), PROFILE_TYPE_ID);
			CProfile::updateArray('web.hosts.filter_proxyids',
				$this->getInput('filter_proxyids', []), PROFILE_TYPE_ID
			);
			CProfile::update('web.hosts.filter.evaltype', $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
				PROFILE_TYPE_INT
			);

			$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
			foreach ($this->getInput('filter_tags', []) as $filter_tag) {
				if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
					continue;
				}

				$filter_tags['tags'][] = $filter_tag['tag'];
				$filter_tags['values'][] = $filter_tag['value'];
				$filter_tags['operators'][] = $filter_tag['operator'];
			}

			CProfile::updateArray('web.hosts.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.hosts.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.hosts.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.hosts.filter_ip');
			CProfile::delete('web.hosts.filter_dns');
			CProfile::delete('web.hosts.filter_host');
			CProfile::delete('web.hosts.filter_port');
			CProfile::delete('web.hosts.filter_monitored_by');
			CProfile::deleteIdx('web.hosts.filter_templates');
			CProfile::deleteIdx('web.hosts.filter_groups');
			CProfile::deleteIdx('web.hosts.filter_proxyids');
			CProfile::delete('web.hosts.filter.evaltype');
			CProfile::deleteIdx('web.hosts.filter.tags.tag');
			CProfile::deleteIdx('web.hosts.filter.tags.value');
			CProfile::deleteIdx('web.hosts.filter.tags.operator');
		}

		$filter = [
			'ip' => CProfile::get('web.hosts.filter_ip', ''),
			'dns' => CProfile::get('web.hosts.filter_dns', ''),
			'host' => CProfile::get('web.hosts.filter_host', ''),
			'templates' => CProfile::getArray('web.hosts.filter_templates', []),
			'groups' => CProfile::getArray('web.hosts.filter_groups', []),
			'port' => CProfile::get('web.hosts.filter_port', ''),
			'monitored_by' => CProfile::get('web.hosts.filter_monitored_by', ZBX_MONITORED_BY_ANY),
			'proxyids' => CProfile::getArray('web.hosts.filter_proxyids', []),
			'evaltype' => CProfile::get('web.hosts.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => []
		];

		foreach (CProfile::getArray('web.hosts.filter.tags.tag', []) as $i => $tag) {
			$filter['tags'][] = [
				'tag' => $tag,
				'value' => CProfile::get('web.hosts.filter.tags.value', null, $i),
				'operator' => CProfile::get('web.hosts.filter.tags.operator', null, $i)
			];
		}

		CArrayHelper::sort($filter['tags'], ['tag', 'value', 'operator']);

		$sort_field = $this->getInput('sort', CProfile::get('web.hosts.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.hosts.sortorder', ZBX_SORT_UP));

		CProfile::update('web.hosts.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.hosts.sortorder', $sort_order, PROFILE_TYPE_STR);

		// Get host groups.
		$filter['groups'] = $filter['groups']
			? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groups'],
				'editable' => true,
				'preservekeys' => true
			]), ['groupid' => 'id'])
			: [];

		$filter_groupids = $filter['groups'] ? array_keys($filter['groups']) : null;

		if ($filter_groupids) {
			$filter_groupids = getSubGroups($filter_groupids);
		}

		// Get templates.
		$filter['templates'] = $filter['templates']
			? CArrayHelper::renameObjectsKeys(API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter['templates'],
				'preservekeys' => true
			]), ['templateid' => 'id'])
			: [];

		switch ($filter['monitored_by']) {
			case ZBX_MONITORED_BY_ANY:
				$proxyids = null;
				break;

			case ZBX_MONITORED_BY_PROXY:
				$proxyids = $filter['proxyids']
					? $filter['proxyids']
					: array_keys(API::Proxy()->get([
						'output' => [],
						'preservekeys' => true
					]));
				break;

			case ZBX_MONITORED_BY_SERVER:
				$proxyids = 0;
				break;
		}

		// Select hosts.
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$hosts = API::Host()->get([
			'output' => ['hostid', $sort_field],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'],
			'groupids' => $filter_groupids,
			'templateids' => $filter['templates'] ? array_keys($filter['templates']) : null,
			'editable' => true,
			'sortfield' => $sort_field,
			'limit' => $limit,
			'search' => [
				'name' => ($filter['host'] === '') ? null : $filter['host'],
				'ip' => ($filter['ip'] === '') ? null : $filter['ip'],
				'dns' => ($filter['dns'] === '') ? null : $filter['dns']
			],
			'filter' => [
				'port' => ($filter['port'] === '') ? null : $filter['port']
			],
			'proxyids' => $proxyids
		]);

		order_result($hosts, $sort_field, $sort_order);

		if ($this->hasInput('page')) {
			$page_num = $this->getInput('page');
		}
		elseif (isRequestMethod('get')) {
			$page_num = 1;
		}
		else {
			$page_num = CPagerHelper::loadPage($this->getAction());
		}

		CPagerHelper::savePage($this->getAction(), $page_num);

		$paging = CPagerHelper::paginate($page_num, $hosts, $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$hosts = API::Host()->get([
			'output' => ['name', 'proxy_hostid', 'maintenance_status', 'maintenance_type', 'maintenanceid', 'flags',
				'status', 'tls_connect', 'tls_accept'
			],
			'selectParentTemplates' => ['templateid', 'name'],
			'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip',  'ip', 'dns', 'port', 'available', 'error',
				'details'
			],
			'selectItems' => API_OUTPUT_COUNT,
			'selectDiscoveries' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectHttpTests' => API_OUTPUT_COUNT,
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectHostDiscovery' => ['ts_delete'],
			'selectTags' => ['tag', 'value'],
			'hostids' => array_column($hosts, 'hostid'),
			'preservekeys' => true
		]);

		order_result($hosts, $sort_field, $sort_order);

		// Selecting linked templates to templates linked to hosts.
		$templateids = [];

		foreach ($hosts as $host) {
			$templateids = array_merge($templateids, array_column($host['parentTemplates'], 'templateid'));
		}

		$templateids = array_keys(array_flip($templateids));

		$templates = API::Template()->get([
			'output' => ['templateid', 'name'],
			'selectParentTemplates' => ['templateid', 'name'],
			'templateids' => $templateids,
			'preservekeys' => true
		]);

		$writable_templates = [];

		if ($templateids) {
			foreach ($templates as $template) {
				$templateids = array_merge($templateids, array_column($template['parentTemplates'], 'templateid'));
			}

			$writable_templates = API::Template()->get([
				'output' => ['templateid'],
				'templateids' => array_keys(array_flip($templateids)),
				'editable' => true,
				'preservekeys' => true
			]);
		}

		// Get proxy host IDs that are not 0 and maintenance IDs.
		$proxy_hostids = [];
		$maintenanceids = [];

		foreach ($hosts as &$host) {
			// Sort interfaces to be listed starting with one selected as 'main'.
			CArrayHelper::sort($host['interfaces'], [
				['field' => 'main', 'order' => ZBX_SORT_DOWN]
			]);

			if ($host['proxy_hostid']) {
				$proxy_hostids[$host['proxy_hostid']] = $host['proxy_hostid'];
			}

			if ($host['status'] == HOST_STATUS_MONITORED &&
					$host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenanceids[$host['maintenanceid']] = true;
			}
		}
		unset($host);

		$proxies = [];

		if ($proxy_hostids) {
			$proxies = API::Proxy()->get([
				'proxyids' => $proxy_hostids,
				'output' => ['host'],
				'preservekeys' => true
			]);
		}

		// Prepare data for multiselect and remove non-existing proxies.
		$proxies_ms = [];

		if ($filter['proxyids']) {
			$filter_proxies = API::Proxy()->get([
				'output' => ['proxyid', 'host'],
				'proxyids' => $filter['proxyids']
			]);

			$proxies_ms = CArrayHelper::renameObjectsKeys($filter_proxies, ['proxyid' => 'id', 'host' => 'name']);
		}

		$db_maintenances = [];

		if ($maintenanceids) {
			$db_maintenances = API::Maintenance()->get([
				'output' => ['name', 'description'],
				'maintenanceids' => array_keys($maintenanceids),
				'preservekeys' => true
			]);
		}

		if (!$filter['tags']) {
			$filter['tags'] = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
		}

		$data = [
			'action' => $this->getAction(),
			'hosts' => $hosts,
			'paging' => $paging,
			'page' => $page_num,
			'filter' => $filter,
			'sortField' => $sort_field,
			'sortOrder' => $sort_order,
			'templates' => $templates,
			'maintenances' => $db_maintenances,
			'writable_templates' => $writable_templates,
			'proxies' => $proxies,
			'proxies_ms' => $proxies_ms,
			'profileIdx' => 'web.hosts.filter',
			'active_tab' => CProfile::get('web.hosts.filter.active', 1),
			'tags' => makeTags($hosts, true, 'hostid', ZBX_TAG_COUNT_DEFAULT, $filter['tags']),
			'config' => [
				'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)
			],
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
			'uncheck' => ($this->getInput('uncheck', 0) == 1)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of hosts'));

		$this->setResponse($response);
	}
}
