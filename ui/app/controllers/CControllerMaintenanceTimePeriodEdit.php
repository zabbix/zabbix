<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class CControllerMaintenanceTimePeriodEdit extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'edit' => 				'in 1',
			'row_index' =>			'required|int32',
			'timeperiod_type' => 	'in '.implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]),
			'every' =>				'int32',
			'month' =>				'int32',
			'dayofweek' =>			'int32',
			'day' =>				'int32',
			'start_time' =>			'int32',
			'period' =>				'int32',
			'start_date' =>			'int32'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->hasInput('edit')) {
			$fields = [
				'timeperiod_type' =>	'required',
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
				error($error);
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

	protected function doAction(): void {
		$form = [
			'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
			'every' => 1,
			'month' => 0,
			'dayofweek' => 0,
			'month_date_type' => 0,
			'day' => 1,
			'start_date' => date(ZBX_DATE_TIME),
			'hour' => '00',
			'minute' => '00',
			'period_days' => 0,
			'period_hours' => 1,
			'period_minutes' => 0
		];

		if ($this->hasInput('edit')) {
			$timeperiod_type = $this->getInput('timeperiod_type');
			$month_date_type = $timeperiod_type == TIMEPERIOD_TYPE_MONTHLY && $this->getInput('day') == 0
				? 1
				: 0;

			$form['timeperiod_type'] = $timeperiod_type;

			if (in_array($timeperiod_type, [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY])
					|| ($timeperiod_type == TIMEPERIOD_TYPE_MONTHLY && $month_date_type == 1)) {
				$form['every'] = $this->getInput('every');
			}

			if ($timeperiod_type == TIMEPERIOD_TYPE_MONTHLY) {
				$form['month'] = $this->getInput('month');
			}

			if ($timeperiod_type == TIMEPERIOD_TYPE_WEEKLY
					|| ($timeperiod_type == TIMEPERIOD_TYPE_MONTHLY && $month_date_type == 1)) {
				$form['dayofweek'] = $this->getInput('dayofweek');
			}

			$form['month_date_type'] = $month_date_type;

			if ($timeperiod_type == TIMEPERIOD_TYPE_MONTHLY && $month_date_type == 0) {
				$form['day'] = $this->getInput('day');
			}

			if ($timeperiod_type == TIMEPERIOD_TYPE_ONETIME) {
				$form['start_date'] = date(ZBX_DATE_TIME, $this->getInput('start_date'));
			}

			if (in_array($timeperiod_type, [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])) {
				$form['hour'] = sprintf('%02d', floor($this->getInput('start_time') / SEC_PER_HOUR));
				$form['minute'] = sprintf('%02d', floor(($this->getInput('start_time') % SEC_PER_HOUR) / SEC_PER_MIN));
			}

			$form['period_days'] = floor($this->getInput('period') / SEC_PER_DAY);
			$form['period_hours'] = floor(($this->getInput('period') % SEC_PER_DAY) / SEC_PER_HOUR);
			$form['period_minutes']= floor((($this->getInput('period') % SEC_PER_DAY) % SEC_PER_HOUR) / SEC_PER_MIN);
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
