<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	require_once('include/config.inc.php');
	require_once('include/hosts.inc.php');
	require_once('include/httptest.inc.php');
	require_once('include/forms.inc.php');

	$page['title'] = 'S_DETAILS_OF_SCENARIO';
	$page['file'] = 'httpdetails.php';
	$page['hist_arg'] = array('httptestid');
	$page['scripts'] = array('class.calendar.js','gtlc.js');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	define('ZBX_PAGE_DO_REFRESH', 1);

	include_once('include/page_header.php');
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'period'=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'stime'=>	array(T_ZBX_STR, O_OPT,	 null,	null, null),
		'reset'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'httptestid'=>	array(T_ZBX_INT, O_MAND,	null,	DB_ID,		'isset({favobj})'),

		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		null),
		'favid'=>		array(T_ZBX_INT, O_OPT, P_ACT,  null,			null),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
	);

	if(!check_fields($fields)) exit();
?>
<?php
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.httpdetails.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
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
		include_once('include/page_footer.php');
		exit();
	}
?>
<?php

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_ONLY, PERM_RES_IDS_ARRAY);
	$sql = 'SELECT ht.* '.
		' FROM httptest ht, applications a '.
		' WHERE '.DBcondition('a.hostid', $available_hosts).
			' AND a.applicationid=ht.applicationid '.
			' AND ht.httptestid='.$_REQUEST['httptestid'];
	if(!$httptest_data = DBfetch(DBselect($sql))){
		access_deny();
	}

	navigation_bar_calc('web.httptest', $_REQUEST['httptestid'], true);
?>
<?php
	$details_wdgt = new CWidget();

// Header
	$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
	$rst_icon = get_icon('reset', array('id' => $_REQUEST['httptestid']));

	$details_wdgt->addPageHeader(
		array(S_DETAILS_OF_SCENARIO_BIG.SPACE, bold($httptest_data['name']),' ['.date(S_DATE_FORMAT_YMDHMS, $httptest_data['lastcheck']).']'),
		array($rst_icon, $fs_icon)
	);
//-------------

// TABLE
	$table = new CTableInfo();
	$table->setHeader(array(S_STEP, S_SPEED, S_RESPONSE_TIME, S_RESPONSE_CODE, S_STATUS));

	$sql = 'SELECT * FROM httpstep WHERE httptestid='.$httptest_data['httptestid'].' ORDER BY no';
	$db_httpsteps = DBselect($sql);

	$totalTime = array(
		'lastvalue' => 0,
		'value_type' => null,
		'valuemapid' => null,
		'units' => null
	);

	while($httpstep_data = DBfetch($db_httpsteps)){
		$status['msg'] = S_OK_BIG;
		$status['style'] = 'enabled';

		if(HTTPTEST_STATE_BUSY == $httptest_data['curstate'] ){
			if($httptest_data['curstep'] == ($httpstep_data['no'])){
				$status['msg'] = S_IN_PROGRESS;
				$status['style'] = 'unknown';
				$status['skip'] = true;
			}
			else if($httptest_data['curstep'] < ($httpstep_data['no'])){
				$status['msg'] = S_UNKNOWN;
				$status['style'] = 'unknown';
				$status['skip'] = true;
			}
		}
		else if( HTTPTEST_STATE_IDLE == $httptest_data['curstate'] ){
			if($httptest_data['lastfailedstep'] != 0){
				if($httptest_data['lastfailedstep'] == ($httpstep_data['no'])){
					$status['msg'] = S_FAIL.' - '.S_ERROR.': '.$httptest_data['error'];
					$status['style'] = 'disabled';
					//$status['skip'] = true;
				}
				else if($httptest_data['lastfailedstep'] < ($httpstep_data['no'])){
					$status['msg'] = S_UNKNOWN;
					$status['style'] = 'unknown';
					$status['skip'] = true;
				}
			}
		}
		else{
			$status['msg'] = S_UNKNOWN;
			$status['style'] = 'unknown';
			$status['skip'] = true;
		}

		$itemids = array();
		$sql = 'SELECT i.lastvalue, i.value_type, i.valuemapid, i.units, i.itemid, hi.type as httpitem_type '.
				' FROM items i, httpstepitem hi '.
				' WHERE hi.itemid=i.itemid '.
					' AND hi.httpstepid='.$httpstep_data['httpstepid'];
		$db_items = DBselect($sql);
		while($item_data = DBfetch($db_items)){
			if(isset($status['skip'])) $item_data['lastvalue'] = null;

			$httpstep_data['item_data'][$item_data['httpitem_type']] = $item_data;

			if($item_data['httpitem_type'] == HTTPSTEP_ITEM_TYPE_TIME){
				$totalTime['lastvalue'] += $item_data['lastvalue'];
				$totalTime['value_type'] = $item_data['value_type'];
				$totalTime['valuemapid'] = $item_data['valuemapid'];
				$totalTime['units'] = $item_data['units'];
			}

			$itemids[] = $item_data['itemid'];
		}

		$speed = format_lastvalue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_IN]);
		$respTime = $httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_TIME]['lastvalue'];
		$resp = format_lastvalue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_RSPCODE]);
		$table->addRow(array(
			$httpstep_data['name'],
			($speed == 0 ? '-' : $speed),
			($respTime == 0 ? '-' : format_lastvalue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_TIME])),
			($resp == 0 ? '-' : $resp),
			new CSpan($status['msg'], $status['style'])
		));
	}

	$status['msg'] = S_OK_BIG;
	$status['style'] = 'enabled';

	if( HTTPTEST_STATE_BUSY == $httptest_data['curstate'] ){
		$status['msg'] = S_IN_PROGRESS;
		$status['style'] = 'unknown';
	}
	else if ( HTTPTEST_STATE_UNKNOWN == $httptest_data['curstate'] ){
		$status['msg'] = S_UNKNOWN;
		$status['style'] = 'unknown';
	}
	else if($httptest_data['lastfailedstep'] > 0){
		$status['msg'] = S_FAIL.' - '.S_ERROR.': '.$httptest_data['error'];
		$status['style'] = 'disabled';
	}

	$table->addRow(array(
		new CSpan(S_TOTAL_BIG, 'bold'),
		SPACE,
		new CSpan(format_lastvalue($totalTime), 'bold'),
		SPACE,
		new CSpan($status['msg'], $status['style'].' bold')
	));

	$details_wdgt->addItem($table);
	$details_wdgt->show();

	echo SBR;

	$graphsWidget = new CWidget();

	$scroll_div = new CDiv();
	$scroll_div->setAttribute('id','scrollbar_cntr');
	$graphsWidget->addFlicker($scroll_div, CProfile::get('web.httpdetails.filter.state',0));
	$graphsWidget->addItem(SPACE);

	$graphTable = new CTableInfo();
	$graphTable->setAttribute('id','graph');

	$graph_cont = new CCol();
	$graph_cont->setAttribute('id', 'graph_1');
	$graphTable->addRow(array(bold(S_SPEED), $graph_cont));

	$graph_cont = new CCol();
	$graph_cont->setAttribute('id', 'graph_2');
	$graphTable->addRow(array(bold(S_RESPONSE_TIME), $graph_cont));

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
		'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1)
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
		'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1)
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
		'periodFixed' => CProfile::get('web.httptest.timelinefixed', 1)
	);

	zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
	zbx_add_post_js('timeControl.processObjects();');

	$graphsWidget->show();

?>
<?php
include_once('include/page_footer.php');
?>
