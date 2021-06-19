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


class CControllerPopupServiceTimeEdit extends CControllerPopup {

	protected function checkInput(): bool {
		$fields = [
			'type' =>		'in '.implode(',', [SERVICE_TIME_TYPE_UPTIME, SERVICE_TIME_TYPE_DOWNTIME, SERVICE_TIME_TYPE_ONETIME_DOWNTIME]),
			'ts_from' =>	'string',
			'ts_to' =>		'string',
			'note' =>		'string',
			'edit' =>		'in 1',
			'update' =>		'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SERVICES);
	}

	protected function doAction(): void {
		$this->setResponse($this->hasInput('update') ? $this->prepareJsonResponse() : $this->prepareViewResponse());
	}

	protected function prepareJsonResponse(): CControllerResponse {
		$data = [
			'type' => SERVICE_TIME_TYPE_UPTIME,
			'ts_from' => 0,
			'ts_to' => 0,
			'note' => ''
		];
		$this->getInputs($data, ['type', 'ts_from', 'ts_to', 'note', 'edit']);

		$type_names = [
			SERVICE_TIME_TYPE_UPTIME => _('Uptime'),
			SERVICE_TIME_TYPE_DOWNTIME => _('Downtime'),
			SERVICE_TIME_TYPE_ONETIME_DOWNTIME => _('One-time downtime')
		];
		$data['type_name'] = $type_names[$data['type']];

		if ($data['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME) {
			$data['time_from'] = zbx_date2str(DATE_TIME_FORMAT, $data['ts_from']);
			$data['time_till'] = zbx_date2str(DATE_TIME_FORMAT, $data['ts_to']);
		}
		else {
			$data['time_from'] = dowHrMinToStr($data['ts_from']);
			$data['time_till'] = dowHrMinToStr($data['ts_to']);
		}

		return (new CControllerResponseData(['main_block' => json_encode($data)]))->disableView();
	}

	protected function prepareViewResponse(): CControllerResponse {
		$data = [
			'action' => $this->getAction(),
			'edit' => 0,
			'type' => SERVICE_TIME_TYPE_UPTIME,
			'ts_from' => 0,
			'ts_to' => 0,
			'note' => ''
		];
		$this->getInputs($data, array_keys($data));

		$data += [
			'title' => _('Service time'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		return new CControllerResponseData($data);
	}
}
