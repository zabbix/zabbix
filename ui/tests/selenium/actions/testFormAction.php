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


require_once __DIR__.'/../../include/CLegacyWebTest.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';

define('ACTION_GOOD', 0);
define('ACTION_BAD', 1);

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup actions, profiles
 *
 * @dataSource Actions
 */
class testFormAction extends CLegacyWebTest {

	protected $event_sources = [
		EVENT_SOURCE_TRIGGERS => 'Trigger actions',
		EVENT_SOURCE_SERVICE => 'Service actions',
		EVENT_SOURCE_DISCOVERY => 'Discovery actions',
		EVENT_SOURCE_AUTOREGISTRATION => 'Autoregistration actions',
		EVENT_SOURCE_INTERNAL => 'Internal actions'
	];

	const SERVICE_ACTION = 'Service action';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public static function layout() {
		return [
			[
				[
					'eventsource' => EVENT_SOURCE_TRIGGERS,
					'recovery_msg' => true,
					'acknowledge_msg' => true
				]
			],
			[
				[
					'eventsource' => EVENT_SOURCE_TRIGGERS,
					'new_operation_operationtype' => 'Send message'
				]
			],
			[
				[
					'eventsource' => EVENT_SOURCE_TRIGGERS,
					'new_operation_operationtype' => 'Send message',
					'new_operation_opmessage_custom_msg' => 'unchecked'
				]
			],
			[
				[
					'eventsource' => EVENT_SOURCE_TRIGGERS,
					'new_operation_operationtype' => 'Send message',
					'add_opcondition' => true
				]
			],
			[
				[
					'eventsource' => EVENT_SOURCE_TRIGGERS,
					'check_operationtype' => true,
					'new_operation_operationtype' => 'Reboot',
					'add_opcondition' => true
				]
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'evaltype' => 'And']
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'evaltype' => 'Or']
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'new_condition_conditiontype' => 'Tag name']
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'new_condition_conditiontype' => 'Tag value']
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'new_condition_conditiontype' => 'Host group']
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'new_condition_conditiontype' => 'Template']
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'new_condition_conditiontype' => 'Host']
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'new_condition_conditiontype' => 'Trigger']
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'new_condition_conditiontype' => 'Event name']
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'new_condition_conditiontype' => 'Trigger severity']
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'new_condition_conditiontype' => 'Time period']
			],
			[
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'new_condition_conditiontype' => 'Problem is suppressed']
			],
			[
				[
					'eventsource' => EVENT_SOURCE_SERVICE,
					'recovery_msg' => true,
					'acknowledge_msg' => true
				]
			],
			[
				[
					'eventsource' => EVENT_SOURCE_SERVICE,
					'new_operation_operationtype' => 'Send message'
				]
			],
			[
				[
					'eventsource' => EVENT_SOURCE_SERVICE,
					'new_operation_operationtype' => 'Send message',
					'new_operation_opmessage_custom_msg' => 'unchecked'
				]
			],
			[
				[
					'eventsource' => EVENT_SOURCE_SERVICE,
					'check_operationtype' => true,
					'new_operation_operationtype' => 'Reboot'
				]
			],
			[
				['eventsource' => EVENT_SOURCE_SERVICE, 'new_condition_conditiontype' => 'Service']
			],
			[
				['eventsource' => EVENT_SOURCE_SERVICE, 'new_condition_conditiontype' => 'Service name']
			],
			[
				['eventsource' => EVENT_SOURCE_SERVICE, 'new_condition_conditiontype' => 'Service tag name']
			],
			[
				['eventsource' => EVENT_SOURCE_SERVICE, 'new_condition_conditiontype' => 'Service tag value']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY]
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_condition_conditiontype' => 'Host IP']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_condition_conditiontype' => 'Service type']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_condition_conditiontype' => 'Service port']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_condition_conditiontype' => 'Discovery rule']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_condition_conditiontype' => 'Discovery check']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_condition_conditiontype' => 'Discovery object']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_condition_conditiontype' => 'Discovery status']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_condition_conditiontype' => 'Uptime/Downtime']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_condition_conditiontype' => 'Received value']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_condition_conditiontype' => 'Proxy']
			],
			[
				[
					'eventsource' => EVENT_SOURCE_DISCOVERY,
					'new_operation_operationtype' => 'Send message'
				]
			],
			[
				[
					'eventsource' => EVENT_SOURCE_DISCOVERY,
					'new_operation_operationtype' => 'Send message',
					'new_operation_opmessage_custom_msg' => 'unchecked'
				]
			],
			[
				[
					'eventsource' => EVENT_SOURCE_DISCOVERY,
					'new_operation_operationtype' => 'Reboot'
				]
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'check_operationtype' => true, 'new_operation_operationtype' => 'Add host']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_operation_operationtype' => 'Remove host']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_operation_operationtype' => 'Add to host group']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_operation_operationtype' => 'Remove from host group']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_operation_operationtype' => 'Link template']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_operation_operationtype' => 'Unlink template']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_operation_operationtype' => 'Enable host']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_operation_operationtype' => 'Disable host']
			],
			[
				['eventsource' => EVENT_SOURCE_AUTOREGISTRATION]
			],
			[
				['eventsource' => EVENT_SOURCE_AUTOREGISTRATION, 'new_condition_conditiontype' => 'Host name']
			],
			[
				['eventsource' => EVENT_SOURCE_AUTOREGISTRATION, 'new_condition_conditiontype' => 'Proxy']
			],
			[
				['eventsource' => EVENT_SOURCE_AUTOREGISTRATION, 'new_condition_conditiontype' => 'Host metadata']
			],
			[
				[
					'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
					'new_operation_operationtype' => 'Send message'
				]
			],
			[
				[
					'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
					'new_operation_operationtype' => 'Send message',
					'new_operation_opmessage_custom_msg' => 'unchecked'
				]
			],
			[
				['eventsource' => EVENT_SOURCE_AUTOREGISTRATION, 'check_operationtype' => true, 'new_operation_operationtype' => 'Add host']
			],
			[
				['eventsource' => EVENT_SOURCE_AUTOREGISTRATION, 'new_operation_operationtype' => 'Add to host group']
			],
			[
				['eventsource' => EVENT_SOURCE_AUTOREGISTRATION, 'new_operation_operationtype' => 'Link template']
			],
			[
				['eventsource' => EVENT_SOURCE_AUTOREGISTRATION, 'new_operation_operationtype' => 'Disable host']
			],
			[
				['eventsource' => EVENT_SOURCE_INTERNAL]
			],
			[
				['eventsource' => EVENT_SOURCE_INTERNAL, 'recovery_msg' => true]
			],
			[
				['eventsource' => EVENT_SOURCE_INTERNAL, 'new_condition_conditiontype' => 'Tag name']
			],
			[
				['eventsource' => EVENT_SOURCE_INTERNAL, 'new_condition_conditiontype' => 'Tag value']
			],
			[
				['eventsource' => EVENT_SOURCE_INTERNAL, 'new_condition_conditiontype' => 'Event type']
			],
			[
				['eventsource' => EVENT_SOURCE_INTERNAL, 'new_condition_conditiontype' => 'Host group']
			],
			[
				['eventsource' => EVENT_SOURCE_INTERNAL, 'new_condition_conditiontype' => 'Template']
			],
			[
				['eventsource' => EVENT_SOURCE_INTERNAL, 'new_condition_conditiontype' => 'Host']
			],
			[
				['eventsource' => EVENT_SOURCE_INTERNAL, 'new_operation_operationtype' => 'Send message']
			],
			[
				[
					'eventsource' => EVENT_SOURCE_INTERNAL,
					'check_operationtype' => true,
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

		$this->zbxTestLogin('zabbix.php?action=action.list&eventsource='.$eventsource.'');
		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestClickButtonText('Create action');
		$this->zbxTestLaunchOverlayDialog('New action');
		$this->zbxTestTextPresent(['Action', 'Operations']);

		$this->zbxTestTextPresent('Name');
		$this->zbxTestAssertElementPresentXpath('//div[@class="form-grid"]//input[@id="name"]');
		$this->zbxTestAssertAttribute('//input[@id="name"]', 'maxlength', 255);
		$this->zbxTestAssertAttribute('//input[@id="name"]', 'autofocus');

		$this->zbxTestTextPresent('Enabled');
		$this->zbxTestAssertElementPresentId('status');
		$this->zbxTestAssertElementPresentXpath('//input[@type="checkbox" and @id="status"]');
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));

		if (array_key_exists('evaltype', $data)) {
			// Open Condition overlay dialog and fill first condition.
			$this->zbxTestClickXpath('//button[text()="Add" and contains(@class, "condition-create")]');
			$this->zbxTestLaunchOverlayDialog('New condition');
			$this->zbxTestInputTypeByXpath('//textarea[@id="value"]', 'TEST1');
			$this->query('xpath://div[@data-dialogueid="action-condition"]//button[text()="Add"]')->one()->click()->waitUntilNotVisible();
			$this->zbxTestAssertVisibleXpath('//*[@id="conditionTable"]//tr[@data-row_index="0"]');
			// Open Condition overlay dialog again and fill second condition.
			$this->zbxTestClickXpath('//button[text()="Add" and contains(@class, "condition-create")]');
			$this->zbxTestLaunchOverlayDialog('New condition');
			$this->zbxTestInputTypeByXpath('//textarea[@id="value"]', 'TEST2');
			$this->query('xpath://div[@data-dialogueid="action-condition"]//button[text()="Add"]')->one()->click()->waitUntilNotVisible();
			// Wait until overlay is closed and value is added, so that Type of calculation dropdown is clickable.
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('evaltype'));
			$this->zbxTestDropdownSelectWait('evaltype', $data['evaltype']);
			$evaltype = $data['evaltype'];
		}

		if ($eventsource == EVENT_SOURCE_TRIGGERS && array_key_exists('evaltype', $data)) {
			$this->zbxTestTextPresent('Type of calculation');
			$this->zbxTestAssertElementPresentId('evaltype');
			$this->zbxTestDropdownHasOptions('evaltype', [
					'And/Or',
					'And',
					'Or',
					'Custom expression'
			]);
			$this->zbxTestDropdownAssertSelected('evaltype', $evaltype);
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

		if ($eventsource == EVENT_SOURCE_TRIGGERS && array_key_exists('evaltype', $data)) {
			$this->zbxTestAssertElementText('//tr[@data-row_index="0"]//td[@class="wordwrap"]', 'Event name contains TEST1');
			$this->zbxTestAssertElementText('//tr[@data-row_index="1"]//td[@class="wordwrap"]', 'Event name contains TEST2');
			$this->zbxTestAssertElementPresentXpath('//tr[@data-row_index="0"]//button[@type="button" and text()="Remove"]');
			$this->zbxTestAssertElementPresentXpath('//tr[@data-row_index="1"]//button[@type="button" and text()="Remove"]');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//tr[@id="conditions_0"]');
			$this->zbxTestAssertElementNotPresentXpath('//button[@name="remove" and @onclick="removeCondition(0);"]');
			$this->zbxTestAssertElementNotPresentXpath('//button[@name="remove" and @onclick="removeCondition(1);"]');
		}

		// Open Condition overlay dialog.
		$this->zbxTestClickXpath('//button[text()="Add" and contains(@class, "condition-create")]');
		$this->zbxTestLaunchOverlayDialog('New condition');
		COverlayDialogElement::find()->one()->waitUntilReady();

		if (isset($data['new_condition_conditiontype'])) {
			$this->zbxTestDropdownSelectWait('condition_type', $data['new_condition_conditiontype']);
			COverlayDialogElement::find()->one()->waitUntilReady();
		}
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('condition-type'));
		$new_condition_conditiontype = $this->zbxTestGetSelectedLabel('condition_type');

		switch ($eventsource) {
			case EVENT_SOURCE_TRIGGERS:
				$this->zbxTestDropdownHasOptions('condition_type', [
						'Tag name',
						'Tag value',
						'Host group',
						'Template',
						'Host',
						'Trigger',
						'Event name',
						'Trigger severity',
						'Time period',
						'Problem is suppressed'
				]);
				break;
			case EVENT_SOURCE_SERVICE:
				$this->zbxTestDropdownHasOptions('condition_type', [
						'Service',
						'Service name',
						'Service tag name',
						'Service tag value'
				]);
				break;
			case EVENT_SOURCE_DISCOVERY:
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
			case EVENT_SOURCE_AUTOREGISTRATION:
				$this->zbxTestDropdownHasOptions('condition_type', [
						'Host name',
						'Proxy',
						'Host metadata'
				]);
				break;
			case EVENT_SOURCE_INTERNAL:
				$this->zbxTestDropdownHasOptions('condition_type', [
						'Tag name',
						'Tag value',
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

		switch ($new_condition_conditiontype) {
			case 'Tag name':
			case 'Tag value':
			case 'Service tag name':
			case 'Service tag value':
				$this->zbxTestTextPresent([
					'equals',
					'does not equal',
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
			case 'Service':
			case 'Discovery rule':
			case 'Discovery check':
			case 'Proxy':
				$this->zbxTestTextPresent([
					'equals',
					'does not equal'
				]);
				break;
			case 'Event name':
			case 'Service name':
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
			case 'Tag name':
			case 'Tag value':
			case 'Service tag name':
			case 'Service tag value':
			case 'Service name':
			case 'Event name':
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
			case 'Tag name':
			case 'Tag value':
			case 'Event name':
			case 'Service tag name':
			case 'Service tag value':
			case 'Service name':
			case 'Time period':
			case 'Host IP':
			case 'Received value':
			case 'Host name':
			case 'Host metadata':
			case 'Service port':
				$this->zbxTestAssertAttribute('//textarea[@id="value"] | //input[@id="value"]', 'maxlength', 255);
				break;
			case 'Uptime/Downtime':
				$this->zbxTestAssertAttribute('//input[@id="value"]', 'maxlength', 7);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Tag name':
			case 'Tag value':
			case 'Event name':
			case 'Service tag name':
			case 'Service tag value':
			case 'Service name':
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
			case 'Service':
			case 'Template':
			case 'Host':
			case 'Trigger':
			case 'Discovery rule':
			case 'Proxy':
				$this->zbxTestAssertElementPresentXpath('//input[@placeholder="type here to search"]');
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
				$this->zbxTestAssertElementPresentXpath('//z-select[@name="value"]');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//ul[@id="value" and contains(@class, "radio")]');
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
						'Trigger in "unknown" state'
				]);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Trigger severity':
				$this->zbxTestAssertElementPresentXpath('//label[text()="Not classified"]/../input[@checked]');
				break;
			case 'Event type':
				$this->zbxTestDropdownAssertSelected('value', 'Item in "not supported" state');
				break;
		}

		$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'drule\']');

		switch ($new_condition_conditiontype) {
			case 'Discovery check':
				$this->zbxTestAssertElementPresentXpath('//input[@id=\'dcheck\']');
				$this->zbxTestAssertAttribute('//input[@id=\'dcheck\']', 'maxlength', 255);
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
		$this->zbxTestClickXpath("//div[@data-dialogueid='action-condition']//button[text()='Cancel']");

		$this->zbxTestTabSwitch('Operations');

		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$operations_field = $form->getField('Operations')->asTable();

		switch ($eventsource) {
			case EVENT_SOURCE_TRIGGERS:
			case EVENT_SOURCE_SERVICE:
				$this->assertEquals('1h', $form->getField('Default operation step duration')->getValue());
				$this->zbxTestAssertVisibleId('esc_period');
				$this->zbxTestAssertAttribute('//input[@id=\'esc_period\']', 'maxlength', 255);
				$this->assertEquals($operations_field->getHeadersText(), ['Steps', 'Details', 'Start in', 'Duration', 'Actions']);

				$checkboxes = [
					'Pause operations for suppressed problems' => 'id:pause_suppressed',
					'Notify about canceled escalations' => 'id:notify_if_canceled'
				];
				foreach ($checkboxes as $label => $locator) {
					if ($eventsource === EVENT_SOURCE_TRIGGERS) {
						$this->assertTrue($form->getField($label)->getValue());
					}
					else {
						$this->assertFalse($form->query($locator)->one(false)->isValid());
					}
				}

				$recovery_field = $form->getField('Recovery operations')->asTable();
				$this->assertEquals($recovery_field->getHeadersText(), ['Details', 'Actions']);
				$update_field = $form->getField('Update operations')->asTable();
				$this->assertEquals($update_field->getHeadersText(), ['Details', 'Actions']);
				break;

			case EVENT_SOURCE_DISCOVERY:
			case EVENT_SOURCE_AUTOREGISTRATION:
				$this->zbxTestTextNotPresent(['Default operation step duration', 'Pause operations for suppressed problems',
					'Notify about canceled escalations', 'Recovery operations', 'Update operations']);
				$this->zbxTestAssertElementNotPresentId('esc_period');
				$this->zbxTestAssertElementNotPresentId('pause_suppressed');
				break;

			case EVENT_SOURCE_INTERNAL:
				$this->assertEquals('1h', $form->getField('Default operation step duration')->getValue());
				$this->zbxTestAssertVisibleId('esc_period');
				$this->zbxTestAssertAttribute('//input[@id=\'esc_period\']', 'maxlength', 255);

				$this->assertEquals($operations_field->getHeadersText(), ['Steps', 'Details', 'Start in', 'Duration', 'Actions']);
				$recovery_field = $form->getField('Recovery operations')->asTable();
				$this->assertEquals($recovery_field->getHeadersText(), ['Details', 'Actions']);
				$this->zbxTestTextNotPresent(['Pause operations for suppressed problems', 'Notify about canceled escalations',
					'Update operations']
				);
				$this->zbxTestAssertElementNotPresentId('pause_suppressed');
				break;
		}

		if (isset($data['new_operation_operationtype'])) {
			$new_operation_operationtype = $data['new_operation_operationtype'];
			$operations_field->query('button:Add')->one()->click();
			COverlayDialogElement::find()->one()->waitUntilReady();

			if ($eventsource === EVENT_SOURCE_INTERNAL) {
				$this->zbxTestTextPresent('Send message');
			}
			else {
				$this->zbxTestWaitUntilElementPresent(webDriverBy::id('operationtype'));
				$this->zbxTestDropdownSelectWait('operation-type-select', $new_operation_operationtype);
				COverlayDialogElement::find()->one()->waitUntilReady();
			}
		}
		else {
			$new_operation_operationtype = null;
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
			$this->query('class:operation-condition-list-footer')->one()->click(true);
			$this->page->query('xpath://div[contains(@class, "overlay-dialogue modal")][2]')
					->asOverlayDialog()->waitUntilReady();
			$add_opcondition = $data['add_opcondition'];
		}
		else {
			$add_opcondition = null;
		}

		if ($eventsource === EVENT_SOURCE_SERVICE) {
			$this->assertFalse($this->query('id:operation-condition-list')->one(false)->isValid());
		}

		if ($new_operation_operationtype != null
				&& in_array($eventsource, [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_SERVICE, EVENT_SOURCE_INTERNAL])) {
			switch ($new_operation_operationtype) {
				case 'Send message':
				case 'Reboot':
					$this->zbxTestTextPresent('Steps');
					COverlayDialogElement::find()->one()->waitUntilReady();
					$this->zbxTestAssertVisibleId('operation_esc_step_from');
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_step_from\']', 'maxlength', 5);
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_step_from\']', 'value', 1);

					$this->zbxTestTextPresent('(0 - infinitely)');
					$this->zbxTestAssertVisibleId('operation_esc_step_to');
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_step_to\']', 'maxlength', 5);
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_step_to\']', 'value', 1);

					$this->zbxTestTextPresent(['Step duration', '(0 - use action default)']);
					$this->zbxTestAssertVisibleId('operation_esc_period');
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_period\']', 'maxlength', 255);
					$this->zbxTestAssertAttribute('//input[@id=\'operation_esc_period\']', 'value', 0);
					break;
				}
			}
			else {
				$this->zbxTestAssertElementNotPresentId('operation_esc_step_from');
				$this->zbxTestAssertElementNotPresentId('operation_esc_step_to');
				$this->zbxTestAssertElementNotPresentId('operation_esc_period');
			}

		if (isset($data['new_operation_operationtype']) && $eventsource != EVENT_SOURCE_INTERNAL) {
			$this->zbxTestTextPresent('Operations');
			$this->zbxTestAssertVisibleXpath('//z-select[@id=\'operation-type-select\']');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//z-select[@id=\'operation-type-select\']');
		}

		if (isset($data['check_operationtype']) && $eventsource === EVENT_SOURCE_INTERNAL) {
			$this->assertFalse($form->query('id:operation-opmessage-subject')->one(false)->isValid());
			$this->zbxTestAssertVisibleXpath('//div[contains(@id, "operation-type")]/label[text()="Send message"]');
		}
		elseif (isset($data['check_operationtype'])) {
			$options = $this->query('id:operation-type-select')->asDropdown()->one();
			switch ($eventsource) {
				case EVENT_SOURCE_TRIGGERS:
				case EVENT_SOURCE_SERVICE:
					$this->assertEquals($options->getOptions()->asText(), ['Send message', 'Reboot', 'Selenium script']);
					break;

				case EVENT_SOURCE_DISCOVERY:
				case EVENT_SOURCE_AUTOREGISTRATION:
					$this->assertEquals($options->getOptions()->asText(), [
							'Send message',
							'Add host',
							'Remove host',
							'Add to host group',
							'Remove from host group',
							'Link template',
							'Unlink template',
							'Add host tags',
							'Remove host tags',
							'Enable host',
							'Disable host',
							'Set host inventory mode',
							'Reboot',
							'Selenium script'
					]);
					break;
			}
			$this->assertEquals($new_operation_operationtype, $options->getValue());
		}

		if ($new_operation_operationtype === 'Reboot' && $eventsource !== EVENT_SOURCE_SERVICE) {
			$this->zbxTestTextPresent(['Target list', 'Current host', 'Host', 'Host group']);
			$operation_details = $this->query('id:popup-operation')->asForm()->one();
			$this->assertTrue($operation_details->query('id:operation_opcommand_hst__hostid')->one()->isVisible());
			$this->assertTrue($operation_details->query('id:operation_opcommand_grp__groupid')->one()->isVisible());
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
			$this->zbxTestAssertVisibleXpath('//div[@id="operation-message-user-groups"]//button');
			$this->zbxTestAssertElementText('//div[@id="operation-message-user-groups"]//button', 'Select');
			$this->zbxTestAssertVisibleXpath('//div[@id="operation-message-users"]//button');
			$this->zbxTestAssertElementText('//div[@id="operation-message-users"]//button', 'Select');

			$this->zbxTestTextPresent('Send to media type');
			$this->zbxTestAssertVisibleId('operation-message-mediatype-only-label');
			$this->zbxTestDropdownAssertSelected('operation[opmessage][mediatypeid]', 'All available');
			$this->zbxTestDropdownHasOptions('operation[opmessage][mediatypeid]', [
					'All available',
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
		}

		switch ($new_operation_opmessage_custom_msg) {
			case 'unchecked':
				$operation_details = $this->query('id:popup-operation')->asForm()->one();
				$this->assertFalse($operation_details->query('id:operation-opmessage-subject')->one()->isVisible());
				$this->assertFalse($operation_details->query('id:operation_opmessage_message')->one()->isVisible());
				break;
			case 'checked':
				$operation_details = $this->query('id:popup-operation')->asForm()->one();
				$this->zbxTestTextPresent('Subject');
				$this->assertTrue($operation_details->query('id:operation-opmessage-subject')->one()->isVisible());
				$this->assertEquals(255, $operation_details->getField('id:operation-opmessage-subject')->getAttribute('maxlength'));

				$this->zbxTestTextPresent('Message');
				$this->assertTrue($operation_details->query('id:operation_opmessage_message')->one()->isVisible());
				$this->assertEquals(7, $operation_details->getField('id:operation_opmessage_message')->getAttribute('rows'));
				break;
			default:
				$this->zbxTestAssertElementNotPresentId('operation_message_subject');
				$this->zbxTestAssertElementNotPresentId('operation_message_message');
				break;
		}

		if ($eventsource == EVENT_SOURCE_TRIGGERS && $new_operation_operationtype != null) {
			$this->zbxTestTextPresent(['Conditions', 'Label', 'Name', 'Actions']);

			if ($add_opcondition == null) {
				$this->zbxTestAssertVisibleXpath('//button[@class="js-add"]');
			}
			else {
				$this->zbxTestTextPresent('New condition');
				$this->query('xpath://div[contains(@class, "overlay-dialogue modal")][2]'.
						'//button[text()="Cancel"]')->one()->waitUntilVisible();

				$this->zbxTestAssertVisibleXpath('//z-select[@id="condition-type"]');
				$this->zbxTestDropdownAssertSelected('condition_type', 'Event acknowledged');
				$this->zbxTestDropdownHasOptions('condition_type', ['Event acknowledged']);

				$this->zbxTestAssertVisibleXpath('//div[contains(@class, "overlay-dialogue modal")]'.
						'//label[text()="equals"]');
				$this->zbxTestAssertVisibleXpath('//div[contains(@class, "overlay-dialogue modal")]'.
						'//ul[@id="value" and @class="radio-list-control"]');
				$this->zbxTestAssertElementPresentXpath('//label[text()="No"]/../input[@checked]');
				$this->zbxTestClickXpathWait('//div[@data-dialogueid="operation-condition"]//'.
						'button[contains(@type,"button") and (text()="Add")]');
				$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath('//div[@data-dialogueid="operation-condition"]'));
			}
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//li[@id="operation-condition-list"]');
			$this->zbxTestAssertElementNotPresentXpath('//tr[@id="operation-condition-list-footer"]');
		}

		switch ($new_operation_operationtype) {
			case 'Add to host group':
			case 'Remove from host group':
				$this->zbxTestAssertElementPresentXpath('//div[@id=\'operation_opgroup__groupid\']/input');
				$this->zbxTestAssertNotVisibleXpath('//div[@id=\'operation_optemplate__templateid\']/input');
				break;
			case 'Link template':
			case 'Unlink template':
				$this->zbxTestAssertElementPresentXpath('//div[@id=\'operation_optemplate__templateid\']/input');
				$this->zbxTestAssertNotVisibleXpath('//div[@id=\'operation_opgroup__groupid\']/input');
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

		$this->zbxTestAssertVisibleXpath('//button[@class="js-add"]');
		$this->zbxTestAssertVisibleXpath('//button[@class="btn-alt js-cancel"]');

		COverlayDialogElement::closeAll();
	}

	/*
	 * Function that checks possible operation types and custom message related fields for recovery and update operations.
	 */
	private function checkRecoveryUpdateOperations($operation_field, $eventsource) {
		$operation_field->query('button:Add')->one()->click();
		COverlayDialogElement::find()->waitUntilReady()->one();
		$operation_details = $this->query('id:popup-operation')->asForm()->one();
		// Check available operation types depending on event source and the selected operation type.
		$message_types = ($eventsource === EVENT_SOURCE_INTERNAL)
			? ['Send message', 'Notify all involved']
			: ['Send message', 'Notify all involved', 'Reboot', 'Selenium script'];
		$this->assertEquals($message_types, $operation_details->query('id:operation-type-select')
				->asDropdown()->one()->getOptions()->asText());
		$this->assertEquals('Send message', $operation_details->getField('Operation')->getValue());
		// Make sure that Custom message is unchecked and that message related fields are not visible.
		$this->assertFalse($operation_details->getField('Custom message')->getValue());
		$this->zbxTestTextNotVisible(['Subject','Message']);
		// Set the Custom message option and check Subject and Message fields.
		$operation_details->getField('Custom message')->set(true);
		$this->assertEquals(255, $operation_details->getField('id:operation-opmessage-subject')->waitUntilVisible()->getAttribute('maxlength'));
		$this->assertEquals(65535, $operation_details->getField('id:operation_opmessage_message')->waitUntilVisible()->getAttribute('maxlength'));
		$this->zbxTestClickXpath("//div[@class='overlay-dialogue modal modal-popup modal-popup-medium undefined']//button[@title='Close']");
	}

	public static function update() {
		return CDBHelper::getDataProvider('SELECT name, eventsource FROM actions');
	}

	public static function updateServiceAction() {
		return [
			[
				[
						'name' => self::SERVICE_ACTION,
						'eventsource' => '4'
				]
			]
		];
	}

	/**
	 * @dataProvider update
	 * @dataProvider updateServiceAction
	 */
	public function testFormAction_SimpleUpdate($data) {
		$name = $data['name'];
		$eventsource = $data['eventsource'];

		if ($name == 'Auto discovery. Linux servers.') {
			$sqlActions = 'SELECT actionid, name, eventsource, evaltype, status FROM actions ORDER BY actionid';
		}
		else {
			$sqlActions = 'SELECT * FROM actions ORDER BY actionid';
		}
		$oldHashActions = CDBHelper::getHash($sqlActions);

		$this->page->login()->open('zabbix.php?action=action.list&eventsource='.$eventsource);
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickButtonText('Update');
		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Action updated');
		$this->zbxTestCheckHeader($this->event_sources[$data['eventsource']]);
		$this->zbxTestTextPresent([
				'Action updated',
				'Actions',
				$name
		]);

		$this->assertEquals($oldHashActions, CDBHelper::getHash($sqlActions));
	}

	public static function create() {
		return [
			[
				[
					'expected' => ACTION_GOOD,
					'eventsource' => EVENT_SOURCE_TRIGGERS,
					'name' => 'TestFormAction Triggers 001',
					'esc_period' => '123',
					'conditions' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Event name'),
							'Value' => 'trigger'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('Trigger severity'),
							'Severity' => 'Warning'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('Tag name'),
							'Operator' => 'does not contain',
							'Tag' => 'Does not contain Tag'
						]
					],
					'expected conditions' => [
						'A' => 'Event name contains trigger',
						'B' => 'Trigger severity equals Warning',
						'C' => 'Tag name does not contain Does not contain Tag'
					],
					'operations' => [
						[
							'type' => 'Send message',
							'media' => 'Email'
						],
						[
							'type' => 'Reboot'
						]
					]
				]
			],
			[
				[
					'expected' => ACTION_BAD,
					'eventsource' => EVENT_SOURCE_TRIGGERS,
					'name' => '',
					'esc_period' => '123',
					'errors' => [
						'Incorrect value for field "name": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => ACTION_GOOD,
					'eventsource' => EVENT_SOURCE_DISCOVERY,
					'name' => 'TestFormAction Discovery 001',
					'conditions' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Service type'),
							'Service type' => 'FTP'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('Received value'),
							'Operator' => 'does not contain',
							'Value' => 'Received value'
						]
					],
					'expected conditions' => [
						'A' => 'Received value does not contain Received value',
						'B' => 'Service type equals FTP'
					],
					'operations' => [
						[
							'type' => 'Send message',
							'media' => 'Email'
						],
						[
							'type' => 'Reboot'
						]
					]
				]
			],
			[
				[
					'expected' => ACTION_BAD,
					'eventsource' => EVENT_SOURCE_DISCOVERY,
					'name' => '',
					'errors' => [
						'Incorrect value for field "name": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => ACTION_GOOD,
					'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
					'name' => 'TestFormAction Autoregistration 001',
					'conditions' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Host name'),
							'Value' => 'Zabbix'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('Host metadata'),
							'Operator'=> 'does not contain',
							'Value' => 'Zabbix'
						]
					],
					'expected conditions' => [
						'A' => 'Host name contains Zabbix',
						'B' => 'Host metadata does not contain Zabbix'
					],
					'operations' => [
						[
							'type' => 'Send message',
							'media' => 'Email'
						],
						[
							'type' => 'Reboot'
						]
					]
				]
			],
			[
				[
					'expected' => ACTION_BAD,
					'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
					'name' => '',
					'errors' => [
						'Incorrect value for field "name": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => ACTION_GOOD,
					'eventsource' => EVENT_SOURCE_INTERNAL,
					'name' => 'TestFormAction Internal 001',
					'esc_period' => '123',
					'conditions' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Event type'),
							'Event type' => 'Trigger in "unknown" state'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('Tag name'),
							'Operator' => 'does not contain',
							'Tag' => 'Does not contain Tag'
						]
					],
					'expected conditions' => [
						'A' => 'Event type equals Trigger in "unknown" state',
						'B' => 'Tag name does not contain Does not contain Tag'
					],
					'operations' => [
						[
							'type' => 'Send message',
							'media' => 'Email'
						]
					],
					'expected operations' => [
						'Send message to users: Admin (Zabbix Administrator) via Email'
					]
				]
			],
			[
				[
					'expected' => ACTION_BAD,
					'eventsource' => EVENT_SOURCE_INTERNAL,
					'name' => '',
					'esc_period' => '123',
					'errors' => [
						'Incorrect value for field "name": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => ACTION_GOOD,
					'eventsource' => EVENT_SOURCE_SERVICE,
					'name' => 'Test Service for Create operation',
					'esc_period' => '666',
					'conditions' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Service'),
							'Operator' => 'does not equal',
							'Services' => 'Reference service'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('Service name'),
							'Value' => 'Part of service name'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('Service tag name'),
							'Operator' => 'contains',
							'Tag' => 'Service tag name'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('Service tag value'),
							'Tag' => 'Service tag',
							'Operator' => 'does not contain',
							'Value' => 'Service tag value'
						]
					],
					'expected conditions' => [
						'A' => 'Service does not equal Reference service',
						'B' => 'Service name contains Part of service name',
						'C' => 'Tag name contains Service tag name',
						'D' => 'Value of tag Service tag does not contain Service tag value'
					],
					'operations' => [
						[
							'type' => 'Send message',
							'user_group' => 'Selenium user group'
						],
						[
							'type' => 'Reboot'
						]
					],
					'expected operations' => [
						'Send message to user groups: Selenium user group via all media',
						'Run script "Reboot" on Zabbix server'
					]
				]
			],
			[
				[
					'expected' => ACTION_BAD,
					'eventsource' => EVENT_SOURCE_SERVICE,
					'name' => '',
					'esc_period' => '666',
					'errors' => [
						'Incorrect value for field "name": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => ACTION_BAD,
					'eventsource' => EVENT_SOURCE_SERVICE,
					'name' => 'No operations action',
					'esc_period' => '666',
					'error_title' => 'Cannot add action',
					'errors' => [
						'No operations defined for action "No operations action".'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormAction_SimpleCreate($data) {
		$this->page->login()->open('zabbix.php?action=action.list&eventsource='.$data['eventsource']);
		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestCheckHeader($this->event_sources[$data['eventsource']]);
		$this->zbxTestClickButtonText('Create action');
		$this->assertEquals('New action', $this->query('tag:h4')->waitUntilVisible()->one()->getText());

		$dialog = $this->query('class:overlay-dialogue-body')->asOverlayDialog()->one()->waitUntilReady();
		$action_form = $dialog->asForm();
		$action_form->getField('id:name')->fill($data['name']);

		if (array_key_exists('conditions', $data)) {
			foreach ($data['conditions'] as $condition) {
				$action_form->query('xpath:.//table[@id="conditionTable"]//button[text()="Add"]')->one()->click();
				COverlayDialogElement::find()->waitUntilReady()->one();
				$condition_form = $this->query('id:popup.condition')->asForm()->one();
				$condition_form->fill($condition);
				$condition_form->submit();
				$condition_form->waitUntilNotVisible();
			}
		}

		if (isset($data['operations'])) {
			$action_form->selectTab('Operations');
			foreach ($data['operations'] as $operation) {
				$action_form->query('xpath://table[@id="op-table"]//button[text()="Add"]')->waitUntilVisible()->one()->click();
				COverlayDialogElement::find()->waitUntilReady()->one();
				$operation_form = $this->query('id:popup-operation')->asForm()->one();

				if ($data['eventsource'] !== EVENT_SOURCE_INTERNAL) {
					$operation_form->getField('Operation')->fill($operation['type']);
				}

				if ($operation['type'] === 'Send message') {
					if (array_key_exists('user_group', $operation)) {
						$field = 'Send to user groups';
						$value = $operation['user_group'];
					}
					else {
						$field = 'Send to users';
						$value = 'Admin';
					}
					$operation_form->getField($field)->query('button:Select')->one()->click();
					$list = COverlayDialogElement::find()->all()->last();
					$list->query('link', $value)->waitUntilClickable()->one()->click();
				}
				elseif ($data['eventsource'] !== EVENT_SOURCE_SERVICE) {
					$operation_form->query('id:operation-command-chst')->asCheckbox()->waitUntilVisible()->one()->check();
				}

				if (array_key_exists('media', $operation)) {
					$operation_form->getField('Send to media type')->fill($operation['media']);
				}

				$operation_form->submit();
				$operation_form->waitUntilNotVisible();
			}

			if (array_key_exists('esc_period', $data)) {
				$action_form->getField('id:esc_period')->fill($data['esc_period']);
			}
		}

		$action_form->submit();
		$sql = 'SELECT actionid FROM actions WHERE name='.zbx_dbstr($data['name']);
		if ($data['expected'] === ACTION_GOOD) {
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Action added');
			$this->assertEquals(1, CDBHelper::getCount($sql), 'Action has not been created in the DB.');

			$this->query('link', $data['name'])->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();

			if (array_key_exists('conditions', $data)) {
				$condition_table = $this->query('id:conditionTable')->waitUntilVisible()->asTable()->one();

				foreach($data['expected conditions'] as $label => $result) {
					$this->assertEquals($result, $condition_table->findRow('Label', $label)->getColumn('Name')->getText());
				}
			}

			if (array_key_exists('operations', $data)) {
				$expected_operations = [
					'Send message to users: Admin (Zabbix Administrator) via Email',
					'Run script "Reboot" on current host'
				];
				if (array_key_exists('expected operations', $data)) {
					$expected_operations = $data['expected operations'];
				}
				$action_form->invalidate();
				$action_form->selectTab('Operations');
				$operations_table = $this->query('id:op-table')->waitUntilVisible()->asTable()->one();

				$saved_operations = [];
				$row_count = count($expected_operations);
				for ($i = 0; $i < $row_count; $i++) {
					$saved_operations[] = $operations_table->getRow($i)->getColumn('Details')->getText();
				}
				$this->assertEquals($expected_operations, $saved_operations);
				$action_form->submit();
				$this->query('xpath://button[@class="btn-overlay-close"]')->waitUntilVisible()->one()->click();
			}
		}
		else {
			$title = CTestArrayHelper::get($data, 'error_title', 'Cannot add action');
			$this->assertMessage(TEST_BAD, $title, $data['errors']);
			$this->query('xpath://output[@aria-label="Error message"]//button[@title="Close"]')->one()->click();
			$this->assertEquals(0, CDBHelper::getCount($sql), 'Action has not been created in the DB.');
			$this->query('xpath://button[text()="Cancel"]')->waitUntilVisible()->one()->click();
		}
	}

	public function testFormAction_Create() {
		$this->zbxTestLogin('zabbix.php?action=action.list&eventsource=0');
		$this->zbxTestCheckTitle('Configuration of actions');
		$this->query('button:Create action')->one()->click()->waitUntilReady();
		$dialog = COverlayDialogElement::find()->waitUntilReady();
		$form = $dialog->asForm()->one();
		$form->getField('id:name')->fill('action test');

		// adding conditions
		$this->zbxTestClickXpathWait('//button[text()="Add" and contains(@class, "condition-create")]');
		$this->zbxTestLaunchOverlayDialog('New condition');
		$this->zbxTestDropdownSelectWait('condition_type', 'Event name');
		$this->zbxTestInputTypeWait('value', 'trigger');
		COverlayDialogElement::find()->waitUntilReady()->one();
		$condition_form = $this->query('id:popup.condition')->asForm()->one();
		$condition_form->submit();
		$this->zbxTestAssertElementText('//tr[@data-row_index="0"]//td[@class="wordwrap"]', 'Event name contains trigger');

		$this->zbxTestClickXpathWait('//button[text()="Add" and contains(@class, "condition-create")]');
		$this->zbxTestLaunchOverlayDialog('New condition');
		$this->zbxTestDropdownSelectWait('condition_type', 'Trigger severity');
		$this->zbxTestClickXpathWait('//label[text()="Average"]');
		$condition_form->submit();
		$this->zbxTestAssertElementText('//tr[@data-row_index="1"]//td[@class="wordwrap"]', 'Trigger severity equals Average');

		$this->zbxTestClickXpathWait('//button[text()="Add" and contains(@class, "condition-create")]');
		$this->zbxTestLaunchOverlayDialog('New condition');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('condition-type'));
		$this->zbxTestDropdownSelectWait('condition_type', 'Tag name');
		$this->zbxTestInputTypeWait('value', 'zabbix');
		$condition_form->submit();
		$this->zbxTestAssertElementText('//tr[@data-row_index="2"]//td[@class="wordwrap"]', 'Tag name equals zabbix');

		// adding operations
		$this->zbxTestTabSwitch('Operations');
		$this->zbxTestClickXpathWait('//table[@id="op-table"]//button[text()="Add"]');

		$this->zbxTestClickXpathWait('//div[@id="operation-message-user-groups"]//button[text()="Select"]');
		$this->zbxTestLaunchOverlayDialog('User groups');
		$this->zbxTestCheckboxSelect('item_7');
		$this->zbxTestCheckboxSelect('item_11');
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		$this->zbxTestClickXpathWait('//div[@id="operation-message-users"]//button[text()="Select"]');
		$this->zbxTestLaunchOverlayDialog('Users');
		$this->zbxTestCheckboxSelect('item_1');
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		$operation_form = $this->query('id:popup-operation')->asForm()->one();
		$operation_form->getField('Send to media type')->select('SMS');
		$operation_form->submit();
		$this->zbxTestAssertElementText("//tr[@id='operations_0']//td[@class='wordbreak']",
			"Send message to users: Admin (Zabbix Administrator) via SMS ".
			"Send message to user groups: Enabled debug mode, Zabbix administrators via SMS");

		$this->zbxTestClickXpathWait('//table[@id="op-table"]//button[text()="Add"]');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]'));
		$operation_form->getField('Operation')->select('Reboot');

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

		$operation_form->submit();

		foreach (['operations_0', 'operations_1'] as $row) {
			$this->query('xpath://tr[@id="'.$row.'"]')->waitUntilVisible();
		}

		$this->zbxTestWaitUntilElementClickable(WebDriverBy::className('js-add'));
		$this->zbxTestAssertElementText("//tr[@id='operations_0']//td[@class='wordbreak']",
			"Send message to users: Admin (Zabbix Administrator) via SMS ".
			"Send message to user groups: Enabled debug mode, Zabbix administrators via SMS");
		$this->zbxTestAssertElementText("//tr[@id='operations_1']//td[@class='wordbreak']",
			"Run script \"Reboot\" on current host ".
			"Run script \"Reboot\" on hosts: Simple form test host ".
			"Run script \"Reboot\" on host groups: Zabbix servers");

		$this->zbxTestClickXpathWait('//table[@id="op-table"]//button[text()="Add"]');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]'));
		$this->zbxTestInputTypeOverwrite('operation_esc_step_to', '2');
		$this->zbxTestDropdownSelectWait('operation-type-select', 'Reboot');
		$this->zbxTestCheckboxSelect('operation-command-chst');

		$operation_form->submit();
		$this->query('xpath://tr[@id="operations_2"]')->waitUntilVisible();
		$this->zbxTestAssertElementText("//tr[@id='operations_0']//td[@class='wordbreak']",
			"Send message to users: Admin (Zabbix Administrator) via SMS ".
			"Send message to user groups: Enabled debug mode, Zabbix administrators via SMS");
		$this->zbxTestAssertElementText("//tr[@id='operations_1']//td[@class='wordbreak']",
			"Run script \"Reboot\" on current host ".
			"Run script \"Reboot\" on hosts: Simple form test host ".
			"Run script \"Reboot\" on host groups: Zabbix servers");
		$this->zbxTestAssertElementText("//tr[@id='operations_2']//td[@class='wordbreak']",
			"Run script \"Reboot\" on current host");
		$this->zbxTestAssertElementText("//tr[@id='operations_2']//td", '1 - 2');
		$form->getField('id:esc_period')->fill('123');

		// Fire onchange event.
		$this->webDriver->executeScript('var event = document.createEvent("HTMLEvents");'.
				'event.initEvent("change", false, true);'.
				'document.getElementById("esc_period").dispatchEvent(event);'
		);

		$this->zbxTestAssertElementValue('esc_period', '123');
		$form->submit()->waitUntilNotVisible();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Action added');

		$sql = "SELECT actionid FROM actions WHERE name='action test'";
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Action has not been created in the DB.');
	}

	public function testFormAction_Clone() {
		$id = CDataHelper::get('Actions.Service action');
		$sql = 'SELECT a.eventsource, a.evaltype, a.status, a.esc_period, a.formula, a.pause_suppressed, '.
				'c.conditiontype, c.operator, c.value, c.value2, '.
				'o.operationtype, o.esc_period, o.esc_step_from, o.esc_step_to, o.evaltype, o.recovery '.
				'FROM actions a '.
				'INNER JOIN conditions c ON c.actionid = a.actionid '.
				'INNER JOIN operations o on o.actionid = c.actionid '.
				'WHERE a.actionid='.zbx_dbstr($id).' ORDER BY o.operationid';

		$original_hash = CDBHelper::getHash($sql);

		$this->page->login()->open('zabbix.php?action=action.list&eventsource=4')->waitUntilReady();
		$this->zbxTestClickXpath('//a[text()="Service action"]');
		$this->zbxTestClickXpathWait('//div[@data-dialogueid="action.edit"]//button[text()="Clone"]');
		$dialog = $this->query('class:overlay-dialogue-body')->asOverlayDialog()->one()->waitUntilReady();
		$form = $dialog->asForm();
		$form->getField('id:name')->fill(self::SERVICE_ACTION.' Clone');
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Action added');
		$this->assertEquals($original_hash, CDBHelper::getHash($sql));
	}
}
