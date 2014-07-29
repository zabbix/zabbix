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
	 * @expectedException 		  Exception
	 * @expectedExceptionMessage  Rule "int" failed for "a"
	 */
	public function testValidationIntegerKeys() {
		$this->processFileTest('suite/validationIntegerKeys');
	}

	/**
	 * @expectedException		  Exception
	 * @expectedExceptionMessage  Rule "notEmpty" failed for ""
	 */
	public function testValidationRequired() {
		$this->processFileTest('suite/validationRequired');
	}


	public function testValidationSuccess() {
		$this->processFileTest('suite/validationSuccess');
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
