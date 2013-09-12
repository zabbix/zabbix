<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing methods for operations with user groups.
 *
 * @package API
 */
class CUserGroup extends CZBXAPI {

	protected $tableName = 'usrgrp';
	protected $tableAlias = 'g';
	protected $sortColumns = array('usrgrpid', 'name');

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
		$userType = self::$userData['type'];

		$sqlParts = array(
			'select'	=> array('usrgrp' => 'g.usrgrpid'),
			'from'		=> array('usrgrp' => 'usrgrp g'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
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

		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if (USER_TYPE_SUPER_ADMIN == $userType) {
		}
		elseif (is_null($options['editable']) && (self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN)) {
			$sqlParts['where'][] = 'g.usrgrpid IN ('.
				'SELECT uug.usrgrpid'.
				' FROM users_groups uug'.
				' WHERE uug.userid='.self::$userData['userid'].
				')';
		}
		elseif (!is_null($options['editable']) && (self::$userData['type'] != USER_TYPE_SUPER_ADMIN)) {
			return array();
		}

		// usrgrpids
		if (!is_null($options['usrgrpids'])) {
			zbx_value2array($options['usrgrpids']);

			$sqlParts['where'][] = dbConditionInt('g.usrgrpid', $options['usrgrpids']);
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			$sqlParts['select']['userid'] = 'ug.userid';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = dbConditionInt('ug.userid', $options['userids']);
			$sqlParts['where']['gug'] = 'g.usrgrpid=ug.usrgrpid';
		}

		// status
		if (!is_null($options['status'])) {
			$sqlParts['where'][] = 'g.users_status='.$options['status'];
		}

		// with_gui_access
		if (!is_null($options['with_gui_access'])) {
			$sqlParts['where'][] = 'g.gui_access='.GROUP_GUI_ACCESS_ENABLED;
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('usrgrp g', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('usrgrp g', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($usrgrp = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $usrgrp['rowscount'];
			}
			else {
				if (!isset($result[$usrgrp['usrgrpid']])) {
					$result[$usrgrp['usrgrpid']]= array();
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

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
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
	 * @param array $groupData
	 * @return string|boolean
	 */
	public function getObjects($groupData) {
		$result = array();
		$usrgrpids = array();

		$res = DBselect(
				'SELECT g.usrgrpid'.
				' FROM usrgrp g'.
				' WHERE g.name='.zbx_dbstr($groupData['name']).
					andDbNode('g.usrgrpid', false)
		);
		while ($group = DBfetch($res)) {
			$usrgrpids[$group['usrgrpid']] = $group['usrgrpid'];
		}

		if (!empty($usrgrpids)) {
			$result = $this->get(array('usrgrpids' => $usrgrpids, 'output' => API_OUTPUT_EXTEND));
		}

		return $result;
	}

	public function exists($object) {
		$options = array(
			'filter' => array('name' => $object['name']),
			'output' => array('usrgrpid'),
			'nopermissions' => 1,
			'limit' => 1,
		);
		if (isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		elseif (isset($object['nodeids']))
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
			$usrgrpDbFields = array(
				'name' => null,
			);
			if (!check_db_fields($usrgrpDbFields, $usrgrp)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for user group.'));
			}

			if ($this->exists(array('name' => $usrgrp['name'], 'nodeids' => get_current_nodeid(false)))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('User group').' [ '.$usrgrp['name'].' ] '._('already exists'));
			}
			$insert[$gnum] = $usrgrp;
		}
		$usrgrpids = DB::insert('usrgrp', $insert);


		foreach ($usrgrps as $gnum => $usrgrp) {
			$massAdd = array();
			if (isset($usrgrp['userids'])) {
				$massAdd['userids'] = $usrgrp['userids'];
			}
			if (isset($usrgrp['rights'])) {
				$massAdd['rights'] = $usrgrp['rights'];
			}
			if (!empty($massAdd)) {
				$massAdd['usrgrpids'] = $usrgrpids[$gnum];
				if (!$this->massAdd($massAdd))
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot add users.'));
			}
		}

		return array('usrgrpids' => $usrgrpids);
	}

	/**
	 * Update UserGroups.
	 * Checks permissions - only super admins can update usergroups.
	 * Formats data to be used in massUpdate() method.
	 *
	 * @param array $usrgrps
	 *
	 * @return int[] array['usrgrpids'] returns passed group ids
	 */
	public function update($usrgrps) {

		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can update user groups.'));
		}

		$usrgrps = zbx_toArray($usrgrps);

		foreach ($usrgrps as $usrgrp) {
			// checks if usergroup id is present
			$groupDbFields = array('usrgrpid' => null);
			if (!check_db_fields($groupDbFields, $usrgrp)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for user group.'));
			}

			$usrgrp['usrgrpids'] = $usrgrp['usrgrpid'];
			unset($usrgrp['usrgrpid']);
			if (!$this->massUpdate($usrgrp)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot update group.'));
			}
		}

		return array('usrgrpids'=> zbx_objectValues($usrgrps, 'usrgrpid'));
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

			$linkedUsers = array();
			$sql = 'SELECT usrgrpid, userid'.
				' FROM users_groups'.
				' WHERE '.dbConditionInt('usrgrpid', $usrgrpids).
				' AND '.dbConditionInt('userid', $userids);
			$linkedUsersDb = DBselect($sql);
			while ($link = DBfetch($linkedUsersDb)) {
				if (!isset($linkedUsers[$link['usrgrpid']])) $linkedUsers[$link['usrgrpid']] = array();
				$linkedUsers[$link['usrgrpid']][$link['userid']] = 1;
			}

			$usersInsert = array();
			foreach ($usrgrpids as $usrgrpid) {
				foreach ($userids as $userid) {
					if (!isset($linkedUsers[$usrgrpid][$userid])) {
						$usersInsert[] = array(
							'usrgrpid' => $usrgrpid,
							'userid' => $userid,
						);
					}
				}
			}
			DB::insert('users_groups', $usersInsert);
		}

		if (!is_null($rights)) {
			$linkedRights = array();
			$sql = 'SELECT groupid,id'.
				' FROM rights'.
				' WHERE '.dbConditionInt('groupid', $usrgrpids);
			' AND '.dbConditionInt('id', zbx_objectValues($rights, 'id'));
			$linkedRightsDb = DBselect($sql);
			while ($link = DBfetch($linkedRightsDb)) {
				if (!isset($linkedRights[$link['groupid']])) $linkedRights[$link['groupid']] = array();
				$linkedRights[$link['groupid']][$link['id']] = 1;
			}

			$rightInsert = array();
			foreach ($usrgrpids as $usrgrpid) {
				foreach ($rights as $right) {
					if (!isset($linkedUsers[$usrgrpid][$right['id']])) {
						$rightInsert[] = array(
							'groupid' => $usrgrpid,
							'permission' => $right['permission'],
							'id' => $right['id']
						);
					}
				}
			}
			DB::insert('rights', $rightInsert);
		}

		return array('usrgrpids' => $usrgrpids);
	}


	/**
	 * Mass update user group.
	 * Checks for permissions - only super admins can change user groups.
	 * Changes name to a group if name and one user group id is provided.
	 * Links/unlinks users to user groups.
	 * Links/unlinks rights to user groups.
	 *
	 * @param array $data
	 * @param int|int[] $data['usrgrpids'] id or ids of user groups to be updated.
	 * @param string $data['name'] name to be set to a user group. Only one host group id can be passed at a time!
	 * @param null|int|int[] $data['userids'] user ids to link to given user groups. Missing user ids will be unlinked from user groups.
	 * @param null|array $data['rights'] rights to link to given user groups. Missing rights will be unlinked from user groups.
	 * @param int $data['rights']['id'] id of right.
	 * @param int $data['rights']['permission'] permission level of right.
	 *
	 * @return int[] array['usrgrpids'] returns passed user group ids
	 */
	public function massUpdate($data) {

		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can update user groups.'));
		}

		$usrgrpids = zbx_toArray($data['usrgrpids']);

		if (count($usrgrpids) == 0) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing parameter: usrgrpids.'));
		}

		// $data['name'] parameter restrictions
		if (isset($data['name'])) {
			// same name can be set only to one hostgroup
			if (count($usrgrpids) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one user group name can be changed at a time.'));
			}
			else {
				// check if there already is hostgroup with this name, except current hostgroup
				$groupExists = $this->get(array(
					'filter' => array('name' => $data['name']),
					'output' => array('usrgrpid'),
					'limit' => 1
				));
				$groupExists = reset($groupExists);
				if ($groupExists && (bccomp($groupExists['usrgrpid'], $usrgrpids[0]) != 0) ) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group "%s" already exists.', $data['name']));
				}
			}
		}

		// update usrgrp (user group) table if there is something to update
		$usrgrpTableUpdateData = $data;
		unset($usrgrpTableUpdateData['usrgrpids'], $usrgrpTableUpdateData['userids'], $usrgrpTableUpdateData['rights']);
		if (!empty($usrgrpTableUpdateData)) {
			foreach ($usrgrpids as $usrgrpid) {
				DB::update('usrgrp', array(
					'values' => $usrgrpTableUpdateData,
					'where' => array('usrgrpid' => $usrgrpid),
				));
			}
		}

		// check that user do not add himself to a disabled user group
		// insert and delete user-userGroup links
		if (isset($data['userids'])) {

			$userids = zbx_toArray($data['userids']);

			// check whether user tries to add himself to a disabled user group
			$usrgrps = $this->get(array(
				'usrgrpids' => $usrgrpids,
				'output' => API_OUTPUT_EXTEND,
			));
			if (uint_in_array(self::$userData['userid'], $userids)) {
				foreach ($usrgrps as $usrgrp) {
					if (($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED)
						|| ($usrgrp['users_status'] == GROUP_STATUS_DISABLED)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('User cannot add himself to a disabled group or a group with disabled GUI access.'));
					}
				}
			}

			// get already linked users
			$linkedUsers = array();
			$sql = 'SELECT usrgrpid,userid'.
				' FROM users_groups'.
				' WHERE '.dbConditionInt('usrgrpid', $usrgrpids);
			$linkedUsersDb = DBselect($sql);
			while ($link = DBfetch($linkedUsersDb)) {
				if (!isset($linkedUsers[$link['usrgrpid']])) {
					$linkedUsers[$link['usrgrpid']] = array();
				}
				$linkedUsers[$link['usrgrpid']][$link['userid']] = 1;
			}

			// get user-userGroup links to insert and get user ids to unlink
			$userUsergroupLinksToInsert = array();
			$userIdsToUnlink = array();
			foreach ($usrgrpids as $usrgrpid) {
				foreach ($userids as $userid) {
					if (!isset($linkedUsers[$usrgrpid][$userid])) {
						$userUsergroupLinksToInsert[] = array(
							'usrgrpid' => $usrgrpid,
							'userid' => $userid,
						);
					}
					unset($linkedUsers[$usrgrpid][$userid]);
				}
				if (isset($linkedUsers[$usrgrpid]) && !empty($linkedUsers[$usrgrpid])) {
					$userIdsToUnlink = array_merge($userIdsToUnlink, array_keys($linkedUsers[$usrgrpid]));
				}
			}

			// link users to user groups
			if (!empty($userUsergroupLinksToInsert)) {
				DB::insert('users_groups', $userUsergroupLinksToInsert);
			}

			// unlink users from user groups
			if (!empty($userIdsToUnlink)) {
				DB::delete('users_groups', array(
					'userid' => $userIdsToUnlink,
					'usrgrpid' => $usrgrpids,
				));
			}
		}

		// link rights to user groups
		// update permissions to right-userGroup links
		// unlink rights from user groups (permissions)
		if (isset($data['rights'])) {

			$rights = zbx_toArray($data['rights']);

			// get already linked rights
			$linkedRights = array();
			$sql = 'SELECT groupid,permission,id'.
				' FROM rights'.
				' WHERE '.dbConditionInt('groupid', $usrgrpids);
			$linkedRightsDb = DBselect($sql);
			while ($link = DBfetch($linkedRightsDb)) {
				if (!isset($linkedRights[$link['groupid']])) {
					$linkedRights[$link['groupid']] = array();
				}
				$linkedRights[$link['groupid']][$link['id']] = $link['permission'];
			}

			// get right-userGroup links to insert
			// get right-userGroup links to update permissions
			// get rightIds to unlink rights from user groups
			$rightUsergroupLinksToInsert = array();
			$rightUsergroupLinksToUpdate = array();
			$rightIdsToUnlink = array();
			foreach ($usrgrpids as $usrgrpid) {
				foreach ($rights as $right) {
					if (!isset($linkedRights[$usrgrpid][$right['id']])) {
						$rightUsergroupLinksToInsert[] = array(
							'groupid' => $usrgrpid,
							'id' => $right['id'],
							'permission' => $right['permission'],
						);
					}
					elseif ($linkedRights[$usrgrpid][$right['id']] != $right['permission']) {
						$rightUsergroupLinksToUpdate[] = array(
							'values' => array('permission' => $right['permission']),
							'where' => array('groupid' => $usrgrpid, 'id' => $right['id']),
						);
					}
					unset($linkedRights[$usrgrpid][$right['id']]);
				}

				if (isset($linkedRights[$usrgrpid]) && !empty($linkedRights[$usrgrpid])) {
					$rightIdsToUnlink = array_merge($rightIdsToUnlink, array_keys($linkedRights[$usrgrpid]));
				}
			}

			// link rights to user groups
			if (!empty($rightUsergroupLinksToInsert)) {
				DB::insert('rights', $rightUsergroupLinksToInsert);
			}

			// unlink rights from user groups
			if (!empty($rightIdsToUnlink)) {
				DB::delete('rights', array(
					'id' => $rightIdsToUnlink,
					'groupid' => $usrgrpids,
				));
			}

			// update right-userGroup permissions
			if (!empty($rightUsergroupLinksToUpdate)) {
				DB::update('rights', $rightUsergroupLinksToUpdate);
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
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group [%s] is used in configuration for database down messages.', $dbUsrgrps[$config['alert_usrgrpid']]['name']));
		}

		// delete action operation msg
		$operationids = array();
		$sql = 'SELECT DISTINCT om.operationid'.
			' FROM opmessage_grp om'.
			' WHERE '.dbConditionInt('om.usrgrpid', $usrgrpids);
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations))
			$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];

		DB::delete('opmessage_grp', array('usrgrpid'=>$usrgrpids));

		// delete empty operations
		$delOperationids = array();
		$sql = 'SELECT DISTINCT o.operationid'.
			' FROM operations o'.
			' WHERE '.dbConditionInt('o.operationid', $operationids).
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
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'usrgrpid', 'userid', 'users_groups');
			$users = API::User()->get(array(
				'output' => $options['selectUsers'],
				'userids' => $relationMap->getRelatedIds(),
				'getAccess' => $options['selectUsers'] == API_OUTPUT_EXTEND ? true : null,
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $users, 'users');
		}

		return $result;
	}
}
