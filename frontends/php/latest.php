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
	require_once 'include/config.inc.php';
	require_once 'include/hosts.inc.php';
	require_once 'include/items.inc.php';

	$page['title'] = "S_LATEST_VALUES";
	$page['file'] = 'latest.php';
	$page['hist_arg'] = array('groupid','hostid','show','select','open','applicationid');
//	$page['scripts'] = array('updater.js');
	
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	
	define('ZBX_PAGE_MAIN_HAT','hat_latest');
	
	if(PAGE_TYPE_HTML == $page['type']){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}
//	define('ZBX_PAGE_DO_JS_REFRESH', 1);
	
include_once 'include/page_header.php';
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'applications'=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,		NULL),
		'applicationid'=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,		NULL),
		'close'=>		array(T_ZBX_INT, O_OPT,	NULL,	IN('1'),	NULL),
		'open'=>		array(T_ZBX_INT, O_OPT,	NULL,	IN('1'),	NULL),
		'groupbyapp'=>		array(T_ZBX_INT, O_OPT,	NULL,	IN('1'),	NULL),

		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
		'hostid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
		'select'=>		array(T_ZBX_STR, O_OPT, NULL,	NULL,		NULL),

		'show'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,		NULL),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
		
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			'isset({favid})'),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("hat"=={favobj})'),
	);

	check_fields($fields);

// HEADER REQUEST
	$_REQUEST['select'] = get_request('select',get_profile('web.latest.filter.select', ''));
	update_profile('web.latest.filter.select', $_REQUEST['select'], PROFILE_TYPE_STR);

	$options = array('allow_all_hosts','monitored_hosts','with_monitored_items','always_select_first_host');
	
	$_REQUEST['hostid'] = get_request('hostid',get_profile('web.latest.last.hostid', null));
	if(is_null($_REQUEST['hostid'])){
		array_push($options,'always_select_first_host');
		
		$_REQUEST['groupid'] = get_request('groupid',get_profile('web.latest.last.groupid', null));
		if(is_null($_REQUEST['groupid'])){
			validate_group(PERM_READ_ONLY,$options,'web.latest.last.groupid');
		}
	}

	if(!$ZBX_WITH_SUBNODES)	array_push($options,'only_current_node');

	validate_group_with_host(PERM_READ_ONLY,$options,'web.latest.last.groupid','web.latest.last.hostid');
//----------------
?>
<?php
/* AJAX	*/
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.latest.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
/*
		else if('refresh' == $_REQUEST['favobj']){
			switch($_REQUEST['favid']){
				case ZBX_PAGE_MAIN_HAT:
					include_once('blocks/latest.page.php');
					break;
			}
		}
//*/
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------
	$p_elements = array();
// Header
	$r_form = new CForm();
	$r_form->SetMethod('get');

//	$cmbGroup = new CComboBox('groupid',$_REQUEST['groupid'],"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."',this.form);");
//	$cmbHosts = new CComboBox('hostid',$_REQUEST['hostid'],"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."',this.form);");

	$cmbGroup = new CComboBox('groupid',$_REQUEST['groupid'],'javascript: submit();');
	$cmbHosts = new CComboBox('hostid',$_REQUEST['hostid'],'javascript: submit();');

	$cmbGroup->AddItem(0,S_ALL_SMALL);
	$cmbHosts->AddItem(0,S_ALL_SMALL);
	
	$available_groups= get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST);
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_LIST);

	$result=DBselect('SELECT DISTINCT g.groupid,g.name '.
					' FROM groups g, hosts_groups hg, hosts h, items i '.
					' WHERE '.DBcondition('g.groupid',$available_groups).
						' AND hg.groupid=g.groupid '.
						' AND h.status='.HOST_STATUS_MONITORED.
						' AND h.hostid=i.hostid '.
						' AND hg.hostid=h.hostid '.
						' AND i.status='.ITEM_STATUS_ACTIVE.
					' ORDER BY g.name');
	while($row=DBfetch($result)){
		$cmbGroup->AddItem(
				$row['groupid'],
				get_node_name_by_elid($row['groupid']).$row['name']
				);
	}
	$r_form->AddItem(array(S_GROUP.SPACE,$cmbGroup));
	
	$sql_from = '';
	$sql_where = '';
	if($_REQUEST['groupid'] > 0){
		$sql_from .= ',hosts_groups hg ';
		$sql_where.= ' AND hg.hostid=h.hostid AND hg.groupid='.$_REQUEST['groupid'];
	}
	$sql='SELECT DISTINCT h.hostid,h.host '.
		' FROM hosts h,items i '.$sql_from.
		' WHERE h.status='.HOST_STATUS_MONITORED.
			' AND h.hostid=i.hostid '.
			$sql_where.
			' AND i.status='.ITEM_STATUS_ACTIVE.
			' AND '.DBcondition('h.hostid',$available_hosts).
		' ORDER BY h.host';
	$result=DBselect($sql);
	while($row=DBfetch($result)){
		$cmbHosts->AddItem(
				$row['hostid'],
				get_node_name_by_elid($row['hostid']).$row['host']
				);
	}

	$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
	
	$l_form = new CForm();
	$l_form->SetMethod('get');
	
	$l_form->AddItem(array(S_SHOW_ITEMS_WITH_DESCRIPTION_LIKE, new CTextBox('select',$_REQUEST['select'],20)));
	$l_form->AddItem(array(SPACE, new CButton('show',S_SHOW,'javascript: submit();')));
//	$l_form->AddItem(array(SPACE, new CButton('show',S_SHOW,"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."',this.form);")));

	$p_elements[] = get_table_header($l_form,$r_form);
//-------------

	validate_sort_and_sortorder('i.description',ZBX_SORT_UP);
	
	$_REQUEST['groupbyapp'] = get_request('groupbyapp',get_profile('web.latest.groupbyapp',1));
	update_profile('web.latest.groupbyapp',$_REQUEST['groupbyapp'],PROFILE_TYPE_INT);

	$_REQUEST['applications'] = get_request('applications',get_profile('web.latest.applications',array(),PROFILE_TYPE_ARRAY_ID));

	if(isset($_REQUEST['open'])){
		if(!isset($_REQUEST['applicationid'])){
			$_REQUEST['applications'] = array();
			$show_all_apps = 1;
		}
		else if(!uint_in_array($_REQUEST['applicationid'],$_REQUEST['applications'])){
			array_push($_REQUEST['applications'],$_REQUEST['applicationid']);
		}
		
	} 
	else if(isset($_REQUEST['close'])){
		if(!isset($_REQUEST['applicationid'])){
			$_REQUEST['applications'] = array();
		}
		else if(($i=array_search($_REQUEST['applicationid'], $_REQUEST['applications'])) !== FALSE){
			unset($_REQUEST['applications'][$i]);
		}
	}

	/* limit opened application count */
	while(count($_REQUEST['applications']) > 25){
		array_shift($_REQUEST['applications']);
	}

	update_profile('web.latest.applications',$_REQUEST['applications'],PROFILE_TYPE_ARRAY_ID);
?>
<?php
	if(isset($show_all_apps)){
		$url = '?close=1'.
			url_param('groupid').
			url_param('hostid').
			url_param('applications').
			url_param('select');
		$link = new CLink(new CImg('images/general/opened.gif'),$url);
//		$link = new CLink(new CImg('images/general/opened.gif'),$url,null,"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');");
	}
	else{
		$url = '?open=1'.
			url_param('groupid').
			url_param('hostid').
			url_param('applications').
			url_param('select');
		$link = new CLink(new CImg('images/general/closed.gif'),$url);
//		$link = new CLink(new CImg('images/general/closed.gif'),$url,null,"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');");
	}
	
	$table=new CTableInfo();
	$table->SetHeader(array(
		is_show_subnodes()?make_sorting_link(S_NODE,'h.hostid') : null,
		($_REQUEST['hostid'] ==0)?make_sorting_link(S_HOST,'h.host') : NULL,
		array($link,SPACE,make_sorting_link(S_DESCRIPTION,'i.description')),
		make_sorting_link(S_LAST_CHECK,'i.lastclock'),
		S_LAST_VALUE,
		S_CHANGE,
		S_HISTORY));
		
//	$table->ShowStart();

	$db_apps = array();
	$db_appids = array();
	
	$sql_where.=($_REQUEST['hostid']>0)?' AND h.hostid='.$_REQUEST['hostid']:'';
	
	$sql = 'SELECT DISTINCT h.host,h.hostid, a.* '.
			' FROM applications a, hosts h '.$sql_from.
			' WHERE a.hostid=h.hostid'.
				$sql_where.
				' AND '.DBcondition('h.hostid',$available_hosts).
				' AND h.status='.HOST_STATUS_MONITORED.
			order_by('h.host,h.hostid','a.name,a.applicationid');
//SDI($sql);
	$db_app_res = DBselect($sql);
	while($db_app = DBfetch($db_app_res)){
		$db_app['item_cnt'] = 0;
		
		$db_apps[$db_app['applicationid']] = $db_app;
		$db_appids[$db_app['applicationid']] = $db_app['applicationid'];
	}

	$tab_rows = array();

	$sql = 'SELECT DISTINCT i.*, ia.applicationid '.
			' FROM items i,items_applications ia'.
			' WHERE '.DBcondition('ia.applicationid',$db_appids).
				' AND i.itemid=ia.itemid'.
				' AND i.status='.ITEM_STATUS_ACTIVE.
			order_by('i.description,i.itemid,i.lastclock');
//SDI($sql);
	$db_items = DBselect($sql);
	while($db_item = DBfetch($db_items)){
		$description = item_description($db_item['description'],$db_item['key_']);

		if(!empty($_REQUEST['select']) && !zbx_stristr($description, $_REQUEST['select']) ) continue;
		
		$db_app = &$db_apps[$db_item['applicationid']];

		if(!isset($tab_rows[$db_app['applicationid']])) $tab_rows[$db_app['applicationid']] = array();
		$app_rows = &$tab_rows[$db_app['applicationid']];

		$db_app['item_cnt']++;
		
		if(!uint_in_array($db_app['applicationid'],$_REQUEST['applications']) && !isset($show_all_apps)) continue;

		if(isset($db_item['lastclock']))
			$lastclock=date(S_DATE_FORMAT_YMDHMS,$db_item['lastclock']);
		else
			$lastclock = new CCol('-', 'center');

		$lastvalue=format_lastvalue($db_item);

		if(isset($db_item['lastvalue']) && isset($db_item['prevvalue']) && ($db_item['value_type'] == 0) && ($db_item['lastvalue']-$db_item['prevvalue'] != 0)){
			if($db_item['lastvalue']-$db_item['prevvalue']<0){
				$change=convert_units($db_item['lastvalue']-$db_item['prevvalue'],$db_item['units']);
			}
			else{
				$change='+'.convert_units($db_item['lastvalue']-$db_item['prevvalue'],$db_item['units']);
			}
			$change=nbsp($change);
		}
		else{
			$change=new CCol('-','center');
		}
		
		if(($db_item['value_type']==ITEM_VALUE_TYPE_FLOAT) || ($db_item['value_type']==ITEM_VALUE_TYPE_UINT64)){
			$actions=new CLink(S_GRAPH,'history.php?action=showgraph&itemid='.$db_item['itemid'],'action');
		}
		else{
			$actions=new CLink(S_HISTORY,'history.php?action=showvalues&period=3600&itemid='.$db_item['itemid'],'action');
		}
		
		array_push($app_rows, new CRow(array(
			is_show_subnodes()?SPACE:null,
			($_REQUEST['hostid']>0)?NULL:SPACE,
			str_repeat(SPACE,6).$description,
			$lastclock,
			new CCol($lastvalue, $lastvalue=='-' ? 'center' : null),
			$change,
			$actions
			)));
	}
	unset($app_rows);
	unset($db_app);

	foreach($db_apps as $appid => $db_app){
		if(!isset($tab_rows[$appid])) continue;

		$app_rows = $tab_rows[$appid];

		if(uint_in_array($db_app['applicationid'],$_REQUEST['applications']) || isset($show_all_apps)){					
			$url = '?close=1&applicationid='.$db_app['applicationid'].
				url_param('groupid').url_param('hostid').url_param('applications').
				url_param('fullscreen').url_param('select');
				
			$link = new CLink(new CImg('images/general/opened.gif'),$url);
//			$link = new CLink(new CImg('images/general/opened.gif'),$url,null,"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');");
		}
		else{
			$url = '?open=1&applicationid='.$db_app['applicationid'].
					url_param('groupid').url_param('hostid').url_param('applications').
					url_param('fullscreen').url_param('select');
			$link = new CLink(new CImg('images/general/closed.gif'),$url);
//			$link = new CLink(new CImg('images/general/closed.gif'),$url,null,"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');");
		}

		$col = new CCol(array($link,SPACE,bold($db_app['name']),SPACE.'('.$db_app['item_cnt'].SPACE.S_ITEMS.')'));
		$col->SetColSpan(5);

		$table->AddRow(array(
				get_node_name_by_elid($db_app['applicationid']),
				($_REQUEST['hostid'] > 0)?NULL:$db_app['host'],
				$col
			));

		foreach($app_rows as $row)
			$table->AddRow($row);
	}

// OTHER ITEMS (which doesn't linked to application)
	$db_hosts = array();
	$db_hostids = array();
	
	$sql = 'SELECT DISTINCT h.host,h.hostid '.
			' FROM hosts h'.$sql_from.', items i '.
				' LEFT JOIN items_applications ia ON ia.itemid=i.itemid'.
			' WHERE ia.itemid is NULL '.
				$sql_where.
				' AND h.hostid=i.hostid '.
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND i.status='.ITEM_STATUS_ACTIVE.
				' AND '.DBcondition('h.hostid',$available_hosts).
			' ORDER BY h.host';

	$db_host_res = DBselect($sql);
	while($db_host = DBfetch($db_host_res)){
		$db_host['item_cnt'] = 0;
		
		$db_hosts[$db_host['hostid']] = $db_host;
		$db_hostids[$db_host['hostid']] = $db_host['hostid'];
	}

	$tab_rows = array();

	$sql = 'SELECT h.host,h.hostid,i.* '.
			' FROM hosts h'.$sql_from.', items i '.
				' LEFT JOIN items_applications ia ON ia.itemid=i.itemid'.
			' WHERE ia.itemid is NULL '.
				$sql_where.
				' AND h.hostid=i.hostid '.
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND i.status='.ITEM_STATUS_ACTIVE.
				' AND '.DBcondition('h.hostid',$db_hostids).
			' ORDER BY i.description,i.itemid';

	$db_items = DBselect($sql);
	while($db_item = DBfetch($db_items)){
		
		$description = item_description($db_item['description'],$db_item['key_']);

		if(!empty($_REQUEST['select']) && !zbx_stristr($description, $_REQUEST['select']) ) continue;		

		$db_host = &$db_hosts[$db_item['hostid']];
		
		if(!isset($tab_rows[$db_host['hostid']])) $tab_rows[$db_host['hostid']] = array();
		$app_rows = &$tab_rows[$db_host['hostid']];

		$db_host['item_cnt']++;

		if(!uint_in_array(0,$_REQUEST['applications']) && !isset($show_all_apps)) continue;


		if(isset($db_item['lastclock']))
			$lastclock=zbx_date2str(S_DATE_FORMAT_YMDHMS,$db_item['lastclock']);
		else
			$lastclock = new CCol('-', 'center');

		$lastvalue=format_lastvalue($db_item);

		if( isset($db_item['lastvalue']) && isset($db_item['prevvalue']) &&
			($db_item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $db_item['value_type'] == ITEM_VALUE_TYPE_UINT64) &&
			($db_item['lastvalue']-$db_item['prevvalue'] != 0) )
		{
			if($db_item['lastvalue']-$db_item['prevvalue']<0){
				$change=convert_units($db_item['lastvalue']-$db_item['prevvalue'],$db_item['units']);
				$change=nbsp($change);
			}
			else{
				$change='+'.convert_units($db_item['lastvalue']-$db_item['prevvalue'],$db_item['units']);
				$change=nbsp($change);
			}
		}
		else{
			$change=new CCol('-','center');
		}
		
		if(($db_item['value_type']==ITEM_VALUE_TYPE_FLOAT) || ($db_item['value_type']==ITEM_VALUE_TYPE_UINT64)){
			$actions=new CLink(S_GRAPH,'history.php?action=showgraph&itemid='.$db_item['itemid'],'action');
		}
		else{
			$actions=new CLink(S_HISTORY,'history.php?action=showvalues&period=3600&itemid='.$db_item['itemid'],'action');
		}
		
		array_push($app_rows, new CRow(array(
			is_show_subnodes()?($item_cnt?SPACE:get_node_name_by_elid($db_item['itemid'])):null,
			$_REQUEST['hostid']?NULL:($db_host['item_cnt']?SPACE:$db_item['host']),
			str_repeat(SPACE, 6).$description,
			$lastclock,
			new CCol($lastvalue, $lastvalue == '-' ? 'center' : null),
			$change,
			$actions
			)));
	}
	unset($app_rows);
	unset($db_host);

	foreach($db_hosts as $hostid => $db_host){

		if(!isset($tab_rows[$hostid])) continue;
		$app_rows = $tab_rows[$hostid];

		if(uint_in_array(0,$_REQUEST['applications']) || isset($show_all_apps)){
			$url = '?close=1&applicationid=0'.
				url_param('groupid').url_param('hostid').
				url_param('applications').url_param('select');
			$link = new CLink(new CImg('images/general/opened.gif'),$url);
//			$link = new CLink(new CImg('images/general/opened.gif'),$url,null,"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');");				
		}
		else{
			$url = '?open=1&applicationid=0'.
				url_param('groupid').url_param('hostid').
				url_param('applications').url_param('select');
			$link = new CLink(new CImg('images/general/closed.gif'),$url);
//			$link = new CLink(new CImg('images/general/closed.gif'),$url,null,"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."','".$url."');");
		}

		$col = new CCol(array($link,SPACE,bold(S_MINUS_OTHER_MINUS),SPACE.'('.$db_host['item_cnt'].SPACE.S_ITEMS.')'));
		$col->SetColSpan(5);
		
		$table->AddRow(array(
				get_node_name_by_elid($db_host['hostid']),
				($_REQUEST['hostid'] > 0)?NULL:$db_host['host'],
				$col
				));	

		foreach($app_rows as $row)
			$table->AddRow($row);
	}

	$p_elements[] = $table;

/*
// Refresh tab
	$refresh_tab = array(
		array('id'	=> ZBX_PAGE_MAIN_HAT,
				'interval' 	=> $USER_DETAILS['refresh'],
				'url'	=>	zbx_empty($_SERVER['QUERY_STRING'])?'':'?'.$_SERVER['QUERY_STRING'],
			)
	);
//*/

	$url = '?fullscreen='.($_REQUEST['fullscreen']?'0':'1');

	$fs_icon = new CDiv(SPACE,'fullscreen');
	$fs_icon->AddOption('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
	$fs_icon->AddAction('onclick',new CScript("javascript: document.location = '".$url."';"));


	$latest_hat = create_hat(
			S_LATEST_DATA_BIG,
			$p_elements,//new CSpan(S_LOADING_P,'textcolorstyles'),
			array($fs_icon),
			ZBX_PAGE_MAIN_HAT,
			get_profile('web.latest.hats.hat_latest.state',1)
	);

	$latest_hat->Show();
	
//	add_refresh_objects($refresh_tab);
?>
<?php
include_once 'include/page_footer.php';
?>
