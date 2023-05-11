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
 * Convert windows events type constant in to the string representation
 *
 * @param int $logtype
 * @return string
 */
function get_item_logtype_description($logtype) {
	switch ($logtype) {
		case ITEM_LOGTYPE_INFORMATION:
			return _('Information');
		case ITEM_LOGTYPE_WARNING:
			return _('Warning');
		case ITEM_LOGTYPE_ERROR:
			return _('Error');
		case ITEM_LOGTYPE_FAILURE_AUDIT:
			return _('Failure Audit');
		case ITEM_LOGTYPE_SUCCESS_AUDIT:
			return _('Success Audit');
		case ITEM_LOGTYPE_CRITICAL:
			return _('Critical');
		case ITEM_LOGTYPE_VERBOSE:
			return _('Verbose');
		default:
			return _('Unknown');
	}
}

/**
 * Convert windows events type constant in to the CSS style name
 *
 * @param int $logtype
 * @return string
 */
function get_item_logtype_style($logtype) {
	switch ($logtype) {
		case ITEM_LOGTYPE_INFORMATION:
		case ITEM_LOGTYPE_SUCCESS_AUDIT:
		case ITEM_LOGTYPE_VERBOSE:
			return ZBX_STYLE_LOG_INFO_BG;

		case ITEM_LOGTYPE_WARNING:
			return ZBX_STYLE_LOG_WARNING_BG;

		case ITEM_LOGTYPE_ERROR:
		case ITEM_LOGTYPE_FAILURE_AUDIT:
			return ZBX_STYLE_LOG_HIGH_BG;

		case ITEM_LOGTYPE_CRITICAL:
			return ZBX_STYLE_LOG_DISASTER_BG;

		default:
			return ZBX_STYLE_LOG_NA_BG;
	}
}

/**
 * Get item type string name by item type number, or array of all item types if null passed.
 *
 * @param int|null $type
 *
 * @return array|string
 */
function item_type2str($type = null) {
	$types = [
		ITEM_TYPE_ZABBIX => _('Zabbix agent'),
		ITEM_TYPE_ZABBIX_ACTIVE => _('Zabbix agent (active)'),
		ITEM_TYPE_SIMPLE => _('Simple check'),
		ITEM_TYPE_SNMP => _('SNMP agent'),
		ITEM_TYPE_SNMPTRAP => _('SNMP trap'),
		ITEM_TYPE_INTERNAL => _('Zabbix internal'),
		ITEM_TYPE_TRAPPER => _('Zabbix trapper'),
		ITEM_TYPE_EXTERNAL => _('External check'),
		ITEM_TYPE_DB_MONITOR => _('Database monitor'),
		ITEM_TYPE_HTTPAGENT => _('HTTP agent'),
		ITEM_TYPE_IPMI => _('IPMI agent'),
		ITEM_TYPE_SSH => _('SSH agent'),
		ITEM_TYPE_TELNET => _('TELNET agent'),
		ITEM_TYPE_JMX => _('JMX agent'),
		ITEM_TYPE_CALCULATED => _('Calculated'),
		ITEM_TYPE_HTTPTEST => _('Web monitoring'),
		ITEM_TYPE_DEPENDENT => _('Dependent item'),
		ITEM_TYPE_SCRIPT => _('Script')
	];

	if ($type === null) {
		return $types;
	}

	return array_key_exists($type, $types) ? $types[$type] : _('Unknown');
}

/**
 * Returns human readable an item value type
 *
 * @param int $valueType
 *
 * @return string
 */
function itemValueTypeString($valueType) {
	switch ($valueType) {
		case ITEM_VALUE_TYPE_UINT64:
			return _('Numeric (unsigned)');
		case ITEM_VALUE_TYPE_FLOAT:
			return _('Numeric (float)');
		case ITEM_VALUE_TYPE_STR:
			return _('Character');
		case ITEM_VALUE_TYPE_LOG:
			return _('Log');
		case ITEM_VALUE_TYPE_TEXT:
			return _('Text');
	}
	return _('Unknown');
}

function item_status2str($type = null) {
	if (is_null($type)) {
		return [ITEM_STATUS_ACTIVE => _('Enabled'), ITEM_STATUS_DISABLED => _('Disabled')];
	}

	return ($type == ITEM_STATUS_ACTIVE) ? _('Enabled') : _('Disabled');
}

/**
 * Returns the names of supported item states.
 *
 * If the $state parameter is passed, returns the name of the specific state, otherwise - returns an array of all
 * supported states.
 *
 * @param string $state
 *
 * @return array|string
 */
function itemState($state = null) {
	$states = [
		ITEM_STATE_NORMAL => _('Normal'),
		ITEM_STATE_NOTSUPPORTED => _('Not supported')
	];

	if ($state === null) {
		return $states;
	}
	elseif (isset($states[$state])) {
		return $states[$state];
	}
	else {
		return _('Unknown');
	}
}

/**
 * Returns the text indicating the items status and state. If the $state parameter is not given, only the status of
 * the item will be taken into account.
 *
 * @param int $status
 * @param int $state
 *
 * @return string
 */
function itemIndicator($status, $state = null) {
	if ($status == ITEM_STATUS_ACTIVE) {
		return ($state == ITEM_STATE_NOTSUPPORTED) ? _('Not supported') : _('Enabled');
	}

	return _('Disabled');
}

/**
 * Returns the CSS class for the items status and state indicator. If the $state parameter is not given, only the status of
 * the item will be taken into account.
 *
 * @param int $status
 * @param int $state
 *
 * @return string
 */
function itemIndicatorStyle($status, $state = null) {
	if ($status == ITEM_STATUS_ACTIVE) {
		return ($state == ITEM_STATE_NOTSUPPORTED) ?
			ZBX_STYLE_GREY :
			ZBX_STYLE_GREEN;
	}

	return ZBX_STYLE_RED;
}

/**
 * Order items by keep history.
 *
 * @param array  $items
 * @param string $items['history']
 * @param string $sortorder
 */
function orderItemsByHistory(array &$items, $sortorder){
	$simple_interval_parser = new CSimpleIntervalParser();

	foreach ($items as &$item) {
		$item['history_sort'] = ($simple_interval_parser->parse($item['history']) == CParser::PARSE_SUCCESS)
			? timeUnitToSeconds($item['history'])
			: $item['history'];
	}
	unset($item);

	order_result($items, 'history_sort', $sortorder);

	foreach ($items as &$item) {
		unset($item['history_sort']);
	}
	unset($item);
}

/**
 * Order items by keep trends.
 *
 * @param array  $items
 * @param int    $items['value_type']
 * @param string $items['trends']
 * @param string $sortorder
 */
function orderItemsByTrends(array &$items, $sortorder){
	$simple_interval_parser = new CSimpleIntervalParser();

	foreach ($items as &$item) {
		if (in_array($item['value_type'], [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT])) {
			$item['trends_sort'] = '';
		}
		else {
			$item['trends_sort'] = ($simple_interval_parser->parse($item['trends']) == CParser::PARSE_SUCCESS)
				? timeUnitToSeconds($item['trends'])
				: $item['trends'];
		}
	}
	unset($item);

	order_result($items, 'trends_sort', $sortorder);

	foreach ($items as &$item) {
		unset($item['trends_sort']);
	}
	unset($item);
}

/**
 * Order items by update interval.
 *
 * @param array  $items
 * @param int    $items['type']
 * @param string $items['delay']
 * @param string $items['key_']
 * @param string $sortorder
 * @param array  $options
 * @param bool   $options['usermacros']
 * @param bool   $options['lldmacros']
 */
function orderItemsByDelay(array &$items, $sortorder, array $options){
	$update_interval_parser = new CUpdateIntervalParser($options);

	foreach ($items as &$item) {
		if (in_array($item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])
				|| ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($item['key_'], 'mqtt.get', 8) === 0)) {
			$item['delay_sort'] = '';
		}
		elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
			$item['delay_sort'] = $update_interval_parser->getDelay();

			if ($item['delay_sort'][0] !== '{') {
				$item['delay_sort'] = timeUnitToSeconds($item['delay_sort']);
			}
		}
		else {
			$item['delay_sort'] = $item['delay'];
		}
	}
	unset($item);

	order_result($items, 'delay_sort', $sortorder);

	foreach ($items as &$item) {
		unset($item['delay_sort']);
	}
	unset($item);
}

/**
 * Orders items by both status and state. Items are sorted in the following order: enabled, disabled, not supported.
 *
 * Keep in sync with orderTriggersByStatus().
 *
 * @param array  $items
 * @param string $sortorder
 */
function orderItemsByStatus(array &$items, $sortorder = ZBX_SORT_UP) {
	$sort = [];

	foreach ($items as $key => $item) {
		if ($item['status'] == ITEM_STATUS_ACTIVE) {
			$sort[$key] = ($item['state'] == ITEM_STATE_NOTSUPPORTED) ? 2 : 0;
		}
		else {
			$sort[$key] = 1;
		}
	}

	if ($sortorder == ZBX_SORT_UP) {
		asort($sort);
	}
	else {
		arsort($sort);
	}

	$sortedItems = [];
	foreach ($sort as $key => $val) {
		$sortedItems[$key] = $items[$key];
	}
	$items = $sortedItems;
}

/**
 * Returns the name of the given interface type. Items "status" and "state" properties must be defined.
 *
 * @param int $type
 *
 * @return ?string
 */
function interfaceType2str($type) {
	$interfaceGroupLabels = [
		INTERFACE_TYPE_AGENT => _('Agent'),
		INTERFACE_TYPE_SNMP => _('SNMP'),
		INTERFACE_TYPE_JMX => _('JMX'),
		INTERFACE_TYPE_IPMI => _('IPMI')
	];

	return array_key_exists($type, $interfaceGroupLabels) ? $interfaceGroupLabels[$type] : null;
}

function itemTypeInterface($type = null) {
	static $types = [
		ITEM_TYPE_SNMP => INTERFACE_TYPE_SNMP,
		ITEM_TYPE_SNMPTRAP => INTERFACE_TYPE_SNMP,
		ITEM_TYPE_IPMI => INTERFACE_TYPE_IPMI,
		ITEM_TYPE_ZABBIX => INTERFACE_TYPE_AGENT,
		ITEM_TYPE_SIMPLE => INTERFACE_TYPE_OPT,
		ITEM_TYPE_EXTERNAL => INTERFACE_TYPE_OPT,
		ITEM_TYPE_SSH => INTERFACE_TYPE_OPT,
		ITEM_TYPE_TELNET => INTERFACE_TYPE_OPT,
		ITEM_TYPE_JMX => INTERFACE_TYPE_JMX,
		ITEM_TYPE_HTTPAGENT => INTERFACE_TYPE_OPT
	];

	if (is_null($type)) {
		return $types;
	}
	elseif (array_key_exists($type, $types)) {
		return $types[$type];
	}

	return false;
}

/**
 * Convert a list of interfaces to an array of interface type => interfaceids.
 *
 * @param array $interfaces  List of (host) interfaces.
 *
 * @return array  Interface IDs grouped by type.
 */

function interfaceIdsByType(array $interfaces) {
	$interface_ids_by_type = [];

	foreach ($interfaces as $interface) {
		$interface_ids_by_type[$interface['type']][] = $interface['interfaceid'];
	}

	return $interface_ids_by_type;
}

/**
 * Create copies of items from the given sources to the given destination hosts or templates.
 *
 * If source type is 'templateids' or 'hostids', only non-inherited items are copied.
 *
 * If source type is 'itemids', all the given items are copied.
 *
 * @param string $src_type
 * @param array  $src_ids
 * @param bool   $dst_is_template
 * @param array  $dst_hostids
 *
 * @return bool
 */
function copyItemsToHosts(string $src_type, array $src_ids, bool $dst_is_template, array $dst_hostids): bool {
	$options = in_array($src_type, ['templateids', 'hostids']) ? ['inherited' => false] : [];

	if ($src_type === 'hostids') {
		$options['filter'] = ['flags' => ZBX_FLAG_DISCOVERY_NORMAL];
	}

	$src_items = API::Item()->get([
		'output' => ['itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history', 'trends',
			'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'status',

			// Type fields.
			// The fields used for multiple item types.
			'interfaceid', 'authtype', 'username', 'password', 'params', 'timeout', 'delay', 'trapper_hosts',

			// Dependent item type specific fields.
			'master_itemid',

			// HTTP Agent item type specific fields.
			'url', 'query_fields', 'request_method', 'post_type', 'posts',
			'headers', 'status_codes', 'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy',
			'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'allow_traps',

			// IPMI item type specific fields.
			'ipmi_sensor',

			// JMX item type specific fields.
			'jmx_endpoint',

			// Script item type specific fields.
			'parameters',

			// SNMP item type specific fields.
			'snmp_oid',

			// SSH item type specific fields.
			'publickey', 'privatekey'
		],
		'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
		'selectTags' => ['tag', 'value'],
		$src_type => $src_ids,
		'preservekeys' => true
	] + $options);

	if (!$src_items) {
		return true;
	}

	$src_itemids = array_fill_keys(array_keys($src_items), true);
	$src_valuemapids = [];
	$src_interfaceids = [];
	$src_dep_items = [];
	$dep_itemids = [];

	foreach ($src_items as $itemid => $item) {
		if ($item['valuemapid'] != 0) {
			$src_valuemapids[$item['valuemapid']] = true;
		}

		if ($item['interfaceid'] != 0) {
			$src_interfaceids[$item['interfaceid']] = true;
		}

		if ($item['type'] == ITEM_TYPE_DEPENDENT) {
			if (array_key_exists($item['master_itemid'], $src_itemids)) {
				$src_dep_items[$item['master_itemid']][] = $item;

				unset($src_items[$itemid]);
			}
			else {
				$dep_itemids[$item['master_itemid']][] = $item['itemid'];
			}
		}
	}

	$valuemap_links = [];

	if ($src_valuemapids) {
		$src_valuemaps = API::ValueMap()->get([
			'output' => ['valuemapid', 'name'],
			'valuemapids' => array_keys($src_valuemapids)
		]);

		$dst_valuemaps = API::ValueMap()->get([
			'output' => ['valuemapid', 'hostid', 'name'],
			'hostids' => $dst_hostids,
			'filter' => ['name' => array_unique(array_column($src_valuemaps, 'name'))]
		]);

		$dst_valuemapids = [];

		foreach ($dst_valuemaps as $dst_valuemap) {
			$dst_valuemapids[$dst_valuemap['name']][$dst_valuemap['hostid']] = $dst_valuemap['valuemapid'];
		}

		foreach ($src_valuemaps as $src_valuemap) {
			if (array_key_exists($src_valuemap['name'], $dst_valuemapids)) {
				foreach ($dst_valuemapids[$src_valuemap['name']] as $dst_hostid => $dst_valuemapid) {
					$valuemap_links[$src_valuemap['valuemapid']][$dst_hostid] = $dst_valuemapid;
				}
			}
		}
	}

	$interface_links = [];
	$dst_interfaceids = [];

	if (!$dst_is_template) {
		$src_interfaces = [];

		if ($src_interfaceids) {
			$src_hosts = API::Host()->get([
				'output' => [],
				'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
				$src_type => $src_ids
			]);

			foreach ($src_hosts as $src_host) {
				foreach ($src_host['interfaces'] as $src_interface) {
					if (array_key_exists($src_interface['interfaceid'], $src_interfaceids)) {
						$src_interfaces[$src_interface['interfaceid']] =
							array_diff_key($src_interface, array_flip(['interfaceid']));
					}
				}
			}
		}

		$dst_hosts = API::Host()->get([
			'output' => ['hostid'],
			'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
			'hostids' => $dst_hostids
		]);

		foreach ($dst_hosts as $dst_host) {
			foreach ($dst_host['interfaces'] as $dst_interface) {
				$dst_interfaceid = $dst_interface['interfaceid'];
				unset($dst_interface['interfaceid']);

				foreach ($src_interfaces as $src_interfaceid => $src_interface) {
					if ($src_interface == $dst_interface) {
						$interface_links[$src_interfaceid][$dst_host['hostid']] = $dst_interfaceid;
					}
				}

				if ($dst_interface['main'] == INTERFACE_PRIMARY) {
					$dst_interfaceids[$dst_host['hostid']][$dst_interface['type']] = $dst_interfaceid;
				}
			}
		}
	}

	$master_item_links = [];

	if ($dep_itemids) {
		$master_items = API::Item()->get([
			'output' => ['itemid', 'key_'],
			'itemids' => array_keys($dep_itemids),
			'webitems' => true
		]);

		$options = $dst_is_template ? ['templateids' => $dst_hostids] : ['hostids' => $dst_hostids];

		$dst_master_items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'key_'],
			'filter' => ['key_' => array_unique(array_column($master_items, 'key_'))],
			'webitems' => true
		] + $options);

		$dst_master_itemids = [];

		foreach ($dst_master_items as $item) {
			$dst_master_itemids[$item['hostid']][$item['key_']] = $item['itemid'];
		}

		foreach ($master_items as $item) {
			foreach ($dst_hostids as $dst_hostid) {
				if (array_key_exists($dst_hostid, $dst_master_itemids)
						&& array_key_exists($item['key_'], $dst_master_itemids[$dst_hostid])) {
					$master_item_links[$item['itemid']][$dst_hostid] = $dst_master_itemids[$dst_hostid][$item['key_']];
				}
				else {
					$src_itemid = reset($dep_itemids[$item['itemid']]);

					error(_s('Cannot copy item with key "%1$s" without its master item with key "%2$s".',
						$src_items[$src_itemid]['key_'], $item['key_']
					));

					return false;
				}
			}
		}
	}

	do {
		$dst_items = [];

		if (!$dst_hostids) {
			return true;
		}

		foreach ($dst_hostids as $dst_hostid) {
			foreach ($src_items as $src_item) {
				$dst_item = array_diff_key($src_item, array_flip(['itemid']));

				if ($src_item['valuemapid'] != 0) {
					if (array_key_exists($src_item['valuemapid'], $valuemap_links)
							&& array_key_exists($dst_hostid, $valuemap_links[$src_item['valuemapid']])) {
						$dst_item['valuemapid'] = $valuemap_links[$src_item['valuemapid']][$dst_hostid];
					}
					else {
						$dst_item['valuemapid'] = 0;
					}
				}

				$dst_item['interfaceid'] = 0;

				if (!$dst_is_template) {
					if (array_key_exists($src_item['interfaceid'], $interface_links)
							&& array_key_exists($dst_hostid, $interface_links[$src_item['interfaceid']])) {
						$dst_item['interfaceid'] = $interface_links[$src_item['interfaceid']][$dst_hostid];
					}
					else {
						$type = itemTypeInterface($src_item['type']);

						if (in_array($type,
							[INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI]
						)) {
							if (array_key_exists($dst_hostid, $dst_interfaceids)
									&& array_key_exists($type, $dst_interfaceids[$dst_hostid])) {
								$dst_item['interfaceid'] = $dst_interfaceids[$dst_hostid][$type];
							}
							else {
								$hosts = API::Host()->get([
									'output' => ['host'],
									'hostids' => $dst_hostid
								]);

								error(_s('Cannot find host interface on "%1$s" for item with key "%2$s".',
									$hosts[0]['host'], $src_item['key_']
								));

								return false;
							}
						}
					}
				}

				if ($src_item['type'] == ITEM_TYPE_DEPENDENT) {
					$dst_item['master_itemid'] = $master_item_links[$src_item['master_itemid']][$dst_hostid];
				}

				$dst_items[] = ['hostid' => $dst_hostid] + $dst_item;
			}
		}

		$response = API::Item()->create($dst_items);

		if ($response === false) {
			return false;
		}

		$_src_items = [];

		if ($src_dep_items) {
			foreach ($dst_hostids as $dst_hostid) {
				foreach ($src_items as $src_item) {
					$dst_itemid = array_shift($response['itemids']);

					if (array_key_exists($src_item['itemid'], $src_dep_items)) {
						$master_item_links[$src_item['itemid']][$dst_hostid] = $dst_itemid;

						$_src_items = array_merge($_src_items, $src_dep_items[$src_item['itemid']]);
						unset($src_dep_items[$src_item['itemid']]);
					}
				}
			}
		}

		$src_items = $_src_items;
	} while ($src_items);

	return true;
}

/**
 * Description:
 * Replace items for specified host
 *
 * Comments:
 * $error= true : rise Error if item doesn't exist (error generated), false: special processing (NO error generated)
 */
function get_same_item_for_host($item, $dest_hostids) {
	$return_array = is_array($dest_hostids);
	zbx_value2array($dest_hostids);

	if (!is_array($item)) {
		$itemid = $item;
	}
	elseif (isset($item['itemid'])) {
		$itemid = $item['itemid'];
	}

	$same_item = null;
	$same_items = [];

	if (isset($itemid)) {
		$db_items = DBselect(
			'SELECT src.*'.
			' FROM items src,items dest'.
			' WHERE dest.itemid='.zbx_dbstr($itemid).
				' AND src.key_=dest.key_'.
				' AND '.dbConditionInt('src.hostid', $dest_hostids)
		);
		while ($db_item = DBfetch($db_items)) {
			if (is_array($item)) {
				$same_item = $db_item;
				$same_items[$db_item['itemid']] = $db_item;
			}
			else {
				$same_item = $db_item['itemid'];
				$same_items[$db_item['itemid']] = $db_item['itemid'];
			}
		}
		if ($return_array) {
			return $same_items;
		}
		else {
			return $same_item;
		}
	}
	return false;
}

/**
 * Get parent templates for each given item.
 *
 * @param array  $items                  An array of items.
 * @param string $items[]['itemid']      ID of an item.
 * @param string $items[]['templateid']  ID of parent template item.
 * @param int    $flag                   Origin of the item (ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE,
 *                                       ZBX_FLAG_DISCOVERY_PROTOTYPE).
 *
 * @return array
 */
function getItemParentTemplates(array $items, $flag) {
	$parent_itemids = [];
	$data = [
		'links' => [],
		'templates' => []
	];

	foreach ($items as $item) {
		if ($item['templateid'] != 0) {
			$parent_itemids[$item['templateid']] = true;
			$data['links'][$item['itemid']] = ['itemid' => $item['templateid']];
		}
	}

	if (!$parent_itemids) {
		return $data;
	}

	$all_parent_itemids = [];
	$hostids = [];
	if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
		$lld_ruleids = [];
	}

	do {
		if ($flag == ZBX_FLAG_DISCOVERY_RULE) {
			$db_items = API::DiscoveryRule()->get([
				'output' => ['itemid', 'hostid', 'templateid'],
				'itemids' => array_keys($parent_itemids)
			]);
		}
		elseif ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
			$db_items = API::ItemPrototype()->get([
				'output' => ['itemid', 'hostid', 'templateid'],
				'itemids' => array_keys($parent_itemids),
				'selectDiscoveryRule' => ['itemid']
			]);
		}
		// ZBX_FLAG_DISCOVERY_NORMAL
		else {
			$db_items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'templateid'],
				'itemids' => array_keys($parent_itemids),
				'webitems' => true
			]);
		}

		$all_parent_itemids += $parent_itemids;
		$parent_itemids = [];

		foreach ($db_items as $db_item) {
			$data['templates'][$db_item['hostid']] = [];
			$hostids[$db_item['itemid']] = $db_item['hostid'];

			if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$lld_ruleids[$db_item['itemid']] = $db_item['discoveryRule']['itemid'];
			}

			if ($db_item['templateid'] != 0) {
				if (!array_key_exists($db_item['templateid'], $all_parent_itemids)) {
					$parent_itemids[$db_item['templateid']] = true;
				}

				$data['links'][$db_item['itemid']] = ['itemid' => $db_item['templateid']];
			}
		}
	}
	while ($parent_itemids);

	foreach ($data['links'] as &$parent_item) {
		$parent_item['hostid'] = array_key_exists($parent_item['itemid'], $hostids)
			? $hostids[$parent_item['itemid']]
			: 0;

		if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
			$parent_item['lld_ruleid'] = array_key_exists($parent_item['itemid'], $lld_ruleids)
				? $lld_ruleids[$parent_item['itemid']]
				: 0;
		}
	}
	unset($parent_item);

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
 * Returns a template prefix for selected item.
 *
 * @param string $itemid
 * @param array  $parent_templates  The list of the templates, prepared by getItemParentTemplates() function.
 * @param int    $flag              Origin of the item (ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE,
 *                                  ZBX_FLAG_DISCOVERY_PROTOTYPE).
 * @param bool   $provide_links     If this parameter is false, prefix will not contain links.
 *
 * @return array|null
 */
function makeItemTemplatePrefix($itemid, array $parent_templates, $flag, bool $provide_links) {
	if (!array_key_exists($itemid, $parent_templates['links'])) {
		return null;
	}

	while (array_key_exists($parent_templates['links'][$itemid]['itemid'], $parent_templates['links'])) {
		$itemid = $parent_templates['links'][$itemid]['itemid'];
	}

	$template = $parent_templates['templates'][$parent_templates['links'][$itemid]['hostid']];

	if ($provide_links && $template['permission'] == PERM_READ_WRITE) {
		if ($flag == ZBX_FLAG_DISCOVERY_RULE) {
			$url = (new CUrl('host_discovery.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$template['hostid']])
				->setArgument('context', 'template');
		}
		elseif ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
			$url = (new CUrl('disc_prototypes.php'))
				->setArgument('parent_discoveryid', $parent_templates['links'][$itemid]['lld_ruleid'])
				->setArgument('context', 'template');
		}
		// ZBX_FLAG_DISCOVERY_NORMAL
		else {
			$url = (new CUrl('items.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$template['hostid']])
				->setArgument('context', 'template');
		}

		$name = (new CLink($template['name'], $url))->addClass(ZBX_STYLE_LINK_ALT);
	}
	else {
		$name = new CSpan($template['name']);
	}

	return [$name->addClass(ZBX_STYLE_GREY), NAME_DELIMITER];
}

/**
 * Returns a list of item templates.
 *
 * @param string $itemid
 * @param array  $parent_templates  The list of the templates, prepared by getItemParentTemplates() function.
 * @param int    $flag              Origin of the item (ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE,
 *                                  ZBX_FLAG_DISCOVERY_PROTOTYPE).
 * @param bool   $provide_links     If this parameter is false, prefix will not contain links.
 *
 * @return array
 */
function makeItemTemplatesHtml($itemid, array $parent_templates, $flag, bool $provide_links) {
	$list = [];

	while (array_key_exists($itemid, $parent_templates['links'])) {
		$template = $parent_templates['templates'][$parent_templates['links'][$itemid]['hostid']];

		if ($provide_links && $template['permission'] == PERM_READ_WRITE) {
			if ($flag == ZBX_FLAG_DISCOVERY_RULE) {
				$url = (new CUrl('host_discovery.php'))
					->setArgument('form', 'update')
					->setArgument('itemid', $parent_templates['links'][$itemid]['itemid'])
					->setArgument('context', 'template');
			}
			elseif ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$url = (new CUrl('disc_prototypes.php'))
					->setArgument('form', 'update')
					->setArgument('itemid', $parent_templates['links'][$itemid]['itemid'])
					->setArgument('parent_discoveryid', $parent_templates['links'][$itemid]['lld_ruleid'])
					->setArgument('context', 'template');
			}
			// ZBX_FLAG_DISCOVERY_NORMAL
			else {
				$url = (new CUrl('items.php'))
					->setArgument('form', 'update')
					->setArgument('itemid', $parent_templates['links'][$itemid]['itemid'])
					->setArgument('context', 'template');
			}

			$name = new CLink($template['name'], $url);
		}
		else {
			$name = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
		}

		array_unshift($list, $name, [NBSP(), RARR(), NBSP()]);

		$itemid = $parent_templates['links'][$itemid]['itemid'];
	}

	if ($list) {
		array_pop($list);
	}

	return $list;
}

/**
 * Collect latest value and actual severity value for each item of Data overview table.
 *
 * @param array $db_items
 * @param array $data
 * @param int   $show_suppressed
 *
 * @return array
 */
function getDataOverviewCellData(array $db_items, array $data, int $show_suppressed): array {
	$history = Manager::History()->getLastValues($db_items, 1,
		timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD))
	);

	$db_triggers = getTriggersWithActualSeverity([
		'output' => ['triggerid', 'priority', 'value'],
		'selectItems' => ['itemid'],
		'itemids' => array_keys($db_items),
		'monitored' => true,
		'preservekeys' => true
	], ['show_suppressed' => $show_suppressed]);

	$itemid_to_triggerids = [];
	foreach ($db_triggers as $triggerid => $db_trigger) {
		foreach ($db_trigger['items'] as $item) {
			if (!array_key_exists($item['itemid'], $itemid_to_triggerids)) {
				$itemid_to_triggerids[$item['itemid']] = [];
			}
			$itemid_to_triggerids[$item['itemid']][] = $triggerid;
		}
	}

	// Apply values and trigger severity to each $data cell.
	foreach ($data as &$data_clusters) {
		foreach ($data_clusters as &$data_cluster) {
			foreach ($data_cluster as &$item) {
				$itemid = $item['itemid'];

				if (array_key_exists($itemid, $itemid_to_triggerids)) {
					$max_priority = -1;
					$max_priority_triggerid = -1;
					foreach ($itemid_to_triggerids[$itemid] as $triggerid) {
						$trigger = $db_triggers[$triggerid];

						// Bump lower priority triggers of value "true" ahead of triggers with value "false".
						$multiplier = ($trigger['value'] == TRIGGER_VALUE_TRUE) ? TRIGGER_SEVERITY_COUNT : 0;
						if ($trigger['priority'] + $multiplier > $max_priority) {
							$max_priority_triggerid = $triggerid;
							$max_priority = $trigger['priority'] + $multiplier;
						}
					}
					$trigger = $db_triggers[$max_priority_triggerid];
				}
				else {
					$trigger = null;
				}

				$item += [
					'value' => array_key_exists($itemid, $history) ? $history[$itemid][0]['value'] : null,
					'trigger' => $trigger
				];
			}
		}
	}
	unset($data_clusters, $data_cluster, $item);

	return $data;
}

/**
 * @param array  $groupids
 * @param array  $hostids
 * @param array  $tags
 * @param int    $evaltype
 *
 * @return array
 */
function getDataOverviewItems(?array $groupids, ?array $hostids, ?array $tags, int $evaltype): array {
	if ($hostids === null) {
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
		$db_hosts = API::Host()->get([
			'output' => ['name'],
			'groupids' => $groupids,
			'monitored_hosts' => true,
			'with_monitored_items' => true,
			'sortfield' => ['name'],
			'limit' => $limit,
			'preservekeys' => true
		]);

		CArrayHelper::sort($db_hosts, ['name']);
		$db_hosts = array_slice($db_hosts, 0, CSettingsHelper::get(CSettingsHelper::MAX_OVERVIEW_TABLE_SIZE) + 1, true);

		$hostids = array_keys($db_hosts);
	}

	$db_items = API::Item()->get([
		'output' => ['itemid', 'hostid', 'name', 'value_type', 'units', 'valuemapid'],
		'selectHosts' => ['name'],
		'selectValueMap' => ['mappings'],
		'hostids' => $hostids,
		'groupids' => $groupids,
		'evaltype' => $evaltype,
		'tags' => $tags,
		'monitored' => true,
		'webitems' => true,
		'preservekeys' => true
	]);

	CArrayHelper::sort($db_items, [
		['field' => 'name', 'order' => ZBX_SORT_UP],
		['field' => 'itemid', 'order' => ZBX_SORT_UP]
	]);

	return $db_items;
}

/**
 * @param array  $groupids
 * @param array  $hostids
 * @param array  $filter
 * @param array  $filter['tags']
 * @param int    $filter['evaltype']
 * @param int    $filter['show_suppressed']
 *
 * @return array
 */
function getDataOverview(?array $groupids, ?array $hostids, array $filter): array {
	$tags = (array_key_exists('tags', $filter) && $filter['tags']) ? $filter['tags'] : null;
	$evaltype = array_key_exists('evaltype', $filter) ? $filter['evaltype'] : TAG_EVAL_TYPE_AND_OR;

	$db_items = getDataOverviewItems($groupids, $hostids, $tags, $evaltype);

	$data = [];
	$item_counter = [];
	$db_hosts = [];

	foreach ($db_items as $db_item) {
		$item_name = $db_item['name'];
		$host_name = $db_item['hosts'][0]['name'];
		$db_hosts[$db_item['hostid']] = $db_item['hosts'][0];

		if (!array_key_exists($host_name, $item_counter)) {
			$item_counter[$host_name] = [];
		}

		if (!array_key_exists($item_name, $item_counter[$host_name])) {
			$item_counter[$host_name][$item_name] = 0;
		}

		$item_place = $item_counter[$host_name][$item_name];
		$item_counter[$host_name][$item_name]++;

		$item = [
			'itemid' => $db_item['itemid'],
			'value_type' => $db_item['value_type'],
			'units' => $db_item['units'],
			'valuemap' => $db_item['valuemap'],
			'acknowledged' => array_key_exists('acknowledged', $db_item) ? $db_item['acknowledged'] : 0
		];

		if (array_key_exists('triggerid', $db_item)) {
			$item += [
				'triggerid' => $db_item['triggerid'],
				'severity' => $db_item['priority'],
				'tr_value' => $db_item['value']
			];
		}
		else {
			$item += [
				'triggerid' => null,
				'severity' => null,
				'tr_value' => null
			];
		}

		$data[$item_name][$item_place][$host_name] = $item;
	}

	CArrayHelper::sort($db_hosts, [
		['field' => 'name', 'order' => ZBX_SORT_UP]
	]);

	$data_display_limit = (int) CSettingsHelper::get(CSettingsHelper::MAX_OVERVIEW_TABLE_SIZE);
	$has_hidden_data = count($data) > $data_display_limit || count($db_hosts) > $data_display_limit;
	$db_hosts = array_slice($db_hosts, 0, $data_display_limit, true);
	$host_names = array_column($db_hosts, 'name', 'name');

	$itemids = [];
	$items_left = $data_display_limit;

	foreach ($data as &$item_columns) {
		if ($items_left != 0) {
			$item_columns = array_slice($item_columns, 0, min($data_display_limit, $items_left));
			$items_left -= count($item_columns);
		}
		else {
			$item_columns = null;
			break;
		}

		foreach ($item_columns as &$item_column) {
			CArrayHelper::ksort($item_column);
			$item_column = array_slice($item_column, 0, $data_display_limit, true);

			foreach ($item_column as $host_name => $item) {
				if (array_key_exists($host_name, $host_names)) {
					$itemids[$item['itemid']] = true;
				}
				else {
					unset($item_column[$host_name]);
				}
			}
		}
		unset($item_column);

		$item_columns = array_filter($item_columns);
	}
	unset($item_columns);

	$data = array_filter($data);
	$data = array_slice($data, 0, $data_display_limit, true);

	$has_hidden_data = $has_hidden_data || count($db_items) != count($itemids);

	$db_items = array_intersect_key($db_items, $itemids);
	$data = getDataOverviewCellData($db_items, $data, $filter['show_suppressed']);

	return [$data, $db_hosts, $has_hidden_data];
}

/**
 * Prepares interfaces select element with options.
 *
 * @param array $interfaces
 *
 * @return CSelect
 */
function getInterfaceSelect(array $interfaces): CSelect {
	$interface_select = new CSelect('interfaceid');

	/** @var CSelectOption[] $options_by_type */
	$options_by_type = [];

	$interface_select->addOption(new CSelectOption(0, _('None')));

	foreach ($interfaces as $interface) {
		$option = new CSelectOption($interface['interfaceid'], getHostInterface($interface));

		if ($interface['type'] == INTERFACE_TYPE_SNMP) {
			$option->setExtra('description', getSnmpInterfaceDescription($interface));
		}

		$options_by_type[$interface['type']][] = $option;
	}

	foreach (CItem::INTERFACE_TYPES_BY_PRIORITY as $interface_type) {
		if (array_key_exists($interface_type, $options_by_type)) {
			$interface_group = new CSelectOptionGroup((string) interfaceType2str($interface_type));

			if ($interface_type == INTERFACE_TYPE_SNMP) {
				$interface_group->setOptionTemplate('#{label}'.(new CDiv('#{description}'))->addClass('description'));
			}

			$interface_group->addOptions($options_by_type[$interface_type]);

			$interface_select->addOptionGroup($interface_group);
		}
	}

	return $interface_select;
}

/**
 * Creates SNMP interface description.
 *
 * @param array $interface
 * @param int   $interface['details']['version']        Interface SNMP version.
 * @param int   $interface['details']['contextname']    Interface context name for SNMP version 3.
 * @param int   $interface['details']['community']      Interface community for SNMP non version 3 interface.
 * @param int   $interface['details']['securitylevel']  Security level for SNMP version 3 interface.
 * @param int   $interface['details']['authprotocol']   Authentication protocol for SNMP version 3 interface.
 * @param int   $interface['details']['privprotocol']   Privacy protocol for SNMP version 3 interface.
 *
 * @return string
 */
function getSnmpInterfaceDescription(array $interface): string {
	$snmp_desc = [
		_s('SNMPv%1$d', $interface['details']['version'])
	];

	if ($interface['details']['version'] == SNMP_V3) {
		$snmp_desc[] = _('Context name').': '.$interface['details']['contextname'];

		if ($interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
			[$interface['details']['authprotocol'] => $auth_protocol] = getSnmpV3AuthProtocols();
			[$interface['details']['privprotocol'] => $priv_protocol] = getSnmpV3PrivProtocols();

			$snmp_desc[] = '(priv: '.$priv_protocol.', auth: '.$auth_protocol.')';
		}
		elseif ($interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
			[$interface['details']['authprotocol'] => $auth_protocol] = getSnmpV3AuthProtocols();

			$snmp_desc[] = '(auth: '.$auth_protocol.')';
		}

	} else {
		$snmp_desc[] = _x('Community', 'SNMP Community').': '.$interface['details']['community'];
	}

	return implode(', ', $snmp_desc);
}

/**
 * Named SNMPv3 authentication protocols.
 *
 * @return array
 */
function getSnmpV3AuthProtocols(): array {
	return [
		ITEM_SNMPV3_AUTHPROTOCOL_MD5 => 'MD5',
		ITEM_SNMPV3_AUTHPROTOCOL_SHA1 => 'SHA1',
		ITEM_SNMPV3_AUTHPROTOCOL_SHA224 => 'SHA224',
		ITEM_SNMPV3_AUTHPROTOCOL_SHA256 => 'SHA256',
		ITEM_SNMPV3_AUTHPROTOCOL_SHA384 => 'SHA384',
		ITEM_SNMPV3_AUTHPROTOCOL_SHA512 => 'SHA512'
	];
}

/**
 * Named SNMPv3 privacy protocols.
 *
 * @return array
 */
function getSnmpV3PrivProtocols(): array {
	return [
		ITEM_SNMPV3_PRIVPROTOCOL_DES => 'DES',
		ITEM_SNMPV3_PRIVPROTOCOL_AES128 => 'AES128',
		ITEM_SNMPV3_PRIVPROTOCOL_AES192 => 'AES192',
		ITEM_SNMPV3_PRIVPROTOCOL_AES256 => 'AES256',
		ITEM_SNMPV3_PRIVPROTOCOL_AES192C => 'AES192C',
		ITEM_SNMPV3_PRIVPROTOCOL_AES256C => 'AES256C'
	];
}

/**
 * @param array $item
 * @param array $trigger
 *
 * @return CCol
 */
function getItemDataOverviewCell(array $item, ?array $trigger = null): CCol {
	$ack = null;
	$css = '';
	$value = UNKNOWN_VALUE;

	if ($trigger && $trigger['value'] == TRIGGER_VALUE_TRUE) {
		$css = CSeverityHelper::getStyle((int) $trigger['priority']);

		if ($trigger['problem']['acknowledged'] == 1) {
			$ack = [' ', (new CSpan())->addClass(ZBX_STYLE_ICON_ACKN)];
		}
	}

	if ($item['value'] !== null) {
		$value = formatHistoryValue($item['value'], $item);
	}

	$col = (new CCol([$value, $ack]))
		->addClass($css)
		->addClass(ZBX_STYLE_NOWRAP)
		->setMenuPopup(CMenuPopupHelper::getHistory($item['itemid']))
		->addClass(ZBX_STYLE_CURSOR_POINTER);

	return $col;
}

/**
 * Prepare item value for displaying, apply value map and/or convert units.
 *
 * @see formatHistoryValueRaw
 *
 * @param int|float|string  $value
 * @param array             $item
 * @param bool              $trim             Whether to trim non-numeric value to a length of 20 characters.
 * @param array             $convert_options  Options for unit conversion. See @convertUnitsRaw.
 *
 * @return string
 */
function formatHistoryValue($value, array $item, bool $trim = true, array $convert_options = []): string {
	$formatted_value = formatHistoryValueRaw($value, $item, $trim, $convert_options);

	return $formatted_value['value'].($formatted_value['units'] !== '' ? ' '.$formatted_value['units'] : '');
}

/**
 * Prepare item value for displaying, apply value map and/or convert units.
 *
 * @param int|float|string  $value
 * @param array             $item
 * @param bool              $trim             Whether to trim non-numeric value to a length of 20 characters.
 * @param array             $convert_options  Options for unit conversion. See @convertUnitsRaw.
 *
 * $item = [
 *     'value_type' => (int)     ITEM_VALUE_TYPE_FLOAT | ITEM_VALUE_TYPE_UINT64, ...
 *     'units' =>      (string)  Item units.
 *     'valuemap' =>   (array)   Item value map.
 * ]
 *
 * @return array
 */
function formatHistoryValueRaw($value, array $item, bool $trim = true, array $convert_options = []): array {
	$mapped_value = in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_STR])
		? CValueMapHelper::getMappedValue($item['value_type'], $value, $item['valuemap'])
		: false;

	switch ($item['value_type']) {
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			if ($mapped_value !== false) {
				return [
					'value' => $mapped_value.' ('.$value.')',
					'units' => '',
					'is_mapped' => true
				];
			}

			$converted_value = convertUnitsRaw([
				'value' => $value,
				'units' => $item['units']
			] + $convert_options);

			return [
				'value' => $converted_value['value'],
				'units' => $converted_value['units'],
				'is_mapped' => false
			];

		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
		case ITEM_VALUE_TYPE_LOG:
			if ($trim && mb_strlen($value) > 20) {
				$value = mb_substr($value, 0, 20).'...';
			}

			if ($mapped_value !== false) {
				$value = $mapped_value.' ('.$value.')';
			}

			return [
				'value' => $value,
				'units' => '',
				'is_mapped' => $mapped_value !== false
			];

		default:
			return [
				'value' => _('Unknown value type'),
				'units' => '',
				'is_mapped' => false
			];
	}
}

/**
 * Retrieves from DB historical data for items and applies functional calculations.
 * If fails for some reason, returns UNRESOLVED_MACRO_STRING.
 *
 * @param array		$item
 * @param string	$item['value_type']	type of item, allowed: ITEM_VALUE_TYPE_FLOAT and ITEM_VALUE_TYPE_UINT64
 * @param string	$item['itemid']		ID of item
 * @param string	$item['units']		units of item
 * @param string	$function			function to apply to time period from param, allowed: min, max and avg
 * @param string	$parameter			formatted parameter for function, example: "2w" meaning 2 weeks
 *
 * @return string item functional value from history
 */
function getItemFunctionalValue($item, $function, $parameter) {
	// Check whether function is allowed and parameter is specified.
	if (!in_array($function, ['min', 'max', 'avg']) || $parameter === '') {
		return UNRESOLVED_MACRO_STRING;
	}

	// Check whether item type is allowed for min, max and avg functions.
	if ($item['value_type'] != ITEM_VALUE_TYPE_FLOAT && $item['value_type'] != ITEM_VALUE_TYPE_UINT64) {
		return UNRESOLVED_MACRO_STRING;
	}

	$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

	if ($number_parser->parse($parameter) != CParser::PARSE_SUCCESS) {
		return UNRESOLVED_MACRO_STRING;
	}

	$parameter = $number_parser->calcValue();

	$time_from = time() - $parameter;

	if ($time_from < 0 || $time_from > ZBX_MAX_DATE) {
		return UNRESOLVED_MACRO_STRING;
	}

	$result = Manager::History()->getAggregatedValue($item, $function, $time_from);

	if ($result !== null) {
		return convertUnits(['value' => $result, 'units' => $item['units']]);
	}
	else {
		return UNRESOLVED_MACRO_STRING;
	}
}

/**
 * Check if current time is within the given period.
 *
 * @param string $period	time period format: "wd[-wd2],hh:mm-hh:mm"
 * @param int $now			current timestamp
 *
 * @return bool		true - within period, false - out of period
 */
function checkTimePeriod($period, $now) {
	if (sscanf($period, '%d-%d,%d:%d-%d:%d', $d1, $d2, $h1, $m1, $h2, $m2) != 6) {
		if (sscanf($period, '%d,%d:%d-%d:%d', $d1, $h1, $m1, $h2, $m2) != 5) {
			// delay period format is wrong - skip
			return false;
		}
		$d2 = $d1;
	}

	$tm = localtime($now, true);
	$day = ($tm['tm_wday'] == 0) ? 7 : $tm['tm_wday'];
	$sec = SEC_PER_HOUR * $tm['tm_hour'] + SEC_PER_MIN * $tm['tm_min'] + $tm['tm_sec'];

	$sec1 = SEC_PER_HOUR * $h1 + SEC_PER_MIN * $m1;
	$sec2 = SEC_PER_HOUR * $h2 + SEC_PER_MIN * $m2;

	return $d1 <= $day && $day <= $d2 && $sec1 <= $sec && $sec < $sec2;
}

/**
 * Get item minimum delay.
 *
 * @param string $delay
 * @param array $flexible_intervals
 *
 * @return string
 */
function getItemDelay($delay, array $flexible_intervals) {
	$delay = timeUnitToSeconds($delay);

	if ($delay != 0 || !$flexible_intervals) {
		return $delay;
	}

	$min_delay = SEC_PER_YEAR;

	foreach ($flexible_intervals as $flexible_interval) {
		$flexible_interval_parts = explode('/', $flexible_interval);
		$flexible_delay = timeUnitToSeconds($flexible_interval_parts[0]);

		$min_delay = min($min_delay, $flexible_delay);
	}

	return $min_delay;
}

/**
 * Return delay value that is currently applicable
 *
 * @param int $delay					default delay
 * @param array $flexible_intervals		array of intervals in format: "d/wd[-wd2],hh:mm-hh:mm"
 * @param int $now						current timestamp
 *
 * @return int							delay for a current timestamp
 */
function getCurrentDelay($delay, array $flexible_intervals, $now) {
	if (!$flexible_intervals) {
		return $delay;
	}

	$current_delay = -1;

	foreach ($flexible_intervals as $flexible_interval) {
		list($flexible_delay, $flexible_period) = explode('/', $flexible_interval);
		$flexible_delay = (int) $flexible_delay;

		if (($current_delay == -1 || $flexible_delay < $current_delay) && checkTimePeriod($flexible_period, $now)) {
			$current_delay = $flexible_delay;
		}
	}

	if ($current_delay == -1) {
		return $delay;
	}

	return $current_delay;
}

/**
 * Return time of next flexible interval
 *
 * @param array $flexible_intervals  array of intervals in format: "d/wd[-wd2],hh:mm-hh:mm"
 * @param int $now                   current timestamp
 * @param int $next_interval          timestamp of a next interval
 *
 * @return bool                      false if no flexible intervals defined
 */
function getNextDelayInterval(array $flexible_intervals, $now, &$next_interval) {
	if (!$flexible_intervals) {
		return false;
	}

	$next = 0;
	$tm = localtime($now, true);
	$day = ($tm['tm_wday'] == 0) ? 7 : $tm['tm_wday'];
	$sec = SEC_PER_HOUR * $tm['tm_hour'] + SEC_PER_MIN * $tm['tm_min'] + $tm['tm_sec'];

	foreach ($flexible_intervals as $flexible_interval) {
		$flexible_interval_parts = explode('/', $flexible_interval);

		if (sscanf($flexible_interval_parts[1], '%d-%d,%d:%d-%d:%d', $d1, $d2, $h1, $m1, $h2, $m2) != 6) {
			if (sscanf($flexible_interval_parts[1], '%d,%d:%d-%d:%d', $d1, $h1, $m1, $h2, $m2) != 5) {
				continue;
			}
			$d2 = $d1;
		}

		$sec1 = SEC_PER_HOUR * $h1 + SEC_PER_MIN * $m1;
		$sec2 = SEC_PER_HOUR * $h2 + SEC_PER_MIN * $m2;

		// current period
		if ($d1 <= $day && $day <= $d2 && $sec1 <= $sec && $sec < $sec2) {
			if ($next == 0 || $next > $now - $sec + $sec2) {
				// the next second after the current interval's upper bound
				$next = $now - $sec + $sec2;
			}
		}
		// will be active today
		elseif ($d1 <= $day && $d2 >= $day && $sec < $sec1) {
			if ($next == 0 || $next > $now - $sec + $sec1) {
				$next = $now - $sec + $sec1;
			}
		}
		else {
			$nextDay = ($day + 1 <= 7) ? $day + 1 : 1;

			// will be active tomorrow
			if ($d1 <= $nextDay && $nextDay <= $d2) {
				if ($next == 0 || $next > $now - $sec + SEC_PER_DAY + $sec1) {
					$next = $now - $sec + SEC_PER_DAY + $sec1;
				}
			}
			// later in the future
			else {
				$dayDiff = -1;

				if ($day < $d1) {
					$dayDiff = $d1 - $day;
				}
				if ($day >= $d2) {
					$dayDiff = ($d1 + 7) - $day;
				}
				if ($d1 <= $day && $day < $d2) {
					// should never happen, could not deduce day difference
					$dayDiff = -1;
				}
				if ($dayDiff != -1 && ($next == 0 || $next > $now - $sec + SEC_PER_DAY * $dayDiff + $sec1)) {
					$next = $now - $sec + SEC_PER_DAY * $dayDiff + $sec1;
				}
			}
		}
	}

	if ($next != 0) {
		$next_interval = $next;
	}

	return ($next != 0);
}

/**
 * Calculate nextcheck timestamp for an item using flexible intervals.
 *
 * the parameter $flexible_intervals is an array if strings that are in the following format:
 *
 *           +------------[;]<----------+
 *           |                          |
 *         ->+-[d/wd[-wd2],hh:mm-hh:mm]-+
 *
 *         d       - delay (0-n)
 *         wd, wd2 - day of week (1-7)
 *         hh      - hours (0-24)
 *         mm      - minutes (0-59)
 *
 * @param int $seed						seed value applied to delay to spread item checks over the delay period
 * @param string $delay					default delay, can be overridden
 * @param array $flexible_intervals		array of flexible intervals
 * @param int $now						current timestamp
 *
 * @return int
 */
function calculateItemNextCheck($seed, $delay, $flexible_intervals, $now) {
	/*
	 * Try to find the nearest 'nextcheck' value with condition 'now' < 'nextcheck' < 'now' + SEC_PER_YEAR
	 * If it is not possible to check the item within a year, fail.
	 */

	$t = $now;
	$tMax = $now + SEC_PER_YEAR;
	$try = 0;

	while ($t < $tMax) {
		// Calculate 'nextcheck' value for the current interval.
		$currentDelay = getCurrentDelay($delay, $flexible_intervals, $t);

		if ($currentDelay != 0) {
			$nextCheck = $currentDelay * floor($t / $currentDelay) + ($seed % $currentDelay);

			if ($try == 0) {
				while ($nextCheck <= $t) {
					$nextCheck += $currentDelay;
				}
			}
			else {
				while ($nextCheck < $t) {
					$nextCheck += $currentDelay;
				}
			}
		}
		else {
			$nextCheck = ZBX_JAN_2038;
		}

		/*
		 * Is 'nextcheck' < end of the current interval and the end of the current interval
		 * is the beginning of the next interval - 1.
		 */
		if (getNextDelayInterval($flexible_intervals, $t, $nextInterval) && $nextCheck >= $nextInterval) {
			// 'nextcheck' is beyond the current interval.
			$t = $nextInterval;
			$try++;
		}
		else {
			break;
		}
	}

	return $nextCheck;
}

/*
 * Description:
 *	Function returns true if http items exists in the $items array.
 *	The array should contain a field 'type'
 */
function httpItemExists($items) {
	foreach ($items as $item) {
		if ($item['type'] == ITEM_TYPE_HTTPTEST) {
			return true;
		}
	}
	return false;
}

function getParamFieldNameByType($itemType) {
	switch ($itemType) {
		case ITEM_TYPE_SCRIPT:
			return 'script';
		case ITEM_TYPE_SSH:
		case ITEM_TYPE_TELNET:
		case ITEM_TYPE_JMX:
			return 'params_es';
		case ITEM_TYPE_DB_MONITOR:
			return 'params_ap';
		case ITEM_TYPE_CALCULATED:
			return 'params_f';
		default:
			return 'params';
	}
}

function getParamFieldLabelByType($itemType) {
	switch ($itemType) {
		case ITEM_TYPE_SCRIPT:
			return _('Script');
		case ITEM_TYPE_SSH:
		case ITEM_TYPE_TELNET:
		case ITEM_TYPE_JMX:
			return _('Executed script');
		case ITEM_TYPE_DB_MONITOR:
			return _('SQL query');
		case ITEM_TYPE_CALCULATED:
			return _('Formula');
		default:
			return 'params';
	}
}

/**
 * Get either one or all item preprocessing types.
 * If $grouped set to true, returns group labels. Returns empty string if no specific type is found.
 *
 * Usage examples:
 *    - get_preprocessing_types(null, true, [5, 4, 2])             Returns array as defined.
 *    - get_preprocessing_types(4, true, [5, 4, 2])                Returns string: 'Trim'.
 *    - get_preprocessing_types(<wrong type>, true, [5, 4, 2])     Returns an empty string: ''.
 *    - get_preprocessing_types(null, false, [5, 12, 15, 16, 20])  Returns subarrays in one array maintaining index:
 *                                                                     [5] => Regular expression
 *                                                                     [12] => JSONPath
 *                                                                     [15] => Does not match regular expression
 *                                                                     [16] => Check for error in JSON
 *                                                                     [20] => Discard unchanged with heartbeat
 *
 * @param int   $type             Item preprocessing type.
 * @param bool  $grouped          Group label flag. If specific type is given, this parameter does not matter.
 * @param array $supported_types  Array of supported pre-processing types. If none are given, empty array is returned.
 *
 * @return array|string
 */
function get_preprocessing_types($type = null, $grouped = true, array $supported_types = []) {
	$types = [
		ZBX_PREPROC_REGSUB => [
			'group' => _('Text'),
			'name' => _('Regular expression')
		],
		ZBX_PREPROC_STR_REPLACE => [
			'group' => _('Text'),
			'name' => _('Replace')
		],
		ZBX_PREPROC_TRIM => [
			'group' => _('Text'),
			'name' => _('Trim')
		],
		ZBX_PREPROC_RTRIM => [
			'group' => _('Text'),
			'name' => _('Right trim')
		],
		ZBX_PREPROC_LTRIM => [
			'group' => _('Text'),
			'name' => _('Left trim')
		],
		ZBX_PREPROC_XPATH => [
			'group' => _('Structured data'),
			'name' => _('XML XPath')
		],
		ZBX_PREPROC_JSONPATH => [
			'group' => _('Structured data'),
			'name' => _('JSONPath')
		],
		ZBX_PREPROC_CSV_TO_JSON => [
			'group' => _('Structured data'),
			'name' => _('CSV to JSON')
		],
		ZBX_PREPROC_XML_TO_JSON => [
			'group' => _('Structured data'),
			'name' => _('XML to JSON')
		],
		ZBX_PREPROC_SNMP_WALK_VALUE => [
			'group' => _('SNMP'),
			'name' => _('SNMP walk value')
		],
		ZBX_PREPROC_SNMP_WALK_TO_JSON => [
			'group' => _('SNMP'),
			'name' => _('SNMP walk to JSON')
		],
		ZBX_PREPROC_MULTIPLIER => [
			'group' => _('Arithmetic'),
			'name' => _('Custom multiplier')
		],
		ZBX_PREPROC_DELTA_VALUE => [
			'group' => _x('Change', 'noun'),
			'name' => _('Simple change')
		],
		ZBX_PREPROC_DELTA_SPEED => [
			'group' => _x('Change', 'noun'),
			'name' => _('Change per second')
		],
		ZBX_PREPROC_BOOL2DEC => [
			'group' => _('Numeral systems'),
			'name' => _('Boolean to decimal')
		],
		ZBX_PREPROC_OCT2DEC => [
			'group' => _('Numeral systems'),
			'name' => _('Octal to decimal')
		],
		ZBX_PREPROC_HEX2DEC => [
			'group' => _('Numeral systems'),
			'name' => _('Hexadecimal to decimal')
		],
		ZBX_PREPROC_SCRIPT => [
			'group' => _('Custom scripts'),
			'name' => _('JavaScript')
		],
		ZBX_PREPROC_VALIDATE_RANGE => [
			'group' => _('Validation'),
			'name' => _('In range')
		],
		ZBX_PREPROC_VALIDATE_REGEX => [
			'group' => _('Validation'),
			'name' => _('Matches regular expression')
		],
		ZBX_PREPROC_VALIDATE_NOT_REGEX => [
			'group' => _('Validation'),
			'name' => _('Does not match regular expression')
		],
		ZBX_PREPROC_ERROR_FIELD_JSON => [
			'group' => _('Validation'),
			'name' => _('Check for error in JSON')
		],
		ZBX_PREPROC_ERROR_FIELD_XML => [
			'group' => _('Validation'),
			'name' => _('Check for error in XML')
		],
		ZBX_PREPROC_ERROR_FIELD_REGEX => [
			'group' => _('Validation'),
			'name' => _('Check for error using regular expression')
		],
		ZBX_PREPROC_VALIDATE_NOT_SUPPORTED => [
			'group' => _('Validation'),
			'name' => _('Check for not supported value')
		],
		ZBX_PREPROC_THROTTLE_VALUE => [
			'group' => _('Throttling'),
			'name' => _('Discard unchanged')
		],
		ZBX_PREPROC_THROTTLE_TIMED_VALUE => [
			'group' => _('Throttling'),
			'name' => _('Discard unchanged with heartbeat')
		],
		ZBX_PREPROC_PROMETHEUS_PATTERN => [
			'group' => _('Prometheus'),
			'name' => _('Prometheus pattern')
		],
		ZBX_PREPROC_PROMETHEUS_TO_JSON => [
			'group' => _('Prometheus'),
			'name' => _('Prometheus to JSON')
		]
	];

	$filtered_types = [];

	foreach ($types as $_type => $data) {
		if (in_array($_type, $supported_types)) {
			$filtered_types[$data['group']][$_type] = $data['name'];
		}
	}

	$groups = [];

	foreach ($filtered_types as $label => $types) {
		$groups[] = [
			'label' => $label,
			'types' => $types
		];
	}

	if ($type !== null) {
		foreach ($groups as $group) {
			if (array_key_exists($type, $group['types'])) {
				return $group['types'][$type];
			}
		}

		return '';
	}
	elseif ($grouped) {
		return $groups;
	}
	else {
		$types = [];

		foreach ($groups as $group) {
			$types += $group['types'];
		}

		return $types;
	}
}

/*
 * Quoting $param if it contain special characters.
 *
 * @param string $param
 * @param bool   $forced
 *
 * @return string
 */
function quoteItemKeyParam($param, $forced = false) {
	if (!$forced) {
		if (!isset($param[0]) || ($param[0] != '"' && false === strpbrk($param, ',]'))) {
			return $param;
		}
	}

	return '"'.str_replace('"', '\\"', $param).'"';
}

/**
 * Expands item name and for dependent item master item name.
 *
 * @param array  $items        Array of items.
 * @param string $data_source  'items' or 'itemprototypes'.
 *
 * @return array
 */
function expandItemNamesWithMasterItems($items, $data_source) {
	$itemids = [];
	$master_itemids = [];

	foreach ($items as $item_index => &$item) {
		if ($item['type'] == ITEM_TYPE_DEPENDENT) {
			$master_itemids[$item['master_itemid']] = true;
		}

		// The "source" is required to tell the frontend where the link should point at - item or item prototype.
		$item['source'] = $data_source;
		$itemids[$item_index] = $item['itemid'];
	}
	unset($item);

	$master_itemids = array_diff(array_keys($master_itemids), $itemids);

	if ($master_itemids) {
		$options = [
			'output' => ['itemid', 'type', 'name'],
			'itemids' => $master_itemids,
			'editable' => true,
			'preservekeys' => true
		];
		$master_items = API::Item()->get($options + ['webitems' => true]);

		foreach ($master_items as &$master_item) {
			$master_item['source'] = 'items';
		}
		unset($master_item);

		$master_item_prototypes = API::ItemPrototype()->get($options);

		foreach ($master_item_prototypes as &$master_item_prototype) {
			$master_item_prototype['source'] = 'itemprototypes';
		}
		unset($master_item_prototype);

		$master_items += $master_item_prototypes;
	}

	foreach ($items as &$item) {
		if ($item['type'] == ITEM_TYPE_DEPENDENT) {
			$master_itemid = $item['master_itemid'];
			$items_index = array_search($master_itemid, $itemids);
			$item['master_item'] = array_fill_keys(['name', 'type', 'source'], '');
			$item['master_item'] = ($items_index === false)
				? array_intersect_key($master_items[$master_itemid], $item['master_item'])
				: array_intersect_key($items[$items_index], $item['master_item']);
			$item['master_item']['itemid'] = $master_itemid;
		}
	}
	unset($item);

	return $items;
}

/**
 * Returns an array of allowed item types for "Check now" functionality.
 *
 * @return array
 */
function checkNowAllowedTypes() {
	return [
		ITEM_TYPE_ZABBIX,
		ITEM_TYPE_SIMPLE,
		ITEM_TYPE_INTERNAL,
		ITEM_TYPE_EXTERNAL,
		ITEM_TYPE_DB_MONITOR,
		ITEM_TYPE_IPMI,
		ITEM_TYPE_SSH,
		ITEM_TYPE_TELNET,
		ITEM_TYPE_CALCULATED,
		ITEM_TYPE_JMX,
		ITEM_TYPE_DEPENDENT,
		ITEM_TYPE_HTTPAGENT,
		ITEM_TYPE_SNMP,
		ITEM_TYPE_SCRIPT
	];
}

/**
 * Validates update interval for items, item prototypes and low-level discovery rules and their overrides.
 *
 * @param CUpdateIntervalParser $parser [IN]      Parser used for delay validation.
 * @param string                $value  [IN]      Update interval to parse and validate.
 * @param string                $field_name [IN]  Frontend or API field name in the error
 * @param string                $error  [OUT]     Returned error string if delay validation fails.
 *
 * @return bool
 */
function validateDelay(CUpdateIntervalParser $parser, $field_name, $value, &$error) {
	if ($parser->parse($value) != CParser::PARSE_SUCCESS) {
		$error = _s('Incorrect value for field "%1$s": %2$s.', $field_name, _('invalid delay'));

		return false;
	}

	$delay = $parser->getDelay();

	if ($delay[0] !== '{') {
		$delay_sec = timeUnitToSeconds($delay);
		$intervals = $parser->getIntervals();
		$flexible_intervals = $parser->getIntervals(ITEM_DELAY_FLEXIBLE);
		$has_scheduling_intervals = (bool) $parser->getIntervals(ITEM_DELAY_SCHEDULING);
		$has_macros = false;

		foreach ($intervals as $interval) {
			if (strpos($interval['interval'], '{') !== false) {
				$has_macros = true;
				break;
			}
		}

		// If delay is 0, there must be at least one either flexible or scheduling interval.
		if ($delay_sec == 0 && !$intervals) {
			$error = _('Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.');

			return false;
		}
		elseif ($delay_sec < 0 || $delay_sec > SEC_PER_DAY) {
			$error = _('Item will not be refreshed. Update interval should be between 1s and 1d. Also Scheduled/Flexible intervals can be used.');

			return false;
		}

		// If there are scheduling intervals or intervals with macros, skip the next check calculation.
		if (!$has_macros && !$has_scheduling_intervals && $flexible_intervals
				&& calculateItemNextCheck(0, $delay_sec, $flexible_intervals, time()) == ZBX_JAN_2038) {
			$error = _('Item will not be refreshed. Please enter a correct update interval.');

			return false;
		}
	}

	return true;
}

/**
 * Normalizes item preprocessing step parameters after item preprocessing form submit.
 *
 * @param array $preprocessing  Array of item preprocessing steps, as received from form submit.
 *
 * @return array
 */
function normalizeItemPreprocessingSteps(array $preprocessing): array {
	foreach ($preprocessing as &$step) {
		switch ($step['type']) {
			case ZBX_PREPROC_MULTIPLIER:
			case ZBX_PREPROC_PROMETHEUS_TO_JSON:
				$step['params'] = trim($step['params'][0]);
				break;

			case ZBX_PREPROC_RTRIM:
			case ZBX_PREPROC_LTRIM:
			case ZBX_PREPROC_TRIM:
			case ZBX_PREPROC_XPATH:
			case ZBX_PREPROC_JSONPATH:
			case ZBX_PREPROC_VALIDATE_REGEX:
			case ZBX_PREPROC_VALIDATE_NOT_REGEX:
			case ZBX_PREPROC_ERROR_FIELD_JSON:
			case ZBX_PREPROC_ERROR_FIELD_XML:
			case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
			case ZBX_PREPROC_SCRIPT:
				$step['params'] = $step['params'][0];
				break;

			case ZBX_PREPROC_SNMP_WALK_VALUE:
			case ZBX_PREPROC_VALIDATE_RANGE:
				foreach ($step['params'] as &$param) {
					$param = trim($param);
				}
				unset($param);

				$step['params'] = implode("\n", $step['params']);
				break;

			case ZBX_PREPROC_PROMETHEUS_PATTERN:
				foreach ($step['params'] as &$param) {
					$param = trim($param);
				}
				unset($param);

				if (in_array($step['params'][1], [ZBX_PREPROC_PROMETHEUS_SUM, ZBX_PREPROC_PROMETHEUS_MIN,
						ZBX_PREPROC_PROMETHEUS_MAX, ZBX_PREPROC_PROMETHEUS_AVG, ZBX_PREPROC_PROMETHEUS_COUNT])) {
					$step['params'][2] = $step['params'][1];
					$step['params'][1] = ZBX_PREPROC_PROMETHEUS_FUNCTION;
				}

				if (!array_key_exists(2, $step['params'])) {
					$step['params'][2] = '';
				}

				$step['params'] = implode("\n", $step['params']);
				break;

			case ZBX_PREPROC_REGSUB:
			case ZBX_PREPROC_ERROR_FIELD_REGEX:
			case ZBX_PREPROC_STR_REPLACE:
				$step['params'] = implode("\n", $step['params']);
				break;

			case ZBX_PREPROC_CSV_TO_JSON:
				if (!array_key_exists(2, $step['params'])) {
					$step['params'][2] = ZBX_PREPROC_CSV_NO_HEADER;
				}
				$step['params'] = implode("\n", $step['params']);
				break;

			case ZBX_PREPROC_SNMP_WALK_TO_JSON:
				$step['params'] = array_values($step['params']);

				$step['params'] = implode("\n", array_map(function (string $value): string {
					return trim($value);
				}, $step['params']));
				break;

			default:
				$step['params'] = '';
		}

		$step += [
			'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
			'error_handler_params' => ''
		];

		// Remove fictional fields that don't belong to DB and API.
		unset($step['sortorder'], $step['on_fail']);
	}
	unset($step);

	return $preprocessing;
}

/**
 * Check that the given key is not equal to the example value presented for a specific type.
 *
 * @param int    $type
 * @param string $key
 *
 * @return bool
 */
function isItemExampleKey(int $type, string $key): bool {
	if (($type == ITEM_TYPE_DB_MONITOR && $key === ZBX_DEFAULT_KEY_DB_MONITOR)
			|| ($type == ITEM_TYPE_SSH && $key === ZBX_DEFAULT_KEY_SSH)
			|| ($type == ITEM_TYPE_TELNET && $key === ZBX_DEFAULT_KEY_TELNET)) {
		error(_('Check the key, please. Default example was passed.'));

		return true;
	}

	return false;
}

/**
 * Check the format of the given custom intervals. Unset the custom intervals with empty values.
 *
 * @param array  $delay_flex
 * @param bool   $lldmacros
 */
function isValidCustomIntervals(array &$delay_flex, bool $lldmacros = false): bool {
	if (!$delay_flex) {
		return true;
	}

	$simple_interval_parser = new CSimpleIntervalParser([
		'usermacros' => true,
		'lldmacros' => $lldmacros
	]);

	$time_period_parser = new CTimePeriodParser([
		'usermacros' => true,
		'lldmacros' => $lldmacros
	]);

	$scheduling_interval_parser = new CSchedulingIntervalParser([
		'usermacros' => true,
		'lldmacros' => $lldmacros
	]);

	foreach ($delay_flex as $i => $interval) {
		if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
			if ($interval['delay'] === '' && $interval['period'] === '') {
				unset($delay_flex[$i]);
				continue;
			}

			if ($simple_interval_parser->parse($interval['delay']) != CParser::PARSE_SUCCESS) {
				error(_s('Invalid interval "%1$s".', $interval['delay']));

				return false;
			}
			elseif ($time_period_parser->parse($interval['period']) != CParser::PARSE_SUCCESS) {
				error(_s('Invalid interval "%1$s".', $interval['period']));

				return false;
			}
		}
		else {
			if ($interval['schedule'] === '') {
				unset($delay_flex[$i]);
				continue;
			}

			if ($scheduling_interval_parser->parse($interval['schedule']) != CParser::PARSE_SUCCESS) {
				error(_s('Invalid interval "%1$s".', $interval['schedule']));

				return false;
			}
		}
	}

	return true;
}

/**
 * Get all given delay intervals as string in API format.
 *
 * @param string $delay
 * @param array  $delay_flex
 *
 * @return string
 */
function getDelayWithCustomIntervals(string $delay, array $delay_flex): string {
	foreach ($delay_flex as $interval) {
		if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
			$delay .= ';'.$interval['delay'].'/'.$interval['period'];
		}
		else {
			$delay .= ';'.$interval['schedule'];
		}
	}

	return $delay;
}

/**
 * Format tags received via form for API input.
 *
 * @param array $tags  Array of item tags, as received from form submit.
 *
 * @return array
 */
function prepareItemTags(array $tags): array {
	foreach ($tags as $key => $tag) {
		if ($tag['tag'] === '' && $tag['value'] === '') {
			unset($tags[$key]);
		}
		elseif (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
			unset($tags[$key]);
		}
		else {
			unset($tags[$key]['type']);
		}
	}

	return $tags;
}

/**
 * Format query fields received via form for API input.
 *
 * @param array $query_fields
 *
 * @return array
 */
function prepareItemQueryFields(array $query_fields): array {
	if ($query_fields) {
		$_query_fields = [];

		foreach ($query_fields['name'] as $index => $key) {
			$value = $query_fields['value'][$index];
			$sortorder = $query_fields['sortorder'][$index];

			if ($key !== '' || $value !== '') {
				$_query_fields[$sortorder] = [$key => $value];
			}
		}

		ksort($_query_fields);
		$query_fields = array_values($_query_fields);
	}

	return $query_fields;
}

/**
 * Format headers field received via form for API input.
 *
 * @param array $headers
 *
 * @return array
 */
function prepareItemHeaders(array $headers): array {
	if ($headers) {
		$_headers = [];

		foreach ($headers['name'] as $i => $name) {
			$value = $headers['value'][$i];

			if ($name === '' && $value === '') {
				continue;
			}

			$_headers[$name] = $value;
		}

		$headers = $_headers;
	}

	return $headers;
}

/**
 * Format parameters field received via form for API input.
 *
 * @param array $parameters
 *
 * @return array
 */
function prepareItemParameters(array $parameters): array {
	$_parameters = [];

	if (is_array($parameters) && array_key_exists('name', $parameters)
			&& array_key_exists('value', $parameters)) {
		foreach ($parameters['name'] as $index => $name) {
			if (array_key_exists($index, $parameters['value'])
					&& ($name !== '' || $parameters['value'][$index] !== '')) {
				$_parameters[] = [
					'name' => $name,
					'value' => $parameters['value'][$index]
				];
			}
		}
	}

	return $_parameters;
}

/**
 * Get sanitized item fields of given input.
 *
 * @param array  $input
 * @param string $input['templateid']
 * @param int    $input['flags']
 * @param int    $input['type']
 * @param string $input['key_']
 * @param int    $input['value_type']
 * @param int    $input['authtype']
 * @param int    $input['allow_traps']
 * @param int    $input['hosts'][0]['status']
 *
 * @return array
 */
function getSanitizedItemFields(array $input): array {
	$field_names = getMainItemFieldNames($input);

	if ($input['flags'] != ZBX_FLAG_DISCOVERY_CREATED) {
		$field_names = array_merge($field_names, getTypeItemFieldNames($input));
		$field_names = getConditionalItemFieldNames($field_names, $input);
	}

	return array_intersect_key($input, array_flip($field_names));
}

/**
 * Get main item fields of given input.
 *
 * @param array  $input
 * @param string $input['templateid']
 * @param int    $input['flags']
 *
 * @return array
 */
function getMainItemFieldNames(array $input): array {
	switch ($input['flags']) {
		case ZBX_FLAG_DISCOVERY_NORMAL:
			if ($input['templateid'] == 0) {
				return ['name', 'type', 'key_', 'value_type', 'units', 'history', 'trends', 'valuemapid',
					'inventory_link', 'logtimefmt', 'description', 'status', 'tags', 'preprocessing'
				];
			}
			else {
				return ['history', 'trends', 'inventory_link', 'description', 'status', 'tags'];
			}

		case ZBX_FLAG_DISCOVERY_PROTOTYPE:
			if ($input['templateid'] == 0) {
				return ['name', 'type', 'key_', 'value_type', 'units', 'history', 'trends', 'valuemapid', 'logtimefmt',
					'description', 'status', 'discover', 'tags', 'preprocessing'
				];
			}
			else {
				return ['history', 'trends', 'description', 'status', 'discover', 'tags'];
			}

		case ZBX_FLAG_DISCOVERY_CREATED:
			return ['status'];
	}
}

/**
 * Get item field names of the given type and template ID.
 *
 * @param array  $input
 * @param string $input['templateid']
 * @param int    $input['type']
 */
function getTypeItemFieldNames(array $input): array {
	switch ($input['type']) {
		case ITEM_TYPE_ZABBIX:
			return ['interfaceid', 'delay'];

		case ITEM_TYPE_TRAPPER:
			return ['trapper_hosts'];

		case ITEM_TYPE_SIMPLE:
			return ['interfaceid', 'username', 'password', 'delay'];

		case ITEM_TYPE_INTERNAL:
			return ['delay'];

		case ITEM_TYPE_ZABBIX_ACTIVE:
			return ['delay'];

		case ITEM_TYPE_EXTERNAL:
			return ['interfaceid', 'delay'];

		case ITEM_TYPE_DB_MONITOR:
			return ['username', 'password', 'params', 'delay'];

		case ITEM_TYPE_IPMI:
			if ($input['templateid'] == 0) {
				return ['interfaceid', 'ipmi_sensor', 'delay'];
			}
			else {
				return ['interfaceid', 'delay'];
			}

		case ITEM_TYPE_SSH:
			return ['interfaceid', 'authtype', 'username', 'publickey', 'privatekey', 'password', 'params', 'delay'];

		case ITEM_TYPE_TELNET:
			return ['interfaceid', 'username', 'password', 'params', 'delay'];

		case ITEM_TYPE_CALCULATED:
			return ['params', 'delay'];

		case ITEM_TYPE_JMX:
			if ($input['templateid'] == 0) {
				return ['interfaceid', 'jmx_endpoint', 'username', 'password', 'delay'];
			}
			else {
				return ['interfaceid', 'username', 'password', 'delay'];
			}

		case ITEM_TYPE_SNMPTRAP:
			return ['interfaceid'];

		case ITEM_TYPE_DEPENDENT:
			if ($input['templateid'] == 0) {
				return ['master_itemid'];
			}

			return [];

		case ITEM_TYPE_HTTPAGENT:
			if ($input['templateid'] == 0) {
				return ['url', 'query_fields', 'request_method', 'post_type', 'posts', 'headers', 'status_codes',
					'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy', 'interfaceid', 'authtype',
					'username', 'password', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file',
					'ssl_key_password', 'timeout', 'delay', 'allow_traps', 'trapper_hosts'
				];
			}
			else {
				return ['interfaceid', 'delay', 'allow_traps', 'trapper_hosts'];
			}

		case ITEM_TYPE_SNMP:
			if ($input['templateid'] == 0) {
				return ['interfaceid', 'snmp_oid', 'delay'];
			}
			else {
				return ['interfaceid', 'delay'];
			}

		case ITEM_TYPE_SCRIPT:
			if ($input['templateid'] == 0) {
				return ['parameters', 'params', 'timeout', 'delay'];
			}
			else {
				return ['delay'];
			}
	}
}

/**
 * Get item field names excluding those that don't match a specific conditions.
 *
 * @param array  $field_names
 * @param array  $input
 * @param int    $input['type']
 * @param string $input['key_']
 * @param int    $input['value_type']
 * @param int    $input['authtype']
 * @param int    $input['allow_traps']
 * @param int    $input['hosts'][0]['status']
 *
 * @return array
 */
function getConditionalItemFieldNames(array $field_names, array $input): array {
	return array_filter($field_names, static function ($field_name) use ($input): bool {
		switch ($field_name) {
			case 'units':
			case 'trends':
				return in_array($input['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);

			case 'valuemapid':
				return in_array($input['value_type'],
					[ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64]
				);

			case 'inventory_link':
				return in_array($input['value_type'],
					[ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT]
				);

			case 'logtimefmt':
				return $input['value_type'] == ITEM_VALUE_TYPE_LOG;

			case 'interfaceid':
				return in_array($input['hosts'][0]['status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);

			case 'username':
			case 'password':
				return $input['type'] != ITEM_TYPE_HTTPAGENT || in_array($input['authtype'],
					[ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST]
				);

			case 'delay':
				return $input['type'] != ITEM_TYPE_ZABBIX_ACTIVE || strncmp($input['key_'], 'mqtt.get', 8) != 0;

			case 'trapper_hosts':
				return $input['type'] != ITEM_TYPE_HTTPAGENT || $input['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON;

			case 'publickey':
			case 'privatekey':
				return $input['authtype'] == ITEM_AUTHTYPE_PUBLICKEY;
		}

		return true;
	});
}
