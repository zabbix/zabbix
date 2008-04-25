<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

	$page['title'] = "S_AUTHENTICATION_TO_ZABBIX";
	$page['file'] = 'authentication.php';
	$page['hist_arg'] = array('config');

include_once('include/page_header.php');

?>
<?php
	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

		'config'=>		array(T_ZBX_INT, O_OPT,	NULL,	IN('0'),	NULL),

// LDAP form
		'ldap_host'=>			array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,			'isset({config})&&({config}==0)&&(isset({save})||isset({test}))'),
		'ldap_port'=>			array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),	'isset({config})&&({config}==0)&&(isset({save})||isset({test}))'),
		
		'ldap_base_dn'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,			'isset({config})&&({config}==0)&&(isset({save})||isset({test}))'),
		
		'ldap_bind_dn'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({config})&&({config}==0)&&(isset({save})||isset({test}))'),
		'ldap_bind_password'=>			array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({config})&&({config}==0)&&(isset({save})||isset({test}))'),
		
		'ldap_search_attribute'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,			'isset({config})&&({config}==0)&&(isset({save})||isset({test}))'),
		
		'authentication_type'=>	array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1'),			NULL),
		
		'user_password'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({config})&&({config}==0)&&(isset({authentication_type})||isset({test}))'),

/* actions */
		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'test'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		
/* other */
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);
?>

<?php
	$_REQUEST['config'] = get_request('config',get_profile('web.authentication.config',0));
	check_fields($fields);
	
	update_profile('web.authentication.config',$_REQUEST['config']);
	
	$_REQUEST['authentication_type'] = get_request('authentication_type',ZBX_AUTH_INTERNAL);
	
	$result = 0;
	if($_REQUEST['config']==0){
		if(isset($_REQUEST['save'])){
	
			$config=select_config();
			$cur_auth_type = $config['authentication_type'] ;
			
			foreach($config as $id => $value){
				if(isset($_REQUEST[$id])){
					$config[$id] = $_REQUEST[$id];
					$ldap_cnf[str_replace('ldap_','',$id)] = $_REQUEST[$id];
				}
				else{
					unset($config[$id]);
				}
			}

			$result = true;
			if(ZBX_AUTH_LDAP == $config['authentication_type']){
				$result=ldap_authentication($USER_DETAILS['alias'],get_request('user_password',''),$ldap_cnf);
			}
			
// If we do save and auth_type changed or is set to LDAP, reset all sessions 
			if($result && (($cur_auth_type<>$config['authentication_type']) || (ZBX_AUTH_LDAP == $config['authentication_type']))){
				DBexecute('DELETE FROM sessions WHERE sessionid<>'.zbx_dbstr($USER_DETAILS['sessionid']));
			}
			
			if($result){
				$result=update_config($config);
			}
	
			show_messages($result, S_LDAP.SPACE.S_UPDATED, S_LDAP.SPACE.S_WAS_NOT.SPACE.S_UPDATED);
	
			if($result){
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,S_LDAP);
			}		
		}
		else if(isset($_REQUEST['test'])){
			$config=select_config();
			foreach($config as $id => $value){
				if(isset($_REQUEST[$id])){
					$ldap_cnf[str_replace('ldap_','',$id)] = $_REQUEST[$id];
				}
			}

			$result = ldap_authentication($USER_DETAILS['alias'],get_request('user_password',''),$ldap_cnf);
			
			show_messages($result, S_LDAP.SPACE.S_LOGIN.SPACE.S_SUCCESSFUL_SMALL, S_LDAP.SPACE.S_LOGIN.SPACE.S_WAS_NOT.SPACE.S_SUCCESSFUL_SMALL);
		}
	}
	show_messages();
?>
<?php

	$form = new CForm('authentication.php');
	$form->SetMethod('get');
	$cmbConfig = new CCombobox('config',$_REQUEST['config'],'submit()');
	$cmbConfig->AddItem(0,S_LDAP);

	$form->AddItem($cmbConfig);

	show_table_header(S_AUTHENTICATION_TO_ZABBIX, $form);
	echo SBR;
?>

<?php
	if($_REQUEST['config']==0){
		$config=select_config();

		if(isset($_REQUEST['form_refresh'])){
			foreach($config as $id => $value){
				if(isset($_REQUEST[$id])){
					$config[$id] = $_REQUEST[$id];
				}
				else{
					unset($config[$id]);
				}
			}
		}

		$form_refresh = get_request('form_refresh',0);
		$form_refresh++;
		
		$frmAuth = new CFormTable(S_LDAP,'authentication.php');
		$frmAuth->SetHelp('web.authentication.php');
		$frmAuth->AddVar('config',get_request('config',0));
		$frmAuth->AddVar('form_refresh',$form_refresh);

		$frmAuth->AddRow(S_LDAP.SPACE.S_HOST, new CTextBox('ldap_host',$config['ldap_host'],64));
		$frmAuth->AddRow(S_PORT, new CNumericBox('ldap_port',$config['ldap_port'],5));

		$frmAuth->AddRow(S_BASE_DN,new CTextBox('ldap_base_dn',$config['ldap_base_dn'],64));		
		$frmAuth->AddRow(S_SEARCH_ATTRIBUTE,new CTextBox('ldap_search_attribute',empty($config['ldap_search_attribute'])?'uid':$config['ldap_search_attribute']));
				
		$frmAuth->AddRow(S_BIND_DN.'*', new CTextBox('ldap_bind_dn',$config['ldap_bind_dn'],64));
		$frmAuth->AddRow(S_BIND_PASSWORD.'*',new CPassBox('ldap_bind_password',$config['ldap_bind_password']));

		$action = "javascript: if(confirm('Switching LDAP authentication will delete all current sessions! Continue?')) return true; else return false;";
		$frmAuth->AddRow(S_LDAP.SPACE.S_AUTHENTICATION.SPACE.S_ENABLED, new CCheckBox('authentication_type', $config['authentication_type'],$action,ZBX_AUTH_LDAP));

		$frmAuth->AddRow(S_TEST.SPACE.S_AUTHENTICATION, ' ['.S_MUST_BE_VALID_SMALL.SPACE.S_LDAP.SPACE.S_USER.']');
		$frmAuth->AddRow(S_LOGIN , new CTextBox('user',$USER_DETAILS['alias'],null,'yes'));
		$frmAuth->AddRow(S_USER.SPACE.S_PASSWORD,new CPassBox('user_password'));
//		$frmAuth->AddRow( ,new CTextBox('',$config['']));
//		$frmAuth->AddRow( ,new CTextBox('',$config['']));

		$frmAuth->AddItemToBottomRow(new CButton('save',S_SAVE));
		$frmAuth->AddItemToBottomRow(new CButton('test',S_TEST));
		$frmAuth->Show();
	}

include_once 'include/page_footer.php';
?>