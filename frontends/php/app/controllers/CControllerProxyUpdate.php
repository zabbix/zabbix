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

class CControllerProxyUpdate extends CController {
	protected function checkInput() {
		$fields = array(
			'proxyid' =>			'fatal|db:hosts.hostid      |required',
			'host' =>				'      db:hosts.host        |required|not_empty',
			'status' =>				'fatal|db:hosts.status      |required|db:hosts.status|in_int:'.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE,
			'interface' =>			'      array                |required_if:status,'.HOST_STATUS_PROXY_ACTIVE,
			'interface/dns' =>		'      db:interface.dns     |required_if:status,'.HOST_STATUS_PROXY_ACTIVE,
			'interface/ip' =>		'      db:interface.ip      |required_if:status,'.HOST_STATUS_PROXY_ACTIVE.'|ip',
			'interface/useip' =>	'fatal|db:interface:useip   |required_if:status,'.HOST_STATUS_PROXY_ACTIVE.'|in_int:1',
			'proxy_hostids' =>		'fatal|array_db:hosts.hostid',
			'description' =>		'      db:hosts.description |required'
		);

		$result = $this->validateInput($fields);

		if (!$result) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('proxies.php?action=proxy.formedit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update proxy'));
					$this->setResponse($response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $result;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			access_deny();
		}

		$proxies = API::Proxy()->get(array(
			'proxyids' => $this->getInput('proxyid'),
			'output' => array()
		));

		if (!$proxies) {
			access_deny();
		}
	}

	protected function doAction() {
		$proxy = array(
			'host' => $this->getInput('host'),
			'status' => $this->getInput('status'),
			'interface' => $this->getInput('interface'),
			'description' => $this->getInput('description')
		);

		DBstart();

		// skip discovered hosts
		$proxy['hosts'] = API::Host()->get(array(
			'hostids' => $this->getInput('proxy_hostids', array()),
			'output' => array('hostid'),
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
		));

		$proxy['proxyid'] = $this->getInput('proxyid');
		$result = API::Proxy()->update($proxy);

		if ($result) {
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_PROXY, '['.$this->getInput('host').'] ['.reset($result['proxyids']).']');
		}

		$result = DBend($result);

		if ($result) {
			$response = new CControllerResponseRedirect('proxies.php?action=proxy.list&uncheck=1');
			$response->setMessageOk(_('Proxy updated'));
		}
		else {
			$response = new CControllerResponseRedirect('proxies.php?action=proxy.formedit&proxyid='.$this->getInput('proxyid'));
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update proxy'));
		}
		$this->setResponse($response);
	}
}
