<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
/**
 * File containing CUserGroup class for API.
 * @package API
 */
/**
 * Class containing methods for operations with UserGroups.
 */
class CUserGroup extends CZBXAPI {

	protected $tableName = 'usrgrp';

	protected $tableAlias = 'g';

	/**
	 * Get UserGroups
	 *
	 * @param array $options
	 * @param array $options['nodeids'] Node IDs
	 * @param array $options['usrgrpids'] UserGroup IDs
	 * @param array $options['userids'] User IDs
	 * @param boolean $options['status']
	 * @param boolean $options['with_gui_access']
	 * @param boolean $options['selectUsers']
	 * @param int $options['count']
	 * @param string $options['pattern']
	 * @param int $options['limit'] limit selection
	 * @param string $options['order']
	 * @return array
	 */
	public function get($options = array()) {
		$result = array();
		$user_type = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sort_columns = array('usrgrpid', 'name');

		// allowed output options for [ select_* ] params
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sql_parts = array(
			'select'	=> array('usrgrp' => 'g.usrgrpid'),
			'from'		=> array('usrgrp' => 'usrgrp g'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$def_options = array(
			'nodeids'					=> null,
			'usrgrpids'					=> null,
			'userids'					=> null,
			'status'					=> null,
			'with_gui_access'			=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'editable'					=> null,
			'output'					=> API_OUTPUT_REFER,
			'selectUsers'				=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);

		if (is_array($options['output'])) {
			unset($sql_parts['select']['usrgrp']);

			$dbTable = DB::getSchema('usrgrp');
			$sql_parts['select']['usrgrpid'] = 'g.usrgrpid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sql_parts['select'][$field] = 'g.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// permission check
		if (USER_TYPE_SUPER_ADMIN == $user_type) {
		}
		elseif (is_null($options['editable']) && (self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN)) {
			$sql_parts['where'][] = 'g.usrgrpid IN ('.
									' SELECT uug.usrgrpid'.
										' FROM users_groups uug'.
										' WHERE uug.userid='.self::$userData['userid'].
									' )';
		}
		elseif (!is_null($options['editable']) && (self::$userData['type'] != USER_TYPE_SUPER_ADMIN)) {
			return array();
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// usrgrpids
		if (!is_null($options['usrgrpids'])) {
			zbx_value2array($options['usrgrpids']);

			$sql_parts['where'][] = DBcondition('g.usrgrpid', $options['usrgrpids']);
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['userid'] = 'ug.userid';
			}
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where'][] = DBcondition('ug.userid', $options['userids']);
			$sql_parts['where']['gug'] = 'g.usrgrpid=ug.usrgrpid';
		}

		// status
		if (!is_null($options['status'])) {
			$sql_parts['where'][] = 'g.users_status='.$options['status'];
		}

		// with_gui_access
		if (!is_null($options['with_gui_access'])) {
			$sql_parts['where'][] = 'g.gui_access='.GROUP_GUI_ACCESS_ENABLED;
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sql_parts['select']['usrgrp'] = 'g.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(g.usrgrpid) as rowscount');
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('usrgrp g', $options, $sql_parts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('usrgrp g', $options, $sql_parts);
		}

		// sorting
		zbx_db_sorting($sql_parts, $options, $sort_columns, 'g');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sql_parts['limit'] = $options['limit'];
		}

		$usrgrpids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if (!empty($sql_parts['select'])) {
			$sql_select .= implode(',', $sql_parts['select']);
		}
		if (!empty($sql_parts['from'])) {
			$sql_from .= implode(',', $sql_parts['from']);
		}
		if (!empty($sql_parts['where'])) {
			$sql_where .= ' AND '.implode(' AND ', $sql_parts['where']);
		}
		if (!empty($sql_parts['order'])) {
			$sql_order .= ' ORDER BY '.implode(',', $sql_parts['order']);
		}
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('g.usrgrpid', $nodeids).
					$sql_where.
					$sql_order;
		$res = DBselect($sql, $sql_limit);
		while ($usrgrp = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $usrgrp['rowscount'];
			}
			else {
				$usrgrpids[$usrgrp['usrgrpid']] = $usrgrp['usrgrpid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$usrgrp['usrgrpid']] = array('usrgrpid' => $usrgrp['usrgrpid']);
				}
				else {
					if (!isset($result[$usrgrp['usrgrpid']])) {
						$result[$usrgrp['usrgrpid']]= array();
					}
					if (!is_null($options['selectUsers']) && !isset($result[$usrgrp['usrgrpid']]['users'])) {
						$result[$usrgrp['usrgrpid']]['users'] = array();
					}

					// groupids
					if (isset($usrgrp['userid']) && is_null($options['selectUsers'])) {
						if (!isset($result[$usrgrp['usrgrpid']]['users'])) {
							$result[$usrgrp['usrgrpid']]['users'] = array();
						}
						$result[$usrgrp['usrgrpid']]['users'][] = array('userid' => $usrgrp['userid']);
						unset($usrgrp['userid']);
					}
					$result[$usrgrp['usrgrpid']] += $usrgrp;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		// adding users
		if (!is_null($options['selectUsers']) && str_in_array($options['selectUsers'], $subselects_allowed_outputs)) {
			$obj_params = array(
				'output' => $options['selectUsers'],
				'usrgrpids' => $usrgrpids,
				'getAccess' => $options['selectUsers'] == API_OUTPUT_EXTEND ? true : null,
				'preservekeys' => true
			);
			$users = API::User()->get($obj_params);
			foreach ($users as $user) {
				$uusrgrps = $user['usrgrps'];
				unset($user['usrgrps']);
				foreach ($uusrgrps as $usrgrp) {
					$result[$usrgrp['usrgrpid']]['users'][] = $user;
				}
			}
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

/**
 * Get UserGroup ID by UserGroup name.
 *
 * @param array $group_data
 * @return string|boolean
 */
	public function getObjects($group_data) {
		$result = array();
		$usrgrpids = array();

		$sql = 'SELECT g.usrgrpid '.
				' FROM usrgrp g '.
				' WHERE g.name='.zbx_dbstr($group_data['name']).
					' AND '.DBin_node('g.usrgrpid', false);
		$res = DBselect($sql);
		while ($group = DBfetch($res)) {
			$usrgrpids[$group['usrgrpid']] = $group['usrgrpid'];
		}

		if (!empty($usrgrpids))
			$result = $this->get(array('usrgrpids'=>$usrgrpids, 'output' => API_OUTPUT_EXTEND));

	return $result;
	}

	public function exists($object) {
		$options = array(
			'filter' => array('name' => $object['name']),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1,
		);
		if (isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if (isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = $this->get($options);

		return !empty($objs);
	}

/**
 * Create UserGroups.
 *
 * @param array $usrgrps
 * @return boolean
 */
	public function create($usrgrps) {


		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can add user groups.'));
		}

		$usrgrps = zbx_toArray($usrgrps);
		$insert = array();

			foreach ($usrgrps as $gnum => $usrgrp) {
				$usrgrp_db_fields = array(
					'name' => null,
				);
				if (!check_db_fields($usrgrp_db_fields, $usrgrp)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for user group.'));
				}

				if ($this->exists(array('name' => $usrgrp['name'], 'nodeids' => get_current_nodeid(false)))) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('User group').' [ '.$usrgrp['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				}
				$insert[$gnum] = $usrgrp;
			}
			$usrgrpids = DB::insert('usrgrp', $insert);


			foreach ($usrgrps as $gnum => $usrgrp) {
				$mass_add = array();
				if (isset($usrgrp['userids'])) {
					$mass_add['userids'] = $usrgrp['userids'];
				}
				if (isset($usrgrp['rights'])) {
					$mass_add['rights'] = $usrgrp['rights'];
				}
				if (!empty($mass_add)) {
					$mass_add['usrgrpids'] = $usrgrpids[$gnum];
					if (!$this->massAdd($mass_add))
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot add users.'));
				}
			}

			return array('usrgrpids' => $usrgrpids);
	}

/**
 * Update UserGroups.
 *
 * @param array $usrgrps
 * @return boolean
 */
	public function update($usrgrps) {

		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can update user groups.'));
		}

		$usrgrps = zbx_toArray($usrgrps);
		$usrgrpids = zbx_objectValues($usrgrps, 'usrgrpid');

			foreach ($usrgrps as $ugnum => $usrgrp) {
				$group_db_fields = array('usrgrpid' => null);
				if (!check_db_fields($group_db_fields, $usrgrp)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for user group.'));
				}

				$mass_update = $usrgrp;
				$mass_update['usrgrpids'] = $usrgrp['usrgrpid'];
				unset($mass_update['usrgrpid']);
				if (!$this->massUpdate($mass_update))
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot update group.'));
			}

		return array('usrgrpids'=> $usrgrpids);
	}

	public function massAdd($data) {

		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can add user groups.'));
		}

		$usrgrpids = zbx_toArray($data['usrgrpids']);
		$userids = (isset($data['userids']) && !is_null($data['userids'])) ? zbx_toArray($data['userids']) : null;
		$rights = (isset($data['rights']) && !is_null($data['rights'])) ? zbx_toArray($data['rights']) : null;

			if (!is_null($userids)) {
				$options = array(
					'usrgrpids' => $usrgrpids,
					'output' => API_OUTPUT_EXTEND,
				);
				$usrgrps = $this->get($options);
				foreach ($usrgrps as $usrgrp) {
					if ((($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED)
							|| ($usrgrp['users_status'] == GROUP_STATUS_DISABLED))
							&& uint_in_array(self::$userData['userid'], $userids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('User cannot change status of himself'));
					}
				}

				$linked_users = array();
				$sql = 'SELECT usrgrpid, userid'.
					' FROM users_groups'.
					' WHERE '.DBcondition('usrgrpid', $usrgrpids).
						' AND '.DBcondition('userid', $userids);
				$linked_users_db = DBselect($sql);
				while ($link = DBfetch($linked_users_db)) {
					if (!isset($linked_users[$link['usrgrpid']])) $linked_users[$link['usrgrpid']] = array();
					$linked_users[$link['usrgrpid']][$link['userid']] = 1;
				}

				$users_insert = array();
				foreach ($usrgrpids as $usrgrpid) {
					foreach ($userids as $userid) {
						if (!isset($linked_users[$usrgrpid][$userid])) {
							$users_insert[] = array(
								'usrgrpid' => $usrgrpid,
								'userid' => $userid,
							);
						}
					}
				}
				DB::insert('users_groups', $users_insert);
			}

			if (!is_null($rights)) {
				$linked_rights = array();
				$sql = 'SELECT groupid, id'.
						' FROM rights'.
						' WHERE '.DBcondition('groupid', $usrgrpids);
							' AND '.DBcondition('id', zbx_objectValues($rights, 'id'));
				$linked_rights_db = DBselect($sql);
				while ($link = DBfetch($linked_rights_db)) {
					if (!isset($linked_rights[$link['groupid']])) $linked_rights[$link['groupid']] = array();
					$linked_rights[$link['groupid']][$link['id']] = 1;
				}

				$rights_insert = array();
				foreach ($usrgrpids as $usrgrpid) {
					foreach ($rights as $right) {
						if (!isset($linked_users[$usrgrpid][$right['id']])) {
							$rights_insert[] = array(
								'groupid' => $usrgrpid,
								'permission' => $right['permission'],
								'id' => $right['id']
							);
						}
					}
				}
				DB::insert('rights', $rights_insert);
			}

			return array('usrgrpids' => $usrgrpids);
	}

	public function massUpdate($data) {

		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can update user groups.'));
		}

		$usrgrpids = zbx_toArray($data['usrgrpids']);
		$userids = (isset($data['userids']) && !is_null($data['userids'])) ? zbx_toArray($data['userids']) : null;;
		$rights = zbx_toArray($data['rights']);

		$update = array();

			if (isset($data['name']) && count($usrgrpids)>1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Multiple Name column');
			}

			foreach ($usrgrpids as $ugnum => $usrgrpid) {
				if (isset($data['name'])) {
					$group_exists = $this->get(array(
						'filter' => array('name' => $data['name']),
						'output' => API_OUTPUT_SHORTEN,
					));
					$group_exists = reset($group_exists);
					if ($group_exists && (bccomp($group_exists['usrgrpid'],$usrgrpid) != 0) ) {
						self::exception(ZBX_API_ERROR_PARAMETERS, S_GROUP.' '.$data['name'].' '.S_ALREADY_EXISTS_SMALL);
					}
				}

				if (!empty($data)) {
					$update[] = array(
						'values' => $data,
						'where' => array('usrgrpid' => $usrgrpid),
					);
				}
			}
			DB::update('usrgrp', $update);

			if (!is_null($userids)) {
				$usrgrps = $this->get(array(
					'usrgrpids' => $usrgrpids,
					'output' => API_OUTPUT_EXTEND,
				));
				foreach ($usrgrps as $usrgrp) {
					if ((($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED)
							|| ($usrgrp['users_status'] == GROUP_STATUS_DISABLED))
							&& uint_in_array(self::$userData['userid'], $userids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('User cannot change status of himself'));
					}
				}

				$linked_users = array();
				$sql = 'SELECT usrgrpid, userid'.
						' FROM users_groups'.
						' WHERE '.DBcondition('usrgrpid', $usrgrpids);
				$linked_users_db = DBselect($sql);
				while ($link = DBfetch($linked_users_db)) {
					if (!isset($linked_users[$link['usrgrpid']])) $linked_users[$link['usrgrpid']] = array();
					$linked_users[$link['usrgrpid']][$link['userid']] = 1;
				}

				$users_insert = array();
				$userids_to_unlink = array();
				foreach ($usrgrpids as $usrgrpid) {
					foreach ($userids as $userid) {
						if (!isset($linked_users[$usrgrpid][$userid])) {
							$users_insert[] = array(
								'usrgrpid' => $usrgrpid,
								'userid' => $userid,
							);
						}
						unset($linked_users[$usrgrpid][$userid]);
					}
					if (isset($linked_users[$usrgrpid]) && !empty($linked_users[$usrgrpid])) {
						$userids_to_unlink = array_merge($userids_to_unlink, array_keys($linked_users[$usrgrpid]));
					}
				}
				if (!empty($users_insert))
					DB::insert('users_groups', $users_insert);
				if (!empty($userids_to_unlink))
					DB::delete('users_groups', array(
						'userid'=>$userids_to_unlink,
						'usrgrpid'=>$usrgrpids,
					));
			}

			if (!is_null($rights)) {
				$linked_rights = array();
				$sql = 'SELECT groupid, permission, id'.
						' FROM rights'.
						' WHERE '.DBcondition('groupid', $usrgrpids);
				$linked_rights_db = DBselect($sql);
				while ($link = DBfetch($linked_rights_db)) {
					if (!isset($linked_rights[$link['groupid']])) $linked_rights[$link['groupid']] = array();
					$linked_rights[$link['groupid']][$link['id']] = $link['permission'];
				}

				$rights_insert = array();
				$rights_update = array();
				$rights_to_unlink = array();
				foreach ($usrgrpids as $usrgrpid) {
					foreach ($rights as $rnum => $right) {
						if (!isset($linked_rights[$usrgrpid][$right['id']])) {
							$rights_insert[] = array(
								'groupid' => $usrgrpid,
								'id' => $right['id'],
								'permission' => $right['permission'],
							);
						}
						else if ($linked_rights[$usrgrpid][$right['id']] != $right['permission']) {
							$rights_update[] = array(
								'values' => array('permission' => $right['permission']),
								'where' => array('groupid' => $usrgrpid, 'id' => $right['id']),
							);
						}
						unset($linked_rights[$usrgrpid][$right['id']]);
					}

					if (isset($linked_rights[$usrgrpid]) && !empty($linked_rights[$usrgrpid])) {
						$rights_to_unlink = array_merge($rights_to_unlink, array_keys($linked_rights[$usrgrpid]));
					}
				}

				if (!empty($rights_insert)) {
					DB::insert('rights', $rights_insert);
				}

				if (!empty($rights_to_unlink)) {
					DB::delete('rights', array(
						'id'=>$rights_to_unlink,
						'groupid'=>$usrgrpids,
					));
				}

				if (!empty($rights_update)) {
					DB::update('rights', $rights_update);
				}
			}


			return array('usrgrpids' => $usrgrpids);
	}

	public function massRemove($data) {

	}

/**
 * Delete UserGroups.
 *
 * @param array $usrgrpids
 * @return boolean
 */
	public function delete($usrgrpids) {

		$usrgrpids = zbx_toArray($usrgrpids);
		$dbUsrgrps = $this->get(array(
			'output' => array('usrgrpid', 'name'),
			'usrgrpids' => $usrgrpids,
			'preservekeys' => true
		));

		if (empty($usrgrpids)) self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));

			if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
				// GETTEXT: API exception
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete user groups.'));
			}

			// check, if this user group is used in one of the scripts. If so, it cannot be deleted
			$dbScripts = API::Script()->get(array(
				'output' => array('scriptid', 'name', 'usrgrpid'),
				'usrgrpids' => $usrgrpids,
				'nopermissions' => true
			));
			if (!empty($dbScripts)) {
				foreach ($dbScripts as $snum => $script) {
					if ($script['usrgrpid'] == 0) continue;

					self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group "%1$s" is used in script "%2$s".', $dbUsrgrps[$script['usrgrpid']]['name'], $script['name']));
				}
			}

			// check, if this user group is used in the config. If so, it cannot be deleted
			$config = select_config();
			if (isset($dbUsrgrps[$config['alert_usrgrpid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,_s('User group [%s] is used in configuration for database down messages.', $dbUsrgrps[$config['alert_usrgrpid']]['name']));
			}

// delete action operation msg
			$operationids = array();
			$sql = 'SELECT DISTINCT om.operationid '.
					' FROM opmessage_grp om '.
					' WHERE '.DBcondition('om.usrgrpid', $usrgrpids);
			$dbOperations = DBselect($sql);
			while ($dbOperation = DBfetch($dbOperations))
				$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];

			DB::delete('opmessage_grp', array('usrgrpid'=>$usrgrpids));

// delete empty operations
			$delOperationids = array();
			$sql = 'SELECT DISTINCT o.operationid '.
					' FROM operations o '.
					' WHERE '.DBcondition('o.operationid', $operationids).
						' AND NOT EXISTS(SELECT om.opmessage_grpid FROM opmessage_grp om WHERE om.operationid=o.operationid)';
			$dbOperations = DBselect($sql);
			while ($dbOperation = DBfetch($dbOperations))
				$delOperationids[$dbOperation['operationid']] = $dbOperation['operationid'];

			DB::delete('operations', array('operationid'=>$delOperationids));
			DB::delete('rights', array('groupid'=>$usrgrpids));
			DB::delete('users_groups', array('usrgrpid'=>$usrgrpids));
			DB::delete('usrgrp', array('usrgrpid'=>$usrgrpids));

			return array('usrgrpids' => $usrgrpids);
	}

	public function isReadable($ids) {
		if (!is_array($ids)) return false;
		if (empty($ids)) return true;

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'usrgrpids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	public function isWritable($ids) {
		if (!is_array($ids)) return false;
		if (empty($ids)) return true;

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'usrgrpids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

}
?>
