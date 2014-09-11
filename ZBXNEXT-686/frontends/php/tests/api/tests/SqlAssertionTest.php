<?php

namespace Zabbix\Tests\Suite;

use Zabbix\Test\FileApiTestCase;

class SqlAssertionTest extends FileApiTestCase
{
	/**
	 * @group suite
	 * @fixtures base_users
	 */
	public function testSqlAssertions() {
		$this->processFileTest('dev/sqlAssertion');
	}
}
