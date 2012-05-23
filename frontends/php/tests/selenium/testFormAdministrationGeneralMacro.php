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
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testFormAdministrationGeneralMacro extends CWebTest {
	private $newmacro = '{$NEW_MACRO}';
	private $newmacrovalue = 'Value of the new macro';

	public function testFormAdministrationGeneralRegexp_CheckLayout() {

		$count = DBcount('select * from globalmacro');

		$this->login('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->dropdown_select_wait('configDropDown', 'Macros');
		$this->assertTitle('Configuration of macros');
		$this->ok('CONFIGURATION OF MACROS');
		$this->ok('Macros');
		$this->ok('Macro');
		$this->ok('Value');
		$this->assertElementPresent('macros[0][macro]');
		$this->assertAttribute("//input[@id='macros_0_macro']/@maxlength", '64');
		$this->assertAttribute("//input[@id='macros_0_macro']/@size", '30');
		for ($i=0; $i<$count; $i++) {
			$this->assertElementPresent("macros[$i][value]");
		}
		$this->assertElementPresent('macro_add');		// button "Add"
		$this->assertElementPresent('macros_0_remove');		// button "Delete selected"
		$this->assertElementPresent('save');
	}

	public function testFormAdministrationGeneralMacro_Create() {

		$count = DBcount('select * from globalmacro');

		// create new Global macro
		$this->login('adm.macros.php');
		$this->click('id=macro_add');
		$this->waitForVisible("macros[$count][macro]");
		$this->input_type("macros[$count][macro]", $this->newmacro);		// id= macros_N_macro
		$this->input_type("macros[$count][value]", $this->newmacrovalue);	// id= macros_N_macro
		$this->button_click('save');
		$this->wait();
		$this->ok('Macros updated');
		$this->ok('CONFIGURATION OF MACROS');
		$this->ok('Macros');

		$sql = "SELECT * FROM globalmacro WHERE macro='".$this->newmacro."' and value='".$this->newmacrovalue."'";
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Macro has not been created in the DB.');
	}

	public function testFormAdministrationGeneralMacro_Update() {
		DBsave_tables('globalmacro');

		$macro = '{$UPDATEDMACRO}';
		$macrovalue = 'Value of the updated macro';

		$this->login('adm.macros.php');
		$globalmacroid = $this->getValue('macros[0][globalmacroid]');
		$this->input_type('macros[0][macro]', $macro);
		$this->input_type('macros[0][value]', $macrovalue);
		$this->button_click('save');
		$this->wait();
		// checking that value of the macro has been updated in the DB
		$count = DBcount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE globalmacroid='.$globalmacroid.
				' AND macro='.zbx_dbstr($macro).
				' AND value='.zbx_dbstr($macrovalue)
		);
		$this->assertEquals(1, $count, 'Chuck Norris: Value of the macro has not been updated in the DB. Perhaps it was saved with different globalmacroid.');

		DBrestore_tables('globalmacro');
	}

	public function testFormAdministrationGeneralMacro_CreateDuplicate() {
		$sql = "SELECT * FROM globalmacro order by globalmacroid";
		$oldHashGlobalmacro = DBhash($sql);

		$row = DBfetch(DBselect('select * from globalmacro order by macro limit 1'));

		$this->login('adm.macros.php');

		// creating already existing macro
		$this->button_click('macro_add');
		$this->input_type('macros[1][macro]', $row['macro']);
		$this->input_type('macros[1][value]', $row['value']);
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Cannot update macros');
		$this->ok('Macro "'.$row['macro'].'" already exists');
		$this->ok('Macros');
		$this->ok('Macro');
		$this->ok('Value');
		$newHashGlobalmacro = DBhash($sql);
		$this->assertEquals($oldHashGlobalmacro, $newHashGlobalmacro, 'Data in the DB table "globalmacro" has been changed');
	}

	public static function wrongMacros() {
		return array(
			array('1','value'),
			array('AAA','value'),
			array('{','value'),
			array('}','value'),
			array('{}','value'),
			array('{AAA}','value'),
			array('{$}','value'),
			array('{$$}','value'),
			array('{$$A}','value'),
			array('{$A$}','value'),
			array('{$ABCEFG}','')
		);
	}

	/**
	 * @dataProvider wrongMacros
	 */
	public function testFormAdministrationGeneralMacro_CreateWrong($macro, $value) {

		$sql = "SELECT * FROM globalmacro order by globalmacroid";
		$oldHashGlobalmacro = DBhash($sql);

		$count = DBcount('select * from globalmacro');

		// create new Global macro
		$this->login('adm.macros.php');
		$this->click('id=macro_add');
		$this->waitForVisible("macros[$count][macro]");
		$this->input_type("macros[$count][macro]", $macro);
		$this->input_type("macros[$count][value]", $value);
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Cannot update macros');
		$this->ok('Cannot add macro');

		$newHashGlobalmacro = DBhash($sql);
		$this->assertEquals($oldHashGlobalmacro, $newHashGlobalmacro, 'Data in the DB table "globalmacro" has been changed');
	}

	public function testFormAdministrationGeneralMacro_Delete() {
		$this->chooseOkOnNextConfirmation();

		$this->login('adm.macros.php');
		// No way to select what macro to remove currently
		$this->click("id=macros_0_remove");
		$this->click("id=save");
		$this->waitForConfirmation();
		$this->wait();
		$this->ok('Macros updated');
		$this->ok('CONFIGURATION OF MACROS');
		$this->ok('Macros');
		$this->ok('Macro');
		$this->ok('Value');
	}
}
?>
