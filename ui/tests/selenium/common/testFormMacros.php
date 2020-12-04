<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../traits/MacrosTrait.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * Base class for Macros tests.
 */
abstract class testFormMacros extends CWebTest {

	use MacrosTrait;

	const SQL_HOSTS = 'SELECT * FROM hosts ORDER BY hostid';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public static function getHash() {
		return CDBHelper::getHash(self::SQL_HOSTS);
	}

	/**
	 * Test creating of host or template with Macros.
	 *
	 * @param array	$data			given data provider
	 * @param string $form_type		string used in form selector
	 * @param string $host_type		string defining is it host, template or host prototype
	 * @param boolean $is_prototype	defines is it prototype or not
	 * @param int $lld_id			points to LLD rule id where host prototype belongs
	 */
	protected function checkCreate($data, $form_type, $host_type, $is_prototype = false, $lld_id = null) {
		$this->page->login()->open(
			$is_prototype
			? 'host_prototypes.php?form=create&parent_discoveryid='.$lld_id
			: $host_type.'s.php?form=create'
		);

		$form = $this->query('id:'.$form_type.'-form')->waitUntilPresent()->asForm()->one();
		$form->fill([ucfirst($host_type).' name' => $data['Name']]);

		if ($is_prototype) {
			$form->selectTab('Groups');
		}
		$form->fill(['Groups' => 'Zabbix servers']);

		$this->checkMacros($data, $form_type, $data['Name'], $host_type, $is_prototype, $lld_id);
	}

	/**
	 * Test updating Macros in host, host prototype or template.
	 *
	 * @param array	$data			given data provider
	 * @param string $name			name of host where changes are made
	 * @param string $form_type		string used in form selector
	 * @param string $host_type		string defining is it host, template or host prototype
	 * @param boolean $is_prototype	defines is it prototype or not
	 * @param int $lld_id			points to LLD rule id where host prototype belongs
	 */
	protected function checkUpdate($data, $name, $form_type, $host_type, $is_prototype = false, $lld_id = null) {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($name));

		$this->page->login()->open(
			$is_prototype
			? 'host_prototypes.php?form=update&parent_discoveryid='.$lld_id.'&hostid='.$id
			: $host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0'
		);

		$this->checkMacros($data, $form_type, $name, $host_type, $is_prototype, $lld_id);
	}

	/**
	 * Test removing Macros from host, host prototype or template.
	 *
	 * @param string $name			name of host where changes are made
	 * @param string $form_type		string used in form selector
	 * @param string $host_type		string defining is it host, template or host prototype
	 * @param boolean $is_prototype	defines is it prototype or not
	 * @param int $lld_id			points to LLD rule id where host prototype belongs
	 */
	protected function checkRemove($name, $form_type, $host_type, $is_prototype = false, $lld_id = null) {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($name));

		$this->page->login()->open(
			$is_prototype
			? 'host_prototypes.php?form=update&parent_discoveryid='.$lld_id.'&hostid='.$id
			: $host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0'
		);

		$form = $this->query('id:'.$form_type.'-form')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Macros');
		$this->removeMacros();
		$form->submit();

		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());

		$this->assertEquals(($is_prototype ? 'Host prototype' : ucfirst($host_type)).' updated', $message->getTitle());

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($name)));
		// Check the results in form.
		$this->checkMacrosFields($name, $is_prototype, $lld_id, $host_type, $form_type, null);
	}

	/**
	 * Test changing and resetting global macro on host, prototype or template.
	 *
	 * @param string $form_type		string used in form selector
	 * @param string $host_type		string defining is it host, template or host prototype
	 * @param boolean $is_prototype	defines is it prototype or not
	 * @param int $lld_id			points to LLD rule id where host prototype belongs
	 */
	protected function checkChangeRemoveInheritedMacro($form_type, $host_type, $is_prototype = false, $lld_id = null) {
		if ($is_prototype) {
			$this->page->login()->open('host_prototypes.php?form=create&parent_discoveryid='.$lld_id);
			$form = $this->query('id:'.$form_type.'-form')->waitUntilPresent()->asForm()->one();

			$name = 'Host prototype with edited global {#MACRO}';
			$form->fill([ucfirst($host_type).' name' => $name]);
			$form->selectTab('Groups');
			$form->fill(['Groups' => 'Zabbix servers']);
		}
		else {
			$this->page->login()->open($host_type.'s.php?form=create');
			$form = $this->query('name:'.$form_type.'Form')->waitUntilPresent()->asForm()->one();

			$name = $host_type.' with edited global macro';
			$form->fill([
				ucfirst($host_type).' name' => $name,
				'Groups' => 'Zabbix servers'
			]);
		}
		$form->selectTab('Macros');
		// Go to inherited macros.
		$this->query('xpath://label[@for="show_inherited_macros_1"]')->waitUntilPresent()->one()->click();
		// Check inherited macros before changes.
		$this->checkInheritedGlobalMacros();

		$edited_macros = [
			[
				'macro' => '{$1}',
				'value' => 'New updated Numeric macro 1',
				'description' => 'New updated Test description 2'
			]
		];

		$count = count($edited_macros);
		// Change macro to edited values.
		for ($i = 0; $i < $count; $i += 1) {
			$this->query('id:macros_'.$i.'_change')->one()->click();
			$this->query('id:macros_'.$i.'_value')->one()->fill($edited_macros[$i]['value']);
			$this->query('id:macros_'.$i.'_description')->one()->fill($edited_macros[$i]['description']);
		}

		$form->submit();

		// Check saved edited macros in host/template form.
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($name));

		$this->page->open(
			$is_prototype
			? 'host_prototypes.php?form=update&parent_discoveryid='.$lld_id.'&hostid='.$id
			: $host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0'
		);

		$form->selectTab('Macros');
		$this->assertMacros($edited_macros);

		// Remove edited macro and reset to global.
		$this->query('xpath://label[@for="show_inherited_macros_1"]')->waitUntilPresent()->one()->click();
		for ($i = 0; $i < $count; $i += 1) {
			$this->query('id:macros_'.$i.'_change')->waitUntilVisible()->one()->click();
		}
		$form->submit();

		$this->page->open(
			$is_prototype
			? 'host_prototypes.php?form=update&parent_discoveryid='.$lld_id.'&hostid='.$id
			: $host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0'
		);

		$form->selectTab('Macros');
		$this->assertMacros();

		// Check inherited macros again after remove.
		$this->query('xpath://label[@for="show_inherited_macros_1"]')->waitUntilPresent()->one()->click();
		$this->checkInheritedGlobalMacros();
	}

	/**
	 *  Check adding and saving macros in host, host prototype or template form.
	 *
	 * @param array	$data			given data provider
	 * @param string $form_type		string used in form selector
	 * @param string $name			name of host where changes are made
	 * @param string $host_type		string defining is it host, template or host prototype
	 * @param boolean $is_prototype	defines is it prototype or not
	 * @param int $lld_id			points to LLD rule id where host prototype belongs
	 */
	private function checkMacros($data = null, $form_type, $name, $host_type, $is_prototype, $lld_id) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = $this->getHash();
		}

		$form = $this->query('id:'.$form_type.'-form')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Macros');
		$this->fillMacros($data['macros']);
		$form->submit();

		$message = CMessageElement::find()->one();
		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertTrue($message->isGood());
				$this->assertEquals($data['success_message'], $message->getTitle());
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($name)));
				// Check the results in form.
				$this->checkMacrosFields($name, $is_prototype, $lld_id, $host_type, $form_type, $data);
				break;
			case TEST_BAD:
				$this->assertTrue($message->isBad());
				$this->assertEquals($data['error_message'], $message->getTitle());
				$this->assertTrue($message->hasLine($data['error_details']));
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL_HOSTS));
				break;
		}
	}

	/**
	 * Checking saved macros in host, host prototype or template form.
	 *
	 * @param string $name			name of host where changes are made
	 * @param boolean $is_prototype	defines is it prototype or not
	 * @param int $lld_id			points to LLD rule id where host prototype belongs
	 * @param string $host_type		string defining is it host, template or host prototype
	 * @param string $form_type		string used in form selector
	 * @param array	$data			given data provider
	 */
	private function checkMacrosFields($name, $is_prototype, $lld_id, $host_type, $form_type,  $data = null) {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($name));

		$this->page->open(
			$is_prototype
			? 'host_prototypes.php?form=update&parent_discoveryid='.$lld_id.'&hostid='.$id
			: $host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0'
		);

		$form = $this->query('id:'.$form_type.'-form')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Macros');
		$this->assertMacros(($data !== null) ? $data['macros'] : []);
		$this->query('xpath://label[@for="show_inherited_macros_1"]')->waitUntilPresent()->one()->click();
		// Get all macros defined for this host.
		$hostmacros = CDBHelper::getAll('SELECT macro, value, description, type FROM hostmacro where hostid ='.$id);

		$this->checkInheritedGlobalMacros($hostmacros);
	}

	/**
	 * Check host/host prototype/template inherited macros in form matching with global macros in DB.
	 *
	 * @param array $hostmacros		all macros defined particularly for this host
	 */
	public function checkInheritedGlobalMacros($hostmacros = []) {
		// Create two macros arrays: from DB and from Frontend form.
		$macros = [
			// Merge global macros with host defined macros.
			'database' => array_merge(
					CDBHelper::getAll('SELECT macro, value, description, type FROM globalmacro'),
					$hostmacros
				),
			'frontend' => []
		];

		// If the macro is expected to have type "Secret text", replace the value from db with the secret macro pattern.
		for ($i = 0; $i < count($macros['database']); $i++) {
			if (intval($macros['database'][$i]['type']) === ZBX_MACRO_TYPE_SECRET) {
				$macros['database'][$i]['value'] = '******';
			}
		}

		// Write macros rows from Frontend to array.
		$table = $this->query('id:tbl_macros')->waitUntilVisible()->asTable()->one();
		$count = $table->getRows()->count() - 1;
		for ($i = 0; $i < $count; $i += 2) {
			$macro = [];
			$row = $table->getRow($i);
			$macro['macro'] = $row->query('xpath:./td[1]/textarea')->one()->getValue();
			$macro_value = $this->getValueField($macro['macro']);
			$macro['value'] = $macro_value->getValue();
			$macro['description'] = $table->getRow($i + 1)->query('tag:textarea')->one()->getValue();
			$macro['type'] = ($macro_value->getInputType() === CInputGroupElement::TYPE_SECRET) ?
					ZBX_MACRO_TYPE_SECRET : ZBX_MACRO_TYPE_TEXT;

			$macros['frontend'][] = $macro;
		}

		// Sort arrays by Macros.
		foreach ($macros as &$array) {
			usort($array, function ($a, $b) {
				return strcmp($a['macro'], $b['macro']);
			});
		}
		unset($array);

		// Compare macros from DB with macros from Frontend.
		$this->assertEquals($macros['database'], $macros['frontend']);
	}

	/**
	 * Check content of macro value InputGroup element for macros.
	 *
	 * @param array		$data		given data provider
	 * @param string	$url		url of configuration form of the corresponding entity
	 * @param string	$source		type of entity that is being checked (hots, hostPrototype, template)
	 */
	public function checkSecretMacrosLayout($data, $url, $source) {
		$this->openMacrosTab($url, $source, true);

		// Check that value field is disabled for global macros in "Inherited and host macros" tab.
		if (CTestArrayHelper::get($data, 'global', false)) {
			$this->query('xpath://label[@for="show_inherited_macros_1"]')->waitUntilPresent()->one()->click();
			$value_field = $this->getValueField($data['macro']);
			$change_button = $value_field->getNewValueButton();
			$revert_button = $value_field->getRevertButton();

			if ($data['type'] === CInputGroupElement::TYPE_TEXT) {
				$this->assertTrue($value_field->query('xpath:./textarea')->one()->isAttributePresent('readonly'));
				$this->assertEquals(2048, $value_field->query('xpath:./textarea')->one()->getAttribute('maxlength'));
				$this->assertFalse($change_button->isValid());
				$this->assertFalse($revert_button->isValid());
			}
			else {
				$this->assertFalse($value_field->query('xpath:.//input')->one()->isEnabled());
				$this->assertEquals(2048, $value_field->query('xpath:.//input')->one()->getAttribute('maxlength'));
				$this->assertFalse($change_button->isEnabled());
				$this->assertFalse($revert_button->isClickable());
			}
			$this->assertFalse($value_field->query('xpath:.//button[contains(@class, "btn-dropdown-toggle")]')->one()->isEnabled());
		}
		else {
			$value_field = $this->getValueField($data['macro']);
			$change_button = $value_field->getNewValueButton();
			$revert_button = $value_field->getRevertButton();
			$textarea_xpath = 'xpath:.//textarea[contains(@class, "textarea-flexible")]';

			if ($data['type'] === CInputGroupElement::TYPE_SECRET) {
				$this->assertFalse($value_field->query($textarea_xpath)->exists());
				$this->assertEquals(2048, $value_field->query('xpath:.//input')->one()->getAttribute('maxlength'));

				$this->assertTrue($change_button->isValid());
				$this->assertFalse($revert_button->isClickable());
				// Change value text or type and check that New value button is not displayed and Revert button appeared.
				if (CTestArrayHelper::get($data, 'change_type', false)) {
					$value_field->changeInputType(CInputGroupElement::TYPE_TEXT);
				}
				else {
					$change_button->click();
				}
				$value_field->invalidate();

				$this->assertFalse($change_button->isEnabled());
				$this->assertTrue($revert_button->isClickable());
			}
			else {
				$this->assertTrue($value_field->query($textarea_xpath)->exists());
				$this->assertEquals(2048, $value_field->query('xpath:./textarea')->one()->getAttribute('maxlength'));
				$this->assertFalse($change_button->isValid());
				$this->assertFalse($revert_button->isValid());

				// Change value type to "Secret text" and check that new value and revert buttons were not added.
				$value_field->changeInputType(CInputGroupElement::TYPE_SECRET);
				$value_field->invalidate();

				$this->assertFalse($value_field->getNewValueButton()->isValid());
				$this->assertFalse($value_field->getRevertButton()->isValid());
			}
		}
	}

	/**
	 * Check adding and saving secret macros for host, host prototype and template entities.
	 *
	 * @param array		$data		given data provider
	 * @param string	$url		url of configuration form of the corresponding entity
	 * @param string	$source		type of entity that is being checked (hots, hostPrototype, template)
	 */
	public function createSecretMacros($data, $url, $source) {
		$this->openMacrosTab($url, $source, true);

		// Check that macro values have type plain text by default.
		if (CTestArrayHelper::get($data, 'check_default_type', false)){
			$this->assertEquals(CInputGroupElement::TYPE_TEXT, $this->query('xpath://div[contains(@class, "macro-value")]')
					->one()->asInputGroup()->getInputType());
		}

		$this->fillMacros([$data['macro_fields']]);
		$value_field = $this->query('xpath://div[contains(@class, "macro-value")]')->all()->last()->asInputGroup();

		// Check that macro type is set correctly.
		$this->assertEquals($data['macro_fields']['value']['type'], $value_field->getInputType());

		// Check that textarea input element is not available for secret text macros.
		$this->assertEquals($data['macro_fields']['value']['text'], $value_field->getValue());

		// Switch to tab with inherited and instance macros and verify that the value is secret but is still accessible.
		$this->checkInheritedTab($data['macro_fields'], true);
		// Check that macro value is hidden but is still accessible after switching back to instance macros list.
		$value_field = $this->getValueField($data['macro_fields']['macro']);
		$this->assertEquals(CInputGroupElement::TYPE_SECRET, $value_field->getInputType());

		// Change macro type back to text (is needed) before saving the changes.
		if (CTestArrayHelper::get($data, 'back_to_text', false)) {
			$value_field->changeInputType(CInputGroupElement::TYPE_TEXT);
		}

		$this->query('button:Update')->one()->click();
		$this->openMacrosTab($url, $source);
		$value_field = $this->getValueField($data['macro_fields']['macro']);

		if (CTestArrayHelper::get($data, 'back_to_text', false)) {
			$this->assertEquals($data['macro_fields']['value']['text'], $value_field->getValue());
			// Switch to tab with inherited and instance macros and verify that the value is plain text.
			$this->checkInheritedTab($data['macro_fields'], false);
		}
		else {
			$this->assertEquals('******', $value_field->getValue());
			// Switch to tab with inherited and instance macros and verify that the value is secret and is not accessible.
			$this->checkInheritedTab($data['macro_fields'], true, false);
		}

		// Check macro value, type and description in DB.
		$sql = 'SELECT value, description, type FROM hostmacro WHERE macro='.zbx_dbstr($data['macro_fields']['macro']);
		$type = (CTestArrayHelper::get($data, 'back_to_text', false)) ? ZBX_MACRO_TYPE_TEXT : ZBX_MACRO_TYPE_SECRET;
		$this->assertEquals([$data['macro_fields']['value']['text'], $data['macro_fields']['description'], $type],
				array_values(CDBHelper::getRow($sql)));
	}

	/**
	 *  Check updateof secret macros for host, host prototype and template entities.
	 *
	 * @param array		$data		given data provider
	 * @param string	$url		url of configuration form of the corresponding entity
	 * @param string	$source		type of entity that is being checked (hots, hostPrototype, template)
	 */
	public function updateSecretMacros($data, $url, $source) {
		$this->openMacrosTab($url, $source, true);
		$this->fillMacros([$data]);

		// Check that new values are correct in Inherited and host prototype macros tab before saving the values.
		$value_field = $this->getValueField($data['macro']);
		$secret = (CTestArrayHelper::get($data['value'], 'type', CInputGroupElement::TYPE_SECRET) ===
				CInputGroupElement::TYPE_SECRET) ? true : false;
		$this->checkInheritedTab($data, $secret);

		$this->query('button:Update')->one()->click();
		$this->openMacrosTab($url, $source);

		$value_field = $this->getValueField($data['macro']);
		if (CTestArrayHelper::get($data['value'], 'type', CInputGroupElement::TYPE_SECRET) === CInputGroupElement::TYPE_SECRET) {
			$this->assertEquals(CInputGroupElement::TYPE_SECRET, $value_field->getInputType());
			$this->assertEquals('******', $value_field->getValue());
			$this->checkInheritedTab($data, true, false);
		}
		else {
			$this->assertEquals(CInputGroupElement::TYPE_TEXT, $value_field->getInputType());
			$this->assertEquals($data['value']['text'], $value_field->getValue());
			$this->checkInheritedTab($data, false);
		}
		// Check in DB that values of the updated macros are correct.
		$sql = 'SELECT value FROM hostmacro WHERE macro='.zbx_dbstr($data['macro']);
		$this->assertEquals($data['value']['text'], CDBHelper::getValue($sql));
	}

	/**
	 *  Check that it is possible to revert secret macro changes for host, host prototype and template entities.
	 *
	 * @param array		$data		given data provider
	 * @param string	$url		url of configuration form of the corresponding entity
	 * @param string	$source		type of entity that is being checked (hots, hostPrototype, template)
	 */
	public function revertSecretMacroChanges($data, $url, $source) {
		$this->openMacrosTab($url, $source, true);

		$sql = 'SELECT * FROM hostmacro WHERE macro='.CDBHelper::escape($data['macro_fields']['macro']);
		$old_values = CDBHelper::getRow($sql);

		$value_field = $this->getValueField($data['macro_fields']['macro']);

		// Check that the existing macro value is hidden.
		$this->assertEquals('******', $value_field->getValue());

		// Change the value of the secret macro
		$value_field->getNewValueButton()->click();
		$this->assertEquals('', $value_field->getValue());
		$value_field->fill('New_macro_value');

		if (CTestArrayHelper::get($data, 'set_to_text', false)) {
			$value_field->changeInputType(CInputGroupElement::TYPE_TEXT);
			$this->assertEquals('New_macro_value', $value_field->getValue());
		}

		// Press revert button and save the changes.
		$value_field->getRevertButton()->click();
		$this->query('button:Update')->one()->click();

		// Check that no macro value changes took place.
		$this->openMacrosTab($url, $source);
		$this->assertEquals('******', $this->getValueField($data['macro_fields']['macro'])->getValue());
		$this->assertEquals($old_values, CDBHelper::getRow($sql));
	}

	/**
	 *  Check how secret macro is resolved in item name for host, host prototype and template entities.
	 *
	 * @param array		$data		given data provider
	 * @param string	$url		url of configuration form of the corresponding entity
	 * @param string	$source		type of entity that is being checked (hots, hostPrototype, template)
	 */
	public function resolveSecretMacro($macro, $url, $source) {
		$this->page->login()->open($url)->waitUntilReady();
		$this->query('link:Items')->one()->click();
		$this->page->waitUntilReady();

		$this->assertTrue($this->query('link', 'Macro value: '.$macro['value'])->exists());

		$this->openMacrosTab($url, $source);

		$value_field = $this->getValueField($macro['macro']);
		$value_field->changeInputType(CInputGroupElement::TYPE_SECRET);

		$this->query('button:Update')->one()->click();
		$this->openMacrosTab($url, $source);

		$this->query('link:Items')->one()->click();
		$this->page->waitUntilReady();

		$this->assertTrue($this->query('link', 'Macro value: ******')->exists());
	}

	/**
	 * Function opens Inherited and instance macros tab and checks the value, it the value has type Secret text and if
	 * the value is displayed.
	 *
	 * @param type $data		given data provider
	 * @param type $secret		flag that indicates if the value should have type "Secret text".
	 * @param type $available	flag that indicates if the value should be available.
	 */
	public function checkInheritedTab($data, $secret, $available = true) {
		// Switch to the list of inherited and instance macros.
		$this->query('xpath://label[@for="show_inherited_macros_1"]')->waitUntilPresent()->one()->click();
		$this->query('class:is-loading')->waitUntilNotPresent();
		$value_field = $this->getValueField($data['macro']);

		if ($secret) {
			$this->assertEquals(CInputGroupElement::TYPE_SECRET, $value_field->getInputType());
			$expected_value = ($available) ? $data['value']['text'] : '******';
			$this->assertEquals($expected_value, $value_field->getValue());
		}
		else {
			$this->assertEquals(CInputGroupElement::TYPE_TEXT, $value_field->getInputType());
			$this->assertEquals($data['value']['text'], $value_field->getValue());
		}
		// Switch back to the list of instance macros.
		$this->query('xpath://label[@for="show_inherited_macros_0"]')->waitUntilPresent()->one()->click();
		$this->query('class:is-loading')->waitUntilNotPresent();
	}

	public function createVaultMacros($data, $url, $source) {
		$this->openMacrosTab($url, $source, true);
		$this->fillMacros([$data['macro_fields']]);
		$this->query('button:Update')->one()->click();
		if ($data['expected'] == TEST_BAD) {
			$this->assertMessage($data['expected'], $data['title'], $data['message']);
		}
		else {
			$this->assertMessage($data['expected'], $data['title']);
			$sql = 'SELECT value, description, type FROM hostmacro WHERE macro='.zbx_dbstr($data['macro_fields']['macro']);
			$this->assertEquals([$data['macro_fields']['value']['text'], $data['macro_fields']['description'], ZBX_MACRO_TYPE_VAULT],
					array_values(CDBHelper::getRow($sql)));
			$this->openMacrosTab($url, $source);
			$value_field = $this->getValueField($data['macro_fields']['macro']);
			$this->assertEquals($data['macro_fields']['value']['text'], $value_field->getValue());
		}
	}

	public function updateVaultMacros($data, $url, $source) {
		$this->openMacrosTab($url, $source, true);
		$this->fillMacros([$data]);
		$this->query('button:Update')->one()->click();
		$this->openMacrosTab($url, $source);
		$result = [];
		foreach (['macro', 'value', 'description'] as $field) {
			$result[] = $this->query('xpath://textarea[@id="macros_'.$data['index'].'_'.$field.'"]')->one()->getText();
		}
		$this->assertEquals([$data['macro'], $data['value']['text'], $data['description']], $result);
		array_push($result, ZBX_MACRO_TYPE_VAULT);
		$sql = 'SELECT macro, value, description, type FROM hostmacro WHERE macro='.zbx_dbstr($data['macro']);
		$this->assertEquals($result, array_values(CDBHelper::getRow($sql)));
	}

	/**
	 * Function opens Macros tab in corresponding instance configuration form.
	 *
	 * @param type $url			URL that leads to the configuration form of corresponding entity
	 * @param type $source		type of entity that is being checked (hots, hostPrototype, template)
	 * @param type $login		flag that indicates whether login should occur before opening the configuration form
	 */
	private function openMacrosTab($url, $source, $login = false) {
		if ($login) {
			$this->page->login();
		}
		$this->page->open($url)->waitUntilReady();
		$this->query('id:'.$source.'-form')->asForm()->one()->selectTab('Macros');
	}
}
