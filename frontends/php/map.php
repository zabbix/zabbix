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

	$map	= get_sysmap_by_sysmapid($_REQUEST["sysmapid"]);

	$name		= $map["name"];
	$width		= $map["width"];
	$height		= $map["height"];
	$background	= $map["background"];
	$label_type	= $map["label_type"];

	set_image_header();

	check_authorisation();

	if(function_exists("imagecreatetruecolor")&&@imagecreatetruecolor(1,1))
	{
		$im = imagecreatetruecolor($width,$height);
	}
	else
	{
		$im = imagecreate($width,$height);
	}
  
	$red		= ImageColorAllocate($im,255,0,0); 
	$darkred	= ImageColorAllocate($im,150,0,0); 
	$green		= ImageColorAllocate($im,0,255,0);
	$darkgreen	= ImageColorAllocate($im,0,150,0); 
	$blue		= ImageColorAllocate($im,0,0,255);
	$yellow		= ImageColorAllocate($im,255,255,0);
	$darkyellow	= ImageColorAllocate($im,150,127,0);
	$cyan		= ImageColorAllocate($im,0,255,255);
	$white		= ImageColorAllocate($im,255,255,255); 
	$black		= ImageColorAllocate($im,0,0,0); 
	$gray		= ImageColorAllocate($im,150,150,150);

	$colors["Red"]		= ImageColorAllocate($im,255,0,0); 
	$colors["Dark Red"]	= ImageColorAllocate($im,150,0,0); 
	$colors["Green"]	= ImageColorAllocate($im,0,255,0); 
	$colors["Dark Green"]	= ImageColorAllocate($im,0,150,0); 
	$colors["Blue"]		= ImageColorAllocate($im,0,0,255); 
	$colors["Dark Blue"]	= ImageColorAllocate($im,0,0,150); 
	$colors["Yellow"]	= ImageColorAllocate($im,255,255,0); 
	$colors["Dark Yellow"]	= ImageColorAllocate($im,150,150,0); 
	$colors["Cyan"]		= ImageColorAllocate($im,0,255,255); 
	$colors["Black"]	= ImageColorAllocate($im,0,0,0); 
	$colors["Gray"]		= ImageColorAllocate($im,150,150,150); 
	$colors["White"]	= ImageColorAllocate($im,255,255,255);

	$x=imagesx($im); 
	$y=imagesy($im);
  
	ImageFilledRectangle($im,0,0,$width,$height,$white);
	if($background!="")
	{
		$db_image = get_image_by_name($background, 2);
		if($db_image)
		{
			$back = ImageCreateFromString($db_image["image"]);
			ImageCopy($im,$back,0,0,0,0,imagesx($back),imagesy($back));
		}
		else
		{
			$x=imagesx($im)/2-ImageFontWidth(4)*strlen($name)/2;
			ImageString($im, 4,$x,1, $name , $darkred);
		}
	}
	else
	{
		$x=imagesx($im)/2-ImageFontWidth(4)*strlen($name)/2;
		ImageString($im, 4,$x,1, $name , $colors["Dark Red"]);
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
			MyDrawLine($im,$x,0,$x,$height,$black,GRAPH_DRAW_TYPE_DASHEDLINE);
			ImageString($im, 2, $x+2,2, $x , $black);
		}
		for($y=$grid;$y<$height;$y+=$grid)
		{
			MyDrawLine($im,0,$y,$width,$y,$black,GRAPH_DRAW_TYPE_DASHEDLINE);
			ImageString($im, 2, 2,$y+2, $y , $black);
		}

		ImageString($im, 2, 1,1, "Y X:" , $black);
	}

# Draw connectors 

	$links = DBselect("select * from sysmaps_links where sysmapid=".$_REQUEST["sysmapid"]);
	while($link = DBfetch($links))
	{
		list($x1, $y1) = get_icon_center_by_selementid($link["selementid1"]);
		list($x2, $y2) = get_icon_center_by_selementid($link["selementid2"]);

		$drawtype = $link["drawtype_off"];
		$color = $colors[$link["color_off"]];

		if(!is_null($link["triggerid"]))
		{
			$trigger=get_trigger_by_triggerid($link["triggerid"]);
			if($trigger["value"] == TRIGGER_VALUE_TRUE)
			{
				$drawtype = $link["drawtype_on"];
				$color = $colors[$link["color_on"]];
			}
		}
		MyDrawLine($im,$x1,$y1,$x2,$y2,$color,$drawtype);
	}

# Draw elements
	$icons=array();
	$db_elements = DBselect("select * from sysmaps_elements where sysmapid=".$_REQUEST["sysmapid"]);

	while($db_element = DBfetch($db_elements))
	{
		$img = get_png_by_selementid($db_element["selementid"]);

		if($img)
			ImageCopy($im,$img,$db_element["x"],$db_element["y"],0,0,ImageSX($img),ImageSY($img));

		if($label_type==MAP_LABEL_TYPE_NOTHING)	continue;

		$color		= $darkgreen;
		$label_color	= $black;
		$info_line	= "";
		$label_location = $db_element["label_location"];
		if(is_null($label_location))	$map["label_location"];

		$label_line = $db_element["label"];

		if($label_type==MAP_LABEL_TYPE_STATUS)
			$label_line = "";

		if($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_HOST)
		{
			$host = get_host_by_hostid($db_element["elementid"]);

			if($label_type==MAP_LABEL_TYPE_NAME)
			{
				$label_line=$host["host"];
			}
			else if($label_type==MAP_LABEL_TYPE_IP)
			{
				$label_line=$host["ip"];
			}

			if($host["status"] == HOST_STATUS_NOT_MONITORED)
			{
				$label_color=$darkred;
			}
		}
		elseif($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_MAP)
		{
			$map = get_sysmap_by_sysmapid($db_element["elementid"]);
			if($label_type==MAP_LABEL_TYPE_NAME)
			{
				$label_line=$map["name"];
			}

		}
		elseif($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_IMAGE && $db_element["elementid"]>0)
		{
			if($label_type==MAP_LABEL_TYPE_NAME)
			{
				$label_line = expand_trigger_description_simple($db_element["elementid"]);
			}
		}

		get_info_by_selementid($db_element["selementid"],$info_line, $color);

		if($label_line=="" && $info_line=="")	continue;

		$x_label = $db_element["x"];
		$y_label = $db_element["y"];

		$x_info = $db_element["x"];
		$y_info = $db_element["y"];
		if($label_location == MAP_LABEL_LOC_TOP)
		{
			$x_label += ImageSX($img)/2-ImageFontWidth(2)*strlen($label_line)/2;
			$y_label -= ImageFontHeight(2)*($info_line == "" ? 1 : 2);

			$x_info += ImageSX($img)/2-ImageFontWidth(2)*strlen($info_line)/2;
			$y_info  = $y_label+ImageFontHeight(2);
		}
		else if($label_location == MAP_LABEL_LOC_LEFT)
		{
			$x_label -= ImageFontWidth(2)*strlen($label_line);
			$y_label += ImageSY($img)/2-ImageFontHeight(2)/2 - 
					($info_line == "" ? 0 : ImageFontHeight(2)/2);

			$x_info -= ImageFontWidth(2)*strlen($info_line);
			$y_info  = $y_label+ImageFontHeight(2) - ($label_line == "" ? ImageFontHeight(2)/2 : 0);
		}
		else if($label_location == MAP_LABEL_LOC_RIGHT)
		{
			$x_label += ImageSX($img);
			$y_label += ImageSY($img)/2-ImageFontHeight(2)/2 - 
					($info_line == "" ? 0 : ImageFontHeight(2)/2);

			$x_info += ImageSX($img);
			$y_info  = $y_label+ImageFontHeight(2) - ($label_line == "" ? ImageFontHeight(2)/2 : 0);
		}
		else
		{
			$x_label += ImageSX($img)/2-ImageFontWidth(2)*strlen($label_line)/2;
			$y_label += ImageSY($img);

			$x_info += ImageSX($img)/2-ImageFontWidth(2)*strlen($info_line)/2;
			$y_info  = $y_label+ ($label_line == "" ? 0 : ImageFontHeight(2));
		}

		if($label_line!="")
		{
			ImageFilledRectangle($im,
				$x_label-2, $y_label,
				$x_label+ImageFontWidth(2)*strlen($label_line), $y_label+ImageFontHeight(2),
				$white);
			ImageString($im, 2, $x_label, $y_label, $label_line,$label_color);
		}

		if($info_line!="")
		{
			ImageFilledRectangle($im,
				$x_info-2, $y_info,
				$x_info+ImageFontWidth(2)*strlen($info_line), $y_info+ImageFontHeight(2),
				$white);
			ImageString($im, 2, $x_info, $y_info, $info_line,$color);
		}
	}

	ImageStringUp($im,0,imagesx($im)-10,imagesy($im)-50, S_ZABBIX_URL, $gray);

	if(!isset($_REQUEST["border"]))
	{
		ImageRectangle($im,0,0,$width-1,$height-1,$colors["Black"]);
	}

	
	if(MAP_OUTPUT_FORMAT == "JPG")	ImageJPEG($im);
	else				ImageOut($im); #default

	ImageDestroy($im);
?>
