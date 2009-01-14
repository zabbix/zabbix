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

$page['title'] = "S_OVERVIEW";
$page['file'] = 'overview.php';
$page['hist_arg'] = array('groupid','type');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_REFRESH', 1);
define('SHOW_TRIGGERS',0);
define('SHOW_DATA',1);

include_once "include/page_header.php";

if(isset($_REQUEST['select']) && ($_REQUEST['select']!='')){
	unset($_REQUEST["groupid"]);
	unset($_REQUEST["hostid"]);
}
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
		'view_style'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	NULL),
		'type'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	NULL),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	NULL),
		
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			'isset({favid})'),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	);

	check_fields($fields);
	
/* AJAX	*/
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.overview.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------

	$_REQUEST['view_style'] = get_request('view_style',get_profile('web.overview.view.style',STYLE_TOP));
	update_profile('web.overview.view.style',$_REQUEST['view_style'],PROFILE_TYPE_INT);
	
	$options = array('allow_all_hosts','monitored_hosts','with_monitored_items');

	$_REQUEST['groupid'] = get_request('groupid',get_profile('web.latest.last.groupid', null));

	if(is_null($_REQUEST['groupid'])){
		array_push($options,'always_select_first_group');
	}	
	validate_group(PERM_READ_ONLY,$options,'web.overview.groupid');

	$_REQUEST['type'] = get_request('type',get_profile('web.overview.type',SHOW_TRIGGERS));
	update_profile('web.overview.type',$_REQUEST['type'],PROFILE_TYPE_INT);

	$form = new CForm();
	$form->SetMethod('get');

	$cmbGroup = new CComboBox('groupid',$_REQUEST['groupid'],'submit()');
	$cmbGroup->AddItem(0,S_ALL_SMALL);
	
	if($_REQUEST['type'] == SHOW_TRIGGERS){
		$from = ', functions f, triggers t ';
		$where = ' AND i.itemid=f.itemid '.
					' AND f.triggerid=t.triggerid '.
					' AND t.status='.TRIGGER_STATUS_ENABLED;
	}
	else{
		$where = $from = '';
	}
	
	$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST);
	
	$sql = 'SELECT DISTINCT g.groupid,g.name '.
				' FROM groups g, hosts_groups hg, hosts h '.
				' WHERE '.DBcondition('g.groupid',$available_groups).
					' AND hg.groupid=g.groupid '.
					' AND h.hostid=hg.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND EXISTS( SELECT i.itemid '.
									' FROM items i '.$from.
									' WHERE i.hostid=h.hostid '.
										' AND i.status='.ITEM_STATUS_ACTIVE.
										$where.')'.
				' ORDER BY g.name';

	$result=DBselect($sql);
	while($row=DBfetch($result)){
		$cmbGroup->addItem(
				$row['groupid'],
				get_node_name_by_elid($row['groupid']).$row['name']
				);
	}
	
	$form->addItem(array(S_GROUP.SPACE,$cmbGroup,SPACE));

	$cmbType = new CComboBox('type',$_REQUEST['type'],'submit()');
	$cmbType->addItem(SHOW_TRIGGERS,S_TRIGGERS);
	$cmbType->addItem(SHOW_DATA,	S_DATA);
	$form->addItem(array(S_TYPE.SPACE,$cmbType));

	$help = new CHelp('web.view.php','right');
	$help_table = new CTableInfo();
	$help_table->addOption('style', 'width: 200px');
	
	if($_REQUEST['type']==SHOW_TRIGGERS){
		$help_table->AddRow(array(new CCol(SPACE, 'normal'), S_DISABLED));
	}
	
	foreach(array(1,2,3,4,5) as $tr_severity)
		$help_table->AddRow(array(new CCol(get_severity_description($tr_severity),get_severity_style($tr_severity)),S_ENABLED));
		
	$help_table->AddRow(array(new CCol(SPACE, 'unknown_trigger'), S_UNKNOWN));
	
	if($_REQUEST['type']==SHOW_TRIGGERS){
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
	else{
		$help_table->AddRow(array(new CCol(SPACE), S_DISABLED.' '.S_OR.' '.S_NO_TRIGGER));
	}

	$help->SetHint($help_table);
	
// Header	
	$text = array(S_OVERVIEW_BIG);
	
	$url = 'overview.php?fullscreen='.($_REQUEST['fullscreen']?'0':'1');

	$fs_icon = new CDiv(SPACE,'fullscreen');
	$fs_icon->AddOption('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
	$fs_icon->AddAction('onclick',new CScript("javascript: document.location = '".$url."';"));
	
	$form_l = new CForm();
	$form_l->SetMethod('get');
	$form_l->AddVar('groupid',$_REQUEST['groupid']);
		
	$cmbStyle = new CComboBox("view_style",$_REQUEST["view_style"],"submit()");
	$cmbStyle->AddItem(STYLE_TOP,S_TOP);
	$cmbStyle->AddItem(STYLE_LEFT,S_LEFT);
	
	$form_l->Additem(array(S_HOSTS_LOCATION.SPACE,$cmbStyle));

	$p_elements[] = get_table_header($form_l,$form);

//-------------
	
?>
<?php
	if($_REQUEST["type"]==SHOW_DATA){
	
COpt::profiling_start("get_items_data_overview");
		$table = get_items_data_overview($_REQUEST["groupid"],$_REQUEST['view_style']);
COpt::profiling_stop("get_items_data_overview");
	}
	else if($_REQUEST["type"]==SHOW_TRIGGERS){
	
COpt::profiling_start("get_triggers_overview");
		$table = get_triggers_overview($_REQUEST["groupid"],$_REQUEST['view_style']);
COpt::profiling_stop("get_triggers_overview");
	}

	$p_elements[] = $table;
	
	$overview_hat = create_hat(
			$text,
			$p_elements,
			array($help,$fs_icon),
			'hat_overview',
			get_profile('web.overview.hats.hat_overview.state',1)
	);

	$overview_hat->Show();	
//	$table->Show();
//	unset($table);

include_once "include/page_footer.php";
?>
