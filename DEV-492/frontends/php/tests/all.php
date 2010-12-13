<?php
require_once 'general/GeneralTests.php';
require_once 'api/ApiTests.php';

class All
{
	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('Project');

		$suite->addTest(GeneralTests::suite());
		$suite->addTest(ApiTests::suite());
		// ...

		return $suite;
	}
}
?>
