<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/maps.inc.php';

$page['title'] = _('Map');
$page['file'] = 'map.php';
$page['type'] = detect_page_type(PAGE_TYPE_IMAGE);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION		EXCEPTION
$fields = array(
	'sysmapid' =>		array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,			null),
	'selements' =>		array(T_ZBX_STR, O_OPT, P_SYS,	DB_ID,			null),
	'links' =>			array(T_ZBX_STR, O_OPT, P_SYS,	DB_ID,			null),
	'noselements' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1'),		null),
	'nolinks' =>		array(T_ZBX_INT, O_OPT, null,	IN('0,1'),		null),
	'nocalculations' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1'),		null),
	'expand_macros' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1'),		null),
	'show_triggers' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1,2,3'),	null),
	'grid' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 500), null),
	'base64image' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1'),		null)
);
check_fields($fields);

$maps = API::Map()->get(array(
	'sysmapids' => $_REQUEST['sysmapid'],
	'selectSelements' => API_OUTPUT_EXTEND,
	'selectLinks' => API_OUTPUT_EXTEND,
	'output' => API_OUTPUT_EXTEND,
	'preservekeys' => true
));
$map = reset($maps);
if (empty($map)) {
	access_deny();
}

$mapPainter = new CMapPainter($map, array(
	'map' => array(
		'drawAreas' => (!isset($_REQUEST['selements']) && !isset($_REQUEST['noselements']))
	),
	'grid' => array(
		'size' => get_request('grid', 0)
	)
));

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

/*
 * Actions
 */
$json = new CJSON();

if (isset($_REQUEST['selements']) || isset($_REQUEST['noselements'])) {
	$map['selements'] = get_request('selements', '[]');
	$map['selements'] = $json->decode($map['selements'], true);
}
else {
	add_elementNames($map['selements']);
}

if (isset($_REQUEST['links']) || isset($_REQUEST['nolinks'])) {
	$map['links'] = get_request('links', '[]');
	$map['links'] = $json->decode($map['links'], true);
}

$nocalculations = get_request('nocalculations', false);
if ($nocalculations) {
	foreach ($map['selements'] as $selement) {
		if ($selement['elementtype'] != SYSMAP_ELEMENT_TYPE_IMAGE) {
			add_elementNames($map['selements']);
			break;
		}
	}

	// get default iconmap id to use for elements that use icon map
	if ($map['iconmapid']) {
		$iconMaps = API::IconMap()->get(array(
			'iconmapids' => $map['iconmapid'],
			'output' => array('default_iconid'),
			'preservekeys' => true
		));
		$iconMap = reset($iconMaps);
		$defaultAutoIconId = $iconMap['default_iconid'];
	}

	$map_info = array();
	foreach ($map['selements'] as $selement) {
		// if element use icon map and icon map is set for map, and is host like element, we use default icon map icon
		if ($map['iconmapid'] && $selement['use_iconmap']
				&& ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST
					|| ($selement['elementtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP
						&& $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS))) {
			$iconid = $defaultAutoIconId;
		}
		else {
			$iconid = $selement['iconid_off'];
		}

		$map_info[$selement['selementid']] = array(
			'iconid' => $iconid,
			'icon_type' => SYSMAP_ELEMENT_ICON_OFF
		);

		$map_info[$selement['selementid']]['name'] = ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_IMAGE)
			? _('Image')
			: $selement['elementName'];
	}

	$allLinks = true;
}
else {
	// we need selements to be a hash for further processing
	$map['selements'] = zbx_toHash($map['selements'], 'selementid');

	add_triggerExpressions($map['selements']);

	$areas = populateFromMapAreas($map);
	$map_info = getSelementsInfo($map);
	processAreasCoordinates($map, $areas, $map_info);
	$allLinks = false;
}

/*
 * Draw map
 */
drawMapConnectors($im, $map, $map_info, $allLinks);

if (!isset($_REQUEST['noselements'])) {
	drawMapHighligts($im, $map, $map_info);
	drawMapSelements($im, $map, $map_info);
}

$expand_macros = get_request('expand_macros', true);
drawMapLabels($im, $map, $map_info, $expand_macros);
drawMapLinkLabels($im, $map, $map_info, $expand_macros);

if (!isset($_REQUEST['noselements']) && $map['markelements'] == 1) {
	drawMapSelementsMarks($im, $map, $map_info);
}

show_messages();

if (get_request('base64image')) {
	ob_start();
	imagepng($im);
	$imageSource = ob_get_contents();
	ob_end_clean();
	$json = new CJSON();
	echo $json->encode(array('result' => base64_encode($imageSource)));
	imagedestroy($im);
}
else {
	imageOut($im);
}

require_once dirname(__FILE__).'/include/page_footer.php';
