<?php

use Zabbix\Test\FileApiTestCase;

class JsonRpcTest extends FileApiTestCase {

	public function fileProvider() {
		$files = array(
			'jsonrpc/invalid/incorrectVersion',
			'jsonrpc/invalid/incorrectMethod',
			'jsonrpc/invalid/incorrectParams',
			'jsonrpc/invalid/incorrectAuth',
			'jsonrpc/invalid/incorrectCall',
			'jsonrpc/valid/validCall',
			'jsonrpc/valid/caseInsensitiveCall',
		);

		$data = array();
		foreach ($files as $file) {
			$data[$file] = array($file);
		}

		return $data;
	}

	/**
	 * @dataProvider fileProvider
	 */
	public function testFile($file) {
		$this->runTestFile($file);
	}

}
