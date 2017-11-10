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

/**
 *
 */
class testFormEventCorelation extends CWebTest {

	public static function create() {
		return [
			[
				[
					'name' => 'Test create with all fields',
					'select_tag' => 'New event tag',
					'tag' => 'tag name',
					'description' => 'Event corelation with description'
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormEventCorelation_Create($data) {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestDropdownSelectWait('new_condition_type', $data['select_tag']);
		$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[1]');
		$this->zbxTestInputTypeOverwrite('description', $data['description']);

		$this->zbxTestClick('tab_operationTab');
		$this->zbxTestClickXpathWait('//li[2]/div[2]/div/table/tbody/tr[2]/td/button');

		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation added');
	}

}
