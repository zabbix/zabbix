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
	include "include/config.inc.php";
	include "include/forms.inc.php";

	$page["title"] = "S_CONFIGURATION_OF_TRIGGERS";
	$page["file"] = "triggers.php";

	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
        if(!check_anyright("Host","U"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_page_footer();
                exit;
        }
?>

<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		"hostid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'isset({save})'),

		"triggerid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'{form}=="update"'),

		"description"=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})'),
		"expression"=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})'),
		"priority"=>	array(T_ZBX_INT, O_OPT,  NULL,  IN("0,1,2,3,4,5"),'isset({save})'),
		"comments"=>	array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),
		"url"=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),
		"disabled"=>	array(T_ZBX_STR, O_OPT,  NULL,	NULL,NULL),

		"dependences"=>		array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),
		"new_dependence"=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({add_dependence})'),
		"rem_dependence"=>	array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),

		"g_triggerid"=>	array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),

/* actions */
		"add_dependence"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"del_dependence"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"group_enable"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"group_disable"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"group_delete"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields);
?>
<?php
	$_REQUEST["hostid"]=get_request("hostid",get_profile("web.latest.hostid",0));
	$_REQUEST["groupid"]=get_request("groupid",get_profile("web.latest.groupid",0));
	update_profile("web.latest.hostid",$_REQUEST["hostid"]);
	update_profile("web.latest.groupid",$_REQUEST["groupid"]);
	update_profile("web.menu.config.last",$page["file"]);
?>

<?php

/* FORM ACTIONS */
	if(isset($_REQUEST["save"]))
	{
		if(validate_expression($_REQUEST["expression"])==0)
		{
			$now=mktime();
			if(isset($_REQUEST["disabled"]))	{ $status=1; }
			else			{ $status=0; }

			if(isset($_REQUEST["triggerid"])){
				$result=update_trigger($_REQUEST["triggerid"],
					$_REQUEST["expression"],$_REQUEST["description"],
					$_REQUEST["priority"],$status,$_REQUEST["comments"],$_REQUEST["url"]);

				$triggerid = $_REQUEST["triggerid"];
				show_messages($result, S_TRIGGER_UPDATED, S_CANNOT_UPDATE_TRIGGER);
			} else {
				$triggerid=add_trigger($_REQUEST["expression"],$_REQUEST["description"],
					$_REQUEST["priority"],$status,$_REQUEST["comments"],$_REQUEST["url"]);

				$result = $triggerid;
				show_messages($triggerid, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
			}

			if($result)
			{
				delete_dependencis_by_triggerid($triggerid);

				if(isset($_REQUEST["dependences"])) foreach($_REQUEST["dependences"] as $val)
				{
					$result=add_trigger_dependency(	$triggerid, $val);
				}

//				update_trigger_from_linked_hosts($_REQUEST["triggerid"]);
				unset($_REQUEST["form"]);
			}
		}
		else
		{
			show_error_message(S_INVALID_TRIGGER_EXPRESSION);
		}
	}
	elseif(isset($_REQUEST["delete"])&&isset($_REQUEST["triggerid"]))
	{
		delete_trigger_from_templates($_REQUEST["triggerid"]);
		$result=delete_trigger($_REQUEST["triggerid"]);
		show_messages($result, S_TRIGGER_DELETED, S_CANNOT_DELETE_TRIGGER);
		if($result){
			unset($_REQUEST["form"]);
			unset($_REQUEST["triggerid"]);
		}
	}
/* DEPENDENCE ACTIONS */
	elseif(isset($_REQUEST["add_dependence"])&&isset($_REQUEST["new_dependence"]))
	{
		if(!isset($_REQUEST["dependences"]))
			$_REQUEST["dependences"] = array();

		if(!in_array($_REQUEST["new_dependence"], $_REQUEST["dependences"]))
			array_push($_REQUEST["dependences"], $_REQUEST["new_dependence"]);
	}
	elseif(isset($_REQUEST["del_dependence"])&&isset($_REQUEST["rem_dependence"]))
	{
		if(isset($_REQUEST["dependences"])){
			foreach($_REQUEST["dependences"]as $key => $val)
			{
				if(!in_array($val, $_REQUEST["rem_dependence"]))	continue;
				unset($_REQUEST["dependences"][$key]);
			}
		}
	}
/* GROUP ACTIONS */
	elseif(isset($_REQUEST["group_enable"])&&isset($_REQUEST["g_triggerid"]))
	{
		$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i,".
			" functions f where f.itemid=i.itemid and h.hostid=i.hostid and".
			" t.triggerid=f.triggerid and h.hostid=".$_REQUEST["hostid"]);
		while($row=DBfetch($result))
		{
			if(!in_array($row["triggerid"], $_REQUEST["g_triggerid"]))	continue;
			$result2=update_trigger_status($row["triggerid"],0);
		}
		show_messages(true, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
	}
	elseif(isset($_REQUEST["group_disable"])&&isset($_REQUEST["g_triggerid"]))
	{
		$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i".
			" ,functions f where f.itemid=i.itemid and h.hostid=i.hostid and".
			" t.triggerid=f.triggerid and h.hostid=".$_REQUEST["hostid"]);
		while($row=DBfetch($result))
		{
			if(!in_array($row["triggerid"], $_REQUEST["g_triggerid"]))	continue;
			$result2=update_trigger_status($row["triggerid"],1);
		}
		show_messages(true, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
	}
	elseif(isset($_REQUEST["group_delete"])&&isset($_REQUEST["g_triggerid"]))
	{
		$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i,".
			" functions f where f.itemid=i.itemid and h.hostid=i.hostid and".
			" t.triggerid=f.triggerid and h.hostid=".$_REQUEST["hostid"]);
		while($row=DBfetch($result))
		{
			if(!in_array($row["triggerid"], $_REQUEST["g_triggerid"]))	continue;
			$result2=delete_trigger($row["triggerid"]);
		}
		show_messages(TRUE, S_TRIGGERS_DELETED, S_CANNOT_DELETE_TRIGGERS);
	}
?>

<?php
?>

<?php

	if(!isset($_REQUEST["form"]))
	{
/* filter panel */
		$form = new CForm();

		$_REQUEST["groupid"] = get_request("groupid",0);
		$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit();");
		$cmbGroup->AddItem(0,S_ALL_SMALL);
		$result=DBselect("select groupid,name from groups order by name");
		while($row=DBfetch($result))
		{
	// Check if at least one host with read permission exists for this group
			$result2=DBselect("select h.hostid,h.host from hosts h,hosts_groups hg".
				" where hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid and".
				" h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host");
			while($row2=DBfetch($result2))
			{
				if(!check_right("Host","U",$row2["hostid"]))	continue;
				$cmbGroup->AddItem($row["groupid"],$row["name"]);
				break;
			}
		}
		$form->AddItem(S_GROUP.SPACE);
		$form->AddItem($cmbGroup);

		if(isset($_REQUEST["groupid"]) && $_REQUEST["groupid"]>0)
		{
			$sql="select h.hostid,h.host from hosts h,hosts_groups hg".
				" where hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid and".
				" h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host";
		}
		else
		{
			$sql="select h.hostid,h.host from hosts h where h.status<>".HOST_STATUS_DELETED.
				" group by h.hostid,h.host order by h.host";
		}

		$result=DBselect($sql);

		$_REQUEST["hostid"] = get_request("hostid",0);
		$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit();");

		$correct_hostid='no';
		$first_hostid = -1;
		while($row=DBfetch($result))
		{
			if(!check_right("Host","U",$row["hostid"]))	continue;
			$cmbHosts->AddItem($row["hostid"],$row["host"]);

			if($_REQUEST["hostid"]!=0){
				if($_REQUEST["hostid"]==$row["hostid"])
					$correct_hostid = 'ok';
			}
			if($first_hostid <= 0)
				$first_hostid = $row["hostid"];
		}
		if($correct_hostid!='ok')
			$_REQUEST["hostid"] = $first_hostid;

		$form->AddItem(SPACE.S_HOST.SPACE);
		$form->AddItem($cmbHosts);
		$form->AddItem(SPACE."|".SPACE);
		$form->AddItem(new CButton("form",S_CREATE_TRIGGER));

		show_header2(S_CONFIGURATION_OF_TRIGGERS_BIG, $form);

/* TABLE */
		$form = new CForm('triggers.php');
		$form->SetName('triggers');
		$form->AddVar('hostid',$_REQUEST["hostid"]);

		$table = new CTableInfo();
		$table->setHeader(array(
			array(	new CCheckBox("all_items",NULL,NULL,
					"CheckAll('".$form->GetName()."','all_items');"),
				S_ID),
			S_NAME,S_EXPRESSION, S_SEVERITY, S_STATUS, S_ERROR));

		$result=DBselect("select distinct h.hostid,h.host,t.triggerid,t.expression,t.description,".
			"t.status,t.value,t.priority,t.error from triggers t,hosts h,items i,functions".
			" f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid".
			" and h.hostid=".$_REQUEST["hostid"]." order by h.host,t.description");
		while($row=DBfetch($result))
		{
			if(check_right_on_trigger("R",$row["triggerid"]) == 0)
			{
				continue;
			}
	
			$description = array(new CLink(expand_trigger_description($row["triggerid"]),
				"triggers.php?form=update&triggerid=".$row["triggerid"].
					"&hostid=".$row["hostid"], 'action'));

			$id= array(new CCheckBox(
					"g_triggerid[]",	/* name */
					NULL,			/* checked */
					NULL,			/* caption */
					NULL,			/* action */
					$row["triggerid"]),	/* value */
				$row["triggerid"]);

			$sql="select t.triggerid,t.description from triggers t,trigger_depends d".
				" where t.triggerid=d.triggerid_up and d.triggerid_down=".$row["triggerid"];
			$result1=DBselect($sql);
			if(DBnum_rows($result1)>0)
			{
				array_push($description,BR.BR."<strong>".S_DEPENDS_ON."</strong>:&nbsp;".BR);
				while($row1=DBfetch($result1))
				{
					array_push($description,expand_trigger_description($row1["triggerid"]).BR);
				}
				array_push($description,BR);
			}
	
			if($row["priority"]==0)		$priority=S_NOT_CLASSIFIED;
			elseif($row["priority"]==1)	$priority=S_INFORMATION;
			elseif($row["priority"]==2)	$priority=S_WARNING;
			elseif($row["priority"]==3)	$priority=array("value"=>S_AVERAGE,"class"=>"average");
			elseif($row["priority"]==4)	$priority=array("value"=>S_HIGH,"class"=>"high");
			elseif($row["priority"]==5)	$priority=array("value"=>S_DISASTER,"class"=>"disaster");
			else				$priority=$row["priority"];

			if($row["status"] == TRIGGER_STATUS_DISABLED)
			{
				$status= new CLink(S_DISABLED,
					"triggers.php?group_enable=1&g_triggerid%5B%5D=".$row["triggerid"].
						"&hostid=".$row["hostid"],
					'disabled');
			}
			else if($row["status"] == TRIGGER_STATUS_UNKNOWN)
			{
				$status= new CLink(S_UNCNOWN,
					"triggers.php?group_disable=1&g_triggerid%5B%5D=".$row["triggerid"].
						"&hostid=".$row["hostid"],
					'uncnown');
			}
			else if($row["status"] == TRIGGER_STATUS_ENABLED)
			{
				$status= new CLink(S_ENABLED,
					"triggers.php?group_disable=1&g_triggerid%5B%5D=".$row["triggerid"].
						"&hostid=".$row["hostid"],
					'enabled');
			}

			if($row["status"] != TRIGGER_STATUS_UNKNOWN)	$row["error"]="&nbsp;";

			if($row["error"]=="")		$row["error"]="&nbsp;";

			$table->addRow(array(
				$id,
				$description,
				explode_exp($row["expression"],1),
				$priority,
				$status,
				$row["error"]
			));
		}
		
		$footerButtons = array();
		array_push($footerButtons, new CButton('group_enable','enable selected',
			"return Confirm('".S_ENABLE_SELECTED_TRIGGERS_Q."');"));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('group_disable','disable selected',
			"return Confirm('Disable selected triggers?');"));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('group_delete','delete selected',
			"return Confirm('".S_DISABLE_SELECTED_TRIGGERS_Q."');"));
		$table->SetFooter(new CCol($footerButtons),'table_footer');

		$form->AddItem($table);
		$form->Show();
	}
	else
	{
/* FORM */
		$result=DBselect("select count(*) as cnt from hosts");
		$row=DBfetch($result);
		if($row["cnt"]>0)
		{
			insert_trigger_form();
		} 
	}
?>

<?php
	show_page_footer();
?>
