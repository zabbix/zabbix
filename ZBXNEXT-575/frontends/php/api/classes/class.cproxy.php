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
 * File containing CProxy class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Proxies
 */
class CProxy extends CZBXAPI{
/**
 * Get Proxy data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids']
 * @param array $options['proxyids']
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['count'] returns value in rowscount
 * @param string $options['pattern']
 * @param int $options['limit']
 * @param string $options['sortfield']
 * @param string $options['sortorder']
 * @return array|boolean
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];

		$sort_columns = array('hostid', 'host', 'status'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('hostid' => 'h.hostid'),
			'from' => array('hosts' => 'hosts h'),
			'where' => array('h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'proxyids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'			=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,

// OutPut
			'output'					=> API_OUTPUT_REFER,
			'countOutput'				=> null,
			'preservekeys'				=> null,

			'selectHosts'				=> null,
			'selectInterfaces'			=> null,
			'limitSelects'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);

		if(is_array($options['output'])){
			unset($sql_parts['select']['hosts']);

			$dbTable = DB::getSchema('hosts');
			$sql_parts['select']['hostid'] = ' h.hostid';
			foreach($options['output'] as $key => $field){
				if($field == 'proxyid') continue;

				if(isset($dbTable['fields'][$field]))
					$sql_parts['select'][$field] = ' h.'.$field;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
		}


// editable + PERMISSION CHECK
		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			if($permission == PERM_READ_WRITE)
				return array();
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// proxyids
		if(!is_null($options['proxyids'])){
			zbx_value2array($options['proxyids']);
			$sql_parts['where'][] = DBcondition('h.hostid', $options['proxyids']);
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('hosts h', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('hosts h', $options, $sql_parts);
		}


// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['hostid'] = 'h.hostid';
			$sql_parts['select']['host'] = 'h.host';
			$sql_parts['select']['status'] = 'h.status';
			$sql_parts['select']['lastaccess'] = 'h.lastaccess';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT h.hostid) as rowscount');
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'h.'.$options['sortfield'].' '.$sortorder;
			if(!str_in_array('h.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('h.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'h.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------

		$proxyids = array();

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

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('h.hostid', $nodeids).
					$sql_where.
				$sql_order;
// sdi($sql);
		$res = DBselect($sql, $sql_limit);
		while($proxy = DBfetch($res)){
			if($options['countOutput']){
				$result = $proxy['rowscount'];
			}
			else{
				$proxyids[$proxy['hostid']] = $proxy['hostid'];

				$proxy['proxyid'] = $proxy['hostid'];
				unset($proxy['hostid']);

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$proxy['proxyid']] = array('proxyid' => $proxy['proxyid']);
				}
				else{
					if(!isset($result[$proxy['proxyid']])) $result[$proxy['proxyid']]= array();

					if(!is_null($options['selectHosts']) && !isset($result[$proxy['proxyid']]['hosts'])){
						$result[$proxy['proxyid']]['hosts'] = array();
					}

					if(!is_null($options['selectInterfaces']) && !isset($result[$proxy['proxyid']]['interfaces'])){
						$result[$proxy['proxyid']]['interfaces'] = array();
					}

					$result[$proxy['proxyid']] += $proxy;
				}
			}
		}

		if(!is_null($options['countOutput']) || empty($proxyids)){
			return $result;
		}

// Adding Objects

// selectHosts
		if(!is_null($options['selectHosts'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'proxyids' => $proxyids,
				'preservekeys' => 1
			);
			if(is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectHosts'];

				$hosts = CHost::get($obj_params);

				foreach($hosts as $host){
					$result[$host['proxy_hostid']]['hosts'][] = $host;
				}
			}
		}

// Adding HostInterfaces
		if(!is_null($options['selectInterfaces'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $proxyids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			if(is_array($options['selectInterfaces']) || str_in_array($options['selectInterfaces'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectInterfaces'];
				$interfaces = CHostInterface::get($obj_params);

				if(!is_null($options['limitSelects']))
					order_result($interfaces, 'interfaceid', ZBX_SORT_UP);

				$count = array();
				foreach($interfaces as $interfaceid => $interface){
					if(!is_null($options['limitSelects'])){
						if(!isset($count[$interface['hostid']])) $count[$interface['hostid']] = 0;
						$count[$interface['hostid']]++;

						if($count[$interface['hostid']] > $options['limitSelects']) continue;
					}

					$result[$interface['hostid']]['interfaces'][] = &$interfaces[$interfaceid];
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectInterfaces']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$interfaces = CHostInterface::get($obj_params);
				$interfaces = zbx_toHash($interfaces, 'hostid');
				foreach($result as $proxyid => $proxy){
					if(isset($interfaces[$proxyid]))
						$result[$proxyid]['interfaces'] = $interfaces[$proxyid]['rowscount'];
					else
						$result[$proxyid]['interfaces'] = 0;
				}
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	protected static function checkInput(&$proxies, $method){
		global $USER_DETAILS;

		$create = ($method == 'create');
		$update = ($method == 'update');
		$delete = ($method == 'delete');

		foreach($proxies as $inum => &$proxy){
			if(isset($proxy['proxyid'])) $proxy['hostid'] = $proxy['proxyid'];
			if(isset($proxy['hostid'])) $proxy['proxyid'] = $proxy['hostid'];
		}
		unset($proxy);

// permissions
		if($update || $delete){
			$proxyDBfields = array('proxyid'=> null);
			$dbProxies = self::get(array(
				'output' => array('proxyid', 'hostid', 'host', 'status'),
				'proxyids' => zbx_objectValues($proxies, 'proxyid'),
				'editable' => 1,
				'preservekeys' => 1
			));
		}
		else{
			$proxyDBfields = array('host'=>null);
		}


		foreach($proxies as $inum => &$proxy){
			if(!check_db_fields($proxyDBfields, $proxy)){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for proxy [ '.$proxy['host'].' ]');
			}

			if($update || $delete){
				if(!isset($dbProxies[$proxy['proxyid']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);

				if(isset($proxy['status']) && ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE)){
					if($dbProxies[$proxy['proxyid']]['status'] == $proxy['status'])
						unset($proxy['status']);
					else if(!isset($proxy['interfaces']))
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('No interfaces provided for proxy "%s".', $proxy['host']));
				}

				if($delete) $proxy['host'] = $dbProxies[$proxy['proxyid']]['host'];
			}
			else{
				if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type'])
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);

				if(($proxy['status'] == HOST_STATUS_PROXY_PASSIVE) && !isset($proxy['interfaces']))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('No interfaces provided for proxy "%s"',$proxy['host']));
			}

			if($delete) continue;

			if(isset($proxy['interfaces'])){
				if(!is_array($proxy['interfaces']) || empty($proxy['interfaces']))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('No interfaces for proxy "%s"',$proxy['host']));
				else if(count($proxy['interfaces']) > 1)
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Too many interfaces provided for proxy "%s".',$proxy['host']));
			}

			if(isset($proxy['host'])){
				if(!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $proxy['host'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect characters used for Proxy name "%s".',$proxy['host']));
				}

				$proxiesExists = self::get(array(
					'filter' => array('host' => $proxy['host'])
				));
				foreach($proxiesExists as $exnum => $proxyExists){
					if(!$update || ($proxyExists['proxyid'] != $proxy['proxyid'])){
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host "%s" already exists.', $proxy['host']));
					}
				}
			}
		}
		unset($proxy);
	}


	public static function create($proxies){
		$proxies = zbx_toArray($proxies);
		$proxyids = array();

		try{
			self::BeginTransaction(__METHOD__);

			self::checkInput($proxies, __FUNCTION__);

			$proxyids = DB::insert('hosts', $proxies);

			$hostUpdate = array();
			foreach($proxies as $pnum => $proxy){
				if(!isset($proxy['hosts'])) continue;

				$hostids = zbx_objectValues($proxy['hosts'], 'hostid');
				$hostUpdate[] = array(
					'values' => array('proxy_hostid' => $proxyids[$pnum]),
					'where' => array(DBCondition('hostid', $hostids))
				);

				if($proxy['status'] == HOST_STATUS_PROXY_ACTIVE) continue;

// INTERFACES
				foreach($proxy['interfaces'] as $ifnum => &$interface){
					$interface['hostid'] = $proxyids[$pnum];
				}
				unset($interface);

				$result = CHostInterface::create($proxy['interfaces']);
				if(!$result) self::exception(ZBX_API_ERROR_INTERNAL, 'Proxy interface creation failed');
			}

			DB::update('hosts', $hostUpdate);

			self::EndTransaction(true, __METHOD__);
			return array('proxyids' => $proxyids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}


	public static function update($proxies){
		$proxies = zbx_toArray($proxies);
		$proxyids = array();

		try{
			self::BeginTransaction(__METHOD__);

			self::checkInput($proxies, __FUNCTION__);

			$proxyUpdate = array();
			$hostUpdate = array();
			foreach($proxies as $pnum => $proxy){
				$proxyids[] = $proxy['proxyid'];

				$proxyUpdate[] = array(
					'values' => $proxy,
					'where' => array('hostid='.$proxy['proxyid'])
				);

				if(!isset($proxy['hosts'])) continue;

				$hostUpdate[] = array(
					'values' => array('proxy_hostid' => 0),
					'where' => array('proxy_hostid='.$proxy['proxyid'])
				);

				$hostids = zbx_objectValues($proxy['hosts'], 'hostid');
				$hostUpdate[] = array(
					'values' => array('proxy_hostid' => $proxy['proxyid']),
					'where' => array(DBCondition('hostid', $hostids))
				);

// INTERFACES
				if(isset($proxy['interfaces']) && is_array($proxy['interfaces'])){
					foreach($proxy['interfaces'] as $inum => &$interface){
						$interface['hostid'] = $proxy['hostid'];
					}

					if(isset($interface['interfaceid']))
						$result = CHostInterface::update($proxy['interfaces']);
					else
						$result = CHostInterface::create($proxy['interfaces']);

					if(!$result) self::exception(ZBX_API_ERROR_INTERNAL, 'Proxy interface update failed');

// unset after foreach with pointer
					unset($interface);
				}
			}

			DB::update('hosts', $proxyUpdate);
			DB::update('hosts', $hostUpdate);

			self::EndTransaction(true, __METHOD__);
			return array('proxyids' => $proxyids);
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
 * Delete Proxy
 *
 * @param array $proxies
 * @param array $proxies[0, ...]['hostid'] Host ID to delete
 * @return array|boolean
 */
	public static function delete($proxies){

		try{
			self::BeginTransaction(__METHOD__);

			if(empty($proxies)) self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter'));

			$proxies = zbx_toArray($proxies);
			$proxyids = zbx_objectValues($proxies, 'proxyid');

			self::checkInput($proxies, __FUNCTION__);

// disable actions
			$actionids = array();

// conditions

			$sql = 'SELECT DISTINCT actionid '.
					' FROM conditions '.
					' WHERE conditiontype='.CONDITION_TYPE_PROXY.
						' AND '.DBcondition('value',$proxyids);
			$db_actions = DBselect($sql);
			while($db_action = DBfetch($db_actions)){
				$actionids[$db_action['actionid']] = $db_action['actionid'];
			}

			if(!empty($actionids)){
				$update = array();
				$update[] = array(
					'values' => array('status' => ACTION_STATUS_DISABLED),
					'where' => array(DBcondition('actionid',$actionids))
				);
				DB::update('actions', $update);
			}

// delete action conditions
			DB::delete('conditions', array(
				'conditiontype'=>CONDITION_TYPE_PROXY,
				'value'=>$proxyids
			));

// interfaces
			DB::delete('interface', array('hostid'=>$proxyids));

// Proxies
			$update = array();
			$update[] = array(
				'values' => array('proxy_hostid' => 0),
				'where' => array(DBcondition('proxy_hostid',$proxyids))
			);
			DB::update('hosts', $update);


// delete host
			DB::delete('hosts', array('hostid'=>$proxyids));

// TODO: remove info from API
			foreach($proxies as $hnum => $proxy) {
				info(S_HOST_HAS_BEEN_DELETED_MSG_PART1.SPACE.$proxy['host'].SPACE.S_HOST_HAS_BEEN_DELETED_MSG_PART2);
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_PROXY,'['.$proxy['host'].' ] ['.$proxy['hostid'].']');
			}

			self::EndTransaction(true, __METHOD__);
			return array('proxyids' => $proxyids);
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
