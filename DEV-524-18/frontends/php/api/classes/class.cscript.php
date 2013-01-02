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
 * File containing CScript class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Scripts
 *
 */
class CScript extends CZBXAPI{
/**
 * Get Scripts data
 *
 * @param array $options
 * @param array $options['itemids']
 * @param array $options['hostids'] - depricated (very slow)
 * @param array $options['groupids']
 * @param array $options['triggerids']
 * @param array $options['scriptids']
 * @param boolean $options['status']
 * @param boolean $options['editable']
 * @param boolean $options['count']
 * @param string $options['pattern']
 * @param int $options['limit']
 * @param string $options['order']
 * @return array|int item data as array or false if error
 */
	public static function get($options = array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('scriptid', 'name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('scripts' => 's.scriptid'),
			'from' => array('scripts s'),
			'where' => array(),
			'order' => array(),
			'limit' => null
		);

		$def_options = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'hostids'				=> null,
			'scriptids'				=> null,
			'editable'				=> null,
			'nopermissions'			=> null,

// filter
			'filter'				=> null,
			'search'				=> null,
			'startSearch'			=> null,
			'excludeSearch'			=> null,
			'searchWildcardsEnabled'=> null,

// OutPut
			'extendoutput'			=> null,
			'output'				=> API_OUTPUT_REFER,
			'select_groups'			=> null,
			'select_hosts'			=> null,
			'countOutput'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
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
		}


// editable + PERMISSION CHECK
		if(USER_TYPE_SUPER_ADMIN == $user_type){

		}
		else if(!is_null($options['editable'])){
			return $result;
		}
		else{
// Filtering
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';

			$sql_parts['where'][] = 'hg.groupid=r.id';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = '(hg.groupid=s.groupid OR s.groupid=0)';
			$sql_parts['where'][] = '(ug.usrgrpid=s.usrgrpid OR s.usrgrpid=0)';
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			$options['groupids'][] = 0;		// include ALL groups scripts

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['scripts'] = 's.scriptid, s.groupid';
			}

			$sql_parts['where'][] = DBcondition('s.groupid', $options['groupids']);
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['hostid'] = 'hg.hostid';
			}

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where'][] = '(('.DBcondition('hg.hostid', $options['hostids']).' AND hg.groupid=s.groupid)'.
									' OR '.
									'(s.groupid=0))';
		}

// scriptids
		if(!is_null($options['scriptids'])){
			zbx_value2array($options['scriptids']);

			$sql_parts['where'][] = DBcondition('s.scriptid', $options['scriptids']);
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['scripts'] = 's.*';
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('scripts s', $options, $sql_parts);
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('scripts s', $options, $sql_parts);
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(s.scriptid) as rowscount');
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 's.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('s.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('s.*', $sql_parts['select'])){
				$sql_parts['select'][] = 's.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//----------

		$scriptids = array();

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

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('s.scriptid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($script = DBfetch($res)){
			if($options['countOutput']){
				$result = $script['rowscount'];
			}
			else{
				$scriptids[$script['scriptid']] = $script['scriptid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$script['scriptid']] = array('scriptid' => $script['scriptid']);
				}
				else{
					if(!isset($result[$script['scriptid']]))
						$result[$script['scriptid']] = array();

					if(!is_null($options['select_groups']) && !isset($result[$script['scriptid']]['groups'])){
						$result[$script['scriptid']]['groups'] = array();
					}

					if(!is_null($options['select_hosts']) && !isset($result[$script['scriptid']]['hosts'])){
						$result[$script['scriptid']]['hosts'] = array();
					}

// groupids
					if(isset($script['groupid']) && is_null($options['select_groups'])){
						if(!isset($result[$script['scriptid']]['groups']))
							$result[$script['scriptid']]['groups'] = array();

						$result[$script['scriptid']]['groups'][] = array('groupid' => $script['groupid']);
					}

// hostids
					if(isset($script['hostid']) && is_null($options['select_hosts'])){
						if(!isset($result[$script['scriptid']]['hosts']))
							$result[$script['scriptid']]['hosts'] = array();

						$result[$script['scriptid']]['hosts'][] = array('hostid' => $script['hostid']);
						unset($script['hostid']);
					}

					$result[$script['scriptid']] += $script;
				}
			}
		}

		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding groups
		if(!is_null($options['select_groups']) && str_in_array($options['select_groups'], $subselects_allowed_outputs)){
			foreach($result as $scriptid => $script){
				$obj_params = array(
					'output' => $options['select_groups'],
				);

				if($script['host_access'] == PERM_READ_WRITE){
					$obj_params['editable'] = 1;
				}

				if($script['groupid'] > 0){
					$obj_params['groupids'] = $script['groupid'];
				}

				$groups = CHostGroup::get($obj_params);

				$result[$scriptid]['groups'] = $groups;
			}
		}

// Adding hosts
		if(!is_null($options['select_hosts']) && str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
			foreach($result as $scriptid => $script){
				$obj_params = array(
					'extendoutput' => $options['select_hosts'],
				);

				if($script['host_access'] == PERM_READ_WRITE){
					$obj_params['editable'] = 1;
				}

				if($script['groupid'] > 0){
					$obj_params['groupids'] = $script['groupid'];
				}

				$hosts = CHost::get($obj_params);

				$result[$scriptid]['hosts'] = $hosts;

			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Get Script ID by host.name and item.key
 *
 * @param array $script
 * @param array $script['name']
 * @param array $script['hostid']
 * @return int|boolean
 */
	public static function getObjects($script){
		$result = array();
		$scriptids = array();

		$sql = 'SELECT scriptid '.
				' FROM scripts '.
				' WHERE '.DBin_node('scriptid').
					' AND name='.$script['name'];
		$res = DBselect($sql);
		while($script = DBfetch($res)){
			$scriptids[$script['scriptid']] = $script['scriptid'];
		}

		if(!empty($scriptids))
			$result = self::get(array('scriptids'=>$scriptids, 'extendoutput'=>1));

	return $result;
	}

/**
 * Add Scripts
 *
 * @param _array $scripts
 * @param array $script['name']
 * @param array $script['hostid']
 * @return boolean
 */
	public static function create($scripts){
		global $USER_DETAILS;

		try{
			self::BeginTransaction(__METHOD__);

			if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			$scripts = zbx_toArray($scripts);
			$scriptids = array();

			foreach($scripts as $snum => $script){
				$script_db_fields = array(
					'name' => null,
					'command' => null,
					'usrgrpid' => 0,
					'groupid' => 0,
					'host_access' => 2,
				);
				if(!check_db_fields($script_db_fields, $script)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for script');
				}

				$result = add_script($script['name'], $script['command'], $script['usrgrpid'], $script['groupid'], $script['host_access']);
				if(!$result)
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot add script');

				$scriptids[] = $result;
			}

			self::EndTransaction(true, __METHOD__);
			return array('scriptids' => $scriptids);
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
 * Update Scripts
 *
 * @param _array $scripts
 * @param array $script['name']
 * @param array $script['hostid']
 * @return boolean
 */
	public static function update($scripts){
		global $USER_DETAILS;

		$scripts = zbx_toArray($scripts);
		$scriptids = zbx_objectValues($scripts, 'scriptid');

		try{
			self::BeginTransaction(__METHOD__);

			if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			$options = array(
				'scriptids' => $scriptids,
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1
			);
			$upd_scripts = self::get($options);
			foreach($scripts as $snum => $script){
				if(!isset($upd_scripts[$script['scriptid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			foreach($scripts as $num => $script){
				$script_db_fields = $upd_scripts[$script['scriptid']];
				if(!check_db_fields($script_db_fields, $script)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for script');
				}

				$result = update_script($script['scriptid'], $script['name'], $script['command'], $script['usrgrpid'], $script['groupid'], $script['host_access']);
				if(!$result)
					self::exception(ZBX_API_ERROR_PARAMETERS, 'cannot update script');
			}

			self::EndTransaction(true, __METHOD__);
			return array('scriptids' => $scriptids);
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
 * Delete Scripts
 *
 * @param _array $scriptids
 * @param array $scriptids
 * @return boolean
 */
	public static function delete($scriptids){
		global $USER_DETAILS;

		$scriptids = zbx_toArray($scriptids);

		try{
			self::BeginTransaction(__METHOD__);

			if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			if(empty($scriptids)){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ scriptids ]');
			}

			$options = array(
				'scriptids' => $scriptids,
				'editable' => 1,
				'output' => array('scriptid'),
				'preservekeys' => 1
			);
			$del_scripts = self::get($options);

			foreach($scriptids as $snum => $scriptid){
				if(!isset($del_scripts[$scriptid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			$sql = 'DELETE FROM scripts WHERE '.DBcondition('scriptid',$scriptids);
			if(!$result = DBexecute($sql))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot delete script');

			self::EndTransaction(true, __METHOD__);
			return array('scriptids' => $scriptids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	public static function execute($data){
		global $ZBX_SERVER, $ZBX_SERVER_PORT, $ZBX_MESSAGES;

		$scriptid = $data['scriptid'];
		$hostid = $data['hostid'];

		try {
			$options = array(
				'hostids' => $hostid,
				'scriptids' => $scriptid,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true,
			);
			$alowedScripts = self::get($options);
			if(!isset($alowedScripts[$scriptid])){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			if (!$socket = fsockopen($ZBX_SERVER, $ZBX_SERVER_PORT, $errorCode, $errorMsg, ZBX_SCRIPT_TIMEOUT)) {
				// remove warnings generated by fsockopen
				foreach ($ZBX_MESSAGES as $arrayNum => $fsockErrorCheck) {
					foreach ($fsockErrorCheck as $key => $val) {
						if ($key == 'message' && strpos($val, 'fsockopen()') !== false) {
							unset($ZBX_MESSAGES[$arrayNum]);
						}
					}

				}

				switch ($errorMsg) {
					case 'Connection refused':
						$d_errorMsg = sprintf(S_NOT_RUN, $ZBX_SERVER)." ";
						break;
					case 'No route to host':
						$d_errorMsg = sprintf(S_IP_NOT_AVAILABLE, $ZBX_SERVER)." ";
						break;
					case 'Connection timed out':
						$d_errorMsg = sprintf(S_TIME_OUT, $ZBX_SERVER)." ";
						break;
					case 'php_network_getaddresses: getaddrinfo failed: Name or service not known':
						$d_errorMsg = sprintf(S_WRONG_DNS, $ZBX_SERVER)." ";
						break;
					default:
						$d_errorMsg = '';
				}

				self::exception(ZBX_API_ERROR_INTERNAL, $d_errorMsg.S_SCRIPT_ERROR_DESCRIPTION.': '.$errorMsg);
			}

			$json = new CJSON();
			$array = array(
				'request' => 'command',
				'nodeid' => id2nodeid($hostid),
				'scriptid' => $scriptid,
				'hostid' => $hostid
			);
			$dataToSend = $json->encode($array, false);

			stream_set_timeout($socket, ZBX_SCRIPT_TIMEOUT);

			if(fwrite($socket, $dataToSend) === false) {
				self::exception(ZBX_API_ERROR_INTERNAL, S_SCRIPT_ERROR_DESCRIPTION.': '.S_SCRIPT_SEND_ERROR);
			}

			$response = '';

			$pbl = ZBX_SCRIPT_BYTES_LIMIT > 8192 ? 8192 : ZBX_SCRIPT_BYTES_LIMIT; // PHP read bytes limit
			$now = time();
			$i = 0;
			while(!feof($socket)){
				$i++;
				if((time()-$now) >= ZBX_SCRIPT_TIMEOUT){
					self::exception(ZBX_API_ERROR_INTERNAL, S_SCRIPT_ERROR_DESCRIPTION.': '.S_SCRIPT_TIMEOUT_ERROR);
				}
				else if( ($i*$pbl) >= ZBX_SCRIPT_BYTES_LIMIT ){
					self::exception(ZBX_API_ERROR_INTERNAL, S_SCRIPT_ERROR_DESCRIPTION.': '.S_SCRIPT_BYTES_LIMIT_ERROR);
				}

				if(($out = fread($socket, $pbl)) !== false) {
					$response .= $out;
				}
				else{
					self::exception(ZBX_API_ERROR_INTERNAL, S_SCRIPT_ERROR_DESCRIPTION.': '.S_SCRIPT_READ_ERROR);
				}
			}

			if(strlen($response) > 0){
				$json = new CJSON();
				$rcv = $json->decode($response, true);
			}
			else{
				self::exception(ZBX_API_ERROR_INTERNAL, S_SCRIPT_ERROR_DESCRIPTION.': '.S_SCRIPT_ERROR_EMPTY_RESPONSE);
			}

			fclose($socket);
			return $rcv;
		}
		catch (APIException $e) {
			if (isset($socket) && $socket) {
				fclose($socket);
			}
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);

			return false;
		}
	}

	public static function getScriptsByHosts($hostids){
		zbx_value2array($hostids);

		$obj_params = array(
			'hostids' => $hostids,
			'preservekeys' => 1
		);
		$hosts_read_only  = CHost::get($obj_params);
		$hosts_read_only = zbx_objectValues($hosts_read_only, 'hostid');

		$obj_params = array(
			'editable' => 1,
			'hostids' => $hostids,
			'preservekeys' => 1
		);
		$hosts_read_write = CHost::get($obj_params);
		$hosts_read_write = zbx_objectValues($hosts_read_write, 'hostid');

// initialize array
		$scripts_by_host = array();
		foreach($hostids as $id => $hostid){
			$scripts_by_host[$hostid] = array();
		}
//-----
		$options = array(
			'hostids' => $hostids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		);
		$groups = CHostGroup::get($options);

		$obj_params = array(
			'groupids' => zbx_objectValues($groups, 'groupid'),
			'sortfield' => 'name',
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		);
		$scripts  = CScript::get($obj_params);

		foreach($scripts as $num => $script){
			$add_to_hosts = array();
			$hostids = zbx_objectValues($groups[$script['groupid']]['hosts'], 'hostid');

			if(PERM_READ_WRITE == $script['host_access']){
				if($script['groupid'] > 0)
					$add_to_hosts = zbx_uint_array_intersect($hosts_read_write, $hostids);
				else
					$add_to_hosts = $hosts_read_write;
			}
			else if(PERM_READ_ONLY == $script['host_access']){
				if($script['groupid'] > 0)
					$add_to_hosts = zbx_uint_array_intersect($hosts_read_only, $hostids);
				else
					$add_to_hosts = $hosts_read_only;
			}

			foreach($add_to_hosts as $id => $hostid){
				$scripts_by_host[$hostid][] = $script;
			}
		}
//SDII(count($scripts_by_host));
	return $scripts_by_host;
	}

}
?>
