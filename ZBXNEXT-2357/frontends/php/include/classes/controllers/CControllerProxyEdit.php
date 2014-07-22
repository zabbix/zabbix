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

class CControllerProxyEdit extends CController {

	public function __construct() {
		parent::__construct();

		$fields = array(
			'proxyid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
			'host' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
			'status' =>			array(T_ZBX_INT, O_OPT, null,	IN(array(HOST_STATUS_PROXY_ACTIVE,HOST_STATUS_PROXY_PASSIVE)), null),
			'interface' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({status}) && {status}=='.HOST_STATUS_PROXY_ACTIVE),
			'hosts' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
			'description' =>	array(T_ZBX_STR, O_OPT, null,	null,		null)
		);

		$this->setValidationRules($fields);
	}

	protected function checkPermissions() {
		if ($this->hasInput('proxyid')) {
			$dbProxy = API::Proxy()->get(array(
				'proxyids' => $this->getInput('proxyid'),
				'selectHosts' => array('hostid', 'host'),
				'selectInterface' => API_OUTPUT_EXTEND,
				'output' => API_OUTPUT_EXTEND
			));

			if (!$dbProxy) {
				access_deny();
			}
		}
	}

	protected function doAction() {
		if ($this->hasInput('proxyid'))
		{
			$dbProxy = API::Proxy()->get(array(
				'proxyids' => $this->getInput('proxyid'),
				'selectHosts' => array('hostid', 'host'),
				'selectInterface' => API_OUTPUT_EXTEND,
				'output' => API_OUTPUT_EXTEND
			));
			$dbProxy = reset($dbProxy);
		}


		$this->data = array(
			'proxyid' => $this->getInput('proxyid', 0),
			'name' => $this->getInput('host', isset($dbProxy['host']) ? $dbProxy['host'] : ''),
			'status' => $this->getInput('status', isset($dbProxy['status']) ? $dbProxy['status'] : HOST_STATUS_PROXY_ACTIVE),
			'hosts' => $this->getInput('hosts', isset($dbProxy['hosts']) ? zbx_objectValues($dbProxy['hosts'], 'hostid') : array()),
			'interface' => $this->getInput('interface', isset($dbProxy['interface']) ? $dbProxy['interface'] : array()),
//			'proxy' => array(),
			'description' => $this->getInput('description', isset($dbProxy['description']) ? $dbProxy['description'] : '')
		);

		// interface
		if ($this->data['status'] == HOST_STATUS_PROXY_PASSIVE && !$this->data['interface']) {
			$this->data['interface'] = array(
				'dns' => 'localhost',
				'ip' => '127.0.0.1',
				'useip' => 1,
				'port' => '10051'
			);
		}

		// fetch available hosts, skip host prototypes
		$this->data['dbHosts'] = DBfetchArray(DBselect(
			'SELECT h.hostid,h.proxy_hostid,h.name,h.flags'.
			' FROM hosts h'.
			' WHERE h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
				' AND h.flags<>'.ZBX_FLAG_DISCOVERY_PROTOTYPE
		));
		order_result($this->data['dbHosts'], 'name');

		return new CControllerResponseData($this->data);
	}
}
