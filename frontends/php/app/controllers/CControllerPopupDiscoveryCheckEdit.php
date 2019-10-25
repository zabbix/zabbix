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

	/**
	 * Default check type
	 */
	const DEFAULT_TYPE = SVC_FTP;

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'index' => 'required|int32', // Count of exists checks.
			'update' => 'in 1',
			'validate' => 'in 1',
			'type' => 'in '.implode(',', array_keys(discovery_check_type2str())),

			'dcheckid' => 'string',
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

		if ($ret) {
			if ($this->getInput('update', 0) || $this->getInput('validate', 0)) {
				$ret = $this->validateFormInputs();
			}
		}

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
	 * Validate form fields.
	 *
	 * @return array
	 */
	protected function validateFormInputs() {
		$fields = [
			'ports' => 'not_empty',
			'type' => 'in '.implode(',', array_keys(discovery_check_type2str()))
		];

		switch ($this->getInput('type', self::DEFAULT_TYPE)) {
			case SVC_AGENT:
				$fields['key_'] = 'not_empty';
				break;

			case SVC_SNMPv1:
			case SVC_SNMPv2c:
				$fields['snmp_community'] = 'not_empty';
				$fields['key_'] = 'string|not_empty';
				break;

			case SVC_SNMPv3:
				$fields['key_'] = 'string|not_empty';
				$fields['snmpv3_contextname'] = 'string';
				$fields['snmpv3_securityname'] = 'string';
				$fields['snmpv3_securitylevel'] = 'in '.implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]);

				$snmpv3_securitylevel = getRequest('snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
				if ($snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV
						|| $snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					$fields['snmpv3_authprotocol'] = 'in '.ITEM_AUTHPROTOCOL_MD5.','.ITEM_AUTHPROTOCOL_SHA;
					$fields['snmpv3_authpassphrase'] = 'string';
				}

				if ($snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					$fields['snmpv3_privprotocol'] = 'in '.ITEM_PRIVPROTOCOL_DES.','.ITEM_PRIVPROTOCOL_AES;
					$fields['snmpv3_privpassphrase'] = 'string|not_empty';
				}
				break;

			case SVC_ICMPPING:
				$fields['ports'] = 'in 0';
		}

		$data = [];
		$this->getInputs($data, array_keys($fields));

		$validator = new CNewValidator($data, $fields);
		array_map('info', $validator->getAllErrors());

		if (!validate_port_list($this->getInput('ports'))) {
			info(_('Incorrect port range.'));
			return false;
		}

		return !$validator->isError();
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
