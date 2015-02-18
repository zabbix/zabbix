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


$this->includeJSfile('app/views/administration.mediatype.edit.js.php');

$mediaTypeWidget = new CWidget();
$mediaTypeWidget->addPageHeader(_('CONFIGURATION OF MEDIA TYPES'));

// create form
$mediaTypeForm = new CForm();
$mediaTypeForm->setAttribute('id', 'mediaTypeForm');
$mediaTypeForm->addVar('form', 1);
$mediaTypeForm->addVar('mediatypeid', $data['mediatypeid']);

// create form list
$mediaTypeFormList = new CFormList('mediaTypeFormList');
$nameTextBox = new CTextBox('description', $data['description'], ZBX_TEXTBOX_STANDARD_SIZE, false, 100);
$nameTextBox->attr('autofocus', 'autofocus');
$mediaTypeFormList->addRow(_('Name'), $nameTextBox);

// append type to form list
$cmbType = new CComboBox('type', $data['type']);
$cmbType->addItems(array(
	MEDIA_TYPE_EMAIL => _('Email'),
	MEDIA_TYPE_EXEC => _('Script'),
	MEDIA_TYPE_SMS => _('SMS'),
	MEDIA_TYPE_JABBER => _('Jabber'),
));
$cmbType->addItemsInGroup(_('Commercial'), array(MEDIA_TYPE_EZ_TEXTING => _('Ez Texting')));
$cmbTypeRow = array($cmbType);
$ez_texting_link = new CLink('https://app.eztexting.com', 'https://app.eztexting.com/', null, null, 'nosid');
$ez_texting_link->setAttribute('id', 'eztext_link');
$ez_texting_link->setTarget('_blank');
$cmbTypeRow[] = $ez_texting_link;

$mediaTypeFormList->addRow(_('Type'), $cmbTypeRow);

$mediaTypeFormList->addRow(_('SMTP server'), new CTextBox('smtp_server', $data['smtp_server'], ZBX_TEXTBOX_STANDARD_SIZE), $data['type'] != MEDIA_TYPE_EMAIL);
$mediaTypeFormList->addRow(_('SMTP helo'), new CTextBox('smtp_helo', $data['smtp_helo'], ZBX_TEXTBOX_STANDARD_SIZE), $data['type'] != MEDIA_TYPE_EMAIL);
$mediaTypeFormList->addRow(_('SMTP email'), new CTextBox('smtp_email', $data['smtp_email'], ZBX_TEXTBOX_STANDARD_SIZE), $data['type'] != MEDIA_TYPE_EMAIL);
$mediaTypeFormList->addRow(_('Script name'), new CTextBox('exec_path', $data['exec_path'], ZBX_TEXTBOX_STANDARD_SIZE), $data['type'] != MEDIA_TYPE_EXEC);
$mediaTypeFormList->addRow(_('GSM modem'), new CTextBox('gsm_modem', $data['gsm_modem'], ZBX_TEXTBOX_STANDARD_SIZE), $data['type'] != MEDIA_TYPE_SMS);

// create password field
if ($data['passwd'] != '') {
	$passwdButton = new CButton('chPass_btn', _('Change password'), 'this.style.display="none"; $("passwd").enable().show().focus();');
	$passwdBox = new CPassBox('passwd', $data['passwd'], ZBX_TEXTBOX_SMALL_SIZE);
	$passwdBox->addStyle('display: none;');
	$passwdField = array($passwdButton, $passwdBox);
}
else {
	$passwdField = new CPassBox('passwd', '', ZBX_TEXTBOX_SMALL_SIZE);
}

// append password field to form list
$mediaTypeFormList->addRow(_('Jabber identifier'), new CTextBox('jabber_username', $data['jabber_username'], ZBX_TEXTBOX_STANDARD_SIZE));
$mediaTypeFormList->addRow(_('Username'), new CTextBox('eztext_username', $data['eztext_username'], ZBX_TEXTBOX_STANDARD_SIZE));
$mediaTypeFormList->addRow(_('Password'), $passwdField);
$limitCb = new CComboBox('eztext_limit', $data['exec_path']);
$limitCb->addItems(array(
	EZ_TEXTING_LIMIT_USA => _('USA (160 characters)'),
	EZ_TEXTING_LIMIT_CANADA => _('Canada (136 characters)'),
));
$mediaTypeFormList->addRow(_('Message text limit'), $limitCb);

$mediaTypeFormList->addRow(_('Enabled'), new CCheckBox('status', MEDIA_TYPE_STATUS_ACTIVE == $data['status'], null, MEDIA_TYPE_STATUS_ACTIVE));

// append form list to tab
$mediaTypeTab = new CTabView();
$mediaTypeTab->addTab('mediaTypeTab', _('Media type'), $mediaTypeFormList);

// append tab to form
$mediaTypeForm->addItem($mediaTypeTab);

// append buttons to form
$cancelButton = new CRedirectButton(_('Cancel'), 'zabbix.php?action=mediatype.list');
$cancelButton->setAttribute('id', 'cancel');

if ($data['mediatypeid'] == 0) {
	$addButton = new CSubmitButton(_('Add'), 'action', 'mediatype.create');
	$addButton->setAttribute('id', 'add');

	$mediaTypeForm->addItem(makeFormFooter(
		$addButton,
		array($cancelButton)
	));
}
else {
	$updateButton = new CSubmitButton(_('Update'), 'action', 'mediatype.update');
	$updateButton->setAttribute('id', 'update');
	$cloneButton = new CSimpleButton(_('Clone'));
	$cloneButton->setAttribute('id', 'clone');
	$deleteButton = new CRedirectButton(_('Delete'),
		'zabbix.php?action=mediatype.delete&sid='.$data['sid'].'&mediatypeids[]='.$data['mediatypeid'],
		_('Delete media type?')
	);
	$deleteButton->setAttribute('id', 'delete');

	$mediaTypeForm->addItem(makeFormFooter(
		$updateButton,
		array(
			$cloneButton,
			$deleteButton,
			$cancelButton
		)
	));
}

// append form to widget
$mediaTypeWidget->addItem($mediaTypeForm);

$mediaTypeWidget->show();
