<?php

namespace Zabbix\Tests\Regression\API\Drule;

use Zabbix\Test\APITestCase;

class ZBX8301DruleCreateTest extends APITestCase
{
	/**
	 * @group regression
	 * @fixtures base_users
	 */
	public function testRegressionZBX8301() {
		$this->processFileTest('regression/api/drule/ZBX8301DruleCreate');
	}
}
