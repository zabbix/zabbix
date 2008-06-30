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
	function	map_link_drawtype2str($drawtype){
		switch($drawtype){
			case MAP_LINK_DRAWTYPE_LINE:		$drawtype = S_LINE;			break;
			case MAP_LINK_DRAWTYPE_BOLD_LINE:	$drawtype = S_BOLD_LINE;	break;
			case MAP_LINK_DRAWTYPE_DOT:			$drawtype = S_DOT;			break;
			case MAP_LINK_DRAWTYPE_DASHED_LINE:	$drawtype = S_DASHED_LINE;	break;
			default: $drawtype = S_UNKNOWN;		break;
		}
	return $drawtype;
	}

        /*
         * Function: sysmap_accessible
         *
         * Description:
         *     Check permission for map
         *
	 * Return: true on success

         * Author:
         *     Eugene Grigorjev 
         *
         */
	function sysmap_accessible($sysmapid,$perm){
		global $USER_DETAILS;

		$result = false;

		if($db_result = DBselect('SELECT * '.
						' FROM sysmaps_elements '.
						' WHERE sysmapid='.$sysmapid.
							' AND '.DBin_node('sysmapid', get_current_nodeid($perm))))
		{
			$result = true;
			$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
						
			while(($se_data = DBfetch($db_result)) && $result){
				switch($se_data['elementtype']){
					case SYSMAP_ELEMENT_TYPE_HOST:
						if(!uint_in_array($se_data['elementid'],$available_hosts)){
							$result = false;
						}
						break;
					case SYSMAP_ELEMENT_TYPE_MAP:
						$result &= sysmap_accessible($se_data['elementid'], PERM_READ_ONLY);
						break;
					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						$available_triggers = get_accessible_triggers(PERM_READ_ONLY, PERM_RES_IDS_ARRAY);
						
						$sql = 'SELECT t.triggerid '.
								' FROM triggers t'.
								' WHERE t.triggerid='.$se_data['elementid'].
									' AND '.DBcondition('t.triggerid',$available_triggers,true);
						if(DBfetch(DBselect($sql,1))){
								$result = false;
						}

						break;
					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY);
						
						$sql = 'SELECT g.groupid '.
							' FROM groups g '.
							' WHERE g.groupid='.$se_data['elementid'].
								' AND g.groupid NOT IN('.$available_groups.') ';
							
						if(DBfetch(DBselect($sql,1))){
							$result = false;
						}
						break;
				}
			}
		}
		else{
			if(DBselect('SELECT sysmapid FROM sysmaps WHERE sysmapid='.$sysmapid.
				' AND '.DBin_node('sysmapid', get_current_nodeid($perm))))
					$result = true;
		}
		return $result;
	}

	function get_sysmap_by_sysmapid($sysmapid){
		$row = DBfetch(DBselect('SELECT * FROM sysmaps WHERE sysmapid='.$sysmapid));
		if($row){
			return	$row;
		}
		error("No system map with sysmapid=[".$sysmapid."]");
		return false;
	}

	function get_sysmaps_element_by_selementid($selementid){
		$sql="select * FROM sysmaps_elements WHERE selementid=$selementid"; 
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row){
			return	$row;
		}
		else{
			error("No sysmap element with selementid=[$selementid]");
		}
	return	$result;
	}

// Add System Map

	function add_sysmap($name,$width,$height,$backgroundid,$label_type,$label_location)
	{
		$sysmapid=get_dbid("sysmaps","sysmapid");

		$result=DBexecute("insert into sysmaps (sysmapid,name,width,height,backgroundid,label_type,label_location)".
			" values ($sysmapid,".zbx_dbstr($name).",$width,$height,".$backgroundid.",$label_type,
			$label_location)");

		if(!$result)
			return $result;

		return $sysmapid;
	}

// Update System Map

	function update_sysmap($sysmapid,$name,$width,$height,$backgroundid,$label_type,$label_location)
	{
		return	DBexecute("update sysmaps set name=".zbx_dbstr($name).",width=$width,height=$height,".
			"backgroundid=".$backgroundid.",label_type=$label_type,".
			"label_location=$label_location WHERE sysmapid=$sysmapid");
	}

// Delete System Map

	function delete_sysmap( $sysmapid ){
		$result = delete_sysmaps_elements_with_sysmapid($sysmapid);
		if(!$result)	return	$result;

		$res=DBselect('SELECT linkid FROM sysmaps_links WHERE sysmapid='.$sysmapid);
		while($rows = DBfetch($res)){
			$result&=delete_link($rows['linkid']);
		}

		$result = DBexecute('DELETE FROM sysmaps_elements WHERE sysmapid='.$sysmapid);
		$result &= DBexecute("DELETE FROM profiles WHERE idx='web.favorite.sysmapids' AND source='sysmapid' AND value_id=$sysmapid");
		$result &= DBexecute('DELETE FROM sysmaps WHERE sysmapid='.$sysmapid);
		
	return $result;
	}

// LINKS

	function add_link($sysmapid,$selementid1,$selementid2,$triggers,$drawtype,$color){
		$linkid=get_dbid("sysmaps_links","linkid");
		
		$result=TRUE;
		foreach($triggers as $id => $trigger){
			if(empty($trigger['triggerid'])) continue;
			$result&=add_link_trigger($linkid,$trigger['triggerid'],$trigger['drawtype'],$trigger['color']);
		}
		
		if(!$result){
			return $result;
		}

		$result&=DBexecute("insert into sysmaps_links".
			" (linkid,sysmapid,selementid1,selementid2,drawtype,color)".
			" values ($linkid,$sysmapid,$selementid1,$selementid2,$drawtype,".zbx_dbstr($color).")");

		if(!$result)
			return $result;

	return $linkid;
	}

	function update_link($linkid,$sysmapid,$selementid1,$selementid2,$triggers,$drawtype,$color){
		
		$result=delete_all_link_triggers($linkid);;
		
		foreach($triggers as $id => $trigger){
			if(empty($trigger['triggerid'])) continue;
			$result&=add_link_trigger($linkid,$trigger['triggerid'],$trigger['drawtype'],$trigger['color']);
		}
		
		if(!$result){
			return $result;
		}
		
		$result&=DBexecute('UPDATE sysmaps_links SET '.
							" sysmapid=$sysmapid,selementid1=$selementid1,selementid2=$selementid2,".
							" drawtype=$drawtype,color=".zbx_dbstr($color).
						" WHERE linkid=$linkid");
	return	$result;
	}

	function delete_link($linkid){
		$result = delete_all_link_triggers($linkid);
		$result&= DBexecute("delete FROM sysmaps_links WHERE linkid=$linkid");
	return	$result;
	}

	function get_link_triggers($linkid){
		$triggers = array();

		$sql = "SELECT * FROM sysmaps_link_triggers WHERE linkid=$linkid";
		$res = DBselect($sql);
		
		while($rows = DBfetch($res)){
			$triggers[] = $rows;
		}
	return $triggers;
	}

	function add_link_trigger($linkid,$triggerid,$drawtype,$color){
		$linktriggerid=get_dbid("sysmaps_link_triggers","linktriggerid");
		$sql = 'INSERT INTO sysmaps_link_triggers (linktriggerid,linkid,triggerid,drawtype,color) '.
					" VALUES ('$linktriggerid','$linkid','$triggerid','$drawtype',".zbx_dbstr($color).")";
	return DBexecute($sql);
	}

	function update_link_trigger($linkid,$triggerid,$drawtype,$color){
		$result=delete_link_trigger($linkid,$triggerid);
		$result&=add_link_trigger($linkid,$triggerid,$drawtype,$color);
	return $result;
	}
	
	function delete_link_trigger($linkid,$triggerid){
	return DBexecute("DELETE FROM sysmaps_link_triggers WHERE linkid=$linkid AND triggerid=$triggerid");
	}
	
	function delete_all_link_triggers($linkid){
	return DBexecute("DELETE FROM sysmaps_link_triggers WHERE linkid=$linkid");
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
	function check_circle_elements_link($sysmapid,$elementid,$elementtype){
		if($elementtype!=SYSMAP_ELEMENT_TYPE_MAP)	return FALSE;

		if(bccomp($sysmapid ,$elementid)==0)	return TRUE;

		$db_elements = DBselect("select elementid, elementtype FROM sysmaps_elements".
			" WHERE sysmapid=$elementid");

		while($element = DBfetch($db_elements))
		{
			if(check_circle_elements_link($sysmapid,$element["elementid"],$element["elementtype"]))
				return TRUE;
		}
		return FALSE;
	}

	# Add Element to system map

	function add_element_to_sysmap($sysmapid,$elementid,$elementtype,
						$label,$x,$y,$iconid_off,$iconid_unknown,$iconid_on,$url,$label_location)
	{
		if($label_location<0) $label_location='null';
		if(check_circle_elements_link($sysmapid,$elementid,$elementtype))
		{
			error("Circle link can't be created");
			return FALSE;
		}

		$selementid = get_dbid("sysmaps_elements","selementid");

		$result=DBexecute('INSERT INTO sysmaps_elements '.
							" (selementid,sysmapid,elementid,elementtype,label,x,y,iconid_off,url,iconid_on,label_location,iconid_unknown)".
						" VALUES ($selementid,$sysmapid,$elementid,$elementtype,".zbx_dbstr($label).
							",$x,$y,$iconid_off,".zbx_dbstr($url).
							",$iconid_on,$label_location,$iconid_unknown)");

		if(!$result)
			return $result;

		return $selementid;
	}

	# Update Element FROM system map

	function update_sysmap_element($selementid,$sysmapid,$elementid,$elementtype,
						$label,$x,$y,$iconid_off,$iconid_unknown,$iconid_on,$url,$label_location)
	{
		if($label_location<0) $label_location='null';
		if(check_circle_elements_link($sysmapid,$elementid,$elementtype))
		{
			error("Circle link can't be created");
			return FALSE;
		}

		return	DBexecute('UPDATE sysmaps_elements '. 
					"SET elementid=$elementid,elementtype=$elementtype,".
						"label=".zbx_dbstr($label).",x=$x,y=$y,iconid_off=$iconid_off,".
						"url=".zbx_dbstr($url).",iconid_on=$iconid_on,".
						"label_location=$label_location,iconid_unknown=$iconid_unknown".
					" WHERE selementid=$selementid");
	}

	/******************************************************************************
	 *                                                                            *
	 * Purpose: Delete Element FROM sysmap definition                             *
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function delete_sysmaps_element($selementid){
		
		$result=TRUE;
		$sql = 'SELECT linkid FROM sysmaps_links '.
				' WHERE selementid1='.$selementid.
					' OR selementid2='.$selementid;
		$res=DBselect($sql);
		while($rows = DBfetch($res)){
			$result&=delete_link($rows['linkid']);
		}
//		$result=DBexecute('DELETE FROM sysmaps_links WHERE selementid1=$selementid OR selementid2=$selementid');

		if(!$result) return	$result;

		return	DBexecute("delete FROM sysmaps_elements WHERE selementid=$selementid");
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function delete_sysmaps_elements_with_hostid($hostid){
		$db_elements = DBselect("select selementid FROM sysmaps_elements".
			" WHERE elementid=$hostid AND elementtype=".SYSMAP_ELEMENT_TYPE_HOST);
		while($db_element = DBfetch($db_elements)){
			delete_sysmaps_element($db_element["selementid"]);
		}
		return TRUE;
	}
	
	function delete_sysmaps_elements_with_sysmapid($sysmapid){
		$db_elements = DBselect("select selementid FROM sysmaps_elements".
			" WHERE elementid=$sysmapid AND elementtype=".SYSMAP_ELEMENT_TYPE_MAP);
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
	function delete_sysmaps_elements_with_triggerid($triggerid){
		$db_elements = DBselect('SELECT selementid FROM sysmaps_elements '.
				' WHERE elementid='.$triggerid.
					' AND elementtype='.SYSMAP_ELEMENT_TYPE_TRIGGER);
			
		while($db_element = DBfetch($db_elements)){
			delete_sysmaps_element($db_element["selementid"]);
		}
		return TRUE;
	}
	
	function delete_sysmaps_elements_with_groupid($groupid){
		$db_elements = DBselect('SELECT selementid FROM sysmaps_elements '.
			' WHERE elementid='.$groupid.
				' AND elementtype='.SYSMAP_ELEMENT_TYPE_HOST_GROUP);
			
		while($db_element = DBfetch($db_elements)){
			delete_sysmaps_element($db_element["selementid"]);
		}
		return TRUE;
	}

	function get_png_by_selementid($selementid)
	{
		$elements = DBselect("select * FROM sysmaps_elements WHERE selementid=$selementid");
		if(!$elements)	return FALSE;

		$element = DBfetch($elements);
		if(!$element)	return FALSE;

		$info = get_info_by_selementid($element["selementid"]);

		$image = get_image_by_imageid($info['iconid']);
		if(!$image)	return FALSE;

		return imagecreatefromstring($image['image']);
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
	function get_info_by_selementid($selementid)
	{
		global $colors;

		$el_name = '';
		$tr_info = array();

		$db_element = get_sysmaps_element_by_selementid($selementid);

		$el_type =& $db_element["elementtype"];

		$sql = array(
			SYSMAP_ELEMENT_TYPE_TRIGGER => 'SELECT DISTINCT t.triggerid, t.priority, t.value, t.description, t.expression, h.host '.
				' FROM triggers t, items i, functions f, hosts h '.
				' WHERE t.triggerid='.$db_element['elementid'].
					' AND h.hostid=i.hostid '.
					' AND i.itemid=f.itemid '.
					' AND f.triggerid=t.triggerid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND i.status='.ITEM_STATUS_ACTIVE,
			SYSMAP_ELEMENT_TYPE_HOST_GROUP => 'SELECT DISTINCT t.triggerid, t.priority, t.value, t.description, t.expression, h.host, g.name as el_name '.
				' FROM items i,functions f,triggers t,hosts h,hosts_groups hg,groups g '.
				' WHERE h.hostid=i.hostid '.
					' AND hg.groupid=g.groupid '.
					' AND g.groupid='.$db_element['elementid'].
					' AND hg.hostid=h.hostid '.
					' AND i.itemid=f.itemid'.
					' AND f.triggerid=t.triggerid '.
					' AND t.status='.TRIGGER_STATUS_ENABLED.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND i.status='.ITEM_STATUS_ACTIVE,
			SYSMAP_ELEMENT_TYPE_HOST => 'SELECT DISTINCT t.triggerid, t.priority, t.value, t.description, t.expression, h.host, h.host as el_name'.
				' FROM items i,functions f,triggers t,hosts h WHERE h.hostid=i.hostid'.
					' AND i.hostid='.$db_element['elementid'].
					' AND i.itemid=f.itemid'.
					' AND f.triggerid=t.triggerid '.
					' AND t.status='.TRIGGER_STATUS_ENABLED.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND i.status='.ITEM_STATUS_ACTIVE
			);
			
		if( isset($sql[$el_type]) ){
			$db_triggers = DBselect($sql[$el_type]);
			$trigger = DBfetch($db_triggers);
			if($trigger)
			{
				if(isset($trigger['el_name']))
				{
					$el_name = $trigger['el_name'];
				}
				else
				{
					$el_name = expand_trigger_description_by_data($trigger);
				}

				do {
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
				$host = get_host_by_hostid($db_element["elementid"]);
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
			$db_map = DBfetch(DBselect('select name FROM sysmaps WHERE sysmapid='.$db_element["elementid"]));
			$el_name = $db_map['name'];

			$db_subelements = DBselect("select selementid FROM sysmaps_elements".
				" WHERE sysmapid=".$db_element["elementid"]);
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

		if(isset($tr_info[TRIGGER_VALUE_TRUE]))
		{
			$inf =& $tr_info[TRIGGER_VALUE_TRUE];

			$out['type'] = TRIGGER_VALUE_TRUE;
			$out['info'] = S_TRUE_BIG;

			if($inf['count'] > 1)
				$out['info'] = $inf['count']." ".S_PROBLEMS_SMALL;
			else if(isset($inf['info']))
				$out['info'] = $inf['info'];

			if($inf['priority'] > 3)
				$out['color'] = $colors['Red'];
			else
				$out['color'] = $colors['Dark Red'];

			$out['iconid'] = $db_element['iconid_on'];
		}
		elseif(isset($tr_info[TRIGGER_VALUE_UNKNOWN]) && !isset($tr_info[TRIGGER_VALUE_FALSE]))
		{
			$inf =& $tr_info[TRIGGER_VALUE_UNKNOWN];

			$out['type'] = TRIGGER_VALUE_UNKNOWN;
			$out['info'] = S_UNKNOWN_BIG;
			
			/* if($inf['count'] > 1)
				$out['info'] = $inf['count']." ".S_UNKNOWN;
			else */ if(isset($inf['info']))
				$out['info'] = $inf['info'];

			$out['color'] = $colors['Gray'];
			$out['iconid'] = $db_element['iconid_unknown'];
		}
		else
		{
			$inf =& $tr_info[TRIGGER_VALUE_FALSE];

			$out['type'] = TRIGGER_VALUE_FALSE;
			$out['info'] = S_FALSE_BIG;
			
			if(isset($inf['info']))
				$out['info'] = S_OK_BIG;

			$out['color'] = $colors['Dark Green'];
			$out['iconid'] = $db_element['iconid_off'];
		}

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
					$url="events.php?triggerid=".$db_element["elementid"];
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
					if( (bccomp($host_nodeid ,$script_nodeid ) == 0))
						$menus.= "['".$script['name']."',\"javascript: openWinCentered('scripts_exec.php?execute=1&hostid=".$db_element["elementid"]."&scriptid=".$script['scriptid']."','".S_TOOLS."',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
				}
				
				$menus.= "[".zbx_jsvalue(S_LINKS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";

				$menus.= "['".S_STATUS_OF_TRIGGERS."',\"javascript: redirect('tr_status.php?hostid=".$db_element['elementid']."&noactions=true&onlytrue=true&compact=true')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";

				if(!empty($db_element["url"])){
					$menus.= "['".S_MAP.SPACE.S_URL."',\"javascript: redirect('".$url."')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
				}
				
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

	function	get_icon_center_by_selementid($selementid)
	{
		$element = get_sysmaps_element_by_selementid($selementid);
		$x = $element["x"];
		$y = $element["y"];

		$image = get_png_by_selementid($selementid);
		if($image)
		{
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
	
	function convertColor($im,$color){
	
		$RGB = array(
			hexdec('0x'.substr($color, 0,2)),
			hexdec('0x'.substr($color, 2,2)),
			hexdec('0x'.substr($color, 4,2))
			);
		
		
	return ImageColorAllocate($im,$RGB[0],$RGB[1],$RGB[2]);
	}

?>
