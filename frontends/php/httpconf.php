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

        $page["title"] = "S_CONFIGURATION_OF_WEB_MONITORING";
        $page["file"] = "httpconf.php";

include_once "include/page_header.php";

?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"applications"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"applicationid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"close"=>		array(T_ZBX_INT, O_OPT,	null,	IN("1"),	null),
		"open"=>		array(T_ZBX_INT, O_OPT,	null,	IN("1"),	null),

		"groupid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,null),
		"hostid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'isset({form})||isset({save})'),

		"httptestid"=>	array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,'{form}=="update"'),
		"application"=>	array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,'isset({save})'),
		"name"=>	array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY.KEY_PARAM(),'isset({save})'),
		"delay"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,86400),'isset({save})'),
		"status"=>	array(T_ZBX_INT, O_OPT,  null,  IN('0,1'),'isset({save})'),
		"agent"=>	array(T_ZBX_STR, O_OPT,  null,	null,'isset({save})'),
		"macros"=>	array(T_ZBX_STR, O_OPT,  null,	null,'isset({save})'),
		"steps"=>	array(T_ZBX_STR, O_OPT,  null,	null,'isset({save})'),
		
		"new_httpstep"=>	array(T_ZBX_STR, O_OPT,  null,	null,null),

		"move_up"=>		array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),
		"move_down"=>		array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),
		
		"sel_step"=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65534), null),

		"group_httptestid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),
		
		"showdisabled"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	null),

		"group_task"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"clone"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"del_sel_step"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	null,	null,	null)
	);

	$_REQUEST["showdisabled"] = get_request("showdisabled", get_profile("web.httpconf.showdisabled", 0));
	
	check_fields($fields);

	$showdisabled = get_request("showdisabled", 0);
	
	$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,get_current_nodeid());

	if(isset($_REQUEST['hostid']) && !in_array($_REQUEST['hostid'], explode(',',$accessible_hosts)))
	{
		unset($_REQUEST['hostid']);
	}
		
	validate_group_with_host(PERM_READ_WRITE,array("always_select_first_host","only_current_node"),'web.last.conf.groupid', 'web.last.conf.hostid');

	update_profile("web.httpconf.showdisabled",$showdisabled);
?>
<?php
	$_REQUEST["applications"] = get_request("applications",get_profile("web.httpconf.applications",array()),PROFILE_TYPE_ARRAY);

	if(isset($_REQUEST["open"]))
	{
		if(!isset($_REQUEST["applicationid"]))
		{
			$_REQUEST["applications"] = array();
			$show_all_apps = 1;
		}
		elseif(!in_array($_REQUEST["applicationid"],$_REQUEST["applications"]))
		{
			array_push($_REQUEST["applications"],$_REQUEST["applicationid"]);
		}
		
	} elseif(isset($_REQUEST["close"]))
	{
		if(!isset($_REQUEST["applicationid"]))
		{
			$_REQUEST["applications"] = array();
		}
		elseif(($i=array_search($_REQUEST["applicationid"], $_REQUEST["applications"])) !== FALSE)
		{
			unset($_REQUEST["applications"][$i]);
		}
	}

	/* limit opened application count */
	while(count($_REQUEST["applications"]) > 25)
	{
		array_shift($_REQUEST["applications"]);
	}


	update_profile("web.httpconf.applications",$_REQUEST["applications"],PROFILE_TYPE_ARRAY);
?>
<?php
	if(isset($_REQUEST['del_sel_step'])&&isset($_REQUEST['sel_step'])&&is_array($_REQUEST['sel_step']))
	{
		foreach($_REQUEST['sel_step'] as $sid)
			if(isset($_REQUEST['steps'][$sid]))
				unset($_REQUEST['steps'][$sid]);
	}
	else if(isset($_REQUEST['new_httpstep']))
	{
		$_REQUEST['steps'] = get_request('steps', array());
		array_push($_REQUEST['steps'],$_REQUEST['new_httpstep']);
	}
	else if(isset($_REQUEST['move_up']) && isset($_REQUEST['steps'][$_REQUEST['move_up']]))
	{
		$new_id = $_REQUEST['move_up'] - 1;

		if(isset($_REQUEST['steps'][$new_id]))
		{
			$tmp = $_REQUEST['steps'][$new_id];
			$_REQUEST['steps'][$new_id] = $_REQUEST['steps'][$_REQUEST['move_up']];
			$_REQUEST['steps'][$_REQUEST['move_up']] = $tmp;
		}
	}
	else if(isset($_REQUEST['move_down']) && isset($_REQUEST['steps'][$_REQUEST['move_down']]))
	{
		$new_id = $_REQUEST['move_down'] + 1;

		if(isset($_REQUEST['steps'][$new_id]))
		{
			$tmp = $_REQUEST['steps'][$new_id];
			$_REQUEST['steps'][$new_id] = $_REQUEST['steps'][$_REQUEST['move_down']];
			$_REQUEST['steps'][$_REQUEST['move_down']] = $tmp;
		}
	}
	else if(isset($_REQUEST["delete"])&&isset($_REQUEST["httptestid"]))
	{
		$result = false;
		if($httptest_data = get_httptest_by_httptestid($_REQUEST["httptestid"]))
		{
			$result = delete_httptest($_REQUEST["httptestid"]);
		}
		show_messages($result, S_SCENARIO_DELETED, S_CANNOT_DELETE_SCENARIO);
		if($result){
			$host = get_host_by_applicationid($httptest_data["applicationid"]);

			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO,
				S_SCENARIO." [".$httptest_data["name"]."] [".$_REQUEST["httptestid"]."] ".S_HOST." [".$host['host']."]");
		}
		unset($_REQUEST["httptestid"]);
		unset($_REQUEST["form"]);
	}
	else if(isset($_REQUEST["clone"]) && isset($_REQUEST["httptestid"]))
	{
		unset($_REQUEST["httptestid"]);
		$_REQUEST["form"] = "clone";
	}
	else if(isset($_REQUEST["save"]))
	{
		/*
		$delay_flex = get_request('delay_flex',array());
		$db_delay_flex = "";
		foreach($delay_flex as $val)
			$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
		$db_delay_flex = trim($db_delay_flex,";");
		// for future use */

		if(isset($_REQUEST["httptestid"]))
		{
			$result = update_httptest($_REQUEST["httptestid"], $_REQUEST['hostid'], $_REQUEST["application"],
				$_REQUEST["name"],$_REQUEST["delay"],$_REQUEST["status"],$_REQUEST["agent"],
				$_REQUEST["macros"],$_REQUEST["steps"]);

			$httptestid = $_REQUEST["httptestid"];
			$action = AUDIT_ACTION_UPDATE;
			
			show_messages($result, S_SCENARIO_UPDATED, S_CANNOT_UPDATE_SCENARIO);
		}
		else
		{
			$httptestid = add_httptest($_REQUEST['hostid'],$_REQUEST["application"],
				$_REQUEST["name"],$_REQUEST["delay"],$_REQUEST["status"],$_REQUEST["agent"],
				$_REQUEST["macros"],$_REQUEST["steps"]);

			$result = $httptestid;
			$action = AUDIT_ACTION_ADD;
			show_messages($result, S_SCENARIO_ADDED, S_CANNOT_ADD_SCENARIO);
		}
		if($result){	
			$host = get_host_by_hostid($_REQUEST["hostid"]);

			add_audit($action, AUDIT_RESOURCE_SCENARIO,
				S_SCENARIO." [".$_REQUEST["name"]."] [".$httptestid."] ".S_HOST." [".$host['host']."]");

			unset($_REQUEST["httptestid"]);
			unset($_REQUEST["form"]);
		}
	}
	elseif(isset($_REQUEST["group_task"])&&isset($_REQUEST["group_httptestid"]))
	{
		if($_REQUEST["group_task"]=="Delete selected")
		{
			$result = false;

			$group_httptestid = $_REQUEST["group_httptestid"];
			foreach($group_httptestid as $id)
			{
				if(!($httptest_data = get_httptest_by_httptestid($id)))	continue;
				/* if($httptest_data["templateid"]<>0)	continue; // for future use */
				if(delete_httptest($id))
				{
					$result = true;
					
					$host = get_host_by_applicationid($httptest_data["applicationid"]);

					add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO,
						S_SCENARIO." [".$httptest_data["name"]."] [".$id."] ".S_HOST." [".$host['host']."]");
				}
			}
			show_messages($result, S_SCENARIO_DELETED, null);
		}
		else if($_REQUEST["group_task"] == S_ACTIVATE_SELECTED)
		{
			$result = false;
			
			$group_httptestid = $_REQUEST["group_httptestid"];
			foreach($group_httptestid as $id)
			{
				if(!($httptest_data = get_httptest_by_httptestid($id)))	continue;
				
				if(activate_httptest($id))
				{
					$result = true;
					
					$host = get_host_by_applicationid($httptest_data["applicationid"]);

					add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO,
						S_SCENARIO." [".$httptest_data["name"]."] [".$id."] ".S_HOST." [".$host['host']."]".
						S_SCENARIO_ACTIVATED);
				}
			}
			show_messages($result, S_SCENARIO_ACTIVATED, null);
		}
		elseif($_REQUEST["group_task"]== S_DISABLE_SELECTED)
		{
			$result = false;
			
			$group_httptestid = $_REQUEST["group_httptestid"];
			foreach($group_httptestid as $id)
			{
				if(!($httptest_data = get_httptest_by_httptestid($id)))	continue;

				if(disable_httptest($id))
				{
					$result = true;				
				
					$host = get_host_by_applicationid($httptest_data["applicationid"]);

					add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO,
						S_SCENARIO." [".$httptest_data["name"]."] [".$id."] ".S_HOST." [".$host['host']."]".
						S_SCENARIO_DISABLED);
				}
			}
			show_messages($result, S_SCENARIO_DISABLED, null);
		}
		elseif($_REQUEST["group_task"]== S_CLEAN_HISTORY_SELECTED_SCENARIOS)
		{
			$result = false;
			
			$group_httptestid = $_REQUEST["group_httptestid"];
			foreach($group_httptestid as $id)
			{
				if(!($httptest_data = get_httptest_by_httptestid($id)))	continue;

				if(delete_history_by_httptestid($id))
				{
					$result = true;
					DBexecute("update httptest set nextcheck=0".
						/* ",lastvalue=null,lastclock=null,prevvalue=null". // for future use */
						" where httptestid=$id");
					
					$host = get_host_by_applicationid($httptest_data["applicationid"]);
					
					add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO,
						S_SCENARIO." [".$httptest_data["name"]."] [".$id."] ".S_HOST." [".$host['host']."]".
						S_HISTORY_CLEANED);
				}
			}
			show_messages($result, S_HISTORY_CLEANED, $result);
		}
	}
?>
<?php
	/* make steps with unique names */
	$_REQUEST['steps'] = get_request('steps',array());
	foreach($_REQUEST['steps'] as $s1id => $s1)
	{
		foreach($_REQUEST['steps'] as $s2id => $s2)
		{
			if($s1['name'] == $s2['name'] && $s1id != $s2id)
			{
				$_REQUEST['steps'][$s1id] = $_REQUEST['steps'][$s2id];
				unset($_REQUEST['steps'][$s2id]);
			}
		}
	}
	$_REQUEST['steps'] = array_merge(get_request('steps',array())); /* reinitialize keys */

	$form = new CForm();

	$form->AddVar("hostid",$_REQUEST["hostid"]);

	$form->AddItem(new CButton("form",S_CREATE_SCENARIO));

	show_table_header(S_CONFIGURATION_OF_WEB_MONITORING_BIG, $form);
	echo BR;

	$db_hosts=DBselect('select hostid from hosts where '.DBin_node('hostid'));
	if(isset($_REQUEST["form"])&&isset($_REQUEST["hostid"])&&DBfetch($db_hosts))
	{
// FORM
		insert_httptest_form();
	} else {

// Table HEADER
		$form = new CForm();
		
		$form->AddItem(array('[', 
			new CLink($showdisabled ? S_HIDE_DISABLED_SCENARIOS: S_SHOW_DISABLED_SCENARIOS,
				'?showdisabled='.($showdisabled ? 0 : 1),'action'),
			']', SPACE));
		
		$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit();");
		$cmbGroup->AddItem(0,S_ALL_SMALL);

		$result=DBselect("select distinct g.groupid,g.name from groups g,hosts_groups hg".
			" where g.groupid=hg.groupid and hg.hostid in (".$accessible_hosts.") ".
			" order by name");
		while($row=DBfetch($result))
		{
			$cmbGroup->AddItem($row["groupid"],$row["name"]);
		}
		$form->AddItem(S_GROUP.SPACE);
		$form->AddItem($cmbGroup);

		if(isset($_REQUEST["groupid"]) && $_REQUEST["groupid"]>0)
		{
			$sql="select distinct h.hostid,h.host from hosts h,hosts_groups hg".
				" where hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid ".
				" and h.hostid in (".$accessible_hosts.") ".
				" and h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host";
		}
		else
		{
			$sql="select distinct h.hostid,h.host from hosts h where h.status<>".HOST_STATUS_DELETED.
				" and h.hostid in (".$accessible_hosts.") ".
				" group by h.hostid,h.host order by h.host";
		}

		$result=DBselect($sql);

		$_REQUEST["hostid"] = get_request("hostid",0);
		$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit();");

		unset($correct_hostid);
		$first_hostid = -1;
		while($row=DBfetch($result))
		{
			$cmbHosts->AddItem($row["hostid"],$row["host"]);

			if($_REQUEST["hostid"]!=0){
				if($_REQUEST["hostid"]==$row["hostid"])
					$correct_hostid = 'ok';
			}
			if($first_hostid <= 0)
				$first_hostid = $row["hostid"];
		}
		if(!isset($correct_hostid))
			$_REQUEST["hostid"] = $first_hostid;

		$form->AddItem(SPACE.S_HOST.SPACE);
		$form->AddItem($cmbHosts);
		
		show_table_header(S_SCENARIOS_BIG, $form);

// TABLE
		$form = new CForm();
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
		$table->setHeader(array(
			array(	$link, SPACE, new CCheckBox("all_httptests",null,
					"CheckAll('".$form->GetName()."','all_httptests');"),
				S_NAME),
			S_NUMBER_OF_STEPS,
			S_UPDATE_INTERVAL,
			S_STATUS));

	$any_app_exist = false;

	$db_applications = DBselect('select distinct h.host,h.hostid,a.* from applications a,hosts h '.
		' where a.hostid=h.hostid and h.hostid='.$_REQUEST['hostid'].
		' order by a.name,a.applicationid,h.host');
	while($db_app = DBfetch($db_applications))
	{
		$db_httptests = DBselect('select wt.*,a.name as application,h.host,h.hostid from httptest wt '.
			' left join applications a on wt.applicationid=a.applicationid '.
			' left join hosts h on h.hostid=a.hostid'.
			' where a.applicationid='.$db_app["applicationid"].
			($showdisabled == 0 ? " and wt.status <> 1" : "").
			' order by h.host,wt.name');

		$app_rows = array();
		$httptest_cnt = 0;
		while($httptest_data = DBfetch($db_httptests))
		{
			++$httptest_cnt;
			if(!in_array($db_app["applicationid"],$_REQUEST["applications"]) && !isset($show_all_apps)) continue;

			$name = array();

			/*
			if($httptest_data["templateid"])
			{
				$template_host = get_realhost_by_httptestid($httptest_data["templateid"]);
				array_push($name,		
					new CLink($template_host["host"],"?".
						"hostid=".$template_host["hostid"],
						'unknown'),
					":");
			} // for future use */
			
			array_push($name, new CLink($httptest_data["name"],"?form=update&httptestid=".
				$httptest_data["httptestid"].url_param("hostid").url_param("groupid"),
				'action'));

			$status=new CCol(new CLink(httptest_status2str($httptest_data["status"]),
					"?group_httptestid%5B%5D=".$httptest_data["httptestid"].
					"&hostid=".$_REQUEST["hostid"].
					"&group_task=".($httptest_data["status"] ? "Activate+selected" : "Disable+selected"),
					httptest_status2style($httptest_data["status"])));
	

			$chkBox = new CCheckBox("group_httptestid[]",null,null,$httptest_data["httptestid"]);
			
			$step_cout = DBfetch(DBselect('select count(*) from httpstep where httptestid='.$httptest_data["httptestid"]));
			$step_cout = $step_cout[0];

			/* if($httptest_data["templateid"] > 0) $chkBox->SetEnabled(false); // for future use */
			
			array_push($app_rows, new CRow(array(
				array(str_repeat(SPACE,4),$chkBox, $name),
				$step_cout,
				$httptest_data["delay"],
				$status
				)));
		}
		if($httptest_cnt > 0)
		{
			if(in_array($db_app["applicationid"],$_REQUEST["applications"]) || isset($show_all_apps))
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

			$table->AddRow($col);

			$any_app_exist = true;
		
			foreach($app_rows as $row)
				$table->AddRow($row);
		}
	}

		$footerButtons = array();
		array_push($footerButtons, new CButtonQMessage('group_task',S_ACTIVATE_SELECTED,S_ACTIVATE_SELECTED_SCENARIOS_Q));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButtonQMessage('group_task',S_DISABLE_SELECTED,S_DISABLE_SELECTED_SCENARIOS_Q));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButtonQMessage('group_task',S_CLEAN_HISTORY_SELECTED_SCENARIOS,S_HISTORY_CLEANING_CAN_TAKE_A_LONG_TIME_CONTINUE_Q));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButtonQMessage('group_task',S_DELETE_SELECTED,S_DELETE_SELECTED_SCENARIOS_Q));
		$table->SetFooter(new CCol($footerButtons));

		$form->AddItem($table);
		$form->Show();

	}
?>
<?php

include_once "include/page_footer.php"

?>
