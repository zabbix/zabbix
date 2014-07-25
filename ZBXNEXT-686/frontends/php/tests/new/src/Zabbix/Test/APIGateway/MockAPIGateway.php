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
	public function execute(APITestRequest $request) {
		if (!isset($this->stepData['requests'][$request->getId()])) {
			throw new \Exception(sprintf('No mock request data for request id "%d"', $request->getId()));
		}

		$mockRequest = $this->stepData['requests'][$request->getId()];

		$mockRequest = array_merge(array(
				'method' => 'unknown',
				'params' => array()),
			$mockRequest
		);

		if ($mockRequest['method'] != $request->getMethod()) {
			throw new \Exception(sprintf('Method error, "%s" expected, "%s" given', $mockRequest['method'], $request->getMethod()));
		}

		if ($mockRequest['params'] != $request->getParams()) {
			throw new \Exception(
				sprintf('Unexpected params for request id "%d" (hint: check key order), expected: "%s", got "%s"',
					$request->getId(),
					json_encode($mockRequest['params']),
					json_encode($request->getParams())
				)
			);
		}

		// all ok, we should prepare an response.
		if (!isset($this->stepData['responses'][$request->getId()])) {
			throw new \Exception(sprintf('No mock response data for request id "%d"', $request->getId()));
		}

		$mockResponse = $this->stepData['responses'][$request->getId()];

		if (!isset($mockResponse['type']) || !in_array($mockResponse['type'], array('response', 'exception'))) {
			throw new \Exception(sprintf('Can not resolve response type for request "%d", must be "response" or "exception"'));
		}

		if ($mockResponse['type'] == 'response') {
			$mockResponse = array_merge(array(
				array(
					'result' => array()
				)
			), $mockResponse);

			return APITestResponse::createTestResponse($mockResponse['result'], $request->getId());
		} else {
			die('exception not implemented');
		}
	}

	/**
	 * Configures test. Requires single parameter 'file' with mock data.
	 *
	 * @param array $params
	 * @throws \Exception
	 */
	public function configure(array $params) {
		if (!isset($params['file'])) {
			throw new \Exception('A configuration for MockAPIGateway must contain single "file" field');
		}

		$this->stepData = Yaml::parse(file_get_contents($params['file']));

		if (!isset($this->stepData['requests']) || !is_array($this->stepData['requests']) ||
			!isset($this->stepData['responses']) || !is_array($this->stepData['responses'])) {
			throw new \Exception('Mock file should contain two arrays, "requests" and "responses"');
		}
	}

}
