<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

	protected function init() {
		$this->disableCsrfValidation();
	}

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
				'output' => ['name', 'operating_mode', 'allowed_addresses', 'description', 'tls_connect', 'tls_accept',
					'tls_issuer', 'tls_subject', 'address', 'port'
				],
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
		if ($this->proxy !== null) {
			$data = [
				'proxyid' => $this->getInput('proxyid'),
				'form' => [
					'name' => $this->proxy['name'],
					'operating_mode' => (int) $this->proxy['operating_mode'],
					'address' => $this->proxy['operating_mode'] == PROXY_OPERATING_MODE_PASSIVE
						? $this->proxy['address']
						: DB::getDefault('proxy', 'address'),
					'port' => $this->proxy['operating_mode'] == PROXY_OPERATING_MODE_PASSIVE
						? $this->proxy['port']
						: DB::getDefault('proxy', 'port'),
					'allowed_addresses' => $this->proxy['allowed_addresses'],
					'description' => $this->proxy['description'],
					'tls_connect' => (int) $this->proxy['tls_connect'],
					'tls_accept' => (int) $this->proxy['tls_accept'],
					'tls_psk_identity' => DB::getDefault('proxy', 'tls_psk_identity'),
					'tls_psk' => DB::getDefault('proxy', 'tls_psk'),
					'tls_issuer' => $this->proxy['tls_issuer'],
					'tls_subject' => $this->proxy['tls_subject']
				]
			];
		}
		else {
			$data = [
				'proxyid' => null,
				'form' => [
					'name' => DB::getDefault('proxy', 'name'),
					'operating_mode' => (int) DB::getDefault('proxy', 'operating_mode'),
					'allowed_addresses' => DB::getDefault('proxy', 'allowed_addresses'),
					'address' => DB::getDefault('proxy', 'address'),
					'port' => DB::getDefault('proxy', 'port'),
					'description' => DB::getDefault('proxy', 'description'),
					'tls_connect' => (int) DB::getDefault('proxy', 'tls_connect'),
					'tls_accept' => (int) DB::getDefault('proxy', 'tls_connect'),
					'tls_psk_identity' => DB::getDefault('proxy', 'tls_psk_identity'),
					'tls_psk' => DB::getDefault('proxy', 'tls_psk_identity'),
					'tls_issuer' => DB::getDefault('proxy', 'tls_issuer'),
					'tls_subject' => DB::getDefault('proxy', 'tls_subject')
				]
			];
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$this->setResponse(new CControllerResponseData($data));
	}
}
