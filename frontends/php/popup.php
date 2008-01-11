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
	require_once "include/triggers.inc.php";
	require_once "include/items.inc.php";
	require_once "include/users.inc.php";
	require_once "include/nodes.inc.php";
	require_once "include/js.inc.php";

	$srctbl		= get_request("srctbl",  '');	// source table name

	switch($srctbl)
	{
		case 'host_templates':
		case 'templates':
			$page["title"] = "S_TEMPLATES_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'hosts':
			$page["title"] = "S_HOSTS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'applications':
			$page["title"] = "S_APPLICATIONS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'host_group':
			$page["title"] = "S_HOST_GROUPS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'triggers':
			$page["title"] = "S_TRIGGERS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'logitems':
			$page["title"] = "S_ITEMS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'usrgrp':
			$page["title"] = "S_GROUPS";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'users':
			$page["title"] = "S_USERS";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'items':
			$page["title"] = "S_ITEMS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'help_items':
			$page["title"] = "S_STANDARD_ITEMS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'screens':
			$page["title"] = "S_SCREENS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'graphs':
			$page["title"] = "S_GRAPHS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'simple_graph':
			$page["title"] = "S_SIMPLE_GRAPH_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'sysmaps':
			$page["title"] = "S_MAPS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'plain_text':
			$page["title"] = "S_PLAIN_TEXT_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'screens2':
			$page["title"] = "S_SCREENS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'overview':
			$page["title"] = "S_OVERVIEW_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'host_group_scr':
			$page["title"] = "S_HOST_GROUPS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'nodes':
			if(ZBX_DISTRIBUTED)
			{
				$page["title"] = "S_NODES_BIG";
				$min_user_type = USER_TYPE_ZABBIX_USER;
				break;
			}
		default:
			$page["title"] = "S_ERROR";
			$error = true;
			break;
	}

	$page["file"] = "popup.php";
	
	define('ZBX_PAGE_NO_MENU', 1);

include_once "include/page_header.php";

	if(isset($error))
	{
		invalid_url();
	}
	
	if(defined($page["title"]))     $page["title"] = constant($page["title"]);
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"dstfrm" =>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		"dstfld1"=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		"dstfld2"=>	array(T_ZBX_STR, O_OPT,P_SYS,	null,		null),
		"srctbl" =>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		"srcfld1"=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		"srcfld2"=>	array(T_ZBX_STR, O_OPT,P_SYS,	null,		null),
		"nodeid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"groupid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"hostid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"screenid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"templates"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		"host_templates"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		"existed_templates"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		"only_hostid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"monitored_hosts"=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
		'real_hosts'=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
		'itemtype'=>	array(T_ZBX_INT, O_OPT, null,   null,		null),
		
		"select"=>	array(T_ZBX_STR,	O_OPT,	P_SYS|P_ACT,	null,	null)
	);

	$allowed_item_types = array(ITEM_TYPE_ZABBIX,ITEM_TYPE_SIMPLE,ITEM_TYPE_INTERNAL,ITEM_TYPE_AGGREGATE);

	if(isset($_REQUEST['itemtype']) && !in_array($_REQUEST['itemtype'], $allowed_item_types))
			unset($_REQUEST['itemtype']);

	check_fields($fields);

	$dstfrm		= get_request("dstfrm",  '');	// destination form
	$dstfld1	= get_request("dstfld1", '');	// output field on destination form
	$dstfld2	= get_request("dstfld2", '');	// second output field on destination form
	$srcfld1	= get_request("srcfld1", '');	// source table field [can be different from fields of source table]
	$srcfld2	= get_request("srcfld2", null);	// second source table field [can be different from fields of source table]
	
	$monitored_hosts = get_request("monitored_hosts", 0);
	$real_hosts = get_request("real_hosts", 0);
	$only_hostid	 = get_request("only_hostid", null);
?>
<?php
	global $USER_DETAILS;

	if($min_user_type > $USER_DETAILS['type'])
	{
		access_deny();
	}
?>
<?php
	function get_window_opener($frame, $field, $value)
	{
//		return empty($field) ? "" : "window.opener.document.forms['".addslashes($frame)."'].elements['".addslashes($field)."'].value='".addslashes($value)."';";
		if(empty($field)) return '';
		
		$script = 	'try{'.
						"window.opener.document.getElementsByName('".addslashes($field)."')[0].value='".addslashes($value)."';".
					'} catch(e){'.
						'throw("Error: Target not found")'.
					'}'."\n";
		return $script;
	}
?>
<?php
	$frmTitle = new CForm();
		
	if($monitored_hosts)
		$frmTitle->AddVar('monitored_hosts', 1);

	if($real_hosts)
		$frmTitle->AddVar('real_hosts', 1);

	$frmTitle->AddVar("dstfrm",	$dstfrm);
	$frmTitle->AddVar("dstfld1",	$dstfld1);
	$frmTitle->AddVar("dstfld2",	$dstfld2);
	$frmTitle->AddVar("srctbl",	$srctbl);
	$frmTitle->AddVar("srcfld1",	$srcfld1);
	$frmTitle->AddVar("srcfld2",	$srcfld2);

	if(isset($only_hostid))
	{
		$_REQUEST['hostid'] = $only_hostid;
		$frmTitle->AddVar("only_hostid",$only_hostid);
		unset($_REQUEST["groupid"],$_REQUEST["nodeid"]);
	}
	
	$validation_param = array("allow_all_hosts");

	if($monitored_hosts)
		array_push($validation_param, "monitored_hosts");
		
	if($real_hosts)
		array_push($validation_param, "real_hosts");
		
	if(in_array($srctbl,array("triggers","logitems","items")))
	{
		array_push($validation_param, "always_select_first_host");
		validate_group_with_host(PERM_READ_LIST,$validation_param);
	}
	elseif(in_array($srctbl,array('hosts','templates','host_templates')))
	{
		validate_group(PERM_READ_LIST,$validation_param);
	}

	$accessible_nodes	= get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_LIST,null,null,get_current_nodeid(true));
	$denyed_hosts		= get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT);
	$accessible_hosts	= get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
	$nodeid				= get_current_nodeid();

	if(isset($only_hostid))
	{
		if(!isset($_REQUEST["hostid"]) || (bccomp($_REQUEST["hostid"], $only_hostid) != 0)) access_deny();
		$hostid = $only_hostid;
	}
	else
	{
		if(in_array($srctbl,array('hosts','host_group','triggers','logitems','items',
									'applications','screens','graphs','simple_graph',
									'sysmaps','plain_text','screens2','overview','host_group_scr')))
		{
			if(ZBX_DISTRIBUTED)
			{
				$nodeid = get_request('nodeid', $nodeid);
				$cmbNode = new CComboBox('nodeid', $nodeid, 'submit()');
				$db_nodes = DBselect('select * from nodes where nodeid in ('.$accessible_nodes.')');
				while($node_data = DBfetch($db_nodes))
				{
					$cmbNode->AddItem($node_data['nodeid'], $node_data['name']);
					if((bccomp($nodeid , $node_data['nodeid']) == 0)) $ok = true;
				}
				$frmTitle->AddItem(array(SPACE,S_NODE,SPACE,$cmbNode));
			}
		}	
		if(!isset($ok)) $nodeid = get_current_nodeid();
		unset($ok);
		
		if(in_array($srctbl,array('hosts','templates','triggers','logitems','items','applications','host_templates','graphs','simple_graph','plain_text')))
		{
			$groupid = get_request('groupid',get_profile('web.popup.groupid',0));
			
			$cmbGroups = new CComboBox('groupid',$groupid,'submit()');
			$cmbGroups->AddItem(0,S_ALL_SMALL);
			$db_groups = DBselect('select distinct g.groupid,g.name from groups g, hosts_groups hg, hosts h '.
				' where '.DBin_node('g.groupid', $nodeid).
				' and g.groupid=hg.groupid and hg.hostid in ('.$accessible_hosts.') '.
				' and hg.hostid = h.hostid '.
				($monitored_hosts ? ' and h.status='.HOST_STATUS_MONITORED : '').
				($real_hosts ? ' and h.status<>'.HOST_STATUS_TEMPLATE : '').
				' order by name');
			while($group = DBfetch($db_groups))
			{
				$cmbGroups->AddItem($group["groupid"],$group["name"]);
				if((bccomp($groupid , $group["groupid"]) == 0)) $ok = true;
			}
			$frmTitle->AddItem(array(S_GROUP,SPACE,$cmbGroups));
			update_profile("web.popup.groupid",$groupid);
			if(!isset($ok) || $groupid == 0) unset($groupid);
			unset($ok);
		}
		if(in_array($srctbl,array("help_items")))
		{
			$itemtype = get_request("itemtype",get_profile("web.popup.itemtype",0));
			$cmbTypes = new CComboBox("itemtype",$itemtype,"submit()");
			foreach($allowed_item_types as $type)
				$cmbTypes->AddItem($type, item_type2str($type));
			$frmTitle->AddItem(array(S_TYPE,SPACE,$cmbTypes));
		}
		if(in_array($srctbl,array("triggers","logitems","items",'applications','graphs','simple_graph','plain_text')))
		{
			$hostid = get_request("hostid",get_profile("web.popup.hostid",0));
			$cmbHosts = new CComboBox("hostid",$hostid,"submit()");
			
			$sql = 'SELECT distinct h.hostid,h.host FROM hosts h';
			if(isset($groupid))
			{
				$sql .= ',hosts_groups hg WHERE '.
					' h.hostid=hg.hostid AND hg.groupid='.$groupid.' AND ';
			}
			else
			{
				$sql .= ' WHERE ';
				$cmbHosts->AddItem(0,S_ALL_SMALL);
			}

			$sql .= DBin_node('h.hostid', $nodeid).
				" and h.hostid in (".$accessible_hosts.")".
				($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : "").
				($real_hosts ? ' and h.status<>'.HOST_STATUS_TEMPLATE : '').
				' order by host,h.hostid';

			$db_hosts = DBselect($sql);
			while($host = DBfetch($db_hosts))
			{
				$cmbHosts->AddItem($host["hostid"],$host["host"]);
				if(bccomp($hostid , $host["hostid"]) == 0) $ok = true;
			}
			$frmTitle->AddItem(array(SPACE,S_HOST,SPACE,$cmbHosts));
			update_profile("web.popup.hostid",$hostid);
			if(!isset($ok) || $hostid == 0) unset($hostid);
			unset($ok);
		}

		if(in_array($srctbl,array("triggers","hosts")))
		{
			$btnEmpty = new CButton("empty",S_EMPTY,
				get_window_opener($dstfrm, $dstfld1, 0).
				get_window_opener($dstfrm, $dstfld2, '').
				" close_window(); return false;");

			$frmTitle->AddItem(array(SPACE,$btnEmpty));
		}
	}

	show_table_header($page["title"], $frmTitle);
?>
<?php
	if($srctbl == "hosts")
	{
		$table = new CTableInfo(S_NO_HOSTS_DEFINED);
		$table->SetHeader(array(S_HOST,S_DNS,S_IP,S_PORT,S_STATUS,S_AVAILABILITY));

		$sql = "select distinct h.* from hosts h";
		if(isset($groupid))
			$sql .= ",hosts_groups hg where hg.groupid=".$groupid.
				" and h.hostid=hg.hostid and ";
		else
			$sql .= " where ";

		$sql .= DBin_node('h.hostid', $nodeid).
				" and h.hostid in (".$accessible_hosts.") ".
				($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : "").
				($real_hosts ? " and h.status<>".HOST_STATUS_TEMPLATE : "").
				" order by h.host,h.hostid";


		$db_hosts = DBselect($sql);
		while($host = DBfetch($db_hosts))
		{
			$name = new CLink($host["host"],"#","action");
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $host[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $host[$srcfld2]) : '').
				" close_window(); return false;");

			if($host["status"] == HOST_STATUS_MONITORED)	
				$status=new CSpan(S_MONITORED,"off");
			else if($host["status"] == HOST_STATUS_NOT_MONITORED)
				$status=new CSpan(S_NOT_MONITORED,"on");
			else if($host["status"] == HOST_STATUS_TEMPLATE)
				$status=new CSpan(S_TEMPLATE,"unknown");
			else if($host["status"] == HOST_STATUS_DELETED)
				$status=new CSpan(S_DELETED,"unknown");
			else
				$status=S_UNKNOWN;

			if($host["status"] == HOST_STATUS_TEMPLATE)
			{
				$dns = $ip = $port = $available = '-';
			}
			else
			{
				$dns = $host['dns'];
				$ip = $host['ip'];

				if($host["useip"]==1)
					$ip = bold($ip);
				else
					$dns = bold($dns);

				$port = $host["port"];

				if($host["available"] == HOST_AVAILABLE_TRUE)	
					$available=new CSpan(S_AVAILABLE,"off");
				else if($host["available"] == HOST_AVAILABLE_FALSE)
					$available=new CSpan(S_NOT_AVAILABLE,"on");
				else if($host["available"] == HOST_AVAILABLE_UNKNOWN)
					$available=new CSpan(S_UNKNOWN,"unknown");

			}

			$table->AddRow(array(
				$name,
				$dns,
				$ip,
				$port,
				$status,
				$available
				));

			unset($host);
		}
		$table->Show();
	}
	elseif($srctbl == "templates")
	{
		$existed_templates = get_request('existed_templates', array());
		$templates = get_request('templates', array());
		$templates = $templates + $existed_templates;

		if(!validate_templates(array_keys($templates)))
		{
			show_error_message('Conflict between selected templates');
		}
		elseif(isset($_REQUEST['select']))
		{
			$new_templates = array_diff($templates, $existed_templates);
			if(count($new_templates) > 0) 
			{
				foreach($new_templates as $id => $name)
				{
?>

<script language="JavaScript" type="text/javascript">
<!--
	add_variable(null,"templates[" + "<?php echo $id; ?>" + "]","<?php echo $name; ?>","<?php echo $dstfrm; ?>",window.opener.document);
-->
</script>

<?php
				} // foreach new_templates
?>

<script language="JavaScript" type="text/javascript">
<!--
        var form = window.opener.document.forms['<?php echo $dstfrm; ?>'];
        if(form) form.submit();
	close_window();
-->
</script>

<?php
			} // if count new_templates > 0
			unset($new_templates);
		}
		
		$table = new CTableInfo(S_NO_TEMPLATES_DEFINED);
		$table->SetHeader(array(S_NAME));

		$sql = "select distinct h.* from hosts h";
		if(isset($groupid))
			$sql .= ",hosts_groups hg where hg.groupid=".$groupid.
				" and h.hostid=hg.hostid and ";
		else
			$sql .= " where ";

		$sql .= DBin_node('h.hostid', $nodeid).
				" and h.hostid in (".$accessible_hosts.") ".
				" and h.status=".HOST_STATUS_TEMPLATE.
				" order by h.host,h.hostid";

		$db_hosts = DBselect($sql);
		while($host = DBfetch($db_hosts))
		{
			$chk = new CCheckBox('templates['.$host["hostid"].']',isset($templates[$host["hostid"]]),
					null,$host["host"]);
			$chk->SetEnabled(!isset($existed_templates[$host["hostid"]]));

			$table->AddRow(array(
				array(
					$chk,
					$host["host"])
				));

			unset($host);
		}
		$table->SetFooter(new CButton('select',S_SELECT));
		$form = new CForm();
		$form->AddVar('existed_templates',$existed_templates);
		
		if($monitored_hosts)
			$form->AddVar('monitored_hosts', 1);
			
		if($real_hosts)
			$form->AddVar('real_hosts', 1);

		$form->AddVar('dstfrm',$dstfrm);
		$form->AddVar('dstfld1',$dstfld1);
		$form->AddVar('srctbl',$srctbl);
		$form->AddVar('srcfld1',$srcfld1);
		$form->AddVar('srcfld2',$srcfld2);
		$form->AddItem($table);
		$form->Show();
	}
	elseif(in_array($srctbl,array("host_group")))
	{
		$accessible_groups	= get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY);

		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->SetHeader(array(S_NAME));

		$db_groups = DBselect("select distinct groupid,name from groups ".
			' where '.DBin_node('groupid', $nodeid).
			" and groupid in (".$accessible_groups.") ".
			" order by name");
		while($row = DBfetch($db_groups))
		{
			$name = new CLink($row["name"],"#","action");
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				" return close_window();");

			$table->AddRow($name);
		}
		$table->Show();
	}
	elseif(in_array($srctbl,array('host_templates')))
	{
		$table = new CTableInfo(S_NO_TEMPLATES_DEFINED);
		$table->SetHeader(array(S_NAME));

		$sql = 'select distinct h.* from hosts h';
		if(isset($groupid))
			$sql .= ',hosts_groups hg where hg.groupid='.$groupid.
				' and h.hostid=hg.hostid and ';
		else
			$sql .= ' where ';

		$sql .= DBin_node('h.hostid',$nodeid).' and status='.HOST_STATUS_TEMPLATE.
				' and h.hostid in ('.$accessible_hosts.') '.
				' order by h.host,h.hostid';
		$db_hosts = DBselect($sql);
		while($row = DBfetch($db_hosts))
		{
			$name = new CLink($row['host'],'#','action');
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				' return close_window();');

			$table->AddRow($name);
		}
		$table->Show();
	}
	elseif($srctbl == "usrgrp")
	{
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->SetHeader(array(S_NAME));

		$result = DBselect('select * from usrgrp where '.DBin_node('usrgrpid').' order by name');
		while($row = DBfetch($result))
		{
			$name = new CLink(
					get_node_name_by_elid($row['usrgrpid']).$row['name'],
					'#',
					'action'
					);
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				" close_window(); return false;");

			$table->AddRow($name);
		}
		$table->Show();
	}
	elseif($srctbl == "users")
	{
		$table = new CTableInfo(S_NO_USERS_DEFINED);
		$table->SetHeader(array(S_NAME));

		$result = DBselect('select * from users where '.DBin_node('userid').' order by name');
		while($row = DBfetch($result))
		{
			$name = new CLink(
					get_node_name_by_elid($row['userid']).$row['alias'],
					'#',
					'action'
					);
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				" close_window(); return false;");

			$table->AddRow($name);
		}
		$table->Show();
	}
	elseif($srctbl == "help_items")
	{
		$table = new CTableInfo(S_NO_ITEMS);
		$table->SetHeader(array(S_KEY,S_DESCRIPTION));

		$result = DBselect("select * from help_items where itemtype=".$itemtype." order by key_");

		while($row = DBfetch($result))
		{
			$name = new CLink($row["key_"],"#","action");
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, html_entity_decode($row[$srcfld1])).
				" close_window(); return false;");

			$table->AddRow(array(
				$name,
				$row["description"]
				));
		}
		$table->Show();
	}
	elseif($srctbl == "triggers")
	{
		$table = new CTableInfo(S_NO_TRIGGERS_DEFINED);
		$table->SetHeader(array(
			S_NAME,
			S_SEVERITY,
			S_STATUS));


		$sql = "select h.host,t.triggerid,t.description,t.expression,t.priority,t.status,count(d.triggerid_up) as dep_count ".
			" from hosts h,items i,functions f, triggers t left join trigger_depends d on d.triggerid_down=t.triggerid ".
			" where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid".
			' and '.DBin_node('t.triggerid', $nodeid).
			" and h.hostid not in (".$denyed_hosts.")".
			($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : "").
			($real_hosts ? " and h.status<>".HOST_STATUS_TEMPLATE : "");

		if(isset($hostid)) 
			$sql .= " and h.hostid=$hostid";

		$sql .= " group by h.host, t.triggerid, t.description, t.expression, t.priority, t.status".
			" order by h.host,t.description";

		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$exp_desc = expand_trigger_description_by_data($row);
			$description = new CLink($exp_desc,"#","action");
			$description->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				get_window_opener($dstfrm, $dstfld2, $exp_desc).
				" close_window(); return false;");

			if($row['dep_count'] > 0)
			{
				$description = array(
					$description,
					BR(),BR(),
					bold(S_DEPENDS_ON),
					BR());

				$deps = get_trigger_dependences_by_triggerid($row["triggerid"]);
				
				foreach($deps as $val)
					$description[] = array(expand_trigger_description($val),BR());
			}

			if($row["status"] == TRIGGER_STATUS_DISABLED)
			{
				$status= new CSpan(S_DISABLED, 'disabled');
			}
			else if($row["status"] == TRIGGER_STATUS_UNKNOWN)
			{
				$status= new CSpan(S_UNKNOWN, 'unknown');
			}
			else if($row["status"] == TRIGGER_STATUS_ENABLED)
			{
				$status= new CSpan(S_ENABLED, 'enabled');
			}

			if($row["status"] != TRIGGER_STATUS_UNKNOWN)	$row["error"]=SPACE;

			if($row["error"]=="")		$row["error"]=SPACE;

			$table->AddRow(array(
				$description,
				new CCol(get_severity_description($row['priority']),get_severity_style($row['priority'])),
				$status,
			));

			unset($description);
			unset($status);
		}
		$table->Show();
	}
	elseif($srctbl == "logitems")
	{
		insert_js_function('add_item_variable');
		
		$table = new CTableInfo(S_NO_ITEMS_DEFINED);

		$table->SetHeader(array(
			!isset($hostid) ? S_HOST : null,
			S_DESCRIPTION,S_KEY,nbsp(S_UPDATE_INTERVAL),
			S_STATUS));

		$db_items = DBselect("select distinct h.host,i.* from items i,hosts h".
			" where i.value_type=".ITEM_VALUE_TYPE_LOG." and h.hostid=i.hostid".
			' and '.DBin_node('i.itemid', $nodeid).
			(isset($hostid) ? " and ".$hostid."=i.hostid " : "").
			" and i.hostid in (".$accessible_hosts.")".
			($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : "").
			($real_hosts ? " and h.status<>".HOST_STATUS_TEMPLATE : "").
			" order by h.host,i.description, i.key_, i.itemid");

		while($db_item = DBfetch($db_items))
		{
			$description = new CLink(item_description($db_item["description"],$db_item["key_"]),"#","action");
			$description->SetAction("return add_item_variable('".$dstfrm."',".$db_item["itemid"].");");

			switch($db_item["status"]){
				case 0: $status=new CCol(S_ACTIVE,"enabled");		break;
				case 1: $status=new CCol(S_DISABLED,"disabled");	break;
				case 3: $status=new CCol(S_NOT_SUPPORTED,"unknown");	break;
				default:$status=S_UNKNOWN;
			}

			$table->AddRow(array(
				!isset($hostid) ? $db_item["host"] : null,
				$description,
				$db_item["key_"],
				$db_item["delay"],
				$status
				));
		}
		unset($db_items, $db_item);

		$table->Show();
	}
	elseif($srctbl == "items")
	{
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->SetHeader(array(
			(isset($hostid) ? null : S_HOST),
			S_DESCRIPTION,
			S_TYPE,
			S_TYPE_OF_INFORMATION,
			S_STATUS
			));

		$sql = "select distinct h.host,i.* from hosts h,items i ".
			' where h.hostid=i.hostid and '.DBin_node('i.itemid', $nodeid).
			" and h.hostid not in (".$denyed_hosts.")".
			($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : '').
			($real_hosts ? " and h.status<>".HOST_STATUS_TEMPLATE : '');

		if(isset($hostid)) 
			$sql .= " and h.hostid=$hostid";

		$sql .= " order by h.host, i.description, i.key_, i.itemid";
			
		$result = DBselect($sql);
		while($row = DBfetch($result))
		{
			$row["description"] = item_description($row["description"],$row["key_"]);
			
			$description = new CLink($row["description"],"#","action");

			$row["description"] = $row['host'].':'.$row["description"];

			$description->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				" close_window(); return false;");

			$table->AddRow(array(
				(isset($hostid) ? null : $row['host']),
				$description,
				item_type2str($row['type']),
				item_value_type2str($row['value_type']),
				new CSpan(item_status2str($row['status']),item_status2style($row['status']))
				));
		}
		$table->Show();
	}
	elseif($srctbl == "applications")
	{
		$table = new CTableInfo(S_NO_APPLICATIONS_DEFINED);
		$table->SetHeader(array(
			(isset($hostid) ? null : S_HOST),
			S_NAME));

		$sql = "select distinct h.host,a.* from hosts h,applications a ".
			' where h.hostid=a.hostid and '.DBin_node('a.applicationid', $nodeid).
			" and h.hostid not in (".$denyed_hosts.")".
			($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : "").
			($real_hosts ? " and h.status<>".HOST_STATUS_TEMPLATE : "");

		if(isset($hostid)) 
			$sql .= " and h.hostid=$hostid";

		$sql .= " order by h.host,a.name";
			
		$result = DBselect($sql);
		while($row = DBfetch($result))
		{

			$name = new CLink($row["name"],"#","action");
			
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				" close_window(); return false;");

			$table->AddRow(array(isset($hostid) ? null : $row['host'], $name));
		}
		$table->Show();
	}
	elseif($srctbl == "nodes")
	{
		$table = new CTableInfo(S_NO_NODES_DEFINED);
		$table->SetHeader(S_NAME);

		$result = DBselect('select distinct * from nodes where nodeid in ('.$accessible_nodes.')');
		while($row = DBfetch($result))
		{

			$name = new CLink($row["name"],"#","action");
			
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				" close_window(); return false;");

			$table->AddRow($name);
		}
		$table->Show();
	}
	elseif($srctbl == "graphs")
	{
		$table = new CTableInfo(S_NO_GRAPHS_DEFINED);
		$table->SetHeader(array(S_NAME,S_GRAPH_TYPE));

		$sql = 'SELECT DISTINCT g.graphid,g.name,g.graphtype,n.name as node_name, h.host'.
					' FROM graphs g '.
						' LEFT JOIN graphs_items gi ON g.graphid=gi.graphid '.
						' LEFT JOIN items i ON gi.itemid=i.itemid '.
						' LEFT JOIN hosts h ON h.hostid=i.hostid '.
						' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('g.graphid').
					' WHERE h.hostid NOT IN ('.$denyed_hosts.')'.
						' AND '.DBin_node('g.graphid', $nodeid);

		if(isset($hostid)) 
			$sql .= ' AND h.hostid='.$hostid;

		$sql .= ' ORDER BY node_name,host,name,graphid';

		$result=DBselect($sql);
		while($row=DBfetch($result)){
			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
			$name = $row['node_name'].$row['host'].':'.$row['name'];

			$description = new CLink($row['name'],'#','action');
			$description->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row['graphid']).
				get_window_opener($dstfrm, $dstfld2, $name).
				' close_window(); return false;');

			switch($row['graphtype']){
				case  GRAPH_TYPE_STACKED:
					$graphtype = S_STACKED;
					break;
				case  GRAPH_TYPE_PIE:
					$graphtype = S_PIE;
					break;
				case  GRAPH_TYPE_EXPLODED:
					$graphtype = S_EXPLODED;
					break;
				default:
					$graphtype = S_NORMAL;
					break;
			}
			
			$table->AddRow(array(
				$description,
				$graphtype
			));

			unset($description);
		}
		$table->Show();
	}
	elseif($srctbl == "simple_graph")
	{
		$table = new CTableInfo(S_NO_ITEMS_DEFINED);
		$table->SetHeader(array(
			(isset($hostid) ? null : S_HOST),
			S_DESCRIPTION,
			S_TYPE,
			S_TYPE_OF_INFORMATION,
			S_STATUS
			));

		$sql = 'SELECT n.name as node_name,h.host,i.*,i.key_ '.
				' FROM hosts h,items i '.
					' left join nodes n on n.nodeid='.DBid2nodeid('i.itemid').
				' WHERE h.hostid=i.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND i.status='.ITEM_STATUS_ACTIVE.
					' AND '.DBin_node('i.itemid', $nodeid).
					' AND h.hostid not in ('.$denyed_hosts.')'.
					($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : '').
					($real_hosts ? " and h.status<>".HOST_STATUS_TEMPLATE : '');

		if(isset($hostid)) 
			$sql .= ' AND h.hostid='.$hostid;

		$sql .= " order by h.host, i.description, i.key_, i.itemid";
			
		$result = DBselect($sql);
		while($row = DBfetch($result))
		{
			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
			$row["description"] = item_description($row["description"],$row["key_"]);
			
			$description = new CLink($row["description"],"#","action");

			$row["description"] = $row['node_name'].$row['host'].':'.$row["description"];

			$description->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row['itemid']).
				get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
				' close_window(); return false;');

			$table->AddRow(array(
				(isset($hostid) ? null : $row['host']),
				$description,
				item_type2str($row['type']),
				item_value_type2str($row['value_type']),
				new CSpan(item_status2str($row['status']),item_status2style($row['status']))
				));
		}
		$table->Show();
	}
	elseif($srctbl == "sysmaps")
	{
		$table = new CTableInfo(S_NO_MAPS_DEFINED);
		$table->SetHeader(array(S_NAME));

		$sql = 'SELECT n.name as node_name, s.sysmapid,s.name '.
							' FROM sysmaps s'.
								' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('s.sysmapid').
							' WHERE '.DBin_node('s.sysmapid', $nodeid).
							' ORDER BY s.name';

		$result=DBselect($sql);
		while($row=DBfetch($result)){
			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
			$name = $row['node_name'].$row['name'];

			$description = new CLink($row['name'],'#','action');
			$description->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row['sysmapid']).
				get_window_opener($dstfrm, $dstfld2, $name).
				' close_window(); return false;');
			
			$table->AddRow($description);

			unset($description);
		}
		$table->Show();
	}
	elseif($srctbl == "plain_text")
	{
		$table = new CTableInfo(S_NO_ITEMS_DEFINED);
		$table->SetHeader(array(
			(isset($hostid) ? null : S_HOST),
			S_DESCRIPTION,
			S_TYPE,
			S_TYPE_OF_INFORMATION,
			S_STATUS
			));

		$sql = 'SELECT n.name as node_name,h.host,i.*,i.key_ '.
				' FROM hosts h,items i '.
					' left join nodes n on n.nodeid='.DBid2nodeid('i.itemid').
				' WHERE h.hostid=i.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND i.status='.ITEM_STATUS_ACTIVE.
					' AND '.DBin_node('i.itemid', $nodeid).
					' AND h.hostid not in ('.$denyed_hosts.')'.
					($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : '').
					($real_hosts ? " and h.status<>".HOST_STATUS_TEMPLATE : '');

		if(isset($hostid)) 
			$sql .= ' AND h.hostid='.$hostid;

		$sql .= ' ORDER BY h.host, i.description, i.key_, i.itemid';
			
		$result = DBselect($sql);
		while($row = DBfetch($result))
		{
			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
			$row["description"] = item_description($row["description"],$row["key_"]);
			
			$description = new CLink($row["description"],"#","action");

			$row["description"] = $row['node_name'].$row['host'].':'.$row["description"];

			$description->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row['itemid']).
				get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
				' close_window(); return false;');

			$table->AddRow(array(
				(isset($hostid) ? null : $row['host']),
				$description,
				item_type2str($row['type']),
				item_value_type2str($row['value_type']),
				new CSpan(item_status2str($row['status']),item_status2style($row['status']))
				));
		}
		$table->Show();
	}
	elseif($srctbl == 'screens')
	{
		require_once "include/screens.inc.php";

		$table = new CTableInfo(S_NO_NODES_DEFINED);
		$table->SetHeader(S_NAME);

		$result = DBselect('select screenid,name from screens where '.DBin_node('screenid',$nodeid).' order by name');
		while($row=DBfetch($result))
		{
			if(!screen_accessiable($row["screenid"], PERM_READ_ONLY))
				continue;

			$name = new CLink($row["name"],"#","action");
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				' close_window(); return false;');

			$table->AddRow($name);
		}

		$table->Show();
	}
	elseif($srctbl == 'screens2')
	{
		require_once "include/screens.inc.php";

		$table = new CTableInfo(S_NO_NODES_DEFINED);
		$table->SetHeader(S_NAME);

		$result = DBselect('SELECT DISTINCT n.name as node_name,s.screenid,s.name '.
							' FROM screens s '.
								' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('s.screenid').
							' WHERE '.DBin_node('s.screenid',$nodeid).
							' ORDER BY s.name');
		while($row=DBfetch($result))
		{
			if(!screen_accessiable($row["screenid"], PERM_READ_ONLY))
				continue;
			if(!screen_accessiable($row['screenid'], PERM_READ_ONLY)) continue;
			if(check_screen_recursion($_REQUEST['screenid'],$row['screenid'])) continue;
			
			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';

			$name = new CLink($row['name'],'#','action');			
			$row['name'] = $row['node_name'].$row['name'];
			
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				' close_window(); return false;');

			$table->AddRow($name);
		}

		$table->Show();
	}
	elseif($srctbl == "overview")
	{
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->SetHeader(S_NAME);

		$result = DBselect('SELECT DISTINCT n.name as node_name,g.groupid,g.name '.
						' FROM hosts_groups hg,hosts h,groups g '.
							' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('g.groupid').
						' WHERE '.DBin_node('g.groupid',$nodeid).
							' AND g.groupid=hg.groupid '.
							' AND hg.hostid=h.hostid '.
							' AND h.status='.HOST_STATUS_MONITORED.
						' ORDER BY g.name');
		while($row=DBfetch($result))
		{
			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';

			$name = new CLink($row['name'],'#','action');			
			$row['name'] = $row['node_name'].$row['name'];
			
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				' close_window(); return false;');

			$table->AddRow($name);
		}

		$table->Show();
	}
	else if($srctbl == 'host_group_scr'){
		$accessible_groups	= get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY);

		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->SetHeader(array(S_NAME));

		$db_groups = DBselect('SELECT DISTINCT n.name as node_name,g.groupid,g.name,n.nodeid '.
						' FROM hosts_groups hg, groups g '.
							' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('g.groupid').
						' WHERE g.groupid IN ('.$accessible_groups.') '.
							' AND '.DBin_node('g.groupid',$nodeid).
						' ORDER BY n.nodeid,g.name');
		
		$all = false;
		
		while($row = DBfetch($db_groups))
		{
			$row['node_name'] = isset($row['node_name'])?'('.$row['node_name'].') ':'';
			
			if(!$all){
				$name = new CLink(bold(S_MINUS_ALL_GROUPS_MINUS),'#','action');
				$name->SetAction(
					get_window_opener($dstfrm, $dstfld1, create_id_by_nodeid(0,$nodeid)).
					get_window_opener($dstfrm, $dstfld2, $row['node_name'].S_MINUS_ALL_GROUPS_MINUS).
					' return close_window();');
				
				$table->AddRow($name);
				$all = true;
			}

			$name = new CLink($row['name'],'#','action');			
			$row['name'] = $row['node_name'].$row['name'];
			
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
				' return close_window();');

			$table->AddRow($name);
		}
		$table->Show();
	}
?>
<?php

include_once 'include/page_footer.php';

?>
