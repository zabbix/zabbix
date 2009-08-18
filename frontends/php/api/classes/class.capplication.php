<?php
/**
 * File containing CApplication class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Applications
 *
 */
class CApplication {

	public static $error;

	/**
	 * Get Applications data
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['triggerids']
	 * @param array $options['applicationids']
	 * @param boolean $options['status']
	 * @param boolean $options['templated_items']
	 * @param boolean $options['editable']
	 * @param boolean $options['count']
	 * @param string $options['pattern']
	 * @param int $options['limit']
	 * @param string $options['order']
	 * @return array|int item data as array or false if error
	 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('applicationid', 'name'); // allowed columns for sorting

		$sql_parts = array(
			'select' => array('apps' => 'a.applicationid'),
			'from' => array('applications a'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'				=> null,
			'hostids'				=> null,
			'itemids'				=> null,
			'applicationids'		=> null,
			'editable'				=> null,
			'nopermissions'			=> null,
// OutPut
			'extendoutput'			=> null,
			'select_items'			=> null,
			'count'					=> null,
			'pattern'				=> '',
			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);

// editable + PERMISSION CHECK
		if(defined('ZBX_API_REQUEST')){
			$options['nopermissions'] = false;
		}

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['r'] = 'rights r';
			$sql_parts['from']['ug'] = 'users_groups ug';
			$sql_parts['where'][] = 'hg.hostid=a.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS( '.
								' SELECT hgg.groupid '.
								' FROM hosts_groups hgg, rights rr, users_groups gg '.
								' WHERE hgg.hostid=hg.hostid '.
									' AND rr.id=hgg.groupid '.
									' AND rr.groupid=gg.usrgrpid '.
									' AND gg.userid='.$userid.
									' AND rr.permission<'.$permission.')';
		}

// nodeids
		$nodeids = $options['nodeids'] ? $options['nodeids'] : get_current_nodeid(false);

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['hostid'] = 'a.hostid';
			}

			$sql_parts['where'][] = DBcondition('a.hostid', $options['hostids']);
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['itemid'] = 'ia.itemid';
			}
			$sql_parts['from']['ia'] = 'items_applications ia';
			$sql_parts['where'][] = DBcondition('ia.itemid', $options['itemids']);
			$sql_parts['where']['aia'] = 'a.applicationid=ia.applicationid';

		}

// applicationids
		if(!is_null($options['applicationids'])){
			zbx_value2array($options['applicationids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['applicationid'] = 'a.applicationid';
			}
			$sql_parts['where'][] = DBcondition('a.applicationid', $options['applicationids']);

		}

// extendoutput
		if(!is_null($options['extendoutput'])){
			$sql_parts['select']['apps'] = 'a.*';
		}

// count
		if(!is_null($options['count'])){
			$options['select_items'] = 0;
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(a.applicationid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(a.name) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'a.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('a.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('a.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'a.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//----------

		$applicationids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('a.applicationid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($application = DBfetch($res)){
			if($options['count'])
				$result = $application;
			else{
				$applicationids[$application['applicationid']] = $application['applicationid'];

				if(is_null($options['extendoutput'])){
					$result[$application['applicationid']] = $application['applicationid'];
				}
				else{
					if(!isset($result[$application['applicationid']]))
						$result[$application['applicationid']]= array();

					if($options['select_items'] && !isset($result[$application['applicationid']]['itemids'])){
						$result[$application['applicationid']]['itemids'] = array();
						$result[$application['applicationid']]['items'] = array();
					}

					// hostids
					if(isset($application['hostid'])){
						if(!isset($result[$application['applicationid']]['hostids']))
							$result[$application['applicationid']]['hostids'] = array();

						$result[$application['applicationid']]['hostids'][$application['hostid']] = $application['hostid'];
						unset($application['hostid']);
					}
					// itemids
					if(isset($application['itemid'])){
						if(!isset($result[$application['applicationid']]['itemids']))
							$result[$application['applicationid']]['itemids'] = array();

						$result[$application['applicationid']]['itemids'][$application['itemid']] = $application['itemid'];
						unset($application['itemid']);
					}

					$result[$application['applicationid']] += $application;
				}
			}
		}

		if(is_null($options['extendoutput'])) return $result;

// Adding Objects
// Adding items
		if($options['select_items']){
			$obj_params = array('extendoutput' => 1, 'applicationids' => $applicationids, 'nopermissions' => 1);
			$items = CItem::get($obj_params);
			foreach($items as $itemid => $item){
				foreach($item['applicationids'] as $num => $applicationid){
					$result[$applicationid]['itemids'][$itemid] = $itemid;
					$result[$applicationid]['items'][$itemid] = $item;
				}
			}
		}


	return $result;
	}

	/**
	 * Gets all Application data from DB by Application ID
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param int $app_data
	 * @param int $app_data['applicationid']
	 * @return array|boolean application data || false if error
	 */
	public static function getById($app_data){
		$item = get_application_by_applicationid($app_data['applicationid']);
		$result = $item ? true : false;
		if($result)
			return $item;
		else{
			self::$error = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'Application with id: '.$app_data['applicationid'].' doesn\'t exists.');
			return false;
		}
	}

	/**
	 * Get Application ID by host.name and item.key
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param array $app_data
	 * @param array $app_data['name']
	 * @param array $app_data['hostid']
	 * @return int|boolean
	 */
	public static function getId($app_data){

		$sql = 'SELECT applicationid '.
				' FROM applications '.
				' WHERE hostid='.$app_data['hostid'].
					' AND name='.$app_data['name'];
		$appid = DBfetch(DBselect($sql));

		$result = $appid ? true : false;
		if($result)
			return $appid['applicationid'];
		else{
			self::$error = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'Application doesn\'t exists.');
			return false;
		}
	}

	/**
	 * Add Applications
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $applications
	 * @param array $app_data['name']
	 * @param array $app_data['hostid']
	 * @return boolean
	 */
	public static function add($applications){

		$result = false;

		DBstart(false);
		foreach($applications as $application){
			$result = add_application($applications['name'], $applications['hostid']);
			if(!$result) break;
		}
		$result = DBend($result);

		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

	/**
	 * Update Applications
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $applications
	 * @param array $app_data['name']
	 * @param array $app_data['hostid']
	 * @return boolean
	 */
	public static function update($applications){

		$result = false;

		DBstart(false);
		foreach($applications as $application){
			$result = update_application($application['applicationid'], $application['name'], $application['hostid']);
			if(!$result) break;
		}
		$result = DBend($result);

		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

	/**
	 * Delete Applications
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $applicationids
	 * @return boolean
	 */
	public static function delete($applicationids){
		$result = delete_application($applicationids);
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

}
?>
