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
 * Class containing methods for operations with hosts.
 */
class CHost extends CHostGeneral {

	protected $sortColumns = ['hostid', 'host', 'name', 'status'];

	/**
	 * Get host data.
	 *
	 * @param array      $options
	 * @param array      $options['groupids']                  HostGroup IDs
	 * @param array      $options['hostids']                   Host IDs
	 * @param bool       $options['monitored_hosts']           only monitored Hosts
	 * @param bool       $options['templated_hosts']           include templates in result
	 * @param bool       $options['with_items']                only with items
	 * @param bool       $options['with_monitored_items']      only with monitored items
	 * @param bool       $options['with_triggers']             only with triggers
	 * @param bool       $options['with_monitored_triggers']   only with monitored triggers
	 * @param bool       $options['with_httptests']            only with http tests
	 * @param bool       $options['with_monitored_httptests']  only with monitored http tests
	 * @param bool       $options['with_graphs']               only with graphs
	 * @param bool       $options['editable']                  only with read-write permission. Ignored for SuperAdmins
	 * @param bool       $options['selectGroups']              select HostGroups
	 * @param bool       $options['selectItems']               select Items
	 * @param bool       $options['selectTriggers']            select Triggers
	 * @param bool       $options['selectGraphs']              select Graphs
	 * @param bool       $options['selectApplications']        select Applications
	 * @param bool       $options['selectMacros']              select Macros
	 * @param bool|array $options['selectInventory']           select Inventory
	 * @param bool       $options['withInventory']             select only hosts with inventory
	 * @param int        $options['count']                     count Hosts, returned column name is rowscount
	 * @param string     $options['pattern']                   search hosts by pattern in Host name
	 * @param string     $options['extendPattern']             search hosts by pattern in Host name, ip and DNS
	 * @param int        $options['limit']                     limit selection
	 * @param string     $options['sortfield']                 field to sort by
	 * @param string     $options['sortorder']                 sort order
	 *
	 * @return array|boolean Host data as array or false if error
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['hosts' => 'h.hostid'],
			'from'		=> ['hosts' => 'hosts h'],
			'where'		=> ['flags' => 'h.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'hostids'					=> null,
			'proxyids'					=> null,
			'templateids'				=> null,
			'interfaceids'				=> null,
			'itemids'					=> null,
			'triggerids'				=> null,
			'maintenanceids'			=> null,
			'graphids'					=> null,
			'applicationids'			=> null,
			'dserviceids'				=> null,
			'httptestids'				=> null,
			'monitored_hosts'			=> null,
			'templated_hosts'			=> null,
			'proxy_hosts'				=> null,
			'with_items'				=> null,
			'with_monitored_items'		=> null,
			'with_simple_graph_items'	=> null,
			'with_triggers'				=> null,
			'with_monitored_triggers'	=> null,
			'with_httptests'			=> null,
			'with_monitored_httptests'	=> null,
			'with_graphs'				=> null,
			'with_applications'			=> null,
			'withInventory'				=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchInventory'			=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> false,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectGroups'				=> null,
			'selectParentTemplates'		=> null,
			'selectItems'				=> null,
			'selectDiscoveries'			=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'selectApplications'		=> null,
			'selectMacros'				=> null,
			'selectScreens'				=> null,
			'selectInterfaces'			=> null,
			'selectInventory'			=> null,
			'selectHttpTests'           => null,
			'selectDiscoveryRule'		=> null,
			'selectHostDiscovery'		=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
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
					' WHERE h.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.zbx_dbstr($permission).
					')';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);
			$sqlParts['where']['hostid'] = dbConditionInt('h.hostid', $options['hostids']);
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where']['hgh'] = 'hg.hostid=h.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['groupid'] = 'hg.groupid';
			}
		}

		// proxyids
		if (!is_null($options['proxyids'])) {
			zbx_value2array($options['proxyids']);

			$sqlParts['where'][] = dbConditionId('h.proxy_hostid', $options['proxyids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			$sqlParts['from']['hosts_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.templateid', $options['templateids']);
			$sqlParts['where']['hht'] = 'h.hostid=ht.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['templateid'] = 'ht.templateid';
			}
		}

		// interfaceids
		if (!is_null($options['interfaceids'])) {
			zbx_value2array($options['interfaceids']);

			$sqlParts['from']['interface'] = 'interface hi';
			$sqlParts['where'][] = dbConditionInt('hi.interfaceid', $options['interfaceids']);
			$sqlParts['where']['hi'] = 'h.hostid=hi.hostid';
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('i.itemid', $options['itemids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
		}

		// httptestids
		if (!is_null($options['httptestids'])) {
			zbx_value2array($options['httptestids']);

			$sqlParts['from']['httptest'] = 'httptest ht';
			$sqlParts['where'][] = dbConditionInt('ht.httptestid', $options['httptestids']);
			$sqlParts['where']['aht'] = 'ht.hostid=h.hostid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['where'][] = dbConditionInt('a.applicationid', $options['applicationids']);
			$sqlParts['where']['ah'] = 'a.hostid=h.hostid';
		}

		// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);

			$sqlParts['from']['dservices'] = 'dservices ds';
			$sqlParts['from']['interface'] = 'interface i';
			$sqlParts['where'][] = dbConditionInt('ds.dserviceid', $options['dserviceids']);
			$sqlParts['where']['dsh'] = 'ds.ip=i.ip';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['dserviceid'] = 'ds.dserviceid';
			}
		}

		// maintenanceids
		if (!is_null($options['maintenanceids'])) {
			zbx_value2array($options['maintenanceids']);

			$sqlParts['from']['maintenances_hosts'] = 'maintenances_hosts mh';
			$sqlParts['where'][] = dbConditionInt('mh.maintenanceid', $options['maintenanceids']);
			$sqlParts['where']['hmh'] = 'h.hostid=mh.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['maintenanceid'] = 'mh.maintenanceid';
			}
		}

		// monitored_hosts, templated_hosts
		if (!is_null($options['monitored_hosts'])) {
			$sqlParts['where']['status'] = 'h.status='.HOST_STATUS_MONITORED;
		}
		elseif (!is_null($options['templated_hosts'])) {
			$sqlParts['where']['status'] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')';
		}
		elseif (!is_null($options['proxy_hosts'])) {
			$sqlParts['where']['status'] = 'h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')';
		}
		else {
			$sqlParts['where']['status'] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		}

		// with_items, with_monitored_items, with_simple_graph_items
		if (!is_null($options['with_items'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i'.
					' WHERE h.hostid=i.hostid'.
						' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}
		elseif (!is_null($options['with_monitored_items'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i'.
					' WHERE h.hostid=i.hostid'.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}
		elseif (!is_null($options['with_simple_graph_items'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i'.
					' WHERE h.hostid=i.hostid'.
						' AND i.value_type IN ('.ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64.')'.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}

		// with_triggers, with_monitored_triggers
		if (!is_null($options['with_triggers'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,functions f,triggers t'.
					' WHERE h.hostid=i.hostid'.
						' AND i.itemid=f.itemid'.
						' AND f.triggerid=t.triggerid'.
						' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}
		elseif (!is_null($options['with_monitored_triggers'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,functions f,triggers t'.
					' WHERE h.hostid=i.hostid'.
						' AND i.itemid=f.itemid'.
						' AND f.triggerid=t.triggerid'.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND t.status='.TRIGGER_STATUS_ENABLED.
						' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}

		// with_httptests, with_monitored_httptests
		if (!empty($options['with_httptests'])) {
			$sqlParts['where'][] = 'EXISTS (SELECT NULL FROM httptest ht WHERE ht.hostid=h.hostid)';
		}
		elseif (!empty($options['with_monitored_httptests'])) {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM httptest ht'.
				' WHERE h.hostid=ht.hostid'.
					' AND ht.status='.HTTPTEST_STATUS_ACTIVE.
				')';
		}

		// with_graphs
		if (!is_null($options['with_graphs'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,graphs_items gi,graphs g'.
					' WHERE i.hostid=h.hostid'.
						' AND i.itemid=gi.itemid '.
						' AND gi.graphid=g.graphid'.
						' AND g.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}

		// with applications
		if (!is_null($options['with_applications'])) {
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['where'][] = 'a.hostid=h.hostid';
		}

		// withInventory
		if (!is_null($options['withInventory']) && $options['withInventory']) {
			$sqlParts['where'][] = ' h.hostid IN ('.
					' SELECT hin.hostid'.
					' FROM host_inventory hin'.
					')';
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('hosts h', $options, $sqlParts);

			if (zbx_db_search('interface hi', $options, $sqlParts)) {
				$sqlParts['from']['interface'] = 'interface hi';
				$sqlParts['where']['hi'] = 'h.hostid=hi.hostid';
			}
		}

		// search inventory
		if ($options['searchInventory'] !== null) {
			$sqlParts['from']['host_inventory'] = 'host_inventory hii';
			$sqlParts['where']['hii'] = 'h.hostid=hii.hostid';

			zbx_db_search('host_inventory hii',
				[
					'search' => $options['searchInventory'],
					'startSearch' => $options['startSearch'],
					'excludeSearch' => $options['excludeSearch'],
					'searchWildcardsEnabled' => $options['searchWildcardsEnabled'],
					'searchByAny' => $options['searchByAny']
				],
				$sqlParts
			);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('hosts h', $options, $sqlParts);

			if ($this->dbFilter('interface hi', $options, $sqlParts)) {
				$sqlParts['from']['interface'] = 'interface hi';
				$sqlParts['where']['hi'] = 'h.hostid=hi.hostid';
			}
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($host = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $host;
				}
				else {
					$result = $host['rowscount'];
				}
			}
			else {
				$result[$host['hostid']] = $host;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Add host.
	 *
	 * @param array  $hosts									An array with hosts data.
	 * @param string $hosts[]['host']						Host technical name.
	 * @param string $hosts[]['name']						Host visible name (optional).
	 * @param array  $hosts[]['groups']						An array of host group objects with IDs that host will be added to.
	 * @param int    $hosts[]['status']						Status of the host (optional).
	 * @param array  $hosts[]['interfaces']					An array of host interfaces data.
	 * @param int    $hosts[]['interfaces']['type']			Interface type.
	 * @param int    $hosts[]['interfaces']['main']			Is this the default interface to use.
	 * @param string $hosts[]['interfaces']['ip']			Interface IP (optional).
	 * @param int    $hosts[]['interfaces']['port']			Interface port (optional).
	 * @param int    $hosts[]['interfaces']['useip']		Interface shoud use IP (optional).
	 * @param string $hosts[]['interfaces']['dns']			Interface shoud use DNS (optional).
	 * @param int    $hosts[]['interfaces']['bulk']			Use bulk requests for interface (optional).
	 * @param int    $hosts[]['proxy_hostid']				ID of the proxy that is used to monitor the host (optional).
	 * @param int    $hosts[]['ipmi_authtype']				IPMI authentication type (optional).
	 * @param int    $hosts[]['ipmi_privilege']				IPMI privilege (optional).
	 * @param string $hosts[]['ipmi_username']				IPMI username (optional).
	 * @param string $hosts[]['ipmi_password']				IPMI password (optional).
	 * @param array  $hosts[]['inventory']					An array of host inventory data (optional).
	 * @param array  $hosts[]['macros']						An array of host macros (optional).
	 * @param string $hosts[]['macros'][]['macro']			Host macro (required if "macros" is set).
	 * @param array  $hosts[]['templates']					An array of template objects with IDs that will be linked to host (optional).
	 * @param string $hosts[]['templates'][]['templateid']	Template ID (required if "templates" is set).
	 * @param string $hosts[]['tls_connect']				Connections to host (optional).
	 * @param string $hosts[]['tls_accept']					Connections from host (optional).
	 * @param string $hosts[]['tls_psk_identity']			PSK identity (required if "PSK" type is set).
	 * @param string $hosts[]['tls_psk']					PSK (required if "PSK" type is set).
	 * @param string $hosts[]['tls_issuer']					Certificate issuer (optional).
	 * @param string $hosts[]['tls_subject']				Certificate subject (optional).
	 *
	 * @return array
	 */
	public function create($hosts) {
		$hosts = zbx_toArray($hosts);

		$this->validateCreate($hosts);

		$hostids = [];

		foreach ($hosts as $host) {
			// If visible name is not given or empty it should be set to host name.
			if (!array_key_exists('name', $host) || !trim($host['name'])) {
				$host['name'] = $host['host'];
			}

			$hostid = DB::insert('hosts', [$host]);
			$hostid = reset($hostid);
			$host['hostid'] = $hostid;
			$hostids[] = $hostid;

			// Save groups. Groups must be added before calling massAdd() for permission validation to work.
			$groupsToAdd = [];
			foreach ($host['groups'] as $group) {
				$groupsToAdd[] = [
					'hostid' => $hostid,
					'groupid' => $group['groupid']
				];
			}
			DB::insert('hosts_groups', $groupsToAdd);

			$options = [
				'hosts' => $host
			];

			if (isset($host['templates']) && !is_null($host['templates'])) {
				$options['templates'] = $host['templates'];
			}

			if (isset($host['macros']) && !is_null($host['macros'])) {
				$options['macros'] = $host['macros'];
			}

			if (isset($host['interfaces']) && !is_null($host['interfaces'])) {
				$options['interfaces'] = $host['interfaces'];
			}

			$result = API::Host()->massAdd($options);
			if (!$result) {
				self::exception();
			}

			if (array_key_exists('inventory', $host) && $host['inventory']) {
				$hostInventory = $host['inventory'];
				$hostInventory['hostid'] = $hostid;
				$hostInventory['inventory_mode'] = HOST_INVENTORY_MANUAL;
			}
			else {
				$hostInventory = [];
			}

			if (array_key_exists('inventory_mode', $host) && $host['inventory_mode'] != HOST_INVENTORY_DISABLED) {
				$hostInventory['hostid'] = $hostid;
				$hostInventory['inventory_mode'] = $host['inventory_mode'];
			}

			if ($hostInventory) {
				DB::insert('host_inventory', [$hostInventory], false);
			}
		}

		return ['hostids' => $hostids];
	}

	/**
	 * Update host.
	 *
	 * @param array  $hosts											An array with hosts data.
	 * @param string $hosts[]['hostid']								Host ID.
	 * @param string $hosts[]['host']								Host technical name (optional).
	 * @param string $hosts[]['name']								Host visible name (optional).
	 * @param array  $hosts[]['groups']								An array of host group objects with IDs that host will be replaced to.
	 * @param int    $hosts[]['status']								Status of the host (optional).
	 * @param array  $hosts[]['interfaces']							An array of host interfaces data to be replaced.
	 * @param int    $hosts[]['interfaces']['type']					Interface type.
	 * @param int    $hosts[]['interfaces']['main']					Is this the default interface to use.
	 * @param string $hosts[]['interfaces']['ip']					Interface IP (optional).
	 * @param int    $hosts[]['interfaces']['port']					Interface port (optional).
	 * @param int    $hosts[]['interfaces']['useip']				Interface shoud use IP (optional).
	 * @param string $hosts[]['interfaces']['dns']					Interface shoud use DNS (optional).
	 * @param int    $hosts[]['interfaces']['bulk']					Use bulk requests for interface (optional).
	 * @param int    $hosts[]['proxy_hostid']						ID of the proxy that is used to monitor the host (optional).
	 * @param int    $hosts[]['ipmi_authtype']						IPMI authentication type (optional).
	 * @param int    $hosts[]['ipmi_privilege']						IPMI privilege (optional).
	 * @param string $hosts[]['ipmi_username']						IPMI username (optional).
	 * @param string $hosts[]['ipmi_password']						IPMI password (optional).
	 * @param array  $hosts[]['inventory']							An array of host inventory data (optional).
	 * @param array  $hosts[]['macros']								An array of host macros (optional).
	 * @param string $hosts[]['macros'][]['macro']					Host macro (required if "macros" is set).
	 * @param array  $hosts[]['templates']							An array of template objects with IDs that will be linked to host (optional).
	 * @param string $hosts[]['templates'][]['templateid']			Template ID (required if "templates" is set).
	 * @param array  $hosts[]['templates_clear']					Templates to unlink and clear from the host (optional).
	 * @param string $hosts[]['templates_clear'][]['templateid']	Template ID (required if "templates" is set).
	 * @param string $hosts[]['tls_connect']						Connections to host (optional).
	 * @param string $hosts[]['tls_accept']							Connections from host (optional).
	 * @param string $hosts[]['tls_psk_identity']					PSK identity (required if "PSK" type is set).
	 * @param string $hosts[]['tls_psk']							PSK (required if "PSK" type is set).
	 * @param string $hosts[]['tls_issuer']							Certificate issuer (optional).
	 * @param string $hosts[]['tls_subject']						Certificate subject (optional).
	 *
	 * @return array
	 */
	public function update($hosts) {
		$hosts = zbx_toArray($hosts);
		$hostids = zbx_objectValues($hosts, 'hostid');

		$db_hosts = $this->get([
			'output' => ['hostid', 'host', 'flags', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject',
				'tls_psk_identity', 'tls_psk'
			],
			'hostids' => $hostids,
			'editable' => true,
			'preservekeys' => true
		]);

		$hosts = $this->validateUpdate($hosts, $db_hosts);

		$inventories = [];
		foreach ($hosts as &$host) {
			// If visible name is not given or empty it should be set to host name.
			if (array_key_exists('host', $host) && (!array_key_exists('name', $host) || !trim($host['name']))) {
				$host['name'] = $host['host'];
			}

			// Fetch fields required to update host inventory.
			if (array_key_exists('inventory', $host)) {
				$inventory = $host['inventory'];
				$inventory['hostid'] = $host['hostid'];

				$inventories[] = $inventory;
			}
		}
		unset($host);

		$inventories = $this->extendObjects('host_inventory', $inventories, ['inventory_mode']);
		$inventories = zbx_toHash($inventories, 'hostid');

		$macros = [];
		foreach ($hosts as &$host) {
			if (isset($host['macros'])) {
				$macros[$host['hostid']] = $host['macros'];

				unset($host['macros']);
			}
		}
		unset($host);

		if ($macros) {
			API::UserMacro()->replaceMacros($macros);
		}

		$hosts = $this->extendObjectsByKey($hosts, $db_hosts, 'hostid', ['tls_connect', 'tls_accept', 'tls_issuer',
			'tls_subject', 'tls_psk_identity', 'tls_psk'
		]);

		foreach ($hosts as $host) {
			// extend host inventory with the required data
			if (isset($host['inventory']) && $host['inventory']) {
				$inventory = $inventories[$host['hostid']];

				// if no host inventory record exists in the DB, it's disabled
				if (!isset($inventory['inventory_mode'])) {
					$inventory['inventory_mode'] = HOST_INVENTORY_DISABLED;
				}

				$host['inventory'] = $inventory;
			}

			$data = $host;
			$data['hosts'] = ['hostid' => $host['hostid']];
			$result = $this->massUpdate($data);

			if (!$result) {
				self::exception(ZBX_API_ERROR_INTERNAL, _('Host update failed.'));
			}
		}

		return ['hostids' => $hostids];
	}

	/**
	 * Additionally allows to create new interfaces on hosts.
	 *
	 * Checks write permissions for hosts.
	 *
	 * Additional supported $data parameters are:
	 * - interfaces - an array of interfaces to create on the hosts
	 * - templates  - an array of templates to link to the hosts, overrides the CHostGeneral::massAdd()
	 *                'templates' parameter
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massAdd(array $data) {
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : [];
		$hostIds = zbx_objectValues($hosts, 'hostid');

		$this->checkPermissions($hostIds, _('You do not have permission to perform this operation.'));

		// add new interfaces
		if (!empty($data['interfaces'])) {
			API::HostInterface()->massAdd([
				'hosts' => $data['hosts'],
				'interfaces' => zbx_toArray($data['interfaces'])
			]);
		}

		// rename the "templates" parameter to the common "templates_link"
		if (isset($data['templates'])) {
			$data['templates_link'] = $data['templates'];
			unset($data['templates']);
		}

		$data['templates'] = [];

		return parent::massAdd($data);
	}

	/**
	 * Mass update hosts.
	 *
	 * @param array  $hosts								multidimensional array with Hosts data
	 * @param array  $hosts['hosts']					Array of Host objects to update
	 * @param string $hosts['fields']['host']			Host name.
	 * @param array  $hosts['fields']['groupids']		HostGroup IDs add Host to.
	 * @param int    $hosts['fields']['port']			Port. OPTIONAL
	 * @param int    $hosts['fields']['status']			Host Status. OPTIONAL
	 * @param int    $hosts['fields']['useip']			Use IP. OPTIONAL
	 * @param string $hosts['fields']['dns']			DNS. OPTIONAL
	 * @param string $hosts['fields']['ip']				IP. OPTIONAL
	 * @param int    $hosts['fields']['bulk']			bulk. OPTIONAL
	 * @param int    $hosts['fields']['proxy_hostid']	Proxy Host ID. OPTIONAL
	 * @param int    $hosts['fields']['ipmi_authtype']	IPMI authentication type. OPTIONAL
	 * @param int    $hosts['fields']['ipmi_privilege']	IPMI privilege. OPTIONAL
	 * @param string $hosts['fields']['ipmi_username']	IPMI username. OPTIONAL
	 * @param string $hosts['fields']['ipmi_password']	IPMI password. OPTIONAL
	 *
	 * @return boolean
	 */
	public function massUpdate($data) {
		if (!array_key_exists('hosts', $data) || !is_array($data['hosts'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Field "%1$s" is mandatory.', 'hosts'));
		}

		$hosts = zbx_toArray($data['hosts']);
		$inputHostIds = zbx_objectValues($hosts, 'hostid');
		$hostids = array_unique($inputHostIds);

		sort($hostids);

		$db_hosts = $this->get([
			'output' => ['hostid', 'host'],
			'hostids' => $hostids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($hosts as $host) {
			if (!array_key_exists($host['hostid'], $db_hosts)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		// Check connection fields only for massupdate action.
		if (array_key_exists('tls_connect', $data) || array_key_exists('tls_accept', $data)
				|| array_key_exists('tls_psk_identity', $data) || array_key_exists('tls_psk', $data)
				|| array_key_exists('tls_issuer', $data) || array_key_exists('tls_subject', $data)) {
			if (!array_key_exists('tls_connect', $data) || !array_key_exists('tls_accept', $data)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _(
					'Cannot update host encryption settings. Connection settings for both directions should be specified.'
				));
			}

			// Clean PSK fields.
			if ($data['tls_connect'] != HOST_ENCRYPTION_PSK && !($data['tls_accept'] & HOST_ENCRYPTION_PSK)) {
				$data['tls_psk_identity'] = '';
				$data['tls_psk'] = '';
			}

			// Clean certificate fields.
			if ($data['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE
					&& !($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)) {
				$data['tls_issuer'] = '';
				$data['tls_subject'] = '';
			}
		}

		$this->validateEncryption([$data]);

		if (array_key_exists('groups', $data) && !$data['groups'] && $db_hosts) {
			$host = reset($db_hosts);

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Host "%1$s" cannot be without host group.', $host['host'])
			);
		}

		// Property 'compress' is not supported for hosts.
		if (array_key_exists('compress', $data)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
		}

		/*
		 * Update hosts properties
		 */
		if (isset($data['name'])) {
			if (count($hosts) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update visible host name.'));
			}
		}

		if (isset($data['host'])) {
			if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $data['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect characters used for host name "%s".', $data['host']));
			}

			if (count($hosts) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update host name.'));
			}

			$curHost = reset($hosts);

			$sameHostnameHost = $this->get([
				'output' => ['hostid'],
				'filter' => ['host' => $data['host']],
				'nopermissions' => true,
				'limit' => 1
			]);
			$sameHostnameHost = reset($sameHostnameHost);
			if ($sameHostnameHost && (bccomp($sameHostnameHost['hostid'], $curHost['hostid']) != 0)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host "%1$s" already exists.', $data['host']));
			}

			// can't add host with the same name as existing template
			$sameHostnameTemplate = API::Template()->get([
				'output' => ['templateid'],
				'filter' => ['host' => $data['host']],
				'nopermissions' => true,
				'limit' => 1
			]);
			if ($sameHostnameTemplate) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Template "%1$s" already exists.', $data['host']));
			}
		}

		if (isset($data['groups'])) {
			$updateGroups = $data['groups'];
		}

		if (isset($data['interfaces'])) {
			$updateInterfaces = $data['interfaces'];
		}

		if (array_key_exists('templates_clear', $data)) {
			$updateTemplatesClear = zbx_toArray($data['templates_clear']);
		}

		if (isset($data['templates'])) {
			$updateTemplates = $data['templates'];
		}

		if (isset($data['macros'])) {
			$updateMacros = $data['macros'];
		}

		// second check is necessary, because import incorrectly inputs unset 'inventory' as empty string rather than null
		if (isset($data['inventory']) && $data['inventory']) {
			if (isset($data['inventory_mode']) && $data['inventory_mode'] == HOST_INVENTORY_DISABLED) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot set inventory fields for disabled inventory.'));
			}

			$updateInventory = $data['inventory'];
			$updateInventory['inventory_mode'] = null;
		}

		if (isset($data['inventory_mode'])) {
			if (!isset($updateInventory)) {
				$updateInventory = [];
			}
			$updateInventory['inventory_mode'] = $data['inventory_mode'];
		}

		if (isset($data['status'])) {
			$updateStatus = $data['status'];
		}

		unset($data['hosts'], $data['groups'], $data['interfaces'], $data['templates_clear'], $data['templates'],
			$data['macros'], $data['inventory'], $data['inventory_mode'], $data['status']);

		if (!zbx_empty($data)) {
			DB::update('hosts', [
				'values' => $data,
				'where' => ['hostid' => $hostids]
			]);
		}

		if (isset($updateStatus)) {
			updateHostStatus($hostids, $updateStatus);
		}

		/*
		 * Update template linkage
		 */
		if (isset($updateTemplatesClear)) {
			$templateIdsClear = zbx_objectValues($updateTemplatesClear, 'templateid');

			if ($updateTemplatesClear) {
				$this->massRemove(['hostids' => $hostids, 'templateids_clear' => $templateIdsClear]);
			}
		}
		else {
			$templateIdsClear = [];
		}

		// unlink templates
		if (isset($updateTemplates)) {
			$hostTemplates = API::Template()->get([
				'hostids' => $hostids,
				'output' => ['templateid'],
				'preservekeys' => true
			]);

			$hostTemplateids = array_keys($hostTemplates);
			$newTemplateids = zbx_objectValues($updateTemplates, 'templateid');

			$templatesToDel = array_diff($hostTemplateids, $newTemplateids);
			$templatesToDel = array_diff($templatesToDel, $templateIdsClear);

			if ($templatesToDel) {
				$result = $this->massRemove([
					'hostids' => $hostids,
					'templateids' => $templatesToDel
				]);
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot unlink template'));
				}
			}
		}

		/*
		 * update interfaces
		 */
		if (isset($updateInterfaces)) {
			foreach($hostids as $hostid) {
				API::HostInterface()->replaceHostInterfaces([
					'hostid' => $hostid,
					'interfaces' => $updateInterfaces
				]);
			}
		}

		// link new templates
		if (isset($updateTemplates)) {
			$result = $this->massAdd([
				'hosts' => $hosts,
				'templates' => $updateTemplates
			]);

			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot link template'));
			}
		}

		// macros
		if (isset($updateMacros)) {
			DB::delete('hostmacro', ['hostid' => $hostids]);

			$this->massAdd([
				'hosts' => $hosts,
				'macros' => $updateMacros
			]);
		}

		/*
		 * Inventory
		 */
		if (isset($updateInventory)) {
			// disabling inventory
			if ($updateInventory['inventory_mode'] == HOST_INVENTORY_DISABLED) {
				$sql = 'DELETE FROM host_inventory WHERE '.dbConditionInt('hostid', $hostids);
				if (!DBexecute($sql)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete inventory.'));
				}
			}
			// changing inventory mode or setting inventory fields
			else {
				$existingInventoriesDb = DBfetchArrayAssoc(DBselect(
					'SELECT hostid,inventory_mode'.
					' FROM host_inventory'.
					' WHERE '.dbConditionInt('hostid', $hostids)
				), 'hostid');

				// check existing host inventory data
				$automaticHostIds = [];
				if ($updateInventory['inventory_mode'] === null) {
					foreach ($hostids as $hostid) {
						// if inventory is disabled for one of the updated hosts, throw an exception
						if (!isset($existingInventoriesDb[$hostid])) {
							$host = get_host_by_hostid($hostid);
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Inventory disabled for host "%1$s".', $host['host']
							));
						}
						// if inventory mode is set to automatic, save its ID for later usage
						elseif ($existingInventoriesDb[$hostid]['inventory_mode'] == HOST_INVENTORY_AUTOMATIC) {
							$automaticHostIds[] = $hostid;
						}
					}
				}

				$inventoriesToSave = [];
				foreach ($hostids as $hostid) {
					$hostInventory = $updateInventory;
					$hostInventory['hostid'] = $hostid;

					// if no 'inventory_mode' has been passed, set inventory 'inventory_mode' from DB
					if ($updateInventory['inventory_mode'] === null) {
						$hostInventory['inventory_mode'] = $existingInventoriesDb[$hostid]['inventory_mode'];
					}

					$inventoriesToSave[$hostid] = $hostInventory;
				}

				// when updating automatic inventory, ignore fields that have items linked to them
				if ($updateInventory['inventory_mode'] == HOST_INVENTORY_AUTOMATIC
						|| ($updateInventory['inventory_mode'] === null && $automaticHostIds)) {

					$itemsToInventories = API::item()->get([
						'output' => ['inventory_link', 'hostid'],
						'hostids' => $automaticHostIds ? $automaticHostIds : $hostids,
						'nopermissions' => true
					]);

					$inventoryFields = getHostInventories();
					foreach ($itemsToInventories as $hinv) {
						// 0 means 'no link'
						if ($hinv['inventory_link'] != 0) {
							$inventoryName = $inventoryFields[$hinv['inventory_link']]['db_field'];
							unset($inventoriesToSave[$hinv['hostid']][$inventoryName]);
						}
					}
				}

				// save inventory data
				foreach ($inventoriesToSave as $inventory) {
					$hostid = $inventory['hostid'];
					if (isset($existingInventoriesDb[$hostid])) {
						DB::update('host_inventory', [
							'values' => $inventory,
							'where' => ['hostid' => $hostid]
						]);
					}
					else {
						DB::insert('host_inventory', [$inventory], false);
					}
				}
			}
		}

		/*
		 * Update host and host group linkage. This procedure should be done the last because user can unlink
		 * him self from a group with write permissions leaving only read premissions. Thus other procedures, like
		 * host-template linkage, inventory update, macros update, must be done before this.
		 */
		if (isset($updateGroups)) {
			$updateGroups = zbx_toArray($updateGroups);

			$hostGroups = API::HostGroup()->get([
				'output' => ['groupid'],
				'hostids' => $hostids
			]);
			$hostGroupIds = zbx_objectValues($hostGroups, 'groupid');
			$newGroupIds = zbx_objectValues($updateGroups, 'groupid');

			$groupsToAdd = array_diff($newGroupIds, $hostGroupIds);
			if ($groupsToAdd) {
				$this->massAdd([
					'hosts' => $hosts,
					'groups' => zbx_toObject($groupsToAdd, 'groupid')
				]);
			}

			$groupIdsToDelete = array_diff($hostGroupIds, $newGroupIds);
			if ($groupIdsToDelete) {
				$this->massRemove([
					'hostids' => $hostids,
					'groupids' => $groupIdsToDelete
				]);
			}
		}

		return ['hostids' => $inputHostIds];
	}

	/**
	 * Additionally allows to remove interfaces from hosts.
	 *
	 * Checks write permissions for hosts.
	 *
	 * Additional supported $data parameters are:
	 * - interfaces  - an array of interfaces to delete from the hosts
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data) {
		$hostids = zbx_toArray($data['hostids']);

		$this->checkPermissions($hostids, _('No permissions to referred object or it does not exist!'));

		if (isset($data['interfaces'])) {
			$options = [
				'hostids' => $hostids,
				'interfaces' => zbx_toArray($data['interfaces'])
			];
			API::HostInterface()->massRemove($options);
		}

		// rename the "templates" parameter to the common "templates_link"
		if (isset($data['templateids'])) {
			$data['templateids_link'] = $data['templateids'];
			unset($data['templateids']);
		}

		$data['templateids'] = [];

		return parent::massRemove($data);
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $hostIds
	 * @param bool 	$nopermissions
	 */
	protected function validateDelete(array $hostIds, $nopermissions = false) {
		if (!$hostIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		if (!$nopermissions) {
			$this->checkPermissions($hostIds, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Delete Host.
	 *
	 * @param array	$hostIds
	 * @param bool	$nopermissions
	 *
	 * @return array
	 */
	public function delete(array $hostIds, $nopermissions = false) {
		$this->validateDelete($hostIds, $nopermissions);

		// delete the discovery rules first
		$delRules = API::DiscoveryRule()->get([
			'output' => ['itemid'],
			'hostids' => $hostIds,
			'nopermissions' => true,
			'preservekeys' => true
		]);
		if ($delRules) {
			API::DiscoveryRule()->delete(array_keys($delRules), true);
		}

		// delete the items
		$delItems = API::Item()->get([
			'templateids' => $hostIds,
			'output' => ['itemid'],
			'nopermissions' => true,
			'preservekeys' => true
		]);
		if ($delItems) {
			API::Item()->delete(array_keys($delItems), true);
		}

		// delete web tests
		$delHttptests = [];
		$dbHttptests = get_httptests_by_hostid($hostIds);
		while ($dbHttptest = DBfetch($dbHttptests)) {
			$delHttptests[$dbHttptest['httptestid']] = $dbHttptest['httptestid'];
		}
		if (!empty($delHttptests)) {
			API::HttpTest()->delete($delHttptests, true);
		}


		// delete screen items
		DB::delete('screens_items', [
			'resourceid' => $hostIds,
			'resourcetype' => SCREEN_RESOURCE_HOST_TRIGGERS
		]);

		// delete host from maps
		if (!empty($hostIds)) {
			DB::delete('sysmaps_elements', [
				'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
				'elementid' => $hostIds
			]);
		}

		// disable actions
		// actions from conditions
		$actionids = [];
		$sql = 'SELECT DISTINCT actionid'.
				' FROM conditions'.
				' WHERE conditiontype='.CONDITION_TYPE_HOST.
				' AND '.dbConditionString('value', $hostIds);
		$dbActions = DBselect($sql);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		// actions from operations
		$sql = 'SELECT DISTINCT o.actionid'.
				' FROM operations o, opcommand_hst oh'.
				' WHERE o.operationid=oh.operationid'.
				' AND '.dbConditionInt('oh.hostid', $hostIds);
		$dbActions = DBselect($sql);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		if (!empty($actionids)) {
			$update = [];
			$update[] = [
				'values' => ['status' => ACTION_STATUS_DISABLED],
				'where' => ['actionid' => $actionids]
			];
			DB::update('actions', $update);
		}

		// delete action conditions
		DB::delete('conditions', [
			'conditiontype' => CONDITION_TYPE_HOST,
			'value' => $hostIds
		]);

		// delete action operation commands
		$operationids = [];
		$sql = 'SELECT DISTINCT oh.operationid'.
				' FROM opcommand_hst oh'.
				' WHERE '.dbConditionInt('oh.hostid', $hostIds);
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('opcommand_hst', [
			'hostid' => $hostIds,
		]);

		// delete empty operations
		$delOperationids = [];
		$sql = 'SELECT DISTINCT o.operationid'.
				' FROM operations o'.
				' WHERE '.dbConditionInt('o.operationid', $operationids).
				' AND NOT EXISTS(SELECT oh.opcommand_hstid FROM opcommand_hst oh WHERE oh.operationid=o.operationid)';
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$delOperationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('operations', [
			'operationid' => $delOperationids,
		]);

		$hosts = API::Host()->get([
			'output' => [
				'hostid',
				'name'
			],
			'hostids' => $hostIds,
			'nopermissions' => true
		]);

		// delete host inventory
		DB::delete('host_inventory', ['hostid' => $hostIds]);

		// delete host applications
		DB::delete('applications', ['hostid' => $hostIds]);

		// delete host
		DB::delete('hosts', ['hostid' => $hostIds]);

		// TODO: remove info from API
		foreach ($hosts as $host) {
			info(_s('Deleted: Host "%1$s".', $host['name']));
			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST, $host['hostid'], $host['name'], 'hosts', NULL, NULL);
		}

		// remove Monitoring > Latest data toggle profile values related to given hosts
		DB::delete('profiles', ['idx' => 'web.latest.toggle_other', 'idx2' => $hostIds]);

		return ['hostids' => $hostIds];
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$hostids = array_keys($result);

		// adding inventories
		if ($options['selectInventory'] !== null) {
			$relationMap = $this->createRelationMap($result, 'hostid', 'hostid');
			$inventory = API::getApiService()->select('host_inventory', [
				'output' => $options['selectInventory'],
				'filter' => ['hostid' => $hostids]
			]);
			$result = $relationMap->mapOne($result, zbx_toHash($inventory, 'hostid'), 'inventory');
		}

		// adding hostinterfaces
		if ($options['selectInterfaces'] !== null) {
			if ($options['selectInterfaces'] != API_OUTPUT_COUNT) {
				$interfaces = API::HostInterface()->get([
					'output' => $this->outputExtend($options['selectInterfaces'], ['hostid', 'interfaceid']),
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				]);

				// we need to order interfaces for proper linkage and viewing
				order_result($interfaces, 'interfaceid', ZBX_SORT_UP);

				$relationMap = $this->createRelationMap($interfaces, 'hostid', 'interfaceid');

				$interfaces = $this->unsetExtraFields($interfaces, ['hostid', 'interfaceid'], $options['selectInterfaces']);
				$result = $relationMap->mapMany($result, $interfaces, 'interfaces', $options['limitSelects']);
			}
			else {
				$interfaces = API::HostInterface()->get([
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);

				$interfaces = zbx_toHash($interfaces, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['interfaces'] = isset($interfaces[$hostid]) ? $interfaces[$hostid]['rowscount'] : 0;
				}
			}
		}

		// adding screens
		if ($options['selectScreens'] !== null) {
			if ($options['selectScreens'] != API_OUTPUT_COUNT) {
				$screens = API::TemplateScreen()->get([
					'output' => $this->outputExtend($options['selectScreens'], ['hostid']),
					'hostids' => $hostids,
					'nopermissions' => true
				]);
				if (!is_null($options['limitSelects'])) {
					order_result($screens, 'name');
				}

				// inherited screens do not have a unique screenid, so we're building a map using array keys
				$relationMap = new CRelationMap();
				foreach ($screens as $key => $screen) {
					$relationMap->addRelation($screen['hostid'], $key);
				}

				$screens = $this->unsetExtraFields($screens, ['hostid'], $options['selectScreens']);
				$result = $relationMap->mapMany($result, $screens, 'screens', $options['limitSelects']);
			}
			else {
				$screens = API::TemplateScreen()->get([
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
				$screens = zbx_toHash($screens, 'hostid');

				foreach ($result as $hostid => $host) {
					$result[$hostid]['screens'] = isset($screens[$hostid]) ? $screens[$hostid]['rowscount'] : 0;
				}
			}
		}

		// adding discovery rule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			// discovered items
			$discoveryRules = DBFetchArray(DBselect(
				'SELECT hd.hostid,hd2.parent_itemid'.
					' FROM host_discovery hd,host_discovery hd2'.
					' WHERE '.dbConditionInt('hd.hostid', $hostids).
					' AND hd.parent_hostid=hd2.hostid'
			));
			$relationMap = $this->createRelationMap($discoveryRules, 'hostid', 'parent_itemid');

			$discoveryRules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding host discovery
		if ($options['selectHostDiscovery'] !== null) {
			$hostDiscoveries = API::getApiService()->select('host_discovery', [
				'output' => $this->outputExtend($options['selectHostDiscovery'], ['hostid']),
				'filter' => ['hostid' => $hostids],
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($hostDiscoveries, 'hostid', 'hostid');

			$hostDiscoveries = $this->unsetExtraFields($hostDiscoveries, ['hostid'],
				$options['selectHostDiscovery']
			);
			$result = $relationMap->mapOne($result, $hostDiscoveries, 'hostDiscovery');
		}

		return $result;
	}

	/**
	 * Checks if all of the given hosts are available for writing.
	 *
	 * @throws APIException     if a host is not writable or does not exist
	 *
	 * @param array  $hostids
	 * @param string $error
	 */
	protected function checkPermissions(array $hostids, $error) {
		if ($hostids) {
			$hostids = array_unique($hostids);

			$count = $this->get([
				'countOutput' => true,
				'hostids' => $hostids,
				'editable' => true
			]);

			if ($count != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	/**
	 * Validate connections from/to host and PSK fields.
	 *
	 * @param array  $hosts
	 * @param string $hosts[]['hostid']                    (optional if $db_hosts is null)
	 * @param int    $hosts[]['tls_connect']               (optionsl)
	 * @param int    $hosts[]['tls_accept']                (optional)
	 * @param string $hosts[]['tls_psk_identity']          (optional)
	 * @param string $hosts[]['tls_psk']                   (optional)
	 * @param string $hosts[]['tls_issuer']                (optional)
	 * @param string $hosts[]['tls_subject']               (optional)
	 * @param array  $db_hosts                             (optional)
	 * @param int    $hosts[<hostid>]['tls_connect']
	 * @param int    $hosts[<hostid>]['tls_accept']
	 * @param string $hosts[<hostid>]['tls_psk_identity']
	 * @param string $hosts[<hostid>]['tls_psk']
	 * @param string $hosts[<hostid>]['tls_issuer']
	 * @param string $hosts[<hostid>]['tls_subject']
	 *
	 * @throws APIException if incorrect encryption options.
	 */
	protected function validateEncryption(array $hosts, array $db_hosts = null) {
		$available_connect_types = [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE];
		$min_accept_type = HOST_ENCRYPTION_NONE;
		$max_accept_type = HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE;

		foreach ($hosts as $host) {
			foreach (['tls_connect', 'tls_accept'] as $field_name) {
				$$field_name = array_key_exists($field_name, $host)
					? $host[$field_name]
					: ($db_hosts !== null ? $db_hosts[$host['hostid']][$field_name] : HOST_ENCRYPTION_NONE);
			}

			if (!in_array($tls_connect, $available_connect_types)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_connect',
					_s('unexpected value "%1$s"', $tls_connect)
				));
			}

			if ($tls_accept < $min_accept_type || $tls_accept > $max_accept_type) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_accept',
					_s('unexpected value "%1$s"', $tls_accept)
				));
			}

			foreach (['tls_psk_identity', 'tls_psk', 'tls_issuer', 'tls_subject'] as $field_name) {
				$$field_name = array_key_exists($field_name, $host)
					? $host[$field_name]
					: ($db_hosts !== null ? $db_hosts[$host['hostid']][$field_name] : '');
			}

			// PSK validation.
			if ($tls_connect == HOST_ENCRYPTION_PSK || ($tls_accept & HOST_ENCRYPTION_PSK)) {
				if ($tls_psk_identity === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk_identity', _('cannot be empty'))
					);
				}

				if ($tls_psk === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk', _('cannot be empty'))
					);
				}

				if (!preg_match('/^([0-9a-f]{2})+$/i', $tls_psk)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_psk',
						_('an even number of hexadecimal characters is expected')
					));
				}

				if (strlen($tls_psk) < PSK_MIN_LEN) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_psk',
						_s('minimum length is %1$s characters', PSK_MIN_LEN)
					));
				}
			}
			else {
				if ($tls_psk_identity !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk_identity', _('should be empty'))
					);
				}

				if ($tls_psk !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk', _('should be empty'))
					);
				}
			}

			// Certificate validation.
			if ($tls_connect != HOST_ENCRYPTION_CERTIFICATE && !($tls_accept & HOST_ENCRYPTION_CERTIFICATE)) {
				if ($tls_issuer !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_issuer', _('should be empty'))
					);
				}

				if ($tls_subject !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_subject', _('should be empty'))
					);
				}
			}
		}
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $hosts		hosts data array
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array $hosts) {
		$host_db_fields = ['host' => null];

		$groupids = [];

		foreach ($hosts as &$host) {
			// Validate mandatory fields.
			if (!check_db_fields($host_db_fields, $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Wrong fields for host "%1$s".', array_key_exists('host', $host) ? $host['host'] : '')
				);
			}

			// Property 'compress' is not supported for hosts.
			if (array_key_exists('compress', $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
			}

			// Validate "host" field.
			if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $host['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect characters used for host name "%s".', $host['host'])
				);
			}

			// If visible name is not given or empty it should be set to host name. Required for duplicate checks.
			if (!array_key_exists('name', $host) || !trim($host['name'])) {
				$host['name'] = $host['host'];
			}

			// Validate "groups" field.
			if (!array_key_exists('groups', $host) || !is_array($host['groups']) || !$host['groups']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host "%1$s" cannot be without host group.', $host['host'])
				);
			}

			$groupids = array_merge($groupids, zbx_objectValues($host['groups'], 'groupid'));
		}
		unset($host);

		// Check for duplicate "host" and "name" fields.
		$duplicate = CArrayHelper::findDuplicate($hosts, 'host');
		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate host. Host with the same host name "%s" already exists in data.', $duplicate['host'])
			);
		}

		$duplicate = CArrayHelper::findDuplicate($hosts, 'name');
		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate host. Host with the same visible name "%s" already exists in data.', $duplicate['name'])
			);
		}

		// Validate permissions to host groups.
		if ($groupids) {
			$db_groups = API::HostGroup()->get([
				'output' => ['groupid'],
				'groupids' => $groupids,
				'editable' => true,
				'preservekeys' => true
			]);
		}

		foreach ($hosts as $host) {
			foreach ($host['groups'] as $group) {
				if (!array_key_exists($group['groupid'], $db_groups)) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}
			}
		}

		$inventory_fields = zbx_objectValues(getHostInventories(), 'db_field');

		$status_validator = new CLimitedSetValidator([
			'values' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED],
			'messageInvalid' => _('Incorrect status for host "%1$s".')
		]);

		$host_names = [];

		foreach ($hosts as $host) {
			if (!array_key_exists('interfaces', $host) || !is_array($host['interfaces']) || !$host['interfaces']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('No interfaces for host "%s".', $host['host']));
			}

			if (array_key_exists('status', $host)) {
				$status_validator->setObjectName($host['host']);
				$this->checkValidator($host['status'], $status_validator);
			}

			if (array_key_exists('inventory', $host) && $host['inventory']) {
				if (array_key_exists('inventory_mode', $host) && $host['inventory_mode'] == HOST_INVENTORY_DISABLED) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot set inventory fields for disabled inventory.'));
				}

				$fields = array_keys($host['inventory']);
				foreach ($fields as $field) {
					if (!in_array($field, $inventory_fields)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect inventory field "%s".', $field));
					}
				}
			}

			// Collect technical and visible names to check if they exist in hosts and templates.
			$host_names['host'][$host['host']] = true;
			$host_names['name'][$host['name']] = true;
		}

		$filter = [
			'host' => array_keys($host_names['host']),
			'name' => array_keys($host_names['name'])
		];

		$hosts_exists = $this->get([
			'output' => ['host', 'name'],
			'filter' => $filter,
			'searchByAny' => true,
			'nopermissions' => true
		]);

		foreach ($hosts_exists as $host_exists) {
			if (array_key_exists($host_exists['host'], $host_names['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host with the same name "%s" already exists.', $host_exists['host'])
				);
			}

			if (array_key_exists($host_exists['name'], $host_names['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host with the same visible name "%s" already exists.', $host_exists['name'])
				);
			}
		}

		$templates_exists = API::Template()->get([
			'output' => ['host', 'name'],
			'filter' => $filter,
			'searchByAny' => true,
			'nopermissions' => true
		]);

		foreach ($templates_exists as $template_exists) {
			if (array_key_exists($template_exists['host'], $host_names['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Template with the same name "%s" already exists.', $template_exists['host'])
				);
			}

			if (array_key_exists($template_exists['name'], $host_names['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Template with the same visible name "%s" already exists.', $template_exists['name'])
				);
			}
		}

		$this->validateEncryption($hosts);
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $hosts			hosts data array
	 * @param array $db_hosts		db hosts data array
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $hosts, array $db_hosts) {
		$host_db_fields = ['hostid' => null];

		foreach ($hosts as $host) {
			// Validate mandatory fields.
			if (!check_db_fields($host_db_fields, $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Wrong fields for host "%1$s".', array_key_exists('host', $host) ? $host['host'] : '')
				);
			}

			// Property 'compress' is not supported for hosts.
			if (array_key_exists('compress', $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
			}

			// Validate host permissions.
			if (!array_key_exists($host['hostid'], $db_hosts)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _(
					'No permissions to referred object or it does not exist!'
				));
			}

			// Validate "groups" field.
			if (array_key_exists('groups', $host) && (!is_array($host['groups']) || !$host['groups'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host "%1$s" cannot be without host group.', $db_hosts[$host['hostid']]['host'])
				);
			}

			// Permissions to host groups is validated in massUpdate().
		}

		$inventory_fields = zbx_objectValues(getHostInventories(), 'db_field');

		$status_validator = new CLimitedSetValidator([
			'values' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED],
			'messageInvalid' => _('Incorrect status for host "%1$s".')
		]);

		$update_discovered_validator = new CUpdateDiscoveredValidator([
			'allowed' => ['hostid', 'status', 'inventory', 'description'],
			'messageAllowedField' => _('Cannot update "%2$s" for a discovered host "%1$s".')
		]);

		$host_names = [];

		foreach ($hosts as &$host) {
			$db_host = $db_hosts[$host['hostid']];
			$host_name = array_key_exists('host', $host) ? $host['host'] : $db_host['host'];

			if (array_key_exists('status', $host)) {
				$status_validator->setObjectName($host_name);
				$this->checkValidator($host['status'], $status_validator);
			}

			if (array_key_exists('inventory', $host) && $host['inventory']) {
				if (array_key_exists('inventory_mode', $host) && $host['inventory_mode'] == HOST_INVENTORY_DISABLED) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot set inventory fields for disabled inventory.'));
				}

				$fields = array_keys($host['inventory']);
				foreach ($fields as $field) {
					if (!in_array($field, $inventory_fields)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect inventory field "%s".', $field));
					}
				}
			}

			// cannot update certain fields for discovered hosts
			$update_discovered_validator->setObjectName($host_name);
			$this->checkPartialValidator($host, $update_discovered_validator, $db_host);

			if (array_key_exists('interfaces', $host)) {
				if (!is_array($host['interfaces']) || !$host['interfaces']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('No interfaces for host "%s".', $host['host']));
				}
			}

			if (array_key_exists('host', $host)) {
				if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $host['host'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect characters used for host name "%s".', $host['host'])
					);
				}

				if (array_key_exists('host', $host_names) && array_key_exists($host['host'], $host_names['host'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Duplicate host. Host with the same host name "%s" already exists in data.', $host['host'])
					);
				}

				$host_names['host'][$host['host']] = $host['hostid'];
			}

			if (array_key_exists('name', $host)) {
				// if visible name is empty replace it with host name
				if (zbx_empty(trim($host['name']))) {
					if (!array_key_exists('host', $host)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Visible name cannot be empty if host name is missing.')
						);
					}
					$host['name'] = $host['host'];
				}

				if (array_key_exists('name', $host_names) && array_key_exists($host['name'], $host_names['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Duplicate host. Host with the same visible name "%s" already exists in data.', $host['name'])
					);
				}
				$host_names['name'][$host['name']] = $host['hostid'];
			}

			if (array_key_exists('tls_connect', $host) || array_key_exists('tls_accept', $host)) {
				$tls_connect = array_key_exists('tls_connect', $host) ? $host['tls_connect'] : $db_host['tls_connect'];
				$tls_accept = array_key_exists('tls_accept', $host) ? $host['tls_accept'] : $db_host['tls_accept'];

				// Clean PSK fields.
				if ($tls_connect != HOST_ENCRYPTION_PSK && !($tls_accept & HOST_ENCRYPTION_PSK)) {
					if (!array_key_exists('tls_psk_identity', $host)) {
						$host['tls_psk_identity'] = '';
					}
					if (!array_key_exists('tls_psk', $host)) {
						$host['tls_psk'] = '';
					}
				}

				// Clean certificate fields.
				if ($tls_connect != HOST_ENCRYPTION_CERTIFICATE && !($tls_accept & HOST_ENCRYPTION_CERTIFICATE)) {
					if (!array_key_exists('tls_issuer', $host)) {
						$host['tls_issuer'] = '';
					}
					if (!array_key_exists('tls_subject', $host)) {
						$host['tls_subject'] = '';
					}
				}
			}
		}
		unset($host);

		if (array_key_exists('host', $host_names) || array_key_exists('name', $host_names)) {
			$filter = [];

			if (array_key_exists('host', $host_names)) {
				$filter['host'] = array_keys($host_names['host']);
			}

			if (array_key_exists('name', $host_names)) {
				$filter['name'] = array_keys($host_names['name']);
			}

			$hosts_exists = $this->get([
				'output' => ['hostid', 'host', 'name'],
				'filter' => $filter,
				'searchByAny' => true,
				'nopermissions' => true,
				'preservekeys' => true
			]);

			foreach ($hosts_exists as $host_exists) {
				if (array_key_exists('host', $host_names) && array_key_exists($host_exists['host'], $host_names['host'])
						&& bccomp($host_exists['hostid'], $host_names['host'][$host_exists['host']]) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Host with the same name "%s" already exists.', $host_exists['host'])
					);
				}

				if (array_key_exists('name', $host_names) && array_key_exists($host_exists['name'], $host_names['name'])
						&& bccomp($host_exists['hostid'], $host_names['name'][$host_exists['name']]) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Host with the same visible name "%s" already exists.', $host_exists['name'])
					);
				}
			}

			$templates_exists = API::Template()->get([
				'output' => ['hostid', 'host', 'name'],
				'filter' => $filter,
				'searchByAny' => true,
				'nopermissions' => true,
				'preservekeys' => true
			]);

			foreach ($templates_exists as $template_exists) {
				if (array_key_exists('host', $host_names)
						&& array_key_exists($template_exists['host'], $host_names['host'])
						&& bccomp($template_exists['templateid'], $host_names['host'][$template_exists['host']]) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Template with the same name "%s" already exists.', $template_exists['host'])
					);
				}

				if (array_key_exists('name', $host_names)
						&& array_key_exists($template_exists['name'], $host_names['name'])
						&& bccomp($template_exists['templateid'], $host_names['name'][$template_exists['name']]) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Template with the same visible name "%s" already exists.', $template_exists['name'])
					);
				}
			}
		}

		$this->validateEncryption($hosts, $db_hosts);

		return $hosts;
	}
}
