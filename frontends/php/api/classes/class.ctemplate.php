<?php
/**
 * File containing template class for API.
 * @package API
 */
/**
 * Class containing methods for operations with templates
 *
 */
class CTemplate {

	/**
	 * Get host data 
	 *
	 * @static
	 * @param array $options 
	 * @return array|boolean host data as array or false if error
	 */
	public static function get($options = array()) {

		$def_sql = array(
			'select' => array('h.hostid','h.host'),
			'from' => array('hosts h'),
			'where' => array('status=3'),
			'order' => array(),
			);

		$def_options = array(
			'nodeid' =>      0,
			'groupids' =>     0,
			'hostids' =>     0,
			'templateids' =>     0,
			'with_items' =>     0,
			'with_triggers' =>    0,
			'with_graphs'=>     0,
		);

		$def_options = array_merge($def_options, $options);

		$result = array();
//-----
// nodes
		if($def_options['nodeid']){
			$nodeid = $def_options['nodeid'];
		}
		else{
			$nodeid = get_current_nodeid(false);
		}

// groups
		if($def_options['groupids']){
			zbx_value2array($def_options['groupids']);

		$def_sql['from'][] = 'hosts_groups hg';
		$def_sql['where'][] = DBcondition('hg.groupid',$def_options['groupids']);
		$def_sql['where'][] = 'hg.hostid=h.hostid';
		}

// templateids 
		if($def_options['templateids']){
			zbx_value2array($def_options['templateids']);

			$def_sql['where'][] = DBcondition('h.hostid',$def_options['templateids']);
		}

// hosts 
		if($def_options['hostids']){
			zbx_value2array($def_options['hostids']);

		$def_sql['from'][] = 'hosts_templates ht';
		$def_sql['where'][] = DBcondition('ht.hostids',$def_options['hostids']);
		$def_sql['where'][] = 'h.hostid=ht.hostid';
		}

// items
		if($def_options['with_items']){
			$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid )';
		}  

// triggers
		if($def_options['with_triggers']){
			$def_sql['where'][] = 'EXISTS( SELECT i.itemid '.
				 ' FROM items i, functions f, triggers t'.
				 ' WHERE i.hostid=h.hostid '.
				  ' AND i.itemid=f.itemid '.
				  ' AND f.triggerid=t.triggerid)';
		} 
		

// graphs
		if($def_options['with_graphs']){
			$def_sql['where'][] = 'EXISTS( SELECT DISTINCT i.itemid '.
				 ' FROM items i, graphs_items gi '.
				 ' WHERE i.hostid=h.hostid '.
				  ' AND i.itemid=gi.itemid)';
		}
//------
		$def_sql['order'][] = 'h.host';

		$def_sql['select'] = array_unique($def_sql['select']);
		$def_sql['from'] = array_unique($def_sql['from']);
		$def_sql['where'] = array_unique($def_sql['where']);
		$def_sql['order'] = array_unique($def_sql['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($def_sql['select'])) $sql_select.= implode(',',$def_sql['select']);
		if(!empty($def_sql['from'])) $sql_from.= implode(',',$def_sql['from']);
		if(!empty($def_sql['where'])) $sql_where.= ' AND '.implode(' AND ',$def_sql['where']);
		if(!empty($def_sql['order'])) $sql_order.= implode(',',$def_sql['order']);

		$sql = 'SELECT DISTINCT '.$sql_select.
			' FROM '.$sql_from.
			' WHERE '.DBin_node('h.hostid', $nodeid).
		$sql_where.
			' ORDER BY '.$sql_order; 
		$res = DBselect($sql);
		while($host = DBfetch($res)){
			$result[$host['hostid']] = $host;
		}
		return $result;
	}
	
	/**
	 * Gets all template data from DB by templateid
	 *
	 * @static
	 * @param int $templateid 
	 * @return array|boolean template data as array or false if error
	 */
	public static function getById($templateid){
		$sql = 'SELECT * FROM hosts WHERE hostid='.$templateid.' AND status=3';
		$template = DBfetch(DBselect($sql));
		
		return $template ? $template : false;
	}
	
	/**
	 * Get templateid by template name
	 *
	 * $template_data = array(
	 * + string 'template' => 'template name'
	 * );
	 *
	 * @static
	 * @param array $template_data
	 * @return int templateid
	 */
	public static function getId($template_data){
		$sql = 'SELECT hostid FROM hosts '.
			' WHERE host='.zbx_dbstr($template_data['template']).
				' AND status=3 '.
				' AND '.DBin_node('hostid', get_current_nodeid(false));
		$templateid = DBfetch(DBselect($sql));
		
		return $templateid ? $templateid['hostid'] : false;	
	}
	
	/**
	 * Add template
	 *
	 * @static
	 * @param array $templates multidimensional array with templates data
	 * @return boolean 
	 */
	public static function add($templates){
		$tpls = null;
		$newgroup = ''; 
		$groups = null;
		$status = 3;
		
		$result = false;
		
		DBstart(false);
		foreach($templates as $template){
		
			$host_db_fields = array(
				'host' => null,
				'useip' => 0,
				'dns' => '',
				'proxy_hostid' => 0,
				'useipmi' => 0,
				'ipmi_ip' => '',
				'ipmi_port' => 623,
				'ipmi_authtype' => 0,
				'ipmi_privilege' => 0,
				'ipmi_username' => '',
				'ipmi_password' => '',
			);

			if(!check_db_fields($host_db_fields, $template)){
				$result = false;
				break;
			}
			
			$result = add_host($template['host'], $template['port'], $status, $template['useip'], $template['dns'], $template['ip'], 
				$template['proxy_hostid'], $tpls, $template['useipmi'], $template['ipmi_ip'], $template['ipmi_port'], $template['ipmi_authtype'], 
				$template['ipmi_privilege'], $template['ipmi_username'], $template['ipmi_password'], $newgroup, $groups);
			if(!$result) break;
		}
		$result = DBend($result);
		
		return $result;
	}
	
	/**
	 * Update template
	 *
	 * @static
	 * @param array $templates multidimensional array with templates data
	 * @return boolean
	 */
	public static function update($templates){
		$tpls = null;
		$newgroup = ''; 
		$groups = null;
		$status = 3;
		
		$result = false;
		
		DBstart(false);
		foreach($templates as $template){
		
			$sql = 'SELECT DISTINCT * '.
				' FROM hosts '.
				' WHERE hostid='.$template['hostid'];

			$host_db_fields = DBfetch(DBselect($sql));
			
			if(!isset($host_db_fields)) {
				$result = false;
				break;
			}
			
			if(!check_db_fields($host_db_fields, $template)){
				error('Incorrect arguments pasted to function [CTemplate::update]');
				$result = false;
				break;
			}
			
			$result = update_host($template['hostid'], $template['host'], $template['port'], $status, $template['useip'], $template['dns'], $template['ip'], 
				$template['proxy_hostid'], $tpls, $template['useipmi'], $template['ipmi_ip'], $template['ipmi_port'], $template['ipmi_authtype'], 
				$template['ipmi_privilege'], $template['ipmi_username'], $template['ipmi_password'], $newgroup, $groups);
			if(!$result) break;
		}	
		$result = DBend($result);
		
		return $result;
	}
	
	/**
	 * Delete template
	 *
	 * @static
	 * @param array $templateids 
	 * @return boolean
	 */	
	public static function delete($templateids){
		return delete_host($templateids, false);	
	}
	
	/**
	 * Add groups to existing template groups
	 *
	 * @static
	 * @param int $templateid
	 * @param array $groupids
	 * @return boolean
	 */	
	public static function addGroups($templateid, $groupids){	
		$result = false;
		
		DBstart(false);	
		foreach($groupids as $key => $groupid) {
			$hostgroupid = get_dbid("hosts_groups","hostgroupid");
			$result = DBexecute("insert into hosts_groups (hostgroupid,hostid,groupid) values ($hostgroupid, $templateid, $groupid)");
			if(!$result)
				return $result;
		}	
		$result = DBend($result);
		
		return $result;
	}
	
	/**
	 * Update existing host groups with new one (rewrite) //result ne boolean
	 *
	 * @static
	 * @param int $templateid 
	 * @param array $groupids 
	 * @return boolean
	 */	
	public static function updateGroups($templateid, $groupids){
		return update_host_groups($templateid, $groupids);
	}
	
	/**
	 * Link template to hosts
	 *
	 * @static
	 * @param int $templateid 
	 * @param array $hostids 
	 * @return boolean
	 */	
	public static function linkHosts($templateid, $hostids){		
		$result = false;
		
		DBstart(false);
		
		foreach($hostids as $hostid){
			$hosttemplateid = get_dbid('hosts_templates', 'hosttemplateid');
			if(!$result = DBexecute('INSERT INTO hosts_templates VALUES ('.$hosttemplateid.','.$hostid.','.$templateid.')'))
				break;
		}
		
		if($result) {
			foreach($hostids as $hostid){
				$result = sync_host_with_templates($hostid, $templateid);
				if(!$result) break;
			}
		}
		$result = DBend($result);
		
		return $result;
	}

	/**
	 * Unlink hosts from templates
	 *
	 * @static
	 * @param int $templateid 
	 * @param array $hostids 
	 * @param boolean $clean default = true; whether to wipe all info from template elements.
	 * @return boolean
	 */
	public static function unlinkHosts($templateid, $hostids, $clean = true){	
		foreach($hostids as $hostid) {
			$result = delete_template_elements($hostid, array($templateid), $clean);
		}
		$result&= DBexecute('DELETE FROM hosts_templates WHERE templateid='.$templateid.' AND '.DBcondition('hostid',$hostids));
		return $result;
	}
	
	/**
	 * Link host to templates 
	 *
	 * @static
	 * @param string $hostid 
	 * @param array $templateids 
	 * @return boolean
	 */	
	public static function linkTemplates($hostid, $templateids){
		$result = false;
		
		DBstart(false);
		
		foreach($templateids as $templateid){
			$hosttemplateid = get_dbid('hosts_templates', 'hosttemplateid');
			if(!$result = DBexecute('INSERT INTO hosts_templates VALUES ('.$hosttemplateid.','.$hostid.','.$templateid.')'))
				break;
		}
		
		if($result) {
			foreach($templateids as $templateid){
				$result = sync_host_with_templates($hostid, $templateid);
				if(!$result) break;
			}
		}
		$result = DBend($result);
		
		return $result;
	}
	
	/**
	 * Unlink templates from host
	 *
	 * @static
	 * @param string $hostid 
	 * @param array $templateids 
	 * @param boolean $clean whether to wipe all info from template elements.
	 * @return boolean
	 */
	public static function unlinkTemplates($hostid, $templateids, $clean = true){
		$result = delete_template_elements($hostid, $templateids, $clean);
		$result&= DBexecute('DELETE FROM hosts_templates WHERE hostid='.$hostid.' AND '.DBcondition('templateid',$templateids));
		return $result;
	}

}
?>