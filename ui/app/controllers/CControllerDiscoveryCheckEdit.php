<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Controller class containing operations for adding and updating discovery checks.
 */
class CControllerDiscoveryCheckEdit extends CController {

	/**
	 * Default discovery check type.
	 */
	const DEFAULT_TYPE = SVC_FTP;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'update' =>					'in 1',
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
			'snmpv3_privpassphrase' =>	'string|not_empty|db dchecks.snmpv3_privpassphrase',
			'allow_redirect' =>			'db dchecks.allow_redirect|in 0,1'
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
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY);
	}

	protected function doAction(): void {
		$data = array_merge([
			'type' => self::DEFAULT_TYPE,
			'ports' => svc_default_port(self::DEFAULT_TYPE)
		], $this->getInputAll());

		$params = array_intersect_key($data, DB::getSchema('dchecks')['fields']);
		$params['name'] = discovery_check_type2str($data['type']);

		$output = [
			'title' => _('Discovery check'),
			'dcheckid' => $data['dcheckid'],
			'params' => $params + DB::getDefaults('dchecks'),
			'update' => $this->getInput('update', 0),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($output));
	}
}
