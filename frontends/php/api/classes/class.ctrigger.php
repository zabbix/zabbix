<?php
/**
 * File containing CTrigger class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Triggers
 *
 */
class CTrigger {

	public static $error = array();

	/**
	 * Get Triggers data
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['triggerids']
	 * @param array $options['applicationids']
	 * @param array $options['status']
	 * @param array $options['templated_items']
	 * @param array $options['editable']
	 * @param array $options['count']
	 * @param array $options['pattern']
	 * @param array $options['limit']
	 * @param array $options['order']
	 * @return array|int item data as array or false if error
	 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];
		
		$sort_columns = array('triggerid'); // allowed columns for sorting

		
		$sql_parts = array(
			'select' => array('t.triggerid, t.description, t.status'),
			'from' => array('triggers t'),
			'where' => array(),
			'order' => array(),
			'limit' => null,
			);

		$def_options = array(
			'triggerids'		=> array(),
			'itemids'		=> array(),
			'hostids'		=> array(),
			'groupids'		=> array(),
			'applicationids'	=> array(),
			'status'		=> false,
			'severity'		=> false,
			'templated_triggers'	=> false,
			'editable'		=> false,
			'count'			=> false,
			'pattern'		=> '',
			'limit'			=> null,
			'order'			=> ''
		);

		$options = array_merge($def_options, $options);

		
// editable + PERMISSION CHECK
		if(USER_TYPE_SUPER_ADMIN != $user_type){
			if($options['editable']){
				$permission = PERM_READ_WRITE;
			}
			else{
				$permission = PERM_READ_ONLY;
			}
			   
			$sql_parts['from']['fi'] = 'functions f, items i';
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from'][] = 'rights r, users_groups g';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = '
					r.id=hg.groupid
					AND r.groupid=g.usrgrpid
					AND g.userid='.$userid.'
					AND r.permission>'.($permission-1).'
					AND NOT EXISTS(
						SELECT ff.triggerid
						FROM functions ff, items ii
						WHERE ff.triggerid=t.triggerid
							AND ff.itemid=ii.itemid
							AND EXISTS(
								SELECT hgg.groupid
								FROM hosts_groups hgg, rights rr, users_groups gg
								WHERE hgg.hostid=hg.hostid
									AND rr.id=hgg.groupid
									AND rr.groupid=gg.usrgrpid
									AND gg.userid='.$userid.'
									AND rr.permission<'.$permission.'))';
		}
// count
		if($options['count']){
			$sql_parts['select'] = array('count(t.triggerid) as rowscount');
		}
// triggerids
		if($options['triggerids']){
			$sql_parts['where'][] = DBcondition('t.triggerid', $options['triggerids']);
		}
// itemids
		if($options['itemids']){
			$sql_parts['from']['fi'] = 'functions f, items i';
			$sql_parts['where'][] = DBcondition('i.itemid', $options['itemids']);
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';

		}
// hostids
		if($options['hostids']){
			$sql_parts['from']['fi'] = 'functions f, items i';
			$sql_parts['where'][] = DBcondition('i.hostid', $options['hostids']);
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
		}
// groupids
		if($options['groupids']){
			$sql_parts['from']['fi'] = 'functions f, items i';
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
		}
// applicationids
		if($options['applicationids']){
			$sql_parts['from']['fi'] = 'functions f, items i';
			$sql_parts['from'][] = 'applications a';
			$sql_parts['where'][] = DBcondition('a.applicationid', $options['applicationids']);
			$sql_parts['where'][] = 'i.hostid=a.hostid';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
		}
// status
		if($options['status'] !== false){
			$sql_parts['where'][] = 't.status='.$options['status'];
		}
// severity
		if($options['severity'] !== false){
			$sql_parts['where'][] = 't.priority='.$options['severity'];
		}
// templated_triggers
		if($options['templated_triggers']){
			$sql_parts['where'][] = 't.templateid<>0';
		}
// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' t.description LIKE '.zbx_dbstr('%'.$options['pattern'].'%');
		}
// order
		// restrict not allowed columns for sorting
		$options['order'] = in_array($options['order'], $sort_columns) ? $options['order'] : '';
		
		if(!zbx_empty($options['order'])){
			$sql_parts['order'][] = 't.'.$options['order'];
		}
// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}

		$sql_select = implode(',', $sql_parts['select']);
		$sql_from = implode(',', $sql_parts['from']);
		$sql_where = implode(' AND ', $sql_parts['where']);
		$sql_order = zbx_empty($options['order']) ? '' : ' ORDER BY '.implode(',', $sql_parts['order']);
		$sql_limit = $sql_parts['limit'];


		$sql = 'SELECT DISTINCT '.$sql_select.'
				FROM '.$sql_from.
				($sql_where ? ' WHERE '.$sql_where : '').
				$sql_order;
		$db_res = DBselect($sql, $sql_limit);

		while($trigger = DBfetch($db_res)){
			if($options['count'])
				$result = $trigger;
			else
				$result[$trigger['triggerid']] = $trigger;
		}
	return $result;
	}

	/**
	 * Gets all trigger data from DB by Trigger ID
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $trigger
	 * @param array $trigger['triggerid']
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $trigger
	 * @param array $trigger['expression']
	 * @param array $trigger['host']
	 * @param array $trigger['hostid']
	 * @param array $trigger['description']
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $triggers multidimensional array with triggers data
	 * @param array $triggers[0,...]['expression']
	 * @param array $triggers[0,...]['description']
	 * @param array $triggers[0,...]['type'] OPTIONAL
	 * @param array $triggers[0,...]['priority'] OPTIONAL
	 * @param array $triggers[0,...]['status'] OPTIONAL
	 * @param array $triggers[0,...]['comments'] OPTIONAL
	 * @param array $triggers[0,...]['url'] OPTIONAL
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
				'type'		=> 0,
				'priority'	=> 1,
				'status'	=> TRIGGER_STATUS_DISABLED,
				'comments'	=> '',
				'url'		=> ''
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $triggers multidimensional array with triggers data
	 * @param array $triggers[0,...]['expression']
	 * @param array $triggers[0,...]['description'] OPTIONAL
	 * @param array $triggers[0,...]['type'] OPTIONAL
	 * @param array $triggers[0,...]['priority'] OPTIONAL
	 * @param array $triggers[0,...]['status'] OPTIONAL
	 * @param array $triggers[0,...]['comments'] OPTIONAL
	 * @param array $triggers[0,...]['url'] OPTIONAL
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $triggerids
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $triggers_data
	 * @param array $triggers_data['triggerid]
	 * @param array $triggers_data['depends_on_triggerid']
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
