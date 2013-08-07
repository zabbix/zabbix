<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

class testFormAction extends CWebTest {

	public function testFormAction_Setup() {
	//	DBsave_tables('actions');
	}

	public static function layout() {
		return array(
			array(
				array('event_source' => 'Triggers',)
			),
			array(
				array('event_source' => 'Discovery',)
			),
			array(
				array('event_source' => 'Auto registration',)
			),
			array(
				array('event_source' => 'Internal',)
			)
		);
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormAction_CheckLayout($data) {
		$this->zbxTestLogin('actionconf.php');
			if (isset($data['event_source'])) {
				$this->zbxTestDropdownSelectWait('//select[@id=\'eventsource\']', $data['event_source']);
			}
		$eventsource = $this->getSelectedLabel('//select[@id=\'eventsource\']');
		$this->zbxTestClickWait('form');

		$this->checkTitle('Configuration of actions');
		$this->zbxTestTextPresent(array(
				'CONFIGURATION OF ACTIONS',
				'Action', 'Conditions', 'Operations'
		));

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

		if ($eventsource == 'Triggers' || $eventsource == 'Internal') {
			$this->zbxTestTextPresent('Recovery message');
			$this->assertElementPresent('recovery_msg');
			$this->assertElementPresent("//input[@type='checkbox' and @id='recovery_msg']");
		}
		else {
			$this->zbxTestTextNotPresent('Recovery message');
			$this->assertElementNotPresent('recovery_msg');
			$this->assertElementNotPresent("//input[@type='checkbox' and @id='recovery_msg']");
		}

		$this->zbxTestTextPresent('Enabled');
		$this->assertElementPresent('status');
		$this->assertElementPresent("//input[@type='checkbox' and @id='status']");
		$this->assertAttribute("//*[@id='status']/@checked", 'checked');

		$this->zbxTestClick('link=Conditions');
		if ($eventsource == 'Triggers') {
			$this->zbxTestTextPresent(array(
					'Type of calculation', '(A) and (B)'
			));
			$this->assertElementPresent('evaltype');
			$this->zbxTestDropdownHasOptions('evaltype', array(
					'AND / OR',
					'AND',
					'OR'
			));
			$this->assertAttribute('//*[@id=\'evaltype\']/option[text()=\'AND / OR\']/@selected', 'selected');
		}
		else {
			$this->zbxTestTextNotPresent(array(
					'Type of calculation', '(A) and (B)'
			));
			$this->assertNotVisible('evaltype');
		}

		$this->zbxTestTextPresent(array(
				'Conditions',
				'Label', 'Name', 'Action'
		));

		if ($eventsource == 'Triggers') {
			$this->zbxTestTextPresent(array(
					'(A)', 'Maintenance status not in maintenance','(B)', 'Trigger value = PROBLEM'
			));
			$this->assertElementPresent('//input[@id="remove" and @value="Remove" and @onclick="javascript:'.
				' removeCondition(0);"]');
			$this->assertElementPresent('//input[@id="remove" and @value="Remove" and @onclick="javascript:'.
				' removeCondition(1);"]');
		}
		else {
			$this->zbxTestTextNotPresent(array(
					'(A)', 'Maintenance status not in maintenance','(B)', 'Trigger value = PROBLEM'
			));
			$this->assertElementNotPresent('//input[@id="remove" and @value="Remove" and @onclick="javascript:'.
				' removeCondition(0);"]');
			$this->assertElementNotPresent('//input[@id="remove" and @value="Remove" and @onclick="javascript:'.
				' removeCondition(1);"]');
		}

		$this->zbxTestTextPresent('New condition');
		$this->assertElementPresent('new_condition_conditiontype');
		switch ($eventsource) {
			case 'Triggers':
				$this->zbxTestDropdownHasOptions('new_condition_conditiontype', array(
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
				));
				break;
			case 'Discovery':
				$this->zbxTestDropdownHasOptions('new_condition_conditiontype', array(
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
				));
				break;
			case 'Auto registration':
				$this->zbxTestDropdownHasOptions('new_condition_conditiontype', array(
						'Host name',
						'Proxy',
						'Host metadata'
				));
				break;
			case 'Internal':
				$this->zbxTestDropdownHasOptions('new_condition_conditiontype', array(
						'Application',
						'Event type',
						'Host group',
						'Template',
						'Host'
				));
				break;
		}


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


		$this->assertElementPresent('new_condition_operator');
		switch ($eventsource) {
			case 'Discovery':
				$this->zbxTestDropdownHasOptions('new_condition_operator', array(
						'=',
						'<>'
				));
				break;
			default:
				$this->zbxTestDropdownHasOptions('new_condition_operator', array(
						'like',
						'not like'
				));
				break;
		}

		switch ($eventsource) {
			case 'Triggers':
			case 'Auto registration':
			case 'Internal':
				$this->assertAttribute('//*[@id=\'new_condition_operator\']/option[text()=\'like\']/@selected', 'selected');
				break;
			default:
				$this->assertElementNotPresent('//*[@id=\'new_condition_operator\']/option[text()=\'like\']/@selected');
				break;
		}

		$this->assertElementPresent('new_condition_value');
		$this->assertAttribute('//input[@id=\'new_condition_value\']/@maxlength', 255);
		$this->assertAttribute('//input[@id=\'new_condition_value\']/@size', 50);
		switch ($eventsource) {
			case 'Discovery':
				$this->assertEquals($this->getValue('new_condition_value'), '192.168.0.1-127,192.168.2.1');
				break;
			default:
				$this->assertEquals($this->getValue('new_condition_value'), "");
				break;
		}

		$this->assertElementPresent('add_condition');
		$this->assertAttribute('//input[@id=\'add_condition\']/@value','Add');

		$this->zbxTestClick('link=Conditions');

		switch ($eventsource) {
			case 'Triggers':
			case 'Internal':
				$this->zbxTestTextPresent(array(
						'Default operation step duration',	'(minimum 60 seconds)'
				));
				$this->assertElementPresent('esc_period');
				$this->assertAttribute('//input[@id=\'esc_period\']/@maxlength', 6);
				$this->assertAttribute('//input[@id=\'esc_period\']/@size', 6);
				$this->assertAttribute('//input[@id=\'esc_period\']/@value', 3600);
				break;
			default:
				$this->zbxTestTextNotPresent(array(
						'Default operation step duration',	'(minimum 60 seconds)'
				));
				$this->assertElementNotPresent('esc_period');
				break;
		}

		$this->zbxTestTextPresent(array(
				'Action operations',
				'Details', 'Action',
				'No operations defined.'
		));
		switch ($eventsource) {
			case 'Triggers':
			case 'Internal':
				$this->zbxTestTextPresent(array(
						'Steps', 'Start in', 'Duration (sec)'
				));
				break;
			default:
				$this->zbxTestTextNotPresent(array(
						'Steps', 'Start in', 'Duration (sec)'
				));
				break;
		}

		$this->assertElementPresent('new_operation');
		$this->assertAttribute('//input[@id=\'new_operation\']/@value','New');

		$this->assertVisible('save');
		$this->assertAttribute('//input[@id=\'save\']/@value', 'Save');

		$this->assertVisible('cancel');
		$this->assertAttribute('//input[@id=\'cancel\']/@value', 'Cancel');

	}

/*	public static function providerNewActions() {
		$data = array(
			array(array(
				'name' => 'action test 2',
				'esc_period' => '123',
				'def_shortdata' => 'def_shortdata',
				'def_longdata' => 'def_longdata',
				'conditions' => array(
					array(
						'type' => 'Trigger name',
						'value' => 'trigger',
					),
					array(
						'type' => 'Trigger severity',
						'value' => 'Warning',
					),
					array(
						'type' => 'Application',
						'value' => 'application',
					),
				),
				'operations' => array(
					array(
						'type' => 'Send message',
						'media' => 'Email',
					),
					array(
						'type' => 'Remote command',
						'command' => 'command',
					)
				),
			)),
		);
		return $data;
	}
*/
	/**
	 * @dataProvider providerNewActions
	 */
/*	public function testFormAction_CreateSimple($action) {

		$this->zbxTestLogin('actionconf.php?form=1&eventsource=0');
		$this->checkTitle('Configuration of actions');

		$this->type('name', $action['name']);
		$this->type("def_shortdata", $action['def_shortdata']);
		$this->type("def_longdata", $action['def_longdata']);

		$this->zbxTestClick('link=Conditions');
		foreach ($action['conditions'] as $condition) {

			$this->zbxTestDropdownSelectWait("new_condition_conditiontype", $condition['type']);
			switch ($condition['type']) {
				case 'Trigger name':
					$this->type("new_condition_value", $condition['value']);
					$this->zbxTestClickWait('add_condition');
					$this->zbxTestTextPresent('Trigger name like '.$condition['value']);
					break;
				case 'Trigger severity':
					$this->zbxTestDropdownSelect('new_condition_value', $condition['value']);
					$this->zbxTestClickWait('add_condition');
					$this->zbxTestTextPresent('Trigger severity = '.$condition['value']);
					break;
				case 'Application':
					$this->type('new_condition_value', $condition['value']);
					$this->zbxTestClickWait('add_condition');
					$this->zbxTestTextPresent('Application = '.$condition['value']);
					break;
			}
		}
		$this->zbxTestClick('link=Operations');

		foreach ($action['operations'] as $operation) {
			$this->zbxTestClickWait('new_operation');
			$this->zbxTestDropdownSelectWait('new_operation_operationtype', $operation['type']);

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
					$this->zbxTestClick("//input[@name='save']");
					$this->type('new_operation_opcommand_command', $operation['command']);
					break;
			}
			$this->zbxTestClickWait('add_operation');
		}
		$this->type('esc_period', $action['esc_period']);
		$this->zbxTestClickWait('save');

		sleep(1);
		$this->type('new_condition_value', '');
		sleep(1);

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Action added');
	}

	public function testFormAction_Create() {

		$this->zbxTestLogin('actionconf.php?form=1&eventsource=0');
		$this->checkTitle('Configuration of actions');

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
		$this->zbxTestTextPresent("Send message to users: Admin via Jabber");
		$this->zbxTestTextPresent("Send message to user groups: Enabled debug mode, Zabbix administrators via Jabber");
		$this->zbxTestClickWait('new_operation');
		$this->select("new_operation_operationtype", "label=Remote command");
		$this->wait();
// add target current host
		$this->zbxTestClick('add');
		$this->zbxTestClick("//input[@name='save']");

// add target host Zabbix server
		$this->zbxTestClick('add');
		sleep(1);
		$this->select("opCmdTarget", "label=Host");
		$this->zbxTestTextPresent(array('Target list', 'Target', 'Action'));
		sleep(1);
		$this->assertElementPresent("//div[@id='opCmdTargetObject']/input");
		$this->input_type("//div[@id='opCmdTargetObject']/input", 'Simple form test host');
		sleep(1);
		$this->zbxTestClick("//span[@class='matched']");
		$this->zbxTestClick("//input[@name='save']");

		sleep(1);
// add target group Zabbix servers
		$this->zbxTestClick('add');
		sleep(1);
		$this->select("opCmdTarget", "label=Host group");
		$this->zbxTestTextPresent(array('Target list', 'Target', 'Action'));
		sleep(1);
		$this->assertElementPresent("//div[@id='opCmdTargetObject']/input");
		$this->input_type("//div[@id='opCmdTargetObject']/input", 'Zabbix servers');
		sleep(1);
		$this->zbxTestClick("//span[@class='matched']");
		$this->zbxTestClick("//input[@name='save']");

		sleep(1);

		$this->type("new_operation_opcommand_command", "command");
		$this->zbxTestClickWait('add_operation');
		$this->zbxTestTextPresent('Send message to users: Admin via Jabber');
		$this->zbxTestTextPresent("Send message to user groups: Enabled debug mode, Zabbix administrators via Jabber");
		$this->zbxTestTextPresent("Run remote commands on current host");
		$this->zbxTestTextPresent('Run remote commands on hosts: Simple form test host');
		$this->zbxTestTextPresent('Run remote commands on host groups: Zabbix servers');

		$this->zbxTestClickWait('new_operation');
		$this->type("new_operation_esc_step_to", "2");
		$this->select("new_operation_operationtype", "label=Remote command");
		$this->wait();
		$this->zbxTestClick('add');
		$this->zbxTestClick("//input[@name='save']");
		$this->select("new_operation_opcommand_type", "label=SSH");
		$this->type("new_operation_opcommand_username", "user");
		$this->type("new_operation_opcommand_password", "pass");
		$this->type("new_operation_opcommand_port", "123");
		$this->type("new_operation_opcommand_command", "command ssh");
		$this->zbxTestClickWait('add_operation');

		$this->zbxTestTextPresent('Send message to users: Admin via Jabber');
		$this->zbxTestTextPresent("Send message to user groups: Enabled debug mode, Zabbix administrators via Jabber");
		$this->zbxTestTextPresent("Run remote commands on current host");
		$this->zbxTestTextPresent('Run remote commands on hosts: Simple form test host');
		$this->zbxTestTextPresent('Run remote commands on host groups: Zabbix servers');

		$this->type("esc_period", "123");
		sleep(1);
		$this->type('new_condition_value', '');
		sleep(1);

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Action added');
	}
*/
	public function testFormAction_Teardown() {
	//	DBrestore_tables('actions');
	}
}
