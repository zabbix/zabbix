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
					'id' => rand(),
					'params' => $request['params']
				), $request
			);

			$request['params'] = $this->expandStepVariables($request['params']);

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
				$responseExpectation = $this->expandStepVariables($responseExpectation);

				reset($responseExpectation);

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

	/**
	 * Expands step variables like "@step1.data"
	 *
	 * @param array $data
	 * @return array
	 */
	protected function expandStepVariables($data) {
		array_walk_recursive($data, function (&$value, $index) {
			if (!is_string($value)) {
				return;
			}

			// TODO: this should be refactored to single regular expression like (@((([a-z0-9]+)[.[\]]*)+)@)
			// extract variables
			preg_match_all("/@(?:[a-z0-9[\\].]+)@/i", $value, $matches);

			if (count($matches) == 1 && count($matches[0]) > 0) {
				foreach ($matches[0] as $expression) {
					$keys = preg_split('/(\[|]\.|\.|\])/', trim($expression, '@'), -1, PREG_SPLIT_NO_EMPTY);

					if (count($keys) > 0) {
						try {
							$newValue = $this->resolveStepVariable($keys);
						} catch (\Exception $e) {
							throw new \Exception(
								sprintf('Parsing of expression "%s" failed with message "%s"',
									$expression,
									$e->getMessage()
								)
							);
						}

						$value = str_replace($expression, $newValue, $value);
					}
					else {
						throw new \Exception(sprintf('Problem parsing expression "%s"', $expression));
					}
				}
			}
		});

		return $data;
	}

	/**
	 * Resolves actual value from array of keys like ['step1', 'response', 'hosts', 0, 'id']
	 *
	 * @param array $keys
	 * @throws \Exception
	 * @return mixed
	 */
	protected function resolveStepVariable(array $keys) {
		// first key should be valid step name
		$stepName = array_shift($keys);

		if (!isset($this->stepData[$stepName])) {
			throw new \Exception(sprintf('No data for step "%s"', $stepName));
		}

		// second key is request/response; exceptions are not supported (since back-referencing exception message is
		// a bit senseless
		$type = array_shift($keys);

		if (!in_array($type, array('request', 'response'))) {
			throw new \Exception(sprintf('Second part of the expressions should be "request" or "response", "%s" given', $type));
		}

		// third key is method, params or result
		$subtype = array_shift($keys);

		if ($type == 'request') {
			$allow = array('method', 'params');

			if (!in_array($subtype, $allow)) {
				throw new \Exception(
					sprintf('Third part of request expression must be one of "%s", "%s" given',
						implode(', ', $allow),
						$subtype
					)
				);
			}

			die('request no working yet');
		}

		if ($type == 'response') {
			$allow = array('result');

			if (!in_array($subtype, $allow)) {
				throw new \Exception('Third part of request expression must be one of "%s", "%s" given',
					implode(', ', $allow),
					$subtype
				);
			}

			if (!isset($this->stepStack[$stepName]['response'])) {
					throw new \Exception(sprintf('No response has been logged yet for step "%s"', $stepName));
				}

				/* @var $response \Zabbix\Test\APITestResponse */
				$response = $this->stepStack[$stepName]['response'];

				return $this->drillIn($response->getResult(), $keys);
		}
	}

	/**
	 * Drill in function - returns item from array $data defined by $keys.
	 *
	 * @param $data
	 * @param $keys
	 * @return mixed
	 * @throws \Exception
	 */
	protected function drillIn($data, $keys) {
		foreach ($keys as $key) {
			if (!is_array($data)) {
				throw new \Exception(sprintf(
					'Data nto an array for key "%s", have we gone too deep?', $key
				));
			}
			elseif(!isset($data[$key])) {
				throw new \Exception(sprintf(
					'No key "%s" for data with keys "%s", have we gone too deep?', $key, implode(', ', array_keys($data))
				));
			}

			$data = $data[$key];
		}

		return $data;
	}

}
