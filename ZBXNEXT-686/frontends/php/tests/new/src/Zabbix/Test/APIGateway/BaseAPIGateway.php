<?php

namespace Zabbix\Test\APIGateway;

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
