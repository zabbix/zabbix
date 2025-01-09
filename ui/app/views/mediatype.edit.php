<?php declare(strict_types = 0);
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
 * @var array $data
 */

$tabs = new CTabView();

// Create form.
$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('mediatype')))->removeId())
	->setId('media-type-form')
	->addVar('mediatypeid', $data['mediatypeid'])
	->disablePasswordAutofill()
	->addItem((new CInput('submit', null))->addStyle('display: none;'))
	->addStyle('display: none;');

// Create form grid.
$mediatype_form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('name', $data['name'], false, DB::getFieldLength('media_type', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		))->setId('name-field')
	])
	->addItem([
		(new CLabel(_('Type'), 'label-type')),
		(new CFormField(
			(new CSelect('type'))
				->setId('type')
				->setFocusableElementId('label-type')
				->addOptions(CSelect::createOptionsFromArray(CMediatypeHelper::getMediaTypes()))
				->setValue($data['type'])
		))->setId('type-field')
	])
	->addItem([
		(new CLabel(_('Email provider'), 'label-provider'))->setId('email-provider-label'),
		(new CFormField(
			(new CSelect('provider'))
				->setId('provider')
				->setFocusableElementId('label-provider')
				->addOptions(CSelect::createOptionsFromArray(CMediatypeHelper::getAllEmailProvidersNames()))
				->setValue($data['provider'])
		))->setId('email-provider-field')
	])
	->addItem([
		(new CLabel(_('SMTP server'), 'smtp_server'))
			->setId('smtp-server-label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('smtp_server', $data['smtp_server']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('smtp-server-field')
	])
	->addItem([
		(new CLabel(_('SMTP server port'), 'smtp_port'))->setId('smtp-port-label'),
		(new CFormField(
			(new CNumericBox(
				'smtp_port', $data['smtp_port'], 5, false, false, false)
			)->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		))->setId('smtp-port-field')
	])
	->addItem([
		(new CLabel(_('Email'), 'smtp_email'))
			->setId('smtp-email-label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('smtp_email', $data['smtp_email']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('smtp-email-field')
	])
	->addItem([
		(new CLabel(_('SMTP helo'), 'smtp_helo'))->setId('smtp-helo-label'),
		(new CFormField(
			(new CTextBox('smtp_helo', $data['smtp_helo']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('smtp-helo-field')
	])
	->addItem([
		(new CLabel(_('Connection security'), 'smtp_security'))->setId('smtp-security-label'),
		(new CFormField(
			(new CRadioButtonList('smtp_security', (int) $data['smtp_security']))
				->addValue(_('None'), SMTP_SECURITY_NONE)
				->addValue(_('STARTTLS'), SMTP_SECURITY_STARTTLS)
				->addValue(_('SSL/TLS'), SMTP_SECURITY_SSL)
				->setModern()
		))->setId('smtp-security-field')
	])
	->addItem([
		(new CLabel(_('SSL verify peer')))->setId('verify-peer-label'),
		(new CFormField(
			(new CCheckBox('smtp_verify_peer'))->setChecked($data['smtp_verify_peer'])
		))->setId('verify-peer-field')
	])
	->addItem([
		(new CLabel(_('SSL verify host')))->setId('verify-host-label'),
		(new CFormField(
			(new CCheckBox('smtp_verify_host'))->setChecked($data['smtp_verify_host'])
		))->setId('verify-host-field')
	])
	->addItem([
		(new CLabel(_('Authentication'), 'smtp_authentication'))->setId('smtp-authentication-label'),
		(new CFormField(
			(new CRadioButtonList('smtp_authentication', (int) $data['smtp_authentication']))
				->addValue(_('None'), SMTP_AUTHENTICATION_NONE)
				->addValue(_('Username and password'), SMTP_AUTHENTICATION_NORMAL)
				->setModern()
		))->setId('smtp-authentication-field')
	])
	->addItem([
		(new CLabel(_('Username'), 'smtp_username'))->setId('smtp-username-label'),
		(new CFormField(
			(new CTextBox('smtp_username', $data['smtp_username']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH))
		)->setId('smtp-username-field')
	])
	->addItem([
		(new CLabel(_('Script name'), 'exec_path'))
			->setId('exec-path-label')
			->setAsteriskMark(),
		(new CFormField([
			(new CTextBox('exec_path', $data['exec_path']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		]))->setId('exec-path-field')
	]);

// MEDIA_TYPE_EXEC
$parameters_exec_table = (new CTable())
	->setId('exec_params_table')
	->setHeader([
		(new CColHeader(_('Value')))->setWidth('100%'),
		''
	])
	->addStyle('width: 100%;')
	->addItem(
		(new CTag('tfoot', true))
			->addItem(
				(new CCol(
					(new CButtonLink(_('Add')))->addClass('element-table-add')
				))->setColSpan(2)
			)
	);

$parameters_exec_template = (new CTemplateTag('exec_params_template'))
	->addItem(
		(new CRow([
			(new CTextBox('parameters_exec[#{row_num}][value]', '', false, DB::getFieldLength('script_param', 'name')))
				->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				->setAttribute('maxlength', DB::getFieldLength('media_type_param', 'name'))
				->setAttribute('value', '#{value}')
				->setId('parameters_exec_#{rowNum}_value')
				->removeId(),
			(new CButtonLink(_('Remove')))
				->removeId()
				->addClass('js-remove')
		]))->addClass('form_row')
	);

$mediatype_form_grid
	->addItem([
		(new CLabel([
			_('Script parameters'),
			makeHelpIcon(
				_('These parameters will be passed to the script as command-line arguments in the specified order.')
			)
		]))->setId('row_exec_params_label'),
		(new CFormField(
			(new CFormField($parameters_exec_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')

		))->setId('row_exec_params_field')
	])
	->addItem([
		(new CLabel(_('GSM modem'), 'gsm_modem'))
			->setId('gsm_modem_label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('gsm_modem', $data['gsm_modem']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('gsm_modem_field')
	]);

if (!$data['display_password_input']) {
	// Disabling 'passwd' field prevents stored passwords autofill by browser.
	$passwd_field = [
		(new CButton('chPass_btn', _('Change password'))),
		(new CPassBox('passwd', ''))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
			->addStyle('display: none;')
			->setEnabled(false)
	];
}
else {
	$passwd_field = (new CPassBox('passwd', ''))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
}

// MEDIA_TYPE_WEBHOOK
$parameters_table = (new CTable())
	->setId('parameters_table')
	->setHeader([
		(new CColHeader(_('Name')))->setWidth('50%'),
		(new CColHeader(_('Value')))->setWidth('50%'),
		''
	])
	->addStyle('width: 100%;')
	->addItem(
		(new CTag('tfoot', true))
			->addItem(
				(new CCol(
					(new CButtonLink(_('Add')))->addClass('webhook-param-add')
				))->setColSpan(2)
			)
	);

$webhook_params_template = (new CTemplateTag('webhook_params_template'))
	->addItem(
		(new CRow([
			(new CTextBox('parameters_webhook[name][]', '', false, DB::getFieldLength('media_type_param', 'name')))
				->addStyle('width: 100%;')
				->setAttribute('value', '#{name}')
				->removeId(),
			(new CTextBox('parameters_webhook[value][]', '', false, DB::getFieldLength('media_type_param', 'value')))
				->addStyle('width: 100%;')
				->setAttribute('value', '#{value}')
				->removeId(),
			(new CButtonLink(_('Remove')))
				->removeId()
				->addClass('js-remove')
		]))->addClass('form_row')
	);

$form->addItem($webhook_params_template);

// Append password field to form grid.
$mediatype_form_grid
	->addItem([
		(new CLabel(_('Password'), 'passwd'))->setId('passwd_label'),
		(new CFormField($passwd_field))->setId('passwd_field')
	])
	->addItem([
		(new CLabel(_('Message format'), 'message_format'))->setId('message_format_label'),
		(new CFormField(
			(new CRadioButtonList('message_format', (int) $data['message_format']))
				->addValue(_('HTML'), ZBX_MEDIA_MESSAGE_FORMAT_HTML)
				->addValue(_('Plain text'), ZBX_MEDIA_MESSAGE_FORMAT_TEXT)
				->setModern()
		))->setId('message_format_field')
	])
	->addItem([
		(new CLabel(_('Parameters'), $parameters_table->getId()))->setId('webhook_parameters_label'),
		(new CFormField(
			(new CDiv($parameters_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		))->setId('webhook_parameters_field')
	])
	->addItem([
		(new CLabel(_('Script'), 'script'))
			->setId('webhook_script_label')
			->setAsteriskMark(),
		(new CFormField(
			(new CMultilineInput('script', $data['script'], [
				'title' => _('JavaScript'),
				'placeholder' => _('script'),
				'placeholder_textarea' => 'return value',
				'grow' => 'auto',
				'rows' => 0,
				'maxlength' => DB::getFieldLength('media_type', 'script')
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('webhook_script_field')
	])
	->addItem([
		(new CLabel(_('Timeout'), 'timeout'))
			->setId('webhook_timeout_label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('timeout', $data['timeout']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		))->setId('webhook_timeout_field')
	])
	->addItem([
		(new CLabel(_('Process tags'), 'process_tags'))->setId('webhook_tags_label'),
		(new CFormField(
			(new CCheckBox('process_tags', ZBX_MEDIA_TYPE_TAGS_ENABLED))
				->setChecked($data['process_tags'] == ZBX_MEDIA_TYPE_TAGS_ENABLED)
				->setUncheckedValue(ZBX_MEDIA_TYPE_TAGS_DISABLED)
		))->setId('webhook_tags_field')
	])
	->addItem([
		(new CLabel(_('Include event menu entry'), 'show_event_menu'))->setId('webhook_event_menu_label'),
		(new CFormField(
			(new CCheckBox('show_event_menu', $data['show_event_menu']))
				->setChecked($data['show_event_menu'] == ZBX_EVENT_MENU_SHOW)
				->setAttribute('value', ZBX_EVENT_MENU_SHOW)
				->setUncheckedValue(0)
		))->setId('webhook_event_menu_field')
	])
	->addItem([
		(new CLabel(_('Menu entry name'), 'event_menu_name'))
			->setId('webhook_url_name_label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('event_menu_name', $data['event_menu_name'], false,
				DB::getFieldLength('media_type', 'event_menu_name')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setEnabled($data['show_event_menu'] == ZBX_EVENT_MENU_SHOW)
				->setAriaRequired()
		))->setId('webhook_url_name_field')
	])
	->addItem([
		(new CLabel(_('Menu entry URL'), 'event_menu_url'))
			->setId('webhook_event_menu_url_label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('event_menu_url', $data['event_menu_url'], false,
				DB::getFieldLength('media_type', 'event_menu_url')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setEnabled($data['show_event_menu'] == ZBX_EVENT_MENU_SHOW)
				->setAriaRequired()
		))->setId('webhook_event_menu_url_field')
	])
	->addItem([
		(new CLabel(_('Description'), 'description')),
		new CFormField(
			(new CTextArea('description', $data['description']))
				->setAttribute('maxlength', DB::getFieldLength('media_type', 'description'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', MEDIA_TYPE_STATUS_ACTIVE))->setChecked($data['status'] == MEDIA_TYPE_STATUS_ACTIVE)
		)
	]);

$message_template = (new CTemplateTag('message-templates-row-tmpl'))
	->addItem(
		(new CRow([
			new CCol('#{message_type_name}'),
			(new CCol([
				new CSpan('#{message}'),
				new CInput('hidden', 'message_templates[#{message_type}][eventsource]', '#{eventsource}'),
				new CInput('hidden', 'message_templates[#{message_type}][recovery]', '#{recovery}'),
				new CInput('hidden', 'message_templates[#{message_type}][subject]', '#{subject}'),
				new CInput('hidden', 'message_templates[#{message_type}][message]', '#{message}')
			]))
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
				->addStyle('max-width: '.ZBX_TEXTAREA_MEDIUM_WIDTH.'px;'),
			(new CHorList([
				(new CButtonLink(_('Edit')))->setAttribute('data-action', 'edit'),
				(new CButtonLink(_('Remove')))->addClass('js-remove-msg-template')
			]))->addClass(ZBX_STYLE_NOWRAP)
		]))->setAttribute('data-message-type', '#{message_type}')
	);

// Message templates tab.
$message_templates_form_grid = (new CFormGrid())
	->setId('messageTemplatesFormlist')
	->addItem([
		(new CLabel(_('Message templates')))->addClass(ZBX_STYLE_WORDBREAK),
		(new CFormField(
			(new CTable())
				->addStyle('width: 100%;')
				->setHeader([
					_('Message type'),
					_('Template'),
					_('Actions')
				])
				->addItem(
					(new CTag('tfoot', true))
						->setId('message-templates-footer')
						->addItem(
							(new CCol(
								(new CButtonLink(_('Add')))
									->setAttribute('data-action', 'add')
									->addClass('msg-template-add')
							))->setColSpan(2)
						)
				)
		))
			->setId('message-templates')
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	])
	->addItem($message_template);

// Media options tab.
$max_sessions = $data['maxsessions'] > 1 ? $data['maxsessions'] : 0;

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

$media_options_form_grid = (new CFormGrid())
	->setId('options')
	->addItem([
		new CLabel(_('Concurrent sessions'), 'maxsessions_type'),
		(new CFormField([
			(new CRadioButtonList('maxsessions_type', $data['maxsessions_type']))
				->addValue(_('One'), 'one')
				->addValue(_('Unlimited'), 'unlimited')
				->addValue(_('Custom'), 'custom')
				->setModern(true)
				->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CNumericBox('maxsessions', $max_sessions, 3, false, false, false))
				->setAriaRequired()
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		]))->addClass(ZBX_STYLE_NOWRAP)
	])
	->addItem([
		(new CLabel(_('Attempts'), 'maxattempts'))->setAsteriskMark(),
		new CFormField(
			(new CNumericBox('maxattempts', $data['maxattempts'], 3, false, false, false))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Attempt interval'), 'attempt_interval'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('attempt_interval', $data['attempt_interval'], false, 12))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		)
	]);

$tabs
	->addTab('media_tab', _('Media type'), $mediatype_form_grid)
	->addTab('msg_templates_tab', _('Message templates'), $message_templates_form_grid, TAB_INDICATOR_MESSAGE_TEMPLATE)
	->addTab('options_tab', _('Options'), $media_options_form_grid, TAB_INDICATOR_MEDIATYPE_OPTIONS)
	->setSelected(0);

$email_defaults =  CMediatypeHelper::getEmailProviders(CMediatypeHelper::EMAIL_PROVIDER_SMTP);

// Append tabs to form.
$form
	->addItem($tabs)
	->addItem($parameters_exec_template)
	->addItem(
		(new CScriptTag('mediatype_edit_popup.init('.json_encode([
			'mediatype' => $data,
			'message_templates' => CMediatypeHelper::getAllMessageTemplates(),
			'smtp_server_default' => $email_defaults['smtp_server'],
			'smtp_email_default' =>  $email_defaults['smtp_email']
		]).');'))->setOnDocumentReady()
	);

if ($data['mediatypeid']) {
	$buttons = [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'mediatype_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'mediatype_edit_popup.clone('.json_encode([
					'title' => _('New media type'),
					'buttons' => [
						[
							'title' => _('Add'),
							'keepOpen' => true,
							'isSubmit' => true,
							'action' => 'mediatype_edit_popup.submit();'
						],
						[
							'title' => _('Cancel'),
							'class' => ZBX_STYLE_BTN_ALT,
							'cancel' => true,
							'action' => ''
						]
					]
				]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete media type?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'mediatype_edit_popup.delete();'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'mediatype_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $data['mediatypeid'] === null ? _('New media type') : _('Media type'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::ALERTS_MEDIATYPE_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('mediatype.edit.js.php'),
	'dialogue_class' => 'modal-popup-static'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
