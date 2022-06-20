<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerPopupSlaEdit extends CController {

	/**
	 * @var array
	 */
	private $sla;

	protected function checkInput(): bool {
		$fields = [
			'slaid' => 'db services.serviceid'
		];

		$ret = $this->validateInput($fields);

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

			foreach ($this->sla['excluded_downtimes'] as $row_index => &$excluded_downtime) {
				$excluded_downtime += [
					'row_index' => $row_index,
					'start_time' => zbx_date2str(DATE_TIME_FORMAT, $excluded_downtime['period_from']),
					'duration' => convertUnitsS($excluded_downtime['period_to'] - $excluded_downtime['period_from'],
						true
					)
				];
			}
			unset($excluded_downtime);

			$data = [
				'slaid' => $this->sla['slaid'],
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
				'form' => [
					'name' => $defaults['name'],
					'slo' => '',
					'period' => ZBX_SLA_PERIOD_WEEKLY,
					'timezone' => ZBX_DEFAULT_TIMEZONE,
					'schedule_mode' => CSlaHelper::SCHEDULE_MODE_24X7,
					'schedule_periods' => [0 => ''] + array_fill(1, 5, '8:00-17:00') + [6 => ''],
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

		$this->setResponse(new CControllerResponseData($data));
	}
}
