<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/graphs.inc.php';

$page['file'] = 'history.php';
$page['title'] = _('History');
$page['hist_arg'] = array('itemid', 'hostid', 'groupid', 'graphid', 'period', 'dec', 'inc', 'left', 'right', 'stime', 'action');
$page['scripts'] = array('class.calendar.js', 'gtlc.js', 'flickerfreescreen.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

if (isset($_REQUEST['plaintext'])) {
	define('ZBX_PAGE_NO_MENU', 1);
}
define('ZBX_PAGE_DO_JS_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'itemid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	'!isset({favobj})'),
	'period' =>			array(T_ZBX_INT, O_OPT, null,	null,	null),
	'dec' =>			array(T_ZBX_INT, O_OPT, null,	null,	null),
	'inc' =>			array(T_ZBX_INT, O_OPT, null,	null,	null),
	'left' =>			array(T_ZBX_INT, O_OPT, null,	null,	null),
	'right' =>			array(T_ZBX_INT, O_OPT, null,	null,	null),
	'stime' =>			array(T_ZBX_STR, O_OPT, null,	null,	null),
	'filter_task' =>	array(T_ZBX_STR, O_OPT, null,	IN(FILTER_TASK_SHOW.','.FILTER_TASK_HIDE.','.FILTER_TASK_MARK.','.FILTER_TASK_INVERT_MARK), null),
	'filter' =>			array(T_ZBX_STR, O_OPT, null,	null,	null),
	'mark_color' =>		array(T_ZBX_STR, O_OPT, null,	IN(MARK_COLOR_RED.','.MARK_COLOR_GREEN.','.MARK_COLOR_BLUE), null),
	'cmbitemlist' =>	array(T_ZBX_INT, O_OPT, null,	DB_ID,	null),
	'plaintext' =>		array(T_ZBX_STR, O_OPT, null,	null,	null),
	'action' =>			array(T_ZBX_STR, O_OPT, P_SYS,	IN('"showgraph","showvalues","showlatest","add","remove"'), null),
	// ajax
	'favobj' =>			array(T_ZBX_STR, O_OPT, P_ACT,	null,	null),
	'favref' =>			array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY, null),
	'favid' =>			array(T_ZBX_INT, O_OPT, P_ACT,	null,	null),
	'favaction' =>		array(T_ZBX_STR, O_OPT, P_ACT,	IN("'add','remove','flop'"), null),
	'favstate' =>		array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY, null),
	// actions
	'remove_log' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'reset' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form_copy_to' =>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,	null),
	'fullscreen' =>		array(T_ZBX_INT, O_OPT, P_SYS,	null,	null)
);
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'timeline') {
		navigation_bar_calc('web.item.graph', $_REQUEST['favid'], true);
	}
	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.history.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
	if (str_in_array($_REQUEST['favobj'], array('itemid', 'graphid'))) {
		$result = false;
		if ($_REQUEST['favaction'] == 'add') {
			$result = CFavorite::add('web.favorite.graphids', $_REQUEST['favid'], $_REQUEST['favobj']);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Remove from favourites').'";'."\n";
				echo '$("addrm_fav").onclick = function() { rm4favorites("itemid", "'.$_REQUEST['favid'].'", 0); }'."\n";
			}
		}
		elseif ($_REQUEST['favaction'] == 'remove') {
			$result = CFavorite::remove('web.favorite.graphids', $_REQUEST['favid'], $_REQUEST['favobj']);

			if ($result) {
				echo '$("addrm_fav").title = "'._('Add to favourites').'";'."\n";
				echo '$("addrm_fav").onclick = function() { add2favorites("itemid", "'.$_REQUEST['favid'].'"); }'."\n";
			}
		}

		if ($page['type'] == PAGE_TYPE_JS && $result) {
			echo 'switchElementsClass("addrm_fav", "iconminus", "iconplus");';
		}
	}

	// saving fixed/dynamic setting to profile
	if ($_REQUEST['favobj'] == 'timelinefixedperiod') {
		if (isset($_REQUEST['favid'])) {
			CProfile::update('web.history.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
		}
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Actions
 */
$_REQUEST['action'] = get_request('action', 'showgraph');
$_REQUEST['itemid'] = array_unique(zbx_toArray($_REQUEST['itemid']));

if (isset($_REQUEST['remove_log']) && isset($_REQUEST['cmbitemlist'])) {
	$itemList = array_flip($_REQUEST['cmbitemlist']);

	foreach ($_REQUEST['itemid'] as $id => $itemid) {
		if (count($_REQUEST['itemid']) == 1) {
			break;
		}
		if (isset($itemList[$itemid])) {
			unset($_REQUEST['itemid'][$id]);
		}
	}

	unset($_REQUEST['remove_log']);
}

/*
 * Display
 */
$items = API::Item()->get(array(
	'nodeids' => get_current_nodeid(),
	'itemids' => $_REQUEST['itemid'],
	'webitems' => true,
	'selectHosts' => array('hostid', 'name'),
	'output' => API_OUTPUT_EXTEND,
	'preservekeys' => true
));

foreach ($_REQUEST['itemid'] as $itemid) {
	if (!isset($items[$itemid])) {
		access_deny();
	}
}

$item = reset($items);
$host = reset($item['hosts']);
$item['hostname'] = $host['name'];
$itemid = reset($_REQUEST['itemid']);

$data = array(
	'itemid' => $itemid,
	'items' => $items,
	'item' => $item,
	'action' => get_request('action'),
	'period' => get_request('period'),
	'stime' => get_request('stime'),
	'plaintext' => isset($_REQUEST['plaintext']),
	'iv_string' => array(ITEM_VALUE_TYPE_LOG => 1, ITEM_VALUE_TYPE_TEXT => 1),
	'iv_numeric' => array(ITEM_VALUE_TYPE_FLOAT => 1, ITEM_VALUE_TYPE_UINT64 => 1)
);

// render view
$historyView = new CView('monitoring.history', $data);
$historyView->render();
$historyView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
