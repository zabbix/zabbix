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


namespace SCIM\clients;

use CLocalApiClient;
use CJsonRpc;
use CUser;
use CApiClientResponse;
use Exception;
use APIException;

class ScimApiClient extends CLocalApiClient {

	public function requiresAuthentication(string $api, string $method): bool {
		return !($api === 'serviceproviderconfig' && $method === 'get');
	}

	public function authenticate(array $auth, string $requested_api_method = ''): CApiClientResponse {
		$response = new CApiClientResponse();

		try {
			if ($auth['type'] != CJsonRpc::AUTH_TYPE_BEARER || $auth['auth'] === null) {
				throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
			}

			$user = (new CUser())->checkAuthentication(['token' => $auth['auth']]);

			if (array_key_exists('debug_mode', $user)) {
				$this->debug = (bool) $user['debug_mode'];
			}
		}
		catch (Exception $e) {
			$response->setErrorByException($e, $this->debug);
		}

		return $response;
	}
}
