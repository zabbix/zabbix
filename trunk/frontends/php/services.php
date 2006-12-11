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
	include_once "include/config.inc.php";
	include_once "include/services.inc.php";

	$page["title"] = "S_IT_SERVICES";
	$page["file"] = "services.php";

include_once "include/page_header.php";

	insert_confirm_javascript();
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"path"=>		array(T_ZBX_STR, O_OPT, null, null, null),

		"serviceid"=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		"group_serviceid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		
		"linkid"=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		"group_linkid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		
		"name"=>		array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,	'isset({save})'),
		"algorithm"=>		array(T_ZBX_INT, O_OPT,  NULL,	IN('0,1,2'),	'isset({save})'),
		"showsla"=>		array(T_ZBX_INT, O_OPT,  NULL,  IN("0,1"),null),
		"goodsla"=>		array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,100),		null),
		"sortorder"=>		array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,65535),	null),
		"service_times"=>	array(T_ZBX_STR, O_OPT,  null,  null,			null),
		
		"linktrigger"=>		array(T_ZBX_INT, O_OPT,  NULL,  IN("0,1"),null),
		"triggerid"=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,	NULL),

		"serviceupid"=>		array(T_ZBX_INT, O_OPT,  null,  DB_ID,		'isset({save_link})'),
		"servicedownid"=>	array(T_ZBX_INT, O_OPT,  null,  DB_ID,		null),
		"soft"=>		array(T_ZBX_INT, O_OPT,  NULL,  IN("0,1"),	null),

		"serverid"=>		array(T_ZBX_INT, O_OPT,  null,  DB_ID,		'isset({add_server})'),

		"new_service_time"=>	array(T_ZBX_STR, O_OPT,  null,  null,			null),
		"rem_service_times"=>	array(T_ZBX_STR, O_OPT,  null,  null,			null),

/* actions */
		"save_service"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"save_link"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"add_server"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		
		"add_service_time"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"del_service_times"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),

		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_copy_to"=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields);
?>
<?php
	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT);

	if(isset($_REQUEST["serviceid"]) && $_REQUEST["serviceid"] > 0)
	{
		if( !($service = DBfetch(DBselect("select s.* from services s left join triggers t on s.triggerid=t.triggerid ".
			" left join functions f on t.triggerid=f.triggerid left join items i on f.itemid=i.itemid ".
			" where (i.hostid is null or i.hostid not in (".$denyed_hosts.")) ".
			" and ".DBid2nodeid("s.serviceid")."=".$ZBX_CURNODEID.
			" and s.serviceid=".$_REQUEST["serviceid"]
			))))
		{
			access_deny();
		}
	}
?>
<?php
/* ACTIONS */
	$_REQUEST["showsla"]	= get_request("showsla",0);
	$_REQUEST["soft"]	= get_request("soft", 0);

	if(isset($_REQUEST["delete"]))
	{
		if(isset($_REQUEST["group_serviceid"]))
		{
			$group_serviceid = get_request('group_serviceid', array(-1));
			
			if(($db_group_services = DBselect("select s.* from services s left join triggers t on s.triggerid=t.triggerid ".
				" left join functions f on t.triggerid=f.triggerid left join items i on f.itemid=i.itemid ".
				" where (i.hostid is null or i.hostid not in (".$denyed_hosts.")) ".
				" and ".DBid2nodeid("s.serviceid")."=".$ZBX_CURNODEID.
				" and s.serviceid in (".implode(',',$group_serviceid).")"
				)))
			{
				while($g_service_data = DBfetch($db_group_services))
				{
					$result = delete_service($g_service_data['serviceid']);

					if(isset($service) && $g_service_data['serviceid'] == $service['serviceid'])
					{
						unset($service, $path);
					}
					
					add_audit_if($result,AUDIT_ACTION_DELETE,AUDIT_RESOURCE_IT_SERVICE,
						' Name ['.$g_service_data["name"].'] id ['.$g_service_data['serviceid'].']');
				}
				show_messages(TRUE, S_SERVICE_DELETED, S_CANNOT_DELETE_SERVICE);
			}
		}
		elseif(isset($_REQUEST["group_linkid"]))
		{
			foreach($_REQUEST["group_linkid"] as $linkid)
				delete_service_link($linkid);
			show_messages(TRUE, S_LINK_DELETED, S_CANNOT_DELETE_LINK);
		}
		elseif(isset($_REQUEST["linkid"]))
		{
			$result = delete_service_link($_REQUEST["linkid"]);
			show_messages($result, S_LINK_DELETED, S_CANNOT_DELETE_LINK);
			unset($_REQUEST["linkid"]);
		}
		elseif(isset($_REQUEST["serviceid"]))
		{
			$result = delete_service($service["serviceid"]);
			show_messages($result, S_SERVICE_DELETED, S_CANNOT_DELETE_SERVICE);
			add_audit_if($result,AUDIT_ACTION_DELETE,AUDIT_RESOURCE_IT_SERVICE,
				' Name ['.$service["name"].'] id ['.$service['serviceid'].']');
			unset($service,$path);
		}
	}
	elseif(isset($_REQUEST["save_service"]))
	{
		$service_times = get_request('service_times',array());

		$triggerid = isset($_REQUEST["linktrigger"]) ? $_REQUEST["triggerid"] : null;
		if(isset($service["serviceid"]))
		{
			$result = update_service($service["serviceid"],
				$_REQUEST["name"],$triggerid,$_REQUEST["algorithm"],
				$_REQUEST["showsla"],$_REQUEST["goodsla"],$_REQUEST["sortorder"],
				$service_times);
			show_messages($result, S_SERVICE_UPDATED, S_CANNOT_UPDATE_SERVICE);
			$serviceid = $service["serviceid"];
			$audit_acrion = AUDIT_ACTION_UPDATE;
		}
		else
		{
			$result = add_service(
				$_REQUEST["name"],$triggerid,$_REQUEST["algorithm"],
				$_REQUEST["showsla"],$_REQUEST["goodsla"],$_REQUEST["sortorder"],
				$service_times);
			show_messages($result, S_SERVICE_ADDED, S_CANNOT_ADD_SERVICE);
			$serviceid = $result;
			$audit_acrion = AUDIT_ACTION_ADD;
		}	
		add_audit_if($result,$audit_acrion,AUDIT_RESOURCE_IT_SERVICE,' Name ['.$_REQUEST["name"].'] id ['.$serviceid.']');
	}
	elseif(isset($_REQUEST["save_link"]))
	{
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
		if(!($host_data = DBfetch(DBselect('select h.* from hosts h where '.DBid2nodeid('h.hostid').'='.$ZBX_CURNODEID.
			' and h.hostid not in ('.$denyed_hosts.') and h.hostid='.$_REQUEST["serverid"]))))
		{
			access_deny();
		}
		$result = add_host_to_services($_REQUEST["serverid"], $service["serviceid"]);
		add_audit_if($result,AUDIT_ACTION_ADD,AUDIT_RESOURCE_IT_SERVICE,' Host ['.$host_data["host"].'] id ['.$_REQUEST["serverid"].']');
		show_messages($result, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
	}
	elseif(isset($_REQUEST["add_service_time"]) && isset($_REQUEST["new_service_time"]))
	{
		$_REQUEST['service_times'] = get_request('service_times',array());

		$new_service_time['type'] = $_REQUEST["new_service_time"]['type'];

		if($_REQUEST["new_service_time"]['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME)
		{
			$new_service_time['from'] = strtotime($_REQUEST["new_service_time"]['from']);
			$new_service_time['to'] = strtotime($_REQUEST["new_service_time"]['to']);
			$new_service_time['note'] = $_REQUEST["new_service_time"]['note'];
		}
		else
		{
			$new_service_time['from'] = strtotime(
				$_REQUEST["new_service_time"]['from_week'].' '.$_REQUEST["new_service_time"]['from']
				);
			$new_service_time['to'] = strtotime(
				$_REQUEST["new_service_time"]['to_week'].' '.$_REQUEST["new_service_time"]['to']
				);
			$new_service_time['note'] = $_REQUEST["new_service_time"]['note'];
		}

		while($new_service_time['to'] <= $new_service_time['from']) $new_service_time['to'] += 7*24*3600;

		if(!in_array($_REQUEST['service_times'], $new_service_time))
			array_push($_REQUEST['service_times'],$new_service_time);
	}
	elseif(isset($_REQUEST["del_service_times"]) && isset($_REQUEST["rem_service_times"]))
	{
		$_REQUEST["service_times"] = get_request("service_times",array());
		foreach($_REQUEST["rem_service_times"] as $val){
			unset($_REQUEST["service_times"][$val]);
		}
	}
?>
<?php
	if(isset($service))
	{
		$service = get_service_by_serviceid($service['serviceid']); // update date after ACTIONS */
	}

	$path = get_request('path', array());
	if(isset($service))
	{
		$path[count($path)] = array('id'=>$service["serviceid"], 'name'=>$service["name"]);
	}
	array_unique($path);
	
	$menu_path = array();
	$new_path = array();
	foreach($path as $el)
	{
		if(count($new_path)==0) 
		{
			$back_name = S_ROOT_SMALL;
			$back_id = 0;
		}
		else 
		{
			$back_name = $new_path[count($new_path)-1]['name'];
			$back_id = $new_path[count($new_path)-1]['id'];
		}

		if(isset($service) && $back_id == $service['serviceid'])	break;

		array_push($menu_path, unpack_object(new CLink($back_name, '?serviceid='.$back_id.url_param($new_path,false,'path'))));
		array_push($new_path, $el);
	}
	$_REQUEST['path'] = $path = $new_path;

	show_table_header(S_IT_SERVICES_BIG.": ".implode('/',$menu_path));

	unset($menu_path, $new_path, $el);

	$form = new CForm();
	$form->SetName("services");
	$form->AddVar("path", $path);

	if(isset($service)) 
		$form->AddVar("serviceid", $service['serviceid']);

	$table = new CTableInfo();
	$table->SetHeader(array(
		array(new CCheckBox("all_services",null,
			"CheckAll('".$form->GetName()."','all_services');"),
			S_SERVICE),
		S_STATUS_CALCULATION,
		S_TRIGGER
		));

	$db_services = DBselect("select distinct s.* from services s left join triggers t on s.triggerid=t.triggerid ".
			" left join functions f on t.triggerid=f.triggerid left join items i on f.itemid=i.itemid ".
			" left join services_links sl on s.serviceid=sl.servicedownid ".
			" where (i.hostid is null or i.hostid not in (".$denyed_hosts.")) ".
			" and ".DBid2nodeid("s.serviceid")."=".$ZBX_CURNODEID.
			" and (sl.serviceupid".(!isset($service) ?
				" is NULL " :
				"=".$service['serviceid']." or s.serviceid=".$service['serviceid'] ).") ".
			" order by sl.serviceupid,s.sortorder,s.name");
	
	while($db_service_data = DBfetch($db_services))
	{
		$prefix	 = null;
		$trigger = "-";
		
		$description = $db_service_data["name"]." [".get_num_of_service_childs($db_service_data["serviceid"])."]";
		
		if(isset($service["serviceid"]))
		{
			if($service["serviceid"] == $db_service_data["serviceid"])
			{
				$description = new CSpan($description, 'bold');
			}
			else
			{
				$prefix = " - ";
			}
		}
		if(!(isset($service["serviceid"]) && $service["serviceid"] == $db_service_data["serviceid"]))
		{
			
			$description = new CLink($description,"services.php?serviceid=".$db_service_data["serviceid"].
					url_param('path')."#form",'action');
		}

		if(isset($db_service_data["triggerid"]))
		{
			$trigger = expand_trigger_description($db_service_data["triggerid"]);
		}

		$table->AddRow(array(
			array(new CCheckBox("group_serviceid[]",null,null,$db_service_data["serviceid"]),
				$prefix,
				$description
			),
			algorithm2str($db_service_data["algorithm"]),
			$trigger
			));
	}

	$table->SetFooter(new CCol(new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_SERVICES,null,false)));
	$form->AddItem($table);
	$form->Show();
?>
<?php
	if(isset($service["serviceid"]))
	{
		echo BR;

		show_table_header("LINKS");

		$form = new CForm();
		$form->SetName("Links");
		$form->AddVar("serviceid",$service["serviceid"]);
		$form->AddVar("path",$path);

		$table = new CTableInfo();
		$table->SetHeader(array(
				array(new CCheckBox("all_services",null,
					"CheckAll('".$form->GetName()."','all_services');"),
					S_LINK),
				S_SERVICE_1,
				S_SERVICE_2,
				S_SOFT_HARD_LINK
			));

		$result=DBselect("select distinct sl.linkid, sl.soft, sl.serviceupid, sl.servicedownid,".
			" s1.name as serviceupname, s2.name as servicedownname".
			" from services s1, services s2, services_links sl".
			" where sl.serviceupid=s1.serviceid and sl.servicedownid=s2.serviceid".
			" and (sl.serviceupid=".$service["serviceid"]." or sl.servicedownid=".$service["serviceid"].")");
		$i = 1;
		while($row=DBfetch($result))
		{
			$table->AddRow(array(
				array(
					new CCheckBox("group_linkid[]",null,null,$row["linkid"]),
					new CLink(S_LINK.SPACE.$i++,
						"services.php?form=update&linkid=".$row["linkid"].url_param("serviceid").url_param("path"),
						"action"),
				),
				new CLink($row["serviceupname"],"services.php?serviceid=".$row["serviceupid"].url_param("path")),
				new CLink($row["servicedownname"],"services.php?serviceid=".$row["servicedownid"].url_param("path")),
				$row["soft"] == 0 ? S_HARD : S_SOFT
				));
		}
		$table->SetFooter(new CCol(new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_SERVICES,null,false)));
		$form->AddItem($table);
		$form->Show();
	}
?>

<?php
	echo BR;

	$frmService = new CFormTable(S_SERVICE);
	$frmService->SetHelp("web.services.service.php");
	$frmService->AddVar("path",$path);
	
	$service_times = get_request('service_times',array());
	$new_service_time = get_request('new_service_time',array('type' => SERVICE_TIME_TYPE_UPTIME));

	if(isset($service["serviceid"]))
	{
		$frmService->AddVar("serviceid",$service["serviceid"]);

		$frmService->SetTitle(S_SERVICE." \"".$service["name"]."\"");
	}

	if(isset($service["serviceid"]) && !isset($_REQUEST["form_refresh"]))
	{
		$name		= $service["name"];
		$algorithm	= $service["algorithm"];
		$showsla	= $service["showsla"];
		$goodsla	= $service["goodsla"];
		$sortorder	= $service["sortorder"];
		$triggerid	= $service["triggerid"];
		$linktrigger	= isset($triggerid) ? 1 : 0;
		if(!isset($triggerid)) $triggerid = 0;

		$result = DBselect('select * from services_times where serviceid='.$service['serviceid']);
		while($db_stime = DBfetch($result))
		{
			$stime = array(
				'type'=>	$db_stime['type'],
				'from'=>	$db_stime['ts_from'],
				'to'=>		$db_stime['ts_to'],
				'note'=>	$db_stime['note']
				);
			if(in_array($stime, $service_times))	continue;
			array_push($service_times, $stime);
		}
	}
	else
	{
		$name		= get_request("name","");
		$showsla	= get_request("showsla",0);
		$goodsla	= get_request("goodsla",99.05);
		$sortorder	= get_request("sortorder",0);
		$algorithm	= get_request("algorithm",0);
		$triggerid	= get_request("triggerid",0);
		$linktrigger	= get_request("linktrigger",0);
	}

	if(isset($service))
	{
		$frmService->AddVar("serviceid",$service["serviceid"]);
	}
	$frmService->AddRow(S_NAME,new CTextBox("name",$name));

	$cmbAlg = new CComboBox("algorithm",$algorithm);
	$cmbAlg->AddItem(0,S_DO_NOT_CALCULATE);
	$cmbAlg->AddItem(1,S_MAX_BIG);
	$cmbAlg->AddItem(2,S_MIN_BIG);
	$frmService->AddRow(S_STATUS_CALCULATION_ALGORITHM, $cmbAlg);

	$frmService->AddRow(S_SHOW_SLA, new CCheckBox("showsla",$showsla,'submit();',1));

	if($showsla)
		$frmService->AddRow(S_ACCEPTABLE_SLA_IN_PERCENT,new CTextBox("goodsla",$goodsla,6));
	else
		$frmService->AddVar("goodsla",$goodsla);

	$stime_el = array();
	$i = 0;
	foreach($service_times as $val)
	{
		switch($val['type'])
		{
			case SERVICE_TIME_TYPE_UPTIME:
				$type = new CSpan(S_UPTIME,'enabled');
				$from = date('l H:i', $val['from']);
				$to = date('l H:i', $val['to']);
				break;
			case SERVICE_TIME_TYPE_DOWNTIME:
				$type = new CSpan(S_DOWNTIME,'disabled');
				$from = date('l H:i', $val['from']);
				$to = date('l H:i', $val['to']);
				break;
			case SERVICE_TIME_TYPE_ONETIME_DOWNTIME:
				$type = new CSpan(S_ONE_TIME_DOWNTIME,'disabled');
				$from = date('d M Y H:i', $val['from']);
				$to = date('d M Y H:i', $val['to']);
				break;
		}
		array_push($stime_el, array(new CCheckBox("rem_service_times[]", 'no', null,$i), 
			$type,':'.SPACE, $from, SPACE.'-'.SPACE, $to,
			(!empty($val['note']) ? BR.'['.htmlspecialchars($val['note']).']' : '' ),BR));

		$frmService->AddVar('service_times['.$i.'][type]',	$val['type']);
		$frmService->AddVar('service_times['.$i.'][from]',	$val['from']);
		$frmService->AddVar('service_times['.$i.'][to]',	$val['to']);
		$frmService->AddVar('service_times['.$i.'][note]',	$val['note']);
		
		$i++;
	}

	if(count($stime_el)==0)
		array_push($stime_el, S_NO_TIMES_DEFINED);
	else
		array_push($stime_el, new CButton('del_service_times','delete selected'));

	$frmService->AddRow(S_SERVICE_TIMES, $stime_el);

	$cmbTimeType = new CComboBox("new_service_time[type]",$new_service_time['type'],'submit()');
	$cmbTimeType->AddItem(SERVICE_TIME_TYPE_UPTIME, S_UPTIME);
	$cmbTimeType->AddItem(SERVICE_TIME_TYPE_DOWNTIME, S_DOWNTIME);
	$cmbTimeType->AddItem(SERVICE_TIME_TYPE_ONETIME_DOWNTIME, S_ONE_TIME_DOWNTIME);

	$time_param = new CTable();
	if($new_service_time['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME)
	{
		$time_param->AddRow(array(S_NOTE, new CTextBox('new_service_time[note]','<short description>',40)));
		$time_param->AddRow(array(S_FROM, new CTextBox('new_service_time[from]','d M Y H:i',20)));
		$time_param->AddRow(array(S_TILL, new CTextBox('new_service_time[to]','d M Y H:i',20)));
	}
	else
	{
		$cmbWeekFrom = new CComboBox('new_service_time[from_week]','Sunday');
		$cmbWeekTo = new CComboBox('new_service_time[to_week]','Sunday');
		foreach(array(
			'Sunday'  =>S_SUNDAY,
			'Monday'  =>S_MONDAY,
			'Tuesday' =>S_TUESDAY,
			'Wednesday'=>S_WEDNESDAY,
			'Thursday'=>S_THURSDAY,
			'Friday'  =>S_FRIDAY,
			'Saturday' =>S_SATURDAY
			) as $day_num => $day_str)
		{
			$cmbWeekFrom->AddItem($day_num, $day_str);
			$cmbWeekTo->AddItem($day_num, $day_str);
		}

		$time_param->AddRow(array(S_FROM, $cmbWeekFrom, new CTextBox('new_service_time[from]','H:i',9)));
		$time_param->AddRow(array(S_TILL, $cmbWeekTo, new CTextBox('new_service_time[to]','H:i',9)));
		$frmService->AddVar('new_service_time[note]','');
	}

	$frmService->AddRow(S_NEW_SERVICE_TIME, array(
			$cmbTimeType, BR, 
			$time_param, BR,
			new CButton('add_service_time','add')
		));

	$frmService->AddRow(S_LINK_TO_TRIGGER_Q, new CCheckBox("linktrigger",$linktrigger,"submit();",1));

	if($linktrigger == 1)
	{
		if($triggerid > 0)
			$trigger = expand_trigger_description($triggerid);
		else
			$trigger = "";

		$frmService->AddRow(S_TRIGGER,array(
			new CTextBox("trigger",$trigger,32,'yes'),
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
	if(isset($service["serviceid"]))
	{
		$frmService->AddItemToBottomRow(SPACE);
		$frmService->AddItemToBottomRow(new CButtonDelete(
			"Delete selected service?",
			url_param("form").url_param("serviceid").url_param("path")
			));
	}
	$frmService->AddItemToBottomRow(SPACE);
	$frmService->AddItemToBottomRow(new CButtonCancel(url_param('serviceid').url_param('path')));
	$frmService->Show();
?>

<?php
	if(isset($service["serviceid"]))
	{
		echo BR;

		$frmLink = new CFormTable(S_LINK_TO);
		$frmLink->SetHelp("web.services.link.php");
		$frmLink->AddVar("serviceid",$service["serviceid"]);
		$frmLink->AddVar("path",$path);
	
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
			$serviceupid	= get_request("serviceupid",$service["serviceid"]);
			$servicedownid	= get_request("servicedownid",0);
			$soft		= get_request("soft",1);
		}

		$frmLink->AddVar("serviceupid",$service["serviceid"]);

		$name = $service["name"];
		if(isset($service["triggerid"]))
			$name .= ": ".expand_trigger_description($service["triggerid"]);
		$frmLink->AddRow(S_SERVICE_1, new CTextBox("service",$name,60,'yes'));

		$cmbServices = new CComboBox("servicedownid",$servicedownid);
		$result=DBselect("select serviceid,triggerid,name from services where serviceid<>$serviceupid order by name");
		
		$result = DBselect("select s.* from services s left join triggers t on s.triggerid=t.triggerid ".
			" left join functions f on t.triggerid=f.triggerid left join items i on f.itemid=i.itemid ".
			" where (i.hostid is null or i.hostid not in (".$denyed_hosts.")) ".
			" and ".DBid2nodeid("s.serviceid")."=".$ZBX_CURNODEID.
			" and s.serviceid <> ".$serviceupid);
		
		while($row=Dbfetch($result))
		{
			if(DBfetch(DBselect("select linkid from services_links".
				" where (servicedownid<>$servicedownid and serviceupid=$serviceupid and servicedownid=".$row["serviceid"].") ".
				" or (servicedownid=".$row["serviceid"]." and soft=0) ")))
				continue;

			$name = $row["name"];
			if(isset($row["triggerid"]))
				$name .= ": ".expand_trigger_description($row["triggerid"]);
			
			$cmbServices->AddItem($row["serviceid"],$name);
		}

		$frmLink->AddRow(S_SERVICE_2, $cmbServices);

		$frmLink->AddRow(S_SOFT_LINK_Q, new CCheckBox("soft",$soft,null,1));

		$frmLink->AddItemToBottomRow(new CButton("save_link",S_SAVE));
		if(isset($_REQUEST["linkid"]))
		{
			$frmLink->AddItemToBottomRow(SPACE);
			$frmLink->AddItemToBottomRow(new CButtonDelete(
				"Delete selected services linkage?",
				url_param("form").url_param("linkid").url_param("serviceid").url_param('path')
				));
		}
		$frmLink->AddItemToBottomRow(SPACE);
		$frmLink->AddItemToBottomRow(new CButtonCancel(url_param("serviceid").url_param("path")));
		$frmLink->Show();
	}
?>

<?php
	if(isset($service["serviceid"]))
	{
		echo BR;

		$frmDetails = new CFormTable(S_ADD_SERVER_DETAILS);
		$frmDetails->SetHelp("web.services.server.php");
		$frmDetails->AddVar("serviceid",$service["serviceid"]);
		$frmDetails->AddVar("path",$path);
		
		$cmbServers = new CComboBox("serverid");
		$result=DBselect("select hostid,host from hosts where ".DBid2nodeid("hostid")."=".$ZBX_CURNODEID.
			" and hostid not in (".$denyed_hosts.") ".
			" order by host");
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

include_once "include/page_footer.php";

?>
