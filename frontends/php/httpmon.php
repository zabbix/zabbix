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
		"hostid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,	null)
	);

	check_fields($fields);
	validate_sort_and_sortorder();

	$options = array('allow_all_hosts','monitored_hosts');//,'always_select_first_host'

	$_REQUEST['groupid'] = get_request('groupid',get_profile('web.latest.groupid',-1));
	if($_REQUEST['groupid'] == -1) array_push($options,'always_select_first_host');
	
//	validate_group_with_host(PERM_READ_ONLY,array('allow_all_hosts','always_select_first_host','monitored_hosts'));
?>
<?php
	$_REQUEST["applications"] = get_request("applications",get_profile("web.httpmon.applications",array()),PROFILE_TYPE_ARRAY);

	if(isset($_REQUEST["open"])){
		if(!isset($_REQUEST["applicationid"])){
			$_REQUEST["applications"] = array();
			$show_all_apps = 1;
		}
		else if(!uint_in_array($_REQUEST["applicationid"],$_REQUEST["applications"])){
			array_push($_REQUEST["applications"],$_REQUEST["applicationid"]);
		}
		
	} 
	else if(isset($_REQUEST["close"])){
		if(!isset($_REQUEST["applicationid"])){
			$_REQUEST["applications"] = array();
		}
		else if(($i=array_search($_REQUEST["applicationid"], $_REQUEST["applications"])) !== FALSE){
			unset($_REQUEST["applications"][$i]);
		}
	}

	/* limit opened application count */
	while(count($_REQUEST["applications"]) > 25){
		array_shift($_REQUEST["applications"]);
	}

	update_profile("web.httpmon.applications",$_REQUEST["applications"],PROFILE_TYPE_ARRAY);
?>
<?php
// Table HEADER
	$form = new CForm();
	$form->SetMethod('get');
	
	$cmbGroup = new CComboBox("groupid",null,"submit();");
	$cmbGroup->AddItem(0,S_ALL_SMALL);

	$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_LIST);
	$accessible_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST);

	$result=DBselect('SELECT DISTINCT g.groupid,g.name '.
		' FROM groups g, hosts_groups hg, hosts h, applications a, httptest ht '.
		' WHERE g.groupid in ('.$accessible_groups.') '.
			' AND hg.groupid=g.groupid '.
			' AND h.status='.HOST_STATUS_MONITORED.
			' AND h.hostid=a.hostid '.
			' AND hg.hostid=h.hostid'.
			' AND ht.applicationid=a.applicationid '.
			' AND ht.status='.HTTPTEST_STATUS_ACTIVE.
		' ORDER BY g.name');
	while($row=DBfetch($result)){
		if($_REQUEST['groupid'] == -1){
			$_REQUEST['groupid'] = $row['groupid'];
		}
		$cmbGroup->AddItem(
			$row['groupid'],
			get_node_name_by_elid($row['groupid']).$row['name'],
			($_REQUEST['groupid'] == $row['groupid'])?1:0
			);
	}

//	Supposed to be here
	validate_group_with_host(PERM_READ_ONLY,$options);

	$form->AddItem(S_GROUP.SPACE);
	$form->AddItem($cmbGroup);

	$_REQUEST["hostid"] = get_request("hostid",0);
	$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit();");
	$cmbHosts->AddItem(0,S_ALL_SMALL);
	
	if(isset($_REQUEST["groupid"]) && $_REQUEST["groupid"] > 0){
		$sql='SElECT DISTINCT h.hostid,h.host '.
			' FROM hosts_groups hg, hosts h,applications a,httptest ht '.
			' WHERE h.status='.HOST_STATUS_MONITORED.
				' AND h.hostid=a.hostid '.
				' AND hg.hostid=h.hostid '.
				' AND hg.groupid='.$_REQUEST["groupid"].
				' AND h.hostid in ('.$accessible_hosts.') '.
				' AND a.applicationid=ht.applicationid '.
				' AND ht.status='.HTTPTEST_STATUS_ACTIVE.
			' GROUP BY h.hostid,h.host'.
			' ORDER BY h.host';
	}
	else{
		$sql='SELECT DISTINCT h.hostid,h.host '.
			' FROM hosts h,applications a,httptest ht '.
			' WHERE h.status='.HOST_STATUS_MONITORED.
				' AND h.hostid=a.hostid '.
				' AND ht.status='.HTTPTEST_STATUS_ACTIVE.
				' AND ht.applicationid=a.applicationid '.
				' AND h.hostid in ('.$accessible_hosts.') '.
			' GROUP BY h.hostid,h.host '.
			' ORDER BY h.host';
	}

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		$cmbHosts->AddItem(
			$row['hostid'],
			get_node_name_by_elid($row['hostid']).$row['host']
			);
	}

	$form->AddItem(SPACE.S_HOST.SPACE);
	$form->AddItem($cmbHosts);
	
	show_table_header(S_STATUS_OF_WEB_MONITORING_BIG, $form);

// TABLE
	$form = new CForm();
	$form->SetMethod('get');
	
	$form->SetName('scenarios');
	$form->AddVar('hostid',$_REQUEST["hostid"]);

	if(isset($show_all_apps))
		$link = new CLink(new CImg("images/general/opened.gif"),
			"?close=1".
			url_param("groupid").url_param("hostid"));
	else
		$link = new CLink(new CImg("images/general/closed.gif"),
			"?open=1".
			url_param("groupid").url_param("hostid"));

	$table  = new CTableInfo();
	$table->SetHeader(array(
		is_show_subnodes() ? make_sorting_link(S_NODE,'h.hostid') : null,
		$_REQUEST["hostid"] ==0 ? make_sorting_link(S_HOST,'h.host') : NULL,
		array($link, SPACE, make_sorting_link(S_NAME,'wt.name')),
		S_NUMBER_OF_STEPS,
		S_STATE,
		S_LAST_CHECK,
		S_STATUS));

	$any_app_exist = false;

	if($_REQUEST["hostid"] > 0)
		$compare_host = " AND h.hostid=".$_REQUEST["hostid"];
	else
		$compare_host = " AND h.hostid in (".$accessible_hosts.") ";
		
	$db_applications = DBselect('SELECT DISTINCT h.host,h.hostid,a.* '.
							' FROM applications a,hosts h '.
							' WHERE a.hostid=h.hostid '.$compare_host.
							order_by('a.applicationid,h.host,h.hostid','a.name')
		);
	while($db_app = DBfetch($db_applications))
	{
		$db_httptests = DBselect('select wt.*,a.name as application,h.host,h.hostid from httptest wt '.
			' left join applications a on wt.applicationid=a.applicationid '.
			' left join hosts h on h.hostid=a.hostid'.
			' where a.applicationid='.$db_app["applicationid"].' AND wt.status <> 1'.
			order_by('wt.name','h.host'));

		$app_rows = array();
		$httptest_cnt = 0;
		while($httptest_data = DBfetch($db_httptests))
		{
			++$httptest_cnt;
			if(!uint_in_array($db_app["applicationid"],$_REQUEST["applications"]) && !isset($show_all_apps)) continue;

			$name = array();

			array_push($name, new CLink($httptest_data["name"],"httpdetails.php?httptestid=".$httptest_data['httptestid'],'action'));
	
			$step_cout = DBfetch(DBselect('select count(*) as cnt from httpstep where httptestid='.$httptest_data["httptestid"]));
			$step_cout = $step_cout['cnt'];

			if(isset($httptest_data["lastcheck"]))
				$lastcheck = date(S_DATE_FORMAT_YMDHMS,$httptest_data["lastcheck"]);
			else
				$lastcheck = new CCol('-', 'center');

			if( HTTPTEST_STATE_BUSY == $httptest_data['curstate'] )
			{
				$step_data = get_httpstep_by_no($httptest_data['httptestid'], $httptest_data['curstep']);
				$state = S_IN_CHECK.' "'.$step_data['name'].'" ['.$httptest_data['curstep'].' '.S_OF_SMALL.' '.$step_cout.']';

				$status['msg'] = S_IN_PROGRESS;
				$status['style'] = 'unknown';
			}
			else if( HTTPTEST_STATE_IDLE == $httptest_data['curstate'] )
			{
				$state = S_IDLE_TILL." ".date(S_DATE_FORMAT_YMDHMS,$httptest_data['nextcheck']);

				if($httptest_data['lastfailedstep'] > 0)
				{
					$step_data = get_httpstep_by_no($httptest_data['httptestid'], $httptest_data['lastfailedstep']);
					$status['msg'] = S_FAILED_ON.' "'.$step_data['name'].'" '.
						'['.$httptest_data['lastfailedstep'].' '.S_OF_SMALL.' '.$step_cout.'] '.
						' '.S_ERROR.': '.$httptest_data['error'];
					$status['style'] = 'disabled';
				}
				else
				{
					$status['msg'] = S_OK_BIG;
					$status['style'] = 'enabled';
				}
			}
			else
			{
				$state = S_IDLE_TILL." ".date(S_DATE_FORMAT_YMDHMS,$httptest_data['nextcheck']);
				$status['msg'] = S_UNKNOWN;
				$status['style'] = 'unknown';
			}

			array_push($app_rows, new CRow(array(
				is_show_subnodes() ? SPACE : null,
				$_REQUEST["hostid"] > 0 ? NULL : SPACE,
				array(str_repeat(SPACE,6), $name),
				$step_cout,
				$state,
				$lastcheck,
				new CSpan($status['msg'], $status['style'])
				)));
		}
		if($httptest_cnt > 0)
		{
			if(uint_in_array($db_app["applicationid"],$_REQUEST["applications"]) || isset($show_all_apps))
				$link = new CLink(new CImg("images/general/opened.gif"),
					"?close=1&applicationid=".$db_app["applicationid"].
					url_param("groupid").url_param("hostid").url_param("applications").
					url_param("select"));
			else
				$link = new CLink(new CImg("images/general/closed.gif"),
					"?open=1&applicationid=".$db_app["applicationid"].
					url_param("groupid").url_param("hostid").url_param("applications").
					url_param("select"));

			$col = new CCol(array($link,SPACE,bold($db_app["name"]),
				SPACE."(".$httptest_cnt.SPACE.S_SCENARIOS.")"));

			$col->SetColSpan(6);

			$table->AddRow(array(
					get_node_name_by_elid($db_app['applicationid']),
					$_REQUEST["hostid"] > 0 ? NULL : $db_app["host"],
					$col
				));

			$any_app_exist = true;
		
			foreach($app_rows as $row)
				$table->AddRow($row);
		}
	}

	$form->AddItem($table);
	$form->Show();

include_once "include/page_footer.php"
?>