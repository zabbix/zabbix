<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
require_once dirname(__FILE__).'/include/httptest.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/blocks.inc.php';

$page['title'] = _('Details of scenario');
$page['file'] = 'httpdetails.php';
$page['hist_arg'] = array('httptestid');
$page['scripts'] = array('class.calendar.js', 'gtlc.js', 'flickerfreescreen.js','class.pmaster.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'period' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'stime' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'reset' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'httptestid' =>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	null),
	'favid' =>		array(T_ZBX_INT, O_OPT, P_ACT,	null,		null),
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	null),
	'favcnt' =>		array(T_ZBX_INT, O_OPT, P_ACT,	null,		null)
);

if (!array_key_exists('favobj', $_REQUEST)
	|| ($_REQUEST['favobj'] != 'set_rf_rate')
	|| ($page['type'] != PAGE_TYPE_JS && $page['type'] != PAGE_TYPE_HTML_BLOCK)) {
	check_fields($fields);
}

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.httpdetails.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}

	// saving fixed/dynamic setting to profile
	if ($_REQUEST['favobj'] == 'timelinefixedperiod') {
		if (isset($_REQUEST['favid'])) {
			CProfile::update('web.httptest.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
		}
	}

	if ($_REQUEST['favobj'] == 'hat') {
		$details = make_webdetails(get_request('httptestid'));
		$details->show();
	}

	if ($_REQUEST['favobj'] == 'set_rf_rate') {
		CProfile::update('web.httpdetails.widget.webdetails.rf_rate', $_REQUEST['favcnt'], PROFILE_TYPE_INT);
		$_REQUEST['favcnt'] = CProfile::get('web.httpdetails.widget.webdetails.rf_rate', 60);

		echo get_update_doll_script('mainpage', $_REQUEST['favref'], 'frequency', $_REQUEST['favcnt'])
		.get_update_doll_script('mainpage', $_REQUEST['favref'], 'stopDoll')
		.get_update_doll_script('mainpage', $_REQUEST['favref'], 'startDoll');

		$menu = array();
		$submenu = array();
		make_refresh_menu('mainpage', $_REQUEST['favref'], $_REQUEST['favcnt'], null, $menu, $submenu);
		echo 'page_menu["menu_'.$_REQUEST['favref'].'"] = '.zbx_jsvalue($menu['menu_'.$_REQUEST['favref']]).';';
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Collect data
 */
$httpTest = API::HttpTest()->get(array(
	'httptestids' => get_request('httptestid'),
	'output' => array('httptestid', 'name'),
	'selectSteps' => array('httpstepid', 'name', 'no'),
	'preservekeys' => true
));
$httpTest = reset($httpTest);
if (!$httpTest) {
	access_deny();
}
$itemIds = get_httpstepitems_by_httptestid($httpTest['httptestid'], array('itemid'));
$itemIds = zbx_objectValues($itemIds, 'itemid');

/*
 * Display
 */
$menu = array();
$submenu = array();
// append web widget
make_refresh_menu('mainpage', 'hat_webdetails', CProfile::get('web.httpdetails.widget.webdetails.rf_rate', 60), null, $menu, $submenu);
insert_js('var page_menu='.zbx_jsvalue($menu).";\n".'var page_submenu='.zbx_jsvalue($submenu).";\n");

$refresh_menu = get_icon('menu', array('menu' => 'hat_webdetails'));
$httpdetails = new CUIWidget('hat_webdetails', new CSpan(_('Loading...'), 'textcolorstyles'), 1);
$httpdetails->setHeader(new CDiv(_('DETAILS OF SCENARIO'), 'textwhite', 'hat_webdetails_header'),
	array(
		get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen'])),
		$refresh_menu
	)
);
$httpdetails->show();

// refresh tab
$refresh_tab = array(
	array(
		'id' => 'hat_webdetails',
		'frequency' => CProfile::get('web.httpdetails.widget.webdetails.rf_rate', 60),
		'params' => array('httptestid' => $httpTest['httptestid'])
	)
);
add_doll_objects($refresh_tab);

echo SBR;

// create graphs widget
$graphsWidget = new CWidget();
$graphsWidget->addFlicker(new CDiv(null, null, 'scrollbar_cntr'), CProfile::get('web.httpdetails.filter.state', 0));
$graphsWidget->addItem(SPACE);

$graphTable = new CTableInfo();
$graphTable->setAttribute('id', 'graph');

// dims
$graphDims = getGraphDims();
$graphDims['shiftYtop'] += 1;
$graphDims['width'] = -120;
$graphDims['graphHeight'] = 150;

/*
 * Graph in
 */
$graphInScreen = new CScreenBase(array(
	'resourcetype' => SCREEN_RESOURCE_GRAPH,
	'mode' => SCREEN_MODE_PREVIEW,
	'dataId' => 'graph_in',
	'profileIdx' => 'web.httptest',
	'profileIdx2' => $httpTest['httptestid'],
	'period' => get_request('period'),
	'stime' => get_request('stime')
));

$graphInScreen->timeline['starttime'] = date(TIMESTAMP_FORMAT, get_min_itemclock_by_itemid($itemIds));

$src = 'chart3.php?height=150'.
	'&name='.$httpTest['name'].
	'&http_item_type='.HTTPSTEP_ITEM_TYPE_IN.
	'&httptestid='.$httpTest['httptestid'].
	'&graphtype='.GRAPH_TYPE_STACKED.
	'&period='.$graphInScreen->timeline['period'].
	'&stime='.$graphInScreen->timeline['stime'].
	'&profileIdx='.$graphInScreen->profileIdx.
	'&profileIdx2='.$graphInScreen->profileIdx2;

$graphInContainer = new CDiv(new CLink(null, $src), 'flickerfreescreen', 'flickerfreescreen_graph_in');
$graphInContainer->setAttribute('style', 'position: relative');
$graphInContainer->setAttribute('data-timestamp', time());
$graphTable->addRow(array(bold(_('Speed')), $graphInContainer));

$timeControlData = array(
	'id' => 'graph_in',
	'containerid' => 'flickerfreescreen_graph_in',
	'src' => $src,
	'objDims' => $graphDims,
	'loadSBox' => 1,
	'loadImage' => 1,
	'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1),
	'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
);
zbx_add_post_js('timeControl.addObject("graph_in", '.zbx_jsvalue($graphInScreen->timeline).', '.zbx_jsvalue($timeControlData).');');
$graphInScreen->insertFlickerfreeJs();

/*
 * Graph time
 */
$graphTimeScreen = new CScreenBase(array(
	'resourcetype' => SCREEN_RESOURCE_GRAPH,
	'mode' => SCREEN_MODE_PREVIEW,
	'dataId' => 'graph_time',
	'profileIdx' => 'web.httptest',
	'profileIdx2' => $httpTest['httptestid'],
	'period' => get_request('period'),
	'stime' => get_request('stime')
));

$src = 'chart3.php?height=150'.
	'&name='.$httpTest['name'].
	'&http_item_type='.HTTPSTEP_ITEM_TYPE_TIME.
	'&httptestid='.$httpTest['httptestid'].
	'&graphtype='.GRAPH_TYPE_STACKED.
	'&period='.$graphTimeScreen->timeline['period'].
	'&stime='.$graphTimeScreen->timeline['stime'].
	'&profileIdx='.$graphTimeScreen->profileIdx.
	'&profileIdx2='.$graphTimeScreen->profileIdx2;

$graphTimeContainer = new CDiv(new CLink(null, $src), 'flickerfreescreen', 'flickerfreescreen_graph_time');
$graphTimeContainer->setAttribute('style', 'position: relative');
$graphTimeContainer->setAttribute('data-timestamp', time());
$graphTable->addRow(array(bold(_('Response time')), $graphTimeContainer));

$timeControlData = array(
	'id' => 'graph_time',
	'containerid' => 'flickerfreescreen_graph_time',
	'src' => $src,
	'objDims' => $graphDims,
	'loadSBox' => 1,
	'loadImage' => 1,
	'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1),
	'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
);
zbx_add_post_js('timeControl.addObject("graph_time", '.zbx_jsvalue($graphInScreen->timeline).', '.zbx_jsvalue($timeControlData).');');
$graphTimeScreen->insertFlickerfreeJs();

// scroll
CScreenBuilder::insertScreenScrollJs(array('timeline' => $graphInScreen->timeline));
CScreenBuilder::insertProcessObjectsJs();

$graphsWidget->addItem($graphTable);
$graphsWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';
