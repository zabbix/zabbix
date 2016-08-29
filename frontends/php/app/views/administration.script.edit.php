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


$this->addJsFile('multiselect.js');
$this->includeJSfile('app/views/administration.script.edit.js.php');

$widget = (new CWidget())->setTitle(_('Scripts'));

$scriptForm = (new CForm())
	->setId('scriptForm')
	->setName('scripts')
	->addVar('form', 1)
	->addVar('scriptid', $data['scriptid']);

$scriptFormList = (new CFormList())
	->addRow(_('Name'),
		(new CTextBox('name', $data['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAttribute('placeholder', _('<Sub-menu/Sub-menu.../>Script'))
	)
	->addRow(_('Type'),
		(new CRadioButtonList('type', (int) $data['type']))
			->addValue(_('IPMI'), ZBX_SCRIPT_TYPE_IPMI)
			->addValue(_('Script'), ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT)
			->setModern(true)
	)
	->addRow(_('Execute on'),
		(new CRadioButtonList('execute_on', (int) $data['execute_on']))
			->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT)
			->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER)
			->setModern(true)
	)
	->addRow(_('Commands'),
		(new CTextArea('command', $data['command']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxLength(255)
	)
	->addRow(_('Command'),
		(new CTextBox('commandipmi', $data['commandipmi']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

$user_groups = [0 => _('All')];
foreach ($data['usergroups'] as $user_group) {
	$user_groups[$user_group['usrgrpid']] = $user_group['name'];
}
$scriptFormList
	->addRow(_('User group'),
		new CComboBox('usrgrpid', $data['usrgrpid'], null, $user_groups))
	->addRow(_('Host group'),
		new CComboBox('hgstype', $data['hgstype'], null, [
			0 => _('All'),
			1 => _('Selected')
		])
	)
	->addRow(null, (new CMultiSelect([
		'name' => 'groupid',
		'selectedLimit' => 1,
		'objectName' => 'hostGroup',
		'data' => $data['hostgroup'],
		'popup' => [
			'parameters' => 'srctbl=host_groups&dstfrm='.$scriptForm->getName().'&dstfld1=groupid&srcfld1=groupid'
		]]))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH), 'hostGroupSelection')
	->addRow(_('Required host permissions'),
		(new CRadioButtonList('host_access', (int) $data['host_access']))
			->addValue(_('Read'), PERM_READ)
			->addValue(_('Write'), PERM_READ_WRITE)
			->setModern(true)
	)
	->addRow(new CLabel(_('Enable confirmation'), 'enable_confirmation'),
		(new CCheckBox('enable_confirmation'))->setChecked($data['enable_confirmation'] == 1)
	);

$confirmationLabel = new CLabel(_('Confirmation text'), 'confirmation');
$scriptFormList->addRow($confirmationLabel, [
	(new CTextBox('confirmation', $data['confirmation']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	SPACE,
	(new CButton('testConfirmation', _('Test confirmation')))->addClass(ZBX_STYLE_BTN_GREY)
]);

$scriptView = (new CTabView())->addTab('scripts', _('Script'), $scriptFormList);

// footer
$cancelButton = (new CRedirectButton(_('Cancel'), 'zabbix.php?action=script.list'))->setId('cancel');

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
		'zabbix.php?action=script.delete&sid='.$data['sid'].'&scriptids[]='.$data['scriptid'],
		_('Delete script?')
	))
		->setId('delete');

	$scriptView->setFooter(makeFormFooter(
		$updateButton,
		[
			$cloneButton,
			$deleteButton,
			$cancelButton
		]
	));
}

$scriptForm->addItem($scriptView);

$widget->addItem($scriptForm)->show();
