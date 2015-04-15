<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CControllerProxyHostDisable extends CController {

	protected function checkInput() {
		$fields = array(
			'proxyids' =>	'required|array_db hosts.hostid'
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
			'proxyids' => $this->getInput('proxyids'),
			'countOutput' => true,
			'editable' => true
		));

		return ($proxies == count($this->getInput('proxyids')));
	}

	protected function doAction() {
		$hosts = API::Host()->get(array(
			'output' => array('hostid'),
			'filter' => array(
				'proxy_hostid' => $this->getInput('proxyids'),
				'status' => HOST_STATUS_MONITORED
			)
		));

		foreach ($hosts as &$host) {
			$host['status'] = HOST_STATUS_NOT_MONITORED;
		}
		unset($host);

		$result = API::Host()->update($hosts);

		$updated = count($hosts);

		$response = new CControllerResponseRedirect('zabbix.php?action=proxy.list&uncheck=1');

		if ($result) {
			$response->setMessageOk(_n('Host disabled', 'Hosts disabled', $updated));
		}
		else {
			$response->setMessageError(_n('Cannot disable host', 'Cannot disable hosts', $updated));
		}
		$this->setResponse($response);
	}
}
