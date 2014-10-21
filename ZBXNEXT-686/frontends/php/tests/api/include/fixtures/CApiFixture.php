<?php

/**
 * A class for loading fixtures using the API.
 */
class CApiFixture extends CFixture {

	/**
	 * Object to use for API requests.
	 *
	 * @var CApiWrapper
	 */
	protected $apiWrapper;

	/**
	 * @param CApiWrapper $apiWrapper	object to use for API requests
	 */
	public function __construct(CApiWrapper $apiWrapper) {
		$this->apiWrapper = $apiWrapper;
	}

	/**
	 * Load a fixture that performs an API request.
	 *
	 * Supported parameters:
	 * - method	- name of the method to call
	 * - params	- array of parameters for the method
	 */
	public function load(array $params) {
		$this->checkMissingParams($params, array('method', 'params'));

		// if the client is not authenticated - log in
		// TODO: pass credentials as a parameter
		if (!$this->apiWrapper->auth) {
			$rs = $this->apiWrapper->callMethod('user.login', array(
				'user' => 'Admin',
				'password' => 'zabbix'
			));

			if ($rs->isError()) {
				throw new UnexpectedValueException(sprintf('Cannot authenticate to load API fixture: %1$s', $rs->getErrorData()));
			}

			$this->apiWrapper->auth = $rs->getResult();
		}

		$rs = $this->apiWrapper->callMethod($params['method'], $params['params']);

		if ($rs->isError()) {
			// treat all API errors as argument exceptions
			throw new InvalidArgumentException($rs->getErrorData());
		}

		return $rs->getResult();
	}

}
