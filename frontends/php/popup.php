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

	$srctbl		= get_request("srctbl",  '');	// source table name

	switch($srctbl)
	{
		case 'templates':
			$page["title"] = "S_TEMPLATES_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'hosts':
			$page["title"] = "S_HOSTS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
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
			$min_user_type = USER_TYPE_SUPER_ADMIN;
			break;
		case 'items':
			$page["title"] = "S_ITEMS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'help_items':
			$page["title"] = "S_STANDARD_ITEMS_BIG";
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
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
	
	insert_confirm_javascript();

	if(defined($page["title"]))     $page["title"] = constant($page["title"]);
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"dstfrm" =>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	NULL),
		"dstfld1"=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	NULL),
		"dstfld2"=>	array(T_ZBX_STR, O_OPT,P_SYS,	NOT_EMPTY,	NULL),
		"srctbl" =>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	NULL),
		"srcfld1"=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	NULL),
		"srcfld2"=>	array(T_ZBX_STR, O_OPT,P_SYS,	NOT_EMPTY,	NULL),
		"nodeid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		NULL),
		"groupid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		NULL),
		"hostid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		NULL),
		"templates"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	NULL),
		"existed_templates"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	NULL),
		"only_hostid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		NULL),
		"monitored_hosts"=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	NULL),
		
		"select"=>	array(T_ZBX_STR,	O_OPT,	P_SYS|P_ACT,	NULL,	NULL)
	);

	check_fields($fields);

	$dstfrm		= get_request("dstfrm",  '');	// destination form
	$dstfld1	= get_request("dstfld1", '');	// output field on destination form
	$dstfld2	= get_request("dstfld2", '');	// second output field on destination form
	$srcfld1	= get_request("srcfld1", '');	// source table field [can be different from fields of source table]
	$srcfld2	= get_request("srcfld2", '');	// second source table field [can be different from fields of source table]
	
	$monitored_hosts = get_request("monitored_hosts", 0);
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
		return "window.opener.document.forms['".addslashes($frame)."'].".addslashes($field).".value='".addslashes($value)."';";
	}
?>
<?php
	$frmTitle = new CForm();
		
	if($monitored_hosts)
		$frmTitle->AddVar('monitored_hosts', 1);

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
		
	if(in_array($srctbl,array("triggers","logitems","items")))
	{
		array_push($validation_param, "always_select_first_host");
		validate_group_with_host(PERM_READ_LIST,$validation_param);
	}
	elseif(in_array($srctbl,array("hosts","templates")))
	{
		validate_group(PERM_READ_LIST,$validation_param);
	}

	$accessible_nodes	= get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_LIST);
	$denyed_hosts		= get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT);
	$accessible_hosts	= get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
	$nodeid			= $ZBX_CURNODEID;

	if(isset($only_hostid))
	{
		if(!isset($_REQUEST["hostid"]) || $_REQUEST["hostid"]!=$only_hostid) access_deny();
		$hostid = $only_hostid;
	}
	else
	{
		if(in_array($srctbl,array("hosts","host_group","triggers","logitems","items")))
		{
			if(ZBX_DISTRIBUTED)
			{
				$nodeid = get_request("nodeid", $ZBX_CURNODEID);
				$cmbNode = new CComboBox("nodeid", $nodeid, "submit()");
				$db_nodes = DBselect("select * from nodes where nodeid in (".$accessible_nodes.")");
				while($node_data = DBfetch($db_nodes))
				{
					$cmbNode->AddItem($node_data['nodeid'], $node_data['name']);
					if($nodeid == $node_data['nodeid']) $ok = true;
				}
				$frmTitle->AddItem(array(SPACE,S_NODE,SPACE,$cmbNode));
			}
		}	
		if(!isset($ok)) $nodeid = $ZBX_CURNODEID;
		unset($ok);
		
		if(in_array($srctbl,array("hosts","templates","triggers","logitems","items")))
		{
			$groupid = get_request("groupid",get_profile("web.popup.groupid",0));
			
			$cmbGroups = new CComboBox("groupid",$groupid,"submit()");
			$cmbGroups->AddItem(0,S_ALL_SMALL);
			$db_groups = DBselect("select distinct g.groupid,g.name from groups g, hosts_groups hg, hosts h ".
				" where ".DBid2nodeid("g.groupid")."=".$nodeid.
				" and g.groupid=hg.groupid and hg.hostid in (".$accessible_hosts.") ".
				" and hg.hostid = h.hostid ".
				($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : "").
				" order by name");
			while($group = DBfetch($db_groups))
			{
				$cmbGroups->AddItem($group["groupid"],$group["name"]);
				if($groupid == $group["groupid"]) $ok = true;
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
			$cmbTypes->AddItem(ITEM_TYPE_ZABBIX,S_ZABBIX_AGENT);
			$cmbTypes->AddItem(ITEM_TYPE_SIMPLE,S_SIMPLE_CHECK);
			$cmbTypes->AddItem(ITEM_TYPE_INTERNAL,S_ZABBIX_INTERNAL);
			$cmbTypes->AddItem(ITEM_TYPE_AGGREGATE,S_ZABBIX_AGGREGATE);
			$frmTitle->AddItem(array(S_TYPE,SPACE,$cmbTypes));
		}
		if(in_array($srctbl,array("triggers","logitems","items")))
		{
			$hostid = get_request("hostid",get_profile("web.popup.hostid",0));
			$cmbHosts = new CComboBox("hostid",$hostid,"submit()");
			
			$sql = "select distinct h.hostid,h.host from hosts h";
			if(isset($groupid))
			{
				$sql .= ",hosts_groups hg where ".
					" h.hostid=hg.hostid and hg.groupid=".$groupid." and ";
			}
			else
			{
				$sql .= " where ";
				$cmbHosts->AddItem(0,S_ALL_SMALL);
			}

			$sql .= DBid2nodeid("h.hostid")."=".$nodeid.
				" and h.hostid in (".$accessible_hosts.")".
				($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : "");

			$db_hosts = DBselect($sql);
			while($host = DBfetch($db_hosts))
			{
				$cmbHosts->AddItem($host["hostid"],$host["host"]);
				if($hostid == $host["hostid"]) $ok = true;
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
				" window.close();");

			$frmTitle->AddItem(array(SPACE,$btnEmpty));
		}
	}
	show_table_header($page["title"], $frmTitle);
?>
<?php
	if($srctbl == "hosts")
	{
		$table = new CTableInfo(S_NO_HOSTS_DEFINED);
		$table->SetHeader(array(S_HOST,S_IP,S_PORT,S_STATUS,S_AVAILABILITY));

		$sql = "select distinct h.* from hosts h";
		if(isset($groupid))
			$sql .= ",hosts_groups hg where hg.groupid=".$groupid.
				" and h.hostid=hg.hostid and ";
		else
			$sql .= " where ";

		$sql .= DBid2nodeid("h.hostid")."=".$nodeid.
				" and h.hostid in (".$accessible_hosts.") ".
				($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : "").
				" order by h.host,h.hostid";


		$db_hosts = DBselect($sql);
		while($host = DBfetch($db_hosts))
		{
			$name = new CLink($host["host"],"#","action");
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $host[$srcfld1]).
				get_window_opener($dstfrm, $dstfld2, $host[$srcfld2]).
				" window.close();");

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

			if($host["available"] == HOST_AVAILABLE_TRUE)	
				$available=new CSpan(S_AVAILABLE,"off");
			else if($host["available"] == HOST_AVAILABLE_FALSE)
				$available=new CSpan(S_NOT_AVAILABLE,"on");
			else if($host["available"] == HOST_AVAILABLE_UNKNOWN)
				$available=new CSpan(S_UNKNOWN,"unknown");

			$table->AddRow(array(
				$name,
				$host["useip"]==1 ? $host["ip"] : "-",
				$host["port"],
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
?>

<script language="JavaScript" type="text/javascript">
<!--
function add_template(formname,id,name)
{
        var msg = '';
        var form = window.opener.document.forms[formname];
        if(!form)
        {
                alert('form '+formname+' not exist');
                window.close();
        }

        new_variable = window.opener.document.createElement('input');
        new_variable.type = 'hidden';
        new_variable.name = 'templates[' + id + ']';
        new_variable.value = name;

        form.appendChild(new_variable);

        return true;
}
-->
</script>

<?php
				foreach($new_templates as $id => $name)
				{
?>

<script language="JavaScript" type="text/javascript">
<!--
	add_template(
		 '<?php echo $dstfrm; ?>',
		 '<?php echo $id; ?>',
		 '<?php echo $name; ?>'
	);
-->
</script>

<?php
				} // foreach new_templates
?>

<script language="JavaScript" type="text/javascript">
<!--
        var form = window.opener.document.forms['<?php echo $dstfrm; ?>'];
        form.submit();
	window.close();
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

		$sql .= DBid2nodeid("h.hostid")."=".$nodeid.
				" and h.hostid in (".$accessible_hosts.") ".
				" and h.status=".HOST_STATUS_TEMPLATE.
				" order by h.host,h.hostid";

		$db_hosts = DBselect($sql);
		while($host = DBfetch($db_hosts))
		{
			$chk = new CCheckBox('templates['.$host["hostid"].']',isset($templates[$host["hostid"]]),
					NULL,$host["host"]);
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
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->SetHeader(array(S_NAME));

		$db_groups = DBselect("select distinct g.groupid,g.name from groups g, hosts_groups hg ".
			" where ".DBid2nodeid("g.groupid")."=".$nodeid.
			" and g.groupid=hg.groupid and hg.hostid in (".$accessible_hosts.") ".
			" order by name");
		while($row = DBfetch($db_groups))
		{
			$name = new CLink($row["name"],"#","action");
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
				" window.close();");

			$table->AddRow($name);
		}
		$table->Show();
	}
	elseif($srctbl == "usrgrp")
	{
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->SetHeader(array(S_NAME));

		$result = DBselect("select * from usrgrp where ".DBid2nodeid("usrgrpid")."=".$ZBX_CURNODEID." order by name");
		while($row = DBfetch($result))
		{
			$name = new CLink($row["name"],"#","action");
			$name->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
				" window.close();");

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
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				" window.close();");

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


		$sql = "select h.host,t.*,count(d.triggerid_up) as dep_count ".
			" from hosts h,items i,functions f, triggers t left join trigger_depends d on d.triggerid_down=t.triggerid ".
			" where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid".
			" and ".DBid2nodeid("t.triggerid")."=".$nodeid.
			" and h.hostid not in (".$denyed_hosts.")".
			($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : "");

		if(isset($hostid)) 
			$sql .= " and h.hostid=$hostid";

		$sql .= " group by h.host, t.triggerid".
			" order by h.host,t.description";

		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$exp_desc = expand_trigger_description_by_data($row);
			$description = new CLink($exp_desc,"#","action");
			$description->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				get_window_opener($dstfrm, $dstfld2, $exp_desc).
				" window.close();");

			if($row['dep_count'] > 0)
			{
				$description = array($description);
				
				$result1=DBselect("select h.host,t.triggerid,t.description ".
					" from triggers t,trigger_depends d,functions f,items i,hosts h ".
					" where t.triggerid=d.triggerid_up and d.triggerid_down=".$row["triggerid"].
					" and ".DBid2nodeid("t.triggerid")."=".$nodeid.
					" and t.triggerid=f.triggerid and f.itemid=i.itemid and i.hostid=h.hostid");
				if($row1=DBfetch($result1))
				{
					array_push($description,BR.BR."<strong>".S_DEPENDS_ON."</strong>".SPACE.BR);
					do
					{
						array_push($description,expand_trigger_description_by_data($row1).BR);
					} while( $row1=DBfetch($result1));
					array_push($description,BR);
				}
			}

			if($row["status"] == TRIGGER_STATUS_DISABLED)
			{
				$status= new CSpan(S_DISABLED, 'disabled');
			}
			else if($row["status"] == TRIGGER_STATUS_UNKNOWN)
			{
				$status= new CSpan(S_UNCNOWN, 'uncnown');
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
?>

<script language="JavaScript" type="text/javascript">
<!--
function add_variable(formname,value)
{
        var msg = '';
        var form = window.opener.document.forms[formname];
        if(!form)
        {
                alert('form '+formname+' not exist');
                window.close();
        }

        new_variable = window.opener.document.createElement('input');
        new_variable.type = 'hidden';
        new_variable.name = 'itemid[]';
        new_variable.value = value;

        form.appendChild(new_variable);

        var element = form.elements['itemid'];
        if(element)     element.name = 'itemid[]';

        form.submit();
	window.close();
        return true;
}
-->
</script>

<?php
		$table = new CTableInfo(S_NO_ITEMS_DEFINED);

		$table->SetHeader(array(
			!isset($hostid) ? S_HOST : NULL,
			S_DESCRIPTION,S_KEY,nbsp(S_UPDATE_INTERVAL),
			S_STATUS));

		$db_items = DBselect("select distinct h.host,i.* from items i,hosts h".
			" where i.value_type=".ITEM_VALUE_TYPE_LOG." and h.hostid=i.hostid".
			" and ".DBid2nodeid("i.itemid")."=".$nodeid.
			(isset($hostid) ? " and ".$hostid."=i.hostid " : "").
			" and i.hostid in (".$accessible_hosts.")".
			($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : "").
			" order by h.host,i.description, i.key_, i.itemid");

		while($db_item = DBfetch($db_items))
		{
			$description = new CLink(item_description($db_item["description"],$db_item["key_"]),"#","action");
			$description->SetAction("return add_variable('".$dstfrm."',".$db_item["itemid"].");");

			switch($db_item["status"]){
				case 0: $status=new CCol(S_ACTIVE,"enabled");		break;
				case 1: $status=new CCol(S_DISABLED,"disabled");	break;
				case 3: $status=new CCol(S_NOT_SUPPORTED,"unknown");	break;
				default:$status=S_UNKNOWN;
			}

			$table->AddRow(array(
				!isset($hostid) ? $db_item["host"] : NULL,
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
			" where h.hostid=i.hostid and ".DBid2nodeid("i.itemid")."=".$nodeid.
			" and h.hostid not in (".$denyed_hosts.")".
			($monitored_hosts ? " and h.status=".HOST_STATUS_MONITORED : "");

		if(isset($hostid)) 
			$sql .= " and h.hostid=$hostid";

		$sql .= " order by h.host";
			
		$result = DBselect($sql);
		while($row = DBfetch($result))
		{
			$row["description"] = item_description($row["description"],$row["key_"]);
			
			$description = new CLink($row["description"],"#","action");
			$description->SetAction(
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
				" window.close();");

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
?>
<?php

include_once "include/page_footer.php";

?>
