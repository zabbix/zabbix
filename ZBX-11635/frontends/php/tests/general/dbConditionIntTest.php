<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
require_once dirname(__FILE__).'/../../include/db.inc.php';

class dbConditionIntTest extends CZabbixTest {

	public static function provider() {
		return array(
			array(
				array('field', array()),
				'1=0'
			),
			array(
				array('field', range(1, 100)),
				"field BETWEEN '1' AND '100'"
			),
			array(
				array('field', array(0)),
				"field='0'"
			),
			array(
				array('field', array(1)),
				"field='1'"
			),
			array(
				array('field', array()),
				'1=0'
			),
			array(
				array('field', array(true)),
				'1=0'
			),
			array(
				array('field', array(0, 1)),
				"field IN ('0','1')"
			),
			array(
				array('field', array(1, 0)),
				"field IN ('0','1')"
			),
			array(
				array('field', array(1), true),
				"field!='1'"
			),
			array(
				array('field', range(1, 20, 5)),
				"field IN ('1','6','11','16')"
			),
			array(
				array('field', range(1, 20, 5), true),
				"field NOT IN ('1','6','11','16')"
			),
			array(
				array('field', range(1, 100, 10)),
				"field IN ('1','11','21','31','41','51','61','71','81','91')"
			),
			array(
				array('field', array_merge(range(1, 10), range(20, 30))),
				"(field BETWEEN '1' AND '10' OR field BETWEEN '20' AND '30')"
			),
			array(
				array('field', array_merge(range(1, 10), range(20, 30)), true),
				"(NOT field BETWEEN '1' AND '10' AND NOT field BETWEEN '20' AND '30')"
			),
			array(
				array('field', array_merge(range(1, 4), range(1, 4), range(20, 30))),
				"(field BETWEEN '20' AND '30' OR field IN ('1','2','3','4'))"
			),
			array(
				array('field', array_merge(range(1, 4), range(1, 4), range(20, 30)), true),
				"(NOT field BETWEEN '20' AND '30' AND field NOT IN ('1','2','3','4'))"
			),
			array(
				array('field', array_merge(range(20, 30), array(10))),
				"(field BETWEEN '20' AND '30' OR field='10')"
			),
			array(
				array('field', array_merge(range(20, 30), array(10)), true),
				"(NOT field BETWEEN '20' AND '30' AND field!='10')"
			),
			array(
				array('field', array('9223372036854775802', '9223372036854775802', '9223372036854775803', '9223372036854775804', '9223372036854775805', '9223372036854775806')),
				"field BETWEEN '9223372036854775802' AND '9223372036854775806'"
			),
			array(
				array('field', array('9223372036854775802', '9223372036854775803', '9223372036854775804', '9223372036854775805', '9223372036854775806', '9223372036854775807')),
				"field BETWEEN '9223372036854775802' AND '9223372036854775807'"
			),
			array(
				array('field', array('9223372036854775807', '9223372036854775806', '9223372036854775805', '9223372036854775804', '9223372036854775803', '9223372036854775802')),
				"field BETWEEN '9223372036854775802' AND '9223372036854775807'"
			),
			array(
				array('field', array('id_001' => 1)),
				"field='1'"
			),
			array(
				array('field', array('id_001' => '1', 'id_002' => '2', 'id_003' => '3', 'id_004' => '4', 'id_005' => '5', 'id_006' => '6')),
				"field BETWEEN '1' AND '6'"
			),
			array(
				array('field', array('1', '7', '90', '91', '100', '400'), false, false),
				"field IN ('1','7','90','91','100','400')"
			)
		);
	}

	/**
	 * @dataProvider provider
	 */
	public function test($params, $expectedResult) {
		$result = call_user_func_array('dbConditionInt', $params);

		$this->assertSame($expectedResult, $result);
	}
}
