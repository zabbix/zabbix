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

$this->includeJsFile('administration.script.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Scripts'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_SCRIPT_EDIT));

$row_template = (new CTag('script', true))
	->setId('parameters-row')
	->setAttribute('type', 'text/x-jquery-tmpl')
	->addItem(
		(new CRow([
			(new CTextBox('parameters[name][]', '', false, DB::getFieldLength('script_param', 'name')))
				->setAttribute('style', 'width: 100%;')
				->removeId(),
			(new CTextBox('parameters[value][]', '', false, DB::getFieldLength('script_param', 'value')))
				->setAttribute('style', 'width: 100%;')
				->removeId(),
			(new CButton('', _('Remove')))
				->removeId()
				->onClick('$(this).closest("tr").remove()')
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row')
	);

$widget->addItem($row_template);

$form = (new CForm())
	->setId('script-form')
	->setName('scripts')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', 1)
	->addVar('scriptid', $data['scriptid']);

$parameters_table = (new CTable())
	->setId('parameters-table')
	->setHeader([
		(new CColHeader(_('Name')))->setWidth('50%'),
		(new CColHeader(_('Value')))->setWidth('50%'),
		_('Action')
	])
	->setAttribute('style', 'width: 100%;');

foreach ($data['parameters'] as $parameter) {
	$parameters_table->addRow([
		(new CTextBox('parameters[name][]', $parameter['name'], false, DB::getFieldLength('script_param', 'name')))
			->setAttribute('style', 'width: 100%;')
			->removeId(),
		(new CTextBox('parameters[value][]', $parameter['value'], false, DB::getFieldLength('script_param', 'value')))
			->setAttribute('style', 'width: 100%;')
			->removeId(),
		(new CButton('', _('Remove')))
			->removeId()
			->onClick('$(this).closest("tr").remove()')
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
	], 'form_row');
}

$parameters_table->addRow([
	(new CButton('parameter_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
]);

$form_list = (new CFormList())
	->addRow((new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['name'], false, DB::getFieldLength('scripts', 'name')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	)
	->addRow(new CLabel(_('Scope'), 'scope'),
		(new CRadioButtonList('scope', (int) $data['scope']))
			->addValue(_('Action operation'), ZBX_SCRIPT_SCOPE_ACTION)
			->addValue(_('Manual host action'), ZBX_SCRIPT_SCOPE_HOST)
			->addValue(_('Manual event action'), ZBX_SCRIPT_SCOPE_EVENT)
			->setModern(true)
			->setEnabled(!$data['actions'])
	)
	->addRow(new CLabel(_('Menu path'), 'menu_path'),
		(new CTextBox('menu_path', $data['menu_path'], false, DB::getFieldLength('scripts', 'menu_path')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', _('<sub-menu/sub-menu/...>'))
			->setId('menu-path')
	)
	->addRow((new CLabel(_('Type'), 'type')),
		(new CRadioButtonList('type', (int) $data['type']))
			->addValue(_('Webhook'), ZBX_SCRIPT_TYPE_WEBHOOK)
			->addValue(_('Script'), ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT)
			->addValue(_('SSH'), ZBX_SCRIPT_TYPE_SSH)
			->addValue(_('Telnet'), ZBX_SCRIPT_TYPE_TELNET)
			->addValue(_('IPMI'), ZBX_SCRIPT_TYPE_IPMI)
			->setModern(true)
	)
	->addRow(new CLabel(_('Execute on'), 'execute_on'),
		(new CRadioButtonList('execute_on', (int) $data['execute_on']))
			->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT)
			->addValue(_('Zabbix server (proxy)'), ZBX_SCRIPT_EXECUTE_ON_PROXY)
			->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER)
			->setModern(true)
			->setId('execute-on')
	)
	->addRow(new CLabel(_('Authentication method'), 'authtype'),
		(new CSelect('authtype'))
			->setId('authtype')
			->setValue($data['authtype'])
			->setFocusableElementId('authtype')
			->addOptions(CSelect::createOptionsFromArray([
				ITEM_AUTHTYPE_PASSWORD => _('Password'),
				ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
			]))
	)
	->addRow((new CLabel(_('Username'), 'username'))->setAsteriskMark(),
		(new CTextBox('username', $data['username'], false, DB::getFieldLength('scripts', 'username')))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Public key file'), 'publickey'))->setAsteriskMark(),
		(new CTextBox('publickey', $data['publickey'], false, DB::getFieldLength('scripts', 'publickey')))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Private key file'), 'privatekey'))->setAsteriskMark(),
		(new CTextBox('privatekey', $data['privatekey'], false, DB::getFieldLength('scripts', 'privatekey')))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	)
	->addRow(new CLabel(_('Password'), 'password'),
		(new CTextBox('password', $data['password'], false, DB::getFieldLength('scripts', 'password')))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	->addRow(new CLabel(_('Key passphrase'), 'passphrase'),
		(new CTextBox('passphrase', $data['passphrase'], false, DB::getFieldLength('scripts', 'password')))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	->addRow(new CLabel(_('Port'), 'port'),
		(new CTextBox('port', $data['port'], false, DB::getFieldLength('scripts', 'port')))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	->addRow((new CLabel(_('Commands'), 'command'))->setAsteriskMark(),
		(new CTextArea('command', $data['command']))
			->addClass(ZBX_STYLE_MONOSPACE_FONT)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxLength(DB::getFieldLength('scripts', 'command'))
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Command'), 'commandipmi'))->setAsteriskMark(),
		(new CTextBox('commandipmi', $data['commandipmi'], false, DB::getFieldLength('scripts', 'command')))
			->addClass(ZBX_STYLE_MONOSPACE_FONT)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(new CLabel(_('Parameters'), $parameters_table->getId()),
		(new CDiv($parameters_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
		'row-webhook-parameters'
	)
	->addRow((new CLabel(_('Script'), 'script'))->setAsteriskMark(),
		(new CMultilineInput('script', $data['script'], [
			'title' => _('JavaScript'),
			'placeholder' => _('script'),
			'placeholder_textarea' => 'return value',
			'grow' => 'auto',
			'rows' => 0,
			'maxlength' => DB::getFieldLength('scripts', 'command')
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Timeout'), 'timeout'))->setAsteriskMark(),
		(new CTextBox('timeout', $data['timeout'], false, DB::getFieldLength('scripts', 'timeout')))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	)
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('scripts', 'description'))
	);

$select_usrgrpid = (new CSelect('usrgrpid'))
	->setId('user-group')
	->setValue($data['usrgrpid'])
	->setFocusableElementId('usrgrpid')
	->addOption(new CSelectOption(0, _('All')))
	->addOptions(CSelect::createOptionsFromArray($data['usergroups']));

$select_hgstype = (new CSelect('hgstype'))
	->setId('hgstype-select')
	->setValue($data['hgstype'])
	->setFocusableElementId('hgstype')
	->addOption(new CSelectOption(0, _('All')))
	->addOption(new CSelectOption(1, _('Selected')));

$form_list
	->addRow(new CLabel(_('Host group'), $select_hgstype->getFocusableElementId()), $select_hgstype)
	->addRow(null,
		(new CMultiSelect([
			'name' => 'groupid',
			'object_name' => 'hostGroup',
			'multiple' => false,
			'data' => $data['hostgroup'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'groupid'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'host-group-selection'
	)
	->addRow(new CLabel(_('User group'), $select_usrgrpid->getFocusableElementId()), $select_usrgrpid)
	->addRow((new CLabel(_('Required host permissions'), 'host_access')),
		(new CRadioButtonList('host_access', (int) $data['host_access']))
			->addValue(_('Read'), PERM_READ)
			->addValue(_('Write'), PERM_READ_WRITE)
			->setModern(true)
			->setId('host-access')
	)
	->addRow(_('Enable confirmation'),
		(new CCheckBox('enable_confirmation'))
			->setChecked($data['enable_confirmation'])
			->setId('enable-confirmation')
	)
	->addRow(new CLabel(_('Confirmation text'), 'confirmation'), [
		(new CTextBox('confirmation', $data['confirmation'], false, DB::getFieldLength('scripts', 'confirmation')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		SPACE,
		(new CButton('testConfirmation', _('Test confirmation')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->setId('test-confirmation')
	]
);

$scriptView = (new CTabView())->addTab('scripts', _('Script'), $form_list);

// footer
$cancelButton = (new CRedirectButton(_('Cancel'),
	(new CUrl('zabbix.php'))
		->setArgument('action', 'script.list')
		->setArgument('page', CPagerHelper::loadPage('script.list', null))
))->setId('cancel');

if ($data['scriptid'] == 0) {
	$addButton = (new CSubmitButton(_('Add'), 'action', 'script.create'))->setId('add');

	$scriptView->setFooter(makeFormFooter(
		$addButton,
		[$cancelButton]
	));
}
else {
	$updateButton = (new CSubmitButton(_('Update'), 'action', 'script.update'))->setId('update');
	$cloneButton = (new CSimpleButton(_('Clone')))->setId('clone');
	$deleteButton = (new CRedirectButton(_('Delete'),
		(new CUrl('zabbix.php'))
			->setArgument('action', 'script.delete')
			->setArgument('scriptids[]', $data['scriptid'])
			->setArgumentSID(),
		_('Delete script?')
	))->setId('delete');

	$scriptView->setFooter(makeFormFooter(
		$updateButton,
		[
			$cloneButton,
			$deleteButton,
			$cancelButton
		]
	));
}

$form->addItem($scriptView);

$widget->addItem($form)->show();
