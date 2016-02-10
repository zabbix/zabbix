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
 * Get item type string name by item type number, or array of all item types if null passed
 *
 * @param int|null $type
 * @return array|string
 */
function item_type2str($type = null) {
	$types = [
		ITEM_TYPE_ZABBIX => _('Zabbix agent'),
		ITEM_TYPE_ZABBIX_ACTIVE => _('Zabbix agent (active)'),
		ITEM_TYPE_SIMPLE => _('Simple check'),
		ITEM_TYPE_SNMPV1 => _('SNMPv1 agent'),
		ITEM_TYPE_SNMPV2C => _('SNMPv2 agent'),
		ITEM_TYPE_SNMPV3 => _('SNMPv3 agent'),
		ITEM_TYPE_SNMPTRAP => _('SNMP trap'),
		ITEM_TYPE_INTERNAL => _('Zabbix internal'),
		ITEM_TYPE_TRAPPER => _('Zabbix trapper'),
		ITEM_TYPE_AGGREGATE => _('Zabbix aggregate'),
		ITEM_TYPE_EXTERNAL => _('External check'),
		ITEM_TYPE_DB_MONITOR => _('Database monitor'),
		ITEM_TYPE_IPMI => _('IPMI agent'),
		ITEM_TYPE_SSH => _('SSH agent'),
		ITEM_TYPE_TELNET => _('TELNET agent'),
		ITEM_TYPE_JMX => _('JMX agent'),
		ITEM_TYPE_CALCULATED => _('Calculated'),
		ITEM_TYPE_HTTPTEST => _('Web monitoring')
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

function item_data_type2str($type = null) {
	$types = [
		ITEM_DATA_TYPE_BOOLEAN => _('Boolean'),
		ITEM_DATA_TYPE_OCTAL => _('Octal'),
		ITEM_DATA_TYPE_DECIMAL => _('Decimal'),
		ITEM_DATA_TYPE_HEXADECIMAL => _('Hexadecimal')
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

function item_status2str($type = null) {
	$types = [
		ITEM_STATUS_ACTIVE => _('Enabled'),
		ITEM_STATUS_DISABLED => _('Disabled')
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
	elseif ($status == ITEM_STATUS_DISABLED) {
		return _('Disabled');
	}

	return _('Unknown');
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
	elseif ($status == ITEM_STATUS_DISABLED) {
		return ZBX_STYLE_RED;
	}

	return ZBX_STYLE_GREY;
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
			$statusOrder = ($item['state'] == ITEM_STATE_NOTSUPPORTED) ? 2 : 0;
		}
		elseif ($item['status'] == ITEM_STATUS_DISABLED) {
			$statusOrder = 1;
		}

		$sort[$key] = $statusOrder;
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
 * @return null
 */
function interfaceType2str($type) {
	$interfaceGroupLabels = [
		INTERFACE_TYPE_AGENT => _('Agent'),
		INTERFACE_TYPE_SNMP => _('SNMP'),
		INTERFACE_TYPE_JMX => _('JMX'),
		INTERFACE_TYPE_IPMI => _('IPMI'),
	];

	return isset($interfaceGroupLabels[$type]) ? $interfaceGroupLabels[$type] : null;
}

function itemTypeInterface($type = null) {
	$types = [
		ITEM_TYPE_SNMPV1 => INTERFACE_TYPE_SNMP,
		ITEM_TYPE_SNMPV2C => INTERFACE_TYPE_SNMP,
		ITEM_TYPE_SNMPV3 => INTERFACE_TYPE_SNMP,
		ITEM_TYPE_SNMPTRAP => INTERFACE_TYPE_SNMP,
		ITEM_TYPE_IPMI => INTERFACE_TYPE_IPMI,
		ITEM_TYPE_ZABBIX => INTERFACE_TYPE_AGENT,
		ITEM_TYPE_SIMPLE => INTERFACE_TYPE_ANY,
		ITEM_TYPE_EXTERNAL => INTERFACE_TYPE_ANY,
		ITEM_TYPE_SSH => INTERFACE_TYPE_ANY,
		ITEM_TYPE_TELNET => INTERFACE_TYPE_ANY,
		ITEM_TYPE_JMX => INTERFACE_TYPE_JMX
	];
	if (is_null($type)) {
		return $types;
	}
	elseif (isset($types[$type])) {
		return $types[$type];
	}
	else {
		return false;
	}
}

function update_item_status($itemids, $status) {
	zbx_value2array($itemids);
	$result = true;

	$db_items = DBselect('SELECT i.* FROM items i WHERE '.dbConditionInt('i.itemid', $itemids));
	while ($item = DBfetch($db_items)) {
		$old_status = $item['status'];
		if ($status != $old_status) {
			$result &= DBexecute(
				'UPDATE items SET status='.zbx_dbstr($status).' WHERE itemid='.zbx_dbstr($item['itemid'])
			);
			if ($result) {
				$host = get_host_by_hostid($item['hostid']);
				$item_new = get_item_by_itemid($item['itemid']);
				add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM, $item['itemid'], $host['host'].NAME_DELIMITER.$item['name'], 'items', $item, $item_new);
			}
		}
	}
	return $result;
}

function copyItemsToHosts($srcItemIds, $dstHostIds) {
	$srcItems = API::Item()->get([
		'itemids' => $srcItemIds,
		'output' => [
			'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends', 'status', 'value_type',
			'trapper_hosts', 'units', 'multiplier', 'delta', 'snmpv3_contextname', 'snmpv3_securityname',
			'snmpv3_securitylevel', 'snmpv3_authprotocol', 'snmpv3_authpassphrase', 'snmpv3_privprotocol',
			'snmpv3_privpassphrase', 'formula', 'logtimefmt', 'valuemapid', 'delay_flex', 'params', 'ipmi_sensor',
			'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'flags', 'port',
			'description', 'inventory_link'
		],
		'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
		'selectApplications' => ['applicationid']
	]);

	$dstHosts = API::Host()->get([
		'output' => ['hostid', 'host', 'status'],
		'selectInterfaces' => ['interfaceid', 'type', 'main'],
		'hostids' => $dstHostIds,
		'preservekeys' => true,
		'nopermissions' => true,
		'templated_hosts' => true
	]);

	foreach ($dstHosts as $dstHost) {
		$interfaceids = [];
		foreach ($dstHost['interfaces'] as $interface) {
			if ($interface['main'] == 1) {
				$interfaceids[$interface['type']] = $interface['interfaceid'];
			}
		}
		foreach ($srcItems as &$srcItem) {
			if ($dstHost['status'] != HOST_STATUS_TEMPLATE) {
				$type = itemTypeInterface($srcItem['type']);

				if ($type == INTERFACE_TYPE_ANY) {
					foreach ([INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI] as $itype) {
						if (isset($interfaceids[$itype])) {
							$srcItem['interfaceid'] = $interfaceids[$itype];
							break;
						}
					}
				}
				elseif ($type !== false) {
					if (!isset($interfaceids[$type])) {
						error(_s('Cannot find host interface on "%1$s" for item key "%2$s".', $dstHost['host'], $srcItem['key_']));
						return false;
					}
					$srcItem['interfaceid'] = $interfaceids[$type];
				}
			}
			unset($srcItem['itemid']);
			$srcItem['hostid'] = $dstHost['hostid'];
			$srcItem['applications'] = get_same_applications_for_host(zbx_objectValues($srcItem['applications'], 'applicationid'), $dstHost['hostid']);
		}
		unset($srcItem);

		if (!API::Item()->create($srcItems)) {
			return false;
		}
	}
	return true;
}

function copyItems($srcHostId, $dstHostId) {
	$srcItems = API::Item()->get([
		'hostids' => $srcHostId,
		'output' => [
			'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends', 'status', 'value_type',
			'trapper_hosts', 'units', 'multiplier', 'delta', 'snmpv3_contextname', 'snmpv3_securityname',
			'snmpv3_securitylevel', 'snmpv3_authprotocol', 'snmpv3_authpassphrase', 'snmpv3_privprotocol',
			'snmpv3_privpassphrase', 'formula', 'logtimefmt', 'valuemapid', 'delay_flex', 'params', 'ipmi_sensor',
			'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'flags', 'port',
			'description', 'inventory_link'
		],
		'inherited' => false,
		'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
		'selectApplications' => ['applicationid']
	]);
	$dstHosts = API::Host()->get([
		'output' => ['hostid', 'host', 'status'],
		'selectInterfaces' => ['interfaceid', 'type', 'main'],
		'hostids' => $dstHostId,
		'preservekeys' => true,
		'nopermissions' => true,
		'templated_hosts' => true
	]);
	$dstHost = reset($dstHosts);

	foreach ($srcItems as &$srcItem) {
		if ($dstHost['status'] != HOST_STATUS_TEMPLATE) {
			// find a matching interface
			$interface = CItem::findInterfaceForItem($srcItem, $dstHost['interfaces']);
			if ($interface) {
				$srcItem['interfaceid'] = $interface['interfaceid'];
			}
			// no matching interface found, throw an error
			elseif ($interface !== false) {
				error(_s('Cannot find host interface on "%1$s" for item key "%2$s".', $dstHost['host'], $srcItem['key_']));
			}
		}
		unset($srcItem['itemid']);
		unset($srcItem['templateid']);
		$srcItem['hostid'] = $dstHostId;
		$srcItem['applications'] = get_same_applications_for_host(zbx_objectValues($srcItem['applications'], 'applicationid'), $dstHostId);
	}
	unset($srcItem);

	return API::Item()->create($srcItems);
}

/**
 * Copy applications to a different host.
 *
 * @param string $source_hostid
 * @param string $destination_hostid
 *
 * @return bool
 */
function copyApplications($source_hostid, $destination_hostid) {
	$applications_to_create = API::Application()->get([
		'output' => ['name'],
		'hostids' => [$source_hostid],
		'inherited' => false,
		'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
	]);

	if (!$applications_to_create) {
		return true;
	}

	foreach ($applications_to_create as &$application) {
		$application['hostid'] = $destination_hostid;
		unset($application['applicationid'], $application['templateid']);
	}
	unset($application);

	return (bool) API::Application()->create($applications_to_create);
}

function activate_item($itemids) {
	zbx_value2array($itemids);

	// first update status for child items
	$child_items = [];
	$db_items = DBselect('SELECT i.itemid,i.hostid FROM items i WHERE '.dbConditionInt('i.templateid', $itemids));
	while ($item = DBfetch($db_items)) {
		$child_items[$item['itemid']] = $item['itemid'];
	}
	if (!empty($child_items)) {
		activate_item($child_items); // Recursion !!!
	}
	return update_item_status($itemids, ITEM_STATUS_ACTIVE);
}

function disable_item($itemids) {
	zbx_value2array($itemids);

	// first update status for child items
	$chd_items = [];
	$db_tmp_items = DBselect('SELECT i.itemid,i.hostid FROM items i WHERE '.dbConditionInt('i.templateid', $itemids));
	while ($db_tmp_item = DBfetch($db_tmp_items)) {
		$chd_items[$db_tmp_item['itemid']] = $db_tmp_item['itemid'];
	}
	if (!empty($chd_items)) {
		disable_item($chd_items); // Recursion !!!
	}
	return update_item_status($itemids, ITEM_STATUS_DISABLED);
}

function get_item_by_itemid($itemid) {
	$db_items = DBfetch(DBselect('SELECT i.* FROM items i WHERE i.itemid='.zbx_dbstr($itemid)));
	if ($db_items) {
		return $db_items;
	}
	error(_s('No item with itemid="%1$s".', $itemid));
	return false;
}

function get_item_by_itemid_limited($itemid) {
	$row = DBfetch(DBselect(
		'SELECT i.itemid,i.interfaceid,i.name,i.key_,i.hostid,i.delay,i.history,i.status,i.type,i.lifetime,'.
			'i.snmp_community,i.snmp_oid,i.value_type,i.data_type,i.trapper_hosts,i.port,i.units,i.multiplier,'.
			'i.delta,i.snmpv3_contextname,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authprotocol,'.
			'i.snmpv3_authpassphrase,i.snmpv3_privprotocol,i.snmpv3_privpassphrase,i.formula,i.trends,i.logtimefmt,'.
			'i.valuemapid,i.delay_flex,i.params,i.ipmi_sensor,i.templateid,i.authtype,i.username,i.password,'.
			'i.publickey,i.privatekey,i.flags,i.description,i.inventory_link'.
		' FROM items i'.
		' WHERE i.itemid='.zbx_dbstr($itemid)));
	if ($row) {
		return $row;
	}
	error(_s('No item with itemid "%1$s".', $itemid));
	return false;
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

function get_realhost_by_itemid($itemid) {
	$item = get_item_by_itemid($itemid);
	if ($item['templateid'] <> 0) {
		return get_realhost_by_itemid($item['templateid']); // attention recursion!
	}
	return get_host_by_itemid($itemid);
}

function fillItemsWithChildTemplates(&$items) {
	$processSecondLevel = false;
	$dbItems = DBselect('SELECT i.itemid,i.templateid FROM items i WHERE '.dbConditionInt('i.itemid', zbx_objectValues($items, 'templateid')));
	while ($dbItem = DBfetch($dbItems)) {
		foreach ($items as $itemid => $item) {
			if ($item['templateid'] == $dbItem['itemid'] && !empty($dbItem['templateid'])) {
				$items[$itemid]['templateid'] = $dbItem['templateid'];
				$processSecondLevel = true;
			}
		}
	}
	if ($processSecondLevel) {
		fillItemsWithChildTemplates($items); // attention recursion!
	}
}

function get_realrule_by_itemid_and_hostid($itemid, $hostid) {
	$item = get_item_by_itemid($itemid);
	if (bccomp($hostid,$item['hostid']) == 0) {
		return $item['itemid'];
	}
	if ($item['templateid'] <> 0) {
		return get_realrule_by_itemid_and_hostid($item['templateid'], $hostid);
	}
	return $item['itemid'];
}

/**
 * Retrieve overview table object for items.
 *
 * @param array  		$hostIds
 * @param array|null	$applicationIds		IDs of applications to filter items by
 * @param int    		$viewMode
 *
 * @return CTableInfo
 */
function getItemsDataOverview($hostIds, array $applicationIds = null, $viewMode) {
	$sqlFrom = '';
	$sqlWhere = '';

	if ($applicationIds !== null) {
		$sqlFrom = 'items_applications ia,';
		$sqlWhere = ' AND i.itemid=ia.itemid AND '.dbConditionInt('ia.applicationid', $applicationIds);
	}

	$dbItems = DBfetchArray(DBselect(
		'SELECT DISTINCT h.hostid,h.name AS hostname,i.itemid,i.key_,i.value_type,i.units,'.
			'i.name,t.priority,i.valuemapid,t.value AS tr_value,t.triggerid'.
		' FROM hosts h,'.$sqlFrom.'items i'.
			' LEFT JOIN functions f ON f.itemid=i.itemid'.
			' LEFT JOIN triggers t ON t.triggerid=f.triggerid AND t.status='.TRIGGER_STATUS_ENABLED.
		' WHERE '.dbConditionInt('h.hostid', $hostIds).
			' AND h.status='.HOST_STATUS_MONITORED.
			' AND h.hostid=i.hostid'.
			' AND i.status='.ITEM_STATUS_ACTIVE.
			' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]).
				$sqlWhere
	));

	$dbItems = CMacrosResolverHelper::resolveItemNames($dbItems);

	CArrayHelper::sort($dbItems, [
		['field' => 'name_expanded', 'order' => ZBX_SORT_UP],
		['field' => 'itemid', 'order' => ZBX_SORT_UP]
	]);

	// fetch latest values
	$history = Manager::History()->getLast(zbx_toHash($dbItems, 'itemid'), 1, ZBX_HISTORY_PERIOD);

	// fetch data for the host JS menu
	$hosts = API::Host()->get([
		'output' => ['name', 'hostid', 'status'],
		'monitored_hosts' => true,
		'hostids' => $hostIds,
		'with_monitored_items' => true,
		'preservekeys' => true,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectScreens' => ($viewMode == STYLE_LEFT) ? API_OUTPUT_COUNT : null
	]);

	$items = [];
	foreach ($dbItems as $dbItem) {
		$name = $dbItem['name_expanded'];

		$hostNames[$dbItem['hostid']] = $dbItem['hostname'];

		// a little tricky check for attempt to overwrite active trigger (value=1) with
		// inactive or active trigger with lower priority.
		if (!isset($items[$name][$dbItem['hostname']])
				|| (($items[$name][$dbItem['hostname']]['tr_value'] == TRIGGER_VALUE_FALSE && $dbItem['tr_value'] == TRIGGER_VALUE_TRUE)
					|| (($items[$name][$dbItem['hostname']]['tr_value'] == TRIGGER_VALUE_FALSE || $dbItem['tr_value'] == TRIGGER_VALUE_TRUE)
						&& $dbItem['priority'] > $items[$name][$dbItem['hostname']]['severity']))) {
			$items[$name][$dbItem['hostname']] = [
				'itemid' => $dbItem['itemid'],
				'value_type' => $dbItem['value_type'],
				'value' => isset($history[$dbItem['itemid']]) ? $history[$dbItem['itemid']][0]['value'] : null,
				'units' => $dbItem['units'],
				'name' => $name,
				'valuemapid' => $dbItem['valuemapid'],
				'severity' => $dbItem['priority'],
				'tr_value' => $dbItem['tr_value'],
				'triggerid' => $dbItem['triggerid']
			];
		}
	}

	$table = new CTableInfo();
	if (empty($hostNames)) {
		return $table;
	}
	$table->makeVerticalRotation();

	order_result($hostNames);

	if ($viewMode == STYLE_TOP) {
		$header = [_('Items')];
		foreach ($hostNames as $hostName) {
			$header[] = (new CColHeader($hostName))->addClass('vertical_rotation');
		}
		$table->setHeader($header);

		foreach ($items as $descr => $ithosts) {
			$tableRow = [nbsp($descr)];
			foreach ($hostNames as $hostName) {
				$tableRow = getItemDataOverviewCells($tableRow, $ithosts, $hostName);
			}
			$table->addRow($tableRow);
		}
	}
	else {
		$scripts = API::Script()->getScriptsByHosts(zbx_objectValues($hosts, 'hostid'));

		$header = [_('Hosts')];
		foreach ($items as $descr => $ithosts) {
			$header[] = (new CColHeader($descr))->addClass('vertical_rotation');
		}
		$table->setHeader($header);

		foreach ($hostNames as $hostId => $hostName) {
			$host = $hosts[$hostId];

			$name = (new CSpan($host['name']))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->setMenuPopup(CMenuPopupHelper::getHost($host, $scripts[$hostId]));

			$tableRow = [(new CCol($name))->addClass(ZBX_STYLE_NOWRAP)];
			foreach ($items as $ithosts) {
				$tableRow = getItemDataOverviewCells($tableRow, $ithosts, $hostName);
			}
			$table->addRow($tableRow);
		}
	}

	return $table;
}

function getItemDataOverviewCells($tableRow, $ithosts, $hostName) {
	$ack = null;
	$css = '';
	$value = UNKNOWN_VALUE;

	if (isset($ithosts[$hostName])) {
		$item = $ithosts[$hostName];

		if ($item['tr_value'] == TRIGGER_VALUE_TRUE) {
			$css = getSeverityStyle($item['severity']);

			// Display event acknowledgement.
			$config = select_config();
			if ($config['event_ack_enable']) {
				$ack = get_last_event_by_triggerid($item['triggerid']);
				$ack = ($ack['acknowledged'] == 1)
					? [SPACE, (new CSpan())->addClass(ZBX_STYLE_ICON_ACKN)]
					: null;
			}
		}

		if ($item['value'] !== null) {
			$value = formatHistoryValue($item['value'], $item);
		}
	}

	if ($value != UNKNOWN_VALUE) {
		$value = $value;
	}

	$column = (new CCol([$value, $ack]))->addClass($css);

	if (isset($ithosts[$hostName])) {
		$column
			->setMenuPopup(CMenuPopupHelper::getHistory($item))
			->addClass(ZBX_STYLE_CURSOR_POINTER)
			->addClass(ZBX_STYLE_NOWRAP);
	}

	$tableRow[] = $column;

	return $tableRow;
}

/**
 * Get same application IDs on destination host and return array with keys as source application IDs
 * and values as destination application IDs.
 *
 * Comments: !!! Don't forget sync code with C !!!
 *
 * @param array  $applicationIds
 * @param string $hostId
 *
 * @return array
 */
function get_same_applications_for_host(array $applicationIds, $hostId) {
	$applications = [];

	$dbApplications = DBselect(
		'SELECT a1.applicationid AS dstappid,a2.applicationid AS srcappid'.
		' FROM applications a1,applications a2'.
		' WHERE a1.name=a2.name'.
			' AND a1.hostid='.zbx_dbstr($hostId).
			' AND '.dbConditionInt('a2.applicationid', $applicationIds)
	);

	while ($dbApplication = DBfetch($dbApplications)) {
		$applications[$dbApplication['srcappid']] = $dbApplication['dstappid'];
	}

	return $applications;
}

/******************************************************************************
 *                                                                            *
 * Comments: !!! Don't forget sync code with C !!!                            *
 *                                                                            *
 ******************************************************************************/
function get_applications_by_itemid($itemids, $field = 'applicationid') {
	zbx_value2array($itemids);
	$result = [];
	$db_applications = DBselect(
		'SELECT DISTINCT app.'.$field.' AS result'.
		' FROM applications app,items_applications ia'.
		' WHERE app.applicationid=ia.applicationid'.
			' AND '.dbConditionInt('ia.itemid', $itemids)
	);
	while ($db_application = DBfetch($db_applications)) {
		array_push($result, $db_application['result']);
	}

	return $result;
}

/**
 * Clear item history and trends by provided item IDs.
 *
 * @param array $itemIds
 *
 * @return bool
 */
function deleteHistoryByItemIds(array $itemIds) {
	$result = DBexecute('DELETE FROM trends WHERE '.dbConditionInt('itemid', $itemIds));
	$result = ($result && DBexecute('DELETE FROM trends_uint WHERE '.dbConditionInt('itemid', $itemIds)));
	$result = ($result && DBexecute('DELETE FROM history_text WHERE '.dbConditionInt('itemid', $itemIds)));
	$result = ($result && DBexecute('DELETE FROM history_log WHERE '.dbConditionInt('itemid', $itemIds)));
	$result = ($result && DBexecute('DELETE FROM history_uint WHERE '.dbConditionInt('itemid', $itemIds)));
	$result = ($result && DBexecute('DELETE FROM history_str WHERE '.dbConditionInt('itemid', $itemIds)));
	$result = ($result && DBexecute('DELETE FROM history WHERE '.dbConditionInt('itemid', $itemIds)));

	return $result;
}

/**
 * Format history value.
 * First format the value according to the configuration of the item. Then apply the value mapping to the formatted (!)
 * value.
 *
 * @param mixed     $value
 * @param array     $item
 * @param int       $item['value_type']     type of the value: ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64, ...
 * @param string    $item['units']          units of item
 * @param int       $item['valuemapid']     id of mapping set of values
 * @param bool      $trim
 *
 * @return string
 */
function formatHistoryValue($value, array $item, $trim = true) {
	$mapping = false;

	// format value
	if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
		$value = convert_units([
				'value' => $value,
				'units' => $item['units']
		]);
	}
	elseif ($item['value_type'] != ITEM_VALUE_TYPE_STR
		&& $item['value_type'] != ITEM_VALUE_TYPE_TEXT
		&& $item['value_type'] != ITEM_VALUE_TYPE_LOG) {

		$value = _('Unknown value type');
	}

	// apply value mapping
	switch ($item['value_type']) {
		case ITEM_VALUE_TYPE_STR:
			$mapping = getMappedValue($value, $item['valuemapid']);
		// break; is not missing here
		case ITEM_VALUE_TYPE_TEXT:
		case ITEM_VALUE_TYPE_LOG:
			if ($trim && mb_strlen($value) > 20) {
				$value = mb_substr($value, 0, 20).'...';
			}

			if ($mapping !== false) {
				$value = $mapping.' ('.$value.')';
			}
			break;
		default:
			$value = applyValueMap($value, $item['valuemapid']);
	}

	return $value;
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
	// check whether function is allowed
	if (!in_array($function, ['min', 'max', 'avg']) || $parameter === '') {
		return UNRESOLVED_MACRO_STRING;
	}

	$parameter = convertFunctionValue($parameter);

	if (bccomp($parameter, 0) == 0) {
		return UNRESOLVED_MACRO_STRING;
	}

	// allowed item types for min, max and avg function
	$historyTables = [ITEM_VALUE_TYPE_FLOAT => 'history', ITEM_VALUE_TYPE_UINT64 => 'history_uint'];

	if (!isset($historyTables[$item['value_type']])) {
		return UNRESOLVED_MACRO_STRING;
	}
	else {
		// search for item function data in DB corresponding history table
		$result = DBselect(
			'SELECT '.$function.'(value) AS value'.
			' FROM '.$historyTables[$item['value_type']].
			' WHERE clock>'.(time() - $parameter).
			' AND itemid='.zbx_dbstr($item['itemid']).
			' HAVING COUNT(*)>0' // necessary because DBselect() return 0 if empty data set, for graph templates
		);
		if ($row = DBfetch($result)) {
			return convert_units(['value' => $row['value'], 'units' => $item['units']]);
		}
		// no data in history
		else {
			return UNRESOLVED_MACRO_STRING;
		}
	}
}

/**
 * Returns the history value of the item at the given time. If no value exists at the given time, the function
 * will return the previous value.
 *
 * The $db_item parameter must have the value_type and itemid properties set.
 *
 * @param array $db_item
 * @param int $clock
 * @param int $ns
 *
 * @return string
 */
function item_get_history($db_item, $clock, $ns) {
	$value = null;

	$table = CHistoryManager::getTableName($db_item['value_type']);

	$sql = 'SELECT value'.
			' FROM '.$table.
			' WHERE itemid='.zbx_dbstr($db_item['itemid']).
				' AND clock='.zbx_dbstr($clock).
				' AND ns='.zbx_dbstr($ns);
	if (null != ($row = DBfetch(DBselect($sql, 1)))) {
		$value = $row['value'];
	}
	if ($value != null) {
		return $value;
	}

	$max_clock = 0;

	$sql = 'SELECT DISTINCT clock'.
			' FROM '.$table.
			' WHERE itemid='.zbx_dbstr($db_item['itemid']).
				' AND clock='.zbx_dbstr($clock).
				' AND ns<'.zbx_dbstr($ns);
	if (null != ($row = DBfetch(DBselect($sql)))) {
		$max_clock = $row['clock'];
	}
	if ($max_clock == 0) {
		$sql = 'SELECT MAX(clock) AS clock'.
				' FROM '.$table.
				' WHERE itemid='.zbx_dbstr($db_item['itemid']).
					' AND clock<'.zbx_dbstr($clock);
		if (null != ($row = DBfetch(DBselect($sql)))) {
			$max_clock = $row['clock'];
		}
	}
	if ($max_clock == 0) {
		return $value;
	}

	if ($clock == $max_clock) {
		$sql = 'SELECT value'.
				' FROM '.$table.
				' WHERE itemid='.zbx_dbstr($db_item['itemid']).
					' AND clock='.zbx_dbstr($clock).
					' AND ns<'.zbx_dbstr($ns);
	}
	else {
		$sql = 'SELECT value'.
				' FROM '.$table.
				' WHERE itemid='.zbx_dbstr($db_item['itemid']).
					' AND clock='.zbx_dbstr($max_clock).
				' ORDER BY itemid,clock desc,ns desc';
	}

	if (null != ($row = DBfetch(DBselect($sql, 1)))) {
		$value = $row['value'];
	}

	return $value;
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
	if ($delay != 0 || !$flexible_intervals) {
		return $delay;
	}

	$min_delay = SEC_PER_YEAR;

	foreach ($flexible_intervals as $flexible_interval) {
		$flexible_interval_parts = explode('/', $flexible_interval);
		$flexible_delay = (int) $flexible_interval_parts[0];

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
