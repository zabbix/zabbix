<?php declare(strict_types = 0);
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


class CControllerMaintenanceTimePeriodCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'row_index' =>			'required|int32',
			'timeperiod_type' =>	'required|in '.implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]),
			'every_day' =>			'required|int32',
			'every_week' =>			'required|int32',
			'weekly_days' =>		'array',
			'months' =>				'array',
			'month_date_type' =>	'required|in 0,1',
			'every_dow' =>			'required|in 1,2,3,4,5',
			'monthly_days' =>		'array',
			'day' =>				'required|int32',
			'start_date' =>			'required|string',
			'hour' =>				'required|ge 0|le 23',
			'minute' =>				'required|ge 0|le 59',
			'period_days' =>		'required|ge 0|le 999',
			'period_hours' =>		'required|ge 0|le 23',
			'period_minutes' =>		'required|ge 0|le 59'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$errors = self::validateTypeSpecificInput($this->getInputAll());

			if ($errors) {
				foreach ($errors as $error) {
					error($error);
				}

				$ret = false;
			}
		}

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

	private static function validateTypeSpecificInput(array $input): array {
		$errors = [];

		switch ($input['timeperiod_type']) {
			case TIMEPERIOD_TYPE_ONETIME:
				$validator = new CNewValidator($input, [
					'start_date' => 'abs_time'
				]);

				$errors = array_merge($errors, $validator->getAllErrors());

				if (!$validator->isErrorFatal() && !$validator->isError()) {
					$parser = new CAbsoluteTimeParser();
					$parser->parse($input['start_date']);
					$start_date = $parser->getDateTime(true);

					if (!validateDateInterval($start_date->format('Y'), $start_date->format('m'),
							$start_date->format('j'))) {
						$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Date'),
							_s('value must be between "%1$s" and "%2$s"', '1970-01-01', '2038-01-18')
						);
					}
				}
				break;

			case TIMEPERIOD_TYPE_DAILY:
				$errors = array_merge($errors, (new CNewValidator($input, [
					'every_day' => 'ge 1|le 999'
				]))->getAllErrors());
				break;

			case TIMEPERIOD_TYPE_WEEKLY:
				$errors = array_merge($errors, (new CNewValidator($input, [
					'every_week' => 'ge 1|le 99',
					'weekly_days' => 'required'
				]))->getAllErrors());
				break;

			case TIMEPERIOD_TYPE_MONTHLY:
				$errors = array_merge($errors, (new CNewValidator($input, [
					'months' => 'required'
				]))->getAllErrors());

				switch ($input['month_date_type']) {
					case 0:
						$errors = array_merge($errors, (new CNewValidator($input, [
							'day' => 'ge 1|le 31'
						]))->getAllErrors());
						break;

					case 1:
						$errors = array_merge($errors, (new CNewValidator($input, [
							'monthly_days' => 'required'
						]))->getAllErrors());
						break;
				}
		}

		if ($input['period_days'] == 0 && $input['period_hours'] == 0 && $input['period_minutes'] < 5) {
			$errors[] = _('Incorrect maintenance period (minimum 5 minutes)');
		}

		return $errors;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)
			&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE);
	}

	protected function doAction(): void {
		$timeperiod_type = $this->getInput('timeperiod_type');

		$timeperiod = [
			'row_index' => $this->getInput('row_index'),
			'timeperiod_type' => $timeperiod_type,
			'every' => 1,
			'month' => 0,
			'dayofweek' => 0,
			'day' => 0,
			'start_time' => 0,
			'period' => (int) $this->getInput('period_days') * SEC_PER_DAY
				+ (int) $this->getInput('period_hours') * SEC_PER_HOUR
				+ (int) $this->getInput('period_minutes') * SEC_PER_MIN,
			'start_date' => 0
		];

		switch ($timeperiod_type) {
			case TIMEPERIOD_TYPE_ONETIME:
				$parser = new CAbsoluteTimeParser();
				$parser->parse($this->getInput('start_date'));
				$timeperiod['start_date'] = $parser->getDateTime(true)->getTimestamp();

				break;

			case TIMEPERIOD_TYPE_DAILY:
				$timeperiod['every'] = $this->getInput('every_day');
				$timeperiod['start_time'] = (int) $this->getInput('hour') * SEC_PER_HOUR
					+ (int) $this->getInput('minute') * SEC_PER_MIN;

				break;

			case TIMEPERIOD_TYPE_WEEKLY:
				$timeperiod['every'] = $this->getInput('every_week');
				$timeperiod['dayofweek'] = array_sum($this->getInput('weekly_days', []));
				$timeperiod['start_time'] = (int) $this->getInput('hour') * SEC_PER_HOUR
					+ (int) $this->getInput('minute') * SEC_PER_MIN;

				break;

			case TIMEPERIOD_TYPE_MONTHLY:
				$timeperiod['month'] = array_sum($this->getInput('months', []));

				switch ($this->getInput('month_date_type')) {
					case 0:
						$timeperiod['day'] = $this->getInput('day');
						break;

					case 1:
						$timeperiod['every'] = $this->getInput('every_dow');
						$timeperiod['dayofweek'] = array_sum($this->getInput('monthly_days', []));
						break;
				}

				$timeperiod['start_time'] = (int) $this->getInput('hour') * SEC_PER_HOUR
					+ (int) $this->getInput('minute') * SEC_PER_MIN;

				break;
		}

		$timeperiod += [
			'formatted_type' => CMaintenanceHelper::getTimePeriodTypeNames()[$timeperiod_type],
			'formatted_schedule' => CMaintenanceHelper::getTimePeriodSchedule($timeperiod),
			'formatted_period' => zbx_date2age(0, $timeperiod['period'])
		];

		$data = [
			'body' => $timeperiod
		];

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
