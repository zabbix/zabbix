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

		$this->ceprule = self::fetchCepRule($this->hasInput('cepruleid') ? $this->getInput('cepruleid') : null);

		if (!$this->ceprule) {
			return false;
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
		if ($this->hasInput('cepruleid')) {
			$rules = (new CFormValidator(
				CControllerCepRuleGeneral::getValidationRules(existing: true)
			))->getRules();
			$rules_for_clone = (new CFormValidator(
				CControllerCepRuleGeneral::getValidationRules(existing: false)
			))->getRules();
		}
		else {
			$rules = (new CFormValidator(
				CControllerCepRuleGeneral::getValidationRules(existing: false)
			))->getRules();
			$rules_for_clone = $rules;
		}

		$data = [
			'js_validation_rules' => $rules,
			'js_validation_rules_for_clone' => $rules_for_clone,
			'condition_js_validation_rules' => self::getConditionValidationRules(),
			'operation_js_validation_rules' => self::getOperationValidationRules(),
			'ceprule' => $this->ceprule,
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		$response = new CControllerResponseData($data);
		$response->setTitle($this->ceprule['cepruleid'] === null
			? _('New complex event processing')
			: _('Complex event processing')
		);

		$this->setResponse($response);
	}

	protected static function fetchCepRule(?string $cepruleid): array {
		if ($cepruleid !== null) {
			$ceprules = API::CepRule()->get([
				'cep_ruleids' => $cepruleid,
				'output' => ['cep_ruleid', 'name', 'description', 'window_type', 'status', 'stop', 'sortorder'],
				'selectOperations' => ['sortorder', 'execute_when', 'type', 'evaltype', 'event_name', 'tag', 'new_tag',
					'tag_value', 'severity', 'tags'],
				'selectFilter' => ['formula', 'evaltype', 'conditions'],
				'selectWindow' => ['duration', 'capacity', 'script', 'group_by_host_group', 'group_by_host',
					'group_by_tags', 'event_count_tag', 'tags']
			]);
		}
		else {
			$ceprules = [DB::getDefaults('cep_rule') + [
				'filter' => [
					'formula' => DB::getDefault('cep_rule', 'formula'),
					'evaltype' => DB::getDefault('cep_rule', 'evaltype'),
					'conditions' => []
				],
				'window_type' => CCepRuleHelper::WINDOW_NONE,
				'window' => ['tags' => []] + DB::getDefaults('cep_window'),
				'operations' => []
			]];
		}

		if (!$ceprules) {
			return [];
		}

		$ceprule = $ceprules[0];

		$ceprule['window'] += DB::getDefaults('cep_window');


		// Unlimited capacity's default value is "0".
		$ceprule['window']['capacity_enabled'] = $ceprule['window']['capacity'] != 0;
		if (!$ceprule['window']['capacity_enabled']) {
			$ceprule['window']['capacity'] = '10'; // Pre-fill a convenient default upon change into limited setting.
		}

		if ($ceprule['sortorder'] == 0) {
			$ceprule['sortorder'] = '1';
		}

		if ($ceprule['window']['duration'] == 0) {
			$ceprule['window']['duration'] = '10m';
		}

		// Consistent naming with URL and fields.
		$ceprule['cepruleid'] = array_key_exists('cep_ruleid', $ceprule) ? $ceprule['cep_ruleid'] : null;
		unset($ceprule['cep_ruleid']);

		$ceprule['filter']['conditions'] = self::prepareFilterConditions($ceprule['filter']['conditions']);

		return $ceprule;
	}

	protected static function prepareFilterConditions(array $conditions): array {
		$conditions = self::prepareConditionsFormula($conditions);
		$conditions = array_map(function(array $condition): array {
			if ($condition['type'] == CCepRuleHelper::CONDITION_TAG_VALUE) {
				$condition['type'] = CCepRuleHelper::CONDITION_TAG;
			}

			if ($condition['type'] == CCepRuleHelper::CONDITION_TAG) {
				$condition['tag_operator'] = $condition['operator'];
			}

			return $condition;
		}, $conditions);

		return $conditions;
	}

	protected static function prepareConditionsFormula(array $conditions): array {
		$conditions = array_reduce($conditions,
			static fn (array $carry, array $condition) => [$condition['formulaid'] => $condition, ...$carry], []
		);
		ksort($conditions);

		return $conditions;
	}

	protected static function getOperationValidationRules(): array {
		return (new CFormValidator([
			'object', 'fields' => CControllerCepRuleGeneral::getOperationValidationFields()
		]))->getRules();
	}

	protected static function getConditionValidationRules(): array {
		return (new CFormValidator([
			'object', 'fields' => CControllerCepRuleGeneral::getConditionValidationFields()
		]))->getRules();
	}
}
