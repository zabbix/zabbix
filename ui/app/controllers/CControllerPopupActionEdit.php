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


class CControllerPopupActionEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'eventsource' => 'in '.implode(',', [
				// is service necessary here?
					EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY,
					EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL,
					EVENT_SOURCE_SERVICE
				]),
			'g_actionid' => 'array_id',
			'filter_set' => 'string',
			'filter_rst' =>	'string',
			'filter_name' =>'string',
			'filter_status' =>'in '.implode(',', [-1, ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		// is this enough?
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_ACTIONS);
	}

	protected function doAction(): void {
		// E.S. TODO: pass all the variables. E.g. $data: actionid, action [recovery_operations] allowedOperations
		// TODO : $operations: operationtype, opconditions, opmessage $operationid

		$data['eventsource'] = getRequest('eventsource');
		$response = new CControllerResponseData($data);

		//$response->setTitle(_('Configuration of actions'));

		$this->setResponse($response);
	}
}
