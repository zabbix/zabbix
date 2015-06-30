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
?>
<?php

// include js + templates
include('include/views/js/administration.script.edit.js.php');
include('include/views/js/general.script.confirm.js.php');

$scripts_wdgt = new CWidget();
$scripts_wdgt->addPageHeader(_('CONFIGURATION OF SCRIPTS'));

$scriptTab = new CFormList('scriptsTab');
$frmScr = new CForm();
$frmScr->setName('scripts');

$frmScr->addVar('form', $this->get('form'));
$frmScr->addVar('form_refresh', $this->get('form_refresh') + 1);

if ($this->get('scriptid')) {
	$frmScr->addVar('scriptid', $this->get('scriptid'));
}

// name
$nameTB = new CTextBox('name', $this->get('name'));
$nameTB->setAttribute('maxlength', 255);
$nameTB->addStyle('width: 50em;');
$scriptTab->addRow(_('Name'), $nameTB);

// type
$typeCB = new CComboBox('type', $this->get('type'));
$typeCB->addItem(ZBX_SCRIPT_TYPE_IPMI, _('IPMI'));
$typeCB->addItem(ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, _('Script'));
$scriptTab->addRow(_('Type'), $typeCB);

// execute on
$typeRB = new CRadioButtonList('execute_on', $this->get('execute_on'));
$typeRB->makeVertical();
$typeRB->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT);
$typeRB->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER);
$scriptTab->addRow(_('Execute on'), new CDiv($typeRB, 'objectgroup inlineblock border_dotted ui-corner-all'), $data['type'] == ZBX_SCRIPT_TYPE_IPMI);

// command
$commandTA = new CTextArea('command', $this->get('command'));
$commandTA->addStyle('width: 50em; padding: 0;');
$scriptTab->addRow(_('Commands'), $commandTA, $this->get('type') == ZBX_SCRIPT_TYPE_IPMI);

// command ipmi
$commandIpmiTB = new CTextBox('commandipmi', $this->get('commandipmi'));
$commandIpmiTB->addStyle('width: 50em;');
$scriptTab->addRow(_('Command'), $commandIpmiTB, $this->get('type') == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT);

// description
$descriptionTA = new CTextArea('description', $this->get('description'));
$descriptionTA->addStyle('width: 50em; padding: 0;');
$scriptTab->addRow(_('Description'), $descriptionTA);

// user groups
$usr_groups = new CCombobox('usrgrpid', $this->get('usrgrpid'));
$usr_groups->addItem(0, _('All'));
$usergroups = $this->getArray('usergroups');
foreach($usergroups as $ugnum => $usr_group){
	$usr_groups->addItem($usr_group['usrgrpid'], $usr_group['name']);
}
$scriptTab->addRow(_('User groups'), $usr_groups);

// host groups
$host_groups = new CCombobox('groupid', $this->get('groupid'));
$host_groups->addItem(0, _('All'));
$groups = $this->getArray('groups');
foreach($groups as $gnum => $group){
	$host_groups->addItem($group['groupid'], $group['name']);
}
$scriptTab->addRow(_('Host groups'), $host_groups);

// permissions
$select_acc = new CCombobox('access', $this->get('access'));
$select_acc->addItem(PERM_READ_ONLY, _('Read'));
$select_acc->addItem(PERM_READ_WRITE, _('Write'));
$scriptTab->addRow(_('Required host permissions'), $select_acc);

// confirmation
$enableQuestCB = new CCheckBox('enableConfirmation', $this->get('enableConfirmation'));
$scriptTab->addRow(new CLabel(_('Enable confirmation'), 'enableConfirmation'), array($enableQuestCB, SPACE));

$confirmationTB = new CTextBox('confirmation', $this->get('confirmation'));
$confirmationTB->addStyle('width: 50em;');
$confirmationTB->setAttribute('maxlength', 255);

$testLink = new CButton('testConfirmation', _('Test confirmation'), null, 'link_menu');

$confirmationLabel = new CLabel(_('Confirmation text'), 'confirmation');
$confirmationLabel->setAttribute('id', 'confirmationLabel');
$scriptTab->addRow($confirmationLabel, array($confirmationTB, SPACE, $testLink));

$scriptView = new CTabView();
$scriptView->addTab('scripts', _('Script'), $scriptTab);
$frmScr->addItem($scriptView);

// footer
$main = array(new CSubmit('save', _('Save')));
$others = array();
if(isset($_REQUEST['scriptid'])){
	$others[] = new CButton('clone', _('Clone'));
	$others[] = new CButtonDelete(_('Delete script?'), url_param('form').url_param('scriptid'));
}
$others[] = new CButtonCancel();

$footer = makeFormFooter($main, $others);
$frmScr->addItem($footer);

$scripts_wdgt->addItem($frmScr);

return $scripts_wdgt;

?>
