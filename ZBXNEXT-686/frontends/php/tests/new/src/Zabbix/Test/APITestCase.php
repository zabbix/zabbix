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

			$gateway->execute($apiRequest);
		}
	}

}
