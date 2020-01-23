<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
			'refresh_unsupported'    => 'string',
			'discovery_groupid'      => 'db hstgrp.groupid',
			'default_inventory_mode' => 'in '.HOST_INVENTORY_DISABLED.','.HOST_INVENTORY_MANUAL.','.HOST_INVENTORY_AUTOMATIC,
			'alert_usrgrpid'         => 'db usrgrp.usrgrpid',
			'snmptrap_logging'       => 'in 0,1'
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
			'refresh_unsupported'    => $this->getInput('refresh_unsupported', $config['refresh_unsupported']),
			'discovery_groupid'      => $this->getInput('discovery_groupid', $config['discovery_groupid']),
			'default_inventory_mode' => $this->getInput('default_inventory_mode', $config['default_inventory_mode']),
			'alert_usrgrpid'         => $this->getInput('alert_usrgrpid', $config['alert_usrgrpid']),
			'snmptrap_logging'       => $this->getInput('snmptrap_logging', $config['snmptrap_logging'])
		];

		$data['discovery_groups'] = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'editable' => true
		]);
		order_result($data['discovery_groups'], 'name');

		$data['alert_usrgrps'] = DBfetchArray(DBselect('SELECT u.usrgrpid,u.name FROM usrgrp u'));
		order_result($data['alert_usrgrps'], 'name');

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Other configuration parameters'));
		$this->setResponse($response);
	}
}
