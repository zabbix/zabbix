<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

	public function callMethod($requestApi, $requestMethod, array $params, $auth) {
		global $DB;

		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			// necessary to surpress errors when calling with error control operator like @function_name()
			if (error_reporting() === 0) {
				return true;
			}

			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});

		$api = strtolower($requestApi);
		$method = strtolower($requestMethod);

		$response = new CApiClientResponse();

		// check API
		if (!$this->isValidApi($api)) {
			$response->errorCode = ZBX_API_ERROR_PARAMETERS;
			$response->errorMessage = _s('Incorrect API "%1$s".', $requestApi);

			return $response;
		}

		// check method
		if (!$this->isValidMethod($api, $method)) {
			$response->errorCode = ZBX_API_ERROR_PARAMETERS;
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
			}

			// the nopermission parameter must not be available for external API calls.
			unset($params['nopermissions']);

			// if no transaction has been started yet - start one
			if ($DB['TRANSACTIONS'] == 0) {
				DBstart();
				$newTransaction = true;
			}

			// call API method
			$result = call_user_func_array(array($this->serviceFactory->getObject($api), $method), array($params));

			// if the method was called successfully - commit the transaction
			if ($newTransaction) {
				DBend(true);
			}

			$response->data = $result;
		}
		catch (ErrorException $e) {
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
			$response->errorMessage = 'Internal API error.';

			// add debug data
			if ($this->debug) {
				$response->debug = array(
					'message' => $e->getMessage(),
					'trace' => $e->getTrace()
				);
			}
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
				$response->debug = array(
					'message' => $e->getMessage(),
					'trace' => $e->getTrace()
				);
			}
		}

		restore_error_handler();

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
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorised.'));
		}

		$user = $this->serviceFactory->getObject('user')->checkAuthentication(array($auth));
		$this->debug = $user['debug_mode'];
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
	protected function isValidMethod($api, $method) {
		$apiService = $this->serviceFactory->getObject($api);

		// validate the method
		$availableMethods = array();
		foreach (get_class_methods($apiService) as $serviceMethod) {
			// the comparison must be case insensitive
			$availableMethods[strtolower($serviceMethod)] = true;
		}

		return isset($availableMethods[$method]);
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
			|| ($api === 'apiinfo' && $method === 'version'));
	}
}
