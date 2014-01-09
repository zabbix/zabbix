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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

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

	private $newEmptyMacro = '{$NEW_EMPTY_MACRO}';

	private $oldGlobalMacroId = 7;
	private $updMacro = '{$UPD_MACRO}';
	private $updValue = 'Value of the updated macro';

	private $sqlHashGlobalMacros = '';
	private $oldHashGlobalMacros = '';

	private function openGlobalMacros() {
		$this->zbxTestLogin('adm.macros.php');
		$this->assertElementPresent('configDropDown');

		$this->zbxTestCheckTitle('Configuration of macros');
		$this->zbxTestTextPresent('CONFIGURATION OF MACROS');
		$this->zbxTestTextPresent('Macros');
		$this->zbxTestTextPresent(array('Macro', 'Value'));
	}

	private function checkGlobalMacrosOrder($skip_index = -1) {
		$globalMacros = array();

		$result = DBselect('select globalmacroid,macro,value from globalmacro');
		while ($row = DBfetch($result)) {
			$globalMacros[] = $row;
		}

		$globalMacros = order_macros($globalMacros, 'macro');
		$globalMacros = array_values($globalMacros);
		$countGlobalMacros = count($globalMacros);

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($i == $skip_index) {
				continue;
			}

			$this->assertEquals($globalMacros[$i]['globalmacroid'],
					$this->getValue('macros['.$i.'][globalmacroid]'));
			$this->assertEquals($globalMacros[$i]['macro'],
					$this->getValue('macros['.$i.'][macro]'));
			$this->assertEquals($globalMacros[$i]['value'],
					$this->getValue('macros['.$i.'][value]'));
		}
	}

	private function saveGlobalMacros($confirmation = false, $wait = true) {
		$this->zbxTestClick('save');
		if ($confirmation) {
			$this->waitForConfirmation();
		}
		if ($wait) {
			$this->wait();

			$this->zbxTestTextPresent('CONFIGURATION OF MACROS');
			$this->zbxTestTextPresent('Macros');
			$this->zbxTestTextPresent(array('Macro', 'Value'));
		}
	}

	private function calculateHash($conditions = null) {
		$this->sqlHashGlobalMacros =
			'SELECT * FROM globalmacro'.
			($conditions ? ' WHERE '.$conditions : '').
			' ORDER BY globalmacroid';
		$this->oldHashGlobalMacros = DBhash($this->sqlHashGlobalMacros);
	}

	private function verifyHash() {
		$this->assertEquals($this->oldHashGlobalMacros, DBhash($this->sqlHashGlobalMacros),
				'Chuck Norris: Data in the DB table "globalmacro" has been changed.');
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

		$this->checkGlobalMacrosOrder();

		$this->assertElementPresent('macro_add');
		$this->assertElementPresent('save');

		$this->zbxTestClick('macro_add');
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
		$this->calculateHash();

		$this->openGlobalMacros();

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_SimpleUpdateWithEmptyRow() {
		$this->calculateHash();

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->assertElementNotPresent('macros['.$countGlobalMacros.'][macro]');
		$this->assertElementNotPresent('macros['.$countGlobalMacros.'][value]');
		$this->assertElementNotPresent('macros_'.$countGlobalMacros.'_remove');

		$this->verifyHash();
	}

	/**
	 * @dataProvider wrongMacros
	 */
	public function testFormAdministrationGeneralMacros_CreateWrong($macro) {
		$this->calculateHash();

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->input_type('macros['.$countGlobalMacros.'][macro]', $macro);
		$this->input_type('macros['.$countGlobalMacros.'][value]', $this->newValue);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('ERROR: Cannot update macros');
		$this->zbxTestTextPresent('Wrong macro "'.$macro.'".');
		$this->zbxTestTextPresent('Cannot add macro.');

		$this->assertEquals($this->getValue('macros['.$countGlobalMacros.'][macro]'), $macro);
		$this->assertEquals($this->getValue('macros['.$countGlobalMacros.'][value]'), $this->newValue);

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_CreateWrongEmptyMacro() {
		$this->calculateHash();

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->input_type('macros['.$countGlobalMacros.'][macro]', '');
		$this->input_type('macros['.$countGlobalMacros.'][value]', $this->newValue);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('ERROR: Cannot update macros');
		$this->zbxTestTextPresent('Empty macro.');
		$this->zbxTestTextPresent('Cannot add macro.');

		$this->assertEquals($this->getValue('macros['.$countGlobalMacros.'][macro]'), '');
		$this->assertEquals($this->getValue('macros['.$countGlobalMacros.'][value]'), $this->newValue);

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_Create() {
		$row = DBfetch(DBselect('SELECT MAX(globalmacroid) AS globalmacroid FROM globalmacro'));

		$this->calculateHash('globalmacroid<='.$row['globalmacroid']);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->input_type('macros['.$countGlobalMacros.'][macro]', $this->newMacro);
		$this->input_type('macros['.$countGlobalMacros.'][value]', $this->newValue);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();

		$count = DBcount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE macro='.zbx_dbstr($this->newMacro).
				' AND value='.zbx_dbstr($this->newValue)
		);
		$this->assertEquals(1, $count, 'Chuck Norris: Macro has not been created in the DB.');
	}

	public function testFormAdministrationGeneralMacros_CreateEmptyValue() {
		$row = DBfetch(DBselect('SELECT MAX(globalmacroid) AS globalmacroid FROM globalmacro'));

		$this->calculateHash('globalmacroid<='.$row['globalmacroid']);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->input_type('macros['.$countGlobalMacros.'][macro]', $this->newEmptyMacro);
		$this->input_type('macros['.$countGlobalMacros.'][value]', '');

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();

		$count = DBcount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE macro='.zbx_dbstr($this->newEmptyMacro).
				' AND value='.zbx_dbstr('')
		);
		$this->assertEquals(1, $count, 'Chuck Norris: Macro has not been created in the DB.');
	}

	public function testFormAdministrationGeneralMacros_CreateDuplicate() {
		$this->calculateHash();

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->input_type('macros['.$countGlobalMacros.'][macro]', $this->newMacro);
		$this->input_type('macros['.$countGlobalMacros.'][value]', $this->newValue);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('ERROR: Cannot update macros');
		$this->zbxTestTextPresent('Macro "'.$this->newMacro.'" already exists.');
		$this->zbxTestTextPresent('Cannot add macro.');

		$this->assertEquals($this->getValue('macros['.$countGlobalMacros.'][macro]'), $this->newMacro);
		$this->assertEquals($this->getValue('macros['.$countGlobalMacros.'][value]'), $this->newValue);

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	/**
	 * @dataProvider wrongMacros
	 */
	public function testFormAdministrationGeneralMacros_UpdateWrong($macro) {
		$this->calculateHash();

		$this->openGlobalMacros();

		$this->input_type('macros[0][macro]', $macro);
		$this->input_type('macros[0][value]', $this->updValue);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('ERROR: Cannot update macros');
		$this->zbxTestTextPresent('Wrong macro "'.$macro.'".');
		$this->zbxTestTextPresent('Cannot update macro.');

		$this->assertEquals($this->getValue('macros[0][macro]'), $macro);
		$this->assertEquals($this->getValue('macros[0][value]'), $this->updValue);

		$this->checkGlobalMacrosOrder(0);

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_UpdateWrongEmptyMacro() {
		$this->calculateHash();

		$this->openGlobalMacros();

		$this->input_type('macros[0][macro]', '');
		$this->input_type('macros[0][value]', $this->updValue);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('ERROR: Cannot update macros');
		$this->zbxTestTextPresent('Empty macro.');
		$this->zbxTestTextPresent('Cannot update macro.');

		$this->assertEquals($this->getValue('macros[0][macro]'), '');
		$this->assertEquals($this->getValue('macros[0][value]'), $this->updValue);

		$this->checkGlobalMacrosOrder(0);

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_UpdateWrongEmptyMacroValue() {
		$this->calculateHash();

		$this->openGlobalMacros();

		$this->input_type('macros[0][macro]', '');
		$this->input_type('macros[0][value]', '');

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('ERROR: Cannot update macros');
		$this->zbxTestTextPresent('Empty macro.');
		$this->zbxTestTextPresent('Cannot update macro.');

		$this->assertEquals($this->getValue('macros[0][macro]'), '');
		$this->assertEquals($this->getValue('macros[0][value]'), '');

		$this->checkGlobalMacrosOrder(0);

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_Update() {
		$this->calculateHash('globalmacroid<>'.$this->oldGlobalMacroId);

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
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder($i);

		$count = DBcount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE globalmacroid='.$this->oldGlobalMacroId.
				' AND macro='.zbx_dbstr($this->updMacro).
				' AND value='.zbx_dbstr($this->updValue)
		);
		$this->assertEquals(1, $count,
				'Chuck Norris: Value of the macro has not been updated in the DB.'.
				' Perhaps it was saved with different globalmacroid.');

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_UpdateEmptyValue() {
		$this->calculateHash('globalmacroid<>'.$this->oldGlobalMacroId);

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($this->getValue('macros['.$i.'][globalmacroid]') == $this->oldGlobalMacroId) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->input_type('macros['.$i.'][macro]', $this->updMacro);
		$this->input_type('macros['.$i.'][value]', '');

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder($i);

		$count = DBcount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE globalmacroid='.$this->oldGlobalMacroId.
				' AND macro='.zbx_dbstr($this->updMacro).
				' AND value='.zbx_dbstr('')
		);
		$this->assertEquals(1, $count,
				'Chuck Norris: Value of the macro has not been updated in the DB.'.
				' Perhaps it was saved with different globalmacroid.');

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_UpdateDuplicate() {
		$this->calculateHash();

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
		$this->zbxTestTextPresent('ERROR: Cannot update macros');
		$this->zbxTestTextPresent('Macro "'.$this->newMacro.'" already exists.');
		$this->zbxTestTextPresent('Cannot update macro.');

		$this->assertEquals($this->getValue('macros['.$i.'][macro]'), $this->newMacro);
		$this->assertEquals($this->getValue('macros['.$i.'][value]'), $this->newValue);

		$this->checkGlobalMacrosOrder($i);

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_DeleteCancel() {
		$this->chooseCancelOnNextConfirmation();

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($this->getValue('macros['.$i.'][globalmacroid]') == $this->oldGlobalMacroId) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->zbxTestClick('macros_'.$i.'_remove');

		$this->saveGlobalMacros(true, false);

		$this->assertElementNotPresent('macros['.$i.'][macro]');
		$this->assertElementNotPresent('macros['.$i.'][value]');
		$this->assertElementNotPresent('macros_'.$i.'_remove');
		$this->assertElementNotPresent('macros['.$i.'][globalmacroid]');

		$this->checkGlobalMacrosOrder($i);

		$count = DBcount('SELECT globalmacroid FROM globalmacro WHERE globalmacroid='.$this->oldGlobalMacroId);
		$this->assertEquals(1, $count, 'Chuck Norris: Global macro has been deleted from the DB.');
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

		$this->zbxTestClick('macros_'.$i.'_remove');

		$this->saveGlobalMacros(true);
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$count = DBcount('SELECT globalmacroid FROM globalmacro WHERE globalmacroid='.$this->oldGlobalMacroId);
		$this->assertEquals(0, $count, 'Chuck Norris: Global macro has not been deleted from the DB.');
	}

	public function testFormAdministrationGeneralMacros_DeleteNew() {
		$this->calculateHash();

		$countGlobalMacros = DBcount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->waitForVisible('macros['.$countGlobalMacros.'][macro]');

		$this->zbxTestClick('macros_'.$countGlobalMacros.'_remove');

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_restore() {
		DBrestore_tables('globalmacro');
	}
}
