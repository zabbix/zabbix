<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
		(new CTextBox('sendto_emails['.$i.']', $email))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
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
	->addRow(_('Send to'),
		(new CTextBox('sendto', $options['sendto'], false, 100))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'mediatype_send_to'
	)
	->addRow(_('Send to'), $email_send_to_table, 'mediatype_email_send_to')
	->addRow(_('When active'),
		(new CTextBox('period', $options['period'], false, 1024))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Use if severity'), $severity_row)
	->addRow(_('Enabled'),
		(new CCheckBox('active', MEDIA_STATUS_ACTIVE))->setChecked($options['active'] == MEDIA_STATUS_ACTIVE)
	);

$body_html = (new CForm())
		->addVar('action', 'popup.media')
		->addVar('add', '1')
		->addVar('media', $options['media'])
		->addVar('type', $options['type'])
		->addVar('dstfrm', $options['dstfrm'])
		->addItem([
			(new CTabView())->addTab('mediaTab', _('Media'), $media_form),
			(new CInput('submit', 'submit'))->addStyle('display: none;')
		])
		->setId('media_form')
		->toString();

$body_html .= (new CTag('script'))
	->addItem((new CRow([
		(new CCol((new CTextBox('sendto_emails[#{rowNum}]', ''))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))),
		(new CCol((new CButton('sendto_emails[#{rowNum}][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
		)),
	]))
		->addClass('form_row'))
	->setAttribute('type', 'text/x-jquery-tmpl')
	->setAttribute('id', 'email_send_to_table_row')
	->toString();

$output = [
	'header' => $data['title'],
	'body' => $body_html,
	'buttons' => [
		[
			'title' => ($options['media'] !== -1) ? _('Update') : _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return validate_media("media_form");'
		]
	],
	'script_inline' =>
		'jQuery(document).ready(function($) {'.
			'\'use strict\';'.
			''.
			'$("#email_send_to").dynamicRows({'.
				'template: "#email_send_to_table_row"'.
			'});'.

			// Show/hide multiple "Send to" inputs and single "Send to" input and populate hidden "type" field.
			'$("#mediatypeid")'.
				'.on("change", function() {'.
					'var mediatypes_by_type = '.(new CJson())->encode($data['mediatypes']).','.
						'mediatypeid = $(this).val();'.

					'$("#type").val(mediatypes_by_type[mediatypeid]);'.

					'if (mediatypes_by_type[mediatypeid] == '.MEDIA_TYPE_EMAIL.') {'.
						'$("#mediatype_send_to").hide();'.
						'$("#mediatype_email_send_to").show();'.
					'}'.
					'else {'.
						'$("#mediatype_send_to").show();'.
						'$("#mediatype_email_send_to").hide();'.
					'}'.
				'})'.
				'.trigger("change");'.
		'});'
];

echo (new CJson())->encode($output);
