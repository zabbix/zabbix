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
		if ($this->options['graphs']['exist'] || $this->options['graphs']['missed']) {
			$this->processGraphs();
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
				$hostsRefs[] = $host['host'];
				$groupsRefs = zbx_objectValues($host['groups'], 'name');
				if (isset($host['templates'])) {
					$templatesRefs = zbx_objectValues($host['templates'], 'name');
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
			$allItems = $this->formatter->getDiscoveryRules();

			$itemsRefs = array();
			foreach ($allItems as $host => $items) {
				$itemsRefs[$host] = zbx_objectValues($items, 'key_');
			}
			$this->referencer->addItems($itemsRefs);
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
					if (!$this->referencer->resolveTemplate($template['host'])) {
						throw new Exception(_s('Template "%1$s" does not exist', $template['host']));
					}
					$host['templates'][$tnum] = array('templateid' => $this->referencer->resolveHostOrTemplate($template['host']));
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
		foreach ($allDiscoveryRules as $host => $items) {
			$hostid = $this->referencer->resolveHostOrTemplate($host);
			if ($hostid) {
				foreach ($items as $item) {
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
	}

	private function processgraphs() {
		$allGraphs = $this->formatter->getGraphs();
		if (empty($allGraphs)) {
			return;
		}

		foreach ($allGraphs as $graph) {
			$graphHosts = array();
			foreach ($graph['graph_items'] as $gitem) {
				$gitemhostId = $this->referencer->resolveHost($gitem['item']['host']);
				$gitem['itemid'] = $this->referencer->resolveItem($gitemhostId, $gitem['item']['key']);

				$graphHosts[$gitemhostId] = $gitemhostId;
			}

			$sqlWhere[] = '(name='.$graph['name'].' AND '.DBcondition('hostid', $graphHosts).')';

		}

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

		$itemsToCreate = array();
		$itemsToUpdate = array();
		foreach ($allGraphs as $host => $items) {
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
