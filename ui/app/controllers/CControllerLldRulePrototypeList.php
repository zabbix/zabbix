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


class CControllerLldRulePrototypeList extends CController {

	protected string $context;
	private array $parent_discovery = [];

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context'						=> 'required|in host,template',
			'parent_discoveryid' 			=> 'required|db items.itemid',
			'sort'							=> 'in delay,key_,name,status,type,discover',
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

		$has_access = $this->context === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

		if (!$has_access) {
			return false;
		}

		$options = [
			'output' => ['itemid', 'name', 'flags'],
			'selectDiscoveryData' => ['parent_itemid'],
			'itemids' => $this->getInput('parent_discoveryid'),
			'selectHosts' => ['hostid', 'name', 'monitored_by', 'proxyid', 'assigned_proxyid', 'status', 'flags'],
			'editable' => true
		];

		$parent_discovery = API::DiscoveryRule()->get($options) ?: API::DiscoveryRulePrototype()->get($options);

		if (!$parent_discovery) {
			return false;
		}

		$this->parent_discovery = reset($parent_discovery);

		return true;
	}

	private function getProfilePrefix () {
		return $this->context === 'host'
			? 'web.hosts.lldrules.prototypes.'
			: 'web.templates.lldrules.prototypes.';
	}

	protected function doAction(): void {
		$this->updateProfileSort();
		$page = $this->getInput('page', 1);
		$filter = $this->getFilter();

		$discoveries = $this->getDiscoveries($filter);

		$view_url = new CUrl('zabbix.php');
		$view_url_params = ['action' => $this->getAction(), 'context' => $this->context,
			'parent_discoveryid' => $this->getInput('parent_discoveryid')
		];

		array_map([$view_url, 'setArgument'], array_keys($view_url_params), $view_url_params);
		$paging = CPagerHelper::paginate($page, $discoveries, $filter['sortorder'], $view_url);

		$data = [
			'action' => $this->getAction(),
			'context' => $this->context,
			'hostid' => $this->parent_discovery['hosts'][0]['hostid'],
			'parent_discoveryid' => $this->parent_discovery['itemid'],
			'is_parent_discovered' => $this->parent_discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED,
			'sort' => $filter['sort'],
			'sortorder' => $filter['sortorder'],
			'active_tab' => CProfile::get($this->getProfilePrefix().'filter.active', 1),
			'checkbox_hash' => $this->parent_discovery['itemid'],
			'discoveries' => $discoveries,
			'paging' => $paging,
			'parent_templates' => getItemParentTemplates($discoveries, ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE),
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		];

		if ($this->parent_discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
			$data['source_link_data'] = [
				'parent_itemid' => $this->parent_discovery['discoveryData']['parent_itemid'],
				'name' => $this->parent_discovery['name']
			];
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of discovery prototypes'));
		$this->setResponse($response);
	}

	protected function getDiscoveries(array $filter): array {
		$options = [
			'output' => ['itemid', 'type', 'name', 'key_', 'delay', 'status', 'templateid', 'flags', 'master_itemid',
				'discover'
			],
			'selectHosts' => ['hostid', 'name', 'status', 'flags'],
			'selectItems' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectHostPrototypes' => API_OUTPUT_COUNT,
			'selectDiscoveryRulePrototypes' => API_OUTPUT_COUNT,
			'selectDiscoveryData' => ['parent_itemid'],
			'discoveryids' => $this->parent_discovery['itemid'],
			'templated' => $this->context === 'template',
			'sortfield' => $filter['sort'],
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
			'editable' => true
		];

		$discoveries = API::DiscoveryRulePrototype()->get($options);

		if ($filter['sort'] === 'delay') {
			orderItemsByDelay($discoveries, $filter['sortorder'], ['usermacros' => true, 'lldmacros' => true]);
		}
		else {
			order_result($discoveries, $filter['sort'], $filter['sortorder']);
		}

		$discoveries = expandItemNamesWithMasterItems($discoveries, 'items');

		return $discoveries;
	}

	protected function getFilter(): array {
		$prefix = $this->getProfilePrefix();

		$filter = [
			'sort'		=> CProfile::get($prefix.'sort', 'name'),
			'sortorder'	=> CProfile::get($prefix.'sortorder', ZBX_SORT_UP)
		];

		return $filter;
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
}
