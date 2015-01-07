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
			'form' =>				'fatal|in_int:1',
			'host' =>				'fatal|db:hosts.host        |required_if:form,1',
			'status' =>				'fatal|db:hosts.status      |required_if:form,1                                                |in_int:'.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE,
			'interface' =>			'fatal|array                |required_if:form,1|required_if:status,'.HOST_STATUS_PROXY_ACTIVE,
			'interface/dns' =>		'fatal|db:interface.dns     |required_if:form,1|required_if:status,'.HOST_STATUS_PROXY_ACTIVE,
			'interface/ip' =>		'fatal|db:interface.ip      |required_if:form,1|required_if:status,'.HOST_STATUS_PROXY_ACTIVE,
			'interface/useip' =>	'fatal|db:interface:useip   |required_if:form,1|required_if:status,'.HOST_STATUS_PROXY_ACTIVE.'|in_int:1',
			'interface/port' =>		'fatal|db:interface:port    |required_if:form,1|required_if:status,'.HOST_STATUS_PROXY_ACTIVE,
			'proxy_hostids' =>		'fatal|array_db:hosts.hostid',
			'description' =>		'fatal|db:hosts.description |required_if:form,1'
		);

		$result = $this->validateInput($fields);

		if (!$result) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $result;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		if ($this->hasInput('form')) {
			$data = array(
				'proxyid' => 0,
				'name' => $this->getInput('host'),
				'status' => $this->getInput('status'),
				'proxy_hostids' => $this->getInput('proxy_hostids'),
				'interface' => $this->getInput('interface'),
				'description' => $this->getInput('description')
			);
		}
		else {
			$data = array(
				'proxyid' => 0,
				'name' => '',
				'status' => HOST_STATUS_PROXY_ACTIVE,
				'proxy_hostids' => array(),
				'interface' => array(
					'dns' => 'localhost',
					'ip' => '127.0.0.1',
					'useip' => 1,
					'port' => '10051'),
				'description' => ''
			);
		}

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
