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
	'type'=>			array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1'),	'isset({save})'),
	'execute_on'=>		array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1'),	'isset({save})&&{type}=='.ZBX_SCRIPT_TYPE_SCRIPT),
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
	$scripts = API::Script()->get(array(
		'scriptids' => $sid,
		'output' => API_OUTPUT_SHORTEN,
	));
	if(empty($scripts)) access_deny();
}

?>
<?php
	if(isset($_REQUEST['clone']) && isset($_REQUEST['scriptid'])){
		unset($_REQUEST['scriptid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){
		$confirmation = get_request('confirmation', '');
		$enableConfirmation = get_request('enableConfirmation', false);

		if($enableConfirmation && zbx_empty($confirmation)){
			error(_('Please enter confirmation text.'));
			show_messages(null, null, _('Cannot add script'));
		}
		else{
			$script = array(
				'name' => $_REQUEST['name'],
				'type' => $_REQUEST['type'],
				'execute_on' => $_REQUEST['execute_on'],
				'command' => $_REQUEST['command'],
				'description' => $_REQUEST['description'],
				'usrgrpid' => $_REQUEST['usrgrpid'],
				'groupid' => $_REQUEST['groupid'],
				'host_access' => $_REQUEST['access'],
				'confirmation' => get_request('confirmation', ''),
			);

			if(isset($_REQUEST['scriptid'])){
				$script['scriptid'] = $_REQUEST['scriptid'];

				$result = API::Script()->update($script);
				show_messages($result, _('Script updated'), _('Cannot update script'));

				$audit_acrion = AUDIT_ACTION_UPDATE;
			}
			else{
				$result = API::Script()->create($script);

				show_messages($result, _('Script added'), _('Cannot add script'));

				$audit_acrion = AUDIT_ACTION_ADD;
			}

			$scriptid = isset($result['scriptids']) ? reset($result['scriptids']) : null;

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

		$result = API::Script()->delete($scriptid);

		if($result){
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, _('Script').' ['.$scriptid.']');
		}

		show_messages($result, _('Script deleted'), _('Cannot delete script'));

		if($result){
			unset($_REQUEST['form']);
			unset($_REQUEST['scriptid']);
		}
	}
// ------ GO -----
	else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['scripts'])){
		$scriptids = $_REQUEST['scripts'];

		$go_result = API::Script()->delete($scriptids);
		if($go_result){
			foreach($scriptids as $snum => $scriptid)
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, _('Script').' ['.$scriptid.']');
		}

		show_messages($go_result, _('Script deleted'), _('Cannot delete script'));

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
		$data = array();
		$data['form'] = get_request('form', 1);
		$data['form_refresh'] = get_request('form_refresh', 0);
		$data['scriptid'] = get_request('scriptid');

		if(!$data['scriptid'] || isset($_REQUEST['form_refresh'])){
			$data['name'] = get_request('name', '');
			$data['type'] = get_request('type', ZBX_SCRIPT_TYPE_SCRIPT);
			$data['execute_on'] = get_request('execute_on', ZBX_SCRIPT_EXECUTE_ON_SERVER);
			$data['command'] = get_request('command', '');
			$data['description'] = get_request('description', '');
			$data['usrgrpid'] = get_request('usrgrpid',	0);
			$data['groupid'] = get_request('groupid', 0);
			$data['access'] = get_request('host_access', 0);
			$data['confirmation'] = get_request('confirmation',	'');
			$data['enableConfirmation'] = get_request('enableConfirmation', false);
		}
		else if($data['scriptid']){
			$options = array(
				'scriptids' => $data['scriptid'],
				'output' => API_OUTPUT_EXTEND,
			);
			$script = API::Script()->get($options);
			$script = reset($script);
			$data['name'] = $script['name'];
			$data['type'] = $script['type'];
			$data['execute_on'] = $script['execute_on'];
			$data['command']  = $script['command'];
			$data['description'] = $script['description'];
			$data['usrgrpid'] = $script['usrgrpid'];
			$data['groupid'] = $script['groupid'];
			$data['access'] = $script['host_access'];
			$data['confirmation'] = $script['confirmation'];
			$data['enableConfirmation'] = !empty($script['confirmation']);
		}

		$scriptForm = new CGetForm('script.edit', $data);
		$scripts_wdgt->addItem($scriptForm->render());
	}
	else{
		$frmForm = new CForm('get');
		$frmForm->addItem(new CSubmit('form', _('Create script')));
		$scripts_wdgt->addPageHeader(_('CONFIGURATION OF SCRIPTS'), $frmForm);

		$scripts_wdgt->addHeader(_('SCRIPTS'));
		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');
		$scripts_wdgt->addHeader($numrows);


		$form = new CForm();
		$form->setName('frm_scripts');
		$form->setAttribute('id', 'scripts');

		$table = new CTableInfo(_('No scripts defined'));
		$table->setHeader(array(
			new CCheckBox('all_scripts', null, "checkAll('".$form->getName()."','all_scripts','scripts');"),
			make_sorting_header(_('Name'), 'name'),
			_('Execute on'),
			make_sorting_header(_('Command'), 'command'),
			_('User group'),
			_('Host group'),
			_('Host access')
		));

		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();

		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'editable' => true,
			'selectGroups' => API_OUTPUT_EXTEND
		);
		$scripts = API::Script()->get($options);

// sorting
		order_result($scripts, $sortfield, $sortorder);
		$paging = getPagingLine($scripts);

		foreach($scripts as $snum => $script){
			$scriptid = $script['scriptid'];

			if($script['usrgrpid'] > 0){
				$user_group = API::UserGroup()->get(array('usrgrpids' => $script['usrgrpid'], 'output' => API_OUTPUT_EXTEND));
				$user_group = reset($user_group);

				$user_group_name = $user_group['name'];
			}
			else{
				$user_group_name = _('All');
			}

			if($script['groupid'] > 0){
				$group = array_pop($script['groups']);
				$host_group_name = $group['name'];
			}
			else{
				$host_group_name = _('All');
			}

			if($script['type'] == ZBX_SCRIPT_TYPE_SCRIPT){
				switch($script['execute_on']){
					case ZBX_SCRIPT_EXECUTE_ON_AGENT:
						$scriptExecuteOn = _('Agent');
						break;
					case ZBX_SCRIPT_EXECUTE_ON_SERVER:
						$scriptExecuteOn = _('Server');
						break;
				}
			}
			else{
				$scriptExecuteOn = '';
			}


			$table->addRow(array(
				new CCheckBox('scripts['.$script['scriptid'].']', 'no', NULL, $script['scriptid']),
				new CLink($script['name'], 'scripts.php?form=1'.'&scriptid='.$script['scriptid']),
				$scriptExecuteOn,
				htmlspecialchars($script['command'], ENT_COMPAT, 'UTF-8'),
				$user_group_name,
				$host_group_name,
				((PERM_READ_WRITE == $script['host_access']) ? _('Write') : _('Read'))
			));
		}



//----- GO ------
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('delete', _('Delete selected'));
		$goOption->setAttribute('confirm', _('Delete selected scripts?'));
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CSubmit('goButton', _('Go'));
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
