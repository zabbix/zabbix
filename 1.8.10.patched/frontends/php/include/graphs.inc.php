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
				GRAPH_ITEM_DRAWTYPE_DASHED_LINE,
				GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE
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
			case GRAPH_ITEM_DRAWTYPE_LINE:			$drawtype = S_LINE;		break;
			case GRAPH_ITEM_DRAWTYPE_FILLED_REGION:		$drawtype = S_FILLED_REGION;	break;
			case GRAPH_ITEM_DRAWTYPE_BOLD_LINE:		$drawtype = S_BOLD_LINE;	break;
			case GRAPH_ITEM_DRAWTYPE_DOT:			$drawtype = S_DOT;		break;
			case GRAPH_ITEM_DRAWTYPE_DASHED_LINE:		$drawtype = S_DASHED_LINE;	break;
			case GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE:	$drawtype = S_GRADIENT_LINE;  break;
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
			case 0:			$calc_fnc = S_COUNT;		break;
			case CALC_FNC_ALL:	$calc_fnc = S_ALL_SMALL;	break;
			case CALC_FNC_MIN:	$calc_fnc = S_MIN_SMALL;	break;
			case CALC_FNC_MAX:	$calc_fnc = S_MAX_SMALL;	break;
			case CALC_FNC_LST:	$calc_fnc = S_LST_SMALL;	break;
			case CALC_FNC_AVG:
			default:		$calc_fnc = S_AVG_SMALL;	break;
		}
	return $calc_fnc;
	}

	function getGraphDims($graphid=null){
		$graphDims = array();

		$graphDims['shiftYtop'] = 35;
		if(is_null($graphid)){
			$graphDims['graphHeight'] = 200;
			$graphDims['graphtype'] = 0;

			if(GRAPH_YAXIS_SIDE_DEFAULT == 0){
				$graphDims['shiftXleft'] = 85;
				$graphDims['shiftXright'] = 30;
			}
			else{
				$graphDims['shiftXleft'] = 30;
				$graphDims['shiftXright'] = 85;
			}

			return $graphDims;
		}
// ZOOM featers

		$sql = 'SELECT MAX(g.graphtype) as graphtype, MIN(gi.yaxisside) as yaxissidel, MAX(gi.yaxisside) as yaxissider, MAX(g.height) as height'.
				' FROM graphs g, graphs_items gi '.
				' WHERE g.graphid='.$graphid.
					' AND gi.graphid=g.graphid ';
		$res = DBselect($sql);
		if($graph=DBfetch($res)){
			$graphDims['graphtype'] = $graph['graphtype'];
			$graphDims['graphHeight'] = $graph['height'];
			$yaxis = $graph['yaxissider'];
			$yaxis = ($graph['yaxissidel'] == $yaxis)?($yaxis):(2);

			$graphDims['yaxis'] = $yaxis;
		}

		if($yaxis == 2){
			$graphDims['shiftXleft'] = 85;
			$graphDims['shiftXright'] = 85;
		}
		else if($yaxis == 0){
			$graphDims['shiftXleft'] = 85;
			$graphDims['shiftXright'] = 30;
		}
		else{
			$graphDims['shiftXleft'] = 30;
			$graphDims['shiftXright'] = 85;
		}
//-------------

	return $graphDims;
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
 * Function: get_min_itemclock_by_graphid
 *
 * Description:
 *     Return the time of the 1st appearance of items included in graph in trends
 *
 * Author:
 *     Aly
 *
 * Comment:
 *	sql is split to many sql's to optimize search on history tables
 *
 */
	function get_min_itemclock_by_graphid($graphid){
		$itemids = array();
		$sql = 'SELECT DISTINCT gi.itemid '.
				' FROM graphs_items gi '.
				' WHERE gi.graphid='.$graphid;
		$res = DBselect($sql);
		while($item = DBfetch($res)){
			$itemids[$item['itemid']] = $item['itemid'];
		}

	return get_min_itemclock_by_itemid($itemids);
	}

/*
 * Function: get_min_itemclock_by_itemid
 *
 * Description:
 *     Return the time of the 1st appearance of item in trends
 *
 * Author:
 *     Aly
 *
 */
	function get_min_itemclock_by_itemid($itemids){
		zbx_value2array($itemids);
		$min = null;
		$result = time() - 86400*365;

		$items_by_type = array(
			ITEM_VALUE_TYPE_FLOAT => array(),
			ITEM_VALUE_TYPE_STR =>  array(),
			ITEM_VALUE_TYPE_LOG => array(),
			ITEM_VALUE_TYPE_UINT64 => array(),
			ITEM_VALUE_TYPE_TEXT => array()
		);

		$sql = 'SELECT i.itemid, i.value_type '.
				' FROM items i '.
				' WHERE '.DBcondition('i.itemid', $itemids);
		$db_result = DBselect($sql);

		while($item = DBfetch($db_result)) {
			$items_by_type[$item['value_type']][$item['itemid']] = $item['itemid'];
		}

// data for ITEM_VALUE_TYPE_FLOAT and ITEM_VALUE_TYPE_UINT64 can be stored in trends tables or history table
// get max trends and history values for such type items to find out in what tables to look for data
		$sql_from = 'history';
		$sql_from_num = '';
		if(!empty($items_by_type[ITEM_VALUE_TYPE_FLOAT]) || !empty($items_by_type[ITEM_VALUE_TYPE_UINT64])) {
			$itemids_numeric = zbx_array_merge($items_by_type[ITEM_VALUE_TYPE_FLOAT], $items_by_type[ITEM_VALUE_TYPE_UINT64]);
			$sql = 'SELECT MAX(i.history) as history, MAX(i.trends) as trends FROM items i WHERE '.DBcondition('i.itemid', $itemids_numeric);

			if($table_for_numeric = DBfetch(DBselect($sql))){
				$sql_from_num = ($table_for_numeric['history'] > $table_for_numeric['trends']) ? 'history' : 'trends';
				$result = time() - (86400 * max($table_for_numeric['history'],$table_for_numeric['trends']));
			}
		}

		foreach($items_by_type as $type => $items) {
			if(empty($items)) continue;
			switch($type) {
				case ITEM_VALUE_TYPE_FLOAT: // 0
					$sql_from = $sql_from_num;
				break;
				case ITEM_VALUE_TYPE_STR: // 1
					$sql_from = 'history_str';
				break;
				case ITEM_VALUE_TYPE_LOG: // 2
					$sql_from = 'history_log';
				break;
				case ITEM_VALUE_TYPE_UINT64: // 3
					$sql_from = $sql_from_num.'_uint';
				break;
				case ITEM_VALUE_TYPE_TEXT: // 4
					$sql_from = 'history_text';
				break;
				default:
					$sql_from = 'history';
			}

			$sql = 'SELECT ht.itemid, MIN(ht.clock) as min_clock '.
					' FROM '.$sql_from.' ht '.
					' WHERE '.DBcondition('ht.itemid', $itemids).
					' GROUP BY ht.itemid';
			$res = DBselect($sql);
			while($min_tmp = DBfetch($res)){
				$min = (is_null($min)) ? $min_tmp['min_clock'] : min($min, $min_tmp['min_clock']);
			}
		}
		$result = is_null($min)?$result:$min;

	return $result;
	}

	function get_graph_by_graphid($graphid){

		$result=DBselect("SELECT * FROM graphs WHERE graphid=$graphid");
		$row=DBfetch($result);
		if($row){
			return	$row;
		}
		error(S_NO_GRAPH_WITH." graphid=[$graphid]");
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
 *		$error= true : rise Error if item doesn't exist (error generated), false: special processing (NO error generated)
 */
	function get_same_graphitems_for_host($gitems, $dest_hostid, $error=true){
		$result = array();

		foreach($gitems as $gitem){
			$sql = 'SELECT src.itemid, dest.key_ '.
					' FROM items src, items dest '.
					' WHERE dest.itemid='.$gitem['itemid'].
						' AND src.key_=dest.key_ '.
						' AND src.hostid='.$dest_hostid;
			$db_item = DBfetch(DBselect($sql));
			if (!$db_item && $error){
				$item = get_item_by_itemid($gitem['itemid']);
				$host = get_host_by_hostid($dest_hostid);
				error(S_MISSING_KEY.SPACE.'"'.$item['key_'].'"'.SPACE.S_FOR_HOST_SMALL.SPACE.'"'.$host['host'].'"');
				return false;
			}
			else if(!$db_item){
				continue;
//				$gitem['itemid'] = 0;
			}
			else{
				$gitem['itemid'] = $db_item['itemid'];
				$gitem['key_'] = $db_item['key_'];
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
			error(S_MISSING_ITEMS_FOR_GRAPH.SPACE.'"'.$name.'"');
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
								' AND '.DBcondition('i.itemid',$itemid));

		$graph_hostids = array();
		while($db_item_host = DBfetch($db_item_hosts)){
			$host_list[] = '"'.$db_item_host['host'].'"';
			$graph_hostids[] = $db_item_host['hostid'];

			if(HOST_STATUS_TEMPLATE ==  $db_item_host['status'])
				$new_host_is_template = true;
		}

		if(isset($new_host_is_template) && count($host_list)>1){
			error(S_GRAPH.SPACE.'"'.$name.'"'.SPACE.S_GRAPH_TEMPLATE_HOST_CANNOT_OTHER_ITEMS_HOSTS_SMALL);
			return $result;
		}

		// $filter = array(
			// 'name' => $name,
			// 'hostids' => $graph_hostids
		// );
		// if(CGraph::exists($filter)){
			// error('Graph already exists [ '.$name.' ]');
			// return false;
		// }

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
			error(S_MISSING_ITEMS_FOR_GRAPH.SPACE.'"'.$name.'"');
			return $result;
		}

// check items for template graph
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
					error(S_CANNOT_USE_MULTIPLE_HOST_ITEMS_TEMPLATE_GRAPH.SPACE.'"'.$name.'"');
					return $result;
				}

				$new_hostid = $db_item['hostid'];
			}

			if ( (bccomp($host['hostid'] ,$new_hostid ) != 0)){
				error(S_MUST_USE_ITEMS_ONLY_FROM_HOST.SPACE.'"'.$host['host'].'"'.SPACE.S_FOR_TEMPLATE_GRAPH_SMALL.SPACE.'"'.$name.'"');
				return $result;
			}
		}

// firstly update child graphs
		$chd_graphs = get_graphs_by_templateid($graphid);
		while($chd_graph = DBfetch($chd_graphs)){
			$tmp_hosts = get_hosts_by_graphid($chd_graph['graphid']);
			$chd_host = DBfetch($tmp_hosts);

			if(!$new_gitems = get_same_graphitems_for_host($gitems, $chd_host['hostid'])){ /* skip host with missing items */
				error(S_CANNOT_UPDATE_GRAPH.SPACE.'"'.$name.'"'.SPACE.S_FOR_HOST_SMALL.SPACE.'"'.$chd_host['host'].'"');
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

			info(S_GRAPH.SPACE.'"'.$name.'"'.SPACE.S_UPDATED_FOR_HOSTS.SPACE.implode(',',$host_list));
		}

		return $result;
	}

/*
 * Function: delete_graph
 *
 * Description:
 *     Delete graph with templates
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
		$host_list = array();
		foreach ($graphids as $id => $graphid) {
			$graphs[$graphid] = get_graph_by_graphid($graphid);

			$host_list[$graphid] = array();

			$db_hosts = get_hosts_by_graphid($graphid);

			while ($db_host = DBfetch($db_hosts)) {
				if (!isset($host_list[$graphid][$db_host['host']])) {
					$host_list[$graphid][$db_host['host']] = true;
					$host_list[$graphid]['host'] = $db_host['host'];
				}
			}
		}

		// first remove child graphs
		$del_chd_graphs = array();
		$chd_graphs = get_graphs_by_templateid($graphids);

		while ($chd_graph = DBfetch($chd_graphs)) {
			/* recursion */
			$del_chd_graphs[$chd_graph['graphid']] = $chd_graph['graphid'];
		}
		if (!empty($del_chd_graphs)) {
			$result &= delete_graph($del_chd_graphs);
		}

		DBexecute('DELETE FROM screens_items WHERE '.DBcondition('resourceid',$graphids).' AND resourcetype='.SCREEN_RESOURCE_GRAPH);

		// delete graph
		DBexecute('DELETE FROM graphs_items WHERE '.DBcondition('graphid',$graphids));
		DBexecute("DELETE FROM profiles WHERE idx='web.favorite.graphids' AND source='graphid' AND ".DBcondition('value_id',$graphids));

		$result = DBexecute('DELETE FROM graphs WHERE '.DBcondition('graphid',$graphids));
		if ($result) {
			foreach ($graphs as $graphid => $graph) {
				if (isset ($host_list[$graphid])) {
					info(S_GRAPH.SPACE.'"'.$host_list[$graphid]['host'].':'.$graph['name'].'"'.SPACE.S_DELETED_SMALL);
				}
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
	function cmp_graphitems(&$gitem1, &$gitem2){
		if($gitem1['drawtype']	!= $gitem2['drawtype'])		return 1;
		if($gitem1['sortorder']	!= $gitem2['sortorder'])	return 2;
		if($gitem1['color']	!= $gitem2['color'])		return 3;
		if($gitem1['yaxisside']	!= $gitem2['yaxisside'])	return 4;

		$item1 = get_item_by_itemid($gitem1['itemid']);
		$item2 = get_item_by_itemid($gitem2['itemid']);

		if($item1['key_'] != $item2['key_'])			return 5;

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
	function add_item_to_graph($graphid,$itemid,$color,$drawtype,$sortorder,$yaxisside,$calc_fnc,$type,$periods_cnt){
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

		$host = get_host_by_hostid($hostid);

		while ($db_graph = DBfetch($db_graphs)) {
			if ($db_graph['templateid'] == 0) {
				continue;
			}

			if (!is_null($templateids)) {
				$tmp_hhosts = get_hosts_by_graphid($db_graph['templateid']);
				$tmp_host = DBfetch($tmp_hhosts);

				if (!uint_in_array($tmp_host['hostid'], $templateids)) {
					continue;
				}
			}

			if ($unlink_mode) {
				if (DBexecute('UPDATE graphs SET templateid=0 WHERE graphid='.$db_graph['graphid'])) {
					info(S_GRAPH.SPACE.'"'.$host['host'].':'.$db_graph['name'].'"'.SPACE.S_UNLINKED_SMALL);
				}
			}
			else {
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

		if($copy_mode){
			while($db_graph = DBfetch($db_graphs)){
				copy_graph_to_host($db_graph["graphid"], $hostid, $copy_mode);
			}
		}
		else{
			while($db_graph = DBfetch($db_graphs)){
				$gitems = CGraphItem::get(array(
					'graphids' => $db_graph['graphid'],
					'output' => API_OUTPUT_EXTEND
				));


				$filter = array(
					'name' => $db_graph['name'],
					'hostids' => $hostid
				);
				if(CGraph::exists($filter)){
					$db_graph['gitems'] = $gitems;
					$res = CGraph::update($db_graph);
				}
				else{
					$db_graph['templateid'] = $db_graph['graphid'];
					$db_graph['gitems'] = get_same_graphitems_for_host($gitems, $hostid);
					$res = CGraph::create($db_graph);
				}
				if($res === false) return false;
			}
		}

		return true;
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
	function copy_graph_to_host($graphid, $hostid, $copy_mode=false){
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

					/* found equal graph item */
					if(!isset($equal))$equal = 0;

					$equal++;
				}

				if(isset($equal) && (count($new_gitems) == $equal)){
/* found equal graph */
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
			$result = false;
		}

		return $result;
	}

	function navigation_bar_calc($idx=null, $idx2=0, $update=false){
//SDI($_REQUEST['stime']);

		if(!is_null($idx)){
			if($update){
				if(isset($_REQUEST['period']) && ($_REQUEST['period'] >= ZBX_MIN_PERIOD))
					CProfile::update($idx.'.period',$_REQUEST['period'],PROFILE_TYPE_INT, $idx2);

				if(isset($_REQUEST['stime']))
					CProfile::update($idx.'.stime',$_REQUEST['stime'], PROFILE_TYPE_STR, $idx2);
			}

			$_REQUEST['period'] = get_request('period', CProfile::get($idx.'.period', ZBX_PERIOD_DEFAULT, $idx2));
			$_REQUEST['stime'] = get_request('stime', CProfile::get($idx.'.stime', null, $idx2));
		}

		$_REQUEST['period'] = get_request('period', ZBX_PERIOD_DEFAULT);
		$_REQUEST['stime'] = get_request('stime', null);

		if($_REQUEST['period']<ZBX_MIN_PERIOD){
			show_message(S_WARNING.'. '.S_TIME_PERIOD.SPACE.S_MIN_VALUE_SMALL.': '.ZBX_MIN_PERIOD.' ('.(int)(ZBX_MIN_PERIOD/3600).S_HOUR_SHORT.')');
			$_REQUEST['period'] = ZBX_MIN_PERIOD;

		}
		else if($_REQUEST['period'] > ZBX_MAX_PERIOD){
			show_message(S_WARNING.'. '.S_TIME_PERIOD.SPACE.S_MAX_VALUE_SMALL.': '.ZBX_MAX_PERIOD.' ('.(int)(ZBX_MAX_PERIOD/86400).S_DAY_SHORT.')');
			$_REQUEST['period'] = ZBX_MAX_PERIOD;
		}

		if(isset($_REQUEST['stime'])){
			$time = zbxDateToTime($_REQUEST['stime']);

			if(($time+$_REQUEST['period']) > time()) {
				$_REQUEST['stime'] = date('YmdHis', time()-$_REQUEST['period']);
			}
		}
		else{
			$_REQUEST['stime'] = date('YmdHis', time()-$_REQUEST['period']);
		}

	return $_REQUEST['period'];
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

	function imageDiagonalMarks($im,$x, $y, $offset, $color){
		global $colors;

		$gims = array(
			'lt' => array(0,0, -9,0, -9,-3, -3,-9, 0,-9),
			'rt' => array(0,0,  9,0,  9,-3,  3,-9, 0,-9),
			'lb' => array(0,0, -9,0,  -9,3,  -3,9,  0,9),
			'rb' => array(0,0,  9,0,   9,3,   3,9,  0,9),
		);

		foreach($gims['lt'] as $num => $px){
			if(($num % 2) == 0) $gims['lt'][$num] = $px  + $x - $offset;
			else $gims['lt'][$num] = $px  + $y - $offset;
		}

		foreach($gims['rt'] as $num => $px){
			if(($num % 2) == 0) $gims['rt'][$num] = $px  + $x + $offset;
			else $gims['rt'][$num] = $px  + $y - $offset;
		}

		foreach($gims['lb'] as $num => $px){
			if(($num % 2) == 0) $gims['lb'][$num] = $px  + $x - $offset;
			else $gims['lb'][$num] = $px  + $y + $offset;
		}

		foreach($gims['rb'] as $num => $px){
			if(($num % 2) == 0) $gims['rb'][$num] = $px  + $x + $offset;
			else $gims['rb'][$num] = $px  + $y + $offset;
		}

		imagefilledpolygon($im,$gims['lt'],5,$color);
		imagepolygon($im,$gims['lt'],5,$colors['Dark Red']);

		imagefilledpolygon($im,$gims['rt'],5,$color);
		imagepolygon($im,$gims['rt'],5,$colors['Dark Red']);

		imagefilledpolygon($im,$gims['lb'],5,$color);
		imagepolygon($im,$gims['lb'],5,$colors['Dark Red']);

		imagefilledpolygon($im,$gims['rb'],5,$color);
		imagepolygon($im,$gims['rb'],5,$colors['Dark Red']);

	}

	function imageVerticalMarks($im,$x, $y, $offset, $color, $marks='tlbr'){
		global $colors;

		$polygons = 5;
		$gims = array(
			't' => array(0,0, -6,-6, -3,-9,  3,-9,  6,-6),
			'l' => array(0,0,  -6,6,  -9,3, -9,-3, -6,-6),
			'b' => array(0,0,   6,6,   3,9,  -3,9,  -6,6),
			'r' => array(0,0,  6,-6,  9,-3,   9,3,   6,6),
		);

		foreach($gims['t'] as $num => $px){
			if(($num % 2) == 0) $gims['t'][$num] = $px  + $x;
			else $gims['t'][$num] = $px  + $y - $offset;
		}

		foreach($gims['l'] as $num => $px){
			if(($num % 2) == 0) $gims['l'][$num] = $px  + $x - $offset;
			else $gims['l'][$num] = $px  + $y;
		}

		foreach($gims['b'] as $num => $px){
			if(($num % 2) == 0) $gims['b'][$num] = $px  + $x;
			else $gims['b'][$num] = $px  + $y + $offset;
		}

		foreach($gims['r'] as $num => $px){
			if(($num % 2) == 0) $gims['r'][$num] = $px  + $x + $offset;
			else $gims['r'][$num] = $px  + $y;
		}

		if(strpos($marks, 't') !== false){
			imagefilledpolygon($im,$gims['t'],$polygons,$color);
			imagepolygon($im,$gims['t'],$polygons,$colors['Dark Red']);
		}

		if(strpos($marks, 'l') !== false){
			imagefilledpolygon($im,$gims['l'],$polygons,$color);
			imagepolygon($im,$gims['l'],$polygons,$colors['Dark Red']);
		}

		if(strpos($marks, 'b') !== false){
			imagefilledpolygon($im,$gims['b'],$polygons,$color);
			imagepolygon($im,$gims['b'],$polygons,$colors['Dark Red']);
		}

		if(strpos($marks, 'r') !== false){
			imagefilledpolygon($im,$gims['r'],$polygons,$color);
			imagepolygon($im,$gims['r'],$polygons,$colors['Dark Red']);
		}

	}

	function imageText($image, $fontsize, $angle, $x, $y, $color, $string){
		$gdinfo = gd_info();

		if($gdinfo['FreeType Support'] && function_exists('imagettftext')){

			if((preg_match(ZBX_PREG_DEF_FONT_STRING, $string) && ($angle != 0)) || (ZBX_FONT_NAME == ZBX_GRAPH_FONT_NAME)){
				$ttf = ZBX_FONTPATH.'/'.ZBX_FONT_NAME.'.ttf';
				imagettftext($image, $fontsize, $angle, $x, $y, $color, $ttf, $string);
			}
			else if($angle == 0){
				$ttf = ZBX_FONTPATH.'/'.ZBX_GRAPH_FONT_NAME.'.ttf';
				imagettftext($image, $fontsize, $angle, $x, $y, $color, $ttf, $string);
			}
			else{
				$ttf = ZBX_FONTPATH.'/'.ZBX_GRAPH_FONT_NAME.'.ttf';

				$size = imageTextSize($fontsize, 0, $string);

				$imgg = imagecreatetruecolor($size['width']+1, $size['height']);
				$transparentColor = imagecolorallocatealpha($imgg, 200, 200, 200, 127);
				imagefill($imgg, 0, 0, $transparentColor);

				imagettftext($imgg, $fontsize, 0, 0, $size['height'], $color, $ttf, $string);

				$imgg = imagerotate($imgg, $angle, $transparentColor);
				ImageAlphaBlending($imgg, false);
				imageSaveAlpha($imgg, true);

				imagecopy($image, $imgg, $x - $size['height'], $y - $size['width'], 0, 0, $size['height'], $size['width']+1);

				imagedestroy($imgg);
			}
/*
			$ar = imagettfbbox($fontsize, $angle, $ttf, $string);
//sdii($ar);
			if(!$angle)	imagerectangle($image, $x, $y+$ar[1], $x+abs($ar[0] - $ar[4]), $y+$ar[5], $color);
			else imagerectangle($image, $x, $y, $x-abs($ar[0] - $ar[4]), $y+($ar[5]-$ar[1]), $color);
//*/

		}
		else{
			$dims = imageTextSize($fontsize, $angle, $string);

			switch($fontsize){
				case 5: $fontsize = 1; break;
				case 6: $fontsize = 1; break;
				case 7: $fontsize = 2; break;
				case 8: $fontsize = 2; break;
				case 9:	$fontsize = 3; break;
				case 10: $fontsize = 3; break;
				case 11: $fontsize = 4; break;
				case 12: $fontsize = 4; break;
				case 13: $fontsize = 5; break;
				case 14: $fontsize = 5; break;
				default: $fontsize = 2; break;
			}

//SDI($dims);
			if($angle){
				$x -= $dims['width'];
				$y -= 2;
			}
			else{
				$y -= $dims['height'] - 2;
			}

			if($angle > 0)	return imagestringup($image, $fontsize, $x, $y, $string, $color);
			return imagestring($image, $fontsize, $x, $y, $string, $color);
		}

	return true;
	}

	function imageTextSize($fontsize, $angle, $string){
		$gdinfo = gd_info();

		$result = array();

		if($gdinfo['FreeType Support'] && function_exists('imagettfbbox')){

			if(preg_match(ZBX_PREG_DEF_FONT_STRING, $string) && ($angle != 0)){
				$ttf = ZBX_FONTPATH.'/'.ZBX_FONT_NAME.'.ttf';
			}
			else{
				$ttf = ZBX_FONTPATH.'/'.ZBX_GRAPH_FONT_NAME.'.ttf';
			}

			$ar = imagettfbbox($fontsize, $angle, $ttf, $string);

			$result['height'] = abs($ar[1] - $ar[5]);
			$result['width'] = abs($ar[0] - $ar[4]);
			$result['baseline'] = $ar[1];
		}
		else{
			switch($fontsize){
				case 5: $fontsize = 1; break;
				case 6: $fontsize = 1; break;
				case 7: $fontsize = 2; break;
				case 8: $fontsize = 2; break;
				case 9:	$fontsize = 3; break;
				case 10: $fontsize = 3; break;
				case 11: $fontsize = 4; break;
				case 12: $fontsize = 4; break;
				case 13: $fontsize = 5; break;
				case 14: $fontsize = 5; break;
				default: $fontsize = 2; break;
			}

			if($angle){
				$result['width'] = imagefontheight($fontsize);
				$result['height'] = imagefontwidth($fontsize) * zbx_strlen($string);
			}
			else{
				$result['height'] = imagefontheight($fontsize);
				$result['width'] = imagefontwidth($fontsize) * zbx_strlen($string);
			}
			$result['baseline'] = 0;
		}

		return $result;
	}

	function DashedLine($image,$x1,$y1,$x2,$y2,$color){
		// Style for dashed lines
		//$style = array($color, $color, $color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
		if(!is_array($color)) $style = array($color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
		else $style = $color;

		ImageSetStyle($image, $style);
		ImageLine($image,$x1,$y1,$x2,$y2,IMG_COLOR_STYLED);
	}

	function DashedRectangle($image,$x1,$y1,$x2,$y2,$color){
		DashedLine($image, $x1,$y1,$x1,$y2,$color);
		DashedLine($image, $x1,$y2,$x2,$y2,$color);
		DashedLine($image, $x2,$y2,$x2,$y1,$color);
		DashedLine($image, $x2,$y1,$x1,$y1,$color);
	}

	function find_period_start($periods,$time){
		$date = getdate($time);
		$wday = $date['wday'] == 0 ? 7 : $date['wday'];
		$curr = $date['hours']*100+$date['minutes'];

		if(isset($periods[$wday])){
			$next_h = -1;
			$next_m = -1;
			foreach($periods[$wday] as $period){
				$per_start = $period['start_h']*100+$period['start_m'];
				if($per_start > $curr){
					if(($next_h == -1 && $next_m == -1) || ($per_start < ($next_h*100 + $next_m))){
						$next_h = $period['start_h'];
						$next_m = $period['start_m'];
					}
					continue;
				}

				$per_end = $period['end_h']*100+$period['end_m'];
				if($per_end <= $curr) continue;
				return $time;
			}

			if($next_h >= 0 && $next_m >= 0){
				return mktime($next_h, $next_m, 0, $date['mon'], $date['mday'], $date['year']);
			}
		}

		for($days=1; $days < 7 ; ++$days){
			$new_wday = (($wday + $days - 1)%7 + 1);
			if(isset($periods[$new_wday ])){
				$next_h = -1;
				$next_m = -1;
				foreach($periods[$new_wday] as $period){
					$per_start = $period['start_h']*100+$period['start_m'];
					if(($next_h == -1 && $next_m == -1) || ($per_start < ($next_h*100 + $next_m))){
						$next_h = $period['start_h'];
						$next_m = $period['start_m'];
					}
				}

				if($next_h >= 0 && $next_m >= 0){
					return mktime($next_h, $next_m, 0, $date['mon'], $date['mday'] + $days, $date['year']);
				}
			}
		}
	return -1;
	}

	function find_period_end($periods,$time,$max_time){
		$date = getdate($time);
		$wday = $date['wday'] == 0 ? 7 : $date['wday'];
		$curr = $date['hours']*100+$date['minutes'];

		if(isset($periods[$wday])){
			$next_h = -1;
			$next_m = -1;
			foreach($periods[$wday] as $period){
				$per_start = $period['start_h']*100+$period['start_m'];
				$per_end = $period['end_h']*100+$period['end_m'];
				if($per_start > $curr) continue;
				if($per_end < $curr) continue;

				if(($next_h == -1 && $next_m == -1) || ($per_end > ($next_h*100 + $next_m))){
					$next_h = $period['end_h'];
					$next_m = $period['end_m'];
				}
			}

			if($next_h >= 0 && $next_m >= 0){
				$new_time = mktime($next_h, $next_m, 0, $date['mon'], $date['mday'], $date['year']);

				if($new_time == $time) return $time;
				if($new_time > $max_time) return $max_time;

				$next_time = find_period_end($periods,$new_time,$max_time);
				if($next_time < 0)
					return $new_time;
				else
					return $next_time;
			}
		}

	return -1;
	}
?>
