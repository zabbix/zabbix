<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
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
$fields = [
	'sysmapid' =>		[T_ZBX_INT, O_MAND, P_SYS,	DB_ID,				null],
	'selements' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'links' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'noselements' =>	[T_ZBX_INT, O_OPT, null,	IN('0,1'),			null],
	'nolinks' =>		[T_ZBX_INT, O_OPT, null,	IN('0,1'),			null],
	'nocalculations' =>	[T_ZBX_INT, O_OPT, null,	IN('0,1'),			null],
	'expand_macros' =>	[T_ZBX_INT, O_OPT, null,	IN('0,1'),			null],
	'show_triggers' =>	[T_ZBX_INT, O_OPT, P_SYS,	IN('0,1,2,3'),		null],
	'severity_min' =>	[T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4,5'),	null],
	'grid' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 500),	null],
	'base64image' =>	[T_ZBX_INT, O_OPT, null,	IN('0,1'),			null]
];
check_fields($fields);

$maps = API::Map()->get([
	'sysmapids' => $_REQUEST['sysmapid'],
	'selectSelements' => API_OUTPUT_EXTEND,
	'selectLinks' => API_OUTPUT_EXTEND,
	'output' => API_OUTPUT_EXTEND,
	'preservekeys' => true
]);
$map = reset($maps);
if (empty($map)) {
	access_deny();
}

$graphtheme = [
	'theme' => 'blue-theme',
	'textcolor' => '1F2C33',
	'highlightcolor' => 'E33734',
	'backgroundcolor' => 'FFFFFF',
	'graphcolor' => 'FFFFFF',
	'gridcolor' => 'CCD5D9',
	'maingridcolor' => 'ACBBC2',
	'gridbordercolor' => 'ACBBC2',
	'nonworktimecolor' => 'EBEBEB',
	'leftpercentilecolor' => '429E47',
	'rightpercentilecolor' => 'E33734'
];

$themes = DB::find('graph_theme', [
	'theme' => getUserTheme(CWebUser::$data)
]);
if ($themes) {
	$graphtheme = $themes[0];
}

$mapPainter = new CMapPainter($map, [
	'map' => [
		'drawAreas' => (!isset($_REQUEST['selements']) && !isset($_REQUEST['noselements']))
	],
	'grid' => [
		'size' => getRequest('grid', 0)
	],
	'graphtheme' => $graphtheme
]);

$im = $mapPainter->paint();

$x = imagesx($im);
$y = imagesy($im);

/*
 * Actions
 */
$json = new CJson();

if (isset($_REQUEST['selements']) || isset($_REQUEST['noselements'])) {
	$map['selements'] = getRequest('selements', '[]');
	$map['selements'] = $json->decode($map['selements'], true);
}
else {
	add_elementNames($map['selements']);
}

if (isset($_REQUEST['links']) || isset($_REQUEST['nolinks'])) {
	$map['links'] = getRequest('links', '[]');
	$map['links'] = $json->decode($map['links'], true);
}

if (getRequest('nocalculations', false)) {
	foreach ($map['selements'] as $selement) {
		if ($selement['elementtype'] != SYSMAP_ELEMENT_TYPE_IMAGE) {
			add_elementNames($map['selements']);
			break;
		}
	}

	// get default iconmap id to use for elements that use icon map
	if ($map['iconmapid']) {
		$iconMaps = API::IconMap()->get([
			'iconmapids' => $map['iconmapid'],
			'output' => ['default_iconid'],
			'preservekeys' => true
		]);
		$iconMap = reset($iconMaps);

		$defaultAutoIconId = $iconMap['default_iconid'];
	}

	$mapInfo = [];
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

		$mapInfo[$selement['selementid']] = [
			'iconid' => $iconid,
			'icon_type' => SYSMAP_ELEMENT_ICON_OFF
		];

		$mapInfo[$selement['selementid']]['name'] = ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_IMAGE)
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
	$mapInfo = getSelementsInfo($map, ['severity_min' => getRequest('severity_min')]);
	processAreasCoordinates($map, $areas, $mapInfo);
	$allLinks = false;
}

/*
 * Draw map
 */
drawMapConnectors($im, $map, $mapInfo, $allLinks);

if (!isset($_REQUEST['noselements'])) {
	drawMapHighlights($im, $map, $mapInfo);
	drawMapSelements($im, $map, $mapInfo);
}

$expandMacros = getRequest('expand_macros', true);
drawMapLabels($im, $map, $mapInfo, $expandMacros, $graphtheme);
drawMapLinkLabels($im, $map, $mapInfo, $expandMacros, $graphtheme);

if (!isset($_REQUEST['noselements']) && $map['markelements'] == 1) {
	drawMapSelementsMarks($im, $map, $mapInfo);
}

show_messages();

if (getRequest('base64image')) {
	ob_start();
	imagepng($im);
	$imageSource = ob_get_contents();
	ob_end_clean();
	$json = new CJson();
	echo $json->encode(['result' => base64_encode($imageSource)]);
	imagedestroy($im);
}
else {
	imageOut($im);
}

require_once dirname(__FILE__).'/include/page_footer.php';
