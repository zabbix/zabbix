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


class CControllerApmMetricListData extends CControllerDataTable {

	protected array $allowed_data_fields = ['metric_name', 'type', 'scope_name', 'metric_unit', 'service_name',
		'start_time_unix', 'value', 'count'
	];

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_METRICS);
	}

	protected function getData(): array {
		if (!$this->isDataSourceConfigured()) {
			return [
				'no_data_icon' => ZBX_ICON_APM_NOT_CONFIGURED_LARGE,
				'no_data_message' => _('No data source')
			];
		}

		$page = $this->getInput('page', 1);
		$filter = $this->getInput('filter', []);
		$search = array_intersect_key($filter, array_flip(['metric_name', 'service_name', 'scope_name']));

		$sort_field = $this->getInput('sort_field', 'metric_name');
		$sort_order = $this->getInput('sort_order', ZBX_SORT_DOWN);

		CProfile::update('web.apm.metric.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.apm.metric.sortorder', $sort_order, PROFILE_TYPE_STR);

		$timeline = getTimeSelectorPeriod([
			'profileIdx' => 'web.apm.metric.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		]);

		$limit = (int) CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		$metrics = API::ApmMetric()->get([
			'output' => $this->getDataFields(),
			'types' => array_key_exists('types', $filter) && $filter['types'] ? $filter['types'] : null,
			'time_from' => $timeline['from_ts'],
			'time_till' => $timeline['to_ts'],
			'search' => array_filter($search) ?: null,
			'sortfield' => $this->getInput('sort_field', 'metric_name'),
			'sortorder' => $sort_order,
			'limit' => $limit
		]);

		$rows = [];

		if ($metrics) {
			$this->paging = $this->paginate($metrics, $page, $sort_order);

			$rows = array_values(array_map(static fn (array $metric) => [['renderer' => 'trace'], $metric], $metrics));
		}

		$output = [
			'data_fields' => $this->getDataFields(),
			'rows' => $rows
		];

		$debug_mode = CWebUser::$data['debug_mode'] ?? GROUP_DEBUG_MODE_DISABLED;

		if ($debug_mode == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$output['debug'] = CProfiler::getInstance()->make()->toString();
		}

		return $output;
	}

	protected function isDataSourceConfigured(): bool {
		try {
			ApmDb::getInstance()->getConfig();
		} catch (DBException) {
			return false;
		}

		return true;
	}
}
