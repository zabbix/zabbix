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
	function	graph_item_type2str($type,$count=null)
	{
		switch($type)
		{
			case GRAPH_ITEM_AGGREGATED:	$type = S_AGGREGATED.(isset($count) ? '('.$count.')' : '');	break;
			case GRAPH_ITEM_SIMPLE:
			default:			$type = S_SIMPLE;	break;
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
	function	graph_item_drawtypes()
	{
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
        function	graph_item_drawtype2str($drawtype,$type=null)
        {
		if($type == GRAPH_ITEM_AGGREGATED) return '-';

		switch($drawtype)
		{
			case GRAPH_ITEM_DRAWTYPE_LINE:		$drawtype = "Line";		break;
			case GRAPH_ITEM_DRAWTYPE_FILLED_REGION:	$drawtype = "Filled region";	break;
			case GRAPH_ITEM_DRAWTYPE_BOLD_LINE:	$drawtype = "Bold line";	break;
			case GRAPH_ITEM_DRAWTYPE_DOT:		$drawtype = "Dot";		break;
			case GRAPH_ITEM_DRAWTYPE_DASHED_LINE:	$drawtype = "Dashed line";	break;
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
	function	graph_item_calc_fnc2str($calc_fnc, $type=null)
	{
		if($type == GRAPH_ITEM_AGGREGATED) return '-';
		
		switch($calc_fnc)
		{
			case CALC_FNC_ALL:      $calc_fnc = S_ALL_SMALL;        break;
			case CALC_FNC_MIN:      $calc_fnc = S_MIN_SMALL;        break;
			case CALC_FNC_MAX:      $calc_fnc = S_MAX_SMALL;        break;
			case CALC_FNC_AVG:
			default:		$calc_fnc = S_AVG_SMALL;        break;
		}
		return $calc_fnc;
	}
	
	function 	get_graph_by_gitemid($gitemid)
	{
		$db_graphs = DBselect("select distinct g.* from graphs g, graphs_items gi".
			" where g.graphid=gi.graphid and gi.gitemid=$gitemid");
		return DBfetch($db_graphs);
		
	}

	function 	&get_graphs_by_hostid($hostid)
	{
		return DBselect("select distinct g.* from graphs g, graphs_items gi, items i".
			" where g.graphid=gi.graphid and gi.itemid=i.itemid and i.hostid=$hostid");
	}

	function	&get_realhosts_by_graphid($graphid)
	{
		$graph = get_graph_by_graphid($graphid);
		if($graph["templateid"] != 0)
			return get_realhosts_by_graphid($graph["templateid"]);

		return get_hosts_by_graphid($graphid);
	}

	function 	&get_hosts_by_graphid($graphid)
	{
		return DBselect("select distinct h.* from graphs_items gi, items i, hosts h".
			" where h.hostid=i.hostid and gi.itemid=i.itemid and gi.graphid=$graphid");
	}

	function	&get_graphitems_by_graphid($graphid)
	{
		return DBselect("select * from graphs_items where graphid=$graphid".
			" order by itemid,drawtype,sortorder,color,yaxisside"); 
	}

	function	get_graphitem_by_gitemid($gitemid)
	{
		$result=DBselect("select * from graphs_items where gitemid=$gitemid");
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		error("No graph item with gitemid=[$gitemid]");
		return	$result;
	}

	function	get_graphitem_by_itemid($itemid)
	{
		$result = DBfetch(DBselect('select * from graphs_items where itemid='.$itemid));
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		return	$result;
	}

	function	get_graph_by_graphid($graphid)
	{

		$result=DBselect("select * from graphs where graphid=$graphid");
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		error("No graph with graphid=[$graphid]");
		return	false;
	}

	function	&get_graphs_by_templateid($templateid)
	{
		return DBselect("select * from graphs where templateid=$templateid");
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
         *
         */
	function	get_same_graphitems_for_host($gitems, $dest_hostid)
	{
		$result = array();

		foreach($gitems as $gitem)
		{
			if ( !($db_item = DBfetch(DBselect('select src.itemid from items src, items dest '.
					       ' where dest.itemid='.$gitem['itemid'].
					       ' and src.key_=dest.key_ and src.hostid='.$dest_hostid))) )
			{

				$item = get_item_by_itemid($gitem['itemid']);
				$host = get_host_by_hostid($dest_hostid);
				error('Missed key "'.$item['key_'].'" for host "'.$host['host'].'"');
				return false;
			}

			$gitem['itemid'] = $db_item['itemid'];

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
	function	add_graph($name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,$showtriggers,$graphtype=GRAPH_TYPE_NORMAL,$templateid=0)
	{
		$graphid = get_dbid("graphs","graphid");

		$result=DBexecute("insert into graphs".
			" (graphid,name,width,height,yaxistype,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype)".
			" values ($graphid,".zbx_dbstr($name).",$width,$height,$yaxistype,$yaxismin,".
			" $yaxismax,$templateid,$showworkperiod,$showtriggers,$graphtype)");

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
	function	add_graph_with_items($name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,$showtriggers,$graphtype=GRAPH_TYPE_NORMAL,$gitems=array(),$templateid=0)
	{
		$result = false;

		if ( !is_array($gitems) || count($gitems) < 1 )
		{
			error('Missed items for graph "'.$name.'"');
			return $result;
		}

		/* check items for template graph */
		unset($new_host_is_template);
		$host_list = array();
		$itemid = array(0);

		foreach($gitems as $gitem)
			$itemid[] = $gitem['itemid'];

		$db_item_hosts = DBselect('select distinct h.hostid,h.host,h.status '.
				' from items i, hosts h where h.hostid=i.hostid and i.itemid in ('.implode(',', $itemid).')');
		while($db_item_host = DBfetch($db_item_hosts))
		{
			$host_list[] = '"'.$db_item_host['host'].'"';

			if ( HOST_STATUS_TEMPLATE ==  $db_item_host['status'] )
				$new_host_is_template = true;
		}

		if ( isset($new_host_is_template) && count($host_list) > 1 )
		{
			error('Graph "'.$name.'" with template host can not contain items from other hosts.');
			return $result;
		}

		if ( ($graphid = add_graph($name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,$showtriggers,$graphtype,$templateid)) )
		{
			$result = true;
			foreach($gitems as $gitem)
			{
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
					break;
				}
			}
		}

		if ( $result )
		{
			info('Graph "'.$name.'" added to hosts '.implode(',',$host_list));

			/* add graphs for child hosts */

			$host = DBfetch(get_hosts_by_graphid($graphid));

			$chd_hosts = get_hosts_by_templateid($host['hostid']);
			while($chd_host = DBfetch($chd_hosts))
			{
				copy_graph_to_host($graphid, $chd_host['hostid'], false);
			}
		}

		if ( !$result && $graphid )
		{
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
	function	update_graph($graphid,$name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,$showtriggers,$graphtype=GRAPH_TYPE_NORMAL,$templateid=0)
	{
		$g_graph = get_graph_by_graphid($graphid);

		if ( ($result = DBexecute('update graphs set name='.zbx_dbstr($name).',width='.$width.',height='.$height.','.
			'yaxistype='.$yaxistype.',yaxismin='.$yaxismin.',yaxismax='.$yaxismax.',templateid='.$templateid.','.
			'show_work_period='.$showworkperiod.',show_triggers='.$showtriggers.',graphtype='.$graphtype.
			' where graphid='.$graphid)) )
		{
			if($g_graph['graphtype'] != $graphtype && $graphtype == GRAPH_TYPE_STACKED)
			{
				$result = DBexecute('update graphs_items set calc_fnc='.CALC_FNC_AVG.',drawtype=1,type='.GRAPH_ITEM_SIMPLE.
					' where graphid='.$graphid);
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
	function	update_graph_with_items($graphid,$name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,$showtriggers,$graphtype=GRAPH_TYPE_NORMAL,$gitems=array(),$templateid=0)
	{
		$result = false;

		if ( !is_array($gitems) || count($gitems) < 1 )
		{
			error('Missed items for graph "'.$name.'"');
			return $result;
		}

		/* check items for template graph */
		$host = DBfetch(get_hosts_by_graphid($graphid));
		if ( $host["status"] == HOST_STATUS_TEMPLATE )
		{
			unset($new_hostid);
			$itemid = array(0);

			foreach($gitems as $gitem)
				$itemid[] = $gitem['itemid'];

			$db_item_hosts = DBselect('select distinct hostid from items where itemid in ('.implode(',', $itemid).')');
			while($db_item = DBfetch($db_item_hosts))
			{
				if ( isset($new_hostid) )
				{
					error('Can not use multiple host items for template graph "'.$name.'"');
					return $result;
				}

				$new_hostid = $db_item['hostid'];
			}

			if ( $host['hostid'] != $new_hostid )
			{
				error('You must use items only from host "'.$host['host'].'" for template graph "'.$name.'"');
				return $result;
			}
		}

		/* firstly update child graphs */
		$chd_graphs = get_graphs_by_templateid($graphid);
		while($chd_graph = DBfetch($chd_graphs))
		{
			$chd_host = DBfetch(get_hosts_by_graphid($chd_graph['graphid']));
			if ( ! ($new_gitems = get_same_graphitems_for_host($gitems, $chd_host['hostid'])) )
			{ /* skip host with missed items */
				error('Can not update graph "'.$name.'" for host "'.$chd_host['host'].'"');
				return $result;
			}
		
			if ( ! ($result = update_graph_with_items($chd_graph['graphid'], $name, $width, $height,
				$yaxistype, $yaxismin, $yaxismax,
				$showworkperiod, $showtriggers, $graphtype, $new_gitems, $graphid)) )
			{
				return $result;
			}
		}

		DBexecute('delete from graphs_items where graphid='.$graphid);

		foreach($gitems as $gitem)
		{
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

		if ( ($result = update_graph($graphid,$name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,
						$showtriggers,$graphtype,$templateid)) )
		{
			$host_list = array();
			$db_hosts = get_hosts_by_graphid($graphid);
			while($db_host = DBfetch($db_hosts))
			{
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
	function	delete_graph($graphid)
	{
		$graph = get_graph_by_graphid($graphid);

		$host_list = array();
		$db_hosts = get_hosts_by_graphid($graphid);
		while($db_host = DBfetch($db_hosts))
		{
			$host_list[] = '"'.$db_host['host'].'"';
		}

		/* firstly remove child graphs */
		$chd_graphs = get_graphs_by_templateid($graphid);
		while($chd_graph = DBfetch($chd_graphs))
		{ /* recursion */
			if ( !($result = delete_graph($chd_graph['graphid'])) )
				return $result;
		}

		DBexecute('delete from screens_items where resourceid='.$graphid.' and resourcetype='.SCREEN_RESOURCE_GRAPH);

		/* delete graph */
		if ( ($result = DBexecute('delete from graphs_items where graphid='.$graphid)) )
		if ( ($result = DBexecute('delete from graphs where graphid='.$graphid)) )
		{	
			info('Graph "'.$graph['name'].'" deleted from hosts '.implode(',',$host_list));
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
	function	cmp_graphitems(&$gitem1, &$gitem2)
	{
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
	function	delete_template_graphs($hostid, $templateid = null, $unlink_mode = false)
	{
		$db_graphs = get_graphs_by_hostid($hostid);
		while($db_graph = DBfetch($db_graphs))
		{
			if($db_graph['templateid'] == 0)
				continue;

			if( !is_null($templateid) )
			{
				if( !is_array($templateid) ) $templateid=array($templateid);

				$tmp_host = DBfetch(get_hosts_by_graphid($db_graph['templateid']));

				if( !in_array($tmp_host['hostid'], $templateid))
					continue;
			}

			if($unlink_mode)
			{
				if(DBexecute('update graphs set templateid=0 where graphid='.$db_graph['graphid']))
				{
					info('Graph "'.$db_graph['name'].'" unlinked');
				}	
			}
			else
			{
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
	function	copy_template_graphs($hostid, $templateid = null, $copy_mode = false)
	{
		if($templateid == null)
		{
			$templateid = array_keys(get_templates_by_hostid($hostid));
		}
		
		if(is_array($templateid))
		{
			foreach($templateid as $id)
				copy_template_graphs($hostid, $id, $copy_mode); // attention recursion
			return;
		}

		$db_graphs = get_graphs_by_hostid($templateid);

		while($db_graph = DBfetch($db_graphs))
		{
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
	function	copy_graph_to_host($graphid, $hostid, $copy_mode = false)
	{
		$result = false;

		$gitems = array();

		$db_graph_items = get_graphitems_by_graphid($graphid);
		while( $db_gitem = DBfetch($db_graph_items) )
		{
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

		if ( ($new_gitems = get_same_graphitems_for_host($gitems, $hostid)) )
		{
			unset($chd_graphid);
			$chd_graphs = get_graphs_by_hostid($hostid);
			while( !isset($chd_graphid) && $chd_graph = DBfetch($chd_graphs))
			{ /* compare graphs */
				if ( $chd_graph['templateid'] != 0 ) continue;

				unset($equal);
				$chd_gitems = get_graphitems_by_graphid($chd_graph["graphid"]);
				while($chd_gitem = DBfetch($chd_gitems))
				{
					unset($gitem_equal);
					foreach($new_gitems as $new_gitem)
					{
						if(cmp_graphitems($new_gitem, $chd_gitem))	continue;

						$gitem_equal = true;
						break;
					}

					if ( !isset($gitem_equal) )
					{
						unset($equal);
						break;
					}

					/* founded equal graph item */
					if ( !isset($equal) ) $equal = 0;

					$equal++;
				}

				if ( isset($equal) && count($new_gitems) == $equal )
				{ /* founded equal graph */
					$chd_graphid = $chd_graph["graphid"];
					break;
				}
			}

			if ( isset($chd_graphid) )
			{
				$result = update_graph_with_items($chd_graphid, $db_graph['name'], $db_graph['width'], $db_graph['height'],
					$db_graph['yaxistype'], $db_graph['yaxismin'], $db_graph['yaxismax'],
					$db_graph['show_work_period'], $db_graph['show_triggers'], $db_graph['graphtype'],
					$new_gitems, ($copy_mode ? 0: $db_graph['graphid']));
			}
			else
			{
				$result = add_graph_with_items($db_graph['name'], $db_graph['width'], $db_graph['height'],
					$db_graph['yaxistype'], $db_graph['yaxismin'], $db_graph['yaxismax'],
					$db_graph['show_work_period'], $db_graph['show_triggers'], $db_graph['graphtype'],
					$new_gitems, ($copy_mode ? 0: $db_graph['graphid']));
			}
		}
		else
		{
			$host = get_host_by_hostid($hostid);
			info('Skipped coping of graph "'.$db_graph["name"].'" to host "'.$host['host'].'"');
		}

		return $result;
	}

	function	navigation_bar_calc()
	{
//		$workingperiod = 3600;
		if(!isset($_REQUEST["period"]))	$_REQUEST["period"]=ZBX_PERIOD_DEFAULT;
		if(!isset($_REQUEST["from"]))	$_REQUEST["from"]=0;
		if(!isset($_REQUEST["stime"]))	$_REQUEST["stime"]=null;

//		if(isset($_REQUEST["inc"]))		$workingperiod= $_REQUEST["period"]+$_REQUEST["inc"];
//		if(isset($_REQUEST["dec"]))		$workingperiod= $workingperiod-$_REQUEST["dec"];
		if(isset($_REQUEST["inc"]))	$_REQUEST["period"] += $_REQUEST["inc"];
		if(isset($_REQUEST["dec"]))	$_REQUEST["period"] -= $_REQUEST["dec"];

		if(isset($_REQUEST["left"]))	$_REQUEST["from"] += $_REQUEST["left"];
		if(isset($_REQUEST["right"]))	$_REQUEST["from"] -= $_REQUEST["right"];

		//unset($_REQUEST["inc"]);
		//unset($_REQUEST["dec"]);
		unset($_REQUEST["left"]);
		unset($_REQUEST["right"]);

		if($_REQUEST["from"] <= 0)			$_REQUEST["from"]	= 0;
		if($_REQUEST["period"] <= ZBX_MIN_PERIOD)	$_REQUEST["period"]	= ZBX_MIN_PERIOD;

		if(isset($_REQUEST["reset"]))
		{
			$_REQUEST["period"]	= ZBX_PERIOD_DEFAULT;
			$_REQUEST["from"]	= 0;
//			$workingperiod		= 3600;
		}

		return $_REQUEST["period"];
//		return $workingperiod;
	}

	function	navigation_bar($url,$ext_saved_request=NULL)
	{
		$saved_request = array("screenid","itemid","action","from","fullscreen");

		if(is_array($ext_saved_request))
			$saved_request = array_merge($saved_request, $ext_saved_request);
		elseif(is_string($ext_saved_request))
			array_push($saved_request,$ext_saved_request);

		$form = new CForm($url);
		$form->SetMethod('get');	
		
		$form->AddItem(S_PERIOD.SPACE);

		$period = get_request('period',ZBX_PERIOD_DEFAULT);

		if(in_array($period,array(3600,2*3600,4*3600,8*3600,12*3600,24*3600,7*24*3600,31*24*3600,365*24*3600)))
			$custom_per = ZBX_MIN_PERIOD;
		else
			$custom_per = $period;

		$cmbPeriod = new CComboBox("period",$period,"submit()");
		$cmbPeriod->AddItem($custom_per,"custom");
		$cmbPeriod->AddItem(3600,"1h");
		$cmbPeriod->AddItem(2*3600,"2h");
		$cmbPeriod->AddItem(4*3600,"4h");
		$cmbPeriod->AddItem(8*3600,"8h");
		$cmbPeriod->AddItem(12*3600,"12h");
		$cmbPeriod->AddItem(24*3600,"24h");
		$cmbPeriod->AddItem(7*24*3600,"week");
		$cmbPeriod->AddItem(31*24*3600,"month");
		$cmbPeriod->AddItem(365*24*3600,"year");
		$form->AddItem($cmbPeriod);

		$cmbDec = new CComboBox("dec",0,"submit()");
		$cmbDec->AddItem(0,S_DECREASE);
		$cmbDec->AddItem(3600,"-1h");
		$cmbDec->AddItem(4*3600,"-4h");
		$cmbDec->AddItem(24*3600,"-24h");
		$cmbDec->AddItem(7*24*3600,"-week");
		$cmbDec->AddItem(31*24*3600,"-month");
		$cmbDec->AddItem(365*24*3600,"-year");
		$form->AddItem($cmbDec);

		$cmbInc = new CComboBox("inc",0,"submit()");
		$cmbInc->AddItem(0,S_INCREASE);
		$cmbInc->AddItem(3600,"+1h");
		$cmbInc->AddItem(4*3600,"+4h");
		$cmbInc->AddItem(24*3600,"+24h");
		$cmbInc->AddItem(7*24*3600,"+week");
		$cmbInc->AddItem(31*24*3600,"+month");
		$cmbInc->AddItem(365*24*3600,"+year");
		$form->AddItem($cmbInc);

		$form->AddItem(SPACE.S_MOVE.SPACE);

		$cmbLeft = new CComboBox("left",0,"submit()");
		$cmbLeft->AddItem(0,S_LEFT_DIR);
		$cmbLeft->AddItem(1,"-1h");
		$cmbLeft->AddItem(4,"-4h");
		$cmbLeft->AddItem(24,"-24h");
		$cmbLeft->AddItem(7*24,"-week");
		$cmbLeft->AddItem(31*24,"-month");
		$cmbLeft->AddItem(365*24,"-year");
		$form->AddItem($cmbLeft);

		$cmbRight = new CComboBox("right",0,"submit()");
		$cmbRight->AddItem(0,S_RIGHT_DIR);
		$cmbRight->AddItem(1,"+1h");
		$cmbRight->AddItem(4,"+4h");
		$cmbRight->AddItem(24,"+24h");
		$cmbRight->AddItem(7*24,"+week");
		$cmbRight->AddItem(31*24,"+month");
		$cmbRight->AddItem(365*24,"+year");
		$form->AddItem($cmbRight);

		$form->AddItem(array(SPACE,
			new CTextBox("stime","yyyymmddhhmm",12),SPACE,
			new CButton("action","go"),
			new CButton("reset","reset")));

		foreach($saved_request as $item)
			if(isset($_REQUEST[$item]))
				$form->AddVar($item,$_REQUEST[$item]);

		show_table_header(
			S_NAVIGATE,
			$form);

		return;
	}

	function ImageStringTTF($image, $font, $x, $y, $string, $color)
	{
		$fontSize = 6;
		$ttf = "/usr/share/fonts/ja/TrueType/kochi-gothic-subst.ttf";

		switch ($font)
		{
			case 0: $fontSize = 6; break;
			case 1: $fontSize = 7; break;
			case 2: $fontSize = 9; break;
			case 3: $fontSize = 10; break;
			case 4: $fontSize = 11; break;
			case 5: $fontSize = 12; break;
			default: $fontSize = 6; break;
		}

		$ar = imagettfbbox($fontSize, 0, $ttf, $string);
		ImageTTFText($image, $fontSize, 0, $x, $y + abs($ar[1] - $ar[7]), $color, $ttf, $string);

		return 0;
	}

?>
