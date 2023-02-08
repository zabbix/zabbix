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
			'id' =>				['type' => API_ID],
			'startIndex' =>		['type' => API_INT32, 'default' => 1],
			'count' =>			['type' => API_INT32, 'default' => 100]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
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

		$scim_group_members = array_column($options['members'], 'value');

		$users = APIRPC::User()->get([
			'output' => ['userid', 'username'],
			'userids' => $scim_group_members,
			'filter' => ['userdirectoryid' => $userdirectoryid]
		]);

		if (count($users) != count($scim_group_members)) {
			self::exception(self::SCIM_ERROR_NOT_FOUND, 'No permissions to referred object or it does not exist!');
		}

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

			$this->updateProvisionedUsersGroup($memberid, $userdirectoryid);
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
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => self::SCIM_GROUP_SCHEMA],
			'displayName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'members' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED, 'fields' => [
				'display' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'value' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
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

		$scim_group_members = array_column($options['members'], 'value');

		$db_scim_group_members = DB::select('user_scim_group', [
			'output' => ['userid'],
			'filter' => ['scim_groupid' => $options['id']]
		]);

		if (count($db_scim_group_members) != count($scim_group_members)) {
			self::exception(self::SCIM_ERROR_NOT_FOUND, 'No permissions to referred object or it does not exist!');
		}

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

				$this->updateProvisionedUsersGroup($userid, $userdirectoryid);
			}
		}

		if ($users_to_remove) {
			DB::delete('user_scim_group', [
				'userid' => array_values($users_to_remove),
				'scim_groupid' =>  $options['id']
			]);

			foreach ($users_to_remove as $userid) {
				$this->updateProvisionedUsersGroup($userid, $userdirectoryid);
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
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => self::SCIM_GROUP_SCHEMA],
			'displayName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'members' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED, 'fields' => [
				'display' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'value' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!in_array(self::SCIM_GROUP_SCHEMA, $options['schemas'], true)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, 'Incorrect schema was sent in the request.');
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
			$this->updateProvisionedUsersGroup($userid, $userdirectoryid);
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
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
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
	private function setData(string $scim_groupid, string $scim_group_name, array $users): void {
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
	private function prepareData(string $scim_groupid, string $scim_group_name, array $users): array {
		$members = [];
		foreach ($users as $user) {
			$members[] = [
				'value' => $user['userid'],
				'display' => $user['username']
			];
		}

		return [
			'id' => $scim_groupid,
			'displayName' => $scim_group_name,
			'members' => $members
		];
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
	private function updateProvisionedUsersGroup(string $userid, string $userdirectoryid): void {
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

		$user_media = APIRPC::User()->get([
			'output' => ['medias'],
			'selectMedias' => ['mediatypeid', 'sendto'],
			'userids' => $userid,
			'filter' => ['userdirectoryid' => $userdirectoryid]
		]);

		APIRPC::User()->updateProvisionedUser([
			'userid' => $userid,
			'roleid' => array_key_exists('roleid', $group_rights) ? $group_rights['roleid'] : '0',
			'usrgrps' => array_key_exists('usrgrps', $group_rights) ? $group_rights['usrgrps'] : [],
			'medias' => $user_media ? $user_media[0]['medias'] : []
		]);
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
