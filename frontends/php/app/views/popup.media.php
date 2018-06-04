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


$options = $data['options'];
$severity_row = (new CList())->addClass(ZBX_STYLE_LIST_CHECK_RADIO);

foreach ($data['severities'] as $severity => $severity_name) {
	$severity_row->addItem(
		(new CCheckBox('severity['.$severity.']', $severity))
			->setLabel($severity_name)
			->setChecked(str_in_array($severity, $options['severities']))
	);
}

// Create table of email addresses.
$email_send_to_table = (new CTable())->setId('email_send_to');

foreach ($options['sendto_emails'] as $i => $email) {
	$email_send_to_table->addRow([
		(new CTextBox('sendto_emails['.$i.']', $email))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CButton('sendto_emails['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
	], 'form_row');
}

$email_send_to_table->setFooter(new CCol(
	(new CButton('email_send_to_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));

// Create media form.
$media_form = (new CFormList(_('Media')))
	->addRow(_('Type'), new CComboBox('mediatypeid', $options['mediatypeid'], null, $data['db_mediatypes']))
	->addRow(
		(new CLabel(_('Send to'), 'sendto'))->setAsteriskMark(),
		(new CTextBox('sendto', $options['sendto'], false, 1024))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'mediatype_send_to'
	)
	->addRow(
		(new CLabel(_('Send to'), 'mediatype_email_send_to'))->setAsteriskMark(),
		$email_send_to_table,
		'mediatype_email_send_to'
	)
	->addRow((new CLabel(_('When active'), 'period'))->setAsteriskMark(),
		(new CTextBox('period', $options['period'], false, 1024))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Use if severity'), $severity_row)
	->addRow(_('Enabled'),
		(new CCheckBox('active', MEDIA_STATUS_ACTIVE))->setChecked($options['active'] == MEDIA_STATUS_ACTIVE)
	);

$form = (new CForm())
	->cleanItems()
	->setName('media_form')
	->addVar('action', 'popup.media')
	->addVar('add', '1')
	->addVar('media', $options['media'])
	->addVar('type', $options['type'])
	->addVar('dstfrm', $options['dstfrm'])
	->setId('media_form')
	->addItem([
		$media_form,
		(new CInput('submit', 'submit'))->addStyle('display: none;'),
		(new CTag('script'))
			->addItem((new CRow([
				(new CCol((new CTextBox('sendto_emails[#{rowNum}]', ''))
					->setAriaRequired()
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				)),
				(new CCol((new CButton('sendto_emails[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
				)),
			]))
				->addClass('form_row'))
				->setAttribute('type', 'text/x-jquery-tmpl')
				->setAttribute('id', 'email_send_to_table_row')
	]);

$output = [
	'header' => $data['title'],
	'script_inline' => require 'app/views/popup.media.js.php',
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => ($options['media'] !== -1) ? _('Update') : _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return validateMedia("'.$form->getName().'");'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
