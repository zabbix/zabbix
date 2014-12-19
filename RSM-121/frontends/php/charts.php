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
require_once dirname(__FILE__).'/include/graphs.inc.php';

$page['title'] = _('Custom graphs');
$page['file'] = 'charts.php';
$page['hist_arg'] = array('hostid', 'groupid', 'graphid');
$page['scripts'] = array('class.calendar.js', 'gtlc.js', 'flickerfreescreen.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_JS_REFRESH', 1);

ob_start();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>	array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,		null),
	'hostid' =>		array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,		null),
	'graphid' =>	array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,		null),
	'period' =>		array(T_ZBX_INT, O_OPT, P_SYS, null,		null),
	'stime' =>		array(T_ZBX_STR, O_OPT, P_SYS, null,		null),
	'action' =>		array(T_ZBX_STR, O_OPT, P_SYS, IN("'go','add','remove'"), null),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'),	null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT, null,		null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT, NOT_EMPTY,	null),
	'favid' =>		array(T_ZBX_INT, O_OPT, P_ACT, null,		null),
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT, NOT_EMPTY,	null),
	'favaction' =>	array(T_ZBX_STR, O_OPT, P_ACT, IN("'add','remove'"), null)
);
check_fields($fields);

$pageFilter = new CPageFilter(array(
	'groups' => array('monitored_hosts' => true, 'with_graphs' => true),
	'hosts' => array('monitored_hosts' => true, 'with_graphs' => true),
	'groupid' => get_request('groupid', null),
	'hostid' => get_request('hostid', null),
	'graphs' => array('templated' => 0),
	'graphid' => get_request('graphid', null)
));

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.charts.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
	if ($_REQUEST['favobj'] == 'hat') {
		CProfile::update('web.charts.hats.'.$_REQUEST['favref'].'.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
	if ($_REQUEST['favobj'] == 'timelinefixedperiod') {
		if (isset($_REQUEST['favid'])) {
			CProfile::update('web.screens.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
		}
	}
	if (str_in_array($_REQUEST['favobj'], array('itemid', 'graphid'))) {
		$result = false;
		if ($_REQUEST['favaction'] == 'add') {
			$result = add2favorites('web.favorite.graphids', $_REQUEST['favid'], $_REQUEST['favobj']);
			if ($result) {
				echo '$("addrm_fav").title = "'._('Remove from favourites').'";'."\n";
				echo '$("addrm_fav").onclick = function() { rm4favorites("graphid", "'.$_REQUEST['favid'].'", 0); }'."\n";
			}
		}
		elseif ($_REQUEST['favaction'] == 'remove') {
			$result = rm4favorites('web.favorite.graphids', $_REQUEST['favid'], $_REQUEST['favobj']);

			if ($result) {
				echo '$("addrm_fav").title = "'._('Add to favourites').'";'."\n";
				echo '$("addrm_fav").onclick = function() { add2favorites("graphid", "'.$_REQUEST['favid'].'"); }'."\n";
			}
		}

		if ($page['type'] == PAGE_TYPE_JS && $result) {
			echo 'switchElementsClass("addrm_fav", "iconminus", "iconplus");';
		}
	}
}
if (!empty($_REQUEST['period']) || !empty($_REQUEST['stime'])) {
	CScreenBase::calculateTime(array(
		'profileIdx' => 'web.screens',
		'profileIdx2' => $pageFilter->graphid,
		'updateProfile' => true,
		'period' => get_request('period'),
		'stime' => get_request('stime')
	));

	$curl = new Curl();
	$curl->removeArgument('period');
	$curl->removeArgument('stime');

	ob_end_clean();
	redirect($curl->getUrl());
}

ob_end_flush();

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Display
 */
$data = array(
	'pageFilter' => $pageFilter,
	'graphid' => $pageFilter->graphid,
	'fullscreen' => get_request('fullscreen')
);

// render view
$chartsView = new CView('monitoring.charts', $data);
$chartsView->render();
$chartsView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
