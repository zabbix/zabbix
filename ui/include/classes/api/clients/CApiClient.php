<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
	 * @param string   $api
	 * @param string   $method
	 * @param array    $params
	 * @param int|null $auth_type  CJsonRpc::AUTH_TYPE_BEARER, CJsonRpc::AUTH_TYPE_COOKIE, CJsonRpc::AUTH_TYPE_DPOP
	 *
	 * @return CApiClientResponse
	 */
	abstract public function callMethod(string $api, string $method, array $params, ?int $auth_type);

	abstract public function getUserData(): ?array;
}
