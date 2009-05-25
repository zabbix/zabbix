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

	/**
	 * Gets all trigger data from DB by triggerid
	 *
	 *	$trigger = array(
	 * + string 'triggerid' => 'triggerid'
	 * );
	 *
	 * @static
	 * @param array $trigger
	 * @return array|boolean trigger data || false if error
	 */
	public static function getById($trigger){
		return get_trigger_by_triggerid($trigger['triggerid']);
	}
	
	/**
	 * Get triggerid by host.host and trigger.expression
	 *
	 * $trigger = array(
	 * + string 'host' => 'hostname',
	 * + string 'hostid' => hostid,
	 * + string 'expression' => 'trigger expression',
	 * + string 'description' => 'trigger description'
	 * );
	 *
	 * @static
	 * @param array $trigger
	 * @return string 
	 */
	public static function getId($trigger){
			
		// if(!isset($trigger['hostid']) && !(isset($trigger['host']))){
			// error('Hostid or Host shuold be given to Ctrigger::getId');
			// return false;
		// }
		$triggerid = false;
		
		$hostid = $trigger['hostid'];
		
		$sql_where = '';
		if(isset($trigger['description'])) {
			$sql_where = ' AND t.description='.zbx_dbstr($trigger['description']);
		}
		
		$sql = 'SELECT DISTINCT t.triggerid, t.expression '.
				' FROM triggers t, functions f, items i, hosts h '.
				' WHERE i.hostid='.$hostid.
					' AND f.itemid=i.itemid '.
					' AND f.triggerid=t.triggerid'.
					' AND i.hostid=h.hostid'.
					' AND h.host='.zbx_dbstr($trigger['host']).
					$sql_where;
		$db_triggers = DBselect($sql);
		
		while($tmp_trigger = DBfetch($db_triggers)) {
			$tmp_exp = explode_exp ($tmp_trigger['expression'], false);
			if(strcmp($tmp_exp, $trigger['expression']) == 0) {
				$triggerid = $tmp_trigger['triggerid'];
				break;
			}
		}
		
		return $triggerid;
	}
		
	/** 
	 * Add triggers
	 *
	 * Input array $triggers has following structure and default values :
	 * ( fields with value * are mandatory )
	 * array( array(
	 * + *'expression'	=> *,
	 * + *'description'	=> *,
	 * + 'type'			=> 0,
	 * + 'priority'		=> 1,
	 * + 'status'		=> TRIGGER_STATUS_ACTIVE,
	 * + 'comments'		=> '',
	 * + 'url'			=> '',
	 * ), ...);
	 *
	 * @static
	 * @param array $triggers multidimensional array with triggers data
	 * @return boolean 
	 */
	public static function add($triggers){
		
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
		}
		
		$result = DBend($result);
		return $result;
	}
	
	/**
	 * Update triggers
	 *
	 * @static
	 * @param array $triggers multidimensional array with triggers data
	 * @return boolean
	 */
	public static function update($triggers){
		
		$result = false;
		
		DBstart(false);
		foreach($triggers as $trigger){
		
			// $sql = 'SELECT DISTINCT * '.
				// ' FROM triggers '.
				// ' WHERE triggerid='.$trigger['triggerid'];

			// $trigger_db_fields = DBfetch(DBselect($sql));
			
			$trigger_db_fields = self::getById($trigger['triggerid']);				
				
			if(!isset($trigger_db_fields)) {
				$result = false;
				break;
			}
			
			if(!check_db_fields($trigger_db_fields, $trigger)){
				error('Incorrect arguments pasted to function [CHost::update]');
				$result = false;
				break;
			}	
			
			$trigger['expression'] = explode_exp($trigger['expression'], false);
			$result = update_trigger($trigger['triggerid'], $trigger['expression'], $trigger['description'], $trigger['type'], 
				$trigger['priority'], $trigger['status'], $trigger['comments'], $trigger['url']);
			if(!$result) break;
		}	
		$result = DBend($result);
		
		return $result;
	}
	
	/**
	 * Delete triggers
	 *
	 * @static
	 * @param array $triggerids 
	 * @return boolean
	 */	
	public static function delete($triggerids){
		return delete_trigger($triggerids);	
	}
	
	/**
	 * Add dependency for trigger
	 *
	 * Add dependency, to make trigger be dependent on other trigger. 
	 *
	 * $triggers_data = array(
	 * + string 'triggerid] => 'triggerid',
	 * + string 'depends_on_triggerid' => 'triggerid'
	 * );
	 *
	 * @static
	 * @param array $triggers_data 
	 * @return boolean
	 */	
	public static function addDependency($triggers_data){
		return insert_dependency($triggers_data['triggerid'], $triggers_data['depends_on_triggerid']);
	}
}
?>