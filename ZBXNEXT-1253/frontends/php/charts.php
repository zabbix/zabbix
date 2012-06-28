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
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/graphs.inc.php';

$page['title'] = _('Custom graphs');
$page['file'] = 'charts.php';
$page['hist_arg'] = array('hostid', 'groupid', 'graphid');
$page['scripts'] = array('class.calendar.js', 'gtlc.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

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
	if ($_REQUEST['favobj'] == 'timeline') {
		if (isset($_REQUEST['graphid']) && isset($_REQUEST['period'])) {
			navigation_bar_calc('web.graph', $_REQUEST['favid'], true);
		}
	}

	// saving fixed/dynamic setting to profile
	if ($_REQUEST['favobj'] == 'timelinefixedperiod') {
		if (isset($_REQUEST['favid'])) {
			CProfile::update('web.charts.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
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

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$pageFilter = new CPageFilter(array(
	'groups' => array('monitored_hosts' => true, 'with_graphs' => true),
	'hosts' => array('monitored_hosts' => true, 'with_graphs' => true),
	'groupid' => get_request('groupid', null),
	'hostid' => get_request('hostid', null),
	'graphs' => array('templated' => 0),
	'graphid' => get_request('graphid', null),
));
$_REQUEST['graphid'] = $pageFilter->graphid;

// resets get params for proper page refresh
if (isset($_REQUEST['period']) || isset($_REQUEST['stime'])) {
	navigation_bar_calc('web.graph', $_REQUEST['graphid'], true);
	jsRedirect('charts.php?graphid='.$_REQUEST['graphid']);

	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Display
 */
$chartWidget = new CWidget('hat_charts');
$chartTable = new CTableInfo(_('No charts defined.'), 'chart');

$icons = array();
if ($pageFilter->graphsSelected) {
	$chartWidget->addFlicker(new CDiv(null, null, 'scrollbar_cntr'), CProfile::get('web.charts.filter.state', 1));

	$icons[] = get_icon('favourite', array('fav' => 'web.favorite.graphids', 'elname' => 'graphid', 'elid' => $_REQUEST['graphid']));
	$icons[] = SPACE;
	$icons[] = get_icon('reset', array('id' => $_REQUEST['graphid']));
	$icons[] = SPACE;
	$icons[] = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));

	$effectiveperiod = navigation_bar_calc('web.graph', $_REQUEST['graphid']);

	$chartForm = new CForm('get');
	$chartForm->addVar('fullscreen', $_REQUEST['fullscreen']);
	$chartForm->addItem(array(_('Group').SPACE, $pageFilter->getGroupsCB(true)));
	$chartForm->addItem(array(SPACE._('Host').SPACE, $pageFilter->getHostsCB(true)));
	$chartForm->addItem(array(SPACE._('Graph').SPACE, $pageFilter->getGraphsCB(true)));

	$domGraphId = 'graph';
	$graphDims = getGraphDims($_REQUEST['graphid']);

	if ($graphDims['graphtype'] == GRAPH_TYPE_PIE || $graphDims['graphtype'] == GRAPH_TYPE_EXPLODED) {
		$loadSBox = 0;
		$scrollWidthByImage = 0;
		$containerid = 'graph_cont1';
		$src = 'chart6.php?graphid='.$_REQUEST['graphid'];
	}
	else {
		$loadSBox = 1;
		$scrollWidthByImage = 1;
		$containerid = 'graph_cont1';
		$src = 'chart2.php?graphid='.$_REQUEST['graphid'];
	}

	$graphContainer = new CCol();
	$graphContainer->setAttribute('id', $containerid);
	$chartTable->addRow($graphContainer);

	$utime = zbxDateToTime($_REQUEST['stime']);
	$starttime = get_min_itemclock_by_graphid($_REQUEST['graphid']);
	if ($utime < $starttime) {
		$starttime = $utime;
	}

	$timeline = array(
		'starttime' => date('YmdHis', $starttime),
		'period' => $effectiveperiod,
		'usertime' => date('YmdHis', $utime + $effectiveperiod)
	);

	$timeControlData = array(
		'id' => $_REQUEST['graphid'],
		'domid' => $domGraphId,
		'containerid' => $containerid,
		'src' => $src,
		'objDims' => $graphDims,
		'loadSBox' => $loadSBox,
		'loadImage' => 1,
		'loadScroll' => 1,
		'scrollWidthByImage' => $scrollWidthByImage,
		'dynamic' => 1,
		'periodFixed' => CProfile::get('web.charts.timelinefixed', 1),
		'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
	);

	zbx_add_post_js('timeControl.addObject("'.$domGraphId.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($timeControlData).');');
	zbx_add_post_js('timeControl.processObjects();');
}

$chartWidget->addPageHeader(_('Graphs'), $icons);
$chartWidget->addHeader($pageFilter->graphs[$pageFilter->graphid], $chartForm);
$chartWidget->addItem(BR());
$chartWidget->addItem($chartTable);
$chartWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';
