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
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'row_index' => ['integer', 'required'],
			'timeperiod_type' => ['db timeperiods.timeperiod_type', 'required',
				'in' => [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]
			],
			'every_day' => ['integer', 'required', 'min' => 1, 'max' => 999,
				'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_DAILY]]
			],
			'every_week' => ['integer', 'required', 'min' => 1, 'max' => 99,
				'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_WEEKLY]]
			],
			'weekly_days' => ['array', 'required', 'not_empty',
				'field' => ['integer'],
				'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_WEEKLY]],
				'messages' => ['not_empty' => _('At least one day must be selected.')]
			],
			'months' => ['array', 'required', 'not_empty',
				'field' => ['integer'],
				'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]],
				'messages' => ['not_empty' => _('At least one month must be selected.')]
			],
			'month_date_type' => ['integer', 'required', 'in' => [0, 1],
				'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]]
			],
			'every_dow' => ['integer', 'required', 'in' => [MONTH_WEEK_FIRST, MONTH_WEEK_SECOND, MONTH_WEEK_THIRD,
				MONTH_WEEK_FOURTH, MONTH_WEEK_LAST],
				'when' => [['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]], ['month_date_type', 'in' => [1]]]
			],
			'monthly_days' => ['array', 'required', 'not_empty',
				'field' => ['integer'],
				'when' => [['month_date_type', 'in' => [1]], ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]]],
				'messages' => ['not_empty' => _('At least one weekday must be selected.')]
			],
			'day' => ['integer', 'required', 'min' => 1, 'max' => MONTH_MAX_DAY,
				'when' => [['month_date_type', 'in' => [0]], ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_MONTHLY]]]
			],
			'start_date' => ['string', 'required', 'not_empty',
				'use' => [CAbsoluteTimeParser::class, [], ['min' => 0, 'max' => ZBX_MAX_DATE]],
				'when' => ['timeperiod_type', 'in' => [TIMEPERIOD_TYPE_ONETIME]],
				'messages' => ['use' => _('Invalid date.')]
			],
			'hour' => ['integer', 'required', 'min' => 0, 'max' => 23,
				'when' => ['timeperiod_type',
					'in' => [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]]
			],
			'minute' =>	['integer', 'required', 'min' => 0, 'max' => 59,
				'when' => ['timeperiod_type',
					'in' => [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY,TIMEPERIOD_TYPE_MONTHLY]]
			],
			'period_days' => ['integer', 'required', 'min' => 0, 'max' => 999],
			'period_hours' => ['integer', 'required', 'min' => 0, 'max' => 23],
			'period_minutes' => [
				['integer', 'required', 'min' => 0, 'max' => 59],
				['integer', 'required', 'min' => 5,
					'when' => [['period_days', 'in' => [0]], ['period_hours', 'in' => [0]]],
					'messages' => ['min' =>
						_s('Minimum value of "Maintenance period length" is %1$d minutes.', 5)]
				]
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

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
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
				$timeperiod['dayofweek'] = array_sum($this->getInput('weekly_days'));
				$timeperiod['start_time'] = (int) $this->getInput('hour') * SEC_PER_HOUR
					+ (int) $this->getInput('minute') * SEC_PER_MIN;

				break;

			case TIMEPERIOD_TYPE_MONTHLY:
				$timeperiod['month'] = array_sum($this->getInput('months'));
				$timeperiod['month_date_type'] = $this->getInput('month_date_type');

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
