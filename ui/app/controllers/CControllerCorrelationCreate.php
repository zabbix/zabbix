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


class CControllerCorrelationCreate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['correlation.get', ['name' => '{name}']]
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'name' => ['db correlation.name', 'required', 'not_empty'],
			'description' => ['db correlation.description'],
			'evaltype' => ['db correlation.evaltype', 'required',
				'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR,
					CONDITION_EVAL_TYPE_EXPRESSION
				]
			],
			'status' => ['db correlation.status', 'required',
				'in' => [ZBX_CORRELATION_ENABLED, ZBX_CORRELATION_DISABLED]
			],
			'formula' => ['db correlation.formula', 'required', 'not_empty',
				'use' => [CConditionFormulaParser::class, []],
				'when' => ['evaltype', 'in' => [CONDITION_EVAL_TYPE_EXPRESSION]]
			],
			'operations' => ['array', 'required', 'not_empty',
				'field' => ['boolean'],
				'messages' => ['not_empty' => _('At least one operation must be selected.')]
			],
			'conditions' => ['objects', 'required', 'not_empty',
				'uniq' => ['type', 'operator', 'tag', 'oldtag', 'newtag', 'value', 'groupid'],
				'fields' => [
					'type' => ['db corr_condition.type', 'required',
						'in' => [ZBX_CORR_CONDITION_OLD_EVENT_TAG, ZBX_CORR_CONDITION_NEW_EVENT_TAG,
							ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP, ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
							ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE, ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
						]
					],
					'operator' => [
						['db corr_condition_group.operator', 'required',
							'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL],
							'when' => ['type', 'in' => [ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP]]
						],
						['db corr_condition_tagvalue.operator', 'required',
							'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
								CONDITION_OPERATOR_NOT_LIKE
							],
							'when' => ['type', 'in' => [ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
								ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
							]]
						]
					],
					'tag' => [
						['db corr_condition_tag.tag', 'required', 'not_empty',
							'when' => ['type', 'in' => [ZBX_CORR_CONDITION_OLD_EVENT_TAG,
								ZBX_CORR_CONDITION_NEW_EVENT_TAG
							]]
						],
						['db corr_condition_tagvalue.tag', 'required', 'not_empty',
							'when' => ['type', 'in' => [ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
								ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
							]]
						]
					],
					'oldtag' => ['db corr_condition_tagpair.oldtag', 'required', 'not_empty',
						'when' => ['type', 'in' => [ZBX_CORR_CONDITION_EVENT_TAG_PAIR]]
					],
					'newtag' => ['db corr_condition_tagpair.newtag', 'required', 'not_empty',
						'when' => ['type', 'in' => [ZBX_CORR_CONDITION_EVENT_TAG_PAIR]]
					],
					'value' => [
						['db corr_condition_tagvalue.value', 'required',
							'when' => ['type', 'in' => [ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
								ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
							]]
						],
						['db corr_condition_tagvalue.value', 'required', 'not_empty',
							'when' => [
								['type', 'in' => [ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
									ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
								]],
								['operator', 'in' => [CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE]]
							]
						]
					],
					'groupid' => ['db corr_condition_group.groupid', 'required',
						'when' => ['type', 'in' => [ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP]]
					],
					'formulaid' => ['string']
				]
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
					'title' => _('Cannot create event correlation'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION);
	}

	protected function doAction(): void {
		$correlation = [
			'name' => $this->getInput('name'),
			'description' => $this->getInput('description', ''),
			'status' => $this->getInput('status', ZBX_CORRELATION_DISABLED),
			'filter' => [
				'evaltype' => $this->getInput('evaltype'),
				'conditions' => $this->getInput('conditions', [])
			],
			'operations' => []
		];

		if ($correlation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
			if (count($correlation['filter']['conditions']) > 1) {
				$correlation['filter']['formula'] = $this->getInput('formula', '');
			}
			else {
				$correlation['filter']['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
			}
		}
		else {
			foreach ($correlation['filter']['conditions'] as &$condition) {
				unset($condition['formulaid']);
			}
			unset($condition);
		}

		foreach ($this->getInput('operations') as $operation) {
			$correlation['operations'][] = ['type' => $operation];
		}

		$result = API::Correlation()->create($correlation);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Event correlation created');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot create event correlation'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
