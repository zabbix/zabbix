<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
require_once dirname(__FILE__).'/include/graphs.inc.php';
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/blocks.inc.php';

$page['title'] = _('Host screens');
$page['file'] = 'screens.php';
$page['hist_arg'] = array('elementid');
$page['scripts'] = array('effects.js', 'dragdrop.js', 'class.calendar.js', 'gtlc.js', 'flickerfreescreen.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_JS_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'hostid' =>		array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,		null),
	'tr_groupid' =>	array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,		null),
	'tr_hostid' =>	array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,		null),
	'screenid' =>	array(T_ZBX_INT, O_OPT, P_SYS|P_NZERO, DB_ID, null),
	'step' =>		array(T_ZBX_INT, O_OPT, P_SYS, BETWEEN(0, 65535), null),
	'period' =>		array(T_ZBX_INT, O_OPT, P_SYS, null,		null),
	'stime' =>		array(T_ZBX_STR, O_OPT, P_SYS, null,		null),
	'reset' =>		array(T_ZBX_STR, O_OPT, P_SYS, IN("'reset'"), null),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'),	null),
	// ajax
	'filterState' => array(T_ZBX_INT, O_OPT, P_ACT,	null,		null),
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT, null,		null),
	'favid' =>		array(T_ZBX_INT, O_OPT, P_ACT, null,		null),
	'favaction' =>	array(T_ZBX_STR, O_OPT, P_ACT, IN("'add','remove'"), null)
);
check_fields($fields);

/*
 * Ajax
 */
if (hasRequest('filterState')) {
	CProfile::update('web.hostscreen.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}

if (getRequest('favobj') === 'timeline' && hasRequest('elementid') && hasRequest('period')) {
	navigation_bar_calc('web.hostscreen', getRequest('elementid'), true);
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Display
 */
$data = array(
	'hostid' => get_request('hostid', 0),
	'fullscreen' => $_REQUEST['fullscreen'],
	'screenid' => get_request('screenid', CProfile::get('web.hostscreen.screenid', null)),
	'period' => get_request('period'),
	'stime' => get_request('stime')
);
CProfile::update('web.hostscreen.screenid', $data['screenid'], PROFILE_TYPE_ID);

// get screen list
$data['screens'] = API::TemplateScreen()->get(array(
	'hostids' => $data['hostid'],
	'output' => API_OUTPUT_EXTEND
));
$data['screens'] = zbx_toHash($data['screens'], 'screenid');
order_result($data['screens'], 'name');

// get screen
$screenid = null;
if (!empty($data['screens'])) {
	$screen = !isset($data['screens'][$data['screenid']]) ? reset($data['screens']) : $data['screens'][$data['screenid']];
	if (!empty($screen['screenid'])) {
		$screenid = $screen['screenid'];
	}
}

$data['screen'] = API::TemplateScreen()->get(array(
	'screenids' => $screenid,
	'hostids' => $data['hostid'],
	'output' => API_OUTPUT_EXTEND,
	'selectScreenItems' => API_OUTPUT_EXTEND
));
$data['screen'] = reset($data['screen']);

// get host
if (!empty($data['screen']['hostid'])) {
	$data['host'] = get_host_by_hostid($data['screen']['hostid']);
}

// render view
$screenView = new CView('monitoring.hostscreen', $data);
$screenView->render();
$screenView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
