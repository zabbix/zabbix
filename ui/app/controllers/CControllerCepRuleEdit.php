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


class CControllerCepRuleEdit extends CController {

	private array $ceprule = [];

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_CEPRULES)) {
			return false;
		}

		if ($this->hasInput('cepruleid')) {
			$this->ceprule = self::fetchCepRule($this->getInput('cepruleid'));

			if (!$this->ceprule) {
				return false;
			}
		}

		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}

	protected function checkInput(): bool {
		$fields = [
			'cepruleid' => 'db cep_rule.cep_ruleid'
		];

		$ret = $this->validateInput($fields);

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

	protected function doAction(): void {
		$js_validation_rules = $this->hasInput('cepruleid')
			? CControllerCepRuleUpdate::getValidationRules()
			: CControllerCepRuleCreate::getValidationRules();

		$data = [
			'js_validation_rules' => (new CFormValidator($js_validation_rules))->getRules(),
			'condition_js_validation_rules' => self::getConditionValidationRules(),
			'window_condition_js_validation_rules' => self::getWindowConditionValidationRules(),
			'operation_js_validation_rules' => self::getOperationValidationRules(),
			'ceprule' => $this->ceprule,
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		$response = new CControllerResponseData($data);
		$response->setTitle($this->ceprule['cep_ruleid'] === null
			? _('New complex event processing')
			: _('Complex event processing')
		);

		$this->setResponse($response);
	}

	protected static function fetchCepRule(string $cepruleid): array {
		$ceprules = API::CepRule()->get([
			'cep_ruleids' => $cepruleid,
			'output' => ['cep_ruleid', 'name', 'description', 'window_type', 'status', 'stop', 'sortorder'],
			'selectOperations' => ['step', 'execute_when', 'event_type', 'eviction_cause', 'type', 'evaltype',
				'event_name', 'tag', 'new_tag', 'tag_value', 'severity', 'tags'],
			'selectFilter' => ['formula', 'evaltype', 'conditions'],
			'selectWindow' => ['duration', 'capacity', 'script', 'group_by_host_group', 'group_by_host', 'group_by_tag',
				'filter', 'event_count_tag', 'tag']
		]);

		if (!$ceprules) {
			return [];
		}

		$ceprule = $ceprules[0];

		// Unlimited capacity's default value is "0".
		if ($ceprule['window']['capacity'] == 0) {
			$ceprule['window']['capacity'] = '';
		}

		return $ceprule;
	}

	protected static function getWindowConditionValidationRules(): array {
		$rules = CControllerCepRuleUpdate::getValidationRules();

		$condition_fields = $rules
			['fields']['window']
			['fields']['filter']
			['fields']['conditions']
			['fields'];

		return (new CFormValidator([
			'object', 'fields' => $condition_fields
		]))->getRules();
	}

	protected static function getConditionValidationRules(): array {
		$rules = CControllerCepRuleUpdate::getValidationRules();

		$condition_fields = $rules['fields']['filter']['fields']['conditions']['fields'];

		return (new CFormValidator([
			'object', 'fields' => $condition_fields
		]))->getRules();
	}

	protected static function getOperationValidationRules(): array {
		$rules = CControllerCepRuleUpdate::getValidationRules();

		$operation_fields = $rules['fields']['operations']['fields'];

		return (new CFormValidator([
			'object', 'fields' => $operation_fields
		]))->getRules();
	}
}
