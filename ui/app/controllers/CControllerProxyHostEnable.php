<?php
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


class CControllerProxyHostEnable extends CController {

	protected function checkInput() {
		$fields = [
			'proxyids' => 'required|array_db hosts.hostid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)) {
			return false;
		}

		$proxies = API::Proxy()->get([
			'proxyids' => $this->getInput('proxyids'),
			'countOutput' => true,
			'editable' => true
		]);

		return ($proxies == count($this->getInput('proxyids')));
	}

	protected function doAction() {
		$hosts = API::Host()->get([
			'output' => ['hostid'],
			'filter' => [
				'proxy_hostid' => $this->getInput('proxyids'),
				'status' => HOST_STATUS_NOT_MONITORED
			]
		]);

		foreach ($hosts as &$host) {
			$host['status'] = HOST_STATUS_MONITORED;
		}
		unset($host);

		$result = $hosts ? (bool) API::Host()->update($hosts) : true;

		$updated = count($hosts);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'proxy.list')
			->setArgument('page', CPagerHelper::loadPage('proxy.list', null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('Host enabled', 'Hosts enabled', $updated));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot enable host', 'Cannot enable hosts', $updated));
		}
		$this->setResponse($response);
	}
}
