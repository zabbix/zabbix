<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerSlaCreateUpdate extends CController {

	/**
	 * @var array
	 */
	private $schedule = [];

	/**
	 * @var int
	 */
	private $effective_date;

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'name' =>				'required|string|not_empty',
			'slo' =>				'required|string|not_empty',
			'period' =>				'required|in '.implode(',', [ZBX_SLA_PERIOD_DAILY, ZBX_SLA_PERIOD_WEEKLY, ZBX_SLA_PERIOD_MONTHLY, ZBX_SLA_PERIOD_QUARTERLY, ZBX_SLA_PERIOD_ANNUALLY]),
			'timezone' =>			'required|in '.implode(',', array_keys(CTimezoneHelper::getList())),
			'schedule_mode' =>		'required|in '.implode(',', [CSlaHelper::SCHEDULE_MODE_24X7, CSlaHelper::SCHEDULE_MODE_CUSTOM]),
			'schedule_enabled' =>	'array',
			'schedule_periods' =>	'array',
			'effective_date' =>		'required|string|not_empty',
			'service_tags' =>		'required|array',
			'description' =>		'required|string',
			'status' =>				'in '.ZBX_SLA_STATUS_ENABLED,
			'excluded_downtimes' =>	'array'
		];

		if ($this->getAction() === 'sla.update') {
			$fields += [
				'slaid' => 'required|id'
			];
		}

		$ret = $this->validateInput($fields);

		if ($ret) {
			if ($this->getInput('schedule_mode') == CSlaHelper::SCHEDULE_MODE_CUSTOM) {
				try {
					$this->schedule = self::validateCustomSchedule($this->getInput('schedule_enabled', []),
						$this->getInput('schedule_periods', [])
					);
				}
				catch (InvalidArgumentException $e) {
					info($e->getMessage());
					$ret = false;
				}
			}

			try {
				$this->effective_date = self::validateEffectiveDate($this->getInput('effective_date'),
					'effective_date'
				);
			}
			catch (InvalidArgumentException $e) {
				info($e->getMessage());
				$ret = false;
			}
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA)) {
			return false;
		}

		if ($this->getAction() === 'sla.update') {
			return (bool) API::Sla()->get([
				'output' => [],
				'slaids' => $this->getInput('slaid')
			]);
		}

		return true;
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$sla = [
			'effective_date' => $this->effective_date,
			'status' => $this->hasInput('status') ? ZBX_SLA_STATUS_ENABLED : ZBX_SLA_STATUS_DISABLED,
			'schedule' => $this->schedule,
			'service_tags' => [],
			'excluded_downtimes' =>	[]
		];

		$fields = ['name', 'slo', 'period', 'timezone', 'description', 'excluded_downtimes'];

		if ($this->getAction() === 'sla.update') {
			$fields[] = 'slaid';
		}

		$this->getInputs($sla, $fields);

		foreach ($this->getInput('service_tags', []) as $service_tag) {
			if ($service_tag['tag'] === '' && $service_tag['value'] === '') {
				continue;
			}

			$sla['service_tags'][] = $service_tag;
		}


		$result = $this->getAction() === 'sla.update' ? API::Sla()->update($sla) : API::Sla()->create($sla);

		if ($result) {
			$output = ['title' => $this->getAction() === 'sla.update' ? _('SLA updated') : _('SLA created')];

			if ($messages = CMessageHelper::getMessages()) {
				$output['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output = [
				'errors' => makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(), CMessageHelper::getTitle())->toString()
			];
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}

	/**
	 * @param string $effective_date
	 * @param string $field_name
	 *
	 * @return int
	 */
	protected static function validateEffectiveDate(string $effective_date, string $field_name): int {
		$datetime = DateTime::createFromFormat(DATE_FORMAT, $effective_date, new DateTimeZone('UTC'));

		if ($datetime === false || $datetime->getTimestamp() > ZBX_MAX_DATE) {
			throw new InvalidArgumentException(
				_s('Incorrect value for field "%1$s": %2$s.', $field_name, _('a date is expected'))
			);
		}

		return $datetime->getTimestamp();
	}

	/**
	 * @param array $schedule_enabled
	 * @param array $schedule_periods
	 *
	 * @return array
	 */
	protected static function validateCustomSchedule(array $schedule_enabled, array $schedule_periods): array {
		$schedule = [];

		$incorrect_schedule_exception = new InvalidArgumentException(
			_s('Incorrect schedule: %1$s.',
				_('comma separated list of time periods is expected for enabled week days')
			)
		);

		foreach (range(0, 6) as $weekday) {
			if (!array_key_exists($weekday, $schedule_enabled)) {
				continue;
			}

			if (!array_key_exists($weekday, $schedule_periods)) {
				throw new InvalidArgumentException(_('Unexpected server error.'));
			}

			if (!is_string($schedule_periods[$weekday])) {
				throw new InvalidArgumentException(_('Unexpected server error.'));
			}

			$weekday_schedule_periods = trim($schedule_periods[$weekday]);

			if ($weekday_schedule_periods === '') {
				throw $incorrect_schedule_exception;
			}

			foreach (explode(',', $weekday_schedule_periods) as $schedule_period) {
				if (!preg_match('/^\s*(?<from_h>\d{1,2}):(?<from_m>\d{2})\s*-\s*(?<to_h>\d{1,2}):(?<to_m>\d{2})\s*$/',
						$schedule_period, $matches)) {
					throw $incorrect_schedule_exception;
				}

				$from_h = $matches['from_h'];
				$from_m = $matches['from_m'];
				$to_h = $matches['to_h'];
				$to_m = $matches['to_m'];

				$day_period_from = SEC_PER_HOUR * $from_h + SEC_PER_MIN * $from_m;
				$day_period_to = SEC_PER_HOUR * $to_h + SEC_PER_MIN * $to_m;

				if ($from_m > 59 || $to_m > 59 || $day_period_from >= $day_period_to || $day_period_to > SEC_PER_DAY) {
					throw $incorrect_schedule_exception;
				}

				$schedule[] = [
					'period_from' => SEC_PER_DAY * $weekday + $day_period_from,
					'period_to' => SEC_PER_DAY * $weekday + $day_period_to
				];
			}
		}

		if (!$schedule) {
			throw new InvalidArgumentException(_s('Incorrect schedule: %1$s.', _('cannot be empty')));
		}

		return $schedule;
	}
}
