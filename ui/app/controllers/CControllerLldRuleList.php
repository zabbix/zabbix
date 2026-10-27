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


class CControllerLldRuleList extends CController {

	protected string $context;

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_set'					=> 'in 1',
			'filter_rst'					=> 'in 1',
			'context'						=> 'required|in host,template',
			'filter_groupids'				=> 'array_db hstgrp.groupid',
			'filter_hostids'				=> 'array_db hosts.hostid',
			'filter_name'					=> 'string',
			'filter_key'					=> 'string',
			'filter_type'					=> 'in '.implode(',', [-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER,
													ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
													ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI,
													ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX,
													ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP,
													ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER, ITEM_TYPE_NESTED
												]),
			'filter_delay'					=> 'string',
			'filter_lifetime_type'			=> 'in '.implode(',', [-1, ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_IMMEDIATELY]),
			'filter_lifetime'				=> 'string',
			'filter_enabled_lifetime_type'	=> 'in '.implode(',', [-1, ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY]),
			'filter_enabled_lifetime'		=> 'string',
			'filter_snmp_oid'				=> 'string',
			'filter_state'					=> 'in '.implode(',', [-1, ITEM_STATE_NORMAL, ITEM_STATE_NOTSUPPORTED]),
			'filter_status'					=> 'in '.implode(',', [-1, ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]),
			'sort'							=> 'in delay,key_,name,status,type',
			'sortorder'						=> 'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'page'							=> 'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->hasInput('context')) {
			return false;
		}

		$this->context = $this->getInput('context');

		return $this->context === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	private function getProfilePrefix(): string {
		return $this->context === 'host' ? 'web.hosts.lldrules.' : 'web.templates.lldrules.';
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			$this->updateProfileFilters();
		}
		elseif ($this->hasInput('filter_rst')) {
			$this->deleteProfileFilters();
		}
		else {
			$this->updateProfileSort();
		}

		$page = $this->getInput('page', 1);
		$filter = $this->getFilter();

		$discoveries = $this->getDiscoveries($filter);

		$view_url = new CUrl('zabbix.php');
		$view_url_params = ['action' => $this->getAction(), 'context' => $this->context];

		array_map([$view_url, 'setArgument'], array_keys($view_url_params), $view_url_params);
		$paging = CPagerHelper::paginate($page, $discoveries, $filter['sortorder'], $view_url);

		$data = [
			'action' => $this->getAction(),
			'filter' => $filter,
			'context' => $this->context,
			'hostid' => count($filter['filter_hostids']) == 1 ? reset($filter['filter_hostids']) : 0,
			'sort' => $filter['sort'],
			'sortorder' => $filter['sortorder'],
			'profileIdx' => $this->getProfilePrefix().'filter',
			'active_tab' => CProfile::get($this->getProfilePrefix().'filter.active', 1),
			'discoveries' => $discoveries,
			'paging' => $paging,
			'parent_templates' => getItemParentTemplates($discoveries, ZBX_FLAG_DISCOVERY_RULE),
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of discovery rules'));
		$this->setResponse($response);
	}

	protected function getDiscoveries(array $filter): array {
		$options = [
			'output' => ['itemid', 'type', 'name', 'key_', 'delay', 'status', 'templateid', 'flags', 'master_itemid',
				'state', 'error'
			],
			'selectHosts' => ['hostid', 'name', 'status'],
			'selectItems' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectHostPrototypes' => API_OUTPUT_COUNT,
			'selectDiscoveryRulePrototypes' => API_OUTPUT_COUNT,
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectDiscoveryData' => ['status', 'ts_delete', 'ts_disable', 'disable_source'],
			'editable' => true,
			'templated' => $this->context === 'template',
			'filter' => [],
			'search' => [],
			'sortfield' => $filter['sort'],
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
		];

		if ($filter['filter_groupids']) {
			$options['groupids'] = $filter['filter_groupids'];
		}

		if ($filter['filter_hostids']) {
			$options['hostids'] = $filter['filter_hostids'];
		}

		if ($filter['filter_name'] !== '') {
			$options['search']['name'] = $filter['filter_name'];
		}

		if ($filter['filter_key'] !== '') {
			$options['search']['key_'] = $filter['filter_key'];
		}

		if ($filter['filter_type'] != -1) {
			$options['filter']['type'] = $filter['filter_type'];
		}

		/*
		 * Trapper and SNMP trap items contain zeros in "delay" field and, if no specific type is set, look in item types
		 * other than trapper and SNMP trap that allow zeros. For example, when a flexible interval is used. Since trapper
		 * and SNMP trap items contain zeros, but those zeros should not be displayed, they cannot be filtered by entering
		 * either zero or any other number in filter field.
		 */
		if ($filter['filter_delay'] !== '') {
			if ($filter['filter_type'] == -1 && $filter['filter_delay'] == 0) {
				$options['filter']['type'] = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE,  ITEM_TYPE_INTERNAL,
					ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI,
					ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX
				];
				$options['filter']['delay'] = $filter['filter_delay'];
			}
			elseif ($filter['filter_type'] == ITEM_TYPE_TRAPPER || $filter['filter_type'] == ITEM_TYPE_DEPENDENT
					|| ($filter['filter_type'] == ITEM_TYPE_ZABBIX_ACTIVE
						&& strncmp($filter['filter_key'], 'mqtt.get', 8) == 0)) {
				$options['filter']['delay'] = -1;
			}
			else {
				$options['filter']['delay'] = $filter['filter_delay'];
			}
		}

		if ($filter['filter_lifetime_type'] != -1) {
			$options['filter']['lifetime_type'] = $filter['filter_lifetime_type'];
		}

		if ($filter['filter_lifetime'] !== '') {
			$options['filter']['lifetime'] = $filter['filter_lifetime'];
		}

		if ($filter['filter_enabled_lifetime_type'] != -1) {
			$options['filter']['enabled_lifetime_type'] = $filter['filter_enabled_lifetime_type'];
		}

		if ($filter['filter_enabled_lifetime'] !== '') {
			$options['filter']['enabled_lifetime'] = $filter['filter_enabled_lifetime'];
		}

		if ($filter['filter_snmp_oid'] !== '') {
			$options['filter']['snmp_oid'] = $filter['filter_snmp_oid'];
		}

		if ($filter['filter_status'] != -1) {
			$options['filter']['status'] = $filter['filter_status'];
		}

		if ($filter['filter_state'] != -1) {
			$options['filter']['status'] = ITEM_STATUS_ACTIVE;
			$options['filter']['state'] = $filter['filter_state'];
		}

		$discoveries = API::DiscoveryRule()->get($options);
		$discoveries = expandItemNamesWithMasterItems($discoveries, 'items');

		$lld_parentids = [];

		foreach ($discoveries as $discovery) {
			if ($discovery['discoveryRule']) {
				$lld_parentids[$discovery['discoveryRule']['itemid']] = true;
			}
		}

		if ($lld_parentids) {
			$editable_lld_parents = API::DiscoveryRule()->get([
				'output' => [],
				'itemids' => array_keys($lld_parentids),
				'editable' => true,
				'preservekeys' => true
			]);

			foreach ($discoveries as &$discovery) {
				if ($discovery['discoveryRule']) {
					$discovery['is_discovery_rule_editable'] =
						array_key_exists($discovery['discoveryRule']['itemid'], $editable_lld_parents);
				}
			}
			unset($discovery);
		}

		if ($filter['sort'] === 'delay') {
			orderItemsByDelay($discoveries, $filter['sortorder'], ['usermacros' => true]);
		}
		elseif ($filter['sort'] === 'status') {
			orderItemsByStatus($discoveries, $filter['sortorder']);
		}
		else {
			order_result($discoveries, $filter['sort'], $filter['sortorder']);
		}

		return $discoveries;
	}

	protected function getFilter(): array {
		$filter = $this->getProfileFilters() + [
				'ms_groups' => [],
				'ms_hosts' => []
			];

		if ($filter['filter_hostids']) {
			if ($this->context === 'host') {
				$filter['ms_hosts'] = CArrayHelper::renameObjectsKeys(API::Host()->get([
					'output' => ['hostid', 'name'],
					'hostids' => $filter['filter_hostids'],
					'editable' => true,
					'preservekeys' => true
				]), ['hostid' => 'id']);
			}
			else {
				$filter['ms_hosts'] = CArrayHelper::renameObjectsKeys(API::Template()->get([
					'output' => ['hostid', 'name'],
					'templateids' => $filter['filter_hostids'],
					'editable' => true,
					'preservekeys' => true
				]), ['templateid' => 'id']);
			}

			$filter['filter_hostids'] = array_column($filter['ms_hosts'], 'id');
		}

		if ($filter['filter_groupids']) {
			$filter['filter_groupids'] = getSubGroups($filter['filter_groupids'], $filter['ms_groups'], $this->context);
		}

		return $filter;
	}

	protected function getProfileFilters(): array {
		$prefix = $this->getProfilePrefix();

		return [
			'filter_groupids'				=> CProfile::getArray($prefix.'filter.groupids', []),
			'filter_hostids'				=> CProfile::getArray($prefix.'filter.hostids', []),
			'filter_name'					=> CProfile::get($prefix.'filter.name', ''),
			'filter_key'					=> CProfile::get($prefix.'filter.key', ''),
			'filter_type'					=> (int) CProfile::get($prefix.'filter.type', -1),
			'filter_delay'					=> CProfile::get($prefix.'filter.delay', ''),
			'filter_lifetime_type'			=> (int) CProfile::get($prefix.'filter.lifetime_type', -1),
			'filter_lifetime'				=> CProfile::get($prefix.'filter.lifetime', ''),
			'filter_enabled_lifetime_type'	=> (int) CProfile::get($prefix.'filter.enabled_lifetime_type', -1),
			'filter_enabled_lifetime'		=> CProfile::get($prefix.'filter.enabled_lifetime', ''),
			'filter_snmp_oid'				=> CProfile::get($prefix.'filter.snmp_oid', ''),
			'filter_state'					=> (int) CProfile::get($prefix.'filter.state', -1),
			'filter_status'					=> (int) CProfile::get($prefix.'filter.status', -1),
			'sort'							=> CProfile::get($prefix.'sort', 'name'),
			'sortorder' 					=> CProfile::get($prefix.'sortorder', ZBX_SORT_UP)
		];
	}

	protected function updateProfileFilters(): void {
		$prefix = $this->getProfilePrefix();

		CProfile::updateArray($prefix.'filter.groupids', $this->getInput('filter_groupids', []), PROFILE_TYPE_ID);
		CProfile::updateArray($prefix.'filter.hostids', $this->getInput('filter_hostids', []), PROFILE_TYPE_ID);
		CProfile::update($prefix.'filter.name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::update($prefix.'filter.key', $this->getInput('filter_key', ''), PROFILE_TYPE_STR);
		CProfile::update($prefix.'filter.type', $this->getInput('filter_type', -1), PROFILE_TYPE_INT);
		CProfile::update($prefix.'filter.delay', $this->getInput('filter_delay', ''), PROFILE_TYPE_STR);
		CProfile::update($prefix.'filter.lifetime_type', $this->getInput('filter_lifetime_type', -1),
			PROFILE_TYPE_INT
		);
		CProfile::update($prefix.'filter.lifetime', $this->getInput('filter_lifetime', ''), PROFILE_TYPE_STR);
		CProfile::update($prefix.'filter.enabled_lifetime_type', $this->getInput('filter_enabled_lifetime_type', -1),
			PROFILE_TYPE_INT
		);
		CProfile::update($prefix.'filter.enabled_lifetime', $this->getInput('filter_enabled_lifetime', ''),
			PROFILE_TYPE_STR
		);
		CProfile::update($prefix.'filter.snmp_oid', $this->getInput('filter_snmp_oid', ''), PROFILE_TYPE_STR);
		CProfile::update($prefix.'filter.state', $this->getInput('filter_state', -1), PROFILE_TYPE_INT);
		CProfile::update($prefix.'filter.status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);

		$this->updateProfileSort();
	}

	protected function updateProfileSort(): void {
		$prefix = $this->getProfilePrefix();

		if ($this->hasInput('sort')) {
			CProfile::update($prefix.'sort', $this->getInput('sort'), PROFILE_TYPE_STR);
		}

		if ($this->hasInput('sortorder')) {
			CProfile::update($prefix.'sortorder', $this->getInput('sortorder'), PROFILE_TYPE_STR);
		}
	}

	protected function deleteProfileFilters(): void {
		$prefix = $this->getProfilePrefix();

		CProfile::deleteIdx($prefix.'filter.groupids');

		if (count(CProfile::getArray($prefix.'filter.hostids', [])) != 1) {
			CProfile::deleteIdx($prefix.'filter.hostids');
		}

		CProfile::delete($prefix.'filter.name');
		CProfile::delete($prefix.'filter.key');
		CProfile::delete($prefix.'filter.type');
		CProfile::delete($prefix.'filter.delay');
		CProfile::delete($prefix.'filter.lifetime_type');
		CProfile::delete($prefix.'filter.lifetime');
		CProfile::delete($prefix.'filter.enabled_lifetime_type');
		CProfile::delete($prefix.'filter.enabled_lifetime');
		CProfile::delete($prefix.'filter.snmp_oid');
		CProfile::delete($prefix.'filter.state');
		CProfile::delete($prefix.'filter.status');
	}
}
