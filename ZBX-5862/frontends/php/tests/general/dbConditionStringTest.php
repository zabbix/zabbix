<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
require_once dirname(__FILE__).'/../../conf/zabbix.conf.php';
require_once dirname(__FILE__).'/../../include/classes/debug/CProfiler.php';
require_once dirname(__FILE__).'/../../include/db.inc.php';

class dbConditionStringTest extends PHPUnit_Framework_TestCase {

	public static function provider() {
		return array(
			array(
				array('field', array()),
				' 1=0'
			),
			array(
				array('field', array(1)),
				' field=1'
			),
			array(
				array('field', array(1), true),
				' field!=1'
			)
		);
	}

	/**
	 * @dataProvider provider
	 */
	public function test($params, $expectedResult) {
		DBConnect($error);

		$result = call_user_func_array('dbConditionString', $params);

		$this->assertSame($expectedResult, $result);
	}
}
