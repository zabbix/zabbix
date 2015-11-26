<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../../include/classes/parsers/CItemKey.php';
require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../include/locales.inc.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

class class_cItemKey extends PHPUnit_Framework_TestCase {
	public static function provider() {
		return array(
			// valid keys
			array(
				'key',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array()
				)
			),
			array(
				'key[a]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a')
				)
			),
			array(
				'key[a, b, c]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', 'b', 'c')
				)
			),
			array(
				'key[ a, b, c]', // whitespace before 'a' should be ignored
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', 'b', 'c')
				)
			),
			array(
				'key[    a,    b,     c]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', 'b', 'c')
				)
			),
			array(
				'key[a , b  , c   ]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a ', 'b  ', 'c   ')
				)
			),
			array(
				'key[   a ,   b  ,  c   ]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a ', 'b  ', 'c   ')
				)
			),
			array(
				'key[echo.one, !@##$%$%^&*(, ryrt"rtyrty]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('echo.one', '!@##$%$%^&*(', 'ryrt"rtyrty')
				)
			),
			array(
				'key["a","b","c"]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', 'b', 'c')
				)
			),
			array(
				'key[  "a",  "b", "c"]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', 'b', 'c')
				)
			),
			array(
				'key[  "a"  ,  "b"  , "c"  ]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', 'b', 'c')
				)
			),
			array(
				'key["a ", " b ", " c"]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a ', ' b ', ' c')
				)
			),
			array(
				'key[  "  a "  , "  b  "  ,  " c "  ]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('  a ', '  b  ', ' c ')
				)
			),
			array(
				'key[123"456, 456"789]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('123"456', '456"789')
				)
			),
			array(
				'key[123\"456, 456"789]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('123\"456', '456"789')
				)
			),
			array(
				'key["I am \"testing\""]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('I am "testing"')
				)
			),
			array(
				'key["I am \"testing\"  "]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('I am "testing"  ')
				)
			),
			array(
				'key["123", "456", 789]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('123', '456', '789')
				)
			),
			array(
				'key["12\"3", "456", 789]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('12"3', '456', '789')
				)
			),
			array(
				'key[abc[]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('abc[')
				)
			),
			array(
				'key["a[][][]]],\"!@$#$^%*&*)"]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a[][][]]],"!@$#$^%*&*)')
				)
			),
			array(
				'key[["a"],b]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('"a"', 'b')
				)
			),
			array(
				'complex.key[a, b, c]',
				array(
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => array('a', 'b', 'c')
				)
			),
			array(
				'complex.key[[a, b], c]',
				array(
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => array('a, b', 'c')
				)
			),
			array(
				'complex.key[abc"efg"h]',
				array(
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => array('abc"efg"h')
				)
			),
			array(
				'complex.key[a][b]',
				array(
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => array('a', 'b')
				)
			),
			array(
				'complex.key["a"]["b"]',
			    array(
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => array('a', 'b')
				)
			),
			array(
				'complex.key["a"][b]',
				array(
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => array('a', 'b')
				)
			),
			array(
				'complex.key[a, b][c, d]',
				array(
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => array('a', 'b', 'c', 'd')
				)
			),
			array(
				'complex.key["a", "b"]["c", "d"]',
			    array(
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => array('a', 'b', 'c', 'd')
				)
			),
			array(
				'more.complex.key[1, 2, [A, B, [a, b], C], 3]',
				array(
					'valid' => true,
					'key_id' => 'more.complex.key',
					'parameters' => array('1', '2', 'A, B, [a, b], C', '3')
				)
			),
			array(
				'more.complex.key["1", "2", ["A", "B", ["a", "b"], "C"], "3"]',
				array(
					'valid' => true,
					'key_id' => 'more.complex.key',
					'parameters' => array('1', '2', '"A", "B", ["a", "b"], "C"', '3')
				)
			),
			array(
				'more.complex.key[["1"]]',
				array(
					'valid' => true,
					'key_id' => 'more.complex.key',
					'parameters' => array('"1"')
				)
			),
			array(
				'key[,,]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('', '', '')
				)
			),
			array(
				'key[a"]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a"')
				)
			),
			array(
				'key[a\"]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a\"')
				)
			),
			array(
				'key["\""]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('"')
				)
			),
			array(
				'key["\\""]', // key["\""]
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('"')
				)
			),
			array(
				'key["\\\""]', // key["\\""]
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('\\"')
				)
			),
			array(
				'key["\\\\""]', // key["\\""]
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('\"')
				)
			),
			array(
				'key["\ "]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('\ ')
				)
			),
			array(
				'key["\\ "]', // key["\ "]
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('\\ ')
				)
			),
			array(
				'key["\\\\ "]',  // key["\\ "]
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('\\\\ ')
				)
			),
			array('key["\"]', false),
			array('key["\\"]', false),
			array('key["\\\"]', false),
			array('key["\\\\"]', false),
			array(
				'key[a,]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', '')
				)
			),
			array(
				'key["a",]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', '')
				)
			),
			array(
				'system.run["echo \'a\"b\' | cut -d\'\"\' -f1"]',
				array(
					'valid' => true,
					'key_id' => 'system.run',
					'parameters' => array('echo \'a"b\' | cut -d\'"\' -f1')
				)
			),
			array(
				'012345',
				array(
					'valid' => true,
					'key_id' => '012345',
					'parameters' => array()
				)
			),
			array(
				'key[ГУГЛ]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('ГУГЛ')
				)
			),
			array(
				'key["ГУГЛ"]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('ГУГЛ')
				)
			),
			array(
				'key[["a", "b", "c"], "d"]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('"a", "b", "c"', 'd')
				)
			),
			array(
				'key["a", ["b", "c"], "d"]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', '"b", "c"', 'd')
				)
			),
			array(
				'key[[a, b, c], d]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a, b, c', 'd')
				)
			),
			array(
				'key[a, [b, c], d]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', 'b, c', 'd')
				)
			),
			array(
				'key["a", "b", "c"]["d"]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', 'b', 'c', 'd')
				)
			),
			array(
				'key[a, b, c][d]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a', 'b', 'c', 'd')
				)
			),
			array(
				'key["12\"3", "456", 789]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('12"3', '456', '789')
				)
			),
			array(
				'key',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array()
				)
			),
			array(
				'key[]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('')
				)
			),
			array(
				'key[""]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('')
				)
			),
			array(
				'key["", "", ""]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('', '', '')
				)
			),
			array(
				'key[, abc]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('', 'abc')
				)
			),
			array(
				'key["", abc]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('', 'abc')
				)
			),
			array(
				'key[][abc]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('', 'abc')
				)
			),
			array(
				'key[ab"\"c]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('ab"\"c')
				)
			),
			array(
				'key[[a, b, c]][a]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('a, b, c', 'a')
				)
			),
			array(
				'key[["a", "b", "c"]]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('"a", "b", "c"')
				)
			),
			array(
				'key[["\"a\"", "\"b\"", "\"c\""]]',
				array(
					'valid' => true,
					'key_id' => 'key',
					'parameters' => array('"\"a\"", "\"b\"", "\"c\""')
				)
			),

			// invalid keys
			array('key[["a",]', false),
			array('key[a]654', false),
			array('key["a"]654', false),
			array('key[a][[b]', false),
			array('key["a"][["b"]', false),
			array('key["a"] ["b"]', false),
			array('key(a)', false),
			array('key[a]]', false),
			array('key["a"]]', false),
			array('key["a]', false),
			array('abc:def', false),
			// 256 char long key (decided that this key is valid)
			//array('0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000', false),
			// UTF8 chars
			array('ГУГЛ', false),
			array('', false),
			array(',telnet', false),
			array('telnet,', false),
			array('telnet,1023', false),
			array('telnet,1023[]', false),
			array('[]', false)
		);
	}

	/**
	* @dataProvider provider
	*/
	public function test_parseItemKey($key, $expectedResult) {

		$itemKey = new CItemKey($key);
		$result = array(
			'valid' => $itemKey->isValid(),
			'key_id' => $itemKey->getKeyId(),
			'parameters' => $itemKey->getParameters()
		);
		if ($expectedResult === false) {
			$this->assertFalse($result['valid'], "I was expecting key $key to be invalid, but got: \n".print_r($result, true));
		}
		else {
			unset($result['error']); // no error descriptions in data providers
			$this->assertEquals($result, $expectedResult, "I was expecting: \n".print_r($expectedResult, true)."but got: \n".print_r($result, true).' for key '.$key);
		}
	}
}
