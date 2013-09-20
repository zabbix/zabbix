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
$fields = array(
	'apps'=>				array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,		NULL),
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

/*
 * Permissions
 */
if (get_request('groupid') && !API::HostGroup()->isReadable(array($_REQUEST['groupid']))) {
	access_deny();
}
if (get_request('hostid') && !API::Host()->isReadable(array($_REQUEST['hostid']))) {
	access_deny();
}

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
		'real_hosts' => true,
	),
	'hosts' => array(
		'with_monitored_items' => true
	),
	'hostid' => get_request('hostid', null),
	'groupid' => get_request('groupid', null),
);
$pageFilter = new CPageFilter($options);
$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['hostid'] = $pageFilter->hostid;

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

$sortField = getPageSortField();
$sortOrder = getPageSortOrder();

// js templates
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

// fetch hosts
$availableHostIds = array();
if ($_REQUEST['hostid']) {
	$availableHostIds = array($_REQUEST['hostid']);
}
elseif ($pageFilter->hostsSelected) {
	$availableHostIds = array_keys($pageFilter->hosts);
}

$hosts = API::Host()->get(array(
	'output' => array('name', 'hostid', 'status'),
	'hostids' => $availableHostIds,
	'with_monitored_items' => true,
	'selectScreens' => API_OUTPUT_COUNT,
	'selectInventory' => array('hostid'),
	'preservekeys' => true
));
foreach ($hosts as &$host) {
	$host['item_cnt'] = 0;
}
unset($host);

// sort hosts
if ($sortField == 'h.name') {
	$sortFields = array(array('field' => 'name', 'order' => $sortOrder));
}
else {
	$sortFields = array('name');
}
CArrayHelper::sort($hosts, $sortFields);

$hostIds = array_keys($hosts);

// fetch scripts for the host JS menu
if ($_REQUEST['hostid'] == 0) {
	$hostScripts = API::Script()->getScriptsByHosts($hostIds);
}

// fetch applications
$applications = API::Application()->get(array(
	'output' => API_OUTPUT_EXTEND,
	'hostids' => $hostIds,
	'preservekeys' => true
));
foreach ($applications as &$application) {
	$application['hostname'] = $hosts[$application['hostid']]['name'];
	$application['item_cnt'] = 0;
}
unset($application);

// if sortfield is host name
if ($sortField == 'h.name') {
	$sortFields = array(array('field' => 'hostname', 'order' => $sortOrder));
}
else {
	$sortFields = array();
}
// by default order by application name and application id
array_push($sortFields, 'name', 'applicationid');
CArrayHelper::sort($applications, $sortFields);

// fetch items and data
$allItems = DBfetchArray(DBselect(
	'SELECT DISTINCT i.hostid,i.itemid,i.name,i.value_type,i.units,i.state,i.valuemapid,i.key_,ia.applicationid'.
	' FROM items i '.
		' LEFT JOIN items_applications ia ON ia.itemid=i.itemid'.
	' WHERE i.status='.ITEM_STATUS_ACTIVE.
		' AND '.dbConditionInt('i.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)).
		' AND '.dbConditionInt('i.hostid', $hostIds)
));

// select history
$history = Manager::History()->getLast($allItems, 2);

// filter items
foreach ($allItems as $key => &$item) {
	// filter items without history
	if (!get_request('show_without_data') && !isset($history[$item['itemid']])) {
		unset($allItems[$key]);

		continue;
	}

	$item['resolvedName'] = itemName($item);

	// filter items by name
	if (!zbx_empty($_REQUEST['select']) && !zbx_stristr($item['resolvedName'], $_REQUEST['select'])) {
		unset($allItems[$key]);
	}
}
unset($item);

// add item last update date for sorting
foreach ($allItems as &$item) {
	if (isset($history[$item['itemid']])) {
		$item['lastclock'] = $history[$item['itemid']][0]['clock'];
	}
}
unset($item);

// sort items
if ($sortField == 'i.name') {
	$sortFields = array(array('field' => 'resolvedName', 'order' => $sortOrder), 'itemid');
}
elseif ($sortField == 'i.lastclock') {
	$sortFields = array(array('field' => 'lastclock', 'order' => $sortOrder), 'resolvedName', 'itemid');
}
else {
	$sortFields = array('resolvedName', 'itemid');
}
CArrayHelper::sort($allItems, $sortFields);

/**
 * Display APPLICATION ITEMS
 */
$tab_rows = array();
foreach ($allItems as $key => $db_item){
	if (!$db_item['applicationid']) {
		continue;
	}

	$lastHistory = isset($history[$db_item['itemid']][0]) ? $history[$db_item['itemid']][0] : null;
	$prevHistory = isset($history[$db_item['itemid']][1]) ? $history[$db_item['itemid']][1] : null;

	if(strpos($db_item['units'], ',') !== false)
		list($db_item['units'], $db_item['unitsLong']) = explode(',', $db_item['units']);
	else
		$db_item['unitsLong'] = '';

	$db_app = &$applications[$db_item['applicationid']];

	if(!isset($tab_rows[$db_app['applicationid']])) $tab_rows[$db_app['applicationid']] = array();
	$app_rows = &$tab_rows[$db_app['applicationid']];

	$db_app['item_cnt']++;

	// last check time and last value
	if ($lastHistory) {
		$lastClock = zbx_date2str(_('d M Y H:i:s'), $lastHistory['clock']);
		$lastValue = formatHistoryValue($lastHistory['value'], $db_item, false);
	}
	else {
		$lastClock = UNKNOWN_VALUE;
		$lastValue = UNKNOWN_VALUE;
	}

	// change
	$digits = ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT) ? 2 : 0;
	if ($lastHistory && $prevHistory
			&& ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $db_item['value_type'] == ITEM_VALUE_TYPE_UINT64)
			&& (bcsub($lastHistory['value'], $prevHistory['value'], $digits) != 0)) {

		$change = '';
		if (($lastHistory['value'] - $prevHistory['value']) > 0) {
			$change = '+';
		}

		// for 'unixtime' change should be calculated as uptime
		$change .= convert_units(array(
			'value' => bcsub($lastHistory['value'], $prevHistory['value'], $digits),
			'units' => $db_item['units'] == 'unixtime' ? 'uptime' : $db_item['units']
		));
		$change = nbsp($change);
	}
	else {
		$change = UNKNOWN_VALUE;
	}

	if(($db_item['value_type']==ITEM_VALUE_TYPE_FLOAT) || ($db_item['value_type']==ITEM_VALUE_TYPE_UINT64)){
		$actions = new CLink(_('Graph'),'history.php?action=showgraph&itemid='.$db_item['itemid']);
	}
	else{
		$actions = new CLink(_('History'),'history.php?action=showvalues&itemid='.$db_item['itemid']);
	}

	$stateCss = ($db_item['state'] == ITEM_STATE_NOTSUPPORTED) ? 'unknown txt' : 'txt';

	array_push($app_rows, new CRow(array(
		SPACE,
		is_show_all_nodes() ? SPACE : null,
		($_REQUEST['hostid'] > 0) ? null : SPACE,
		new CCol(new CDiv(SPACE.SPACE.$db_item['resolvedName'], $stateCss)),
		new CCol(new CDiv($lastClock, $stateCss)),
		new CCol(new CDiv($lastValue, $stateCss)),
		new CCol(new CDiv($change, $stateCss)),
		$actions
	)));

	// remove items with applications from the collection
	unset($allItems[$key]);
}
unset($app_rows);
unset($db_app);

foreach ($applications as $appid => $dbApp) {
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

	$hostName = null;

	if ($_REQUEST['hostid'] == 0) {
		$hostName = new CSpan($host['name'],
			'link_menu menu-host'.(($host['status'] == HOST_STATUS_NOT_MONITORED) ? ' not-monitored' : '')
		);
		$hostName->setMenuPopup(getMenuPopupHost($host, $hostScripts[$host['hostid']]));
	}

	// add toggle row
	$table->addRow(array(
		$toggle,
		get_node_name_by_elid($dbApp['applicationid']),
		$hostName,
		new CCol(array(
				bold($dbApp['name']),
				SPACE.'('._n('%1$s Item', '%1$s Items', $dbApp['item_cnt']).')'
			), null, 5
		)
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
$tab_rows = array();
foreach ($allItems as $db_item){
	$lastHistory = isset($history[$db_item['itemid']][0]) ? $history[$db_item['itemid']][0] : null;
	$prevHistory = isset($history[$db_item['itemid']][1]) ? $history[$db_item['itemid']][1] : null;

	if (strpos($db_item['units'], ',') !== false)
		list($db_item['units'], $db_item['unitsLong']) = explode(',', $db_item['units']);
	else
		$db_item['unitsLong'] = '';

	$db_host = &$hosts[$db_item['hostid']];

	if (!isset($tab_rows[$db_host['hostid']])) $tab_rows[$db_host['hostid']] = array();
	$app_rows = &$tab_rows[$db_host['hostid']];

	$db_host['item_cnt']++;

	// last check time and last value
	if ($lastHistory) {
		$lastClock = zbx_date2str(_('d M Y H:i:s'), $lastHistory['clock']);
		$lastValue = formatHistoryValue($lastHistory['value'], $db_item, false);
	}
	else {
		$lastClock = UNKNOWN_VALUE;
		$lastValue = UNKNOWN_VALUE;
	}

	// column "change"
	$digits = ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT) ? 2 : 0;
	if (isset($lastHistory['value']) && isset($prevHistory['value'])
			&& ($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $db_item['value_type'] == ITEM_VALUE_TYPE_UINT64)
			&& (bcsub($lastHistory['value'], $prevHistory['value'], $digits) != 0)) {

		$change = '';
		if (($lastHistory['value'] - $prevHistory['value']) > 0) {
			$change = '+';
		}

		// for 'unixtime' change should be calculated as uptime
		$change .= convert_units(array(
			'value' => bcsub($lastHistory['value'], $prevHistory['value'], $digits),
			'units' => $db_item['units'] == 'unixtime' ? 'uptime' : $db_item['units']
		));
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

	$stateCss = ($db_item['state'] == ITEM_STATE_NOTSUPPORTED) ? 'unknown txt' : 'txt';

	array_push($app_rows, new CRow(array(
		SPACE,
		is_show_all_nodes() ? ($db_host['item_cnt'] ? SPACE : get_node_name_by_elid($db_item['itemid'])) : null,
		$_REQUEST['hostid'] ? null : SPACE,
		new CCol(new CDiv(SPACE.SPACE.$db_item['resolvedName'], $stateCss)),
		new CCol(new CDiv($lastClock, $stateCss)),
		new CCol(new CDiv($lastValue, $stateCss)),
		new CCol(new CDiv($change, $stateCss)),
		new CCol(new CDiv($actions, $stateCss))
	)));
}
unset($app_rows);
unset($db_host);

foreach ($hosts as $hostId => $dbHost) {
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

	$hostName = null;

	if ($_REQUEST['hostid'] == 0) {
		$hostName = new CSpan($host['name'],
			'link_menu menu-host'.(($host['status'] == HOST_STATUS_NOT_MONITORED) ? ' not-monitored' : '')
		);
		$hostName->setMenuPopup(getMenuPopupHost($host, $hostScripts[$host['hostid']]));
	}

	// add toggle row
	$table->addRow(array(
		$toggle,
		get_node_name_by_elid($dbHost['hostid']),
		$hostName,
		new CCol(
			array(
				bold('- '.('other').' -'),
				SPACE.'('._n('%1$s Item', '%1$s Items', $dbHost['item_cnt']).')'
			),
			null, 5
		)
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
