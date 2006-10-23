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
	require_once "include/triggers.inc.php";

	$page["file"]	= "chart4.php";
	$page["title"]	= "S_CHART";
	$page["type"]	= PAGE_TYPE_IMAGE;

include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"triggerid"=>		array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,		NULL)
	);

	check_fields($fields);
?>
<?php
	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, PERM_MODE_LT);

	if(! ($db_data = DBfetch(DBselect('select distinct  t.triggerid,t.description,h.host,h.hostid '.
			' from hosts h, items i, functions f, triggers t'.
			' where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid='.$_REQUEST["triggerid"].
			' and i.hostid not in ('.$denyed_hosts.') '
			))))
	{
		access_deny();
	}

	$start_time = time(NULL);

	$sizeX		= 900;
	$sizeY		= 300;

	$shiftX		= 12;
	$shiftYup	= 17;
	$shiftYdown	= 25+15*3;

	$im = imagecreate($sizeX+$shiftX+61,$sizeY+$shiftYup+$shiftYdown+10); 
	
	$red		= ImageColorAllocate($im,255,0,0); 
	$darkred	= ImageColorAllocate($im,150,0,0); 
	$green		= ImageColorAllocate($im,0,255,0); 
	$darkgreen	= ImageColorAllocate($im,0,150,0); 
	$bluei		= ImageColorAllocate($im,0,0,255); 
	$darkblue	= ImageColorAllocate($im,0,0,150); 
	$yellow		= ImageColorAllocate($im,255,255,0); 
	$darkyellow	= ImageColorAllocate($im,150,150,0); 
	$cyan		= ImageColorAllocate($im,0,255,255); 
	$black		= ImageColorAllocate($im,0,0,0); 
	$gray		= ImageColorAllocate($im,150,150,150); 
	$white		= ImageColorAllocate($im,255,255,255); 
	$bg		= ImageColorAllocate($im,6+6*16,7+7*16,8+8*16);

	$x=imagesx($im); 
	$y=imagesy($im);
  
	ImageFilledRectangle($im,0,0,$x,$y,$white);
	ImageRectangle($im,0,0,$x-1,$y-1,$black);

	$str = expand_trigger_description_by_data($db_data);

	$str = $str." (year ".date("Y").")";
	$x = imagesx($im)/2-ImageFontWidth(4)*strlen($str)/2;
	ImageString($im, 4,$x,1, $str , $darkred);

	$now = time(NULL);

	$count_now=array();
	$true=array();

	$year=date("Y");
	$start=mktime(0,0,0,1,1,$year);

	$wday=date("w",$start);
	if($wday==0) $wday=7;
	$start=$start-($wday-1)*24*3600;

	for($i=0;$i<52;$i++)
	{
		$period_start=$start+7*24*3600*$i;
		$period_end=$start+7*24*3600*($i+1);
		$stat=calculate_availability($_REQUEST["triggerid"],$period_start,$period_end);
		
		$true[$i]=$stat["true"];
		$false[$i]=$stat["false"];
		$unknown[$i]=$stat["unknown"];
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
	$tmp=max($true);
	if($tmp>$maxY)
	{
		$maxY=$tmp;
	}
	$minY=0;

	$maxX=900;
	$minX=0;

	for($i=0;$i<52;$i++)
	{

		$x1=(900/52)*$sizeX*($i-$minX)/($maxX-$minX);

		ImageFilledRectangle($im,$x1+$shiftX,$shiftYup,$x1+$shiftX+8,$sizeY+$shiftYup,ImageColorAllocate($im,200,200,120))
;
		$y1=$sizeY*$true[$i]/100;

		ImageFilledRectangle($im,$x1+$shiftX,$shiftYup,$x1+$shiftX+8,$y1+$shiftYup,ImageColorAllocate($im,200,120,120));

		$y1=$sizeY*$false[$i]/100;
		$y1=$sizeY-$y1;

		ImageFilledRectangle($im,$x1+$shiftX,$y1+$shiftYup,$x1+$shiftX+8,$sizeY+$shiftYup,ImageColorAllocate($im,120,200,120));

		ImageRectangle($im,$x1+$shiftX,$shiftYup,$x1+$shiftX+8,$sizeY+$shiftYup,$black);
	}

	for($i=0;$i<=$sizeY;$i+=$sizeY/10)
	{
		ImageString($im, 1, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftYup, $i*($maxY-$minY)/$sizeY+$minY , $darkred);
	}

	ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*0,$shiftX+5,$sizeY+$shiftYup+35+9+15*0,ImageColorAllocate($im,120,200,120));
	ImageRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*0,$shiftX+5,$sizeY+$shiftYup+35+9+15*0,$black);
	ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*0+35, "FALSE (%)", $black);

	ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*1,$shiftX+5,$sizeY+$shiftYup+35+9+15*1,ImageColorAllocate($im,200,120,120));
	ImageRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*1,$shiftX+5,$sizeY+$shiftYup+15+9+35*1,$black);
	ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*1+35, "TRUE (%)", $black);

	ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*2,$shiftX+5,$sizeY+$shiftYup+35+9+15*2,ImageColorAllocate($im,200,200,120));
	ImageRectangle($im,$shiftX,$sizeY+$shiftYup+39+15*2,$shiftX+5,$sizeY+$shiftYup+35+9+15*2,$black);
	ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*2+35, "UNKNOWN (%)", $black);

	ImageStringUp($im,0,imagesx($im)-10,imagesy($im)-50, "http://www.zabbix.com", $gray);

	$end_time=time(NULL);
	ImageString($im, 0,imagesx($im)-100,imagesy($im)-12,"Generated in ".($end_time-$start_time)." sec", $gray);

	ImageOut($im); 
	ImageDestroy($im); 
?>
<?php

include_once "include/page_footer.php";

?>
