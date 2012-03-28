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
			'globalmacro'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// filter
			'filter'					=> null,
			'search'					=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,

// OutPut
			'output'					=> API_OUTPUT_REFER,
			'extendoutput'				=> null,
			'select_groups'				=> null,
			'select_hosts'				=> null,
			'select_templates'			=> null,
			'countOutput'				=> null,
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

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else if(!is_null($options['editable']) && !is_null($options['globalmacro'])){
			return array();
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


// search
		if(is_array($options['search'])){
			zbx_db_search('hostmacro hm', $options, $sql_parts);
			zbx_db_search('globalmacro gm', $options, $sql_parts_global);
		}

// filter
		if(is_array($options['filter'])){
			if(isset($options['filter']['macro'])){
				zbx_value2array($options['filter']['macro']);

				$sql_parts['where'][] = DBcondition('hm.macro', $options['filter']['macro'], null, true);
				$sql_parts_global['where'][] = DBcondition('gm.macro', $options['filter']['macro'], null, true);
			}
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['macros'] = 'hm.*';
			$sql_parts_global['select']['macros'] = 'gm.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT hm.hostmacroid) as rowscount');
			$sql_parts_global['select'] = array('count(DISTINCT gm.globalmacroid) as rowscount');
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
				if($options['countOutput']){
					$result = $macro['rowscount'];
				}
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
				if($options['countOutput']){
					$result = $macro['rowscount'];
				}
				else{
					$hostmacroids[$macro['hostmacroid']] = $macro['hostmacroid'];

					if($options['output'] == API_OUTPUT_SHORTEN){
						$result[$macro['hostmacroid']] = $macro['hostmacroid'];
					}
					else{
						$hostids[$macro['hostid']] = $macro['hostid'];

						if(!isset($result[$macro['hostmacroid']])) $result[$macro['hostmacroid']]= array();

// Groups
						if($options['select_groups'] && !isset($result[$macro['hostmacroid']]['groups'])){
							$result[$macro['hostmacroid']]['groups'] = array();
						}
// Templates
						if($options['select_templates'] && !isset($result[$macro['hostmacroid']]['templates'])){
							$result[$macro['hostmacroid']]['templates'] = array();
						}
// Hosts
						if($options['select_hosts'] && !isset($result[$macro['hostmacroid']]['hosts'])){
							$result[$macro['hostmacroid']]['hosts'] = array();
						}


// groupids
						if(isset($macro['groupid'])){
							if(!isset($result[$macro['hostmacroid']]['groups']))
								$result[$macro['hostmacroid']]['groups'] = array();

							$result[$macro['hostmacroid']]['groups'][] = array('groupid' => $macro['groupid']);
							unset($macro['groupid']);
						}
// templateids
						if(isset($macro['templateid'])){
							if(!isset($result[$macro['hostmacroid']]['templates']))
								$result[$macro['hostmacroid']]['templates'] = array();

							$result[$macro['hostmacroid']]['templates'][] = array('templateid' => $macro['templateid']);
							unset($macro['templateid']);
						}
// hostids
						if(isset($macro['hostid'])){
							if(!isset($result[$macro['hostmacroid']]['hosts']))
								$result[$macro['hostmacroid']]['hosts'] = array();

							$result[$macro['hostmacroid']]['hosts'][] = array('hostid' => $macro['hostid']);
						}

						$result[$macro['hostmacroid']] += $macro;
					}
				}
			}
		}

		if(!is_null($options['countOutput'])){
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
	public static function deleteHostMacro($hostmacroids){
		$hostmacroids = zbx_toArray($hostmacroids);

		try{
			self::BeginTransaction(__METHOD__);

			if(empty($hostmacroids))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ hostmacroids ]');

// permissions + existance
			$options = array(
				'hostmacroids' => $hostmacroids,
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1
			);
			$db_hmacros = self::get($options);

			foreach($hostmacroids as $hmnum => $hostmacroid){
				if(!isset($db_hmacros[$hostmacroid]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}
//--------

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
		global $USER_DETAILS;

		$macros = zbx_toArray($macros);
		$macros_macros = zbx_objectValues($macros, 'macro');
		$globalmacroids = array();

		try{
			self::BeginTransaction(__METHOD__);

// permission check
			if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}
//--

			self::validate($macros);

// Check on existing
			$options = array(
				'globalmacro' => 1,
				'filter' => array('macro' => $macros_macros ),
				'output' => API_OUTPUT_EXTEND
			);
			$existing_macros = self::get($options);
			foreach($existing_macros as $emnum => $exst_macro){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_MACRO.' [ '.$exst_macro['macro'].' ] '.S_ALREADY_EXISTS_SMALL);
			}
//--
			foreach($macros as $mnum => $macro){
				$globalmacroids[] = $globalmacroid = get_dbid('globalmacro', 'globalmacroid');

				$values = array(
					'globalmacroid' => $globalmacroid,
					'macro' => zbx_dbstr($macro['macro']),
					'value' => zbx_dbstr($macro['value'])
				);
				$sql = 'INSERT INTO globalmacro ('.implode(', ', array_keys($values)).')'.
					' VALUES ('.implode(', ', $values).')';
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

	public static function updateGlobal($globalmacros){
		global $USER_DETAILS;

		$globalmacros = zbx_toArray($globalmacros);
		$globalmacros = zbx_toHash($globalmacros, 'macro');

		$globalmacroids = array();

		try{
			self::BeginTransaction(__METHOD__);

// permission check
			if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}
//--

			self::validate($globalmacros);

// permissions + existance
			$options = array(
				'filter' => array('macro' => zbx_objectValues($globalmacros, 'macro')),
				'globalmacro' => 1,
				'editable' => 1,
				'output'=> API_OUTPUT_EXTEND
			);
			$db_gmacros = self::get($options);
			$db_gmacros = zbx_toHash($db_gmacros, 'macro');

			foreach($globalmacros as $mnum => $gmacro){
				if(!isset($db_gmacros[$gmacro['macro']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_MACROS.' [ '.$gmacro['macro'].' ] '.S_DOES_NOT_EXIST_SMALL);
			}
//--------
			$globalmacroids = zbx_objectValues($db_gmacros, 'globalmacroid');

			$data = array();
			foreach($globalmacros as $mnum => $gmacro){
				$data[] = array(
					'values'=> array('value' => $gmacro['value']),
					'where'=> array('macro='.zbx_dbstr($gmacro['macro']))
				);
			}

			$result = DB::update('globalmacro', $data);
			if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

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

		try{
			self::BeginTransaction(__METHOD__);

			if(empty($globalmacros))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty input parameter');

// permissions + existance
			$options = array(
				'filter' => array('macro' => $globalmacros),
				'globalmacro' => 1,
				'editable' => 1,
				'output'=> API_OUTPUT_EXTEND
			);
			$db_gmacros = self::get($options);
			$db_gmacros = zbx_toHash($db_gmacros, 'macro');

			foreach($globalmacros as $mnum => $gmacro){
				if(!isset($db_gmacros[$gmacro]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}
//--------
			$sql = 'DELETE FROM globalmacro WHERE '.DBcondition('macro', $globalmacros, false, true);
			if(!DBExecute($sql))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

			self::EndTransaction(true, __METHOD__);
			return array('globalmacroids' => zbx_objectValues($db_gmacros, 'globalmacroid'));
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
	private static function validate($macros){
		$tmp = array();
		foreach($macros as $mnum => $macro){
			if(isset($tmp[$macro['macro']]))
				self::exception(ZBX_API_ERROR_PARAMETERS, '['.$macro['macro'].']: not unique');
			else
				$tmp[$macro['macro']] = 1;
		}

		foreach($macros as $mnum => $macro){
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
	public static function massAdd($data) {
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : array();
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : array();

		$hostids = zbx_objectValues($hosts, 'hostid');
		$templateids = zbx_objectValues($templates, 'templateid');

		try {
			self::BeginTransaction(__METHOD__);

			if (!isset($data['macros']) || empty($data['macros'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Not set input parameter [ macros ]');
			}
			elseif (empty($hosts) && empty($templates)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Not set input parameter [ hosts ] or [ templates ]');
			}

			// Host permission
			if (!empty($hosts)) {
				$options = array(
					'hostids' => $hostids,
					'editable' => true,
					'output' => array('hostid', 'host'),
					'preservekeys' => true
				);
				$upd_hosts = CHost::get($options);
				foreach($hosts as $host) {
					if (!isset($upd_hosts[$host['hostid']])) {
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
					}
				}
			}

			// Template permission
			if (!empty($templates)) {
				$options = array(
					'templateids' => $templateids,
					'editable' => true,
					'output' => array('hostid', 'host'),
					'preservekeys' => true
				);
				$upd_templates = CTemplate::get($options);
				foreach ($templates as $template) {
					if (!isset($upd_templates[$template['templateid']])) {
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
					}
				}
			}

			// Check on existing
			$objectids = array_merge($hostids, $templateids);
			$options = array(
				'hostids' => $objectids,
				'filter' => array('macro' => zbx_objectValues($data['macros'], 'macro')),
				'output' => API_OUTPUT_EXTEND,
				'limit' => 1
			);
			$existing_macros = self::get($options);
			foreach ($existing_macros as $exst_macro) {
				if (isset($upd_hosts[$exst_macro['hostid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_MACRO.' [ '.$upd_hosts[$exst_macro['hostid']]['host'].':'.$exst_macro['macro'].' ] '.S_ALREADY_EXISTS_SMALL);
				}
				elseif (isset($upd_templates[$exst_macro['hostid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_MACRO.' [ '.$upd_templates[$exst_macro['hostid']]['host'].':'.$exst_macro['macro'].' ] '.S_ALREADY_EXISTS_SMALL);
				}
			}

			self::validate($data['macros']);

			$insertData = array();
			foreach ($data['macros'] as $macro) {
				foreach ($objectids as $hostid) {
					$insertData[] = array(
						'hostid' => $hostid,
						'macro' => $macro['macro'],
						'value' => $macro['value']
					);
				}
			}

			$hostmacroids = DB::insert('hostmacro', $insertData);

			self::EndTransaction(true, __METHOD__);

			return array('hostmacroids' => $hostmacroids);
		}
		catch (APIException $e) {
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
 * @param array $data['hostids']
 * @param array $data['templateids']
 * @return boolean
 */
	public static function massRemove($data){
		try{
			self::BeginTransaction(__METHOD__);

			$macros = zbx_toArray($data['macros'], 'macro');

			$hostids = isset($data['hostids']) ? zbx_toArray($data['hostids']) : array();
			$templateids = isset($data['templateids']) ? zbx_toArray($data['templateids']) : array();
			$objectids = array_merge($hostids, $templateids);

// Check on existing
			$options = array(
				'hostids' => $objectids,
				'templated_hosts' => 1,
				'editable' => true,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			);
			$db_objects = CHost::get($options);

			foreach($objectids as $objectid){
				if(!isset($db_objects[$objectid]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			$options = array(
				'hostids' => $objectids,
				'filter' => array('macro' => $macros),
				'nopermissions' => true,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			);
			$db_macros = self::get($options);
			$hostmacroids = array_keys($db_macros);

			DB::delete('hostmacro', array(DBcondition('hostmacroid', $hostmacroids)));

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
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : array();
		$hostids = zbx_objectValues($hosts, 'hostid');

		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : array();
		$templateids = zbx_objectValues($templates, 'templateid');

		try{
			self::BeginTransaction(__METHOD__);

			if(!isset($data['macros']) || empty($data['macros']))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Not set input parameter [ macros ]');
			else if(empty($hosts) && empty($templates))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Not set input parameter [ hosts ] or [ templates ]');

			if(!empty($hosts)){
// Host permission
				$options = array(
					'hostids' => $hostids,
					'editable' => 1,
					'output' => array('hostid', 'host'),
					'preservekeys' => 1
				);
				$upd_hosts = CHost::get($options);
				foreach($hosts as $hnum => $host){
					if(!isset($upd_hosts[$host['hostid']]))
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
//--
			}

			if(!empty($templates)){
// Template permission
				$options = array(
					'templateids' => $templateids,
					'editable' => 1,
					'output' => array('hostid', 'host'),
					'preservekeys' => 1
				);
				$upd_templates = CTemplate::get($options);
				foreach($templates as $tnum => $template){
					if(!isset($upd_templates[$template['templateid']]))
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
//--
			}

			$objectids = array_merge($hostids, $templateids);

// Check on existing
			$options = array(
				'hostids' => $objectids,
				'filter' => array('macro' => zbx_objectValues($data['macros'], 'macro')),
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND
			);
			$db_macros = self::get($options);
/*
			foreach($db_macros as $dbnum => $db_macro){
				if(!isset($existing_macros[$db_macro['macro']])) $existing_macros[$db_macro['macro']] = array();
				$existing_macros[$db_macro['macro']][$db_macro['hostid']] = $db_macro;
			}

			foreach($data['macros'] as $hmnum => $updMacro){
				foreach($objectids as $hnum => $objectid){
					if(!isset($existing_macros[$updMacro['macro']][$objectid]))
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_MACRO.' [ '.$updMacro['macro'].' ] '.S_DOES_NOT_EXIST_SMALL);
				}
			}
//*/
//--

// first we need to validate input data
			self::validate($data['macros']);
			$updateMacros = zbx_toHash($data['macros'], 'macro');

			$hostmacroids = array();
			$data_update = array();

			foreach($db_macros as $dbnum => $db_macro){
				$hostmacroids[] = $db_macro['hostmacroid'];
				$data_update[] = array(
					'values' => array('value' => $updateMacros[$db_macro['macro']]['value']),
					'where' => array('hostmacroid='.$db_macro['hostmacroid'])
				);
			}

			DB::update('hostmacro', $data_update);

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

// TODO: should be private
	public static function getMacros($macros, $options){
		zbx_value2array($macros);
		$macros = array_unique($macros);
		
		$result = array();

		$obj_options = array(
			'itemids' => isset($options['itemid']) ? $options['itemid'] : null,
			'triggerids' => isset($options['triggerid']) ? $options['triggerid'] : null,
			'nopermissions' => 1,
			'preservekeys' => 1,
			'output' => API_OUTPUT_SHORTEN,
			'templated_hosts' => true,
		);
		$hosts = CHost::get($obj_options);
		$hostids = array_keys($hosts);	
		
		do{
			$obj_options = array(
				'hostids' => $hostids,
				'macros' => $macros,
				'output' => API_OUTPUT_EXTEND,
				'nopermissions' => 1,
				'preservekeys' => 1,
			);
			$host_macros = self::get($obj_options);
			order_result($host_macros, 'hostid');

			foreach($macros as $mnum => $macro){
				foreach($host_macros as $hmnum => $hmacro){
					if($macro == $hmacro['macro']){
						$result[$macro] = $hmacro['value'];
						unset($host_macros[$hmnum], $macros[$mnum]);
						break;
					}
				}
			}

			if(!empty($macros)){
				$obj_options = array(
					'hostids' => $hostids,
					'nopermissions' => 1,
					'preservekeys' => 1,
					'output' => API_OUTPUT_SHORTEN,
				);
				$hosts = CTemplate::get($obj_options);
				$hostids = array_keys($hosts);
			}
		}while(!empty($macros) && !empty($hostids));


		if(!empty($macros)){
			$obj_options = array(
				'output' => API_OUTPUT_EXTEND,
				'globalmacro' => 1,
				'nopermissions' => 1,
				'macros' => $macros
			);
			$gmacros = self::get($obj_options);

			foreach($macros as $macro){
				foreach($gmacros as $mid => $gmacro){
					if($macro == $gmacro['macro']){
						$result[$macro] = $gmacro['value'];
						unset($gmacros[$mid]);
						break;
					}
				}
			}
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
