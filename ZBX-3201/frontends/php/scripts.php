<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
include_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/scripts.inc.php');
require_once('include/users.inc.php');

$page['title'] = 'S_SCRIPTS';
$page['file'] = 'scripts.php';
$page['hist_arg'] = array('scriptid', 'form');

include_once('include/page_header.php');

?>
<?php

//---------------------------------- CHECKS ------------------------------------
//		VAR							TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields=array(
		'scriptid'=>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	null),
		'scripts'=>				array(T_ZBX_INT, O_OPT,	P_SYS,			DB_ID,	null),
// Actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// form
		'action'=>				array(T_ZBX_INT, O_OPT,  P_ACT, 		IN('0,1'),	null),
		'save'=>				array(T_ZBX_STR, O_OPT,	 P_SYS|P_ACT,	NULL,		null),
		'delete'=>				array(T_ZBX_STR, O_OPT,  P_ACT, 		null,	null),
// form
		'name'=>				array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	'isset({save})'),
		'command'=>				array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	'isset({save})'),
		'access'=>				array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1,2,3'),	'isset({save})'),
		'groupid'=>				array(T_ZBX_INT, O_OPT,	 P_SYS,			DB_ID,		'isset({save})'),
		'usrgrpid'=>			array(T_ZBX_INT, O_OPT,  P_SYS,			DB_ID,		'isset({save})'),

		'form'=>				array(T_ZBX_STR, O_OPT,  NULL,		  	NULL,		null),
		'form_refresh'=>		array(T_ZBX_INT, O_OPT,	 NULL,			NULL,		null),
);

check_fields($fields);

$_REQUEST['go'] = get_request('go','none');

validate_sort_and_sortorder('name',ZBX_SORT_UP);

?>
<?php
	if(isset($_REQUEST['action'])){

		if(isset($_REQUEST['save'])){

			$cond = (isset($_REQUEST['scriptid']))?(' AND scriptid<>'.$_REQUEST['scriptid']):('');
			$scripts = DBfetch(DBselect('SELECT count(scriptid) as cnt FROM scripts WHERE name='.zbx_dbstr($_REQUEST['name']).$cond.' and '.DBin_node('scriptid', get_current_nodeid(false)),1));

			if($scripts && $scripts['cnt']>0){
				error(S_SCRIPT.SPACE.'['.htmlspecialchars($_REQUEST['name']).']'.SPACE.S_ALREADY_EXISTS_SMALL);
				show_messages(null,S_ERROR,S_CANNOT_ADD_SCRIPT);
			}
			else{

				if(isset($_REQUEST['scriptid'])){
					$result = update_script($_REQUEST['scriptid'],$_REQUEST['name'],$_REQUEST['command'],$_REQUEST['usrgrpid'],$_REQUEST['groupid'],$_REQUEST['access']);

					show_messages($result, S_SCRIPT_UPDATED, S_CANNOT_UPDATE_SCRIPT);
					$scriptid = $_REQUEST['scriptid'];
					$audit_acrion = AUDIT_ACTION_UPDATE;
				}
				else {
					$result = add_script($_REQUEST['name'],$_REQUEST['command'],$_REQUEST['usrgrpid'],$_REQUEST['groupid'],$_REQUEST['access']);

					show_messages($result, S_SCRIPT_ADDED, S_CANNOT_ADD_SCRIPT);
					$scriptid = $result;
					$audit_acrion = AUDIT_ACTION_ADD;
				}

				add_audit_if($result,$audit_acrion,AUDIT_RESOURCE_SCRIPT,' Name ['.$_REQUEST['name'].'] id ['.$scriptid.']');

				if($result){
					unset($_REQUEST['action']);
					unset($_REQUEST['form']);
					unset($_REQUEST['scriptid']);
				}
			}
		}
		else if(isset($_REQUEST['delete'])){
			$scriptid = get_request('scriptid', 0);

			$result = CScript::delete($scriptid);

			if($result){
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, S_SCRIPT.' ['.$scriptid.']');
			}

			show_messages($result, S_SCRIPT_DELETED, S_CANNOT_DELETE_SCRIPT);

			if($result){
				unset($_REQUEST['form']);
				unset($_REQUEST['scriptid']);
			}
		}
	// ------ GO -----
		else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['scripts'])){
			$scriptids = $_REQUEST['scripts'];

			$go_result = CScript::delete($scriptids);
			if($go_result){
				foreach($scriptids as $snum => $scriptid)
					add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, S_SCRIPT.' ['.$scriptid.']');
			}

			show_messages($go_result, S_SCRIPT_DELETED, S_CANNOT_DELETE_SCRIPT);

			if($go_result){
				unset($_REQUEST['form']);
				unset($_REQUEST['scriptid']);
			}
		}

		if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
			$url = new CUrl();
			$path = $url->getPath();
			insert_js('cookie.eraseArray("'.$path.'")');
		}
	}
?>
<?php

	$scripts_wdgt = new CWidget();

	$frmForm = new CForm();
	$frmForm->setMethod('get');
	$frmForm->addItem(new CButton('form',S_CREATE_SCRIPT,"javascript: redirect('scripts.php?form=1');"));
	$scripts_wdgt->addPageHeader(S_SCRIPTS_CONFIGURATION_BIG, $frmForm);

	if(isset($_REQUEST['form'])){

		$frmScr = new CFormTable(S_SCRIPT,'scripts.php','POST',null,'form');
		$frmScr->setAttribute('id','scripts');

		if(isset($_REQUEST['scriptid'])) $frmScr->addVar('scriptid',$_REQUEST['scriptid']);

		if(!isset($_REQUEST['scriptid']) || isset($_REQUEST['form_refresh'])){
			$name = get_request('name','');
			$command  = get_request('command','');

			$usrgrpid = get_request('usrgrpid',	0);
			$groupid = get_request('groupid',	0);

			$access = get_request('access',	PERM_READ_ONLY);
		}

		if(isset($_REQUEST['scriptid']) && !isset($_REQUEST['form_refresh'])){
			$frmScr->addVar('form_refresh',get_request('form_refresh',1));

			if($script = get_script_by_scriptid($_REQUEST['scriptid'])){
				$name = $script['name'];
				$command  = $script['command'];

				$usrgrpid = $script['usrgrpid'];
				$groupid = $script['groupid'];

				$access = $script['host_access'];
			}
		}

		$frmScr->addRow(S_NAME,new CTextBox('name',$name,80));
		$frmScr->addRow(S_COMMAND,new CTextBox('command',$command,80));

		$usr_groups = new CCombobox('usrgrpid',$usrgrpid);
		$usr_groups->addItem(0,S_ALL_S);

		$usrgrps = CUserGroup::get(array('extendoutput'=>1, 'sortfield'=>'name'));

		foreach($usrgrps as $ugnum => $usr_group){
			$usr_groups->addItem($usr_group['usrgrpid'],$usr_group['name']);
		}

		$frmScr->addRow(S_USER_GROUPS,$usr_groups);

		$host_groups = new CCombobox('groupid',$groupid);
		$host_groups->addItem(0,S_ALL_S);

		$groups = CHostGroup::get(array('extendoutput' => 1, 'sortfield'=>'name'));
		foreach($groups as $gnum => $group){
			$host_groups->addItem($group['groupid'],$group['name']);
		}

		$frmScr->addRow(S_HOST_GROUPS,$host_groups);

		$select_acc = new CCombobox('access',$access);
		$select_acc->addItem(PERM_READ_ONLY,S_READ);
		$select_acc->addItem(PERM_READ_WRITE,S_WRITE);

		$frmScr->addRow(S_REQUIRED_HOST.SPACE.S_PERMISSIONS_SMALL,$select_acc);

		$frmScr->addItemToBottomRow(new CButton('save',S_SAVE,"javascript: document.getElementById('scripts').action+='?action=1'; "));
		$frmScr->addItemToBottomRow(SPACE);

		if(isset($_REQUEST['scriptid'])) {
			$deleteButton = new CButtonDelete(S_DELETE_SCRIPTS_Q, '&action=1&scriptid='.$_REQUEST['scriptid']);
			$frmScr->addItemToBottomRow($deleteButton);
			$frmScr->addItemToBottomRow(SPACE);
		}

		$frmScr->addItemToBottomRow(new CButtonCancel());
		$scripts_wdgt->addItem($frmScr);
	}
	else {
		$form = new CForm();
		$form->setName('frm_scripts');
		$form->setAttribute('id', 'scripts');
		$form->addVar('action', '1');

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$scripts_wdgt->addHeader(S_SCRIPTS_BIG);
		$scripts_wdgt->addHeader($numrows);

		$table = new CTableInfo(S_NO_SCRIPTS_DEFINED);
		$table->setHeader(array(
				new CCheckBox('all_scripts',null,"checkAll('".$form->getName()."','all_scripts','scripts');"),
				make_sorting_header(S_NAME,'name'),
				make_sorting_header(S_COMMAND,'command'),
				S_USER_GROUP,
				S_HOST_GROUP,
				S_HOST_ACCESS
			)
		);

		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();

		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1,
			'select_groups' => API_OUTPUT_EXTEND
		);
		$scripts = CScript::get($options);

// sorting
		order_result($scripts, $sortfield, $sortorder);

// PAGING UPPER
		$paging = getPagingLine($scripts);
		$scripts_wdgt->addItem($paging);
//---------

		foreach($scripts as $snum => $script){
			$scriptid = $script['scriptid'];

			$user_group_name = S_ALL_S;

			if($script['usrgrpid'] > 0){
				$user_group = CUserGroup::get(array('usrgrpids' => $script['usrgrpid'], 'extendoutput' => 1));
				$user_group = reset($user_group);

				$user_group_name = $user_group['name'];
			}

			$host_group_name = S_ALL_S;
			if($script['groupid'] > 0){
				$group = array_pop($script['groups']);
				$host_group_name = $group['name'];
			}


			$table->addRow(array(
					new CCheckBox('scripts['.$script['scriptid'].']','no',NULL,$script['scriptid']),
					new CLink($script['name'],'scripts.php?form=1'.'&scriptid='.$script['scriptid'].'#form'),
					htmlspecialchars($script['command']),
					$user_group_name,
					$host_group_name,
					((PERM_READ_WRITE == $script['host_access'])?S_WRITE:S_READ)
				));
		}


// PAGING FOOTER
		$table->addRow(new CCol($paging));
//		$items_wdgt->addItem($paging);
//---------

//----- GO ------
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_SCRIPTS_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "scripts";');

		$table->setFooter(new CCol(array($goBox, $goButton)));
//----

		$form->addItem($table);
		$scripts_wdgt->addItem($form);
	}

	$scripts_wdgt->show();

?>
<?php

include_once('include/page_footer.php');

?>