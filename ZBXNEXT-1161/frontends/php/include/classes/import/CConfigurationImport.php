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
	 * @var array with references to interfaceid (hostid -> reference_name -> interfaceid)
	 */
	protected $interfacesCache = array();

	/**
	 * Array of hosts/templates that were created or updated,
	 * so it's related items and discovery rules should be processed too.
	 *
	 * @var array
	 */
	protected $processedHosts = array();


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
	 * @todo   for 1.8 version import old class CXmlImport18 is used
	 *
	 * @throws Exception
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
				if ($this->options['hosts']['updateExisting'] || $this->options['hosts']['createMissing']) {
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
		$iconMaps = array();

		foreach ($this->getFormattedGroups() as $group) {
			$groupsRefs[$group['name']] = $group['name'];
		}

		foreach ($this->getFormattedTemplates() as $template) {
			$templatesRefs[$template['host']] = $template['host'];

			foreach ($template['groups'] as $group) {
				$groupsRefs[$group['name']] = $group['name'];
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
			if (!empty($host['templates'])) {
				foreach ($host['templates'] as $linkedTemplate) {
					$templatesRefs[$linkedTemplate['name']] = $linkedTemplate['name'];
				}
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
			if (!empty($map['iconmap'])) {
				$iconMaps[$map['iconmap']['name']] = $map['iconmap']['name'];
			}
		}

		$this->referencer->addGroups($groupsRefs);
		$this->referencer->addTemplates($templatesRefs);
		$this->referencer->addHosts($hostsRefs);
		$this->referencer->addApplications($applicationsRefs);
		$this->referencer->addItems($itemsRefs);
		$this->referencer->addValueMaps($valueMapsRefs);
		$this->referencer->addTriggers($triggersRefs);
		$this->referencer->addIconMaps($iconMaps);
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
		$templates = $this->getFormattedTemplates();
		if (empty($templates)) {
			return;
		}
		$templates = zbx_toHash($templates, 'host');

		foreach ($templates as &$template) {
			// screens are not needed in this method
			unset($template['screens']);

			// if we don't need to update linkage, unset templates
			if (!$this->options['templateLinkage']['createMissing']) {
				unset($template['templates']);
			}
		}
		unset($template);


		$orderedList = array();
		$templatesInSource = array_keys($templates);
		$parentTemplateRefs = array();
		foreach ($templates as $template) {
			$parentTemplateRefs[$template['host']] = array();

			if (!empty($template['templates'])) {
				foreach ($template['templates'] as $ref) {
					// if the template already exists in the system, we skip it
					if ($this->referencer->resolveTemplate($ref['name'])) {
						continue;
					}
					else {
						// if the template is not in the system and not in the imported data, throw an error
						if (!in_array($ref['name'], $templatesInSource)) {
							throw new Exception(_s('Template "%1$s" does not exist.', $ref['name']));
						}
						$parentTemplateRefs[$template['host']][$ref['name']] = $ref['name'];
					}
				}
			}
		}

		// we loop through all templates looking for any without parent templates
		// when one is found it's pushed to ordered list and removed from the list of parent templates of all
		// other templates
		while (!empty($parentTemplateRefs)) {
			$templateWithoutParents = false;
			foreach ($parentTemplateRefs as $template => $refs) {
				if (empty($refs)) {
					$templateWithoutParents = $template;
					$orderedList[] = $template;
					unset($parentTemplateRefs[$template]);
					break;
				}
			}
			if (!$templateWithoutParents) {
				throw new Exception('Circular template reference.');
			}

			foreach ($parentTemplateRefs as $template => $refs) {
				unset($parentTemplateRefs[$template][$templateWithoutParents]);
			}
		}

		foreach ($orderedList as $name) {
			$template = $templates[$name];
			foreach ($template['groups'] as $gnum => $group) {
				if (!$this->referencer->resolveGroup($group['name'])) {
					throw new Exception(_s('Group "%1$s" does not exist.', $group['name']));
				}
				$template['groups'][$gnum] = array('groupid' => $this->referencer->resolveGroup($group['name']));
			}
			if (isset($template['templates'])) {
				foreach ($template['templates'] as $tnum => $parentTemplate) {
					$template['templates'][$tnum] = array(
						'templateid' => $this->referencer->resolveTemplate($parentTemplate['name'])
					);
				}
			}

			if ($this->referencer->resolveTemplate($template['host'])) {
				if ($this->options['templates']['updateExisting']) {
					$template['templateid'] = $this->referencer->resolveTemplate($template['host']);
					API::Template()->update($template);
					$this->processedHosts[$template['host']] = $template['host'];
				}
			}
			elseif ($this->options['templates']['createMissing']) {
				$newHostIds = API::Template()->create($template);
				$templateid = reset($newHostIds['templateids']);
				$this->referencer->addTemplateRef($template['host'], $templateid);
				$this->processedHosts[$template['host']] = $template['host'];
			}
		}
	}

	/**
	 * Import hosts.
	 *
	 * @throws Exception
	 */
	protected function processHosts() {
		$hosts = $this->getFormattedHosts();
		if (empty($hosts)) {
			return;
		}

		// if we don't need to update linkage, unset templates
		if (!$this->options['templateLinkage']['createMissing']) {
			foreach ($hosts as &$host) {
				unset($host['templates']);
			}
			unset($host);
		}

		// a list of hostids which were created or updated to create an interface cache for those hosts
		$processedHosts = array();
		// create interface references
		$hostInterfacesRefsByName = array();
		foreach ($hosts as $host) {
			$hostInterfacesRefsByName[$host['host']] = array();
			foreach ($host['interfaces'] as $interface) {
				$hostInterfacesRefsByName[$host['host']][$interface['interface_ref']] = $interface;
			}
		}

		$hostsToCreate = $hostsToUpdate = array();
		foreach ($hosts as $host) {
			foreach ($host['groups'] as $gnum => $group) {
				if (!$this->referencer->resolveGroup($group['name'])) {
					throw new Exception(_s('Group "%1$s" does not exist.', $group['name']));
				}
				$host['groups'][$gnum] = array('groupid' => $this->referencer->resolveGroup($group['name']));
			}
			if (isset($host['templates'])) {
				foreach ($host['templates'] as $tnum => $template) {
					if (!$this->referencer->resolveTemplate($template['name'])) {
						throw new Exception(_s('Template "%1$s" does not exist.', $template['name']));
					}
					$host['templates'][$tnum] = array(
						'templateid' => $this->referencer->resolveHostOrTemplate($template['name'])
					);
				}
			}

			if ($this->referencer->resolveHost($host['host'])) {
				$host['hostid'] = $this->referencer->resolveHost($host['host']);
				$processedHosts[$host['host']] = $host['hostid'];
				$hostsToUpdate[] = $host;
			}
			else {
				$hostsToCreate[] = $host;
			}
		}

		// for existing hosts we need to set an interfaceid for existing interfaces or they will be added
		$dbInterfaces = API::HostInterface()->get(array(
			'hostids' => zbx_objectValues($hostsToUpdate, 'hostid'),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		foreach ($dbInterfaces as $dbInterface) {
			foreach ($hostsToUpdate as $hnum => $host) {
				if (bccomp($host['hostid'], $dbInterface['hostid']) == 0) {
					foreach ($host['interfaces'] as $inum => $interface) {
						if ($dbInterface['ip'] == $interface['ip']
								&& $dbInterface['dns'] == $interface['dns']
								&& $dbInterface['useip'] == $interface['useip']
								&& $dbInterface['port'] == $interface['port']
								&& $dbInterface['type'] == $interface['type']
								&& $dbInterface['main'] == $interface['main']) {
							unset($hostsToUpdate[$hnum]['interfaces'][$inum]);
						}
					}
				}
				if (empty($hostsToUpdate[$hnum]['interfaces'])) {
					unset($hostsToUpdate[$hnum]['interfaces']);
				}
			}
		}

		// create/update hosts and create hash host->hostid
		if ($this->options['hosts']['createMissing'] && $hostsToCreate) {
			$newHostIds = API::Host()->create($hostsToCreate);
			foreach ($newHostIds['hostids'] as $hnum => $hostid) {
				$processedHosts[$hostsToCreate[$hnum]['host']] = $hostid;
				$this->referencer->addHostRef($hostsToCreate[$hnum]['host'], $hostid);
			}
			foreach ($hostsToCreate as $host) {
				$this->processedHosts[$host['host']] = $host['host'];
			}
		}
		if ($this->options['hosts']['updateExisting'] && $hostsToUpdate) {
			API::Host()->update($hostsToUpdate);
			foreach ($hostsToUpdate as $host) {
				$this->processedHosts[$host['host']] = $host['host'];
			}
		}

		// create interface hash interface_ref->interfaceid
		$dbInterfaces = API::HostInterface()->get(array(
			'hostids' => $processedHosts,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		foreach ($dbInterfaces as $dbInterface) {
			foreach ($hostInterfacesRefsByName as $hostName => $interfaceRefs) {
				$hostId = $this->referencer->resolveHost($hostName);
				if (!isset($this->interfacesCache[$processedHosts[$hostName]])) {
					$this->interfacesCache[$hostId] = array();
				}

				foreach ($interfaceRefs as $refName => $interface) {
					if ($hostId == $dbInterface['hostid']
							&& $dbInterface['ip'] == $interface['ip']
							&& $dbInterface['dns'] == $interface['dns']
							&& $dbInterface['useip'] == $interface['useip']
							&& $dbInterface['port'] == $interface['port']
							&& $dbInterface['type'] == $interface['type']
							&& $dbInterface['main'] == $interface['main']) {
						$this->interfacesCache[$hostId][$refName] = $dbInterface['interfaceid'];
					}
				}
			}
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
			if (!isset($this->processedHosts[$host])) {
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
		$newApplicationsIds = API::Application()->create($applicationsToCreate);
		foreach ($newApplicationsIds['applicationids'] as $anum => $applicationId) {
			$application = $applicationsToCreate[$anum];
			$this->referencer->addApplicationRef($application['hostid'], $application['name'], $applicationId);
		}
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
			if (!isset($this->processedHosts[$host])) {
				continue;
			}

			$hostid = $this->referencer->resolveHostOrTemplate($host);
			foreach ($items as $item) {
				$item['hostid'] = $hostid;

				if (!empty($item['applications'])) {
					$applicationsIds = array();
					foreach ($item['applications'] as $application) {
						$applicationsIds[] = $this->referencer->resolveApplication($hostid, $application['name']);
					}
					$item['applications'] = $applicationsIds;
				}

				if (isset($item['interface_ref'])) {
					$item['interfaceid'] = $this->interfacesCache[$hostid][$item['interface_ref']];
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
			if (!isset($this->processedHosts[$host])) {
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
					$item['interfaceid'] = $this->interfacesCache[$hostid][$item['interface_ref']];
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
						$prototype['interfaceid'] = $this->interfacesCache[$hostid][$prototype['interface_ref']];
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
					$item['interfaceid'] = $this->interfacesCache[$hostid][$item['interface_ref']];
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
						$gitemhostId = $this->referencer->resolveHostOrTemplate($gitem['item']['host']);

						$gitem['itemid'] = $this->referencer->resolveItem($gitemhostId, $gitem['item']['key']);

						$graphHostIds[$gitemhostId] = $gitemhostId;
					}
					unset($gitem);


					// TODO: do this for all graphs at once
					$sql = 'SELECT g.graphid
					FROM graphs g, graphs_items gi, items i
					WHERE g.graphid=gi.graphid
						AND gi.itemid=i.itemid
						AND g.name='.zbx_dbstr($graph['name']).'
						AND '.DBcondition('i.hostid', $graphHostIds);
					$graphExists = DBfetch(DBselect($sql));

					if ($graphExists) {
						$dbGraph = API::GraphPrototype()->get(array(
							'graphids' => $graphExists['graphid'],
							'output' => API_OUTPUT_SHORTEN,
							'editable' => true
						));
						if (empty($dbGraph)) {
							throw new Exception(_s('No permission for Graph "%1$s".', $graph['name']));
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
				$gitemhostId = $this->referencer->resolveHostOrTemplate($gitem['item']['host']);

				$gitem['itemid'] = $this->referencer->resolveItem($gitemhostId, $gitem['item']['key']);

				$graphHostIds[$gitemhostId] = $gitemhostId;
			}
			unset($gitem);

			// TODO: do this for all graphs at once
			$sql = 'SELECT g.graphid
			FROM graphs g, graphs_items gi, items i
			WHERE g.graphid=gi.graphid
				AND gi.itemid=i.itemid
				AND g.name='.zbx_dbstr($graph['name']).'
				AND '.DBcondition('i.hostid', $graphHostIds);
			$graphExists = DBfetch(DBselect($sql));

			if ($graphExists) {
				$dbGraph = API::Graph()->get(array(
					'graphids' => $graphExists['graphid'],
					'output' => API_OUTPUT_SHORTEN,
					'editable' => true
				));
				if (empty($dbGraph)) {
					throw new Exception(_s('No permission for Graph "%1$s".', $graph['name']));
				}
				$graph['graphid'] = $graphExists['graphid'];
				$graphsToUpdate[] = $graph;
			}
			else {
				$graphsToCreate[] = $graph;
			}
		}

		// create/update items and create hash hostid->key_->itemid
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
					$deps[] = array('triggerid' => $this->referencer->resolveTrigger($dependency['name'], $dependency['expression']));
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
		if ($this->options['triggers']['createMissing'] && $triggersToCreate) {
			$newTriggerIds = API::Trigger()->create($triggersToCreate);
			foreach ($newTriggerIds['triggerids'] as $tnum => $triggerId) {
				$trigger = $triggersToCreate[$tnum];
				$this->referencer->addTriggerRef($trigger['description'], $trigger['expression'], $triggerId);
			}
		}

		// if we have new triggers with dependencies and they were created, create their dependencies
		if ($triggersToCreateDependencies && isset($newTriggerIds)) {
			foreach ($newTriggerIds['triggerids'] as $tnum => $triggerId) {
				$deps = array();
				foreach ($triggersToCreateDependencies[$tnum] as $dependency) {
					$deps[] = array('triggerid' => $this->referencer->resolveTrigger($dependency['name'], $dependency['expression']));
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

		$dbImages = DBselect('SELECT i.imageid, i.name FROM images i WHERE '.DBcondition('i.name', array_keys($allImages)));
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
	 *
	 * @throws Exception
	 */
	protected function processMaps() {
		$allMaps = $this->getFormattedMaps();
		if (empty($allMaps)) {
			return;
		}

		$mapsToCreate = array();
		$mapsToUpdate = array();
		$existingMaps = array();
		$allMaps = zbx_toHash($allMaps, 'name');
		$dbMaps = DBselect('SELECT s.sysmapid, s.name FROM sysmaps s WHERE '.DBcondition('s.name', array_keys($allMaps)));
		while ($dbMap = DBfetch($dbMaps)) {
			$existingMaps[$dbMap['sysmapid']] = $dbMap['name'];
			$allMaps[$dbMap['name']]['sysmapid'] = $dbMap['sysmapid'];
		}

		// if we are going to update maps, check for permissions
		if ($existingMaps && $this->options['maps']['updateExisting']) {
			$allowedMaps = API::Map()->get(array(
				'sysmapids' => array_keys($existingMaps),
				'output' => API_OUTPUT_SHORTEN,
				'editable' => true,
				'preservekeys' => true
			));
			foreach ($existingMaps as $existingMapId => $existingMapName) {
				if (!isset($allowedMaps[$existingMapId])) {
					throw new Exception(_s('No permissions for map "%1$s".', $existingMapName));
				}
			}
		}

		foreach ($allMaps as $map) {
			// resolve icon map
			if (isset($map['iconmap'])) {
				if (empty($map['iconmap'])) {
					$map['iconmapid'] = 0;
				}
				else {
					$map['iconmapid'] = $this->referencer->resolveIconMap($map['iconmap']['name']);
					if (!$map['iconmapid']) {
						throw new Exception(_s('Cannot find icon map "%1$s" for map "%2$s".', $map['iconmap']['name'], $map['name']));
					}
				}
			}


			if (isset($map['background'])) {
				$image = getImageByIdent($map['background']);

				if (!$image) {
					throw new Exception(_s('Cannot find background image for map "%1$s.', $map['name']));
				}
				$map['backgroundid'] = $image['imageid'];
			}

			$map['selements'] = isset($map['selements']) ? array_values($map['selements']) : array();
			$map['links'] = isset($map['links']) ? array_values($map['links']) : array();

			foreach ($map['selements'] as &$selement) {
				$nodeCaption = isset($selement['elementid']['node']) ? $selement['elementid']['node'].':' : '';

				if (!isset($selement['elementid'])) {
					$selement['elementid'] = 0;
				}

				if (empty($selement['urls'])) {
					unset($selement['urls']);
				}
				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_MAP:
						$db_sysmaps = API::Map()->getObjects($selement['element']);
						if (empty($db_sysmaps)) {
							throw new Exception(_s('Cannot find map "%1$s" used in map %2$s".',
									$nodeCaption.$selement['element']['name'], $map['name']));
						}

						$tmp = reset($db_sysmaps);
						$selement['elementid'] = $tmp['sysmapid'];
						break;

					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$db_hostgroups = API::HostGroup()->getObjects($selement['element']);
						if (empty($db_hostgroups)) {
							throw new Exception(_s('Cannot find group "%1$s" used in map "$2%s".',
									$nodeCaption.$selement['element']['name'], $map['name']));
						}

						$tmp = reset($db_hostgroups);
						$selement['elementid'] = $tmp['groupid'];
						break;

					case SYSMAP_ELEMENT_TYPE_HOST:
						$db_hosts = API::Host()->getObjects($selement['element']);
						if (empty($db_hosts)) {
							throw new Exception(_s('Cannot find host "%1$s" used in map "$2%s".',
									$nodeCaption.$selement['element']['host'], $map['name']));
						}

						$tmp = reset($db_hosts);
						$selement['elementid'] = $tmp['hostid'];
						break;

					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						$dbTriggers = API::Trigger()->getObjects($selement['element']);
						if (empty($dbTriggers)) {
							throw new Exception(_s('Cannot find trigger "%1$s" used in map "$2%s".',
									$nodeCaption.$selement['element']['host'], $map['name']));
						}

						$tmp = reset($dbTriggers);
						$selement['elementid'] = $tmp['triggerid'];
						break;
				}

				$icons = array(
					'icon_off' => 'iconid_off',
					'icon_on' => 'iconid_on',
					'icon_disabled' => 'iconid_disabled',
					'icon_maintenance' => 'iconid_maintenance',
				);
				foreach ($icons as $element => $field) {
					if (!empty($selement[$element])) {
						$image = getImageByIdent($selement[$element]);
						if (!$image) {
							throw new Exception(_s('Cannot find icon "%1$s" for map "%2$s".',
								$selement[$element]['name'], $map['name']));
						}
						$selement[$field] = $image['imageid'];
					}
				}
			}
			unset($selement);

			foreach ($map['links'] as &$link) {
				if (empty($link['linktriggers'])) {
					unset($link['linktriggers']);
					continue;
				}

				foreach ($link['linktriggers'] as &$linktrigger) {
					$dbTriggers = API::Trigger()->getObjects($linktrigger['trigger']);
					if (empty($dbTriggers)) {
						throw new Exception(_s('Cannot find trigger "%1$s" for map "%2$s".',
							$linktrigger['trigger']['description'], $map['name']));
					}

					$tmp = reset($dbTriggers);
					$linktrigger['triggerid'] = $tmp['triggerid'];
				}
				unset($linktrigger);
			}
			unset($link);

			if (isset($map['sysmapid'])) {
				$mapsToUpdate[] = $map;
			}
			else {
				$mapsToCreate[] = $map;
			}
		}

		if ($this->options['maps']['createMissing'] && $mapsToCreate) {
			API::Map()->create($mapsToCreate);
		}
		if ($this->options['maps']['updateExisting'] && $mapsToUpdate) {
			API::Map()->update($mapsToUpdate);
		}
	}

	/**
	 * Import screens.
	 */
	protected function processScreens() {
		$allScreens = $this->getFormattedScreens();
		if (empty($allScreens)) {
			return;
		}

		$allScreens = zbx_toHash($allScreens, 'name');

		$existingScreens = array();
		$dbScreens = DBselect('SELECT s.screenid, s.name FROM screens s WHERE'.
			' s.templateid IS NULL '.
			' AND '.DBcondition('s.name', array_keys($allScreens)));
		while ($dbScreen = DBfetch($dbScreens)) {
			$existingScreens[$dbScreen['screenid']] = $dbScreen['name'];
			$allScreens[$dbScreen['name']]['screenid'] = $dbScreen['screenid'];
		}

		// if we are going to update screens, check for permissions
		if ($existingScreens && $this->options['screens']['updateExisting']) {
			$allowedScreens = API::Screen()->get(array(
				'screenids' => array_keys($existingScreens),
				'output' => API_OUTPUT_SHORTEN,
				'editable' => true,
				'preservekeys' => true
			));
			foreach ($existingScreens as $existingScreenId => $existingScreenName) {
				if (!isset($allowedScreens[$existingScreenId])) {
					throw new Exception(_s('No permissions for screen "%1$s".', $existingScreenName));
				}
			}
		}

		$allScreens = $this->prepareScreenImport($allScreens);
		$screensToCreate = array();
		$screensToUpdate = array();

		foreach ($allScreens as $screen) {
			if (isset($screen['screenid'])) {
				$screensToUpdate[] = $screen;
			}
			else {
				$screensToCreate[] = $screen;
			}
		}

		if ($this->options['screens']['createMissing'] && $screensToCreate) {
			API::Screen()->create($screensToCreate);
		}
		if ($this->options['screens']['updateExisting'] && $screensToUpdate) {
			API::Screen()->update($screensToUpdate);
		}
	}

	/**
	 * Import template screens.
	 */
	protected function processTemplateScreens() {
		$allScreens = $this->getFormattedTemplateScreens();
		if (empty($allScreens)) {
			return;
		}

		$screensToCreate = array();
		$screensToUpdate = array();
		foreach ($allScreens as $template => $screens) {
			$existingScreens = array();
			$dbScreens = DBselect('SELECT s.screenid, s.name FROM screens s WHERE '.
					' s.templateid='.zbx_dbstr($this->referencer->resolveTemplate($template)).
					' AND '.DBcondition('s.name', array_keys($screens)));
			while ($dbScreen = DBfetch($dbScreens)) {
				$existingScreens[$dbScreen['screenid']] = $dbScreen['name'];
				$screens[$dbScreen['name']]['screenid'] = $dbScreen['screenid'];
			}

			// if we are going to update screens, check for permissions
			if ($existingScreens && $this->options['screens']['updateExisting']) {
				$allowedTplScreens = API::TemplateScreen()->get(array(
					'screenids' => array_keys($existingScreens),
					'output' => API_OUTPUT_SHORTEN,
					'editable' => true,
					'preservekeys' => true
				));
				foreach ($existingScreens as $existingScreenId => $existingScreenName) {
					if (!isset($allowedTplScreens[$existingScreenId])) {
						throw new Exception(_s('No permissions for screen "%1$s".', $existingScreenName));
					}
				}
			}

			$screens = $this->prepareScreenImport($screens);
			foreach ($screens as $screen) {
				$screen['templateid'] = $this->referencer->resolveTemplate($template);
				if (isset($screen['screenid'])) {
					$screensToUpdate[] = $screen;
				}
				else {
					$screensToCreate[] = $screen;
				}
			}
		}


		if ($this->options['templateScreens']['createMissing'] && $screensToCreate) {
			API::TemplateScreen()->create($screensToCreate);
		}
		if ($this->options['templateScreens']['updateExisting'] && $screensToUpdate) {
			API::TemplateScreen()->update($screensToUpdate);
		}
	}

	/**
	 * Prepare screen data for import.
	 * Each screen element has reference to resource it represents, reference structure may differ depending on type.
	 * Referenced database objects ids are stored to 'resourceid' field of screen items.
	 *
	 * @todo: it's copy of old frontend function, should be refactored
	 * @throws Exception if referenced object is not found in database
	 *
	 * @param array $allScreens
	 *
	 * @return array
	 */
	protected function prepareScreenImport(array $allScreens) {
		foreach ($allScreens as &$screen) {
			if (!isset($screen['screenitems'])) {
				continue;
			}

			foreach ($screen['screenitems'] as &$screenitem) {
				$nodeCaption = isset($screenitem['resource']['node']) ? $screenitem['resource']['node'] . ':' : '';

				if (empty($screenitem['resource'])) {
					$screenitem['resourceid'] = 0;
				}

				if (is_array($screenitem['resource'])) {
					switch ($screenitem['resourcetype']) {
						case SCREEN_RESOURCE_HOSTS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
						case SCREEN_RESOURCE_DATA_OVERVIEW:
						case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
							$db_hostgroups = API::HostGroup()->getObjects($screenitem['resource']);
							if (empty($db_hostgroups)) {
								throw new Exception(_s('Cannot find group "%1$s" used in screen "%2$s".',
										$nodeCaption.$screenitem['resource']['name'], $screen['name']));
							}

							$tmp = reset($db_hostgroups);
							$screenitem['resourceid'] = $tmp['groupid'];
							break;

						case SCREEN_RESOURCE_HOST_TRIGGERS:
							$db_hosts = API::Host()->getObjects($screenitem['resource']);
							if (empty($db_hosts)) {
								throw new Exception(_s('Cannot find host "%1$s" used in screen "%2$s".',
										$nodeCaption.$screenitem['resource']['host'], $screen['name']));
							}

							$tmp = reset($db_hosts);
							$screenitem['resourceid'] = $tmp['hostid'];
							break;

						case SCREEN_RESOURCE_GRAPH:
							$db_graphs = API::Graph()->getObjects($screenitem['resource']);
							if (empty($db_graphs)) {
								throw new Exception(_s('Cannot find graph "%1$s" used in screen "%2$s".',
										$nodeCaption.$screenitem['resource']['name'], $screen['name']));
							}

							$tmp = reset($db_graphs);
							$screenitem['resourceid'] = $tmp['graphid'];
							break;

						case SCREEN_RESOURCE_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_PLAIN_TEXT:
							$db_items = API::Item()->getObjects(array(
								'host' => $screenitem['resource']['host'],
								'key_' => $screenitem['resource']['key']
							));

							if (empty($db_items)) {
								throw new Exception(_s('Cannot find item "%1$s" used in screen "%2$s".',
										$nodeCaption.$screenitem['resource']['host'].':'.$screenitem['resource']['key_'], $screen['name']));
							}

							$tmp = reset($db_items);
							$screenitem['resourceid'] = $tmp['itemid'];
							break;

						case SCREEN_RESOURCE_MAP:
							$db_sysmaps = API::Map()->getObjects($screenitem['resource']);
							if (empty($db_sysmaps)) {
								throw new Exception(_s('Cannot find map "%1$s" used in screen "%2$s".',
										$nodeCaption.$screenitem['resource']['name'], $screen['name']));
							}

							$tmp = reset($db_sysmaps);
							$screenitem['resourceid'] = $tmp['sysmapid'];
							break;

						case SCREEN_RESOURCE_SCREEN:
							$db_screens = API::Screen()->get(array(
								'output' => API_OUTPUT_SHORTEN,
								'preservekeys' => true,
								'editable' => true,
								'filter' => array('name' => $screenitem['resource']['name'])
							));
							if (empty($db_screens)) {
								throw new Exception(_s('Cannot find screen "%1$s" used in screen "%2$s".',
										$nodeCaption.$screenitem['resource']['name'], $screen['name']));
							}

							$tmp = reset($db_screens);
							$screenitem['resourceid'] = $tmp['screenid'];
							break;

						default:
							$screenitem['resourceid'] = 0;
							break;
					}
				}
			}
			unset($screenitem);
		}
		unset($screen);

		return $allScreens;
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
