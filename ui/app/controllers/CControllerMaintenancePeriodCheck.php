<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CControllerMaintenancePeriodCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableSIDvalidation();
	}

	/**
	 * @throws Exception
	 */
	protected function checkInput(): bool {
		$fields = [
			'update' =>				'in 0,1',
			'form_refresh' =>		'in 0,1',
			'row_index' =>			'required|int32',
			'days' =>				'array',
			'months' =>				'array',
			'month_date_type' =>	'in 0,1',
			'period_days' =>		'int32',
			'hour' =>				'int32|ge 0|le 23',
			'minute' =>				'int32|ge 0|le 59',
			'period_hours' =>		'int32|ge 0|le 23',
			'period_minutes' =>		'int32|ge 0|le 59',
			'start_date' =>			'string',
			'monthly_days' =>		'array',
			'timeperiodid' =>		'id',
			'timeperiod_type' =>	'in '.implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]),
			'every' =>				'db timeperiods.every',
			'month' =>				'db timeperiods.month',
			'dayofweek' =>			'db timeperiods.dayofweek',
			'day' =>				'db timeperiods.day',
			'start_time' =>			'db timeperiods.start_time',
			'period' =>				'db timeperiods.period'
		];

		$ret = $this->validateInput($fields);

		$ret = ($ret && $this->getInput('form_refresh', 0)) ? $this->validateTypeSpecificInput() : $ret;

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function validateTypeSpecificInput() {
		$rules = [
			'period' => 'int32'
		];
		$data = [
			'period' =>	strval(($this->getInput('period_days', 0) * SEC_PER_DAY)
				+ ($this->getInput('period_hours', 0) * SEC_PER_HOUR)
				+ ($this->getInput('period_minutes', 0) * SEC_PER_MIN))
		];

		switch ($this->getInput('timeperiod_type')) {
			case TIMEPERIOD_TYPE_ONETIME:
				$parser = new CAbsoluteTimeParser();
				$failed = ($parser->parse($this->getInput('start_date')) != CParser::PARSE_SUCCESS);
				$start_date = $parser->getDateTime(true);

				if ($failed || !validateDateInterval($start_date->format('Y'), $start_date->format('m'),
						$start_date->format('d'))) {
					error(_('Incorrect maintenance - date must be between 1970.01.01 and 2038.01.18'));

					return false;
				}
				break;

			case TIMEPERIOD_TYPE_DAILY:
				$rules['every'] = 'required|ge 1';
				break;

			case TIMEPERIOD_TYPE_WEEKLY:
				$rules = [
					'every'	=>		'required|ge 1',
					'days' =>		'required|not_empty'
				];
				break;

			case TIMEPERIOD_TYPE_MONTHLY:
				$rules['months'] = 'required|not_empty';

				if ($this->getInput('month_date_type', 0)) {
					$rules['monthly_days'] = 'required|not_empty';
				}
				else {
					$rules['day'] = 'required|ge 1|le 31';
				}

				break;
		}

		$this->getInputs($data, array_keys($rules));

		if ($data['period'] < 300) {
			info(_('Incorrect maintenance period (minimum 5 minutes)'));

			return false;
		}

		$validator = new CNewValidator($data, $rules);
		$errors = $validator->getAllErrors();
		array_map('info', $errors);

		return !$errors;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)
			&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE);
	}

	protected function doAction(): void {
		$timeperiod = [
			'update' =>				0,
			'timeperiod_type' => 	TIMEPERIOD_TYPE_ONETIME,
			'every' =>				1,
			'day' =>				1,
			'dayofweek' =>			0,
			'start_date' =>			date(ZBX_DATE_TIME),
			'period' =>				SEC_PER_HOUR,
			'start_time' =>			0
		];
		$fields = [
			TIMEPERIOD_TYPE_ONETIME =>	['start_date'],
			TIMEPERIOD_TYPE_DAILY =>	['every', 'start_time', 'hour', 'minute'],
			TIMEPERIOD_TYPE_WEEKLY =>	['dayofweek', 'every', 'start_time', 'hour', 'minute', 'days'],
			TIMEPERIOD_TYPE_MONTHLY =>	['day', 'dayofweek', 'month', 'months', 'month_date_type', 'monthly_days',
				'start_time', 'hour', 'minute', 'every'
			]
		];
		$this->getInputs($timeperiod, ['update', 'form_refresh', 'row_index', 'period_days', 'period', 'period_hours',
			'period_minutes', 'timeperiodid', 'timeperiod_type'
		]);

		if (array_key_exists($timeperiod['timeperiod_type'], $fields)) {
			$this->getInputs($timeperiod, $fields[$timeperiod['timeperiod_type']]);
		}

		if ($this->getInput('form_refresh', 0)) {
			$timeperiod += [
				'month_date_type' => 0,
				'hour' => 0,
				'minute' => 0
			];

			if ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) {
				$parser = new CAbsoluteTimeParser();
				$parser->parse($timeperiod['start_date']);
				$start_date = $parser->getDateTime(true);
				$timeperiod['start_date'] = $start_date->format(ZBX_DATE_TIME);
			}
			elseif ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY) {
				$timeperiod['dayofweek'] = array_sum($this->getInput('days', []));
			}
			elseif ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY) {
				$timeperiod['month'] = array_sum($this->getInput('months', []));

				if ($timeperiod['month_date_type'] == 1) {
					$timeperiod['dayofweek'] = array_sum($this->getInput('monthly_days', []));
					unset($timeperiod['day']);
				}
				else {
					unset($timeperiod['every']);
				}
			}

			$timeperiod['period'] = ($timeperiod['period_days'] * SEC_PER_DAY)
				+ ($timeperiod['period_hours'] * SEC_PER_HOUR) + ($timeperiod['period_minutes'] * SEC_PER_MIN);
			$timeperiod['start_time'] = ($timeperiod['hour'] * SEC_PER_HOUR) + ($timeperiod['minute'] * SEC_PER_MIN);
			$timeperiod += DB::getDefaults('timeperiods');
		}
		else {
			// Initialize form fields from database field values.
			$timeperiod += [
				'period_days' =>		floor($timeperiod['period'] / SEC_PER_DAY),
				'period_hours' =>		floor(($timeperiod['period'] % SEC_PER_DAY) / SEC_PER_HOUR),
				'period_minutes' => 	floor((($timeperiod['period'] % SEC_PER_DAY) % SEC_PER_HOUR) / SEC_PER_MIN),
				'hour' =>				sprintf("%02d", floor($timeperiod['start_time'] / SEC_PER_HOUR)),
				'minute' =>				sprintf("%02d", floor(($timeperiod['start_time'] % SEC_PER_HOUR) / SEC_PER_MIN)),
				'month_date_type' =>	($timeperiod['timeperiod_type'] != TIMEPERIOD_TYPE_MONTHLY
					|| $timeperiod['day'] > 0) ? 0 : 1
			];
		}

		$params = array_intersect_key($timeperiod, DB::getSchema('timeperiods')['fields']);
		$params['row_index'] = $timeperiod['row_index'];

		$params += [
			'period_type' => timeperiod_type2str($timeperiod['timeperiod_type']),
			'schedule' => $timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME
				? $timeperiod['start_date']
				: schedule2str($timeperiod),
			'period_table_entry' => zbx_date2age(0, $timeperiod['period'])
		];

		$data['body'] = $params;

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
