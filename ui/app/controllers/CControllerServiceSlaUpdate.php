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


class CControllerServiceSlaUpdate extends CController {

	protected $record;
	protected $schedule = [];

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'id' =>							'db sla.slaid',
			'name' =>						'required|db services.name|not_empty',
			'period' =>						'required|in '.implode(',', array_keys(CSlaHelper::periods())),
			'slo' =>						'required|not_empty',
			'effective_date' =>				'required|range_time',
			'timezone' =>					'required|in '.implode(',', array_keys(CDateTimeZoneHelper::getAllDateTimeZones())),
			'status' =>						'required|in '.implode(',', [CSlaHelper::SLA_STATUS_DISABLED, CSlaHelper::SLA_STATUS_ENABLED]),
			'description' => 				'required|db sla.description',
			'service_tags' =>				'array',
			'schedule' => 					'array',
			'excluded_downtimes' =>			'array'
		];

		$ret = $this->validateInput($fields) && $this->validateSlo() && $this->validateAndTransformSchedule();

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function validateSLo(): bool {
		$slo = (float) $this->getInput('slo');

		if (!($slo > 0 && $slo <= 100)) {
			error(_s(
				'Incorrect value "%1$s" for "%2$s" field: must be between %3$s and %4$s.', $this->getInput('slo'),
				'slo', 0, 100
			));
		}

		return true;
	}

	protected function validateAndTransformSchedule(): bool {
		$rules = [
			'hours_from' => 'ge 0|le 24',
			'hours_till' => 'ge 0|le 24',
			'minutes_from' => 'ge 0|le 59',
			'minutes_till' => 'ge 0|le 59'
		];

		function periodError(int $weekday, ?int $key = null): bool {
			error(_s(
				'Incorrect value for field "%1$s": %2$s.',
				_('Schedule').':'.getDayOfWeekCaption($weekday).($key === null ? '' : '/'.++$key),
				_('a time period is expected')
			));

			return false;
		}

		foreach ($this->getInput('schedule', []) as $weekday => &$periods) {
			$periods = preg_replace('/\s/', '', $periods);

			if ($periods === '') {
				continue;
			}

			$hhmm_hhmm = '(?P<hours_from>[0-9]{1,2}):(?P<minutes_from>[0-9]{2})'.
				'-(?P<hours_till>[0-9]{1,2}):(?P<minutes_till>[0-9]{2})';

			if (!preg_match('/^('.$hhmm_hhmm.',?)+$/', $periods)) {
				return periodError($weekday);
			}

			$periods = explode(',', $periods);
			$matches = [];

			foreach ($periods as $key => $period) {
				preg_match('/'.$hhmm_hhmm.'/', $period, $matches);

				$validator = new CNewValidator($matches, $rules);

				if ($validator->isError()) {
					periodError($weekday, $key);

					foreach ($validator->getAllErrors() as $error) {
						info($error);
					}

					return false;
				}

				$period = explode('-', $period);
				$period = [
					'period_from' => strtotime(getDayOfWeekCaption($weekday).', '.$period[0]) % SEC_PER_WEEK,
					'period_to' => strtotime(getDayOfWeekCaption($weekday).', '.$period[1]) % SEC_PER_WEEK
				];

				if ($period['period_from'] > $period['period_to']) {
					return periodError($weekday, $key);
				}

				$this->schedule[] = $period;
			}
		}
		unset($periods);

		return true;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA)) {
			return false;
		}

		if ($this->hasInput('id') || $this->hasInput('clone')) {
			$source_id = $this->hasInput('id') ? $this->getInput('id') : $this->getInput('clone');

			$this->record = API::SLA()->get([
				'output' => array_merge(CSlaHelper::OUTPUT_FIELDS, ['slaid']),
				'slaids' => $source_id,
				'editable' => true,
				'limit' => 1
			]);

			if (!$this->record) {
				return false;
			}

			$this->record = $this->record[0];
		}

		return true;
	}

	protected function doAction(): void {
		$this->record = $this->record === null ? [] : $this->record;
		$this->record = $this->record + DB::getDefaults('sla');
		$this->getInputs($this->record, array_keys($this->record));
		$this->record += [
			'service_tags' => [],
			'schedule' => [],
			'excluded_downtimes' => []
		];

		foreach ($this->getInput('service_tags', []) as $tag) {
			if ($tag['tag'] === '' && $tag['value'] === '') {
				continue;
			}

			$this->record['service_tags'][] = $tag;
		}

		$this->record['excluded_downtimes'] = $this->getInput('excluded_downtimes', []);
		$this->record['effective_date'] = (new DateTime($this->getInput('effective_date')))->getTimestamp();
		$this->record['schedule'] = $this->schedule;

		if ($this->hasInput('id')) {
			$result = API::SLA()->update($this->record);
			$output = ['title' => _('SLA updated')];
		}
		else {
			$result = API::SLA()->create($this->record);
			$output = ['title' => _('SLA created')];
		}

		if ($result) {
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
}
