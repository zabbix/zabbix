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
			'filter_statuses' => ['array', 'field' => ['integer']],
			'filter_evaltype' => ['integer', 'in' => [APM_ATTR_EVAL_TYPE_AND_OR, APM_ATTR_EVAL_TYPE_OR]],
			'filter_attributes' => ['array'],
			'from' => ['string', 'use' => [CRangeTimeValidator::class]],
			'to' => ['string', 'use' => [CRangeTimeValidator::class]],
			'sort' => ['string', 'in' => ['timestamp']],
			'sortorder' => ['string', 'in' => [ZBX_SORT_DOWN, ZBX_SORT_UP]],
			'page' => ['integer', 'min' => 1],
			'filter_custom_time' => ['integer', 'in' => [0, 1]],
			'filter_set' => ['integer', 'in' => ['1']],
			'filter_rst' => ['integer', 'in' => ['1']]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules())
			&& $this->validateTimeSelectorPeriod()
			&& $this->validateStatuses()
			&& $this->validateAttributes();

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

		$filter_attributes = [];

		foreach (CProfile::getArray('web.apm.trace.filter_attributes.key', []) as $i => $attribute) {
			$filter_attributes[] = [
				'key' => $attribute,
				'value' => CProfile::get('web.apm.trace.filter_attributes.value', null, $i),
				'operator' => CProfile::get('web.apm.trace.filter_attributes.operator', null, $i)
			];
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
			'evaltype' => CProfile::get('web.apm.trace.filter_evaltype', APM_ATTR_EVAL_TYPE_AND_OR),
			'attributes' => $filter_attributes
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
				CProfile::getArray($storage_idx, []))
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Traces'));
		$this->setResponse($response);
	}

	private function updateProfiles(): void {
		$filter_attributes = [];

		foreach ($this->getInput('filter_attributes', []) as $filter_attribute) {
			if ($filter_attribute['key'] !== '' || $filter_attribute['value'] !== '') {
				$filter_attributes[] = $filter_attribute;
			}
		}

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
		CProfile::update('web.apm.trace.filter_evaltype', $this->getInput('filter_evaltype', APM_ATTR_EVAL_TYPE_AND_OR),
			PROFILE_TYPE_INT);
		CProfile::updateArray('web.apm.trace.filter_attributes.key', array_column($filter_attributes, 'key'),
			PROFILE_TYPE_STR);
		CProfile::updateArray('web.apm.trace.filter_attributes.value', array_column($filter_attributes, 'value'),
			PROFILE_TYPE_STR);
		CProfile::updateArray('web.apm.trace.filter_attributes.operator', array_column($filter_attributes, 'operator'),
			PROFILE_TYPE_INT);
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
		CProfile::delete('web.apm.trace.filter_evaltype');
		CProfile::deleteIdx('web.apm.trace.filter_attributes.key');
		CProfile::deleteIdx('web.apm.trace.filter_attributes.value');
		CProfile::deleteIdx('web.apm.trace.filter_attributes.operator');
	}

	/**
	 * Validate values of filter statuses.
	 *
	 * @return bool
	 */
	private function validateStatuses(): bool {
		if (!$this->hasInput('filter_statuses')) {
			return true;
		}

		return !array_diff($this->getInput('filter_statuses'), [APM_TRACE_STATUS_OK, APM_TRACE_STATUS_ERROR,
			APM_TRACE_STATUS_UNSET]);
	}

	/**
	 * Validate values of filter attributes.
	 *
	 * @return bool
	 */
	private function validateAttributes(): bool {
		if (!$this->hasInput('filter_attributes')) {
			return true;
		}

		$ret = true;
		foreach ($this->getInput('filter_attributes') as $filter_attribute) {
			if (count($filter_attribute) != 3
					|| !array_key_exists('key', $filter_attribute) || !is_string($filter_attribute['key'])
					|| !array_key_exists('value', $filter_attribute) || !is_string($filter_attribute['value'])
					|| !array_key_exists('operator', $filter_attribute) || !is_string($filter_attribute['operator'])) {
				$ret = false;
				break;
			}
		}

		return $ret;
	}
}
