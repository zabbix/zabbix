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
require_once('include/items.inc.php');

$page['title'] = 'S_LATEST_DATA';
$page['file'] = 'latest.php';
$page['hist_arg'] = array('groupid','hostid','show','select','open','applicationid');
$page['scripts'] = array();

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_MAIN_HAT','hat_latest');

if(PAGE_TYPE_HTML == $page['type']){
	define('ZBX_PAGE_DO_REFRESH', 1);
}
//	define('ZBX_PAGE_DO_JS_REFRESH', 1);

include_once('include/page_header.php');
?>
<?php
//		VAR			     			 TYPE	   OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'apps'=>				array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,		NULL),
		'applicationid'=>		array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,		NULL),
		'close'=>				array(T_ZBX_INT, O_OPT,	NULL,	IN('1'),	NULL),
		'open'=>				array(T_ZBX_INT, O_OPT,	NULL,	IN('1'),	NULL),

		'groupid'=>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
		'hostid'=>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),

		'fullscreen'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),	NULL),
// filter
		'select'=>				array(T_ZBX_STR, O_OPT, NULL,	NULL,		NULL),
		'show_without_data'=>	array(T_ZBX_INT, O_OPT, NULL,	IN('0,1'),	NULL),
		'filter_rst'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),	NULL),
		'filter_set'=>			array(T_ZBX_STR, O_OPT,	P_SYS,	null,		NULL),
//ajax
		'favobj'=>				array(T_ZBX_STR, O_OPT, P_ACT,	NULL,		NULL),
		'favref'=>				array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
		'state'=>				array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
	);

	check_fields($fields);
?>
<?php
/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.latest.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------

/* FILTER */
	if (!isset($_REQUEST['show_without_data'])) {
		$_REQUEST['show_without_data'] = 0;
	}

	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['select'] = '';
		$_REQUEST['show_without_data'] = 0;
	}

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.latest.filter.select',$_REQUEST['select'], PROFILE_TYPE_STR);
		CProfile::update('web.latest.filter.show_without_data',$_REQUEST['show_without_data'], PROFILE_TYPE_INT);
	}
	else {
		$_REQUEST['select'] = CProfile::get('web.latest.filter.select', '');
		$_REQUEST['show_without_data'] = CProfile::get('web.latest.filter.show_without_data', 0);
	}
// --------------

	$latest_wdgt = new CWidget();

// Header
	$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
	$latest_wdgt->addPageHeader(S_LATEST_DATA_BIG,$fs_icon);

// 2nd header
	$r_form = new CForm(null, 'get');

	$options = array(
		'groups' => array(
			'monitored_hosts' => 1,
		),
		'hosts' => array(
			'monitored_hosts' => 1,
		),
		'hostid' => get_request('hostid', null),
		'groupid' => get_request('groupid', null),
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = $pageFilter->hostid;

	$available_hosts = $pageFilter->hostsSelected ? array_keys($pageFilter->hosts) : array();

	$r_form->addItem(array(S_GROUP.SPACE,$pageFilter->getGroupsCB(true)));
	$r_form->addItem(array(SPACE.S_HOST.SPACE,$pageFilter->getHostsCB(true)));

	$latest_wdgt->addHeader(S_ITEMS_BIG,$r_form);
//-------------

/************************* FILTER **************************/
/***********************************************************/
	$filterForm = new CFormTable();
	$filterForm->setAttribute('name','zbx_filter');
	$filterForm->setAttribute('id','zbx_filter');

	$filterForm->addRow(S_SHOW_ITEMS_WITH_DESCRIPTION_LIKE, new CTextBox('select',$_REQUEST['select'],20));
	$filterForm->addRow(S_SHOW_ITEMS_WITHOUT_DATA, new CCheckBox('show_without_data', $_REQUEST['show_without_data'], null, 1));

	$reset = new CButton("filter_rst",S_RESET);
	$reset->setType('button');
	$reset->setAction('javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

	$filterForm->addItemToBottomRow(new CButton("filter_set",S_FILTER));
	$filterForm->addItemToBottomRow($reset);

	$latest_wdgt->addFlicker($filterForm, CProfile::get('web.latest.filter.state',1));
//-------

	validate_sort_and_sortorder('i.description',ZBX_SORT_UP);

	$_REQUEST['apps'] = get_request('apps', array());
	$apps = zbx_toHash($_REQUEST['apps']);

	if(isset($_REQUEST['open'])){
		$showAll = 1;
		if(isset($_REQUEST['applicationid']))
			$apps[$_REQUEST['applicationid']] = $_REQUEST['applicationid'];
	}
	else{
		$hideAll = 1;
		if(isset($_REQUEST['applicationid']))
			$apps[$_REQUEST['applicationid']] = $_REQUEST['applicationid'];
	}

	if(count($apps) > 35){
		$apps = array_slice($apps, -35);
	}

?>
<?php
	if(isset($showAll)){
		$url = '?close=1'.
			url_param('groupid').
			url_param('hostid').
			url_param('select');
		$link = new CLink(new CImg('images/general/opened.gif'),$url);
//		$link = new CLink(new CImg('images/general/opened.gif'),$url,null,"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');");
	}
	else{
		$url = '?open=1'.
			url_param('groupid').
			url_param('hostid').
			url_param('select');
		$link = new CLink(new CImg('images/general/closed.gif'),$url);
//		$link = new CLink(new CImg('images/general/closed.gif'),$url,null,"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');");
	}

	$table=new CTableInfo();
	$table->setHeader(array(
		$link,
		is_show_all_nodes()?make_sorting_header(S_NODE,'h.hostid') : null,
		($_REQUEST['hostid'] ==0)?make_sorting_header(S_HOST,'h.host') : NULL,
		make_sorting_header(S_DESCRIPTION,'i.description'),
		make_sorting_header(S_LAST_CHECK,'i.lastclock'),
		S_LAST_VALUE,
		S_CHANGE,
		S_HISTORY));

//	$table->ShowStart();

	/**
	 * Display APPLICATION ITEMS
	 */
	$db_apps = array();
	$db_appids = array();

	$sql_from = '';
	$sql_where = '';
	if($_REQUEST['groupid'] > 0){
		$sql_from .= ',hosts_groups hg ';
		$sql_where.= ' AND hg.hostid=h.hostid AND hg.groupid='.$_REQUEST['groupid'];
	}

	if($_REQUEST['hostid']>0){
		$sql_where.= ' AND h.hostid='.$_REQUEST['hostid'];
	}

	// select hosts
	$sql = 'SELECT DISTINCT h.host,h.hostid, a.* '.
			' FROM applications a, hosts h '.$sql_from.
			' WHERE a.hostid=h.hostid'.
				$sql_where.
				' AND '.DBcondition('h.hostid', $available_hosts).
			order_by('h.host,h.hostid','a.name,a.applicationid');

	$db_app_res = DBselect($sql);
	while($db_app = DBfetch($db_app_res)){
		$db_app['item_cnt'] = 0;

		$db_apps[$db_app['applicationid']] = $db_app;
		$db_appids[$db_app['applicationid']] = $db_app['applicationid'];
	}

	$tab_rows = array();

	// select items
	$sql = 'SELECT DISTINCT i.*, ia.applicationid '.
			' FROM items i,items_applications ia'.
			' WHERE '.DBcondition('ia.applicationid',$db_appids).
				' AND i.itemid=ia.itemid'.
				($_REQUEST['show_without_data'] ? '' : ' AND i.lastvalue IS NOT NULL').
				' AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.')'.
			order_by('i.description,i.itemid,i.lastclock');

	$db_items = DBselect($sql);
	while($db_item = DBfetch($db_items)){
		$description = item_description($db_item);

		if(!empty($_REQUEST['select']) && !zbx_stristr($description, $_REQUEST['select']) ) continue;

		if(strpos($db_item['units'], ',') !== false)
			list($db_item['units'], $db_item['unitsLong']) = explode(',', $db_item['units']);
		else
			$db_item['unitsLong'] = '';

		$db_app = &$db_apps[$db_item['applicationid']];

		if(!isset($tab_rows[$db_app['applicationid']])) $tab_rows[$db_app['applicationid']] = array();
		$app_rows = &$tab_rows[$db_app['applicationid']];

		$db_app['item_cnt']++;

		if(isset($showAll) && !empty($apps) && !isset($apps[$db_app['applicationid']])) continue;
		else if(isset($hideAll) && (empty($apps) || isset($apps[$db_app['applicationid']]))) continue;

		if(isset($db_item['lastclock']))
			$lastclock=zbx_date2str(S_LATEST_ITEMS_TRIGGERS_DATE_FORMAT,$db_item['lastclock']);
		else
			$lastclock = ' - ';

		$lastvalue = format_lastvalue($db_item);

		if(isset($db_item['lastvalue']) && isset($db_item['prevvalue'])
				&& in_array($db_item['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64))
				&& ($db_item['lastvalue']-$db_item['prevvalue'] != 0)){
			if($db_item['lastvalue']-$db_item['prevvalue']<0){
				$change=convert_units($db_item['lastvalue']-$db_item['prevvalue'],$db_item['units']);
			}
			else{
				$change='+'.convert_units($db_item['lastvalue']-$db_item['prevvalue'],$db_item['units']);
			}
			$change=nbsp($change);
		}
		else{
			$change = ' - ';
		}

		if(($db_item['value_type']==ITEM_VALUE_TYPE_FLOAT) || ($db_item['value_type']==ITEM_VALUE_TYPE_UINT64)){
			$actions = new CLink(S_GRAPH,'history.php?action=showgraph&itemid='.$db_item['itemid']);
		}
		else{
			$actions = new CLink(S_HISTORY,'history.php?action=showvalues&period=3600&itemid='.$db_item['itemid']);
		}

		$item_status = $db_item['status'] == ITEM_STATUS_NOTSUPPORTED ? 'unknown' : null;

		array_push($app_rows, new CRow(array(
			SPACE,
			is_show_all_nodes()?SPACE:null,
			($_REQUEST['hostid']>0)?NULL:SPACE,
			new CCol(SPACE.SPACE.$description, $item_status),
			new CCol($lastclock, $item_status),
			new CCol($lastvalue, $item_status),
			new CCol($change, $item_status),
			$actions
			)));
	}
	unset($app_rows);
	unset($db_app);

	foreach($db_apps as $appid => $db_app){
		if(!isset($tab_rows[$appid])) continue;

		$app_rows = $tab_rows[$appid];

		$tmp_apps = $apps;
		if(isset($apps[$db_app['applicationid']])){
			unset($tmp_apps[$db_app['applicationid']]);
			$tmp_apps = array_values($tmp_apps);
		}

		if(isset($showAll)){
			if(!empty($apps) && !isset($apps[$db_app['applicationid']])) $img = new CImg('images/general/closed.gif');
			else $img = new CImg('images/general/opened.gif');
		}
		else{
			if(!empty($apps) && !isset($apps[$db_app['applicationid']])) $img = new CImg('images/general/opened.gif');
			else $img = new CImg('images/general/closed.gif');
		}

		if(isset($showAll) && (!empty($tmp_apps) || empty($apps))){
			if(empty($apps)) $url = '?close=1&applicationid='.$db_app['applicationid'];
			else if(isset($apps[$db_app['applicationid']])) $url = '?open=1'.url_param($tmp_apps, false, 'apps');
			else $url = '?open=1&applicationid='.$db_app['applicationid'].url_param($tmp_apps, false, 'apps');
		}
		else{
			if(empty($apps)) $url = '?open=1&applicationid='.$db_app['applicationid'];
			else if(isset($apps[$db_app['applicationid']])) $url = '?close=1'.url_param($tmp_apps, false, 'apps');
			else $url = '?close=1&applicationid='.$db_app['applicationid'].url_param($tmp_apps, false, 'apps');
		}

		$url.= url_param('groupid').url_param('hostid').url_param('fullscreen').url_param('select');
		$link = new CLink($img,$url);

		$col = new CCol(array(bold($db_app['name']),SPACE.'('.$db_app['item_cnt'].SPACE.S_ITEMS.')'));
		$col->setColSpan(5);

		$table->addRow(array(
			$link,
			get_node_name_by_elid($db_app['applicationid']),
			($_REQUEST['hostid'] > 0)?NULL:$db_app['host'],
			$col
		));

		foreach($app_rows as $row)
			$table->addRow($row);
	}

	/**
	 * Display OTHER ITEMS (which doesn't linked to application)
	 */
	$db_hosts = array();
	$db_hostids = array();

	// select hosts
	$sql = 'SELECT DISTINCT h.host,h.hostid '.
			' FROM hosts h'.$sql_from.', items i '.
				' LEFT JOIN items_applications ia ON ia.itemid=i.itemid'.
			' WHERE ia.itemid is NULL '.
				$sql_where.
				' AND h.hostid=i.hostid '.
				' AND '.DBcondition('h.hostid', $available_hosts).
			' ORDER BY h.host';

	$db_host_res = DBselect($sql);
	while($db_host = DBfetch($db_host_res)) {
		$db_host['item_cnt'] = 0;

		$db_hosts[$db_host['hostid']] = $db_host;
		$db_hostids[$db_host['hostid']] = $db_host['hostid'];
	}

	$tab_rows = array();

	// select items
	$sql = 'SELECT DISTINCT h.host,h.hostid,i.* '.
			' FROM hosts h'.$sql_from.', items i '.
				' LEFT JOIN items_applications ia ON ia.itemid=i.itemid'.
			' WHERE ia.itemid is NULL '.
				$sql_where.
				' AND h.hostid=i.hostid '.
				($_REQUEST['show_without_data'] ? '' : ' AND i.lastvalue IS NOT NULL').
				' AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.')'.
				' AND '.DBcondition('h.hostid', $db_hostids).
			' ORDER BY i.description,i.itemid';
	$db_items = DBselect($sql);
	while ($db_item = DBfetch($db_items)) {

		$description = item_description($db_item);

		if (!empty($_REQUEST['select']) && !zbx_stristr($description, $_REQUEST['select'])) continue;

		if (strpos($db_item['units'], ',') !== false)
			list($db_item['units'], $db_item['unitsLong']) = explode(',', $db_item['units']);
		else
			$db_item['unitsLong'] = '';

		$db_host = &$db_hosts[$db_item['hostid']];

		if (!isset($tab_rows[$db_host['hostid']])) $tab_rows[$db_host['hostid']] = array();
		$app_rows = &$tab_rows[$db_host['hostid']];

		$db_host['item_cnt']++;

		if (isset($showAll) && !empty($apps) && !isset($apps[0])) continue;
		else if (isset($hideAll) && (empty($apps) || isset($apps[0]))) continue;

		// column "lastclock"
		if (isset($db_item['lastclock']))
			$lastclock = zbx_date2str(S_LATEST_ITEMS_TRIGGERS_DATE_FORMAT, $db_item['lastclock']);
		else
			$lastclock = ' - ';

		// column "lastvalue"
		$lastvalue = format_lastvalue($db_item);

		// column "change"
		if (isset($db_item['lastvalue']) && isset($db_item['prevvalue'])
				&& ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $db_item['value_type'] == ITEM_VALUE_TYPE_UINT64)
				&& ($db_item['lastvalue'] - $db_item['prevvalue'] != 0)) {
			$change = '';
			if(($db_item['lastvalue'] - $db_item['prevvalue']) > 0) {
				$change = '+';
			}

			$digits = ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT) ? 2 : 0;
			$change = $change.convert_units(bcsub($db_item['lastvalue'], $db_item['prevvalue'], $digits), $db_item['units'], 0);
			$change = nbsp($change);
		}
		else {
			$change = ' - ';
		}

		// column "action"
		if (($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT) || ($db_item['value_type'] == ITEM_VALUE_TYPE_UINT64)) {
			$actions = new CLink(S_GRAPH, 'history.php?action=showgraph&itemid='.$db_item['itemid']);
		}
		else{
			$actions = new CLink(S_HISTORY, 'history.php?action=showvalues&period=3600&itemid='.$db_item['itemid']);
		}

		$item_status = $db_item['status'] == ITEM_STATUS_NOTSUPPORTED ? 'unknown' : null;

		array_push($app_rows, new CRow(array(
			SPACE,
			is_show_all_nodes() ? ($db_host['item_cnt'] ? SPACE : get_node_name_by_elid($db_item['itemid'])) : null,
			$_REQUEST['hostid'] ? NULL : ($db_host['item_cnt'] ? SPACE : $db_item['host']),
			new CCol(SPACE.SPACE.$description, $item_status),
			new CCol($lastclock, $item_status),
			new CCol($lastvalue, $item_status),
			new CCol($change, $item_status),
			new CCol($actions, $item_status)
		)));
	}
	unset($app_rows);
	unset($db_host);

	foreach($db_hosts as $hostid => $db_host){

		if(!isset($tab_rows[$hostid])) continue;
		$app_rows = $tab_rows[$hostid];

		$tmp_apps = $apps;
		if(isset($apps[0])){
			unset($tmp_apps[0]);
			$tmp_apps = array_values($tmp_apps);
		}

		if(isset($showAll)){
			if(!empty($apps) && !isset($apps[0])) $img = new CImg('images/general/closed.gif');
			else $img = new CImg('images/general/opened.gif');
		}
		else{
			if(!empty($apps) && !isset($apps[0])) $img = new CImg('images/general/opened.gif');
			else $img = new CImg('images/general/closed.gif');
		}

		if(isset($showAll) && (!empty($tmp_apps) || empty($apps))){
			if(empty($apps)) $url = '?close=1&applicationid=0';
			else if(isset($apps[0])) $url = '?open=1'.url_param($tmp_apps, false, 'apps');
			else $url = '?open=1&applicationid=0'.url_param($tmp_apps, false, 'apps');
		}
		else{
			if(empty($apps)) $url = '?open=1&applicationid=0';
			else if(isset($apps[0])) $url = '?close=1'.url_param($tmp_apps, false, 'apps');
			else $url = '?close=1&applicationid=0'.url_param($tmp_apps, false, 'apps');
		}

		$url.= url_param('groupid').url_param('hostid').url_param('fullscreen').url_param('select');
		$link = new CLink($img,$url);


		$col = new CCol(array(bold(S_MINUS_OTHER_MINUS),SPACE.'('.$db_host['item_cnt'].SPACE.S_ITEMS.')'));
		$col->setColSpan(5);

		$table->addRow(array(
			$link,
			get_node_name_by_elid($db_host['hostid']),
			($_REQUEST['hostid'] > 0)?NULL:$db_host['host'],
			$col
		));

		foreach($app_rows as $row)
			$table->addRow($row);
	}

/*
// Refresh tab
	$refresh_tab = array(
		array('id'	=> ZBX_PAGE_MAIN_HAT,
				'interval' 	=> $USER_DETAILS['refresh'],
				'url'	=>	zbx_empty($_SERVER['QUERY_STRING'])?'':'?'.$_SERVER['QUERY_STRING'],
			)
	);
//*/

	$latest_wdgt->addItem($table);
	$latest_wdgt->show();

//	add_refresh_objects($refresh_tab);

include_once 'include/page_footer.php';
?>
