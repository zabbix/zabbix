<?php
require_once 'PHPUnit/Framework.php';

require('../include/func.inc.php');
require('../include/items.inc.php');
require('../include/defines.inc.php');
require('../include/locales.inc.php');
 
class All extends PHPUnit_Framework_TestCase
{
	public static function provider()
	{
		return array(
			array('key[a]',true),
			array('key["a"]',true),
			array('key[a, b, c]',true),
			array('key["a", "b", "c"]',true),
			array('key[a, b, "c"]',true),
			array('key["a", "b", c]',true),
			array('key["a[][][]]],\"!@$#$^%*&*)"]',true),
			array('key[["a"],b]',true),
			array('complex.key[a, b, c]',true),
			array('complex.key[[a, b], c]',true),
			array('complex.key[abc"efg"h]',true),
			array('complex.key[a][b]',true),
			array('complex.key["a"]["b"]',true),
			array('complex.key["a"][b]',true),
			array('complex.key[a, b][c, d]',true),
			array('complex.key["a", "b"]["c", "d"]',true),
			array('more.complex.key[1, 2, [A, B, [a, b], C], 3]',true),
			array('more.complex.key["1", "2", ["A", "B", ["a", "b"], "C"], "3"]',true),
			array('more.complex.key[["1"]]',true),
			array('key[,,]',true),
			array('key[a]]',false),
			array('key["a"]]',false),
			array('key["a]',false),
			array('key[a,]',true),
			array('key["a",]',true),
			array('key[["a",]',false),
			array('key[a]654',false),
			array('key["a"]654',false),
			array('key[a][[b]',false),
			array('key["a"][["b"]',false),
			// Incorrect test case for testing
			array('key(a)',true)
		);
	}

	/**
	* @dataProvider provider
	*/
	public function testItemKeyValidation($a, $b)
	{
		$result=check_item_key($a);
		$this->assertEquals($result[0],$b,$result[1]);
	}

}
?>

