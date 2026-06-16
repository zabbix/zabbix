<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerDiscoveryCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {

		$api_uniq = [
			'drule.get', ['name' => '{name}']
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'name' => ['db drules.name', 'required', 'not_empty'],
			'discovery_by' => ['integer', 'in' => [ZBX_DISCOVERY_BY_SERVER, ZBX_DISCOVERY_BY_PROXY]],
			'proxyid' => ['db drules.proxyid', 'required',
				'when' => ['discovery_by', 'in' => [ZBX_DISCOVERY_BY_PROXY]]
			],
			'iprange' => ['db drules.iprange', 'required', 'not_empty'],
			'delay' => ['db drules.delay', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, [
					'min' => 1,
					'max' => SEC_PER_WEEK,
					'usermacros' => true
				]]
			],
			'status' => ['db drules.status', 'in' => [DRULE_STATUS_ACTIVE, DRULE_STATUS_DISABLED]],
			'concurrency_max_type' => ['integer',
				'in' => [ZBX_DISCOVERY_CHECKS_ONE, ZBX_DISCOVERY_CHECKS_UNLIMITED, ZBX_DISCOVERY_CHECKS_CUSTOM]
			],
			'concurrency_max' => [
				'integer', 'required',
				'min' => ZBX_DISCOVERY_CHECKS_UNLIMITED,
				'max' => ZBX_DISCOVERY_CHECKS_MAX,
				'when' => ['concurrency_max_type', 'in' => [ZBX_DISCOVERY_CHECKS_CUSTOM]]
			],
			'dchecks' => [
				'objects', 'required', 'not_empty',
				'uniq' => [['type', 'ports', 'key_']],
				'fields' => [
					'dcheckid' =>              ['string'],
					'type' =>                  ['db dchecks.type', 'required'],
					'ports' =>                 ['db dchecks.ports'],
					'key_' =>                  ['db dchecks.key_'],
					'snmp_community' =>        ['db dchecks.snmp_community'],
					'snmpv3_securityname' =>   ['db dchecks.snmpv3_securityname'],
					'snmpv3_securitylevel' =>  ['db dchecks.snmpv3_securitylevel'],
					'snmpv3_authpassphrase' => ['db dchecks.snmpv3_authpassphrase'],
					'snmpv3_privpassphrase' => ['db dchecks.snmpv3_privpassphrase'],
					'snmpv3_authprotocol' =>   ['db dchecks.snmpv3_authprotocol'],
					'snmpv3_privprotocol' =>   ['db dchecks.snmpv3_privprotocol'],
					'snmpv3_contextname' =>    ['db dchecks.snmpv3_contextname'],
					'allow_redirect' =>        ['db dchecks.allow_redirect'],
					'host_source' =>           ['db dchecks.host_source'],
					'name_source' =>           ['db dchecks.name_source']
				],
				'messages' => [
					'uniq' => _('An identical discovery check already exists for this rule.')
				]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add discovery rule'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY);
	}

	protected function doAction(): void {
		$discovery = $this->getInputAll() + [
				'proxyid' => 0
			];

		unset($discovery['discovery_by']);
		unset($discovery['concurrency_max_type']);

		$result = API::DRule()->create($discovery);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Discovery rule created');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot create discovery rule'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
