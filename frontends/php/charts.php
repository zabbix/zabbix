<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
require_once dirname(__FILE__).'/include/graphs.inc.php';

$page['title'] = _('Custom graphs');
$page['file'] = 'charts.php';
$page['scripts'] = ['class.calendar.js', 'gtlc.js', 'flickerfreescreen.js', 'layout.mode.js', 'multiselect.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['web_layout_mode'] = CViewHelper::loadLayoutMode();

define('ZBX_PAGE_DO_JS_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'from'                  => [T_ZBX_RANGE_TIME, O_OPT, P_SYS, null,                                                              null],
	'to'                    => [T_ZBX_RANGE_TIME, O_OPT, P_SYS, null,                                                              null],
	'action'                => [T_ZBX_STR,        O_OPT, P_SYS, IN('"'.HISTORY_GRAPH.'", "'.HISTORY_VALUES.'"'),                   null],
	'filter_search_type'    => [T_ZBX_INT,        O_OPT, P_SYS, IN('"'.ZBX_SEARCH_TYPE_STRICT.'", "'.ZBX_SEARCH_TYPE_PATTERN.'"'), null],
	'filter_hostids'        => [T_ZBX_INT,        O_OPT, null,  DB_ID,                                                             null],
	'filter_graphids'       => [T_ZBX_INT,        O_OPT, null,  DB_ID,                                                             null],
	'filter_graph_patterns' => [T_ZBX_STR,        O_OPT, null,  null,                                                              null]
];
check_fields($fields);

validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

/*
 * Permissions
 */
if (getRequest('groupid') && !isReadableHostGroups([getRequest('groupid')])) {
	access_deny();
}
if (getRequest('hostid') && !isReadableHosts([getRequest('hostid')])) {
	access_deny();
}
if (getRequest('graphid')) {
	$graphs = API::Graph()->get([
		'graphids' => [$_REQUEST['graphid']],
		'output' => ['graphid']
	]);
	if (!$graphs) {
		access_deny();
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Display
 */
$timeselector_options = [
	'profileIdx' => 'web.graphs.filter',
	'profileIdx2' => null,
	'from' => getRequest('from'),
	'to' => getRequest('to')
];
updateTimeSelectorPeriod($timeselector_options);

$data = [
	'action' => getRequest('action', HISTORY_GRAPH),
	'actions' => [
		HISTORY_GRAPH => _('Graph'),
		HISTORY_VALUES => _('Values')
	],
	'ms_hosts' => [],
	'ms_graphs' => [],
	'ms_graph_patterns' => [],
	'timeline' => getTimeSelectorPeriod($timeselector_options),
	'page' => getRequest('page', 1),
	'active_tab' => CProfile::get('web.graphs.filter.active', 1),
	'search_type' => ZBX_SEARCH_TYPE_STRICT
];

// render view
echo (new CView('monitoring.charts', $data))->getOutput();

require_once dirname(__FILE__).'/include/page_footer.php';
