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
#	trendavg

	$start_time=time(NULL);

	if(!isset($HTTP_GET_VARS["type"]))
	{
		$HTTP_GET_VARS["type"]="15min";
	}

	if($HTTP_GET_VARS["type"] == "15min")
	{
		$period=900;
		$label_format="H:i";
	}
	else if($HTTP_GET_VARS["type"] == "30min")
	{
		$period=1800;
		$label_format="H:i";
	}
	else if($HTTP_GET_VARS["type"] == "4hours")
	{
		$period=4*3600;
		$label_format="H:i";
	}
	else if($HTTP_GET_VARS["type"] == "12hours")
	{
		$period=12*3600;
		$label_format="H:i";
	}
	else
	{
		$period=3600;
		$label_format="H:i";
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

	$shiftX=10;
	$shiftY=15;

	$nodata=1;	


//	Header( "Content-type:  text/html"); 
	Header( "Content-type:  image/png"); 
	Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

	check_authorisation();

	$im = imagecreate($sizeX+$shiftX+61,$sizeY+2*$shiftY+10); 
  
	$red=ImageColorAllocate($im,255,0,0); 
	$darkred=ImageColorAllocate($im,150,0,0); 
	$green=ImageColorAllocate($im,0,255,0); 
	$darkgreen=ImageColorAllocate($im,0,150,0); 
	$blue=ImageColorAllocate($im,0,0,255); 
	$yellow=ImageColorAllocate($im,255,255,0); 
	$darkyellow=ImageColorAllocate($im,150,150,0); 
	$cyan=ImageColorAllocate($im,0,255,255); 
	$black=ImageColorAllocate($im,0,0,0); 
	$gray=ImageColorAllocate($im,150,150,150); 
	$white=ImageColorAllocate($im,255,255,255); 

	$x=imagesx($im); 
	$y=imagesy($im);
  
	ImageFilledRectangle($im,0,0,$sizeX+$shiftX+61,$sizeY+2*$shiftY+10,$white);
	ImageRectangle($im,0,0,$x-1,$y-1,$black);

	if(!check_right("Item","R",$HTTP_GET_VARS["itemid"]))
	{
		ImagePng($im); 
		ImageDestroy($im); 
		exit;
	}

	$now = time(NULL);
	$to_time=$now-$now%$period;
	$from_time=$to_time-17*$period;

	$count=array();
	$min=array();
	$max=array();
	$avg=array();
#if($DB_TYPE!="MYSQL")
if(0)
{
//	$sql="select round(900*((clock+3*3600)%(24*3600))/(24*3600)) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max from history where itemid=$itemid and clock>$from_time and clock<$to_time group by round(900*((clock+3*3600)%(24*3600))/(24*3600))";
	$p=$to_time-$from_time;
	$z=$from_time%$p;
	$sql="select round(900*((clock+$z)%($p))/($p)) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max from history where itemid=$itemid and clock>$from_time and clock<$to_time group by round(900*((clock+$z)%($p))/($p))";
	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		$i=$row["i"];
		$count[$i]=$row["count"];
		$min[$i]=$row["min"];
		$max[$i]=$row["max"];
		$avg[$i]=$row["avg"];
		$nodata=0;
	}
}
else
{
	for($i=0;$i<900;$i++)
	{
		$result=DBselect("select count(value),min(value),max(value),avg(value) from history where itemid=".$HTTP_GET_VARS["itemid"]." and clock>$from_time+$i*($to_time-$from_time)/(900-50) and clock<$from_time+($i+1)*($to_time-$from_time)/(900-50)");
		$count[$i]=DBget_field($result,0,0);
		if($count[$i]>0)
		{
			$min[$i]=DBget_field($result,0,1);
			$max[$i]=DBget_field($result,0,2);
			$avg[$i]=DBget_field($result,0,3);
			$nodata=0;
		}
	}
}


	for($i=0;$i<=$sizeY;$i+=$sizeY/5)
	{
		DashedLine($im,$shiftX,$i+$shiftY,$sizeX+$shiftX,$i+$shiftY,$gray);
	}

	for($i=0;$i<=$sizeX;$i+=$sizeX/24)
	{
		DashedLine($im,$i+$shiftX,$shiftY,$i+$shiftX,$sizeY+$shiftY,$gray);
		if($nodata == 0)
		{
			ImageString($im, 1,$i+$shiftX-11, $sizeY+$shiftY+5, date($label_format,$from_time+$period*($i/50)) , $black);
		}
	}

	unset($maxY);
	unset($minY);

/*
	for($i=0;$i<900;$i++)
	{
		$nodata=0;
		if(!isset($maxY) || $max[$i]>$maxY)
		{
			$maxY=$max[$i];
		}
		if(!isset($minY) || (($min[$i]<$minY)&&($count[$i]>0)) )
		{
			$minY=$min[$i];
		}
	}
*/

	if($nodata == 0)
	{
		if(isset($HTTP_GET_VARS["trendavg"]))
		{
			$maxY=max($avg);
			$minY=min($avg);
		}
		else
		{
			$maxY=max($max);
			$minY=min($min);
		}
	}

	$maxX=900;
	$minX=0;
#	echo "MIN/MAX:",$minX," - ",$maxX," - ",$minY," - ",$maxY,"<Br>";

	if(isset($minY)&&($maxY)&&($minX!=$maxX)&&($minY!=$maxY))
	{
		for($i=0;$i<900;$i++)
		{
			if($count[$i]>0)
			{
				if(!isset($HTTP_GET_VARS["trendavg"]))
				{
					$x1=$sizeX*($i-$minX)/($maxX-$minX);
					$y1=$sizeY*($max[$i]-$minY)/($maxY-$minY);
					$x2=$x1;
					$y2=0;
					$y1=$sizeY-$y1;
					$y2=$sizeY-$y2;

					ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$darkred);
				}

				$x1=$sizeX*($i-$minX)/($maxX-$minX);
				$y1=$sizeY*($avg[$i]-$minY)/($maxY-$minY);
				$x2=$x1;
				$y2=0;
				$y1=$sizeY-$y1;
				$y2=$sizeY-$y2;
	
				ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$darkyellow);

				if(!isset($HTTP_GET_VARS["trendavg"]))
				{
					$x1=$sizeX*($i-$minX)/($maxX-$minX);
					$y1=$sizeY*($min[$i]-$minY)/($maxY-$minY);
					$x2=$x1;
					$y2=0;
					$y1=$sizeY-$y1;
					$y2=$sizeY-$y2;
	
					ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$darkgreen);
				}
			}


#			ImageStringUp($im, 1, $x1+10, $sizeY+$shiftY+15, $i , $red);
		}
	}
	else
	{
//		ImageLine($im,$shiftX,$shiftY+$sizeY/2,$sizeX+$shiftX,$shiftY+$sizeY/2,$green);
	}

	if($nodata == 0)
	{
		$item=get_item_by_itemid($HTTP_GET_VARS["itemid"]);
		for($i=0;$i<=$sizeY;$i+=$sizeY/5)
		{
			ImageString($im, 1, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftY, convert_units($i*($maxY-$minY)/$sizeY+$minY,$item["units"],$item["multiplier"]) , $darkred);
		}

//		date("dS of F Y h:i:s A",DBget_field($result,0,0));

//		ImageString($im, 1,10,                $sizeY+$shiftY+5, date("dS of F Y h:i:s A",$minX) , $red);
//		ImageString($im, 1,$sizeX+$shiftX-168,$sizeY+$shiftY+5, date("dS of F Y h:i:s A",$maxX) , $red);
	}
	else
	{
		ImageString($im, 2,$sizeX/2 -50,                $sizeY+$shiftY+3, "NO DATA FOR THIS PERIOD" , $darkred);
	}

	ImageString($im, 1,$shiftX, $sizeY+$shiftY+15, "MIN" , $darkgreen);
	ImageString($im, 1,$shiftX+20, $sizeY+$shiftY+15, "AVG" , $darkyellow);
	ImageString($im, 1,$shiftX+40, $sizeY+$shiftY+15, "MAX" , $darkred);

	ImageStringUp($im,0,imagesx($im)-10,imagesy($im)-50, "http://www.zabbix.com", $gray);

	$end_time=time(NULL);
	ImageString($im, 0,imagesx($im)-100,imagesy($im)-12,"Generated in ".($end_time-$start_time)." sec", $gray);

	ImagePng($im); 
	ImageDestroy($im); 
?>
