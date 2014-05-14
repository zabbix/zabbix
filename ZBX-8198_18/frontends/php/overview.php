<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
?>
<?php
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
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	);

	check_fields($fields);
?>
<?php
/* AJAX	*/
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			CProfile::update('web.overview.hats.'.$_REQUEST['favref'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
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

	$options = array(
		'groups' => array(
			'monitored_hosts' => 1,
			'with_monitored_items' => 1,
			'with_monitored_triggers' => ($_REQUEST['type'] == SHOW_TRIGGERS) ? 1 : null,
		),
		'hosts' => array(
			'monitored_hosts' => 1,
			'with_monitored_items' => 1,
			'with_monitored_triggers' => ($_REQUEST['type'] == SHOW_TRIGGERS) ? 1 : null,
		),
		'hostid' => get_request('hostid', null),
		'groupid' => get_request('groupid', null),
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;


	$form = new CForm(null, 'get');
	$form->addItem(array(S_GROUP.SPACE, $pageFilter->getGroupsCB(true)));

	$cmbType = new CComboBox('type', $_REQUEST['type'], 'submit()');
	$cmbType->addItem(SHOW_TRIGGERS, S_TRIGGERS);
	$cmbType->addItem(SHOW_DATA, S_DATA);
	$form->addItem(array(SPACE.S_TYPE.SPACE, $cmbType));

	$help = new CHelp('web.view.php', 'right');
	$help_table = new CTableInfo();
	$help_table->setAttribute('style', 'width: 200px');

	if($_REQUEST['type'] == SHOW_TRIGGERS){
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
	$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
	$over_wdgt->addPageHeader(S_OVERVIEW_BIG, array($fs_icon, $help));

// 2nd header
	$form_l = new CForm(null, 'get');
	$form_l->addVar('groupid',$_REQUEST['groupid']);

	$cmbStyle = new CComboBox('view_style',$_REQUEST['view_style'],'submit()');
	$cmbStyle->addItem(STYLE_TOP,S_TOP);
	$cmbStyle->addItem(STYLE_LEFT,S_LEFT);

	$form_l->additem(array(S_HOSTS_LOCATION.SPACE,$cmbStyle));

	$over_wdgt->addHeader(S_OVERVIEW_BIG, $form);
	$over_wdgt->addHeader($form_l);

//	show_table_header(S_OVERVIEW_BIG,$form);
//-------------

	if($_REQUEST['type']==SHOW_DATA){
//COpt::profiling_start('get_items_data_overview');
		$table = get_items_data_overview(array_keys($pageFilter->hosts),$_REQUEST['view_style']);
//COpt::profiling_stop('get_items_data_overview');
	}
	else if($_REQUEST['type']==SHOW_TRIGGERS){
//COpt::profiling_start('get_triggers_overview');
		$table = get_triggers_overview(array_keys($pageFilter->hosts),$_REQUEST['view_style']);
//COpt::profiling_stop('get_triggers_overview');
	}

	$over_wdgt->addItem($table);
	$over_wdgt->show();

?>
<?php

include_once('include/page_footer.php');

?>
