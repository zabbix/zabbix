<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once 'PHPUnit/Framework.php';

require_once('../include/func.inc.php');
require_once('../include/items.inc.php');
require_once('../include/defines.inc.php');
require_once('../include/locales.inc.php');

class otherTest2 extends PHPUnit_Framework_TestCase
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
	public function testItemKeyValidation($a, $b)
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
