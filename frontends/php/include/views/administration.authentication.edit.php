<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


// Authentication general fields and HTTP authentication fields.
$auth_tab = (new CFormList('list_auth'))
	->addRow(_('Default authentication'),
		(new CRadioButtonList('authentication_type', (int)$data['authentication_type']))
			->setAttribute('autofocus', 'autofocus')
			->addValue(_x('Internal', 'authentication'), ZBX_AUTH_INTERNAL)
			->addValue(_('LDAP'), ZBX_AUTH_LDAP)
			->setModern(true)
			->removeId()
	)
	->addRow(_('Enable HTTP authentication'),
		(new CCheckBox('http_auth_enabled', ZBX_AUTH_HTTP_ENABLED))
			->setChecked($data['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED)
			->onChange('jQuery("input,select", ".http_auth").attr("disabled", !this.checked)')
	)
	->addRow(_('Default login form'),
		(new CComboBox('http_login_form', $data['http_login_form'], null, [
			ZBX_AUTH_FORM_ZABBIX => _('Zabbix login form'),
			ZBX_AUTH_FORM_HTTP => _('HTTP login form')
		]))->setEnabled($data['http_auth_enabled']),
		null, 'http_auth'
	)
	->addRow(_('Remove domain name'),
		(new CTextBox('http_strip_domains', $data['http_strip_domains']))
			->setEnabled($data['http_auth_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		null, 'http_auth'
	)
	->addRow(_('Case sensitive login'), (new CCheckBox('login_case_sensitive', ZBX_AUTH_CASE_MATCH))
		->setChecked($data['login_case_sensitive'] == ZBX_AUTH_CASE_MATCH)
);

// LDAP configuration fields.
if ($data['change_bind_password'] || zbx_empty($data['ldap_bind_password'])) {
	$password_box = [
		new CVar('change_bind_password', 1),
		(new CPassBox('ldap_bind_password', $data['ldap_bind_password']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	];
}
else {
	$password_box = (new CSubmitButton(_('Change password'), 'change_bind_password', 1))
		->setEnabled($data['ldap_enabled'])
		->onClick('jQuery("[name=action]").val("'.$data['action_passw_change'].'")')
		->addClass(ZBX_STYLE_BTN_GREY);
}

$ldap_tab = (new CFormList('list_ldap'));

if ($data['ldap_error']) {
	$ldap_tab->addRow('', $data['ldap_error']
		? (new CLabel($data['ldap_error']))->addClass(ZBX_STYLE_RED)
		: null
	);
}

$ldap_tab
	->addRow((new CLabel(_('LDAP host'), 'ldap_host')),
		(new CTextBox('ldap_host', $data['ldap_host'], !$data['ldap_enabled']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Port'),
		(new CNumericBox('ldap_port', $data['ldap_port'], 5, !$data['ldap_enabled']))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Base DN'), 'ldap_base_dn')),
		(new CTextBox('ldap_base_dn', $data['ldap_base_dn'], !$data['ldap_enabled']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Search attribute'), 'ldap_search_attribute')),
		(new CTextBox('ldap_search_attribute', $data['ldap_search_attribute'],  !$data['ldap_enabled'], 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Bind DN'),
		(new CTextBox('ldap_bind_dn', $data['ldap_bind_dn'],  !$data['ldap_enabled']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Bind password'), $password_box)
	->addRow(_('Test authentication'), ' ['._('must be a valid LDAP user').']')
	->addRow(_('Login'), $data['users_list']
		? new CComboBox('user', $data['user'], null, $data['users_list'])
		: (new CTextBox('user', $data['user'], true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('User password'), 'user_password')),
		(new CPassBox('user_password'))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

$confirm_script = 'return jQuery("[name=authentication_type]:checked").val() == '.$data['config']['authentication_type'].
	'|| confirm('.
			CJs::encodeJson(_('Switching authentication method will reset all except this session! Continue?'))
		.')';

(new CWidget())
	->setTitle(_('Authentication'))
	->addItem((new CForm())
		->addVar('action', $data['action_submit'])
		->setName('form_auth')
		->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
		->addItem((new CTabView())
			// Change 'Test' button enabled state according active tab: 'Authentication' or 'LDAP configuration'.
			->onTabChange($data['ldap_enabled'] ? 'jQuery("[name=test]").attr("disabled", tab != 1);' : '')
			->addTab('auth', _('Authentication'), $auth_tab)
			->addTab('ldap', _('LDAP settings'), $ldap_tab)
			->setFooter(makeFormFooter(
				(new CSubmit('update', _('Update')))->onClick($confirm_script),
				[(new CSubmitButton(_('Test'), 'test', 1))
					->setEnabled($data['active_tab'] == 1 && $data['ldap_enabled'])
				]
			))
))->show();
