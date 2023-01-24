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
			'form_refresh' =>			'in 0,1',
			'row_index' =>				'required|int32',
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
		$data = [
			'body' => [
				'row_index' => $this->getInput('row_index'),
				'name' => $this->getInput('name'),
				'period_from' => 1,
				'period_to' => 1,
				'start_time' => zbx_date2str(DATE_TIME_FORMAT, 1),
				'duration' => convertUnitsS(1 - 1, true)
			]
		];

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
