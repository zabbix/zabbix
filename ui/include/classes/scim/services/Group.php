<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace SCIM\services;

use API as APIRPC;
use APIException;
use CAuthenticationHelper;
use CApiInputValidator;
use CProvisioning;
use DB;
use SCIM\ScimApiService;

class Group extends ScimApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'put' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'post' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'patch' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	private const SCIM_GROUP_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:Group';
	private const SCIM_LIST_RESPONSE_SCHEMA = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';
	private const SCIM_PATCH_SCEMA = 'urn:ietf:params:scim:api:messages:2.0:PatchOp';

	protected array $data = [
		'schemas' => [self::SCIM_GROUP_SCHEMA]
	];

	/**
	 * Returns information on specific group or all groups if no specific information is requested.
	 *
	 * @param array $options        Array with data from request.
	 * @param array $options['id']  Optional. SCIM group id.
	 *
	 * @return array                Returns group data necessary for GET request SCIM response.
	 */
	public function get(array $options = []): array {
		$this->validateGet($options);

		if (array_key_exists('id', $options)) {
			$db_scim_group = DB::select('scim_group', [
				'output' => ['name'],
				'scim_groupids' => $options['id']
			]);

			if (!$db_scim_group) {
				self::exception(self::SCIM_ERROR_NOT_FOUND, 'No permissions to referred object or it does not exist!');
			}

			$users = $this->getUsersByGroupIds([$options['id']]);

			$this->setData($options['id'], $db_scim_group[0]['name'], $users[$options['id']]);
		}
		elseif (array_key_exists('displayName', $options)) {
			$db_scim_group = DB::select('scim_group', [
				'output' => ['name', 'scim_groupid'],
				'filter' => ['name' => $options['displayName']]
			]);

			if (!$db_scim_group) {
				$this->data = [
					'schemas' => [self::SCIM_LIST_RESPONSE_SCHEMA],
					'Resources' => []
				];
			}
			else {
				$users = $this->getUsersByGroupIds([$db_scim_group[0]['scim_groupid']]);

				$this->setData($db_scim_group[0]['scim_groupid'], $db_scim_group[0]['name'],
					$users[$db_scim_group[0]['scim_groupid']]
				);
			}
		}
		else {
			$db_scim_groups = DB::select('scim_group', [
				'output' => ['name'],
				'preservekeys' => true
			]);
			$total_groups = count($db_scim_groups);

			$this->data = [
				'schemas' => [self::SCIM_LIST_RESPONSE_SCHEMA],
				'totalResults' => $total_groups,
				'startIndex' => max($options['startIndex'], 1),
				'itemsPerPage' => min($total_groups, max($options['count'], 0)),
				'Resources' => []
			];

			if ($db_scim_groups) {
				$groups_users = $this->getUsersByGroupIds(array_keys($db_scim_groups));

				foreach ($groups_users as $groupid => $group_users) {
					$this->data['Resources'][] = $this->prepareData(
						$groupid, $db_scim_groups[$groupid]['name'], $group_users
					);
				}
			}
		}

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if input is invalid.
	 */
	private function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'displayName' =>	['type' => API_STRING_UTF8],
			'id' =>				['type' => API_ID],
			'startIndex' =>		['type' => API_INT32, 'default' => 1],
			'count' =>			['type' => API_INT32, 'default' => 100]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, $error);
		}
	}

	/**
	 * Receives information on new SCIM group and its members. Creates new entries in 'scim_group' and
	 * 'user_scim_group' tables, updates users' user groups mapping based on the SCIM groups and SAML settings.
	 *
	 * @param array  $options                        Array with data from request.
	 * @param string $options['displayName']         SCIM group name.
	 * @param array  $options['members']             Array with SCIM group members.
	 * @param string $options['members'][]['value']  Userid.
	 *
	 * @return array                                 Returns array with data necessary for SCIM response.
	 */
	public function post(array $options): array {
		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();
		$this->validatePost($options);

		$db_scim_groups = DB::select('scim_group', [
			'output' => ['scim_groupid'],
			'filter' => ['name' => $options['displayName']]
		]);

		if ($db_scim_groups) {
			$options['id'] = $db_scim_groups[0]['scim_groupid'];
			return $this->put($options);
		}

		[$scim_groupid] = DB::insert('scim_group', [['name' => $options['displayName']]]);

		if (!$scim_groupid) {
			self::exception(self::SCIM_INTERNAL_ERROR, 'Cannot create group '.$options['displayName'].'.');
		}

		$users = [];

		if (array_key_exists('memebers', $options)) {
			$scim_group_members = array_column($options['members'], 'value');

			$users = $this->verifyUserids($scim_group_members, $userdirectoryid);

			foreach ($scim_group_members as $memberid) {
				$user_group = DB::insert('user_scim_group', [[
					'userid' => $memberid,
					'scim_groupid' => $scim_groupid
				]]);

				if (!$user_group) {
					self::exception(self::SCIM_INTERNAL_ERROR,
						'Cannot add user '.$memberid.' to group '.$options['displayName'].'.'
					);
				}

				$this->updateProvisionedUserGroups($memberid, $userdirectoryid);
			}
		}

		$this->setData($scim_groupid, $options['displayName'], $users);

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if input is invalid.
	 */
	private function validatePost(array $options): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_ALLOW_UNEXPECTED, 'fields' => [
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'displayName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'members' =>		['type' => API_OBJECTS, 'fields' => [
				'display' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'value' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, $error);
		}

		if (!in_array(self::SCIM_GROUP_SCHEMA, $options['schemas'], true)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, 'Incorrect schema was sent in the request.');
		}
	}

	/**
	 * Receives new information on the SCIM group and its members. Updates 'user_scim_group' table, updates users'
	 * user groups mapping based on the remaining SCIM groups and SAML settings.
	 *
	 * @param array  $options                        Array with data from request.
	 * @param string $options['id']                  SCIM group id.
	 * @param array  $options['members']             Array with SCIM group members.
	 * @param string $options['members'][]['value']  Userid.
	 *
	 * @return array                                 Returns array with data necessary for SCIM response.
	 */
	public function put(array $options): array {
		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();

		$this->validatePut($options);

		$db_scim_groups = DB::select('scim_group', [
			'output' => ['name'],
			'scim_groupids' => $options['id']
		]);

		if (!$db_scim_groups) {
			self::exception(self::SCIM_ERROR_NOT_FOUND, 'No permissions to referred object or it does not exist!');
		}
		$db_scim_group = $db_scim_groups[0];

		if ($options['displayName'] !== $db_scim_group['name']) {
			$scim_groupid = DB::update('scim_group', [
				'values' => ['name' => $options['displayName']],
				'where' => ['scim_groupid' => $options['id']]
			]);

			if (!$scim_groupid) {
				self::exception(self::SCIM_INTERNAL_ERROR,
					'Cannot update group '.$db_scim_group['name'].' to group '.$options['displayName'].'.'
				);
			}

			$db_scim_group['name'] = $options['displayName'];
		}

		$scim_group_members = array_column($options['members'], 'value');

		$db_scim_group_members = DB::select('user_scim_group', [
			'output' => ['userid'],
			'filter' => ['scim_groupid' => $options['id']]
		]);

		$users_to_add = array_diff($scim_group_members, array_column($db_scim_group_members, 'userid'));
		$users_to_remove = array_diff(array_column($db_scim_group_members, 'userid'), $scim_group_members);

		if($users_to_add) {
			foreach ($users_to_add as $userid) {
				$scim_user_group = DB::insert('user_scim_group', [[
					'userid' => $userid,
					'scim_groupid' => $options['id']
				]]);

				if (!$scim_user_group) {
					self::exception(self::SCIM_INTERNAL_ERROR,
						'Cannot add user '.$userid.' to group '.$options['displayName'].'.'
					);
				}

				$this->updateProvisionedUserGroups($userid, $userdirectoryid);
			}
		}

		if ($users_to_remove) {
			DB::delete('user_scim_group', [
				'userid' => array_values($users_to_remove),
				'scim_groupid' =>  $options['id']
			]);

			foreach ($users_to_remove as $userid) {
				$this->updateProvisionedUserGroups($userid, $userdirectoryid);
			}
		}

		$db_users = APIRPC::User()->get([
			'output' => ['userid', 'username'],
			'userids' => $scim_group_members,
			'filter' => ['userdirectoryid' => $userdirectoryid]
		]);

		$this->setData($options['id'], $db_scim_group['name'], $db_users);

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if input is invalid.
	 */
	private function validatePut($options) {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_ALLOW_UNEXPECTED, 'fields' => [
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'displayName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'members' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED, 'fields' => [
				'display' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'value' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, $error);
		}

		if (!in_array(self::SCIM_GROUP_SCHEMA, $options['schemas'], true)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, 'Incorrect schema was sent in the request.');
		}
	}

	/**
	 * Receives new information on the SCIM group members. Updates 'user_scim_group' table, updates users'
	 * user groups mapping based on the remaining SCIM groups and SAML settings.
	 * @param array  $options                                      Array with data from request.
	 * @param string $options['id']                                SCIM group id.
	 * @param array  $options['Operations']                        List of operations that need to be performed.
	 * @param string $options['Operations'][]['op']                Operation that needs to be performed -'add',
	 *                                                             'replace', 'remove'.
	 * @param string $options['Operations'][]['path']              On what operation should be performed, filters are
	 *                                                             not supported, only 'members' path is supported.
	 * @param array  $options['Operations'][]['value']             Array of values on which operation should be
	 *                                                             performed. If operation is 'remove' this can be
	 *                                                             omitted, in this case all members should be removed.
	 * @param string $options['Operations'][]['value'][]['value']  User id on which operation should be performed.
	 *
	 * @return array  Returns array with data necessary for SCIM response.
	 *
	 * @throws APIException
	 */
	public function patch(array $options): array {
		$this->validatePatch($options);

		$db_scim_groups = DB::select('scim_group', [
			'output' => ['name'],
			'scim_groupids' => $options['id']
		]);

		if (!$db_scim_groups) {
			self::exception(self::SCIM_ERROR_NOT_FOUND, 'No permissions to referred object or it does not exist!');
		}

		$db_users = [];
		$new_userids = [];
		$del_userids = [];
		$do_replace = false;
		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();

		foreach ($options['Operations'] as $operation) {
			if ($operation['path'] === 'displayName') {
				$scim_groupid = DB::update('scim_group', [
					'values' => ['name' => $operation['value']],
					'where' => ['scim_groupid' => $options['id']]
				]);

				if (!$scim_groupid) {
					self::exception(self::SCIM_INTERNAL_ERROR,
						'Cannot update group '.$db_scim_groups[0]['name'].' to group '.$operation['value'].'.'
					);
				}

				$db_scim_groups[0]['name'] = $operation['value'];
			}
			else if ($operation['path'] === 'members') {
				switch ($operation['op']) {
					case 'add':
						$new_userids = array_merge($new_userids, array_column($operation['value'], 'value'));

						break;

					case 'remove':
						if (!$do_replace) {
							$del_userids = array_merge($del_userids, array_column($operation['value'], 'value'));

							if (!$del_userids) {
								// Empty 'value' array for 'remove' operation should act as 'replace' operation.
								$do_replace = true;
							}
						}

						break;

					case 'replace':
						$new_userids = array_merge($new_userids, array_column($operation['value'], 'value'));
						$do_replace = true;

						break;
				}
			}
		}

		if ($new_userids || $del_userids) {
			$new_userids = array_diff($new_userids, $del_userids);

			if (!$do_replace && $new_userids) {
				$db_userids = DB::select('user_scim_group', [
					'output' => ['userid'],
					'filter' => ['scim_groupid' => $options['id']]
				]);
				$new_userids = array_diff($new_userids, array_column($db_userids, 'userid'));
			}

			$db_users = $this->verifyUserids(array_merge($new_userids, $del_userids), $userdirectoryid);
		}

		if ($do_replace) {
			DB::delete('user_scim_group', ['scim_groupid' => $options['id']]);
		}
		else if ($del_userids) {
			DB::delete('user_scim_group', ['userid' => $del_userids, 'scim_groupid' => $options['id']]);
		}

		if ($new_userids) {
			$values = [];

			foreach ($new_userids as $userid) {
				$values[] = [
					'userid' => $userid,
					'scim_groupid' => $options['id']
				];
			}

			DB::insertBatch('user_scim_group', $values);
		}

		foreach (array_column($db_users, 'userid') as $db_userid) {
			$this->updateProvisionedUserGroups($db_userid, $userdirectoryid);
		}

		$this->setData($options['id'], $db_scim_groups[0]['name'], $db_users);

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if input is invalid.
	 */
	private function validatePatch(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_ALLOW_UNEXPECTED, 'fields' => [
			'id' =>			['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'Operations' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_UNEXPECTED, 'fields' => [
				'op' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'path', 'in' => 'displayName'], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', ['replace', 'Replace'])],
									['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', ['add', 'remove', 'replace', 'Add', 'Remove', 'Replace'])]
				]],
				'path' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', ['members', 'externalId', 'displayName'])],

				'value' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'path', 'in' => 'members'], 'type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'fields' => [
										'value' =>		['type' => API_ID]
									]],
									['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY]
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, $error);
		}

		if (!in_array(self::SCIM_PATCH_SCEMA, $options['schemas'], true)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, 'Incorrect schema was sent in the request.');
		}

		foreach ($options['Operations'] as &$operation) {
			$operation['op'] = strtolower($operation['op']);
		}
	}

	/**
	 * Deletes SCIM group from 'scim_group' table. Deletes the users that belong to this group from 'user_scim_group'
	 * table. Updates users' user groups mapping based on the remaining SCIM groups and SAML settings.
	 *
	 * @param array  $options       Array with data from request.
	 * @param string $options['id]  SCIM group's ID.
	 *
	 * @return array                Returns schema parameter in the array if the deletion was successful.
	 */
	public function delete(array $options): array {
		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();

		$this->validateDelete($options);

		$db_scim_group_members = DB::select('user_scim_group', [
			'output' => ['userid'],
			'filter' => ['scim_groupid' => $options['id']]
		]);

		DB::delete('scim_group', ['scim_groupid' => $options['id']]);

		foreach (array_column($db_scim_group_members, 'userid') as $userid) {
			$this->updateProvisionedUserGroups($userid, $userdirectoryid);
		}

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array $options): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
			'id' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, $error);
		}
	}

	/**
	 * Sets $this->data parameter to a necessary value.
	 *
	 * @param string $scim_groupid
	 * @param string $scim_group_name
	 * @param array  $users            Users that belong to this group.
	 *
	 * @return void
	 */
	private function setData(string $scim_groupid, string $scim_group_name, array $users = null): void {
		$this->data += $this->prepareData($scim_groupid, $scim_group_name, $users);
	}

	/**
	 * Prepares data array as required for SCIM response.
	 *
	 * @param string $scim_groupid
	 * @param string $scim_group_name
	 * @param array  $users                Users that belong to this group.
	 * @param string $users[]['userid']
	 * @param string $users[]['username']
	 *
	 * @return array
	 *         ['id']
	 *         ['displayName']
	 *         ['members']
	 *         ['members'][]['value']
	 *         ['members'][]['display']
	 */
	private function prepareData(string $scim_groupid, string $scim_group_name, array $users = null): array {
		$data = [
			'id' => $scim_groupid,
			'displayName' => $scim_group_name
		];

		if ($users !== null) {
			$members = [];
			foreach ($users as $user) {
				$members[] = [
					'value' => $user['userid'],
					'display' => $user['username']
				];
			}

			$data['members'] = $members;
		}

		return $data;
	}

	/**
	 * Based on SCIM group id, returns all the users that are included in this group.
	 *
	 * @param array $groupids    SCIM groups' IDs.
	 *
	 * @return array    Returns array where each group has its own array of users. Groupid is key, userid is key.
	 *                  [<groupid>][<userid>]['userid']
	 *                  [<groupid>][<userid>]['username']
	 */
	private function getUsersByGroupIds(array $groupids): array {
		$db_scim_groups_members = DB::select('user_scim_group', [
			'output' => ['userid', 'scim_groupid'],
			'filter' => ['scim_groupid' => $groupids]
		]);

		if (!$db_scim_groups_members) {
			return array_fill_keys($groupids, []);
		}

		$users = APIRPC::User()->get([
			'output' => ['userid', 'username'],
			'userids' => array_column($db_scim_groups_members, 'userid'),
			'preservekeys' => true
		]);

		$users_groups = array_fill_keys($groupids, []);
		foreach ($groupids as $groupid) {
			foreach ($db_scim_groups_members as $scim_group_member) {
				if ($scim_group_member['scim_groupid'] == $groupid) {
					$users_groups[$groupid][$scim_group_member['userid']] = $users[$scim_group_member['userid']];
				}
			}
		}

		return $users_groups;
	}

	/**
	 * Checks what kind of SCIM groups user belongs to, checks the mapping of the groups in SAML settings and updates
	 * user's user group and role based on this mapping.
	 *
	 * @param string $userid
	 * @param array  $userdirectoryid
	 *
	 * @return void
	 */
	private function updateProvisionedUserGroups(string $userid, string $userdirectoryid): void {
		$provisioning = CProvisioning::forUserDirectoryId($userdirectoryid);

		$user_scim_groupids = DB::select('user_scim_group', [
			'output' => ['scim_groupid'],
			'filter' => ['userid' => $userid]
		]);

		$user_scim_group_names = DB::select('scim_group', [
			'output' => ['name'],
			'scim_groupids' => array_column($user_scim_groupids, 'scim_groupid')
		]);

		$group_rights = $provisioning->getUserGroupsAndRole(array_column($user_scim_group_names, 'name'));

		APIRPC::User()->updateProvisionedUser([
			'userid' => $userid,
			'roleid' => $group_rights['roleid'],
			'usrgrps' => $group_rights['usrgrps']
		]);
	}

	/**
	 * Verifies if provided users exist in the database.
	 *
	 * @param array $userids           User ids.
	 * @param string $userdirectoryid  User directory id to which users belong to.
	 *
	 * @return array  Returns array with users' id and username.
	 *
	 * @throws APIException
	 */
	private function verifyUserids(array $userids, string $userdirectoryid): array {
		$users = APIRPC::User()->get([
			'output' => ['userid', 'username'],
			'userids' => $userids,
			'filter' => ['userdirectoryid' => $userdirectoryid]
		]);

		if (count($users) !== count($userids)) {
			self::exception(self::SCIM_ERROR_NOT_FOUND, 'No permissions to referred object or it does not exist!');
		}

		return $users;
	}

	/**
	 * Add user to specified list of SAML/SCIM groups. Creates new entries in 'scim_group' if group do not exists.
	 * Remove user to scim group relation from 'user_scim_group' when no groups supplied but
	 * do not removes scim_groups entry if user were last related to group user.
	 *
	 * @param array $saml_group_names  Array of strings with SAML/SCIM groups names.
	 * @param int   $userid            User id.
	 */
	public static function createScimGroupsFromSamlAttributes(array $saml_group_names, string $userid): void {
		if (!$saml_group_names) {
			DB::delete('user_scim_group', ['userid' => $userid]);

			return;
		}

		$db_scim_groups = DB::select('scim_group', [
			'output' => ['scim_groupid', 'name'],
			'filter' => ['name' => $saml_group_names],
			'preservekeys' => true
		]);

		$groups_to_add = array_diff($saml_group_names, array_column($db_scim_groups, 'name'));
		$scim_groupids = array_column($db_scim_groups, 'scim_groupid');

		if ($groups_to_add) {
			$insert = [];

			foreach ($groups_to_add as $group_to_add) {
				$insert[] = ['name' => $group_to_add];
			}

			$db_newids = DB::insert('scim_group', $insert, true);
			$scim_groupids = array_merge($scim_groupids, $db_newids);
		}

		$db_users_scim_groupids = DB::select('user_scim_group', [
			'output' => ['scim_groupid'],
			'filter' => ['userid' => $userid]
		]);
		$db_users_scim_groupids = array_column($db_users_scim_groupids, 'scim_groupid');
		$user_scim_groupids_delete = array_diff($db_users_scim_groupids, $scim_groupids);
		$user_scim_groupids_add = [];

		foreach(array_diff($scim_groupids, $db_users_scim_groupids) as $scim_groupid) {
			$user_scim_groupids_add[] = [
				'userid' => $userid,
				'scim_groupid' => $scim_groupid
			];
		}

		if ($user_scim_groupids_add) {
			DB::insert('user_scim_group', $user_scim_groupids_add);
		}

		if ($user_scim_groupids_delete) {
			DB::delete('user_scim_group', [
				'userid' => $userid,
				'scim_groupid' => $user_scim_groupids_delete
			]);
		}
	}
}
