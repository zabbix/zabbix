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
		return [
			// valid keys
			[
				'key',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => []
				]
			],
			[
				'key[a]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a']
				]
			],
			[
				'key[a, b, c]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', 'b', 'c']
				]
			],
			[
				'key[ a, b, c]', // whitespace before 'a' should be ignored
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', 'b', 'c']
				]
			],
			[
				'key[    a,    b,     c]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', 'b', 'c']
				]
			],
			[
				'key[a , b  , c   ]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a ', 'b  ', 'c   ']
				]
			],
			[
				'key[   a ,   b  ,  c   ]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a ', 'b  ', 'c   ']
				]
			],
			[
				'key[echo.one, !@##$%$%^&*(, ryrt"rtyrty]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['echo.one', '!@##$%$%^&*(', 'ryrt"rtyrty']
				]
			],
			[
				'key["a","b","c"]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', 'b', 'c']
				]
			],
			[
				'key[  "a",  "b", "c"]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', 'b', 'c']
				]
			],
			[
				'key[  "a"  ,  "b"  , "c"  ]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', 'b', 'c']
				]
			],
			[
				'key["a ", " b ", " c"]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a ', ' b ', ' c']
				]
			],
			[
				'key[  "  a "  , "  b  "  ,  " c "  ]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['  a ', '  b  ', ' c ']
				]
			],
			[
				'key[123"456, 456"789]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['123"456', '456"789']
				]
			],
			[
				'key[123\"456, 456"789]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['123\"456', '456"789']
				]
			],
			[
				'key["I am \"testing\""]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['I am "testing"']
				]
			],
			[
				'key["I am \"testing\"  "]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['I am "testing"  ']
				]
			],
			[
				'key["123", "456", 789]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['123', '456', '789']
				]
			],
			[
				'key["12\"3", "456", 789]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['12"3', '456', '789']
				]
			],
			[
				'key[abc[]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['abc[']
				]
			],
			[
				'key["a[][][]]],\"!@$#$^%*&*)"]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a[][][]]],"!@$#$^%*&*)']
				]
			],
			[
				'key[["a"],b]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['"a"', 'b']
				]
			],
			[
				'complex.key[a, b, c]',
				[
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => ['a', 'b', 'c']
				]
			],
			[
				'complex.key[[a, b], c]',
				[
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => ['a, b', 'c']
				]
			],
			[
				'complex.key[abc"efg"h]',
				[
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => ['abc"efg"h']
				]
			],
			[
				'complex.key[a][b]',
				[
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => ['a', 'b']
				]
			],
			[
				'complex.key["a"]["b"]',
			[
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => ['a', 'b']
				]
			],
			[
				'complex.key["a"][b]',
				[
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => ['a', 'b']
				]
			],
			[
				'complex.key[a, b][c, d]',
				[
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => ['a', 'b', 'c', 'd']
				]
			],
			[
				'complex.key["a", "b"]["c", "d"]',
				[
					'valid' => true,
					'key_id' => 'complex.key',
					'parameters' => ['a', 'b', 'c', 'd']
				]
			],
			[
				'more.complex.key[1, 2, [A, B, [a, b], C], 3]',
				[
					'valid' => true,
					'key_id' => 'more.complex.key',
					'parameters' => ['1', '2', 'A, B, [a, b], C', '3']
				]
			],
			[
				'more.complex.key["1", "2", ["A", "B", ["a", "b"], "C"], "3"]',
				[
					'valid' => true,
					'key_id' => 'more.complex.key',
					'parameters' => ['1', '2', '"A", "B", ["a", "b"], "C"', '3']
				]
			],
			[
				'more.complex.key[["1"]]',
				[
					'valid' => true,
					'key_id' => 'more.complex.key',
					'parameters' => ['"1"']
				]
			],
			[
				'key[,,]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['', '', '']
				]
			],
			[
				'key[a"]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a"']
				]
			],
			[
				'key[a\"]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a\"']
				]
			],
			[
				'key["\""]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['"']
				]
			],
			[
				'key["\\""]', // key["\""]
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['"']
				]
			],
			[
				'key["\\\""]', // key["\\""]
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['\\"']
				]
			],
			[
				'key["\\\\""]', // key["\\""]
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['\"']
				]
			],
			[
				'key["\ "]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['\ ']
				]
			],
			[
				'key["\\ "]', // key["\ "]
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['\\ ']
				]
			],
			[
				'key["\\\\ "]',  // key["\\ "]
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['\\\\ ']
				]
			],
			['key["\"]', false],
			['key["\\"]', false],
			['key["\\\"]', false],
			['key["\\\\"]', false],
			[
				'key[a,]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', '']
				]
			],
			[
				'key["a",]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', '']
				]
			],
			[
				'system.run["echo \'a\"b\' | cut -d\'\"\' -f1"]',
				[
					'valid' => true,
					'key_id' => 'system.run',
					'parameters' => ['echo \'a"b\' | cut -d\'"\' -f1']
				]
			],
			[
				'012345',
				[
					'valid' => true,
					'key_id' => '012345',
					'parameters' => []
				]
			],
			[
				'key[ГУГЛ]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['ГУГЛ']
				]
			],
			[
				'key["ГУГЛ"]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['ГУГЛ']
				]
			],
			[
				'key[["a", "b", "c"], "d"]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['"a", "b", "c"', 'd']
				]
			],
			[
				'key["a", ["b", "c"], "d"]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', '"b", "c"', 'd']
				]
			],
			[
				'key[[a, b, c], d]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a, b, c', 'd']
				]
			],
			[
				'key[a, [b, c], d]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', 'b, c', 'd']
				]
			],
			[
				'key["a", "b", "c"]["d"]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', 'b', 'c', 'd']
				]
			],
			[
				'key[a, b, c][d]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a', 'b', 'c', 'd']
				]
			],
			[
				'key["12\"3", "456", 789]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['12"3', '456', '789']
				]
			],
			[
				'key',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => []
				]
			],
			[
				'key[]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['']
				]
			],
			[
				'key[""]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['']
				]
			],
			[
				'key["", "", ""]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['', '', '']
				]
			],
			[
				'key[, abc]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['', 'abc']
				]
			],
			[
				'key["", abc]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['', 'abc']
				]
			],
			[
				'key[][abc]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['', 'abc']
				]
			],
			[
				'key[ab"\"c]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['ab"\"c']
				]
			],
			[
				'key[[a, b, c]][a]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['a, b, c', 'a']
				]
			],
			[
				'key[["a", "b", "c"]]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['"a", "b", "c"']
				]
			],
			[
				'key[["\"a\"", "\"b\"", "\"c\""]]',
				[
					'valid' => true,
					'key_id' => 'key',
					'parameters' => ['"\"a\"", "\"b\"", "\"c\""']
				]
			],

			// invalid keys
			['key[["a",]', false],
			['key[a]654', false],
			['key["a"]654', false],
			['key[a][[b]', false],
			['key["a"][["b"]', false],
			['key["a"] ["b"]', false],
			['key(a)', false],
			['key[a]]', false],
			['key["a"]]', false],
			['key["a]', false],
			['abc:def', false],
			// 256 char long key (decided that this key is valid)
			//array('0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000', false),
			// UTF8 chars
			['ГУГЛ', false],
			['', false],
			[',telnet', false],
			['telnet,', false],
			['telnet,1023', false],
			['telnet,1023[]', false],
			['[]', false]
		];
	}

	/**
	* @dataProvider provider
	*/
	public function test_parseItemKey($key, $expectedResult) {

		$itemKey = new CItemKey($key);
		$result = [
			'valid' => $itemKey->isValid(),
			'key_id' => $itemKey->getKeyId(),
			'parameters' => $itemKey->getParameters()
		];
		if ($expectedResult === false) {
			$this->assertFalse($result['valid'], "I was expecting key $key to be invalid, but got: \n".print_r($result, true));
		}
		else {
			unset($result['error']); // no error descriptions in data providers
			$this->assertEquals($result, $expectedResult, "I was expecting: \n".print_r($expectedResult, true)."but got: \n".print_r($result, true).' for key '.$key);
		}
	}
}
