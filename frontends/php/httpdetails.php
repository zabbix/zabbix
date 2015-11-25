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

$page['title'] = _('Details of web scenario');
$page['file'] = 'httpdetails.php';
$page['scripts'] = ['class.calendar.js', 'gtlc.js', 'flickerfreescreen.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'period' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'stime' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'reset' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'httptestid' =>	[T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null],
	'fullscreen' =>	[T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null],
	// ajax
	'favobj' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	'favid' =>		[T_ZBX_INT, O_OPT, P_ACT,	null,		null]
];
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	// saving fixed/dynamic setting to profile
	if ($_REQUEST['favobj'] == 'timelinefixedperiod') {
		if (isset($_REQUEST['favid'])) {
			CProfile::update('web.httptest.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
		}
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Collect data
 */
$httpTest = API::HttpTest()->get([
	'output' => ['httptestid', 'name', 'hostid'],
	'httptestids' => getRequest('httptestid'),
	'preservekeys' => true
]);
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
$details_screen_params = [
	'resourcetype' => SCREEN_RESOURCE_WEBDETAILS,
	'mode' => SCREEN_MODE_JS,
	'dataId' => 'webdetails',
	'profileIdx2' => $httpTest['httptestid'],
];

$widget = (new CWidget())
	->setTitle(
		_('Details of web scenario').': '.
		CMacrosResolverHelper::resolveHttpTestName($httpTest['hostid'], $httpTest['name'])
	)
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())
			->addItem(get_icon('reset', ['id' => getRequest('httptestid')]))
			->addItem(get_icon('fullscreen', ['fullscreen' => $_REQUEST['fullscreen']]))
		)
	);

$details_screen = CScreenBuilder::getScreen($details_screen_params);
$widget->addItem($details_screen->get())->show();
$details_js = (new CScreenBase($details_screen_params))->insertFlickerfreeJs();

echo BR();

// create graphs widget
$graphsWidget = new CWidget();

$filterForm = (new CFilter('web.httpdetails.filter.state'))
	->addNavigator();
$graphsWidget->addItem($filterForm);

$graphs = [];

// dims
$graphDims = getGraphDims();
$graphDims['width'] = -50;
$graphDims['graphHeight'] = 150;

/*
 * Graph in
 */
$graphInScreen = new CScreenBase([
	'resourcetype' => SCREEN_RESOURCE_GRAPH,
	'mode' => SCREEN_MODE_PREVIEW,
	'dataId' => 'graph_in',
	'profileIdx' => 'web.httptest',
	'profileIdx2' => getRequest('httptestid'),
	'period' => getRequest('period'),
	'stime' => getRequest('stime')
]);
$graphInScreen->timeline['starttime'] = date(TIMESTAMP_FORMAT, get_min_itemclock_by_itemid($itemids));

$src = 'chart3.php?height=150'.
	'&name='._('Speed').
	'&http_item_type='.HTTPSTEP_ITEM_TYPE_IN.
	'&httptestid='.$httpTest['httptestid'].
	'&graphtype='.GRAPH_TYPE_STACKED.
	'&period='.$graphInScreen->timeline['period'].
	'&stime='.$graphInScreen->timeline['stime'].
	'&profileIdx='.$graphInScreen->profileIdx.
	'&profileIdx2='.$graphInScreen->profileIdx2;

$graphs[] = (new CDiv(new CLink(null, $src)))
	->addClass('flickerfreescreen')
	->setId('flickerfreescreen_graph_in')
	->setAttribute('data-timestamp', time());

$timeControlData = [
	'id' => 'graph_in',
	'containerid' => 'flickerfreescreen_graph_in',
	'src' => $src,
	'objDims' => $graphDims,
	'loadSBox' => 1,
	'loadImage' => 1,
	'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1),
	'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
];
zbx_add_post_js('timeControl.addObject("graph_in", '.zbx_jsvalue($graphInScreen->timeline).', '.zbx_jsvalue($timeControlData).');');
$graphInScreen->insertFlickerfreeJs();

/*
 * Graph time
 */
$graphTimeScreen = new CScreenBase([
	'resourcetype' => SCREEN_RESOURCE_GRAPH,
	'mode' => SCREEN_MODE_PREVIEW,
	'dataId' => 'graph_time',
	'profileIdx' => 'web.httptest',
	'profileIdx2' => getRequest('httptestid'),
	'period' => getRequest('period'),
	'stime' => getRequest('stime')
]);

$src = 'chart3.php?height=150'.
	'&name='._('Response time').
	'&http_item_type='.HTTPSTEP_ITEM_TYPE_TIME.
	'&httptestid='.$httpTest['httptestid'].
	'&graphtype='.GRAPH_TYPE_STACKED.
	'&period='.$graphTimeScreen->timeline['period'].
	'&stime='.$graphTimeScreen->timeline['stime'].
	'&profileIdx='.$graphTimeScreen->profileIdx.
	'&profileIdx2='.$graphTimeScreen->profileIdx2;

$graphs[] = (new CDiv(new CLink(null, $src)))
	->addClass('flickerfreescreen')
	->setId('flickerfreescreen_graph_time')
	->setAttribute('data-timestamp', time());

$timeControlData = [
	'id' => 'graph_time',
	'containerid' => 'flickerfreescreen_graph_time',
	'src' => $src,
	'objDims' => $graphDims,
	'loadSBox' => 1,
	'loadImage' => 1,
	'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1),
	'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
];
zbx_add_post_js('timeControl.addObject("graph_time", '.zbx_jsvalue($graphInScreen->timeline).', '.zbx_jsvalue($timeControlData).');');
$graphTimeScreen->insertFlickerfreeJs();

// scroll
CScreenBuilder::insertScreenStandardJs(['timeline' => $graphInScreen->timeline]);

$graphsWidget
	->addItem((new CDiv($graphs))->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER))
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';
