<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
 * @param array  $sysmap
 * @param array  $sysmap['selements']
 * @param array  $options                   Options used to retrieve actions.
 * @param int    $options['severity_min']   Minimal severity used.
 * @param int    $options['unique_id']
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

		if (array_key_exists('unique_id', $options)) {
			$elem['unique_id'] = $options['unique_id'];
		}

		$hostid = ($elem['elementtype_orig'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
				&& $elem['elementsubtype_orig'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS)
			? $elem['elements'][0]['hostid']
			: 0;

		$map = CMenuPopupHelper::getMapElement($sysmap['sysmapid'], $elem, $severity_min, $hostid);

		$actions[$selementid] = json_encode($map);
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
			'groupids' => $groupids,
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
 * Returns selement icon rendering parameters.
 *
 * @param array    $i
 * @param int      $i['elementtype']         Element type. Possible values:
 *                                           SYSMAP_ELEMENT_TYPE_HOST, SYSMAP_ELEMENT_TYPE_MAP,
 *                                           SYSMAP_ELEMENT_TYPE_TRIGGER, SYSMAP_ELEMENT_TYPE_HOST_GROUP,
 *                                           SYSMAP_ELEMENT_TYPE_IMAGE.
 * @param int      $i['disabled']            The number of disabled hosts.
 * @param int      $i['maintenance']         The number of hosts in maintenance.
 * @param int      $i['problem']             The number of problems.
 * @param int      $i['problem_unack']       The number of unacknowledged problems.
 * @param int      $i['iconid_off']          Icon ID for element without problems.
 * @param int      $i['iconid_on']           Icon ID for element with problems.
 * @param int      $i['iconid_maintenance']  Icon ID for element with hosts in maintenance.
 * @param int      $i['iconid_disabled']     Icon ID for disabled element.
 * @param bool     $i['latelyChanged']       Whether trigger status has changed recently.
 * @param int      $i['priority']            Problem severity. Possible values:
 *                                           TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION,
 *                                           TRIGGER_SEVERITY_WARNING, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH,
 *                                           TRIGGER_SEVERITY_DISASTER.
 * @param int      $i['expandproblem']       Map "Display problems" option. Possible values:
 *                                           SYSMAP_SINGLE_PROBLEM, SYSMAP_PROBLEMS_NUMBER,
 *                                           SYSMAP_PROBLEMS_NUMBER_CRITICAL.
 * @param string   $i['problem_title']       (optional) The name of the most critical problem.
 * @param int      $host_count               (optional) Number of unique hosts that the current selement is related to.
 * @param int|null $show_unack               (optional) Map "Problem display" option. Possible values:
 *                                           EXTACK_OPTION_ALL, EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH.
 *
 * @return array
 */
function getSelementInfo(array $i, int $host_count = 0, ?int $show_unack = null): array {
	if ($i['elementtype'] == SYSMAP_ELEMENT_TYPE_IMAGE) {
		return [
			'iconid' => $i['iconid_off'],
			'icon_type' => SYSMAP_ELEMENT_ICON_OFF,
			'name' => _('Image'),
			'latelyChanged' => false
		];
	}

	$info = [
		'latelyChanged' => $i['latelyChanged'],
		'ack' => !$i['problem_unack'],
		'priority' => $i['priority'],
		'info' => [],
		'iconid' => $i['iconid_off'],
		'aria_label' => ''
	];

	if ($i['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST && $i['disabled']) {
		$info['iconid'] = $i['iconid_disabled'];
		$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
		$info['info']['status'] = [
			'msg' => _('Disabled'),
			'color' => '960000'
		];

		return $info;
	}

	$has_problem = false;

	if ($i['problem']) {
		if ($show_unack == EXTACK_OPTION_ALL || $show_unack == EXTACK_OPTION_BOTH) {
			$msg = '';

			// Expand single problem.
			if ($i['expandproblem'] == SYSMAP_SINGLE_PROBLEM) {
				$msg = ($i['problem'] == 1) ? $i['problem_title'] : _n('%1$s problem', '%1$s problems', $i['problem']);
			}
			// Number of problems.
			elseif ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER) {
				$msg = _n('%1$s problem', '%1$s problems', $i['problem']);
			}
			// Number of problems and expand most critical one.
			elseif ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER_CRITICAL) {
				$msg = $i['problem_title'];

				if ($i['problem'] > 1) {
					$msg .= "\n"._n('%1$s problem', '%1$s problems', $i['problem']);
				}
			}

			$info['info']['problem'] = [
				'msg' => $msg,
				'color' => getSelementLabelColor(true, !$i['problem_unack'])
			];
		}

		if ($i['problem_unack'] && ($show_unack == EXTACK_OPTION_UNACK || $show_unack == EXTACK_OPTION_BOTH)) {
			$msg = '';

			if ($show_unack == EXTACK_OPTION_UNACK) {
				if ($i['expandproblem'] == SYSMAP_SINGLE_PROBLEM) {
					$msg = ($i['problem_unack'] == 1)
						? $i['problem_title']
						: _n('%1$s unacknowledged problem', '%1$s unacknowledged problems', $i['problem_unack']);
				}
				elseif ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER) {
					$msg = _n('%1$s unacknowledged problem', '%1$s unacknowledged problems', $i['problem_unack']);
				}
				elseif ($i['expandproblem'] == SYSMAP_PROBLEMS_NUMBER_CRITICAL) {
					$msg = $i['problem_title'];

					if ($i['problem_unack'] > 1) {
						$msg .= "\n".
							_n('%1$s unacknowledged problem', '%1$s unacknowledged problems', $i['problem_unack']);
					}
				}
			}
			elseif ($show_unack == EXTACK_OPTION_BOTH) {
				$msg = _n('%1$s unacknowledged problem', '%1$s unacknowledged problems', $i['problem_unack']);
			}

			$info['info']['unack'] = [
				'msg' => $msg,
				'color' => getSelementLabelColor(true, false)
			];
		}

		// Set element to problem state if it has problem events.
		if ($info['info']) {
			$info['iconid'] = $i['iconid_on'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
			$has_problem = true;
		}

		$info['aria_label'] = ($i['problem'] > 1)
			? _n('%1$s problem', '%1$s problems', $i['problem'])
			: $i['problem_title'];
	}

	$all_hosts_in_maintenance = $i['maintenance'] && ($host_count == $i['disabled'] + $i['maintenance']);

	if ($i['maintenance']) {
		if (!$has_problem && $all_hosts_in_maintenance) {
			$info['iconid'] = $i['iconid_maintenance'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
		}

		$info['info']['maintenance'] = [
			'msg' => ($i['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST)
				? _('In maintenance')
				: _n('%1$s host in maintenance', '%1$s hosts in maintenance', $i['maintenance']),
			'color' => 'EE9600'
		];
	}

	if (!$has_problem) {
		if (!$all_hosts_in_maintenance) {
			$info['iconid'] = $i['iconid_off'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
		}

		$info['info']['ok'] = [
			'msg' => _('OK'),
			'color' => getSelementLabelColor(false, $info['ack'])
		];
	}

	return $info;
}

/**
 * Function to extract all resource IDs from map and its nested maps.
 */
function getSysmapResourceIds(array $selements, array &$sysmaps_data, bool $collect_iconmap_hosts = false): array {
	$hostids = [];
	$triggerids = [];
	$hostgroupids = [];
	$hosts_to_get_inventories = [];

	foreach ($selements as $selement) {
		if ($selement['permission'] < PERM_READ) {
			continue;
		}

		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_MAP:
				$lookup_sysmapids = [$selement['elements'][0]['sysmapid']];

				while ($lookup_sysmapids) {
					$nested_sysmaps = API::Map()->get([
						'output' => ['sysmapid', 'name'],
						'selectSelements' => ['elementtype', 'elements', 'tags', 'evaltype', 'permission'],
						'sysmapids' => $lookup_sysmapids,
						'preservekeys' => true
					]);

					$sysmaps_data += $nested_sysmaps;
					$lookup_sysmapids = [];

					foreach ($nested_sysmaps as $nested_sysmap) {
						foreach ($nested_sysmap['selements'] as $nested_sysmap_selement) {
							if ($nested_sysmap_selement['permission'] < PERM_READ) {
								continue;
							}

							switch ($nested_sysmap_selement['elementtype']) {
								case SYSMAP_ELEMENT_TYPE_MAP:
									$sysmapid = $nested_sysmap_selement['elements'][0]['sysmapid'];

									if (!array_key_exists($sysmapid, $sysmaps_data)) {
										$lookup_sysmapids[] = $sysmapid;
									}
									break;

								case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
									$groupid = $nested_sysmap_selement['elements'][0]['groupid'];
									$hostgroupids[$groupid] = $groupid;
									break;

								case SYSMAP_ELEMENT_TYPE_HOST:
									$hostid = $nested_sysmap_selement['elements'][0]['hostid'];
									$hostids[$hostid] = $hostid;
									break;

								case SYSMAP_ELEMENT_TYPE_TRIGGER:
									foreach ($nested_sysmap_selement['elements'] as $element) {
										$triggerid = $element['triggerid'];
										$triggerids[$triggerid] = $triggerid;
									}
									break;
							}
						}
					}
				}
				break;

			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$groupid = $selement['elements'][0]['groupid'];
				$hostgroupids[$groupid] = $groupid;
				break;

			case SYSMAP_ELEMENT_TYPE_HOST:
				$hostid = $selement['elements'][0]['hostid'];
				$hostids[$hostid] = $hostid;

				if ($collect_iconmap_hosts && $selement['use_iconmap']) {
					$hosts_to_get_inventories[] = $hostid;
				}
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				foreach ($selement['elements'] as $element) {
					$triggerid = $element['triggerid'];
					$triggerids[$triggerid] = $triggerid;
				}
				break;
		}
	}

	return [
		'hostids' => $hostids,
		'triggerids' => $triggerids,
		'hostgroupids' => $hostgroupids,
		'hosts_to_get_inventories' => $hosts_to_get_inventories
	];
};

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
function getSelementsInfo(array $sysmap, array $options = []): array {
	if (!array_key_exists('severity_min', $options)) {
		$options['severity_min'] = TRIGGER_SEVERITY_NOT_CLASSIFIED;
	}

	$sysmaps_data = [];

	[
		'hostids' => $selement_hostids,
		'triggerids' => $selement_triggerids,
		'hostgroupids' => $selement_hostgroupids,
		'hosts_to_get_inventories' => $hosts_to_get_inventories
	] = getSysmapResourceIds($sysmap['selements'], $sysmaps_data, $sysmap['iconmapid'] != 0);

	// Prepare hosts data.
	$selement_hosts = $selement_hostids
		? API::Host()->get([
			'output' => ['name', 'status', 'maintenance_status'],
			'hostids' => $selement_hostids,
			'preservekeys' => true
		])
		: [];

	$selement_hostgroup_hosts = $selement_hostgroupids
		? API::Host()->get([
			'output' => ['name', 'status', 'maintenance_status'],
			'selectHostGroups' => ['groupid'],
			'groupids' => $selement_hostgroupids,
			'preservekeys' => true
		])
		: [];

	$hosts = $selement_hostgroup_hosts + $selement_hosts;

	$hosts_by_groupids = array_fill_keys($selement_hostgroupids, []);
	foreach ($selement_hostgroup_hosts as $host) {
		foreach ($host['hostgroups'] as $group) {
			$groupid = $group['groupid'];
			$hostid = $host['hostid'];

			$hosts_by_groupids[$groupid][$hostid] = $hostid;
		}
	}
	unset($selement_hostgroup_hosts, $selement_hosts);

	// Prepare triggers data.
	$selement_triggers = $selement_triggerids
		? API::Trigger()->get([
			'output' => ['triggerid', 'status', 'value', 'priority', 'description', 'expression'],
			'selectHosts' => ['hostid', 'status', 'maintenance_status'],
			'triggerids' => $selement_triggerids,
			'filter' => ['state' => null],
			'preservekeys' => true
		])
		: [];

	$monitored_selement_triggers = API::Trigger()->get([
		'output' => [],
		'triggerids' => array_keys($selement_triggers),
		'monitored' => true,
		'skipDependent' => true,
		'preservekeys' => true
	]);

	foreach (array_diff_key($selement_triggers, $monitored_selement_triggers) as $triggerid => $trigger) {
		$selement_triggers[$triggerid]['status'] = TRIGGER_STATUS_DISABLED;
	}
	unset($monitored_selement_triggers);

	$monitored_hosts = array_filter($hosts, function ($host) {
		return $host['status'] == HOST_STATUS_MONITORED;
	});

	$triggers = $monitored_hosts
		? API::Trigger()->get([
			'output' => ['triggerid', 'status', 'value', 'priority', 'description', 'expression'],
			'selectHosts' => ['hostid', 'status', 'maintenance_status'],
			'selectItems' => ['itemid'],
			'hostids' => array_keys($monitored_hosts),
			'filter' => ['state' => null],
			'monitored' => true,
			'only_true' => true,
			'skipDependent' => true,
			'preservekeys' => true
		])
		: [];

	$triggers = $triggers + $selement_triggers;

	$triggers_by_hostids = array_fill_keys(array_keys($hosts), []);
	foreach ($triggers as $trigger) {
		foreach ($trigger['hosts'] as $host) {
			$triggerid = $trigger['triggerid'];
			$hostid = $host['hostid'];

			$triggers_by_hostids[$hostid][$triggerid] = $triggerid;
		}
	}

	unset($monitored_hosts, $selement_triggers);

	// Prepare problems data.
	$problems = API::Problem()->get([
		'output' => ['eventid', 'objectid', 'name', 'acknowledged', 'clock', 'r_clock', 'severity'],
		'selectTags' => ['tag', 'value'],
		'objectids' => array_keys($triggers),
		'acknowledged' => $sysmap['show_unack'] == EXTACK_OPTION_UNACK ? false : null,
		'severities' => range($options['severity_min'], TRIGGER_SEVERITY_COUNT - 1),
		'suppressed' => $sysmap['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_FALSE ? false : null,
		'symptom' => false,
		'recent' => true
	]);

	$problems_by_trigger = array_fill_keys(array_keys($triggers), []);
	foreach ($problems as $problem) {
		$problems_by_trigger[$problem['objectid']][] = $problem;
	}

	// Assign direct hosts, triggers and problems to all sysmap elements. Both the opened and nested maps are processed.
	$all_sysmaps = [0 => $sysmap] + $sysmaps_data;

	foreach ($all_sysmaps as &$_sysmap) {
		foreach ($_sysmap['selements'] as &$selement) {
			$selement['triggers'] = [];
			$selement['hosts'] = [];

			if ($selement['permission'] < PERM_READ) {
				continue;
			}

			// Assign selected triggers and problems back to the sysmap elements they origin from.
			switch ($selement['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					$triggerids = array_column($selement['elements'], 'triggerid', 'triggerid');
					break;

				case SYSMAP_ELEMENT_TYPE_HOST:
					$triggerids = $triggers_by_hostids[$selement['elements'][0]['hostid']];
					break;

				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$triggerids = [];
					foreach ($hosts_by_groupids[$selement['elements'][0]['groupid']] as $hostid) {
						if (array_key_exists($hostid, $triggers_by_hostids)) {
							$triggerids += $triggers_by_hostids[$hostid];
						}
					}
					break;

				default:
					$triggerids = [];
					break;
			}

			$selement['triggers'] = $triggerids ? array_intersect_key($triggers, $triggerids) : [];

			foreach ($selement['triggers'] as &$trigger) {
				$trigger['problems'] = array_key_exists($trigger['triggerid'], $problems_by_trigger)
					? $problems_by_trigger[$trigger['triggerid']]
					: [];

				if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST
						|| $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP) {
					$trigger['problems'] = getProblemsMatchingTags($trigger['problems'], $selement['tags'],
						$selement['evaltype']
					);
				}
			}
			unset($trigger);

			// Assign selected hosts back to the sysmap elements they origin from.
			$selement['hosts'] = getElementHosts($selement, $sysmaps_data, $hosts_by_groupids);
		}
		unset($selement);
	}
	unset($_sysmap);

	$sysmap = $all_sysmaps[0];
	$sysmaps_data = array_slice($all_sysmaps, 1, null, true);
	unset($all_sysmaps);

	$icon_map = $sysmap['sysmapid']
		? API::IconMap()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectMappings' => API_OUTPUT_EXTEND,
			'sysmapids' => $sysmap['sysmapid']
		])
		: [];
	$icon_map = reset($icon_map);

	// Get host inventories.
	$host_inventories = $hosts_to_get_inventories
		? API::Host()->get([
			'output' => ['hostid', 'inventory_mode'],
			'selectInventory' => API_OUTPUT_EXTEND,
			'hostids' => $hosts_to_get_inventories,
			'preservekeys' => true
		])
		: [];

	// Make selement info.
	$info = [];

	foreach ($sysmap['selements'] as $selement) {
		$selementid = $selement['selementid'];
		$selement_info = [
			'elementtype' => $selement['elementtype'],
			'disabled' => 0,
			'maintenance' => 0,
			'expandproblem' => $sysmap['expandproblem'],
			'latelyChanged' => false,
			'problem_unack' => [],
			'priority' => 0,
			'problem' => []
		];

		/*
		 * If user has no rights to see the details of particular selement, add only info that is needed to render map
		 * icons.
		 */
		if (PERM_READ > $selement['permission']) {
			$info[$selementid] = getSelementInfo($selement_info + ['iconid_off' => $selement['iconid_off']]);

			continue;
		}

		$host_count = count($selement['hosts']);

		if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER
				|| $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) {
			$trigger_hosts = [];
			foreach ($selement['triggers'] as $trigger) {
				foreach ($trigger['hosts'] as $host) {
					if (!array_key_exists($host['hostid'], $trigger_hosts)
							&& !array_key_exists($host['hostid'], $selement['hosts'])) {
						$trigger_hosts[$host['hostid']] = true;
						$host_count++;

						if ($host['status'] == HOST_STATUS_MONITORED
								&& $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON
								&& ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER
									|| ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP
										&& array_key_exists(SYSMAP_ELEMENT_TYPE_TRIGGER, $trigger['source'])))) {
							$selement_info['maintenance']++;
						}
					}
				}
			}
		}

		foreach ($selement['hosts'] as $hostid) {
			$host = $hosts[$hostid];

			if ($host['status'] == HOST_STATUS_NOT_MONITORED) {
				$selement_info['disabled']++;
			}
			elseif ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$selement_info['maintenance']++;
			}
		}

		$selement_problem_summary = countSelementProblems($selement, $sysmaps_data);

		$selement_info['problem'] = count($selement_problem_summary['problem']);
		$selement_info['problem_unack'] = count($selement_problem_summary['problem_unack']);
		$selement_info['latelyChanged'] = $selement_problem_summary['latelyChanged'];
		$selement_info['priority'] = $selement_problem_summary['priority'];
		$selement_info['problem_title'] = $selement_problem_summary['problem_title'];

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

		$selement_info['iconid_off'] = $selement['iconid_off'];
		$selement_info['iconid_on'] = $selement['iconid_on'];
		$selement_info['iconid_maintenance'] = $selement['iconid_maintenance'];
		$selement_info['iconid_disabled'] = $selement['iconid_disabled'];

		$info[$selementid] = getSelementInfo($selement_info, $host_count, $sysmap['show_unack']);

		if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST && $sysmap['iconmapid'] && $selement['use_iconmap']) {
			$host_inventory = $host_inventories[$selement['elements'][0]['hostid']];
			$info[$selementid]['iconid'] = getIconByMapping($icon_map, $host_inventory);
		}

		$info[$selementid]['problems_total'] = $selement_info['problem'];
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

	// Resolve map names in selement labels.
	if ($elems['sysmaps'] && $mlabel) {
		foreach ($elems['sysmaps'] as $selement) {
			$selementid = $selement['selementid'];
			$sysmapid = $selement['elements'][0]['sysmapid'];

			if ($selement['permission'] >= PERM_READ) {
				$info[$selementid]['name'] = array_key_exists($sysmapid, $sysmaps_data)
					? $sysmaps_data[$sysmapid]['name']
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
		$selements = array_column($sysmap['selements'], null, 'selementid');

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
				$hostid = $selement['elements'][0]['hostid'];
				$info[$selement['selementid']]['name'] = array_key_exists($hostid, $hosts)
					? $hosts[$hostid]['name']
					: [];
			}
		}
	}

	return $info;
}

function getElementHosts($selement, $sysmaps_data, $hosts_by_groupids) {
	$host_ids = [];

	if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
		$hostid = $selement['elements'][0]['hostid'];
		$host_ids[$hostid] = $hostid;
	}
	elseif ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP) {
		$groupid = $selement['elements'][0]['groupid'];
		$host_ids = $hosts_by_groupids[$groupid];
	}
	elseif ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) {
		$sysmapid = $selement['elements'][0]['sysmapid'];

		if (array_key_exists($sysmapid, $sysmaps_data)) {
			foreach ($sysmaps_data[$sysmapid]['selements'] as $nested_element) {
				$host_ids += getElementHosts($nested_element, $sysmaps_data, $hosts_by_groupids);
			}
		}
	}

	return $host_ids;
}

function countSelementProblems(array $selement, array &$sysmaps_data): array {
	if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) {
		return countNestedMapSelementProblems($selement, $sysmaps_data);
	}

	$trigger_order = ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER)
		? array_column($selement['elements'], 'triggerid')
		: [];
	$critical_problem = [];
	$lately_changed = 0;
	$selement_problem_summary = defaultProblemSummary();

	foreach ($selement['triggers'] as $trigger) {
		if ($trigger['status'] == TRIGGER_STATUS_DISABLED) {
			continue;
		}

		foreach ($trigger['problems'] as $problem) {
			if ($problem['r_clock'] == 0) {
				$eventid = $problem['eventid'];
				$selement_problem_summary['problem'][$eventid] = true;

				if ($problem['acknowledged'] == EVENT_NOT_ACKNOWLEDGED) {
					$selement_problem_summary['problem_unack'][$eventid] = true;
				}

				if (!$critical_problem || $critical_problem['severity'] < $problem['severity']) {
					$critical_problem = $problem;
				}
				elseif ($critical_problem['severity'] === $problem['severity']) {
					if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
						if ($critical_problem['objectid'] === $problem['objectid']
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

		if ((time() - $lately_changed) < timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::BLINK_PERIOD))) {
			$selement_problem_summary['latelyChanged'] = true;
		}
	}

	if ($critical_problem) {
		$selement_problem_summary['priority'] = $critical_problem['severity'];
		$selement_problem_summary['problem_title'] = $critical_problem['name'];
		$selement_problem_summary['problem_eventid'] = $critical_problem['eventid'];
	}

	return $selement_problem_summary;
}

function countNestedMapSelementProblems(array $selement, array &$sysmaps_data): array {
	$sysmapid = $selement['elements'][0]['sysmapid'];

	if (!array_key_exists($sysmapid, $sysmaps_data)) {
		return defaultProblemSummary();
	}
	elseif (!array_key_exists('selement_problem_summary', $sysmaps_data[$sysmapid])) {
		$selement_problem_summary = defaultProblemSummary();

		foreach ($sysmaps_data[$sysmapid]['selements'] as $nested_sysmap_element) {
			$nested_problem_summary = countSelementProblems($nested_sysmap_element, $sysmaps_data);

			if ($selement_problem_summary == null) {
				$selement_problem_summary = $nested_problem_summary;
			}
			else {
				$selement_problem_summary['problem'] += $nested_problem_summary['problem'];
				$selement_problem_summary['problem_unack'] += $nested_problem_summary['problem_unack'];
				$selement_problem_summary['latelyChanged'] |= $nested_problem_summary['latelyChanged'];

				if ($nested_problem_summary['priority'] > $selement_problem_summary['priority']) {
					$selement_problem_summary['priority'] = $nested_problem_summary['priority'];
					$selement_problem_summary['problem_title'] = $nested_problem_summary['problem_title'];
					$selement_problem_summary['problem_eventid'] = $nested_problem_summary['problem_eventid'];
				}
				elseif ($nested_problem_summary['priority'] == $selement_problem_summary['priority']
						&& $nested_problem_summary['problem_eventid'] > $selement_problem_summary['problem_eventid']) {
					$selement_problem_summary['problem_title'] = $nested_problem_summary['problem_title'];
					$selement_problem_summary['problem_eventid'] = $nested_problem_summary['problem_eventid'];
				}
			}
		}

		$sysmaps_data[$sysmapid]['selement_problem_summary'] = $selement_problem_summary;
	}

	return $sysmaps_data[$sysmapid]['selement_problem_summary'];
}

function defaultProblemSummary(): array {
	return [
		'problem' => [],
		'problem_unack' => [],
		'latelyChanged' => false,
		'priority' => 0,
		'problem_title' => null,
		'problem_eventid' => null
	];
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
 * @param array $icon_map
 * @param array $host
 * @param int   $host['inventory_mode']
 * @param array $host['inventory']
 *
 * @return int
 */
function getIconByMapping(array $icon_map, array $host) {
	if ($host['inventory_mode'] == HOST_INVENTORY_DISABLED) {
		return $icon_map['default_iconid'];
	}

	$inventories = getHostInventories();

	foreach ($icon_map['mappings'] as $mapping) {
		try {
			$expr = new CGlobalRegexp($mapping['expression']);
			if ($expr->match($host['inventory'][$inventories[$mapping['inventory_link']]['db_field']])) {
				return $mapping['iconid'];
			}
		}
		catch(Exception $e) {
			continue;
		}
	}

	return $icon_map['default_iconid'];
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
				$hl_color = CSeverityHelper::getColor((int) $element_info['priority']);
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
 * @param array $sysmap['links']            Map element link options.
 * @param array $sysmap['show_suppressed']  Whether to show suppressed problems.
 * @param array $sysmap['show_unack']       Property specified in sysmap's 'Problem display' field. Used to determine
 *                                          whether to show unacknowledged problems only.
 * @param array $options                    Options used to retrieve actions.
 * @param int   $options['severity_min']    Minimal severity used.
 *
 * @return array
 */
function getMapLinkTriggerInfo($sysmap, $options) {
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
	static $schema = null;

	if ($schema === null) {
		$schema = DB::getSchema('config');
	}

	if ($is_problem) {
		$param = $is_ack ? CSettingsHelper::PROBLEM_ACK_COLOR : CSettingsHelper::PROBLEM_UNACK_COLOR;
	}
	else {
		$param = $is_ack ? CSettingsHelper::OK_ACK_COLOR : CSettingsHelper::OK_UNACK_COLOR;
	}

	if (CSettingsHelper::get(CSettingsHelper::CUSTOM_COLOR) === '1') {
		return CSettingsHelper::get($param);
	}

	return $schema['fields'][$param]['default'];
}

/**
 * Filter problems by given tags.
 *
 * @param array  $problems
 * @param array  $problems[]['tags']
 * @param string $problems[]['tags'][]['tag']
 * @param string $problems[]['tags'][]['value']
 * @param array  $filter_tags
 * @param string $filter_tags[]['tag']
 * @param string $filter_tags[]['value']
 * @param int    $filter_tags[]['operator']
 * @param int    $evaltype
 *
 * @return array
 */
function getProblemsMatchingTags(array $problems, array $filter_tags, int $evaltype): array {
	if (!$problems) {
		return [];
	}

	$tags = [];
	foreach ($filter_tags as $tag) {
		$tags[$tag['tag']][] = $tag;
	}

	$filtered_problems = [];
	foreach ($problems as $problem) {
		$matching_tags = array_fill_keys(array_keys($tags), 0);
		array_walk($matching_tags, function (&$match, $key) use ($tags, $problem) {
			foreach ($tags[$key] as $tag) {
				if (checkIfProblemTagsMatches($tag, $problem['tags'])) {
					$match = 1;
					break;
				}
			}
		});

		$matching_tags = array_flip($matching_tags);
		if ($evaltype == TAG_EVAL_TYPE_OR && array_key_exists(1, $matching_tags)) {
			$filtered_problems[] = $problem;
		}
		elseif ($evaltype == TAG_EVAL_TYPE_AND_OR && !array_key_exists(0, $matching_tags)) {
			$filtered_problems[] = $problem;
		}
	}

	return $filtered_problems;
}

/**
 * Check if $filter_tag matches one of tags in $tags array.
 *
 * @param array  $filter_tag
 * @param string $filter_tag['tag']
 * @param string $filter_tag['value']
 * @param int    $filter_tag['operator']
 * @param array  $tags
 * @param string $tags[]['tag']
 * @param string $tags[]['value']
 *
 * @return bool
 */
function checkIfProblemTagsMatches(array $filter_tag, array $tags): bool {
	if (in_array($filter_tag['operator'], [TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_NOT_EXISTS])
			&& !$tags) {
		return true;
	}

	if ($filter_tag['operator'] == TAG_OPERATOR_NOT_LIKE && $filter_tag['value'] === '') {
		$filter_tag['operator'] = TAG_OPERATOR_NOT_EXISTS;
	}
	elseif ($filter_tag['operator'] == TAG_OPERATOR_LIKE && $filter_tag['value'] === '') {
		$filter_tag['operator'] = TAG_OPERATOR_EXISTS;
	}

	switch ($filter_tag['operator']) {
		case TAG_OPERATOR_LIKE:
			foreach ($tags as $tag) {
				if ($filter_tag['tag'] === $tag['tag'] && mb_stripos($tag['value'], $filter_tag['value']) !== false) {
					return true;
				}
			}
			break;

		case TAG_OPERATOR_EQUAL:
			foreach ($tags as $tag) {
				if ($filter_tag['tag'] === $tag['tag'] && $filter_tag['value'] === $tag['value']) {
					return true;
				}
			}
			break;

		case TAG_OPERATOR_NOT_LIKE:
			$tags_count = count($tags);
			$tags = array_filter($tags, function ($tag) use ($filter_tag) {
				return !($filter_tag['tag'] === $tag['tag']
					&& mb_stripos($tag['value'], $filter_tag['value']) !== false
				);
			});
			return (count($tags) == $tags_count);

		case TAG_OPERATOR_NOT_EQUAL:
			$tags_count = count($tags);
			$tags = array_filter($tags, function ($tag) use ($filter_tag) {
				return !($filter_tag['tag'] === $tag['tag'] && $filter_tag['value'] === $tag['value']);
			});
			return (count($tags) == $tags_count);

		case TAG_OPERATOR_EXISTS:
			return array_key_exists($filter_tag['tag'], zbx_toHash($tags, 'tag'));

		case TAG_OPERATOR_NOT_EXISTS:
			return !array_key_exists($filter_tag['tag'], zbx_toHash($tags, 'tag'));
	}

	return false;
}
