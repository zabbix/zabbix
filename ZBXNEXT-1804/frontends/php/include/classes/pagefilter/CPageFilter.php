<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * @property string $graphid
 * @property string $druleid
 * @property string $severityMin
 * @property array  $groups
 * @property array  $hosts
 * @property array  $graphs
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
	const DRULE_LATEST_IDX = 'web.latest.druleid';

	/**
	 * Configuration options.
	 *
	 * @var array
	 */
	protected $config = [
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
		'druleid' => null,

		// API parameters to be used to retrieve filter objects
		'groups' => null,
		'hosts' => null,
		'graphs' => null,
		'triggers' => null,
		'drules' => null
	];

	/**
	 * Objects present in the filter.
	 *
	 * @var array
	 */
	protected $data = [
		'groups' => null,
		'hosts' => null,
		'graphs' => null,
		'triggers' => null,
		'drules' => null
	];

	/**
	 * Selected objects IDs.
	 *
	 * @var array
	 */
	protected $ids = [
		'groupid' => null,
		'groupids' => null,
		'hostid' => null,
		'graphid' => null,
		'druleid' => null,
		'severityMin' => null
	];

	/**
	 * Contains information about the selected values.
	 * The '*Selected' value is set to true if a specific object is chosen or the corresponding filter is set to 'All'
	 * and contains objects.
	 * The '*All' value is set to true if the corresponding filter is set to 'All' and contains objects.
	 *
	 * @var array
	 */
	protected $isSelected = [
		'groupsSelected' => null,
		'groupsAll' => null,
		'hostsSelected' => null,
		'hostsAll' => null,
		'graphsSelected' => null,
		'triggersSelected' => null,
		'drulesSelected' => null,
		'drulesAll' => null
	];

	/**
	 * User profile keys to be used when remembering the selected values.
	 *
	 * @see the 'individual' option for more info.
	 *
	 * @var array
	 */
	private $_profileIdx = [
		'groupid' => null,
		'hostid' => null,
		'graphid' => null,
		'druleid' => null,
		'severityMin' => null
	];

	/**
	 * IDs of specific objects to be selected.
	 *
	 * @var array
	 */
	private $_profileIds = [
		'groupid' => null,
		'hostid' => null,
		'graphid' => null,
		'druleid' => null,
		'severityMin' => null
	];

	/**
	 * Request ids.
	 *
	 * @var array
	 */
	private $_requestIds = [];

	/**
	 * Get value from $data, $ids or $isSelected arrays.
	 * Search occurs in mentioned above order.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get($name) {
		if (array_key_exists($name, $this->data)) {
			return $this->data[$name];
		}
		elseif (array_key_exists($name, $this->ids)) {
			return $this->ids[$name];
		}
		elseif (array_key_exists($name, $this->isSelected)) {
			return $this->isSelected[$name];
		}
		else {
			trigger_error(_s('Try to read inaccessible property "%s".', get_class($this).'->'.$name), E_USER_WARNING);

			return false;
		}
	}

	/**
	 * Initialize filter features.
	 * Supported: Host groups, Hosts, Triggers, Graphs, Discovery rules, Minimum trigger severities.
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
	 * @param array  $options['drules']
	 * @param string $options['druleid']
	 * @param array  $options['severitiesMin']
	 * @param int    $options['severitiesMin']['default']
	 * @param string $options['severitiesMin']['mapId']
	 * @param string $options['severityMin']
	 */
	public function __construct(array $options = []) {
		$this->config['select_latest'] = isset($options['config']['select_latest']);
		$this->config['DDReset'] = getRequest('ddreset');
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
			$this->_initGroups($options['groupid'], $options['groups'], $this->_requestIds['hostid']);
		}

		// hosts
		if (isset($options['hosts'])) {
			$this->_initHosts($options['hostid'], $options['hosts']);
		}

		// graphs
		if (isset($options['graphs'])) {
			$this->_initGraphs($options['graphid'], $options['graphs']);
		}

		// drules
		if (isset($options['drules'])) {
			$this->_initDiscoveries($options['druleid'], $options['drules']);
		}

		// severities min
		if (isset($options['severitiesMin'])) {
			$this->_initSeveritiesMin($options['severityMin'], $options['severitiesMin'], $config);
		}
	}

	/**
	 * Retrieve objects stored in the user profile.
	 * If the 'select_latest' option is used, the IDs will be loaded from the web.latest.objectid profile values,
	 * otherwise - from the web.*.objectid field, depending on the use of the 'individual' option.
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
		$this->_profileIdx['drules'] = 'web.'.$profileSection.'.druleid';
		$this->_profileIdx['severityMin'] = 'web.maps.severity_min';

		if ($this->config['select_latest']) {
			$this->_profileIds['groupid'] = CProfile::get(self::GROUP_LATEST_IDX);
			$this->_profileIds['hostid'] = CProfile::get(self::HOST_LATEST_IDX);
			$this->_profileIds['graphid'] = CProfile::get(self::GRAPH_LATEST_IDX);
			$this->_profileIds['druleid'] = CProfile::get(self::DRULE_LATEST_IDX);
		}
		elseif ($this->config['DDReset'] && !$this->config['DDRemember']) {
			$this->_profileIds['groupid'] = 0;
			$this->_profileIds['hostid'] = 0;
			$this->_profileIds['graphid'] = 0;
			$this->_profileIds['druleid'] = 0;
		}
		else {
			$this->_profileIds['groupid'] = CProfile::get($this->_profileIdx['groups']);
			$this->_profileIds['hostid'] = CProfile::get($this->_profileIdx['hosts']);
			$this->_profileIds['graphid'] = CProfile::get($this->_profileIdx['graphs']);
			$this->_profileIds['druleid'] = CProfile::get($this->_profileIdx['drules']);
		}

		// minimum severity
		$mapid = (array_key_exists('severitiesMin', $options) && array_key_exists('mapId', $options['severitiesMin']))
			? $options['severitiesMin']['mapId']
			: null;
		$this->_profileIds['severityMin'] = CProfile::get($this->_profileIdx['severityMin'], null, $mapid);

		$this->_requestIds['groupid'] = isset($options['groupid']) ? $options['groupid'] : null;
		$this->_requestIds['hostid'] = isset($options['hostid']) ? $options['hostid'] : null;
		$this->_requestIds['graphid'] = isset($options['graphid']) ? $options['graphid'] : null;
		$this->_requestIds['druleid'] = isset($options['druleid']) ? $options['druleid'] : null;
		$this->_requestIds['severityMin'] = isset($options['severityMin']) ? $options['severityMin'] : null;
	}

	private function _updateByGraph(array &$options) {
		$graphs = API::Graph()->get([
			'graphids' => $options['graphid'],
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => ['hostid'],
			'selectTemplates' => ['templateid'],
			'selectGroups' => ['groupid']
		]);

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
	 * @param string $groupId
	 * @param array  $options
	 * @param string $hostId
	 */
	private function _initGroups($groupId, array $options, $hostId) {
		$defaultOptions = [
			'output' => ['groupid', 'name'],
			'preservekeys' => true,
			'sortfield' => ['name']
		];
		$options = zbx_array_merge($defaultOptions, $options);
		$this->data['groups'] = API::HostGroup()->get($options);

		$parents = [];
		$parent_name = '';
		foreach ($this->data['groups'] as $group) {
			$parent = explode('/', $group['name']);
			if (count($parent) > 1) {
				array_pop($parent);
				foreach ($parent as $sub_parent) {
					if ($parent_name === '') {
						$parent_name = $sub_parent;
					}
					else {
						$parent_name .= '/'.$sub_parent;
					}
					$parents[] = $parent_name;
				}
			}
		}

		if ($parents) {
			$parent_groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'filter' => ['name' => $parents],
				'preservekeys' => true
			]);

			$this->data['groups'] = array_replace($this->data['groups'], $parent_groups);

			CArrayHelper::sort($this->data['groups'], ['name']);
		}

		// select remembered selection
		if ($groupId === null && $this->_profileIds['groupid'] > 0) {
			// set group only if host is in group or hostid is not set
			$host = null;
			$template = null;

			if ($hostId) {
				// Get child groups for "profileIds['groupid']".
				$profileids = [$this->_profileIds['groupid']];
				$parent = $this->data['groups'][$this->_profileIds['groupid']]['name'].'/';
				foreach ($this->data['groups'] as $group) {
					if (strpos($group['name'], $parent) === 0) {
						$profileids[] = $group['groupid'];
					}
				}

				// Profile ID can contain zero, hence no host will be selected.
				$host = API::Host()->get([
					'output' => ['hostid'],
					'hostids' => $hostId,
					'groupids' => $profileids
				]);

				if (!$host) {
					$template = API::Template()->get([
						'output' => ['hostid'],
						'templateids' => $hostId,
						'groupids' => $profileids
					]);
				}
			}

			if (!$hostId || $host || $template) {
				$groupId = $this->_profileIds['groupid'];
			}
		}

		// nonexisting or unset $groupId
		if ((!isset($this->data['groups'][$groupId]) && $groupId > 0) || $groupId === null) {
			// for popup select first group in the list
			if ($this->config['popupDD'] && $this->data['groups']) {
				reset($this->data['groups']);
				$groupId = key($this->data['groups']);
			}
			// Otherwise for 'Dropdown first entry' option ALL or NONE.
			else {
				// If no group will be found for host use the default the option ALL (or NONE depending on config).
				$groupId = 0;

				// For 'Dropdown first entry' option NONE, select the first possible group when the host is given.
				if ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_NONE && $hostId) {
					$groupids = [];

					$hosts = API::Host()->get([
						'output' => ['hostid'],
						'selectGroups' => ['groupid'],
						'hostids' => [$hostId]
					]);

					if ($hosts) {
						$host = reset($hosts);
						$groupids = zbx_objectValues($host['groups'], 'groupid');
					}
					else {
						$templates = API::Template()->get([
							'output' => ['hostid'],
							'selectGroups' => ['groupid'],
							'templateids' => [$hostId]
						]);

						$template = reset($templates);
						$groupids = zbx_objectValues($template['groups'], 'groupid');
					}

					// Set first possible group (ordered by ID, not names), if found in list. Leave 0 (NONE) otherwise.
					foreach ($groupids as $id) {
						if (array_key_exists($id, $this->data['groups'])) {
							$groupId = $id;
							break;
						}
					}
				}
			}
		}

		CProfile::update($this->_profileIdx['groups'], $groupId, PROFILE_TYPE_ID);
		CProfile::update(self::GROUP_LATEST_IDX, $groupId, PROFILE_TYPE_ID);

		$firstIsAllAndHaveGroups = (($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL) && $this->data['groups']);

		$this->isSelected['groupsSelected'] = ($firstIsAllAndHaveGroups || $groupId > 0);
		$this->isSelected['groupsAll'] = ($firstIsAllAndHaveGroups && $groupId == 0);

		$this->ids['groupid'] = $groupId;

		if ($groupId != 0) {
			$this->ids['groupids'] = [$groupId];
			$parent = $this->data['groups'][$groupId]['name'].'/';
			foreach ($this->data['groups'] as $group) {
				if (strpos($group['name'], $parent) === 0) {
					$this->ids['groupids'][] = $group['groupid'];
				}
			}
		}
		else {
			$this->ids['groupids'] = null;
		}
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
		$this->data['hosts'] = [];

		if (isset($options['DDFirstLabel'])) {
			$this->config['DDFirstLabels']['hosts'] = $options['DDFirstLabel'];

			unset($options['DDFirstLabel']);
		}

		if (!$this->groupsSelected) {
			$hostId = 0;
		}
		else {
			$defaultOptions = [
				'output' => ['hostid', 'name', 'status'],
				'groupids' => $this->groupids
			];
			$hosts = API::Host()->get(zbx_array_merge($defaultOptions, $options));

			if ($hosts) {
				order_result($hosts, 'name');

				foreach ($hosts as $host) {
					$this->data['hosts'][$host['hostid']] = $host;
				}
			}

			// select remembered selection
			if ($hostId === null) {
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
		$this->data['graphs'] = [];

		if (!$this->hostsSelected) {
			$graphid = 0;
		}
		else {
			$def_ptions = [
				'output' => ['graphid', 'name'],
				'groupids' => ($this->groupid > 0 && $this->hostid == 0) ? $this->groupids : null,
				'hostids' => ($this->hostid > 0) ? $this->hostid : null,
				'expandName' => true
			];
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
				$options = [
					'output' => ['name'],
					'graphids' => [$graphid]
				];
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
	 * Load the available network discovery rules, choose the selected rule and remember the selection.
	 *
	 * @param int   $druleid
	 * @param array $options
	 */
	private function _initDiscoveries($druleid, array $options) {
		$def_options = [
			'output' => API_OUTPUT_EXTEND
		];
		$options = zbx_array_merge($def_options, $options);
		$drules = API::DRule()->get($options);
		order_result($drules, 'name');

		$this->data['drules'] = [];
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
	 * Initialize minimum trigger severities.
	 *
	 * @param string $severityMin			minimum trigger severity
	 * @param array  $options				array of options
	 * @param int    $options['default']	default severity
	 * @param string $options['mapId']		ID of a map
	 * @param array	 $config				array of configuration parameters for getting severity names
	 */
	private function _initSeveritiesMin($severityMin, array $options, array $config) {
		$default = isset($options['default']) ? $options['default'] : TRIGGER_SEVERITY_NOT_CLASSIFIED;
		$mapId = isset($options['mapId']) ? $options['mapId'] : 0;
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

		$this->data['severitiesMin'] = [];
		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severityName = getSeverityName($severity, $config);

			if ($severity == $default) {
				$this->data['severitiesMin'][] = $severityName.SPACE.'('._('default').')';
			}
			else {
				$this->data['severitiesMin'][] = $severityName;
			}
		}

		$this->ids['severityMin'] = ($severityMin === null) ? $default : $severityMin;
	}

	/**
	 * Get hosts combobox with selected item.
	 *
	 * @return CComboBox
	 */
	public function getHostsCB() {
		$items = $classes = [];
		foreach ($this->hosts as $id => $host) {
			$items[$id] = $host['name'];
			$classes[$id] = ($host['status'] == HOST_STATUS_NOT_MONITORED) ? ZBX_STYLE_RED : null;
		}
		$options = ['objectName' => 'hosts', 'classes' => $classes];

		return $this->_getCB('hostid', $this->hostid, $items, $options);
	}

	/**
	 * Get host groups combobox with selected item.
	 *
	 * @return CComboBox
	 */
	public function getGroupsCB() {
		$items = [];
		foreach ($this->groups as $id => $group) {
			$items[$id] = $group['name'];
		}
		return $this->_getCB('groupid', $this->groupid, $items, ['objectName' => 'groups']);
	}

	/**
	 * Get graphs combobox with selected item.
	 *
	 * @return CComboBox
	 */
	public function getGraphsCB() {
		$graphComboBox = new CComboBox('graphid', $this->graphid, 'javascript: submit();');
		$graphComboBox->addItem(0, _('not selected'));

		if ($this->graphs) {
			$graphs = $this->graphs;

			order_result($graphs, 'name');

			foreach ($graphs as $graph) {
				$graphComboBox->addItem($graph['graphid'], $graph['name']);
			}
		}

		return $graphComboBox;
	}

	/**
	 * Get discovery rules combobox with selected item.
	 *
	 * @return CComboBox
	 */
	public function getDiscoveryCB() {
		$items = [];
		foreach ($this->drules as $id => $drule) {
			$items[$id] = $drule['name'];
		}
		return $this->_getCB('druleid', $this->druleid, $items, ['objectName' => 'discovery']);
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
	 * Preselect active item. Add addition 'not selected' or 'all' item to top adjusted by configuration.
	 *
	 * @param string $name
	 * @param string $selectedId
	 * @param array  $items
	 * @param int    $allValue
	 * @param array  $options
	 * @param string $options['objectName']
	 * @param array  $options['classes']	array of class names for the combobox options with item IDs as keys
	 *
	 * @return CComboBox
	 */
	private function _getCB($name, $selectedId, $items, array $options = []) {
		$comboBox = new CComboBox($name, $selectedId, 'javascript: submit();');

		natcasesort($items);

		// add drop down first item
		if (!$this->config['popupDD']) {
			if (isset($this->config['DDFirstLabels'][$options['objectName']])) {
				$firstLabel = $this->config['DDFirstLabels'][$options['objectName']];
			}
			else {
				$firstLabel = ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_NONE) ? _('not selected') : _('all');
			}

			$items = [$firstLabel] + $items;
		}

		foreach ($items as $id => $name) {
			$comboBox->addItem($id, $name, null, true, isset($options['classes'][$id]) ? $options['classes'][$id] : null);
		}

		return $comboBox;
	}
}
