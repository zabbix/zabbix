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

use Throwable;

class ScimHttpResponse {

	private const SCIM_USER_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:User';
	private const SCIM_GROUP_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:Group';
	private const SCIM_LIST_RESPONSE_SCHEMA = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';

	/** @var array $response_data  Array of response data to be sent. */
	protected array $response_data = [];

	/** @var Throwable $exception */
	protected $exception = null;

	/** @var int $response_code  HTTP response status code. */
	protected $response_code = 200;

	/** @var string $class  Name of the class that response is received from */
	protected string $class;

	/** @var string $method  Name of the method that response is received from */
	protected string $method;

	/** @var array $request_data Array of request data that was sent */
	protected array $request_data;

	public function __construct(string $class = '', string $method = '', array $request_data = [], array $response_data = []) {
		$this->class = $class;
		$this->method = $method;
		$this->request_data = $request_data;
		$this->response_data = $response_data;
	}

	public function setResponseData(array $data) {
		$this->response_data = $data;

		return $this;
	}

	public function getResponseData(): array {
		return $this->response_data;
	}

	public function setException(Throwable $e) {
		$this->exception = $e;
		$this->setResponseCode($e->getCode());

		return $this;
	}

	public function setResponseCode($response_code) {
		$this->response_code = $response_code;

		return $this;
	}

	/**
	 * Send HTTP response.
	 *
	 * @return void
	 */
	public function send(): void {
		if ($this->exception instanceof Throwable) {
			$this->setResponseData([
				'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
				'detail' => $this->exception->getMessage(),
				'status' => $this->exception->getCode()
			]);
		}
		elseif ($this->class === 'users') {
			$this->setResponseData($this->prepareUsersResponse());
		}
		else {
			$this->setResponseData($this->prepareGroupsResponse());
		}

		header('Content-Type: application/json', true, $this->response_code);
		echo json_encode($this->getResponseData());
		exit;
	}

	/**
	 * Prepares Users request response based on the data provided.
	 *
	 * @return array
	 */
	private function prepareUsersResponse(): array {
		if ($this->method === 'delete' ) {
			return ['schemas'	=> [self::SCIM_USER_SCHEMA]];
		}

		if (array_key_exists('userName', $this->request_data) || array_key_exists('id', $this->request_data)) {
			if ($this->response_data === []) {
				return [
					'schemas' => [self::SCIM_USER_SCHEMA],
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
			'schemas' => [self::SCIM_LIST_RESPONSE_SCHEMA],
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
			return ['schemas'	=> [self::SCIM_GROUP_SCHEMA]];
		}

		if (array_key_exists('id', $this->request_data) || array_key_exists('displayName', $this->request_data)) {
			if ($this->response_data === []) {
				return [
					'schemas' => [self::SCIM_LIST_RESPONSE_SCHEMA],
					'Resources' => []
				];
			}

			$data = [
				'schemas' => [self::SCIM_GROUP_SCHEMA],
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
			'schemas' => [self::SCIM_LIST_RESPONSE_SCHEMA],
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
			'schemas'	=> [self::SCIM_USER_SCHEMA],
			'id' 		=> $user_data['userid'],
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
