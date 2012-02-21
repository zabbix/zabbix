<?php

class CConfigurationImport {

	/**
	 * @var CImportReader
	 */
	protected $reader;

	/**
	 * @var array
	 */
	protected $options;

	protected $hostsCache = array();
	protected $applicationsCache = array();
	protected $interfacesCache = array();
	protected $itemsCache = array();
	protected $discoveryRulesCache = array();

	public function __construct($file, $options = array()) {
		$this->options = array(
			'groups' => array('missed' => true),
			'hosts' => array('exist' => true, 'missed' => true),
			'templates' => array('exist' => true, 'missed' => true),
			'applications' => array('exist' => true, 'missed' => true),
			'template_linkages' => array('exist' => true, 'missed' => true),
			'items' => array('exist' => true, 'missed' => true),
			'discoveryrules' => array('exist' => true, 'missed' => true),
			'triggers' => array('exist' => true, 'missed' => true),
			'graphs' => array('exist' => true, 'missed' => true),
			'screens' => array('exist' => true, 'missed' => true),
			'maps' => array('exist' => true, 'missed' => true),
			'images' => array('exist' => false, 'missed' => false),
		);
		$this->options = array_merge($this->options, $options);

		$ext = pathinfo($file['name'], PATHINFO_EXTENSION);

		$this->reader = $this->getReader($ext);

		$this->data = $this->reader->read($file['tmp_name']);

		$this->formatter = $this->getFormatter($this->getImportVersion());

		$this->referencer = new CImportReferencer();
	}

	public function import() {
		DBstart();
		$this->formatter->setData($this->data['zabbix_export']);

		$this->gatherReferences();

		if ($this->options['groups']['missed']) {
			$this->processGroups();
		}
		if ($this->options['templates']['exist'] || $this->options['templates']['missed']) {
			$this->processTemplates();
		}
		if ($this->options['hosts']['exist'] || $this->options['hosts']['missed']) {
			$this->processHosts();
		}
		if ($this->options['templates']['exist']
				|| $this->options['templates']['missed']
				|| $this->options['hosts']['exist']
				|| $this->options['hosts']['missed']) {
			$this->processApplications();
		}

		if ($this->options['items']['exist'] || $this->options['items']['missed']) {
			$this->processItems();
		}
		if ($this->options['discoveryrules']['exist'] || $this->options['discoveryrules']['missed']) {
			$this->processDiscoveryRules();
		}
		if ($this->options['triggers']['exist'] || $this->options['triggers']['missed']) {
			$this->processTriggers();
		}
		if ($this->options['graphs']['exist'] || $this->options['graphs']['missed']) {
			$this->processGraphs();
		}
		if (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN
				&& ($this->options['images']['exist'] || $this->options['images']['missed'])) {
			$this->processImages();
		}
		if ($this->options['maps']['exist'] || $this->options['maps']['missed']) {
			$this->processMaps();
		}
		DBend(false);
	}

	protected function gatherReferences() {
		if ($this->options['groups']['missed']) {
			$groups = $this->formatter->getGroups();
			$this->referencer->addGroups(zbx_objectValues($groups, 'name'));
		}

		if ($this->options['templates']['exist'] || $this->options['templates']['missed']) {
			$templates = $this->formatter->getTemplates();

			$templatesRefs = array();
			$groupsRefs = array();
			foreach ($templates as $template) {
				$templatesRefs[] = $template['template'];
				$groupsRefs = zbx_objectValues($template['groups'], 'name');
			}
			$this->referencer->addTemplates($templatesRefs);
			$this->referencer->addGroups($groupsRefs);
		}

		if ($this->options['hosts']['exist'] || $this->options['hosts']['missed']) {
			$hosts = $this->formatter->getHosts();

			$hostsRefs = array();
			$groupsRefs = array();
			$templatesRefs = array();
			foreach ($hosts as $host) {
				$hostsRefs[$host['host']] = $host['host'];
				$groupsRefs = array_merge($groupsRefs, zbx_objectValues($host['groups'], 'name'));
				if (isset($host['templates'])) {
					$templatesRefs = array_merge($templatesRefs, zbx_objectValues($host['templates'], 'name'));
				}
			}


			$allGraphs = $this->formatter->getGraphs();
			foreach ($allGraphs as $graph) {
				if ($graph['ymin_item_1']) {
					$hostsRefs[$graph['ymin_item_1']['host']] = $graph['ymin_item_1']['host'];
				}
				if ($graph['ymax_item_1']) {
					$hostsRefs[$graph['ymax_item_1']['host']] = $graph['ymax_item_1']['host'];
				}
				foreach ($graph['gitems'] as $gitem) {
					$hostsRefs[$gitem['item']['host']] = $gitem['item']['host'];
				}
			}

			$this->referencer->addHosts($hostsRefs);
			$this->referencer->addTemplates($templatesRefs);
			$this->referencer->addGroups($groupsRefs);
		}

		if ($this->options['templates']['exist']
				|| $this->options['templates']['missed']
				|| $this->options['hosts']['exist']
				|| $this->options['hosts']['missed']) {

			$applicationsRefs = array();
			$allApplications = $this->formatter->getApplications();
			foreach ($allApplications as $host => $applications) {
				$applicationsRefs[$host] = zbx_objectValues($applications, 'name');
			}
			$this->referencer->addApplications($applicationsRefs);
		}

		if ($this->options['items']['exist'] || $this->options['items']['missed']) {
			$allItems = $this->formatter->getItems();

			$itemsRefs = array();
			$applicationsRefs = array();
			$valueMapsRefs = array();

			foreach ($allItems as $host => $items) {
				foreach ($items as $item) {
					$applicationsRefs[$host] = zbx_objectValues($item['applications'], 'name');
					$valueMapsRefs = zbx_objectValues($item['valuemap'], 'name');
				}
				$itemsRefs[$host] = zbx_objectValues($items, 'key_');
			}
			$this->referencer->addItems($itemsRefs);
			$this->referencer->addApplications($applicationsRefs);
			$this->referencer->addValueMaps($valueMapsRefs);
		}

		if ($this->options['discoveryrules']['exist'] || $this->options['discoveryrules']['missed']) {
			$allDiscoveryRules = $this->formatter->getDiscoveryRules();

			$itemsRefs = array();
			$triggersRefs = array();
			$applicationsRefs = array();
			foreach ($allDiscoveryRules as $host => $discoveryRules) {
				if (!isset($itemsRefs[$host])) {
					$itemsRefs[$host] = array();
				}
				$itemsRefs[$host] = array_merge($itemsRefs[$host], zbx_objectValues($discoveryRules, 'key_'));

				foreach ($discoveryRules as $discoveryRule) {
					foreach ($discoveryRule['item_prototypes'] as $itemp) {
						$applicationsRefs[$host] = zbx_objectValues($itemp['applications'], 'name');
					}
					foreach ($discoveryRule['trigger_prototypes'] as $trigerp) {
						$triggersRefs[$trigerp['description']][] = $trigerp['expression'];
					}

					$itemsRefs[$host] = array_merge($itemsRefs[$host], zbx_objectValues($discoveryRule['item_prototypes'], 'key_'));
				}
			}
			$this->referencer->addItems($itemsRefs);
			$this->referencer->addApplications($applicationsRefs);
			$this->referencer->addTriggers($triggersRefs);
		}

		if ($this->options['graphs']['exist'] || $this->options['graphs']['missed']) {
			$allGraphs = $this->formatter->getGraphs();

			$graphsRefs = array();
			foreach ($allGraphs as $graph) {
				if ($graph['ymin_item_1']) {
					$graphsRefs[$graph['ymin_item_1']['host']][] = $graph['ymin_item_1']['key'];
				}
				if ($graph['ymax_item_1']) {
					$graphsRefs[$graph['ymax_item_1']['host']][] = $graph['ymax_item_1']['key'];
				}
				foreach ($graph['gitems'] as $gitem) {
					$graphsRefs[$gitem['item']['host']][] = $gitem['item']['key'];
				}
			}

			$this->referencer->addItems($graphsRefs);
		}

		if ($this->options['triggers']['exist'] || $this->options['triggers']['missed']) {
			$allTriggers = $this->formatter->getTriggers();

			$triggersRefs = array();
			foreach ($allTriggers as $trigger) {
				$triggersRefs[$trigger['description']][] = $trigger['expression'];

				foreach ($trigger['dependencies'] as $dependency) {
					$triggersRefs[$dependency['name']][] = $dependency['expression'];
				}
			}

			$this->referencer->addTriggers($triggersRefs);
		}
	}

	private function processGroups() {
		$groups = $this->formatter->getGroups();
		if (empty($groups)) {
			return;
		}

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

	private function processTemplates() {
		$hosts = $this->formatter->getTemplates();
		if (empty($hosts)) {
			return;
		}

		$allGroups = array();
		foreach ($hosts as $host) {
			// gather all host group names
			$allGroups += zbx_objectValues($host['groups'], 'name');
		}


		// set host groups ids
		$allDbGroups = $this->getGroupIds($allGroups);
		foreach ($hosts as &$host) {
			foreach ($host['groups'] as $gnum => $group) {
				unset($host['groups'][$gnum]);
				if (isset($allDbGroups[$group['name']])) {
					$host['groups'][$gnum] = array('groupid' => $allDbGroups[$group['name']]['groupid']);
				}
			}
		}
		unset($host);


		// get hostids for existing hosts
		$dbHosts = API::Template()->get(array(
			'filter' => array('host' => zbx_objectValues($hosts, 'host')),
			'output' => array('hostid', 'host'),
			'preservekeys' => true,
			'editable' => true
		));
		$dbHosts = zbx_toHash($dbHosts, 'host');
		$hostsToCreate = $hostsToUpdate = array();
		foreach ($hosts as $host) {
			if (isset($dbHosts[$host['host']])) {
				$host['templateid'] = $dbHosts[$host['host']]['templateid'];
				$hostsToUpdate[] = $host;
			}
			else {
				$hostsToCreate[] = $host;
			}
		}


		// create/update hosts and create hash host->hostid
		if ($this->options['templates']['missed'] && $hostsToCreate) {
			$newHostIds = API::Template()->create($hostsToCreate);
			foreach ($newHostIds['templateids'] as $hnum => $hostid) {
				$this->hostsCache[$hostsToCreate[$hnum]['host']] = $hostid;
			}
		}
		if ($this->options['templates']['exist'] && $hostsToUpdate) {
			API::Template()->update($hostsToUpdate);
			foreach ($hostsToUpdate as $host) {
				$this->hostsCache[$host['host']] = $host['templateid'];
			}
		}
	}

	private function processHosts() {
		$hosts = $this->formatter->getHosts();
		if (empty($hosts)) {
			return;
		}

		// create interfaces references
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
					throw new Exception(_s('Group "%1$s" does not exist', $group['name']));
				}
				$host['groups'][$gnum] = array('groupid' => $this->referencer->resolveGroup($group['name']));
			}
			if (isset($host['templates'])) {
				foreach ($host['templates'] as $tnum => $template) {
					if (!$this->referencer->resolveTemplate($template['name'])) {
						throw new Exception(_s('Template "%1$s" does not exist', $template['name']));
					}
					$host['templates'][$tnum] = array('templateid' => $this->referencer->resolveHostOrTemplate($template['name']));
				}
			}

			if ($this->referencer->resolveHost($host['host'])) {
				$host['hostid'] = $this->referencer->resolveHost($host['host']);
				$this->hostsCache[$host['host']] = $host['hostid'];
				$hostsToUpdate[] = $host;
			}
			else {
				$hostsToCreate[] = $host;
			}
		}


		// for exisitng hosts need to set interfaceid for existing interfaces
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
		if ($this->options['hosts']['missed'] && $hostsToCreate) {
			$newHostIds = API::Host()->create($hostsToCreate);
			foreach ($newHostIds['hostids'] as $hnum => $hostid) {
				$this->hostsCache[$hostsToCreate[$hnum]['host']] = $hostid;
				$this->referencer->addHostRef($hostsToCreate[$hnum]['host'], $hostid);
			}
		}
		if ($this->options['hosts']['exist'] && $hostsToUpdate) {
			API::Host()->update($hostsToUpdate);
		}

		// create interface hash interface_ref->interfaceid
		$dbInterfaces = API::HostInterface()->get(array(
			'hostids' => $this->hostsCache,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		foreach ($dbInterfaces as $dbInterface) {
			foreach ($hostInterfacesRefsByName as $hostName => $interfaceRefs) {
				if (!isset($this->interfacesCache[$this->hostsCache[$hostName]])) {
					$this->interfacesCache[$this->referencer->resolveHost($hostName)] = array();
				}

				foreach ($interfaceRefs as $refName => $interface) {
					if ($dbInterface['ip'] == $interface['ip']
							&& $dbInterface['dns'] == $interface['dns']
							&& $dbInterface['useip'] == $interface['useip']
							&& $dbInterface['port'] == $interface['port']
							&& $dbInterface['type'] == $interface['type']
							&& $dbInterface['main'] == $interface['main']) {
						$this->interfacesCache[$this->referencer->resolveHost($hostName)][$refName] = $dbInterface['interfaceid'];
					}
				}
			}
		}
	}

	private function processApplications() {
		$allApplciations = $this->formatter->getApplications();
		if (empty($allApplciations)) {
			return;
		}

		$applicationsToCreate = array();
		foreach ($allApplciations as $host => $applications) {
			$hostid = $this->referencer->resolveHost($host);
			if (isset($hostid)) {
				foreach ($applications as $application) {
					$application['hostid'] = $hostid;
					$appId = $this->referencer->resolveApplication($hostid, $application['name']);
					if (!$appId) {
						$applicationsToCreate[] = $application;
					}
				}
			}
		}

		// create applications and create hash hostid->name->applicationid
		$newApplicationsIds = API::Application()->create($applicationsToCreate);
		foreach ($newApplicationsIds['applicationids'] as $anum => $applicationId) {
			$application = $applicationsToCreate[$anum];
			$this->referencer->addApplicationRef($application['hostid'], $application['name'], $applicationId);
		}
	}

	private function processItems() {
		$allItems = $this->formatter->getItems();
		if (empty($allItems)) {
			return;
		}

		$itemsToCreate = array();
		$itemsToUpdate = array();
		foreach ($allItems as $host => $items) {
			$hostid = $this->referencer->resolveHostOrTemplate($host);
			if ($hostid) {
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
		}

		// create/update items and create hash hostid->key_->itemid
		if ($this->options['items']['missed'] && $itemsToCreate) {
			$newItemsIds = API::Item()->create($itemsToCreate);
			foreach ($newItemsIds['itemids'] as $inum => $itemid) {
				$item = $itemsToCreate[$inum];
				$this->referencer->addItemRef($item['hostid'], $item['key_'], $itemid);
			}
		}
		if ($this->options['items']['exist'] && $itemsToUpdate) {
			API::Item()->update($itemsToUpdate);
		}
	}

	private function processDiscoveryRules() {
		$allDiscoveryRules = $this->formatter->getDiscoveryRules();
		if (empty($allDiscoveryRules)) {
			return;
		}

		$itemsToCreate = array();
		$itemsToUpdate = array();
		$prototypesToUpdate = array();
		$prototypesToCreate = array();
		$triggersToCreate = array();
		$triggersToUpdate = array();
		foreach ($allDiscoveryRules as $host => $discoveryRules) {
			$hostid = $this->referencer->resolveHostOrTemplate($host);
			if ($hostid) {
				foreach ($discoveryRules as $item) {
					$item['hostid'] = $hostid;

					if (isset($item['interface_ref'])) {
						$item['interfaceid'] = $this->interfacesCache[$hostid][$item['interface_ref']];
					}

					$itemsId = $this->referencer->resolveItem($hostid, $item['key_']);
					if ($itemsId) {
						$item['itemid'] = $itemsId;
						$itemsToUpdate[] = $item;
					}
					else {
						$itemsToCreate[] = $item;
					}

					if (($itemsId && $this->options['items']['exist']) || (!$itemsId && $this->options['items']['missed'])) {
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
					}
				}
			}
		}

		// create/update items and create hash hostid->key_->itemid
		if ($this->options['items']['missed'] && $itemsToCreate) {
			$newItemsIds = API::DiscoveryRule()->create($itemsToCreate);
			foreach ($newItemsIds['itemids'] as $inum => $itemid) {
				$item = $itemsToCreate[$inum];
				$this->referencer->addItemRef($item['hostid'], $item['key_'], $itemid);
			}
		}
		if ($this->options['items']['exist'] && $itemsToUpdate) {
			API::DiscoveryRule()->update($itemsToUpdate);
		}


		if ($prototypesToCreate) {
			foreach ($prototypesToCreate as &$prototype) {
				$prototype['ruleid'] = $this->referencer->resolveItem($prototype['rule']['hostid'], $prototype['rule']['key']);
			}
			unset($prototype);
			API::ItemPrototype()->create($prototypesToCreate);
		}
		if ($prototypesToUpdate) {
			foreach ($prototypesToCreate as &$prototype) {
				$prototype['ruleid'] = $this->referencer->resolveItem($prototype['rule']['hostid'], $prototype['rule']['key']);
			}
			unset($prototype);

			API::ItemPrototype()->update($prototypesToUpdate);
		}


		if ($triggersToCreate) {
			API::TriggerPrototype()->create($triggersToCreate);
		}
		if ($triggersToUpdate) {
			API::TriggerPrototype()->update($triggersToUpdate);
		}


	}

	private function processGraphs() {
		$allGraphs = $this->formatter->getGraphs();
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
				$gitemhostId = $this->referencer->resolveHost($gitem['item']['host']);

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
		if ($this->options['graphs']['missed'] && $graphsToCreate) {
			API::Graph()->create($graphsToCreate);
		}
		if ($this->options['graphs']['exist'] && $graphsToUpdate) {
			API::Graph()->update($graphsToUpdate);
		}
	}

	private function processTriggers() {
		$allTriggers = $this->formatter->getTriggers();
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
					$deps[] = $this->referencer->resolveTrigger($dependency['name'], $dependency['expression']);
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
		if ($this->options['triggers']['missed'] && $triggersToCreate) {
			$newTriggerIds = API::Trigger()->create($triggersToCreate);
			foreach ($newTriggerIds['triggerids'] as $tnum => $triggerId) {
				$deps = array();
				foreach ($triggersToCreateDependencies[$tnum] as $dependency) {
					$deps[] = $this->referencer->resolveTrigger($dependency['name'], $dependency['expression']);
				}

				$triggerDependencies[] = array(
					'triggerid' => $triggerId,
					'dependencies' => $deps
				);
			}
		}
		if ($this->options['triggers']['exist'] && $triggersToUpdate) {
			API::Trigger()->update($triggersToUpdate);
		}

		if ($triggerDependencies) {
			API::Trigger()->update($triggerDependencies);
		}

	}

	private function processImages() {
		$allImages = $this->formatter->getImages();

		$imagesToUpdate = array();
		$allImages = zbx_toHash($allImages, 'name');

		$dbImages = DBselect('SELECT i.imageid, i.name FROM images i WHERE '.DBcondition('i.name', array_keys($allImages)));
		while ($dbImage = DBfetch($dbImages)) {
			$dbImage['image'] = $allImages[$dbImage['name']]['image'];
			$imagesToUpdate[] = $dbImage;
			unset($allImages[$dbImage['name']]);
		}

		if ($this->options['images']['missed']) {
			$allImages = array_values($allImages);
			$result = API::Image()->create($allImages);
			if (!$result) {
				throw new Exception(_('Cannot add image.'));
			}
		}

		if ($this->options['images']['exist']) {
			$result = API::Image()->update($imagesToUpdate);
			if (!$result) {
				throw new Exception(_('Cannot update image.'));
			}
		}
	}

	private function processMaps() {
		$allMaps = $this->formatter->getMaps();

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
		if ($existingMaps && $this->options['maps']['exist']) {
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
				$iconMap = API::IconMap()->get(array(
					'filter' => array('name' => $map['iconmap']),
					'output' => API_OUTPUT_SHORTEN,
					'nopermissions' => true,
					'preservekeys' => true
				));
				$iconMap = reset($iconMap);
				if (!$iconMap) {
					throw new Exception(_s('Cannot find icon map "%1$s" for map "%2$s".', $map['iconmap'], $map['name']));
				}

				$map['iconmapid'] = $iconMap['iconmapid'];
			}


			if (isset($map['backgroundid'])) {
				$image = getImageByIdent($map['backgroundid']);

				if (!$image) {
					throw new Exception(_s('Cannot find background image for map "%1$s.', $map['name']));
				}
				$map['backgroundid'] = $image['imageid'];
			}

			if (!isset($map['selements'])) {
				$map['selements'] = array();
			}
			else {
				$map['selements'] = array_values($map['selements']);
			}

			if (!isset($map['links'])) {
				$map['links'] = array();
			}
			else {
				$map['links'] = array_values($map['links']);
			}

			foreach ($map['selements'] as &$selement) {
				$nodeCaption = isset($selement['elementid']['node']) ? $selement['elementid']['node'].':' : '';

				if (!isset($selement['elementid'])) {
					$selement['elementid'] = 0;
				}
				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_MAP:
						$db_sysmaps = API::Map()->getObjects($selement['elementid']);
						if (empty($db_sysmaps)) {
							$error = S_CANNOT_FIND_MAP.' "'.$nodeCaption.$selement['elementid']['name'].'" '.S_USED_IN_EXPORTED_MAP_SMALL.' "'.$map['name'].'"';
							throw new Exception($error);
						}

						$tmp = reset($db_sysmaps);
						$selement['elementid'] = $tmp['sysmapid'];
						break;
					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$db_hostgroups = API::HostGroup()->getObjects($selement['elementid']);
						if (empty($db_hostgroups)) {
							$error = S_CANNOT_FIND_HOSTGROUP.' "'.$nodeCaption.$selement['elementid']['name'].'" '.S_USED_IN_EXPORTED_MAP_SMALL.' "'.$map['name'].'"';
							throw new Exception($error);
						}

						$tmp = reset($db_hostgroups);
						$selement['elementid'] = $tmp['groupid'];
						break;
					case SYSMAP_ELEMENT_TYPE_HOST:
						$db_hosts = API::Host()->getObjects($selement['elementid']);
						if (empty($db_hosts)) {
							$error = S_CANNOT_FIND_HOST.' "'.$nodeCaption.$selement['elementid']['host'].'" '.S_USED_IN_EXPORTED_MAP_SMALL.' "'.$map['name'].'"';
							throw new Exception($error);
						}

						$tmp = reset($db_hosts);
						$selement['elementid'] = $tmp['hostid'];
						break;
					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						$db_triggers = API::Trigger()->getObjects($selement['elementid']);
						if (empty($db_triggers)) {
							$error = S_CANNOT_FIND_TRIGGER.' "'.$nodeCaption.$selement['elementid']['host'].':'.$selement['elementid']['description'].'" '.S_USED_IN_EXPORTED_MAP_SMALL.' "'.$map['name'].'"';
							throw new Exception($error);
						}

						$tmp = reset($db_triggers);
						$selement['elementid'] = $tmp['triggerid'];
						break;
					case SYSMAP_ELEMENT_TYPE_IMAGE:
					default:
				}

				$icons = array('iconid_off', 'iconid_on', 'iconid_disabled', 'iconid_maintenance');
				foreach ($icons as $icon) {
					if (isset($selement[$icon])) {
						$image = getImageByIdent($selement[$icon]);
						if (!$image) {
							throw new Exception(_s('Cannot find icon "%1$s" for map "%2$s".', $selement[$icon]['name'], $map['name']));
						}
						$selement[$icon] = $image['imageid'];
					}
				}
			}
			unset($selement);

			foreach ($map['links'] as &$link) {
				if (!isset($link['linktriggers'])) continue;

				foreach ($link['linktriggers'] as &$linktrigger) {
					$db_triggers = API::Trigger()->getObjects($linktrigger['triggerid']);
					if (empty($db_triggers)) {
						$nodeCaption = isset($linktrigger['triggerid']['node']) ? $linktrigger['triggerid']['node'].':' : '';
						$error = S_CANNOT_FIND_TRIGGER.' "'.$nodeCaption.$linktrigger['triggerid']['host'].':'.$linktrigger['triggerid']['description'].'" '.S_USED_IN_EXPORTED_MAP_SMALL.' "'.$map['name'].'"';
						throw new Exception($error);
					}

					$tmp = reset($db_triggers);
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

		if ($this->options['maps']['missed'] && $mapsToCreate) {
			API::Map()->create($mapsToCreate);
		}
		if ($this->options['maps']['exist'] && $mapsToUpdate) {
			API::Map()->update($mapsToUpdate);
		}
	}



	private function getReader($ext) {
		switch ($ext) {
			case 'xml':
				return new CXmlImportReader();

			default:
				throw new InvalidArgumentException('Unknown import file extension.');
		}

	}

	private function getFormatter($version) {
		switch ($version) {
			case '1.8':
				return new C18ImportFormatter;

			case '2.0':
				return new C20ImportFormatter;

			default:
				throw new InvalidArgumentException('Unknown import version.');
		}

	}

	private function getImportVersion() {
		return $this->data['zabbix_export']['version'];

	}

	private static function validate($schema) {
		libxml_use_internal_errors(true);

		$result = self::$xml->relaxNGValidate($schema);

		if (!$result) {
			$errors = libxml_get_errors();
			libxml_clear_errors();

			foreach ($errors as $error) {
				$text = '';

				switch ($error->level) {
					case LIBXML_ERR_WARNING:
						$text .= S_XML_FILE_CONTAINS_ERRORS.'. Warning '.$error->code.': ';
						break;
					case LIBXML_ERR_ERROR:
						$text .= S_XML_FILE_CONTAINS_ERRORS.'. Error '.$error->code.': ';
						break;
					case LIBXML_ERR_FATAL:
						$text .= S_XML_FILE_CONTAINS_ERRORS.'. Fatal Error '.$error->code.': ';
						break;
				}

				$text .= trim($error->message).' [ Line: '.$error->line.' | Column: '.$error->column.' ]';
				throw new Exception($text);
			}
		}
		return true;
	}

}
