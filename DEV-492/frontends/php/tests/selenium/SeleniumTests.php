<?php
//require_once 'Framework/ClassTest.php';
require_once 'testGeneric.php';
// ...

//class Package_GeneralTests
class SeleniumTests
{
	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('selenium');

		$suite->addTestSuite('testGeneric');
		// ...

		return $suite;
	}
}
?>
