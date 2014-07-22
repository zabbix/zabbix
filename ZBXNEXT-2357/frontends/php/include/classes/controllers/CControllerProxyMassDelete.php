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

class CControllerProxyMassDelete extends CController {

	public function __construct() {
		parent::__construct();

		$fields = array(
			'hosts' =>			array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
		);
		$this->setValidationRules($fields);
	}

	protected function checkPermissions() {
// TODO Should be moved to validation rules
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
		DBstart();
		$result = API::Proxy()->delete($this->getInput('hosts'));
		$result = DBend($result);

		$response = new CControllerResponseRedirect('proxies.php');

		if ($result) {
			$response->setMessageOk(_('Proxy deleted'));
		}
		else {
			$response->setMessageError(_('Cannot delete proxy'));
		}
		return $response;
	}
}
