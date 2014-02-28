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
 * This class should be used for calling API services.
 */
abstract class CApiClient {

	/**
	 * Name of the current API.
	 *
	 * @var string
	 */
	public $api;

	/**
	 * A magic method for calling public methods of the API service.
	 *
	 * @param string 	$method
	 * @param array 	$params
	 *
	 * @return mixed
	 */
	public function __call($method, array $params) {
		return $this->callServiceMethod($method, $params);
	}

	/**
	 * Call the given API service method and return the response.
	 *
	 * @param string $method
	 * @param array	 $params
	 *
	 * @return CApiClientResponse
	 */
	abstract protected function callServiceMethod($method, array $params);
}
