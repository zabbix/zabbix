<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace SCIM\clients;

use CLocalApiClient;
use Exception;

class ScimApiClient extends CLocalApiClient {
	/**
	 * Returns true if the given API is valid.
	 *
	 * @param string $api
	 *
	 * @return bool
	 */
	protected function isValidApi($api) {
		if (!$this->serviceFactory->hasObject($api)) {
			throw new Exception('The requested endpoint is not supported.', 501);
		}

		return true;
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
		return !($api === 'serviceproviderconfig' && $method === 'get');
	}
}
