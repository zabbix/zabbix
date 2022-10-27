<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

use Exception;
use CHttpRequest;
use CApiClientResponse;
use SCIM\clients\ScimApiClient;

class API {
	/**
	 * Executes received request.
	 *
	 * @param ScimApiClient  $client   API client.
	 * @param CHttpRequest   $request  Request received.
	 *
	 * @return HttpResponse
	 */
	public function execute(ScimApiClient $client, CHttpRequest $request): HttpResponse {
		[$input, $auth, $class] = $this->parseRequestData($request);

		/** @var CApiClientResponse $response */
		$response = $client->callMethod($class, strtolower($request->method()), $input, $auth);

		if ($response->errorCode !== null) {
			throw new Exception($response->errorMessage, $response->errorCode);
		}

		return new HttpResponse($response->data);
	}

	/**
	 * Parses the information sent in request and returns specific data.
	 *
	 * @param CHttpRequest $request
	 *
	 * @return array with input, authorisation token, class and id.
	 */
	private function parseRequestData(CHttpRequest $request): array {
		$input = $request->body() === '' ? [] : json_decode($request->body(), true);

		[, $auth] = explode('Bearer ', $request->header('AUTHORIZATION'), 2) + ['', ''];
		[, $class, $id] = explode('/', $request->header('PATH-INFO'), 3) + ['', '', ''];

		if ($id !== '') {
			$input['id'] = $id;
		}

		$class = strtolower($class);

		if ($class === 'serviceproviderconfig' && $request->method() === 'GET') {
			$auth = null;
		}

		if (array_key_exists('filter', $_GET)) {
			preg_match('/^userName eq "(?<value>(?:[^"]|\\\\")*)"$/', $_GET['filter'], $filter_value);

			if (array_key_exists('value', $filter_value)) {
				$input['userName'] = $filter_value['value'];
			}
			else {
				throw new Exception(_('This filter is not supported'), 400);
			}
		}

		if (array_key_exists('startIndex', $_GET)) {
			$input['startIndex'] = $_GET['startIndex'];
		}

		if (array_key_exists('count', $_GET)) {
			$input['count'] = $_GET['count'];
		}

		return [$input, $auth, $class];
	}

}
