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


/**
 * This class should be used to call API services locally using the CApiService classes.
 */
class CLocalApiClient extends CApiClient {

	/**
	 * Factory for creating API services.
	 *
	 * @var CRegistryFactory
	 */
	protected $serviceFactory;

	/**
	 * Whether debug mode is enabled.
	 *
	 * @var bool
	 */
	protected $debug = false;

	/**
	 * Set service factory.
	 *
	 * @param CRegistryFactory $factory
	 */
	public function setServiceFactory(CRegistryFactory $factory) {
		$this->serviceFactory = $factory;
	}

	/**
	 * Call the given API service method and return the response.
	 *
	 * @param string 	$requestApi			API name
	 * @param string 	$requestMethod		API method
	 * @param array 	$params				API parameters
	 * @param string	$auth				Authentication token
	 *
	 * @return CApiClientResponse
	 */
	public function callMethod($requestApi, $requestMethod, array $params, $auth) {
		global $DB;

		$api = strtolower($requestApi);
		$method = strtolower($requestMethod);

		$response = new CApiClientResponse();

		// check API
		if (!$this->isValidApi($api)) {
			$response->errorCode = ZBX_API_ERROR_NO_METHOD;
			$response->errorMessage = _s('Incorrect API "%1$s".', $requestApi);

			return $response;
		}

		// check method
		if (!$this->isValidMethod($api, $method)) {
			$response->errorCode = ZBX_API_ERROR_NO_METHOD;
			$response->errorMessage = _s('Incorrect method "%1$s.%2$s".', $requestApi, $requestMethod);

			return $response;
		}

		$requiresAuthentication = $this->requiresAuthentication($api, $method);

		// check that no authentication token is passed to methods that don't require it
		if (!$requiresAuthentication && $auth !== null) {
			$response->errorCode = ZBX_API_ERROR_PARAMETERS;
			$response->errorMessage = _s('The "%1$s.%2$s" method must be called without the "auth" parameter.',
				$requestApi, $requestMethod
			);

			return $response;
		}

		$newTransaction = false;
		try {
			// authenticate
			if ($requiresAuthentication) {
				$this->authenticate($auth);

				// check permissions
				if (APP::getMode() === APP::EXEC_MODE_API && !$this->isAllowedMethod($api, $method)) {
					$response->errorCode = ZBX_API_ERROR_PARAMETERS;
					$response->errorMessage = _s('No permissions to call "%1$s.%2$s".', $requestApi, $requestMethod);

					return $response;
				}
			}

			// the nopermission parameter must not be available for external API calls.
			unset($params['nopermissions']);

			// if no transaction has been started yet - start one
			if ($DB['TRANSACTIONS'] == 0) {
				DBstart();
				$newTransaction = true;
			}

			// call API method
			$result = call_user_func_array([$this->serviceFactory->getObject($api), $method], [$params]);

			// if the method was called successfully - commit the transaction
			if ($newTransaction) {
				DBend(true);
			}

			$response->data = $result;
		}
		catch (Exception $e) {
			if ($newTransaction) {
				// if we're calling user.login and authentication failed - commit the transaction to save the
				// failed attempt data
				if ($api === 'user' && $method === 'login') {
					DBend(true);
				}
				// otherwise - revert the transaction
				else {
					DBend(false);
				}
			}

			$response->errorCode = ($e instanceof APIException) ? $e->getCode() : ZBX_API_ERROR_INTERNAL;
			$response->errorMessage = $e->getMessage();

			// add debug data
			if ($this->debug) {
				$response->debug = $e->getTrace();
			}
		}

		return $response;
	}

	/**
	 * Checks if the authentication token is valid.
	 *
	 * @param string $auth
	 *
	 * @throws APIException
	 */
	protected function authenticate($auth) {
		if (zbx_empty($auth)) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
		}

		if (strlen($auth) == 64) {
			$this->tokenAuthentication($auth);
			return;
		}

		$user = $this->serviceFactory->getObject('user')->checkAuthentication(['sessionid' => $auth]);
		if (array_key_exists('debug_mode', $user)) {
			$this->debug = $user['debug_mode'];
		}
	}

	/**
	 * Authenticates user based on token.
	 *
	 * @param string $auth_token
	 *
	 * @throws APIException
	 */
	protected function tokenAuthentication(string $auth_token) {
		$api_tokens = DB::select('token', [
			'output' => ['userid', 'expires_at', 'tokenid'],
			'filter' => ['token' => hash('sha512', $auth_token), 'status' => ZBX_AUTH_TOKEN_ENABLED]
		]);

		if (!$api_tokens) {
			usleep(10000);
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
		}

		[['expires_at' => $expires_at, 'userid' => $userid, 'tokenid' => $tokenid]] = $api_tokens;

		if ($expires_at != 0 && $expires_at < time()) {
			throw new APIException(ZBX_API_ERROR_PERMISSIONS, _('API token expired.'));
		}

		[['roleid' => $roleid, 'username' => $username]] = DB::select('users', [
			'output' => ['roleid', 'username'],
			'userids' => $userid
		]);

		[$type] = DBfetchColumn(DBselect('SELECT type FROM role WHERE roleid='.zbx_dbstr($roleid)), 'type');

		$db_usrgrps = DBselect(
			'SELECT g.debug_mode,g.users_status'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE g.usrgrpid=ug.usrgrpid'.
				' AND ug.userid='.$userid
		);

		$debug_mode = GROUP_DEBUG_MODE_DISABLED;
		while ($db_usrgrp = DBfetch($db_usrgrps)) {
			if ($db_usrgrp['users_status'] == GROUP_STATUS_DISABLED) {
				throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
			}

			if ($db_usrgrp['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
				$debug_mode = GROUP_DEBUG_MODE_ENABLED;
				break;
			}
		}

		CApiService::$userData = [
			'userid' => $userid,
			'username' => $username,
			'type' => $type,
			'roleid' => $roleid,
			'userip' => CWebUser::getIp(),
			'sessionid' => $auth_token,
			'debug_mode' => $debug_mode
		];

		$this->debug = ($debug_mode == GROUP_DEBUG_MODE_ENABLED);

		DB::update('token', [
			'values' => ['lastaccess' => time()],
			'where' => ['tokenid' => $tokenid]
		]);
	}

	/**
	 * Returns true if the given API is valid.
	 *
	 * @param string $api
	 *
	 * @return bool
	 */
	protected function isValidApi($api) {
		return $this->serviceFactory->hasObject($api);
	}

	/**
	 * Returns true if the given method is valid.
	 *
	 * @param string $api
	 * @param string $method
	 *
	 * @return bool
	 */
	protected function isValidMethod(string $api, string $method): bool {
		$api_service = $this->serviceFactory->getObject($api);

		return array_key_exists($method, $api_service::ACCESS_RULES);
	}

	/**
	 * Returns true if calling the given method requires a valid authentication token.
	 *
	 * @param $api
	 * @param $method
	 *
	 * @return bool
	 */
	protected function requiresAuthentication($api, $method) {
		return !(($api === 'user' && $method === 'login')
			|| ($api === 'user' && $method === 'checkauthentication')
			|| ($api === 'apiinfo' && $method === 'version')
			|| ($api === 'settings' && $method === 'getglobal'));
	}

	/**
	 * Returns true if the current user is permitted to call the given API method, and false otherwise.
	 *
	 * @param string $api
	 * @param string $method
	 *
	 * @return bool
	 */
	protected function isAllowedMethod(string $api, string $method): bool {
		$api_service = $this->serviceFactory->getObject($api);
		$user_data = $api_service::$userData;
		$method_rules = $api_service::ACCESS_RULES[$method];

		if (!array_key_exists('min_user_type', $method_rules)
				|| !in_array($user_data['type'], [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])
				|| $user_data['type'] < $method_rules['min_user_type']) {
			return false;
		}

		$exists_action_rule = array_key_exists('action', $method_rules);

		$name_conditions = 'name LIKE '.zbx_dbstr('api%');
		if ($exists_action_rule) {
			$name_conditions = '('.
				$name_conditions.
				' OR name='.zbx_dbstr($method_rules['action']).
				' OR name='.zbx_dbstr('actions.default_access').
			')';
		}

		$db_rules = DBselect(
			'SELECT type,name,value_str,value_int'.
			' FROM role_rule'.
			' WHERE roleid='.zbx_dbstr($user_data['roleid']).
				' AND '.$name_conditions.
			' ORDER by name'
		);

		$api_access_mode = false;
		$api_methods = [];
		$actions_default_access = true;
		$is_action_allowed = null;

		while ($db_rule = DBfetch($db_rules)) {
			$rule_value = $db_rule[CRole::RULE_TYPE_FIELDS[$db_rule['type']]];

			switch ($db_rule['name']) {
				case 'api.access':
					if ($rule_value == 0) {
						return false;
					}
					break;

				case 'api.mode':
					$api_access_mode = (bool) $rule_value;
					break;

				case 'actions.default_access':
					$actions_default_access = (bool) $rule_value;
					break;

				default:
					if (strpos($db_rule['name'], 'api.method.') === 0) {
						$api_methods[] = $rule_value;
					}
					elseif ($exists_action_rule && $db_rule['name'] === $method_rules['action']) {
						$is_action_allowed = (bool) $rule_value;
					}
			}
		}

		if ($exists_action_rule) {
			$is_action_allowed = ($is_action_allowed !== null) ? $is_action_allowed : $actions_default_access;

			if (!$is_action_allowed) {
				return false;
			}
		}

		if (!$api_methods) {
			return true;
		}

		$api_method_masks = [
			ZBX_ROLE_RULE_API_WILDCARD, ZBX_ROLE_RULE_API_WILDCARD_ALIAS, CRoleHelper::API_ANY_SERVICE.$method,
			$api.CRoleHelper::API_ANY_METHOD
		];
		foreach ($api_methods as $api_method) {
			if ($api_method === $api.'.'.$method || in_array($api_method, $api_method_masks)) {
				return $api_access_mode;
			}
		}

		return !$api_access_mode;
	}
}
