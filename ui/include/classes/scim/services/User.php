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
use CApiInputValidator;
use CAuthenticationHelper;
use CProvisioning;
use DB;
use Exception;
use SCIM\ScimApiService;

class User extends ScimApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'put' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'post' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'patch' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	private const SCIM_USER_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:User';
	private const SCIM_LIST_RESPONSE_SCHEMA = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';

	protected array $data = [
		'schemas' => [self::SCIM_USER_SCHEMA]
	];

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
		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();

		$this->validateGet($options);

		if (array_key_exists('userName', $options)) {
			$users = APIRPC::User()->get([
				'output' => ['userid', 'username', 'userdirectoryid'],
				'selectUsrgrps' => ['usrgrpid'],
				'filter' => ['username' => $options['userName']]
			]);

			$user_groups = $users ? array_column($users[0]['usrgrps'], 'usrgrpid') : [];
			$disabled_groupid = CAuthenticationHelper::get(CAuthenticationHelper::DISABLED_USER_GROUPID);

			if (!$users || (count($user_groups) == 1 && $user_groups[0] == $disabled_groupid)) {
				$this->data += [
					'totalResults' => 0,
					'Resources' => []
				];
			}
			elseif ($users[0]['userdirectoryid'] != $userdirectoryid) {
				self::exception(self::SCIM_ERROR_BAD_REQUEST,
					'User with username '.$options["userName"].' already exists.'
				);
			}
			else {
				$this->data = $this->prepareData($users[0]);
			}
		}
		elseif (array_key_exists('id', $options)) {
			$users = APIRPC::User()->get([
				'output' => ['userid', 'username', 'userdirectoryid'],
				'userids' => $options['id']
			]);

			if (!$users) {
				self::exception(self::SCIM_ERROR_NOT_FOUND, 'No permissions to referred object or it does not exist!');
			}
			elseif ($users[0]['userdirectoryid'] != $userdirectoryid) {
				self::exception(self::SCIM_ERROR_BAD_REQUEST,
					'The user '.$options['id'].' belongs to another userdirectory.'
				);
			}

			$this->data = $this->prepareData($users[0]);
		}
		else {
			$userids = APIRPC::User()->get([
				'output' => ['userid'],
				'filter' => ['userdirectoryid' => $userdirectoryid]
			]);
			$total_users = count($userids);

			$this->data = [
				'schemas' => [self::SCIM_LIST_RESPONSE_SCHEMA],
				'totalResults' => $total_users,
				'startIndex' => max($options['startIndex'], 1),
				'itemsPerPage' => min($total_users, max($options['count'], 0)),
				'Resources' => []
			];

			if ($total_users != 0) {
				$userids = array_slice($userids, $this->data['startIndex'] - 1, $this->data['itemsPerPage']);
				$userids = array_column($userids, 'userid');

				$users = $userids
					? APIRPC::User()->get([
						'output' => ['userid', 'username', 'userdirectoryid'],
						'userids' => $userids
					])
					: [];

				foreach ($users as $user) {
					$user_data = $this->prepareData($user);
					unset($user_data['schemas']);
					$this->data['Resources'][] = $user_data;
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
	 * @return array                       Returns SCIM data that is necessary for POST request response.
	 */
	public function post(array $options): array {
		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();

		$this->validatePost($options);

		$db_users = APIRPC::User()->get([
			'output' => ['userid', 'userdirectoryid'],
			'filter' => ['username' => $options['userName']]
		]);

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
			$user_data['usrgrps'] = [];
			$user = APIRPC::User()->updateProvisionedUser($user_data);
			$user['userid'] = $db_users[0]['userid'];
		}
		else {
			self::exception(self::SCIM_ERROR_BAD_REQUEST,
				'User with username '.$options['userName'].' already exists.'
			);
		}

		$this->setData($user['userid'], $userdirectoryid, $options);

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if input is invalid.
	 */
	private function validatePost(array $options) {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_ALLOW_UNEXPECTED, 'fields' => [
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => self::SCIM_USER_SCHEMA],
			'userName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Updates user in the database with newly received information. If $options['active'] parameter is false, user
	 * is deleted from the database.
	 *
	 * @param array  $options
	 * @param string $options['id']
	 * @param string $options['userName']
	 * @param bool   $options['active']  True of false, but sent as string.
	 *
	 * @return array          Returns SCIM data that is necessary for PUT request response.
	 */
	public function put(array $options): array {
		$db_user = $this->validatePut($options);

		$provisioning = CProvisioning::forUserDirectoryId($db_user['userdirectoryid']);

		$user_group_names = array_key_exists('groups', $options)
			? array_column($options['groups'], 'display')
			: [];

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

		if ($options['active'] == true) {
			APIRPC::User()->updateProvisionedUser($user_data);

			$this->setData($db_user['userid'], $db_user['userdirectoryid'], $options);
		}
		else {
			DB::delete('user_scim_group', [
				'userid' => $user_data['userid']
			]);

			$user_data['usrgrps'] = [];
			APIRPC::User()->updateProvisionedUser($user_data);
		}

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @returns array                Returns user data from the database.
	 *          ['userid']
	 *          ['userdirectoryid']
	 *
	 * @throws APIException if input is invalid or user cannot be modified.
	 */
	private function validatePut(array $options): array {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_ALLOW_UNEXPECTED, 'fields' => [
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => self::SCIM_USER_SCHEMA],
			'id' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'userName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'username')],
			'active' =>		['type' => API_BOOLEAN, 'flags' => API_NOT_EMPTY],
			'groups' =>		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'fields' => [
				'value' =>		['type' => API_ID, 'flags' => API_REQUIRED],
				'display' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!in_array(self::SCIM_USER_SCHEMA, $options['schemas'], true)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, 'Incorrect schema was sent in the request.');
		}

		[$db_user] = APIRPC::User()->get([
			'output' => ['userid', 'userdirectoryid'],
			'userids' => $options['id']
		]);
		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();

		if (!$db_user) {
			self::exception(self::SCIM_ERROR_NOT_FOUND, 'No permissions to referred object or it does not exist!');
		}
		elseif ($db_user['userdirectoryid'] != $userdirectoryid) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST,
				'The user '.$options['id'].' belongs to another userdirectory.'
			);
		}

		return $db_user;
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
		$db_user = $this->validateDelete($options);

		$provisioning = CProvisioning::forUserDirectoryId($db_user['userdirectoryid']);
		$user_data = [
			'userid' => $db_user[0]['userid']
		];
		$user_data += $provisioning->getUserAttributes($options);
		$user_data['medias'] = $provisioning->getUserMedias($options);
		$user_data['usrgrps'] = [];

		DB::delete('user_scim_group', [
			'userid' => $user_data['userid']
		]);

		APIRPC::User()->updateProvisionedUser($user_data);

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array $options): array {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
			'id' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryidForScim();

		$db_users = APIRPC::User()->get([
			'output' => ['userid', 'userdirectoryid'],
			'userids' => $options['id']
		]);

		if (!$db_users) {
			self::exception(self::SCIM_ERROR_NOT_FOUND, 'No permissions to referred object or it does not exist!');
		}
		elseif ($db_users[0]['userdirectoryid'] != $userdirectoryid) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST,
				'The user '.$options['id'].' belongs to another userdirectory.'
			);
		}

		return $db_users[0];
	}

	/**
	 * Updates $this->data parameter with the data that is required by SCIM.
	 *
	 * @param string $userid
	 * @param string $userdirectoryid  SAML userdirectory ID.
	 * @param array  $options          Optional. User information sent in request from IdP.
	 *
	 * @return void
	 */
	private function setData(string $userid, string $userdirectoryid, array $options = []): void {
		$user = APIRPC::User()->get([
			'output' => ['userid', 'username', 'userdirectoryid'],
			'userids' => $userid,
			'filter' => ['userdirectoryid' => $userdirectoryid]
		]);

		$this->data += $this->prepareData($user[0], $options);
	}

	/**
	 * Returns user data that is necessary for SCIM response to IdP and that can be used to update $this->data.
	 *
	 * @param array  $user
	 * @param string $user['userid']
	 * @param string $user['username']
	 * @param array  $options                                     Optional. User information sent in request from IdP.
	 *
	 * @return array                                              Returns array with data formatted according to SCIM.
	 *                                                            Attributes might vary based on SAML settings.
	 *         ['id']
	 *         ['userName']
	 *         ['active']
	 *         ['attribute']                                      Some other attributes set up in SAML settings.
	 */
	private function prepareData(array $user, array $options = []): array {
		$data = [
			'schemas'	=> [self::SCIM_USER_SCHEMA],
			'id' 		=> $user['userid'],
			'userName'	=> $user['username'],
			'active'	=> true
		];

		$provisioning = CProvisioning::forUserDirectoryId($user['userdirectoryid']);
		$user_attributes = $provisioning->getUserAttributes($options);
		$data += $user_attributes;

		$media_attributes = $provisioning->getUserIdpMediaAttributes();
		foreach ($media_attributes as $media_attribute) {
			if (array_key_exists($media_attribute, $options)) {
				$data[$media_attribute] = $options[$media_attribute];
			}
		}

		return $data;
	}
}
