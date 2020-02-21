<?php
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


class CControllerChartsView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'from'                  => 'range_time',
			'to'                    => 'range_time',
			'action'                => 'in '.HISTORY_GRAPH.','.HISTORY_VALUES,
			'action'                => '',
			'filter_set' => 'in 1',
			'filter_rst' => 'in 1',
			'filter_search_type'    => 'in '.ZBX_SEARCH_TYPE_STRICT.','.ZBX_SEARCH_TYPE_PATTERN,
			'filter_hostids'        => 'array',
			'filter_graphids'       => 'array',
			'filter_graph_patterns' => 'array'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_USER) {
			return false;
		}

		if ($this->getInput('search_type', ZBX_SEARCH_TYPE_STRICT) == ZBX_SEARCH_TYPE_STRICT) {
		}
		else {
		}

		return true;
	}

	protected function getGraphidsByPatterns(array $patterns, array $hostids): array {
		return array_keys(API::Graph()->get([
			'output' => [],
			'hostids' => $hostids,
			'search' => ['name' => $patterns],
			'searchWildcardsEnabled' => true,
			'limit' => ZBX_MAX_GRAPHS_PER_PAGE,
			'preservekeys' => true,
			'searchByAny' => true
		]));
	}

	protected function getChartsById(array $graphids): array {
		$charts = [];

		foreach ($graphids as $graphid) {
			$graph_dims = getGraphDims($graphid);
			$chart = [
				'chartid' => $graphid,
			];

			$chart['dimensions'] = $graph_dims;

			if ($graph_dims['graphtype'] == GRAPH_TYPE_PIE || $graph_dims['graphtype'] == GRAPH_TYPE_EXPLODED) {
				$chart['sbox'] = false;
				$chart['src'] = 'chart6.php';
			}
			else {
				$chart['sbox'] = true;
				$chart['src'] = 'chart2.php';
			}

			$charts[] = $chart;
		}

		return $charts;
	}

	protected function doAction() {
		if ($this->hasInput('filter_rst')) {
			CProfile::deleteIdx('web.graphs.filter.search_type');
			CProfile::deleteIdx('web.graphs.filter.graphids');
			CProfile::deleteIdx('web.graphs.filter.graph_patterns');
		}
		else if ($this->hasInput('filter_set')) {
			CProfile::update('web.graphs.filter.search_type', $this->getInput('filter_search_type',
				ZBX_SEARCH_TYPE_STRICT
			), PROFILE_TYPE_INT);
			CProfile::updateArray('web.graphs.filter.graphids', $this->getInput('filter_graphids', []),
				PROFILE_TYPE_ID
			);
			CProfile::updateArray('web.graphs.filter.graph_patterns', $this->getInput('filter_graph_patterns', []),
				PROFILE_TYPE_STR
			);
			CProfile::updateArray('web.graphs.filter.hostids', $this->getInput('filter_hostids', []), PROFILE_TYPE_ID);
		}

		$filter_search_type = (int) $this->getInput('filter_search_type', CProfile::get(
			'web.graphs.filter.search_type', ZBX_SEARCH_TYPE_STRICT
		));

		$filter_graphids = $this->getInput('filter_graphids', CProfile::getArray(
			'web.graphs.filter.graphids', []
		));

		$filter_graph_patterns = $this->getInput('filter_graph_patterns', CProfile::getArray(
			'web.graphs.filter.graph_patterns', []
		));

		$filter_hostids = $this->getInput('filter_hostids', CProfile::getArray(
			'web.graphs.filter.hostids', []
		));

		$timeselector_options = [
			'profileIdx' => 'web.graphs.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];
		updateTimeSelectorPeriod($timeselector_options);

		$data = [
			'action' => $this->getInput('action', HISTORY_GRAPH),
			'actions' => [
				HISTORY_GRAPH => _('Graph'),
				HISTORY_VALUES => _('Values')
			],
			'graphids' => [],
			'ms_hosts' => [],
			'ms_graphs' => [],
			'ms_graph_patterns' => [],
			'timeline' => getTimeSelectorPeriod($timeselector_options),
			'active_tab' => CProfile::get('web.graphs.filter.active', 1),
			'filter_search_type' => $filter_search_type,
			'filter_hostids' => $filter_hostids,
			'filter_graphids' => $filter_graphids,
			'filter_graph_patterns' => $filter_graph_patterns,
			'must_specify_host' => false
		];

		foreach ($filter_graph_patterns as $pattern) {
			$data['ms_graph_patterns'][] = ['name' => $pattern, 'id' => $pattern];
		}

		if ($filter_graphids) {
			$data['ms_graphs'] = CArrayHelper::renameObjectsKeys(API::Graph()->get([
				'output' => ['name', 'graphid'],
				'graphids' => $filter_graphids
			]), ['graphid' => 'id']);
		}

		if ($filter_hostids) {
			$data['ms_hosts'] = CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['name', 'hostid'],
				'hostids' => $filter_hostids
			]), ['hostid' => 'id']);
		}

		if ($filter_search_type == ZBX_SEARCH_TYPE_STRICT) {
			$data['graphids'] = $filter_graphids;
		}
		else if ($filter_hostids) {
			$data['graphids'] = $this->getGraphidsByPatterns($filter_graph_patterns, $filter_hostids);
		}
		else {
			$data['must_specify_host'] = true;
		}
		$data['graphids'] = [910, 567082, 39963];

		if ($data['graphids']) {
			$data['charts'] = $this->getChartsById($data['graphids']);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Custom graphs'));

		$this->setResponse($response);
	}
}
