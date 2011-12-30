<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testPageAdministrationGeneralRegexp extends CWebTest {

	public static function allRegexps(){
		return DBdata('select * from regexps');
	}

	/**
	* @dataProvider allRegexps
	*/
	public function testPageAdministrationGeneralRegexp_CheckLayout($regexp) {

		$this->login('config.php');
		$this->dropdown_select_wait('configDropDown', 'Regular expressions');
		$this->assertTitle('Configuration of Zabbix');
		$this->ok('CONFIGURATION OF ZABBIX');
		$this->ok('Regular expressions');
		$this->ok(array('Name', 'Expressions'));
		// checking that all elements exists
		$this->assertElementPresent('go');
		// single quotes does not work here
		$this->assertElementPresent("//select[@id='go']/option[text()='Delete selected']");
		$this->assertElementPresent('goButton');

		// checking that all regexps are present in the report
		$this->ok(array($regexp['name']));
		$this->dropdown_select('go', 'Delete selected');
	}

	/**
	* @dataProvider allRegexps
	*/
	public function testPageAdministrationGeneralRegexp_SimpleUpdate($regexp) {

		$sqlRegexps="select * from regexps order by regexpid";
		$oldHashRegexps=DBhash($sqlRegexps);

		$sqlExpressions="select * from expressions order by expressionid";
		$oldHashExpressions=DBhash($sqlExpressions);

		$this->login('config.php');
		$this->dropdown_select_wait('configDropDown', 'Regular expressions');
		$this->ok('Regular expressions');
		// checking that can click on each regexp and then save it without any changes
		$this->click('link='.$regexp['name']);
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->ok('Regular expression updated');

		$this->assertEquals($oldHashRegexps, DBhash($sqlRegexps), "Chuck Norris: no-change regexp update should not update data in table 'regexps'");

		$this->assertEquals($oldHashExpressions, DBhash($sqlExpressions), "Chuck Norris: no-change regexp update should not update data in table 'expressions'");
	}

	/**
	* @dataProvider allRegexps
	*/
	public function testPageAdministrationGeneralRegexp_MassDelete($regexp) {
		$name = $regexp['name'];

		DBsave_tables('regexps');

		$this->login('config.php');
		$this->dropdown_select_wait('configDropDown', 'Regular expressions');
		$this->assertTitle('Configuration of Zabbix');
		$this->ok('CONFIGURATION OF ZABBIX');
		$this->ok('Regular expressions');
		$this->ok(array('Name', 'Expressions'));

		// detecting "regexpid" values for clicking checkboxes "regexpids[$regexpid]"
		$sql = "SELECT regexpid FROM regexps WHERE name='$name'";
		$result = DBfetch(DBselect($sql));
		$regexpid = $result['regexpid'];
		$this->checkbox_select("regexpids[$regexpid]");
		$this->dropdown_select('go', 'Delete selected');
		$this->chooseOkOnNextConfirmation();
		$this->click('goButton');
		$this->waitForConfirmation();
		$this->wait();
		$this->ok('Regular expression deleted');

		// $sql = "SELECT * FROM regexps r WHERE r.name='$name'";
		$sql1 = "SELECT * FROM regexps r WHERE r.name='$name'";
		$this->assertEquals(0, DBcount($sql1), 'Chuck Norris: Regexp has not been deleted from the DB');

		$sql2 = "SELECT * FROM expressions e WHERE e.regexpid=$regexpid";
		$this->assertEquals(0, DBcount($sql2), 'Chuck Norris: Regexp expressions has not been deleted from the DB');
		DBrestore_tables('regexps');
	}

	public function testPageAdministrationGeneralRegexp_MassDeleteAll() {

		// DBsave_tables('regexps');

		$this->login('config.php');
		$this->dropdown_select_wait('configDropDown', 'Regular expressions');
		$this->assertTitle('Configuration of Zabbix');
		$this->ok('CONFIGURATION OF ZABBIX');
		$this->ok('Regular expressions');
		$this->ok(array('Name', 'Expressions'));

		$this->checkbox_select("all_regexps");
		$this->dropdown_select('go', 'Delete selected');
		$this->chooseOkOnNextConfirmation();
		$this->click('goButton');
		$this->waitForConfirmation();
		$this->wait();
		$this->ok('Regular expression deleted');

		$sql1 = "SELECT * FROM regexps";
		$this->assertEquals(0, DBcount($sql1), 'Chuck Norris: Regexp has not been deleted from the DB');

		$sql2 = "SELECT * FROM regexps r,expressions e WHERE r.regexpid=e.regexpid";
		$this->assertEquals(0, DBcount($sql2), 'Chuck Norris: Regexp expressions has not been deleted from the DB');

		// DBrestore_tables('regexps');
	}

}
