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


class CControllerDiscoveryCheckCheck extends CController {

	/**
	 * Default discovery check type.
	 */
	const DEFAULT_TYPE = SVC_FTP;

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'dcheckid' => ['string'],
			'dchecks' => ['string'],
			'type' => ['db dchecks.type', 'required',
				'in' => [SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP, SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP,
					SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2c, SVC_ICMPPING, SVC_SNMPv3, SVC_HTTPS, SVC_TELNET
				]
			],
			'ports' => ['db dchecks.ports', 'required', 'not_empty',
				'use' => [CPortRangeParser::class, []],
				'when' => ['type',
					'in' => [SVC_FTP, SVC_HTTP, SVC_HTTPS, SVC_IMAP, SVC_LDAP, SVC_NNTP, SVC_POP, SVC_SMTP,
						SVC_SSH, SVC_TCP, SVC_TELNET, SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3, SVC_AGENT
					]
				]
			],
			'key_' => [
				['db dchecks.key_', 'required', 'not_empty',
					'use' => [CItemKey::class, []],
					'when' => ['type', 'in' => [SVC_AGENT]]
				],
				['db dchecks.key_']
			],
			'snmp_community' => ['db dchecks.snmp_community', 'required', 'not_empty',
				'when' => ['type', 'in' => [SVC_SNMPv1, SVC_SNMPv2c]]
			],
			'snmp_oid' => ['string', 'required', 'not_empty',
				'when' => ['type', 'in' => [SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3]]
			],
			'snmpv3_securityname' => ['db dchecks.snmpv3_securityname',
				'when' => ['type', 'in' => [SVC_SNMPv3]]
			],
			'snmpv3_securitylevel' => ['db dchecks.snmpv3_securitylevel',
				'in' => [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,
					ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV
				],
				'when' => ['type', 'in' => [SVC_SNMPv3]]
			],
			'snmpv3_authpassphrase' => ['db dchecks.snmpv3_authpassphrase',
				'when' => [
					['type', 'in' => [SVC_SNMPv3]],
					['snmpv3_securitylevel',
						'in' => [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]
					]
				]
			],
			'snmpv3_privpassphrase' => ['db dchecks.snmpv3_privpassphrase', 'required', 'not_empty',
				'when' => [
					['type', 'in' => [SVC_SNMPv3]],
					['snmpv3_securitylevel', 'in' => [ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]]
				]
			],
			'snmpv3_authprotocol' => ['db dchecks.snmpv3_authprotocol',
				'in' => [ITEM_SNMPV3_AUTHPROTOCOL_MD5, ITEM_SNMPV3_AUTHPROTOCOL_SHA1,
					ITEM_SNMPV3_AUTHPROTOCOL_SHA224, ITEM_SNMPV3_AUTHPROTOCOL_SHA256,
					ITEM_SNMPV3_AUTHPROTOCOL_SHA384, ITEM_SNMPV3_AUTHPROTOCOL_SHA512
				],
				'when' => [
					['type', 'in' => [SVC_SNMPv3]],
					['snmpv3_securitylevel',
						'in' => [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]
					]
				]
			],
			'snmpv3_privprotocol' => ['db dchecks.snmpv3_privprotocol',
				'in' => [ITEM_SNMPV3_PRIVPROTOCOL_DES, ITEM_SNMPV3_PRIVPROTOCOL_AES128,
					ITEM_SNMPV3_PRIVPROTOCOL_AES192, ITEM_SNMPV3_PRIVPROTOCOL_AES256,
					ITEM_SNMPV3_PRIVPROTOCOL_AES192C, ITEM_SNMPV3_PRIVPROTOCOL_AES256C
				],
				'when' => [
					['type', 'in' => [SVC_SNMPv3]],
					['snmpv3_securitylevel',
						'in' => [ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]
					]
				]
			],
			'snmpv3_contextname' => ['db dchecks.snmpv3_contextname',
				'when' => ['type', 'in' => [SVC_SNMPv3]]
			],
			'allow_redirect' => ['db dchecks.allow_redirect', 'in' => [0, 1],
				'when' => ['type', 'in' => [SVC_ICMPPING]]
			],
			'host_source' => [
				['db dchecks.host_source',
					'in' => [ZBX_DISCOVERY_DNS, ZBX_DISCOVERY_IP]
				],
				['db dchecks.host_source',
					'in' => [ZBX_DISCOVERY_DNS, ZBX_DISCOVERY_IP, ZBX_DISCOVERY_VALUE],
					'when' => ['type', 'in' => [SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3]]
				]
			],
			'name_source' => [
				['db dchecks.name_source',
					'in' => [ZBX_DISCOVERY_UNSPEC, ZBX_DISCOVERY_DNS, ZBX_DISCOVERY_IP]
				],
				['db dchecks.name_source',
					'in' => [ZBX_DISCOVERY_UNSPEC, ZBX_DISCOVERY_DNS, ZBX_DISCOVERY_IP, ZBX_DISCOVERY_VALUE],
					'when' => ['type', 'in' => [SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3]]
				]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());
		CMessageHelper::setErrorTitle(_('Cannot update discovery check'));

		if ($ret && $this->hasDuplicateDCheck()) {
			CMessageHelper::setErrorTitle(_('Cannot add duplicate discovery check'));
			$ret = false;
		}

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => CMessageHelper::getTitle(),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	private function hasDuplicateDCheck(): bool {
		$existing_dchecks = json_decode($this->getInput('dchecks', '[]'), true);
		$compare_exclude = ['dchecks', 'host_source', 'name_source', 'name', 'dcheckid', 'uniq', 'warning'];

		if (!is_array($existing_dchecks)) {
			return false;
		}

		$dcheck = $this->normalizeDCheck($this->getInputAll());
		$compare_dcheck = array_diff_key($dcheck, array_flip($compare_exclude));

		foreach ($existing_dchecks as $existing_dcheck) {
			if (!is_array($existing_dcheck)) {
				continue;
			}

			$existing_dcheck = $this->normalizeDCheck($existing_dcheck);

			if ($existing_dcheck['type'] !== $dcheck['type']) {
				continue;
			}

			$compare_existing_dcheck = array_diff_key($existing_dcheck, array_flip($compare_exclude));
			$is_duplicate = true;

			foreach ($compare_existing_dcheck as $field => $value) {
				if (array_key_exists($field, $compare_dcheck)
					&& strcmp((string) $value, (string) $compare_dcheck[$field]) !== 0) {
					$is_duplicate = false;
					break;
				}
			}

			if ($is_duplicate) {
				return true;
			}
		}

		return false;
	}

	private function normalizeDCheck(array $dcheck): array {
		foreach ($dcheck as $field => $value) {
			if (is_string($value)) {
				$dcheck[$field] = trim($value);
			}
		}

		if (array_key_exists('snmp_oid', $dcheck)) {
			$dcheck['key_'] = $dcheck['snmp_oid'];
			unset($dcheck['snmp_oid']);
		}

		if (!array_key_exists('snmpv3_securitylevel', $dcheck) || $dcheck['snmpv3_securitylevel'] === null) {
			$dcheck['snmpv3_securitylevel'] = ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV;
		}

		if ($dcheck['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
			$dcheck['snmpv3_authprotocol'] = ITEM_SNMPV3_AUTHPROTOCOL_MD5;
			$dcheck['snmpv3_privprotocol'] = ITEM_SNMPV3_PRIVPROTOCOL_DES;
			$dcheck['snmpv3_authpassphrase'] = '';
			$dcheck['snmpv3_privpassphrase'] = '';
		}
		elseif ($dcheck['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
			$dcheck['snmpv3_privprotocol'] = ITEM_SNMPV3_PRIVPROTOCOL_DES;
			$dcheck['snmpv3_privpassphrase'] = '';
		}

		if (!array_key_exists('allow_redirect', $dcheck) && ($dcheck['type'] ?? null) == SVC_ICMPPING) {
			$dcheck['allow_redirect'] = '0';
		}

		$dcheck += DB::getDefaults('dchecks');

		foreach ($dcheck as $field => $value) {
			if ($value !== null && !is_array($value)) {
				$dcheck[$field] = (string) $value;
			}
		}

		return $dcheck;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY);
	}

	/**
	 * @throws JsonException
	 */
	protected function doAction(): void {
		$data = array_merge([
			'type' => self::DEFAULT_TYPE,
		], $this->getInputAll());

		$data['dcheckid'] = $this->getInput('dcheckid');

		if ($data['type'] == SVC_SNMPv1 || $data['type'] == SVC_SNMPv2c || $data['type'] == SVC_SNMPv3) {
			$data['key_'] = $data['snmp_oid'];
		}

		$data['name'] = discovery_check2str(
			$data['type'],
			array_key_exists('key_', $data) ? $data['key_'] : '',
			array_key_exists('ports',  $data) ? $data['ports'] : '',
			array_key_exists('allow_redirect',  $data) ? $data['allow_redirect'] : ''
		);

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data, JSON_THROW_ON_ERROR)]));
	}
}
