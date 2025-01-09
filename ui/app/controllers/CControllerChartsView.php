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


/**
 * Class to handle "charts.view" requests.
 */
class CControllerChartsView extends CControllerCharts {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'from' =>				'range_time',
			'to' =>					'range_time',
			'view_as' =>			'in '.HISTORY_GRAPH.','.HISTORY_VALUES,
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1',
			'filter_hostids' =>		'array_id',
			'filter_name' =>		'string',
			'filter_show' =>		'in '.GRAPH_FILTER_ALL.','.GRAPH_FILTER_HOST.','.GRAPH_FILTER_SIMPLE,
			'subfilter_set' =>		'in 1',
			'subfilter_tagnames' =>	'array',
			'subfilter_tags' =>		'array',
			'page' =>				'ge 1'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if ($ret && $this->hasInput('subfilter_tagnames')) {
			$tagnames = $this->getInput('subfilter_tagnames', []);
			$ret = !$tagnames || count($tagnames) == count(array_filter($tagnames, 'is_string'));
		}

		if ($ret && $this->hasInput('subfilter_tags')) {
			$tags = $this->getInput('subfilter_tags', []);
			foreach ($tags as $tag => $values) {
				if (!is_scalar($tag) || !is_array($values)
						|| count($values) != count(array_filter($values, 'is_string'))) {
					$ret = false;
					break;
				}
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
	}

	protected function doAction() {
		if ($this->hasInput('filter_rst')) {
			CProfile::deleteIdx('web.charts.filter.hostids');
			CProfile::deleteIdx('web.charts.filter.name');
			CProfile::deleteIdx('web.charts.filter.show');
			CProfile::deleteIdx('web.charts.subfilter.tagnames');
			CProfile::deleteIdx('web.charts.subfilter.tags');
		}
		elseif ($this->hasInput('subfilter_set')) {
			CProfile::updateArray('web.charts.subfilter.tagnames', $this->getInput('subfilter_tagnames', []), PROFILE_TYPE_STR);
			CProfile::update('web.charts.subfilter.tags', json_encode($this->getInput('subfilter_tags', [])), PROFILE_TYPE_STR);
		}
		elseif ($this->hasInput('filter_set')) {
			CProfile::updateArray('web.charts.filter.hostids', $this->getInput('filter_hostids', []), PROFILE_TYPE_ID);
			CProfile::update('web.charts.filter.name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.charts.filter.show',
				$this->getInput('filter_show', GRAPH_FILTER_ALL), PROFILE_TYPE_INT
			);
		}

		$filter_hostids = CProfile::getArray('web.charts.filter.hostids', []);
		$filter_name = CProfile::get('web.charts.filter.name', '');
		$filter_show = (int) CProfile::get('web.charts.filter.show', GRAPH_FILTER_ALL);

		$subfilter_tagnames = CProfile::getArray('web.charts.subfilter.tagnames', []);
		$subfilter_tags = json_decode(CProfile::get('web.charts.subfilter.tags', '{}'), true);

		$timeselector_options = [
			'profileIdx' => 'web.charts.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];
		updateTimeSelectorPeriod($timeselector_options);

		$data = [
			'view_as' => $this->getInput('view_as', HISTORY_GRAPH),
			'ms_hosts' => [],
			'timeline' => getTimeSelectorPeriod($timeselector_options),
			'active_tab' => CProfile::get('web.charts.filter.active', 1),
			'filter_hostids' => $filter_hostids,
			'filter_name' => $filter_name,
			'filter_show' => $filter_show,
			'subfilter_tagnames' => $subfilter_tagnames,
			'subfilter_tags' => $subfilter_tags,
			'error' => '',
			'page' => $this->getInput('page', 1)
		];

		if ($filter_hostids) {
			$data['ms_hosts'] = CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['name', 'hostid'],
				'hostids' => $filter_hostids
			]), ['hostid' => 'id']);

			// Continue with readable hosts only.
			if (count($filter_hostids) != count($data['ms_hosts'])) {
				$filter_hostids = array_column($data['ms_hosts'], 'id');
				if ($this->hasInput('filter_set')) {
					$data['error'] = _('No permissions to referred object or it does not exist!');
				}
			}
		}

		$host_graphs = [];
		$simple_graphs = [];

		if ($filter_hostids) {
			if (in_array($filter_show, [GRAPH_FILTER_ALL, GRAPH_FILTER_HOST])) {
				$host_graphs = $this->getHostGraphs($filter_hostids, $filter_name);
			}

			if (in_array($filter_show, [GRAPH_FILTER_ALL, GRAPH_FILTER_SIMPLE])) {
				$simple_graphs = $this->getSimpleGraphs($filter_hostids, $filter_name);
			}
		}

		$graphs = array_merge($host_graphs, $simple_graphs);

		// Prepare subfilter data.
		$subfilters_fields = self::getSubfilterFields([
			'subfilter_tagnames' => $subfilter_tagnames,
			'subfilter_tags' => $subfilter_tags
		]);
		$data['subfilters'] = self::getSubfilters($graphs, $subfilters_fields);
		$graphs = self::applySubfilters($graphs);

		CArrayHelper::sort($graphs, ['name', 'graphid', 'itemid']);

		$data['paging'] = CPagerHelper::paginate($data['page'], $graphs, ZBX_SORT_UP,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$data['charts'] = $this->getCharts($graphs);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Custom graphs'));

		$this->setResponse($response);
	}
}
