<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 */
class CUserGroup extends CApiService {

	protected $tableName = 'usrgrp';
	protected $tableAlias = 'g';
	protected $sortColumns = ['usrgrpid', 'name'];

	/**
	 * Get user groups.
	 *
	 * @param array  $options
	 * @param array  $options['usrgrpids']
	 * @param array  $options['userids']
	 * @param bool   $options['status']
	 * @param bool   $options['with_gui_access']
	 * @param bool   $options['selectUsers']
	 * @param int    $options['count']
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['order']
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];
		$userType = self::$userData['type'];

		$sqlParts = [
			'select'	=> ['usrgrp' => 'g.usrgrpid'],
			'from'		=> ['usrgrp' => 'usrgrp g'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
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
			'output'					=> API_OUTPUT_EXTEND,
			'selectUsers'				=> null,
			'selectRights'				=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];

		$options = zbx_array_merge($defOptions, $options);

		// permissions
		if ($userType != USER_TYPE_SUPER_ADMIN) {
			if (!$options['editable']) {
				$sqlParts['where'][] = 'g.usrgrpid IN ('.
					'SELECT uug.usrgrpid'.
					' FROM users_groups uug'.
					' WHERE uug.userid='.self::$userData['userid'].
				')';
			}
			else {
				return [];
			}
		}

		// usrgrpids
		if (!is_null($options['usrgrpids'])) {
			zbx_value2array($options['usrgrpids']);

			$sqlParts['where'][] = dbConditionInt('g.usrgrpid', $options['usrgrpids']);
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = dbConditionInt('ug.userid', $options['userids']);
			$sqlParts['where']['gug'] = 'g.usrgrpid=ug.usrgrpid';
		}

		// status
		if (!is_null($options['status'])) {
			$sqlParts['where'][] = 'g.users_status='.zbx_dbstr($options['status']);
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
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($usrgrp = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $usrgrp['rowscount'];
			}
			else {
				$result[$usrgrp['usrgrpid']] = $usrgrp;
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
	 * Create UserGroups.
	 *
	 * @param array $usrgrps
	 * @return boolean
	 */
	public function create($usrgrps) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete user groups.'));
		}

		$usrgrps = zbx_toArray($usrgrps);
		$insert = [];

		foreach ($usrgrps as $gnum => $usrgrp) {
			$usrgrpDbFields = [
				'name' => null,
			];
			if (!check_db_fields($usrgrpDbFields, $usrgrp)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for user group.'));
			}

			$userGroupExists = $this->get([
				'output' => ['usrgrpid'],
				'filter' => ['name' => $usrgrp['name']],
				'limit' => 1
			]);
			if ($userGroupExists) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group "%1$s" already exists.', $usrgrp['name']));
			}
			$insert[$gnum] = $usrgrp;
		}
		$usrgrpids = DB::insert('usrgrp', $insert);


		foreach ($usrgrps as $gnum => $usrgrp) {
			$massAdd = [];
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

		return ['usrgrpids' => $usrgrpids];
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
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete user groups.'));
		}

		$usrgrps = zbx_toArray($usrgrps);

		foreach ($usrgrps as $usrgrp) {
			// checks if usergroup id is present
			$groupDbFields = ['usrgrpid' => null];
			if (!check_db_fields($groupDbFields, $usrgrp)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for user group.'));
			}

			$usrgrp['usrgrpids'] = $usrgrp['usrgrpid'];
			unset($usrgrp['usrgrpid']);
			if (!$this->massUpdate($usrgrp)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot update group.'));
			}
		}

		return ['usrgrpids'=> zbx_objectValues($usrgrps, 'usrgrpid')];
	}

	public function massAdd($data) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete user groups.'));
		}

		$usrgrpids = array_keys(array_flip(zbx_toArray($data['usrgrpids'])));
		$userids = (isset($data['userids']) && !is_null($data['userids'])) ? zbx_toArray($data['userids']) : null;
		$rights = (isset($data['rights']) && !is_null($data['rights'])) ? zbx_toArray($data['rights']) : null;

		if (!is_null($userids)) {
			$options = [
				'usrgrpids' => $usrgrpids,
				'output' => API_OUTPUT_EXTEND,
			];
			$usrgrps = $this->get($options);
			foreach ($usrgrps as $usrgrp) {
				if ((($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED)
					|| ($usrgrp['users_status'] == GROUP_STATUS_DISABLED))
					&& uint_in_array(self::$userData['userid'], $userids)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('User cannot change status of himself'));
				}
			}

			$linkedUsers = [];
			$sql = 'SELECT usrgrpid, userid'.
				' FROM users_groups'.
				' WHERE '.dbConditionInt('usrgrpid', $usrgrpids).
				' AND '.dbConditionInt('userid', $userids);
			$linkedUsersDb = DBselect($sql);
			while ($link = DBfetch($linkedUsersDb)) {
				if (!isset($linkedUsers[$link['usrgrpid']])) $linkedUsers[$link['usrgrpid']] = [];
				$linkedUsers[$link['usrgrpid']][$link['userid']] = 1;
			}

			$usersInsert = [];
			foreach ($usrgrpids as $usrgrpid) {
				foreach ($userids as $userid) {
					if (!isset($linkedUsers[$usrgrpid][$userid])) {
						$usersInsert[] = [
							'usrgrpid' => $usrgrpid,
							'userid' => $userid,
						];
					}
				}
			}
			DB::insert('users_groups', $usersInsert);
		}

		if (!is_null($rights)) {
			$linkedRights = [];
			$sql = 'SELECT groupid,id'.
				' FROM rights'.
				' WHERE '.dbConditionInt('groupid', $usrgrpids).
					' AND '.dbConditionInt('id', zbx_objectValues($rights, 'id'));
			$linkedRightsDb = DBselect($sql);
			while ($link = DBfetch($linkedRightsDb)) {
				if (!isset($linkedRights[$link['groupid']])) $linkedRights[$link['groupid']] = [];
				$linkedRights[$link['groupid']][$link['id']] = 1;
			}

			$rightInsert = [];
			foreach ($usrgrpids as $usrgrpid) {
				foreach ($rights as $right) {
					if (!isset($linkedRights[$usrgrpid][$right['id']])) {
						$rightInsert[] = [
							'groupid' => $usrgrpid,
							'permission' => $right['permission'],
							'id' => $right['id']
						];
					}
				}
			}
			DB::insert('rights', $rightInsert);
		}

		return ['usrgrpids' => $usrgrpids];
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
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete user groups.'));
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
				$groupExists = $this->get([
					'filter' => ['name' => $data['name']],
					'output' => ['usrgrpid'],
					'limit' => 1
				]);
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
				DB::update('usrgrp', [
					'values' => $usrgrpTableUpdateData,
					'where' => ['usrgrpid' => $usrgrpid],
				]);
			}
		}

		// check that user do not add himself to a disabled user group
		// insert and delete user-userGroup links
		if (isset($data['userids'])) {
			$userids = zbx_toArray($data['userids']);

			// check whether user tries to add himself to a disabled user group
			$usrgrps = $this->get([
				'output' => ['usrgrpid', 'gui_access', 'users_status'],
				'usrgrpids' => $usrgrpids,
				'selectUsers' => ['userid'],
				'preservekeys' => true
			]);
			if (uint_in_array(self::$userData['userid'], $userids)) {
				foreach ($usrgrps as $usrgrp) {
					if (($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED)
						|| ($usrgrp['users_status'] == GROUP_STATUS_DISABLED)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('User cannot add himself to a disabled group or a group with disabled GUI access.'));
					}
				}
			}

			$userUsergroupLinksToInsert = [];
			$amount_of_usrgrps_to_unlink = [];

			foreach ($usrgrps as $usrgrpid => $usrgrp) {
				$usrgrp_userids = [];

				foreach ($usrgrp['users'] as $user) {
					$usrgrp_userids[$user['userid']] = true;
				}

				foreach ($userids as $userid) {
					if (!array_key_exists($userid, $usrgrp_userids)) {
						$userUsergroupLinksToInsert[] = [
							'usrgrpid' => $usrgrpid,
							'userid' => $userid
						];
					}
					else {
						unset($usrgrp_userids[$userid]);
					}
				}

				foreach ($usrgrp_userids as $userid => $value) {
					$amount_of_usrgrps_to_unlink[$userid] = array_key_exists($userid, $amount_of_usrgrps_to_unlink)
						? $amount_of_usrgrps_to_unlink[$userid] + 1
						: 1;
				}
			}

			// link users to user groups
			if (!empty($userUsergroupLinksToInsert)) {
				DB::insert('users_groups', $userUsergroupLinksToInsert);
			}

			if ($amount_of_usrgrps_to_unlink) {
				// Unlink users from user groups.

				$userids_to_unlink = array_keys($amount_of_usrgrps_to_unlink);

				$db_users = API::User()->get([
					'output' => ['userid', 'alias'],
					'userids' => $userids_to_unlink,
					'selectUsrgrps' => ['usrgrps']
				]);

				foreach ($db_users as $user) {
					if (count($user['usrgrps']) <= $amount_of_usrgrps_to_unlink[$user['userid']]) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('User "%1$s" cannot be without user group.', $user['alias'])
						);
					}
				}

				DB::delete('users_groups', [
					'userid' => $userids_to_unlink,
					'usrgrpid' => $usrgrpids
				]);
			}
		}

		// link rights to user groups
		// update permissions to right-userGroup links
		// unlink rights from user groups (permissions)
		if (isset($data['rights'])) {
			$rights = zbx_toArray($data['rights']);

			// get already linked rights
			$linkedRights = [];
			$sql = 'SELECT groupid,permission,id'.
				' FROM rights'.
				' WHERE '.dbConditionInt('groupid', $usrgrpids);
			$linkedRightsDb = DBselect($sql);
			while ($link = DBfetch($linkedRightsDb)) {
				if (!isset($linkedRights[$link['groupid']])) {
					$linkedRights[$link['groupid']] = [];
				}
				$linkedRights[$link['groupid']][$link['id']] = $link['permission'];
			}

			// get right-userGroup links to insert
			// get right-userGroup links to update permissions
			// get rightIds to unlink rights from user groups
			$rightUsergroupLinksToInsert = [];
			$rightUsergroupLinksToUpdate = [];
			$rightIdsToUnlink = [];
			foreach ($usrgrpids as $usrgrpid) {
				foreach ($rights as $right) {
					if (!isset($linkedRights[$usrgrpid][$right['id']])) {
						$rightUsergroupLinksToInsert[] = [
							'groupid' => $usrgrpid,
							'id' => $right['id'],
							'permission' => $right['permission'],
						];
					}
					elseif ($linkedRights[$usrgrpid][$right['id']] != $right['permission']) {
						$rightUsergroupLinksToUpdate[] = [
							'values' => ['permission' => $right['permission']],
							'where' => ['groupid' => $usrgrpid, 'id' => $right['id']],
						];
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
				DB::delete('rights', [
					'id' => $rightIdsToUnlink,
					'groupid' => $usrgrpids,
				]);
			}

			// update right-userGroup permissions
			if (!empty($rightUsergroupLinksToUpdate)) {
				DB::update('rights', $rightUsergroupLinksToUpdate);
			}
		}

		return ['usrgrpids' => $usrgrpids];
	}

	/**
	 * Delete user groups.
	 *
	 * @param array $usergroupids
	 *
	 * @return array
	 */
	public function delete(array $usergroupids) {
		$this->validateDelete($usergroupids);

		$db_operations = DBFetchArray(DBselect(
			'SELECT DISTINCT om.operationid'.
			' FROM opmessage_grp om'.
			' WHERE '.dbConditionInt('om.usrgrpid', $usergroupids)
		));

		DB::delete('opmessage_grp', ['usrgrpid' => $usergroupids]);

		// delete empty operations
		$del_operations = DBFetchArray(DBselect(
			'SELECT DISTINCT o.operationid,o.actionid'.
			' FROM operations o'.
			' WHERE '.dbConditionInt('o.operationid', zbx_objectValues($db_operations, 'operationid')).
				' AND NOT EXISTS(SELECT NULL FROM opmessage_grp omg WHERE omg.operationid=o.operationid)'.
				' AND NOT EXISTS(SELECT NULL FROM opmessage_usr omu WHERE omu.operationid=o.operationid)'
		));

		DB::delete('operations', ['operationid' => zbx_objectValues($del_operations, 'operationid')]);
		DB::delete('rights', ['groupid' => $usergroupids]);
		DB::delete('users_groups', ['usrgrpid' => $usergroupids]);
		DB::delete('usrgrp', ['usrgrpid' => $usergroupids]);

		$actionids = zbx_objectValues($del_operations, 'actionid');
		if ($actionids) {
			$this->disableActionsWithoutOperations($actionids);
		}

		return ['usrgrpids' => $usergroupids];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @throws APIException
	 *
	 * @param array $userGroupIds
	 */
	protected function validateDelete(array $userGroupIds) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete user groups.'));
		}

		if (!$userGroupIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$dbUserGroups = $this->get([
			'output' => ['usrgrpid', 'name'],
			'usrgrpids' => $userGroupIds,
			'preservekeys' => true
		]);

		// check if user group is used in scripts
		$dbScripts = API::Script()->get([
			'output' => ['scriptid', 'name', 'usrgrpid'],
			'usrgrpids' => $userGroupIds,
			'nopermissions' => true
		]);

		foreach ($dbScripts as $dbScript) {
			if ($dbScript['usrgrpid'] == 0) {
				continue;
			}

			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'User group "%1$s" is used in script "%2$s".',
				$dbUserGroups[$dbScript['usrgrpid']]['name'],
				$dbScript['name']
			));
		}

		// check if user group is used in config
		$config = select_config();

		if (isset($dbUserGroups[$config['alert_usrgrpid']])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'User group "%1$s" is used in configuration for database down messages.',
				$dbUserGroups[$config['alert_usrgrpid']]['name']
			));
		}

		// check if user group is used in users with 1 user group
		$dbUsers = API::User()->get([
			'output' => ['userid', 'usrgrpid', 'alias'],
			'usrgrpids' => $userGroupIds,
			'selectUsrgrps' => ['usrgrpid']
		]);

		foreach ($dbUsers as $dbUser) {
			$db_usrgrpids = [];

			foreach ($dbUser['usrgrps'] as $usrgrp) {
				$db_usrgrpids[] = $usrgrp['usrgrpid'];
			}

			if (!array_diff($db_usrgrpids, $userGroupIds)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('User "%1$s" cannot be without user group.', $dbUser['alias'])
				);
			}
		}
	}

	public function isWritable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get([
			'usrgrpids' => $ids,
			'editable' => true,
			'countOutput' => true
		]);

		return (count($ids) == $count);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'usrgrpid', 'userid', 'users_groups');

			$dbUsers = API::User()->get([
				'output' => $options['selectUsers'],
				'userids' => $relationMap->getRelatedIds(),
				'getAccess' => ($options['selectUsers'] == API_OUTPUT_EXTEND) ? true : null,
				'preservekeys' => true
			]);

			$result = $relationMap->mapMany($result, $dbUsers, 'users');
		}

		// adding usergroup rights
		if ($options['selectRights'] !== null && $options['selectRights'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'groupid', 'rightid', 'rights');

			if (is_array($options['selectRights'])) {
				$pk_field = $this->pk('rights');

				$output_fields = [
					$pk_field => $this->fieldId($pk_field, 'r')
				];

				foreach ($options['selectRights'] as $field) {
					if ($this->hasField($field, 'rights')) {
						$output_fields[$field] = $this->fieldId($field, 'r');
					}
				}

				$output_fields = implode(',', $output_fields);
			}
			else {
				$output_fields = 'r.*';
			}

			$db_rights = DBfetchArray(DBselect(
				'SELECT '.$output_fields.
				' FROM rights r'.
				' WHERE '.dbConditionInt('r.rightid', $relationMap->getRelatedIds()).
					((self::$userData['type'] == USER_TYPE_SUPER_ADMIN) ? '' : ' AND r.permission>'.PERM_DENY)
			));
			$db_rights = zbx_toHash($db_rights, 'rightid');

			foreach ($db_rights as &$db_right) {
				unset($db_right['rightid'], $db_right['groupid']);
			}
			unset($db_right);

			$result = $relationMap->mapMany($result, $db_rights, 'rights');
		}

		return $result;
	}

	/**
	 * Disable actions that do not have operations.
	 */
	protected function disableActionsWithoutOperations(array $actionids) {
		$actions = DBFetchArray(DBselect(
			'SELECT DISTINCT a.actionid'.
			' FROM actions a'.
			' WHERE NOT EXISTS (SELECT NULL FROM operations o WHERE o.actionid=a.actionid)'.
				' AND '.dbConditionInt('a.actionid', $actionids)
		));

		$actions_without_operations = zbx_objectValues($actions, 'actionid');
		if ($actions_without_operations) {
			$this->disableActions($actions_without_operations);
		}
	}

	/**
	 * Disable actions.
	 *
	 * @param array $actionids
	 */
	protected function disableActions(array $actionids) {
		$update = [
			'values' => ['status' => ACTION_STATUS_DISABLED],
			'where' => ['actionid' => $actionids]
		];
		DB::update('actions', $update);

		foreach($actionids as $actionid) {
			add_audit_details(AUDIT_ACTION_DISABLE, AUDIT_RESOURCE_ACTION, $actionid, '',
				_('Action disabled due to deletion of user group.'), null
			);
		}
	}
}
