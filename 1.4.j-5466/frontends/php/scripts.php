<?php
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
	include_once "include/config.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/scripts.inc.php";

	$page['title'] = "S_SCRIPTS";
	$page['file'] = 'scripts.php';

	
include_once "include/page_header.php";

//---------------------------------- CHECKS ------------------------------------

//		VAR							TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

	$fields=array(
		'scriptid'=>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	null),
		'scripts'=>				array(T_ZBX_INT, O_OPT,	P_SYS,			DB_ID,	null),

// action
		'action'=>				array(T_ZBX_INT, O_OPT,  P_ACT, 		IN('0,1'),	null),
		'save'=>				array(T_ZBX_STR, O_OPT,	 P_SYS|P_ACT,	NULL,		null),
		'delete'=>				array(T_ZBX_STR, O_OPT,  P_ACT, 		null,	null),
		
// form
		'name'=>				array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	'isset({save})'),
		'command'=>				array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	'isset({save})'),
		'access'=>				array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1'),	'isset({save})'),
		
		'form'=>				array(T_ZBX_STR, O_OPT,  NULL,		  	IN('0,1'),	null),
		'form_refresh'=>		array(T_ZBX_INT, O_OPT,	 NULL,			NULL,		null),
	);
	
check_fields($fields);

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
				$result = update_script($_REQUEST['scriptid'],$_REQUEST['name'],$_REQUEST['command'],$_REQUEST['access']);
					
				show_messages($result, S_SCRIPT_UPDATED, S_CANNOT_UPDATE_SCRIPT);
				$scriptid = $_REQUEST['scriptid'];
				$audit_acrion = AUDIT_ACTION_UPDATE;
			} 
			else {
				$result = add_script($_REQUEST['name'],$_REQUEST['command'],$_REQUEST['access']);
				
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

		if(isset($_REQUEST['scriptid'])){
			$scripts[$_REQUEST['scriptid']] = $_REQUEST['scriptid'];
		}
		else{
			$scripts = $_REQUEST['scripts'];
		}

		$result = true;
		foreach($scripts as $scriptid){
			$result &= delete_script($scriptid);
		
			if($result){
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, S_SCRIPT.' ['.$scriptid.']');
			}
		}
		show_messages($result, S_SCRIPT_DELETED, S_CANNOT_DELETE_SCRIPT);
		
		if($result){
			unset($_REQUEST['form']);
			unset($_REQUEST['scriptid']);
		}
	}
}

if(isset($_REQUEST['form'])){

	$frmScr = new CFormTable(S_SCRIPT,'scripts.php','POST',null,'form');
	$frmScr->AddOption('id','scripts');
	
	if(isset($_REQUEST['scriptid'])) $frmScr->AddVar('scriptid',$_REQUEST['scriptid']);
	
	$name = get_request('name','');
	$command  = get_request('command','');
	$access = get_request('access',	SCRIPT_HOST_ACCESS_READ);

	if(isset($_REQUEST['scriptid']) && !isset($_REQUEST['form_refresh'])){
		$frmScr->AddVar('form_refresh',get_request('form_refresh',1));
		
		if($script = get_script_by_scriptid($_REQUEST['scriptid'])){
			$name = $script['name'];
			$command  = $script['command'];
			$access = $script['host_access'];
		}
	}
	
	$frmScr->AddRow(S_NAME,new CTextBox('name',$name,80));
	$frmScr->AddRow(S_COMMAND,new CTextBox('command',$command,80));
	
	$select_acc = new CCombobox('access');
		$select_acc->AddItem(SCRIPT_HOST_ACCESS_READ,S_READ,(($access == SCRIPT_HOST_ACCESS_READ)?'yes':'no'));
		$select_acc->AddItem(SCRIPT_HOST_ACCESS_WRITE,S_WRITE,(($access == SCRIPT_HOST_ACCESS_WRITE)?'yes':'no'));
		
	$frmScr->AddRow(S_REQUIRED_HOST.SPACE.S_PERMISSIONS_SMALL,$select_acc);

	$frmScr->AddItemToBottomRow(new CButton('save',S_SAVE,"javascript: document.getElementById('scripts').action+='?action=1'; "));
	$frmScr->AddItemToBottomRow(SPACE);

	$frmScr->AddItemToBottomRow(new CButtonCancel());
	$frmScr->Show();
}
else {
	$form = new CForm();
	$form->SetName('scripts');
	$form->AddOption('id','scripts');
	
	show_table_header(S_SCRIPTS);
	
	$table=new CTableInfo(S_NO_SCRIPTS_DEFINED);
	$table->setHeader(array(
				array(new CCheckBox('all_scripts',null,"CheckAll('".$form->GetName()."','all_scripts');"),S_NAME),
						S_COMMAND, S_HOST_ACCESS
				)
			);
	
	$sql = 'SELECT * FROM scripts '.
			' WHERE '.DBin_node('scriptid').
			' ORDER BY scriptid ASC';
	
	$scripts=DBselect($sql);
	
	while($script=DBfetch($scripts)){
		
		$table->addRow(array(
			array(
				new CCheckBox('scripts['.$script['scriptid'].']','no',NULL,$script['scriptid']),
				new CLink($script['name'],'scripts.php?form=1'.'&scriptid='.$script['scriptid'].'#form','action')
			),
			htmlspecialchars($script['command']),
			(($script['host_access'] == SCRIPT_HOST_ACCESS_READ)?S_READ:S_WRITE)
			));
	}
	$qbutton = new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_SCRIPTS_Q,'1');
	$qbutton->SetAction("javascript: document.getElementById('scripts').action+='?action=1';");
	
	$tr = new CCol(
				array(
					new CButton('form',S_ADD_SCRIPT,"javascript: Redirect('scripts.php?form=1');"),
					SPACE,
					$qbutton
				)
			);
	
	$table->SetFooter($tr);
	
	$form->AddItem($table);
	$form->show();
}
?>
<?php
include_once "include/page_footer.php";
?>
