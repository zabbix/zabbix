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
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'signal_type' =>		'required|in '.implode(',', [
										CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
										CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
										CItemTypeTelemetryQuery::SIGNAL_TYPE_LOGS
									]),
			'metric_point_type' =>	'in '.implode(',', [
										CItemTypeTelemetryQuery::METRICS_POINT_SUM,
										CItemTypeTelemetryQuery::METRICS_POINT_GAUGE,
										CItemTypeTelemetryQuery::METRICS_POINT_HISTOGRAM,
										CItemTypeTelemetryQuery::METRICS_POINT_EXPHISTOGRAM
									]),
			'row_index' =>			'required|int32',
			'column' =>				'string',
			'attribute_key' =>		'string',
			'operator' =>			'in '.implode(',', [
										CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
										CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS
									]),
			'value' =>				'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			|| $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	private static function getValidationRules(): array {
		$complex_columns = CItemTypeTelemetryQuery::COMPLEX_COLUMN_NAME;
		$operators = CTelemetryHelper::getConditionOperators();

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
				['integer', 'required', 'in' => $operators['complex'], 'when' => ['column', 'in' => $complex_columns]],
				['integer', 'required', 'in' => $operators['simple'], 'when' => ['column', 'not_in' => $complex_columns]]
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
			'column' => $this->getInput('column', ''),
			'attribute_key' => $this->getInput('attribute_key', ''),
			'operator' => (int) $this->getInput('operator', (string) CONDITION_OPERATOR_EQUAL),
			'value' => $this->getInput('value', ''),
			'js_validation_rules' => (new CFormValidator(self::getValidationRules()))->getRules(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$data['columns'] = CTelemetryHelper::getConditionColumnOptions($data['signal_type'],
			$data['metric_point_type']
		);
		$data['operators'] = CTelemetryHelper::getOperatorLabels();

		$this->setResponse(new CControllerResponseData($data));
	}
}
