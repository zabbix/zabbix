<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

	protected function checkInput() {
		// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
		$fields = [
			// actions
			/*'action' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
											IN('"host.list"'),
											null
										],*/
			// filter
			'filter_set' =>				[T_ZBX_STR, O_OPT, P_SYS,			null,		null],
			'filter_rst' =>				[T_ZBX_STR, O_OPT, P_SYS,			null,		null],
			'filter_host' =>			[T_ZBX_STR, O_OPT, null,			null,		null],
			'filter_templates' =>		[T_ZBX_INT, O_OPT, null,			DB_ID,		null],
			'filter_groups' =>			[T_ZBX_INT, O_OPT, null,			DB_ID,		null],
			'filter_ip' =>				[T_ZBX_STR, O_OPT, null,			null,		null],
			'filter_dns' =>				[T_ZBX_STR, O_OPT, null,			null,		null],
			'filter_port' =>			[T_ZBX_STR, O_OPT, null,			null,		null],
			'filter_monitored_by' =>	[T_ZBX_INT, O_OPT, null,
											IN([ZBX_MONITORED_BY_ANY, ZBX_MONITORED_BY_SERVER, ZBX_MONITORED_BY_PROXY]),
											null
										],
			'filter_proxyids' =>		[T_ZBX_INT, O_OPT, null,			DB_ID,		null],
			'filter_evaltype' =>		[T_ZBX_INT, O_OPT, null,
											IN([TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]),
											null
										],
			'filter_tags' =>			[T_ZBX_STR, O_OPT, null,			null,		null],
			// sort and sortorder
			'sort' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"name","status"'),						null],
			'sortorder' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
		];

		// ? $ret = $this->validateInput($fields);
		$ret = check_fields($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected function doAction() {
		if (hasRequest('filter_set')) {
			CProfile::update('web.hosts.filter_ip', getRequest('filter_ip', ''), PROFILE_TYPE_STR);
			CProfile::update('web.hosts.filter_dns', getRequest('filter_dns', ''), PROFILE_TYPE_STR);
			CProfile::update('web.hosts.filter_host', getRequest('filter_host', ''), PROFILE_TYPE_STR);
			CProfile::update('web.hosts.filter_port', getRequest('filter_port', ''), PROFILE_TYPE_STR);
			CProfile::update('web.hosts.filter_monitored_by', getRequest('filter_monitored_by', ZBX_MONITORED_BY_ANY),
				PROFILE_TYPE_INT
			);
			CProfile::updateArray('web.hosts.filter_templates', getRequest('filter_templates', []), PROFILE_TYPE_ID);
			CProfile::updateArray('web.hosts.filter_groups', getRequest('filter_groups', []), PROFILE_TYPE_ID);
			CProfile::updateArray('web.hosts.filter_proxyids', getRequest('filter_proxyids', []), PROFILE_TYPE_ID);
			CProfile::update('web.hosts.filter.evaltype', getRequest('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
				PROFILE_TYPE_INT
			);

			$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
			foreach (getRequest('filter_tags', []) as $filter_tag) {
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
		elseif (hasRequest('filter_rst')) {
			DBstart();
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
			DBend();
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

		$tags = getRequest('tags', []);
		foreach ($tags as $key => $tag) {
			// remove empty new tag lines
			if ($tag['tag'] === '' && $tag['value'] === '') {
				unset($tags[$key]);
				continue;
			}

			// remove inherited tags
			if (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
				unset($tags[$key]);
			}
			else {
				unset($tags[$key]['type']);
			}
		}

		$sortField = getRequest('sort', CProfile::get('web.host.list.sort', 'name'));
		$sortOrder = getRequest('sortorder', CProfile::get('web.host.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.host.list.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.host.list.sortorder', $sortOrder, PROFILE_TYPE_STR);

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
			'output' => ['hostid', $sortField],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'],
			'groupids' => $filter_groupids,
			'templateids' => $filter['templates'] ? array_keys($filter['templates']) : null,
			'editable' => true,
			'sortfield' => $sortField,
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

		order_result($hosts, $sortField, $sortOrder);

		// pager
		if (hasRequest('page')) {
			$page_num = getRequest('page');
		}
		elseif (isRequestMethod('get') && !hasRequest('cancel')) {
			$page_num = 1;
		}
		else {
			$page_num = CPagerHelper::loadPage('host.list');
		}

		CPagerHelper::savePage('host.list', $page_num);

		$pagingLine = CPagerHelper::paginate($page_num, $hosts, $sortOrder, new CUrl('host.list'));

		$hosts = API::Host()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => ['templateid', 'name'],
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'selectItems' => API_OUTPUT_COUNT,
			'selectDiscoveries' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectHttpTests' => API_OUTPUT_COUNT,
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectHostDiscovery' => ['ts_delete'],
			'selectTags' => ['tag', 'value'],
			'hostids' => zbx_objectValues($hosts, 'hostid'),
			'preservekeys' => true
		]);
		order_result($hosts, $sortField, $sortOrder);

		// selecting linked templates to templates linked to hosts
		$templateids = [];

		foreach ($hosts as $host) {
			$templateids = array_merge($templateids, zbx_objectValues($host['parentTemplates'], 'templateid'));
		}

		$templateids = array_keys(array_flip($templateids));

		$templates = API::Template()->get([
			'output' => ['templateid', 'name'],
			'selectParentTemplates' => ['templateid', 'name'],
			'templateids' => $templateids,
			'preservekeys' => true
		]);

		// selecting writable templates IDs
		$writable_templates = [];
		if ($templateids) {
			foreach ($templates as $template) {
				$templateids = array_merge($templateids, zbx_objectValues($template['parentTemplates'], 'templateid'));
			}

			$writable_templates = API::Template()->get([
				'output' => ['templateid'],
				'templateids' => array_keys(array_flip($templateids)),
				'editable' => true,
				'preservekeys' => true
			]);
		}

		// Get proxy host IDs that are not 0 and maintenance IDs.
		$proxyHostIds = [];
		$maintenanceids = [];

		foreach ($hosts as &$host) {
			// Sort interfaces to be listed starting with one selected as 'main'.
			CArrayHelper::sort($host['interfaces'], [
				['field' => 'main', 'order' => ZBX_SORT_DOWN]
			]);

			if ($host['proxy_hostid']) {
				$proxyHostIds[$host['proxy_hostid']] = $host['proxy_hostid'];
			}

			if ($host['status'] == HOST_STATUS_MONITORED && $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenanceids[$host['maintenanceid']] = true;
			}
		}
		unset($host);

		$proxies = [];
		if ($proxyHostIds) {
			$proxies = API::Proxy()->get([
				'proxyids' => $proxyHostIds,
				'output' => ['host'],
				'preservekeys' => true
			]);
		}

		// Prepare data for multiselect and remove unexisting proxies.
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

		$data = [
			'hosts' => $hosts,
			'paging' => $pagingLine,
			'page' => $page_num,
			'filter' => $filter,
			'sortField' => $sortField,
			'sortOrder' => $sortOrder,
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
			'user' =>  [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
