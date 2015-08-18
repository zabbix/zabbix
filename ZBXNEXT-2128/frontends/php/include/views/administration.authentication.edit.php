<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


$widget = (new CWidget())->setTitle(_('Authentication'));

// create form
$authenticationForm = (new CForm())->setName('authenticationForm');

// create form list
$authenticationFormList = new CFormList('authenticationList');

// append config radio buttons to form list
$authenticationFormList->addRow(_('Default authentication'),
	(new CRadioButtonList('config', (int) $this->data['config']['authentication_type']))
		->addValue(_x('Internal', 'authentication'), ZBX_AUTH_INTERNAL, null, 'submit()')
		->addValue(_('LDAP'), ZBX_AUTH_LDAP, null, 'submit()')
		->addValue(_('HTTP'), ZBX_AUTH_HTTP, null, 'submit()')
		->setModern(true)
);

// append LDAP fields to form list
if ($this->data['config']['authentication_type'] == ZBX_AUTH_LDAP) {
	if ($this->data['user_list']) {
		$userComboBox = new CComboBox('user', $this->data['user']);
		foreach ($this->data['user_list'] as $user) {
			if (check_perm2login($user['userid']) && check_perm2system($user['userid'])) {
				$userComboBox->addItem($user['alias'], $user['alias']);
			}
		}
	}
	else {
		$userComboBox = (new CTextBox('user', $this->data['user'], true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
	}

	$authenticationFormList->addRow(
		_('LDAP host'),
		(new CTextBox('ldap_host', $this->data['config']['ldap_host']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
	$authenticationFormList->addRow(
		_('Port'),
		(new CNumericBox('ldap_port', $this->data['config']['ldap_port'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);
	$authenticationFormList->addRow(
		_('Base DN'),
		(new CTextBox('ldap_base_dn', $this->data['config']['ldap_base_dn']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
	$authenticationFormList->addRow(
		_('Search attribute'),
		(new CTextBox(
			'ldap_search_attribute',
			(zbx_empty($this->data['config']['ldap_search_attribute']) && $this->data['form_refresh'] == 0)
				? 'uid'
				: $this->data['config']['ldap_search_attribute'],
			false,
			128
		))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
	$authenticationFormList->addRow(
		_('Bind DN'),
		(new CTextBox('ldap_bind_dn', $this->data['config']['ldap_bind_dn']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

	// bind password
	if (isset($this->data['change_bind_password']) || zbx_empty($this->data['config']['ldap_bind_password'])) {
		$authenticationForm->addVar('change_bind_password', 1);
		$authenticationFormList->addRow(
			_('Bind password'),
			(new CPassBox('ldap_bind_password'))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);
	}
	else {
		$authenticationFormList->addRow(
			_('Bind password'),
			(new CSubmit('change_bind_password', _('Change password')))->addClass(ZBX_STYLE_BTN_GREY)
		);
	}

	$authenticationFormList->addRow(_('Test authentication'), ' ['._('must be a valid LDAP user').']');
	$authenticationFormList->addRow(_('Login'), $userComboBox);
	$authenticationFormList->addRow(_('User password'), (new CPassBox('user_password'))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH));
}

// append form list to tab
$authenticationTab = new CTabView();
$authenticationTab->addTab('authenticationTab', $this->data['title'], $authenticationFormList);

// create save button
$saveButton = new CSubmit('update', _('Update'));
if ($this->data['is_authentication_type_changed']) {
	$saveButton->onClick('javascript: if (confirm('.
		CJs::encodeJson(_('Switching authentication method will reset all except this session! Continue?')).')) {'.
		'jQuery("#authenticationForm").submit(); return true; } else { return false; }'
	);
}
elseif ($this->data['config']['authentication_type'] != ZBX_AUTH_LDAP) {
	$saveButton->setAttribute('disabled', 'true');
}

// append buttons to form
if ($this->data['config']['authentication_type'] == ZBX_AUTH_LDAP) {
	$authenticationTab->setFooter(makeFormFooter($saveButton, [new CSubmit('test', _('Test'))]));
}
else {
	$authenticationTab->setFooter(makeFormFooter($saveButton));
}

// append tab to form
$authenticationForm->addItem($authenticationTab);

// append form to widget
$widget->addItem($authenticationForm);

return $widget;
