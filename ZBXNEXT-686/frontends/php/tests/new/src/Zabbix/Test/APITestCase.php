<?php

namespace Zabbix\Test;

use Symfony\Component\Yaml\Yaml;

class APITestCase extends BaseAPITestCase {

	/**
	 * Parsed step data.
	 *
	 * @var string
	 */
	protected $stepData;

	/**
	 * Stack of requests / responses
	 *
	 * @var array
	 */
	protected $stepStack;

	/**
	 * Executes test by file-based scenarios.
	 *
	 * @param string $name filename relative to ./tests/data/file, with no extension.
	 * @throws \Exception
	 */
	protected function processFileTest($name) {
		$path = ZABBIX_NEW_TEST_DIR . '/tests/data/file/'.$name.'.yml';

		if (!is_readable($path)) {
			throw new \Exception(sprintf('Test file "%s" not readable, expected location "%s"', $name, $path));
		}

		$this->stepData = Yaml::parse(file_get_contents($path));

		$gateway = $this->getGateway();

		foreach ($this->stepData as $stepName => $definition) {
			$this->stepStack[$stepName] = array();

			if (!isset($definition['request'])) {
				throw new \Exception(sprintf('Each step should have "request" field, "%s" has not', $stepName));
			}

			$request = $definition['request'];

			if (!isset($request['method']) || !is_string($request['method']) ||
				!isset($request['params']) || !is_array($request['params'])
			) {
				throw new \Exception(sprintf('Each step should have string "method" and array "params" (failing step: "%s")', $stepName));
			}

			$request = array_merge(
				array(
					'jsonrpc' => '2.0',
					'id' => rand()
				), $request
			);

			$apiRequest = new APITestRequest($request['method'], $request['params'], $request['id'], $request);

			$this->stepStack[$stepName]['request'] = $apiRequest;

			$apiResponse = $gateway->execute($apiRequest);

			$this->stepStack[$stepName]['response'] = $apiResponse;

			// now we verify that the response is what we expected
			if (!isset($definition['expect']) || !in_array($definition['expect'], array('response', 'exception'))) {
				throw new \Exception('Wrong step definition: do not know what to expect, must be "expect: response|exception"');
			}

			$expectation = $definition['expect'];

			if ($expectation == 'response' && !$apiResponse->isResponse()) {
				throw new \Exception(sprintf('Expected plain response from api on step "%s", did not get one.', $stepName));
			}

			if ($expectation == 'exception' && !$apiResponse->isException()) {
				throw new \Exception(sprintf('Expected exception response from api on step "%s", did not get one', $stepName));
			}

			if ($expectation == 'response') {
				// TODO: validators here
				$responseExpectation = $definition['response'];

				if (count($responseExpectation) != 1 || key($responseExpectation) != 'equals') {
					throw new \Exception('Sorry, now we only support single key "equals" under "response" section. This is a "TODO"');
				}

				if ($responseExpectation['equals'] != $apiResponse->getResult()) {
					throw new \Exception(
						sprintf('Response for step "%s" does not look like we expected ("%s" expected, "%s" given).',
							$stepName,
							json_encode($responseExpectation['equals']),
							json_encode($apiResponse->getResult())
						));
				}
			}
			elseif ($expectation == 'exception') {
				die('not processing exceptions yet');
			}
			else {
				throw new \Exception(sprintf('\Expectation "%s" is not yet supported', $expectation));
			}
		}
	}

}
