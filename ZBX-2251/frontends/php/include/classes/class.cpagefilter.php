<?php

class CPageFilter{

	protected $data = array(); // groups, hosts, ...
	protected $ids = array(); // groupid, hostid, ...
	protected $isSelected = array(); // hostsSelected, groupsSelected, ...

	protected $config = array();

// profiles idx
	private $_profileIdx = array();

	const GROUP_LATEST_IDX = 'web.latest.groupid';
	const HOST_LATEST_IDX = 'web.latest.hostid';
	const GRAPH_LATEST_IDX = 'web.latest.graphid';


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
		global $page, $ZBX_WITH_ALL_NODES;

		/* options = array(
			'config' => {DDFirst: [ allow_all, deny_all, select_latest ], 'individual': [true,false]},
			'groups' => [apiget filters],
			'hosts' => [apiget filters],
			'graphs' => [apiget filters],
			'groupid' => groupid,
			'hostid' => hostid,
			'graphid' => graphid,
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

		
		if(!isset($options['groupid'], $options['hostid'])){
			if(isset($options['graphid'])){
				$this->_updateGHbyGraph($options);
			}
		}

		$profileSection = ($this->config['individual']) ? $page['file'] : $page['menu'];
// groups
		if(isset($options['groups'])){
			if(!isset($options['groupid']) && isset($options['hostid'])){
				$options['groupid'] = 0;
			}
			
			$this->_profileIdx['groups'] = 'web.'.$profileSection.'.groupid';
			$this->_initGroups($options['groupid'], $options['groups']);
		}

// hosts
		if(isset($options['hosts'])){
			$this->_profileIdx['hosts'] = 'web.'.$profileSection.'.hostid';
			$this->_initHosts($options['hostid'], $options['hosts']);
		}

// graphs
		if(isset($options['graphs'])){
			$this->_profileIdx['graphs'] = 'web.'.$profileSection.'.graphid';
			$this->_initGraphs($options['graphid'], $options['graphs']);
		}
	}

	private function _updateGHbyGraph(&$options){
		$graph = CGraph::get(array(
			'graphids' => $options['graphid'],
			'output' => API_OUTPUT_EXTEND,
			'select_hosts' => API_OUTPUT_REFER,
			'select_groups' => API_OUTPUT_REFER,
		));
		$graph = reset($graph);

		$options['groupid'] = $graph ? $graph['groups'][0]['groupid'] : null;
		$options['hostid'] = $graph ? $graph['hosts'][0]['hostid'] : null;
	}

	private function _initGroups($groupid, $options){
		$def_options = array(
			'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
			'output' => API_OUTPUT_EXTEND,
		);
		$options = zbx_array_merge($def_options, $options);
		$groups = CHostGroup::get($options);

		$this->data['groups'] = array();
		foreach($groups as $group){
			$this->data['groups'][$group['groupid']] = $group['name'];
		}

		if(is_null($groupid) && ($this->config['DDRemember'])){
			if($this->config['select_latest']){
				$groupid = CProfile::get(self::GROUP_LATEST_IDX);
			}
			else{
				$groupid = CProfile::get($this->_profileIdx['groups']);
			}
		}

		if(is_null($groupid)){
			$groupid = 0;
		}
		else{
			$groupid = isset($this->data['groups'][$groupid]) ? $groupid : 0;
		}

		CProfile::update($this->_profileIdx['groups'], $groupid, PROFILE_TYPE_ID);
		CProfile::update(self::GROUP_LATEST_IDX, $groupid, PROFILE_TYPE_ID);

		$this->isSelected['groupsSelected'] = (($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL) && !empty($this->data['groups'])) || ($groupid > 0);
		$this->ids['groupid'] = $groupid;
	}

	private function _initHosts($hostid, $options){
		$this->data['hosts'] = array();

		if(!$this->groupsSelected){
			$hostid = 0;
		}
		else{
			$def_ptions = array(
				'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
				'output' => array('hostid', 'host'),
				'groupids' => (($this->groupid > 0) ? $this->groupid : null),
			);
			$options = zbx_array_merge($def_ptions, $options);
			$hosts = CHost::get($options);

			foreach($hosts as $host){
				$this->data['hosts'][$host['hostid']] = $host['host'];
			}

			if(is_null($hostid) && ($this->config['DDRemember'])){
				if($this->config['select_latest']){
					$hostid = CProfile::get(self::HOST_LATEST_IDX);
				}
				else{
					$hostid = CProfile::get($this->_profileIdx['hosts']);
				}
			}

			if(is_null($hostid)){
				$hostid = 0;
			}
			else{
				$hostid = isset($this->data['hosts'][$hostid]) ? $hostid : 0;
			}
		}

		CProfile::update($this->_profileIdx['hosts'], $hostid, PROFILE_TYPE_ID);
		CProfile::update(self::HOST_LATEST_IDX, $hostid, PROFILE_TYPE_ID);

		$this->isSelected['hostsSelected'] = (($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL) && !empty($this->data['hosts']))
			|| ($hostid > 0) ;
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
				'groupids' => ((($this->groupid > 0) && ($this->hostid == 0)) ? $this->groupid : null),
				'hostids' => (($this->hostid > 0) ? $this->hostid : null),
			);
			$options = zbx_array_merge($def_ptions, $options);
			$graphs = CGraph::get($options);

			foreach($graphs as $graph){
				$this->data['graphs'][$graph['graphid']] = $graph['name'];
			}

			if(is_null($graphid) && ($this->config['DDRemember'])){
				if($this->config['select_latest']){
					$graphid = CProfile::get(self::GRAPH_LATEST_IDX);
				}
				else{
					$graphid = CProfile::get($this->_profileIdx['graphs']);
				}
			}
			if(is_null($graphid)){
				$graphid = 0;
			}
			else{
				$graphid = isset($this->data['graphs'][$graphid]) ? $graphid : 0;
			}
		}

		CProfile::update($this->_profileIdx['graphs'], $graphid, PROFILE_TYPE_ID);
		CProfile::update(self::GRAPH_LATEST_IDX, $graphid, PROFILE_TYPE_ID);

		$this->isSelected['graphsSelected'] = $graphid > 0;
		$this->ids['graphid'] = $graphid;
	}

	public function getHostsCB($withNode=false){
		return $this->_getCB('hostid', $this->hostid, $this->hosts, $withNode);
	}

	public function getGroupsCB($withNode=false){
		return $this->_getCB('groupid', $this->groupid, $this->groups, $withNode);
	}

	public function getGraphsCB($withNode=false){
		return $this->_getCB('graphid', $this->graphid, $this->graphs, $withNode);
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
