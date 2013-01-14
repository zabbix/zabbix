<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
class CItemPrototype extends CItemGeneral {

	protected $tableName = 'items';
	protected $tableAlias = 'i';

	/**
	 * Get Itemprototype data
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('itemid', 'name', 'key_', 'delay', 'type', 'status');

		$sqlParts = array(
			'select'	=> array('items' => 'i.itemid'),
			'from'		=> array('items' => 'items i'),
			'where'		=> array('i.flags='.ZBX_FLAG_DISCOVERY_CHILD),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'itemids'					=> null,
			'discoveryids'				=> null,
			'graphids'					=> null,
			'triggerids'				=> null,
			'inherited'					=> null,
			'templated'					=> null,
			'monitored'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectHosts'				=> null,
			'selectApplications'		=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'selectDiscoveryRule'		=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ;

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE i.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.$permission.
					')';
		}

// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else{
				$options['hostids'] = $options['templateids'];
			}
		}

// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			if ($options['output'] != API_OUTPUT_EXTEND) {
				$sqlParts['select']['hostid'] = 'i.hostid';
			}

			$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['hostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['where']['itemid'] = dbConditionInt('i.itemid', $options['itemids']);
		}

// discoveryids
		if (!is_null($options['discoveryids'])) {
			zbx_value2array($options['discoveryids']);

			$sqlParts['select']['discoveryid'] = 'id.parent_itemid';
			$sqlParts['from']['item_discovery'] = 'item_discovery id';
			$sqlParts['where'][] = dbConditionInt('id.parent_itemid', $options['discoveryids']);
			$sqlParts['where']['idi'] = 'i.itemid=id.itemid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['id'] = 'id.parent_itemid';
			}
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['select']['triggerid'] = 'f.triggerid';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['if'] = 'i.itemid=f.itemid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['select']['graphid'] = 'gi.graphid';
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
		}

// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited'])
				$sqlParts['where'][] = 'i.templateid IS NOT NULL';
			else
				$sqlParts['where'][] = 'i.templateid IS NULL';
		}

// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated'])
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			else
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
		}

// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['monitored']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sqlParts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else{
				$sqlParts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}


// search
		if (is_array($options['search'])) {
			zbx_db_search('items i', $options, $sqlParts);
		}

// --- FILTER ---
		if (is_array($options['filter'])) {
			$this->dbFilter('items i', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['h'] = dbConditionString('h.host', $options['filter']['host']);
			}
		}

		// sorting
		$this->dbSorting($sqlParts, $options, $sortColumns, 'i');

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//----------

		$itemids = array();
		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($item = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $item;
				else
					$result = $item['rowscount'];
			}
			else{
				$itemids[$item['itemid']] = $item['itemid'];

				if (!isset($result[$item['itemid']])) {
					$result[$item['itemid']]= array();
				}

				// hostids
				if (isset($item['hostid']) && is_null($options['selectHosts'])) {
					if (!isset($result[$item['itemid']]['hosts'])) $result[$item['itemid']]['hosts'] = array();
					$result[$item['itemid']]['hosts'][] = array('hostid' => $item['hostid']);
				}

				// triggerids
				if (isset($item['triggerid']) && is_null($options['selectTriggers'])) {
					if (!isset($result[$item['itemid']]['triggers']))
						$result[$item['itemid']]['triggers'] = array();

					$result[$item['itemid']]['triggers'][] = array('triggerid' => $item['triggerid']);
					unset($item['triggerid']);
				}

				// graphids
				if (isset($item['graphid']) && is_null($options['selectGraphs'])) {
					if (!isset($result[$item['itemid']]['graphs']))
						$result[$item['itemid']]['graphs'] = array();

					$result[$item['itemid']]['graphs'][] = array('graphid' => $item['graphid']);
					unset($item['graphid']);
				}

				// discoveryids
				if (isset($item['discoveryids'])) {
					if (!isset($result[$item['itemid']]['discovery']))
						$result[$item['itemid']]['discovery'] = array();

					$result[$item['itemid']]['discovery'][] = array('ruleid' => $item['item_parentid']);
					unset($item['item_parentid']);
				}

				$result[$item['itemid']] += $item;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// add other related objects
		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, array('hostid'), $options['output']);
		}

		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	public function exists($object) {
		$options = array(
			'filter' => array('key_' => $object['key_']),
			'output' => array('itemid'),
			'nopermissions' => 1,
			'limit' => 1
		);

		if (isset($object['hostid'])) $options['hostids'] = $object['hostid'];
		if (isset($object['host'])) $options['filter']['host'] = $object['host'];

		if (isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		elseif (isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = $this->get($options);

		return !empty($objs);
	}

	/**
	 * Check item prototype data and set flags field.
	 *
	 * @param array $items passed by reference
	 * @param bool  $update
	 *
	 * @return void
	 */
	protected function checkInput(array &$items, $update = false) {
		// add the values that cannot be changed, but are required for further processing
		foreach ($items as &$item) {
			$item['flags'] = ZBX_FLAG_DISCOVERY_CHILD;
		}
		unset($item);

		parent::checkInput($items, $update);
	}

/**
 * Add Itemprototype
 *
 * @param array $items
 * @return array|boolean
 */
	public function create($items) {
		$items = zbx_toArray($items);
		$this->checkInput($items);
		$this->createReal($items);
		$this->inherit($items);

		return array('itemids' => zbx_objectValues($items, 'itemid'));
	}

	protected function createReal(&$items) {
		$itemids = DB::insert('items', $items);

		$itemApplications = $insertItemDiscovery = array();
		foreach ($items as $key => $item) {
			$items[$key]['itemid'] = $itemids[$key];

			$insertItemDiscovery[] = array(
				'itemid' => $items[$key]['itemid'],
				'parent_itemid' => $item['ruleid']
			);

			if (isset($item['applications'])) {
				foreach ($item['applications'] as $anum => $appid) {
					if ($appid == 0) continue;

					$itemApplications[] = array(
						'applicationid' => $appid,
						'itemid' => $items[$key]['itemid']
					);
				}
			}
		}

		DB::insert('item_discovery', $insertItemDiscovery);

		if (!empty($itemApplications)) {
			DB::insert('items_applications', $itemApplications);
		}

// TODO: REMOVE info
		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('name'),
			'selectHosts' => array('name'),
			'nopermissions' => true
		));
		foreach ($itemHosts as $item) {
			$host = reset($item['hosts']);
			info(_s('Created: Item prototype "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	protected function updateReal($items) {
		$items = zbx_toArray($items);

		$data = array();
		foreach ($items as $inum => $item) {
			$data[] = array('values' => $item, 'where'=> array('itemid' => $item['itemid']));
		}

		$result = DB::update('items', $data);
		if (!$result) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
		}

		$itemids = array();
		$itemidsWithApplications = array();
		$itemApplications = array();
		foreach ($items as $item) {
			if (!isset($item['applications'])) {
				array_push($itemids, $item['itemid']);
				continue;
			}

			$itemidsWithApplications[] = $item['itemid'];
			foreach ($item['applications'] as $anum => $appid) {
				$itemApplications[] = array(
					'applicationid' => $appid,
					'itemid' => $item['itemid']
				);
			}
		}

		if (!empty($itemidsWithApplications)) {
			DB::delete('items_applications', array('itemid' => $itemidsWithApplications));
			DB::insert('items_applications', $itemApplications);
		}

// TODO: REMOVE info
		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('name'),
			'selectHosts' => array('name'),
			'nopermissions' => true,
		));

		foreach ($itemHosts as $item) {
			$host = reset($item['hosts']);
			info(_s('Updated: Item prototype "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

/**
 * Update Itemprototype
 *
 * @param array $items
 * @return boolean
 */
	public function update($items) {
		$items = zbx_toArray($items);
		$this->checkInput($items, true);
		$this->updateReal($items);
		$this->inherit($items);

		return array('itemids' => zbx_objectValues($items, 'itemid'));
	}

	/**
	 * Delete Item prototypes.
	 *
	 * @param int|string|array $prototypeids
	 * @param bool             $nopermissions
	 *
	 * @return array
	 */
	public function delete($prototypeids, $nopermissions = false) {
		if (empty($prototypeids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$delItemPrototypeIds = zbx_toArray($prototypeids);
		$prototypeids = zbx_toHash($prototypeids);

		$options = array(
			'itemids' => $prototypeids,
			'editable' => true,
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => array('name')
		);
		$delItemPrototypes = $this->get($options);

		// TODO: remove $nopermissions hack
		if (!$nopermissions) {
			foreach ($prototypeids as $prototypeid) {
				if (!isset($delItemPrototypes[$prototypeid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
				}
				if ($delItemPrototypes[$prototypeid]['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete templated items'));
				}
			}
		}

		// first delete child items
		$parentItemids = $prototypeids;
		$childPrototypeids = array();
		do {
			$dbItems = DBselect('SELECT itemid FROM items WHERE '.dbConditionInt('templateid', $parentItemids));
			$parentItemids = array();
			while ($dbItem = DBfetch($dbItems)) {
				$parentItemids[$dbItem['itemid']] = $dbItem['itemid'];
				$childPrototypeids[$dbItem['itemid']] = $dbItem['itemid'];
			}
		} while (!empty($parentItemids));

		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $childPrototypeids,
			'nopermissions' => true,
			'preservekeys' => true,
			'selectHosts' => array('name')
		);
		$delItemPrototypesChilds = $this->get($options);

		$delItemPrototypes = array_merge($delItemPrototypes, $delItemPrototypesChilds);
		$prototypeids = array_merge($prototypeids, $childPrototypeids);

		// delete graphs with this item prototype
		$delGraphPrototypes = API::GraphPrototype()->get(array(
			'itemids' => $prototypeids,
			'output' => array('graphid'),
			'nopermissions' => true,
			'preservekeys' => true
		));
		if (!empty($delGraphPrototypes)) {
			$result = API::GraphPrototype()->delete(zbx_objectValues($delGraphPrototypes, 'graphid'), true);
			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete graph prototype'));
			}
		}

		// check if any graphs are referencing this item
		$this->checkGraphReference($prototypeids);

// CREATED ITEMS
		$createdItems = array();
		$sql = 'SELECT itemid FROM item_discovery WHERE '.dbConditionInt('parent_itemid', $prototypeids);
		$dbItems = DBselect($sql);
		while ($item = DBfetch($dbItems)) {
			$createdItems[$item['itemid']] = $item['itemid'];
		}
		if (!empty($createdItems)) {
			$result = API::Item()->delete($createdItems, true);
			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete items created by low level discovery.'));
			}
		}


// TRIGGER PROTOTYPES
		$delTriggerPrototypes = API::TriggerPrototype()->get(array(
			'itemids' => $prototypeids,
			'output' => array('triggerid'),
			'nopermissions' => true,
			'preservekeys' => true,
		));
		if (!empty($delTriggerPrototypes)) {
			$result = API::TriggerPrototype()->delete(zbx_objectValues($delTriggerPrototypes, 'triggerid'), true);
			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete trigger prototype'));
			}
		}


// ITEM PROTOTYPES
		DB::delete('items', array('itemid' => $prototypeids));


// TODO: remove info from API
		foreach ($delItemPrototypes as $item) {
			$host = reset($item['hosts']);
			info(_s('Deleted: Item prototype "%1$s" on "%2$s".', $item['name'], $host['name']));
		}

		return array('prototypeids' => $delItemPrototypeIds);
	}

	public function syncTemplates($data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		if (!API::Host()->isWritable($data['hostids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}
		if (!API::Template()->isReadable($data['templateids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$selectFields = array();
		foreach ($this->fieldRules as $key => $rules) {
			if (!isset($rules['system']) && !isset($rules['host'])) {
				$selectFields[] = $key;
			}
		}

		$items = $this->get(array(
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'selectApplications' => API_OUTPUT_REFER,
			'output' => $selectFields
		));

		foreach ($items as $inum => $item) {
			$items[$inum]['applications'] = zbx_objectValues($item['applications'], 'applicationid');
		}

		$this->inherit($items, $data['hostids']);

		return true;
	}

	protected function inherit(array $items, array $hostids = null) {
		if (!$items) {
			return true;
		}

		// fetch the corresponding discovery rules for the child items
		$ruleids = array();
		$dbResult = DBselect(
			'SELECT i.itemid AS ruleid,id.itemid,i.hostid'.
			' FROM items i,item_discovery id'.
			' WHERE i.templateid=id.parent_itemid'.
				' AND '.dbConditionInt('id.itemid', zbx_objectValues($items, 'itemid'))
		);
		while ($rule = DBfetch($dbResult)) {
			if (!isset($ruleids[$rule['itemid']])) {
				$ruleids[$rule['itemid']] = array();
			}
			$ruleids[$rule['itemid']][$rule['hostid']] = $rule['ruleid'];
		}

		// prepare the child items
		$newItems = $this->prepareInheritedItems($items, $hostids);
		if (!$items) {
			return true;
		}

		$insertItems = array();
		$updateItems = array();
		foreach ($newItems as $newItem) {
			if (isset($newItem['itemid'])) {
				unset($newItem['ruleid']);
				$updateItems[] = $newItem;
			}
			else {
				// set the corresponding discovery rule id for the new items
				$newItem['ruleid'] = $ruleids[$newItem['templateid']][$newItem['hostid']];
				$newItem['flags'] = ZBX_FLAG_DISCOVERY_CHILD;
				$insertItems[] = $newItem;
			}
		}

		// save the new items
		$this->createReal($insertItems);
		$this->updateReal($updateItems);

		// propagate the inheritance to the children
		$this->inherit(array_merge($insertItems, $updateItems));
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			if ($options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect('i.hostid', $sqlParts);
			}
		}

		return $sqlParts;
	}

	public function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$itemids = array_keys($result);

		// adding applications
		if ($options['selectApplications'] !== null && $options['selectApplications'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'itemid', 'applicationid', 'items_applications');
			$applications = API::Application()->get(array(
				'output' => $options['selectApplications'],
				'nodeids' => $options['nodeids'],
				'applicationids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $applications, 'applications');
		}

		// adding triggers
		if (!is_null($options['selectTriggers'])) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'itemid', 'triggerid', 'functions');
				$triggers = API::TriggerPrototype()->get(array(
					'output' => $options['selectTriggers'],
					'nodeids' => $options['nodeids'],
					'triggerids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				));

				if (!is_null($options['limitSelects'])) {
					order_result($triggers, 'description');
				}
				$result = $relationMap->mapMany($result, $triggers, 'triggers', $options['limitSelects']);
			}
			else {
				$triggers = API::TriggerPrototype()->get(array(
					'countOutput' => true,
					'groupCount' => true,
					'nodeids' => $options['nodeids'],
					'itemids' => $itemids
				));
				$triggers = zbx_toHash($triggers, 'itemid');

				foreach ($result as $itemid => $item) {
					if (isset($triggers[$itemid])) {
						$result[$itemid]['triggers'] = $triggers[$itemid]['rowscount'];
					}
					else {
						$result[$itemid]['triggers'] = 0;
					}
				}
			}
		}

		// adding graphs
		if (!is_null($options['selectGraphs'])) {
			if ($options['selectGraphs'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'itemid', 'graphid', 'graphs_items');
				$graphs = API::GraphPrototype()->get(array(
					'output' => $options['selectGraphs'],
					'nodeids' => $options['nodeids'],
					'graphids' => $relationMap->getRelatedIds('graphs'),
					'preservekeys' => true
				));

				if (!is_null($options['limitSelects'])) {
					order_result($graphs, 'name');
				}
				$result = $relationMap->mapMany($result, $graphs, 'graphs', $options['limitSelects']);
			}
			else {
				$graphs = API::GraphPrototype()->get(array(
					'countOutput' => true,
					'groupCount' => true,
					'nodeids' => $options['nodeids'],
					'itemids' => $itemids
				));
				$graphs = zbx_toHash($graphs, 'itemid');

				foreach ($result as $itemid => $item) {
					if (isset($graphs[$itemid])) {
						$result[$itemid]['graphs'] = $graphs[$itemid]['rowscount'];
					}
					else {
						$result[$itemid]['graphs'] = 0;
					}
				}
			}
		}

		// adding discoveryrule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'itemid', 'parent_itemid', 'item_discovery');
			$discoveryRules = API::DiscoveryRule()->get(array(
				'output' => $options['selectDiscoveryRule'],
				'nodeids' => $options['nodeids'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true,
			));
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		return $result;
	}
}
