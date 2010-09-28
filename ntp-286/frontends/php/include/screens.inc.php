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
	function	screen_accessiable($screenid,$perm)
	{
		global $USER_DETAILS;

		$result = false;

		if(DBselect('select screenid from screens where screenid='.$screenid.
			' and '.DBin_node('screenid', get_current_nodeid($perm))))
		{
			$result = true;
			
			$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, PERM_MODE_LT);
			$denyed_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY, PERM_MODE_LT);
			
			$db_result = DBselect("select * from screens_items where screenid=".$screenid);
			while(($ac_data = DBfetch($db_result)) && $result)
			{
				switch($ac_data['resourcetype'])
				{
					case SCREEN_RESOURCE_GRAPH:
						$itemid = array();

						$db_gitems = DBselect("select distinct itemid from graphs_items ".
							" where graphid=".$ac_data['resourceid']);
						
						while($gitem_data = DBfetch($db_gitems)) array_push($itemid, $gitem_data['itemid']);
						
						if(count($itemid) == 0) $itemid = array(-1);
						// break; /* use same processing as items */
					case SCREEN_RESOURCE_SIMPLE_GRAPH:
						// break; /* use same processing as items */
					case SCREEN_RESOURCE_PLAIN_TEXT:
						if(!isset($itemid))
							$itemid = array($ac_data['resourceid']);

						if(DBfetch(DBselect("select itemid from items where itemid in (".implode(',',$itemid).") ".
							" and hostid in (".$denyed_hosts.")")))
						{
							$result = false;
						}	

						unset($itemid);
						break;
					case SCREEN_RESOURCE_MAP:
						$result &= sysmap_accessiable($ac_data['resourceid'], PERM_READ_ONLY);
						break;
					case SCREEN_RESOURCE_SCREEN:
						$result &= screen_accessiable($ac_data['resourceid'],PERM_READ_ONLY);
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

        function        update_screen($screenid,$name,$hsize,$vsize)
        {
                $sql="update screens set name=".zbx_dbstr($name).",hsize=$hsize,vsize=$vsize where screenid=$screenid";
                return  DBexecute($sql);
        }

        function        delete_screen($screenid)
        {
                $result=DBexecute("delete from screens_items where screenid=$screenid");
                if(!$result)	return  $result;

                $result=DBexecute("delete from screens_items where resourceid=$screenid and resourcetype=".SCREEN_RESOURCE_SCREEN);
                if(!$result)	return  $result;

		$result=DBexecute('delete from slides where screenid='.$screenid);
		if(!$result)    return  $result;

                return  DBexecute("delete from screens where screenid=$screenid");
        }

        function add_screen_item($resourcetype,$screenid,$x,$y,$resourceid,$width,$height,$colspan,$rowspan,$elements,$valign,$halign,$style,$url)
        {
                $sql="delete from screens_items where screenid=$screenid and x=$x and y=$y";
                DBexecute($sql);
		$screenitemid=get_dbid("screens_items","screenitemid");
                $result=DBexecute("insert into screens_items (screenitemid,resourcetype,screenid,x,y,resourceid,width,height,".
			" colspan,rowspan,elements,valign,halign,style,url) ".
			" values ($screenitemid,$resourcetype,$screenid,$x,$y,$resourceid,".
			" $width,$height,$colspan,$rowspan,$elements,$valign,$halign,$style,".
			zbx_dbstr($url).")");

		if(!$result)
			return $result;

		return $screenitemid;
        }

        function update_screen_item($screenitemid,$resourcetype,$resourceid,$width,$height,$colspan,$rowspan,$elements,$valign,$halign,$style,$url)
        {
                return  DBexecute("update screens_items set resourcetype=$resourcetype,resourceid=$resourceid,".
			"width=$width,height=$height,colspan=$colspan,rowspan=$rowspan,elements=$elements,valign=$valign,".
			"halign=$halign,style=$style,url=".zbx_dbstr($url)." where screenitemid=$screenitemid");
        }

        function delete_screen_item($screenitemid)
        {
                $sql="delete from screens_items where screenitemid=$screenitemid";
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

	function	check_screen_recursion($mother_screenid, $child_screenid)
	{
			if($mother_screenid == $child_screenid)	return TRUE;

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
			return new CIFrame('screens.php?config=1&fullscreen=2&elementid='.$slideshowid.'&step='.$curr_step.
					'&period='.$effectiveperiod.url_param('stime').url_param('from'));
		}

		$slide_data = DBfetch(DBselect('select sl.screenid,sl.delay,ss.delay as ss_delay from slides sl,slideshows ss '.
				       ' where ss.slideshowid='.$slideshowid.' and ss.slideshowid=sl.slideshowid and sl.step='.$curr_step));

		if( $slide_data['delay'] <= 0 )
		{
			$slide_data['delay'] = $slide_data['ss_delay'];
		}

		Redirect('screens.php?config=1&fullscreen=2&elementid='.$slideshowid.'&step='.($curr_step + 1).
				'&period='.$effectiveperiod.url_param('stime').url_param('from'),
				$slide_data['delay']);

		return get_screen($slide_data['screenid'],2,$effectiveperiod);
	}

	// editmode: 0 - view with actions, 1 - edit mode, 2 - view without any actions
	function get_screen($screenid, $editmode, $effectiveperiod=NULL)
	{
		if(!screen_accessiable($screenid, $editmode ? PERM_READ_WRITE : PERM_READ_ONLY))
			access_deny();
		
		if(is_null($effectiveperiod)) 
			$effectiveperiod = 3600;

		$result=DBselect("select name,hsize,vsize from screens where screenid=$screenid");
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
	
		for($r=0;$r<$row["vsize"];$r++)
		{
			$new_cols = array();
			for($c=0;$c<$row["hsize"];$c++)
			{
				$item = array();
				if(isset($skip_field[$r][$c]))		continue;
				
				$iresult=DBSelect("select * from screens_items".
					" where screenid=$screenid and x=$c and y=$r");

				$irow		= DBfetch($iresult);
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
				}
				else
				{
					$screenitemid	= 0;
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
				}

				if($editmode == 1 && $screenitemid!=0)
					 $action = "screenedit.php?form=update".url_param("screenid").
                                        	"&screenitemid=$screenitemid#form";
				elseif ($editmode == 1 && $screenitemid==0)
					$action = "screenedit.php?form=update".url_param("screenid")."&x=$c&y=$r#form";
				else
					$action = NULL;

				if($editmode == 1 && isset($_REQUEST["form"]) && 
					isset($_REQUEST["x"]) && $_REQUEST["x"]==$c &&
					isset($_REQUEST["y"]) && $_REQUEST["y"]==$r)
				{ // click on empty field
					$item = get_screen_item_form();
				}
				elseif($editmode == 1 && isset($_REQUEST["form"]) &&
					isset($_REQUEST["screenitemid"]) && $_REQUEST["screenitemid"]==$screenitemid)
				{ // click on element
					$item = get_screen_item_form();
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_GRAPH) )
				{
					if($editmode == 0)
						$action = "charts.php?graphid=$resourceid".url_param("period").
                                                        url_param("inc").url_param("dec");

					$item = new CLink(
						new CImg("chart2.php?graphid=$resourceid&width=$width&height=$height".
							"&period=$effectiveperiod".url_param("stime").url_param("from")),
						$action
						);
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SIMPLE_GRAPH) )
				{
					if($editmode == 0)
						$action = "history.php?action=showgraph&itemid=$resourceid".
                                                        url_param("period").url_param("inc").url_param("dec");

					$item = new CLink(
						new CImg("chart.php?itemid=$resourceid&width=$width&height=$height".
							"&period=$effectiveperiod".url_param("stime").url_param("from")),
						$action
						);
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_MAP) )
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
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_PLAIN_TEXT) )
				{
					$item = array(get_screen_plaintext($resourceid,$elements));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_HOSTS_INFO) )
				{
					$item = array(new CHostsInfo($style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_TRIGGERS_INFO) )
				{
					$item = array(new CTriggersInfo($style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SERVER_INFO) )
				{
					$item = array(new CServerInfo());
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_CLOCK) )
				{
					$item = new CFlashClock($width, $height, $style, $action);
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SCREEN) )
				{
					$item = array(get_screen($resourceid, 2, $effectiveperiod));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_TRIGGERS_OVERVIEW) )
				{
					$item = array(get_triggers_overview($resourceid));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_DATA_OVERVIEW) )
				{
					$item = array(get_items_data_overview($resourceid));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_URL) )
				{
					$item = array(new CIFrame($url,$width,$height,"auto"));
					if($editmode == 1)	array_push($item,BR,new CLink(S_CHANGE,$action));
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_ACTIONS) )
				{
					$item = array(get_history_of_actions(0, $elements));
					if($editmode == 1)      array_push($item,new CLink(S_CHANGE,$action));
				}
				elseif( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_EVENTS) )
				{
					$item = array(get_history_of_triggers_events(0, $elements));
					if($editmode == 1)      array_push($item,new CLink(S_CHANGE,$action));
				}
				else
				{
					$item = array(SPACE);
					if($editmode == 1)	array_push($item,BR,new CLink(S_CHANGE,$action));
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

	function	slideshow_accessiable($slideshowid, $perm)
	{
		$result = false;

		if(DBselect('select slideshowid from slideshows where slideshowid='.$slideshowid.
			' and '.DBin_node('slideshowid', get_current_nodeid($perm))))
		{
			$result = true;
			$db_slides = DBselect('select distinct screenid from slides where slideshowid='.$slideshowid);
			while($slide_data = DBfetch($db_slides))
			{
				if( !($result = screen_accessiable($slide_data["screenid"], PERM_READ_ONLY)) ) break;
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
		if(!screen_accessiable($slide["screenid"], PERM_READ_ONLY)) return false;

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

	function	update_slideshow($slideshowid, $name, $delay, $slides)
	{
		foreach($slides as $slide)
		{
			if( !validate_slide($slide) )
				return false;
		}

		if( !($result = DBexecute('update slideshows set name='.zbx_dbstr($name).',delay='.$delay.' where slideshowid='.$slideshowid)) )
			return false;

		DBexecute('delete from slides where slideshowid='.$slideshowid);

		$i = 0;
		foreach($slides as $slide)
		{
			$slideid = get_dbid('slides','slideid');
			if( !($result = DBexecute('insert into slides (slideid,slideshowid,screenid,step,delay) '.
				' values ('.$slideid.','.$slideshowid.','.$slide['screenid'].','.($i++).','.$slide['delay'].')')) )
			{
				return false;
			}
		}
		
		return true;
	}

	function	delete_slideshow($slideshowid)
	{
		return (
			DBexecute('delete from slideshows where slideshowid='.$slideshowid) &&
			DBexecute('delete from slides where slideshowid='.$slideshowid)
		);
	}


?>
