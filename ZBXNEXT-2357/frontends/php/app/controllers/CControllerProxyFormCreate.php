<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class CControllerProxyFormCreate extends CController {

	protected function checkInput() {
		$fields = array(
			'host' =>			'fatal|db       hosts.host       |not_empty',
			'status' =>			'fatal|db       hosts.status     |in '.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE,
			'dns' =>			'fatal|db       interface.dns',
			'ip' =>				'fatal|db       interface.ip',
			'useip' =>			'fatal|db       interface.useip  |in 0,1',
			'port' =>			'fatal|db       interface.port',
//			'proxy_hostids' =>	'fatal|array_db hosts.hostid',
			'description' =>	'fatal|db       hosts.description'
		);

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
		$data = array(
			'proxyid' => 0,
			'host' => $this->getInput('host', ''),
			'status' => $this->getInput('status', HOST_STATUS_PROXY_ACTIVE),
			'dns' => $this->getInput('dns', 'localhost'),
			'ip' => $this->getInput('ip', '127.0.0.1'),
			'useip' => $this->getInput('useip', '1'),
			'port' => $this->getInput('port', '10051'),
			'proxy_hostids' => $this->getInput('proxy_hostids', array()),
			'description' => $this->getInput('description', '')
		);

		// fetch available hosts, skip host prototypes
		$data['all_hosts'] = DBfetchArray(DBselect(
			'SELECT h.hostid,h.proxy_hostid,h.name,h.flags'.
			' FROM hosts h'.
			' WHERE h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
				' AND h.flags<>'.ZBX_FLAG_DISCOVERY_PROTOTYPE
		));
		order_result($data['all_hosts'], 'name');

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of proxies'));
		$this->setResponse($response);
	}
}
