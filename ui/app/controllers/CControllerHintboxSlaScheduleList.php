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


class CControllerHintboxSlaScheduleList extends CController {

	protected $record;

	protected function init(): void {
		$this->disableSIDvalidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'slaid' => 'required|db sla.slaid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
			return false;
		}

		$this->recordid = $this->getInput('slaid');

		/*
		$records = API::SLA()->get([
			'output' => [],
			'slaids' => [$this->recordid]
		]);
		*/

		$records = [123];

		if (!$records) {
			$message = _('No permissions to referred object or it does not exist!');

			$this->setResponse(new CControllerResponseData([
				'message' => $message
			]));

			error($message);
			return false;
		}

		return true;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA)) {
			return false;
		}
	}

	protected function doAction(): void {

		/*
		$records = API::SlaSchedules()->get([
			'output' => ['period_from', 'period_to'],
			'slaids' => [$this->recordid],
			'limit' => ZBX_WIDGET_ROWS
		]);

		// TODO:
		CSlaHelper::convertScheduleToWeekdayPeriods($records);
		*/

		$records = [];
		$x = rand(5, ZBX_WIDGET_ROWS);

		while (--$x > 0) {
			$period_from = intdiv(
				strtotime('+'.rand(1, 7).' days '.rand(-24, 24).' hours '.(rand(0, 1) ? 30 : 0).' minutes'),
				30 * SEC_PER_MIN
			) * 30 * SEC_PER_MIN;
			$period_from = $period_from % SEC_PER_WEEK;
			$period_to = $period_from + rand(1, 14) * 30 * SEC_PER_MIN;

			if (date('d', $period_from) != date('d', $period_to)) {
				$period_to = strtotime(date('Y-m-d', $period_from).' midnight');
			}

			$record = [
				'period_from' => $period_from,
				'period_to' => $period_to
			];

			$records[] = $record;
		}

		$weekly_schedule = [];

		foreach (range(0, 6) as $weekday) {
			$weekly_schedule[$weekday] = [];
		}

		foreach ($records as $record) {
			$weekly_schedule[date('w', $record['period_from'])][$record['period_from']] = $record;
		}

		$this->setResponse(new CControllerResponseData([
			'weekly_schedule' => $weekly_schedule
		]));
	}
}
