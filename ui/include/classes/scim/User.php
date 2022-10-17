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

use API as APIRPC;
use APIException;
use CApiInputValidator;
use CAuthenticationHelper;
use CProvisioning;
use DB;
use Exception;

class User extends CScimApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'put' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'post' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'patch' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	private const SCIM_USER_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:User';

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
		$this->validateGet($options);

		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryId();

		if (array_key_exists('userName', $options)) {
			$user = APIRPC::User()->get([
				'filter' => ['username' => $options['userName']]
			]);

			if (!$user) {
				$this->data['Resources'] = [];
			}
			else {
				if ($user[0]['userdirectoryid'] != $userdirectoryid) {
					self::exception(self::SCIM_ERROR_BAD_REQUEST,
						_s('The username "%1$s" has already been taken by another user.', $options['userName'])
					);
				}
			}
		}
		elseif (array_key_exists('id', $options)) {
			$user = APIRPC::User()->get([
				'userids' => $options['id']
			]);

			if (!$user) {
				self::exception(self::SCIM_ERROR_NOT_FOUND, _('This user does not exist.'));
			}

			if ($user[0]['userdirectoryid'] != $userdirectoryid) {
				self::exception(self::SCIM_ERROR_BAD_REQUEST,
					_s('The user "%1$s" belongs to another userdirectory.', $options['id'])
				);
			}
		}
		else {
			$total_users = APIRPC::User()->get([
				'countOutput' => true,
				'filter' => ['userdirectoryid' => $userdirectoryid]
			]);
			$this->data['totalResults'] = $total_users;
			$this->data['startIndex'] = 1;
			$this->data['itemsPerPage'] = 0;

			if (array_key_exists('startIndex', $options) && $options['startIndex'] > 0) {
				$this->data['startIndex'] = $options['startIndex'];
			}

			if (array_key_exists('count', $options) && $options['count'] > 0 && $options['count'] < $total_users) {
				$this->data['itemsPerPage'] = $options['count'];
			}
			elseif ($total_users > 0) {
				$this->data['itemsPerPage'] = $total_users;
			}

			$this->listResponse($userdirectoryid);

			return $this->data;
		}

		if ($user) {
			$this->data += $this->prepareData($user[0]);
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
			'id' =>				['type' => API_ID],
			'userName' =>		['type' => API_STRING_UTF8],
			'startIndex' =>		['type' => API_STRING_UTF8],
			'count' =>			['type' => API_STRING_UTF8]
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
		$this->validatePost($options);

		[$db_user] = APIRPC::User()->get([
			'output' => ['userid', 'userdirectoryid'],
			'filter' => ['username' => $options['userName']]
		]);

		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryid();
		$provisioning = CProvisioning::forUserDirectoryId($userdirectoryid);

		$user_data['userdirectoryid'] = $userdirectoryid;
		$user_data += $provisioning->getUserAttributes($options);
		$user_data['medias'] = $provisioning->getUserMedias($options);

		if (!$db_user) {
			$user_data['username'] = $options['userName'];
			$user = APIRPC::User()->createProvisionedUser($user_data);
		}
		elseif ($db_user['userdirectoryid'] == $userdirectoryid) {
			$user_data['userid'] = $db_user['userid'];
			$user = APIRPC::User()->updateProvisionedUser($user_data);
			$user['userid'] = $db_user['userid'];
		}
		else {
			self::exception(self::SCIM_ERROR_BAD_REQUEST,
				_s('The username "%1$s" has already been taken by another user.', $options['userName'])
			);
		}

		if (!$user) {
			self::exception(self::SCIM_INTERNAL_ERROR, _('Unable to create this user.'));
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
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY | API_ALLOW_UNEXPECTED, 'fields' => [
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'userName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!in_array(self::SCIM_USER_SCHEMA, $options['schemas'], true)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, _('Incorrect schema was sent in the request.'));
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
		$group_attribute = $provisioning->getGroupIdpAttributes()[0];
		$user_group_names = [];
		if (array_key_exists($group_attribute, $options)) {
			$user_group_names = array_column($options[$group_attribute], 'display');
		}

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
	 * @throws APIException if input is invalid.
	 */
	private function validatePut(array $options): array {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY | API_ALLOW_UNEXPECTED, 'fields' => [
			'schemas' =>	['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'id' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'userName' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'username')],
			'active' =>		['type' => API_BOOLEAN, 'flags' => API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!in_array(self::SCIM_USER_SCHEMA, $options['schemas'], true)) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST, _('Incorrect schema was sent in the request.'));
		}

		[$db_user] = APIRPC::User()->get([
			'output' => ['userid', 'userdirectoryid'],
			'userids' => $options['id']
		]);
		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryid();

		if (!$db_user) {
			self::exception(self::SCIM_ERROR_NOT_FOUND, _('This user does not exist.'));
		}
		elseif ($db_user['userdirectoryid'] != $userdirectoryid) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST,
				_s('The user "%1$s" belongs to another userdirectory.', $options['id'])
			);
		}

		return $db_user;
	}

	/**
	 * User patch endpoint is not supported.
	 *
	 * @return void
	 * @throws APIException
	 */
	public function patch(): void {
		self::exception(self::SCIM_METHOD_NOT_SUPPORTED, _('The endpoint does not support the provided method.'));
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
			'userid' => $db_user[0]['userid'],
			'username' => $options['userName']
		];
		$user_data += $provisioning->getUserAttributes($options);
		$user_data['medias'] = $provisioning->getUserMedias($options);
		$user_data['usrgrps'] = [];

		APIRPC::User()->updateProvisionedUser($user_data);

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array $options): array {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'id' =>	['type' => API_ID, 'flags' => API_REQUIRED, 'uniq' => true]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryid();

		[$db_user] = APIRPC::User()->get([
			'output' => ['userid', 'userdirectoryid'],
			'userids' => $options['id']
		]);

		if (!$db_user) {
			self::exception(self::SCIM_ERROR_NOT_FOUND, _('This user does not exist.'));
		}
		elseif ($db_user['userdirectoryid'] != $userdirectoryid) {
			self::exception(self::SCIM_ERROR_BAD_REQUEST,
				_s('The user "%1$s" belongs to another userdirectory.', $options['id'])
			);
		}

		return $db_user;
	}

	/**
	 * Updates $this->data parameter with ListResponse data that is required by SCIM.
	 *
	 * @param string $userdirectoryid  SAML userdirectoryid
	 *
	 * @return void
	 */
	private function listResponse(string $userdirectoryid): void {
		$this->data['schemas'] = ['urn:ietf:params:scim:api:messages:2.0:ListResponse'];
		$this->data['Resources'] = [];
		$users = APIRPC::User()->get([
			'filter' => ['userdirectoryid' => $userdirectoryid]
		]);

		foreach ($users as $user) {
			$this->data['Resources'][] = $this->prepareData($user);
		}
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
	 * @param array  $options                                        Optional. User information sent in request from IdP.
	 *
	 * @return array                                                 Returns array with data formatted according to SCIM.
	 *                                                               Attributes might vary based on SAML settings.
	 *         ['id']
	 *         ['userName']
	 *         ['active']
	 *         ['attribute']                                          Some other attributes set up in SAML settings.
	 */
	private function prepareData(array $user, array $options = []): array {
		$data = [];
		$data['id'] = $user['userid'];
		$data['userName'] = $user['username'];
		$data['active'] = true;

		$provisioning = CProvisioning::forUserDirectoryId($user['userdirectoryid']);
		$user_attributes = $provisioning->getUserAttributes($options);
		$data += $user_attributes;

		$media_attributes = $provisioning->getUserIdpMediaAttributes();
		foreach($media_attributes as $media) {
			if (array_key_exists($media, $options)) {
				$data[$media] = $options[$media];
			}
		}

		return $data;
	}
}
