<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

define('ITEM_GOOD', 0);
define('ITEM_BAD', 1);

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testInheritanceItemPrototype extends CWebTest {

	/**
	 * The name of the test template created in the test data set.
	 *
	 * @var string
	 */
	protected $template = 'Inheritance test template';

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Template inheritance test host';

	/**
	 * The name of the test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRule = 'discoveryRuleTest';


	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testInheritanceItemPrototype_setup() {
		DBsave_tables('items');
	}
	// returns all possible item types
	public static function itemTypes() {
		return array(
			array(
				array('type' => 'Zabbix agent')
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Numeric (unsigned)', 'data_type' => 'Boolean')
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Numeric (unsigned)', 'data_type' => 'Hexadecimal')
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Numeric (unsigned)', 'data_type' => 'Octal')
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Numeric (float)')
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Character')
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Log')
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Text')
			),
			array(
				array('type' => 'Zabbix agent (active)'),
			),
			array(
				array('type' => 'Simple check')
			),
			array(
				array('type' => 'SNMPv1 agent')
			),
			array(
				array('type' => 'SNMPv1 agent', 'value_type' => 'Numeric (float)')
			),
			array(
				array('type' => 'SNMPv2 agent')
			),
			array(
				array('type' => 'SNMPv3 agent')
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Hexadecimal'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Octal'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (float)'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Character'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Log')
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Text'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authNoPriv'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authPriv'
				)
			),
			array(
				array('type' => 'SNMP trap')
			),
			array(
				array('type' => 'Zabbix internal')
			),
			array(
				array('type' => 'Zabbix internal', 'value_type' => 'Numeric (unsigned)', 'data_type' => 'Boolean')
			),
			array(
				array('type' => 'Zabbix trapper')
			),
			array(
				array('type' => 'Zabbix aggregate')
			),
			array(
				array('type' => 'External check')
			),
			array(
				array('type' => 'Database monitor')
			),
			array(
				array('type' => 'IPMI agent')
			),
			array(
				array('type' => 'SSH agent')
			),
			array(
				array('type' => 'SSH agent', 'authtype' => 'Public key')
			),
			array(
				array('type' => 'SSH agent', 'authtype' => 'Password')
			),
			array(
				array('type' => 'SSH agent', 'value_type' => 'Numeric (unsigned)', 'data_type' => 'Boolean')
			),
			array(
				array('type' => 'SSH agent', 'authtype' => 'Password', 'value_type' => 'Character')
			),
			array(
				array('type' => 'TELNET agent')
			),
			array(
				array('type' => 'JMX agent')
			),
			array(
				array('type' => 'Calculated')
			)
		);
	}

	/**
	 * @dataProvider itemTypes
	 */
	public function testFormItem_CheckLayout($data) {
		$this->zbxTestLogin('templates.php');

		$this->zbxTestClickWait('link='.$this->template);
		$this->zbxTestClickWait("link=Discovery rules");
		$this->zbxTestClickWait('link='.$this->discoveryRule);
		$this->zbxTestClickWait("link=Item prototypes");

		$this->checkTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent(array('CONFIGURATION OF ITEM PROTOTYPES', "Item prototypes of ".$this->discoveryRule));

		$this->zbxTestClickWait('form');
		$this->checkTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent(array('CONFIGURATION OF ITEM PROTOTYPES', 'Item prototype'));

		$this->zbxTestTextPresent('Name');
		$this->assertVisible('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", 255);
		$this->assertAttribute("//input[@id='name']/@size", 50);
		$this->assertAttribute("//input[@id='name']/@autofocus", 'autofocus');

		$this->zbxTestTextPresent('Type');
		$this->assertVisible('type');
		$this->zbxTestDropdownHasOptions('type', array(
			'Zabbix agent',
			'Zabbix agent (active)',
			'Simple check',
			'SNMPv1 agent',
			'SNMPv2 agent',
			'SNMPv3 agent',
			'SNMP trap',
			'Zabbix internal',
			'Zabbix trapper',
			'Zabbix aggregate',
			'External check',
			'Database monitor',
			'IPMI agent',
			'SSH agent',
			'TELNET agent',
			'JMX agent',
			'Calculated'
		));
		$this->zbxTestDropdownSelect('type', $data['type']);

		$this->zbxTestTextPresent('Key');
		$this->assertVisible('key');
		$this->assertAttribute("//input[@id='key']/@maxlength", 255);
		$this->assertAttribute("//input[@id='key']/@size", 50);
		$this->assertElementPresent('keyButton');

		if ($data['type'] == 'Database monitor') {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "db.odbc.select[<unique short description>]");
		}

		if ($data['type'] == 'SSH agent') {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "ssh.run[<unique short description>,<ip>,<port>,<encoding>]");
		}

		if ($data['type'] == 'TELNET agent') {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "telnet.run[<unique short description>,<ip>,<port>,<encoding>]");
		}

		if ($data['type'] == 'JMX agent') {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "jmx[<object name>,<attribute name>]");
		}

		if ($data['type'] == 'SNMPv3 agent') {
			if (isset($data['snmpv3_securitylevel'])) {
				$this->zbxTestDropdownSelect('snmpv3_securitylevel', $data['snmpv3_securitylevel']);
			}
			$snmpv3_securitylevel = $this->getSelectedLabel('snmpv3_securitylevel');
		}
		else {
			$snmpv3_securitylevel = null;
		}

		if (isset($data['value_type'])) {
			$this->zbxTestDropdownSelect('value_type', $data['value_type']);
		}
		$value_type = $this->getSelectedLabel('value_type');

		if ($value_type == 'Numeric (unsigned)') {
			if (isset($data['data_type'])) {
				$this->zbxTestDropdownSelect('data_type', $data['data_type']);
			}
			$data_type = $this->getSelectedLabel('data_type');
		}

		if ($data['type'] == 'SSH agent') {
			if (isset($data['authtype'])) {
				$this->zbxTestDropdownSelect('authtype', $data['authtype']);
			}
			$authtype = $this->getSelectedLabel('authtype');
		}
		else {
			$authtype = null;
		}

		if ($data['type'] == 'Database monitor') {
			$this->zbxTestTextPresent('Additional parameters');
			$this->assertVisible('params_ap');
			$this->assertAttribute("//textarea[@id='params_ap']/@rows", 7);
			$addParams = $this->getValue('params_ap');
			$this->assertEquals($addParams, "DSN=<database source name>\nuser=<user name>\npassword=<password>\nsql=<query>");
		}
		else {
			$this->zbxTestTextNotPresent('Additional parameters');
			$this->assertNotVisible('params_ap');
		}

		if ($data['type'] == 'SSH agent' || $data['type'] == 'TELNET agent' ) {
			$this->zbxTestTextPresent('Executed script');
			$this->assertVisible('params_es');
			$this->assertAttribute("//textarea[@id='params_es']/@rows", 7);
		}
		else {
			$this->zbxTestTextNotPresent('Executed script');
			$this->assertNotVisible('params_es');
		}

		if ($data['type'] == 'Calculated') {
			$this->zbxTestTextPresent('Formula');
			$this->assertVisible('params_f');
			$this->assertAttribute("//textarea[@id='params_f']/@rows", 7);
		}
		else {
			$this->zbxTestTextNotPresent('Formula');
			$this->assertNotVisible('params_f');
		}

		if ($data['type'] == 'IPMI agent') {
			$this->zbxTestTextPresent('IPMI sensor');
			$this->assertVisible('ipmi_sensor');
			$this->assertAttribute("//input[@id='ipmi_sensor']/@maxlength", 128);
			$this->assertAttribute("//input[@id='ipmi_sensor']/@size", 50);
		}
		else {
			$this->zbxTestTextNotPresent('IPMI sensor');
			$this->assertNotVisible('ipmi_sensor');
		}

		if ($data['type'] == 'SSH agent') {
			$this->zbxTestTextPresent('Authentication method');
			$this->assertVisible('authtype');
			$this->zbxTestDropdownHasOptions('authtype', array('Password', 'Public key'));
		}
		else {
			$this->zbxTestTextNotPresent('Authentication method');
			$this->assertNotVisible('authtype');
		}

		if ($data['type'] == 'SSH agent' || $data['type'] == 'TELNET agent' || $data['type'] == 'JMX agent') {
			$this->zbxTestTextPresent('User name');
			$this->assertVisible('username');
			$this->assertAttribute("//input[@id='username']/@maxlength", 64);
			$this->assertAttribute("//input[@id='username']/@size", 25);

			if ($authtype == 'Public key') {
				$this->zbxTestTextPresent('Key passphrase');
			}
			else {
				$this->zbxTestTextPresent('Password');
			}
			$this->assertVisible('password');
			$this->assertAttribute("//input[@id='password']/@maxlength", 64);
			$this->assertAttribute("//input[@id='password']/@size", 25);
		}
		else {
			$this->zbxTestTextNotPresent(array('User name', 'Password', 'Key passphrase'));
			$this->assertNotVisible('username');
			$this->assertNotVisible('password');
		}

		if	($data['type'] == 'SSH agent' && $authtype == 'Public key') {
			$this->zbxTestTextPresent('Public key file');
			$this->assertVisible('publickey');
			$this->assertAttribute("//input[@id='publickey']/@maxlength", 64);
			$this->assertAttribute("//input[@id='publickey']/@size", 25);

			$this->zbxTestTextPresent('Private key file');
			$this->assertVisible('privatekey');
			$this->assertAttribute("//input[@id='privatekey']/@maxlength", 64);
			$this->assertAttribute("//input[@id='privatekey']/@size", 25);
		}
		else {
			$this->zbxTestTextNotPresent('Public key file');
			$this->assertNotVisible('publickey');

			$this->zbxTestTextNotPresent('Private key file');
			$this->assertNotVisible('publickey');
		}

		if	($data['type'] == 'SNMPv1 agent' || $data['type'] == 'SNMPv2 agent' || $data['type'] == 'SNMPv3 agent') {
			$this->zbxTestTextPresent('SNMP OID');
			$this->assertVisible('snmp_oid');
			$this->assertAttribute("//input[@id='snmp_oid']/@maxlength", 255);
			$this->assertAttribute("//input[@id='snmp_oid']/@size", 50);
			$this->assertAttribute("//input[@id='snmp_oid']/@value", 'interfaces.ifTable.ifEntry.ifInOctets.1');

			$this->zbxTestTextPresent('Port');
			$this->assertVisible('port');
			$this->assertAttribute("//input[@id='port']/@maxlength", 64);
			$this->assertAttribute("//input[@id='port']/@size", 25);
		}
		else {
			$this->zbxTestTextNotPresent('SNMP OID');
			$this->assertNotVisible('snmp_oid');

			$this->zbxTestTextNotPresent('Port');
			$this->assertNotVisible('port');
		}

		if	($data['type'] == 'SNMPv1 agent' || $data['type'] == 'SNMPv2 agent') {
			$this->zbxTestTextPresent('SNMP community');
			$this->assertVisible('snmp_community');
			$this->assertAttribute("//input[@id='snmp_community']/@maxlength", 64);
			$this->assertAttribute("//input[@id='snmp_community']/@size", 50);
			$this->assertAttribute("//input[@id='snmp_community']/@value", 'public');
		}
		else {
			$this->zbxTestTextNotPresent('SNMP community');
			$this->assertNotVisible('snmp_community');
		}

		if	($data['type'] == 'SNMPv3 agent') {
			$this->zbxTestTextPresent('Security name');
			$this->assertVisible('snmpv3_securityname');
			$this->assertAttribute("//input[@id='snmpv3_securityname']/@maxlength", 64);
			$this->assertAttribute("//input[@id='snmpv3_securityname']/@size", 50);

			$this->zbxTestTextPresent('Security level');
			$this->assertVisible('snmpv3_securitylevel');
			$this->zbxTestDropdownHasOptions('snmpv3_securitylevel', array('noAuthNoPriv', 'authNoPriv', 'authPriv'));
		}
		else {
			$this->zbxTestTextNotPresent('Security name');
			$this->assertNotVisible('snmpv3_securityname');

			$this->zbxTestTextNotPresent('Security level');
			$this->assertNotVisible('snmpv3_securitylevel');
		}

		if ($snmpv3_securitylevel == 'authNoPriv' || $snmpv3_securitylevel == 'authPriv') {
			$this->zbxTestTextPresent('Authentication protocol');
			$this->assertVisible('row_snmpv3_authprotocol');
			$this->assertVisible("//span[text()='MD5']");
			$this->assertVisible("//span[text()='SHA']");

			$this->zbxTestTextPresent('Authentication passphrase');
			$this->assertVisible('snmpv3_authpassphrase');
			$this->assertAttribute("//input[@id='snmpv3_authpassphrase']/@maxlength", 64);
			$this->assertAttribute("//input[@id='snmpv3_authpassphrase']/@size", 50);
		}
		else {
			$this->zbxTestTextNotPresent('Authentication protocol');
			$this->assertNotVisible('row_snmpv3_authprotocol');
			$this->assertNotVisible("//span[text()='MD5']");
			$this->assertNotVisible("//span[text()='SHA']");

			$this->zbxTestTextNotPresent('Authentication passphrase');
			$this->assertNotVisible('snmpv3_authpassphrase');
		}

		if ($snmpv3_securitylevel == 'authPriv') {
			$this->zbxTestTextPresent('Privacy protocol');
			$this->assertVisible('row_snmpv3_privprotocol');
			$this->assertVisible("//span[text()='DES']");
			$this->assertVisible("//span[text()='AES']");

			$this->zbxTestTextPresent('Privacy passphrase');
			$this->assertVisible('snmpv3_privpassphrase');
			$this->assertAttribute("//input[@id='snmpv3_privpassphrase']/@maxlength", 64);
			$this->assertAttribute("//input[@id='snmpv3_privpassphrase']/@size", 50);
		}
		else {
			$this->zbxTestTextNotPresent('Privacy protocol');
			$this->assertNotVisible('row_snmpv3_privprotocol');
			$this->assertNotVisible("//span[text()='DES']");
			$this->assertNotVisible("//span[text()='AES']");

			$this->zbxTestTextNotPresent('Privacy passphrase');
			$this->assertNotVisible('snmpv3_privpassphrase');
		}

		switch ($data['type']) {
			case 'Zabbix agent':
			case 'Zabbix agent (active)':
			case 'Simple check':
			case 'SNMPv1 agent':
			case 'SNMPv2 agent':
			case 'SNMPv3 agent':
			case 'Zabbix internal':
			case 'Zabbix aggregate':
			case 'External check':
			case 'Database monitor':
			case 'IPMI agent':
			case 'SSH agent':
			case 'TELNET agent':
			case 'JMX agent':
			case 'Calculated':
				$this->zbxTestTextPresent('Update interval (in sec)');
				$this->assertVisible('delay');
				$this->assertAttribute("//input[@id='delay']/@maxlength", 5);
				$this->assertAttribute("//input[@id='delay']/@size", 5);
				$this->assertAttribute("//input[@id='delay']/@value", 30);
				break;
			default:
				$this->zbxTestTextNotPresent('Update interval (in sec)');
				$this->assertNotVisible('delay');
		}

		$this->zbxTestTextPresent('Type of information');
		$this->assertVisible('value_type');
		$this->zbxTestDropdownHasOptions('value_type', array(
			'Numeric (unsigned)',
			'Numeric (float)',
			'Character',
			'Log',
			'Text'
		));
		$this->assertAttribute("//*[@id='value_type']/option[text()='Numeric (unsigned)']/@selected", 'selected');
		$this->isEditable("//*[@id='value_type']/option[text()='Numeric (unsigned)']");
		$this->isEditable("//*[@id='value_type']/option[text()='Numeric (float)']");

		if ($data['type'] == 'Zabbix aggregate' || $data['type'] == 'Calculated') {
			$this->assertAttribute("//*[@id='value_type']/option[text()='Character']/@disabled", 'disabled');
			$this->assertAttribute("//*[@id='value_type']/option[text()='Log']/@disabled", 'disabled');
			$this->assertAttribute("//*[@id='value_type']/option[text()='Text']/@disabled", 'disabled');
		}
		else {
			$this->isEditable("//*[@id='value_type']/option[text()='Character']");
			$this->isEditable("//*[@id='value_type']/option[text()='Log']");
			$this->isEditable("//*[@id='value_type']/option[text()='Text']");
		}

		if ($value_type == 'Numeric (unsigned)') {
			$this->zbxTestTextPresent('Data type');
			$this->assertVisible('data_type');
			$this->zbxTestDropdownHasOptions('data_type', array('Boolean', 'Octal', 'Decimal', 'Hexadecimal'));
			$this->assertAttribute("//*[@id='data_type']/option[text()='Decimal']/@selected", 'selected');
			$this->isEditable("//*[@id='data_type']/option[text()='Decimal']");

			if ($data['type'] == 'Zabbix aggregate' || $data['type'] == 'Calculated') {
				$this->assertAttribute("//*[@id='data_type']/option[text()='Boolean']/@disabled", 'disabled');
				$this->assertAttribute("//*[@id='data_type']/option[text()='Octal']/@disabled", 'disabled');
				$this->assertAttribute("//*[@id='data_type']/option[text()='Hexadecimal']/@disabled", 'disabled');
			}
			else {
				$this->isEditable("//*[@id='data_type']/option[text()='Boolean']");
				$this->isEditable("//*[@id='data_type']/option[text()='Octal']");
				$this->isEditable("//*[@id='data_type']/option[text()='Hexadecimal']");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Data type');
			$this->assertNotVisible('data_type');
		}

		if ($value_type == 'Numeric (float)' || ($value_type == 'Numeric (unsigned)' && $data_type != 'Boolean')) {
			$this->zbxTestTextPresent('Units');
			$this->assertVisible('units');
			$this->assertAttribute("//input[@id='units']/@maxlength", 255);
			$this->assertAttribute("//input[@id='units']/@size", 50);

			$this->zbxTestTextPresent('Use custom multiplier');
			$this->assertVisible('multiplier');
			$this->assertAttribute("//input[@id='multiplier']/@type", 'checkbox');

			$this->assertVisible('formula');
			$this->assertAttribute("//input[@id='formula']/@maxlength", 255);
			$this->assertAttribute("//input[@id='formula']/@size", 25);
			$this->assertAttribute("//input[@id='formula']/@value", 1);
			$this->assertElementPresent("//input[@id='formula']/@disabled");
		}
		else {
			$this->zbxTestTextNotPresent('Units');
			$this->assertNotVisible('units');

			$this->zbxTestTextNotPresent('Use custom multiplier');
			$this->assertNotVisible('multiplier');
			$this->assertNotVisible('formula');
		}

		switch ($data['type']) {
			case 'Zabbix agent':
			case 'Simple check':
			case 'SNMPv1 agent':
			case 'SNMPv2 agent':
			case 'SNMPv3 agent':
			case 'Zabbix internal':
			case 'Zabbix aggregate':
			case 'External check':
			case 'Database monitor':
			case 'IPMI agent':
			case 'SSH agent':
			case 'TELNET agent':
			case 'JMX agent':
			case 'Calculated':
				$this->zbxTestTextPresent(array('Flexible intervals', 'Interval', 'Period', 'No flexible intervals defined.'));
				$this->assertVisible('delayFlexTable');

				$this->zbxTestTextPresent('New flexible interval', 'Interval (in sec)', 'Period');
				$this->assertVisible('new_delay_flex_delay');
				$this->assertAttribute("//input[@id='new_delay_flex_delay']/@maxlength", 5);
				$this->assertAttribute("//input[@id='new_delay_flex_delay']/@size", 5);
				$this->assertAttribute("//input[@id='new_delay_flex_delay']/@value", 50);

				$this->assertVisible('new_delay_flex_period');
				$this->assertAttribute("//input[@id='new_delay_flex_period']/@maxlength", 255);
				$this->assertAttribute("//input[@id='new_delay_flex_period']/@size", 20);
				$this->assertAttribute("//input[@id='new_delay_flex_period']/@value", '1-7,00:00-24:00');
				$this->assertVisible('add_delay_flex');
				break;
			default:
				$this->zbxTestTextNotPresent(array('Flexible intervals', 'Interval', 'Period', 'No flexible intervals defined.'));
				$this->assertNotVisible('delayFlexTable');

				$this->zbxTestTextNotPresent('New flexible interval', 'Interval (in sec)', 'Period');
				$this->assertNotVisible('new_delay_flex_period');
				$this->assertNotVisible('new_delay_flex_delay');
				$this->assertNotVisible('add_delay_flex');
		}

		$this->zbxTestTextPresent('Keep history (in days)');
		$this->assertVisible('history');
		$this->assertAttribute("//input[@id='history']/@maxlength", 8);
		$this->assertAttribute("//input[@id='history']/@value", 90);
		$this->assertAttribute("//input[@id='history']/@size", 8);

		if ($value_type == 'Numeric (unsigned)' || $value_type == 'Numeric (float)') {
			$this->zbxTestTextPresent('Keep trends (in days)');
			$this->assertVisible('trends');
			$this->assertAttribute("//input[@id='trends']/@maxlength", 8);
			$this->assertAttribute("//input[@id='trends']/@value", 365);
			$this->assertAttribute("//input[@id='trends']/@size", 8);
		}
		else {
			$this->zbxTestTextNotPresent('Keep trends (in days)');
			$this->assertNotVisible('trends');
		}

		if ($value_type == 'Numeric (float)' || ($value_type == 'Numeric (unsigned)' && $data_type != 'Boolean')) {
			$this->zbxTestTextPresent('Store value');
			$this->assertVisible('delta');
			$this->zbxTestDropdownHasOptions('delta', array('As is', 'Delta (speed per second)', 'Delta (simple change)'));
			$this->assertAttribute("//*[@id='delta']/option[text()='As is']/@selected", 'selected');
		}
		else {
			$this->zbxTestTextNotPresent('Store value');
			$this->assertNotVisible('delta');
		}

		if ($value_type == 'Numeric (float)' || $value_type == 'Numeric (unsigned)' || $value_type == 'Character') {
			$this->zbxTestTextPresent(array('Show value', 'show value mappings'));
			$this->assertVisible('valuemapid');
			$this->assertAttribute("//*[@id='valuemapid']/option[text()='As is']/@selected", 'selected');

			$options = array('As is');
			$result = DBselect('SELECT name FROM valuemaps');
			while ($row = DBfetch($result)) {
				$options[] = $row['name'];
			}
			$this->zbxTestDropdownHasOptions('valuemapid', $options);
		}
		else {
			$this->zbxTestTextNotPresent(array('Show value', 'show value mappings'));
			$this->assertNotVisible('valuemapid');
		}

		if ($data['type'] == 'Zabbix trapper') {
			$this->zbxTestTextPresent('Allowed hosts');
			$this->assertVisible('trapper_hosts');
			$this->assertAttribute("//input[@id='trapper_hosts']/@maxlength", 255);
			$this->assertAttribute("//input[@id='trapper_hosts']/@size", 50);
		}
		else {
			$this->zbxTestTextNotPresent('Allowed hosts');
			$this->assertNotVisible('trapper_hosts');
		}

		if ($value_type == 'Log') {
			$this->zbxTestTextPresent('Log time format');
			$this->assertVisible('logtimefmt');
			$this->assertAttribute("//input[@id='logtimefmt']/@maxlength", 64);
			$this->assertAttribute("//input[@id='logtimefmt']/@size", 25);
		}
		else {
			$this->zbxTestTextNotPresent('Log time format');
			$this->assertNotVisible('logtimefmt');
		}

		$this->zbxTestTextPresent('New application');
		$this->assertVisible('new_application');
		$this->assertAttribute("//input[@id='new_application']/@maxlength", 255);
		$this->assertAttribute("//input[@id='new_application']/@size", 50);

		$this->zbxTestTextPresent('Applications');
		$this->assertVisible('applications_');
		$this->assertAttribute("//*[@id='applications_']/option[text()='-None-']/@selected", 'selected');

		$this->zbxTestTextPresent('Description');
		$this->assertVisible('description');
		$this->assertAttribute("//textarea[@id='description']/@rows", 7);

		$this->zbxTestTextPresent('Enabled');
		$this->assertVisible('status');
		$this->assertAttribute("//input[@id='status']/@checked", 'checked');
	}


	// returns data for simple create
	public static function simple() {
		return array(
			array(
				array('expected' => ITEM_GOOD,
					'item' => 'itemSimple',
					'key' => 'key-template-simple',
					'hostCheck' => true,
					'dbCheck' => true)
			),
			array(
				array('expected' => ITEM_GOOD,
					'item' => 'itemName',
					'key' => 'key-template-item',
					'hostCheck' => true)
			),
			array(
				array('expected' => ITEM_GOOD,
					'item' => 'itemTrigger',
					'key' => 'key-template-trigger',
					'hostCheck' => true,
					'dbCheck' => true,
					'remove' => true)
			),
			array(
				array('expected' => ITEM_GOOD,
					'item' => 'itemRemove',
					'key' => 'key-template-remove',
					'hostCheck' => true,
					'dbCheck' => true,
					'hostRemove' => true,
					'remove' => true)
			),
			array(
				array('expected' => ITEM_BAD,
					'item' => 'itemInheritance',
					'key' => 'key-item-inheritance',
					'errors' => array(
						'ERROR: Cannot add item',
						'Item with key "key-item-inheritance" already exists on "Inheritance test template".')
				)
			)
		);
	}

	/**
	 * @dataProvider simple
	 */
	public function testInheritanceItemPrototype_simpleCreate($data) {
		$this->zbxTestLogin('templates.php');

		$itemName = $data['item'];
		$keyName = $data['key'];

		$this->zbxTestClickWait('link='.$this->template);
		$this->zbxTestClickWait("link=Discovery rules");
		$this->zbxTestClickWait('link='.$this->discoveryRule);
		$this->zbxTestClickWait("link=Item prototypes");
		$this->zbxTestClickWait('form');

		$this->input_type('name', $itemName);
		$this->input_type('key', $keyName);
		$this->zbxTestClickWait('save');

		switch ($data['expected']) {
			case ITEM_GOOD:
				$this->zbxTestTextPresent('Item added');
				$this->checkTitle('Configuration of item prototypes');
				$this->zbxTestTextPresent(array('CONFIGURATION OF ITEM PROTOTYPES', "Item prototypes of ".$this->discoveryRule));
				break;

			case ITEM_BAD:
				$this->checkTitle('Configuration of item prototypes');
				$this->zbxTestTextPresent(array('CONFIGURATION OF ITEM PROTOTYPES', 'Item prototype'));
				foreach ($data['errors'] as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				$this->zbxTestTextPresent(array('Name', 'Type', 'Key'));
				break;
		}

		if (isset($data['hostCheck'])) {
			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait("link=Discovery rules");
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait("link=Item prototypes");

			$this->zbxTestTextPresent($this->template.": $itemName");
			$this->zbxTestClickWait("link=$itemName");

			$this->zbxTestTextPresent('Parent items');
			$this->assertElementPresent('link='.$this->template);
			$this->assertElementValue('name', $itemName);
			$this->assertElementValue('key', $keyName);
		}

		if (isset($data['dbCheck'])) {
			// template
			$result = DBselect("SELECT name, key_, hostid FROM items where name = '".$itemName."' limit 1");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $itemName);
				$this->assertEquals($row['key_'], $keyName);
				$hostid = $row['hostid'] + 1;
			}
			// host
			$result = DBselect("SELECT name, key_ FROM items where name = '".$itemName."'  AND hostid = ".$hostid."");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $itemName);
				$this->assertEquals($row['key_'], $keyName);
			}
		}

		if (isset($data['hostRemove'])) {
			$result = DBselect("SELECT hostid FROM items where name = '".$itemName."' limit 1");
			while ($row = DBfetch($result)) {
				$hostid = $row['hostid'] + 1;
			}
			$result = DBselect("SELECT name, key_, itemid FROM items where name = '".$itemName."'  AND hostid = ".$hostid."");
			while ($row = DBfetch($result)) {
				$itemId = $row['itemid'];
			}

			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait("link=Discovery rules");
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait("link=Item prototypes");

			$this->zbxTestCheckboxSelect("group_itemid_$itemId");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent(array('ERROR: Cannot delete items', 'Cannot delete templated items'));
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT itemid FROM items where name = '".$itemName."' limit 1");
			while ($row = DBfetch($result)) {
				$itemId = $row['itemid'];
			}

			$this->zbxTestOpenWait('templates.php');
			$this->zbxTestClickWait('link='.$this->template);
			$this->zbxTestClickWait("link=Discovery rules");
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait("link=Item prototypes");

			$this->zbxTestCheckboxSelect("group_itemid_$itemId");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent('Items deleted');
			$this->zbxTestTextNotPresent($this->template.': $itemName');
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testInheritanceItemPrototype_teardown() {
		DBrestore_tables('items');
	}
}
