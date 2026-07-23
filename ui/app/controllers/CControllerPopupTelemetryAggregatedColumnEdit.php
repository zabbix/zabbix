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
										APM_SIGNAL_TYPE_TRACES, APM_SIGNAL_TYPE_METRICS, APM_SIGNAL_TYPE_LOGS
									]),
			'metric_point_type' =>	'in '.implode(',', [
										APM_METRICS_POINT_SUM, APM_METRICS_POINT_GAUGE, APM_METRICS_POINT_HISTOGRAM,
										APM_METRICS_POINT_EXPHISTOGRAM
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

	protected function doAction(): void {
		$data = [
			'action' => $this->getAction(),
			'row_index' => $this->getInput('row_index'),
			'signal_type' => (int) $this->getInput('signal_type'),
			'metric_point_type' => (int) $this->getInput('metric_point_type', (string) APM_METRICS_POINT_SUM),
			'column' => $this->getInput('column', ''),
			'function' => (int) $this->getInput('function', (string) AGGREGATE_COUNT),
			'percentile' => $this->getInput('percentile', ''),
			'alias' => $this->getInput('alias', ''),
			'js_validation_rules' => (new CFormValidator(
				CControllerPopupTelemetryAggregatedColumnCheck::getValidationRules()
			))->getRules(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$data['columns'] = CTelemetryData::getAggregatedColumns($data['signal_type'], $data['metric_point_type']);
		$data['functions'] = CTelemetryData::getFunctionLabels();

		$this->setResponse(new CControllerResponseData($data));
	}
}
