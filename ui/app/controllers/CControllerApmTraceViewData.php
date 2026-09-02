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


class CControllerApmTraceViewData extends CControllerDataTable {

	protected array $allowed_data_fields = ['traceid', 'service_name', 'span_count', 'error_count', 'span_name',
		'timestamp', 'span_attributes', 'duration_time_units', 'duration_percentage'];

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_TRACES);
	}

	protected function getData(): array
	{
		$page = $this->getInput('page', 1);

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

		$limit = (int) CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$traces = API::Apm()->getTraces([
			'time_from' => $timeline['from_ts'],
			'time_till' => $timeline['to_ts'],
			'sortfield' => $sort_field,
			'sortorder' => $sort_order,
			'limit' => $limit
		]);

		$rows = [];

		if ($traces) {
			$this->paging = $this->paginate($traces, $page, $sort_order);

			foreach ($traces as &$trace) {
				$trace['duration_time_units'] = convertSecondsToTimeUnits(
					bcmul($trace['duration'], sprintf('%f', SEC_PER_MICROSEC)));
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
