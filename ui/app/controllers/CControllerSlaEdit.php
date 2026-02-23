<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerSlaEdit extends CController {

	/**
	 * @var array
	 */
	private $sla;

	protected function init() {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(['object', 'fields' => [
			'slaid' => ['db sla.slaid']
		]]);

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

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA) || !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA)) {
			return false;
		}

		if ($this->hasInput('slaid')) {
			$this->sla = API::Sla()->get([
				'output' => ['slaid', 'name', 'period', 'slo', 'effective_date', 'timezone', 'status', 'description'],
				'selectServiceTags' => ['tag', 'operator', 'value'],
				'selectSchedule' => ['period_from', 'period_to'],
				'selectExcludedDowntimes' => ['name', 'period_from', 'period_to'],
				'slaids' => $this->getInput('slaid')
			]);

			if (!$this->sla) {
				return false;
			}

			$this->sla = $this->sla[0];
		}

		return true;
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		if ($this->sla !== null) {
			CArrayHelper::sort($this->sla['service_tags'], ['tag', 'value', 'operator']);
			$this->sla['service_tags'] = array_values($this->sla['service_tags']);
		}

		$defaults = DB::getDefaults('sla');

		if ($this->sla !== null) {
			$schedule_periods = CSlaHelper::getSchedulePeriods($this->sla['schedule']);
			$schedule_periods = self::structureSchedulePeriods($schedule_periods);

			foreach ($this->sla['excluded_downtimes'] as $row_index => &$excluded_downtime) {
				$excluded_downtime += [
					'row_index' => $row_index,
					'start_time' => zbx_date2str(DATE_TIME_FORMAT, $excluded_downtime['period_from']),
					'duration' => convertUnitsS($excluded_downtime['period_to'] - $excluded_downtime['period_from'],
						['ignore_milliseconds' => true]
					)
				];
			}
			unset($excluded_downtime);

			$data = [
				'slaid' => $this->sla['slaid'],
				'form_action' => 'sla.update',
				'form' => [
					'name' => $this->sla['name'],
					'slo' => (string) round((float) $this->sla['slo'], 4),
					'period' => $this->sla['period'],
					'timezone' => $this->sla['timezone'],
					'schedule_mode' => $this->sla['schedule']
						? CSlaHelper::SCHEDULE_MODE_CUSTOM
						: CSlaHelper::SCHEDULE_MODE_24X7,
					'schedule_periods' => $schedule_periods,
					'effective_date' => zbx_date2str(ZBX_DATE, $this->sla['effective_date'], 'UTC'),
					'service_tags' => $this->sla['service_tags'],
					'description' => $this->sla['description'],
					'status' => $this->sla['status'],
					'excluded_downtimes' => $this->sla['excluded_downtimes']
				]
			];
		}
		else {
			$data = [
				'slaid' => null,
				'form_action' => 'sla.create',
				'form' => [
					'name' => $defaults['name'],
					'slo' => '',
					'period' => ZBX_SLA_PERIOD_WEEKLY,
					'timezone' => ZBX_DEFAULT_TIMEZONE,
					'schedule_mode' => CSlaHelper::SCHEDULE_MODE_24X7,
					'schedule_periods' => self::structureSchedulePeriods([0 => ''] + array_fill(1, 5, '8:00-17:00') + [6 => '']),
					'effective_date' => zbx_date2str(ZBX_DATE, null, CTimezoneHelper::getSystemTimezone()),
					'service_tags' => [
						['tag' => '', 'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_EQUAL, 'value' => '']
					],
					'description' => $defaults['description'],
					'status' => ZBX_SLA_STATUS_ENABLED,
					'excluded_downtimes' => []
				]
			];
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$js_validation_rules = $data['slaid']
			? CControllerSlaUpdate::getValidationRules()
			: CControllerSlaCreate::getValidationRules();

		$data['js_validation_rules'] = (new CFormValidator($js_validation_rules))->getRules();

		$this->setResponse(new CControllerResponseData($data));
	}

	protected static function structureSchedulePeriods(array $periods): array {
		$result = [];

		foreach ($periods as $day => $period_string) {
			$result[] = ['day' => $day, 'period' => $period_string, 'enabled' => $period_string !== ''];
		}

		return $result;
	}
}
