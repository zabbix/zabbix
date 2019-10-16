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


$this->includeJSfile('app/views/administration.authentication.edit.js.php');

// Authentication general fields and HTTP authentication fields.
$auth_tab = (new CFormList('list_auth'))
	->addRow(new CLabel(_('Default authentication'), 'authentication_type'),
		(new CRadioButtonList('authentication_type', (int) $data['authentication_type']))
			->setAttribute('autofocus', 'autofocus')
			->addValue(_x('Internal', 'authentication'), ZBX_AUTH_INTERNAL)
			->addValue(_('LDAP'), ZBX_AUTH_LDAP)
			->setModern(true)
			->removeId()
	);

// HTTP Authentication fields.
$http_tab = (new CFormList('list_http'))
	->addRow(new CLabel(_('Enable HTTP authentication'), 'http_auth_enabled'),
		(new CCheckBox('http_auth_enabled', ZBX_AUTH_HTTP_ENABLED))
			->setChecked($data['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED)
			->setUncheckedValue(ZBX_AUTH_HTTP_DISABLED)
	)
	->addRow(new CLabel(_('Default login form'), 'http_login_form'),
		(new CComboBox('http_login_form', $data['http_login_form'], null, [
			ZBX_AUTH_FORM_ZABBIX => _('Zabbix login form'),
			ZBX_AUTH_FORM_HTTP => _('HTTP login form')
		]))->setEnabled($data['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED)
	)
	->addRow(new CLabel(_('Remove domain name'), 'http_strip_domains'),
		(new CTextBox('http_strip_domains', $data['http_strip_domains']))
			->setEnabled($data['http_auth_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(new CLabel(_('Case sensitive login'), 'http_case_sensitive'),
		(new CCheckBox('http_case_sensitive', ZBX_AUTH_CASE_SENSITIVE))
			->setChecked($data['http_case_sensitive'] == ZBX_AUTH_CASE_SENSITIVE)
			->setEnabled($data['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED)
			->setUncheckedValue(ZBX_AUTH_CASE_INSENSITIVE)
);

// LDAP configuration fields.
if ($data['change_bind_password']) {
	$password_box = [
		new CVar('change_bind_password', 1),
		(new CPassBox('ldap_bind_password', $data['ldap_bind_password']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	];
}
else {
	$password_box = [
		new CVar('action_passw_change', $data['action_passw_change']),
		(new CButton('change_bind_password', _('Change password')))
			->setEnabled($data['ldap_enabled'])
			->addClass(ZBX_STYLE_BTN_GREY)
	];
}

$ldap_tab = (new CFormList('list_ldap'))
	->addRow(new CLabel(_('Enable LDAP authentication'), 'ldap_configured'),
		$data['ldap_error']
		? (new CLabel($data['ldap_error']))->addClass(ZBX_STYLE_RED)
		: (new CCheckBox('ldap_configured', ZBX_AUTH_LDAP_ENABLED))
			->setChecked($data['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED)
			->setUncheckedValue(ZBX_AUTH_LDAP_DISABLED)
	)
	->addRow((new CLabel(_('LDAP host'), 'ldap_host'))->setAsteriskMark(),
		(new CTextBox('ldap_host', $data['ldap_host']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Port'), 'ldap_port'))->setAsteriskMark(),
		(new CNumericBox('ldap_port', $data['ldap_port'], 5))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Base DN'), 'ldap_base_dn'))->setAsteriskMark(),
		(new CTextBox('ldap_base_dn', $data['ldap_base_dn']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Search attribute'), 'ldap_search_attribute'))->setAsteriskMark(),
		(new CTextBox('ldap_search_attribute', $data['ldap_search_attribute']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(new CLabel(_('Bind DN'), 'ldap_bind_dn'),
		(new CTextBox('ldap_bind_dn', $data['ldap_bind_dn']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(new CLabel(_('Case sensitive login'), 'ldap_case_sensitive'),
		(new CCheckBox('ldap_case_sensitive', ZBX_AUTH_CASE_SENSITIVE))
			->setChecked($data['ldap_case_sensitive'] == ZBX_AUTH_CASE_SENSITIVE)
			->setEnabled($data['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED)
			->setUncheckedValue(ZBX_AUTH_CASE_INSENSITIVE)
	)
	->addRow(new CLabel(_('Bind password'), 'ldap_bind_password'), $password_box)
	->addRow(_('Test authentication'), ' ['._('must be a valid LDAP user').']')
	->addRow((new CLabel(_('Login'), 'ldap_test_user'))->setAsteriskMark(),
		(new CTextBox('ldap_test_user', $data['ldap_test_user']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('User password'), 'ldap_test_password'))->setAsteriskMark(),
		(new CPassBox('ldap_test_password', $data['ldap_test_password']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
);

(new CWidget())
	->setTitle(_('Authentication'))
	->addItem((new CForm())
		->addVar('action', $data['action_submit'])
		->addVar('db_authentication_type', $data['db_authentication_type'])
		->setName('form_auth')
		->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
		->disablePasswordAutofill()
		->addItem((new CTabView())
			->setSelected($data['form_refresh'] ? null : 0)
			->addTab('auth', _('Authentication'), $auth_tab)
			->addTab('http', _('HTTP settings'), $http_tab)
			->addTab('ldap', _('LDAP settings'), $ldap_tab)
			->setFooter(makeFormFooter(
				(new CSubmit('update', _('Update'))),
				[(new CSubmitButton(_('Test'), 'ldap_test', 1))
					->addStyle(($data['form_refresh'] && get_cookie('tab', 0) == 2) ? '' : 'display: none')
					->setEnabled($data['ldap_enabled'])
				]
			))
			->onTabChange('jQuery("[name=ldap_test]")[(ui.newTab.index() == 2) ? "show" : "hide"]()')
))->show();
