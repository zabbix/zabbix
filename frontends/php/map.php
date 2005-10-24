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
	include "include/config.inc.php";
	include_once "include/locales/en_gb.inc.php";

	process_locales();

#	PARAMETERS:

#	sysmapid
#	noedit

	$grid=50;

	$result=DBselect("select * from sysmaps where sysmapid=".$_REQUEST["sysmapid"]);
	$row=DBfetch($result);

	$name=$row["name"];
	$width=$row["width"];
	$height=$row["height"];
	$background=$row["background"];
	$label_type=$row["label_type"];

//	Header( "Content-type:  text/html"); 
	if(MAP_OUTPUT_FORMAT == "JPG")	Header( "Content-type:  image/jpeg"); 
	else				Header( "Content-type:  image/png"); 
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
	if($background!="")
	{
		$sql="select image from images where imagetype=2 and name='$background'";
		$result2=DBselect($sql);
		if(DBnum_rows($result2)==1)
		{
			$back=ImageCreateFromString(DBget_field($result2,0,0));
			ImageCopy($im,$back,0,0,0,0,imagesx($back),imagesy($back));
		}
		else
		{
			ImageFilledRectangle($im,0,0,$width,$height,$white);
			$x=imagesx($im)/2-ImageFontWidth(4)*strlen($name)/2;
			ImageString($im, 4,$x,1, $name , $colors["Dark Red"]);
		}
	}
	else
	{
		ImageFilledRectangle($im,0,0,$width,$height,$white);
		$x=imagesx($im)/2-ImageFontWidth(4)*strlen($name)/2;
		ImageString($im, 4,$x,1, $name , $colors["Dark Red"]);
	}

	if(!isset($_REQUEST["border"]))
	{
		ImageRectangle($im,0,0,$width-1,$height-1,$colors["Black"]);
	}

//	$x=imagesx($im)/2-ImageFontWidth(4)*strlen($name)/2;
//	ImageString($im, 4,$x,1, $name , $colors["Dark Red"]);

	$str=date("m.d.Y H:i:s",time(NULL));
	ImageString($im, 0,imagesx($im)-120,imagesy($im)-12,"$str", $gray);

	if(!check_right("Network map","R",$_REQUEST["sysmapid"]))
	{
		ImageOut($im); 
		ImageDestroy($im); 
		exit();
	}

	if(!isset($_REQUEST["noedit"]))
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

	$result=DBselect("select shostid1,shostid2,triggerid,color_off,drawtype_off,color_on,drawtype_on from sysmaps_links where sysmapid=".$_REQUEST["sysmapid"]);
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$shostid1=DBget_field($result,$i,0);
		$shostid2=DBget_field($result,$i,1);
		$triggerid=DBget_field($result,$i,2);
		$color_off=DBget_field($result,$i,3);
		$drawtype_off=DBget_field($result,$i,4);
		$color_on=DBget_field($result,$i,5);
		$drawtype_on=DBget_field($result,$i,6);

		$result1=DBselect("select x,y,icon from sysmaps_hosts where shostid=$shostid1");
		$x1=DBget_field($result1,0,0);
		$y1=DBget_field($result1,0,1);
		$image1=get_image_by_name(1,DBget_field($result1,0,2));

		$result1=DBselect("select x,y,icon from sysmaps_hosts where shostid=$shostid2");
		$x2=DBget_field($result1,0,0);
		$y2=DBget_field($result1,0,1);
		$image2=get_image_by_name(1,DBget_field($result1,0,2));

// Get image dimensions

		if($image1!=0)
		{
			$icon=ImageCreateFromString($image1["image"]);
			$sizex1=imagesx($icon);
			$sizey1=imagesx($icon);
		}
		else
		{
			$sizex1=0;
			$sizey1=0;
		}
		if($image2!=0)
		{
			$icon=ImageCreateFromString($image2["image"]);
			$sizex2=imagesx($icon);
			$sizey2=imagesx($icon);
		}
		else
		{
			$sizex2=0;
			$sizey2=0;
		}

		if(isset($triggerid))
		{
			$trigger=get_trigger_by_triggerid($triggerid);
			if($trigger["value"] == TRIGGER_VALUE_TRUE)
			{
				if($drawtype_on == GRAPH_DRAW_TYPE_BOLDLINE)
				{
					ImageLine($im,$x1+$sizex1/2,$y1+$sizey1/2,$x2+$sizex2/2,$y2+$sizey2/2,$colors[$color_on]);
					ImageLine($im,$x1+$sizex1/2,$y1+$sizey1/2+1,$x2+$sizex2/2,$y2+$sizey2/2+1,$colors[$color_on]);
				}
				else if($drawtype_on == GRAPH_DRAW_TYPE_DASHEDLINE)
				{
					DashedLine($im,$x1+$sizex1/2,$y1+$sizey1/2,$x2+$sizex2/2,$y2+$sizey2/2,$colors[$color_on]);
				}
				else
				{
					ImageLine($im,$x1+$sizex1/2,$y1+$sizey1/2,$x2+$sizex2/2,$y2+$sizey2/2,$colors[$color_on]);
				}
			}
			else
			{
				if($drawtype_off == GRAPH_DRAW_TYPE_BOLDLINE)
				{
					ImageLine($im,$x1+$sizex1/2,$y1+$sizey1/2,$x2+$sizex2/2,$y2+$sizey2/2,$colors[$color_off]);
					ImageLine($im,$x1+$sizex1/2,$y1+$sizey1/2+1,$x2+$sizex2/2,$y2+$sizey2/2+1,$colors[$color_off]);
				}
				else if($drawtype_off == GRAPH_DRAW_TYPE_DASHEDLINE)
				{
					DashedLine($im,$x1+$sizex1/2,$y1+$sizey1/2,$x2+$sizex2/2,$y2+$sizey2/2,$colors[$color_off]);
				}
				else
				{
					ImageLine($im,$x1+$sizex1/2,$y1+$sizey1/2+1,$x2+$sizex2/2,$y2+$sizey2/2+1,$colors[$color_off]);
				}
			}
		}
		else
		{
			ImageLine($im,$x1+$sizex1/2,$y1+$sizey1/2,$x2+$sizex2/2,$y2+$sizey2/2,$colors["Black"]);
		}
	}

# Draw hosts

	$icons=array();
	$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,sh.y,h.status,sh.icon,sh.icon_on,h.ip from sysmaps_hosts sh,hosts h where sh.sysmapid=".$_REQUEST["sysmapid"]." and h.hostid=sh.hostid");
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
		$icon_on=DBget_field($result,$i,9);
		$ip=DBget_field($result,$i,10);


		$result1=DBselect("select count(distinct t.triggerid) from items i,functions f,triggers t,hosts h where h.hostid=i.hostid and i.hostid=$hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.value=1 and t.status=0 and h.status=".HOST_STATUS_MONITORED." and i.status=0");
		$count=DBget_field($result1,0,0);

		if( ($status!=HOST_STATUS_NOT_MONITORED)&&($count>0))
		{
			$icon=$icon_on;
		}

		if(@gettype($icons["$icon"])!="resource")
		{
/*
			if(function_exists("imagecreatetruecolor")&&@imagecreatetruecolor(1,1))
			{
				$icons[$icon]=ImageCreateFromPNG("images/sysmaps/$icon.png");
			}
			else
			{
				$icons[$icon]=ImageCreateFromPNG("images/sysmaps/old/$icon.png");
			}
*/
			$sql="select image from images where imagetype=1 and name='$icon'";
			$result2=DBselect($sql);
			if(DBnum_rows($result2)!=1)
			{
				$icons[$icon] = imagecreatetruecolor(48,48);
			}
			else
			{
				$icons[$icon]=ImageCreateFromString(DBget_field($result2,0,0));
			}
		}

		$img=$icons[$icon];

//		imagecolortransparent ($img, imagecolorat ($img, 0, 0));
//		imagecolortransparent ($img, 0, 0, 0);
		ImageCopy($im,$img,$x,$y,0,0,ImageSX($img),ImageSY($img));

		$first_line="";
		if($label_type==MAP_LABEL_TYPE_HOSTNAME)
		{
			$first_line=$host;
		}
		else if($label_type==MAP_LABEL_TYPE_HOSTLABEL)
		{
			$first_line=$label;
		}
		else if($label_type==MAP_LABEL_TYPE_IP)
		{
			$first_line=$ip;
		}

		if($first_line!="")
		{

			$x1=$x+ImageSX($img)/2-ImageFontWidth(2)*strlen($first_line)/2;
			$y1=$y+ImageSY($img);
			ImageFilledRectangle($im,$x1-2, $y1,$x1+ImageFontWidth(2)*strlen($first_line), $y1+ImageFontHeight(2),$white);
			ImageString($im, 2, $x1, $y1, $first_line,$black);
		}

		if($status == HOST_STATUS_NOT_MONITORED)
		{
			$color=$darkred;
#			$label="Not monitored";
			$label="";
		}
		else
		{
			if($count==1)
			{
				$result1=DBselect("select distinct t.description,t.triggerid, t.priority from items i,functions f,triggers t,hosts h where h.hostid=i.hostid and i.hostid=$hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.value=1 and t.status=0 and h.status=".HOST_STATUS_MONITORED."  and i.status=0");
				$label=DBget_field($result1,0,0);
				if (DBget_field($result1,0,2) > 3)
					$color=$red;
				else
					$color=$darkyellow;

					$label=expand_trigger_description_simple(DBget_field($result1,0,1));
			}
			else if($count>1)
			{
				$color=$red;
				$label=$count." ".S_PROBLEMS_SMALL;
			}
			else
			{
				$color=$darkgreen;
				$label=S_OK_BIG;
			}
		}
		$x1=$x+ImageSX($img)/2-ImageFontWidth(2)*strlen($label)/2;
		$y1=$y+ImageSY($img);
		if($first_line!="")
		{
			$y1=$y1+ImageFontHeight(2);
		}
		if($label_type!=MAP_LABEL_TYPE_NOTHING)
		{
			ImageFilledRectangle($im,$x1-2, $y1,$x1+ImageFontWidth(2)*strlen($label), $y1+ImageFontHeight(2),$white);
			ImageString($im, 2, $x1, $y1, $label,$color);
		}
	}

	ImageStringUp($im,0,imagesx($im)-10,imagesy($im)-50, S_ZABBIX_URL, $gray);
	
	if(MAP_OUTPUT_FORMAT == "JPG")	ImageJPEG($im);
	else				ImageOut($im); #default

	ImageDestroy($im);
?>
