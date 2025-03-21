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


/**
 * Check if user has write permissions for host groups.
 *
 * @param array $groupids
 *
 * @return bool
 */
function isWritableHostGroups(array $groupids) {
	return count($groupids) == API::HostGroup()->get([
		'countOutput' => true,
		'groupids' => $groupids,
		'editable' => true
	]);
}

/**
 * Get sub-groups of selected host groups or template groups.
 *
 * @param array  $groupids
 * @param array  $ms_groups  [OUT] The list of groups for multiselect.
 * @param string $context    Context of hosts or templates.
 *
 * @return array
 */
function getSubGroups(array $groupids, ?array &$ms_groups = null, string $context = 'host') {
	$entity = $context === 'host' ? API::HostGroup() : API::TemplateGroup();
	$db_groups = $groupids
		? $entity->get([
			'output' => ['groupid', 'name'],
			'groupids' => $groupids,
			'preservekeys' => true
		])
		: [];

	if ($ms_groups !== null) {
		$ms_groups = CArrayHelper::renameObjectsKeys($db_groups, ['groupid' => 'id']);
	}

	$db_groups_names = [];

	foreach ($db_groups as $db_group) {
		$db_groups_names[] = $db_group['name'].'/';
	}

	if ($db_groups_names) {
		$db_groups += $entity->get([
			'output' => ['groupid'],
			'search' => ['name' => $db_groups_names],
			'searchByAny' => true,
			'startSearch' => true,
			'preservekeys' => true
		]);
	}

	return array_keys($db_groups);
}

/**
 * Get sub-groups of selected template groups.
 *
 * @param array $template_groupids
 * @param array $ms_template_groups  [OUT] The list of groups for multiselect.
 * @param array $options             Additional API options to select template groups.
 *
 * @return array
 */
function getTemplateSubGroups(array $template_groupids, ?array &$ms_template_groups = null, array $options = []) {
	$db_groups = $template_groupids
		? API::TemplateGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $template_groupids,
				'preservekeys' => true
			] + $options)
		: [];

	if ($ms_template_groups !== null) {
		$ms_template_groups = CArrayHelper::renameObjectsKeys($db_groups, ['groupid' => 'id']);
	}

	$db_groups_names = [];

	foreach ($db_groups as $db_group) {
		$db_groups_names[] = $db_group['name'].'/';
	}

	if ($db_groups_names) {
		$db_groups += API::TemplateGroup()->get([
				'output' => ['groupid'],
				'search' => ['name' => $db_groups_names],
				'searchByAny' => true,
				'startSearch' => true,
				'preservekeys' => true
			] + $options);
	}

	return array_keys($db_groups);
}

/**
 * Creates a hintbox suitable for Problem hosts widget.
 *
 * @param array      $hosts                                                   Array of problematic hosts.
 * @param array      $data                                                    Array of host data, filter settings and
 *                                                                            severity configuration.
 * @param array      $data['filter']['severities']                            Array of severities.
 * @param string     $data['hosts_data'][<hostid>]['host']                    Host name.
 * @param int        $data['hosts_data'][<hostid>]['severities'][<severity>]  Severity count.
 * @param CUrl|null  $url                                                     URL that leads to problems view having
 *                                                                            hostid in its filter.
 *
 * @return CTableInfo
 */
function makeProblemHostsHintBox(array $hosts, array $data, ?CUrl $url) {
	// Set trigger severities as table header, ordered starting from highest severity.
	$header = [_('Host')];

	foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
		if (in_array($severity, $data['filter']['severities'])) {
			$header[] = CSeverityHelper::getName($severity);
		}
	}

	$maintenanceids = [];
	foreach ($data['hosts_data'] as $host) {
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			$maintenanceids[$host['maintenanceid']] = true;
		}
	}

	$db_maintenances = $maintenanceids
		? API::Maintenance()->get([
			'output' => ['name', 'description'],
			'maintenanceids' => array_keys($maintenanceids),
			'preservekeys' => true
		])
		: [];

	$table_inf = (new CTableInfo())->setHeader($header);

	$popup_rows = 0;

	foreach ($hosts as $hostid => $host) {
		$host_data = $data['hosts_data'][$hostid];
		if ($url !== null) {
			$url->setArgument('hostids', [$hostid]);
			$host_name = new CLink($host_data['host'], $url->getUrl());
		}
		else {
			$host_name = $host_data['host'];
		}

		if ($host_data['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			if (array_key_exists($host_data['maintenanceid'], $db_maintenances)) {
				$maintenance = $db_maintenances[$host_data['maintenanceid']];
				$maintenance_icon = makeMaintenanceIcon($host_data['maintenance_type'], $maintenance['name'],
					$maintenance['description']
				);
			}
			else {
				$maintenance_icon = makeMaintenanceIcon($host_data['maintenance_type'], _('Inaccessible maintenance'),
					''
				);
			}

			$host_name = [$host_name, $maintenance_icon];
		}

		$row = new CRow((new CCol($host_name))->addClass(ZBX_STYLE_NOWRAP));

		foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
			if (in_array($severity, $data['filter']['severities'])) {
				$row->addItem(
					($host_data['severities'][$severity] != 0)
						? (new CCol($host_data['severities'][$severity]))
							->addClass(CSeverityHelper::getStyle($severity))
						: ''
				);
			}
		}

		$table_inf->addRow($row);

		if (++$popup_rows == ZBX_WIDGET_ROWS) {
			break;
		}
	}

	return $table_inf;
}

/**
 * Enriches host groups array by parent groups.
 *
 * @param array  $groups
 * @param string $groups[<groupid>]['groupid']
 * @param string $groups[<groupid>]['name']
 *
 * @return array
 */
function enrichParentGroups(array $groups) {
	$parents = [];
	foreach ($groups as $group) {
		$parent = explode('/', $group['name']);
		while (array_pop($parent) && $parent) {
			$parent_name = implode('/', $parent);
			$parents[$parent_name] = $parent_name;
		}
	}

	if ($parents) {
		foreach ($groups as $group) {
			if (array_key_exists($group['name'], $parents)) {
				unset($parents[$group['name']]);
			}
		}
	}

	if ($parents) {
		$groups += API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'filter' => [
				'name' => $parents
			],
			'preservekeys' => true
		]);
	}

	return $groups;
}

/**
 * Enriches template groups array by parent groups.
 *
 * @param array  $groups
 * @param string $groups[<groupid>]['groupid']
 * @param string $groups[<groupid>]['name']
 *
 * @return array
 */
function enrichParentTemplateGroups(array $groups) {
	$parents = [];
	foreach ($groups as $group) {
		$parent = explode('/', $group['name']);
		while (array_pop($parent) && $parent) {
			$parent_name = implode('/', $parent);
			$parents[$parent_name] = $parent_name;
		}
	}

	if ($parents) {
		foreach ($groups as $group) {
			if (array_key_exists($group['name'], $parents)) {
				unset($parents[$group['name']]);
			}
		}
	}

	if ($parents) {
		$groups += API::TemplateGroup()->get([
			'output' => ['groupid', 'name'],
			'filter' => [
				'name' => $parents
			],
			'preservekeys' => true
		]);
	}

	return $groups;
}
