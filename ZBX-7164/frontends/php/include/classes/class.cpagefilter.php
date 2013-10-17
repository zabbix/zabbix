<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
 * @property string $groupid
 * @property string $hostid
 * @property string $triggerid
 * @property string $graphid
 * @property string $druleid
 * @property string $severityMin
 * @property array  $groups
 * @property array  $hosts
 * @property array  $graphs
 * @property array  $triggers
 * @property array  $drules
 * @property bool   $groupsSelected
 * @property bool   $groupsAll
 * @property bool   $hostsSelected
 * @property bool   $hostsAll
 * @property bool   $graphsSelected
 * @property bool   $triggersSelected
 * @property bool   $drulesSelected
 * @property bool   $drulesAll
 */
class CPageFilter {

	const GROUP_LATEST_IDX = 'web.latest.groupid';
	const HOST_LATEST_IDX = 'web.latest.hostid';
	const GRAPH_LATEST_IDX = 'web.latest.graphid';
	const TRIGGER_LATEST_IDX = 'web.latest.triggerid';
	const DRULE_LATEST_IDX = 'web.latest.druleid';

	/**
	 * Configuration options.
	 *
	 * @var array
	 */
	protected $config = array(
		// whether to allow all nodes
		'all_nodes' => null,

		// select the latest object viewed by the user on any page
		'select_latest' => null,

		// reset the remembered values if the remember first dropdown entry function is disabled
		'DDReset' => null,

		// if set to true selections will be remembered for each file separately,
		// if set to false - for each main menu section (monitoring, inventory, configuration etc.)
		'individual' => null,

		// if set to true and the remembered object is missing from the selection, sets the filter to the first
		// available object. If set to false, the selection will remain empty.
		'popupDD' => null,

		// Force the filter to select the given objects.
		// works only if the host given in 'hostid' belongs to that group or 'hostid' is not set
		'groupid' => null,

		// works only if a host group is selected or the host group filter value is set to 'all'
		'hostid' => null,

		// works only if a host is selected or the host filter value is set to 'all'
		'graphid' => null,

		// works only if a specific host has been selected, will NOT work if the host filter is set to 'all'
		'triggerid' => null,
		'druleid' => null,

		// API parameters to be used to retrieve filter objects
		'groups' => null,
		'hosts' => null,
		'graphs' => null,
		'triggers' => null,
		'drules' => null
	);

	/**
	 * Objects present in the filter.
	 *
	 * @var array
	 */
	protected $data = array(
		'groups' => null,
		'hosts' => null,
		'graphs' => null,
		'triggers' => null,
		'drules' => null
	);

	/**
	 * Selected objects IDs.
	 *
	 * @var array
	 */
	protected $ids = array(
		'groupid' => null,
		'hostid' => null,
		'triggerid' => null,
		'graphid' => null,
		'druleid' => null,
		'severityMin' => null
	);

	/**
	 * Contains information about the selected values.
	 * The '*Selected' value is set to true if a specific object is chosen or the corresponding filter is set to 'All'
	 * and contains objects.
	 * The '*All' value is set to true if the corresponding filter is set to 'All' and contains objects.
	 *
	 * @var array
	 */
	protected $isSelected = array(
		'groupsSelected' => null,
		'groupsAll' => null,
		'hostsSelected' => null,
		'hostsAll' => null,
		'graphsSelected' => null,
		'triggersSelected' => null,
		'drulesSelected' => null,
		'drulesAll' => null
	);

	/**
	 * User profile keys to be used when remembering the selected values.
	 *
	 * @see the 'individual' option for more info.
	 *
	 * @var array
	 */
	private $_profileIdx = array(
		'groupid' => null,
		'hostid' => null,
		'triggerid' => null,
		'graphid' => null,
		'druleid' => null,
		'severityMin' => null
	);

	/**
	 * IDs of specific objects to be selected.
	 *
	 * @var array
	 */
	private $_profileIds = array(
		'groupid' => null,
		'hostid' => null,
		'triggerid' => null,
		'graphid' => null,
		'druleid' => null,
		'severityMin' => null
	);

	/**
	 * Request ids.
	 *
	 * @var array
	 */
	private $_requestIds = array();

	/**
	 * Get value from $data, $ids or $isSelected arrays.
	 * Search occurs in mentioned above order.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
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

	/**
	 * Initialize filter features.
	 * Supported: Host groups, Hosts, Triggers, Graphs, Applications, Discovery rules, Minimum trigger severities.
	 *
	 * @param array  $options
	 * @param array  $options['config']
	 * @param bool   $options['config']['select_latest']
	 * @param bool   $options['config']['popupDD']
	 * @param bool   $options['config']['individual']
	 * @param bool   $options['config']['allow_all']
	 * @param bool   $options['config']['deny_all']
	 * @param array  $options['config']['DDFirstLabels']
	 * @param array  $options['hosts']
	 * @param string $options['hostid']
	 * @param array  $options['groups']
	 * @param string $options['groupid']
	 * @param array  $options['graphs']
	 * @param string $options['graphid']
	 * @param array  $options['triggers']
	 * @param string $options['triggerid']
	 * @param array  $options['drules']
	 * @param string $options['druleid']
	 * @param array  $options['applications']
	 * @param string $options['application']
	 * @param array  $options['severitiesMin']
	 * @param int    $options['severitiesMin']['default']
	 * @param string $options['severitiesMin']['mapId']
	 * @param string $options['severityMin']
	 */
	public function __construct(array $options = array()) {
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

		if (!isset($options['groupid'], $options['hostid'])) {
			if (isset($options['graphid'])) {
				$this->_updateByGraph($options);
			}
		}

		// groups
		if (isset($options['groups'])) {
			$this->_initGroups($options['groupid'], $options['groups'], isset($options['hostid']) ? $options['hostid'] : null);
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

		// applications
		if (isset($options['applications'])) {
			$this->_initApplications($options['application'], $options['applications']);
		}

		// severities min
		if (isset($options['severitiesMin'])) {
			$this->_initSeveritiesMin($options['severityMin'], $options['severitiesMin']);
		}
	}

	/**
	 * Retrieve objects stored in the user profile.
	 * If the 'select_latest' option is used, the IDs will be loaded from the web.latest.objectid profile values,
	 * otherwise - from the web.*.objectid field, depending on the use of the 'individial' option.
	 * If the 'DDReset' option is used, IDs will be reset to zeroes.
	 * The method also sets the scope for remembering the selected values, see the 'individual' option for more info.
	 *
	 * @param array $options
	 */
	private function _getProfiles(array $options) {
		global $page;

		$profileSection = $this->config['individual'] ? $page['file'] : $page['menu'];

		$this->_profileIdx['groups'] = 'web.'.$profileSection.'.groupid';
		$this->_profileIdx['hosts'] = 'web.'.$profileSection.'.hostid';
		$this->_profileIdx['graphs'] = 'web.'.$profileSection.'.graphid';
		$this->_profileIdx['triggers'] = 'web.'.$profileSection.'.triggerid';
		$this->_profileIdx['drules'] = 'web.'.$profileSection.'.druleid';
		$this->_profileIdx['application'] = 'web.'.$profileSection.'.application';
		$this->_profileIdx['severityMin'] = 'web.maps.severity_min';

		if ($this->config['select_latest']) {
			$this->_profileIds['groupid'] = CProfile::get(self::GROUP_LATEST_IDX);
			$this->_profileIds['hostid'] = CProfile::get(self::HOST_LATEST_IDX);
			$this->_profileIds['graphid'] = CProfile::get(self::GRAPH_LATEST_IDX);
			$this->_profileIds['triggerid'] = null;
			$this->_profileIds['druleid'] = CProfile::get(self::DRULE_LATEST_IDX);
			$this->_profileIds['application'] = '';
			$this->_profileIds['severityMin'] = null;
		}
		elseif ($this->config['DDReset'] && !$this->config['DDRemember']) {
			$this->_profileIds['groupid'] = 0;
			$this->_profileIds['hostid'] = 0;
			$this->_profileIds['graphid'] = 0;
			$this->_profileIds['triggerid'] = 0;
			$this->_profileIds['druleid'] = 0;
			$this->_profileIds['application'] = '';
			$this->_profileIds['severityMin'] = null;
		}
		else {
			$this->_profileIds['groupid'] = CProfile::get($this->_profileIdx['groups']);
			$this->_profileIds['hostid'] = CProfile::get($this->_profileIdx['hosts']);
			$this->_profileIds['graphid'] = CProfile::get($this->_profileIdx['graphs']);
			$this->_profileIds['triggerid'] = null;
			$this->_profileIds['druleid'] = CProfile::get($this->_profileIdx['drules']);
			$this->_profileIds['application'] = CProfile::get($this->_profileIdx['application']);

			// minimum severity
			$mapId = isset($options['severitiesMin']['mapId']) ? $options['severitiesMin']['mapId'] : null;
			$this->_profileIds['severityMin'] = CProfile::get($this->_profileIdx['severityMin'], null, $mapId);
		}

		$this->_requestIds['groupid'] = isset($options['groupid']) ? $options['groupid'] : null;
		$this->_requestIds['hostid'] = isset($options['hostid']) ? $options['hostid'] : null;
		$this->_requestIds['graphid'] = isset($options['graphid']) ? $options['graphid'] : null;
		$this->_requestIds['triggerid'] = isset($options['triggerid']) ? $options['triggerid'] : null;
		$this->_requestIds['druleid'] = isset($options['druleid']) ? $options['druleid'] : null;
		$this->_requestIds['application'] = isset($options['application']) ? $options['application'] : null;
		$this->_requestIds['severityMin'] = isset($options['severityMin']) ? $options['severityMin'] : null;
	}

	private function _updateByGraph(array &$options) {
		$graphs = API::Graph()->get(array(
			'graphids' => $options['graphid'],
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => API_OUTPUT_REFER,
			'selectTemplates' => API_OUTPUT_REFER,
			'selectGroups' => API_OUTPUT_REFER
		));

		if ($graph = reset($graphs)) {
			$groups = zbx_toHash($graph['groups'], 'groupid');
			$hosts = zbx_toHash($graph['hosts'], 'hostid');
			$templates = zbx_toHash($graph['templates'], 'templateid');

			if (isset($groups[$this->_profileIds['groupid']])) {
				$options['groupid'] = $this->_profileIds['groupid'];
			}
			else {
				$groupids = array_keys($groups);
				$options['groupid'] = reset($groupids);
			}

			if (isset($hosts[$this->_profileIds['hostid']])) {
				$options['hostid'] = $this->_profileIds['hostid'];
			}
			else {
				$hostids = array_keys($hosts);
				$options['hostid'] = reset($hostids);
			}

			if (is_null($options['hostid'])) {
				if (isset($templates[$this->_profileIds['hostid']])) {
					$options['hostid'] = $this->_profileIds['hostid'];
				}
				else {
					$templateids = array_keys($templates);
					$options['hostid'] = reset($templateids);
				}
			}
		}
	}

	/**
	 * Load available host groups, choose the selected host group and remember the selection.
	 * If the host given in the 'hostid' option does not belong to the selected host group, the selected host group
	 * will be reset to 0.
	 *
	 * @param int   $groupid
	 * @param array $options
	 * @param int   $hostid
	 */
	private function _initGroups($groupid, array $options, $hostid) {
		$def_options = array(
			'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
			'output' => array('groupid', 'name')
		);
		$options = zbx_array_merge($def_options, $options);
		$groups = API::HostGroup()->get($options);
		order_result($groups, 'name');

		$this->data['groups'] = array();
		foreach ($groups as $group) {
			$this->data['groups'][$group['groupid']] = $group;
		}

		// select remebered selection
		if (is_null($groupid) && $this->_profileIds['groupid']) {
			// set group only if host is in group or hostid is not set
			if ($hostid) {
				$host = API::Host()->get(array(
					'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
					'output' => array('hostid'),
					'hostids' => $hostid,
					'groupids' => $this->_profileIds['groupid']
				));
			}
			if (!$hostid || !empty($host)) {
				$groupid = $this->_profileIds['groupid'];
			}
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

	/**
	 * Load available hosts, choose the selected host and remember the selection.
	 * If no host group is selected, reset the selected host to 0.
	 *
	 * @param int    $hostId
	 * @param array  $options
	 * @param string $options['DDFirstLabel']
	 */
	private function _initHosts($hostId, array $options) {
		$this->data['hosts'] = array();

		if (isset($options['DDFirstLabel'])) {
			$this->config['DDFirstLabels']['hosts'] = $options['DDFirstLabel'];

			unset($options['DDFirstLabel']);
		}

		if (!$this->groupsSelected) {
			$hostId = 0;
		}
		else {
			$defaultOptions = array(
				'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
				'output' => array('hostid', 'name', 'status'),
				'groupids' => ($this->groupid > 0) ? $this->groupid : null
			);
			$hosts = API::Host()->get(zbx_array_merge($defaultOptions, $options));

			if ($hosts) {
				order_result($hosts, 'name');

				foreach ($hosts as $host) {
					$this->data['hosts'][$host['hostid']] = $host;
				}
			}

			// select remebered selection
			if (is_null($hostId) && $this->_profileIds['hostid']) {
				$hostId = $this->_profileIds['hostid'];
			}

			// nonexisting or unset $hostid
			if ((!isset($this->data['hosts'][$hostId]) && $hostId > 0) || is_null($hostId)) {
				// for popup select first host in the list
				if ($this->config['popupDD'] && !empty($this->data['hosts'])) {
					reset($this->data['hosts']);
					$hostId = key($this->data['hosts']);
				}
				// otherwise hostid = 0 for 'Dropdown first entry' option ALL or NONE
				else {
					$hostId = 0;
				}
			}
		}

		if (!is_null($this->_requestIds['hostid'])) {
			CProfile::update($this->_profileIdx['hosts'], $hostId, PROFILE_TYPE_ID);
			CProfile::update(self::HOST_LATEST_IDX, $hostId, PROFILE_TYPE_ID);
		}

		$this->isSelected['hostsSelected'] = (($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL && !empty($this->data['hosts'])) || $hostId > 0);
		$this->isSelected['hostsAll'] = ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL && !empty($this->data['hosts']) && $hostId == 0);
		$this->ids['hostid'] = $hostId;
	}

	/**
	 * Load available graphs, choose the selected graph and remember the selection.
	 * If no host is selected, reset the selected graph to 0.
	 *
	 * @param int   $graphid
	 * @param array $options
	 */
	private function _initGraphs($graphid, array $options) {
		$this->data['graphs'] = array();

		if (!$this->hostsSelected) {
			$graphid = 0;
		}
		else {
			$def_ptions = array(
				'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
				'output' => array('graphid', 'name'),
				'groupids' => ($this->groupid > 0 && $this->hostid == 0) ? $this->groupid : null,
				'hostids' => ($this->hostid > 0) ? $this->hostid : null,
				'expandName' => true
			);
			$options = zbx_array_merge($def_ptions, $options);
			$graphs = API::Graph()->get($options);
			order_result($graphs, 'name');

			foreach ($graphs as $graph) {
				$this->data['graphs'][$graph['graphid']] = $graph;
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
					if ($graph['name'] === $selectedGraphInfo['name']) {
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

	/**
	 * Load available triggers, choose the selected trigger and remember the selection.
	 * If no host is elected, or the host selection is set to 'All', reset the selected trigger to 0.
	 *
	 * @param int   $triggerid
	 * @param array $options
	 */
	private function _initTriggers($triggerid, array $options) {
		$this->data['triggers'] = array();

		if (!$this->hostsSelected || $this->hostsAll) {
			$triggerid = 0;
		}
		else {
			$def_ptions = array(
				'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
				'output' => array('triggerid', 'description'),
				'groupids' => ($this->groupid > 0 && $this->hostid == 0) ? $this->groupid : null,
				'hostids' => ($this->hostid > 0) ? $this->hostid : null
			);
			$options = zbx_array_merge($def_ptions, $options);
			$triggers = API::Trigger()->get($options);
			order_result($triggers, 'description');

			foreach ($triggers as $trigger) {
				$this->data['triggers'][$trigger['triggerid']] = $trigger;
			}

			if (is_null($triggerid)) {
				$triggerid = $this->_profileIds['triggerid'];
			}
			$triggerid = isset($this->data['triggers'][$triggerid]) ? $triggerid : 0;
		}

		$this->isSelected['triggersSelected'] = $triggerid > 0;
		$this->ids['triggerid'] = $triggerid;
	}

	/**
	 * Load the available network discovery rules, choose the selected rule and remember the selection.
	 *
	 * @param int   $druleid
	 * @param array $options
	 */
	private function _initDiscoveries($druleid, array $options) {
		$def_options = array(
			'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
			'output' => API_OUTPUT_EXTEND
		);
		$options = zbx_array_merge($def_options, $options);
		$drules = API::DRule()->get($options);
		order_result($drules, 'name');

		$this->data['drules'] = array();
		foreach ($drules as $drule) {
			$this->data['drules'][$drule['druleid']] = $drule;
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

	/**
	 * Set applications related variables.
	 *  - applications: all applications available for dropdown on page
	 *  - application: application curently selected, can be '' for 'all' or 'not selected'
	 *  - applicationsSelected: if an application selected, i.e. not 'not selected'
	 * Applications are dependent on groups.
	 *
	 * @param int   $application
	 * @param array $options
	 */
	private function _initApplications($application, array $options) {
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
				$this->data['applications'][$app['name']] = $app;
			}

			// select remembered selection
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

	/**
	 * Initialize minimum trigger severities.
	 *
	 * @param string $severityMin
	 * @param array  $options
	 * @param int    $options['default']
	 * @param string $options['mapId']
	 */
	private function _initSeveritiesMin($severityMin, array $options = array()) {
		$default = isset($options['default']) ? $options['default'] : TRIGGER_SEVERITY_NOT_CLASSIFIED;
		$mapId = isset($options['mapId']) ? $options['mapId'] : null;
		$severityMinProfile = isset($this->_profileIds['severityMin']) ? $this->_profileIds['severityMin'] : null;

		if ($severityMin === null && $severityMinProfile !== null) {
			$severityMin = $severityMinProfile;
		}

		if ($severityMin !== null) {
			if ($severityMin == $default) {
				CProfile::delete($this->_profileIdx['severityMin'], $mapId);
			}
			else {
				CProfile::update($this->_profileIdx['severityMin'], $severityMin, PROFILE_TYPE_INT, $mapId);
			}
		}

		$this->data['severitiesMin'] = getSeverityCaption();
		$this->data['severitiesMin'][$default] = $this->data['severitiesMin'][$default].SPACE.'('._('default').')';
		$this->ids['severityMin'] = ($severityMin === null) ? $default : $severityMin;
	}

	/**
	 * Get hosts combobox with selected item.
	 *
	 * @param bool $withNode
	 *
	 * @return CComboBox
	 */
	public function getHostsCB($withNode = false) {
		$items = $classes = array();
		foreach ($this->hosts as $id => $host) {
			$items[$id] = $host['name'];
			$classes[$id] = ($host['status'] == HOST_STATUS_NOT_MONITORED) ? 'not-monitored' : null;
		}
		$options = array('objectName' => 'hosts', 'classes' => $classes);

		return $this->_getCB('hostid', $this->hostid, $items, $withNode, $options);
	}

	/**
	 * Get host groups combobox with selected item.
	 *
	 * @param bool $withNode
	 *
	 * @return CComboBox
	 */
	public function getGroupsCB($withNode = false) {
		$items = array();
		foreach ($this->groups as $id => $group) {
			$items[$id] = $group['name'];
		}
		return $this->_getCB('groupid', $this->groupid, $items, $withNode, array('objectName' => 'groups'));
	}

	/**
	 * Get graphs combobox with selected item.
	 *
	 * @param bool $withNode
	 *
	 * @return CComboBox
	 */
	public function getGraphsCB($withNode = false) {
		$graphs = $this->graphs;
		if ($withNode) {
			foreach ($graphs as $id => $graph) {
				$graphs[$id] = get_node_name_by_elid($id, null, NAME_DELIMITER).$graph['name'];
			}
		}

		natcasesort($graphs);
		$graphs = array(0 => _('not selected')) + $graphs;

		$graphComboBox = new CComboBox('graphid', $this->graphid, 'javascript: submit();');
		foreach ($graphs as $id => $name) {
			$graphComboBox->addItem($id, $name);
		}

		return $graphComboBox;
	}

	/**
	 * Get discovery rules combobox with selected item.
	 *
	 * @param bool $withNode
	 *
	 * @return CComboBox
	 */
	public function getDiscoveryCB($withNode = false) {
		$items = array();
		foreach ($this->drules as $id => $drule) {
			$items[$id] = $drule['name'];
		}
		return $this->_getCB('druleid', $this->druleid, $items, $withNode, array('objectName' => 'discovery'));
	}

	/**
	 * Get applications combobox with selected item.
	 *
	 * @param bool $withNode
	 *
	 * @return CComboBox
	 */
	public function getApplicationsCB($withNode = false) {
		$items = array();
		foreach ($this->applications as $id => $application) {
			$items[$id] = $application['name'];
		}
		return $this->_getCB('application', $this->application, $items, $withNode, array(
			'objectName' => 'applications'
		));
	}

	/**
	 * Get minimum trigger severities combobox with selected item.
	 *
	 * @return CComboBox
	 */
	public function getSeveritiesMinCB() {
		return new CComboBox('severity_min', $this->severityMin, 'javascript: submit();', $this->severitiesMin);
	}

	/**
	 * Create combobox with available data.
	 * Preselect active item. Display nodes. Add addition 'not selected' or 'all' item to top adjusted by configuration.
	 *
	 * @param string $name
	 * @param string $selectedId
	 * @param array  $items
	 * @param bool   $withNode
	 * @param int    $allValue
	 * @param array  $options
	 * @param string $options['objectName']
	 * @param array  $options['classes']	array of class names for the combobox options with item IDs as keys
	 *
	 * @return CComboBox
	 */
	private function _getCB($name, $selectedId, $items, $withNode, array $options = array()) {
		$comboBox = new CComboBox($name, $selectedId, 'javascript: submit();');

		if ($withNode) {
			foreach ($items as $id => $item) {
				$items[$id] = get_node_name_by_elid($id, null, NAME_DELIMITER).$item;
			}
		}

		natcasesort($items);

		// add drop down first item
		if (!$this->config['popupDD']) {
			if (isset($this->config['DDFirstLabels'][$options['objectName']])) {
				$firstLabel = $this->config['DDFirstLabels'][$options['objectName']];
			}
			else {
				$firstLabel = ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_NONE) ? _('not selected') : _('all');
			}

			if ($name == 'application') {
				$items = array('' => $firstLabel) + $items;
			}
			else {
				$items = array($firstLabel) + $items;
			}
		}

		foreach ($items as $id => $name) {
			$comboBox->addItem($id, $name, null, 'yes', isset($options['classes'][$id]) ? $options['classes'][$id] : null);
		}

		return $comboBox;
	}
}
