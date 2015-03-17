<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


$this->addJSfile('js/multiselect.js');
$this->includeJSfile('app/views/administration.script.edit.js.php');

$scriptsWidget = new CWidget();
$scriptsWidget->addPageHeader(_('CONFIGURATION OF SCRIPTS'));

$scriptForm = new CForm();
$scriptForm->setAttribute('id', 'scriptForm');
$scriptForm->addVar('form', 1);
$scriptForm->addVar('scriptid', $data['scriptid']);

$scriptFormList = new CFormList('scriptsTab');

// name
$nameTextBox = new CTextBox('name', $data['name'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->attr('autofocus', 'autofocus');
$nameTextBox->attr('placeholder', _('<Sub-menu/Sub-menu.../>Script'));
$scriptFormList->addRow(_('Name'), $nameTextBox);

// type
$typeComboBox = new CComboBox('type', $data['type']);
$typeComboBox->addItem(ZBX_SCRIPT_TYPE_IPMI, _('IPMI'));
$typeComboBox->addItem(ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, _('Script'));
$scriptFormList->addRow(_('Type'), $typeComboBox);

// execute on
$typeRadioButton = new CRadioButtonList('execute_on', $data['execute_on']);
$typeRadioButton->makeVertical();
$typeRadioButton->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT);
$typeRadioButton->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER);
$scriptFormList->addRow(
	_('Execute on'),
	new CDiv($typeRadioButton, 'objectgroup inlineblock border_dotted ui-corner-all'),
	($data['type'] == ZBX_SCRIPT_TYPE_IPMI)
);
$scriptFormList->addRow(
	_('Commands'),
	new CTextArea('command', $data['command']),
	($data['type'] == ZBX_SCRIPT_TYPE_IPMI)
);
$scriptFormList->addRow(
	_('Command'),
	new CTextBox('commandipmi', $data['commandipmi'], ZBX_TEXTBOX_STANDARD_SIZE),
	($data['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT)
);
$scriptFormList->addRow(_('Description'), new CTextArea('description', $data['description']));

// user groups
$userGroups = new CComboBox('usrgrpid', $data['usrgrpid']);
$userGroups->addItem(0, _('All'));
foreach ($data['usergroups'] as $userGroup){
	$userGroups->addItem($userGroup['usrgrpid'], $userGroup['name']);
}
$scriptFormList->addRow(_('User group'), $userGroups);

// host groups
$hostGroups = new CComboBox('hgstype', $data['hgstype']);
$hostGroups->addItem(0, _('All'));
$hostGroups->addItem(1, _('Selected'));
$scriptFormList->addRow(_('Host group'), $hostGroups);
$scriptFormList->addRow(null, new CMultiSelect(array(
	'name' => 'groupid',
	'selectedLimit' => 1,
	'objectName' => 'hostGroup',
	'data' => $data['hostgroup'],
	'popup' => array(
		'parameters' => 'srctbl=host_groups&dstfrm='.$scriptForm->getName().'&dstfld1=groupid&srcfld1=groupid',
		'width' => 450,
		'height' => 450
	)
)), null, 'hostGroupSelection');

// access
$accessComboBox = new CComboBox('host_access', $data['host_access']);
$accessComboBox->addItem(PERM_READ, _('Read'));
$accessComboBox->addItem(PERM_READ_WRITE, _('Write'));
$scriptFormList->addRow(_('Required host permissions'), $accessComboBox);
$scriptFormList->addRow(new CLabel(_('Enable confirmation'), 'enable_confirmation'),
	new CCheckBox('enable_confirmation', $data['enable_confirmation']));

$confirmationLabel = new CLabel(_('Confirmation text'), 'confirmation');
$scriptFormList->addRow($confirmationLabel, array(
	new CTextBox('confirmation', $data['confirmation'], ZBX_TEXTBOX_STANDARD_SIZE),
	SPACE,
	new CButton('testConfirmation', _('Test confirmation'), null, 'link_menu')
));

$scriptView = new CTabView();
$scriptView->addTab('scripts', _('Script'), $scriptFormList);
$scriptForm->addItem($scriptView);

// footer
$cancelButton = new CRedirectButton(_('Cancel'), 'zabbix.php?action=script.list');
$cancelButton->setAttribute('id', 'cancel');

if ($data['scriptid'] == 0) {
	$addButton = new CSubmitButton(_('Add'), 'action', 'script.create');
	$addButton->setAttribute('id', 'add');

	$scriptForm->addItem(makeFormFooter(
		$addButton,
		array($cancelButton)
	));
}
else {
	$updateButton = new CSubmitButton(_('Update'), 'action', 'script.update');
	$updateButton->setAttribute('id', 'update');
	$cloneButton = new CSimpleButton(_('Clone'));
	$cloneButton->setAttribute('id', 'clone');
	$deleteButton = new CRedirectButton(_('Delete'),
		'zabbix.php?action=script.delete&sid='.$data['sid'].'&scriptids[]='.$data['scriptid'],
		_('Delete script?')
	);
	$deleteButton->setAttribute('id', 'delete');

	$scriptForm->addItem(makeFormFooter(
		$updateButton,
		array(
			$cloneButton,
			$deleteButton,
			$cancelButton
		)
	));
}

$scriptsWidget->addItem($scriptForm);

$scriptsWidget->show();
