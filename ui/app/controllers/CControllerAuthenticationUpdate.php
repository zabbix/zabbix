<?php
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


class CControllerAuthenticationUpdate extends CController {

	public static function getValidationRules(): array {
		global $ALLOW_HTTP_AUTH;

		$rules = ['object', 'fields' => [
			'passwd_min_length' => ['setting passwd_min_length', 'required', 'min' => 1, 'max' => 70],
			'passwd_check_rules' => ['array', 'field' => ['setting passwd_check_rules',
				'in' => [0, PASSWD_CHECK_CASE, PASSWD_CHECK_DIGITS, PASSWD_CHECK_SPECIAL, PASSWD_CHECK_SIMPLE]
			]],
			'ldap_auth_enabled' => ['setting ldap_auth_enabled',
				'in' => [ZBX_AUTH_LDAP_DISABLED, ZBX_AUTH_LDAP_ENABLED]
			],
			'authentication_type' => [
				['setting authentication_type', 'required', 'in' => [ZBX_AUTH_INTERNAL, ZBX_AUTH_LDAP]],
				['setting authentication_type', 'not_in' => [ZBX_AUTH_LDAP],
					'when' => ['ldap_auth_enabled', 'in' => [ZBX_AUTH_LDAP_DISABLED]],
					'messages' => ['not_in' => _('LDAP is not configured.')]
				]
			],
			'ldap_jit_status' => ['setting ldap_jit_status',
				'in' => [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED],
				'when' => ['ldap_auth_enabled', 'in' => [ZBX_AUTH_LDAP_ENABLED]]
			],
			'ldap_servers' => [
				[
					'objects', 'uniq' => ['name'],
					'fields' =>  [
						'userdirectoryid' => ['db userdirectory.userdirectoryid'],
						'name' => ['db userdirectory.name', 'required', 'not_empty'],
						'host' => ['db userdirectory_ldap.host', 'required', 'not_empty'],
						'port' => ['db userdirectory_ldap.port', 'required', 'min' => ZBX_MIN_PORT_NUMBER,
							'max' => ZBX_MAX_PORT_NUMBER
						],
						'base_dn' => ['db userdirectory_ldap.base_dn', 'required', 'not_empty'],
						'search_attribute' => ['db userdirectory_ldap.search_attribute', 'required', 'not_empty'],
						'bind_dn' => ['db userdirectory_ldap.bind_dn'],
						'bind_password' => ['db userdirectory_ldap.bind_password'],
						'description' => ['db userdirectory.description'],
						'provision_status' => ['db userdirectory.provision_status',
							'in' => [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED]
						],
						'group_basedn' => ['db userdirectory_ldap.group_basedn',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'group_name' => ['db userdirectory_ldap.group_name',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'group_membership' => ['db userdirectory_ldap.group_membership',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'group_member' => ['db userdirectory_ldap.group_member',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'user_ref_attr' => ['db userdirectory_ldap.user_ref_attr',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'group_filter' => ['db userdirectory_ldap.group_filter',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'user_username' => ['db userdirectory_ldap.user_username',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'user_lastname' => ['db userdirectory_ldap.user_lastname',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'provision_groups' => ['objects', 'not_empty', 'uniq' => ['name'],
							'fields' => CControllerPopupUserGroupMappingCheck::getFieldsValidationRules(),
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'provision_media' => ['objects', 'uniq' => ['attribute', 'mediatypeid'],
							'fields' => [
								'userdirectory_mediaid' => ['db userdirectory_media.userdirectory_mediaid'],
								'mediatypeid' => ['db media_type.mediatypeid', 'required'],
								'name' => ['db userdirectory_media.name', 'required', 'not_empty'],
								'attribute' => ['db userdirectory_media.attribute', 'required', 'not_empty'],
								'period' => ['db userdirectory_media.period', 'required', 'not_empty',
									'use' => [CTimePeriodParser::class, ['usermacros' => true]]
								],
								'severity' => ['db userdirectory_media.severity', 'min' => 0,
									'max' => (pow(2, TRIGGER_SEVERITY_COUNT) - 1)
								],
								'active' => ['db userdirectory_media.active', 'required',
									'in' => [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]
								]
							],
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]],
							'messages' => ['uniq' => _('Media type and attribute is not unique.')]
						],
						'start_tls' => ['db userdirectory_ldap.start_tls',
							'in' => [ZBX_AUTH_START_TLS_OFF, ZBX_AUTH_START_TLS_ON]
						],
						'search_filter' => ['db userdirectory_ldap.search_filter']
					],
					'when' => ['ldap_auth_enabled', 'in' => [ZBX_AUTH_LDAP_DISABLED]]
				],
				[
					'objects', 'required', 'not_empty', 'uniq' => ['name'],
					'fields' =>  [
						'userdirectoryid' => ['db userdirectory.userdirectoryid'],
						'name' => ['db userdirectory.name', 'required', 'not_empty'],
						'host' => ['db userdirectory_ldap.host', 'required', 'not_empty'],
						'port' => ['db userdirectory_ldap.port', 'required', 'min' => ZBX_MIN_PORT_NUMBER,
							'max' => ZBX_MAX_PORT_NUMBER
						],
						'base_dn' => ['db userdirectory_ldap.base_dn', 'required', 'not_empty'],
						'search_attribute' => ['db userdirectory_ldap.search_attribute', 'required', 'not_empty'],
						'bind_dn' => ['db userdirectory_ldap.bind_dn'],
						'bind_password' => ['db userdirectory_ldap.bind_password'],
						'description' => ['db userdirectory.description'],
						'provision_status' => ['db userdirectory.provision_status',
							'in' => [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED]
						],
						'group_basedn' => ['db userdirectory_ldap.group_basedn',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'group_name' => ['db userdirectory_ldap.group_name',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'group_membership' => ['db userdirectory_ldap.group_membership',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'group_member' => ['db userdirectory_ldap.group_member',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'user_ref_attr' => ['db userdirectory_ldap.user_ref_attr',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'group_filter' => ['db userdirectory_ldap.group_filter',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'user_username' => ['db userdirectory_ldap.user_username',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'user_lastname' => ['db userdirectory_ldap.user_lastname',
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'provision_groups' => ['objects', 'not_empty', 'uniq' => ['name'],
							'fields' => CControllerPopupUserGroupMappingCheck::getFieldsValidationRules(),
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
						],
						'provision_media' => ['objects', 'uniq' => ['attribute', 'mediatypeid'],
							'fields' => [
								'userdirectory_mediaid' => ['db userdirectory_media.userdirectory_mediaid'],
								'mediatypeid' => ['db media_type.mediatypeid', 'required'],
								'name' => ['db userdirectory_media.name', 'required', 'not_empty'],
								'attribute' => ['db userdirectory_media.attribute', 'required', 'not_empty'],
								'period' => ['db userdirectory_media.period', 'required', 'not_empty',
									'use' => [CTimePeriodParser::class, ['usermacros' => true]]
								],
								'severity' => ['db userdirectory_media.severity', 'min' => 0,
									'max' => (pow(2, TRIGGER_SEVERITY_COUNT) - 1)
								],
								'active' => ['db userdirectory_media.active', 'required',
									'in' => [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]
								]
							],
							'when' => ['provision_status', 'in' => [JIT_PROVISIONING_ENABLED]],
							'messages' => ['uniq' => _('Media type and attribute is not unique.')]
						],
						'start_tls' => ['db userdirectory_ldap.start_tls',
							'in' => [ZBX_AUTH_START_TLS_OFF, ZBX_AUTH_START_TLS_ON]
						],
						'search_filter' => ['db userdirectory_ldap.search_filter']
					],
					'when' => ['ldap_auth_enabled', 'in' => [ZBX_AUTH_LDAP_ENABLED]]
				]
			],
			'ldap_default_row_index' =>	['integer', 'required'],
			'ldap_case_sensitive' => ['setting ldap_case_sensitive',
				'in' => [ZBX_AUTH_CASE_INSENSITIVE, ZBX_AUTH_CASE_SENSITIVE],
				'when' => ['ldap_auth_enabled', 'in' => [ZBX_AUTH_LDAP_ENABLED]]
			],
			'ldap_removed_userdirectoryids' => ['array', 'field' => ['db userdirectory_ldap.userdirectoryid']],
			'jit_provision_interval' =>	['setting jit_provision_interval',
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_HOUR, 'max' => 25 * SEC_PER_YEAR]],
				'when' => [
					['ldap_auth_enabled', 'in' => [ZBX_AUTH_LDAP_ENABLED]],
					['ldap_jit_status', 'in' => [JIT_PROVISIONING_ENABLED]]
				]
			],
			'saml_auth_enabled' => ['setting saml_auth_enabled',
				'in' => [ZBX_AUTH_SAML_DISABLED, ZBX_AUTH_SAML_ENABLED]
			],
			'saml_jit_status' => ['setting saml_jit_status',
				'in' => [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED],
				'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
			],
			'disabled_usrgrpid' => [
				['setting disabled_usrgrpid'],
				['setting disabled_usrgrpid', 'required',
					'when' => [
						['ldap_auth_enabled', 'in' => [ZBX_AUTH_LDAP_ENABLED]],
						['ldap_jit_status', 'in' => [JIT_PROVISIONING_ENABLED]]
					]
				],
				['setting disabled_usrgrpid', 'required',
					'when' => [
						['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]],
						['saml_jit_status', 'in' => [JIT_PROVISIONING_ENABLED]]
					]
				]
			],
			'idp_entityid' => ['db userdirectory_saml.idp_entityid', 'required', 'not_empty',
				'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
			],
			'sso_url' => ['db userdirectory_saml.sso_url', 'required', 'not_empty',
				'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
			],
			'slo_url' => ['db userdirectory_saml.slo_url',
				'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
			],
			'username_attribute' =>	['db userdirectory_saml.username_attribute', 'not_empty',
				'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
			],
			'sp_entityid' => ['db userdirectory_saml.sp_entityid', 'required', 'not_empty',
				'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
			],
			'nameid_format' => ['db userdirectory_saml.nameid_format',
				'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
			],
			'sign_messages' => ['boolean', 'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]],
			'sign_assertions' => ['boolean', 'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]],
			'sign_authn_requests' => ['boolean', 'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]],
			'sign_logout_requests' => ['boolean', 'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]],
			'sign_logout_responses' => ['boolean', 'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]],
			'encrypt_nameid' =>	['boolean', 'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]],
			'encrypt_assertions' =>	['boolean', 'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]],
			'saml_case_sensitive' => ['integer', 'in' => [ZBX_AUTH_CASE_INSENSITIVE, ZBX_AUTH_CASE_SENSITIVE],
				'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
			],
			'saml_provision_status' => ['integer', 'in' => [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED],
				'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
			],
			'saml_group_name' => ['db userdirectory_saml.group_name', 'required', 'not_empty',
				'when' => [
					['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]],
					['saml_provision_status', 'in' => [ZBX_AUTH_SAML_ENABLED]]
				]
			],
			'saml_user_username' =>	['db userdirectory_saml.user_username',
				'when' => [
					['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]],
					['saml_provision_status', 'in' => [ZBX_AUTH_SAML_ENABLED]]
				]
			],
			'saml_user_lastname' =>	['db userdirectory_saml.user_lastname',
				'when' => [
					['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]],
					['saml_provision_status', 'in' => [ZBX_AUTH_SAML_ENABLED]]
				]
			],
			'saml_provision_groups' => ['objects', 'not_empty', 'uniq' => ['name'],
				'fields' => CControllerPopupUserGroupMappingCheck::getFieldsValidationRules(),
				'when' => [
					['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]],
					['saml_provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
				]
			],
			'saml_provision_media' => ['objects', 'uniq' => ['attribute', 'mediatypeid'],
				'fields' => [
					'userdirectory_mediaid' => ['db userdirectory_media.userdirectory_mediaid'],
					'mediatypeid' => ['db media_type.mediatypeid', 'required'],
					'name' => ['db userdirectory_media.name', 'required', 'not_empty'],
					'attribute' => ['db userdirectory_media.attribute', 'required', 'not_empty'],
					'period' => ['db userdirectory_media.period', 'required', 'not_empty',
						'use' => [CTimePeriodParser::class, ['usermacros' => true]]
					],
					'severity' => ['db userdirectory_media.severity', 'min' => 0,
						'max' => (pow(2, TRIGGER_SEVERITY_COUNT) - 1)
					],
					'active' => ['db userdirectory_media.active', 'required',
						'in' => [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]
					]
				],
				'when' => [
					['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]],
					['saml_provision_status', 'in' => [JIT_PROVISIONING_ENABLED]]
				],
				'messages' => ['uniq' => _('Media type and attribute is not unique.')]
			],
			'scim_status' => ['db userdirectory_saml.scim_status',
				'in' => [ZBX_AUTH_SCIM_PROVISIONING_DISABLED, ZBX_AUTH_SCIM_PROVISIONING_ENABLED],
				'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
			],
			'mfa_status' => ['setting mfa_status', 'in' => [MFA_DISABLED, MFA_ENABLED]],
			'mfa_methods' => [
				[
					'objects', 'uniq' => ['name'],
					'fields' => [
						'mfaid' => ['db mfa.mfaid'],
						'type' => ['db mfa.type', 'required', 'in' => [MFA_TYPE_TOTP, MFA_TYPE_DUO]],
						'name' => ['db mfa.name', 'required', 'not_empty'],
						'hash_function' => ['db mfa.hash_function', 'required',
							'in' => [TOTP_HASH_SHA1, TOTP_HASH_SHA256, TOTP_HASH_SHA512],
							'when' => ['type', 'in' => [MFA_TYPE_TOTP]]
						],
						'code_length' => ['db mfa.code_length', 'required',
							'in' => [TOTP_CODE_LENGTH_6, TOTP_CODE_LENGTH_8],
							'when' => ['type', 'in' => [MFA_TYPE_TOTP]]
						],
						'api_hostname' => ['db mfa.api_hostname', 'required', 'not_empty',
							'when' => ['type', 'in' => [MFA_TYPE_DUO]]
						],
						'clientid' => ['db mfa.clientid', 'required', 'not_empty',
							'when' => ['type', 'in' => [MFA_TYPE_DUO]]
						],
						'client_secret' => ['db mfa.client_secret', 'not_empty',
							'when' => ['type', 'in' => [MFA_TYPE_DUO]]
						]
					],
					'when' => ['mfa_status', 'in' => [MFA_DISABLED]]
				],
				[
					'objects', 'required', 'not_empty', 'uniq' => ['name'],
					'fields' => [
						'mfaid' => ['db mfa.mfaid'],
						'type' => ['db mfa.type', 'required', 'in' => [MFA_TYPE_TOTP, MFA_TYPE_DUO]],
						'name' => ['db mfa.name', 'required', 'not_empty'],
						'hash_function' => ['db mfa.hash_function', 'required',
							'in' => [TOTP_HASH_SHA1, TOTP_HASH_SHA256, TOTP_HASH_SHA512],
							'when' => ['type', 'in' => [MFA_TYPE_TOTP]]
						],
						'code_length' => ['db mfa.code_length', 'required',
							'in' => [TOTP_CODE_LENGTH_6, TOTP_CODE_LENGTH_8],
							'when' => ['type', 'in' => [MFA_TYPE_TOTP]]
						],
						'api_hostname' => ['db mfa.api_hostname', 'required', 'not_empty',
							'when' => ['type', 'in' => [MFA_TYPE_DUO]]
						],
						'clientid' => ['db mfa.clientid', 'required', 'not_empty',
							'when' => ['type', 'in' => [MFA_TYPE_DUO]]
						],
						'client_secret' => ['db mfa.client_secret', 'not_empty',
							'when' => ['type', 'in' => [MFA_TYPE_DUO]]
						]
					],
					'when' => ['mfa_status', 'in' => [MFA_ENABLED]]
				]
			],
			'mfa_default_row_index' => ['integer', 'required'],
			'mfa_removed_mfaids' =>	['array', 'field' => ['db mfa.mfaid']]
		]];

		if (CAuthenticationHelper::isSamlCertsStorageDatabase()) {
			$rules['fields'] += [
				'idp_certificate' => ['string', 'not_empty',
					'length' => CApiInputValidator::SSL_CERTIFICATE_MAX_LENGTH,
					'use' => [CSslCertificateValidator::class],
					'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
				],
				'sp_private_key' => [
					[
						'string', 'length' => CApiInputValidator::SSL_PRIVATE_KEY_MAX_LENGTH,
						'use' => [CSslPrivateKeyValidator::class],
						'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
					],
					['string', 'not_empty', 'when' => ['sign_messages', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['sign_assertions', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['sign_authn_requests', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['sign_logout_requests', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['sign_logout_responses', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['encrypt_nameid', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['encrypt_assertions', 'in' => [1]]]
				],
				'sp_certificate' => [
					[
						'string', 'length' => CApiInputValidator::SSL_CERTIFICATE_MAX_LENGTH,
						'use' => [CSslCertificateValidator::class],
						'when' => ['saml_auth_enabled', 'in' => [ZBX_AUTH_SAML_ENABLED]]
					],
					['string', 'not_empty', 'when' => ['sign_messages', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['sign_assertions', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['sign_authn_requests', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['sign_logout_requests', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['sign_logout_responses', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['encrypt_nameid', 'in' => [1]]],
					['string', 'not_empty', 'when' => ['encrypt_assertions', 'in' => [1]]]
				]
			];
		}

		if ($ALLOW_HTTP_AUTH) {
			$rules['fields'] += [
				'http_auth_enabled' => ['setting http_auth_enabled',
					'in' => [ZBX_AUTH_HTTP_DISABLED, ZBX_AUTH_HTTP_ENABLED]
				],
				'http_login_form' => ['setting http_login_form',
					'in' => [ZBX_AUTH_FORM_ZABBIX, ZBX_AUTH_FORM_HTTP],
					'when' => ['http_auth_enabled', 'in' => [ZBX_AUTH_HTTP_ENABLED]]
				],
				'http_strip_domains' => ['setting http_strip_domains',
					'when' => ['http_auth_enabled', 'in' => [ZBX_AUTH_HTTP_ENABLED]]
				],
				'http_case_sensitive' => ['setting http_case_sensitive',
					'in' => [ZBX_AUTH_CASE_INSENSITIVE, ZBX_AUTH_CASE_SENSITIVE],
					'when' => ['http_auth_enabled', 'in' => [ZBX_AUTH_HTTP_ENABLED]]
				]
			];
		}

		return $rules;
	}

	private const PROVISION_ENABLED_FIELDS = ['group_basedn', 'group_member', 'group_membership',  'group_name',
		'user_username', 'user_lastname', 'uer_ref_attr', 'provision_groups', 'provision_media'
	];

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	protected function checkInput() {
		$ret = $this->validateInput(self::getValidationRules());

		if ($ret) {
			$ret = $this->validateLdap() && $this->validateSamlAuth() && $this->validateMfa();
		}

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => CMessageHelper::getTitle() === null
						? _('Cannot update authentication')
						: CMessageHelper::getTitle(),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	/**
	 * Validate LDAP settings.
	 *
	 * @return bool
	 */
	private function validateLdap(): bool {
		if ($this->getInput('ldap_auth_enabled', CSettingsSchema::getDefault('ldap_auth_enabled'))
				== ZBX_AUTH_LDAP_ENABLED) {
			$ldap_status = (new CFrontendSetup())->checkPhpLdapModule();

			if ($ldap_status['result'] != CFrontendSetup::CHECK_OK) {
				error($ldap_status['error']);

				return false;
			}

			$ldap_servers = $this->getInput('ldap_servers', []);

			if (!$this->hasInput('ldap_default_row_index')
					|| !array_key_exists($this->getInput('ldap_default_row_index'), $ldap_servers)) {
				error(_('Default LDAP server must be specified.'));

				return false;
			}
		}

		return true;
	}

	/**
	 * Validate SAML authentication settings.
	 *
	 * @return bool
	 */
	private function validateSamlAuth() {
		if ($this->getInput('saml_auth_enabled', ZBX_AUTH_SAML_ENABLED) == ZBX_AUTH_SAML_DISABLED) {
			return true;
		}

		$openssl_status = (new CFrontendSetup())->checkPhpOpenSsl();
		if ($openssl_status['result'] != CFrontendSetup::CHECK_OK) {
			error($openssl_status['error']);

			return false;
		}

		$this->getInputs($saml_fields, [
			'idp_entityid',
			'sso_url',
			'username_attribute',
			'sp_entityid'
		]);

		return true;
	}

	/**
	 * Validate MFA settings.
	 *
	 * @return bool
	 */
	private function validateMfa(): bool {
		$default_mfa = $this->hasInput('mfa_default_row_index') ? $this->getInput('mfa_default_row_index') : null;
		$error = $this->getInput('mfa_status', MFA_DISABLED) == MFA_ENABLED
			&& !array_key_exists($default_mfa, $this->getInput('mfa_methods', []));

		if ($error) {
			error(_('Default MFA method must be specified.'));
		}

		return !$error;
	}

	/**
	 * Validate is user allowed to change configuration.
	 *
	 * @return bool
	 */
	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	/**
	 * In case of error, convert array back to integer (string) so edit form does not fail.
	 *
	 * @return array
	 */
	public function getInputAll() {
		$input = parent::getInputAll();
		$rules = $input['passwd_check_rules'];
		$input['passwd_check_rules'] = 0x00;

		foreach ($rules as $rule) {
			$input['passwd_check_rules'] |= $rule;
		}

		// CNewValidator thinks int32 must be a string type integer.
		$input['passwd_check_rules'] = (string) $input['passwd_check_rules'];

		return $input;
	}

	protected function doAction() {
		$result = false;

		try {
			DBstart();

			$result = $this->processSamlConfiguration();

			$ldap_userdirectoryid = 0;
			if ($result) {
				$ldap_servers = $this->getInput('ldap_servers', []);

				if ($ldap_servers) {
					$ldap_userdirectoryids = $this->processLdapServers($ldap_servers);
					$ldap_default_row_index = $this->getInput('ldap_default_row_index');

					if (!$ldap_userdirectoryids) {
						$result = false;
					}
					else {
						$ldap_userdirectoryid = $ldap_userdirectoryids[$ldap_default_row_index];
					}
				}
			}

			$mfaid = 0;
			if ($result) {
				$mfa_methods = $this->getInput('mfa_methods', []);

				if ($mfa_methods) {
					$mfaids = $this->processMfaMethods($mfa_methods);

					if (!$mfaids) {
						$result = false;
					}
					else {
						$mfaid = $mfaids[$this->getInput('mfa_default_row_index', 0)];
					}
				}
			}

			if ($result) {
				$result = $this->processGeneralAuthenticationSettings($ldap_userdirectoryid, $mfaid);
			}

			if ($result && $this->getInput('ldap_removed_userdirectoryids', []) !== []) {
				$result = (bool) API::UserDirectory()->delete($this->getInput('ldap_removed_userdirectoryids'));
			}

			if ($result && $this->getInput('mfa_removed_mfaids', []) !== []) {
				$result = (bool) API::Mfa()->delete($this->getInput('mfa_removed_mfaids'));
			}

			if (!$result) {
				throw new Exception();
			}

			$result = DBend(true);
		}
		catch (Exception $e) {
			DBend(false);
		}

		$output = [];

		if ($result) {
			$output['success'] = [
				'title' => _('Authentication settings updated'),
				'redirect' => (new CUrl('zabbix.php'))->setArgument('action', 'authentication.edit')->getUrl()
			];
		}
		else {
			$output['error'] = [
				'title' => CMessageHelper::getTitle() === null
					? _('Cannot update authentication')
					: CMessageHelper::getTitle(),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	private function processGeneralAuthenticationSettings(int $ldap_userdirectoryid, int $mfaid): bool {
		global $ALLOW_HTTP_AUTH;

		$auth_params = [
			CAuthenticationHelper::AUTHENTICATION_TYPE,
			CAuthenticationHelper::DISABLED_USER_GROUPID,
			CAuthenticationHelper::LDAP_AUTH_ENABLED,
			CAuthenticationHelper::LDAP_USERDIRECTORYID,
			CAuthenticationHelper::LDAP_CASE_SENSITIVE,
			CAuthenticationHelper::LDAP_JIT_STATUS,
			CAuthenticationHelper::JIT_PROVISION_INTERVAL,
			CAuthenticationHelper::SAML_AUTH_ENABLED,
			CAuthenticationHelper::SAML_JIT_STATUS,
			CAuthenticationHelper::SAML_CASE_SENSITIVE,
			CAuthenticationHelper::PASSWD_MIN_LENGTH,
			CAuthenticationHelper::PASSWD_CHECK_RULES,
			CAuthenticationHelper::MFA_STATUS,
			CAuthenticationHelper::MFAID
		];

		$fields = [
			'authentication_type' => ZBX_AUTH_INTERNAL,
			'disabled_usrgrpid' => 0,
			'ldap_auth_enabled' => ZBX_AUTH_LDAP_DISABLED,
			'ldap_userdirectoryid' => $ldap_userdirectoryid,
			'saml_auth_enabled' => ZBX_AUTH_SAML_DISABLED,
			'passwd_min_length' => CSettingsSchema::getDefault('passwd_min_length'),
			'passwd_check_rules' => CSettingsSchema::getDefault('passwd_check_rules'),
			'mfa_status' => MFA_DISABLED,
			'mfaid' => $mfaid
		];

		if ($ALLOW_HTTP_AUTH) {
			$auth_params = array_merge($auth_params, [
				CAuthenticationHelper::HTTP_AUTH_ENABLED,
				CAuthenticationHelper::HTTP_LOGIN_FORM,
				CAuthenticationHelper::HTTP_STRIP_DOMAINS,
				CAuthenticationHelper::HTTP_CASE_SENSITIVE
			]);

			$fields['http_auth_enabled'] = ZBX_AUTH_HTTP_DISABLED;

			if ($this->getInput('http_auth_enabled', ZBX_AUTH_HTTP_DISABLED) == ZBX_AUTH_HTTP_ENABLED) {
				$fields += [
					'http_case_sensitive' => 0,
					'http_login_form' => 0,
					'http_strip_domains' => ''
				];
			}
		}

		if ($this->getInput('ldap_auth_enabled', ZBX_AUTH_LDAP_DISABLED) == ZBX_AUTH_LDAP_ENABLED) {
			$fields += [
				'ldap_jit_status' => JIT_PROVISIONING_DISABLED,
				'ldap_case_sensitive' => ZBX_AUTH_CASE_INSENSITIVE
			];

			if ($this->getInput('ldap_jit_status', JIT_PROVISIONING_DISABLED) == JIT_PROVISIONING_ENABLED) {
				$fields['jit_provision_interval'] = CSettingsSchema::getDefault('jit_provision_interval');
			}
		}

		if ($this->getInput('saml_auth_enabled', ZBX_AUTH_SAML_DISABLED) == ZBX_AUTH_SAML_ENABLED) {
			$fields += [
				'saml_case_sensitive' => ZBX_AUTH_CASE_INSENSITIVE,
				'saml_jit_status' => JIT_PROVISIONING_DISABLED
			];
		}

		$auth = [];
		foreach ($auth_params as $param) {
			$auth[$param] = CAuthenticationHelper::get($param);
		}

		$data = $fields + $auth;
		$this->getInputs($data, array_keys($fields));

		$rules = $data['passwd_check_rules'];
		$data['passwd_check_rules'] = 0x00;

		foreach ($rules as $rule) {
			$data['passwd_check_rules'] |= $rule;
		}

		$data = array_diff_assoc($data, $auth);
		$result = true;

		if ($data) {
			$result = (bool) API::Authentication()->update($data);

			if ($result && array_key_exists('authentication_type', $data)) {
				$this->invalidateSessions();
			}

			CAuthenticationHelper::reset();
		}

		return $result;
	}

	/**
	 * Updates existing LDAP servers, creates new ones, removes deleted ones.
	 *
	 * @param array $ldap_servers
	 *
	 * @return array
	 */
	private function processLdapServers(array $ldap_servers): array {
		$ins_ldap_servers = [];
		$upd_ldap_servers = [];
		$userdirectoryid_map = [];

		foreach ($ldap_servers as $row_index => &$ldap_server) {
			if (!array_key_exists('provision_status', $ldap_server)
					|| $ldap_server['provision_status'] != JIT_PROVISIONING_ENABLED) {
				$ldap_server = array_diff_key($ldap_server, array_flip(self::PROVISION_ENABLED_FIELDS));
			}

			if (array_key_exists('provision_groups', $ldap_server)) {
				foreach ($ldap_server['provision_groups'] as &$group) {
					$group['user_groups'] = zbx_toObject($group['user_groups'], 'usrgrpid');
				}
				unset($group);
			}

			if (array_key_exists('userdirectoryid', $ldap_server)) {
				$userdirectoryid_map[$row_index] = $ldap_server['userdirectoryid'];
				$upd_ldap_servers[] = $ldap_server + ['provision_media' => []];
			}
			else {
				$userdirectoryid_map[$row_index] = null;
				$ins_ldap_servers[] = ['idp_type' => IDP_TYPE_LDAP] + $ldap_server;
			}
		}
		unset($ldap_server);

		$result = $upd_ldap_servers ? API::UserDirectory()->update($upd_ldap_servers) : [];
		$result = $result !== false && $ins_ldap_servers ? API::UserDirectory()->create($ins_ldap_servers) : $result;

		if ($result) {
			foreach ($userdirectoryid_map as $row_index => $userdirectoryid) {
				if ($userdirectoryid === null) {
					$userdirectoryid_map[$row_index] = array_shift($result['userdirectoryids']);
				}
			}

			return $userdirectoryid_map;
		}
		else {
			return [];
		}
	}

	/**
	 * Updates existing MFA methods, creates new ones, removes deleted ones.
	 *
	 * @param array $mfa_methods
	 *
	 * @return array
	 */
	private function processMfaMethods(array $mfa_methods): array {
		$ins_mfa_methods = [];
		$upd_mfa_methods = [];
		$mfaid_map = [];

		foreach ($mfa_methods as $row_index => $mfa_method) {
			if (array_key_exists('mfaid', $mfa_method)) {
				$mfaid_map[$row_index] = $mfa_method['mfaid'];
				$upd_mfa_methods[] = $mfa_method;
			}
			else {
				$mfaid_map[$row_index] = null;
				$ins_mfa_methods[] = $mfa_method;
			}
		}

		$result = $upd_mfa_methods ? API::Mfa()->update($upd_mfa_methods) : [];
		$result = ($result !== false && $ins_mfa_methods) ? API::Mfa()->create($ins_mfa_methods) : $result;

		if (!$result) {
			return [];
		}

		if ($ins_mfa_methods) {
			foreach ($mfaid_map as $row_index => $mfaid) {
				if ($mfaid === null) {
					$mfaid_map[$row_index] = array_shift($result['mfaids']);
				}
			}
		}

		return $mfaid_map;
	}

	/**
	 * Retrieves SAML configuration fields and creates or updates SAML configuration.
	 *
	 * @return bool
	 */
	private function processSamlConfiguration(): bool {
		if ($this->getInput('saml_auth_enabled', ZBX_AUTH_SAML_DISABLED) != ZBX_AUTH_SAML_ENABLED) {
			return true;
		}

		$saml_data = [
			'idp_entityid' => '',
			'sso_url' => '',
			'slo_url' => '',
			'username_attribute' => '',
			'sp_entityid' => '',
			'nameid_format' => '',
			'sign_messages' => 0,
			'sign_assertions' => 0,
			'sign_authn_requests' => 0,
			'sign_logout_requests' => 0,
			'sign_logout_responses' => 0,
			'encrypt_nameid' => 0,
			'encrypt_assertions' => 0,
			'provision_status' => JIT_PROVISIONING_DISABLED,
			'scim_status' => ZBX_AUTH_SCIM_PROVISIONING_DISABLED
		];
		$this->getInputs($saml_data, array_keys($saml_data));

		if (CAuthenticationHelper::isSamlCertsStorageDatabase()) {
			$this->getInputs($saml_data, [
				'idp_certificate',
				'sp_certificate',
				'sp_private_key'
			]);
		}

		if ($this->getInput('saml_provision_status', JIT_PROVISIONING_DISABLED) == JIT_PROVISIONING_ENABLED) {
			$provisioning_fields = [
				'saml_provision_status' => JIT_PROVISIONING_ENABLED,
				'saml_group_name' => '',
				'saml_user_username' => '',
				'saml_user_lastname' => '',
				'saml_provision_groups' => [],
				'saml_provision_media' => []
			];
			$this->getInputs($provisioning_fields, array_keys($provisioning_fields));

			foreach ($provisioning_fields['saml_provision_groups'] as &$group) {
				$group['user_groups'] = zbx_toObject($group['user_groups'], 'usrgrpid');
			}
			unset($group);

			$provisioning_fields = CArrayHelper::renameKeys($provisioning_fields, [
				'saml_group_name' => 'group_name',
				'saml_user_username' => 'user_username',
				'saml_user_lastname' => 'user_lastname',
				'saml_provision_status' => 'provision_status',
				'saml_provision_groups' => 'provision_groups',
				'saml_provision_media' => 'provision_media'
			]);
			$saml_data = array_merge($saml_data, $provisioning_fields);
		}

		$db_saml = API::UserDirectory()->get([
			'output' => ['userdirectoryid'],
			'filter' => ['idp_type' => IDP_TYPE_SAML]
		]);

		if ($db_saml) {
			$result = API::UserDirectory()->update(['userdirectoryid' => $db_saml[0]['userdirectoryid']] + $saml_data);
		}
		else {
			$result = API::UserDirectory()->create($saml_data + ['idp_type' => IDP_TYPE_SAML]);
		}

		return $result !== false;
	}

	/**
	 * Mark all active GROUP_GUI_ACCESS_INTERNAL sessions, except current user sessions, as ZBX_SESSION_PASSIVE.
	 *
	 * @return bool
	 */
	private function invalidateSessions() {
		$internal_auth_user_groups = API::UserGroup()->get([
			'output' => [],
			'filter' => [
				'gui_access' => GROUP_GUI_ACCESS_INTERNAL
			],
			'preservekeys' => true
		]);

		$internal_auth_users = API::User()->get([
			'output' => [],
			'usrgrpids' => array_keys($internal_auth_user_groups),
			'preservekeys' => true
		]);
		unset($internal_auth_users[CWebUser::$data['userid']]);

		if ($internal_auth_users) {
			DB::update('sessions', [
				'values' => ['status' => ZBX_SESSION_PASSIVE],
				'where' => ['userid' => array_keys($internal_auth_users)]
			]);
		}

		return true;
	}
}
