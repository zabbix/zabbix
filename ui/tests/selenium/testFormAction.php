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


require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

define('ACTION_GOOD', 0);
define('ACTION_BAD', 1);

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup actions
 */
class testFormAction extends CLegacyWebTest {

	private $event_sources = [
		EVENT_SOURCE_TRIGGERS => 'Trigger actions',
		EVENT_SOURCE_DISCOVERY => 'Discovery actions',
		EVENT_SOURCE_AUTOREGISTRATION => 'Autoregistration actions',
		EVENT_SOURCE_INTERNAL => 'Internal actions'
	];

	public static function layout() {
		return [
			[
				[
					'eventsource' => 'Triggers',
					'recovery_msg' => true,
					'acknowledge_msg' => true
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Send message'
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Send message',
					'new_operation_opmessage_custom_msg' => 'unchecked'
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Send message',
					'add_opcondition' => true
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Remote command',
					'add_opcondition' => true
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Custom script'
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'IPMI'
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'SSH'
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'SSH',
					'new_operation_opcommand_authtype' => 'Public key'
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Telnet'
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Global script'
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Custom script',
					'add_opcondition' => true
				]
			],
			[
				['eventsource' => 'Triggers', 'evaltype' => 'And']
			],
			[
				['eventsource' => 'Triggers', 'evaltype' => 'Or']
			],
			[
				['eventsource' => 'Triggers', 'new_condition_conditiontype' => 'Application']
			],
			[
				['eventsource' => 'Triggers', 'new_condition_conditiontype' => 'Host group']
			],
			[
				['eventsource' => 'Triggers', 'new_condition_conditiontype' => 'Template']
			],
			[
				['eventsource' => 'Triggers', 'new_condition_conditiontype' => 'Host']
			],
			[
				['eventsource' => 'Triggers', 'new_condition_conditiontype' => 'Trigger']
			],
			[
				['eventsource' => 'Triggers', 'new_condition_conditiontype' => 'Trigger name']
			],
			[
				['eventsource' => 'Triggers', 'new_condition_conditiontype' => 'Trigger severity']
			],
			[
				['eventsource' => 'Triggers', 'new_condition_conditiontype' => 'Time period']
			],
			[
				['eventsource' => 'Triggers', 'new_condition_conditiontype' => 'Problem is suppressed']
			],
			[
				['eventsource' => 'Discovery']
			],
			[
				['eventsource' => 'Discovery', 'new_condition_conditiontype' => 'Host IP']
			],
			[
				['eventsource' => 'Discovery', 'new_condition_conditiontype' => 'Service type']
			],
			[
				['eventsource' => 'Discovery', 'new_condition_conditiontype' => 'Service port']
			],
			[
				['eventsource' => 'Discovery', 'new_condition_conditiontype' => 'Discovery rule']
			],
			[
				['eventsource' => 'Discovery', 'new_condition_conditiontype' => 'Discovery check']
			],
			[
				['eventsource' => 'Discovery', 'new_condition_conditiontype' => 'Discovery object']
			],
			[
				['eventsource' => 'Discovery', 'new_condition_conditiontype' => 'Discovery status']
			],
			[
				['eventsource' => 'Discovery', 'new_condition_conditiontype' => 'Uptime/Downtime']
			],
			[
				['eventsource' => 'Discovery', 'new_condition_conditiontype' => 'Received value']
			],
			[
				['eventsource' => 'Discovery', 'new_condition_conditiontype' => 'Proxy']
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Send message'
				]
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Send message',
					'new_operation_opmessage_custom_msg' => 'unchecked'
				]
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Custom script'
				]
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'IPMI'
				]
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'SSH'
				]
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'SSH',
					'new_operation_opcommand_authtype' => 'Public key'
				]
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Telnet'
				]
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Global script'
				]
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Custom script'
				]
			],
			[
				['eventsource' => 'Discovery', 'new_operation_operationtype' => 'Add host']
			],
			[
				['eventsource' => 'Discovery', 'new_operation_operationtype' => 'Remove host']
			],
			[
				['eventsource' => 'Discovery', 'new_operation_operationtype' => 'Add to host group']
			],
			[
				['eventsource' => 'Discovery', 'new_operation_operationtype' => 'Remove from host group']
			],
			[
				['eventsource' => 'Discovery', 'new_operation_operationtype' => 'Link to template']
			],
			[
				['eventsource' => 'Discovery', 'new_operation_operationtype' => 'Unlink from template']
			],
			[
				['eventsource' => 'Discovery', 'new_operation_operationtype' => 'Enable host']
			],
			[
				['eventsource' => 'Discovery', 'new_operation_operationtype' => 'Disable host']
			],
			[
				['eventsource' => 'Autoregistration']
			],
			[
				['eventsource' => 'Autoregistration', 'new_condition_conditiontype' => 'Host name']
			],
			[
				['eventsource' => 'Autoregistration', 'new_condition_conditiontype' => 'Proxy']
			],
			[
				['eventsource' => 'Autoregistration', 'new_condition_conditiontype' => 'Host metadata']
			],
			[
				[
					'eventsource' => 'Autoregistration',
					'new_operation_operationtype' => 'Send message'
				]
			],
			[
				[
					'eventsource' => 'Autoregistration',
					'new_operation_operationtype' => 'Send message',
					'new_operation_opmessage_custom_msg' => 'unchecked'
				]
			],
			[
				[
					'eventsource' => 'Autoregistration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Custom script'
				]
			],
			[
				[
					'eventsource' => 'Autoregistration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'IPMI'
				]
			],
			[
				[
					'eventsource' => 'Autoregistration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'SSH'
				]
			],
			[
				[
					'eventsource' => 'Autoregistration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'SSH',
					'new_operation_opcommand_authtype' => 'Public key'
				]
			],
			[
				[
					'eventsource' => 'Autoregistration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Telnet'
				]
			],
			[
				[
					'eventsource' => 'Autoregistration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Global script'
				]
			],
			[
				['eventsource' => 'Autoregistration', 'new_operation_operationtype' => 'Add host']
			],
			[
				['eventsource' => 'Autoregistration', 'new_operation_operationtype' => 'Add to host group']
			],
			[
				['eventsource' => 'Autoregistration', 'new_operation_operationtype' => 'Link to template']
			],
			[
				['eventsource' => 'Autoregistration', 'new_operation_operationtype' => 'Disable host']
			],
			[
				['eventsource' => 'Internal']
			],
			[
				['eventsource' => 'Internal', 'recovery_msg' => true]
			],
			[
				['eventsource' => 'Internal', 'new_condition_conditiontype' => 'Application']
			],
			[
				['eventsource' => 'Internal', 'new_condition_conditiontype' => 'Event type']
			],
			[
				['eventsource' => 'Internal', 'new_condition_conditiontype' => 'Host group']
			],
			[
				['eventsource' => 'Internal', 'new_condition_conditiontype' => 'Template']
			],
			[
				['eventsource' => 'Internal', 'new_condition_conditiontype' => 'Host']
			],
			[
				['eventsource' => 'Internal', 'new_operation_operationtype' => 'Send message']
			],
			[
				[
					'eventsource' => 'Internal',
					'new_operation_operationtype' => 'Send message',
					'new_operation_opmessage_custom_msg' => 'unchecked'
				]
			]
		];
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormAction_CheckLayout($data) {
		$eventsource = $data['eventsource'];
		switch ($eventsource) {
			case 'Triggers':
				$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_TRIGGERS.'&form=Create+action');
				break;
			case 'Discovery':
				$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_DISCOVERY.'&form=Create+action');
				break;
			case 'Autoregistration':
				$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_AUTOREGISTRATION.'&form=Create+action');
				break;
			case 'Internal';
				$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_INTERNAL.'&form=Create+action');
				break;
			default:
				$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_TRIGGERS.'&form=Create+action');
				break;
		}

		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestTextPresent(['Action', 'Operations']);

		$this->zbxTestTextPresent('Name');
		$this->zbxTestAssertVisibleId('name');
		$this->zbxTestAssertAttribute('//input[@id="name"]', 'maxlength', 255);
		$this->zbxTestAssertAttribute('//input[@id="name"]', 'size', 20);
		$this->zbxTestAssertAttribute('//input[@id="name"]', 'autofocus');

		$this->zbxTestTextPresent('Enabled');
		$this->zbxTestAssertElementPresentId('status');
		$this->zbxTestAssertElementPresentXpath('//input[@type="checkbox" and @id="status"]');
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));

		if (array_key_exists('evaltype', $data)) {
			// Open Condition overlay dialog and fill first condition.
			$this->zbxTestClickXpath('//button[text()="Add" and contains(@onclick, "popup.condition.actions")]');
			$this->zbxTestLaunchOverlayDialog('New condition');
			$this->zbxTestInputTypeByXpath('//textarea[@id="value"]', 'TEST1');
			$this->zbxTestClickXpathWait('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('conditions_0'));
			// Open Condition overlay dialog again and fill second condition.
			$this->zbxTestClickXpath('//button[text()="Add" and contains(@onclick, "popup.condition.actions")]');
			$this->zbxTestLaunchOverlayDialog('New condition');
			$this->zbxTestInputTypeByXpath('//textarea[@id="value"]', 'TEST2');
			$this->zbxTestClickXpathWait('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');
			// Wait until overlay is closed and value is added, so that Type of calculation dropdown is clickable.
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('evaltype'));
			$this->zbxTestDropdownSelectWait('evaltype', $data['evaltype']);
			$evaltype = $data['evaltype'];
		}

		if ($eventsource == 'Triggers' && array_key_exists('evaltype', $data)) {
			$this->zbxTestTextPresent('Type of calculation');
			$this->zbxTestAssertElementPresentId('evaltype');
			$this->zbxTestDropdownHasOptions('evaltype', [
					'And/Or',
					'And',
					'Or',
					'Custom expression'
			]);
			$this->zbxTestAssertAttribute('//*[@id=\'evaltype\']/option[text()=\''.$evaltype.'\']', 'selected');
			switch ($evaltype) {
				case 'And/Or':
				case 'And':
					$this->zbxTestTextPresent('A and B');
					break;
				default:
					$this->zbxTestTextPresent('A or B');
					break;
			}
		}
		else {
			$this->zbxTestTextNotVisible('Type of calculation');
			$this->zbxTestAssertNotVisibleId('evaltype');
		}

		$this->zbxTestTextPresent([
				'Conditions',
				'Label', 'Name', 'Action'
		]);

		if ($eventsource == 'Triggers' && array_key_exists('evaltype', $data)) {
			$this->zbxTestAssertElementText('//tr[@id="conditions_0"]/td[2]', 'Trigger name contains TEST1');
			$this->zbxTestAssertElementText('//tr[@id="conditions_1"]/td[2]', 'Trigger name contains TEST2');
			$this->zbxTestAssertElementPresentXpath('//button[@name="remove" and @onclick="javascript: removeCondition(0);"]');
			$this->zbxTestAssertElementPresentXpath('//button[@name="remove" and @onclick="javascript: removeCondition(1);"]');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//tr[@id="conditions_0"]');
			$this->zbxTestAssertElementNotPresentXpath('//button[@name="remove" and @onclick="javascript: removeCondition(0);"]');
			$this->zbxTestAssertElementNotPresentXpath('//button[@name="remove" and @onclick="javascript: removeCondition(1);"]');
		}

		// Open Condition overlay dialog.
		$this->zbxTestClickXpath('//button[text()="Add" and contains(@onclick, "popup.condition.actions")]');
		$this->zbxTestLaunchOverlayDialog('New condition');
		COverlayDialogElement::find()->one()->waitUntilReady();

		if (isset($data['new_condition_conditiontype'])) {
			$this->zbxTestDropdownSelectWait('condition_type', $data['new_condition_conditiontype']);
			COverlayDialogElement::find()->one()->waitUntilReady();
		}
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('condition_type'));
		$new_condition_conditiontype = $this->zbxTestGetSelectedLabel('condition_type');

		switch ($eventsource) {
			case 'Triggers':
				$this->zbxTestDropdownHasOptions('condition_type', [
						'Application',
						'Host group',
						'Template',
						'Host',
						'Trigger',
						'Trigger name',
						'Trigger severity',
						'Time period',
						'Problem is suppressed'
				]);
				break;
			case 'Discovery':
				$this->zbxTestDropdownHasOptions('condition_type', [
						'Host IP',
						'Service type',
						'Service port',
						'Discovery rule',
						'Discovery check',
						'Discovery object',
						'Discovery status',
						'Uptime/Downtime',
						'Received value',
						'Proxy'
				]);
				break;
			case 'Autoregistration':
				$this->zbxTestDropdownHasOptions('condition_type', [
						'Host name',
						'Proxy',
						'Host metadata'
				]);
				break;
			case 'Internal':
				$this->zbxTestDropdownHasOptions('condition_type', [
						'Application',
						'Event type',
						'Host group',
						'Template',
						'Host'
				]);
				break;
		}

		if (isset($data['new_condition_conditiontype'])) {
			$this->zbxTestDropdownAssertSelected('condition_type', $new_condition_conditiontype);
		}

		$this->zbxTestAssertElementPresentId('operator');

		switch ($new_condition_conditiontype) {
			case 'Application':
				$this->zbxTestTextPresent([
					'equals',
					'contains',
					'does not contain'
				]);
				break;
			case 'Host group':
			case 'Template':
			case 'Host':
			case 'Trigger':
			case 'Host IP':
			case 'Service type':
			case 'Discovery rule':
			case 'Discovery check':
			case 'Proxy':
				$this->zbxTestTextPresent([
					'equals',
					'does not equal'
				]);
				break;
			case 'Trigger name':
			case 'Host name':
			case 'Host metadata':
				$this->zbxTestTextPresent([
					'contains',
					'does not contain'
				]);
				break;
			case 'Trigger severity':
				$this->zbxTestTextPresent([
					'equals',
						'does not equal',
						'is greater than or equals',
						'is less than or equals'
				]);
				break;
			case 'Trigger value':
			case 'Discovery object':
			case 'Discovery status':
			case 'Event type':
				$this->zbxTestIsElementPresent('//td[@colspan="1" and text()="equals"]');
				break;
			case 'Time period':
				$this->zbxTestTextPresent([
					'in',
					'not in'
				]);
				break;
			case 'Problem is suppressed':
				$this->zbxTestTextPresent([
					'No',
					'Yes'
				]);
				break;
			case 'Uptime/Downtime':
				$this->zbxTestTextPresent([
						'is greater than or equals',
						'is less than or equals'
				]);
				break;
			case 'Received value':
				$this->zbxTestDropdownHasOptions('operator', [
						'equals',
						'does not equal',
						'is greater than or equals',
						'is less than or equals',
						'contains',
						'does not contain'
				]);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Application':
			case 'Trigger name':
			case 'Time period':
			case 'Host IP':
			case 'Uptime/Downtime':
			case 'Received value':
			case 'Host name':
			case 'Host metadata':
			case 'Service port':
				$this->zbxTestAssertElementPresentXpath('//input[@id="value"] | //textarea[@id="value"]');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//input[@id="value"] | //textarea[@id="value"]');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Application':
			case 'Trigger name':
			case 'Time period':
			case 'Host IP':
			case 'Received value':
			case 'Host name':
			case 'Host metadata':
			case 'Service port':
				$this->zbxTestAssertAttribute('//textarea[@id="value"] | //input[@id="value"]', 'maxlength', 255);
				break;
			case 'Uptime/Downtime':
				$this->zbxTestAssertAttribute('//input[@id="value"]', 'maxlength', 15);
				$this->zbxTestAssertAttribute('//input[@id="value"]', 'size', 20);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Application':
			case 'Trigger name':
			case 'Received value':
			case 'Host name':
			case 'Host metadata':
				$this->zbxTestAssertElementValue('value', "");
				break;
			case 'Time period':
				$this->zbxTestAssertElementValue('value', '1-7,00:00-24:00');
				break;
			case 'Service port':
				$this->zbxTestAssertElementValue('value', '0-1023,1024-49151');
				break;
			case 'Host IP':
				$this->zbxTestAssertElementValue('value', '192.168.0.1-127,192.168.2.1');
				break;
			case 'Uptime/Downtime':
				$this->zbxTestAssertElementValue('value', 600);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Host group':
			case 'Template':
			case 'Host':
			case 'Trigger':
			case 'Discovery rule':
			case 'Proxy':
				$this->zbxTestAssertElementPresentXpath('//div[@class="multiselect"]/input[@placeholder]');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//div[@id=\'new_condition_value_\']/input[@placeholder]');
				$this->zbxTestAssertElementNotPresentXpath('//div[@id=\'new_condition_value\']/input[@placeholder]');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Trigger severity':
			case 'Trigger value':
			case 'Discovery object':
			case 'Discovery status':
				$this->zbxTestAssertElementPresentXpath('//ul[@id="value" and contains(@class, "radio")]');
				break;
			case 'Event type':
			case 'Service type':
				$this->zbxTestAssertElementPresentXpath('//input[@type="radio" and contains(@id, "0") and @checked]');
				$this->zbxTestAssertElementPresentXpath('//select[@id="value"]');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//ul[@id="value"]|//select[@id="value"]');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Trigger severity':
				$this->zbxTestTextPresent([
						'Not classified',
						'Information',
						'Warning',
						'Average',
						'High',
						'Disaster'
				]);
				break;
			case 'Trigger value':
				$this->zbxTestDropdownHasOptions('new_condition_value', [
						'OK',
						'PROBLEM'
				]);
				break;
			case 'Service type':
				$this->zbxTestDropdownHasOptions('value', [
						'SSH',
						'LDAP',
						'SMTP',
						'FTP',
						'HTTP',
						'HTTPS',
						'POP',
						'NNTP',
						'IMAP',
						'TCP',
						'Zabbix agent',
						'SNMPv1 agent',
						'SNMPv2 agent',
						'SNMPv3 agent',
						'ICMP ping',
						'Telnet'
				]);
				break;
			case 'Discovery object':
				$this->zbxTestTextPresent([
						'Device',
						'Service'
				]);
				break;
			case 'Discovery status':
				$this->zbxTestTextPresent([
						'Up',
						'Down',
						'Discovered',
						'Lost'
				]);
				break;
			case 'Event type':
				$this->zbxTestDropdownHasOptions('value', [
						'Item in "not supported" state',
						'Low-level discovery rule in "not supported" state',
						'Trigger in "unknown" state',
				]);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Trigger severity':
				$this->zbxTestAssertElementPresentXpath('//label[text()="Not classified"]/../input[@checked]');
				break;
			case 'Event type':
				$this->zbxTestAssertAttribute('//*[@id="value"]/option[text()=\'Item in "not supported" state\']', 'selected');
				break;
		}

		$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'drule\']');

		switch ($new_condition_conditiontype) {
			case 'Discovery check':
				$this->zbxTestAssertElementPresentXpath('//input[@id=\'dcheck\']');
				$this->zbxTestAssertAttribute('//input[@id=\'dcheck\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'dcheck\']', 'size', 20);
				$this->zbxTestAssertAttribute('//input[@id=\'dcheck\']', 'readonly');
				$this->zbxTestAssertElementPresentXpath('//button[@id=\'btn1\']');
				$this->zbxTestAssertElementText('//button[@id=\'btn1\']', 'Select');
				break;

			default:
				$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'dcheck\']');
				$this->zbxTestAssertElementNotPresentXpath('//button[@id=\'btn1\']');
				break;
		}

		$this->zbxTestAssertElementPresentXpath("//div[@class='overlay-dialogue-footer']//button[text()='Add']");
		$this->zbxTestClickXpath("//div[@class='overlay-dialogue-footer']//button[text()='Cancel']");

		$this->zbxTestTabSwitch('Operations');

		$form = $this->query('id:action-form')->asForm()->waitUntilVisible()->one();
		$operations_field = $form->getField('Operations')->asTable();

		switch ($eventsource) {
			case 'Triggers':
				$this->assertEquals('1h', $form->getField('Default operation step duration')->getValue());
				$this->zbxTestAssertVisibleId('esc_period');
				$this->zbxTestAssertAttribute('//input[@id=\'esc_period\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'esc_period\']', 'size', 20);

				$this->assertEquals($operations_field->getHeadersText(), ['Steps', 'Details', 'Start in', 'Duration', 'Action']);
				$this->assertTrue($form->getField('Pause operations for suppressed problems')->getValue());
				$recovery_field = $form->getField('Recovery operations')->asTable();
				$this->assertEquals($recovery_field->getHeadersText(), ['Details', 'Action']);
				$update_field = $form->getField('Update operations')->asTable();
				$this->assertEquals($update_field->getHeadersText(), ['Details', 'Action']);
				break;
			case 'Discovery':
			case 'Autoregistration':
				$this->zbxTestTextNotPresent(['Default operation step duration', 'Pause operations for suppressed problems',
					'Recovery operations', 'Update operations']);
				$this->zbxTestAssertElementNotPresentId('esc_period');
				$this->zbxTestAssertElementNotPresentId('pause_suppressed');
				break;
			case 'Internal':
				$this->assertEquals('1h', $form->getField('Default operation step duration')->getValue());
				$this->zbxTestAssertVisibleId('esc_period');
				$this->zbxTestAssertAttribute('//input[@id=\'esc_period\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'esc_period\']', 'size', 20);

				$this->assertEquals($operations_field->getHeadersText(), ['Steps', 'Details', 'Start in', 'Duration', 'Action']);
				$recovery_field = $form->getField('Recovery operations')->asTable();
				$this->assertEquals($recovery_field->getHeadersText(), ['Details', 'Action']);
				$this->zbxTestTextNotPresent(['Pause operations for suppressed problems', 'Update operations']);
				$this->zbxTestAssertElementNotPresentId('pause_suppressed');
				break;
		}

		if (isset($data['new_operation_operationtype'])) {
			$new_operation_operationtype = $data['new_operation_operationtype'];
			$operations_field->query('button:Add')->one()->click();
			COverlayDialogElement::find()->one()->waitUntilReady();
			switch ($eventsource) {
				case 'Triggers':
				case 'Discovery':
				case 'Autoregistration':
					$this->zbxTestWaitUntilElementPresent(webDriverBy::id('operationtype'));
					$this->zbxTestDropdownSelectWait('operationtype', $new_operation_operationtype);
					COverlayDialogElement::find()->one()->waitUntilReady();
					break;
				case 'Internal':
					$this->zbxTestTextPresent('Send message');
					break;
			}
		}
		else {
			$new_operation_operationtype = null;
		}

		if (isset($data['new_operation_opcommand_type'])) {
			$new_operation_opcommand_type = $data['new_operation_opcommand_type'];
			$this->zbxTestDropdownSelect('operation[opcommand][type]', $new_operation_opcommand_type);
		}
		elseif ($new_operation_operationtype == 'Remote command') {
			$new_operation_opcommand_type = $this->zbxTestGetSelectedLabel('operation[opcommand][type]');
		}
		else {
			$new_operation_opcommand_type = null;
		}

		if (isset($data['new_operation_opcommand_authtype'])) {
			$new_operation_opcommand_authtype = $data['new_operation_opcommand_authtype'];
			$this->zbxTestDropdownSelect('operation[opcommand][authtype]', $new_operation_opcommand_authtype);
		}
		elseif ($new_operation_opcommand_type == 'SSH') {
			$new_operation_opcommand_authtype = $this->zbxTestGetSelectedLabel('operation[opcommand][authtype]');
		}
		else {
			$new_operation_opcommand_authtype = null;
		}

		if (isset($data['new_operation_opmessage_custom_msg'])) {
			$new_operation_opmessage_custom_msg = $data['new_operation_opmessage_custom_msg'];
			$this->assertFalse($this->zbxTestCheckboxSelected('operation_opmessage_default_msg'));
		}
		elseif ($new_operation_operationtype == 'Send message') {
			$new_operation_opmessage_custom_msg = 'checked';
			$this->zbxTestCheckboxSelect('operation_opmessage_default_msg');
		}
		else {
			$new_operation_opmessage_custom_msg = null;
		}

		if (isset($data['add_opcondition'])) {
			$this->zbxTestClickXpathWait('//tr[@id="operation-condition-list-footer"]//button[text()="Add"]');
			$this->page->query('xpath://div[contains(@class, "overlay-dialogue modal")][2]')
					->asOverlayDialog()->waitUntilReady();
			$add_opcondition = $data['add_opcondition'];
		}
		else {
			$add_opcondition = null;
		}

		if ($new_operation_operationtype != null && $eventsource == 'Triggers' || $eventsource == 'Internal') 	{
			switch ($new_operation_operationtype) {
				case 'Send message':
				case 'Remote command':
					$this->zbxTestTextPresent('Steps');
					$this->zbxTestAssertVisibleId('operation_esc_step_from');
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_step_from\']', 'maxlength', 5);
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_step_from\']', 'size', 20);
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_step_from\']', 'value', 1);

					$this->zbxTestTextPresent('(0 - infinitely)');
					$this->zbxTestAssertVisibleId('operation_esc_step_to');
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_step_to\']', 'maxlength', 5);
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_step_to\']', 'size', 20);
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_step_to\']', 'value', 1);

					$this->zbxTestTextPresent(['Step duration', '(0 - use action default)']);
					$this->zbxTestAssertVisibleId('operation_esc_period');
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_period\']', 'maxlength', 255);
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_period\']', 'size', 20);
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_period\']', 'value', 0);
					break;
				}
			}
			else {
				$this->zbxTestAssertElementNotPresentId('operation_esc_step_from');
				$this->zbxTestAssertElementNotPresentId('operation_esc_step_to');
				$this->zbxTestAssertElementNotPresentId('operation_esc_period');
			}

		if (isset($data['new_operation_operationtype']) && $eventsource != 'Internal') {
			$this->zbxTestTextPresent('Operation type');
			$this->zbxTestAssertVisibleXpath('//z-select[@name=\'operationtype\']');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//z-select[@name=\'operationtype\']');
		}

		if (isset($data['operationtype'])) {
			switch ($eventsource) {
				case 'Triggers':
				$this->zbxTestDropdownHasOptions('operationtype', [
						'Send message',
						'Remote command'
				]);
					break;
				case 'Discovery':
				$this->zbxTestDropdownHasOptions('operationtype', [
						'Send message',
						'Remote command',
						'Add host',
						'Remove host',
						'Add to host group',
						'Remove from host group',
						'Link to template',
						'Unlink from template',
						'Enable host',
						'Disable host'
				]);
					break;
				case 'Autoregistration':
				$this->zbxTestDropdownHasOptions('operationtype', [
						'Send message',
						'Remote command',
						'Add host',
						'Add to host group',
						'Link to template',
						'Disable host'
				]);
					break;
			}
		}

		if (isset($data['operationtype'])) {
			switch ($eventsource) {
				case 'Triggers':
				case 'Discovery':
				case 'Autoregistration':
					$this->zbxTestDropdownAssertSelected('new_operation[operationtype]', $new_operation_operationtype);
					break;
			}
		}

		if ($new_operation_operationtype === 'Remote command') {
			$this->zbxTestTextPresent(['Target list', 'Current host', 'Host', 'Host group']);
			$this->query('id:operation-command-chst')->one()->isSelected(false);
			$this->zbxTestAssertVisibleId('operation_opcommand_hst__hostid');
			$this->zbxTestAssertVisibleId('operation_opcommand_grp__groupid');
		}
		else {
			$this->zbxTestAssertElementNotPresentId('opCmdList');
			$this->zbxTestAssertElementNotPresentXpath('//li[@id="operation-command-targets"]//label[text()="Target list"]');
			$this->zbxTestAssertElementNotPresentXpath('//li[@id="operation-command-script-target"]//label[text()="Execute on"]');
		}

		if ($new_operation_operationtype == 'Send message') {
			$this->zbxTestTextPresent([
				'Send to user groups', 'User group', 'Action',
				'Send to users'
			]);
			$this->zbxTestAssertVisibleXpath('//tr[@id=\'operation-message-user-groups-footer\']//button[@class=\'btn-link\']');
			$this->zbxTestAssertElementText('//tr[@id=\'operation-message-user-groups-footer\']//button[@class=\'btn-link\']', 'Add');
			$this->zbxTestAssertVisibleXpath('//tr[@id=\'operation-message-users-footer\']//button[@class=\'btn-link\']');
			$this->zbxTestAssertElementText('//tr[@id=\'operation-message-users-footer\']//button[@class=\'btn-link\']', 'Add');

			$this->zbxTestTextPresent('Send only to');
			$this->zbxTestAssertVisibleId('operation-opmessage-mediatypeid');
			$this->zbxTestDropdownAssertSelected('operation[opmessage][mediatypeid]', '- All -');
			$this->zbxTestDropdownHasOptions('operation[opmessage][mediatypeid]', [
					'- All -',
					'Email',
					'SMS'
			]);

			$this->zbxTestTextPresent('Custom message');
			$this->zbxTestAssertElementPresentId('operation_opmessage_default_msg');
			$this->zbxTestAssertElementPresentXpath('//input[@type=\'checkbox\' and @id=\'operation_opmessage_default_msg\']');
			if ($new_operation_opmessage_custom_msg == 'checked') {
				$this->assertTrue($this->zbxTestCheckboxSelected('operation_opmessage_default_msg'));
			}
			else {
				$this->assertFalse($this->zbxTestCheckboxSelected('operation_opmessage_default_msg'));
			}
		}
		else {
			$this->zbxTestAssertElementNotPresentId('addusrgrpbtn');
			$this->zbxTestAssertElementNotPresentId('adduserbtn');
			$this->zbxTestAssertElementNotPresentXpath('//z-select[@name=\'operation[opmessage][mediatypeid]\']');
			$this->zbxTestAssertElementNotPresentId('operation_opmessage_default_msg');
		}

		switch ($new_operation_opmessage_custom_msg) {
			case 'unchecked':
				$this->zbxTestAssertNotVisibleId('operation_opmessage_subject');
				$this->zbxTestAssertNotVisibleId('operation_opmessage_message');
				break;
			case 'checked':
				$this->zbxTestTextPresent('Subject');
				$this->zbxTestAssertVisibleId('operation_opmessage_subject');
				$this->zbxTestAssertAttribute('//input[@id=\'operation_opmessage_subject\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'operation_opmessage_subject\']', 'size', 20);

				$this->zbxTestTextPresent('Message');
				$this->zbxTestAssertVisibleId('operation_opmessage_message');
				$this->zbxTestAssertAttribute('//textarea[@id=\'operation_opmessage_message\']', 'rows', 7);
				break;
			default:
				$this->zbxTestAssertElementNotPresentId('operation_opmessage_subject');
				$this->zbxTestAssertElementNotPresentId('operation_opmessage_message');
				break;
		}

		if ($eventsource == 'Triggers' && $new_operation_operationtype != null) {
			$this->zbxTestTextPresent([
				'Conditions', 'Label', 'Name', 'Action'
			]);

			if ($add_opcondition == null) {
				$this->zbxTestAssertVisibleXpath('//div[@id="operationTab"]//button[text()="Add"]');
			}
			else {
				$this->zbxTestTextPresent('New condition');
				$this->query('xpath://div[contains(@class, "overlay-dialogue modal")][2]'.
						'//button[text()="Cancel"]')->one()->waitUntilVisible();

				$this->zbxTestAssertVisibleXpath('//select[@id="condition_type"]');
				$this->zbxTestDropdownAssertSelected('condition_type', 'Event acknowledged');
				$this->zbxTestDropdownHasOptions('condition_type', [
						'Event acknowledged'
				]);

				$this->zbxTestAssertVisibleXpath('//div[contains(@class, "overlay-dialogue modal")]'.
						'//label[text()="equals"]');
				$this->zbxTestAssertVisibleXpath('//div[contains(@class, "overlay-dialogue modal")]'.
						'//ul[@id="value" and @class="radio-list-control"]');
				$this->zbxTestAssertElementPresentXpath('//label[text()="No"]/../input[@checked]');
				$this->zbxTestClickXpathWait('//div[contains(@class, "overlay-dialogue modal")][2]'.
						'//button[text()="Add"]');
				$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath('//div[contains(@class, "overlay-dialogue '.
						'modal")][2]'));
			}
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//div[@id="operationTab"]//button[contains(@onclick,"new_opcondition")]');
			$this->zbxTestAssertElementNotPresentXpath('//div[@id="operationTab"]//button[contains(@onclick,"cancel_new_opcondition")]');

			$this->zbxTestAssertElementNotPresentXpath('//select[@id=\'new_opcondition_conditiontype\']');
			$this->zbxTestAssertElementNotPresentXpath('//select[@id=\'new_opcondition_operator\']');
			$this->zbxTestAssertElementNotPresentXpath('//select[@id=\'new_opcondition_value\']');
		}

		if ($new_operation_opcommand_type != null) {
			$this->zbxTestTextPresent('Type');
			$this->query('xpath://z-select[@name="operation[opcommand][type]"]')->waitUntilVisible();
			$this->zbxTestDropdownAssertSelected('operation[opcommand][type]', $new_operation_opcommand_type);
			$this->zbxTestDropdownHasOptions('operation[opcommand][type]', [
					'IPMI',
					'Custom script',
					'SSH',
					'Telnet',
					'Global script'
			]);
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//z-select[@name="operation[opcommand][type]"]');
		}

		if ($new_operation_opcommand_type == 'Custom script') {
			$this->zbxTestTextPresent([
				'Execute on', 'Zabbix agent', 'Zabbix server']
			);
			$this->zbxTestAssertElementPresentXpath('//input[@id=\'operation_opcommand_execute_on_0\']');
			$this->assertTrue($this->zbxTestCheckboxSelected('operation_opcommand_execute_on_0'));
			$this->zbxTestAssertElementPresentXpath('//input[@id=\'operation_opcommand_execute_on_1\']');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'operation_opcommand_execute_on_0\']');
			$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'operation_opcommand_execute_on_1\']');
		}

		switch ($new_operation_opcommand_type) {
			case 'Custom script':
			case 'SSH':
			case 'Telnet':
				$this->zbxTestTextPresent('Commands');
				$this->zbxTestAssertVisibleXpath('//textarea[@id=\'operation_opcommand_command\']');
				$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_command\']', 'rows', 7);
				break;
			case 'IPMI':
				$this->zbxTestTextPresent('Commands');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'operation_opcommand_command_ipmi\']');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//textarea[@id=\'operation_opcommand_command\']');
				break;
		}

		if ($new_operation_opcommand_type == 'IPMI') {
			$this->zbxTestTextPresent('Commands');
			$this->zbxTestAssertVisibleXpath('//input[@id="operation_opcommand_command_ipmi"]');
			$this->zbxTestAssertAttribute('//*[@id="operation_opcommand_command_ipmi"]', 'maxlength', 255);
			$this->zbxTestAssertAttribute('//*[@id="operation_opcommand_command_ipmi"]', 'size', 20);
			$this->zbxTestAssertElementValue('operation_opcommand_command_ipmi', '');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//input[@id="operation_opcommand_command_ipmi"]');
		}

		switch ($new_operation_opcommand_type) {
			case 'SSH':
				$this->zbxTestTextPresent('Authentication method');
				$this->zbxTestAssertVisibleXpath('//z-select[@name="operation[opcommand][authtype]"]');
				$this->zbxTestDropdownHasOptions('operation[opcommand][authtype]', [
						'Password',
						'Public key'
				]);
				$this->zbxTestDropdownAssertSelected('operation[opcommand][authtype]',
						$new_operation_opcommand_authtype
				);
				break;
			case 'IPMI':
			case 'Custom script':
			case 'Telnet':
			case 'Global script':
				$this->zbxTestAssertElementNotPresentXpath('//z-select[@name="operation[opcommand][authtype]"]');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//label[@for="operation_opcommand_authtype" and text()="Authentication method"]');
				break;
		}

		switch ($new_operation_opcommand_type) {
			case 'SSH':
			case 'Telnet':
				$this->zbxTestTextPresent('User name');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'operation_opcommand_username\']');
				$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_username\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_username\']', 'size', 20);
				$this->zbxTestAssertElementValue('operation_opcommand_username', '');
				break;
			case 'IPMI':
			case 'Custom script':
			case 'Global script':
				$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'operation_opcommand_username\']');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//label[@for="operation_opcommand_username" and text()="User name"]');
				break;
		}

		switch ($new_operation_opcommand_authtype) {
			case 'Password':
				$this->zbxTestTextPresent('Password');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'operation_opcommand_password\']');
				$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_password\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_password\']', 'size', 20);
				$this->zbxTestAssertElementValue('operation_opcommand_password', '');

				$this->zbxTestAssertNotVisibleId('opcommand_passphrase');

				$this->zbxTestAssertNotVisibleId('operation_opcommand_publickey');
				$this->zbxTestAssertNotVisibleId('operation_opcommand_privatekey');
				break;
			case 'Public key':
				$this->zbxTestTextPresent('Key passphrase');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'opcommand_passphrase\']');
				$this->zbxTestAssertAttribute('//*[@id=\'opcommand_passphrase\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//*[@id=\'opcommand_passphrase\']', 'size', 20);
				$this->zbxTestAssertElementValue('opcommand_passphrase', '');

				$this->zbxTestAssertNotVisibleXpath('//input[@id=\'operation_opcommand_password\']');

				$this->zbxTestTextPresent('Public key file');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'operation_opcommand_publickey\']');
				$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_publickey\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_publickey\']', 'size', 20);
				$this->zbxTestAssertElementValue('operation_opcommand_publickey', '');

				$this->zbxTestTextPresent('Private key file');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'operation_opcommand_privatekey\']');
				$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_privatekey\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_privatekey\']', 'size', 20);
				$this->zbxTestAssertElementValue('operation_opcommand_privatekey', '');
				break;
			default:
				if ($new_operation_opcommand_type != null) {
					$this->zbxTestAssertElementNotPresentXpath('//li[@id="operation-command-pubkey"]//input[@id="operation_opcommand_publickey"]');
					$this->zbxTestAssertElementNotPresentXpath('//li[@id="operation-command-privatekey"]//input[@id="operation_opcommand_privatekey"]');
					$this->zbxTestAssertElementNotPresentXpath('//li[@id="operation-command-passphrase"]//input[@id="opcommand_passphrase"]');
				}
				else {
					$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'operation_opcommand_password\']');
					$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'opcommand_passphrase\']');
					$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'operation_opcommand_publickey\']');
					$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'operation_opcommand_privatekey\']');
				}
				break;
		}

		switch ($new_operation_opcommand_type) {
			case 'SSH':
			case 'Telnet':
				$this->zbxTestTextPresent('Port');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'operation_opcommand_port\']');
				$this->zbxTestAssertAttribute('//input[@id=\'operation_opcommand_port\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'operation_opcommand_port\']', 'size', 20);
				$this->zbxTestAssertElementValue('operation_opcommand_port', '');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'operation_opcommand_port\']');
				break;
		}

		if ($new_operation_opcommand_type == 'Global script') {
			$this->zbxTestAssertVisibleXpath('//input[@id=\'operation_opcommand_script\']');
			$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_script\']', 'maxlength', 255);
			$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_script\']', 'size', 20);
			$this->zbxTestAssertAttribute('//*[@id=\'operation_opcommand_script\']', 'readonly');
			$this->zbxTestAssertElementValue('operation_opcommand_script', '');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'operation_opcommand_script\']');
		}

		switch ($new_operation_operationtype) {
			case 'Add to host group':
			case 'Remove from host group':
				$this->zbxTestAssertElementPresentXpath('//div[@id=\'operation_opgroup__groupid\']/input');
				$this->zbxTestAssertElementNotPresentXpath('//div[@id=\'operation_optemplate__templateid\']/input');
				break;
			case 'Link to template':
			case 'Unlink from template':
				$this->zbxTestAssertElementPresentXpath('//div[@id=\'operation_optemplate__templateid\']/input');
				$this->zbxTestAssertElementNotPresentXpath('//div[@id=\'operation_opgroup__groupid\']/input');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//div[@id=\'operation_groupids_\']/input');
				$this->zbxTestAssertElementNotPresentXpath('//div[@id=\'operation_templateids_\']/input');
				break;
		}

		if ($new_operation_operationtype != null) {
			$this->zbxTestAssertVisibleXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');
			$this->zbxTestAssertVisibleXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Cancel"]');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//div[@id="operationTab"]//button[contains(@onclick,"add_operation")]');
			$this->zbxTestAssertElementNotPresentXpath('//div[@id="operationTab"]//button[contains(@onclick,"cancel_new_operation")]');
		}

		if (CTestArrayHelper::get($data, 'recovery_msg', false)) {
			$this->checkRecoveryUpdateOperations($recovery_field, $eventsource);
		}

		if (CTestArrayHelper::get($data, 'acknowledge_msg', false)) {
			$this->checkRecoveryUpdateOperations($update_field, $eventsource);
		}

		$this->zbxTestAssertVisibleId('add');
		$this->zbxTestAssertAttribute('//button[@id="add" and @type="submit"]', 'value', 'Add');

		$this->zbxTestAssertVisibleId('cancel');
		$this->zbxTestAssertAttribute('//button[@id=\'cancel\']', 'name', 'cancel');
	}

	/*
	 * Function that checks possible operation types and custom message related fields for recovery and update operations.
	 */
	private function checkRecoveryUpdateOperations($operation_field, $eventsource) {
		$operation_field->query('button:Add')->one()->click();
		COverlayDialogElement::find()->one()->waitUntilReady();
		$operation_details = $this->query('name:popup.operation')->asForm()->one();
		// Check available operation types depending on event source and the selected operation type.
		$message_types = ($eventsource === 'Triggers') ? ['Send message', 'Remote command', 'Notify all involved'] :
				['Send message', 'Notify all involved'];
		$this->zbxTestDropdownHasOptions('operationtype', $message_types);
		$this->assertEquals('Send message', $operation_details->getField('Operation type')->getValue());
		// Make sure that Custom message is unchecked and that message related fields are not visible.
		$this->assertFalse($operation_details->getField('Custom message')->getValue());
		$this->zbxTestTextNotVisible(['Subject','Message']);
		// Set the Custom message option and check Subject and Message fields.
		$operation_details->getField('Custom message')->set(true);
		$this->assertEquals(255, $operation_details->getField('Subject')->waitUntilVisible()->getAttribute('maxlength'));
		$this->assertFalse($operation_details->getField('Message')->isAttributePresent('maxlength'));
		COverlayDialogElement::find()->one()->close();
	}

	public static function update() {
		return CDBHelper::getDataProvider('SELECT name, eventsource FROM actions');
	}

	/**
	 * @dataProvider update
	 */
	public function testFormAction_SimpleUpdate($data) {
		$name = $data['name'];
		$eventsource = $data['eventsource'];

		if ($name == 'Auto discovery. Linux servers.') {
			$sqlActions = "SELECT actionid,name,eventsource,evaltype,status FROM actions ORDER BY actionid";
		}
		else {
			$sqlActions = "SELECT * FROM actions ORDER BY actionid";
		}
		$oldHashActions = CDBHelper::getHash($sqlActions);

		$this->zbxTestLogin('actionconf.php');
		switch ($eventsource) {
			case EVENT_SOURCE_TRIGGERS:
				$this->query('id:page-title-general')->asPopupButton()->one()->select('Trigger actions');
				break;
			case EVENT_SOURCE_DISCOVERY:
				$this->query('id:page-title-general')->asPopupButton()->one()->select('Discovery actions');
				break;
			case EVENT_SOURCE_AUTOREGISTRATION:
				$this->query('id:page-title-general')->asPopupButton()->one()->select('Autoregistration actions');
				break;
			case EVENT_SOURCE_INTERNAL;
				$this->query('id:page-title-general')->asPopupButton()->one()->select('Internal actions');
				break;
		}

		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of actions');

		$this->zbxTestCheckHeader($this->event_sources[$data['eventsource']]);
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Action updated');
		$this->zbxTestTextPresent([
				'Action updated',
				'Actions',
				$name
		]);

		$this->assertEquals($oldHashActions, CDBHelper::getHash($sqlActions));
	}

	public static function create() {
		return [
			[[
				'expected' => ACTION_GOOD,
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'name' => 'TestFormAction Triggers 001',
				'esc_period' => '123',
				'conditions' => [
					[
						'type' => 'Trigger name',
						'value' => 'trigger',
					],
					[
						'type' => 'Trigger severity',
						'value' => 'Warning',
					],
					[
						'type' => 'Application',
						'value' => 'application',
					],
					[
						'type' => 'Tag name',
						'operator' => 'does not contain',
						'value' => 'Does not contain Tag',
					],
				],
				'operations' => [
					[
						'type' => 'Send message',
						'media' => 'Email',
					],
					[
						'type' => 'Remote command',
						'command' => 'command',
					]
				],
			]],
			[[
				'expected' => ACTION_BAD,
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'name' => '',
				'esc_period' => '123',
				'errors' => [
						'Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
				]
			]],
			[[
				'expected' => ACTION_GOOD,
				'eventsource' => EVENT_SOURCE_DISCOVERY,
				'name' => 'TestFormAction Discovery 001',
				'conditions' => [
					[
						'type' => 'Service type',
						'value' => 'FTP',
					],
					[
						'type' => 'Received value',
						'operator' => 'does not contain',
						'value' => 'Received value',
					]
				],
				'operations' => [
					[
						'type' => 'Send message',
						'media' => 'Email',
					],
					[
						'type' => 'Remote command',
						'command' => 'command',
					]
				],
			]],
			[[
				'expected' => ACTION_BAD,
				'eventsource' => EVENT_SOURCE_DISCOVERY,
				'name' => '',
				'errors' => [
						'Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
				]
			]],
			[[
				'expected' => ACTION_GOOD,
				'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
				'name' => 'TestFormAction Autoregistration 001',
				'conditions' => [
					[
						'type' => 'Host name',
						'value' => 'Zabbix',
					],
					[
						'type' => 'Host metadata',
						'operator'=> 'does not contain',
						'value' => 'Zabbix',
					]
				],
				'operations' => [
					[
						'type' => 'Send message',
						'media' => 'Email',
					],
					[
						'type' => 'Remote command',
						'command' => 'command',
					]
				],
			]],
			[[
				'expected' => ACTION_BAD,
				'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
				'name' => '',
				'errors' => [
						'Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
				]
			]],
			[[
				'expected' => ACTION_GOOD,
				'eventsource' => EVENT_SOURCE_INTERNAL,
				'name' => 'TestFormAction Internal 001',
				'esc_period' => '123',
				'conditions' => [
					[
						'type' => 'Event type',
						'value' => 'Trigger in "unknown" state',
					],
					[
						'type' => 'Application',
						'value' => 'application',
					],
				],
				'operations' => [
					[
						'type' => 'Send message',
						'media' => 'Email',
					]
				]
			]],
			[[
				'expected' => ACTION_BAD,
				'eventsource' => EVENT_SOURCE_INTERNAL,
				'name' => '',
				'esc_period' => '123',
				'errors' => [
						'Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
				]
			]]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormAction_SimpleCreate($data) {
		$this->zbxTestLogin('actionconf.php?form=1&eventsource='.$data['eventsource']);
		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestCheckHeader('Actions');

		if (isset($data['name'])){
			$this->zbxTestInputTypeOverwrite('name', $data['name']);
			$this->zbxTestAssertElementValue('name', $data['name']);
		}

			$conditionCount = 0;

		if (isset($data['conditions'])) {
			foreach ($data['conditions'] as $condition) {
				$this->zbxTestClickXpathWait('//button[text()="Add" and contains(@onclick, "popup.condition.actions")]');
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('condition_type'));
				$this->zbxTestDropdownSelectWait('condition_type', $condition['type']);
				COverlayDialogElement::find()->one()->waitUntilReady();
				switch ($condition['type']) {
					case 'Application':
					case 'Host name':
					case 'Host metadata':
					case 'Trigger name':
					case 'Tag name':
						if (array_key_exists('operator', $condition)) {
							$this->zbxTestClickXpathWait('//label[text()="'.$condition['operator'].'"]');
						}
						$this->zbxTestInputTypeWait('value', $condition['value']);
						$this->zbxTestClickXpath("//div[@class='overlay-dialogue-footer']//button[text()='Add']");
						switch($condition['type']){
							case 'Application':
								$this->zbxTestAssertElementText("//tr[@id='conditions_".$conditionCount."']/td[2]", 'Application equals '.$condition['value']);
								$conditionCount++;
								break;
							case 'Host name':
								$this->zbxTestAssertElementText('//tr[@id="conditions_'.$conditionCount.'"]/td[2]', 'Host name contains '.$condition['value']);
								$conditionCount++;
								break;
							case 'Host metadata':
								$this->zbxTestAssertElementText('//tr[@id="conditions_'.$conditionCount.'"]/td[2]', 'Host metadata does not contain '.$condition['value']);
								$conditionCount++;
								break;
							case 'Trigger name':
								$this->zbxTestAssertElementText("//tr[@id='conditions_".$conditionCount."']/td[2]", 'Trigger name contains '.$condition['value']);
								$conditionCount++;
								break;
							case 'Tag':
								$this->zbxTestAssertElementText("//tr[@id='conditions_".$conditionCount."']/td[2]", 'Tag does not contain '.$condition['value']);
								$conditionCount++;
								break;
						}
						break;
					case 'Received value':
						$this->zbxTestDropdownSelect('operator', $condition['operator']);
						$this->zbxTestInputTypeWait('value', $condition['value']);
						$this->zbxTestClickXpath("//div[@class='overlay-dialogue-footer']//button[text()='Add']");

						$this->zbxTestAssertElementText('//tr[@id="conditions_'.$conditionCount.'"]/td[2]', 'Received value does not contain '.$condition['value']);
						$conditionCount++;
						break;
					case 'Trigger severity':
						$this->zbxTestClickXpathWait('//label[text()="'.$condition['value'].'"]');
						$this->zbxTestClickXpath("//div[@class='overlay-dialogue-footer']//button[text()='Add']");

						$this->zbxTestAssertElementText('//tr[@id="conditions_'.$conditionCount.'"]/td[2]', 'Trigger severity equals '.$condition['value']);
						$conditionCount++;
						break;
					case 'Event type':
					case 'Service type':
						$this->zbxTestDropdownSelectWait('value', $condition['value']);
						$this->zbxTestClickXpath("//div[@class='overlay-dialogue-footer']//button[text()='Add']");
						switch($condition['type']){
							case 'Service type':
								$this->zbxTestAssertElementText('//tr[@id="conditions_'.$conditionCount.'"]/td[2]', 'Service type equals '.$condition['value']);
								$conditionCount++;
								break;
							case 'Event type':
								$this->zbxTestAssertElementText('//tr[@id="conditions_'.$conditionCount.'"]/td[2]', 'Event type equals '.$condition['value']);
								$conditionCount++;
								break;
						}
						break;
				}
			}
		}

		if (isset($data['operations'])) {
			$this->page->waitUntilReady();
			$this->zbxTestTabSwitch('Operations');

			foreach ($data['operations'] as $operation) {
				$this->zbxTestClickXpathWait('//div[@id="operationTab"]//button[text()="Add"]');
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]'));
			if ($data['eventsource']!= EVENT_SOURCE_INTERNAL){
				$this->zbxTestDropdownSelectWait('operationtype', $operation['type']);
			}
				switch ($operation['type']) {
					case 'Send message':
						$this->zbxTestClickXpath('//tr[@id="operation-message-user-groups-footer"]//button');
						$this->zbxTestLaunchOverlayDialog('User groups');
						$this->zbxTestCheckboxSelect('all_records');
						$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

						$this->zbxTestClickXpath('//tr[@id="operation-message-users-footer"]//button');
						$this->zbxTestLaunchOverlayDialog('Users');
						$this->zbxTestCheckboxSelect('all_records');
						$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

						$this->zbxTestDropdownSelect('operation[opmessage][mediatypeid]', $operation['media']);
						break;
					case 'Remote command':
						$this->zbxTestCheckboxSelect('operation-command-chst');
						$this->zbxTestInputType('operation_opcommand_command', $operation['command']);
						break;
				}
				$this->zbxTestClickXpathWait('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('operations_0'));
			}
			COverlayDialogElement::ensureNotPresent();
		}

		if (isset($data['esc_period'])){
			$this->zbxTestTabSwitch('Operations');
			$this->zbxTestInputTypeOverwrite('esc_period', $data['esc_period']);
			// Fire onchange event.
			$this->webDriver->executeScript('var event = document.createEvent("HTMLEvents");'.
				'event.initEvent("change", false, true);'.
				'document.getElementById("esc_period").dispatchEvent(event);'
			);
			$this->zbxTestWaitForPageToLoad();
		}
		$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath('//div[contains(@class, "overlay-dialogue modal")]'));
		$this->query('xpath://button[@id="add"]')->waitUntilClickable()->one()->click();
		switch ($data['expected']) {
			case ACTION_GOOD:
				$this->zbxTestCheckTitle('Configuration of actions');

				$this->zbxTestCheckHeader($this->event_sources[$data['eventsource']]);
				$this->zbxTestTextNotPresent(['Page received incorrect data', 'Cannot add action']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Action added');
				$sql = "SELECT actionid FROM actions WHERE name='".$data['name']."'";
				$this->assertEquals(1, CDBHelper::getCount($sql), 'Action has not been created in the DB.');
				break;

			case ACTION_BAD:
				$this->zbxTestCheckTitle('Configuration of actions');
				$this->zbxTestCheckHeader('Actions');
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Page received incorrect data');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}

	public function testFormAction_Create() {
		$this->zbxTestLogin('actionconf.php?form=1&eventsource=0');
		$this->zbxTestCheckTitle('Configuration of actions');

		$this->zbxTestInputTypeWait('name', 'action test');

// adding conditions
		$this->zbxTestClickXpathWait('//button[text()="Add" and contains(@onclick, "popup.condition.actions")]');
		$this->zbxTestLaunchOverlayDialog('New condition');
		$this->zbxTestDropdownSelectWait('condition_type', 'Trigger name');
		$this->zbxTestInputTypeWait('value', 'trigger');
		$this->zbxTestClickXpath("//div[@class='overlay-dialogue-footer']//button[text()='Add']");
		$this->zbxTestAssertElementText("//tr[@id='conditions_0']/td[2]", 'Trigger name contains trigger');

		$this->zbxTestClickXpathWait('//button[text()="Add" and contains(@onclick, "popup.condition.actions")]');
		$this->zbxTestLaunchOverlayDialog('New condition');
		$this->zbxTestDropdownSelectWait('condition_type', 'Trigger severity');
		$this->zbxTestClickXpathWait('//label[text()="Average"]');
		$this->zbxTestClickXpath("//div[@class='overlay-dialogue-footer']//button[text()='Add']");
		$this->zbxTestAssertElementText("//tr[@id='conditions_1']/td[2]", 'Trigger severity equals Average');

		$this->zbxTestClickXpathWait('//button[text()="Add" and contains(@onclick, "popup.condition.actions")]');
		$this->zbxTestLaunchOverlayDialog('New condition');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('condition_type'));
		$this->zbxTestDropdownSelectWait('condition_type', 'Application');
		$this->zbxTestInputTypeWait('value', 'app');
		$this->zbxTestClickXpath("//div[@class='overlay-dialogue-footer']//button[text()='Add']");
		$this->zbxTestAssertElementText("//tr[@id='conditions_2']/td[2]", 'Application equals app');

// adding operations
		$this->zbxTestTabSwitch('Operations');
		$this->zbxTestClickXpathWait('//div[@id="operationTab"]//button[text()="Add"]');

		$this->zbxTestClickXpathWait('//tr[@id="operation-message-user-groups-footer"]//button');
		$this->zbxTestLaunchOverlayDialog('User groups');
		$this->zbxTestCheckboxSelect('item_7');
		$this->zbxTestCheckboxSelect('item_11');
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		$this->zbxTestClickXpath('//tr[@id="operation-message-users-footer"]//button');
		$this->zbxTestLaunchOverlayDialog('Users');
		$this->zbxTestCheckboxSelect('item_1');
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		$this->zbxTestDropdownSelect('operation[opmessage][mediatypeid]', 'SMS');
		$this->zbxTestClickXpathWait('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');
		$this->zbxTestAssertElementText("//tr[@id='operations_0']//span",
			"Send message to users: Admin (Zabbix Administrator) via SMS ".
			"Send message to user groups: Enabled debug mode, Zabbix administrators via SMS");

		$this->zbxTestClickXpathWait('//div[@id="operationTab"]//button[text()="Add"]');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]'));
		$this->zbxTestDropdownSelectWait('operationtype', 'Remote command');

// add target current host
		$this->zbxTestCheckboxSelect('operation-command-chst');

// add target host Zabbix server
		$this->zbxTestClickButtonMultiselect('operation_opcommand_hst__hostid');
		$this->zbxTestLaunchOverlayDialog('Hosts');
		$this->zbxTestClickButtonMultiselect('popup_host_group');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->query('link:Zabbix servers')->one()->waitUntilClickable()->click();

		$this->zbxTestClickLinkTextWait('Simple form test host');
// add target group Zabbix servers
		$this->zbxTestClickButtonMultiselect('operation_opcommand_grp__groupid');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->zbxTestClickLinkTextWait('Zabbix servers');

		$this->zbxTestInputType('operation_opcommand_command', 'command');
		$this->zbxTestClickXpathWait('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');
		COverlayDialogElement::ensureNotPresent();
		$this->page->waitUntilReady();
		$this->zbxTestWaitUntilElementClickable(WebDriverBy::id('add'));
		$this->zbxTestAssertElementText("//tr[@id='operations_0']//span",
			"Send message to users: Admin (Zabbix Administrator) via SMS ".
			"Send message to user groups: Enabled debug mode, Zabbix administrators via SMS");
		$this->zbxTestAssertElementText("//tr[@id='operations_1']//span",
			"Run remote commands on current host ".
			"Run remote commands on hosts: Simple form test host ".
			"Run remote commands on host groups: Zabbix servers");

		$this->zbxTestClickXpathWait('//div[@id="operationTab"]//button[text()="Add"]');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]'));
		$this->zbxTestInputTypeOverwrite('operation_esc_step_to', '2');
		$this->zbxTestDropdownSelectWait('operationtype', 'Remote command');
		$this->zbxTestCheckboxSelect('operation-command-chst');

		$this->zbxTestDropdownSelect('operation[opcommand][type]', 'SSH');
		$this->zbxTestInputTypeWait('operation_opcommand_username', 'user');
		$this->zbxTestInputType('operation_opcommand_password', 'pass');
		$this->zbxTestInputType('operation_opcommand_port', '123');
		$this->zbxTestInputType('operation_opcommand_command', 'command ssh');
		$this->zbxTestClickXpathWait('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');
		$this->page->waitUntilReady();
		$this->zbxTestAssertElementText("//tr[@id='operations_0']//span",
			"Send message to users: Admin (Zabbix Administrator) via SMS ".
			"Send message to user groups: Enabled debug mode, Zabbix administrators via SMS");
		$this->zbxTestAssertElementText("//tr[@id='operations_1']//span",
			"Run remote commands on current host ".
			"Run remote commands on hosts: Simple form test host ".
			"Run remote commands on host groups: Zabbix servers");
		$this->zbxTestAssertElementText("//tr[@id='operations_2']//span",
			"Run remote commands on current host");
		$this->zbxTestAssertElementText('//tr[@id="operations_2"]//td', '1 - 2');

		$this->zbxTestInputTypeOverwrite('esc_period', '123');
		// Fire onchange event.
		$this->webDriver->executeScript('var event = document.createEvent("HTMLEvents");'.
				'event.initEvent("change", false, true);'.
				'document.getElementById("esc_period").dispatchEvent(event);'
		);
		$this->zbxTestWaitForPageToLoad();

		$this->zbxTestAssertElementValue('esc_period', '123');
		$this->zbxTestClickXpath('//div[@id="operationTab"]//button[text()="Add"]');

		$this->zbxTestWaitUntilElementClickable(WebDriverBy::xpath('//tr[@id="operation-message-users-footer"]//button'));

		$this->zbxTestClickXpath('//tr[@id="operation-message-users-footer"]//button');
		$this->page->query('xpath://div[contains(@class, "overlay-dialogue modal")][2]'.
				'//button[text()="Cancel"]')->waitUntilClickable()->one()->click();
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Cancel"]');
		$this->zbxTestWaitUntilElementClickable(WebDriverBy::id('add'));

		$this->zbxTestDoubleClickBeforeMessage('add', 'filter_name');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Action added');

		$sql = "SELECT actionid FROM actions WHERE name='action test'";
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Action has not been created in the DB.');
	}
}
