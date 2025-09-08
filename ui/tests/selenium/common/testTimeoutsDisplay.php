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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';

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
		'Zabbix agent' => '111s',
		'Simple check' => '222s',
		'SNMP agent' => '333s',
		'External check' => '444s',
		'Database monitor' => '555s',
		'HTTP agent' => '556s',
		'SSH agent' => '557s',
		'TELNET agent' => '558s',
		'Script' => '559s'
	];

	const GLOBAL_MACROS = [
		'Zabbix agent' => '{$MAC1}',
		'Simple check' => '{$MAC2}',
		'SNMP agent' => '{$MAC3}',
		'External check' => '{$MAC4}',
		'Database monitor' => '{$MAC5}',
		'HTTP agent' => '{$MAC6}',
		'SSH agent' => '{$MAC7}',
		'TELNET agent' => '{$MAC8}',
		'Script' => '{$MAC9}'
	];

	const PROXY_MACROS = [
		'Zabbix agent' => '{$PRO1}',
		'Simple check' => '{$PRO2}',
		'SNMP agent' => '{$PRO3}',
		'External check' => '{$PRO4}',
		'Database monitor' => '{$PRO5}',
		'HTTP agent' => '{$PRO6}',
		'SSH agent' => '{$PRO7}',
		'TELNET agent' => '{$PRO8}',
		'Script' => '{$PRO9}'
	];

	const PROXY_CUSTOM = [
		'Zabbix agent' => '123s',
		'Simple check' => '234s',
		'SNMP agent' => '345s',
		'External check' => '456s',
		'Database monitor' => '567s',
		'HTTP agent' => '568s',
		'SSH agent' => '569s',
		'TELNET agent' => '570s',
		'Script' => '571s'
	];

	/**
	 * Change global or proxy timeouts values and check them.
	 *
	 * @param string  $timeout_values    reset default, macros values or custom values with/without proxy
	 * @param string  $link    			 link to table with items, items prototype, LLD
	 * @param string  $activity			 button name or table selector
	 * @param boolean $proxy			 true if proxy timeouts, false if global
	 * @param boolean $linked			 true for timeouts from linked template, false if not
	 */
	public function checkGlobal($timeout_values, $link, $activity, $proxy = false, $linked = false) {
		// Global Zabbix agent timeout used in active and passive agents. Add this value to array.
		switch ($timeout_values) {
			case 'proxy_macros':
				$values = self::PROXY_MACROS;
				$new_value = ['Zabbix agent (active)' => '{$PRO1}'];
				break;

			case 'proxy_custom':
				$values = self::PROXY_CUSTOM;
				$new_value = ['Zabbix agent (active)' => '123s'];
				break;

			case 'global_default':
				$values = self::GLOBAL_DEFAULT;
				$new_value = ['Zabbix agent (active)' => '3s'];
				break;

			case 'global_macros':
				$values = self::GLOBAL_MACROS;
				$new_value = ['Zabbix agent (active)' => '{$MAC1}'];
				break;

			case 'global_custom':
				$values = self::GLOBAL_CUSTOM;
				$new_value = ['Zabbix agent (active)' => '111s'];
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
			$this->checkLinked($values, $link, $activity);
		}
		else {
			// Check not linked items, item prototypes, discovery.
			$this->checkSimple($values, $link, $activity);
		}
	}

	/**
	 * Change global timeouts.
	 *
	 * @param array $values    timeouts values to fill
	 */
	protected function fillGlobalTimeouts($values) {
		$this->page->login()->open('zabbix.php?action=timeouts.edit')->waitUntilReady();
		$form = $this->query('id:timeouts-form')->waitUntilVisible()->asForm()->one();

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

	/**
	 * Change proxy timeouts.
	 *
	 * @param array $values    timeouts values to fill
	 */
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

		// If proxy uses Global timeouts values - change parameter to global and Reset global values to timeouts page.
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

	/**
	 * Check timeouts in items/item prototypes/LLD after timeout changes.
	 *
	 * @param array  $values		 values to check
	 * @param string $link			 link to page where to check timeouts
	 * @param string $button_name    button name to add new item, item prototype, LLD
	 */
	protected function checkSimple($values, $link, $button_name) {
		// Check timeout value in items one by one, after changes in Administration->Timeouts or Proxy->timeouts.
		foreach ($values as $item_type => $timeout) {
			$this->query('button', $button_name)->one()->click();

			// Discovery rule doesn't have overlay.
			if ($button_name === 'Create discovery rule') {
				$this->page->waitUntilReady();
				$selector = 'id:inherited_timeout';
			}
			else {
				COverlayDialogElement::find()->waitUntilReady()->one();
				$selector = 'id:inherited_timeout';
			}

			$form = $this->query('name:itemForm')->asForm()->one();

			// Timeout field for SNMP agent appears after adding walk[1] to SNMP OID field.
			$fill_form_values = ($item_type === 'SNMP agent')
				? ['Type' => $item_type, 'SNMP OID' => 'walk[1]']
				: ['Type' => $item_type];

			$form->fill($fill_form_values);
			$this->checkItemsTimeoutField($form, $selector, $timeout);

			if ($button_name === 'Create discovery rule') {
				$this->page->open($link)->waitUntilReady();
			}
			else {
				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	/**
	 * Check timeouts in templated items/item prototypes/LLD after timeout changes.
	 *
	 * @param array  $values			values to check
	 * @param string $link				link to page where to check timeouts
	 * @param string $table_selector    table selector for items, item prototypes and LLD
	 */
	protected function checkLinked($values, $link, $table_selector) {
		// If we need to check linked item prototype timeouts, we should first navigate through discovery rule.
		if ($table_selector === 'name:itemprototype') {
			$this->query('name:discovery')->asTable()->one()->findRow('Name', 'Template for linking: Zabbix agent')
					->getColumn('Items')->query('link:Item prototypes')->one()->click();
			$this->page->waitUntilReady();
		}

		// Check timeout value in items one by one, after changes in Administration->Timeouts.
		foreach ($values as $item_name => $timeout) {
			$this->query($table_selector)->asTable()->one()->query('link', $item_name)->one()->click();

			// Discovery rule doesn't have overlay.
			if ($table_selector === 'name:discovery') {
				$this->page->waitUntilReady();
				$selector = 'id:inherited_timeout';
			}
			else {
				COverlayDialogElement::find()->waitUntilReady()->one();
				$selector = 'id:inherited_timeout';
			}

			$form = $this->query('name:itemForm')->asForm()->one();
			$this->checkItemsTimeoutField($form, $selector, $timeout, false);

			if ($table_selector === 'name:discovery') {
				$this->page->open($link)->waitUntilReady();
			}
			else {
				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	/**
	 * Check timeout radio button and input field for items, item prototypes and LLD.
	 *
	 * @param CFormElement $form        items, items prototype, discovery form
	 * @param string       $selector    timeout input field selector
	 * @param string       $value       timeout input field value
	 * @param boolean      $status      true if timeout radio button enabled, false if disabled
	 */
	protected function checkItemsTimeoutField($form, $selector, $value, $status = true) {
		$radio = $form->query('id:custom_timeout')->asSegmentedRadio()->one();
		$this->assertEquals('Global', $radio->getText());
		$this->assertTrue($radio->isEnabled($status));
		$this->assertTrue($form->getField($selector)->isVisible());
		$this->assertFalse($form->getField($selector)->isEnabled());
		$form->checkValue([$selector => $value]);
	}
}
