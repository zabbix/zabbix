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
	require_once "include/services.inc.php";

	$page["file"]	= "chart_sla.php";
	$page["title"]	= "S_CHART";
	$page["type"]	= PAGE_TYPE_IMAGE;

include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"serviceid"=>		array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,		null)
	);

	check_fields($fields);
?>
<?php
	if(! (DBfetch(DBselect('select serviceid from services where serviceid='.$_REQUEST["serviceid"]))) )
	{
		fatal_error(S_NO_IT_SERVICE_DEFINED);
	}

	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT);
	
	if( !($service = DBfetch(DBselect("select s.* from services s left join triggers t on s.triggerid=t.triggerid ".
		" left join functions f on t.triggerid=f.triggerid left join items i on f.itemid=i.itemid ".
		" where (i.hostid is NULL or i.hostid not in (".$denyed_hosts.")) ".
		/* " and ".DBid2nodeid("s.serviceid")."=".$ZBX_CURNODEID. */ /* NOTE: allow displaying all accessiables services */
		" and s.serviceid=".$_REQUEST["serviceid"]
		))))
	{
		access_deny();
	}
?>
<?php
	$sizeX=200;
	$sizeY=15;

	$im = imagecreate($sizeX,$sizeY); 
  
	$red		= ImageColorAllocate($im,255,0,0); 
	$darkred	= ImageColorAllocate($im,150,0,0); 
	$green		= ImageColorAllocate($im,0,255,0); 
	$darkgreen	= ImageColorAllocate($im,0,150,0); 
	$blue		= ImageColorAllocate($im,0,0,255); 
	$yellow		= ImageColorAllocate($im,255,255,0); 
	$cyan		= ImageColorAllocate($im,0,255,255); 
	$black		= ImageColorAllocate($im,0,0,0); 
	$gray		= ImageColorAllocate($im,150,150,150); 
	$white		= ImageColorAllocate($im,255,255,255); 

	ImageFilledRectangle($im,0,0,$sizeX,$sizeY,ImageColorAllocate($im,120,200,120));

	$now=time(NULL);
	$period_start=$now-7*24*3600;
	$period_end=$now;
	$stat=calculate_service_availability($_REQUEST["serviceid"],$period_start,$period_end);
		
	$problem=$stat["problem"];
	$ok=$stat["ok"];

	$p=min($problem,20);
	$g=max($service["goodsla"]-80,0);

	ImageFilledRectangle($im,$sizeX-$sizeX*$p/20,1,$sizeX-2,$sizeY-2,ImageColorAllocate($im,200,120,120));
	ImageLine($im,$sizeX*$g/20,1,$sizeX*$g/20,$sizeY-1,$yellow);

	ImageRectangle($im,0,0,$sizeX-1,$sizeY-1,$black);

	$s=sprintf("%2.2f%%",$ok);
	ImageString($im, 2,1,1, $s , $white);
	$s=sprintf("%2.2f%%", $problem);
	ImageString($im, 2,$sizeX-45,1, $s , $white);
	ImageOut($im); 
	ImageDestroy($im); 
?>
<?php

include_once "include/page_footer.php";

?>
