<?php

namespace Zabbix\Test\Fixtures;

class ApiFixture extends Fixture {

	/**
	 * @var \CApiWrapper
	 */
	protected $apiWrapper;

	public function __construct(\CApiWrapper $apiWrapper) {
		$this->apiWrapper = $apiWrapper;
	}

	public function load(array $params) {
		// if the client is not authenticated - log in
		if (!$this->apiWrapper->auth) {
			$rs = $this->apiWrapper->callMethod('user', 'login', array(
				'user' => 'Admin',
				'password' => 'zabbix'
			));

			if ($rs->errorCode) {
				throw new \Exception(sprintf('Cannot authenticate to load API fixture: %1$s', $rs->errorMessage));
			}

			$this->apiWrapper->auth = $rs->data;
		}

		list($api, $method) = explode('.', $params['method']);

		$rs = $this->apiWrapper->callMethod($api, $method, $params['params']);

		if ($rs->errorCode) {
			throw new \Exception($rs->errorMessage);
		}

		$data = $rs->data;
		$ids = reset($data);

		return $ids;
	}

}
