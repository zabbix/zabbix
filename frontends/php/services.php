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

	$page["title"] = "S_IT_SERVICES";
	$page["file"] = "services.php";

	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(!check_anyright("Service","U"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}

	$_REQUEST["serviceid"] = get_request("serviceid",0);
	if($_REQUEST["serviceid"] == 0) unset($_REQUEST["serviceid"]);

	$_REQUEST["parentid"] = get_request("parentid", 0);

	update_profile("web.menu.config.last",$page["file"]);
?>

<?php

	if(isset($_REQUEST["delete"]))
	{
		if(isset($_REQUEST["group_serviceid"]))
		{
			foreach($_REQUEST["group_serviceid"] as $serviceid)
				delete_service($serviceid);
			show_messages(TRUE, S_SERVICE_DELETED, S_CANNOT_DELETE_SERVICE);
		}
		elseif(isset($_REQUEST["group_linkid"]))
		{
			foreach($_REQUEST["group_linkid"] as $linkid)
				delete_service_link($linkid);
			show_messages(TRUE, S_LINK_DELETED, S_CANNOT_DELETE_LINK);
		}
		elseif(isset($_REQUEST["delete_service"]))
		{
			$result=delete_service($_REQUEST["serviceid"]);
			show_messages($result, S_SERVICE_DELETED, S_CANNOT_DELETE_SERVICE);
			unset($_REQUEST["serviceid"]);
		}
		elseif(isset($_REQUEST["delete_link"]))
		{
			$result=delete_service_link($_REQUEST["linkid"]);
			show_messages($result, S_LINK_DELETED, S_CANNOT_DELETE_LINK);
			unset($_REQUEST["linkid"]);
		}
	}
	elseif(isset($_REQUEST["save_service"]))
	{
		$showsla = isset($_REQUEST["showsla"]) ? 1 : 0;
		$triggerid = isset($_REQUEST["linktrigger"]) ? $_REQUEST["triggerid"] : NULL;
		if(isset($_REQUEST["serviceid"]))
		{
			$result = update_service($_REQUEST["serviceid"],
				$_REQUEST["name"],$triggerid,$_REQUEST["algorithm"],
				$showsla,$_REQUEST["goodsla"],$_REQUEST["sortorder"]);
			show_messages($result, S_SERVICE_UPDATED, S_CANNOT_UPDATE_SERVICE);
		}
		else
		{
			$result = add_service(
				$_REQUEST["name"],$triggerid,$_REQUEST["algorithm"],
				$showsla,$_REQUEST["goodsla"],$_REQUEST["sortorder"]);
			show_messages($result, S_SERVICE_ADDED, S_CANNOT_ADD_SERVICE);
		}	
	}
	elseif(isset($_REQUEST["save_link"]))
	{
		$_REQUEST["soft"] = isset($_REQUEST["soft"]) ? 1 : 0;
		if(isset($_REQUEST["linkid"]))
		{
			$result = update_service_link($_REQUEST["linkid"],
				$_REQUEST["servicedownid"],$_REQUEST["serviceupid"],$_REQUEST["soft"]);
			show_messages($result, S_LINK_ADDED, S_CANNOT_ADD_LINK);
		}
		else
		{
			$result = add_service_link($_REQUEST["servicedownid"],$_REQUEST["serviceupid"],$_REQUEST["soft"]);
			show_messages($result, S_LINK_ADDED, S_CANNOT_ADD_LINK);
		}		
	}
	elseif(isset($_REQUEST["add_server"]))
	{
		$result=add_host_to_services($_REQUEST["serverid"],$_REQUEST["serviceid"]);
		show_messages($result, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
	}
?>

<?php
	show_table_header(S_IT_SERVICES_BIG);

	$form = new CForm();
	$form->SetName("services");

	$table = new CTableInfo();
	$table->SetHeader(array(
		array(new CCheckBox("all_services",NULL,NULL,
			"CheckAll('".$form->GetName()."','all_services');"),
			S_ID),
		S_SERVICE,
		S_STATUS_CALCULATION,
		S_TRIGGER
		));

	$sql = "select serviceid,name,algorithm,triggerid from services order by sortorder,name";
	if(isset($_REQUEST["serviceid"]))
	{
		$form->AddVar("serviceid",$_REQUEST["serviceid"]);

		$service = get_service_by_serviceid($_REQUEST["serviceid"]);
		if($service)
		{
			$childs=get_num_of_service_childs($service["serviceid"]);

			if(isset($service["triggerid"]))
				$trigger = expand_trigger_description($service["triggerid"]);
			else
				$trigger = "-";

			$table->AddRow(array(
				array(
					new CCheckBox("group_serviceid[]",NULL,NULL,NULL,$_REQUEST["serviceid"]),
					$_REQUEST["serviceid"]
				),
				new CLink(new CSpan($service["name"]." [$childs]","bold"),"services.php?serviceid=".$_REQUEST["parentid"]."#form"),
				algorithm2str($service["algorithm"]),
				$trigger
				));
			$sql = "select s.serviceid,s.name,s.algorithm,triggerid from services s, services_links sl".
				" where s.serviceid=sl.servicedownid and sl.serviceupid=".$_REQUEST["serviceid"].
				" order by s.sortorder,s.name";
		}
		else
		{
			unset($_REQUEST["serviceid"]);
		}
	}
	$db_services = DBselect($sql);
	while($service = DBfetch($db_services))
	{
		$prefix = NULL;
		if(!isset($_REQUEST["serviceid"]))
		{
			if(service_has_parent($service["serviceid"]))
				continue;
		}
		else
		{
			$prefix = " - ";
		}
		$childs=get_num_of_service_childs($service["serviceid"]);

		if(isset($service["triggerid"]))
			$trigger = expand_trigger_description($service["triggerid"]);
		else
			$trigger = "-";

		$parrent = get_request("serviceid",0);
		$table->AddRow(array(
			array(new CCheckBox("group_serviceid[]",NULL,NULL,NULL,$service["serviceid"]),$service["serviceid"]),
			array($prefix, new CLink($service["name"]." [$childs]",
				"services.php?serviceid=".$service["serviceid"]."&parentid=$parrent#form")),
			algorithm2str($service["algorithm"]),
			$trigger
			));
	}

	$table->SetFooter(new CCol(new CButton("delete","Delete selected","return Confirm('".S_DELETE_SELECTED_SERVICES."');")));
	$form->AddItem($table);
	$form->Show();
?>
<?php
	if(isset($_REQUEST["serviceid"]))
	{
		echo BR;

		show_table_header("LINKS");

		$form = new CForm();
		$form->SetName("Links");
		$form->AddVar("serviceid",$_REQUEST["serviceid"]);
		$form->AddVar("parentid",$_REQUEST["parentid"]);

		$table = new CTableInfo();
		$table->SetHeader(array(
				array(new CCheckBox("all_services",NULL,NULL,
					"CheckAll('".$form->GetName()."','all_services');"),
					S_LINK),
				S_SERVICE_1,
				S_SERVICE_2,
				S_SOFT_HARD_LINK
			));

		$result=DBselect("select sl.linkid, sl.soft, sl.serviceupid, sl.servicedownid,".
			" s1.name as serviceupname, s2.name as servicedownname".
			" from services s1, services s2, services_links sl".
			" where sl.serviceupid=s1.serviceid and sl.servicedownid=s2.serviceid".
			" and (sl.serviceupid=".$_REQUEST["serviceid"]." or sl.servicedownid=".$_REQUEST["serviceid"].")");
		$i = 1;
		while($row=DBfetch($result))
		{
			$table->AddRow(array(
				array(
					new CCheckBox("group_linkid[]",NULL,NULL,NULL,$row["linkid"]),
					new CLink(S_LINK.SPACE.$i++,
						"services.php?form=update&linkid=".$row["linkid"].url_param("serviceid"),
						"action"),
				),
				new CLink($row["serviceupname"],"services.php?serviceid=".$row["serviceupid"]),
				new CLink($row["servicedownname"],"services.php?serviceid=".$row["servicedownid"]),
				$row["soft"] == 0 ? S_HARD : S_SOFT
				));
		}
		$table->SetFooter(new CCol(new CButton("delete","Delete selected","return Confirm('".S_DELETE_SELECTED_LINKS."');")));
		$form->AddItem($table);
		$form->Show();
	}
?>

<?php
	echo BR;

	$frmService = new CFormTable(S_SERVICE);
	$frmService->SetHelp("web.services.service.php");
	$frmService->AddVar("parentid",$_REQUEST["parentid"]);
	
	if(isset($_REQUEST["serviceid"]))
	{
		$frmService->AddVar("serviceid",$_REQUEST["serviceid"]);

		$service=get_service_by_serviceid($_REQUEST["serviceid"]);

		$frmService->SetTitle(S_SERVICE." \"".$service["name"]."\"");
	}

	if(isset($_REQUEST["serviceid"]) && !isset($_REQUEST["form_refresh"]))
	{
		$name		=$service["name"];
		$algorithm	=$service["algorithm"];
		$showsla	=$service["showsla"];
		$goodsla	=$service["goodsla"];
		$sortorder	=$service["sortorder"];
		$triggerid	=$service["triggerid"];
		$linktrigger	= isset($triggerid) ? 'yes' : 'no';
		if(!isset($triggerid)) $triggerid = 0;
	}
	else
	{
		$name		= get_request("name","");
		$showsla	= get_request("showsla",0);
		$goodsla	= get_request("goodsla",99.05);
		$sortorder	= get_request("sortorder",0);
		$algorithm	= get_request("algorithm",0);
		$triggerid	= get_request("triggerid",0);
		$linktrigger	= isset($_REQUEST["linktrigger"]) ? 'yes' : 'no';
	}

	if(isset($_REQUEST["serviceid"]))
	{
		$frmService->AddVar("serviceid",$_REQUEST["serviceid"]);
	}
	$frmService->AddRow(S_NAME,new CTextBox("name",$name));

	$cmbAlg = new CComboBox("algorithm",$algorithm);
	$cmbAlg->AddItem(0,S_DO_NOT_CALCULATE);
	$cmbAlg->AddItem(1,S_MAX_BIG);
	$cmbAlg->AddItem(2,S_MIN_BIG);
	$frmService->AddRow(S_STATUS_CALCULATION_ALGORITHM, $cmbAlg);

	$frmService->AddRow(S_SHOW_SLA, new CCheckBox("showsla",$showsla,NULL,'submit();'));

	if($showsla)
		$frmService->AddRow(S_ACCEPTABLE_SLA_IN_PERCENT,new CTextBox("goodsla",$goodsla,6));
	else
		$frmService->AddVar("goodsla",$goodsla);

	$frmService->AddRow(S_LINK_TO_TRIGGER_Q, new CCheckBox("linktrigger",$linktrigger,NULL,"submit();"));

	if($linktrigger == 'yes')
	{
		if($triggerid > 0)
			$trigger = expand_trigger_description($triggerid);
		else
			$trigger = "";

		$frmService->AddRow(S_TRIGGER,array(
			new CTextBox("trigger",$trigger,32,NULL,'yes'),
			new CButton("btn1",S_SELECT,
				"return PopUp('popup.php?".
				"dstfrm=".$frmService->GetName()."&dstfld1=triggerid&dstfld2=trigger".
				"&srctbl=triggers&srcfld1=triggerid&&srcfld2=description','new_win',".
				"'width=600,height=450,resizable=1,scrollbars=1');",
				'T')
			));
		$frmService->AddVar("triggerid",$triggerid);
	}

	$frmService->AddRow(S_SORT_ORDER_0_999, new CTextBox("sortorder",$sortorder,3));

	$frmService->AddItemToBottomRow(new CButton("save_service",S_SAVE));
	if(isset($_REQUEST["serviceid"]))
	{
		$frmService->AddItemToBottomRow(SPACE);
		$frmService->AddItemToBottomRow(new CButtonDelete(
			"Delete selected service?",
			url_param("form").url_param("serviceid")."&delete_service=1"
			));
	}
	$frmService->AddItemToBottomRow(SPACE);
	$frmService->AddItemToBottomRow(new CButtonCancel("&serviceid=".get_request("parentid",0)));
	$frmService->Show();
?>

<?php
	if(isset($_REQUEST["serviceid"]))
	{
		echo BR;

		$frmLink = new CFormTable(S_LINK_TO);
		$frmLink->SetHelp("web.services.link.php");
		$frmLink->AddVar("serviceid",$_REQUEST["serviceid"]);
		$frmLink->AddVar("parentid",$_REQUEST["parentid"]);
	
		if(isset($_REQUEST["linkid"]))
		{
			$frmLink->AddVar("linkid",$_REQUEST["linkid"]);

			$link = get_services_links_by_linkid($_REQUEST["linkid"]);
			$serviceupid	= $link["serviceupid"];
			$servicedownid	= $link["servicedownid"];
			$soft		= $link["soft"];
		}
		else
		{
			$serviceupid	= get_request("serviceupid",$_REQUEST["serviceid"]);
			$servicedownid	= get_request("servicedownid",0);
			$soft		= get_request("soft",1);
		}

		$frmLink->AddVar("serviceupid",$_REQUEST["serviceid"]);

		$service = get_service_by_serviceid($_REQUEST["serviceid"]);
		$name = $service["name"];
		if(isset($service["triggerid"]))
			$name .= ": ".expand_trigger_description($service["triggerid"]);
		$frmLink->AddRow(S_SERVICE_1, new CTextBox("service",$name,60,NULL,'yes'));

		$cmbServices = new CComboBox("servicedownid",$servicedownid);
		$result=DBselect("select serviceid,triggerid,name from services where serviceid<>$serviceupid order by name");
		while($row=Dbfetch($result))
		{
			if(DBfetch(DBselect("select linkid from services_links".
				" where servicedownid<>$servicedownid and serviceupid=$serviceupid and servicedownid=".$row["serviceid"])))
				continue;

			$name = $row["name"];
			if(isset($row["triggerid"]))
				$name .= ": ".expand_trigger_description($row["triggerid"]);
			
			$cmbServices->AddItem($row["serviceid"],$name);
		}

		$frmLink->AddRow(S_SERVICE_2, $cmbServices);

		$frmLink->AddRow(S_SOFT_LINK_Q, new CCheckBox("soft",$soft));

		$frmLink->AddItemToBottomRow(new CButton("save_link",S_SAVE));
		if(isset($_REQUEST["linkid"]))
		{
			$frmLink->AddItemToBottomRow(SPACE);
			$frmLink->AddItemToBottomRow(new CButtonDelete(
				"Delete selected services linkage?",
				url_param("form").url_param("linkid")."&delete_link=1".url_param("serviceid")
				));
		}
		$frmLink->AddItemToBottomRow(SPACE);
		$frmLink->AddItemToBottomRow(new CButtonCancel(url_param("serviceid")));
		$frmLink->Show();
	}
?>

<?php
	if(isset($_REQUEST["serviceid"]))
	{
		echo BR;

		$frmDetails = new CFormTable(S_ADD_SERVER_DETAILS);
		$frmDetails->SetHelp("web.services.server.php");
		$frmDetails->AddVar("serviceid",$_REQUEST["serviceid"]);
		$frmDetails->AddVar("parentid",$_REQUEST["parentid"]);
		
		$cmbServers = new CComboBox("serverid");
		$result=DBselect("select hostid,host from hosts order by host");
		while($row=DBfetch($result))
		{
			$cmbServers->AddItem($row["hostid"],$row["host"]);
		}
		$frmDetails->AddRow(S_SERVER,$cmbServers);

		$frmDetails->AddItemToBottomRow(new CButton("add_server","Add server"));
		$frmDetails->Show();
	}

?>

<?php
	show_page_footer();
?>
