<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
require_once('include/graphs.inc.php');

$page['title'] = 'S_CUSTOM_GRAPHS';
$page['file'] = 'charts.php';
$page['hist_arg'] = array('hostid','groupid','graphid');
$page['scripts'] = array('scriptaculous.js?load=effects,dragdrop','class.calendar.js','gtlc.js');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_REFRESH', 1);

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>		array(T_ZBX_INT, O_OPT,	 P_SYS,		DB_ID,NULL),
		'hostid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,		DB_ID,NULL),
		'graphid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,		DB_ID,NULL),
		'from'=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		'period'=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	null,NULL),
		'stime'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	NULL,NULL),
		'action'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'go','add','remove'"),NULL),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,		IN('0,1'),NULL),

//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),

		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'add','remove'"),NULL)
	);

	check_fields($fields);
?>
<?php

	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.charts.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		if('hat' == $_REQUEST['favobj']){
			CProfile::update('web.charts.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}

		if('timeline' == $_REQUEST['favobj']){
			if(isset($_REQUEST['graphid']) && isset($_REQUEST['period'])){
				navigation_bar_calc('web.graph',$_REQUEST['graphid'], true);
			}
		}

		if(str_in_array($_REQUEST['favobj'],array('itemid','graphid'))){
			$result = false;
			if('add' == $_REQUEST['action']){
				$result = add2favorites('web.favorite.graphids', $_REQUEST['favid'], $_REQUEST['favobj']);
				if($result){
					print('$("addrm_fav").title = "'.S_REMOVE_FROM.' '.S_FAVOURITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){rm4favorites("graphid","'.$_REQUEST['favid'].'",0);}'."\n");
				}
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.graphids',$_REQUEST['favid'],$_REQUEST['favobj']);

				if($result){
					print('$("addrm_fav").title = "'.S_ADD_TO.' '.S_FAVOURITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){ add2favorites("graphid","'.$_REQUEST['favid'].'");}'."\n");
				}
			}

			if((PAGE_TYPE_JS == $page['type']) && $result){
				print('switchElementsClass("addrm_fav","iconminus","iconplus");');
			}
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
?>
<?php
	$_REQUEST['graphid'] = get_request('graphid', CProfile::get('web.charts.graphid', 0));
	if(!in_node($_REQUEST['graphid'])) $_REQUEST['graphid'] = 0;

	if($_REQUEST['graphid']>0){
		$sql_from = '';
		$sql_where = '';
		if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid'] > 0)){
			$sql_where.= ' AND hg.groupid='.$_REQUEST['groupid'];
		}

		if(isset($_REQUEST['hostid']) && ($_REQUEST['hostid'] > 0)){
			$sql_where.= ' AND hg.hostid='.$_REQUEST['hostid'];
		}

		$sql = 'SELECT DISTINCT hg.groupid, hg.hostid '.
				' FROM hosts_groups hg, hosts h, graphs g, graphs_items gi, items i '.
				' WHERE g.graphid='.$_REQUEST['graphid'].
					' AND gi.graphid=g.graphid '.
					' AND i.itemid=gi.itemid '.
					' AND hg.hostid=i.hostid '.
					$sql_where.
					' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';

		if($host_group = DBfetch(DBselect($sql,1))){
			if(!isset($_REQUEST['groupid']) || !isset($_REQUEST['hostid'])){
				$_REQUEST['groupid'] = $host_group['groupid'];
				$_REQUEST['hostid'] = $host_group['hostid'];
			}
			else if((($_REQUEST['groupid']!=$host_group['groupid']) && ($_REQUEST['groupid'] > 0)) ||
					(($_REQUEST['hostid']!=$host_group['hostid']) && ($_REQUEST['hostid'] > 0)))
			{
				$_REQUEST['graphid'] = 0;
			}
		}
		else{
			$_REQUEST['graphid'] = 0;
		}
	}

	$effectiveperiod = navigation_bar_calc('web.graph',$_REQUEST['graphid']);

	CProfile::update('web.charts.graphid',$_REQUEST['graphid'], PROFILE_TYPE_ID);

	$h1 = array();

	$options = array('allow_all_hosts','monitored_hosts','with_graphs');
	if(!$ZBX_WITH_ALL_NODES)	array_push($options,'only_current_node');

	$params = array();
	foreach($options as $option) $params[$option] = 1;

	$PAGE_GROUPS = get_viewed_groups(PERM_READ_ONLY, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_ONLY, $PAGE_GROUPS['selected'], $params);

	validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);
//SDI($_REQUEST['groupid'].' : '.$_REQUEST['hostid']);

	$available_groups= $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];

	if(empty($PAGE_GROUPS['groupids']) || empty($PAGE_HOSTS['hostids'])){
		$_REQUEST['graphid'] = 0;
	}

	if($_REQUEST['graphid']>0){
		$options = array(
			'graphids' => $_REQUEST['graphid'],
			'output' => API_OUTPUT_EXTEND,
			'nodeids' => get_current_nodeid(true)
		);
		$db_data = CGraph::get($options);
		if(empty($db_data)){
			CProfile::update('web.charts.graphid',0,PROFILE_TYPE_ID);
			access_deny();
		}

		$db_data = reset($db_data);
		array_push($h1, $db_data['name']);
	}
	else{
		$_REQUEST['graphid'] = 0;
		array_push($h1, S_SELECT_GRAPH_TO_DISPLAY);
	}

	$charts_wdgt = new CWidget('hat_charts');
	
	$scroll_div = new CDiv();
	$scroll_div->setAttribute('id','scrollbar_cntr');
	$charts_wdgt->addFlicker($scroll_div, CProfile::get('web.charts.filter.state',1));

// HEADER

	$r_form = new CForm();
	$r_form->setMethod('get');

	$r_form->addVar('fullscreen', $_REQUEST['fullscreen']);

	$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
	$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');

	foreach($PAGE_GROUPS['groups'] as $groupid => $name){
		$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid, null, ': ').$name);
	}
	foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
		$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid, null, ': ').$name);
	}

	$r_form->addItem(array(S_GROUP.SPACE,$cmbGroups));
	$r_form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));

	$cmbGraphs = new CComboBox('graphid',$_REQUEST['graphid'],'submit()');
	$cmbGraphs->addItem(0,S_SELECT_GRAPH_DOT_DOT_DOT);

	$options = array(
		'extendoutput' => 1,
		'templated' => 0
	);

// Filtering
	if(($PAGE_HOSTS['selected'] > 0) || empty($PAGE_HOSTS['hostids'])){
		$options['hostids'] = $PAGE_HOSTS['selected'];
	}
	else if(($PAGE_GROUPS['selected'] > 0) && !empty($PAGE_HOSTS['hostids'])){
		$options['hostids'] = $PAGE_HOSTS['hostids'];
	}
	else if(($PAGE_GROUPS['selected'] > 0) || empty($PAGE_GROUPS['groupids'])){
		$options['groupids'] = $PAGE_GROUPS['selected'];
	}

	$db_graphs = CGraph::get($options);
	order_result($db_graphs, 'name');
	foreach($db_graphs as $num => $db_graph){
		$cmbGraphs->addItem($db_graph['graphid'], get_node_name_by_elid($db_graph['graphid'], null, ': ').$db_graph['name']);
	}
	
	$r_form->addItem(array(SPACE.S_GRAPH.SPACE,$cmbGraphs));

//	show_table_header(S_GRAPHS_BIG, $r_form);
//---------------------------------------------
?>
<?php
	$table = new CTableInfo('...','chart');
//	$table->setAttribute('border',1);

	if($_REQUEST['graphid'] > 0){
		$dom_graph_id = 'graph';
		$graphDims = getGraphDims($_REQUEST['graphid']);

		if(($graphDims['graphtype'] == GRAPH_TYPE_PIE) || ($graphDims['graphtype'] == GRAPH_TYPE_EXPLODED)){
			$loadSBox = 0;
			$scrollWidthByImage = 0;
			$containerid = 'graph_cont1';
			$src = 'chart6.php?graphid='.$_REQUEST['graphid'];
		}
		else{
			$loadSBox = 1;
			$scrollWidthByImage = 1;
			$containerid = 'graph_cont1';
			$src = 'chart2.php?graphid='.$_REQUEST['graphid'];
		}

		$graph_cont = new CCol();
		$graph_cont->setAttribute('id', $containerid);
		$table->addRow($graph_cont);
	}

	$icon = null;
	$fs_icon = null;
	$rst_icon = null;
	if($_REQUEST['graphid'] > 0){
		if(infavorites('web.favorite.graphids',$_REQUEST['graphid'],'graphid')){
			$icon = new CDiv(SPACE,'iconminus');
			$icon->setAttribute('title',S_REMOVE_FROM.' '.S_FAVOURITES);
			$icon->addAction('onclick',new CJSscript("javascript: rm4favorites('graphid','".$_REQUEST['graphid']."',0);"));
		}
		else{
			$icon = new CDiv(SPACE,'iconplus');
			$icon->setAttribute('title',S_ADD_TO.' '.S_FAVOURITES);
			$icon->addAction('onclick',new CJSscript("javascript: add2favorites('graphid','".$_REQUEST['graphid']."');"));
		}
		$icon->setAttribute('id','addrm_fav');

		$url = '?graphid='.$_REQUEST['graphid'].($_REQUEST['fullscreen']?'':'&fullscreen=1');

		$fs_icon = new CDiv(SPACE,'fullscreen');
		$fs_icon->setAttribute('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
		$fs_icon->addAction('onclick',new CJSscript("javascript: document.location = '".$url."';"));

		$rst_icon = new CDiv(SPACE,'iconreset');
		$rst_icon->setAttribute('title',S_RESET);
		$rst_icon->addAction('onclick',new CJSscript("javascript: timeControl.objectReset('".$_REQUEST['graphid']."');"));
	}

	$charts_wdgt->addPageHeader(S_GRAPHS_BIG,array($icon,$rst_icon,$fs_icon));

	$charts_wdgt->addHeader($h1,$r_form);
	$charts_wdgt->addItem(BR());
	$charts_wdgt->addItem($table);

	$charts_wdgt->show();

	if($_REQUEST['graphid'] > 0){
// NAV BAR
		$timeline = array();
		$timeline['period'] = $effectiveperiod;
		$timeline['starttime'] = get_min_itemclock_by_graphid($_REQUEST['graphid']);

		if(isset($_REQUEST['stime'])){
			$bstime = $_REQUEST['stime'];
			$timeline['usertime'] = mktime(substr($bstime,8,2),substr($bstime,10,2),0,substr($bstime,4,2),substr($bstime,6,2),substr($bstime,0,4));
			$timeline['usertime'] += $timeline['period'];
		}

		$objData = array(
			'id' => $_REQUEST['graphid'],
			'domid' => $dom_graph_id,
			'containerid' => $containerid,
			'src' => $src,
			'objDims' => $graphDims,
			'loadSBox' => $loadSBox,
			'loadImage' => 1,
			'loadScroll' => 1,
			'scrollWidthByImage' => $scrollWidthByImage,
			'dynamic' => 1
		);

		zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
		zbx_add_post_js('timeControl.processObjects();');
//-------------
	}
?>
<?php

include_once('include/page_footer.php');

?>
