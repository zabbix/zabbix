<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

	protected function init(): void {
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
			$this->updateProfiles();
		}
		elseif ($this->hasInput('filter_rst')) {
			$this->deleteProfiles();
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

		$sort_field = $this->getInput('sort',
			CProfile::get('web.hosts.sort', CControllerHost::DEFAULT_SORT));
		$sort_order = $this->getInput('sortorder',
			CProfile::get('web.hosts.sortorder', CControllerHost::DEFAULT_SORTORDER));

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

		// Get templates.
		$filter['templates'] = $filter['templates']
			? CArrayHelper::renameObjectsKeys(API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter['templates'],
				'preservekeys' => true
			]), ['templateid' => 'id'])
			: [];

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

		$storage_idx = 'web.hosts.datatable';

		$data = [
			'action' => $this->getAction(),
			'active_tab' => CProfile::get('web.hosts.filter.active', 1),
			'csrf_token' => CCsrfTokenHelper::get('host'),
			'default_sort_field' => CControllerHost::DEFAULT_SORT,
			'default_sort_order' => CControllerHost::DEFAULT_SORTORDER,
			'filter' => $filter,
			'page' => $page_num,
			'sort_field' => $sort_field,
			'sort_order' => $sort_order,
			'profileIdx' => 'web.hosts.filter',
			'proxies_ms' => $proxies_ms,
			'proxy_groups_ms' => $proxy_groups_ms,
			'storage_idx' => $storage_idx,
			'uncheck' => ($this->getInput('uncheck', 0) == 1),
			'user_configs' => array_map(static fn (string $user_config) => json_decode($user_config, true) ?? [],
				CProfile::getArray($storage_idx, []))
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of hosts'));

		$this->setResponse($response);
	}

	private function updateProfiles(): void {
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

	private function deleteProfiles(): void {
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
}
