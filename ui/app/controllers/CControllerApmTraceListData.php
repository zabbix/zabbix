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


class CControllerApmTraceListData extends CControllerDataTable {

	protected array $allowed_data_fields = ['traceid', 'service_name', 'span_count', 'error_count', 'span_name',
		'timestamp', 'span_attributes', 'duration_time_units', 'duration_percentage'];

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_TRACES);
	}

	protected function getData(): array
	{
		$settings = API::Settings()->get(['output' => ['apm_global_db']]);
		if ($settings['apm_global_db']['status'] === APM_GLOBAL_DB_STATUS_NOT_CONFIGURED) {
			return [
				'no_data_icon' => ZBX_ICON_SEARCH_LARGE,
				'no_data_message' => _('No data source')
			];
		}

		$page = $this->getInput('page', 1);
		$filter = $this->getInput('filter', []);

		$sort_field = $this->getInput('sort_field', 'timestamp');
		$sort_order = $this->getInput('sort_order', ZBX_SORT_DOWN);

		CProfile::update('web.apm.trace.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.apm.trace.sortorder', $sort_order, PROFILE_TYPE_STR);

		$timeline = getTimeSelectorPeriod([
			'profileIdx' => 'web.apm.trace.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		]);

		$min_duration = $filter['min_duration']
			? (int) round(timeUnitToSeconds($filter['min_duration']) / SEC_PER_NANOSEC)
			: null;
		$max_duration = $filter['max_duration']
			? (int) round(timeUnitToSeconds($filter['max_duration']) / SEC_PER_NANOSEC)
			: null;
		$statuses = array_key_exists('statuses', $filter) && $filter['statuses']
			? $filter['statuses']
			: [];
		$span_attributes = array_key_exists('attributes', $filter) && $filter['attributes']
			? $filter['attributes']
			: null;
		$span_attributes_evaltype = array_key_exists('evaltype', $filter) && $filter['evaltype']
			? $filter['evaltype']
			: CONDITION_EVAL_TYPE_AND_OR;

		$trace_filter = array_filter(array_intersect_key($filter, array_flip(['traceid', 'spanid', 'span_name',
			'service_name', 'scope_name'])));

		if ($statuses) {
			$trace_filter['status_code'] = CApmTraceHelper::getStatusCodes($statuses);
		}

		$limit = (int) CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$traces = API::ApmTrace()->get([
			'output' => ['traceid', 'service_name', 'span_count', 'error_count', 'span_name', 'timestamp',
				'span_attributes', 'duration'],
			'time_from' => $timeline['from_ts'],
			'time_till' => $timeline['to_ts'],
			'min_duration' => $min_duration,
			'max_duration' => $max_duration,
			'filter' => $trace_filter ?: null,
			'span_attributes' => $span_attributes,
			'span_attributes_evaltype' => $span_attributes_evaltype,
			'sortfield' => $sort_field,
			'sortorder' => $sort_order,
			'limit' => $limit
		]);

		$rows = [];

		if ($traces) {
			$this->paging = $this->paginate($traces, $page, $sort_order);

			foreach ($traces as &$trace) {
				if (!$trace['parent_spanid']) {
					$trace['service_name'] = $trace['service_name'] ?: '['._('No root span found').']';
					$trace['span_name'] = $trace['span_name'] ?: '['._('No root span found').']';
				}

				$trace['duration_time_units'] = convertSecondsToTimeUnits($trace['duration'] * SEC_PER_NANOSEC);
				$trace['duration_percentage'] = round(bcdiv($trace['duration'], 86_400_000_000) * 100);
			}
			unset($trace);

			$rows = array_values(array_map(static fn (array $trace) => [['renderer' => 'trace'], $trace], $traces));
		}

		$output = [
			'data_fields' => $this->getDataFields(['traceid']),
			'rows' => $rows
		];

		$debug_mode = CWebUser::$data['debug_mode'] ?? GROUP_DEBUG_MODE_DISABLED;

		if ($debug_mode == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$output['debug'] = CProfiler::getInstance()->make()->toString();
		}

		return $output;
	}
}
