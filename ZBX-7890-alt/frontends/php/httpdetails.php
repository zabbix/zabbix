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
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/httptest.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

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
	'filterState' => array(T_ZBX_INT, O_OPT, P_ACT, null,		null),
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favid' =>		array(T_ZBX_INT, O_OPT, P_ACT,	null,		null)
);
check_fields($fields);

/*
 * Ajax
 */
if (hasRequest('filterState')) {
	CProfile::update('web.httpdetails.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}
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
$httpTest = API::HttpTest()->get(array(
	'httptestids' => get_request('httptestid'),
	'output' => API_OUTPUT_EXTEND,
	'preservekeys' => true
));
$httpTest = reset($httpTest);
if (!$httpTest) {
	access_deny();
}

$httpTest['lastfailedstep'] = 0;
$httpTest['error'] = '';

// fetch http test execution data
$httpTestData = Manager::HttpTest()->getLastData(array($httpTest['httptestid']));

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
$httpStepItems = array();
$items = array();
while ($item = DBfetch($query)) {
	$items[] = $item;
	$httpStepItems[$item['httpstepid']][$item['httpitem_type']] = $item;
}

// fetch HTTP item history
$itemHistory = Manager::History()->getLast($items);

/*
 * Display
 */
$httpdetailsWidget = new CWidget();
$httpdetailsWidget->addPageHeader(
	array(
		_('DETAILS OF SCENARIO'),
		SPACE,
		bold(CMacrosResolverHelper::resolveHttpTestName($httpTest['hostid'], $httpTest['name'])),
		isset($httpTestData['lastcheck']) ? ' ['.zbx_date2str(DATE_TIME_FORMAT_SECONDS, $httpTestData['lastcheck']).']' : null
	),
	array(
		get_icon('reset', array('id' => get_request('httptestid'))),
		get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']))
	)
);

// append table to widget
$httpdetailsTable = new CTableInfo();
$httpdetailsTable->setHeader(array(
	_('Step'),
	_('Speed'),
	_('Response time'),
	_('Response code'),
	_('Status')
));

$db_httpsteps = DBselect('SELECT * FROM httpstep WHERE httptestid='.zbx_dbstr($httpTest['httptestid']).' ORDER BY no');

$totalTime = array(
	'value' => 0,
	'value_type' => null,
	'valuemapid' => null,
	'units' => null
);

while ($httpstep_data = DBfetch($db_httpsteps)) {
	$httpStepItemsByType = $httpStepItems[$httpstep_data['httpstepid']];

	$status['msg'] = _('OK');
	$status['style'] = 'enabled';
	$status['afterError'] = false;

	if (!isset($httpTestData['lastfailedstep'])) {
		$status['msg'] = _('Never executed');
		$status['style'] = 'unknown';
	}
	elseif ($httpTestData['lastfailedstep'] != 0) {
		if ($httpTestData['lastfailedstep'] == $httpstep_data['no']) {
			$status['msg'] = ($httpTestData['error'] === null)
				? _('Unknown error')
				: _s('Error: %1$s', $httpTestData['error']);
			$status['style'] = 'disabled';
		}
		elseif ($httpTestData['lastfailedstep'] < $httpstep_data['no']) {
			$status['msg'] = _('Unknown');
			$status['style'] = 'unknown';
			$status['afterError'] = true;
		}
	}

	$itemIds = array();
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

	$httpdetailsTable->addRow(array(
		CMacrosResolverHelper::resolveHttpTestName($httpTest['hostid'], $httpstep_data['name']),
		$speed,
		$respTime,
		$resp,
		new CSpan($status['msg'], $status['style'])
	));
}

if (!isset($httpTestData['lastfailedstep'])) {
	$status['msg'] = _('Never executed');
	$status['style'] = 'unknown';
}
elseif ($httpTestData['lastfailedstep'] != 0) {
	$status['msg'] = ($httpTestData['error'] === null)
		? _('Unknown error')
		: _s('Error: %1$s', $httpTestData['error']);
	$status['style'] = 'disabled';
}
else {
	$status['msg'] = _('OK');
	$status['style'] = 'enabled';
}

$httpdetailsTable->addRow(array(
	bold(_('TOTAL')),
	SPACE,
	bold(($totalTime['value']) ? formatHistoryValue($totalTime['value'], $totalTime) : UNKNOWN_VALUE),
	SPACE,
	new CSpan($status['msg'], $status['style'].' bold')
));

$httpdetailsWidget->addItem($httpdetailsTable);
$httpdetailsWidget->show();

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
	'profileIdx2' => get_request('httptestid'),
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
	'profileIdx2' => get_request('httptestid'),
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
