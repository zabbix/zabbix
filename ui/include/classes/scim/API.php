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


namespace SCIM;

use CJsonRpc;
use Exception;
use APIException;
use DBException;
use CApiClientResponse;
use CHttpRequest;
use SCIM\clients\ScimApiClient;
use CUser;

class API {

	/**
	 * Executes received request.
	 *
	 * @param ScimApiClient    $client   API client.
	 * @param CHttpRequest  $request  Request received.
	 *
	 * @return HttpResponse
	 */
	public function execute(ScimApiClient $client, CHttpRequest $request): HttpResponse {
		$response = new HttpResponse();
		$endpoint = strtolower($request->getPathInfoSegment(0));
		$method = strtolower($request->method());
		$input = $this->getRequestData($request);
		$response->setRequestDetails($endpoint, $method, $input);

		$auth_header = $request->getParsedAuthHeader();

		$auth = [
			'type' => CJsonRpc::AUTH_TYPE_BEARER,
			'auth' => $auth_header['auth']
		];

		$authenticate_response = null;

		if ($client::requiresAuthentication($endpoint, $method)) {
			$authenticate_response = $this->authenticate($client, $auth);
		}

		return $authenticate_response === null || $authenticate_response->errorCode === null
			? $response->setResponse($client->callMethod($endpoint, $method, $input, $auth))
			: $response->setResponse($authenticate_response);
	}

	/**
	 * Returns SCIM HTTP request data in array form for SCIM API.
	 *
	 * @param CHttpRequest  $request
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getRequestData(CHttpRequest $request): array {
		$data = (array) json_decode($request->body(), true);
		$filter = $request->getUrlArgument('filter', '');

		if ($request->getPathInfoSegment(1) !== '') {
			$data['id'] = $request->getPathInfoSegment(1);
		}

		if ($filter !== '') {
			if (strtolower($request->method()) !== 'get') {
				throw new APIException(ZBX_API_ERROR_PARAMETERS, 'This filter is not supported');
			}

			$value = null;

			switch (strtolower($request->getPathInfoSegment(0))) {
				case 'users':
					$key = 'userName';
					$value = $this->getUsersQueryFilter($filter);
					break;

				case 'groups':
					$key = 'displayName';
					$value = $this->getGroupsQueryFilter($filter);
					break;
			}

			if ($value === null) {
				throw new APIException(ZBX_API_ERROR_PARAMETERS, 'This filter is not supported');
			}

			$data[$key] = $value;
		}

		if ($request->hasUrlArgument('startIndex')) {
			$data['startIndex'] = $request->getUrlArgument('startIndex');
		}

		if ($request->hasUrlArgument('count')) {
			$data['count'] = $request->getUrlArgument('count');
		}

		return $data;
	}

	/**
	 * Parses filter for users request filter.
	 *
	 * @param string $filter  Filter string.
	 *
	 * @return ?string  String value for userName filter, null when filter is incorrect.
	 */
	public function getUsersQueryFilter(string $filter): ?string {
		preg_match('/^userName eq "(?<value>(?:[^"]|\\\\")*)"$/', $filter, $filter_value);

		return array_key_exists('value', $filter_value) ? $filter_value['value'] : null;
	}

	/**
	 * Parses filter for groups request filter.
	 *
	 * @param string $filter  Filter string.
	 *
	 * @return ?string  String value for displayName filter, null when filter is incorrect.
	 */
	public function getGroupsQueryFilter(string $filter): ?string {
		preg_match('/^displayName eq "(?<value>(?:[^"]|\\\\")*)"$/', $filter, $filter_value);

		return array_key_exists('value', $filter_value) ? $filter_value['value'] : null;
	}

	public function authenticate(ScimApiClient $client, array $auth): CApiClientResponse {
		global $NO_AUTH_DEBUG_MODE;

		$response = new CApiClientResponse();

		try {
			if ($auth['auth'] === null) {
				throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
			}

			$user = (new CUser())->checkAuthentication(['token' => $auth['auth']]);

			if (array_key_exists('debug_mode', $user)) {
				$client->debug = $user['debug_mode'];
			}
		}
		catch (Exception $e) {
			if ($e instanceof APIException) {
				$response->errorCode = $e->getCode();
			}
			elseif ($e instanceof DBException) {
				$response->errorCode = ZBX_API_ERROR_DB;
			}
			else {
				$response->errorCode = ZBX_API_ERROR_INTERNAL;
			}

			$response->errorMessage = $e->getMessage();

			// add debug data
			if ($NO_AUTH_DEBUG_MODE) {
				$response->debug = $e->getTrace();

				if ($e instanceof APIException) {
					$response->errorMessage = $e->getDebugMessage();
				}
			}
		}

		return $response;
	}
}
