<?php

namespace Zabbix\Test;

use Symfony\Component\Yaml\Yaml;
use Zabbix\Test\Fixtures\FixtureLoader;

class FileApiTestCase extends ApiTestCase {

	/**
	 * @var \CArrayMacroResolver
	 */
	private static $macroResolver;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		self::$macroResolver = new \CArrayMacroResolver();
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

			$response = $this->executeRequest($this->createRequest($definition['request']));

			$steps[$stepName]['response'] = $response->getBody();

			// assert error
			if (isset($definition['assertError'])) {
				$expectedError = $this->resolveStepMacros($definition['assertError'], $steps, $fixtures);
				$this->assertError($expectedError, $response);
			}

			// assert result
			if (isset($definition['assertResult'])) {
				$expectedResult = $this->resolveStepMacros($definition['assertResult'], $steps, $fixtures);
				$this->assertResult($expectedResult, $response);
			}

			// assert response
			if (isset($definition['assertResponse'])) {
				$expectedResponse = $this->resolveStepMacros($definition['assertResponse'], $steps, $fixtures);
				$this->assertResponse($expectedResponse, $response);
			}
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
		return self::$macroResolver->resolve($data, array(
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
