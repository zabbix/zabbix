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


class CControllerPopupTelemetryAggregatedColumnEdit extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	private static function getValidationRules(): array {
		return ['object', 'fields' => [
			'edit' => ['integer', 'in' => [1]],
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
			'existing_aliases' => ['array', 'field' => ['string']],
			'function' => ['integer', 'required',
				'in' => [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM,
					AGGREGATE_PCTILE
				],
				'when' => ['edit', 'in' => [1]]
			],
			'column' => ['string', 'required', 'when' => ['edit', 'in' => [1]]],
			'percentile' => ['string', 'required', 'when' => ['edit', 'in' => [1]]],
			'alias' => ['string', 'required', 'when' => ['edit', 'in' => [1]]]
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

	private static function getFormValidationRules(array $existing_aliases): array {
		$alias_rules = ['string', 'required', 'not_empty'];

		if ($existing_aliases) {
			$alias_rules['not_in'] = $existing_aliases;
			$alias_rules['messages'] = ['not_in' => _('Alias is not unique.')];
		}

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
			'function' => ['integer', 'required',
				'in' => [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM,
					AGGREGATE_PCTILE
				]
			],
			'column' => CTelemetryHelper::getColumnValidationRules(CTelemetryHelper::SECTION_AGGREGATED_COLUMNS),
			'percentile' => ['float', 'required', 'not_empty', 'min' => 0, 'max' => 100, 'decimal_limit' => 4,
				'when' => ['function', 'in' => [AGGREGATE_PCTILE]]
			],
			'alias' => $alias_rules
		]];
	}

	protected function doAction(): void {
		$data = [
			'action' => $this->getAction(),
			'row_index' => $this->getInput('row_index'),
			'signal_type' => $this->getInput('signal_type'),
			'metric_point_type' => $this->getInput('metric_point_type'),
			'column' => $this->getInput('column', ''),
			'function' => $this->getInput('function', AGGREGATE_COUNT),
			'percentile' => $this->getInput('percentile', ''),
			'alias' => $this->getInput('alias', ''),
			'js_validation_rules' => (new CFormValidator(
				self::getFormValidationRules($this->getInput('existing_aliases', []))
			))->getRules(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$data['columns'] = CTelemetryHelper::getAggregatedColumnOptions($data['signal_type'],
			$data['metric_point_type']
		);

		if ($data['column'] !== '' && !array_key_exists($data['column'], $data['columns'])) {
			$data['columns'][$data['column']] = $data['column'];
		}

		$data['functions'] = CTelemetryHelper::getFunctionLabels();

		$this->setResponse(new CControllerResponseData($data));
	}
}
