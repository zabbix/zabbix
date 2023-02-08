<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
		IPMI_AUTHTYPE_MD5 => 'MD5',
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

/**
 * Returns the host interface as a string of the host's IP address (or DNS name) and port number.
 *
 * @param array|null $interface
 * @param int    $interface['useip']  Interface use IP or DNS. INTERFACE_USE_DNS or INTERFACE_USE_IP.
 * @param string $interface['ip']     Interface IP.
 * @param string $interface['dns']    Interface DNS.
 * @param string $interface['port']   Interface port.
 *
 * @return string
 */
function getHostInterface(?array $interface): string {
	if ($interface === null) {
		return '';
	}

	if ($interface['type'] == INTERFACE_TYPE_AGENT_ACTIVE) {
		return _('Active checks');
	}

	if ($interface['useip'] == INTERFACE_USE_IP) {
		$ip_or_dns = (filter_var($interface['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false)
			? '['.$interface['ip'].']'
			: $interface['ip'];
	}
	else {
		$ip_or_dns = $interface['dns'];
	}

	return $ip_or_dns.':'.$interface['port'];
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
		error(_s('No host with host ID "%1$s".', $hostid));
	}

	return false;
}

/**
 * Get the necessary data to display the parent host prototypes of the given host prototypes.
 *
 * @param array  $host_prototypes
 * @param string $host_prototypes[]['templateid']
 * @param bool   $allowed_ui_conf_templates
 *
 * @return array
 */
function getParentHostPrototypes(array $host_prototypes, bool $allowed_ui_conf_templates): array {
	$parent_host_prototypes = [];

	foreach ($host_prototypes as $host_prototype) {
		if ($host_prototype['templateid'] != 0) {
			$parent_host_prototypes[$host_prototype['templateid']] = [];
		}
	}

	if (!$parent_host_prototypes) {
		return [];
	}

	$db_host_prototypes = API::HostPrototype()->get([
		'output' => [],
		'selectParentHost' => ['name'],
		'hostids' => array_keys($parent_host_prototypes),
		'preservekeys' => true
	]);

	if ($allowed_ui_conf_templates && $db_host_prototypes) {
		$editable_host_prototypes = API::HostPrototype()->get([
			'output' => [],
			'selectDiscoveryRule' => ['itemid'],
			'hostids' => array_keys($parent_host_prototypes),
			'editable' => true,
			'preservekeys' => true
		]);
	}

	foreach ($parent_host_prototypes as $hostid => &$parent_host_prototype) {
		if (array_key_exists($hostid, $db_host_prototypes)) {
			if ($allowed_ui_conf_templates && array_key_exists($hostid, $editable_host_prototypes)) {
				$parent_host_prototype = [
					'editable' => true,
					'template_name' => $db_host_prototypes[$hostid]['parentHost']['name'],
					'ruleid' => $editable_host_prototypes[$hostid]['discoveryRule']['itemid']
				];
			}
			else {
				$parent_host_prototype = [
					'editable' => false,
					'template_name' => $db_host_prototypes[$hostid]['parentHost']['name']
				];
			}
		}
		else {
			$parent_host_prototype = [
				'editable' => false,
				'template_name' => _('Inaccessible template')
			];
		}
	}
	unset($parent_host_prototype);

	return $parent_host_prototypes;
}

function isTemplate($hostId) {
	$dbHost = DBfetch(DBselect('SELECT h.status FROM hosts h WHERE h.hostid='.zbx_dbstr($hostId)));

	return ($dbHost && $dbHost['status'] == HOST_STATUS_TEMPLATE);
}

/**
 * Supplement the given macros with the inherited macros from the parent host, templates and global macros.
 *
 * @param array       $macros         User macros of current host/template/host prototype.
 * @param array|null  $templateids    Linked template IDs.
 * @param string|null $parent_hostid  Parent host ID of host prototype.
 */
function addInheritedMacros(array &$macros, array $templateids = null, ?string $parent_hostid = null): void {
	$inherited_macros = [];

	$db_global_macros = API::UserMacro()->get([
		'output' => ['macro', 'value', 'description', 'type'],
		'globalmacro' => true
	]);

	foreach ($db_global_macros as $db_macro) {
		$inherited_macros[CApiInputValidator::trimMacro($db_macro['macro'])]['global'] = [
			'value' => getMacroConfigValue($db_macro)
		] + $db_macro;
	}

	if ($templateids !== null) {
		$db_templates = API::Template()->get([
			'output' => ['name'],
			'selectMacros' => ['macro', 'value', 'description', 'type'],
			'templateids' => $templateids,
			'preservekeys' => true
		]);

		natksort($db_templates);

		foreach ($db_templates as $db_template) {
			foreach ($db_template['macros'] as $db_macro) {
				$trimmed_macro = CApiInputValidator::trimMacro($db_macro['macro']);

				if (array_key_exists($trimmed_macro, $inherited_macros)
						&& array_key_exists('template', $inherited_macros[$trimmed_macro])) {
					continue;
				}

				$inherited_macros[$trimmed_macro]['template'] = [
					'value' => getMacroConfigValue($db_macro),
					'templateid' => $db_template['templateid'],
					'name' => $db_template['name'],
					'rights' => PERM_READ
				] + $db_macro;
			}
		}

		$tpl_links = [];

		foreach ($inherited_macros as $trimmed_macro => $level_macros) {
			if (array_key_exists('template', $level_macros)) {
				$tpl_links[$level_macros['template']['templateid']][] = $trimmed_macro;
			}
		}

		$db_templates = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => array_keys($tpl_links),
			'editable' => true
		]);

		foreach ($db_templates as $db_template) {
			foreach ($tpl_links[$db_template['templateid']] as $trimmed_macro) {
				$inherited_macros[$trimmed_macro]['template']['rights'] = PERM_READ_WRITE;
			}
		}
	}

	if ($parent_hostid !== null) {
		$db_macros = API::UserMacro()->get([
			'output' => ['macro', 'type', 'value', 'description'],
			'hostids' => $parent_hostid
		]);

		foreach ($db_macros as $db_macro) {
			$inherited_macros[CApiInputValidator::trimMacro($db_macro['macro'])]['parent_host'] = [
				'value' => getMacroConfigValue($db_macro)
			] + $db_macro;
		}
	}

	$user_macro_parser = new CUserMacroParser();
	$inherit_order = array_flip(['parent_host', 'template', 'global']);

	foreach ($macros as &$macro) {
		$macro += ['inherited_type' => ZBX_PROPERTY_OWN];

		if ($user_macro_parser->parse($macro['macro']) == CParser::PARSE_SUCCESS) {
			$trimmed_macro = CApiInputValidator::trimMacro($macro['macro']);

			if (array_key_exists($trimmed_macro, $inherited_macros)) {
				$macro = [
					'inherited_type' => ZBX_PROPERTY_BOTH,
					'inherited_level' => key(array_intersect_key($inherit_order, $inherited_macros[$trimmed_macro]))
				] + $inherited_macros[$trimmed_macro] + $macro;

				unset($inherited_macros[$trimmed_macro]);
			}
		}
	}
	unset($macro);

	foreach ($inherited_macros as $inherited_macro) {
		$inherited_level = key(array_intersect_key($inherit_order, $inherited_macro));

		$macro = [
			'inherited_type' => ZBX_PROPERTY_INHERITED,
			'inherited_level' => $inherited_level
		];

		$ignored_fields = ['hostmacroid'];

		if ($inherited_macro[$inherited_level]['type'] == ZBX_MACRO_TYPE_SECRET) {
			$ignored_fields[] = 'value';
		}

		$macro += array_diff_key($inherited_macro[$inherited_level], array_flip($ignored_fields));

		$macros[] = $macro + $inherited_macro;
	}
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
		if (array_key_exists('inherited_type', $macro) && !($macro['inherited_type'] & ZBX_PROPERTY_OWN)) {
			unset($macros[$idx]);
		}
		else {
			unset($macros[$idx]['inherited_type'], $macros[$idx]['inherited']);
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
 * Check if user has write permissions for hosts or templates.
 *
 * @param array $hostids
 *
 * @return bool
 */
function isWritableHostTemplates(array $hostids) {
	$count = API::Host()->get([
		'countOutput' => true,
		'hostids' => $hostids,
		'editable' => true
	]);

	if ($count == count($hostids)) {
		return true;
	}

	$count += API::Template()->get([
		'countOutput' => true,
		'templateids' => $hostids,
		'editable' => true
	]);

	return ($count == count($hostids));
}

function getAddNewInterfaceSubmenu() {
	return [
		'main_section' => [
			'items' => [
				"javascript:hostInterfaceManager.addAgent();" => _('Agent'),
				"javascript:hostInterfaceManager.addSnmp();" => _('SNMP'),
				"javascript:hostInterfaceManager.addJmx();" => _('JMX'),
				"javascript:hostInterfaceManager.addIpmi();" => _('IPMI')
			]
		]
	];
}

function renderInterfaceHeaders() {
	return (new CDiv())
		->addClass(implode(' ', [ZBX_STYLE_HOST_INTERFACE_CONTAINER, ZBX_STYLE_HOST_INTERFACE_CONTAINER_HEADER]))
		->addItem(
			(new CDiv())
				->addClass(implode(' ', [ZBX_STYLE_HOST_INTERFACE_ROW, ZBX_STYLE_HOST_INTERFACE_ROW_HEADER]))
				->addItem([
					(new CDiv())->addClass(ZBX_STYLE_HOST_INTERFACE_CELL),
					(new CDiv(_('Type')))->addClass(
						implode(' ', [ZBX_STYLE_HOST_INTERFACE_CELL, ZBX_STYLE_HOST_INTERFACE_CELL_HEADER,
							ZBX_STYLE_HOST_INTERFACE_CELL_TYPE
						])
					),
					(new CDiv(_('IP address')))->addClass(
						implode(' ', [ZBX_STYLE_HOST_INTERFACE_CELL, ZBX_STYLE_HOST_INTERFACE_CELL_HEADER,
							ZBX_STYLE_HOST_INTERFACE_CELL_IP
						])
					),
					(new CDiv(_('DNS name')))->addClass(
						implode(' ', [ZBX_STYLE_HOST_INTERFACE_CELL, ZBX_STYLE_HOST_INTERFACE_CELL_HEADER,
							ZBX_STYLE_HOST_INTERFACE_CELL_DNS
						])
					),
					(new CDiv(_('Connect to')))->addClass(
						implode(' ', [ZBX_STYLE_HOST_INTERFACE_CELL, ZBX_STYLE_HOST_INTERFACE_CELL_HEADER,
							ZBX_STYLE_HOST_INTERFACE_CELL_USEIP
						])
					),
					(new CDiv(_('Port')))->addClass(
						implode(' ', [ZBX_STYLE_HOST_INTERFACE_CELL, ZBX_STYLE_HOST_INTERFACE_CELL_HEADER,
							ZBX_STYLE_HOST_INTERFACE_CELL_PORT
						])
					),
					(new CDiv(_('Default')))->addClass(
						implode(' ', [ZBX_STYLE_HOST_INTERFACE_CELL, ZBX_STYLE_HOST_INTERFACE_CELL_HEADER,
							ZBX_STYLE_HOST_INTERFACE_CELL_ACTION
						])
					)
				])
		);
}

function getHostDashboards(string $hostid, array $dashboard_fields = []): array {
	$dashboard_fields = array_merge($dashboard_fields, ['dashboardid']);
	$dashboard_fields = array_keys(array_flip($dashboard_fields));

	$templateids = CApiHostHelper::getParentTemplates([$hostid])[1];

	return API::TemplateDashboard()->get([
		'output' => $dashboard_fields,
		'templateids' => $templateids,
		'preservekeys' => true
	]);
}

/**
 * Return macro value to display in the list of inherited macros.
 *
 * @param array $macro
 *
 * @return string
 */
function getMacroConfigValue(array $macro): string {
	return ($macro['type'] == ZBX_MACRO_TYPE_SECRET) ? ZBX_SECRET_MASK : $macro['value'];
}

/**
 * Add to the given array of host IDs their parent template IDs.
 *
 * @param array $hostids
 */
function addParentTemplateIds(array &$hostids): void {
	$hosts = API::Host()->get([
		'output' => [],
		'selectParentTemplates' => ['templateid'],
		'hostids' => $hostids
	]);

	$hostids = array_flip($hostids);

	foreach ($hosts as $host) {
		$hostids += array_flip(array_column($host['parentTemplates'], 'templateid'));
	}

	$hostids = array_keys($hostids);
}
