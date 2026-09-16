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


class CControllerApmLogListData extends CControllerDataTable {

	protected array $allowed_data_fields = ['timestamp', 'traceid', 'spanid', 'trace_flags', 'severity_text',
		'severity_number', 'service_name', 'body', 'resource_schema_url', 'resource_attributes', 'scope_schema_url',
		'scope_name', 'scope_version', 'scope_attributes', 'log_attributes', 'event_name'
	];

	protected array $filter;

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_LOGS);
	}

	protected function checkInput(): bool {
		$this->addValidationRules(['filter' => 'array|required']);

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
			'body' => ['string', 'required'],
			'traceid' => ['string', 'required'],
			'spanid' => ['string', 'required'],
			'service_name' => ['string', 'required'],
			'scope_name' => ['string', 'required'],
			'severities' => ['array', 'required', 'field' => ['integer',
				'in' => [APM_LOG_SEVERITY_TRACE, APM_LOG_SEVERITY_DEBUG, APM_LOG_SEVERITY_INFO,
					APM_LOG_SEVERITY_WARNING, APM_LOG_SEVERITY_ERROR, APM_LOG_SEVERITY_FATAL
				]
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

		$data_fields = $this->getDataFields($this->allowed_data_fields);

		$sort_field = $this->getInput('sort_field', 'timestamp');
		$sort_order = $this->getInput('sort_order', ZBX_SORT_DOWN);

		CProfile::update('web.apm.log.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.apm.log.sortorder', $sort_order, PROFILE_TYPE_STR);

		$timeline = getTimeSelectorPeriod([
			'profileIdx' => 'web.apm.log.filter',
			'profileIdx2' => 0,
			'from' => $this->filter['from'],
			'to' => $this->filter['to']
		]);

		$severity_numbers = null;

		if ($this->filter['severities']) {
			$severity_numbers = [];

			foreach ($this->filter['severities'] as $severity) {
				$severity_numbers = array_merge($severity_numbers, range($severity * 4, $severity * 4 + 3));
			}
		}

		$resource_attributes = array_filter($this->filter['resource_attributes'],
			static fn (array $attribute) => $attribute['key'] !== ''
		);

		$output = [
			'data_fields' => $data_fields
		];

		$options = [
			'time_from' => $timeline['from_ts'],
			'time_till' => $timeline['to_ts'],
			'severity_numbers' => $severity_numbers,
			'resource_attributes_evaltype' => $this->filter['resource_attributes_evaltype'],
			'resource_attributes' => $resource_attributes,
			'filter' => [
				'traceid' => $this->filter['traceid'] !== '' ? $this->filter['traceid'] : null,
				'spanid' => $this->filter['spanid'] !== '' ? $this->filter['spanid'] : null
			],
			'search' => [
				'body' => $this->filter['body'] !== '' ? $this->filter['body'] : null,
				'service_name' => $this->filter['service_name'] !== '' ? $this->filter['service_name'] : null,
				'scope_name' => $this->filter['scope_name'] !== '' ? $this->filter['scope_name'] : null
			],
			'sortfield' => $sort_field,
			'sortorder' => $sort_order
		];

		$num_rows = (int) API::ApmLog()->get($options + [
			'countOutput' => true
		]);

		if ($num_rows > 0) {
			$this->paging = $this->paginateNumRows($num_rows, $this->getInput('page', 1), $sort_order, $offset, $limit);

			$logs = API::ApmLog()->get($options + [
				'output' => $data_fields,
				'offset' => $offset,
				'limit' => $limit
			]);

			$output['rows'] = array_values(array_map(static fn (array $log) => [['renderer' => 'log'], $log], $logs));
		}

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
		}
		catch (DBException) {
			return false;
		}

		return true;
	}
}
