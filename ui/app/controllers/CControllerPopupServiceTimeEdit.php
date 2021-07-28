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


class CControllerPopupServiceTimeEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'form_refresh' => 	'int32',
			'edit' => 			'in 1',
			'row_index' =>		'required|int32',
			'type' =>			'in '.implode(',', [SERVICE_TIME_TYPE_UPTIME, SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_ONETIME_DOWNTIME]),
			'ts_from' =>		'int32',
			'ts_to' =>			'int32',
			'note' =>			'string',
			'from' =>			'string',
			'till' =>			'string',
			'from_week' =>		'in '.implode(',', range(0, 6)),
			'from_hour' =>		'string',
			'from_minute' =>	'string',
			'till_week' =>		'in '.implode(',', range(0, 6)),
			'till_hour' =>		'string',
			'till_minute' =>	'string'
		];

		$ret = $this->validateInput($fields);

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
		return $this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SERVICES);
	}

	protected function doAction(): void {
		$type = $this->getInput('type', SERVICE_TIME_TYPE_UPTIME);

		$form = ['type' => $type];

		switch ($type) {
			case SERVICE_TIME_TYPE_UPTIME:
			case SERVICE_TIME_TYPE_DOWNTIME:
				if ($this->hasInput('form_refresh')) {
					$form += [
						'from_week' => $this->getInput('from_week', ''),
						'from_hour' => $this->getInput('from_hour', ''),
						'from_minute' => $this->getInput('from_minute', ''),
						'till_week' => $this->getInput('till_week', ''),
						'till_hour' => $this->getInput('till_hour', ''),
						'till_minute' => $this->getInput('till_minute', '')
					];
				}
				else {
					$from = $this->hasInput('ts_from') ? strtotime('last Sunday') + $this->getInput('ts_from') : null;
					$till = $this->hasInput('ts_to') ? strtotime('last Sunday') + $this->getInput('ts_to') : null;

					$form += [
						'from_week' => $from !== null ? date('w', $from) : 0,
						'from_hour' => $from !== null ? date('H', $from) : '',
						'from_minute' => $from !== null ? date('i', $from) : '',
						'till_week' => $till !== null ? date('w', $till) : 0,
						'till_hour' => $till !== null ? date('H', $till) : '',
						'till_minute' => $till !== null ? date('i', $till) : ''
					];
				}
				break;

			case SERVICE_TIME_TYPE_ONETIME_DOWNTIME:
				$default_from = date(DATE_TIME_FORMAT, strtotime('today'));
				$default_till = date(DATE_TIME_FORMAT, strtotime('tomorrow'));

				if ($this->hasInput('form_refresh')) {
					$form += [
						'note' => $this->getInput('note', ''),
						'from' => $this->getInput('from', $default_from),
						'till' => $this->getInput('till', $default_till)
					];
				}
				else {
					$form += [
						'note' => $this->getInput('note', ''),
						'from' => $this->hasInput('ts_from')
							? date(DATE_TIME_FORMAT, (int) $this->getInput('ts_from'))
							: $default_from,
						'till' => $this->hasInput('ts_to')
							? date(DATE_TIME_FORMAT, (int) $this->getInput('ts_to'))
							: $default_till
					];
				}
				break;
		}

		$data = [
			'title' => $this->hasInput('edit') ? _('Service time') : _('New service time'),
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
