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

$page['title'] = _('Custom screens');
$page['file'] = 'screens.php';
$page['hist_arg'] = array('elementid', 'screenname');
$page['scripts'] = array('class.calendar.js', 'gtlc.js', 'flickerfreescreen.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_JS_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'tr_groupid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'tr_hostid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'elementid' =>	array(T_ZBX_INT, O_OPT, P_SYS|P_NZERO, DB_ID, null),
	'screenname' =>	array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'step' =>		array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(0, 65535), null),
	'period' =>		array(T_ZBX_INT, O_OPT, P_SYS,	null,		null),
	'stime' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'reset' =>		array(T_ZBX_STR, O_OPT, P_SYS,	IN('"reset"'), null),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'), null),
	// ajax
	'filterState' => array(T_ZBX_INT, O_OPT, P_ACT,	null,		null),
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favid' =>		array(T_ZBX_INT, O_OPT, P_ACT,	null,		null),
	'favaction' =>	array(T_ZBX_STR, O_OPT, P_ACT,	IN('"add","remove"'), null)
);
check_fields($fields);

/*
 * Permissions
 */
// validate group IDs
$validateGroupIds = array_filter(array(
	getRequest('groupid'),
	getRequest('tr_groupid')
));
if ($validateGroupIds && !API::HostGroup()->isReadable($validateGroupIds)) {
	access_deny();
}

// validate host IDs
$validateHostIds = array_filter(array(
	getRequest('hostid'),
	getRequest('tr_hostid')
));
if ($validateHostIds && !API::Host()->isReadable($validateHostIds)) {
	access_deny();
}

if (getRequest('elementid')) {
	$screens = API::Screen()->get(array(
		'screenids' => array($_REQUEST['elementid']),
		'output' => array('screenid')
	));
	if (!$screens) {
		access_deny();
	}
}


/*
 * Filter
 */
if (hasRequest('filterState')) {
	CProfile::update('web.screens.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}

if (isset($_REQUEST['favobj'])) {
	if (getRequest('favobj') === 'timeline' && hasRequest('elementid') && hasRequest('period')) {
		navigation_bar_calc('web.screens', $_REQUEST['elementid'], true);
	}

	if (str_in_array($_REQUEST['favobj'], array('screenid', 'slideshowid'))) {
		$result = false;

		DBstart();

		if ($_REQUEST['favaction'] == 'add') {
			$result = CFavorite::add('web.favorite.screenids', $_REQUEST['favid'], $_REQUEST['favobj']);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Remove from favourites').'";'."\n".
					'$("addrm_fav").onclick = function() { rm4favorites("'.$_REQUEST['favobj'].'", "'.$_REQUEST['favid'].'"); }'."\n";
			}
		}
		elseif ($_REQUEST['favaction'] == 'remove') {
			$result = CFavorite::remove('web.favorite.screenids', $_REQUEST['favid'], $_REQUEST['favobj']);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Add to favourites').'";'."\n".
					'$("addrm_fav").onclick = function() { add2favorites("'.$_REQUEST['favobj'].'", "'.$_REQUEST['favid'].'"); }'."\n";
			}
		}

		$result = DBend($result);

		if ($page['type'] == PAGE_TYPE_JS && $result) {
			echo 'switchElementClass("addrm_fav", "iconminus", "iconplus");';
		}
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
$data = array(
	'fullscreen' => $_REQUEST['fullscreen'],
	'period' => getRequest('period'),
	'stime' => getRequest('stime'),
	'elementid' => getRequest('elementid', false),

	// whether we should use screen name to fetch a screen (if this is false, elementid is used)
	'use_screen_name' => isset($_REQUEST['screenname'])
);

// if none is provided
if (empty($data['elementid']) && !$data['use_screen_name']) {
	// get element id saved in profile from the last visit
	$data['elementid'] = CProfile::get('web.screens.elementid', null);
}

$data['screens'] = API::Screen()->get(array(
	'output' => array('screenid', 'name')
));

// if screen name is provided it takes priority over elementid
if ($data['use_screen_name']) {
	$data['screens'] = zbx_toHash($data['screens'], 'name');
	$data['elementIdentifier'] = getRequest('screenname');
}
else {
	$data['screens'] = zbx_toHash($data['screens'], 'screenid');
	$data['elementIdentifier'] = $data['elementid'];
}
order_result($data['screens'], 'name');

// render view
$screenView = new CView('monitoring.screen', $data);
$screenView->render();
$screenView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
