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

	$dstfrm		= get_request("dstfrm",0);	// destination form
	$dstfld1	= get_request("dstfld1", 0);	// output field on destination form
	$dstfld2	= get_request("dstfld2", 0);	// second output field on destination form
	$srctbl		= get_request("srctbl", 0);	// source table name
	$srcfld1	= get_request("srcfld1", 0);	// source table field [can be different from fields of source table]
	$srcfld2	= get_request("srcfld2", 0);	// second source table field [can be different from fields of source table]

	if($srctbl == "hosts")	{  $page["title"] = "S_HOSTS_BIG";	$right_src = "Host"; }
	if($srctbl == "triggers"){ $page["title"] = "S_TRIGGERS_BIG";	$right_src = "Triggers"; }

	if(!isset($page["title"]))
	{
		show_header("Error",0,1);
		error("Incorrect URL");
		show_messages();
		exit;
	}

	$page["file"] = "popup.php";
	show_header($page["title"],0,1);
	insert_confirm_javascript();

	if(defined($page["title"]))     $page["title"] = constant($page["title"]);
?>

<?php
	if(!check_anyright($right_src,"R"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
?>
<?php
	$frmTitle = new CForm();
	$frmTitle->AddVar("dstfrm",	$dstfrm);
	$frmTitle->AddVar("dstfld1",	$dstfld1);
	$frmTitle->AddVar("dstfld2",	$dstfld2);
	$frmTitle->AddVar("srctbl",	$srctbl);
	$frmTitle->AddVar("srcfld1",	$srcfld1);
	$frmTitle->AddVar("srcfld2",	$srcfld2);
	
	
	if(in_array($srctbl,array("hosts","triggers")))
	{
		$groupid = get_request("groupid",get_profile("web.popup.groupid",0));
		$cmbGroups = new CComboBox("groupid",$groupid,"submit()");
		$cmbGroups->AddItem(0,S_ALL_SMALL);
		$db_groups = DBselect("select groupid,name from groups order by name");
		while($group = DBfetch($db_groups))
		{ // Check if at least one host with read permission exists for this group
			$db_hosts = DBselect("select distinct h.hostid from hosts h,items i,hosts_groups hg".
				" where h.hostid=i.hostid and hg.groupid=".$group["groupid"]." and hg.hostid=h.hostid".
				" and h.status not in (".HOST_STATUS_DELETED.")group by h.hostid,h.host order by h.host");
			while($host = DBfetch($db_hosts))
			{
				if(!check_right("Host","R",$host["hostid"]))	continue;
				$cmbGroups->AddItem($group["groupid"],$group["name"]);
				break;
			}
		}
		$frmTitle->AddItem(array(S_GROUP,SPACE,$cmbGroups));
		if($groupid == 0) unset($groupid);
		else update_profile("web.popup.groupid",$groupid);
	}

	if(in_array($srctbl,array("triggers")))
	{
		$hostid = get_request("hostid",get_profile("web.popup.hostid",0));
		$cmbHosts = new CComboBox("hostid",$hostid,"submit()");
		
		$sql = "select h.hostid,h.host from hosts h";
		if(isset($groupid))
			$sql .= ",hosts_groups hg where h.hostid=hg.hostid and hg.groupid=$groupid";
		else
			$cmbHosts->AddItem(0,S_ALL_SMALL);
		$first_hostid = 0;
		$db_hosts = DBselect($sql);
		while($host = DBfetch($db_hosts))
		{
			if(!check_right("Host","R",$host["hostid"]))	continue;
			$cmbHosts->AddItem($host["hostid"],$host["host"]);
			if($hostid == $host["hostid"]) $correct_host = 1;
			if($first_hostid == 0)	$first_hostid = $host["hostid"];
		}
		if(!isset($correct_host))
		{
			if($hostid != 0) $hostid = $first_hostid;
			$cmbHosts->SetValue($hostid);
		}
		$frmTitle->AddItem(array(SPACE,S_HOST,SPACE,$cmbHosts));
		if($hostid == 0) unset($hostid);
		else update_profile("web.popup.hostid",$hostid);
	}

	$btnEmpty = new CButton("empty",S_EMPTY,"window.opener.document.forms[\"$dstfrm\"].$dstfld1.value=\"0\";".
		" window.opener.document.forms[\"$dstfrm\"].$dstfld2.value=\"\";".
		" window.close();");

	$frmTitle->AddItem(array(SPACE,$btnEmpty));

	show_header2($page["title"], $frmTitle);
?>

<?php
	if($srctbl == "hosts")
	{
		$table = new CTableInfo(S_NO_HOSTS_DEFINED);
		$table->SetHeader(array(S_HOST,S_IP,S_PORT,S_STATUS,S_AVAILABILITY));

		$sql = "select * from hosts h";
		if(isset($groupid))
			$sql .= ",hosts_groups hg where h.hostid=hg.hostid and hg.groupid=$groupid";

		$db_hosts = DBselect($sql);
		while($host = DBfetch($db_hosts))
		{
			if(!check_right("Host","R",$host["hostid"]))	continue;
			$name = new CLink($host["host"],"#","action");
			$name->SetAction(
				"window.opener.document.forms[\"$dstfrm\"].$dstfld1.value=\"".$host[$srcfld1]."\";".
				" window.opener.document.forms[\"$dstfrm\"].$dstfld2.value=\"".$host[$srcfld2]."\";".
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

			$table->addRow(array(
				$name,
				$host["useip"]==1 ? $host["ip"] : "-",
				$host["port"],
				$status,
				$available
				));
		}
		$table->show();
	}
	if($srctbl == "triggers")
	{
		$table = new CTableInfo(S_NO_TRIGGERS_DEFINED);
		$table->setHeader(array(
			S_NAME,
//			S_EXPRESSION,
			S_SEVERITY,
			S_STATUS));


		$sql = "select distinct t.*".
			" from triggers t,hosts h,items i,functions f".
			" where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid";

		if(isset($hostid)) 
			$sql .= " and h.hostid=$hostid";

		$sql .= " order by h.host,t.description";

		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if(check_right_on_trigger("R",$row["triggerid"]) == 0)
			{
				continue;
			}

			$exp_desc = expand_trigger_description($row["triggerid"]);
			$description = new CLink($exp_desc,"#","action");
			$description->SetAction(
				"window.opener.document.forms[\"$dstfrm\"].$dstfld1.value=\"".$row[$srcfld1]."\";".
				" window.opener.document.forms[\"$dstfrm\"].$dstfld2.value=\"$exp_desc\";".
				" window.close();");

			//add dependences
			$result1=DBselect("select t.triggerid,t.description from triggers t,trigger_depends d".
				" where t.triggerid=d.triggerid_up and d.triggerid_down=".$row["triggerid"]);
			if(DBnum_rows($result1)>0)
			{
				array_push($description,BR.BR."<strong>".S_DEPENDS_ON."</strong>".SPACE.BR);
				while($row1=DBfetch($result1))
				{
					array_push($description,expand_trigger_description($row1["triggerid"]).BR);
				}
				array_push($description,BR);
			}
	
			if($row["priority"]==0)		$priority=S_NOT_CLASSIFIED;
			elseif($row["priority"]==1)	$priority=S_INFORMATION;
			elseif($row["priority"]==2)	$priority=S_WARNING;
			elseif($row["priority"]==3)	$priority=new CCol(S_AVERAGE,"average");
			elseif($row["priority"]==4)	$priority=new CCol(S_HIGH,"high");
			elseif($row["priority"]==5)	$priority=new CCol(S_DISASTER,"disaster");
			else				$priority=$row["priority"];

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

			$table->addRow(array(
				$description,
//				explode_exp($row["expression"],0),
				$priority,
				$status,
			));
		}
		$table->show();
	}
?>
