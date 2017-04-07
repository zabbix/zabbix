<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
	protected $applications = [];
	protected $items = [];
	protected $valueMaps = [];
	protected $triggers = [];
	protected $graphs = [];
	protected $iconMaps = [];
	protected $maps = [];
	protected $screens = [];
	protected $templateScreens = [];
	protected $macros = [];
	protected $proxies = [];
	protected $hostPrototypes = [];
	protected $httptests = [];
	protected $httpsteps = [];
	protected $groupsRefs;
	protected $templatesRefs;
	protected $hostsRefs;
	protected $applicationsRefs;
	protected $itemsRefs;
	protected $valueMapsRefs;
	protected $triggersRefs;
	protected $graphsRefs;
	protected $iconMapsRefs;
	protected $mapsRefs;
	protected $screensRefs;
	protected $templateScreensRefs;
	protected $macrosRefs;
	protected $proxiesRefs;
	protected $hostPrototypesRefs;
	protected $httptestsRefs;
	protected $httpstepsRefs;

	/**
	 * Get group id by name.
	 *
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveGroup($name) {
		if ($this->groupsRefs === null) {
			$this->selectGroups();
		}

		return isset($this->groupsRefs[$name]) ? $this->groupsRefs[$name] : false;
	}

	/**
	 * Get host id by host.
	 *
	 * @param string $host
	 *
	 * @return string|bool
	 */
	public function resolveHost($host) {
		if ($this->hostsRefs === null) {
			$this->selectHosts();
		}

		return isset($this->hostsRefs[$host]) ? $this->hostsRefs[$host] : false;
	}

	/**
	 * Get template id by host.
	 *
	 * @param string $host
	 *
	 * @return string|bool
	 */
	public function resolveTemplate($host) {
		if ($this->templatesRefs === null) {
			$this->selectTemplates();
		}

		return isset($this->templatesRefs[$host]) ? $this->templatesRefs[$host] : false;
	}

	/**
	 * Get host or template id by host.
	 *
	 * @param string $host
	 *
	 * @return string|bool
	 */
	public function resolveHostOrTemplate($host) {
		if ($this->templatesRefs === null) {
			$this->selectTemplates();
		}
		if ($this->hostsRefs === null) {
			$this->selectHosts();
		}

		if (isset($this->templatesRefs[$host])) {
			return $this->templatesRefs[$host];
		}
		elseif (isset($this->hostsRefs[$host])) {
			return $this->hostsRefs[$host];
		}
		else {
			return false;
		}
	}

	/**
	 * Get application id by host id and application name.
	 *
	 * @param string $hostid
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveApplication($hostid, $name) {
		if ($this->applicationsRefs === null) {
			$this->selectApplications();
		}

		return isset($this->applicationsRefs[$hostid][$name]) ? $this->applicationsRefs[$hostid][$name] : false;
	}

	/**
	 * Get item id by host id and item key_.
	 *
	 * @param string $hostid
	 * @param string $key
	 *
	 * @return string|bool
	 */
	public function resolveItem($hostid, $key) {
		if ($this->itemsRefs === null) {
			$this->selectItems();
		}

		return isset($this->itemsRefs[$hostid][$key]) ? $this->itemsRefs[$hostid][$key] : false;
	}

	/**
	 * Get value map id by vale map name.
	 *
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveValueMap($name) {
		if ($this->valueMapsRefs === null) {
			$this->selectValueMaps();
		}

		return isset($this->valueMapsRefs[$name]) ? $this->valueMapsRefs[$name] : false;
	}

	/**
	 * Get trigger ID by trigger name and expression.
	 *
	 * @param string $name
	 * @param string $expression
	 * @param string $recovery_expression
	 *
	 * @return string|bool
	 */
	public function resolveTrigger($name, $expression, $recovery_expression) {
		if ($this->triggersRefs === null) {
			$this->selectTriggers();
		}

		return array_key_exists($name, $this->triggersRefs)
				&& array_key_exists($expression, $this->triggersRefs[$name])
				&& array_key_exists($recovery_expression, $this->triggersRefs[$name][$expression])
			? $this->triggersRefs[$name][$expression][$recovery_expression]
			: false;
	}

	/**
	 * Get graph ID by host ID and graph name.
	 *
	 * @param string $hostId
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveGraph($hostId, $name) {
		if ($this->graphsRefs === null) {
			$this->selectGraphs();
		}

		return isset($this->graphsRefs[$hostId][$name]) ? $this->graphsRefs[$hostId][$name] : false;
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
	 * Get screen id by name.
	 *
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveScreen($name) {
		if ($this->screensRefs === null) {
			$this->selectScreens();
		}

		return isset($this->screensRefs[$name]) ? $this->screensRefs[$name] : false;
	}

	/**
	 * Get templated screen ID by template ID and screen name.
	 *
	 * @param string $templateId
	 * @param string $screenName
	 *
	 * @return string|bool
	 */
	public function resolveTemplateScreen($templateId, $screenName) {
		if ($this->templateScreensRefs === null) {
			$this->selectTemplateScreens();
		}

		return isset($this->templateScreensRefs[$templateId][$screenName])
			? $this->templateScreensRefs[$templateId][$screenName]
			: false;
	}

	/**
	 * Get macro ID by host ID and macro name.
	 *
	 * @param string $hostid
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveMacro($hostid, $name) {
		if ($this->macrosRefs === null) {
			$this->selectMacros();
		}

		return isset($this->macrosRefs[$hostid][$name]) ? $this->macrosRefs[$hostid][$name] : false;
	}

	/**
	 * Get proxy id by name.
	 *
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveProxy($name) {
		if ($this->proxiesRefs === null) {
			$this->selectProxies();
		}

		return isset($this->proxiesRefs[$name]) ? $this->proxiesRefs[$name] : false;
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
	 * Get httptestid by hostid and web scenario name.
	 *
	 * @param string $hostid
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveHttpTest($hostid, $name) {
		if ($this->httptestsRefs === null) {
			$this->selectHttpTests();
		}

		return array_key_exists($hostid, $this->httptestsRefs) && array_key_exists($name, $this->httptestsRefs[$hostid])
			? $this->httptestsRefs[$hostid][$name]
			: false;
	}

	/**
	 * Get httpstepid by hostid, httptestid and web scenario step name.
	 *
	 * @param string $hostid
	 * @param string $httptestid
	 * @param string $name
	 *
	 * @return string|bool
	 */
	public function resolveHttpStep($hostid, $httptestid, $name) {
		if ($this->httpstepsRefs === null) {
			$this->selectHttpSteps();
		}

		if (!array_key_exists($hostid, $this->httpstepsRefs)) {
			return false;
		}

		if (!array_key_exists($httptestid, $this->httpstepsRefs[$hostid])) {
			return false;
		}

		if (!array_key_exists($name, $this->httpstepsRefs[$hostid][$httptestid])) {
			return false;
		}

		return $this->httpstepsRefs[$hostid][$httptestid][$name];
	}

	/**
	 * Add group names that need association with a database group id.
	 *
	 * @param array $groups
	 */
	public function addGroups(array $groups) {
		$this->groups = array_unique(array_merge($this->groups, $groups));
	}

	/**
	 * Add group name association with group id.
	 *
	 * @param string $name
	 * @param string $id
	 */
	public function addGroupRef($name, $id) {
		$this->groupsRefs[$name] = $id;
	}

	/**
	 * Add templates names that need association with a database template id.
	 *
	 * @param array $templates
	 */
	public function addTemplates(array $templates) {
		$this->templates = array_unique(array_merge($this->templates, $templates));
	}

	/**
	 * Add template name association with template id.
	 *
	 * @param string $name
	 * @param string $id
	 */
	public function addTemplateRef($name, $id) {
		$this->templatesRefs[$name] = $id;
	}

	/**
	 * Add hosts names that need association with a database host id.
	 *
	 * @param array $hosts
	 */
	public function addHosts(array $hosts) {
		$this->hosts = array_unique(array_merge($this->hosts, $hosts));
	}

	/**
	 * Add host name association with host id.
	 *
	 * @param string $host
	 * @param string $id
	 */
	public function addHostRef($host, $id) {
		$this->hostsRefs[$host] = $id;
	}

	/**
	 * Add application names that need association with a database application id.
	 * Input array has format:
	 * array('hostname1' => array('appname1', 'appname2'), 'hostname2' => array('appname1'), ...)
	 *
	 * @param array $applications
	 */
	public function addApplications(array $applications) {
		foreach ($applications as $host => $apps) {
			if (!isset($this->applications[$host])) {
				$this->applications[$host] = [];
			}
			$this->applications[$host] = array_unique(array_merge($this->applications[$host], $apps));
		}
	}

	/**
	 * Add item keys that need association with a database item id.
	 * Input array has format:
	 * array('hostname1' => array('itemkey1', 'itemkey2'), 'hostname2' => array('itemkey1'), ...)
	 *
	 * @param array $items
	 */
	public function addItems(array $items) {
		foreach ($items as $host => $keys) {
			if (!isset($this->items[$host])) {
				$this->items[$host] = [];
			}
			$this->items[$host] = array_unique(array_merge($this->items[$host], $keys));
		}
	}

	/**
	 * Add item key association with item id.
	 *
	 * @param string $hostId
	 * @param string $key
	 * @param string $itemId
	 */
	public function addItemRef($hostId, $key, $itemId) {
		$this->itemsRefs[$hostId][$key] = $itemId;
	}

	/**
	 * Add value map association with valuemap ID.
	 *
	 * @param string $name
	 * @param string $valuemapid
	 */
	public function addValueMapRef($name, $valuemapid) {
		$this->valueMapsRefs[$name] = $valuemapid;
	}

	/**
	 * Add value map names that need association with a database value map ID.
	 *
	 * @param array $valueMaps
	 */
	public function addValueMaps(array $valueMaps) {
		$this->valueMaps = array_unique(array_merge($this->valueMaps, $valueMaps));
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
		foreach ($triggers as $description => $expressions) {
			if (!array_key_exists($description, $this->triggers)) {
				$this->triggers[$description] = [];
			}

			foreach ($expressions as $expression => $recovery_expressions) {
				if (!array_key_exists($expression, $this->triggers[$description])) {
					$this->triggers[$description][$expression] = [];
				}

				foreach ($recovery_expressions as $recovery_expression => $foo) {
					if (!array_key_exists($recovery_expression, $this->triggers[$description][$expression])) {
						$this->triggers[$description][$expression][$recovery_expression] = true;
					}
				}
			}
		}
	}

	/**
	 * Add graph names that need association with a database graph ID.
	 * Input array has format:
	 * array('hostname1' => array('graphname1', 'graphname2'), 'hostname2' => array('graphname1'), ...)
	 *
	 * @param array $graphs
	 */
	public function addGraphs(array $graphs) {
		foreach ($graphs as $host => $hostGraphs) {
			if (!isset($this->graphs[$host])) {
				$this->graphs[$host] = [];
			}
			$this->graphs[$host] = array_unique(array_merge($this->graphs[$host], $hostGraphs));
		}
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
		$this->triggersRefs[$name][$expression][$recovery_expression] = $triggerid;
	}

	/**
	 * Add icon map names that need association with a database icon map id.
	 *
	 * @param array $iconMaps
	 */
	public function addIconMaps(array $iconMaps) {
		$this->iconMaps = array_unique(array_merge($this->iconMaps, $iconMaps));
	}

	/**
	 * Add map names that need association with a database map id.
	 *
	 * @param array $maps
	 */
	public function addMaps(array $maps) {
		$this->maps = array_unique(array_merge($this->maps, $maps));
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
	 * Add screens names that need association with a database screen id.
	 *
	 * @param array $screens
	 */
	public function addScreens(array $screens) {
		$this->screens = array_unique(array_merge($this->screens, $screens));
	}

	/**
	 * Add templated screen names that need association with a database screen id.
	 *
	 * @param array $screens
	 */
	public function addTemplateScreens(array $screens) {
		$this->templateScreens = array_unique(array_merge($this->templateScreens, $screens));
	}

	/**
	 * Add screen name association with screen id.
	 *
	 * @param string $name
	 * @param string $screenId
	 */
	public function addScreenRef($name, $screenId) {
		$this->screensRefs[$name] = $screenId;
	}

	/**
	 * Add template screen name association with template screen ID.
	 *
	 * @param string $screenName
	 * @param string $templateScreenId
	 */
	public function addTemplateScreenRef($screenName, $templateScreenId) {
		$this->templateScreensRefs[$screenName] = $templateScreenId;
	}

	/**
	 * Add macros names that need association with a database macro id.
	 *
	 * @param array $macros
	 */
	public function addMacros(array $macros) {
		foreach ($macros as $host => $ms) {
			if (!isset($this->macros[$host])) {
				$this->macros[$host] = [];
			}
			$this->macros[$host] = array_unique(array_merge($this->macros[$host], $ms));
		}
	}

	/**
	 * Add macro name association with macro id.
	 *
	 * @param string $hostId
	 * @param string $macro
	 * @param string $macroId
	 */
	public function addMacroRef($hostId, $macro, $macroId) {
		$this->macrosRefs[$hostId][$macro] = $macroId;
	}

	/**
	 * Add proxy names that need association with a database proxy id.
	 *
	 * @param array $proxies
	 */
	public function addProxies(array $proxies) {
		$this->proxies = array_unique(array_merge($this->proxies, $proxies));
	}

	/**
	 * Add proxy name association with proxy id.
	 *
	 * @param string $name
	 * @param string $proxyId
	 */
	public function addProxyRef($name, $proxyId) {
		$this->proxiesRefs[$name] = $proxyId;
	}

	/**
	 * Add host prototypes that need association with a database host prototype id.
	 *
	 * @param array $hostPrototypes
	 */
	public function addHostPrototypes(array $hostPrototypes) {
		foreach ($hostPrototypes as $host => $discoveryRule) {
			if (!isset($this->hostPrototypes[$host])) {
				$this->hostPrototypes[$host] = [];
			}
			foreach ($discoveryRule as $discoveryRuleKey => $hostPrototypes) {
				if (!isset($this->hostPrototypes[$host][$discoveryRuleKey])) {
					$this->hostPrototypes[$host][$discoveryRuleKey] = [];
				}
				$this->hostPrototypes[$host][$discoveryRuleKey] = array_unique(
					array_merge($this->hostPrototypes[$host][$discoveryRuleKey], $hostPrototypes)
				);
			}
		}
	}

	/**
	 * Add web scenario names that need association with a database httptestid.
	 *
	 * @param array  $httptests
	 * @param string $httptests[<host>][]	web scenario name
	 */
	public function addHttpTests(array $httptests) {
		foreach ($httptests as $host => $names) {
			if (!array_key_exists($host, $this->httptests)) {
				$this->httptests[$host] = [];
			}

			$this->httptests[$host] = array_unique(array_merge($this->httptests[$host], $names));
		}
	}

	/**
	 * Add web scenario step names that need association with a database httpstepid.
	 *
	 * @param array  $httpsteps
	 * @param string $httpsteps[<host>][<httptest_name>][]	web scenario step name
	 */
	public function addHttpSteps(array $httpsteps) {
		foreach ($httpsteps as $host => $httptests) {
			if (!array_key_exists($host, $this->httpsteps)) {
				$this->httpsteps[$host] = [];
			}

			foreach ($httptests as $httptest_name => $httpstep_names) {
				if (!array_key_exists($httptest_name, $this->httpsteps[$host])) {
					$this->httpsteps[$host][$httptest_name] = [];
				}

				$this->httpsteps[$host][$httptest_name] =
					array_unique(array_merge($this->httpsteps[$host][$httptest_name], $httpstep_names));
			}
		}
	}

	/**
	 * Select group ids for previously added group names.
	 */
	protected function selectGroups() {
		if (!empty($this->groups)) {
			$this->groupsRefs = [];
			$dbGroups = API::HostGroup()->get([
				'filter' => ['name' => $this->groups],
				'output' => ['groupid', 'name'],
				'preservekeys' => true
			]);
			foreach ($dbGroups as $group) {
				$this->groupsRefs[$group['name']] = $group['groupid'];
			}

			$this->groups = [];
		}
	}

	/**
	 * Select template ids for previously added template names.
	 */
	protected function selectTemplates() {
		if (!empty($this->templates)) {
			$this->templatesRefs = [];
			$dbTemplates = API::Template()->get([
				'output' => ['host', 'templateid'],
				'preservekeys' => true,
				'editable' => true,
				'filter' => ['host' => $this->templates]
			]);
			foreach ($dbTemplates as $template) {
				$this->templatesRefs[$template['host']] = $template['templateid'];
			}

			$this->templates = [];
		}
	}

	/**
	 * Select host ids for previously added host names.
	 */
	protected function selectHosts() {
		if (!empty($this->hosts)) {
			$this->hostsRefs = [];
			// fetch only normal hosts, discovered hosts must not be imported
			$dbHosts = API::Host()->get([
				'filter' => ['host' => $this->hosts],
				'output' => ['hostid', 'host'],
				'preservekeys' => true,
				'templated_hosts' => true
			]);
			foreach ($dbHosts as $host) {
				$this->hostsRefs[$host['host']] = $host['hostid'];
			}

			$this->hosts = [];
		}
	}

	/**
	 * Select application ids for previously added application names.
	 */
	protected function selectApplications() {
		if (!empty($this->applications)) {
			$this->applicationsRefs = [];
			$sqlWhere = [];

			foreach ($this->applications as $host => $applications) {
				$hostId = $this->resolveHostOrTemplate($host);
				if ($hostId) {
					$sqlWhere[] = '(a.hostid='.zbx_dbstr($hostId).' AND '.
						dbConditionString('a.name', $applications).')';
				}
			}

			if ($sqlWhere) {
				$dbApplications = DBselect(
					'SELECT a.applicationid,a.hostid,a.name'.
					' FROM applications a'.
					' WHERE '.implode(' OR ', $sqlWhere).
						' AND a.flags='.ZBX_FLAG_DISCOVERY_NORMAL
				);
				while ($dbApplication = DBfetch($dbApplications)) {
					$this->applicationsRefs[$dbApplication['hostid']][$dbApplication['name']] = $dbApplication['applicationid'];
				}
			}
		}
	}

	/**
	 * Unset application refs to make referencer select them from db again.
	 */
	public function refreshApplications() {
		$this->applicationsRefs = null;
	}

	/**
	 * Select item ids for previously added item keys.
	 */
	protected function selectItems() {
		if (!empty($this->items)) {
			$this->itemsRefs = [];

			$sqlWhere = [];
			foreach ($this->items as $host => $keys) {
				$hostId = $this->resolveHostOrTemplate($host);
				if ($hostId) {
					$sqlWhere[] = '(i.hostid='.zbx_dbstr($hostId).' AND '.dbConditionString('i.key_', $keys).')';
				}
			}

			if ($sqlWhere) {
				$dbItems = DBselect('SELECT i.itemid,i.hostid,i.key_ FROM items i WHERE '.implode(' OR ', $sqlWhere));
				while ($dbItem = DBfetch($dbItems)) {
					$this->itemsRefs[$dbItem['hostid']][$dbItem['key_']] = $dbItem['itemid'];
				}
			}
		}
	}

	/**
	 * Unset item refs to make referencer select them from db again.
	 */
	public function refreshItems() {
		$this->itemsRefs = null;
	}

	/**
	 * Select value map IDs for previously added value map names.
	 */
	protected function selectValueMaps() {
		if ($this->valueMaps) {
			$this->valueMapsRefs = [];

			$valuemaps = API::ValueMap()->get([
				'output' => ['valeumapid', 'name'],
				'filter' => ['name' => $this->valueMaps]
			]);

			foreach ($valuemaps as $valuemap) {
				$this->valueMapsRefs[$valuemap['name']] = $valuemap['valuemapid'];
			}

			$this->valueMaps = [];
		}
	}

	/**
	 * Select trigger ids for previously added trigger names/expressions.
	 */
	protected function selectTriggers() {
		if ($this->triggers) {
			$this->triggersRefs = [];

			$dbTriggers = API::Trigger()->get([
				'output' => ['triggerid', 'expression', 'description', 'recovery_expression'],
				'filter' => [
					'description' => array_keys($this->triggers),
					'flags' => [
						ZBX_FLAG_DISCOVERY_NORMAL,
						ZBX_FLAG_DISCOVERY_PROTOTYPE,
						ZBX_FLAG_DISCOVERY_CREATED
					]
				]
			]);

			$dbTriggers = CMacrosResolverHelper::resolveTriggerExpressions($dbTriggers,
				['sources' => ['expression', 'recovery_expression']]
			);

			foreach ($dbTriggers as $dbTrigger) {
				$description = $dbTrigger['description'];
				$expression = $dbTrigger['expression'];
				$recovery_expression = $dbTrigger['recovery_expression'];

				if (array_key_exists($description, $this->triggers)
						&& array_key_exists($expression, $this->triggers[$description])
						&& array_key_exists($recovery_expression, $this->triggers[$description][$expression])) {
					$this->triggersRefs[$description][$expression][$recovery_expression] = $dbTrigger['triggerid'];
				}
			}
		}
	}

	/**
	 * Select graph IDs for previously added graph names.
	 */
	protected function selectGraphs() {
		if ($this->graphs) {
			$this->graphsRefs = [];

			$graphNames = [];

			foreach ($this->graphs as $graphs) {
				foreach ($graphs as $graph) {
					$graphNames[$graph] = $graph;
				}
			}

			$dbGraphs = API::Graph()->get([
				'output' => ['graphid', 'name'],
				'selectHosts' => ['hostid'],
				'filter' => [
					'name' => $graphNames,
					'flags' => null
				]
			]);

			foreach ($dbGraphs as $dbGraph) {
				foreach ($dbGraph['hosts'] as $host) {
					$this->graphsRefs[$host['hostid']][$dbGraph['name']] = $dbGraph['graphid'];
				}
			}
		}
	}

	/**
	 * Unset trigger refs to make referencer select them from db again.
	 */
	public function refreshTriggers() {
		$this->triggersRefs = null;
	}

	/**
	 * Unset graph refs to make referencer select them from DB again.
	 */
	public function refreshGraphs() {
		$this->graphsRefs = null;
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
	 * Select screen ids for previously added screen names.
	 */
	protected function selectScreens() {
		if (!empty($this->screens)) {
			$this->screensRefs = [];

			$db_screens = API::Screen()->get([
				'filter' => ['name' => $this->screens],
				'output' => ['screenid', 'name']
			]);
			foreach ($db_screens as $db_screen) {
				$this->screensRefs[$db_screen['name']] = $db_screen['screenid'];
			}

			$this->screens = [];
		}
	}

	/**
	 * Select template screen IDs for previously added screen names and template IDs.
	 */
	protected function selectTemplateScreens() {
		if ($this->templateScreens) {
			$this->templateScreensRefs = [];

			$db_template_screens = API::TemplateScreen()->get([
				'filter' => ['name' => $this->templateScreens],
				'output' => ['screenid', 'name', 'templateid']
			]);
			foreach ($db_template_screens as $screen) {
				$this->templateScreensRefs[$screen['templateid']][$screen['name']] = $screen['screenid'];
			}

			$this->templateScreens = [];
		}
	}

	/**
	 * Select macro ids for previously added macro names.
	 */
	protected function selectMacros() {
		if (!empty($this->macros)) {
			$this->macrosRefs = [];
			$sqlWhere = [];
			foreach ($this->macros as $host => $macros) {
				$hostId = $this->resolveHostOrTemplate($host);
				if ($hostId) {
					$sqlWhere[] = '(hm.hostid='.zbx_dbstr($hostId).' AND '.dbConditionString('hm.macro', $macros).')';
				}
			}

			if ($sqlWhere) {
				$dbMacros = DBselect('SELECT hm.hostmacroid,hm.hostid,hm.macro FROM hostmacro hm WHERE '.implode(' OR ', $sqlWhere));
				while ($dbMacro = DBfetch($dbMacros)) {
					$this->macrosRefs[$dbMacro['hostid']][$dbMacro['macro']] = $dbMacro['hostmacroid'];
				}
			}

			$this->macros = [];
		}
	}

	/**
	 * Select proxy ids for previously added proxy names.
	 */
	protected function selectProxies() {
		if (!empty($this->proxies)) {
			$this->proxiesRefs = [];
			$dbProxy = API::Proxy()->get([
				'filter' => ['host' => $this->proxies],
				'output' => ['hostid', 'host'],
				'preservekeys' => true
			]);
			foreach ($dbProxy as $proxy) {
				$this->proxiesRefs[$proxy['host']] = $proxy['proxyid'];
			}

			$this->proxies = [];
		}
	}

	/**
	 * Select host prototype ids for previously added host prototypes names.
	 */
	protected function selectHostPrototypes() {
		if (!empty($this->hostPrototypes)) {
			$this->hostPrototypesRefs = [];
			$sqlWhere = [];
			foreach ($this->hostPrototypes as $host => $discoveryRule) {
				$hostId = $this->resolveHostOrTemplate($host);

				foreach ($discoveryRule as $discoveryRuleKey => $hostPrototypes) {
					$discoveryRuleId = $this->resolveItem($hostId, $discoveryRuleKey);
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
						' AND '.implode(' OR ', $sqlWhere)
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
	protected function selectHttpTests() {
		if ($this->httptests) {
			$this->httptestsRefs = [];

			$sql_where = [];

			foreach ($this->httptests as $host => $names) {
				$hostid = $this->resolveHostOrTemplate($host);

				if ($hostid !== false) {
					$sql_where[] = '(ht.hostid='.zbx_dbstr($hostid).' AND '.dbConditionString('ht.name', $names).')';
				}
			}

			if ($sql_where) {
				$db_httptests = DBselect(
					'SELECT ht.hostid,ht.name,ht.httptestid'.
					' FROM httptest ht'.
					' WHERE '.implode(' OR ', $sql_where)
				);
				while ($db_httptest = DBfetch($db_httptests)) {
					$this->httptestsRefs[$db_httptest['hostid']][$db_httptest['name']] = $db_httptest['httptestid'];
				}
			}
		}
	}

	/**
	 * Unset web scenario refs to make referencer select them from db again.
	 */
	public function refreshHttpTests() {
		$this->httptestsRefs = null;
	}

	/**
	 * Select httpstepids for previously added web scenario step names.
	 */
	protected function selectHttpSteps() {
		if ($this->httpsteps) {
			$this->httpstepsRefs = [];

			$sql_where = [];

			foreach ($this->httpsteps as $host => $httptests) {
				$hostid = $this->resolveHostOrTemplate($host);

				if ($hostid !== false) {
					foreach ($httptests as $httptest_name => $httpstep_names) {
						$httptestid = $this->resolveHttpTest($hostid, $httptest_name);

						if ($httptestid !== false) {
							$sql_where[] = '(hs.httptestid='.zbx_dbstr($httptestid).
								' AND '.dbConditionString('hs.name', $httpstep_names).')';
						}
					}
				}
			}

			if ($sql_where) {
				$db_httpsteps = DBselect(
					'SELECT ht.hostid,hs.httptestid,hs.name,hs.httpstepid'.
					' FROM httptest ht,httpstep hs'.
					' WHERE ht.httptestid=hs.httptestid'.
						' AND '.implode(' OR ', $sql_where)
				);
				while ($db_httpstep = DBfetch($db_httpsteps)) {
					$this->httpstepsRefs[$db_httpstep['hostid']][$db_httpstep['httptestid']][$db_httpstep['name']] =
						$db_httpstep['httpstepid'];
				}
			}
		}
	}
}
