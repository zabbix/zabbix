<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	require_once "include/config.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/httptest.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_DETAILS_OF_SCENARIO";
	$page["file"] = "httpdetails.php";
	$page['hist_arg'] = array('hostid','grouid','graphid','period','stime');
	$page['scripts'] = array('gmenu.js','scrollbar.js','sbox.js','sbinit.js');
	
	define('ZBX_PAGE_DO_REFRESH', 1);

include_once "include/page_header.php";

?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"from"=>	array(T_ZBX_INT, O_OPT,	 null,	'{}>=0', null),
		"period"=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		"stime"=>	array(T_ZBX_STR, O_OPT,	 null,	null, null),

		"reset"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		"httptestid"=>	array(T_ZBX_INT, O_MAND,	null,	DB_ID,		null),

		"groupid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"hostid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			'isset({favid})'),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),

	);

	check_fields($fields);

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);

	$sql = 'select ht.* '.
		' from httptest ht, applications a '.
		' where '.DBcondition('a.hostid',$available_hosts).
			' and a.applicationid=ht.applicationid '.
			' and ht.httptestid='.$_REQUEST['httptestid'];
			
	if(!$httptest_data = DBfetch(DBselect($sql))){
		access_deny();
	}
	
	navigation_bar_calc();
?>
<?php
// Header	
	$text = array(S_DETAILS_OF_SCENARIO_BIG.' / ',bold($httptest_data['name']),' ['.date(S_DATE_FORMAT_YMDHMS,$httptest_data['lastcheck']).']');
	
	$url = '?httptestid='.$_REQUEST['httptestid'].'&fullscreen='.($_REQUEST['fullscreen']?'0':'1');

	$fs_icon = new CDiv(SPACE,'fullscreen');
	$fs_icon->AddOption('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
	$fs_icon->AddAction('onclick',new CScript("javascript: document.location = '".$url."';"));
	
	$icon_tab = new CTable();
	$icon_tab->AddRow(array($fs_icon,SPACE,$text));
	
	$text = $icon_tab;

	show_table_header($text,new CLink(S_CANCEL,'httpmon.php'.url_param('groupid').url_param('hostid')));

//-------------

// TABLE
	$table  = new CTableInfo();
	$table->SetHeader(array(S_STEP, S_SPEED, S_RESPONSE_TIME, S_RESPONSE_CODE, S_STATUS));

	$items = array();
	$total_data = array( HTTPSTEP_ITEM_TYPE_IN => null, HTTPSTEP_ITEM_TYPE_TIME => null );

	$color = array(
		'current' => 0,
		0  => array('next' => '1'),
		1  => array('color' => 'Red', 		'next' => '2'),
		2  => array('color' => 'Dark Green',	'next' => '3'),
		3  => array('color' => 'Blue', 		'next' => '4'),
		4  => array('color' => 'Dark Yellow', 	'next' => '5'),
		5  => array('color' => 'Cyan', 		'next' => '6'),
		6  => array('color' => 'Gray',		'next' => '7'),
		7  => array('color' => 'Dark Red',	'next' => '8'),
		8  => array('color' => 'Green',		'next' => '9'),
		9  => array('color' => 'Dark Blue', 	'next' => '10'),
		10 => array('color' => 'Yellow', 	'next' => '11'),
		11 => array('color' => 'Black',	 	'next' => '1')
		);

	$db_httpsteps = DBselect('select * from httpstep where httptestid='.$httptest_data['httptestid'].' order by no');
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

		$table->AddRow(array(
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
	
	$table->AddRow(array(
		new CCol(S_TOTAL_BIG, 'bold'), 
		new CCol(SPACE, 'bold'),
		new CCol(format_lastvalue($total_data[HTTPSTEP_ITEM_TYPE_TIME]), 'bold'),
		new CCol(SPACE, 'bold'),
		new CCol(new CSpan($status['msg'], $status['style']), 'bold')
		));

	$table->Show();

	echo SBR;
	
	if( isset($_REQUEST['period']) && $_REQUEST['period'] != ZBX_MIN_PERIOD ) {
		update_profile('web.httptest.period', $_REQUEST['period'], PROFILE_TYPE_INT, $_REQUEST['httptestid']);
	}
	$_REQUEST['period'] = get_profile('web.httptest.period', ZBX_PERIOD_DEFAULT, PROFILE_TYPE_INT, $_REQUEST['httptestid']);
				
	show_table_header(array(S_HISTORY.' "',
						bold($httptest_data['name']),
						'"')
					);
	$form = new CTableInfo();
	$form->AddOption('id','graph');
	$form->AddRow(array(bold(S_SPEED) , new CCol(
		get_dynamic_chart('graph_1','chart3.php?'.url_param('period').url_param('from').
			url_param($httptest_data['name'], false,'name').
			url_param(150, false,'height').
			url_param(get_request('stime',0), false,'stime').
			url_param($items[HTTPSTEP_ITEM_TYPE_IN], false, 'items').
			url_param(GRAPH_TYPE_STACKED, false, 'graphtype'),'-128')
		, 'center')));

	$form->AddRow(array(bold(S_RESPONSE_TIME) , new CCol(
		get_dynamic_chart('graph_2','chart3.php?'.url_param('period').url_param('from').
			url_param($httptest_data['name'], false,'name').
			url_param(150, false,'height').
			url_param(get_request('stime',0), false,'stime').
			url_param($items[HTTPSTEP_ITEM_TYPE_TIME], false, 'items').
			url_param(GRAPH_TYPE_STACKED, false, 'graphtype'),'-128')
		,'center')));

	$form->Show();
	echo SBR.SBR;
	

	$period = get_request('period',3600);
//SDI(get_min_itemclock_by_itemid($items[HTTPSTEP_ITEM_TYPE_IN][0]['itemid']));
	$mstime = min(get_min_itemclock_by_itemid($items[HTTPSTEP_ITEM_TYPE_IN][0]['itemid']),get_min_itemclock_by_itemid($items[HTTPSTEP_ITEM_TYPE_TIME][0]['itemid']));
	$stime = ($mstime)?$mstime:0;
	$bstime = time()-$period;
	
	if(isset($_REQUEST['stime'])){
		$bstime = $_REQUEST['stime'];
		$bstime = mktime(substr($bstime,8,2),substr($bstime,10,2),0,substr($bstime,4,2),substr($bstime,6,2),substr($bstime,0,4));
	}
	$script = 	'scrollinit(0,'.$period.','.$stime.',0,'.$bstime.');
				showgraphmenu("graph");
				graph_zoom_init("graph_1",'.$bstime.','.$period.',ZBX_G_WIDTH, 150, false);
				graph_zoom_init("graph_2",'.$bstime.','.$period.',ZBX_G_WIDTH, 150, false);';
					
	zbx_add_post_js($script); 

//	navigation_bar("#", array('httptestid'));
?>
<?php

include_once "include/page_footer.php"

?>
