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
 * A class for loading fixtures using the API.
 */
class CApiFixture extends CFixture {

	/**
	 * Object to use for API requests.
	 *
	 * @var CApiWrapper
	 */
	protected $apiWrapper;

	/**
	 * @param CApiWrapper $apiWrapper	object to use for API requests
	 */
	public function __construct(CApiWrapper $apiWrapper) {
		$this->apiWrapper = $apiWrapper;
	}

	/**
	 * Load a fixture that performs an API request.
	 *
	 * Supported parameters:
	 * - method	- name of the method to call
	 * - params	- array of parameters for the method
	 */
	public function load(array $params) {
		$this->checkMissingParams($params, array('method', 'params'));

		// if the client is not authenticated - log in
		// TODO: pass credentials as a parameter
		if (!$this->apiWrapper->auth) {
			$rs = $this->apiWrapper->callMethod('user.login', array(
				'user' => 'Admin',
				'password' => 'zabbix'
			));

			if ($rs->isError()) {
				throw new UnexpectedValueException(sprintf('Cannot authenticate to load API fixture: %1$s', $rs->getErrorData()));
			}

			$this->apiWrapper->auth = $rs->getResult();
		}

		$rs = $this->apiWrapper->callMethod($params['method'], $params['params']);

		if ($rs->isError()) {
			// treat all API errors as argument exceptions
			throw new InvalidArgumentException($rs->getErrorData());
		}

		return $rs->getResult();
	}

}
