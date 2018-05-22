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
require_once dirname(__FILE__).'/include/graphs.inc.php';
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/blocks.inc.php';

$page['title'] = _('Host screens');
$page['file'] = 'screens.php';
$page['scripts'] = ['effects.js', 'dragdrop.js', 'class.calendar.js', 'gtlc.js', 'flickerfreescreen.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_JS_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>		[T_ZBX_INT,			O_OPT, P_SYS, DB_ID,		null],
	'tr_groupid' =>	[T_ZBX_INT,			O_OPT, P_SYS, DB_ID,		null],
	'tr_hostid' =>	[T_ZBX_INT,			O_OPT, P_SYS, DB_ID,		null],
	'screenid' =>	[T_ZBX_INT,			O_OPT, P_SYS|P_NZERO, DB_ID, null],
	'step' =>		[T_ZBX_INT,			O_OPT, P_SYS, BETWEEN(0, 65535), null],
	'from' =>		[T_ZBX_RANGE_TIME,	O_OPT, P_SYS, null,		null],
	'to' =>			[T_ZBX_RANGE_TIME,	O_OPT, P_SYS, null,		null],
	'reset' =>		[T_ZBX_STR,			O_OPT, P_SYS, IN('"reset"'), null],
	'fullscreen' =>	[T_ZBX_INT,			O_OPT, P_SYS, IN('0,1'),	null]
];
check_fields($fields);

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Display
 */
$data = [
	'hostid' => getRequest('hostid', 0),
	'fullscreen' => getRequest('fullscreen', 0),
	'screenid' => getRequest('screenid', CProfile::get('web.hostscreen.screenid', null)),
	'from' => getRequest('from'),
	'to' => getRequest('to'),
	'profileIdx' => 'web.screens.filter',
	'active_tab' => CProfile::get('web.screens.filter.active', 1)
];
CProfile::update('web.hostscreen.screenid', $data['screenid'], PROFILE_TYPE_ID);

// get screen list
$data['screens'] = API::TemplateScreen()->get([
	'hostids' => $data['hostid'],
	'output' => API_OUTPUT_EXTEND
]);
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

$data['screen'] = API::TemplateScreen()->get([
	'screenids' => $screenid,
	'hostids' => $data['hostid'],
	'output' => API_OUTPUT_EXTEND,
	'selectScreenItems' => API_OUTPUT_EXTEND
]);
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
