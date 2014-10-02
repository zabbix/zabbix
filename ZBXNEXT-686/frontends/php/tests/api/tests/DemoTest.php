<?php

class DemoTest extends CFileApiTestCase {

	public function fileProvider() {
		$files = array(
			'dev/syntax',
			'dev/host.create',
			'dev/host.create.invalid',
			'dev/trigger.create',
			'dev/hostUnlinkTemplateAndRemoveInterface',
			'dev/templateLinkHttpTest'
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
