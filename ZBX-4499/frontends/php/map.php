<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
	require_once('include/maps.inc.php');

	$page['title'] = 'S_MAP';
	$page['file'] = 'map.php';
	$page['type'] = detect_page_type(PAGE_TYPE_IMAGE);

include_once('include/page_header.php');
set_time_limit(10);
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'sysmapid'=>		array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,		NULL),

		'selements'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID,		NULL),
		'links'=>			array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID,		NULL),
		'noselements'=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("0,1"),	NULL),
		'nolinks'=>			array(T_ZBX_INT, O_OPT,	NULL,	IN("0,1"),	NULL),

		'show_triggers'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1,2,3"),	NULL),
		'grid'=>			array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,500),	NULL),
		'border'=>			array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1'),		NULL)
	);

	check_fields($fields);
?>
<?php

	$options = array(
		'sysmapids' => $_REQUEST['sysmapid'],
		'select_selements' => API_OUTPUT_EXTEND,
		'select_links' => API_OUTPUT_EXTEND,
		'output' => API_OUTPUT_EXTEND
	);

	$maps = CMap::get($options);

	if(empty($maps)) access_deny();
	else $map = reset($maps);

	$name		= $map['name'];
	$width		= $map['width'];
	$height		= $map['height'];
	$backgroundid	= $map['backgroundid'];
	$status_view = 0;// $map['status_view'];

	if(function_exists('imagecreatetruecolor')&&@imagecreatetruecolor(1,1)){
		$im = imagecreatetruecolor($width,$height);
	}
	else{
		$im = imagecreate($width,$height);
	}

	$colors['Red']		= imagecolorallocate($im,255,0,0);
	$colors['Dark Red']	= imagecolorallocate($im,150,0,0);
	$colors['Green']	= imagecolorallocate($im,0,255,0);
	$colors['Dark Green']	= imagecolorallocate($im,0,150,0);
	$colors['Blue']		= imagecolorallocate($im,0,0,255);
	$colors['Dark Blue']	= imagecolorallocate($im,0,0,150);
	$colors['Yellow']	= imagecolorallocate($im,255,255,0);
	$colors['Dark Yellow']	= imagecolorallocate($im,150,150,0);
	$colors['Cyan']		= imagecolorallocate($im,0,255,255);
	$colors['Black']	= imagecolorallocate($im,0,0,0);
	$colors['Gray']		= imagecolorallocate($im,150,150,150);
	$colors['White']	= imagecolorallocate($im,255,255,255);
	$colors['Orange']	= imagecolorallocate($im,238,96,0);


	$x=imagesx($im);
	$y=imagesy($im);

	imagefilledrectangle($im,0,0,$width,$height,$colors['White']);

	if(($db_image = get_image_by_imageid($backgroundid))){
		$back = imagecreatefromstring($db_image['image']);
		imagecopy($im,$back,0,0,0,0,imagesx($back),imagesy($back));
	}
	unset($db_image);
	
	$x=imagesx($im)/2-ImageFontWidth(4)*zbx_strlen($name)/2;
	imagetext($im, 10, 0, $x, 25, $colors['Dark Red'], $name);
	

	$str = zbx_date2str(S_MAPS_DATE_FORMAT,time(NULL));
	imagestring($im, 0,imagesx($im)-120,imagesy($im)-12,$str, $colors['Gray']);

	if(isset($_REQUEST['grid'])){
		$grid = get_request('grid', 50);
		if(!is_numeric($grid)) $grid = 50;

		$dims = imageTextSize(8, 0, '11');
		for($x=$grid; $x<$width; $x+=$grid){
			MyDrawLine($im,$x,0,$x,$height,$colors['Black'], MAP_LINK_DRAWTYPE_DASHED_LINE);
			imageText($im, 8, 0, $x+3, $dims['height']+3, $colors['Black'],$x);
		}
		for($y=$grid;$y<$height;$y+=$grid){
			MyDrawLine($im,0,$y,$width,$y,$colors['Black'], MAP_LINK_DRAWTYPE_DASHED_LINE);
			imageText($im, 8, 0, 3, $y+$dims['height']+3, $colors['Black'], $y);
		}

		imageText($im, 8, 0, 2, $dims['height']+3, $colors['Black'], 'Y X:');
	}
// ACTION /////////////////////////////////////////////////////////////////////////////

	$json = new CJSON();

	if(isset($_REQUEST['selements']) || isset($_REQUEST['noselements'])){
		$map['selements'] = get_request('selements', '[]');
		$map['selements'] = $json->decode($map['selements'], true);
	}
	else{
		$map['selements'] = zbx_toHash($map['selements'], 'selementid');
	}

	if(isset($_REQUEST['links']) || isset($_REQUEST['nolinks'])){
		$map['links'] = get_request('links', '[]');
		$map['links'] = $json->decode($map['links'], true);
	}
	else{
		$map['links'] = zbx_toHash($map['links'],'linkid');
	}

//SDI($selements);

	$map_info = getSelementsInfo($map);

// Draw MAP
	drawMapConnectors($im, $map, $map_info);

	if(!isset($_REQUEST['noselements'])){
		drawMapHighligts($im, $map, $map_info);
		drawMapSelements($im, $map, $map_info);
	}

	drawMapLabels($im, $map, $map_info);
	drawMapLinkLabels($im, $map, $map_info);

	if(!isset($_REQUEST['noselements']) && ($map['markelements'] == 1)){
		drawMapSelemetsMarks($im, $map, $map_info);
	}
//--

	imagestringup($im,0,imagesx($im)-10,imagesy($im)-50, S_ZABBIX_URL, $colors['Gray']);

	if(!isset($_REQUEST['border'])){
		imagerectangle($im,0,0,$width-1,$height-1,$colors['Black']);
	}

	show_messages();

	imageOut($im);
	imagedestroy($im);

?>
<?php

include_once('include/page_footer.php');

?>
