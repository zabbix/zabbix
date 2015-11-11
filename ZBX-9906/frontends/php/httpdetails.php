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
$page['scripts'] = array('class.calendar.js', 'gtlc.js', 'flickerfreescreen.js');
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
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	null)
);
check_fields($fields);

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
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Collect data
 */
$httpTest = API::HttpTest()->get(array(
	'httptestids' => getRequest('httptestid'),
	'output' => array('httptestid', 'hostid', 'name'),
	'selectSteps' => array('httpstepid', 'name', 'no'),
	'preservekeys' => true
));
$httpTest = reset($httpTest);
if (!$httpTest) {
	access_deny();
}

$httptest_manager = new CHttpTestManager();
$itemids = $httptest_manager->getHttpStepItems($httpTest['httptestid']);
$itemids = zbx_objectValues($itemids, 'itemid');

/*
 * Display
 */
$details_screen_params = array(
	'resourcetype' => SCREEN_RESOURCE_WEBDETAILS,
	'mode' => SCREEN_MODE_JS,
	'dataId' => 'webdetails',
	'profileIdx2' => $httpTest['httptestid'],
);

$widget = new CWidget();
$widget->addPageHeader(
	new CSpan(
		array(_('DETAILS OF SCENARIO'), SPACE,
			bold(CMacrosResolverHelper::resolveHttpTestName($httpTest['hostid'], $httpTest['name']))
		),
		null,
		'hat_webdetails_header'
	),
	array(
		get_icon('reset', array('id' => getRequest('httptestid'))),
		get_icon('fullscreen', array('fullscreen' => getRequest('fullscreen')))
	)
);
$details_screen = CScreenBuilder::getScreen($details_screen_params);
$widget->addItem($details_screen->get());
$widget->show();

$details_js = new CScreenBase($details_screen_params);
$details_js->insertFlickerfreeJs();

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

$graphInScreen->timeline['starttime'] = date(TIMESTAMP_FORMAT, get_min_itemclock_by_itemid($itemids));

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
CScreenBuilder::insertScreenRefreshTimeJs();
CScreenBuilder::insertProcessObjectsJs();

$graphsWidget->addItem($graphTable);
$graphsWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';
