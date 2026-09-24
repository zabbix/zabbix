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

	protected array $allowed_data_fields = ['type', 'resource_attributes', 'resource_schema_url', 'scope_name',
		'scope_version', 'scope_attributes', 'scope_schema_url', 'service_name', 'metric_name', 'metric_description',
		'metric_unit', 'attributes', 'value', 'flags', 'exemplars', 'aggregation_temporality','is_monotonic', 'count',
		'sum', 'min', 'max', 'histogram_buckets', 'start_time_formatted', 'start_time_ns_formatted', 'time_formatted',
		'time_ns_formatted'
	];

	protected array $filter;

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_METRICS);
	}

	protected function checkInput(): bool {
		$this->addValidationRules([
			'filter' => 'array|required'
		]);

		if (!parent::checkInput()) {
			return false;
		}

		$this->filter = $this->getInput('filter');

		$validator = new CFormValidator(self::getFilterValidationRules());

		if ($validator->validate($this->filter) !== CFormValidator::SUCCESS) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => _('Invalid request')
					]
				])])
			);

			return false;
		}


		return true;
	}

	protected static function getFilterValidationRules(): array {
		return ['object', 'fields' => [
			'metric_name' => ['string'],
			'service_name' => ['string'],
			'scope_name' => ['string'],
			'types' => ['array', 'field' => ['integer', 'in' => [APM_METRIC_TYPE_GAUGE, APM_METRIC_TYPE_SUM,
				APM_METRIC_TYPE_HISTOGRAM, APM_METRIC_TYPE_EXPONENTIAL_HISTOGRAM
			]]],
			'attributes_evaltype' => ['integer', 'required',
				'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]
			],
			'attributes' => ['objects', 'required', 'fields' => [
				'key' => ['string', 'required'],
				'operator' => ['integer', 'required',
					'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS
					]
				],
				'value' => ['string', 'required']
			]],
			'resource_attributes_evaltype' => ['integer', 'required',
				'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]
			],
			'resource_attributes' => ['objects', 'required', 'fields' => [
				'key' => ['string', 'required'],
				'operator' => ['integer', 'required',
					'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS
					]
				],
				'value' => ['string', 'required']
			]],
			'scope_attributes_evaltype' => ['integer', 'required',
				'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]
			],
			'scope_attributes' => ['objects', 'required', 'fields' => [
				'key' => ['string', 'required'],
				'operator' => ['integer', 'required',
					'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS
					]
				],
				'value' => ['string', 'required']
			]],
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

		$sort_field = $this->getInput('sort_field', 'metric_name');
		$sort_order = $this->getInput('sort_order', ZBX_SORT_DOWN);

		$sort_field = match ($sort_field) {
			'start_time_formatted' => 'start_time_unix',
			'time_formatted' => 'time_unix',
			default => $sort_field
		};

		CProfile::update('web.apm.metric.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.apm.metric.sortorder', $sort_order, PROFILE_TYPE_STR);

		$timeline = getTimeSelectorPeriod([
			'profileIdx' => 'web.apm.metric.filter',
			'profileIdx2' => 0,
			'from' => $this->filter['from'],
			'to' => $this->filter['to']
		]);

		$attributes = array_filter($this->filter['attributes'],
			static fn (array $attribute) => $attribute['key'] !== ''
		);

		$resource_attributes = array_filter($this->filter['resource_attributes'],
			static fn (array $attribute) => $attribute['key'] !== ''
		);

		$scope_attributes = array_filter($this->filter['scope_attributes'],
			static fn (array $attribute) => $attribute['key'] !== ''
		);

		$output = ['type', 'resource_attributes', 'resource_schema_url', 'scope_name', 'scope_version',
			'scope_attributes', 'scope_schema_url', 'service_name', 'metric_name', 'metric_description', 'metric_unit',
			'attributes', 'start_time_unix', 'time_unix', 'value', 'flags', 'exemplars', 'aggregation_temporality',
			'is_monotonic', 'count', 'sum', 'bucket_counts', 'explicit_bounds', 'min', 'max', 'scale', 'zero_count',
			'positive_offset', 'positive_bucket_counts', 'negative_offset', 'negative_bucket_counts'
		];

		$data_fields = $this->getDataFields(['type', 'resource_attributes', 'resource_schema_url', 'scope_name',
			'scope_version', 'scope_attributes', 'scope_schema_url', 'service_name', 'metric_name',
			'metric_description', 'metric_unit', 'attributes', 'value', 'flags', 'exemplars', 'aggregation_temporality',
			'is_monotonic', 'count', 'sum', 'min', 'max', 'histogram_buckets', 'start_time_ns_formatted',
			'time_ns_formatted'
		]);

		$result = [
			'data_fields' => $data_fields
		];

		$options = [
			'types' => $this->filter['types'] ?: null,
			'time_from' => $timeline['from_ts'],
			'time_till' => $timeline['to_ts'],
			'attributes_evaltype' => $this->filter['attributes_evaltype'],
			'attributes' => $attributes ?: null,
			'resource_attributes_evaltype' => $this->filter['resource_attributes_evaltype'],
			'resource_attributes' => $resource_attributes ?: null,
			'scope_attributes_evaltype' => $this->filter['scope_attributes_evaltype'],
			'scope_attributes' => $scope_attributes ?: null,
			'search' => [
				'metric_name' => $this->filter['metric_name'] !== '' ? $this->filter['metric_name'] : null,
				'service_name' => $this->filter['service_name'] !== '' ? $this->filter['service_name'] : null,
				'scope_name' => $this->filter['scope_name'] !== '' ? $this->filter['scope_name'] : null
			],
			'sortfield' => $sort_field,
			'sortorder' => $sort_order
		];

		$search_limit = (int) CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$num_rows = (int) API::ApmMetric()->get($options + [
			'countOutput' => true,
			'limit' => $search_limit
		]);

		if ($num_rows > 0) {
			$this->paging = $this->paginateNumRows($num_rows, $this->getInput('page', 1), null, $offset, $limit);

			$metrics = API::ApmMetric()->get($options + [
				'output' => $output,
				'offset' => $offset,
				'limit' => $limit
			]);

			$result['rows'] = [];

			$today = strtotime('today');

			foreach ($metrics as $metric) {
				$clock = floor($metric['start_time_unix'] / 1000000000);
				$metric['start_time_ns_formatted'] = $this->formatTimeNs($metric['start_time_unix'], $today, $clock);

				if (in_array('start_time_formatted', $data_fields)) {
					$metric['start_time_formatted'] = $clock >= $today
						? zbx_date2str(TIME_FORMAT_SECONDS, $clock)
						: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $clock);
				}

				$clock = floor($metric['time_unix'] / 1000000000);
				$metric['time_ns_formatted'] = $this->formatTimeNs($metric['time_unix'], $today, $clock);

				if (in_array('time_formatted', $data_fields)) {
					$metric['time_formatted'] = $clock >= $today
						? zbx_date2str(TIME_FORMAT_SECONDS, $clock)
						: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $clock);
				}

				foreach ($metric['exemplars'] as &$exemplar) {
					$exemplar['time_ns_formatted'] = $this->formatTimeNs($exemplar['time_unix'], $today);

					unset($exemplar['time_unix']);
				}
				unset($exemplar);

				if ($metric['type'] == APM_METRIC_TYPE_HISTOGRAM) {
					$metric['histogram_buckets'] = $this->prepareHistogramBuckets($metric['explicit_bounds'],
						$metric['bucket_counts']
					);
				}
				elseif ($metric['type'] == APM_METRIC_TYPE_EXPONENTIAL_HISTOGRAM) {
					$metric['histogram_buckets'] = $this->prepareExponentialHistogramBuckets($metric['scale'],
						$metric['positive_offset'], $metric['positive_bucket_counts'], $metric['negative_offset'],
						$metric['negative_bucket_counts'], $metric['zero_count']
					);
				}
				else {
					$metric['histogram_buckets'] = null;
				}

				$metric = array_intersect_key($metric, array_flip($data_fields));

				$result['rows'][] = [['renderer' => 'metric'], $metric];
			}
		}

		$debug_mode = CWebUser::$data['debug_mode'] ?? GROUP_DEBUG_MODE_DISABLED;

		if ($debug_mode == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$result['debug'] = CProfiler::getInstance()->make()->toString();
		}

		return $result;
	}

	private function prepareHistogramBuckets(array $explicit_bounds, array $bucket_counts): array {
		$buckets = [];
		$explicit_bounds_count = count($explicit_bounds);

		foreach ($bucket_counts as $i => $count) {
			if ($i === 0) {
				$name = '≤ '.convertUnits(['value' => $explicit_bounds[0]]);
			}
			elseif ($i < $explicit_bounds_count) {
				$lower = convertUnits(['value' => $explicit_bounds[$i - 1]]);
				$upper = convertUnits(['value' =>$explicit_bounds[$i]]);

				$name = '> '.$lower.' - '.$upper;
			}
			else {
				$name = '> '.convertUnits(['value' => $explicit_bounds[$explicit_bounds_count - 1]]);
			}

			$buckets[] = [
				'name' => $name,
				'value' => $count
			];
		}

		return $buckets;
	}

	private function prepareExponentialHistogramBuckets(int $scale, int $positive_offset, array $positive_bucket_counts,
			int $negative_offset, array $negative_bucket_counts, int $zero_count): array {
		$buckets = [];

		$scale_factor = 2 ** $scale;

		foreach ($negative_bucket_counts as $i => $counts) {
			$index = $negative_offset + $i;

			$lower = convertUnits(['value' => 2 ** ($index / $scale_factor)]);
			$upper = convertUnits(['value' => 2 ** (($index + 1) / $scale_factor)]);

			$buckets[] = [
				'name' => ($i === 0 ? '>= ' : '> ').(-$upper).'-'.(-$lower),
				'value' => $counts
			];
		}

		if ($zero_count > 0) {
			$buckets[] = [
				'name' => '0',
				'value' => convertUnits(['value' => $zero_count])
			];
		}

		foreach ($positive_bucket_counts as $i => $counts) {
			$index = $positive_offset + $i;

			$lower = convertUnits(['value' => 2 ** ($index / $scale_factor)]);
			$upper = convertUnits(['value' => 2 ** (($index + 1) / $scale_factor)]);

			$buckets[] = [
				'name' => ($i === 0 ? '>= ' : '> ' ).$lower.' - '.$upper,
				'value' => $counts
			];
		}

		return $buckets;
	}

	private function formatTimeNs($time_unix, $today, $clock = null): string {
		if ($clock === null) {
			$clock = floor($time_unix / 1000000000);
		}

		$ns = str_pad((string) ($time_unix % 1000000000), 9, '0', STR_PAD_LEFT);
		return $clock >= $today
			? strtr(zbx_date2str(strtr(TIME_FORMAT_SECONDS, ['s' => 's.!']), $clock), ['!' => $ns])
			: strtr(zbx_date2str(strtr(DATE_TIME_FORMAT_SECONDS, ['s' => 's.!']), $clock),
				['!' => $ns]
			);
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
