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
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_APM_LOGS);
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_body' =>			'string',
			'filter_traceid' =>			'string',
			'filter_spanid' =>			'string',
			'filter_service_name' =>	'string',
			'filter_scope_name' =>		'string',
			'filter_severities' =>		'array',
			'filter_evaltype' =>		'in '.APM_ATTR_EVAL_TYPE_AND_OR.','.APM_ATTR_EVAL_TYPE_OR,
			'filter_attributes' =>		'array',
			'from' =>					'range_time',
			'to' =>						'range_time',
			'sort' =>					'in timestamp',
			'sortorder' =>				'in '.ZBX_SORT_DOWN,
			'page' =>					'ge 1',
			'filter_name' =>			'string',
			'filter_custom_time' =>		'in 1,0',
			'filter_set' =>				'in 1',
			'filter_rst' =>				'in 1'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod() && $this->validateSeverities()
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

		$sort_field = $this->getInput('sort', 'timestamp');
		$sort_order = $this->getInput('sortorder', ZBX_SORT_DOWN);

		$storage_idx = 'web.apm.trace.list.datatable';

		$timeselector_options = [
			'profileIdx' => 'web.apm.log.list.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];
		updateTimeSelectorPeriod($timeselector_options);

		$filter_attributes = [];

		foreach (CProfile::getArray('web.apm.log.list.filter_attributes.key', []) as $i => $attribute) {
			$filter_attributes[] = [
				'key' => $attribute,
				'value' => CProfile::get('web.apm.log.list.filter_attributes.value', null, $i),
				'operator' => CProfile::get('web.apm.log.list.filter_attributes.operator', null, $i)
			];
		}

		$filter = [
			'body' => CProfile::get('web.apm.log.list.filter_body', ''),
			'traceid' => CProfile::get('web.apm.log.list.filter_traceid', ''),
			'spanid' => CProfile::get('web.apm.log.list.filter_spanid', ''),
			'service_name' => CProfile::get('web.apm.log.list.filter_service_name', ''),
			'scope_name' => CProfile::get('web.apm.log.list.filter_scope_name', ''),
			'severities' =>	CProfile::getArray('web.apm.log.list.filter_severities', []),
			'evaltype' => CProfile::get('web.apm.log.list.filter_evaltype', APM_ATTR_EVAL_TYPE_AND_OR),
			'attributes' => $filter_attributes
		];

		$data = [
			'action' => $this->getAction(),
			'default_sort_field' => 'timestamp',
			'default_sort_order' => ZBX_SORT_DOWN,
			'filter' => $filter,
			'filter_options' => [
				'idx' => 'web.apm.log.list.filter',
				'timeselector' => getTimeSelectorPeriod($timeselector_options)
			],
			'active_tab' => CProfile::get('web.apm.log.list.filter.active', 2),
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
		$filter_attributes = [];

		foreach ($this->getInput('filter_attributes', []) as $filter_attribute) {
			if ($filter_attribute['key'] !== '' || $filter_attribute['value'] !== '') {
				$filter_attributes[] = $filter_attribute;
			}
		}

		CProfile::update('web.apm.log.list.filter_body', $this->getInput('filter_body', ''), PROFILE_TYPE_STR);
		CProfile::update('web.apm.log.list.filter_traceid', $this->getInput('filter_traceid', ''), PROFILE_TYPE_STR);
		CProfile::update('web.apm.log.list.filter_spanid', $this->getInput('filter_spanid', ''), PROFILE_TYPE_STR);
		CProfile::update('web.apm.log.list.filter_service_name', $this->getInput('filter_service_name', ''),
			PROFILE_TYPE_STR);
		CProfile::update('web.apm.log.list.filter_scope_name', $this->getInput('filter_scope_name', ''), PROFILE_TYPE_STR);
		CProfile::updateArray('web.apm.log.list.filter_severities', $this->getInput('filter_severities', []),
			PROFILE_TYPE_INT);
		CProfile::update('web.apm.log.list.filter_evaltype', $this->getInput('filter_evaltype', APM_ATTR_EVAL_TYPE_AND_OR),
			PROFILE_TYPE_INT);
		CProfile::updateArray('web.apm.log.list.filter_attributes.key', array_column($filter_attributes, 'key'),
			PROFILE_TYPE_STR);
		CProfile::updateArray('web.apm.log.list.filter_attributes.value', array_column($filter_attributes, 'value'),
			PROFILE_TYPE_STR);
		CProfile::updateArray('web.apm.log.list.filter_attributes.operator', array_column($filter_attributes, 'operator'),
			PROFILE_TYPE_INT);
	}

	private function deleteProfiles(): void {
		CProfile::delete('web.apm.log.list.filter_body');
		CProfile::delete('web.apm.log.list.filter_traceid');
		CProfile::delete('web.apm.log.list.filter_spanid');
		CProfile::delete('web.apm.log.list.filter_service_name');
		CProfile::delete('web.apm.log.list.filter_scope_name');
		CProfile::deleteIdx('web.apm.log.list.filter_severities');
		CProfile::delete('web.apm.log.list.filter_evaltype');
		CProfile::deleteIdx('web.apm.log.list.filter_attributes.key');
		CProfile::deleteIdx('web.apm.log.list.filter_attributes.value');
		CProfile::deleteIdx('web.apm.log.list.filter_attributes.operator');
	}

	/**
	 * Validate values of filter severities.
	 *
	 * @return bool
	 */
	private function validateSeverities(): bool {
		if (!$this->hasInput('filter_severities')) {
			return true;
		}

		return !array_diff($this->getInput('filter_severities'), [TRIGGER_SEVERITY_NOT_CLASSIFIED,
			TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_WARNING, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH,
			TRIGGER_SEVERITY_DISASTER]);
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
