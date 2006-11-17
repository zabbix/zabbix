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
	function	get_sysmap_by_sysmapid($sysmapid)
	{
		$sql="select * from sysmaps where sysmapid=$sysmapid"; 
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		error("No system map with sysmapid=[$sysmapid]");
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

	// delete map permisions
		DBexecute('delete from rights where name=\'Network map\' and id='.$sysmapid);

		return DBexecute("delete from sysmaps where sysmapid=$sysmapid");
	}

	# Update System Map

	function	update_sysmap($sysmapid,$name,$width,$height,$background,$label_type,$label_location)
	{
		if(!check_right("Network map","U",$sysmapid))
		{
			error("Insufficient permissions");
			return 0;
		}

		return	DBexecute("update sysmaps set name=".zbx_dbstr($name).",width=$width,height=$height,".
			"background=".zbx_dbstr($background).",label_type=$label_type,".
			"label_location=$label_location where sysmapid=$sysmapid");
	}

	# Add System Map

	function	add_sysmap($name,$width,$height,$background,$label_type,$label_location)
	{
		if(!check_right("Network map","A",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		return	DBexecute("insert into sysmaps (name,width,height,background,label_type,label_location)".
			" values (".zbx_dbstr($name).",$width,$height,".zbx_dbstr($background).",$label_type,
			$label_location)");
	}

	function	add_link($sysmapid,$selementid1,$selementid2,$triggerid,$drawtype_off,$color_off,$drawtype_on,$color_on)
	{
		if($triggerid == 0)	$triggerid = 'NULL';

		return	DBexecute("insert into sysmaps_links".
			" (sysmapid,selementid1,selementid2,triggerid,drawtype_off,".
			"color_off,drawtype_on,color_on)".
			" values ($sysmapid,$selementid1,$selementid2,$triggerid,$drawtype_off,".
			zbx_dbstr($color_off).",$drawtype_on,".zbx_dbstr($color_on).")");
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
						$label,$x,$y,$icon,$url,$icon_on,$label_location)
	{
		if($label_location<0) $label_location='null';
		if(check_circle_elements_link($sysmapid,$elementid,$elementtype))
		{
			error("Circle link can't be created");
			return FALSE;
		}

		return	DBexecute("insert into sysmaps_elements".
			" (sysmapid,elementid,elementtype,label,x,y,icon,url,icon_on,label_location)".
			" values ($sysmapid,$elementid,$elementtype,".zbx_dbstr($label).",
			$x,$y,".zbx_dbstr($icon).",".zbx_dbstr($url).",".zbx_dbstr($icon_on).",".
			"$label_location)");
	}

	# Update Element from system map

	function	update_sysmap_element($selementid,$sysmapid,$elementid,$elementtype,
						$label,$x,$y,$icon,$url,$icon_on,$label_location)
	{
		if($label_location<0) $label_location='null';
		if(check_circle_elements_link($sysmapid,$elementid,$elementtype))
		{
			error("Circle link can't be created");
			return FALSE;
		}

		return	DBexecute("update sysmaps_elements set elementid=$elementid,elementtype=$elementtype,".
			"label=".zbx_dbstr($label).",x=$x,y=$y,icon=".zbx_dbstr($icon).",url=".zbx_dbstr($url).
			",icon_on=".zbx_dbstr($icon_on).",label_location=$label_location".
			" where selementid=$selementid");
	}

	# Delete Element from sysmap definition

	function	delete_sysmaps_element($selementid)
	{
		$result=DBexecute("delete from sysmaps_links".
			" where selementid1=$selementid or selementid2=$selementid");

		if(!$result)		return	$result;

		return	DBexecute("delete from sysmaps_elements where selementid=$selementid");
	}

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

	function get_png_by_selementid($selementid)
	{
		$elements = DBselect("select * from sysmaps_elements where selementid=$selementid");
		if(!$elements)	return FALSE;

		$element = DBfetch($elements);
		if(!$element)	return FALSE;

		if(get_info_by_selementid($element["selementid"],$info,$color) != 0)
			$icon = $element["icon_on"];
		else
			$icon = $element["icon"];

		$image = get_image_by_name($icon);
		if(!$image)	return FALSE;
		return imagecreatefromstring($image['image']);
	}

	function	get_info_by_selementid($selementid, &$out_info, &$out_color)
	{
		global $colors;

		$count = 0;
		$info	= S_OK_BIG;
		$color	= $colors["Dark Green"];
		
		$db_element = get_sysmaps_element_by_selementid($selementid);
		if($db_element["elementtype"]==SYSMAP_ELEMENT_TYPE_HOST)
		{
			$db_triggers = DBselect("select distinct t.triggerid, t.priority".
				" from items i,functions f,triggers t,hosts h where h.hostid=i.hostid".
				" and i.hostid=".$db_element["elementid"]." and i.itemid=f.itemid".
				" and f.triggerid=t.triggerid and t.value=1 and t.status=0".
				" and h.status=".HOST_STATUS_MONITORED." and i.status=0");

			$trigger = DBfetch($db_triggers);
			if($trigger)
			{
				for($count=1; DBfetch($db_triggers); $count++);

				if ($trigger["priority"] > 3)           $color=$colors["Red"];
				else                                    $color=$colors["Dark Yellow"];
				$info = expand_trigger_description_simple($trigger["triggerid"]);
			}
			else
			{
				$host = get_host_by_hostid($db_element["elementid"]);
				if($host["status"] == HOST_STATUS_TEMPLATE)
				{
					$color = $colors["Gray"];
					$info = "template";
				}
			}
		}
		elseif($db_element["elementtype"]==SYSMAP_ELEMENT_TYPE_MAP)
		{
			$db_subelements = DBselect("select selementid from sysmaps_elements".
				" where sysmapid=".$db_element["elementid"]);
			while($db_subelement = DBfetch($db_subelements))
			{// recursion
				if(($curr_count = get_info_by_selementid($db_subelement["selementid"],$curr_info,$curr_color)) > 0)
				{
					$count += $curr_count;
					$info = $curr_info;
					$color = $curr_color;
				}
			}
		}
		elseif($db_element["elementtype"]==SYSMAP_ELEMENT_TYPE_TRIGGER)
		{
			if($db_element["elementid"]>0){
				$trigger=get_trigger_by_triggerid($db_element["elementid"]);
				if($trigger["value"] == TRIGGER_VALUE_TRUE)
				{
					$info=S_TRUE_BIG;
					$color=$colors["Red"];
					$count = 1;
				}
				else
				{
					$info=S_FALSE_BIG;
				}
			}
		}
		elseif($db_element["elementtype"]==SYSMAP_ELEMENT_TYPE_HOST_GROUP)
		{
			$db_triggers = DBselect("select distinct t.triggerid, t.priority ".
				" from items i,functions f,triggers t,hosts h,hosts_groups hg ".
				" where h.hostid=i.hostid".
				" and hg.groupid=".$db_element["elementid"].
				" and hg.hostid=h.hostid and i.itemid=f.itemid".
				" and f.triggerid=t.triggerid and t.value=1 and t.status=0".
				" and h.status=".HOST_STATUS_MONITORED." and i.status=0");

			$trigger = DBfetch($db_triggers);
			if($trigger)
			{
				for($count=1; DBfetch($db_triggers); $count++);

				if ($trigger["priority"] > 3)           $color=$colors["Red"];
				else                                    $color=$colors["Dark Yellow"];
				$info = expand_trigger_description_simple($trigger["triggerid"]);
			}
		}

		if($count>1)
		{
			$out_info	= $count." ".S_PROBLEMS_SMALL;
			$out_color	= $colors["Red"];
		}
		else
		{
			$out_info	= $info;
			$out_color	= $color;
		}

		return $count;
	}

	function get_action_map_by_sysmapid($sysmapid)
	{
		$action_map = new CMap("links$sysmapid");

		$db_elements=DBselect("select * from sysmaps_elements where sysmapid=$sysmapid");
		while($db_element = DBfetch($db_elements))
		{
			$url	= $db_element["url"];
			$alt	= "Label: ".$db_element["label"];

			if($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_HOST)
			{
				$host = get_host_by_hostid($db_element["elementid"]);
				if($host["status"] != HOST_STATUS_MONITORED)	continue;

			
				if($url=="")
					$url="tr_status.php?hostid=".$db_element["elementid"].
						"&noactions=true&onlytrue=true&compact=true";

				$alt = "Host: ".$host["host"]." ".$alt;
			}
			elseif($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_MAP)
			{
				$map = get_sysmap_by_sysmapid($db_element["elementid"]);

				if($url=="")
					$url="maps.php?sysmapid=".$db_element["elementid"];

				$alt = "Host: ".$map["name"]." ".$alt;
			}
			elseif($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_TRIGGER)
			{
				if($url=="" && $db_element["elementid"]!=0)
					$url="alarms.php?triggerid=".$db_element["elementid"];
			}
			elseif($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_HOST_GROUP)
			{
				if($url=="" && $db_element["elementid"]!=0)
					$url="events.php?groupid=".$db_element["elementid"];
			}

			if($url=="")	continue;

			$back = get_png_by_selementid($db_element["selementid"]);
			if(!$back)	continue;

			$x1_		= $db_element["x"];
			$y1_		= $db_element["y"];
			$x2_		= $db_element["x"] + imagesx($back);
			$y2_		= $db_element["y"] + imagesy($back);

			$action_map->AddRectArea($x1_,$y1_,$x2_,$y2_, $url, $alt);
		}
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
		if($drawtype == GRAPH_DRAW_TYPE_BOLDLINE)
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
		else if($drawtype == GRAPH_DRAW_TYPE_DASHEDLINE)
		{
			if(function_exists("imagesetstyle"))
			{ /* Use ImageSetStyle+ImageLIne instead of bugged ImageDashedLine */
				$style = array($color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
				ImageSetStyle($image, $style);
				ImageLine($image,$x1,$y1,$x2,$y2,IMG_COLOR_STYLED);
			}
			else
			{
				ImageDashedLine($image,$x1,$y1,$x2,$y2,$color);
			}
		}
		else
		{
			ImageLine($image,$x1,$y1,$x2,$y2,$color);
		}
	}
?>
