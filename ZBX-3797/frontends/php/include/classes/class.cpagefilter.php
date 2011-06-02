<?php

class CPageFilter{

	protected $data = array(); // groups, hosts, ...
	protected $ids = array(); // groupid, hostid, ...
	protected $isSelected = array(); // hostsSelected, groupsSelected, ...

	protected $config = array();

// profiles idx
	private $_profileIdx = array();
	
	private $_profileIds = array();
	private $_requestIds = array();

	const GROUP_LATEST_IDX = 'web.latest.groupid';
	const HOST_LATEST_IDX = 'web.latest.hostid';
	const GRAPH_LATEST_IDX = 'web.latest.graphid';
	const TRIGGER_LATEST_IDX = 'web.latest.triggerid';

	public function __get($name){
		if(isset($this->data[$name])){
			return $this->data[$name];
		}
		else if(isset($this->ids[$name])){
			return $this->ids[$name];
		}
		else if(isset($this->isSelected[$name])){
			return $this->isSelected[$name];
		}
		else{
			trigger_error('Try to read inaccessible property: '.get_class($this).'->'.$name, E_USER_WARNING);
		}
	}

	public function __construct($options=array()){
		global $ZBX_WITH_ALL_NODES;

/*
options = array(
	'config' => {'DDFirst': [ allow_all, deny_all], select_latest: [true,false], 'individual': [true,false]},
	'groups' => [apiget filters],
	'hosts' => [apiget filters],
	'graphs' => [apiget filters],
	'groupid' => groupid,
	'hostid' => hostid,
	'graphid' => graphid,
	'triggerid' => triggerid
);
*/
		$this->config['all_nodes'] = $ZBX_WITH_ALL_NODES;
		$this->config['select_latest'] = isset($options['config']['select_latest']);

		$config = select_config();

// Individual remember selections per page (not for menu)
		$this->config['individual'] = false;
		if(isset($options['config']['individual']) && !is_null($options['config']['individual'])){
			$this->config['individual'] = true;
		}

// DropDown
		$this->config['DDRemember'] = $config['dropdown_first_remember'];
		if(isset($options['config']['allow_all'])){
			$this->config['DDFirst'] = ZBX_DROPDOWN_FIRST_ALL;
		}
		else if(isset($options['config']['deny_all'])){
			$this->config['DDFirst'] = ZBX_DROPDOWN_FIRST_NONE;
		}
		else{
			$this->config['DDFirst'] = $config['dropdown_first_entry'];
		}

// profiles
		$this->_getProfiles($options);


		if(!isset($options['groupid'], $options['hostid'])){
			if(isset($options['graphid'])){
				$this->_updateByGraph($options);
			}
			else if(isset($options['triggerid'])){
				$this->_updateByTrigger($options);
			}
		}

		if(!isset($options['groupid'])){
			if(isset($options['hostid'])){
				$this->_updateByHost($options);
			}
		}

// groups
		if(isset($options['groups'])){
			if(!isset($options['groupid']) && isset($options['hostid'])){
				$options['groupid'] = 0;
			}

			$this->_initGroups($options['groupid'], $options['groups']);
		}

// hosts
		if(isset($options['hosts'])){
			$this->_initHosts($options['hostid'], $options['hosts']);
		}

// graphs
		if(isset($options['graphs'])){
			$this->_initGraphs($options['graphid'], $options['graphs']);
		}

// triggers
		if(isset($options['triggers'])){
			$this->_initTriggers($options['triggerid'], $options['triggers']);
		}
	}


	private function _getProfiles($options){
		global $page;

		$profileSection = ($this->config['individual']) ? $page['file'] : $page['menu'];

		$this->_profileIdx['groups'] = 'web.'.$profileSection.'.groupid';
		$this->_profileIdx['hosts'] = 'web.'.$profileSection.'.hostid';
		$this->_profileIdx['graphs'] = 'web.'.$profileSection.'.graphid';
		$this->_profileIdx['triggers'] = 'web.'.$profileSection.'.triggerid';

		$this->_profileIds['groupid'] = 0;
		$this->_profileIds['hostid'] = 0;
		$this->_profileIds['graphid'] = 0;
		$this->_profileIds['triggerid'] = 0;

		if($this->config['DDRemember']){
			if($this->config['select_latest']){
				$this->_profileIds['groupid'] = CProfile::get(self::GROUP_LATEST_IDX);
				$this->_profileIds['hostid'] = CProfile::get(self::HOST_LATEST_IDX);
				$this->_profileIds['graphid'] = CProfile::get(self::GRAPH_LATEST_IDX);
//				$this->_profileIds['triggerid'] = CProfile::get(self::TRIGGER_LATEST_IDX);
				$this->_profileIds['triggerid'] = null;
			}
			else{
				$this->_profileIds['groupid'] = CProfile::get($this->_profileIdx['groups']);
				$this->_profileIds['hostid'] = CProfile::get($this->_profileIdx['hosts']);
				$this->_profileIds['graphid'] = CProfile::get($this->_profileIdx['graphs']);
//				$this->_profileIds['triggerid'] = CProfile::get($this->_profileIdx['triggers']);
				$this->_profileIds['triggerid'] = null;
			}
		}

		$this->_requestIds['groupid'] = isset($options['groupid'])?$options['groupid']:null;
		$this->_requestIds['hostid'] = isset($options['hostid'])?$options['hostid']:null;
		$this->_requestIds['graphid'] = isset($options['graphid'])?$options['graphid']:null;
		$this->_requestIds['triggerid'] = isset($options['triggerid'])?$options['triggerid']:null;

//SDII($this->_profileIds);
//SDII($this->_requestIds);
	}

	private function _updateByGraph(&$options){
		$graphs = CGraph::get(array(
			'graphids' => $options['graphid'],
			'output' => API_OUTPUT_EXTEND,
			'select_hosts' => API_OUTPUT_REFER,
			'select_templates' => API_OUTPUT_REFER,
			'select_groups' => API_OUTPUT_REFER,
		));

		if($graph = reset($graphs)){
			$groups = zbx_toHash($graph['groups'], 'groupid');
			$hosts = zbx_toHash($graph['hosts'], 'hostid');
			$templates = zbx_toHash($graph['templates'], 'templateid');

			if(isset($groups[$this->_profileIds['groupid']])){
				$options['groupid'] = $this->_profileIds['groupid'];
			}
			else{
				$groupids = array_keys($groups);
				$options['groupid'] = reset($groupids);
			}

			if(isset($hosts[$this->_profileIds['hostid']])){
				$options['hostid'] = $this->_profileIds['hostid'];
			}
			else{
				$hostids = array_keys($hosts);
				$options['hostid'] = reset($hostids);
			}

			if(is_null($options['hostid'])){
				if(isset($templates[$this->_profileIds['hostid']])){
					$options['hostid'] = $this->_profileIds['hostid'];
				}
				else{
					$templateids = array_keys($templates);
					$options['hostid'] = reset($templateids);
				}
			}
		}
	}

	private function _updateByHost(&$options){
		$hosts = CHost::get(array(
			'hostids' => $options['hostid'],
			'templated_hosts' => 1,
			'output' => array('hostid', 'host'),
			'select_groups' => API_OUTPUT_REFER,
		));

		if($host = reset($hosts)){
			$groups = zbx_toHash($host['groups'], 'groupid');

			if(isset($groups[$this->_profileIds['groupid']])){
				$options['groupid'] = $this->_profileIds['groupid'];
			}
			else{
				$groupids = array_keys($groups);
				$options['groupid'] = reset($groupids);
			}
		}
	}

	private function _updateByTrigger(&$options){
		$triggers = CTrigger::get(array(
			'triggerids' => $options['triggerid'],
			'output' => API_OUTPUT_EXTEND,
			'select_hosts' => API_OUTPUT_REFER,
			'select_templates' => API_OUTPUT_REFER,
			'select_groups' => API_OUTPUT_REFER,
		));

		if($trigger = reset($triggers)){
			$groups = zbx_toHash($trigger['groups'], 'groupid');
			$hosts = zbx_toHash($trigger['hosts'], 'hostid');
			$templates = zbx_toHash($trigger['templates'], 'templateid');

			if(isset($groups[$this->_profileIds['groupid']])){
				$options['groupid'] = $this->_profileIds['groupid'];
			}
			else{
				$groupids = array_keys($groups);
				$options['groupid'] = reset($groupids);
			}

			if(isset($hosts[$this->_profileIds['hostid']])){
				$options['hostid'] = $this->_profileIds['hostid'];
			}
			else{
				$hostids = array_keys($hosts);
				$options['hostid'] = reset($hostids);
			}

			if(is_null($options['hostid'])){
				if(isset($templates[$this->_profileIds['hostid']])){
					$options['hostid'] = $this->_profileIds['hostid'];
				}
				else{
					$templateids = array_keys($templates);
					$options['hostid'] = reset($templateids);
				}
			}
		}
	}

	private function _initGroups($groupid, $options){
		$def_options = array(
			'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
			'output' => API_OUTPUT_EXTEND,
		);
		$options = zbx_array_merge($def_options, $options);
		$groups = CHostGroup::get($options);
		order_result($groups, 'name');

		$this->data['groups'] = array();
		foreach($groups as $group){
			$this->data['groups'][$group['groupid']] = $group['name'];
		}

		if(is_null($groupid)) $groupid = $this->_profileIds['groupid'];

		if((!isset($this->data['groups'][$groupid]) && ($groupid > 0)) || is_null($groupid)){
			if($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_NONE){
				$groupid = 0;
			}
			else if(is_null($this->_requestIds['groupid']) || ($this->_requestIds['groupid'] > 0)){
				$groupids = array_keys($this->data['groups']);
				$groupid = empty($groupids)?0:reset($groupids);
			}
		}

		if(!is_null($this->_requestIds['groupid'])){
			CProfile::update($this->_profileIdx['groups'], $groupid, PROFILE_TYPE_ID);
			CProfile::update(self::GROUP_LATEST_IDX, $groupid, PROFILE_TYPE_ID);
		}

		$this->isSelected['groupsSelected'] = (($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL) && !empty($this->data['groups'])) || ($groupid > 0);
		$this->isSelected['groupsAll'] = (($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL) && !empty($this->data['groups']) && ($groupid == 0));
		$this->ids['groupid'] = $groupid;
	}

	private function _initHosts($hostid, $options){
		$this->data['hosts'] = array();

		if(!$this->groupsSelected){
			$hostid = 0;
		}
		else{
			$def_options = array(
				'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
				'output' => array('hostid', 'host'),
				'groupids' => (($this->groupid > 0) ? $this->groupid : null),
			);
			$options = zbx_array_merge($def_options, $options);
			$hosts = CHost::get($options);
			order_result($hosts, 'host');

			foreach($hosts as $host){
				$this->data['hosts'][$host['hostid']] = $host['host'];
			}

			if(is_null($hostid)) $hostid = $this->_profileIds['hostid'];

			if((!isset($this->data['hosts'][$hostid]) && ($hostid > 0)) || is_null($hostid)){
				if($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_NONE){
					$hostid = 0;
				}
				else if(is_null($this->_requestIds['hostid']) || ($this->_requestIds['hostid'] > 0)){
					$hostids = array_keys($this->data['hosts']);
					$hostid = empty($hostids)?0:reset($hostids);
				}
			}
		}

		if(!is_null($this->_requestIds['hostid'])){
			CProfile::update($this->_profileIdx['hosts'], $hostid, PROFILE_TYPE_ID);
			CProfile::update(self::HOST_LATEST_IDX, $hostid, PROFILE_TYPE_ID);
		}

		$this->isSelected['hostsSelected'] = (($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL) && !empty($this->data['hosts'])) || ($hostid > 0);
		$this->isSelected['hostsAll'] = (($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL) && !empty($this->data['hosts']) && ($hostid == 0));

		$this->ids['hostid'] = $hostid;
	}

	private function _initGraphs($graphid, $options){
		$this->data['graphs'] = array();

		if(!$this->hostsSelected){
			$graphid = 0;
		}
		else{
			$def_ptions = array(
				'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
				'output' => API_OUTPUT_EXTEND,
				'groupids' => $this->groupid > 0 && $this->hostid == 0 ? $this->groupid : null,
				'hostids' => $this->hostid > 0 ? $this->hostid : null
			);
			$options = zbx_array_merge($def_ptions, $options);
			$graphs = CGraph::get($options);
			order_result($graphs, 'name');

			foreach($graphs as $graph){
				$this->data['graphs'][$graph['graphid']] = $graph['name'];
			}

			// no graphid provided
			if(is_null($graphid)){
				// if there is one saved in profile, let's take it from there
				$graphid = is_null($this->_profileIds['graphid'])
							? 0
							: $this->_profileIds['graphid'];
			}

			// if there is no graph with given id in selected host
			if($graphid > 0 && !isset($this->data['graphs'][$graphid])){
				// then let's take a look how the desired graph is named
				$options = array(
					'output' => API_OUTPUT_EXTEND,
					'graphids' => array($graphid)
				);
				$selectedGraphInfo = CGraph::get($options);
				$selectedGraphInfo = reset($selectedGraphInfo);
				$graphid = 0;
				// if there is a graph with the same name on new host, why not show it then?
				foreach($this->data['graphs'] as $gid => $graph){
					if($graph === $selectedGraphInfo['name']){
						$graphid = $gid;
						break;
					}
				}
			}
		}

		if(!is_null($this->_requestIds['graphid'])){
			CProfile::update($this->_profileIdx['graphs'], $graphid, PROFILE_TYPE_ID);
			CProfile::update(self::GRAPH_LATEST_IDX, $graphid, PROFILE_TYPE_ID);
		}

		$this->isSelected['graphsSelected'] = $graphid > 0;
		$this->ids['graphid'] = $graphid;
	}

	private function _initTriggers($triggerid, $options){
		$this->data['triggers'] = array();

		if(!$this->hostsSelected || $this->hostsAll){
			$triggerid = 0;
		}
		else{
			$def_ptions = array(
				'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
				'output' => API_OUTPUT_EXTEND,
				'groupids' => ((($this->groupid > 0) && ($this->hostid == 0)) ? $this->groupid : null),
				'hostids' => (($this->hostid > 0) ? $this->hostid : null),
			);
			$options = zbx_array_merge($def_ptions, $options);
			$triggers = Ctrigger::get($options);
			order_result($triggers, 'description');

			foreach($triggers as $trigger){
				$this->data['triggers'][$trigger['triggerid']] = $trigger['description'];
			}

			if(is_null($triggerid)) $triggerid = $this->_profileIds['triggerid'];
			$triggerid = isset($this->data['triggers'][$triggerid]) ? $triggerid : 0;
		}

		if(!is_null($this->_requestIds['triggerid'])){
//			CProfile::update($this->_profileIdx['triggers'], $triggerid, PROFILE_TYPE_ID);
//			CProfile::update(self::TRIGGER_LATEST_IDX, $triggerid, PROFILE_TYPE_ID);
		}

		$this->isSelected['triggersSelected'] = $triggerid > 0;
		$this->ids['triggerid'] = $triggerid;
	}

	public function getHostsCB($withNode=false){
		return $this->_getCB('hostid', $this->hostid, $this->hosts, $withNode);
	}

	public function getGroupsCB($withNode=false){
		return $this->_getCB('groupid', $this->groupid, $this->groups, $withNode);
	}

	public function getGraphsCB($withNode=false){		
		$cmb = new CComboBox('graphid', $this->graphid, 'javascript: submit();');
		$items = $this->graphs;

		if($withNode){
			foreach($items as $id => $item){
				$items[$id] = get_node_name_by_elid($id, null, ': ') . $item;
			}
		}

		natcasesort($items);
		$items = array(0 => S_NOT_SELECTED_SMALL) + $items;

		foreach($items as $id => $name){
			$cmb->addItem($id, $name);
		}

		return $cmb;
	}

	public function getTriggerCB($withNode=false){
		return $this->_getCB('triggerid', $this->triggerid, $this->triggers, $withNode);
	}

	private function _getCB($cbname, $selectedid, $items, $withNode){
		$cmb = new CComboBox($cbname, $selectedid,'javascript: submit();');

		if($withNode){
			foreach($items as $id => $item){
				$items[$id] = get_node_name_by_elid($id, null, ': ') . $item;
			}
		}

		natcasesort($items);
		$items = array(0 => ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_NONE) ? S_NOT_SELECTED_SMALL : S_ALL_SMALL) + $items;

		foreach($items as $id => $name){
			$cmb->addItem($id, $name);
		}

		return $cmb;
	}
}

?>
