<?php
/**
 * File containing Trigger class for API.
 * @package API
 */
/**
 * Class containing methods for operations with triggers
 *
 */
class CTrigger {

	public static $error = array();

	/**
	 *Gets all trigger data from DB by triggerid
	 * 
	 * <code>
	 * $trigger = array(
	 * 	*string 'triggerid' => 'triggerid'
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $trigger
	 * @return array|boolean array of trigger data || false if error
	 */
	public static function getById($trigger){
		$trigger =  get_trigger_by_triggerid($trigger['triggerid']);
		$result = $trigger ? true : false;
		if($result)
			$result = $trigger;
		else
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			
		return $result;
	}
	
	/**
	 * Get triggerid by host.host and trigger.expression
	 *
	 * <code>
	 * $trigger = array(
	 * 	*string 'expression' => 'trigger expression',
	 * 	string 'host' => 'hostname',
	 * 	string 'hostid' => hostid,
	 * 	string 'description' => 'trigger description'
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $trigger
	 * @return string|boolean triggerid || false if error 
	 */
	public static function getId($trigger){
			
		$result = false;
		
		$sql_where = '';
		$sql_from = '';	
		if(isset($trigger['hostid']) && isset($trigger['host'])) {
			$sql_where .= ''.
					' i.hostid='.$trigger['hostid'].
					' AND f.itemid=i.itemid '.
					' AND f.triggerid=t.triggerid'.
					' AND i.hostid=h.hostid'.
					' AND h.host='.zbx_dbstr($trigger['host']);
			$sql_from .= ', functions f, items i, hosts h ';
		}
		else{
			if(isset($trigger['hostid'])) {
				$sql_where .= ''.
					' i.hostid='.$trigger['hostid'].
					' AND f.itemid=i.itemid '.
					' AND f.triggerid=t.triggerid';
				$sql_from .= ', functions f, items i';
			}
			if(isset($trigger['host'])) {
				$sql_where .= ''.
					' f.itemid=i.itemid '.
					' AND f.triggerid=t.triggerid'.
					' AND i.hostid=h.hostid'.
					' AND h.host='.zbx_dbstr($trigger['host']);
				$sql_from .= ', functions f, items i, hosts h ';
			}
		}
		if(isset($trigger['description'])) {
			$sql_where .= ' AND t.description='.zbx_dbstr($trigger['description']);
		}
		
		$sql = 'SELECT DISTINCT t.triggerid, t.expression '.
				' FROM triggers t'.$sql_from.
				' WHERE '.$sql_where;	
		if($db_triggers = DBselect($sql)){
			$result = true;
			$triggerid = null; 
			
			while($tmp_trigger = DBfetch($db_triggers)) {
				$tmp_exp = explode_exp($tmp_trigger['expression'], false);
				if(strcmp($tmp_exp, $trigger['expression']) == 0) {
					$triggerid = $tmp_trigger['triggerid']; 
					break;
				}
			}
		}
		
		if($result)
			return $triggerid;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
		
	/** 
	 * Add triggers
	 *
	 * Input array $triggers has following structure and default values :
	 * <code>
	 * array( array(
	 * 	*'expression'	=> *,
	 * 	*'description'	=> *,
	 * 	'type'			=> 0,
	 * 	'priority'		=> 1,
	 * 	'status'		=> TRIGGER_STATUS_ACTIVE,
	 * 	'comments'		=> '',
	 * 	'url'			=> '',
	 * ), ...);
	 * </code>
	 *
	 * @static
	 * @param array $triggers multidimensional array with triggers data
	 * @return boolean 
	 */
	public static function add($triggers){
		
		$triggerids = array();
		DBstart(false);
		
		$result = false;
		foreach($triggers as $trigger){
			$trigger_db_fields = array(
				'expression'	=> null,
				'description'	=> null,
				'type'			=> 0,
				'priority'		=> 1,
				'status'		=> TRIGGER_STATUS_DISABLED,
				'comments'		=> '',
				'url'			=> ''
			);

			if(!check_db_fields($trigger_db_fields, $trigger)){
				$result = false;
				break;
			}
			
			$result = add_trigger($trigger['expression'], $trigger['description'], $trigger['type'], $trigger['priority'], 
			$trigger['status'], $trigger['comments'], $trigger['url']);
			if(!$result) break;
			$triggerids[$result] = $result;
		}
		
		$result = DBend($result);
		if($result)
			return $triggerids;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Update triggers
	 *
	 * <code>
	 * array( array(
	 * 	*'triggerid'	=> ,
	 * 	'description'	=> ,
	 * 	'type'			=> 0,
	 * 	'priority'		=> 1,
	 * 	'status'		=> TRIGGER_STATUS_ACTIVE,
	 * 	'comments'		=> '',
	 * 	'url'			=> '',
	 * ), ...);
	 * </code>
	 *
	 * @static
	 * @param array $triggers multidimensional array with triggers data
	 * @return boolean
	 */
	public static function update($triggers){
		
		$result = false;
		$triggerids = array();
		DBstart(false);
		foreach($triggers as $trigger){
			$trigger_db_fields = self::getById($trigger);
			if(!isset($trigger_db_fields)) {
				$result = false;
				break;
			}
			
			if(!check_db_fields($trigger_db_fields, $trigger)){
				error('Incorrect arguments pasted to function [CTrigger::update]');
				$result = false;
				break;
			}	
			
			$trigger['expression'] = explode_exp($trigger['expression'], false);
			$result = update_trigger($trigger['triggerid'], $trigger['expression'], $trigger['description'], $trigger['type'], 
				$trigger['priority'], $trigger['status'], $trigger['comments'], $trigger['url']);
			if(!$result) break;
			$triggerids[$result] = $result;
		}	
		$result = DBend($result);
		
		if($result)
			return $triggerids;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Delete triggers
	 *
	 * @static
	 * @param array $triggerids 
	 * @return boolean
	 */	
	public static function delete($triggerids){
		$result = delete_trigger($triggerids);	
		if($result)
			return $result;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Add dependency for trigger
	 *
	 * Add dependency, to make trigger be dependent on other trigger. 
	 * <code>
	 * $triggers_data = array(
	 * 	string 'triggerid] => 'triggerid',
	 * 	string 'depends_on_triggerid' => 'triggerid'
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $triggers_data 
	 * @return boolean
	 */	
	public static function addDependency($triggers_data){
		$result = insert_dependency($triggers_data['triggerid'], $triggers_data['depends_on_triggerid']);
		if($result)
			return $groupids;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
}
?>