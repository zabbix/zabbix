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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
	'reset' =>		array(T_ZBX_STR, O_OPT, P_SYS,	IN("'reset'"), null),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'), null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	null),
	'favid' =>		array(T_ZBX_INT, O_OPT, P_ACT,	null,		null),
	'favaction' =>	array(T_ZBX_STR, O_OPT, P_ACT,	IN("'add','remove','flop'"), null),
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	null)
);
check_fields($fields);

/*
 * Filter
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'hat') {
		CProfile::update('web.screens.hats.'.$_REQUEST['favref'].'.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}

	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.screens.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}

	if ($_REQUEST['favobj'] == 'timeline') {
		if (isset($_REQUEST['elementid']) && isset($_REQUEST['period'])) {
			navigation_bar_calc('web.screens', $_REQUEST['elementid'], true);
		}
	}

	if (str_in_array($_REQUEST['favobj'], array('screenid', 'slideshowid'))) {
		$result = false;
		if ($_REQUEST['favaction'] == 'add') {
			$result = CFavorite::add('web.favorite.screenids', $_REQUEST['favid'], $_REQUEST['favobj']);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Remove from favourites').'";'."\n".
					'$("addrm_fav").onclick = function() { rm4favorites("'.$_REQUEST['favobj'].'", "'.$_REQUEST['favid'].'", 0); }'."\n";
			}
		}
		elseif ($_REQUEST['favaction'] == 'remove') {
			$result = CFavorite::remove('web.favorite.screenids', $_REQUEST['favid'], $_REQUEST['favobj']);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Add to favourites').'";'."\n".
					'$("addrm_fav").onclick = function() { add2favorites("'.$_REQUEST['favobj'].'", "'.$_REQUEST['favid'].'"); }'."\n";
			}
		}

		if ($page['type'] == PAGE_TYPE_JS && $result) {
			echo 'switchElementsClass("addrm_fav", "iconminus", "iconplus");';
		}
	}

	// saving fixed/dynamic setting to profile
	if ($_REQUEST['favobj'] == 'timelinefixedperiod') {
		if (isset($_REQUEST['favid'])) {
			CProfile::update('web.screens.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
		}
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Display
 */
$data = array(
	'fullscreen' => get_request('fullscreen'),
	'period' => get_request('period'),
	'stime' => get_request('stime'),
	'elementid' => get_request('elementid', false),

	// whether we should use screen name to fetch a screen (if this is false, elementid is used)
	'use_screen_name' => isset($_REQUEST['screenname'])
);

// if none is provided
if (empty($data['elementid']) && !$data['use_screen_name']) {
	// get element id saved in profile from the last visit
	$data['elementid'] = CProfile::get('web.screens.elementid', null);

	// this flag will be used in case this element does not exist
	$data['id_has_been_fetched_from_profile'] = true;
}
else {
	$data['id_has_been_fetched_from_profile'] = false;
}

$data['screens'] = API::Screen()->get(array(
	'nodeids' => get_current_nodeid(),
	'output' => array('screenid', 'name')
));

// if screen name is provided it takes priority over elementid
if ($data['use_screen_name']) {
	$data['screens'] = zbx_toHash($data['screens'], 'name');
	$data['elementIdentifier'] = get_request('screenname');
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
