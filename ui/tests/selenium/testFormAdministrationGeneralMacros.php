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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/traits/MacrosTrait.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/common/testFormMacros.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup globalmacro
 */
class testFormAdministrationGeneralMacros extends testFormMacros {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	use MacrosTrait;

	private $macroMaxLength = 255;
	private $macroPlaceholder = '{$MACRO}';
	private $macroClass = 'textarea-flexible macro';

	private $valueMaxLength = 2048;
	private $valuePlaceholder = 'value';

	private $descriptionMaxLength = 65535;
	private $descriptionPlaceholder = 'description';

	private $newMacro = '{$NEW_MACRO}';
	private $newValue = 'Value of the new macro';
	private $newDescription = 'New test description';

	private $newEmptyMacro = '{$NEW_EMPTY_MACRO}';

	private $oldGlobalMacroId = 7;
	private $updMacro = '{$UPD_MACRO}';
	private $updValue = 'Value of the updated macro';
	private $updDescription = 'Description of the updated macro';

	private $sqlHashGlobalMacros = '';
	private $oldHashGlobalMacros = '';

	public $macro_resolve = '{$Z_GLOBAL_MACRO_2_RESOLVE}';
	public $macro_resolve_hostid = 99134;

	public $vault_object = 'macros';
	public $vault_error_field = '/1/value';

	public $update_vault_macro = '{$1_VAULT_MACRO_CHANGED}';
	public $vault_macro_index = 1;

	private function openGlobalMacros() {
		$this->zbxTestLogin('zabbix.php?action=macros.edit');
		$this->query('id:page-title-general')->asPopupButton()->one()->select('Macros');

		$this->zbxTestCheckTitle('Configuration of macros');
		$this->zbxTestCheckHeader('Macros');
		$this->zbxTestTextPresent('Macros');
		$this->zbxTestTextPresent(['Macro', 'Value', 'Description']);
	}

	private function checkGlobalMacrosOrder($skip_index = -1) {
		$globalMacros = [];

		$result = DBselect('select globalmacroid,macro,type,value,description from globalmacro');
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

			$this->zbxTestAssertElementValue('macros_'.$i.'_globalmacroid',
					$globalMacros[$i]['globalmacroid']);
			$this->zbxTestAssertElementValue('macros_'.$i.'_macro',
					$globalMacros[$i]['macro']);

			if (intval($globalMacros[$i]['type']) === ZBX_MACRO_TYPE_SECRET) {
				$globalMacros[$i]['value'] = '******';
			}
			$this->zbxTestAssertElementValue('macros_'.$i.'_value',
					$globalMacros[$i]['value']);
			$this->zbxTestAssertElementValue('macros_'.$i.'_description',
					$globalMacros[$i]['description']);
		}
	}

	private function saveGlobalMacros($confirmation = false) {
		$this->zbxTestClick('update');
		if ($confirmation) {
			$this->zbxTestAcceptAlert();
		}
			$this->zbxTestCheckHeader('Macros');
			$this->zbxTestTextPresent('Macros');
			$this->zbxTestTextPresent(['Macro', 'Value', 'Description']);
	}

	private function calculateHash($conditions = null) {
		$this->sqlHashGlobalMacros =
			'SELECT * FROM globalmacro'.
			($conditions ? ' WHERE '.$conditions : '').
			' ORDER BY globalmacroid';
		$this->oldHashGlobalMacros = CDBHelper::getHash($this->sqlHashGlobalMacros);
	}

	private function verifyHash() {
		$this->assertEquals($this->oldHashGlobalMacros, CDBHelper::getHash($this->sqlHashGlobalMacros),
				'Chuck Norris: Data in the DB table "globalmacro" has been changed.');
	}

	public static function wrongMacros() {
		return [
			['MACRO', 'incorrect syntax near "MACRO"'],
			['{', 'unexpected end of macro'],
			['{$', 'unexpected end of macro'],
			['{$MACRO', 'unexpected end of macro'],
			['}', 'incorrect syntax near "}"'],
			['$}', 'incorrect syntax near "$}"'],
			['MACRO}', 'incorrect syntax near "MACRO}"'],
			['$MACRO}', 'incorrect syntax near "$MACRO}"'],
			['{}', 'incorrect syntax near "}"'],
			['{MACRO}', 'incorrect syntax near "MACRO}"'],
			['}$MACRO{', 'incorrect syntax near "}$MACRO{"'],
			['{$MACRO}}', 'incorrect syntax near "}"'],
			['{{$MACRO}', 'incorrect syntax near "{$MACRO}"'],
			['{{$MACRO}}', 'incorrect syntax near "{$MACRO}}"'],
			['{$}', 'incorrect syntax near "}"'],
			['{$$}', 'incorrect syntax near "$}"'],
			['{$$MACRO}', 'incorrect syntax near "$MACRO}"'],
			['{$MACRO$}', 'incorrect syntax near "$}"']
		];
	}

	public function testFormAdministrationGeneralMacros_CheckLayout() {
		$countGlobalMacros = CDBHelper::getCount('select globalmacroid from globalmacro');

		$this->openGlobalMacros();

		$this->checkGlobalMacrosOrder();

		$this->zbxTestAssertElementPresentId('macro_add');

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		for ($i = 0; $i <= $countGlobalMacros; $i++) {
			if ($i < $countGlobalMacros) {
				$this->zbxTestAssertElementPresentId('macros_'.$i.'_globalmacroid');
			}
			else {
				$this->zbxTestAssertElementNotPresentId('macros_'.$i.'_globalmacroid');
			}

			$this->zbxTestAssertElementPresentId('macros_'.$i.'_macro');
			$this->zbxTestAssertElementPresentId('macros_'.$i.'_value');
			$this->zbxTestAssertElementPresentId('macros_'.$i.'_description');
			$this->zbxTestAssertElementPresentId('macros_'.$i.'_remove');

			$this->zbxTestAssertAttribute('//textarea[@id="macros_'.$i.'_macro"]', "maxlength", $this->macroMaxLength);
			$this->zbxTestAssertAttribute('//textarea[@id="macros_'.$i.'_macro"]', "placeholder", $this->macroPlaceholder);
			$this->zbxTestAssertAttribute('//textarea[@id="macros_'.$i.'_macro"]', "class", $this->macroClass);

			$macro_name = $this->query('id:macros_'.$i.'_macro')->one()->getAttribute('value');
			if ($macro_name !== '' && $this->getValueField($macro_name)->getInputType() === CInputGroupElement::TYPE_SECRET) {
				$element = 'input';
			}
			else {
				$element = 'textarea';
				$this->zbxTestAssertAttribute('//'.$element.'[@id="macros_'.$i.'_value"]', 'placeholder', $this->valuePlaceholder);
			}
			$this->zbxTestAssertAttribute('//'.$element.'[@id="macros_'.$i.'_value"]', "maxlength", $this->valueMaxLength);

			$this->zbxTestAssertAttribute('//textarea[@id="macros_'.$i.'_description"]', "maxlength", $this->descriptionMaxLength);
			$this->zbxTestAssertAttribute('//textarea[@id="macros_'.$i.'_description"]', "placeholder", $this->descriptionPlaceholder);
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

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->zbxTestAssertElementNotPresentId('macros_'.$countGlobalMacros.'_macro');
		$this->zbxTestAssertElementNotPresentId('macros_'.$countGlobalMacros.'_value');
		$this->zbxTestAssertElementNotPresentId('macros_'.$countGlobalMacros.'_description');
		$this->zbxTestAssertElementNotPresentId('macros_'.$countGlobalMacros.'_remove');

		$this->verifyHash();
	}

	/**
	 * @dataProvider wrongMacros
	 */
	public function testFormAdministrationGeneralMacros_CreateWrong(string $macro, string $error) {
		$this->calculateHash();

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_macro', $macro);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_value', $this->newValue);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_description', $this->newDescription);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Invalid parameter "/1/macro": '.$error.'.');

		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_macro', $macro);
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_value', $this->newValue);
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_description', $this->newDescription);

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_CreateWrongEmptyMacro() {
		$this->calculateHash();

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_macro', '');
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_value', $this->newValue);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_description', $this->newDescription);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Invalid parameter "/1/macro": cannot be empty.');

		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_macro', '');
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_value', $this->newValue);
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_description', $this->newDescription);

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_Create() {
		$row = DBfetch(DBselect('SELECT MAX(globalmacroid) AS globalmacroid FROM globalmacro'));

		$this->calculateHash('globalmacroid<='.$row['globalmacroid']);

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_macro',  $this->newMacro);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_value', $this->newValue);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_description', $this->newDescription);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();

		$count = CDBHelper::getCount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE macro='.zbx_dbstr($this->newMacro).
				' AND value='.zbx_dbstr($this->newValue).
					' AND description='.zbx_dbstr($this->newDescription)
		);
		$this->assertEquals(1, $count, 'Chuck Norris: Macro has not been created in the DB.');
	}

	public function testFormAdministrationGeneralMacros_CreateEmptyValue() {
		$row = DBfetch(DBselect('SELECT MAX(globalmacroid) AS globalmacroid FROM globalmacro'));

		$this->calculateHash('globalmacroid<='.$row['globalmacroid']);

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_macro',  $this->newEmptyMacro);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_value', '');
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_description', '');

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();

		$count = CDBHelper::getCount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE macro='.zbx_dbstr($this->newEmptyMacro).
				' AND value='.zbx_dbstr('').
					' AND description='.zbx_dbstr('')
		);
		$this->assertEquals(1, $count, 'Chuck Norris: Macro has not been created in the DB.');
	}

	public function testFormAdministrationGeneralMacros_CreateDuplicate() {
		$this->calculateHash();

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_macro',  $this->newMacro);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_value', $this->newValue);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_description', $this->newDescription);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Macro "'.$this->newMacro.'" already exists.');

		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_macro', $this->newMacro);
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_value', $this->newValue);
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_description', $this->newDescription);

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	/**
	 * @dataProvider wrongMacros
	 */
	public function testFormAdministrationGeneralMacros_UpdateWrong(string $macro, string $error) {
		$this->calculateHash();

		$this->openGlobalMacros();

		$this->zbxTestInputType('macros_0_macro', $macro);
		$this->zbxTestInputType('macros_0_value', $this->updValue);
		$this->zbxTestInputType('macros_0_description', $this->updDescription);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Invalid parameter "/1/macro": '.$error.'.');

		$this->zbxTestAssertElementValue('macros_0_macro', $macro);
		$this->zbxTestAssertElementValue('macros_0_value', $this->updValue);
		$this->zbxTestAssertElementValue('macros_0_description', $this->updDescription);

		$this->checkGlobalMacrosOrder(0);

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_UpdateWrongEmptyMacro() {
		$this->calculateHash();

		$this->openGlobalMacros();

		$this->zbxTestInputType('macros_0_macro', '');
		$this->zbxTestInputType('macros_0_value', $this->updValue);
		$this->zbxTestInputType('macros_0_description', $this->updDescription);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Invalid parameter "/1/macro": cannot be empty.');

		$this->zbxTestAssertElementValue('macros_0_macro', '');
		$this->zbxTestAssertElementValue('macros_0_description', $this->updDescription);

		$this->checkGlobalMacrosOrder(0);

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_UpdateWrongEmptyMacroValue() {
		$this->calculateHash();

		$this->openGlobalMacros();

		$this->zbxTestInputType('macros_0_macro', '');
		$this->zbxTestInputType('macros_0_value', '');
		$this->zbxTestInputType('macros_0_description', '');

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Invalid parameter "/1/macro": cannot be empty.');

		$this->zbxTestAssertElementValue('macros_0_macro', '');
		$this->zbxTestAssertElementValue('macros_0_value', '');
		$this->zbxTestAssertElementValue('macros_0_description', '');

		$this->checkGlobalMacrosOrder(0);

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_Update() {
		$this->calculateHash('globalmacroid<>'.$this->oldGlobalMacroId);

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($this->zbxTestGetValue("//input[@id='macros_".$i."_globalmacroid']") == $this->oldGlobalMacroId) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->zbxTestInputType('macros_'.$i.'_macro', $this->updMacro);
		$this->zbxTestInputType('macros_'.$i.'_value', $this->updValue);
		$this->zbxTestInputType('macros_'.$i.'_description', $this->updDescription);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder($i);

		$count = CDBHelper::getCount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE globalmacroid='.$this->oldGlobalMacroId.
			' AND macro='.zbx_dbstr($this->updMacro).
			' AND value='.zbx_dbstr($this->updValue).
			' AND description='.zbx_dbstr($this->updDescription)
		);
		$this->assertEquals(1, $count,
				'Chuck Norris: Value of the macro has not been updated in the DB.'.
				' Perhaps it was saved with different globalmacroid.');

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_UpdateEmptyValueAndDescription() {
		$this->calculateHash('globalmacroid<>'.$this->oldGlobalMacroId);

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($this->zbxTestGetValue("//input[@id='macros_".$i."_globalmacroid']") == $this->oldGlobalMacroId) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->zbxTestInputType('macros_'.$i.'_macro', $this->updMacro);
		$this->zbxTestInputType('macros_'.$i.'_value', '');
		$this->zbxTestInputType('macros_'.$i.'_description', '');

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder($i);

		$count = CDBHelper::getCount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE globalmacroid='.$this->oldGlobalMacroId.
				' AND macro='.zbx_dbstr($this->updMacro).
					' AND value='.zbx_dbstr('').
						' AND description='.zbx_dbstr('')
		);
		$this->assertEquals(1, $count,
				'Chuck Norris: Value of the macro has not been updated in the DB.'.
				' Perhaps it was saved with different globalmacroid.');

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_UpdateDuplicate() {
		$this->calculateHash();

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($this->zbxTestGetValue("//input[@id='macros_".$i."_globalmacroid']") == $this->oldGlobalMacroId) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->zbxTestInputType('macros_'.$i.'_macro', $this->newMacro);
		$this->zbxTestInputType('macros_'.$i.'_value', $this->newValue);
		$this->zbxTestInputType('macros_'.$i.'_description', $this->newDescription);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Macro "'.$this->newMacro.'" already exists.');

		$this->zbxTestAssertElementValue('macros_'.$i.'_macro', $this->newMacro);
		$this->zbxTestAssertElementValue('macros_'.$i.'_value', $this->newValue);
		$this->zbxTestAssertElementValue('macros_'.$i.'_description', $this->newDescription);

		$this->checkGlobalMacrosOrder($i);

		$this->verifyHash();
	}

	public function testFormAdministrationGeneralMacros_DeleteCancel() {
		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($this->zbxTestGetValue("//input[@id='macros_".$i."_globalmacroid']") == $this->oldGlobalMacroId) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->zbxTestClick('macros_'.$i.'_remove');

		$this->zbxTestClick('update');
		$this->zbxTestDismissAlert();
		$this->zbxTestTextNotPresent('Macros updated');

		$this->zbxTestAssertElementNotPresentId('macros_'.$i.'_macro');
		$this->zbxTestAssertElementNotPresentId('macros_'.$i.'_value');
		$this->zbxTestAssertElementNotPresentId('macros_'.$i.'_description');
		$this->zbxTestAssertElementNotPresentId('macros_'.$i.'_remove');
		$this->zbxTestAssertElementNotPresentId('macros_'.$i.'_globalmacroid');

		$this->checkGlobalMacrosOrder($i);

		$count = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro WHERE globalmacroid='.$this->oldGlobalMacroId);
		$this->assertEquals(1, $count, 'Chuck Norris: Global macro has been deleted from the DB.');
	}

	public function testFormAdministrationGeneralMacros_Delete() {
		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($this->zbxTestGetValue("//input[@id='macros_".$i."_globalmacroid']") == $this->oldGlobalMacroId) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->zbxTestClick('macros_'.$i.'_remove');

		$this->saveGlobalMacros(true);
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$count = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro WHERE globalmacroid='.$this->oldGlobalMacroId);
		$this->assertEquals(0, $count, 'Chuck Norris: Global macro has not been deleted from the DB.');
	}

	public function testFormAdministrationGeneralMacros_DeleteNew() {
		$this->calculateHash();

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->zbxTestClick('macros_'.$countGlobalMacros.'_remove');

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	public function getCreateSecretMacrosData() {
		return [
			[
				[
					'macro_fields' => [
						'macro' => '{$Z_NEW_SECRET_MACRO}',
						'value' => [
							'text' => 'secret value',
							'type' => 'Secret text'
						],
						'description' => 'secret description'
					]
				]
			],
			[
				[
					'macro_fields' => [
						'macro' => '{$Z_NEW_TEXT_MACRO}',
						'value' => [
							'text' => 'plain text value',
							'type' => 'Secret text'
						],
						'description' => 'plain text description'
					],
					'back_to_text' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateSecretMacrosData
	 */
	public function testFormAdministrationGeneralMacros_CreateSecretMacros($data) {
		$this->page->login()->open('zabbix.php?action=macros.edit')->waitUntilReady();
		$this->fillMacros([$data['macro_fields']]);

		// Check that value field is filled correctly.
		$value_field = $this->query('xpath://div[contains(@class, "macro-value")]')->all()->last()->asInputGroup();
		$this->assertEquals($data['macro_fields']['value']['type'], $value_field->getInputType());
		$this->assertEquals($data['macro_fields']['value']['text'], $value_field->getValue());
		// Check that textarea input element is not available for secret text macros.
		$textarea_xpath = 'xpath:.//textarea[contains(@class, "textarea-flexible")]';
		if ($value_field->getInputType() === CInputGroupElement::TYPE_SECRET) {
			$this->assertFalse($value_field->query($textarea_xpath)->exists());
		}

		// If needed, change value type back to "Text" and verify that value is accessible.
		if (CTestArrayHelper::get($data, 'back_to_text', false)) {
			$value_field->changeInputType(CInputGroupElement::TYPE_TEXT);
			$this->assertEquals(CInputGroupElement::TYPE_TEXT, $value_field->getInputType());
			$this->assertTrue($value_field->query($textarea_xpath)->exists());
			$this->assertEquals($data['macro_fields']['value']['text'], $value_field->getValue());
		}

		$this->query('button:Update')->one()->click();
		$value_field = $this->getValueField($data['macro_fields']['macro']);

		if (CTestArrayHelper::get($data, 'back_to_text', false)) {
			$this->assertEquals(CInputGroupElement::TYPE_TEXT, $value_field->getInputType());
			$this->assertEquals($data['macro_fields']['value']['text'], $value_field->getValue());
			$this->assertFalse($value_field->getNewValueButton()->isValid());
			$this->assertFalse($value_field->getRevertButton()->isValid());
		}
		else {
			$this->assertEquals('******', $value_field->getValue());
			$change_button = $value_field->getNewValueButton();
			$revert_button = $value_field->getRevertButton();

			// Check that "Set new value" button is perent and "Revert" button is hidden if secret value wasn't modified.
			$this->assertTrue($change_button->isEnabled());
			$this->assertFalse($revert_button->isClickable());

			// Modify secret value and check that Revert button becomes clickable and "Set new value" button is Disabled.
			$change_button->click();
			$value_field->invalidate();
			$this->assertFalse($change_button->isEnabled());
			$this->assertTrue($revert_button->isClickable());
			// Revert changes
			$revert_button->click();
		}
		$this->query('button:Update')->one()->click();
		// Check macro value, type and description in DB.
		$sql = 'SELECT value, description, type FROM globalmacro WHERE macro='.zbx_dbstr($data['macro_fields']['macro']);
		$type = (CTestArrayHelper::get($data, 'back_to_text', false)) ? ZBX_MACRO_TYPE_TEXT : ZBX_MACRO_TYPE_SECRET;
		$this->assertEquals([$data['macro_fields']['value']['text'], $data['macro_fields']['description'], $type],
				array_values(CDBHelper::getRow($sql)));
	}

	public function getUpdateSecretMacrosData() {
		return [
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 9,
					'macro' => '{$X_SECRET_2_SECRET}',
					'value' => [
						'text' => 'This text is updated and should stay secret'
					]
				]
			],
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 10,
					'macro' => '{$X_SECRET_2_TEXT}',
					'value' => [
						'text' => 'This text is updated and should become visible',
						'type' => 'Text'
					]
				]
			],
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 11,
					'macro' => '{$X_TEXT_2_SECRET}',
					'value' => [
						'text' => 'This text is updated and should become secret',
						'type' => 'Secret text'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateSecretMacrosData
	 */
	public function testFormAdministrationGeneralMacros_UpdateSecretMacros($data) {
		$this->page->login()->open('zabbix.php?action=macros.edit')->waitUntilReady();
		$this->fillMacros([$data]);
		$this->query('button:Update')->one()->click();

		$value_field = $this->getValueField($data['macro']);
		if (CTestArrayHelper::get($data['value'], 'type', CInputGroupElement::TYPE_SECRET) === CInputGroupElement::TYPE_SECRET) {
			$this->assertEquals(CInputGroupElement::TYPE_SECRET, $value_field->getInputType());
			$this->assertEquals('******', $value_field->getValue());
		}
		else {
			$this->assertEquals(CInputGroupElement::TYPE_TEXT, $value_field->getInputType());
			$this->assertEquals($data['value']['text'], $value_field->getValue());
		}
		$sql = 'SELECT value FROM globalmacro WHERE macro='.zbx_dbstr($data['macro']);
		$this->assertEquals($data['value']['text'], CDBHelper::getValue($sql));
	}

	public function getRevertSecretMacrosData() {
		return [
			[
				[
					'macro_fields' => [
						'macro' => '{$Y_SECRET_MACRO_REVERT}',
						'value' => 'Secret host value'
					]
				]
			],
			[
				[
					'macro_fields' => [
						'macro' => '{$Y_SECRET_MACRO_2_TEXT_REVERT}',
						'value' => 'Secret host value 2'
					],
					'set_to_text' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getRevertSecretMacrosData
	 */
	public function testFormAdministrationGeneralMacros_RevertSecretMacroChanges($data) {
		$this->page->login()->open('zabbix.php?action=macros.edit')->waitUntilReady();

		$sql = 'SELECT * FROM globalmacro WHERE macro in ('.CDBHelper::escape($data['macro_fields']['macro']).')';
		$old_values = CDBHelper::getRow($sql);

		$value_field = $this->getValueField($data['macro_fields']['macro']);

		// Check that the existing macro value is hidden.
		$this->assertEquals('******', $value_field->getValue());

		// Change the value of the secret macro.
		$value_field->getNewValueButton()->click();
		$this->assertEquals('', $value_field->getValue());
		$value_field->fill('New_macro_value');

		if (CTestArrayHelper::get($data, 'set_to_text', false)) {
			$value_field->changeInputType(CInputGroupElement::TYPE_TEXT);
			$this->assertEquals('New_macro_value', $value_field->getValue());
		}

		// Press revert button amd save the changes and make sure that changes were reverted.
		$value_field->getRevertButton()->click();
		$this->query('button:Update')->one()->click();
		// Check that no macro value changes took place.
		$this->assertEquals('******', $this->getValueField($data['macro_fields']['macro'])->getValue());
		$this->assertEquals($old_values, CDBHelper::getRow($sql));
	}

	/**
	 * 	Test opens the list of items of "Available host" and "Latest data" and checks macro resolution in item fields.
	 *
	 * @dataProvider getResolveSecretMacroData
	 */
	public function testFormAdministrationGeneralMacros_ResolveSecretMacro($data) {
		$this->resolveSecretMacro($data);
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 */
	public function testFormAdministrationGeneralMacros_CreateVaultMacros($data) {
		$this->page->login()->open('zabbix.php?action=macros.edit')->waitUntilReady();
		$this->fillMacros([$data['macro_fields']]);
		$this->query('button:Update')->one()->click();
		if ($data['expected'] == TEST_BAD) {
			$this->assertMessage($data['expected'], $data['title'], $data['message']);
		}
		else {
			$this->assertMessage($data['expected'], $data['title']);
			$sql = 'SELECT value, description, type FROM globalmacro WHERE macro='.zbx_dbstr($data['macro_fields']['macro']);
			$this->assertEquals([$data['macro_fields']['value']['text'], $data['macro_fields']['description'], ZBX_MACRO_TYPE_VAULT],
				array_values(CDBHelper::getRow($sql)));
			$value_field = $this->getValueField($data['macro_fields']['macro']);
			$this->assertEquals($data['macro_fields']['value']['text'], $value_field->getValue());
		}
	}

	public function prepareUpdateData() {
		$response = CDataHelper::call('usermacro.createglobal', [
			'macro' => '{$1_VAULT_MACRO}',
			'value' => 'secret/path:key',
			'type' => ZBX_MACRO_TYPE_VAULT
		]);

		$this->assertArrayHasKey('globalmacroids', $response);
	}

	/**
	 * @onBeforeOnce prepareUpdateData
	 *
	 * @dataProvider getUpdateVaultMacrosData
	 */
	public function testFormAdministrationGeneralMacros_UpdateVaultMacros($data) {
		$this->page->login()->open('zabbix.php?action=macros.edit')->waitUntilReady();
		$this->fillMacros([$data]);
		$this->query('button:Update')->one()->click();
		$this->page->waitUntilReady();
		$result = [];
		foreach (['macro', 'value', 'description'] as $field) {
			$result[] = $this->query('xpath://textarea[@id="macros_'.$data['index'].'_'.$field.'"]')->one()->getText();
		}
		$this->assertEquals([$data['macro'], $data['value']['text'], $data['description']], $result);
		array_push($result, ZBX_MACRO_TYPE_VAULT);
		$sql = 'SELECT macro, value, description, type FROM globalmacro WHERE macro='.zbx_dbstr($data['macro']);
		$this->assertEquals($result, array_values(CDBHelper::getRow($sql)));
	}
}
