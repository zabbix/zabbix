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
	
	require_once "include/events.inc.php";
	require_once "include/actions.inc.php";
?>
<?php
	function	screen_accessible($screenid,$perm)
	{
		global $USER_DETAILS;

		$result = false;

		if(DBfetch(DBselect('SELECT screenid FROM screens WHERE screenid='.$screenid.' AND '.DBin_node('screenid', get_current_nodeid($perm)))))
		{
			$result = true;
			$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
			
			$db_result = DBselect('SELECT * FROM screens_items WHERE screenid='.$screenid);
			while(($ac_data = DBfetch($db_result)) && $result){
				switch($ac_data['resourcetype']){
					case SCREEN_RESOURCE_GRAPH:
						$itemid = array();

						$db_gitems = DBselect('SELECT DISTINCT itemid '.
										' FROM graphs_items '.
										' WHERE graphid='.$ac_data['resourceid']);
						
						while($gitem_data = DBfetch($db_gitems)) array_push($itemid, $gitem_data['itemid']);
						
						if(count($itemid) == 0) $itemid = array(-1);
						// break; /* use same processing as items */
					case SCREEN_RESOURCE_SIMPLE_GRAPH:
						// break; /* use same processing as items */
					case SCREEN_RESOURCE_PLAIN_TEXT:
						if(!isset($itemid))
							$itemid = array($ac_data['resourceid']);

						if(DBfetch(DBselect('SELECT itemid '.
										' FROM items '.
										' WHERE itemid IN ('.implode(',',$itemid).') '.
											' AND hostid NOT IN ('.$available_hosts.')')))
						{
							$result = false;
						}	

						unset($itemid);
						break;
					case SCREEN_RESOURCE_MAP:
						$result &= sysmap_accessible($ac_data['resourceid'], PERM_READ_ONLY);
						break;
					case SCREEN_RESOURCE_SCREEN:
						$result &= screen_accessible($ac_data['resourceid'],PERM_READ_ONLY);
						break;
					case SCREEN_RESOURCE_SERVER_INFO:
					case SCREEN_RESOURCE_HOSTS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
					case SCREEN_RESOURCE_DATA_OVERVIEW:
					case SCREEN_RESOURCE_CLOCK:
					case SCREEN_RESOURCE_URL:
					case SCREEN_RESOURCE_ACTIONS:
					case SCREEN_RESOURCE_EVENTS:
						/* skip */
						break;
				}
			}
		}
		return $result;
	}

        function        add_screen($name,$hsize,$vsize)
        {
		$screenid=get_dbid("screens","screenid");
                $sql="insert into screens (screenid,name,hsize,vsize) values ($screenid,".zbx_dbstr($name).",$hsize,$vsize)";
                $result=DBexecute($sql);

		if(!$result)
			return $result;

		return $screenid;
        }

        function update_screen($screenid,$name,$hsize,$vsize)
        {
                $sql="update screens set name=".zbx_dbstr($name).",hsize=$hsize,vsize=$vsize where screenid=$screenid";
                return  DBexecute($sql);
        }

        function delete_screen($screenid){
			$result=DBexecute('DELETE FROM screens_items where screenid='.$screenid);
			$result&=DBexecute('DELETE FROM screens_items where resourceid='.$screenid.' and resourcetype='.SCREEN_RESOURCE_SCREEN);
			$result&=DBexecute('DELETE FROM slides where screenid='.$screenid);
			$result&=DBexecute("DELETE FROM profiles WHERE idx='web.favorite.screenids' AND source='screenid' AND value='$screenid'");
			$result&=DBexecute('DELETE FROM screens where screenid='.$screenid);	
		return	$result;
        }

        function add_screen_item($resourcetype,$screenid,$x,$y,$resourceid,$width,$height,$colspan,$rowspan,$elements,$valign,$halign,$style,$url,$dynamic){
			$sql='DELETE FROM screens_items WHERE screenid='.$screenid.' and x='.$x.' and y='.$y;
			DBexecute($sql);
			
			$screenitemid=get_dbid("screens_items","screenitemid");
			$result=DBexecute('INSERT INTO screens_items '.
								'(screenitemid,resourcetype,screenid,x,y,resourceid,width,height,'.
								' colspan,rowspan,elements,valign,halign,style,url,dynamic) '.
							' VALUES '.
								"($screenitemid,$resourcetype,$screenid,$x,$y,$resourceid,$width,$height,$colspan,".
								"$rowspan,$elements,$valign,$halign,$style,".zbx_dbstr($url).",$dynamic)");

			if(!$result) return $result;
		return $screenitemid;
        }

        function update_screen_item($screenitemid,$resourcetype,$resourceid,$width,$height,$colspan,$rowspan,$elements,$valign,$halign,$style,$url,$dynamic){
			return  DBexecute("UPDATE screens_items SET ".
								"resourcetype=$resourcetype,"."resourceid=$resourceid,"."width=$width,".
								"height=$height,colspan=$colspan,rowspan=$rowspan,elements=$elements,".
								"valign=$valign,halign=$halign,style=$style,url=".zbx_dbstr($url).",dynamic=$dynamic".
							" WHERE screenitemid=$screenitemid");
        }

        function delete_screen_item($screenitemid)
        {
                $sql="DELETE FROM screens_items where screenitemid=$screenitemid";
                return  DBexecute($sql);
        }

	function	get_screen_by_screenid($screenid)
	{
		$result = DBselect("select * from screens where screenid=$screenid");
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		// error("No screen with screenid=[$screenid]");
		return FALSE;
	}

	function check_screen_recursion($mother_screenid, $child_screenid){
			if((bccomp($mother_screenid , $child_screenid)==0))	return TRUE;

			$db_scr_items = DBselect("select resourceid from screens_items where".
				" screenid=$child_screenid and resourcetype=".SCREEN_RESOURCE_SCREEN);
			while($scr_item = DBfetch($db_scr_items))
			{
				if(check_screen_recursion($mother_screenid,$scr_item["resourceid"]))
					return TRUE; 
			}
			return FALSE;
	}
	


	function get_slideshow($slideshowid, $step, $effectiveperiod=NULL)
	{
		$slide_data = DBfetch(DBselect('select min(step) as min_step, max(step) as max_step from slides '.
					' where slideshowid='.$slideshowid));

		if(!$slide_data || is_null($slide_data['min_step']))
		{
			return new CTableInfo(S_NO_SLIDES_DEFINED);
		}

		if(!isset($step) || $step < $slide_data['min_step'] || $step > $slide_data['max_step'])
		{
			$curr_step = $slide_data['min_step'];
		}
		else
		{
			$curr_step = $step;
		}
		
		if(!isset($step))
		{
			$iframe = new CIFrame('screens.php?config=1&fullscreen=2&elementid='.$slideshowid.'&step='.$curr_step.
					'&period='.$effectiveperiod.url_param('stime').url_param('from'),'99%');
					
			return $iframe;
		}

		$slide_data = DBfetch(DBselect('select sl.screenid,sl.delay,ss.delay as ss_delay from slides sl,slideshows ss '.
				       ' where ss.slideshowid='.$slideshowid.' and ss.slideshowid=sl.slideshowid and sl.step='.$curr_step));

		if( $slide_data['delay'] <= 0 )
		{
			$slide_data['delay'] = $slide_data['ss_delay'];
		}

		simple_js_redirect('screens.php?config=1&fullscreen=2&elementid='.$slideshowid.'&step='.($curr_step + 1).
				'&period='.$effectiveperiod.url_param('stime').url_param('from'),
				$slide_data['delay']);

		return get_screen($slide_data['screenid'],2,$effectiveperiod);
	}

	// editmode: 0 - view with actions, 1 - edit mode, 2 - view without any actions
	function get_screen($screenid, $editmode, $effectiveperiod=NULL)
	{
		if(!screen_accessible($screenid, $editmode ? PERM_READ_WRITE : PERM_READ_ONLY))
			access_deny();
		
		if(is_null($effectiveperiod)) 
			$effectiveperiod = ZBX_MIN_PERIOD;

		$result=DBselect('SELECT name,hsize,vsize FROM screens WHERE screenid='.$screenid);
		$row=DBfetch($result);
		if(!$row) return new CTableInfo(S_NO_SCREENS_DEFINED);

		for($r=0;$r<$row["vsize"];$r++)
		{
			for($c=0;$c<$row["hsize"];$c++)
			{
				if(isset($skip_field[$r][$c]))	continue;

				$sql="select * from screens_items where screenid=$screenid and x=$c and y=$r";
				$iresult=DBSelect($sql);
				$irow=DBfetch($iresult);
				if($irow)
				{
					$colspan=$irow["colspan"];
					$rowspan=$irow["rowspan"];
				} else {
					$colspan=0;
					$rowspan=0;
				}
				for($i=0; $i < $rowspan || $i==0; $i++){
					for($j=0; $j < $colspan || $j==0; $j++){
						if($i!=0 || $j!=0)
							$skip_field[$r+$i][$c+$j]=1;
					}
				}
			}
		}
		$table = new CTable(
			new CLink("No rows in screen ".$row["name"],"screenconf.php?config=0&form=update&screenid=".$screenid),
			($editmode == 0 || $editmode == 2) ? "screen_view" : "screen_edit");
		$table->AddOption('id','iframe');
	
		for($r=0;$r<$row["vsize"];$r++)
		{
			$new_cols = array();
			for($c=0;$c<$row["hsize"];$c++)
			{
				$item = array();
				if(isset($skip_field[$r][$c]))		continue;
				
				$iresult=DBSelect("select * from screens_items".
					" where screenid=$screenid and x=$c and y=$r");

				$irow = DBfetch($iresult);
				if($irow)
				{
					$screenitemid	= $irow["screenitemid"];
					$resourcetype	= $irow["resourcetype"];
					$resourceid	= $irow["resourceid"];
					$width		= $irow["width"];
					$height		= $irow["height"];
					$colspan	= $irow["colspan"];
					$rowspan	= $irow["rowspan"];
					$elements	= $irow["elements"];
					$valign		= $irow["valign"];
					$halign		= $irow["halign"];
					$style		= $irow["style"];
					$url		= $irow["url"];
					$dynamic	= $irow['dynamic'];
				}
				else
				{
					$screenitemid	= 0;
					$resourcetype	= 0;
					$resourceid	= 0;
					$width		= 0;
					$height		= 0;
					$colspan	= 0;
					$rowspan	= 0;
					$elements	= 0;
					$valign		= VALIGN_DEFAULT;
					$halign		= HALIGN_DEFAULT;
					$style		= 0;
					$url		= "";
					$dynamic	= 0;
				}

				if($editmode == 1 && $screenitemid!=0)
					 $action = "screenedit.php?form=update".url_param("screenid").
                                        	"&screenitemid=$screenitemid#form";
				else if ($editmode == 1 && $screenitemid==0)
					$action = "screenedit.php?form=update".url_param("screenid")."&x=$c&y=$r#form";
				else
					$action = NULL;

				if($editmode == 1 && isset($_REQUEST["form"]) && 
					isset($_REQUEST["x"]) && $_REQUEST["x"]==$c &&
					isset($_REQUEST["y"]) && $_REQUEST["y"]==$r)
				{ // click on empty field
					$item = get_screen_item_form();
				}
				else if($editmode == 1 && isset($_REQUEST["form"]) &&
					isset($_REQUEST["screenitemid"]) && (bccomp($_REQUEST["screenitemid"], $screenitemid)==0))
				{ // click on element
					$item = get_screen_item_form();
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_GRAPH) )
				{
					if($editmode == 0)
						$action = "charts.php?graphid=$resourceid".url_param("period").url_param("stime");
														
					$graphid = null;						
					$graphtype = GRAPH_TYPE_NORMAL;
					$yaxis = 0;
					
// GRAPH & ZOOM features
					$sql = 'SELECT MAX(g.graphid) as graphid, MAX(g.graphtype) as graphtype, MIN(gi.yaxisside) as yaxissidel, MAX(gi.yaxisside) as yaxissider,'.
								' MAX(g.show_legend) as legend, MAX(g.show_3d) as show3d '.
							' FROM graphs g, graphs_items gi '.
							' WHERE g.graphid='.$resourceid.
								' AND gi.graphid=g.graphid ';
			
					$res = Dbselect($sql);
					while($graph=DBfetch($res)){
						$graphid = $graph['graphid'];
						$graphtype = $graph['graphtype'];
						$yaxis = $graph['yaxissider'];
						$yaxis = ($graph['yaxissidel'] == $yaxis)?($yaxis):(2);
						
						$legend = $graph['legend'];
						$graph3d = $graph['show3d'];
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
// Host feature
					if(($dynamic == SCREEN_DYNAMIC_ITEM) && isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0)){
						$def_items = array();
						$di_res = get_graphitems_by_graphid($resourceid);
						while( $gitem = DBfetch($di_res)){
							$def_items[] = $gitem;
						};
	
						$url='';
						if($new_items = get_same_graphitems_for_host($def_items, $_REQUEST['hostid'], false)){
							$url.= make_url_from_gitems($new_items);
						}
						
						$url= make_url_from_graphid($resourceid,false).$url;
					}
//-------------
					
					if(($graphtype == GRAPH_TYPE_PIE) || ($graphtype == GRAPH_TYPE_EXPLODED)){
						if(($dynamic==SCREEN_SIMPLE_ITEM) || empty($url)){
							$url="chart6.php?graphid=$resourceid";
						}
					
						$item = new CLink(
							new CImg($url.'&width='.$width.
											'&height='.$height.
											'&period='.$effectiveperiod.
											url_param('stime').
											'&legend='.$legend.
											'&graph3d='.$graph3d),
							$action
							);
					}
					else {
						if(($dynamic==SCREEN_SIMPLE_ITEM) || empty($url)){
							$url="chart2.php?graphid=$resourceid";
						}
						
						$dom_graph_id = 'graph_'.$screenitemid.'_'.$resourceid;
						$g_img = new CImg($url."&width=$width&height=$height"."&period=$effectiveperiod".url_param("stime"));
						$g_img->AddOPtion('id',$dom_graph_id);

						$item = new CLink($g_img,$action);

						if(!is_null($graphid) && ($editmode != 1)){
							insert_js('	A_SBOX["'.$dom_graph_id.'"] = new Object;'.
										'A_SBOX["'.$dom_graph_id.'"].shiftT = 17;'.
										'A_SBOX["'.$dom_graph_id.'"].shiftL = '.$shiftXleft.';'
									);
									
							if(isset($_REQUEST['stime'])){
								$stime = $_REQUEST['stime'];
								$stime = mktime(substr($stime,8,2),substr($stime,10,2),0,substr($stime,4,2),substr($stime,6,2),substr($stime,0,4));
							}
							else{
								$stime = 'null';
							}
							zbx_add_post_js('graph_zoom_init("'.$dom_graph_id.'",'.$stime.','.$effectiveperiod.','.$width.','.$height.', false);');
						}
					}
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SIMPLE_GRAPH) )
				{
					if($editmode == 0)
						$action = "history.php?action=showgraph&itemid=$resourceid".
                                                        url_param("period").url_param("inc").url_param("dec");

// Host feature
					if(($dynamic == SCREEN_DYNAMIC_ITEM) && isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0)){
						if($newitemid = get_same_item_for_host($resourceid,$_REQUEST['hostid'],false)){
							$resourceid = $newitemid;
						}
						else{
							$resourceid='';
						}
					}
//-------------
					$url = (empty($resourceid))?'chart3.php?':"chart.php?itemid=$resourceid&";
					$item = new CLink(
						new CImg($url."width=$width&height=$height"."&period=$effectiveperiod".url_param("stime")),
						$action
						);
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_MAP) )
				{
					$image_map = new CImg("map.php?noedit=1&sysmapid=$resourceid".
							"&width=$width&height=$height");
					if($editmode == 0)
					{
						$action_map = get_action_map_by_sysmapid($resourceid);
						$image_map->SetMap($action_map->GetName());
						$item = array($action_map,$image_map);
					} else {
						$item = new CLink($image_map, $action);
					}
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_PLAIN_TEXT) ){
// Host feature
					if(($dynamic == SCREEN_DYNAMIC_ITEM) && isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0)){
						if($newitemid = get_same_item_for_host($resourceid,$_REQUEST['hostid'],false)){
							$resourceid = $newitemid;
						}
						else{
							$resourceid=0;
						}
					}
//-------------
					$item = array(get_screen_plaintext($resourceid,$elements));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_HOSTS_INFO) )
				{
					$item = array(new CHostsInfo($resourceid, $style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_TRIGGERS_INFO) )
				{
					$item = array(new CTriggersInfo($style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SERVER_INFO) )
				{
					$item = array(new CServerInfo());
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_CLOCK) )
				{
					$item = new CFlashClock($width, $height, $style, $action);
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SCREEN) )
				{
					$item = array(get_screen($resourceid, 2, $effectiveperiod));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_TRIGGERS_OVERVIEW) )
				{
					$item = array(get_triggers_overview($resourceid,$style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_DATA_OVERVIEW) )
				{
					$item = array(get_items_data_overview($resourceid,$style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_URL) )
				{
					$item = array(new CIFrame($url,$width,$height,"auto"));
					if($editmode == 1)	array_push($item,BR(),new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_ACTIONS) )
				{
					$item = array(get_history_of_actions(0, $elements));
					if($editmode == 1)      array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_EVENTS) )
				{
					$item = array(get_history_of_triggers_events(0, $elements));
					if($editmode == 1)      array_push($item,new CLink(S_CHANGE,$action));
				}
				else
				{
					$item = array(SPACE);
					if($editmode == 1)	array_push($item,BR(),new CLink(S_CHANGE,$action));
				}

				$str_halign = "def";
				if($halign == HALIGN_CENTER)	$str_halign = "cntr";
				if($halign == HALIGN_LEFT)	$str_halign = "left";
				if($halign == HALIGN_RIGHT)	$str_halign = "right";

				$str_valign = "def";
				if($valign == VALIGN_MIDDLE)	$str_valign = "mdl";
				if($valign == VALIGN_TOP)	$str_valign = "top";
				if($valign == VALIGN_BOTTOM)	$str_valign = "bttm";

				$new_col = new CCol($item,$str_halign."_".$str_valign);

				if($colspan) $new_col->SetColSpan($colspan);
				if($rowspan) $new_col->SetRowSpan($rowspan);

				array_push($new_cols, $new_col);
			}
			$table->AddRow(new CRow($new_cols));
		}
		return $table;
	}

	function	slideshow_accessible($slideshowid, $perm)
	{
		$result = false;

		if(DBselect('select slideshowid from slideshows where slideshowid='.$slideshowid.
			' and '.DBin_node('slideshowid', get_current_nodeid($perm))))
		{
			$result = true;
			$db_slides = DBselect('select distinct screenid from slides where slideshowid='.$slideshowid);
			while($slide_data = DBfetch($db_slides))
			{
				if( !($result = screen_accessible($slide_data["screenid"], PERM_READ_ONLY)) ) break;
			}
		}
		return $result;
	}

	function	get_slideshow_by_slideshowid($slideshowid)
	{
		return DBfetch(DBselect('select * from slideshows where slideshowid='.$slideshowid));
	}

	function	validate_slide($slide)
	{
		if(!screen_accessible($slide["screenid"], PERM_READ_ONLY)) return false;

		if( !is_numeric($slide['delay']) ) return false;

		return true;
	}

	function	add_slideshow($name, $delay, $slides)
	{
		foreach($slides as $slide)
		{
			if( !validate_slide($slide) )
				return false;
		}

		$slideshowid = get_dbid('slideshows','slideshowid');
		$result = DBexecute('insert into slideshows (slideshowid,name,delay) '.
			' values ('.$slideshowid.','.zbx_dbstr($name).','.$delay.')');

		$i = 0;
		foreach($slides as $slide)
		{
			$slideid = get_dbid('slides','slideid');
			if( !($result = DBexecute('insert into slides (slideid,slideshowid,screenid,step,delay) '.
				' values ('.$slideid.','.$slideshowid.','.$slide['screenid'].','.($i++).','.$slide['delay'].')')) )
			{
				break;
			}
		}
		
		if( !$result )
		{
			delete_slideshow($slideshowid);
			return false;
		}
		return $slideshowid;
	}

	function	update_slideshow($slideshowid, $name, $delay, $slides){
		foreach($slides as $slide){
			if(!validate_slide($slide))
				return false;
		}

		if(!$result = DBexecute('update slideshows set name='.zbx_dbstr($name).',delay='.$delay.' where slideshowid='.$slideshowid))
			return false;

		DBexecute('DELETE FROM slides where slideshowid='.$slideshowid);

		$i = 0;
		foreach($slides as $slide){
			$slideid = get_dbid('slides','slideid');
			if( !($result = DBexecute('insert into slides (slideid,slideshowid,screenid,step,delay) '.
				' values ('.$slideid.','.$slideshowid.','.$slide['screenid'].','.($i++).','.$slide['delay'].')')) ){
				return false;
			}
		}
		
		return true;
	}

	function delete_slideshow($slideshowid){

		$result = DBexecute('DELETE FROM slideshows where slideshowid='.$slideshowid);
		$result &= DBexecute('DELETE FROM slides where slideshowid='.$slideshowid);
		$result &= DBexecute("DELETE FROM profiles WHERE idx='web.favorite.screenids' AND source='slideshowid' AND value='$slideshowid'");
		
		return $result;
	}
	

	# Show screen cell containing plain text values
	function	get_screen_plaintext($itemid,$elements){

		if($itemid == 0){
			$table = new CTableInfo(S_ITEM_NOT_EXISTS);
			$table->SetHeader(array(S_TIMESTAMP,S_ITEM));
			return $table;
		}

		global $DB;

		$item=get_item_by_itemid($itemid);
		switch($item["value_type"]){
			case ITEM_VALUE_TYPE_FLOAT:		$history_table = "history";		break;
			case ITEM_VALUE_TYPE_UINT64:	$history_table = "history_uint";	break;
			case ITEM_VALUE_TYPE_TEXT:		$history_table = "history_text";	break;
			case ITEM_VALUE_TYPE_LOG:		$history_table = "history_log";         break;
			default:						$history_table = "history_str";		break;
		}

		$sql='SELECT h.clock,h.value,i.valuemapid '.
			' FROM '.$history_table.' h, items i '.
			' WHERE h.itemid=i.itemid '.
				' AND i.itemid='.$itemid.
			' ORDER BY h.clock DESC';

		$result=DBselect($sql,$elements);

		$host = get_host_by_itemid($itemid);
		
		$table = new CTableInfo();
		$table->SetHeader(array(S_TIMESTAMP,item_description($host['host'].': '.$item["description"],$item["key_"])));
		
		while($row=DBfetch($result)){
			switch($item["value_type"])
			{
				case ITEM_VALUE_TYPE_TEXT:	
					if($DB['TYPE'] == "ORACLE")
					{
						if(isset($row["value"]))
						{
							$row["value"] = $row["value"]->load();
						}
						else
						{
							$row["value"] = "";
						}
					}
					/* do not use break */
				case ITEM_VALUE_TYPE_STR:	
					$value = nl2br(nbsp(htmlspecialchars($row["value"])));
					break;
				
				default:
					$value = $row["value"];
					break;
			}

			if($row["valuemapid"] > 0)
				$value = replace_value_by_map($value, $row["valuemapid"]);

			$table->AddRow(array(date(S_DATE_FORMAT_YMDHMS,$row["clock"]),	$value));
		}
		return $table;
	}

/*
* Function: 
*		check_dynamic_items
*
* Description:
*		Check if in screen are dynamic items, if so return TRUE, esle FALSE
*
* Author: 
*		Aly
*/

	function check_dynamic_items($screenid){
		$sql = 'SELECT screenitemid '.
			' FROM screens_items '.
			' WHERE screenid='.$screenid.
				' AND dynamic='.SCREEN_DYNAMIC_ITEM;
		if(DBfetch(DBselect($sql,1))) return TRUE;
	return FALSE;
	}
?>
