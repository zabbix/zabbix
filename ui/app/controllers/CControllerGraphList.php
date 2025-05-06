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


class CControllerGraphList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context' =>			'required|in '.implode(',', ['host', 'template']),
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1',
			'filter_groupids' =>	'array_db hosts_groups.groupid',
			'filter_hostids' =>		'array_db hosts.hostid',
			'sort' =>				'in '.implode(',', ['graphtype', 'name']),
			'sortorder' =>			'in '.implode(',', [ZBX_SORT_UP, ZBX_SORT_DOWN]),
			'page' =>				'ge 1',
			'uncheck' =>            'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		// Update profile keys.
		$context = $this->getInput('context');
		$prefix = $context === 'host' ? 'web.hosts.' : 'web.templates.';

		$sort_field = $this->getInput('sort', CProfile::get($prefix.'graph.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get($prefix.'graph.list.sortorder', ZBX_SORT_UP));

		CProfile::update($prefix.'graph.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update($prefix.'graph.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::updateArray($prefix.'graph.list.filter_groupids', $this->getInput('filter_groupids', []),
				PROFILE_TYPE_ID
			);
			CProfile::updateArray($prefix.'graph.list.filter_hostids', $this->getInput('filter_hostids', []),
				PROFILE_TYPE_ID
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::deleteIdx($prefix.'graph.list.filter_groupids');

			$filter_hostids = $this->getInput('filter_hostids',
				CProfile::getArray($prefix.'graph.list.filter_hostids', [])
			);

			if (count($filter_hostids) != 1) {
				CProfile::deleteIdx($prefix.'graph.list.filter_hostids');
			}
		}

		$filter_groupids = CProfile::getArray($prefix.'graph.list.filter_groupids', []);
		$filter_hostids = CProfile::getArray($prefix.'graph.list.filter_hostids', []);

		$filter = [
			'hosts' => [],
			'groups' => []
		];

		$filter_groupids = getSubGroups($filter_groupids, $filter['groups'], $context);

		if ($context === 'host') {
			$filter['hosts'] = $filter_hostids
				? CArrayHelper::renameObjectsKeys(API::Host()->get([
					'output' => ['hostid', 'name'],
					'hostids' => $filter_hostids,
					'editable' => true,
					'preservekeys' => true
				]), ['hostid' => 'id'])
				: [];
		}
		else {
			$filter['hosts'] = $filter_hostids
				? CArrayHelper::renameObjectsKeys(API::Template()->get([
					'output' => ['templateid', 'name'],
					'templateids' => $filter_hostids,
					'editable' => true,
					'preservekeys' => true
				]), ['templateid' => 'id'])
				: [];
		}

		$hostid = 0;

		if (count($filter['hosts']) == 1) {
			$hostid = reset($filter['hosts'])['id'];
		}

		// Select graphs.
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		$graphs = API::Graph()->get([
			'output' => ['graphid', 'name', 'graphtype'],
			'hostids' => $filter['hosts'] ? array_keys($filter['hosts']) : null,
			'groupids' => $filter_groupids ?: null,
			'templated' => $context === 'template',
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

		$paging = CPagerHelper::paginate($page_num, $data['graphs'], $sort_order,
			(new CUrl('zabbix.php'))
				->setArgument('action', 'graph.list')
				->setArgument('context', $context)
		);

		// Get graphs after paging.
		$data['graphs'] = API::Graph()->get([
			'output' => ['graphid', 'name', 'templateid', 'graphtype', 'width', 'height'],
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectDiscoveryData' => ['status', 'ts_delete'],
			'selectHosts' => ['name'],
			'graphids' => array_column($data['graphs'], 'graphid'),
			'preservekeys' => true
		]);

		foreach ($data['graphs'] as $gnum => $graph) {
			$data['graphs'][$gnum]['graphtype'] = graphType($graph['graphtype']);
		}

		order_result($data['graphs'], $sort_field, $sort_order);

		if ($data['hostid'] == 0) {
			foreach ($data['graphs'] as &$graph) {
				CArrayHelper::sort($graph['hosts'], ['name']);
			}
			unset($graph);
		}

		$data += [
			'filter' => $filter,
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'profileIdx' => $prefix.'graph.list.filter',
			'active_tab' => CProfile::get($prefix.'graph.list.filter.active', 1),
			'context' => $context,
			'page' => $page_num,
			'paging' => $paging,
			'parent_templates' => getGraphParentTemplates($data['graphs'], ZBX_FLAG_DISCOVERY_NORMAL),
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
			'checkbox_hash' => crc32(implode('', $filter_hostids)),
			'uncheck' => $this->hasInput('uncheck')
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of graphs'));
		$this->setResponse($response);
	}
}
