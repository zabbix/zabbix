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
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	private static function getValidationRules(): array {
		return ['object', 'fields' => [
			'edit' => ['integer', 'in' => [1]],
			'row_index' => ['integer', 'required'],
			'timeperiod_type' => ['db timeperiods.timeperiod_type', 'required',
				'in' => [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY],
				'when' => ['edit', 'in' => [1]]
			],
			'every' => ['db timeperiods.every', 'required', 'when' => ['edit', 'in' => [1]]],
			'month' => ['db timeperiods.month', 'required',
				'when' => [['edit', 'in' => [1]], ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]]]
			],
			'dayofweek' => ['db timeperiods.dayofweek', 'required',
				'when' => [['edit', 'in' => [1]], ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]]]
			],
			'day' => ['db timeperiods.day', 'required',
				'when' => [['edit', 'in' => [1]], ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]]]
			],
			'start_time' => ['db timeperiods.start_time', 'required',
				'when' => [['edit', 'in' => [1]], ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]]]
			],
			'period' => ['db timeperiods.period', 'required', 'when' => ['edit', 'in' => [1]]],
			'start_date' => ['db timeperiods.start_date', 'required',
				'when' => [['edit', 'in' => [1]], ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_ONETIME]]]
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
				(new CControllerResponseData(['main_block' => json_encode($response)]))->disableView()
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
			$form['period_minutes'] = floor((($this->getInput('period') % SEC_PER_DAY) % SEC_PER_HOUR) / SEC_PER_MIN);
		}

		$data = [
			'title' => $this->hasInput('edit') ? _('Maintenance period') : _('New maintenance period'),
			'is_edit' => $this->hasInput('edit'),
			'row_index' => $this->getInput('row_index'),
			'js_validation_rules' => (new CFormValidator(
				CControllerMaintenanceTimePeriodCheck::getValidationRules()
			))->getRules(),
			'form' => $form,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
