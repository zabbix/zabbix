<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
require_once dirname(__FILE__).'/include/hostgroups.inc.php';
require_once dirname(__FILE__).'/include/graphs.inc.php';
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/blocks.inc.php';

$page['title'] = _('Custom screens');
$page['file'] = 'screens.php';
$page['scripts'] = ['class.calendar.js', 'gtlc.js', 'flickerfreescreen.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_JS_REFRESH', 1);

ob_start();
require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'groupid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'hostid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'tr_groupid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'tr_hostid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'elementid' =>	[T_ZBX_INT, O_OPT, P_SYS|P_NZERO, DB_ID, null],
	'screenname' =>	[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'step' =>		[T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(0, 65535), null],
	'period' =>		[T_ZBX_INT, O_OPT, P_SYS,	null,		null],
	'stime' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'reset' =>		[T_ZBX_STR, O_OPT, P_SYS,	IN('"reset"'), null],
	'fullscreen' =>	[T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'), null],
	// ajax
	'favobj' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	'favid' =>		[T_ZBX_INT, O_OPT, P_ACT,	null,		null]
];
check_fields($fields);

/*
 * Permissions
 */
// Validate group IDs.
if (getRequest('groupid') && !isReadableHostGroups([getRequest('groupid')])) {
	access_deny();
}
if (getRequest('tr_groupid') && !isReadableHostGroups([getRequest('tr_groupid')])) {
	access_deny();
}

// Validate host IDs.
if (getRequest('hostid') && !isReadableHosts([getRequest('hostid')])) {
	access_deny();
}
if (getRequest('tr_hostid') && !isReadableHosts([getRequest('tr_hostid')])) {
	access_deny();
}

/*
 * Filter
 */
if (isset($_REQUEST['favobj'])) {
	if (getRequest('favobj') === 'timeline' && hasRequest('elementid') && hasRequest('period')) {
		navigation_bar_calc('web.screens', $_REQUEST['elementid'], true);
	}

	// saving fixed/dynamic setting to profile
	if ($_REQUEST['favobj'] == 'timelinefixedperiod' && isset($_REQUEST['favid'])) {
		CProfile::update('web.screens.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Display
 */
$data = [
	'fullscreen' => $_REQUEST['fullscreen'],
	'period' => getRequest('period'),
	'stime' => getRequest('stime')
];

$options = [
	'output' => ['screenid', 'name']
];

if (getRequest('elementid')) {
	$options['screenids'] = getRequest('elementid');
	CProfile::update('web.screens.elementid', getRequest('elementid') , PROFILE_TYPE_ID);
}
elseif (hasRequest('screenname')) {
	$options['filter']['name'] = getRequest('screenname');
}
elseif (CProfile::get('web.screens.elementid')) {
	$options['screenids'] = CProfile::get('web.screens.elementid');
}
else {
	// Redirect to screen list.
	ob_end_clean();
	redirect('screenconf.php');
}

$screens = API::Screen()->get($options);

if (!$screens && (getRequest('elementid') || hasRequest('screenname'))) {
	access_deny();
}
elseif (!$screens) {
	// Redirect to screen list.
	ob_end_clean();
	redirect('screenconf.php');
}
else {
	$data['screen'] = reset($screens);
	$data['screen']['editable'] = (bool) API::Screen()->get([
		'output' => [],
		'screenids' => [$data['screen']['screenid']],
		'editable' => true
	]);
}
ob_end_flush();

// render view
$screenView = new CView('monitoring.screen', $data);
$screenView->render();
$screenView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
