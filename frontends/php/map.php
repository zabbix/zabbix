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
	include "include/config.inc.php";

#	PARAMETERS:

#	sysmapid
#	noedit

	$grid=50;

	$result=DBselect("select name,width,height from sysmaps where sysmapid=".$HTTP_GET_VARS["sysmapid"]);

	$name=DBget_field($result,0,0);
	$width=DBget_field($result,0,1);
	$height=DBget_field($result,0,2);

//	Header( "Content-type:  text/html"); 
	Header( "Content-type:  image/png"); 
	Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

	check_authorisation();

	if(function_exists("imagecreatetruecolor")&&@imagecreatetruecolor(1,1))
	{
		$im = imagecreatetruecolor($width,$height);
	}
	else
	{
		$im = imagecreate($width,$height);
	}
  
	$red=ImageColorAllocate($im,255,0,0); 
	$darkred=ImageColorAllocate($im,150,0,0); 
	$green=ImageColorAllocate($im,0,255,0);
	$darkgreen=ImageColorAllocate($im,0,150,0); 
	$blue=ImageColorAllocate($im,0,0,255);
	$yellow=ImageColorAllocate($im,255,255,0);
	$darkyellow=ImageColorAllocate($im,150,127,0);
	$cyan=ImageColorAllocate($im,0,255,255);
	$white=ImageColorAllocate($im,255,255,255); 
	$black=ImageColorAllocate($im,0,0,0); 
	$gray=ImageColorAllocate($im,150,150,150);

	$colors["Red"]=ImageColorAllocate($im,255,0,0); 
	$colors["Dark Red"]=ImageColorAllocate($im,150,0,0); 
	$colors["Green"]=ImageColorAllocate($im,0,255,0); 
	$colors["Dark Green"]=ImageColorAllocate($im,0,150,0); 
	$colors["Blue"]=ImageColorAllocate($im,0,0,255); 
	$colors["Dark Blue"]=ImageColorAllocate($im,0,0,150); 
	$colors["Yellow"]=ImageColorAllocate($im,255,255,0); 
	$colors["Dark Yellow"]=ImageColorAllocate($im,150,150,0); 
	$colors["Cyan"]=ImageColorAllocate($im,0,255,255); 
	$colors["Black"]=ImageColorAllocate($im,0,0,0); 
	$colors["Gray"]=ImageColorAllocate($im,150,150,150); 
	$colors["White"]=ImageColorAllocate($im,255,255,255);

	$x=imagesx($im); 
	$y=imagesy($im);
  
#	ImageFilledRectangle($im,0,0,$width,$height,$black);
	ImageFilledRectangle($im,0,0,$width,$height,$white);

	if(!isset($HTTP_GET_VARS["border"]))
	{
		ImageRectangle($im,0,0,$width-1,$height-1,$colors["Black"]);
	}

	$x=imagesx($im)/2-ImageFontWidth(4)*strlen($name)/2;
	ImageString($im, 4,$x,1, $name , $colors["Dark Red"]);

	$str=date("m.d.Y H:i:s",time(NULL));
	ImageString($im, 0,imagesx($im)-120,imagesy($im)-12,"$str", $gray);

	if(!check_right("Network map","R",$HTTP_GET_VARS["sysmapid"]))
	{
		ImagePng($im); 
		ImageDestroy($im); 
		exit();
	}

	if(!isset($HTTP_GET_VARS["noedit"]))
	{
		for($x=$grid;$x<$width;$x+=$grid)
		{
			DashedLine($im,$x,0,$x,$height,$black);
			ImageString($im, 2, $x+2,2, $x , $black);
		}
		for($y=$grid;$y<$height;$y+=$grid)
		{
			DashedLine($im,0,$y,$width,$y,$black);
			ImageString($im, 2, 2,$y+2, $y , $black);
		}

		ImageString($im, 2, 1,1, "Y X:" , $black);
	}

# Draw connectors 

	$result=DBselect("select shostid1,shostid2,triggerid,color_off,drawtype_off,color_on,drawtype_on from sysmaps_links where sysmapid=".$HTTP_GET_VARS["sysmapid"]);
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$shostid1=DBget_field($result,$i,0);
		$shostid2=DBget_field($result,$i,1);
		$triggerid=DBget_field($result,$i,2);
		$color_off=DBget_field($result,$i,3);
		$drawtype_off=DBget_field($result,$i,4);
		$color_on=DBget_field($result,$i,5);
		$drawtype_on=DBget_field($result,$i,6);

		$result1=DBselect("select x,y from sysmaps_hosts where shostid=$shostid1");
		$x1=DBget_field($result1,0,0);
		$y1=DBget_field($result1,0,1);

		$result1=DBselect("select x,y from sysmaps_hosts where shostid=$shostid2");
		$x2=DBget_field($result1,0,0);
		$y2=DBget_field($result1,0,1);

		if(function_exists("imagecreatetruecolor")&&@imagecreatetruecolor(1,1))
		{
			$size=48/2;
		}
		else
		{
			$size=32/2;
		}
		if(isset($triggerid))
		{
			$trigger=get_trigger_by_triggerid($triggerid);
			if($trigger["value"] == TRIGGER_VALUE_TRUE)
			{
				if($drawtype_on == GRAPH_DRAW_TYPE_BOLDLINE)
				{
					ImageLine($im,$x1+$size,$y1+$size,$x2+$size,$y2+$size,$colors[$color_on]);
					ImageLine($im,$x1+$size,$y1+$size+1,$x2+$size,$y2+$size+1,$colors[$color_on]);
				}
				else if($drawtype_on == GRAPH_DRAW_TYPE_DASHEDLINE)
				{
					DashedLine($im,$x1+$size,$y1+$size,$x2+$size,$y2+$size,$colors[$color_on]);
				}
				else
				{
					ImageLine($im,$x1+$size,$y1+$size,$x2+$size,$y2+$size,$colors[$color_on]);
				}
			}
			else
			{
				if($drawtype_off == GRAPH_DRAW_TYPE_BOLDLINE)
				{
					ImageLine($im,$x1+$size,$y1+$size,$x2+$size,$y2+$size,$colors[$color_off]);
					ImageLine($im,$x1+$size,$y1+$size+1,$x2+$size,$y2+$size+1,$colors[$color_off]);
				}
				else if($drawtype_off == GRAPH_DRAW_TYPE_DASHEDLINE)
				{
					DashedLine($im,$x1+$size,$y1+$size,$x2+$size,$y2+$size,$colors[$color_off]);
				}
				else
				{
					ImageLine($im,$x1+$size,$y1+$size+1,$x2+$size,$y2+$size+1,$colors[$color_off]);
				}
			}
		}
		else
		{
			ImageLine($im,$x1+$size,$y1+$size,$x2+$size,$y2+$size,$colors["Black"]);
		}
	}

# Draw hosts

	$icons=array();
	$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,sh.y,h.status,sh.icon from sysmaps_hosts sh,hosts h where sh.sysmapid=".$HTTP_GET_VARS["sysmapid"]." and h.hostid=sh.hostid");
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

		if(@gettype($icons["$icon"])!="resource")
		{
			if(function_exists("imagecreatetruecolor")&&@imagecreatetruecolor(1,1))
			{
				$icons[$icon]=ImageCreateFromPNG("images/sysmaps/$icon.png");
			}
			else
			{
				$icons[$icon]=ImageCreateFromPNG("images/sysmaps/old/$icon.png");
			}
		}

		$img=$icons[$icon];

//		imagecolortransparent ($img, imagecolorat ($img, 0, 0));
//		imagecolortransparent ($img, 0, 0, 0);
		ImageCopy($im,$img,$x,$y,0,0,ImageSX($img),ImageSY($img));

		$x1=$x+ImageSX($img)/2-ImageFontWidth(2)*strlen($label)/2;
		$y1=$y+ImageSY($img);
		ImageFilledRectangle($im,$x1-2, $y1,$x1+ImageFontWidth(2)*strlen($label), $y1+ImageFontHeight(2),$white);
		ImageString($im, 2, $x1, $y1, $label,$black);

		if($status == 1)
		{
			$color=$darkred;
#			$label="Not monitored";
			$label="";
		}
		else
		{
			$result1=DBselect("select count(distinct t.triggerid) from items i,functions f,triggers t,hosts h where h.hostid=i.hostid and i.hostid=$hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.value=1 and t.status=0 and h.status in (0,2) and i.status=0");
			$count=DBget_field($result1,0,0);
			if($count==1)
			{
				$result1=DBselect("select distinct t.description,t.triggerid, t.priority from items i,functions f,triggers t,hosts h where h.hostid=i.hostid and i.hostid=$hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.value=1 and t.status=0 and h.status in (0,2) and i.status=0");
				$label=DBget_field($result1,0,0);
				if (DBget_field($result1,0,2) > 3)
					$color=$red;
				else
					$color=$darkyellow;

//				if( strstr($label,"%s"))
//				{
					$label=expand_trigger_description_simple(DBget_field($result1,0,1));
//				}
			}
			else if($count>1)
			{
				$color=$red;
				$label=$count." problems";
			}
			else
			{
				$color=$darkgreen;
				$label="OK";
			}
		}
		$x1=$x+ImageSX($img)/2-ImageFontWidth(2)*strlen($label)/2;
		$y1=$y+ImageSY($img)+ImageFontHeight(2);
		ImageFilledRectangle($im,$x1-2, $y1,$x1+ImageFontWidth(2)*strlen($label), $y1+ImageFontHeight(2),$white);
		ImageString($im, 2, $x1, $y1, $label,$color);

#		ImageFilledRectangle($im,$x+ImageSX($img)/2-ImageFontWidth(2)*strlen($label)/2-2, $y+ImageSY($img),$x+ImageSX($img)/2+ImageFontWidth(2)*strlen($label)/2, $y+ImageSY($img)+ImageFontHeight(2),$white);
#		ImageString($im, 2, $x+ImageSX($img)/2-ImageFontWidth(2)*strlen($label)/2, $y+ImageSY($img)+ImageFontHeight(2), $label,$color);
#		ImageDestroy($img);
	}

	ImageStringUp($im,0,imagesx($im)-10,imagesy($im)-50, "http://www.zabbix.com", $gray);

	ImagePng($im);
	ImageDestroy($im);
?>
