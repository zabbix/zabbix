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
			'dchecks' => ['objects',
				'uniq' => ['type', 'key_', 'snmp_community', 'ports', 'snmpv3_securityname', 'snmpv3_securitylevel',
					'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'snmpv3_authprotocol', 'snmpv3_privprotocol',
					'snmpv3_contextname', 'allow_redirect'
				],
				'fields' => [
					'type' => ['db dchecks.type',
						'in' => [SVC_FTP, SVC_HTTP, SVC_HTTPS, SVC_ICMPPING, SVC_IMAP, SVC_LDAP, SVC_NNTP, SVC_POP,
							SVC_SMTP, SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3, SVC_SSH, SVC_TCP, SVC_TELNET, SVC_AGENT
						]
					],
					'ports' => [
						['db dchecks.ports', 'required', 'not_empty',
							'use' => [CPortRangeParser::class],
							'when' => ['type',
								'in' => [SVC_FTP, SVC_HTTP, SVC_HTTPS, SVC_IMAP, SVC_LDAP, SVC_NNTP, SVC_POP, SVC_SMTP,
									SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3, SVC_SSH, SVC_TCP, SVC_TELNET, SVC_AGENT
								]
							],
							'messages' => [
								'use' => _('Incorrect port range.')
							]
						],
						['integer', 'in' => [0],
							'when' => ['type', 'in' => [SVC_ICMPPING]]
						]
					],
					'key_' => [
						['db dchecks.key_',
							'use' => [CItemKey::class],
							'when' => ['type', 'in' => [SVC_AGENT]]
						],
						['db dchecks.key_',
							'when' => ['type', 'in' => [SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3]]
						]
					],
					'snmp_community' => ['db dchecks.snmp_community',
						'when' => ['type', 'in' => [SVC_SNMPv1, SVC_SNMPv2c]]
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
					'snmpv3_privpassphrase' => ['db dchecks.snmpv3_privpassphrase',
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
							['snmpv3_securitylevel', 'in' => [ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]]
						]
					],
					'snmpv3_contextname' => ['db dchecks.snmpv3_contextname',
						'when' => ['type', 'in' => [SVC_SNMPv3]]
					],
					'allow_redirect' => ['db dchecks.allow_redirect', 'in' => [0, 1],
						'when' => ['type', 'in' => [SVC_ICMPPING]]
					]
				],
				'messages' => [
					'uniq' => _('Check already exists.')
				]
			],
			'type' => ['db dchecks.type', 'required',
				'in' => [SVC_FTP, SVC_HTTP, SVC_HTTPS, SVC_ICMPPING, SVC_IMAP, SVC_LDAP, SVC_NNTP, SVC_POP,
					SVC_SMTP, SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3, SVC_SSH, SVC_TCP, SVC_TELNET, SVC_AGENT
				]
			],
			'ports' => [
				['db dchecks.ports', 'required', 'not_empty',
					'use' => [CPortRangeParser::class],
					'when' => ['type',
						'in' => [SVC_FTP, SVC_HTTP, SVC_HTTPS, SVC_IMAP, SVC_LDAP, SVC_NNTP, SVC_POP, SVC_SMTP,
							SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3, SVC_SSH, SVC_TCP, SVC_TELNET, SVC_AGENT
						]
					],
					'messages' => [
						'use' => _('Incorrect port range.')
					]
				],
				['integer', 'in' => [0],
					'when' => ['type', 'in' => [SVC_ICMPPING]]
				]
			],
			'key_' => [
				['db dchecks.key_', 'required', 'not_empty',
					'use' => [CItemKey::class],
					'when' => ['type', 'in' => [SVC_AGENT]]
				],
				['db dchecks.key_', 'required',
					'when' => ['type', 'in' => [SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3]]
				]
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
					'title' => _('Cannot update discovery check'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	private function validateDuplicateDCheck(&$data): int {
		if (!isset($data['dchecks']) || !is_array($data['dchecks'])) {
			return CFormValidator::SUCCESS;
		}

		$newDCheck = array_diff_key($data, array_flip(['dchecks']));
		$data['dchecks'][] = $newDCheck;

		$validator = new CFormValidator(self::getValidationRules());
		$is_valid = $validator->validate($data);

		unset($data['dchecks']);

		return $is_valid;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY);
	}

	/**
	 * @throws JsonException
	 */
	protected function doAction(): void {
		$data = $this->getInputAll();

		if (in_array($data['type'], [SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3])) {
			$data['key_'] = $data['snmp_oid'];
		}

		$is_valid = self::validateDuplicateDCheck($data);

		if ($is_valid !== CFormValidator::SUCCESS) {
			$response = [
				'error' => [
					'title' => _('Check already exists.'),
					'messages' => $this->getValidationError()
				]
			];
			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));

			return;
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
