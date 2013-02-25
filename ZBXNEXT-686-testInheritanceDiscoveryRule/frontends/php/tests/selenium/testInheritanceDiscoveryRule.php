<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

define('DISCOVERY_GOOD', 0);
define('DISCOVERY_BAD', 1);

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testInheritanceDiscovery extends CWebTest {


	// returns all possible item types
	public static function itemTypes() {
		return array(
			array(
				array('type' => 'Zabbix agent')
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
				array('type' => 'SNMPv2 agent')
			),
			array(
				array('type' => 'SNMPv3 agent')
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv'
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
	 * Backup the tables that will be modified during the tests.
	 */
	public function testInheritanceDiscoveryRule_setup() {
		DBsave_tables('hosts');
	}

	/**
	 * @dataProvider itemTypes
	 */
	public function testInheritanceDiscoveryRule_CheckLayout($data) {
		$this->zbxTestLogin('templates.php');

		$template = 'Inheritance test template';
		$host = 'Template inheritance test host';

		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('CONFIGURATION OF TEMPLATES');

		$this->zbxTestClickWait("link=$template");
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('form');

		$this->checkTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');
		$this->zbxTestTextPresent('Discovery rule');

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

		$keyValue = $this->getValue('key');
		switch($data['type']) {
			case 'Database monitor':
				$this->assertEquals($keyValue, "db.odbc.select[<unique short description>]");
				break;
			case 'SSH agent':
				$this->assertEquals($keyValue, "ssh.run[<unique short description>,<ip>,<port>,<encoding>]");
				break;
			case 'TELNET agent':
				$this->assertEquals($keyValue, "telnet.run[<unique short description>,<ip>,<port>,<encoding>]");
				break;
			case 'JMX agent':
				$this->assertEquals($keyValue, "jmx[<object name>,<attribute name>]");
				break;
			}

		if ($data['type'] == 'SNMPv3 agent') {
			if (isset($data['snmpv3_securitylevel'])) {
				$this->zbxTestDropdownSelect('snmpv3_securitylevel', $data['snmpv3_securitylevel']);
			}
			$snmpv3_securitylevel = $this->getSelectedLabel('snmpv3_securitylevel');
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

			if (isset($authtype) && $authtype == 'Public key') {
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

		if	(isset($authtype) && $authtype == 'Public key') {
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

		if (isset($snmpv3_securitylevel) && $snmpv3_securitylevel != 'noAuthNoPriv') {
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

		if (isset($snmpv3_securitylevel) && $snmpv3_securitylevel == 'authPriv') {
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

		$this->zbxTestTextPresent('Keep lost resources period (in days)');
		$this->assertVisible('lifetime');
		$this->assertAttribute("//input[@id='lifetime']/@maxlength", 64);
		$this->assertAttribute("//input[@id='lifetime']/@size", 25);
		$this->assertAttribute("//input[@id='lifetime']/@value", 30);

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

		$this->zbxTestTextPresent('Filter');
		$this->zbxTestTextPresent('Macro');
		$this->assertVisible('filter_macro');
		$this->assertAttribute("//input[@id='filter_macro']/@maxlength", 255);
		$this->assertAttribute("//input[@id='filter_macro']/@size", 13);

		$this->zbxTestTextPresent('Regexp');
		$this->assertVisible('filter_value');
		$this->assertAttribute("//input[@id='filter_value']/@maxlength", 255);
		$this->assertAttribute("//input[@id='filter_value']/@size", 20);

		$this->zbxTestTextPresent('Description');
		$this->assertVisible('description');
		$this->assertAttribute("//textarea[@id='description']/@rows", 7);

		$this->zbxTestTextPresent('Status');
		$this->assertVisible('status');
		$this->zbxTestDropdownHasOptions('status', array('Enabled', 'Disabled', 'Not supported'));
		$this->assertAttribute("//*[@id='status']/option[text()='Enabled']/@selected", 'selected');
	}

	/**
	 * Restore the original tables.
	 */
	public function testInheritanceDiscoveryRule_teardown() {
		DBrestore_tables('hosts');
	}
}
