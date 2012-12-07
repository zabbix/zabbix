<?php
/*
** Zabbix
** Copyright (C) 2001-2012 Zabbix SIA
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
 * @property string $groupid
 * @property string $hostid
 * @property array $groups
 * @property array $hosts
 */
class CPageFilter {

	protected $data = array(); // groups, hosts, ...
	protected $ids = array(); // groupid, hostid, ...
	protected $isSelected = array(); // hostsSelected, groupsSelected, ...
	protected $config = array();
	private $_profileIdx = array(); // profiles idx
	private $_profileIds = array();
	private $_requestIds = array();

	const GROUP_LATEST_IDX = 'web.latest.groupid';
	const HOST_LATEST_IDX = 'web.latest.hostid';
	const GRAPH_LATEST_IDX = 'web.latest.graphid';
	const TRIGGER_LATEST_IDX = 'web.latest.triggerid';
	const DRULE_LATEST_IDX = 'web.latest.druleid';

	public function __get($name) {
		if (isset($this->data[$name])) {
			return $this->data[$name];
		}
		elseif (isset($this->ids[$name])) {
			return $this->ids[$name];
		}
		elseif (isset($this->isSelected[$name])) {
			return $this->isSelected[$name];
		}
		else {
			trigger_error(_s('Try to read inaccessible property "%s".', get_class($this).'->'.$name), E_USER_WARNING);
			return false;
		}
	}

	public function __construct($options = array()) {
		global $ZBX_WITH_ALL_NODES;

		$this->config['all_nodes'] = $ZBX_WITH_ALL_NODES;
		$this->config['select_latest'] = isset($options['config']['select_latest']);
		$this->config['DDReset'] = get_request('ddreset', null);
		$this->config['popupDD'] = isset($options['config']['popupDD']);

		$config = select_config();

		// individual remember selections per page (not for menu)
		$this->config['individual'] = false;
		if (isset($options['config']['individual']) && !is_null($options['config']['individual'])) {
			$this->config['individual'] = true;
		}

		// dropdown
		$this->config['DDRemember'] = $config['dropdown_first_remember'];
		if (isset($options['config']['allow_all'])) {
			$this->config['DDFirst'] = ZBX_DROPDOWN_FIRST_ALL;
		}
		elseif (isset($options['config']['deny_all'])) {
			$this->config['DDFirst'] = ZBX_DROPDOWN_FIRST_NONE;
		}
		else {
			$this->config['DDFirst'] = $config['dropdown_first_entry'];
		}

		// profiles
		$this->_getProfiles($options);

		// groups
		if (isset($options['groups'])) {
			$this->_initGroups($options['groupid'], $options['groups']);
		}

		// hosts
		if (isset($options['hosts'])) {
			$this->_initHosts($options['hostid'], $options['hosts']);
		}

		// graphs
		if (isset($options['graphs'])) {
			$this->_initGraphs($options['graphid'], $options['graphs']);
		}

		// triggers
		if (isset($options['triggers'])) {
			$this->_initTriggers($options['triggerid'], $options['triggers']);
		}

		// drules
		if (isset($options['drules'])) {
			$this->_initDiscoveries($options['druleid'], $options['drules']);
		}

		if (isset($options['applications'])) {
			$this->_initApplications($options['application'], $options['applications']);
		}
	}

	private function _getProfiles($options) {
		global $page;

		$profileSection = $this->config['individual'] ? $page['file'] : $page['menu'];
		$this->_profileIdx['groups'] = 'web.'.$profileSection.'.groupid';
		$this->_profileIdx['hosts'] = 'web.'.$profileSection.'.hostid';
		$this->_profileIdx['graphs'] = 'web.'.$profileSection.'.graphid';
		$this->_profileIdx['triggers'] = 'web.'.$profileSection.'.triggerid';
		$this->_profileIdx['drules'] = 'web.'.$profileSection.'.druleid';
		$this->_profileIdx['application'] = 'web.'.$profileSection.'.application';

		if ($this->config['select_latest']) {
			$this->_profileIds['groupid'] = CProfile::get(self::GROUP_LATEST_IDX);
			$this->_profileIds['hostid'] = CProfile::get(self::HOST_LATEST_IDX);
			$this->_profileIds['graphid'] = CProfile::get(self::GRAPH_LATEST_IDX);
			$this->_profileIds['triggerid'] = null;
			$this->_profileIds['druleid'] = CProfile::get(self::DRULE_LATEST_IDX);
			$this->_profileIds['application'] = '';
		}
		elseif ($this->config['DDReset'] && !$this->config['DDRemember']) {
			$this->_profileIds['groupid'] = 0;
			$this->_profileIds['hostid'] = 0;
			$this->_profileIds['graphid'] = 0;
			$this->_profileIds['triggerid'] = 0;
			$this->_profileIds['druleid'] = 0;
			$this->_profileIds['application'] = '';
		}
		else {
			$this->_profileIds['groupid'] = CProfile::get($this->_profileIdx['groups']);
			$this->_profileIds['hostid'] = CProfile::get($this->_profileIdx['hosts']);
			$this->_profileIds['graphid'] = CProfile::get($this->_profileIdx['graphs']);
			$this->_profileIds['triggerid'] = null;
			$this->_profileIds['druleid'] = CProfile::get($this->_profileIdx['drules']);
			$this->_profileIds['application'] = CProfile::get($this->_profileIdx['application']);
		}

		$this->_requestIds['groupid'] = isset($options['groupid']) ? $options['groupid'] : null;
		$this->_requestIds['hostid'] = isset($options['hostid']) ? $options['hostid'] : null;
		$this->_requestIds['graphid'] = isset($options['graphid']) ? $options['graphid'] : null;
		$this->_requestIds['triggerid'] = isset($options['triggerid']) ? $options['triggerid'] : null;
		$this->_requestIds['druleid'] = isset($options['druleid']) ? $options['druleid'] : null;
		$this->_requestIds['application'] = isset($options['application']) ? $options['application'] : null;
	}

	private function _initGroups($groupid, $options) {
		$def_options = array(
			'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
			'output' => API_OUTPUT_EXTEND
		);
		$options = zbx_array_merge($def_options, $options);
		$groups = API::HostGroup()->get($options);
		order_result($groups, 'name');

		$this->data['groups'] = array();
		foreach ($groups as $group) {
			$this->data['groups'][$group['groupid']] = $group['name'];
		}

		// select remebered selection
		if (is_null($groupid) && $this->_profileIds['groupid']) {
			$groupid = $this->_profileIds['groupid'];
		}

		// nonexisting or unset $groupid
		if ((!isset($this->data['groups'][$groupid]) && $groupid > 0) || is_null($groupid)) {
			// for popup select first group in the list
			if ($this->config['popupDD'] && !empty($this->data['groups'])) {
				reset($this->data['groups']);
				$groupid = key($this->data['groups']);
			}
			// otherwise groupid = 0 for 'Dropdown first entry' option ALL or NONE
			else {
				$groupid = 0;
			}
		}

		CProfile::update($this->_profileIdx['groups'], $groupid, PROFILE_TYPE_ID);
		CProfile::update(self::GROUP_LATEST_IDX, $groupid, PROFILE_TYPE_ID);

		$this->isSelected['groupsSelected'] = ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL && !empty($this->data['groups'])) || $groupid > 0;
		$this->isSelected['groupsAll'] = $this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL && !empty($this->data['groups']) && $groupid == 0;
		$this->ids['groupid'] = $groupid;
	}

	private function _initHosts($hostid, $options) {
		$this->data['hosts'] = array();

		if (!$this->groupsSelected) {
			$hostid = 0;
		}
		else {
			$def_options = array(
				'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
				'output' => array('hostid', 'name'),
				'groupids' => ($this->groupid > 0) ? $this->groupid : null
			);
			$options = zbx_array_merge($def_options, $options);
			$hosts = API::Host()->get($options);
			order_result($hosts, 'name');

			foreach ($hosts as $host) {
				$this->data['hosts'][$host['hostid']] = $host['name'];
			}

			// select remebered selection
			if (is_null($hostid) && $this->_profileIds['hostid']) {
				$hostid = $this->_profileIds['hostid'];
			}

			// nonexisting or unset $hostid
			if ((!isset($this->data['hosts'][$hostid]) && $hostid > 0) || is_null($hostid)) {
				// for popup select first host in the list
				if ($this->config['popupDD'] && !empty($this->data['hosts'])) {
					reset($this->data['hosts']);
					$hostid = key($this->data['hosts']);
				}
				// otherwise hostid = 0 for 'Dropdown first entry' option ALL or NONE
				else {
					$hostid = 0;
				}
			}
		}

		if (!is_null($this->_requestIds['hostid'])) {
			CProfile::update($this->_profileIdx['hosts'], $hostid, PROFILE_TYPE_ID);
			CProfile::update(self::HOST_LATEST_IDX, $hostid, PROFILE_TYPE_ID);
		}
		$this->isSelected['hostsSelected'] = ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL && !empty($this->data['hosts'])) || $hostid > 0;
		$this->isSelected['hostsAll'] = $this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL && !empty($this->data['hosts']) && $hostid == 0;
		$this->ids['hostid'] = $hostid;
	}

	private function _initGraphs($graphid, $options) {
		$this->data['graphs'] = array();

		if (!$this->hostsSelected) {
			$graphid = 0;
		}
		else {
			$def_ptions = array(
				'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
				'output' => API_OUTPUT_EXTEND,
				'groupids' => ($this->groupid > 0 && $this->hostid == 0) ? $this->groupid : null,
				'hostids' => ($this->hostid > 0) ? $this->hostid : null
			);
			$options = zbx_array_merge($def_ptions, $options);
			$graphs = API::Graph()->get($options);
			order_result($graphs, 'name');

			foreach ($graphs as $graph) {
				$this->data['graphs'][$graph['graphid']] = $graph['name'];
			}

			// no graphid provided
			if (is_null($graphid)) {
				// if there is one saved in profile, let's take it from there
				$graphid = is_null($this->_profileIds['graphid']) ? 0 : $this->_profileIds['graphid'];
			}

			// if there is no graph with given id in selected host
			if ($graphid > 0 && !isset($this->data['graphs'][$graphid])) {
				// then let's take a look how the desired graph is named
				$options = array(
					'output' => array('name'),
					'graphids' => array($graphid)
				);
				$selectedGraphInfo = API::Graph()->get($options);
				$selectedGraphInfo = reset($selectedGraphInfo);
				$graphid = 0;

				// if there is a graph with the same name on new host, why not show it then?
				foreach ($this->data['graphs'] as $gid => $graph) {
					if ($graph === $selectedGraphInfo['name']) {
						$graphid = $gid;
						break;
					}
				}
			}
		}

		if (!is_null($this->_requestIds['graphid'])) {
			CProfile::update($this->_profileIdx['graphs'], $graphid, PROFILE_TYPE_ID);
			CProfile::update(self::GRAPH_LATEST_IDX, $graphid, PROFILE_TYPE_ID);
		}
		$this->isSelected['graphsSelected'] = $graphid > 0;
		$this->ids['graphid'] = $graphid;
	}

	private function _initTriggers($triggerid, $options) {
		$this->data['triggers'] = array();

		if (!$this->hostsSelected || $this->hostsAll) {
			$triggerid = 0;
		}
		else {
			$def_ptions = array(
				'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
				'output' => API_OUTPUT_EXTEND,
				'groupids' => ($this->groupid > 0 && $this->hostid == 0) ? $this->groupid : null,
				'hostids' => ($this->hostid > 0) ? $this->hostid : null
			);
			$options = zbx_array_merge($def_ptions, $options);
			$triggers = API::Trigger()->get($options);
			order_result($triggers, 'description');

			foreach ($triggers as $trigger) {
				$this->data['triggers'][$trigger['triggerid']] = $trigger['description'];
			}

			if (is_null($triggerid)) {
				$triggerid = $this->_profileIds['triggerid'];
			}
			$triggerid = isset($this->data['triggers'][$triggerid]) ? $triggerid : 0;
		}

		$this->isSelected['triggersSelected'] = $triggerid > 0;
		$this->ids['triggerid'] = $triggerid;
	}

	private function _initDiscoveries($druleid, $options) {
		$def_options = array(
			'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
			'output' => API_OUTPUT_EXTEND
		);
		$options = zbx_array_merge($def_options, $options);
		$drules = API::DRule()->get($options);
		order_result($drules, 'name');

		$this->data['drules'] = array();
		foreach ($drules as $drule) {
			$this->data['drules'][$drule['druleid']] = $drule['name'];
		}

		if (is_null($druleid)) {
			$druleid = $this->_profileIds['druleid'];
		}

		if ((!isset($this->data['drules'][$druleid]) && $druleid > 0) || is_null($druleid)) {
			if ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_NONE) {
				$druleid = 0;
			}
			elseif (is_null($this->_requestIds['druleid']) || $this->_requestIds['druleid'] > 0) {
				$druleids = array_keys($this->data['drules']);
				$druleid = empty($druleids) ? 0 : reset($druleids);
			}
		}

		CProfile::update($this->_profileIdx['drules'], $druleid, PROFILE_TYPE_ID);
		CProfile::update(self::DRULE_LATEST_IDX, $druleid, PROFILE_TYPE_ID);

		$this->isSelected['drulesSelected'] = ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL && !empty($this->data['drules'])) || $druleid > 0;
		$this->isSelected['drulesAll'] = $this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL && !empty($this->data['drules']) && $druleid == 0;
		$this->ids['druleid'] = $druleid;
	}

	private function _initApplications($application, $options) {
		$this->data['applications'] = array();

		if (!$this->groupsSelected) {
			$application = '';
		}
		else {
			$def_options = array(
				'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
				'output' => array('name'),
				'groupids' => ($this->groupid > 0) ? $this->groupid : null
			);
			$options = zbx_array_merge($def_options, $options);
			$applications = API::Application()->get($options);

			foreach ($applications as $app) {
				$this->data['applications'][$app['name']] = $app['name'];
			}

			// select remebered selection
			if (is_null($application) && $this->_profileIds['application']) {
				$application = $this->_profileIds['application'];
			}

			// nonexisting or unset application
			if ((!isset($this->data['applications'][$application]) && $application !== '') || is_null($application)) {
				$application = '';
			}
		}

		if (!is_null($this->_requestIds['application'])) {
			CProfile::update($this->_profileIdx['application'], $application, PROFILE_TYPE_STR);
		}
		$this->isSelected['applicationsSelected'] = ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL && !empty($this->data['applications'])) || $application !== '';
		$this->isSelected['applicationsAll'] = $this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL && !empty($this->data['applications']) && $application === '';
		$this->ids['application'] = $application;
	}

	public function getHostsCB($withNode = false) {
		return $this->_getCB('hostid', $this->hostid, $this->hosts, $withNode);
	}

	public function getGroupsCB($withNode = false) {
		return $this->_getCB('groupid', $this->groupid, $this->groups, $withNode);
	}

	public function getGraphsCB($withNode = false) {
		$items = $this->graphs;
		if ($withNode) {
			foreach ($items as $id => $item) {
				$items[$id] = get_node_name_by_elid($id, null, ': ').$item;
			}
		}

		natcasesort($items);
		$items = array(0 => _('not selected')) + $items;

		$graphComboBox = new CComboBox('graphid', $this->graphid, 'javascript: submit();');
		foreach ($items as $id => $name) {
			$graphComboBox->addItem($id, $name);
		}

		return $graphComboBox;
	}

	public function getDiscoveryCB($withNode = false) {
		return $this->_getCB('druleid', $this->druleid, $this->drules, $withNode);
	}

	public function getApplicationsCB($withNode = false) {
		return $this->_getCB('application', $this->application, $this->applications, $withNode, '');
	}

	private function _getCB($cbname, $selectedid, $items, $withNode, $allValue = 0) {
		$cmb = new CComboBox($cbname, $selectedid, 'javascript: submit();');

		if ($withNode) {
			foreach ($items as $id => $item) {
				$items[$id] = get_node_name_by_elid($id, null, ': ').$item;
			}
		}

		natcasesort($items);

		if (!$this->config['popupDD']) {
			$items = array($allValue => ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_NONE) ? _('not selected') : _('all')) + $items;
		}

		foreach ($items as $id => $name) {
			$cmb->addItem($id, $name);
		}

		return $cmb;
	}
}
