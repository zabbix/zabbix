<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../common/testFormMacros.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup globalmacro, config
 *
 * @dataSource GlobalMacros
 *
 * @onBefore prepareHostMacrosData
 */
class testFormMacrosAdministrationGeneral extends testFormMacros {

	const NEW_MACRO = '{$NEW_MACRO}';
	const NEW_VALUE = 'Value of the new macro';
	const NEW_DESCRIPTION = 'New test description';
	const NEW_EMPTY_MACRO  = '{$NEW_EMPTY_MACRO}';
	const OLD_GLOBAL_MACROID = 7;
	const UPDATE_MACRO  = '{$UPD_MACRO}';
	const UPDATE_VALUE = 'Value of the updated macro';
	const UPDATE_DESCRIPTION = 'Description of the updated macro';

	protected $sqlHashGlobalMacros;
	protected $oldHashGlobalMacros;

	protected $vault_object = 'macros';
	protected $hashi_error_field = '/1/value';
	protected $cyber_error_field = '/1/value';

	protected $update_vault_macro = '{$1_VAULT_MACRO_CHANGED}';
	protected $vault_macro_index = 1;

	protected $macro_resolve = '{$Z_GLOBAL_MACRO_2_RESOLVE}';
	protected static $macro_resolve_hostid;

	public function prepareHostMacrosData() {
		$hosts = CDataHelper::createHosts([
			[
				'host' => 'Host for checking global macro',
				'groups' => ['groupid' => self::ZABBIX_SERVERS_GROUPID],
				'items' => [
					[
						'name' => 'Macro value: {$Z_GLOBAL_MACRO_2_RESOLVE}',
						'key_' => 'trap[{$Z_GLOBAL_MACRO_2_RESOLVE}]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);
		self::$macro_resolve_hostid = $hosts['hostids']['Host for checking global macro'];
	}

	private function openGlobalMacros() {
		$this->zbxTestLogin('zabbix.php?action=macros.edit');

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

	public function testFormMacrosAdministrationGeneral_CheckLayout() {
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

			$this->zbxTestAssertAttribute('//textarea[@id="macros_'.$i.'_macro"]', "maxlength", 255);
			$this->zbxTestAssertAttribute('//textarea[@id="macros_'.$i.'_macro"]', "placeholder",'{$MACRO}');
			$this->zbxTestAssertAttribute('//textarea[@id="macros_'.$i.'_macro"]', "class", 'textarea-flexible macro');

			$macro_name = $this->query('id:macros_'.$i.'_macro')->one()->getAttribute('value');
			if ($macro_name !== '' && $this->getValueField($macro_name)->getInputType() === CInputGroupElement::TYPE_SECRET) {
				$element = 'input';
			}
			else {
				$element = 'textarea';
				$this->zbxTestAssertAttribute('//'.$element.'[@id="macros_'.$i.'_value"]', 'placeholder', 'value');
			}
			$this->zbxTestAssertAttribute('//'.$element.'[@id="macros_'.$i.'_value"]', "maxlength", 2048);

			$this->zbxTestAssertAttribute('//textarea[@id="macros_'.$i.'_description"]', "maxlength", 65535);
			$this->zbxTestAssertAttribute('//textarea[@id="macros_'.$i.'_description"]', "placeholder", 'description');
		}
	}

	public function testFormMacrosAdministrationGeneral_SimpleUpdate() {
		$this->calculateHash();

		$this->openGlobalMacros();

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	public function testFormMacrosAdministrationGeneral_SimpleUpdateWithEmptyRow() {
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
	public function testFormMacrosAdministrationGeneral_CreateWrong(string $macro, string $error) {
		$this->calculateHash();

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_macro', $macro);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_value', self::NEW_VALUE);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_description', self::NEW_DESCRIPTION);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Invalid parameter "/1/macro": '.$error.'.');

		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_macro', $macro);
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_value', self::NEW_VALUE);
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_description', self::NEW_DESCRIPTION);

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	public function testFormMacrosAdministrationGeneral_CreateWrongEmptyMacro() {
		$this->calculateHash();

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_macro', '');
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_value', self::NEW_VALUE);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_description', self::NEW_DESCRIPTION);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Invalid parameter "/1/macro": cannot be empty.');

		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_macro', '');
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_value', self::NEW_VALUE);
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_description', self::NEW_DESCRIPTION);

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	public function testFormMacrosAdministrationGeneral_Create() {
		$row = DBfetch(DBselect('SELECT MAX(globalmacroid) AS globalmacroid FROM globalmacro'));

		$this->calculateHash('globalmacroid<='.$row['globalmacroid']);

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_macro', self::NEW_MACRO);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_value', self::NEW_VALUE);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_description', self::NEW_DESCRIPTION);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();

		$count = CDBHelper::getCount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE macro='.zbx_dbstr(self::NEW_MACRO).
				' AND value='.zbx_dbstr(self::NEW_VALUE).
				' AND description='.zbx_dbstr(self::NEW_DESCRIPTION)
		);
		$this->assertEquals(1, $count, 'Chuck Norris: Macro has not been created in the DB.');
	}

	public function testFormMacrosAdministrationGeneral_CreateEmptyValue() {
		$row = DBfetch(DBselect('SELECT MAX(globalmacroid) AS globalmacroid FROM globalmacro'));

		$this->calculateHash('globalmacroid<='.$row['globalmacroid']);

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_macro', self::NEW_EMPTY_MACRO);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_value', '');
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_description', '');

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();

		$count = CDBHelper::getCount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE macro='.zbx_dbstr(self::NEW_EMPTY_MACRO).
				' AND value='.zbx_dbstr('').
					' AND description='.zbx_dbstr('')
		);
		$this->assertEquals(1, $count, 'Chuck Norris: Macro has not been created in the DB.');
	}

	public function testFormMacrosAdministrationGeneral_CreateDuplicate() {
		$this->calculateHash();

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		$this->zbxTestClick('macro_add');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('macros_'.$countGlobalMacros.'_macro'));

		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_macro',  self::NEW_MACRO);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_value', self::NEW_VALUE);
		$this->zbxTestInputType('macros_'.$countGlobalMacros.'_description', self::NEW_DESCRIPTION);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Macro "'.self::NEW_MACRO.'" already exists.');

		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_macro', self::NEW_MACRO);
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_value', self::NEW_VALUE);
		$this->zbxTestAssertElementValue('macros_'.$countGlobalMacros.'_description', self::NEW_DESCRIPTION);

		$this->checkGlobalMacrosOrder();

		$this->verifyHash();
	}

	/**
	 * @dataProvider wrongMacros
	 */
	public function testFormMacrosAdministrationGeneral_UpdateWrong(string $macro, string $error) {
		$this->calculateHash();

		$this->openGlobalMacros();

		$this->zbxTestInputType('macros_0_macro', $macro);
		$this->zbxTestInputType('macros_0_value', self::UPDATE_VALUE);
		$this->zbxTestInputType('macros_0_description', self::UPDATE_DESCRIPTION);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Invalid parameter "/1/macro": '.$error.'.');

		$this->zbxTestAssertElementValue('macros_0_macro', $macro);
		$this->zbxTestAssertElementValue('macros_0_value', self::UPDATE_VALUE);
		$this->zbxTestAssertElementValue('macros_0_description', self::UPDATE_DESCRIPTION);

		$this->checkGlobalMacrosOrder(0);

		$this->verifyHash();
	}

	public function testFormMacrosAdministrationGeneral_UpdateWrongEmptyMacro() {
		$this->calculateHash();

		$this->openGlobalMacros();

		$this->zbxTestInputType('macros_0_macro', '');
		$this->zbxTestInputType('macros_0_value', self::UPDATE_VALUE);
		$this->zbxTestInputType('macros_0_description', self::UPDATE_DESCRIPTION);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Invalid parameter "/1/macro": cannot be empty.');

		$this->zbxTestAssertElementValue('macros_0_macro', '');
		$this->zbxTestAssertElementValue('macros_0_description', self::UPDATE_DESCRIPTION);

		$this->checkGlobalMacrosOrder(0);

		$this->verifyHash();
	}

	public function testFormMacrosAdministrationGeneral_UpdateWrongEmptyMacroValue() {
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

	public function testFormMacrosAdministrationGeneral_Update() {
		$this->calculateHash('globalmacroid<>'.self::OLD_GLOBAL_MACROID);

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if ($this->zbxTestGetValue("//input[@id='macros_".$i."_globalmacroid']") == self::OLD_GLOBAL_MACROID) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->zbxTestInputType('macros_'.$i.'_macro', self::UPDATE_MACRO);
		$this->zbxTestInputType('macros_'.$i.'_value', self::UPDATE_VALUE);
		$this->zbxTestInputType('macros_'.$i.'_description', self::UPDATE_DESCRIPTION);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder($i);

		$count = CDBHelper::getCount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE globalmacroid='.self::OLD_GLOBAL_MACROID.
			' AND macro='.zbx_dbstr(self::UPDATE_MACRO).
			' AND value='.zbx_dbstr(self::UPDATE_VALUE).
			' AND description='.zbx_dbstr(self::UPDATE_DESCRIPTION)
		);
		$this->assertEquals(1, $count,
				'Chuck Norris: Value of the macro has not been updated in the DB.'.
				' Perhaps it was saved with different globalmacroid.');

		$this->verifyHash();
	}

	public function testFormMacrosAdministrationGeneral_UpdateEmptyValueAndDescription() {
		$this->calculateHash('globalmacroid<>'.self::OLD_GLOBAL_MACROID);

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if (intval($this->zbxTestGetValue('//input[@id=\'macros_'.$i.'_globalmacroid\']')) === self::OLD_GLOBAL_MACROID) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->zbxTestInputType('macros_'.$i.'_macro', self::UPDATE_MACRO);
		$this->zbxTestInputType('macros_'.$i.'_value', '');
		$this->zbxTestInputType('macros_'.$i.'_description', '');

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder($i);

		$count = CDBHelper::getCount(
			'SELECT globalmacroid FROM globalmacro'.
			' WHERE globalmacroid='.self::OLD_GLOBAL_MACROID.
				' AND macro='.zbx_dbstr(self::UPDATE_MACRO).
				' AND value='.zbx_dbstr('').
				' AND description='.zbx_dbstr('')
		);
		$this->assertEquals(1, $count,
				'Chuck Norris: Value of the macro has not been updated in the DB.'.
				' Perhaps it was saved with different globalmacroid.');

		$this->verifyHash();
	}

	public function testFormMacrosAdministrationGeneral_UpdateDuplicate() {
		$this->calculateHash();

		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if (intval($this->zbxTestGetValue('//input[@id=\'macros_'.$i.'_globalmacroid\']')) === self::OLD_GLOBAL_MACROID) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->zbxTestInputType('macros_'.$i.'_macro', self::NEW_MACRO);
		$this->zbxTestInputType('macros_'.$i.'_value', self::NEW_VALUE);
		$this->zbxTestInputType('macros_'.$i.'_description', self::NEW_DESCRIPTION);

		$this->saveGlobalMacros();
		$this->zbxTestTextPresent('Cannot update macros');
		$this->zbxTestTextPresent('Macro "'.self::NEW_MACRO.'" already exists.');

		$this->zbxTestAssertElementValue('macros_'.$i.'_macro', self::NEW_MACRO);
		$this->zbxTestAssertElementValue('macros_'.$i.'_value', self::NEW_VALUE);
		$this->zbxTestAssertElementValue('macros_'.$i.'_description', self::NEW_DESCRIPTION);

		$this->checkGlobalMacrosOrder($i);

		$this->verifyHash();
	}

	public function testFormMacrosAdministrationGeneral_DeleteCancel() {
		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if (intval($this->zbxTestGetValue('//input[@id=\'macros_'.$i.'_globalmacroid\']')) === self::OLD_GLOBAL_MACROID) {
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

		$count = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro WHERE globalmacroid='.self::OLD_GLOBAL_MACROID);
		$this->assertEquals(1, $count, 'Chuck Norris: Global macro has been deleted from the DB.');
	}

	public function testFormMacrosAdministrationGeneral_Delete() {
		$countGlobalMacros = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro');

		$this->openGlobalMacros();

		for ($i = 0; $i < $countGlobalMacros; $i++) {
			if (intval($this->zbxTestGetValue('//input[@id=\'macros_'.$i.'_globalmacroid\']')) === self::OLD_GLOBAL_MACROID) {
				break;
			}
		}
		$this->assertNotEquals($i, $countGlobalMacros);

		$this->zbxTestClick('macros_'.$i.'_remove');

		$this->saveGlobalMacros(true);
		$this->zbxTestTextPresent('Macros updated');

		$this->checkGlobalMacrosOrder();

		$count = CDBHelper::getCount('SELECT globalmacroid FROM globalmacro WHERE globalmacroid='.self::OLD_GLOBAL_MACROID);
		$this->assertEquals(0, $count, 'Chuck Norris: Global macro has not been deleted from the DB.');
	}

	public function testFormMacrosAdministrationGeneral_DeleteNew() {
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
	public function testFormMacrosAdministrationGeneral_CreateSecretMacros($data) {
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
	public function testFormMacrosAdministrationGeneral_UpdateSecretMacros($data) {
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
	public function testFormMacrosAdministrationGeneral_RevertSecretMacroChanges($data) {
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
	public function testFormMacrosAdministrationGeneral_ResolveSecretMacro($data) {
		$this->resolveSecretMacro($data, self::$macro_resolve_hostid);
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 */
	public function testFormMacrosAdministrationGeneral_CreateVaultMacros($data) {
		$this->selectVault($data['vault']);
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
		CDataHelper::call('settings.update', [
			'vault_provider' => 0
		]);

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
	 * @dataProvider getUpdateVaultMacrosNormalData
	 * @dataProvider getUpdateVaultMacrosCommonData
	 */
	public function testFormMacrosAdministrationGeneral_UpdateVaultMacros($data) {
		$this->selectVault($data['vault']);
		$this->page->login()->open('zabbix.php?action=macros.edit')->waitUntilReady();
		$this->fillMacros([$data['fields']]);
		$this->query('button:Update')->one()->click();
		$this->page->waitUntilReady();
		$result = [];
		foreach (['macro', 'value', 'description'] as $field) {
			$result[] = $this->query('xpath://textarea[@id="macros_'.$data['fields']['index'].'_'.$field.'"]')->one()->getText();
		}
		$this->assertEquals([$data['fields']['macro'], $data['fields']['value']['text'],
				$data['fields']['description']], $result);
		array_push($result, ZBX_MACRO_TYPE_VAULT);
		$sql = 'SELECT macro, value, description, type FROM globalmacro WHERE macro='.zbx_dbstr($data['fields']['macro']);
		$this->assertEquals($result, array_values(CDBHelper::getRow($sql)));
	}

	/**
	 * Check vault macros validation after changing vault type. Works only for CyberArk. Right now, there is almost NO
	 * incorrect validation for HashiCorp.
	 */
	public function testFormMacrosAdministrationGeneral_checkVaultValidation() {
		$hashicorp = [
			'fields' =>
				[
				'macro' => '{$VAULT}',
				'value' => [
					'text' => 'secret/path:key',
					'type' => 'Vault secret'
				],
				'description' => 'HashiCorp vault description'
			],
			'error' => 'Invalid parameter "/1/value": incorrect syntax near "secret/path:key".'
		];

		$this->page->login();

		$this->page->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();

		// Check in setting what Vault is enabled.
		$setting_form = $this->query('name:otherForm')->asForm()->one();
		$setting_form->fill(['Vault provider' => 'CyberArk Vault']);
		$setting_form->submit();

		// Try to create macros with Vault type different from settings.
		$this->page->open('zabbix.php?action=macros.edit')->waitUntilReady();
		$this->fillMacros([$hashicorp['fields']]);
		$this->query('button:Update')->one()->click();
		$this->assertMessage(TEST_BAD, 'Cannot update macros', $hashicorp['error']);

		// Change Vault in settings to correct one and create macros with this Vault.
		$this->page->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();
		$setting_form->fill(['Vault provider'=> 'HashiCorp Vault'])->submit();
		$this->page->open('zabbix.php?action=macros.edit')->waitUntilReady();
		$this->fillMacros([$hashicorp['fields']]);
		$this->query('button:Update')->one()->click();
		$this->assertMessage(TEST_GOOD, 'Macros updated');

		// Remove created macros.
		$this->removeMacro([$hashicorp['fields']]);
		$this->query('button:Update')->one()->click();
		$this->page->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Macros updated');
	}
}
