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

class CControllerProxyMassDisable extends CController {

	public function __construct() {
		parent::__construct();

		$fields = array(
			'hosts' =>			array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null)
		);
		$this->setValidationRules($fields);
	}

	protected function checkPermissions() {
// TODO Should be checked in check_fields
		if (!is_array($this->getInput('hosts'))) {
			access_deny();
		}
		else {
			$dbProxyChk = API::Proxy()->get(array(
				'proxyids' => $this->getInput('hosts'),
				'selectHosts' => array('hostid', 'host'),
				'selectInterface' => API_OUTPUT_EXTEND,
				'countOutput' => true
			));
			if ($dbProxyChk != count($this->getInput('hosts'))) {
				access_deny();
			}
		}
	}

	protected function doAction() {
		$result = true;
		$hosts = $this->getInput('hosts', array());

		DBstart();

		$this->data['updatedHosts'] = 0;
		foreach ($hosts as $hostId) {

// TODO Replace with API calls
			$dbHosts = DBselect(
				'SELECT h.hostid,h.status FROM hosts h WHERE h.proxy_hostid='.zbx_dbstr($hostId)
			);

			while ($dbHost = DBfetch($dbHosts)) {
				$oldStatus = $dbHost['status'];
				$this->data['updatedHosts']++;

				if ($oldStatus == HOST_STATUS_NOT_MONITORED) {
					continue;
				}

// TODO Replace with API call
				$result &= updateHostStatus($dbHost['hostid'], HOST_STATUS_NOT_MONITORED);

// TODO Is it correct?
				if (!$result) {
					continue;
				}
			}
		}

// Why $hosts? Also $hosts may be set to empty array()
		$result = DBend($result && $hosts);

		$response = new CControllerResponseRedirect('proxies.php');

		if ($result) {
			$response->setMessageOk(_n('Host disabled', 'Hosts disabled', $this->data['updatedHosts']));
		}
		else {
			$response->setMessageError(_n('Cannot disable host', 'Cannot disable hosts', $this->data['updatedHosts']));
		}
		return $response;
	}
}
