<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Get parent templates for each given host prototype.
 *
 * @param array  $host_prototypes                  An array of host prototypes.
 * @param string $host_prototypes[]['hostid']      ID of host prototype.
 * @param string $host_prototypes[]['templateid']  ID of parent template host prototype.
 *
 * @return array
 */
function getHostPrototypeParentTemplates(array $host_prototypes) {
	$parent_host_prototypeids = [];
	$data = [
		'links' => [],
		'templates' => []
	];

	foreach ($host_prototypes as $host_prototype) {
		if ($host_prototype['templateid'] != 0) {
			$parent_host_prototypeids[$host_prototype['templateid']] = true;
			$data['links'][$host_prototype['hostid']] = ['hostid' => $host_prototype['templateid']];
		}
	}

	if (!$parent_host_prototypeids) {
		return $data;
	}

	$all_parent_host_prototypeids = [];
	$hostids = [];
	$lld_ruleids = [];

	do {
		$db_host_prototypes = API::HostPrototype()->get([
			'output' => ['hostid', 'templateid'],
			'selectDiscoveryRule' => ['itemid'],
			'selectParentHost' => ['hostid'],
			'hostids' => array_keys($parent_host_prototypeids)
		]);

		$all_parent_host_prototypeids += $parent_host_prototypeids;
		$parent_host_prototypeids = [];

		foreach ($db_host_prototypes as $db_host_prototype) {
			$data['templates'][$db_host_prototype['parentHost']['hostid']] = [];
			$hostids[$db_host_prototype['hostid']] = $db_host_prototype['parentHost']['hostid'];
			$lld_ruleids[$db_host_prototype['hostid']] = $db_host_prototype['discoveryRule']['itemid'];

			if ($db_host_prototype['templateid'] != 0) {
				if (!array_key_exists($db_host_prototype['templateid'], $all_parent_host_prototypeids)) {
					$parent_host_prototypeids[$db_host_prototype['templateid']] = true;
				}

				$data['links'][$db_host_prototype['hostid']] = ['hostid' => $db_host_prototype['templateid']];
			}
		}
	}
	while ($parent_host_prototypeids);

	foreach ($data['links'] as &$parent_host_prototype) {
		$parent_host_prototype['parent_hostid'] = array_key_exists($parent_host_prototype['hostid'], $hostids)
			? $hostids[$parent_host_prototype['hostid']]
			: 0;

		$parent_host_prototype['lld_ruleid'] = array_key_exists($parent_host_prototype['hostid'], $lld_ruleids)
			? $lld_ruleids[$parent_host_prototype['hostid']]
			: 0;
	}
	unset($parent_host_prototype);

	$db_templates = $data['templates']
		? API::Template()->get([
			'output' => ['name'],
			'templateids' => array_keys($data['templates']),
			'preservekeys' => true
		])
		: [];

	$rw_templates = $db_templates
		? API::Template()->get([
			'output' => [],
			'templateids' => array_keys($db_templates),
			'editable' => true,
			'preservekeys' => true
		])
		: [];

	$data['templates'][0] = [];

	foreach ($data['templates'] as $hostid => &$template) {
		$template = array_key_exists($hostid, $db_templates)
			? [
				'hostid' => $hostid,
				'name' => $db_templates[$hostid]['name'],
				'permission' => array_key_exists($hostid, $rw_templates) ? PERM_READ_WRITE : PERM_READ
			]
			: [
				'hostid' => $hostid,
				'name' => _('Inaccessible template'),
				'permission' => PERM_DENY
			];
	}
	unset($template);

	return $data;
}

/**
 * Returns a template prefix for selected host prototype.
 *
 * @param string $host_prototypeid
 * @param array  $parent_templates  The list of the templates, prepared by getHostPrototypeParentTemplates() function.
 * @param bool   $provide_links     If this parameter is false, prefix will not contain links.
 *
 * @return array|null
 */
function makeHostPrototypeTemplatePrefix($host_prototypeid, array $parent_templates, bool $provide_links) {
	if (!array_key_exists($host_prototypeid, $parent_templates['links'])) {
		return null;
	}

	while (array_key_exists($parent_templates['links'][$host_prototypeid]['hostid'], $parent_templates['links'])) {
		$host_prototypeid = $parent_templates['links'][$host_prototypeid]['hostid'];
	}

	$template = $parent_templates['templates'][$parent_templates['links'][$host_prototypeid]['parent_hostid']];

	if ($provide_links && $template['permission'] == PERM_READ_WRITE) {
		$name = (new CLink(CHtml::encode($template['name']),
			(new CUrl('host_prototypes.php'))
				->setArgument('parent_discoveryid', $parent_templates['links'][$host_prototypeid]['lld_ruleid'])
				->setArgument('context', 'template')
		))->addClass(ZBX_STYLE_LINK_ALT);
	}
	else {
		$name = new CSpan(CHtml::encode($template['name']));
	}

	return [$name->addClass(ZBX_STYLE_GREY), NAME_DELIMITER];
}

/**
 * Returns a list of host prototype templates.
 *
 * @param string $host_prototypeid
 * @param array  $parent_templates  The list of the templates, prepared by getHostPrototypeParentTemplates() function.
 * @param bool   $provide_links     If this parameter is false, prefix will not contain links.
 *
 * @return array
 */
function makeHostPrototypeTemplatesHtml($host_prototypeid, array $parent_templates, bool $provide_links) {
	$list = [];

	while (array_key_exists($host_prototypeid, $parent_templates['links'])) {
		$template = $parent_templates['templates'][$parent_templates['links'][$host_prototypeid]['parent_hostid']];

		if ($provide_links && $template['permission'] == PERM_READ_WRITE) {
			$name = new CLink(CHtml::encode($template['name']),
				(new CUrl('host_prototypes.php'))
					->setArgument('form', 'update')
					->setArgument('parent_discoveryid', $parent_templates['links'][$host_prototypeid]['lld_ruleid'])
					->setArgument('hostid', $parent_templates['links'][$host_prototypeid]['hostid'])
					->setArgument('context', 'template')
			);
		}
		else {
			$name = (new CSpan(CHtml::encode($template['name'])))->addClass(ZBX_STYLE_GREY);
		}

		array_unshift($list, $name, '&nbsp;&rArr;&nbsp;');

		$host_prototypeid = $parent_templates['links'][$host_prototypeid]['hostid'];
	}

	if ($list) {
		array_pop($list);
	}

	return $list;
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
 *           'parent_host' => array(                <- optional
 *               'value' => 'parent host level value',
 *               'type' => 0,
 *               'description' => ''
 *           ),
 *           'template' => array(                   <- optional
 *               'value' => 'template-level value'
 *               'templateid' => 10001,
 *               'name' => 'Template OS Linux by Zabbix agent'
 *           ),
 *           'global' => array(                     <- optional
 *               'value' => 'global-level value'
 *           )
 *       )
 *   )
 *
 * @param array     $hostids        Host or template ids.
 * @param int|null  $parent_hostid  Parent host id of host prototype.
 *
 * @return array
 */
function getInheritedMacros(array $hostids, ?int $parent_hostid = null): array {
	$user_macro_parser = new CUserMacroParser();

	$all_macros = [];
	$global_macros = [];

	$db_global_macros = API::UserMacro()->get([
		'output' => ['macro', 'value', 'description', 'type'],
		'globalmacro' => true
	]);

	foreach ($db_global_macros as $db_global_macro) {
		$all_macros[$db_global_macro['macro']] = true;
		$global_macros[$db_global_macro['macro']] = [
			'value' => getMacroConfigValue($db_global_macro),
			'description' => $db_global_macro['description'],
			'type' => $db_global_macro['type']
		];
	}

	// hostid => array('name' => name, 'macros' => array(macro => value), 'templateids' => array(templateid))
	$hosts = [];

	$templateids = $hostids;

	do {
		$db_templates = API::Template()->get([
			'output' => ['name'],
			'selectParentTemplates' => ['templateid'],
			'selectMacros' => ['macro', 'value', 'description', 'type'],
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
					$hosts[$hostid]['macros'][$dbMacro['macro']] = [
						'value' => getMacroConfigValue($dbMacro),
						'description' => $dbMacro['description'],
						'type' => $dbMacro['type']
					];
					$all_macros[$dbMacro['macro']] = true;
				}
				else {
					$user_macro_parser->parse($dbMacro['macro']);
					$tpl_macro = $user_macro_parser->getMacro();
					$tpl_context = $user_macro_parser->getContext();

					if ($tpl_context === null) {
						$hosts[$hostid]['macros'][$dbMacro['macro']] = [
							'value' => getMacroConfigValue($dbMacro),
							'description' => $dbMacro['description'],
							'type' => $dbMacro['type']
						];
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

								$hosts[$hostid]['macros'][$dbMacro['macro']] = [
									'value' => getMacroConfigValue($dbMacro),
									'description' => $dbMacro['description'],
									'type' => $dbMacro['type']
								];
								$all_macros[$dbMacro['macro']] = true;
								$global_macros[$dbMacro['macro']] = $global_value;

								break;
							}
						}

						if (!$match_found) {
							$hosts[$hostid]['macros'][$dbMacro['macro']] = [
								'value' => getMacroConfigValue($dbMacro),
								'description' => $dbMacro['description'],
								'type' => $dbMacro['type']
							];
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

	$all_templates = [];
	$inherited_macros = [];
	$parent_host_macros = [];

	if ($parent_hostid !== null) {
		$parent_host_macros = API::UserMacro()->get([
			'output' => ['macro', 'type', 'value', 'description'],
			'hostids' => [$parent_hostid]
		]);

		$parent_host_macros = array_column($parent_host_macros, null, 'macro');
		$all_macros += array_fill_keys(array_keys($parent_host_macros), true);
	}

	$all_macros = array_keys($all_macros);

	// resolving
	foreach ($all_macros as $macro) {
		$inherited_macro = ['macro' => $macro];

		if (array_key_exists($macro, $parent_host_macros)) {
			$inherited_macro['parent_host'] = [
				'value' => getMacroConfigValue($parent_host_macros[$macro]),
				'description' => $parent_host_macros[$macro]['description'],
				'type' => $parent_host_macros[$macro]['type']
			];
		}
		elseif (array_key_exists($macro, $global_macros)) {
			$inherited_macro['global'] = [
				'value' => $global_macros[$macro]['value'],
				'description' => $global_macros[$macro]['description'],
				'type' => $global_macros[$macro]['type']
			];
		}

		$templateids = $hostids;

		do {
			natsort($templateids);

			foreach ($templateids as $templateid) {
				if (array_key_exists($templateid, $hosts) && array_key_exists($macro, $hosts[$templateid]['macros'])) {
					$inherited_macro['template'] = [
						'value' => $hosts[$templateid]['macros'][$macro]['value'],
						'description' => $hosts[$templateid]['macros'][$macro]['description'],
						'templateid' => $hosts[$templateid]['templateid'],
						'name' => $hosts[$templateid]['name'],
						'rights' => PERM_READ,
						'type' => $hosts[$templateid]['macros'][$macro]['type']
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
 *           'type' => 0,                           <- ZBX_MACRO_TYPE_TEXT or ZBX_MACRO_TYPE_SECRET
 *           'inherited_type' => 0x03,              <- ZBX_PROPERTY_INHERITED, ZBX_PROPERTY_OWN or ZBX_PROPERTY_BOTH
 *           'value' => 'effective value',
 *           'hostmacroid' => 7532,                 <- optional
 *           'parent_host' => array(                <- optional
 *               'value' => 'parent host value',
 *               'type' => 0,
 *               'description' => ''
 *           ),
 *           'template' => array(                   <- optional
 *               'value' => 'template-level value'
 *               'templateid' => 10001,
 *               'name' => 'Template OS Linux by Zabbix agent'
 *           ),
 *           'global' => array(                     <- optional
 *               'value' => 'global-level value'
 *           )
 *       )
 *   )
 *
 * @param array $host_macros       The list of host macros.
 * @param array $inherited_macros  The list of inherited macros (the output of the getInheritedMacros() function).
 *
 * @return array
 */
function mergeInheritedMacros(array $host_macros, array $inherited_macros): array {
	$user_macro_parser = new CUserMacroParser();
	$inherit_order = ['parent_host', 'template', 'global'];

	foreach ($inherited_macros as &$inherited_macro) {
		[$inherited_level] = array_values(array_intersect($inherit_order, array_keys($inherited_macro)));
		$inherited_macro['inherited_type'] = ZBX_PROPERTY_INHERITED;
		$inherited_macro['inherited_level'] = $inherited_level;
		$inherited_macro['value'] = $inherited_macro[$inherited_level]['value'];
		$inherited_macro['type'] = $inherited_macro[$inherited_level]['type'];
		$inherited_macro['description'] = $inherited_macro[$inherited_level]['description'];

		// Secret macro value cannot be inherited.
		if ($inherited_macro['type'] == ZBX_MACRO_TYPE_SECRET) {
			unset($inherited_macro['value']);
		}
	}
	unset($inherited_macro);

	/*
	 * Global macros and template macros are overwritten by host macros. Macros with contexts require additional
	 * checking for contexts, since {$MACRO:} is the same as {$MACRO:""}.
	 */
	foreach ($host_macros as &$host_macro) {
		// Secret macro value cannot be inherited.
		if ($host_macro['type'] == ZBX_MACRO_TYPE_SECRET) {
			unset($inherited_macros[$host_macro['macro']]['value']);
		}

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
					$host_macro['inherited_type'] = 0x00;
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
						$host_macro['inherited_type'] = 0x00;
					}
				}
			}
			else {
				$host_macro['inherited_type'] = 0x00;
			}
		}

		$host_macro['inherited_type'] |= ZBX_PROPERTY_OWN;
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
