<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

require_once(dirname(__FILE__).'/../../include/func.inc.php');
require_once(dirname(__FILE__).'/../../include/items.inc.php');
require_once(dirname(__FILE__).'/../../include/defines.inc.php');
require_once(dirname(__FILE__).'/../../include/locales.inc.php');

class function_parseItemKey extends PHPUnit_Framework_TestCase
{
	public static function provider()
	{
		return array(
			array(
				'key',
				array(
					'key_id'=>'key',
					'parameters'=>array()
				)
			),
			array(
				'key[]',
				array(
					'key_id'=>'key',
					'parameters'=>array()
				)
			),
			array(
				'key[a]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a')
				)
			),
			array(
				'key[a, b, c]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a', 'b', 'c')
				)
			),
			array(
				'key[ a, b, c]', // whitespace before 'a' should be ignored
				array(
					'key_id'=>'key',
					'parameters'=>array('a', 'b', 'c')
				)
			),
			array(
				'key[    a,    b,     c]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a', 'b', 'c')
				)
			),
			array(
				'key[a , b  , c   ]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a ', 'b  ', 'c   ')
				)
			),
			array(
				'key[   a ,   b  ,  c   ]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a ', 'b  ', 'c   ')
				)
			),
			array(
				'key[echo.one, !@##$%$%^&*(, ryrt"rtyrty]',
				array(
					'key_id'=>'key',
					'parameters'=>array('echo.one', '!@##$%$%^&*(', 'ryrt"rtyrty')
				)
			),
			array(
				'key["a","b","c"]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a', 'b', 'c')
				)
			),
			array(
				'key[  "a",  "b", "c"]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a', 'b', 'c')
				)
			),
			array(
				'key[  "a"  ,  "b"  , "c"  ]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a', 'b', 'c')
				)
			),
			array(
				'key["a ", " b ", " c"]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a ', ' b ', ' c')
				)
			),
			array(
				'key[  "  a "  , "  b  "  ,  " c "  ]',
				array(
					'key_id'=>'key',
					'parameters'=>array('  a ', '  b  ', ' c ')
				)
			),
			array(
				'key[123"456, 456"789]',
				array(
					'key_id'=>'key',
					'parameters'=>array('123"456', '456"789')
				)
			),
			array(
				'key[123\"456, 456"789]',
				array(
					'key_id'=>'key',
					'parameters'=>array('123\"456', '456"789')
				)
			),
			array(
				'key["I am \"testing\""]',
				array(
					'key_id'=>'key',
					'parameters'=>array('I am "testing"')
				)
			),
			array(
				'key["I am \"testing\"  "]',
				array(
					'key_id'=>'key',
					'parameters'=>array('I am "testing"  ')
				)
			),
			array(
				'key["123", "456", 789]',
				array(
					'key_id'=>'key',
					'parameters'=>array('123', '456', '789')
				)
			),
			array(
				'key["12\"3", "456", 789]',
				array(
					'key_id'=>'key',
					'parameters'=>array('12"3', '456', '789')
				)
			),
			array(
				'key["12\"3", "456", 789]',
				array(
					'key_id'=>'key',
					'parameters'=>array('12"3', '456', '789')
				)
			),
			array(
				'key',
				array(
					'key_id'=>'key',
					'parameters'=>array()
				)
			),
			array(
				'key[]',
				array(
					'key_id'=>'key',
					'parameters'=>array('')
				)
			),
			array(
				'key[""]',
				array(
					'key_id'=>'key',
					'parameters'=>array('')
				)
			),
			array(
				'key["", "", ""]',
				array(
					'key_id'=>'key',
					'parameters'=>array('', '', '')
				)
			),
			array(
				'key[, abc]',
				array(
					'key_id'=>'key',
					'parameters'=>array('', 'abc')
				)
			),
			array(
				'key["", abc]',
				array(
					'key_id'=>'key',
					'parameters'=>array('', 'abc')
				)
			),
			array(
				'key[["a", "b", "c"], "d"]',
				array(
					'key_id'=>'key',
					'parameters'=>array('"a", "b", "c"', 'd')
				)
			),
			array(
				'key["a", ["b", "c"], "d"]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a', '"b", "c"', 'd')
				)
			),
			array(
				'key[[a, b, c], d]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a, b, c', 'd')
				)
			),
			array(
				'key[a, [b, c], d]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a', 'b, c', 'd')
				)
			),
			array(
				'key["a", "b", "c"]["d"]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a', 'b', 'c', 'd')
				)
			),
			array(
				'key[a, b, c][d]',
				array(
					'key_id'=>'key',
					'parameters'=>array('a', 'b', 'c', 'd')
				)
			),
		);
	}

	/**
	* @dataProvider provider
	*/
	public function test_parseItemKey($key, $expectedResult){

		$result = parseItemKey($key);
		$this->assertEquals($result, $expectedResult, "I was expecting: \n".print_r($expectedResult, true).", but got: \n".print_r($result, true).' for key '.$key);

	}

}
?>
