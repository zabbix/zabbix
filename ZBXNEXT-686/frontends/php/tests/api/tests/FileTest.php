<?php

class FileTest extends CFileApiTestCase {

	public function fileProvider() {
		return $this->provideTestFiles();
	}

	/**
	 * @dataProvider fileProvider
	 */
	public function test($file) {
		$this->runTestFile($file);
	}

}
