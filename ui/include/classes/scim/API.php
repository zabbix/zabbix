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


namespace SCIM;

use CJsonRpc;
use APIException;
use CApiClientResponse;
use CHttpRequest;
use SCIM\clients\ScimApiClient;

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
		$response->setResponse(
			$client->callMethod($endpoint, $method, $input, [
				'type' => CJsonRpc::AUTH_TYPE_HEADER,
				'auth' => $request->getAuthBearerValue()
			])
		);

		return $response;
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
}
