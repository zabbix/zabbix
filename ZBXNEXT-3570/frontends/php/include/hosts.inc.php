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


/**
 * Get ipmi auth type label by it's number.
 *
 * @param null|int $type
 *
 * @return array|string
 */
function ipmiAuthTypes($type = null) {
	$types = [
		IPMI_AUTHTYPE_DEFAULT => _('Default'),
		IPMI_AUTHTYPE_NONE => _('None'),
		IPMI_AUTHTYPE_MD2 => _('MD2'),
		IPMI_AUTHTYPE_MD5 => _('MD5'),
		IPMI_AUTHTYPE_STRAIGHT => _('Straight'),
		IPMI_AUTHTYPE_OEM => _('OEM'),
		IPMI_AUTHTYPE_RMCP_PLUS => _('RMCP+')
	];

	if (is_null($type)) {
		return $types;
	}
	elseif (isset($types[$type])) {
		return $types[$type];
	}
	else {
		return _('Unknown');
	}
}

/**
 * Get ipmi auth privilege label by it's number.
 *
 * @param null|int $type
 *
 * @return array|string
 */
function ipmiPrivileges($type = null) {
	$types = [
		IPMI_PRIVILEGE_CALLBACK => _('Callback'),
		IPMI_PRIVILEGE_USER => _('User'),
		IPMI_PRIVILEGE_OPERATOR => _('Operator'),
		IPMI_PRIVILEGE_ADMIN => _('Admin'),
		IPMI_PRIVILEGE_OEM => _('OEM')
	];

	if (is_null($type)) {
		return $types;
	}
	elseif (isset($types[$type])) {
		return $types[$type];
	}
	else {
		return _('Unknown');
	}
}

/**
 * Get info about what host inventory fields we have, their numbers and names.
 * Example of usage:
 *      $inventories = getHostInventories();
 *      echo $inventories[1]['db_field']; // host_networks
 *      echo $inventories[1]['title']; // Host networks
 *      echo $inventories[1]['nr']; // 1
 *
 * @param bool $orderedByTitle	whether an array should be ordered by field title, not by number
 *
 * @return array
 */
function getHostInventories($orderedByTitle = false) {
	/*
	 * WARNING! Before modifying this array, make sure changes are synced with C
	 * C analog is located in function DBget_inventory_field() in src/libs/zbxdbhigh/db.c
	 */
	$inventoryFields = [
		1 => [
			'nr' => 1,
			'db_field' => 'type',
			'title' => _('Type')
		],
		2 => [
			'nr' => 2,
			'db_field' => 'type_full',
			'title' => _('Type (Full details)')
		],
		3 => [
			'nr' => 3,
			'db_field' => 'name',
			'title' => _('Name')
		],
		4 => [
			'nr' => 4,
			'db_field' => 'alias',
			'title' => _('Alias')
		],
		5 => [
			'nr' => 5,
			'db_field' => 'os',
			'title' => _('OS')
		],
		6 => [
			'nr' => 6,
			'db_field' => 'os_full',
			'title' => _('OS (Full details)')
		],
		7 => [
			'nr' => 7,
			'db_field' => 'os_short',
			'title' => _('OS (Short)')
		],
		8 => [
			'nr' => 8,
			'db_field' => 'serialno_a',
			'title' => _('Serial number A')
		],
		9 => [
			'nr' => 9,
			'db_field' => 'serialno_b',
			'title' => _('Serial number B')
		],
		10 => [
			'nr' => 10,
			'db_field' => 'tag',
			'title' => _('Tag')
		],
		11 => [
			'nr' => 11,
			'db_field' => 'asset_tag',
			'title' => _('Asset tag')
		],
		12 => [
			'nr' => 12,
			'db_field' => 'macaddress_a',
			'title' => _('MAC address A')
		],
		13 => [
			'nr' => 13,
			'db_field' => 'macaddress_b',
			'title' => _('MAC address B')
		],
		14 => [
			'nr' => 14,
			'db_field' => 'hardware',
			'title' => _('Hardware')
		],
		15 => [
			'nr' => 15,
			'db_field' => 'hardware_full',
			'title' => _('Hardware (Full details)')
		],
		16 => [
			'nr' => 16,
			'db_field' => 'software',
			'title' => _('Software')
		],
		17 => [
			'nr' => 17,
			'db_field' => 'software_full',
			'title' => _('Software (Full details)')
		],
		18 => [
			'nr' => 18,
			'db_field' => 'software_app_a',
			'title' => _('Software application A')
		],
		19 => [
			'nr' => 19,
			'db_field' => 'software_app_b',
			'title' => _('Software application B')
		],
		20 => [
			'nr' => 20,
			'db_field' => 'software_app_c',
			'title' => _('Software application C')
		],
		21 => [
			'nr' => 21,
			'db_field' => 'software_app_d',
			'title' => _('Software application D')
		],
		22 => [
			'nr' => 22,
			'db_field' => 'software_app_e',
			'title' => _('Software application E')
		],
		23 => [
			'nr' => 23,
			'db_field' => 'contact',
			'title' => _('Contact')
		],
		24 => [
			'nr' => 24,
			'db_field' => 'location',
			'title' => _('Location')
		],
		25 => [
			'nr' => 25,
			'db_field' => 'location_lat',
			'title' => _('Location latitude')
		],
		26 => [
			'nr' => 26,
			'db_field' => 'location_lon',
			'title' => _('Location longitude')
		],
		27 => [
			'nr' => 27,
			'db_field' => 'notes',
			'title' => _('Notes')
		],
		28 => [
			'nr' => 28,
			'db_field' => 'chassis',
			'title' => _('Chassis')
		],
		29 => [
			'nr' => 29,
			'db_field' => 'model',
			'title' => _('Model')
		],
		30 => [
			'nr' => 30,
			'db_field' => 'hw_arch',
			'title' => _('HW architecture')
		],
		31 => [
			'nr' => 31,
			'db_field' => 'vendor',
			'title' => _('Vendor')
		],
		32 => [
			'nr' => 32,
			'db_field' => 'contract_number',
			'title' => _('Contract number')
		],
		33 => [
			'nr' => 33,
			'db_field' => 'installer_name',
			'title' => _('Installer name')
		],
		34 => [
			'nr' => 34,
			'db_field' => 'deployment_status',
			'title' => _('Deployment status')
		],
		35 => [
			'nr' => 35,
			'db_field' => 'url_a',
			'title' => _('URL A')
		],
		36 => [
			'nr' => 36,
			'db_field' => 'url_b',
			'title' => _('URL B')
		],
		37 => [
			'nr' => 37,
			'db_field' => 'url_c',
			'title' => _('URL C')
		],
		38 => [
			'nr' => 38,
			'db_field' => 'host_networks',
			'title' => _('Host networks')
		],
		39 => [
			'nr' => 39,
			'db_field' => 'host_netmask',
			'title' => _('Host subnet mask')
		],
		40 => [
			'nr' => 40,
			'db_field' => 'host_router',
			'title' => _('Host router')
		],
		41 => [
			'nr' => 41,
			'db_field' => 'oob_ip',
			'title' => _('OOB IP address')
		],
		42 => [
			'nr' => 42,
			'db_field' => 'oob_netmask',
			'title' => _('OOB subnet mask')
		],
		43 => [
			'nr' => 43,
			'db_field' => 'oob_router',
			'title' => _('OOB router')
		],
		44 => [
			'nr' => 44,
			'db_field' => 'date_hw_purchase',
			'title' => _('Date HW purchased')
		],
		45 => [
			'nr' => 45,
			'db_field' => 'date_hw_install',
			'title' => _('Date HW installed')
		],
		46 => [
			'nr' => 46,
			'db_field' => 'date_hw_expiry',
			'title' => _('Date HW maintenance expires')
		],
		47 => [
			'nr' => 47,
			'db_field' => 'date_hw_decomm',
			'title' => _('Date HW decommissioned')
		],
		48 => [
			'nr' => 48,
			'db_field' => 'site_address_a',
			'title' => _('Site address A')
		],
		49 => [
			'nr' => 49,
			'db_field' => 'site_address_b',
			'title' => _('Site address B')
		],
		50 => [
			'nr' => 50,
			'db_field' => 'site_address_c',
			'title' => _('Site address C')
		],
		51 => [
			'nr' => 51,
			'db_field' => 'site_city',
			'title' => _('Site city')
		],
		52 => [
			'nr' => 52,
			'db_field' => 'site_state',
			'title' => _('Site state / province')
		],
		53 => [
			'nr' => 53,
			'db_field' => 'site_country',
			'title' => _('Site country')
		],
		54 => [
			'nr' => 54,
			'db_field' => 'site_zip',
			'title' => _('Site ZIP / postal')
		],
		55 => [
			'nr' => 55,
			'db_field' => 'site_rack',
			'title' => _('Site rack location')
		],
		56 => [
			'nr' => 56,
			'db_field' => 'site_notes',
			'title' => _('Site notes')
		],
		57 => [
			'nr' => 57,
			'db_field' => 'poc_1_name',
			'title' => _('Primary POC name')
		],
		58 => [
			'nr' => 58,
			'db_field' => 'poc_1_email',
			'title' => _('Primary POC email')
		],
		59 => [
			'nr' => 59,
			'db_field' => 'poc_1_phone_a',
			'title' => _('Primary POC phone A')
		],
		60 => [
			'nr' => 60,
			'db_field' => 'poc_1_phone_b',
			'title' => _('Primary POC phone B')
		],
		61 => [
			'nr' => 61,
			'db_field' => 'poc_1_cell',
			'title' => _('Primary POC cell')
		],
		62 => [
			'nr' => 62,
			'db_field' => 'poc_1_screen',
			'title' => _('Primary POC screen name')
		],
		63 => [
			'nr' => 63,
			'db_field' => 'poc_1_notes',
			'title' => _('Primary POC notes')
		],
		64 => [
			'nr' => 64,
			'db_field' => 'poc_2_name',
			'title' => _('Secondary POC name')
		],
		65 => [
			'nr' => 65,
			'db_field' => 'poc_2_email',
			'title' => _('Secondary POC email')
		],
		66 => [
			'nr' => 66,
			'db_field' => 'poc_2_phone_a',
			'title' => _('Secondary POC phone A')
		],
		67 => [
			'nr' => 67,
			'db_field' => 'poc_2_phone_b',
			'title' => _('Secondary POC phone B')
		],
		68 => [
			'nr' => 68,
			'db_field' => 'poc_2_cell',
			'title' => _('Secondary POC cell')
		],
		69 => [
			'nr' => 69,
			'db_field' => 'poc_2_screen',
			'title' => _('Secondary POC screen name')
		],
		70 => [
			'nr' => 70,
			'db_field' => 'poc_2_notes',
			'title' => _('Secondary POC notes')
		]
	];

	// array is ordered by number by default, should we change that and order by title?
	if ($orderedByTitle) {
		function sortInventoriesByTitle($a, $b) {
			return strcmp($a['title'], $b['title']);
		}
		uasort($inventoryFields, 'sortInventoriesByTitle');
	}

	return $inventoryFields;
}

function hostInterfaceTypeNumToName($type) {
	switch ($type) {
		case INTERFACE_TYPE_AGENT:
			$name = _('agent');
			break;
		case INTERFACE_TYPE_SNMP:
			$name = _('SNMP');
			break;
		case INTERFACE_TYPE_JMX:
			$name = _('JMX');
			break;
		case INTERFACE_TYPE_IPMI:
			$name = _('IPMI');
			break;
		default:
			throw new Exception(_('Unknown interface type.'));
	}

	return $name;
}

function get_hostgroup_by_groupid($groupid) {
	$groups = DBfetch(DBselect('SELECT g.* FROM groups g WHERE g.groupid='.zbx_dbstr($groupid)));

	if ($groups) {
		return $groups;
	}

	error(_s('No host groups with groupid "%s".', $groupid));

	return false;
}

function get_host_by_itemid($itemids) {
	$res_array = is_array($itemids);
	zbx_value2array($itemids);
	$result = false;
	$hosts = [];

	$db_hostsItems = DBselect(
		'SELECT i.itemid,h.*'.
		' FROM hosts h,items i'.
		' WHERE i.hostid=h.hostid'.
			' AND '.dbConditionInt('i.itemid', $itemids)
	);
	while ($hostItem = DBfetch($db_hostsItems)) {
		$result = true;
		$hosts[$hostItem['itemid']] = $hostItem;
	}

	if (!$res_array) {
		foreach ($hosts as $itemid => $host) {
			$result = $host;
		}
	}
	elseif ($result) {
		$result = $hosts;
		unset($hosts);
	}

	return $result;
}

function get_host_by_hostid($hostid, $no_error_message = 0) {
	$row = DBfetch(DBselect('SELECT h.* FROM hosts h WHERE h.hostid='.zbx_dbstr($hostid)));

	if ($row) {
		return $row;
	}

	if ($no_error_message == 0) {
		error(_s('No host with hostid "%s".', $hostid));
	}

	return false;
}

function updateHostStatus($hostids, $status) {
	zbx_value2array($hostids);

	$hostIds = [];
	$oldStatus = ($status == HOST_STATUS_MONITORED ? HOST_STATUS_NOT_MONITORED : HOST_STATUS_MONITORED);

	$db_hosts = DBselect(
		'SELECT h.hostid,h.host,h.status'.
		' FROM hosts h'.
		' WHERE '.dbConditionInt('h.hostid', $hostids).
			' AND h.status='.zbx_dbstr($oldStatus)
	);
	while ($host = DBfetch($db_hosts)) {
		$hostIds[] = $host['hostid'];

		$host_new = $host;
		$host_new['status'] = $status;
		add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST, $host['hostid'], $host['host'], 'hosts', $host, $host_new);
		info(_s('Updated status of host "%1$s".', $host['host']));
	}

	return DB::update('hosts', [
		'values' => ['status' => $status],
		'where' => ['hostid' => $hostIds]
	]);
}

function get_application_by_applicationid($applicationid, $no_error_message = 0) {
	$row = DBfetch(DBselect('SELECT a.* FROM applications a WHERE a.applicationid='.zbx_dbstr($applicationid)));

	if ($row) {
		return $row;
	}

	if ($no_error_message == 0) {
		error(_s('No application with ID "%1$s".', $applicationid));
	}

	return false;
}

/**
 * Returns the farthest application ancestor for each given application.
 *
 * @param array $applicationIds
 * @param array $templateApplicationIds		array with parent application IDs as keys and arrays of child application
 * 											IDs as values
 *
 * @return array	an array with child IDs as keys and arrays of ancestor IDs as values
 */
function getApplicationSourceParentIds(array $applicationIds, array $templateApplicationIds = []) {
	$query = DBSelect(
		'SELECT at.applicationid,at.templateid'.
		' FROM application_template at'.
		' WHERE '.dbConditionInt('at.applicationid', $applicationIds)
	);

	$applicationIds = [];
	$unsetApplicationIds = [];
	while ($applicationTemplate = DBfetch($query)) {
		// check if we already have an application inherited from the current application
		// if we do - copy all of its child applications to the parent template
		if (isset($templateApplicationIds[$applicationTemplate['applicationid']])) {
			$templateApplicationIds[$applicationTemplate['templateid']] = $templateApplicationIds[$applicationTemplate['applicationid']];
			$unsetApplicationIds[$applicationTemplate['applicationid']] = $applicationTemplate['applicationid'];
		}
		// if no - just add the application
		else {
			$templateApplicationIds[$applicationTemplate['templateid']][] = $applicationTemplate['applicationid'];
		}
		$applicationIds[$applicationTemplate['applicationid']] = $applicationTemplate['templateid'];
	}

	// unset children of all applications that we found a new parent for
	foreach ($unsetApplicationIds as $applicationId) {
		unset($templateApplicationIds[$applicationId]);
	}

	// continue while we still have new applications to check
	if ($applicationIds) {
		return getApplicationSourceParentIds($applicationIds, $templateApplicationIds);
	}
	else {
		// return an inverse hash with application IDs as keys and arrays of parent application IDs as values
		$result = [];
		foreach ($templateApplicationIds as $templateId => $applicationIds) {
			foreach ($applicationIds as $applicationId) {
				$result[$applicationId][] = $templateId;
			}
		}

		return $result;
	}
}

/**
 * Returns the farthest host prototype ancestor for each given host prototype.
 *
 * @param array $hostPrototypeIds
 * @param array $templateHostPrototypeIds	array with parent host prototype IDs as keys and arrays of child host
 * 											prototype IDs as values
 *
 * @return array	an array of child ID - ancestor ID pairs
 */
function getHostPrototypeSourceParentIds(array $hostPrototypeIds, array $templateHostPrototypeIds = []) {
	$query = DBSelect(
		'SELECT h.hostid,h.templateid'.
		' FROM hosts h'.
		' WHERE '.dbConditionInt('h.hostid', $hostPrototypeIds).
			' AND h.templateid>0'
	);

	$hostPrototypeIds = [];
	while ($hostPrototype = DBfetch($query)) {
		// check if we already have host prototype inherited from the current host prototype
		// if we do - move all of its child prototypes to the parent template
		if (isset($templateHostPrototypeIds[$hostPrototype['hostid']])) {
			$templateHostPrototypeIds[$hostPrototype['templateid']] = $templateHostPrototypeIds[$hostPrototype['hostid']];
			unset($templateHostPrototypeIds[$hostPrototype['hostid']]);
		}
		// if no - just add the prototype
		else {
			$templateHostPrototypeIds[$hostPrototype['templateid']][] = $hostPrototype['hostid'];
			$hostPrototypeIds[] = $hostPrototype['templateid'];
		}
	}

	// continue while we still have new host prototypes to check
	if ($hostPrototypeIds) {
		return getHostPrototypeSourceParentIds($hostPrototypeIds, $templateHostPrototypeIds);
	}
	else {
		// return an inverse hash with prototype IDs as keys and parent prototype IDs as values
		$result = [];
		foreach ($templateHostPrototypeIds as $templateId => $hostIds) {
			foreach ($hostIds as $hostId) {
				$result[$hostId] = $templateId;
			}
		}

		return $result;
	}
}

/**
 * Get host ids of hosts which $groupids can be unlinked from.
 * if $hostids is passed, function will check only these hosts.
 *
 * @param array $groupids
 * @param array $hostids
 *
 * @return array
 */
function getUnlinkableHostIds(array $groupIds, array $hostIds) {
	if (!$hostIds) {
		return [];
	}

	$dbResult = DBselect(
		'SELECT hg.hostid'.
		' FROM hosts_groups hg'.
		' WHERE '.dbConditionInt('hg.groupid', $groupIds, true).
			' AND '.dbConditionInt('hg.hostid', $hostIds).
		' GROUP BY hg.hostid'
	);

	$unlinkableHostIds = [];
	while ($dbRow = DBfetch($dbResult)) {
		$unlinkableHostIds[] = $dbRow['hostid'];
	}

	return $unlinkableHostIds;
}

function getDeletableHostGroupIds(array $groupIds) {
	// selecting the list of hosts linked to the host groups
	$dbResult = DBselect(
		'SELECT hg.hostid'.
		' FROM hosts_groups hg'.
		' WHERE '.dbConditionInt('hg.groupid', $groupIds)
	);

	$linkedHostIds = [];
	while ($dbRow = DBfetch($dbResult)) {
		$linkedHostIds[] = $dbRow['hostid'];
	}

	// the list of hosts which can be unlinked from the host groups
	$hostIds = getUnlinkableHostIds($groupIds, $linkedHostIds);

	$dbResult = DBselect(
		'SELECT g.groupid'.
		' FROM groups g'.
		' WHERE g.internal='.ZBX_NOT_INTERNAL_GROUP.
			' AND '.dbConditionInt('g.groupid', $groupIds).
			' AND NOT EXISTS ('.
				'SELECT NULL'.
				' FROM hosts_groups hg'.
				' WHERE g.groupid=hg.groupid'.
					($hostIds ? ' AND '.dbConditionInt('hg.hostid', $hostIds, true) : '').
			')'
	);

	$deletableGroupIds = [];
	while ($dbRow = DBfetch($dbResult)) {
		$deletableGroupIds[$dbRow['groupid']] = $dbRow['groupid'];
	}

	return $deletableGroupIds;
}

function isTemplate($hostId) {
	$dbHost = DBfetch(DBselect('SELECT h.status FROM hosts h WHERE h.hostid='.zbx_dbstr($hostId)));

	return ($dbHost && $dbHost['status'] == HOST_STATUS_TEMPLATE);
}

/**
 * Get list of inherited macros by host ids.
 *
 * Returns an array like:
 *   array(
 *       '{$MACRO}' => array(
 *           'macro' => '{$MACRO}',
 *           'template' => array(                   <- optional
 *               'value' => 'template-level value'
 *               'templateid' => 10001,
 *               'name' => 'Template OS Linux'
 *           ),
 *           'global' => array(                     <- optional
 *               'value' => 'global-level value'
 *           )
 *       )
 *   )
 *
 * @param array $hostids
 *
 * @return array
 */
function getInheritedMacros(array $hostids) {
	$user_macro_parser = new CUserMacroParser();

	$all_macros = [];
	$global_macros = [];

	$db_global_macros = API::UserMacro()->get([
		'output' => ['macro', 'value'],
		'globalmacro' => true
	]);

	foreach ($db_global_macros as $db_global_macro) {
		$all_macros[$db_global_macro['macro']] = true;
		$global_macros[$db_global_macro['macro']] = $db_global_macro['value'];
	}

	// hostid => array('name' => name, 'macros' => array(macro => value), 'templateids' => array(templateid))
	$hosts = [];

	$templateids = $hostids;

	do {
		$db_templates = API::Template()->get([
			'output' => ['name'],
			'selectParentTemplates' => ['templateid'],
			'selectMacros' => ['macro', 'value'],
			'templateids' => $templateids,
			'preservekeys' => true
		]);

		$templateids = [];

		foreach ($db_templates as $hostid => $db_template) {
			$hosts[$hostid] = [
				'templateid' => $hostid,
				'name' => $db_template['name'],
				'templateids' => zbx_objectValues($db_template['parentTemplates'], 'templateid'),
				'macros' => []
			];

			/*
			 * Global macros are overwritten by template macros and template macros are overwritten by host macros.
			 * Macros with contexts require additional checking for contexts, since {$MACRO:} is the same as
			 * {$MACRO:""}.
			 */
			foreach ($db_template['macros'] as $dbMacro) {
				if (array_key_exists($dbMacro['macro'], $all_macros)) {
					$hosts[$hostid]['macros'][$dbMacro['macro']] = $dbMacro['value'];
					$all_macros[$dbMacro['macro']] = true;
				}
				else {
					$user_macro_parser->parse($dbMacro['macro']);
					$tpl_macro = $user_macro_parser->getMacro();
					$tpl_context = $user_macro_parser->getContext();

					if ($tpl_context === null) {
						$hosts[$hostid]['macros'][$dbMacro['macro']] = $dbMacro['value'];
						$all_macros[$dbMacro['macro']] = true;
					}
					else {
						$match_found = false;

						foreach ($global_macros as $global_macro => $global_value) {
							$user_macro_parser->parse($global_macro);
							$gbl_macro = $user_macro_parser->getMacro();
							$gbl_context = $user_macro_parser->getContext();

							if ($tpl_macro === $gbl_macro && $tpl_context === $gbl_context) {
								$match_found = true;

								unset($global_macros[$global_macro], $hosts[$hostid][$global_macro],
									$all_macros[$global_macro]
								);

								$hosts[$hostid]['macros'][$dbMacro['macro']] = $dbMacro['value'];
								$all_macros[$dbMacro['macro']] = true;
								$global_macros[$dbMacro['macro']] = $global_value;

								break;
							}
						}

						if (!$match_found) {
							$hosts[$hostid]['macros'][$dbMacro['macro']] = $dbMacro['value'];
							$all_macros[$dbMacro['macro']] = true;
						}
					}
				}
			}
		}

		foreach ($db_templates as $db_template) {
			// only unprocessed templates will be populated
			foreach ($db_template['parentTemplates'] as $template) {
				if (!array_key_exists($template['templateid'], $hosts)) {
					$templateids[$template['templateid']] = $template['templateid'];
				}
			}
		}
	} while ($templateids);

	$all_macros = array_keys($all_macros);
	$all_templates = [];
	$inherited_macros = [];

	// resolving
	foreach ($all_macros as $macro) {
		$inherited_macro = ['macro' => $macro];

		if (array_key_exists($macro, $global_macros)) {
			$inherited_macro['global'] = [
				'value' => $global_macros[$macro]
			];
		}

		$templateids = $hostids;

		do {
			natsort($templateids);

			foreach ($templateids as $templateid) {
				if (array_key_exists($templateid, $hosts) && array_key_exists($macro, $hosts[$templateid]['macros'])) {
					$inherited_macro['template'] = [
						'value' => $hosts[$templateid]['macros'][$macro],
						'templateid' => $hosts[$templateid]['templateid'],
						'name' => $hosts[$templateid]['name'],
						'rights' => PERM_READ
					];

					if (!array_key_exists($hosts[$templateid]['templateid'], $all_templates)) {
						$all_templates[$hosts[$templateid]['templateid']] = [];
					}
					$all_templates[$hosts[$templateid]['templateid']][] = &$inherited_macro['template'];

					break 2;
				}
			}

			$parent_templateids = [];

			foreach ($templateids as $templateid) {
				if (array_key_exists($templateid, $hosts)) {
					foreach ($hosts[$templateid]['templateids'] as $templateid) {
						$parent_templateids[$templateid] = $templateid;
					}
				}
			}

			$templateids = $parent_templateids;
		} while ($templateids);

		$inherited_macros[$macro] = $inherited_macro;
	}

	// checking permissions
	if ($all_templates) {
		$db_templates = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => array_keys($all_templates),
			'editable' => true
		]);

		foreach ($db_templates as $db_template) {
			foreach ($all_templates[$db_template['templateid']] as &$template) {
				$template['rights'] = PERM_READ_WRITE;
			}
			unset($template);
		}
	}

	return $inherited_macros;
}

/**
 * Merge list of inherited and host-level macros.
 *
 * Returns an array like:
 *   array(
 *       '{$MACRO}' => array(
 *           'macro' => '{$MACRO}',
 *           'type' => 0x03,						<- MACRO_TYPE_INHERITED, MACRO_TYPE_HOSTMACRO or MACRO_TYPE_BOTH
 *           'value' => 'effective value',
 *           'hostmacroid' => 7532,                 <- optional
 *           'template' => array(                   <- optional
 *               'value' => 'template-level value'
 *               'templateid' => 10001,
 *               'name' => 'Template OS Linux'
 *           ),
 *           'global' => array(                     <- optional
 *               'value' => 'global-level value'
 *           )
 *       )
 *   )
 *
 * @param array $host_macros		the list of host macros
 * @param array $inherited_macros	the list of inherited macros (the output of the getInheritedMacros() function)
 *
 * @return array
 */
function mergeInheritedMacros(array $host_macros, array $inherited_macros) {
	$user_macro_parser = new CUserMacroParser();

	foreach ($inherited_macros as &$inherited_macro) {
		$inherited_macro['type'] = MACRO_TYPE_INHERITED;
		$inherited_macro['value'] = array_key_exists('template', $inherited_macro)
			? $inherited_macro['template']['value']
			: $inherited_macro['global']['value'];
	}
	unset($inherited_macro);

	/*
	 * Global macros and template macros are overwritten by host macros. Macros with contexts require additional
	 * checking for contexts, since {$MACRO:} is the same as {$MACRO:""}.
	 */
	foreach ($host_macros as &$host_macro) {
		if (array_key_exists($host_macro['macro'], $inherited_macros)) {
			$host_macro = array_merge($inherited_macros[$host_macro['macro']], $host_macro);
			unset($inherited_macros[$host_macro['macro']]);
		}
		else {
			/*
			 * Cannot use array dereferencing because "$host_macro['macro']" may contain invalid macros
			 * which results in empty array.
			 */
			if ($user_macro_parser->parse($host_macro['macro']) == CParser::PARSE_SUCCESS) {
				$hst_macro = $user_macro_parser->getMacro();
				$hst_context = $user_macro_parser->getContext();

				if ($hst_context === null) {
					$host_macro['type'] = 0x00;
				}
				else {
					$match_found = false;

					foreach ($inherited_macros as $inherited_macro => $inherited_values) {
						// Safe to use array dereferencing since these values come from database.
						$user_macro_parser->parse($inherited_macro);
						$inh_macro = $user_macro_parser->getMacro();
						$inh_context = $user_macro_parser->getContext();

						if ($hst_macro === $inh_macro && $hst_context === $inh_context) {
							$match_found = true;

							$host_macro = array_merge($inherited_macros[$inherited_macro], $host_macro);
							unset($inherited_macros[$inherited_macro]);

							break;
						}
					}

					if (!$match_found) {
						$host_macro['type'] = 0x00;
					}
				}
			}
			else {
				$host_macro['type'] = 0x00;
			}
		}

		$host_macro['type'] |= MACRO_TYPE_HOSTMACRO;
	}
	unset($host_macro);

	foreach ($inherited_macros as $inherited_macro) {
		$host_macros[] = $inherited_macro;
	}

	return $host_macros;
}

/**
 * Remove inherited macros data.
 *
 * @param array $macros
 *
 * @return array
 */
function cleanInheritedMacros(array $macros) {
	foreach ($macros as $idx => $macro) {
		if (array_key_exists('type', $macro) && !($macro['type'] & MACRO_TYPE_HOSTMACRO)) {
			unset($macros[$idx]);
		}
		else {
			unset($macros[$idx]['type'], $macros[$idx]['inherited']);
		}
	}

	return $macros;
}

/**
 * An array of available host inventory modes.
 *
 * @return array
 */
function getHostInventoryModes() {
	return [
		HOST_INVENTORY_DISABLED => _('Disabled'),
		HOST_INVENTORY_MANUAL => _('Manual'),
		HOST_INVENTORY_AUTOMATIC => _('Automatic')
	];
}

/**
 * Check if user has read permissions for hosts.
 *
 * @param array $hostids
 *
 * @return bool
 */
function isReadableHosts(array $hostids) {
	return count($hostids) == API::Host()->get([
		'countOutput' => true,
		'hostids' => $hostids
	]);
}

/**
 * Check if user has read permissions for templates.
 *
 * @param array $templateids
 *
 * @return bool
 */
function isReadableTemplates(array $templateids) {
	return count($templateids) == API::Template()->get([
		'countOutput' => true,
		'templateids' => $templateids
	]);
}

/**
 * Check if user has read permissions for hosts or templates.
 *
 * @param array $hostids
 *
 * @return bool
 */
function isReadableHostTemplates(array $hostids) {
	$count = API::Host()->get([
		'countOutput' => true,
		'hostids' => $hostids
	]);

	if ($count == count($hostids)) {
		return true;
	}

	$count += API::Template()->get([
		'countOutput' => true,
		'templateids' => $hostids
	]);

	return ($count == count($hostids));
}
