<?php

namespace Zabbix\Tests\Suite;

use Zabbix\Test\APITestCase;

class SqlAssertionTest extends APITestCase
{
	/**
	 * @group suite
	 * @fixtures base_users
	 */
	public function testSqlAssertions() {
		$this->processFileTest('suite/sqlAssertion');
	}
}
