<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


$authenticationWidget = new CWidget();
$authenticationWidget->addPageHeader(_('CONFIGURATION OF AUTHENTICATION'));

// create form
$authenticationForm = new CForm();
$authenticationForm->setName('authenticationForm');

// create form list
$authenticationFormList = new CFormList('authenticationList');

// append config radio buttons to form list
$configTypeRadioButton = array(
	new CRadioButton('config', ZBX_AUTH_INTERNAL, null, 'config_'.ZBX_AUTH_INTERNAL, $this->data['config'] == ZBX_AUTH_INTERNAL, 'submit()'),
	new CLabel(_('Internal'), 'config_'.ZBX_AUTH_INTERNAL),
	new CRadioButton('config', ZBX_AUTH_LDAP, null, 'config_'.ZBX_AUTH_LDAP, $this->data['config'] == ZBX_AUTH_LDAP, 'submit()'),
	new CLabel(_('LDAP'), 'config_'.ZBX_AUTH_LDAP),
	new CRadioButton('config', ZBX_AUTH_HTTP, null, 'config_'.ZBX_AUTH_HTTP, $this->data['config'] == ZBX_AUTH_HTTP, 'submit()'),
	new CLabel(_('HTTP'), 'config_'.ZBX_AUTH_HTTP),
);
$authenticationFormList->addRow(_('Default authentication'), new CDiv($configTypeRadioButton, 'jqueryinputset'));

// append LDAP fields to form list
if ($this->data['config'] == ZBX_AUTH_LDAP) {
	if (!empty($this->data['user_list'])) {
		$userComboBox = new CComboBox('user', $this->data['user']);
		foreach ($this->data['user_list'] as $user) {
			if (check_perm2login($user['userid']) && check_perm2system($user['userid'])) {
				$userComboBox->addItem($user['alias'], $user['alias']);
			}
		}
	}
	else {
		$userComboBox = new CTextBox('user', $this->data['user'], ZBX_TEXTBOX_STANDARD_SIZE, 'yes');
	}

	$authenticationFormList->addRow(_('LDAP host'), new CTextBox('ldap_host', $this->data['config_data']['ldap_host'], ZBX_TEXTBOX_STANDARD_SIZE));
	$authenticationFormList->addRow(_('Port'), new CNumericBox('ldap_port', $this->data['config_data']['ldap_port'], 5));
	$authenticationFormList->addRow(_('Base DN'), new CTextBox('ldap_base_dn',$this->data['config_data']['ldap_base_dn'], ZBX_TEXTBOX_STANDARD_SIZE));
	$authenticationFormList->addRow(_('Search attribute'), new CTextBox('ldap_search_attribute', !empty($this->data['config_data']['ldap_search_attribute']) ? $this->data['config_data']['ldap_search_attribute'] : 'uid', ZBX_TEXTBOX_STANDARD_SIZE, 'no', 128));
	$authenticationFormList->addRow(_('Bind DN'), new CTextBox('ldap_bind_dn', $this->data['config_data']['ldap_bind_dn'], ZBX_TEXTBOX_STANDARD_SIZE));
	$authenticationFormList->addRow(_('Bind password'), new CPassBox('ldap_bind_password', $this->data['config_data']['ldap_bind_password'], ZBX_TEXTBOX_STANDARD_SIZE));
	$authenticationFormList->addRow(_('Test authentication'), ' ['._('must be a valid LDAP user').']');
	$authenticationFormList->addRow(_('Login'), $userComboBox);
	$authenticationFormList->addRow(_('User password'), new CPassBox('user_password', $this->data['user_password'], ZBX_TEXTBOX_STANDARD_SIZE));
}

// append form list to tab
$authenticationTab = new CTabView();
$authenticationTab->addTab('authenticationTab', $this->data['tab_title'], $authenticationFormList);

// append tab to form
$authenticationForm->addItem($authenticationTab);

// create save button
$saveButton = new CSubmit('save', _('Save'));
if ($this->data['is_authentication_type_changed']) {
	$saveButton->addAction('onclick', 'javascript: if (Confirm("'._('Switching authentication method will reset all except this session! Continue?').'")) { jQuery("#authenticationForm").submit(); return true; } else { return false; }');
}
else {
	if ($this->data['config'] != ZBX_AUTH_LDAP) {
		$saveButton->setAttribute('disabled', 'true');
	}
}

// append buttons to form
if ($this->data['config'] == ZBX_AUTH_LDAP) {
	$authenticationForm->addItem(makeFormFooter($saveButton, new CSubmit('test', _('Test'))));
}
else {
	$authenticationForm->addItem(makeFormFooter($saveButton));
}

// append form to widget
$authenticationWidget->addItem($authenticationForm);

return $authenticationWidget;
