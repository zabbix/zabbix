<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php
	require_once dirname(__FILE__).'/include/config.inc.php';
	require_once dirname(__FILE__).'/include/hosts.inc.php';
	require_once dirname(__FILE__).'/include/httptest.inc.php';
	require_once dirname(__FILE__).'/include/forms.inc.php';

	$page['title'] = _('Details of scenario');
	$page['file'] = 'httpdetails.php';
	$page['hist_arg'] = array('httptestid');
	$page['scripts'] = array('class.calendar.js','gtlc.js');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	define('ZBX_PAGE_DO_REFRESH', 1);

	require_once dirname(__FILE__).'/include/page_header.php';
?>
<?php

//		VAR			TYPE		OPTIONAL	FLAGS		VALIDATION	EXCEPTION
	$fields = array(
		'period' =>	array(T_ZBX_INT,	O_OPT,		null,		null,		null),
		'stime' =>	array(T_ZBX_STR,	O_OPT,		null,		null,		null),
		'reset' =>	array(T_ZBX_STR,	O_OPT,		P_SYS|P_ACT,	null,		null),
		'httptestid' =>	array(T_ZBX_INT,	O_MAND,		null,		DB_ID,		'isset({favobj})'),
		'fullscreen' =>	array(T_ZBX_INT,	O_OPT,		P_SYS,		IN('0,1'),	null),
//ajax
		'favobj' =>	array(T_ZBX_STR,	O_OPT,		P_ACT,		null,		null),
		'favref' =>	array(T_ZBX_STR,	O_OPT,		P_ACT,  	NOT_EMPTY,	null),
		'favid' =>	array(T_ZBX_INT,	O_OPT,		P_ACT,  	null,		null),
		'favstate' =>	array(T_ZBX_INT,	O_OPT,		P_ACT,  	NOT_EMPTY,	null),
	);

	if(!check_fields($fields)) exit();
?>
<?php
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.httpdetails.filter.state',$_REQUEST['favstate'], PROFILE_TYPE_INT);
		}
		if('timeline' == $_REQUEST['favobj']){
			if(isset($_REQUEST['favid']) && isset($_REQUEST['period'])){
				navigation_bar_calc('web.httptest', $_REQUEST['favid'], true);
			}
		}
		// saving fixed/dynamic setting to profile
		if('timelinefixedperiod' == $_REQUEST['favobj']){
			if(isset($_REQUEST['favid'])){
				CProfile::update('web.httptest.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
			}
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit();
	}
?>
<?php
	$httptest_data = API::WebCheck()->get(array(
		'httptestids' => $_REQUEST['httptestid'],
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	));
	$httptest_data = reset($httptest_data);
	if (!$httptest_data) {
		access_deny();
	}

	$httptest_data['lastfailedstep'] = 0;
	$httptest_data['error'] = '';

	$result = DBselect(
			'SELECT hti.httptestid,hti.type,i.lastvalue,i.lastclock'.
			' FROM httptestitem hti,items i'.
			' WHERE hti.itemid=i.itemid'.
				' AND hti.type IN ('.HTTPSTEP_ITEM_TYPE_LASTSTEP.','.HTTPSTEP_ITEM_TYPE_LASTERROR.')'.
				' AND i.lastclock IS NOT NULL'.
				' AND hti.httptestid='.$httptest_data['httptestid']
	);
	while ($row = DBfetch($result)) {
		if ($row['type'] == HTTPSTEP_ITEM_TYPE_LASTSTEP) {
			$httptest_data['lastcheck'] = $row['lastclock'];
			$httptest_data['lastfailedstep'] = $row['lastvalue'];
		}
		else {
			$httptest_data['error'] = $row['lastvalue'];
		}
	}

	navigation_bar_calc('web.httptest', $_REQUEST['httptestid'], true);
?>
<?php
	$details_wdgt = new CWidget();

// Header
	$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
	$rst_icon = get_icon('reset', array('id' => $_REQUEST['httptestid']));

	$lastcheck = null;
	if (isset($httptest_data['lastcheck'])) {
		$lastcheck = ' ['.zbx_date2str(_('d M Y H:i:s'), $httptest_data['lastcheck']).']';
	}

	$details_wdgt->addPageHeader(
		array(_('DETAILS OF SCENARIO').SPACE, bold($httptest_data['name']), $lastcheck),
		array($rst_icon, $fs_icon)
	);
//-------------

// TABLE
	$table = new CTableInfo(_('No steps defined.'));
	$table->setHeader(array(_('Step'), _('Speed'), _('Response time'), _('Response code'), _('Status')));

	$sql = 'SELECT * FROM httpstep WHERE httptestid='.$httptest_data['httptestid'].' ORDER BY no';
	$db_httpsteps = DBselect($sql);

	$totalTime = array(
		'lastvalue' => 0,
		'value_type' => null,
		'valuemapid' => null,
		'units' => null
	);

	while($httpstep_data = DBfetch($db_httpsteps)){
		$status['msg'] = _('OK');
		$status['style'] = 'enabled';

		if (!isset($httptest_data['lastcheck'])) {
			$status['msg'] = _('Never executed');
			$status['style'] = 'unknown';
		}
		elseif ($httptest_data['lastfailedstep'] != 0) {
			if ($httptest_data['lastfailedstep'] == $httpstep_data['no']) {
				$status['msg'] = _s('Error: %1$s', $httptest_data['error']);
				$status['style'] = 'disabled';
			}
			elseif ($httptest_data['lastfailedstep'] < $httpstep_data['no']) {
				$status['msg'] = _('Unknown');
				$status['style'] = 'unknown';
				$status['skip'] = true;
			}
		}

		$itemids = array();
		$sql = 'SELECT i.lastvalue, i.lastclock, i.value_type, i.valuemapid, i.units, i.itemid, hi.type as httpitem_type '.
				' FROM items i, httpstepitem hi '.
				' WHERE hi.itemid=i.itemid '.
					' AND hi.httpstepid='.$httpstep_data['httpstepid'];
		$db_items = DBselect($sql);
		while($item_data = DBfetch($db_items)){
			if(isset($status['skip'])) $item_data['lastvalue'] = null;

			$httpstep_data['item_data'][$item_data['httpitem_type']] = $item_data;

			if($item_data['httpitem_type'] == HTTPSTEP_ITEM_TYPE_TIME){
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

		$table->addRow(array(
			$httpstep_data['name'],
			$speed,
			($respTime == 0 ? '-' : $respItemTime),
			$resp,
			new CSpan($status['msg'], $status['style'])
		));
	}

	if (!isset($httptest_data['lastcheck'])) {
		$status['msg'] = _('Never executed');;
		$status['style'] = 'unknown';
	}
	elseif ($httptest_data['lastfailedstep'] != 0) {
		$status['msg'] = _s('Error: %1$s', $httptest_data['error']);
		$status['style'] = 'disabled';
	}
	else {
		$status['msg'] = _('OK');
		$status['style'] = 'enabled';
	}

	$table->addRow(array(
		bold(_('TOTAL')),
		SPACE,
		bold(formatItemValue($totalTime)),
		SPACE,
		new CSpan($status['msg'], $status['style'].' bold')
	));

	$details_wdgt->addItem($table);
	$details_wdgt->show();

	echo SBR;

	$graphsWidget = new CWidget();

	$scroll_div = new CDiv();
	$scroll_div->setAttribute('id','scrollbar_cntr');
	$graphsWidget->addFlicker($scroll_div, CProfile::get('web.httpdetails.filter.state', 0));
	$graphsWidget->addItem(SPACE);

	$graphTable = new CTableInfo();
	$graphTable->setAttribute('id','graph');

	$graph_cont = new CCol();
	$graph_cont->setAttribute('id', 'graph_1');
	$graphTable->addRow(array(bold(_('Speed')), $graph_cont));

	$graph_cont = new CCol();
	$graph_cont->setAttribute('id', 'graph_2');
	$graphTable->addRow(array(bold(_('Response time')), $graph_cont));

	$graphsWidget->addItem($graphTable);

// NAV BAR
	$timeline = array(
		'period' => get_request('period',ZBX_PERIOD_DEFAULT),
		'starttime' => date('YmdHis', get_min_itemclock_by_itemid($itemids))
	);

	if(isset($_REQUEST['stime'])){
		$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
	}

	$graphDims = getGraphDims();
	$graphDims['shiftYtop'] += 1;
	$graphDims['width'] = -120;
	$graphDims['graphHeight'] = 150;

	$src = 'chart3.php?'.url_param('period').
		url_param($httptest_data['name'], false,'name').
		url_param(150, false, 'height').
		url_param(get_request('stime',0), false,'stime').
		url_param(HTTPSTEP_ITEM_TYPE_IN, false, 'http_item_type').
		url_param($httptest_data['httptestid'], false, 'httptestid').
		url_param(GRAPH_TYPE_STACKED, false, 'graphtype');

	$dom_graph_id = 'graph_in';
	$objData = array(
		'id' => $_REQUEST['httptestid'],
		'domid' => $dom_graph_id,
		'containerid' => 'graph_1',
		'src' => $src,
		'objDims' => $graphDims,
		'loadSBox' => 1,
		'loadImage' => 1,
		'loadScroll' => 0,
		'dynamic' => 1,
		'mainObject' => 1,
		'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1),
		'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
	);
	zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');


	$src ='chart3.php?'.url_param('period').url_param('from').
		url_param($httptest_data['name'], false,'name').
		url_param(150, false, 'height').
		url_param(get_request('stime',0), false,'stime').
		url_param(HTTPSTEP_ITEM_TYPE_TIME, false, 'http_item_type').
		url_param($httptest_data['httptestid'], false, 'httptestid').
		url_param(GRAPH_TYPE_STACKED, false, 'graphtype');

	$dom_graph_id = 'graph_time';
	$objData = array(
		'id' => $_REQUEST['httptestid'],
		'domid' => $dom_graph_id,
		'containerid' => 'graph_2',
		'src' => $src,
		'objDims' => $graphDims,
		'loadSBox' => 1,
		'loadImage' => 1,
		'loadScroll' => 0,
		'dynamic' => 1,
		'mainObject' => 1,
		'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1),
		'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
	);
	zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
//-------------

	$dom_graph_id = 'none';
	$objData = array(
		'id' => $_REQUEST['httptestid'],
		'domid' => $dom_graph_id,
		'loadSBox' => 0,
		'loadImage' => 0,
		'loadScroll' => 1,
		'scrollWidthByImage' => 0,
		'dynamic' => 1,
		'mainObject' => 1,
		'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1),
		'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
	);

	zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
	zbx_add_post_js('timeControl.processObjects();');

	$graphsWidget->show();

?>
<?php
require_once dirname(__FILE__).'/include/page_footer.php';
?>
