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
		DBsave_tables('actions');
	}

	public static function providerNewActions() {
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

	/**
	 * @dataProvider providerNewActions
	 */
	public function testFormAction_CreateSimple($action) {

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

	public function testFormAction_Teardown() {
		DBrestore_tables('actions');
	}
}
