<?php
require_once 'itemKeyValidation.php';
require_once 'otherTest.php';
 
class GeneralTests
{
	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('general');
 
		$suite->addTestSuite('itemKeyValidation');
		$suite->addTestSuite('otherTest');
		// ...
 
		return $suite;
	}
}
?>

