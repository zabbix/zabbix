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


class CControllerPopupSlaDowntimeUpdate extends CController {

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'row_index' =>			'required|ge 0',
			'name' => 				'required|string',
			'start_time' => 		'required|range_time',
			'duration_days' => 		'required|ge 0',
			'duration_hours' =>		'required|in '.implode(',', range(0, 23)),
			'duration_minutes' =>	'required|in '.implode(',', range(0, 59))
		];

		$ret = $this->validateInput($fields) && $this->validateDurationEntered();

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function validateDurationEntered() {
		$durations = [];
		$this->getInputs($durations, ['duration_days', 'duration_hours', 'duration_minutes']);

		$ret = (array_sum($durations) > 0);

		if (!$ret) {
			error(_('Incorrect downtime duration.'));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA)) {
			return false;
		}

		return true;
	}

	protected function doAction(): void {
		$period_from = (new DateTime($this->getInput('start_time')))->getTimestamp();
		$duration = $this->getInput('duration_days') * SEC_PER_DAY
			+ $this->getInput('duration_hours') * SEC_PER_HOUR
			+ $this->getInput('duration_minutes') * SEC_PER_MIN;

		$output = [
			'form' => [
				'row_index' => $this->getInput('row_index'),
				'name' => $this->getInput('name'),
				'start_time' => zbx_date2str(DATE_TIME_FORMAT, $period_from),
				'period_from' => $period_from,
				'period_to' => $period_from + $duration,
				'duration' => convertUnitsS($duration, true),
				'duration_days' => $this->getInput('duration_days'),
				'duration_hours' => $this->getInput('duration_hours'),
				'duration_minutes' => $this->getInput('duration_minutes')
			]
		];

		if ($messages = CMessageHelper::getMessages()) {
			$output['messages'] = array_column($messages, 'message');
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
