<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

class testFormAction extends CWebTest {

	public function testFormAction_Setup() {
		DBsave_tables('actions');
	}

	public static function layout() {
		return [
			[
				['eventsource' => 'Triggers']
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
				['eventsource' => 'Triggers', 'evaltype' => 'AND']
			],
			[
				['eventsource' => 'Triggers', 'evaltype' => 'OR']
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
				['eventsource' => 'Triggers', 'new_condition_conditiontype' => 'Trigger value']
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

		if (isset($data['new_condition_conditiontype'])) {
			$this->zbxTestDropdownSelectWait('new_condition_conditiontype', $data['new_condition_conditiontype']);
		}
		$new_condition_conditiontype = $this->getSelectedLabel('//select[@id=\'new_condition_conditiontype\']');

		if ($eventsource == 'Triggers') {
			if (isset($data['evaltype'])) {
				$this->zbxTestDropdownSelectWait('evaltype', $data['evaltype']);
			}
			$evaltype = $this->getSelectedLabel('//select[@id=\'evaltype\']');
		}


		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestTextPresent([
				'CONFIGURATION OF ACTIONS',
				'Action', 'Conditions', 'Operations'
		]);

		$this->zbxTestTextPresent('Name');
		$this->assertVisible('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", 255);
		$this->assertAttribute("//input[@id='name']/@size", 50);
		$this->assertAttribute("//input[@id='name']/@autofocus", 'autofocus');

		$this->zbxTestTextPresent('Default subject');
		$this->assertVisible('def_shortdata');
		$this->assertAttribute("//input[@id='def_shortdata']/@maxlength", 255);
		$this->assertAttribute("//input[@id='def_shortdata']/@size", 50);
		switch ($eventsource) {
			case 'Triggers':
				$this->assertAttribute('//input[@id=\'def_shortdata\']/@value', '{TRIGGER.STATUS}: {TRIGGER.NAME}');
				break;
			case 'Discovery':
				$this->assertAttribute('//input[@id=\'def_shortdata\']/@value', 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}');
				break;
			case 'Auto registration':
				$this->assertAttribute('//input[@id=\'def_shortdata\']/@value', 'Auto registration: {HOST.HOST}');
				break;
			case 'Internal':
				$this->assertEquals($this->getValue('def_shortdata'), "");
				break;
		}
		$this->zbxTestTextPresent('Default message');
		$this->assertVisible('def_longdata');
		$this->assertAttribute("//textarea[@id='def_longdata']/@rows", 7);
		switch ($eventsource) {
			case 'Triggers':
				$def_longdata_val = 'Trigger: {TRIGGER.NAME}'.
					' Trigger status: {TRIGGER.STATUS}'.
					' Trigger severity: {TRIGGER.SEVERITY}'.
					' Trigger URL: {TRIGGER.URL}'.
					' Item values:'.
					' 1. {ITEM.NAME1} ({HOST.NAME1}:{ITEM.KEY1}): {ITEM.VALUE1}'.
					' 2. {ITEM.NAME2} ({HOST.NAME2}:{ITEM.KEY2}): {ITEM.VALUE2}'.
					' 3. {ITEM.NAME3} ({HOST.NAME3}:{ITEM.KEY3}): {ITEM.VALUE3}'.
					' Original event ID: {EVENT.ID}';
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
		$this->assertEquals($this->getText('def_longdata'), $def_longdata_val);

		if ($eventsource == 'Triggers') {
			$this->assertNotVisible('r_shortdata');
			$this->assertNotVisible('r_longdata');
		}
		else {
			$this->assertElementNotPresent('r_shortdata');
			$this->assertElementNotPresent('r_longdata');
		}

		$this->zbxTestTextPresent('Enabled');
		$this->assertElementPresent('status');
		$this->assertElementPresent("//input[@type='checkbox' and @id='status']");
		$this->assertAttribute("//*[@id='status']/@checked", 'checked');

		$this->zbxTestClick('link=Conditions');

		if ($eventsource == 'Triggers') {
			$this->zbxTestTextPresent('Type of calculation');
			$this->assertElementPresent('evaltype');
			$this->zbxTestDropdownHasOptions('evaltype', [
					'AND / OR',
					'AND',
					'OR'
			]);
			$this->assertAttribute('//*[@id=\'evaltype\']/option[text()=\''.$evaltype.'\']/@selected', 'selected');
			switch ($evaltype) {
				case 'AND / OR':
				case 'AND':
					$this->zbxTestTextPresent('(A) and (B)');
					break;
				default:
					$this->zbxTestTextPresent('(A) or (B)');
					break;
			}
		}
		else {
			$this->zbxTestTextNotPresent('Type of calculation');
			$this->assertNotVisible('evaltype');
		}

		$this->zbxTestTextPresent([
				'Conditions',
				'Label', 'Name', 'Action'
		]);

		if ($eventsource == 'Triggers') {
			$this->zbxTestTextPresent([
					'(A)', 'Maintenance status not in maintenance','(B)', 'Trigger value = PROBLEM'
			]);
			$this->assertElementPresent('//input[@id="remove" and @value="Remove" and @onclick="javascript:'.
				' removeCondition(0);"]');
			$this->assertElementPresent('//input[@id="remove" and @value="Remove" and @onclick="javascript:'.
				' removeCondition(1);"]');
		}
		else {
			$this->zbxTestTextNotPresent([
					'(A)', 'Maintenance status not in maintenance','(B)', 'Trigger value = PROBLEM'
			]);
			$this->assertElementNotPresent('//input[@id="remove" and @value="Remove" and @onclick="javascript:'.
				' removeCondition(0);"]');
			$this->assertElementNotPresent('//input[@id="remove" and @value="Remove" and @onclick="javascript:'.
				' removeCondition(1);"]');
		}

		$this->zbxTestTextPresent('New condition');
		$this->assertElementPresent('new_condition_conditiontype');
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
						'Trigger value',
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
			$this->assertAttribute('//*[@id=\'new_condition_conditiontype\']/option[text()=\''.
				$new_condition_conditiontype.'\']/@selected', 'selected');
		}
		else {
			switch ($eventsource) {
				case 'Triggers':
					$this->assertAttribute('//*[@id=\'new_condition_conditiontype\']/option[text()=\'Trigger name\']/@selected', 'selected');
					break;
				case 'Discovery':
					$this->assertAttribute('//*[@id=\'new_condition_conditiontype\']/option[text()=\'Host IP\']/@selected', 'selected');
					break;
				case 'Auto registration':
					$this->assertAttribute('//*[@id=\'new_condition_conditiontype\']/option[text()=\'Host name\']/@selected', 'selected');
					break;
				case 'Internal':
					$this->assertAttribute('//*[@id=\'new_condition_conditiontype\']/option[text()=\'Application\']/@selected', 'selected');
					break;
			}
		}

		$this->assertElementPresent('new_condition_operator');

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
				$this->assertElementPresent('//input[@id=\'new_condition_value\']');
				break;
			case 'Proxy':
			case 'Discovery rule':
			case 'Discovery check':
				$this->assertNotVisible('//input[@id=\'new_condition_value\']');
				break;
			default:
				$this->assertElementNotPresent('//input[@id=\'new_condition_value\']');
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
				$this->assertAttribute('//input[@id=\'new_condition_value\']/@maxlength', 255);
				$this->assertAttribute('//input[@id=\'new_condition_value\']/@size', 50);
				break;
			case 'Uptime/Downtime':
				$this->assertAttribute('//input[@id=\'new_condition_value\']/@maxlength', 15);
				$this->assertAttribute('//input[@id=\'new_condition_value\']/@size', 15);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Application':
			case 'Trigger name':
			case 'Received value':
			case 'Host name':
			case 'Host metadata':
				$this->assertEquals($this->getValue('//input[@id=\'new_condition_value\']'), "");
				break;
			case 'Time period':
				$this->assertEquals($this->getValue('//input[@id=\'new_condition_value\']'), '1-7,00:00-24:00');
				break;
			case 'Service port':
				$this->assertEquals($this->getValue('//input[@id=\'new_condition_value\']'), '0-1023,1024-49151');
				break;
			case 'Host IP':
				$this->assertEquals($this->getValue('//input[@id=\'new_condition_value\']'), '192.168.0.1-127,192.168.2.1');
				break;
			case 'Uptime/Downtime':
				$this->assertEquals($this->getValue('//input[@id=\'new_condition_value\']'), 600);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Host group':
			case 'Template':
			case 'Host':
			case 'Trigger':
				$this->assertElementPresent('//*[@id=\'new_condition_value_\']/input[@placeholder]');
				break;
			default:
				$this->assertElementNotPresent('//*[@id=\'new_condition_value_\']/input[@placeholder]');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Trigger severity':
			case 'Trigger value':
			case 'Service type':
			case 'Discovery object':
			case 'Discovery status':
			case 'Event type':
				$this->assertElementPresent('//select[@id=\'new_condition_value\']');
				break;
			default:
				$this->assertElementNotPresent('//select[@id=\'new_condition_value\']');
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
						'Item in "normal" state',
						'Low-level discovery rule in "not supported" state',
						'Low-level discovery rule in "normal" state',
						'Trigger in "unknown" state',
						'Trigger in "normal" state'
				]);
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Trigger severity':
				$this->assertAttribute('//*[@id=\'new_condition_value\']/option[text()=\'Not classified\']/@selected', 'selected');
				break;
			case 'Event type':
				$this->assertAttribute('//*[@id=\'new_condition_value\']/option[text()=\'Item in "not supported" state\']/@selected', 'selected');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Maintenance status':
				$this->assertElementPresent('//td[text()=\'maintenance\']');
				break;
			default:
				$this->assertElementNotPresent('//td[text()=\'maintenance\']');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Discovery rule':
				$this->assertElementPresent('//input[@id=\'drule\']');
				$this->assertAttribute('//input[@id=\'drule\']/@maxlength', 255);
				$this->assertAttribute('//input[@id=\'drule\']/@size', 50);
				$this->assertAttribute('//input[@id=\'drule\']/@readonly', 'readonly');
				break;
			default:
				$this->assertElementNotPresent('//input[@id=\'drule\']');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Discovery check':
				$this->assertElementPresent('//input[@id=\'dcheck\']');
				$this->assertAttribute('//input[@id=\'dcheck\']/@maxlength', 255);
				$this->assertAttribute('//input[@id=\'dcheck\']/@size', 50);
				$this->assertAttribute('//input[@id=\'dcheck\']/@readonly', 'readonly');
				break;
			default:
				$this->assertElementNotPresent('//input[@id=\'dcheck\']');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Proxy':
				$this->assertElementPresent('//input[@id=\'proxy\']');
				$this->assertAttribute('//input[@id=\'proxy\']/@maxlength', 255);
				$this->assertAttribute('//input[@id=\'proxy\']/@size', 50);
				$this->assertAttribute('//input[@id=\'proxy\']/@readonly', 'readonly');
				break;
			default:
				$this->assertElementNotPresent('//input[@id=\'proxy\']');
				break;
		}

		switch ($new_condition_conditiontype) {
			case 'Discovery rule':
			case 'Discovery check':
			case 'Proxy':
				$this->assertElementPresent('//input[@id=\'btn1\']');
				$this->assertAttribute('//input[@id=\'btn1\']/@value', 'Select');
				break;
			default:
				$this->assertElementNotPresent('//input[@id=\'btn1\']');
				break;
		}

		$this->assertElementPresent('add_condition');
		$this->assertAttribute('//input[@id=\'add_condition\']/@value','Add');

		$this->zbxTestClick('link=Operations');
		if (isset($data['new_operation_operationtype'])) {
			$new_operation_operationtype = $data['new_operation_operationtype'];
			$this->zbxTestClickWait('new_operation');
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
			$new_operation_opcommand_type = $this->getSelectedLabel('//select[@id=\'new_operation_opcommand_type\']');
		}
		else {
			$new_operation_opcommand_type = null;
		}

		if (isset($data['new_operation_opcommand_authtype'])) {
			$new_operation_opcommand_authtype = $data['new_operation_opcommand_authtype'];
			$this->zbxTestDropdownSelect('new_operation_opcommand_authtype', $new_operation_opcommand_authtype);
		}
		elseif ($new_operation_opcommand_type == 'SSH' || $new_operation_opcommand_type == 'Telnet') {
			$new_operation_opcommand_authtype = $this->getSelectedLabel('//select[@id=\'new_operation_opcommand_authtype\']');
		}
		else {
			$new_operation_opcommand_authtype = null;
		}

		if (isset($data['new_operation_opmessage_default_msg'])) {
			$new_operation_opmessage_default_msg = $data['new_operation_opmessage_default_msg'];
			$this->zbxTestCheckboxSelect('new_operation_opmessage_default_msg', false);
			$this->wait();
		}
		elseif ($new_operation_operationtype == 'Send message') {
			$new_operation_opmessage_default_msg = 'checked';
		}
		else {
			$new_operation_opmessage_default_msg = null;
		}

		if (isset($data['add_opcondition'])) {
			$this->zbxTestClickWait('new_opcondition');
			$add_opcondition = $data['add_opcondition'];
		}
		else {
			$add_opcondition = null;
		}

		if (isset($data['opCmdTarget'])) {
			$opCmdTarget = $data['opCmdTarget'];
			$this->zbxTestClick('add');
			$this->zbxTestDropdownSelect('opCmdTarget', $opCmdTarget);
		}
		else {
			$opCmdTarget = null;
		}

		switch ($eventsource) {
			case 'Triggers':
			case 'Internal':
				$this->zbxTestTextPresent([
						'Default operation step duration',	'(minimum 60 seconds)'
				]);
				$this->assertVisible('esc_period');
				$this->assertAttribute('//input[@id=\'esc_period\']/@maxlength', 6);
				$this->assertAttribute('//input[@id=\'esc_period\']/@size', 6);
				$this->assertAttribute('//input[@id=\'esc_period\']/@value', 3600);
				break;
			default:
				$this->zbxTestTextNotPresent([
						'Default operation step duration',	'(minimum 60 seconds)'
				]);
				$this->assertElementNotPresent('esc_period');
				break;
		}

		$this->zbxTestTextPresent([
				'Action operations',
				'Details', 'Action',
				'No operations defined.'
		]);

		switch ($eventsource) {
			case 'Triggers':
			case 'Internal':
				$this->zbxTestTextPresent([
						'Steps', 'Start in', 'Duration (sec)'
				]);
				break;
			default:
				$this->zbxTestTextNotPresent([
						'Steps', 'Start in', 'Duration (sec)'
				]);
				break;
		}

		if ($new_operation_operationtype == null) {
			$this->assertVisible('new_operation');
			$this->assertAttribute('//input[@id=\'new_operation\']/@value','New');
		}
		else {
			$this->assertElementNotPresent('new_operation');
		}

		if ($new_operation_operationtype != null && $eventsource == 'Triggers' || $eventsource == 'Internal') 	{
			switch ($new_operation_operationtype) {
				case 'Send message':
				case 'Remote command':
					$this->zbxTestTextPresent ('Step');

					$this->zbxTestTextPresent ('From');
					$this->assertVisible('new_operation_esc_step_from');
					$this->assertAttribute('//input[@id=\'new_operation_esc_step_from\']/@maxlength', 5);
					$this->assertAttribute('//input[@id=\'new_operation_esc_step_from\']/@size', 6);
					$this->assertAttribute('//input[@id=\'new_operation_esc_step_from\']/@value', 1);

					$this->zbxTestTextPresent (['To', '(0 - infinitely)']);
					$this->assertVisible('new_operation_esc_step_to');
					$this->assertAttribute('//input[@id=\'new_operation_esc_step_to\']/@maxlength', 5);
					$this->assertAttribute('//input[@id=\'new_operation_esc_step_to\']/@size', 6);
					$this->assertAttribute('//input[@id=\'new_operation_esc_step_to\']/@value', 1);

					$this->zbxTestTextPresent (['Step duration', '(minimum 60 seconds, 0 - use action default)']);
					$this->assertVisible('new_operation_esc_period');
					$this->assertAttribute('//input[@id=\'new_operation_esc_period\']/@maxlength', 6);
					$this->assertAttribute('//input[@id=\'new_operation_esc_period\']/@size', 6);
					$this->assertAttribute('//input[@id=\'new_operation_esc_period\']/@value', 0);
					break;
				}
			}
			else {
				$this->assertElementNotPresent('new_operation_esc_step_from');
				$this->assertElementNotPresent('new_operation_esc_step_to');
				$this->assertElementNotPresent('new_operation_esc_period');
			}

		if (isset($data['new_operation_operationtype']) && $eventsource != 'Internal') {
			$this->zbxTestTextPresent ('Operation type');
			$this->assertVisible('//select[@id=\'new_operation_operationtype\']');
		}
		else {
			$this->assertElementNotPresent('//select[@id=\'new_operation_operationtype\']');
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
					$this->assertAttribute('//*[@id=\'new_operation_operationtype\']/option[text()=\''.$new_operation_operationtype.'\']/@selected', 'selected');
					break;
			}
		}

		if ($opCmdTarget != null) {
			$this->assertVisible('//*[@id=\'opcmdEditForm\']');
			$this->assertVisible('//select[@name=\'opCmdTarget\']');
			$this->zbxTestDropdownHasOptions('opCmdTarget', ['Current host', 'Host', 'Host group']);

			$this->assertVisible('//input[@value=\'Add\' and @name=\'save\']');
			$this->assertVisible('//input[@value=\'Cancel\' and @name=\'cancel\']');
		}
		else {
			$this->assertElementNotPresent('//div[@id=\'opcmdEditForm\']');
		}

		if ($new_operation_operationtype == 'Send message') {
			$this->zbxTestTextPresent ([
				'Send to User groups', 'User group', 'Action',
				'Send to Users'
			]);
			$this->assertVisible('addusrgrpbtn');
			$this->assertAttribute('//input[@id=\'addusrgrpbtn\']/@value', 'Add');
			$this->assertVisible('adduserbtn');
			$this->assertAttribute('//input[@id=\'adduserbtn\']/@value', 'Add');

			$this->zbxTestTextPresent ('Send only to');
			$this->assertVisible('//select[@id=\'new_operation_opmessage_mediatypeid\']');
			$this->assertAttribute('//*[@id=\'new_operation_opmessage_mediatypeid\']/option[text()=\'- All -\']/@selected', 'selected');
			$this->zbxTestDropdownHasOptions('new_operation_opmessage_mediatypeid', [
					'- All -',
					'Email',
					'Jabber',
					'SMS',
					'SMS via IP'
			]);

			$this->zbxTestTextPresent('Default message');
			$this->assertVisible('new_operation_opmessage_default_msg');
			$this->assertVisible('//input[@type=\'checkbox\' and @id=\'new_operation_opmessage_default_msg\']');
			if ($new_operation_opmessage_default_msg == 'checked') {
				$this->assertAttribute('//*[@id=\'new_operation_opmessage_default_msg\']/@checked', 'checked');
			}
			else {
				$this->assertElementNotPresent('//*[@id=\'new_operation_opmessage_default_msg\']/@checked');
			}

		}
		else {
			$this->assertElementNotPresent('addusrgrpbtn');
			$this->assertElementNotPresent('adduserbtn');
			$this->assertElementNotPresent('//select[@id=\'new_operation_opmessage_mediatypeid\']');
			$this->assertElementNotPresent('new_operation_opmessage_default_msg');
		}

		switch ($new_operation_opmessage_default_msg) {
			case 'unchecked':
				$this->zbxTestTextPresent('Subject');
				$this->assertVisible('new_operation_opmessage_subject');
				$this->assertAttribute("//input[@id='new_operation_opmessage_subject']/@maxlength", 255);
				$this->assertAttribute("//input[@id='new_operation_opmessage_subject']/@size", 50);
				switch ($eventsource) {
					case 'Triggers':
						$this->assertAttribute('//input[@id=\'new_operation_opmessage_subject\']/@value', '{TRIGGER.STATUS}: {TRIGGER.NAME}');
						break;
					case 'Discovery':
						$this->assertAttribute('//input[@id=\'new_operation_opmessage_subject\']/@value', 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}');
						break;
					case 'Auto registration':
						$this->assertAttribute('//input[@id=\'new_operation_opmessage_subject\']/@value', 'Auto registration: {HOST.HOST}');
						break;
					case 'Internal':
						$this->assertEquals($this->getValue('new_operation_opmessage_subject'), "");
						break;
				}

				$this->zbxTestTextPresent('Message');
				$this->assertVisible('new_operation_opmessage_message');
				$this->assertAttribute("//textarea[@id='new_operation_opmessage_message']/@rows", 7);
				switch ($eventsource) {
					case 'Triggers':
						$new_operation_opmessage_message_val = 'Trigger: {TRIGGER.NAME}'.
							' Trigger status: {TRIGGER.STATUS}'.
							' Trigger severity: {TRIGGER.SEVERITY}'.
							' Trigger URL: {TRIGGER.URL}'.
							' Item values:'.
							' 1. {ITEM.NAME1} ({HOST.NAME1}:{ITEM.KEY1}): {ITEM.VALUE1}'.
							' 2. {ITEM.NAME2} ({HOST.NAME2}:{ITEM.KEY2}): {ITEM.VALUE2}'.
							' 3. {ITEM.NAME3} ({HOST.NAME3}:{ITEM.KEY3}): {ITEM.VALUE3}'.
							' Original event ID: {EVENT.ID}';
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
				$this->assertEquals($this->getText('new_operation_opmessage_message'), $new_operation_opmessage_message_val);
				break;
			case 'checked':
				$this->assertNotVisible('new_operation_opmessage_subject');
				$this->assertNotVisible('new_operation_opmessage_message');
				break;
			default:
				$this->assertElementNotPresent('new_operation_opmessage_subject');
				$this->assertElementNotPresent('new_operation_opmessage_message');
				break;
		}

		if ($eventsource == 'Triggers' && $new_operation_operationtype != null) {
			$this->zbxTestTextPresent ([
				'Conditions', 'Label', 'Name', 'Action'
			]);

			if ($add_opcondition == null) {
				$this->assertVisible('new_opcondition');
				$this->assertAttribute('//input[@id=\'new_opcondition\']/@value', 'New');
			}
			else {
				$this->zbxTestTextPresent ('Operation condition');
				$this->assertVisible('cancel_new_opcondition');
				$this->assertAttribute('//input[@id=\'cancel_new_opcondition\']/@value', 'Cancel');

				$this->assertVisible('//select[@id=\'new_opcondition_conditiontype\']');
				$this->assertAttribute('//*[@id=\'new_opcondition_conditiontype\']/option[text()=\'Event acknowledged\']/@selected', 'selected');
				$this->zbxTestDropdownHasOptions('new_opcondition_conditiontype', [
						'Event acknowledged'
				]);

				$this->assertVisible('//select[@id=\'new_opcondition_operator\']');
				$this->zbxTestDropdownHasOptions('new_opcondition_operator', [
						'='
				]);

				$this->assertVisible('//select[@id=\'new_opcondition_value\']');
				$this->assertAttribute('//*[@id=\'new_opcondition_value\']/option[text()=\'Not Ack\']/@selected', 'selected');
				$this->zbxTestDropdownHasOptions('new_opcondition_value', [
						'Not Ack',
						'Ack'
				]);
			}
		}
		else {
			$this->assertElementNotPresent('new_opcondition');
			$this->assertElementNotPresent('cancel_new_opcondition');

			$this->assertElementNotPresent('//select[@id=\'new_opcondition_conditiontype\']');
			$this->assertElementNotPresent('//select[@id=\'new_opcondition_operator\']');
			$this->assertElementNotPresent('//select[@id=\'new_opcondition_value\']');
		}

		if ($new_operation_operationtype == 'Remote command') {
			$this->zbxTestTextPresent ([
				'Target list', 'Target', 'Action'
			]);
			$this->assertVisible('//input[@value=\'New\' and @id=\'add\']');
		}
		else {
			$this->zbxTestTextNotPresent (['Target list', 'Target']);
		}

		if ($new_operation_opcommand_type != null) {
			$this->zbxTestTextPresent ('Type');
			$this->assertVisible('//select[@id=\'new_operation_opcommand_type\']');
			$this->assertAttribute('//*[@id=\'new_operation_opcommand_type\']/option[text()=\'Custom script\']/@selected', 'selected');
			$this->zbxTestDropdownHasOptions('new_operation_opcommand_type', [
					'IPMI',
					'Custom script',
					'SSH',
					'Telnet',
					'Global script'
			]);
		}
		else {
			$this->assertElementNotPresent('//select[@id=\'new_operation_opcommand_type\']');
		}

		if ($new_operation_opcommand_type == 'Custom script') {
			$this->zbxTestTextPresent ([
				'Execute on', 'Zabbix agent', 'Zabbix server']
			);
			$this->assertVisible('//input[@id=\'new_operation_opcommand_execute_on_1\']');
			$this->assertAttribute('//*[@id=\'new_operation_opcommand_execute_on_1\']/@checked', 'checked');

			$this->assertVisible('//input[@id=\'new_operation_opcommand_execute_on_2\']');
		}
		elseif ($new_operation_opcommand_type != null) {
			$this->assertNotVisible('//input[@id=\'new_operation_opcommand_execute_on_1\']');
			$this->assertNotVisible('//input[@id=\'new_operation_opcommand_execute_on_2\']');
		}
		else {
			$this->assertElementNotPresent('//input[@id=\'new_operation_opcommand_execute_on_1\']');
			$this->assertElementNotPresent('//input[@id=\'new_operation_opcommand_execute_on_2\']');
		}

		switch ($new_operation_opcommand_type) {
			case 'Custom script':
			case 'SSH':
			case 'Telnet':
				$this->zbxTestTextPresent ('Commands');
				$this->assertVisible('//textarea[@id=\'new_operation_opcommand_command\']');
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_command\']/@rows', 7);
				break;
			case 'IPMI':
			case 'Global script':
				$this->assertNotVisible('//textarea[@id=\'new_operation_opcommand_command\']');
				break;
			default:
				$this->assertElementNotPresent('//textarea[@id=\'new_operation_opcommand_command\']');
				break;
		}

		if ($new_operation_opcommand_type == 'IPMI') {
			$this->zbxTestTextPresent ('Commands');
			$this->assertVisible('//input[@id=\'opcommand_command_ipmi\']');
			$this->assertAttribute('//*[@id=\'opcommand_command_ipmi\']/@maxlength', 255);
			$this->assertAttribute('//*[@id=\'opcommand_command_ipmi\']/@size', 50);
			$this->assertEquals($this->getValue('//input[@id=\'opcommand_command_ipmi\']'), "");
		}
		elseif ($new_operation_opcommand_type != null) {
			$this->assertNotVisible('//input[@id=\'opcommand_command_ipmi\']');
		}
		else {
			$this->assertElementNotPresent('//input[@id=\'opcommand_command_ipmi\']');
		}

		switch ($new_operation_opcommand_type) {
			case 'SSH':
				$this->zbxTestTextPresent ('Authentication method');
				$this->assertVisible('//select[@id=\'new_operation_opcommand_authtype\']');
				$this->zbxTestDropdownHasOptions('new_operation_opcommand_authtype', [
						'Password',
						'Public key'
				]);
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_authtype\']/option[text()=\'Password\']/@selected', 'selected');
				break;
			case 'IPMI':
			case 'Custom script':
			case 'Telnet':
			case 'Global script':
				$this->assertElementPresent('//*[@class=\'class_authentication_method hidden\']');
				break;
			default:
				$this->zbxTestTextNotPresent ('Authentication method');
				$this->assertElementNotPresent('//select[@id=\'new_operation_opcommand_authtype\']');
				break;
		}

		switch ($new_operation_opcommand_type) {
			case 'SSH':
			case 'Telnet':
				$this->zbxTestTextPresent ('User name');
				$this->assertVisible('//input[@id=\'new_operation_opcommand_username\']');
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_username\']/@maxlength', 255);
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_username\']/@size', 25);
				$this->assertEquals($this->getValue('//input[@id=\'new_operation_opcommand_username\']'), "");
				break;
			case 'IPMI':
			case 'Custom script':
			case 'Global script':
				$this->assertElementPresent('//*[@class=\'class_authentication_username hidden indent_both\']');
				break;
			default:
				$this->zbxTestTextNotPresent ('User name');
				$this->assertElementNotPresent('//input[@id=\'new_operation_opcommand_username\']');
				break;
		}

		switch ($new_operation_opcommand_authtype) {
			case 'Password':
				$this->zbxTestTextPresent ('Password');
				$this->assertVisible('//input[@id=\'new_operation_opcommand_password\']');
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_password\']/@maxlength', 255);
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_password\']/@size', 25);
				$this->assertEquals($this->getValue('//input[@id=\'new_operation_opcommand_password\']'), "");

				$this->assertElementPresent('//input[@id=\'new_operation_opcommand_passphrase\']/@disabled');

				$this->assertElementPresent('//input[@id=\'new_operation_opcommand_publickey\']/@disabled');
				$this->assertElementPresent('//input[@id=\'new_operation_opcommand_privatekey\']/@disabled');
				break;
			case 'Public key':
				$this->zbxTestTextPresent ('Key passphrase');
				$this->assertVisible('//input[@id=\'new_operation_opcommand_passphrase\']');
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_passphrase\']/@maxlength', 255);
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_passphrase\']/@size', 25);
				$this->assertEquals($this->getValue('//input[@id=\'new_operation_opcommand_passphrase\']'), "");

				$this->assertElementPresent('//input[@id=\'new_operation_opcommand_password\']/@disabled');

				$this->zbxTestTextPresent ('Public key file');
				$this->assertVisible('//input[@id=\'new_operation_opcommand_publickey\']');
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_publickey\']/@maxlength', 255);
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_publickey\']/@size', 25);
				$this->assertEquals($this->getValue('//input[@id=\'new_operation_opcommand_publickey\']'), "");

				$this->zbxTestTextPresent ('Private key file');
				$this->assertVisible('//input[@id=\'new_operation_opcommand_privatekey\']');
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_privatekey\']/@maxlength', 255);
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_privatekey\']/@size', 25);
				$this->assertEquals($this->getValue('//input[@id=\'new_operation_opcommand_privatekey\']'), "");
				break;
			default:
				if ($new_operation_opcommand_type != null) {
					$this->assertElementPresent('//input[@id=\'new_operation_opcommand_publickey\']/@disabled');
					$this->assertElementPresent('//input[@id=\'new_operation_opcommand_privatekey\']/@disabled');

					$this->assertElementPresent('//input[@id=\'new_operation_opcommand_passphrase\']/@disabled');
					$this->assertElementPresent('//input[@id=\'new_operation_opcommand_password\']/@disabled');
				}
				else {
					$this->assertElementNotPresent('//input[@id=\'new_operation_opcommand_password\']');
					$this->assertElementNotPresent('//input[@id=\'new_operation_opcommand_passphrase\']');

					$this->assertElementNotPresent('//input[@id=\'new_operation_opcommand_publickey\']');
					$this->assertElementNotPresent('//input[@id=\'new_operation_opcommand_privatekey\']');
				}
				break;
		}

		switch ($new_operation_opcommand_type) {
			case 'SSH':
			case 'Telnet':
				$this->zbxTestTextPresent ('Port');
				$this->assertVisible('//input[@id=\'new_operation_opcommand_port\']');
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_port\']/@maxlength', 255);
				$this->assertAttribute('//*[@id=\'new_operation_opcommand_port\']/@size', 25);
				$this->assertEquals($this->getValue('//input[@id=\'new_operation_opcommand_port\']'), "");
				break;
			case 'IPMI':
			case 'Custom script':
			case 'Global script':
				$this->assertElementPresent('//*[@class=\'class_opcommand_port hidden indent_both\']');
				break;
			default:
				$this->assertElementNotPresent('//input[@id=\'new_operation_opcommand_port\']');
				break;
		}

		if ($new_operation_opcommand_type == 'Global script') {
			$this->assertVisible('//input[@id=\'new_operation_opcommand_script\']');
			$this->assertAttribute('//*[@id=\'new_operation_opcommand_script\']/@maxlength', 255);
			$this->assertAttribute('//*[@id=\'new_operation_opcommand_script\']/@size',32);
			$this->assertAttribute('//*[@id=\'new_operation_opcommand_script\']/@readonly', 'readonly');
			$this->assertEquals($this->getValue('//input[@id=\'new_operation_opcommand_script\']'), "");
		}
		elseif ($new_operation_operationtype == 'Remote command') {
			$this->assertNotVisible('//input[@id=\'new_operation_opcommand_script\']');
		}
		else {
			$this->assertElementNotPresent('//input[@id=\'new_operation_opcommand_script\']');
		}

		switch ($new_operation_operationtype) {
			case 'Add to host group':
			case 'Remove from host group':
				$this->assertVisible('//div[@id=\'discoveryHostGroup\']/input');
				$this->assertElementNotPresent('//div[@id=\'discoveryTemplates\']/input');
				break;
			case 'Link to template':
			case 'Unlink from template':
				$this->assertVisible('//div[@id=\'discoveryTemplates\']/input');
				$this->assertElementNotPresent('//div[@id=\'discoveryHostGroup\']/input');
				break;
			default:
				$this->assertElementNotPresent('//div[@id=\'discoveryHostGroup\']/input');
				$this->assertElementNotPresent('//div[@id=\'discoveryTemplates\']/input');
				break;
		}

		if ($new_operation_operationtype != null) {
			$this->assertVisible('add_operation');
			$this->assertAttribute('//input[@id=\'add_operation\']/@value', 'Add');

			$this->assertVisible('cancel_new_operation');
			$this->assertAttribute('//input[@id=\'cancel_new_operation\']/@value', 'Cancel');
		}
		else {
			$this->assertElementNotPresent('add_operation');
			$this->assertElementNotPresent('cancel_new_operation');
		}

		$this->assertVisible('update');
		$this->assertAttribute('//input[@id=\'update\']/@value', 'Update');

		$this->assertVisible('cancel');
		$this->assertAttribute('//input[@id=\'cancel\']/@value', 'Cancel');
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

		$sqlActions = "SELECT * FROM actions ORDER BY actionid";
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

		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestTextPresent([
				'Action updated',
				'CONFIGURATION OF ACTIONS',
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
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
						'Field "operations" is mandatory.'
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
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
						'Field "operations" is mandatory.'
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
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
						'Field "operations" is mandatory.'
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
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
						'Field "operations" is mandatory.'
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

		if (isset($data['name'])){
			$this->input_type('name', $data['name']);
		}

		if (isset($data['def_shortdata'])){
			$this->input_type('def_shortdata', $data['def_shortdata']);
		}

		if (isset($data['def_longdata'])){
			$this->input_type('def_longdata', $data['def_longdata']);
		}

		if (isset($data['conditions'])) {
			$this->zbxTestClick('link=Conditions');
			foreach ($data['conditions'] as $condition) {
				$this->zbxTestDropdownSelectWait('new_condition_conditiontype', $condition['type']);
				switch ($condition['type']) {
					case 'Application':
					case 'Host name':
					case 'Host metadata':
					case 'Trigger name':
						$this->input_type('new_condition_value', $condition['value']);
						$this->zbxTestClickWait('add_condition');
						switch($condition['type']){
							case 'Application':
								$this->zbxTestTextPresent('Application = '.$condition['value']);
								break;
							case 'Host name':
								$this->zbxTestTextPresent('Host name like '.$condition['value']);
								break;
							case 'Host metadata':
								$this->zbxTestTextPresent('Host metadata like '.$condition['value']);
								break;
							case 'Trigger name':
								$this->zbxTestTextPresent('Trigger name like '.$condition['value']);
								break;
						}
						break;
					case 'Trigger severity':
					case 'Service type':
					case 'Event type':
						$this->zbxTestDropdownSelect('new_condition_value', $condition['value']);
						$this->zbxTestClickWait('add_condition');
						switch($condition['type']){
							case 'Trigger severity':
								$this->zbxTestTextPresent('Trigger severity = '.$condition['value']);
								break;
							case 'Service type':
								$this->zbxTestTextPresent('Service type = '.$condition['value']);
								break;
							case 'Event type':
								$this->zbxTestTextPresent('Event type = '.$condition['value']);
								break;
						}
						break;
				}
			}
		}

		if (isset($data['operations'])) {
			$this->zbxTestClick('link=Operations');
			foreach ($data['operations'] as $operation) {
				$this->zbxTestClickWait('new_operation');
			if ($data['eventsource']!= EVENT_SOURCE_INTERNAL){
				$this->zbxTestDropdownSelectWait('new_operation_operationtype', $operation['type']);
			}
				switch ($operation['type']) {
					case 'Send message':
						sleep(1);

						$this->zbxTestLaunchPopup('addusrgrpbtn');
						$this->zbxTestClick('all_usrgrps');
						$this->zbxTestClick('select');
						$this->selectWindow('null');

						sleep(1);

						$this->zbxTestLaunchPopup('adduserbtn');
						$this->zbxTestClick('all_users');
						$this->zbxTestClick('select');
						$this->selectWindow('null');

						$this->select('new_operation_opmessage_mediatypeid', $operation['media']);
						break;
					case 'Remote command':
						$this->zbxTestClick('add');
						$this->zbxTestClick("//input[@name='add']");
						$this->type('new_operation_opcommand_command', $operation['command']);
						break;
					case 'Remote command':
						$this->zbxTestClick('add');
						break;
				}
				$this->zbxTestClickWait('add_operation');
			}
		}

		if (isset($data['esc_period'])){
			$this->input_type('esc_period', $data['esc_period']);
			$this->wait();
		}

		sleep(1);
		$this->zbxTestClick('search');
		sleep(1);

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case ACTION_GOOD:
				$this->zbxTestCheckTitle('Configuration of actions');
				$this->zbxTestTextPresent('CONFIGURATION OF ACTIONS');
				$this->zbxTestTextPresent('Action added');
				break;

			case ACTION_BAD:
				$this->zbxTestCheckTitle('Configuration of actions');
				$this->zbxTestTextPresent('CONFIGURATION OF ACTIONS');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}

	public function testFormAction_Create() {
		$this->zbxTestLogin('actionconf.php?form=1&eventsource=0');
		$this->zbxTestCheckTitle('Configuration of actions');

		$this->type("name", "action test");
		$this->type("def_shortdata", "subject");
		$this->type("def_longdata", "message");

// adding conditions
		$this->zbxTestClick('link=Conditions');
		$this->type("new_condition_value", "trigger");
		$this->zbxTestClickWait('add_condition');
		$this->zbxTestTextPresent("Trigger name like trigger");

		$this->select("new_condition_conditiontype", "label=Trigger severity");
		$this->wait();
		$this->select("new_condition_value", "label=Average");
		$this->zbxTestClickWait('add_condition');
		$this->zbxTestTextPresent("Trigger severity = Average");

		$this->select("new_condition_conditiontype", "label=Application");
		$this->wait();
		$this->type("new_condition_value", "app");
		$this->zbxTestClickWait('add_condition');
		$this->zbxTestTextPresent("Application = app");

// adding operations
		$this->zbxTestClick('link=Operations');
		$this->zbxTestClickWait('new_operation');
		sleep(1);
		$this->zbxTestLaunchPopup('addusrgrpbtn');
		$this->zbxTestClick('usrgrps_7');
		$this->zbxTestClick('usrgrps_11');
		$this->zbxTestClick('select');
		$this->selectWindow('null');
		sleep(1);
		$this->zbxTestLaunchPopup('adduserbtn');
		$this->zbxTestClick("users_'1'");
		$this->zbxTestClick('select');
		$this->selectWindow('null');
		$this->select("new_operation_opmessage_mediatypeid", "label=Jabber");
		$this->zbxTestClickWait('add_operation');
		$this->zbxTestTextPresent("Send message to users: Admin (Zabbix Administrator) via Jabber");
		$this->zbxTestTextPresent("Send message to user groups: Enabled debug mode, Zabbix administrators via Jabber");
		$this->zbxTestClickWait('new_operation');
		$this->select("new_operation_operationtype", "label=Remote command");
		$this->wait();
// add target current host
		$this->zbxTestClick('add');
		$this->zbxTestClick("//input[@name='add']");

// add target host Zabbix server
		$this->zbxTestClick('add');
		sleep(1);
		$this->select("opCmdTarget", "label=Host");
		$this->zbxTestTextPresent(['Target list', 'Target', 'Action']);
		sleep(1);
		$this->assertElementPresent("//div[@id='opCmdTargetObject']/input");
		$this->input_type("//div[@id='opCmdTargetObject']/input", 'Simple form test host');
		sleep(1);
		$this->zbxTestClick("//span[@class='matched']");
		$this->zbxTestClick("//input[@name='add']");

		sleep(1);
// add target group Zabbix servers
		$this->zbxTestClick('add');
		sleep(1);
		$this->select("opCmdTarget", "label=Host group");
		$this->zbxTestTextPresent(['Target list', 'Target', 'Action']);
		sleep(1);
		$this->assertElementPresent("//div[@id='opCmdTargetObject']/input");
		$this->input_type("//div[@id='opCmdTargetObject']/input", 'Zabbix servers');
		sleep(1);
		$this->zbxTestClick("//span[@class='matched']");
		$this->zbxTestClick("//input[@name='add']");

		sleep(1);

		$this->type("new_operation_opcommand_command", "command");
		$this->zbxTestClickWait('add_operation');
		$this->zbxTestTextPresent('Send message to users: Admin (Zabbix Administrator) via Jabber');
		$this->zbxTestTextPresent("Send message to user groups: Enabled debug mode, Zabbix administrators via Jabber");
		$this->zbxTestTextPresent("Run remote commands on current host");
		$this->zbxTestTextPresent('Run remote commands on hosts: Simple form test host');
		$this->zbxTestTextPresent('Run remote commands on host groups: Zabbix servers');

		$this->zbxTestClickWait('new_operation');
		$this->type("new_operation_esc_step_to", "2");
		$this->select("new_operation_operationtype", "label=Remote command");
		$this->wait();
		$this->zbxTestClick('add');
		$this->zbxTestClick("//input[@name='add']");
		$this->select("new_operation_opcommand_type", "label=SSH");
		$this->type("new_operation_opcommand_username", "user");
		$this->type("new_operation_opcommand_password", "pass");
		$this->type("new_operation_opcommand_port", "123");
		$this->type("new_operation_opcommand_command", "command ssh");
		$this->zbxTestClickWait('add_operation');

		$this->zbxTestTextPresent('Send message to users: Admin (Zabbix Administrator) via Jabber');
		$this->zbxTestTextPresent("Send message to user groups: Enabled debug mode, Zabbix administrators via Jabber");
		$this->zbxTestTextPresent("Run remote commands on current host");
		$this->zbxTestTextPresent('Run remote commands on hosts: Simple form test host');
		$this->zbxTestTextPresent('Run remote commands on host groups: Zabbix servers');

		$this->type("esc_period", "123");
		sleep(1);
		$this->type('new_condition_value', '');
		sleep(1);

		$this->zbxTestClickWait('add');
		$this->zbxTestTextPresent('Action added');
	}

	public function testFormAction_Teardown() {
		DBrestore_tables('actions');
	}
}
