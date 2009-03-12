<?php
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
	require_once 'include/graphs.inc.php';

	$page['title'] = 'S_CUSTOM_GRAPHS';
	$page['file'] = 'charts.php';
	$page['hist_arg'] = array('hostid','grouid','graphid','period','dec','inc','left','right','stime');
	$page['scripts'] = array('gmenu.js','scrollbar.js','sbox.js','sbinit.js');
	
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	
//	define('ZBX_PAGE_DO_REFRESH', 1);

include_once 'include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>		array(T_ZBX_INT, O_OPT,	 P_SYS,		DB_ID,NULL),
		'hostid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,		DB_ID,NULL),
		'graphid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,		DB_ID,NULL),
		'from'=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		'period'=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	null,NULL),
		'stime'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	NULL,NULL),
		'action'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'go','add','remove'"),NULL),
		'reset'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'reset'"),NULL),
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
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.charts.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		else if(str_in_array($_REQUEST['favobj'],array('itemid','graphid'))){
			$result = false;
			if('add' == $_REQUEST['action']){
				$result = add2favorites('web.favorite.graphids',$_REQUEST['favid'],$_REQUEST['favobj']);
				if($result){
					print('$("addrm_fav").title = "'.S_REMOVE_FROM.' '.S_FAVORITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){rm4favorites("graphid","'.$_REQUEST['favid'].'",0);}'."\n");
				}
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.graphids',$_REQUEST['favid'],ZBX_FAVORITES_ALL,$_REQUEST['favobj']);
				
				if($result){
					print('$("addrm_fav").title = "'.S_ADD_TO.' '.S_FAVORITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){ add2favorites("graphid","'.$_REQUEST['favid'].'");}'."\n");
				}
			}

			if((PAGE_TYPE_JS == $page['type']) && $result){
				print('switchElementsClass("addrm_fav","iconminus","iconplus");');
			}
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
	
	$_REQUEST['graphid'] = get_request('graphid', get_profile('web.charts.graphid', 0, PROFILE_TYPE_ID));
	if(!in_node($_REQUEST['graphid'])) $_REQUEST['graphid'] = 0;

//	$_REQUEST['stime'] =	get_request('stime',get_profile('web.graph.stime', null, PROFILE_TYPE_STR, $_REQUEST['graphid']));
	$_REQUEST['period'] =	get_request('period',get_profile('web.graph.period', ZBX_PERIOD_DEFAULT, PROFILE_TYPE_INT, $_REQUEST['graphid']));
	
	$effectiveperiod = navigation_bar_calc();
		
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

	if($_REQUEST['graphid']>0){
		if(isset($_REQUEST['stime'])) 
			update_profile('web.graph.stime',$_REQUEST['stime'], PROFILE_TYPE_STR, $_REQUEST['graphid']);

		if($_REQUEST['period'] >= ZBX_MIN_PERIOD)
			update_profile('web.graph.period',$_REQUEST['period'],PROFILE_TYPE_INT,$_REQUEST['graphid']);			
	}

	update_profile('web.charts.graphid',$_REQUEST['graphid']);

	$h1 = array();
	
	$options = array('allow_all_hosts','monitored_hosts','with_graphs');
	if(!$ZBX_WITH_SUBNODES)	array_push($options,'only_current_node');
		
	$params = array();
	foreach($options as $option) $params[$option] = 1;
	$PAGE_GROUPS = get_viewed_groups(PERM_READ_ONLY, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_ONLY, $PAGE_GROUPS['selected'], $params);
	validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);
//SDI($_REQUEST['groupid'].' : '.$_REQUEST['hostid']);		

	$available_groups= $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];
	
	$available_graphs = get_accessible_graphs(PERM_READ_LIST, $available_hosts, PERM_RES_IDS_ARRAY, get_current_nodeid(true));

	if(($_REQUEST['graphid']>0) && ($row=DBfetch(DBselect('SELECT DISTINCT graphid, name FROM graphs WHERE graphid='.$_REQUEST['graphid'])))){
		if(!graph_accessible($_REQUEST['graphid'])){
			update_profile('web.charts.graphid',0);
			access_deny();
		}
		array_push($h1, $row['name']);
	}
	else{
		$_REQUEST['graphid'] = 0;
		array_push($h1, S_SELECT_GRAPH_TO_DISPLAY);
	}

	$p_elements = array();

	$r_form = new CForm();
	$r_form->setMethod('get');
	
	$r_form->addVar('fullscreen', $_REQUEST['fullscreen']);

	$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
	$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');
	
	foreach($PAGE_GROUPS['groups'] as $groupid => $name){
		$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid).$name);
	}
	foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
		$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid).$name);
	}

	$r_form->addItem(array(S_GROUP.SPACE,$cmbGroups));
	$r_form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));	
		
//---------------------------------------------_
	$cmbGraphs = new CComboBox('graphid',$_REQUEST['graphid'],'submit()');
	$cmbGraphs->addItem(0,S_SELECT_GRAPH_DOT_DOT_DOT);
	
	$sql_from = '';
	$sql_where = '';
	if($_REQUEST['groupid'] > 0){
		$sql_from .= ',hosts_groups hg ';
		$sql_where.= ' AND hg.hostid=h.hostid AND hg.groupid='.$_REQUEST['groupid'];
	}
	if($_REQUEST['hostid'] > 0){
		$sql_where.= ' AND h.hostid='.$_REQUEST['hostid'];
	}
	
	$sql = 'SELECT DISTINCT g.graphid,g.name '.
		' FROM graphs g,graphs_items gi,items i,hosts h'.$sql_from.
		' WHERE gi.graphid=g.graphid '.
			' AND i.itemid=gi.itemid '.
			' AND h.hostid=i.hostid '.
			' AND h.status='.HOST_STATUS_MONITORED.
			$sql_where.
			
			' AND '.DBin_node('g.graphid').
			' AND '.DBcondition('g.graphid',$available_graphs).
		' ORDER BY g.name';

	$result = DBselect($sql);
	while($row=DBfetch($result)){
		$cmbGraphs->addItem(
				$row['graphid'],
				get_node_name_by_elid($row['graphid']).$row['name']
				);
	}
	
	$r_form->addItem(array(SPACE.S_GRAPH.SPACE,$cmbGraphs));
	
	$p_elements[] = get_table_header($h1, $r_form);
?>
<?php
	$table = new CTableInfo('...','chart');
//	$table->AddOption('border',1);
	
	if($_REQUEST['graphid'] > 0){
		$graphtype = GRAPH_TYPE_NORMAL;
		$yaxis = 0;

// ZOOM featers
		$sql = 'SELECT MAX(g.graphtype) as graphtype, MIN(gi.yaxisside) as yaxissidel, MAX(gi.yaxisside) as yaxissider, MAX(g.height) as height'.
				' FROM graphs g, graphs_items gi '.
				' WHERE g.graphid='.$_REQUEST['graphid'].
					' AND gi.graphid=g.graphid ';

		$res = Dbselect($sql);
		while($graph=DBfetch($res)){
			$graphtype = $graph['graphtype'];
			$graph_height = $graph['height'];
			$yaxis = $graph['yaxissider'];
			$yaxis = ($graph['yaxissidel'] == $yaxis)?($yaxis):(2);
		}
		if($yaxis == 2){
			$shiftXleft = 60;
			$shiftXright = 60;
		}
		else if($yaxis == 0){
			$shiftXleft = 60;
			$shiftXright = 20;
		}
		else{
			$shiftXleft = 10;
			$shiftXright = 60;
		}
//-------------

		$dom_graph_id = 'graph';
		
		if(($graphtype == GRAPH_TYPE_PIE) || ($graphtype == GRAPH_TYPE_EXPLODED)){
			$row = 	"\n".'<script language="javascript" type="text/javascript">
				<!--
				document.write(\'<img id="'.$dom_graph_id.'" src="chart6.php?graphid='.$_REQUEST['graphid'].url_param('stime').
				'&period='.$effectiveperiod.'" /><br />\');
				-->
				</script>'."\n";
		}
		else{
			$row = 	"\n".'<script language="javascript" type="text/javascript">
				<!--
				A_SBOX["'.$dom_graph_id.'"] = new Object;
				A_SBOX["'.$dom_graph_id.'"].shiftT = 17;
				A_SBOX["'.$dom_graph_id.'"].shiftL = '.$shiftXleft.';
				
				var ZBX_G_WIDTH = get_bodywidth();
				if(!is_number(ZBX_G_WIDTH)) ZBX_G_WIDTH = 900;
				
				ZBX_G_WIDTH-= parseInt('.($shiftXleft+$shiftXright).'+parseInt((SF)?27:27));
				
				document.write(\'<img id="'.$dom_graph_id.'" src="chart2.php?graphid='.$_REQUEST['graphid'].url_param('stime').
				'&period='.$effectiveperiod.'&width=\'+ZBX_G_WIDTH+\'" /><br />\');
				-->
				</script>'."\n";
		}
		
		$table->addRow(new CScript($row));
	}
	
	$p_elements[] = $table;
	
	$icon = null;
	$fs_icon = null;
	$rst_icon = null;
	if($_REQUEST['graphid'] > 0){
		if(infavorites('web.favorite.graphids',$_REQUEST['graphid'],'graphid')){
			$icon = new CDiv(SPACE,'iconminus');
			$icon->addOption('title',S_REMOVE_FROM.' '.S_FAVORITES);
			$icon->addAction('onclick',new CScript("javascript: rm4favorites('graphid','".$_REQUEST['graphid']."',0);"));
		}
		else{
			$icon = new CDiv(SPACE,'iconplus');
			$icon->addOption('title',S_ADD_TO.' '.S_FAVORITES);
			$icon->addAction('onclick',new CScript("javascript: add2favorites('graphid','".$_REQUEST['graphid']."');"));
		}
		$icon->addOption('id','addrm_fav');
		
		$url = '?graphid='.$_REQUEST['graphid'].($_REQUEST['fullscreen']?'':'&fullscreen=1');

		$fs_icon = new CDiv(SPACE,'fullscreen');
		$fs_icon->addOption('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
		$fs_icon->addAction('onclick',new CScript("javascript: document.location = '".$url."';"));
		
		$rst_icon = new CDiv(SPACE,'iconreset');
		$rst_icon->addOption('title',S_RESET);
		$rst_icon->addAction('onclick',new CScript("javascript: graphload(SCROLL_BAR.dom_graphs, ".(time()+100000000).", 3600, false);"));
		
	}
	
	$charts_hat = create_hat(
			S_GRAPHS_BIG,
			$p_elements,
			array($icon,$rst_icon,$fs_icon),
			'hat_charts',
			get_profile('web.charts.hats.hat_charts.state',1)
	);

	$charts_hat->show();
	
	if($_REQUEST['graphid'] > 0){
// NAV BAR
		$stime = get_min_itemclock_by_graphid($_REQUEST['graphid']);
		$stime = (is_null($stime))?0:$stime;
		$bstime = time()-$effectiveperiod;
		if(isset($_REQUEST['stime'])){
			$bstime = $_REQUEST['stime'];
			$bstime = mktime(substr($bstime,8,2),substr($bstime,10,2),0,substr($bstime,4,2),substr($bstime,6,2),substr($bstime,0,4));
		}
		
		$script = 'scrollinit(0,'.$effectiveperiod.','.$stime.',0,'.$bstime.'); showgraphmenu("graph");';
		
		if(($graphtype == GRAPH_TYPE_NORMAL) || ($graphtype == GRAPH_TYPE_STACKED)){		
			$script.= 'graph_zoom_init("'.$dom_graph_id.'",'.$bstime.','.$effectiveperiod.',ZBX_G_WIDTH,'.$graph_height.',true);'; 
		}

		zbx_add_post_js($script);
		
		$scroll_div = new CDiv();
		$scroll_div->addOption('id','scroll_cntnr');
		$scroll_div->addOption('style','border: 0px #CC0000 solid; height: 25px; width: 800px;');
		$scroll_div->show();
//		navigation_bar('charts.php',array('groupid','hostid','graphid'));
//-------------
	}
	
?>
<?php

include_once 'include/page_footer.php';

?>

