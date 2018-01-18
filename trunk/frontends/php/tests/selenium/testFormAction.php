<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

define('ACTION_GOOD', 0);
define('ACTION_BAD', 1);

/**
 * @backup actions
 */
class testFormAction extends CWebTest {

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
					'new_operation_opmessage_default_msg' => 'unchecked'
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
					'opCmdTarget' => 'Current host'
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Remote command',
					'opCmdTarget' => 'Host'
				]
			],
			[
				[
					'eventsource' => 'Triggers',
					'new_operation_operationtype' => 'Remote command',
					'opCmdTarget' => 'Host group'
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
				['eventsource' => 'Triggers', 'new_condition_conditiontype' => 'Maintenance status']
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
					'new_operation_opmessage_default_msg' => 'unchecked'
				]
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Remote command',
					'opCmdTarget' => 'Current host'
				]
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Remote command',
					'opCmdTarget' => 'Host'
				]
			],
			[
				[
					'eventsource' => 'Discovery',
					'new_operation_operationtype' => 'Remote command',
					'opCmdTarget' => 'Host group'
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
				['eventsource' => 'Auto registration']
			],
			[
				['eventsource' => 'Auto registration', 'new_condition_conditiontype' => 'Host name']
			],
			[
				['eventsource' => 'Auto registration', 'new_condition_conditiontype' => 'Proxy']
			],
			[
				['eventsource' => 'Auto registration', 'new_condition_conditiontype' => 'Host metadata']
			],
			[
				[
					'eventsource' => 'Auto registration',
					'new_operation_operationtype' => 'Send message'
				]
			],
			[
				[
					'eventsource' => 'Auto registration',
					'new_operation_operationtype' => 'Send message',
					'new_operation_opmessage_default_msg' => 'unchecked'
				]
			],
			[
				[
					'eventsource' => 'Auto registration',
					'new_operation_operationtype' => 'Remote command',
					'opCmdTarget' => 'Current host'
				]
			],
			[
				[
					'eventsource' => 'Auto registration',
					'new_operation_operationtype' => 'Remote command',
					'opCmdTarget' => 'Host'
				]
			],
			[
				[
					'eventsource' => 'Auto registration',
					'new_operation_operationtype' => 'Remote command',
					'opCmdTarget' => 'Host group'
				]
			],
			[
				[
					'eventsource' => 'Auto registration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Custom script'
				]
			],
			[
				[
					'eventsource' => 'Auto registration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'IPMI'
				]
			],
			[
				[
					'eventsource' => 'Auto registration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'SSH'
				]
			],
			[
				[
					'eventsource' => 'Auto registration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'SSH',
					'new_operation_opcommand_authtype' => 'Public key'
				]
			],
			[
				[
					'eventsource' => 'Auto registration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Telnet'
				]
			],
			[
				[
					'eventsource' => 'Auto registration',
					'new_operation_operationtype' => 'Remote command',
					'new_operation_opcommand_type' => 'Global script'
				]
			],
			[
				['eventsource' => 'Auto registration', 'new_operation_operationtype' => 'Add host']
			],
			[
				['eventsource' => 'Auto registration', 'new_operation_operationtype' => 'Add to host group']
			],
			[
				['eventsource' => 'Auto registration', 'new_operation_operationtype' => 'Link to template']
			],
			[
				['eventsource' => 'Auto registration', 'new_operation_operationtype' => 'Disable host']
			],
			[
				['eventsource' => 'Internal']
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
					'new_operation_opmessage_default_msg' => 'unchecked'
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
			case 'Auto registration':
				$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_AUTO_REGISTRATION.'&form=Create+action');
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
		$this->zbxTestAssertAttribute("//input[@id='name']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='name']", 'size', 20);
		$this->zbxTestAssertAttribute("//input[@id='name']", 'autofocus');

		$this->zbxTestTextPresent('Enabled');
		$this->zbxTestAssertElementPresentId('status');
		$this->zbxTestAssertElementPresentXpath("//input[@type='checkbox' and @id='status']");
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));

		if ($eventsource == 'Triggers') {
			$this->zbxTestInputTypeWait('new_condition_value', 'TEST');
			$this->zbxTestClickXpathWait("//div[@id='actionTab']//button[text()='Add' and contains(@onclick, 'add_condition')]");
			if (isset($data['evaltype'])) {
				$this->zbxTestDropdownSelect('evaltype', $data['evaltype']);
				$evaltype = $data['evaltype'];
			}
			else {
				$select_options = $this->zbxTestGetDropDownElements('evaltype');
				$evaltype = $select_options[0]['content'];
			}
		}

		if ($eventsource == 'Triggers') {
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
			$this->zbxTestTextNotVisibleOnPage('Type of calculation');
			$this->zbxTestAssertNotVisibleId('evaltype');
		}

		$this->zbxTestTextPresent([
				'Conditions',
				'Label', 'Name', 'Action'
		]);

		if ($eventsource == 'Triggers') {
			$this->zbxTestAssertElementText('//tr[@id="conditions_0"]/td[2]', 'Maintenance status not in maintenance');
			$this->zbxTestAssertElementText('//tr[@id="conditions_1"]/td[2]', 'Trigger name like TEST');
			$this->zbxTestTextPresent([
					'A', 'Maintenance status','B', 'Trigger name'
			]);
			$this->zbxTestAssertElementPresentXpath('//button[@id="remove" and @name="remove" and @onclick="javascript:'.
				' removeCondition(0);"]');
			$this->zbxTestAssertElementPresentXpath('//button[@id="remove" and @name="remove" and @onclick="javascript:'.
				' removeCondition(1);"]');
		}
		else {
			$this->zbxTestTextNotVisibleOnPage(['A', 'B']);
			$this->zbxTestTextNotPresent(['Maintenance status', 'Trigger name']);
			$this->zbxTestAssertElementNotPresentXpath('//button[@id="remove" and @name="remove" and @onclick="javascript:'.
				' removeCondition(0);"]');
			$this->zbxTestAssertElementNotPresentXpath('//button[@id="remove" and @name="remove" and @onclick="javascript:'.
				' removeCondition(1);"]');
		}

		if (isset($data['new_condition_conditiontype'])) {
			$this->zbxTestDropdownSelectWait('new_condition_conditiontype', $data['new_condition_conditiontype']);
		}
		$new_condition_conditiontype = $this->zbxTestGetSelectedLabel('new_condition_conditiontype');

		$this->zbxTestTextPresent('New condition');
		$this->zbxTestAssertElementPresentId('new_condition_conditiontype');
		switch ($eventsource) {
			case 'Triggers':
				$this->zbxTestDropdownHasOptions('new_condition_conditiontype', [
						'Application',
						'Host group',
						'Template',
						'Host',
						'Trigger',
						'Trigger name',
						'Trigger severity',
						'Time period',
						'Maintenance status'
				]);
				break;
			case 'Discovery':
				$this->zbxTestDropdownHasOptions('new_condition_conditiontype', [
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
			case 'Auto registration':
				$this->zbxTestDropdownHasOptions('new_condition_conditiontype', [
						'Host name',
						'Proxy',
						'Host metadata'
				]);
				break;
			case 'Internal':
				$this->zbxTestDropdownHasOptions('new_condition_conditiontype', [
						'Application',
						'Event type',
						'Host group',
						'Template',
						'Host'
				]);
				break;
		}

		if (isset($data['new_condition_conditiontype'])) {
			$this->zbxTestDropdownAssertSelected('new_condition[conditiontype]', $new_condition_conditiontype);
		}
		else {
			switch ($eventsource) {
				case 'Triggers':
					$this->zbxTestDropdownAssertSelected('new_condition[conditiontype]', 'Trigger name');
					break;
				case 'Discovery':
					$this->zbxTestDropdownAssertSelected('new_condition[conditiontype]', 'Host IP');
					break;
				case 'Auto registration':
					$this->zbxTestDropdownAssertSelected('new_condition[conditiontype]', 'Host name');
					break;
				case 'Internal':
					$this->zbxTestDropdownAssertSelected('new_condition[conditiontype]', 'Application');
					break;
			}
		}

		$this->zbxTestAssertElementPresentId('new_condition_operator');

		switch ($new_condition_conditiontype) {
			case 'Application':
				$this->zbxTestDropdownHasOptions('new_condition_operator', [
						'=',
						'like',
						'not like'
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
				$this->zbxTestDropdownHasOptions('new_condition_operator', [
						'=',
						'<>'
				]);
				break;
			case 'Trigger name':
			case 'Host name':
			case 'Host metadata':
				$this->zbxTestDropdownHasOptions('new_condition_operator', [
						'like',
						'not like'
				]);
				break;
			case 'Trigger severity':
				$this->zbxTestDropdownHasOptions('new_condition_operator', [
						'=',
						'<>',
						'>=',
						'<='
				]);
				break;
			case 'Trigger value':
			case 'Discovery object':
			case 'Discovery status':
			case 'Event type':
				$this->zbxTestDropdownHasOptions('new_condition_operator', [
						'='
				]);
				break;
			case 'Time period':
			case 'Maintenance status':
				$this->zbxTestDropdownHasOptions('new_condition_operator', [
						'in',
						'not in'
				]);
				break;
			case 'Uptime/Downtime':
				$this->zbxTestDropdownHasOptions('new_condition_operator', [
						'>=',
						'<='
				]);
				break;
			case 'Received value':
				$this->zbxTestDropdownHasOptions('new_condition_operator', [
						'=',
						'<>',
						'>=',
						'<=',
						'like',
						'not like'
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
				$this->zbxTestAssertElementPresentXpath('//input[@id=\'new_condition_value\']');
				break;
			case 'Proxy':
			case 'Discovery rule':
			case 'Discovery check':
				$this->zbxTestAssertNotVisibleXpath('//input[@id=\'new_condition_value\']');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'new_condition_value\']');
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
				$this->zbxTestAssertAttribute('//input[@id=\'new_condition_value\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'new_condition_value\']', 'size', 20);
				break;
			case 'Uptime/Downtime':
				$this->zbxTestAssertAttribute('//input[@id=\'new_condition_value\']', 'maxlength', 15);
				$this->zbxTestAssertAttribute('//input[@id=\'new_condition_value\']', 'size', 20);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Application':
			case 'Trigger name':
			case 'Received value':
			case 'Host name':
			case 'Host metadata':
				$this->zbxTestAssertElementValue('new_condition_value', "");
				break;
			case 'Time period':
				$this->zbxTestAssertElementValue('new_condition_value', '1-7,00:00-24:00');
				break;
			case 'Service port':
				$this->zbxTestAssertElementValue('new_condition_value', '0-1023,1024-49151');
				break;
			case 'Host IP':
				$this->zbxTestAssertElementValue('new_condition_value', '192.168.0.1-127,192.168.2.1');
				break;
			case 'Uptime/Downtime':
				$this->zbxTestAssertElementValue('new_condition_value', 600);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Host group':
			case 'Template':
			case 'Host':
			case 'Trigger':
				$this->zbxTestAssertElementPresentXpath('//*[@id=\'new_condition_value_\']/input[@placeholder]');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//*[@id=\'new_condition_value_\']/input[@placeholder]');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Trigger severity':
			case 'Trigger value':
			case 'Service type':
			case 'Discovery object':
			case 'Discovery status':
			case 'Event type':
				$this->zbxTestAssertElementPresentXpath('//select[@id=\'new_condition_value\']');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//select[@id=\'new_condition_value\']');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Trigger severity':
				$this->zbxTestDropdownHasOptions('new_condition_value', [
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
				$this->zbxTestDropdownHasOptions('new_condition_value', [
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
				$this->zbxTestDropdownHasOptions('new_condition_value', [
						'Device',
						'Service'
				]);
				break;
			case 'Discovery status':
				$this->zbxTestDropdownHasOptions('new_condition_value', [
						'Up',
						'Down',
						'Discovered',
						'Lost'
				]);
				break;
			case 'Event type':
				$this->zbxTestDropdownHasOptions('new_condition_value', [
						'Item in "not supported" state',
						'Low-level discovery rule in "not supported" state',
						'Trigger in "unknown" state',
				]);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Trigger severity':
				$this->zbxTestAssertAttribute('//*[@id=\'new_condition_value\']/option[text()=\'Not classified\']', 'selected');
				break;
			case 'Event type':
				$this->zbxTestAssertAttribute('//*[@id=\'new_condition_value\']/option[text()=\'Item in "not supported" state\']', 'selected');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Maintenance status':
				$this->zbxTestAssertElementPresentXpath('//td[text()=\'maintenance\']');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//td[text()=\'maintenance\']');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Discovery rule':
				$this->zbxTestAssertElementPresentXpath('//input[@id=\'drule\']');
				$this->zbxTestAssertAttribute('//input[@id=\'drule\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'drule\']', 'size', 20);
				$this->zbxTestAssertAttribute('//input[@id=\'drule\']', 'readonly');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'drule\']');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Discovery check':
				$this->zbxTestAssertElementPresentXpath('//input[@id=\'dcheck\']');
				$this->zbxTestAssertAttribute('//input[@id=\'dcheck\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'dcheck\']', 'size', 20);
				$this->zbxTestAssertAttribute('//input[@id=\'dcheck\']', 'readonly');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'dcheck\']');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Proxy':
				$this->zbxTestAssertElementPresentXpath('//input[@id=\'proxy\']');
				$this->zbxTestAssertAttribute('//input[@id=\'proxy\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'proxy\']', 'size', 20);
				$this->zbxTestAssertAttribute('//input[@id=\'proxy\']', 'readonly');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'proxy\']');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Discovery rule':
			case 'Discovery check':
			case 'Proxy':
				$this->zbxTestAssertElementPresentXpath('//button[@id=\'btn1\']');
				$this->zbxTestAssertElementText('//button[@id=\'btn1\']', 'Select');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//button[@id=\'btn1\']');
				break;
		}

		$this->zbxTestAssertElementPresentXpath("//div[@id='actionTab']//button[text()='Add' and contains(@onclick,'add_condition')]");

		$this->zbxTestTabSwitch('Operations');

		$this->zbxTestTextPresent('Default subject');
		$this->zbxTestAssertVisibleId('def_shortdata');
		$this->zbxTestAssertAttribute("//input[@id='def_shortdata']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='def_shortdata']", 'size', 20);
		switch ($eventsource) {
			case 'Triggers':
				$this->zbxTestAssertElementValue('def_shortdata', 'Problem: {TRIGGER.NAME}');
				break;
			case 'Discovery':
				$this->zbxTestAssertElementValue('def_shortdata', 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}');
				break;
			case 'Auto registration':
				$this->zbxTestAssertElementValue('def_shortdata', 'Auto registration: {HOST.HOST}');
				break;
			case 'Internal':
				$this->zbxTestAssertElementValue('def_shortdata', '');
				break;
		}
		$this->zbxTestTextPresent('Default message');
		$this->zbxTestAssertVisibleId('def_longdata');
		$this->zbxTestAssertAttribute("//textarea[@id='def_longdata']", 'rows', 7);
		switch ($eventsource) {
			case 'Triggers':
				$def_longdata_val = 'Problem started at {EVENT.TIME} on {EVENT.DATE}'.
					' Problem name: {TRIGGER.NAME}'.
					' Host: {HOST.NAME}'.
					' Severity: {TRIGGER.SEVERITY}'.
					' Original problem ID: {EVENT.ID}'.
					' {TRIGGER.URL}';
					break;
			case 'Discovery':
				$def_longdata_val = 'Discovery rule: {DISCOVERY.RULE.NAME}'.
					' Device IP:{DISCOVERY.DEVICE.IPADDRESS}'.
					' Device DNS: {DISCOVERY.DEVICE.DNS}'.
					' Device status: {DISCOVERY.DEVICE.STATUS}'.
					' Device uptime: {DISCOVERY.DEVICE.UPTIME}'.
					' Device service name: {DISCOVERY.SERVICE.NAME}'.
					' Device service port: {DISCOVERY.SERVICE.PORT}'.
					' Device service status: {DISCOVERY.SERVICE.STATUS}'.
					' Device service uptime: {DISCOVERY.SERVICE.UPTIME}';
				break;
			case 'Auto registration':
				$def_longdata_val = 'Host name: {HOST.HOST}'.
					' Host IP: {HOST.IP}'.
					' Agent port: {HOST.PORT}';
				break;
			case 'Internal':
				$def_longdata_val = "";
				break;
		}
		$this->zbxTestAssertElementText('//textarea[@id="def_longdata"]', $def_longdata_val);

		if (isset($data['new_operation_operationtype'])) {
			$new_operation_operationtype = $data['new_operation_operationtype'];
			$this->zbxTestClickXpathWait("//ul[@id='operationlist']//button[text()='New' and contains(@onclick,'new_operation')]");
			switch ($eventsource) {
				case 'Triggers':
				case 'Discovery':
				case 'Auto registration':
					$this->zbxTestDropdownSelectWait('new_operation_operationtype', $new_operation_operationtype);
					break;
				case 'Internal':
					$this->zbxTestTextPresent ('Send message');
					break;
			}
		}
		else {
			$new_operation_operationtype = null;
		}

		if (isset($data['new_operation_opcommand_type'])) {
			$new_operation_opcommand_type = $data['new_operation_opcommand_type'];
			$this->zbxTestDropdownSelect('new_operation_opcommand_type', $new_operation_opcommand_type);
		}
		elseif ($new_operation_operationtype == 'Remote command') {
			$new_operation_opcommand_type = $this->zbxTestGetSelectedLabel('new_operation_opcommand_type');
		}
		else {
			$new_operation_opcommand_type = null;
		}

		if (isset($data['new_operation_opcommand_authtype'])) {
			$new_operation_opcommand_authtype = $data['new_operation_opcommand_authtype'];
			$this->zbxTestDropdownSelect('new_operation_opcommand_authtype', $new_operation_opcommand_authtype);
		}
		elseif ($new_operation_opcommand_type == 'SSH' || $new_operation_opcommand_type == 'Telnet') {
			$new_operation_opcommand_authtype = $this->zbxTestGetSelectedLabel('new_operation_opcommand_authtype');
		}
		else {
			$new_operation_opcommand_authtype = null;
		}

		if (isset($data['new_operation_opmessage_default_msg'])) {
			$new_operation_opmessage_default_msg = $data['new_operation_opmessage_default_msg'];
			$this->zbxTestCheckboxSelect('new_operation_opmessage_default_msg', false);
		}
		elseif ($new_operation_operationtype == 'Send message') {
			$new_operation_opmessage_default_msg = 'checked';
		}
		else {
			$new_operation_opmessage_default_msg = null;
		}

		if (isset($data['add_opcondition'])) {
			$this->zbxTestClickWait('search');
			$this->zbxTestClickXpathWait("//table[@id='operationConditionTable']//button[text()='New' and contains(@onclick,'new_opcondition')]");
			$this->zbxTestWaitUntilElementPresent(webDriverBy::id('new_opcondition_conditiontype'));
			$add_opcondition = $data['add_opcondition'];
		}
		else {
			$add_opcondition = null;
		}

		if (isset($data['opCmdTarget'])) {
			$opCmdTarget = $data['opCmdTarget'];
			$this->zbxTestClickXpath('//*[@id=\'opCmdListFooter\']//button[@id=\'add\']');
			$this->zbxTestDropdownSelect('opCmdTarget', $opCmdTarget);
		}
		else {
			$opCmdTarget = null;
		}

		switch ($eventsource) {
			case 'Triggers':
			case 'Internal':
				$this->zbxTestTextPresent([
						'Default operation step duration'
				]);
				$this->zbxTestAssertVisibleId('esc_period');
				$this->zbxTestAssertAttribute('//input[@id=\'esc_period\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'esc_period\']', 'size', 20);
				$this->zbxTestAssertAttribute('//input[@id=\'esc_period\']', 'value', '1h');
				break;
			default:
				$this->zbxTestTextNotPresent([
						'Default operation step duration'
				]);
				$this->zbxTestAssertElementNotPresentId('esc_period');
				break;
		}

		$this->zbxTestTextPresent(['Operations', 'Details', 'Action']);

		switch ($eventsource) {
			case 'Triggers':
			case 'Internal':
				$this->zbxTestTextPresent([
						'Steps', 'Start in', 'Duration'
				]);
				break;
			default:
				$this->zbxTestTextNotPresent([
						'Steps', 'Start in', 'Duration'
				]);
				break;
		}

		if ($new_operation_operationtype == null) {
			$this->zbxTestAssertVisibleXpath("//div[@id='operationTab']//button[text()='New' and contains(@onclick,'new_operation')]");
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//div[@id='operationTab']//button[text()='New' and contains(@onclick,'new_operation')]");
		}

		if ($new_operation_operationtype != null && $eventsource == 'Triggers' || $eventsource == 'Internal') 	{
			switch ($new_operation_operationtype) {
				case 'Send message':
				case 'Remote command':
					$this->zbxTestTextPresent ('Step');

					$this->zbxTestTextPresent ('Steps');
					$this->zbxTestAssertVisibleId('new_operation_esc_step_from');
					$this->zbxTestAssertAttribute('//input[@id=\'new_operation_esc_step_from\']', 'maxlength', 5);
					$this->zbxTestAssertAttribute('//input[@id=\'new_operation_esc_step_from\']', 'size', 20);
					$this->zbxTestAssertAttribute('//input[@id=\'new_operation_esc_step_from\']', 'value', 1);

					$this->zbxTestTextPresent ('(0 - infinitely)');
					$this->zbxTestAssertVisibleId('new_operation_esc_step_to');
					$this->zbxTestAssertAttribute('//input[@id=\'new_operation_esc_step_to\']', 'maxlength', 5);
					$this->zbxTestAssertAttribute('//input[@id=\'new_operation_esc_step_to\']', 'size', 20);
					$this->zbxTestAssertAttribute('//input[@id=\'new_operation_esc_step_to\']', 'value', 1);

					$this->zbxTestTextPresent (['Step duration', '(0 - use action default)']);
					$this->zbxTestAssertVisibleId('new_operation_esc_period');
					$this->zbxTestAssertAttribute('//input[@id=\'new_operation_esc_period\']', 'maxlength', 255);
					$this->zbxTestAssertAttribute('//input[@id=\'new_operation_esc_period\']', 'size', 20);
					$this->zbxTestAssertAttribute('//input[@id=\'new_operation_esc_period\']', 'value', '0');
					break;
				}
			}
			else {
				$this->zbxTestAssertElementNotPresentId('new_operation_esc_step_from');
				$this->zbxTestAssertElementNotPresentId('new_operation_esc_step_to');
				$this->zbxTestAssertElementNotPresentId('new_operation_esc_period');
			}

		if (isset($data['new_operation_operationtype']) && $eventsource != 'Internal') {
			$this->zbxTestTextPresent ('Operation type');
			$this->zbxTestAssertVisibleXpath('//select[@id=\'new_operation_operationtype\']');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//select[@id=\'new_operation_operationtype\']');
		}

		if (isset($data['new_operation_operationtype'])) {
			switch ($eventsource) {
				case 'Triggers':
				$this->zbxTestDropdownHasOptions('new_operation_operationtype', [
						'Send message',
						'Remote command'
				]);
					break;
				case 'Discovery':
				$this->zbxTestDropdownHasOptions('new_operation_operationtype', [
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
				case 'Auto registration':
				$this->zbxTestDropdownHasOptions('new_operation_operationtype', [
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

		if (isset($data['new_operation_operationtype'])) {
			switch ($eventsource) {
				case 'Triggers':
				case 'Discovery':
				case 'Auto registration':
					$this->zbxTestDropdownAssertSelected('new_operation[operationtype]', $new_operation_operationtype);
					break;
			}
		}

		if ($opCmdTarget != null) {
			$this->zbxTestAssertVisibleXpath('//*[@id=\'opcmdEditForm\']');
			$this->zbxTestAssertVisibleXpath('//select[@name=\'opCmdTarget\']');
			$this->zbxTestDropdownHasOptions('opCmdTarget', ['Current host', 'Host', 'Host group']);

			$this->zbxTestAssertVisibleXpath('//ul[@class=\'hor-list\']//button[@id=\'save\']');
			$this->zbxTestAssertVisibleXpath('//ul[@class=\'hor-list\']//button[@id=\'cancel\']');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//div[@id=\'opcmdEditForm\']');
		}

		if ($new_operation_operationtype == 'Send message') {
			$this->zbxTestTextPresent ([
				'Send to User groups', 'User group', 'Action',
				'Send to Users'
			]);
			$this->zbxTestAssertVisibleXpath('//tr[@id=\'opmsgUsrgrpListFooter\']//button[@class=\'btn-link\']');
			$this->zbxTestAssertElementText('//tr[@id=\'opmsgUsrgrpListFooter\']//button[@class=\'btn-link\']', 'Add');
			$this->zbxTestAssertVisibleXpath('//tr[@id=\'opmsgUserListFooter\']//button[@class=\'btn-link\']');
			$this->zbxTestAssertElementText('//tr[@id=\'opmsgUserListFooter\']//button[@class=\'btn-link\']', 'Add');

			$this->zbxTestTextPresent ('Send only to');
			$this->zbxTestAssertVisibleId('new_operation_opmessage_mediatypeid');
			$this->zbxTestDropdownAssertSelected('new_operation[opmessage][mediatypeid]', '- All -');
			$this->zbxTestDropdownHasOptions('new_operation_opmessage_mediatypeid', [
					'- All -',
					'Email',
					'Jabber',
					'SMS',
					'SMS via IP'
			]);

			$this->zbxTestTextPresent('Default message');
			$this->zbxTestAssertElementPresentId('new_operation_opmessage_default_msg');
			$this->zbxTestAssertElementPresentXpath('//input[@type=\'checkbox\' and @id=\'new_operation_opmessage_default_msg\']');
			if ($new_operation_opmessage_default_msg == 'checked') {
				$this->assertTrue($this->zbxTestCheckboxSelected('new_operation_opmessage_default_msg'));
			}
			else {
				$this->assertFalse($this->zbxTestCheckboxSelected('new_operation_opmessage_default_msg'));
			}

		}
		else {
			$this->zbxTestAssertElementNotPresentId('addusrgrpbtn');
			$this->zbxTestAssertElementNotPresentId('adduserbtn');
			$this->zbxTestAssertElementNotPresentXpath('//select[@id=\'new_operation_opmessage_mediatypeid\']');
			$this->zbxTestAssertElementNotPresentId('new_operation_opmessage_default_msg');
		}

		switch ($new_operation_opmessage_default_msg) {
			case 'unchecked':
				$this->zbxTestTextPresent('Subject');
				$this->zbxTestAssertVisibleId('new_operation_opmessage_subject');
				$this->zbxTestAssertAttribute('//input[@id=\'new_operation_opmessage_subject\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'new_operation_opmessage_subject\']', 'size', 20);
				switch ($eventsource) {
					case 'Triggers':
						$this->zbxTestAssertElementValue('new_operation_opmessage_subject', 'Problem: {TRIGGER.NAME}');
						break;
					case 'Discovery':
						$this->zbxTestAssertElementValue('new_operation_opmessage_subject', 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}');
						break;
					case 'Auto registration':
						$this->zbxTestAssertElementValue('new_operation_opmessage_subject', 'Auto registration: {HOST.HOST}');
						break;
					case 'Internal':
						$this->zbxTestAssertElementValue('new_operation_opmessage_subject', '');
						break;
				}

				$this->zbxTestTextPresent('Message');
				$this->zbxTestAssertVisibleId('new_operation_opmessage_message');
				$this->zbxTestAssertAttribute('//textarea[@id=\'new_operation_opmessage_message\']', 'rows', 7);
				switch ($eventsource) {
					case 'Triggers':
						$new_operation_opmessage_message_val = 'Problem started at {EVENT.TIME} on {EVENT.DATE}'.
							' Problem name: {TRIGGER.NAME}'.
							' Host: {HOST.NAME}'.
							' Severity: {TRIGGER.SEVERITY}'.
							' Original problem ID: {EVENT.ID}'.
							' {TRIGGER.URL}';
							break;
					case 'Discovery':
						$new_operation_opmessage_message_val = 'Discovery rule: {DISCOVERY.RULE.NAME}'.
							' Device IP:{DISCOVERY.DEVICE.IPADDRESS}'.
							' Device DNS: {DISCOVERY.DEVICE.DNS}'.
							' Device status: {DISCOVERY.DEVICE.STATUS}'.
							' Device uptime: {DISCOVERY.DEVICE.UPTIME}'.
							' Device service name: {DISCOVERY.SERVICE.NAME}'.
							' Device service port: {DISCOVERY.SERVICE.PORT}'.
							' Device service status: {DISCOVERY.SERVICE.STATUS}'.
							' Device service uptime: {DISCOVERY.SERVICE.UPTIME}';
						break;
					case 'Auto registration':
						$new_operation_opmessage_message_val = 'Host name: {HOST.HOST}'.
							' Host IP: {HOST.IP}'.
							' Agent port: {HOST.PORT}';
						break;
					case 'Internal':
						$new_operation_opmessage_message_val = "";
						break;
				}
				$this->zbxTestAssertElementText('//textarea[@id=\'new_operation_opmessage_message\']', $new_operation_opmessage_message_val);
				break;
			case 'checked':
				$this->zbxTestAssertNotVisibleId('new_operation_opmessage_subject');
				$this->zbxTestAssertNotVisibleId('new_operation_opmessage_message');
				break;
			default:
				$this->zbxTestAssertElementNotPresentId('new_operation_opmessage_subject');
				$this->zbxTestAssertElementNotPresentId('new_operation_opmessage_message');
				break;
		}

		if ($eventsource == 'Triggers' && $new_operation_operationtype != null) {
			$this->zbxTestTextPresent ([
				'Conditions', 'Label', 'Name', 'Action'
			]);

			if ($add_opcondition == null) {
				$this->zbxTestAssertVisibleXpath("//ul[@id='operationlist']//button[text()='New' and contains(@onclick,'new_opcondition')]");
			}
			else {
				$this->zbxTestTextPresent ('Operation condition');
				$this->zbxTestAssertVisibleXpath("//ul[@id='operationlist']//button[text()='Cancel' and contains(@onclick,'cancel_new_opcondition')]");

				$this->zbxTestAssertVisibleXpath('//select[@id=\'new_opcondition_conditiontype\']');
				$this->zbxTestDropdownAssertSelected('new_opcondition[conditiontype]', 'Event acknowledged');
				$this->zbxTestDropdownHasOptions('new_opcondition_conditiontype', [
						'Event acknowledged'
				]);

				$this->zbxTestAssertVisibleXpath('//select[@id=\'new_opcondition_operator\']');
				$this->zbxTestDropdownHasOptions('new_opcondition_operator', [
						'='
				]);

				$this->zbxTestAssertVisibleXpath('//select[@id=\'new_opcondition_value\']');
				$this->zbxTestDropdownAssertSelected('new_opcondition[value]', 'Not Ack');
				$this->zbxTestDropdownHasOptions('new_opcondition_value', [
						'Not Ack',
						'Ack'
				]);
			}
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//ul[@id='operationlist']//button[contains(@onclick,'new_opcondition')]");
			$this->zbxTestAssertElementNotPresentXpath("//ul[@id='operationlist']//button[contains(@onclick,'cancel_new_opcondition')]");

			$this->zbxTestAssertElementNotPresentXpath('//select[@id=\'new_opcondition_conditiontype\']');
			$this->zbxTestAssertElementNotPresentXpath('//select[@id=\'new_opcondition_operator\']');
			$this->zbxTestAssertElementNotPresentXpath('//select[@id=\'new_opcondition_value\']');
		}

		if ($new_operation_operationtype == 'Remote command') {
			$this->zbxTestTextPresent ([
				'Target list', 'Target', 'Action'
			]);
			$this->zbxTestAssertElementText('//button[@id=\'add\']', 'New');
		}
		else {
			$this->zbxTestTextNotPresent (['Target list', 'Execute on']);
		}

		if ($new_operation_opcommand_type != null) {
			$this->zbxTestTextPresent ('Type');
			$this->zbxTestAssertVisibleXpath('//select[@id=\'new_operation_opcommand_type\']');
			$this->zbxTestDropdownAssertSelected('new_operation[opcommand][type]', $new_operation_opcommand_type);
			$this->zbxTestDropdownHasOptions('new_operation_opcommand_type', [
					'IPMI',
					'Custom script',
					'SSH',
					'Telnet',
					'Global script'
			]);
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//select[@id=\'new_operation_opcommand_type\']');
		}

		if ($new_operation_opcommand_type == 'Custom script') {
			$this->zbxTestTextPresent ([
				'Execute on', 'Zabbix agent', 'Zabbix server']
			);
			$this->zbxTestAssertElementPresentXpath('//input[@id=\'new_operation_opcommand_execute_on_0\']');
			$this->assertTrue($this->zbxTestCheckboxSelected('new_operation_opcommand_execute_on_0'));
			$this->zbxTestAssertElementPresentXpath('//input[@id=\'new_operation_opcommand_execute_on_1\']');
		}
		elseif ($new_operation_opcommand_type != null) {
			$this->zbxTestAssertNotVisibleXpath('//input[@id=\'new_operation_opcommand_execute_on_0\']');
			$this->zbxTestAssertNotVisibleXpath('//input[@id=\'new_operation_opcommand_execute_on_1\']');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'new_operation_opcommand_execute_on_0\']');
			$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'new_operation_opcommand_execute_on_1\']');
		}

		switch ($new_operation_opcommand_type) {
			case 'Custom script':
			case 'SSH':
			case 'Telnet':
				$this->zbxTestTextPresent ('Commands');
				$this->zbxTestAssertVisibleXpath('//textarea[@id=\'new_operation_opcommand_command\']');
				$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_command\']', 'rows', 7);
				break;
			case 'IPMI':
			case 'Global script':
				$this->zbxTestAssertNotVisibleXpath('//textarea[@id=\'new_operation_opcommand_command\']');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//textarea[@id=\'new_operation_opcommand_command\']');
				break;
		}

		if ($new_operation_opcommand_type == 'IPMI') {
			$this->zbxTestTextPresent ('Commands');
			$this->zbxTestAssertVisibleXpath('//input[@id=\'new_operation_opcommand_command_ipmi\']');
			$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_command_ipmi\']', 'maxlength', 255);
			$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_command_ipmi\']', 'size', 20);
			$this->zbxTestAssertElementValue('new_operation_opcommand_command_ipmi', '');
		}
		elseif ($new_operation_opcommand_type != null) {
			$this->zbxTestAssertNotVisibleXpath('//input[@id=\'new_operation_opcommand_command_ipmi\']');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'new_operation_opcommand_command_ipmi\']');
		}

		switch ($new_operation_opcommand_type) {
			case 'SSH':
				$this->zbxTestTextPresent ('Authentication method');
				$this->zbxTestAssertVisibleXpath('//select[@id=\'new_operation_opcommand_authtype\']');
				$this->zbxTestDropdownHasOptions('new_operation_opcommand_authtype', [
						'Password',
						'Public key'
				]);
				$this->zbxTestDropdownAssertSelected('new_operation[opcommand][authtype]',
						$new_operation_opcommand_authtype
				);
				break;
			case 'IPMI':
			case 'Custom script':
			case 'Telnet':
			case 'Global script':
				$this->zbxTestAssertNotVisibleXpath('//select[@id=\'new_operation_opcommand_authtype\']');
				break;
			default:
				$this->zbxTestTextNotPresent ('Authentication method');
				$this->zbxTestAssertElementNotPresentXpath('//select[@id=\'new_operation_opcommand_authtype\']');
				break;
		}

		switch ($new_operation_opcommand_type) {
			case 'SSH':
			case 'Telnet':
				$this->zbxTestTextPresent ('User name');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'new_operation_opcommand_username\']');
				$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_username\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_username\']', 'size', 20);
				$this->zbxTestAssertElementValue('new_operation_opcommand_username', '');
				break;
			case 'IPMI':
			case 'Custom script':
			case 'Global script':
				$this->zbxTestAssertNotVisibleXpath('//input[@id=\'new_operation_opcommand_username\']');
				break;
			default:
				$this->zbxTestTextNotPresent ('User name');
				$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'new_operation_opcommand_username\']');
				break;
		}

		switch ($new_operation_opcommand_authtype) {
			case 'Password':
				$this->zbxTestTextPresent ('Password');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'new_operation_opcommand_password\']');
				$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_password\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_password\']', 'size', 20);
				$this->zbxTestAssertElementValue('new_operation_opcommand_password', '');

				$this->zbxTestAssertNotVisibleId('new_operation_opcommand_passphrase');

				$this->zbxTestAssertNotVisibleId('new_operation_opcommand_publickey');
				$this->zbxTestAssertNotVisibleId('new_operation_opcommand_privatekey');
				break;
			case 'Public key':
				$this->zbxTestTextPresent ('Key passphrase');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'new_operation_opcommand_passphrase\']');
				$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_passphrase\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_passphrase\']', 'size', 20);
				$this->zbxTestAssertElementValue('new_operation_opcommand_passphrase', '');

				$this->zbxTestAssertNotVisibleXpath('//input[@id=\'new_operation_opcommand_password\']');

				$this->zbxTestTextPresent ('Public key file');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'new_operation_opcommand_publickey\']');
				$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_publickey\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_publickey\']', 'size', 20);
				$this->zbxTestAssertElementValue('new_operation_opcommand_publickey', '');

				$this->zbxTestTextPresent ('Private key file');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'new_operation_opcommand_privatekey\']');
				$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_privatekey\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_privatekey\']', 'size', 20);
				$this->zbxTestAssertElementValue('new_operation_opcommand_privatekey', '');
				break;
			default:
				if ($new_operation_opcommand_type != null) {
					$this->zbxTestAssertNotVisibleXpath('//input[@id=\'new_operation_opcommand_publickey\']');
					$this->zbxTestAssertNotVisibleXpath('//input[@id=\'new_operation_opcommand_privatekey\']');

					$this->zbxTestAssertNotVisibleXpath('//input[@id=\'new_operation_opcommand_passphrase\']');
				}
				else {
					$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'new_operation_opcommand_password\']');
					$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'new_operation_opcommand_passphrase\']');

					$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'new_operation_opcommand_publickey\']');
					$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'new_operation_opcommand_privatekey\']');
				}
				break;
		}

		switch ($new_operation_opcommand_type) {
			case 'SSH':
			case 'Telnet':
				$this->zbxTestTextPresent ('Port');
				$this->zbxTestAssertVisibleXpath('//input[@id=\'new_operation_opcommand_port\']');
				$this->zbxTestAssertAttribute('//input[@id=\'new_operation_opcommand_port\']', 'maxlength', 255);
				$this->zbxTestAssertAttribute('//input[@id=\'new_operation_opcommand_port\']', 'size', 20);
				$this->zbxTestAssertElementValue('new_operation_opcommand_port', '');
				break;
			case 'IPMI':
			case 'Custom script':
			case 'Global script':
				$this->zbxTestAssertNotVisibleXpath('//input[@id=\'new_operation_opcommand_port\']');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'new_operation_opcommand_port\']');
				break;
		}

		if ($new_operation_opcommand_type == 'Global script') {
			$this->zbxTestAssertVisibleXpath('//input[@id=\'new_operation_opcommand_script\']');
			$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_script\']', 'maxlength', 255);
			$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_script\']', 'size', 20);
			$this->zbxTestAssertAttribute('//*[@id=\'new_operation_opcommand_script\']', 'readonly');
			$this->zbxTestAssertElementValue('new_operation_opcommand_script', '');
		}
		elseif ($new_operation_operationtype == 'Remote command') {
			$this->zbxTestAssertNotVisibleXpath('//input[@id=\'new_operation_opcommand_script\']');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath('//input[@id=\'new_operation_opcommand_script\']');
		}

		switch ($new_operation_operationtype) {
			case 'Add to host group':
			case 'Remove from host group':
				$this->zbxTestAssertVisibleXpath('//div[@id=\'new_operation_groupids_\']/input');
				$this->zbxTestAssertElementNotPresentXpath('//div[@id=\'new_operation_templateids_\']/input');
				break;
			case 'Link to template':
			case 'Unlink from template':
				$this->zbxTestAssertVisibleXpath('//div[@id=\'new_operation_templateids_\']/input');
				$this->zbxTestAssertElementNotPresentXpath('//div[@id=\'new_operation_groupids_\']/input');
				break;
			default:
				$this->zbxTestAssertElementNotPresentXpath('//div[@id=\'new_operation_groupids_\']/input');
				$this->zbxTestAssertElementNotPresentXpath('//div[@id=\'new_operation_templateids_\']/input');
				break;
		}

		if ($new_operation_operationtype != null) {
			$this->zbxTestAssertVisibleXpath("//ul[@id='operationlist']//button[text()='Add' and contains(@onclick,'add_operation')]");
			$this->zbxTestAssertVisibleXpath("//ul[@id='operationlist']//button[text()='Cancel' and contains(@onclick,'cancel_new_operation')]");
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//ul[@id='operationlist']//button[contains(@onclick,'add_operation')]");
			$this->zbxTestAssertElementNotPresentXpath("//ul[@id='operationlist']//button[contains(@onclick,'cancel_new_operation')]");
		}

		if (array_key_exists('recovery_msg', $data)) {
			$this->zbxTestTabSwitch('Recovery operations');
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('r_shortdata'));
			$recovery_msg = $data['recovery_msg'];
		}
		else {
			$recovery_msg = false;
		}

		if ($eventsource == 'Triggers' || $eventsource == 'Internal') {
			$this->zbxTestAssertElementPresentId('tab_recoveryOperationTab');
		}
		else {
			$this->zbxTestTextNotPresent('Recovery operations');
			$this->zbxTestAssertElementNotPresentId('tab_recoveryOperationTab');
		}

		if ($recovery_msg == true) {
			$this->zbxTestTextPresent('Default subject');
			$this->zbxTestAssertVisibleId('r_shortdata');
			$this->zbxTestAssertAttribute("//input[@id='r_shortdata']", 'maxlength', 255);
			$this->zbxTestAssertAttribute("//input[@id='r_shortdata']", 'size', 20);
			switch ($eventsource) {
				case 'Triggers':
					$this->zbxTestAssertElementValue('r_shortdata', 'Resolved: {TRIGGER.NAME}');
					break;
				case 'Internal':
					$this->zbxTestAssertElementValue('r_shortdata', '');
					break;
			}

			$this->zbxTestTextPresent('Default message');
			$this->zbxTestAssertVisibleId('r_longdata');
			$this->zbxTestAssertAttribute("//textarea[@id='r_longdata']", 'rows', 7);
			switch ($eventsource) {
				case 'Triggers':
					$r_longdata_val = 'Problem has been resolved at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}'.
						' Problem name: {TRIGGER.NAME}'.
						' Host: {HOST.NAME}'.
						' Severity: {TRIGGER.SEVERITY}'.
						' Original problem ID: {EVENT.ID}'.
						' {TRIGGER.URL}';
						break;
				case 'Internal':
					$r_longdata_val = "";
					break;
			}
			$this->zbxTestAssertElementText('//textarea[@id="r_longdata"]', $r_longdata_val);
		}
		elseif ($eventsource == 'Triggers' || $eventsource == 'Internal') {
			$this->zbxTestAssertNotVisibleId('r_shortdata');
			$this->zbxTestAssertNotVisibleId('r_longdata');
		}
		else {
			$this->zbxTestAssertElementNotPresentId('r_shortdata');
			$this->zbxTestAssertElementNotPresentId('r_longdata');
		}

		if (array_key_exists('acknowledge_msg', $data)) {
			$this->zbxTestTabSwitch('Acknowledgement operations');
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('ack_shortdata'));
			$acknowledge_msg = $data['acknowledge_msg'];
		}
		else {
			$acknowledge_msg = false;
		}

		if ($eventsource == 'Triggers') {
			$this->zbxTestAssertElementPresentId('tab_acknowledgeTab');
		}
		else {
			$this->zbxTestTextNotPresent('Acknowledgement operations');
			$this->zbxTestAssertElementNotPresentId('tab_acknowledgeTab');
		}

		if ($acknowledge_msg == true) {
			$this->zbxTestTextPresent('Default subject');
			$this->zbxTestAssertVisibleId('ack_shortdata');
			$this->zbxTestAssertAttribute("//input[@id='ack_shortdata']", 'maxlength', 255);
			$this->zbxTestAssertAttribute("//input[@id='ack_shortdata']", 'size', 20);
			$this->zbxTestAssertElementValue('ack_shortdata', 'Acknowledged: {TRIGGER.NAME}');

			$this->zbxTestTextPresent('Default message');
			$this->zbxTestAssertVisibleId('ack_longdata');
			$this->zbxTestAssertAttribute("//textarea[@id='ack_longdata']", 'rows', 7);
			$ack_longdata_val = '{USER.FULLNAME} acknowledged problem at {ACK.DATE} {ACK.TIME}'.
						' with the following message:'.
						' {ACK.MESSAGE}'.
						' Current problem status is {EVENT.STATUS}';
			$this->zbxTestAssertElementText('//textarea[@id="ack_longdata"]', $ack_longdata_val);
		}
		elseif ($eventsource == 'Triggers') {
			$this->zbxTestAssertNotVisibleId('ack_shortdata');
			$this->zbxTestAssertNotVisibleId('ack_longdata');
		}
		else {
			$this->zbxTestAssertElementNotPresentId('ack_shortdata');
			$this->zbxTestAssertElementNotPresentId('ack_longdata');
		}

		$this->zbxTestAssertVisibleId('add');
		$this->zbxTestAssertAttribute("//button[@id='add' and @type='submit']", 'value', 'Add');

		$this->zbxTestAssertVisibleId('cancel');
		$this->zbxTestAssertAttribute('//button[@id=\'cancel\']', 'name', 'cancel');
	}

	public static function update() {
		return DBdata(
			'SELECT name, eventsource FROM actions');
	}

	/**
	 * @dataProvider update
	 */
	public function testFormAction_SimpleUpdate($data) {
		$name = $data['name'];
		$eventsource = $data['eventsource'];

		if ($name == 'Auto discovery. Linux servers.') {
			$sqlActions = "SELECT actionid,name,eventsource,evaltype,status,def_shortdata,def_longdata,r_shortdata,r_longdata FROM actions ORDER BY actionid";
		}
		else {
			$sqlActions = "SELECT * FROM actions ORDER BY actionid";
		}
		$oldHashActions = DBhash($sqlActions);

		$this->zbxTestLogin('actionconf.php');
		switch ($eventsource) {
			case EVENT_SOURCE_TRIGGERS:
				$this->zbxTestDropdownSelectWait('eventsource', 'Triggers');
				break;
			case EVENT_SOURCE_DISCOVERY:
				$this->zbxTestDropdownSelectWait('eventsource', 'Discovery');
				break;
			case EVENT_SOURCE_AUTO_REGISTRATION:
				$this->zbxTestDropdownSelectWait('eventsource', 'Auto registration');
				break;
			case EVENT_SOURCE_INTERNAL;
				$this->zbxTestDropdownSelectWait('eventsource', 'Internal');
				break;
		}

		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestCheckHeader('Actions');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Action updated');
		$this->zbxTestTextPresent([
				'Action updated',
				'Actions',
				$name
		]);

		$this->assertEquals($oldHashActions, DBhash($sqlActions));
	}

	public static function create() {
		return [
			[[
				'expected' => ACTION_GOOD,
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'name' => 'TestFormAction Triggers 001',
				'esc_period' => '123',
				'def_shortdata' => 'def_shortdata',
				'def_longdata' => 'def_longdata',
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
				'def_shortdata' => 'def_shortdata',
				'def_longdata' => 'def_longdata',
				'errors' => [
						'Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
				]
			]],
			[[
				'expected' => ACTION_GOOD,
				'eventsource' => EVENT_SOURCE_DISCOVERY,
				'name' => 'TestFormAction Discovery 001',
				'def_shortdata' => 'def_shortdata',
				'def_longdata' => 'def_longdata',
				'conditions' => [
					[
						'type' => 'Service type',
						'value' => 'FTP',
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
				'def_shortdata' => 'def_shortdata',
				'def_longdata' => 'def_longdata',
				'errors' => [
						'Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
				]
			]],
			[[
				'expected' => ACTION_GOOD,
				'eventsource' => EVENT_SOURCE_AUTO_REGISTRATION,
				'name' => 'TestFormAction Auto registration 001',
				'def_shortdata' => 'def_shortdata',
				'def_longdata' => 'def_longdata',
				'conditions' => [
					[
						'type' => 'Host name',
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
				'eventsource' => EVENT_SOURCE_AUTO_REGISTRATION,
				'name' => '',
				'def_shortdata' => 'def_shortdata',
				'def_longdata' => 'def_longdata',
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
				'def_shortdata' => 'def_shortdata',
				'def_longdata' => 'def_longdata',
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
				'def_shortdata' => 'def_shortdata',
				'def_longdata' => 'def_longdata',
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

		if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
			$conditionCount = 1;
		} else {
			$conditionCount = 0;
		}

		if (isset($data['conditions'])) {
			foreach ($data['conditions'] as $condition) {
				$this->zbxTestDropdownSelectWait('new_condition_conditiontype', $condition['type']);
				switch ($condition['type']) {
					case 'Application':
					case 'Host name':
					case 'Host metadata':
					case 'Trigger name':
						$this->zbxTestInputTypeWait('new_condition_value', $condition['value']);
						$this->zbxTestClickXpathWait("//div[@id='actionTab']//button[contains(@onclick, 'add_condition')]");
						switch($condition['type']){
							case 'Application':
								$this->zbxTestAssertElementText("//tr[@id='conditions_".$conditionCount."']/td[2]", 'Application = '.$condition['value']);
								$conditionCount++;
								break;
							case 'Host name':
								$this->zbxTestAssertElementText('//tr[@id="conditions_'.$conditionCount.'"]/td[2]', 'Host name like '.$condition['value']);
								$conditionCount++;
								break;
							case 'Host metadata':
								$this->zbxTestAssertElementText('//tr[@id="conditions_'.$conditionCount.'"]/td[2]', 'Host metadata like '.$condition['value']);
								$conditionCount++;
								break;
							case 'Trigger name':
								$this->zbxTestAssertElementText("//tr[@id='conditions_".$conditionCount."']/td[2]", 'Trigger name like '.$condition['value']);
								$conditionCount++;
								break;
						}
						break;
					case 'Trigger severity':
					case 'Service type':
					case 'Event type':
						$this->zbxTestDropdownSelect('new_condition_value', $condition['value']);
						$this->zbxTestDoubleClickXpath("//div[@id='actionTab']//button[contains(@onclick, 'add_condition')]", "conditions_".$conditionCount);
						switch($condition['type']){
							case 'Trigger severity':
								$this->zbxTestAssertElementText('//tr[@id="conditions_'.$conditionCount.'"]/td[2]', 'Trigger severity = '.$condition['value']);
								$conditionCount++;
								break;
							case 'Service type':
								$this->zbxTestAssertElementText('//tr[@id="conditions_'.$conditionCount.'"]/td[2]', 'Service type = '.$condition['value']);
								$conditionCount++;
								break;
							case 'Event type':
								$this->zbxTestAssertElementText('//tr[@id="conditions_'.$conditionCount.'"]/td[2]', 'Event type = '.$condition['value']);
								$conditionCount++;
								break;
						}
						break;
				}
			}
		}

		if (isset($data['operations'])) {
			$this->zbxTestTabSwitch('Operations');

			if (isset($data['def_shortdata'])){
				$this->zbxTestInputTypeOverwrite('def_shortdata', $data['def_shortdata']);
				$this->zbxTestAssertElementValue('def_shortdata', $data['def_shortdata']);
			}

			if (isset($data['def_longdata'])){
				$this->zbxTestInputTypeOverwrite('def_longdata', $data['def_longdata']);
				$this->zbxTestAssertElementValue('def_longdata', $data['def_longdata']);
			}

			foreach ($data['operations'] as $operation) {
				$this->zbxTestClickXpathWait("//div[@id='operationTab']//button[contains(@onclick, 'new_operation')]");
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[contains(@onclick, 'add_operation')]"));
			if ($data['eventsource']!= EVENT_SOURCE_INTERNAL){
				$this->zbxTestDropdownSelectWait('new_operation_operationtype', $operation['type']);
			}
				switch ($operation['type']) {
					case 'Send message':
						$this->zbxTestClickXpath('//tr[@id="opmsgUsrgrpListFooter"]//button');
						$this->zbxTestLaunchOverlayDialog('User groups');
						$this->zbxTestCheckboxSelect('all_records');
						$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

						$this->zbxTestClickXpath('//tr[@id="opmsgUserListFooter"]//button');
						$this->zbxTestLaunchOverlayDialog('Users');
						$this->zbxTestCheckboxSelect('all_records');
						$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

						$this->zbxTestDropdownSelect('new_operation_opmessage_mediatypeid', $operation['media']);
						break;
					case 'Remote command':
						$this->zbxTestClickXpathWait('//tr[@id="opCmdListFooter"]//button[@id="add"]');
						$this->zbxTestClickXpathWait('//*[@id="opcmdEditForm"]//button[@id="save"]');
						$this->zbxTestInputType('new_operation_opcommand_command', $operation['command']);
						break;
				}
				$this->zbxTestClickXpathWait("//div[@id='operationTab']//button[contains(@onclick, 'add_operation')]");
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('operations_0'));
			}
		}

		if (isset($data['esc_period'])){
			$this->zbxTestTabSwitch('Operations');
			$this->zbxTestInputTypeOverwrite('esc_period', $data['esc_period']);
			$this->zbxTestWaitForPageToLoad();
			$this->webDriver->findElement(WebDriverBy::id('search'))->click();
			$this->zbxTestWaitForPageToLoad();
		}

		$this->zbxTestDoubleClickBeforeMessage('add', 'filter_name');

		switch ($data['expected']) {
			case ACTION_GOOD:
				$this->zbxTestCheckTitle('Configuration of actions');
				$this->zbxTestCheckHeader('Actions');
				$this->zbxTestTextNotPresent(['Page received incorrect data', 'Cannot add action']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Action added');
				$sql = "SELECT actionid FROM actions WHERE name='".$data['name']."'";
				$this->assertEquals(1, DBcount($sql), 'Action has not been created in the DB.');
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
		$this->zbxTestAssertElementText("//tr[@id='conditions_0']/td[2]", 'Maintenance status not in maintenance');

		$this->zbxTestInputTypeWait('new_condition_value', 'trigger');
		$this->zbxTestClickXpathWait("//div[@id='actionTab']//button[contains(@onclick, 'add_condition')]");
		$this->zbxTestAssertElementText("//tr[@id='conditions_1']/td[2]", 'Trigger name like trigger');

		$this->zbxTestDropdownSelectWait('new_condition_conditiontype', 'Trigger severity');
		$this->zbxTestDropdownSelect('new_condition_value', 'Average');
		$this->zbxTestClickXpathWait("//div[@id='actionTab']//button[contains(@onclick, 'add_condition')]");
		$this->zbxTestAssertElementText("//tr[@id='conditions_2']/td[2]", 'Trigger severity = Average');

		$this->zbxTestDropdownSelectWait('new_condition_conditiontype', 'Application');
		$this->zbxTestInputTypeWait('new_condition_value', 'app');
		$this->zbxTestClickXpathWait("//div[@id='actionTab']//button[contains(@onclick, 'add_condition')]");
		$this->zbxTestAssertElementText("//tr[@id='conditions_3']/td[2]", 'Application = app');

// adding operations
		$this->zbxTestTabSwitch('Operations');
		$this->zbxTestInputTypeWait('def_shortdata', 'subject');
		$this->zbxTestInputType('def_longdata', 'message');
		$this->zbxTestClickXpathWait("//div[@id='operationTab']//button[contains(@onclick, 'new_operation')]");

		$this->zbxTestClickXpath('//tr[@id="opmsgUsrgrpListFooter"]//button');
		$this->zbxTestLaunchOverlayDialog('User groups');
		$this->zbxTestCheckboxSelect('item_7');
		$this->zbxTestCheckboxSelect('item_11');
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		$this->zbxTestClickXpath('//tr[@id="opmsgUserListFooter"]//button');
		$this->zbxTestLaunchOverlayDialog('Users');
		$this->zbxTestCheckboxSelect('item_1');
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		$this->zbxTestDropdownSelect('new_operation_opmessage_mediatypeid', 'Jabber');
		$this->zbxTestClickXpathWait("//div[@id='operationTab']//button[contains(@onclick, 'add_operation')]");
		$this->zbxTestAssertElementText("//tr[@id='operations_0']//span",
			"Send message to users: Admin (Zabbix Administrator) via Jabber ".
			"Send message to user groups: Enabled debug mode, Zabbix administrators via Jabber");

		$this->zbxTestClickXpathWait("//div[@id='operationTab']//button[contains(@onclick, 'new_operation')]");
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[contains(@onclick, 'add_operation')]"));
		$this->zbxTestDropdownSelectWait('new_operation_operationtype', 'Remote command');

// add target current host
		$this->zbxTestClickXpathWait('//tr[@id="opCmdListFooter"]//button[@id="add"]');
		$this->zbxTestClickXpathWait('//*[@id="opcmdEditForm"]//button[@id="save"]');

// add target host Zabbix server
		$this->zbxTestClickXpath('//tr[@id="opCmdListFooter"]//button[@id="add"]');
		$this->zbxTestDropdownSelect('opCmdTarget', 'Host');
		$this->zbxTestTextPresent(['Target list', 'Target', 'Action']);

		$this->zbxTestClickButtonMultiselect('opCmdTargetObject');
		$this->zbxTestLaunchOverlayDialog('Hosts');

		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestClickLinkTextWait('Simple form test host');
		$this->zbxTestClickXpath('//*[@id="opcmdEditForm"]//button[@id="save"]');

// add target group Zabbix servers
		$this->zbxTestClickXpath('//tr[@id="opCmdListFooter"]//button[@id="add"]');
		$this->zbxTestDropdownSelect('opCmdTarget', 'Host group');
		$this->zbxTestTextPresent(['Target list', 'Target', 'Action']);

		$this->zbxTestClickButtonMultiselect('opCmdTargetObject');
		$this->zbxTestLaunchOverlayDialog('Host groups');

		$this->zbxTestClickLinkTextWait('Zabbix servers');
		$this->zbxTestClickXpath('//*[@id="opcmdEditForm"]//button[@id="save"]');

		$this->zbxTestInputType('new_operation_opcommand_command', 'command');
		$this->zbxTestClickXpathWait("//div[@id='operationTab']//button[contains(@onclick, 'add_operation')]");
		$this->zbxTestAssertElementText("//tr[@id='operations_0']//span",
			"Send message to users: Admin (Zabbix Administrator) via Jabber ".
			"Send message to user groups: Enabled debug mode, Zabbix administrators via Jabber");
		$this->zbxTestAssertElementText("//tr[@id='operations_1']//span",
			"Run remote commands on current host ".
			"Run remote commands on hosts: Simple form test host ".
			"Run remote commands on host groups: Zabbix servers");

		$this->zbxTestClickXpathWait("//div[@id='operationTab']//button[contains(@onclick, 'new_operation')]");
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[contains(@onclick, 'add_operation')]"));
		$this->zbxTestInputTypeOverwrite('new_operation_esc_step_to', '2');
		$this->zbxTestDropdownSelectWait('new_operation_operationtype', 'Remote command');
		$this->zbxTestClickXpath('//tr[@id="opCmdListFooter"]//button[@id="add"]');
		$this->zbxTestClickXpath('//*[@id="opcmdEditForm"]//button[@id="save"]');
		$this->zbxTestDropdownSelect('new_operation_opcommand_type', 'SSH');
		$this->zbxTestInputTypeWait('new_operation_opcommand_username', 'user');
		$this->zbxTestInputType('new_operation_opcommand_password', 'pass');
		$this->zbxTestInputType('new_operation_opcommand_port', '123');
		$this->zbxTestInputType('new_operation_opcommand_command', 'command ssh');
		$this->zbxTestClickXpathWait("//div[@id='operationTab']//button[contains(@onclick, 'add_operation')]");
		$this->zbxTestAssertElementText("//tr[@id='operations_0']//span",
			"Send message to users: Admin (Zabbix Administrator) via Jabber ".
			"Send message to user groups: Enabled debug mode, Zabbix administrators via Jabber");
		$this->zbxTestAssertElementText("//tr[@id='operations_1']//span",
			"Run remote commands on current host ".
			"Run remote commands on hosts: Simple form test host ".
			"Run remote commands on host groups: Zabbix servers");
		$this->zbxTestAssertElementText("//tr[@id='operations_2']//span",
			"Run remote commands on current host");
		$this->zbxTestAssertElementText('//tr[@id="operations_2"]//td', '1 - 2');

		$this->zbxTestInputTypeOverwrite('esc_period', '123');
		$this->zbxTestWaitForPageToLoad();
		$this->webDriver->findElement(WebDriverBy::id('search'))->click();
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementValue('esc_period', '123');
		$this->zbxTestDoubleClickXpath("//div[@id='operationTab']//button[contains(@onclick, 'new_operation')]", 'new_operation_esc_step_from');

		$this->zbxTestDoubleClickBeforeMessage('add', 'filter_name');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Action added');

		$sql = "SELECT actionid FROM actions WHERE name='action test'";
		$this->assertEquals(1, DBcount($sql), 'Action has not been created in the DB.');
	}
}
