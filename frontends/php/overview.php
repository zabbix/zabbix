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

require_once "include/config.inc.php";
require_once "include/hosts.inc.php";
require_once "include/triggers.inc.php";
require_once "include/items.inc.php";

$page["title"] = "S_OVERVIEW";
$page["file"] = "overview.php";
$page['hist_arg'] = array('groupid','type');

define('ZBX_PAGE_DO_REFRESH', 1);

$_REQUEST["fullscreen"] = get_request("fullscreen", 0);

if($_REQUEST["fullscreen"]){
	define('ZBX_PAGE_NO_MENU', 1);
}
	
include_once "include/page_header.php";

define("SHOW_TRIGGERS",0);
define("SHOW_DATA",1);

if(isset($_REQUEST["select"])&&($_REQUEST["select"]!=""))
{
	unset($_REQUEST["groupid"]);
	unset($_REQUEST["hostid"]);
}
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
		'view_style'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	NULL),
		'type'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	NULL),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	NULL)
	);

	check_fields($fields);
	
	$_REQUEST['view_style'] = get_request('view_style',get_profile('web.overview.view.style',STYLE_TOP));
	update_profile('web.overview.view.style',$_REQUEST['view_style']);
	
	validate_group(PERM_READ_ONLY,array("allow_all_hosts","monitored_hosts","with_monitored_items"));

	$_REQUEST["type"] = get_request("type",get_profile("web.overview.type",SHOW_TRIGGERS));
	update_profile("web.overview.type",$_REQUEST["type"]);

	$form = new CForm();
	$form->SetMethod('get');
	
	$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
	$cmbGroup->AddItem(0,S_ALL_SMALL);
	
	if($_REQUEST["type"] == SHOW_TRIGGERS){
		$from = ', functions f, triggers t ';
		$where = ' and i.itemid=f.itemid '.
					' and f.triggerid=t.triggerid '.
					' and t.status='.TRIGGER_STATUS_ENABLED;
	}
	else{
		$where = $from = '';
	}
	
	$result=DBselect('SELECT DISTINCT g.groupid,g.name '.
				' FROM groups g, hosts_groups hg, hosts h, items i'.$from.
				' WHERE g.groupid IN ('.get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST).') '.
					' AND hg.groupid=g.groupid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND h.hostid=i.hostid '.
					' AND hg.hostid=h.hostid '.
					' AND i.status='.ITEM_STATUS_ACTIVE.
					$where.
				' ORDER BY g.name');
	while($row=DBfetch($result)){
		$cmbGroup->AddItem(
				$row["groupid"],
				get_node_name_by_elid($row["groupid"]).$row["name"]
				);
	}
	
	$form->AddItem(array(S_GROUP.SPACE,$cmbGroup));

	$cmbType = new CComboBox("type",$_REQUEST["type"],"submit()");
	$cmbType->AddItem(SHOW_TRIGGERS,S_TRIGGERS);
	$cmbType->AddItem(SHOW_DATA,	S_DATA);
	$form->AddItem(array(S_TYPE.SPACE,$cmbType));

	$help = new CHelp('web.view.php','left');
	$help_table = new CTableInfo();
	$help_table->AddOption('style', 'width: 200px');
	if($_REQUEST["type"]==SHOW_TRIGGERS){
		$help_table->AddRow(array(new CCol(SPACE, 'normal'), S_DISABLED));
	}
	
	foreach(array(1,2,3,4,5) as $tr_severity)
		$help_table->AddRow(array(new CCol(get_severity_description($tr_severity),get_severity_style($tr_severity)),S_ENABLED));
	$help_table->AddRow(array(new CCol(SPACE, 'unknown_trigger'), S_UNKNOWN));
	if($_REQUEST["type"]==SHOW_TRIGGERS)
	{
		$col = new CCol(SPACE, 'unknown_trigger');
		$col->AddOption('style','background-image: url(images/gradients/blink1.gif); '.
			'background-position: top left; background-repeat: repeate;');
		$help_table->AddRow(array($col, S_5_MIN));
		$col = new CCol(SPACE, 'unknown_trigger');
		$col->AddOption('style','background-image: url(images/gradients/blink2.gif); '.
			'background-position: top left; background-repeat: repeate;');
		$help_table->AddRow(array($col, S_15_MIN));
		$help_table->AddRow(array(new CCol(SPACE), S_NO_TRIGGER));
	}
	else
	{
		$help_table->AddRow(array(new CCol(SPACE), S_DISABLED.' '.S_OR.' '.S_NO_TRIGGER));
	}

	$help->SetHint($help_table);
	
	show_table_header(array($help, new Clink(S_OVERVIEW_BIG,'overview.php?fullscreen='.($_REQUEST['fullscreen']?'0':'1'))), $form);
	unset($help, $help_table, $form, $col);
	
	$form = new CForm();
	$form->SetMethod('get');
	
	$cmbStyle = new CComboBox("view_style",$_REQUEST["view_style"],"submit()");
	$cmbStyle->AddItem(STYLE_TOP,S_TOP);
	$cmbStyle->AddItem(STYLE_LEFT,S_LEFT);
	
	$form->Additem(array(S_HOSTS_LOCATION.SPACE,$cmbStyle));
	
	show_table_header($form, NULL);
?>

<?php
	if($_REQUEST["type"]==SHOW_DATA)
	{
COpt::profiling_start("get_items_data_overview");
		$table = get_items_data_overview($_REQUEST["groupid"],$_REQUEST['view_style']);
COpt::profiling_stop("get_items_data_overview");
		$table->Show();
		unset($table);
	}
	elseif($_REQUEST["type"]==SHOW_TRIGGERS)
	{
COpt::profiling_start("get_triggers_overview");
		$table = get_triggers_overview($_REQUEST["groupid"],$_REQUEST['view_style']);
COpt::profiling_stop("get_triggers_overview");
		$table->Show();
		unset($table);
	}
?>
<?php

include_once "include/page_footer.php";

?>
