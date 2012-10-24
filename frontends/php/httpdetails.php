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
require_once dirname(__FILE__).'/include/httptest.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Details of scenario');
$page['file'] = 'httpdetails.php';
$page['hist_arg'] = array('httptestid');
$page['scripts'] = array('class.calendar.js', 'gtlc.js', 'flickerfreescreen.js');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'period' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'stime' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'reset' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'httptestid' =>	array(T_ZBX_INT, O_MAND, null,	DB_ID,		'isset({favobj})'),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	null),
	'favid' =>		array(T_ZBX_INT, O_OPT, P_ACT,	null,		null),
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	null)
);
if (!check_fields($fields)) {
	exit();
}

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.httpdetails.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
	if ($_REQUEST['favobj'] == 'timeline') {
		if (isset($_REQUEST['favid']) && isset($_REQUEST['period'])) {
			navigation_bar_calc('web.httptest', $_REQUEST['favid'], true);
		}
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
$db_httptest = API::HttpTest()->get(array(
	'httptestids' => $_REQUEST['httptestid'],
	'output' => API_OUTPUT_EXTEND,
	'preservekeys' => true
));
$db_httptest = reset($db_httptest);
if (!$db_httptest) {
	access_deny();
}

$db_httptest['lastfailedstep'] = 0;
$db_httptest['error'] = '';

$result = DBselect(
	'SELECT hti.httptestid,hti.type,i.lastvalue,i.lastclock'.
	' FROM httptestitem hti,items i'.
	' WHERE hti.itemid=i.itemid'.
		' AND hti.type IN ('.HTTPSTEP_ITEM_TYPE_LASTSTEP.','.HTTPSTEP_ITEM_TYPE_LASTERROR.')'.
		' AND i.lastclock IS NOT NULL'.
		' AND hti.httptestid='.$db_httptest['httptestid']
);
while ($row = DBfetch($result)) {
	if ($row['type'] == HTTPSTEP_ITEM_TYPE_LASTSTEP) {
		$db_httptest['lastcheck'] = $row['lastclock'];
		$db_httptest['lastfailedstep'] = $row['lastvalue'];
	}
	else {
		$db_httptest['error'] = $row['lastvalue'];
	}
}

/*
 * Display
 */
$httpdetailsWidget = new CWidget();

$lastcheck = null;
if (isset($db_httptest['lastcheck'])) {
	$lastcheck = ' ['.zbx_date2str(_('d M Y H:i:s'), $db_httptest['lastcheck']).']';
}

$httpdetailsWidget->addPageHeader(
	array(_('DETAILS OF SCENARIO').SPACE, bold($db_httptest['name']), $lastcheck),
	array(
		get_icon('reset', array('id' => $_REQUEST['httptestid'])),
		get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']))
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

$db_httpsteps = DBselect('SELECT * FROM httpstep WHERE httptestid='.$db_httptest['httptestid'].' ORDER BY no');

$totalTime = array(
	'lastvalue' => 0,
	'value_type' => null,
	'valuemapid' => null,
	'units' => null
);

while ($httpstep_data = DBfetch($db_httpsteps)) {
	$status['msg'] = _('OK');
	$status['style'] = 'enabled';

	if (!isset($db_httptest['lastcheck'])) {
		$status['msg'] = _('Never executed');
		$status['style'] = 'unknown';
	}
	elseif ($db_httptest['lastfailedstep'] != 0) {
		if ($db_httptest['lastfailedstep'] == $httpstep_data['no']) {
			$status['msg'] = _s('Error: %1$s', $db_httptest['error']);
			$status['style'] = 'disabled';
		}
		elseif ($db_httptest['lastfailedstep'] < $httpstep_data['no']) {
			$status['msg'] = _('Unknown');
			$status['style'] = 'unknown';
			$status['skip'] = true;
		}
	}

	$itemids = array();
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

		$itemids[] = $item_data['itemid'];
	}

	$speed = formatItemValue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_IN]);
	$resp = formatItemValue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_RSPCODE]);
	$respTime = $httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_TIME]['lastvalue'];
	$respItemTime = formatItemValue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_TIME]);

	$httpdetailsTable->addRow(array(
		$httpstep_data['name'],
		$speed,
		($respTime == 0 ? '-' : $respItemTime),
		$resp,
		new CSpan($status['msg'], $status['style'])
	));
}

if (!isset($db_httptest['lastcheck'])) {
	$status['msg'] = _('Never executed');
	$status['style'] = 'unknown';
}
elseif ($db_httptest['lastfailedstep'] != 0) {
	$status['msg'] = _s('Error: %1$s', $db_httptest['error']);
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

$graphContainer = new CCol();
$graphContainer->setAttribute('id', 'graph_1');
$graphTable->addRow(array(bold(_('Speed')), $graphContainer));

$graphContainer = new CCol();
$graphContainer->setAttribute('id', 'graph_2');
$graphTable->addRow(array(bold(_('Response time')), $graphContainer));

$graphsWidget->addItem($graphTable);

// timeline
$timeline = CScreenBase::calculateTime(array(
	'profileIdx' => 'web.httptest',
	'profileIdx2' => get_request('httptestid'),
	'period' => get_request('period'),
	'stime' => get_request('stime')
));
$timeline['starttime'] = date('YmdHis', get_min_itemclock_by_itemid($itemids));

$graphDims = getGraphDims();
$graphDims['shiftYtop'] += 1;
$graphDims['width'] = -120;
$graphDims['graphHeight'] = 150;

// graph 1
$src = 'chart3.php?'.url_param('period').
	url_param($db_httptest['name'], false,'name').
	url_param(150, false, 'height').
	url_param(get_request('stime',0), false,'stime').
	url_param(HTTPSTEP_ITEM_TYPE_IN, false, 'http_item_type').
	url_param($db_httptest['httptestid'], false, 'httptestid').
	url_param(GRAPH_TYPE_STACKED, false, 'graphtype');

$dom_graph_id = 'graph_in';
$objData = array(
	'id' => $dom_graph_id,
	'containerid' => 'graph_1',
	'src' => $src,
	'objDims' => $graphDims,
	'loadImage' => 1,
	'dynamic' => 1,
	'mainObject' => 1,
	'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1),
	'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
);
zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($objData).');');

// graph 2
$src ='chart3.php?'.url_param('period').url_param('from').
	url_param($db_httptest['name'], false,'name').
	url_param(150, false, 'height').
	url_param(get_request('stime',0), false,'stime').
	url_param(HTTPSTEP_ITEM_TYPE_TIME, false, 'http_item_type').
	url_param($db_httptest['httptestid'], false, 'httptestid').
	url_param(GRAPH_TYPE_STACKED, false, 'graphtype');

$dom_graph_id = 'graph_time';
$objData = array(
	'id' => $dom_graph_id,
	'containerid' => 'graph_2',
	'src' => $src,
	'objDims' => $graphDims,
	'loadImage' => 1,
	'dynamic' => 1,
	'mainObject' => 1,
	'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1),
	'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
);
zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($objData).');');

$dom_graph_id = 'none';
$objData = array(
	'id' => $dom_graph_id,
	'loadScroll' => 1,
	'dynamic' => 1,
	'mainObject' => 1,
	'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1),
	'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
);

zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($objData).');');
zbx_add_post_js('timeControl.processObjects();');

$graphsWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';
