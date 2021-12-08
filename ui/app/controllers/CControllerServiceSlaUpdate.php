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
		$schedule = [];
		$rules = [];

		foreach ($this->getInput('schedule', []) as $weekday => &$periods) {
			$periods = trim($periods);

			if ($periods === '') {
				continue;
			}

			$periods = str_replace(',', ';', $periods);
			$periods = explode(';', $periods);

			foreach ($periods as $key => $period) {
				$pattern_weekday = $weekday == 0 ? 7 : $weekday;
				$schedule[$weekday.'.'.$key] = $pattern_weekday.','.trim($period);
			}
		}
		unset($periods);

		foreach ($schedule as $key => $period) {
			$rules[$key] = 'time_periods';
		}

		$validator = new CNewValidator($schedule, $rules);

		foreach ($validator->getAllErrors() as $error) {
			error($error);
		}

		if ($validator->isError()) {
			return false;
		}

		foreach ($schedule as $key => $period) {
			$key = explode('.', $key);
			$weekday = array_shift($key);

			$period = explode(',', $period);
			$period = array_pop($period);
			$period = explode('-', $period);

			$period = [
				'period_from' => strtotime(getDayOfWeekCaption($weekday).', '.$period[0]) % SEC_PER_WEEK,
				'period_to' => strtotime(getDayOfWeekCaption($weekday).', '.$period[1]) % SEC_PER_WEEK
			];

			$this->schedule[] = $period;
		}

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
