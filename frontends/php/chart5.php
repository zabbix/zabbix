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
	require_once "include/config.inc.php";

#	PARAMETERS:
	
#	itemid
#	type

	$start_time=time(NULL);

	if(!isset($_REQUEST["type"]))
	{
		$_REQUEST["type"]="week";
	}

	if($_REQUEST["type"] == "month")
	{
		$period=30*24*3600;
	}
	else if($_REQUEST["type"] == "week")
	{
		$period=7*24*3600;
	}
	else if($_REQUEST["type"] == "year")
	{
		$period=365*24*3600;
	}
	else
	{
		$period=7*24*3600;
		$type="week";
	}

	$sizeX=900;
	$sizeY=300;

	$shiftX=12;
	$shiftYup=17;
	$shiftYdown=25+15*3;

	set_image_header();

	check_authorisation();

	$im = imagecreate($sizeX+$shiftX+61,$sizeY+$shiftYup+$shiftYdown+10); 
  
	$red=ImageColorAllocate($im,255,0,0); 
	$darkred=ImageColorAllocate($im,150,0,0); 
	$green=ImageColorAllocate($im,0,255,0); 
	$darkgreen=ImageColorAllocate($im,0,150,0); 
	$blue=ImageColorAllocate($im,0,0,255); 
	$darkblue=ImageColorAllocate($im,0,0,150); 
	$yellow=ImageColorAllocate($im,255,255,0); 
	$darkyellow=ImageColorAllocate($im,150,150,0); 
	$cyan=ImageColorAllocate($im,0,255,255); 
	$black=ImageColorAllocate($im,0,0,0); 
	$gray=ImageColorAllocate($im,150,150,150); 
	$white=ImageColorAllocate($im,255,255,255); 
	$bg=ImageColorAllocate($im,6+6*16,7+7*16,8+8*16);

	$x=imagesx($im); 
	$y=imagesy($im);
  
//	ImageFilledRectangle($im,0,0,$sizeX+$shiftX+61,$sizeY+$shiftYup+$shiftYdown+10,$white);
	ImageFilledRectangle($im,0,0,$x,$y,$white);
	ImageRectangle($im,0,0,$x-1,$y-1,$black);

//	if(!check_right_on_trigger("R",$_REQUEST["triggerid"]))
//	{
//		ImageOut($im); 
//		ImageDestroy($im); 
//		exit;
//	}


	$service=get_service_by_serviceid($_REQUEST["serviceid"]);

	$str=$service["name"]." (year ".date("Y").")";
	$x=imagesx($im)/2-ImageFontWidth(4)*strlen($str)/2;
	ImageString($im, 4,$x,1, $str , $darkred);

	$now = time(NULL);
	$to_time=$now;
	$from_time=$to_time-$period;
	$from_time_now=$to_time-24*3600;

	$count_now=array();
	$problem=array();

	$year=date("Y");
	$start=mktime(0,0,0,1,1,$year);

	$wday=date("w",$start);
	if($wday==0) $wday=7;
	$start=$start-($wday-1)*24*3600;
	for($i=0;$i<52;$i++)
	{
		$period_start=$start+7*24*3600*$i;
		$period_end=$start+7*24*3600*($i+1);
		$stat=calculate_service_availability($_REQUEST["serviceid"],$period_start,$period_end);
		
		$problem[$i]=$stat["problem"];
		$ok[$i]=$stat["ok"];
		$count_now[$i]=1;
	}

	for($i=0;$i<=$sizeY;$i+=$sizeY/10)
	{
		DashedLine($im,$shiftX,$i+$shiftYup,$sizeX+$shiftX,$i+$shiftYup,$gray);
	}

	$j=0;
	for($i=0;$i<=$sizeX;$i+=$sizeX/52)
	{
		DashedLine($im,$i+$shiftX,$shiftYup,$i+$shiftX,$sizeY+$shiftYup,$gray);
		$period_start=$start+7*24*3600*$j;
		ImageStringUp($im, 1,$i+$shiftX-4, $sizeY+$shiftYup+32, date("d.M",$period_start) , $black);
		$j++;
	}

	$maxY=100;
	$tmp=max($problem);
	if($tmp>$maxY)
	{
		$maxY=$tmp;
	}
	$minY=0;

	$maxX=900;
	$minX=0;

	for($i=1;$i<=52;$i++)
	{
//		$x1=(900/52)*$sizeX*($i-$minX)/($maxX-$minX);
//		$y1=$sizeY*($problem[$i]-$minY)/($maxY-$minY);
//		$x2=(900/52)*$sizeX*($i-$minX-1)/($maxX-$minX);
//		$y2=$sizeY*($problem[$i-1]-$minY)/($maxY-$minY);
//		$y1=$sizeY-$y1;
//		$y2=$sizeY-$y2;

//		ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$darkred);

//		ImageRectangle($im,$x1+$shiftX-1,$y1+$shiftYup-1,$x1+$shiftX+1,$y1+$shiftYup+1,$darkred);
//		ImageRectangle($im,$x2+$shiftX-1,$y2+$shiftYup-1,$x2+$shiftX+1,$y2+$shiftYup+1,$darkred);


//		$x1=(900/52)*$sizeX*($i-$minX)/($maxX-$minX);
//		$y1=$sizeY*($ok[$i]-$minY)/($maxY-$minY);
		$x2=(900/52)*$sizeX*($i-$minX-1)/($maxX-$minX);
		$y2=$sizeY*($ok[$i-1]-$minY)/($maxY-$minY);
//		$y1=$sizeY-$y1;
		$y2=$sizeY-$y2;

//		ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$darkgreen);

//		ImageRectangle($im,$x1+$shiftX-1,$y1+$shiftYup-1,$x1+$shiftX+1,$y1+$shiftYup+1,$darkgreen);
//		ImageRectangle($im,$x2+$shiftX-1,$y2+$shiftYup-1,$x2+$shiftX+1,$y2+$shiftYup+1,$darkgreen);

		ImageFilledRectangle($im,$x2+$shiftX,$y2+$shiftYup,$x2+$shiftX+8,$sizeY+$shiftYup,ImageColorAllocate($im,120,200,120));
		ImageRectangle($im,$x2+$shiftX,$y2+$shiftYup,$x2+$shiftX+8,$sizeY+$shiftYup,$black);
// Doesn't work for some reason
		ImageFilledRectangle($im,$x2+$shiftX,$shiftYup,$x2+$shiftX+8,$y2+$shiftYup,ImageColorAllocate($im,200,120,120));
		ImageRectangle($im,$x2+$shiftX,$shiftYup,$x2+$shiftX+8,$y2+$shiftYup,$black);
//		ImageRectangle($im,$x2+$shiftX,$sizeY+$shiftYup,$x2+$shiftX+8,$shiftYup,$black);


/*
		$x1=(900/52)*$sizeX*($i-$minX)/($maxX-$minX);
		$y1=$sizeY*($unknown[$i]-$minY)/($maxY-$minY);
		$x2=(900/52)*$sizeX*($i-$minX-1)/($maxX-$minX);
		$y2=$sizeY*($unknown[$i-1]-$minY)/($maxY-$minY);
		$y1=$sizeY-$y1;
		$y2=$sizeY-$y2;

		ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$darkyellow);

		ImageRectangle($im,$x1+$shiftX-1,$y1+$shiftYup-1,$x1+$shiftX+1,$y1+$shiftYup+1,$darkyellow);
		ImageRectangle($im,$x2+$shiftX-1,$y2+$shiftYup-1,$x2+$shiftX+1,$y2+$shiftYup+1,$darkyellow);*/

#			ImageStringUp($im, 1, $x1+10, $sizeY+$shiftYup+15, $i , $red);
	}

	for($i=0;$i<=$sizeY;$i+=$sizeY/10)
	{
		ImageString($im, 1, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftYup, ($i*($maxY-$minY)/$sizeY+$minY)."%" , ImageColorAllocate($im,200,120,120));
	}

	ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*0,$shiftX+5,$sizeY+$shiftYup+35+9+15*0,ImageColorAllocate($im,120,200,120));
	ImageRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*0,$shiftX+5,$sizeY+$shiftYup+35+9+15*0,$black);
	ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*0+35, "OK (%)", $black);

	ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*1,$shiftX+5,$sizeY+$shiftYup+35+9+15*1,$darkred);
	ImageRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*1,$shiftX+5,$sizeY+$shiftYup+15+9+35*1,$black);
	ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*1+35, "PROBLEMS (%)", $black);

//	ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*2,$shiftX+5,$sizeY+$shiftYup+35+9+15*2,$darkyellow);
//	ImageRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*2,$shiftX+5,$sizeY+$shiftYup+35+9+15*2,$black);
//	ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*2+35, "UNKNOWN (%)", $black);

	ImageStringUp($im,0,imagesx($im)-10,imagesy($im)-50, "http://www.zabbix.com", $gray);

	$end_time=time(NULL);
	ImageString($im, 0,imagesx($im)-100,imagesy($im)-12,"Generated in ".($end_time-$start_time)." sec", $gray);

	ImageOut($im); 
	ImageDestroy($im); 
?>
