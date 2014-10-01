<?php

namespace Zabbix\Test;

use Symfony\Component\Yaml\Yaml;
use Zabbix\Test\Fixtures\FixtureLoader;

class FileApiTestCase extends ApiTestCase {

	/**
	 * @var \CArrayMacroResolver
	 */
	protected $macroResolver;

	public function __construct($name = null, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);

		$this->macroResolver = new \CArrayMacroResolver();
	}

	protected function parseTestFile($name) {
		$path = ZABBIX_NEW_TEST_DIR . '/tests/yaml/'.$name.'.yml';

		if (!is_readable($path)) {
			throw new \Exception(sprintf('Test file "%s" not readable, expected location "%s"', $name, $path));
		}

		return Yaml::parse(file_get_contents($path));
	}

	protected function runTestFile($file) {
		$test = $this->parseTestFile($file);

		$fixtures = (isset($test['fixtures'])) ? $test['fixtures'] : array();

		// always load a user and a user group
		$fixtures = array_merge(
			array(
				'base' => array(
					'type' => FixtureLoader::TYPE_INCLUDE,
					'params' => array(
						'file' => 'base'
					)
				)
			),
			$fixtures
		);

		$fixtures = $this->loadFixtures($fixtures);

		$this->login('Admin', 'zabbix');
		$this->runSteps($test['steps'], $fixtures);
	}

	/**
	 * Executes test by file-based scenarios.
	 *
	 * @param array $steps
	 * @param array $fixtures
	 *
	 * @throws \Exception
	 */
	protected function runSteps(array $steps, array $fixtures) {
		foreach ($steps as $stepName => &$definition) {
			if (!isset($definition['request'])) {
				throw new \Exception(sprintf('Each step should have "request" field, "%s" has not', $stepName));
			}

			if (is_array($definition['request']['params'])) {
				$definition['request']['params'] = $this->resolveStepMacros($definition['request']['params'], $steps, $fixtures);
			}

			$expectedResponse = $definition['response'];

			$apiResponse = $this->executeRequest($this->createRequest($definition['request']));

			$steps[$stepName]['response'] = $apiResponse->getResult();

			// now we verify that the response is what we expected
			if (!isset($definition['expect']) || !in_array($definition['expect'], array('result', 'error'))) {
				throw new \Exception('Wrong step definition: do not know what to expect, must be "expect: result|error"');
			}

			$expectation = $definition['expect'];

			if ($apiResponse->isError() && $expectation !== 'error' || !$apiResponse->isError() && $expectation === 'error') {
				throw new \Exception(
					sprintf('Expected "%s" from api on step "%s", got "%s" instead: %s',
						$expectation,
						$stepName,
						$apiResponse->isError() ? 'error' : 'result',
						json_encode($apiResponse->getResponseData())
					))
				;
			}

			if ($expectation == 'result' || $expectation == 'error') {
				$expectedResponse = $this->resolveStepMacros($expectedResponse, $steps, $fixtures);

				$this->assertResponse($expectedResponse, $apiResponse);
			}
			else {
				throw new \Exception(sprintf('\Expectation "%s" is not yet supported', $expectation));
			}

			// each step is one assertion
			$this->addToAssertionCount(1);
		}
		unset($definition);
	}

	/**
	 * Expands step variables like "@step1.data"
	 *
	 * @param array $data
	 * @param array $steps
	 * @param array $fixtures
	 *
	 * @return array
	 */
	protected function resolveStepMacros(array $data, array $steps, array $fixtures) {
		return $this->macroResolver->resolve($data, array(
			'steps' => $steps,
			'fixtures' => $fixtures
		));
	}

	/**
	 * Create a request array from the request defined in the YAML file.
	 *
	 * The values in the request definition will override any default values.
	 *
	 * @param array $requestDefinition
	 *
	 * @return array
	 */
	protected function createRequest(array $requestDefinition) {
		// if the method request authentication - use the session of currently authenticated user
		$auth = null;
		if (isset($requestDefinition['method']) && $this->requiresAuthentication($requestDefinition['method'])) {
			$auth = $this->getAuth();
		}

		return array_merge(array(
			'jsonrpc' => '2.0',
			'id' => rand(),
			'method' => null,
			'params' => null,
			'auth' => $auth
		), $requestDefinition);
	}
}
