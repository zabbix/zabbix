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


namespace SCIM;

use CJsonRpc;
use Exception;
use CApiClientResponse;
use SCIM\clients\ScimApiClient;

class API {

	/**
	 * Executes received request.
	 *
	 * @param ScimApiClient    $client   API client.
	 * @param ScimHttpRequest  $request  Request received.
	 *
	 * @return ScimHttpResponse
	 */
	public function execute(ScimApiClient $client, ScimHttpRequest $request): ScimHttpResponse {
		$requestApi = $request->getRequestApi();
		$requestMethod = strtolower($request->method());
		$data = $request->getRequestData();

		/** @var CApiClientResponse $response */
		$response = $client->callMethod($requestApi, $requestMethod, $data, [
			'type' => CJsonRpc::AUTH_TYPE_HEADER,
			'auth' => $request->getAuthBearerValue()
		]);

		if ($response->errorCode !== null) {
			throw new Exception($response->errorMessage, $response->errorCode);
		}

		return new ScimHttpResponse($requestApi, $requestMethod, $data, $response->data);
	}
}
