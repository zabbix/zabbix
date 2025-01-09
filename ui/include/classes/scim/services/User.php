<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


namespace SCIM\services;

use API as APIRPC;
use APIException;
use CApiInputValidator;
use CAuthenticationHelper;
use CProvisioning;
use DB;
use Exception;
use SCIM\ScimApiService;

class User extends ScimApiService {

	public const SCIM_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:User';

	/**
	 * Returns information on specific user or all users if no specific information is requested.
	 * If user is not in database, returns only 'schemas' parameter.
	 *
	 * @param array  $options              Array with data from request.
	 * @param string $options['userName']  UserName parameter from GET request.
	 * @param string $options['id']        User id parameter from GET request URL.
	 *
	 * @return array                       Array with data necessary to create response.
	 *
	 * @throws Exception
	 */
	public function get(array $options = []): array {
		$this->validateGet($options);

		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();

		if (array_key_exists('userName', $options)) {
			$users = APIRPC::User()->get([
				'output' => ['userid', 'username', 'userdirectoryid'],
				'selectUsrgrps' => ['usrgrpid'],
				'filter' => ['username' => $options['userName']]
			]);

			if ($users && $users[0]['userdirectoryid'] != $userdirectoryid) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					'User with username '.$options["userName"].' already exists.'
				);
			}

			$user_groups = $users ? array_column($users[0]['usrgrps'], 'usrgrpid') : [];
			$disabled_groupid = CAuthenticationHelper::get(CAuthenticationHelper::DISABLED_USER_GROUPID);

			if (!$users || (count($user_groups) == 1 && $user_groups[0] == $disabled_groupid)) {
				return [];
			}

			$user = $users[0];
			$this->addScimUserAttributes($user, $options);

			return $user;
		}

		if (array_key_exists('id', $options)) {
			$users = APIRPC::User()->get([
				'output' => ['userid', 'username', 'userdirectoryid'],
				'userids' => $options['id'],
				'filter' => ['userdirectoryid' => $userdirectoryid]
			]);

			if (!$users) {
				self::exception(ZBX_API_ERROR_NO_ENTITY, 'No permissions to referred object or it does not exist!');
			}

			$user = $users[0];
			$this->addScimUserAttributes($user, $options);

			return $user;
		}

		$users = APIRPC::User()->get([
			'output' => ['userid', 'username'],
			'filter' => ['userdirectoryid' => $userdirectoryid]
		]);

		foreach ($users as &$user) {
			$user['userdirectoryid'] = $userdirectoryid;
			$this->addScimUserAttributes($user);
		}
		unset($user);

		return $users;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 */
	private function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'id' =>				['type' => API_ID],
			'userName' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
			'startIndex' =>		['type' => API_INT32, 'default' => 1],
			'count' =>			['type' => API_INT32, 'default' => 100]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Checks if requested user is in database. If user does not exist, creates new user, if user exists, updates this
	 * user.
	 *
	 * @param array  $options              Array with different attributes that might be set up in SAML settings.
	 * @param string $options['userName']  Users user name based on which user will be searched.
	 *
	 * @return array  Created or updated user data.
	 */
	public function post(array $options): array {
		$this->validatePost($options);

		$db_users = APIRPC::User()->get([
			'output' => ['userid', 'userdirectoryid'],
			'filter' => ['username' => $options['userName']]
		]);

		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();
		$provisioning = CProvisioning::forUserDirectoryId($userdirectoryid);

		$user_data['userdirectoryid'] = $userdirectoryid;
		$user_data += $provisioning->getUserAttributes($options);
		$user_data['medias'] = $provisioning->getUserMedias($options);

		if (!$db_users) {
			$user_data['username'] = $options['userName'];
			$user = APIRPC::User()->createProvisionedUser($user_data);
		}
		elseif ($db_users[0]['userdirectoryid'] == $userdirectoryid) {
			$user_data['userid'] = $db_users[0]['userid'];
			$user = APIRPC::User()->updateProvisionedUser($user_data);
		}
		else {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				'User with username '.$options['userName'].' already exists.'
			);
		}

		$this->addScimUserAttributes($user, $options);

		return $user;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 */
	private function validatePost(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_ALLOW_UNEXPECTED, 'fields' => [
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'userName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!in_array(self::SCIM_SCHEMA, $options['schemas'], true)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect schema was sent in the request.');
		}
	}

	/**
	 * Updates user in the database with newly received information. If $options['active'] parameter is false, user
	 * is deleted from the database.
	 *
	 * @param array  $options
	 * @param string $options['id']
	 * @param string $options['userName']
	 * @param bool   $options['active']    True of false, but sent as string.
	 *
	 * @return array  Array of updated user data.
	 */
	public function put(array $options): array {
		// In order to comply with Azure SCIM without flag "aadOptscim062020", attribute active value is transformed to
		// boolean.
		if (array_key_exists('active', $options) && !is_bool($options['active'])) {
			$options['active'] = strtolower($options['active']) === 'true';
		}

		$this->validatePut($options, $db_user);

		$db_user = $db_user[0];
		$user_group_names = [];
		$provisioning = CProvisioning::forUserDirectoryId($db_user['userdirectoryid']);

		// Some IdPs have group attribute, but others don't.
		if (array_key_exists('groups', $options)) {
			$user_group_names = array_column($options['groups'], 'display');
		}
		else {
			$user_groupids = DB::select('user_scim_group', [
				'output' => ['scim_groupid'],
				'filter' => ['userid' => $options['id']]
			]);

			if ($user_groupids) {
				$user_group_names = DB::select('scim_group', [
					'output' => ['name'],
					'scim_groupids' => array_column($user_groupids, 'scim_groupid')
				]);
				$user_group_names = array_column($user_group_names, 'name');
			}
		}

		// In case some IdPs do not send attribute 'active'.
		$options += [
			'active' => true
		];

		$user_data = [
			'userid' => $db_user['userid'],
			'username' => $options['userName']
		];
		$user_data += $provisioning->getUserAttributes($options);
		$user_data += $provisioning->getUserGroupsAndRole($user_group_names);
		$user_data['medias'] = $provisioning->getUserMedias($options);

		if ($options['active'] == false) {
			$user_data['usrgrps'] = [];
		}

		$user = APIRPC::User()->updateProvisionedUser($user_data);

		if ($options['active'] == false) {
			$user['userid'] = $db_user['userid'];
		}

		$this->addScimUserAttributes($user, $options);

		return $user;
	}

	/**
	 * @param array $options
	 * @param array $db_user
	 *
	 * @throws APIException
	 */
	private function validatePut(array &$options, ?array &$db_user): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_ALLOW_UNEXPECTED, 'fields' => [
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'id' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'userName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'username')],
			'active' =>		['type' => API_BOOLEAN, 'flags' => API_NOT_EMPTY],
			'groups' =>		['type' => API_OBJECTS, 'fields' => [
				'value' =>		['type' => API_ID, 'flags' => API_REQUIRED],
				'display' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!in_array(self::SCIM_SCHEMA, $options['schemas'], true)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect schema was sent in the request.');
		}

		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();
		$db_user = APIRPC::User()->get([
			'output' => ['userid', 'userdirectoryid'],
			'userids' => $options['id'],
			'filter' => ['userdirectoryid' => $userdirectoryid]
		]);

		if (!$db_user) {
			self::exception(ZBX_API_ERROR_NO_ENTITY, 'No permissions to referred object or it does not exist!');
		}
	}

	/**
	 * Updates user in the database with newly received information.
	 *
	 * @param array  $options
	 * @param string $options['id']                                User id.
	 * @param array  $options['Operations']                        List of operations that need to be performed.
	 * @param string $options['Operations'][]['op']                Operation that needs to be performed -'add',
	 *                                                             'replace', 'remove'.
	 * @param string $options['Operations'][]['path']              On what operation should be performed, filters are
	 *                                                             not supported, supported 'path' is only 'userName',
	 *                                                             'active' and the one that matches custom user
	 *                                                             attributes .
	 * @param string $options['Operations'][]['value']             Value on which operation should be
	 *                                                             performed. If operation is 'remove' this can be
	 *                                                             omitted.
	 *
	 * @return array  Array of updated user data.
	 *
	 * @throws APIException
	 */
	public function patch(array $options): array {
		// In order to comply with Azure SCIM without flag "aadOptscim062020", attribute active value is transformed to
		// boolean.
		if (array_key_exists('Operations', $options)) {
			foreach ($options['Operations'] as &$operation) {
				if (array_key_exists('path', $operation) && $operation['path'] === 'active'
						&& !is_bool($operation['value'])
				) {
					$operation['value'] = strtolower($operation['value']) === 'true';
				}
			}
			unset($operation);
		}

		$this->validatePatch($options, $db_user);

		$ins_attrs = [];
		$del_attrs = [];
		$upd_attrs = [];

		foreach ($options['Operations'] as $operation) {
			switch ($operation['op']) {
				case 'add':
					$ins_attrs[$operation['path']] = $operation['value'];
					break;

				case 'replace':
					$upd_attrs[$operation['path']] = $operation['value'];
					break;

				case 'remove':
					$del_attrs[$operation['path']] = '';
					break;
			}
		}

		$user_idp_data = array_merge($ins_attrs, $upd_attrs, $del_attrs);
		$provisioning = CProvisioning::forUserDirectoryId($db_user['userdirectoryid']);
		$new_user_data = $provisioning->getUserAttributes($user_idp_data);
		$new_user_data['medias'] = $provisioning->getUserMedias(array_merge($ins_attrs, $upd_attrs));
		$new_user_data = array_merge($db_user, $new_user_data);
		$idp_mediaids = array_column($new_user_data['medias'], 'userdirectory_mediaid', 'userdirectory_mediaid');
		$del_userdirectory_mediaids = [];

		if ($del_attrs) {
			$del_userdirectory_mediaids = array_column($provisioning->getUserMediaMappingByAttribute($del_attrs),
				'userdirectory_mediaid', 'userdirectory_mediaid'
			);
		}

		foreach ($db_user['medias'] as $db_media) {
			if ($db_media['userdirectory_mediaid'] == 0
					|| array_key_exists($db_media['userdirectory_mediaid'], $del_userdirectory_mediaids)
					|| array_key_exists($db_media['userdirectory_mediaid'], $idp_mediaids)) {
				continue;
			}

			$new_user_data['medias'][] = ['sendto' => (array) $db_media['sendto']] + $db_media;
		}

		if (array_key_exists('active', $user_idp_data)) {
			if ($user_idp_data['active'] === false) {
				// If user status 'active' is changed to false, user needs to be added to disabled group.
				$new_user_data['usrgrps'] = [];
			}
			elseif (!$db_user['roleid']) {
				// If disabled user is activated again, need to return group mapping.
				$group_names = DBfetchColumn(DBselect(
					'SELECT g.name'.
					' FROM user_scim_group ug,scim_group g'.
					' WHERE g.scim_groupid=ug.scim_groupid AND '.dbConditionId('ug.userid', [$options['id']])
				), 'name');

				$new_user_data = array_merge($new_user_data, $provisioning->getUserGroupsAndRole($group_names));
			}
		}

		$user = APIRPC::User()->updateProvisionedUser($new_user_data);
		$user = $user ?: $new_user_data;

		$this->addScimUserAttributes($user, $user_idp_data);

		return $user;
	}

	/**
	 * @param array $options
	 * @param array $db_user
	 *
	 * @throws APIException
	 */
	private function validatePatch(array &$options, array &$db_user = null): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_ALLOW_UNEXPECTED, 'fields' => [
			'id' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'Operations' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
				'op' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', ['add', 'remove', 'replace', 'Add', 'Remove', 'Replace'])],
				'path' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'value' =>      ['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'path', 'in' => implode(',', ['active'])], 'type' => API_BOOLEAN, 'flags' => API_REQUIRED],
					['if' => ['field' => 'op', 'in' => implode(',', ['remove', 'Remove'])], 'type' => API_STRING_UTF8],
					['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!in_array(ScimApiService::SCIM_PATCH_SCHEMA, $options['schemas'], true)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect schema was sent in the request.');
		}

		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();
		$db_user = APIRPC::User()->get([
			'output' => ['userid', 'name', 'surname', 'userdirectoryid', 'roleid'],
			'userids' => $options['id'],
			'selectMedias' => ['mediaid', 'mediatypeid', 'sendto', 'userdirectory_mediaid'],
			'filter' => ['userdirectoryid' => $userdirectoryid]
		]);

		if (!$db_user) {
			self::exception(ZBX_API_ERROR_NO_ENTITY, 'No permissions to referred object or it does not exist!');
		}

		$db_user = $db_user[0];

		foreach ($options['Operations'] as &$operation) {
			$operation['op'] = strtolower($operation['op']);
		}
		unset($operation);
	}

	/**
	 * Deletes requested user based on userid.
	 *
	 * @param array  $options
	 * @param string $options['id']  Userid.
	 *
	 * @return array          Returns only schema parameter, the rest of the parameters are not included.
	 */
	public function delete(array $options): array {
		$this->validateDelete($options);

		$user_data = [
			'userid' => $options['id'],
			'medias' => [],
			'usrgrps' => []
		];

		DB::delete('user_scim_group', ['userid' => $user_data['userid']]);

		return APIRPC::User()->updateProvisionedUser($user_data);
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$options) {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
			'id' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();
		$options['userdirectoryid'] = $userdirectoryid;

		$db_users = APIRPC::User()->get([
			'output' => ['userid', 'userdirectoryid'],
			'userids' => $options['id'],
			'filter' => ['userdirectoryid' => $userdirectoryid]
		]);

		if (!$db_users) {
			self::exception(ZBX_API_ERROR_NO_ENTITY, 'No permissions to referred object or it does not exist!');
		}
	}

	private function addScimUserAttributes(array &$user, array $options = []): void {
		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();
		$user = array_intersect_key($user, array_flip(['userid', 'username', 'surname', 'name']));

		if (!array_key_exists('username', $user)) {
			if (array_key_exists('username', $options)) {
				$user['username'] = $options['userName'];
			}
			else {
				$usernames = APIRPC::User()->get([
					'output' => ['username'],
					'userids' => $user['userid'],
					'filter' => ['userdirectoryid' => $userdirectoryid]
				]);
				$user['username'] = $usernames[0]['username'];
			}
		}

		// Property 'name' in SCIM response is required for Okta SCIM to work correctly.
		$user['name'] = array_key_exists('name', $options) ? $options['name'] : ['givenName' => '', 'familyName' => ''];
		$user['active'] = array_key_exists('active', $options) ? $options['active'] : true;

		$provisioning = CProvisioning::forUserDirectoryId($userdirectoryid);
		$user_attributes = $provisioning->getUserAttributes($options);
		$user += $user_attributes;

		$media_attributes = $provisioning->getUserIdpMediaAttributes();
		foreach ($media_attributes as $media_attribute) {
			if (array_key_exists($media_attribute, $options)) {
				$user[$media_attribute] = $options[$media_attribute];
			}
		}
	}
}
