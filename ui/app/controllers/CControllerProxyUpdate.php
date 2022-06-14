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


class CControllerProxyUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'proxyid' =>			'fatal|required|db hosts.hostid',
			'host' =>				'db hosts.host',
			'status' =>				'db hosts.status|in '.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE,
			'dns' =>				'db interface.dns',
			'ip' =>					'db interface.ip',
			'useip' =>				'db interface.useip|in 0,1',
			'port' =>				'db interface.port',
			'proxy_address' =>		'db hosts.proxy_address',
			'description' =>		'db hosts.description',
			'tls_connect' =>		'db hosts.tls_connect|in '.HOST_ENCRYPTION_NONE.','.HOST_ENCRYPTION_PSK.','.
				HOST_ENCRYPTION_CERTIFICATE,
			'tls_accept' =>			'db hosts.tls_accept|in 0,'.HOST_ENCRYPTION_NONE.','.HOST_ENCRYPTION_PSK.','.
				(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK).','.
				HOST_ENCRYPTION_CERTIFICATE.','.
				(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_CERTIFICATE).','.
				(HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE).','.
				(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE),
			'tls_psk_identity' =>	'db hosts.tls_psk_identity',
			'tls_psk' =>			'db hosts.tls_psk',
			'psk_edit_mode' =>		'in 0,1',
			'tls_issuer' =>			'db hosts.tls_issuer',
			'tls_subject' =>		'db hosts.tls_subject',
			'form_refresh' =>		'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=proxy.edit');
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot update proxy'));
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
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)) {
			return false;
		}

		return (bool) API::Proxy()->get([
			'output' => [],
			'proxyids' => $this->getInput('proxyid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$proxy = [];

		$this->getInputs($proxy, ['proxyid', 'host', 'status', 'description', 'tls_connect', 'tls_accept', 'tls_issuer',
			'tls_subject', 'tls_psk_identity', 'tls_psk'
		]);

		if ($this->getInput('status', HOST_STATUS_PROXY_ACTIVE) == HOST_STATUS_PROXY_PASSIVE) {
			$proxy['interface'] = [];
			$this->getInputs($proxy['interface'], ['dns', 'ip', 'useip', 'port']);
		}
		else {
			$proxy['proxy_address'] = $this->getInput('proxy_address', '');
		}

		DBstart();

		$result = API::Proxy()->update($proxy);

		$result = DBend($result);

		if ($result) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'proxy.list')
				->setArgument('page', CPagerHelper::loadPage('proxy.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('Proxy updated'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'proxy.edit')
				->setArgument('proxyid', $this->getInput('proxyid'))
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update proxy'));
		}
		$this->setResponse($response);
	}
}
