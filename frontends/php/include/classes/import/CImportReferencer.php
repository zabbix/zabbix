<?php

class CImportReferencer {

	protected $groups = array();
	protected $templates = array();
	protected $hosts = array();
	protected $applications = array();
	protected $items = array();
	protected $triggers = array();
	protected $groupsRefs;
	protected $templatesRefs;
	protected $hostsRefs;
	protected $applicationsRefs;
	protected $itemsRefs;
	protected $triggersRefs;

	public function resolveGroup($name) {
		if ($this->groupsRefs === null) {
			$this->selectGroups();
		}

		return isset($this->groupsRefs[$name]) ? $this->groupsRefs[$name] : false;;
	}

	public function resolveHost($host) {
		if ($this->hostsRefs === null) {
			$this->selectHosts();
		}

		return isset($this->hostsRefs[$host]) ? $this->hostsRefs[$host] : false;
	}

	public function resolveTemplate($host) {
		if ($this->templatesRefs === null) {
			$this->selectTemplates();
		}
		return isset($this->templatesRefs[$host]) ? $this->templatesRefs[$host] : false;
	}

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

	public function resolveApplication($hostid, $name) {
		if ($this->applicationsRefs === null) {
			$this->selectApplications();
		}

		return isset($this->applicationsRefs[$hostid][$name]) ? $this->applicationsRefs[$hostid][$name] : false;
	}

	public function resolveItem($hostid, $key) {
		if ($this->itemsRefs === null) {
			$this->selectItems();
		}

		return isset($this->itemsRefs[$hostid][$key]) ? $this->itemsRefs[$hostid][$key] : false;
	}

	public function resolveTrigger($name, $expression) {
		if ($this->triggersRefs === null) {
			$this->selectTriggers();
		}

		return isset($this->triggersRefs[$name][$expression]) ? $this->triggersRefs[$name][$expression] : false;
	}




	public function addGroups($groups) {
		$this->groups = array_unique(array_merge($this->groups, $groups));
	}

	public function addGroupRef($name, $id) {
		$this->groupsRefs[$name] = $id;
	}


	public function addTemplates($templatesRefs) {
		$this->templates = array_unique(array_merge($this->templates, $templatesRefs));
	}

	public function addTemplateRef($name, $id) {
		$this->templatesRefs[$name] = $id;
	}


	public function addHosts($hostsRefs) {
		$this->hosts = array_unique(array_merge($this->hosts, $hostsRefs));
	}

	public function addHostRef($host, $id) {
		$this->hostsRefs[$host] = $id;
	}

	public function addApplications($applicationsRefs) {
		foreach ($applicationsRefs as $host => $applications) {
			if (!isset($this->applications[$host])) {
				$this->applications[$host] = array();
			}
			$this->applications[$host] = array_unique(array_merge($this->applications[$host], $applications));
		}
	}

	public function addApplicationRef($hostId, $name, $id) {
		$this->applicationsRefs[$hostId][$name] = $id;
	}

	public function addItems($itemsRefs) {
		foreach ($itemsRefs as $host => $keys) {
			if (!isset($this->items[$host])) {
				$this->items[$host] = array();
			}
			$this->items[$host] = array_unique(array_merge($this->items[$host], $keys));
		}
	}

	public function addItemRef($hostId, $key, $id) {
		$this->itemsRefs[$hostId][$key] = $id;
	}

	public function addValueMaps($valueMapsRefs) {
	}

	public function addTriggers($triggersRefs) {
		foreach ($triggersRefs as $name => $expressions) {
			if (!isset($this->triggers[$name])) {
				$this->triggers[$name] = array();
			}
			$this->triggers[$name] = array_unique(array_merge($this->triggers[$name], $expressions));
		}
	}

	public function addTriggerRef($name, $expression, $id) {
		$this->triggers[$name][$expression] = $id;
	}




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

	protected function selectTemplates() {
		if (!empty($this->templates)) {
			$this->templatesRefs = array();
			$dbTemplates = API::Template()->get(array(
				'filter' => array('host' => $this->templates),
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

	protected function selectHosts() {
		if (!empty($this->hosts)) {
			$this->hostsRefs = array();
			$dbHosts = API::Host()->get(array(
				'filter' => array('host' => $this->hosts),
				'output' => array('hostid', 'host'),
				'preservekeys' => true,
				'editable' => true
			));
			foreach ($dbHosts as $host) {
				$this->hostsRefs[$host['host']] = $host['hostid'];
			}

			$this->hosts = array();
		}
	}

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

			$this->applications = array();
		}
	}

	protected function selectItems() {
		if (!empty($this->items)) {
			$this->itemsRefs = array();

			$sqlWhere = array();
			foreach ($this->items as $host => $keys) {
				$hostId = $this->resolveHostOrTemplate($host);
				if ($hostId) {
					$sqlWhere[] = '(hostid='.$hostId.' AND '.DBcondition('key_', $keys).')';
				}
			}

			if ($sqlWhere) {
				$dbitems = DBselect('SELECT itemid, hostid, key_ FROM items WHERE '.implode(' OR ', $sqlWhere));
				while ($dbItem = DBfetch($dbitems)) {
					$this->itemsRefs[$dbItem['hostid']][$dbItem['key_']] = $dbItem['itemid'];
				}
			}

			$this->items = array();
		}
	}

	protected function selectTriggers() {
		if (!empty($this->triggers)) {
			$this->triggersRefs = array();

			$triggerIds = array();
			$sql = 'SELECT t.triggerid, t.expression, t.description
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

			$this->triggers = array();
		}
	}




}
