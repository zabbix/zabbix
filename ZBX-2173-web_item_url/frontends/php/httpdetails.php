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
$page['hist_arg'] = array('hostid','grouid','graphid');
$page['scripts'] = array('scriptaculous.js?load=effects,dragdrop','class.calendar.js','gtlc.js');

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
		'httptestid'=>	array(T_ZBX_INT, O_MAND,	null,	DB_ID,		null),
		'groupid'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'hostid'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),

		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
	);

	if(!check_fields($fields)) exit();;
?>
<?php
	if(isset($_REQUEST['favobj'])){
		if('timeline' == $_REQUEST['favobj']){
			if(isset($_REQUEST['httptestid']) && isset($_REQUEST['period'])){
				navigation_bar_calc('web.httptest', $_REQUEST['httptestid']);
			}
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
?>
<?php
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);

	
	$sql = 'SELECT ht.* '.
		' FROM httptest ht, applications a '.
		' WHERE '.DBcondition('a.hostid',$available_hosts).
			' AND a.applicationid=ht.applicationid '.
			' AND ht.httptestid='.$_REQUEST['httptestid'];

	if(!$httptest_data = DBfetch(DBselect($sql))){
		access_deny();
	}

	$effectiveperiod = navigation_bar_calc('web.httptest',$_REQUEST['httptestid']);
?>
<?php
	$details_wdgt = new CWidget();
	$details_wdgt->setClass('header');

// Header
	$text = array(S_DETAILS_OF_SCENARIO_BIG.' / ',bold($httptest_data['name']),' ['.date(S_DATE_FORMAT_YMDHMS,$httptest_data['lastcheck']).']');

	$url = '?httptestid='.$_REQUEST['httptestid'].'&fullscreen='.($_REQUEST['fullscreen']?'0':'1');

	$fs_icon = new CDiv(SPACE,'fullscreen');
	$fs_icon->setAttribute('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
	$fs_icon->addAction('onclick',new CJSscript("javascript: document.location = '".$url."';"));

	$rst_icon = new CDiv(SPACE,'iconreset');
	$rst_icon->setAttribute('title',S_RESET);
	$rst_icon->addAction('onclick',new CJSscript("javascript: timeControl.objectReset('".$_REQUEST['httptestid']."');"));

	$details_wdgt->addHeader($text, array($rst_icon, $fs_icon));
//-------------

// TABLE
	$table = new CTableInfo();
	$table->setHeader(array(S_STEP, S_SPEED, S_RESPONSE_TIME, S_RESPONSE_CODE, S_STATUS));

	$items = array();
	$total_data = array( HTTPSTEP_ITEM_TYPE_IN => null, HTTPSTEP_ITEM_TYPE_TIME => null );

	$color = array(
		'current' => 0,
		0  => array('next' => '1'),
		1  => array('color' => 'Red', 			'next' => '2'),
		2  => array('color' => 'Dark Green',	'next' => '3'),
		3  => array('color' => 'Blue', 			'next' => '4'),
		4  => array('color' => 'Dark Yellow', 	'next' => '5'),
		5  => array('color' => 'Cyan', 			'next' => '6'),
		6  => array('color' => 'Gray',			'next' => '7'),
		7  => array('color' => 'Dark Red',		'next' => '8'),
		8  => array('color' => 'Green',			'next' => '9'),
		9  => array('color' => 'Dark Blue', 	'next' => '10'),
		10 => array('color' => 'Yellow', 		'next' => '11'),
		11 => array('color' => 'Black',	 		'next' => '1')
		);

	$sql = 'SELECT * FROM httpstep WHERE httptestid='.$httptest_data['httptestid'].' ORDER BY no';
	$db_httpsteps = DBselect($sql);
	while($httpstep_data = DBfetch($db_httpsteps)){
		$status['msg'] = S_OK_BIG;
		$status['style'] = 'enabled';

		if( HTTPTEST_STATE_BUSY == $httptest_data['curstate'] ){
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

		$item_color = $color[$color['current'] = $color[$color['current']]['next']]['color'];

		$sql = 'SELECT i.*, hi.type as httpitem_type '.
				' FROM items i, httpstepitem hi '.
				' WHERE hi.itemid=i.itemid '.
					' AND hi.httpstepid='.$httpstep_data['httpstepid'];
		$db_items = DBselect($sql);
		while($item_data = DBfetch($db_items)){
			if(isset($status['skip'])) $item_data['lastvalue'] = null;

			$httpstep_data['item_data'][$item_data['httpitem_type']] = $item_data;

			if (!str_in_array($item_data['httpitem_type'], array(HTTPSTEP_ITEM_TYPE_IN, HTTPSTEP_ITEM_TYPE_TIME))) continue;

			if(isset($total_data[$item_data['httpitem_type']])){
				$total_data[$item_data['httpitem_type']]['lastvalue'] += $item_data['lastvalue'];
			}
			else{
				$total_data[$item_data['httpitem_type']] = $item_data;
			}
			$items[$item_data['httpitem_type']][] = array(
				'itemid' => $item_data['itemid'],
				'color' => $item_color,
				'sortorder' => 'no');
		}

		$table->addRow(array(
			$httpstep_data['name'],
			format_lastvalue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_IN]),
			format_lastvalue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_TIME]),
			format_lastvalue($httpstep_data['item_data'][HTTPSTEP_ITEM_TYPE_RSPCODE]),
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
		new CCol(S_TOTAL_BIG, 'bold'),
		new CCol(SPACE, 'bold'),
		new CCol(format_lastvalue($total_data[HTTPSTEP_ITEM_TYPE_TIME]), 'bold'),
		new CCol(SPACE, 'bold'),
		new CCol(new CSpan($status['msg'], $status['style']), 'bold')
		));

	$details_wdgt->addItem($table);
	$details_wdgt->show();

	echo SBR;

	show_table_header(array(S_HISTORY.' "',bold($httptest_data['name']),'"'));

	$form = new CTableInfo();
	$form->setAttribute('id','graph');

	$graph_cont = new CCol();
	$graph_cont->setAttribute('id', 'graph_1');
	$form->addRow(array(bold(S_SPEED), $graph_cont));

	$graph_cont = new CCol();
	$graph_cont->setAttribute('id', 'graph_2');
	$form->addRow(array(bold(S_RESPONSE_TIME), $graph_cont));

	$form->show();

// NAV BAR
	$timeline = array();
	$timeline['period'] = get_request('period',ZBX_PERIOD_DEFAULT);
	$timeline['starttime'] = get_min_itemclock_by_itemid($items[HTTPSTEP_ITEM_TYPE_IN][0]['itemid']);

	if(isset($_REQUEST['stime'])){
		$bstime = $_REQUEST['stime'];
		$timeline['usertime'] = mktime(substr($bstime,8,2),substr($bstime,10,2),0,substr($bstime,4,2),substr($bstime,6,2),substr($bstime,0,4));
		$timeline['usertime'] += $timeline['period'];
	}

	$containerid = 'graph_1';
	$dom_graph_id = 'graph_in';

	$graphDims = array();
	$graphDims['width'] = -120;
	$graphDims['graphHeight'] = 150;
	$graphDims['shiftXleft'] = 100;
	$graphDims['shiftXright'] = 50;
	$graphDims['graphtype'] = GRAPH_TYPE_STACKED;

	$src = 'chart3.php?'.url_param('period').
			url_param($httptest_data['name'], false,'name').
			url_param(150, false, 'height').
			url_param(get_request('stime',0), false,'stime').
			url_param($items[HTTPSTEP_ITEM_TYPE_IN], false, 'items').
			url_param(GRAPH_TYPE_STACKED, false, 'graphtype');

	$objData = array(
		'id' => $_REQUEST['httptestid'],
		'domid' => $dom_graph_id,
		'containerid' => $containerid,
		'src' => $src,
		'objDims' => $graphDims,
		'loadSBox' => 1,
		'loadImage' => 1,
		'loadScroll' => 0,
		'dynamic' => 1,
		'mainObject' => 1
	);

	zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');

	$containerid = 'graph_2';
	$dom_graph_id = 'graph_time';
	$src ='chart3.php?'.url_param('period').url_param('from').
			url_param($httptest_data['name'], false,'name').
			url_param(150, false,'height').
			url_param(get_request('stime',0), false,'stime').
			url_param($items[HTTPSTEP_ITEM_TYPE_TIME], false, 'items').
			url_param(GRAPH_TYPE_STACKED, false, 'graphtype');

	$objData = array(
		'id' => $_REQUEST['httptestid'],
		'domid' => $dom_graph_id,
		'containerid' => $containerid,
		'src' => $src,
		'objDims' => $graphDims,
		'loadSBox' => 1,
		'loadImage' => 1,
		'loadScroll' => 0,
		'dynamic' => 1,
		'mainObject' => 1
	);

	zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
//-------------

	$scroll_div = new CDiv();
	$scroll_div->setAttribute('id','scrollbar_cntr');
	$scroll_div->show();

	$dom_graph_id = 'none';
	$objData = array(
		'id' => $_REQUEST['httptestid'],
		'domid' => $dom_graph_id,
		'loadSBox' => 0,
		'loadImage' => 0,
		'loadScroll' => 1,
		'scrollWidthByImage' => 0,
		'dynamic' => 1,
		'mainObject' => 1
	);

	zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
	zbx_add_post_js('timeControl.processObjects();');
?>
<?php

include_once('include/page_footer.php');

?>
