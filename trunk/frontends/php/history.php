<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
$page['scripts'] = ['class.calendar.js', 'gtlc.js', 'flickerfreescreen.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

if (isset($_REQUEST['plaintext'])) {
	define('ZBX_PAGE_NO_MENU', 1);
}
define('ZBX_PAGE_DO_JS_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'itemids' =>		[T_ZBX_INT, O_MAND, P_SYS,	DB_ID,	null],
	'period' =>			[T_ZBX_INT, O_OPT, null,	null,	null],
	'stime' =>			[T_ZBX_STR, O_OPT, null,	null,	null],
	'isNow' =>			[T_ZBX_INT, O_OPT, null,	IN('0,1'),	null],
	'filter_task' =>	[T_ZBX_STR, O_OPT, null,	IN(FILTER_TASK_SHOW.','.FILTER_TASK_HIDE.','.FILTER_TASK_MARK.','.FILTER_TASK_INVERT_MARK), null],
	'filter' =>			[T_ZBX_STR, O_OPT, null,	null,	null],
	'mark_color' =>		[T_ZBX_STR, O_OPT, null,	IN(MARK_COLOR_RED.','.MARK_COLOR_GREEN.','.MARK_COLOR_BLUE), null],
	'cmbitemlist' =>	[T_ZBX_INT, O_OPT, null,	DB_ID,	null],
	'plaintext' =>		[T_ZBX_STR, O_OPT, null,	null,	null],
	'action' =>			[T_ZBX_STR, O_OPT, P_SYS,	IN('"'.HISTORY_GRAPH.'","'.HISTORY_VALUES.'","'.HISTORY_LATEST.'","'.HISTORY_BATCH_GRAPH.'"'), null],
	'graphtype' =>		[T_ZBX_INT, O_OPT, null,   IN([GRAPH_TYPE_NORMAL, GRAPH_TYPE_STACKED]), null],
	// actions
	'reset' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null],
	'cancel' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'form' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'form_copy_to' =>	[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT, null,	null,	null],
	'fullscreen' =>		[T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null]
];
check_fields($fields);

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Actions
 */
$_REQUEST['action'] = getRequest('action', HISTORY_GRAPH);

/*
 * Display
 */
$items = API::Item()->get([
	'itemids' => getRequest('itemids'),
	'webitems' => true,
	'selectHosts' => ['name'],
	'output' => ['itemid', 'key_', 'name', 'value_type', 'hostid', 'valuemapid', 'history', 'trends'],
	'preservekeys' => true
]);

foreach (getRequest('itemids') as $itemid) {
	if (!isset($items[$itemid])) {
		access_deny();
	}
}

$items = CMacrosResolverHelper::resolveItemNames($items);

$item = reset($items);

$data = [
	'itemids' => getRequest('itemids'),
	'items' => $items,
	'value_type' => $item['value_type'],
	'action' => getRequest('action'),
	'period' => getRequest('period'),
	'stime' => getRequest('stime'),
	'isNow' => getRequest('isNow'),
	'plaintext' => isset($_REQUEST['plaintext']),
	'iv_string' => [ITEM_VALUE_TYPE_LOG => 1, ITEM_VALUE_TYPE_TEXT => 1],
	'iv_numeric' => [ITEM_VALUE_TYPE_FLOAT => 1, ITEM_VALUE_TYPE_UINT64 => 1],
	'fullscreen' => $_REQUEST['fullscreen'],
	'graphtype' => getRequest('graphtype', GRAPH_TYPE_NORMAL)
];

// render view
$historyView = new CView('monitoring.history', $data);
$historyView->render();
$historyView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
