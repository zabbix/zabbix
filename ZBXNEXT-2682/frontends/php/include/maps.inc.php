<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


function sysmap_element_types($type = null) {
	$types = [
		SYSMAP_ELEMENT_TYPE_HOST => _('Host'),
		SYSMAP_ELEMENT_TYPE_HOST_GROUP => _('Host group'),
		SYSMAP_ELEMENT_TYPE_TRIGGER => _('Trigger'),
		SYSMAP_ELEMENT_TYPE_MAP => _('Map'),
		SYSMAP_ELEMENT_TYPE_IMAGE => _('Image')
	];

	if (is_null($type)) {
		natsort($types);
		return $types;
	}
	elseif (isset($types[$type])) {
		return $types[$type];
	}
	else {
		return _('Unknown');
	}
}

function sysmapElementLabel($label = null) {
	$labels = [
		MAP_LABEL_TYPE_LABEL => _('Label'),
		MAP_LABEL_TYPE_IP => _('IP address'),
		MAP_LABEL_TYPE_NAME => _('Element name'),
		MAP_LABEL_TYPE_STATUS => _('Status only'),
		MAP_LABEL_TYPE_NOTHING => _('Nothing'),
		MAP_LABEL_TYPE_CUSTOM => _('Custom label')
	];

	if (is_null($label)) {
		return $labels;
	}
	elseif (isset($labels[$label])) {
		return $labels[$label];
	}
	else {
		return false;
	}
}

/**
 * Create map area with submenu for sysmap elements.
 * In submenu gathered information about urls, scripts and submaps.
 *
 * @param array $sysmap
 * @param array $options
 * @param int   $options['severity_min']
 *
 * @return CAreaMap
 */
function getActionMapBySysmap($sysmap, array $options = []) {
	$sysmap['selements'] = zbx_toHash($sysmap['selements'], 'selementid');
	$sysmap['links'] = zbx_toHash($sysmap['links'], 'linkid');

	$actionMap = new CAreaMap('links'.$sysmap['sysmapid']);

	$areas = populateFromMapAreas($sysmap);
	$mapInfo = getSelementsInfo($sysmap, $options);
	processAreasCoordinates($sysmap, $areas, $mapInfo);

	$hostIds = [];
	$triggerIds = [];

	foreach ($sysmap['selements'] as $id => &$selement) {
		if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
			$hostIds[$selement['elementid']] = $selement['elementid'];

			// expanding host URL macros again as some hosts were added from hostgroup areas
			// and automatic expanding only happens for elements that are defined for map in db
			foreach ($selement['urls'] as $urlId => $url) {
				$selement['urls'][$urlId]['url'] = str_replace('{HOST.ID}', $selement['elementid'], $url['url']);
			}
		}
		elseif ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
			$triggerIds[$selement['elementid']] = $selement['elementid'];
		}

		if ($selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
			unset($sysmap['selements'][$id]);
		}
	}
	unset($selement);

	$hostScripts = API::Script()->getScriptsByHosts($hostIds);

	$hosts = API::Host()->get([
		'hostids' => $hostIds,
		'output' => ['hostid', 'status'],
		'nopermissions' => true,
		'preservekeys' => true,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT
	]);

	$triggers = API::Trigger()->get([
		'output' => ['triggerid'],
		'selectHosts' => ['hostid', 'status'],
		'triggerids' => $triggerIds,
		'preservekeys' => true,
		'nopermissions' => true
	]);

	// Find monitored hosts and get groups that those hosts belong to.
	$monitored_hostids = [];

	foreach ($triggers as $trigger) {
		foreach ($trigger['hosts'] as $host) {
			if ($host['status'] == HOST_STATUS_MONITORED) {
				$monitored_hostids[$host['hostid']] = true;
			}
		}
	}

	if ($monitored_hostids) {
		$monitored_hosts = API::Host()->get([
			'output' => ['hostid'],
			'selectGroups' => ['groupid'],
			'hostids' => array_keys($monitored_hostids),
			'preservekeys' => true
		]);
	}

	foreach ($sysmap['selements'] as $elem) {
		$back = get_png_by_selement($mapInfo[$elem['selementid']]);
		$area = new CArea(
			[
				$elem['x'],
				$elem['y'],
				$elem['x'] + imagesx($back),
				$elem['y'] + imagesy($back)
			],
			'', '', 'rect'
		);
		$area->addClass('menu-map');

		$hostId = null;
		$scripts = null;
		$gotos = null;

		switch ($elem['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST:
				$host = $hosts[$elem['elementid']];

				if ($hostScripts[$elem['elementid']]) {
					$hostId = $elem['elementid'];
					$scripts = $hostScripts[$elem['elementid']];
				}

				$gotos['triggerStatus'] = [
					'hostid' => $elem['elementid'],
					'show_severity' => isset($options['severity_min']) ? $options['severity_min'] : null
				];
				$gotos['showTriggers'] = ($hosts[$elem['elementid']]['status'] == HOST_STATUS_MONITORED);

				$gotos['graphs'] = ['hostid' => $host['hostid']];
				$gotos['showGraphs'] = (bool) $host['graphs'];

				$gotos['screens'] = ['hostid' => $host['hostid']];
				$gotos['showScreens'] = (bool) $host['screens'];

				$gotos['inventory'] = ['hostid' => $host['hostid']];

				$gotos['latestData'] = ['hostids' => [$host['hostid']]];
				break;

			case SYSMAP_ELEMENT_TYPE_MAP:
				$gotos['submap'] = [
					'sysmapid' => $elem['elementid'],
					'severity_min' => isset($options['severity_min']) ? $options['severity_min'] : null
				];
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$gotos['showEvents'] = false;

				if (isset($triggers[$elem['elementid']])) {
					$trigger = $triggers[$elem['elementid']];

					foreach ($trigger['hosts'] as $host) {
						if ($host['status'] == HOST_STATUS_MONITORED) {
							$gotos['showEvents'] = true;

							// Pass a monitored 'hostid' and corresponding first 'groupid' to menu pop-up "Events" link.
							$gotos['events']['hostid'] = $host['hostid'];
							$gotos['events']['groupid'] = $monitored_hosts[$host['hostid']]['groups'][0]['groupid'];
							break;
						}
						else {
							// Unmonitored will have disabled "Events" link and there is no 'groupid' or 'hostid'.
							$gotos['events']['hostid'] = 0;
							$gotos['events']['groupid'] = 0;
						}
					}

					$gotos['events']['triggerid'] = $elem['elementid'];
				}
				break;

			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$gotos['triggerStatus'] = [
					'groupid' => $elem['elementid'],
					'hostid' => 0,
					'show_severity' => isset($options['severity_min']) ? $options['severity_min'] : null
				];

				// always show active trigger link for host group map elements
				$gotos['showTriggers'] = true;
				break;
		}

		order_result($elem['urls'], 'name');

		$area->setMenuPopup(CMenuPopupHelper::getMap($hostId, $scripts, $gotos, $elem['urls']));

		$actionMap->addItem($area);
	}

	return $actionMap;
}

function get_icon_center_by_selement($element, $info, $map) {
	$x = $element['x'];
	$y = $element['y'];

	if (isset($element['elementsubtype']) && $element['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
		if ($element['areatype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
			$w = $element['width'];
			$h = $element['height'];
		}
		else {
			$w = $map['width'];
			$h = $map['height'];
		}
	}
	else {
		$image = get_png_by_selement($info);
		$w = imagesx($image);
		$h = imagesy($image);
	}

	$x += $w / 2;
	$y += $h / 2;

	return [$x, $y];
}

function myDrawLine($image, $x1, $y1, $x2, $y2, $color, $drawtype) {
	if ($drawtype == MAP_LINK_DRAWTYPE_BOLD_LINE) {
		zbx_imagealine($image, $x1, $y1, $x2, $y2, $color, LINE_TYPE_BOLD);
	}
	elseif ($drawtype == MAP_LINK_DRAWTYPE_DASHED_LINE) {
		if (function_exists('imagesetstyle')) {
			// use imagesetstyle + imageline instead of bugged ImageDashedLine
			$style = [
				$color, $color, $color, $color,
				IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT
			];
			imagesetstyle($image, $style);
			zbx_imageline($image, $x1, $y1, $x2, $y2, IMG_COLOR_STYLED);
		}
		else {
			imagedashedline($image, $x1, $y1, $x2, $y2, $color);
		}
	}
	elseif ($drawtype == MAP_LINK_DRAWTYPE_DOT && function_exists('imagesetstyle')) {
		$style = [$color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT];
		imagesetstyle($image, $style);
		zbx_imageline($image, $x1, $y1, $x2, $y2, IMG_COLOR_STYLED);
	}
	else {
		zbx_imagealine($image, $x1, $y1, $x2, $y2, $color);
	}
}

function get_png_by_selement($info) {
	$image = get_image_by_imageid($info['iconid']);

	return $image['image'] ? imagecreatefromstring($image['image']) : get_default_image();
}

function convertColor($im, $color) {
	$RGB = [
		hexdec('0x'.substr($color, 0, 2)),
		hexdec('0x'.substr($color, 2, 2)),
		hexdec('0x'.substr($color, 4, 2))
	];
	return imagecolorallocate($im, $RGB[0], $RGB[1], $RGB[2]);
}

function get_map_elements($db_element, &$elements) {
	switch ($db_element['elementtype']) {
		case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
			$elements['hosts_groups'][] = $db_element['elementid'];
			break;
		case SYSMAP_ELEMENT_TYPE_HOST:
			$elements['hosts'][] = $db_element['elementid'];
			break;
		case SYSMAP_ELEMENT_TYPE_TRIGGER:
			$elements['triggers'][] = $db_element['elementid'];
			break;
		case SYSMAP_ELEMENT_TYPE_MAP:
			$db_mapselements = DBselect(
				'SELECT DISTINCT se.elementtype,se.elementid'.
				' FROM sysmaps_elements se'.
				' WHERE se.sysmapid='.zbx_dbstr($db_element['elementid'])
			);
			while ($db_mapelement = DBfetch($db_mapselements)) {
				get_map_elements($db_mapelement, $elements);
			}
			break;
	}
}

/**
 * Adds names to elements. Adds expression for SYSMAP_ELEMENT_TYPE_TRIGGER elements.
 *
 * @param type $selements
 */
function add_elementNames(&$selements) {
	$hostids = [];
	$triggerids = [];
	$mapids = [];
	$hostgroupids = [];
	$imageids = [];

	foreach ($selements as $selement) {
		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST:
				$hostids[$selement['elementid']] = $selement['elementid'];
				break;
			case SYSMAP_ELEMENT_TYPE_MAP:
				$mapids[$selement['elementid']] = $selement['elementid'];
				break;
			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$triggerids[$selement['elementid']] = $selement['elementid'];
				break;
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$hostgroupids[$selement['elementid']] = $selement['elementid'];
				break;
			case SYSMAP_ELEMENT_TYPE_IMAGE:
				$imageids[$selement['iconid_off']] = $selement['iconid_off'];
				break;
		}
	}

	$hosts = API::Host()->get([
		'hostids' => $hostids,
		'output' => ['name'],
		'nopermissions' => true,
		'preservekeys' => true
	]);

	$maps = API::Map()->get([
		'mapids' => $mapids,
		'output' => ['name'],
		'nopermissions' => true,
		'preservekeys' => true
	]);

	$triggers = API::Trigger()->get([
		'triggerids' => $triggerids,
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => ['hostid', 'name'],
		'nopermissions' => true,
		'preservekeys' => true
	]);

	$hostgroups = API::HostGroup()->get([
		'hostgroupids' => $hostgroupids,
		'output' => ['name'],
		'nopermissions' => true,
		'preservekeys' => true
	]);

	$images = API::image()->get([
		'imageids' => $imageids,
		'output' => API_OUTPUT_EXTEND,
		'nopermissions' => true,
		'preservekeys' => true
	]);

	foreach ($selements as $snum => $selement) {
		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST:
				$selements[$snum]['elementName'] = $hosts[$selement['elementid']]['name'];
				break;
			case SYSMAP_ELEMENT_TYPE_MAP:
				$selements[$snum]['elementName'] = $maps[$selement['elementid']]['name'];
				break;
			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$hostname = reset($triggers[$selement['elementid']]['hosts']);
				$selements[$snum]['elementName'] = $hostname['name'].NAME_DELIMITER.
					CMacrosResolverHelper::resolveTriggerName($triggers[$selement['elementid']]);
				break;
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$selements[$snum]['elementName'] = $hostgroups[$selement['elementid']]['name'];
				break;
			case SYSMAP_ELEMENT_TYPE_IMAGE:
				if (isset($images[$selement['iconid_off']]['name'])) {
					$selements[$snum]['elementName'] = $images[$selement['iconid_off']]['name'];
				}
				break;
		}
	}

	if (!empty($triggers)) {
		add_triggerExpressions($selements, $triggers);
	}
}

function add_triggerExpressions(&$selements, $triggers = []) {
	if (empty($triggers)) {
		$triggerIds = [];

		foreach ($selements as $selement) {
			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				$triggerIds[] = $selement['elementid'];
			}
		}

		$triggers = API::Trigger()->get([
			'triggerids' => $triggerIds,
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => ['name'],
			'nopermissions' => true,
			'preservekeys' => true
		]);
	}

	foreach ($selements as $snum => $selement) {
		if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
			$selements[$snum]['elementExpressionTrigger'] = $triggers[$selement['elementid']]['expression'];
		}
	}
}

/**
 * Returns trigger element icon rendering parameters.
 *
 * @param $selement
 * @param $i
 * @param $showUnack    map "problem display" parameter
 *
 * @return array
 */
function getTriggersInfo($selement, $i, $showUnack) {
	global $colors;

	$info = [
		'latelyChanged' => $i['latelyChanged'],
		'ack' => $i['ack'],
		'priority' => $i['priority'],
		'info' => [],
		'iconid' => $selement['iconid_off']
	];

	if ($i['problem'] && ($i['problem_unack'] && $showUnack == EXTACK_OPTION_UNACK
			|| in_array($showUnack, [EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH]))) {
		$info['iconid'] = $selement['iconid_on'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
		$info['info']['unack'] = [
			'msg' => _('PROBLEM'),
			'color' => ($i['priority'] > 3) ? $colors['Red'] : $colors['Dark Red']
		];
	}
	elseif ($i['trigger_disabled']) {
		$info['iconid'] = $selement['iconid_disabled'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
		$info['info']['status'] = [
			'msg' => _('DISABLED'),
			'color' => $colors['Dark Red']
		];
	}
	else {
		$info['iconid'] = $selement['iconid_off'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		$info['info']['ok'] = [
			'msg' => _('OK'),
			'color' => $colors['Dark Green']
		];
	}

	return $info;
}

/**
 * Returns host element icon rendering parameters.
 *
 * @param $selement
 * @param $i
 * @param $show_unack    map "problem display" parameter
 *
 * @return array
 */
function getHostsInfo($selement, $i, $show_unack) {
	global $colors;

	$info = [
		'latelyChanged' => $i['latelyChanged'],
		'ack' => $i['ack'],
		'priority' => $i['priority'],
		'info' => [],
		'iconid' => $selement['iconid_off']
	];
	$hasProblem = false;

	if ($i['problem']) {
		if (in_array($show_unack, [EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH])) {
			if ($i['problem'] > 1) {
				$msg = $i['problem'].' '._('Problems');
			}
			elseif (isset($i['problem_title'])) {
				$msg = $i['problem_title'];
			}
			else {
				$msg = '1 '._('Problem');
			}

			$info['info']['problem'] = [
				'msg' => $msg,
				'color' => ($i['priority'] > 3) ? $colors['Red'] : $colors['Dark Red']
			];
		}

		if (in_array($show_unack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH]) && $i['problem_unack']) {
			$info['info']['unack'] = [
				'msg' => $i['problem_unack'].' '._('Unacknowledged'),
				'color' => $colors['Dark Red']
			];
		}

		// set element to problem state if it has problem events
		if ($info['info']) {
			$info['iconid'] = $selement['iconid_on'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
			$hasProblem = true;
		}
	}

	if ($i['maintenance']) {
		$info['iconid'] = $selement['iconid_maintenance'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
		$info['info']['maintenance'] = [
			'msg' => _('MAINTENANCE').' ('.$i['maintenance_title'].')',
			'color' => $colors['Orange']
		];
	}
	elseif ($i['disabled']) {
		$info['iconid'] = $selement['iconid_disabled'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
		$info['info']['status'] = [
			'msg' => _('DISABLED'),
			'color' => $colors['Dark Red']
		];
	}
	elseif (!$hasProblem) {
		$info['iconid'] = $selement['iconid_off'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		$info['info']['ok'] = [
			'msg' => _('OK'),
			'color' => $colors['Dark Green']
		];
	}

	return $info;
}

/**
 * Returns host groups element icon rendering parameters.
 *
 * @param $selement
 * @param $i
 * @param $show_unack    map "problem display" parameter
 *
 * @return array
 */
function getHostGroupsInfo($selement, $i, $show_unack) {
	global $colors;

	$info = [
		'latelyChanged' => $i['latelyChanged'],
		'ack' => $i['ack'],
		'priority' => $i['priority'],
		'info' => [],
		'iconid' => $selement['iconid_off']
	];
	$hasProblem = false;
	$hasStatus = false;

	if ($i['problem']) {
		if (in_array($show_unack, [EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH])) {
			if ($i['problem'] > 1) {
				$msg = $i['problem'].' '._('Problems');
			}
			elseif (isset($i['problem_title'])) {
				$msg = $i['problem_title'];
			}
			else {
				$msg = '1 '._('Problem');
			}

			$info['info']['problem'] = [
				'msg' => $msg,
				'color' => ($i['priority'] > 3) ? $colors['Red'] : $colors['Dark Red']
			];
		}

		if (in_array($show_unack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH]) && $i['problem_unack']) {
			$info['info']['unack'] = [
				'msg' => $i['problem_unack'].' '._('Unacknowledged'),
				'color' => $colors['Dark Red']
			];
		}

		// set element to problem state if it has problem events
		if ($info['info']) {
			$info['iconid'] = $selement['iconid_on'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
			$hasProblem = true;
		}
	}

	if ($i['maintenance']) {
		if (!$hasProblem) {
			$info['iconid'] = $selement['iconid_maintenance'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
		}
		$info['info']['maintenance'] = [
			'msg' => $i['maintenance'].' '._('Maintenance'),
			'color' => $colors['Orange']
		];
		$hasStatus = true;
	}
	elseif ($i['disabled']) {
		if (!$hasProblem) {
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
			$info['iconid'] = $selement['iconid_disabled'];
		}
		$info['info']['disabled'] = [
			'msg' => _('DISABLED'),
			'color' => $colors['Dark Red']
		];
		$hasStatus = true;
	}

	if (!$hasStatus && !$hasProblem) {
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		$info['iconid'] = $selement['iconid_off'];
		$info['info']['ok'] = [
			'msg' => _('OK'),
			'color' => $colors['Dark Green']
		];
	}

	return $info;
}

/**
 * Returns maps groups element icon rendering parameters.
 *
 * @param $selement
 * @param $i
 * @param $show_unack    map "problem display" parameter
 *
 * @return array
 */
function getMapsInfo($selement, $i, $show_unack) {
	global $colors;

	$info = [
		'latelyChanged' => $i['latelyChanged'],
		'ack' => $i['ack'],
		'priority' => $i['priority'],
		'info' => [],
		'iconid' => $selement['iconid_off']
	];

	$hasProblem = false;
	$hasStatus = false;

	if ($i['problem']) {
		if (in_array($show_unack, [EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH])) {
			if ($i['problem'] > 1) {
				$msg = $i['problem'].' '._('Problems');
			}
			elseif (isset($i['problem_title'])) {
				$msg = $i['problem_title'];
			}
			else {
				$msg = '1 '._('Problem');
			}

			$info['info']['problem'] = [
				'msg' => $msg,
				'color' => ($i['priority'] > 3) ? $colors['Red'] : $colors['Dark Red']
			];
		}

		if (in_array($show_unack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH]) && $i['problem_unack']) {
			$info['info']['unack'] = [
				'msg' => $i['problem_unack'].' '._('Unacknowledged'),
				'color' => $colors['Dark Red']
			];
		}

		if ($info['info']) {
			$info['iconid'] = $selement['iconid_on'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
			$hasProblem = true;
		}
	}

	if ($i['maintenance']) {
		if (!$hasProblem) {
			$info['iconid'] = $selement['iconid_maintenance'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
		}
		$info['info']['maintenance'] = [
			'msg' => $i['maintenance'].' '._('Maintenance'),
			'color' => $colors['Orange']
		];
		$hasStatus = true;
	}
	elseif ($i['disabled']) {
		if (!$hasProblem) {
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
			$info['iconid'] = $selement['iconid_disabled'];
		}
		$info['info']['disabled'] = [
			'msg' => _('DISABLED'),
			'color' => $colors['Dark Red']
		];
		$hasStatus = true;
	}

	if (!$hasStatus && !$hasProblem) {
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		$info['iconid'] = $selement['iconid_off'];
		$info['info']['ok'] = [
			'msg' => _('OK'),
			'color' => $colors['Dark Green']
		];
	}

	return $info;
}

function getImagesInfo($selement) {
	return [
		'iconid' => $selement['iconid_off'],
		'icon_type' => SYSMAP_ELEMENT_ICON_OFF,
		'name' => _('Image'),
		'latelyChanged' => false
	];
}

/**
 * Prepare map elements data.
 * Calculate problem triggers and priorities. Populate map elements with automatic icon mapping, acknowledging and
 * recent change markers.
 *
 * @param array $sysmap
 * @param int   $options
 * @param int   $options['severity_min'] Minimum trigger severity, default value is maximal (Disaster)
 *
 * @return array
 */
function getSelementsInfo($sysmap, array $options = []) {
	if (!isset($options['severity_min'])) {
		$options['severity_min'] = TRIGGER_SEVERITY_NOT_CLASSIFIED;
	}

	$config = select_config();
	$showUnacknowledged = $config['event_ack_enable'] ? $sysmap['show_unack'] : EXTACK_OPTION_ALL;

	$triggerIdToSelementIds = [];
	$subSysmapTriggerIdToSelementIds = [];
	$hostGroupIdToSelementIds = [];
	$hostIdToSelementIds = [];

	if ($sysmap['sysmapid']) {
		$iconMap = API::IconMap()->get([
			'sysmapids' => $sysmap['sysmapid'],
			'selectMappings' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		]);
		$iconMap = reset($iconMap);

	}
	$hostsToGetInventories = [];

	$selements = $sysmap['selements'];
	$selementIdToSubSysmaps = [];
	foreach ($selements as $selementId => &$selement) {
		$selement['hosts'] = [];
		$selement['triggers'] = [];

		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_MAP:
				$sysmapIds = [$selement['elementid']];

				while (!empty($sysmapIds)) {
					$subSysmaps = API::Map()->get([
						'sysmapids' => $sysmapIds,
						'output' => ['sysmapid'],
						'selectSelements' => API_OUTPUT_EXTEND,
						'nopermissions' => true,
						'preservekeys' => true
					]);

					if(!isset($selementIdToSubSysmaps[$selementId])) {
						$selementIdToSubSysmaps[$selementId] = [];
					}
					$selementIdToSubSysmaps[$selementId] += $subSysmaps;

					$sysmapIds = [];
					foreach ($subSysmaps as $subSysmap) {
						foreach ($subSysmap['selements'] as $subSysmapSelement) {
							switch ($subSysmapSelement['elementtype']) {
								case SYSMAP_ELEMENT_TYPE_MAP:
									$sysmapIds[] = $subSysmapSelement['elementid'];
									break;
								case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
									$hostGroupIdToSelementIds[$subSysmapSelement['elementid']][$selementId] = $selementId;
									break;
								case SYSMAP_ELEMENT_TYPE_HOST:
									$hostIdToSelementIds[$subSysmapSelement['elementid']][$selementId] = $selementId;
									break;
								case SYSMAP_ELEMENT_TYPE_TRIGGER:
									$subSysmapTriggerIdToSelementIds[$subSysmapSelement['elementid']][$selementId] = $selementId;
									break;
							}
						}
					}
				}
				break;
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$hostGroupId = $selement['elementid'];
				$hostGroupIdToSelementIds[$hostGroupId][$selementId] = $selementId;
				break;
			case SYSMAP_ELEMENT_TYPE_HOST:
				$hostId = $selement['elementid'];
				$hostIdToSelementIds[$hostId][$selementId] = $selementId;

				// if we have icon map applied, we need to get inventories for all hosts,
				// where automatic icon selection is enabled.
				if ($sysmap['iconmapid'] && $selement['use_iconmap']) {
					$hostsToGetInventories[] = $hostId;
				}
				break;
			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$triggerId = $selement['elementid'];
				$triggerIdToSelementIds[$triggerId][$selementId] = $selementId;
				break;
		}
	}
	unset($selement);

	// get host inventories
	if ($sysmap['iconmapid']) {
		$hostInventories = API::Host()->get([
			'hostids' => $hostsToGetInventories,
			'output' => ['hostid'],
			'nopermissions' => true,
			'preservekeys' => true,
			'selectInventory' => API_OUTPUT_EXTEND
		]);
	}

	$allHosts = [];
	if (!empty($hostIdToSelementIds)) {
		$hosts = API::Host()->get([
			'hostids' => array_keys($hostIdToSelementIds),
			'output' => ['name', 'status', 'maintenance_status', 'maintenanceid'],
			'nopermissions' => true,
			'preservekeys' => true
		]);
		$allHosts = array_merge($allHosts, $hosts);
		foreach ($hosts as $hostId => $host) {
			foreach ($hostIdToSelementIds[$hostId] as $selementId) {
				$selements[$selementId]['hosts'][$hostId] = $hostId;
			}
		}
	}

	$hostsFromHostGroups = [];
	if (!empty($hostGroupIdToSelementIds)) {
		$hostsFromHostGroups = API::Host()->get([
			'groupids' => array_keys($hostGroupIdToSelementIds),
			'output' => ['name', 'status', 'maintenance_status', 'maintenanceid'],
			'selectGroups' => ['groupid'],
			'nopermissions' => true,
			'preservekeys' => true
		]);

		foreach ($hostsFromHostGroups as $hostId => $host) {
			foreach ($host['groups'] as $group) {
				$groupId = $group['groupid'];

				if (isset($hostGroupIdToSelementIds[$groupId])) {
					foreach ($hostGroupIdToSelementIds[$groupId] as $selementId) {
						$selement =& $selements[$selementId];

						$selement['hosts'][$hostId] = $hostId;

						// add hosts to hosts_map for trigger selection;
						if (!isset($hostIdToSelementIds[$hostId])) {
							$hostIdToSelementIds[$hostId] = [];
						}
						$hostIdToSelementIds[$hostId][$selementId] = $selementId;

						unset($selement);
					}
				}
			}
		}

		$allHosts = array_merge($allHosts, $hostsFromHostGroups);
	}

	$allHosts = zbx_toHash($allHosts, 'hostid');

	// get triggers data, triggers from current map, select all
	$allTriggers = [];

	if (!empty($triggerIdToSelementIds)) {
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'status', 'value', 'priority', 'lastchange', 'description', 'expression'],
			'selectLastEvent' => ['acknowledged'],
			'triggerids' => array_keys($triggerIdToSelementIds),
			'filter' => ['state' => null],
			'nopermissions' => true,
			'preservekeys' => true
		]);

		$allTriggers = array_merge($allTriggers, $triggers);

		foreach ($triggers as $triggerId => $trigger) {
			foreach ($triggerIdToSelementIds[$triggerId] as $selementId) {
				$selements[$selementId]['triggers'][$triggerId] = $triggerId;
			}
		}
	}

	// triggers from submaps, skip dependent
	if (!empty($subSysmapTriggerIdToSelementIds)) {
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'status', 'value', 'priority', 'lastchange', 'description', 'expression'],
			'selectLastEvent' => ['acknowledged'],
			'triggerids' => array_keys($subSysmapTriggerIdToSelementIds),
			'filter' => ['state' => null],
			'skipDependent' => true,
			'nopermissions' => true,
			'preservekeys' => true,
			'only_true' => true
		]);

		$allTriggers = array_merge($allTriggers, $triggers);

		foreach ($triggers as $triggerId => $trigger) {
			foreach ($subSysmapTriggerIdToSelementIds[$triggerId] as $selementId) {
				$selements[$selementId]['triggers'][$triggerId] = $triggerId;
			}
		}
	}

	$monitored_hostids = [];
	foreach ($allHosts as $hostid => $host) {
		if ($host['status'] == HOST_STATUS_MONITORED) {
			$monitored_hostids[$hostid] = true;
		}
	}

	// triggers from all hosts/hostgroups, skip dependent
	if ($monitored_hostids) {
		$triggersFromMonitoredHosts = API::Trigger()->get([
			'output' => ['triggerid', 'status', 'value', 'priority', 'lastchange', 'description', 'expression'],
			'selectHosts' => ['hostid'],
			'selectItems' => ['itemid'],
			'selectLastEvent' => ['acknowledged'],
			'hostids' => array_keys($monitored_hostids),
			'filter' => ['state' => null],
			'monitored' => true,
			'skipDependent' => true,
			'nopermissions' => true,
			'preservekeys' => true,
			'only_true' => true
		]);

		foreach ($triggersFromMonitoredHosts as $triggerId => $trigger) {
			foreach ($trigger['hosts'] as $host) {
				$hostId = $host['hostid'];

				if (isset($hostIdToSelementIds[$hostId])) {
					foreach ($hostIdToSelementIds[$hostId] as $selementId) {
						$selements[$selementId]['triggers'][$triggerId] = $triggerId;
					}
				}
			}
		}

		$subSysmapHostApplicationFilters = getSelementHostApplicationFilters($selements, $selementIdToSubSysmaps,
			$hostsFromHostGroups
		);
		$selements = filterSysmapTriggers($selements, $subSysmapHostApplicationFilters, $triggersFromMonitoredHosts,
			$subSysmapTriggerIdToSelementIds
		);

		$allTriggers = array_merge($allTriggers, $triggersFromMonitoredHosts);
	}

	$allTriggers = zbx_toHash($allTriggers, 'triggerid');

	$info = [];
	foreach ($selements as $selementId => $selement) {
		$i = [
			'disabled' => 0,
			'maintenance' => 0,
			'problem' => 0,
			'problem_unack' => 0,
			'priority' => 0,
			'trigger_disabled' => 0,
			'latelyChanged' => false,
			'ack' => true
		];

		foreach ($selement['hosts'] as $hostId) {
			$host = $allHosts[$hostId];
			$last_hostid = $hostId;

			if ($host['status'] == HOST_STATUS_NOT_MONITORED) {
				$i['disabled']++;
			}
			elseif ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$i['maintenance']++;
			}
		}

		$last_event = false;

		foreach ($selement['triggers'] as $triggerId) {
			$trigger = $allTriggers[$triggerId];

			if ($options['severity_min'] <= $trigger['priority']) {
				if ($trigger['status'] == TRIGGER_STATUS_DISABLED) {
					$i['trigger_disabled']++;
				}
				else {
					if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
						$i['problem']++;
						$lastProblemId = $triggerId;

						if ($i['priority'] < $trigger['priority']) {
							$i['priority'] = $trigger['priority'];
						}

						if ($trigger['lastEvent']) {
							if (!$trigger['lastEvent']['acknowledged']) {
								$i['problem_unack']++;
							}

							$last_event = $last_event || true;
						}
					}

					$i['latelyChanged'] |= ((time() - $trigger['lastchange']) < $config['blink_period']);
				}
			}
		}

		// If there are no events, problems cannot be unacknowledged. Hide the green line in this case.
		$i['ack'] = ($last_event) ? (bool) !($i['problem_unack']) : false;

		if ($sysmap['expandproblem'] && $i['problem'] == 1) {
			if (!isset($lastProblemId)) {
				$lastProblemId = null;
			}

			$i['problem_title'] = CMacrosResolverHelper::resolveTriggerName($allTriggers[$lastProblemId]);
		}

		if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST && $i['maintenance'] == 1) {
			$mnt = get_maintenance_by_maintenanceid($allHosts[$last_hostid]['maintenanceid']);
			$i['maintenance_title'] = $mnt['name'];
		}

		// replace default icons
		if (!$selement['iconid_on']) {
			$selement['iconid_on'] = $selement['iconid_off'];
		}
		if (!$selement['iconid_maintenance']) {
			$selement['iconid_maintenance'] = $selement['iconid_off'];
		}
		if (!$selement['iconid_disabled']) {
			$selement['iconid_disabled'] = $selement['iconid_off'];
		}

		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_MAP:
				$info[$selementId] = getMapsInfo($selement, $i, $showUnacknowledged);
				break;

			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$info[$selementId] = getHostGroupsInfo($selement, $i, $showUnacknowledged);
				break;

			case SYSMAP_ELEMENT_TYPE_HOST:
				$info[$selementId] = getHostsInfo($selement, $i, $showUnacknowledged);
				if ($sysmap['iconmapid'] && $selement['use_iconmap']) {
					$info[$selementId]['iconid'] = getIconByMapping($iconMap, $hostInventories[$selement['elementid']]);
				}
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$info[$selementId] = getTriggersInfo($selement, $i, $showUnacknowledged);
				break;

			case SYSMAP_ELEMENT_TYPE_IMAGE:
				$info[$selementId] = getImagesInfo($selement);
				break;
		}
	}

	if ($sysmap['label_format'] == SYSMAP_LABEL_ADVANCED_OFF) {
		$hlabel = $hglabel = $tlabel = $mlabel = ($sysmap['label_type'] == MAP_LABEL_TYPE_NAME);
	}
	else {
		$hlabel = ($sysmap['label_type_host'] == MAP_LABEL_TYPE_NAME);
		$hglabel = ($sysmap['label_type_hostgroup'] == MAP_LABEL_TYPE_NAME);
		$tlabel = ($sysmap['label_type_trigger'] == MAP_LABEL_TYPE_NAME);
		$mlabel = ($sysmap['label_type_map'] == MAP_LABEL_TYPE_NAME);
	}

	// get names if needed
	$elems = separateMapElements($sysmap);
	if (!empty($elems['sysmaps']) && $mlabel) {
		$subSysmaps = API::Map()->get([
			'sysmapids' => zbx_objectValues($elems['sysmaps'], 'elementid'),
			'nopermissions' => true,
			'output' => ['name']
		]);
		$subSysmaps = zbx_toHash($subSysmaps, 'sysmapid');

		foreach ($elems['sysmaps'] as $elem) {
			$info[$elem['selementid']]['name'] = $subSysmaps[$elem['elementid']]['name'];
		}
	}
	if (!empty($elems['hostgroups']) && $hglabel) {
		$hostgroups = API::HostGroup()->get([
			'groupids' => zbx_objectValues($elems['hostgroups'], 'elementid'),
			'nopermissions' => true,
			'output' => ['name']
		]);
		$hostgroups = zbx_toHash($hostgroups, 'groupid');

		foreach ($elems['hostgroups'] as $elem) {
			$info[$elem['selementid']]['name'] = $hostgroups[$elem['elementid']]['name'];
		}
	}

	if (!empty($elems['triggers']) && $tlabel) {
		foreach ($elems['triggers'] as $elem) {
			$info[$elem['selementid']]['name'] = CMacrosResolverHelper::resolveTriggerName($allTriggers[$elem['elementid']]);
		}
	}
	if (!empty($elems['hosts']) && $hlabel) {
		foreach ($elems['hosts'] as $elem) {
			$info[$elem['selementid']]['name'] = $allHosts[$elem['elementid']]['name'];
		}
	}

	return $info;
}

/**
 * Takes sysmap selements array, applies filtering by application to triggers and returns sysmap selements array.
 *
 * @param array $selements                          selements of current sysmap
 * @param array $selementHostApplicationFilters     a list of application filters applied to each host under each element
 *                                                  @see getSelementHostApplicationFilters()
 * @param array $triggersFromMonitoredHosts         triggers that are relevant to filtering
 * @param array $subSysmapTriggerIdToSelementIds    a map of triggers in sysmaps to selement IDs
 *
 * @return array
 */
function filterSysmapTriggers(array $selements, array $selementHostApplicationFilters, array $triggersFromMonitoredHosts, array $subSysmapTriggerIdToSelementIds) {
	// pick only host, host group or map selements
	$filterableSelements = [];
	foreach ($selements as $selementId => $selement) {
		if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST
				|| $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
				|| $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) {
			$filterableSelements[$selementId] = $selement;
		}
	}
	// calculate list of triggers that might get removed from $selement['triggers']
	$triggersToFilter = [];
	foreach ($filterableSelements as $selementId => $selement) {
		foreach ($selement['triggers'] as $triggerId) {
			if (!isset($triggersFromMonitoredHosts[$triggerId])) {
				continue;
			}
			$trigger = $triggersFromMonitoredHosts[$triggerId];
			foreach ($trigger['hosts'] as $host) {
				$hostId = $host['hostid'];
				if (isset($selementHostApplicationFilters[$selementId][$hostId])) {
					$triggersToFilter[$triggerId] = $trigger;
				}
			}
		}
	}

	// if there are no triggers to filter
	if (!$triggersToFilter) {
		return $selements;
	}

	// produce mapping of trigger to application names it is related to and produce mapping of host to triggers
	$itemIds = [];
	foreach ($triggersToFilter as $trigger) {
		foreach ($trigger['items'] as $item) {
			$itemIds[$item['itemid']] = $item['itemid'];
		}
	}
	$items = API::Item()->get([
		'output' => ['itemid'],
		'selectApplications' => ['name'],
		'itemids' => $itemIds,
		'webitems' => true,
		'preservekeys' => true
	]);

	$triggerApplications = [];
	$hostIdToTriggers = [];
	foreach ($triggersToFilter as $trigger) {
		$triggerId = $trigger['triggerid'];

		foreach ($trigger['items'] as $item) {
			foreach ($items[$item['itemid']]['applications'] as $application) {
				$triggerApplications[$triggerId][$application['name']] = true;
			}
		}

		foreach ($trigger['hosts'] as $host) {
			$hostIdToTriggers[$host['hostid']][$triggerId] = $trigger;
		}
	}

	foreach ($filterableSelements as $selementId => &$selement) {
		// walk through each host of a submap and apply its filters to all its triggers
		foreach ($selement['hosts'] as $hostId) {
			// skip hosts that don't have any filters or triggers to filter
			if (!isset($hostIdToTriggers[$hostId]) || !isset($selementHostApplicationFilters[$selementId][$hostId])) {
				continue;
			}

			// remove the triggers that don't have applications or don't match the filter
			$filteredApplicationNames = $selementHostApplicationFilters[$selementId][$hostId];
			foreach ($hostIdToTriggers[$hostId] as $trigger) {
				$triggerId = $trigger['triggerid'];

				// skip if this trigger is standalone trigger and those are not filtered
				if (isset($subSysmapTriggerIdToSelementIds[$triggerId])
						&& isset($subSysmapTriggerIdToSelementIds[$triggerId][$selementId])) {
					continue;
				}

				$applicationNamesForTrigger = isset($triggerApplications[$triggerId])
					? array_keys($triggerApplications[$triggerId])
					: [];

				if (!array_intersect($applicationNamesForTrigger, $filteredApplicationNames)) {
					unset($selement['triggers'][$triggerId]);
				}
			}
		}
	}
	unset($selement);

	// put back updated selements
	foreach ($filterableSelements as $selementId => $selement) {
		$selements[$selementId] = $selement;
	}

	return $selements;
}

/**
 * Returns a list of application filters applied to each host under each element.
 *
 * @param array $selements                  selements of current sysmap
 * @param array $selementIdToSubSysmaps     all sub-sysmaps used in current sysmap, indexed by selementId
 * @param array $hostsFromHostGroups        collection of hosts that get included via host groups
 *
 * @return array    a two-dimensional array with selement IDs as the primary key, host IDs as the secondary key
 *                  application names as values
 */
function getSelementHostApplicationFilters(array $selements, array $selementIdToSubSysmaps, array $hostsFromHostGroups) {
	$hostIdsForHostGroupId = [];
	foreach ($hostsFromHostGroups as $host) {
		$hostId = $host['hostid'];
		foreach ($host['groups'] as $group) {
			$hostIdsForHostGroupId[$group['groupid']][$hostId] = $hostId;
		}
	}

	$selementHostApplicationFilters = [];
	foreach ($selements as $selementId => $selement) {
		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST:
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				// skip host and host group elements with an empty filter
				if ($selement['application'] === '') {
					continue 2;
				}

				foreach ($selement['hosts'] as $hostId) {
					$selementHostApplicationFilters[$selementId][$hostId][] = $selement['application'];
				}

				break;

			case SYSMAP_ELEMENT_TYPE_MAP:
				foreach ($selementIdToSubSysmaps[$selementId] as $subSysmap) {
					// add all filters set for host elements
					foreach ($subSysmap['selements'] as $subSysmapSelement) {
						if ($subSysmapSelement['elementtype'] != SYSMAP_ELEMENT_TYPE_HOST
								|| $subSysmapSelement['application'] === '') {

							continue;
						}

						$hostId = $subSysmapSelement['elementid'];
						$selementHostApplicationFilters[$selementId][$hostId][] = $subSysmapSelement['application'];
					}

					// Find all selements with host groups and sort them into two arrays:
					// - with application filter
					// - without application filter
					$hostGroupSelementsWithApplication = [];
					$hostGroupSelementsWithoutApplication = [];
					foreach ($subSysmap['selements'] as $subSysmapSelement) {
						if ($subSysmapSelement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP) {
							if ($subSysmapSelement['application'] !== '') {
								$hostGroupSelementsWithApplication[] = $subSysmapSelement;
							}
							else {
								$hostGroupSelementsWithoutApplication[] = $subSysmapSelement;
							}
						}
					}

					// Combine application filters for hosts from host group selements with
					// application filters set.
					foreach ($hostGroupSelementsWithApplication as $hostGroupSelement) {
						$hostGroupId = $hostGroupSelement['elementid'];

						if (isset($hostIdsForHostGroupId[$hostGroupId])) {
							foreach ($hostIdsForHostGroupId[$hostGroupId] as $hostId) {
								$selementHostApplicationFilters[$selementId][$hostId][] = $hostGroupSelement['application'];
							}
						}
					}

					// Unset all application filters for hosts in host group selements without any filters.
					// This might reset application filters set by previous foreach.
					foreach ($hostGroupSelementsWithoutApplication AS $hostGroupSelement) {
						$hostGroupId = $hostGroupSelement['elementid'];

						if (isset($hostIdsForHostGroupId[$hostGroupId])) {
							foreach ($hostIdsForHostGroupId[$hostGroupId] as $hostId) {
								unset($selementHostApplicationFilters[$selementId][$hostId]);
							}
						}
					}
				}

				break;
		}
	}

	return $selementHostApplicationFilters;
}

function separateMapElements($sysmap) {
	$elements = [
		'sysmaps' => [],
		'hostgroups' => [],
		'hosts' => [],
		'triggers' => [],
		'images' => []
	];

	foreach ($sysmap['selements'] as $selement) {
		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_MAP:
				$elements['sysmaps'][$selement['selementid']] = $selement;
				break;
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$elements['hostgroups'][$selement['selementid']] = $selement;
				break;
			case SYSMAP_ELEMENT_TYPE_HOST:
				$elements['hosts'][$selement['selementid']] = $selement;
				break;
			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$elements['triggers'][$selement['selementid']] = $selement;
				break;
			case SYSMAP_ELEMENT_TYPE_IMAGE:
			default:
				$elements['images'][$selement['selementid']] = $selement;
		}
	}
	return $elements;
}

function drawMapConnectors(&$im, $map, $mapInfo, $drawAll = false) {
	$selements = $map['selements'];

	foreach ($map['links'] as $link) {
		$selement1 = $selements[$link['selementid1']];
		$selement2 = $selements[$link['selementid2']];

		list($x1, $y1) = get_icon_center_by_selement($selement1, $mapInfo[$link['selementid1']], $map);
		list($x2, $y2) = get_icon_center_by_selement($selement2, $mapInfo[$link['selementid2']], $map);

		if (isset($selement1['elementsubtype']) && $selement1['elementsubtype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
			if (!$drawAll && ($selement2['elementsubtype'] != SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS)) {
				continue;
			}

			if ($selement1['areatype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
				$w = $selement1['width'];
				$h = $selement1['height'];
			}
			else {
				$w = $map['width'];
				$h = $map['height'];
			}
			list($x1, $y1) = calculateMapAreaLinkCoord($x1, $y1, $w, $h, $x2, $y2);
		}

		if (isset($selement2['elementsubtype']) && $selement2['elementsubtype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
			if (!$drawAll && ($selement1['elementsubtype'] != SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS)) {
				continue;
			}

			if ($selement2['areatype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
				$w = $selement2['width'];
				$h = $selement2['height'];
			}
			else {
				$w = $map['width'];
				$h = $map['height'];
			}
			list($x2, $y2) = calculateMapAreaLinkCoord($x2, $y2, $w,$h, $x1, $y1);
		}

		$drawtype = $link['drawtype'];
		$color = convertColor($im, $link['color']);

		$linktriggers = $link['linktriggers'];
		order_result($linktriggers, 'triggerid');

		if (!empty($linktriggers)) {
			$max_severity = 0;

			$triggers = [];
			foreach ($linktriggers as $link_trigger) {
				if ($link_trigger['triggerid'] == 0) {
					continue;
				}

				$id = $link_trigger['linktriggerid'];

				$triggers[$id] = zbx_array_merge($link_trigger, get_trigger_by_triggerid($link_trigger['triggerid']));
				if ($triggers[$id]['status'] == TRIGGER_STATUS_ENABLED && $triggers[$id]['value'] == TRIGGER_VALUE_TRUE) {
					if ($triggers[$id]['priority'] >= $max_severity) {
						$drawtype = $triggers[$id]['drawtype'];
						$color = convertColor($im, $triggers[$id]['color']);
						$max_severity = $triggers[$id]['priority'];
					}
				}
			}
		}

		myDrawLine($im, $x1, $y1, $x2, $y2, $color, $drawtype);
	}
}

function drawMapSelements(&$im, $map, $mapInfo) {
	$selements = $map['selements'];

	foreach ($selements as $selementId => $selement) {
		if (isset($selement['elementsubtype']) && $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
			continue;
		}

		$elementInfo = $mapInfo[$selementId];
		$img = get_png_by_selement($elementInfo);

		$iconX = imagesx($img);
		$iconY = imagesy($img);

		imagecopy($im, $img, $selement['x'], $selement['y'], 0, 0, $iconX, $iconY);
	}
}

function drawMapHighligts(&$im, $map, $mapInfo) {
	$config = select_config();

	$selements = $map['selements'];

	foreach ($selements as $selementId => $selement) {
		if (isset($selement['elementsubtype']) && $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
			continue;
		}

		$elementInfo = $mapInfo[$selementId];
		$img = get_png_by_selement($elementInfo);

		$iconX = imagesx($img);
		$iconY = imagesy($img);

		if (($map['highlight'] % 2) == SYSMAP_HIGHLIGHT_ON) {
			$hl_color = null;
			$st_color = null;

			if ($elementInfo['icon_type'] == SYSMAP_ELEMENT_ICON_ON) {
				$hl_color = hex2rgb(getSeverityColor($elementInfo['priority']));
			}

			if ($elementInfo['icon_type'] == SYSMAP_ELEMENT_ICON_MAINTENANCE) {
				$st_color = hex2rgb('FF9933');
			}
			if ($elementInfo['icon_type'] == SYSMAP_ELEMENT_ICON_DISABLED) {
				$st_color = hex2rgb('EEEEEE');
			}

			$mainProblems = [
				SYSMAP_ELEMENT_TYPE_HOST_GROUP => 1,
				SYSMAP_ELEMENT_TYPE_MAP => 1
			];

			if (isset($mainProblems[$selement['elementtype']])) {
				if (!is_null($hl_color)) {
					$st_color = null;
				}
			}
			elseif (!is_null($st_color)) {
				$hl_color = null;
			}

			if (!is_null($st_color)) {
				$r = $st_color[0];
				$g = $st_color[1];
				$b = $st_color[2];

				imagefilledrectangle($im,
					$selement['x'] - 2,
					$selement['y'] - 2,
					$selement['x'] + $iconX + 2,
					$selement['y'] + $iconY + 2,
					imagecolorallocatealpha($im, $r, $g, $b, 0)
				);

				// shadow
				imagerectangle($im,
					$selement['x'] - 2 - 1,
					$selement['y'] - 2 - 1,
					$selement['x'] + $iconX + 2 + 1,
					$selement['y'] + $iconY + 2 + 1,
					imagecolorallocate($im, 120, 120, 120)
				);

				imagerectangle($im,
					$selement['x'] - 2 - 2,
					$selement['y'] - 2 - 2,
					$selement['x'] + $iconX + 2 + 2,
					$selement['y'] + $iconY + 2 + 2,
					imagecolorallocate($im, 220, 220, 220)
				);
			}

			if (!is_null($hl_color)) {
				$r = $hl_color[0];
				$g = $hl_color[1];
				$b = $hl_color[2];

				imagefilledellipse($im,
					$selement['x'] + ($iconX / 2),
					$selement['y'] + ($iconY / 2),
					$iconX + 20,
					$iconX + 20,
					imagecolorallocatealpha($im, $r, $g, $b, 0)
				);

				imageellipse($im,
					$selement['x'] + ($iconX / 2),
					$selement['y'] + ($iconY / 2),
					$iconX + 20 + 1,
					$iconX + 20 + 1,
					imagecolorallocate($im, 120, 120, 120)
				);

				if (isset($elementInfo['ack']) && $elementInfo['ack'] && $config['event_ack_enable']) {
					imagesetthickness($im, 5);
					imagearc($im,
						$selement['x'] + ($iconX / 2),
						$selement['y'] + ($iconY / 2),
						$iconX + 20 - 3,
						$iconX + 20 - 3,
						0,
						359,
						imagecolorallocate($im, 50, 150, 50)
					);
					imagesetthickness($im, 1);
				}
			}
		}
	}
}

function drawMapSelementsMarks(&$im, $map, $mapInfo) {
	global $colors;

	$selements = $map['selements'];

	foreach ($selements as $selementId => $selement) {
		if (empty($selement)) {
			continue;
		}

		$elementInfo = $mapInfo[$selementId];
		if (!$elementInfo['latelyChanged']) {
			continue;
		}

		// skip host group element containers
		if ($selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
			continue;
		}

		$img = get_png_by_selement($elementInfo);

		$iconX = imagesx($img);
		$iconY = imagesy($img);

		$hl_color = null;
		$st_color = null;
		if (!isset($_REQUEST['noselements']) && (($map['highlight'] % 2) == SYSMAP_HIGHLIGHT_ON)) {
			if ($elementInfo['icon_type'] == SYSMAP_ELEMENT_ICON_ON) {
				$hl_color = true;
			}
			if ($elementInfo['icon_type'] == SYSMAP_ELEMENT_ICON_MAINTENANCE
					|| $elementInfo['icon_type'] == SYSMAP_ELEMENT_ICON_DISABLED) {
				$st_color = true;
			}
		}

		$mainProblems = [
			SYSMAP_ELEMENT_TYPE_HOST_GROUP => 1,
			SYSMAP_ELEMENT_TYPE_MAP => 1
		];

		if (isset($mainProblems[$selement['elementtype']])) {
			if (!is_null($hl_color)) {
				$st_color = null;
			}
			elseif (!is_null($st_color)) {
				$hl_color = null;
			}
		}

		$markSize = $iconX / 2;
		if ($hl_color) {
			$markSize += 12;
		}
		elseif ($st_color) {
			$markSize += 8;
		}
		else {
			$markSize += 3;
		}

		if ($map['label_type'] != MAP_LABEL_TYPE_NOTHING) {
			$labelLocation = $selement['label_location'];
			if (is_null($labelLocation) || ($labelLocation < 0)) {
				$labelLocation = $map['label_location'];
			}

			switch ($labelLocation) {
				case MAP_LABEL_LOC_TOP:
					$marks = 'rbl';
					break;
				case MAP_LABEL_LOC_LEFT:
					$marks = 'trb';
					break;
				case MAP_LABEL_LOC_RIGHT:
					$marks = 'tbl';
					break;
				case MAP_LABEL_LOC_BOTTOM:
				default:
					$marks = 'trl';
			}
		}
		else {
			$marks = 'trbl';
		}

		imageVerticalMarks($im, $selement['x'] + ($iconX / 2), $selement['y'] + ($iconY / 2), $markSize, $colors['Red'], $marks);
	}
}

function drawMapLinkLabels(&$im, $map, $mapInfo, $resolveMacros = true) {
	global $colors;

	$links = $map['links'];
	$selements = $map['selements'];

	foreach ($links as $link) {
		if (empty($link['label'])) {
			continue;
		}

		$selement1 = $selements[$link['selementid1']];
		list($x1, $y1) = get_icon_center_by_selement($selement1, $mapInfo[$link['selementid1']], $map);

		$selement2 = $selements[$link['selementid2']];
		list($x2, $y2) = get_icon_center_by_selement($selement2, $mapInfo[$link['selementid2']], $map);

		if (isset($selement1['elementsubtype']) && $selement1['elementsubtype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
			if ($selement1['areatype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
				$w = $selement1['width'];
				$h = $selement1['height'];
			}
			else {
				$w = $map['width'];
				$h = $map['height'];
			}
			list($x1, $y1) = calculateMapAreaLinkCoord($x1, $y1, $w, $h, $x2, $y2);
		}

		if (isset($selement2['elementsubtype']) && $selement2['elementsubtype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
			if ($selement2['areatype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
				$w = $selement2['width'];
				$h = $selement2['height'];
			}
			else {
				$w = $map['width'];
				$h = $map['height'];
			}
			list($x2, $y2) = calculateMapAreaLinkCoord($x2, $y2, $w, $h, $x1, $y1);
		}

		$drawtype = $link['drawtype'];
		$color = convertColor($im, $link['color']);

		$linktriggers = $link['linktriggers'];
		order_result($linktriggers, 'triggerid');

		if (!empty($linktriggers)) {
			$max_severity = 0;

			$triggers = [];
			foreach ($linktriggers as $link_trigger) {
				if ($link_trigger['triggerid'] == 0) {
					continue;
				}
				$id = $link_trigger['linktriggerid'];

				$triggers[$id] = zbx_array_merge($link_trigger, get_trigger_by_triggerid($link_trigger['triggerid']));
				if ($triggers[$id]['status'] == TRIGGER_STATUS_ENABLED && $triggers[$id]['value'] == TRIGGER_VALUE_TRUE) {
					if ($triggers[$id]['priority'] >= $max_severity) {
						$drawtype = $triggers[$id]['drawtype'];
						$color = convertColor($im, $triggers[$id]['color']);
						$max_severity = $triggers[$id]['priority'];
					}
				}
			}
		}

		$label = $link['label'];

		$label = str_replace("\r", '', $label);
		$strings = explode("\n", $label);

		$box_width = 0;
		$box_height = 0;

		foreach ($strings as $snum => $str) {
			$strings[$snum] = $resolveMacros ? CMacrosResolverHelper::resolveMapLabelMacros($str) : $str;
		}

		foreach ($strings as $str) {
			$dims = imageTextSize(8, 0, $str);

			$box_width = ($box_width > $dims['width']) ? $box_width : $dims['width'];
			$box_height += $dims['height'] + 2;
		}

		$boxX_left = round(($x1 + $x2) / 2 - ($box_width / 2) - 6);
		$boxX_right = round(($x1 + $x2) / 2 + ($box_width / 2) + 6);

		$boxY_top = round(($y1 + $y2) / 2 - ($box_height / 2) - 4);
		$boxY_bottom = round(($y1 + $y2) / 2 + ($box_height / 2) + 2);

		switch ($drawtype) {
			case MAP_LINK_DRAWTYPE_DASHED_LINE:
			case MAP_LINK_DRAWTYPE_DOT:
				dashedRectangle($im, $boxX_left, $boxY_top, $boxX_right, $boxY_bottom, $color);
				break;
			case MAP_LINK_DRAWTYPE_BOLD_LINE:
				imagerectangle($im, $boxX_left - 1, $boxY_top - 1, $boxX_right + 1, $boxY_bottom + 1, $color);
				// break; is not ne
			case MAP_LINK_DRAWTYPE_LINE:
			default:
				imagerectangle($im, $boxX_left, $boxY_top, $boxX_right, $boxY_bottom, $color);
		}

		imagefilledrectangle($im, $boxX_left + 1, $boxY_top + 1, $boxX_right - 1, $boxY_bottom - 1, $colors['White']);

		$increasey = 4;
		foreach ($strings as $str) {
			$dims = imageTextSize(8, 0, $str);

			$labelx = ($x1 + $x2) / 2 - ($dims['width'] / 2);
			$labely = $boxY_top + $increasey;

			imagetext($im, 8, 0, $labelx, $labely + $dims['height'], $colors['Black'], $str);

			$increasey += $dims['height'] + 2;
		}
	}
}

function drawMapLabels(&$im, $map, $mapInfo, $resolveMacros = true) {
	global $colors;

	if ($map['label_type'] == MAP_LABEL_TYPE_NOTHING && $map['label_format'] == SYSMAP_LABEL_ADVANCED_OFF) {
		return;
	}

	$selements = $map['selements'];
	$allStrings = '';
	$labelLines = [];
	$statusLines = [];

	foreach ($selements as $sid => $selement) {
		if (isset($selement['elementsubtype']) && $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
			unset($selements[$sid]);
		}
	}

	// set label type and custom label text for all selements
	foreach ($selements as $selementId => $selement) {
		$selements[$selementId]['label_type'] = $map['label_type'];

		if ($map['label_format'] == SYSMAP_LABEL_ADVANCED_OFF) {
			continue;
		}

		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$selements[$selementId]['label_type'] = $map['label_type_hostgroup'];
				if ($map['label_type_hostgroup'] == MAP_LABEL_TYPE_CUSTOM) {
					$selements[$selementId]['label'] = $map['label_string_hostgroup'];
				}
				break;

			case SYSMAP_ELEMENT_TYPE_HOST:
				$selements[$selementId]['label_type'] = $map['label_type_host'];
				if ($map['label_type_host'] == MAP_LABEL_TYPE_CUSTOM) {
					$selements[$selementId]['label'] = $map['label_string_host'];
				}
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$selements[$selementId]['label_type'] = $map['label_type_trigger'];
				if ($map['label_type_trigger'] == MAP_LABEL_TYPE_CUSTOM) {
					$selements[$selementId]['label'] = $map['label_string_trigger'];
				}
				break;

			case SYSMAP_ELEMENT_TYPE_MAP:
				$selements[$selementId]['label_type'] = $map['label_type_map'];
				if ($map['label_type_map'] == MAP_LABEL_TYPE_CUSTOM) {
					$selements[$selementId]['label'] = $map['label_string_map'];
				}
				break;

			case SYSMAP_ELEMENT_TYPE_IMAGE:
				$selements[$selementId]['label_type'] = $map['label_type_image'];
				if ($map['label_type_image'] == MAP_LABEL_TYPE_CUSTOM) {
					$selements[$selementId]['label'] = $map['label_string_image'];
				}
				break;
		}
	}

	foreach ($selements as $selementId => $selement) {
		if (!isset($labelLines[$selementId])) {
			$labelLines[$selementId] = [];
		}
		if (!isset($statusLines[$selementId])) {
			$statusLines[$selementId] = [];
		}

		$msg = $resolveMacros ? CMacrosResolverHelper::resolveMapLabelMacrosAll($selement) : $selement['label'];

		$allStrings .= $msg;
		$msgs = explode("\n", $msg);
		foreach ($msgs as $msg) {
			$labelLines[$selementId][] = ['msg' => $msg];
		}

		$elementInfo = $mapInfo[$selementId];

		foreach (['problem', 'unack', 'maintenance', 'ok', 'status'] as $caption) {
			if (!isset($elementInfo['info'][$caption]) || zbx_empty($elementInfo['info'][$caption]['msg'])) {
				continue;
			}

			$statusLines[$selementId][] = [
				'msg' => $elementInfo['info'][$caption]['msg'],
				'color' => $elementInfo['info'][$caption]['color']
			];

			$allStrings .= $elementInfo['info'][$caption]['msg'];
		}
	}

	$allLabelsSize = imageTextSize(8, 0, str_replace("\r", '', str_replace("\n", '', $allStrings)));
	$labelFontHeight = $allLabelsSize['height'];
	$labelFontBaseline = $allLabelsSize['baseline'];

	$elementsHostIds = [];
	foreach ($selements as $selement) {
		if ($selement['label_type'] != MAP_LABEL_TYPE_IP) {
			continue;
		}
		if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
			$elementsHostIds[] = $selement['elementid'];
		}
	}

	if (!empty($elementsHostIds)) {
		$mapHosts = API::Host()->get([
			'hostids' => $elementsHostIds,
			'output' => ['hostid'],
			'selectInterfaces' => API_OUTPUT_EXTEND
		]);
		$mapHosts = zbx_toHash($mapHosts, 'hostid');
	}

	// draw
	foreach ($selements as $selementId => $selement) {
		if (empty($selement) || $selement['label_type'] == MAP_LABEL_TYPE_NOTHING) {
			continue;
		}

		$elementInfo = $mapInfo[$selementId];

		$hl_color = null;
		$st_color = null;

		if (!isset($_REQUEST['noselements']) && ($map['highlight'] % 2) == SYSMAP_HIGHLIGHT_ON) {
			if ($elementInfo['icon_type'] == SYSMAP_ELEMENT_ICON_ON) {
				$hl_color = true;
			}
			if ($elementInfo['icon_type'] == SYSMAP_ELEMENT_ICON_MAINTENANCE) {
				$st_color = true;
			}
			if ($elementInfo['icon_type'] == SYSMAP_ELEMENT_ICON_DISABLED) {
				$st_color = true;
			}
		}

		if (in_array($selement['elementtype'], [SYSMAP_ELEMENT_TYPE_HOST_GROUP, SYSMAP_ELEMENT_TYPE_MAP])
				&& !is_null($hl_color)) {
			$st_color = null;
		}
		elseif (!is_null($st_color)) {
			$hl_color = null;
		}

		$labelLocation = (is_null($selement['label_location']) || $selement['label_location'] < 0)
			? $map['label_location']
			: $selement['label_location'];

		$label = [];

		if ($selement['label_type'] == MAP_LABEL_TYPE_IP && $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
			$interface = reset($mapHosts[$selement['elementid']]['interfaces']);

			$label[] = ['msg' => $interface['ip']];
			$label = array_merge($label, $statusLines[$selementId]);
		}
		elseif ($selement['label_type'] == MAP_LABEL_TYPE_STATUS) {
			$label = $statusLines[$selementId];
		}
		elseif ($selement['label_type'] == MAP_LABEL_TYPE_NAME) {
			$label[] = ['msg' => $elementInfo['name']];
			$label = array_merge($label, $statusLines[$selementId]);
		}
		else {
			$label = array_merge($labelLines[$selementId], $statusLines[$selementId]);
		}

		if (empty($label)) {
			continue;
		}

		$w = 0;
		foreach ($label as $str) {
			$dims = imageTextSize(8, 0, $str['msg']);
			$w = max($w, $dims['width']);
		}

		$h = count($label) * $labelFontHeight;
		$x = $selement['x'];
		$y = $selement['y'];

		$image = get_png_by_selement($elementInfo);
		$iconX = imagesx($image);
		$iconY = imagesy($image);

		if (!is_null($hl_color)) {
			$icon_hl = 14;
		}
		elseif (!is_null($st_color)) {
			$icon_hl = 6;
		}
		else {
			$icon_hl = 2;
		}

		switch ($labelLocation) {
			case MAP_LABEL_LOC_TOP:
				$y_rec = $y - $icon_hl - $h - 6;
				$x_rec = $x + $iconX / 2 - $w / 2;
				break;

			case MAP_LABEL_LOC_LEFT:
				$y_rec = $y - $h / 2 + $iconY / 2;
				$x_rec = $x - $icon_hl - $w;
				break;

			case MAP_LABEL_LOC_RIGHT:
				$y_rec = $y - $h / 2 + $iconY / 2;
				$x_rec = $x + $iconX + $icon_hl;
				break;

			case MAP_LABEL_LOC_BOTTOM:
			default:
				$y_rec = $y + $iconY + $icon_hl;
				$x_rec = $x + $iconX / 2 - $w / 2;
		}

		$increasey = 12;
		foreach ($label as $line) {
			if (zbx_empty($line['msg'])) {
				continue;
			}

			$str = str_replace("\r", '', $line['msg']);
			$color = isset($line['color']) ? $line['color'] : $colors['Black'];

			$dims = imageTextSize(8, 0, $str);

			if ($labelLocation == MAP_LABEL_LOC_TOP || $labelLocation == MAP_LABEL_LOC_BOTTOM) {
				$x_label = $x + ceil($iconX / 2) - ceil($dims['width'] / 2);
			}
			elseif ($labelLocation == MAP_LABEL_LOC_LEFT) {
				$x_label = $x_rec + $w - $dims['width'];
			}
			else {
				$x_label = $x_rec;
			}

			imagefilledrectangle(
				$im,
				$x_label - 1, $y_rec + $increasey - $labelFontHeight + $labelFontBaseline,
				$x_label + $dims['width'] + 1, $y_rec + $increasey + $labelFontBaseline,
				$colors['White']
			);
			imagetext($im, 8, 0, $x_label, $y_rec + $increasey, $color, $str);

			$increasey += $labelFontHeight + 1;
		}
	}
}

/**
 * For each host group which is area for hosts virtual elements as hosts from that host group are created
 *
 * @param array $map
 * @return array areas with area coordinates and selementids
 */
function populateFromMapAreas(array &$map) {
	$areas = [];

	foreach ($map['selements'] as $selement) {
		if ($selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
			$area = ['selementids' => []];

			$origSelement = $selement;

			$hosts = API::host()->get([
				'groupids' => $selement['elementid'],
				'sortfield' => 'name',
				'output' => ['hostid'],
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$hostsCount = count($hosts);

			if ($hostsCount == 0) {
				continue;
			}

			if ($selement['areatype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
				$area['width'] = $selement['width'];
				$area['height'] = $selement['height'];
				$area['x'] = $selement['x'];
				$area['y'] = $selement['y'];
			}
			else {
				$area['width'] = $map['width'];
				$area['height'] = $map['height'];
				$area['x'] = 0;
				$area['y'] = 0;
			}

			foreach ($hosts as $host) {
				$selement['elementtype'] = SYSMAP_ELEMENT_TYPE_HOST;
				$selement['elementsubtype'] = SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP;
				$selement['elementid'] = $host['hostid'];

				$newSelementid = rand(1, 9999999);
				while (isset($map['selements'][$newSelementid])) {
					$newSelementid += 1;
				};
				$selement['selementid'] = $newSelementid;

				$area['selementids'][$newSelementid] = $newSelementid;
				$map['selements'][$newSelementid] = $selement;
			}

			$areas[] = $area;

			foreach ($map['links'] as $link) {
				// do not multiply links between two areas
				if ($map['selements'][$link['selementid1']]['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS
						&& $map['selements'][$link['selementid2']]['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
					continue;
				}

				$idNumber = null;
				if ($link['selementid1'] == $origSelement['selementid']) {
					$idNumber = 'selementid1';
				}
				elseif ($link['selementid2'] == $origSelement['selementid']) {
					$idNumber = 'selementid2';
				}

				if ($idNumber) {
					foreach ($area['selementids'] as $newSelementid) {
						$newLinkid = rand(1, 9999999);
						while (isset($map['links'][$newLinkid])) {
							$newLinkid += 1;
						};

						$link['linkid'] = $newLinkid;
						$link[$idNumber] = $newSelementid;
						$map['links'][$newLinkid] = $link;
					}
				}
			}
		}
	}

	return $areas;
}

/**
 * Calculates coordinates from elements inside areas
 *
 * @param array $map
 * @param array $areas
 * @param array $mapInfo
 *
 * @return void
 */
function processAreasCoordinates(array &$map, array $areas, array $mapInfo) {
	foreach ($areas as $area) {
		$rowPlaceCount = ceil(sqrt(count($area['selementids'])));

		// offset from area borders
		$area['x'] += 5;
		$area['y'] += 5;
		$area['width'] -= 5;
		$area['height'] -= 5;

		$xOffset = floor($area['width'] / $rowPlaceCount);
		$yOffset = floor($area['height'] / $rowPlaceCount);

		$colNum = 0;
		$rowNum = 0;
		// some offset is required so that icon highlights are not drawn outside area
		$borderOffset = 20;
		foreach ($area['selementids'] as $selementId) {
			$selement = $map['selements'][$selementId];

			$image = get_png_by_selement($mapInfo[$selementId]);
			$iconX = imagesx($image);
			$iconY = imagesy($image);

			$labelLocation = (is_null($selement['label_location']) || ($selement['label_location'] < 0))
				? $map['label_location'] : $selement['label_location'];
			switch ($labelLocation) {
				case MAP_LABEL_LOC_TOP:
					$newX = $area['x'] + ($xOffset / 2) - ($iconX / 2);
					$newY = $area['y'] + $yOffset - $iconY - ($iconY >= $iconX ? 0 : abs($iconX - $iconY) / 2) - $borderOffset;
					break;
				case MAP_LABEL_LOC_LEFT:
					$newX = $area['x'] + $xOffset - $iconX - $borderOffset;
					$newY = $area['y'] + ($yOffset / 2) - ($iconY / 2);
					break;
				case MAP_LABEL_LOC_RIGHT:
					$newX = $area['x'] + $borderOffset;
					$newY = $area['y'] + ($yOffset / 2) - ($iconY / 2);
					break;
				case MAP_LABEL_LOC_BOTTOM:
					$newX = $area['x'] + ($xOffset / 2) - ($iconX / 2);
					$newY = $area['y'] + abs($iconX - $iconY) / 2 + $borderOffset;
					break;
			}

			$map['selements'][$selementId]['x'] = $newX + ($colNum * $xOffset);
			$map['selements'][$selementId]['y'] = $newY + ($rowNum * $yOffset);

			$colNum++;
			if ($colNum == $rowPlaceCount) {
				$colNum = 0;
				$rowNum++;
			}
		}
	}
}

/**
 * Calculates area connector point on area perimeter
 *
 * @param int $ax      x area coordinate
 * @param int $ay      y area coordinate
 * @param int $aWidth  area width
 * @param int $aHeight area height
 * @param int $x2      x coordinate of connector second element
 * @param int $y2      y coordinate of connector second element
 *
 * @return array contains two values, x and y coordinates of new area connector point
 */
function calculateMapAreaLinkCoord($ax, $ay, $aWidth, $aHeight, $x2, $y2) {
	$dY = abs($y2 - $ay);
	$dX = abs($x2 - $ax);

	$halfHeight = $aHeight / 2;
	$halfWidth = $aWidth / 2;

	if ($dY == 0) {
		$ay = $y2;
		$ax = ($x2 < $ax) ? $ax - $halfWidth : $ax + $halfWidth;
	}
	elseif ($dX == 0) {
		$ay = ($y2 > $ay) ? $ay + $halfHeight : $ay - $halfHeight;
		$ax = $x2;
	}
	else {
		$koef = $halfHeight / $dY;

		$c = $dX * $koef;

		// if point is further than area diagonal, we should use calculations with width instead of height
		if (($halfHeight / $c) > ($halfHeight / $halfWidth)) {
			$ay = ($y2 > $ay) ? $ay + $halfHeight : $ay - $halfHeight;
			$ax = ($x2 < $ax) ? $ax - $c : $ax + $c;
		}
		else {
			$koef = $halfWidth / $dX;

			$c = $dY * $koef;

			$ay = ($y2 > $ay) ? $ay + $c : $ay - $c;
			$ax = ($x2 < $ax) ? $ax - $halfWidth : $ax + $halfWidth;
		}
	}

	return [$ax, $ay];
}

/**
 * Get icon id by mapping.
 *
 * @param array $iconMap
 * @param array $inventory
 *
 * @return int
 */
function getIconByMapping($iconMap, $inventory) {
	if (!empty($inventory['inventory'])) {
		$inventories = getHostInventories();

		foreach ($iconMap['mappings'] as $mapping) {
			try {
				$expr = new CGlobalRegexp($mapping['expression']);
				if ($expr->match($inventory['inventory'][$inventories[$mapping['inventory_link']]['db_field']])) {
					return $mapping['iconid'];
				}
			}
			catch(Exception $e) {
				continue;
			}
		}
	}

	return $iconMap['default_iconid'];
}

/**
 * Get parent maps for current map.
 *
 * @param int $mapId
 *
 * @return array
 */
function getParentMaps($mapId) {
	$parentMaps = DBfetchArrayAssoc(DBselect(
		'SELECT s.sysmapid,s.name'.
			' FROM sysmaps s'.
				' JOIN sysmaps_elements se ON se.sysmapid=s.sysmapid'.
			' WHERE se.elementtype='.SYSMAP_ELEMENT_TYPE_MAP.
				' AND se.elementid='.zbx_dbstr($mapId)
	), 'sysmapid');

	CArrayHelper::sort($parentMaps, ['name']);

	return $parentMaps;
}
