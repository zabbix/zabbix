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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
	public $interfacesCache = array();
	protected $processedHosts = array();
	protected $groups = array();
	protected $templates = array();
	protected $hosts = array();
	protected $applications = array();
	protected $items = array();
	protected $valueMaps = array();
	protected $triggers = array();
	protected $iconMaps = array();
	protected $maps = array();
	protected $screens = array();
	protected $macros = array();
	protected $proxies = array();
	protected $groupsRefs;
	protected $templatesRefs;
	protected $hostsRefs;
	protected $applicationsRefs;
	protected $itemsRefs;
	protected $valueMapsRefs;
	protected $triggersRefs;
	protected $iconMapsRefs;
	protected $mapsRefs;
	protected $screensRefs;
	protected $macrosRefs;
	protected $proxiesRefs;


	/**
	 * Add host/template that has been updated or created, i.e. all items, discovery rules, etc,
	 * related to these hosts/templates should be created or updated too.
	 *
	 * @param $host
	 */
	public function addProcessedHost($host) {
		$this->processedHosts[$host] = $host;
	}

	/**
	 * Checks if host/template has been created or updated during the current import.
	 *
	 * @param $host
	 *
	 * @return bool
	 */
	public function isProcessedHost($host) {
		return isset($this->processedHosts[$host]);
	}

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
		if (!isset($this->templatesRefs[$host])) {
			$this->selectTemplates($host);
		}
		if (!isset($this->hostsRefs[$host])) {
			$this->selectHosts($host);
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
	 * Get trigger id by trigger name and expression.
	 *
	 * @param string $name
	 * @param string $expression
	 *
	 * @return string|bool
	 */
	public function resolveTrigger($name, $expression) {
		if ($this->triggersRefs === null) {
			$this->selectTriggers();
		}

		return isset($this->triggersRefs[$name][$expression]) ? $this->triggersRefs[$name][$expression] : false;
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
	 * Get macro id by host id and macro name.
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
			$this->selectProxyes();
		}

		return isset($this->proxiesRefs[$name]) ? $this->proxiesRefs[$name] : false;
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
				$this->applications[$host] = array();
			}
			$this->applications[$host] = array_unique(array_merge($this->applications[$host], $apps));
		}
	}

	/**
	 * Add application name association with application id.
	 *
	 * @param string $hostId
	 * @param string $name
	 * @param string $appId
	 */
	public function addApplicationRef($hostId, $name, $appId) {
		$this->applicationsRefs[$hostId][$name] = $appId;
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
				$this->items[$host] = array();
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
	 * Add value map names that need association with a database value map id.
	 *
	 * @param array $valueMaps
	 */
	public function addValueMaps(array $valueMaps) {
		$this->valueMaps = array_unique(array_merge($this->valueMaps, $valueMaps));
	}

	/**
	 * Add trigger names/expressions that need association with a database trigger id.
	 * Input array has format:
	 * array('triggername1' => array('expr1', 'expr2'), 'triggername2' => array('expr1'), ...)
	 *
	 * @param array $triggers
	 */
	public function addTriggers(array $triggers) {
		foreach ($triggers as $name => $expressions) {
			if (!isset($this->triggers[$name])) {
				$this->triggers[$name] = array();
			}
			$this->triggers[$name] = array_unique(array_merge($this->triggers[$name], $expressions));
		}
	}

	/**
	 * Add trigger name/expression association with trigger id.
	 *
	 * @param string $name
	 * @param string $expression
	 * @param string $triggerId
	 */
	public function addTriggerRef($name, $expression, $triggerId) {
		$this->triggersRefs[$name][$expression] = $triggerId;
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
	 * Add screen name association with screen id.
	 *
	 * @param string $name
	 * @param string $screenId
	 */
	public function addScreenRef($name, $screenId) {
		$this->screensRefs[$name] = $screenId;
	}

	/**
	 * Add macros names that need association with a database macro id.
	 *
	 * @param array $macros
	 */
	public function addMacros(array $macros) {
		foreach ($macros as $host => $ms) {
			if (!isset($this->macros[$host])) {
				$this->macros[$host] = array();
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
	 * Select group ids for previously added group names.
	 */
	protected function selectGroups() {
		if (!empty($this->groups)) {
			$this->groupsRefs = array();
			$dbGroups = API::HostGroup()->get(array(
				'filter' => array('name' => $this->groups),
				'output' => array('groupid', 'name'),
				'preservekeys' => true,
				'editable' => true
			));
			foreach ($dbGroups as $group) {
				$this->groupsRefs[$group['name']] = $group['groupid'];
			}

			$this->groups = array();
		}
	}

	/**
	 * Select template ids for previously added template names.
	 */
	protected function selectTemplates($templateName = false) {
		if (!empty($this->templates)) {
			$templateName = $this->templates;
		}
		if (!empty($templateName)) {
			$this->templatesRefs = array();
			$dbTemplates = API::Template()->get(array(
				'filter' => array('host' => $templateName),
				'output' => array('hostid', 'host'),
				'preservekeys' => true,
				'editable' => true
			));
			foreach ($dbTemplates as $template) {
				$this->templatesRefs[$template['host']] = $template['templateid'];
			}

			$this->templates = array();
		}
	}

	/**
	 * Select host ids for previously added host names.
	 */
	protected function selectHosts($hostName = false) {
		if (!empty($this->hosts)) {
			$hostName = $this->hosts;
		}
		if (!empty($hostName)) {
			$this->hostsRefs = array();
			$dbHosts = API::Host()->get(array(
				'filter' => array('host' => $hostName),
				'output' => array('hostid', 'host'),
				'preservekeys' => true,
				'templated_hosts' => true,
				'editable' => true
			));
			foreach ($dbHosts as $host) {
				$this->hostsRefs[$host['host']] = $host['hostid'];
			}

			$this->hosts = array();
		}
	}

	/**
	 * Select application ids for previously added application names.
	 */
	protected function selectApplications() {
		if (!empty($this->applications)) {
			$this->applicationsRefs = array();
			$sqlWhere = array();
			foreach ($this->applications as $host => $applications) {
				$hostId = $this->resolveHostOrTemplate($host);
				if ($hostId) {
					$sqlWhere[] = '(hostid='.$hostId.' AND '.DBcondition('name', $applications).')';
				}
			}

			if ($sqlWhere) {
				$dbApplications = DBselect('SELECT applicationid, hostid, name FROM applications WHERE '.implode(' OR ', $sqlWhere));
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
			$this->itemsRefs = array();

			$sqlWhere = array();
			foreach ($this->items as $host => $keys) {
				$hostId = $this->resolveHostOrTemplate($host);
				if ($hostId) {
					$sqlWhere[] = '(i.hostid='.$hostId.' AND '.DBcondition('i.key_', $keys).')';
				}
			}

			if ($sqlWhere) {
				$dbitems = DBselect('SELECT i.itemid,i.hostid,i.key_ FROM items i WHERE '.implode(' OR ', $sqlWhere));
				while ($dbItem = DBfetch($dbitems)) {
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
	 * Select value map ids for previously added value map names.
	 */
	protected function selectValueMaps() {
		if (!empty($this->valueMaps)) {
			$this->valueMapsRefs = array();

			$dbitems = DBselect('SELECT v.name,v.valuemapid FROM valuemaps v WHERE '.DBcondition('v.name', $this->valueMaps));
			while ($dbItem = DBfetch($dbitems)) {
				$this->valueMapsRefs[$dbItem['name']] = $dbItem['valuemapid'];
			}

			$this->valueMaps = array();
		}
	}

	/**
	 * Select trigger ids for previously added trigger names/expressions.
	 */
	protected function selectTriggers() {
		if (!empty($this->triggers)) {
			$this->triggersRefs = array();

			$triggerIds = array();
			$sql = 'SELECT t.triggerid,t.expression,t.description
				FROM triggers t
				WHERE '.DBcondition('t.description', array_keys($this->triggers));
			$dbTriggers = DBselect($sql);
			while ($dbTrigger = DBfetch($dbTriggers)) {
				$dbExpr = explode_exp($dbTrigger['expression']);
				foreach ($this->triggers as $name => $expressions) {
					if ($name == $dbTrigger['description']) {
						foreach ($expressions as $expression) {
							if ($expression == $dbExpr) {
								$this->triggersRefs[$name][$expression] = $dbTrigger['triggerid'];
								$triggerIds[] = $dbTrigger['triggerid'];
							}
						}
					}
				}
			}

			$allowedTriggers = API::Trigger()->get(array(
				'triggerids' => $triggerIds,
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CHILD)),
				'editable' => true,
				'preservekeys' => true
			));
			foreach ($this->triggersRefs as $name => $expressions) {
				foreach ($expressions as $expression => $triggerId) {
					if (!isset($allowedTriggers[$triggerId])) {
						unset($this->triggersRefs[$name][$expression]);
					}
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
	 * Select icon map ids for previously added icon maps names.
	 */
	protected function selectIconMaps() {
		if (!empty($this->iconMaps)) {
			$this->iconMapsRefs = array();
			$dbIconMaps = API::IconMap()->get(array(
				'filter' => array('name' => $this->iconMaps),
				'output' => array('iconmapid', 'name'),
				'preservekeys' => true,
			));
			foreach ($dbIconMaps as $iconMap) {
				$this->iconMapsRefs[$iconMap['name']] = $iconMap['iconmapid'];
			}

			$this->iconMaps = array();
		}
	}

	/**
	 * Select map ids for previously added maps names.
	 */
	protected function selectMaps() {
		if (!empty($this->maps)) {
			$this->mapsRefs = array();
			$dbMaps = API::Map()->get(array(
				'filter' => array('name' => $this->maps),
				'output' => array('sysmapid', 'name'),
				'preservekeys' => true,
			));
			foreach ($dbMaps as $dbMap) {
				$this->mapsRefs[$dbMap['name']] = $dbMap['sysmapid'];
			}

			$this->maps = array();
		}
	}

	/**
	 * Select screen ids for previously added screen names.
	 */
	protected function selectScreens() {
		if (!empty($this->screens)) {
			$this->screensRefs = array();

			$dbScreens = DBselect('SELECT s.screenid,s.name FROM screens s WHERE'.
					' s.templateid IS NULL '.
					' AND '.DBcondition('s.name', $this->screens));
			while ($dbScreen = DBfetch($dbScreens)) {
				$this->screensRefs[$dbScreen['name']] = $dbScreen['screenid'];
			}

			$this->screens = array();
		}
	}

	/**
	 * Select macro ids for previously added macro names.
	 */
	protected function selectMacros() {
		if (!empty($this->macros)) {
			$this->macrosRefs = array();
			$sqlWhere = array();
			foreach ($this->macros as $host => $macros) {
				$hostId = $this->resolveHostOrTemplate($host);
				if ($hostId) {
					$sqlWhere[] = '(hm.hostid='.$hostId.' AND '.DBcondition('hm.macro', $macros).')';
				}
			}

			if ($sqlWhere) {
				$dbMacros = DBselect('SELECT hm.hostmacroid,hm.hostid,hm.macro FROM hostmacro hm WHERE '.implode(' OR ', $sqlWhere));
				while ($dbMacro = DBfetch($dbMacros)) {
					$this->macrosRefs[$dbMacro['hostid']][$dbMacro['macro']] = $dbMacro['hostmacroid'];
				}
			}

			$this->macros = array();
		}
	}

	/**
	 * Select proxy ids for previously added proxy names.
	 */
	protected function selectProxyes() {
		if (!empty($this->proxies)) {
			$this->proxiesRefs = array();
			$dbProxy = API::Proxy()->get(array(
				'filter' => array('host' => $this->proxies),
				'output' => array('hostid', 'host'),
				'preservekeys' => true,
				'editable' => true
			));
			foreach ($dbProxy as $proxy) {
				$this->proxiesRefs[$proxy['host']] = $proxy['proxyid'];
			}

			$this->proxies = array();
		}
	}
}
