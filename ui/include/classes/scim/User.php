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
use CApiInputValidator;
use CApiService;
use CAuthenticationHelper;
use CProvisioning;
use DB;
use Exception;

class User extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'put' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'post' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected array $data = [
		'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User']
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

		$saml_settings = CAuthenticationHelper::getDefaultUserdirectory(IDP_TYPE_SAML);

		if (array_key_exists('userName', $options)) {
			$user = JSRPC::User()->get([
				'outputCount' => true,
				'filter' => ['username' => $options['userName']]
			]);
		}
		elseif (array_key_exists('id', $options)) {
			$user = JSRPC::User()->get([
				'userids' => $options['id']
			]);
		}
		else {
			$this->listResponse($saml_settings);
			return $this->data;
		}

		if ($user) {
			$this->data += $this->prepareData($user[0], $saml_settings);
		}
		else {
			$this->data['Resources'] = [];
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
			'userName' =>	['type' => API_STRING_UTF8]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			sdff($error);
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
	 * @return array          Returns SCIM data that is necessary for POST request response.
	 */
	public function post(array $options): array {
		$this->validatePost($options);

		$saml_settings = CAuthenticationHelper::getDefaultUserdirectory(IDP_TYPE_SAML);
		$saml_provisioning_data = new CProvisioning($saml_settings);

		$db_user = JSRPC::User()->get([
			'output' => ['userid'],
			'filter' => [
				'username' => $options['userName']
			]
		]);

		$user_attributes = $saml_provisioning_data->getUserAttributes($options);
		$provision_media = $saml_provisioning_data->getUserMedias($options);

		if (!$db_user) {
			$user = JSRPC::User()->createProvisionedUser([
				'username' => $options['userName'],
				'name' => $user_attributes['name'],
				'surname' => $user_attributes['surname'],
				'medias' => $provision_media,
				'userdirectoryid' => $saml_settings['userdirectoryid']
			]);
		}
		else {
			$user = JSRPC::User()->updateProvisionedUser([
				'userid' => $db_user[0]['userid'],
				'name' => $user_attributes['name'],
				'surname' => $user_attributes['surname'],
				'medias' => $provision_media,
				'userdirectoryid' => $saml_settings['userdirectoryid']
			]);
			$user['userid'] = $db_user[0]['userid'];
		}

		if ($user) {
			$this->setData($user['userid'], $saml_settings, $options);
		}
													// TODO here need to add error message in case user is not created

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if input is invalid.
	 */
	private function validatePost(array $options) {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY | API_ALLOW_UNEXPECTED, 'fields' => [
			'userName' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
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
		$this->validatePut($options);

		$saml_settings = CAuthenticationHelper::getDefaultUserdirectory(IDP_TYPE_SAML);
		$saml_provisioning_data = new CProvisioning($saml_settings);
		$db_user = JSRPC::User()->get([
			'userids' => $options['id']
		]);

		if ($options['active'] == true) {
			$user_attributes = $saml_provisioning_data->getUserAttributes($options);
			$group_attribute = $saml_provisioning_data->getGroupIdpAttributes()[0];
			$user_group_names = array_column($options[$group_attribute], 'display');
			$provision_groups = $saml_provisioning_data->getUserGroupsAndRole($user_group_names);
			$provision_media = $saml_provisioning_data->getUserMedias($options);

			$user = JSRPC::User()->updateProvisionedUser([
				'userid' => $db_user[0]['userid'],
				'username' => $options['userName'],
				'name' => $user_attributes['name'],
				'surname' => $user_attributes['surname'],
				'usrgrps' => $provision_groups['usrgrps'],
				'roleid' => $provision_groups['roleid'],
				'medias' => $provision_media
			]);

			if ($user) {
				$this->setData($user['userid'], $saml_settings, $options);
			}
		}
		else {
			JSRPC::User()->delete([$db_user[0]['userid']]);
		}

		return $this->data;
	}

	/**
	 * @param array $options
	 *
	 * @throws APIException if input is invalid.
	 */
	private function validatePut(array $options): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY | API_ALLOW_UNEXPECTED, 'fields' => [
			'id' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'userName' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'username')],
			'active' =>		['type' => API_BOOLEAN, 'flags' => API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Deletes requested user based on userid.
	 *
	 * @param array  $options
	 * @param string $options['id']  Userid.
	 *
	 * @return array          Returns only schema parameter, the rest of the parameters are not included.
	 */
	public function delete(array $options) {
		$this->validateDelete($options);

		$db_user = JSRPC::User()->get([
			'userids' => $options['id']
		]);

		if ($db_user) {
			JSRPC::User()->delete([$db_user[0]['userid']]);
		}										// TODO need to add response if user was not found

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
	 * Updates $this->data parameter with ListResponse data that is required by SCIM.
	 *
	 * @param array $saml_settings  Information on SAML user directory. Use CAuthenticationHelper to get default SAML.
	 *
	 * @return void
	 */
	private function listResponse(array $saml_settings): void {
		$this->data['schemas'] = ['urn:ietf:params:scim:api:messages:2.0:ListResponse'];
		$this->data['Resources'] = [];
		$users = JSRPC::User()->get([
			'filter' => ['userdirectoryid' => $saml_settings['userdirectoryid']]
		]);

		foreach ($users as $user) {
			$this->data['Resources'][] = $this->prepareData($user, $saml_settings);
		}
	}

	/**
	 * Updates $this->data parameter with the data that is required by SCIM.
	 *
	 * @param string $userid
	 * @param array  $saml_settings  Information on SAML user directory. Use CAuthenticationHelper to get default SAML.
	 * @param array  $options        Optional. User information sent in request from IdP.
	 *
	 * @return void
	 */
	private function setData(string $userid, array $saml_settings, array $options = []): void {
		$user = JSRPC::User()->get([
			'userids' => $userid
		]);

		$this->data += $this->prepareData($user[0], $saml_settings, $options);
	}

	/**
	 * Returns user data that is necessary for SCIM response to IdP and that can be used to update $this->data.
	 *
	 * @param array  $user
	 * @param string $user['userid']
	 * @param string $user['username']
	 * @param array  $saml_settings
	 * @param string $saml_settings['user_username']
	 * @param string $saml_settings['user_lastname']
	 * @param array  $saml_settings['provision_media']
	 * @param string $saml_settings['provision_media']['attribute']  Some other specific attribute defined in SAML
	 *                                                               settings.
	 * @param array  $options                                        Optional. User information sent in request from IdP.
	 *
	 * @return array                                                 Returns array with data formatted according to SCIM.
	 *                                                               Attributes might vary based on SAML settings.
	 *         ['id']
	 *         ['userName']
	 *         ['active']
	 *         ['attribute']                                          Some other attributes set up in SAML settings.
	 */
	private function prepareData(array $user, array $saml_settings, array $options = []): array {
		$data = [];

		$data['id'] = $user['userid'];
		$data['userName'] = $user['username'];
		$data['active'] = true;

		if (array_key_exists($saml_settings['user_username'], $options)) {
			$data[$saml_settings['user_username']] = $user['name'];
		}

		if (array_key_exists($saml_settings['user_lastname'], $options)) {
			$data[$saml_settings['user_lastname']] = $user['surname'];
		}

		foreach($saml_settings['provision_media'] as $media) {
			if (array_key_exists($media['attribute'], $options)) {
				$data[$media['attribute']] = $options[$media['attribute']];
			}
		}

		return $data;
	}
}
