<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	public $interfaces_cache = [];

	protected $template_groups = [];
	protected $host_groups = [];
	protected $templates = [];
	protected $hosts = [];
	protected $items = [];
	protected $valuemaps = [];
	protected $triggers = [];
	protected $graphs = [];
	protected $iconmaps = [];
	protected $images = [];
	protected $maps = [];
	protected $template_dashboards = [];
	protected $template_macros = [];
	protected $host_macros = [];
	protected $host_prototype_macros = [];
	protected $proxies = [];
	protected $host_prototypes = [];
	protected $httptests = [];
	protected $httpsteps = [];

	protected $db_template_groups;
	protected $db_host_groups;
	protected $db_templates;
	protected $db_hosts;
	protected $db_items;
	protected $db_valuemaps;
	protected $db_triggers;
	protected $db_graphs;
	protected $db_iconmaps;
	protected $db_images;
	protected $db_maps;
	protected $db_template_dashboards;
	protected $db_template_macros;
	protected $db_host_macros;
	protected $db_host_prototype_macros;
	protected $db_proxies;
	protected $db_host_prototypes;
	protected $db_httptests;
	protected $db_httpsteps;

	/**
	 * Get template group ID by group UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findTemplateGroupidByUuid(string $uuid): ?string {
		if ($this->db_template_groups === null) {
			$this->selectTemplateGroups();
		}

		foreach ($this->db_template_groups as $groupid => $group) {
			if ($group['uuid'] === $uuid) {
				return $groupid;
			}
		}

		return null;
	}

	/**
	 * Get host group ID by group UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findHostGroupidByUuid(string $uuid): ?string {
		if ($this->db_host_groups === null) {
			$this->selectHostGroups();
		}

		foreach ($this->db_host_groups as $groupid => $group) {
			if ($group['uuid'] === $uuid) {
				return $groupid;
			}
		}

		return null;
	}

	/**
	 * Get template group ID by group name.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findTemplateGroupidByName(string $name): ?string {
		if ($this->db_template_groups === null) {
			$this->selectTemplateGroups();
		}

		foreach ($this->db_template_groups as $groupid => $group) {
			if ($group['name'] === $name) {
				return $groupid;
			}
		}

		return null;
	}

	/**
	 * Get host group ID by group name.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findHostGroupidByName(string $name): ?string {
		if ($this->db_host_groups === null) {
			$this->selectHostGroups();
		}

		foreach ($this->db_host_groups as $groupid => $group) {
			if ($group['name'] === $name) {
				return $groupid;
			}
		}

		return null;
	}

	/**
	 * Get template ID by group UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
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

	/**
	 * Get template ID by template host.
	 *
	 * @param string $host
	 *
	 * @return string|null
	 */
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
	 * Get host ID by host.
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
	 * Get host ID or template ID by host.
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
	 * Get interface ID by host ID and interface reference.
	 *
	 * @param string $hostid
	 * @param string $interface_ref
	 *
	 * @return string|null
	 */
	public function findInterfaceidByRef(string $hostid, string $interface_ref): ?string {
		if (array_key_exists($hostid, $this->interfaces_cache)
				&& array_key_exists($interface_ref, $this->interfaces_cache[$hostid])) {
			return $this->interfaces_cache[$hostid][$interface_ref];
		}

		return null;
	}

	/**
	 * Initializes references for items.
	 */
	public function initItemsReferences(): void {
		if ($this->db_items === null) {
			$this->selectItems();
		}
	}

	/**
	 * Get item ID by uuid.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findItemidByUuid(string $uuid): ?string {
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
	 * Get item ID by host ID and item key_.
	 *
	 * @param string $hostid
	 * @param string $key
	 *
	 * @return string|null
	 */
	public function findItemidByKey(string $hostid, string $key): ?string {
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
	 * Get valuemap ID by valuemap name.
	 *
	 * @param string $hostid
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findValuemapidByName(string $hostid, string $name): ?string {
		if ($this->db_valuemaps === null) {
			$this->selectValuemaps();
		}

		foreach ($this->db_valuemaps as $valuemapid => $valuemap) {
			if ($valuemap['hostid'] === $hostid && $valuemap['name'] === $name) {
				return $valuemapid;
			}
		}

		return null;
	}

	/**
	 * Get image ID by image name.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findImageidByName(string $name): ?string {
		if ($this->db_images === null) {
			$this->selectImages();
		}

		foreach ($this->db_images as $imageid => $image) {
			if ($image['name'] === $name) {
				return $imageid;
			}
		}

		return null;
	}

	/**
	 * Get trigger by trigger ID.
	 *
	 * @param string $triggerid
	 *
	 * @return array|null
	 */
	public function findTriggerById(string $triggerid): ?array {
		if ($this->db_triggers === null) {
			$this->selectTriggers();
		}

		if (array_key_exists($triggerid, $this->db_triggers)) {
			return $this->db_triggers[$triggerid];
		}

		return null;
	}

	/**
	 * Get trigger ID by trigger UUID.
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
	 * Get graph ID by UUID.
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
	 * Get iconmap ID by name.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findIconmapidByName(string $name): ?string {
		if ($this->db_iconmaps === null) {
			$this->selectIconmaps();
		}

		foreach ($this->db_iconmaps as $iconmapid => $iconmap) {
			if ($iconmap['name'] === $name) {
				return $iconmapid;
			}
		}

		return null;
	}

	/**
	 * Get map ID by name.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function findMapidByName(string $name): ?string {
		if ($this->db_maps === null) {
			$this->selectMaps();
		}

		foreach ($this->db_maps as $mapid => $map) {
			if ($map['name'] === $name) {
				return $mapid;
			}
		}

		return null;
	}

	/**
	 * Get template dashboard ID by dashboard UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 *
	 * @throws APIException
	 */
	public function findTemplateDashboardidByUuid(string $uuid): ?string {
		if ($this->db_template_dashboards === null) {
			$this->selectTemplateDashboards();
		}

		foreach ($this->db_template_dashboards as $dashboardid => $dashboard) {
			if ($dashboard['uuid'] === $uuid) {
				return $dashboardid;
			}
		}

		return null;
	}

	/**
	 * Get macro ID by template ID and macro name.
	 *
	 * @param string $templateid
	 * @param string $macro
	 *
	 * @return string|null
	 */
	public function findTemplateMacroid(string $templateid, string $macro): ?string {
		if ($this->db_template_macros === null) {
			$this->selectTemplateMacros();
		}

		return (array_key_exists($templateid, $this->db_template_macros)
				&& array_key_exists($macro, $this->db_template_macros[$templateid]))
			? $this->db_template_macros[$templateid][$macro]
			: null;
	}

	/**
	 * Get macro ID by host ID and macro name.
	 *
	 * @param string $hostid
	 * @param string $macro
	 *
	 * @return string|null
	 */
	public function findHostMacroid(string $hostid, string $macro): ?string {
		if ($this->db_host_macros === null) {
			$this->selectHostMacros();
		}

		return (array_key_exists($hostid, $this->db_host_macros)
				&& array_key_exists($macro, $this->db_host_macros[$hostid]))
			? $this->db_host_macros[$hostid][$macro]
			: null;
	}

	/**
	 * Get macro ID by host prototype ID and macro name.
	 *
	 * @param string $hostid
	 * @param string $macro
	 *
	 * @return string|null
	 */
	public function findHostPrototypeMacroid(string $hostid, string $macro): ?string {
		if ($this->db_host_prototype_macros === null) {
			$this->selectHostPrototypeMacros();
		}

		return (array_key_exists($hostid, $this->db_host_prototype_macros)
				&& array_key_exists($macro, $this->db_host_prototype_macros[$hostid]))
			? $this->db_host_prototype_macros[$hostid][$macro]
			: null;
	}

	/**
	 * Get proxy ID by name.
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
	 * Get host prototype ID by UUID.
	 *
	 * @param string $uuid
	 *
	 * @return string|null
	 */
	public function findHostPrototypeidByUuid(string $uuid): ?string {
		if ($this->db_host_prototypes === null) {
			$this->selectHostPrototypes();
		}

		foreach ($this->db_host_prototypes as $host_prototypeid => $host_prototype) {
			if ($host_prototype['uuid'] === $uuid) {
				return $host_prototypeid;
			}
		}

		return null;
	}

	/**
	 * Get host prototype ID by host.
	 *
	 * @param string $parent_hostid
	 * @param string $discovery_ruleid
	 * @param string $host
	 *
	 * @return string|null
	 */
	public function findHostPrototypeidByHost(string $parent_hostid, string $discovery_ruleid, string $host): ?string {
		if ($this->db_host_prototypes === null) {
			$this->selectHostPrototypes();
		}

		foreach ($this->db_host_prototypes as $host_prototypeid => $host_prototype) {
			if ($host_prototype['parent_hostid'] === $parent_hostid
					&& $host_prototype['discovery_ruleid'] === $discovery_ruleid
					&& $host_prototype['host'] === $host) {
				return $host_prototypeid;
			}
		}

		return null;
	}

	/**
	 * Get httptest ID by web scenario UUID.
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
	 * Get httptest ID by hostid and web scenario name.
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
	 * Get httpstep ID by hostid, httptestid and web scenario step name.
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
	 * Add template group names that need association with a database group ID.
	 *
	 * @param array $groups
	 */
	public function addTemplateGroups(array $groups): void {
		$this->template_groups = $groups;
	}

	/**
	 * Add host group names that need association with a database group ID.
	 *
	 * @param array $groups
	 */
	public function addHostGroups(array $groups): void {
		$this->host_groups = $groups;
	}

	/**
	 * Add template group name association with group ID.
	 *
	 * @param string $groupid
	 * @param array  $group
	 */
	public function setDbTemplateGroup(string $groupid, array $group): void {
		$this->db_template_groups[$groupid] = [
			'uuid' => $group['uuid'],
			'name' => $group['name']
		];
	}

	/**
	 * Add host group name association with group ID.
	 *
	 * @param string $groupid
	 * @param array  $group
	 */
	public function setDbHostGroup(string $groupid, array $group): void {
		$this->db_host_groups[$groupid] = [
			'uuid' => $group['uuid'],
			'name' => $group['name']
		];
	}

	/**
	 * Add templates names that need association with a database template ID.
	 *
	 * @param array $templates
	 */
	public function addTemplates(array $templates): void {
		$this->templates = $templates;
	}

	/**
	 * Add template name association with template ID.
	 *
	 * @param string $templateid
	 * @param array  $template
	 */
	public function setDbTemplate(string $templateid, array $template): void {
		$this->db_templates[$templateid] = [
			'uuid' => $template['uuid'],
			'host' => $template['host']
		];
	}

	/**
	 * Add hosts names that need association with a database host ID.
	 *
	 * @param array $hosts
	 */
	public function addHosts(array $hosts): void {
		$this->hosts = $hosts;
	}

	/**
	 * Add host name association with host ID.
	 *
	 * @param string $hostid
	 * @param array  $host
	 */
	public function setDbHost(string $hostid, array $host): void {
		$this->db_hosts[$hostid] = [
			'host' => $host['host']
		];
	}

	/**
	 * Add item keys that need association with a database item ID.
	 *
	 * @param array $items
	 */
	public function addItems(array $items): void {
		$this->items = $items;
	}

	/**
	 * Add item key association with item ID.
	 *
	 * @param string $itemid
	 * @param array  $item
	 */
	public function setDbItem(string $itemid, array $item): void {
		$this->db_items[$itemid] = [
			'hostid' => $item['hostid'],
			'uuid' => array_key_exists('uuid', $item) ? $item['uuid'] : '',
			'key_' => $item['key_']
		];
	}

	/**
	 * Add value map names that need association with a database value map ID.
	 *
	 * @param array $valuemaps
	 */
	public function addValuemaps(array $valuemaps): void {
		$this->valuemaps = $valuemaps;
	}

	/**
	 * Add trigger description/expression/recovery_expression that need association with a database trigger ID.
	 *
	 * @param array $triggers
	 */
	public function addTriggers(array $triggers): void {
		$this->triggers = $triggers;
	}

	/**
	 * Add graph names that need association with a database graph ID.
	 *
	 * @param array $graphs
	 */
	public function addGraphs(array $graphs): void {
		$this->graphs = $graphs;
	}

	/**
	 * Add trigger name/expression association with trigger ID.
	 *
	 * @param string $triggerid
	 * @param array  $trigger
	 */
	public function setDbTrigger(string $triggerid, array $trigger): void {
		$this->db_triggers[$triggerid] = [
			'uuid' => array_key_exists('uuid', $trigger) ? $trigger['uuid'] : '',
			'description' => $trigger['description'],
			'expression' => $trigger['expression'],
			'recovery_expression' => $trigger['recovery_expression']
		];
	}

	/**
	 * Add icon map names that need association with a database icon map ID.
	 *
	 * @param array $iconmaps
	 */
	public function addIconmaps(array $iconmaps): void {
		$this->iconmaps = $iconmaps;
	}

	/**
	 * Add icon map names that need association with a database icon map ID.
	 *
	 * @param array $images
	 */
	public function addImages(array $images): void {
		$this->images = $images;
	}

	/**
	 * Add image name association with image ID.
	 *
	 * @param string $imageid
	 * @param array  $image
	 */
	public function setDbImage(string $imageid, array $image): void {
		$this->db_images[$imageid] = [
			'name' => $image['name']
		];
	}

	/**
	 * Add map names that need association with a database map ID.
	 *
	 * @param array $maps
	 */
	public function addMaps(array $maps) {
//		$this->maps = array_unique(array_merge($this->maps, $maps));
		$this->maps = $maps;
	}

	/**
	 * Add map name association with map ID.
	 *
	 * @param string $mapid
	 * @param array  $map
	 */
	public function setDbMap(string $mapid, array $map): void {
		$this->db_maps[$mapid] =[
			'name' => $map['name']
		];
	}

	/**
	 * Add templated dashboard names that need association with a database dashboard ID.
	 *
	 * @param array $dashboards
	 */
	public function addTemplateDashboards(array $dashboards): void {
		$this->template_dashboards = $dashboards;
	}

	/**
	 * Add user macro names that need association with a database macro ID.
	 *
	 * @param array $macros[<template uuid>]  An array of macros by template UUID.
	 */
	public function addTemplateMacros(array $macros): void {
		$this->template_macros = $macros;
	}

	/**
	 * Add user macro names that need association with a database macro ID.
	 *
	 * @param array $macros[<host name>]  An array of macros by host technical name.
	 */
	public function addHostMacros(array $macros): void {
		$this->host_macros = $macros;
	}

	/**
	 * Add user macro names that need association with a database macro ID.
	 *
	 * @param array $macros[<host name>]  An array of macros by host technical name.
	 */
	public function addHostPrototypeMacros(array $macros): void {
		$this->host_prototype_macros = $macros;
	}

	/**
	 * Add proxy names that need association with a database proxy ID.
	 *
	 * @param array $proxies
	 */
	public function addProxies(array $proxies): void {
		$this->proxies = $proxies;
	}

	/**
	 * Add host prototypes that need association with a database host prototype ID.
	 *
	 * @param array $hostPrototypes
	 */
	public function addHostPrototypes(array $hostPrototypes): void {
		$this->host_prototypes = $hostPrototypes;
	}

	/**
	 * Add web scenario names that need association with a database httptest ID.
	 *
	 * @param array  $httptests
	 */
	public function addHttpTests(array $httptests): void {
		$this->httptests = $httptests;
	}

	/**
	 * Add web scenario step names that need association with a database httpstep ID.
	 *
	 * @param array  $httpsteps
	 */
	public function addHttpSteps(array $httpsteps): void {
		$this->httpsteps = $httpsteps;
	}

	/**
	 * Select template group ids for previously added group names.
	 */
	protected function selectTemplateGroups(): void {
		$this->db_template_groups = [];

		if (!$this->template_groups) {
			return;
		}

		$this->db_template_groups = API::TemplateGroup()->get([
			'output' => ['name', 'uuid'],
			'filter' => [
				'uuid' => array_column($this->template_groups, 'uuid'),
				'name' => array_column($this->template_groups, 'name')
			],
			'searchByAny' => true,
			'preservekeys' => true
		]);

		$this->template_groups = [];
	}

	/**
	 * Select host group ids for previously added group names.
	 */
	protected function selectHostGroups(): void {
		$this->db_host_groups = [];

		if (!$this->host_groups) {
			return;
		}

		$this->db_host_groups = API::HostGroup()->get([
			'output' => ['name', 'uuid'],
			'filter' => [
				'uuid' => array_column($this->host_groups, 'uuid'),
				'name' => array_column($this->host_groups, 'name')
			],
			'searchByAny' => true,
			'preservekeys' => true
		]);

		$this->host_groups = [];
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
	protected function selectValuemaps(): void {
		$this->db_valuemaps = [];

		if (!$this->valuemaps) {
			return;
		}

		$sql_where = [];

		foreach ($this->valuemaps as $host => $valuemap_names) {
			$hostid = $this->findTemplateidOrHostidByHost($host);

			if ($hostid !== null) {
				$sql_where[] = '(vm.hostid='.zbx_dbstr($hostid).' AND '.
					dbConditionString('vm.name', array_keys($valuemap_names)).')';
			}
		}

		if ($sql_where) {
			$db_valuemaps = DBselect(
				'SELECT vm.valuemapid,vm.hostid,vm.name'.
				' FROM valuemap vm'.
				' WHERE '.implode(' OR ', $sql_where)
			);

			while ($valuemap = DBfetch($db_valuemaps)) {
				$this->db_valuemaps[$valuemap['valuemapid']] = [
					'name' => $valuemap['name'],
					'hostid' => $valuemap['hostid']
				];
			}
		}

		$this->valuemaps = [];
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
	}

	/**
	 * Unset trigger refs to make referencer select them from db again.
	 */
	public function refreshTriggers(): void {
		$this->db_triggers = null;
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
	protected function selectIconmaps(): void {
		$this->db_iconmaps = [];

		if (!$this->iconmaps) {
			return;
		}

		$db_iconmaps = API::IconMap()->get([
			'filter' => ['name' => array_keys($this->iconmaps)],
			'output' => ['name'],
			'preservekeys' => true
		]);

		foreach ($db_iconmaps as $iconmapid => $iconmap) {
			$this->db_iconmaps[$iconmapid] = $iconmap;
		}

		$this->iconmaps = [];
	}

	/**
	 * Select icon map ids for previously added icon maps names.
	 */
	protected function selectImages(): void {
		$this->db_images = [];

		if (!$this->images) {
			return;
		}

		$db_images = API::Image()->get([
			'output' => ['name'],
			'filter' => ['name' => array_keys($this->images)],
			'preservekeys' => true
		]);

		foreach ($db_images as $imageid => $image) {
			$this->db_images[$imageid] = $image;
		}

		$this->images = [];
	}

	/**
	 * Select map ids for previously added maps names.
	 */
	protected function selectMaps(): void {
		$this->db_maps = [];

		if (!$this->maps) {
			return;
		}

		$db_maps = API::Map()->get([
			'output' => ['name'],
			'filter' => ['name' => array_keys($this->maps)],
			'preservekeys' => true
		]);

		foreach ($db_maps as $mapid => $map) {
			$this->db_maps[$mapid] = $map;
		}

		$this->maps = [];
	}

	/**
	 * Select template dashboard IDs for previously added dashboard names and template IDs.
	 *
	 * @throws APIException
	 */
	protected function selectTemplateDashboards(): void {
		$this->db_template_dashboards = [];

		if (!$this->template_dashboards) {
			return;
		}

		$db_template_dashboards = API::TemplateDashboard()->get([
			'output' => ['uuid'],
			'filter' => ['uuid' => array_keys($this->template_dashboards)],
			'preservekeys' => true
		]);

		foreach ($db_template_dashboards as $dashboardid => $dashboard) {
			$this->db_template_dashboards[$dashboardid] = [
				'uuid' => $dashboard['uuid']
			];
		}

		$this->template_dashboards = [];
	}

	/**
	 * Select user macro ids for previously added macro names.
	 */
	protected function selectTemplateMacros(): void {
		$this->db_template_macros = [];

		$sql_where = [];

		foreach ($this->template_macros as $uuid => $macros) {
			$sql_where[] = '(h.uuid='.zbx_dbstr($uuid).' AND '.dbConditionString('hm.macro', $macros).')';
		}

		if ($sql_where) {
			$db_macros = DBselect(
				'SELECT hm.hostmacroid,hm.hostid,hm.macro'.
				' FROM hostmacro hm'.
					' JOIN hosts h on hm.hostid=h.hostid'.
				' WHERE '.implode(' OR ', $sql_where)
			);

			while ($db_macro = DBfetch($db_macros)) {
				$this->db_template_macros[$db_macro['hostid']][$db_macro['macro']] = $db_macro['hostmacroid'];
			}
		}

		$this->template_macros = [];
	}

	/**
	 * Select user macro ids for previously added macro names.
	 */
	protected function selectHostMacros(): void {
		$this->db_host_macros = [];

		$sql_where = [];

		foreach ($this->host_macros as $host => $macros) {
			$sql_where[] = '(h.host='.zbx_dbstr($host).' AND '.dbConditionString('hm.macro', $macros).')';
		}

		if ($sql_where) {
			$db_macros = DBselect(
				'SELECT hm.hostmacroid,hm.hostid,hm.macro'.
				' FROM hostmacro hm'.
					' JOIN hosts h on hm.hostid=h.hostid'.
				' WHERE '.implode(' OR ', $sql_where)
			);

			while ($db_macro = DBfetch($db_macros)) {
				$this->db_host_macros[$db_macro['hostid']][$db_macro['macro']] = $db_macro['hostmacroid'];
			}
		}

		$this->host_macros = [];
	}

	/**
	 * Select user macro ids for previously added macro names.
	 */
	protected function selectHostPrototypeMacros(): void {
		$this->db_host_prototype_macros = [];

		$sql_where = [];

		foreach ($this->host_prototype_macros as $type => $hosts) {
			foreach ($hosts as $host => $discovery_rules) {
				foreach ($discovery_rules as $discovery_rule_key => $host_prototypes) {
					foreach ($host_prototypes as $host_prototype_id => $macros) {
						$sql_where[] = '('.
							'h.host='.zbx_dbstr($host).
							' AND dr.key_='.zbx_dbstr($discovery_rule_key).
							' AND hp.'.($type === 'uuid' ? 'uuid' : 'host').'='.zbx_dbstr($host_prototype_id).
							' AND '.dbConditionString('hm.macro', $macros).
						')';
					}
				}
			}
		}

		if ($sql_where) {
			$db_macros = DBselect(
				'SELECT hm.hostmacroid,hm.hostid,hm.macro'.
				' FROM hostmacro hm'.
					' JOIN hosts hp on hm.hostid=hp.hostid'.
					' JOIN host_discovery hd on hp.hostid=hd.hostid'.
					' JOIN items dr on hd.parent_itemid=dr.itemid'.
					' JOIN hosts h on dr.hostid=h.hostid'.
				' WHERE '.implode(' OR ', $sql_where)
			);

			while ($db_macro = DBfetch($db_macros)) {
				$this->db_host_prototype_macros[$db_macro['hostid']][$db_macro['macro']] = $db_macro['hostmacroid'];
			}
		}

		$this->host_prototype_macros = [];
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
			'filter' => ['host' => array_keys($this->proxies)],
			'preservekeys' => true
		]);

		$this->proxies = [];
	}

	/**
	 * Select host prototype ids for previously added host prototypes names.
	 */
	protected function selectHostPrototypes(): void {
		$this->db_host_prototypes = [];

		$sql_where = [];

		foreach ($this->host_prototypes as $type => $hosts) {
			foreach ($hosts as $host => $discovery_rules) {
				foreach ($discovery_rules as $discovery_rule_id => $host_prototype_ids) {
					$sql_where[] = '('.
						'h.host='.zbx_dbstr($host).
						' AND dr.'.($type === 'uuid' ? 'uuid' : 'key_').'='.zbx_dbstr($discovery_rule_id).
						' AND '.dbConditionString('hp.'.($type === 'uuid' ? 'uuid' : 'host'), $host_prototype_ids).
					')';
				}
			}
		}

		if ($sql_where) {
			$db_host_prototypes = DBselect(
				'SELECT hp.host,hp.uuid,hp.hostid,hd.parent_itemid,dr.hostid AS parent_hostid'.
				' FROM hosts hp'.
					' JOIN host_discovery hd ON hp.hostid=hd.hostid'.
					' JOIN items dr ON hd.parent_itemid=dr.itemid'.
					' JOIN hosts h on dr.hostid=h.hostid'.
				' WHERE '.implode(' OR ', $sql_where)
			);
			while ($db_host_prototype = DBfetch($db_host_prototypes)) {
				$this->db_host_prototypes[$db_host_prototype['hostid']] = [
					'uuid' => $db_host_prototype['uuid'],
					'host' => $db_host_prototype['host'],
					'parent_hostid' => $db_host_prototype['parent_hostid'],
					'discovery_ruleid' => $db_host_prototype['parent_itemid']
				];
			}
		}

		$this->host_prototypes = [];
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
