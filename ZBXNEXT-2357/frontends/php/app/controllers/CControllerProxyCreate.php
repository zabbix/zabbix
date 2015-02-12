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


class CControllerProxyCreate extends CController {

	protected function checkInput() {
		$fields = array(
			'host' =>			'db       hosts.host',
			'status' =>			'db       hosts.status     |in '.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE,
			'dns' =>			'db       interface.dns',
			'ip' =>				'db       interface.ip',
			'useip' =>			'db       interface.useip  |in 0,1',
			'port' =>			'db       interface.port',
			'proxy_hostids' =>	'array_db hosts.hostid',
			'description' =>	'db       hosts.description'
		);

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=proxy.edit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot add proxy'));
					$this->setResponse($response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$proxy = array();

		$this->getInputs($proxy, array('host', 'description', 'status'));

		if ($this->getInput('status', HOST_STATUS_PROXY_ACTIVE) == HOST_STATUS_PROXY_PASSIVE) {
			$proxy['interface'] = array();
			$this->getInputs($proxy['interface'], array('dns', 'ip', 'useip', 'port'));
		}

		DBstart();

		if ($this->hasInput('proxy_hostids')) {
			// skip discovered hosts
			$proxy['hosts'] = API::Host()->get(array(
				'output' => array('hostid'),
				'hostids' => $this->getInput('proxy_hostids'),
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
			));
		}

		$result = API::Proxy()->create(array($proxy));

		if ($result) {
			add_audit(AUDIT_ACTION_ADD, AUDIT_RESOURCE_PROXY,
				'['.$this->getInput('host', '').'] ['.reset($result['proxyids']).']'
			);
		}

		$result = DBend($result);

		if ($result) {
			$response = new CControllerResponseRedirect('zabbix.php?action=proxy.list&uncheck=1');
			$response->setMessageOk(_('Proxy added'));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=proxy.edit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot add proxy'));
		}
		$this->setResponse($response);
	}
}
