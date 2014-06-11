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
	require_once 'include/config.inc.php';
	require_once 'include/triggers.inc.php';
	require_once 'include/services.inc.php';

	$page['file']	= 'chart_sla.php';
	// $page['title']	= "S_CHART";
	$page['type']	= PAGE_TYPE_IMAGE;

include_once 'include/page_header.php';

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'serviceid'=>		array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,		null)
	);

	check_fields($fields);
?>
<?php
	$sql = 'SELECT s.* FROM services s  WHERE s.serviceid='.$_REQUEST['serviceid'];
	if(!$service = DBfetch(DBselect($sql,1))){
		fatal_error(S_NO_IT_SERVICE_DEFINED);
	}

	if(!is_null($service['triggerid'])){
		$options = array(
			'triggerids' => $service['triggerid'],
			'output' => API_OUTPUT_SHORTEN,
			'nodeids' => get_current_nodeid(true)
		);

		$db_data = CTrigger::get($options);
		if(empty($db_data)) access_deny();
	}
?>
<?php
	$sizeX=200;
	$sizeY=15;

	$im = imagecreate($sizeX,$sizeY);

	$red		= imagecolorallocate($im,255,0,0);
	$darkred	= imagecolorallocate($im,150,0,0);
	$green		= imagecolorallocate($im,0,255,0);
	$darkgreen	= imagecolorallocate($im,0,150,0);
	$blue		= imagecolorallocate($im,0,0,255);
	$yellow		= imagecolorallocate($im,255,255,0);
	$cyan		= imagecolorallocate($im,0,255,255);
	$black		= imagecolorallocate($im,0,0,0);
	$gray		= imagecolorallocate($im,150,150,150);
	$white		= imagecolorallocate($im,255,255,255);

	imagefilledrectangle($im,0,0,$sizeX,$sizeY,imagecolorallocate($im,120,200,120));

	$now=time(NULL);
	$period_start=$now-7*86400;
	$period_end=$now;
	$stat = calculateServiceAvailability($_REQUEST['serviceid'], $period_start, $period_end);

	$problem=$stat['problem'];
	$ok=$stat['ok'];

// Percentage to show
	$percentage = 20;

	$p=min($problem,$percentage);
	$g=max($service['goodsla']-(100 - $percentage),0);

	imagefilledrectangle($im,$sizeX-$sizeX*$p/$percentage,1,$sizeX-2,$sizeY-2,imagecolorallocate($im,200,120,120));
	imageline($im,$sizeX*$g/$percentage,1,$sizeX*$g/$percentage,$sizeY-1,$yellow);

	imagerectangle($im,0,0,$sizeX-1,$sizeY-1,$black);

	$s=sprintf('%2.2f%%',$ok);
	imagestring($im, 2,1,1, $s , $white);
	$s=sprintf('%2.2f%%', $problem);
	imagestring($im, 2,$sizeX-45,1, $s , $white);
	ImageOut($im);
	imagedestroy($im);

include_once('include/page_footer.php');

?>
