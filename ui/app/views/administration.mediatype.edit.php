<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 */

$this->addJsFile('multilineinput.js');

$this->includeJsFile('administration.mediatype.edit.js.php');

$widget = (new CWidget())->setTitle(_('Media types'));

$tabs = new CTabView();

if ($data['form_refresh'] == 0) {
	$tabs->setSelected(0);
}

// create form
$mediaTypeForm = (new CForm())
	->setId('media-type-form')
	->addVar('form', 1)
	->addVar('mediatypeid', $data['mediatypeid'])
	->addItem((new CVar('status', MEDIA_TYPE_STATUS_DISABLED))->removeId())
	->disablePasswordAutofill()
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE);

// Create form list.
$mediatype_formlist = (new CFormList())
	->addRow((new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['name'], false, 100))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(new CLabel(_('Type'), 'label-type'), (new CSelect('type'))
		->setId('type')
		->setFocusableElementId('label-type')
		->addOptions(CSelect::createOptionsFromArray(media_type2str()))
		->setValue($data['type'])
	)
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
	->addRow(new CLabel(_('Connection security'), 'smtp_security'),
		(new CRadioButtonList('smtp_security', (int) $data['smtp_security']))
			->addValue(_('None'), SMTP_CONNECTION_SECURITY_NONE)
			->addValue(_('STARTTLS'), SMTP_CONNECTION_SECURITY_STARTTLS)
			->addValue(_('SSL/TLS'), SMTP_CONNECTION_SECURITY_SSL_TLS)
			->setModern(true)
	)
	->addRow(_('SSL verify peer'), (new CCheckBox('smtp_verify_peer'))->setChecked($data['smtp_verify_peer']))
	->addRow(_('SSL verify host'), (new CCheckBox('smtp_verify_host'))->setChecked($data['smtp_verify_host']))
	->addRow(new CLabel(_('Authentication'), 'smtp_authentication'),
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
		], 'form_row');
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
if ($data['passwd'] !== '' && !$data['change_passwd']) {
	// Disabling 'passwd' field prevents stored passwords autofill by browser.
	$passwd_field = [
		(new CButton('chPass_btn', _('Change password'))),
		(new CPassBox('passwd', ''))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
			->addStyle('display: none;')
			->setAttribute('disabled', 'disabled')
	];
}
else {
	$passwd_field = (new CPassBox('passwd', $data['passwd']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
}

// MEDIA_TYPE_WEBHOOK
$parameters_table = (new CTable())
	->setId('parameters_table')
	->setHeader([
		(new CColHeader(_('Name')))->setWidth('50%'),
		(new CColHeader(_('Value')))->setWidth('50%'),
		_('Action')
	])
	->setAttribute('style', 'width: 100%;');

foreach ($data['parameters'] as $parameter) {
	$parameters_table->addRow([
		(new CTextBox('parameters[name][]', $parameter['name'], false, DB::getFieldLength('media_type_param', 'name')))
			->setAttribute('style', 'width: 100%;')
			->removeId(),
		(new CTextBox('parameters[value][]', $parameter['value'], false,
			DB::getFieldLength('media_type_param', 'value')
		))
			->setAttribute('style', 'width: 100%;')
			->removeId(),
		(new CButton('', _('Remove')))
			->removeId()
			->onClick('jQuery(this).closest("tr").remove()')
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
	], 'form_row');
}

$row_template = (new CTag('script', true))
	->setId('parameters_row')
	->setAttribute('type', 'text/x-jquery-tmpl')
	->addItem(
		(new CRow([
			(new CTextBox('parameters[name][]', '', false, DB::getFieldLength('media_type_param', 'name')))
				->setAttribute('style', 'width: 100%;')
				->removeId(),
			(new CTextBox('parameters[value][]', '', false, DB::getFieldLength('media_type_param', 'value')))
				->setAttribute('style', 'width: 100%;')
				->removeId(),
			(new CButton('', _('Remove')))
				->removeId()
				->onClick('jQuery(this).closest("tr").remove()')
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row')
	);

$widget->addItem($row_template);

$parameters_table->addRow([(new CButton('parameter_add', _('Add')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->addClass('element-table-add')]);

// append password field to form list
$mediatype_formlist
	->addRow(new CLabel(_('Password'), 'passwd'), $passwd_field)
	->addRow(new CLabel(_('Message format'), 'content_type'),
		(new CRadioButtonList('content_type', (int) $data['content_type']))
			->addValue(_('HTML'), SMTP_MESSAGE_FORMAT_HTML)
			->addValue(_('Plain text'), SMTP_MESSAGE_FORMAT_PLAIN_TEXT)
			->setModern(true)
	)
	->addRow(new CLabel(_('Parameters'), $parameters_table->getId()),
		(new CDiv($parameters_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
		'row_webhook_parameters'
	)
	->addRow((new CLabel(_('Script'), 'script'))->setAsteriskMark(),
		(new CMultilineInput('script', $data['script'], [
			'title' => _('JavaScript'),
			'placeholder' => _('script'),
			'placeholder_textarea' => 'return value',
			'grow' => 'auto',
			'rows' => 0,
			'maxlength' => DB::getFieldLength('media_type', 'script')
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		'row_webhook_script'
	)
	->addRow((new CLabel(_('Timeout'), 'timeout'))->setAsteriskMark(),
		(new CTextBox('timeout', $data['timeout']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		'row_webhook_timeout'
	)
	->addRow(new CLabel(_('Process tags'), 'process_tags'),
		(new CCheckBox('process_tags', ZBX_MEDIA_TYPE_TAGS_ENABLED))
			->setChecked($data['process_tags'] == ZBX_MEDIA_TYPE_TAGS_ENABLED)
			->setUncheckedValue(ZBX_MEDIA_TYPE_TAGS_DISABLED),
		'row_webhook_tags'
	)
	->addRow(new CLabel(_('Include event menu entry'), 'show_event_menu'),
		(new CCheckBox('show_event_menu', ZBX_EVENT_MENU_SHOW))
			->setChecked($data['show_event_menu'] == ZBX_EVENT_MENU_SHOW)
			->setUncheckedValue(ZBX_EVENT_MENU_HIDE),
		'row_webhook_show_event_menu'
	)
	->addRow((new CLabel(_('Menu entry name'), 'event_menu_name'))->setAsteriskMark(),
		(new CTextBox('event_menu_name', $data['event_menu_name'], false,
			DB::getFieldLength('media_type', 'event_menu_name')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setEnabled($data['show_event_menu'] == ZBX_EVENT_MENU_SHOW)
			->setAriaRequired(),
		'row_webhook_url_name'
	)
	->addRow((new CLabel(_('Menu entry URL'), 'event_menu_url'))->setAsteriskMark(),
		(new CTextBox('event_menu_url', $data['event_menu_url'], false,
			DB::getFieldLength('media_type', 'event_menu_url')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setEnabled($data['show_event_menu'] == ZBX_EVENT_MENU_SHOW)
			->setAriaRequired(),
		'row_webhook_event_menu_url'
	)
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Enabled'),
		(new CCheckBox('status', MEDIA_TYPE_STATUS_ACTIVE))->setChecked($data['status'] == MEDIA_TYPE_STATUS_ACTIVE)
	);
$tabs->addTab('mediaTab', _('Media type'), $mediatype_formlist);

// Message templates tab.
$message_templates_formlist = (new CFormList('messageTemplatesFormlist'))
	->addRow(null,
		(new CDiv(
			(new CTable())
				->addStyle('width: 100%;')
				->setHeader([
					_('Message type'),
					_('Template'),
					_('Actions')
				])
				->setFooter(
					(new CRow(
						(new CCol(
							(new CSimpleButton(_('Add')))
								->setAttribute('data-action', 'add')
								->addClass(ZBX_STYLE_BTN_LINK)
						))->setColSpan(3)
					))->setId('message-templates-footer')
				)
		))
			->setId('message-templates')
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
$tabs->addTab('messageTemplatesTab', _('Message templates'), $message_templates_formlist,
	TAB_INDICATOR_MESSAGE_TEMPLATE
);

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
	->addRow(new CLabel(_('Concurrent sessions'), 'maxsessions_type'),
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
$cancelButton = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
	->setArgument('action', 'mediatype.list')
	->setArgument('page', CPagerHelper::loadPage('mediatype.list', null))
))->setId('cancel');

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
