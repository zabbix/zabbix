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


class CControllerProxyCreate extends CController {

	/**
	 * @var array
	 */
	private $clone_proxy;

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'name' =>					'required|string|not_empty',
			'operating_mode' =>			'required|in '.implode(',', [PROXY_OPERATING_MODE_ACTIVE, PROXY_OPERATING_MODE_PASSIVE]),
			'address' =>				'string',
			'port' =>					'string',
			'allowed_addresses' =>		'string',
			'description' =>			'string',
			'tls_connect' =>			'in '.implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE]),
			'tls_accept_none' =>		'in 1',
			'tls_accept_psk' =>			'in 1',
			'tls_accept_certificate' =>	'in 1',
			'tls_psk_identity' =>		'string',
			'tls_psk' =>				'string',
			'tls_issuer' =>				'string',
			'tls_subject' =>			'string',
			'clone_proxyid' =>			'id',
			'clone_psk' =>				'required|bool'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			switch ($this->getInput('operating_mode')) {
				case PROXY_OPERATING_MODE_ACTIVE:
					if (!$this->hasInput('tls_accept_none') && !$this->hasInput('tls_accept_psk')
							&& !$this->hasInput('tls_accept_certificate')) {
						info(_s('Incorrect value for field "%1$s": %2$s.', _('Connections from proxy'),
							_('cannot be empty')
						));

						$ret = false;
					}

					break;

				case PROXY_OPERATING_MODE_PASSIVE:
					if ($this->getInput('address', '')	== '') {
						info(
							_s('Incorrect value for field "%1$s": %2$s.', _('Address'), _('cannot be empty'))
						);

						$ret = false;
					}

					if ($this->getInput('port', '') === '') {
						info(_s('Incorrect value for field "%1$s": %2$s.', _('Port'), _('cannot be empty')));

						$ret = false;
					}

					break;
			}

			if (!$this->getInput('clone_psk')) {
				if (($this->getInput('operating_mode') == PROXY_OPERATING_MODE_ACTIVE && $this->hasInput('tls_accept_psk'))
						|| ($this->getInput('operating_mode') == PROXY_OPERATING_MODE_PASSIVE
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

			if ($this->getInput('clone_psk') && $this->getInput('clone_proxyid', '') === '') {
				$ret = false;
			}
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot add proxy'),
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

		if ($this->getInput('clone_psk')) {
			$this->clone_proxy = API::Proxy()->get([
				'output' => ['tls_psk_identity', 'tls_psk'],
				'proxyids' => $this->getInput('clone_proxyid')
			]);

			if (!$this->clone_proxy) {
				return false;
			}

			$this->clone_proxy = $this->clone_proxy[0];
		}

		return true;
	}

	protected function doAction() {
		$proxy = [];

		$this->getInputs($proxy, ['name', 'operating_mode', 'description', 'tls_connect', 'tls_psk_identity',
			'tls_psk', 'tls_issuer', 'tls_subject'
		]);

		switch ($this->getInput('operating_mode')) {
			case PROXY_OPERATING_MODE_ACTIVE:
				$proxy['allowed_addresses'] = $this->getInput('allowed_addresses', '');

				$proxy['tls_accept'] = ($this->hasInput('tls_accept_none') ? HOST_ENCRYPTION_NONE : 0)
					| ($this->hasInput('tls_accept_psk') ? HOST_ENCRYPTION_PSK : 0)
					| ($this->hasInput('tls_accept_certificate') ? HOST_ENCRYPTION_CERTIFICATE : 0);

				if ($this->getInput('clone_psk') && $this->hasInput('tls_accept_psk')) {
					$proxy['tls_psk_identity'] = $this->clone_proxy['tls_psk_identity'];
					$proxy['tls_psk'] = $this->clone_proxy['tls_psk'];
				}

				break;

			case PROXY_OPERATING_MODE_PASSIVE:
				$proxy['address'] = $this->getInput('address','');
				$proxy['port'] = $this->getInput('port','');

				if ($this->getInput('clone_psk') && $this->getInput('tls_connect', 0) == HOST_ENCRYPTION_PSK) {
					$proxy['tls_psk_identity'] = $this->clone_proxy['tls_psk_identity'];
					$proxy['tls_psk'] = $this->clone_proxy['tls_psk'];
				}

				break;
		}

		$result = API::Proxy()->create($proxy);

		$output = $result
			? ['success' => ['title' => _('Proxy added')]]
			: ['error' => [
				'title' => _('Cannot add proxy'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			]];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
