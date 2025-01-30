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
		ITEM_TYPE_SCRIPT => _('Script'),
		ITEM_TYPE_BROWSER => _('Browser')
	];

	if ($type === null) {
		return $types;
	}

	return array_key_exists($type, $types) ? $types[$type] : _('Unknown');
}

/**
 * Returns label for value type.
 *
 * @param int $value_type
 *
 * @return string
 */
function itemValueTypeString($value_type): string {
	switch ($value_type) {
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
		case ITEM_VALUE_TYPE_BINARY:
			return _('Binary');
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
				|| ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($item['key_'], 'mqtt.get', 8) == 0)) {
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
			$url = (new CUrl('zabbix.php'))
				->setArgument('action', 'item.prototype.list')
				->setArgument('parent_discoveryid', $parent_templates['links'][$itemid]['lld_ruleid'])
				->setArgument('context', 'template');
		}
		// ZBX_FLAG_DISCOVERY_NORMAL
		else {
			$url = (new CUrl('zabbix.php'))
				->setArgument('action', 'item.list')
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
				$name = new CLink($template['name'], $url);
			}
			elseif ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$item_url = (new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'item.prototype.edit')
					->setArgument('context', 'template')
					->setArgument('itemid', $parent_templates['links'][$itemid]['itemid'])
					->setArgument('parent_discoveryid', $parent_templates['links'][$itemid]['lld_ruleid'])
					->getUrl();

				$name = new CLink($template['name'], $item_url);
			}
			// ZBX_FLAG_DISCOVERY_NORMAL
			else {
				$item_url = (new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'item.edit')
					->setArgument('context', 'template')
					->setArgument('hostid', $parent_templates['links'][$itemid]['hostid'])
					->setArgument('itemid', $parent_templates['links'][$itemid]['itemid'])
					->getUrl();

				$name = new CLink($template['name'], $item_url);
			}
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
 * Prepare aggregated item value for displaying, apply value map and/or convert units if appropriate for the aggregation
 * function.
 *
 * @see formatAggregatedHistoryValueRaw
 *
 * @param int|float|string $value
 * @param array            $item
 * @param int              $function         Aggregation function (AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX,
 *                                           AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST,
 *                                           AGGREGATE_LAST).
 * @param bool             $force_units      Whether to keep units despite the aggregation function not supporting it.
 * @param bool             $trim             Whether to trim non-numeric value to a length of 20 characters.
 * @param array            $convert_options  Options for unit conversion. See @convertUnitsRaw.
 *
 * @return string
 */
function formatAggregatedHistoryValue($value, array $item, int $function, bool $force_units = false, bool $trim = true,
		array $convert_options = []): string {
	$formatted_value = formatAggregatedHistoryValueRaw($value, $item, $function, $force_units, $trim, $convert_options);

	return $formatted_value['value'].($formatted_value['units'] !== '' ? ' '.$formatted_value['units'] : '');
}

/**
 * Prepare aggregated item value for displaying, apply value map and/or convert units if appropriate for the aggregation
 * function.
 *
 * @param int|float|string $value
 * @param array            $item
 * @param int              $function         Aggregation function (AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX,
 *                                           AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST,
 *                                           AGGREGATE_LAST).
 * @param bool             $force_units      Whether to keep units despite the aggregation function not supporting it.
 * @param bool             $trim             Whether to trim non-numeric value to a length of 20 characters.
 * @param array            $convert_options  Options for unit conversion. See @convertUnitsRaw.
 *
 * @return array
 */
function formatAggregatedHistoryValueRaw($value, array $item, int $function, bool $force_units = false,
		bool $trim = true, array $convert_options = []): array {
	$is_numeric_item = in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
	$is_numeric_data = $is_numeric_item || CAggFunctionData::isNumericResult($function);

	if ($is_numeric_data) {
		$display_value = $value;
	}
	else {
		switch ($item['value_type']) {
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
			case ITEM_VALUE_TYPE_LOG:
				$display_value = $trim && mb_strlen($value) > 20 ? mb_substr($value, 0, 20).'...' : $value;
				break;

			case ITEM_VALUE_TYPE_BINARY:
				$display_value = _('binary value');
				break;

			default:
				$display_value = _('Unknown value type');
		}
	}

	if (in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_STR])
			&& CAggFunctionData::preservesValueMapping($function)) {
		$mapped_value = CValueMapHelper::getMappedValue($item['value_type'], $value, $item['valuemap']);

		if ($mapped_value !== false) {
			return [
				'value' => $mapped_value.' ('.$display_value.')',
				'units' => '',
				'is_mapped' => true
			];
		}
	}

	$units = $force_units || CAggFunctionData::preservesUnits($function) ? $item['units'] : '';

	if ($is_numeric_data) {
		$converted_value = convertUnitsRaw([
			'value' => $value,
			'units' => $units
		] + $convert_options);

		return [
			'value' => $converted_value['value'],
			'units' => $converted_value['units'],
			'is_mapped' => false
		];
	}

	return [
		'value' => $display_value,
		'units' => $units,
		'is_mapped' => false
	];
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

		case ITEM_VALUE_TYPE_BINARY:
			return [
				'value' => _('binary value'),
				'units' => '',
				'is_mapped' => false
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
 * Get power to time-unit relation in descending order of power.
 *
 * @return array
 */
function getUnitsSPowerData(): array {
	return [
		5	=> ['suffix' => _x('y', 'year short'),			'base' => SEC_PER_YEAR],
		4	=> ['suffix' => _x('M', 'month short'),			'base' => SEC_PER_MONTH],
		3	=> ['suffix' => _x('d', 'day short'),			'base' => SEC_PER_DAY],
		2	=> ['suffix' => _x('h', 'hour short'),			'base' => SEC_PER_HOUR],
		1	=> ['suffix' => _x('m', 'minute short'),		'base' => SEC_PER_MIN],
		0	=> ['suffix' => _x('s', 'second short'),		'base' => 1],
		-1	=> ['suffix' => _x('ms', 'millisecond short'),	'base' => 0.001]
	];
}

/**
 * Convert seconds to a single time unit with decimals.
 *
 * @param int|float|string $value                Number of seconds.
 * @param bool             $ignore_milliseconds  Do not use milliseconds.
 * @param int              $decimals             Max number of first non-zero decimals to display.
 * @param bool             $decimals_exact       Display exactly this number of decimals instead of first non-zeros.
 *
 * @return string
 */
function convertUnitsSWithDecimals($value, bool $ignore_milliseconds, int $decimals, bool $decimals_exact): string {
	$value = (float) $value;

	if ($value == 0) {
		return '0';
	}

	$power_data = getUnitsSPowerData();
	$power = -1;

	foreach ($power_data as $_power => $power_datum) {
		if ($value >= $power_datum['base']) {
			$power = $_power;
			break;
		}
	}

	if ($power === -1 && $ignore_milliseconds) {
		$power = 0;
	}

	return formatFloat($value / $power_data[$power]['base'], [
		'decimals' => $decimals,
		'decimals_exact' => $decimals_exact
	]).$power_data[$power]['suffix'];
}

/**
 * Check whether the unit of an item is binary or not.
 *
 * @param string $units
 *
 * @return bool
 */
function isBinaryUnits(string $units): bool {
	return $units === 'B' || $units === 'Bps';
}

/**
 * Get units value base for specified power.
 *
 * Examples: 'Bps', 0 => 1; 'Bps', 1 => 1024; 's', 0 => 1; 's', 1 => 60; 's', 2 => 3600; '', 3 => 1000000000.
 *
 * @param string $units
 * @param int    $power
 *
 * @return float
 */
function getUnitsBase(string $units, int $power): float {
	switch ($units) {
		case 's':
			return getUnitsSPowerData()[$power]['base'];

		default:
			return pow(isBinaryUnits($units) ? ZBX_KIBIBYTE : 1000, $power);
	}
}

/**
 * Get units power for specified value.
 *
 * Used for displaying multiple values at fixed power.
 * @see convertUnits
 *
 * Examples: 1023 B => 0; 1024 B => 1; 999 => 0; 1000 => 1; 1000000 => 2; 59s => 0, 60s => 1, 0.9s => 0.1.
 *
 * @param string $units  Units, like 's', 'Bps', 'uptime' or empty string.
 * @param float  $value  Numeric value, for which power will be calculated.
 *
 * @return int
 */
function getUnitsPower(string $units, float $value): int {
	switch ($units) {
		case 's':
			foreach (getUnitsSPowerData() as $power => $power_datum) {
				if ($value >= $power_datum['base']) {
					return $power;
				}
			}

			return -1;

		default:
			return (int) min(8, max(0, floor(log($value, isBinaryUnits($units) ? ZBX_KIBIBYTE : 1000))));
	}
}

/**
 * Get item functional value for use in expression macros. Will return null on errors.
 *
 * @param array  $item
 *               $item['itemid']     Item ID.
 *               $item['value_type'] Item value type (either ITEM_VALUE_TYPE_FLOAT or ITEM_VALUE_TYPE_UINT64).
 * @param string $function           Aggregation function (either 'min', 'max' or 'avg').
 * @param string $parameter          Time shift for aggregation (like '1h' or '2w').
 *
 * @return string|null
 */
function getItemFunctionalValue(array $item, string $function, string $parameter): ?string {
	if (!in_array($function, ['min', 'max', 'avg']) || $parameter === '') {
		return null;
	}

	if ($item['value_type'] != ITEM_VALUE_TYPE_FLOAT && $item['value_type'] != ITEM_VALUE_TYPE_UINT64) {
		return null;
	}

	$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

	if ($number_parser->parse($parameter) != CParser::PARSE_SUCCESS) {
		return null;
	}

	$time_shift = $number_parser->calcValue();

	$time_from = time() - $time_shift;

	if ($time_from < 0 || $time_from > ZBX_MAX_DATE) {
		return null;
	}

	$aggregated_data = Manager::History()->getAggregatedValues([['source' => 'history'] + $item],
		CItemHelper::resolveAggregateFunction($function), $time_from
	);

	if (!$aggregated_data) {
		return null;
	}

	return $aggregated_data[$item['itemid']]['value'];
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
		[$flexible_delay, $flexible_period] = explode('/', $flexible_interval);
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
		ZBX_PREPROC_SNMP_GET_VALUE => [
			'group' => _('SNMP'),
			'name' => _('SNMP get value')
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
		ITEM_TYPE_SCRIPT,
		ITEM_TYPE_BROWSER
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
			case ZBX_PREPROC_SNMP_GET_VALUE:
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

			case ZBX_PREPROC_VALIDATE_NOT_SUPPORTED:
				if ($step['params'][0] == ZBX_PREPROC_MATCH_ERROR_ANY) {
					unset($step['params'][1]);
				}

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
 * Format LLD macro paths received via form for API input.
 *
 * @param array $macro_paths  Array of LLD macro paths, as received from form submit.
 *
 * @return array
 */
function prepareLldMacroPaths(array $macro_paths): array {
	foreach ($macro_paths as $i => &$macro_path) {
		if ($macro_path['lld_macro'] === '' && $macro_path['path'] === '') {
			unset($macro_paths[$i]);
			continue;
		}

		$macro_path['lld_macro'] = mb_strtoupper($macro_path['lld_macro']);
	}
	unset($macro_path);

	return array_values($macro_paths);
}

/**
 * Format LLD rule filter data received via form for API input.
 *
 * @param array $filter  Array of LLD filters, as received from form submit.
 *
 * @return array
 */
function prepareLldFilter(array $filter): array {
	$filter['conditions'] = array_values(array_filter($filter['conditions'], static function (array $condition): bool {
		return $condition['macro'] !== '' || $condition['value'] !== '';
	}));

	if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION && count($filter['conditions']) <= 1) {
		$filter['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
	}

	if ($filter['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
		$filter['formula'] = '';
	}

	return $filter;
}

/**
 * Format LLD rule overrides data received via form for API input.
 *
 * @param array $overrides  Array of LLD overrides, as received from form submit.
 *
 * @return array
 */
function prepareLldOverrides(array $overrides): array {
	foreach ($overrides as &$override) {
		$override['filter'] = prepareLldFilter([
			'evaltype' => $override['filter']['evaltype'],
			'formula' => $override['filter']['formula'],
			'conditions' => array_key_exists('conditions', $override['filter'])
				? $override['filter']['conditions']
				: []
		]);

		if (!array_key_exists('operations', $override)) {
			$override['operations'] = [];
		}
	}
	unset($override);

	return $overrides;
}

/**
 * Format query fields received via form for API input.
 *
 * @param array $query_fields
 *
 * @return array
 */
function prepareItemQueryFields(array $query_fields): array {
	foreach ($query_fields as $i => $query_field) {
		unset($query_fields[$i]['sortorder']);

		if ($query_field['name'] === '' && $query_field['value'] === '') {
			unset($query_fields[$i]);
		}
	}

	return array_values($query_fields);
}

/**
 * Format headers field received via form for API input.
 *
 * @param array $headers
 *
 * @return array
 */
function prepareItemHeaders(array $headers): array {
	foreach ($headers as $i => $header) {
		unset($headers[$i]['sortorder']);

		if ($header['name'] === '' && $header['value'] === '') {
			unset($headers[$i]);
		}
	}

	return array_values($headers);
}

/**
 * Format parameters field received via form for API input.
 *
 * @param array $parameters
 *
 * @return array
 */
function prepareItemParameters(array $parameters): array {
	foreach ($parameters as $i => $parameter) {
		if ($parameter['name'] === '' && $parameter['value'] === '') {
			unset($parameters[$i]);
		}
	}

	return array_values($parameters);
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

		case ZBX_FLAG_DISCOVERY_RULE:
			if ($input['templateid'] == 0) {
				$field_names = ['name', 'type', 'key_', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
					'enabled_lifetime','description', 'status', 'preprocessing', 'lld_macro_paths', 'overrides'
				];
			}
			else {
				$field_names = ['lifetime_type', 'lifetime', 'enabled_lifetime_type', 'enabled_lifetime', 'description',
					'status'
				];
			}

			if (array_key_exists('itemid', $input) || $input['filter']['conditions']) {
				$field_names[] = 'filter';
			}

			return $field_names;

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
 *        string $input['templateid']
 *        int    $input['type']
 */
function getTypeItemFieldNames(array $input): array {
	switch ($input['type']) {
		case ITEM_TYPE_ZABBIX:
			return $input['templateid'] == 0
				? ['interfaceid', 'timeout', 'delay']
				: ['interfaceid', 'delay'];

		case ITEM_TYPE_TRAPPER:
			return ['trapper_hosts'];

		case ITEM_TYPE_SIMPLE:
			return $input['templateid'] == 0
				? ['interfaceid', 'username', 'password', 'timeout', 'delay']
				: ['interfaceid', 'username', 'password', 'delay'];

		case ITEM_TYPE_INTERNAL:
			return ['delay'];

		case ITEM_TYPE_ZABBIX_ACTIVE:
			return $input['templateid'] == 0
				? ['timeout', 'delay']
				: ['delay'];

		case ITEM_TYPE_EXTERNAL:
			return $input['templateid'] == 0
				? ['interfaceid', 'timeout', 'delay']
				: ['interfaceid', 'delay'];

		case ITEM_TYPE_DB_MONITOR:
			return $input['templateid'] == 0
				? ['username', 'password', 'params', 'timeout', 'delay']
				: ['username', 'password', 'params', 'delay'];

		case ITEM_TYPE_IPMI:
			return $input['templateid'] == 0
				? ['interfaceid', 'ipmi_sensor', 'delay']
				: ['interfaceid', 'delay'];

		case ITEM_TYPE_SSH:
			return $input['templateid'] == 0
				? ['interfaceid', 'authtype', 'username', 'publickey', 'privatekey', 'password', 'params', 'timeout',
					'delay'
				]
				: ['interfaceid', 'authtype', 'username', 'publickey', 'privatekey', 'password', 'params', 'delay'];

		case ITEM_TYPE_TELNET:
			return $input['templateid'] == 0
				? ['interfaceid', 'username', 'password', 'params', 'timeout', 'delay']
				: ['interfaceid', 'username', 'password', 'params', 'delay'];

		case ITEM_TYPE_CALCULATED:
			return ['params', 'delay'];

		case ITEM_TYPE_JMX:
			return $input['templateid'] == 0
				? ['interfaceid', 'jmx_endpoint', 'username', 'password', 'delay']
				: ['interfaceid', 'username', 'password', 'delay'];

		case ITEM_TYPE_SNMPTRAP:
			return ['interfaceid'];

		case ITEM_TYPE_DEPENDENT:
			return $input['templateid'] == 0 ? ['master_itemid'] : [];

		case ITEM_TYPE_HTTPAGENT:
			return $input['templateid'] == 0
				? ['url', 'query_fields', 'request_method', 'post_type', 'posts', 'headers', 'status_codes',
					'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy', 'interfaceid', 'authtype',
					'username', 'password', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file',
					'ssl_key_password', 'timeout', 'delay', 'allow_traps', 'trapper_hosts'
				]
				: ['interfaceid', 'delay', 'allow_traps', 'trapper_hosts'];

		case ITEM_TYPE_SNMP:
			return $input['templateid'] == 0
				? ['interfaceid', 'snmp_oid', 'timeout', 'delay']
				: ['interfaceid', 'delay'];

		case ITEM_TYPE_SCRIPT:
			return $input['templateid'] == 0
				? ['parameters', 'params', 'timeout', 'delay']
				: ['delay'];

		case ITEM_TYPE_BROWSER:
			return $input['templateid'] == 0
				? ['parameters', 'params', 'timeout', 'delay']
				: ['delay'];
	}
}

/**
 * Get item field names excluding those that don't match a specific conditions.
 *
 * @param array  $field_names
 * @param array  $input
 *        int    $input['type']
 *        string $input['key_']
 *        int    $input['value_type']
 *        int    $input['authtype']
 *        int    $input['allow_traps']
 *        string $input['snmp_oid']
 *        int    $input['hosts'][0]['status']
 *
 * @return array
 */
function getConditionalItemFieldNames(array $field_names, array $input): array {
	return array_filter($field_names, static function ($field_name) use ($input): bool {
		switch ($field_name) {
			case 'lifetime':
				return $input['lifetime_type'] == ZBX_LLD_DELETE_AFTER;
			case 'enabled_lifetime':
				return in_array($input['lifetime_type'], [ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_AFTER])
					&& $input['enabled_lifetime_type'] == ZBX_LLD_DISABLE_AFTER;
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

			case 'timeout':
				return ($input['type'] != ITEM_TYPE_SIMPLE || (strncmp($input['key_'], 'icmpping', 8) != 0
						&& strncmp($input['key_'], 'vmware.', 7) != 0))
					&& ($input['type'] != ITEM_TYPE_SNMP || strncmp($input['snmp_oid'], 'get[', 4) == 0
						|| strncmp($input['snmp_oid'], 'walk[', 5) == 0);

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

/**
 * Get per-item-type timeouts from proxy or global settings.
 *
 * @param string $proxyid
 *
 * @return array
 */
function getInheritedTimeouts(string $proxyid): array {
	if ($proxyid != 0) {
		$db_proxies = API::Proxy()->get([
			'output' => ['custom_timeouts', 'timeout_zabbix_agent', 'timeout_simple_check', 'timeout_snmp_agent',
				'timeout_external_check', 'timeout_db_monitor', 'timeout_http_agent', 'timeout_ssh_agent',
				'timeout_telnet_agent', 'timeout_script', 'timeout_browser'
			],
			'proxyids' => $proxyid,
			'nopermissions' => true
		]);
		$db_proxy = reset($db_proxies);

		if ($db_proxy && $db_proxy['custom_timeouts'] == ZBX_PROXY_CUSTOM_TIMEOUTS_ENABLED) {
			return [
				'source' => 'proxy',
				'proxyid' => $proxyid,
				'timeouts' => [
					ITEM_TYPE_ZABBIX => $db_proxy['timeout_zabbix_agent'],
					ITEM_TYPE_SIMPLE => $db_proxy['timeout_simple_check'],
					ITEM_TYPE_ZABBIX_ACTIVE => $db_proxy['timeout_zabbix_agent'],
					ITEM_TYPE_EXTERNAL => $db_proxy['timeout_external_check'],
					ITEM_TYPE_DB_MONITOR => $db_proxy['timeout_db_monitor'],
					ITEM_TYPE_SSH => $db_proxy['timeout_ssh_agent'],
					ITEM_TYPE_TELNET => $db_proxy['timeout_telnet_agent'],
					ITEM_TYPE_HTTPAGENT => $db_proxy['timeout_http_agent'],
					ITEM_TYPE_SNMP => $db_proxy['timeout_snmp_agent'],
					ITEM_TYPE_SCRIPT => $db_proxy['timeout_script'],
					ITEM_TYPE_BROWSER => $db_proxy['timeout_browser']
				]
			];
		}
	}

	return [
		'source' => 'global',
		'proxyid' => $proxyid,
		'timeouts' => [
			ITEM_TYPE_ZABBIX => CSettingsHelper::get(CSettingsHelper::TIMEOUT_ZABBIX_AGENT),
			ITEM_TYPE_SIMPLE => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SIMPLE_CHECK),
			ITEM_TYPE_ZABBIX_ACTIVE => CSettingsHelper::get(CSettingsHelper::TIMEOUT_ZABBIX_AGENT),
			ITEM_TYPE_EXTERNAL => CSettingsHelper::get(CSettingsHelper::TIMEOUT_EXTERNAL_CHECK),
			ITEM_TYPE_DB_MONITOR => CSettingsHelper::get(CSettingsHelper::TIMEOUT_DB_MONITOR),
			ITEM_TYPE_SSH => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SSH_AGENT),
			ITEM_TYPE_TELNET => CSettingsHelper::get(CSettingsHelper::TIMEOUT_TELNET_AGENT),
			ITEM_TYPE_HTTPAGENT => CSettingsHelper::get(CSettingsHelper::TIMEOUT_HTTP_AGENT),
			ITEM_TYPE_SNMP => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SNMP_AGENT),
			ITEM_TYPE_SCRIPT => CSettingsHelper::get(CSettingsHelper::TIMEOUT_SCRIPT),
			ITEM_TYPE_BROWSER => CSettingsHelper::get(CSettingsHelper::TIMEOUT_BROWSER)
		]
	];
}

/**
 * @param int   $item_type
 * @param array $hostids
 *
 * @return array
 */
function getItemTypeCountByHostId(int $item_type, array $hostids): array {
	if (!$hostids) {
		return [];
	}

	$items_count = API::Item()->get([
		'countOutput' => true,
		'groupCount' => true,
		'hostids' => $hostids,
		'filter' => ['type' => $item_type]
	]);

	return $items_count ? array_column($items_count, 'rowscount', 'hostid') : [];
}
