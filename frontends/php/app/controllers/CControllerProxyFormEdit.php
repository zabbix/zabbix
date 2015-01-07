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

class CControllerProxyFormEdit extends CController {

	protected function checkInput() {
		$fields = array(
			'form' =>				'fatal|in_int:1',
			'proxyid' =>			'fatal|db:hosts.hostid      |required',
			'host' =>				'fatal|db:hosts.host        |required_if:form,1',
			'status' =>				'fatal|db:hosts.status      |required_if:form,1|in_int:'.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE,
			'interface' =>			'fatal|                     required_if:form,1|required_if:status,'.HOST_STATUS_PROXY_ACTIVE,
			'interface/dns' =>		'fatal|db:interface.dns     |required_if:form,1|required_if:status,'.HOST_STATUS_PROXY_ACTIVE,
			'interface/ip' =>		'fatal|db:interface.ip      |required_if:form,1|required_if:status,'.HOST_STATUS_PROXY_ACTIVE,
			'interface/useip' =>	'fatal|db:interface:useip   |required_if:form,1|required_if:status,'.HOST_STATUS_PROXY_ACTIVE.'|in_int:1',
			'interface/port' =>		'fatal|db:interface:port    |required_if:form,1|required_if:status,'.HOST_STATUS_PROXY_ACTIVE,
			'proxy_hostids' =>		'fatal|array_db:hosts.hostid',
			'description' =>		'fatal|db:hosts.description |required_if:form,1'
		);

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$proxies = API::Proxy()->get(array(
			'output' => array(),
			'proxyids' => $this->getInput('proxyid')
		));

		if (!$proxies) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		if ($this->hasInput('form')) {
			$data = array(
				'proxyid' => $this->getInput('proxyid'),
				'name' => $this->getInput('host'),
				'status' => $this->getInput('status'),
				'proxy_hostids' => $this->getInput('proxy_hostids'),
				'interface' => $this->getInput('interface'),
				'description' => $this->getInput('description')
			);
		}
		else {
			$proxies = API::Proxy()->get(array(
				'output' => array('proxyid', 'host', 'status', 'description'),
				'selectHosts' => array('hostid', 'host'),
				'selectInterface' => array('interfaceid', 'hostid', 'dns', 'ip', 'useip', 'port'),
				'proxyids' => $this->getInput('proxyid')
			));
			$proxy = $proxies[0];

			$data = array(
				'proxyid' => $proxy['proxyid'],
				'name' => $proxy['host'],
				'status' => $proxy['status'],
				'proxy_hostids' => zbx_objectValues($proxy['hosts'], 'hostid'),
				'interface' => $proxy['interface'],
				'description' => $proxy['description']
			);

			// interface
			if ($data['status'] == HOST_STATUS_PROXY_ACTIVE) {
				$data['interface'] = array(
					'dns' => 'localhost',
					'ip' => '127.0.0.1',
					'useip' => 1,
					'port' => '10051'
				);
			}
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
