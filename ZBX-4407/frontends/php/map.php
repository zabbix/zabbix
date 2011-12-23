<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once('include/config.inc.php');
require_once('include/maps.inc.php');

$page['title'] = 'S_MAP';
$page['file'] = 'map.php';
$page['type'] = detect_page_type(PAGE_TYPE_IMAGE);

require_once('include/page_header.php');

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields=array(
	'sysmapid'=>		array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,		NULL),

	'selements'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID,		NULL),
	'links'=>			array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID,		NULL),
	'noselements'=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("0,1"),	NULL),
	'nolinks'=>			array(T_ZBX_INT, O_OPT,	NULL,	IN("0,1"),	NULL),
	'nocalculations'=>	array(T_ZBX_INT, O_OPT,	NULL,	IN("0,1"),	NULL),

	'show_triggers'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1,2,3"),	NULL),
	'grid'=>			array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,500),	NULL),
	'base64image'=>		array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1'),		NULL),
);

check_fields($fields);
?>
<?php

$options = array(
	'sysmapids' => $_REQUEST['sysmapid'],
	'selectSelements' => API_OUTPUT_EXTEND,
	'selectLinks' => API_OUTPUT_EXTEND,
	'output' => API_OUTPUT_EXTEND,
	'preservekeys' => true,
);
$maps = API::Map()->get($options);
$map = reset($maps);

if(!$map){
	access_deny();
}

$mapOptions = array(
	'map' => array(
		'drawAreas' => (!isset($_REQUEST['selements']) && !isset($_REQUEST['noselements'])),
	),
	'grid' => array(
		'size' => get_request('grid', 0),
	),
);
$mapPainter = new CMapPainter($map, $mapOptions);

$im = $mapPainter->paint();


$colors['Red'] = imagecolorallocate($im, 255, 0, 0);
$colors['Dark Red'] = imagecolorallocate($im, 150, 0, 0);
$colors['Green'] = imagecolorallocate($im, 0, 255, 0);
$colors['Dark Green'] = imagecolorallocate($im, 0, 150, 0);
$colors['Blue'] = imagecolorallocate($im, 0, 0, 255);
$colors['Dark Blue'] = imagecolorallocate($im, 0, 0, 150);
$colors['Yellow'] = imagecolorallocate($im, 255, 255, 0);
$colors['Dark Yellow'] = imagecolorallocate($im, 150, 150, 0);
$colors['Cyan'] = imagecolorallocate($im, 0, 255, 255);
$colors['Black'] = imagecolorallocate($im, 0, 0, 0);
$colors['Gray'] = imagecolorallocate($im, 150, 150, 150);
$colors['White'] = imagecolorallocate($im, 255, 255, 255);
$colors['Orange'] = imagecolorallocate($im, 238, 96, 0);



$x = imagesx($im);
$y = imagesy($im);

// ACTION /////////////////////////////////////////////////////////////////////////////

$json = new CJSON();

if(isset($_REQUEST['selements']) || isset($_REQUEST['noselements'])){
	$map['selements'] = get_request('selements', '[]');
	$map['selements'] = $json->decode($map['selements'], true);
}

if(isset($_REQUEST['links']) || isset($_REQUEST['nolinks'])){
	$map['links'] = get_request('links', '[]');
	$map['links'] = $json->decode($map['links'], true);
}


$nocalculations = get_request('nocalculations', false);
if($nocalculations){
	// get default iconmap id to use for elements that use icon map
	if ($map['iconmapid']) {
		$iconMaps = API::IconMap()->get(array(
				'iconmapids' => $map['iconmapid'],
				'output' => array('default_iconid'),
				'preservekeys' => true,
			));
		$iconMap = reset($iconMaps);
		$defaultAutoIconId = $iconMap['default_iconid'];
	}

	$map_info = array();

	foreach($map['selements'] as $selement){
		// if element use icon map and icon map is set for map, and is host like element, we use default icon map icon
		if ($map['iconmapid'] && $selement['use_iconmap'] &&
			(
				($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) ||
				($selement['elementtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP && $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS)
			)
		) {
			$iconid = $defaultAutoIconId;
		}
		else {
			$iconid = $selement['iconid_off'];
		}

		$map_info[$selement['selementid']] = array(
			'iconid' => $iconid,
			'icon_type' => SYSMAP_ELEMENT_ICON_OFF,
		);
		if($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_IMAGE){
			$map_info[$selement['selementid']]['name'] = _('Image');
		}
		else{
			$map_info[$selement['selementid']]['name'] = $selement['elementName'];
		}
	}
	$allLinks = true;
}
else{
	$areas = populateFromMapAreas($map);
	$map_info = getSelementsInfo($map);
	processAreasCoordinates($map, $areas, $map_info);
	$allLinks = false;
}

// Draw MAP
drawMapConnectors($im, $map, $map_info, $allLinks);

if(!isset($_REQUEST['noselements'])){
	drawMapHighligts($im, $map, $map_info);
	drawMapSelements($im, $map, $map_info);
}

drawMapLabels($im, $map, $map_info, !$nocalculations);
drawMapLinkLabels($im, $map, $map_info, !$nocalculations);

if(!isset($_REQUEST['noselements']) && ($map['markelements'] == 1)){
	drawMapSelementsMarks($im, $map, $map_info);
}
//--

show_messages();

if(get_request('base64image')){
	ob_start();
	imagepng($im);
	$imageSource = ob_get_contents();
	ob_end_clean();
	$json = new CJSON();
	echo $json->encode(array('result' => base64_encode($imageSource)));
	imagedestroy($im);
}
else{
	imageOut($im);
}

require_once('include/page_footer.php');

?>
