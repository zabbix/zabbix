<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * This class should be used for calling API services.
 */
abstract class CApiClient {

	/**
	 * Call the given API service method and return the response.
	 *
	 * @param string $api
	 * @param string $method
	 * @param array  $params
	 * @param array  $auth
	 * @param int    $auth['type']  CJsonRpc::AUTH_TYPE_HEADER, CJsonRpc::AUTH_TYPE_COOKIE
	 * @param string $auth['auth']
	 *
	 * @return CApiClientResponse
	 */
	abstract public function callMethod(string $api, string $method, array $params, array $auth);
}
