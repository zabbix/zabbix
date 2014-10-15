<?php


class CFileApiTestCase extends CApiTestCase {

	/**
	 * Object for resolving array macros.
	 *
	 * @var CArrayMacroResolver
	 */
	private static $macroResolver;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		// set up macro resolver
		self::$macroResolver = new CArrayMacroResolver();
	}

	/**
	 * Return the path to the test file directory.
	 *
	 * @return string
	 */
	protected function getTestFileDir() {
		return API_TEST_DIR.'/tests/yaml';
	}

	/**
	 * Run the test from the file.
	 *
	 * @param $path
	 */
	protected function runTestFile($path) {
		$test = $this->parseTestFile($path);

		$fixtures = (isset($test['fixtures'])) ? $test['fixtures'] : array();

		// always load a user and a user group
		$fixtures = array_merge(
			array(
				'base' => array(
					'type' => CFixtureFactory::TYPE_INCLUDE,
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
	 * Parse the test file and return the contents as an array.
	 *
	 * @param string $path
	 *
	 * @return array
	 */
	protected function parseTestFile($path) {
		return yaml_parse_file($this->getTestFileDir().'/'.$path);
	}

	/**
	 * Executes test by file-based scenarios.
	 *
	 * @param array $steps
	 * @param array $fixtures
	 *
	 * @throws InvalidArgumentException	if a step has been defined incorrectly
	 */
	protected function runSteps(array $steps, array $fixtures) {
		foreach ($steps as $stepName => &$definition) {
			if (!isset($definition['request'])) {
				throw new InvalidArgumentException(sprintf('Each step should have "request" field, "%s" has not', $stepName));
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

	/**
	 * Returns the data for a test file provider containing the path to the test file.
	 *
	 * @return array
	 */
	protected function provideTestFiles() {
		$dir = $this->getTestFileDir();

		$rs = array();
		foreach ($this->getTestFileIterator($dir) as $file) {
			/* @var SplFileInfo $file */
			$subPath = ltrim(str_replace($dir, '', $file->getPathname()), '/');

			$rs[$subPath] = array($subPath);
		}

		return $rs;
	}

	/**
	 * Returns an iterator over test files.
	 *
	 * @param string $dir	directory to look for the files in
	 *
	 * @return Iterator
	 */
	protected function getTestFileIterator($dir) {
		$i = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

		return new RegexIterator($i, '/^.+\.yml$/i');
	}
}
