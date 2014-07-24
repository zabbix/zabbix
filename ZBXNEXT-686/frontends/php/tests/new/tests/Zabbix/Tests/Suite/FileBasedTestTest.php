<?php

namespace Zabbix\Tests\Suite;

use Zabbix\Test\APIGateway\MockAPIGateway;
use Zabbix\Test\APITestCase;

class FileBasedTestTest extends APITestCase {
	/**
	 * General test; no assertions, just should not throw errors.
	 */
	public function testStepVariableSubstitution() {
		$this->processFileTest('suite/variableSubstitution');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getGateway()
	{
		$gateway = new MockAPIGateway();
		$gateway->configure(array(
			'file' => ZABBIX_NEW_TEST_DIR.'/tests/data/file/suite/fileBasedTestTestMock.yml'
		));

		return $gateway;
	}

}
