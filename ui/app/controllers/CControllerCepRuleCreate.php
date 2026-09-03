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


class CControllerCepRuleCreate extends CControllerCepRuleGeneral {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_CEPRULES);
	}

	public static function getValidationRules(): array {
		$api_uniq = ['ceprule.get', ['name' => '{name}']];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'name' => ['db cep_rule.name', 'required', 'not_empty'],
			'filter' => ['object', 'fields' => [
				'evaltype' => ['db cep_rule.evaltype', 'required',
					'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR,
						CONDITION_EVAL_TYPE_EXPRESSION
					]
				],
				'conditions' => ['objects', 'fields' => [
					'type' => ['integer', 'required', 'in' => [
						CCepRuleHelper::CONDITION_EVENT_NAME, CCepRuleHelper::CONDITION_TAG,
						CCepRuleHelper::CONDITION_TAG_VALUE, CCepRuleHelper::CONDITION_SEVERITY,
						CCepRuleHelper::CONDITION_HOST, CCepRuleHelper::CONDITION_HOST_GROUP,
						CCepRuleHelper::CONDITION_TIME_PERIOD
					]],
					'operator' => [
						[
							'integer', 'required', 'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL,
								CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE
							],
							'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_EVENT_NAME,
								CCepRuleHelper::CONDITION_HOST, CCepRuleHelper::CONDITION_HOST_GROUP
							]]
						],
						[
							'integer', 'required', 'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL,
								CONDITION_OPERATOR_LESS_EQUAL, CONDITION_OPERATOR_MORE_EQUAL
							],
							'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_SEVERITY]]
						],
						[
							'integer', 'required',
							'in' => [CONDITION_OPERATOR_IN, CONDITION_OPERATOR_NOT_IN],
							'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TIME_PERIOD]]
						],
						[
							'integer', 'required',
							'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
								CONDITION_OPERATOR_NOT_LIKE],
							'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG]]
						],
						[
							'integer', 'required',
							'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
								CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_MORE_EQUAL,
								CONDITION_OPERATOR_LESS_EQUAL],
							'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG_VALUE]]
						]
					],
					'event_name' => ['db cep_condition.event_name', 'required', 'not_empty',
						'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_EVENT_NAME]]
					],
					'tag' => ['db cep_condition.tag', 'required', 'not_empty',
						'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG]]
					],
					'tag_name' => ['db cep_condition.tag', 'required', 'not_empty',
						'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG_VALUE]]
					],
					'tag_value' => [
						['db cep_condition.tag_value', 'required',
							'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG_VALUE]]
						],
						['db cep_condition.tag_value', 'required', 'not_empty',
							'when' => [
								['type', 'in' => [CCepRuleHelper::CONDITION_TAG_VALUE]],
								['operator', 'in' => [CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE,
									CONDITION_OPERATOR_MORE_EQUAL, CONDITION_OPERATOR_LESS_EQUAL
								]]
							]
						]
					],
					'host' => ['db cep_condition.host', 'required', 'not_empty',
						'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_HOST]]
					],
					'host_group' => ['db cep_condition.host_group', 'required', 'not_empty',
						'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_HOST_GROUP]]
					],
					'severity' => ['db cep_condition.severity', 'required',
						'in' => [TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION,
							TRIGGER_SEVERITY_WARNING, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH,
							TRIGGER_SEVERITY_DISASTER
						],
						'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_SEVERITY]]
					],
					'time_period' => ['db cep_condition.time_period', 'required', 'not_empty',
						'use' => [CTimePeriodParser::class, ['usermacros' => false, 'lldmacros' => false]],
						'messages' => ['use' => _('Invalid period.')],
						'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TIME_PERIOD]]
					],
					'formulaid' => ['string', 'required', 'not_empty',
						'when' => ['../evaltype', 'in' => [CONDITION_EVAL_TYPE_EXPRESSION]]
					]
				]],
				'formula' => ['db cep_rule.formula', 'required', 'not_empty',
					'use' => [CConditionFormulaParser::class, []],
					'when' => ['evaltype', 'in' => [CONDITION_EVAL_TYPE_EXPRESSION]]
				]
			]],
			'window_type' => ['db cep_rule_window.type', 'required', 'in' => [CCepRuleHelper::WINDOW_NONE,
				CCepRuleHelper::WINDOW_SIMPLE, CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, CCepRuleHelper::WINDOW_TAG_MATCH,
				CCepRuleHelper::WINDOW_PATTERN_MATCH
			]],
			'window' => ['object', 'required',
				'fields' => [
					'duration' => ['db cep_rule_window.duration', 'required', 'not_empty',
						'use' => [CTimeUnitValidator::class, ['max' => SEC_PER_YEAR, 'min' => 1, 'usermacros' => true,
							'lldmacros' => false, 'accept_zero' => false, 'with_year' => false
						]]
					],
					'capacity_enabled' => ['boolean', 'required'],
					'capacity' => ['db cep_rule_window.capacity', 'required', 'not_empty',
						'use' => [CNumberValidator::class, ['min' => 1, 'max' => ZBX_MAX_INT64, 'with_float' => false,
							'usermacros' => true, 'lldmacros' => false
						]],
						'when' => ['capacity_enabled', 'in' => [1]]
					],
					'group_by_host_group' => ['integer',
						'in' => [CCepRuleHelper::GROUP_BY_YES, CCepRuleHelper::GROUP_BY_NO]
					],
					'group_by_host' => ['integer',
						'in' => [CCepRuleHelper::GROUP_BY_YES, CCepRuleHelper::GROUP_BY_NO]
					],
					'group_by_tags' => ['integer', 'in' => [CCepRuleHelper::GROUP_BY_YES, CCepRuleHelper::GROUP_BY_NO]],
					'tags' => ['array', 'required', 'not_empty',
						'field' => ['db cep_rule_window.tags', 'not_empty'],
						'when' => ['group_by_tags', 'in' => [CCepRuleHelper::GROUP_BY_YES]]
					],
					'event_count_tag_enabled' => ['integer', 'required', 'in' => [0, 1],
						'when' => ['../window_type', 'in' => [CCepRuleHelper::WINDOW_CAUSE_SYMPTOM]]
					],
					'event_count_tag' => ['db cep_rule_window.event_count_tag', 'required', 'not_empty',
						'when' => ['event_count_tag_enabled', 'in' => [1]]
					],
					'script' => ['db cep_rule_window.script', 'required', 'not_empty',
						'when' => ['../window_type', 'in' => [CCepRuleHelper::WINDOW_PATTERN_MATCH]]
					]
				],
				'when' => ['window_type', 'not_in' => [CCepRuleHelper::WINDOW_NONE]]
			],
			'operations' => [
				['objects', 'required', 'fields' => self::getOperationValidationFields(),
					'when' => ['window_type', 'in' => [CCepRuleHelper::WINDOW_CAUSE_SYMPTOM]]
				],
				['objects', 'required', 'not_empty', 'fields' => self::getOperationValidationFields(),
					'when' => ['window_type', 'in' => [CCepRuleHelper::WINDOW_NONE, CCepRuleHelper::WINDOW_SIMPLE,
						CCepRuleHelper::WINDOW_TAG_MATCH
					]
				]],
				['objects', 'required', 'not_empty', 'fields' => self::getOperationValidationFields(),
					'when' => ['window_type', 'in' => [CCepRuleHelper::WINDOW_PATTERN_MATCH]],
					'count_values' => [
						'field_rules' => ['execute_when', 'in' => [CCepRuleHelper::WHEN_PATTERN_MATCHED]],
						'min' => 1,
						'message' => _('At least one operation must execute when pattern matched.')
					]
				]
			],
			'stop' => ['db cep_rule.stop', 'required', 'in' => [CCepRuleHelper::EXECUTION_CONTINUE,
				CCepRuleHelper::EXECUTION_STOP
			]],
			'sortorder' => ['db cep_rule.sortorder', 'required', 'min' => ZBX_MIN_INT32, 'max' => ZBX_MAX_INT32],
			'description' => ['db cep_rule.description'],
			'status' => ['db cep_rule.status', 'required',
				'in' => [CCepRuleHelper::STATUS_ENABLED, CCepRuleHelper::STATUS_DISABLED]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add complex event processing rule'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function doAction() {
		$result = API::CepRule()->create($this->prepareApiRequest());
		$output = [];

		if ($result) {
			$output['success']['title'] = _('Complex event processing rule added');
			$output['success']['redirect'] = (new CUrl('zabbix.php'))
				->setArgument('action', 'ceprule.list')
				->setArgument('page', CPagerHelper::loadPage('ceprule.list', null))
				->getUrl();

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add complex event processing rule'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
