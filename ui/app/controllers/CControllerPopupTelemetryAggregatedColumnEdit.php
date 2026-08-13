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
			'function' =>			'in '.implode(',', [
										AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM,
										AGGREGATE_PCTILE
									]),
			'percentile' =>			'string',
			'alias' =>				'string'
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
			'percentile' => ['float', 'required', 'not_empty', 'min' => 0, 'max' => 100,
				'when' => ['function', 'in' => [AGGREGATE_PCTILE]]
			],
			'alias' => ['string', 'required', 'not_empty']
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
			'function' => (int) $this->getInput('function', (string) AGGREGATE_COUNT),
			'percentile' => $this->getInput('percentile', ''),
			'alias' => $this->getInput('alias', ''),
			'js_validation_rules' => (new CFormValidator(self::getValidationRules()))->getRules(),
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
