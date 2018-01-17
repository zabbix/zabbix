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


$this->includeJSfile('app/views/administration.mediatype.edit.js.php');

$widget = (new CWidget())->setTitle(_('Media types'));

$tabs = new CTabView();

if ($data['form_refresh'] == 0) {
	$tabs->setSelected(0);
}

// create form
$mediaTypeForm = (new CForm())
	->setId('media_type_form')
	->addVar('form', 1)
	->addVar('mediatypeid', $data['mediatypeid']);

// Create form list.
$mediatype_formlist = (new CFormList())
	->addRow((new CLabel(_('Name'), 'description'))->setAsteriskMark(),
		(new CTextBox('description', $data['description'], false, 100))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow((new CLabel(_('Type'), 'type')), [
		(new CComboBox('type', $data['type'], null, [
			MEDIA_TYPE_EMAIL => _('Email'),
			MEDIA_TYPE_EXEC => _('Script'),
			MEDIA_TYPE_SMS => _('SMS'),
			MEDIA_TYPE_JABBER => _('Jabber')
		]))
			->addItemsInGroup(_('Commercial'), [MEDIA_TYPE_EZ_TEXTING => _('Ez Texting')]),
		(new CLink('https://app.eztexting.com', 'https://app.eztexting.com/'))
			->setId('eztext_link')
			->setTarget('_blank')
	])
	->addRow((new CLabel(_('SMTP server'), 'smtp_server'))->setAsteriskMark(),
		(new CTextBox('smtp_server', $data['smtp_server']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(_('SMTP server port'),
		(new CNumericBox('smtp_port', $data['smtp_port'], 5, false, false, false))->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	)
	->addRow((new CLabel(_('SMTP helo'), 'smtp_helo'))->setAsteriskMark(),
		(new CTextBox('smtp_helo', $data['smtp_helo']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('SMTP email'), 'smtp_email'))->setAsteriskMark(),
		(new CTextBox('smtp_email', $data['smtp_email']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Connection security'), 'smtp_security')),
		(new CRadioButtonList('smtp_security', (int) $data['smtp_security']))
			->addValue(_('None'), SMTP_CONNECTION_SECURITY_NONE)
			->addValue(_('STARTTLS'), SMTP_CONNECTION_SECURITY_STARTTLS)
			->addValue(_('SSL/TLS'), SMTP_CONNECTION_SECURITY_SSL_TLS)
			->setModern(true)
	)
	->addRow(_('SSL verify peer'), (new CCheckBox('smtp_verify_peer'))->setChecked($data['smtp_verify_peer']))
	->addRow(_('SSL verify host'), (new CCheckBox('smtp_verify_host'))->setChecked($data['smtp_verify_host']))
	->addRow((new CLabel(_('Authentication'), 'smtp_authentication')),
		(new CRadioButtonList('smtp_authentication', (int) $data['smtp_authentication']))
			->addValue(_('None'), SMTP_AUTHENTICATION_NONE)
			->addValue(_('Username and password'), SMTP_AUTHENTICATION_NORMAL)
			->setModern(true)
	)
	->addRow(_('Username'), (new CTextBox('smtp_username', $data['smtp_username']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH))
	->addRow((new CLabel(_('Script name'), 'exec_path'))->setAsteriskMark(),
		(new CTextBox('exec_path', $data['exec_path']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	);

$exec_params_table = (new CTable())
	->setId('exec_params_table')
	->setHeader([_('Parameter'), _('Action')])
	->setAttribute('style', 'width: 100%;');

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

$exec_params_table->addRow([(new CButton('exec_param_add', _('Add')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->addClass('element-table-add')]);

$mediatype_formlist->addRow(_('Script parameters'),
	(new CDiv($exec_params_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
	'row_exec_params'
);

$mediatype_formlist->addRow((new CLabel(_('GSM modem'), 'gsm_modem'))->setAsteriskMark(),
	(new CTextBox('gsm_modem', $data['gsm_modem']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
);

// Create password field.
if ($data['type'] != MEDIA_TYPE_EMAIL && $data['passwd'] !== '') {
	$passwd_field = [
		(new CButton('chPass_btn', _('Change password')))
			->onClick('this.style.display="none"; $("passwd").show().focus();'),
		(new CPassBox('passwd', $data['passwd']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
			->addStyle('display: none;')
	];
}
else {
	$passwd_field = (new CPassBox('passwd'))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
}

if ($data['type'] == MEDIA_TYPE_EMAIL && $data['smtp_passwd'] != '') {
	$smtp_passwd = [
		(new CButton('chPass_btn', _('Change password')))
			->onClick('this.style.display="none"; $("smtp_passwd").show().focus();'),
		(new CPassBox('smtp_passwd', $data['smtp_passwd']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
			->addStyle('display: none;')
	];
}
else {
	$smtp_passwd = (new CPassBox('smtp_passwd'))
		->setAriaRequired()
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
}

// append password field to form list
$mediatype_formlist
	->addRow((new CLabel(_('Jabber identifier'), 'jabber_username'))->setAsteriskMark(),
		(new CTextBox('jabber_username', $data['jabber_username']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Username'), 'eztext_username'))->setAsteriskMark(),
		(new CTextBox('eztext_username', $data['eztext_username']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Password'), 'passwd')),
		$passwd_field
	)
	->addRow((new CLabel(_('Password'), 'smtp_passwd'))->setAsteriskMark(),
		$smtp_passwd
	)
	->addRow(_('Message text limit'), new CComboBox('eztext_limit', $data['eztext_limit'], null, [
		EZ_TEXTING_LIMIT_USA => _('USA (160 characters)'),
		EZ_TEXTING_LIMIT_CANADA => _('Canada (136 characters)')
	]))
	->addRow(_('Enabled'),
		(new CCheckBox('status', MEDIA_TYPE_STATUS_ACTIVE))->setChecked(MEDIA_TYPE_STATUS_ACTIVE == $data['status'])
	);
$tabs->addTab('mediaTab', _('Media type'), $mediatype_formlist);

// media options tab
$max_sessions = ($data['maxsessions'] > 1) ? $data['maxsessions'] : 0;
if ($data['type'] == MEDIA_TYPE_SMS) {
	$max_sessions = 1;
}

switch ($data['maxsessions']) {
	case 1:
		$data['maxsessions_type'] = 'one';
		break;
	case 0:
		$data['maxsessions_type'] = 'unlimited';
		break;
	default:
		$data['maxsessions_type'] = 'custom';
}

$mediaOptionsForm = (new CFormList('options'))
	->addRow((new CLabel(_('Concurrent sessions'), 'maxsessions_type')),
		(new CDiv())
			->addClass(ZBX_STYLE_NOWRAP)
			->addItem([
				(new CDiv(
					(new CRadioButtonList('maxsessions_type', $data['maxsessions_type']))
						->addValue(_('One'), 'one')
						->addValue(_('Unlimited'), 'unlimited')
						->addValue(_('Custom'), 'custom')
						->setModern(true)
				))->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CNumericBox('maxsessions', $max_sessions, 3, false, false, false))
					->setAriaRequired()
					->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			])
	)
	->addRow((new CLabel(_('Attempts'), 'maxattempts'))->setAsteriskMark(),
		(new CNumericBox('maxattempts', $data['maxattempts'], 3, false, false, false))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Attempt interval'), 'attempt_interval'))->setAsteriskMark(),
		(new CTextBox('attempt_interval', $data['attempt_interval'], false, 12))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	);
$tabs->addTab('optionsTab', _('Options'), $mediaOptionsForm);

// append buttons to form
$cancelButton = (new CRedirectButton(_('Cancel'), 'zabbix.php?action=mediatype.list'))->setId('cancel');

if ($data['mediatypeid'] == 0) {
	$addButton = (new CSubmitButton(_('Add'), 'action', 'mediatype.create'))->setId('add');

	$tabs->setFooter(makeFormFooter(
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

	$tabs->setFooter(makeFormFooter(
		$updateButton,
		[
			$cloneButton,
			$deleteButton,
			$cancelButton
		]
	));
}

// append tab to form
$mediaTypeForm->addItem($tabs);

// append form to widget
$widget->addItem($mediaTypeForm)->show();
