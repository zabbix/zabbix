<?php

namespace Zabbix\Test;

use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Rules\AbstractRule;
use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Yaml\Yaml;
use Respect\Validation\Validator as v;

class FileApiTestCase extends ApiTestCase {

	/**
	 * Parsed step data.
	 *
	 * @var string
	 */
	protected $stepData;

	/**
	 * @var \CArrayMacroResolver
	 */
	protected $macroResolver;

	/**
	 * Stack of requests / responses
	 *
	 * TODO: remove it and replace with a local variable
	 *
	 * @var array
	 */
	protected $stepStack;

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
		$fixtures = $this->loadFixtures($test['fixtures']);

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
		$gateway = $this->getGateway();

		foreach ($steps as $stepName => &$definition) {
			if (!isset($definition['request'])) {
				throw new \Exception(sprintf('Each step should have "request" field, "%s" has not', $stepName));
			}

			$definition['request']['params'] = $this->resolveStepMacros($definition['request']['params'], $steps, $fixtures);

			$request = $definition['request'];
			$expectedResponse = $definition['response'];

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

			$apiRequest = new APITestRequest($request['method'], $request['params'], $request['id'], $request);

			$apiResponse = $gateway->execute($apiRequest);

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

				$this->validate($expectedResponse, $apiResponse->getResponseData());
			}
			else {
				throw new \Exception(sprintf('\Expectation "%s" is not yet supported', $expectation));
			}

			$this->processSqlAssertions($definition, $stepName);

			// each step is one assertion
			$this->addToAssertionCount(1);
		}
		unset($definition);
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
				elseif (isset($assertion['rowResult'])) {
					$expectation = $this->resolveStepMacros($assertion['rowResult']);

					$realResult = array();

					while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
						$realResult[] = $row;
					}

					foreach ($expectation as $key => $expectedRow) {
						foreach ($realResult as $resultKey => $resultRow) {
							if ($resultRow == $expectedRow) {
								unset($expectation[$key]);
								unset($realResult[$resultKey]);
							}
						}
					}

					if (count($expectation) != 0) {
						$table = new TableHelper();

						$table->addRows($expectation);

						$output = new BufferedOutput();
						$table->render($output);

						throw new \Exception(
							sprintf('Sql assertion for step "%s" failed for query "%s": the following rows are expected but not present in the result set:%s',
								$stepName,
								$assertion['sqlQuery'],
								PHP_EOL.$output->fetch()
							)
						);
					}

					if (count($realResult) != 0) {
						$table = new TableHelper();

						$table->addRows($realResult);

						$output = new BufferedOutput();
						$table->render($output);

						throw new \Exception(
							sprintf('Sql assertion for step "%s" failed for query "%s": the following extra rows are present in a result set:%s',
								$stepName,
								$assertion['sqlQuery'],
								PHP_EOL.$output->fetch()
							)
						);
					}

					$this->addToAssertionCount(1);
				}
				else {
					throw new \Exception(
						sprintf('Hm. Do not know what to do with sql assertions in step "%s", probably unfinished case?',
							$stepName
						)
					);
				}
			}
		}
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
	 * Validate data according to rules; path holds current validator chain
	 *
	 * @param $definition
	 * @param $data
	 * @throws \Exception
	 */
	protected function validate($definition, $data) {
		$validator = new \CTestSchemaValidator(array('schema' => $definition));
		if (!$validator->validate($data)) {
			throw new \Exception($validator->getError());
		}
	}

	protected function loadFixtures(array $fixtures) {
		return $this->getFixtureLoader()->load($fixtures);
	}
}
