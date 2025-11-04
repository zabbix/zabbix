<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class CControllerServiceCreate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'name' => ['db services.name', 'required', 'not_empty'],
			'parent_serviceids' => ['array', 'field' => ['db services.serviceid']],
			'problem_tags' => ['objects', 'uniq' => ['tag', 'value'],
				'messages' => ['uniq' => _('Tag name and value combination is not unique.')],
				'fields' => [
					'value' => ['db service_problem_tag.value'],
					'operator' => ['db service_problem_tag.operator', 'in' => [
						ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL, ZBX_SERVICE_PROBLEM_TAG_OPERATOR_LIKE
					]],
					'tag' => ['db service_problem_tag.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
				]
			],
			'sortorder' => ['db services.sortorder', 'required', 'min' => 0, 'max' => 999],
			'algorithm' => ['db services.algorithm', 'in' => [
				ZBX_SERVICE_STATUS_CALC_SET_OK, ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL,
				ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE
			]],
			'description' => ['db services.description'],
			'status_rules' => ['objects', 'uniq' => ['type', 'limit_value', 'limit_status'],
				'messages' => ['uniq' => _('Condition, limit and status combination is not unique.')],
				'fields' => [
					'type' => ['db service_status_rule.type', 'required',
						'in' => array_keys(CServiceHelper::getStatusRuleTypeOptions())
					],
					'limit_value' => [
						['db service_status_rule.limit_value', 'required', 'min' => 1, 'max' => 1000000,
							'when' => ['type', 'in' => [
								ZBX_SERVICE_STATUS_RULE_TYPE_N_GE, ZBX_SERVICE_STATUS_RULE_TYPE_N_L,
								ZBX_SERVICE_STATUS_RULE_TYPE_W_GE, ZBX_SERVICE_STATUS_RULE_TYPE_W_L
							]]
						],
						['db service_status_rule.limit_value', 'required', 'min' => 1, 'max' => 100,
							'when' => ['type', 'in' => [
								ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_NP_L,
								ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_WP_L
							]]
						]
					],
					'limit_status' => ['db service_status_rule.limit_status',
						'in' => array_keys(CServiceHelper::getStatusNames())
					],
					'new_status' => ['db service_status_rule.new_status', 'required',
						'in' => array_keys(CServiceHelper::getProblemStatusNames())
					]
				]
			],
			'propagation_rule' => ['db services.propagation_rule', 'required',
				'in' => array_keys(CServiceHelper::getStatusPropagationNames())
			],
			'propagation_value_number' => ['integer', 'required', 'in 1:'.(TRIGGER_SEVERITY_COUNT - 1),
				'when' => ['propagation_rule', 'in' => [
					ZBX_SERVICE_STATUS_PROPAGATION_INCREASE, ZBX_SERVICE_STATUS_PROPAGATION_DECREASE
				]]
			],
			'propagation_value_status' => ['integer', 'required', 'in' => array_keys(CServiceHelper::getStatusNames()),
				'when' => ['propagation_rule', 'in' => [ZBX_SERVICE_STATUS_PROPAGATION_FIXED]]
			],
			'weight' => ['db services.weight', 'required', 'min' => 0, 'max' => 1000000],
			'tags' => ['objects', 'uniq' => ['tag', 'value'],
				'messages' => ['uniq' => _('Tag name and value combination is not unique.')],
				'fields' => [
					'value' => ['db service_tag.value'],
					'tag' => ['db service_tag.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
				]
			],
			'child_serviceids' => ['array', 'field' => ['db services.serviceid']]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();

			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot create service'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response, JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SERVICES);
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$service = [
			'tags' => [],
			'problem_tags' => [],
			'parents' => [],
			'children' => [],
			'status_rules' => []
		];

		$fields = ['name', 'algorithm', 'sortorder', 'description', 'status_rules', 'propagation_rule', 'weight'];

		$this->getInputs($service, $fields);

		switch ($service['propagation_rule']) {
			case ZBX_SERVICE_STATUS_PROPAGATION_INCREASE:
			case ZBX_SERVICE_STATUS_PROPAGATION_DECREASE:
				$service['propagation_value'] = $this->getInput('propagation_value_number', 0);
				break;

			case ZBX_SERVICE_STATUS_PROPAGATION_FIXED:
				$service['propagation_value'] = $this->getInput('propagation_value_status', 0);
				break;

			default:
				$service['propagation_value'] = 0;
				break;
		}

		foreach ($this->getInput('tags', []) as $tag) {
			if ($tag['tag'] === '' && $tag['value'] === '') {
				continue;
			}

			$service['tags'][] = $tag;
		}

		foreach ($this->getInput('problem_tags', []) as $problem_tag) {
			if ($problem_tag['tag'] === '' && $problem_tag['value'] === '') {
				continue;
			}

			$service['problem_tags'][] = $problem_tag;
		}

		foreach ($this->getInput('parent_serviceids', []) as $serviceid) {
			$service['parents'][] = ['serviceid' => $serviceid];
		}

		foreach ($this->getInput('child_serviceids', []) as $serviceid) {
			$service['children'][] = ['serviceid' => $serviceid];
		}

		$result = API::Service()->create($service);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Service created');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot create service'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
