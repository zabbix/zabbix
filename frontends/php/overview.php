<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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

require_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/triggers.inc.php');
require_once('include/items.inc.php');

$page['title'] = "S_OVERVIEW";
$page['file'] = 'overview.php';
$page['hist_arg'] = array('groupid','type');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_REFRESH', 1);
define('SHOW_TRIGGERS',0);
define('SHOW_DATA',1);

include_once('include/page_header.php');

if(isset($_REQUEST['select']) && ($_REQUEST['select']!='')){
	unset($_REQUEST['groupid']);
	unset($_REQUEST['hostid']);
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
			CProfile::update('web.overview.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------

	$_REQUEST['view_style'] = get_request('view_style',CProfile::get('web.overview.view.style',STYLE_TOP));
	CProfile::update('web.overview.view.style',$_REQUEST['view_style'],PROFILE_TYPE_INT);

	$_REQUEST['type'] = get_request('type',CProfile::get('web.overview.type',SHOW_TRIGGERS));
	CProfile::update('web.overview.type',$_REQUEST['type'],PROFILE_TYPE_INT);

	$options = array('allow_all_hosts','monitored_hosts','with_monitored_items');
	if($_REQUEST['type'] == SHOW_TRIGGERS) array_push($options,'with_monitored_triggers');
	if(!$ZBX_WITH_ALL_NODES)	array_push($options,'only_current_node');

//SDI($_REQUEST['groupid']);
	$params = array();
	foreach($options as  $option) $params[$option] = 1;
	$PAGE_GROUPS = get_viewed_groups(PERM_READ_ONLY, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_ONLY, $PAGE_GROUPS['selected'], $params);

	validate_group($PAGE_GROUPS, $PAGE_HOSTS);

	$form = new CForm();
	$form->setMethod('get');

	$available_groups = $PAGE_GROUPS['groupids'];

	$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');

	foreach($PAGE_GROUPS['groups'] as $groupid => $name){
		$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid, null, ': ').$name);
	}

	$form->addItem(array(S_GROUP.SPACE,$cmbGroups,SPACE));

	$cmbType = new CComboBox('type',$_REQUEST['type'],'submit()');
	$cmbType->addItem(SHOW_TRIGGERS,S_TRIGGERS);
	$cmbType->addItem(SHOW_DATA,	S_DATA);
	$form->addItem(array(S_TYPE.SPACE,$cmbType));

	$help = new CHelp('web.view.php','right');
	$help_table = new CTableInfo();
	$help_table->setAttribute('style', 'width: 200px');

	if($_REQUEST['type']==SHOW_TRIGGERS){
		$help_table->addRow(array(new CCol(SPACE, 'normal'), S_DISABLED));
	}

	foreach(array(1,2,3,4,5) as $tr_severity)
		$help_table->addRow(array(new CCol(get_severity_description($tr_severity),get_severity_style($tr_severity)),S_ENABLED));

	$help_table->addRow(array(new CCol(SPACE, 'unknown_trigger'), S_UNKNOWN));

	if($_REQUEST['type']==SHOW_TRIGGERS){
		$col = new CCol(SPACE, 'unknown_trigger');
		$col->setAttribute('style','background-image: url(images/gradients/blink1.gif); '.
			'background-position: top left; background-repeat: repeate;');
		$help_table->addRow(array($col, S_5_MIN));
		$col = new CCol(SPACE, 'unknown_trigger');
		$col->setAttribute('style','background-image: url(images/gradients/blink2.gif); '.
			'background-position: top left; background-repeat: repeate;');
		$help_table->addRow(array($col, S_15_MIN));
		$help_table->addRow(array(new CCol(SPACE), S_NO_TRIGGER));
	}
	else{
		$help_table->addRow(array(new CCol(SPACE), S_DISABLED.' '.S_OR.' '.S_NO_TRIGGER));
	}

	$help->setHint($help_table);

	$over_wdgt = new CWidget();
// Header
	$url = 'overview.php?fullscreen='.($_REQUEST['fullscreen']?'0':'1');

	$fs_icon = new CDiv(SPACE,'fullscreen');
	$fs_icon->setAttribute('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
	$fs_icon->addAction('onclick',new CJSscript("javascript: document.location = '".$url."';"));

	$over_wdgt->addPageHeader(S_OVERVIEW_BIG, array($fs_icon, $help));

// 2nd heder
	$form_l = new CForm();
	$form_l->setMethod('get');
	$form_l->addVar('groupid',$_REQUEST['groupid']);

	$cmbStyle = new CComboBox('view_style',$_REQUEST['view_style'],'submit()');
	$cmbStyle->addItem(STYLE_TOP,S_TOP);
	$cmbStyle->addItem(STYLE_LEFT,S_LEFT);

	$form_l->additem(array(S_HOSTS_LOCATION.SPACE,$cmbStyle));

	$over_wdgt->addHeader(S_OVERVIEW_BIG, $form);
	$over_wdgt->addHeader($form_l);

//	show_table_header(S_OVERVIEW_BIG,$form);
//-------------

?>
<?php
	if($_REQUEST['type']==SHOW_DATA){
//COpt::profiling_start('get_items_data_overview');
		$table = get_items_data_overview($PAGE_HOSTS['hostids'],$_REQUEST['view_style']);
//COpt::profiling_stop('get_items_data_overview');
	}
	else if($_REQUEST['type']==SHOW_TRIGGERS){
//COpt::profiling_start('get_triggers_overview');
		$table = get_triggers_overview($PAGE_HOSTS['hostids'],$_REQUEST['view_style']);
//COpt::profiling_stop('get_triggers_overview');
	}

	$over_wdgt->addItem($table);

	$over_wdgt->show();
?>
<?php

include_once('include/page_footer.php');

?>
