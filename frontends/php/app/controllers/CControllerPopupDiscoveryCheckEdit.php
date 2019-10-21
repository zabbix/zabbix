<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

class CControllerPopupDiscoveryCheckEdit extends CController {

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'update' =>					'in 0,1',
			'refresh' =>				'in 0,1',
			'index' =>					'required|int32',
			'types' =>					'array',

			'dcheckid' =>				'',
			'type' =>					'db dchecks.type|in '.implode(',', array_keys(discovery_check_type2str())),
			'ports' =>					'db dchecks.ports',
			'snmp_community' =>			'db dchecks.snmp_community',
			'key_' =>					'db dchecks.key_',
			'snmpv3_contextname' =>		'db dchecks.snmpv3_contextname',
			'snmpv3_securityname' =>	'db dchecks.snmpv3_securityname',
			'snmpv3_securitylevel' =>	'db dchecks.snmpv3_securitylevel',
			'snmpv3_authprotocol' =>	'db dchecks.snmpv3_authprotocol',
			'snmpv3_authpassphrase' =>	'db dchecks.snmpv3_authpassphrase',
			'snmpv3_privprotocol' =>	'db dchecks.snmpv3_privprotocol',
			'snmpv3_privpassphrase' =>	'db dchecks.snmpv3_privpassphrase'
		];

		$ret = $this->validateInput($fields);

		$ret = ($ret && $this->getInput('refresh', 0)) ? $this->checkInputByType() : $ret;

		if (!$ret) {
			$output = [];

			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkInputByType() {
		$fields = [];

		switch ($this->getInput('type', 0)) {
			case SVC_SSH:
			case SVC_LDAP:
			case SVC_SMTP:
			case SVC_FTP:
			case SVC_HTTP:
			case SVC_POP:
			case SVC_NNTP:
			case SVC_IMAP:
			case SVC_TCP:
			case SVC_HTTPS:
			case SVC_TELNET:
				$fields['ports'] =						'required';
				break;

			case SVC_AGENT:
				$fields['ports'] =						'required';
				$fields['key_'] =						'required';
				break;

			case SVC_SNMPv1:
			case SVC_SNMPv2c:
				$fields['ports'] =						'required';
				$fields['snmp_community'] =				'required';
				$fields['snmp_oid'] =					'required';
				break;

			case SVC_SNMPv3:
				$fields['ports'] =						'required';
				$fields['key_'] =						'required';
				$fields['snmpv3_securitylevel'] =		'in '.ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV.','.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.','.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV;

				$snmpv3_securitylevel = $this->getInput('snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
				if ($snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV
						|| $snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					$fields['snmpv3_authprotocol'] =	'in '.ITEM_AUTHPROTOCOL_MD5.','.ITEM_AUTHPROTOCOL_SHA;
				}

				if ($snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					$fields['snmpv3_privprotocol'] = 	'in '.ITEM_PRIVPROTOCOL_DES.','.ITEM_PRIVPROTOCOL_AES;
					$fields['snmpv3_privpassphrase'] =	'required';
				}
				break;

			case SVC_ICMPPING:
				$fields['ports'] =						'in 0';
		}

		$this->getInputs($data, array_keys($fields));

		return !(new CNewValidator($data, $fields))->getAllErrors();
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = $this->getInputAll() + [
			'update' => 0,
			'type' => SVC_FTP,
			'ports' => svc_default_port(SVC_FTP)
		];

		$params = array_intersect_key($data, DB::getSchema('dchecks')['fields']);
		$params['name'] = discovery_check_type2str($data['type']);

		$output = [
			'title' => _('Discovery check'),
			'errors' => hasErrorMesssages() ? getMessages() : null,
			'params' => $params,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($output + $data));
	}
}
