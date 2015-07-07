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

$widget = (new CWidget())->setTitle(_('Media types'));

// create form
$mediaTypeForm = (new CForm())
	->setId('mediaTypeForm')
	->addVar('form', 1)
	->addVar('mediatypeid', $data['mediatypeid']);

// create form list
$nameTextBox = (new CTextBox('description', $data['description'], false, 100))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAttribute('autofocus', 'autofocus');
$mediaTypeFormList = (new CFormList('mediaTypeFormList'))
	->addRow(_('Name'), $nameTextBox);

// append type to form list
$cmbType = new CComboBox('type', $data['type'], null, [
	MEDIA_TYPE_EMAIL => _('Email'),
	MEDIA_TYPE_EXEC => _('Script'),
	MEDIA_TYPE_SMS => _('SMS'),
	MEDIA_TYPE_JABBER => _('Jabber')
]);
$cmbType->addItemsInGroup(_('Commercial'), [MEDIA_TYPE_EZ_TEXTING => _('Ez Texting')]);
$cmbTypeRow = [$cmbType];
$ez_texting_link = (new CLink('https://app.eztexting.com', 'https://app.eztexting.com/'))
	->removeSID()
	->setId('eztext_link')
	->setTarget('_blank');
$cmbTypeRow[] = $ez_texting_link;

$connections = [
	(new CRadioButton('smtp_security', SMTP_CONNECTION_SECURITY_NONE,
		$data['smtp_security'] == SMTP_CONNECTION_SECURITY_NONE
	))->setId('smtp_security_'.SMTP_CONNECTION_SECURITY_NONE),
	new CLabel(_('None'), 'smtp_security_'.SMTP_CONNECTION_SECURITY_NONE),
	(new CRadioButton('smtp_security', SMTP_CONNECTION_SECURITY_STARTTLS,
		$data['smtp_security'] == SMTP_CONNECTION_SECURITY_STARTTLS
	))->setId('smtp_security_'.SMTP_CONNECTION_SECURITY_STARTTLS),
	new CLabel(_('STARTTLS'), 'smtp_security_'.SMTP_CONNECTION_SECURITY_STARTTLS),
	(new CRadioButton('smtp_security', SMTP_CONNECTION_SECURITY_SSL_TLS,
		$data['smtp_security'] == SMTP_CONNECTION_SECURITY_SSL_TLS
	))->setId('smtp_security_'.SMTP_CONNECTION_SECURITY_SSL_TLS),
	new CLabel(_('SSL/TLS'), 'smtp_security_'.SMTP_CONNECTION_SECURITY_SSL_TLS)
];

$authentication = [
	(new CRadioButton('smtp_authentication', SMTP_AUTHENTICATION_NONE,
		$data['smtp_authentication'] == SMTP_AUTHENTICATION_NONE
	))->setId('smtp_authentication_'.SMTP_AUTHENTICATION_NONE),
	new CLabel(_('None'), 'smtp_authentication_'.SMTP_AUTHENTICATION_NONE),
	(new CRadioButton('smtp_authentication', SMTP_AUTHENTICATION_NORMAL,
		$data['smtp_authentication'] == SMTP_AUTHENTICATION_NORMAL
	))->setId('smtp_authentication_'.SMTP_AUTHENTICATION_NORMAL),
	new CLabel(_('Normal password'), 'smtp_authentication_'.SMTP_AUTHENTICATION_NORMAL)
];

$mediaTypeFormList
	->addRow(_('Type'), $cmbTypeRow)
	->addRow(_('SMTP server'), [
			(new CTextBox('smtp_server', $data['smtp_server']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			_('Port'),
			(new CNumericBox('smtp_port', $data['smtp_port'], 5, false, false, false))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		],
		$data['type'] != MEDIA_TYPE_EMAIL
	)
	->addRow(_('SMTP helo'),
		(new CTextBox('smtp_helo', $data['smtp_helo']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		$data['type'] != MEDIA_TYPE_EMAIL
	)
	->addRow(_('SMTP email'),
		(new CTextBox('smtp_email', $data['smtp_email']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		$data['type'] != MEDIA_TYPE_EMAIL
	)
	->addRow(_('Connection security'),
		$connections, $data['type'] != MEDIA_TYPE_EMAIL
	)
	->addRow(_('SSL verify peer'),
		(new CCheckBox('smtp_verify_peer'))->setChecked($data['smtp_verify_peer']),
		$data['type'] != MEDIA_TYPE_EMAIL
	)
	->addRow(_('SSL verify host'),
		(new CCheckBox('smtp_verify_host'))->setChecked($data['smtp_verify_host']),
		$data['type'] != MEDIA_TYPE_EMAIL
	)
	->addRow(_('Authentication'), $authentication, $data['type'] != MEDIA_TYPE_EMAIL)
	->addRow(_('Username'),
		(new CTextBox('smtp_username', $data['smtp_username']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	->addRow(_('Script name'),
		(new CTextBox('exec_path', $data['exec_path']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		$data['type'] != MEDIA_TYPE_EXEC
	)
	->addRow(_('GSM modem'),
		(new CTextBox('gsm_modem', $data['gsm_modem']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		$data['type'] != MEDIA_TYPE_SMS
	);

// create password field
if ($data['passwd'] != '') {
	$passwdField = [
		(new CButton('chPass_btn', _('Change password')))
			->onClick('this.style.display="none"; $("passwd").show().focus();'),
		(new CPassBox('passwd', $data['passwd']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->addStyle('display: none;')
	];
}
else {
	$passwdField = (new CPassBox('passwd'))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
}

// append password field to form list
$mediaTypeFormList
	->addRow(_('Jabber identifier'),
		(new CTextBox('jabber_username', $data['jabber_username']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	->addRow(_('Username'),
		(new CTextBox('eztext_username', $data['eztext_username']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	->addRow(_('Password'), $passwdField)
	->addRow(_('Message text limit'), new CComboBox('eztext_limit', $data['exec_path'], null, [
		EZ_TEXTING_LIMIT_USA => _('USA (160 characters)'),
		EZ_TEXTING_LIMIT_CANADA => _('Canada (136 characters)')
	]))
	->addRow(_('Enabled'),
		(new CCheckBox('status', MEDIA_TYPE_STATUS_ACTIVE))->setChecked(MEDIA_TYPE_STATUS_ACTIVE == $data['status'])
	);

// append form list to tab
$mediaTypeTab = (new CTabView())->addTab('mediaTypeTab', _('Media type'), $mediaTypeFormList);

// append buttons to form
$cancelButton = (new CRedirectButton(_('Cancel'), 'zabbix.php?action=mediatype.list'))->setId('cancel');

if ($data['mediatypeid'] == 0) {
	$addButton = (new CSubmitButton(_('Add'), 'action', 'mediatype.create'))->setId('add');

	$mediaTypeTab->setFooter(makeFormFooter(
		$addButton,
		[$cancelButton]
	));
}
else {
	$updateButton = (new CSubmitButton(_('Update'), 'action', 'mediatype.update'))->setId('update');
	$cloneButton = (new CSimpleButton(_('Clone')))->setId('clone');
	$deleteButton = (new CRedirectButton(_('Delete'),
		'zabbix.php?action=mediatype.delete&sid='.$data['sid'].'&mediatypeids[]='.$data['mediatypeid'],
		_('Delete media type?')
	))
		->setId('delete');

	$mediaTypeTab->setFooter(makeFormFooter(
		$updateButton,
		[
			$cloneButton,
			$deleteButton,
			$cancelButton
		]
	));
}

// append tab to form
$mediaTypeForm->addItem($mediaTypeTab);

// append form to widget
$widget->addItem($mediaTypeForm)->show();
