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
	define('ZBX_PAGE_DO_REFRESH', 1);

include_once "include/page_header.php";

?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"from"=>	array(T_ZBX_INT, O_OPT,	 null,	'{}>=0', null),
		"period"=>	array(T_ZBX_INT, O_OPT,	 null,	BETWEEN(ZBX_MIN_PERIOD,ZBX_MAX_PERIOD), null),
		"dec"=>		array(T_ZBX_INT, O_OPT,	 null,	null, null),
		"inc"=>		array(T_ZBX_INT, O_OPT,	 null,	null, null),
		"left"=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		"right"=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		"stime"=>	array(T_ZBX_STR, O_OPT,	 null,	null, null),

		"httptestid"=>	array(T_ZBX_INT, O_MAND,	null,	DB_ID,		null),

		"groupid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"hostid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null)
	);

	check_fields($fields);

	$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,null,null,$ZBX_CURNODEID);

	if(!($httptest_data = DBfetch(DBselect('select ht.* from httptest ht, applications a '.
		' where a.hostid in ('.$accessible_hosts.') and a.applicationid=ht.applicationid '.
		' and ht.httptestid='.$_REQUEST['httptestid']))))
	{
		access_deny();
	}
	
	navigation_bar_calc();
?>
<?php
	$lnkCancel = new CLink(S_CANCEL,'httpmon.php'.url_param('groupid').url_param('hostid'));
	show_table_header(S_DETAILS_OF_SCENARIO_BIG.' "'.bold($httptest_data['name']).'" - '.
		date(S_DATE_FORMAT_YMDHMS,$httptest_data['lastcheck']),$lnkCancel);

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
	while($httpstep_data = DBfetch($db_httpsteps))
	{
		$status['msg'] = S_OK_BIG;
		$status['style'] = 'enabled';

		if( HTTPTEST_STATE_BUSY == $httptest_data['curstate'] )
		{
			if($httptest_data['curstep'] == ($httpstep_data['no']))
			{
				$status['msg'] = S_IN_PROGRESS;
				$status['style'] = 'unknown';
				$status['skip'] = true;
			}
			elseif($httptest_data['curstep'] < ($httpstep_data['no']))
			{
				$status['msg'] = S_UNKNOWN;
				$status['style'] = 'unknown';
				$status['skip'] = true;
			}
		}
		else if( HTTPTEST_STATE_IDLE == $httptest_data['curstate'] )
		{
			if($httptest_data['lastfailedstep'] != 0)
			{
				if($httptest_data['lastfailedstep'] == ($httpstep_data['no']))
				{
					$status['msg'] = S_FAIL.' - '.S_ERROR.': '.$httptest_data['error'];
					$status['style'] = 'disabled';
					//$status['skip'] = true;
				}
				else if($httptest_data['lastfailedstep'] < ($httpstep_data['no']))
				{
					$status['msg'] = S_UNKNOWN;
					$status['style'] = 'unknown';
					$status['skip'] = true;
				}
			}

		}
		else
		{
			$status['msg'] = S_UNKNOWN;
			$status['style'] = 'unknown';
			$status['skip'] = true;
		}

		$item_color = $color[$color['current'] = $color[$color['current']]['next']]['color'];

		$db_items = DBselect('select i.*, hi.type as httpitem_type from items i, httpstepitem hi '.
			' where hi.itemid=i.itemid and hi.httpstepid='.$httpstep_data['httpstepid']);
		while($item_data = DBfetch($db_items))
		{
			if(isset($status['skip'])) $item_data['lastvalue'] = null;

			$httpstep_data['item_data'][$item_data['httpitem_type']] = $item_data;

			if (!in_array($item_data['httpitem_type'], array(HTTPSTEP_ITEM_TYPE_IN, HTTPSTEP_ITEM_TYPE_TIME))) continue;
	
			if(isset($total_data[$item_data['httpitem_type']]))
			{
				$total_data[$item_data['httpitem_type']]['lastvalue'] += $item_data['lastvalue'];
			}
			else
			{
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

	if( HTTPTEST_STATE_BUSY == $httptest_data['curstate'] )
	{
		$status['msg'] = S_IN_PROGRESS;
		$status['style'] = 'unknown';
	}
	else if ( HTTPTEST_STATE_UNKNOWN == $httptest_data['curstate'] )
	{
		$status['msg'] = S_UNKNOWN;
		$status['style'] = 'unknown';
	}
	else if($httptest_data['lastfailedstep'] > 0)
	{
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

	echo BR;

	show_table_header(S_HISTORY.' "'.bold($httptest_data['name']).'"');
	$form = new CTableInfo();

	$form->AddRow(array(bold(S_SPEED) , new CCol(
		get_dynamic_chart('chart3.php?'.url_param('period').url_param('from').
			url_param($httptest_data['name'], false,'name').
			url_param(150, false,'height').
			url_param($items[HTTPSTEP_ITEM_TYPE_IN], false, 'items').
			url_param(GRAPH_TYPE_STACKED, false, 'graphtype'),'-100')
		, 'center')));

	$form->AddRow(array(bold(S_RESPONSE_TIME) , new CCol(
		get_dynamic_chart('chart3.php?'.url_param('period').url_param('from').
			url_param($httptest_data['name'], false,'name').
			url_param(150, false,'height').
			url_param($items[HTTPSTEP_ITEM_TYPE_TIME], false, 'items').
			url_param(GRAPH_TYPE_STACKED, false, 'graphtype'),'-100')
		,'center')));

	$form->Show();

	navigation_bar("#", array('httptestid'));
?>
<?php

include_once "include/page_footer.php"

?>
