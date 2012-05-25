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
	private $macroSize = 30;
	private $macroMaxLength = 64;
	private $macroPlaceholder = '\{\$MACRO\}';
	private $macroStyle = 'text-transform: uppercase;';

	private $valueSize = 40;
	private $valueMaxLength = 255;
	private $valuePlaceholder = 'value';

	private $newMacro = '{$NEW_MACRO}';
	private $newValue = 'Value of the new macro';

	private $oldGlobalMacroId = 7;
	private $updMacro = '{$UPD_MACRO}';
	private $updValue = 'Value of the updated macro';

	private function openGlobalMacros() {
		$this->login('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->dropdown_select_wait('configDropDown', 'Macros');

		$this->assertTitle('Configuration of macros');
		$this->ok('CONFIGURATION OF MACROS');
		$this->ok('Macros');
		$this->ok(array('Macro', 'Value'));
	}

	private function saveGlobalMacros($confirmation = false) {
		$this->button_click('save');
		if ($confirmation) {
			$this->waitForConfirmation();
		}
		$this->wait();

		$this->ok('CONFIGURATION OF MACROS');
		$this->ok('Macros');
		$this->ok(array('Macro', 'Value'));
	}

	public static function wrongMacros() {
		return array(
			array('MACRO'),
			array('{'),
			array('{$'),
			array('{$MACRO'),
			array('}'),
			array('$}'),
			array('MACRO}'),
			array('$MACRO}'),
			array('{}'),
			array('{MACRO}'),
			array('}$MACRO{'),
			array('{$MACRO}}'),
			array('{{$MACRO}'),
			array('{{$MACRO}}'),
			array('{$}'),
			array('{$$}'),
			array('{$$MACRO}'),
			array('{$MACRO$}')
		);
	}

	public function testFormAdministrationGeneralMacros_backup() {
		DBsave_tables('globalmacro');
	}

	public function testFormAdministrationGeneralMacros_CheckLayout() {
		$countGlobalMacros = DBcount('select globalmacroid from globalmacro');

		$this->openGlobalMacros();

		$this->assertElementPresent('macro_add');
		$this->assertElementPresent('save');

		$this->click('id=macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		for ($i = 0; $i <= $countGlobalMacros; $i++) {
			if ($i < $countGlobalMacros) {
				$this->assertElementPresent('macros['.$i.'][globalmacroid]');
			}
			else {
				$this->assertElementNotPresent('macros['.$i.'][globalmacroid]');
			}

			$this->assertElementPresent('macros['.$i.'][macro]');
			$this->assertElementPresent('macros['.$i.'][value]');
			$this->assertElementPresent('macros_'.$i.'_remove');

			$this->assertAttribute("//input[@id='macros_${i}_macro']/@size", $this->macroSize);
			$this->assertAttribute("//input[@id='macros_${i}_macro']/@maxlength", $this->macroMaxLength);
			$this->assertAttribute("//input[@id='macros_${i}_macro']/@placeholder", $this->macroPlaceholder);
			$this->assertAttribute("//input[@id='macros_${i}_macro']/@style", $this->macroStyle);

			$this->assertAttribute("//input[@id='macros_${i}_value']/@size", $this->valueSize);
			$this->assertAttribute("//input[@id='macros_${i}_value']/@maxlength", $this->valueMaxLength);
			$this->assertAttribute("//input[@id='macros_${i}_value']/@placeholder", $this->valuePlaceholder);
		}
	}

	public function testFormAdministrationGeneralMacros_SimpleUpdate() {
		$sqlHashGlobalMacros = 'SELECT * FROM globalmacro ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$this->openGlobalMacros();

		$this->saveGlobalMacros();
		$this->ok('Macros updated');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	public function testFormAdministrationGeneralMacros_SimpleUpdateWithEmptyRow() {
		$sqlHashGlobalMacros = 'SELECT * FROM globalmacro ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->click('id=macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->saveGlobalMacros();
		$this->ok('Macros updated');

		$this->assertElementNotPresent('macros['.$countGlobalMacros.'][macro]');
		$this->assertElementNotPresent('macros['.$countGlobalMacros.'][value]');
		$this->assertElementNotPresent('macros_'.$countGlobalMacros.'_remove');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	/**
	 * @dataProvider wrongMacros
	 */
	public function testFormAdministrationGeneralMacros_CreateWrong($macro) {
		$sqlHashGlobalMacros = 'SELECT * FROM globalmacro ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->click('id=macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->input_type('macros['.$countGlobalMacros.'][macro]', $macro);
		$this->input_type('macros['.$countGlobalMacros.'][value]', $this->newValue);

		$this->saveGlobalMacros();
		$this->ok('ERROR: Cannot update macros');
		$this->ok('Wrong macro "'.$macro.'".');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	public function testFormAdministrationGeneralMacros_CreateWrongEmptyValue() {
		$sqlHashGlobalMacros = 'SELECT * FROM globalmacro ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->click('id=macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->input_type('macros['.$countGlobalMacros.'][macro]', $this->newMacro);

		$this->saveGlobalMacros();
		$this->ok('ERROR: Cannot update macros');
		$this->ok('Empty value for macro "'.$this->newMacro.'".');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	public function testFormAdministrationGeneralMacros_CreateWrongEmptyMacro() {
		$sqlHashGlobalMacros = 'SELECT * FROM globalmacro ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->click('id=macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->input_type('macros['.$countGlobalMacros.'][value]', $this->newValue);

		$this->saveGlobalMacros();
		$this->ok('ERROR: Cannot update macros');
		$this->ok('Empty macro.');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	public function testFormAdministrationGeneralMacros_Create() {
		$row = DBfetch(DBselect('SELECT MAX(globalmacroid) AS globalmacroid FROM globalmacro'));

		$sqlHashGlobalMacros =
			'SELECT * FROM globalmacro'.
			' WHERE globalmacroid<='.$row['globalmacroid'].
			' ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->click('id=macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->input_type('macros['.$countGlobalMacros.'][macro]', $this->newMacro);
		$this->input_type('macros['.$countGlobalMacros.'][value]', $this->newValue);

		$this->saveGlobalMacros();
		$this->ok('Macros updated');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');

		$count = DBcount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE macro='.zbx_dbstr($this->newMacro).
				' AND value='.zbx_dbstr($this->newValue)
		);
		$this->assertEquals(1, $count, 'Chuck Norris: Macro has not been created in the DB.');
	}

	public function testFormAdministrationGeneralMacros_CreateDuplicate() {
		$sqlHashGlobalMacros = 'SELECT * FROM globalmacro ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->click('id=macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->input_type('macros['.$countGlobalMacros.'][macro]', $this->newMacro);
		$this->input_type('macros['.$countGlobalMacros.'][value]', $this->newValue);

		$this->saveGlobalMacros();
		$this->ok('ERROR: Cannot update macros');
		$this->ok('Macro "'.$this->newMacro.'" already exists.');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	/**
	 * @dataProvider wrongMacros
	 */
	public function testFormAdministrationGeneralMacros_UpdateWrong($macro) {
		$sqlHashGlobalMacros = 'SELECT * FROM globalmacro ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$this->openGlobalMacros();

		$this->input_type('macros[0][macro]', $macro);
		$this->input_type('macros[0][value]', $this->updValue);

		$this->saveGlobalMacros();
		$this->ok('ERROR: Cannot update macros');
		$this->ok('Wrong macro "'.$macro.'".');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	public function testFormAdministrationGeneralMacros_UpdateWrongEmptyValue() {
		$sqlHashGlobalMacros = 'SELECT * FROM globalmacro ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$this->openGlobalMacros();

		$this->input_type('macros[0][macro]', $this->updMacro);
		$this->input_type('macros[0][value]', '');

		$this->saveGlobalMacros();
		$this->ok('ERROR: Cannot update macros');
		$this->ok('Empty value for macro "'.$this->updMacro.'".');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	public function testFormAdministrationGeneralMacros_UpdateWrongEmptyMacro() {
		$sqlHashGlobalMacros = 'SELECT * FROM globalmacro ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$this->openGlobalMacros();

		$this->input_type('macros[0][macro]', '');
		$this->input_type('macros[0][value]', $this->updValue);

		$this->saveGlobalMacros();
		$this->ok('ERROR: Cannot update macros');
		$this->ok('Empty macro.');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	public function testFormAdministrationGeneralMacros_Update() {
		$sqlHashGlobalMacros =
			'SELECT * FROM globalmacro'.
			' WHERE globalmacroid<>'.$this->oldGlobalMacroId.
			' ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($this->getValue('macros['.$i.'][globalmacroid]') == $this->oldGlobalMacroId) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->input_type('macros['.$i.'][macro]', $this->updMacro);
		$this->input_type('macros['.$i.'][value]', $this->updValue);

		$this->saveGlobalMacros();
		$this->ok('Macros updated');

		$count = DBcount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE globalmacroid='.$this->oldGlobalMacroId.
				' AND macro='.zbx_dbstr($this->updMacro).
				' AND value='.zbx_dbstr($this->updValue)
		);
		$this->assertEquals(1, $count,
				'Chuck Norris: Value of the macro has not been updated in the DB.'.
				' Perhaps it was saved with different globalmacroid.');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	public function testFormAdministrationGeneralMacros_UpdateDuplicate() {
		$sqlHashGlobalMacros = 'SELECT * FROM globalmacro ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($this->getValue('macros['.$i.'][globalmacroid]') == $this->oldGlobalMacroId) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->input_type('macros['.$i.'][macro]', $this->newMacro);
		$this->input_type('macros['.$i.'][value]', $this->newValue);

		$this->saveGlobalMacros();
		$this->ok('ERROR: Cannot update macros');
		$this->ok('Macro "'.$this->newMacro.'" already exists.');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	public function testFormAdministrationGeneralMacros_Delete() {
		$this->chooseOkOnNextConfirmation();

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($this->getValue('macros['.$i.'][globalmacroid]') == $this->oldGlobalMacroId) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->click('id=macros_'.$i.'_remove');

		$this->saveGlobalMacros(true);
		$this->ok('Macros updated');

		$count = DBcount('SELECT globalmacroid FROM globalmacro WHERE globalmacroid='.$this->oldGlobalMacroId);
		$this->assertEquals(0, $count, 'Chuck Norris: Global magrp has not been deleted from the DB.');
	}

	public function testFormAdministrationGeneralMacros_DeleteNew() {
		$sqlHashGlobalMacros = 'SELECT * FROM globalmacro ORDER BY globalmacroid';
		$oldHashGlobalMacros = DBhash($sqlHashGlobalMacros);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->click('id=macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->click('id=macros_'.$countGlobalMacros.'_remove');

		$this->saveGlobalMacros();
		$this->ok('Macros updated');

		$newHashGlobalMacros = DBhash($sqlHashGlobalMacros);
		$this->assertEquals($oldHashGlobalMacros, $newHashGlobalMacros,
				'Chuck Norris: Data in the DB table "globalmacro" has been changed');
	}

	public function testFormAdministrationGeneralMacros_restore() {
		DBrestore_tables('globalmacro');
	}
}
?>
