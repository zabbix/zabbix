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


class CControllerPopupTelemetryConditionCheck extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$complex_columns = CTelemetryData::getComplexColumns();
		$operators = CTelemetryData::getConditionOperators();

		return ['object', 'fields' => [
			'row_index' => ['integer', 'required'],
			'signal_type' => ['integer', 'required',
				'in' => [APM_SIGNAL_TYPE_TRACES, APM_SIGNAL_TYPE_METRICS, APM_SIGNAL_TYPE_LOGS]
			],
			'metric_point_type' => ['integer',
				'in' => [APM_METRICS_POINT_SUM, APM_METRICS_POINT_GAUGE, APM_METRICS_POINT_HISTOGRAM,
					APM_METRICS_POINT_EXPHISTOGRAM
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

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add condition'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			|| $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		$complex_columns = CTelemetryData::getComplexColumns();

		$column = $this->getInput('column', '');
		$operator = (int) $this->getInput('operator');
		$is_complex = in_array($column, $complex_columns, true);

		$this->setResponse(
			(new CControllerResponseData(['main_block' => json_encode([
				'row_index' => $this->getInput('row_index'),
				'column' => $column,
				'attribute_key' => $is_complex ? $this->getInput('attribute_key', '') : '',
				'operator' => $operator,
				'value' => $operator == CONDITION_OPERATOR_EXISTS ? '' : $this->getInput('value', '')
			], JSON_THROW_ON_ERROR)]))->disableView()
		);
	}
}
