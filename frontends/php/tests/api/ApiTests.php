<?php
//require_once 'Framework/ClassTest.php';
require_once 'otherTest2.php';
// ...
 
//class Package_GeneralTests
class ApiTests
{
	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('api');
 
		$suite->addTestSuite('otherTest2');
		// ...
 
		return $suite;
	}
}
?>

