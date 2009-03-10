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
	require_once "include/config.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/httptest.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_STATUS_OF_WEB_MONITORING";
	$page["file"] = "httpmon.php";
	$page['hist_arg'] = array('open','groupid','hostid');
	
	define('ZBX_PAGE_DO_REFRESH', 1);

include_once "include/page_header.php";

?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"applications"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"applicationid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"close"=>		array(T_ZBX_INT, O_OPT,	null,	IN("1"),	null),
		"open"=>		array(T_ZBX_INT, O_OPT,	null,	IN("1"),	null),

		"groupid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,	null),
		"hostid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,	null),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			'isset({favid})'),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),

	);

	check_fields($fields);

/* AJAX	*/
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.httpmon.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------
	validate_sort_and_sortorder('wt.name',ZBX_SORT_DOWN);

	$options = array('allow_all_hosts','monitored_hosts','with_monitored_httptests');
	if(!$ZBX_WITH_SUBNODES)	array_push($options,'only_current_node');
	
//SDI($_REQUEST['groupid'].' : '.$_REQUEST['hostid']);
	$params = array();
	foreach($options as  $option) $params[$option] = 1;
	$PAGE_GROUPS = get_viewed_groups(PERM_READ_ONLY, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_ONLY, $PAGE_GROUPS['selected'], $params);
//SDI($_REQUEST['groupid'].' : '.$_REQUEST['hostid']);
	validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);
//SDI($_REQUEST['groupid'].' : '.$_REQUEST['hostid']);
?>
<?php
	$_REQUEST['applications'] = get_request('applications',get_profile('web.httpmon.applications',array(),PROFILE_TYPE_ARRAY_ID));

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

	/* limit opened application count */
	while(count($_REQUEST['applications']) > 25){
		array_shift($_REQUEST['applications']);
	}

	update_profile('web.httpmon.applications',$_REQUEST['applications'],PROFILE_TYPE_ARRAY_ID);
?>
<?php

	$p_elements = array();
	
// Table HEADER
	$form = new CForm();
	$form->setMethod('get');

	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];

	$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
	$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');

	foreach($PAGE_GROUPS['groups'] as $groupid => $name){
		$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid).$name);
	}
	foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
		$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid).$name);
	}
	
	$form->addItem(array(S_GROUP.SPACE,$cmbGroups));
	$form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
			
	$p_elements[] = get_table_header(SPACE, $form);

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
	$table->SetHeader(array(
		is_show_subnodes() ? make_sorting_link(S_NODE,'h.hostid') : null,
		$_REQUEST['hostid'] ==0 ? make_sorting_link(S_HOST,'h.host') : NULL,
		array($link, SPACE, make_sorting_link(S_NAME,'wt.name')),
		S_NUMBER_OF_STEPS,
		S_STATE,
		S_LAST_CHECK,
		S_STATUS));

	$any_app_exist = false;
	
	$db_apps = array();
	$db_appids = array();
	
	$sql_where = '';
	if($_REQUEST['hostid']>0){
		$sql_where = ' AND h.hostid='.$_REQUEST['hostid'];	
	}
	
	$sql = 'SELECT DISTINCT h.host,h.hostid,a.* '.
			' FROM applications a,hosts h '.
			' WHERE a.hostid=h.hostid '.
				$sql_where.
				' AND '.DBcondition('h.hostid',$available_hosts).
			' ORDER BY a.name,a.applicationid,h.host';
//SDI($sql);
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
			' AND wt.status <> 1 '.
		order_by('wt.name','h.host');
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
		array_push($name, new CLink($httptest_data['name'],'httpdetails.php?httptestid='.$httptest_data['httptestid'],'action'));

		if(isset($httptest_data['lastcheck']))
			$lastcheck = date(S_DATE_FORMAT_YMDHMS,$httptest_data['lastcheck']);
		else
			$lastcheck = new CCol('-', 'center');

		if( HTTPTEST_STATE_BUSY == $httptest_data['curstate'] ){
			$step_data = get_httpstep_by_no($httptest_data['httptestid'], $httptest_data['curstep']);
			$state = S_IN_CHECK.' "'.$step_data['name'].'" ['.$httptest_data['curstep'].' '.S_OF_SMALL.' '.$httptest_data['step_cout'].']';

			$status['msg'] = S_IN_PROGRESS;
			$status['style'] = 'orange';
		}
		else if( HTTPTEST_STATE_IDLE == $httptest_data['curstate'] ){
			$state = S_IDLE_TILL.' '.date(S_DATE_FORMAT_YMDHMS,$httptest_data['nextcheck']);

			if($httptest_data['lastfailedstep'] > 0){
				$step_data = get_httpstep_by_no($httptest_data['httptestid'], $httptest_data['lastfailedstep']);
				$status['msg'] = S_FAILED_ON.' "'.$step_data['name'].'" '.
					'['.$httptest_data['lastfailedstep'].' '.S_OF_SMALL.' '.$httptest_data['step_cout'].'] '.
					SPACE.S_ERROR.': '.$httptest_data['error'];
				$status['style'] = 'disabled';
			}
			else{
				$status['msg'] = S_OK_BIG;
				$status['style'] = 'enabled';
			}
		}
		else{
			$state = S_IDLE_TILL.' '.date(S_DATE_FORMAT_YMDHMS,$httptest_data['nextcheck']);
			$status['msg'] = S_UNKNOWN;
			$status['style'] = 'unknown';
		}

		array_push($app_rows, new CRow(array(
			is_show_subnodes()?SPACE:NULL,
			($_REQUEST['hostid']>0)?NULL:SPACE,
			array(str_repeat(SPACE,6), $name),
			$httptest_data['step_cout'],
			$state,
			$lastcheck,
			new CSpan($status['msg'], $status['style'])
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
		$col->SetColSpan(6);

		$table->addRow(array(
				get_node_name_by_elid($db_app['applicationid']),
				($_REQUEST['hostid'] > 0)?NULL:$db_app['host'],
				$col
			));

		$any_app_exist = true;
	
		foreach($app_rows as $row)
			$table->addRow($row);
	}

	$form->addItem($table);
	
	$p_elements[] = $form;
	
	$latest_hat = create_hat(
			S_STATUS_OF_WEB_MONITORING_BIG,
			$p_elements,
			null,
			'hat_httpmon',
			get_profile('web.httpmon.hats.hat_httpmon.state',1)
	);

	$latest_hat->Show();
?>
<?php

include_once('include/page_footer.php');

?>