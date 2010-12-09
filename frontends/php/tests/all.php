<?php
require_once 'general/GeneralTests.php';
 
class All
{
	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('Project');
	
		$suite->addTest(GeneralTests::suite());
		// ...
 
		return $suite;
	}
}
?>

