<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
$page['scripts'] = ['class.calendar.js', 'gtlc.js', 'flickerfreescreen.js', 'layout.mode.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['web_layout_mode'] = CViewHelper::loadLayoutMode();

if (hasRequest('plaintext')) {
	define('ZBX_PAGE_NO_MENU', true);
}
define('ZBX_PAGE_DO_JS_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'itemids' =>		[T_ZBX_INT,			O_OPT, P_SYS,	DB_ID,	null],
	'from' =>			[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,	null],
	'to' =>				[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,	null],
	'filter_task' =>	[T_ZBX_STR,			O_OPT, null,	IN(FILTER_TASK_SHOW.','.FILTER_TASK_HIDE.','.FILTER_TASK_MARK.','.FILTER_TASK_INVERT_MARK), null],
	'filter' =>			[T_ZBX_STR,			O_OPT, null,	null,	null],
	'mark_color' =>		[T_ZBX_STR,			O_OPT, null,	IN(MARK_COLOR_RED.','.MARK_COLOR_GREEN.','.MARK_COLOR_BLUE), null],
	'plaintext' =>		[T_ZBX_STR,			O_OPT, null,	null,	null],
	'action' =>			[T_ZBX_STR,			O_OPT, P_SYS,	IN('"'.HISTORY_GRAPH.'","'.HISTORY_VALUES.'","'.HISTORY_LATEST.'","'.HISTORY_BATCH_GRAPH.'"'), null],
	'graphtype' =>		[T_ZBX_INT,			O_OPT, null,   IN([GRAPH_TYPE_NORMAL, GRAPH_TYPE_STACKED]), null],
	// filter
	'filter_rst' =>		[T_ZBX_STR,			O_OPT, P_SYS,	null,	null]
];
check_fields($fields);

validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

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
$itemids = getRequest('filter_rst') ? [] : getRequest('itemids', []);
$items = [];
$value_type = '';

if ($itemids) {
	$items = API::Item()->get([
		'output' => ['itemid', 'name', 'value_type'],
		'selectHosts' => ['name'],
		'itemids' => $itemids,
		'preservekeys' => true,
		'templated' => false,
		'webitems' => true
	]);

	foreach ($itemids as $itemid) {
		if (!array_key_exists($itemid, $items)) {
			access_deny();
		}
	}

	$item = reset($items);
	$value_type = $item['value_type'];
}

$data = [
	'itemids' => $itemids,
	'items' => $items,
	'value_type' => $value_type,
	'action' => getRequest('action'),
	'from' => getRequest('from'),
	'to' => getRequest('to'),
	'page' => getRequest('page', 1),
	'plaintext' => hasRequest('plaintext'),
	'graphtype' => getRequest('graphtype', GRAPH_TYPE_NORMAL),
	'iv_string' => [ITEM_VALUE_TYPE_LOG => true, ITEM_VALUE_TYPE_TEXT => true],
	'iv_numeric' => [ITEM_VALUE_TYPE_FLOAT => true, ITEM_VALUE_TYPE_UINT64 => true],
	'profileIdx' => 'web.item.graph.filter',
	'profileIdx2' => 0,
	'filter_task' => getRequest('filter_rst') ? FILTER_TASK_SHOW : getRequest('filter_task', FILTER_TASK_SHOW),
	'mark_color' => getRequest('mark_color', MARK_COLOR_RED),
	'filter' => getRequest('filter_rst') ? '' : getRequest('filter', ''),
	'active_tab' => CProfile::get('web.item.graph.filter.active', 1)
];

if ($data['action'] != HISTORY_BATCH_GRAPH && $itemids) {
	$data['profileIdx2'] = reset($itemids);
}

updateTimeSelectorPeriod([
	'profileIdx' => $data['profileIdx'],
	'profileIdx2' => $data['profileIdx2'],
	'from' => getRequest('from'),
	'to' => getRequest('to')
]);

// render view
echo (new CView('monitoring.history', $data))->getOutput();

require_once dirname(__FILE__).'/include/page_footer.php';
