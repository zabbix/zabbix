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
	$page['scripts'] = array('prototype.js','url.js','gmenu.js','scrollbar.js','sbox.js','sbinit.js');
?>
<?php
	if(isset($_REQUEST['fullscreen']))
	{
		define('ZBX_PAGE_NO_MENU', 1);
	}

	if(isset($_REQUEST['graphid']) && $_REQUEST['graphid'] > 0 && !isset($_REQUEST['period']) && !isset($_REQUEST['stime']))
	{
		define('ZBX_PAGE_DO_REFRESH', 1);
	}
	
include_once 'include/page_header.php';

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
		'action'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'go'"),NULL),
		'reset'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'reset'"),NULL),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,		IN("1"),NULL)
	);

	check_fields($fields);
?>
<?php
	if(isset($_REQUEST["graphid"]) && !isset($_REQUEST["hostid"]))
	{
		$_REQUEST["groupid"] = $_REQUEST["hostid"] = 0;
	}

	$_REQUEST["graphid"] = get_request("graphid", get_profile("web.charts.graphid", 0));
	
	$_REQUEST["keep"] = get_request("keep", 1); // possible excessed REQUEST variable !!!

	$_REQUEST["period"] = get_request("period",get_profile("web.graph[".$_REQUEST["graphid"]."].period", ZBX_PERIOD_DEFAULT));
	$effectiveperiod = navigation_bar_calc();
	
	$options = array("allow_all_hosts","monitored_hosts","with_items");//, "always_select_first_host");
	if(!$ZBX_WITH_SUBNODES)	array_push($options,"only_current_node");
	
	validate_group_with_host(PERM_READ_ONLY,$options);

	if($_REQUEST['graphid'] > 0 && $_REQUEST['hostid'] > 0)
	{
		$result=DBselect('SELECT g.graphid '.
					' FROM graphs g, graphs_items gi, items i'.
					' WHERE i.hostid='.$_REQUEST['hostid'].
						' AND gi.itemid = i.itemid'.
						' AND gi.graphid = g.graphid '.
						' AND g.graphid='.$_REQUEST['graphid']);

		if(!DBfetch($result)) $_REQUEST['graphid'] = 0;
	}
?>
<?php
	if($_REQUEST['graphid'] > 0 && $_REQUEST['period'] >= ZBX_MIN_PERIOD)
	{
		update_profile('web.graph['.$_REQUEST['graphid'].'].period',$_REQUEST['period']);
	}

	update_profile('web.charts.graphid',$_REQUEST['graphid']);
?>
<?php
	$h1 = array(S_GRAPHS_BIG.SPACE."/".SPACE);
	
	$availiable_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST, null, null, get_current_nodeid());
	$availiable_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_LIST, null, null, get_current_nodeid());

	if($_REQUEST['graphid'] > 0 && DBfetch(DBselect('select distinct graphid from graphs where graphid='.$_REQUEST['graphid'])))
	{
		if(! ($row = DBfetch(DBselect(' SELECT distinct h.host, g.name '.
					' FROM hosts h, items i, graphs_items gi, graphs g '.
					' WHERE h.status='.HOST_STATUS_MONITORED.
						' AND h.hostid=i.hostid '.
						' AND g.graphid='.$_REQUEST['graphid'].
						' AND i.itemid=gi.itemid '.
						' AND gi.graphid=g.graphid'.
//						' AND h.hostid NOT IN ('.$denyed_hosts.') '.
						' AND h.hostid IN ('.$availiable_hosts.') '.
						' AND '.DBin_node('g.graphid').
					' ORDER BY h.host, g.name'
				))))
		{
			update_profile('web.charts.graphid',0);
			access_deny();
		}
		array_push($h1, new CLink($row['name'], '?graphid='.$_REQUEST['graphid'].(isset($_REQUEST['fullscreen']) ? '&fullscreen=1' : '')));
	}
	else
	{
		$_REQUEST['graphid'] = 0;
		array_push($h1, S_SELECT_GRAPH_TO_DISPLAY);
	}

	$r_form = new CForm();
	$r_form->SetMethod('get');
	if(isset($_REQUEST['fullscreen']))
		$r_form->AddVar('fullscreen', 1);

	$cmbGroup = new CComboBox('groupid',$_REQUEST['groupid'],'submit()');
	$cmbHosts = new CComboBox('hostid',$_REQUEST['hostid'],'submit()');
	$cmbGraph = new CComboBox('graphid',$_REQUEST['graphid'],'submit()');

	$cmbGroup->AddItem(0,S_ALL_SMALL);
	$cmbHosts->AddItem(0,S_ALL_SMALL);
	$cmbGraph->AddItem(0,S_SELECT_GRAPH_DOT_DOT_DOT);
	
// Selecting first group,host,graph if it's one of kind ;)
	if($_REQUEST['groupid'] == 0){
		$sql = 'SELECT COUNT(DISTINCT g.groupid) as grpcount, MAX(g.groupid) as groupid'.
				' FROM groups g, hosts_groups hg, hosts h, items i, graphs_items gi '.
				' WHERE g.groupid in ('.$availiable_groups.') '.
					' AND hg.groupid=g.groupid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND h.hostid=i.hostid '.
					' AND hg.hostid=h.hostid '.
					' AND i.itemid=gi.itemid ';
		if($cnt_row = DBfetch(DBselect($sql))){
			if($cnt_row['grpcount'] == 1){
				$_REQUEST['groupid'] = $cnt_row['groupid'];
				$cmbGroup->SetValue($_REQUEST['groupid']);
			}
		}
	}
	
	if($_REQUEST['hostid'] == 0){
		if($_REQUEST['groupid'] > 0){
			$sql = 'SELECT COUNT(DISTINCT h.hostid) as hstcount, MAX(h.hostid) as hostid '.
				' FROM hosts h,items i,hosts_groups hg, graphs_items gi '.
				' WHERE h.status='.HOST_STATUS_MONITORED.
					' AND h.hostid=i.hostid '.
					' AND hg.groupid='.$_REQUEST['groupid'].
					' AND hg.hostid=h.hostid '.
					' AND h.hostid IN ('.$availiable_hosts.') '.
					' AND i.itemid=gi.itemid';
		}
		else{
			$sql = 'SELECT COUNT(DISTINCT h.hostid) as hstcount, MAX(h.hostid) as hostid '.
				' FROM hosts h,items i, graphs_items gi '.
				' WHERE h.status='.HOST_STATUS_MONITORED.
					' AND i.status='.ITEM_STATUS_ACTIVE.
					' AND h.hostid=i.hostid'.
					' AND h.hostid IN ('.$availiable_hosts.') '.
					' AND i.itemid=gi.itemid';
		}

		if($cnt_row = DBfetch(DBselect($sql))){
			if($cnt_row['hstcount'] == 1){
				$_REQUEST['hostid'] = $cnt_row['hostid'];
				$cmbHosts->SetValue($_REQUEST['hostid']);
			}
		}
	}
	if($_REQUEST['graphid'] == 0){
		if($_REQUEST['hostid'] > 0){
			$sql = 'SELECT COUNT(DISTINCT g.graphid) as grphcount, MAX(g.graphid) as graphid '.
				' FROM graphs g,graphs_items gi,items i'.
				' WHERE i.itemid=gi.itemid '.
					' AND g.graphid=gi.graphid '.
					' AND i.hostid='.$_REQUEST['hostid'].
					' AND '.DBin_node('g.graphid').
					' AND i.hostid IN ('.$availiable_hosts.') ';
		}
		elseif ($_REQUEST['groupid'] > 0){
			$sql = 'SELECT COUNT(DISTINCT g.graphid) as grphcount, MAX(g.graphid) as graphid '.
				' FROM graphs g,graphs_items gi,items i,hosts_groups hg,hosts h'.
				' WHERE i.itemid=gi.itemid '.
					' AND g.graphid=gi.graphid '.
					' AND i.hostid=hg.hostid '.
					' AND hg.groupid='.$_REQUEST['groupid'].
					' AND i.hostid=h.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND '.DBin_node('g.graphid').
					' AND h.hostid IN ('.$availiable_hosts.') ';
		}
		else{
			$sql = 'SELECT COUNT(DISTINCT g.graphid) as grphcount, MAX(g.graphid) as graphid '.
				' FROM graphs g,graphs_items gi,items i,hosts h'.
				' WHERE i.itemid=gi.itemid '.
					' AND g.graphid=gi.graphid '.
					' AND i.hostid=h.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND '.DBin_node('g.graphid').
					' AND h.hostid IN ('.$availiable_hosts.') ';
		}
		if($cnt_row = DBfetch(DBselect($sql))){
			if($cnt_row['grphcount'] == 1){
				$_REQUEST['graphid'] = $cnt_row['graphid'];
				$cmbGraph->SetValue($_REQUEST['graphid']);
			}
		}
	}
	
//----------------------------------------------

	
	$result=DBselect('SELECT DISTINCT g.groupid, g.name '.
				' FROM groups g, hosts_groups hg, hosts h, items i, graphs_items gi '.
				' WHERE g.groupid in ('.$availiable_groups.') '.
					' AND hg.groupid=g.groupid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND h.hostid=i.hostid '.
					' AND hg.hostid=h.hostid '.
					' AND i.itemid=gi.itemid '.
				' ORDER BY g.name');
	while($row=DBfetch($result))
	{
		$cmbGroup->AddItem(
				$row['groupid'],
				get_node_name_by_elid($row['groupid']).$row["name"]
				);
	}
	
	$r_form->AddItem(array(S_GROUP.SPACE,$cmbGroup));
	
	if($_REQUEST['groupid'] > 0){
		$sql = ' SELECT distinct h.hostid,h.host '.
			' FROM hosts h,items i,hosts_groups hg, graphs_items gi '.
			' WHERE h.status='.HOST_STATUS_MONITORED.
				' AND h.hostid=i.hostid '.
				' AND hg.groupid='.$_REQUEST['groupid'].
				' AND hg.hostid=h.hostid '.
				' AND h.hostid IN ('.$availiable_hosts.') '.
				' AND i.itemid=gi.itemid'.
			' ORDER BY h.host';
	}
	else{
		$sql = 'SELECT distinct h.hostid,h.host '.
			' FROM hosts h,items i, graphs_items gi '.
			' WHERE h.status='.HOST_STATUS_MONITORED.
				' AND i.status='.ITEM_STATUS_ACTIVE.
				' AND h.hostid=i.hostid'.
				' AND h.hostid IN ('.$availiable_hosts.') '.
				' AND i.itemid=gi.itemid'.
			' ORDER BY h.host';
	}
	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		$cmbHosts->AddItem(
				$row['hostid'],
				get_node_name_by_elid($row['hostid']).$row['host']
				);
	}

	$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));

	if($_REQUEST['hostid'] > 0){
		$sql = 'SELECT distinct g.graphid,g.name '.
			' FROM graphs g,graphs_items gi,items i'.
			' WHERE i.itemid=gi.itemid '.
				' AND g.graphid=gi.graphid '.
				' AND i.hostid='.$_REQUEST['hostid'].
				' AND '.DBin_node('g.graphid').
				' AND i.hostid IN ('.$availiable_hosts.') '.
			' ORDER BY g.name';
	}
	elseif ($_REQUEST['groupid'] > 0){
		$sql = 'SELECT distinct g.graphid,g.name '.
			' FROM graphs g,graphs_items gi,items i,hosts_groups hg,hosts h'.
			' WHERE i.itemid=gi.itemid '.
				' AND g.graphid=gi.graphid '.
				' AND i.hostid=hg.hostid '.
				' AND hg.groupid='.$_REQUEST['groupid'].
				' AND i.hostid=h.hostid '.
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND '.DBin_node('g.graphid').
				' AND h.hostid IN ('.$availiable_hosts.') '.
			' ORDER BY g.name';
	}
	else{
		$sql = 'SELECT DISTINCT g.graphid,g.name '.
			' FROM graphs g,graphs_items gi,items i,hosts h'.
			' WHERE i.itemid=gi.itemid '.
				' AND g.graphid=gi.graphid '.
				' AND i.hostid=h.hostid '.
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND '.DBin_node('g.graphid').
				' AND h.hostid IN ('.$availiable_hosts.') '.
			' ORDER BY g.name';
	}

	$result = DBselect($sql);
	while($row=DBfetch($result))
	{
		$cmbGraph->AddItem(
				$row['graphid'],
				get_node_name_by_elid($row['graphid']).$row['name']
				);
	}
	
	$r_form->AddItem(array(SPACE.S_GRAPH.SPACE,$cmbGraph));
	
	show_table_header($h1, $r_form);
?>
<?php
	$table = new CTableInfo('...','chart');

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
				var ZBX_G_WIDTH;
				if(window.innerWidth) ZBX_G_WIDTH=window.innerWidth; 
				else ZBX_G_WIDTH=document.body.clientWidth;

				ZBX_G_WIDTH-='.($shiftXleft+$shiftXright+10).';
				document.write(\'<img id="'.$dom_graph_id.'" src="chart6.php?graphid='.$_REQUEST['graphid'].url_param('stime').
				'&period='.$effectiveperiod.'&width=\'+ZBX_G_WIDTH+\'" /><br /><br />\');
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
				ZBX_G_WIDTH-= parseInt('.($shiftXleft+$shiftXright).'+parseInt((SF)?27:27));
				//ZBX_G_WIDTH-= '.($shiftXleft+$shiftXright+27).';
				
				document.write(\'<img id="'.$dom_graph_id.'" src="chart2.php?graphid='.$_REQUEST['graphid'].url_param('stime').
				'&period='.$effectiveperiod.'&width=\'+ZBX_G_WIDTH+\'" /><br />\');
				-->
				</script>'."\n";
		}
		
		$table->AddRow(new CScript($row));
	}
	$table->Show();
	echo SBR;
	
	if($_REQUEST['graphid'] > 0)
	{
// NAV BAR
		$stime = get_min_itemclock_by_graphid($_REQUEST['graphid']);
		$stime = (is_null($stime))?0:$stime;
		$bstime = time()-$effectiveperiod;
		if(isset($_REQUEST['stime'])){
			$bstime = $_REQUEST['stime'];
			$bstime = mktime(substr($bstime,8,2),substr($bstime,10,2),0,substr($bstime,4,2),substr($bstime,6,2),substr($bstime,0,4));
		}
		
		$script = 	'scrollinit(0,0,0,'.$effectiveperiod.','.$stime.',0,'.$bstime.');
					showgraphmenu("graph");
					graph_zoom_init("'.$dom_graph_id.'",'.$bstime.','.$effectiveperiod.',ZBX_G_WIDTH,'.$graph_height.');
					';
						
		zbx_add_post_js($script);
//		navigation_bar('charts.php',array('groupid','hostid','graphid'));
//-------------
	}
	
?>
<?php

include_once 'include/page_footer.php';

?>

