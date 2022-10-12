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
use CApiClient;
use CHttpRequest;
use CApiClientResponse;

class API {

	public function execute(CApiClient $client, CHttpRequest $request) {
		[$input, $auth, $class, $id] = $this->parseRequestData($request);

		if ($id != null) {
			$input['id'] = $id;
		}

		if ($class === 'Users') {
			/** @var CApiClientResponse $response */
			$response = $client->callMethod('/users', strtolower($request->method()), $input, $auth);
		}
		elseif ($class === 'Groups') {
			$response = $client->callMethod('/groups', strtolower($request->method()), $input, $auth);
		}

		if ($response->errorCode !== null) {
			throw new Exception($response->errorMessage, $response->errorCode);
		}

		return new HttpResponse($response->data);
	}

	private function parseRequestData(CHttpRequest $request): array {
		$input = $request->body() === '' ? [] : json_decode($request->body(), true);

		[, $auth] = explode('Bearer ', $request->header('AUTHORIZATION'), 2) + ['', ''];
		[, $class, $id] = explode('/', $request->header('PATH-INFO'));

		if (array_key_exists('filter', $_GET)) {
			[, $filter] = explode(' eq ', $_GET['filter']);
			$input['userName'] = str_replace('"', '', $filter);
		}

		return [$input, $auth, $class, $id];
	}

}
