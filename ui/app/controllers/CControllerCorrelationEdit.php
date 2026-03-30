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


class CControllerCorrelationEdit extends CController {

	private array $correlation = [];

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(['object', 'fields' => [
			'correlationid' => ['db correlation.correlationid']
		]]);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION)) {
			return false;
		}

		if ($this->hasInput('correlationid')) {
			$correlations = API::Correlation()->get([
				'output' => ['correlationid', 'name', 'description', 'status'],
				'selectFilter' => ['formula', 'conditions', 'evaltype'],
				'selectOperations' => ['type'],
				'correlationids' => $this->getInput('correlationid'),
				'editable' => true
			]);

			if (!$correlations) {
				return false;
			}

			$this->correlation = $correlations[0];
		}

		return true;
	}

	protected function doAction(): void {
		if (!$this->correlation) {
			$correlation = [
				'correlationid' => null,
				'name' => DB::getDefault('correlation', 'name'),
				'filter' => [
					'evaltype' => DB::getDefault('correlation', 'evaltype'),
					'formula' => DB::getDefault('correlation', 'formula'),
					'conditions' => [],
				],
				'operations' => [],
				'description' => DB::getDefault('correlation', 'description'),
				'status' => DB::getDefault('correlation', 'status')
			];
		}
		else {
			$correlation = [
				'correlationid' => $this->correlation['correlationid'],
				'name' => $this->correlation['name'],
				'filter' => [
					'evaltype' => $this->correlation['filter']['evaltype'],
					'formula' => $this->correlation['filter']['formula'],
					'conditions' =>	$this->prepareConditions($this->correlation)
				],
				'operations' => $this->correlation['operations'],
				'description' => $this->correlation['description'],
				'status' => $this->correlation['status']
			];
		}

		$js_validation_rules = $correlation['correlationid']
			? CControllerCorrelationUpdate::getValidationRules()
			: CControllerCorrelationCreate::getValidationRules();

		$data = [
			'correlation' => $correlation,
			'js_validation_rules' => (new CFormValidator($js_validation_rules))->getRules(),
			'js_clone_validation_rules' => (new CFormValidator(CControllerCorrelationCreate::getValidationRules()))
				->getRules(),
			'user' => ['debug_mode' => $this->getDebugMode()]
		];


		$response = new CControllerResponseData($data);
		$response->setTitle(_('Event correlation rules'));
		$this->setResponse($response);
	}

	protected function prepareConditions(array $correlation): array {
		$result = [];
		$hostgroup_names = $this->fetchHostGroupNames($correlation);

		foreach ($correlation['filter']['conditions'] as $index => $condition) {
			$type = (int) $condition['type'];

			$template_data = [
				'row_index' => $index,
				'type' => $condition['type'],
				'formulaid' => $condition['formulaid']
			];

			$result[] = $template_data + match ($type) {
				ZBX_CORR_CONDITION_OLD_EVENT_TAG,
				ZBX_CORR_CONDITION_NEW_EVENT_TAG => [
					'tag' => $condition['tag']
				],
				ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP => [
					'groupid' => $condition['groupid'],
					'group_name' => $hostgroup_names[$condition['groupid']],
					'operator' => $condition['operator'],
					'operator_name' => CCorrelationHelper::getLabelByOperator($condition['operator'])
				],
				ZBX_CORR_CONDITION_EVENT_TAG_PAIR => [
					'oldtag' => $condition['oldtag'],
					'newtag' => $condition['newtag']
				],
				ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
				ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE => [
					'tag' => $condition['tag'],
					'value' => $condition['value'],
					'operator' => $condition['operator'],
					'operator_name' => CCorrelationHelper::getLabelByOperator($condition['operator'])
				]
			};
		}

		return $result;
	}

	/**
	 * @param array $correlation  API structure.
	 *
	 * @return array<string, string>  Group names keyed by group ID.
	 */
	protected function fetchHostGroupNames(array $correlation): array {
		$groupids = array_column($correlation['filter']['conditions'], 'groupid', 'groupid');
		$group_names = [];

		if ($groupids) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => array_keys($groupids),
				'preservekeys' => true
			]);

			$group_names = array_column($groups, 'name', 'groupid');
		}

		return $group_names;
	}
}
