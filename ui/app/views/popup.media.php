<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 */

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
		(new CTextBox('sendto_emails['.$i.']', $email, $options['provisioned'] == CUser::PROVISION_STATUS_YES))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CButton('sendto_emails['.$i.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
			->setEnabled($options['provisioned'] == CUser::PROVISION_STATUS_NO)
	], 'form_row dynamic-row');
}

$email_send_to_table->setFooter(new CCol(
	(new CButton('email_send_to_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
		->setEnabled($options['provisioned'] == CUser::PROVISION_STATUS_NO)
), 'dynamic-row-control');

$type_select = (new CSelect('mediatypeid'))
	->setId('mediatypeid')
	->setFocusableElementId('label-mediatypeid')
	->setValue($options['mediatypeid'])
	->setReadonly($options['provisioned'] == CUser::PROVISION_STATUS_YES);

foreach ($data['db_mediatypes'] as $mediatypeid => $value) {
	if ($options['mediatypeid'] == $mediatypeid || $value['status'] != MEDIA_TYPE_STATUS_DISABLED) {
		$type_select->addOption((new CSelectOption($mediatypeid, $value['name']))
			->addClass($value['status'] == MEDIA_TYPE_STATUS_DISABLED ? ZBX_STYLE_RED : null)
		);
	}
}

$disabled_media_types_msg = null;

if (!in_array(MEDIA_TYPE_STATUS_ACTIVE, array_column($data['db_mediatypes'], 'status'))) {
	$type_select->addStyle('display: none;');

	$disabled_media_types_msg = (new CDiv(_('Media types disabled in Alerts.')))
		->addClass(ZBX_STYLE_RED)
		->addStyle('margin:1px 0 0 5px;');
}

// Create media form.
$media_form = (new CFormList(_('Media')))
	->addRow(new CLabel(_('Type'), $type_select->getFocusableElementId()), [$type_select, $disabled_media_types_msg])
	->addRow(
		(new CLabel(_('Send to'), 'sendto'))->setAsteriskMark(),
		(new CTextBox('sendto', $options['sendto'], $options['provisioned'] == CUser::PROVISION_STATUS_YES, 1024))
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
	->setName('media_form')
	->addVar('action', 'popup.media')
	->addVar('add', '1')
	->addVar('media', $options['media'])
	->addVar('dstfrm', $options['dstfrm'])
	->setId('media_form')
	->addStyle('display: none;');

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form->addItem([
	$media_form,
	(new CInput('submit', 'submit'))->addStyle('display: none;'),
	(new CTag('script'))
		->addItem((new CRow([
			(new CCol(
				(new CTextBox('sendto_emails[#{rowNum}]', ''))
					->setAriaRequired()
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)),
			(new CCol(
				(new CButton('sendto_emails[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))
		]))
			->addClass('form_row')
			->addClass('dynamic-row'))
			->setAttribute('type', 'text/x-jquery-tmpl')
			->setAttribute('id', 'email_send_to_table_row')
]);

$output = [
	'header' => $data['title'],
	'script_inline' => $this->readJsFile('popup.media.js.php'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => ($options['media'] !== -1) ? _('Update') : _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return validateMedia(overlay);'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
