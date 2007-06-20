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

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	add_graph($name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,$showtriggers,$graphtype=GRAPH_TYPE_NORMAL,$templateid=0)
	{
		$graphid = get_dbid("graphs","graphid");

		$result=DBexecute("insert into graphs".
			" (graphid,name,width,height,yaxistype,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype)".
			" values ($graphid,".zbx_dbstr($name).",$width,$height,$yaxistype,$yaxismin,".
			" $yaxismax,$templateid,$showworkperiod,$showtriggers,$graphtype)");
		if($result)
		{
			info("Graph '$name' added");
			$result = $graphid;
		}
		return $result;
	}

	function	add_graph_with_items($name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,$showtriggers,$graphtype=GRAPH_TYPE_NORMAL,$items=array(),$templateid=0)
	{
		if($result = add_graph($name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,$showtriggers,$graphtype,$templateid))
		{
			foreach($items as $gitem)
			{
				if(!add_item_to_graph(
					$result,
					$gitem['itemid'],
					$gitem['color'],
					$gitem['drawtype'],
					$gitem['sortorder'],
					$gitem['yaxisside'],
					$gitem['calc_fnc'],
					$gitem['type'],
					$gitem['periods_cnt']))
				{
					delete_graph($result);
					return false;
				}
				
			}
		}
		return $result;
	}
	
	# Update Graph

	function	update_graph($graphid,$name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,$showtriggers,$graphtype=GRAPH_TYPE_NORMAL,$templateid=0)
	{
		$g_graph = get_graph_by_graphid($graphid);

		$graphs = get_graphs_by_templateid($graphid);
		while($graph = DBfetch($graphs))
		{
			$result = update_graph($graph["graphid"],$name,$width,
				$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,$showtriggers,$graphtype,$graphid);
			if(!$result)
				return $result;
		}

		$result = DBexecute("update graphs set name=".zbx_dbstr($name).",width=$width,height=$height,".
			"yaxistype=$yaxistype,yaxismin=$yaxismin,yaxismax=$yaxismax,templateid=$templateid,".
			"show_work_period=$showworkperiod,show_triggers=$showtriggers,graphtype=$graphtype ".
			"where graphid=$graphid");
		if($result)
		{
			if($g_graph['graphtype'] != $graphtype && $graphtype == GRAPH_TYPE_STACKED)
			{
				$result = DBexecute('update graphs_items set calc_fnc='.CALC_FNC_AVG.',drawtype=1,type='.GRAPH_ITEM_SIMPLE.
					' where graphid='.$graphid);
			}

			info("Graph '".$g_graph["name"]."' updated");
		}
		return $result;
	}
	
	function	update_graph_with_items($graphid,$name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,$showtriggers,$graphtype=GRAPH_TYPE_NORMAL,$items=array(),$templateid=0)
	{
		$result = update_graph($graphid,$name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$showworkperiod,
						$showtriggers,$graphtype,$templateid);

		if($result)
		{
			$db_graphs_items = DBselect('select gitemid from graphs_items where graphid='.$graphid);
			while($gitem_data = DBfetch($db_graphs_items))
			{
				delete_graph_item($gitem_data['gitemid']);
			}

			foreach($items as $gitem)
			{
				if(!add_item_to_graph(
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
					delete_graph($graphid);
					return false;
				}
			}
		}
		return $result;
	}
	
	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	delete_graph($graphid)
	{
		$graph = get_graph_by_graphid($graphid);

		$chd_graphs = get_graphs_by_templateid($graphid);
		while($chd_graph = DBfetch($chd_graphs))
		{// recursion
			$result = delete_graph($chd_graph["graphid"]);
			if(!$result)
				return $result;
		}

		// delete graph
		$result=DBexecute("delete from graphs_items where graphid=$graphid");
		if(!$result)
			return	$result;

		$result = DBexecute("delete from graphs where graphid=$graphid");
		if($result)
		{	
			info("Graph '".$graph["name"]."' deleted");
		}
		return $result;
	}

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

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	add_item_to_graph($graphid,$itemid,$color,$drawtype,$sortorder,$yaxisside,$calc_fnc,$type,$periods_cnt)
	{
		$gitemid=get_dbid("graphs_items","gitemid");
		$result = DBexecute("insert into graphs_items".
			" (gitemid,graphid,itemid,color,drawtype,sortorder,yaxisside,calc_fnc,type,periods_cnt)".
			" values ($gitemid,$graphid,$itemid,".zbx_dbstr($color).",$drawtype,$sortorder,$yaxisside,$calc_fnc,$type,$periods_cnt)");

		$item = get_item_by_itemid($itemid);
		$graph = get_graph_by_graphid($graphid);

		$host = get_host_by_itemid($itemid);
		if($gitemid && $host["status"]==HOST_STATUS_TEMPLATE)
		{// add to child graphs
			$item_num = DBfetch(DBselect(
				'select count(*) as num from graphs_items where graphid='.$graphid
			));

			if($item_num['num'] == 1)
			{// create graphs for childs with item
				$chd_hosts = get_hosts_by_templateid($host["hostid"]);
				while($chd_host = DBfetch($chd_hosts))
				{
					$new_graphid = add_graph($graph["name"],$graph["width"],$graph["height"],
						$graph["yaxistype"],$graph["yaxismin"],$graph["yaxismax"],$graph["show_work_period"],
						$graph["show_triggers"],$graph["graphtype"],$graph["graphid"]);

					if(!$new_graphid)
					{
						$result = $new_graphid;
						break;
					}
					$db_items = DBselect("select itemid from items".
						" where key_=".zbx_dbstr($item["key_"]).
						" and hostid=".$chd_host["hostid"]);
					$db_item = DBfetch($db_items);
					if(!$db_item)
					{
						$result = FALSE;
						break;
					}
				// recursion
					$result = add_item_to_graph($new_graphid,$db_item["itemid"],
						$color,$drawtype,$sortorder,$yaxisside,$calc_fnc,$type,$periods_cnt);

					if(!$result)
						break;
					
				}
			}
			else
			{// copy items to childs
				$childs = get_graphs_by_templateid($graphid);
				while($child = DBfetch($childs))
				{
			!		$chd_hosts = get_hosts_by_graphid($child["graphid"]);
					$chd_host = DBfetch($chd_hosts);
					$db_items = DBselect("select itemid from items".
						" where key_=".zbx_dbstr($item["key_"]).
						" and hostid=".$chd_host["hostid"]);
					$db_item = DBfetch($db_items);
					if(!$db_item)
					{
						$result = FALSE;
						break;
					}
				// recursion
					$result = add_item_to_graph($child["graphid"],$db_item["itemid"],
						$color,$drawtype,$sortorder,$yaxisside,$calc_fnc,$type,$periods_cnt);
					if(!$result)
						break;
				}
				
			}
			if(!$result && $graph["templateid"]==0)
			{// remove only main graph item
				delete_graph_item($gitemid);
				return $result;
			}
		}
		if($result)
		{
			info("Added Item '".$item["description"]."' for graph '".$graph["name"]."'");
			$result = $gitemid;
		}

		return $result;
	}
	
	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	delete_graph_item($gitemid)
	{
		
		$gitem = get_graphitem_by_gitemid($gitemid);

		$graph = get_graph_by_gitemid($gitemid);
		$childs = get_graphs_by_templateid($graph["graphid"]);
		while($child = DBfetch($childs))
		{
			$chd_gitems = get_graphitems_by_graphid($child["graphid"]);
			while($chd_gitem = DBfetch($chd_gitems))
			{
				if(cmp_graphitems($gitem, $chd_gitem))	continue;

			// recursion
				$result = delete_graph_item($chd_gitem["gitemid"]);
				if(!$result)
					return $reslut;
				break;
			}
		}

		$result = DBexecute("delete from graphs_items where gitemid=$gitemid");
		if($result)
		{
			$item = get_item_by_itemid($gitem["itemid"]);
			info("Item '".$item["description"]."' deleted from graph '".$graph["name"]."'");

			$graph_items = get_graphitems_by_graphid($graph["graphid"]);
			if($graph["templateid"]>0 && !DBfetch($graph_items))
			{
				return delete_graph($graph["graphid"]);
			}
		}
		return $result;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	delete_template_graphs($hostid, $templateid = null, $unlink_mode = false)
	{
		$db_graphs = get_graphs_by_hostid($hostid);
		while($db_graph = DBfetch($db_graphs))
		{
			if($db_graph["templateid"] == 0)
				continue;

			if( !is_null($templateid) )
			{
				if( !is_array($templateid) ) $templateid=array($templateid);

				$tmp_host = DBfetch(get_hosts_by_graphid($db_graph["templateid"]));

				if( !in_array($tmp_host["hostid"], $templateid))
					continue;
			}

			if($unlink_mode)
			{
				if(DBexecute("update graphs set templateid=0 where graphid=".$db_graph["graphid"]))
				{
					info("Graph '".$db_graph["name"]."' unlinked");
				}	
			}
			else
			{
				delete_graph($db_graph["graphid"]);
			}
		}
	}
	
	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
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

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	copy_graph_to_host($graphid, $hostid, $copy_mode = false)
	{
		$db_graph = get_graph_by_graphid($graphid);
		$new_graphid = add_graph($db_graph["name"],$db_graph["width"],$db_graph["height"],$db_graph["yaxistype"],
			$db_graph["yaxismin"],$db_graph["yaxismax"],$db_graph["show_work_period"],$db_graph["show_triggers"], 
			$db_graph["graphtype"],$copy_mode ? 0 : $graphid
			);

		if(!$new_graphid)
			return $new_graphid;

		$result = copy_graphitems_for_host($graphid, $new_graphid, $hostid);
		if(!$result)
		{
			delete_graph($new_graphid);
		}
		return $result;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	copy_graphitems_for_host($src_graphid,$dist_graphid,$hostid)
	{
		$src_graphitems=get_graphitems_by_graphid($src_graphid);
		while($src_graphitem=DBfetch($src_graphitems))
		{
			$src_item=get_item_by_itemid($src_graphitem["itemid"]);
			$host_items=get_items_by_hostid($hostid);
			$item_exist=0;
			while($host_item=DBfetch($host_items))
			{
				if($src_item["key_"]!=$host_item["key_"])	continue;

				$item_exist=1;
				$host_itemid=$host_item["itemid"];

				$result = add_item_to_graph($dist_graphid,$host_itemid,$src_graphitem["color"],
					$src_graphitem["drawtype"],$src_graphitem["sortorder"],
					$src_graphitem["yaxisside"],$src_graphitem["calc_fnc"],
					$src_graphitem["type"],$src_graphitem["periods_cnt"]);
				if(!$result)
				{
					error('Can\'t add key ['.$host_item['key_'].']');
					return $result;
				}
				break;
			}
			if($item_exist==0)
			{
				error('Missed key ['.$src_item['key_'].']');
				return FALSE;
			}
		}
		return TRUE;
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
?>
