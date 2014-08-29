<?php

use Zabbix\Test\FileApiTestCase;

class HostCreateTest extends FileApiTestCase {

	public function fileProvider() {
		return array(
			array('dev/host.create'),
			array('dev/host.create.invalid'),
			array('dev/trigger.create'),
		);
	}

	/**
	 * @dataProvider fileProvider
	 */
	public function testFile($file) {
		$test = $this->parseTestFile($file);
		$this->database->loadFixtures($test['fixtures']);
		$this->runSteps($test['steps']);
	}

}
