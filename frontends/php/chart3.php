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
	
#	itemid
#	type

	$start_time=time(NULL);

	if(!isset($HTTP_GET_VARS["type"]))
	{
		$HTTP_GET_VARS["type"]="week";
	}

	if($HTTP_GET_VARS["type"] == "month")
	{
		$period=30*24*3600;
	}
	else if($HTTP_GET_VARS["type"] == "week")
	{
		$period=7*24*3600;
	}
	else if($HTTP_GET_VARS["type"] == "year")
	{
		$period=365*24*3600;
	}
	else
	{
		$period=7*24*3600;
		$type="week";
	}

	if(isset($HTTP_GET_VARS["width"])&&$HTTP_GET_VARS["width"]>0)
	{
		$sizeX=$HTTP_GET_VARS["width"];
	}
	else
	{
		$sizeX=900;
	}
	$sizeY=200;

	$shiftX=12;
	$shiftYup=13;
	$shiftYdown=7+15*2;


//	Header( "Content-type:  text/html"); 
	Header( "Content-type:  image/png"); 
	Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

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
  
	ImageFilledRectangle($im,0,0,$sizeX+$shiftX+61,$sizeY+$shiftYup+$shiftYdown+10,$white);
	ImageRectangle($im,0,0,$x-1,$y-1,$black);

	if(!check_right("Item","R",$HTTP_GET_VARS["itemid"]))
	{
//		show_table_header("<font color=\"AA0000\">No permissions !</font>");
//		show_footer();
		ImagePng($im); 
		ImageDestroy($im); 
		exit;
	}


	$now = time(NULL);
	$to_time=$now;
	$from_time=$to_time-$period;
	$from_time_now=$to_time-24*3600;

	$count=array();
	$min=array();
	$max=array();
	$avg=array();

	$sql="select round(900*((clock+3*3600)%(24*3600))/(24*3600)) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max from history where itemid=".$HTTP_GET_VARS["itemid"]." and clock>$from_time and clock<$to_time group by 1";
//	echo $sql."<br>";
	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		$i=$row["i"];

		$max[$i]=$row["max"];
		$min[$i]=$row["min"];
		$avg[$i]=$row["avg"];
		$count[$i]=$row["count"];
	}

	$count_now=array();
	$avg_now=array();
	$result=DBselect("select round(900*((clock+3*3600)%(24*3600))/(24*3600)) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max from history where itemid=".$HTTP_GET_VARS["itemid"]." and clock>$from_time_now and clock<$to_time group by 1");
	while($row=DBfetch($result))
	{
		$i=$row["i"];
		$avg_now[$i]=$row["avg"];
		$count_now[$i]=$row["count"];
	}

	for($i=0;$i<=$sizeY;$i+=$sizeY/5)
	{
		DashedLine($im,$shiftX,$i+$shiftYup,$sizeX+$shiftX,$i+$shiftYup,$gray);
	}

	for($i=0;$i<=$sizeX;$i+=$sizeX/24)
	{
		DashedLine($im,$i+$shiftX,$shiftYup,$i+$shiftX,$sizeY+$shiftYup,$gray);
		ImageString($im, 1,$i+$shiftX-11, $sizeY+$shiftYup+5, date("H:i",-3*3600+24*3600*$i/900) , $black);
	}

	unset($maxY);
	unset($minY);

	$maxY=max($avg);
	$tmp=max($avg_now);
	if($tmp>$maxY)
	{
		$maxY=$tmp;
	}
	$minY=min($avg);
	$tmp=min($avg_now);
	if($tmp<$minY)
	{
		$minY=$tmp;
	}

	$maxX=900;
	$minX=0;

	if(isset($minY)&&($maxY)&&($minX!=$maxX)&&($minY!=$maxY))
	{
		$nodata=0;
		for($i=0;$i<900;$i++)
		{
			if(isset($count[$i])&&$count[$i]>0)
			{
/*				if(!isset($trendavg))
				{
					$x1=$sizeX*($i-$minX)/($maxX-$minX);
					$y1=$sizeY*($max[$i]-$minY)/($maxY-$minY);
					$x2=$sizeX*($i-$minX)/($maxX-$minX);
					$y2=$sizeY*($min[$i]-$minY)/($maxY-$minY);
					$y1=$sizeY-$y1;
					$y2=$sizeY-$y2;

					ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$bg);
				}*/

/*				if(!isset($trendavg))
				{
					$x1=$sizeX*($i-$minX)/($maxX-$minX);
					$y1=$sizeY*($max[$i]-$minY)/($maxY-$minY);
					$x2=$x1;
					$y2=0;
					$y1=$sizeY-$y1;
					$y2=$sizeY-$y2;

					ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$blue);
				}*/

				$x1=$sizeX*($i-$minX)/($maxX-$minX);
				$y1=$sizeY*($avg[$i]-$minY)/($maxY-$minY);
				$x2=$x1;
				$y2=0;
				$y1=$sizeY-$y1;
				$y2=$sizeY-$y2;
	
				ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$darkgreen);

/*				if(!isset($trendavg))
				{
					$x1=$sizeX*($i-$minX)/($maxX-$minX);
					$y1=$sizeY*($min[$i]-$minY)/($maxY-$minY);
					$x2=$x1;
					$y2=0;
					$y1=$sizeY-$y1;
					$y2=$sizeY-$y2;
	
					ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$green);
				}*/
			}
			if(isset($count_now[$i])&&isset($count_now[$i-1])&&($count_now[$i]>0)&&($count_now[$i-1]>0))
			{
				if($i>0)
				{
					$x1=$sizeX*($i-$minX)/($maxX-$minX);
					$y1=$sizeY*($avg_now[$i]-$minY)/($maxY-$minY);
					$x2=$sizeX*($i-$minX-1)/($maxX-$minX);
					$y2=$sizeY*($avg_now[$i-1]-$minY)/($maxY-$minY);
//					$x2=$x1;
//					$y2=0;
					$y1=$sizeY-$y1;
					$y2=$sizeY-$y2;
	
					ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$darkred);
//					ImageLine($im,$x1+$shiftX-1,$y1+$shiftYup,$x2+$shiftX-1,$y2+$shiftYup,$darkred);
				}
			}


#			ImageStringUp($im, 1, $x1+10, $sizeY+$shiftYup+15, $i , $red);
		}
	}
	else
	{
//		ImageLine($im,$shiftX,$shiftYup+$sizeY/2,$sizeX+$shiftX,$shiftYup+$sizeY/2,$green);
	}

	$i=intval( 900*(($now+3*3600)%(24*3600))/(24*3600));
	$x1=$sizeX*($i-$minX)/($maxX-$minX);
	$y1=$sizeY*($avg[$i]-$minY)/($maxY-$minY);
	$x2=$x1;
	$y2=0;
	$y1=$sizeY-$y1;
	$y2=$sizeY-$y2;

	DashedLine($im,$x1+$shiftX,$shiftYup,$x2+$shiftX,$sizeY+$shiftYup,$black);

	if(isset($nodata)&&($nodata == 0))
	{
		$item=get_item_by_itemid($HTTP_GET_VARS["itemid"]);
		for($i=0;$i<=$sizeY;$i+=$sizeY/5)
		{
			ImageString($im, 1, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftYup, convert_units($i*($maxY-$minY)/$sizeY+$minY,$item["units"],$item["multiplier"]) , $darkred);
		}

//		date("dS of F Y h:i:s A",DBget_field($result,0,0));

//		ImageString($im, 1,10,                $sizeY+$shiftY+5, date("dS of F Y h:i:s A",$minX) , $red);
//		ImageString($im, 1,$sizeX+$shiftX-168,$sizeY+$shiftY+5, date("dS of F Y h:i:s A",$maxX) , $red);
	}
	else
	{
		ImageString($im, 2,$sizeX/2 -50,                $sizeY+$shiftYup+3, "NO DATA FOR THIS PERIOD" , $red);
	}

	ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*0,$shiftX+5,$sizeY+$shiftYup+15+9+15*0,$darkgreen);
	ImageRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*0,$shiftX+5,$sizeY+$shiftYup+15+9+15*0,$black);
	if($HTTP_GET_VARS["type"]=="year")
	{
		ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*0+15, "Average for last 365 days", $black);
	}
	else if($HTTP_GET_VARS["type"]=="month")
	{
		ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*0+15, "Average for last 30 days", $black);
	}
	else
	{
		ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*0+15, "Average for last 7 days", $black);
	}

	ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*1,$shiftX+5,$sizeY+$shiftYup+15+9+15*1,$darkred);
	ImageRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*1,$shiftX+5,$sizeY+$shiftYup+15+9+15*1,$black);
	ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*1+15, "Average for last 24 hours", $black);

//	ImageString($im, 1,$shiftX, $sizeY+$shiftY+15, "AVG (LAST WEEK)" , $darkgreen);
//	ImageString($im, 1,$shiftX+80, $sizeY+$shiftY+15, "AVG (TODAY)" , $darkred);

	ImageStringUp($im,0,imagesx($im)-10,imagesy($im)-50, "http://www.zabbix.com", $gray);

	$end_time=time(NULL);
	ImageString($im, 0,imagesx($im)-100,imagesy($im)-12,"Generated in ".($end_time-$start_time)." sec", $gray);

	ImagePng($im); 
	ImageDestroy($im); 
?>
