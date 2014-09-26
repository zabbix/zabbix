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

	public function callMethod($method, $params, $auth = null, $id = null, $jsonRpc = '2.0') {
		global $DB;

		$lowerCaseMethod = strtolower($method);

		if ($jsonRpc === null) {
			return $this->createErrorResponse(-32600, _('JSON-rpc version is not specified.'), $id, $jsonRpc);
		}
		elseif ($jsonRpc !== '2.0') {
			return $this->createErrorResponse(-32600, _s('Expecting JSON-rpc version 2.0, "%s" is given.', $jsonRpc),
				$id, $jsonRpc
			);
		}

		// check method
		if ($lowerCaseMethod === '') {
			return $this->createErrorResponse(-32600, _('JSON-rpc method is not defined.'), $id, $jsonRpc);
		}
		elseif (!$this->isValidMethod($lowerCaseMethod)) {
			return $this->createErrorResponse(-32600, _s('Incorrect method "%1$s".', $method),
				$id, $jsonRpc
			);
		}

		// check params
		if ($params !== null && !is_array($params)) {
			return $this->createErrorResponse(-32602, _('JSON-rpc params is not an Array.'), $id, $jsonRpc);
		}

		$requiresAuthentication = $this->requiresAuthentication($lowerCaseMethod);

		// check that no authentication token is passed to methods that don't require it
		if (!$requiresAuthentication && $auth !== null) {
			return $this->createErrorResponse(
				$this->getJsonRpcErrorCode(ZBX_API_ERROR_PARAMETERS),
				_s('The "%1$s" method must be called without the "auth" parameter.', $method),
				$id,
				$jsonRpc
			);
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
			list($api, $apiMethod) = explode('.', $lowerCaseMethod);
			$result = call_user_func_array(array($this->serviceFactory->getObject($api), $apiMethod), array($params));

			// if the method was called successfully - commit the transaction
			if ($newTransaction) {
				DBend(true);
			}

			return $this->createResultResponse($result, $id, $jsonRpc);
		}
		catch (Exception $e) {
			if ($newTransaction) {
				// if we're calling user.login and authentication failed - commit the transaction to save the
				// failed attempt data
				if ($lowerCaseMethod === 'user.login') {
					DBend(true);
				}
				// otherwise - revert the transaction
				else {
					DBend(false);
				}
			}

			$errorCode = $this->getJsonRpcErrorCode(
				($e instanceof APIException) ? $e->getCode() : ZBX_API_ERROR_INTERNAL
			);
			$debug = ($this->debug) ? $e->getTrace() : null;

			return $this->createErrorResponse($errorCode, $e->getMessage(), $id, $jsonRpc, $debug);
		}
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
	 * Returns true if the given method is valid.
	 *
	 * @param string $method
	 *
	 * @return bool
	 */
	protected function isValidMethod($method) {
		if (strpos($method, '.') === false) {
			return false;
		}

		list($api, $method) = explode('.', $method);

		if (!$this->serviceFactory->hasObject($api)) {
			return false;
		}

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
	 * Get a JSON RPC error code for an internal service error code.
	 *
	 * @param int $serviceErrorCode
	 *
	 * @return int
	 */
	protected function getJsonRpcErrorCode($serviceErrorCode) {
		$errors = array(
			ZBX_API_ERROR_NO_METHOD => -32601,
			ZBX_API_ERROR_PARAMETERS => -32602,
			ZBX_API_ERROR_NO_AUTH => -32602,
			ZBX_API_ERROR_PERMISSIONS => -32500,
			ZBX_API_ERROR_INTERNAL => -32500
		);

		return $errors[$serviceErrorCode];
	}


}
