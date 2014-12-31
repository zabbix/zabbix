<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


$mediaTypeWidget = new CWidget();
$mediaTypeWidget->addPageHeader(_('CONFIGURATION OF MEDIA TYPES'));

// create form
$mediaTypeForm = new CForm();
$mediaTypeForm->setName('mediaTypeForm');
$mediaTypeForm->addVar('form', $this->data['form']);
$mediaTypeForm->addVar('form_refresh', $this->data['form_refresh'] + 1);
$mediaTypeForm->addVar('mediatypeid', $this->data['mediatypeid']);

// create form list
$mediaTypeFormList = new CFormList('mediaTypeFormList');
$nameTextBox = new CTextBox('description', $this->data['description'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 100);
$nameTextBox->attr('autofocus', 'autofocus');
$mediaTypeFormList->addRow(_('Name'), $nameTextBox);

// append type to form list
$cmbType = new CComboBox('type', $this->data['type'], 'submit()');
$cmbType->addItems(array(
	MEDIA_TYPE_EMAIL => _('Email'),
	MEDIA_TYPE_EXEC => _('Script'),
	MEDIA_TYPE_SMS => _('SMS'),
	MEDIA_TYPE_JABBER => _('Jabber'),
));
$cmbType->addItemsInGroup(_('Commercial'), array(MEDIA_TYPE_EZ_TEXTING => _('Ez Texting')));
$cmbTypeRow = array($cmbType);
if ($this->data['type'] == MEDIA_TYPE_EZ_TEXTING) {
	$ez_texting_link = new CLink('https://app.eztexting.com', 'https://app.eztexting.com/', null, null, 'nosid');
	$ez_texting_link->setTarget('_blank');
	$cmbTypeRow[] = $ez_texting_link;
}
$mediaTypeFormList->addRow(_('Type'), $cmbTypeRow);

// append others fields to form list
if ($this->data['type'] == MEDIA_TYPE_EMAIL) {
	$mediaTypeFormList->addRow(_('SMTP server'), new CTextBox('smtp_server', $this->data['smtp_server'], ZBX_TEXTBOX_STANDARD_SIZE));
	$mediaTypeFormList->addRow(_('SMTP helo'), new CTextBox('smtp_helo', $this->data['smtp_helo'], ZBX_TEXTBOX_STANDARD_SIZE));
	$mediaTypeFormList->addRow(_('SMTP email'), new CTextBox('smtp_email', $this->data['smtp_email'], ZBX_TEXTBOX_STANDARD_SIZE));
}
elseif ($this->data['type'] == MEDIA_TYPE_SMS) {
	$mediaTypeFormList->addRow(_('GSM modem'), new CTextBox('gsm_modem', $this->data['gsm_modem'], ZBX_TEXTBOX_STANDARD_SIZE));
}
elseif ($this->data['type'] == MEDIA_TYPE_EXEC) {
	$mediaTypeFormList->addRow(_('Script name'), new CTextBox('exec_path', $this->data['exec_path'], ZBX_TEXTBOX_STANDARD_SIZE));
}
elseif ($this->data['type'] == MEDIA_TYPE_JABBER || $this->data['type'] == MEDIA_TYPE_EZ_TEXTING) {
	// create password field
	if (!empty($this->data['password'])) {
		$passwordButton = new CButton('chPass_btn', _('Change password'), 'this.style.display="none"; $("password").enable().show().focus();');
		$passwordBox = new CPassBox('password', $this->data['password'], ZBX_TEXTBOX_SMALL_SIZE);
		$passwordBox->addStyle('display: none;');
		$passwordField = array($passwordButton, $passwordBox);
	}
	else {
		$passwordField = new CPassBox('password', '', ZBX_TEXTBOX_SMALL_SIZE);
	}

	// append password field to form list
	if ($this->data['type'] == MEDIA_TYPE_JABBER) {
		$mediaTypeFormList->addRow(_('Jabber identifier'), new CTextBox('username', $this->data['username'], ZBX_TEXTBOX_STANDARD_SIZE));
		$mediaTypeFormList->addRow(_('Password'), $passwordField);
	}
	else {
		$mediaTypeFormList->addRow(_('Username'), new CTextBox('username', $this->data['username'], ZBX_TEXTBOX_STANDARD_SIZE));
		$mediaTypeFormList->addRow(_('Password'), $passwordField);
		$limitCb = new CComboBox('exec_path', $this->data['exec_path']);
		$limitCb->addItems(array(
			EZ_TEXTING_LIMIT_USA => _('USA (160 characters)'),
			EZ_TEXTING_LIMIT_CANADA => _('Canada (136 characters)'),
		));
		$mediaTypeFormList->addRow(_('Message text limit'), $limitCb);
	}
}

$mediaTypeFormList->addRow(_('Enabled'), new CCheckBox('status', MEDIA_TYPE_STATUS_ACTIVE == $this->data['status'], null, MEDIA_TYPE_STATUS_ACTIVE));

// append form list to tab
$mediaTypeTab = new CTabView();
$mediaTypeTab->addTab('mediaTypeTab', _('Media type'), $mediaTypeFormList);

// append tab to form
$mediaTypeForm->addItem($mediaTypeTab);

// append buttons to form
if (empty($this->data['mediatypeid'])) {
	$mediaTypeForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), array(new CButtonCancel(url_param('config')))));
}
else {
	$mediaTypeForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), array(new CButtonDelete(_('Delete selected media type?'), url_param('form').url_param('mediatypeid').url_param('config')), new CButtonCancel(url_param('config')))));
}

// append form to widget
$mediaTypeWidget->addItem($mediaTypeForm);

return $mediaTypeWidget;
