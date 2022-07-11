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


require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

define('ACTION_GOOD', 0);
define('ACTION_BAD', 1);

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup actions, profiles
 *
 * @onBefore prepareServiceActionData
 */
class testFormAction extends CLegacyWebTest {

	private $event_sources = [
		EVENT_SOURCE_TRIGGERS => 'Trigger actions',
		EVENT_SOURCE_SERVICE => 'Service actions',
		EVENT_SOURCE_DISCOVERY => 'Discovery actions',
		EVENT_SOURCE_AUTOREGISTRATION => 'Autoregistration actions',
		EVENT_SOURCE_INTERNAL => 'Internal actions'
	];

	/**
	 * Id of the action to be used for Simple update and Clone scenarios.
	 *
	 * @var integer
	 */
	protected static $actionid;

	/**
	 * Name of the action to be used for Simple update and Clone scenarios.
	 *
	 * @var integer
	 */
	protected static $action_name = 'Service action';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	/**
	 * Function creates Service actions.
	 */
	public function prepareServiceActionData() {
		$response = CDataHelper::call('action.create', [
			[
				'name' => self::$action_name,
				'eventsource' => 4,
				'status' => '0',
				'esc_period' => '1h',
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'conditiontype' => 28,
							'operator' => 2,
							'value' => 'Service name'
						],
						[
							'conditiontype' => 25,
							'operator' => 2,
							'value' => 'Service tag name'
						]
					]
				],
				'operations' => [
					[
						'operationtype' => 0,
						'esc_period' => 0,
						'esc_step_from' => 1,
						'esc_step_to' => 1,
						'opmessage' => [
							'default_msg' => 1,
							'mediatypeid' => 0
						],
						'opmessage_usr' => [
							[
								'userid' => 1
							]
						]
					]
				],
				'recovery_operations' => [
					[
						'operationtype' => 11,
						'opmessage' => [
							'default_msg' => 0,
							'subject' => 'Subject',
							'message' => 'Message'
						]
					]
				],
				'update_operations' => [
					[
						'operationtype' => 0,
						'opmessage' => [
							'default_msg' => 1,
							'mediatypeid' => 1
						],
						'opmessage_grp' => [
							[
								'usrgrpid' => 7
							]
						]
					]
				]
			]
		]);


		$this->assertArrayHasKey('actionids', $response);
		self::$actionid = $response['actionids'][0];

		CDataHelper::call('service.create', [
			[
				'name' => 'Reference service',
				'algorithm' => 1,
				'sortorder' => 1
			]
		]);
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
				['eventsource' => EVENT_SOURCE_TRIGGERS, 'new_condition_conditiontype' => 'Trigger name']
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
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_operation_operationtype' => 'Link to template']
			],
			[
				['eventsource' => EVENT_SOURCE_DISCOVERY, 'new_operation_operationtype' => 'Unlink from template']
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
				['eventsource' => EVENT_SOURCE_AUTOREGISTRATION, 'new_operation_operationtype' => 'Link to template']
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

		$this->zbxTestLogin('actionconf.php?eventsource='.$eventsource.'&form=Create+action');
		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestTextPresent(['Action', 'Operations']);

		$this->zbxTestTextPresent('Name');
		$this->zbxTestAssertVisibleId('name');
		$this->zbxTestAssertAttribute('//input[@id="name"]', 'maxlength', 255);
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
						'Trigger name',
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
			case 'Trigger name':
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
			case 'Tag name':
			case 'Tag value':
			case 'Trigger name':
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
				$this->zbxTestAssertAttribute('//input[@id="value"]', 'maxlength', 15);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Tag name':
			case 'Tag value':
			case 'Trigger name':
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
		$this->zbxTestClickXpath("//div[@class='overlay-dialogue-footer']//button[text()='Cancel']");

		$this->zbxTestTabSwitch('Operations');

		$form = $this->query('id:action-form')->asForm()->waitUntilVisible()->one();
		$operations_field = $form->getField('Operations')->asTable();

		switch ($eventsource) {
			case EVENT_SOURCE_TRIGGERS:
			case EVENT_SOURCE_SERVICE:
				$this->assertEquals('1h', $form->getField('Default operation step duration')->getValue());
				$this->zbxTestAssertVisibleId('esc_period');
				$this->zbxTestAssertAttribute('//input[@id=\'esc_period\']', 'maxlength', 255);

				$this->assertEquals($operations_field->getHeadersText(), ['Steps', 'Details', 'Start in', 'Duration', 'Action']);

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
				$this->assertEquals($recovery_field->getHeadersText(), ['Details', 'Action']);
				$update_field = $form->getField('Update operations')->asTable();
				$this->assertEquals($update_field->getHeadersText(), ['Details', 'Action']);
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

				$this->assertEquals($operations_field->getHeadersText(), ['Steps', 'Details', 'Start in', 'Duration', 'Action']);
				$recovery_field = $form->getField('Recovery operations')->asTable();
				$this->assertEquals($recovery_field->getHeadersText(), ['Details', 'Action']);
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
			$this->query('xpath://tr[@id="operation-condition-list-footer"]//button[text()="Add"]')->one()->click(true);
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
			$this->assertFalse($this->query('id:operation-type-select')->one(false)->isValid());
			$this->assertTrue($this->query('xpath://label[text()="Operation"]/../../div[text()="Send message"]')->one()->isValid());
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
							'Link to template',
							'Unlink from template',
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

				$this->zbxTestTextPresent('Message');
				$this->zbxTestAssertVisibleId('operation_opmessage_message');
				$this->zbxTestAssertAttribute('//textarea[@id=\'operation_opmessage_message\']', 'rows', 7);
				break;
			default:
				$this->zbxTestAssertElementNotPresentId('operation_opmessage_subject');
				$this->zbxTestAssertElementNotPresentId('operation_opmessage_message');
				break;
		}

		if ($eventsource == EVENT_SOURCE_TRIGGERS && $new_operation_operationtype != null) {
			$this->zbxTestTextPresent(['Conditions', 'Label', 'Name', 'Action']);

			if ($add_opcondition == null) {
				$this->zbxTestAssertVisibleXpath('//div[@id="operationTab"]//button[text()="Add"]');
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
				$this->zbxTestClickXpathWait('//div[contains(@class, "overlay-dialogue modal")][2]'.
						'//button[text()="Add"]');
				$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath('//div[contains(@class, "overlay-dialogue '.
						'modal")][2]'));
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
		$this->assertEquals(255, $operation_details->getField('Subject')->waitUntilVisible()->getAttribute('maxlength'));
		$this->assertFalse($operation_details->getField('Message')->isAttributePresent('maxlength'));
		COverlayDialogElement::find()->one()->close();
	}

	public static function update() {
		return CDBHelper::getDataProvider('SELECT name, eventsource FROM actions');
	}

	public static function updateServiceAction() {
		return [
			[
				[
						'name' => self::$action_name,
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

		$this->page->login()->open('actionconf.php?eventsource='.$eventsource);
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
			[
				[
					'expected' => ACTION_GOOD,
					'eventsource' => EVENT_SOURCE_TRIGGERS,
					'name' => 'TestFormAction Triggers 001',
					'esc_period' => '123',
					'conditions' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Trigger name'),
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
						'A' => 'Tag name does not contain Does not contain Tag',
						'B' => 'Trigger severity equals Warning',
						'C' => 'Trigger name contains trigger'
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
						'Incorrect value for field "Name": cannot be empty.'
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
						'Incorrect value for field "Name": cannot be empty.'
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
						'A' => 'Host metadata does not contain Zabbix',
						'B' => 'Host name contains Zabbix'
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
						'Incorrect value for field "Name": cannot be empty.'
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
						'A' => 'Tag name does not contain Does not contain Tag',
						'B' => 'Event type equals Trigger in "unknown" state'
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
						'Incorrect value for field "Name": cannot be empty.'
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
						'A' => 'Service name contains Part of service name',
						'B' => 'Service does not equal Reference service',
						'C' => 'Value of tag Service tag does not contain Service tag value',
						'D' => 'Tag name contains Service tag name'
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
						'Incorrect value for field "Name": cannot be empty.'
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
		$this->zbxTestLogin('actionconf.php?form=1&eventsource='.$data['eventsource']);
		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestCheckHeader('Actions');

		$action_form = $this->query('id:action-form')->asForm()->one();
		$action_form->getField('Name')->fill($data['name']);

		if (array_key_exists('conditions', $data)) {
			foreach ($data['conditions'] as $condition) {
				$action_form->query('xpath:.//table[@id="conditionTable"]//button[text()="Add"]')->one()->click();

				COverlayDialogElement::find()->waitUntilReady()->one();
				$condition_form = $this->query('id:popup.condition')->asForm()->one();
				$condition_form->fill($condition);
				$condition_form->submit();
				COverlayDialogElement::ensureNotPresent();
			}
		}

		if (isset($data['operations'])) {
			$action_form->selectTab('Operations');

			foreach ($data['operations'] as $operation) {
				$action_form->query('xpath:.//table[@id="op-table"]//button[text()="Add"]')->one()->click();

				COverlayDialogElement::find()->waitUntilReady()->one();
				$operation_form = $this->query('id:popup.operation')->asForm()->one();

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
					$operation_form->getField($field)->query('button:Add')->one()->click();
					$list = COverlayDialogElement::find()->all()->last();
					$list->query('link', $value)->waitUntilClickable()->one()->click();
				}
				elseif ($data['eventsource'] !== EVENT_SOURCE_SERVICE) {
					$operation_form->query('id:operation-command-chst')->asCheckbox()->waitUntilVisible()->one()->check();
				}

				if (array_key_exists('media', $operation)) {
					$operation_form->getField('Send only to')->fill($operation['media']);
				}

				$operation_form->submit();
				COverlayDialogElement::ensureNotPresent();
			}

			if (array_key_exists('esc_period', $data)) {
				$action_form->getField('Default operation step duration')->fill($data['esc_period']);
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
			}
		}
		else {
			$title = CTestArrayHelper::get($data, 'error_title', 'Page received incorrect data');
			$this->assertMessage(TEST_BAD, $title, $data['errors']);
			$this->assertEquals(0, CDBHelper::getCount($sql), 'Action has not been created in the DB.');
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
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('condition-type'));
		$this->zbxTestDropdownSelectWait('condition_type', 'Tag name');
		$this->zbxTestInputTypeWait('value', 'zabbix');
		$this->zbxTestClickXpath("//div[@class='overlay-dialogue-footer']//button[text()='Add']");
		$this->zbxTestAssertElementText("//tr[@id='conditions_2']/td[2]", 'Tag name equals zabbix');

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
		$this->zbxTestDropdownSelectWait('operation-type-select', 'Reboot');

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

		$this->zbxTestClickXpathWait('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');
		COverlayDialogElement::ensureNotPresent();
		$this->page->waitUntilReady();
		$this->zbxTestWaitUntilElementClickable(WebDriverBy::id('add'));
		$this->zbxTestAssertElementText("//tr[@id='operations_0']//span",
			"Send message to users: Admin (Zabbix Administrator) via SMS ".
			"Send message to user groups: Enabled debug mode, Zabbix administrators via SMS");
		$this->zbxTestAssertElementText("//tr[@id='operations_1']//span",
			"Run script \"Reboot\" on current host ".
			"Run script \"Reboot\" on hosts: Simple form test host ".
			"Run script \"Reboot\" on host groups: Zabbix servers");

		$this->zbxTestClickXpathWait('//div[@id="operationTab"]//button[text()="Add"]');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]'));
		$this->zbxTestInputTypeOverwrite('operation_esc_step_to', '2');
		$this->zbxTestDropdownSelectWait('operation-type-select', 'Reboot');
		$this->zbxTestCheckboxSelect('operation-command-chst');

		$this->zbxTestClickXpathWait('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');
		$this->page->waitUntilReady();
		$this->zbxTestAssertElementText("//tr[@id='operations_0']//span",
			"Send message to users: Admin (Zabbix Administrator) via SMS ".
			"Send message to user groups: Enabled debug mode, Zabbix administrators via SMS");
		$this->zbxTestAssertElementText("//tr[@id='operations_1']//span",
			"Run script \"Reboot\" on current host ".
			"Run script \"Reboot\" on hosts: Simple form test host ".
			"Run script \"Reboot\" on host groups: Zabbix servers");
		$this->zbxTestAssertElementText("//tr[@id='operations_2']//span",
			"Run script \"Reboot\" on current host");
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

	public function testFormAction_Clone() {
		$id = self::$actionid;
		$sql = 'SELECT a.eventsource, a.evaltype, a.status, a.esc_period, a.formula, a.pause_suppressed, '.
				'c.conditiontype, c.operator, c.value, c.value2, '.
				'o.operationtype, o.esc_period, o.esc_step_from, o.esc_step_to, o.evaltype, o.recovery '.
				'FROM actions a '.
				'INNER JOIN conditions c ON c.actionid = a.actionid '.
				'INNER JOIN operations o on o.actionid = c.actionid '.
				'WHERE a.actionid='.zbx_dbstr($id).' ORDER BY o.operationid';

		$original_hash = CDBHelper::getHash($sql);

		$this->page->login()->open('actionconf.php?eventsource=4&form=update&actionid='.$id)->waitUntilReady();
		$this->query('button:Clone')->waitUntilClickable()->one()->click();

		$form = $this->query('id:action-form')->asForm()->one();
		$form->getField('Name')->fill(self::$action_name.' Clone');
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Action added');

		$id = CDBHelper::getValue('SELECT actionid FROM actions WHERE name='.zbx_dbstr(self::$action_name.' Clone'));
		$this->assertEquals($original_hash, CDBHelper::getHash($sql));
	}
}
