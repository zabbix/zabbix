<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerMiscConfigEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'demo' => ''
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$config = select_config();

		$data = [
			'refresh_unsupported' => $config['refresh_unsupported'],
			'discovery_groupid' => $config['discovery_groupid'],
			'default_inventory_mode' => $config['default_inventory_mode'],
			'alert_usrgrpid' => $config['alert_usrgrpid'],
			'snmptrap_logging' => $config['snmptrap_logging']
		];

		$data['discovery_groups'] = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'editable' => true
		]);

		$data['alert_usrgrps'] = DBfetchArray(DBselect('SELECT u.usrgrpid,u.name FROM usrgrp u'));
		order_result($data['alert_usrgrps'], 'name');

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Other configuration parameters'));
		$this->setResponse($response);
	}
}
