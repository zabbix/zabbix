<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

class testInheritanceWeb extends CWebTest {
	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	public function testInheritanceWeb_backup() {
		DBsave_tables('httptest');
	}

	public static function update() {
		return DBdata(
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
		$oldHashHttpTests = DBhash($sqlHttpTests);
		$sqlHttpSteps = 'SELECT * FROM httpstep ORDER BY httpstepid';
		$oldHashHttpSteps = DBhash($sqlHttpSteps);
		$sqlHttpTestItems = 'SELECT * FROM httptestitem ORDER BY httptestitemid';
		$oldHashHttpTestItems = DBhash($sqlHttpTestItems);
		$sqlHttpStepItems = 'SELECT * FROM httpstepitem ORDER BY httpstepitemid';
		$oldHashHttpStepItems = DBhash($sqlHttpStepItems);
		$sqlItems = 'SELECT * FROM items ORDER BY itemid';
		$oldHashItems = DBhash($sqlItems);

		$this->zbxTestLogin('httpconf.php?form=update&hostid='.$data['hostid'].'&httptestid='.$data['httptestid']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of web monitoring');
		$this->zbxTestTextPresent('Web scenario updated');

		$this->assertEquals($oldHashHttpTests, DBhash($sqlHttpTests));
		$this->assertEquals($oldHashHttpSteps, DBhash($sqlHttpSteps));
		$this->assertEquals($oldHashHttpTestItems, DBhash($sqlHttpTestItems));
		$this->assertEquals($oldHashHttpStepItems, DBhash($sqlHttpStepItems));
		$this->assertEquals($oldHashItems, DBhash($sqlItems));
	}

	public static function create() {
		return array(
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceWeb5',
					'addStep' => array(
						array('name' => 'testInheritanceStep1', 'url' => 'http://testInheritanceStep1/')
					)
				)
			)
		);
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceWeb_SimpleCreate($data) {
		$this->zbxTestLogin('httpconf.php?form=Create+web+scenario&hostid='.$this->templateid);

		$this->input_type('name', $data['name']);

		$this->zbxTestClick('tab_stepTab');
		foreach ($data['addStep'] as $step) {
			$this->zbxTestLaunchPopup('add_step');
			$this->input_type('name', $step['name']);
			$this->input_type('url', $step['url']);
			$this->zbxTestClick('add');
			$this->selectWindow();
			sleep(1);
		}

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of web monitoring');
				$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');
				$this->zbxTestTextPresent('Web scenario added');
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of web monitoring');
				$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}

	public function testInheritanceWeb_restore() {
		DBrestore_tables('httptest');
	}
}
