<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
// include JS + templates
	include('include/templates/script.js.php');
	include('include/templates/scriptConfirm.js.php');
?>
<?php
	$scriptTab = new CFormList('scriptsTab');
	$frmScr = new CForm();
	$frmScr->setName('scripts');

	$frmScr->addVar('form', $data['form']);
	$frmScr->addVar('form_refresh', $data['form_refresh'] + 1);


	if($data['scriptid']) $frmScr->addVar('scriptid', $data['scriptid']);


// NAME
	$nameTB = new CTextBox('name', $data['name']);
	$nameTB->setAttribute('maxlength', 255);
	$nameTB->addStyle('width: 50em');
	$scriptTab->addRow(_('Name'), $nameTB);

// TYPE
	$typeCB = new CComboBox('type', $data['type']);
	$typeCB->addItem(ZBX_SCRIPT_TYPE_IPMI, _('IPMI'));
	$typeCB->addItem(ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, _('Script'));
	$scriptTab->addRow(_('Type'), $typeCB);

// EXECUTE ON
	$typeRB = new CRadioButton('execute_on', $data['execute_on']);
	$typeRB->makeVertical();
	$typeRB->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT);
	$typeRB->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER);
	$scriptTab->addRow(_('Execute on'), new CDiv($typeRB, 'objectgroup inlineblock border_dotted ui-corner-all'), ($data['type'] == ZBX_SCRIPT_TYPE_IPMI));

// COMMAND
	$commandTA = new CTextArea('command', $data['command']);
	$commandTA->addStyle('width: 50em; padding: 0;');
	$scriptTab->addRow(_('Commands'), $commandTA, $data['type'] == ZBX_SCRIPT_TYPE_IPMI);

// COMMAND IPMI
	$commandIpmiTB = new CTextBox('commandipmi', $data['commandipmi']);
	$commandIpmiTB->addStyle('width: 50em;');
	$scriptTab->addRow(_('Command'), $commandIpmiTB, $data['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT);

// DESCRIPTION
	$descriptionTA = new CTextArea('description', $data['description']);
	$descriptionTA->addStyle('width: 50em; padding: 0;');
	$scriptTab->addRow(_('Description'), $descriptionTA);

// USER GROUPS
	$usr_groups = new CCombobox('usrgrpid', $data['usrgrpid']);
	$usr_groups->addItem(0, _('All'));
	$usrgrps = API::UserGroup()->get(array(
		'output' => API_OUTPUT_EXTEND,
	));
	order_result($usrgrps, 'name');
	foreach($usrgrps as $ugnum => $usr_group){
		$usr_groups->addItem($usr_group['usrgrpid'], $usr_group['name']);
	}
	$scriptTab->addRow(_('User groups'), $usr_groups);

// HOST GROUPS
	$host_groups = new CCombobox('groupid', $data['groupid']);
	$host_groups->addItem(0, _('All'));
	$groups = API::HostGroup()->get(array(
		'output' => API_OUTPUT_EXTEND,
	));
	order_result($groups, 'name');
	foreach($groups as $gnum => $group){
		$host_groups->addItem($group['groupid'], $group['name']);
	}
	$scriptTab->addRow(_('Host groups'), $host_groups);

// PERMISSIONS
	$select_acc = new CCombobox('access', $data['access']);
	$select_acc->addItem(PERM_READ_ONLY, _('Read'));
	$select_acc->addItem(PERM_READ_WRITE, _('Write'));
	$scriptTab->addRow(_('Required host permissions'), $select_acc);

// CONFIRMATION
	$enableQuestCB = new CCheckBox('enableConfirmation', $data['enableConfirmation']);
	$scriptTab->addRow(new CLabel(_('Enable confirmation'), 'enableConfirmation'), array($enableQuestCB, SPACE));

	$confirmationTB = new CTextBox('confirmation', $data['confirmation']);
	$confirmationTB->addStyle('width: 50em;');
	$confirmationTB->setAttribute('maxlength', 255);

	$testLink = new CButton('testConfirmation', _('Test confirmation'), null, 'link_menu');

	$confirmationLabel = new CLabel(_('Confirmation text'), 'confirmation');
	$confirmationLabel->setAttribute('id', 'confirmationLabel');
	$scriptTab->addRow($confirmationLabel, array($confirmationTB, SPACE, $testLink));

	$scriptView = new CTabView();
	$scriptView->addTab('scripts', _('Script'), $scriptTab);
	$frmScr->addItem($scriptView);


// Footer
	$main = array(new CSubmit('save', _('Save')));
	$others = array();
	if(isset($_REQUEST['scriptid'])){
		$others[] = new CButton('clone', _('Clone'));
		$others[] = new CButtonDelete(_('Delete script?'), url_param('form').url_param('scriptid'));
	}
	$others[] = new CButtonCancel();

	$footer = makeFormFooter($main, $others);
	$frmScr->addItem($footer);


	return $frmScr;
?>
