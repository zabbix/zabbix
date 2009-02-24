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
	require_once "include/images.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/triggers.inc.php";
	require_once "include/scripts.inc.php";
	require_once "include/maintenances.inc.php";
	
        /*
         * Function: map_link_drawtypes
         *
         * Description:
         *     Return available drawing types for links
         *
         * Author:
         *     Eugene Grigorjev 
         *
         */
	function	map_link_drawtypes()
	{
		return array(
				MAP_LINK_DRAWTYPE_LINE,
				MAP_LINK_DRAWTYPE_BOLD_LINE,
				(function_exists('imagesetstyle') ? MAP_LINK_DRAWTYPE_DOT : null),
				MAP_LINK_DRAWTYPE_DASHED_LINE
			    );
	}

        /*
         * Function: map_link_drawtype2str
         *
         * Description:
         *     Represent integer value of links drawing type into the string
         *
         * Author:
         *     Eugene Grigorjev 
         *
         */
        function	map_link_drawtype2str($drawtype)
        {
		switch($drawtype)
		{
			case MAP_LINK_DRAWTYPE_LINE:		$drawtype = "Line";		break;
			case MAP_LINK_DRAWTYPE_BOLD_LINE:	$drawtype = "Bold line";	break;
			case MAP_LINK_DRAWTYPE_DOT:		$drawtype = "Dot";		break;
			case MAP_LINK_DRAWTYPE_DASHED_LINE:	$drawtype = "Dashed line";	break;
			default: $drawtype = S_UNKNOWN;		break;
		}
		return $drawtype;
        }

        /*
         * Function: sysmap_accessiable
         *
         * Description:
         *     Check permission for map
         *
	 * Return: true on success

         * Author:
         *     Eugene Grigorjev 
         *
         */
	function	sysmap_accessiable($sysmapid,$perm)
	{
		global $USER_DETAILS;

		$result = false;

		if($db_result = DBselect('select * from sysmaps_elements where sysmapid='.$sysmapid.
			' and '.DBin_node('sysmapid', get_current_nodeid($perm))))
		{
			$result = true;
			
			$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, PERM_MODE_LT);
						
			while(($se_data = DBfetch($db_result)) && $result)
			{
				switch($se_data['elementtype'])
				{
					case SYSMAP_ELEMENT_TYPE_HOST:
						if(in_array($se_data['elementid'],explode(',',$denyed_hosts)))
						{
							$result = false;
						}
						break;
					case SYSMAP_ELEMENT_TYPE_MAP:
						$result &= sysmap_accessiable($se_data['elementid'], PERM_READ_ONLY);
						break;
					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						if( DBfetch(DBselect('select triggerid from triggers where triggerid='.$se_data['elementid'])) &&
						    !DBfetch(DBselect("select distinct t.*".
							" from triggers t,items i,functions f".
							" where f.itemid=i.itemid and t.triggerid=f.triggerid".
							" and i.hostid not in (".$denyed_hosts.") and t.triggerid=".$se_data['elementid'])))
						{
							$result = false;
						}
						break;
					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						if( DBfetch(DBselect('select groupid from groups where groupid='.$se_data['elementid'])) &&
						    in_array($se_data['elementid'],
							get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY, PERM_MODE_LT, PERM_RES_IDS_ARRAY)))
						{
							$result = false;
						}
						break;
				}
			}
		}
		else
		{
			if(DBselect('select sysmapid from sysmaps where sysmapid='.$sysmapid.
				' and '.DBin_node('sysmapid', get_current_nodeid($perm))))
					$result = true;
		}
		return $result;
	}

	function	get_sysmap_by_sysmapid($sysmapid)
	{
		$row = DBfetch(DBselect("select * from sysmaps where sysmapid=".$sysmapid));
		if($row)
		{
			return	$row;
		}
		error("No system map with sysmapid=[".$sysmapid."]");
		return false;
	}

	function	get_sysmaps_element_by_selementid($selementid)
	{
		$sql="select * from sysmaps_elements where selementid=$selementid"; 
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		else
		{
			error("No sysmap element with selementid=[$selementid]");
		}
		return	$result;
	}

	# Delete System Map

	function	delete_sysmap( $sysmapid )
	{
		$result = delete_sysmaps_elements_with_sysmapid($sysmapid);
		if(!$result)	return	$result;

		$result = DBexecute("delete from sysmaps_links where sysmapid=$sysmapid");
		if(!$result)	return	$result;

		$result = DBexecute("delete from sysmaps_elements where sysmapid=$sysmapid");
		if(!$result)	return	$result;

		return DBexecute("delete from sysmaps where sysmapid=$sysmapid");
	}

	# Update System Map

	function	update_sysmap($sysmapid,$name,$width,$height,$backgroundid,$label_type,$label_location,$status_view){
		return	DBexecute('UPDATE sysmaps SET '.
								'name='.zbx_dbstr($name).','.
								'width='.$width.','.
								'height='.$height.','.
								'backgroundid='.$backgroundid.','.
								'label_type='.$label_type.','.
								'label_location='.$label_location.','.
								'status_view='.$status_view.
							' WHERE sysmapid='.$sysmapid);
	}

	# Add System Map

	function	add_sysmap($name,$width,$height,$backgroundid,$label_type,$label_location,$status_view)
	{
		$sysmapid=get_dbid("sysmaps","sysmapid");

		$result=DBexecute("insert into sysmaps (sysmapid,name,width,height,backgroundid,label_type,label_location,status_view)".
			" values ($sysmapid,".zbx_dbstr($name).",$width,$height,".$backgroundid.",$label_type,$label_location,$status_view)");

		if(!$result)
			return $result;

		return $sysmapid;
	}

	function	add_link($sysmapid,$selementid1,$selementid2,$triggerid,$drawtype_off,$color_off,$drawtype_on,$color_on)
	{
		if($triggerid == 0)	$triggerid = 'NULL';

		$linkid=get_dbid("sysmaps_links","linkid");

		$result=DBexecute("insert into sysmaps_links".
			" (linkid,sysmapid,selementid1,selementid2,triggerid,drawtype_off,".
			"color_off,drawtype_on,color_on)".
			" values ($linkid,$sysmapid,$selementid1,$selementid2,$triggerid,$drawtype_off,".
			zbx_dbstr($color_off).",$drawtype_on,".zbx_dbstr($color_on).")");

		if(!$result)
			return $result;

		return $linkid;
	}

	function	update_link($linkid,$sysmapid,$selementid1,$selementid2,$triggerid,$drawtype_off,$color_off,$drawtype_on,$color_on)
	{
		if($triggerid == 0)	$triggerid = 'NULL';

		return	DBexecute("update sysmaps_links set ".
			"sysmapid=$sysmapid,selementid1=$selementid1,selementid2=$selementid2,".
			"triggerid=$triggerid,drawtype_off=$drawtype_off,color_off=".zbx_dbstr($color_off).",".
			"drawtype_on=$drawtype_on,color_on=".zbx_dbstr($color_on).
			" where linkid=$linkid");
	}

	function	delete_link($linkid)
	{
		return	DBexecute("delete from sysmaps_links where linkid=$linkid");
	}

        /*
         * Function: check_circle_elements_link
         *
         * Description:
         *     Check circeling of maps
         *
         * Author:
         *     Eugene Grigorjev 
         *
         */
	function	check_circle_elements_link($sysmapid,$elementid,$elementtype)
	{
		if($elementtype!=SYSMAP_ELEMENT_TYPE_MAP)	return FALSE;

		if($sysmapid == $elementid)	return TRUE;

		$db_elements = DBselect("select elementid, elementtype from sysmaps_elements".
			" where sysmapid=$elementid");

		while($element = DBfetch($db_elements))
		{
			if(check_circle_elements_link($sysmapid,$element["elementid"],$element["elementtype"]))
				return TRUE;
		}
		return FALSE;
	}

	# Add Element to system map

	function add_element_to_sysmap($sysmapid,$elementid,$elementtype,
						$label,$x,$y,$iconid_off,$iconid_unknown,$iconid_on,$iconid_maintenance,
						$url,$label_location)
	{
		if($label_location<0) $label_location='null';
		if(check_circle_elements_link($sysmapid,$elementid,$elementtype))
		{
			error("Circle link can't be created");
			return FALSE;
		}

		$selementid = get_dbid("sysmaps_elements","selementid");

		$result=DBexecute("insert into sysmaps_elements".
			" (selementid,sysmapid,elementid,elementtype,label,x,y,iconid_off,url,iconid_on,label_location,iconid_unknown,iconid_maintenance)".
			" values ($selementid,$sysmapid,$elementid,$elementtype,".zbx_dbstr($label).",
			$x,$y,$iconid_off,".zbx_dbstr($url).",$iconid_on,$label_location,$iconid_unknown,$iconid_maintenance)");

		if(!$result)
			return $result;

		return $selementid;
	}

	# Update Element from system map

	function	update_sysmap_element($selementid,$sysmapid,$elementid,$elementtype,
						$label,$x,$y,$iconid_off,$iconid_unknown,$iconid_on,$iconid_maintenance,
						$url,$label_location)
	{
		if($label_location<0) $label_location='null';
		if(check_circle_elements_link($sysmapid,$elementid,$elementtype))
		{
			error("Circle link can't be created");
			return FALSE;
		}

		return	DBexecute("update sysmaps_elements set elementid=$elementid,elementtype=$elementtype,".
			"label=".zbx_dbstr($label).",x=$x,y=$y,iconid_off=$iconid_off,url=".zbx_dbstr($url).
			",iconid_on=$iconid_on,label_location=$label_location,iconid_unknown=$iconid_unknown,iconid_maintenance=$iconid_maintenance".
			" where selementid=$selementid");
	}

	/******************************************************************************
	 *                                                                            *
	 * Purpose: Delete Element from sysmap definition                             *
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	delete_sysmaps_element($selementid)
	{
		$result=DBexecute("delete from sysmaps_links".
			" where selementid1=$selementid or selementid2=$selementid");

		if(!$result)		return	$result;

		return	DBexecute("delete from sysmaps_elements where selementid=$selementid");
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	delete_sysmaps_elements_with_hostid($hostid)
	{
		$db_elements = DBselect("select selementid from sysmaps_elements".
			" where elementid=$hostid and elementtype=".SYSMAP_ELEMENT_TYPE_HOST);
		while($db_element = DBfetch($db_elements))
		{
			delete_sysmaps_element($db_element["selementid"]);
		}
		return TRUE;
	}
	function	delete_sysmaps_elements_with_sysmapid($sysmapid)
	{
		$db_elements = DBselect("select selementid from sysmaps_elements".
			" where elementid=$sysmapid and elementtype=".SYSMAP_ELEMENT_TYPE_MAP);
		while($db_element = DBfetch($db_elements))
		{
			delete_sysmaps_element($db_element["selementid"]);
		}
		return TRUE;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	delete_sysmaps_elements_with_triggerid($triggerid)
	{
		$db_elements = DBselect("select selementid from sysmaps_elements".
			" where elementid=$triggerid and elementtype=".SYSMAP_ELEMENT_TYPE_TRIGGER);
		while($db_element = DBfetch($db_elements))
		{
			delete_sysmaps_element($db_element["selementid"]);
		}
		return TRUE;
	}
	function	delete_sysmaps_elements_with_groupid($groupid)
	{
		$db_elements = DBselect("select selementid from sysmaps_elements".
			" where elementid=$groupid and elementtype=".SYSMAP_ELEMENT_TYPE_HOST_GROUP);
		while($db_element = DBfetch($db_elements))
		{
			delete_sysmaps_element($db_element["selementid"]);
		}
		return TRUE;
	}

/*
 * Function: get_info_by_selementid
 *
 * Description:
 *     Retrive information for map element
 *
 * Author:
 *     Eugene Grigorjev 
 *
 */
	function	get_info_by_selementid($selementid,$view_status=0){
		$db_element = get_sysmaps_element_by_selementid($selementid);
		$info = get_info_by_selement($db_element,$view_status);
		
	return $info;
	}

	function get_info_by_selement($element,$view_status=0){
		global $colors;

		$el_name = '';
		$tr_info = array();
		$maintenance = array('status'=>false, 'maintenanceid'=>0);
		
		$el_type =& $element["elementtype"];

		$sql = array(
			SYSMAP_ELEMENT_TYPE_TRIGGER => 'select distinct t.triggerid, t.priority, t.value, t.description, t.expression, h.host '.
				' from triggers t, items i, functions f, hosts h '.
				' where t.triggerid='.$element['elementid'].
					' and h.hostid=i.hostid '.
					' and i.itemid=f.itemid '.
					' and f.triggerid=t.triggerid '.
					' and h.status='.HOST_STATUS_MONITORED.
					' and i.status='.ITEM_STATUS_ACTIVE,
			SYSMAP_ELEMENT_TYPE_HOST_GROUP => 'select distinct t.triggerid, t.priority, t.value,'.
					' t.description, t.expression, h.host, g.name as el_name '.
				' from items i,functions f,triggers t,hosts h,hosts_groups hg,groups g '.
				' where h.hostid=i.hostid and hg.groupid=g.groupid and g.groupid='.$element['elementid'].
					' and hg.hostid=h.hostid'.
					' and i.itemid=f.itemid'.
					' and f.triggerid=t.triggerid'.
					' and t.status='.TRIGGER_STATUS_ENABLED.
					' and h.status='.HOST_STATUS_MONITORED.
					' and i.status='.ITEM_STATUS_ACTIVE,
			SYSMAP_ELEMENT_TYPE_HOST => 'select distinct t.triggerid, t.priority, t.value, '.
					' t.description, t.expression, h.host, h.host as el_name, h.maintenanceid, h.maintenance_status '.
				' from items i,functions f,triggers t,hosts h '.
				' where h.hostid=i.hostid '.
					' and i.hostid='.$element['elementid'].
					' and i.itemid=f.itemid '.
					' and f.triggerid=t.triggerid '.
					' and t.status='.TRIGGER_STATUS_ENABLED.
					' and h.status='.HOST_STATUS_MONITORED.
					' and i.status='.ITEM_STATUS_ACTIVE
			);
		if( isset($sql[$el_type]) )
		{

			$db_triggers = DBselect($sql[$el_type]);
			$trigger = DBfetch($db_triggers);
			if($trigger)
			{				
				if(isset($trigger['el_name'])){
					$el_name = $trigger['el_name'];
				}
				else{
					$el_name = expand_trigger_description_by_data($trigger);
				}

				if(isset($trigger['maintenance_status']) && ($trigger['maintenance_status'] == 1)){
					$maintenance['status'] = true;
					$maintenance['maintenanceid'] = $trigger['maintenanceid'];
				}

				do {
					if(isset($_REQUEST['show_triggers']) && (TRIGGERS_OPTION_NOFALSEFORB == $_REQUEST['show_triggers'])){								
						$event_sql = 'SELECT e.eventid, e.value, e.clock, e.ms, e.objectid as triggerid, e.acknowledged, t.type '.
							' FROM events e, triggers t '.
							' WHERE e.object=0 AND e.objectid='.$trigger['triggerid'].
								' AND t.triggerid=e.objectid '.
								' AND e.acknowledged=0 '.
								' AND ((e.value='.TRIGGER_VALUE_TRUE.') OR ((e.value='.TRIGGER_VALUE_FALSE.') AND t.type='.TRIGGER_MULT_EVENT_DISABLED.'))'.
							' ORDER by e.object DESC, e.objectid DESC, e.eventid DESC';
						if($trigger_tmp = get_row_for_nofalseforb($trigger,$event_sql)){
//							$trigger = array_merge($trigger,$trigger_tmp);
							$trigger['value'] = TRIGGER_VALUE_TRUE;
						}

					}
					
					$type	=& $trigger['value'];

					if(!isset($tr_info[$type]))
						$tr_info[$type] = array('count' => 0);

					$tr_info[$type]['count']++;
					if(!isset($tr_info[$type]['priority']) || $tr_info[$type]['priority'] < $trigger["priority"])
					{
						$tr_info[$type]['priority']	= $trigger["priority"];
						if($el_type != SYSMAP_ELEMENT_TYPE_TRIGGER && $type!=TRIGGER_VALUE_UNKNOWN)
							$tr_info[$type]['info']		= expand_trigger_description_by_data($trigger);
					}
				} while ($trigger = DBfetch($db_triggers));
			}
			elseif($el_type == SYSMAP_ELEMENT_TYPE_HOST)
			{
				$host = get_host_by_hostid($element["elementid"]);
				$el_name = $host['host'];
				if($host["status"] == HOST_STATUS_TEMPLATE)
				{
					$tr_info[TRIGGER_VALUE_UNKNOWN]['count']	= 1;
					$tr_info[TRIGGER_VALUE_UNKNOWN]['priority']	= 0;
					$tr_info[TRIGGER_VALUE_UNKNOWN]['info']		= S_TEMPLATE_SMALL;
				}
				else
				{
					$tr_info[TRIGGER_VALUE_FALSE]['count']		= 0;
					$tr_info[TRIGGER_VALUE_FALSE]['priority']	= 0;
					$tr_info[TRIGGER_VALUE_FALSE]['info']		= S_OK_BIG;
				}
			}
		}
		elseif($el_type==SYSMAP_ELEMENT_TYPE_MAP)
		{
			$db_map = DBfetch(DBselect('select name from sysmaps where sysmapid='.$element["elementid"]));
			$el_name = $db_map['name'];

			$db_subelements = DBselect("select selementid from sysmaps_elements".
				" where sysmapid=".$element["elementid"]);
			while($db_subelement = DBfetch($db_subelements))
			{// recursion
				$inf = get_info_by_selementid($db_subelement["selementid"]);
				$type = $inf['type'];
				if(!isset($tr_info[$type]['count'])) $tr_info[$type]['count'] = 0;
				$tr_info[$type]['count'] += isset($inf['count']) ? $inf['count'] : 1;
				if(!isset($tr_info[$type]['priority']) || $tr_info[$type]['priority'] < $inf["priority"])
				{
					$tr_info[$type]['priority'] = $inf['priority'];
					$tr_info[$type]['info'] = $inf['info'];
				}
			}
		}

		if(isset($tr_info[TRIGGER_VALUE_TRUE])){
			$inf =& $tr_info[TRIGGER_VALUE_TRUE];

			$out['type'] = TRIGGER_VALUE_TRUE;
			$out['info'] = S_TRUE_BIG;

			if(($inf['count'] > 1) || ($view_status == 1))
				$out['info'] = $inf['count']." ".S_PROBLEMS_SMALL;
			else if(isset($inf['info']))
				$out['info'] = $inf['info'];

			if($inf['priority'] > 3)
				$out['color'] = $colors['Red'];
			else
				$out['color'] = $colors['Dark Red'];

			$out['iconid'] = $element['iconid_on'];
			$out['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
		}
		else if(isset($tr_info[TRIGGER_VALUE_UNKNOWN]) && !isset($tr_info[TRIGGER_VALUE_FALSE])){
			$inf =& $tr_info[TRIGGER_VALUE_UNKNOWN];

			$out['type'] = TRIGGER_VALUE_UNKNOWN;
			$out['info'] = S_UNKNOWN_BIG;
			
			/* if($inf['count'] > 1)
				$out['info'] = $inf['count']." ".S_UNKNOWN;
			else */ if(isset($inf['info']))
				$out['info'] = $inf['info'];

			$out['color'] = $colors['Gray'];
			$out['iconid'] = $element['iconid_unknown'];
			$out['icon_type'] = SYSMAP_ELEMENT_ICON_UNKNOWN;
		}
		else if(isset($tr_info[TRIGGER_VALUE_FALSE])){
			$inf =& $tr_info[TRIGGER_VALUE_FALSE];

			$out['type'] = TRIGGER_VALUE_FALSE;
			$out['info'] = S_FALSE_BIG;
			
			if(isset($inf['info']))
				$out['info'] = S_OK_BIG;

			$out['color'] = $colors['Dark Green'];
			$out['iconid'] = $element['iconid_off'];
			$out['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		}
		else{
// UNDEFINED ELEMENT
			$inf['count'] = 0;
			$inf['priority'] = 0;
			
			$out['type'] = TRIGGER_VALUE_TRUE;
			$out['info'] = '';

			$out['color'] = $colors['Green'];

			$out['iconid'] = $element['iconid_on'];
			$out['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
		}
		
// Host in maintenance
		if($maintenance['status']){
			$out['type'] = TRIGGER_VALUE_UNKNOWN;
			$out['info'] = S_IN_MAINTENANCE;
			if($maintenance['maintenanceid'] > 0){
				$mnt = get_maintenance_by_maintenanceid($maintenance['maintenanceid']);
				$out['info'].='['.$mnt['name'].']';
			}
			
			$out['color'] = $colors['Gray'];
			$out['iconid'] = $element['iconid_maintenance'];
			$out['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
		}
//---
		$out['count'] = $inf['count'];
		$out['priority'] = $inf['priority'];
		$out['name'] = $el_name;

	return $out;
	}

        /*
         * Function: get_action_map_by_sysmapid
         *
         * Description:
         *     Retrive action for map element
         *
         * Author:
         *     Eugene Grigorjev 
         *
         */
	function get_action_map_by_sysmapid($sysmapid)
	{
		$action_map = new CMap("links$sysmapid");

		$db_elements=DBselect('SELECT * FROM sysmaps_elements WHERE sysmapid='.$sysmapid);
		while($db_element = DBfetch($db_elements))
		{
			$url	= $db_element["url"];
			$alt	= "Label: ".$db_element["label"];
			$scripts_by_hosts = null;
			
			if($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_HOST)
			{
				$host = get_host_by_hostid($db_element["elementid"]);
				if($host["status"] != HOST_STATUS_MONITORED)	continue;

				$scripts_by_hosts = get_accessible_scripts_by_hosts(array($db_element["elementid"]));
				
				if(empty($url))	$url='tr_status.php?hostid='.$db_element['elementid'].'&noactions=true&onlytrue=true&compact=true';
				
				$alt = "Host: ".$host["host"]." ".$alt;
			}
			elseif($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_MAP)
			{
				$map = get_sysmap_by_sysmapid($db_element["elementid"]);

				if(empty($url))
					$url="maps.php?sysmapid=".$db_element["elementid"];

				$alt = "Host: ".$map["name"]." ".$alt;
			}
			elseif($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_TRIGGER)
			{
				if(empty($url) && $db_element["elementid"]!=0)
					$url="tr_events.php?triggerid=".$db_element["elementid"];
			}
			elseif($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_HOST_GROUP)
			{
				if(empty($url) && $db_element["elementid"]!=0)
					$url="events.php?hostid=0&groupid=".$db_element["elementid"];
			}

			if(empty($url))	continue;

			$back = get_png_by_selementid($db_element["selementid"]);
			if(!$back)	continue;

			$x1_		= $db_element["x"];
			$y1_		= $db_element["y"];
			$x2_		= $db_element["x"] + imagesx($back);
			$y2_		= $db_element["y"] + imagesy($back);

			$r_area = new CArea(array($x1_,$y1_,$x2_,$y2_),$url,$alt,'rect');
			if(!empty($scripts_by_hosts)){
				$menus = '';
	
				$host_nodeid = id2nodeid($db_element["elementid"]);
				foreach($scripts_by_hosts[$db_element["elementid"]] as $id => $script){
					$script_nodeid = id2nodeid($script['scriptid']);
					if( $host_nodeid == $script_nodeid )
						$menus.= "['".$script['name']."',null, function(){openWinCentered('scripts_exec.php?execute=1&hostid=".$db_element["elementid"]."&scriptid=".$script['scriptid']."','".S_TOOLS."',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');},{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
				}
				$menus.= "['".S_STATUS_OF_TRIGGERS."', '".$url."', null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
				
				$menus = trim($menus,',');
				$menus="show_popup_menu(event,[[".zbx_jsvalue(S_TOOLS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],".$menus."],180); cancelEvent(event);";
				
				$r_area->AddAction('onclick','javascript: '.$menus);
			}
			$action_map->AddItem($r_area);//AddRectArea($x1_,$y1_,$x2_,$y2_, $url, $alt);
		}
		
		$jsmenu = new CPUMenu(null,170);
		$jsmenu->InsertJavaScript();
		return $action_map;
	}

	function get_icon_center_by_selementid($selementid){
		$element = get_sysmaps_element_by_selementid($selementid);
	return get_icon_center_by_selement($element);
	}
	
	function get_icon_center_by_selement($element){

		$x = $element['x'];
		$y = $element['y'];

		$image = get_png_by_selement($element);
		if($image){
			$x += imagesx($image) / 2;
			$y += imagesy($image) / 2;
		}

	return array($x, $y);
	}

	function	MyDrawLine($image,$x1,$y1,$x2,$y2,$color,$drawtype)
	{
		if($drawtype == MAP_LINK_DRAWTYPE_BOLD_LINE)
		{
			ImageLine($image,$x1,$y1,$x2,$y2,$color);
			if(($x1-$x2) < ($y1-$y2))
			{
				$x1++;		$x2++;
			}
			else
			{
				$y1++;		$y2++;
			}
			ImageLine($image,$x1,$y1,$x2,$y2,$color);
		}
		else if($drawtype == MAP_LINK_DRAWTYPE_DASHED_LINE)
		{
			if(function_exists("imagesetstyle"))
			{ /* Use ImageSetStyle+ImageLIne instead of bugged ImageDashedLine */
				$style = array(
					$color, $color, $color, $color,
					IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT
					);
				ImageSetStyle($image, $style);
				ImageLine($image,$x1,$y1,$x2,$y2,IMG_COLOR_STYLED);
			}
			else
			{
				ImageDashedLine($image,$x1,$y1,$x2,$y2,$color);
			}
		}
		else if ( $drawtype == MAP_LINK_DRAWTYPE_DOT && function_exists("imagesetstyle"))
		{
			$style = array($color,IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
			ImageSetStyle($image, $style);
			ImageLine($image,$x1,$y1,$x2,$y2,IMG_COLOR_STYLED);
		}
		else
		{
			ImageLine($image,$x1,$y1,$x2,$y2,$color);
		}
	}
	
	function get_png_by_selementid($selementid){
		$element = DBfetch(DBselect('SELECT * FROM sysmaps_elements WHERE selementid='.$selementid));
		if(!$element)	return FALSE;

	return get_png_by_selement($element);
	}
	
	function get_png_by_selement($element){
		$info = get_info_by_selement($element);

		switch($info['icon_type']){
			case SYSMAP_ELEMENT_ICON_ON:
				$info['iconid'] = $element['iconid_on'];
				break;
			case SYSMAP_ELEMENT_ICON_OFF:
				$info['iconid'] = $element['iconid_off'];
				break;
			case SYSMAP_ELEMENT_ICON_UNKNOWN:
				$info['iconid'] = $element['iconid_unknown'];
				break;
			case SYSMAP_ELEMENT_ICON_MAINTENANCE:
				$info['iconid'] = $element['iconid_maintenance'];
				break;
		}
		
		$image = get_image_by_imageid($info['iconid']);
		if(!$image)	return FALSE;

	return imagecreatefromstring($image['image']);
	}
	
	function get_base64_icon($element){	
	return base64_encode(get_element_icon($element));
	}
	
	function get_element_iconid($element){
		if($element['selementid'] > 0){
			$info = get_info_by_selement($element);
//SDI($info);
			switch($info['icon_type']){
				case SYSMAP_ELEMENT_ICON_ON:
					$info['iconid'] = $element['iconid_on'];
					break;
				case SYSMAP_ELEMENT_ICON_OFF:
					$info['iconid'] = $element['iconid_off'];
					break;
				case SYSMAP_ELEMENT_ICON_UNKNOWN:
					$info['iconid'] = $element['iconid_unknown'];
					break;
				case SYSMAP_ELEMENT_ICON_MAINTENANCE:
					$info['iconid'] = $element['iconid_maintenance'];
					break;
			}
		}
		else{
			$info['iconid'] = $element['iconid_off'];
		}
		
	return $info['iconid'];
	}
	
	function get_element_icon($element){
		$iconid = get_element_iconid($element);
		
		$image = get_image_by_imageid($iconid);
		$img = imagecreatefromstring($image['image']);
		
		unset($image);
		
		$w=imagesx($img); 
		$h=imagesy($img);
		
		if(function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1,1)){
			$im = imagecreatetruecolor($w,$h);
		}
		else{
			$im = imagecreate($w,$h);
		}

		imagefilledrectangle($im,0,0,$w,$h, imagecolorallocate($im,255,255,255));

		imagecopy($im,$img,0,0,0,0,$w,$h);
		imagedestroy($img);
		
		ob_start();
		imagepng($im);
		$image_txt = ob_get_contents();
		ob_end_clean();
		
	return $image_txt;
	}
	
	function get_element_form_menu(){
		global $USER_DETAILS;

		$menu = '';
		$cmapid = get_request('favid',0);
		
		$el_menu = array(
				array('form_key'=>'elementtype',		'value'=> S_TYPE),				
				array('form_key'=>'label', 				'value'=> S_LABEL),
				array('form_key'=>'label_location', 	'value'=> S_LABEL_LOCATION),
				array('form_key'=>'iconid_off',	 		'value'=> S_ICON_OFF),
				array('form_key'=>'iconid_on',	 		'value'=> S_ICON_ON),
				array('form_key'=>'iconid_unknown',	 	'value'=> S_ICON_UNKNOWN),
				array('form_key'=>'iconid_maintenance',	'value'=> S_ICON_MAINTENANCE),
				
				array('form_key'=>'url', 			'value'=> S_URL),
			);
		
		$menu.= 'var zbx_element_menu = '.zbx_jsvalue($el_menu).';'."\n";
		
		$el_form_menu = array();
// Element type
		$el_form_menu['elementtype'] = array();

		$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT);
		$allowed_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY);
		
		$db_hosts = DBselect("select distinct n.name as node_name,h.hostid,h.host from hosts h".
			" left join nodes n on n.nodeid=".DBid2nodeid("h.hostid").
			" where h.hostid not in(".$denyed_hosts.")".
			" order by node_name,h.host");
		if($db_hosts)
			$el_form_menu['elementtype'][] = array('key'=> SYSMAP_ELEMENT_TYPE_HOST,	'value'=> S_HOST);

		$db_maps = DBselect('SELECT sysmapid FROM sysmaps WHERE sysmapid!='.$_REQUEST['sysmapid']);
		if(DBfetch($db_maps))
			$el_form_menu['elementtype'][] = array('key'=> SYSMAP_ELEMENT_TYPE_MAP,	'value'=> S_MAP);

		$el_form_menu['elementtype'][] = array('key'=> SYSMAP_ELEMENT_TYPE_TRIGGER,	'value'=> S_TRIGGER);
		$el_form_menu['elementtype'][] = array('key'=> SYSMAP_ELEMENT_TYPE_HOST_GROUP,	'value'=> S_HOST_GROUP);
		$el_form_menu['elementtype'][] = array('key'=> SYSMAP_ELEMENT_TYPE_UNDEFINED,	'value'=> S_UNDEFINED);
		

// ELEMENTID by TYPE
		$el_form_menu['elementid'] = array();
// HOST		
		$host_link = new CLink(S_SELECT);
		$host_link->addAction('onclick',"return PopUp('popup.php?dstfrm=".'FORM'.
								"&dstfld1=elementid&dstfld2=host&srctbl=hosts&srcfld1=hostid&srcfld2=host',450,450);");
		$el_form_menu['hostid_hosts'][] = array('key'=>SYSMAP_ELEMENT_TYPE_HOST, 'value'=> unpack_object($host_link));
// MAP
		$maps = array();
		$db_maps = DBselect('SELECT DISTINCT n.name as node_name,s.sysmapid,s.name '.
							' FROM sysmaps s '.
								' LEFT JOIN nodes n on n.nodeid='.DBid2nodeid('s.sysmapid').
							' ORDER BY node_name,s.name');
		while($db_map = DBfetch($db_maps)){
			if(!sysmap_accessiable($db_map['sysmapid'],PERM_READ_ONLY)) continue;
			
			$node_name = isset($db_map['node_name']) ? '('.$db_map['node_name'].') ' : '';
			$maps[] = array($db_map['sysmapid'],$node_name.$db_map['name']);
		}
		$el_form_menu['sysmapid_sysmaps'][] = array('key'=>SYSMAP_ELEMENT_TYPE_MAP, 'value'=> $maps);
		
// TRIGGER
		$trigger_link = new CLink(S_SELECT);
		$trigger_link->addAction('onclick',"return PopUp('popup.php?dstfrm=".'FORM'.
					"&dstfld1=elementid&dstfld2=trigger&srctbl=triggers&srcfld1=triggerid&srcfld2=description');");
		$el_form_menu['triggerid_triggers'][] = array('key'=>SYSMAP_ELEMENT_TYPE_TRIGGER, 'value'=> unpack_object($trigger_link));
		
// HOST GROUP
		$hg_link = new CLink(S_SELECT);
		$hg_link->addAction('onclick',"return PopUp('popup.php?dstfrm=".'FORM'.
					"&dstfld1=elementid&dstfld2=group&srctbl=host_group&srcfld1=groupid&srcfld2=name',450,450);");
		$el_form_menu['groupid_host_group'][] = array('key'=>SYSMAP_ELEMENT_TYPE_HOST_GROUP, 'value'=> unpack_object($hg_link));

// LABEL
		$el_form_menu['label'][] = array('key'=> 'unknown',	'value'=> 'unknown');
		

// LABEL Location
		$el_form_menu['label_location'] = array();
		
		$el_form_menu['label_location'][] = array('key'=> -1, 'value'=> '-');
		$el_form_menu['label_location'][] = array('key'=> 0, 'value'=> S_BOTTOM);
		$el_form_menu['label_location'][] = array('key'=> 1, 'value'=> S_LEFT);
		$el_form_menu['label_location'][] = array('key'=> 2, 'value'=> S_RIGHT);
		$el_form_menu['label_location'][] = array('key'=> 3, 'value'=> S_TOP);
// ICONS 
		$el_form_menu['iconid_off'] = array();
		$el_form_menu['iconid_on'] = array();
		$el_form_menu['iconid_unknown'] = array();
		$el_form_menu['iconid_maintenance'] = array();
		
		$result = DBselect('SELECT * FROM images WHERE imagetype=1 AND '.DBin_node('imageid').' ORDER BY name');
		while($row=DBfetch($result)){
			$row['name'] = get_node_name_by_elid($row['imageid']).$row['name'];
			$el_form_menu['iconid_off'][] = array('key'=>$row['imageid'], 'value'=>$row['name']);
			$el_form_menu['iconid_on'][] = array('key'=>$row['imageid'], 'value'=>$row['name']);
			$el_form_menu['iconid_unknown'][] = array('key'=>$row['imageid'], 'value'=>$row['name']);
			$el_form_menu['iconid_maintenance'][] = array('key'=>$row['imageid'], 'value'=>$row['name']);
		}
		
// URL
		$el_form_menu['url'][] = array('key'=> '',	'value'=> '');

		$menu.= 'var zbx_element_form_menu = '.zbx_jsvalue($el_form_menu).';';
	
	return $menu;
	}
	
	function get_link_form_menu(){
		global $USER_DETAILS;

		$menu = '';
		$cmapid = get_request('favid',0);
		
		$ln_menu = array(
				array('form_key'=>'selementid1',		'value'=> S_ELEMENT_1),
				array('form_key'=>'selementid2',		'value'=> S_ELEMENT_2),
				array('form_key'=>'triggerid',			'value'=> S_LINK_STATUS_INDICATOR),
				array('form_key'=>'drawtype_off', 		'value'=> S_TYPE_OFF),
				array('form_key'=>'color_off', 			'value'=> S_COLOR_OFF),
				array('form_key'=>'drawtype_on', 		'value'=> S_TYPE_ON),
				array('form_key'=>'color_on', 			'value'=> S_COLOR_ON),				
			);
		
		$menu.= 'var zbx_link_menu = '.zbx_jsvalue($ln_menu).';'."\n";
		
		$ln_form_menu = array();
		
		$ln_form_menu['triggerid'][] = array('key'=> '0',	'value'=> S_SELECT);
// LINK draw type
		$ln_form_menu['drawtype_off'] = array();
		$ln_form_menu['drawtype_on'] = array();
		
		foreach(map_link_drawtypes() as $i){		
			$value = map_link_drawtype2str($i);
			
			$ln_form_menu['drawtype_off'][] = array('key'=> $i,	'value'=> $value);
			$ln_form_menu['drawtype_on'][] = array('key'=> $i,	'value'=> $value);
		}

		
		$ln_form_menu['color_off'] = array();
		$ln_form_menu['color_on'] = array();
		$colors = array('Black','Blue','Cyan','Dark Blue','Dark Green','Dark Red','Dark Yellow','Gray','Green','Red','White','Yellow');
		foreach($colors as $id => $value){
			$ln_form_menu['color_off'][] = array('key'=> $value,'value'=> $value);
			$ln_form_menu['color_on'][] = array('key'=> $value,	'value'=> $value);
		}
		
		$menu.= 'var zbx_link_form_menu = '.zbx_jsvalue($ln_form_menu).';';
	
	return $menu;
	}
?>
