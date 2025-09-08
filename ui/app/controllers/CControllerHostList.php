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


class CControllerHostList extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'page' =>					'ge 1',
			'filter_set' =>				'in 1',
			'filter_rst' =>				'in 1',
			'filter_host' =>			'string',
			'filter_templates' =>		'array_db hosts.hostid',
			'filter_groups' =>			'array_db hosts_groups.groupid',
			'filter_ip' =>				'string',
			'filter_dns' =>				'string',
			'filter_port' =>			'string',
			'filter_status' =>			'in -1,'.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED,
			'filter_monitored_by' =>	'in '.implode(',', [ZBX_MONITORED_BY_ANY, ZBX_MONITORED_BY_SERVER, ZBX_MONITORED_BY_PROXY, ZBX_MONITORED_BY_PROXY_GROUP]),
			'filter_proxyids' =>		'array_db hosts.proxyid',
			'filter_proxy_groupids' =>	'array_db hosts.proxy_groupid',
			'filter_evaltype' =>		'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>			'array',
			'sort' =>					'in name,status',
			'sortorder' =>				'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>				'in 1'
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
			CProfile::update('web.hosts.filter_status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);
			CProfile::update('web.hosts.filter_monitored_by',
				$this->getInput('filter_monitored_by', ZBX_MONITORED_BY_ANY), PROFILE_TYPE_INT
			);
			CProfile::updateArray('web.hosts.filter_templates', $this->getInput('filter_templates', []),
				PROFILE_TYPE_ID
			);
			CProfile::updateArray('web.hosts.filter_groups', $this->getInput('filter_groups', []), PROFILE_TYPE_ID);
			CProfile::updateArray('web.hosts.filter_proxyids', $this->getInput('filter_proxyids', []), PROFILE_TYPE_ID);
			CProfile::updateArray('web.hosts.filter_proxy_groupids', $this->getInput('filter_proxy_groupids', []),
				PROFILE_TYPE_ID
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
			CProfile::delete('web.hosts.filter_status');
			CProfile::delete('web.hosts.filter_monitored_by');
			CProfile::deleteIdx('web.hosts.filter_templates');
			CProfile::deleteIdx('web.hosts.filter_groups');
			CProfile::deleteIdx('web.hosts.filter_proxyids');
			CProfile::deleteIdx('web.hosts.filter_proxy_groupids');
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
			'status' => CProfile::get('web.hosts.filter_status', -1),
			'monitored_by' => CProfile::get('web.hosts.filter_monitored_by', ZBX_MONITORED_BY_ANY),
			'proxyids' => CProfile::getArray('web.hosts.filter_proxyids', []),
			'proxy_groupids' => CProfile::getArray('web.hosts.filter_proxy_groupids', []),
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
				$proxy_groupids = null;
				break;

			case ZBX_MONITORED_BY_SERVER:
				$proxyids = 0;
				$proxy_groupids = 0;
				break;

			case ZBX_MONITORED_BY_PROXY:
				$proxyids = $filter['proxyids'] ?: array_keys(API::Proxy()->get([
					'output' => [],
					'preservekeys' => true
				]));
				$proxy_groupids = 0;
				break;

			case ZBX_MONITORED_BY_PROXY_GROUP:
				$proxyids = 0;
				$proxy_groupids = $filter['proxy_groupids'] ?: array_keys(API::ProxyGroup()->get([
					'output' => [],
					'preservekeys' => true
				]));
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
			'proxyids' => $proxyids,
			'proxy_groupids' => $proxy_groupids,
			'editable' => true,
			'sortfield' => $sort_field,
			'limit' => $limit,
			'search' => [
				'name' => $filter['host'] === '' ? null : $filter['host'],
				'ip' => $filter['ip'] === '' ? null : $filter['ip'],
				'dns' => $filter['dns'] === '' ? null : $filter['dns']
			],
			'filter' => [
				'port' => $filter['port'] === '' ? null : $filter['port'],
				'status' => $filter['status'] == -1 ? null : $filter['status']
			]
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
			'output' => ['hostid', 'name', 'monitored_by', 'proxyid', 'proxy_groupid', 'assigned_proxyid',
				'maintenance_status', 'maintenance_type', 'maintenanceid', 'flags', 'status', 'tls_connect',
				'tls_accept', 'active_available'
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
			'selectDiscoveryRule' => ['itemid', 'name', 'lifetime_type', 'enabled_lifetime_type'],
			'selectHostDiscovery' => ['parent_hostid', 'status', 'ts_delete', 'ts_disable', 'disable_source'],
			'selectTags' => ['tag', 'value'],
			'hostids' => array_column($hosts, 'hostid'),
			'preservekeys' => true
		]);

		foreach ($hosts as &$host) {
			$host['is_discovery_rule_editable'] = $host['discoveryRule']
				&& API::DiscoveryRule()->get([
					'output' => [],
					'itemids' => $host['discoveryRule']['itemid'],
					'editable' => true
				]);
		}
		unset($host);

		order_result($hosts, $sort_field, $sort_order);

		$hostids = array_column($hosts, 'hostid');
		$active_item_count_by_hostid = getEnabledItemTypeCountByHostId(ITEM_TYPE_ZABBIX_ACTIVE, $hostids);

		// Selecting linked templates to templates linked to hosts.
		$templateids = [];
		$interfaceids = [];

		foreach ($hosts as $host) {
			$templateids = array_merge($templateids, array_column($host['parentTemplates'], 'templateid'));
			$interfaceids = array_merge($interfaceids, array_column($host['interfaces'], 'interfaceid'));
		}

		$templateids = array_keys(array_flip($templateids));
		$interface_enabled_items_count = getEnabledItemsCountByInterfaceIds($interfaceids);

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

		$proxyids = [];
		$proxy_groupids = [];
		$maintenanceids = [];

		foreach ($hosts as &$host) {
			// Sort interfaces to be listed starting with one selected as 'main'.
			CArrayHelper::sort($host['interfaces'], [
				['field' => 'main', 'order' => ZBX_SORT_DOWN]
			]);

			foreach ($host['interfaces'] as &$interface) {
				$interfaceid = $interface['interfaceid'];
				$interface['has_enabled_items'] = array_key_exists($interfaceid, $interface_enabled_items_count)
					&& $interface_enabled_items_count[$interfaceid] > 0;
			}
			unset($interface);

			// Add active checks interface if host have items with type ITEM_TYPE_ZABBIX_ACTIVE (7).
			if (array_key_exists($host['hostid'], $active_item_count_by_hostid)
					&& $active_item_count_by_hostid[$host['hostid']] > 0) {
				$host['interfaces'][] = [
					'type' => INTERFACE_TYPE_AGENT_ACTIVE,
					'available' => $host['active_available'],
					'has_enabled_items' => true,
					'error' => ''
				];
			}
			unset($host['active_available']);

			if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
				$proxyids[$host['proxyid']] = true;
			}
			elseif ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
				$proxy_groupids[$host['proxy_groupid']] = true;

				if ($host['assigned_proxyid'] != 0) {
					$proxyids[$host['assigned_proxyid']] = true;
				}
			}

			if ($host['status'] == HOST_STATUS_MONITORED &&
					$host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenanceids[$host['maintenanceid']] = true;
			}
		}
		unset($host);

		$proxies = $proxyids
			? API::Proxy()->get([
				'output' => ['name'],
				'proxyids' => array_keys($proxyids),
				'preservekeys' => true
			])
			: [];
		$proxy_groups = $proxy_groupids
			? API::ProxyGroup()->get([
				'output' => ['name'],
				'proxy_groupids' => array_keys($proxy_groupids),
				'preservekeys' => true
			])
			: [];
		$db_maintenances = $maintenanceids
			? API::Maintenance()->get([
				'output' => ['name', 'description'],
				'maintenanceids' => array_keys($maintenanceids),
				'preservekeys' => true
			])
			: [];

		// Prepare data for multiselects.
		$proxies_ms = $filter['proxyids']
			? CArrayHelper::renameObjectsKeys(API::Proxy()->get([
				'output' => ['proxyid', 'name'],
				'proxyids' => $filter['proxyids']
			]), ['proxyid' => 'id'])
			: [];
		$proxy_groups_ms = $filter['proxy_groupids']
			? CArrayHelper::renameObjectsKeys(API::ProxyGroup()->get([
				'output' => ['proxy_groupid', 'name'],
				'proxy_groupids' => $filter['proxy_groupids']
			]), ['proxy_groupid' => 'id'])
			: [];

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
			'proxy_groups' => $proxy_groups,
			'proxy_groups_ms' => $proxy_groups_ms,
			'profileIdx' => 'web.hosts.filter',
			'active_tab' => CProfile::get('web.hosts.filter.active', 1),
			'tags' => makeTags($hosts, true, 'hostid', ZBX_TAG_COUNT_DEFAULT, $filter['tags']),
			'config' => [
				'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)
			],
			'user' => [
				'can_edit_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
				'can_edit_proxy_groups' => CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS),
				'can_edit_proxies' => CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
			],
			'uncheck' => ($this->getInput('uncheck', 0) == 1)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of hosts'));

		$this->setResponse($response);
	}
}
