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
require_once dirname(__FILE__).'/../../include/classes/helpers/CArrayHelper.php';

class CArrayHelperDiffTest extends CZabbixTest {

	public static function provider() {
		return array(
			array(
				array(array('a' => 'abc', 'b' => '123'), array('a' => '123', 'b' => 'abc')),
				array('a' => 'abc', 'b' => '123')
			),
			array(
				array(array('a' => 'abc', 'b' => '123'), array('b' => 'abc', 'a' => '123')),
				array('a' => 'abc', 'b' => '123')
			),
			array(
				array(array('a' => 'abc', 'b' => '123'), array('b' => 'abc', 'a' => 'abc')),
				array('b' => '123')
			),
			array(
				array(array('a' => "0"), array('a' => 0)),
				array()
			),
			array(
				array(array('a' => "1"), array('a' => 1)),
				array()
			),
			array(
				array(array('a' => null), array('a' => null)),
				array()
			),
			array(
				array(array('a' => null), array('a' => 0)),
				array('a' => null)
			),
			array(
				array(array('a' => ""), array('a' => null)),
				array('a' => "")
			),
			array(
				array(array('a' => ""), array('a' => 0)),
				array('a' => "")
			),
			array(
				array(array('a' => 0), array('a' => null)),
				array('a' => 0)
			),
			array(
				array(array('a' => 0), array('a' => "")),
				array('a' => 0)
			),
			array(
				array(array('a' => array(1, 2, 3)), array('a' => '123')),
				array('a' => array(1, 2, 3))
			)
		);
	}

	/**
	 * @dataProvider provider
	 */
	public function test($params, $expectedResult) {
		$result = CArrayHelper::diff($params[0], $params[1]);

		$this->assertSame(serialize($expectedResult), serialize($result));
	}
}
