<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerPopupMaintenancePeriodEdit extends CController {

	protected function checkInput() {
		$fields = [
			'update' =>				'in 0,1',
			'refresh' =>			'in 0,1',
			'index' =>				'required|int32',
			'days' =>				'array',
			'months' =>				'array',
			'month_date_type' =>	'in 0,1',
			'period_days' =>		'int32',
			'hour' =>				'int32|ge 0|le 23',
			'minute' =>				'int32|ge 0|le 59',
			'period_hours' =>		'int32|ge 0|le 23',
			'period_minutes' =>		'int32|ge 0|le 59',
			'start_date' =>			'string',

			'timeperiodid' =>		'id',
			'timeperiod_type' =>	'in '.implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY,
				TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]
			),
			'every' =>				'db timeperiods.every',
			'month' =>				'db timeperiods.month',
			'dayofweek' =>			'db timeperiods.dayofweek',
			'day' =>				'db timeperiods.day',
			'start_time' =>			'db timeperiods.start_time',
			'period' =>				'db timeperiods.period'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			$messages = getMessages();

			if ($messages !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = $this->getInputAll() + [
			'update' =>			0,
			'timeperiod_type'	=> TIMEPERIOD_TYPE_ONETIME,
			'every' =>			1,
			'dayofweek' =>		0,
			'start_date' =>		date(ZBX_DATE_TIME),
			'period' =>			SEC_PER_HOUR,
			'start_time' =>		12 * SEC_PER_HOUR
		];

		if ($this->getInput('refresh', 0)) {
			if ($data['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY) {
				$data['dayofweek'] = array_sum($this->getInput('days', []));
			}
			if ($data['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY) {
				$data['month'] = array_sum($this->getInput('months', []));

				if ($data['month_date_type'] == 1) {
					$data['dayofweek'] = array_sum($this->getInput('days', []));
					$data['day'] = 0;
				}
			}

			$data['period'] = ($data['period_days'] * SEC_PER_DAY) + ($data['period_hours'] * SEC_PER_HOUR)
				+ ($data['period_minutes'] * SEC_PER_MIN);

			$data['start_time'] = ($data['hour'] * SEC_PER_HOUR) + ($data['minute'] * SEC_PER_MIN);
		}
		else {
			// Initialize form fields from database field values.
			$data += [
				'day' =>				1,
				'period_days' =>		floor($data['period'] / SEC_PER_DAY),
				'period_hours' =>		floor(($data['period'] % SEC_PER_DAY) / SEC_PER_HOUR),
				'period_minutes' => 	floor((($data['period'] % SEC_PER_DAY) % SEC_PER_DAY) / SEC_PER_MIN),
				'hour' =>				sprintf("%02d", floor($data['start_time'] / SEC_PER_HOUR)),
				'minute' =>				sprintf("%02d", floor(($data['start_time'] % SEC_PER_HOUR) / SEC_PER_MIN)),
				'month_date_type' =>	($data['timeperiod_type'] != TIMEPERIOD_TYPE_MONTHLY || $data['day'] > 0)
					? 0 : 1
			];
		}

		$params = array_intersect_key($data, DB::getSchema('timeperiods')['fields']);
		$params['index'] = $data['index'];

		$this->setResponse(new CControllerResponseData([
			'title' => $data['update']
				? _('Update maintenance period')
				: _('Add maintenance period'),
			'errors' => hasErrorMesssages() ? getMessages() : null,
			'params' => $params,
			'user' => ['debug_mode' => $this->getDebugMode()]
		] + $data));
	}
}
