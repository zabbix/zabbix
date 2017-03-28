<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

function getActionsBySysmap($sysmap, array $options = []) {
	$sysmap['links'] = zbx_toHash($sysmap['links'], 'linkid');

	$actions = [];

	$hostIds = [];
	$triggerIds = [];
	$host_groupids = [];

	foreach ($sysmap['selements'] as &$selement) {
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
		elseif ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP) {
			$host_groupids[$selement['elementid']] = $selement['elementid'];
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

	$monitored_triggers_hosts = API::Host()->get([
		'output' => ['hostid'],
		'hostids' => $hostIds,
		'with_monitored_triggers' => true,
		'preservekeys' => true,
		'nopermissions' => true
	]);

	$triggers = API::Trigger()->get([
		'output' => ['triggerid'],
		'selectHosts' => ['hostid', 'status'],
		'triggerids' => $triggerIds,
		'preservekeys' => true,
		'nopermissions' => true
	]);

	$host_groups = API::HostGroup()->get([
		'output' => ['groupid'],
		'groupids' => $host_groupids,
		'with_monitored_triggers' => true,
		'preservekeys' => true,
		'nopermissions' => true
	]);

	foreach ($sysmap['selements'] as $selementid => $elem) {
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
				$gotos['showTriggers'] = ($host['status'] == HOST_STATUS_MONITORED
						&& array_key_exists($elem['elementid'], $monitored_triggers_hosts));

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
					'severity_min' => isset($options['severity_min']) ? $options['severity_min'] : null,
					'fullscreen' => array_key_exists('fullscreen', $options) ? $options['fullscreen'] : 0
				];
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$gotos['showEvents'] = false;

				if (isset($triggers[$elem['elementid']])) {
					$trigger = $triggers[$elem['elementid']];

					foreach ($trigger['hosts'] as $host) {
						if ($host['status'] == HOST_STATUS_MONITORED) {
							$gotos['showEvents'] = true;

							break;
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
				$gotos['showTriggers'] = array_key_exists($elem['elementid'], $host_groups);
				break;
		}

		order_result($elem['urls'], 'name');

		$map = CMenuPopupHelper::getMap($hostId, $scripts, $gotos, $elem['urls']);
		if ($map == ['type' => 'map']) {
			$map = null;
		}
		else {
			$map = CJs::encodeJson($map);
		}

		$actions[$selementid] = $map;
	}

	return $actions;
}

function get_png_by_selement($info) {
	$image = get_image_by_imageid($info['iconid']);

	return $image['image'] ? imagecreatefromstring($image['image']) : get_default_image();
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
	$info = [
		'latelyChanged' => $i['latelyChanged'],
		'ack' => $i['ack'],
		'priority' => $i['priority'],
		'info' => [],
		'iconid' => $selement['iconid_off']
	];

	if ($i['problem'] && ($i['problem_unack'] && $showUnack == EXTACK_OPTION_UNACK
			|| in_array($showUnack, [EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH]))) {
		$info['info']['unack'] = [
			'msg' => _('PROBLEM'),
			'color' => ($i['priority'] > 3) ? 'FF0000' : '960000'
		];

		if (!array_key_exists('maintenance_title', $i)) {
			$info['iconid'] = $selement['iconid_on'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;

			return $info;
		}
	}

	if (array_key_exists('maintenance_title', $i)) {
		$info['iconid'] = $selement['iconid_maintenance'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
		$info['info']['maintenance'] = [
			'msg' => _('MAINTENANCE').' ('.$i['maintenance_title'].')',
			'color' => 'EE9600'
		];
	}
	elseif ($i['trigger_disabled']) {
		$info['iconid'] = $selement['iconid_disabled'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
		$info['info']['status'] = [
			'msg' => _('DISABLED'),
			'color' => '960000'
		];
	}
	else {
		$info['iconid'] = $selement['iconid_off'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		$info['info']['ok'] = [
			'msg' => _('OK'),
			'color' => '009600'
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
				'color' => ($i['priority'] > 3) ? 'FF0000' : '960000'
			];
		}

		if (in_array($show_unack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH]) && $i['problem_unack']) {
			$info['info']['unack'] = [
				'msg' => $i['problem_unack'].' '._('Unacknowledged'),
				'color' => '960000'
			];
		}

		// set element to problem state if it has problem events
		if ($info['info']) {
			$info['iconid'] = $selement['iconid_on'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
			$hasProblem = true;
		}
	}

	if (array_key_exists('maintenance_title', $i)) {
		$info['iconid'] = $selement['iconid_maintenance'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
		$info['info']['maintenance'] = [
			'msg' => _('MAINTENANCE').' ('.$i['maintenance_title'].')',
			'color' => 'EE9600'
		];
	}
	elseif ($i['disabled']) {
		$info['iconid'] = $selement['iconid_disabled'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
		$info['info']['status'] = [
			'msg' => _('DISABLED'),
			'color' => '960000'
		];
	}
	elseif (!$hasProblem) {
		$info['iconid'] = $selement['iconid_off'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		$info['info']['ok'] = [
			'msg' => _('OK'),
			'color' => '009600'
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
				'color' => ($i['priority'] > 3) ? 'FF0000' : '960000'
			];
		}

		if (in_array($show_unack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH]) && $i['problem_unack']) {
			$info['info']['unack'] = [
				'msg' => $i['problem_unack'].' '._('Unacknowledged'),
				'color' => '960000'
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
			'color' => 'EE9600'
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
			'color' => '960000'
		];
		$hasStatus = true;
	}

	if (!$hasStatus && !$hasProblem) {
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		$info['iconid'] = $selement['iconid_off'];
		$info['info']['ok'] = [
			'msg' => _('OK'),
			'color' => '009600'
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
				'color' => ($i['priority'] > 3) ? 'FF0000' : '960000'
			];
		}

		if (in_array($show_unack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH]) && $i['problem_unack']) {
			$info['info']['unack'] = [
				'msg' => $i['problem_unack'].' '._('Unacknowledged'),
				'color' => '960000'
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
			'color' => 'EE9600'
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
			'color' => '960000'
		];
		$hasStatus = true;
	}

	if (!$hasStatus && !$hasProblem) {
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		$info['iconid'] = $selement['iconid_off'];
		$info['info']['ok'] = [
			'msg' => _('OK'),
			'color' => '009600'
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
	if (!empty($triggerIdToSelementIds)) {
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'status', 'value', 'priority', 'lastchange', 'description', 'expression'],
			'selectHosts' => ['maintenance_status', 'maintenanceid'],
			'selectLastEvent' => ['acknowledged'],
			'triggerids' => array_keys($triggerIdToSelementIds),
			'filter' => ['state' => null],
			'nopermissions' => true
		]);

		foreach ($triggers as $trigger) {
			foreach ($triggerIdToSelementIds[$trigger['triggerid']] as $belongs_to_sel) {
				$selements[$belongs_to_sel]['triggers'][$trigger['triggerid']] = $trigger;
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

		foreach ($triggers as $trigger) {
			foreach ($subSysmapTriggerIdToSelementIds[$trigger['triggerid']] as $belongs_to_sel) {
				$selements[$belongs_to_sel]['triggers'][$trigger['triggerid']] = $trigger;
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
		$triggers = API::Trigger()->get([
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

		foreach ($triggers as $trigger) {
			foreach ($trigger['hosts'] as $host) {
				if (isset($hostIdToSelementIds[$host['hostid']])) {
					foreach ($hostIdToSelementIds[$host['hostid']] as $belongs_to_sel) {
						$selements[$belongs_to_sel]['triggers'][$trigger['triggerid']] = $trigger;
					}
				}
			}
		}

		$subSysmapHostApplicationFilters = getSelementHostApplicationFilters($selements, $selementIdToSubSysmaps,
			$hostsFromHostGroups
		);
		$selements = filterSysmapTriggers($selements, $subSysmapHostApplicationFilters, $triggers,
			$subSysmapTriggerIdToSelementIds
		);
	}

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

		foreach ($selement['triggers'] as $trigger) {
			if ($options['severity_min'] <= $trigger['priority']) {
				if ($trigger['status'] == TRIGGER_STATUS_DISABLED) {
					$i['trigger_disabled']++;
				}
				else {
					if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
						$i['problem']++;
						$lastProblemId = $trigger['triggerid'];

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
			$i['problem_title'] = CMacrosResolverHelper::resolveTriggerName($selement['triggers'][$lastProblemId]);
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
				if ($i['maintenance'] == 1) {
					$mnt = get_maintenance_by_maintenanceid($allHosts[$last_hostid]['maintenanceid']);
					$i['maintenance_title'] = $mnt['name'];
				}

				$info[$selementId] = getHostsInfo($selement, $i, $showUnacknowledged);
				if ($sysmap['iconmapid'] && $selement['use_iconmap']) {
					$info[$selementId]['iconid'] = getIconByMapping($iconMap, $hostInventories[$selement['elementid']]);
				}
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				foreach ($trigger['hosts'] as $host) {
					if (array_key_exists('maintenance_status', $host)
							&& $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
						$maintenance = get_maintenance_by_maintenanceid($host['maintenanceid']);
						$i['maintenance_title'] = $maintenance['name'];

						break;
					}
				}

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
		foreach ($elems['triggers'] as $selementid => $elem) {
			$trigger = $selements[$selementid]['triggers'][$elem['elementid']];
			$info[$elem['selementid']]['name'] = CMacrosResolverHelper::resolveTriggerName($trigger);
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
		foreach ($selement['triggers'] as $trigger) {
			if (!isset($triggersFromMonitoredHosts[$trigger['triggerid']])) {
				continue;
			}
			$trigger = $triggersFromMonitoredHosts[$trigger['triggerid']];
			foreach ($trigger['hosts'] as $host) {
				$hostId = $host['hostid'];
				if (isset($selementHostApplicationFilters[$selementId][$hostId])) {
					$triggersToFilter[$trigger['triggerid']] = $trigger;
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

/**
 * For each host group which is area for hosts virtual elements as hosts from that host group are created
 *
 * @param array $map
 * @return array areas with area coordinates and selementids
 */
function populateFromMapAreas(array &$map, $theme) {
	$areas = [];

	foreach ($map['selements'] as $selement) {
		if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP &&
			$selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {

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

				$map['shapes'][] = [
					'shapeid' => 'e-' . $selement['selementid'],
					'type' => 0,
					'x' => $selement['x'],
					'y' => $selement['y'],
					'width' => $selement['width'],
					'height' => $selement['height'],
					'border_type' => 0,
					'border_width' => 3,
					'border_color' => $theme['maingridcolor'],
					'background_color' => '',
					'zindex' => -1
				];
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

				$newSelementid = array_keys($map['selements'])[count($map['selements']) - 1] + 1;
				while (isset($map['selements'][$newSelementid])) {
					$newSelementid += 1;
				}
				$selement['selementid'] = -$newSelementid;

				$area['selementids'][$newSelementid] = $newSelementid;
				$map['selements'][$newSelementid] = $selement;
			}

			$areas[] = $area;

			$selements = zbx_toHash($map['selements'], 'selementid');
			foreach ($map['links'] as $link) {
				// do not multiply links between two areas
				if ($selements[$link['selementid1']]['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP &&
					$selements[$link['selementid1']]['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS &&
					$selements[$link['selementid2']]['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP &&
					$selements[$link['selementid2']]['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS) {
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
						$newLinkid = array_keys($map['links'])[count($map['links']) - 1] + 1;
						while (isset($map['links'][$newLinkid])) {
							$newLinkid += 1;
						}

						$link['linkid'] = -$newLinkid;
						$link[$idNumber] = -$newSelementid;
						$map['links'][$newLinkid] = $link;
					}
				}
			}
		}
	}

	$map['selements'] = array_filter($map['selements'], function ($element) {
		return ($element['elementtype'] != SYSMAP_ELEMENT_TYPE_HOST_GROUP ||
			$element['elementsubtype'] != SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS);
	});

	return $areas;
}

/**
 * Calculates coordinates from elements inside areas
 *
 * @param array $map
 * @param array $areas
 * @param array $mapInfo
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
 * @param int $sysmapid
 *
 * @return array
 */
function get_parent_sysmaps($sysmapid) {
	$db_sysmaps_elements = DBselect(
		'SELECT DISTINCT se.sysmapid'.
		' FROM sysmaps_elements se'.
		' WHERE '.dbConditionInt('se.elementtype', [SYSMAP_ELEMENT_TYPE_MAP]).
			' AND '.dbConditionInt('se.elementid', [$sysmapid])
	);

	$sysmapids = [];

	while ($db_sysmaps_element = DBfetch($db_sysmaps_elements)) {
		$sysmapids[] = $db_sysmaps_element['sysmapid'];
	}

	if ($sysmapids) {
		$sysmaps = API::Map()->get([
			'output' => ['sysmapid', 'name'],
			'sysmapids' => $sysmapids
		]);

		CArrayHelper::sort($sysmaps, ['name']);

		return $sysmaps;
	}

	return [];
}

/* TODO: refactor much */
function getMapLabels($map, $mapInfo, $resolveMacros) {
	if ($map['label_type'] == MAP_LABEL_TYPE_NOTHING && $map['label_format'] == SYSMAP_LABEL_ADVANCED_OFF) {
		return;
	}

	$selements = $map['selements'];
	$labelLines = [];
	$statusLines = [];

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
		$msgs = explode("\n", $msg);
		foreach ($msgs as $msg) {
			$labelLines[$selementId][] = ['content' => $msg];
		}

		$elementInfo = $mapInfo[$selementId];

		foreach (['problem', 'unack', 'maintenance', 'ok', 'status'] as $caption) {
			if (!isset($elementInfo['info'][$caption]) || zbx_empty($elementInfo['info'][$caption]['msg'])) {
				continue;
			}

			$statusLines[$selementId][] = [
				'content' => $elementInfo['info'][$caption]['msg'],
				'attributes' => [
					'fill' => '#' . $elementInfo['info'][$caption]['color']
				]
			];
		}
	}

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

	$labels = [];
	foreach ($selements as $selementId => $selement) {
		if (empty($selement) || $selement['label_type'] == MAP_LABEL_TYPE_NOTHING) {
			$labels[$selementId] = [];
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

			$label[] = ['content' => $interface['ip']];
			$label = array_merge($label, $statusLines[$selementId]);
		}
		elseif ($selement['label_type'] == MAP_LABEL_TYPE_STATUS) {
			$label = $statusLines[$selementId];
		}
		elseif ($selement['label_type'] == MAP_LABEL_TYPE_NAME) {
			$label[] = ['content' => $elementInfo['name']];
			$label = array_merge($label, $statusLines[$selementId]);
		}
		else {
			$label = array_merge($labelLines[$selementId], $statusLines[$selementId]);
		}

		$labels[$selementId] = $label;
	}

	return $labels;
}

function getMapHighligts($map, $mapInfo) {
	$config = select_config();

	$highlights = [];
	foreach ($map['selements'] as $id => $selement) {
		if ((($map['highlight'] % 2) != SYSMAP_HIGHLIGHT_ON) ||
			(isset($selement['elementtype']) && $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP &&
			isset($selement['elementsubtype']) && $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS)) {
			$highlights[$id] = null;
			continue;
		}

		$hl_color = null;
		$st_color = null;
		$elementInfo = $mapInfo[$id];

		switch ($elementInfo['icon_type']) {
			case SYSMAP_ELEMENT_ICON_ON:
				$hl_color = getSeverityColor($elementInfo['priority']);
			break;

			case SYSMAP_ELEMENT_ICON_MAINTENANCE:
				$st_color = 'FF9933';
			break;

			case SYSMAP_ELEMENT_ICON_DISABLED:
				$st_color = '999999';
			break;
		}

		$mainProblems = [
			SYSMAP_ELEMENT_TYPE_HOST_GROUP => 1,
			SYSMAP_ELEMENT_TYPE_MAP => 1
		];

		if (isset($mainProblems[$selement['elementtype']])) {
			if ($hl_color !== null) {
				$st_color = null;
			}
		}
		elseif (!is_null($st_color)) {
			$hl_color = null;
		}

		$highlights[$id] = [
			'st' =>  $st_color,
			'hl' => $hl_color,
			'ack' => ($hl_color !== null && isset($elementInfo['ack']) && $elementInfo['ack'] && $config['event_ack_enable'])
		];
	}

	return $highlights;
}
