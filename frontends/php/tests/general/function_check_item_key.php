<?php
require_once 'PHPUnit/Framework.php';

require_once('../include/func.inc.php');
require_once('../include/items.inc.php');
require_once('../include/defines.inc.php');
require_once('../include/locales.inc.php');

class function_check_item_key extends PHPUnit_Framework_TestCase
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
			array('key(a)',false),
			// 256 char long key
			array('0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000',false),
			// Empty key
			array('',false),
			// Only digits
			array('012345',true),
			// UTF8 chars
			array('ГУГЛ',false),
			// UTF8 chars in params
			array('key[ГУГЛ]',true),
			array('key["ГУГЛ"]',true)
		);
	}

	/**
	* @dataProvider provider
	*/
	public function test_check_item_key($a, $b)
	{
		$result=check_item_key($a);
		if($result[0]) {
			$this->assertEquals($result[0],$b);
		} else {
			$this->assertEquals($result[0],$b,$result[1]);
		}
	}

}
?>
