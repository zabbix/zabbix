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

use APIException;
use CApiClientResponse;
use SCIM\services\User;
use SCIM\services\Group;

class HttpResponse {

	protected $http_codes = [
		ZBX_API_ERROR_INTERNAL		=> 500,
		ZBX_API_ERROR_PARAMETERS	=> 400,
		ZBX_API_ERROR_NO_ENTITY		=> 404,
		ZBX_API_ERROR_PERMISSIONS	=> 403,
		ZBX_API_ERROR_NO_AUTH		=> 403,
		ZBX_API_ERROR_NO_METHOD		=> 405
	];

	public const SCHEMA_ERROR = 'urn:ietf:params:scim:api:messages:2.0:Error';
	public const SCHEMA_LIST = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';

	/** @var array $response_data  Array of response data to be sent. */
	protected array $response_data = [];

	/** @var int $response_code  HTTP response status code. */
	protected $http_code = 200;

	/** @var string $class  Name of the class that response is received from */
	protected string $class;

	/** @var string $method  Name of the method that response is received from */
	protected string $method;

	/** @var array $request_data Array of request data that was sent */
	protected array $request_data;

	public function setRequestDetails(string $api, string $method, array $input) {
		$this->class = $api;
		$this->method = $method;
		$this->request_data = $input;
	}

	public function setResponse(CApiClientResponse $response) {
		if ($response->errorCode) {
			$this->setException(new APIException($response->errorCode, $response->errorMessage));
		}
		else {
			$this->response_data = $response->data;

			switch ($this->class) {
				case 'users':
					$this->response_data = $this->prepareUsersResponse();
					break;

				case 'groups':
					$this->response_data = $this->prepareGroupsResponse();
					break;
			}
		}

		return $this;
	}

	public function setException(APIException $e) {
		$this->http_code = $this->http_codes[$e->getCode()];
		$this->response_data = [
			'schemas' => [HttpResponse::SCHEMA_ERROR],
			'detail' => $e->getMessage(),
			'status' => $this->http_code
		];

		return $this;
	}

	/**
	 * Send HTTP response.
	 */
	public function send(): void {
		header('Content-Type: application/json', true, $this->http_code);
		echo json_encode($this->response_data);
	}

	/**
	 * Prepares Users request response based on the data provided.
	 *
	 * @return array
	 */
	private function prepareUsersResponse(): array {
		if ($this->method === 'delete' ) {
			return ['schemas'	=> [User::SCIM_SCHEMA]];
		}

		if (array_key_exists('userName', $this->request_data) || array_key_exists('id', $this->request_data)) {
			if ($this->response_data === []) {
				return [
					'schemas' => [User::SCIM_SCHEMA],
					'totalResults' => 0,
					'Resources' => $this->response_data
				];
			}

			return $this->wrapUserData($this->response_data);
		}

		$total_users = count($this->response_data);
		$resources = [];

		foreach ($this->response_data as $resource) {
			$user = $this->wrapUserData($resource);
			unset($user['schemas']);

			$resources[] = $user;
		}

		return [
			'schemas' => [HttpResponse::SCHEMA_LIST],
			'totalResults' => $total_users,
			'startIndex' => array_key_exists('startIndex', $this->request_data)
				? max($this->request_data['startIndex'], 1)
				: 1,
			'itemsPerPage' => min($total_users, max($total_users, 0)),
			'Resources' => $resources
		];
	}

	private function prepareGroupsResponse(): array {
		if ($this->method === 'delete' ) {
			return ['schemas'	=> [Group::SCIM_SCHEMA]];
		}

		if (array_key_exists('id', $this->request_data) || array_key_exists('displayName', $this->request_data)) {
			if ($this->response_data === []) {
				return [
					'schemas' => [HttpResponse::SCHEMA_LIST],
					'Resources' => []
				];
			}

			$data = [
				'schemas' => [Group::SCIM_SCHEMA],
				'id' => $this->response_data['id'],
				'displayName' => $this->response_data['displayName'],
				'members' => []
			];

			if ($this->response_data['users'] != []) {
				$data['members'] = $this->wrapGroupUserData($this->response_data['users']);
				unset($this->response_data['users']);
			}

			return $data;
		}

		$total_groups = count($this->response_data);
		$data = [
			'schemas' => [HttpResponse::SCHEMA_LIST],
			'totalResults' => $total_groups,
			'Resources' => []
		];

		$data['startIndex'] = array_key_exists('startIndex', $this->request_data)
			? max($this->request_data['startIndex'], 1)
			: 1;

		$data['itemsPerPage'] = array_key_exists('count', $this->request_data)
			? min($total_groups, max($this->request_data['count'], 0))
			: $total_groups;

		foreach ($this->response_data as $groupid => $group_data) {
			$data['Resources'][] = [
				'id' => (string) $groupid,
				'displayName' => $group_data['name'],
				'members' => $this->wrapGroupUserData($group_data['users'])
			];
		}

		return $data;
	}

	/**
	 * Adds schema and key names that are necessary for Users response.
	 *
	 * @param array $user_data  User data returned from User SCIM class.
	 *
	 * @return array
	 */
	private function wrapUserData(array $user_data): array {
		$data = [
			'schemas'	=> [User::SCIM_SCHEMA],
			'id' 		=> $user_data['userid']
		];

		if (array_key_exists('username', $user_data)) {
			$data['userName'] = $user_data['username'];
		}

		unset($user_data['userid'], $user_data['username']);

		return $data + $user_data;
	}

	private function wrapGroupUserData(array $users): array {
		$members = [];

		if ($users != []) {
			foreach ($users as $user) {
				$members[] = [
					'value' => $user['userid'],
					'display' => $user['username']
				];
			}
		}

		return $members;
	}
}
