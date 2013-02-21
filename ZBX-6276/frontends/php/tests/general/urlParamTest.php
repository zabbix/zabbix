<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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
require_once dirname(__FILE__).'/../include/class.czabbixtest.php';
require_once dirname(__FILE__).'/../../include/html.inc.php';

class urlParamTest extends CZabbixTest {

	public static function providerNotRequest() {
		return array(
			array(
				array('abc', true, 'name'),
				''
			),
			array(
				array('abc', true),
				''
			),
			array(
				array('abc'),
				''
			),
			array(
				array(array('a' => 1, 'b' => 2, 'c' => 3), true),
				''
			),
			array(
				array('abc', false, 'name'),
				'&name=abc'
			),
			array(
				array(array('a' => 1, 'b' => 2, 'c' => 3), false, 'a'),
				'&a[a]=1&a[b]=2&a[c]=3'
			),
			array(
				array(array('a' => 1, 'b' => 2, 'c' => 3), false, 'abc'),
				'&abc[a]=1&abc[b]=2&abc[c]=3'
			)
		);
	}

	public static function providerRequest() {
		return array(
			array(
				array('a'),
				'&a=1'
			),
			array(
				array('a', true, 'b'),
				'&b=1'
			),
			array(
				array('b', true, 'b'),
				'&b=2'
			),
			array(
				array('abc', true),
				''
			),
			array(
				array('abc', true, 'abc'),
				''
			),
			array(
				array('d', true, 'aaa'),
				'&aaa[0]=d0&aaa[1]=d1&aaa[2]=d2'
			),
			array(
				array('d', true, 'b'),
				'&b[0]=d0&b[1]=d1&b[2]=d2'
			),
		);
	}

	/**
	 * @dataProvider providerNotRequest
	 */
	public function test($params, $expectedResult) {
		$result = call_user_func_array('url_param', $params);

		$this->assertSame($result, $expectedResult);
	}

	/**
	 * @dataProvider providerRequest
	 */
	public function test2($params, $expectedResult) {
		$_REQUEST['a'] = 1;
		$_REQUEST['b'] = 2;
		$_REQUEST['c'] = 3;
		$_REQUEST['d'][0] = 'd0';
		$_REQUEST['d'][1] = 'd1';
		$_REQUEST['d'][2] = 'd2';

		$result = call_user_func_array('url_param', $params);

		$this->assertSame($result, $expectedResult);
	}
}
