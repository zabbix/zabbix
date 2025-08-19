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


class CControllerGraphPrototypeList extends CController {

	private array $parent_discovery = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context' =>			'required|in '.implode(',', ['host', 'template']),
			'parent_discoveryid' =>	'required|db items.itemid',
			'sort' =>				'in '.implode(',', ['graphtype', 'name', 'discover']),
			'sortorder' =>			'in '.implode(',', [ZBX_SORT_UP, ZBX_SORT_DOWN]),
			'page' =>				'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		$options = [
			'output' => ['itemid', 'name', 'hostid', 'flags'],
			'selectDiscoveryData' => ['parent_itemid'],
			'itemids' => $this->getInput('parent_discoveryid'),
			'editable' => true
		];

		$parent_discovery = API::DiscoveryRule()->get($options) ?: API::DiscoveryRulePrototype()->get($options);

		if (!$parent_discovery) {
			return false;
		}

		$this->parent_discovery = reset($parent_discovery);

		return $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		// Update profile keys.
		$context = $this->getInput('context');
		$prefix = $context === 'host' ? 'web.hosts.' : 'web.templates.';
		$sort_field = $this->getInput('sort', CProfile::get($prefix.'graph.prototype.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder',
			CProfile::get($prefix.'graph.prototype.list.sortorder', ZBX_SORT_UP)
		);

		CProfile::update($prefix.'graph.prototype.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update($prefix.'graph.prototype.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		$hostid = $this->parent_discovery['hostid'];
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		$graphs = API::GraphPrototype()->get([
			'output' => ['graphid', 'name', 'graphtype'],
			'templated' => $context === 'template',
			'discoveryids' => $this->parent_discovery['itemid'],
			'editable' => true,
			'limit' => $limit
		]);

		$data = [
			'graphs' => $graphs,
			'hostid' => $hostid,
			'is_parent_discovered' => $this->parent_discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED
		];

		if ($context === 'host') {
			$editable_hosts = API::Host()->get([
				'output' => ['hostids'],
				'graphids' => array_column($data['graphs'], 'graphid'),
				'editable' => true
			]);

			$data['editable_hosts'] = array_column($editable_hosts, 'hostid');
		}

		if ($sort_field === 'graphtype') {
			foreach ($data['graphs'] as &$graph) {
				$graph['graphtype'] = graphType($graph['graphtype']);
			}
			unset($graph);
		}

		order_result($data['graphs'], $sort_field, $sort_order);

		// Pager.
		$page_num = $this->getInput('page', 1);

		CPagerHelper::savePage('graph.list', $page_num);
		$paging = CPagerHelper::paginate($page_num, $data['graphs'], $sort_order, (new CUrl('zabbix.php'))
			->setArgument('action', 'graph.prototype.list')
			->setArgument('context', $context)
			->setArgument('parent_discoveryid', $this->parent_discovery['itemid'])
		);

		// Get graphs after paging.
		$data['graphs'] = API::GraphPrototype()->get([
			'output' => ['graphid', 'name', 'templateid', 'graphtype', 'width', 'height', 'discover', 'flags'],
			'selectDiscoveryData' => ['parent_graphid'],
			'graphids' => array_column($data['graphs'], 'graphid')
		]);

		if ($this->parent_discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
			$data['source_link_data'] = [
				'parent_itemid' => $this->parent_discovery['discoveryData']['parent_itemid'],
				'name' => $this->parent_discovery['name']
			];
		}

		foreach ($data['graphs'] as &$graph) {
			$graph['graphtype'] = graphType($graph['graphtype']);
		}
		unset($graph);

		order_result($data['graphs'], $sort_field, $sort_order);

		$data += [
			'parent_discoveryid' => $this->parent_discovery['itemid'],
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'profileIdx' => $prefix.'graph.prototype.list.filter',
			'active_tab' => CProfile::get($prefix.'graph.prototype.list.filter.active', 1),
			'context' => $context,
			'page' => $page_num,
			'paging' => $paging,
			'parent_templates' => getGraphParentTemplates($data['graphs'], ZBX_FLAG_DISCOVERY_PROTOTYPE),
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of graph prototypes'));
		$this->setResponse($response);
	}
}
