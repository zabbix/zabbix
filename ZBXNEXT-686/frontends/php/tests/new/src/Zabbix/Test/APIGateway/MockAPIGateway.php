<?php

namespace Zabbix\Test\APIGateway;

use Symfony\Component\Yaml\Yaml;
use Zabbix\Test\APITestRequest;
use Zabbix\Test\APITestResponse;

/**
 * This class acts as API Gateway yet loads responses from mock file. Responses and requests are determined by id, like:
 * requests:
 *   2:
 *     method: asdf
 *     params: [1,2,3]
 * thus, a request with id = 2 must be exact as above
 * same syntax is used for responses:
 * responses:
 *    2:
 *       type: response
 *       result: [4,5,6]
 *  thus for a request with id 2 will be returned the response given
 */
class MockAPIGateway extends BaseAPIGateway {
	/**
	 * Parsed scenario data
	 *
	 * @var array
	 */
	protected $stepData;

	/**
	 * {@inheritdoc}
	 */
	public function apiRequest(APITestRequest $request) {

	}

	/**
	 * Configures test. Requires single parameter 'file'
	 * @param array $params
	 */
	public function configure(array $params) {
		if (!isset($params['file'])) {
			throw new \Exception('A configuration for MockAPIGateway must contain single "file" field');
		}

		$this->stepData = Yaml::parse(file_get_contents($params['file']));
	}

}
