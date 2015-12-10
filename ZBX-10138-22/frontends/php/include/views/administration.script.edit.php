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


include('include/views/js/administration.script.edit.js.php');

$scriptsWidget = new CWidget();
$scriptsWidget->addPageHeader(_('CONFIGURATION OF SCRIPTS'));

$scriptForm = new CForm();
$scriptForm->setName('scripts');
$scriptForm->addVar('form', $this->get('form'));
$scriptForm->addVar('form_refresh', $this->get('form_refresh') + 1);

if ($this->get('scriptid')) {
	$scriptForm->addVar('scriptid', $this->get('scriptid'));
}

$scriptFormList = new CFormList('scriptsTab');

// name
$nameTextBox = new CTextBox('name', $this->get('name'), ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->attr('autofocus', 'autofocus');
$nameTextBox->attr('placeholder', _('<Sub-menu/Sub-menu.../>Script'));
$scriptFormList->addRow(_('Name'), $nameTextBox);

// type
$typeComboBox = new CComboBox('type', $this->get('type'));
$typeComboBox->addItem(ZBX_SCRIPT_TYPE_IPMI, _('IPMI'));
$typeComboBox->addItem(ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, _('Script'));
$scriptFormList->addRow(_('Type'), $typeComboBox);

// execute on
$typeRadioButton = new CRadioButtonList('execute_on', $this->get('execute_on'));
$typeRadioButton->makeVertical();
$typeRadioButton->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT);
$typeRadioButton->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER);
$scriptFormList->addRow(
	_('Execute on'),
	new CDiv($typeRadioButton, 'objectgroup inlineblock border_dotted ui-corner-all'),
	($this->get('type') == ZBX_SCRIPT_TYPE_IPMI)
);
$scriptFormList->addRow(
	_('Commands'),
	new CTextArea('command', $this->get('command')),
	($this->get('type') == ZBX_SCRIPT_TYPE_IPMI)
);
$scriptFormList->addRow(
	_('Command'),
	new CTextBox('commandipmi', $this->get('commandipmi'), ZBX_TEXTBOX_STANDARD_SIZE),
	($this->get('type') == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT)
);
$scriptFormList->addRow(_('Description'), new CTextArea('description', $this->get('description')));

// user groups
$userGroups = new CCombobox('usrgrpid', $this->get('usrgrpid'));
$userGroups->addItem(0, _('All'));
foreach ($this->getArray('usergroups') as $userGroup){
	$userGroups->addItem($userGroup['usrgrpid'], $userGroup['name']);
}
$scriptFormList->addRow(_('User group'), $userGroups);

// host groups
$hostGroups = new CCombobox('hgstype', $this->get('hgstype'));
$hostGroups->addItem(0, _('All'));
$hostGroups->addItem(1, _('Selected'));
$scriptFormList->addRow(_('Host group'), $hostGroups);
$scriptFormList->addRow(null, new CMultiSelect(array(
	'name' => 'groupid',
	'selectedLimit' => 1,
	'objectName' => 'hostGroup',
	'data' => $this->get('hostGroup'),
	'popup' => array(
		'parameters' => 'srctbl=host_groups&dstfrm='.$scriptForm->getName().'&dstfld1=groupid&srcfld1=groupid',
		'width' => 450,
		'height' => 450
	)
)), null, 'hostGroupSelection');

// access
$accessComboBox = new CCombobox('access', $this->get('access'));
$accessComboBox->addItem(PERM_READ, _('Read'));
$accessComboBox->addItem(PERM_READ_WRITE, _('Write'));
$scriptFormList->addRow(_('Required host permissions'), $accessComboBox);
$scriptFormList->addRow(new CLabel(_('Enable confirmation'), 'enableConfirmation'),
	new CCheckBox('enableConfirmation', $this->get('enableConfirmation')));

$confirmationLabel = new CLabel(_('Confirmation text'), 'confirmation');
$confirmationLabel->setAttribute('id', 'confirmationLabel');
$scriptFormList->addRow($confirmationLabel, array(
	new CTextBox('confirmation', $this->get('confirmation'), ZBX_TEXTBOX_STANDARD_SIZE),
	SPACE,
	new CButton('testConfirmation', _('Test confirmation'), null, 'link_menu')
));

$scriptView = new CTabView();
$scriptView->addTab('scripts', _('Script'), $scriptFormList);
$scriptForm->addItem($scriptView);

// footer
$others = array();
if (isset($_REQUEST['scriptid'])) {
	$others[] = new CButton('clone', _('Clone'));
	$others[] = new CButtonDelete(_('Delete script?'), url_param('form').url_param('scriptid'));
}
$others[] = new CButtonCancel();
$scriptForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), $others));
$scriptsWidget->addItem($scriptForm);

return $scriptsWidget;
