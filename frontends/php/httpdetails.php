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
	'httptestids' => getRequest('httptestid'),
	'output' => API_OUTPUT_EXTEND,
	'preservekeys' => true
]);
$httpTest = reset($httpTest);
if (!$httpTest) {
	access_deny();
}

$httpTest['lastfailedstep'] = 0;
$httpTest['error'] = '';

// fetch http test execution data
$httpTestData = Manager::HttpTest()->getLastData([$httpTest['httptestid']]);

if ($httpTestData) {
	$httpTestData = reset($httpTestData);
}

// fetch HTTP step items
$query = DBselect(
	'SELECT i.value_type,i.valuemapid,i.units,i.itemid,hi.type AS httpitem_type,hs.httpstepid'.
	' FROM items i,httpstepitem hi,httpstep hs'.
	' WHERE hi.itemid=i.itemid'.
		' AND hi.httpstepid=hs.httpstepid'.
		' AND hs.httptestid='.zbx_dbstr($httpTest['httptestid'])
);
$httpStepItems = [];
$items = [];
while ($item = DBfetch($query)) {
	$items[] = $item;
	$httpStepItems[$item['httpstepid']][$item['httpitem_type']] = $item;
}

// fetch HTTP item history
$itemHistory = Manager::History()->getLast($items);

/*
 * Display
 */
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

// append table to widget
$httpdetailsTable = (new CTableInfo())
	->setHeader([
		_('Step'),
		_('Speed'),
		_('Response time'),
		_('Response code'),
		_('Status')
	]);

$db_httpsteps = DBselect('SELECT * FROM httpstep WHERE httptestid='.zbx_dbstr($httpTest['httptestid']).' ORDER BY no');

$totalTime = [
	'value' => 0,
	'value_type' => null,
	'valuemapid' => null,
	'units' => null
];

$itemIds = [];
while ($httpstep_data = DBfetch($db_httpsteps)) {
	$httpStepItemsByType = $httpStepItems[$httpstep_data['httpstepid']];

	$status['msg'] = _('OK');
	$status['style'] = ZBX_STYLE_GREEN;
	$status['afterError'] = false;

	if (!isset($httpTestData['lastfailedstep'])) {
		$status['msg'] = _('Never executed');
		$status['style'] = ZBX_STYLE_GREY;
	}
	elseif ($httpTestData['lastfailedstep'] != 0) {
		if ($httpTestData['lastfailedstep'] == $httpstep_data['no']) {
			$status['msg'] = ($httpTestData['error'] === null)
				? _('Unknown error')
				: _s('Error: %1$s', $httpTestData['error']);
			$status['style'] = ZBX_STYLE_RED;
		}
		elseif ($httpTestData['lastfailedstep'] < $httpstep_data['no']) {
			$status['msg'] = _('Unknown');
			$status['style'] = ZBX_STYLE_GREY;
			$status['afterError'] = true;
		}
	}

	foreach ($httpStepItemsByType as &$httpStepItem) {
		// calculate the total time it took to execute the scenario
		// skip steps that come after a failed step
		if (!$status['afterError'] && $httpStepItem['httpitem_type'] == HTTPSTEP_ITEM_TYPE_TIME) {
			$totalTime['value_type'] = $httpStepItem['value_type'];
			$totalTime['valuemapid'] = $httpStepItem['valuemapid'];
			$totalTime['units'] = $httpStepItem['units'];

			if (isset($itemHistory[$httpStepItem['itemid']])) {
				$history = $itemHistory[$httpStepItem['itemid']][0];

				$totalTime['value'] += $history['value'];
			}
		}

		$itemIds[] = $httpStepItem['itemid'];
	}
	unset($httpStepItem);

	// step speed
	$speedItem = $httpStepItemsByType[HTTPSTEP_ITEM_TYPE_IN];
	if (!$status['afterError'] && isset($itemHistory[$speedItem['itemid']]) && $itemHistory[$speedItem['itemid']][0]['value'] > 0) {
		$speed = formatHistoryValue($itemHistory[$speedItem['itemid']][0]['value'], $speedItem);
	}
	else {
		$speed = UNKNOWN_VALUE;
	}

	// step response time
	$respTimeItem = $httpStepItemsByType[HTTPSTEP_ITEM_TYPE_TIME];
	if (!$status['afterError'] && isset($itemHistory[$respTimeItem['itemid']]) && $itemHistory[$respTimeItem['itemid']][0]['value'] > 0) {
		$respTime = formatHistoryValue($itemHistory[$respTimeItem['itemid']][0]['value'], $respTimeItem);
	}
	else {
		$respTime = UNKNOWN_VALUE;
	}

	// step response code
	$respItem = $httpStepItemsByType[HTTPSTEP_ITEM_TYPE_RSPCODE];
	if (!$status['afterError'] && isset($itemHistory[$respItem['itemid']]) && $itemHistory[$respItem['itemid']][0]['value'] > 0) {
		$resp = formatHistoryValue($itemHistory[$respItem['itemid']][0]['value'], $respItem);
	}
	else {
		$resp = UNKNOWN_VALUE;
	}

	$httpdetailsTable->addRow([
		CMacrosResolverHelper::resolveHttpTestName($httpTest['hostid'], $httpstep_data['name']),
		$speed,
		$respTime,
		$resp,
		(new CSpan($status['msg']))->addClass($status['style'])
	]);
}

if (!isset($httpTestData['lastfailedstep'])) {
	$status['msg'] = _('Never executed');
	$status['style'] = ZBX_STYLE_GREY;
}
elseif ($httpTestData['lastfailedstep'] != 0) {
	$status['msg'] = ($httpTestData['error'] === null)
		? _('Unknown error')
		: _s('Error: %1$s', $httpTestData['error']);
	$status['style'] = ZBX_STYLE_RED;
}
else {
	$status['msg'] = _('OK');
	$status['style'] = ZBX_STYLE_GREEN;
}

$httpdetailsTable->addRow([
	bold(_('TOTAL')),
	SPACE,
	bold(($totalTime['value']) ? formatHistoryValue($totalTime['value'], $totalTime) : UNKNOWN_VALUE),
	SPACE,
	(new CSpan($status['msg']))->addClass($status['style'])->addClass('bold')
]);

$widget->addItem($httpdetailsTable)->show();

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
$graphInScreen->timeline['starttime'] = date(TIMESTAMP_FORMAT, get_min_itemclock_by_itemid($itemIds));

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
CScreenBuilder::insertScreenScrollJs(['timeline' => $graphInScreen->timeline]);
CScreenBuilder::insertScreenRefreshTimeJs();
CScreenBuilder::insertProcessObjectsJs();

$graphsWidget
	->addItem((new CDiv($graphs))->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER))
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';
