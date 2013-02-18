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
				array('abc', false, 'name'),
				'&name=abc'
			),
			array(
				array('abc', true, 'name'),
				''
			),
			array(
				array('abc', false),
				'&abc=abc'
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
				array(array('a' => 1, 'b' => 2, 'c' => 3), false, 'a'),
				'&a=1'
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
				array('a', true, 'a'),
				'&a=1'
			),
			array(
				array('a', true, 'b'),
				'&a=1'
			)
		);
	}

	/**
	 * @dataProvider providerNotRequest
	 */
	public function test($params, $expectedResult) {
		$result = call_user_func_array('url_param', $params);

		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @dataProvider providerRequest
	 */
	public function test2($params, $expectedResult) {
		$_REQUEST['a'] = 1;
		$_REQUEST['b'] = 2;
		$_REQUEST['c'] = 3;

		$result = call_user_func_array('url_param', $params);

		$this->assertSame($expectedResult, $result);
	}
}
