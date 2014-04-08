<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	 * @var CImportReader
	 */
	protected $reader;

	/**
	 * @var CImportFormatter
	 */
	protected $formatter;

	/**
	 * @var CImportReferencer
	 */
	protected $referencer;

	/**
	 * @var array
	 */
	protected $options;

	/**
	 * @var string with import data in one of supported formats
	 */
	protected $source;

	/**
	 * @var array with data read from source string
	 */
	protected $data;

	/**
	 * @var array with formatted data received from formatter
	 */
	protected $formattedData = array();

	/**
	 * Constructor.
	 * Source string must be suitable for reader class,
	 * i.e. if string contains json then reader should be able to read json.
	 *
	 * @param string $source
	 * @param array $options
	 */
	public function __construct($source, array $options = array()) {
		$this->options = array(
			'groups' => array('createMissing' => false),
			'hosts' => array('updateExisting' => false, 'createMissing' => false),
			'templates' => array('updateExisting' => false, 'createMissing' => false),
			'templateScreens' => array('updateExisting' => false, 'createMissing' => false),
			'applications' => array('updateExisting' => false, 'createMissing' => false),
			'templateLinkage' => array('createMissing' => false),
			'items' => array('updateExisting' => false, 'createMissing' => false),
			'discoveryRules' => array('updateExisting' => false, 'createMissing' => false),
			'triggers' => array('updateExisting' => false, 'createMissing' => false),
			'graphs' => array('updateExisting' => false, 'createMissing' => false),
			'screens' => array('updateExisting' => false, 'createMissing' => false),
			'maps' => array('updateExisting' => false, 'createMissing' => false),
			'images' => array('updateExisting' => false, 'createMissing' => false),
		);
		$this->options = array_merge($this->options, $options);

		$this->source = $source;
	}

	/**
	 * Set reader that is used to read data from source string that is passed to constructor.
	 *
	 * @param CImportReader $reader
	 */
	public function setReader(CImportReader $reader) {
		$this->reader = $reader;
	}

	/**
	 * Import configuration data.
	 *
	 * @todo   for 1.8 version import old class CXmlImport18 is used
	 *
	 * @throws Exception
	 * @throws UnexpectedValueException
	 * @return bool
	 */
	public function import() {
		if (empty($this->reader)) {
			throw new UnexpectedValueException('Reader is not set.');
		}

		try {
			// hack to make api throw exceptions
			// this made to not check all api calls results for false return
			czbxrpc::$useExceptions = true;
			DBstart();

			$this->data = $this->reader->read($this->source);

			$version = $this->getImportVersion();

			// if import version is 1.8 we use old class that support it.
			// old import class process hosts, maps and screens separately.
			if ($version == '1.8') {
				CXmlImport18::import($this->source);
				if ($this->options['maps']['updateExisting'] || $this->options['maps']['createMissing']) {
					CXmlImport18::parseMap($this->options);
				}
				if ($this->options['screens']['updateExisting'] || $this->options['screens']['createMissing']) {
					CXmlImport18::parseScreen($this->options);
				}
				if ($this->options['hosts']['updateExisting']
						|| $this->options['hosts']['createMissing']
						|| $this->options['templates']['updateExisting']
						|| $this->options['templates']['createMissing']) {
					CXmlImport18::parseMain($this->options);
				}
			}
			else {
				$this->formatter = $this->getFormatter($version);

				// pass data to formatter
				// export has root key "zabbix_export" which is not passed
				$this->formatter->setData($this->data['zabbix_export']);
				$this->referencer = new CImportReferencer();

				// parse all import for references to resolve them all together with less sql count
				$this->gatherReferences();
				$this->processGroups();
				$this->processTemplates();
				$this->processHosts();
				$this->processApplications();
				$this->processItems();
				$this->processDiscoveryRules();
				$this->processTriggers();
				$this->processGraphs();
				$this->processImages();
				$this->processMaps();

				// screens should be created after all other elements
				$this->processTemplateScreens();
				$this->processScreens();
			}

			// prevent api from throwing exception
			czbxrpc::$useExceptions = false;
			return DBend(true);
		}
		catch (Exception $e) {
			czbxrpc::$useExceptions = false;
			DBend(false);
			throw new Exception($e->getMessage(), $e->getCode());
		}
	}

	/**
	 * Parse all import data and collect references to objects.
	 * For host objects it collects host names, for items - host name and item key, etc.
	 * Collected references are added and resolved via the $this->referencer object.
	 *
	 * @see CImportReferencer
	 */
	protected function gatherReferences() {
		$groupsRefs = array();
		$templatesRefs = array();
		$hostsRefs = array();
		$applicationsRefs = array();
		$itemsRefs = array();
		$valueMapsRefs = array();
		$triggersRefs = array();
		$iconMapsRefs = array();
		$mapsRefs = array();
		$screensRefs = array();
		$macrosRefs = array();
		$proxyRefs = array();

		foreach ($this->getFormattedGroups() as $group) {
			$groupsRefs[$group['name']] = $group['name'];
		}

		foreach ($this->getFormattedTemplates() as $template) {
			$templatesRefs[$template['host']] = $template['host'];

			foreach ($template['groups'] as $group) {
				$groupsRefs[$group['name']] = $group['name'];
			}

			foreach ($template['macros'] as $macro) {
				$macrosRefs[$template['host']][$macro['macro']] = $macro['macro'];
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

			foreach ($host['macros'] as $macro) {
				$macrosRefs[$host['host']][$macro['macro']] = $macro['macro'];
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
				foreach ($discoveryRule['trigger_prototypes'] as $trigerp) {
					$triggersRefs[$trigerp['description']][$trigerp['expression']] = $trigerp['expression'];
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
						$itemsRefs[$gitemItem['host']][$gitemItem['key']] = $gitemItem['key'];
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
			foreach ($graph['gitems'] as $gitem) {
				$gitemItem = $gitem['item'];
				$hostsRefs[$gitemItem['host']] = $gitemItem['host'];
				$itemsRefs[$gitemItem['host']][$gitemItem['key']] = $gitemItem['key'];
			}
		}

		foreach ($this->getFormattedTriggers() as $trigger) {
			$triggersRefs[$trigger['description']][$trigger['expression']] = $trigger['expression'];

			foreach ($trigger['dependencies'] as $dependency) {
				$triggersRefs[$dependency['name']][$dependency['expression']] = $dependency['expression'];
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
							$mapsRefs[$selement['element']['name']] = $selement['element']['name'];
							break;

						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$groupsRefs[$selement['element']['name']] = $selement['element']['name'];
							break;

						case SYSMAP_ELEMENT_TYPE_HOST:
							$hostsRefs[$selement['element']['host']] = $selement['element']['host'];
							break;

						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							$el = $selement['element'];
							$triggersRefs[$el['description']][$el['expression']] = $el['expression'];
							break;
					}
				}
			}

			if (isset($map['links'])) {
				foreach ($map['links'] as $link) {
					if (isset($link['linktriggers'])) {
						foreach ($link['linktriggers'] as $linkTrigger) {
							$t = $linkTrigger['trigger'];
							$triggersRefs[$t['description']][$t['expression']] = $t['expression'];
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
						case SCREEN_RESOURCE_HOSTS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
						case SCREEN_RESOURCE_DATA_OVERVIEW:
						case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
							$groupsRefs[$resource['name']] = $resource['name'];
							break;

						case SCREEN_RESOURCE_HOST_TRIGGERS:
							$hostsRefs[$resource['host']] = $resource['host'];
							break;

						case SCREEN_RESOURCE_GRAPH:
							// TODO: gather graphs too
							break;

						case SCREEN_RESOURCE_SIMPLE_GRAPH:
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
				if (!empty($screen['screenitems'])) {
					foreach ($screen['screenitems'] as $screenItem) {
						$resource = $screenItem['resource'];

						switch ($screenItem['resourcetype']) {
							case SCREEN_RESOURCE_GRAPH:
								// TODO: gather graphs too
								break;

							case SCREEN_RESOURCE_SIMPLE_GRAPH:
							case SCREEN_RESOURCE_PLAIN_TEXT:
								$hostsRefs[$resource['host']] = $resource['host'];
								$itemsRefs[$resource['host']][$resource['key']] = $resource['key'];
								break;
						}
					}
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
		$this->referencer->addIconMaps($iconMapsRefs);
		$this->referencer->addMaps($mapsRefs);
		$this->referencer->addScreens($screensRefs);
		$this->referencer->addMacros($macrosRefs);
		$this->referencer->addProxies($proxyRefs);
	}

	/**
	 * Import groups.
	 */
	protected function processGroups() {
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
		if ($templates = $this->getFormattedTemplates()) {
			$templateImporter = new CTemplateImporter($this->options, $this->referencer);
			$templateImporter->import($templates);
		}
	}

	/**
	 * Import hosts.
	 *
	 * @throws Exception
	 */
	protected function processHosts() {
		if ($hosts = $this->getFormattedHosts()) {
			$hostImporter = new CHostImporter($this->options, $this->referencer);
			$hostImporter->import($hosts);
		}
	}

	/**
	 * Import applications.
	 */
	protected function processApplications() {
		$allApplciations = $this->getFormattedApplications();
		if (empty($allApplciations)) {
			return;
		}

		$applicationsToCreate = array();
		foreach ($allApplciations as $host => $applications) {
			if (!$this->referencer->isProcessedHost($host)) {
				continue;
			}

			$hostid = $this->referencer->resolveHostOrTemplate($host);
			foreach ($applications as $application) {
				$application['hostid'] = $hostid;
				$appId = $this->referencer->resolveApplication($hostid, $application['name']);
				if (!$appId) {
					$applicationsToCreate[] = $application;
				}
			}
		}

		// create the applications and create a hash hostid->name->applicationid
		if (!empty($applicationsToCreate)) {
			$newApplicationsIds = API::Application()->create($applicationsToCreate);
			foreach ($newApplicationsIds['applicationids'] as $anum => $applicationId) {
				$application = $applicationsToCreate[$anum];
				$this->referencer->addApplicationRef($application['hostid'], $application['name'], $applicationId);
			}
		}

		// refresh applications beacuse templated ones can be inherited to host and used in items
		$this->referencer->refreshApplications();
	}

	/**
	 * Import items.
	 */
	protected function processItems() {
		$allItems = $this->getFormattedItems();
		if (empty($allItems)) {
			return;
		}

		$itemsToCreate = array();
		$itemsToUpdate = array();
		foreach ($allItems as $host => $items) {
			if (!$this->referencer->isProcessedHost($host)) {
				continue;
			}

			$hostid = $this->referencer->resolveHostOrTemplate($host);
			foreach ($items as $item) {
				$item['hostid'] = $hostid;

				if (!empty($item['applications'])) {
					$applicationsIds = array();
					foreach ($item['applications'] as $application) {
						if ($applicationId = $this->referencer->resolveApplication($hostid, $application['name'])) {
							$applicationsIds[] = $applicationId;
						}
						else {
							throw new Exception(_s('Item "%1$s" on "%2$s": application "%3$s" does not exist.',
								$item['name'], $host, $application['name']));
						}
					}
					$item['applications'] = $applicationsIds;
				}

				if (isset($item['interface_ref'])) {
					$item['interfaceid'] = $this->referencer->interfacesCache[$hostid][$item['interface_ref']];
				}

				if (!empty($item['valuemap'])) {
					$item['valuemapid'] = $this->referencer->resolveValueMap($item['valuemap']['name']);
				}

				$itemsId = $this->referencer->resolveItem($hostid, $item['key_']);
				if ($itemsId) {
					$item['itemid'] = $itemsId;
					$itemsToUpdate[] = $item;
				}
				else {
					$itemsToCreate[] = $item;
				}
			}
		}

		// create/update the items and create a hash hostid->key_->itemid
		if ($this->options['items']['createMissing'] && $itemsToCreate) {
			$newItemsIds = API::Item()->create($itemsToCreate);
			foreach ($newItemsIds['itemids'] as $inum => $itemid) {
				$item = $itemsToCreate[$inum];
				$this->referencer->addItemRef($item['hostid'], $item['key_'], $itemid);
			}
		}
		if ($this->options['items']['updateExisting'] && $itemsToUpdate) {
			API::Item()->update($itemsToUpdate);
		}

		// refresh items because templated ones can be inherited to host and used in triggers, grahs, etc.
		$this->referencer->refreshItems();
	}

	/**
	 * Import discovery rules.
	 *
	 * @throws Exception
	 */
	protected function processDiscoveryRules() {
		$allDiscoveryRules = $this->getFormattedDiscoveryRules();
		if (empty($allDiscoveryRules)) {
			return;
		}

		// unset rules that are related to hosts we did not process
		foreach ($allDiscoveryRules as $host => $discoveryRules) {
			if (!$this->referencer->isProcessedHost($host)) {
				unset($allDiscoveryRules[$host]);
			}
		}

		$itemsToCreate = array();
		$itemsToUpdate = array();
		foreach ($allDiscoveryRules as $host => $discoveryRules) {
			$hostid = $this->referencer->resolveHostOrTemplate($host);
			foreach ($discoveryRules as $item) {
				$item['hostid'] = $hostid;

				if (isset($item['interface_ref'])) {
					$item['interfaceid'] = $this->referencer->interfacesCache[$hostid][$item['interface_ref']];
				}
				unset($item['item_prototypes']);
				unset($item['trigger_prototypes']);
				unset($item['graph_prototypes']);

				$itemId = $this->referencer->resolveItem($hostid, $item['key_']);
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
		$processedRules = array();
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

		// process prototypes
		$prototypesToUpdate = array();
		$prototypesToCreate = array();
		foreach ($allDiscoveryRules as $host => $discoveryRules) {
			$hostid = $this->referencer->resolveHostOrTemplate($host);
			foreach ($discoveryRules as $item) {
				// if rule was not processed we should not create/upadate any of it's prototypes
				if (!isset($processedRules[$hostid][$item['key_']])) {
					continue;
				}

				$item['hostid'] = $hostid;

				// prototypes
				foreach ($item['item_prototypes'] as $prototype) {
					$prototype['hostid'] = $hostid;

					$applicationsIds = array();
					foreach ($prototype['applications'] as $application) {
						$applicationsIds[] = $this->referencer->resolveApplication($hostid, $application['name']);
					}
					$prototype['applications'] = $applicationsIds;

					if (isset($prototype['interface_ref'])) {
						$prototype['interfaceid'] = $this->referencer->interfacesCache[$hostid][$prototype['interface_ref']];
					}

					if (!empty($prototype['valuemap'])) {
						$prototype['valuemapid'] = $this->referencer->resolveValueMap($prototype['valuemap']['name']);
					}

					$prototypeId = $this->referencer->resolveItem($hostid, $prototype['key_']);
					$prototype['rule'] = array('hostid' => $hostid, 'key' => $item['key_']);
					if ($prototypeId) {
						$prototype['itemid'] = $prototypeId;
						$prototypesToUpdate[] = $prototype;
					}
					else {
						$prototypesToCreate[] = $prototype;
					}
				}


				if (isset($item['interface_ref'])) {
					$item['interfaceid'] = $this->referencer->interfacesCache[$hostid][$item['interface_ref']];
				}
				unset($item['item_prototypes']);
				unset($item['trigger_prototypes']);
				unset($item['graph_prototypes']);

				$itemsId = $this->referencer->resolveItem($hostid, $item['key_']);
				if ($itemsId) {
					$item['itemid'] = $itemsId;
					$itemsToUpdate[] = $item;
				}
				else {
					$itemsToCreate[] = $item;
				}
			}
		}


		if ($prototypesToCreate) {
			foreach ($prototypesToCreate as &$prototype) {
				$prototype['ruleid'] = $this->referencer->resolveItem($prototype['rule']['hostid'], $prototype['rule']['key']);
			}
			unset($prototype);
			$newPrototypeIds = API::ItemPrototype()->create($prototypesToCreate);
			foreach ($newPrototypeIds['itemids'] as $inum => $itemid) {
				$item = $prototypesToCreate[$inum];
				$this->referencer->addItemRef($item['hostid'], $item['key_'], $itemid);
			}
		}
		if ($prototypesToUpdate) {
			foreach ($prototypesToCreate as &$prototype) {
				$prototype['ruleid'] = $this->referencer->resolveItem($prototype['rule']['hostid'], $prototype['rule']['key']);
			}
			unset($prototype);

			API::ItemPrototype()->update($prototypesToUpdate);
		}

		// refresh prototypes because templated ones can be inherited to host and used in triggers prototypes or graph prototypes
		$this->referencer->refreshItems();

		// first we need to create item prototypes and only then graph prototypes
		$triggersToCreate = array();
		$triggersToUpdate = array();
		$graphsToCreate = array();
		$graphsToUpdate = array();
		foreach ($allDiscoveryRules as $host => $discoveryRules) {
			$hostid = $this->referencer->resolveHostOrTemplate($host);
			foreach ($discoveryRules as $item) {
				// if rule was not processed we should not create/upadate any of it's prototypes
				if (!isset($processedRules[$hostid][$item['key_']])) {
					continue;
				}

				// trigger prototypes
				foreach ($item['trigger_prototypes'] as $trigger) {
					$triggerId = $this->referencer->resolveTrigger($trigger['description'], $trigger['expression']);

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
					$graphHostIds = array();
					if (!empty($graph['ymin_item_1'])) {
						$hostId = $this->referencer->resolveHostOrTemplate($graph['ymin_item_1']['host']);
						$graph['ymin_itemid'] = $this->referencer->resolveItem($hostId, $graph['ymin_item_1']['key']);
					}
					if (!empty($graph['ymax_item_1'])) {
						$hostId = $this->referencer->resolveHostOrTemplate($graph['ymax_item_1']['host']);
						$graph['ymax_itemid'] = $this->referencer->resolveItem($hostId, $graph['ymax_item_1']['key']);
					}


					foreach ($graph['gitems'] as &$gitem) {
						if (!$gitemHostId = $this->referencer->resolveHostOrTemplate($gitem['item']['host'])) {
							throw new Exception(_s('Cannot find host or template "%1$s" used in graph "%2$s".',
								$gitem['item']['host'], $graph['name']));
						}

						$gitem['itemid'] = $this->referencer->resolveItem($gitemHostId, $gitem['item']['key']);

						$graphHostIds[$gitemHostId] = $gitemHostId;
					}
					unset($gitem);


					// TODO: do this for all graphs at once
					$sql = 'SELECT g.graphid'.
							' FROM graphs g,graphs_items gi,items i'.
							' WHERE g.graphid=gi.graphid'.
								' AND gi.itemid=i.itemid'.
								' AND g.name='.zbx_dbstr($graph['name']).
								' AND '.dbConditionInt('i.hostid', $graphHostIds);
					$graphExists = DBfetch(DBselect($sql));

					if ($graphExists) {
						$dbGraph = API::GraphPrototype()->get(array(
							'graphids' => $graphExists['graphid'],
							'output' => API_OUTPUT_SHORTEN,
							'editable' => true
						));
						if (empty($dbGraph)) {
							throw new Exception(_s('No permission for graph "%1$s".', $graph['name']));
						}
						$graph['graphid'] = $graphExists['graphid'];
						$graphsToUpdate[] = $graph;
					}
					else {
						$graphsToCreate[] = $graph;
					}
				}
			}
		}

		if ($triggersToCreate) {
			API::TriggerPrototype()->create($triggersToCreate);
		}
		if ($triggersToUpdate) {
			API::TriggerPrototype()->update($triggersToUpdate);
		}

		if ($graphsToCreate) {
			API::GraphPrototype()->create($graphsToCreate);
		}
		if ($graphsToUpdate) {
			API::GraphPrototype()->update($graphsToUpdate);
		}
	}

	/**
	 * Import graphs.
	 *
	 * @throws Exception
	 */
	protected function processGraphs() {
		$allGraphs = $this->getFormattedGraphs();
		if (empty($allGraphs)) {
			return;
		}

		$graphsToCreate = array();
		$graphsToUpdate = array();
		foreach ($allGraphs as $graph) {
			$graphHostIds = array();

			if (!empty($graph['ymin_item_1'])) {
				$hostId = $this->referencer->resolveHostOrTemplate($graph['ymin_item_1']['host']);
				$graph['ymin_itemid'] = $this->referencer->resolveItem($hostId, $graph['ymin_item_1']['key']);
			}
			if (!empty($graph['ymax_item_1'])) {
				$hostId = $this->referencer->resolveHostOrTemplate($graph['ymax_item_1']['host']);
				$graph['ymax_itemid'] = $this->referencer->resolveItem($hostId, $graph['ymax_item_1']['key']);
			}

			foreach ($graph['gitems'] as &$gitem) {
				if (!$gitemHostId = $this->referencer->resolveHostOrTemplate($gitem['item']['host'])) {
					throw new Exception(_s('Cannot find host or template "%1$s" used in graph "%2$s".',
						$gitem['item']['host'], $graph['name']));
				}

				$gitem['itemid'] = $this->referencer->resolveItem($gitemHostId, $gitem['item']['key']);

				$graphHostIds[$gitemHostId] = $gitemHostId;
			}
			unset($gitem);

			// TODO: do this for all graphs at once
			$sql = 'SELECT g.graphid'.
					' FROM graphs g,graphs_items gi,items i'.
					' WHERE g.graphid=gi.graphid'.
						' AND gi.itemid=i.itemid'.
						' AND g.name='.zbx_dbstr($graph['name']).
						' AND '.dbConditionInt('i.hostid', $graphHostIds);
			$graphExists = DBfetch(DBselect($sql));

			if ($graphExists) {
				$dbGraph = API::Graph()->get(array(
					'graphids' => $graphExists['graphid'],
					'output' => API_OUTPUT_SHORTEN,
					'editable' => true
				));
				if (empty($dbGraph)) {
					throw new Exception(_s('No permission for graph "%1$s".', $graph['name']));
				}
				$graph['graphid'] = $graphExists['graphid'];
				$graphsToUpdate[] = $graph;
			}
			else {
				$graphsToCreate[] = $graph;
			}
		}

		if ($this->options['graphs']['createMissing'] && $graphsToCreate) {
			API::Graph()->create($graphsToCreate);
		}
		if ($this->options['graphs']['updateExisting'] && $graphsToUpdate) {
			API::Graph()->update($graphsToUpdate);
		}
	}

	/**
	 * Import triggers.
	 */
	protected function processTriggers() {
		$allTriggers = $this->getFormattedTriggers();
		if (empty($allTriggers)) {
			return;
		}

		$triggersToCreate = array();
		$triggersToUpdate = array();
		$triggersToCreateDependencies = array();
		foreach ($allTriggers as $trigger) {
			$triggerId = $this->referencer->resolveTrigger($trigger['description'], $trigger['expression']);

			if ($triggerId) {
				$deps = array();
				foreach ($trigger['dependencies'] as $dependency) {
					$depTriggerId = $this->referencer->resolveTrigger($dependency['name'], $dependency['expression']);
					if (!$depTriggerId) {
						throw new Exception(_s('Trigger "%1$s" depends on trigger "%2$s", which does not exist.', $trigger['description'], $dependency['name']));
					}
					$deps[] = array('triggerid' => $depTriggerId);
				}

				$trigger['dependencies'] = $deps;
				$trigger['triggerid'] = $triggerId;
				$triggersToUpdate[] = $trigger;
			}
			else {
				$triggersToCreateDependencies[] = $trigger['dependencies'];
				unset($trigger['dependencies']);
				$triggersToCreate[] = $trigger;
			}
		}

		$triggerDependencies = array();
		$newTriggers = array();
		if ($this->options['triggers']['createMissing'] && $triggersToCreate) {
			$newTriggerIds = API::Trigger()->create($triggersToCreate);
			foreach ($newTriggerIds['triggerids'] as $tnum => $triggerId) {
				$trigger = $triggersToCreate[$tnum];
				$this->referencer->addTriggerRef($trigger['description'], $trigger['expression'], $triggerId);

				$newTriggers[$triggerId] = $trigger;
			}
		}

		// if we have new triggers with dependencies and they were created, create their dependencies
		if ($triggersToCreateDependencies && isset($newTriggerIds)) {
			foreach ($newTriggerIds['triggerids'] as $tnum => $triggerId) {
				$deps = array();
				foreach ($triggersToCreateDependencies[$tnum] as $dependency) {
					$depTriggerId = $this->referencer->resolveTrigger($dependency['name'], $dependency['expression']);
					if (!$depTriggerId) {
						$trigger = $newTriggers[$triggerId];
						throw new Exception(_s('Trigger "%1$s" depends on trigger "%2$s", which does not exist.', $trigger['description'], $dependency['name']));
					}
					$deps[] = array('triggerid' => $depTriggerId);
				}

				if (!empty($deps)) {
					$triggerDependencies[] = array(
						'triggerid' => $triggerId,
						'dependencies' => $deps
					);
				}
			}
		}

		if ($this->options['triggers']['updateExisting'] && $triggersToUpdate) {
			API::Trigger()->update($triggersToUpdate);
		}

		if ($triggerDependencies) {
			API::Trigger()->update($triggerDependencies);
		}

		// refresh triggers because template triggers can be inherited to host and used in maps
		$this->referencer->refreshTriggers();
	}

	/**
	 * Import images.
	 *
	 * @throws Exception
	 */
	protected function processImages() {
		$allImages = $this->getFormattedImages();
		if (empty($allImages)) {
			return;
		}

		$imagesToUpdate = array();
		$allImages = zbx_toHash($allImages, 'name');

		$dbImages = DBselect('SELECT i.imageid,i.name FROM images i WHERE '.dbConditionString('i.name', array_keys($allImages)));
		while ($dbImage = DBfetch($dbImages)) {
			$dbImage['image'] = $allImages[$dbImage['name']]['image'];
			$imagesToUpdate[] = $dbImage;
			unset($allImages[$dbImage['name']]);
		}

		if ($this->options['images']['createMissing']) {
			API::Image()->create(array_values($allImages));
		}

		if ($this->options['images']['updateExisting']) {
			API::Image()->update($imagesToUpdate);
		}
	}

	/**
	 * Import maps.
	 */
	protected function processMaps() {
		if ($maps = $this->getFormattedMaps()) {
			$mapImporter = new CMapImporter($this->options, $this->referencer);
			$mapImporter->import($maps);
		}
	}

	/**
	 * Import screens.
	 */
	protected function processScreens() {
		if ($screens = $this->getFormattedScreens()) {
			$screenImporter = new CScreenImporter($this->options, $this->referencer);
			$screenImporter->import($screens);
		}
	}

	/**
	 * Import template screens.
	 */
	protected function processTemplateScreens() {
		if ($screens = $this->getFormattedTemplateScreens()) {
			$screenImporter = new CTemplateScreenImporter($this->options, $this->referencer);
			$screenImporter->import($screens);
		}
	}

	/**
	 * Method for creating an import formatter for the specified import version.
	 *
	 * @param string $version
	 *
	 * @return CImportFormatter
	 *
	 * @throws InvalidArgumentException
	 */
	protected function getFormatter($version) {
		switch ($version) {
			case '2.0':
				return new C20ImportFormatter;
			default:
				throw new InvalidArgumentException('Unknown import version.');
		}
	}

	/**
	 * Get configuration import version.
	 *
	 * @return string
	 */
	protected function getImportVersion() {
		if (isset($this->data['zabbix_export']['version'])) {
			return $this->data['zabbix_export']['version'];
		}
		return '1.8';
	}

	/**
	 * Get formatted groups, if either "createMissing" groups option is true.
	 *
	 * @return array
	 */
	protected function getFormattedGroups() {
		if (!isset($this->formattedData['groups'])) {
			$this->formattedData['groups'] = array();
			if ($this->options['groups']['createMissing']) {
				$this->formattedData['groups'] = $this->formatter->getGroups();
			}
		}

		return $this->formattedData['groups'];
	}

	/**
	 * Get formatted templates, if either "createMissing" or "updateExisting" templates option is true.
	 *
	 * @return array
	 */
	protected function getFormattedTemplates() {
		if (!isset($this->formattedData['templates'])) {
			$this->formattedData['templates'] = array();
			if ($this->options['templates']['updateExisting'] || $this->options['templates']['createMissing']) {
				$this->formattedData['templates'] = $this->formatter->getTemplates();
			}
		}

		return $this->formattedData['templates'];
	}

	/**
	 * Get formatted hosts, if either "createMissing" or "updateExisting" hosts option is true.
	 *
	 * @return array
	 */
	protected function getFormattedHosts() {
		if (!isset($this->formattedData['hosts'])) {
			$this->formattedData['hosts'] = array();
			if ($this->options['hosts']['updateExisting'] || $this->options['hosts']['createMissing']) {
				$this->formattedData['hosts'] = $this->formatter->getHosts();
			}
		}

		return $this->formattedData['hosts'];
	}

	/**
	 * Get formatted applications, if either "createMissing" or "updateExisting" applications option is true.
	 *
	 * @return array
	 */
	protected function getFormattedApplications() {
		if (!isset($this->formattedData['applications'])) {
			$this->formattedData['applications'] = array();
			if ($this->options['templates']['updateExisting']
					|| $this->options['templates']['createMissing']
					|| $this->options['hosts']['updateExisting']
					|| $this->options['hosts']['createMissing']) {
				$this->formattedData['applications'] = $this->formatter->getApplications();
			}
		}

		return $this->formattedData['applications'];
	}

	/**
	 * Get formatted items, if either "createMissing" or "updateExisting" items option is true.
	 *
	 * @return array
	 */
	protected function getFormattedItems() {
		if (!isset($this->formattedData['items'])) {
			$this->formattedData['items'] = array();
			if ($this->options['items']['updateExisting'] || $this->options['items']['createMissing']) {
				$this->formattedData['items'] = $this->formatter->getItems();
			}
		}

		return $this->formattedData['items'];
	}

	/**
	 * Get formatted discovery rules, if either "createMissing" or "updateExisting" discovery rules option is true.
	 *
	 * @return array
	 */
	protected function getFormattedDiscoveryRules() {
		if (!isset($this->formattedData['discoveryRules'])) {
			$this->formattedData['discoveryRules'] = array();
			if ($this->options['discoveryRules']['updateExisting'] || $this->options['discoveryRules']['createMissing']) {
				$this->formattedData['discoveryRules'] = $this->formatter->getDiscoveryRules();
			}
		}

		return $this->formattedData['discoveryRules'];
	}

	/**
	 * Get formatted triggers, if either "createMissing" or "updateExisting" triggers option is true.
	 *
	 * @return array
	 */
	protected function getFormattedTriggers() {
		if (!isset($this->formattedData['triggers'])) {
			$this->formattedData['triggers'] = array();
			if ($this->options['triggers']['updateExisting'] || $this->options['triggers']['createMissing']) {
				$this->formattedData['triggers'] = $this->formatter->getTriggers();
			}
		}

		return $this->formattedData['triggers'];
	}

	/**
	 * Get formatted graphs, if either "createMissing" or "updateExisting" graphs option is true.
	 *
	 * @return array
	 */
	protected function getFormattedGraphs() {
		if (!isset($this->formattedData['graphs'])) {
			$this->formattedData['graphs'] = array();
			if ($this->options['graphs']['updateExisting'] || $this->options['graphs']['createMissing']) {
				$this->formattedData['graphs'] = $this->formatter->getGraphs();
			}
		}

		return $this->formattedData['graphs'];
	}

	/**
	 * Get formatted images, if user is super admin and either "createMissing" or "updateExisting" images option is true.
	 *
	 * @return array
	 */
	protected function getFormattedImages() {
		if (!isset($this->formattedData['images'])) {
			$this->formattedData['images'] = array();
			if (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN
					&& $this->options['images']['updateExisting'] || $this->options['images']['createMissing']) {
				$this->formattedData['images'] = $this->formatter->getImages();
			}
		}

		return $this->formattedData['images'];
	}

	/**
	 * Get formatted maps, if either "createMissing" or "updateExisting" maps option is true.
	 *
	 * @return array
	 */
	protected function getFormattedMaps() {
		if (!isset($this->formattedData['maps'])) {
			$this->formattedData['maps'] = array();
			if ($this->options['maps']['updateExisting'] || $this->options['maps']['createMissing']) {
				$this->formattedData['maps'] = $this->formatter->getMaps();
			}
		}

		return $this->formattedData['maps'];
	}

	/**
	 * Get formatted screens, if either "createMissing" or "updateExisting" screens option is true.
	 *
	 * @return array
	 */
	protected function getFormattedScreens() {
		if (!isset($this->formattedData['screens'])) {
			$this->formattedData['screens'] = array();
			if ($this->options['screens']['updateExisting'] || $this->options['screens']['createMissing']) {
				$this->formattedData['screens'] = $this->formatter->getScreens();
			}
		}

		return $this->formattedData['screens'];
	}

	/**
	 * Get formatted template screens, if either "createMissing" or "updateExisting" template screens option is true.
	 *
	 * @return array
	 */
	protected function getFormattedTemplateScreens() {
		if (!isset($this->formattedData['templateScreens'])) {
			$this->formattedData['templateScreens'] = array();
			if ($this->options['templateScreens']['updateExisting'] || $this->options['templateScreens']['createMissing']) {
				$this->formattedData['templateScreens'] = $this->formatter->getTemplateScreens();
			}
		}

		return $this->formattedData['templateScreens'];
	}
}
