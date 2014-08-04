<?php

namespace Zabbix\Test;

use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Rules\AbstractRule;
use Symfony\Component\Yaml\Yaml;
use Respect\Validation\Validator as v;

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

		if (!isset($this->stepData['steps']) || !is_array($this->stepData['steps'])) {
			throw new \Exception('Each test file should have top-level array "steps", can not find one in yours');
		}

		foreach ($this->stepData['steps'] as $stepName => $definition) {
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
			if (!isset($definition['expect']) || !in_array($definition['expect'], array('response', 'error'))) {
				throw new \Exception('Wrong step definition: do not know what to expect, must be "expect: response|error"');
			}

			$expectation = $definition['expect'];

			if ($expectation == 'response' && !$apiResponse->isResponse()) {
				throw new \Exception(
					sprintf('Expected plain response from api on step "%s", did not get one, got exception "%s" data "%s".',
						$stepName,
						$apiResponse->getMessage(),
						$apiResponse->getData()
					))
				;
			}

			if ($expectation == 'exception' && !$apiResponse->isException()) {
				throw new \Exception(sprintf('Expected exception response from api on step "%s", did not get one', $stepName));
			}

			if ($expectation == 'response') {
				$responseExpectation = $definition['response'];
				$responseExpectation = $this->expandStepVariables($responseExpectation);

				reset($responseExpectation);

				$this->validate($responseExpectation, $apiResponse->getResult());
			}
			elseif ($expectation == 'error') {
				$errorExpectation = $definition['error'];
				$errorExpectation = $this->expandStepVariables($errorExpectation);

				if (!isset($errorExpectation['message']) || !isset($errorExpectation['code'])) {
					throw new \Exception('Error expectation should have at least "message" and "code" fields');
				}

				if ($errorExpectation['message'] != $apiResponse->getMessage()) {
					throw new \Exception(sprintf(
						'Expected error message "%s", "%s" given in step "%s"',
						$errorExpectation['message'],
						$apiResponse->getMessage(),
						$stepName
					));
				}

				if ($errorExpectation['code'] != $apiResponse->getCode()) {
					throw new \Exception(sprintf(
						'Expected error code "%d", "%d" given in step "%s"',
						$errorExpectation['code'],
						$apiResponse->getCode(),
						$stepName
					));
				}

				if (isset($errorExpectation['data']) &&
					$errorExpectation['data'] != $apiResponse->getData()
				) {
					throw new \Exception(sprintf(
						'Expected error data "%s", "%s" given in step "%s"',
						$errorExpectation['data'],
						$apiResponse->getData(),
						$stepName
					));
				}
			}
			else {
				throw new \Exception(sprintf('\Expectation "%s" is not yet supported', $expectation));
			}

			$this->processSqlAssertions($definition, $stepName);

			// each step is one assertion
			$this->addToAssertionCount(1);
		}
	}

	protected function processSqlAssertions($definition, $stepName) {
		if (isset($definition['sqlAssertions']) && is_array($definition['sqlAssertions'])) {
			foreach ($definition['sqlAssertions'] as $assertion) {
				if (!isset($assertion['sqlQuery'])) {
					throw new \Exception('Each "sqlAssertions" member must have sqlQuery parameter');
				}

				if (!isset($assertion['singleScalarResult']) && !isset($assertion['rowResult'])) {
					throw new \Exception('Each "sqlAssertions" member should contain "singleScalarResult" or "rowResult" parameter');
				}

				if (isset($assertion['singleScalarResult']) && isset($assertion['rowResult'])) {
					throw new \Exception('Each "sqlAssertions" member can not have both "singleScalarResult" and "rowResult" parameters');
				}

				$pdo = $this->getPdo();

				$result = $pdo->query($assertion['sqlQuery']);

				if (isset($assertion['singleScalarResult'])) {
					$value = $result->fetchColumn();

					if ($value != $assertion['singleScalarResult']) {
						throw new \Exception(
							sprintf('Sql assertion "singleScalarResult" failed, step "%s", query "%s", expected "%s", got "%s"',
								$stepName, $assertion['sqlQuery'],
								$assertion['singleScalarResult'], $value
							));
					}

					$this->addToAssertionCount(1);
				}
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
	 * @todo: this should be cached
	 *
	 * @param array $keys
	 * @throws \Exception
	 * @return mixed
	 */
	protected function resolveStepVariable(array $keys) {
		// first key should be valid step name
		$stepName = array_shift($keys);

		if (!isset($this->stepStack[$stepName])) {
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

			if (!isset($this->stepStack[$stepName]['request'])) {
				throw new \Exception(sprintf('No request has been logged yet for step "%s"', $stepName));
			}

			/* @var $request APITestRequest */
			$request = $this->stepStack[$stepName]['request'];

			if ($subtype == 'method') {
				return $request->getMethod();
			}

			return $this->drillIn($request->getParams(), $keys);
		}

		if ($type == 'response') {
			$allow = array('result');

			if (!in_array($subtype, $allow)) {
				throw new \Exception(sprintf('Third part of request expression must be one of "%s", "%s" given',
						implode(', ', $allow),
						$subtype
					)
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

	/**
	 * Validate data according to rules; path holds current validator chain
	 *
	 * @param $definition
	 * @param $data
	 * @param array $path
	 * @throws \Exception
	 */
	protected function validate($definition, $data, $path = array()) {
		if (!is_array($definition)) {
			// shorthand syntax
			$this->validateSingle($data, $definition, $path);

			return;
		}

		foreach ($definition as $key => $rules) {
			if ($key == '_assert') {
				if (is_string($rules)) {
					// simple assertion
					$this->validateSingle($data, $rules, $path);

					continue;
				}
				elseif (is_array($rules)) {
					$path[] = $key;
					$this->validate($rules, $data, $path);

					continue;
				} else {
					throw new \Exception(
						sprintf('Wrong value for "_assert" key, not a string or array on path "%s"', implode('->', $path))
					);
				}
			}

			if ($key == '_keys') {
				if (!is_array($data)) {
					throw new \Exception(
						sprintf('Data for "_keys" is not an array on path "%s"', implode('->', $path))
					);
				}

				$keys = array_keys($data);
				$path[] = $key;

				$this->validate($rules, $keys, $path);

				continue;
			}

			if ($key == '_each') {
				if (!is_array($data)) {
					throw new \Exception(
						sprintf('Data for "_each" is not an array on path "%s"', implode('->', $path))
					);
				}

				foreach ($data as $index => $value) {
					$subPath = $path;
					$subPath[] = '_each['.$index.']';
					$this->validate($rules, $value, $subPath);
				}

				continue;
			}

			if ($key == '_equals') {
				if ($data != $rules) {
					throw new \Exception(sprintf('Data "%s" is not the same as expected ("%s") on path "%s"',
						json_encode($data),
						json_encode($rules),
						implode('->', $path)
					));
				}
				continue;
			}

			if (is_array($data) && array_key_exists($key, $data)) {
				$path[] = $key;

				$this->validate($rules, $data[$key], $path);

				continue;
			}

			throw new \Exception(
				sprintf('Do not know what to do processing key "%s" on path "%s"', $key, implode('->', $path))
			);
		}
	}

	/**
	 * Validates value against validator chain
	 *
	 * @param $value
	 * @param $ruleDefinition
	 * @param $path
	 * @throws \Exception
	 */
	protected function validateSingle($value, $ruleDefinition, $path) {
		$rules = explode('|', $ruleDefinition);
		$validator = v::create();

		foreach ($rules as $rule) {
			preg_match("/^(?'rule'[a-z]+)(\((?'params'[^)]+)\)){0,1}$/i", $rule, $matches);

			if (!isset($matches['rule'])) {
				throw new \Exception(sprintf('Can not parse validation rule "%s"', $rule));
			}

			$rule = $matches['rule'];

			if (isset($matches['params'])) {
				$params = explode(',', $matches['params']);
				$params = array_map(function ($value) {
					return trim($value);
				}, $params);
			} else {
				$params = array();
			}

			$validatorInstance = call_user_func_array(array($validator, $rule), $params);
			/* @var $validatorInstance AbstractRule */
			try {
				$validatorInstance->assert($value);
				$this->addToAssertionCount(1);
			} catch (\InvalidArgumentException $e) {
				throw new \Exception(
					sprintf('Rule "%s" failed for "%s" on path "%s"',
						$rule,
						ValidationException::stringify($value),
						implode('->', $path)
					)
				);
			}
		}
	}

}
