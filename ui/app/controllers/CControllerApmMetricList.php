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


class CControllerApmMetricList extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_TRACES);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'filter_metric_name' => ['string'],
			'filter_service_name' => ['string'],
			'filter_scope_name' => ['string'],
			'filter_types' => ['array', 'field' => ['integer', 'in' => [APM_METRIC_TYPE_GAUGE, APM_METRIC_TYPE_SUM,
				APM_METRIC_TYPE_HISTOGRAM, APM_METRIC_TYPE_EXPONENTIAL_HISTOGRAM]]],
			'filter_evaltype' => ['integer', 'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]],
			'filter_attributes_evaltype' => ['integer', 'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]
			],
			'filter_attributes' => ['objects', 'fields' => [
				'key' => ['string', 'required'],
				'operator' => ['integer', 'required',
					'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS
					]
				],
				'value' => ['string', 'required']
			]],
			'filter_resource_attributes_evaltype' => ['integer',
				'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]
			],
			'filter_resource_attributes' => ['objects', 'fields' => [
				'key' => ['string', 'required'],
				'operator' => ['integer', 'required',
					'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS
					]
				],
				'value' => ['string', 'required']
			]],
			'filter_scope_attributes_evaltype' => ['integer',
				'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]
			],
			'filter_scope_attributes' => ['objects', 'fields' => [
				'key' => ['string', 'required'],
				'operator' => ['integer', 'required',
					'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS
					]
				],
				'value' => ['string', 'required']
			]],
			'from' => ['string', 'use' => [CRangeTimeValidator::class]],
			'to' => ['string', 'use' => [CRangeTimeValidator::class]],
			'sort' => ['string', 'in' => ['metric_name', 'start_time_unix']],
			'sortorder' => ['string', 'in' => [ZBX_SORT_DOWN, ZBX_SORT_UP]],
			'page' => ['integer', 'min' => 1],
			'filter_set' => ['integer', 'in' => ['1']],
			'filter_rst' => ['integer', 'in' => ['1']]
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

		$sort_field = $this->getInput('sort', CProfile::get('web.apm.metric.sort', 'metric_name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.apm.metric.sortorder', ZBX_SORT_DOWN));

		CProfile::update('web.apm.metric.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.apm.metric.sortorder', $sort_order, PROFILE_TYPE_STR);

		$storage_idx = 'web.apm.metric.datatable';

		$timeselector_options = [
			'profileIdx' => 'web.apm.metric.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];
		updateTimeSelectorPeriod($timeselector_options);

		$filter_attributes = [];

		foreach (CProfile::getArray('web.apm.metric.filter_attributes.key', []) as $i => $attribute) {
			$filter_attributes[] = [
				'key' => $attribute,
				'value' => CProfile::get('web.apm.metric.filter_attributes.value', null, $i),
				'operator' => CProfile::get('web.apm.metric.filter_attributes.operator', null, $i)
			];
		}

		$filter_resource_attributes = [];

		foreach (CProfile::getArray('web.apm.metric.filter_resource_attributes.key', []) as $i => $attribute) {
			$filter_resource_attributes[] = [
				'key' => $attribute,
				'value' => CProfile::get('web.apm.metric.filter_resource_attributes.value', null, $i),
				'operator' => CProfile::get('web.apm.metric.filter_resource_attributes.operator', null, $i)
			];
		}

		$filter_scope_attributes = [];

		foreach (CProfile::getArray('web.apm.metric.filter_scope_attributes.key', []) as $i => $attribute) {
			$filter_scope_attributes[] = [
				'key' => $attribute,
				'value' => CProfile::get('web.apm.metric.filter_scope_attributes.value', null, $i),
				'operator' => CProfile::get('web.apm.metric.filter_scope_attributes.operator', null, $i)
			];
		}

		$filter = [
			'metric_name' => CProfile::get('web.apm.metric.filter_metric_name', ''),
			'types' => CProfile::getArray('web.apm.metric.filter_types', []),
			'service_name' => CProfile::get('web.apm.metric.filter_service_name', ''),
			'scope_name' => CProfile::get('web.apm.metric.filter_scope_name', ''),
			'attributes_evaltype' => CProfile::get('web.apm.metric.filter_attributes_evaltype',
				CONDITION_EVAL_TYPE_AND_OR
			),
			'attributes' => $filter_attributes,
			'resource_attributes_evaltype' => CProfile::get('web.apm.metric.filter_resource_attributes_evaltype',
				CONDITION_EVAL_TYPE_AND_OR
			),
			'resource_attributes' => $filter_resource_attributes,
			'scope_attributes_evaltype' => CProfile::get('web.apm.metric.filter_scope_attributes_evaltype',
				CONDITION_EVAL_TYPE_AND_OR
			),
			'scope_attributes' => $filter_scope_attributes
		];

		$data = [
			'action' => $this->getAction(),
			'default_sort_field' => 'metric_name',
			'default_sort_order' => ZBX_SORT_UP,
			'filter' => $filter,
			'filter_options' => [
				'idx' => 'web.apm.metric.filter',
				'timeselector' => getTimeSelectorPeriod($timeselector_options)
			],
			'filter_validation_rules' => (new CFormValidator(self::getValidationRules()))->getRules(),
			'active_tab' => CProfile::get('web.apm.metric.filter.active', 2),
			'page' => $this->getInput('page', 1),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'sort_field' => $sort_field,
			'sort_order' => $sort_order,
			'storage_idx' => $storage_idx,
			'user' => ['debug_mode' => $this->getDebugMode()],
			'user_configs' => array_map(static fn (string $user_config) => json_decode($user_config, true) ?? [],
				CProfile::getArray($storage_idx, []))
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Metrics'));
		$this->setResponse($response);
	}

	private function updateProfiles(): void {
		$filter_attributes = [];

		foreach ($this->getInput('filter_attributes', []) as $filter_attribute) {
			if ($filter_attribute['key'] !== '' || $filter_attribute['value'] !== '') {
				$filter_attributes[] = $filter_attribute;
			}
		}

		$filter_resource_attributes = [];

		foreach ($this->getInput('filter_resource_attributes', []) as $filter_resource_attribute) {
			if ($filter_resource_attribute['key'] !== '' || $filter_resource_attribute['value'] !== '') {
				$filter_resource_attributes[] = $filter_resource_attribute;
			}
		}

		$filter_scope_attributes = [];

		foreach ($this->getInput('filter_scope_attributes', []) as $filter_scope_attribute) {
			if ($filter_scope_attribute['key'] !== '' || $filter_scope_attribute['value'] !== '') {
				$filter_scope_attributes[] = $filter_scope_attribute;
			}
		}

		CProfile::update('web.apm.metric.filter_metric_name', $this->getInput('filter_metric_name', ''),
			PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.apm.metric.filter_types', $this->getInput('filter_types', []),
			PROFILE_TYPE_INT
		);
		CProfile::update('web.apm.metric.filter_service_name', $this->getInput('filter_service_name', ''),
			PROFILE_TYPE_STR
		);
		CProfile::update('web.apm.metric.filter_scope_name', $this->getInput('filter_scope_name', ''),
			PROFILE_TYPE_STR
		);
		CProfile::update('web.apm.metric.filter_evaltype',
			$this->getInput('filter_evaltype', CONDITION_EVAL_TYPE_AND_OR), PROFILE_TYPE_INT
		);
		CProfile::updateArray('web.apm.metric.filter_attributes.key', array_column($filter_attributes, 'key'),
			PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.apm.metric.filter_attributes.value', array_column($filter_attributes, 'value'),
			PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.apm.metric.filter_attributes.operator', array_column($filter_attributes, 'operator'),
			PROFILE_TYPE_INT
		);
		CProfile::update('web.apm.metric.filter_resource_evaltype', $this->getInput('filter_resource_evaltype',
			CONDITION_EVAL_TYPE_AND_OR), PROFILE_TYPE_INT
		);
		CProfile::updateArray('web.apm.metric.filter_resource_attributes.key',
			array_column($filter_resource_attributes, 'key'), PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.apm.metric.filter_resource_attributes.value',
			array_column($filter_resource_attributes, 'value'), PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.apm.metric.filter_resource_attributes.operator',
			array_column($filter_resource_attributes, 'operator'), PROFILE_TYPE_INT
		);
		CProfile::update('web.apm.metric.filter_scope_evaltype',
			$this->getInput('filter_scope_evaltype', CONDITION_EVAL_TYPE_AND_OR), PROFILE_TYPE_INT
		);
		CProfile::updateArray('web.apm.metric.filter_scope_attributes.key',
			array_column($filter_scope_attributes, 'key'), PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.apm.metric.filter_scope_attributes.value',
			array_column($filter_scope_attributes, 'value'), PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.apm.metric.filter_scope_attributes.operator',
			array_column($filter_scope_attributes, 'operator'), PROFILE_TYPE_INT
		);
	}

	private function deleteProfiles(): void {
		CProfile::delete('web.apm.metric.filter_metric_name');
		CProfile::deleteIdx('web.apm.metric.filter_types');
		CProfile::delete('web.apm.metric.filter_service_name');
		CProfile::delete('web.apm.metric.filter_scope_name');
		CProfile::delete('web.apm.metric.filter_evaltype');
		CProfile::delete('web.apm.metric.filter_resource_evaltype');
		CProfile::delete('web.apm.metric.filter_scope_evaltype');
		CProfile::deleteIdx('web.apm.metric.filter_attributes.key');
		CProfile::deleteIdx('web.apm.metric.filter_attributes.value');
		CProfile::deleteIdx('web.apm.metric.filter_attributes.operator');
		CProfile::deleteIdx('web.apm.metric.filter_resource_attributes.key');
		CProfile::deleteIdx('web.apm.metric.filter_resource_attributes.value');
		CProfile::deleteIdx('web.apm.metric.filter_resource_attributes.operator');
		CProfile::deleteIdx('web.apm.metric.filter_scope_attributes.key');
		CProfile::deleteIdx('web.apm.metric.filter_scope_attributes.value');
		CProfile::deleteIdx('web.apm.metric.filter_scope_attributes.operator');
	}
}
