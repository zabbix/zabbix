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
 * File containing CInterface class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Interfaces
 */
class CHostInterface extends CZBXAPI{
/**
 * Get Interface Interface data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param array $options['hostids'] Interface IDs
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['extendoutput'] return all fields for Interfaces
 * @param boolean $options['selectHosts'] select Interface hosts
 * @param boolean $options['selectItems'] select Items
 * @param int $options['count'] count Interfaces, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in Interface name
 * @param int $options['limit'] limit selection
 * @param string $options['sortfield'] field to sort by
 * @param string $options['sortorder'] sort order
 * @return array|boolean Interface data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$nodeCheck = false;
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('interfaceid', 'dns', 'ip'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('interface' => 'hi.interfaceid'),
			'from' => array('interface' => 'interface hi'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null
		);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'interfaceids'				=> null,
			'itemids'					=> null,
			'triggerids'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,

// filter
			'filter'					=> null,
			'search'					=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,

// OutPut
			'output'					=> API_OUTPUT_REFER,
			'selectHosts'				=> null,
			'selectItems'				=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);

		$options = zbx_array_merge($def_options, $options);

		if(is_array($options['output'])){
			unset($sql_parts['select']['interface']);
			$sql_parts['select']['interfaceid'] = ' hi.interfaceid';
			foreach($options['output'] as $key => $field){
				$sql_parts['select'][$field] = ' hi.'.$field;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
		}

// editable + PERMISSION CHECK
		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where'][] = 'hg.hostid=hi.hostid';
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
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// interfaceids
		if(!is_null($options['interfaceids'])){
			zbx_value2array($options['interfaceids']);
			$sql_parts['where']['interfaceid'] = DBcondition('hi.interfaceid', $options['interfaceids']);

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('hi.interfaceid', $nodeids);
			}
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);
			$sql_parts['select']['hostid'] = 'hi.hostid';
			$sql_parts['where']['hostid'] = DBcondition('hi.hostid', $options['hostids']);

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('hi.hostid', $nodeids);
			}
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['itemid'] = 'i.itemid';
			}

			$sql_parts['from']['items'] = 'items i';
			$sql_parts['where'][] = DBcondition('i.itemid', $options['itemids']);
			$sql_parts['where']['hi'] = 'hi.hostid=i.hostid';

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('i.itemid', $nodeids);
			}
		}

// triggerids
		if(!is_null($options['triggerids'])){
			zbx_value2array($options['triggerids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['triggerid'] = 'f.triggerid';
			}

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			$sql_parts['where']['hi'] = 'hi.hostid=i.hostid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('f.triggerid', $nodeids);
			}
		}

// node check !!!!!
// should last, after all ****IDS checks
		if(!$nodeCheck){
			$nodeCheck = true;
			$sql_parts['where'][] = DBin_node('hi.interfaceid', $nodeids);
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['interface'] = 'hi.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT hi.interfaceid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('interface hi', $options, $sql_parts);
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('interface hi', $options, $sql_parts);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][$options['sortfield']] = 'hi.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('hi.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('hi.*', $sql_parts['select'])){
				$sql_parts['select'][$options['sortfield']] = 'hi.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------


		$interfaceids = array();

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
		if(!empty($sql_parts['where']))		$sql_where.= implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['group']))		$sql_where.= ' GROUP BY '.implode(',',$sql_parts['group']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.$sql_where.
				$sql_group.
				$sql_order;
//SDI($sql);
		$res = DBselect($sql, $sql_limit);
		while($interface = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $interface;
				else
					$result = $interface['rowscount'];
			}
			else{
				$interfaceids[$interface['interfaceid']] = $interface['interfaceid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$interface['interfaceid']] = array('interfaceid' => $interface['interfaceid']);
				}
				else{
					if(!isset($result[$interface['interfaceid']])) $result[$interface['interfaceid']] = array();

					if(!is_null($options['selectHosts']) && !isset($result[$interface['hostid']]['hosts'])){
						$result[$interface['interfaceid']]['hosts'] = array();
					}
					if(!is_null($options['selectItems']) && !isset($result[$interface['hostid']]['items'])){
						$result[$interface['interfaceid']]['items'] = array();
					}

// itemids
					if(isset($interface['itemid']) && is_null($options['selectItems'])){
						if(!isset($result[$interface['hostid']]['items']))
							$result[$interface['hostid']]['items'] = array();

						$result[$interface['hostid']]['items'][] = array('itemid' => $interface['itemid']);
						unset($interface['itemid']);
					}
//---

					$result[$interface['interfaceid']] += $interface;
				}
			}
		}

Copt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding Hosts
		if(!is_null($options['selectHosts'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'interfaceids' => $interfaceids,
				'preservekeys' => 1
			);

			if(is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectHosts'];
				$hosts = CHost::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($hosts, 'host');

				$count = array();
				foreach($hosts as $hostid => $host){
					unset($hosts[$hostid]['interfaces']);

					foreach($host['interfaces'] as $tnum => $interface){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$interface['interfaceid']])) $count[$interface['interfaceid']] = 0;
							$count[$interface['interfaceid']]++;

							if($count[$interface['interfaceid']] > $options['limitSelects']) continue;
						}

						$result[$interface['interfaceid']]['hosts'][] = &$hosts[$hostid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectHosts']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$hosts = CHost::get($obj_params);
				$hosts = zbx_toHash($hosts, 'hostid');
				foreach($result as $templateid => $template){
					if(isset($hosts[$templateid]))
						$result[$templateid]['hosts'] = $hosts[$templateid]['rowscount'];
					else
						$result[$templateid]['hosts'] = 0;
				}
			}
		}

// Adding Items
		if(!is_null($options['selectItems'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'interfaceids' => $interfaceids,
				'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			if(is_array($options['selectItems']) || str_in_array($options['selectItems'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectItems'];
				$items = CItem::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($items, 'description');

				$count = array();
				foreach($items as $itemid => $item){
					if(!is_null($options['limitSelects'])){
						if(!isset($count[$item['interfaceid']])) $count[$item['interfaceid']] = 0;
						$count[$item['interfaceid']]++;

						if($count[$item['interfaceid']] > $options['limitSelects']) continue;
					}

					$result[$item['interfaceid']]['items'][] = &$items[$itemid];
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectItems']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$items = CItem::get($obj_params);
				$items = zbx_toHash($items, 'interfaceid');
				foreach($result as $interfaceid => $interface){
					if(isset($items[$interfaceid]))
						$result[$interfaceid]['items'] = $items[$interfaceid]['rowscount'];
					else
						$result[$interfaceid]['items'] = 0;
				}
			}
		}

Copt::memoryPick();
// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	public static function exists($object){
		$keyFields = array('interfaceid', 'hostid', 'ip', 'dns');

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

	protected static function checkInput(&$interfaces, $method){
		$create = ($method == 'create');
		$update = ($method == 'update');
		$delete = ($method == 'delete');

// permissions
		if($update || $delete){
			$interfaceDBfields = array('interfaceid'=> null);
			$dbInterfaces = self::get(array(
				'output' => API_OUTPUT_EXTEND,
				'interfaceids' => zbx_objectValues($interfaces, 'interfaceid'),
				'editable' => 1,
				'preservekeys' => 1
			));
		}
		else{
			$interfaceDBfields = array('hostid'=>null,'ip'=>null,'dns'=>null,'useip'=>null);
			$dbHosts = CHost::get(array(
				'output' => array('host', 'status'),
				'hostids' => zbx_objectValues($interfaces, 'hostid'),
				'editable' => 1,
				'preservekeys' => 1
			));

			$dbProxies = CProxy::get(array(
				'output' => array('host', 'status'),
				'proxyids' => zbx_objectValues($interfaces, 'hostid'),
				'editable' => 1,
				'preservekeys' => 1
			));
		}

		foreach($interfaces as $inum => &$interface){
			if(!check_db_fields($interfaceDBfields, $interface)){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_ARGUMENTS_PASSED_TO_FUNCTION);
			}

			if($update || $delete){
				if(!isset($dbInterfaces[$interface['interfaceid']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);

				$dbInterface = $dbInterfaces[$interface['interfaceid']];
				if(isset($interface['hostid']) && (bccomp($dbInterface['hostid'], $interface['hostid']) !=0))
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Can not switch host for interface');

				$interface['hostid'] = $dbInterface['hostid'];

				if($delete) continue;


				if($update){
					if(((isset($interface['ip']) && zbx_empty($interface['ip'])) || zbx_empty($dbInterfaces[$interface['interfaceid']]['ip']))
						&& ((isset($interface['dns']) && zbx_empty($interface['dns'])) || zbx_empty($dbInterfaces[$interface['interfaceid']]['dns']))
					){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'IP and DNS can not be empty for host interface');
					}
				}
			}
			else{
				if(!isset($dbHosts[$interface['hostid']]) && !isset($dbProxies[$interface['hostid']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);

				if(isset($dbProxies[$interface['hostid']]))
					$interface['type'] = INTERFACE_TYPE_UNKNOWN;
				else if(!isset($interface['type']))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_ARGUMENTS_PASSED_TO_FUNCTION);
			}

			if($create){
				if(zbx_empty($interface['ip']) && zbx_empty($interface['dns'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'IP and DNS can not be empty for host interface');
				}

				if(isset($interface['port']) && zbx_empty($interface['port'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'PORT can not be empty for host interface');
				}
			}

			if(isset($interface['dns']) && !zbx_empty($interface['dns'])){
				if(!preg_match('/^'.ZBX_PREG_DNS_FORMAT.'$/i', $interface['dns'])
					&& !preg_match('/^'.ZBX_PREG_MACRO_NAME_FORMAT.'$/u', $interface['dns'])
					&& !preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/u', $interface['dns']))
				{
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect interface DNS paramter "'.$interface['dns'].'" provided');
				}
			}

			if(isset($interface['ip']) && !zbx_empty($interface['ip'])){
				if(!validate_ip($interface['ip'], $arr)
					&& !preg_match('/^'.ZBX_PREG_MACRO_NAME_FORMAT.'$/i', $interface['ip'])
					&& !preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/i', $interface['ip']))
				{
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect interface IP parameter "'.$interface['ip'].'" provided');
				}
			}

			if(isset($interface['port'])){
				if(zbx_ctype_digit($interface['port'])){
					if($interface['port'] > 65535 || $interface['port'] < 0)
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect interface PORT "'.$interface['port'].'" provided');
				}
				else if(!preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/', $interface['port'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect interface PORT "'.$interface['port'].'". '.S_WRONG_MACRO);
				}
			}
		}
		unset($interface);

		if($delete){
			$items = CItem::get(array(
				'output' => array('key_'),
				'selectHosts' => array('host'),
				'interfaceids' => zbx_objectValues($interfaces, 'interfaceid'),
				'preservekeys' => 1,
				'nopermissions' => 1
			));

			foreach($items as $item){
				$host = reset($item['hosts']);
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Interface is linked to "'.$host['host'].':'.$item['key_'].' item');
			}
		}
	}

	protected static function setMainInterfaces($interfaces){
		$interfaces = zbx_toHash($interfaces, 'hostid');
		$hostids = array_keys($interfaces);

		$updateData = array();
		foreach($hostids as $hnum => $hostid){
			$interfaces = self::get(array(
				'output' => API_OUTPUT_EXTEND,
				'hostids' => $hostid,
				'nopermissions' => 1,
				'preservekeys' => 1,
				'sortfield' => 'interfaceid',
				'sortorder' => ZBX_SORT_UP,
			));

			$interfaceMain = array();
			foreach($interfaces as $interfaceid => $interface){
				if(!isset($interfaceMain[$interface['type']])){
					$interfaceMain[$interface['type']] = $interface;

					if($interface['main'] != INTERFACE_PRIMARY){
						$updateData[] = array(
							'values' => array('main' => INTERFACE_PRIMARY),
							'where'=> array('interfaceid='.$interface['interfaceid'])
						);
					}
					continue;
				}

				if($interface['main'] == INTERFACE_PRIMARY){
					$updateData[] = array(
						'values' => array('main' => INTERFACE_SECONDARY),
						'where'=> array('interfaceid='.$interface['interfaceid'])
					);
				}
			}
		}

		if(!empty($updateData))
			DB::update('interface', $updateData);
	}

/**
 * Add Interface
 *
 * @param _array $Interfaces multidimensional array with Interfaces data
 * @return array
 */
	public static function create($interfaces){
		$interfaces = zbx_toArray($interfaces);
		try{
			self::BeginTransaction(__METHOD__);

			self::checkInput($interfaces, __FUNCTION__);

			$interfaceids = DB::insert('interface', $interfaces);

// auto seting main interfaces
			self::setMainInterfaces($interfaces);

			self::EndTransaction(true, __METHOD__);
			return array('interfaceids' => $interfaceids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Update Interface
 *
 * @param _array $interfaces multidimensional array with Interfaces data
 * @return array
 */
	public static function update($interfaces){
		$interfaces = zbx_toArray($interfaces);

		try{
			self::BeginTransaction(__METHOD__);

			self::checkInput($interfaces, __FUNCTION__);

			$data = array();
			foreach($interfaces as $inum => $interface){
				$data[] = array('values' => $interface, 'where'=> array('interfaceid='.$interface['interfaceid']));
			}
			$result = DB::update('interface', $data);

// auto seting main interfaces
			self::setMainInterfaces($interfaces);

			self::EndTransaction($result, __METHOD__);
			return array('interfaceids' => zbx_objectValues($interfaces, 'interfaceid'));
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}



/**
 * Delete Interface
 *
 * @param array $Interfaces
 * @param array $Interfaceids[1, ...] Interface ID to delete
 * @return array|boolean
 */
	public static function delete($interfaceids){
		if(empty($interfaceids)) return true;

		$interfaceids = zbx_toArray($interfaceids);
		$interfaces = zbx_toObject($interfaceids, 'interfaceid');

		try{
			self::BeginTransaction(__METHOD__);

			self::checkInput($interfaces,__FUNCTION__);

			DB::delete('interface', array('interfaceid'=>$interfaceids));

// auto seting main interfaces
			self::setMainInterfaces($interfaces);

			self::EndTransaction(true, __METHOD__);
			return array('interfaceids' => $interfaceids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	public static function massAdd($data){
		$interfaces = zbx_toArray($data['interfaces']);
		$hosts = zbx_toArray($data['hosts']);

		try{
			self::BeginTransaction(__METHOD__);

			$insertData = array();
			foreach($interfaces as $inum => $interface){
				foreach($hosts as $hnum => $host){
					$newInterface = $interface;
					$newInterface['hostid'] = $host['hostid'];

					$insertData[] = $newInterface;
				}
			}

			$interfaceids = self::create($insertData);

			self::EndTransaction(true, __METHOD__);
			return array('interfaceids' => $interfaceids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Remove Hosts from Hostinterfaces
 *
 * @param array $data
 * @param array $data['interfaceids']
 * @param array $data['hostids']
 * @param array $data['templateids']
 * @return boolean
 */
	public static function massRemove($data){
		$interfaces = zbx_toArray($data['interfaces']);
		$interfaceids = zbx_objectValues($interfaces, 'interfaceid');

		$hostids = zbx_toArray($data['hostids']);

		try{
			self::BeginTransaction(__METHOD__);

			self::checkInput($interfaces, __FUNCTION__);

			foreach($interfaces as $inum => $interface){
				DB::delete('interface', array(
					'hostid'=>$hostids,
					'ip'=>$interface['ip'],
					'dns'=>$interface['dns'],
					'port'=>$interface['port']
				));
			}

			self::EndTransaction(true, __METHOD__);
			return array('interfaceids' => $interfaceids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}
}
?>
