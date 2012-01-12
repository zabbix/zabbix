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
require_once('include/httptest.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_CONFIGURATION_OF_WEB_MONITORING';
$page['file'] = 'httpconf.php';
$page['hist_arg'] = array('groupid','hostid');

include_once('include/page_header.php');
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'applications'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'applicationid'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'close'=>			array(T_ZBX_INT, O_OPT,	null,	IN('1'),	null),
		'open'=>			array(T_ZBX_INT, O_OPT,	null,	IN('1'),	null),

		'groupid'=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,null),
		'hostid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'isset({form})||isset({save})'),

		'httptestid'=>	array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID, '(isset({form})&&({form}=="update"))'),
		'application'=>	array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY, 'isset({save})'),
		'name'=>		array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY, 'isset({save})'),
		'delay'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,86400),'isset({save})'),
		'status'=>		array(T_ZBX_INT, O_OPT,  null,  IN('0,1'),'isset({save})'),
		'agent'=>		array(T_ZBX_STR, O_OPT,  null,	null,'isset({save})'),
		'macros'=>		array(T_ZBX_STR, O_OPT,  null,	null,'isset({save})'),
		'steps'=>		array(T_ZBX_STR, O_OPT,  null,	null,'isset({save})'),
		'authentication'=>	array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2'),'isset({save})'),
		'http_user'=>		array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,'isset({save}) && isset({authentication}) && ({authentication}=='.HTTPTEST_AUTH_BASIC.'||{authentication}=='.HTTPTEST_AUTH_NTLM.')'),
		'http_password'=>	array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,'isset({save}) && isset({authentication}) && ({authentication}=='.HTTPTEST_AUTH_BASIC.'||{authentication}=='.HTTPTEST_AUTH_NTLM.')'),

		'new_httpstep'=>	array(T_ZBX_STR, O_OPT,  null,	null,null),

		'move_up'=>			array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),
		'move_down'=>		array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),

		'sel_step'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65534), null),

		'group_httptestid'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),

		'showdisabled'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),	null),
// Actions
		'go'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// form
		'clone'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_sel_step'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null)
	);

	$_REQUEST['showdisabled'] = get_request('showdisabled', CProfile::get('web.httpconf.showdisabled', 0));

	check_fields($fields);
	validate_sort_and_sortorder('name', ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go', 'none');
?>
<?php
	$showdisabled = get_request('showdisabled', 0);

	$options = array(
		'groups' => array(
			'not_proxy_hosts' => 1,
			'editable' => 1,
		),
		'hosts' => array(
			'editable' => 1,
		),
		'hostid' => get_request('hostid', null),
		'groupid' => get_request('groupid', null),
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = $pageFilter->hostid;


	$available_hosts = $pageFilter->hostsSelected ? array_keys($pageFilter->hosts) : array();

	CProfile::update('web.httpconf.showdisabled',$showdisabled, PROFILE_TYPE_STR);
?>
<?php
	$_REQUEST['applications'] = get_request('applications', get_favorites('web.httpconf.applications'));
	$_REQUEST['applications'] = zbx_objectValues($_REQUEST['applications'], 'value');

	if(isset($_REQUEST['open'])){
		if(!isset($_REQUEST['applicationid'])){
			$_REQUEST['applications'] = array();
			$show_all_apps = 1;
		}
		else if(!uint_in_array($_REQUEST['applicationid'],$_REQUEST['applications'])){
			array_push($_REQUEST['applications'],$_REQUEST['applicationid']);
		}
	}
	else if(isset($_REQUEST['close'])){
		if(!isset($_REQUEST['applicationid'])){
			$_REQUEST['applications'] = array();
		}
		else if(($i=array_search($_REQUEST['applicationid'], $_REQUEST['applications'])) !== FALSE){
			unset($_REQUEST['applications'][$i]);
		}
	}

	if(count($_REQUEST['applications']) > 25){
		$_REQUEST['applications'] = array_slice($_REQUEST['applications'], -25);
	}
/* limit opened application count */
	// while(count($_REQUEST['applications']) > 25){
		// array_shift($_REQUEST['applications']);
	// }

	rm4favorites('web.httpconf.applications');
	foreach($_REQUEST['applications'] as $application){
		add2favorites('web.httpconf.applications', $application);
	}
	// CProfile::update('web.httpconf.applications',$_REQUEST['applications'],PROFILE_TYPE_ARRAY_ID);

	if(isset($_REQUEST['del_sel_step'])&&isset($_REQUEST['sel_step'])&&is_array($_REQUEST['sel_step'])){
		foreach($_REQUEST['sel_step'] as $sid)
			if(isset($_REQUEST['steps'][$sid]))
				unset($_REQUEST['steps'][$sid]);
	}
	else if(isset($_REQUEST['new_httpstep'])){
		$_REQUEST['steps'] = get_request('steps', array());
		array_push($_REQUEST['steps'],$_REQUEST['new_httpstep']);
	}
	else if(isset($_REQUEST['move_up']) && isset($_REQUEST['steps'][$_REQUEST['move_up']])){
		$new_id = $_REQUEST['move_up'] - 1;

		if(isset($_REQUEST['steps'][$new_id])){
			$tmp = $_REQUEST['steps'][$new_id];
			$_REQUEST['steps'][$new_id] = $_REQUEST['steps'][$_REQUEST['move_up']];
			$_REQUEST['steps'][$_REQUEST['move_up']] = $tmp;
		}
	}
	else if(isset($_REQUEST['move_down']) && isset($_REQUEST['steps'][$_REQUEST['move_down']])){
		$new_id = $_REQUEST['move_down'] + 1;

		if(isset($_REQUEST['steps'][$new_id])){
			$tmp = $_REQUEST['steps'][$new_id];
			$_REQUEST['steps'][$new_id] = $_REQUEST['steps'][$_REQUEST['move_down']];
			$_REQUEST['steps'][$_REQUEST['move_down']] = $tmp;
		}
	}
	else if(isset($_REQUEST['delete'])&&isset($_REQUEST['httptestid'])){
		$result = false;
		if($httptest_data = get_httptest_by_httptestid($_REQUEST['httptestid'])){
			$result = delete_httptest($_REQUEST['httptestid']);
		}
		show_messages($result, S_SCENARIO_DELETED, S_CANNOT_DELETE_SCENARIO);
		if($result){
			$host = get_host_by_applicationid($httptest_data['applicationid']);

			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO,
				S_SCENARIO.' ['.$httptest_data['name'].'] ['.$_REQUEST['httptestid'].'] '.S_HOST.' ['.$host['host'].']');
		}
		unset($_REQUEST['httptestid']);
		unset($_REQUEST['form']);
	}
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['httptestid'])){
		unset($_REQUEST['httptestid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){
		if($_REQUEST['authentication'] != HTTPTEST_AUTH_NONE){
			$http_user = htmlspecialchars($_REQUEST['http_user']);
			$http_password = htmlspecialchars($_REQUEST['http_password']);
		}
		else{
			$http_user = '';
			$http_password = '';
		}

		if(isset($_REQUEST['httptestid'])){
			$result = update_httptest($_REQUEST['httptestid'], $_REQUEST['hostid'], $_REQUEST['application'],
				$_REQUEST['name'],$_REQUEST['authentication'],$http_user,$http_password,
				$_REQUEST['delay'],$_REQUEST['status'],$_REQUEST['agent'],
				$_REQUEST['macros'],$_REQUEST['steps']);

			$httptestid = $_REQUEST['httptestid'];
			$action = AUDIT_ACTION_UPDATE;

			show_messages($result, S_SCENARIO_UPDATED, S_CANNOT_UPDATE_SCENARIO);
		}
		else{
			$httptestid = add_httptest($_REQUEST['hostid'],$_REQUEST['application'],
				$_REQUEST['name'],$_REQUEST['authentication'],$http_user,$http_password,
				$_REQUEST['delay'],$_REQUEST['status'],$_REQUEST['agent'],
				$_REQUEST['macros'],$_REQUEST['steps']);

			$result = $httptestid;
			$action = AUDIT_ACTION_ADD;
			show_messages($result, S_SCENARIO_ADDED, S_CANNOT_ADD_SCENARIO);
		}
		if($result){
			$host = get_host_by_hostid($_REQUEST['hostid']);

			add_audit($action, AUDIT_RESOURCE_SCENARIO,
				S_SCENARIO.' ['.$_REQUEST['name'].'] ['.$httptestid.'] '.S_HOST.' ['.$host['host'].']');

			unset($_REQUEST['httptestid']);
			unset($_REQUEST['form']);
		}
	}
// -------- GO ---------
	else if(($_REQUEST['go'] == 'activate') && isset($_REQUEST['group_httptestid'])){
		$go_result = false;

		$group_httptestid = $_REQUEST['group_httptestid'];
		foreach($group_httptestid as $id){
			if(!($httptest_data = get_httptest_by_httptestid($id)))	continue;

			if(activate_httptest($id)){
				$go_result = true;

				$host = get_host_by_applicationid($httptest_data['applicationid']);

				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO,
					S_SCENARIO.' ['.$httptest_data['name'].'] ['.$id.'] '.S_HOST.' ['.$host['host'].']'.
					S_SCENARIO_ACTIVATED);
			}
		}
		show_messages($go_result, S_SCENARIO_ACTIVATED, null);
	}
	else if(($_REQUEST['go'] == 'disable') && isset($_REQUEST['group_httptestid'])){
		$go_result = false;

		$group_httptestid = $_REQUEST['group_httptestid'];
		foreach($group_httptestid as $id){
			if(!($httptest_data = get_httptest_by_httptestid($id)))	continue;

			if(disable_httptest($id)){
				$go_result = true;

				$host = get_host_by_applicationid($httptest_data['applicationid']);

				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO,
					S_SCENARIO.' ['.$httptest_data['name'].'] ['.$id.'] '.S_HOST.' ['.$host['host'].']'.
					S_SCENARIO_DISABLED);
			}
		}
		show_messages($go_result, S_SCENARIO_DISABLED, null);
	}
	else if(($_REQUEST['go'] == 'clean_history') && isset($_REQUEST['group_httptestid'])){
		$go_result = false;

		$group_httptestid = $_REQUEST['group_httptestid'];
		foreach($group_httptestid as $id){
			if(!($httptest_data = get_httptest_by_httptestid($id)))	continue;

			if(delete_history_by_httptestid($id)){
				$go_result = true;
				DBexecute('update httptest set nextcheck=0'.
					/* ',lastvalue=null,lastclock=null,prevvalue=null'. // for future use */
					' where httptestid='.$id);

				$host = get_host_by_applicationid($httptest_data['applicationid']);

				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO,
					S_SCENARIO.' ['.$httptest_data['name'].'] ['.$id.'] '.S_HOST.' ['.$host['host'].']'.
					S_HISTORY_CLEARED);
			}
		}
		show_messages($go_result, S_HISTORY_CLEARED, $go_result);
	}
	else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['group_httptestid'])){
		$go_result = false;

		$group_httptestid = $_REQUEST['group_httptestid'];
		foreach($group_httptestid as $id){
			if(!($httptest_data = get_httptest_by_httptestid($id)))	continue;
			/* if($httptest_data['templateid']<>0)	continue; // for future use */
			if(delete_httptest($id)){
				$go_result = true;

				$host = get_host_by_applicationid($httptest_data['applicationid']);

				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO,
					S_SCENARIO.' ['.$httptest_data['name'].'] ['.$id.'] '.S_HOST.' ['.$host['host'].']');
			}
		}
		show_messages($go_result, S_SCENARIO_DELETED, null);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}

?>
<?php
	/* make steps with unique names */
	$_REQUEST['steps'] = get_request('steps',array());
	foreach($_REQUEST['steps'] as $s1id => $s1){
		foreach($_REQUEST['steps'] as $s2id => $s2){
			if((strcmp($s1['name'],$s2['name'])==0) && $s1id != $s2id){
				$_REQUEST['steps'][$s1id] = $_REQUEST['steps'][$s2id];
				unset($_REQUEST['steps'][$s2id]);
			}
		}
	}
	$_REQUEST['steps'] = array_merge(get_request('steps',array())); /* reinitialize keys */


	if (!isset($_REQUEST['form'])){
		//creating button "Create scenario"
		$form_button = new CForm(null, 'get');
		$form_button->addVar('hostid', $_REQUEST['hostid']);
		//if host is selected
		if(!isset($_REQUEST['form']) && ($_REQUEST['hostid'] > 0)){
			//allowing to press button
			$create_scenario_button = new CButton('form', S_CREATE_SCENARIO);
			$create_scenario_button->setEnabled('yes');
		}
		else{
			//adding additional hint to button
			$create_scenario_button = new CButton('form', S_CREATE_SCENARIO.' '.S_SELECT_HOST_FIRST);
			//and disabling it
			$create_scenario_button->setEnabled('no');
		}
		$form_button->addItem($create_scenario_button);
	}
	else {
		$form_button = null;
	}



	$http_wdgt = new CWidget();
	$http_wdgt->addPageHeader(S_CONFIGURATION_OF_WEB_MONITORING_BIG, $form_button);

	if (isset($_REQUEST['form']) && isset($_REQUEST['hostid']) && $_REQUEST['hostid']) {
		$form = new CFormTable(S_SCENARIO);
		$form->setName('form_scenario');

		if($_REQUEST['groupid'] > 0) {
			$form->addVar('groupid', $_REQUEST['groupid']);
		}
		$form->addVar('hostid', $_REQUEST['hostid']);

		if(isset($_REQUEST['httptestid'])){
			$form->addVar('httptestid', $_REQUEST['httptestid']);
		}

		$name = get_request('name', '');
		$application = get_request('application', '');
		$delay = get_request('delay', 60);
		$status = get_request('status', HTTPTEST_STATUS_ACTIVE);
		$agent = get_request('agent', '');
		$macros = get_request('macros', array());
		$steps = get_request('steps', array());

		$authentication = get_request('authentication', HTTPTEST_AUTH_NONE);
		$http_user = get_request('http_user', '');
		$http_password = get_request('http_password', '');

		if((isset($_REQUEST["httptestid"]) && !isset($_REQUEST["form_refresh"])) || isset($limited)){
			$httptest_data = DBfetch(DBselect("SELECT wt.*, a.name as application ".
				" FROM httptest wt,applications a WHERE wt.httptestid=".$_REQUEST["httptestid"].
				" AND a.applicationid=wt.applicationid"));

			$name		= $httptest_data['name'];
			$application	= $httptest_data['application'];
			$delay		= $httptest_data['delay'];
			$status		= $httptest_data['status'];
			$agent		= $httptest_data['agent'];
			$macros		= $httptest_data['macros'];

			$authentication = $httptest_data['authentication'];
			$http_user 	= $httptest_data['http_user'];
			$http_password 	= $httptest_data['http_password'];

			$steps = array();
			$db_steps = DBselect('SELECT * FROM httpstep WHERE httptestid='.$_REQUEST["httptestid"].' ORDER BY no');
			while($step_data = DBfetch($db_steps)){
				$steps[] = $step_data;
			}
		}

		$form->addRow(S_APPLICATION,array(
			new CTextBox('application', $application, 40),
			SPACE,
			new CButton('select_app',S_SELECT,
				'return PopUp("popup.php?dstfrm='.$form->getName().
				'&dstfld1=application&srctbl=applications'.
				'&srcfld1=name&only_hostid='.$_REQUEST['hostid'].'",500,600,"application");')
			));

		$form->addRow(S_NAME, new CTextBox('name', $name, 40));

		$cmbAuth = new CComboBox('authentication', $authentication, 'submit();');
		$cmbAuth->addItems(httptest_authentications());

		$form->addRow(S_AUTHENTICATION, $cmbAuth);
		if(in_array($authentication, array(HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM))){
			$form->addRow(S_USER, new CTextBox('http_user', $http_user, 32));
			$form->addRow(S_PASSWORD, new CTextBox('http_password', $http_password, 40));
		}

		$form->addRow(S_UPDATE_INTERVAL_IN_SEC, new CNumericBox('delay', $delay, 5));

		$cmbAgent = new CEditableComboBox('agent', $agent, 80);
// IE6
		$cmbAgent->addItem('Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727)',
			'Internet Explorer 6.0 on Windows XP SP2 with .NET Framework 2.0 installed');
// IE7
		$cmbAgent->addItem('Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; .NET CLR 1.1.4322; .NET CLR 2.0.50727; .NET CLR 3.0.04506.648; .NET CLR 3.5.21022)', 'Internet Explorer 7.0 on Windows XP SP3 with .NET Framework 3.5 installed');
// FF 1.5
		$cmbAgent->addItem('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.0.7) Gecko/20060909 Firefox/1.5.0.7',
			'Mozilla Firefox 1.5.0.7 on Windows XP');
		$cmbAgent->addItem('Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.0.7) Gecko/20060909 Firefox/1.5.0.7',
			'Mozilla Firefox 1.5.0.7 on Linux');
// FF 2.0
		$cmbAgent->addItem('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.18) Gecko/20081029 Firefox/2.0.0.18',
			'Mozilla Firefox 2.0.0.18 on Windows XP');
		$cmbAgent->addItem('Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.18) Gecko/20081029 Firefox/2.0.0.18',
			'Mozilla Firefox 2.0.0.18 on Linux');
// FF 3.0
		$cmbAgent->addItem('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1',
			'Mozilla Firefox 3.0.1 on Windows XP');
		$cmbAgent->addItem('Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1',
			'Mozilla Firefox 3.0.1 on Linux');
// OP 9.0
		$cmbAgent->addItem('Opera/9.02 (Windows NT 5.1; U; en)',
			'Opera 9.02 on Windows XP');
		$cmbAgent->addItem('Opera/9.02 (X11; Linux i686; U; en)',
			'Opera 9.02 on Linux');
// OP 9.6
		$cmbAgent->addItem('Opera/9.61 (Windows NT 5.1; U; en) Presto/2.1.1',
			'Opera 9.61 on Windows XP');
		$cmbAgent->addItem('Opera/9.61 (X11; Linux i686; U; en) Presto/2.1.1',
			'Opera 9.61 on Linux');
// SF 3.1
		$cmbAgent->addItem('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.19 (KHTML, like Gecko) Version/3.1.2 Safari/525.21',
			'Safari 3.1.2 on Windows XP');
		$cmbAgent->addItem('Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_5_4; en-us) AppleWebKit/527.2+ (KHTML, like Gecko) Version/3.1.2 Safari/525.20.1',
			'Safari 3.1.2 on Intel Mac OS X 10.5.4');
		$cmbAgent->addItem('Mozilla/5.0 (iPhone; U; CPU iPhone OS 2_1 like Mac OS X; fr-fr) AppleWebKit/525.18.1 (KHTML, like Gecko) Mobile/5F136',
			'Safari on iPhone');

		$cmbAgent->addItem('Lynx/2.8.4rel.1 libwww-FM/2.14',
			'Lynx 2.8.4rel.1 on Linux');
		$cmbAgent->addItem('Googlebot/2.1 (+http://www.google.com/bot.html)',
			'Googlebot');
		$form->addRow(S_AGENT, $cmbAgent);

		$cmbStatus = new CComboBox("status", $status);
		foreach(array(HTTPTEST_STATUS_ACTIVE, HTTPTEST_STATUS_DISABLED) as $st)
			$cmbStatus->addItem($st, httptest_status2str($st));
		$form->addRow(S_STATUS,$cmbStatus);

		$form->addRow(S_VARIABLES, new CTextArea('macros', $macros, 84, 5));

		$tblSteps = new CTableInfo();
		$tblSteps->setHeader(array('',S_NAME,S_TIMEOUT,S_URL,S_REQUIRED,S_STATUS,S_SORT));
		if(count($steps) > 0){
			$first = min(array_keys($steps));
			$last = max(array_keys($steps));
		}
		foreach($steps as $stepid => $s){
			if(!isset($s['name']))		$s['name'] = '';
			if(!isset($s['timeout']))	$s['timeout'] = 15;
			if(!isset($s['url']))		$s['url'] = '';
			if(!isset($s['posts']))		$s['posts'] = '';
			if(!isset($s['required']))	$s['required'] = '';

			$up = null;
			if($stepid != $first){
				$up = new CSpan(S_UP,'link');
				$up->onClick("return create_var('".$form->getName()."','move_up',".$stepid.", true);");
			}

			$down = null;
			if($stepid != $last){
				$down = new CLink(S_DOWN,'link');
				$down->onClick("return create_var('".$form->getName()."','move_down',".$stepid.", true);");
			}

			$name = new CSpan($s['name'],'link');
			$name->onClick('return PopUp("popup_httpstep.php?dstfrm='.$form->getName().
				'&list_name=steps&stepid='.$stepid.
				url_param($s['name'],false,'name').
				url_param($s['timeout'],false,'timeout').
				url_param($s['url'],false,'url').
				url_param($s['posts'],false,'posts').
				url_param($s['required'],false,'required').
				url_param($s['status_codes'],false,'status_codes').
				'");');

			if(zbx_strlen($s['url']) > 70){
				$url = new CTag('span','yes', substr($s['url'],0,35).SPACE.'...'.SPACE.substr($s['url'],zbx_strlen($s['url'])-25,25));
				$url->setHint($s['url']);
			}
			else{
				$url = $s['url'];
			}

			$tblSteps->addRow(array(
				(new CCheckBox('sel_step[]',null,null,$stepid)),
				$name,
				$s['timeout'].SPACE.S_SEC_SMALL,
				$url,
				htmlspecialchars($s['required']),
				$s['status_codes'],
				array($up, isset($up) && isset($down) ? SPACE : null, $down)
				));
		}
		$form->addVar('steps', $steps);

		$form->addRow(S_STEPS, array(
			(count($steps) > 0) ? array ($tblSteps, BR()) : null ,
			new CButton('add_step',S_ADD,
				'return PopUp("popup_httpstep.php?dstfrm='.$form->getName().'");'),
			(count($steps) > 0) ? new CButton('del_sel_step',S_DELETE_SELECTED) : null
			));

		$form->addItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["httptestid"])){
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButton("clone",S_CLONE));
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButtonDelete(S_DELETE_SCENARIO_Q,
				url_param("form").url_param("httptestid").url_param('hostid')));
		}
		$form->addItemToBottomRow(SPACE);
		$form->addItemToBottomRow(new CButtonCancel());

		$http_wdgt->addItem($form);
	}
	else{
		$form = new CForm(null, 'get');

		$form->addItem(array(S_GROUP.SPACE,$pageFilter->getGroupsCB()));
		$form->addItem(array(SPACE.S_HOST.SPACE,$pageFilter->getHostsCB()));

		$http_wdgt->addHeader(S_SCENARIOS_BIG, $form);

		$link = array('[ ',
				new CLink($showdisabled ? S_HIDE_DISABLED_SCENARIOS: S_SHOW_DISABLED_SCENARIOS,'?showdisabled='.($showdisabled ? 0 : 1),NULL),
				' ]');
		$http_wdgt->addHeader(SPACE, $link);

// TABLE
		$form = new CForm();
		$form->setMethod('get');

		$form->setName('scenarios');
		$form->addVar('hostid',$_REQUEST['hostid']);

		if(isset($show_all_apps))
			$link = new CLink(new CImg('images/general/opened.gif'),'?close=1'.url_param('groupid').url_param('hostid'));
		else
			$link = new CLink(new CImg('images/general/closed.gif'),'?open=1'.url_param('groupid').url_param('hostid'));

		$table  = new CTableInfo();
		$table->setHeader(array(
			new CCheckBox('all_httptests',null, "checkAll('".$form->getName()."','all_httptests','group_httptestid');"),
			is_show_all_nodes() ? make_sorting_header(S_NODE,'h.hostid') : null,
			($_REQUEST['hostid']==0) ? make_sorting_header(S_HOST,'host'):NULL,
			make_sorting_header(array($link, SPACE, S_NAME),'name'),
			S_NUMBER_OF_STEPS,
			S_UPDATE_INTERVAL,
			make_sorting_header(S_STATUS,'status')));

		$db_apps = array();

		$http_wdgt->addItem(BR());

		$sql_where = '';
		if ($_REQUEST['hostid'] > 0) {
			$sql_where = ' AND h.hostid='.$_REQUEST['hostid'];
		}
		$sql = 'SELECT DISTINCT h.host,h.hostid,a.*'.
				' FROM applications a,hosts h'.
				' WHERE a.hostid=h.hostid'.
					$sql_where.
					' AND '.DBcondition('h.hostid', $available_hosts);
		$db_app_res = DBselect($sql);
		while ($db_app = DBfetch($db_app_res)) {
			$db_app['scenarios_cnt'] = 0;

			$db_apps[$db_app['applicationid']] = $db_app;
		}

		$db_httptests = array();
		$sql = 'SELECT wt.*,a.name AS application,h.host,h.hostid'.
				' FROM httptest wt,applications a,hosts h'.
				' WHERE wt.applicationid=a.applicationid'.
					' AND a.hostid=h.hostid'.
					' AND '.DBcondition('a.applicationid', array_keys($db_apps)).
					($showdisabled == 0 ? ' AND wt.status='.HTTPTEST_STATUS_ACTIVE : '');
		$db_httptests_res = DBselect($sql);
		while ($httptest_data = DBfetch($db_httptests_res)) {
			$db_apps[$httptest_data['applicationid']]['scenarios_cnt']++;

			$httptest_data['step_count'] = null;
			$db_httptests[$httptest_data['httptestid']] = $httptest_data;
		}

		$sql = 'SELECT hs.httptestid,COUNT(hs.httpstepid) AS cnt'.
				' FROM httpstep hs'.
				' WHERE '.DBcondition('hs.httptestid', array_keys($db_httptests)).
				' GROUP BY hs.httptestid';
		$httpstep_res = DBselect($sql);
		while ($step_count = DBfetch($httpstep_res)) {
			$db_httptests[$step_count['httptestid']]['step_count'] = $step_count['cnt'];
		}

		order_result($db_httptests, getPageSortField('host'), getPageSortOrder());

		$tab_rows = array();
		foreach ($db_httptests as $httptestid => $httptest_data) {
			$db_app = $db_apps[$httptest_data['applicationid']];

			if (!isset($tab_rows[$db_app['applicationid']])) {
				$tab_rows[$db_app['applicationid']] = array();
			}

			if(!uint_in_array($db_app['applicationid'], $_REQUEST['applications']) && !isset($show_all_apps)) {
				continue;
			}

			$status = new CCol(new CLink(httptest_status2str($httptest_data['status']),
				'?group_httptestid[]='.$httptest_data['httptestid'].
				'&go='.($httptest_data['status'] ? 'activate' : 'disable'),
				httptest_status2style($httptest_data['status'])));


			$tab_rows[$db_app['applicationid']][] = array(
				new CCheckBox('group_httptestid['.$httptest_data['httptestid'].']', null, null, $httptest_data['httptestid']),
				is_show_all_nodes() ? SPACE : NULL,
				($_REQUEST['hostid'] > 0) ? null : $db_app['host'],
				new CLink($httptest_data['name'], '?form=update'.'&httptestid='.$httptest_data['httptestid'].
						'&hostid='.$db_app['hostid'].url_param('groupid')),
				$httptest_data['step_count'],
				$httptest_data['delay'],
				$status
			);
		}

		foreach ($tab_rows as $appid => $app_rows) {
			$db_app = $db_apps[$appid];

			if (uint_in_array($db_app['applicationid'],$_REQUEST['applications']) || isset($show_all_apps)) {
				$link = new CLink(new CImg('images/general/opened.gif'),
					'?close=1&applicationid='.$db_app['applicationid'].
							url_param('groupid').url_param('hostid').url_param('applications').url_param('select'));
			}
			else {
				$link = new CLink(new CImg('images/general/closed.gif'),
					'?open=1&applicationid='.$db_app['applicationid'].
							url_param('groupid').url_param('hostid').url_param('applications').url_param('select'));
			}

			$col = new CCol(array($link, SPACE, bold($db_app['name']), SPACE.'('.$db_app['scenarios_cnt'].SPACE.S_SCENARIOS.')'));
			$col->setColSpan(6);

			$table->addRow(array(
				get_node_name_by_elid($db_app['applicationid']),
				$col
			));

			foreach ($app_rows as $row) {
				$table->addRow($row);
			}
		}

//----- GO ------
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('activate',S_ACTIVATE_SELECTED);
		$goOption->setAttribute('confirm',S_ENABLE_SELECTED_WEB_SCENARIOS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable',S_DISABLE_SELECTED);
		$goOption->setAttribute('confirm',S_DISABLE_SELECTED_WEB_SCENARIOS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('clean_history',S_CLEAR_HISTORY_FOR_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_HISTORY_SELECTED_WEB_SCENARIOS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_WEB_SCENARIOS_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO.' (0)');
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "group_httptestid";');

		$table->setFooter(new CCol(array($goBox, $goButton)));
//----
		$form->addItem($table);

		$http_wdgt->addItem($form);
	}

	$http_wdgt->show();


include_once('include/page_footer.php');
?>
