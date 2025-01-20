<?php
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
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('administration.authentication.edit.js.php');

$form = (new CForm())
	->addItem((new CVar('form_refresh', $data['form_refresh'] + 1))->removeId())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('authentication')))->removeId())
	->addVar('action', $data['action_submit'])
	->addVar('ldap_removed_userdirectoryids', $data['ldap_removed_userdirectoryids'])
	->addVar('mfa_removed_mfaids', $data['mfa_removed_mfaids'])
	->setId('authentication-form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->disablePasswordAutofill();

// Authentication general fields.
$auth_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Default authentication')),
		new CFormField(
			(new CRadioButtonList('authentication_type', (int) $data['authentication_type']))
				->addValue(_x('Internal', 'authentication'), ZBX_AUTH_INTERNAL)
				->addValue(_('LDAP'), ZBX_AUTH_LDAP)
				->setModern(true)
				->removeId()
		)
	])
	->addItem([
		new CLabel([
			_('Deprovisioned users group'),
			makeHelpIcon(_('Only disabled group can be set for deprovisioned users.'))
		]),
		new CFormField(
			(new CMultiSelect([
				'name' => 'disabled_usrgrpid',
				'object_name' => 'usersGroups',
				'data' => $data['disabled_usrgrpid_ms'],
				'multiple' => false,
				'popup' => [
					'parameters' => [
						'srctbl' => 'usrgrp',
						'srcfld1' => 'usrgrpid',
						'srcfld2' => 'name',
						'dstfrm' => $form->getId(),
						'dstfld1' => 'disabled_usrgrpid',
						'group_status' => GROUP_STATUS_DISABLED
					]
				]
			]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		)
	])
	->addItem(
		new CFormField(
			(new CTag('h4', true, _('Password policy')))->addClass('input-section-header')
		)
	)
	->addItem([
		new CLabel(_('Minimum password length'), 'passwd_min_length'),
		new CFormField(
			(new CNumericBox('passwd_min_length', $data['passwd_min_length'], 2, false, false, false))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		)
	])
	->addItem([
		new CLabel([
			_('Password must contain'),
			makeHelpIcon([
				_('Password requirements:'),
				(new CList([
					new CListItem([
						_('must contain at least one lowercase and one uppercase Latin letter'),
						' (', (new CSpan('A-Z'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ', ',
						(new CSpan('a-z'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ')'
					]),
					new CListItem([
						_('must contain at least one digit'),
						' (', (new CSpan('0-9'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ')'
					]),
					new CListItem([
						_('must contain at least one special character'),
						' (', (new CSpan(' !"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ')'
					])
				]))->addClass(ZBX_STYLE_LIST_DASHED)
			])
		]),
		new CFormField(
			(new CList([
				(new CCheckBox('passwd_check_rules[]', PASSWD_CHECK_CASE))
					->setLabel(_('an uppercase and a lowercase Latin letter'))
					->setChecked(($data['passwd_check_rules'] & PASSWD_CHECK_CASE) == PASSWD_CHECK_CASE)
					->setUncheckedValue(0x00)
					->setId('passwd_check_rules_case'),
				(new CCheckBox('passwd_check_rules[]', PASSWD_CHECK_DIGITS))
					->setLabel(_('a digit'))
					->setChecked(($data['passwd_check_rules'] & PASSWD_CHECK_DIGITS) == PASSWD_CHECK_DIGITS)
					->setUncheckedValue(0x00)
					->setId('passwd_check_rules_digits'),
				(new CCheckBox('passwd_check_rules[]', PASSWD_CHECK_SPECIAL))
					->setLabel(_('a special character'))
					->setChecked(($data['passwd_check_rules'] & PASSWD_CHECK_SPECIAL) == PASSWD_CHECK_SPECIAL)
					->setUncheckedValue(0x00)
					->setId('passwd_check_rules_special')
			]))->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
		)
	])
	->addItem([
		new CLabel([
			_('Avoid easy-to-guess passwords'),
			makeHelpIcon([
				_('Password requirements:'),
				(new CList([
					_("must not contain user's name, surname or username"),
					_('must not be one of common or context-specific passwords')
				]))->addClass(ZBX_STYLE_LIST_DASHED)
			])
		], 'passwd_check_rules_simple'),
		new CFormField(
			(new CCheckBox('passwd_check_rules[]', PASSWD_CHECK_SIMPLE))
				->setChecked(($data['passwd_check_rules'] & PASSWD_CHECK_SIMPLE) == PASSWD_CHECK_SIMPLE)
				->setUncheckedValue(0x00)
				->setId('passwd_check_rules_simple')
		)
	]);

// LDAP authentication fields.
$ldap_auth_enabled = $data['ldap_error'] === '' && $data['ldap_auth_enabled'] == ZBX_AUTH_LDAP_ENABLED;
$form->addVar('ldap_default_row_index', $data['ldap_default_row_index']);
$ldap_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Enable LDAP authentication'), 'ldap_auth_enabled'),
		new CFormField($data['ldap_error']
			? (new CLabel($data['ldap_error']))->addClass(ZBX_STYLE_RED)
			: (new CCheckBox('ldap_auth_enabled', ZBX_AUTH_LDAP_ENABLED))
				->setChecked($data['ldap_auth_enabled'] == ZBX_AUTH_LDAP_ENABLED)
				->setUncheckedValue(ZBX_AUTH_LDAP_DISABLED)
		)
	])
	->addItem([
		new CLabel(_('Enable JIT provisioning'), 'ldap_jit_status'),
		new CFormField(
			(new CCheckBox('ldap_jit_status', JIT_PROVISIONING_ENABLED))
				->setChecked($data['ldap_jit_status'] == JIT_PROVISIONING_ENABLED)
				->setUncheckedValue(JIT_PROVISIONING_DISABLED)
				->setEnabled($ldap_auth_enabled)
		)
	])
	->addItem([
		(new CLabel(_('Servers')))->setAsteriskMark(),
		new CFormField(
			(new CDiv(
				(new CTable())
					->setId('ldap-servers')
					->addClass($ldap_auth_enabled ? null : ZBX_STYLE_DISABLED)
					->setHeader(
						(new CRowHeader([
							new CColHeader(_('Name')),
							new CColHeader(_('Host')),
							(new CColHeader(_('User groups')))->addClass(ZBX_STYLE_NOWRAP),
							_('Default'),
							_('Action')
						]))->addClass(ZBX_STYLE_GREY)
					)
					->addItem(
						(new CTag('tfoot', true))
							->addItem(
								(new CCol(
									(new CButtonLink(_('Add')))->addClass('js-add')
								))->setColSpan(5)
							)
					)->addStyle('width: 100%;')
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	])
	->addItem([
		new CLabel(_('Case-sensitive login'), 'ldap_case_sensitive'),
		new CFormField(
			(new CCheckBox('ldap_case_sensitive', ZBX_AUTH_CASE_SENSITIVE))
				->setChecked($data['ldap_case_sensitive'] == ZBX_AUTH_CASE_SENSITIVE)
				->setUncheckedValue(ZBX_AUTH_CASE_INSENSITIVE)
				->setEnabled($ldap_auth_enabled)
		)
	])
	->addItem([
		new CLabel(_('Provisioning period'), 'jit_provision_interval'),
		new CFormField(
			(new CTextBox('jit_provision_interval', $data['jit_provision_interval']))
				->setWidth(ZBX_TEXTAREA_4DIGITS_WIDTH)
				->setEnabled($ldap_auth_enabled && $data['ldap_jit_status'] == JIT_PROVISIONING_ENABLED)
		)
	]);

// SAML authentication fields.
$view_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'authentication.edit')
	->getUrl();
$saml_auth_enabled = $data['saml_auth_enabled'] == ZBX_AUTH_SAML_ENABLED;
$saml_provisioning = $data['saml_provision_status'] == JIT_PROVISIONING_ENABLED;
$saml_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Enable SAML authentication'), 'saml_auth_enabled'),
		new CFormField($data['saml_error']
			? (new CLabel($data['saml_error']))->addClass(ZBX_STYLE_RED)
			: (new CCheckBox('saml_auth_enabled', ZBX_AUTH_SAML_ENABLED))
				->setChecked($saml_auth_enabled)
				->setUncheckedValue(ZBX_AUTH_SAML_DISABLED)
		)
	])
	->addItem([
		new CLabel(_('Enable JIT provisioning'), 'saml_jit_status'),
		new CFormField(
			(new CCheckBox('saml_jit_status', JIT_PROVISIONING_ENABLED))
				->setChecked($data['saml_jit_status'] == JIT_PROVISIONING_ENABLED)
				->setUncheckedValue(JIT_PROVISIONING_DISABLED)
				->setReadonly(!$saml_auth_enabled)
				->addClass('saml-enabled')
		)
	])
	->addItem([
		(new CLabel(_('IdP entity ID'), 'idp_entityid'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('idp_entityid', $data['idp_entityid'], !$saml_auth_enabled,
				DB::getFieldLength('userdirectory_saml', 'idp_entityid')
			))
				->addClass('saml-enabled')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('SSO service URL'), 'sso_url'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('sso_url', $data['sso_url'], !$saml_auth_enabled,
				DB::getFieldLength('userdirectory_saml', 'sso_url'))
			)
				->addClass('saml-enabled')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel(_('SLO service URL'), 'slo_url'),
		new CFormField(
			(new CTextBox('slo_url', $data['slo_url'], !$saml_auth_enabled,
				DB::getFieldLength('userdirectory_saml', 'slo_url'))
			)
				->addClass('saml-enabled')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	])
	->addItem([
		(new CLabel(_('Username attribute'), 'username_attribute'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('username_attribute', $data['username_attribute'], !$saml_auth_enabled,
				DB::getFieldLength('userdirectory_saml', 'username_attribute')
			))
				->addClass('saml-enabled')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('SP entity ID'), 'sp_entityid'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('sp_entityid', $data['sp_entityid'], !$saml_auth_enabled,
				DB::getFieldLength('userdirectory_saml', 'sp_entityid')
			))
				->addClass('saml-enabled')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel(_('SP name ID format'), 'nameid_format'),
		new CFormField(
			(new CTextBox('nameid_format', $data['nameid_format'], !$saml_auth_enabled,
				DB::getFieldLength('userdirectory_saml', 'nameid_format')
			))
				->addClass('saml-enabled')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient')
		)
	])
	->addItem([
		new CLabel(_('Sign')),
		new CFormField(
			(new CList([
				(new CCheckBox('sign_messages'))
					->setLabel(_('Messages'))
					->setChecked($data['sign_messages'] == 1)
					->setUncheckedValue(0)
					->setReadonly(!$saml_auth_enabled)
					->addClass('saml-enabled'),
				(new CCheckBox('sign_assertions'))
					->setLabel(_('Assertions'))
					->setChecked($data['sign_assertions'] == 1)
					->setUncheckedValue(0)
					->setReadonly(!$saml_auth_enabled)
					->addClass('saml-enabled'),
				(new CCheckBox('sign_authn_requests'))
					->setLabel(_('AuthN requests'))
					->setChecked($data['sign_authn_requests'] == 1)
					->setUncheckedValue(0)
					->setReadonly(!$saml_auth_enabled)
					->addClass('saml-enabled'),
				(new CCheckBox('sign_logout_requests'))
					->setLabel(_('Logout requests'))
					->setChecked($data['sign_logout_requests'] == 1)
					->setUncheckedValue(0)
					->setReadonly(!$saml_auth_enabled)
					->addClass('saml-enabled'),
				(new CCheckBox('sign_logout_responses'))
					->setLabel(_('Logout responses'))
					->setChecked($data['sign_logout_responses'] == 1)
					->setUncheckedValue(0)
					->setReadonly(!$saml_auth_enabled)
					->addClass('saml-enabled')
			]))->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
		)
	])
	->addItem([
		new CLabel(_('Encrypt')),
		new CFormField(
			(new CList([
				(new CCheckBox('encrypt_nameid'))
					->setLabel(_('Name ID'))
					->setChecked($data['encrypt_nameid'] == 1)
					->setUncheckedValue(0)
					->setReadonly(!$saml_auth_enabled)
					->addClass('saml-enabled'),
				(new CCheckBox('encrypt_assertions'))
					->setLabel(_('Assertions'))
					->setChecked($data['encrypt_assertions'] == 1)
					->setUncheckedValue(0)
					->setReadonly(!$saml_auth_enabled)
					->addClass('saml-enabled')
			]))->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
		)
	])
	->addItem([
		new CLabel(_('Case-sensitive login'), 'saml_case_sensitive'),
		new CFormField(
			(new CCheckBox('saml_case_sensitive'))
				->setChecked($data['saml_case_sensitive'] == ZBX_AUTH_CASE_SENSITIVE)
				->setUncheckedValue(ZBX_AUTH_CASE_INSENSITIVE)
				->setReadonly(!$saml_auth_enabled)
				->addClass('saml-enabled')
		)
	])
	->addItem([
		new CLabel(_('Configure JIT provisioning'), 'saml_provision_status'),
		new CFormField(
			(new CCheckBox('saml_provision_status'))
				->setChecked($saml_provisioning)
				->setUncheckedValue(JIT_PROVISIONING_DISABLED)
				->setReadonly(!$saml_auth_enabled)
				->addClass('saml-enabled')
		)
	])
	->addItem([
		(new CLabel(_('Group name attribute'), 'saml_group_name'))
			->setAsteriskMark()
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status'),
		(new CFormField(
			(new CTextBox('saml_group_name', $data['saml_group_name'], !$saml_auth_enabled,
				DB::getFieldLength('userdirectory_saml', 'group_name')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->addClass('saml-enabled')
		))
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status')
	])
	->addItem([
		(new CLabel(_('User name attribute'), 'saml_user_username'))
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status'),
		(new CFormField(
			(new CTextBox('saml_user_username', $data['saml_user_username'], !$saml_auth_enabled,
				DB::getFieldLength('userdirectory_saml', 'user_username')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->addClass('saml-enabled')
		))
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status')
	])
	->addItem([
		(new CLabel(_('User last name attribute'), 'saml_user_lastname'))
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status'),
		(new CFormField(
			(new CTextBox('saml_user_lastname', $data['saml_user_lastname'], !$saml_auth_enabled,
				DB::getFieldLength('userdirectory_saml', 'user_lastname')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->addClass('saml-enabled')
		))
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status')
	])
	->addItem([
		(new CLabel(_('User group mapping')))
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status')
			->setAsteriskMark(),
		(new CFormField(
			(new CDiv(
				(new CTable())
					->setId('saml-group-table')
					->setAttribute('style', 'width: 100%;')
					->addClass($saml_auth_enabled ? null : ZBX_STYLE_DISABLED)
					->setHeader(
						(new CRowHeader([
							_('SAML group pattern'), _('User groups'), _('User role'), _('Action')
						]))->addClass(ZBX_STYLE_GREY)
					)
					->addItem(
						(new CTag('tfoot', true))->addItem(
							(new CCol(
								(new CButtonLink(_('Add')))
									->addClass($saml_auth_enabled ? null : ZBX_STYLE_DISABLED)
									->addClass('js-add')
									->setEnabled($data['saml_enabled'])
							))->setColSpan(5)
						)
					)
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status')
	])
	->addItem([
		(new CLabel([
			_('Media type mapping'),
			makeHelpIcon(
				_("Map user's SAML media attributes (e.g. email) to Zabbix user media for sending notifications.")
			)
		]))
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status'),
		(new CFormField(
			(new CDiv(
				(new CTable())
					->setId('saml-media-type-mapping-table')
					->addClass($saml_auth_enabled ? null : ZBX_STYLE_DISABLED)
					->setHeader(
						(new CRowHeader([
							_('Name'),
							_('Media type'),
							_('Attribute'),
							(new CColHeader(_('Action')))->setWidth('12%')
						]))->addClass(ZBX_STYLE_GREY)
					)
					->addItem(
						(new CTag('tfoot', true))->addItem(
							(new CCol(
								(new CButtonLink(_('Add')))
									->addClass($saml_auth_enabled ? null : ZBX_STYLE_DISABLED)
									->addClass('js-add')
									->setEnabled($data['saml_enabled'])
							))->setColSpan(5)
						)
					)
					->addStyle('width: 100%;')
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status')
	])
	->addItem([
		(new CLabel(_('Enable SCIM provisioning'), 'scim_status'))
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status'),
		(new CFormField(
			(new CCheckBox('scim_status', ZBX_AUTH_SCIM_PROVISIONING_ENABLED))
				->setChecked($data['scim_status'] == ZBX_AUTH_SCIM_PROVISIONING_ENABLED)
				->setUncheckedValue(ZBX_AUTH_SCIM_PROVISIONING_DISABLED)
				->setReadonly(!$saml_auth_enabled)
				->addClass('saml-enabled')
		))
			->addClass($saml_provisioning ? null : ZBX_STYLE_DISPLAY_NONE)
			->addClass('saml-provision-status')
	]);

$form->addVar('mfa_default_row_index', $data['mfa_default_row_index']);

$mfa_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Enable multi-factor authentication'), 'mfa_status'),
		new CFormField(
			(new CCheckBox('mfa_status', MFA_ENABLED))
				->setChecked($data['mfa_status'] == MFA_ENABLED)
				->setUncheckedValue(MFA_DISABLED)
		)
	])
	->addItem([
		(new CLabel(_('Methods')))->setAsteriskMark(),
		new CFormField(
			(new CDiv(
				(new CTable())
					->setId('mfa-methods')
					->setHeader(
						(new CRowHeader([
							new CColHeader(_('Name')),
							new CColHeader(_('Type')),
							(new CColHeader(_('User groups')))->addClass(ZBX_STYLE_NOWRAP),
							_('Default'),
							_('Action')
						]))->addClass(ZBX_STYLE_GREY)
					)
					->addItem(
						(new CTag('tfoot', true))
							->addItem(
								(new CCol(
									(new CButtonLink(_('Add')))->addClass('js-add')
								))->setColSpan(5)
							)
					)->addStyle('width: 100%')
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	]);

$tab_view = (new CTabView())
	->setSelected($data['form_refresh'] != 0 ? null : 0)
	->addTab('auth', _('Authentication'), $auth_tab);

if ($data['is_http_auth_allowed']) {
	// HTTP authentication fields.
	$http_tab = (new CFormGrid())
		->addItem([
			new CLabel([_('Enable HTTP authentication'),
				makeHelpIcon(
					_('If HTTP authentication is enabled, all users (even with frontend access set to LDAP/Internal) will be authenticated by the web server, not by Zabbix.')
				)
			], 'http_auth_enabled'),
			new CFormField(
				(new CCheckBox('http_auth_enabled', ZBX_AUTH_HTTP_ENABLED))
					->setChecked($data['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED)
					->setUncheckedValue(ZBX_AUTH_HTTP_DISABLED)
			)
		])
		->addItem([
			new CLabel(_('Default login form'), 'label-http-login-form'),
			new CFormField(
				(new CSelect('http_login_form'))
					->setFocusableElementId('label-http-login-form')
					->setValue($data['http_login_form'])
					->addOptions(CSelect::createOptionsFromArray([
						ZBX_AUTH_FORM_ZABBIX => _('Zabbix login form'),
						ZBX_AUTH_FORM_HTTP => _('HTTP login form')
					]))
					->setDisabled($data['http_auth_enabled'] != ZBX_AUTH_HTTP_ENABLED)
			)
		])
		->addItem([
			new CLabel(_('Remove domain name'), 'http_strip_domains'),
			new CFormField(
				(new CTextBox('http_strip_domains', $data['http_strip_domains'], false,
					DB::getFieldLength('config', 'http_strip_domains')
				))
					->setEnabled($data['http_auth_enabled'])
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Case-sensitive login'), 'http_case_sensitive'),
			new CFormField(
				(new CCheckBox('http_case_sensitive', ZBX_AUTH_CASE_SENSITIVE))
					->setChecked($data['http_case_sensitive'] == ZBX_AUTH_CASE_SENSITIVE)
					->setEnabled($data['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED)
					->setUncheckedValue(ZBX_AUTH_CASE_INSENSITIVE)
			)
		]);

	$tab_view->addTab('http', _('HTTP settings'), $http_tab, TAB_INDICATOR_AUTH_HTTP);
}

$tab_view
	->addTab('ldap', _('LDAP settings'), $ldap_tab, TAB_INDICATOR_AUTH_LDAP)
	->addTab('saml', _('SAML settings'), $saml_tab, TAB_INDICATOR_AUTH_SAML)
	->addTab('mfa', _('MFA settings'), $mfa_tab, TAB_INDICATOR_AUTH_MFA)
	->setFooter(makeFormFooter(
		(new CSubmit('update', _('Update')))
	));

$form->addItem($tab_view);

(new CHtmlPage())
	->setTitle(_('Authentication'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::USERS_AUTHENTICATION_EDIT))
	->addItem($form)
	->show();

$templates = [];
// SAML provisioning group row template.
$templates['saml_provisioning_group_row'] = (string) (new CRow([
	[
		(new CLink('#{name}', 'javascript:void(0);'))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addClass('js-edit'),
		(new CVar('saml_provision_groups[#{row_index}][name]', '#{name}'))->removeId()
	],
	(new CCol('#{user_group_names}'))->addClass(ZBX_STYLE_WORDBREAK),
	(new CCol('#{role_name}'))->addClass(ZBX_STYLE_WORDBREAK),
	(new CButtonLink(_('Remove')))->addClass('js-remove')
]))->setAttribute('data-row_index', '#{row_index}');
// SAML provisioning medias row template.
$templates['saml_provisioning_media_row'] = (string) (new CRow([
	[
		(new CLink('#{name}', 'javascript:void(0);'))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addClass('js-edit'),
		(new CVar('saml_provision_media[#{row_index}][userdirectory_mediaid]', '#{userdirectory_mediaid}'))->removeId(),
		(new CVar('saml_provision_media[#{row_index}][name]', '#{name}'))->removeId(),
		(new CVar('saml_provision_media[#{row_index}][mediatypeid]', '#{mediatypeid}'))->removeId(),
		(new CVar('saml_provision_media[#{row_index}][attribute]', '#{attribute}'))->removeId(),
		(new CVar('saml_provision_media[#{row_index}][period]', '#{period}'))->removeId(),
		(new CVar('saml_provision_media[#{row_index}][severity]', '#{severity}'))->removeId(),
		(new CVar('saml_provision_media[#{row_index}][active]', '#{active}'))->removeId()
	],
	(new CCol('#{mediatype_name}'))->addClass(ZBX_STYLE_WORDBREAK),
	(new CCol('#{attribute}'))->addClass(ZBX_STYLE_WORDBREAK),
	(new CButtonLink(_('Remove')))->addClass('js-remove')
]))->setAttribute('data-row_index', '#{row_index}');
// LDAP servers list row.
$templates['ldap_servers_row'] = (string) (new CRow([
	[
		(new CLink('#{name}', 'javascript:void(0);'))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addClass('js-edit'),
		(new CVar('ldap_servers[#{row_index}][userdirectoryid]', '#{userdirectoryid}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][name]', '#{name}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][host]', '#{host}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][port]', '#{port}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][base_dn]', '#{base_dn}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][search_attribute]', '#{search_attribute}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][search_filter]', '#{search_filter}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][start_tls]', '#{start_tls}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][bind_dn]', '#{bind_dn}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][bind_password]', '#{bind_password}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][description]', '#{description}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][provision_status]', '#{provision_status}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][group_basedn]', '#{group_basedn}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][group_name]', '#{group_name}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][group_member]', '#{group_member}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][user_ref_attr]', '#{user_ref_attr}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][group_filter]', '#{group_filter}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][group_membership]', '#{group_membership}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][user_username]', '#{user_username}'))->removeId(),
		(new CVar('ldap_servers[#{row_index}][user_lastname]', '#{user_lastname}'))->removeId()
	],
	(new CCol('#{host}'))->addClass(ZBX_STYLE_WORDBREAK),
	(new CCol('#{usrgrps}'))->addClass('js-ldap-usergroups'),
	[
		(new CInput('radio', 'ldap_default_row_index', '#{row_index}'))
			->addClass(ZBX_STYLE_CHECKBOX_RADIO)
			->setId('ldap_default_row_index_#{row_index}'),
		(new CLabel(new CSpan(), 'ldap_default_row_index_#{row_index}'))->addClass(ZBX_STYLE_WORDWRAP)
	],
	(new CButtonLink(_('Remove')))->addClass('js-remove')
]))->setAttribute('data-row_index', '#{row_index}');

$templates['mfa_methods_row'] = (string) (new CRow([
	[
		(new CLink('#{name}', 'javascript:void(0);'))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addClass('js-edit'),
		(new CVar('mfa_methods[#{row_index}][mfaid]', '#{mfaid}'))->removeId(),
		(new CVar('mfa_methods[#{row_index}][type]', '#{type}'))->removeId(),
		(new CVar('mfa_methods[#{row_index}][name]', '#{name}'))->removeId(),
		(new CVar('mfa_methods[#{row_index}][hash_function]', '#{hash_function}'))->removeId(),
		(new CVar('mfa_methods[#{row_index}][code_length]', '#{code_length}'))->removeId(),
		(new CVar('mfa_methods[#{row_index}][api_hostname]', '#{api_hostname}'))->removeId(),
		(new CVar('mfa_methods[#{row_index}][clientid]', '#{clientid}'))->removeId(),
		(new CVar('mfa_methods[#{row_index}][client_secret]', '#{client_secret}'))->removeId()
	],
	(new CCol('#{type_name}'))->addClass(ZBX_STYLE_WORDBREAK),
	(new CCol('#{usrgrps}'))->addClass('js-mfa-usergroups'),
	[
		(new CInput('radio', 'mfa_default_row_index', '#{row_index}'))
			->addClass(ZBX_STYLE_CHECKBOX_RADIO)
			->setId('mfa_default_row_index_#{row_index}'),
		(new CLabel(new CSpan(), 'mfa_default_row_index_#{row_index}'))->addClass(ZBX_STYLE_WORDWRAP)
	],
	(new CButtonLink(_('Remove')))->addClass('js-remove')
]))->setAttribute('data-row_index', '#{row_index}');

(new CScriptTag(
	'view.init('.json_encode([
		'ldap_servers' => $data['ldap_servers'],
		'ldap_default_row_index' => $data['ldap_default_row_index'],
		'db_authentication_type' => $data['db_authentication_type'],
		'saml_provision_groups' => $data['saml_provision_groups'],
		'saml_provision_media' => $data['saml_provision_media'],
		'templates' => $templates,
		'mfa_methods' => $data['mfa_methods'],
		'mfa_default_row_index' => $data['mfa_default_row_index'],
		'is_http_auth_allowed' => $data['is_http_auth_allowed']
	]).');'
))
	->setOnDocumentReady()
	->show();
