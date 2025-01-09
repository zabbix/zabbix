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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * Base class for Administration General configuration function tests.
 */
class testFormAdministrationGeneral extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	public $config_link;
	public $form_selector;
	public $default_values;
	public $db_default_values;
	public $custom_values;

	/**
	 * Test for checking form update without changing any data.
	 *
	 * @param boolean    $trigger_disp   If it is Trigger displaying options form
	 */
	public function executeSimpleUpdate($trigger_disp = false) {
		$config = CDBHelper::getRow('SELECT * FROM config ORDER BY configid');
		$this->page->login()->open($this->config_link);
		$form = $this->query($this->form_selector)->waitUntilVisible()->asForm()->one();
		$values = $form->getFields()->asValues();
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');

		$this->page->refresh();
		$this->page->waitUntilReady();
		$form->invalidate();
		// Check that DBdata is not changed.
		$this->assertEquals($config, CDBHelper::getRow('SELECT * FROM config ORDER BY configid'));
		// Check that Frontend form is not changed.
		$this->assertEquals($values, $form->getFields()->asValues());
		// Check that Frontend colors are not changed.
		if ($trigger_disp) {
			$form->checkValue($this->default_values);
		}
	}

	/**
	 * Test for checking 'Reset defaults' button.
	 *
	 * @param boolean    $other			 If it is Other configuration parameters form
	 */
	public function executeResetButtonTest($other = false) {
		$this->page->login()->open($this->config_link);
		$form = $this->query($this->form_selector)->waitUntilVisible()->asForm()->one();
		// Reset form in case of some previous scenario.
		$this->resetConfiguration($form, $this->default_values, 'Reset defaults', $other);
		$default_config = CDBHelper::getRow('SELECT * FROM config');

		// Reset form after customly filled data and check that values are reset to default or reset is cancelled.
		foreach (['Cancel', 'Reset defaults'] as $action) {
			// Fill form with custom data.
			$form->fill($this->custom_values);
			$form->submit();
			$this->assertMessage(TEST_GOOD, 'Configuration updated');
			$custom_config = CDBHelper::getRow('SELECT * FROM config');
			// Check custom data in form.
			$this->page->refresh()->waitUntilReady();
			$form->invalidate();
			$form->checkValue($this->custom_values);
			$this->resetConfiguration($form, $this->default_values, $action, $other, $this->custom_values);

			$config = ($action === 'Reset defaults') ? $default_config : $custom_config;
			$this->assertEquals($config, CDBHelper::getRow('SELECT * FROM config'));
		}
	}

	/**
	 * Function for configuration resetting.
	 *
	 * @param element  $form		 Settings configuration form
	 * @param array    $default		 Default form values
	 * @param string   $action		 Reset defaults or Cancel
	 * @param boolean  $other		 Is this Other parameters form or not
	 * @param array    $custom		 Custom values for filling into settings form
	 */
	public function resetConfiguration($form, $default, $action, $other = false, $custom = null) {
		if (CTestArrayHelper::get($default, 'Default time zone')) {
			$default['Default time zone'] = CDateTimeHelper::getTimeZoneFormat($default['Default time zone']);
		}
		$form->query('button:Reset defaults')->one()->click();
		COverlayDialogElement::find()->waitUntilVisible()->one()->query('button', $action)->one()->click();
		switch ($action) {
			case 'Reset defaults':
				if ($other) {
					// In Other parameters form these fields have no default value, so can be filled with anything.
					$form->checkValue(
						[
							'Group for discovered hosts' => '',
							'User group for database down message' => ''
						]
					);
					$form->fill(
						[
							'Group for discovered hosts' => 'Empty group',
							'User group for database down message' => 'Zabbix administrators'
						]
					);
				}
				$form->submit();
				$this->assertMessage(TEST_GOOD, 'Configuration updated');
				$this->page->refresh();
				$this->page->waitUntilReady();
				$form->invalidate();
				// Check reset form.
				$form->checkValue($default);
				break;

			case 'Cancel':
				$form->checkValue($custom);
				break;
		}
	}

	/**
	 * Test for checking configuration form.
	 *
	 * @param array      $data        data provider
	 * @param boolean    $other       true if Other configuration parameters form
	 * @param boolean    $timeouts    true if Timeouts configuration form
	 */
	public function executeCheckForm($data, $other = false, $timeouts = false) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		if ($expected === TEST_GOOD) {
			$message = 'Configuration updated';
			$values = $data['fields'];
			$db = CTestArrayHelper::get($data, 'db', []);
		}
		else {
			$message = 'Cannot update configuration';
			$values = $this->default_values;
			$db = $this->db_default_values;
		}

		$this->page->login()->open($this->config_link);
		$form = $this->query($this->form_selector)->waitUntilVisible()->asForm()->one();

		// Reset form in case of previous test case.
		$this->resetConfiguration($form, $this->default_values, 'Reset defaults', $other);

		if ($expected === TEST_BAD && $timeouts) {
			$old_hash = CDBHelper::getHash('SELECT * FROM config');
		}

		// Fill form with new data.
		if (CTestArrayHelper::get($data, 'fields.Default time zone')) {
			$data['fields']['Default time zone'] = CDateTimeHelper::getTimeZoneFormat($data['fields']['Default time zone']);
		}
		$form->fill($data['fields']);

		$form->submit();
		$this->page->waitUntilReady();

		$this->assertMessage($expected, $message, CTestArrayHelper::get($data, 'details'));

		// Check saved configuration in frontend.
		$this->page->refresh();
		$form->invalidate();

		// Check trimming symbols in Login attempts field.
		if (CTestArrayHelper::get($values, 'Login attempts') === '3M') {
			$values['Login attempts'] = '3';
		}

		if (CTestArrayHelper::get($values, 'Default time zone')) {
			$values['Default time zone'] = CDateTimeHelper::getTimeZoneFormat($values['Default time zone']);
		}

		if (CTestArrayHelper::get($data, 'trim')) {
			$values = array_map('trim', $values);
		}

		$form->checkValue($values);

		// Check saved configuration in database.
		$config = CDBHelper::getRow('SELECT * FROM config');

		foreach ($db as $key => $value) {
			$this->assertArrayHasKey($key, $config);
			$this->assertEquals($value, $config[$key]);
		}

		if ($expected === TEST_BAD && $timeouts) {
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM config'));
		}
	}
}
