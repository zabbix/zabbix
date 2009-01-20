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
/*
 * Function: graph_item_type2str
 *
 * Description:
 *     Represent integer value of graph item type into the string
 *
 * Author:
 *     Eugene Grigorjev 
 *
 */
	function graph_item_type2str($type,$count=null){
		switch($type){
			case GRAPH_ITEM_SUM:	
				$type = S_GRAPH_SUM;
				break;				
			case GRAPH_ITEM_AGGREGATED:	
				$type = S_AGGREGATED.(isset($count) ? '('.$count.')' : '');	
				break;
			case GRAPH_ITEM_SIMPLE:
			default:			
				$type = S_SIMPLE;	
				break;
		}
	return $type;
	}
	
/*
 * Function: graph_item_drawtypes
 *
 * Description:
 *     Return available drawing types for graph item
 *
 * Author:
 *     Eugene Grigorjev 
 *
 */
	function graph_item_drawtypes(){
		return array(
				GRAPH_ITEM_DRAWTYPE_LINE,
				GRAPH_ITEM_DRAWTYPE_FILLED_REGION,
				GRAPH_ITEM_DRAWTYPE_BOLD_LINE,
				GRAPH_ITEM_DRAWTYPE_DOT,
				GRAPH_ITEM_DRAWTYPE_DASHED_LINE
			    );
	}
	
/*
 * Function: graph_item_drawtype2str
 *
 * Description:
 *     Represent integer value of graph item drawing type into the string
 *
 * Author:
 *     Eugene Grigorjev 
 *
 */
	function graph_item_drawtype2str($drawtype,$type=null){
		if($type == GRAPH_ITEM_AGGREGATED) return '-';

		switch($drawtype){
			case GRAPH_ITEM_DRAWTYPE_LINE:			$drawtype = S_LINE;				break;
			case GRAPH_ITEM_DRAWTYPE_FILLED_REGION:	$drawtype = S_FILLED_REGION;	break;
			case GRAPH_ITEM_DRAWTYPE_BOLD_LINE:		$drawtype = S_BOLD_LINE;		break;
			case GRAPH_ITEM_DRAWTYPE_DOT:			$drawtype = S_DOT;				break;
			case GRAPH_ITEM_DRAWTYPE_DASHED_LINE:	$drawtype = S_DASHED_LINE;		break;
			default: $drawtype = S_UNKNOWN;		break;
		}
	return $drawtype;
	}
/*
 * Function: graph_item_calc_fnc2str
 *
 * Description:
 *     Represent integer value of calculation function into the string
 *
 * Author:
 *     Eugene Grigorjev 
 *
 */
	function graph_item_calc_fnc2str($calc_fnc, $type=null){
		if($type == GRAPH_ITEM_AGGREGATED) return '-';
		
		switch($calc_fnc){
			case 0:					$calc_fnc = S_COUNT; 	       break;		
			case CALC_FNC_ALL:      $calc_fnc = S_ALL_SMALL;        break;
			case CALC_FNC_MIN:      $calc_fnc = S_MIN_SMALL;        break;
			case CALC_FNC_MAX:      $calc_fnc = S_MAX_SMALL;        break;
			case CALC_FNC_LST:		$calc_fnc = S_LST_SMALL;		break;
			case CALC_FNC_AVG:
			default:		$calc_fnc = S_AVG_SMALL;        break;
		}
	return $calc_fnc;
	}
	
	function get_graph_by_gitemid($gitemid){
		$db_graphs = DBselect('SELECT distinct g.* '.
						' FROM graphs g, graphs_items gi '.
						' WHERE g.graphid=gi.graphid '.
							' AND gi.gitemid='.$gitemid);
			
	return DBfetch($db_graphs);
	}

	function get_graphs_by_hostid($hostid){
		$sql = 'SELECT distinct g.* '.
				' FROM graphs g, graphs_items gi, items i '.
				' WHERE g.graphid=gi.graphid '.
					' AND gi.itemid=i.itemid '.
					' AND i.hostid='.$hostid;
	return DBselect($sql);
	}

	function get_realhosts_by_graphid($graphid){
		$graph = get_graph_by_graphid($graphid);
		if($graph["templateid"] != 0)
			return get_realhosts_by_graphid($graph["templateid"]);

	return get_hosts_by_graphid($graphid);
	}

	function get_hosts_by_graphid($graphid){	
		return DBselect('SELECT distinct h.* '.
							' FROM graphs_items gi, items i, hosts h'.
							' WHERE h.hostid=i.hostid '.
								' AND gi.itemid=i.itemid '.
								' AND gi.graphid='.$graphid);
	}

	function get_graphitems_by_graphid($graphid){
		return DBselect('SELECT * '.
					' FROM graphs_items '.
					' WHERE graphid='.$graphid.
					' ORDER BY itemid,drawtype,sortorder,color,yaxisside'); 
	}

/*
 * Function: graph_accessible
 *
 * Description:
 *     Checks if graph is accessible to USER
 *
 * Author:
 *     Aly
 *
 */		
	function graph_accessible($graphid){
		global $USER_DETAILS;
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_ONLY, PERM_RES_IDS_ARRAY,get_current_nodeid(true));
		
		$sql = 	'SELECT g.graphid '.
				' FROM graphs g, graphs_items gi, items i '.
				' WHERE g.graphid='.$graphid.
					' AND g.graphid=gi.graphid '.
					' AND i.itemid=gi.itemid '.
					' AND '.DBcondition('i.hostid',$available_hosts,true);

		if(DBfetch(DBselect($sql,1))){
			return false;
		}
	return true;
	}


/*
 * Function: get_accessible_graphs
 *
 * Description:
 *     returns string of accessible graphid's
 *
 * Author:
 *     Aly
 *
 */		
	function get_accessible_graphs($perm,$perm_res=null,$nodeid=null,$hostid=null,$cache=1){
		global $USER_DETAILS;
		static $available_graphs;
		
		if(is_null($perm_res)) $perm_res = PERM_RES_IDS_ARRAY;
		$nodeid_str =(is_array($nodeid))?implode('',$nodeid):strval($nodeid);
		$hostid_str =(is_array($hostid))?implode('',$hostid):strval($hostid);
		
		if($cache && isset($available_graphs[$perm][$perm_res][$nodeid_str][$hostid_str])){
			return $available_graphs[$perm][$perm_res][$nodeid_str][$hostid_str];
		}
		
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, $perm, PERM_RES_IDS_ARRAY, $nodeid);

		$denied_graphs = array();
		$result = array();
		
		$sql = 	'SELECT DISTINCT g.graphid '.
				' FROM graphs g, graphs_items gi, items i '.
				' WHERE g.graphid=gi.graphid '.
					(!empty($hostid)?' AND i.hostid='.$hostid:'').
					' AND i.itemid=gi.itemid '.
					' AND '.DBcondition('i.hostid',$available_hosts, true);

		$db_graphs = DBselect($sql);
		while($graph = DBfetch($db_graphs)){
			$denied_graphs[] = $graph['graphid'];
		}

		$sql = 	'SELECT DISTINCT g.graphid '.
				' FROM graphs g, graphs_items gi, items i '.
				' WHERE g.graphid=gi.graphid '.
					(!empty($hostid)?' AND i.hostid='.$hostid:'').
					' AND i.itemid=gi.itemid '.
					' AND i.status='.ITEM_STATUS_ACTIVE.
					(!empty($denied_graphs)?' AND '.DBcondition('g.graphid',$denied_graphs,true):'');
		$db_graphs = DBselect($sql);
		while($graph = DBfetch($db_graphs)){
			$result[$graph['graphid']] = $graph['graphid'];
		}
		
		if(PERM_RES_STRING_LINE == $perm_res){
			if(count($result) == 0) 
				$result = '-1';
			else
				$result = implode(',',$result);
		}

		$available_graphs[$perm][$perm_res][$nodeid_str][$hostid_str] = $result;

	return $result;
	}

/*
 * Function: get_min_itemclock_by_graphid
 *
 * Description:
 *     Return the time of the 1st apearance of items included in graph in trends
 *
 * Author:
 *     Aly
 *
 */	
	function get_min_itemclock_by_graphid($graphid){
		$min = 0;
		$row = DBfetch(DBselect('SELECT MIN(t.clock) as clock '.
						' FROM graphs_items gi, trends t '.
						' WHERE gi.graphid='.$graphid.
						  ' AND t.itemid = gi.itemid')); 
						  
		if(!empty($row) && $row && $row['clock']) 
			$min = $row['clock'];

		$row = DBfetch(DBselect('SELECT MIN(t.clock) as clock '.
						' FROM graphs_items gi, trends_uint t '.
						' WHERE gi.graphid='.$graphid.
						  ' AND t.itemid = gi.itemid')); 
						  
		if(!empty($row) && $row && $row['clock']) 
			$min = $min == 0 ? $row['clock'] : min($min, $row['clock']);

	return $min;
	}

/*
 * Function: get_min_itemclock_by_itemid
 *
 * Description:
 *     Return the time of the 1st apearance of item in trends
 *
 * Author:
 *     Aly
 *
 */	
	function get_min_itemclock_by_itemid($itemid){
		$min = 0;
		$row = DBfetch(DBselect('SELECT MIN(t.clock) as clock '.
						' FROM trends t '.
						' WHERE t.itemid='.$itemid)); 
						  
		if(!empty($row) && $row && $row['clock']) 
			$min = $row['clock'];

		$row = DBfetch(DBselect('SELECT MIN(t.clock) as clock '.
						' FROM trends_uint t '.
						' WHERE t.itemid='.$itemid)); 
						  
		if(!empty($row) && $row && $row['clock']) 
			$min = $min == 0 ? $row['clock'] : min($min, $row['clock']);

	return $min;
	}
	
// Show History Graph
	function show_history($itemid,$from,$stime,$period){
		$till=date(S_DATE_FORMAT_YMDHMS,time(NULL)-$from*3600);   
		
		show_table_header(S_TILL.SPACE.$till.' ( '.zbx_date2age($stime,$stime+$period).' )');

		$td = new CCol(get_js_sizeable_graph('graph','chart.php?itemid='.$itemid.
				url_param($from,false,'from').
				url_param($stime,false,'stime').
				url_param($period,false,'period')));
		$td->addOption('align','center');
		
		$tr = new CRow($td);
		$tr->addOption('bgcolor','#dddddd');
		
		$table = new CTable();
		$table->addOption('width','100%');
		$table->addOption('bgcolor','#cccccc');
		$table->addOption('cellspacing','1');
		$table->addOption('cellpadding','3');
		
		$table->addRow($tr);
		
		$table->show();
	}

	function get_graphitem_by_gitemid($gitemid){
		$result=DBselect("SELECT * FROM graphs_items WHERE gitemid=$gitemid");
		$row=DBfetch($result);
		if($row){
			return	$row;
		}
		error("No graph item with gitemid=[$gitemid]");
		
	return	$result;
	}

	function get_graphitem_by_itemid($itemid){
		$result = DBfetch(DBselect('SELECT * FROM graphs_items WHERE itemid='.$itemid));
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		return	$result;
	}

	function get_graph_by_graphid($graphid){

		$result=DBselect("SELECT * FROM graphs WHERE graphid=$graphid");
		$row=DBfetch($result);
		if($row){
			return	$row;
		}
		error("No graph with graphid=[$graphid]");
		return	false;
	}

	function get_graphs_by_templateid($templateids){
		zbx_value2array($templateids);
	return DBselect('SELECT * FROM graphs WHERE '.DBcondition('templateid',$templateids));
	}

/*
 * Function: get_same_graphitems_for_host
 *
 * Description:
 *     Replace items for specified host
 *
 * Author:
 *     Eugene Grigorjev 
 *
 * Comments: !!! Don't forget sync code with C !!!
 * Only PHP:
 *		$error= true : rise Error if item doesn't exists(error generated), false: special processing (NO error generated)
 */
	function get_same_graphitems_for_host($gitems, $dest_hostid, $error=true){
		$result = array();

		foreach($gitems as $gitem){
			$sql = 'SELECT src.itemid '.
					' FROM items src, items dest '.
					' WHERE dest.itemid='.$gitem['itemid'].
						' AND src.key_=dest.key_ '.
						' AND src.hostid='.$dest_hostid;
			$db_item = DBfetch(DBselect($sql));
			if (!$db_item && $error){
				$item = get_item_by_itemid($gitem['itemid']);
				$host = get_host_by_hostid($dest_hostid);
				error('Missed key "'.$item['key_'].'" for host "'.$host['host'].'"');
				return false;
			}
			else if(!$db_item){
				continue;
//				$gitem['itemid'] = 0;
			}
			else{
				$gitem['itemid'] = $db_item['itemid'];
			}

			$result[] = $gitem;
		}

		return $result;
	}
	
        /*
         * Function: add_graph
         *
         * Description:
         *     Add graph without items and recursion for templates
         *
         * Author:
         *     Eugene Grigorjev 
         *
         * Comments: !!! Don't forget sync code with C !!!
         *
         */
	function add_graph($name,$width,$height,$ymin_type,$ymax_type,$yaxismin,$yaxismax,$ymin_itemid,$ymax_itemid,$showworkperiod,$showtriggers,$graphtype,$legend,$graph3d,$percent_left,$percent_right,$templateid=0)
	{
		$graphid = get_dbid("graphs","graphid");

		$result=DBexecute('INSERT INTO graphs '.
			' (graphid,name,width,height,ymin_type,ymax_type,yaxismin,yaxismax,ymin_itemid,ymax_itemid,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right) '.
			" VALUES ($graphid,".zbx_dbstr($name).",$width,$height,$ymin_type,$ymax_type,$yaxismin,$yaxismax,$ymin_itemid,$ymax_itemid,".
			" $templateid,$showworkperiod,$showtriggers,$graphtype,$legend,$graph3d,$percent_left,$percent_right)");

		return ( $result ? $graphid : $result);
	}

        /*
         * Function: add_graph_with_items
         *
         * Description:
         *     Add graph with items and recursion for templates
         *
         * Author:
         *     Eugene Grigorjev 
         *
         * Comments: !!! Don't forget sync code with C !!!
         *
         */
	function add_graph_with_items($name,$width,$height,$ymin_type,$ymax_type,$yaxismin,$yaxismax,$ymin_itemid,$ymax_itemid,$showworkperiod,$showtriggers,$graphtype,$legend,$graph3d,$percent_left,$percent_right,$gitems=array(),$templateid=0)
	{
		$result = false;

		if(!is_array($gitems) || count($gitems)<1){
			error('Missed items for graph "'.$name.'"');
			return $result;
		}

		/* check items for template graph */
		unset($new_host_is_template);
		$host_list = array();
		$itemid = array(0);

		foreach($gitems as $gitem)
			$itemid[] = $gitem['itemid'];

		$db_item_hosts = DBselect('SELECT DISTINCT h.hostid,h.host,h.status '.
							' FROM items i, hosts h '.
							' WHERE h.hostid=i.hostid '.
								' AND i.itemid in ('.implode(',', $itemid).')');
								
		while($db_item_host = DBfetch($db_item_hosts)){
			$host_list[] = '"'.$db_item_host['host'].'"';

			if(HOST_STATUS_TEMPLATE ==  $db_item_host['status'])
				$new_host_is_template = true;
		}

		if(isset($new_host_is_template) && count($host_list)>1){
			error('Graph "'.$name.'" with template host can not contain items from other hosts.');
			return $result;
		}

		if($graphid = add_graph($name,$width,$height,$ymin_type,$ymax_type,$yaxismin,$yaxismax,$ymin_itemid,$ymax_itemid,$showworkperiod,$showtriggers,$graphtype,$legend,$graph3d,$percent_left,$percent_right,$templateid)){
			$result = true;
			foreach($gitems as $gitem){
				if (!$result = add_item_to_graph(
					$graphid,
					$gitem['itemid'],
					$gitem['color'],
					$gitem['drawtype'],
					$gitem['sortorder'],
					$gitem['yaxisside'],
					$gitem['calc_fnc'],
					$gitem['type'],
					$gitem['periods_cnt']))
				{
					break;
				}
			}
		}

		if ( $result ){
			info('Graph "'.$name.'" added to hosts '.implode(',',$host_list));

			/* add graphs for child hosts */
			$tmp_hosts = get_hosts_by_graphid($graphid);
			$host = DBfetch($tmp_hosts);

			$chd_hosts = get_hosts_by_templateid($host['hostid']);
			while($chd_host = DBfetch($chd_hosts)){
				copy_graph_to_host($graphid, $chd_host['hostid'], false);
			}
		}

		if ( !$result && $graphid ){
			delete_graph($graphid);
			$graphid = false;
		}

	return $graphid;
	}

        /*
         * Function: update_graph
         *
         * Description:
         *     Update graph without items and recursion for template
         *
         * Author:
         *     Eugene Grigorjev 
         *
         * Comments: !!! Don't forget sync code with C !!!
         *
         */
	function update_graph($graphid,$name,$width,$height,$ymin_type,$ymax_type,$yaxismin,$yaxismax,$ymin_itemid,$ymax_itemid,$showworkperiod,$showtriggers,$graphtype,$legend,$graph3d,$percent_left,$percent_right,$templateid=0){

		$g_graph = get_graph_by_graphid($graphid);

		$sql = 'UPDATE graphs SET '.
				'name='.zbx_dbstr($name).','.
				'width='.$width.','.
				'height='.$height.','.
				'ymin_type='.$ymin_type.','.
				'ymax_type='.$ymax_type.','.
				'yaxismin='.$yaxismin.','.
				'yaxismax='.$yaxismax.','.
				'ymin_itemid='.$ymin_itemid.','.
				'ymax_itemid='.$ymax_itemid.','.
				'templateid='.$templateid.','.
				'show_work_period='.$showworkperiod.','.
				'show_triggers='.$showtriggers.','.
				'graphtype='.$graphtype.','.
				'show_legend='.$legend.','.
				'show_3d='.$graph3d.','.
				'percent_left='.$percent_left.','.
				'percent_right='.$percent_right.
			' WHERE graphid='.$graphid;
			
		if($result = DBexecute($sql)){
			if($g_graph['graphtype'] != $graphtype && $graphtype == GRAPH_TYPE_STACKED){
				$result = DBexecute('UPDATE graphs_items SET calc_fnc='.CALC_FNC_AVG.',drawtype=1,type='.GRAPH_ITEM_SIMPLE.
					' WHERE graphid='.$graphid);
			}
		}
		return $result;
	}
	
        /*
         * Function: update_graph_with_items
         *
         * Description:
         *     Update graph with items and recursion for template
         *
         * Author:
         *     Eugene Grigorjev 
         *
         * Comments: !!! Don't forget sync code with C !!!
         *
         */
	function update_graph_with_items($graphid,$name,$width,$height,$ymin_type,$ymax_type,$yaxismin,$yaxismax,$ymin_itemid,$ymax_itemid,$showworkperiod,$showtriggers,$graphtype,$legend,$graph3d,$percent_left,$percent_right,$gitems=array(),$templateid=0)
	{
		$result = false;

		if(!is_array($gitems) || count($gitems) < 1){
			error('Missed items for graph "'.$name.'"');
			return $result;
		}

		/* check items for template graph */
		$tmp_hosts = get_hosts_by_graphid($graphid);
		$host = DBfetch($tmp_hosts);
		if($host["status"] == HOST_STATUS_TEMPLATE ){
			unset($new_hostid);
			$itemid = array(0);

			foreach($gitems as $gitem)
				$itemid[] = $gitem['itemid'];

			$db_item_hosts = DBselect('SELECT DISTINCT hostid from items where itemid in ('.implode(',', $itemid).')');
			while($db_item = DBfetch($db_item_hosts)){
				if ( isset($new_hostid) ){
					error('Can not use multiple host items for template graph "'.$name.'"');
					return $result;
				}

				$new_hostid = $db_item['hostid'];
			}

			if ( (bccomp($host['hostid'] ,$new_hostid ) != 0)){
				error('You must use items only from host "'.$host['host'].'" for template graph "'.$name.'"');
				return $result;
			}
		}

		/* firstly update child graphs */
		$chd_graphs = get_graphs_by_templateid($graphid);
		while($chd_graph = DBfetch($chd_graphs)){
			$tmp_hosts = get_hosts_by_graphid($chd_graph['graphid']);
			$chd_host = DBfetch($tmp_hosts);
			
			if(!$new_gitems = get_same_graphitems_for_host($gitems, $chd_host['hostid'])){ /* skip host with missed items */
				error('Can not update graph "'.$name.'" for host "'.$chd_host['host'].'"');
				return $result;
			}
		
			if (!$result = update_graph_with_items($chd_graph['graphid'], $name, $width, $height,
				$ymin_type,$ymax_type,$yaxismin,$yaxismax,$ymin_itemid,$ymax_itemid,
				$showworkperiod, $showtriggers, $graphtype, $legend, $graph3d, $percent_left, $percent_right, $new_gitems, $graphid))
			{
				return $result;
			}
		}

		DBexecute('DELETE FROM graphs_items WHERE graphid='.$graphid);

		foreach($gitems as $gitem){
			if ( ! ($result = add_item_to_graph(
					$graphid,
					$gitem['itemid'],
					$gitem['color'],
					$gitem['drawtype'],
					$gitem['sortorder'],
					$gitem['yaxisside'],
					$gitem['calc_fnc'],
					$gitem['type'],
					$gitem['periods_cnt'])) )
			{
				return $result;
			}
		}

		if ($result = update_graph($graphid,$name,$width,$height,$ymin_type,$ymax_type,$yaxismin,$yaxismax,$ymin_itemid,$ymax_itemid,$showworkperiod,
						$showtriggers,$graphtype,$legend,$graph3d,$percent_left,$percent_right,$templateid))
		{
			$host_list = array();
			$db_hosts = get_hosts_by_graphid($graphid);
			while($db_host = DBfetch($db_hosts)){
				$host_list[] = '"'.$db_host["host"].'"';
			}

			info('Graph "'.$name.'" updated for hosts '.implode(',',$host_list));
		}

		return $result;
	}
	
/*
 * Function: delete_graph
 *
 * Description:
 *     De;ete graph with templates
 *
 * Author:
 *     Eugene Grigorjev 
 *
 * Comments: !!! Don't forget sync code with C !!!
 *
 */
	function delete_graph($graphids){
		zbx_value2array($graphids);
		
		$result = true;
		
		$graphs = array();
		$host_lists = array();
		foreach($graphids as $id => $graphid){
			$graphs[] = get_graph_by_graphid($graphid);
	
			$host_list[$graphid] = array();
			$db_hosts = get_hosts_by_graphid($graphid);
			while($db_host = DBfetch($db_hosts)){
				$host_list[$graphid] = '"'.$db_host['host'].'"';
			}
		}		
// firstly remove child graphs 
		$del_chd_graphs = array();
		$chd_graphs = get_graphs_by_templateid($graphids);
		while($chd_graph = DBfetch($chd_graphs)){ /* recursion */
			$del_chd_graphs[$chd_graph['graphid']] = $chd_graph['graphid'];
		}
		if(!empty($del_chd_graphs)){
			$result &= delete_graph($del_chd_graphs);
		}
		
		DBexecute('DELETE FROM screens_items WHERE '.DBcondition('resourceid',$graphids).' AND resourcetype='.SCREEN_RESOURCE_GRAPH);

// delete graph 
		DBexecute('DELETE FROM graphs_items WHERE '.DBcondition('graphid',$graphids));
		DBexecute("DELETE FROM profiles WHERE idx='web.favorite.graphids' AND source='graphid' AND ".DBcondition('value_id',$graphids));
		
		$result = DBexecute('DELETE FROM graphs WHERE '.DBcondition('graphid',$graphids));
		if($result){
			foreach($graphs as $graphid => $graph){
				if(isset($host_list[$graphid]))
					info('Graph "'.$graph['name'].'" deleted from hosts '.implode(',',$host_list[$graphid]));
			}
		}

	return $result;
	}

/*
 * Function: cmp_graphitems
 *
 * Description:
 *     Compare two graph items
 *
 * Author:
 *     Eugene Grigorjev 
 *
 * Comments: !!! Don't forget sync code with C !!!
 *
 */
	function	cmp_graphitems(&$gitem1, &$gitem2){
		if($gitem1["drawtype"]	!= $gitem2["drawtype"])		return 1;
		if($gitem1["sortorder"] != $gitem2["sortorder"])	return 2;
		if($gitem1["color"]	!= $gitem2["color"])		return 3;
		if($gitem1["yaxisside"] != $gitem2["yaxisside"])	return 4;

		$item1 = get_item_by_itemid($gitem1["itemid"]);
		$item2 = get_item_by_itemid($gitem2["itemid"]);

		if($item1["key_"] != $item2["key_"])			return 5;

		return 0;
	}

/*
 * Function: add_item_to_graph
 *
 * Description:
 *     Add item to graph
 *
 * Author:
 *     Eugene Grigorjev 
 *
 * Comments: !!! Don't forget sync code with C !!!
 *
 */
	function	add_item_to_graph($graphid,$itemid,$color,$drawtype,$sortorder,$yaxisside,$calc_fnc,$type,$periods_cnt)
	{
		$gitemid = get_dbid('graphs_items','gitemid');

		$result = DBexecute('insert into graphs_items'.
			' (gitemid,graphid,itemid,color,drawtype,sortorder,yaxisside,calc_fnc,type,periods_cnt)'.
			' values ('.$gitemid.','.$graphid.','.$itemid.','.zbx_dbstr($color).','.$drawtype.','.
			$sortorder.','.$yaxisside.','.$calc_fnc.','.$type.','.$periods_cnt.')');

		return ( $result ? $gitemid : $result );
	}
	
/*
 * Function: delete_template_graphs
 *
 * Description:
 *     Delete template graph from specified host
 *
 * Author:
 *     Eugene Grigorjev 
 *
 * Comments: !!! Don't forget sync code with C !!!
 *
 */
	function delete_template_graphs($hostid, $templateids = null /* array format 'arr[id]=name' */, $unlink_mode = false){
		zbx_value2array($templateids);
		
		$db_graphs = get_graphs_by_hostid($hostid);
		while($db_graph = DBfetch($db_graphs)){
			if($db_graph['templateid'] == 0)
				continue;

			if(!is_null($templateids)){
				$tmp_hhosts = get_hosts_by_graphid($db_graph['templateid']);
				$tmp_host = DBfetch($tmp_hhosts);

				if( !uint_in_array($tmp_host['hostid'], $templateids)) continue;
			}

			if($unlink_mode){
				if(DBexecute('UPDATE graphs SET templateid=0 WHERE graphid='.$db_graph['graphid'])){
					info('Graph "'.$db_graph['name'].'" unlinked');
				}	
			}
			else{
				delete_graph($db_graph['graphid']);
			}
		}
	}
	
        /*
         * Function: copy_template_graphs
         *
         * Description:
         *     Copy all graphs to the specified host
         *
         * Author:
         *     Eugene Grigorjev 
         *
         * Comments: !!! Don't forget sync code with C !!!
         *
         */
	function copy_template_graphs($hostid, $templateid = null /* array format 'arr[key]=id' */, $copy_mode = false){
		if($templateid == null){
			$templateid = get_templates_by_hostid($hostid);
			$templateid = array_keys($templateid);
		}
		
		if(is_array($templateid)){
			foreach($templateid as $key => $id)
				copy_template_graphs($hostid, $id, $copy_mode); // attention recursion
			return;
		}

		$db_graphs = get_graphs_by_hostid($templateid);

		while($db_graph = DBfetch($db_graphs)){
			copy_graph_to_host($db_graph["graphid"], $hostid, $copy_mode);
		}
	}

/*
 * Function: copy_graph_to_host
 *
 * Description:
 *     Copy specified graph to the specified host
 *
 * Author:
 *     Eugene Grigorjev 
 *
 * Comments: !!! Don't forget sync code with C !!!
 *
 */
	function copy_graph_to_host($graphid, $hostid, $copy_mode = false){
		$result = false;

		$gitems = array();

		$db_graph_items = get_graphitems_by_graphid($graphid);
		while( $db_gitem = DBfetch($db_graph_items) ){
			$gitems[] = array(
				'itemid'	=> $db_gitem['itemid'],
				'color'		=> $db_gitem['color'],
				'drawtype'	=> $db_gitem['drawtype'],
				'sortorder'	=> $db_gitem['sortorder'],
				'yaxisside'	=> $db_gitem['yaxisside'],
				'calc_fnc'	=> $db_gitem['calc_fnc'],
				'type'		=> $db_gitem['type'],
				'periods_cnt'	=> $db_gitem['periods_cnt']
				);
		}

		$db_graph = get_graph_by_graphid($graphid);

		if($new_gitems = get_same_graphitems_for_host($gitems, $hostid)){
			unset($chd_graphid);
			
			$chd_graphs = get_graphs_by_hostid($hostid);
			while( !isset($chd_graphid) && $chd_graph = DBfetch($chd_graphs)){ 
/* compare graphs */
				if ( $chd_graph['templateid'] != 0 ) continue;

				unset($equal);
				$chd_gitems = get_graphitems_by_graphid($chd_graph["graphid"]);
				while($chd_gitem = DBfetch($chd_gitems)){
					unset($gitem_equal);
					
					foreach($new_gitems as $new_gitem){
						if(cmp_graphitems($new_gitem, $chd_gitem))	continue;

						$gitem_equal = true;
						break;
					}

					if(!isset($gitem_equal)){
						unset($equal);
						break;
					}

					/* founded equal graph item */
					if(!isset($equal))$equal = 0;

					$equal++;
				}

				if(isset($equal) && (count($new_gitems) == $equal)){ 
/* founded equal graph */
					$chd_graphid = $chd_graph['graphid'];
					break;
				}
			}

			if(isset($chd_graphid)){
				$result = update_graph_with_items($chd_graphid, $db_graph['name'], $db_graph['width'], $db_graph['height'],
					$db_graph['ymin_type'], $db_graph['ymax_type'], $db_graph['yaxismin'], $db_graph['yaxismax'],
					$db_graph['ymin_itemid'], $db_graph['ymax_itemid'],
					$db_graph['show_work_period'], $db_graph['show_triggers'], $db_graph['graphtype'],$db_graph['show_legend'], 
					$db_graph['show_3d'], $db_graph['percent_left'], $db_graph['percent_right'], $new_gitems, ($copy_mode ? 0: $db_graph['graphid']));
			}
			else{
				$result = add_graph_with_items($db_graph['name'], $db_graph['width'], $db_graph['height'],
					$db_graph['ymin_type'], $db_graph['ymax_type'], $db_graph['yaxismin'], $db_graph['yaxismax'],
					$db_graph['ymin_itemid'], $db_graph['ymax_itemid'],
					$db_graph['show_work_period'], $db_graph['show_triggers'], $db_graph['graphtype'],$db_graph['show_legend'], 
					$db_graph['show_3d'], $db_graph['percent_left'], $db_graph['percent_right'], $new_gitems, ($copy_mode ? 0: $db_graph['graphid']));
			}
		}
		else{
			$host = get_host_by_hostid($hostid);
			info('Skipped coping of graph "'.$db_graph["name"].'" to host "'.$host['host'].'"');
		}

	return $result;
	}

	function navigation_bar_calc(){
		if(!isset($_REQUEST['period']))	$_REQUEST['period']=ZBX_PERIOD_DEFAULT;
		if(!isset($_REQUEST['from']))	$_REQUEST['from']=0;
		if(!isset($_REQUEST['stime']))	$_REQUEST['stime']=null;

		if($_REQUEST['period']<ZBX_MIN_PERIOD){
			show_message(S_WARNING.'. '.S_TIME_PERIOD.SPACE.S_MIN_VALUE_SMALL.': '.ZBX_MIN_PERIOD.' ('.(int)(ZBX_MIN_PERIOD/3600).'h)');
			$_REQUEST['period'] = ZBX_MIN_PERIOD;
			
		}
		else if($_REQUEST['period'] > ZBX_MAX_PERIOD){
			show_message(S_WARNING.'. '.S_TIME_PERIOD.SPACE.S_MAX_VALUE_SMALL.': '.ZBX_MAX_PERIOD.' ('.(int)(ZBX_MAX_PERIOD/86400).'d)');
			$_REQUEST['period'] = ZBX_MAX_PERIOD;			
		}

		if(isset($_REQUEST['stime'])){
			$bstime = $_REQUEST['stime'];
			$time = mktime(substr($bstime,8,2),substr($bstime,10,2),0,substr($bstime,4,2),substr($bstime,6,2),substr($bstime,0,4));
			if(($time+$_REQUEST['period']) > time()) unset($_REQUEST['stime']);
		}
		
	return $_REQUEST['period'];
	}

	
/*
 * Function: 
 *		make_array_from_gitems
 *
 * Description:
 *     Creates array with items params for preapare_url function
 *
 * Author:
 *     Aly
 *
 * Comments
 *	
 */	
	function make_url_from_gitems($gitems){

		$gurl=array();
		$ifields = array(
						'itemid'	=> 1,
						'drawtype'	=> 1,
						'sortorder'	=> 1,
						'color'		=> 1,
						'yaxisside'	=> 1,
						'calc_fnc'	=> 1,
						'type'		=> 1,
						'periods_cnt'=>1
					);

		foreach($gitems as $gitem){
			foreach($gitem as $name => $value){
				if(isset($ifields[$name])){
					$gurl['items['.$gitem['itemid'].']['.$name.']']=$value;
				}
			}
		}
		
	return prepare_url($gurl);
	}
	
/*
 * Function: 
 *		make_array_from_graphid
 *
 * Description:
 *     Creates array with graph params for preapare_url function
 *
 * Author:
 *     Aly
 *
 * Comments
 *	$full= false: for screens(WITHOUT width && height), true=all params
 */	
	function make_url_from_graphid($graphid,$full=false){

		$gurl=array();
		if($full){
			$gparams = array();
		}
		else{
			$gparams = array(
						'height'=> 1,
						'width'	=> 1
					);
		}
		
		$graph=get_graph_by_graphid($graphid);
		if($graph){
			foreach($graph as $name => $value){
				if(!is_numeric($name) && !isset($gparams[$name])) $gurl[$name]=$value;
			}
		}

		$url = prepare_url($gurl);
		if(!empty($url)){
			$url=((($gurl['graphtype']==GRAPH_TYPE_PIE) || ($gurl['graphtype']==GRAPH_TYPE_EXPLODED))?'chart7.php?':'chart3.php?').trim($url,'&');
		}
	return $url;
	}
	
//Author:	Aly
	function get_next_color($palettetype=0){
		static $prev_color = array('dark'=>true, 'color'=>0, 'grad'=>0);
		
		switch($palettetype){
			case 1: $grad = array(200,150,255,100,50,0); break;
			case 2: $grad = array(100,50,200,150,250,0); break;
			case 0:
			default: $grad = array(255,200,150,100,50,0); break;
		}
		
		$set_grad = $grad[$prev_color['grad']];
		
//		$r = $g = $b = $prev_color['dark']?0:250;
		$r = $g = $b = (100<$set_grad)?0:255;
		
		switch($prev_color['color']){
			case 0: $r = $set_grad; break;
			case 1: $g = $set_grad; break;
			case 2:	$b = $set_grad;	break;
			case 3:	$r = $b = $set_grad; break;
			case 4: $g = $b = $set_grad; break;
			case 5: $r = $g = $set_grad; break;
			case 6: $r = $g = $b = $set_grad; break;
		}
//SDI($prev_color);
		$prev_color['dark'] = $prev_color['dark']?false:true;
		if($prev_color['color'] == 6) $prev_color['grad'] = ($prev_color['grad']+1) % 6 ;
		$prev_color['color'] = ($prev_color['color']+1) % 7;
		
	return array($r,$g,$b);
	}
	
//Author:	Aly
	function get_next_palette($palette=0,$palettetype=0){
		static $prev_color = array(0,0,0,0);
		
		switch($palette){
			case 0: $palettes = array(array(150,0,0), array(0,100,150), array(170,180,180), array(152,100,0), 
									array(130,0,150), array(0,0,150), array(200,100,50),
									array(250,40,40), array(50,150,150), array(100,150,0));
				break;
			case 1: $palettes = array(array(0,100,150), array(153,0,30), array(100,150,0),
									array(130,0,150), array(0,0,100), array(200,100,50), array(152,100,0),
									array(0,100,0), array(170,180,180), array(50,150,150));
				break;
			case 2: $palettes = array( array(170,180,180), array(152,100,0), array(50,200,200),
									array(153,0,30), array(0,0,100), array(100,150,0), array(130,0,150),
									array(0,100,150), array(200,100,50), array(0,100,0),);
				break;
			case 3:
			default: 
				return get_next_color($palettetype);
		}

		if(isset($palettes[$prev_color[$palette]]) )
			$result = $palettes[$prev_color[$palette]];
		else
			return get_next_color($palettetype);
				
		switch($palettetype){
			case 0: $diff = 0; break;
			case 1:	$diff = -50; break;
			case 2:	$diff = 50; break;
		}
		
		foreach($result as $n => $color){
			if(($color + $diff) < 0) $result[$n] = 0;
			else if(($color + $diff) > 255) $result[$n] = 255;
			else $result[$n] += $diff;
		}
		
		$prev_color[$palette]++;

	return $result;
	}
?>
