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
require_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/users.inc.php');

$page['title'] = 'S_SCRIPTS';
$page['file'] = 'scripts.php';
$page['hist_arg'] = array('scriptid');

require_once('include/page_header.php');
?>
<?php
//		VAR						TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'scriptid'=>		array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	null),
	'scripts'=>			array(T_ZBX_INT, O_OPT,	P_SYS,			DB_ID,	null),
// Actions
	'go'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// form
	'action'=>			array(T_ZBX_INT, O_OPT,  P_ACT, 		IN('0,1'),	null),
	'save'=>			array(T_ZBX_STR, O_OPT,	 P_SYS|P_ACT,	NULL,		null),
	'delete'=>			array(T_ZBX_STR, O_OPT,  P_ACT, 		null,	null),
	'clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
// form
	'name'=>			array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	'isset({save})'),
	'command'=>			array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	'isset({save})'),
	'description'=>		array(T_ZBX_STR, O_OPT,  NULL,			NULL,	'isset({save})'),
	'access'=>			array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1,2,3'),	'isset({save})'),
	'groupid'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,			DB_ID,		'isset({save})'),
	'usrgrpid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,			DB_ID,		'isset({save})'),
	'confirmation'=>		array(T_ZBX_STR, O_OPT,  NULL,			NULL,	null),
	'enableConfirmation'=>	array(T_ZBX_STR, O_OPT,  NULL,			NULL,	null),

	'form'=>			array(T_ZBX_STR, O_OPT,  NULL,		  	NULL,		null),
	'form_refresh'=>	array(T_ZBX_INT, O_OPT,	 NULL,			NULL,		null),
);

check_fields($fields);

$_REQUEST['go'] = get_request('go', 'none');

validate_sort_and_sortorder('name', ZBX_SORT_UP);

	if($sid = get_request('scriptid')){
		$options = array(
			'scriptids' => $sid,
			'output' => API_OUTPUT_SHORTEN,
		);
		$scripts = CScript::get($options);
		if(empty($scripts)) access_deny();
	}

?>
<?php
	if(isset($_REQUEST['clone']) && isset($_REQUEST['scriptid'])){
		unset($_REQUEST['scriptid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){
		$cond = (isset($_REQUEST['scriptid']))?(' AND scriptid<>'.$_REQUEST['scriptid']):('');
		$scripts = DBfetch(DBselect('SELECT count(scriptid) as cnt FROM scripts WHERE name='.zbx_dbstr($_REQUEST['name']).$cond.' and '.DBin_node('scriptid', get_current_nodeid(false)),1));

		$confirmation = get_request('confirmation', '');
		$enableConfirmation = get_request('enableConfirmation', false);

		if($scripts && $scripts['cnt'] > 0){
			error(_s('Script [%s] already exists.', htmlspecialchars($_REQUEST['name'])));
			show_messages(null, S_ERROR, S_CANNOT_ADD_SCRIPT);
		}
		else if($enableConfirmation && zbx_empty($confirmation)){
			error(_('Please input confirmation'));
			show_messages(null, S_ERROR, S_CANNOT_ADD_SCRIPT);
		}
		else{
			$script = array(
				'name' => $_REQUEST['name'],
				'command' => $_REQUEST['command'],
				'description' => $_REQUEST['description'],
				'usrgrpid' => $_REQUEST['usrgrpid'],
				'groupid' => $_REQUEST['groupid'],
				'host_access' => $_REQUEST['access'],
				'confirmation' => get_request('confirmation', ''),
			);

			if(isset($_REQUEST['scriptid'])){
				$script['scriptid'] = $_REQUEST['scriptid'];

				$result = CScript::update($script);
				show_messages($result, S_SCRIPT_UPDATED, S_CANNOT_UPDATE_SCRIPT);
				$scriptid = $_REQUEST['scriptid'];
				$audit_acrion = AUDIT_ACTION_UPDATE;
			}
			else{
				$result = CScript::create($script);

				show_messages($result, S_SCRIPT_ADDED, S_CANNOT_ADD_SCRIPT);
				$scriptid = reset($result['scriptids']);
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
?>
<?php
	$scripts_wdgt = new CWidget();

	if(isset($_REQUEST['form'])){
		$scriptForm = new CGetForm();
		$scripts_wdgt->addItem($scriptForm->render('script.edit'));
	}
	else{
		$frmForm = new CForm(null, 'get');
		$frmForm->addItem(new CSubmit('form', _('Create script')));
		$scripts_wdgt->addPageHeader(S_SCRIPTS_CONFIGURATION_BIG, $frmForm);

		$scripts_wdgt->addHeader(S_SCRIPTS_BIG);
		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');
		$scripts_wdgt->addHeader($numrows);


		$form = new CForm();
		$form->setName('frm_scripts');
		$form->setAttribute('id', 'scripts');

		$table = new CTableInfo(S_NO_SCRIPTS_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_scripts', null, "checkAll('".$form->getName()."','all_scripts','scripts');"),
			make_sorting_header(S_NAME, 'name'),
			make_sorting_header(S_COMMAND, 'command'),
			S_USER_GROUP,
			S_HOST_GROUP,
			S_HOST_ACCESS
		));

		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();

		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'editable' => true,
			'selectGroups' => API_OUTPUT_EXTEND
		);
		$scripts = CScript::get($options);

// sorting
		order_result($scripts, $sortfield, $sortorder);
		$paging = getPagingLine($scripts);

		foreach($scripts as $snum => $script){
			$scriptid = $script['scriptid'];

			$user_group_name = S_ALL_S;

			if($script['usrgrpid'] > 0){
				$user_group = CUserGroup::get(array('usrgrpids' => $script['usrgrpid'], 'output' => API_OUTPUT_EXTEND));
				$user_group = reset($user_group);

				$user_group_name = $user_group['name'];
			}

			$host_group_name = S_ALL_S;
			if($script['groupid'] > 0){
				$group = array_pop($script['groups']);
				$host_group_name = $group['name'];
			}


			$table->addRow(array(
				new CCheckBox('scripts['.$script['scriptid'].']', 'no', NULL, $script['scriptid']),
				new CLink($script['name'], 'scripts.php?form=1'.'&scriptid='.$script['scriptid']),
				htmlspecialchars($script['command'], ENT_COMPAT, 'UTF-8'),
				$user_group_name,
				$host_group_name,
				((PERM_READ_WRITE == $script['host_access']) ? S_WRITE : S_READ)
			));
		}



//----- GO ------
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('delete', S_DELETE_SELECTED);
		$goOption->setAttribute('confirm', S_DELETE_SELECTED_SCRIPTS_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CSubmit('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "scripts";');

		$footer = get_table_header(array($goBox, $goButton));
//----

		$form->addItem(array($paging,$table,$paging,$footer));
		$scripts_wdgt->addItem($form);
	}

	$scripts_wdgt->show();

?>
<?php
include_once('include/page_footer.php');
?>
