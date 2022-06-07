<?php declare(strict_types = 0);
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


class CControllerPopupProxyEdit extends CController {

	/**
	 * @var array
	 */
	private $proxy;

	protected function checkInput(): bool {
		$fields = [
			'proxyid' => 'id'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)) {
			return false;
		}

		if ($this->hasInput('proxyid')) {
			$this->proxy = API::Proxy()->get([
				'output' => ['host', 'status', 'proxy_address', 'description', 'tls_connect', 'tls_accept',
					'tls_issuer', 'tls_subject'
				],
				'selectInterface' => ['dns', 'ip', 'useip', 'port'],
				'proxyids' => $this->getInput('proxyid'),
				'editable' => true
			]);

			if (!$this->proxy) {
				return false;
			}

			$this->proxy = $this->proxy[0];
		}

		return true;
	}

	protected function doAction(): void {
		$default_interface = [
			'dns' => 'localhost',
			'ip' => '127.0.0.1',
			'useip' => '1',
			'port' => '10051'
		];

		if ($this->proxy !== null) {
			$data = [
				'proxyid' => $this->getInput('proxyid'),
				'form' => [
					'host' => $this->proxy['host'],
					'status' => (int) $this->proxy['status'],
					'interface' => $this->proxy['status'] == HOST_STATUS_PROXY_PASSIVE
						? [
							'dns' => $this->proxy['interface']['dns'],
							'ip' => $this->proxy['interface']['ip'],
							'useip' => $this->proxy['interface']['useip'],
							'port' => $this->proxy['interface']['port']
						]
						: $default_interface,
					'proxy_address' => $this->proxy['proxy_address'],
					'description' => $this->proxy['description'],
					'tls_connect' => (int) $this->proxy['tls_connect'],
					'tls_accept' => (int) $this->proxy['tls_accept'],
					'tls_psk_identity' => '',
					'tls_psk' => '',
					'tls_issuer' => $this->proxy['tls_issuer'],
					'tls_subject' => $this->proxy['tls_subject']
				]
			];
		}
		else {
			$data = [
				'proxyid' => null,
				'form' => [
					'host' => '',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'interface' => $default_interface,
					'proxy_address' => '',
					'description' => '',
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_psk_identity' => '',
					'tls_psk' => '',
					'tls_issuer' => '',
					'tls_subject' => ''
				]
			];
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$this->setResponse(new CControllerResponseData($data));
	}
}
