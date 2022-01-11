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

require_once 'vendor/autoload.php';

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
	public $default;
	public $db_default;
	public $custom;

	/**
	 * Test for checking form update without changing any data.
	 */
	public function executeSimpleUpdate() {
		$config = CDBHelper::getRow('SELECT * FROM config ORDER BY configid');
		$this->page->login()->open($this->config_link);
		$form = $this->query($this->form_selector)->waitUntilReady()->asForm()->one();
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
	}

	/**
	 * Test for checking 'Reset defaults' button.
	 *
	 * @param boolean    $other   If it is Other configuration parameters form
	 */
	public function executeResetButtonTest($other = false) {
		$this->page->login()->open($this->config_link);
		$form = $this->query($this->form_selector)->waitUntilReady()->asForm()->one();
		// Reset form in case of some previous scenario.
		$this->resetConfiguration($form, $this->default, 'Reset defaults', $other);
		$default_config = CDBHelper::getRow('SELECT * FROM config');

		// Reset form after customly filled data and check that values are reset to default or reset is cancelled.
		foreach (['Cancel', 'Reset defaults'] as $action) {
			// Fill form with custom data.
			$form->fill($this->custom);
			$form->submit();
			$this->assertMessage(TEST_GOOD, 'Configuration updated');
			$custom_config = CDBHelper::getRow('SELECT * FROM config');
			// Check custom data in form.
			$this->page->refresh()->waitUntilReady();
			$form->invalidate();
			$form->checkValue($this->custom);
			$this->resetConfiguration($form, $this->default, $action, $other, $this->custom);
			$config = ($action === 'Reset defaults') ? $default_config : $custom_config;
			$this->assertEquals($config, CDBHelper::getRow('SELECT * FROM config'));
		}
	}

	/**
	 * Function for configuration resetting.
	 *
	 * @param element  $form      Settings configuration form
	 * @param array    $default   Default form values
	 * @param string   $action    Reset defaults or Cancel
	 * @param boolean  $other     Is this Other parameters form or not
	 * @param array    $custom    Custom values for filling into settings form
	 */
	public function resetConfiguration($form, $default, $action, $other = false, $custom = null) {
		if (CTestArrayHelper::get($default, 'Default time zone')) {
			$default['Default time zone'] = CDateTimeHelper::getTimeZoneFormat($default['Default time zone']);
		}
		$form->query('button:Reset defaults')->one()->click();
		COverlayDialogElement::find()->waitUntilReady()->one()->query('button', $action)->one()->click();
		switch ($action) {
			case 'Reset defaults':
				if ($other) {
					// In Other parameters form these fields have no default value, so can be filled with anything.
					$form->checkValue(
						[
							'Group for discovered hosts' => [],
							'User group for database down message' => []
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
	 * @param array      $data    Data provider
	 * @param boolean    $other   If it is Other configuration parameters form
	 */
	public function executeCheckForm($data, $other = false) {
		$this->page->login()->open($this->config_link);
		$form = $this->query($this->form_selector)->waitUntilReady()->asForm()->one();
		// Reset form in case of previous test case.
		$this->resetConfiguration($form, $this->default, 'Reset defaults', $other);
		// Fill form with new data.
		if (CTestArrayHelper::get($data, 'fields.Default time zone')) {
			$data['fields']['Default time zone'] = CDateTimeHelper::getTimeZoneFormat($data['fields']['Default time zone']);
		}
		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();
		$message = (CTestArrayHelper::get($data, 'expected') === TEST_GOOD)
			? 'Configuration updated'
			: 'Cannot update configuration';
		$this->assertMessage($data['expected'], $message, CTestArrayHelper::get($data, 'details'));
		// Check saved configuration in frontend.
		$this->page->refresh();
		$form->invalidate();
		// Check trimming symbols in Login attempts field.
		if (CTestArrayHelper::get($data['fields'], 'Login attempts') === '3M') {
			$data['fields']['Login attempts'] = '3';
		}
		$values = (CTestArrayHelper::get($data, 'expected')) === TEST_GOOD ? $data['fields'] : $this->default;
		if (CTestArrayHelper::get($data, 'expected') === TEST_BAD && CTestArrayHelper::get($values, 'Default time zone')) {
			$values['Default time zone'] = CDateTimeHelper::getTimeZoneFormat($values['Default time zone']);
		}
		$form->checkValue($values);
		// Check saved configuration in database.
		$config = CDBHelper::getRow('SELECT * FROM config');
		$db = (CTestArrayHelper::get($data, 'expected') === TEST_GOOD)
			? CTestArrayHelper::get($data, 'db', [])
			: $this->db_default;
		foreach ($db as $key => $value) {
			$this->assertArrayHasKey($key, $config);
			$this->assertEquals($value, $config[$key]);
		}
	}
}
