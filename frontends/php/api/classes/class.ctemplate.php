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

	public static $error;

	/**
	 * Get host data 
	 *
	 * @static
	 * @param array $options 
	 * @return array|boolean host data as array or false if error
	 */
	public static function get($options = array()) {

		$def_sql = array(
			'select' => array(),
			'from' => array('hosts h'),
			'where' => array(),
			'order' => array(),
			'limit' => null,
			);

		$def_options = array(
			'nodeid' =>				0,
			'groupids'	 =>			0,
			'hostids' 	=>			0,
			'templateids'	 =>		0,
			'with_items' 	=>		0,
			'with_triggers' =>		0,
			'with_graphs' 	=>		0,
			'count'	=>				0,
			'pattern' =>			'',
			'order' =>				0,
			'limit' =>				0,
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
		if($def_options['groupids'] != 0){
			zbx_value2array($def_options['groupids']);

		$def_sql['from'][] = 'hosts_groups hg';
		$def_sql['where'][] = DBcondition('hg.groupid',$def_options['groupids']);
		$def_sql['where'][] = 'hg.hostid=h.hostid';
		}

// templateids 
		if($def_options['templateids'] != 0){
			zbx_value2array($def_options['templateids']);

			$def_sql['where'][] = DBcondition('h.hostid',$def_options['templateids']);
		}
		
		if(!zbx_empty($def_options['pattern'])){
			$def_sql['where'][] = ' UPPER(h.host) LIKE '.zbx_dbstr('%'.strtoupper($def_options['pattern']).'%');
		}

// hosts 
		$def_sql['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
		
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

// count
		if($def_options['count']){
			$def_sql['select'][] = 'COUNT(h.hostid) as rowscount';
		}
		else{
			$def_sql['select'][] = 'h.hostid';
			$def_sql['select'][] = 'h.host';
		}
// order
		if(str_in_array($def_options['order'], array('host','hostid'))){
			$def_sql['order'][] = 'h.'.$def_options['order'];
		}
		
// limit
		if(zbx_ctype_digit($def_options['limit'])){
			$def_sql['limit'] = $def_options['limit'];
		}
//------

		$def_sql['select'] = array_unique($def_sql['select']);
		$def_sql['from'] = array_unique($def_sql['from']);
		$def_sql['where'] = array_unique($def_sql['where']);
		$def_sql['order'] = array_unique($def_sql['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		$sql_limit = null;
		if(!empty($def_sql['select'])) $sql_select.= implode(',',$def_sql['select']);
		if(!empty($def_sql['from'])) $sql_from.= implode(',',$def_sql['from']);
		if(!empty($def_sql['where'])) $sql_where.= ' AND '.implode(' AND ',$def_sql['where']);
		if(!empty($def_sql['order'])) $sql_order.= ' ORDER BY '.implode(',',$def_sql['order']);
		if(!empty($def_sql['limit'])) $sql_limit = $def_sql['limit'];

		$sql = 'SELECT DISTINCT '.$sql_select.
			' FROM '.$sql_from.
			' WHERE '.DBin_node('h.hostid', $nodeid).
				$sql_where.
			$sql_order; 
		$res = DBselect($sql, $sql_limit);
		while($host = DBfetch($res)){
			if($def_options['count']) 
				$result = $host;
			else 
				$result[$host['hostid']] = $host;
		}
		
		return $result;
	}
	
	/**
	 * Gets all template data from DB by templateid
	 *
	 * @static
	 * @param array $template_data 
	 * @return array|boolean template data as array or false if error
	 */
	public static function getById($template_data){
		$sql = 'SELECT * FROM hosts WHERE hostid='.$template_data['templateid'].' AND status=3';
		$template = DBfetch(DBselect($sql));
		
		$result = $template ? true : false;
		if($result)
			return $template;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Get templateid by template name
	 *
	 * <code>
	 * $template_data = array(
	 * 	string 'template' => 'template name'
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $template_data
	 * @return int templateid
	 */
	public static function getId($template_data){
		$sql = 'SELECT hostid FROM hosts '.
			' WHERE host='.zbx_dbstr($template_data['name']).
				' AND status=3 '.
				' AND '.DBin_node('hostid', get_current_nodeid(false));
		$templateid = DBfetch(DBselect($sql));
		
		$result = $templateid ? true : false;
		if($result)
			return $templateid['hostid'];
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
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
		
		$templateids = array();
		
		$result = false;
		
		DBstart(false);
		foreach($templates as $template){
		
			$host_db_fields = array(
				'host' => null,
				'port' => 0,
				'status' => 0,
				'useip' => 0,
				'dns' => '',
				'ip' => '0.0.0.0',
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
			$templateids[$result] = $result;
		}
		$result = DBend($result);
		
		if($result)
			return $templateids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return $result;	
		}
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
		
		$templateids = array();
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
			$templateids[$result] = $result;
		}	
		$result = DBend($result);
		
		if($result)
			return $templateids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}

	}
	
	/**
	 * Delete template
	 *
	 * @static
	 * @param array $templateids 
	 * @return boolean
	 */	
	public static function delete($templateids){
		$result = delete_host($templateids, false);
		if($result)
			return $templateids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	
	/**
	 * Link template to hosts
	 *
	 * @static
	 * @param int $templateid 
	 * @param array $hostids 
	 * @return boolean
	 */	
	public static function linkHosts($data){		
		$result = false;
		$error = '';
		
		$templateid = $data['templateid'];
		$hostids = $data['hostids'];
		DBstart(false);
		
		foreach($hostids as $hostid){
			$hosttemplateid = get_dbid('hosts_templates', 'hosttemplateid');
			if(!$result = DBexecute('INSERT INTO hosts_templates VALUES ('.$hosttemplateid.','.$hostid.','.$templateid.')')){
				$error = 'DBexecute';
				break;
			}
		}
		
		if($result) {
			foreach($hostids as $hostid){
				$result = sync_host_with_templates($hostid, $templateid);
				if(!$result) {
					$error = 'sync_host_with_templates';
					break;
				}
			}
		}
		$result = DBend($result);
		
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
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
	public static function unlinkHosts($data){	
		$templateid = $data['templateid'];
		$hostids = $data['hostids'];
		$clean = isset($data['clean']);
		
		foreach($hostids as $hostid) {
			$result = delete_template_elements($hostid, array($templateid), $clean);
		}
		$result&= DBexecute('DELETE FROM hosts_templates WHERE templateid='.$templateid.' AND '.DBcondition('hostid',$hostids));
		
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
	}
	
	/**
	 * Link host to templates 
	 *
	 * @static
	 * @param string $hostid 
	 * @param array $templateids 
	 * @return boolean
	 */	
	public static function linkTemplates($data){
		$result = false;
		
		$hostid = $data['hostid'];
		$templateids = $data['templateids'];
		
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
		
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
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
	public static function unlinkTemplates($data){
		$templateid = $data['templateid'];
		$hostids = $data['hostids'];
		$clean = isset($data['clean']);
		
		$result = delete_template_elements($hostid, $templateids, $clean);
		$result&= DBexecute('DELETE FROM hosts_templates WHERE hostid='.$hostid.' AND '.DBcondition('templateid',$templateids));
		
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
	}

}
?>