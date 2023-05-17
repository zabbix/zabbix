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
		$endpoint = strtolower($request->getPathInfoSegment(0));
		$method = strtolower($request->method());
		$data = $this->getRequestData($request);

		/** @var CApiClientResponse $response */
		$response = $client->callMethod($endpoint, $method, $data, [
			'type' => CJsonRpc::AUTH_TYPE_HEADER,
			'auth' => $request->getAuthBearerValue()
		]);

		if ($response->errorCode !== null) {
			throw new Exception($response->errorMessage, $response->errorCode);
		}

		return new HttpResponse($endpoint, $method, $data, $response->data);
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
		$data = $request->body() === '' ? [] : json_decode($request->body(), true);
		$filter = $request->getUrlArgument('filter', '');

		if ($request->getPathInfoSegment(1) !== '') {
			$data['id'] = $request->getPathInfoSegment(1);
		}

		if ($filter !== '') {
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
				throw new Exception('This filter is not supported', 400);
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
