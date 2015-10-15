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


class CControllerProxyEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'proxyid' =>		'db       hosts.hostid',
			'host' =>			'db       hosts.host',
			'status' =>			'db       hosts.status         |in '.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE,
			'interfaceid' =>	'db       interface.interfaceid',
			'dns' =>			'db       interface.dns',
			'ip' =>				'db       interface.ip',
			'useip' =>			'db       interface.useip      |in 0,1',
			'port' =>			'db       interface.port',
			'proxy_hostids' =>	'array_db hosts.hostid',
			'description' =>	'db       hosts.description',
			'tls_connect' => 	'db       hosts.tls_connect    |in '.HOST_ENCRYPTION_NONE.','.HOST_ENCRYPTION_PSK.','.
				HOST_ENCRYPTION_CERTIFICATE,
			'tls_accept' => 	'db       hosts.tls_accept     |in 0,'.HOST_ENCRYPTION_NONE.','.HOST_ENCRYPTION_PSK.','.
				(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK).','.
				HOST_ENCRYPTION_CERTIFICATE.','.
				(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_CERTIFICATE).','.
				(HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE).','.
				(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE),
			'tls_issuer' => 	'db       hosts.tls_issuer',
			'tls_psk' =>		'db       hosts.tls_psk',
			'tls_psk_identity'=>'db       hosts.tls_psk_identity',
			'tls_subject' => 	'db       hosts.tls_subject',
			'form_refresh' =>	'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		if ($this->hasInput('proxyid')) {
			return (bool) API::Proxy()->get([
				'output' => [],
				'proxyids' => $this->getInput('proxyid'),
				'editable' => true
			]);
		}

		return true;
	}

	protected function doAction() {
		// default values
		$data = [
			'sid' => $this->getUserSID(),
			'proxyid' => 0,
			'host' => '',
			'status' => HOST_STATUS_PROXY_ACTIVE,
			'dns' => 'localhost',
			'ip' => '127.0.0.1',
			'useip' => '1',
			'port' => '10051',
			'proxy_hostids' => [],
			'description' => '',
			'tls_accept' => HOST_ENCRYPTION_NONE,
			'tls_connect' => HOST_ENCRYPTION_NONE,
			'tls_issuer' => '',
			'tls_psk' => '',
			'tls_psk_identity' => '',
			'tls_subject' => '',
			'form_refresh' => 0
		];

		// get values from the dabatase
		if ($this->hasInput('proxyid')) {
			$data['proxyid'] = $this->getInput('proxyid');

			$proxies = API::Proxy()->get([
				'output' => ['host', 'status', 'description', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject',
					'tls_psk_identity', 'tls_psk'
				],
				'selectHosts' => ['hostid'],
				'selectInterface' => ['interfaceid', 'dns', 'ip', 'useip', 'port'],
				'proxyids' => $data['proxyid']
			]);
			$proxy = $proxies[0];

			$data['host'] = $proxy['host'];
			$data['status'] = $proxy['status'];
			$data['tls_accept'] = $proxy['tls_accept'];
			$data['tls_connect'] = $proxy['tls_connect'];
			$data['tls_issuer'] = $proxy['tls_issuer'];
			$data['tls_psk'] = $proxy['tls_psk'];
			$data['tls_psk_identity'] = $proxy['tls_psk_identity'];
			$data['tls_subject'] = $proxy['tls_subject'];
			if ($data['status'] == HOST_STATUS_PROXY_PASSIVE) {
				$data['interfaceid'] = $proxy['interface']['interfaceid'];
				$data['dns'] = $proxy['interface']['dns'];
				$data['ip'] = $proxy['interface']['ip'];
				$data['useip'] = $proxy['interface']['useip'];
				$data['port'] = $proxy['interface']['port'];
			}
			$data['proxy_hostids'] = zbx_objectValues($proxy['hosts'], 'hostid');
			$data['description'] = $proxy['description'];
		}

		// overwrite with input variables
		$data['host'] = $this->getInput('host', $data['host']);
		$data['status'] = $this->getInput('status', $data['status']);
		$data['dns'] = $this->getInput('dns', $data['dns']);
		$data['ip'] = $this->getInput('ip', $data['ip']);
		$data['useip'] = $this->getInput('useip', $data['useip']);
		$data['port'] = $this->getInput('port', $data['port']);
		$data['proxy_hostids'] = $this->getInput('proxy_hostids', $data['proxy_hostids']);
		$data['description'] = $this->getInput('description', $data['description']);
		$data['tls_accept'] = $this->getInput('tls_accept', $data['tls_accept']);
		$data['tls_connect'] = $this->getInput('tls_connect', $data['tls_connect']);
		$data['tls_issuer'] = $this->getInput('tls_issuer', $data['tls_issuer']);
		$data['tls_psk'] = $this->getInput('tls_psk', $data['tls_psk']);
		$data['tls_psk_identity'] = $this->getInput('tls_psk_identity', $data['tls_psk_identity']);
		$data['tls_subject'] = $this->getInput('tls_subject', $data['tls_subject']);
		$data['form_refresh'] = $this->getInput('form_refresh', $data['form_refresh']);

		if ($data['status'] == HOST_STATUS_PROXY_PASSIVE && $this->hasInput('interfaceid')) {
			$data['interfaceid'] = $this->getInput('interfaceid');
		}

		// fetch available hosts, skip host prototypes
		$data['all_hosts'] = DBfetchArray(DBselect(
			'SELECT h.hostid,h.proxy_hostid,h.name,h.flags'.
			' FROM hosts h'.
			' WHERE h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
				' AND h.flags<>'.ZBX_FLAG_DISCOVERY_PROTOTYPE
		));
		order_result($data['all_hosts'], 'name');

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of proxies'));
		$this->setResponse($response);
	}
}
