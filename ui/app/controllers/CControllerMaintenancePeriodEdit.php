<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CControllerMaintenancePeriodEdit extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'edit' => 				'in 1',
			'row_index' =>			'required|int32',
			'timeperiod_type' => 	'in '.implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]),
			'every' =>				'db timeperiods.every',
			'month' =>				'db timeperiods.month',
			'dayofweek' =>			'db timeperiods.dayofweek',
			'day' =>				'db timeperiods.day',
			'start_time' =>			'db timeperiods.start_time',
			'period' =>				'db timeperiods.period',
			'start_date' =>			'string'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->hasInput('edit')) {
			$fields = [
				'timeperiod_type' => 	'required',
				'every' =>				'required',
				'month' =>				'required',
				'dayofweek' =>			'required',
				'day' =>				'required',
				'start_time' =>			'required',
				'period' =>				'required',
				'start_date' =>			'required'
			];

			$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);

			foreach ($validator->getAllErrors() as $error) {
				info($error);
			}

			$ret = !$validator->isErrorFatal() && !$validator->isError();
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)
			&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		if ($this->hasInput('edit')) {
			$form = [
				'edit' => 				$this->getInput('edit'),
				'timeperiod_type' =>	$this->getInput('timeperiod_type'),
				'every' =>				$this->getInput('every'),
				'month' =>				$this->getInput('month'),
				'dayofweek' =>			$this->getInput('dayofweek'),
				'day' =>				$this->getInput('day'),
				'start_time' =>			$this->getInput('start_time'),
				'period' =>				$this->getInput('period'),
				'start_date' =>			$this->getInput('start_date'),
				'period_days' =>		floor($this->getInput('period') / SEC_PER_DAY),
				'period_hours' =>		floor(($this->getInput('period') % SEC_PER_DAY) / SEC_PER_HOUR),
				'period_minutes' => 	floor((($this->getInput('period') % SEC_PER_DAY) % SEC_PER_HOUR) / SEC_PER_MIN),
				'hour' =>				sprintf("%02d", floor($this->getInput('start_time') / SEC_PER_HOUR)),
				'minute' =>				sprintf("%02d",
											floor(($this->getInput('start_time') % SEC_PER_HOUR) / SEC_PER_MIN)
										),
				'month_date_type' =>	($this->getInput('timeperiod_type') != TIMEPERIOD_TYPE_MONTHLY
											|| $this->getInput('day') > 0
										) ? 0 : 1
			];
		}
		else {
			$form = [
				'timeperiod_type' => 	TIMEPERIOD_TYPE_ONETIME,
				'every' =>				1,
				'month' =>				0,
				'dayofweek' =>			0,
				'day' =>				1,
				'start_time' =>			0,
				'period' =>				SEC_PER_HOUR,
				'start_date' =>			date(ZBX_DATE_TIME),
				'period_days' =>		0,
				'period_hours' =>		1,
				'period_minutes' => 	0,
				'hour' =>				sprintf("%02d", 00),
				'minute' =>				sprintf("%02d", 00),
				'month_date_type' =>	0
			];
		}

		$data = [
			'title' => $this->hasInput('edit') ? _('Maintenance period') : _('New maintenance period'),
			'is_edit' => $this->hasInput('edit'),
			'row_index' => $this->getInput('row_index'),
			'form' => $form,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
