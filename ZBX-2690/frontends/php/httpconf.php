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
		'authentication'=>	array(T_ZBX_INT, O_OPT,  null,  IN('0,1'),'isset({save})'),
		'http_user'=>		array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,'isset({save}) && isset({authentication}) && ({authentication}=='.HTTPTEST_AUTH_BASIC.')'),
		'http_password'=>	array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,'isset({save}) && isset({authentication}) && ({authentication}=='.HTTPTEST_AUTH_BASIC.')'),

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
	validate_sort_and_sortorder('wt.name',ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go','none');
?>
<?php
	$showdisabled = get_request('showdisabled', 0);

	$options = array(
		'groups' => array(
			'not_proxy_hosts' => 1,
			'editable' => 1,
		),
		'hosts' => array(
			'templated_hosts' => 1,
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
		/*
		$delay_flex = get_request('delay_flex',array());
		$db_delay_flex = '';
		foreach($delay_flex as $val)
			$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
		$db_delay_flex = trim($db_delay_flex,';');
		// for future use */

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

	$form = new CForm();
	$form->setMethod('get');

	$form->addVar('hostid',$_REQUEST['hostid']);

	if(!isset($_REQUEST['form']) && ($_REQUEST['hostid'] > 0))
		$form->addItem(new CButton('form',S_CREATE_SCENARIO));

	$http_wdgt = new CWidget();
	$http_wdgt->addPageHeader(S_CONFIGURATION_OF_WEB_MONITORING_BIG, $form);

	$db_hosts=DBselect('select hostid from hosts where '.DBin_node('hostid'));
	if(isset($_REQUEST['form'])&&isset($_REQUEST['hostid']) && DBfetch($db_hosts)){
// FORM
		$http_wdgt->addItem(insert_httptest_form());
	}
	else {
// Table HEADER

		$form = new CForm(null, 'get');

		$form->addItem(array(S_GROUP.SPACE,$pageFilter->getGroupsCB()));
		$form->addItem(array(SPACE.S_HOST.SPACE,$pageFilter->getHostsCB()));

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

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
			($_REQUEST['hostid']==0) ? make_sorting_header(S_HOST,'h.host'):NULL,
			make_sorting_header(array($link, SPACE, S_NAME),'wt.name'),
			S_NUMBER_OF_STEPS,
			S_UPDATE_INTERVAL,
			make_sorting_header(S_STATUS,'wt.status')));

		$any_app_exist = false;

		$db_apps = array();
		$db_appids = array();

/* sorting
		order_page_result($applications, 'name');

// PAGING UPPER
		$paging = getPagingLine($applications);
		$http_wdgt->addItem($paging);
//-------*/
		$http_wdgt->addItem(BR());


		$sql_where = '';
		if($_REQUEST['hostid']>0){
			$sql_where = ' AND h.hostid='.$_REQUEST['hostid'];
		}

		$sql = 'SELECT DISTINCT h.host,h.hostid,a.* '.
				' FROM applications a,hosts h '.
				' WHERE a.hostid=h.hostid '.
					$sql_where.
					' AND '.DBcondition('h.hostid',$available_hosts).
				order_by('a.applicationid,h.host,h.hostid','a.name');
		$db_app_res = DBselect($sql);
		while($db_app = DBfetch($db_app_res)){
			$db_app['scenarios_cnt'] = 0;

			$db_apps[$db_app['applicationid']] = $db_app;
			$db_appids[$db_app['applicationid']] = $db_app['applicationid'];
		}


		$db_httptests = array();
		$db_httptestids = array();

		$sql = 'SELECT wt.*,a.name as application, h.host,h.hostid '.
			' FROM httptest wt '.
				' LEFT JOIN applications a on wt.applicationid=a.applicationid '.
				' LEFT JOIN hosts h on h.hostid=a.hostid '.
			' WHERE '.DBcondition('a.applicationid',$db_appids).
				($showdisabled==0?' AND wt.status <> 1':'').
			order_by('wt.name,wt.status','h.host');
//SDI($sql);
		$db_httptests_res = DBselect($sql);
		while($httptest_data = DBfetch($db_httptests_res)){
			$httptest_data['step_cout'] = null;
			$db_apps[$httptest_data['applicationid']]['scenarios_cnt']++;

			$db_httptests[$httptest_data['httptestid']] = $httptest_data;
			$db_httptestids[$httptest_data['httptestid']] = $httptest_data['httptestid'];
		}

		$sql = 'SELECT hs.httptestid, COUNT(hs.httpstepid) as cnt '.
				' FROM httpstep hs'.
				' WHERE '.DBcondition('hs.httptestid',$db_httptestids).
				' GROUP BY hs.httptestid';
//SDI($sql);
		$httpstep_res = DBselect($sql);
		while($step_cout = DBfetch($httpstep_res)){
			$db_httptests[$step_cout['httptestid']]['step_cout'] = $step_cout['cnt'];
		}

		$tab_rows = array();
		foreach($db_httptests as $httptestid => $httptest_data){
			$db_app = &$db_apps[$httptest_data['applicationid']];

			if(!isset($tab_rows[$db_app['applicationid']])) $tab_rows[$db_app['applicationid']] = array();
			$app_rows = &$tab_rows[$db_app['applicationid']];

			if(!uint_in_array($db_app['applicationid'],$_REQUEST['applications']) && !isset($show_all_apps)) continue;

			$name = array();
			array_push($name, new CLink($httptest_data['name'],'?form=update'.
									'&httptestid='.$httptest_data['httptestid'].
									'&hostid='.$db_app['hostid'].
									url_param('groupid'),
								NULL));

			$status=new CCol(new CLink(httptest_status2str($httptest_data['status']),
					'?group_httptestid[]='.$httptest_data['httptestid'].
					'&go='.($httptest_data['status']?'activate':'disable'),
					httptest_status2style($httptest_data['status'])));


			$chkBox = new CCheckBox('group_httptestid['.$httptest_data['httptestid'].']',null,null,$httptest_data['httptestid']);

			$step_cout = DBfetch(DBselect('select count(*) as cnt from httpstep where httptestid='.$httptest_data['httptestid']));
			$step_cout = $step_cout['cnt'];

			array_push($app_rows,
				new CRow(array(
					$chkBox,
					is_show_all_nodes()?SPACE:NULL,
					($_REQUEST['hostid']>0) ? null : $db_app['host'],
					array(str_repeat(SPACE,4), $name),
					$step_cout,
					$httptest_data['delay'],
					$status
				)));
		}
		unset($app_rows);
		unset($db_app);

		foreach($tab_rows as $appid => $app_rows){
			$db_app = &$db_apps[$appid];

			if(uint_in_array($db_app['applicationid'],$_REQUEST['applications']) || isset($show_all_apps))
				$link = new CLink(new CImg('images/general/opened.gif'),
					'?close=1&applicationid='.$db_app['applicationid'].
					url_param('groupid').url_param('hostid').url_param('applications').
					url_param('select'));
			else
				$link = new CLink(new CImg('images/general/closed.gif'),
					'?open=1&applicationid='.$db_app['applicationid'].
					url_param('groupid').url_param('hostid').url_param('applications').
					url_param('select'));

			$col = new CCol(array($link,SPACE,bold($db_app['name']),SPACE.'('.$db_app['scenarios_cnt'].SPACE.S_SCENARIOS.')'));
			$col->setColSpan(6);

			$table->addRow(array(
					get_node_name_by_elid($db_app['applicationid']),
					$col
				));

			$any_app_exist = true;

			foreach($app_rows as $row)
				$table->addRow($row);
		}

// PAGING FOOTER
//		$table->addRow(new CCol($paging));
//		$http_wdgt->addItem($paging);
//---------

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
