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

	/**
	 * @var mixed
	 */
	private $correlation = [];

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
		else {
			$this->correlation = [
				'correlationid' => null, 'operations' => [],
				'filter' => ['formula' => '', 'conditions' => [], 'evaltype' => CONDITION_EVAL_TYPE_AND_OR]
			];
		}

		return true;
	}

	protected function doAction(): void {
		$correlation = $this->correlation + DB::getDefaults('correlation');

		$js_validation_rules = $correlation['correlationid']
			? CControllerCorrelationUpdate::getValidationRules()
			: CControllerCorrelationCreate::getValidationRules();

		$data = [
			'correlation' => $correlation,
			'hostgroup_names' => $this->fetchHostGroupNames($correlation),
			'js_validation_rules' => (new CFormValidator($js_validation_rules))->getRules(),
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Event correlation rules'));
		$this->setResponse($response);
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
