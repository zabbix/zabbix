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
require_once('include/config.inc.php');
require_once('include/triggers.inc.php');

$page['file']	= 'chart4.php';
// $page['title']	= "S_CHART";
$page['type']	= PAGE_TYPE_IMAGE;

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'triggerid'=>		array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,		NULL)
	);

	check_fields($fields);
?>
<?php
	if(!isset($_REQUEST['triggerid'])) fatal_error(S_NO_TRIGGER_DEFINED);

	$options = array(
		'triggerids' => $_REQUEST['triggerid'],
		'output' => API_OUTPUT_EXTEND,
		'nodeids' => get_current_nodeid(true)
	);

	$db_data = CTrigger::get($options);
	if(empty($db_data)) access_deny();
	else $db_data = reset($db_data);

	$start_time = time(NULL);

	$sizeX		= 900;
	$sizeY		= 300;

	$shiftX		= 12;
	$shiftYup	= 17;
	$shiftYdown	= 25+15*3;

	$im = imagecreate($sizeX+$shiftX+61,$sizeY+$shiftYup+$shiftYdown+10);

	$red		= imagecolorallocate($im,255,0,0);
	$darkred	= imagecolorallocate($im,150,0,0);
	$green		= imagecolorallocate($im,0,255,0);
	$darkgreen	= imagecolorallocate($im,0,150,0);
	$bluei		= imagecolorallocate($im,0,0,255);
	$darkblue	= imagecolorallocate($im,0,0,150);
	$yellow		= imagecolorallocate($im,255,255,0);
	$darkyellow	= imagecolorallocate($im,150,150,0);
	$cyan		= imagecolorallocate($im,0,255,255);
	$black		= imagecolorallocate($im,0,0,0);
	$gray		= imagecolorallocate($im,150,150,150);
	$white		= imagecolorallocate($im,255,255,255);
	$bg			= imagecolorallocate($im,6+6*16,7+7*16,8+8*16);

	$x=imagesx($im);
	$y=imagesy($im);

	imagefilledrectangle($im,0,0,$x,$y,$white);
	imagerectangle($im,0,0,$x-1,$y-1,$black);

	$str = expand_trigger_description_by_data($db_data);

	$str = S_CHART4_HEADER_TITLE_PART1.' '.$str.' '.S_CHART4_HEADER_TITLE_PART2.' '.zbx_date2str(S_CHART4_HEADER_DATE_FORMAT).' '.S_CHART4_HEADER_TITLE_PART3;
	$x = imagesx($im)/2-imagefontwidth(4)*zbx_strlen($str)/2;
	//imagestring($im, 4,$x,1, $str , $darkred);
	imageText($im, 10, 0, $x, 14, $darkred, $str);

	$now = time(NULL);

	$count_now=array();
	$true = array();
	$false = array();
	$unknown = array();

	$start=mktime(0,0,0,1,1,date('Y'));

	$wday=date('w',$start);
	if($wday==0) $wday=7;
	$start=$start-($wday-1)*24*3600;

	$weeks = (int)(date('z')/7 +1);

	for($i=0;$i<$weeks;$i++){
		$period_start=$start+7*24*3600*$i;
		$period_end=$start+7*24*3600*($i+1);

		$stat=calculate_availability($_REQUEST['triggerid'],$period_start,$period_end);
		$true[$i]=$stat['true'];
		$false[$i]=$stat['false'];
		$unknown[$i]=$stat['unknown'];
		$count_now[$i]=1;
//SDI($false[$i]);
	}

	for($i=0;$i<=$sizeY;$i+=$sizeY/10){
		DashedLine($im,$shiftX,$i+$shiftYup,$sizeX+$shiftX,$i+$shiftYup,$gray);
	}


	for($i=0, $period_start = $start; $i <= $sizeX; $i += $sizeX/52){
		DashedLine($im,$i+$shiftX,$shiftYup,$i+$shiftX,$sizeY+$shiftYup,$gray);
		//imagestringup($im, 1,$i+$shiftX-4, $sizeY+$shiftYup+32, zbx_date2str(S_CHART4_TIMELINE_DATE_FORMAT,$period_start) , $black);
		imageText($im, 6, 90, $i+$shiftX+4, $sizeY+$shiftYup+30, $black, zbx_date2str(S_CHART4_TIMELINE_DATE_FORMAT,$period_start));

		$period_start += 7*24*3600;
	}

	$maxY = max(max($true), 100);
	$minY = 0;

	$maxX=900;
	$minX=0;

	for($i=1;$i<=$weeks;$i++){
		$x1=(900/52)*$sizeX*($i-1-$minX)/($maxX-$minX);

//		imagefilledrectangle($im,$x1+$shiftX,$shiftYup,$x1+$shiftX+8,$sizeY+$shiftYup,imagecolorallocate($im,0,0,0)); 	// WHITE

		$yt=$sizeY*$true[$i-1]/100;
		if($yt > 0) imagefilledrectangle($im,$x1+$shiftX,$shiftYup,$x1+$shiftX+8,$yt+$shiftYup,imagecolorallocate($im,235,120,120));	// RED

		$yu=(int)($sizeY*$unknown[$i-1]/100+0.5);
		if($yu > 0) imagefilledrectangle($im,$x1+$shiftX,$yt+$shiftYup,$x1+$shiftX+8,$yt+$yu+$shiftYup,imagecolorallocate($im,235,235,235)); 	// UNKNOWN

		$yf=$sizeY*$false[$i-1]/100;
		if($yf > 0) imagefilledrectangle($im,$x1+$shiftX,$yt+$yu+$shiftYup,$x1+$shiftX+8,$sizeY+$shiftYup,imagecolorallocate($im,120,235,120));  // GREEN

//SDI($yt.'+'.$yf.'+'.$yu);
		if($yt+$yf+$yu > 0) imagerectangle($im,$x1+$shiftX,$shiftYup,$x1+$shiftX+8,$sizeY+$shiftYup,$black);
	}

	for($i=0;$i<=$sizeY;$i+=$sizeY/10){
		//imagestring($im, 1, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftYup, $i*($maxY-$minY)/$sizeY+$minY , $darkred);
		imageText($im, 7, 0, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftYup+8, $darkred, $i*($maxY-$minY)/$sizeY+$minY);
	}

	imagefilledrectangle($im,$shiftX,$sizeY+$shiftYup+39+15*0,$shiftX+5,$sizeY+$shiftYup+35+9+15*0,imagecolorallocate($im,120,235,120));
	imagerectangle($im,$shiftX,$sizeY+$shiftYup+39+15*0,$shiftX+5,$sizeY+$shiftYup+35+9+15*0,$black);
	imageText($im, 8, 0, $shiftX+9, $sizeY+$shiftYup+15*0+45, $black, S_OK." (%)");
	//imagestring($im, 2,$shiftX+9,$sizeY+$shiftYup+15*0+35, S_OK." (%)", $black);

	imagefilledrectangle($im,$shiftX,$sizeY+$shiftYup+39+15*1,$shiftX+5,$sizeY+$shiftYup+35+9+15*1,imagecolorallocate($im,235,120,120));
	imagerectangle($im,$shiftX,$sizeY+$shiftYup+39+15*1,$shiftX+5,$sizeY+$shiftYup+15+9+35*1,$black);
	imageText($im, 8, 0, $shiftX+9, $sizeY+$shiftYup+15*1+45, $black, S_PROBLEMS." (%)");
	//imagestring($im, 2,$shiftX+9,$sizeY+$shiftYup+15*1+35, S_PROBLEMS." (%)", $black);

	imagefilledrectangle($im,$shiftX,$sizeY+$shiftYup+39+15*2,$shiftX+5,$sizeY+$shiftYup+35+9+15*2,imagecolorallocate($im,220,220,220));
	imagerectangle($im,$shiftX,$sizeY+$shiftYup+39+15*2,$shiftX+5,$sizeY+$shiftYup+35+9+15*2,$black);
	imageText($im, 8, 0, $shiftX+9, $sizeY+$shiftYup+15*2+45, $black, S_UNKNOWN." (%)");

	imagestringup($im,0,imagesx($im)-10,imagesy($im)-50, 'http://www.zabbix.com', $gray);

	$end_time=time(NULL);
	imagestring($im, 0,imagesx($im)-100,imagesy($im)-12,'Generated in '.($end_time-$start_time).' sec', $gray);

	ImageOut($im);
	imagedestroy($im);
?>
<?php

include_once('include/page_footer.php');

?>
