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


class CControllerDiscoveryUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['drule.get', ['name' => '{name}'], 'druleid']
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'druleid' => ['db drules.druleid', 'required'],
			'name' => ['db drules.name', 'required', 'not_empty'],
			'discovery_by' => ['integer', 'required', 'in' => [ZBX_DISCOVERY_BY_SERVER, ZBX_DISCOVERY_BY_PROXY]],
			'proxyid' => ['db drules.proxyid', 'required',
				'when' => ['discovery_by', 'in' => [ZBX_DISCOVERY_BY_PROXY]]
			],
			'iprange' => ['db drules.iprange', 'required', 'not_empty',
				'use' => [CIPRangeValidator::class, [
					'v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30, 'max' => ZBX_DISCOVERER_IPRANGE_LIMIT
				]]
			],
			'delay' => ['db drules.delay', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => SEC_PER_WEEK, 'usermacros' => true]]
			],
			'status' => ['db drules.status', 'required', 'in' => [DRULE_STATUS_ACTIVE, DRULE_STATUS_DISABLED]],
			'concurrency_max_type' => ['integer', 'required',
				'in' => [ZBX_DISCOVERY_CHECKS_ONE, ZBX_DISCOVERY_CHECKS_UNLIMITED, ZBX_DISCOVERY_CHECKS_CUSTOM]
			],
			'concurrency_max' => ['integer', 'required',
				'min' => ZBX_DISCOVERY_CHECKS_UNLIMITED, 'max' => ZBX_DISCOVERY_CHECKS_MAX,
				'when' => ['concurrency_max_type', 'in' => [ZBX_DISCOVERY_CHECKS_CUSTOM]]
			],
			'dchecks' => ['objects', 'required', 'not_empty',
				'uniq' => ['type', 'key_', 'snmp_community', 'ports', 'snmpv3_securityname', 'snmpv3_securitylevel',
					'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'snmpv3_authprotocol', 'snmpv3_privprotocol',
					'snmpv3_contextname', 'allow_redirect'
				],
				'fields' => [
					'dcheckid' => ['string', 'required', 'not_empty'],
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
						['db dchecks.key_', 'required', 'not_empty',
							'when' => ['type', 'in' => [SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3]]
						]
					],
					'snmp_community' => ['db dchecks.snmp_community', 'required', 'not_empty',
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
							['snmpv3_securitylevel', 'in' => [ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]]
						]
					],
					'snmpv3_contextname' => ['db dchecks.snmpv3_contextname',
						'when' => ['type', 'in' => [SVC_SNMPv3]]
					],
					'allow_redirect' => ['db dchecks.allow_redirect', 'required', 'in' => [0, 1],
						'when' => ['type', 'in' => [SVC_ICMPPING]]
					],
					'uniq' => [
						['boolean',
							'when' => ['type', 'in' => [SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3, SVC_AGENT]]
						],
						['integer', 'in' => [0],
							'when' => ['type', 'in' => [SVC_FTP, SVC_HTTP, SVC_HTTPS, SVC_ICMPPING, SVC_IMAP, SVC_LDAP,
								SVC_NNTP, SVC_POP, SVC_SMTP, SVC_SSH, SVC_TCP, SVC_TELNET]
							]
						]
					],
					'host_source' => [
						['db dchecks.host_source',
							'in' => [ZBX_DISCOVERY_DNS, ZBX_DISCOVERY_IP, ZBX_DISCOVERY_VALUE]
						],
						['db dchecks.host_source', 'not_in' => [ZBX_DISCOVERY_VALUE],
							'when' => ['type',
								'in' => [SVC_FTP, SVC_HTTP, SVC_HTTPS, SVC_ICMPPING, SVC_IMAP, SVC_LDAP, SVC_NNTP,
									SVC_POP, SVC_SMTP, SVC_SSH, SVC_TCP, SVC_TELNET
								]
							]
						]
					],
					'name_source' => [
						['db dchecks.name_source',
							'in' => [ZBX_DISCOVERY_UNSPEC, ZBX_DISCOVERY_DNS, ZBX_DISCOVERY_IP, ZBX_DISCOVERY_VALUE]
						],
						['db dchecks.name_source', 'not_in' => [ZBX_DISCOVERY_VALUE],
							'when' => ['type',
								'in' => [SVC_FTP, SVC_HTTP, SVC_HTTPS, SVC_ICMPPING, SVC_IMAP, SVC_LDAP, SVC_NNTP,
									SVC_POP, SVC_SMTP, SVC_SSH, SVC_TCP, SVC_TELNET
								]
							]
						]
					]
				],
				'count_values' => [
					[
						'field_rules' => ['uniq', 'in' => [1]],
						'max' => 1,
						'message' => _('Only one check can be unique.')
					],
					[
						'field_rules' => ['host_source', 'in' => [ZBX_DISCOVERY_VALUE]],
						'max' => 1,
						'message' => _('Only one check can be unique.')
					],
					[
						'field_rules' => ['name_source', 'in' => [ZBX_DISCOVERY_VALUE]],
						'max' => 1,
						'message' => _('Only one check can be unique.')
					]
				],
				'messages' => [
					'uniq' => _('Checks should be unique.')
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
					'title' => _('Cannot update discovery rule'),
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
		$drule = $this->getInputAll() + [
				'proxyid' => 0
			];

		if ($drule['concurrency_max_type'] != ZBX_DISCOVERY_CHECKS_CUSTOM) {
			$drule['concurrency_max'] = $drule['concurrency_max_type'];
		}

		foreach ($drule['dchecks'] as $key => $check) {
			if (str_starts_with($check['dcheckid'], 'new')) {
				unset($drule['dchecks'][$key]['dcheckid']);
			}

			if (!in_array($check['type'], [SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3, SVC_AGENT])) {
				$drule['dchecks'][$key]['uniq'] = 0;
			}
		}

		unset($drule['discovery_by']);
		unset($drule['concurrency_max_type']);

		$result = API::DRule()->update($drule);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Discovery rule updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update discovery rule'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
