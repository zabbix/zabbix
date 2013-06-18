<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';

$page['title'] = _('Latest data');
$page['file'] = 'latest.php';
$page['hist_arg'] = array('groupid','hostid','show','select','open','applicationid');
$page['scripts'] = array();

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_MAIN_HAT','hat_latest');

if (PAGE_TYPE_HTML == $page['type']) {
	define('ZBX_PAGE_DO_REFRESH', 1);
}
//	define('ZBX_PAGE_DO_JS_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

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
	'favref'=>				array(T_ZBX_STR, O_OPT, P_ACT,  NULL,		NULL),
	'favstate'=>			array(T_ZBX_INT, O_OPT, P_ACT,  NULL,		NULL),
	'toggle_ids'=>			array(T_ZBX_STR, O_OPT, P_ACT,  NULL,		NULL),
	'toggle_open_state'=>	array(T_ZBX_INT, O_OPT, P_ACT,  NULL,		NULL)
);

check_fields($fields);

/* AJAX */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.latest.filter.state',$_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
	elseif ($_REQUEST['favobj'] == 'toggle') {
		// $_REQUEST['toggle_ids'] can be single id or list of ids,
		// where id xxxx is application id and id 0_xxxx is 0_ + host id
		if (!is_array($_REQUEST['toggle_ids'])) {
			if ($_REQUEST['toggle_ids'][1] == '_') {
				$hostId = substr($_REQUEST['toggle_ids'], 2);
				CProfile::update('web.latest.toggle_other', $_REQUEST['toggle_open_state'], PROFILE_TYPE_INT, $hostId);
			}
			else {
				$applicationId = $_REQUEST['toggle_ids'];
				CProfile::update('web.latest.toggle', $_REQUEST['toggle_open_state'], PROFILE_TYPE_INT, $applicationId);
			}
		}
		else {
			foreach ($_REQUEST['toggle_ids'] as $toggleId) {
				if ($toggleId[1] == '_') {
					$hostId = substr($toggleId, 2);
					CProfile::update('web.latest.toggle_other', $_REQUEST['toggle_open_state'], PROFILE_TYPE_INT, $hostId);
				}
				else {
					$applicationId = $toggleId;
					CProfile::update('web.latest.toggle', $_REQUEST['toggle_open_state'], PROFILE_TYPE_INT, $applicationId);
				}
			}
		}
	}
}

if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
	require_once dirname(__FILE__).'/include/page_footer.php';
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

$latest_wdgt = new CWidget(null, 'latest-mon');

// Header
$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
$latest_wdgt->addPageHeader(_('LATEST DATA'), $fs_icon);

// 2nd header
$r_form = new CForm('get');

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

$r_form->addItem(array(_('Group').SPACE, $pageFilter->getGroupsCB(true)));
$r_form->addItem(array(SPACE._('Host').SPACE, $pageFilter->getHostsCB(true)));

$latest_wdgt->addHeader(_('Items'), $r_form);
//-------------

/************************* FILTER **************************/
/***********************************************************/
$filterForm = new CFormTable(null, null, 'get');
$filterForm->setAttribute('name','zbx_filter');
$filterForm->setAttribute('id','zbx_filter');

$filterForm->addRow(_('Show items with name like'), new CTextBox('select',$_REQUEST['select'],20));
$filterForm->addRow(_('Show items without data'), new CCheckBox('show_without_data', $_REQUEST['show_without_data'], null, 1));

$reset = new CButton("filter_rst", _('Reset'), 'javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

$filterForm->addItemToBottomRow(new CSubmit("filter_set", _('Filter')));
$filterForm->addItemToBottomRow($reset);

$latest_wdgt->addFlicker($filterForm, CProfile::get('web.latest.filter.state', 1));
//-------

validate_sort_and_sortorder('i.name',ZBX_SORT_UP);

// js templates
require_once dirname(__FILE__).'/include/views/js/general.script.confirm.js.php';
require_once dirname(__FILE__).'/include/views/js/monitoring.latest.js.php';

$link = new CCol(new CDiv(null, 'app-list-toggle-all icon-plus-9x9'));

$table = new CTableInfo(_('No values found.'));
$table->setHeader(array(
	$link,
	is_show_all_nodes() ? make_sorting_header(_('Node'), 'h.hostid') : null,
	($_REQUEST['hostid'] == 0) ? make_sorting_header(_('Host'), 'h.name') : NULL,
	make_sorting_header(_('Name'), 'i.name'),
	make_sorting_header(_('Last check'), 'i.lastclock'),
	_('Last value'),
	_x('Change', 'noun in latest data'),
	_('History')
));

/**
 * Display APPLICATION ITEMS
 */
$db_apps = array();
$db_appids = array();

$options = array(
	'output' => array('name', 'hostid'),
	'hostids' => $available_hosts,
	'selectScreens' => API_OUTPUT_COUNT,
	'selectInventory' => array('hostid'),
	'preservekeys' => true
);

$sql_from = '';
$sql_where = '';
if($_REQUEST['groupid'] > 0){
	$sql_from .= ',hosts_groups hg ';
	$sql_where.= ' AND hg.hostid=h.hostid AND hg.groupid='.$_REQUEST['groupid'];

	$options['groupid'] = $_REQUEST['groupid'];
}

if($_REQUEST['hostid']>0){
	$sql_where.= ' AND h.hostid='.$_REQUEST['hostid'];

	$options['hostids'] = $_REQUEST['hostid'];
}

$hosts = API::Host()->get($options);

// fetch scripts for the host JS menu
if ($_REQUEST['hostid'] == 0) {
	$hostScripts = API::Script()->getScriptsByHosts($options['hostids']);
}

// select hosts
$sql = 'SELECT DISTINCT h.name as hostname,h.hostid, a.* '.
		' FROM applications a, hosts h '.$sql_from.
		' WHERE a.hostid=h.hostid'.
			$sql_where.
			' AND '.dbConditionInt('h.hostid', $available_hosts);

$db_app_res = DBselect($sql);
while($db_app = DBfetch($db_app_res)){
	$db_app['item_cnt'] = 0;

	$db_apps[$db_app['applicationid']] = $db_app;
	$db_appids[$db_app['applicationid']] = $db_app['applicationid'];
}

$sortField = getPageSortField();
$sortOrder = getPageSortOrder();

// if sortfield is host name
if ($sortField == 'h.name') {
	$sortFields = array(array('field' => 'hostname', 'order' => $sortOrder));
}
else {
	$sortFields = array();
}
// by default order by application name and application id
array_push($sortFields, 'name', 'applicationid');
CArrayHelper::sort($db_apps, $sortFields);

$tab_rows = array();

// select items
$sql = 'SELECT DISTINCT i.*, ia.applicationid '.
		' FROM items i,items_applications ia'.
		' WHERE '.dbConditionInt('ia.applicationid',$db_appids).
			' AND i.itemid=ia.itemid'.
			($_REQUEST['show_without_data'] ? '' : ' AND i.lastvalue IS NOT NULL').
			' AND i.status='.ITEM_STATUS_ACTIVE.
			' AND '.dbConditionInt('i.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED));

$dbItems = DBfetchArray(DBselect($sql));

foreach ($dbItems as &$dbItem) {
	$dbItem['resolvedName'] = itemName($dbItem);
}
unset($dbItem);

// if sortfield is item name
if ($sortField == 'i.name') {
	$sortFields = array(array('field' => 'resolvedName', 'order' => $sortOrder), 'itemid');
}
// if sortfield is item lastclock
elseif ($sortField == 'i.lastclock') {
	$sortFields = array(array('field' => 'lastclock', 'order' => $sortOrder), 'resolvedName', 'itemid');
}
// by default
else {
	$sortFields = array('resolvedName', 'itemid');
}
CArrayHelper::sort($dbItems, $sortFields);

foreach ($dbItems as $db_item){
	if (!empty($_REQUEST['select']) && !zbx_stristr($db_item['resolvedName'], $_REQUEST['select'])) {
		continue;
	}

	if(strpos($db_item['units'], ',') !== false)
		list($db_item['units'], $db_item['unitsLong']) = explode(',', $db_item['units']);
	else
		$db_item['unitsLong'] = '';

	$db_app = &$db_apps[$db_item['applicationid']];

	if(!isset($tab_rows[$db_app['applicationid']])) $tab_rows[$db_app['applicationid']] = array();
	$app_rows = &$tab_rows[$db_app['applicationid']];

	$db_app['item_cnt']++;

	if (isset($db_item['lastclock'])) {
		$lastclock = zbx_date2str(_('d M Y H:i:s'), $db_item['lastclock']);
	}
	else {
		$lastclock = ' - ';
	}

	$lastvalue = formatItemLastValue($db_item, '-', false);

	$digits = ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT) ? 2 : 0;
	if (isset($db_item['lastvalue']) && isset($db_item['prevvalue'])
			&& ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $db_item['value_type'] == ITEM_VALUE_TYPE_UINT64)
			&& (bcsub($db_item['lastvalue'], $db_item['prevvalue'], $digits) != 0)) {

		$change = '';
		if (($db_item['lastvalue'] - $db_item['prevvalue']) > 0) {
			$change = '+';
		}

		// for 'unixtime' change should be calculated as uptime
		$change .= convert_units(
			bcsub($db_item['lastvalue'], $db_item['prevvalue'], $digits),
			$db_item['units'] == 'unixtime' ? 'uptime' : $db_item['units'],
			0
		);
		$change = nbsp($change);
	}
	else {
		$change = ' - ';
	}

	if(($db_item['value_type']==ITEM_VALUE_TYPE_FLOAT) || ($db_item['value_type']==ITEM_VALUE_TYPE_UINT64)){
		$actions = new CLink(_('Graph'),'history.php?action=showgraph&itemid='.$db_item['itemid']);
	}
	else{
		$actions = new CLink(_('History'),'history.php?action=showvalues&itemid='.$db_item['itemid']);
	}

	$item_status = $db_item['state'] == ITEM_STATE_NOTSUPPORTED ? 'unknown' : null;

	array_push($app_rows, new CRow(array(
		SPACE,
		is_show_all_nodes()?SPACE:null,
		($_REQUEST['hostid']>0)?NULL:SPACE,
		new CCol(new CDiv(SPACE.SPACE.$db_item['resolvedName'], $item_status)),
		new CCol(new CDiv($lastclock, $item_status)),
		new CCol(new CDiv($lastvalue, $item_status)),
		new CCol(new CDiv($change, $item_status)),
		$actions
	)));
}
unset($app_rows);
unset($db_app);

foreach ($db_apps as $appid => $dbApp) {
	$host = $hosts[$dbApp['hostid']];

	if(!isset($tab_rows[$appid])) continue;

	$appRows = $tab_rows[$appid];

	$openState = CProfile::get('web.latest.toggle', null, $dbApp['applicationid']);

	$toggle = new CDiv(SPACE, 'app-list-toggle icon-plus-9x9');
	if ($openState) {
		$toggle->addClass('icon-minus-9x9');
	}
	$toggle->setAttribute('data-app-id', $dbApp['applicationid']);
	$toggle->setAttribute('data-open-state', $openState);

	$col = new CCol(array(bold($dbApp['name']),SPACE.'('._n('%1$s Item', '%1$s Items', $dbApp['item_cnt']).')'));
	$col->setColSpan(5);

	// host JS menu link
	$hostSpan = null;
	if ($_REQUEST['hostid'] == 0) {
		$hostSpan = new CSpan($host['name'], 'link_menu menu-host');
		$scripts = $hostScripts[$host['hostid']];
		$hostSpan->setAttribute('data-menu', hostMenuData($host, $scripts));
		$hostSpan = new CDiv($hostSpan);
	}

	// add toggle row
	$table->addRow(array(
		$toggle,
		get_node_name_by_elid($dbApp['applicationid']),
		$hostSpan,
		$col
	), 'odd_row');

	// add toggle sub rows
	foreach ($appRows as $row) {
		$row->setAttribute('parent_app_id', $dbApp['applicationid']);
		$row->addClass('odd_row');
		if (!$openState) {
			$row->addClass('hidden');
		}
		$table->addRow($row);
	}
}

/**
 * Display OTHER ITEMS (which are not linked to application)
 */
$db_hosts = array();
$db_hostids = array();

// select hosts
$sql = 'SELECT DISTINCT h.name,h.hostid '.
		' FROM hosts h'.$sql_from.', items i '.
			' LEFT JOIN items_applications ia ON ia.itemid=i.itemid'.
		' WHERE ia.itemid is NULL '.
			$sql_where.
			' AND h.hostid=i.hostid '.
			' AND '.dbConditionInt('i.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)).
			' AND '.dbConditionInt('h.hostid', $available_hosts);

$dbHostRes = DBfetchArray(DBselect($sql));

// if sortfield is host name
if ($sortField == 'h.name') {
	$sortFields = array(array('field' => 'name', 'order' => $sortOrder));
}
else {
	$sortFields = array('name');
}
CArrayHelper::sort($dbHostRes, $sortFields);

foreach ($dbHostRes as $dbHost) {
	$dbHost['item_cnt'] = 0;
	$db_hosts[$dbHost['hostid']] = $dbHost;
	$db_hostids[$dbHost['hostid']] = $dbHost['hostid'];
}

$tab_rows = array();

// select items
$sql = 'SELECT DISTINCT h.host as hostname,h.hostid,i.* '.
		' FROM hosts h'.$sql_from.', items i '.
			' LEFT JOIN items_applications ia ON ia.itemid=i.itemid'.
		' WHERE ia.itemid is NULL '.
			$sql_where.
			' AND h.hostid=i.hostid '.
			($_REQUEST['show_without_data'] ? '' : ' AND i.lastvalue IS NOT NULL').
			' AND i.status='.ITEM_STATUS_ACTIVE.
			' AND '.dbConditionInt('i.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)).
			' AND '.dbConditionInt('h.hostid', $db_hostids);

$dbItems = DBfetchArray(DBselect($sql));

foreach ($dbItems as &$dbItem) {
	$dbItem['resolvedName'] = itemName($dbItem);
}
unset($dbItem);

// if sortfield is item name
if ($sortField == 'i.name') {
	$sortFields = array(array('field' => 'resolvedName', 'order' => $sortOrder), 'itemid');
}
// if sortfield is item lastclock
elseif ($sortField == 'i.lastclock') {
	$sortFields = array(array('field' => 'lastclock', 'order' => $sortOrder), 'resolvedName', 'itemid');
}
// by default
else {
	$sortFields = array('resolvedName', 'itemid');
}
CArrayHelper::sort($dbItems, $sortFields);

foreach ($dbItems as $db_item){
	if (!empty($_REQUEST['select']) && !zbx_stristr($db_item['resolvedName'], $_REQUEST['select'])) {
		continue;
	}

	if (strpos($db_item['units'], ',') !== false)
		list($db_item['units'], $db_item['unitsLong']) = explode(',', $db_item['units']);
	else
		$db_item['unitsLong'] = '';

	$db_host = &$db_hosts[$db_item['hostid']];

	if (!isset($tab_rows[$db_host['hostid']])) $tab_rows[$db_host['hostid']] = array();
	$app_rows = &$tab_rows[$db_host['hostid']];

	$db_host['item_cnt']++;

	// column "lastclock"
	if (isset($db_item['lastclock'])) {
		$lastclock = zbx_date2str(_('d M Y H:i:s'), $db_item['lastclock']);
	}
	else {
		$lastclock = ' - ';
	}

	// column "lastvalue"
	$lastvalue = formatItemLastValue($db_item);

	// column "change"
	$digits = ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT) ? 2 : 0;
	if (isset($db_item['lastvalue']) && isset($db_item['prevvalue'])
			&& ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $db_item['value_type'] == ITEM_VALUE_TYPE_UINT64)
			&& (bcsub($db_item['lastvalue'], $db_item['prevvalue'], $digits) != 0)) {

		$change = '';
		if (($db_item['lastvalue'] - $db_item['prevvalue']) > 0) {
			$change = '+';
		}

		// for 'unixtime' change should be calculated as uptime
		$change .= convert_units(
			bcsub($db_item['lastvalue'], $db_item['prevvalue'], $digits),
			$db_item['units'] == 'unixtime' ? 'uptime' : $db_item['units'],
			0
		);
		$change = nbsp($change);
	}
	else {
		$change = ' - ';
	}

	// column "action"
	if (($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT) || ($db_item['value_type'] == ITEM_VALUE_TYPE_UINT64)) {
		$actions = new CLink(_('Graph'), 'history.php?action=showgraph&itemid='.$db_item['itemid']);
	}
	else{
		$actions = new CLink(_('History'), 'history.php?action=showvalues&itemid='.$db_item['itemid']);
	}

	$item_status = $db_item['state'] == ITEM_STATE_NOTSUPPORTED ? 'unknown' : null;
	array_push($app_rows, new CRow(array(
		SPACE,
		is_show_all_nodes() ? ($db_host['item_cnt'] ? SPACE : get_node_name_by_elid($db_item['itemid'])) : null,
		$_REQUEST['hostid'] ? null : SPACE,
		new CCol(new CDiv(SPACE.SPACE.$db_item['resolvedName'], $item_status)),
		new CCol(new CDiv($lastclock, $item_status)),
		new CCol(new CDiv($lastvalue, $item_status)),
		new CCol(new CDiv($change, $item_status)),
		new CCol(new CDiv($actions, $item_status))
	)));
}
unset($app_rows);
unset($db_host);

foreach ($db_hosts as $hostId => $dbHost) {
	$host = $hosts[$dbHost['hostid']];

	if(!isset($tab_rows[$hostId])) {
		continue;
	}
	$appRows = $tab_rows[$hostId];

	$openState = CProfile::get('web.latest.toggle_other', null, $host['hostid']);

	$toggle = new CDiv(SPACE, 'app-list-toggle icon-plus-9x9');
	if ($openState) {
		$toggle->addClass('icon-minus-9x9');
	}
	$toggle->setAttribute('data-app-id', '0_'.$host['hostid']);
	$toggle->setAttribute('data-open-state', $openState);

	$col = new CCol(array(bold('- '.('other').' -'), SPACE.'('._n('%1$s Item', '%1$s Items', $dbHost['item_cnt']).')'));
	$col->setColSpan(5);

	// host JS menu link
	$hostSpan = null;
	if ($_REQUEST['hostid'] == 0) {
		$hostSpan = new CSpan($host['name'], 'link_menu menu-host');
		$scripts = $hostScripts[$host['hostid']];
		$hostSpan->setAttribute('data-menu', hostMenuData($host, $scripts));
		$hostSpan = new CDiv($hostSpan);
	}

	// add toggle row
	$table->addRow(array(
		$toggle,
		get_node_name_by_elid($dbHost['hostid']),
		$hostSpan,
		$col
	), 'odd_row');

	// add toggle sub rows
	foreach($appRows as $row) {
		$row->setAttribute('parent_app_id', '0_'.$host['hostid']);
		$row->addClass('odd_row');
		if (!$openState) {
			$row->addClass('hidden');
		}
		$table->addRow($row);
	}
}

$latest_wdgt->addItem($table);
$latest_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
