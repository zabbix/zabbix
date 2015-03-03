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


$mediaTypeWidget = new CWidget();
$mediaTypeWidget->addPageHeader(_('CONFIGURATION OF MEDIA TYPES'));

// create form
$mediaTypeForm = new CForm();
$mediaTypeForm->setName('mediaTypeForm');
$mediaTypeForm->addVar('form', $this->data['form']);
$mediaTypeForm->addVar('mediatypeid', $this->data['mediatypeid']);

// create form list
$mediaTypeFormList = new CFormList('mediaTypeFormList');
$nameTextBox = new CTextBox('description', $this->data['description'], ZBX_TEXTBOX_STANDARD_SIZE, false, 100);
$nameTextBox->attr('autofocus', 'autofocus');
$mediaTypeFormList->addRow(_('Name'), $nameTextBox);

// append type to form list
$cmbType = new CComboBox('type', $this->data['type']);
$cmbType->addItems(array(
	MEDIA_TYPE_EMAIL => _('Email'),
	MEDIA_TYPE_EXEC => _('Script'),
	MEDIA_TYPE_SMS => _('SMS'),
	MEDIA_TYPE_JABBER => _('Jabber'),
));
$cmbType->addItemsInGroup(_('Commercial'), array(
	MEDIA_TYPE_EZ_TEXTING => _('Ez Texting'),
	MEDIA_TYPE_REMEDY => _('Remedy Service')
));

$ezTextingLink = new CLink('https://app.eztexting.com', 'https://app.eztexting.com/', null, null, 'nosid');
$ezTextingLink->setAttribute('id', 'ezTextingLink');
$ezTextingLink->setTarget('_blank');

$mediaTypeFormList->addRow(_('Type'), array($cmbType, $ezTextingLink));

// SMTP
$mediaTypeFormList->addRow(_('SMTP server'),
	new CTextBox('smtp_server', $data['smtp_server'], ZBX_TEXTBOX_STANDARD_SIZE), false, 'row_smtp_server'
);
$mediaTypeFormList->addRow(_('SMTP helo'),
	new CTextBox('smtp_helo', $data['smtp_helo'], ZBX_TEXTBOX_STANDARD_SIZE), false, 'row_smtp_helo'
);
$mediaTypeFormList->addRow(_('SMTP email'),
	new CTextBox('smtp_email', $data['smtp_email'], ZBX_TEXTBOX_STANDARD_SIZE), false, 'row_smtp_email'
);

// Remedy
$mediaTypeFormList->addRow(_('Remedy Service URL'),
	new CTextBox('remedy_url', $data['remedy_url'], ZBX_TEXTBOX_STANDARD_SIZE), false, 'row_remedy_url'
);

// GSM modem
$mediaTypeFormList->addRow(_('GSM modem'),
	new CTextBox('gsm_modem', $data['gsm_modem'], ZBX_TEXTBOX_STANDARD_SIZE), false, 'row_gsm_modem'
);

// Script
$mediaTypeFormList->addRow(_('Script name'),
	new CTextBox('exec_path', $data['exec_path'], ZBX_TEXTBOX_STANDARD_SIZE), false, 'row_exec_path'
);

// Username
$mediaTypeFormList->addRow(_('Username'),
	new CTextBox('username', $data['username'], ZBX_TEXTBOX_STANDARD_SIZE), false, 'row_username'
);
$mediaTypeFormList->addRow(_('Username'),
	new CTextBox('ez_username', $data['ez_username'], ZBX_TEXTBOX_STANDARD_SIZE), false, 'row_ez_username'
);
$mediaTypeFormList->addRow(_('Jabber identifier'),
	new CTextBox('jabber_identifier', $data['jabber_identifier'], ZBX_TEXTBOX_STANDARD_SIZE), false,
	'row_jabber_identifier'
);

// Password
if ($data['password'] !== '') {
	$passwordButton = new CButton('chPass_btn', _('Change password'),
		'this.style.display="none"; $("password").enable().show().focus();',
		'formlist'
	);
	$passwordBox = new CPassBox('password', $data['password'], ZBX_TEXTBOX_SMALL_SIZE);
	$passwordBox->addStyle('display: none;');
	$passwordField = array($passwordButton, $passwordBox);
}
else {
	$passwordField = new CPassBox('password', '', ZBX_TEXTBOX_SMALL_SIZE);
}
$mediaTypeFormList->addRow(_('Password'), $passwordField, false, 'row_password');

// EZ Texting
$msgTextLimitCmbBox = new CComboBox('msg_txt_limit', $data['msg_txt_limit']);
$msgTextLimitCmbBox->addItems(array(
	EZ_TEXTING_LIMIT_USA => _('USA (160 characters)'),
	EZ_TEXTING_LIMIT_CANADA => _('Canada (136 characters)'),
));
$mediaTypeFormList->addRow(_('Message text limit'),	$msgTextLimitCmbBox, false, 'row_msg_txt_limit');

// Remedy
$remedyProxyTextBox = new CTextBox('remedy_proxy', $data['remedy_proxy'], ZBX_TEXTBOX_STANDARD_SIZE);
$remedyProxyTextBox->setAttribute('placeholder', 'http://[username[:password]@]proxy.example.com[:port]');
$mediaTypeFormList->addRow(_('Proxy'), $remedyProxyTextBox, false, 'row_remedy_proxy');
$mediaTypeFormList->addRow(_('Company name'),
	new CTextBox('remedy_company', $data['remedy_company'], ZBX_TEXTBOX_STANDARD_SIZE), false,
	'row_remedy_company'
);
$mediaTypeFormList->addRow(_('Services mapping'),
	new CTextBox('remedy_mapping', $data['remedy_mapping'], ZBX_TEXTBOX_STANDARD_SIZE), false,
	'row_remedy_mapping'
);

$mediaTypeFormList->addRow(_('Enabled'),
	new CCheckBox('status', MEDIA_TYPE_STATUS_ACTIVE == $data['status'], null, MEDIA_TYPE_STATUS_ACTIVE)
);

// append form list to tab
$mediaTypeTab = new CTabView();
$mediaTypeTab->addTab('mediaTypeTab', _('Media type'), $mediaTypeFormList);

// append tab to form
$mediaTypeForm->addItem($mediaTypeTab);

// append buttons to form
if (!empty($this->data['mediatypeid'])) {
	$mediaTypeForm->addItem(makeFormFooter(
		new CSubmit('update', _('Update')),
		array(
			new CButtonDelete(
				_('Delete selected media type?'),
				url_param('form').url_param('mediatypeid').url_param('config')
			),
			new CButtonCancel(url_param('config'))
		)
	));
}
else {
	$mediaTypeForm->addItem(makeFormFooter(
		new CSubmit('add', _('Add')),
		new CButtonCancel(url_param('config'))
	));
}

// append form to widget
$mediaTypeWidget->addItem($mediaTypeForm);

$data['typeVisibility'] = array();
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EMAIL, 'smtp_server');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EMAIL, 'row_smtp_server');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EMAIL, 'smtp_helo');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EMAIL, 'row_smtp_helo');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EMAIL, 'smtp_email');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EMAIL, 'row_smtp_email');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EXEC, 'exec_path');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EXEC, 'row_exec_path');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_SMS, 'gsm_modem');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_SMS, 'row_gsm_modem');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_JABBER, 'jabber_identifier');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_JABBER, 'row_jabber_identifier');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_JABBER, 'password');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_JABBER, 'row_password');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EZ_TEXTING, 'ez_username');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EZ_TEXTING, 'row_ez_username');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EZ_TEXTING, 'password');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EZ_TEXTING, 'row_password');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EZ_TEXTING, 'msg_txt_limit');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_EZ_TEXTING, 'row_msg_txt_limit');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'remedy_url');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'row_remedy_url');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'username');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'row_username');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'password');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'row_password');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'remedy_proxy');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'row_remedy_proxy');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'remedy_company');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'row_remedy_company');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'remedy_mapping');
zbx_subarray_push($data['typeVisibility'], MEDIA_TYPE_REMEDY, 'row_remedy_mapping');

require_once dirname(__FILE__).'/js/administration.mediatypes.edit.js.php';

return $mediaTypeWidget;
