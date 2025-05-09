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

	/**
	 * @var array
	 */
	private $discovery_rule = [];

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
		$discovery_rule = API::DiscoveryRule()->get([
			'output' => ['itemid', 'hostid'],
			'itemids' => $this->getInput('parent_discoveryid'),
			'editable' => true
		]);

		if (!$discovery_rule) {
			$discovery_rule = API::DiscoveryRulePrototype()->get([
				'output' => ['itemid', 'hostid'],
				'itemids' => $this->getInput('parent_discoveryid'),
				'editable' => true
			]);
		}

		if (!$discovery_rule) {
			return false;
		}

		$this->discovery_rule = reset($discovery_rule);

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

		$hostid = $this->discovery_rule['hostid'];
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		$graphs = API::GraphPrototype()->get([
			'output' => ['graphid', 'name', 'graphtype'],
			'templated' => $context === 'template',
			'discoveryids' => $this->discovery_rule['itemid'],
			'editable' => true,
			'limit' => $limit
		]);

		$data = [
			'graphs' => $graphs,
			'hostid' => $hostid
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
			foreach ($data['graphs'] as $gnum => $graph) {
				$data['graphs'][$gnum]['graphtype'] = graphType($graph['graphtype']);
			}
		}

		order_result($data['graphs'], $sort_field, $sort_order);

		// Pager.
		$page_num = $this->getInput('page', 1);

		CPagerHelper::savePage('graph.list', $page_num);
		$paging = CPagerHelper::paginate($page_num, $data['graphs'], $sort_order, (new CUrl('zabbix.php'))
			->setArgument('action', 'graph.prototype.list')
			->setArgument('context', $context)
			->setArgument('parent_discoveryid', $this->discovery_rule['itemid'])
		);

		// Get graphs after paging.
		$data['graphs'] = API::GraphPrototype()->get([
			'output' => ['graphid', 'name', 'templateid', 'graphtype', 'width', 'height', 'discover', 'flags'],
			'selectDiscoveryRule' => ['itemid'],
			'selectDiscoveryRulePrototype' => ['itemid'],
			'selectDiscoveryData' => ['parent_graphid'],
			'graphids' => array_column($data['graphs'], 'graphid'),
			'preservekeys' => true
		]);

		// Get the name of the LLD rule that discovered the prototype and the parent_itemid for the prototype source.
		$parent_graphids = [];
		$parent_lldruleids = [];

		foreach ($data['graphs'] as $graph) {
			if ($graph['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
				$parent_lld = $graph['discoveryRule'] ?: $graph['discoveryRulePrototype'];
				$parent_graphids[$graph['discoveryData']['parent_graphid']] = $graph['graphid'];
				$parent_lldruleids[$parent_lld['itemid']][] = $graph['graphid'];
			}
		}

		if ($parent_graphids) {
			$parent_graph_prototypes = API::GraphPrototype()->get([
				'output' => [],
				'selectDiscoveryRule' => ['itemid'],
				'selectDiscoveryRulePrototype' => ['itemid'],
				'graphids' => array_keys($parent_graphids),
				'preservekeys' => true
			]);

			foreach ($parent_graph_prototypes as $graphid => $parent_graph_prototype) {
				$parent_lld = $parent_graph_prototype['discoveryRule'] ?: $parent_graph_prototype['discoveryRulePrototype'];
				$data['graphs'][$parent_graphids[$graphid]]['parent_lld']['itemid'] = $parent_lld['itemid'];
			}

			$lld_rules = API::DiscoveryRule()->get([
				'output' => [],
				'selectDiscoveryRule' => ['name'],
				'itemids' => array_keys($parent_lldruleids),
				'preservekeys' => true
			]);

			if (!$lld_rules) {
				$lld_rules = API::DiscoveryRulePrototype()->get([
					'output' => [],
					'selectDiscoveryRule' => ['name'],
					'itemids' => array_keys($parent_lldruleids),
					'preservekeys' => true
				]);
			}

			foreach ($lld_rules as $lldruleid => $lld_rule) {
				foreach ($parent_lldruleids[$lldruleid] as $graphid) {
					$data['graphs'][$graphid]['parent_lld']['name'] = $lld_rule['discoveryRule']['name'];
				}
			}
		}

		order_result($data['graphs'], $sort_field, $sort_order);

		$data += [
			'parent_discoveryid' => $this->discovery_rule['itemid'],
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
