<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * This class should be used as a client for calling API services.
 */
class CApiWrapper {

	/**
	 * Currently used API.
	 *
	 * @var string
	 */
	public $api;

	/**
	 * Authentication token.
	 *
	 * @var string
	 */
	public $auth;

	/**
	 * Current API client.
	 *
	 * @var CApiClient
	 */
	protected $client;

	/**
	 * @param CApiClient $client	the API client to use
	 */
	public function __construct(CApiClient $client) {
		$this->setClient($client);
	}

	/**
	 * Sets the API client.
	 *
	 * @param CApiClient $client
	 */
	public function setClient(CApiClient $client) {
		$this->client = $client;
	}

	/**
	 * Returns the API client.
	 *
	 * @return CApiClient
	 */
	public function getClient() {
		return $this->client;
	}

	/**
	 * A magic method for calling the public methods of the API client.
	 *
	 * @param string 	$method		API method name
	 * @param array 	$params		API method parameters
	 *
	 * @return CApiClientResponse
	 */
	public function __call($method, array $params) {
		return $this->callMethod($method, reset($params));
	}

	/**
	 * Pre-process and call the client method.
	 *
	 * @param string 	$method		API method name
	 * @param array 	$params		API method parameters
	 *
	 * @return CApiClientResponse
	 */
	protected function callMethod($method, array $params) {
		return $this->callClientMethod($method, $params);
	}

	/**
	 * Call the client method and return the result.
	 *
	 * @param string 	$method		API method name
	 * @param array 	$params		API method parameters
	 *
	 * @return CApiClientResponse
	 */
	protected function callClientMethod($method, array $params) {
		return $this->client->callMethod($this->api, $method, $params, $this->auth);
	}
}
