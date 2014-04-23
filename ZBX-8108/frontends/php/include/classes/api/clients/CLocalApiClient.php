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

	public function callMethod($api, $method, array $params, $auth) {
		global $DB;

		$response = new CApiClientResponse();
		$newTransaction = false;
		try {
			// check method
			$this->checkMethod($api, $method);

			// authenticate
			$this->authenticate($api, $method, $auth);

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
	 * Checks the authentication token if the given method requires authentication.
	 *
	 * @param string $api
	 * @param string $method
	 * @param string $auth
	 *
	 * @throws APIException
	 */
	protected function authenticate($api, $method, $auth) {
		// authenticate
		if ($this->requiresAuthentication($api, $method)) {
			if (zbx_empty($auth)) {
				throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorised.'));
			}

			$user = $this->serviceFactory->getObject('user')->checkAuthentication(array($auth));
			$this->debug = $user['debug_mode'];
		}
		elseif ($auth !== null) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS,
				_s('The "%1$s.%2$s" method must be called without the "auth" parameter.', $api, $method)
			);
		}
	}

	/**
	 * Checks if the given API and method are valid.
	 *
	 * @param $api
	 * @param $method
	 *
	 * @throws APIException
	 */
	protected function checkMethod($api, $method) {
		// validate the API
		if (!$this->serviceFactory->hasObject($api)) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Incorrect API "%1$s".', $api));
		}

		$apiService = $this->serviceFactory->getObject($api);

		// validate the method
		if (!in_array($method, get_class_methods($apiService))) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect method "%1$s.%2$s".', $api, $method)
			);
		}
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
			|| ($api === 'user' && $method === 'checkAuthentication')
			|| ($api === 'apiinfo' && $method === 'version'));
	}
}
