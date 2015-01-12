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
			'proxyid' =>		'fatal|db       hosts.hostid         |required',
			'host' =>			'      db       hosts.host           |required                                        |not_empty',
			'status' =>			'fatal|db       hosts.status         |required                                        |in '.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE,
			'interfaceid' =>    'fatal|db       interface.interfaceid',
			'dns' =>			'      db       interface.dns        |required_if status='.HOST_STATUS_PROXY_PASSIVE,
			'ip' =>				'      db       interface.ip         |required_if status='.HOST_STATUS_PROXY_PASSIVE,
			'useip' =>			'fatal|db       interface.useip      |required_if status='.HOST_STATUS_PROXY_PASSIVE.'|in 0,1',
			'port' =>			'      db       interface.port       |required_if status='.HOST_STATUS_PROXY_PASSIVE,
			'proxy_hostids' =>	'fatal|array_db hosts.hostid',
			'description' =>	'      db       hosts.description    |required'
		);

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=proxy.formedit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update proxy'));
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
		return (bool) API::Proxy()->get(array(
			'output' => array(),
			'proxyids' => $this->getInput('proxyid'),
			'editable' => true
		));
	}

	protected function doAction() {
		$proxy = array(
			'proxyid' => $this->getInput('proxyid'),
			'host' => $this->getInput('host'),
			'status' => $this->getInput('status'),
			'description' => $this->getInput('description')
		);

		if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE) {
			$proxy['interface'] = array(
				'dns' => $this->getInput('dns'),
				'ip' => $this->getInput('ip'),
				'useip' => $this->getInput('useip'),
				'port' => $this->getInput('port')
			);
			if ($this->hasInput('interfaceid')) {
				$proxy['interface']['interfaceid'] = $this->getInput('interfaceid');
			}
		}

		DBstart();

		if ($this->getInput('proxy_hostids', array())) {
			// skip discovered hosts
			$proxy['hosts'] = API::Host()->get(array(
				'output' => array('hostid'),
				'hostids' => $this->getInput('proxy_hostids'),
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
			));
		}

		$result = API::Proxy()->update($proxy);

		if ($result) {
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_PROXY, '['.$this->getInput('host').'] ['.reset($result['proxyids']).']');
		}

		$result = DBend($result);

		if ($result) {
			$response = new CControllerResponseRedirect('zabbix.php?action=proxy.list&uncheck=1');
			$response->setMessageOk(_('Proxy updated'));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=proxy.formedit&proxyid='.$this->getInput('proxyid'));
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update proxy'));
		}
		$this->setResponse($response);
	}
}
