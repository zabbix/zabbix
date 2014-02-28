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
	 * @var CApiServiceFactory
	 */
	protected $serviceFactory;

	/**
	 * Set service factory.
	 *
	 * @param CApiServiceFactory $factory
	 */
	public function setServiceFactory(CApiServiceFactory $factory) {
		$this->serviceFactory = $factory;
	}

	protected function callServiceMethod($method, array $params) {
		global $DB;

		// the nopermission parameter must not be available for external API calls.
		unset($params['nopermissions']);

		// if no transaction has been started yet - start one
		$newTransaction = false;
		if ($DB['TRANSACTIONS'] == 0) {
			DBstart();
			$newTransaction = true;
		}

		$response = new CApiClientResponse();
		try {
			$result = call_user_func_array(array($this->serviceFactory->getObject($this->api), $method), $params);

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
				if ($this->api === 'user' && $method === 'login') {
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
			if (CWebUser::getDebugMode()) {
				$response->debug = $e->getTrace();
			}
		}

		return $response;
	}
}
