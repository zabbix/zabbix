<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * Class containing methods for operations with item prototypes.
 *
 * @package API
 */
class CItemPrototype extends CItemGeneral {

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = array('itemid', 'name', 'key_', 'delay', 'history', 'trends', 'type', 'status');

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, array(
			self::ERROR_EXISTS_TEMPLATE => _('Item prototype "%1$s" already exists on "%2$s", inherited from another template.'),
			self::ERROR_EXISTS => _('Item prototype "%1$s" already exists on "%2$s".'),
			self::ERROR_INVALID_KEY => _('Invalid key "%1$s" for item prototype "%2$s" on "%3$s": %4$s.')
		));
	}

	/**
	 * Get Itemprototype data.
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = array(
			'select'	=> array('items' => 'i.itemid'),
			'from'		=> array('items' => 'items i'),
			'where'		=> array('i.flags='.ZBX_FLAG_DISCOVERY_PROTOTYPE),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'groupids'						=> null,
			'templateids'					=> null,
			'hostids'						=> null,
			'itemids'						=> null,
			'discoveryids'					=> null,
			'graphids'						=> null,
			'triggerids'					=> null,
			'inherited'						=> null,
			'templated'						=> null,
			'monitored'						=> null,
			'editable'						=> null,
			'nopermissions'					=> null,
			// filter
			'filter'						=> null,
			'search'						=> null,
			'searchByAny'					=> null,
			'startSearch'					=> null,
			'excludeSearch'					=> null,
			'searchWildcardsEnabled'		=> null,
			// output
			'output'						=> API_OUTPUT_EXTEND,
			'selectHosts'					=> null,
			'selectApplications'			=> null,
			'selectApplicationPrototypes'	=> null,
			'selectTriggers'				=> null,
			'selectGraphs'					=> null,
			'selectDiscoveryRule'			=> null,
			'countOutput'					=> null,
			'groupCount'					=> null,
			'preservekeys'					=> null,
			'sortfield'						=> '',
			'sortorder'						=> '',
			'limit'							=> null,
			'limitSelects'					=> null
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
						' AND MAX(r.permission)>='.zbx_dbstr($permission).
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

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['if'] = 'i.itemid=f.itemid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

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

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//----------

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($item = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $item;
				else
					$result = $item['rowscount'];
			}
			else {
				$result[$item['itemid']] = $item;
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

	/**
	 * Check item prototype data and set flags field.
	 *
	 * @param array  $items										an array of items passed by reference
	 * @param array  $item['applicationPrototypes']				an array of application prototypes
	 * @param string $item['applicationPrototypes'][]['name']	application prototype name
	 * @param bool	 $update
	 */
	protected function checkInput(array &$items, $update = false) {
		parent::checkInput($items, $update);

		// set proper flags to divide normal and discovered items in future processing
		foreach ($items as &$item) {
			$item['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;

			// set default formula value
			if (!$update && !isset($item['formula'])) {
				$item['formula'] = '1';
			}

			if (isset($item['applicationPrototypes']) && is_array($item['applicationPrototypes'])
					&& $item['applicationPrototypes']) {
				// Check that "name" field exists for application prototypes.
				foreach ($item['applicationPrototypes'] as $application_prototype) {
					if (!array_key_exists('name', $application_prototype)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Missing "name" field for application prototype in item prototype "%1$s".', $item['name']
						));
					}
				}

				// Check that "name" field has no duplicate values for application prototypes.
				$duplicate_name = CArrayHelper::findDuplicate($item['applicationPrototypes'], 'name');
				if ($duplicate_name) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Duplicate "name" value "%1$s" for application prototype in item prototype "%2$s".',
						$duplicate_name['name'],
						$item['name']
					));
				}
			}
		}
		unset($item);
	}

	/**
	 * Create item prototype.
	 *
	 * @param array $items
	 *
	 * @return array
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

		$item_application_prototypes = array();

		foreach ($items as $item) {
			// 'applicationPrototypes' is an array of 'name' properties and it should not be empty.
			if (isset($item['applicationPrototypes']) && is_array($item['applicationPrototypes'])
					&& $item['applicationPrototypes']) {
				// Get only application prototypes that already exist with this name in this discovery rule.
				$db_application_prototypes = DBfetchArray(DBselect(
					'SELECT ap.application_prototypeid,ap.name'.
					' FROM application_prototype ap'.
					' WHERE ap.itemid='.zbx_dbstr($item['ruleid']).
						' AND '.dbConditionString('ap.name', zbx_objectValues($item['applicationPrototypes'], 'name'))
				));

				$names = zbx_objectValues($db_application_prototypes, 'name');

				$application_prototypes_to_create = array();

				foreach ($item['applicationPrototypes'] as $application_prototype) {
					if (!in_array($application_prototype['name'], $names)) {
						$application_prototypes_to_create[] = array(
							'itemid' => $item['ruleid'],
							'name' => $application_prototype['name']
						);
					}
				}

				// Get newly created application prototype IDs and old existing IDs for linkage.
				$new_ids = array();

				if ($application_prototypes_to_create) {
					$new_ids = DB::insert('application_prototype', $application_prototypes_to_create);
				}

				$ids = array_merge($new_ids, zbx_objectValues($db_application_prototypes, 'application_prototypeid'));

				foreach ($ids as $id) {
					$item_application_prototypes[] = array(
						'application_prototypeid' => $id,
						'itemid' => $item['itemid']
					);
				}
			}
		}

		// Link item prototypes to application prototypes.
		if ($item_application_prototypes) {
			DB::insert('item_application_prototype', $item_application_prototypes);
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

		// application prototype names that need to be created in database
		$application_prototypes_to_create = array();

		// application prototypes that are no longer linked to items will be deleted from database
		$application_prototypes_to_remove = array();

		// currently linked item prototypes and application prototypes
		$old_records = array();

		// records that will be added or replaced with in item_application_prototype table
		$new_records = array();

		foreach ($items as $item) {
			/*
			 * 'applicationPrototypes' is an array of 'name' properties. It can also be an empty array in case
			 * application prototypes should be unlinked from item prototypes.
			 */
			if (isset($item['applicationPrototypes']) && is_array($item['applicationPrototypes'])) {
				// Get discovery rule ID for current item prototype, if it is not yet set.
				if (isset($item['ruleid'])) {
					$discovery_ruleid = $item['ruleid'];
				}
				else {
					$discovery_rule = DBfetch(DBselect(
						'SELECT id.parent_itemid'.
						' FROM item_discovery id'.
						' WHERE id.itemid='.zbx_dbstr($item['itemid'])
					));
					$discovery_ruleid = $discovery_rule['parent_itemid'];
				}

				// Get currently linked application prototypes to current item prototype.
				$db_item_application_prototypes = DBfetchArray(DBselect(
					'SELECT DISTINCT ap.application_prototypeid,ap.name,iap.itemapplicationprototypeid'.
					' FROM application_prototype ap,item_application_prototype iap'.
					' WHERE ap.application_prototypeid=iap.application_prototypeid'.
						' AND ap.itemid='.zbx_dbstr($discovery_ruleid).
						' AND iap.itemid='.zbx_dbstr($item['itemid'])
				));

				// Gather all item_application_prototype records for each item.
				foreach ($db_item_application_prototypes as $item_application_prototype) {
					$id = $item_application_prototype['itemapplicationprototypeid'];

					$old_records[$id] = array(
						'itemapplicationprototypeid' => $id,
						'application_prototypeid' => $item_application_prototype['application_prototypeid'],
						'itemid' => $item['itemid']
					);
				}

				// Compare which application prototypes should be linked (but first created) and which should be unlinked.
				$db_item_application_prototypes = zbx_toHash($db_item_application_prototypes, 'name');
				$new_application_prototypes = zbx_toHash($item['applicationPrototypes'], 'name');

				$application_prototypes_to_link = array_diff_key($new_application_prototypes,
					$db_item_application_prototypes
				);
				$application_prototypes_to_unlink = array_diff_key($db_item_application_prototypes,
					$new_application_prototypes
				);

				if ($application_prototypes_to_link) {
					/*
					 * Check given application prototype names if they exist in database. If they exist, return IDs.
					 * Other application prototypes will be created later.
					 */
					$db_application_prototypes = DBfetchArray(DBselect(
						'SELECT ap.application_prototypeid,ap.name'.
						' FROM application_prototype ap'.
						' WHERE ap.itemid='.zbx_dbstr($discovery_ruleid).
							' AND '.dbConditionString('ap.name', array_keys($new_application_prototypes))
					));

					$names = zbx_objectValues($db_application_prototypes, 'name');

					// Cycle each application prototype and check if it exists. If not, we will create it.
					$application_prototypes_to_create = array();
					foreach ($new_application_prototypes as $application_prototype) {
						if (!in_array($application_prototype['name'], $names)) {
							$application_prototypes_to_create[] = array(
								'itemid' => $discovery_ruleid,
								'name' => $application_prototype['name']
							);
						}
					}

					// Collect already existing application prototype IDs
					foreach ($db_application_prototypes as $application_prototype) {
						$new_records[] = array(
							'application_prototypeid' => $application_prototype['application_prototypeid'],
							'itemid' => $item['itemid']
						);
					}

					// Create new application prototypes, get new IDs and add them to new records.
					if ($application_prototypes_to_create) {
						$ids = DB::insert('application_prototype', $application_prototypes_to_create);

						foreach ($ids as $id) {
							$new_records[] = array(
								'application_prototypeid' => $id,
								'itemid' => $item['itemid']
							);
						}
					}
				}

				/*
				 * Collect application prototype IDs that will be unlinked from item prototypes, in case those
				 * application prototypes should be permanently deleted when no longer linked to any item prototypes.
				 */
				foreach ($application_prototypes_to_unlink as $application_prototype) {
					$application_prototypes_to_remove[$application_prototype['application_prototypeid']] = true;
				}
			}
		}

		// Remove unchanged records.
		foreach ($old_records as $old_record) {
			foreach ($new_records as $num => $new_record) {
				if ($old_record['application_prototypeid'] == $new_record['application_prototypeid']
						&& $old_record['itemid'] == $new_record['itemid']) {
					unset($new_records[$num]);
				}
			}
		}

		DB::replace('item_application_prototype', $old_records, $new_records);

		// Find and delete application prototype from database that are no longer linked to any item prototypes.
		if ($application_prototypes_to_remove) {
			$db_application_prototypes = DBfetchArray(DBselect(
				'SELECT ap.application_prototypeid'.
				' FROM application_prototype ap'.
				' WHERE NOT EXISTS ('.
					'SELECT NULL'.
					' FROM item_application_prototype iap'.
					' WHERE ap.application_prototypeid=iap.application_prototypeid'.
				')'.
				' AND '.dbConditionInt('ap.application_prototypeid', array_keys($application_prototypes_to_remove))
			));

			if ($db_application_prototypes) {
				DB::delete('application_prototype', array(
					'application_prototypeid' => zbx_objectValues($db_application_prototypes, 'application_prototypeid')
				));
			}
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
	 * Update Itemprototype.
	 *
	 * @param array $items
	 *
	 * @return array
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
	 * @param array $prototypeids
	 * @param bool 	$nopermissions
	 *
	 * @return array
	 */
	public function delete(array $prototypeids, $nopermissions = false) {
		if (empty($prototypeids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$prototypeids = array_keys(array_flip($prototypeids));

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
		} while ($parentItemids);

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
			'output' => array(),
			'itemids' => $prototypeids,
			'nopermissions' => true,
			'preservekeys' => true
		));
		if ($delGraphPrototypes) {
			API::GraphPrototype()->delete(array_keys($delGraphPrototypes), true);
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
		if ($createdItems) {
			API::Item()->delete($createdItems, true);
		}


// TRIGGER PROTOTYPES
		$delTriggerPrototypes = API::TriggerPrototype()->get(array(
			'output' => array(),
			'itemids' => $prototypeids,
			'nopermissions' => true,
			'preservekeys' => true,
		));
		if ($delTriggerPrototypes) {
			API::TriggerPrototype()->delete(array_keys($delTriggerPrototypes), true);
		}

		// screen items
		DB::delete('screens_items', array(
			'resourceid' => $prototypeids,
			'resourcetype' => array(SCREEN_RESOURCE_LLD_SIMPLE_GRAPH)
		));

// ITEM PROTOTYPES
		DB::delete('items', array('itemid' => $prototypeids));


// TODO: remove info from API
		foreach ($delItemPrototypes as $item) {
			$host = reset($item['hosts']);
			info(_s('Deleted: Item prototype "%1$s" on "%2$s".', $item['name'], $host['name']));
		}

		return array('prototypeids' => $prototypeids);
	}

	public function syncTemplates($data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$selectFields = array();
		foreach ($this->fieldRules as $key => $rules) {
			if (!isset($rules['system']) && !isset($rules['host'])) {
				$selectFields[] = $key;
			}
		}

		$items = $this->get(array(
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'selectApplications' => array('applicationid'),
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
				$newItem['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
				$insertItems[] = $newItem;
			}
		}

		// save the new items
		if ($insertItems) {
			$this->createReal($insertItems);
		}

		if ($updateItems) {
			$this->updateReal($updateItems);
		}

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
				'applicationids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $applications, 'applications');
		}

		// adding application prototypes
		if ($options['selectApplicationPrototypes'] !== null
				&& $options['selectApplicationPrototypes'] != API_OUTPUT_COUNT) {
			$pkFieldId = $this->pk('application_prototype');
			$outputFields = array(
				$pkFieldId => $this->fieldId($pkFieldId, 'ap')
			);

			if (is_array($options['selectApplicationPrototypes'])) {
				foreach ($options['selectApplicationPrototypes'] as $field) {
					if ($this->hasField($field, 'application_prototype')) {
						$outputFields[$field] = $this->fieldId($field, 'ap');
					}
				}

				$outputFields = implode(',', $outputFields);
			}
			else {
				$outputFields = 'ap.*';
			}

			$relationMap = $this->createRelationMap($result, 'itemid', 'application_prototypeid',
				'item_application_prototype'
			);

			$application_prototypes = DBfetchArray(DBselect(
				'SELECT '.$outputFields.
				' FROM application_prototype ap'.
				' WHERE '.dbConditionInt('ap.application_prototypeid', $relationMap->getRelatedIds())
			));

			$application_prototypes = zbx_toHash($application_prototypes, 'application_prototypeid');

			$result = $relationMap->mapMany($result, $application_prototypes, 'applicationPrototypes');
		}

		// adding triggers
		if (!is_null($options['selectTriggers'])) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'itemid', 'triggerid', 'functions');
				$triggers = API::TriggerPrototype()->get(array(
					'output' => $options['selectTriggers'],
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
					'graphids' => $relationMap->getRelatedIds(),
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
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true,
			));
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		return $result;
	}
}
