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

	public function __construct() {
		parent::__construct();

		$fields = array(
			'proxyid' =>		array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
			'host' =>			array(T_ZBX_STR, O_MAND, null,	NOT_EMPTY,	null),
			'status' =>			array(T_ZBX_INT, O_MAND, null,	IN(array(HOST_STATUS_PROXY_ACTIVE,HOST_STATUS_PROXY_PASSIVE)), null),
			'interface' =>		array(T_ZBX_STR, O_MAND, null,	null,		'{status}=='.HOST_STATUS_PROXY_ACTIVE),
			'hosts' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
			'description' =>	array(T_ZBX_STR, O_MAND, null,	null,		null)
		);

		$this->setValidationRules($fields);
	}

	protected function checkPermissions() {
		$dbProxy = API::Proxy()->get(array(
			'proxyids' => $this->getInput('proxyid'),
			'output' => array('hostid')
		));

		if (!$dbProxy) {
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
			'hostids' => $this->getInput('hosts', array()),
			'output' => array('hostid'),
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
		));

		$proxy['proxyid'] = $this->getInput('proxyid');
		$result = API::Proxy()->update($proxy);

		if ($result) {
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_PROXY, '['.$this->getInput['host'].'] ['.reset($result['proxyids']).']');
		}

		$result = DBend($result);

		if ($result) {
			$response = new CControllerResponseRedirect('proxies.php');
			$response->setMessageOk(_('Proxy updated'));
		}
		else {
			$response = new CControllerResponseRedirect('proxies.php?action=proxy.edit&proxyid='.$this->getInput('proxyid'));
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update proxy'));
		}
		return $response;
	}
}
