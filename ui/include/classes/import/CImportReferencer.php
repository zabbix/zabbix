<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Class that handles associations for zabbix elements unique fields and their database ids.
 * The purpose is to gather all elements that need ids from database and resolve them with one query.
 */
class CImportReferencer {

	/**
	 * @var array with references to interfaceid (hostid -> reference_name -> interfaceid)
	 */
	public $interfacesCache = [];
	protected $groups = [];
	protected $templates = [];
	protected $hosts = [];
	protected $items = [];
	protected $valueMaps = [];
	protected $triggers = [];
	protected $graphs = [];
	protected $iconMaps = [];
	protected $maps = [];
	protected $templateDashboards = [];
	protected $macros = [];
	protected $proxies = [];
	protected $hostPrototypes = [];
	protected $httptests = [];
	protected $httpsteps = [];

	protected $db_groups;
	protected $db_templates;
	protected $db_hosts;
	protected $db_items;
	protected $valueMapsRefs;
	protected $db_triggers;
	protected $db_graphs;
	protected $iconMapsRefs;
	protected $mapsRefs;
	protected $templateDashboardsRefs;
	protected $db_macros;
	protected $db_proxies;
	protected $hostPrototypesRefs;
	protected $db_httptests;
	protected $db_httpsteps;

	/**
	 * Initializes references for items.
	 */
	public function initItemsReferences() {
		if ($this->db_items === null) {
			$this->selectItems();
		}
	}

	public function findGroupidByUuid(string $uuid): ?string {
		if ($this->db_groups === null) {
			$this->selectGroups();
		}

		foreach ($this->db_groups as $groupid => $group) {
			if ($group['uuid'] === $uuid) {
				return $groupid;
			}
		}

		return null;
	}

	public function findGroupidByName(string $name): ?string {
		if ($this->db_groups === null) {
			$this->selectGroups();
		}

		foreach ($this->db_groups as $groupid => $group) {
			if ($group['name'] === $name) {
				return $groupid;
			}
		}

		return null;
	}

	public function findTemplateidByUuid(string $uuid): ?string {
		if ($this->db_templates === null) {
			$this->selectTemplates();
		}

		foreach ($this->db_templates as $templateid => $template) {
			if ($template['uuid'] === $uuid) {
				return $templateid;
			}
		}

		return null;
	}

	public function findTemplateidByHost(string $host): ?string {
		if ($this->db_templates === null) {
			$this->selectTemplates();
		}

		foreach ($this->db_templates as $templateid => $template) {
			if ($template['host'] === $host) {
				return $templateid;
			}
		}

		return null;
	}

	/**
	 * Get host id by host.
	 *
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function findHostidByHost(string $name): ?string {
		if ($this->db_hosts === null) {
			$this->selectHosts();
		}

		foreach ($this->db_hosts as $hostid => $host) {
			if ($host['host'] === $name) {
				return $hostid;
			}
		}

		return null;
	}

	/**
	 * Get host or template id by host.
	 *
	 * @param string $host
	 *
	 * @return string|null
	 */
	public function findTemplateidOrHostidByHost(string $host): ?string {
		$templateid = $this->findTemplateidByHost($host);

		if ($templateid !== null) {
			return $templateid;
		}

		return $this->findHostidByHost($host);
	}

	/**
	 * Get interface ID by host ID and interface reference name.
	 *
	 * @param string $hostid  Host ID.
	 * @param string $name    Interface reference name.
	 *
	 * @return string|bool
	 */
	public function resolveInterface($hostid, $name) {
		return (array_key_exists($hostid, $this->interfacesCache)
				&& array_key_exists($name, $this->interfacesCache[$hostid]))
			? $this->interfacesCache[$hostid][$name]
			: false;
	}

	/**
	 * Get item id by uuid.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findItemByUuid(string $uuid): ?string {
		if ($this->db_items === null) {
			$this->selectItems();
		}

		foreach ($this->db_items as $itemid => $item) {
			if ($item['uuid'] === $uuid) {
				return $itemid;
			}
		}

		return null;
	}

	/**
	 * Get item id by host id and item key_.
	 *
	 * @param string $hostid
	 * @param string $key
	 *
	 * @return string|null
	 */
	public function findItemByKey(string $hostid, string $key): ?string {
		if ($this->db_items === null) {
			$this->selectItems();
		}

		foreach ($this->db_items as $itemid => $item) {
			if ($item['hostid'] === $hostid && $item['key_'] === $key) {
				return $itemid;
			}
		}

		return null;
	}

	/**
	 * Get value map id by vale map name.
	 *
	 * @param string $hostid
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveValueMap($hostid, $name) {
		if ($this->valueMapsRefs === null) {
			$this->selectValueMaps();
		}

		return isset($this->valueMapsRefs[$hostid][$name]) ? $this->valueMapsRefs[$hostid][$name] : false;
	}

	/**
	 * Get trigger ID by trigger uuid.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findTriggeridByUuid(string $uuid): ?string {
		if ($this->db_triggers === null) {
			$this->selectTriggers();
		}

		foreach ($this->db_triggers as $triggerid => $trigger) {
			if ($trigger['uuid'] === $uuid) {
				return $triggerid;
			}
		}

		return null;
	}

	/**
	 * Get trigger ID by trigger name and expressions.
	 *
	 * @param string $name
	 * @param string $expression
	 * @param string $recovery_expression
	 *
	 * @return string|null
	 */
	public function findTriggeridByName(string $name, string $expression, string $recovery_expression): ?string {
		if ($this->db_triggers === null) {
			$this->selectTriggers();
		}

		foreach ($this->db_triggers as $triggerid => $trigger) {
			if ($trigger['description'] === $name
					&& $trigger['expression'] === $expression
					&& $trigger['recovery_expression'] === $recovery_expression) {
				return $triggerid;
			}
		}

		return null;
	}

	/**
	 * Get graph ID by uuid.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findGraphidByUuid(string $uuid): ?string {
		if ($this->db_graphs === null) {
			$this->selectGraphs();
		}

		foreach ($this->db_graphs as $graphid => $graph) {
			if ($graph['uuid'] === $uuid) {
				return $graphid;
			}
		}

		return null;
	}

	/**
	 * Get graph ID by host ID and graph name.
	 *
	 * @param string $hostid
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findGraphidByName(string $hostid, string $name): ?string {
		if ($this->db_graphs === null) {
			$this->selectGraphs();
		}

		foreach ($this->db_graphs as $graphid => $graph) {
			if ($graph['name'] === $name && in_array($hostid, $graph['hosts'])) {
				return $graphid;
			}
		}

		return null;
	}

	/**
	 * Get icon map id by name.
	 *
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveIconMap($name) {
		if ($this->iconMapsRefs === null) {
			$this->selectIconMaps();
		}

		return isset($this->iconMapsRefs[$name]) ? $this->iconMapsRefs[$name] : false;
	}

	/**
	 * Get map id by name.
	 *
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveMap($name) {
		if ($this->mapsRefs === null) {
			$this->selectMaps();
		}

		return isset($this->mapsRefs[$name]) ? $this->mapsRefs[$name] : false;
	}

	/**
	 * Get template dashboard ID by template ID and dashboard name.
	 *
	 * @param string $templateid
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveTemplateDashboards($templateid, $name) {
		if ($this->templateDashboardsRefs === null) {
			$this->selectTemplateDashboards();
		}

		return isset($this->templateDashboardsRefs[$templateid][$name])
			? $this->templateDashboardsRefs[$templateid][$name]
			: false;
	}

	/**
	 * Get macro ID by host ID and macro name.
	 *
	 * @param string $hostid
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findMacroid(string $hostid, string $name): ?string {
		if ($this->db_macros === null) {
			$this->selectMacros();
		}

		foreach ($this->db_macros as $hostmacroid => $macro) {
			if ($macro['hostid'] === $hostid && $macro['macro'] === $name) {
				return $hostmacroid;
			}
		}

		return null;
	}

	/**
	 * Get proxy id by name.
	 *
	 * @param string $host
	 *
	 * @return string|null
	 */
	public function findProxyidByHost(string $host): ?string {
		if ($this->db_proxies === null) {
			$this->selectProxies();
		}

		foreach ($this->db_proxies as $proxyid => $proxy) {
			if ($proxy['host'] === $host) {
				return $proxyid;
			}
		}

		return null;
	}

	/**
	 * Get proxy id by name.
	 *
	 * @param string $hostId
	 * @param string $discoveryRuleId
	 * @param string $hostPrototype
	 *
	 * @return string|bool
	 */
	public function resolveHostPrototype($hostId, $discoveryRuleId, $hostPrototype) {
		if ($this->hostPrototypesRefs === null) {
			$this->selectHostPrototypes();
		}

		if (isset($this->hostPrototypesRefs[$hostId][$discoveryRuleId][$hostPrototype])) {
			return $this->hostPrototypesRefs[$hostId][$discoveryRuleId][$hostPrototype];
		}
		else {
			return false;
		}
	}

	/**
	 * Get httptestid by web scenario uuid.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findHttpTestidByUuid(string $uuid): ?string {
		if ($this->db_httptests === null) {
			$this->selectHttpTests();
		}

		foreach ($this->db_httptests as $httptestid => $httptest) {
			if ($httptest['uuid'] === $uuid) {
				return $httptestid;
			}
		}

		return null;
	}

	/**
	 * Get httptestid by hostid and web scenario name.
	 *
	 * @param string $hostid
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function findHttpTestidByName(string $hostid, string $name): ?string {
		if ($this->db_httptests === null) {
			$this->selectHttpTests();
		}

		foreach ($this->db_httptests as $httptestid => $httptest) {
			if ($httptest['hostid'] === $hostid && $httptest['name'] === $name) {
				return $httptestid;
			}
		}

		return null;
	}

	/**
	 * Get httpstepid by hostid, httptestid and web scenario step name.
	 *
	 * @param string $hostid
	 * @param string $httptestid
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findHttpStepidByName(string $hostid, string $httptestid, string $name): ?string {
		if ($this->db_httpsteps === null) {
			$this->selectHttpSteps();
		}

		foreach ($this->db_httpsteps as $httpstepid => $httpstep) {
			if ($httpstep['hostid'] === $hostid && $httpstep['name'] === $name
					&& $httpstep['httptestid'] === $httptestid) {
				return $httpstepid;
			}
		}

		return null;
	}

	/**
	 * Add group names that need association with a database group id.
	 *
	 * @param array $groups
	 */
	public function addGroups(array $groups) {
// TODO VM: can this lead to error?
//		$this->groups = array_unique(array_merge($this->groups, $groups));
		$this->groups = $groups;
	}

	/**
	 * Add group name association with group id.
	 *
	 * @param string $id
	 * @param array  $group
	 */
	public function setDbGroup(string $id, array $group): void {
		$this->db_groups[$id] = [
			'uuid' => $group['uuid'],
			'name' => $group['name']
		];
	}

	/**
	 * Add templates names that need association with a database template id.
	 *
	 * @param array $templates
	 */
	public function addTemplates(array $templates) {
//		$this->templates = array_unique(array_merge($this->templates, $templates));
		$this->templates = $templates;
	}

	/**
	 * Add template name association with template id.
	 *
	 * @param string $id
	 * @param array  $template
	 */
	public function setDbTemplate(string $id, array $template): void {
		$this->db_templates[$id] = [
			'uuid' => $template['uuid'],
			'host' => $template['host']
		];
	}

	/**
	 * Add hosts names that need association with a database host id.
	 *
	 * @param array $hosts
	 */
	public function addHosts(array $hosts) {
//		$this->hosts = array_unique(array_merge($this->hosts, $hosts));
		$this->hosts = $hosts;
	}

	/**
	 * Add host name association with host id.
	 *
	 * @param string $id
	 * @param array  $host
	 */
	public function setDbHost(string $id, array $host): void {
		$this->db_hosts[$id] = [
			'host' => $host['host']
		];
	}

	/**
	 * Add item keys that need association with a database item id.
	 * Input array has format:
	 * array('hostname1' => array('itemkey1', 'itemkey2'), 'hostname2' => array('itemkey1'), ...)
	 *
	 * @param array $items
	 */
	public function addItems(array $items) {
//		foreach ($items as $host => $keys) {
//			if (!isset($this->items[$host])) {
//				$this->items[$host] = [];
//			}
//			$this->items[$host] = array_unique(array_merge($this->items[$host], $keys));
//		}

		$this->items = $items;
	}

	/**
	 * Add item key association with item id.
	 *
	 * @param string $hostId
	 * @param string $key
	 * @param string $itemId
	 */
	public function addItemRef($hostId, $key, $itemId) {
		$this->db_items[$hostId][$key] = $itemId;
	}

	/**
	 * Add value map names that need association with a database value map ID.
	 *
	 * @param array $valueMaps
	 */
	public function addValueMaps(array $valueMaps) {
//		foreach ($valueMaps as $host => $valuemap_names) {
//			if (!array_key_exists($host, $this->valueMaps)) {
//				$this->valueMaps[$host] = [];
//			}
//			$this->valueMaps[$host] = array_unique(array_merge($this->valueMaps[$host], $valuemap_names));
//		}

		$this->valueMaps = $valueMaps;
	}

	/**
	 * Add trigger description/expression/recovery_expression that need association with a database trigger id.
	 *
	 * @param array $triggers
	 * @param array $triggers[<description>]
	 * @param array $triggers[<description>][<expression>]
	 * @param bool  $triggers[<description>][<expression>][<recovery_expression>]
	 */
	public function addTriggers(array $triggers) {
//		foreach ($triggers as $description => $expressions) {
//			if (!array_key_exists($description, $this->triggers)) {
//				$this->triggers[$description] = [];
//			}

//			foreach ($expressions as $expression => $recovery_expressions) {
//				if (!array_key_exists($expression, $this->triggers[$description])) {
//					$this->triggers[$description][$expression] = [];
//				}

//				foreach ($recovery_expressions as $recovery_expression => $foo) {
//					if (!array_key_exists($recovery_expression, $this->triggers[$description][$expression])) {
//						$this->triggers[$description][$expression][$recovery_expression] = true;
//					}
//				}
//			}
//		}

		$this->triggers = $triggers;
	}

	/**
	 * Add graph names that need association with a database graph ID.
	 * Input array has format:
	 * array('hostname1' => array('graphname1', 'graphname2'), 'hostname2' => array('graphname1'), ...)
	 *
	 * @param array $graphs
	 */
	public function addGraphs(array $graphs) {
//		foreach ($graphs as $host => $hostGraphs) {
//			if (!isset($this->graphs[$host])) {
//				$this->graphs[$host] = [];
//			}
//			$this->graphs[$host] = array_unique(array_merge($this->graphs[$host], $hostGraphs));
//		}

		$this->graphs = $graphs;
	}

	/**
	 * Add trigger name/expression association with trigger id.
	 *
	 * @param string $name
	 * @param string $expression
	 * @param string $recovery_expression
	 * @param string $triggerid
	 */
	public function addTriggerRef($name, $expression, $recovery_expression, $triggerid) {
		$this->db_triggers[$name][$expression][$recovery_expression] = $triggerid;
	}

	/**
	 * Add icon map names that need association with a database icon map id.
	 *
	 * @param array $iconMaps
	 */
	public function addIconMaps(array $iconMaps) {
//		$this->iconMaps = array_unique(array_merge($this->iconMaps, $iconMaps));
		$this->iconMaps = $iconMaps;
	}

	/**
	 * Add map names that need association with a database map id.
	 *
	 * @param array $maps
	 */
	public function addMaps(array $maps) {
//		$this->maps = array_unique(array_merge($this->maps, $maps));
		$this->maps = $maps;
	}

	/**
	 * Add map name association with map id.
	 *
	 * @param string $name
	 * @param string $mapId
	 */
	public function addMapRef($name, $mapId) {
		$this->mapsRefs[$name] = $mapId;
	}

	/**
	 * Add templated dashboard names that need association with a database dashboard id.
	 *
	 * @param array $dashboards
	 */
	public function addTemplateDashboards(array $dashboards) {
//		$this->templateDashboards = array_unique(array_merge($this->templateDashboards, $dashboards));
		$this->templateDashboards = $dashboards;
	}

	/**
	 * Add template dashboard name association with template dashboard ID.
	 *
	 * @param string $name
	 * @param string $template_dashboardid
	 */
	public function addTemplateDashboardsRef($name, $template_dashboardid) {
		$this->templateDashboardsRefs[$name] = $template_dashboardid;
	}

	/**
	 * Add macros names that need association with a database macro id.
	 *
	 * @param array $macros
	 */
	public function addMacros(array $macros) {
//		foreach ($macros as $host => $ms) {
//			if (!isset($this->macros[$host])) {
//				$this->macros[$host] = [];
//			}
//			$this->macros[$host] = array_unique(array_merge($this->macros[$host], $ms));
//		}

		$this->macros = $macros;
	}

	/**
	 * Add macro name association with macro id.
	 *
	 * @param string $hostId
	 * @param string $macro
	 * @param string $macroId
	 */
	public function addMacroRef($hostId, $macro, $macroId) {
		$this->db_macros[$hostId][$macro] = $macroId;
	}

	/**
	 * Add proxy names that need association with a database proxy id.
	 *
	 * @param array $proxies
	 */
	public function addProxies(array $proxies) {
//		$this->proxies = array_unique(array_merge($this->proxies, $proxies));
		$this->proxies = $proxies;
	}

	/**
	 * Add proxy name association with proxy id.
	 *
	 * @param string $name
	 * @param string $proxyId
	 */
	public function addProxyRef($name, $proxyId) {
		$this->db_proxies[$name] = $proxyId;
	}

	/**
	 * Add host prototypes that need association with a database host prototype id.
	 *
	 * @param array $hostPrototypes
	 */
	public function addHostPrototypes(array $hostPrototypes) {
//		foreach ($hostPrototypes as $host => $discoveryRule) {
//			if (!isset($this->hostPrototypes[$host])) {
//				$this->hostPrototypes[$host] = [];
//			}
//			foreach ($discoveryRule as $discoveryRuleKey => $hostPrototypes) {
//				if (!isset($this->hostPrototypes[$host][$discoveryRuleKey])) {
//					$this->hostPrototypes[$host][$discoveryRuleKey] = [];
//				}
//				$this->hostPrototypes[$host][$discoveryRuleKey] = array_unique(
//					array_merge($this->hostPrototypes[$host][$discoveryRuleKey], $hostPrototypes)
//				);
//			}
//		}

		$this->hostPrototypes = $hostPrototypes;
	}

	/**
	 * Add web scenario names that need association with a database httptestid.
	 *
	 * @param array  $httptests
	 * @param string $httptests[<host>][]	web scenario name
	 */
	public function addHttpTests(array $httptests) {
//		foreach ($httptests as $host => $names) {
//			if (!array_key_exists($host, $this->httptests)) {
//				$this->httptests[$host] = [];
//			}

//			$this->httptests[$host] = array_unique(array_merge($this->httptests[$host], $names));
//		}

		$this->httptests = $httptests;
	}

	/**
	 * Add web scenario step names that need association with a database httpstepid.
	 *
	 * @param array  $httpsteps
	 * @param string $httpsteps[<host>][<httptest_name>][]	web scenario step name
	 */
	public function addHttpSteps(array $httpsteps) {
//		foreach ($httpsteps as $host => $httptests) {
//			if (!array_key_exists($host, $this->httpsteps)) {
//				$this->httpsteps[$host] = [];
//			}

//			foreach ($httptests as $httptest_name => $httpstep_names) {
//				if (!array_key_exists($httptest_name, $this->httpsteps[$host])) {
//					$this->httpsteps[$host][$httptest_name] = [];
//				}

//				$this->httpsteps[$host][$httptest_name] =
//					array_unique(array_merge($this->httpsteps[$host][$httptest_name], $httpstep_names));
//			}
//		}

		$this->httpsteps = $httpsteps;
	}

	/**
	 * Select group ids for previously added group names.
	 */
	protected function selectGroups(): void {
		$this->db_groups = [];

		if (!$this->groups) {
			return;
		}

		$this->db_groups = API::HostGroup()->get([
			'output' => ['name', 'uuid'],
			'filter' => [
				'uuid' => array_column($this->groups, 'uuid'),
				'name' => array_keys($this->groups)
			],
			'searchByAny' => true,
			'preservekeys' => true
		]);

		$this->groups = [];
	}

	/**
	 * Select template ids for previously added template names.
	 */
	protected function selectTemplates(): void {
		$this->db_templates = [];

		if (!$this->templates) {
			return;
		}

		$this->db_templates = API::Template()->get([
			'output' => ['host', 'uuid'],
			'filter' => [
				'uuid' => array_column($this->templates, 'uuid'),
				'host' => array_keys($this->templates)
			],
			'searchByAny' => true,
			'editable' => true,
			'preservekeys' => true
		]);

		$this->templates = [];
	}

	/**
	 * Select host ids for previously added host names.
	 */
	protected function selectHosts(): void {
		$this->db_hosts = [];

		if (!$this->hosts) {
			return;
		}

		// Fetch only normal hosts, discovered hosts must not be imported.
		$this->db_hosts = API::Host()->get([
			'output' => ['host'],
			'filter' => ['host' => array_keys($this->hosts)],
			'templated_hosts' => true,
			'preservekeys' => true
		]);

		$this->hosts = [];
	}

	/**
	 * Select item ids for previously added item keys.
	 */
	protected function selectItems(): void {
		$this->db_items = [];

		if (!$this->items) {
			return;
		}

		$sql_where = [];

		foreach ($this->items as $host => $items) {
			$hostid = $this->findTemplateidOrHostidByHost($host);

			if ($hostid !== null) {
				$sql_where[] = '(i.hostid='.zbx_dbstr($hostid)
					.' AND ('
						.dbConditionString('i.key_', array_keys($items))
						.' OR '.dbConditionString('i.uuid', array_column($items, 'uuid'))
					.'))';
			}
		}

		if ($sql_where) {
			$db_items = DBselect(
				'SELECT i.itemid,i.hostid,i.key_,i.uuid FROM items i WHERE '.implode(' OR ', $sql_where)
			);

			while ($db_item = DBfetch($db_items)) {
				$this->db_items[$db_item['itemid']] = [
					'uuid' => $db_item['uuid'],
					'key_' => $db_item['key_'],
					'hostid' => $db_item['hostid']
				];
			}
		}
	}

	/**
	 * Unset item refs to make referencer select them from db again.
	 */
	public function refreshItems(): void {
		$this->db_items = null;
	}

	/**
	 * Select value map IDs for previously added value map names.
	 */
	protected function selectValueMaps() {
		if ($this->valueMaps) {
			$this->valueMapsRefs = [];
			$sql_where = [];

			foreach ($this->valueMaps as $host => $valuemap_names) {
				$hostid = $this->findTemplateidOrHostidByHost($host);
				if ($hostid) {
					$sql_where[] = '(vm.hostid='.zbx_dbstr($hostid).' AND '.
						dbConditionString('vm.name', $valuemap_names).')';
				}
			}

			if ($sql_where) {
				$db_valuemaps = DBselect(
					'SELECT vm.valuemapid,vm.hostid,vm.name'.
					' FROM valuemap vm'.
					' WHERE '.implode(' OR ', $sql_where)
				);
				while ($valuemap = DBfetch($db_valuemaps)) {
					$this->valueMapsRefs[$valuemap['hostid']][$valuemap['name']] = $valuemap['valuemapid'];
				}
			}

			$this->valueMaps = [];
		}
	}

	/**
	 * Select trigger ids for previously added trigger names/expressions.
	 */
	protected function selectTriggers(): void {
		$this->db_triggers = [];

		if (!$this->triggers) {
			return;
		}

		$uuids = [];

		foreach ($this->triggers as $trigger) {
			foreach ($trigger as $expression) {
				$uuids += array_flip(array_column($expression, 'uuid'));
			}
		}

		$db_triggers = API::Trigger()->get([
			'output' => ['uuid', 'description', 'expression', 'recovery_expression'],
			'filter' => [
				'uuid' => array_keys($uuids),
				'flags' => [
					ZBX_FLAG_DISCOVERY_NORMAL,
					ZBX_FLAG_DISCOVERY_PROTOTYPE,
					ZBX_FLAG_DISCOVERY_CREATED
				]
			],
			'preservekeys' => true
		]);

		$db_triggers += API::Trigger()->get([
			'output' => ['uuid', 'description', 'expression', 'recovery_expression'],
			'filter' => [
				'description' => array_keys($this->triggers),
				'flags' => [
					ZBX_FLAG_DISCOVERY_NORMAL,
					ZBX_FLAG_DISCOVERY_PROTOTYPE,
					ZBX_FLAG_DISCOVERY_CREATED
				]
			],
			'preservekeys' => true
		]);

		if (!$db_triggers) {
			return;
		}

		$db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($db_triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		foreach ($db_triggers as $db_trigger) {
			$uuid = $db_trigger['uuid'];
			$description = $db_trigger['description'];
			$expression = $db_trigger['expression'];
			$recovery_expression = $db_trigger['recovery_expression'];

			if (array_key_exists($uuid, $uuids)
				|| (array_key_exists($description, $this->triggers)
					&& array_key_exists($expression, $this->triggers[$description])
					&& array_key_exists($recovery_expression, $this->triggers[$description][$expression]))) {
				$this->db_triggers[$db_trigger['triggerid']] = $db_trigger;
			}
		}

		$this->triggers = [];

		// TODO VM: How to check, if nonexisting trigger is from a template? Probably it can only be done by parsing the trigger expressions.
		// TODO VM: if such check is done, they (triggers) need to be added to $this->triggersUuidRefs with 'false' to avoid seraching them by name.
	}

	/**
	 * Select graph IDs for previously added graph names.
	 */
	protected function selectGraphs(): void {
		$this->db_graphs = [];

		if (!$this->graphs) {
			return;
		}

		$graph_uuids = [];
		$graph_names = [];

		foreach ($this->graphs as $graph) {
			$graph_uuids += array_flip(array_column($graph, 'uuid'));
			$graph_names += array_flip(array_keys($graph));
		}

		$db_graphs =  API::Graph()->get([
			'output' => ['uuid', 'name'],
			'selectHosts' => ['hostid'],
			'filter' => [
				'uuid' => array_keys($graph_uuids),
				'flags' => null
			],
			'preservekeys' => true
		]);

		$db_graphs += API::Graph()->get([
			'output' => ['uuid', 'name'],
			'selectHosts' => ['hostid'],
			'filter' => [
				'name' => array_keys($graph_names),
				'flags' => null
			],
			'preservekeys' => true
		]);

		foreach ($db_graphs as $graph) {
			$graph['hosts'] = array_column($graph['hosts'], 'hostid');
			$this->db_graphs[$graph['graphid']] = $graph;
		}

		$this->graphs = [];
	}

	/**
	 * Unset trigger refs to make referencer select them from db again.
	 */
	public function refreshTriggers(): void {
		$this->db_triggers = null;
	}

	/**
	 * Unset graph refs to make referencer select them from DB again.
	 */
	public function refreshGraphs(): void {
		$this->db_graphs = null;
	}

	/**
	 * Select icon map ids for previously added icon maps names.
	 */
	protected function selectIconMaps() {
		if (!empty($this->iconMaps)) {
			$this->iconMapsRefs = [];
			$dbIconMaps = API::IconMap()->get([
				'filter' => ['name' => $this->iconMaps],
				'output' => ['iconmapid', 'name'],
				'preservekeys' => true
			]);
			foreach ($dbIconMaps as $iconMap) {
				$this->iconMapsRefs[$iconMap['name']] = $iconMap['iconmapid'];
			}

			$this->iconMaps = [];
		}
	}

	/**
	 * Select map ids for previously added maps names.
	 */
	protected function selectMaps() {
		if (!empty($this->maps)) {
			$this->mapsRefs = [];
			$dbMaps = API::Map()->get([
				'filter' => ['name' => $this->maps],
				'output' => ['sysmapid', 'name'],
				'preservekeys' => true
			]);
			foreach ($dbMaps as $dbMap) {
				$this->mapsRefs[$dbMap['name']] = $dbMap['sysmapid'];
			}

			$this->maps = [];
		}
	}

	/**
	 * Select template dashboard IDs for previously added dashboard names and template IDs.
	 */
	protected function selectTemplateDashboards() {
		if ($this->templateDashboards) {
			$this->templateDashboardsRefs = [];

			$db_template_dashboards = API::TemplateDashboard()->get([
				'output' => ['dashboardid', 'name', 'templateid'],
				'filter' => ['name' => $this->templateDashboards]
			]);
			foreach ($db_template_dashboards as $dashboard) {
				$this->templateDashboardsRefs[$dashboard['templateid']][$dashboard['name']] = $dashboard['dashboardid'];
			}

			$this->templateDashboards = [];
		}
	}

	/**
	 * Select macro ids for previously added macro names.
	 */
	protected function selectMacros(): void {
		$this->db_macros = [];

		if (!$this->macros) {
			return;
		}

		$sql_where = [];

		foreach ($this->macros as $host => $macros) {
			$hostid = $this->findTemplateidOrHostidByHost($host);
			if ($hostid) {
				$sql_where[] = '(hm.hostid='.zbx_dbstr($hostid).' AND '
					.dbConditionString('hm.macro', array_keys($macros)).')';
			}
		}

		if ($sql_where) {
			$db_macros = DBselect('SELECT hm.hostmacroid,hm.hostid,hm.macro FROM hostmacro hm'
				.' WHERE '.implode(' OR ', $sql_where));

			while ($db_macro = DBfetch($db_macros)) {
				$this->db_macros[$db_macro['hostmacroid']] = [
					'hostid' => $db_macro['hostid'],
					'macro' => $db_macro['macro']
				];
			}
		}

		$this->macros = [];
	}

	/**
	 * Select proxy ids for previously added proxy names.
	 */
	protected function selectProxies(): void {
		$this->db_proxies = [];

		if (!$this->proxies) {
			return;
		}

		$this->db_proxies = API::Proxy()->get([
			'output' => ['host'],
			'search' => ['host' => array_keys($this->proxies)],
			'preservekeys' => true
		]);

		$this->proxies = [];
	}

	/**
	 * Select host prototype ids for previously added host prototypes names.
	 */
	protected function selectHostPrototypes() {
		if (!empty($this->hostPrototypes)) {
			$this->hostPrototypesRefs = [];
			$sqlWhere = [];
			foreach ($this->hostPrototypes as $host => $discoveryRule) {
				$hostId = $this->findTemplateidOrHostidByHost($host);

				foreach ($discoveryRule as $discoveryRuleKey => $hostPrototypes) {
					$discoveryRuleId = $this->findItemByKey($hostId, $discoveryRuleKey);
					if ($hostId) {
						$sqlWhere[] = '(hd.parent_itemid='.zbx_dbstr($discoveryRuleId).' AND '.dbConditionString('h.host', $hostPrototypes).')';
					}
				}
			}

			if ($sqlWhere) {
				$query = DBselect(
					'SELECT h.host,h.hostid,hd.parent_itemid,i.hostid AS parent_hostid '.
					' FROM hosts h,host_discovery hd,items i'.
					' WHERE h.hostid=hd.hostid'.
						' AND hd.parent_itemid=i.itemid'.
						' AND ('.implode(' OR ', $sqlWhere).')'
				);
				while ($data = DBfetch($query)) {
					$this->hostPrototypesRefs[$data['parent_hostid']][$data['parent_itemid']][$data['host']] = $data['hostid'];
				}
			}
		}
	}

	/**
	 * Select httptestids for previously added web scenario names.
	 */
	protected function selectHttpTests(): void {
		$this->db_httptests = [];

		if (!$this->httptests) {
			return;
		}

		$sql_where = [];

		foreach ($this->httptests as $host => $httptests) {
			$hostid = $this->findTemplateidOrHostidByHost($host);

			if ($hostid !== false) {
				$sql_where[] = '(ht.hostid='.zbx_dbstr($hostid)
					.' AND ('
						.dbConditionString('ht.name', array_keys($httptests))
						.' OR '.dbConditionString('ht.uuid', array_column($httptests, 'uuid'))
					.'))';
			}
		}

		if ($sql_where) {
			$db_httptests = DBselect(
				'SELECT ht.hostid,ht.name,ht.httptestid,ht.uuid FROM httptest ht WHERE '.implode(' OR ', $sql_where)
			);

			while ($db_httptest = DBfetch($db_httptests)) {
				$this->db_httptests[$db_httptest['httptestid']] = [
					'uuid' => $db_httptest['uuid'],
					'name' => $db_httptest['name'],
					'hostid' => $db_httptest['hostid']
				];
			}
		}
	}

	/**
	 * Unset web scenario refs to make referencer select them from db again.
	 */
	public function refreshHttpTests(): void {
		$this->db_httptests = null;
	}

	/**
	 * Select httpstepids for previously added web scenario step names.
	 */
	protected function selectHttpSteps(): void {
		$this->db_httpsteps = [];

		if (!$this->httpsteps) {
			return;
		}

		$sql_where = [];

		foreach ($this->httpsteps as $host => $httptests) {
			$hostid = $this->findTemplateidOrHostidByHost($host);

			if ($hostid !== null) {
				foreach ($httptests as $httpstep_names) {
					$sql_where[] = dbConditionString('hs.name', array_keys($httpstep_names));
				}
			}
		}

		if ($sql_where) {
			$db_httpsteps = DBselect(
				'SELECT ht.hostid,hs.httptestid,hs.name,hs.httpstepid'.
				' FROM httptest ht,httpstep hs'.
				' WHERE ht.httptestid=hs.httptestid'.
					' AND ('.implode(' OR ', $sql_where).')'
			);

			while ($db_httpstep = DBfetch($db_httpsteps)) {
				$this->db_httpsteps[$db_httpstep['httpstepid']] = [
					'name' => $db_httpstep['name'],
					'hostid' => $db_httpstep['hostid'],
					'httptestid' => $db_httpstep['httptestid']
				];
			}
		}
	}
}
