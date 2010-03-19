<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/**
 * File containing CApplication class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Applications
 *
 */
class CApplication extends CZBXAPI{
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
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('apps' => 'a.applicationid'),
			'from' => array('applications a'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'hostids'				=> null,
			'itemids'				=> null,
			'applicationids'		=> null,
			'templated'				=> null,
			'editable'				=> null,
			'inherited' 			=> null,
			'nopermissions'			=> null,
// Filter
			'filter'				=> null,
			'pattern'				=> '',

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'extendoutput'			=> null,
			'expand_data'			=> null,
			'select_hosts'			=> null,
			'select_items'			=> null,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_items'])){
				$options['select_items'] = API_OUTPUT_EXTEND;
			}
		}


// editable + PERMISSION CHECK

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
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid(false);
// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['ahg'] = 'a.hostid=hg.hostid';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['hg'] = 'hg.groupid';
			}
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_EXTEND){
				$sql_parts['select']['hostid'] = 'a.hostid';
			}

			$sql_parts['where']['hostid'] = DBcondition('a.hostid', $options['hostids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['hostid'] = 'a.hostid';
			}
		}

// expand_data
		if(!is_null($options['expand_data'])){
			$sql_parts['select']['host'] = 'h.host';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['ah'] = 'a.hostid=h.hostid';
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['itemid'] = 'ia.itemid';
			}
			$sql_parts['from']['ia'] = 'items_applications ia';
			$sql_parts['where'][] = DBcondition('ia.itemid', $options['itemids']);
			$sql_parts['where']['aia'] = 'a.applicationid=ia.applicationid';

		}

// applicationids
		if(!is_null($options['applicationids'])){
			zbx_value2array($options['applicationids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['applicationid'] = 'a.applicationid';
			}
			$sql_parts['where'][] = DBcondition('a.applicationid', $options['applicationids']);

		}

// templated
		if(!is_null($options['templated'])){
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['ah'] = 'a.hostid=h.hostid';

			if($options['templated'])
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			else
				$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
		}
		
// inherited
		if(!is_null($options['inherited'])){
			if($options['inherited']){
				$sql_parts['where'][] = 'a.templateid<>0';
			}
			else{
				$sql_parts['where'][] = 'a.templateid=0';
			}
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['apps'] = 'a.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(a.applicationid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where']['name'] = ' UPPER(a.name) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
		}

// filter
		if(!is_null($options['filter'])){
			zbx_value2array($options['filter']);

			if(isset($options['filter']['name']))
				$sql_parts['where']['name'] = 'a.name='.zbx_dbstr($options['filter']['name']);

			if(isset($options['filter']['hostid']))
				$sql_parts['where']['hostid'] = 'a.hostid='.$options['filter']['hostid'];
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
		$sql_parts['group'] = array_unique($sql_parts['group']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_group = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['group']))		$sql_where.= ' GROUP BY '.implode(',',$sql_parts['group']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT DISTINCT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('a.applicationid', $nodeids).
					$sql_where.
				$sql_group.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($application = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $application;
				else
					$result = $application['rowscount'];
			}
			else{
				$applicationids[$application['applicationid']] = $application['applicationid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$application['applicationid']] = array('applicationid' => $application['applicationid']);
				}
				else{
					if(!isset($result[$application['applicationid']]))
						$result[$application['applicationid']]= array();


					if(!is_null($options['select_hosts']) && !isset($result[$application['applicationid']]['hosts'])){
						$result[$application['applicationid']]['hosts'] = array();
					}

					if(!is_null($options['select_items']) && !isset($result[$application['applicationid']]['items'])){
						$result[$application['applicationid']]['items'] = array();
					}

// hostids
					if(isset($application['hostid']) && is_null($options['select_hosts'])){
						if(!isset($result[$application['applicationid']]['hosts']))
							$result[$application['applicationid']]['hosts'] = array();

						$result[$application['applicationid']]['hosts'][] = array('hostid' => $application['hostid']);
						unset($application['hostid']);
					}

// itemids
					if(isset($application['itemid']) && is_null($options['select_items'])){
						if(!isset($result[$application['applicationid']]['items']))
							$result[$application['applicationid']]['items'] = array();

						$result[$application['applicationid']]['items'][] = array('itemid' => $application['itemid']);
						unset($application['itemid']);
					}

					$result[$application['applicationid']] += $application;
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding Hosts
		if(!is_null($options['select_hosts']) && str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_hosts'],
				'applicationids' => $applicationids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$hosts = CHost::get($obj_params);
			foreach($hosts as $hostid => $host){
				$iapplications = $host['applications'];
				unset($host['applications']);
				foreach($iapplications as $num => $application){
					$result[$application['applicationid']]['hosts'][] = $host;
				}
			}
		}

// Adding Objects
// Adding items
		if(!is_null($options['select_items']) && str_in_array($options['select_items'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_items'],
				'applicationids' => $applicationids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$items = CItem::get($obj_params);
			foreach($items as $itemid => $item){
				$iapplications = $item['applications'];
				unset($item['applications']);
				foreach($iapplications as $num => $application){
					$result[$application['applicationid']]['items'][] = $item;
				}
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
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
	public static function getObjects($applicationData){
		$options = array(
			'filter' => $applicationData,
			'output'=>API_OUTPUT_EXTEND
		);

		if(isset($applicationData['node']))
			$options['nodeids'] = getNodeIdByNodeName($applicationData['node']);
		else if(isset($applicationData['nodeids']))
			$options['nodeids'] = $applicationData['nodeids'];

		$result = self::get($options);

	return $result;
	}

	public static function exists($object){
		$keyFields = array(array('hostid', 'host'), 'name');

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);
		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = self::get($options);

	return !empty($objs);
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
	public static function create($applications){
		$applications = zbx_toArray($applications);
		$applicationids = array();

		$result = false;

		self::BeginTransaction(__METHOD__);
		foreach($applications as $anum => $application){
			$result = add_application($application['name'], $application['hostid']);

			if(!$result) break;
			$applicationids[] = $result;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('applicationids'=>$applicationids);
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
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
		$applications = zbx_toArray($applications);
		$applicationids = array();

		$options = array(
			'applicationids'=>zbx_objectValues($applications, 'applicationid'),
			'editable'=>1,
			'extendoutput'=>1,
			'preservekeys'=>1
		);
		$upd_applications = self::get($options);
		foreach($applications as $anum => $application){
			if(!isset($upd_applications[$application['applicationid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}
			$applicationids[] = $application['applicationid'];
		}

		$result = false;

		self::BeginTransaction(__METHOD__);
		foreach($applications as $anum => $application){
			$application_db_fields = $upd_applications[$application['applicationid']];

			if(!check_db_fields($application_db_fields, $application)){
				error('Incorrect arguments pasted to function [CApplication::update]');
				$result = false;
				break;
			}

			$result = update_application($application['applicationid'], $application['name'], $application['hostid']);

			if(!$result) break;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('applicationids'=>$applicationids);
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
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
 * @param _array $applications
 * @param array $applications[0,...]['applicationid']
 * @return boolean
 */
	public static function delete($applications){
		$applications = zbx_toArray($applications);
		$applicationids = array();

		$options = array(
			'applicationids'=>zbx_objectValues($applications, 'applicationid'),
			'editable'=>1,
			'extendoutput'=>1,
			'preservekeys'=>1
		);
		$del_applications = self::get($options);
		foreach($applications as $anum => $application){
			if(!isset($del_applications[$application['applicationid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}

			$applicationids[] = $application['applicationid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_APPLICATION, 'application ['.$application['name'].']');
		}

		if(!empty($applicationids)){
			$result = delete_application($applicationids);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ applicationids ]');
			$result = false;
		}

		if($result){
			return array('applicationids'=>$applicationids);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}


/**
 * Add Items to applications
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param array $data['applications']
 * @param array $data['items']
 * @return boolean
 */
	public static function addItems($data){

		$result = true;
		$errors = array();

		$applications = zbx_toArray($data['applications']);
		$items = zbx_toArray($data['items']);
		$applicationids = array();
		$itemids = array();

		if(empty($applications)) return true;

// PERMISSION {{{
		$app_options = array(
			'applicationids' => zbx_objectValues($applications, 'applicationid'),
			'editable' => 1,
			'extendoutput' => 1,
			'preservekeys' => 1
		);
		$allowed_applications = self::get($app_options);
		foreach($applications as $num => $application){
			if(!isset($allowed_applications[$application['applicationid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}
			$applicationids[] = $application['applicationid'];
		}

		$item_options = array(
			'itemids' => zbx_objectValues($items, 'itemid'),
			'editable' => 1,
			'extendoutput' => 1,
			'preservekeys' => 1
		);
		$allowed_items = CItem::get($item_options);
		foreach($items as $num => $item){
			if(!isset($allowed_items[$item['itemid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}
			$itemids[] = $item['itemid'];
		}
// }}} PERMISSION


		self::BeginTransaction(__METHOD__);

		$sql = 'SELECT itemid, applicationid '.
				' FROM items_applications '.
				' WHERE '.DBcondition('itemid', $itemids).
					' AND '.DBcondition('applicationid', $applicationids);
		$linked_db = DBselect($sql);
		while($pair = DBfetch($linked_db)){
			$linked[$pair['applicationid']] = array($pair['itemid'] => $pair['itemid']);
		}

		foreach($applicationids as $anum => $applicationid){
			foreach($itemids as $inum => $itemid){
				if(isset($linked[$applicationid]) && isset($linked[$applicationid][$itemid])) continue;

				$itemappid = get_dbid('items_applications', 'itemappid');
				$result = DBexecute("INSERT INTO items_applications (itemappid, itemid, applicationid) VALUES ($itemappid, $itemid, $applicationid)");
				if(!$result){
					break 2;
				}
			}
		}

		if($result){
			$child_applications = array();
			foreach($itemids as $itemid){
				$db_childs = DBselect('SELECT itemid, hostid FROM items WHERE templateid='.$itemid);

				if($child = DBfetch($db_childs)){
					$sql = 'SELECT a1.applicationid '.
							' FROM applications a1, applications a2 '.
							' WHERE a1.name=a2.name '.
								' AND a1.hostid='.$child['hostid'].
								' AND '.DBcondition('a2.applicationid', $applicationids);
					$db_apps = DBselect($sql);
					while($app = DBfetch($db_apps)){
						$child_applications[] = $app;
					}
					$result = self::addItems(array('items' => $child, 'applications' => $child_applications));
					if(!$result){
						break;
					}
				}
			}
		}


		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$options = array(
				'applicationids' => $applicationids,
				'output' => API_OUTPUT_EXTEND,
				'select_items' => API_OUTPUT_EXTEND,
				'nopermission' => 1
			);
			$result = self::get($options);
			return $result;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}


}

?>
