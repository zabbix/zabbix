<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
 * File containing CUserMacro class for API.
 * @package API
 */
/**
 * Class containing methods for operations with UserMacro
 */
class CUserMacro extends CZBXAPI{
/**
 * Get UserMacros data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param array $options['groupids'] UserMacrosGroup IDs
 * @param array $options['macroids'] UserMacros IDs
 * @param boolean $options['monitored_macros'] only monitored UserMacros
 * @param boolean $options['templated_macros'] include templates in result
 * @param boolean $options['with_items'] only with items
 * @param boolean $options['with_monitored_items'] only with monitored items
 * @param boolean $options['with_historical_items'] only with historical items
 * @param boolean $options['with_triggers'] only with triggers
 * @param boolean $options['with_monitored_triggers'] only with monitored triggers
 * @param boolean $options['with_httptests'] only with http tests
 * @param boolean $options['with_monitored_httptests'] only with monitored http tests
 * @param boolean $options['with_graphs'] only with graphs
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['extendoutput'] return all fields for UserMacros
 * @param int $options['count'] count UserMacros, returned column name is rowscount
 * @param string $options['pattern'] search macros by pattern in macro names
 * @param int $options['limit'] limit selection
 * @param string $options['order'] deprecated parameter (for now)
 * @return array|boolean UserMacros data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('macro'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('macros' => 'hm.hostmacroid'),
			'from' => array('hostmacro hm'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$sql_parts_global = array(
			'select' => array('macros' => 'gm.globalmacroid'),
			'from' => array('globalmacro gm'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'hostmacroids'				=> null,
			'globalmacroids'			=> null,
			'templateids'				=> null,
			'macros'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// filter
			'pattern'					=> '',
// OutPut
			'globalmacro'				=> null,
			'output'					=> API_OUTPUT_REFER,
			'extendoutput'				=> null,
			'select_groups'				=> null,
			'select_hosts'				=> null,
			'select_templates'			=> null,
			'count'						=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_groups'])){
				$options['select_groups'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_hosts'])){
				$options['select_hosts'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_templates'])){
				$options['select_templates'] = API_OUTPUT_EXTEND;
			}
		}


// editable + PERMISSION CHECK

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions'] || !is_null($options['globalmacro'])){
		}
		else{
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where']['hgh'] = 'hg.hostid=hm.hostid';
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

// Global Macro
		if(!is_null($options['globalmacro'])){
			$options['groupids'] = null;
			$options['hostmacroids'] = null;
			$options['triggerids'] = null;
			$options['hostids'] = null;
			$options['itemids'] = null;

			$options['select_groups'] = null;
			$options['select_templates'] = null;
			$options['select_hosts'] = null;
		}

// globalmacroids
		if(!is_null($options['globalmacroids'])){
			zbx_value2array($options['globalmacroids']);
			$sql_parts_global['where'][] = DBcondition('gm.globalmacroid', $options['globalmacroids']);
		}

// hostmacroids
		if(!is_null($options['hostmacroids'])){
			zbx_value2array($options['hostmacroids']);
			$sql_parts['where'][] = DBcondition('hm.hostmacroid', $options['hostmacroids']);
		}

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where']['hgh'] = 'hg.hostid=hm.hostid';
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);
			$sql_parts['where'][] = DBcondition('hm.hostid', $options['hostids']);
		}

// templateids
		if(!is_null($options['templateids'])){
			zbx_value2array($options['templateids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['templateid'] = 'ht.templateid';
			}

			$sql_parts['from']['macros_templates'] = 'macros_templates ht';
			$sql_parts['where'][] = DBcondition('ht.templateid', $options['templateids']);
			$sql_parts['where']['hht'] = 'hm.hostid=ht.macroid';
		}

// macros
		if(!is_null($options['macros'])){
			zbx_value2array($options['macros']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['macro'] = 'hm.macro';
				$sql_parts_global['select']['macro'] = 'gm.macro';
			}

			$sql_parts['where'][] = DBcondition('hm.macro', $options['macros'], null, true);
			$sql_parts_global['where'][] = DBcondition('gm.macro', $options['macros'], null, true);
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['macros'] = 'hm.*';
			$sql_parts_global['select']['macros'] = 'gm.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT hm.hostmacroid) as rowscount');
			$sql_parts_global['select'] = array('count(DISTINCT gm.globalmacroid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(hm.macro) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
			$sql_parts_global['where'][] = ' UPPER(gm.macro) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'hm.'.$options['sortfield'].' '.$sortorder;
			$sql_parts_global['order'][] = 'gm.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('hm.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('hm.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'hm.'.$options['sortfield'];
			}

			if(!str_in_array('gm.'.$options['sortfield'], $sql_parts_global['select']) && !str_in_array('gm.*', $sql_parts_global['select'])){
				$sql_parts_global['select'][] = 'gm.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
			$sql_parts_global['limit'] = $options['limit'];
		}
//-------

// init GLOBALS
		if(!is_null($options['globalmacro'])){
			$sql_parts_global['select'] = array_unique($sql_parts_global['select']);
			$sql_parts_global['from'] = array_unique($sql_parts_global['from']);
			$sql_parts_global['where'] = array_unique($sql_parts_global['where']);
			$sql_parts_global['order'] = array_unique($sql_parts_global['order']);

			$sql_select = '';
			$sql_from = '';
			$sql_where = '';
			$sql_order = '';
			if(!empty($sql_parts_global['select']))		$sql_select.= implode(',',$sql_parts_global['select']);
			if(!empty($sql_parts_global['from']))		$sql_from.= implode(',',$sql_parts_global['from']);
			if(!empty($sql_parts_global['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts_global['where']);
			if(!empty($sql_parts_global['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts_global['order']);
			$sql_limit = $sql_parts_global['limit'];

			$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.'
					FROM '.$sql_from.'
					WHERE '.DBin_node('gm.globalmacroid', $nodeids).
					$sql_where.
					$sql_order;
			$res = DBselect($sql, $sql_limit);
			while($macro = DBfetch($res)){
				if($options['count'])
					$result = $macro;
				else{
					$globalmacroids[$macro['globalmacroid']] = $macro['globalmacroid'];

					if($options['output'] == API_OUTPUT_SHORTEN){
						$result[$macro['globalmacroid']] = array('globalmacroid' => $macro['globalmacroid']);
					}
					else{
						if(!isset($result[$macro['globalmacroid']])) $result[$macro['globalmacroid']]= array();

						$result[$macro['globalmacroid']] += $macro;
					}
				}
			}
		}
// init HOSTS
		else{
			$hostids = array();

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

			$sql = 'SELECT '.$sql_select.'
					FROM '.$sql_from.'
					WHERE '.DBin_node('hm.hostmacroid', $nodeids).
					$sql_where.
					$sql_order;
//SDI($sql);
			$res = DBselect($sql, $sql_limit);
			while($macro = DBfetch($res)){
				if($options['count'])
					$result = $macro;
				else{
					$hostmacroids[$macro['hostmacroid']] = $macro['hostmacroid'];

					if($options['output'] == API_OUTPUT_SHORTEN){
						$result[$macro['hostmacroid']] = $macro['hostmacroid'];
					}
					else{
						$hostids[$macro['hostid']] = $macro['hostid'];

						if(!isset($result[$macro['hostmacroid']])) $result[$macro['hostmacroid']]= array();

						if($options['select_groups'] && !isset($result[$macro['hostmacroid']]['groups'])){
							$result[$macro['hostmacroid']]['groups'] = array();
						}

						if($options['select_templates'] && !isset($result[$macro['hostmacroid']]['templates'])){
							$result[$macro['hostmacroid']]['templates'] = array();
						}

						if($options['select_hosts'] && !isset($result[$macro['hostmacroid']]['hosts'])){
							$result[$macro['hostmacroid']]['hosts'] = array();
						}


// groupids
						if(isset($macro['groupid'])){
							if(!isset($result[$macro['hostmacroid']]['groups']))
								$result[$macro['hostmacroid']]['groups'] = array();

							$result[$macro['hostmacroid']]['groups'][$macro['groupid']] = array('groupid' => $macro['groupid']);
							unset($macro['groupid']);
						}
// templateids
						if(isset($macro['templateid'])){
							if(!isset($result[$macro['hostmacroid']]['templates']))
								$result[$macro['hostmacroid']]['templates'] = array();

							$result[$macro['hostmacroid']]['templates'][$macro['templateid']] = array('templateid' => $macro['templateid']);
							unset($macro['templateid']);
						}
// hostids
						if(isset($macro['hostid'])){
							if(!isset($result[$macro['hostmacroid']]['hosts']))
								$result[$macro['hostmacroid']]['hosts'] = array();

							$result[$macro['hostmacroid']]['hosts'][$macro['hostid']] = array('hostid' => $macro['hostid']);
							unset($macro['hostid']);
						}

						$result[$macro['hostmacroid']] += $macro;
					}
				}
			}
		}

		if(($options['output'] != API_OUTPUT_EXTEND) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding Groups
		if(!is_null($options['select_groups']) && str_in_array($options['select_groups'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_groups'],
				'hostids' => $hostids,
				'preservekeys' => 1
			);
			$groups = CHostgroup::get($obj_params);
			foreach($groups as $groupid => $group){
				$ghosts = $group['hosts'];
				unset($group['hosts']);
				foreach($ghosts as $num => $host){
					foreach($result as $macroid => $macro){
						if($macro['hostid'] == $host['hostid']){
							$result[$macroid]['groups'][] = $group;
						}
					}
				}
			}
		}

// Adding Templates
		if(!is_null($options['select_templates']) && str_in_array($options['select_templates'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_templates'],
				'hostids' => $hostids,
				'preservekeys' => 1
			);
			$templates = CTemplate::get($obj_params);
			foreach($templates as $templateid => $template){
				$thosts = $template['hosts'];
				unset($template['hosts']);
				foreach($thosts as $num => $host){
					foreach($result as $macroid => $macro){
						if($macro['hostid'] == $host['hostid']){
							$result[$macroid]['templates'][] = $template;
						}
					}
				}
			}
		}

// Adding Hosts
		if(!is_null($options['select_hosts']) && str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_hosts'],
				'hostids' => $hostids,
				'preservekeys' => 1
			);
			$hosts = CHost::get($obj_params);
			foreach($hosts as $hostid => $host){
				foreach($result as $macroid => $macro){
					if($macro['hostid'] == $hostid){
						$result[$macroid]['hosts'][] = $host;
					}
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
 * Gets all UserMacros data from DB by UserMacros ID
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $macro_data
 * @param string $macro_data['macroid']
 * @return array|boolean UserMacros data as array or false if error
 */
	public static function getHostMacroObjects($macro_data){
		$macro_data = zbx_toArray($macro_data);
		$result = array();
		$hostmacroids = array();

		$macros = array();
		foreach($macro_data as $macro_obj){
			if(!isset($macro_obj['hostid'])) $macro_obj['hostid'] = array();
			$macros[$macro_obj['hostid']][] = $macro_obj['macro'];
		}

		foreach($macros as $hostid => $mac_list){
			$sql = 'SELECT hostmacroid '.
					' FROM hostmacro '.
					' WHERE hostid='.$hostid.
					' AND '.DBcondition('macro', $mac_list, false, true);
			$res = DBselect($sql);
			while($macro = DBfetch($res)){
				$hostmacroids[$macro['hostmacroid']] = $macro['hostmacroid'];
			}
		}

		if(!empty($hostmacroids))
			$result = self::get(array('hostmacroids' => $hostmacroids, 'extendoutput' => 1));

	return $result;
	}

/**
 * Delete UserMacros
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $hostmacroids
 * @param array $hostmacroids['hostmacroids']
 * @return boolean
 */
	public static function deleteHostMacro($hostmacros){
		$hostmacros = zbx_toArray($hostmacros);
		$hostmacroids = zbx_objectValues($hostmacros, 'hostmacroid');

		try{
			self::BeginTransaction(__METHOD__);

			if(empty($hostmacroids))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ hostmacroids ]');

			$sql = 'DELETE FROM hostmacro WHERE '.DBcondition('hostmacroid', $hostmacroids);
			if(!DBExecute($sql))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

			self::EndTransaction(true, __METHOD__);
			return array('hostmacroids' => $hostmacroids);
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
 * Add global macros
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 * @param _array $macros
 * @param string $macros[0..]['macro']
 * @param string $macros[0..]['value']
 * @return array|boolean
 */
	public static function createGlobal($macros){
		$macros = zbx_toArray($macros);
		$macros_macros = zbx_objectValues($macros, 'macro');
		$globalmacroids = array();

		try{
			self::BeginTransaction(__METHOD__);

			foreach($macros as $mid => $macro){
				if(empty($macro['macro']) && empty($macro['value'])) unset($macros[$mid]);
			}
				
			self::validate($macros);
			
			$existing = array();
			$sql = 'SELECT macro FROM globalmacro WHERE '.DBcondition('macro', $macros_macros, false, true);
			$existing_db = DBselect($sql);
			while($m = DBfetch($existing_db)){
				$existing[$m['macro']] = 1;
			}

			foreach($macros as $mnum => $macro){
				if(isset($existing[$macro['macro']])) continue;
				
				$globalmacroids[] = $globalmacroid = get_dbid('globalmacro', 'globalmacroid');

				$values = array(
					'globalmacroid' => $globalmacroid,
					'macro' => zbx_dbstr($macro['macro']),
					'value' => zbx_dbstr($macro['value'])
				);
				$sql = 'INSERT INTO globalmacro ('. implode(', ', array_keys($values)) .')'.
					' VALUES ('. implode(', ', $values) .')';
				if(!DBExecute($sql))
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
			}

			self::EndTransaction(true, __METHOD__);
			return array('globalmacroids' => $globalmacroids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	public static function updateGlobal($macros){
		$macros = zbx_toArray($macros);
		$macros = zbx_toHash($macros, 'macro');
		$macros_macros = zbx_objectValues($macros, 'macro');
		$globalmacroids = array();

		try{
			self::BeginTransaction(__METHOD__);
			foreach($macros as $mid => $macro){
				if(empty($macro['macro']) && empty($macro['value'])) unset($macros[$mid]);
			}
			
			self::validate($macros);

			$sql = 'SELECT globalmacroid, macro FROM globalmacro WHERE '.DBcondition('macro', $macros_macros, false, true);
			$row_db = DBselect($sql);
			while($row = DBfetch($row_db)){
				$sql = 'UPDATE globalmacro SET value='.zbx_dbstr($macros[$row['macro']]['value']).
					' WHERE macro='.zbx_dbstr($row['macro']);
				if(!DBexecute($sql))
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
					
				$globalmacroids[] = $row['globalmacroid'];
			}			

			self::EndTransaction(true, __METHOD__);
			return array('globalmacroids' => $globalmacroids);
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
 * Delete UserMacros
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $globalmacroids
 * @param array $globalmacroids['globalmacroids']
 * @return boolean
 */
	public static function deleteGlobal($globalmacros){

		$globalmacros = zbx_toArray($globalmacros);
		$globalmacros = zbx_objectValues($globalmacros, 'macro');

		try{
			self::BeginTransaction(__METHOD__);

			if(empty($globalmacros))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ $globalmacroids ]');

			$sql = 'DELETE FROM globalmacro WHERE '.DBcondition('macro', $globalmacros, false, true);
			if(!DBExecute($sql))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

			self::EndTransaction(true, __METHOD__);
			// return array('globalmacroids' => $globalmacroids);
			return true;
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
 * Validates macros expression
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $macros array with macros expressions
 * @return array|boolean
 */
	public static function validate($macros){
		$tmp = array();
		foreach($macros as $macro){
			if(isset($tmp[$macro['macro']]))
				self::exception(ZBX_API_ERROR_PARAMETERS, '['.$macro['macro'].']: not unique');
			else
				$tmp[$macro['macro']] = 1;
		}

		foreach($macros as $macro){
			if(zbx_empty($macro['value'])){
				self::exception(ZBX_API_ERROR_PARAMETERS, '['.$macro['macro'].']: '.S_EMPTY_MACRO_VALUE);
			}
			if(zbx_strlen($macro['macro']) > 64){
				self::exception(ZBX_API_ERROR_PARAMETERS, '['.$macro['macro'].']: '.S_MACRO_TOO_LONG.' > 64');
			}

			if(zbx_strlen($macro['value']) > 255){
				self::exception(ZBX_API_ERROR_PARAMETERS, '['.$macro['macro'].']: '.S_MACRO_VALUE_TOO_LONG.' > 255');
			}

			if(!preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/', $macro['macro'])){
				self::exception(ZBX_API_ERROR_PARAMETERS, '['.$macro['macro'].']: '.S_WRONG_MACRO);
			}
		}

		return true;
	}

/**
 * Gets all UserMacros data from DB by UserMacros ID
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $macro_data
 * @param string $macro_data['macroid']
 * @return array|boolean UserMacros data as array or false if error
 */
	public static function getGlobalMacroObjects($macro_data){
		$result = array();
		$globalmacroids = array();

		$sql = 'SELECT globalmacroid '.
				' FROM globalmacro '.
				' WHERE globalmacroid='.$macro_data['globalmacroid'];
		$res = DBselect($sql);
		while($macro = DBfetch($res)){
			$globalmacroids[$macro[globalmacroid]] = $macro[globalmacroid];
		}

		if(!empty($globalmacroids))
			$result = self::get(array('globalmacroids'=>$globalmacroids, 'extendoutput'=>1));

	return $result;
	}

/**
 * Get UserMacros ID by UserMacros name
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $macro_data
 * @param string $macro_data['macro']
 * @param string $macro_data['hostid']
 * @return int|boolean
 */
	public static function getHostMacroId($macro_data){
		$result = false;

		$sql = 'SELECT hostmacroid '.
				' FROM hostmacro '.
				' WHERE macro='.zbx_dbstr($macro_data['macro']).
					' AND hostid='.$macro_data['hostid'].
					' AND '.DBin_node('hostmacroid', false);
		$res = DBselect($sql);
		if($macroid = DBfetch($res))
			$result = $macroid['hostmacroid'];
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => S_HOST_MACRO.' "'.$macro_data['macro'].'" '.S_DOESNT_EXIST);
		}

	return $result;
	}

/**
 * Add Macros to Hosts
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param array $data['templates']
 * @param array $data['hosts']
 * @param array $data['macros']
 * @return boolean
 */
	public static function massAdd($data){
		try{
			$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
			$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');

			$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
			$templateids = is_null($templates) ? array() : zbx_objectValues($templates, 'templateid');

			self::BeginTransaction(__METHOD__);

			if(isset($data['macros'])){
				foreach($data['macros'] as $mid => $macro){
					if(empty($macro['macro']) && empty($macro['value'])) unset($data['macros'][$mid]);
				}
				self::validate($data['macros']);
				
				if(isset($data['hosts']) || isset($data['templates'])){
					$linked = array();

					$macros_macro = zbx_objectValues($data['macros'], 'macro');

					$objectids = array_merge($hostids, $templateids);

					$sql = 'SELECT macro, hostid '.
						' FROM hostmacro '.
						' WHERE '.DBcondition('hostid', $objectids).
							' AND '.DBcondition('macro', $macros_macro, false, true);
					$linked_db = DBselect($sql);
					while($pair = DBfetch($linked_db)){
						$linked[] = array(
							'macro' => $pair['macro'],
							'hostid' => $pair['hostid']
						);
					}

					foreach($data['macros'] as $mnum => $macro){
						foreach($objectids as $onum => $hostid){

							foreach($linked as $link){
								if(($link['macro'] == $macro['macro']) && ($link['hostid'] == $hostid)) continue 2;
							}

							$values = array(
								'hostmacroid' => get_dbid('hostmacro', 'hostmacroid'),
								'hostid' => $hostid,
								'macro' => zbx_dbstr($macro['macro']),
								'value' => zbx_dbstr($macro['value'])
							);
							$sql = 'INSERT INTO hostmacro (hostmacroid, hostid, macro, value) VALUES ('. implode(', ', $values) .')';

							if(!DBexecute($sql)){
								self::exception(ZBX_API_ERROR_PARAMETERS, 'DB error');
							}
						}
					}
				}
			}

			self::EndTransaction(true, __METHOD__);

			return true;
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
 * Remove Macros from Hosts
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param array $data['groups']
 * @param array $data['hosts']
 * @param array $data['templates']
 * @return boolean
 */
	public static function massRemove($data){
		$errors = array();
		$result = true;

		$macros = zbx_objectValues($data['macros'], 'macro');

		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');

		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
		$templateids = is_null($templates) ? array() : zbx_objectValues($templates, 'templateid');


		$objectids_to_unlink = array_merge($hostids, $templateids);

		self::BeginTransaction(__METHOD__);

		$sql = 'DELETE FROM hostmacro WHERE '.DBcondition('hostid', $objectids_to_unlink).' AND '.DBcondition('macro', $macros, false, true);
		$result = DBexecute($sql);
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			// $result = self::get(array(
				// 'hostids' => $hostids,
				// 'output' => API_OUTPUT_EXTEND,
				// 'select_hosts' => API_OUTPUT_EXTEND,
				// 'nopermission' => 1));
			return $result;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Remove Macros from Hosts
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param array $data['groups']
 * @param array $data['hosts']
 * @param array $data['templates']
 * @return boolean
 */
	public static function massUpdate($data){

		try{
			$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
			$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');

			$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
			$templateids = is_null($templates) ? array() : zbx_objectValues($templates, 'templateid');

			if(isset($data['macros'])){
				self::BeginTransaction(__METHOD__);
				
				foreach($data['macros'] as $mid => $macro){
					if(empty($macro['macro']) && empty($macro['value'])) unset($data['macros'][$mid]);
				}
				
				self::validate($data['macros']);

				if(isset($data['hosts']) || isset($data['templates'])){
					$objectids = array_merge($hostids, $templateids);
					$macros = zbx_toHash($data['macros'], 'macro');

					$macros_macros = zbx_objectValues($data['macros'], 'macro');

					$sql = 'SELECT macro, hostid, value FROM hostmacro'.
						' WHERE '.DBcondition('hostid', $objectids).
						' AND '.DBcondition('macro', $macros_macros, false, true);
					$row_db = DBselect($sql);
					while($row = DBfetch($row_db)){
						$sql = 'UPDATE hostmacro SET value='.zbx_dbstr($macros[$row['macro']]['value']).
							' WHERE macro='.zbx_dbstr($row['macro']).' AND hostid='.$row['hostid'];
						if(!DBexecute($sql)){
							self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
						}
					}
				}

				self::EndTransaction(true, __METHOD__);
			}

			return true;
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
 * Get UserMacros ID by UserMacros name
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $macro_data
 * @param string $macro_data['macro']
 * @return int|boolean
 */
	public static function getGlobalMacroId($macro_data){
		$result = false;

		$sql = 'SELECT globalmacroid '.
				' FROM globalmacro '.
				' WHERE macro='.zbx_dbstr($macro_data['macro']).
					' AND '.DBin_node('globalmacroid', false);
		$res = DBselect($sql);
		if($macroid = DBfetch($res))
			$result = $macroid['globalmacroid'];

	return $result;
	}


	public static function getMacros($macros, $options){
		zbx_value2array($macros);

		$def_options = array(
			'itemid' => null,
			'triggerid' => null
		);
		$options = zbx_array_merge($def_options, $options);

		$hmacro = array();
		$obj_options = array(
			'extendoutput' => 1,
			'globalmacro' => 1,
			'nopermissions' => 1,
			'macros' => $macros
		);
		$gmacros = self::get($obj_options);

		$obj_options = array(
			'nopermissions' => 1,
			'preservekeys' => 1,
			'itemids' => $options['itemid'],
			'triggerids' => $options['triggerid']
		);
		$hosts = CHost::get($obj_options);
		$hostids = array_keys($hosts);

		$hmacros = array();
		while((count($hmacro) < count($macros)) && !empty($hosts)){
			$obj_options = array(
				'nopermissions' => 1,
				'extendoutput' => 1,
				'preservekeys' => 1,
				'macros' => $macros,
				'hostids' => $hostids
			);

			$tmacros = self::get($obj_options);
			$hmacros = zbx_array_merge($tmacros, $hmacros);

			$obj_options = array(
				'nopermissions' => 1,
				'preservekeys' => 1,
				'hostids' => $hostids
			);
			$hosts = CTemplate::get($obj_options);
			$hostids = array_keys($hosts);
		}

		$macros = zbx_array_merge($gmacros + $hmacros);

		$result = array();
		foreach($macros as $macroid => $macro){
			$result[$macro['macro']] = $macro['value'];
		}

		return $result;
	}

	public static function resolveTrigger(&$triggers){
		$single = false;
		if(isset($triggers['triggerid'])){
			$single = true;
			$triggers = array($triggers);
		}

		foreach($triggers as $num => $trigger){
			if(!isset($trigger['triggerid']) || !isset($trigger['expression'])) continue;

			if($res = preg_match_all('/'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $trigger['expression'], $arr)){
				$macros = self::getMacros($arr[1], array('triggerid' => $trigger['triggerid']));

				$search = array_keys($macros);
				$values = array_values($macros);

				$triggers[$num]['expression'] = str_replace($search, $values, $trigger['expression']);
			}
		}

		if($single) $triggers = reset($triggers);
	}


	public static function resolveItem(&$items){
		$single = false;
		if(isset($items['itemid'])){
			$single = true;
			$items = array($items);
		}

		foreach($items as $num => $item){
			if(!isset($item['itemid']) || !isset($item['key_'])) continue;

			if($res = preg_match_all('/'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $item['key_'], $arr)){
				$macros = self::getMacros($arr[1], array('itemid' => $item['itemid']));

				$search = array_keys($macros);
				$values = array_values($macros);
				$items[$num]['key_'] = str_replace($search, $values, $item['key_']);
			}
		}

		if($single) $items = $items[0];
	}
}
?>
