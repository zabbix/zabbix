<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	->setId('media_type_form')
	->addVar('form', 1)
	->addVar('mediatypeid', $data['mediatypeid'])
	->addVar('exec_params_count', $data['exec_params_count']);

// create form list
$nameTextBox = (new CTextBox('description', $data['description'], false, 100))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAttribute('autofocus', 'autofocus');
$mediaTypeFormList = (new CFormList())
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
	->setId('eztext_link')
	->setTarget('_blank');
$cmbTypeRow[] = $ez_texting_link;

$mediaTypeFormList
	->addRow(_('Type'), $cmbTypeRow)
	->addRow(_('SMTP server'),
		(new CTextBox('smtp_server', $data['smtp_server']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('SMTP server port'),
		(new CNumericBox('smtp_port', $data['smtp_port'], 5, false, false, false))->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	)
	->addRow(_('SMTP helo'), (new CTextBox('smtp_helo', $data['smtp_helo']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	->addRow(_('SMTP email'), (new CTextBox('smtp_email', $data['smtp_email']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	->addRow(_('Connection security'),
		(new CRadioButtonList('smtp_security', (int) $data['smtp_security']))
			->addValue(_('None'), SMTP_CONNECTION_SECURITY_NONE)
			->addValue(_('STARTTLS'), SMTP_CONNECTION_SECURITY_STARTTLS)
			->addValue(_('SSL/TLS'), SMTP_CONNECTION_SECURITY_SSL_TLS)
			->setModern(true)
	)
	->addRow(_('SSL verify peer'), (new CCheckBox('smtp_verify_peer'))->setChecked($data['smtp_verify_peer']))
	->addRow(_('SSL verify host'), (new CCheckBox('smtp_verify_host'))->setChecked($data['smtp_verify_host']))
	->addRow(_('Authentication'),
		(new CRadioButtonList('smtp_authentication', (int) $data['smtp_authentication']))
			->addValue(_('None'), SMTP_AUTHENTICATION_NONE)
			->addValue(_('Normal password'), SMTP_AUTHENTICATION_NORMAL)
			->setModern(true)
	)
	->addRow(_('Username'), (new CTextBox('smtp_username', $data['smtp_username']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH))
	->addRow(_('Script name'), (new CTextBox('exec_path', $data['exec_path']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));

$exec_params_table = (new CTable())
	->setId('exec_params_table')
	->setHeader([_('Parameter'), _('Action')])
	->setAttribute('style', 'width: 100%;');

if ($data['exec_params_count'] != 0) {
	foreach ($data['exec_params'] as $i => $exec_param) {
		$exec_params_table->addRow([
			(new CTextBox('exec_params['.$i.'][exec_param]', $exec_param['exec_param'], false, 255))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CButton('exec_params['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
			],
			'form_row'
		);
	}
}

$exec_params_table->addRow([(new CButton('exec_param_add', _('Add')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->addClass('element-table-add')]);

$mediaTypeFormList->addRow(_('Script parameters'),
	(new CDiv($exec_params_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
	'row_exec_params'
);

$mediaTypeFormList->addRow(_('GSM modem'),
	(new CTextBox('gsm_modem', $data['gsm_modem']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
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
	->addRow(_('Message text limit'), new CComboBox('eztext_limit', $data['eztext_limit'], null, [
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
