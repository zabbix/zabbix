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
	require_once "include/events.inc.php";
	require_once "include/discovery.inc.php";

	$page["title"] = "S_LATEST_EVENTS";
	$page["file"] = "events.php";
	
	define('ZBX_PAGE_DO_REFRESH', 1);

include_once "include/page_header.php";

?>
<?php
	$allow_discovery = check_right_on_discovery(PERM_READ_ONLY);

	$allowed_sources[] = EVENT_SOURCE_TRIGGERS;
	if($allow_discovery)
		$allowed_sources[] = EVENT_SOURCE_DISCOVERY;

	define('PAGE_SIZE',	100);
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"source"=>			array(T_ZBX_INT, O_OPT,	P_SYS,	IN($allowed_sources),	NULL),
		"show_unknown"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		"groupid"=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		"hostid"=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		"start"=>			array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535)."({}%".PAGE_SIZE."==0)",	NULL),
		"next"=>			array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		"prev"=>			array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL)
	);

	$_REQUEST['source'] = get_request('source', get_profile('web.events.source', 0));
	
	check_fields($fields);

	$source = get_request('source', EVENT_SOURCE_TRIGGERS);
	$show_unknown = get_request('show_unknown',get_profile('web.events.show_unknown',0));

	update_profile('web.events.source',$source);
	update_profile('web.events.show_unknown',$show_unknown);

?>
<?php
	$_REQUEST["start"] = get_request("start", 0);
	if(isset($_REQUEST["prev"]))
	{
		$_REQUEST["start"] -= PAGE_SIZE;
	}
	if(isset($_REQUEST["next"]))
	{
		$_REQUEST["start"]	+= PAGE_SIZE;
	}
	if($_REQUEST["start"] < 0) $_REQUEST["start"] = 0;
?>
<?php
	$source = get_request('source', EVENT_SOURCE_TRIGGERS);

	$r_form = new CForm();
	$r_form->SetMethod('get');
	
	$r_form->AddOption('name','events_menu');

	if($source == EVENT_SOURCE_DISCOVERY)
	{
		$table = get_history_of_discovery_events($_REQUEST["start"], PAGE_SIZE);
	}
	else
	{
		validate_group_with_host(PERM_READ_ONLY, array("allow_all_hosts","monitored_hosts","with_items"));

		$table = get_history_of_triggers_events($_REQUEST["start"], PAGE_SIZE, $_REQUEST["groupid"],$_REQUEST["hostid"]);

		$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
		$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit()");

		$cmbGroup->AddItem(0,S_ALL_SMALL);
		
		$availiable_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_LIST, null, null, get_current_nodeid());

		$result=DBselect('SELECT DISTINCT g.groupid,g.name '.
			' FROM groups g, hosts_groups hg, hosts h, items i '.
			' WHERE h.hostid in ('.$availiable_hosts.') '.
				' AND hg.groupid=g.groupid AND h.status='.HOST_STATUS_MONITORED.
				' AND h.hostid=i.hostid AND hg.hostid=h.hostid '.
			' ORDER BY g.name');

		while($row=DBfetch($result))
		{
			$cmbGroup->AddItem(
					$row['groupid'],
					get_node_name_by_elid($row['groupid']).$row['name']
					);
		}
		$r_form->AddItem(array(S_GROUP.SPACE,$cmbGroup));
		
		$cmbHosts->AddItem(0,S_ALL_SMALL);
		if($_REQUEST['groupid'] > 0)
		{
			$sql='SELECT h.hostid,h.host '.
				' FROM hosts h,items i,hosts_groups hg '.
				' WHERE h.status='.HOST_STATUS_MONITORED.
					' AND h.hostid=i.hostid AND hg.groupid='.$_REQUEST['groupid'].
					' AND hg.hostid=h.hostid AND h.hostid in ('.$availiable_hosts.') '.
				' GROUP BY h.hostid,h.host '.
				' ORDER BY h.host';
		}
		else
		{
			$sql='SELECT h.hostid,h.host '.
				' FROM hosts h,items i '.
				' WHERE h.status='.HOST_STATUS_MONITORED.
					' AND h.hostid=i.hostid'.
					' AND h.hostid in ('.$availiable_hosts.') '.
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

		$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
	}
	
	if($allow_discovery)
	{
		$cmbSource = new CComboBox('source', $source, 'submit()');
		$cmbSource->AddItem(EVENT_SOURCE_TRIGGERS, S_TRIGGER);
		$cmbSource->AddItem(EVENT_SOURCE_DISCOVERY, S_DISCOVERY);
		$r_form->AddItem(array(S_SOURCE, SPACE, $cmbSource));
	}
	show_table_header(S_HISTORY_OF_EVENTS_BIG.SPACE.date("[H:i:s]",time()),$r_form);
//	echo BR;

	$r_form = new CForm();
	$r_form->SetMethod('get');
	
	$r_form->AddVar('start',$_REQUEST['start']);

	$btnPrev = new CButton("prev","<< Prev ".PAGE_SIZE);
	if($_REQUEST["start"] <= 0)
		$btnPrev->SetEnabled('no');
	$r_form->AddItem($btnPrev);

	$btnNext = new CButton("next","Next ".PAGE_SIZE." >>");
	if($table->GetNumRows() < PAGE_SIZE)
		$btnNext->SetEnabled('no');
	$r_form->AddItem($btnNext);
	
	$l_form = new CForm();
	$l_form->SetMethod('get');
	
	if($source == EVENT_SOURCE_TRIGGERS){
		$link = array('[', 
			new CLink($show_unknown!=1?S_SHOW_UNKNOWN:S_HIDE_UNKNOWN,
				"events.php?show_unknown=".($show_unknown!=1?'1':'0')
			), 
			']'.SPACE
		);
		
		$l_form->AddItem(array($link, SPACE, SPACE));
	}
	
	show_table_header($l_form,$r_form);

        $table->Show();
?>
<?php

include_once "include/page_footer.php";

?>
