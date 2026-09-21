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


class CControllerApmTraceList extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_TRACES);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'filter_traceid' => ['string'],
			'filter_spanid' => ['string'],
			'filter_service_name' => ['string'],
			'filter_operation_name' => ['string'],
			'filter_scope_name' => ['string'],
			'filter_min_duration' => ['string', 'use' => [CTimeUnitValidator::class]],
			'filter_max_duration' => ['string', 'use' => [CTimeUnitValidator::class,]],
			'filter_statuses' => ['array', 'field' => ['integer', 'in' => [APM_TRACE_STATUS_UNSET, APM_TRACE_STATUS_OK,
				APM_TRACE_STATUS_ERROR]]],
			'filter_resource_attributes_evaltype' => ['integer', 'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]],
			'filter_resource_attributes' => ['array',
				'field' => ['object', 'fields' => [
					'key' => ['string', 'required'],
					'operator' => ['integer', 'required', 'in' => [CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_EQUAL,
						CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_EXISTS, CONDITION_OPERATOR_NOT_EQUAL,
						CONDITION_OPERATOR_NOT_LIKE]],
					'value' => ['string', 'required']
				]]
			],
			'filter_span_attributes_evaltype' => ['integer', 'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]],
			'filter_span_attributes' => ['array',
				'field' => ['object', 'fields' => [
					'key' => ['string', 'required'],
					'operator' => ['integer', 'required', 'in' => [CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_EQUAL,
						CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_EXISTS, CONDITION_OPERATOR_NOT_EQUAL,
						CONDITION_OPERATOR_NOT_LIKE]],
					'value' => ['string', 'required']
				]]
			],
			'sort' => ['string', 'in' => ['timestamp']],
			'sortorder' => ['string', 'in' => [ZBX_SORT_DOWN, ZBX_SORT_UP]],
			'page' => ['integer', 'min' => 1],
			'filter_set' => ['integer', 'in' => [1]],
			'filter_rst' => ['integer', 'in' => [1]],
			'from' => ['string', 'use' => [CRangeTimeValidator::class]],
			'to' => ['string', 'use' => [CRangeTimeValidator::class]]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules()) && $this->validateTimeSelectorPeriod();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			$this->updateProfiles();
		}
		elseif ($this->hasInput('filter_rst')) {
			$this->deleteProfiles();
		}

		$sort_field = $this->getInput('sort', CProfile::get('web.apm.trace.sort', 'timestamp'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.apm.trace.sortorder', ZBX_SORT_DOWN));

		CProfile::update('web.apm.trace.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.apm.trace.sortorder', $sort_order, PROFILE_TYPE_STR);

		$storage_idx = 'web.apm.trace.datatable';

		$timeselector_options = [
			'profileIdx' => 'web.apm.trace.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];
		updateTimeSelectorPeriod($timeselector_options);

		$filter_resource_attributes = [];
		$filter_resource_attributes_evaltype = CProfile::get('web.apm.trace.filter_resource_attributes_evaltype',
			CONDITION_EVAL_TYPE_AND_OR);

		foreach (CProfile::getArray('web.apm.trace.filter_resource_attributes.key', []) as $i => $attribute) {
			$filter_resource_attributes[] = [
				'key' => $attribute,
				'value' => CProfile::get('web.apm.trace.filter_resource_attributes.value', null, $i),
				'operator' => CProfile::get('web.apm.trace.filter_resource_attributes.operator', null, $i)
			];
		}

		if (!$filter_resource_attributes) {
			$filter_resource_attributes[] = ['key' => '', 'value' => '', 'operator' => CONDITION_OPERATOR_LIKE];
		}

		$filter_span_attributes = [];
		$filter_span_attributes_evaltype = CProfile::get('web.apm.trace.filter_span_attributes_evaltype',
			CONDITION_EVAL_TYPE_AND_OR);

		foreach (CProfile::getArray('web.apm.trace.filter_span_attributes.key', []) as $i => $attribute) {
			$filter_span_attributes[] = [
				'key' => $attribute,
				'value' => CProfile::get('web.apm.trace.filter_span_attributes.value', null, $i),
				'operator' => CProfile::get('web.apm.trace.filter_span_attributes.operator', null, $i)
			];
		}

		if (!$filter_span_attributes) {
			$filter_span_attributes[] = ['key' => '', 'value' => '', 'operator' => CONDITION_OPERATOR_LIKE];
		}

		$filter = [
			'traceid' => CProfile::get('web.apm.trace.filter_traceid', ''),
			'spanid' => CProfile::get('web.apm.trace.filter_spanid', ''),
			'service_name' => CProfile::get('web.apm.trace.filter_service_name', ''),
			'operation_name' => CProfile::get('web.apm.trace.filter_operation_name', ''),
			'scope_name' => CProfile::get('web.apm.trace.filter_scope_name', ''),
			'min_duration' => CProfile::get('web.apm.trace.filter_min_duration', ''),
			'max_duration' => CProfile::get('web.apm.trace.filter_max_duration', ''),
			'statuses' => CProfile::getArray('web.apm.trace.filter_statuses', []),
			'resource_attributes_evaltype' => $filter_resource_attributes_evaltype,
			'resource_attributes' => $filter_resource_attributes,
			'span_attributes_evaltype' => $filter_span_attributes_evaltype,
			'span_attributes' => $filter_span_attributes
		];

		$data = [
			'action' => $this->getAction(),
			'default_sort_field' => 'timestamp',
			'default_sort_order' => ZBX_SORT_DOWN,
			'filter' => $filter,
			'filter_options' => [
				'idx' => 'web.apm.trace.filter',
				'timeselector' => getTimeSelectorPeriod($timeselector_options)
			],
			'filter_validation_rules' => (new CFormValidator(self::getValidationRules()))->getRules(),
			'active_tab' => CProfile::get('web.apm.trace.filter.active', 2),
			'page' => $this->getInput('page', 1),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'sort_field' => $sort_field,
			'sort_order' => $sort_order,
			'storage_idx' => $storage_idx,
			'user' => ['debug_mode' => $this->getDebugMode()],
			'user_configs' => array_map(static fn (string $user_config) => json_decode($user_config, true) ?? [],
				CProfile::getArray($storage_idx, [])
			),
			'side_drawer_position' => CProfile::get('web.apm.trace.side_drawer.position', '20%')
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Traces'));
		$this->setResponse($response);
	}

	private function updateProfiles(): void {
		CProfile::update('web.apm.trace.filter_traceid', $this->getInput('filter_traceid', ''), PROFILE_TYPE_STR);
		CProfile::update('web.apm.trace.filter_spanid', $this->getInput('filter_spanid', ''), PROFILE_TYPE_STR);
		CProfile::update('web.apm.trace.filter_service_name', $this->getInput('filter_service_name', ''),
			PROFILE_TYPE_STR);
		CProfile::update('web.apm.trace.filter_operation_name', $this->getInput('filter_operation_name', ''),
			PROFILE_TYPE_STR);
		CProfile::update('web.apm.trace.filter_scope_name', $this->getInput('filter_scope_name', ''), PROFILE_TYPE_STR);
		CProfile::update('web.apm.trace.filter_min_duration', $this->getInput('filter_min_duration', ''),
			PROFILE_TYPE_STR);
		CProfile::update('web.apm.trace.filter_max_duration', $this->getInput('filter_max_duration', ''),
			PROFILE_TYPE_STR);
		CProfile::updateArray('web.apm.trace.filter_statuses', $this->getInput('filter_statuses', []),
			PROFILE_TYPE_INT);

		$filter_resource_attributes = [];

		foreach ($this->getInput('filter_resource_attributes', []) as $resource_attribute) {
			if (!array_key_exists('key', $resource_attribute) || !array_key_exists('value', $resource_attribute)) {
				continue;
			}

			if ($resource_attribute['key'] !== '' || $resource_attribute['value'] !== '') {
				$filter_resource_attributes[] = $resource_attribute;
			}
		}

		CProfile::update('web.apm.trace.filter_resource_attributes_evaltype',
			$this->getInput('filter_resource_attributes_evaltype', CONDITION_EVAL_TYPE_AND_OR), PROFILE_TYPE_INT);
		CProfile::updateArray('web.apm.trace.filter_resource_attributes.key',
			array_column($filter_resource_attributes, 'key'), PROFILE_TYPE_STR);
		CProfile::updateArray('web.apm.trace.filter_resource_attributes.value',
			array_column($filter_resource_attributes, 'value'), PROFILE_TYPE_STR);
		CProfile::updateArray('web.apm.trace.filter_resource_attributes.operator',
			array_column($filter_resource_attributes, 'operator'), PROFILE_TYPE_INT);

		$filter_span_attributes = [];

		foreach ($this->getInput('filter_span_attributes', []) as $span_attribute) {
			if (!array_key_exists('key', $span_attribute) || !array_key_exists('value', $span_attribute)) {
				continue;
			}

			if ($span_attribute['key'] !== '' || $span_attribute['value'] !== '') {
				$filter_span_attributes[] = $span_attribute;
			}
		}

		CProfile::update('web.apm.trace.filter_span_attributes_evaltype',
			$this->getInput('filter_span_attributes_evaltype', CONDITION_EVAL_TYPE_AND_OR), PROFILE_TYPE_INT);
		CProfile::updateArray('web.apm.trace.filter_span_attributes.key',
			array_column($filter_span_attributes, 'key'), PROFILE_TYPE_STR);
		CProfile::updateArray('web.apm.trace.filter_span_attributes.value',
			array_column($filter_span_attributes, 'value'), PROFILE_TYPE_STR);
		CProfile::updateArray('web.apm.trace.filter_span_attributes.operator',
			array_column($filter_span_attributes, 'operator'), PROFILE_TYPE_INT);
	}

	private function deleteProfiles(): void {
		CProfile::delete('web.apm.trace.filter_traceid');
		CProfile::delete('web.apm.trace.filter_spanid');
		CProfile::delete('web.apm.trace.filter_service_name');
		CProfile::delete('web.apm.trace.filter_operation_name');
		CProfile::delete('web.apm.trace.filter_scope_name');
		CProfile::delete('web.apm.trace.filter_min_duration');
		CProfile::delete('web.apm.trace.filter_max_duration');
		CProfile::deleteIdx('web.apm.trace.filter_statuses');

		CProfile::delete('web.apm.trace.filter_resource_attributes_evaltype');
		CProfile::deleteIdx('web.apm.trace.filter_resource_attributes.key');
		CProfile::deleteIdx('web.apm.trace.filter_resource_attributes.value');
		CProfile::deleteIdx('web.apm.trace.filter_resource_attributes.operator');

		CProfile::delete('web.apm.trace.filter_span_attributes_evaltype');
		CProfile::deleteIdx('web.apm.trace.filter_span_attributes.key');
		CProfile::deleteIdx('web.apm.trace.filter_span_attributes.value');
		CProfile::deleteIdx('web.apm.trace.filter_span_attributes.operator');
	}
}
