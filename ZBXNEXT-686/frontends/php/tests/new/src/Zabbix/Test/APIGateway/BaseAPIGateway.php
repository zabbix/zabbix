<?php

namespace Zabbix\Test\APIGateway;

use Zabbix\Test\APITestRequest;

abstract class BaseAPIGateway implements APIGatewayInterface {
	/**
	 * Current username.
	 *
	 * @var string
	 */
	protected $username;

	/**
	 * Current password.
	 *
	 * @var string
	 */
	protected $password;

	/**
	 * Current authorization key
	 *
	 * @var string
	 */
	protected $authKey;

	/**
	 * Current test case configuration
	 *
	 * @var array
	 */
	protected $testConfig;

	public function configure(array $params, array $testConfig) {
		$this->testConfig = $testConfig;
	}

	protected function authorize() {
		if (null !== $this->authKey) {
			return $this->authKey;
		}

		$result = $this->execute(new APITestRequest(
			'user.login',
			array(
				'user' => $this->testConfig['username'],
				'password' => $this->testConfig['password']
			))
		);

		if ($result->isError()) {
			throw new \RuntimeException(
				sprintf('API authentication error: "%s"', json_encode($result->getResponseData()))
			);
		}

		if (!preg_match('/^[a-z0-9]{32}$/', $result->getResult())) {
			throw new \RuntimeException(
				sprintf('API auth token does not much expected format, "%s" given',
					json_encode($result->getResponseData())
				)
			);
		}

		$this->authKey = $result->getResult()['result'];

		return $this->authKey;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setCredentials($username, $password)
	{
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUsername()
	{
		return $this->username;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPassword()
	{
		return $this->password;
	}


}
