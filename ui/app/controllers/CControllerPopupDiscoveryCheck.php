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


/**
 * Controller class containing operations for adding and updating discovery checks.
 */
class CControllerPopupDiscoveryCheck extends CController {

	/**
	 * Default discovery check type.
	 */
	const DEFAULT_TYPE = SVC_FTP;

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'update' =>					'in 1',
			'validate' =>				'in 1',
			'dcheckid' =>				'string',
			'type' =>					'in '.implode(',', array_keys(discovery_check_type2str())),
			'ports' =>					'string|not_empty|db dchecks.ports',
			'snmp_community' =>			'string|not_empty|db dchecks.snmp_community',
			'key_' =>					'string|not_empty|db dchecks.key_',
			'snmp_oid' =>				'string|not_empty|db dchecks.key_',
			'snmpv3_contextname' =>		'string|db dchecks.snmpv3_contextname',
			'snmpv3_securityname' =>	'string|db dchecks.snmpv3_securityname',
			'snmpv3_securitylevel' =>	'db dchecks.snmpv3_securitylevel|in '.implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]),
			'snmpv3_authprotocol' =>	'db dchecks.snmpv3_authprotocol|in '.implode(',', array_keys(getSnmpV3AuthProtocols())),
			'snmpv3_authpassphrase' =>	'string|db dchecks.snmpv3_authpassphrase',
			'snmpv3_privprotocol' =>	'db dchecks.snmpv3_privprotocol|in '.implode(',', array_keys(getSnmpV3PrivProtocols())),
			'snmpv3_privpassphrase' =>	'string|not_empty|db dchecks.snmpv3_privpassphrase'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->hasInput('ports') && !validate_port_list($this->getInput('ports'))) {
			info(_('Incorrect port range.'));
			$ret = false;
		}

		if ($ret && $this->hasInput('type') && $this->getInput('type') == SVC_AGENT) {
			$item_key_parser = new CItemKey();
			if ($item_key_parser->parse($this->getInput('key_')) != CParser::PARSE_SUCCESS) {
				info(_s('Invalid key "%1$s": %2$s.', $this->getInput('key_'), $item_key_parser->getError()));
				$ret = false;
			}
		}

		if (!$ret) {
			$output = [];

			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
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
			if ($params['type'] == SVC_SNMPv1 || $params['type'] == SVC_SNMPv2c || $params['type'] == SVC_SNMPv3) {
				$params['key_'] = $data['snmp_oid'];
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode(['params' => $params])]))->disableView()
			);
			return;
		}

		$output = [
			'title' => _('Discovery check'),
			'params' => $params + DB::getDefaults('dchecks'),
			'update' => $this->getInput('update', 0),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($output));
	}
}
