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


class CControllerPopupSlaExcludedDowntimeEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'edit' => 			'in 1',
			'row_index' =>		'required|int32',
			'name' =>			'string',
			'period_from' =>	'uint64',
			'period_to' =>		'uint64'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->hasInput('edit')) {
			$fields = [
				'name' =>			'required',
				'period_from' =>	'required',
				'period_to' =>		'required'
			];

			$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);

			foreach ($validator->getAllErrors() as $error) {
				info($error);
			}

			$ret = !$validator->isErrorFatal() && !$validator->isError();
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

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SLA) && $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		if ($this->hasInput('edit')) {
			$datetime_from = (new DateTime())->setTimestamp((int) $this->getInput('period_from'));
			$datetime_to = (new DateTime())->setTimestamp((int) $this->getInput('period_to'));
			$interval = $datetime_to->diff($datetime_from);

			$form = [
				'name' => $this->getInput('name'),
				'start_time' => $datetime_from->format(ZBX_DATE_TIME),
				'duration_days' => $interval->days,
				'duration_hours' => $interval->h,
				'duration_minutes' => $interval->i
			];
		}
		else {
			$form = [
				'name' => '',
				'start_time' => date(ZBX_DATE_TIME, strtotime('tomorrow')),
				'duration_days' => 0,
				'duration_hours' => 1,
				'duration_minutes' => 0
			];
		}

		$data = [
			'title' => $this->hasInput('edit') ? _('Excluded downtime') : _('New excluded downtime'),
			'is_edit' => $this->hasInput('edit'),
			'row_index' => $this->getInput('row_index'),
			'form' => $form,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
