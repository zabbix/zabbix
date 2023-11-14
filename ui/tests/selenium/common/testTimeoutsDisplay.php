<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

class testTimeoutsDisplay extends CWebTest {

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

	const DEFAULT_VALUES = [
		'Zabbix agent' => '3s',
		'Simple check' => '3s',
		'SNMP agent' => '3s',
		'External check' => '3s',
		'Database monitor' => '3s',
		'HTTP agent' => '3s',
		'SSH agent' => '3s',
		'TELNET agent' => '3s',
		'Script' => '3s'
	];

	const SECONDS_VALUES = [
		'Zabbix agent' => '300s',
		'Simple check' => '300s',
		'SNMP agent' => '300s',
		'External check' => '300s',
		'Database monitor' => '300s',
		'HTTP agent' => '300s',
		'SSH agent' => '300s',
		'TELNET agent' => '300s',
		'Script' => '300s'
	];

	const MACROS_VALUES = [
		'Zabbix agent' => '{$MACROS}',
		'Simple check' => '{$MACROS}',
		'SNMP agent' => '{$MACROS}',
		'External check' => '{$MACROS}',
		'Database monitor' => '{$MACROS}',
		'HTTP agent' => '{$MACROS}',
		'SSH agent' => '{$MACROS}',
		'TELNET agent' => '{$MACROS}',
		'Script' => '{$MACROS}'
	];

	/**
	 * @param string $timeout_values  	Reset default, macros values or seconds.
	 * @param string $link    			Link to table with items, items prototype, discovery rules.
	 * @param string $table_selector	Table selector for items, discovery rules, items prototype.
	 */
	public function checkGlobal($timeout_values, $link, $table_selector) {
		// Value for active agent timeout. Global Zabbix agent timeout used in active and passive agents.
		if ($timeout_values === 'macros') {
			$values = self::MACROS_VALUES;
			$new_value = ['Zabbix agent (active)' => '{$MACROS}'];
		}
		elseif ($timeout_values === 'seconds') {
			$values = self::SECONDS_VALUES;
			$new_value = ['Zabbix agent (active)' => '300s'];
		}
		else {
			$values = self::DEFAULT_VALUES;
			$new_value = ['Zabbix agent (active)' => '3s'];
		}

		$this->fillValues($values);

		// Add created Zabbix active timeout to array.
		$values = array_merge($values, $new_value);

		$this->page->open($link)->waitUntilReady();

		// Check timeout value in items one by one, after changes in Administration->Timeouts.
		foreach ($values as $item_name => $timeout) {
			$this->query($table_selector)->asTable()->one()->query('link', $item_name)->one()->click();

			// Discovery rule doesn't have overlay.
			if ($table_selector === 'name:discovery') {
				$this->page->waitUntilReady();
			}
			else {
				COverlayDialogElement::find()->waitUntilReady()->one();
			}

			$form = $this->query('name:itemForm')->asForm()->one();
			$radio = $form->query('id:custom_timeout')->asSegmentedRadio()->one();
			$this->assertEquals('Global', $radio->getText());
			$this->assertTrue($radio->isEnabled());
			$form->checkValue(['Timeout' => $timeout]);

			if ($table_selector === 'name:discovery') {
				$this->page->open($link)->waitUntilReady();
			}
			else {
				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	protected function fillValues($values) {
		$this->page->login()->open('zabbix.php?action=timeouts.edit')->waitUntilReady();
		$form = $this->query('id:timeouts')->waitUntilVisible()->asForm()->one();

		// Check values in form. If they are the same, we can skip and don't update them.
		if ($form->checkValue($values, false) === true) {
			return;
		}

		// Reset if we need to check default values.
		if ($values === self::DEFAULT_VALUES) {
			$form->query('button:Reset defaults')->one()->click();
			COverlayDialogElement::find()->waitUntilVisible()->one()->query('button:Reset defaults')->one()->click();
		}
		else {
			$form->fill($values);
		}

		// Submit form and check that values updated correctly.
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->page->refresh();
		$this->page->waitUntilReady();
		$form->invalidate();

		// Check form.
		$form->checkValue($values);
	}
}
