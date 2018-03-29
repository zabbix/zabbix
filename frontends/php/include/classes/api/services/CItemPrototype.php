<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 */
class CItemPrototype extends CItemGeneral {

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'history', 'trends', 'type', 'status'];

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, [
			self::ERROR_EXISTS_TEMPLATE => _('Item prototype "%1$s" already exists on "%2$s", inherited from another template.'),
			self::ERROR_EXISTS => _('Item prototype "%1$s" already exists on "%2$s".'),
			self::ERROR_INVALID_KEY => _('Invalid key "%1$s" for item prototype "%2$s" on "%3$s": %4$s.')
		]);
	}

	/**
	 * Get ItemPrototype data.
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['items' => 'i.itemid'],
			'from'		=> ['items' => 'items i'],
			'where'		=> ['i.flags='.ZBX_FLAG_DISCOVERY_PROTOTYPE],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
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
			'editable'						=> false,
			'nopermissions'					=> null,
			// filter
			'filter'						=> null,
			'search'						=> null,
			'searchByAny'					=> null,
			'startSearch'					=> false,
			'excludeSearch'					=> false,
			'searchWildcardsEnabled'		=> null,
			// output
			'output'						=> API_OUTPUT_EXTEND,
			'selectHosts'					=> null,
			'selectApplications'			=> null,
			'selectApplicationPrototypes'	=> null,
			'selectTriggers'				=> null,
			'selectGraphs'					=> null,
			'selectDiscoveryRule'			=> null,
			'selectPreprocessing'			=> null,
			'countOutput'					=> false,
			'groupCount'					=> false,
			'preservekeys'					=> false,
			'sortfield'						=> '',
			'sortorder'						=> '',
			'limit'							=> null,
			'limitSelects'					=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

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

			if ($options['groupCount']) {
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

			if ($options['groupCount']) {
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
			if (array_key_exists('delay', $options['filter']) && $options['filter']['delay'] !== null) {
				$sqlParts['where'][] = makeUpdateIntervalFilter('i.delay', $options['filter']['delay']);
				unset($options['filter']['delay']);
			}

			if (array_key_exists('history', $options['filter']) && $options['filter']['history'] !== null) {
				$options['filter']['history'] = getTimeUnitFilters($options['filter']['history']);
			}

			if (array_key_exists('trends', $options['filter']) && $options['filter']['trends'] !== null) {
				$options['filter']['trends'] = getTimeUnitFilters($options['filter']['trends']);
			}

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
			if ($options['countOutput']) {
				if ($options['groupCount'])
					$result[] = $item;
				else
					$result = $item['rowscount'];
			}
			else {
				$result[$item['itemid']] = $item;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		// add other related objects
		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);
		}

		// Decode ITEM_TYPE_HTTPAGENT encoded fields.
		$json = new CJson();

		foreach ($result as &$item) {
			if (array_key_exists('query_fields', $item)) {
				$query_fields = ($item['query_fields'] !== '') ? $json->decode($item['query_fields'], true) : [];
				$item['query_fields'] = $json->hasError() ? [] : $query_fields;
			}

			if (array_key_exists('headers', $item)) {
				$item['headers'] = $this->headersStringToArray($item['headers']);
			}
		}

		if (!$options['preservekeys']) {
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

			if (array_key_exists('applicationPrototypes', $item) && is_array($item['applicationPrototypes'])
					&& $item['applicationPrototypes']) {
				// Check that "name" field exists for application prototypes.
				foreach ($item['applicationPrototypes'] as $application_prototype) {
					if (!array_key_exists('name', $application_prototype)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Missing "name" field for application prototype in item prototype "%1$s".', $item['name']
						));
					}

					if ($application_prototype['name'] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Empty application prototype name in item prototype "%1$s".', $item['name']
						));
					}

					if (array_key_exists('templateid', $application_prototype)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Cannot set "templateid" field for application prototype in item prototype "%1$s".',
							$item['name']
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

		foreach ($items as &$item) {
			unset($item['itemid']);
		}

		$this->validateDependentItems($items, API::ItemPrototype());

		$json = new CJson();

		foreach ($items as &$item) {
			if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
				if (array_key_exists('query_fields', $item)) {
					$item['query_fields'] = $item['query_fields'] ? $json->encode($item['query_fields']) : '';
				}

				if (array_key_exists('headers', $item)) {
					$item['headers'] = $this->headersArrayToString($item['headers']);
				}
			}
			else {
				$item['query_fields'] = '';
				$item['headers'] = '';
			}
		}
		unset($item);

		$this->createReal($items);
		$this->inherit($items);

		return ['itemids' => zbx_objectValues($items, 'itemid')];
	}

	protected function createReal(&$items) {
		foreach ($items as &$item) {
			if ($item['type'] != ITEM_TYPE_DEPENDENT) {
				$item['master_itemid'] = null;
			}
		}
		unset($item);
		$itemids = DB::insert('items', $items);

		$itemApplications = $insertItemDiscovery = [];
		foreach ($items as $key => $item) {
			$items[$key]['itemid'] = $itemids[$key];

			$insertItemDiscovery[] = [
				'itemid' => $items[$key]['itemid'],
				'parent_itemid' => $item['ruleid']
			];

			if (isset($item['applications'])) {
				foreach ($item['applications'] as $anum => $appid) {
					if ($appid == 0) continue;

					$itemApplications[] = [
						'applicationid' => $appid,
						'itemid' => $items[$key]['itemid']
					];
				}
			}
		}

		DB::insertBatch('item_discovery', $insertItemDiscovery);

		if ($itemApplications) {
			DB::insertBatch('items_applications', $itemApplications);
		}

		$item_application_prototypes = [];

		foreach ($items as $item) {
			// 'applicationPrototypes' is an array of 'name' properties and it should not be empty.
			if (array_key_exists('applicationPrototypes', $item) && is_array($item['applicationPrototypes'])
					&& $item['applicationPrototypes']) {
				// Get only application prototypes that already exist with this name in this discovery rule.
				$db_application_prototypes = DBfetchArray(DBselect(
					'SELECT ap.application_prototypeid,ap.name'.
					' FROM application_prototype ap'.
					' WHERE ap.itemid='.zbx_dbstr($item['ruleid']).
						' AND '.dbConditionString('ap.name', zbx_objectValues($item['applicationPrototypes'], 'name'))
				));

				$names = zbx_objectValues($db_application_prototypes, 'name');

				$application_prototypes_to_create = [];

				foreach ($item['applicationPrototypes'] as $application_prototype) {
					if (!in_array($application_prototype['name'], $names)) {
						$application_prototypes_to_create[] = [
							'itemid' => $item['ruleid'],
							'name' => $application_prototype['name'],
							'templateid' => array_key_exists('templateid', $application_prototype)
								? $application_prototype['templateid']
								: null
						];
					}
				}

				// Get newly created application prototype IDs and old existing IDs for linkage.
				$new_ids = [];

				if ($application_prototypes_to_create) {
					$new_ids = DB::insertBatch('application_prototype', $application_prototypes_to_create);
				}

				$ids = array_merge($new_ids, zbx_objectValues($db_application_prototypes, 'application_prototypeid'));

				foreach ($ids as $id) {
					$item_application_prototypes[] = [
						'application_prototypeid' => $id,
						'itemid' => $item['itemid']
					];
				}
			}
		}

		// Link item prototypes to application prototypes.
		if ($item_application_prototypes) {
			DB::insertBatch('item_application_prototype', $item_application_prototypes);
		}

		$this->createItemPreprocessing($items);
	}

	protected function updateReal($items) {
		$items = zbx_toArray($items);

		$data = [];
		foreach ($items as $inum => $item) {
			$data[] = ['values' => $item, 'where'=> ['itemid' => $item['itemid']]];
		}

		$result = DB::update('items', $data);
		if (!$result) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
		}

		$itemids = [];
		$itemidsWithApplications = [];
		$itemApplications = [];
		foreach ($items as $item) {
			if (!isset($item['applications'])) {
				array_push($itemids, $item['itemid']);
				continue;
			}

			$itemidsWithApplications[] = $item['itemid'];
			foreach ($item['applications'] as $anum => $appid) {
				$itemApplications[] = [
					'applicationid' => $appid,
					'itemid' => $item['itemid']
				];
			}
		}

		if (!empty($itemidsWithApplications)) {
			DB::delete('items_applications', ['itemid' => $itemidsWithApplications]);
			DB::insertBatch('items_applications', $itemApplications);
		}

		// application prototypes that are no longer linked to items will be deleted from database
		$application_prototypes_to_remove = [];

		// currently linked item prototypes and application prototypes
		$old_records = [];

		// records that will be added or replaced with in item_application_prototype table
		$new_records = [];

		// Get discovery rule IDs for all items.
		$itemids_with_application_prototypes = [];

		foreach ($items as $item) {
			if (array_key_exists('applicationPrototypes', $item) && is_array($item['applicationPrototypes'])
					&& !array_key_exists('ruleid', $item)) {
				$itemids_with_application_prototypes[$item['itemid']] = true;
			}
		}

		if ($itemids_with_application_prototypes) {
			$discovery_rules = DBfetchArray(DBselect(
				'SELECT id.itemid,id.parent_itemid'.
				' FROM item_discovery id'.
				' WHERE '.dbConditionInt('id.itemid', array_keys($itemids_with_application_prototypes))
			));
			$discovery_rules = zbx_toHash($discovery_rules, 'itemid');
		}

		// Process application prototypes.
		foreach ($items as $item) {
			/*
			 * "applicationPrototypes" is an array of "name" properties. It can also be an empty array in case
			 * application prototypes should be unlinked from item prototypes.
			 */
			if (array_key_exists('applicationPrototypes', $item) && is_array($item['applicationPrototypes'])) {
				// Get discovery rule ID for current item prototype, if it is not yet set.
				if (array_key_exists('ruleid', $item)) {
					$discovery_ruleid = $item['ruleid'];
				}
				else {
					$discovery_ruleid = $discovery_rules[$item['itemid']]['parent_itemid'];
				}

				/*
				 * Get currently linked application prototypes to current item prototype together
				 * existing application prototypes in this discovery rule. There cannot be unlinked
				 * application prototypes.
				 */
				$db_item_application_prototypes = DBfetchArray(DBselect(
					'SELECT DISTINCT ap.application_prototypeid,ap.name,ap.templateid,iap.item_application_prototypeid'.
					' FROM application_prototype ap,item_application_prototype iap'.
					' WHERE ap.application_prototypeid=iap.application_prototypeid'.
						' AND ap.itemid='.zbx_dbstr($discovery_ruleid).
						' AND iap.itemid='.zbx_dbstr($item['itemid'])
				));

				// Gather all item application prototype records in $old_records for each item.
				foreach ($db_item_application_prototypes as $db_item_application_prototype) {
					$id = $db_item_application_prototype['item_application_prototypeid'];
					$application_prototypeid = $db_item_application_prototype['application_prototypeid'];

					$old_records[$id] = [
						'item_application_prototypeid' => $id,
						'application_prototypeid' => $application_prototypeid,
						'itemid' => $item['itemid']
					];
				}

				$application_prototypes = zbx_toHash($item['applicationPrototypes'], 'name');

				/*
				 * Check given application prototype names if they exist in database. If they exist, return IDs.
				 * Other application prototypes will be created later.
				 */
				$db_application_prototypes = DBfetchArray(DBselect(
					'SELECT ap.application_prototypeid,ap.name,ap.templateid'.
					' FROM application_prototype ap'.
					' WHERE ap.itemid='.zbx_dbstr($discovery_ruleid).
						' AND '.dbConditionString('ap.name', array_keys($application_prototypes))
				));

				$names = [];
				foreach ($db_application_prototypes as $db_application_prototype) {
					$names[] = (string) $db_application_prototype['name'];
				}

				$db_application_prototypes = zbx_toHash($db_application_prototypes, 'name');

				// New application prototype names that need to be created in database.
				$application_prototypes_to_create = [];

				// An array that contains "application_prototypeid" as key and "templateid" as value.
				$application_prototypes_to_update = [];

				/*
				 * Cycle each application prototype and check if it exists. If not, we will create it. Else for existing
				 * application prototypes check only if template ID has changed. No other parameters can change.
				 * Otherwise application prototypes are deleted or stay unchanged.
				 */
				foreach ($application_prototypes as $application_prototype) {
					if (!in_array((string) $application_prototype['name'], $names, true)) {
						$application_prototypes_to_create[] = [
							'itemid' => $discovery_ruleid,
							'name' => $application_prototype['name'],
							'templateid' => array_key_exists('templateid', $application_prototype)
								? $application_prototype['templateid']
								: null
						];
					}
					elseif (array_key_exists('templateid', $application_prototype)) {
						$db_application_prototype = $db_application_prototypes[$application_prototype['name']];

						if ($db_application_prototype['templateid'] != $application_prototype['templateid']) {
							$id = $db_application_prototype['application_prototypeid'];

							$application_prototypes_to_update[$id] = $application_prototype['templateid'];
						}
					}
				}

				// Collect already existing application prototype IDs.
				foreach ($db_application_prototypes as $db_application_prototype) {
					$application_prototypeid = $db_application_prototype['application_prototypeid'];
					$new_application_prototype = $application_prototypes[$db_application_prototype['name']];

					$templateid = array_key_exists('templateid', $new_application_prototype)
						? $new_application_prototype['templateid']
						: 0;

					$new_records[] = [
						'application_prototypeid' => $application_prototypeid,
						'itemid' => $item['itemid']
					];
				}

				// Create new application prototypes, get new IDs.
				if ($application_prototypes_to_create) {
					$ids = DB::insertBatch('application_prototype', $application_prototypes_to_create);

					foreach ($ids as $id) {
						$new_records[] = [
							'application_prototypeid' => $id,
							'itemid' => $item['itemid']
						];
					}
				}

				// Update application prototype template IDs.
				if ($application_prototypes_to_update) {
					foreach ($application_prototypes_to_update as $application_prototypeid => $templateid) {
						DB::update('application_prototype', [
							'values' => ['templateid' => $templateid],
							'where' => ['application_prototypeid' => $application_prototypeid]
						]);
					}
				}

				/*
				 * Collect application prototype IDs that will be unlinked from item prototypes, in case those
				 * application prototypes should be permanently deleted when no longer linked to any item prototypes.
				 */
				$db_item_application_prototypes = zbx_toHash($db_item_application_prototypes, 'name');

				$application_prototypes_to_unlink = array_diff_key($db_item_application_prototypes,
					$application_prototypes
				);
				foreach ($application_prototypes_to_unlink as $application_prototype) {
					$application_prototypes_to_remove[$application_prototype['application_prototypeid']] = true;
				}
			}
		}

		// Remove unchanged records.
		foreach ($old_records as $i => $old_record) {
			foreach ($new_records as $j => $new_record) {
				if ($old_record['application_prototypeid'] == $new_record['application_prototypeid']
						&& $old_record['itemid'] == $new_record['itemid']) {
					unset($old_records[$i]);
					unset($new_records[$j]);
				}
			}
		}

		// Find discovered applications that have been discovered from only one rule and delete them.
		DB::replace('item_application_prototype', $old_records, $new_records);

		// Find and delete application prototypes from database that are no longer linked to any item prototypes.
		if ($application_prototypes_to_remove) {
			$this->deleteApplicationPrototypes(array_keys($application_prototypes_to_remove));
		}

		$this->updateItemPreprocessing($items);
	}

	/**
	 * Update ItemPrototype.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	public function update($items) {
		$items = zbx_toArray($items);

		$this->checkInput($items, true);
		$this->validateDependentItems($items, API::ItemPrototype());

		$db_items = $this->get([
			'output' => ['type', 'master_itemid', 'authtype', 'allow_traps', 'retrieve_mode'],
			'itemids' => zbx_objectValues($items, 'itemid'),
			'editable' => true,
			'preservekeys' => true
		]);

		$items = $this->extendFromObjects(zbx_toHash($items, 'itemid'), $db_items, ['type']);

		$defaults = DB::getDefaults('items');
		$clean = [
			ITEM_TYPE_HTTPAGENT => [
				'url' => '',
				'query_fields' => '',
				'timeout' => $defaults['timeout'],
				'status_codes' => '',
				'follow_redirects' => $defaults['follow_redirects'],
				'request_method' => $defaults['request_method'],
				'allow_traps' => $defaults['allow_traps'],
				'post_type' => $defaults['post_type'],
				'http_proxy' => '',
				'headers' => '',
				'retrieve_mode' => $defaults['retrieve_mode'],
				'output_format' => $defaults['output_format'],
				'ssl_key_password' => '',
				'verify_peer' => $defaults['verify_peer'],
				'verify_host' => $defaults['verify_host'],
				'ssl_cert_file' => '',
				'ssl_key_file' => '',
				'authtype' => $defaults['authtype'],
				'username' => '',
				'password' => '',
				'posts' => ''
			]
		];

		$json = new CJson();

		foreach ($items as &$item) {
			$type_change = ($item['type'] != $db_items[$item['itemid']]['type']);

			if ($item['type'] != ITEM_TYPE_DEPENDENT && $db_items[$item['itemid']]['master_itemid']) {
				$item['master_itemid'] = null;
			}
			elseif (!array_key_exists('master_itemid', $item)) {
				$item['master_itemid'] = $db_items[$item['itemid']]['master_itemid'];
			}

			if ($type_change && $db_items[$item['itemid']]['type'] == ITEM_TYPE_HTTPAGENT) {
				$item = array_merge($item, $clean[ITEM_TYPE_HTTPAGENT]);

				if ($item['type'] != ITEM_TYPE_SSH) {
					$item['authtype'] = $defaults['authtype'];
					$item['username'] = '';
					$item['password'] = '';
				}

				if ($item['type'] != ITEM_TYPE_TRAPPER) {
					$item['trapper_hosts'] = '';
				}
			}

			if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
				// Clean username and password on authtype change to HTTPTEST_AUTH_NONE.
				if (array_key_exists('authtype', $item) && $item['authtype'] == HTTPTEST_AUTH_NONE
						&& $item['authtype'] != $db_items[$item['itemid']]['authtype']) {
					$item['username'] = '';
					$item['password'] = '';
				}

				if (array_key_exists('allow_traps', $item) && $item['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_OFF
						&& $item['allow_traps'] != $db_items[$item['itemid']]['allow_traps']) {
					$item['trapper_hosts'] = '';
				}

				if (array_key_exists('query_fields', $item) && is_array($item['query_fields'])) {
					$item['query_fields'] = $item['query_fields'] ? $json->encode($item['query_fields']) : '';
				}

				if (array_key_exists('headers', $item) && is_array($item['headers'])) {
					$item['headers'] = $this->headersArrayToString($item['headers']);
				}

				if (array_key_exists('request_method', $item) && $item['request_method'] == HTTPCHECK_REQUEST_HEAD
						&& !array_key_exists('retrieve_mode', $item)
						&& $db_items[$item['itemid']]['retrieve_mode'] != HTTPTEST_STEP_RETRIEVE_MODE_HEADERS) {
					$item['retrieve_mode'] = HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
				}
			}
			else {
				$item['query_fields'] = '';
				$item['headers'] = '';
			}
		}
		unset($item);

		$this->updateReal($items);
		$this->inherit($items);

		return ['itemids' => zbx_objectValues($items, 'itemid')];
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

		$options = [
			'itemids' => $prototypeids,
			'editable' => true,
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND
		];
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
		$childPrototypeids = [];
		do {
			$dbItems = DBselect('SELECT itemid FROM items WHERE '.dbConditionInt('templateid', $parentItemids));
			$parentItemids = [];
			while ($dbItem = DBfetch($dbItems)) {
				$parentItemids[$dbItem['itemid']] = $dbItem['itemid'];
				$childPrototypeids[$dbItem['itemid']] = $dbItem['itemid'];
			}
		} while ($parentItemids);

		$db_dependent_items = $delItemPrototypes + $childPrototypeids;
		$dependent_itemprototypes = [];
		// Master item deletion will remove dependent items on database level.
		while ($db_dependent_items) {
			$db_dependent_items = $this->get([
				'output' => ['itemid', 'name'],
				'filter' => ['type' => ITEM_TYPE_DEPENDENT, 'master_itemid' => array_keys($db_dependent_items)],
				'preservekeys' => true
			]);
			$db_dependent_items = array_diff_key($db_dependent_items, $dependent_itemprototypes);
			$dependent_itemprototypes += $db_dependent_items;
		};
		$dependent_itemprototypeids = array_keys($dependent_itemprototypes);
		$childPrototypeids += array_combine($dependent_itemprototypeids, $dependent_itemprototypeids);

		$prototypeids = array_merge($prototypeids, $childPrototypeids);

		// Delete graphs or leave them if graphs still have at least one item prototype.
		$del_graph_prototypes = [];
		$db_graph_prototypes = DBselect(
			'SELECT gi.graphid'.
			' FROM graphs_items gi'.
			' WHERE '.dbConditionInt('gi.itemid', $prototypeids).
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM graphs_items gii,items i'.
					' WHERE gi.graphid=gii.graphid'.
						' AND gii.itemid=i.itemid'.
						' AND '.dbConditionInt('i.itemid', $prototypeids, true).
						' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]).
				')'
		);
		while ($db_graph_prototype = DBfetch($db_graph_prototypes)) {
			$del_graph_prototypes[] = $db_graph_prototype['graphid'];
		}

		if ($del_graph_prototypes) {
			API::GraphPrototype()->delete($del_graph_prototypes, true);
		}

		// check if any graphs are referencing this item
		$this->checkGraphReference($prototypeids);

// CREATED ITEMS
		$createdItems = [];
		$sql = 'SELECT itemid FROM item_discovery WHERE '.dbConditionInt('parent_itemid', $prototypeids);
		$dbItems = DBselect($sql);
		while ($item = DBfetch($dbItems)) {
			$createdItems[$item['itemid']] = $item['itemid'];
		}
		if ($createdItems) {
			// This API call will also make sure that discovered applications are no longer linked to other items.
			API::Item()->delete($createdItems, true);
		}


// TRIGGER PROTOTYPES
		$delTriggerPrototypes = API::TriggerPrototype()->get([
			'output' => [],
			'itemids' => $prototypeids,
			'nopermissions' => true,
			'preservekeys' => true,
		]);
		if ($delTriggerPrototypes) {
			API::TriggerPrototype()->delete(array_keys($delTriggerPrototypes), true);
		}

		// screen items
		DB::delete('screens_items', [
			'resourceid' => $prototypeids,
			'resourcetype' => [SCREEN_RESOURCE_LLD_SIMPLE_GRAPH]
		]);

		// Unlink application prototypes and delete those who are no longer linked to any other item prototypes.
		$db_item_application_prototypes = DBfetchArray(DBselect(
			'SELECT iap.item_application_prototypeid,iap.application_prototypeid'.
			' FROM item_application_prototype iap'.
			' WHERE '.dbConditionInt('iap.itemid', $prototypeids)
		));

		if ($db_item_application_prototypes) {
			DB::delete('item_application_prototype', [
				'item_application_prototypeid' => zbx_objectValues($db_item_application_prototypes,
					'item_application_prototypeid'
				)
			]);

			$this->deleteApplicationPrototypes(zbx_objectValues($db_item_application_prototypes,
				'application_prototypeid'
			));
		}

// ITEM PROTOTYPES
		DB::delete('items', ['itemid' => $prototypeids]);

		$prototypeids = array_map('strval', $prototypeids);

		return ['prototypeids' => $prototypeids];
	}

	/*
	 * Finds and deletes application prototypes by given IDs. Looks for discovered applications that were created from
	 * prototypes and deletes them if they are not discovered by other rules.
	 *
	 * @param array $application_prototypeids
	 */
	protected function deleteApplicationPrototypes(array $application_prototypeids) {
		$db_application_prototypes = DBfetchArray(DBselect(
			'SELECT ap.application_prototypeid'.
			' FROM application_prototype ap'.
			' WHERE NOT EXISTS ('.
				'SELECT NULL'.
				' FROM item_application_prototype iap'.
				' WHERE ap.application_prototypeid=iap.application_prototypeid'.
			')'.
			' AND '.dbConditionInt('ap.application_prototypeid', $application_prototypeids)
		));

		if ($db_application_prototypes) {
			// Find discovered applications for deletable application prototypes.
			$discovered_applications = DBfetchArray(DBselect(
				'SELECT DISTINCT ad.applicationid'.
				' FROM application_discovery ad'.
				' WHERE '.dbConditionInt('ad.application_prototypeid', $application_prototypeids)
			));

			$db_application_prototypeids = zbx_objectValues($db_application_prototypes, 'application_prototypeid');

			// unlink templated application prototype
			DB::update('application_prototype', [
				'values' => ['templateid' => null],
				'where' => ['templateid' => $db_application_prototypeids]
			]);

			DB::delete('application_prototype', ['application_prototypeid' => $db_application_prototypeids]);

			/*
			 * Deleting an application prototype will automatically delete the link in 'item_application_prototype',
			 * but it will not delete the actual discovered application. When the link is gone,
			 * delete the discoveted application. Link between a regular item does not matter any more.
			 */
			if ($discovered_applications) {
				$discovered_applicationids = zbx_objectValues($discovered_applications, 'applicationid');

				$discovered_applications_to_delete = DBfetchArray(DBselect(
					'SELECT DISTINCT ad.applicationid'.
					' FROM application_discovery ad'.
					' WHERE '.dbConditionInt('ad.applicationid', $discovered_applicationids)
				));

				$applications_to_delete = array_diff($discovered_applicationids,
					zbx_objectValues($discovered_applications_to_delete, 'applicationid')
				);

				if ($applications_to_delete) {
					API::Application()->delete($applications_to_delete, true);
				}
			}
		}
	}

	public function syncTemplates($data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$output = [];
		foreach ($this->fieldRules as $field_name => $rules) {
			if (!array_key_exists('system', $rules) && !array_key_exists('host', $rules)) {
				$output[] = $field_name;
			}
		}

		$tpl_items = $this->get([
			'output' => $output,
			'selectApplications' => ['applicationid'],
			'selectApplicationPrototypes' => ['name'],
			'selectPreprocessing' => ['type', 'params'],
			'hostids' => $data['templateids'],
			'preservekeys' => true
		]);

		$json = new CJson();

		foreach ($tpl_items as &$tpl_item) {
			$tpl_item['applications'] = zbx_objectValues($tpl_item['applications'], 'applicationid');

			if ($tpl_item['type'] == ITEM_TYPE_HTTPAGENT) {
				if (array_key_exists('query_fields', $tpl_item) && is_array($tpl_item['query_fields'])) {
					$tpl_item['query_fields'] = $tpl_item['query_fields']
						? $json->encode($tpl_item['query_fields'])
						: '';
				}

				if (array_key_exists('headers', $tpl_item) && is_array($tpl_item['headers'])) {
					$tpl_item['headers'] = $this->headersArrayToString($tpl_item['headers']);
				}
			}
			else {
				$tpl_item['query_fields'] = '';
				$tpl_item['headers'] = '';
			}
		}
		unset($tpl_item);

		$this->inherit($tpl_items, $data['hostids']);

		return true;
	}

	/**
	 * Check item prototype specific fields:
	 *		- validate history and trends using simple interval parser, user macro parser and lld macro parser;
	 *		- validate item preprocessing.
	 *
	 * @param array  $item    An array of single item data.
	 * @param string $method  A string of "create" or "update" method.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function checkSpecificFields(array $item, $method) {
		if (array_key_exists('history', $item)
				&& !validateTimeUnit($item['history'], SEC_PER_HOUR, 25 * SEC_PER_YEAR, true, $error,
					['usermacros' => true, 'lldmacros' => true])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'history', $error)
			);
		}

		if (array_key_exists('trends', $item)
				&& !validateTimeUnit($item['trends'], SEC_PER_DAY, 25 * SEC_PER_YEAR, true, $error,
					['usermacros' => true, 'lldmacros' => true])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'trends', $error)
			);
		}

		$this->validateItemPreprocessing($item, $method);
	}

	protected function inherit(array $items, array $hostids = null) {
		if (!$items) {
			return;
		}

		// prepare the child items
		$new_items = $this->prepareInheritedItems($items, $hostids);
		if (!$new_items) {
			return;
		}

		$ins_items = [];
		$upd_items = [];
		foreach ($new_items as $new_item) {
			if (array_key_exists('itemid', $new_item)) {
				unset($new_item['ruleid']);
				$upd_items[] = $new_item;
			}
			else {
				// set the corresponding discovery rule id for the new items
				$ins_items[] = $new_item;
			}
		}

		// save the new items
		if ($ins_items) {
			$this->createReal($ins_items);
		}

		if ($upd_items) {
			$this->updateReal($upd_items);
		}

		$new_items = array_merge($upd_items, $ins_items);

		// Update master_itemid for inserted or updated inherited dependent items.
		$this->inheritDependentItems($new_items);

		// Inheriting items from the templates.
		$tpl_items = DBselect(
			'SELECT i.itemid'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionInt('i.itemid', zbx_objectValues($new_items, 'itemid')).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE])
		);

		$tpl_itemids = [];
		while ($tpl_item = DBfetch($tpl_items)) {
			$tpl_itemids[$tpl_item['itemid']] = true;
		}

		foreach ($new_items as $index => $new_item) {
			if (!array_key_exists($new_item['itemid'], $tpl_itemids)) {
				unset($new_items[$index]);
			}
		}

		// Inheriting items from the templates.
		$this->inherit($new_items);
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput']) {
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
			$applications = API::Application()->get([
				'output' => $options['selectApplications'],
				'applicationids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $applications, 'applications');
		}

		// adding application prototypes
		if ($options['selectApplicationPrototypes'] !== null
				&& $options['selectApplicationPrototypes'] != API_OUTPUT_COUNT) {
			$pkFieldId = $this->pk('application_prototype');
			$outputFields = [
				$pkFieldId => $this->fieldId($pkFieldId, 'ap')
			];

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
				$triggers = API::TriggerPrototype()->get([
					'output' => $options['selectTriggers'],
					'triggerids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);

				if (!is_null($options['limitSelects'])) {
					order_result($triggers, 'description');
				}
				$result = $relationMap->mapMany($result, $triggers, 'triggers', $options['limitSelects']);
			}
			else {
				$triggers = API::TriggerPrototype()->get([
					'countOutput' => true,
					'groupCount' => true,
					'itemids' => $itemids
				]);
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
				$graphs = API::GraphPrototype()->get([
					'output' => $options['selectGraphs'],
					'graphids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);

				if (!is_null($options['limitSelects'])) {
					order_result($graphs, 'name');
				}
				$result = $relationMap->mapMany($result, $graphs, 'graphs', $options['limitSelects']);
			}
			else {
				$graphs = API::GraphPrototype()->get([
					'countOutput' => true,
					'groupCount' => true,
					'itemids' => $itemids
				]);
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
			$discoveryRules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		if ($options['selectPreprocessing'] !== null && $options['selectPreprocessing'] != API_OUTPUT_COUNT) {
			$db_item_preproc = API::getApiService()->select('item_preproc', [
				'output' => $this->outputExtend($options['selectPreprocessing'], ['itemid', 'step']),
				'filter' => ['itemid' => array_keys($result)],
			]);

			CArrayHelper::sort($db_item_preproc, ['step']);

			foreach ($result as &$item) {
				$item['preprocessing'] = [];
			}
			unset($item);

			foreach ($db_item_preproc as $step) {
				$itemid = $step['itemid'];
				unset($step['item_preprocid'], $step['itemid'], $step['step']);

				if (array_key_exists($itemid, $result)) {
					$result[$itemid]['preprocessing'][] = $step;
				}
			}
		}

		return $result;
	}
}
