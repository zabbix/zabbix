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
	'httptestid' =>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		'isset({favobj})'),
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

$result = DBselect(
	'SELECT hti.httptestid,hti.type,i.lastvalue,i.lastclock'.
	' FROM httptestitem hti,items i'.
	' WHERE hti.itemid=i.itemid'.
		' AND hti.type IN ('.HTTPSTEP_ITEM_TYPE_LASTSTEP.','.HTTPSTEP_ITEM_TYPE_LASTERROR.')'.
		' AND i.lastclock IS NOT NULL'.
		' AND hti.httptestid='.$httpTest['httptestid']
);
while ($row = DBfetch($result)) {
	if ($row['type'] == HTTPSTEP_ITEM_TYPE_LASTSTEP) {
		$httpTest['lastcheck'] = $row['lastclock'];
		$httpTest['lastfailedstep'] = $row['lastvalue'];
	}
	else {
		$httpTest['error'] = $row['lastvalue'];
	}
}

/*
 * Display
 */
$httpdetailsWidget = new CWidget();

$lastcheck = null;
if (isset($httpTest['lastcheck'])) {
	$lastcheck = ' ['.zbx_date2str(_('d M Y H:i:s'), $httpTest['lastcheck']).']';
}

$httpdetailsWidget->addPageHeader(
	array(_('DETAILS OF SCENARIO').SPACE, bold(CMacrosResolverHelper::resolveHttpTestName($httpTest['hostid'], $httpTest['name'])), $lastcheck),
	array(
		get_icon('reset', array('id' => get_request('httptestid'))),
		get_icon('fullscreen', array('fullscreen' => get_request('fullscreen')))
	)
);

// append table to widget
$httpdetailsTable = new CTableInfo(_('No steps defined.'));
$httpdetailsTable->setHeader(array(
	_('Step'),
	_('Speed'),
	_('Response time'),
	_('Response code'),
	_('Status')
));

$db_httpsteps = DBselect('SELECT * FROM httpstep WHERE httptestid='.$httpTest['httptestid'].' ORDER BY no');

$totalTime = array(
	'lastvalue' => 0,
	'value_type' => null,
	'valuemapid' => null,
	'units' => null
);


while ($httpstep_data = DBfetch($db_httpsteps)) {
	$status['msg'] = _('OK');
	$status['style'] = 'enabled';

	if (!isset($httpTest['lastcheck'])) {
		$status['msg'] = _('Never executed');
		$status['style'] = 'unknown';
	}
	elseif ($httpTest['lastfailedstep'] != 0) {
		if ($httpTest['lastfailedstep'] == $httpstep_data['no']) {
			$status['msg'] = _s('Error: %1$s', $httpTest['error']);
			$status['style'] = 'disabled';
		}
		elseif ($httpTest['lastfailedstep'] < $httpstep_data['no']) {
			$status['msg'] = _('Unknown');
			$status['style'] = 'unknown';
			$status['skip'] = true;
		}
	}

	$itemIds = array();
	$db_items = DBselect(
		'SELECT i.lastvalue,i.lastclock,i.value_type,i.valuemapid,i.units,i.itemid,hi.type AS httpitem_type'.
			' FROM items i,httpstepitem hi'.
			' WHERE hi.itemid=i.itemid'.
				' AND hi.httpstepid='.$httpstep_data['httpstepid']
	);
	while ($item_data = DBfetch($db_items)) {
		if (isset($status['skip'])) {
			$item_data['lastvalue'] = null;
		}

		$httpstep_data['item_data'][$item_data['httpitem_type']] = $item_data;

		if ($item_data['httpitem_type'] == HTTPSTEP_ITEM_TYPE_TIME) {
			$totalTime['lastvalue'] += $item_data['lastvalue'];
			$totalTime['lastclock'] = $item_data['lastclock'];
			$totalTime['value_type'] = $item_data['value_type'];
			$totalTime['valuemapid'] = $item_data['valuemapid'];
			$totalTime['units'] = $item_data['units'];
		}

		$itemIds[] = $item_data['itemid'];
	}

	$speed = formatItemValue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_IN]);
	$resp = formatItemValue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_RSPCODE]);
	$respTime = $httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_TIME]['lastvalue'];
	$respItemTime = formatItemValue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_TIME]);

	$httpdetailsTable->addRow(array(
		CMacrosResolverHelper::resolveHttpTestName($httpTest['hostid'], $httpstep_data['name']),
		$speed,
		($respTime == 0 ? '-' : $respItemTime),
		$resp,
		new CSpan($status['msg'], $status['style'])
	));
}

if (!isset($httpTest['lastcheck'])) {
	$status['msg'] = _('Never executed');
	$status['style'] = 'unknown';
}
elseif ($httpTest['lastfailedstep'] != 0) {
	$status['msg'] = _s('Error: %1$s', $httpTest['error']);
	$status['style'] = 'disabled';
}
else {
	$status['msg'] = _('OK');
	$status['style'] = 'enabled';
}

$httpdetailsTable->addRow(array(
	bold(_('TOTAL')),
	SPACE,
	bold(formatItemValue($totalTime)),
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
