<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

class CControllerHostMassToggle extends CController {

	protected function checkInput() {
		$fields = [
			'action'     => 'required|in host.massenable,host.massdisable',
			'hosts'      => 'required|array_db hosts.hostid',
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected function doAction() {
		$enable = ($this->getAction() === 'host.massenable');
		$status = $enable ? TRIGGER_STATUS_ENABLED : TRIGGER_STATUS_DISABLED;

		$actHosts = API::Host()->get([
			'hostids' => getRequest('hosts'),
			'editable' => true,
			'templated_hosts' => true,
			'output' => ['hostid']
		]);

		$updated = 0;
		$result = false;

		if ($actHosts) {
			foreach ($actHosts as &$host) {
				$host['status'] = $status;
			}
			unset($host);

			$result = (bool) API::Host()->update($actHosts);

			if ($result) {
				uncheckTableRows();
			}

			$updated = count($actHosts);
		}

		$messageSuccess = $enable
			? _n('Host enabled', 'Hosts enabled', $updated)
			: _n('Host disabled', 'Hosts disabled', $updated);
		$messageFailed = $enable
			? _n('Cannot enable host', 'Cannot enable hosts', $updated)
			: _n('Cannot disable host', 'Cannot disable hosts', $updated);

		if ($result) {
			CMessageHelper::setSuccessTitle($messageSuccess);
		}
		else {
			CMessageHelper::setErrorTitle($messageFailed);
		}

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'host.list')
			->setArgument('page', CPagerHelper::loadPage('host.list', null))
		);

		$this->setResponse($response);
	}
}
