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

$page['title'] = "S_AUTHENTICATION_TO_ZABBIX";
$page['file'] = 'authentication.php';
$page['hist_arg'] = array('config');

include_once('include/page_header.php');

?>
<?php
	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
//												0 - internal, 1- LDAP, 2 - HTTP
		'config'=>		array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1,2'),	NULL),

// LDAP form
		'ldap_host'=>			array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,			'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),
		'ldap_port'=>			array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),	'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),

		'ldap_base_dn'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,			'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),

		'ldap_bind_dn'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),
		'ldap_bind_password'=>			array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),

		'ldap_search_attribute'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,			'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),

		'authentication_type'=>	array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1,2'),			NULL),

		'user'=>				array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({config})&&({config}==1)&&isset({form_refresh_ldap})&&(isset({authentication_type}) || isset({test}))'),
		'user_password'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({config})&&({config}==1)&&isset({form_refresh_ldap})&&(isset({authentication_type}) || isset({test}))'),

/* actions */
		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'test'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),

/* other */
		'form'=>					array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh_internal'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL),
		'form_refresh_ldap'=>		array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL),
		'form_refresh_http'=>		array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);
?>

<?php
	$_REQUEST['config'] = get_request('config',CProfile::get('web.authentication.config',ZBX_AUTH_LDAP));
	check_fields($fields);

	CProfile::update('web.authentication.config',$_REQUEST['config'], PROFILE_TYPE_INT);

	$_REQUEST['authentication_type'] = get_request('authentication_type',ZBX_AUTH_INTERNAL);

	$result = 0;

	if(ZBX_AUTH_INTERNAL==$_REQUEST['config']){
		if(isset($_REQUEST['save'])){

			$config=select_config();

			$cur_auth_type = $config['authentication_type'] ;
			$config['authentication_type'] = ZBX_AUTH_INTERNAL;

			foreach($config as $id => $value){
				if(isset($_REQUEST[$id])){
					$config[$id] = $_REQUEST[$id];
				}
				else{
					unset($config[$id]);
				}
			}

// If we do save and auth_type changed, reset all sessions
			if($cur_auth_type<>$config['authentication_type']){
				DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid<>'.zbx_dbstr($USER_DETAILS['sessionid']));
			}

			$result=update_config($config);

			show_messages($result, S_ZABBIX_INTERNAL_AUTH.SPACE.S_UPDATED, S_CANNOT_UPDATE.SPACE.S_HTTP_AUTH);

			if($result){
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,S_HTTP_AUTH);
			}
		}
	}
	else if($_REQUEST['config']==ZBX_AUTH_LDAP){
		if(isset($_REQUEST['save'])){
			$alias = get_request('user', $USER_DETAILS['alias']);
			$passwd = get_request('user_password','');

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
				$ldap = new CLdap($ldap_cnf);
				$ldap->connect();
				$result = $ldap->checkPass($alias, $passwd);
			}

// If we do save and auth_type changed, reset all sessions
			if($result && ($cur_auth_type<>$config['authentication_type'])){
				DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid<>'.zbx_dbstr($USER_DETAILS['sessionid']));
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
			$alias = get_request('user', $USER_DETAILS['alias']);
			$passwd = get_request('user_password','');

			$config=select_config();
			foreach($config as $id => $value){
				if(isset($_REQUEST[$id])){
					$ldap_cnf[str_replace('ldap_','',$id)] = $_REQUEST[$id];
				}
			}

			$ldap = new CLdap($ldap_cnf);
			$ldap->connect();
			$result = $ldap->checkPass($alias, $passwd);

			show_messages($result, S_LDAP.SPACE.S_LOGIN.SPACE.S_SUCCESSFUL_SMALL, S_LDAP.SPACE.S_LOGIN.SPACE.S_WAS_NOT.SPACE.S_SUCCESSFUL_SMALL);
		}
	}
	else if(ZBX_AUTH_HTTP==$_REQUEST['config']){
		if(isset($_REQUEST['save'])){

			if(ZBX_AUTH_HTTP == $_REQUEST['authentication_type']){
				$sql = 'SELECT COUNT(g.usrgrpid) as cnt_usrgrp FROM usrgrp g WHERE g.gui_access='.GROUP_GUI_ACCESS_INTERNAL;
				$res = DBfetch(DBselect($sql));
				if($res['cnt_usrgrp'] > 0){
					info('Exists ['.$res['cnt_usrgrp'].'] groups with ['.S_INTERNAL_S.'] GUI access.');
				}
			}

			$config=select_config();

			$cur_auth_type = $config['authentication_type'] ;
			$config['authentication_type'] = ZBX_AUTH_HTTP;

			foreach($config as $id => $value){
				if(isset($_REQUEST[$id])){
					$config[$id] = $_REQUEST[$id];
				}
				else{
					unset($config[$id]);
				}
			}

// If we do save and auth_type changed or is set to LDAP, reset all sessions
			if($cur_auth_type<>$config['authentication_type']){
				DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid<>'.zbx_dbstr($USER_DETAILS['sessionid']));
			}

			$result=update_config($config);

			show_messages($result, S_HTTP_AUTH.SPACE.S_UPDATED, S_CANNOT_UPDATE.SPACE.S_HTTP_AUTH);

			if($result){
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,S_HTTP_AUTH);
			}
		}
	}
	show_messages();
?>
<?php
	$config=select_config();

	switch($config['authentication_type']){
		case ZBX_AUTH_INTERNAL:
			$auth = S_ZABBIX_INTERNAL_AUTH;
			break;
		case ZBX_AUTH_LDAP:
			$auth = S_LDAP_AUTH;
			break;
		case ZBX_AUTH_HTTP:
			$auth = S_HTTP_AUTH;
			break;
		default:
			$auth = '';
	}

	show_table_header(S_AUTHENTICATION_TO_ZABBIX, $auth);
	
	if(ZBX_AUTH_INTERNAL==$_REQUEST['config']){

		$form_refresh_internal = get_request('form_refresh_internal',0);
		$form_refresh_internal++;

		$frmAuth = new CFormTable(S_ZABBIX_INTERNAL_AUTH,'authentication.php');
		$frmAuth->setHelp('web.authentication.php');
		$frmAuth->addVar('form_refresh_internal',$form_refresh_internal);

		$cmbConfig = new CCombobox('config',ZBX_AUTH_INTERNAL,'submit()');
		$cmbConfig->addItem(ZBX_AUTH_INTERNAL,S_INTERNAL_S);
		$cmbConfig->addItem(ZBX_AUTH_LDAP,S_LDAP);
		$cmbConfig->addItem(ZBX_AUTH_HTTP,S_HTTP);

		$frmAuth->addRow(S_DEFAULT_AUTHENTICATION, $cmbConfig);

		$action = "javascript: if(confirm('".S_SWITCHING_HTTP."')) return true; else return false;";
		$frmAuth->addRow(S_ZABBIX_INTERNAL_AUTH.SPACE.S_ENABLED, new CCheckBox('authentication_type', (ZBX_AUTH_INTERNAL == $config['authentication_type']), $action, ZBX_AUTH_INTERNAL));

		$frmAuth->addItemToBottomRow(new CButton('save',S_SAVE));
		$frmAuth->Show();
	}
	else if(ZBX_AUTH_LDAP==$_REQUEST['config']){
		if(isset($_REQUEST['form_refresh_ldap'])){
			foreach($config as $id => $value){
				if(isset($_REQUEST[$id])){
					$config[$id] = $_REQUEST[$id];
				}
				else{
					unset($config[$id]);
				}
			}
		}

		$form_refresh_ldap = get_request('form_refresh_ldap',0);
		$form_refresh_ldap++;

		$frmAuth = new CFormTable(S_LDAP_AUTH,'authentication.php');
		$frmAuth->SetHelp('web.authentication.php');
		$frmAuth->addVar('form_refresh_ldap',$form_refresh_ldap);

		$cmbConfig = new CCombobox('config',ZBX_AUTH_LDAP,'submit()');
		$cmbConfig->addItem(ZBX_AUTH_INTERNAL,S_INTERNAL_S);
		$cmbConfig->addItem(ZBX_AUTH_LDAP,S_LDAP);
		$cmbConfig->addItem(ZBX_AUTH_HTTP,S_HTTP);

		$frmAuth->addRow(S_DEFAULT_AUTHENTICATION, $cmbConfig);

		$frmAuth->addRow(S_LDAP.SPACE.S_HOST, new CTextBox('ldap_host',$config['ldap_host'],64));
		$frmAuth->addRow(S_PORT, new CNumericBox('ldap_port',$config['ldap_port'],5));

		$frmAuth->addRow(S_BASE_DN,new CTextBox('ldap_base_dn',$config['ldap_base_dn'],64));
		$frmAuth->addRow(S_SEARCH_ATTRIBUTE,new CTextBox('ldap_search_attribute',empty($config['ldap_search_attribute'])?'uid':$config['ldap_search_attribute']));

		$frmAuth->addRow(S_BIND_DN.'*', new CTextBox('ldap_bind_dn',$config['ldap_bind_dn'],64));
		$frmAuth->addRow(S_BIND_PASSWORD.'*',new CPassBox('ldap_bind_password',$config['ldap_bind_password']));

		$action = "javascript: if(confirm('".S_SWITCHING_LDAP."')) return true; else return false;";
		$frmAuth->addRow(S_LDAP.SPACE.S_AUTHENTICATION.SPACE.S_ENABLED, new CCheckBox('authentication_type', $config['authentication_type'],$action,ZBX_AUTH_LDAP));

		$frmAuth->addRow(S_TEST.SPACE.S_AUTHENTICATION, ' ['.S_MUST_BE_VALID_SMALL.SPACE.S_LDAP.SPACE.S_USER.']');

		if(GROUP_GUI_ACCESS_INTERNAL == get_user_auth($USER_DETAILS['userid'])){
			$usr_test = new CComboBox('user', $USER_DETAILS['alias']);
			$sql = 'SELECT u.alias, u.userid '.
					' FROM users u '.
					' WHERE '.DBin_node('u.userid').
					' ORDER BY alias ASC';
			$u_res = DBselect($sql);
			while($db_user = Dbfetch($u_res)){
				if(check_perm2login($db_user['userid']) && check_perm2system($db_user['userid'])){
					$usr_test->addItem($db_user['alias'],$db_user['alias']);
				}
			}
		}
		else{
			$usr_test = new CTextBox('user',$USER_DETAILS['alias'],null,'yes');
		}

		$frmAuth->addRow(S_LOGIN , $usr_test);
		$frmAuth->addRow(S_USER.SPACE.S_PASSWORD,new CPassBox('user_password'));

		$frmAuth->addItemToBottomRow(new CButton('save',S_SAVE));
		$frmAuth->addItemToBottomRow(new CButton('test',S_TEST));
		$frmAuth->Show();
	}
	else if(ZBX_AUTH_HTTP==$_REQUEST['config']){

		$form_refresh_http = get_request('form_refresh_http',0);
		$form_refresh_http++;

		$frmAuth = new CFormTable(S_HTTP_AUTH,'authentication.php');
		$frmAuth->SetHelp('web.authentication.php');
		$frmAuth->addVar('form_refresh_http',$form_refresh_http);

		$cmbConfig = new CCombobox('config',ZBX_AUTH_HTTP,'submit()');
		$cmbConfig->addItem(ZBX_AUTH_INTERNAL,S_INTERNAL_S);
		$cmbConfig->addItem(ZBX_AUTH_LDAP,S_LDAP);
		$cmbConfig->addItem(ZBX_AUTH_HTTP,S_HTTP);

		$frmAuth->addRow(S_DEFAULT_AUTHENTICATION, $cmbConfig);

		$action = "javascript: if(confirm('".S_SWITCHING_HTTP."')) return true; else return false;";
		$frmAuth->addRow(S_HTTP_AUTH.SPACE.S_ENABLED, new CCheckBox('authentication_type', (ZBX_AUTH_HTTP == $config['authentication_type']), $action, ZBX_AUTH_HTTP));

		$frmAuth->addItemToBottomRow(new CButton('save',S_SAVE));
		$frmAuth->Show();
	}

include_once 'include/page_footer.php';
?>
