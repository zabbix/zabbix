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

$form = (new CForm('post'))
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('connector')))->removeId())
	->setId('connector-form')
	->setName('connector_form')
	->addItem(getMessages())
	->addStyle('display: none;');

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['form']['name'], false, DB::getFieldLength('connector', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		new CLabel(_('Protocol')),
		new CFormField([
			_('Zabbix Streaming Protocol v1.0'),
			new CInput('hidden', 'protocol', $data['form']['protocol'])
		])
	])
	->addItem([
		new CLabel(_('Data type'), 'data_type'),
		new CFormField(
			(new CRadioButtonList('data_type', $data['form']['data_type']))
				->addValue(_('Item values'), ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES)
				->addValue(_('Events'), ZBX_CONNECTOR_DATA_TYPE_EVENTS)
				->setModern()
		)
	])
	->addItem([
		(new CLabel(_('URL'), 'url'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('url', $data['form']['url'], false, DB::getFieldLength('connector', 'url')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel(_('Tag filter'), 'tags_evaltype'),
		new CFormField(
			(new CRadioButtonList('tags_evaltype', $data['form']['tags_evaltype']))
				->addValue(_('And/Or'), CONDITION_EVAL_TYPE_AND_OR)
				->addValue(_('Or'), CONDITION_EVAL_TYPE_OR)
				->setModern()
		)
	])
	->addItem(
		new CFormField([
			(new CTable())
				->setId('tags')
				->addClass('table-tags')
				->addClass(ZBX_STYLE_TABLE_INITIAL_WIDTH)
				->setFooter(
					new CCol(
						(new CButtonLink(_('Add')))->addClass('element-table-add')
					)
				),
			(new CTemplateTag('tag-row-tmpl'))->addItem(
				(new CRow([
					(new CTextBox('tags[#{rowNum}][tag]', '#{tag}', false,
						DB::getFieldLength('connector_tag', 'tag')
					))
						->setAttribute('placeholder', _('tag'))
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
					(new CSelect('tags[#{rowNum}][operator]'))
						->addClass('js-tag-operator')
						->setValue(CONDITION_OPERATOR_EQUAL)
						->addOptions(CSelect::createOptionsFromArray([
							CONDITION_OPERATOR_EXISTS => _('Exists'),
							CONDITION_OPERATOR_EQUAL => _('Equals'),
							CONDITION_OPERATOR_LIKE => _('Contains'),
							CONDITION_OPERATOR_NOT_EXISTS => _('Does not exist'),
							CONDITION_OPERATOR_NOT_EQUAL => _('Does not equal'),
							CONDITION_OPERATOR_NOT_LIKE => _('Does not contain')
						])),
					(new CTextBox('tags[#{rowNum}][value]', '#{value}', false,
						DB::getFieldLength('connector_tag', 'value')
					))
						->addClass('js-tag-value')
						->setAttribute('placeholder', _('value'))
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
					(new CButtonLink(_('Remove')))->addClass('element-table-remove')
				]))->addClass('form_row')
			)
		])
	)
	->addItem([
		(new CLabel(_('Type of information'), 'item_value_types'))
			->setAsteriskMark()
			->addClass('js-field-item-value-types'),
		(new CFormField(
			(new CCheckBoxList('item_value_types'))
				->setOptions([
					[
						'value' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_UINT64,
						'label' => _('Numeric (unsigned)'),
						'checked' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_UINT64 & $data['form']['item_value_type']
					],
					[
						'value' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_FLOAT,
						'label' => _('Numeric (float)'),
						'checked' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_FLOAT & $data['form']['item_value_type']
					],
					[
						'value' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_STR,
						'label' => _('Character'),
						'checked' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_STR & $data['form']['item_value_type']
					],
					[
						'value' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_LOG,
						'label' => _('Log'),
						'checked' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_LOG & $data['form']['item_value_type']
					],
					[
						'value' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_TEXT,
						'label' => _('Text'),
						'checked' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_TEXT & $data['form']['item_value_type']
					],
					[
						'value' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_BIN,
						'label' => _('Binary'),
						'checked' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_BIN & $data['form']['item_value_type']
					]
				])
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setVertical()
				->setColumns(3)
		))->addClass('js-field-item-value-types')
	])
	->addItem([
		new CLabel(_('HTTP authentication'), 'authtype-focusable'),
		new CFormField(
			(new CSelect('authtype'))
				->setId('authtype')
				->setFocusableElementId('authtype-focusable')
				->setValue($data['form']['authtype'])
				->addOptions(CSelect::createOptionsFromArray([
					ZBX_HTTP_AUTH_NONE => _('None'),
					ZBX_HTTP_AUTH_BASIC => _('Basic'),
					ZBX_HTTP_AUTH_NTLM => _('NTLM'),
					ZBX_HTTP_AUTH_KERBEROS => _('Kerberos'),
					ZBX_HTTP_AUTH_DIGEST => _('Digest'),
					ZBX_HTTP_AUTH_BEARER => _('Bearer')
				]))
		)
	])
	->addItem([
		(new CLabel(_('Username'), 'username'))->addClass('js-field-username'),
		(new CFormField(
			(new CTextBox('username', $data['form']['username'], false, DB::getFieldLength('connector', 'username')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass('js-field-username')
	])
	->addItem([
		(new CLabel(_('Password'), 'password'))->addClass('js-field-password'),
		(new CFormField(
			(new CTextBox('password', $data['form']['password'], false, DB::getFieldLength('connector', 'password')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableAutocomplete()
		))->addClass('js-field-password')
	])
	->addItem([
		(new CLabel(_('Bearer token'), 'token'))
			->addClass('js-field-token')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('token', $data['form']['token'], false, DB::getFieldLength('connector', 'token')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass('js-field-token')
	])
	->addItem(
		(new CFormFieldsetCollapsible(_('Advanced configuration')))
			->setId('advanced-configuration')
			->addItem([
				(new CLabel(_('Max records per message'), 'max_records'))->setAsteriskMark(),
				new CFormField([
					(new CRadioButtonList('max_records_mode', $data['form']['max_records_mode']))
						->addValue(_('Unlimited'), 0)
						->addValue(_('Custom'), 1)
						->setModern()
						->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CNumericBox('max_records', $data['form']['max_records'], 10, false, false, false))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				])
			])
			->addItem([
				(new CLabel(_('Concurrent sessions'), 'max_senders'))->setAsteriskMark(),
				new CFormField(
					(new CNumericBox('max_senders', $data['form']['max_senders'], 3, false, false, false))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('Attempts'), 'max_attempts'))->setAsteriskMark(),
				new CFormField(
					(new CNumericBox('max_attempts', $data['form']['max_attempts'], 1, false, false, false))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('Attempt interval'), 'attempt_interval'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('attempt_interval', $data['form']['attempt_interval'], false,
						DB::getFieldLength('connector', 'attempt_interval')
					))
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				(new CLabel(_('Timeout'), 'timeout'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('timeout', $data['form']['timeout'], false,
						DB::getFieldLength('connector', 'timeout')
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setAriaRequired()
				)
			])
			->addItem([
				new CLabel(_('HTTP proxy'), 'http_proxy'),
				new CFormField(
					(new CTextBox('http_proxy', $data['form']['http_proxy'], false,
						DB::getFieldLength('connector', 'http_proxy')
					))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAttribute('placeholder', _('[protocol://][user[:password]@]proxy.example.com[:port]'))
						->disableAutocomplete()
				)
			])
			->addItem([
				new CLabel(_('SSL verify peer'), 'verify_peer'),
				new CFormField(
					(new CCheckBox('verify_peer', ZBX_HTTP_VERIFY_PEER_ON))
						->setChecked($data['form']['verify_peer'] == ZBX_HTTP_VERIFY_PEER_ON)
				)
			])
			->addItem([
				new CLabel(_('SSL verify host'), 'verify_host'),
				new CFormField(
					(new CCheckBox('verify_host', ZBX_HTTP_VERIFY_HOST_ON))
						->setChecked($data['form']['verify_host'] == ZBX_HTTP_VERIFY_HOST_ON)
				)
			])
			->addItem([
				new CLabel(_('SSL certificate file'), 'ssl_cert_file'),
				new CFormField(
					(new CTextBox('ssl_cert_file', $data['form']['ssl_cert_file'], false,
						DB::getFieldLength('connector', 'ssl_cert_file')
					))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('SSL key file'), 'ssl_key_file'),
				new CFormField(
					(new CTextBox('ssl_key_file', $data['form']['ssl_key_file'], false,
						DB::getFieldLength('connector', 'ssl_key_file')
					))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('SSL key password'), 'ssl_key_password'),
				new CFormField(
					(new CTextBox('ssl_key_password', $data['form']['ssl_key_password'], false,
						DB::getFieldLength('connector', 'ssl_key_password')
					))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->disableAutocomplete()
				)
			])
	)
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField(
			(new CTextArea('description', $data['form']['description']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('connector', 'description'))
		)
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', ZBX_CONNECTOR_STATUS_ENABLED))
				->setChecked($data['form']['status'] == ZBX_CONNECTOR_STATUS_ENABLED)
		)
	]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			connector_edit_popup.init('.json_encode([
				'connectorid' => $data['connectorid'],
				'tags' => $data['form']['tags']
			]).');
		'))->setOnDocumentReady()
	);

if ($data['connectorid'] !== null) {
	$title = _('Connector');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'connector_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'connector_edit_popup.clone('.json_encode([
				'title' => _('New connector'),
				'buttons' => [
					[
						'title' => _('Add'),
						'class' => 'js-add',
						'keepOpen' => true,
						'isSubmit' => true,
						'action' => 'connector_edit_popup.submit();'
					],
					[
						'title' => _('Cancel'),
						'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-cancel']),
						'cancel' => true,
						'action' => ''
					]
				]
			]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected connector?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'connector_edit_popup.delete();'
		]
	];
}
else {
	$title = _('New connector');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'connector_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_CONNECTOR_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('connector.edit.js.php'),
	'dialogue_class' => 'modal-popup-static'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
