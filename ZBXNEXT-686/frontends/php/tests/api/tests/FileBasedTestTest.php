<?php

namespace Zabbix\Tests\Suite;

use Zabbix\Test\APIGateway\MockAPIGateway;
use Zabbix\Test\FileApiTestCase;

class FileBasedTestTest extends FileApiTestCase {
	/**
	 * General test; no assertions, just should not throw errors.
	 * @group suite
	 */
	public function testStepVariableSubstitution() {
		$this->processFileTest('dev/variableSubstitution');
	}

	/**
	 * @expectedException 		  Exception
	 * @expectedExceptionMessage  Rule "int" failed for "a" on path "_assert->_keys->_each[0]"
	 * @group suite
	 */
	public function testValidationIntegerKeys() {
		$this->processFileTest('dev/validationIntegerKeys');
	}

	/**
	 * @expectedException		  Exception
	 * @expectedExceptionMessage  Rule "notEmpty" failed for "" on path "_assert->_keys->_each[0]->id"
	 * @group suite
	 */
	public function testValidationRequired() {
		$this->processFileTest('dev/validationRequired');
	}

	/**
	 * @expectedException		  Exception
	 * @expectedExceptionMessage  Rule "length" failed for "a very long hostname that does not match validation rule" on path "_assert->_keys->_each[0]->id->hostname"
	 * @group suite
	 */
	public function testValidationLongHostnames() {
		$this->processFileTest('dev/validationLongHostnames');
	}

	/**
	 * @expectedException		  Exception
	 * @expectedExceptionMessage  Rule "string" failed for "-7" on path "_assert->_keys->_each[1]->id->hostname->templates->_each[1]"
	 * @group suite
	 */
	public function testValidationType() {
		$this->processFileTest('dev/validationType');
	}

	/**
	 * @group suite
	 */
	public function testValidationSuccessSequence() {
		$this->processFileTest('dev/validationSuccessSequence');
	}

	/**
	 * @expectedException		  Exception
	 * @expectedExceptionMessage  Rule "sequence" failed for "Array" on path "_assert->_keys"
	 * @group suite
	 */
	public function testValidationFailSequence1() {
		$this->processFileTest('dev/validationFailSequence1');
	}

	/**
	 * @expectedException		  Exception
	 * @expectedExceptionMessage  Rule "sequence" failed for "Array" on path "_assert->_keys"
	 * @group suite
	 */
	public function testValidationFailSequence2() {
		$this->processFileTest('dev/validationFailSequence2');
	}

	/**
	 * @group suite
	 */
	public function testValidationSuccess() {
		$this->processFileTest('dev/validationSuccess');
	}

	/**
	 * @group suite
	 */
	public function testValidationSuccessShortSyntax() {
		$this->processFileTest('dev/validationSuccessShortSyntax');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getGateway()
	{
		$gateway = new MockAPIGateway();
		$gateway->configure(array(
			'file' => ZABBIX_NEW_TEST_DIR.'/tests/mock/fileBasedTestTestMock.yml'
		), $this->parsedConfig);

		return $gateway;
	}

}
