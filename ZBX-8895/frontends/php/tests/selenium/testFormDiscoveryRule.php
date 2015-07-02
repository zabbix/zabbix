<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

class testFormDiscoveryRule extends CWebTest {

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	/**
	 * The id of the test host created in the test data set.
	 *
	 * @var integer
	 */
	protected $hostid = 40001;

	/**
	 * The key of the host item used in discovery rule.
	 *
	 * @var string
	 */
	protected $keyForm = 'discovery-rule-form1';

	/**
	 * The key of the inheritance item used in discovery rule.
	 *
	 * @var string
	 */
	protected $keyInheritance = 'discovery-rule-inheritance1';


	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormDiscoveryRule_Setup() {
		DBsave_tables('items');
	}

	// Returns layout data
	public static function layout() {
		return array(
			array(
				array('type' => 'Zabbix agent', 'host' => 'Simple form test host')
			),
			array(
				array('host' => 'Simple form test host', 'form' => 'testFormDiscoveryRule1')
			),
			array(
				array('type' => 'Zabbix agent (active)', 'host' => 'Simple form test host'),
			),
			array(
				array('type' => 'Simple check', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'SNMPv1 agent', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'SNMPv2 agent', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'SNMPv3 agent', 'host' => 'Simple form test host')
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authNoPriv',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authPriv',
					'host' => 'Simple form test host'
				)
			),
			array(
				array('type' => 'Zabbix internal', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'Zabbix trapper', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'External check', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'IPMI agent', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'SSH agent', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'SSH agent',
				'authtype' => 'Public key',
				'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'SSH agent',
					'authtype' => 'Password',
					'host' => 'Simple form test host'
				)
			),
			array(
				array('type' => 'TELNET agent', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'JMX agent', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'Zabbix agent', 'template' => 'Inheritance test template')
			),
			array(
				array(
					'type' => 'Zabbix agent',
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceDiscoveryRule1'
				)
			),
			array(
				array('type' => 'Zabbix agent (active)', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'Simple check', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'SNMPv1 agent', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'SNMPv2 agent', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'SNMPv3 agent', 'template' => 'Inheritance test template')
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authNoPriv',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authPriv',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array('type' => 'Zabbix internal', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'Zabbix trapper', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'External check', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'IPMI agent', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'SSH agent', 'template' => 'Inheritance test template')
			),
			array(
				array(
					'type' => 'SSH agent',
					'authtype' => 'Public key',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'SSH agent',
					'authtype' => 'Password',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array('type' => 'TELNET agent', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'JMX agent', 'template' => 'Inheritance test template')
			)
		);
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormDiscoveryRule_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickWait('link='.$data['template']);
			$hostid = 30000;
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickWait('link='.$data['host']);
			if (isset($data['templatedHost'])) {
				$hostid = 30001;
			}
			else {
				$hostid = 40001;
			}
		}

		$this->zbxTestClickWait('link=Discovery rules');

		if (isset($data['form'])) {
			$this->zbxTestClickWait('link='.$data['form']);
		}
		else {
			$this->zbxTestClickWait('form');
		}

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');
		$this->zbxTestTextPresent('Discovery rule');

		if (isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Parent discovery rules');
			if (isset($data['hostTemplate'])) {
				$this->assertElementPresent("//a[text()='".$data['hostTemplate']."']");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Parent discovery rules');
		}

		$this->zbxTestTextPresent('Name');
		$this->assertVisible('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", 255);
		$this->assertAttribute("//input[@id='name']/@size", 50);
		$this->assertAttribute("//input[@id='name']/@autofocus", 'autofocus');
			if(isset($data['templatedHost'])) {
				$this->assertAttribute("//input[@id='name']/@readonly", 'readonly');
			}

		$this->zbxTestTextPresent('Type');
		if (!isset($data['templatedHost'])) {
			$this->assertVisible('type');
			$this->zbxTestDropdownHasOptions('type', array(
				'Zabbix agent',
				'Zabbix agent (active)',
				'Simple check',
				'SNMPv1 agent',
				'SNMPv2 agent',
				'SNMPv3 agent',
				'Zabbix internal',
				'Zabbix trapper',
				'External check',
				'IPMI agent',
				'SSH agent',
				'TELNET agent',
				'JMX agent'
			));
			if (isset($data['type'])) {
				$this->zbxTestDropdownSelect('type', $data['type']);
			}
			$type = $this->getSelectedLabel('type');
		}
		else {
			$this->assertVisible('typename');
			$this->assertAttribute("//input[@id='typename']/@maxlength", 255);
			$this->assertAttribute("//input[@id='typename']/@size", 50);
			$this->assertAttribute("//input[@id='typename']/@readonly", 'readonly');

			$type = $this->getValue('typename');
		}

		$this->zbxTestTextPresent('Key');
		$this->assertVisible('key');
		$this->assertAttribute("//input[@id='key']/@maxlength", 255);
		$this->assertAttribute("//input[@id='key']/@size", 50);
		if (isset($data['templatedHost'])) {
			$this->assertAttribute("//input[@id='key']/@readonly", 'readonly');
		}

		if (isset($data['host']) && isset($data['form']) && !isset($data['templatedHost'])) {
			$keyTest = $this->getValue('key');
			$this->assertEquals($this->keyForm, $keyTest);
		}

		if (isset($data['template']) && isset($data['form'])) {
			$keyTest = $this->getValue('key');
			$this->assertEquals($this->keyInheritance, $keyTest);
		}

		if (isset($data['host']) && isset($data['templatedHost'])) {
			$keyTest = $this->getValue('key');
			$this->assertEquals($this->keyInheritance, $keyTest);
		}

		if (!isset($data['form'])) {
			$keyValue = $this->getValue('key');
			switch($type) {
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
		}

		if (!isset($data['template'])){
			$interfaceType = itemTypeInterface($this->getValue('type'));
			switch ($interfaceType) {
				case INTERFACE_TYPE_SNMP :
				case INTERFACE_TYPE_IPMI :
				case INTERFACE_TYPE_AGENT :
				case INTERFACE_TYPE_ANY :
				case INTERFACE_TYPE_JMX :
					$this->zbxTestTextPresent('Host interface');
					$dbInterfaces = DBdata(
						'SELECT type,ip,port'.
						' FROM interface'.
						' WHERE hostid='.$hostid.
							($interfaceType == INTERFACE_TYPE_ANY ? '' : ' AND type='.$interfaceType)
					);
					$dbInterfaces = reset($dbInterfaces);
					if ($dbInterfaces != null) {
						foreach ($dbInterfaces as $host_interface) {
							$this->assertElementPresent('//select[@id="interfaceid"]/optgroup/option[text()="'.
							$host_interface['ip'].' : '.$host_interface['port'].'"]');
						}
					}
					else {
						$this->zbxTestTextPresent('No interface found');
						$this->assertNotVisible('interfaceid');
					}
					break;
				default:
					$this->zbxTestTextNotPresent(array('Host interface', 'No interface found'));
					$this->assertNotVisible('interfaceid');
					break;
			}
		}
		if ($type == 'SNMPv3 agent') {
			if (isset($data['snmpv3_securitylevel'])) {
				$this->zbxTestDropdownSelect('snmpv3_securitylevel', $data['snmpv3_securitylevel']);
			}
			$snmpv3_securitylevel = $this->getSelectedLabel('snmpv3_securitylevel');
		}

		$this->zbxTestTextNotPresent('Additional parameters');
		$this->assertNotVisible('params_ap');

		if ($type == 'SSH agent' || $type == 'TELNET agent' ) {
			$this->zbxTestTextPresent('Executed script');
			$this->assertVisible('params_es');
			$this->assertAttribute("//textarea[@id='params_es']/@rows", 7);
		}
		else {
			$this->zbxTestTextNotPresent('Executed script');
			$this->assertNotVisible('params_es');
		}

		$this->zbxTestTextNotPresent('Formula');
		$this->assertNotVisible('params_f');

		if ($type == 'IPMI agent') {
			$this->zbxTestTextPresent('IPMI sensor');
			$this->assertVisible('ipmi_sensor');
			$this->assertAttribute("//input[@id='ipmi_sensor']/@maxlength", 128);
			$this->assertAttribute("//input[@id='ipmi_sensor']/@size", 50);
		}
		else {
			$this->zbxTestTextNotPresent('IPMI sensor');
			$this->assertNotVisible('ipmi_sensor');
		}

		if ($type == 'SSH agent') {
			$this->zbxTestTextPresent('Authentication method');
			$this->assertVisible('authtype');
			$this->zbxTestDropdownHasOptions('authtype', array('Password', 'Public key'));
		}
		else {
			$this->zbxTestTextNotPresent('Authentication method');
			$this->assertNotVisible('authtype');
		}

		if ($type == 'SSH agent' || $type == 'TELNET agent' || $type == 'JMX agent' || $type == 'Simple check') {
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

		if	($type == 'SNMPv1 agent' || $type == 'SNMPv2 agent' || $type == 'SNMPv3 agent') {
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

		if	($type == 'SNMPv1 agent' || $type == 'SNMPv2 agent') {
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

		if	($type == 'SNMPv3 agent') {
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

		switch ($type) {
			case 'Zabbix agent':
			case 'Zabbix agent (active)':
			case 'Simple check':
			case 'SNMPv1 agent':
			case 'SNMPv2 agent':
			case 'SNMPv3 agent':
			case 'Zabbix internal':
			case 'External check':
			case 'IPMI agent':
			case 'SSH agent':
			case 'TELNET agent':
			case 'JMX agent':
				$this->zbxTestTextPresent('Update interval (in sec)');
				$this->assertVisible('delay');
				$this->assertAttribute("//input[@id='delay']/@maxlength", 5);
				$this->assertAttribute("//input[@id='delay']/@size", 5);
				if (!isset($data['form'])) {
					$this->assertAttribute("//input[@id='delay']/@value", 30);
				}
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

		switch ($type) {
			case 'Zabbix agent':
			case 'Simple check':
			case 'SNMPv1 agent':
			case 'SNMPv2 agent':
			case 'SNMPv3 agent':
			case 'Zabbix internal':
			case 'External check':
			case 'IPMI agent':
			case 'SSH agent':
			case 'TELNET agent':
			case 'JMX agent':
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

		if ($type == 'Zabbix trapper') {
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

		$this->zbxTestTextPresent('Enabled');
		$this->assertVisible('status');
		$this->assertAttribute("//*[@id='status']/@checked", 'checked');
	}

	// Returns update data
	public static function update() {
		return DBdata("select * from items where hostid = 40001 and key_ LIKE 'discovery-rule-form%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testFormDiscoveryRule_SimpleUpdate($data) {
		$name = $data['name'];

		$sqlDiscovery = 'select itemid, hostid, name, key_, delay from items order by itemid';
		$oldHashDiscovery = DBhash($sqlDiscovery);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('save');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule updated');
		$this->zbxTestTextPresent("$name");

		$this->assertEquals($oldHashDiscovery, DBhash($sqlDiscovery));

	}

	// Returns create data
	public static function create() {
		return array(
			array(
				array(
					'expected' => TEST_BAD,
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "Name": cannot be empty.',
							'Incorrect value for field "Key": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'discoveryRuleError',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "Key": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'key' => 'discovery-rule-error',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "Name": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'discoveryRuleNo1',
					'key' => 'discovery-key-no1',
					'formCheck' =>true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'discoveryRuleNo2',
					'key' => 'discovery-key-no2',
					'formCheck' =>true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array('expected' => TEST_BAD,
					'name' => 'discoveryRuleNo1',
					'key' => 'discovery-key-no1',
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item with key "discovery-key-no1" already exists on "Simple form test host".')
				)
			),
			array(
				array('expected' => TEST_BAD,
					'name' => 'discoveryRuleError',
					'key' => 'discovery-key-no1',
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item with key "discovery-key-no1" already exists on "Simple form test host".')
				)
			),
			// Empty timedelay
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
					'delay' => 0,
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Incorrect timedelay
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
					'delay' => '-30',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "-30" for "Update interval (in sec)" field: must be between 0 and 86400.'
					)
				)
			),
			// Incorrect timedelay
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
					'delay' => 86401,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "86401" for "Update interval (in sec)" field: must be between 0 and 86400.'
					)
				)
			),
			// Empty time flex period
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
					'flexPeriod' => array(
						array('flexDelay' => '', 'flexTime' => '', 'instantCheck' => true)
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "New flexible interval": cannot be empty.'
					)
				)
			),
			// Incorrect flex period
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
					'flexPeriod' => array(
						array('flexTime' => '1-11,00:00-24:00', 'instantCheck' => true)
					),
					'errors' => array(
						'ERROR: Invalid time period',
						'Incorrect time period "1-11,00:00-24:00".'
					)
				)
			),
			// Incorrect flex period
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
					'flexPeriod' => array(
						array('flexTime' => '1-7,00:00-25:00', 'instantCheck' => true)
					),
					'errors' => array(
						'ERROR: Invalid time period',
						'Incorrect time period "1-7,00:00-25:00".'
					)
				)
			),
			// Incorrect flex period
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
					'flexPeriod' => array(
						array('flexTime' => '1-7,24:00-00:00', 'instantCheck' => true)
					),
					'errors' => array(
						'ERROR: Invalid time period',
						'Incorrect time period "1-7,24:00-00:00" start time must be less than end time.'
					)
				)
			),
			// Incorrect flex period
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
					'flexPeriod' => array(
						array('flexTime' => '1,00:00-24:00;2,00:00-24:00', 'instantCheck' => true)
					),
					'errors' => array(
						'ERROR: Invalid time period',
						'Incorrect time period "1,00:00-24:00;2,00:00-24:00".'
					)
				)
			),
			// Multiple flex periods
			array(
				array(
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
					'flexPeriod' => array(
						array('flexTime' => '1,00:00-24:00'),
						array('flexTime' => '2,00:00-24:00'),
						array('flexTime' => '1,00:00-24:00'),
						array('flexTime' => '2,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '2,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '3,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '4,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '5,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '6,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex1',
					'key' =>'discovery-flex-delay1',
					'flexPeriod' => array(
						array('flexTime' => '1,00:00-24:00'),
						array('flexTime' => '2,00:00-24:00'),
						array('flexTime' => '3,00:00-24:00'),
						array('flexTime' => '4,00:00-24:00'),
						array('flexTime' => '5,00:00-24:00'),
						array('flexTime' => '6,00:00-24:00'),
						array('flexTime' => '7,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'delay' => 0,
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '2,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '3,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '4,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '5,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '6,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex2',
					'key' =>'discovery-flex-delay2',
					'delay' => 0,
					'flexPeriod' => array(
						array('flexTime' => '1-5,00:00-24:00'),
						array('flexTime' => '6-7,00:00-24:00')
					),
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay3',
					'flexPeriod' => array(
						array('flexTime' => '1-5,00:00-24:00'),
						array('flexTime' => '6-7,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay4',
					'delay' => 0,
					'flexPeriod' => array(
						array('flexTime' => '1-7,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay5',
					'flexPeriod' => array(
						array('flexTime' => '1-7,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00'),
						array('flexTime' => '1-5,00:00-24:00'),
						array('flexTime' => '6-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => array(
						array('flexTime' => '1-5,00:00-24:00'),
						array('flexTime' => '6-7,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => array(
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay6',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1,00:00-24:00', 'remove' => true),
						array('flexDelay' => 0, 'flexTime' => '2,00:00-24:00', 'remove' => true),
						array('flexDelay' => 0, 'flexTime' => '3,00:00-24:00', 'remove' => true),
						array('flexDelay' => 0, 'flexTime' => '4,00:00-24:00', 'remove' => true),
						array('flexDelay' => 0, 'flexTime' => '5,00:00-24:00', 'remove' => true),
						array('flexDelay' => 0, 'flexTime' => '6,00:00-24:00', 'remove' => true),
						array('flexDelay' => 0, 'flexTime' => '7,00:00-24:00', 'remove' => true),
						array('flexTime' => '1,00:00-24:00'),
						array('flexTime' => '2,00:00-24:00'),
						array('flexTime' => '3,00:00-24:00'),
						array('flexTime' => '4,00:00-24:00'),
						array('flexTime' => '5,00:00-24:00'),
						array('flexTime' => '6,00:00-24:00'),
						array('flexTime' => '7,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay7',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00', 'remove' => true),
						array('flexTime' => '1-7,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex Check',
					'key' =>'discovery-flex-delay8',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00', 'remove' => true),
						array('flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00', 'remove' => true),
						array('flexTime' => '1-5,00:00-24:00'),
						array('flexTime' => '6-7,00:00-24:00')
					),
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			// Maximum flexfields allowed reached- error
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Discovery flex-maximum entries',
					'key' => 'discovery-flex-maximum',
					'flexPeriod' => array(
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00', 'instantCheck' => true, 'maximumItems' => true)
					),
					'errors' => array(
						'Maximum number of flexible intervals added'
					)
				)
			),
			// Maximum flexfields allowed reached- save OK
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Discovery flex-maximum save OK',
					'key' => 'discovery-flex-maximum-save',
					'flexPeriod' => array(
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00', 'maximumItems' => true)
					),
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			// Maximum flexfields allowed reached- remove one item
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Discovery flex-maximum with remove',
					'key' => 'discovery-flex-maximum-remove',
					'flexPeriod' => array(
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00', 'instantCheck' => true, 'maximumItems' => true, 'remove' => true),
						array('flexTime' => '1-7,00:00-24:00', 'instantCheck' => true, 'maximumItems' => true)
					),
					'errors' => array(
						'Maximum number of flexible intervals added'
					)
				)
			),
			// Flexfields with negative number in flexdelay
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Item flex-negative flexdelay',
					'key' => 'item-flex-negative-flexdelay',
					'flexPeriod' => array(
						array('flexDelay' => '-50', 'flexTime' => '1-7,00:00-24:00')
					)
				)
			),
			// Flexfields with symbols in flexdelay
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Item flex-symbols in flexdelay',
					'key' => 'item-flex-symbols-flexdelay',
					'flexPeriod' => array(
						array('flexDelay' => '50abc', 'flexTime' => '1-7,00:00-24:00')
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' =>'!@#$%^&*()_+-=[]{};:"|,./<>?',
					'key' =>'discovery-symbols-test',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			// List of all item types
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'Zabbix agent',
					'name' => 'Zabbix agent',
					'key' => 'discovery-zabbix-agent',
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'Zabbix agent (active)',
					'name' => 'Zabbix agent (active)',
					'key' => 'discovery-zabbix-agent-active',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'Simple check',
					'name' => 'Simple check',
					'key' => 'discovery-simple-check',
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'SNMPv1 agent',
					'name' => 'SNMPv1 agent',
					'key' => 'discovery-snmpv1-agent',
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'SNMPv2 agent',
					'name' => 'SNMPv2 agent',
					'key' => 'discovery-snmpv2-agent',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'SNMPv3 agent',
					'name' => 'SNMPv3 agent',
					'key' => 'discovery-snmpv3-agent',
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'type' => 'SNMPv1 agent',
					'name' => 'SNMPv1 agent',
					'key' => 'test-item-reuse',
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item with key "test-item-reuse" already exists on "Simple form test host".'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'type' => 'SNMPv1 agent',
					'name' => 'SNMPv1 agent',
					'key' => 'test-item-form1',
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item with key "test-item-form1" already exists on "Simple form test host".'
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'Zabbix internal',
					'name' => 'Zabbix internal',
					'key' => 'discovery-zabbix-internal',
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'Zabbix trapper',
					'name' => 'Zabbix trapper',
					'key' => 'snmptrap.fallback',
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'External check',
					'name' => 'External check',
					'key' => 'discovery-external-check',
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'IPMI agent',
					'name' => 'IPMI agent',
					'key' => 'discovery-ipmi-agent',
					'ipmi_sensor' => 'ipmi_sensor',
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'IPMI agent',
					'name' => 'IPMI agent with spaces',
					'key' => 'item-ipmi-agent-spaces',
					'ipmi_sensor' => 'ipmi_sensor',
					'ipmiSpaces' => true,
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'SSH agent',
					'name' => 'SSH agent',
					'key' => 'discovery-ssh-agent',
					'username' => 'zabbix',
					'params_es' => 'executed script',
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'TELNET agent',
					'name' => 'TELNET agent',
					'key' => 'discovery-telnet-agent',
					'username' => 'zabbix',
					'params_es' => 'executed script',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'type' => 'IPMI agent',
					'name' => 'IPMI agent error',
					'key' => 'discovery-ipmi-agent-error',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "IPMI sensor": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'type' => 'SSH agent',
					'name' => 'SSH agent error',
					'key' => 'discovery-ssh-agent-error',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "User name": cannot be empty.',
							'Incorrect value for field "Executed script": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'type' => 'TELNET agent',
					'name' => 'TELNET agent error',
					'key' => 'discovery-telnet-agent-error',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "User name": cannot be empty.',
							'Incorrect value for field "Executed script": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'type' => 'JMX agent',
					'name' => 'JMX agent',
					'key' => 'discovery-jmx-agent',
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			// Default
			array(
				array(
					'expected' => TEST_BAD,
					'type' => 'SSH agent',
					'name' => 'SSH agent',
					'username' => 'zabbix',
					'params_es' => 'script to be executed',
					'errors' => array(
							'ERROR: Cannot add discovery rule',
							'Check the key, please. Default example was passed.'
					)
				)
			),
			// Default
			array(
				array(
					'expected' => TEST_BAD,
					'type' => 'TELNET agent',
					'name' => 'TELNET agent',
					'username' => 'zabbix',
					'params_es' => 'script to be executed',
					'errors' => array(
							'ERROR: Cannot add discovery rule',
							'Check the key, please. Default example was passed.'
					)
				)
			),
			// Default
			array(
				array(
					'expected' => TEST_BAD,
					'type' => 'JMX agent',
					'name' => 'JMX agent',
					'username' => 'zabbix',
					'params_es' => 'script to be executed',
					'errors' => array(
							'ERROR: Cannot add discovery rule',
							'Check the key, please. Default example was passed.'
					)
				)
			)
		);
	}

	/**
	 * @dataProvider create
	 */
	public function testFormDiscoveryRule_SimpleCreate($data) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('CONFIGURATION OF HOSTS');

		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('form');

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');
		$this->zbxTestTextPresent('Discovery rule');

		if (isset($data['type'])) {
			$this->zbxTestDropdownSelect('type', $data['type']);
		}
		$type = $this->getSelectedLabel('type');

		switch ($type) {
			case 'Zabbix agent':
			case 'Simple check':
			case 'SNMPv1 agent':
			case 'SNMPv2 agent':
			case 'SNMPv3 agent':
			case 'SNMP trap':
			case 'External check':
			case 'IPMI agent':
			case 'SSH agent':
			case 'TELNET agent':
			case 'JMX agent':
				$interfaceid = $this->getSelectedLabel('interfaceid');
				break;
			default:
				$this->assertNotVisible('interfaceid');
		}

		if (isset($data['name'])) {
			$this->input_type('name', $data['name']);
		}
		$name = $this->getValue('name');

		if (isset($data['key'])) {
			$this->input_type('key', $data['key']);
		}
		$key = $this->getValue('key');

		if (isset($data['username'])) {
			$this->input_type('username', $data['username']);
		}

		if (isset($data['ipmi_sensor'])) {
			if (isset($data['ipmiSpaces'])) {
				$this->getEval("this.browserbot.findElement('ipmi_sensor').value = '    ipmi_sensor    ';");
				$ipmi_sensor = $this->getEval("this.browserbot.findElement('ipmi_sensor').value;");
			}
			else {
				$this->input_type('ipmi_sensor', $data['ipmi_sensor']);
				$ipmi_sensor = $this->getValue('ipmi_sensor');
			}
		}

		if (isset($data['params_es'])) {
			$this->input_type('params_es', $data['params_es']);
		}

		if (isset($data['formula'])) {
			$this->zbxTestCheckboxSelect('multiplier');
			$this->input_type('formula', $data['formula']);
		}

		if (isset($data['delay']))	{
			$this->input_type('delay', $data['delay']);
		}

		$itemFlexFlag = true;
		if (isset($data['flexPeriod'])) {

			$itemCount = 0;
			foreach ($data['flexPeriod'] as $period) {
				$this->input_type('new_delay_flex_period', $period['flexTime']);
				$itemCount ++;

				if (isset($period['flexDelay'])) {
					$this->input_type('new_delay_flex_delay', $period['flexDelay']);
				}
				$this->zbxTestClickWait('add_delay_flex');

				if (isset($period['instantCheck'])) {
					foreach ($data['errors'] as $msg) {
						$this->zbxTestTextPresent($msg);
					}
					$itemFlexFlag = false;
				}

				if (isset($period['maximumItems']) || $itemCount == 7) {
					$this->assertNotVisible('new_delay_flex_delay');
					$this->assertNotVisible('new_delay_flex_period');
				}
				else {
					$this->assertVisible('new_delay_flex_delay');
					$this->assertVisible('new_delay_flex_period');
				}

				if (isset($period['remove'])) {
					$itemCount --;
					$this->zbxTestClick('remove');
					sleep(1);
				}
			}
		}

		if (isset($data['history'])) {
			$this->input_type('history', $data['history']);
		}

		if (isset($data['trends'])) {
			$this->input_type('trends', $data['trends']);
		}

		if ($itemFlexFlag == true) {
			$this->zbxTestClickWait('save');
			$expected = $data['expected'];
			switch ($expected) {
				case TEST_GOOD:
					$this->zbxTestTextPresent('Discovery rule created');
					$this->zbxTestCheckTitle('Configuration of discovery rules');
					$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');
					$this->zbxTestTextPresent(array('Item prototypes',  'Trigger prototypes', 'Graph prototypes'));
					break;

				case TEST_BAD:
					$this->zbxTestCheckTitle('Configuration of discovery rules');
					$this->zbxTestTextPresent(array('CONFIGURATION OF DISCOVERY RULES','Discovery rule'));
					foreach ($data['errors'] as $msg) {
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent(array('Name', 'Type', 'Key'));
					break;
			}
		}

		if (isset($data['formCheck'])) {
			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait('link=Discovery rules');

			if (isset ($data['dbName'])) {
				$dbName = $data['dbName'];
			}
			else {
				$dbName = $name;
			}
			$this->zbxTestClick("link=$dbName");
			$this->wait();
			$this->assertAttribute("//input[@id='name']/@value", 'exact:'.$name);
			$this->assertAttribute("//input[@id='key']/@value", 'exact:'.$key);
			$this->assertElementPresent("//select[@id='type']/option[text()='$type']");
			switch ($type) {
				case 'Zabbix agent':
				case 'Simple check':
				case 'SNMPv1 agent':
				case 'SNMPv2 agent':
				case 'SNMPv3 agent':
				case 'SNMP trap':
				case 'External check':
				case 'IPMI agent':
				case 'SSH agent':
				case 'TELNET agent':
				case 'JMX agent':
					$this->assertElementPresent("//select[@id='interfaceid']/optgroup/option[text()='".$interfaceid."']");
					break;
				default:
					$this->assertNotVisible('interfaceid');
			}

			if (isset($data['ipmi_sensor'])) {
				if (isset($data['ipmiSpaces'])) {
					$ipmiValue = $this->getEval("this.browserbot.findElement('ipmi_sensor').value;");
				}
				else {
					$ipmiValue = $this->getValue('ipmi_sensor');
				}
				$this->assertEquals($ipmi_sensor, $ipmiValue);
				}
		}

		if (isset($data['dbCheck'])) {
			$result = DBselect("SELECT name, key_ FROM items where name = '".$name."' and hostid = ".$this->hostid);
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $name);
				$this->assertEquals($row['key_'], $key);
			}
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT itemid FROM items where name = '".$name."' and hostid = ".$this->hostid);
			while ($row = DBfetch($result)) {
				$itemId = $row['itemid'];
			}

			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait("link=Discovery rules");

			$this->zbxTestCheckboxSelect("g_hostdruleid_$itemId");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent('Discovery rules deleted');
			$this->zbxTestTextNotPresent($name);

		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormDiscoveryRule_Teardown() {
		DBrestore_tables('items');
	}
}
