<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
require_once dirname(__FILE__).'/../include/CTest.php';
require_once dirname(__FILE__).'/../../include/html.inc.php';

class urlParamTest extends CTest {

	public static function provider() {
		return [
			/*
			 * Request is empty
			 */
			[
				'inputData' => ['abc'],
				'expectedResult' => '',
				'expectError' => false,
				'requestData' => []
			],
			[
				'inputData' => ['abc', true],
				'expectedResult' => '',
				'expectError' => false,
				'requestData' => []
			],
			[
				'inputData' => ['abc', true, 'name'],
				'expectedResult' => '',
				'expectError' => false,
				'requestData' => []
			],
			[
				'inputData' => [['a' => 1, 'b' => 2, 'c' => 3]],
				'expectedResult' => '',
				'expectError' => true,
				'requestData' => []
			],
			[
				'inputData' => ['abc', false, 'name'],
				'expectedResult' => '&name=abc',
				'expectError' => false,
				'requestData' => []
			],
			[
				'inputData' => [['a' => 1, 'b' => 2, 'c' => 3], false, 'abc'],
				'expectedResult' => '&abc[a]=1&abc[b]=2&abc[c]=3',
				'expectError' => false,
				'requestData' => []
			],
			/*
			 * Request exist
			 */
			[
				'inputData' => ['a'],
				'expectedResult' => '&a=1',
				'expectError' => false,
				'requestData' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => ['d0', 'd1', 'd2']]
			],
			[
				'inputData' => ['a', true],
				'expectedResult' => '&a=1',
				'expectError' => false,
				'requestData' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => ['d0', 'd1', 'd2']]
			],
			[
				'inputData' => ['a', true, 'b'],
				'expectedResult' => '&b=1',
				'expectError' => false,
				'requestData' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => ['d0', 'd1', 'd2']]
			],
			[
				'inputData' => ['b', true, 'b'],
				'expectedResult' => '&b=2',
				'expectError' => false,
				'requestData' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => ['d0', 'd1', 'd2']]
			],
			[
				'inputData' => ['abc', true],
				'expectedResult' => '',
				'expectError' => false,
				'requestData' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => ['d0', 'd1', 'd2']]
			],
			[
				'inputData' => ['abc', true, 'abc'],
				'expectedResult' => '',
				'expectError' => false,
				'requestData' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => ['d0', 'd1', 'd2']]
			],
			[
				'inputData' => ['d', true, 'aaa'],
				'expectedResult' => '&aaa[0]=d0&aaa[1]=d1&aaa[2]=d2',
				'expectError' => false,
				'requestData' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => ['d0', 'd1', 'd2']]
			],
			[
				'inputData' => ['d', true, 'b'],
				'expectedResult' => '&b[0]=d0&b[1]=d1&b[2]=d2',
				'expectError' => false,
				'requestData' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => ['d0', 'd1', 'd2']]
			],
			[
				'inputData' => ['abc', false, 'name'],
				'expectedResult' => '&name=abc',
				'expectError' => false,
				'requestData' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => ['d0', 'd1', 'd2']]
			],
			[
				'inputData' => [['a' => 1, 'b' => 2, 'c' => 3], false, 'abc'],
				'expectedResult' => '&abc[a]=1&abc[b]=2&abc[c]=3',
				'expectError' => false,
				'requestData' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => ['d0', 'd1', 'd2']]
			]
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test($inputData, $expectedResult, $expectError, $requestData) {
		$_REQUEST = $requestData;

		if ($expectError) {
			try {
				$result = null;

				if (isset($inputData[2])) {
					$result = call_user_func_array('url_param', [$inputData[0], $inputData[1], $inputData[2]]);
				}
				elseif (isset($inputData[1])) {
					$result = call_user_func_array('url_param', [$inputData[0], $inputData[1]]);
				}
				elseif (isset($inputData[0])) {
					$result = call_user_func_array('url_param', $inputData[0]);
				}
			}
			catch (Exception $e) {
				if (!isset($result)) {
					$this->assertTrue(true);
				}
			}

			if (isset($result)) {
				$this->assertSame($expectedResult, $result);
			}
		}
		else {
			$this->assertSame($expectedResult, call_user_func_array('url_param', $inputData));
		}
	}
}
