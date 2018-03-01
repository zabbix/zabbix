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
 * Class for importing configuration data.
 */
class CConfigurationImport {

	/**
	 * @var CImportDataAdapter
	 */
	protected $adapter;

	/**
	 * @var CImportReferencer
	 */
	protected $referencer;

	/**
	 * @var CImportedObjectContainer
	 */
	protected $importedObjectContainer;

	/**
	 * @var array
	 */
	protected $options;

	/**
	 * @var array with data read from source string
	 */
	protected $data;

	/**
	 * @var array  cached data from the adapter
	 */
	protected $formattedData = [];

	/**
	 * Constructor.
	 * Source string must be suitable for reader class,
	 * i.e. if string contains json then reader should be able to read json.
	 *
	 * @param array						$options					import options "createMissing", "updateExisting" and "deleteMissing"
	 * @param CImportReferencer			$referencer					class containing all importable objects
	 * @param CImportedObjectContainer	$importedObjectContainer	class containing processed host and template IDs
	 */
	public function __construct(array $options = [], CImportReferencer $referencer,
			CImportedObjectContainer $importedObjectContainer) {
		$this->options = [
			'groups' => ['createMissing' => false],
			'hosts' => ['updateExisting' => false, 'createMissing' => false],
			'templates' => ['updateExisting' => false, 'createMissing' => false],
			'templateScreens' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'applications' => ['createMissing' => false, 'deleteMissing' => false],
			'templateLinkage' => ['createMissing' => false],
			'items' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'discoveryRules' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'triggers' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'graphs' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'httptests' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'screens' => ['updateExisting' => false, 'createMissing' => false],
			'maps' => ['updateExisting' => false, 'createMissing' => false],
			'images' => ['updateExisting' => false, 'createMissing' => false],
			'valueMaps' => ['updateExisting' => false, 'createMissing' => false]
		];

		$this->options = array_merge($this->options, $options);
		$this->referencer = $referencer;
		$this->importedObjectContainer = $importedObjectContainer;
	}

	/**
	 * Import configuration data.
	 *
	 * @param CImportDataAdapter $adapter   an object to provide access to the imported data
	 *
	 * @return bool
	 */
	public function import(CImportDataAdapter $adapter) {
		$this->adapter = $adapter;

		// parse all import for references to resolve them all together with less sql count
		$this->gatherReferences();

		$this->processGroups();
		$this->processTemplates();
		$this->processHosts();

		// delete missing objects from processed hosts and templates
		$this->deleteMissingHttpTests();
		$this->deleteMissingDiscoveryRules();
		$this->deleteMissingTriggers();
		$this->deleteMissingGraphs();
		$this->deleteMissingItems();
		$this->deleteMissingApplications();

		// import objects
		$this->processApplications();
		$this->processValueMaps();
		$this->processItems();
		$this->processHttpTests();
		$this->processTriggers();
		$this->processDiscoveryRules();
		$this->processGraphs();
		$this->processImages();
		$this->processMaps();
		$this->processTemplateScreens();
		$this->processScreens();

		return true;
	}

	/**
	 * Parse all import data and collect references to objects.
	 * For host objects it collects host names, for items - host name and item key, etc.
	 * Collected references are added and resolved via the $this->referencer object.
	 *
	 * @see CImportReferencer
	 */
	protected function gatherReferences() {
		$groupsRefs = [];
		$templatesRefs = [];
		$hostsRefs = [];
		$applicationsRefs = [];
		$itemsRefs = [];
		$valueMapsRefs = [];
		$triggersRefs = [];
		$graphsRefs = [];
		$iconMapsRefs = [];
		$mapsRefs = [];
		$screensRefs = [];
		$templateScreensRefs = [];
		$macrosRefs = [];
		$proxyRefs = [];
		$hostPrototypesRefs = [];
		$httptestsRefs = [];
		$httpstepsRefs = [];

		foreach ($this->getFormattedGroups() as $group) {
			$groupsRefs[$group['name']] = $group['name'];
		}

		foreach ($this->getFormattedTemplates() as $template) {
			$templatesRefs[$template['host']] = $template['host'];

			foreach ($template['groups'] as $group) {
				$groupsRefs[$group['name']] = $group['name'];
			}

			if (array_key_exists('macros', $template)) {
				foreach ($template['macros'] as $macro) {
					$macrosRefs[$template['host']][$macro['macro']] = $macro['macro'];
				}
			}

			if (!empty($template['templates'])) {
				foreach ($template['templates'] as $linkedTemplate) {
					$templatesRefs[$linkedTemplate['name']] = $linkedTemplate['name'];
				}
			}
		}

		foreach ($this->getFormattedHosts() as $host) {
			$hostsRefs[$host['host']] = $host['host'];

			foreach ($host['groups'] as $group) {
				$groupsRefs[$group['name']] = $group['name'];
			}

			if (array_key_exists('macros', $host)) {
				foreach ($host['macros'] as $macro) {
					$macrosRefs[$host['host']][$macro['macro']] = $macro['macro'];
				}
			}

			if (!empty($host['templates'])) {
				foreach ($host['templates'] as $linkedTemplate) {
					$templatesRefs[$linkedTemplate['name']] = $linkedTemplate['name'];
				}
			}

			if (!empty($host['proxy'])) {
				$proxyRefs[$host['proxy']['name']] = $host['proxy']['name'];
			}
		}

		foreach ($this->getFormattedApplications() as $host => $applications) {
			foreach ($applications as $app) {
				$applicationsRefs[$host][$app['name']] = $app['name'];
			}
		}

		foreach ($this->getFormattedValueMaps() as $valuemap) {
			$valueMapsRefs[$valuemap['name']] = $valuemap['name'];
		}

		foreach ($this->getFormattedItems() as $host => $items) {
			foreach ($items as $item) {
				$itemsRefs[$host][$item['key_']] = $item['key_'];

				foreach ($item['applications'] as $app) {
					$applicationsRefs[$host][$app['name']] = $app['name'];
				}

				if (!empty($item['valuemap'])) {
					$valueMapsRefs[$item['valuemap']['name']] = $item['valuemap']['name'];
				}
			}
		}

		foreach ($this->getFormattedDiscoveryRules() as $host => $discoveryRules) {
			foreach ($discoveryRules as $discoveryRule) {
				$itemsRefs[$host][$discoveryRule['key_']] = $discoveryRule['key_'];

				foreach ($discoveryRule['item_prototypes'] as $itemp) {
					$itemsRefs[$host][$itemp['key_']] = $itemp['key_'];

					foreach ($itemp['applications'] as $app) {
						$applicationsRefs[$host][$app['name']] = $app['name'];
					}

					if (!empty($itemp['valuemap'])) {
						$valueMapsRefs[$itemp['valuemap']['name']] = $itemp['valuemap']['name'];
					}
				}

				foreach ($discoveryRule['trigger_prototypes'] as $trigger) {
					$triggersRefs[$trigger['description']][$trigger['expression']][$trigger['recovery_expression']] =
						true;

					if (array_key_exists('dependencies', $trigger)) {
						foreach ($trigger['dependencies'] as $dependency) {
							$name = $dependency['name'];
							$expression = $dependency['expression'];
							$recovery_expression = $dependency['recovery_expression'];

							$triggersRefs[$name][$expression][$recovery_expression] = true;
						}
					}
				}

				foreach ($discoveryRule['graph_prototypes'] as $graph) {
					if ($graph['ymin_item_1']) {
						$yMinItem = $graph['ymin_item_1'];
						$itemsRefs[$yMinItem['host']][$yMinItem['key']] = $yMinItem['key'];
					}

					if ($graph['ymax_item_1']) {
						$yMaxItem = $graph['ymax_item_1'];
						$itemsRefs[$yMaxItem['host']][$yMaxItem['key']] = $yMaxItem['key'];
					}

					foreach ($graph['gitems'] as $gitem) {
						$gitemItem = $gitem['item'];

						$hostsRefs[$gitemItem['host']] = $gitemItem['host'];
						$itemsRefs[$gitemItem['host']][$gitemItem['key']] = $gitemItem['key'];
						$graphsRefs[$gitemItem['host']][$graph['name']] = $graph['name'];
					}
				}

				foreach ($discoveryRule['host_prototypes'] as $hostPrototype) {
					$hostPrototypesRefs[$host][$discoveryRule['key_']][$hostPrototype['host']] = $hostPrototype['host'];

					foreach ($hostPrototype['group_prototypes'] as $groupPrototype) {
						if (isset($groupPrototype['group'])) {
							$groupsRefs[$groupPrototype['group']['name']] = $groupPrototype['group']['name'];
						}
					}

					foreach ($hostPrototype['templates'] as $template) {
						$templatesRefs[$template['name']] = $template['name'];
					}
				}
			}
		}

		foreach ($this->getFormattedGraphs() as $graph) {
			if ($graph['ymin_item_1']) {
				$yMinItem = $graph['ymin_item_1'];

				$hostsRefs[$yMinItem['host']] = $yMinItem['host'];
				$itemsRefs[$yMinItem['host']][$yMinItem['key']] = $yMinItem['key'];
			}

			if ($graph['ymax_item_1']) {
				$yMaxItem = $graph['ymax_item_1'];

				$hostsRefs[$yMaxItem['host']] = $yMaxItem['host'];
				$itemsRefs[$yMaxItem['host']][$yMaxItem['key']] = $yMaxItem['key'];
			}

			if (isset($graph['gitems']) && $graph['gitems']) {
				foreach ($graph['gitems'] as $gitem) {
					$gitemItem = $gitem['item'];

					$hostsRefs[$gitemItem['host']] = $gitemItem['host'];
					$itemsRefs[$gitemItem['host']][$gitemItem['key']] = $gitemItem['key'];
					$graphsRefs[$gitemItem['host']][$graph['name']] = $graph['name'];
				}
			}
		}

		foreach ($this->getFormattedTriggers() as $trigger) {
			$triggersRefs[$trigger['description']][$trigger['expression']][$trigger['recovery_expression']] = true;

			if (array_key_exists('dependencies', $trigger)) {
				foreach ($trigger['dependencies'] as $dependency) {
					$triggersRefs[$dependency['name']][$dependency['expression']][$dependency['recovery_expression']] =
						true;
				}
			}
		}

		foreach ($this->getFormattedMaps() as $map) {
			$mapsRefs[$map['name']] = $map['name'];

			if (!empty($map['iconmap'])) {
				$iconMapsRefs[$map['iconmap']['name']] = $map['iconmap']['name'];
			}

			if (isset($map['selements'])) {
				foreach ($map['selements'] as $selement) {
					switch ($selement['elementtype']) {
						case SYSMAP_ELEMENT_TYPE_MAP:
							$mapsRefs[$selement['elements'][0]['name']] = $selement['elements'][0]['name'];
							break;

						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$groupsRefs[$selement['elements'][0]['name']] = $selement['elements'][0]['name'];
							break;

						case SYSMAP_ELEMENT_TYPE_HOST:
							$hostsRefs[$selement['elements'][0]['host']] = $selement['elements'][0]['host'];
							break;

						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							foreach ($selement['elements'] as $element) {
								$triggersRefs[$element['description']][$element['expression']][$element['recovery_expression']] = true;
							}
							break;
					}
				}
			}

			if (isset($map['links'])) {
				foreach ($map['links'] as $link) {
					if (isset($link['linktriggers'])) {
						foreach ($link['linktriggers'] as $linkTrigger) {
							$t = $linkTrigger['trigger'];
							$triggersRefs[$t['description']][$t['expression']][$t['recovery_expression']] = true;
						}
					}
				}
			}
		}

		foreach ($this->getFormattedScreens() as $screen) {
			$screensRefs[$screen['name']] = $screen['name'];

			if (!empty($screen['screenitems'])) {
				foreach ($screen['screenitems'] as $screenItem) {
					$resource = $screenItem['resource'];

					if (empty($resource)) {
						continue;
					}

					switch ($screenItem['resourcetype']) {
						case SCREEN_RESOURCE_HOST_INFO:
						case SCREEN_RESOURCE_TRIGGER_INFO:
						case SCREEN_RESOURCE_TRIGGER_OVERVIEW:
						case SCREEN_RESOURCE_DATA_OVERVIEW:
						case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
							$groupsRefs[$resource['name']] = $resource['name'];
							break;

						case SCREEN_RESOURCE_HOST_TRIGGERS:
							$hostsRefs[$resource['host']] = $resource['host'];
							break;

						case SCREEN_RESOURCE_GRAPH:
						case SCREEN_RESOURCE_LLD_GRAPH:
							$hostsRefs[$resource['host']] = $resource['host'];
							$graphsRefs[$resource['host']][$resource['name']] = $resource['name'];
							break;

						case SCREEN_RESOURCE_CLOCK:
							if ($screenItem['style'] != TIME_TYPE_HOST) {
								break;
							}
							// break; is not missing here

						case SCREEN_RESOURCE_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_PLAIN_TEXT:
							$hostsRefs[$resource['host']] = $resource['host'];
							$itemsRefs[$resource['host']][$resource['key']] = $resource['key'];
							break;

						case SCREEN_RESOURCE_MAP:
							$mapsRefs[$resource['name']] = $resource['name'];
							break;

						case SCREEN_RESOURCE_SCREEN:
							$screensRefs[$resource['name']] = $resource['name'];
							break;
					}
				}
			}
		}

		foreach ($this->getFormattedTemplateScreens() as $screens) {
			foreach ($screens as $screen) {
				$templateScreensRefs[$screen['name']] = $screen['name'];

				if (!empty($screen['screenitems'])) {
					foreach ($screen['screenitems'] as $screenItem) {
						$resource = $screenItem['resource'];

						switch ($screenItem['resourcetype']) {
							case SCREEN_RESOURCE_GRAPH:
							case SCREEN_RESOURCE_LLD_GRAPH:
								$hostsRefs[$resource['host']] = $resource['host'];
								$graphsRefs[$resource['host']][$resource['name']] = $resource['name'];
								break;

							case SCREEN_RESOURCE_CLOCK:
								if ($screenItem['style'] != TIME_TYPE_HOST) {
									break;
								}
								// break; is not missing here

							case SCREEN_RESOURCE_SIMPLE_GRAPH:
							case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
							case SCREEN_RESOURCE_PLAIN_TEXT:
								$hostsRefs[$resource['host']] = $resource['host'];
								$itemsRefs[$resource['host']][$resource['key']] = $resource['key'];
								break;
						}
					}
				}
			}
		}

		foreach ($this->getFormattedHttpTests() as $host => $httptests) {
			foreach ($httptests as $httptest) {
				$httptestsRefs[$host][$httptest['name']] = $httptest['name'];

				if (array_key_exists('name', $httptest['application'])) {
					$applicationsRefs[$host][$httptest['application']['name']] = $httptest['application']['name'];
				}
			}
		}

		foreach ($this->getFormattedHttpSteps() as $host => $httptests) {
			foreach ($httptests as $httptest_name => $httpsteps) {
				foreach ($httpsteps as $httpstep) {
					$httpstepsRefs[$host][$httptest_name][$httpstep['name']] = $httpstep['name'];
				}
			}
		}

		$this->referencer->addGroups($groupsRefs);
		$this->referencer->addTemplates($templatesRefs);
		$this->referencer->addHosts($hostsRefs);
		$this->referencer->addApplications($applicationsRefs);
		$this->referencer->addItems($itemsRefs);
		$this->referencer->addValueMaps($valueMapsRefs);
		$this->referencer->addTriggers($triggersRefs);
		$this->referencer->addGraphs($graphsRefs);
		$this->referencer->addIconMaps($iconMapsRefs);
		$this->referencer->addMaps($mapsRefs);
		$this->referencer->addScreens($screensRefs);
		$this->referencer->addTemplateScreens($templateScreensRefs);
		$this->referencer->addMacros($macrosRefs);
		$this->referencer->addProxies($proxyRefs);
		$this->referencer->addHostPrototypes($hostPrototypesRefs);
		$this->referencer->addHttpTests($httptestsRefs);
		$this->referencer->addHttpSteps($httpstepsRefs);
	}

	/**
	 * Import groups.
	 *
	 * @return null
	 */
	protected function processGroups() {
		if (!$this->options['groups']['createMissing']) {
			return;
		}

		$groups = $this->getFormattedGroups();

		if (empty($groups)) {
			return;
		}

		// skip the groups that already exist
		foreach ($groups as $gnum => $group) {
			if ($this->referencer->resolveGroup($group['name'])) {
				unset($groups[$gnum]);
			}
		}

		if ($groups) {
			// reset indexing because ids from api does not preserve input array keys
			$groups = array_values($groups);
			$newGroups = API::HostGroup()->create($groups);

			foreach ($newGroups['groupids'] as $gnum => $groupid) {
				$this->referencer->addGroupRef($groups[$gnum]['name'], $groupid);
			}
		}
	}

	/**
	 * Import templates.
	 *
	 * @throws Exception
	 */
	protected function processTemplates() {
		if ($this->options['templates']['updateExisting'] || $this->options['templates']['createMissing']) {
			$templates = $this->getFormattedTemplates();
			if ($templates) {
				$templateImporter = new CTemplateImporter($this->options, $this->referencer,
					$this->importedObjectContainer
				);
				$templateImporter->import($templates);

				// get list of imported template IDs and add them processed template ID list
				$templateIds = $templateImporter->getProcessedTemplateIds();
				$this->importedObjectContainer->addTemplateIds($templateIds);
			}
		}
	}

	/**
	 * Import hosts.
	 *
	 * @throws Exception
	 */
	protected function processHosts() {
		if ($this->options['hosts']['updateExisting'] || $this->options['hosts']['createMissing']) {
			$hosts = $this->getFormattedHosts();
			if ($hosts) {
				$hostImporter = new CHostImporter($this->options, $this->referencer, $this->importedObjectContainer);
				$hostImporter->import($hosts);

				// get list of imported host IDs and add them processed host ID list
				$hostIds = $hostImporter->getProcessedHostIds();
				$this->importedObjectContainer->addHostIds($hostIds);
			}
		}
	}

	/**
	 * Import applications.
	 */
	protected function processApplications() {
		if (!$this->options['applications']['createMissing']) {
			return;
		}

		$allApplications = $this->getFormattedApplications();

		if (!$allApplications) {
			return;
		}

		$applicationsToCreate = [];

		foreach ($allApplications as $host => $applications) {
			$hostId = $this->referencer->resolveHostOrTemplate($host);

			if (!$this->importedObjectContainer->isHostProcessed($hostId)
					&& !$this->importedObjectContainer->isTemplateProcessed($hostId)) {
				continue;
			}

			foreach ($applications as $application) {
				$application['hostid'] = $hostId;
				$appId = $this->referencer->resolveApplication($hostId, $application['name']);

				if (!$appId) {
					$applicationsToCreate[] = $application;
				}
			}
		}

		if ($applicationsToCreate) {
			API::Application()->create($applicationsToCreate);
		}

		// refresh applications because templated ones can be inherited to host and used in items
		$this->referencer->refreshApplications();
	}

	/**
	 * Import value maps.
	 */
	protected function processValueMaps() {
		if (!$this->options['valueMaps']['createMissing'] && !$this->options['valueMaps']['updateExisting']) {
			return;
		}

		$all_valuemaps = $this->getFormattedValueMaps();

		if (!$all_valuemaps) {
			return;
		}

		$valuemaps_to_create = [];
		$valuemaps_to_update = [];

		foreach ($all_valuemaps as $valuemap) {
			$valuemapid = $this->referencer->resolveValueMap($valuemap['name']);

			if ($valuemapid) {
				$valuemap['valuemapid'] = $valuemapid;
				$valuemaps_to_update[] = $valuemap;
			}
			else {
				$valuemaps_to_create[] = $valuemap;
			}
		}

		if ($this->options['valueMaps']['createMissing'] && $valuemaps_to_create) {
			$valuemapids = API::ValueMap()->create($valuemaps_to_create);

			foreach ($valuemaps_to_create as $key => $valuemap) {
				$this->referencer->addValueMapRef($valuemap['name'], $valuemapids['valuemapids'][$key]);
			}
		}

		if ($this->options['valueMaps']['updateExisting'] && $valuemaps_to_update) {
			API::ValueMap()->update($valuemaps_to_update);
		}
	}

	/**
	 * Import items.
	 */
	protected function processItems() {
		if (!$this->options['items']['createMissing'] && !$this->options['items']['updateExisting']) {
			return;
		}

		$allItems = $this->getFormattedItems();

		if (!$allItems) {
			return;
		}

		$xml_itemkey = 'master_item';
		$order_tree = $this->getItemsOrder($xml_itemkey);
		$items_to_create = [];
		$items_to_update = [];

		foreach ($allItems as $host => $items) {
			$hostId = $this->referencer->resolveHostOrTemplate($host);

			if (!$this->importedObjectContainer->isHostProcessed($hostId)
					&& !$this->importedObjectContainer->isTemplateProcessed($hostId)) {
				continue;
			}

			foreach ($order_tree[$host] as $index => $level) {
				$item = $items[$index];
				$item['hostid'] = $hostId;

				if (isset($item['applications']) && $item['applications']) {
					$applicationsIds = [];

					foreach ($item['applications'] as $application) {
						if ($applicationId = $this->referencer->resolveApplication($hostId, $application['name'])) {
							$applicationsIds[] = $applicationId;
						}
						else {
							throw new Exception(_s('Item "%1$s" on "%2$s": application "%3$s" does not exist.',
								$item['name'], $host, $application['name']));
						}
					}

					$item['applications'] = $applicationsIds;
				}

				if (array_key_exists('interface_ref', $item) && $item['interface_ref']) {
					$item['interfaceid'] = $this->referencer->interfacesCache[$hostId][$item['interface_ref']];
				}

				if (isset($item['valuemap']) && $item['valuemap']) {
					$valueMapId = $this->referencer->resolveValueMap($item['valuemap']['name']);

					if (!$valueMapId) {
						throw new Exception(_s(
							'Cannot find value map "%1$s" used for item "%2$s" on "%3$s".',
							$item['valuemap']['name'],
							$item['name'],
							$host
						));
					}

					$item['valuemapid'] = $valueMapId;
				}

				if ($item['type'] == ITEM_TYPE_DEPENDENT) {
					$master_itemid = $this->referencer->resolveItem($hostId, $item[$xml_itemkey]['key']);

					if ($master_itemid !== false) {
						$item['master_itemid'] = $master_itemid;
						unset($item[$xml_itemkey]);
					}
				}
				else {
					unset($item[$xml_itemkey]);
				}

				if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
					$headers = [];

					foreach ($item['headers'] as $header) {
						$headers[$header['name']] = $header['value'];
					}
					$item['headers'] = $headers;

					$query_fields = [];

					foreach ($item['query_fields'] as $query_field) {
						$query_fields[] = [$query_field['name'] => $query_field['value']];
					}
					$item['query_fields'] = $query_fields;
				}

				$itemsId = $this->referencer->resolveItem($hostId, $item['key_']);

				if ($itemsId) {
					$item['itemid'] = $itemsId;
					if (!array_key_exists($level, $items_to_update)) {
						$items_to_update[$level] = [];
					}
					$items_to_update[$level][] = $item;
				}
				else {
					if (!array_key_exists($level, $items_to_create)) {
						$items_to_create[$level] = [];
					}
					$items_to_create[$level][] = $item;
				}
			}
		}

		// create/update the items and create a hash hostid->key_->itemid
		if ($this->options['items']['createMissing'] && $items_to_create) {
			$this->createEntitiesWithDependency($xml_itemkey, $items_to_create, API::Item());
		}

		if ($this->options['items']['updateExisting'] && $items_to_update) {
			$this->updateEntitiesWithDependency($xml_itemkey, $items_to_update, API::Item());
		}

		// refresh items because templated ones can be inherited to host and used in triggers, graphs, etc.
		$this->referencer->refreshItems();
	}


	/**
	 * Create CItem or CItemPrototype with dependency.
	 *
	 * @param string               $xml_entitykey       Master entity array key in xml parsed data.
	 * @param array                $entities_by_level   Associative array of entities where key is entity dependency
	 *                                                  level and value is array of entities for this level.
	 * @param CItem|CItemPrototype $entity_service      Entity service which is capable to proceed with entity create.
	 *
	 * @throws Exception if entity master entity can not be resolved.
	 */
	protected function createEntitiesWithDependency($xml_entitykey, $entities_by_level, $entity_service) {
		foreach ($entities_by_level as $level => $entities) {
			foreach ($entities as &$entity) {
				if (array_key_exists($xml_entitykey, $entity)) {
					$entity['master_itemid'] = $this->referencer->resolveItem($entity['hostid'],
						$entity[$xml_entitykey]['key']
					);

					if ($entity['master_itemid'] === false) {
						throw new Exception(_s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _s('value "%1$s" not found', $entity[$xml_entitykey]['key'])
						));
					}
					unset($entity[$xml_entitykey]);
				}
			}
			unset($entity);
			$entityids = $entity_service->create($entities)['itemids'];
			$entityid = reset($entityids);

			foreach ($entities as $entity) {
				$this->referencer->addItemRef($entity['hostid'], $entity['key_'], $entityid);
				$entityid = next($entityids);
			}
		}
	}

	/**
	 * Update CItem or CItemPrototype with dependency.
	 *
	 * @param string               $xml_entitykey       Master entity array key in xml parsed data.
	 * @param array                $entities_by_level   Associative array of entities where key is entity dependency
	 *                                                  level and value is array of entities for this level.
	 * @param CItem|CItemPrototype $entity_service      Entity service which is capable to proceed with entity update.
	 *
	 * @throws Exception if entity master entity can not be resolved.
	 */
	protected function updateEntitiesWithDependency($xml_entitykey, $entities_by_level, $entity_service) {
		foreach ($entities_by_level as $level => $entities) {
			foreach ($entities as &$entity) {
				if (array_key_exists($xml_entitykey, $entity)) {
					$entity['master_itemid'] = $this->referencer->resolveItem($entity['hostid'],
						$entity[$xml_entitykey]['key']
					);

					if ($entity['master_itemid'] === false) {
						throw new Exception(_s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _s('value "%1$s" not found', $entity[$xml_entitykey]['key'])
						));
					}
					unset($entity[$xml_entitykey]);
				}
			}
			unset($entity);
			$entityids = $entity_service->update($entities)['itemids'];
			$entityid = reset($entityids);

			foreach ($entities as $entity) {
				$this->referencer->addItemRef($entity['hostid'], $entity['key_'], $entityid);
				$entityid = next($entityids);
			}
		}
	}

	/**
	 * Import discovery rules.
	 *
	 * @throws Exception
	 */
	protected function processDiscoveryRules() {
		if (!$this->options['discoveryRules']['createMissing'] && !$this->options['discoveryRules']['updateExisting']) {
			return;
		}

		$allDiscoveryRules = $this->getFormattedDiscoveryRules();

		if (!$allDiscoveryRules) {
			return;
		}

		// unset rules that are related to hosts we did not process
		foreach ($allDiscoveryRules as $host => $discoveryRules) {
			$hostId = $this->referencer->resolveHostOrTemplate($host);

			if (!$this->importedObjectContainer->isHostProcessed($hostId)
					&& !$this->importedObjectContainer->isTemplateProcessed($hostId)) {
				unset($allDiscoveryRules[$host]);
			}
		}

		$itemsToCreate = [];
		$itemsToUpdate = [];

		foreach ($allDiscoveryRules as $host => $discoveryRules) {
			$hostId = $this->referencer->resolveHostOrTemplate($host);

			foreach ($discoveryRules as $item) {
				$item['hostid'] = $hostId;

				if (array_key_exists('interface_ref', $item) && $item['interface_ref']) {
					$item['interfaceid'] = $this->referencer->interfacesCache[$hostId][$item['interface_ref']];
				}

				unset($item['item_prototypes']);
				unset($item['trigger_prototypes']);
				unset($item['graph_prototypes']);
				unset($item['host_prototypes']);

				$itemId = $this->referencer->resolveItem($hostId, $item['key_']);

				if ($itemId) {
					$item['itemid'] = $itemId;
					$itemsToUpdate[] = $item;
				}
				else {
					$itemsToCreate[] = $item;
				}
			}
		}

		// create/update discovery rules and add processed rules to array $processedRules
		$processedRules = [];

		if ($this->options['discoveryRules']['createMissing'] && $itemsToCreate) {
			$newItemsIds = API::DiscoveryRule()->create($itemsToCreate);

			foreach ($newItemsIds['itemids'] as $inum => $itemid) {
				$item = $itemsToCreate[$inum];

				$this->referencer->addItemRef($item['hostid'], $item['key_'], $itemid);
			}

			foreach ($itemsToCreate as $item) {
				$processedRules[$item['hostid']][$item['key_']] = 1;
			}
		}

		if ($this->options['discoveryRules']['updateExisting'] && $itemsToUpdate) {
			API::DiscoveryRule()->update($itemsToUpdate);

			foreach ($itemsToUpdate as $item) {
				$processedRules[$item['hostid']][$item['key_']] = 1;
			}
		}

		// refresh discovery rules because templated ones can be inherited to host and used for prototypes
		$this->referencer->refreshItems();

		$xml_itemprototype_key = 'master_item_prototype';
		$order_tree = $this->getDiscoveryRulesItemsOrder($xml_itemprototype_key);
		// process prototypes
		$prototypes_to_update = [];
		$prototypes_to_create = [];
		$hostPrototypesToUpdate = [];
		$hostPrototypesToCreate = [];

		foreach ($allDiscoveryRules as $host => $discoveryRules) {
			$hostId = $this->referencer->resolveHostOrTemplate($host);

			foreach ($discoveryRules as $item) {
				// if rule was not processed we should not create/update any of its prototypes
				if (!isset($processedRules[$hostId][$item['key_']])) {
					continue;
				}

				$item['hostid'] = $hostId;
				$item_key = $item['key_'];
				$itemId = $this->referencer->resolveItem($hostId, $item_key);

				// prototypes
				$item_prototypes = $item['item_prototypes'] ? $order_tree[$host][$item_key] : [];
				foreach ($item_prototypes as $index => $level) {
					$prototype = $item['item_prototypes'][$index];
					$prototype['hostid'] = $hostId;
					$applicationsIds = [];

					foreach ($prototype['applications'] as $application) {
						$applicationsIds[] = $this->referencer->resolveApplication($hostId, $application['name']);
					}

					$prototype['applications'] = $applicationsIds;

					if (array_key_exists('application_prototypes', $prototype)) {
						$prototype['applicationPrototypes'] = $prototype['application_prototypes'];
					}

					if (array_key_exists('interface_ref', $prototype) && $prototype['interface_ref']) {
						$prototype['interfaceid'] = $this->referencer->interfacesCache[$hostId][$prototype['interface_ref']];
					}

					if ($prototype['valuemap']) {
						$valueMapId = $this->referencer->resolveValueMap($prototype['valuemap']['name']);

						if (!$valueMapId) {
							throw new Exception(_s(
								'Cannot find value map "%1$s" used for item prototype "%2$s" of discovery rule "%3$s" on "%4$s".',
								$prototype['valuemap']['name'],
								$prototype['name'],
								$item['name'],
								$host
							));
						}

						$prototype['valuemapid'] = $valueMapId;
					}

					if ($prototype['type'] == ITEM_TYPE_DEPENDENT) {
						$master_itemprototypeid = $this->referencer->resolveItem($hostId,
							$prototype[$xml_itemprototype_key]['key']
						);

						if ($master_itemprototypeid !== false) {
							$prototype['master_itemid'] = $master_itemprototypeid;
							unset($prototype[$xml_itemprototype_key]);
						}
					}
					else {
						unset($prototype[$xml_itemprototype_key]);
					}

					if ($prototype['type'] == ITEM_TYPE_HTTPAGENT) {
						$headers = [];

						foreach ($prototype['headers'] as $header) {
							$headers[$header['name']] = $header['value'];
						}
						$prototype['headers'] = $headers;

						$query_fields = [];

						foreach ($prototype['query_fields'] as $query_field) {
							$query_fields[] = [$query_field['name'] => $query_field['value']];
						}
						$prototype['query_fields'] = $query_fields;
					}

					$prototypeId = $this->referencer->resolveItem($hostId, $prototype['key_']);
					$prototype['rule'] = ['hostid' => $hostId, 'key' => $item['key_']];
					$prototype['ruleid'] = $this->referencer->resolveItem($hostId, $item['key_']);

					if ($prototypeId) {
						if (!array_key_exists($level, $prototypes_to_update)) {
							$prototypes_to_update[$level] = [];
						}
						$prototype['itemid'] = $prototypeId;
						$prototypes_to_update[$level][] = $prototype;
					}
					else {
						if (!array_key_exists($level, $prototypes_to_create)) {
							$prototypes_to_create[$level] = [];
						}
						$prototypes_to_create[$level][] = $prototype;
					}
				}

				// host prototype
				foreach ($item['host_prototypes'] as $hostPrototype) {
					// resolve group prototypes
					$groupLinks = [];

					foreach ($hostPrototype['group_links'] as $groupLink) {
						$groupId = $this->referencer->resolveGroup($groupLink['group']['name']);

						if (!$groupId) {
							throw new Exception(_s(
								'Cannot find host group "%1$s" for host prototype "%2$s" of discovery rule "%3$s" on "%4$s".',
								$groupLink['group']['name'],
								$hostPrototype['name'],
								$item['name'],
								$host
							));
						}

						$groupLinks[] = ['groupid' => $groupId];
					}

					$hostPrototype['groupLinks'] = $groupLinks;
					$hostPrototype['groupPrototypes'] = $hostPrototype['group_prototypes'];
					unset($hostPrototype['group_links'], $hostPrototype['group_prototypes']);

					// resolve templates
					$templates = [];

					foreach ($hostPrototype['templates'] as $template) {
						$templateId = $this->referencer->resolveTemplate($template['name']);

						if (!$templateId) {
							throw new Exception(_s(
								'Cannot find template "%1$s" for host prototype "%2$s" of discovery rule "%3$s" on "%4$s".',
								$template['name'],
								$hostPrototype['name'],
								$item['name'],
								$host
							));
						}

						$templates[] = ['templateid' => $templateId];
					}

					$hostPrototype['templates'] = $templates;

					$hostPrototypeId = $this->referencer->resolveHostPrototype($hostId, $itemId, $hostPrototype['host']);

					if ($hostPrototypeId) {
						$hostPrototype['hostid'] = $hostPrototypeId;
						$hostPrototypesToUpdate[] = $hostPrototype;
					}
					else {
						$hostPrototype['ruleid'] = $itemId;
						$hostPrototypesToCreate[] = $hostPrototype;
					}
				}

				if (array_key_exists('interface_ref', $item) && $item['interface_ref']) {
					$item['interfaceid'] = $this->referencer->interfacesCache[$hostId][$item['interface_ref']];
				}
				unset($item['item_prototypes']);
				unset($item['trigger_prototypes']);
				unset($item['graph_prototypes']);
				unset($item['host_prototypes']);

				$itemsId = $this->referencer->resolveItem($hostId, $item['key_']);

				if ($itemsId) {
					$item['itemid'] = $itemsId;
					$itemsToUpdate[] = $item;
				}
				else {
					$itemsToCreate[] = $item;
				}
			}
		}

		if ($prototypes_to_create) {
			$this->createEntitiesWithDependency($xml_itemprototype_key, $prototypes_to_create, API::ItemPrototype());
		}

		if ($prototypes_to_update) {
			$this->updateEntitiesWithDependency($xml_itemprototype_key, $prototypes_to_update, API::ItemPrototype());
		}

		if ($hostPrototypesToCreate) {
			API::HostPrototype()->create($hostPrototypesToCreate);
		}
		if ($hostPrototypesToUpdate) {
			API::HostPrototype()->update($hostPrototypesToUpdate);
		}

		// refresh prototypes because templated ones can be inherited to host and used in triggers prototypes or graph prototypes
		$this->referencer->refreshItems();

		// first we need to create item prototypes and only then graph prototypes
		$triggersToCreate = [];
		$triggersToUpdate = [];
		$graphsToCreate = [];
		$graphsToUpdate = [];
		// the list of triggers to process dependencies
		$triggers = [];

		foreach ($allDiscoveryRules as $host => $discoveryRules) {
			$hostId = $this->referencer->resolveHostOrTemplate($host);

			foreach ($discoveryRules as $item) {
				// if rule was not processed we should not create/update any of its prototypes
				if (!isset($processedRules[$hostId][$item['key_']])) {
					continue;
				}

				// trigger prototypes
				foreach ($item['trigger_prototypes'] as $trigger) {
					$triggerId = $this->referencer->resolveTrigger($trigger['description'], $trigger['expression'],
						$trigger['recovery_expression']
					);

					$triggers[] = $trigger;
					unset($trigger['dependencies']);

					if ($triggerId) {
						$trigger['triggerid'] = $triggerId;
						$triggersToUpdate[] = $trigger;
					}
					else {
						$triggersToCreate[] = $trigger;
					}
				}

				// graph prototypes
				foreach ($item['graph_prototypes'] as $graph) {
					if ($graph['ymin_item_1']) {
						$hostId = $this->referencer->resolveHostOrTemplate($graph['ymin_item_1']['host']);
						$itemId = $hostId
							? $this->referencer->resolveItem($hostId, $graph['ymin_item_1']['key'])
							: false;

						if (!$itemId) {
							throw new Exception(_s(
								'Cannot find item "%1$s" on "%2$s" used as the Y axis MIN value for graph prototype "%3$s" of discovery rule "%4$s" on "%5$s".',
								$graph['ymin_item_1']['key'],
								$graph['ymin_item_1']['host'],
								$graph['name'],
								$item['name'],
								$host
							));
						}

						$graph['ymin_itemid'] = $itemId;
					}

					if ($graph['ymax_item_1']) {
						$hostId = $this->referencer->resolveHostOrTemplate($graph['ymax_item_1']['host']);
						$itemId = $hostId
							? $this->referencer->resolveItem($hostId, $graph['ymax_item_1']['key'])
							: false;

						if (!$itemId) {
							throw new Exception(_s(
								'Cannot find item "%1$s" on "%2$s" used as the Y axis MAX value for graph prototype "%3$s" of discovery rule "%4$s" on "%5$s".',
								$graph['ymax_item_1']['key'],
								$graph['ymax_item_1']['host'],
								$graph['name'],
								$item['name'],
								$host
							));
						}

						$graph['ymax_itemid'] = $itemId;
					}

					foreach ($graph['gitems'] as &$gitem) {
						$gitemHostId = $this->referencer->resolveHostOrTemplate($gitem['item']['host']);
						$gitem['itemid'] = $gitemHostId
							? $this->referencer->resolveItem($gitemHostId, $gitem['item']['key'])
							: false;

						if (!$gitem['itemid']) {
							throw new Exception(_s(
								'Cannot find item "%1$s" on "%2$s" used in graph prototype "%3$s" of discovery rule "%4$s" on "%5$s".',
								$gitem['item']['key'],
								$gitem['item']['host'],
								$graph['name'],
								$item['name'],
								$host
							));
						}
					}
					unset($gitem);

					$graphId = $this->referencer->resolveGraph($gitemHostId, $graph['name']);
					if ($graphId) {
						$graph['graphid'] = $graphId;
						$graphsToUpdate[] = $graph;
					}
					else {
						$graphsToCreate[] = $graph;
					}
				}
			}
		}

		if ($triggersToCreate) {
			$result = API::TriggerPrototype()->create($triggersToCreate);

			foreach ($result['triggerids'] as $tnum => $triggerid) {
				$trigger = $triggersToCreate[$tnum];
				$this->referencer->addTriggerRef($trigger['description'], $trigger['expression'],
					$trigger['recovery_expression'], $triggerid
				);
			}
		}
		if ($triggersToUpdate) {
			API::TriggerPrototype()->update($triggersToUpdate);
		}
		if ($graphsToCreate) {
			API::GraphPrototype()->create($graphsToCreate);
			$this->referencer->refreshGraphs();
		}
		if ($graphsToUpdate) {
			API::GraphPrototype()->update($graphsToUpdate);
		}

		$this->processTriggerPrototypeDependencies($triggers);
	}

	/**
	 * Update trigger dependencies
	 *
	 * @param array $triggers
	 *
	 * @throws Exception
	 */
	protected function processTriggerPrototypeDependencies(array $triggers) {
		$dependencies = [];

		foreach ($triggers as $trigger) {
			if (!array_key_exists('dependencies', $trigger)) {
				continue;
			}

			$deps = [];
			$triggerid = $this->referencer->resolveTrigger($trigger['description'], $trigger['expression'],
				$trigger['recovery_expression']
			);

			foreach ($trigger['dependencies'] as $dependency) {
				$dep_triggerid = $this->referencer->resolveTrigger($dependency['name'], $dependency['expression'],
					$dependency['recovery_expression']
				);

				if (!$dep_triggerid) {
					throw new Exception(_s('Trigger prototype "%1$s" depends on trigger "%2$s", which does not exist.',
						$trigger['description'],
						$dependency['name']
					));
				}

				$deps[] = ['triggerid' => $dep_triggerid];
			}

			$dependencies[] = [
				'triggerid' => $triggerid,
				'dependencies' => $deps
			];
		}

		if ($dependencies) {
			API::TriggerPrototype()->update($dependencies);
		}
	}

	/**
	 * Import web scenarios.
	 */
	protected function processHttpTests() {
		if (!$this->options['httptests']['createMissing'] && !$this->options['httptests']['updateExisting']) {
			return;
		}

		$all_httptests = $this->getFormattedHttpTests();

		if (!$all_httptests) {
			return;
		}

		$httptests_to_create = [];
		$httptests_to_update = [];

		foreach ($all_httptests as $host => $httptests) {
			$hostid = $this->referencer->resolveHostOrTemplate($host);

			if (!$this->importedObjectContainer->isHostProcessed($hostid)
					&& !$this->importedObjectContainer->isTemplateProcessed($hostid)) {
				continue;
			}

			foreach ($httptests as $httptest) {
				if (array_key_exists('name', $httptest['application'])) {
					$applicationid = $this->referencer->resolveApplication($hostid, $httptest['application']['name']);

					if ($applicationid === false) {
						throw new Exception(_s('Web scenario "%1$s" on "%2$s": application "%3$s" does not exist.',
							$httptest['name'], $host, $httptest['application']['name']));
					}

					$httptest['applicationid'] = $applicationid;
				}
				else {
					$httptest['applicationid'] = 0;
				}

				unset($httptest['application']);

				$httptestid = $this->referencer->resolveHttpTest($hostid, $httptest['name']);

				if ($httptestid !== false) {
					foreach ($httptest['steps'] as &$httpstep) {
						$httpstepid = $this->referencer->resolveHttpStep($hostid, $httptestid, $httpstep['name']);

						if ($httpstepid !== false) {
							$httpstep['httpstepid'] = $httpstepid;
						}
					}
					unset($httpstep);

					$httptest['httptestid'] = $httptestid;
					$httptests_to_update[] = $httptest;
				}
				else {
					$httptest['hostid'] = $hostid;
					$httptests_to_create[] = $httptest;
				}
			}
		}

		// create/update web scenarios and create a hash hostid->name->httptestid
		if ($this->options['httptests']['createMissing'] && $httptests_to_create) {
			API::HttpTest()->create($httptests_to_create);
		}

		if ($this->options['httptests']['updateExisting'] && $httptests_to_update) {
			API::HttpTest()->update($httptests_to_update);
		}

		$this->referencer->refreshHttpTests();
	}

	/**
	 * Import graphs.
	 *
	 * @throws Exception
	 */
	protected function processGraphs() {
		if (!$this->options['graphs']['createMissing'] && !$this->options['graphs']['updateExisting']) {
			return;
		}

		$allGraphs = $this->getFormattedGraphs();

		if (!$allGraphs) {
			return;
		}

		$graphsToCreate = [];
		$graphsToUpdate = [];

		foreach ($allGraphs as $graph) {
			if ($graph['ymin_item_1']) {
				$hostId = $this->referencer->resolveHostOrTemplate($graph['ymin_item_1']['host']);
				$itemId = $hostId ? $this->referencer->resolveItem($hostId, $graph['ymin_item_1']['key']) : false;

				if (!$itemId) {
					throw new Exception(_s(
						'Cannot find item "%1$s" on "%2$s" used as the Y axis MIN value for graph "%3$s".',
						$graph['ymin_item_1']['key'],
						$graph['ymin_item_1']['host'],
						$graph['name']
					));
				}

				$graph['ymin_itemid'] = $itemId;
			}

			if ($graph['ymax_item_1']) {
				$hostId = $this->referencer->resolveHostOrTemplate($graph['ymax_item_1']['host']);
				$itemId = $hostId ? $this->referencer->resolveItem($hostId, $graph['ymax_item_1']['key']) : false;

				if (!$itemId) {
					throw new Exception(_s(
						'Cannot find item "%1$s" on "%2$s" used as the Y axis MAX value for graph "%3$s".',
						$graph['ymax_item_1']['key'],
						$graph['ymax_item_1']['host'],
						$graph['name']
					));
				}

				$graph['ymax_itemid'] = $itemId;
			}

			if (isset($graph['gitems']) && $graph['gitems']) {
				foreach ($graph['gitems'] as &$gitem) {
					$gitemHostId = $this->referencer->resolveHostOrTemplate($gitem['item']['host']);
					$gitem['itemid'] = $gitemHostId
						? $this->referencer->resolveItem($gitemHostId, $gitem['item']['key'])
						: false;

					if (!$gitem['itemid']) {
						throw new Exception(_s(
							'Cannot find item "%1$s" on "%2$s" used in graph "%3$s".',
							$gitem['item']['key'],
							$gitem['item']['host'],
							$graph['name']
						));
					}
				}
				unset($gitem);

				$graphId = $this->referencer->resolveGraph($gitemHostId, $graph['name']);

				if ($graphId) {
					$graph['graphid'] = $graphId;
					$graphsToUpdate[] = $graph;
				}
				else {
					$graphsToCreate[] = $graph;
				}
			}
		}

		if ($this->options['graphs']['createMissing'] && $graphsToCreate) {
			API::Graph()->create($graphsToCreate);
		}
		if ($this->options['graphs']['updateExisting'] && $graphsToUpdate) {
			API::Graph()->update($graphsToUpdate);
		}

		$this->referencer->refreshGraphs();
	}

	/**
	 * Import triggers.
	 */
	protected function processTriggers() {
		if (!$this->options['triggers']['createMissing'] && !$this->options['triggers']['updateExisting']) {
			return;
		}

		$allTriggers = $this->getFormattedTriggers();

		if (!$allTriggers) {
			return;
		}

		$triggersToCreate = [];
		$triggersToUpdate = [];
		// the list of triggers to process dependencies
		$triggers = [];

		foreach ($allTriggers as $trigger) {
			$triggerId = $this->referencer->resolveTrigger($trigger['description'], $trigger['expression'],
				$trigger['recovery_expression']
			);

			if ($triggerId) {
				if ($this->options['triggers']['updateExisting']) {
					$triggers[] = $trigger;

					$trigger['triggerid'] = $triggerId;
					unset($trigger['dependencies']);
					$triggersToUpdate[] = $trigger;
				}
			}
			else {
				if ($this->options['triggers']['createMissing']) {
					$triggers[] = $trigger;

					unset($trigger['dependencies']);
					$triggersToCreate[] = $trigger;
				}
			}
		}

		if ($triggersToCreate) {
			$result = API::Trigger()->create($triggersToCreate);

			foreach ($result['triggerids'] as $tnum => $triggerid) {
				$trigger = $triggersToCreate[$tnum];
				$this->referencer->addTriggerRef($trigger['description'], $trigger['expression'],
					$trigger['recovery_expression'], $triggerid
				);
			}
		}

		if ($triggersToUpdate) {
			API::Trigger()->update($triggersToUpdate);
		}

		// refresh triggers because template triggers can be inherited to host and used in maps
		$this->referencer->refreshTriggers();

		$this->processTriggerDependencies($triggers);
	}

	/**
	 * Update trigger dependencies
	 *
	 * @param array $triggers
	 *
	 * @throws Exception
	 */
	protected function processTriggerDependencies(array $triggers) {
		$dependencies = [];

		foreach ($triggers as $trigger) {
			if (!array_key_exists('dependencies', $trigger)) {
				continue;
			}

			$deps = [];
			$triggerid = $this->referencer->resolveTrigger($trigger['description'], $trigger['expression'],
				$trigger['recovery_expression']
			);

			foreach ($trigger['dependencies'] as $dependency) {
				$dep_triggerid = $this->referencer->resolveTrigger($dependency['name'], $dependency['expression'],
					$dependency['recovery_expression']
				);

				if (!$dep_triggerid) {
					throw new Exception(_s('Trigger "%1$s" depends on trigger "%2$s", which does not exist.',
						$trigger['description'],
						$dependency['name']
					));
				}

				$deps[] = ['triggerid' => $dep_triggerid];
			}

			$dependencies[] = [
				'triggerid' => $triggerid,
				'dependencies' => $deps
			];
		}

		if ($dependencies) {
			API::Trigger()->update($dependencies);
		}
	}

	/**
	 * Import images.
	 *
	 * @throws Exception
	 *
	 * @return null
	 */
	protected function processImages() {
		if (CWebUser::$data['type'] != USER_TYPE_SUPER_ADMIN
				|| (!$this->options['images']['updateExisting'] && !$this->options['images']['createMissing'])) {
			return;
		}

		$allImages = $this->getFormattedImages();

		if (!$allImages) {
			return;
		}

		$allImages = zbx_toHash($allImages, 'name');

		$dbImages = API::Image()->get([
			'output' => ['imageid', 'name'],
			'filter' => ['name' => array_keys($allImages)]
		]);
		$dbImages = zbx_toHash($dbImages, 'name');

		$imagesToUpdate = [];
		$imagesToCreate = [];

		foreach ($allImages as $imageName => $image) {
			if (isset($dbImages[$imageName])) {
				$image['imageid'] = $dbImages[$imageName]['imageid'];
				unset($image['imagetype']);
				$imagesToUpdate[] = $image;
			}
			else {
				$imagesToCreate[] = $image;
			}
		}

		if ($this->options['images']['createMissing'] && $imagesToCreate) {
			API::Image()->create($imagesToCreate);
		}

		if ($this->options['images']['updateExisting'] && $imagesToUpdate) {
			API::Image()->update($imagesToUpdate);
		}
	}

	/**
	 * Import maps.
	 */
	protected function processMaps() {
		if ($this->options['maps']['updateExisting'] || $this->options['maps']['createMissing']) {
			$maps = $this->getFormattedMaps();
			if ($maps) {
				$mapImporter = new CMapImporter($this->options, $this->referencer, $this->importedObjectContainer);
				$mapImporter->import($maps);
			}
		}
	}

	/**
	 * Import screens.
	 */
	protected function processScreens() {
		if ($this->options['screens']['updateExisting'] || $this->options['screens']['createMissing']) {
			$screens = $this->getFormattedScreens();
			if ($screens) {
				$screenImporter = new CScreenImporter($this->options, $this->referencer,
					$this->importedObjectContainer
				);
				$screenImporter->import($screens);
			}
		}
	}

	/**
	 * Import template screens.
	 */
	protected function processTemplateScreens() {
		if ($this->options['templateScreens']['updateExisting']
				|| $this->options['templateScreens']['createMissing']
				|| $this->options['templateScreens']['deleteMissing']) {
			$screens = $this->getFormattedTemplateScreens();
			$screenImporter = new CTemplateScreenImporter($this->options, $this->referencer,
				$this->importedObjectContainer
			);
			$screenImporter->delete($screens);
			$screenImporter->import($screens);
		}
	}

	/**
	 * Deletes items from DB that are missing in XML.
	 *
	 * @return null
	 */
	protected function deleteMissingItems() {
		if (!$this->options['items']['deleteMissing']) {
			return;
		}

		$processedHostIds = $this->importedObjectContainer->getHostIds();
		$processedTemplateIds = $this->importedObjectContainer->getTemplateIds();

		$processedHostIds = array_merge($processedHostIds, $processedTemplateIds);

		// no hosts or templates have been processed
		if (!$processedHostIds) {
			return;
		}

		$itemIdsXML = [];

		$allItems = $this->getFormattedItems();

		if ($allItems) {
			foreach ($allItems as $host => $items) {
				$hostId = $this->referencer->resolveHostOrTemplate($host);

				foreach ($items as $item) {
					$itemId = $this->referencer->resolveItem($hostId, $item['key_']);

					if ($itemId) {
						$itemIdsXML[$itemId] = $itemId;
					}
				}
			}
		}

		$dbItemIds = API::Item()->get([
			'output' => ['itemid'],
			'hostids' => $processedHostIds,
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);

		$itemsToDelete = array_diff_key($dbItemIds, $itemIdsXML);

		if ($itemsToDelete) {
			API::Item()->delete(array_keys($itemsToDelete));
		}

		$this->referencer->refreshItems();
	}

	/**
	 * Deletes applications from DB that are missing in XML.
	 *
	 * @return null
	 */
	protected function deleteMissingApplications() {
		if (!$this->options['applications']['deleteMissing']) {
			return;
		}

		$processedHostIds = $this->importedObjectContainer->getHostIds();
		$processedTemplateIds = $this->importedObjectContainer->getTemplateIds();

		$processedHostIds = array_merge($processedHostIds, $processedTemplateIds);

		// no hosts or templates have been processed
		if (!$processedHostIds) {
			return;
		}

		$applicationIdsXML = [];

		$allApplications = $this->getFormattedApplications();

		if ($allApplications) {
			foreach ($allApplications as $host => $applications) {
				$hostId = $this->referencer->resolveHostOrTemplate($host);

				foreach ($applications as $application) {
					$applicationId = $this->referencer->resolveApplication($hostId, $application['name']);

					if ($applicationId) {
						$applicationIdsXML[$applicationId] = $applicationId;
					}
				}
			}
		}

		$dbApplicationIds = API::Application()->get([
			'output' => ['applicationid'],
			'hostids' => $processedHostIds,
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);

		$applicationsToDelete = array_diff_key($dbApplicationIds, $applicationIdsXML);
		if ($applicationsToDelete) {
			API::Application()->delete(array_keys($applicationsToDelete));
		}

		// refresh applications because templated ones can be inherited to host and used in items
		$this->referencer->refreshApplications();
	}

	/**
	 * Deletes triggers from DB that are missing in XML.
	 *
	 * @return null
	 */
	protected function deleteMissingTriggers() {
		if (!$this->options['triggers']['deleteMissing']) {
			return;
		}

		$processedHostIds = $this->importedObjectContainer->getHostIds();
		$processedTemplateIds = $this->importedObjectContainer->getTemplateIds();

		$processedHostIds = array_merge($processedHostIds, $processedTemplateIds);

		// no hosts or templates have been processed
		if (!$processedHostIds) {
			return;
		}

		$triggersXML = [];

		$allTriggers = $this->getFormattedTriggers();

		if ($allTriggers) {
			foreach ($allTriggers as $trigger) {
				$triggerId = $this->referencer->resolveTrigger($trigger['description'], $trigger['expression'],
					$trigger['recovery_expression']
				);

				if ($triggerId) {
					$triggersXML[$triggerId] = $triggerId;
				}
			}
		}

		$dbTriggerIds = API::Trigger()->get([
			'output' => ['triggerid'],
			'hostids' => $processedHostIds,
			'selectHosts' => ['hostid'],
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);

		// check that potentially deletable trigger belongs to same hosts that are in XML
		// if some triggers belong to more hosts than current XML contains, don't delete them
		$triggersToDelete = array_diff_key($dbTriggerIds, $triggersXML);
		$triggerIdsToDelete = [];
		$processedHostIds = array_flip($processedHostIds);

		foreach ($triggersToDelete as $triggerId => $trigger) {
			$triggerHostIds = array_flip(zbx_objectValues($trigger['hosts'], 'hostid'));
			if (!array_diff_key($triggerHostIds, $processedHostIds)) {
				$triggerIdsToDelete[] = $triggerId;
			}
		}

		if ($triggerIdsToDelete) {
			API::Trigger()->delete($triggerIdsToDelete);
		}

		// refresh triggers because template triggers can be inherited to host and used in maps
		$this->referencer->refreshTriggers();
	}

	/**
	 * Deletes graphs from DB that are missing in XML.
	 *
	 * @return null
	 */
	protected function deleteMissingGraphs() {
		if (!$this->options['graphs']['deleteMissing']) {
			return;
		}

		$processedHostIds = $this->importedObjectContainer->getHostIds();
		$processedTemplateIds = $this->importedObjectContainer->getTemplateIds();

		$processedHostIds = array_merge($processedHostIds, $processedTemplateIds);

		// no hosts or templates have been processed
		if (!$processedHostIds) {
			return;
		}

		$graphsIdsXML = [];

		// gather host IDs for graphs that exist in XML
		$allGraphs = $this->getFormattedGraphs();

		if ($allGraphs) {
			foreach ($allGraphs as $graph) {
				if (isset($graph['gitems']) && $graph['gitems']) {
					foreach ($graph['gitems'] as $gitem) {
						$gitemHostId = $this->referencer->resolveHostOrTemplate($gitem['item']['host']);
						$graphId = $this->referencer->resolveGraph($gitemHostId, $graph['name']);

						if ($graphId) {
							$graphsIdsXML[$graphId] = $graphId;
						}
					}
				}
			}
		}

		$dbGraphIds = API::Graph()->get([
			'output' => ['graphid'],
			'hostids' => $processedHostIds,
			'selectHosts' => ['hostid'],
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);

		// check that potentially deletable graph belongs to same hosts that are in XML
		// if some graphs belong to more hosts than current XML contains, don't delete them
		$graphsToDelete = array_diff_key($dbGraphIds, $graphsIdsXML);
		$graphIdsToDelete = [];
		$processedHostIds = array_flip($processedHostIds);

		foreach ($graphsToDelete as $graphId => $graph) {
			$graphHostIds = array_flip(zbx_objectValues($graph['hosts'], 'hostid'));

			if (!array_diff_key($graphHostIds, $processedHostIds)) {
				$graphIdsToDelete[] = $graphId;
			}
		}

		if ($graphIdsToDelete) {
			API::Graph()->delete($graphIdsToDelete);
		}

		$this->referencer->refreshGraphs();
	}

	/**
	 * Deletes discovery rules and prototypes from DB that are missing in XML.
	 *
	 * @return null
	 */
	protected function deleteMissingDiscoveryRules() {
		if (!$this->options['discoveryRules']['deleteMissing']) {
			return;
		}

		$processedHostIds = $this->importedObjectContainer->getHostIds();
		$processedTemplateIds = $this->importedObjectContainer->getTemplateIds();

		$processedHostIds = array_merge($processedHostIds, $processedTemplateIds);

		// no hosts or templates have been processed
		if (!$processedHostIds) {
			return;
		}

		$discoveryRuleIdsXML = [];

		$allDiscoveryRules = $this->getFormattedDiscoveryRules();

		if ($allDiscoveryRules) {
			foreach ($allDiscoveryRules as $host => $discoveryRules) {
				$hostId = $this->referencer->resolveHostOrTemplate($host);

				foreach ($discoveryRules as $discoveryRule) {
					$discoveryRuleId = $this->referencer->resolveItem($hostId, $discoveryRule['key_']);

					if ($discoveryRuleId) {
						$discoveryRuleIdsXML[$discoveryRuleId] = $discoveryRuleId;
					}
				}
			}
		}

		$dbDiscoveryRuleIds = API::DiscoveryRule()->get([
			'output' => ['itemid'],
			'hostids' => $processedHostIds,
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false
		]);

		$discoveryRulesToDelete = array_diff_key($dbDiscoveryRuleIds, $discoveryRuleIdsXML);

		if ($discoveryRulesToDelete) {
			API::DiscoveryRule()->delete(array_keys($discoveryRulesToDelete));
		}

		// refresh discovery rules because templated ones can be inherited to host and used for prototypes
		$this->referencer->refreshItems();

		$hostPrototypeIdsXML = [];
		$triggerPrototypeIdsXML = [];
		$itemPrototypeIdsXML = [];
		$graphPrototypeIdsXML = [];

		foreach ($allDiscoveryRules as $host => $discoveryRules) {
			$hostId = $this->referencer->resolveHostOrTemplate($host);

			foreach ($discoveryRules as $discoveryRule) {
				$discoveryRuleId = $this->referencer->resolveItem($hostId, $discoveryRule['key_']);

				if ($discoveryRuleId) {
					// gather host prototype IDs to delete
					foreach ($discoveryRule['host_prototypes'] as $hostPrototype) {
						$hostPrototypeId = $this->referencer->resolveHostPrototype($hostId, $discoveryRuleId,
							$hostPrototype['host']
						);

						if ($hostPrototypeId) {
							$hostPrototypeIdsXML[$hostPrototypeId] = $hostPrototypeId;
						}
					}

					// gather trigger prototype IDs to delete
					foreach ($discoveryRule['trigger_prototypes'] as $triggerPrototype) {
						$triggerPrototypeId = $this->referencer->resolveTrigger($triggerPrototype['description'],
							$triggerPrototype['expression'], $triggerPrototype['recovery_expression']
						);

						if ($triggerPrototypeId) {
							$triggerPrototypeIdsXML[$triggerPrototypeId] = $triggerPrototypeId;
						}
					}

					// gather graph prototype IDs to delete
					foreach ($discoveryRule['graph_prototypes'] as $graphPrototype) {
						$graphPrototypeId = $this->referencer->resolveGraph($hostId, $graphPrototype['name']);

						if ($graphPrototypeId) {
							$graphPrototypeIdsXML[$graphPrototypeId] = $graphPrototypeId;
						}
					}

					// gather item prototype IDs to delete
					foreach ($discoveryRule['item_prototypes'] as $itemPrototype) {
						$itemPrototypeId = $this->referencer->resolveItem($hostId, $itemPrototype['key_']);

						if ($itemPrototypeId) {
							$itemPrototypeIdsXML[$itemPrototypeId] = $itemPrototypeId;
						}
					}
				}
			}
		}

		// delete missing host prototypes
		$dbHostPrototypeIds = API::HostPrototype()->get([
			'output' => ['hostid'],
			'discoveryids' => $discoveryRuleIdsXML,
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false
		]);

		$hostPrototypesToDelete = array_diff_key($dbHostPrototypeIds, $hostPrototypeIdsXML);

		if ($hostPrototypesToDelete) {
			API::HostPrototype()->delete(array_keys($hostPrototypesToDelete));
		}

		// delete missing trigger prototypes
		$dbTriggerPrototypeIds = API::TriggerPrototype()->get([
			'output' => ['triggerid'],
			'hostids' => $processedHostIds,
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false
		]);

		$triggerPrototypesToDelete = array_diff_key($dbTriggerPrototypeIds, $triggerPrototypeIdsXML);

		// unlike triggers that belong to multiple hosts, trigger prototypes do not, so we just delete them
		if ($triggerPrototypesToDelete) {
			API::TriggerPrototype()->delete(array_keys($triggerPrototypesToDelete));
		}

		// delete missing graph prototypes
		$dbGraphPrototypeIds = API::GraphPrototype()->get([
			'output' => ['graphid'],
			'hostids' => $processedHostIds,
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false
		]);

		$graphPrototypesToDelete = array_diff_key($dbGraphPrototypeIds, $graphPrototypeIdsXML);

		// unlike graphs that belong to multiple hosts, graph prototypes do not, so we just delete them
		if ($graphPrototypesToDelete) {
			API::GraphPrototype()->delete(array_keys($graphPrototypesToDelete));
		}

		// delete missing item prototypes
		$dbItemPrototypeIds = API::ItemPrototype()->get([
			'output' => ['itemid'],
			'hostids' => $processedHostIds,
			'preservekeys' => true,
			'nopermissions' => true,
			'inherited' => false
		]);

		$itemPrototypesToDelete = array_diff_key($dbItemPrototypeIds, $itemPrototypeIdsXML);

		if ($itemPrototypesToDelete) {
			API::ItemPrototype()->delete(array_keys($itemPrototypesToDelete));
		}
	}

	/**
	 * Deletes web scenarios from DB that are missing in XML.
	 */
	protected function deleteMissingHttpTests() {
		if (!$this->options['httptests']['deleteMissing']) {
			return;
		}

		$processed_hostids = array_merge(
			$this->importedObjectContainer->getHostIds(),
			$this->importedObjectContainer->getTemplateIds()
		);

		// no hosts or templates have been processed
		if (!$processed_hostids) {
			return;
		}

		$xml_httptestids = [];

		$all_httptests = $this->getFormattedHttpTests();

		if ($all_httptests) {
			foreach ($all_httptests as $host => $httptests) {
				$hostid = $this->referencer->resolveHostOrTemplate($host);

				foreach ($httptests as $httptest) {
					$httptestid = $this->referencer->resolveHttpTest($hostid, $httptest['name']);

					if ($httptestid) {
						$xml_httptestids[$httptestid] = true;
					}
				}
			}
		}

		$db_httptestids = API::HttpTest()->get([
			'output' => [],
			'hostids' => $processed_hostids,
			'inherited' => false,
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$del_httptestids = array_diff_key($db_httptestids, $xml_httptestids);

		if ($del_httptestids) {
			API::HttpTest()->delete(array_keys($del_httptestids));
		}

		$this->referencer->refreshHttpTests();
	}

	/**
	 * Get formatted groups.
	 *
	 * @return array
	 */
	protected function getFormattedGroups() {
		if (!isset($this->formattedData['groups'])) {
			$this->formattedData['groups'] = $this->adapter->getGroups();
		}

		return $this->formattedData['groups'];
	}

	/**
	 * Get formatted templates.
	 *
	 * @return array
	 */
	public function getFormattedTemplates() {
		if (!isset($this->formattedData['templates'])) {
			$this->formattedData['templates'] = $this->adapter->getTemplates();
		}

		return $this->formattedData['templates'];
	}

	/**
	 * Get formatted hosts.
	 *
	 * @return array
	 */
	public function getFormattedHosts() {
		if (!isset($this->formattedData['hosts'])) {
			$this->formattedData['hosts'] = $this->adapter->getHosts();
		}

		return $this->formattedData['hosts'];
	}

	/**
	 * Get formatted applications.
	 *
	 * @return array
	 */
	protected function getFormattedApplications() {
		if (!isset($this->formattedData['applications'])) {
			$this->formattedData['applications'] = $this->adapter->getApplications();
		}

		return $this->formattedData['applications'];
	}

	/**
	 * Get formatted value maps.
	 *
	 * @return array
	 */
	protected function getFormattedValueMaps() {
		if (!isset($this->formattedData['valueMaps'])) {
			$this->formattedData['valueMaps'] = $this->adapter->getValueMaps();
		}

		return $this->formattedData['valueMaps'];
	}

	/**
	 * Get formatted items.
	 *
	 * @return array
	 */
	protected function getFormattedItems() {
		if (!isset($this->formattedData['items'])) {
			$this->formattedData['items'] = $this->adapter->getItems();
		}

		return $this->formattedData['items'];
	}

	/**
	 * Get formatted discovery rules.
	 *
	 * @return array
	 */
	protected function getFormattedDiscoveryRules() {
		if (!isset($this->formattedData['discoveryRules'])) {
			$this->formattedData['discoveryRules'] = $this->adapter->getDiscoveryRules();
		}

		return $this->formattedData['discoveryRules'];
	}

	/**
	 * Get formatted web scenarios.
	 *
	 * @return array
	 */
	protected function getFormattedHttpTests() {
		if (!array_key_exists('httptests', $this->formattedData)) {
			$this->formattedData['httptests'] = $this->adapter->getHttpTests();
		}

		return $this->formattedData['httptests'];
	}

	/**
	 * Get formatted web scenario steps.
	 *
	 * @return array
	 */
	protected function getFormattedHttpSteps() {
		if (!array_key_exists('httpsteps', $this->formattedData)) {
			$this->formattedData['httpsteps'] = $this->adapter->getHttpSteps();
		}

		return $this->formattedData['httpsteps'];
	}

	/**
	 * Get formatted triggers.
	 *
	 * @return array
	 */
	protected function getFormattedTriggers() {
		if (!isset($this->formattedData['triggers'])) {
			$this->formattedData['triggers'] = $this->adapter->getTriggers();
		}

		return $this->formattedData['triggers'];
	}

	/**
	 * Get formatted graphs.
	 *
	 * @return array
	 */
	protected function getFormattedGraphs() {
		if (!isset($this->formattedData['graphs'])) {
			$this->formattedData['graphs'] = $this->adapter->getGraphs();
		}

		return $this->formattedData['graphs'];
	}

	/**
	 * Get formatted images.
	 *
	 * @return array
	 */
	protected function getFormattedImages() {
		if (!isset($this->formattedData['images'])) {
			$this->formattedData['images'] = $this->adapter->getImages();
		}

		return $this->formattedData['images'];
	}

	/**
	 * Get formatted maps.
	 *
	 * @return array
	 */
	protected function getFormattedMaps() {
		if (!isset($this->formattedData['maps'])) {
			$this->formattedData['maps'] = $this->adapter->getMaps();
		}

		return $this->formattedData['maps'];
	}

	/**
	 * Get formatted screens.
	 *
	 * @return array
	 */
	protected function getFormattedScreens() {
		if (!isset($this->formattedData['screens'])) {
			$this->formattedData['screens'] = $this->adapter->getScreens();
		}

		return $this->formattedData['screens'];
	}

	/**
	 * Get formatted template screens.
	 *
	 * @return array
	 */
	protected function getFormattedTemplateScreens() {
		if (!isset($this->formattedData['templateScreens'])) {
				$this->formattedData['templateScreens'] = $this->adapter->getTemplateScreens();
		}

		return $this->formattedData['templateScreens'];
	}

	/**
	 * Get items keys order tree, to ensure that master item will be inserted or updated before any of it dependent
	 * item. Returns associative array where key is item index and value is item dependency level.
	 *
	 * @param string $master_key_identifier     String containing master key name used to identify item master.
	 *
	 * @return array
	 */
	protected function getItemsOrder($master_key_identifier) {
		$entities = $this->getFormattedItems();
		$data_provider = API::Item();
		return $this->getEntitiesOrder($master_key_identifier, $entities, $data_provider);
	}

	/**
	 * Get discovery rules items prototypes keys order tree, to ensure that master item will be inserted or updated
	 * before any of it dependent item. Returns associative array where key is item prototype index and value is item
	 * prototype dependency level.
	 *
	 * @param string $master_key_identifier     String containing master key name used to identify item master.
	 *
	 * @return array
	 */
	protected function getDiscoveryRulesItemsOrder($master_key_identifier) {
		$discovery_rules = $this->getFormattedDiscoveryRules();
		$data_povider = API::ItemPrototype();
		$entities_order = [];

		foreach ($discovery_rules as $host_key => $items) {
			foreach ($items as $item) {
				if ($item['item_prototypes']) {
					$item_prototypes = [$host_key => $item['item_prototypes']];
					$item_prototypes = $this->getEntitiesOrder($master_key_identifier, $item_prototypes, $data_povider);
					$entities_order[$host_key][$item['key_']] = $item_prototypes[$host_key];
				}
			}
		}

		return $entities_order;
	}

	/**
	 * Generic method to get entities order tree, to ensure that master entity will be inserted or updated before any
	 * of it dependent entities.
	 * Returns associative array where key is entity index in source array grouped by host key and value is entity
	 * dependency level.
	 *
	 * @param string               $master_key_identifier   String containing master key name to identify item master.
	 * @param array                $host_entities           Associative array of host key and host items.
	 * @param CItem|CItemPrototype $data_provider           Service to get master items not found in supplied input.
	 *
	 * @throws Exception if data is invalid.
	 *
	 * @return array
	 */
	protected function getEntitiesOrder($master_key_identifier, $host_entities, $data_provider) {
		$find_keys = [];
		$find_hosts = [];
		$resolved_masters_cache = [];
		$hostkey_to_hostid = array_fill_keys(array_keys($host_entities), 0);

		foreach ($hostkey_to_hostid as $host_key => &$hostid) {
			$hostid = $this->referencer->resolveHostOrTemplate($host_key);
		}

		foreach ($host_entities as $host_key => $entities) {
			if (!array_key_exists($host_key, $hostkey_to_hostid)) {
				throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'host',
					_s('value "%1$s" not found', $host_key)
				));
			}

			if (!array_key_exists($host_key, $resolved_masters_cache)) {
				$resolved_masters_cache[$host_key] = [];
			}

			// Cache input array entities.
			foreach ($entities as $entity_key => $entity) {
				$resolved_masters_cache[$host_key][$entity['key_']] = [
					'type' => $entity['type'],
					$master_key_identifier => $entity[$master_key_identifier]
				];
			}

			// Search master entities not in input array. Will be requested from database.
			foreach ($entities as $entity_key => $entity) {
				if ($entity['type'] == ITEM_TYPE_DEPENDENT
						&& array_key_exists('key', $entity[$master_key_identifier])) {
					$find_hosts[$hostkey_to_hostid[$host_key]] = true;
					$find_keys[$entity[$master_key_identifier]['key']] = true;
				}
			}
		}

		// There are entities to resolve from database, resolve and cache them recursively.
		if ($find_keys) {
			// For existing items, 'referencer' should be initialized before 'addItemRef' method will be used.
			// Registering reference when property 'itemRefs' is empty, will not allow first call of
			// 'resolveValueMap' method update references to existing items.
			$this->referencer->initItemsReferences();
			$resolved_entities = $data_provider->get([
				'output' => ['key_', 'type', 'hostid', 'master_itemid'],
				'hostids' => array_keys($find_hosts),
				'filter' => ['key_' => array_keys($find_keys)],
				'preservekeys' => true
			]);
			$resolve_entity_keys = [];
			$host_entity_idkey_hash = [];

			for ($level = 0; $level < ZBX_DEPENDENT_ITEM_MAX_LEVELS; $level++) {
				$find_ids = [];

				foreach ($resolved_entities as $entityid => $entity) {
					$host_key = array_search($entity['hostid'], $hostkey_to_hostid);
					$entity['key'] = $entity['key_'];
					unset($entity['key_']);
					$host_entity_idkey_hash[$host_key][$entityid] = $entity['key'];
					$this->referencer->addItemRef($entity['hostid'], $entity['key'], $entityid);
					$cache_entity = [
						'type' => $entity['type']
					];

					if ($entity['type'] == ITEM_TYPE_DEPENDENT) {
						$master_itemid = $entity['master_itemid'];
						if (array_key_exists($master_itemid, $host_entity_idkey_hash[$host_key])) {
							$cache_entity[$master_key_identifier] = [
								'key' => $host_entity_idkey_hash[$host_key][$master_itemid]
							];
						}
						else {
							$find_ids[] = $entity['master_itemid'];
							$resolve_entity_keys[] = [
								'host' => $host_key,
								'key' => $entity['key'],
								'master_itemid' => $entity['master_itemid']
							];
						}
					}
					$resolved_masters_cache[$host_key][$entity['key']] = $cache_entity;
				}

				if ($find_ids) {
					$resolved_entities = $data_provider->get([
						'output' => ['key_', 'type', 'hostid', 'master_itemid'],
						'itemids' => $find_ids,
						'preservekeys' => true
					]);
				}
				else {
					break;
				}
			}

			if ($find_ids) {
				throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
					_('maximum number of dependency levels reached')
				));
			}

			foreach ($resolve_entity_keys as $entity) {
				$master_itemid = $entity['master_itemid'];

				if (!array_key_exists($entity['host'], $host_entity_idkey_hash) ||
						!array_key_exists($master_itemid, $host_entity_idkey_hash[$entity['host']])) {
					throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
						_s('value "%1$s" not found', $master_itemid)
					));
				}
				$master_key = $host_entity_idkey_hash[$entity['host']][$master_itemid];
				$resolved_masters_cache[$entity['host']][$entity['key']] += [
					$master_key_identifier => ['key' => $master_key]
				];
			}
			unset($resolve_entity_keys);
			unset($host_entity_idkey_hash);
		}
		// Resolve every entity dependency level.
		$tree = [];

		foreach ($host_entities as $host_key => $entities) {
			$hostid = $hostkey_to_hostid[$host_key];
			$host_items_tree = [];

			foreach ($entities as $entity_index => $entity) {
				$level = 0;
				$traversal_path = [$entity['key_']];

				while ($entity && $entity['type'] == ITEM_TYPE_DEPENDENT) {
					$master_key = $entity[$master_key_identifier]['key'];

					if (array_key_exists($host_key, $resolved_masters_cache)
							&& array_key_exists($master_key, $resolved_masters_cache[$host_key])) {
						$entity = $resolved_masters_cache[$host_key][$master_key];

						if (in_array($master_key, $traversal_path) || ($entity['type'] == ITEM_TYPE_DEPENDENT
								&& $entity[$master_key_identifier]
								&& $master_key == $entity[$master_key_identifier]['key'])) {
							throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
								_('circular item dependency is not allowed')
							));
						}

						$traversal_path[] = $master_key;
						$level++;
					}
					else {
						throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
							_s('value "%1$s" not found', $master_key)
						));
					}

					if ($level > ZBX_DEPENDENT_ITEM_MAX_LEVELS) {
						$traversal = '"'. implode('" => "', $traversal_path) . '"';
						throw new Exception(_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
							_('maximum number of dependency levels reached')
						));
					}
				}
				$host_items_tree[$entity_index] = $level;
			}
			$tree[$host_key] = $host_items_tree;
		}
		// Order item indexes in descending order by nesting level
		foreach ($tree as $host_key => &$item_indexes) {
			asort($item_indexes);
		}
		unset($item_indexes);

		return $tree;
	}
}
