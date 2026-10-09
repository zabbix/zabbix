<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('script')))->removeId())
	->setId('script-form')
	->setName('scripts')
	->addVar('scriptid', $data['form']['scriptid'])
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

$parameters_table = (new CTable())
	->setAttribute('data-field-type', 'set')
	->setAttribute('data-field-name', 'parameters')
	->setId('parameters-table')
	->setHeader([
		(new CColHeader(_('Name')))->setWidth('50%'),
		(new CColHeader(_('Value')))->setWidth('50%'),
		''
	])
	->setAttribute('style', 'width: 100%;')
	->addItem(
		(new CTag('tfoot', true))
			->addItem(
				(new CCol(
					(new CButtonLink(_('Add')))->addClass('js-parameter-add')
				))->setColSpan(2)
			)
	);

$row_template = (new CTemplateTag('script-parameter-template'))
	->addItem(
		(new CRow([
			(new CTextAreaFlexible('parameters[#{row_index}][name]', ''))
				->setMaxlength(DB::getFieldLength('script_param', 'name'))
				->setAttribute('data-error-label', _('Name'))
				->setAttribute('data-error-container', 'parameter-#{row_index}-error-container')
				->setAttribute('style', 'width: 100%;')
				->setAttribute('value', '#{name}')
				->removeId(),
			(new CTextAreaFlexible('parameters[#{row_index}][value]', ''))
				->setMaxlength(DB::getFieldLength('script_param', 'value'))
				->setAttribute('data-error-label', _('Value'))
				->setAttribute('data-error-container', 'parameter-#{row_index}-error-container')
				->setAttribute('style', 'width: 100%;')
				->setAttribute('value', '#{value}')
				->removeId(),
			(new CButtonLink(_('Remove')))->addClass('js-remove')
		]))->addClass('form_row')
	)
	->addItem(
		(new CRow())->addItem((new CCol())
			->setColSpan(3)
			->setId('parameter-#{row_index}-error-container')
			->addClass(ZBX_STYLE_ERROR_CONTAINER)
		)->addClass('error-container-row')
	);

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))
			->setAsteriskMark(),
		(new CFormField(
			(new CTextAreaFlexible('name', $data['form']['name']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('scripts', 'name'))
				->setAriaRequired()
		))
	])
	->addItem([
		new CLabel(_('Scope'), 'scope'),
		new CFormField(
			(new CRadioButtonList('scope', (int) $data['form']['scope']))
				->addValue(_('Action operation'), ZBX_SCRIPT_SCOPE_ACTION)
				->addValue(_('Manual host action'), ZBX_SCRIPT_SCOPE_HOST)
				->addValue(_('Manual event action'), ZBX_SCRIPT_SCOPE_EVENT)
				->setModern()
				->setReadonly($data['form']['actions'])
		)
	])
	->addItem([
		(new CLabel(_('Menu path'), 'menu_path'))->setId('menu-path-label'),
		(new CFormField(
			(new CTextAreaFlexible('menu_path', $data['form']['menu_path']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('scripts', 'menu_path'))
				->setAttribute('placeholder', _('<sub-menu/sub-menu/...>'))
				->setAttribute('data-field-type', 'menu-path')
		))->setId('menu-path')
	])
	->addItem([
		(new CLabel(_('Type'), 'type')),
		new CFormField(
			(new CRadioButtonList('type', (int) $data['form']['type']))
				->addValue(_('URL'), ZBX_SCRIPT_TYPE_URL)
				->addValue(_('Webhook'), ZBX_SCRIPT_TYPE_WEBHOOK)
				->addValue(_('Script'), ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT)
				->addValue(_('SSH'), ZBX_SCRIPT_TYPE_SSH)
				->addValue(_('Telnet'), ZBX_SCRIPT_TYPE_TELNET)
				->addValue(_('IPMI'), ZBX_SCRIPT_TYPE_IPMI)
				->setModern()
		)
	])
	->addItem([
		(new CLabel(_('Execute on'), 'execute_on'))->setId('execute-on-label'),
		(new CFormField([
			(new CRadioButtonList('execute_on', (int) $data['form']['execute_on']))
				->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT)
				->addValue(_('Zabbix proxy or server'), ZBX_SCRIPT_EXECUTE_ON_PROXY)
				->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER, null, null,
					!$data['form']['is_global_scripts_enabled']
				)
				->setModern()
				->setId('execute-on'),
			!$data['form']['is_global_scripts_enabled']
				? makeWarningIcon(_('Global script execution on Zabbix server is disabled by server configuration.'))
				: null
		]))->setId('execute-on')
	])
	->addItem([
		(new CLabel(_('Authentication method'), 'authentication'))->setId('auth-type-label'),
		(new CFormField(
			(new CSelect('authtype'))
				->setId('authtype')
				->setValue($data['form']['authtype'])
				->setFocusableElementId('authentication')
				->addOptions(CSelect::createOptionsFromArray([
					ITEM_AUTHTYPE_PASSWORD => _('Password'),
					ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
				]))
		))->setId('auth-type')
	])
	->addItem([
		(new CLabel(_('Username'), 'username'))
			->setId('username-label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('username', $data['form']['username'], false, DB::getFieldLength('scripts', 'username')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		))->setId('username-field')
	])
	->addItem([
		(new CLabel(_('Public key file'), 'publickey'))
			->setId('publickey-label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('publickey', $data['form']['publickey'], false, DB::getFieldLength('scripts', 'publickey')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		))->setId('publickey-field')
	])
	->addItem([
		(new CLabel(_('Private key file'), 'privatekey'))
			->setId('privatekey-label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('privatekey', $data['form']['privatekey'], false, DB::getFieldLength('scripts', 'privatekey')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		))->setId('privatekey-field')
	])
	->addItem([
		(new CLabel(_('Password'), 'password'))->setId('password-label'),
		(new CFormField(
			(new CTextBox('password', $data['form']['password'], false, DB::getFieldLength('scripts', 'password')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		))->setId('password-field')
	])
	->addItem([
		(new CLabel(_('Key passphrase'), 'passphrase'))->setId('passphrase-label'),
		(new CFormField(
			(new CTextBox('passphrase', $data['form']['passphrase'], false, DB::getFieldLength('scripts', 'password')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		))->setId('passphrase-field')
	])
	->addItem([
		(new CLabel(_('Port'), 'port'))->setId('port-label'),
		(new CFormField(
			(new CTextBox('port', $data['form']['port'], false, DB::getFieldLength('scripts', 'port')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		))->setId('port-field')
	])
	->addItem([
		(new CLabel(_('Commands'), 'command'))
			->setId('commands-label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextArea('command', $data['form']['command']))
				->addClass(ZBX_STYLE_MONOSPACE_FONT)
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('scripts', 'command'))
				->setAriaRequired()
				->disableSpellcheck()
		))->setId('commands')
	])
	->addItem([
		(new CLabel(_('Command'), 'commandipmi'))
			->setId('command-ipmi-label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('commandipmi', $data['form']['commandipmi'], false, DB::getFieldLength('scripts', 'command')))
				->addClass(ZBX_STYLE_MONOSPACE_FONT)
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('command-ipmi')
	])
	->addItem([
		(new CLabel(_('Parameters'), $parameters_table->getId()))->setId('webhook-parameters-label'),
		(new CFormField(
			(new CDiv($parameters_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		))->setId('webhook-parameters')
	])
	->addItem([
		(new CLabel(_('URL'), 'url'))
			->setId('url-label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextAreaFlexible('url', $data['form']['url']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('scripts', 'url'))
				->setAriaRequired()
		))->setId('url')
	])
	->addItem([
		(new CLabel(_('Open in a new window')))->setId('new-window-label')->setFor('new_window'),
		(new CFormField(
			(new CCheckBox('new_window', ZBX_SCRIPT_URL_NEW_WINDOW_YES))
				->setChecked($data['form']['new_window'] == ZBX_SCRIPT_URL_NEW_WINDOW_YES)
				->setUncheckedValue(ZBX_SCRIPT_URL_NEW_WINDOW_NO)
		))->setId('new-window')
	])
	->addItem([
		(new CLabel(_('Script'), 'script'))
			->setAsteriskMark()
			->setId('script-label'),
		(new CFormField(
			(new CMultilineInput('script', $data['form']['script'], [
				'title' => _('JavaScript'),
				'placeholder' => _('script'),
				'placeholder_textarea' => 'return value',
				'grow' => 'auto',
				'rows' => 0,
				'maxlength' => DB::getFieldLength('scripts', 'command')
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('js-item-script-field')
	])
	->addItem([
		(new CLabel(_('Timeout'), 'timeout'))
			->setAsteriskMark()
			->setId('timeout-label'),
		(new CFormField(
			(new CTextBox('timeout', $data['form']['timeout'], false, DB::getFieldLength('scripts', 'timeout')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		))->setId('timeout-field')
	])
	->addItem([
		(new CLabel(_('Description'), 'description')),
		new CFormField(
			(new CTextArea('description', $data['form']['description']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('scripts', 'description'))
		)
	]);

$select_usrgrpid = (new CSelect('usrgrpid'))
	->setId('user-group')
	->setValue($data['form']['usrgrpid'])
	->setFocusableElementId('usrgrpid')
	->addOption(new CSelectOption(0, _('All')))
	->addOptions(CSelect::createOptionsFromArray($data['form']['usergroups']));

$select_hgstype = (new CSelect('hgstype'))
	->setId('hgstype-select')
	->setValue($data['form']['hgstype'])
	->setFocusableElementId('hgstype')
	->addOption(new CSelectOption(0, _('All')))
	->addOption(new CSelectOption(1, _('Selected')));

$validation_rule = $data['form']['manualinput_validator_type'] == ZBX_SCRIPT_MANUALINPUT_TYPE_STRING
	? $data['form']['manualinput_validator']
	: '';

$dropdown_options = $data['form']['manualinput_validator_type'] == ZBX_SCRIPT_MANUALINPUT_TYPE_LIST
	? $data['form']['manualinput_validator']
	: '';

$form_grid
	->addItem([
		new CLabel(_('Host group'), $select_hgstype->getFocusableElementId()),
		new CFormField($select_hgstype)
	])
	->addItem(
		(new CFormField(
			(new CMultiSelect([
				'name' => 'groupid',
				'object_name' => 'hostGroup',
				'multiple' => false,
				'data' => $data['form']['hostgroup'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'host_groups',
						'srcfld1' => 'groupid',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'groupid',
						'normal_only' => '1'
					]
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('host-group-selection')
	)
	->addItem([
		(new CLabel(_('User group'), $select_usrgrpid->getFocusableElementId()))->setId('usergroup-label'),
		(new CFormField($select_usrgrpid))->setId('usergroup')
	])
	->addItem([
		(new CLabel(_('Required host permissions'), 'host_access'))->setId('host-access-label'),
		(new CFormField(
			(new CRadioButtonList('host_access', (int) $data['form']['host_access']))
				->addValue(_('Read'), PERM_READ)
				->addValue(_('Write'), PERM_READ_WRITE)
				->setModern()
				->setId('host-access')
		))->setId('host-access-field')
	])
	->addItem((new CFormFieldsetCollapsible(_('Advanced configuration')))
		->setId('advanced-configuration')
		->addItem([
			(new CLabel(_('Enable user input'), 'manualinput')),
			new CFormField(
				(new CCheckBox('manualinput'))
					->setChecked($data['form']['manualinput'] == ZBX_SCRIPT_MANUALINPUT_ENABLED)
					->setUncheckedValue(0)
			)
		])
		->addItem([
			(new CLabel(_('Input prompt'), 'manualinput_prompt')),
			new CFormField([
				(new CTextAreaFlexible('manualinput_prompt', $data['form']['manualinput_prompt']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setErrorContainer('manualinput-prompt-error-container')
					->setMaxlength(DB::getFieldLength('scripts', 'manualinput_prompt')),
				NBSP(),
				(new CButton('test_user_input', _('Test user input')))->addClass(ZBX_STYLE_BTN_GREY),
				(new CDiv())->setId('manualinput-prompt-error-container')
			])
		])
		->addItem([
			(new CLabel(_('Input type'), 'manualinput_validator_type')),
			new CFormField(
				(new CRadioButtonList('manualinput_validator_type', (int) $data['form']['manualinput_validator_type']))
					->addValue(_('String'), ZBX_SCRIPT_MANUALINPUT_TYPE_STRING)
					->addValue(_('Dropdown'), ZBX_SCRIPT_MANUALINPUT_TYPE_LIST)
					->setModern()
			)
		])
		->addItem([
			new CLabel(_('Default input string'), 'manualinput_default_value'),
			new CFormField([
				(new CTextAreaFlexible('manualinput_default_value', $data['form']['manualinput_default_value']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setMaxlength(DB::getFieldLength('scripts', 'manualinput_default_value'))
			])
		])
		->addItem([
			new CLabel(_('Dropdown options'), 'dropdown_options'),
			new CFormField([
				(new CTextAreaFlexible('dropdown_options', $dropdown_options))
					->setAttribute('placeholder', _('comma-separated list'))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setMaxlength(DB::getFieldLength('scripts', 'manualinput_validator'))
			])
		])
		->addItem([
			new CLabel(_('Input validation rule'), 'manualinput_validator'),
			new CFormField([
				(new CTextAreaFlexible('manualinput_validator', $validation_rule))
					->setAttribute('placeholder', _('regular expression'))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setMaxlength(DB::getFieldLength('scripts', 'manualinput_validator'))
			])
		])
		->addItem([
			(new CLabel(_('Enable confirmation'), 'enable_confirmation')),
			new CFormField(
				(new CCheckBox('enable_confirmation'))
					->setChecked($data['form']['enable_confirmation'])
					->setUncheckedValue(0)
			)
		])
		->addItem([
			(new CLabel(_('Confirmation text'), 'confirmation')),
			new CFormField([
				(new CTextAreaFlexible('confirmation', $data['form']['confirmation']))
					->setAttribute('data-notrim', '')
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setErrorContainer('confirmation-error-container')
					->setMaxlength(DB::getFieldLength('scripts', 'confirmation')),
				NBSP(),
				(new CButton('test_confirmation', _('Test confirmation')))->addClass(ZBX_STYLE_BTN_GREY),
				(new CDiv())->setId('confirmation-error-container')
			])
		])
	);

if ($data['form']['scriptid'] === null) {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false
		],
		[
			'title' => _('Delete'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false
		]
	];
}

$form
	->addItem($form_grid)
	->addItem($row_template)
	->addStyle('display: none;');

$output = [
	'header' => $data['form']['scriptid'] === null ? _('New script') : _('Script'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::ALERTS_SCRIPT_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('administration.script.edit.js.php').
		'script_edit_popup.init('.json_encode([
			'script' => $data['form'],
			'rules' => $data['js_validation_rules'],
			'clone_rules' => $data['js_clone_validation_rules']
		]).');',
	'dialogue_class' => 'modal-popup-large'
];

if ($data['form']['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
