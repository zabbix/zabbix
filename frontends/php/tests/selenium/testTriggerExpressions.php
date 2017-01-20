<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testTriggerExpressions extends CWebTest {

	public static function provider() {
		return [
			['10M', '20M', 'FALSE'],
			['10T', '2G', 'TRUE'],
			['10T', '2T', 'TRUE']
		];
	}

	/**
	* @dataProvider provider
	*/
	public function testTriggerExpression_SimpleTest($where, $what, $expected) {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$this->zbxTestCheckHeader('Dashboard');
		$this->zbxTestOpen('tr_testexpr.php?expression={Test%20host%3Avm.memory.size[total].last%280%29}%3C'.$where);
		$this->zbxTestCheckTitle('Test');
		$this->zbxTestCheckHeader('Test');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//input[@type='text']"));
		$this->zbxTestInputTypeByXpath("//input[@type='text']", $what);

		$this->zbxTestClick("test_expression");
		$this->zbxTestTextPresent($expected);
	}
}
