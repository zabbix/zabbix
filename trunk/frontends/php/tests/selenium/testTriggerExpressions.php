<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
			['20M', 'FALSE', 'red'],
			['19.9M', 'TRUE', 'green'],
			['20479K', 'TRUE', 'green']
		];
	}

	/**
	* @dataProvider provider
	*/
	public function testTriggerExpression_SimpleTest($value, $expected, $css_class) {
		// Open advanced editor for testing trigger expression results
		$this->zbxTestLogin('triggers.php?form=update&hostid=10084&triggerid=13504');
		$this->zbxTestCheckHeader('Triggers');
		$this->zbxTestClickButtonText('Expression constructor');
		$this->zbxTestClickWait('test_expression');
		$this->zbxTestLaunchOverlayDialog('Test');

		// Type values in expression testing form
		$this->zbxTestInputTypeByXpath('//div[@class="overlay-dialogue-body"]//input[@type="text"]', $value);

		// Verify result of expression status
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Test"]');
		$this->zbxTestAssertElementText('(//div[@class="overlay-dialogue-body"]//td[@class="'.$css_class.'"])[1]', $expected);
		$this->zbxTestAssertElementText('(//div[@class="overlay-dialogue-body"]//td[@class="'.$css_class.'"])[2]', $expected);
		$this->zbxTestCheckFatalErrors();
	}
}
