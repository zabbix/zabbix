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
	require_once "include/graphs.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_CONFIGURATION_OF_GRAPHS";
	$page["file"] = "graphs.php";

include_once "include/page_header.php";

	insert_confirm_javascript();
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,	NULL),
		"hostid"=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,	NULL),

		"copy_type"	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN("0,1"),'isset({copy})'),
		"copy_mode"	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN("0"),NULL),

		"graphid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			'{form}=="update"'),
		"name"=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,		'isset({save})'),
		"width"=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,65535),	'isset({save})'),
		"height"=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,65535),	'isset({save})'),
		"yaxistype"=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN("0,1"),		'isset({save})'),
		"graphtype"=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN("0,1"),		'isset({save})'),
		"yaxismin"=>	array(T_ZBX_DBL, O_OPT,	 NULL,	BETWEEN(-65535,65535),	'isset({save})'),
		"yaxismax"=>	array(T_ZBX_DBL, O_OPT,	 NULL,	BETWEEN(-65535,65535),	'isset({save})'),
		"yaxismax"=>	array(T_ZBX_DBL, O_OPT,	 NULL,	BETWEEN(-65535,65535),	'isset({save})'),
		
		"items"=>		array(T_ZBX_STR, O_OPT,  NULL,	null,		null),
		"new_graph_item"=>	array(T_ZBX_STR, O_OPT,  NULL,	null,		null),
		"group_gid"=>		array(T_ZBX_STR, O_OPT,  NULL,	null,		null),
		"move_up"=>		array(T_ZBX_INT, O_OPT,  NULL,	null,		null),
		"move_down"=>		array(T_ZBX_INT, O_OPT,  NULL,	null,		null),
		
		"showworkperiod"=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN("1"),	NULL),
		"showtriggers"=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN("1"),	NULL),

		"group_graphid"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),
		"copy_targetid"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),
		"filter_groupid"=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, 'isset({copy})&&{copy_type}==0'),
/* actions */
		"add_item"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete_item"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"copy"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_copy_to"=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields);

	validate_group_with_host(PERM_READ_WRITE,array("allow_all_hosts","always_select_first_host"));
?>
<?php
	$_REQUEST['items'] = get_request('items', array());
	$_REQUEST['group_gid'] = get_request('group_gid', array());
	
	$availiable_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, null, null, $ZBX_CURNODEID);
	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, PERM_MODE_LT);

	if(isset($_REQUEST["save"]))
	{
		$items = get_request('items', array());
		foreach($items as $gitem)
		{
			$host = DBfetch(DBselect('select h.* from hosts h,items i where h.hostid=i.hostid and i.itemid='.$gitem['itemid']));
			if(in_array($host['hostid'], explode(',',$denyed_hosts)))
			{
				access_deny();
			}
		}
		if(count($items) <= 0)
		{
			info(S_REQUIRED_ITEMS_FOR_GRAPH);
		}
		else
		{
			$showworkperiod	= isset($_REQUEST["showworkperiod"]) ? 1 : 0;
			$showtriggers	= isset($_REQUEST["showtriggers"]) ? 1 : 0;

			if(isset($_REQUEST["graphid"]))
			{
				$result = update_graph_with_items($_REQUEST["graphid"],
					$_REQUEST["name"],$_REQUEST["width"],$_REQUEST["height"],
					$_REQUEST["yaxistype"],$_REQUEST["yaxismin"],$_REQUEST["yaxismax"],
					$showworkperiod,$showtriggers,$_REQUEST["graphtype"],$items);

				if($result)
				{
					add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_GRAPH,
						"Graph ID [".$_REQUEST["graphid"]."] Graph [".
						$_REQUEST["name"]."]");
				}
				show_messages($result, S_GRAPH_UPDATED, S_CANNOT_UPDATE_GRAPH);
			}
			else
			{
				$result = add_graph_with_items($_REQUEST["name"],$_REQUEST["width"],$_REQUEST["height"],
					$_REQUEST["yaxistype"],$_REQUEST["yaxismin"],$_REQUEST["yaxismax"],
					$showworkperiod,$showtriggers,$_REQUEST["graphtype"],$items);

				if($result)
				{
					add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_GRAPH,
						"Graph [".$_REQUEST["name"]."]");
				}
				show_messages($result, S_GRAPH_ADDED, S_CANNOT_ADD_GRAPH);
			}
			if($result){
				unset($_REQUEST["form"]);
			}
		}
	}
	elseif(isset($_REQUEST["delete"])&&isset($_REQUEST["graphid"]))
	{
		$graph=get_graph_by_graphid($_REQUEST["graphid"]);
		$result=delete_graph($_REQUEST["graphid"]);
		if($result)
		{
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_GRAPH,
				"Graph [".$graph["name"]."]");
			unset($_REQUEST["form"]);
		}
		show_messages($result, S_GRAPH_DELETED, S_CANNOT_DELETE_GRAPH);
	}
	elseif(isset($_REQUEST["delete"])&&isset($_REQUEST["group_graphid"]))
	{
		foreach($_REQUEST["group_graphid"] as $id)
		{
			$graph=get_graph_by_graphid($id);
			if($graph["templateid"]<>0)	continue;
			if(delete_graph($id))
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_GRAPH,
					"Graph [".$graph["name"]."]");
			}
		}
		show_messages(TRUE, S_ITEMS_DELETED, S_CANNOT_DELETE_ITEMS);
	}
	elseif(isset($_REQUEST["copy"])&&isset($_REQUEST["group_graphid"])&&isset($_REQUEST["form_copy_to"]))
	{
		if(isset($_REQUEST['copy_targetid']) && $_REQUEST['copy_targetid'] > 0 && isset($_REQUEST['copy_type']))
		{
			if(0 == $_REQUEST['copy_type'])
			{ /* hosts */
				$hosts_ids = $_REQUEST['copy_targetid'];
			}
			else
			{ /* groups */
				$hosts_ids = array();
				$db_hosts = DBselect('select distinct h.hostid from hosts h, hosts_groups hg'.
					' where h.hostid=hg.hostid and hg.groupid in ('.implode(',',$_REQUEST['copy_targetid']).')'.
					' and h.hostid in ('.$availiable_hosts.")"
					);
				while($db_host = DBfetch($db_hosts))
				{
					array_push($hosts_ids, $db_host['hostid']);
				}
			}
			foreach($_REQUEST["group_graphid"] as $graph_id)
				foreach($hosts_ids as $host_id)
				{
					copy_graph_to_host($graph_id, $host_id, true);
				}
			unset($_REQUEST["form_copy_to"]);
		}
		else
		{
			error('No target selection.');
		}
		show_messages();
	}
	elseif(isset($_REQUEST['delete_item']) && isset($_REQUEST['group_gid']))
	{
		foreach($_REQUEST['items'] as $gid => $data)
		{
			if(!isset($_REQUEST['group_gid'][$gid])) continue;
			unset($_REQUEST['items'][$gid]);
		}
		unset($_REQUEST['delete_item'], $_REQUEST['group_gid']);
	}
	elseif(isset($_REQUEST['new_graph_item']))
	{
		$new_gitem = get_request('new_graph_item', array());
		foreach($_REQUEST['items'] as $gid => $data)
		{
			if(	$new_gitem['itemid']		== $data['itemid'] &&
				$new_gitem['yaxisside']		== $data['yaxisside'] &&
				$new_gitem['calc_fnc']		== $data['calc_fnc'] &&
				$new_gitem['type']		== $data['type'] &&
				$new_gitem['periods_cnt']	== $data['periods_cnt']) 
			{
				$already_exist = true;
				break;
			}
		}
		if(!isset($already_exist))
		{
			array_push($_REQUEST['items'], $new_gitem);
		}
	}
	elseif(isset($_REQUEST['move_up']) && isset($_REQUEST['items']))
	{
		SDI($_REQUEST['items'][$_REQUEST['move_up']]['sortorder']);
		if(isset($_REQUEST['items'][$_REQUEST['move_up']]))
			if($_REQUEST['items'][$_REQUEST['move_up']]['sortorder'] > 0)
				$_REQUEST['items'][$_REQUEST['move_up']]['sortorder']
					 = ''.($_REQUEST['items'][$_REQUEST['move_up']]['sortorder'] - 1);

		SDI($_REQUEST['items'][$_REQUEST['move_up']]['sortorder']);
	}
	elseif(isset($_REQUEST['move_down']) && isset($_REQUEST['items']))
	{
		if(isset($_REQUEST['items'][$_REQUEST['move_down']]))
			if($_REQUEST['items'][$_REQUEST['move_down']]['sortorder'] < 1000)
				$_REQUEST['items'][$_REQUEST['move_down']]['sortorder']++;
	}
?>
<?php
	$form = new CForm();
	$form->AddItem(new CButton("form",S_CREATE_GRAPH));

	show_table_header(S_CONFIGURATION_OF_GRAPHS_BIG,$form);
	echo BR;

	if(isset($_REQUEST["form_copy_to"]) && isset($_REQUEST["group_graphid"]))
	{
		insert_copy_elements_to_forms("group_graphid");
	}
	else if(isset($_REQUEST["form"]))
	{
		insert_graph_form();
		echo BR;
		$table = new CTable(NULL,"graph");
		$table->AddRow(new CImg('chart3.php?period=3600&from=0'.url_param('items').
			url_param('name').url_param('width').url_param('height').url_param('yaxistype').
			url_param('yaxismin').url_param('yaxismax').url_param('show_work_period').
			url_param('show_triggers').url_param('graphtype')));
		$table->Show();
	} else {
/* Table HEADER */
		if(isset($_REQUEST["graphid"])&&($_REQUEST["graphid"]==0))
		{
			unset($_REQUEST["graphid"]);
		}

		$r_form = new CForm();

		$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
		$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit()");

		$cmbGroup->AddItem(0,S_ALL_SMALL);

		$result=DBselect("select distinct g.groupid,g.name from groups g, hosts_groups hg, hosts h, items i ".
			" where h.hostid in (".$availiable_hosts.") ".
			" and hg.groupid=g.groupid ".
			" and h.hostid=i.hostid and hg.hostid=h.hostid ".
			" order by g.name");
		while($row=DBfetch($result))
		{
			$cmbGroup->AddItem($row["groupid"],$row["name"]);
		}
		$r_form->AddItem(array(S_GROUP.SPACE,$cmbGroup));
		
		if($_REQUEST["groupid"] > 0)
		{
			$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where ".
				" h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid".
				" and h.hostid in (".$availiable_hosts.") ".
				" group by h.hostid,h.host order by h.host";
		}
		else
		{
			$cmbHosts->AddItem(0,S_ALL_SMALL);
			$sql="select h.hostid,h.host from hosts h,items i where ".
				" h.hostid=i.hostid".
				" and h.hostid in (".$availiable_hosts.") ".
				" group by h.hostid,h.host order by h.host";
		}
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$cmbHosts->AddItem($row["hostid"],$row["host"]);
		}

		$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
		
		show_table_header(S_GRAPHS_BIG, $r_form);

/* TABLE */
		$form = new CForm();
		$form->SetName('graphs');
		$form->AddVar('hostid',$_REQUEST["hostid"]);

		$table = new CTableInfo(S_NO_GRAPHS_DEFINED);
		$table->SetHeader(array(
			$_REQUEST["hostid"] != 0 ? NULL : S_HOSTS,
			array(	new CCheckBox("all_graphs",NULL,
					"CheckAll('".$form->GetName()."','all_graphs');"),
				S_NAME),
			S_WIDTH,S_HEIGHT,S_GRAPH_TYPE));

		if($_REQUEST["hostid"] > 0)
		{
			$result = DBselect("select distinct g.* from graphs g left join graphs_items gi on g.graphid=gi.graphid ".
				" left join items i on gi.itemid=i.itemid ".
				" where i.hostid=".$_REQUEST["hostid"].
				" and i.hostid not in (".$denyed_hosts.") ".
				" and ".DBid2nodeid("g.graphid")."=".$ZBX_CURNODEID.
				" and i.hostid is not NULL ".
				" order by g.name");
		}
		else
		{
			$result = DBselect("select distinct g.* from graphs g left join graphs_items gi on g.graphid=gi.graphid ".
				" left join items i on gi.itemid=i.itemid ".
				" where ".DBid2nodeid("g.graphid")."=".$ZBX_CURNODEID.
				" and ( i.hostid not in (".$denyed_hosts.")  OR i.hostid is NULL )".
				" order by g.name");
		}
		while($row=DBfetch($result))
		{
			if($_REQUEST["hostid"] != 0)
			{
				$host_list = NULL;
			}
			else
			{
				$host_list = array();
				$db_hosts = get_hosts_by_graphid($row["graphid"]);
				while($db_host = DBfetch($db_hosts))
				{
					array_push($host_list, $db_host["host"]);
				}
				$host_list = implode(',',$host_list);
			}
	
			if($row["templateid"]==0)
			{
				$name = new CLink($row["name"],
					"graphs.php?graphid=".$row["graphid"]."&form=update".
					url_param("groupid").url_param("hostid"),'action');
			} else {
				$real_hosts = get_realhosts_by_graphid($row["templateid"]);
				$real_host = DBfetch($real_hosts);
				if($real_host)
				{
					$name = array(
						new CLink($real_host["host"],"graphs.php?".
							"hostid=".$real_host["hostid"],
							'action'),
						":",
						$row["name"]
						);
				}
				else
				{
					array_push($description,
						new CSpan("error","on"),
						":",
						expand_trigger_description($row["triggerid"])
						);
				}
			}

			$chkBox = new CCheckBox("group_graphid[]",NULL,NULL,$row["graphid"]);
			if($row["templateid"] > 0) $chkBox->SetEnabled(false);

			if($row["graphtype"] == GRAPH_TYPE_STACKED)
				$graphtype = S_STACKED;
			else
				$graphtype = S_NORMAL;

			$table->AddRow(array(
				$host_list,
				array($chkBox, $name),
				$row["width"],
				$row["height"],
				$graphtype
				));
		}

		$footerButtons = array();
		array_push($footerButtons, new CButton('delete','Delete selected',
			"return Confirm('".S_DELETE_SELECTED_ITEMS_Q."');"));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('form_copy_to','Copy selected to ...'));
		$table->SetFooter(new CCol($footerButtons));

		$form->AddItem($table);
		$form->Show();
	}

?>
<?php

include_once "include/page_footer.php";

?>
