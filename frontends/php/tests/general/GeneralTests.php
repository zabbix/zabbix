<?php
require_once 'function_check_item_key.php';
//require_once 'otherTest.php';

class GeneralTests
{
	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('general');

		$suite->addTestSuite('function_check_item_key');
//		$suite->addTestSuite('otherTest');
//		...

		return $suite;
	}
}
?>
