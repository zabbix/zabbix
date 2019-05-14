<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Get actions (data for popup menu) for map elements.
 *
 * @param array $sysmap
 * @param array $sysmap['selements']
 * @param array $options                    Options used to retrieve actions.
 * @param int   $options['severity_min']    Minimal severity used.
 *
 * @return array
 */
function getActionsBySysmap(array $sysmap, array $options = []) {
	$actions = [];
	$severity_min = array_key_exists('severity_min', $options)
		? $options['severity_min']
		: TRIGGER_SEVERITY_NOT_CLASSIFIED;

	foreach ($sysmap['selements'] as $selementid => $elem) {
		if ($elem['permission'] < PERM_READ) {
			continue;
		}

		$hostid = ($elem['elementtype_orig'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
				&& $elem['elementsubtype_orig'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS)
			? $elem['elements'][0]['hostid']
			: 0;

		$map = CMenuPopupHelper::getMapElement($sysmap['sysmapid'], $elem['selementid_orig'], $severity_min, $hostid);

		$actions[$selementid] = CJs::encodeJson($map);
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
			$elements['hosts_groups'][] = $db_element['elements'][0]['groupid'];
			break;
		case SYSMAP_ELEMENT_TYPE_HOST:
			$elements['hosts'][] = $db_element['elements'][0]['hostid'];
			break;
		case SYSMAP_ELEMENT_TYPE_TRIGGER:
			foreach ($db_element['elements'] as $db_element) {
				$elements['triggers'][] = $db_element['triggerid'];
			}
			break;
		case SYSMAP_ELEMENT_TYPE_MAP:
			$map = API::Map()->get([
				'output' => [],
				'selectSelements' => ['selementid', 'elements', 'elementtype'],
				'sysmapids' => $db_element['elements'][0]['sysmapid'],
				'nopermissions' => true
			]);

			if ($map) {
				$map = reset($map);

				foreach ($map['selements'] as $db_mapelement) {
					get_map_elements($db_mapelement, $elements);
				}
			}
			break;
	}
}

/**
 * Adds names to elements. Adds expression for SYSMAP_ELEMENT_TYPE_TRIGGER elements.
 *
 * @param array $selements
 * @param array $selements[]['elements']
 * @param int   $selements[]['elementtype']
 * @param int   $selements[]['iconid_off']
 * @param int   $selements[]['permission']
 */
function addElementNames(array &$selements) {
	$hostids = [];
	$triggerids = [];
	$sysmapids = [];
	$groupids = [];
	$imageids = [];

	foreach ($selements as $selement) {
		if ($selement['permission'] < PERM_READ) {
			continue;
		}

		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST:
				$hostids[$selement['elements'][0]['hostid']] = $selement['elements'][0]['hostid'];
				break;

			case SYSMAP_ELEMENT_TYPE_MAP:
				$sysmapids[$selement['elements'][0]['sysmapid']] = $selement['elements'][0]['sysmapid'];
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				foreach ($selement['elements'] as $element) {
					$triggerids[$element['triggerid']] = $element['triggerid'];
				}
				break;

			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$groupids[$selement['elements'][0]['groupid']] = $selement['elements'][0]['groupid'];
				break;

			case SYSMAP_ELEMENT_TYPE_IMAGE:
				$imageids[$selement['iconid_off']] = $selement['iconid_off'];
				break;
		}
	}

	$hosts = $hostids
		? API::Host()->get([
			'output' => ['name'],
			'hostids' => $hostids,
			'preservekeys' => true
		])
		: [];

	$maps = $sysmapids
		? API::Map()->get([
			'output' => ['name'],
			'sysmapids' => $sysmapids,
			'preservekeys' => true
		])
		: [];

	$triggers = $triggerids
		? API::Trigger()->get([
			'output' => ['description', 'expression', 'priority'],
			'selectHosts' => ['name'],
			'triggerids' => $triggerids,
			'preservekeys' => true
		])
		: [];
	$triggers = CMacrosResolverHelper::resolveTriggerNames($triggers);

	$groups = $groupids
		? API::HostGroup()->get([
			'output' => ['name'],
			'hostgroupids' => $groupids,
			'preservekeys' => true
		])
		: [];

	$images = $imageids
		? API::Image()->get([
			'output' => ['name'],
			'imageids' => $imageids,
			'preservekeys' => true
		])
		: [];

	foreach ($selements as $snum => &$selement) {
		if ($selement['permission'] < PERM_READ) {
			continue;
		}

		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST:
				$selements[$snum]['elements'][0]['elementName'] = $hosts[$selement['elements'][0]['hostid']]['name'];
				break;

			case SYSMAP_ELEMENT_TYPE_MAP:
				$selements[$snum]['elements'][0]['elementName'] = $maps[$selement['elements'][0]['sysmapid']]['name'];
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				foreach ($selement['elements'] as $enum => &$element) {
					if (array_key_exists($element['triggerid'], $triggers)) {
						$trigger = $triggers[$element['triggerid']];
						$element['elementName'] = $trigger['hosts'][0]['name'].NAME_DELIMITER.$trigger['description'];
						$element['priority'] = $trigger['priority'];
					}
					else {
						unset($selement['elements'][$enum]);
					}
				}
				unset($element);
				$selement['elements'] = array_values($selement['elements']);
				break;

			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$selements[$snum]['elements'][0]['elementName'] = $groups[$selement['elements'][0]['groupid']]['name'];
				break;

			case SYSMAP_ELEMENT_TYPE_IMAGE:
				if (array_key_exists($selement['iconid_off'], $images)) {
					$selements[$snum]['elements'][0]['elementName'] = $images[$selement['iconid_off']]['name'];
				}
				break;
		}
	}
	unset($selement);
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
		// Number of problems.
		if ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER) {
			if ($i['problem'] == 1) {
				$msg = _('PROBLEM');
			}
			else {
				$msg = $i['problem'].' '._('Problems');
			}
		}
		// Expand single problem.
		elseif ($i['expandproblem'] == SYSMAP_SINGLE_PROBLEM) {
			$msg = $i['problem_title'];
		}
		// Number of problems and expand most critical one.
		elseif ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER_CRITICAL) {
			$msg = $i['problem_title'];

			if ($i['problem'] > 1) {
				$msg .= "\n".$i['problem'].' '._('Problems');
			}
		}

		$info['info']['unack'] = [
			'msg' => $msg,
			'color' => getSelementLabelColor(true, !$i['problem_unack'])
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
			'color' => getSelementLabelColor(false, $i['ack'])
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
			// Number of problems.
			if ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER) {
				if ($i['problem'] == 1) {
					$msg = _('PROBLEM');
				}
				else {
					$msg = $i['problem'].' '._('Problems');
				}
			}
			// Expand single problem.
			elseif ($i['expandproblem'] == SYSMAP_SINGLE_PROBLEM) {
				$msg = $i['problem_title'];
			}
			// Number of problems and expand most critical one.
			elseif ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER_CRITICAL) {
				$msg = $i['problem_title'];

				if ($i['problem'] > 1) {
					$msg .= "\n".$i['problem'].' '._('Problems');
				}
			}

			$info['info']['problem'] = [
				'msg' => $msg,
				'color' => getSelementLabelColor(true, !$i['problem_unack'])
			];
		}

		if (in_array($show_unack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH]) && $i['problem_unack']) {
			$info['info']['unack'] = [
				'msg' => $i['problem_unack'].' '._('Unacknowledged'),
				'color' => getSelementLabelColor(true, false)
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
			'color' => getSelementLabelColor(false, $i['ack'])
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
			// Number of problems.
			if ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER) {
				if ($i['problem'] == 1) {
					$msg = _('PROBLEM');
				}
				else {
					$msg = $i['problem'].' '._('Problems');
				}
			}
			// Expand single problem.
			elseif ($i['expandproblem'] == SYSMAP_SINGLE_PROBLEM) {
				$msg = $i['problem_title'];
			}
			// Number of problems and expand most critical one.
			elseif ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER_CRITICAL) {
				$msg = $i['problem_title'];

				if ($i['problem'] > 1) {
					$msg .= "\n".$i['problem'].' '._('Problems');
				}
			}

			$info['info']['problem'] = [
				'msg' => $msg,
				'color' => getSelementLabelColor(true, !$i['problem_unack'])
			];
		}

		if (in_array($show_unack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH]) && $i['problem_unack']) {
			$info['info']['unack'] = [
				'msg' => $i['problem_unack'].' '._('Unacknowledged'),
				'color' => getSelementLabelColor(true, false)
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
			'msg' => $i['maintenance'].' '._('Maintenance'),
			'color' => 'EE9600'
		];
		$hasStatus = true;
	}

	if (!$hasStatus && !$hasProblem) {
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		$info['iconid'] = $selement['iconid_off'];
		$info['info']['ok'] = [
			'msg' => _('OK'),
			'color' => getSelementLabelColor(false, $i['ack'])
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
			// Number of problems.
			if ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER) {
				if ($i['problem'] == 1) {
					$msg = _('PROBLEM');
				}
				else {
					$msg = $i['problem'].' '._('Problems');
				}
			}
			// Expand single problem.
			elseif ($i['expandproblem'] == SYSMAP_SINGLE_PROBLEM) {
				$msg = $i['problem_title'];
			}
			// Number of problems and expand most critical one.
			elseif ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER_CRITICAL) {
				$msg = $i['problem_title'];

				if ($i['problem'] > 1) {
					$msg .= "\n".$i['problem'].' '._('Problems');
				}
			}

			$info['info']['problem'] = [
				'msg' => $msg,
				'color' => getSelementLabelColor(true, !$i['problem_unack'])
			];
		}

		if (in_array($show_unack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH]) && $i['problem_unack']) {
			$info['info']['unack'] = [
				'msg' => $i['problem_unack'].' '._('Unacknowledged'),
				'color' => getSelementLabelColor(true, false)
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

	if (!$hasStatus && !$hasProblem) {
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		$info['iconid'] = $selement['iconid_off'];
		$info['info']['ok'] = [
			'msg' => _('OK'),
			'color' => getSelementLabelColor(false, $i['ack'])
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
 * @param array $options
 * @param int   $options['severity_min']  Minimum severity, default value is maximal (Disaster)
 *
 * @return array
 */
function getSelementsInfo(array $sysmap, array $options = []) {
	if (!isset($options['severity_min'])) {
		$options['severity_min'] = TRIGGER_SEVERITY_NOT_CLASSIFIED;
	}

	$triggerIdToSelementIds = [];
	$subSysmapTriggerIdToSelementIds = [];
	$hostGroupIdToSelementIds = [];
	$hostIdToSelementIds = [];

	if ($sysmap['sysmapid']) {
		$iconMap = API::IconMap()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectMappings' => API_OUTPUT_EXTEND,
			'sysmapids' => $sysmap['sysmapid']
		]);
		$iconMap = reset($iconMap);
	}
	$hostsToGetInventories = [];

	$selements = $sysmap['selements'];
	$selementIdToSubSysmaps = [];
	foreach ($selements as $selementId => &$selement) {
		$selement['hosts'] = [];
		$selement['triggers'] = [];

		if ($selement['permission'] < PERM_READ) {
			continue;
		}

		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_MAP:
				$sysmapIds = [$selement['elements'][0]['sysmapid']];

				while (!empty($sysmapIds)) {
					$subSysmaps = API::Map()->get([
						'output' => ['sysmapid'],
						'selectSelements' => ['elementtype', 'elements', 'application'],
						'sysmapids' => $sysmapIds,
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
									$sysmapIds[] = $subSysmapSelement['elements'][0]['sysmapid'];
									break;
								case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
									$hostGroupIdToSelementIds[$subSysmapSelement['elements'][0]['groupid']][$selementId]
										= $selementId;
									break;
								case SYSMAP_ELEMENT_TYPE_HOST:
									$hostIdToSelementIds[$subSysmapSelement['elements'][0]['hostid']][$selementId]
										= $selementId;
									break;
								case SYSMAP_ELEMENT_TYPE_TRIGGER:
									foreach ($subSysmapSelement['elements'] as $element) {
										$subSysmapTriggerIdToSelementIds[$element['triggerid']][$selementId]
											= $selementId;
									}
									break;
							}
						}
					}
				}
				break;
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$hostGroupId = $selement['elements'][0]['groupid'];
				$hostGroupIdToSelementIds[$hostGroupId][$selementId] = $selementId;
				break;
			case SYSMAP_ELEMENT_TYPE_HOST:
				$hostId = $selement['elements'][0]['hostid'];
				$hostIdToSelementIds[$hostId][$selementId] = $selementId;

				/**
				 * If we have icon map applied, we need to get inventories for all hosts, where automatic icon
				 * selection is enabled.
				 */
				if ($sysmap['iconmapid'] && $selement['use_iconmap']) {
					$hostsToGetInventories[] = $hostId;
				}
				break;
			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				foreach ($selement['elements'] as $element) {
					$triggerIdToSelementIds[$element['triggerid']][$selementId] = $selementId;
				}
				break;
		}
	}
	unset($selement);

	// get host inventories
	if ($sysmap['iconmapid']) {
		$hostInventories = API::Host()->get([
			'output' => ['hostid'],
			'selectInventory' => API_OUTPUT_EXTEND,
			'hostids' => $hostsToGetInventories,
			'preservekeys' => true
		]);
	}

	$allHosts = [];
	if ($hostIdToSelementIds) {
		$hosts = API::Host()->get([
			'output' => ['name', 'status', 'maintenance_status', 'maintenanceid'],
			'hostids' => array_keys($hostIdToSelementIds),
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
	if ($hostGroupIdToSelementIds) {
		$hostsFromHostGroups = API::Host()->get([
			'output' => ['name', 'status', 'maintenance_status', 'maintenanceid'],
			'selectGroups' => ['groupid'],
			'groupids' => array_keys($hostGroupIdToSelementIds),
			'preservekeys' => true
		]);

		foreach ($hostsFromHostGroups as $hostId => $host) {
			foreach ($host['groups'] as $group) {
				$groupId = $group['groupid'];

				if (isset($hostGroupIdToSelementIds[$groupId])) {
					foreach ($hostGroupIdToSelementIds[$groupId] as $selementId) {
						$selement =& $selements[$selementId];

						$selement['hosts'][$hostId] = $hostId;

						// Add hosts to hosts_map for trigger selection.
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

	// Get triggers data, triggers from current map, select all.
	if ($triggerIdToSelementIds) {
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'status', 'value', 'priority', 'description', 'expression'],
			'selectHosts' => ['maintenance_status', 'maintenanceid'],
			'triggerids' => array_keys($triggerIdToSelementIds),
			'filter' => ['state' => null],
			'preservekeys' => true
		]);

		$monitored_triggers = API::Trigger()->get([
			'output' => [],
			'triggerids' => array_keys($triggers),
			'monitored' => true,
			'preservekeys' => true
		]);

		foreach ($triggers as $trigger) {
			if (!array_key_exists($trigger['triggerid'], $monitored_triggers)) {
				$trigger['status'] = TRIGGER_STATUS_DISABLED;
			}

			foreach ($triggerIdToSelementIds[$trigger['triggerid']] as $belongs_to_sel) {
				$selements[$belongs_to_sel]['triggers'][$trigger['triggerid']] = $trigger;
			}
		}
		unset($triggers, $monitored_triggers);
	}

	// triggers from submaps, skip dependent
	if ($subSysmapTriggerIdToSelementIds) {
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'status', 'value', 'priority', 'description', 'expression'],
			'triggerids' => array_keys($subSysmapTriggerIdToSelementIds),
			'filter' => ['state' => null],
			'skipDependent' => true,
			'only_true' => true,
			'preservekeys' => true
		]);

		$monitored_triggers = API::Trigger()->get([
			'output' => [],
			'triggerids' => array_keys($triggers),
			'monitored' => true,
			'preservekeys' => true
		]);

		foreach ($triggers as $trigger) {
			if (!array_key_exists($trigger['triggerid'], $monitored_triggers)) {
				$trigger['status'] = TRIGGER_STATUS_DISABLED;
			}

			foreach ($subSysmapTriggerIdToSelementIds[$trigger['triggerid']] as $belongs_to_sel) {
				$selements[$belongs_to_sel]['triggers'][$trigger['triggerid']] = $trigger;
			}
		}
		unset($triggers, $monitored_triggers);
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
			'output' => ['triggerid', 'status', 'value', 'priority', 'description', 'expression'],
			'selectHosts' => ['hostid'],
			'selectItems' => ['itemid'],
			'hostids' => array_keys($monitored_hostids),
			'filter' => ['state' => null],
			'monitored' => true,
			'skipDependent' => true,
			'only_true' => true,
			'preservekeys' => true
		]);

		foreach ($triggers as $trigger) {
			foreach ($trigger['hosts'] as $host) {
				if (array_key_exists($host['hostid'], $hostIdToSelementIds)) {
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

	// Get problems by triggerids.
	$triggerids = [];
	foreach ($selements as $selement) {
		foreach ($selement['triggers'] as $trigger) {
			if ($trigger['status'] == TRIGGER_STATUS_ENABLED) {
				$triggerids[$trigger['triggerid']] = true;
			}
		}
	}

	$problems = API::Problem()->get([
		'output' => ['eventid', 'objectid', 'name', 'acknowledged', 'clock', 'r_clock', 'severity'],
		'objectids' => array_keys($triggerids),
		'recent' => true,
		'suppressed' => ($sysmap['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_FALSE) ? false : null
	]);

	foreach ($selements as $snum => $selement) {
		foreach ($problems as $problem) {
			if (array_key_exists($problem['objectid'], $selement['triggers'])
					&& $options['severity_min'] <= $problem['severity']) {
				$selements[$snum]['triggers'][$problem['objectid']]['problems'][] = $problem;
			}
		}
	}

	$config = select_config();

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
		$info[$selementId]['aria_label'] = '';

		/*
		 * If user has no rights to see the details of particular selement, add only info that is needed to render map
		 * icons.
		 */
		if (PERM_READ > $selement['permission']) {
			switch ($selement['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_MAP:
					$info[$selementId] = getMapsInfo(['iconid_off' => $selement['iconid_off']], $i, null);
					break;

				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$info[$selementId] = getHostGroupsInfo(['iconid_off' => $selement['iconid_off']], $i, null);
					break;

				case SYSMAP_ELEMENT_TYPE_HOST:
					$info[$selementId] = getHostsInfo(['iconid_off' => $selement['iconid_off']], $i, null);
					break;

				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					$info[$selementId] = getTriggersInfo(['iconid_off' => $selement['iconid_off']], $i, null);
					break;

				case SYSMAP_ELEMENT_TYPE_IMAGE:
					$info[$selementId] = getImagesInfo(['iconid_off' => $selement['iconid_off']]);
					break;
			}

			continue;
		}

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

		$critical_problem = [];
		$trigger_order = ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER)
			? zbx_objectValues($selement['elements'], 'triggerid')
			: [];
		$lately_changed = 0;

		foreach ($selement['triggers'] as $trigger) {
			if ($trigger['status'] == TRIGGER_STATUS_DISABLED && $options['severity_min'] <= $trigger['priority']) {
				$i['trigger_disabled']++;
				continue;
			}

			if (array_key_exists('problems', $trigger)) {
				foreach ($trigger['problems'] as $problem) {
					if ($problem['r_clock'] == 0) {
						$i['problem']++;

						if ($problem['acknowledged'] == EVENT_NOT_ACKNOWLEDGED) {
							$i['problem_unack']++;
						}

						if (!$critical_problem || $critical_problem['severity'] < $problem['severity']) {
							$critical_problem = $problem;
						}
						elseif ($critical_problem['severity'] === $problem['severity']) {
							if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
								if ($problem['objectid'] === $critical_problem['objectid']
										&& $critical_problem['eventid'] < $problem['eventid']) {
									$critical_problem = $problem;
								}
								elseif (array_search($critical_problem['objectid'], $trigger_order)
										> array_search($problem['objectid'], $trigger_order)) {
									$critical_problem = $problem;
								}
							}
							elseif ($critical_problem['eventid'] < $problem['eventid']) {
								$critical_problem = $problem;
							}
						}
					}

					if ($problem['r_clock'] > $lately_changed) {
						$lately_changed = $problem['r_clock'];
					}
					elseif ($problem['clock'] > $lately_changed) {
						$lately_changed = $problem['clock'];
					}
				}
			}

			$i['latelyChanged'] |= ((time() - $lately_changed) < timeUnitToSeconds($config['blink_period']));
		}

		if ($critical_problem) {
			$i['priority'] = $critical_problem['severity'];
		}

		$i['ack'] = !$i['problem_unack'];

		// Number of problems.
		if ($sysmap['expandproblem'] == SYSMAP_PROBLEMS_NUMBER) {
			$i['expandproblem'] = SYSMAP_PROBLEMS_NUMBER;
		}
		// Expand single problem.
		elseif ($sysmap['expandproblem'] == SYSMAP_SINGLE_PROBLEM && $i['problem']) {
			$i['problem_title'] = $critical_problem['name'];
			$i['expandproblem'] = SYSMAP_SINGLE_PROBLEM;
		}
		// Number of problems and expand most critical one.
		elseif ($sysmap['expandproblem'] == SYSMAP_PROBLEMS_NUMBER_CRITICAL && $i['problem']) {
			$i['problem_title'] = $critical_problem['name'];
			$i['expandproblem'] = SYSMAP_PROBLEMS_NUMBER_CRITICAL;
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
				$info[$selementId] = getMapsInfo($selement, $i, $sysmap['show_unack']);
				break;

			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$info[$selementId] = getHostGroupsInfo($selement, $i, $sysmap['show_unack']);
				break;

			case SYSMAP_ELEMENT_TYPE_HOST:
				if ($i['maintenance'] == 1) {
					$mnt = get_maintenance_by_maintenanceid($allHosts[$last_hostid]['maintenanceid']);
					$i['maintenance_title'] = $mnt['name'];
				}

				$info[$selementId] = getHostsInfo($selement, $i, $sysmap['show_unack']);
				if ($sysmap['iconmapid'] && $selement['use_iconmap']) {
					$info[$selementId]['iconid'] = getIconByMapping($iconMap,
						$hostInventories[$selement['elements'][0]['hostid']]
					);
				}
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				foreach ($selement['triggers'] as $trigger) {
					foreach ($trigger['hosts'] as $host) {
						if (array_key_exists('maintenance_status', $host)
								&& $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
							$maintenance = get_maintenance_by_maintenanceid($host['maintenanceid']);
							$i['maintenance_title'] = $maintenance['name'];

							break;
						}
					}
				}

				$info[$selementId] = getTriggersInfo($selement, $i, $sysmap['show_unack']);
				break;

			case SYSMAP_ELEMENT_TYPE_IMAGE:
				$info[$selementId] = getImagesInfo($selement);
				break;
		}

		if ($i['problem'] > 0) {
			$info[$selementId]['aria_label'] = ($i['problem'] > 1)
				? _n('%1$s problem', '%1$s problems', $i['problem'])
				: $critical_problem['name'];
		}

		$info[$selementId]['problems_total'] = $i['problem'];
	}

	if ($sysmap['label_format'] == SYSMAP_LABEL_ADVANCED_OFF) {
		$hlabel = ($sysmap['label_type'] == MAP_LABEL_TYPE_NAME);
		$hglabel = ($sysmap['label_type'] == MAP_LABEL_TYPE_NAME);
		$tlabel = ($sysmap['label_type'] == MAP_LABEL_TYPE_NAME);
		$mlabel = ($sysmap['label_type'] == MAP_LABEL_TYPE_NAME);
	}
	else {
		$hlabel = ($sysmap['label_type_host'] == MAP_LABEL_TYPE_NAME);
		$hglabel = ($sysmap['label_type_hostgroup'] == MAP_LABEL_TYPE_NAME);
		$tlabel = ($sysmap['label_type_trigger'] == MAP_LABEL_TYPE_NAME);
		$mlabel = ($sysmap['label_type_map'] == MAP_LABEL_TYPE_NAME);
	}

	// get names if needed
	$elems = separateMapElements($sysmap);

	if ($elems['sysmaps'] && $mlabel) {
		$sysmapids = [];

		foreach ($elems['sysmaps'] as $selement) {
			if ($selement['permission'] >= PERM_READ) {
				$sysmapids[$selement['elements'][0]['sysmapid']] = true;
			}
		}

		$db_sysmaps = API::Map()->get([
			'output' => ['name'],
			'sysmapids' => array_keys($sysmapids),
			'preservekeys' => true
		]);

		foreach ($elems['sysmaps'] as $selement) {
			if ($selement['permission'] >= PERM_READ) {
				$info[$selement['selementid']]['name'] =
					array_key_exists($selement['elements'][0]['sysmapid'], $db_sysmaps)
						? $db_sysmaps[$selement['elements'][0]['sysmapid']]['name']
						: '';
			}
		}
	}

	if ($elems['hostgroups'] && $hglabel) {
		$groupids = [];

		foreach ($elems['hostgroups'] as $selement) {
			if ($selement['permission'] >= PERM_READ) {
				$groupids[$selement['elements'][0]['groupid']] = true;
			}
		}

		$db_groups = $groupids
			? API::HostGroup()->get([
				'output' => ['name'],
				'groupids' => array_keys($groupids),
				'preservekeys' => true
			])
			: [];

		foreach ($elems['hostgroups'] as $selement) {
			if ($selement['permission'] >= PERM_READ) {
				$info[$selement['selementid']]['name'] =
					array_key_exists($selement['elements'][0]['groupid'], $db_groups)
						? $db_groups[$selement['elements'][0]['groupid']]['name']
						: '';
			}
		}
	}

	if ($elems['triggers'] && $tlabel) {
		$selements = zbx_toHash($selements, 'selementid');
		foreach ($elems['triggers'] as $selementid => $selement) {
			foreach ($selement['elements'] as $element) {
				if ($selement['permission'] >= PERM_READ) {
					$trigger = array_key_exists($element['triggerid'], $selements[$selementid]['triggers'])
						? $selements[$selementid]['triggers'][$element['triggerid']]
						: null;
					$info[$selement['selementid']]['name'] = ($trigger != null)
						? CMacrosResolverHelper::resolveTriggerName($trigger)
						: '';
				}
			}
		}
	}

	if ($elems['hosts'] && $hlabel) {
		foreach ($elems['hosts'] as $selement) {
			if ($selement['permission'] >= PERM_READ) {
				$info[$selement['selementid']]['name'] = array_key_exists($selement['elements'][0]['hostid'], $allHosts)
					? $allHosts[$selement['elements'][0]['hostid']]['name']
					: [];
			}
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
function filterSysmapTriggers(array $selements, array $selementHostApplicationFilters,
		array $triggersFromMonitoredHosts, array $subSysmapTriggerIdToSelementIds) {
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
function getSelementHostApplicationFilters(array $selements, array $selementIdToSubSysmaps,
		array $hostsFromHostGroups) {
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
				if (array_key_exists($selementId, $selementIdToSubSysmaps)) {
					foreach ($selementIdToSubSysmaps[$selementId] as $subSysmap) {
						// add all filters set for host elements
						foreach ($subSysmap['selements'] as $subSysmapSelement) {
							if ($subSysmapSelement['elementtype'] != SYSMAP_ELEMENT_TYPE_HOST
									|| $subSysmapSelement['application'] === '') {

								continue;
							}

							$hostId = $subSysmapSelement['elements'][0]['hostid'];
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
							$hostGroupId = $hostGroupSelement['elements'][0]['groupid'];

							if (isset($hostIdsForHostGroupId[$hostGroupId])) {
								foreach ($hostIdsForHostGroupId[$hostGroupId] as $hostId) {
									$selementHostApplicationFilters[$selementId][$hostId][]
										= $hostGroupSelement['application'];
								}
							}
						}

						// Unset all application filters for hosts in host group selements without any filters.
						// This might reset application filters set by previous foreach.
						foreach ($hostGroupSelementsWithoutApplication AS $hostGroupSelement) {
							$hostGroupId = $hostGroupSelement['elements'][0]['groupid'];

							if (isset($hostIdsForHostGroupId[$hostGroupId])) {
								foreach ($hostIdsForHostGroupId[$hostGroupId] as $hostId) {
									unset($selementHostApplicationFilters[$selementId][$hostId]);
								}
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

		// If point is further than area diagonal, we should use calculations with width instead of height.
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
	if ($inventory['inventory']['inventory_mode'] != HOST_INVENTORY_DISABLED) {
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

/**
 * Get labels for map elements.
 *
 * @param array $map       Sysmap data array.
 * @param array $map_info  Array of selements (@see getSelementsInfo).
 *
 * @return array
 */
function getMapLabels($map, $map_info) {
	$selements = $map['selements'];

	// Collect labels for each map element and apply appropriate values.
	$labels = [];
	foreach ($selements as $selementId => $selement) {
		if ($selement['permission'] < PERM_READ) {
			continue;
		}
		elseif ($selement['label_type'] == MAP_LABEL_TYPE_NOTHING) {
			$labels[$selementId] = [];
			continue;
		}

		$label_lines = [];
		$msgs = explode("\n", $selement['label']);
		foreach ($msgs as $msg) {
			$label_lines[] = ['content' => $msg];
		}

		$status_lines = [];
		$element_info = $map_info[$selementId];
		if (array_key_exists('info', $element_info)) {
			foreach (['problem', 'unack', 'maintenance', 'ok', 'status'] as $caption) {
				if (array_key_exists($caption, $element_info['info'])
						&& $element_info['info'][$caption]['msg'] !== '') {
					$msgs = explode("\n", $element_info['info'][$caption]['msg']);
					foreach ($msgs as $msg) {
						$status_lines[] = [
							'content' => $msg,
							'attributes' => [
								'fill' => '#'.$element_info['info'][$caption]['color']
							]
						];
					}
				}
			}
		}

		if ($selement['label_type'] == MAP_LABEL_TYPE_IP && $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
			$label = array_merge([['content' => $selement['label']]], $status_lines);
		}
		elseif ($selement['label_type'] == MAP_LABEL_TYPE_STATUS) {
			$label = $status_lines;
		}
		elseif ($selement['label_type'] == MAP_LABEL_TYPE_NAME) {
			$label = array_merge([['content' => $element_info['name']]], $status_lines);
		}
		else {
			$label = array_merge($label_lines, $status_lines);
		}

		$labels[$selementId] = $label;
	}

	return $labels;
}

/**
 * Get map element highlights (information about elements with marks or background).
 *
 * @param array $map       Sysmap data array.
 * @param array $map_info  Array of selements (@see getSelementsInfo).
 *
 * @return array
 */
function getMapHighligts(array $map, array $map_info) {
	$highlights = [];

	foreach ($map['selements'] as $id => $selement) {
		if ((($map['highlight'] % 2) != SYSMAP_HIGHLIGHT_ON) || (array_key_exists('elementtype', $selement)
				&& $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
				&& array_key_exists('elementsubtype', $selement)
				&& $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS)) {
			$highlights[$id] = null;
			continue;
		}

		$hl_color = null;
		$st_color = null;
		$element_info = $map_info[$id];

		switch ($element_info['icon_type']) {
			case SYSMAP_ELEMENT_ICON_ON:
				$hl_color = getSeverityColor($element_info['priority']);
				break;

			case SYSMAP_ELEMENT_ICON_MAINTENANCE:
				$st_color = 'FF9933';
				break;

			case SYSMAP_ELEMENT_ICON_DISABLED:
				$st_color = '999999';
				break;
		}

		if (array_key_exists('elementtype', $selement)
				&& ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
				|| $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) && $hl_color !== null) {
			$st_color = null;
		}
		elseif ($st_color !== null) {
			$hl_color = null;
		}

		$highlights[$id] = [
			'st' =>  $st_color,
			'hl' => $hl_color,
			'ack' => ($hl_color !== null && array_key_exists('ack', $element_info) && $element_info['ack'])
		];
	}

	return $highlights;
}

/**
 * Get trigger data for all linktriggers.
 *
 * @param array $sysmap
 * @param array $sysmap['show_suppressed']  Whether to show suppressed problems.
 * @param array $sysmap['show_unack']       Property specified in sysmap's 'Problem display' field. Used to determine
 *                                          whether to show unacknowledged problems only.
 * @param array $options                    Options used to retrieve actions.
 * @param int   $options['severity_min']    Minimal severity used.
 *
 * @return array
 */
function getMapLinktriggerInfo($sysmap, $options) {
	if (!array_key_exists('severity_min', $options)) {
		$options['severity_min'] = TRIGGER_SEVERITY_NOT_CLASSIFIED;
	}

	$triggerids = [];

	foreach ($sysmap['links'] as $link) {
		foreach ($link['linktriggers'] as $linktrigger) {
			$triggerids[$linktrigger['triggerid']] = true;
		}
	}

	$trigger_options = [
		'output' => ['status', 'value', 'priority'],
		'triggerids' => array_keys($triggerids),
		'monitored' => true,
		'preservekeys' => true
	];

	$problem_options = [
		'show_suppressed' => $sysmap['show_suppressed'],
		'acknowledged' => ($sysmap['show_unack'] == EXTACK_OPTION_UNACK) ? false : null
	];

	return getTriggersWithActualSeverity($trigger_options, $problem_options);
}

/**
 * Get map selement label color based on problem and acknowledgement state
 * as well as taking custom event status color settings into account.
 *
 * @throws APIException if the given table does not exist
 *
 * @param bool $is_problem
 * @param bool $is_ack
 *
 * @return string
 */
function getSelementLabelColor($is_problem, $is_ack) {
	static $config = null;
	static $schema = null;

	if ($config === null) {
		$config = select_config();
		$schema = DB::getSchema('config');
	}

	$ack_unack = $is_ack ? 'ack' : 'unack';
	$ok_problem = $is_problem ? 'problem' : 'ok';

	if ($config['custom_color'] === '1') {
		return $config[$ok_problem . '_' . $ack_unack . '_color'];
	}

	return $schema['fields'][$ok_problem . '_' . $ack_unack . '_color']['default'];
}
