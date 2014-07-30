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
	 * @expectedExceptionMessage  Rule "int" failed for "a" on path "_assert->_keys->_each[0]"
	 */
	public function testValidationIntegerKeys() {
		$this->processFileTest('suite/validationIntegerKeys');
	}

	/**
	 * @expectedException		  Exception
	 * @expectedExceptionMessage  Rule "notEmpty" failed for "" on path "_assert->_keys->_each[0]->id"
	 */
	public function testValidationRequired() {
		$this->processFileTest('suite/validationRequired');
	}

	/**
	 * @expectedException		  Exception
	 * @expectedExceptionMessage  Rule "length" failed for "a very long hostname that does not match validation rule" on path "_assert->_keys->_each[0]->id->hostname"
	 */
	public function testValidationLongHostnames() {
		$this->processFileTest('suite/validationLongHostnames');
	}

	/**
	 * @expectedException		  Exception
	 * @expectedExceptionMessage  Rule "string" failed for "-7" on path "_assert->_keys->_each[1]->id->hostname->templates->_each[1]"
	 */
	public function testValidationType() {
		$this->processFileTest('suite/validationType');
	}

	public function testValidationSuccessSequence() {
		$this->processFileTest('suite/validationSuccessSequence');
	}

	/**
	 * @expectedException		  Exception
	 * @expectedExceptionMessage  Rule "sequence" failed for "Array" on path "_assert->_keys"
	 */
	public function testValidationFailSequence1() {
		$this->processFileTest('suite/validationFailSequence1');
	}

	/**
	 * @expectedException		  Exception
	 * @expectedExceptionMessage  Rule "sequence" failed for "Array" on path "_assert->_keys"
	 */
	public function testValidationFailSequence2() {
		$this->processFileTest('suite/validationFailSequence2');
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
