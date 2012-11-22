<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

/**
 * @package API
 */
class CItem extends CItemGeneral {

	protected $tableName = 'items';
	protected $tableAlias = 'i';

	/**
	 * Get items data.
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['triggerids']
	 * @param array $options['applicationids']
	 * @param boolean $options['status']
	 * @param boolean $options['templated_items']
	 * @param boolean $options['editable']
	 * @param boolean $options['count']
	 * @param string $options['pattern']
	 * @param int $options['limit']
	 * @param string $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('itemid', 'name', 'key_', 'delay', 'history', 'trends', 'type', 'status');

		$sqlParts = array(
			'select'	=> array('items' => 'i.itemid'),
			'from'		=> array('items' => 'items i'),
			'where'		=> array('webtype' => 'i.type<>'.ITEM_TYPE_HTTPTEST),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'proxyids'					=> null,
			'itemids'					=> null,
			'interfaceids'				=> null,
			'graphids'					=> null,
			'triggerids'				=> null,
			'applicationids'			=> null,
			'webitems'					=> null,
			'inherited'					=> null,
			'templated'					=> null,
			'monitored'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			'group'						=> null,
			'host'						=> null,
			'application'				=> null,
			'with_triggers'				=> null,
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
			'selectInterfaces'			=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'selectApplications'		=> null,
			'selectDiscoveryRule'		=> null,
			'selectItemDiscovery'		=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		if (is_array($options['output'])) {
			unset($sqlParts['select']['items']);

			$dbTable = DB::getSchema('items');
			$sqlParts['select']['itemid'] = 'i.itemid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 'i.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// editable + permission check
		if (USER_TYPE_SUPER_ADMIN == $userType || $options['nopermissions']) {
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['rights'] = 'rights r';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = 'hg.hostid=i.hostid';
			$sqlParts['where'][] = 'r.id=hg.groupid ';
			$sqlParts['where'][] = 'r.groupid=ug.usrgrpid';
			$sqlParts['where'][] = 'ug.userid='.$userid;
			$sqlParts['where'][] = 'r.permission>='.$permission;
			$sqlParts['where'][] = 'NOT EXISTS ('.
				' SELECT hgg.groupid'.
					' FROM hosts_groups hgg,rights rr,users_groups gg'.
					' WHERE hgg.hostid=hg.hostid'.
						' AND rr.id=hgg.groupid'.
						' AND rr.groupid=gg.usrgrpid'.
						' AND gg.userid='.$userid.
						' AND rr.permission='.PERM_DENY.')';
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['where']['itemid'] = DBcondition('i.itemid', $options['itemids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		if ($options['hostids'] !== null || $options['selectHosts'] !== null) {
			zbx_value2array($options['hostids']);

			// filter
			if ($options['hostids'] !== null) {
				$sqlParts['where']['hostid'] = DBcondition('i.hostid', $options['hostids']);

				if (!is_null($options['groupCount'])) {
					$sqlParts['group']['i'] = 'i.hostid';
				}
			}

			// make sure we retrieve the hostid property to be able to map hosts to items
			$sqlParts = $this->addQuerySelect('i.hostid', $sqlParts);
		}

		// interfaceids
		if ($options['interfaceids'] !== null || $options['selectInterfaces'] !== null) {
			zbx_value2array($options['interfaceids']);

			// filter
			if ($options['interfaceids'] !== null) {
				$sqlParts['where']['interfaceid'] = DBcondition('i.interfaceid', $options['interfaceids']);

				if (!is_null($options['groupCount'])) {
					$sqlParts['group']['i'] = 'i.interfaceid';
				}
			}

			// make sure we retrieve the hostid property to be able to map hosts to items
			$sqlParts = $this->addQuerySelect('i.interfaceid', $sqlParts);
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['select']['groupid'] = 'hg.groupid';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=i.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// proxyids
		if (!is_null($options['proxyids'])) {
			zbx_value2array($options['proxyids']);

			if ($options['output'] != API_OUTPUT_EXTEND) {
				$sqlParts['select']['proxyid'] = 'h.proxy_hostid';
			}

			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where'][] = DBcondition('h.proxy_hostid', $options['proxyids']);
			$sqlParts['where'][] = 'h.hostid=i.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['h'] = 'h.proxy_hostid';
			}
		}

		// triggerids
		if ($options['triggerids'] !== null
			|| ($options['selectTriggers'] !== null && $options['selectTriggers'] != API_OUTPUT_COUNT)) {

			zbx_value2array($options['triggerids']);

			$sqlParts['select']['triggerid'] = 'f.triggerid';
			$sqlParts['leftJoin']['functions'] = 'functions f ON i.itemid=f.itemid';

			// filter
			if ($options['triggerids'] !== null) {
				$sqlParts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			}
		}

		// applicationids
		if ($options['applicationids'] !== null
			|| ($options['selectApplications'] !== null && $options['selectApplications'] != API_OUTPUT_COUNT)) {

			zbx_value2array($options['applicationids']);

			$sqlParts['select']['applicationid'] = 'ia.applicationid';
			$sqlParts['leftJoin']['items_applications'] = 'items_applications ia ON ia.itemid=i.itemid';

			// filter
			if ($options['applicationids'] !== null) {
				$sqlParts['where'][] = DBcondition('ia.applicationid', $options['applicationids']);
			}
		}

		// graphids
		if ($options['graphids'] !== null
			|| ($options['selectGraphs'] !== null && $options['selectGraphs'] != API_OUTPUT_COUNT)) {

			zbx_value2array($options['graphids']);

			$sqlParts['select']['graphid'] = 'gi.graphid';
			$sqlParts['leftJoin']['graphs_items'] = 'graphs_items gi ON i.itemid=gi.itemid';

			// filter
			if ($options['graphids'] !== null) {
				$sqlParts['where'][] = DBcondition('gi.graphid', $options['graphids']);
			}
		}

		// item discovery
		if ($options['selectItemDiscovery'] !== null) {
			$sqlParts['leftJoin']['item_discovery'] = 'item_discovery id ON i.itemid=id.itemid';
			$sqlParts = $this->addQuerySelect('id.itemdiscoveryid', $sqlParts);
		}

		// webitems
		if (!is_null($options['webitems'])) {
			unset($sqlParts['where']['webtype']);
		}

		// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited']) {
				$sqlParts['where'][] = 'i.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 'i.templateid IS NULL';
			}
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['monitored']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sqlParts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else {
				$sqlParts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('items i', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('items i', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['h'] = DBcondition('h.host', $options['filter']['host'], false, true);
			}
		}

		// group
		if (!is_null($options['group'])) {
			$sqlParts['from']['groups'] = 'groups g';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['ghg'] = 'g.groupid=hg.groupid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where'][] = ' g.name='.zbx_dbstr($options['group']);
		}

		// host
		if (!is_null($options['host'])) {
			$sqlParts['select']['host'] = 'h.host';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where'][] = ' h.host='.zbx_dbstr($options['host']);
		}

		// application
		if (!is_null($options['application'])) {
			$sqlParts['select']['application'] = 'a.name as application';
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['from']['items_applications'] = 'items_applications ia';
			$sqlParts['where']['aia'] = 'a.applicationid = ia.applicationid';
			$sqlParts['where']['iai'] = 'ia.itemid=i.itemid';
			$sqlParts['where'][] = ' a.name='.zbx_dbstr($options['application']);
		}

		// with_triggers
		if (!is_null($options['with_triggers'])) {
			if ($options['with_triggers'] == 1) {
				$sqlParts['where'][] = ' EXISTS (SELECT ff.functionid FROM functions ff WHERE ff.itemid=i.itemid)';
			}
			else {
				$sqlParts['where'][] = 'NOT EXISTS (SELECT ff.functionid FROM functions ff WHERE ff.itemid=i.itemid)';
			}
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['items'] = 'i.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('COUNT(DISTINCT i.itemid) AS rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'i');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$itemids = array();

		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);

		$relationMap = new CRelationMap();
		while ($item = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $item;
				}
				else {
					$result = $item['rowscount'];
				}
			}
			else {
				$itemids[$item['itemid']] = $item['itemid'];

				if (!isset($result[$item['itemid']])) {
					$result[$item['itemid']]= array();
				}

				// triggerids
				if (isset($item['triggerid']) && is_null($options['selectTriggers'])) {
					if (!isset($result[$item['itemid']]['triggers'])) {
						$result[$item['itemid']]['triggers'] = array();
					}
					$result[$item['itemid']]['triggers'][] = array('triggerid' => $item['triggerid']);
				}
				// graphids
				if (isset($item['graphid']) && is_null($options['selectGraphs'])) {
					if (!isset($result[$item['itemid']]['graphs'])) {
						$result[$item['itemid']]['graphs'] = array();
					}
					$result[$item['itemid']]['graphs'][] = array('graphid' => $item['graphid']);
					unset($item['graphid']);
				}
				// applicationids
				if (isset($item['applicationid']) && is_null($options['selectApplications'])) {
					if (!isset($result[$item['itemid']]['applications'])) {
						$result[$item['itemid']]['applications'] = array();
					}
					$result[$item['itemid']]['applications'][] = array('applicationid' => $item['applicationid']);
					unset($item['applicationid']);
				}

				// populate relation map
				if (isset($item['hostid']) && $item['hostid']) {
					$relationMap->addRelation($item['itemid'], 'hosts', $item['hostid']);
				}
				if (isset($item['triggerid']) && $item['triggerid']) {
					$relationMap->addRelation($item['itemid'], 'triggers', $item['triggerid']);
				}
				unset($item['triggerid']);
				if (isset($item['interfaceid']) && $item['interfaceid']) {
					$relationMap->addRelation($item['itemid'], 'interfaces', $item['interfaceid']);
				}
				if (isset($item['graphid']) && $item['graphid']) {
					$relationMap->addRelation($item['itemid'], 'graphs', $item['graphid']);
				}
				unset($item['graphid']);
				if (isset($item['applicationid']) && $item['applicationid']) {
					$relationMap->addRelation($item['itemid'], 'applications', $item['applicationid']);
				}
				unset($item['applicationid']);
				if (isset($item['itemdiscoveryid']) && $item['itemdiscoveryid']) {
					$relationMap->addRelation($item['itemid'], 'itemDiscovery', $item['itemdiscoveryid']);
				}
				unset($item['itemdiscoveryid']);

				$result[$item['itemid']] += $item;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$hosts = API::Host()->get(array(
				'nodeids' => $options['nodeids'],
				'hostids' => $relationMap->getRelatedIds('hosts'),
				'templated_hosts' => true,
				'output' => $options['selectHosts'],
				'nopermissions' => true,
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding interfaces
		if ($options['selectInterfaces'] !== null && $options['selectInterfaces'] != API_OUTPUT_COUNT) {
			$interfaces = API::HostInterface()->get(array(
				'nodeids' => $options['nodeids'],
				'output' => $options['selectInterfaces'],
				'intefaceids' => $relationMap->getRelatedIds('interfaces'),
				'nopermissions' => true,
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $interfaces, 'interfaces');
		}

		// adding triggers
		if (!is_null($options['selectTriggers'])) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				$triggers = API::Trigger()->get(array(
					'output' => $options['selectTriggers'],
					'nodeids' => $options['nodeids'],
					'triggerids' => $relationMap->getRelatedIds('triggers'),
					'preservekeys' => true
				));

				if (!is_null($options['limitSelects'])) {
					order_result($triggers, 'description');
				}
				$result = $relationMap->mapMany($result, $triggers, 'triggers', $options['limitSelects']);
			}
			else {
				$triggers = API::Trigger()->get(array(
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
				$graphs = API::Graph()->get(array(
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
				$graphs = API::Graph()->get(array(
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

		// adding applications
		if ($options['selectApplications'] !== null && $options['selectApplications'] != API_OUTPUT_COUNT) {
			$applications = API::Application()->get(array(
				'output' => $options['selectApplications'],
				'nodeids' => $options['nodeids'],
				'applicationids' => $relationMap->getRelatedIds('applications'),
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $applications, 'applications');
		}

		// adding discoveryrule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			// discovered items
			$dbRules = DBselect(
				'SELECT id1.itemid,id2.parent_itemid'.
				' FROM item_discovery id1,item_discovery id2,items i'.
				' WHERE '.DBcondition('id1.itemid', $itemids).
					' AND id1.parent_itemid=id2.itemid'.
					' AND i.itemid=id1.itemid'.
					' AND i.flags='.ZBX_FLAG_DISCOVERY_CREATED
			);
			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['itemid'], 'discoveryRule', $rule['parent_itemid']);
			}

			// item prototypes
			// TODO: this should not be in the item API
			$dbRules = DBselect(
				'SELECT id.parent_itemid,id.itemid'.
				' FROM item_discovery id,items i'.
				' WHERE '.DBcondition('id.itemid', $itemids).
					' AND i.itemid=id.itemid'.
					' AND i.flags='.ZBX_FLAG_DISCOVERY_CHILD
			);
			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['itemid'], 'discoveryRule', $rule['parent_itemid']);
			}

			$discoveryRules = API::DiscoveryRule()->get(array(
				'output' => $options['selectDiscoveryRule'],
				'nodeids' => $options['nodeids'],
				'itemids' => $relationMap->getRelatedIds('discoveryRule'),
				'nopermissions' => true,
				'preservekeys' => true
			));
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// add other related objects
		if ($result) {
			$result = $this->addRelatedObjects($options, $result, $relationMap);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Get itemid by host.name and item.key.
	 *
	 * @param array $itemData
	 * @param array $itemData['key_']
	 * @param array $itemData['hostid']
	 *
	 * @return array
	 */
	public function getObjects($itemData) {
		$options = array(
			'filter' => $itemData,
			'output' => API_OUTPUT_EXTEND,
			'webitems' => true
		);

		if (isset($itemData['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($itemData['node']);
		}
		elseif (isset($itemData['nodeids'])) {
			$options['nodeids'] = $itemData['nodeids'];
		}

		$result = $this->get($options);

		return $result;
	}

	/**
	 * Check if item exists.
	 *
	 * @param array $object
	 *
	 * @return bool
	 */
	public function exists(array $object) {
		$options = array(
			'filter' => array('key_' => $object['key_']),
			'webitems' => true,
			'output' => array('itemid'),
			'nopermissions' => true,
			'limit' => 1
		);

		if (isset($object['hostid'])) {
			$options['hostids'] = $object['hostid'];
		}
		if (isset($object['host'])) {
			$options['filter']['host'] = $object['host'];
		}

		if (isset($object['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		}
		elseif (isset($object['nodeids'])) {
			$options['nodeids'] = $object['nodeids'];
		}

		$objs = $this->get($options);

		return !empty($objs);
	}

	/**
	 * Check item data and set flags field.
	 *
	 * @param array $items passed by reference
	 * @param bool  $update
	 *
	 * @return void
	 */
	protected function checkInput(array &$items, $update = false) {
		// add the values that cannot be changed, but are required for further processing
		foreach ($items as &$item) {
			$item['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
		}
		unset($item);

		// validate if everything is ok with 'item->inventory fields' linkage
		self::validateInventoryLinks($items, $update);
		parent::checkInput($items, $update);
	}

	/**
	 * Create item.
	 *
	 * @param $items
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

	/**
	 * Create host item.
	 *
	 * @param array $items
	 */
	protected function createReal(array &$items) {
		$itemids = DB::insert('items', $items);

		$itemApplications = array();
		foreach ($items as $key => $item) {
			$items[$key]['itemid'] = $itemids[$key];

			if (!isset($item['applications'])) {
				continue;
			}

			foreach ($item['applications'] as $appid) {
				if ($appid == 0) {
					continue;
				}

				$itemApplications[] = array(
					'applicationid' => $appid,
					'itemid' => $items[$key]['itemid']
				);
			}
		}

		if (!empty($itemApplications)) {
			DB::insert('items_applications', $itemApplications);
		}

		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('name'),
			'selectHosts' => array('name'),
			'nopermissions' => true
		));
		foreach ($itemHosts as $item) {
			$host = reset($item['hosts']);
			info(_s('Created: Item "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	/**
	 * Update host items.
	 *
	 * @param array $items
	 *
	 * @return void
	 */
	protected function updateReal(array $items) {
		$items = zbx_toArray($items);

		$itemids = array();
		$data = array();
		foreach ($items as $item) {
			$data[] = array('values' => $item, 'where' => array('itemid' => $item['itemid']));
			$itemids[] = $item['itemid'];
		}
		DB::update('items', $data);

		$itemApplications = array();
		$applicationids = array();
		foreach ($items as $item) {
			if (!isset($item['applications'])) {
				continue;
			}
			$applicationids[] = $item['itemid'];

			foreach ($item['applications'] as $appid) {
				$itemApplications[] = array(
					'applicationid' => $appid,
					'itemid' => $item['itemid']
				);
			}
		}

		if (!empty($applicationids)) {
			DB::delete('items_applications', array('itemid' => $applicationids));
			DB::insert('items_applications', $itemApplications);
		}

		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('name'),
			'selectHosts' => array('name'),
			'nopermissions' => true
		));
		foreach ($itemHosts as $item) {
			$host = reset($item['hosts']);
			info(_s('Updated: Item "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	/**
	 * Update item.
	 *
	 * @param array $items
	 *
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
	 * Delete items
	 *
	 * @param array $itemids
	 */
	public function delete($itemids, $nopermissions = false) {
		if (empty($itemids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$delItemIds = zbx_toArray($itemids);
		$itemids = zbx_toHash($itemids);

		$delItems = $this->get(array(
			'itemids' => $itemids,
			'editable' => true,
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => array('name')
		));

		// TODO: remove $nopermissions hack
		if (!$nopermissions) {
			foreach ($itemids as $itemid) {
				if (!isset($delItems[$itemid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
				}
				if ($delItems[$itemid]['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete templated item.'));
				}
			}
		}

		// first delete child items
		$parentItemids = $itemids;
		do {
			$dbItems = DBselect('SELECT i.itemid FROM items i WHERE '.DBcondition('i.templateid', $parentItemids));
			$parentItemids = array();
			while ($dbItem = DBfetch($dbItems)) {
				$parentItemids[] = $dbItem['itemid'];
				$itemids[$dbItem['itemid']] = $dbItem['itemid'];
			}
		} while (!empty($parentItemids));

		// delete graphs, leave if graph still have item
		$delGraphs = array();
		$dbGraphs = DBselect(
			'SELECT gi.graphid'.
			' FROM graphs_items gi'.
			' WHERE '.DBcondition('gi.itemid', $itemids).
				' AND NOT EXISTS ('.
					'SELECT gii.gitemid'.
					' FROM graphs_items gii'.
					' WHERE gii.graphid=gi.graphid'.
						' AND '.DBcondition('gii.itemid', $itemids, true, false).
				')'
		);
		while ($dbGraph = DBfetch($dbGraphs)) {
			$delGraphs[$dbGraph['graphid']] = $dbGraph['graphid'];
		}

		if (!empty($delGraphs)) {
			$result = API::Graph()->delete($delGraphs, true);
			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete graph.'));
			}
		}

		// check if any graphs are referencing this item
		$this->checkGraphReference($itemids);

		$triggers = API::Trigger()->get(array(
			'itemids' => $itemids,
			'output' => array('triggerid'),
			'nopermissions' => true,
			'preservekeys' => true
		));
		if (!empty($triggers)) {
			$result = API::Trigger()->delete(array_keys($triggers), true);
			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete trigger.'));
			}
		}

		DB::delete('screens_items', array(
			'resourceid' => $itemids,
			'resourcetype' => array(SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT)
		));
		DB::delete('items', array('itemid' => $itemids));
		DB::delete('profiles', array(
			'idx' => 'web.favorite.graphids',
			'source' => 'itemid',
			'value_id' => $itemids
		));

		$itemDataTables = array(
			'trends',
			'trends_uint',
			'history_text',
			'history_log',
			'history_uint',
			'history_str',
			'history'
		);
		$insert = array();
		foreach ($itemids as $itemid) {
			foreach ($itemDataTables as $table) {
				$insert[] = array(
					'tablename' => $table,
					'field' => 'itemid',
					'value' => $itemid
				);
			}
		}
		DB::insert('housekeeper', $insert);

		// TODO: remove info from API
		foreach ($delItems as $item) {
			$host = reset($item['hosts']);
			info(_s('Deleted: Item "%1$s" on "%2$s".', $item['name'], $host['name']));
		}

		return array('itemids' => $delItemIds);
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
			'output' => $selectFields,
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
		));

		foreach ($items as $inum => $item) {
			$items[$inum]['applications'] = zbx_objectValues($item['applications'], 'applicationid');
		}

		$this->inherit($items, $data['hostids']);

		return true;
	}

	protected function inherit(array $items, array $hostids = null) {
		if (empty($items)) {
			return true;
		}

		// prepare the child items
		$newItems = $this->prepareInheritedItems($items, $hostids);
		if (!$newItems) {
			return true;
		}

		$insertItems = array();
		$updateItems = array();
		foreach ($newItems as $newItem) {
			if (isset($newItem['itemid'])) {
				$updateItems[] = $newItem;
			}
			else {
				$newItem['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
				$insertItems[] = $newItem;
			}
		}

		// save the new items
		if (!zbx_empty($insertItems)) {
			self::validateInventoryLinks($insertItems, false); // false means 'create'
			$this->createReal($insertItems);
		}

		if (!zbx_empty($updateItems)) {
			self::validateInventoryLinks($updateItems, true); // true means 'update'
			$this->updateReal($updateItems);
		}

		// propagate the inheritance to the children
		return $this->inherit(array_merge($updateItems, $insertItems));
	}

	/**
	 * Check, if items that are about to be inserted or updated violate the rule:
	 * only one item can be linked to a inventory filed.
	 * If everything is ok, function return true or throws Exception otherwise
	 *
	 * @static
	 *
	 * @param array $items
	 * @param bool $update whether this is update operation
	 *
	 * @return bool
	 */
	public static function validateInventoryLinks(array $items, $update = false) {
		// inventory link field is not being updated, or being updated to 0, no need to validate anything then
		foreach ($items as $i => $item) {
			if (!isset($item['inventory_link']) || $item['inventory_link'] == 0) {
				unset($items[$i]);
			}
		}

		if (zbx_empty($items)) {
			return true;
		}

		$possibleHostInventories = getHostInventories();
		if ($update) {
			// for successful validation we need three fields for each item: inventory_link, hostid and key_
			// problem is, that when we are updating an item, we might not have them, because they are not changed
			// so, we need to find out what is missing and use API to get the lacking info
			$itemsWithNoHostId = array();
			$itemsWithNoInventoryLink = array();
			$itemsWithNoKeys = array();
			foreach ($items as $item) {
				if (!isset($item['inventory_link'])) {
					$itemsWithNoInventoryLink[$item['itemid']] = $item['itemid'];
				}
				if (!isset($item['hostid'])) {
					$itemsWithNoHostId[$item['itemid']] = $item['itemid'];
				}
				if (!isset($item['key_'])) {
					$itemsWithNoKeys[$item['itemid']] = $item['itemid'];
				}
			}
			$itemsToFind = array_merge($itemsWithNoHostId, $itemsWithNoInventoryLink, $itemsWithNoKeys);

			// are there any items with lacking info?
			if (!zbx_empty($itemsToFind)) {
				$missingInfo = API::Item()->get(array(
					'output' => array('hostid', 'inventory_link', 'key_'),
					'filter' => array('itemid' => $itemsToFind),
					'nopermissions' => true
				));
				$missingInfo = zbx_toHash($missingInfo, 'itemid');

				// appending host ids, inventory_links and keys where they are needed
				foreach ($items as $i => $item) {
					if (isset($missingInfo[$item['itemid']])) {
						if (!isset($items[$i]['hostid'])) {
							$items[$i]['hostid'] = $missingInfo[$item['itemid']]['hostid'];
						}
						if (!isset($items[$i]['inventory_link'])) {
							$items[$i]['inventory_link'] = $missingInfo[$item['itemid']]['inventory_link'];
						}
						if (!isset($items[$i]['key_'])) {
							$items[$i]['key_'] = $missingInfo[$item['itemid']]['key_'];
						}
					}
				}
			}
		}

		$hostids = zbx_objectValues($items, 'hostid');

		// getting all inventory links on every affected host
		$itemsOnHostsInfo = API::Item()->get(array(
			'output' => array('key_', 'inventory_link', 'hostid'),
			'filter' => array('hostid' => $hostids),
			'nopermissions' => true
		));

		// now, changing array to: 'hostid' => array('key_'=>'inventory_link')
		$linksOnHostsCurr = array();
		foreach ($itemsOnHostsInfo as $info) {
			// 0 means no link - we are not interested in those ones
			if ($info['inventory_link'] != 0) {
				if (!isset($linksOnHostsCurr[$info['hostid']])) {
					$linksOnHostsCurr[$info['hostid']] = array($info['key_'] => $info['inventory_link']);
				}
				else{
					$linksOnHostsCurr[$info['hostid']][$info['key_']] = $info['inventory_link'];
				}
			}
		}

		$linksOnHostsFuture = array();

		foreach ($items as $item) {
			// checking if inventory_link value is a valid number
			if ($update || $item['value_type'] != ITEM_VALUE_TYPE_LOG) {
				// does inventory field with provided number exists?
				if (!isset($possibleHostInventories[$item['inventory_link']])) {
					$maxVar = max(array_keys($possibleHostInventories));
					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_s('Item "%1$s" cannot populate a missing host inventory field number "%2$d". Choices are: from 0 (do not populate) to %3$d.', $item['name'], $item['inventory_link'], $maxVar)
					);
				}
			}

			if (!isset($linksOnHostsFuture[$item['hostid']])) {
				$linksOnHostsFuture[$item['hostid']] = array($item['key_'] => $item['inventory_link']);
			}
			else {
				$linksOnHostsFuture[$item['hostid']][$item['key_']] = $item['inventory_link'];
			}
		}

		foreach ($linksOnHostsFuture as $hostId => $linkFuture) {
			if (isset($linksOnHostsCurr[$hostId])) {
				$futureSituation = array_merge($linksOnHostsCurr[$hostId], $linksOnHostsFuture[$hostId]);
			}
			else {
				$futureSituation = $linksOnHostsFuture[$hostId];
			}
			$valuesCount = array_count_values($futureSituation);

			// if we have a duplicate inventory links after merging - we are in trouble
			if (max($valuesCount) > 1) {
				// what inventory field caused this conflict?
				$conflictedLink = array_keys($valuesCount, 2);
				$conflictedLink = reset($conflictedLink);

				// which of updated items populates this link?
				$beingSavedItemName = '';
				foreach ($items as $item) {
					if ($item['inventory_link'] == $conflictedLink) {
						if (isset($item['name'])) {
							$beingSavedItemName = $item['name'];
						}
						else {
							$thisItem = API::Item()->get(array(
								'output' => array('name'),
								'filter' => array('itemid' => $item['itemid']),
								'nopermissions' => true
							));
							$beingSavedItemName = $thisItem[0]['name'];
						}
						break;
					}
				}

				// name of the original item that already populates the field
				$originalItem = API::Item()->get(array(
					'output' => array('name'),
					'filter' => array(
						'hostid' => $hostId,
						'inventory_link' => $conflictedLink
					),
					'nopermissions' => true
				));
				$originalItemName = $originalItem[0]['name'];

				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s(
						'Two items ("%1$s" and "%2$s") cannot populate one host inventory field "%3$s", this would lead to a conflict.',
						$beingSavedItemName,
						$originalItemName,
						$possibleHostInventories[$conflictedLink]['title']
					)
				);
			}
		}

		return true;
	}

	public function addRelatedObjects(array $options, array $result, CRelationMap $relationMap) {
		// TODO: move selectItemHosts to CItemGeneral::addRelatedObjects();
		// TODO: move selectInterfaces to CItemGeneral::addRelatedObjects();
		// TODO: move selectTriggers to CItemGeneral::addRelatedObjects();
		// TODO: move selectGraphs to CItemGeneral::addRelatedObjects();
		// TODO: move selectApplications to CItemGeneral::addRelatedObjects();
		$result = parent::addRelatedObjects($options, $result, $relationMap);

		// adding item discovery
		if ($options['selectItemDiscovery']) {
			$itemDiscoveries = API::getApi()->select('item_discovery', array(
				'output' => $options['selectItemDiscovery'],
				'itemdiscoveryids' => $relationMap->getRelatedIds('itemDiscovery'),
				'preservekeys' => true
			));
			$result = $relationMap->mapOne($result, $itemDiscoveries, 'itemDiscovery');
		}

		return $result;
	}

	protected function applyQueryNodeOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		// only apply the node option if no specific ids are given
		if ($options['groupids'] === null
				&& $options['templateids'] === null
				&& $options['hostids'] === null
				&& $options['proxyids'] === null
				&& $options['itemids'] === null
				&& $options['interfaceids'] === null
				&& $options['graphids'] === null
				&& $options['triggerids'] === null
				&& $options['applicationids'] === null) {
			$sqlParts = parent::applyQueryNodeOptions($tableName, $tableAlias, $options, $sqlParts);
		}

		return $sqlParts;
	}
}
