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
	show_header($page["title"],0,0);
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
		"showworkperiod"=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN("1"),	NULL),
		"showtriggers"=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN("1"),	NULL),

		"group_graphid"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),
		"copy_targetid"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),
		"filter_groupid"=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, 'isset({copy})&&{copy_type}==0'),
/* actions */
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

	validate_group_with_host(PERM_READ_WRITE,array("allow_all_hosts"));
?>
<?php
	if(isset($_REQUEST["save"]))
	{
		$showworkperiod = 0;
		if(isset($_REQUEST["showworkperiod"]))
			$showworkperiod = 1;
		$showtriggers = 0;
		if(isset($_REQUEST["showtriggers"]))
			$showtriggers = 1;

		if(isset($_REQUEST["graphid"]))
		{
			$result=update_graph($_REQUEST["graphid"],
				$_REQUEST["name"],$_REQUEST["width"],$_REQUEST["height"],
				$_REQUEST["yaxistype"],$_REQUEST["yaxismin"],$_REQUEST["yaxismax"],
				$showworkperiod,$showtriggers,$_REQUEST["graphtype"]);

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
			$result=add_graph($_REQUEST["name"],$_REQUEST["width"],$_REQUEST["height"],
				$_REQUEST["yaxistype"],$_REQUEST["yaxismin"],$_REQUEST["yaxismax"],
				$showworkperiod,$showtriggers,$_REQUEST["graphtype"]);
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
			$result=delete_graph($id);
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
				$group_ids = "";
				foreach($_REQUEST['copy_targetid'] as $group_id)
				{
					$group_ids .= $group_id.',';
				}
				$group_ids = trim($group_ids,',');

				$db_hosts = DBselect('select distinct h.hostid from hosts h, hosts_groups hg'.
					' where h.hostid=hg.hostid and hg.groupid in ('.$group_ids.')');
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
	} else {
/* Table HEADER */
		if(isset($_REQUEST["graphid"])&&($_REQUEST["graphid"]==0))
		{
			unset($_REQUEST["graphid"]);
		}

		$form = new CForm();
		$form->AddItem(S_GROUP.SPACE);
		$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
		$cmbGroup->AddItem(0,S_ALL_SMALL);
		$result=DBselect("select groupid,name from groups where mod(groupid,100)=$ZBX_CURNODEID order by name");
		while($row=DBfetch($result))
		{
	// Check if at least one host with read permission exists for this group
			$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg".
				" where h.hostid=i.hostid and hg.groupid=".$row["groupid"].
				" and hg.hostid=h.hostid and h.status=".HOST_STATUS_MONITORED.
				" group by h.hostid,h.host order by h.host");
			while($row2=DBfetch($result2))
			{
//				if(!check_right("Host","R",$row2["hostid"])) /* TODO */
					continue;
				$cmbGroup->AddItem($row["groupid"],$row["name"]);
				break;
			}
		}
		$form->AddItem($cmbGroup);

		$form->AddItem(SPACE.S_HOST.SPACE);
			
		$cmbHosts = new CComboBox("hostid", $_REQUEST["hostid"], "submit()");
		if($_REQUEST["groupid"]==0)
			$cmbHosts->AddItem(0,S_ALL_SMALL);

		if($_REQUEST["groupid"] > 0)
		{
			$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg".
				" where h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"].
				" and hg.hostid=h.hostid"." and h.status=".HOST_STATUS_MONITORED.
				" group by h.hostid,h.host order by h.host";
		}
		else
		{
			$sql="select h.hostid,h.host from hosts h,items i where h.hostid=i.hostid".
				" and h.status=".HOST_STATUS_MONITORED." group by h.hostid,h.host".
				" and mod(h.hostid,100)=".$ZBX_CURNODEID.
				" order by h.host";
		}

		$result=DBselect($sql);
		$host_ok = 0;
		$first_host = 0;
		while($row=DBfetch($result))
		{
//			if(!check_right("Host","R",$row["hostid"]))	continue; /* TODO */
			$cmbHosts->AddItem($row["hostid"],$row["host"]);
			if($first_host == 0) $first_host = $row["hostid"];
			if($_REQUEST["hostid"] == $row["hostid"]) $host_ok = 1;
		}
		$form->AddItem($cmbHosts);
		if(!$host_ok && $_REQUEST["hostid"]!=0)
			$_REQUEST["hostid"] = $first_host;

		show_header2(S_GRAPHS_BIG, $form);

/* TABLE */
		$form = new CForm();
		$form->SetName('graphs');
		$form->AddVar('hostid',$_REQUEST["hostid"]);

		$table = new CTableInfo(S_NO_GRAPHS_DEFINED);
		$table->setHeader(array(
			array(	new CCheckBox("all_graphs",NULL,
					"CheckAll('".$form->GetName()."','all_graphs');"),
				S_ID),
			$_REQUEST["hostid"] != 0 ? NULL : S_HOSTS, S_NAME,S_WIDTH,S_HEIGHT,S_GRAPH_TYPE,S_GRAPH));

		if($_REQUEST["hostid"] > 0)
		{
			$result=DBselect("select distinct g.* from graphs g,items i".
				",graphs_items gi where gi.itemid=i.itemid and g.graphid=gi.graphid".
				" and i.hostid=".$_REQUEST["hostid"]." order by g.name");
		}
		else
		{
			$result=DBselect("select * from graphs g where mod(graphid,100)=$ZBX_CURNODEID order by g.name");
		}
		while($row=DBfetch($result))
		{
//			if(!check_right("Graph","U",$row["graphid"]))		continue; /* TODO */

			if($_REQUEST["hostid"] != 0)
			{
				$host_list = NULL;
			}
			else
			{
				$host_list = "";
				$db_hosts = get_hosts_by_graphid($row["graphid"]);
				while($db_host = DBfetch($db_hosts))
				{
					$host_list .= $db_host["host"].",";
				}
				$host_list = trim($host_list,',');
			}
	
			if($row["templateid"]==0)
			{
				$name = new CLink($row["name"],
					"graphs.php?graphid=".$row["graphid"]."&form=update".
					url_param("groupid").url_param("hostid"),'action');
				$edit = new CLink("Edit",
					"graph.php?graphid=".$row["graphid"]);
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
				$edit = SPACE;
			}

			$chkBox = new CCheckBox("group_graphid[]",NULL,NULL,$row["graphid"]);
			if($row["templateid"] > 0) $chkBox->SetEnabled(false);

			if($row["graphtype"] == GRAPH_TYPE_STACKED)
				$graphtype = S_STACKED;
			else
				$graphtype = S_NORMAL;

			$table->AddRow(array(
				array($chkBox, $row["graphid"]),
				$host_list,
				$name,
				$row["width"],
				$row["height"],
				$graphtype,
				$edit
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
	show_page_footer();
?>
