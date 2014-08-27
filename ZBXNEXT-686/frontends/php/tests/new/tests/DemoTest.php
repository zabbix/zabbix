<?php

use Zabbix\Test\FileApiTestCase;

class HostCreateTest extends FileApiTestCase {

	public function fileProvider() {
		return array(
			array('dev/host.create'),
		);
	}

	/**
	 * @dataProvider fileProvider
	 */
	public function testFile($file) {
		$test = $this->parseTestFile($file);
		$this->loadFixtures($test['fixtures']);
		$this->runSteps($test['steps']);
	}

}
