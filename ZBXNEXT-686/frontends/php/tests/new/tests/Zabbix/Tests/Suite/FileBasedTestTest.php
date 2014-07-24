<?php

namespace Zabbix\Tests\Suite;

use Zabbix\Test\APIGateway\APIGatewayInterface;
use Zabbix\Test\APIGateway\MockAPIGateway;
use Zabbix\Test\APITestCase;

class FileBasedTestTest extends APITestCase {
	public function testStepVariableSubstitution() {
		$this->getGateway();
		$this->processFileTest('suite\variableSubstitution');
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
	}

}
