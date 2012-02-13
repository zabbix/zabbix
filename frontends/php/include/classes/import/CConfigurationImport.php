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

	public function __construct($file, $options = array()) {
		$this->options = array(
			'groups' => array('missed' => true),
			'hosts' => array('exist' => true, 'missed' => true),
			'templates' => array('exist' => true, 'missed' => true),
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
	}

	public function import() {
		$this->formatter->setData($this->data['zabbix_export']);

		if ($this->options['groups']['missed']) {
			$this->processGroups();
		}
		if ($this->options['hosts']['exist'] || $this->options['hosts']['missed']) {
			$this->processHosts();
			$this->processApplications();
		}
		if ($this->options['items']['exist'] || $this->options['items']['missed']) {
			$this->processItems();
		}

	}

	private function processGroups() {
		$groups = $this->formatter->getGroups();
		$groupNames = zbx_objectValues($groups, 'name');
		$dbGroups = API::HostGroup()->get(array(
			'filter' => array('name' => $groupNames),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'editable' => true
		));

		$sepGroups = zbx_array_diff($groups, $dbGroups, 'name');

		if ($this->options['groups']['missed']) {
			API::HostGroup()->create($sepGroups['first']);
		}

	}

	private function processTemplates() {
		$hosts = $this->formatter->getTemplates();

		$allGroups = array();
		foreach ($hosts as $host) {
			// gather all host group names
			$allGroups += zbx_objectValues($host['groups'], 'name');
		}

		// set host groups ids
		$allDbGroups = $this->getGroupIds($allGroups);
		foreach ($hosts as &$host) {
			foreach ($host['groups'] as $gnum => $group) {
				if (isset($allDbGroups[$group['name']])) {
					$host['groups'][$gnum] = $allDbGroups[$group['name']];
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
				$host['hostid'] = $dbHosts[$host['host']]['hostid'];
				$hostsToUpdate[] = $host;
			}
			else {
				$hostsToCreate[] = $host;
			}
		}


		// create/update hosts and create hash host->hostid
		if ($this->options['templates']['missed'] && $hostsToCreate) {
			$newHostIds = API::Template()->create($hostsToCreate);
			foreach ($newHostIds['hostids'] as $hnum => $hostid) {
				$this->hostsCache[$hostsToCreate[$hnum]['host']] = $hostid;
			}
		}
		if ($this->options['templates']['exist'] && $hostsToUpdate) {
			API::Template()->update($hostsToUpdate);
			foreach ($hostsToUpdate as $host) {
				$this->hostsCache[$host['host']] = $host['hostid'];
			}
		}
	}

	private function processHosts() {
		$hosts = $this->formatter->getHosts();

		// create interfaces references
		$hostInterfacesRefsByName = array();
		$allGroups = array();
		$allTemplates = array();
		foreach ($hosts as $host) {
			// gather all host group names
			$allGroups += zbx_objectValues($host['groups'], 'name');
			$allTemplates += zbx_objectValues($host['templates'], 'name');

			$hostInterfacesRefsByName[$host['host']] = array();
			foreach ($host['interfaces'] as $interface) {
				$hostInterfacesRefsByName[$host['host']][$interface['interface_ref']] = $interface;
			}
		}

		// set host groups ids
		$allDbGroups = $this->getGroupIds($allGroups);
		$allDbTemplates = $this->getTemplateIds($allTemplates);
		foreach ($hosts as &$host) {
			foreach ($host['groups'] as $gnum => $group) {
				if (isset($allDbGroups[$group['name']])) {
					$host['groups'][$gnum] = $allDbGroups[$group['name']];
				}
			}
			foreach ($host['templates'] as $tnum => $template) {
				if (isset($allDbTemplates[$template['host']])) {
					$host['templates'][$tnum] = array('templateid' => $allDbTemplates[$template['host']]['templateid']);
				}
			}
		}
		unset($host);



		// get hostids for existing hosts
		$dbHosts = API::Host()->get(array(
			'filter' => array('host' => zbx_objectValues($hosts, 'host')),
			'output' => array('hostid', 'host'),
			'preservekeys' => true,
			'editable' => true
		));
		$dbHosts = zbx_toHash($dbHosts, 'host');
		$hostsToCreate = $hostsToUpdate = array();
		foreach ($hosts as $host) {
			if (isset($dbHosts[$host['host']])) {
				$host['hostid'] = $dbHosts[$host['host']]['hostid'];
				$hostsToUpdate[] = $host;
			}
			else {
				$hostsToCreate[] = $host;
			}
		}


		// create/update hosts and create hash host->hostid
		if ($this->options['hosts']['missed'] && $hostsToCreate) {
			$newHostIds = API::Host()->create($hostsToCreate);
			foreach ($newHostIds['hostids'] as $hnum => $hostid) {
				$this->hostsCache[$hostsToCreate[$hnum]['host']] = $hostid;
			}
		}
		if ($this->options['hosts']['exist'] && $hostsToUpdate) {
			API::Host()->update($hostsToUpdate);
			foreach ($hostsToUpdate as $host) {
				$this->hostsCache[$host['host']] = $host['hostid'];
			}
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
					$this->interfacesCache[$this->hostsCache[$hostName]] = array();
				}

				foreach ($interfaceRefs as $refName => $interface) {
					if ($dbInterface['ip'] == $interface['ip']
							&& $dbInterface['dns'] == $interface['dns']
							&& $dbInterface['useip'] == $interface['useip']
							&& $dbInterface['port'] == $interface['port']
							&& $dbInterface['type'] == $interface['type']
							&& $dbInterface['main'] == $interface['main']) {
						$this->interfacesCache[$this->hostsCache[$hostName]][$refName] = $dbInterface['interfaceid'];
					}
				}
			}
		}
	}

	private function processApplications() {
		$allApplciations = $this->formatter->getApplications();

		$applicationsByHostId = array();
		// build where clause for finding existing items
		$sqlWhere = array();
		foreach ($allApplciations as $host => $applications) {
			if (isset($this->hostsCache[$host])) {
				$hostid = $this->hostsCache[$host];

				foreach ($applications as &$application) {
					$application['hostid'] = $hostid;
				}
				unset($application);

				// create list of all applications by hostid
				$applicationsByHostId[$hostid] = $applications;

				$sqlWhere[] = '(hostid='.$hostid.' AND '.DBcondition('name', array_keys($applications)).')';
			}
		}

		$applicationsToCreate = $applicationsToUpdate = array();
		if ($sqlWhere) {
			$exisistingApplicationsDb = DBselect('SELECT applicationid, hostid, name FROM applications WHERE '.implode(' OR ', $sqlWhere));
			while ($dbApplication = DBfetch($exisistingApplicationsDb)) {
				unset($applicationsByHostId[$dbApplication['hostid']][$dbApplication['name']]);
				$this->applicationsCache[$dbApplication['hostid']][$dbApplication['name']] = $dbApplication['applicationid'];
			}
		}

		foreach ($applicationsByHostId as $applications) {
			foreach ($applications as $application) {
				$applicationsToCreate[] = $application;
			}
		}


		// create applications and create hash hostid->name->applicationid
		$newApplicationsIds = API::Application()->create($applicationsToCreate);
		foreach ($newApplicationsIds['applicationids'] as $anum => $applicationId) {
			$application = $applicationsToCreate[$anum];

			if (!isset($applicationsCache[$application['hostid']])) {
				$this->applicationsCache[$application['hostid']] = array();
			}
			$this->applicationsCache[$application['hostid']][$application['name']] = $applicationId;
		}
	}

	private function processItems() {
		$allItems = $this->formatter->getItems();

		$itemsByHostId = array();
		// build where clause for finding existing items
		$sqlWhere = array();
		foreach ($allItems as $host => $items) {
			if (isset($this->hostsCache[$host])) {
				$hostid = $this->hostsCache[$host];

				foreach ($items as &$item) {
					$item['hostid'] = $hostid;
					$applicationsIds = array();
					foreach ($item['applications'] as $application) {
						$applicationsIds[] = $this->applicationsCache[$hostid][$application['name']];
					}
					$item['applications'] = $applicationsIds;
				}
				unset($item);

				// create list of all items by hostid
				$itemsByHostId[$hostid] = $items;

				$sqlWhere[] = '(hostid='.$hostid.' AND '.DBcondition('key_', array_keys($items)).')';
			}
		}

		$itemsToCreate = $itemsToUpdate = array();
		if ($sqlWhere) {
			$exisistingItemsDb = DBselect('SELECT itemid, hostid, key_ FROM items WHERE '.implode(' OR ', $sqlWhere));
			while ($dbItem = DBfetch($exisistingItemsDb)) {
				if (isset($itemsByHostId[$dbItem['hostid']][$dbItem['key_']])) {
					$item = $itemsByHostId[$dbItem['hostid']][$dbItem['key_']];
					$item['itemid'] = $dbItem['itemid'];
					if (isset($item['interface_ref'])) {
						$item['interfaceid'] = $this->interfacesCache[$item['interface_ref']];
					}

					$itemsToUpdate[] = $item;
					unset($itemsByHostId[$dbItem['hostid']][$dbItem['key_']]);
				}
			}
		}

		foreach ($itemsByHostId as $items) {
			foreach ($items as $item) {
				if (isset($item['interface_ref'])) {
					$item['interfaceid'] = $this->interfacesCache[$item['interface_ref']];
				}
				$itemsToCreate[] = $item;
			}
		}


		// create/update items and create hash hostid->key_->itemid
		$itemsCache = array();
		if ($this->options['items']['missed'] && $itemsToCreate) {
			$newItemsIds = API::Item()->create($itemsToCreate);
			foreach ($newItemsIds['itemids'] as $inum => $itemid) {
				$item = $itemsToCreate[$inum];

				if (!isset($itemsCache[$item['hostid']])) {
					$itemsCache[$item['hostid']] = array();
				}
				$itemsCache[$item['hostid']][$item['key_']] = $itemid;
			}
		}
		if ($this->options['items']['exist'] && $itemsToUpdate) {
			API::Item()->update($itemsToUpdate);
			foreach ($itemsToUpdate as $item) {
				if (!isset($itemsCache[$item['hostid']])) {
					$itemsCache[$item['hostid']] = array();
				}
				$itemsCache[$item['hostid']][$item['key_']] = $item['itemid'];
			}
		}
	}

	private function getGroupIds(array $groupsNames) {
		$allDbGroups = API::HostGroup()->get(array(
			'filter' => array('name' => array_unique($groupsNames)),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'editable' => true
		));
		return zbx_toHash($allDbGroups, 'name');
	}

	private function getTemplateIds(array $templatesNames) {
		$allDbTemplates = API::Template()->get(array(
			'filter' => array('host' => array_unique($templatesNames)),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'editable' => true
		));
		return zbx_toHash($allDbTemplates, 'host');
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
