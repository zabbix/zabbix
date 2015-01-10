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
			'form' =>			'fatal                                    |in 1',
			'proxyid' =>		'fatal|required|db       hosts.hostid',
			'host' =>			'fatal         |db       hosts.host       |not_empty',
			'status' =>			'fatal         |db       hosts.status     |in '.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE,
			'dns' =>			'fatal         |db       interface.dns',
			'ip' =>				'fatal         |db       interface.ip',
			'useip' =>			'fatal         |db       interface.useip  |in 0,1',
			'port' =>			'fatal         |db       interface.port',
//			'proxy_hostids' =>	'fatal         |array_db hosts.hostid',
			'description' =>	'fatal         |db       hosts.description'
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
		$data = array(
			'proxyid' => $this->getInput('proxyid')
		);

		if ($this->hasInput('form')) {
			$data['host'] = $this->getInput('host', '');
			$data['status'] = $this->getInput('status', HOST_STATUS_PROXY_ACTIVE);
			$data['dns'] = $this->getInput('dns', 'localhost');
			$data['ip'] = $this->getInput('ip', '127.0.0.1');
			$data['useip'] = $this->getInput('useip', '1');
			$data['port'] = $this->getInput('port', '10051');
			$data['proxy_hostids'] = $this->getInput('proxy_hostids', array());
			$data['description'] = $this->getInput('description', '');
		}
		else {
			$proxies = API::Proxy()->get(array(
				'output' => array('proxyid', 'host', 'status', 'description'),
				'selectHosts' => array('hostid'),
				'selectInterface' => array('interfaceid', 'dns', 'ip', 'useip', 'port'),
				'proxyids' => $data['proxyid']
			));
			$proxy = $proxies[0];

			$data['host'] = $proxy['host'];
			$data['status'] = $proxy['status'];
			if ($data['status'] == HOST_STATUS_PROXY_PASSIVE) {
				$data['dns'] = $proxy['interface'][0]['dns'];
				$data['ip'] = $proxy['interface'][0]['ip'];
				$data['useip'] = $proxy['interface'][0]['useip'];
				$data['port'] = $proxy['interface'][0]['port'];
			}
			else {
				$data['dns'] = 'localhost';
				$data['ip'] = '127.0.0.1';
				$data['useip'] = '1';
				$data['port'] = '10051';
			}
			$data['proxy_hostids'] = zbx_objectValues($proxy['hosts'], 'hostid');
			$data['description'] = $proxy['description'];
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
