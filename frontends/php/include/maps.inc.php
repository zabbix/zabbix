<?php
/*
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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
	function	get_map_by_sysmapid($sysmapid)
	{
		global	$ERROR_MSG;

		$sql="select * from sysmaps where sysmapid=$sysmapid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			$ERROR_MSG="No system map with sysmapid=[$sysmapid]";
		}
		return	$result;
	}

	# Delete System Map

	function	delete_sysmap( $sysmapid )
	{
		$sql="delete from sysmaps where sysmapid=$sysmapid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from sysmaps_hosts where sysmapid=$sysmapid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from sysmaps_links where sysmapid=$sysmapid";
		return	DBexecute($sql);
	}

	# Update System Map

	function	update_sysmap($sysmapid,$name,$width,$height,$background,$label_type)
	{
		global	$ERROR_MSG;

		if(!check_right("Network map","U",$sysmapid))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="update sysmaps set name='$name',width=$width,height=$height,background='$background',label_type=$label_type where sysmapid=$sysmapid";
		return	DBexecute($sql);
	}

	# Add System Map

	function	add_sysmap($name,$width,$height,$background,$label_type)
	{
		global	$ERROR_MSG;

		if(!check_right("Network map","A",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="insert into sysmaps (name,width,height,background,label_type) values ('$name',$width,$height,'$background',$label_type)";
		return	DBexecute($sql);
	}

	function	add_link($sysmapid,$shostid1,$shostid2,$triggerid,$drawtype_off,$color_off,$drawtype_on,$color_on)
	{
		if($triggerid == 0)
		{
			$sql="insert into sysmaps_links (sysmapid,shostid1,shostid2,triggerid,drawtype_off,color_off,drawtype_on,color_on) values ($sysmapid,$shostid1,$shostid2,NULL,$drawtype_off,'$color_off',$drawtype_on,'$color_on')";
		}
		else
		{
			$sql="insert into sysmaps_links (sysmapid,shostid1,shostid2,triggerid,drawtype_off,color_off,drawtype_on,color_on) values ($sysmapid,$shostid1,$shostid2,$triggerid,$drawtype_off,'$color_off',$drawtype_on,'$color_on')";
		}
		return	DBexecute($sql);
	}

	function	delete_link($linkid)
	{
		$sql="delete from sysmaps_links where linkid=$linkid";
		return	DBexecute($sql);
	}

	# Add Host to system map

	function add_host_to_sysmap($sysmapid,$hostid,$label,$x,$y,$icon,$url,$icon_on)
	{
		$sql="insert into sysmaps_hosts (sysmapid,hostid,label,x,y,icon,url,icon_on) values ($sysmapid,$hostid,'$label',$x,$y,'$icon','$url','$icon_on')";
		return	DBexecute($sql);
	}

	function	update_sysmap_host($shostid,$sysmapid,$hostid,$label,$x,$y,$icon,$url,$icon_on)
	{
		$sql="update sysmaps_hosts set hostid=$hostid,label='$label',x=$x,y=$y,icon='$icon',url='$url',icon_on='$icon_on' where shostid=$shostid";
		return	DBexecute($sql);
	}

	function	delete_sysmaps_host_by_hostid($hostid)
	{
		$sql="select shostid from sysmaps_hosts where hostid=$hostid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$sql="delete from sysmaps_links where shostid1=".$row["shostid"]." or shostid2".$row["shostid"];
			DBexecute($sql);
		}
		$sql="delete from sysmaps_hosts where hostid=$hostid";
		return DBexecute($sql);
	}

	# Delete Host from sysmap definition

	function	delete_sysmaps_host($shostid)
	{
		$sql="delete from sysmaps_links where shostid1=$shostid or shostid2=$shostid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from sysmaps_hosts where shostid=$shostid";
		return	DBexecute($sql);
	}

	function get_map_imagemap($sysmapid)
	{
		$map="\n<map name=links$sysmapid>";
		$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,sh.y,h.status,sh.icon,sh.url from sysmaps_hosts sh,hosts h where sh.sysmapid=$sysmapid and h.hostid=sh.hostid");
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$host=DBget_field($result,$i,0);
			$shostid=DBget_field($result,$i,1);
			$sysmapid=DBget_field($result,$i,2);
			$hostid=DBget_field($result,$i,3);
			$label=DBget_field($result,$i,4);
			$x=DBget_field($result,$i,5);
			$y=DBget_field($result,$i,6);
			$status=DBget_field($result,$i,7);
			$icon=DBget_field($result,$i,8);
			$url=DBget_field($result,$i,9);

			if($status==HOST_STATUS_MONITORED)
			{
				$sql="select image from images where imagetype=1 and name='$icon'";
				$result2=DBselect($sql);
				if(DBnum_rows($result2)==1)
				{
					$back=ImageCreateFromString(DBget_field($result2,0,0));
					$sizex = imagesx($back);
					$sizey = imagesy($back);
					if($url=="")
					{
						$url="tr_status.php?hostid=$hostid&noactions=true&onlytrue=true&compact=true";
					}
					$map=$map."\n<area shape=rect coords=$x,$y,".($x+$sizex).",".($y+$sizey)." href=\"$url\" alt=\"Host: $host Label: $label\">";
				}

/*				if(function_exists("imagecreatetruecolor")&&@imagecreatetruecolor(1,1))
				{
					$map=$map."\n<area shape=rect coords=$x,$y,".($x+48).",".($y+48)." href=\"tr_status.php?hostid=$hostid&noactions=true&onlytrue=true&compact=true\" alt=\"$host\">";
				}
				else
				{
					$map=$map."\n<area shape=rect coords=$x,$y,".($x+32).",".($y+32)." href=\"tr_status.php?hostid=$hostid&noactions=true&onlytrue=true&compact=true\" alt=\"$host\">";
				}*/
			}
		}
		$map=$map."\n</map>";
		return $map;
	}
?>
