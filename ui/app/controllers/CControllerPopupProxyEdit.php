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
	 * @var array|null
	 */
	private ?array $proxy = null;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'proxyid' =>	'db proxy.proxyid'
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

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)) {
			return false;
		}

		if ($this->hasInput('proxyid')) {
			$db_proxies = API::Proxy()->get([
				'output' => ['name', 'operating_mode', 'allowed_addresses', 'description', 'tls_connect', 'tls_accept',
					'tls_issuer', 'tls_subject', 'address', 'port', 'custom_timeouts', 'timeout_zabbix_agent',
					'timeout_simple_check', 'timeout_snmp_agent', 'timeout_external_check', 'timeout_db_monitor',
					'timeout_http_agent', 'timeout_ssh_agent', 'timeout_telnet_agent', 'timeout_script', 'compatibility'
				],
				'proxyids' => $this->getInput('proxyid'),
				'editable' => true
			]);

			if (!$db_proxies) {
				return false;
			}

			$this->proxy = $db_proxies[0];
		}

		return true;
	}

	protected function doAction(): void {
		if ($this->proxy !== null) {
			$data = [
				'proxyid' => $this->getInput('proxyid'),
				'version_mismatch' => $this->proxy['compatibility'] == ZBX_PROXY_VERSION_OUTDATED
					|| $this->proxy['compatibility'] == ZBX_PROXY_VERSION_UNSUPPORTED,
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
					'tls_subject' => $this->proxy['tls_subject'],
					'custom_timeouts' => (int) $this->proxy['custom_timeouts']
				]
			];

			$data['form'] += $this->proxy['custom_timeouts'] == ZBX_PROXY_CUSTOM_TIMEOUTS_DISABLED
				? [
					'timeout_zabbix_agent' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_ZABBIX_AGENT),
					'timeout_simple_check' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SIMPLE_CHECK),
					'timeout_snmp_agent' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SNMP_AGENT),
					'timeout_external_check' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_EXTERNAL_CHECK),
					'timeout_db_monitor' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_DB_MONITOR),
					'timeout_http_agent' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_HTTP_AGENT),
					'timeout_ssh_agent' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SSH_AGENT),
					'timeout_telnet_agent' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_TELNET_AGENT),
					'timeout_script' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SCRIPT)
				]
				: [
					'timeout_zabbix_agent' => $this->proxy['timeout_zabbix_agent'],
					'timeout_simple_check' => $this->proxy['timeout_simple_check'],
					'timeout_snmp_agent' => $this->proxy['timeout_snmp_agent'],
					'timeout_external_check' => $this->proxy['timeout_external_check'],
					'timeout_db_monitor' => $this->proxy['timeout_db_monitor'],
					'timeout_http_agent' => $this->proxy['timeout_http_agent'],
					'timeout_ssh_agent' => $this->proxy['timeout_ssh_agent'],
					'timeout_telnet_agent' => $this->proxy['timeout_telnet_agent'],
					'timeout_script' => $this->proxy['timeout_script']
				];
		}
		else {
			$data = [
				'proxyid' => null,
				'version_mismatch' => false,
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
					'tls_subject' => DB::getDefault('proxy', 'tls_subject'),
					'custom_timeouts' => (int) DB::getDefault('proxy', 'custom_timeouts'),
					'timeout_zabbix_agent' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_ZABBIX_AGENT),
					'timeout_simple_check' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SIMPLE_CHECK),
					'timeout_snmp_agent' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SNMP_AGENT),
					'timeout_external_check' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_EXTERNAL_CHECK),
					'timeout_db_monitor' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_DB_MONITOR),
					'timeout_http_agent' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_HTTP_AGENT),
					'timeout_ssh_agent' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SSH_AGENT),
					'timeout_telnet_agent' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_TELNET_AGENT),
					'timeout_script' => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SCRIPT)
				]
			];
		}

		$data['user'] = [
			'debug_mode' => $this->getDebugMode(),
			'can_edit_global_timeouts' => $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
