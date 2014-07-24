<?php

namespace Zabbix\Test\APIGateway;

use Zabbix\Test\APITestRequest;
use Zabbix\Test\APITestResponse;

interface APIGatewayInterface {
	/**
	 * Performs API request. Errors are determined by $response->isApiError().
	 *
	 * @param APITestRequest $request
	 * @return APITestResponse
	 */
	public function execute(APITestRequest $request);

	/**
	 * @param string $username
	 * @param string $password
	 */
	public function setCredentials($username, $password);

	/**
	 * Gateway - dependent configuration function.
	 *
	 * @param array $params
	 * @throws \Exception
	 */
	public function configure(array $params);

	/**
	 * Current username getter.
	 *
	 * @return string
	 */
	public function getUsername();

	/**
	 * Current password getter.
	 *
	 * @return string
	 */
	public function getPassword();
}
