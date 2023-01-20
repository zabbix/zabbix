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


class CControllerProxyUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'proxyid' =>				'required|id',
			'host' =>					'required|string|not_empty',
			'status' =>					'required|in '.implode(',', [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]),
			'ip' =>						'string',
			'dns' =>					'string',
			'useip' =>					'in '.implode(',', [INTERFACE_USE_IP, INTERFACE_USE_DNS]),
			'port' =>					'string',
			'proxy_address' =>			'string',
			'description' =>			'string',
			'tls_connect' =>			'in '.implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE]),
			'tls_accept_none' =>		'in 1',
			'tls_accept_psk' =>			'in 1',
			'tls_accept_certificate' =>	'in 1',
			'tls_psk_identity' =>		'string',
			'tls_psk' =>				'string',
			'tls_issuer' =>				'string',
			'tls_subject' =>			'string',
			'update_psk' =>				'required|bool'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			switch ($this->getInput('status')) {
				case HOST_STATUS_PROXY_ACTIVE:
					if (!$this->hasInput('tls_accept_none') && !$this->hasInput('tls_accept_psk')
							&& !$this->hasInput('tls_accept_certificate')) {
						info(_s('Incorrect value for field "%1$s": %2$s.', _('Connections from proxy'),
							_('cannot be empty')
						));

						$ret = false;
					}

					break;

				case HOST_STATUS_PROXY_PASSIVE:
					if ($this->getInput('useip', INTERFACE_USE_IP) == INTERFACE_USE_IP
							&& $this->getInput('ip', '') === '') {
						info(_s('Incorrect value for field "%1$s": %2$s.', _('IP address'), _('cannot be empty')));

						$ret = false;
					}

					if ($this->getInput('useip', INTERFACE_USE_IP) == INTERFACE_USE_DNS
							&& $this->getInput('dns', '') === '') {
						info(_s('Incorrect value for field "%1$s": %2$s.', _('DNS name'), _('cannot be empty')));

						$ret = false;
					}

					if ($this->getInput('port', '') === '') {
						info(_s('Incorrect value for field "%1$s": %2$s.', _('Port'), _('cannot be empty')));

						$ret = false;
					}

					break;
			}

			if ($this->getInput('update_psk')) {
				if (($this->getInput('status') == HOST_STATUS_PROXY_ACTIVE && $this->hasInput('tls_accept_psk'))
						|| ($this->getInput('status') == HOST_STATUS_PROXY_PASSIVE
							&& $this->getInput('tls_connect', 0) == HOST_ENCRYPTION_PSK)) {
					if ($this->getInput('tls_psk_identity', '') === '') {
						info(_s('Incorrect value for field "%1$s": %2$s.', _('PSK identity'), _('cannot be empty')));

						$ret = false;
					}

					if ($this->getInput('tls_psk', '') === '') {
						info(_s('Incorrect value for field "%1$s": %2$s.', _('PSK'), _('cannot be empty')));

						$ret = false;
					}
				}
			}
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update proxy'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
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

		$this->getInputs($proxy, ['proxyid', 'host', 'status', 'description', 'tls_connect', 'tls_psk_identity',
			'tls_psk', 'tls_issuer', 'tls_subject'
		]);

		switch ($this->getInput('status')) {
			case HOST_STATUS_PROXY_ACTIVE:
				$proxy['proxy_address'] = $this->getInput('proxy_address', '');

				$proxy['tls_accept'] = ($this->hasInput('tls_accept_none') ? HOST_ENCRYPTION_NONE : 0)
					| ($this->hasInput('tls_accept_psk') ? HOST_ENCRYPTION_PSK : 0)
					| ($this->hasInput('tls_accept_certificate') ? HOST_ENCRYPTION_CERTIFICATE : 0);

				break;

			case HOST_STATUS_PROXY_PASSIVE:
				$proxy['interface'] = [];

				$this->getInputs($proxy['interface'], ['dns', 'ip', 'useip', 'port']);

				break;
		}

		$result = API::Proxy()->update($proxy);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Proxy updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update proxy'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
