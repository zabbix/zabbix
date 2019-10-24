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


/**
 * Discovery checks popup
 */
class CControllerPopupDiscoveryCheckEdit extends CController {

	const DEFAULT_TYPE = SVC_FTP;

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'index' => 'required|int32',
			'update' => 'in 1',
			'validate' => 'in 1',
			'type' => 'in '.implode(',', array_keys(discovery_check_type2str())),
			'dcheckid' => 'string'
		];

		if (getRequest('update', 0) || getRequest('validate', 0)) {
			$fields += $this->getAdditionallyFields();
		}

		$ret = $this->validateInput($fields);
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

	/**
	 * Get additionally inpuf field rules for validation.
	 *
	 * @return array
	 */
	protected function getAdditionallyFields() {
		$fields = [];

		switch (getRequest('type', SVC_FTP)) {
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
				$fields['ports'] = 'int32|required';
				break;

			case SVC_AGENT:
				$fields['ports'] = 'int32|required';
				$fields['key_'] = 'required|not_empty';
				break;

			case SVC_SNMPv1:
			case SVC_SNMPv2c:
				$fields['ports'] = 'int32|required';
				$fields['snmp_community'] = 'required|not_empty';
				$fields['snmp_oid'] = 'string';
				if (getRequest('validate', 0)) {
					$fields['snmp_oid'] .= '|not_empty|required';
				}
				$fields['key_'] = 'string';
				if (getRequest('update', 0)) {
					$fields['key_'] .= '|not_empty|required';
				}
				break;

			case SVC_SNMPv3:
				$fields['ports'] = 'int32|required';
				$fields['snmp_oid'] = 'string';
				if (getRequest('validate', 0)) {
					$fields['snmp_oid'] .= '|not_empty|required';
				}
				$fields['key_'] = 'string';
				if (getRequest('update', 0)) {
					$fields['key_'] .= '|not_empty|required';
				}
				$fields['snmpv3_securitylevel'] = 'in '.implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]);

				$snmpv3_securitylevel = getRequest('snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
				if ($snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV
						|| $snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					$fields['snmpv3_authprotocol'] = 'in '.ITEM_AUTHPROTOCOL_MD5.','.ITEM_AUTHPROTOCOL_SHA;
					$fields['snmpv3_authpassphrase'] = 'string';
				}

				if ($snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					$fields['snmpv3_privprotocol'] = 'in '.ITEM_PRIVPROTOCOL_DES.','.ITEM_PRIVPROTOCOL_AES;
					$fields['snmpv3_privpassphrase'] = 'required|not_empty';
				}
				break;

			case SVC_ICMPPING:
				$fields['ports'] = 'in 0';
		}

		return $fields;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = array_merge([
			'type' => self::DEFAULT_TYPE,
			'ports' => svc_default_port(self::DEFAULT_TYPE)
		], $this->getInputAll());

		$params = array_intersect_key($data, DB::getSchema('dchecks')['fields']);
		$params['name'] = discovery_check_type2str($data['type']);

		if ($this->getInput('validate', 0)) {
			return $this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson(['params' => $params])]))->disableView()
			);
		}

		$output = [
			'title' => _('Discovery check'),
			'errors' => null,
			'params' => $params,
			'index' => $this->getInput('index'),
			'update' => $this->getInput('update', 0),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($output));
	}
}
