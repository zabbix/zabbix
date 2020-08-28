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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralOtherParams extends CWebTest {

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

	private $default = [
		'Refresh unsupported items' => '10m',
		'Group for discovered hosts' => 'Discovered hosts',
		'Default host inventory mode' => 'Disabled',
		'User group for database down message' => 'Zabbix administrators',
		'Log unmatched SNMP traps' => true,
		// Authorization.
		'Login attempts' => 5,
		'Login blocking interval' => '30s',
		// Security.
		'Validate URI schemes' => true,
		'Valid URI schemes' => 'http,https,ftp,file,mailto,tel,ssh',
		'X-Frame-Options HTTP header' => 'SAMEORIGIN',
		'Use iframe sandboxing' => true,
		'Iframe sandboxing exceptions' => '',
		// Communication with Zabbix server.
		'Network timeout' => '3s',
		'Connection timeout' => '3s',
		'Network timeout for media type test' => '65s',
		'Network timeout for script execution' => '60s',
		'Network timeout for item test' => '60s'
	];

	private $db_default = [
		'Refresh unsupported items' => '10m',
		'Group for discovered hosts' => 'Discovered hosts',
		'Default host inventory mode' => 'Disabled',
		'User group for database down message' => 'Selenium user group in configuration',
		'Log unmatched SNMP traps' => true,
		// Authorization.
		'Login attempts' => 5,
		'Login blocking interval' => '30s',
		// Security.
		'Validate URI schemes' => true,
		'Valid URI schemes' => 'http,https,ftp,file,mailto,tel,ssh',
		'X-Frame-Options HTTP header' => 'SAMEORIGIN',
		'Use iframe sandboxing' => true,
		'Iframe sandboxing exceptions' => '',
		// Communication with Zabbix server.
		'Network timeout' => '3s',
		'Connection timeout' => '3s',
		'Network timeout for media type test' => '65s',
		'Network timeout for script execution' => '60s',
		'Network timeout for item test' => '60s'
	];

	private $custom = [
		'Refresh unsupported items' => '99m',
		'Group for discovered hosts' => 'Empty group',
		'Default host inventory mode' => 'Automatic',
		'User group for database down message' => 'Selenium user group in configuration',
		'Log unmatched SNMP traps' => false,
		// Authorization.
		'Login attempts' => 13,
		'Login blocking interval' => '52s',
		// Security.
		'Validate URI schemes' => true,
		'Valid URI schemes' => 'custom_scheme',
		'X-Frame-Options HTTP header' => 'SOME_NEW_VALUE',
		'Use iframe sandboxing' => true,
		'Iframe sandboxing exceptions' => 'some-new-flag',
		// Communication with Zabbix server.
		'Network timeout' => '7s',
		'Connection timeout' => '4s',
		'Network timeout for media type test' => '91s',
		'Network timeout for script execution' => '46s',
		'Network timeout for item test' => '76s'
	];

	/**
	 * Test for checking form layout.
	 */
	public function testFormAdministrationGeneralOtherParams_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=miscconfig.edit');
		$this->assertPageTitle('Other configuration parameters');
		$this->assertPageHeader('Other configuration parameters');
		$form = $this->query('name:otherForm')->waitUntilPresent()->asForm()->one();

		foreach (['Authorization', 'Security', 'Communication with Zabbix server'] as $header) {
			$this->assertTrue($this->query('xpath://h4[text()="'.$header.'"]')->one()->isVisible());
		}

		$limits = [
			'refresh_unsupported' => 32,
			'login_attempts' => 2,
			'login_block' => 32,
			'uri_valid_schemes' => 255,
			'x_frame_options' => 255,
			'iframe_sandboxing_exceptions' => 255,
			'socket_timeout' => 32,
			'connect_timeout' => 32,
			'media_type_test_timeout' => 32,
			'script_timeout' => 32,
			'item_test_timeout' => 32
		];
		foreach ($limits as $id => $limit) {
			$this->assertEquals($limit, $this->query('id', $id)->one()->getAttribute('maxlength'));
		}

		foreach ([true, false] as $status) {
			$checkboxes = [
				'snmptrap_logging',
				'validate_uri_schemes',
				'iframe_sandboxing_enabled'
			];
			foreach ($checkboxes as $checkbox) {
				$this->assertTrue($this->query('id', $checkbox)->one()->isEnabled());
				$form->getField('id:'.$checkbox)->fill($status);
			}

			foreach (['uri_valid_schemes','iframe_sandboxing_exceptions'] as $input) {
				$this->assertTrue($this->query('id', $input)->one()->isEnabled($status));
			}
		}

		foreach (['Update', 'Reset defaults'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled());
		}
	}

	/**
	 * Test for checking 'Reset defaults' button.
	 */
	public function testFormAdministrationGeneralOtherParams_ResetButton() {
		$this->page->login()->open('zabbix.php?action=miscconfig.edit');
		$form = $this->query('name:otherForm')->waitUntilPresent()->asForm()->one();
		// Reset form in case of some previous scenario.
		$this->resetConfiguration($form, $this->default, 'Reset defaults');
		$default_sql = CDBHelper::getRow('SELECT * FROM config');

		// Reset form after customly filled data and check that values are reset to default or reset is cancelled.
		foreach (['Reset defaults', 'Cancel'] as $action) {
			// Fill form with custom data.
			$form->fill($this->custom);
			$form->submit();
			$this->assertMessage(TEST_GOOD, 'Configuration updated');
			$custom_sql = CDBHelper::getRow('SELECT * FROM config');
			// Check custom data in form.
			$this->page->refresh();
			$this->page->waitUntilReady();
			$form->invalidate();
			$form->checkValue($this->custom);
			$this->resetConfiguration($form, $this->default, $action, $this->custom);
			$sql = ($action === 'Reset defaults') ? $default_sql : $custom_sql;
			$this->assertEquals($sql, CDBHelper::getRow('SELECT * FROM config'));
		}
	}

	/**
	 * Function for configuration resetting.
	 *
	 * @param element  $form      Settings configuration form
	 * @param array    $default   Default form values
	 * @param string   $action    Reset defaults or Cancel
	 * @param array    $custom    Custom values for filling into settings form
	 */
	private function resetConfiguration($form, $default, $action, $custom = null) {
		$form->query('button:Reset defaults')->one()->click();
		COverlayDialogElement::find()->waitUntilPresent()->one()->query('button', $action)->one()->click();
		switch ($action) {
			case 'Reset defaults':
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
}

