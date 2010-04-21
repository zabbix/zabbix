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
			'global' => [ allow_all, deny_all, select_latest ],
			'groups' => [apiget filters],
			'hosts' => [apiget filters],
			'graphs' => [apiget filters],
			'groupid' => groupid,
			'hostid' => hostid,
			'graphid' => graphid,
		*/

		$this->config['all_nodes'] = $ZBX_WITH_ALL_NODES;
		$this->config['select_latest'] = isset($options['global']['select_latest']);

		$config = select_config();
		$this->config['DDRemember'] = $config['dropdown_first_remember'];

		if(isset($options['global']['allow_all'])){
			$this->config['DDFirst'] = ZBX_DROPDOWN_FIRST_ALL;
		}
		else if(isset($options['global']['deny_all'])){
			$this->config['DDFirst'] = ZBX_DROPDOWN_FIRST_NONE;
		}
		else{
			$this->config['DDFirst'] = $config['dropdown_first_entry'];
		}


		if(!isset($options['groupid'], $options['hostid'])){
			if(isset($options['graphid'])){
				$this->_updateGHbyGraph($options);
			}
			else if(isset($options['itemid'])){
//				$this->updateGHbyItem();
			}
		}


		if(isset($options['groups'])){
			$this->_profileIdx['groups'] = 'web.'.$page['menu'].'.groupid';
			$this->_initGroups($options['groupid'], $options['groups']);
		}
		if(isset($options['hosts'])){
			$this->_profileIdx['hosts'] = 'web.'.$page['menu'].'.hostid';
			$this->_initHosts($options['hostid'], $options['hosts']);
		}
		if(isset($options['graphs'])){
			$this->_profileIdx['graphs'] = 'web.'.$page['file'].'.graphid';
			$this->_initGraphs($options['graphid'], $options['graphs']);
		}

		// nodes
//		if(ZBX_DISTRIBUTED){
//			$def_sql['select'][] = 'n.name as node_name';
//			$def_sql['from'][] = 'nodes n';
//			$def_sql['where'][] = 'n.nodeid='.DBid2nodeid('g.groupid');
//			$def_sql['order'][] = 'node_name';
//		}

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

		$def_ptions = array(
			'nodeids' => $this->config['all_nodes'] ? get_current_nodeid() : null,
			'output' => API_OUTPUT_EXTEND,
		);
		$options = zbx_array_merge($def_ptions, $options);
		$groups = CHostGroup::get($options);
		order_result($groups, 'name');

		$this->data['groups'] = array();
		foreach($groups as $group){
			$this->data['groups'][$group['groupid']] = $group['name'];
		}

		if(is_null($groupid) && ($this->config['DDRemember'])){
			if(isset($this->config['select_latest'])){
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

		$this->isSelected['groupsSelected'] = (($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_ALL) && !empty($this->data['groups']))
			|| ($groupid > 0);
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
			order_result($hosts, 'host');

			foreach($hosts as $host){
				$this->data['hosts'][$host['hostid']] = $host['host'];
			}

			if(is_null($hostid) && ($this->config['DDRemember'])){
				if(isset($this->config['select_latest'])){
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
			order_result($graphs, 'name');

			foreach($graphs as $graph){
				$this->data['graphs'][$graph['graphid']] = $graph['name'];
			}

			if(is_null($graphid) && ($this->config['DDRemember'])){
				if(isset($this->config['select_latest'])){
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
		$cmb = new CComboBox('hostid', $this->hostid,'javascript: submit();');

		$hosts = $this->hosts;
		if($withNode){
			foreach($hosts as $hostid => $host){
				$hosts[$hostid] = get_node_name_by_elid($hostid, null, ': ') . $host;
			}
		}
		order_result($hosts, 'host');

		array_unshift($hosts, array(0 => ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_NONE) ? S_NOT_SELECTED_SMALL : S_ALL_SMALL));
	

		foreach($hosts as $hostid => $name){
			$cmb->addItem($hostid, $name);
		}

		return $cmb;
	}

	public function getGroupsCB($withNode=false){
		$cmb = new CComboBox('groupid', $this->groupid,'javascript: submit();');

		$groups = array(0 => ($this->config['DDFirst'] == ZBX_DROPDOWN_FIRST_NONE) ? S_NOT_SELECTED_SMALL : S_ALL_SMALL);
		$groups += $this->groups;

		foreach($groups as $groupid => $name){
			$cmb->addItem($groupid, ($withNode ? get_node_name_by_elid($groupid, null, ': ') : '').$name);
		}

		return $cmb;
	}

	public function getGraphsCB($withNode=false){
		$cmb = new CComboBox('graphid', $this->graphid,'javascript: submit();');

		$graphs = array(0 => S_SELECT_GRAPH_DOT_DOT_DOT);
		$graphs += $this->graphs;

		foreach($graphs as $graphid => $name){
			$cmb->addItem($graphid, ($withNode ? get_node_name_by_elid($graphid, null, ': ') : '').$name);
		}

		return $cmb;
	}

}

?>
