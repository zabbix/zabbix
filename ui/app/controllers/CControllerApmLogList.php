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


class CControllerApmLogList extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_LOGS);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'filter_body' => ['string'],
			'filter_traceid' => ['string'],
			'filter_spanid' => ['string'],
			'filter_service_name' => ['string'],
			'filter_scope_name' => ['string'],
			'filter_severities' => ['array', 'field' => ['integer',
				'in' => [APM_LOG_SEVERITY_TRACE, APM_LOG_SEVERITY_DEBUG, APM_LOG_SEVERITY_INFO,
					APM_LOG_SEVERITY_WARNING, APM_LOG_SEVERITY_ERROR, APM_LOG_SEVERITY_FATAL
				]
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
			'from' => ['string', 'use' => [CRangeTimeValidator::class]],
			'to' => ['string', 'use' => [CRangeTimeValidator::class]],
			'sort' => ['string', 'in' => ['timestamp']],
			'sortorder' => ['string', 'in' => [ZBX_SORT_DOWN, ZBX_SORT_UP]],
			'page' => ['integer', 'min' => 1],
			'filter_set' => ['integer', 'in' => [1]],
			'filter_rst' => ['integer', 'in' => [1]]
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

		$sort_field = $this->getInput('sort', CProfile::get('web.apm.log.sort', 'timestamp'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.apm.log.sortorder', ZBX_SORT_DOWN));

		CProfile::update('web.apm.log.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.apm.log.sortorder', $sort_order, PROFILE_TYPE_STR);

		$storage_idx = 'web.apm.log.datatable';

		$timeselector_options = [
			'profileIdx' => 'web.apm.log.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];
		updateTimeSelectorPeriod($timeselector_options);

		$filter_resource_attributes = [];

		foreach (CProfile::getArray('web.apm.log.filter_resource_attributes.key', []) as $i => $key) {
			$filter_resource_attributes[] = [
				'key' => $key,
				'value' => CProfile::get('web.apm.log.filter_resource_attributes.value', null, $i),
				'operator' => CProfile::get('web.apm.log.filter_resource_attributes.operator', null, $i)
			];
		}

		$filter = [
			'body' => CProfile::get('web.apm.log.filter_body', ''),
			'traceid' => CProfile::get('web.apm.log.filter_traceid', ''),
			'spanid' => CProfile::get('web.apm.log.filter_spanid', ''),
			'service_name' => CProfile::get('web.apm.log.filter_service_name', ''),
			'scope_name' => CProfile::get('web.apm.log.filter_scope_name', ''),
			'severities' =>	CProfile::getArray('web.apm.log.filter_severities', []),
			'resource_attributes_evaltype' => CProfile::get('web.apm.log.filter_resource_attributes_evaltype',
				CONDITION_EVAL_TYPE_AND_OR
			),
			'resource_attributes' => $filter_resource_attributes
		];

		$data = [
			'action' => $this->getAction(),
			'default_sort_field' => 'timestamp',
			'default_sort_order' => ZBX_SORT_DOWN,
			'filter' => $filter,
			'filter_options' => [
				'idx' => 'web.apm.log.filter',
				'timeselector' => getTimeSelectorPeriod($timeselector_options)
			],
			'filter_validation_rules' => (new CFormValidator(self::getValidationRules()))->getRules(),
			'active_tab' => CProfile::get('web.apm.log.filter.active', 2),
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
		$response->setTitle(_('Logs'));
		$this->setResponse($response);
	}

	private function updateProfiles(): void {
		$filter_resource_attributes = [];

		foreach ($this->getInput('filter_resource_attributes', []) as $attribute) {
			if ($attribute['key'] !== '') {
				$filter_resource_attributes[] = $attribute;
			}
		}

		CProfile::update('web.apm.log.filter_body', $this->getInput('filter_body', ''), PROFILE_TYPE_STR);
		CProfile::update('web.apm.log.filter_traceid', $this->getInput('filter_traceid', ''), PROFILE_TYPE_STR);
		CProfile::update('web.apm.log.filter_spanid', $this->getInput('filter_spanid', ''), PROFILE_TYPE_STR);
		CProfile::update('web.apm.log.filter_service_name', $this->getInput('filter_service_name', ''),
			PROFILE_TYPE_STR
		);
		CProfile::update('web.apm.log.filter_scope_name', $this->getInput('filter_scope_name', ''), PROFILE_TYPE_STR);
		CProfile::updateArray('web.apm.log.filter_severities', $this->getInput('filter_severities', []),
			PROFILE_TYPE_INT
		);
		CProfile::update('web.apm.log.filter_resource_attributes_evaltype',
			$this->getInput('filter_resource_attributes_evaltype', CONDITION_EVAL_TYPE_AND_OR),
			PROFILE_TYPE_INT
		);
		CProfile::updateArray('web.apm.log.filter_resource_attributes.key',
			array_column($filter_resource_attributes, 'key'),
			PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.apm.log.filter_resource_attributes.value',
			array_column($filter_resource_attributes, 'value'),
			PROFILE_TYPE_STR
		);
		CProfile::updateArray('web.apm.log.filter_resource_attributes.operator',
			array_column($filter_resource_attributes, 'operator'),
			PROFILE_TYPE_INT
		);
	}

	private function deleteProfiles(): void {
		CProfile::delete('web.apm.log.filter_body');
		CProfile::delete('web.apm.log.filter_traceid');
		CProfile::delete('web.apm.log.filter_spanid');
		CProfile::delete('web.apm.log.filter_service_name');
		CProfile::delete('web.apm.log.filter_scope_name');
		CProfile::deleteIdx('web.apm.log.filter_severities');
		CProfile::delete('web.apm.log.filter_resource_attributes_evaltype');
		CProfile::deleteIdx('web.apm.log.filter_resource_attributes.key');
		CProfile::deleteIdx('web.apm.log.filter_resource_attributes.value');
		CProfile::deleteIdx('web.apm.log.filter_resource_attributes.operator');
	}
}
