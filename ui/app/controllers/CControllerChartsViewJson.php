<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * Controller for the chart list refresh in "Charts" charts.view.
 */
class CControllerChartsViewJson extends CControllerCharts {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'from'                  => 'range_time',
			'to'                    => 'range_time',
			'filter_hostids'        => 'required | array_id',
			'filter_name'           => 'string',
			'filter_show'           => 'in '.GRAPH_FILTER_ALL.','.GRAPH_FILTER_HOST.','.GRAPH_FILTER_SIMPLE,
			'subfilter_tagnames'    => 'array',
			'subfilter_tags'        => 'array',
			'page'                  => 'ge 1'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$timeselector_options = [
			'profileIdx' => 'web.charts.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];
		updateTimeSelectorPeriod($timeselector_options);

		$filter_hostids = $this->getInput('filter_hostids', []);
		$filter_name = $this->getInput('filter_name', '');
		$filter_show = $this->getInput('filter_show', GRAPH_FILTER_ALL);
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

		$subfilters_fields = self::getSubfilterFields([
			'subfilter_tagnames' => $this->getInput('subfilter_tagnames', []),
			'subfilter_tags' => $this->getInput('subfilter_tags', [])
		]);
		$subfilters = self::getSubfilters($graphs, $subfilters_fields);
		$graphs = self::applySubfilters($graphs);

		CArrayHelper::sort($graphs, ['name', 'graphid', 'itemid']);

		$paging = CPagerHelper::paginate($this->getInput('page', 1), $graphs, ZBX_SORT_UP,
			(new CUrl('zabbix.php'))->setArgument('action', 'charts.view')
		);

		$data = [
			'charts' => $this->getCharts($graphs),
			'timeline' => getTimeSelectorPeriod($timeselector_options),
			'subfilter' => (new CPartial('monitoring.charts.subfilter', $subfilters))->getOutput(),
			'paging' => $paging->toString()
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
