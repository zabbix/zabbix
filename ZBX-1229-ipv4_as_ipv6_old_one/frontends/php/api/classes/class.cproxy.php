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
 * @param boolean $options['extendoutput'] return all fields
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
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('hostid', 'host', 'status', 'dns', 'ip'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('hosts' => 'h.hostid'),
			'from' => array('hosts h'),
			'where' => array('h.status='.HOST_STATUS_PROXY),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'proxyids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// filter
			'pattern'					=> '',
// OutPut
			'extendoutput'				=> null,
			'output'					=> API_OUTPUT_REFER,
			'count'						=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;
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
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid(false);

// proxyids
		if(!is_null($options['proxyids'])){
			zbx_value2array($options['proxyids']);
			$sql_parts['where'][] = DBcondition('h.hostid', $options['proxyids']);
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['hosts'] = 'h.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT h.hostid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(h.host) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
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

		$sql = 'SELECT DISTINCT '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('h.hostid', $nodeids).
					$sql_where.
				$sql_order;
// sdi($sql);
		$res = DBselect($sql, $sql_limit);
		while($proxy = DBfetch($res)){
			if($options['count'])
				$result = $proxy;
			else{
				// $hostids[$proxy['hostid']] = $proxy['hostid'];

				$proxy['proxyid'] = $proxy['hostid'];
				unset($proxy['hostid']);

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$proxy['proxyid']] = array('proxyid' => $proxy['proxyid']);
				}
				else{
					if(!isset($result[$proxy['proxyid']])) $result[$proxy['proxyid']]= array();

					$result[$proxy['proxyid']] += $proxy;
				}
			}
		}

		if(($options['output'] != API_OUTPUT_EXTEND) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects


// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Get Proxy ID by Proxy name
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $proxy_data
 * @param string $proxy_data['proxy']
 * @return int|boolean
 *
	public static function getObjects($proxy_data){
		$result = array();
		$proxyids = array();

		$sql = 'SELECT hostid '.
				' FROM hosts '.
				' WHERE host='.zbx_dbstr($proxy_data['proxy']).
					' AND '.DBin_node('hostid', false);

		$res = DBselect($sql);
		while($proxy = DBfetch($res)){
			$proxyids[$proxy['hostid']] = $proxy['hostid'];
		}

		if(!empty($proxyids))
			$result = self::get(array('proxyids' => $proxyids, 'extendoutput' => 1));

	return $result;
	}

/**
 * Add Host
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $hosts multidimensional array with Hosts data
 * @param string $hosts['host'] Host name.
 * @param array $hosts['groups'] array of HostGroup objects with IDs add Host to.
 * @param int $hosts['port'] Port. OPTIONAL
 * @param int $hosts['status'] Host Status. OPTIONAL
 * @param int $hosts['useip'] Use IP. OPTIONAL
 * @param string $hosts['dns'] DNS. OPTIONAL
 * @param string $hosts['ip'] IP. OPTIONAL
 * @param int $hosts['proxy_hostid'] Proxy Host ID. OPTIONAL
 * @param int $hosts['useipmi'] Use IPMI. OPTIONAL
 * @param string $hosts['ipmi_ip'] IPMAI IP. OPTIONAL
 * @param int $hosts['ipmi_port'] IPMI port. OPTIONAL
 * @param int $hosts['ipmi_authtype'] IPMI authentication type. OPTIONAL
 * @param int $hosts['ipmi_privilege'] IPMI privilege. OPTIONAL
 * @param string $hosts['ipmi_username'] IPMI username. OPTIONAL
 * @param string $hosts['ipmi_password'] IPMI password. OPTIONAL
 * @return boolean
 *
	public static function create($hosts){
		$errors = array();
		$hosts = zbx_toArray($hosts);
		$hostids = array();
		$groupids = array();
		$result = false;

// PERMISSIONS {{{
		$upd_groups = CHostGroup::get(array(
			'groupids' => $groupids,
			'editable' => 1,
			'preservekeys' => 1));
		foreach($groupids as $gnum => $groupid){
			if(!isset($upd_groups[$groupid])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'You do not have enough rights for operation');
				return false;
			}
		}
// }}} PERMISSIONS

		self::BeginTransaction(__METHOD__);
		foreach($hosts as $num => $host){
			$host_db_fields = array(
				'host' => null,
				'port' => 0,
				'status' => 0,
				'useip' => 0,
				'dns' => '',
				'ip' => '0.0.0.0',
				'proxy_hostid' => 0,
				'useipmi' => 0,
				'ipmi_ip' => '',
				'ipmi_port' => 623,
				'ipmi_authtype' => 0,
				'ipmi_privilege' => 0,
				'ipmi_username' => '',
				'ipmi_password' => '',
			);

			if(!check_db_fields($host_db_fields, $host)){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields for host [ '.$host['host'].' ]');
				break;
			}

			if(!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $host['host'])){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Incorrect characters used for Hostname [ '.$host['host'].' ]');
				break;
			}
			if(!empty($dns) && !preg_match('/^'.ZBX_PREG_DNS_FORMAT.'$/i', $host['dns'])){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Incorrect characters used for DNS [ '.$host['dns'].' ]');
				break;
			}

			$host_exists = self::getObjects(array('host' => $host['host']));
			if(!empty($host_exists)){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_HOST.' [ '.$host['host'].' ] '.S_ALREADY_EXISTS_SMALL);
				break;
			}

			$hostid = get_dbid('hosts', 'hostid');
			$hostids[] = $hostid;
			$result = DBexecute('INSERT INTO hosts (hostid, proxy_hostid, host, port, status, useip, dns, ip, disable_until, available,'.
				'useipmi,ipmi_port,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,ipmi_ip) VALUES ('.
				$hostid.','.
				$host['proxy_hostid'].','.
				zbx_dbstr($host['host']).','.
				$host['port'].','.
				$host['status'].','.
				$host['useip'].','.
				zbx_dbstr($host['dns']).','.
				zbx_dbstr($host['ip']).
				',0,'.
				HOST_AVAILABLE_UNKNOWN.','.
				$host['useipmi'].','.
				$host['ipmi_port'].','.
				$host['ipmi_authtype'].','.
				$host['ipmi_privilege'].','.
				zbx_dbstr($host['ipmi_username']).','.
				zbx_dbstr($host['ipmi_password']).','.
				zbx_dbstr($host['ipmi_ip']).')'
			);
			if(!$result){
				break;
			}


			$host['hostid'] = $hostid;
			$options = array();
			$options['hosts'] = $host;
			$options['groups'] = $host['groups'];
			if(isset($host['templates']) && !is_null($host['templates']))
				$options['templates'] = $host['templates'];
			if(isset($host['macros']) && !is_null($host['macros']))
				$options['macros'] = $host['macros'];

			$result &= CHost::massAdd($options);

		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$new_hosts = self::get(array('hostids' => $hostids, 'editable' => 1, 'extendoutput' => 1, 'nopermissions' => 1));
			return $new_hosts;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Update Host
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $hosts multidimensional array with Hosts data
 * @param string $hosts['host'] Host name.
 * @param int $hosts['port'] Port. OPTIONAL
 * @param int $hosts['status'] Host Status. OPTIONAL
 * @param int $hosts['useip'] Use IP. OPTIONAL
 * @param string $hosts['dns'] DNS. OPTIONAL
 * @param string $hosts['ip'] IP. OPTIONAL
 * @param int $hosts['proxy_hostid'] Proxy Host ID. OPTIONAL
 * @param int $hosts['useipmi'] Use IPMI. OPTIONAL
 * @param string $hosts['ipmi_ip'] IPMAI IP. OPTIONAL
 * @param int $hosts['ipmi_port'] IPMI port. OPTIONAL
 * @param int $hosts['ipmi_authtype'] IPMI authentication type. OPTIONAL
 * @param int $hosts['ipmi_privilege'] IPMI privilege. OPTIONAL
 * @param string $hosts['ipmi_username'] IPMI username. OPTIONAL
 * @param string $hosts['ipmi_password'] IPMI password. OPTIONAL
 * @param string $hosts['groups'] groups
 * @return boolean
 *
	public static function update($hosts){
		$errors = array();
		$result = true;

		$hosts = zbx_toArray($hosts);
		$hostids = zbx_objectValues($hosts, 'hostid');


		$options = array(
			'hostids' => $hostids,
			'editable' => 1,
			'preservekeys' => 1
		);
		$upd_hosts = self::get($options);
		foreach($hosts as $gnum => $host){
			if(!isset($upd_hosts[$host['hostid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'You do not have enough rights for operation');
				return false;
			}
		}

		self::BeginTransaction(__METHOD__);

		foreach($hosts as $num => $host){
			$host['hosts'] = $host;

			$result = self::massUpdate($host);
			if(!$result) break;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$upd_hosts = self::get(array('hostids' => $hostids, 'extendoutput' => 1, 'nopermissions' => 1));
			return $upd_hosts;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Mass update hosts
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $hosts multidimensional array with Hosts data
 * @param array $hosts['hosts'] Array of Host objects to update
 * @param string $hosts['fields']['host'] Host name.
 * @param array $hosts['fields']['groupids'] HostGroup IDs add Host to.
 * @param int $hosts['fields']['port'] Port. OPTIONAL
 * @param int $hosts['fields']['status'] Host Status. OPTIONAL
 * @param int $hosts['fields']['useip'] Use IP. OPTIONAL
 * @param string $hosts['fields']['dns'] DNS. OPTIONAL
 * @param string $hosts['fields']['ip'] IP. OPTIONAL
 * @param int $hosts['fields']['proxy_hostid'] Proxy Host ID. OPTIONAL
 * @param int $hosts['fields']['useipmi'] Use IPMI. OPTIONAL
 * @param string $hosts['fields']['ipmi_ip'] IPMAI IP. OPTIONAL
 * @param int $hosts['fields']['ipmi_port'] IPMI port. OPTIONAL
 * @param int $hosts['fields']['ipmi_authtype'] IPMI authentication type. OPTIONAL
 * @param int $hosts['fields']['ipmi_privilege'] IPMI privilege. OPTIONAL
 * @param string $hosts['fields']['ipmi_username'] IPMI username. OPTIONAL
 * @param string $hosts['fields']['ipmi_password'] IPMI password. OPTIONAL
 * @return boolean
 *
	public static function massUpdate($data){
		$transaction = false;

		$hosts = zbx_toArray($data['hosts']);
		$hostids = zbx_objectValues($hosts, 'hostid');

		try{
			$options = array(
				'hostids' => $hostids,
				'editable' => 1,
				'extendoutput' => 1,
				'preservekeys' => 1,
			);
			$upd_hosts = self::get($options);

			foreach($hosts as $hnum => $host){
				if(!isset($upd_hosts[$host['hostid']])){
					throw new APIException(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

// CHECK IF HOSTS HAVE AT LEAST 1 GROUP {{{
			if(isset($data['groups']) && empty($data['groups'])){
				throw new APIException(ZBX_API_ERROR_PARAMETERS, 'No groups for hosts');
			}
			$data['groups'] = zbx_toArray($data['groups']);
// }}} CHECK IF HOSTS HAVE AT LEAST 1 GROUP

			$transaction = self::BeginTransaction(__METHOD__);

// UPDATE HOSTS PROPERTIES {{{
			if(isset($data['host'])){
				if(count($hosts) > 1){
					$error = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields');
					throw new APIException($error);
				}

				$host_exists = self::getObjects(array('host' => $data['host']));
				$host_exists = reset($host_exists);
				$cur_host = reset($hosts);

				if(!empty($host_exists) && ($host_exists['hostid'] != $cur_host['hostid'])){
					$error = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_HOST.' [ '.$data['host'].' ] '.S_ALREADY_EXISTS_SMALL);
					throw new APIException($error);
				}
			}

			if(isset($data['host']) && !preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $data['host'])){
				throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Incorrect characters used for Hostname [ '.$data['host'].' ]');
			}
			if(isset($data['dns']) && !empty($dns) && !preg_match('/^'.ZBX_PREG_DNS_FORMAT.'$/i', $data['dns'])){
				throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Incorrect characters used for DNS [ '.$data['dns'].' ]');
			}

			$sql_set = array();
			if(isset($data['proxy_hostid'])) $sql_set[] = 'proxy_hostid='.$data['proxy_hostid'];
			if(isset($data['host'])) $sql_set[] = 'host='.zbx_dbstr($data['host']);
			if(isset($data['port'])) $sql_set[] = 'port='.$data['port'];
			if(isset($data['status'])) $sql_set[] = 'status='.$data['status'];
			if(isset($data['useip'])) $sql_set[] = 'useip='.$data['useip'];
			if(isset($data['dns'])) $sql_set[] = 'dns='.zbx_dbstr($data['dns']);
			if(isset($data['ip'])) $sql_set[] = 'ip='.zbx_dbstr($data['ip']);
			if(isset($data['useipmi'])) $sql_set[] = 'useipmi='.$data['useipmi'];
			if(isset($data['ipmi_port'])) $sql_set[] = 'ipmi_port='.$data['ipmi_port'];
			if(isset($data['ipmi_authtype'])) $sql_set[] = 'ipmi_authtype='.$data['ipmi_authtype'];
			if(isset($data['ipmi_privilege'])) $sql_set[] = 'ipmi_privilege='.$data['ipmi_privilege'];
			if(isset($data['ipmi_username'])) $sql_set[] = 'ipmi_username='.zbx_dbstr($data['ipmi_username']);
			if(isset($data['ipmi_password'])) $sql_set[] = 'ipmi_password='.zbx_dbstr($data['ipmi_password']);
			if(isset($data['ipmi_ip'])) $sql_set[] = 'ipmi_ip='.zbx_dbstr($data['ipmi_ip']);

			if(!empty($sql_set)){
				$sql = 'UPDATE hosts SET ' . implode(', ', $sql_set) . ' WHERE '.DBcondition('hostid', $hostids);
				$result = DBexecute($sql);
				if(isset($data['status']))
					update_host_status($hostids, $data['status']);
			}
// }}} UPDATE HOSTS PROPERTIES


// UPDATE HOSTGROUPS LINKAGE {{{
			if(isset($data['groups']) && !is_null($data['groups'])){
				$host_groups = CHostGroup::get(array('hostids' => $hostids));
				$host_groupids = zbx_objectValues($host_groups, 'groupid');
				$new_groupids = zbx_objectValues($data['groups'], 'groupid');

				$groups_to_add = array_diff($new_groupids, $host_groupids);

				if(!empty($groups_to_add)){
					$result = self::massAdd(array('hosts' => $hosts, 'groups' => $groups_to_add));
					if(!$result){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Can\'t add group');
					}
				}

				$groups_to_del = array_diff($host_groupids, $new_groupids);

				if(!empty($groups_to_del)){
					$result = self::massRemove(array('hosts' => $hosts, 'groups' => $groups_to_del));
					if(!$result){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Can\'t remove group');
					}
				}
			}
// }}} UPDATE HOSTGROUPS LINKAGE


			$data['templates_clear'] = isset($data['templates_clear']) ? zbx_toArray($data['templates_clear']) : array();
			$cleared_templateids = array();
			foreach($hostids as $hostid){
				foreach($data['templates_clear'] as $tpl){
					$result = unlink_template($hostid, $tpl['templateid'], false);
					if(!$result){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Cannot unlink template [ '.$tpl['templateid'].' ]');
					}
					$cleared_templateids[] = $tpl['templateid'];
				}
			}


// UPDATE TEMPLATE LINKAGE {{{
			if(isset($data['templates']) && !is_null($data['templates'])){
				$host_templates = CTemplate::get(array('hostids' => $hostids));
				$host_templateids = zbx_objectValues($host_templates, 'templateid');
				$new_templateids = zbx_objectValues($data['templates'], 'templateid');

				$result = self::massAdd(array('hosts' => $hosts, 'templates' => $new_templateids));
				if(!$result){
					throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Can\'t link template');
				}


				$templates_to_del = array_diff($host_templateids, $new_templateids);
				$templates_to_del = array_diff($templates_to_del, $cleared_templateids);

				if(!empty($templates_to_del)){
					$result = self::massRemove(array('hosts' => $hosts, 'templates' => $templates_to_del));
					if(!$result){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Can\'t unlink template');
					}
				}
			}
// }}} UPDATE TEMPLATE LINKAGE


// UPDATE MACROS {{{

			if(isset($data['macros']) && !is_null($data['macros'])){
				$host_macros = CUserMacro::get(array('hostids' => $hostids, 'extendoutput' => 1));

				$result = self::massAdd(array('hosts' => $hosts, 'macros' => $data['macros']));
				if(!$result){
					throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Can\'t add macro');
				}

				$macros_to_del = array();
				foreach($host_macros as $hmacro){
					$del = true;
					foreach($data['macros'] as $nmacro){
						if($hmacro['macro'] == $nmacro['macro']){
							$del = false;
							break;
						}
					}
					if($del){
						$macros_to_del[] = $hmacro;
					}
				}

				if(!empty($macros_to_del)){
					$result = self::massRemove(array('hosts' => $hosts, 'macros' => $macros_to_del));
					if(!$result){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Can\'t remove macro');
					}
				}
			}

			self::EndTransaction(true, __METHOD__);
// }}} UPDATE MACROS

			$upd_hosts = self::get(array('hostids' => $hostids, 'extendoutput' => 1, 'nopermissions' => 1));
			return $upd_hosts;

		}
		catch(APIException $e){
			if($transaction) self::EndTransaction(false, __METHOD__);

			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Add Hosts to HostGroups. All Hosts are added to all HostGroups.
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
 * @return boolean
 *
	public static function massAdd($data){
		$errors = array();
		$result = true;

		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');

		self::BeginTransaction(__METHOD__);

		if(isset($data['groups'])){
			$options = array('groups' => zbx_toArray($data['groups']), 'hosts' => zbx_toArray($data['hosts']));
			$result = CHostGroup::massAdd($options);
		}

		if(isset($data['templates'])){
			$options = array('hosts' => zbx_toArray($data['hosts']), 'templates' => zbx_toArray($data['templates']));
			$result = CTemplate::massAdd($options);
		}

		if(isset($data['macros'])){
			$options = array('hosts' => zbx_toArray($data['hosts']), 'macros' => $data['macros']);
			$result = CUserMacro::massAdd($options);
		}


		$result = self::EndTransaction($result, __METHOD__);


		if($result !== false){
			return $result;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * remove Hosts to HostGroups. All Hosts are added to all HostGroups.
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
 * @return boolean
 *
	public static function massRemove($data){
		$errors = array();
		$result = true;

		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');

		self::BeginTransaction(__METHOD__);

		if(isset($data['groups'])){
			$options = array('groups' => zbx_toArray($data['groups']), 'hosts' => zbx_toArray($data['hosts']));
			$result = CHostGroup::massRemove($options);
		}

		if(isset($data['templates'])){
			$options = array('hosts' => zbx_toArray($data['hosts']), 'templates' => zbx_toArray($data['templates']));
			$result = CTemplate::massRemove($options);
		}

		if(isset($data['macros'])){
			$options = array('hosts' => zbx_toArray($data['hosts']), 'macros' => $data['macros']);
			$result = CUserMacro::massRemove($options);
		}


		$result = self::EndTransaction($result, __METHOD__);


		if($result !== false){
			return $result;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Delete Host
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $hosts
 * @param array $hosts[0, ...]['hostid'] Host ID to delete
 * @return array|boolean
 *
	public static function delete($hosts){
		$hosts = zbx_toArray($hosts);
		$hostids = array();

		$del_hosts = self::get(array('hostids'=> zbx_objectValues($hosts, 'hostid'),
									'editable'=>1,
									'extendoutput'=>1,
									'preservekeys'=>1));
		if(empty($del_hosts)){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'Host does not exist');
			return false;
		}

		foreach($hosts as $num => $host){
			if(!isset($del_hosts[$host['hostid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}

			$hostids[] = $host['hostid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST, 'Host ['.$host['host'].']');
		}

		self::BeginTransaction(__METHOD__);
		if(!empty($hostids)){
			$result = delete_host($hostids, false);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter');
			$result = false;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return zbx_cleanHashes($del_hosts);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}
*/
}
?>
