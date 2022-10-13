<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

namespace SCIM;

use API as JSRPC;
use APIException;
use CAuthenticationHelper;
use CApiInputValidator;
use CApiService;
use CProvisioning;
use DB;
use Exception;

class Group extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'put' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'post' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'patch' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	private const ZBX_SCIM_ERROR_BAD_REQUEST = 400;
	private const ZBX_SCIM_ERROR_GROUP_NOT_FOUND = 404;
	private const ZBX_SCIM_METHOD_NOT_SUPPORTED = 405;
	private const ZBX_SCIM_ERROR = 500;

	private const ZBX_SCIM_GROUP_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:Group';

	protected array $data = [
		'schemas' => [self::ZBX_SCIM_GROUP_SCHEMA]
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

		if (!array_key_exists('id', $options)) {
			$db_scim_groups = DB::select('scim_groups', [
				'output' => ['name', 'scim_groupid']
			]);

			$this->data['Resources'] = [];

			if ($db_scim_groups) {
				foreach ($db_scim_groups as $db_scim_group) {
					$users = $this->getUsersByGroupId($db_scim_group['scim_groupid']);

					$this->data['Resources'][] = $this->prepareData(
						$db_scim_group['scim_groupid'], $db_scim_group['name'], $users
					);
				}
			}
		}
		else {
			$db_scim_group = DB::select('scim_groups', [
				'scim_groupids' => $options['id'],
				'output' => ['name']
			]);

			if (!$db_scim_group) {
				self::exception(self::ZBX_SCIM_ERROR_GROUP_NOT_FOUND, _('This group does not exist.'));
			}

			$users = $this->getUsersByGroupId($options['id']);

			$this->setData($options['id'], $db_scim_group[0]['name'], $users);
		}

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if input is invalid.
	 */
	private function validateGet(array $options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'id' =>			['type' => API_ID],
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Receives information on new SCIM group and its members. Creates new entries in 'scim_groups' and
	 * 'users_scim_groups' tables, updates users' user groups mapping based on the SCIM groups and SAML settings.
	 *
	 * @param array $options                         Array with data from request.
	 * @param string $options['displayName']         SCIM group name.
	 * @param array  $options['members']             Array with SCIM group members.
	 * @param string $options['members'][]['value']  Userid.
	 *
	 * @return array                                 Returns array with data necessary for SCIM response.
	 */
	public function post(array $options): array {
		$this->validatePost($options);

		$saml_settings = CAuthenticationHelper::getDefaultUserdirectory(IDP_TYPE_SAML);
		$scim_groupid = DB::insert('scim_groups', [['name' => $options['displayName']]]);

		if (!$scim_groupid) {
			self::exception(self::ZBX_SCIM_ERROR, _s('Unable to create group "%1$s".', $options['displayName']));
		}

		$memberids = array_column($options['members'], 'value');

		foreach ($memberids as $memberid) {
			$user_group = DB::insert('users_scim_groups', [[
				'userid' => $memberid,
				'scim_groupid' => $scim_groupid[0]
			]]);

			if (!$user_group) {
				self::exception(self::ZBX_SCIM_ERROR,
					_s('Unable to add user "%1$s" to group "%2$s".', $memberid, $options['displayName'])
				);
			}

			$this->updateProvisionedUsersGroup($memberid, $saml_settings);
		}

		$users = JSRPC::User()->get([
			'output' => ['userid', 'username'],
			'userids' => $memberids,
			'filter' => [
				'userdirectoryid' => $saml_settings['userdirectoryid']
			]
		]);

		$this->setData($scim_groupid[0], $options['displayName'], $users);

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if input is invalid.
	 */
	private function validatePost(array $options): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY | API_ALLOW_UNEXPECTED, 'fields' => [
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'displayName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'members' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED, 'fields' => [
				'display' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'value' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!in_array(self::ZBX_SCIM_GROUP_SCHEMA, $options['schemas'], true)) {
			self::exception(self::ZBX_SCIM_ERROR_BAD_REQUEST, _('Incorrect schema was sent in the request.'));
		}
	}

	/**
	 * Receives new information on the SCIM group and its members. Updates 'users_scim_groups' table, updates users'
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
		$this->validatePut($options);

		$saml_settings = CAuthenticationHelper::getDefaultUserdirectory(IDP_TYPE_SAML);

		$db_scim_group = DB::select('scim_groups', [
			'scim_groupids' => $options['id'],
			'output' => ['name']
		]);

		if (!$db_scim_group) {
			self::exception(self::ZBX_SCIM_ERROR_GROUP_NOT_FOUND, _('This group does not exist.'));
		}

		$scim_group_members = array_column($options['members'], 'value');

		$db_scim_group_members = DB::select('users_scim_groups', [
			'filter' => ['scim_groupid' => $options['id']],
			'output' => ['userid']
		]);

		$users_to_add = array_diff($scim_group_members, array_column($db_scim_group_members, 'userid'));
		$users_to_remove = array_diff(array_column($db_scim_group_members, 'userid'), $scim_group_members);

		if($users_to_add) {
			foreach ($users_to_add as $userid) {
				$user_group = DB::insert('users_scim_groups', [[
					'userid' => $userid,
					'scim_groupid' => $options['id']
				]]);

				if (!$user_group) {
					self::exception(self::ZBX_SCIM_ERROR,
						_s('Unable to add user "%1$s" to group "%2$s".', $userid, $options['displayName'])
					);
				}

				$this->updateProvisionedUsersGroup($userid, $saml_settings);
			}
		}
		elseif($users_to_remove) {
			DB::delete('users_scim_groups', ['userid' => array_values($users_to_remove)]);

			foreach ($users_to_remove as $userid) {
				$this->updateProvisionedUsersGroup($userid, $saml_settings);
			}
		}

		$db_users = JSRPC::User()->get([
			'output' => ['userid', 'username'],
			'userids' => $scim_group_members,
			'filter' => ['userdirectoryid' => $saml_settings['userdirectoryid']]
		]);

		$this->setData($options['id'], $db_scim_group[0]['name'], $db_users);

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if input is invalid.
	 */
	private function validatePut($options) {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY | API_ALLOW_UNEXPECTED, 'fields' => [
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'displayName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'members' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED, 'fields' => [
				'display' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'value' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!in_array(self::ZBX_SCIM_GROUP_SCHEMA, $options['schemas'], true)) {
			self::exception(self::ZBX_SCIM_ERROR_BAD_REQUEST, _('Incorrect schema was sent in the request.'));
		}
	}

	public function patch(): void {
		self::exception(self::ZBX_SCIM_METHOD_NOT_SUPPORTED, _('The endpoint does not support the provided method.'));
	}

	/**
	 * Deletes SCIM group from 'scim_group' table. Deletes the users that belong to this group from 'users_scim_groups'
	 * table. Updates users' user groups mapping based on the remaining SCIM groups and SAML settings.
	 *
	 * @param array  $options       Array with data from request.
	 * @param string $options['id]  SCIM group's ID.
	 *
	 * @return array                Returns schema parameter in the array if the deletion was successful.
	 */
	public function delete(array $options): array {
		$this->validateDelete($options);

		$db_scim_group_members = DB::select('users_scim_groups', [
			'filter' => ['scim_groupid' => $options['id']],
			'output' => ['userid']
		]);

		$deleted_group = DB::delete('scim_groups', ['scim_groupid' => $options['id']]);

		if (!$deleted_group) {
			self::exception(self::ZBX_SCIM_ERROR, _s('Unable to delete group "%1$s".', $options['id']));
		}

		$saml_settings = CAuthenticationHelper::getDefaultUserdirectory(IDP_TYPE_SAML);

		foreach (array_column($db_scim_group_members, 'userid') as $userid) {
			$this->updateProvisionedUsersGroup($userid, $saml_settings);
		}

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array $options): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'id' =>	['type' => API_ID, 'flags' => API_REQUIRED, 'uniq' => true]
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
	 * Based on SCIM group id, returns all the users that are included in this group. Checks table 'users_scim_groups'.
	 *
	 * @param string $groupid  SCIM group's ID.
	 *
	 * @return array
	 *         []['userid']
	 *         []['username']
	 */
	private function getUsersByGroupId(string $groupid): array {
		$db_scim_group_members = DB::select('users_scim_groups', [
			'filter' => ['scim_groupid' => $groupid],
			'output' => ['userid']
		]);

		$users = JSRPC::User()->get([
			'output' => ['userid', 'username'],
			'userids' => array_column($db_scim_group_members, 'userid')
		]);

		return $users;
	}

	/**
	 * Checks what kind of SCIM groups user belongs to, checks the mapping of the groups in SAML settings and updates
	 * user's user group and role based on this mapping.
	 *
	 * @param string $userid
	 * @param array  $saml_settings
	 *
	 * @return void
	 */
	private function updateProvisionedUsersGroup(string $userid, array $saml_settings): void {
		$saml_provisioning_data = new CProvisioning($saml_settings);

		$user_scim_groupids = DB::select('users_scim_groups', [
			'filter' => ['userid' => $userid],
			'output' => ['scim_groupid']
		]);

		$user_scim_group_names = DB::select('scim_groups', [
			'scim_groupids' => array_column($user_scim_groupids, 'scim_groupid'),
			'output' => ['name']
		]);

		$group_rights = $saml_provisioning_data->getUserGroupsAndRole(array_column($user_scim_group_names, 'name'));

		$user_media = JSRPC::User()->get([
			'output' => ['medias'],
			'userids' => $userid,
			'selectMedias' => ['mediatypeid', 'sendto'],
			'filter' => ['userdirectoryid' => $saml_settings['userdirectoryid']]
		]);

		JSRPC::User()->updateProvisionedUser([
			'userid' => $userid,
			'roleid' => array_key_exists('roleid', $group_rights) ? $group_rights['roleid'] : '0',
			'usrgrps' => array_key_exists('usrgrps', $group_rights) ? $group_rights['usrgrps'] : [],
			'medias' => $user_media[0]['medias']
		]);
	}
}
