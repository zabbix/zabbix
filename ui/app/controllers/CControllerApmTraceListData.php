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

	protected array $allowed_data_fields = ['traceid', 'spanid', 'service_name', 'span_count', 'error_count',
		'span_name', 'span_kind', 'scope_name', 'scope_version', 'status_code', 'status_message', 'timestamp',
		'timestamp_formatted', 'timestamp_ns_formatted', 'trace_state', 'span_attributes', 'resource_attributes',
		'duration', 'duration_time_units', 'duration_percentage'];

	protected array $filter = [];

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_TRACES);
	}

	protected function checkInput(): bool {
		$this->addValidationRules(['filter' => 'array|required']);

		if (!parent::checkInput()) {
			return false;
		}

		$this->filter = $this->getInput('filter');

		$validator = new CFormValidator(self::getFilterValidationRules());

		return $validator->validate($this->filter) === CFormValidator::SUCCESS;
	}

	protected static function getFilterValidationRules(): array {
		return ['object', 'fields' => [
			'traceid' => ['string'],
			'spanid' => ['string'],
			'service_name' => ['string'],
			'span_name' => ['string'],
			'scope_name' => ['string'],
			'min_duration' => ['string', 'use' => [CTimeUnitValidator::class]],
			'max_duration' => ['string', 'use' => [CTimeUnitValidator::class]],
			'statuses' => ['array', 'field' => ['integer', 'in' => [APM_TRACE_STATUS_UNSET, APM_TRACE_STATUS_OK,
				APM_TRACE_STATUS_ERROR]]],
			'resource_attributes_evaltype' => ['integer', 'required',
				'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]
			],
			'resource_attributes' => ['array',
				'field' => ['object', 'fields' => [
					'key' => ['string'],
					'operator' => ['integer', 'required', 'in' => [CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_EQUAL,
						CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_EXISTS, CONDITION_OPERATOR_NOT_EQUAL,
						CONDITION_OPERATOR_NOT_LIKE]],
					'value' => ['string']
				]]
			],
			'span_attributes_evaltype' => ['integer', 'required',
				'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]
			],
			'span_attributes' => ['array',
				'field' => ['object', 'fields' => [
					'key' => ['string'],
					'operator' => ['integer', 'required', 'in' => [CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_EQUAL,
						CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_EXISTS, CONDITION_OPERATOR_NOT_EQUAL,
						CONDITION_OPERATOR_NOT_LIKE]],
					'value' => ['string']
				]]
			],
			'from' => ['string', 'required', 'use' => [CRangeTimeValidator::class]],
			'to' => ['string', 'required', 'use' => [CRangeTimeValidator::class]]
		]];
	}

	protected function getData(): array {
		if (!$this->isDataSourceConfigured()) {
			return [
				'no_data_icon' => ZBX_ICON_APM_NOT_CONFIGURED_LARGE,
				'no_data_message' => _('No data source')
			];
		}

		$page = $this->getInput('page', 1);

		$sort_field = $this->getInput('sort_field', 'timestamp');
		$sort_order = $this->getInput('sort_order', ZBX_SORT_DOWN);

		CProfile::update('web.apm.trace.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.apm.trace.sortorder', $sort_order, PROFILE_TYPE_STR);

		$timeline = getTimeSelectorPeriod([
			'profileIdx' => 'web.apm.trace.filter',
			'profileIdx2' => 0,
			'from' => $this->filter['from'],
			'to' => $this->filter['to']
		]);

		$min_duration = $this->filter['min_duration']
			? (int) round(timeUnitToSeconds($this->filter['min_duration']) / SEC_PER_NANOSEC)
			: null;

		$max_duration = $this->filter['max_duration']
			? (int) round(timeUnitToSeconds($this->filter['max_duration']) / SEC_PER_NANOSEC)
			: null;

		$statuses = array_key_exists('statuses', $this->filter) && $this->filter['statuses']
			? $this->filter['statuses']
			: [];

		$resource_attributes = array_key_exists('resource_attributes', $this->filter)
			&& $this->filter['resource_attributes']
				? array_filter($this->filter['resource_attributes'],
					static fn (array $attribute) => $attribute['key'] !== '')
				: null;

		$span_attributes = array_key_exists('span_attributes', $this->filter) && $this->filter['span_attributes']
			? array_filter($this->filter['span_attributes'], static fn (array $attribute) => $attribute['key'] !== '')
			: null;

		$filter = [];
		$search = [];

		foreach ($this->filter as $name => $value) {
			if (in_array($name, ['traceid', 'spanid'])) {
				$filter[$name] = $value;
			}

			if (in_array($name, ['span_name', 'service_name', 'scope_name'])) {
				$search[$name] = $value;
			}
		}

		if ($statuses) {
			$filter['status_code'] = CApmTraceHelper::getStatusCodes($statuses);
		}

		$data_fields = $this->getDataFields(['traceid', 'duration']);

		$select_fields = array_diff($data_fields, ['duration_time_units', 'duration_percentage']);

		if (array_intersect($select_fields, ['timestamp_formatted', 'timestamp_ns_formatted'])) {
			$select_fields = array_diff($select_fields, ['timestamp_formatted', 'timestamp_ns_formatted']);
			$select_fields[] = 'timestamp';
		}

		$limit = (int) CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$traces = API::ApmTrace()->get([
			'output' => $select_fields,
			'time_from' => $timeline['from_ts'],
			'time_till' => $timeline['to_ts'],
			'min_duration' => $min_duration,
			'max_duration' => $max_duration,
			'filter' => array_filter($filter) ?: null,
			'search' => array_filter($search) ?: null,
			'resource_attributes_evaltype' => $this->filter['resource_attributes_evaltype'],
			'resource_attributes' => $resource_attributes,
			'span_attributes_evaltype' => $this->filter['span_attributes_evaltype'],
			'span_attributes' => $span_attributes,
			'sortfield' => $sort_field,
			'sortorder' => $sort_order,
			'limit' => $limit
		]);

		$rows = [];

		if ($traces) {
			$this->paging = $this->paginate($traces, $page, $sort_order);

			$trace_max_duration = $traces ? max(array_column($traces, 'duration')) : 0;

			foreach ($traces as &$trace) {
				$trace['service_name'] = $trace['service_name'] ?: '['._('No root span found').']';
				$trace['span_name'] = $trace['span_name'] ?: '['._('No root span found').']';

				if (in_array('timestamp', $select_fields)) {
					$today = strtotime('today');
					$clock = floor($trace['timestamp'] / 1000000000);

					if (in_array('timestamp_formatted', $data_fields)) {
						$trace['timestamp_formatted'] = $clock >= $today
							? zbx_date2str(TIME_FORMAT_SECONDS, $clock)
							: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $clock);
					}

					if (in_array('timestamp_ns_formatted', $data_fields)) {
						$ns = str_pad((string)($trace['timestamp'] % 1000000000), 9, '0', STR_PAD_LEFT);

						$trace['timestamp_ns_formatted'] = $clock >= $today
							? strtr(zbx_date2str(strtr(TIME_FORMAT_SECONDS, ['s', 's.!']), $clock), ['!' => $ns])
							: strtr(zbx_date2str(strtr(DATE_TIME_FORMAT_SECONDS, ['s' => 's.!']), $clock),
								['!' => $ns]
							);
					}
				}

				$trace['duration_time_units'] = $trace['duration']
					? convertSecondsToTimeUnits($trace['duration'] * SEC_PER_NANOSEC, [
						'combine_last_subsecond_parts' => true
					])
					: '0'._x('ns', 'nanosecond short');
				$trace['duration_percentage'] = $trace_max_duration
					? round($trace['duration'] / $trace_max_duration * 100)
					: 0;
			}
			unset($trace);

			$rows = array_values(array_map(static fn (array $trace) => [['renderer' => 'trace'], $trace], $traces));
		}

		$output = [
			'data_fields' => $data_fields,
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
