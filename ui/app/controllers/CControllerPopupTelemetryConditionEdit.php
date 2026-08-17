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


class CControllerPopupTelemetryConditionEdit extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	private static function getValidationRules(): array {
		return ['object', 'fields' => [
			'row_index' => ['integer', 'required'],
			'signal_type' => ['integer', 'required',
				'in' => [
					CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					CItemTypeTelemetryQuery::SIGNAL_TYPE_LOGS
				]
			],
			'metric_point_type' => ['integer',
				'in' => [
					CItemTypeTelemetryQuery::METRICS_POINT_SUM,
					CItemTypeTelemetryQuery::METRICS_POINT_GAUGE,
					CItemTypeTelemetryQuery::METRICS_POINT_HISTOGRAM,
					CItemTypeTelemetryQuery::METRICS_POINT_EXPHISTOGRAM
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
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($response, JSON_THROW_ON_ERROR)]))
					->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			|| $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	private static function getFormValidationRules(): array {
		$complex_columns = CItemTypeTelemetryQuery::COMPLEX_COLUMN_NAME;

		return ['object', 'fields' => [
			'row_index' => ['integer', 'required'],
			'signal_type' => ['integer', 'required',
				'in' => [
					CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					CItemTypeTelemetryQuery::SIGNAL_TYPE_LOGS
				]
			],
			'metric_point_type' => ['integer', 'required',
				'in' => [
					CItemTypeTelemetryQuery::METRICS_POINT_SUM,
					CItemTypeTelemetryQuery::METRICS_POINT_GAUGE,
					CItemTypeTelemetryQuery::METRICS_POINT_HISTOGRAM,
					CItemTypeTelemetryQuery::METRICS_POINT_EXPHISTOGRAM
				]
			],
			'column' => ['string', 'required', 'not_empty'],
			'attribute_key' => ['string', 'required', 'not_empty',
				'when' => ['column', 'in' => $complex_columns]
			],
			'operator' => [
				['integer', 'required',
					'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_EXISTS],
					'when' => ['column', 'in' => $complex_columns]
				],
				['integer', 'required',
					'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE
					],
					'when' => ['column', 'not_in' => $complex_columns]
				]
			],
			'value' => ['string', 'required', 'not_empty',
				'when' => ['operator', 'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL,
					CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE
				]]
			]
		]];
	}

	protected function doAction(): void {
		$data = [
			'action' => $this->getAction(),
			'row_index' => $this->getInput('row_index'),
			'signal_type' => (int) $this->getInput('signal_type'),
			'metric_point_type' => (int) $this->getInput('metric_point_type',
				(string) CItemTypeTelemetryQuery::METRICS_POINT_SUM
			),
			'column' => '',
			'attribute_key' => '',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => '',
			'js_validation_rules' => (new CFormValidator(self::getFormValidationRules()))->getRules(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$data['columns'] = CTelemetryHelper::getConditionColumnOptions($data['signal_type'],
			$data['metric_point_type']
		);
		$data['operators'] = CTelemetryHelper::getOperatorLabels();
		$data['complex_columns'] = CItemTypeTelemetryQuery::COMPLEX_COLUMN_NAME;
		$data['condition_operators'] = CTelemetryHelper::getConditionOperators();

		$this->setResponse(new CControllerResponseData($data));
	}
}
