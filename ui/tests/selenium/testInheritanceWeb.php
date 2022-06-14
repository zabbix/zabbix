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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup httptest
 */
class testInheritanceWeb extends CLegacyWebTest {
	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	public static function update() {
		return CDBHelper::getDataProvider(
			'SELECT httptestid,hostid'.
			' FROM httptest'.
			' WHERE hostid=15000'	//	$this->templateid.
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceWeb_SimpleUpdate($data) {
		$sqlHttpTests = 'SELECT * FROM httptest ORDER BY httptestid';
		$oldHashHttpTests = CDBHelper::getHash($sqlHttpTests);
		$sqlHttpSteps = 'SELECT * FROM httpstep ORDER BY httpstepid';
		$oldHashHttpSteps = CDBHelper::getHash($sqlHttpSteps);
		$sqlHttpTestItems = 'SELECT * FROM httptestitem ORDER BY httptestitemid';
		$oldHashHttpTestItems = CDBHelper::getHash($sqlHttpTestItems);
		$sqlHttpStepItems = 'SELECT * FROM httpstepitem ORDER BY httpstepitemid';
		$oldHashHttpStepItems = CDBHelper::getHash($sqlHttpStepItems);
		$sqlItems = 'SELECT * FROM items ORDER BY itemid';
		$oldHashItems = CDBHelper::getHash($sqlItems);

		$this->zbxTestLogin('httpconf.php?form=update&context=host&hostid='.$data['hostid'].'&httptestid='.
				$data['httptestid']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of web monitoring');
		$this->zbxTestTextPresent('Web scenario updated');

		$this->assertEquals($oldHashHttpTests, CDBHelper::getHash($sqlHttpTests));
		$this->assertEquals($oldHashHttpSteps, CDBHelper::getHash($sqlHttpSteps));
		$this->assertEquals($oldHashHttpTestItems, CDBHelper::getHash($sqlHttpTestItems));
		$this->assertEquals($oldHashHttpStepItems, CDBHelper::getHash($sqlHttpStepItems));
		$this->assertEquals($oldHashItems, CDBHelper::getHash($sqlItems));
	}

	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceWeb5',
					'addStep' => [
						['name' => 'testInheritanceStep1', 'url' => 'http://testInheritanceStep1/']
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'testInheritanceWeb1',
					'addStep' => [
						['name' => 'testInheritanceStep1', 'url' => 'http://testInheritanceStep1/']
					],
					'errors' => [
						'Cannot add web scenario',
						'Web scenario "testInheritanceWeb1" already exists.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceWeb_SimpleCreate($data) {
		$this->zbxTestLogin('httpconf.php?form=Create+web+scenario&context=template&hostid='.$this->templateid);

		$this->zbxTestInputTypeWait('name', $data['name']);
		$this->zbxTestAssertElementValue('name', $data['name']);

		$this->zbxTestClick('tab_stepTab');
		foreach ($data['addStep'] as $step) {
			$this->zbxTestClickXpathWait('//td[@colspan="8"]/button[contains(@class, "element-table-add")]');
			$this->zbxTestLaunchOverlayDialog('Step of web scenario');
			$this->zbxTestInputTypeByXpath('//div[@class="overlay-dialogue-body"]//input[@id="step_name"]', $step['name']);
			$this->zbxTestInputTypeByXpath('//div[@class="overlay-dialogue-body"]//input[@id="url"]', $step['url']);
			$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');
			$this->zbxTestTextNotPresent('Page received incorrect data');
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//a[contains(@href,"javascript:httpconf.steps.open")]'));
			$this->zbxTestTextPresent($step['name']);
		}

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestTextNotPresent('Cannot add web scenario');
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Web scenario added');
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of web monitoring');
				$this->zbxTestCheckHeader('Web monitoring');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}
}
