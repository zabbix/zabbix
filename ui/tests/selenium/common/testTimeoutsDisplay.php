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

	const GLOBAL_DEFAULT = [
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

	const GLOBAL_CUSTOM = [
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

	const GLOBAL_MACROS = [
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

	const PROXY_MACROS = [
		'Zabbix agent' => '{$PROXY}',
		'Simple check' => '{$PROXY}',
		'SNMP agent' => '{$PROXY}',
		'External check' => '{$PROXY}',
		'Database monitor' => '{$PROXY}',
		'HTTP agent' => '{$PROXY}',
		'SSH agent' => '{$PROXY}',
		'TELNET agent' => '{$PROXY}',
		'Script' => '{$PROXY}'
	];

	const PROXY_CUSTOM = [
		'Zabbix agent' => '255s',
		'Simple check' => '255s',
		'SNMP agent' => '255s',
		'External check' => '255s',
		'Database monitor' => '255s',
		'HTTP agent' => '255s',
		'SSH agent' => '255s',
		'TELNET agent' => '255s',
		'Script' => '255s'
	];

	/**
	 * Change global timeouts values and check them.
	 *
	 * @param string $timeout_values  	Reset default, macros values or custom values.
	 * @param string $link    			Link to table with items, items prototype, discovery rules.
	 * @param string $button_name		Button name to check not linked items.
	 * @param string $proxy				Fill proxy timeouts.
	 */
	public function checkGlobal($timeout_values, $link, $button_name, $proxy = false, $linked = false) {
		// Global Zabbix agent timeout used in active and passive agents. Add this value to array.
		switch ($timeout_values) {
			case 'proxy_macros':
				$values = self::PROXY_MACROS;
				$new_value = ['Zabbix agent (active)' => '{$PROXY}'];
				break;

			case 'proxy_custom':
				$values = self::PROXY_CUSTOM;
				$new_value = ['Zabbix agent (active)' => '255s'];
				break;

			case 'global_default':
				$values = self::GLOBAL_DEFAULT;
				$new_value = ['Zabbix agent (active)' => '3s'];
				break;

			case 'global_macros':
				$values = self::GLOBAL_MACROS;
				$new_value = ['Zabbix agent (active)' => '{$MACROS}'];
				break;

			case 'global_custom':
				$values = self::GLOBAL_CUSTOM;
				$new_value = ['Zabbix agent (active)' => '300s'];
				break;
		}

		// Change timeout values for proxy or global timeouts.
		if ($proxy) {
			$this->fillProxyTimeouts($values);
		}
		else {
			$this->fillGlobalTimeouts($values);
		}

		// Add Zabbix agent active timeout to array.
		$values = array_merge($values, $new_value);

		// Navigate to page for timeouts checking.
		$this->page->open($link)->waitUntilReady();

		if ($linked) {
			// Check linked item, item prototypes, discovery.
			$this->checkLinked($values, $link, $button_name);
		}
		else {
			// Check not linked items, item prototypes, discovery.
			$this->checkSimple($values, $link, $button_name);
		}
	}

	/**
	 * Fill timeouts.
	 *
	 * @param array $values		Timeouts values to fill.
	 */
	protected function fillGlobalTimeouts($values) {
		$this->page->login()->open('zabbix.php?action=timeouts.edit')->waitUntilReady();
		$form = $this->query('id:timeouts')->waitUntilVisible()->asForm()->one();

		// Check values in form. If they are the same, we can skip and don't update them.
		if ($form->checkValue($values, false) === true) {
			return;
		}

		// Reset if we need to check default values.
		if ($values === self::GLOBAL_DEFAULT) {
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

	protected function fillProxyTimeouts($values) {
		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->query('name:proxy_list')->asTable()->one()->query('link:Proxy assigned to host')->one()->click();
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$form->selectTab('Timeouts');
		$form->invalidate();

		// Check values in form. If they are the same, we can skip and don't update them.
		if ($form->checkValue($values, false) === true) {
			COverlayDialogElement::find()->one()->close();
			return;
		}

		// If proxy use Global timeouts values - change parameter to global and Reset global values ot timeouts page.
		if ($values === self::GLOBAL_DEFAULT) {
			$form->fill(['Timeouts for item types' => 'Global']);
			$form->submit();
			COverlayDialogElement::ensureNotPresent();
			$this->page->waitUntilReady();
			$this->fillGlobalTimeouts($values);
		}
		else {
			$form->fill(['Timeouts for item types' => 'Override']);
			$form->fill($values);
			$form->submit();
			COverlayDialogElement::ensureNotPresent();
			$this->page->waitUntilReady();
		}
	}

	protected function checkSimple($values, $link, $button_name) {
		// Check timeout value in items one by one, after changes in Administration->Timeouts or Proxy->timeouts.
		foreach ($values as $item_type => $timeout) {
			$this->query('button', $button_name)->one()->click();

			// Discovery rule doesn't have overlay.
			if ($button_name === 'Create discovery rule') {
				$this->page->waitUntilReady();
			}
			else {
				COverlayDialogElement::find()->waitUntilReady()->one();
			}

			$form = $this->query('name:itemForm')->asForm()->one();

			// Timeout field for SNMP agent appears after adding walk[1] to SNMP OID field.
			$fill_form_values = ($item_type === 'SNMP agent')
				? ['Type' => $item_type, 'SNMP OID' => 'walk[1]']
				: ['Type' => $item_type];

			$form->fill($fill_form_values);
			$radio = $form->query('id:custom_timeout')->asSegmentedRadio()->one();
			$this->assertEquals('Global', $radio->getText());
			$this->assertTrue($radio->isEnabled());
			$form->checkValue(['Timeout' => $timeout]);

			if ($button_name === 'Create discovery rule') {
				$this->page->open($link)->waitUntilReady();
			}
			else {
				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	protected function checkLinked($values, $link, $table_selector, $discovery = false) {
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
			$this->assertFalse($radio->isEnabled());
			$form->checkValue(['Timeout' => $timeout]);

			if ($table_selector === 'name:discovery') {
				$this->page->open($link)->waitUntilReady();
			}
			else {
				COverlayDialogElement::find()->one()->close();
			}
		}
	}
}
