<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * @package API
 */
abstract class CItemGeneral extends CZBXAPI {

	protected $fieldRules;

	/**
	 * @abstract
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	abstract public function get($options = array());

	public function __construct() {
		parent::__construct();

		// template - if templated item, value is taken from template item, cannot be changed on host
		// system - values should not be updated
		// host - value should be null for template items
		$this->fieldRules = array(
			'type'					=> array('template' => 1),
			'snmp_community'		=> array(),
			'snmp_oid'				=> array('template' => 1),
			'hostid'				=> array(),
			'name'					=> array('template' => 1),
			'description'			=> array(),
			'key_'					=> array('template' => 1),
			'delay'					=> array(),
			'history'				=> array(),
			'trends'				=> array(),
			'lastvalue'				=> array('system' => 1),
			'lastclock'				=> array('system' => 1),
			'prevvalue'				=> array('system' => 1),
			'status'				=> array(),
			'value_type'			=> array('template' => 1),
			'trapper_hosts'			=> array(),
			'units'					=> array('template' => 1),
			'multiplier'			=> array('template' => 1),
			'delta'					=> array(),
			'prevorgvalue'			=> array('system' => 1),
			'snmpv3_securityname'	=> array(),
			'snmpv3_securitylevel'	=> array(),
			'snmpv3_authpassphrase'	=> array(),
			'snmpv3_privpassphrase'	=> array(),
			'formula'				=> array('template' => 1),
			'error'					=> array('system' => 1),
			'lastlogsize'			=> array('system' => 1),
			'logtimefmt'			=> array(),
			'templateid'			=> array('system' => 1),
			'valuemapid'			=> array('template' => 1),
			'delay_flex'			=> array(),
			'params'				=> array(),
			'ipmi_sensor'			=> array('template' => 1),
			'data_type'				=> array('template' => 1),
			'authtype'				=> array(),
			'username'				=> array(),
			'password'				=> array(),
			'publickey'				=> array(),
			'privatekey'			=> array(),
			'mtime'					=> array('system' => 1),
			'lastns'				=> array('system' => 1),
			'flags'					=> array(),
			'filter'				=> array(),
			'interfaceid'			=> array('host' => 1),
			'port'					=> array(),
			'inventory_link'		=> array(),
			'lifetime'				=> array()
		);
	}

	/**
	 * Check items data.
	 *
	 * Any system field passed to the function will be unset.
	 *
	 * @throw APIException
	 *
	 * @param array $items passed by reference
	 * @param bool  $update
	 *
	 * @return void
	 */
	protected function checkInput(array &$items, $update = false) {
		// permissions
		if ($update) {
			$itemDbFields = array('itemid' => null);

			$dbItemsFields = array('itemid', 'templateid');
			foreach ($this->fieldRules as $field => $rule) {
				if (!isset($rule['system'])) {
					$dbItemsFields[] = $field;
				}
			}

			$dbItems = $this->get(array(
				'output' => $dbItemsFields,
				'itemids' => zbx_objectValues($items, 'itemid'),
				'editable' => true,
				'preservekeys' => true
			));

			$dbHosts = API::Host()->get(array(
				'output' => array('hostid', 'status', 'name'),
				'hostids' => zbx_objectValues($dbItems, 'hostid'),
				'templated_hosts' => true,
				'editable' => true,
				'selectApplications' => API_OUTPUT_REFER,
				'preservekeys' => true
			));
		}
		else {
			$itemDbFields = array(
				'name' => null,
				'key_' => null,
				'hostid' => null,
				'type' => null,
				'value_type' => null,
				'delay' => '0',
				'delay_flex' => ''
			);

			$dbHosts = API::Host()->get(array(
				'output' => array('hostid', 'status', 'name'),
				'hostids' => zbx_objectValues($items, 'hostid'),
				'templated_hosts' => true,
				'editable' => true,
				'selectApplications' => API_OUTPUT_REFER,
				'preservekeys' => true
			));
		}

		// interfaces
		$interfaces = API::HostInterface()->get(array(
			'output' => array('interfaceid', 'hostid', 'type'),
			'hostids' => zbx_objectValues($dbHosts, 'hostid'),
			'nopermissions' => true,
			'preservekeys' => true
		));

		if ($update){
			$items = $this->extendObjects($this->tableName(), $items, array('name'));
		}

		foreach ($items as $inum => &$item) {
			$item = $this->clearValues($item);

			$fullItem = $items[$inum];

			if (!check_db_fields($itemDbFields, $item)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if ($update) {
				if (!isset($dbItems[$item['itemid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				// check for "templateid", because it is not allowed
				if (array_key_exists('templateid', $item)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot update "templateid" for item "%1$s".', $item['name']));
				}

				check_db_fields($dbItems[$item['itemid']], $fullItem);

				// apply rules
				foreach ($this->fieldRules as $field => $rules) {
					if ((0 != $fullItem['templateid'] && isset($rules['template'])) || isset($rules['system'])) {
						unset($item[$field]);
					}
				}

				if (!isset($item['key_'])) {
					$item['key_'] = $fullItem['key_'];
				}
				if (!isset($item['hostid'])) {
					$item['hostid'] = $fullItem['hostid'];
				}
				if (isset($item['status']) && $item['status'] != ITEM_STATUS_NOTSUPPORTED) {
					$item['error'] = '';
				}

				// if a templated item is being assigned to an interface with a different type, ignore it
				$itemInterfaceType = itemTypeInterface($dbItems[$item['itemid']]['type']);
				if ($fullItem['templateid'] && isset($item['interfaceid']) && isset($interfaces[$item['interfaceid']])
						&& $itemInterfaceType !== INTERFACE_TYPE_ANY && $interfaces[$item['interfaceid']]['type'] != $itemInterfaceType) {

					unset($item['interfaceid']);
				}
			}
			else {
				if (!isset($dbHosts[$item['hostid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				check_db_fields($itemDbFields, $fullItem);

				// check for "templateid", because it is not allowed
				if (array_key_exists('templateid', $item)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot set "templateid" for item "%1$s".', $item['name']));;
				}
			}

			$host = $dbHosts[$fullItem['hostid']];

			if ($fullItem['type'] == ITEM_TYPE_ZABBIX_ACTIVE) {
				$item['delay_flex'] = '';
			}
			if ($fullItem['value_type'] == ITEM_VALUE_TYPE_STR) {
				$item['delta'] = 0;
			}
			if ($fullItem['value_type'] != ITEM_VALUE_TYPE_UINT64) {
				$item['data_type'] = 0;
			}

			// check if the item requires an interface
			$itemInterfaceType = itemTypeInterface($fullItem['type']);
			if ($itemInterfaceType !== false && $host['status'] != HOST_STATUS_TEMPLATE) {
				if (!$fullItem['interfaceid']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No interface found.'));
				}
				elseif (!isset($interfaces[$fullItem['interfaceid']]) || bccomp($interfaces[$fullItem['interfaceid']]['hostid'], $fullItem['hostid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Item uses host interface from non-parent host.'));
				}
				elseif ($itemInterfaceType !== INTERFACE_TYPE_ANY && $interfaces[$fullItem['interfaceid']]['type'] != $itemInterfaceType) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Item uses incorrect interface type.'));
				}
			}
			// no interface required, just set it to null
			else {
				$item['interfaceid'] = 0;
			}

			// item key
			if (($fullItem['type'] == ITEM_TYPE_DB_MONITOR && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_DB_MONITOR) == 0)
					|| ($fullItem['type'] == ITEM_TYPE_SSH && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_SSH) == 0)
					|| ($fullItem['type'] == ITEM_TYPE_TELNET && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_TELNET) == 0)
					|| ($fullItem['type'] == ITEM_TYPE_JMX && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_JMX) == 0)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Check the key, please. Default example was passed.'));
			}

			$itemKey = new CItemKey($fullItem['key_']);
			if (!$itemKey->isValid()) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Error in item key: "%s".', $itemKey->getError()));
			}

			if ($fullItem['type'] == ITEM_TYPE_AGGREGATE) {
				$params = $itemKey->getParameters();

				if (!str_in_array($itemKey->getKeyId(), array('grpmax', 'grpmin', 'grpsum', 'grpavg'))
						|| count($params) != 4
						|| !str_in_array($params[2], array('last', 'min', 'max', 'avg', 'sum', 'count'))) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Key "%1$s" does not match <grpmax|grpmin|grpsum|grpavg>["Host group(s)", "Item key",'.
							' "<last|min|max|avg|sum|count>", "parameter"].', $itemKey->getKeyId()));
				}
			}

			if ($fullItem['type'] == ITEM_TYPE_SNMPTRAP
					&& strcmp($fullItem['key_'], 'snmptrap.fallback') != 0
					&& strcmp($itemKey->getKeyId(), 'snmptrap') != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('SNMP trap key is invalid.'));
			}

			// type of information
			if ($fullItem['type'] == ITEM_TYPE_AGGREGATE && $fullItem['value_type'] != ITEM_VALUE_TYPE_FLOAT
					&& $fullItem['value_type'] != ITEM_VALUE_TYPE_UINT64) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Type of information must be "Numeric (float)" for aggregate items.'));
			}

			if ($fullItem['value_type'] != ITEM_VALUE_TYPE_LOG && str_in_array($itemKey->getKeyId(), array('log', 'logrt', 'eventlog'))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Type of information must be "Log" for log key.'));
			}

			// update interval
			if ($fullItem['type'] != ITEM_TYPE_TRAPPER && $fullItem['type'] != ITEM_TYPE_SNMPTRAP) {
				$res = calculateItemNextcheck(0, 0, $fullItem['type'], $fullItem['delay'], $fullItem['delay_flex'], time());
				if ($res['delay'] == 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('Item will not be refreshed. Please enter a correct update interval.'));
				}
			}

			if ($fullItem['type'] == ITEM_TYPE_SSH || $fullItem['type'] == ITEM_TYPE_TELNET) {
				if (zbx_empty($fullItem['username'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No authentication user name specified.'));
				}

				if ($fullItem['type'] == ITEM_TYPE_SSH && $fullItem['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
					if (zbx_empty($fullItem['publickey'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No public key file specified.'));
					}
					if (zbx_empty($fullItem['privatekey'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No private key file specified.'));
					}
				}
			}

			// SNMP port
			if (isset($fullItem['port']) && !zbx_empty($fullItem['port']) && !validatePortNumberOrMacro($fullItem['port'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Item "%1$s:%2$s" has invalid port: "%3$s".', $fullItem['name'], $fullItem['key_'], $fullItem['port']));
			}

			// check that the given applications belong to the item's host
			if (isset($item['applications']) && $item['applications']) {
				$dbApplicationIds = zbx_objectValues($host['applications'], 'applicationid');
				foreach($item['applications'] as $appId) {
					if (!in_array($appId, $dbApplicationIds)) {
						$error = _s('Application with ID "%1$s" is not available on "%2$s".', $appId, $host['name']);
						self::exception(ZBX_API_ERROR_PARAMETERS, $error);
					}
				}
			}

			$this->checkDelayFlex($fullItem);

			$this->checkSpecificFields($fullItem);
		}
		unset($item);

		$this->checkExistingItems($items);
	}

	protected function checkSpecificFields(array $item) {
		return true;
	}

	protected function clearValues(array $item) {
		if (isset($item['port']) && $item['port'] != '') {
			$item['port'] = ltrim($item['port'], '0');
			if ($item['port'] == '') {
				$item['port'] = 0;
			}
		}

		if (isset($item['lifetime']) && $item['lifetime'] != '') {
			$item['lifetime'] = ltrim($item['lifetime'], '0');
			if ($item['lifetime'] == '') {
				$item['lifetime'] = 0;
			}
		}

		return $item;
	}

	protected function errorInheritFlags($flag, $key, $host) {
		switch ($flag) {
			case ZBX_FLAG_DISCOVERY_NORMAL:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as an item.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as a discovery rule.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY_CHILD:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as an item prototype.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY_CREATED:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as an item created from item prototype.', $key, $host));
				break;
			default:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as unknown item element.', $key, $host));
		}
	}

	/**
	 * Returns the interface that best matches the given item.
	 *
	 * @param array $itemType   An item
	 * @param array $interfaces An array of interfaces to choose from
	 *
	 * @return array|boolean    The best matching interface;
	 *							an empty array of no matching interface was found;
	 *							false, if the item does not need an interface
	 */
	public static function findInterfaceForItem(array $item, array $interfaces) {
		$typeInterface = array();
		foreach ($interfaces as $interface) {
			if ($interface['main'] == 1) {
				$typeInterface[$interface['type']] = $interface;
			}
		}

		// find item interface type
		$type = itemTypeInterface($item['type']);

		$matchingInterface = array();

		// the item can use any interface
		if ($type == INTERFACE_TYPE_ANY) {
			$interfaceTypes = array(
				INTERFACE_TYPE_AGENT,
				INTERFACE_TYPE_SNMP,
				INTERFACE_TYPE_JMX,
				INTERFACE_TYPE_IPMI
			);
			foreach ($interfaceTypes as $itype) {
				if (isset($typeInterface[$itype])) {
					$matchingInterface = $typeInterface[$itype];
					break;
				}
			}
		}
		// the item uses a specific type of interface
		elseif ($type !== false) {
			$matchingInterface = (isset($typeInterface[$type])) ? $typeInterface[$type] : array();
		}
		// the item does not need an interface
		else {
			$matchingInterface = false;
		}

		return $matchingInterface;
	}

	public function isReadable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'itemids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	public function isWritable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'itemids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	/**
	 * Checks whether the given items are referenced by any graphs and tries to
	 * unset these references, if they are no longer used.
	 *
	 * @throws APIException if at least one of the item can't be deleted
	 *
	 * @param array $itemids   An array of item IDs
	 */
	protected function checkGraphReference(array $itemids) {
		$this->checkUseInGraphAxis($itemids, true);
		$this->checkUseInGraphAxis($itemids);
	}

	/**
	 * Checks if any of the given items are used as min/max Y values in a graph.
	 *
	 * if there are graphs, that have an y*_itemid column set, but the
	 * y*_type column is not set to GRAPH_YAXIS_TYPE_ITEM_VALUE, the y*_itemid
	 * column will be set to NULL.
	 *
	 * If the $checkMax parameter is set to true, the items will be checked against
	 * max Y values, otherwise, they will be checked against min Y values.
	 *
	 * @throws APIException if any of the given items are used as min/max Y values in a graph.
	 *
	 * @param array $itemids   An array of items IDs
	 * @param type $checkMax
	 */
	protected function checkUseInGraphAxis(array $itemids, $checkMax = false) {
		if ($checkMax) {
			$filter = array('ymax_itemid' => $itemids);
			$itemIdColumn = 'ymax_itemid';
			$typeColumn = 'ymax_type';
		}
		else {
			$filter = array('ymin_itemid' => $itemids);
			$itemIdColumn = 'ymin_itemid';
			$typeColumn = 'ymin_type';
		}

		// make it work for both graphs and graph prototypes
		$filter['flags'] = array(
			ZBX_FLAG_DISCOVERY_CHILD,
			ZBX_FLAG_DISCOVERY_NORMAL,
			ZBX_FLAG_DISCOVERY_CREATED
		);

		// check if the items are used in Y axis min/max values in any graphs
		$graphs = API::Graph()->get(array(
			'output' => array($itemIdColumn, $typeColumn, 'graphtype'),
			'filter' => $filter
		));

		$updateGraphs = array();
		foreach ($graphs as &$graph) {
			// check if Y type is actually set to GRAPH_YAXIS_TYPE_ITEM_VALUE
			if ($graph[$typeColumn] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if ($checkMax) {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Could not delete these items because some of them are used as MAX values for graphs.');
				}
				else {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Could not delete these items because some of them are used as MIN values for graphs.');
				}
			}
			else {
				$graph[$itemIdColumn] = null;
				$updateGraphs[] = $graph;
			}
		}

		// if there are graphs, that have an y*_itemid column set, but the
		// y*_type column is not set to GRAPH_YAXIS_TYPE_ITEM_VALUE, set y*_itemid to NULL.
		// Otherwise we won't be able to delete them.
		if ($updateGraphs) {
			API::Graph()->update($updateGraphs);
		}
	}

	/**
	 * Updates the children of the item on the given hosts and propagates the inheritance to the child hosts.
	 *
	 * @abstract
	 *
	 * @param array $items          an array of items to inherit
	 * @param array|null $hostids   an array of hosts to inherit to; if set to null, teh children will be updated on all
	 *                              child hosts
	 *
	 * @return bool
	 */
	abstract protected function inherit(array $items, array $hostids = null);

	/**
	 * Prepares and returns an array of child items, inherited from items $itemsToInherit on the given hosts.
	 *
	 * @param array      $itemsToInherit
	 * @param array|null $hostIds
	 * @param array      $errors         an array of messages to use for errors
	 *
	 * @return array an array of unsaved child items
	 */
	protected function prepareInheritedItems(array $itemsToInherit, array $hostIds = null, array $errors = array()) {
		$errors = array_merge(
			array(
				'exists' => _('Item "%1$s" already exists on "%2$s", inherited from another template.'),
				'noInterface' => _('Cannot find host interface on "%1$s" for item key "%2$s".')
			),
			$errors
		);

		// fetch all child hosts
		$chdHosts = API::Host()->get(array(
			'output' => array('hostid', 'host', 'status'),
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'templateids' => zbx_objectValues($itemsToInherit, 'hostid'),
			'hostids' => $hostIds,
			'preservekeys' => true,
			'nopermissions' => true,
			'templated_hosts' => true
		));
		if (empty($chdHosts)) {
			return array();
		}

		$newItems = array();
		foreach ($chdHosts as $hostId => $host) {
			$templateids = zbx_toHash($host['templates'], 'templateid');

			// skip items not from parent templates of current host
			$parentItems = array();
			foreach ($itemsToInherit as $inum => $parentItem) {
				if (isset($templateids[$parentItem['hostid']])) {
					$parentItems[$inum] = $parentItem;
				}
			}

			// check existing items to decide insert or update
			$exItems = API::Item()->get(array(
				'output' => array('itemid', 'type', 'key_', 'flags', 'templateid'),
				'hostids' => $hostId,
				'preservekeys' => true,
				'nopermissions' => true,
				'filter' => array('flags' => null)
			));

			$exItemsKeys = zbx_toHash($exItems, 'key_');
			$exItemsTpl = zbx_toHash($exItems, 'templateid');

			foreach ($parentItems as $parentItem) {
				$exItem = null;

				// update by templateid
				if (isset($exItemsTpl[$parentItem['itemid']])) {
					$exItem = $exItemsTpl[$parentItem['itemid']];
				}

				// update by key
				if (isset($exItemsKeys[$parentItem['key_']])) {
					$exItem = $exItemsKeys[$parentItem['key_']];
					if ($exItem['flags'] != $parentItem['flags']) {
						$this->errorInheritFlags($exItem['flags'], $exItem['key_'], $host['host']);
					}
					elseif ($exItem['templateid'] > 0 && bccomp($exItem['templateid'], $parentItem['itemid']) != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s($errors['exists'], $parentItem['key_'], $host['host']));
					}
				}

				if ($host['status'] == HOST_STATUS_TEMPLATE || !isset($parentItem['type'])) {
					unset($parentItem['interfaceid']);
				}
				elseif ((isset($parentItem['type']) && isset($exItem) && $parentItem['type'] != $exItem['type']) || !isset($exItem)) {
					$interface = self::findInterfaceForItem($parentItem, $host['interfaces']);

					if (!empty($interface)) {
						$parentItem['interfaceid'] = $interface['interfaceid'];
					}
					elseif ($interface !== false) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s($errors['noInterface'], $host['host'], $parentItem['key_']));
					}
				}
				else {
					unset($parentItem['interfaceid']);
				}

				// copying item
				$newItem = $parentItem;
				$newItem['hostid'] = $host['hostid'];
				$newItem['templateid'] = $parentItem['itemid'];

				// setting item application
				if (isset($parentItem['applications'])) {
					$newItem['applications'] = get_same_applications_for_host($parentItem['applications'], $host['hostid']);
				}

				if ($exItem) {
					$newItem['itemid'] = $exItem['itemid'];
				}
				else {
					unset($newItem['itemid']);
				}
				$newItems[] = $newItem;
			}
		}

		return $newItems;
	}

	/**
	 * Check if any item from list already exists.
	 * If items have item ids it will check for existing item with different itemid.
	 *
	 * @throw APIException
	 *
	 * @param array $items
	 */
	protected function checkExistingItems(array $items) {
		$itemKeysByHostId = array();
		$itemIds = array();
		foreach ($items as $item) {
			if (!isset($itemKeysByHostId[$item['hostid']])) {
				$itemKeysByHostId[$item['hostid']] = array();
			}
			$itemKeysByHostId[$item['hostid']][] = $item['key_'];

			if (isset($item['itemid'])) {
				$itemIds[] = $item['itemid'];
			}
		}

		$sqlWhere = array();
		foreach ($itemKeysByHostId as $hostId => $keys) {
			$sqlWhere[] = '(i.hostid='.zbx_dbstr($hostId).' AND '.dbConditionString('i.key_', $keys).')';
		}

		if ($sqlWhere) {
			$sql = 'SELECT i.key_,h.host'.
					' FROM items i,hosts h'.
					' WHERE i.hostid=h.hostid AND ('.implode(' OR ', $sqlWhere).')';

			// if we update existing items we need to exclude them from result.
			if ($itemIds) {
				$sql .= ' AND '.dbConditionInt('i.itemid', $itemIds, true);
			}
			$dbItems = DBselect($sql, 1);
			while ($dbItem = DBfetch($dbItems)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Item with key "%1$s" already exists on "%2$s".', $dbItem['key_'], $dbItem['host']));
			}
		}
	}

	/**
	 * Validate flexible intervals.
	 * Flexible intervals is string with format:
	 *   'delay/day1-day2,time1-time2;interval2;interval3;...' (day2 is optional)
	 * Examples:
	 *   600/5-7,00:00-09:00;600/1-2,00:00-09:00
	 *   600/5,0:0-9:0;600/1-2,0:0-9:0
	 *
	 * @param array $item
	 *
	 * @return bool
	 */
	protected function checkDelayFlex(array $item) {
		if (array_key_exists('delay_flex', $item)) {
			$delayFlex = $item['delay_flex'];

			if (!is_string($delayFlex)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect flexible interval in item "%1$s". Flexible interval must be a string.', $item['name']));
			}

			if ($delayFlex === '') {
				return true;
			}

			$validator = new CTimePeriodValidator();
			$intervals = explode(';', rtrim($delayFlex, ';'));
			foreach ($intervals as $interval) {
				if (!preg_match('#^\d+/(.+)$#', $interval, $matches)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect flexible interval "%1$s".', $interval));
				}

				if (!$validator->validate($matches[1])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $validator->getError());
				}
			}
		}
	}
}
