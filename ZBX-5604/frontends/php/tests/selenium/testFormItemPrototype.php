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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/class.cwebtest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

define('ITEM_GOOD', 0);
define('ITEM_BAD', 1);

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testFormItemPrototype extends CWebTest {


	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	/**
	 * The id of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $hostid = 40001;

	/**
	 * The name of the test discovery rule created in the test data set on host.
	 *
	 * @var string
	 */
	protected $discoveryRule = 'testFormDiscoveryRule';

	/**
	 * The name of the test discovery rule created in the test data set on template.
	 *
	 * @var string
	 */
	protected $discoveryRuleTemplate = 'testInheritanceDiscoveryRule';


	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormItemPrototype_Setup() {
		DBsave_tables('items');
	}
	// Returns layout data
	public static function layout() {
		return array(
			array(
				array('type' => 'Zabbix agent', 'host' => 'Simple form test host')
			),
			array(
				array('host' => 'Simple form test host', 'form' => 'testFormItemPrototype1')
			),
			array(
				array(
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Hexadecimal',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Octal',
					'host' => 'Simple form test host'
				)
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Numeric (float)', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Character', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Log', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Text', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'Zabbix agent (active)', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'Simple check', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'SNMPv1 agent', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'SNMPv1 agent', 'value_type' => 'Numeric (float)', 'host' => 'Simple form test host')
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
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Hexadecimal',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Octal',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (float)',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Character',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Log',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Text',
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
				array('type' => 'SNMP trap', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'Zabbix internal', 'host' => 'Simple form test host')
			),
			array(
				array(
					'type' => 'Zabbix internal',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Simple form test host'
				)
			),
			array(
				array('type' => 'Zabbix trapper', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'Zabbix aggregate', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'External check', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'Database monitor', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'IPMI agent', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'SSH agent', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'SSH agent', 'authtype' => 'Public key', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'SSH agent', 'authtype' => 'Password', 'host' => 'Simple form test host')
			),
			array(
				array(
					'type' => 'SSH agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'type' => 'SSH agent',
					'authtype' => 'Password',
					'value_type' => 'Character',
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
				array('type' => 'Calculated', 'host' => 'Simple form test host')
			),
			array(
				array('type' => 'Zabbix agent', 'template' => 'Inheritance test template')
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceItemPrototype1'
				)
			),
			array(
				array(
					'host' => 'Template inheritance test host',
					'templatedHost' => 'Inheritance test template',
					'form' => 'testInheritanceItemPrototype1'
				)
			),
			array(
				array(
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Hexadecimal',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Octal',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (float)',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'Zabbix agent',
					'value_type' => 'Character',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Log', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'Zabbix agent', 'value_type' => 'Text', 'template' => 'Inheritance test template')
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
				array(
					'type' => 'SNMPv1 agent',
					'value_type' => 'Numeric (float)',
					'template' => 'Inheritance test template'
				)
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
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Hexadecimal',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Octal',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (float)',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Character',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Log',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Text',
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
				array('type' => 'SNMP trap', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'Zabbix internal', 'template' => 'Inheritance test template')
			),
			array(
				array(
					'type' => 'Zabbix internal',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array('type' => 'Zabbix trapper', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'Zabbix aggregate', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'External check', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'Database monitor', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'IPMI agent', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'SSH agent', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'SSH agent', 'authtype' => 'Public key', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'SSH agent', 'authtype' => 'Password', 'template' => 'Inheritance test template')
			),
			array(
				array(
					'type' => 'SSH agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'type' => 'SSH agent',
					'authtype' => 'Password',
					'value_type' => 'Character',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array('type' => 'TELNET agent', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'JMX agent', 'template' => 'Inheritance test template')
			),
			array(
				array('type' => 'Calculated', 'template' => 'Inheritance test template')
			)
		);
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormItemPrototype_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickWait('link='.$data['template']);
			$hostid = 30000;
			$discoveryRule = $this->discoveryRuleTemplate;
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickWait('link='.$data['host']);
			if (isset($data['templatedHost'])) {
				$hostid = 30001;
				$discoveryRule = $this->discoveryRuleTemplate;
			}
			else {
				$hostid = 40001;
				$discoveryRule = $this->discoveryRule;
			}
		}

		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link='.$discoveryRule);
		$this->zbxTestClickWait("link=Item prototypes");
		$this->checkTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent(array('CONFIGURATION OF ITEM PROTOTYPES', "Item prototypes of ".$discoveryRule));

		if (isset($data['form'])) {
			$this->zbxTestClickWait('link='.$data['form']);
		}
		else {
			$this->zbxTestClickWait('form');
		}

		$this->checkTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent(array('CONFIGURATION OF ITEM PROTOTYPES', 'Item prototype'));

		if (isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Parent items');
			if (isset($data['hostTemplate'])) {
				$this->assertElementPresent("//a[text()='".$data['hostTemplate']."']");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Parent items');
		}

		$this->zbxTestTextPresent('Name');
		$this->assertVisible('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", 255);
		$this->assertAttribute("//input[@id='name']/@size", 50);
		$this->assertAttribute("//input[@id='name']/@autofocus", 'autofocus');

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
		if (!isset($data['templatedHost'])) {
			$this->assertElementPresent('keyButton');
		}
		else {
			$this->assertAttribute("//input[@id='key']/@readonly", 'readonly');
		}

		if ($type == 'Database monitor' && !isset($data['form'])) {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "db.odbc.select[<unique short description>,<dsn>]");
		}

		if ($type == 'SSH agent' && !isset($data['form'])) {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "ssh.run[<unique short description>,<ip>,<port>,<encoding>]");
		}

		if ($type == 'TELNET agent' && !isset($data['form'])) {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "telnet.run[<unique short description>,<ip>,<port>,<encoding>]");
		}

		if ($type == 'JMX agent' && !isset($data['form'])) {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "jmx[<object name>,<attribute name>]");
		}

		if ($type == 'SNMPv3 agent') {
			if (isset($data['snmpv3_securitylevel'])) {
				$this->zbxTestDropdownSelect('snmpv3_securitylevel', $data['snmpv3_securitylevel']);
			}
			$snmpv3_securitylevel = $this->getSelectedLabel('snmpv3_securitylevel');
		}
		else {
			$snmpv3_securitylevel = null;
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

		if (isset($data['templatedHost'])) {
			$value_type = $this->getValue('value_type_name');
		}
		elseif (isset($data['value_type'])) {
			$this->zbxTestDropdownSelect('value_type', $data['value_type']);
			$value_type = $this->getSelectedLabel('value_type');
		}
		else {
			$value_type = $this->getSelectedLabel('value_type');
		}

		if ($value_type == 'Numeric (unsigned)') {
			if (isset($data['data_type'])) {
				$this->zbxTestDropdownSelect('data_type', $data['data_type']);
			}
			if (!isset($data['templatedHost'])) {
				$data_type = $this->getSelectedLabel('data_type');
			}
			else {
				$data_type = $this->getValue('data_type_name');
			}
		}

		if ($type == 'SSH agent') {
			if (isset($data['authtype'])) {
				$this->zbxTestDropdownSelect('authtype', $data['authtype']);
			}
			$authtype = $this->getSelectedLabel('authtype');
		}
		else {
			$authtype = null;
		}

		if ($type == 'Database monitor') {
			$this->zbxTestTextPresent('SQL query');
			$this->assertVisible('params_ap');
			$this->assertAttribute("//textarea[@id='params_ap']/@rows", 7);
			$addParams = $this->getValue('params_ap');
			$this->assertEquals($addParams, "");
		}
		else {
			$this->zbxTestTextNotPresent('SQL query');
			$this->assertNotVisible('params_ap');
		}

		if ($type == 'SSH agent' || $type == 'TELNET agent' ) {
			$this->zbxTestTextPresent('Executed script');
			$this->assertVisible('params_es');
			$this->assertAttribute("//textarea[@id='params_es']/@rows", 7);
		}
		else {
			$this->zbxTestTextNotPresent('Executed script');
			$this->assertNotVisible('params_es');
		}

		if ($type == 'Calculated') {
			$this->zbxTestTextPresent('Formula');
			$this->assertVisible('params_f');
			$this->assertAttribute("//textarea[@id='params_f']/@rows", 7);
		}
		else {
			$this->zbxTestTextNotPresent('Formula');
			$this->assertNotVisible('params_f');
		}

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

		if ($type == 'SSH agent' || $type == 'TELNET agent' || $type == 'JMX agent' || $type == 'Database monitor') {
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

		if	($type == 'SSH agent' && $authtype == 'Public key') {
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
			if (!isset($data['form'])) {
				$this->assertAttribute("//input[@id='snmp_oid']/@value", 'interfaces.ifTable.ifEntry.ifInOctets.1');
			}

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
			if (!isset($data['form'])) {
				$this->assertAttribute("//input[@id='snmp_community']/@value", 'public');
			}
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

		switch ($type) {
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
				if (!isset($data['form'])) {
					$this->assertAttribute("//input[@id='delay']/@value", 30);
				}
				break;
			default:
				$this->zbxTestTextNotPresent('Update interval (in sec)');
				$this->assertNotVisible('delay');
		}

		if (!isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Type of information');
			$this->assertVisible('value_type');
			$this->zbxTestDropdownHasOptions('value_type', array(
				'Numeric (unsigned)',
				'Numeric (float)',
				'Character',
				'Log',
				'Text'
			));
		}

		if (!isset($data['templatedHost'])) {
			$this->assertAttribute("//*[@id='value_type']/option[text()='Numeric (unsigned)']/@selected", 'selected');
			$this->isEditable("//*[@id='value_type']/option[text()='Numeric (unsigned)']");
			$this->isEditable("//*[@id='value_type']/option[text()='Numeric (float)']");
		}

		if (($type == 'Zabbix aggregate' || $type == 'Calculated') && !isset($data['templatedHost'])) {
			$this->assertAttribute("//*[@id='value_type']/option[text()='Character']/@disabled", 'disabled');
			$this->assertAttribute("//*[@id='value_type']/option[text()='Log']/@disabled", 'disabled');
			$this->assertAttribute("//*[@id='value_type']/option[text()='Text']/@disabled", 'disabled');
		}
		elseif (!isset($data['templatedHost'])) {
			$this->isEditable("//*[@id='value_type']/option[text()='Character']");
			$this->isEditable("//*[@id='value_type']/option[text()='Log']");
			$this->isEditable("//*[@id='value_type']/option[text()='Text']");
		}

		if ($value_type == 'Numeric (unsigned)' && !isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Data type');
			$this->assertVisible('data_type');
			$this->zbxTestDropdownHasOptions('data_type', array('Boolean', 'Octal', 'Decimal', 'Hexadecimal'));
			$this->assertAttribute("//*[@id='data_type']/option[text()='Decimal']/@selected", 'selected');
			$this->isEditable("//*[@id='data_type']/option[text()='Decimal']");

			if ($type == 'Zabbix aggregate' || $type == 'Calculated') {
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
		elseif (isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Data type');
			$this->assertVisible('data_type_name');
			$this->assertAttribute("//input[@id='data_type_name']/@readonly", 'readonly');
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
			if(isset($data['templatedHost'])) {
				$this->assertAttribute("//input[@id='units']/@readonly", 'readonly');
			}

			$this->zbxTestTextPresent('Use custom multiplier');
			if (!isset($data['templatedHost'])) {
				$this->assertVisible('multiplier');
				$this->assertAttribute("//input[@id='multiplier']/@type", 'checkbox');
			}
			else {
				$this->assertElementPresent("//input[@type='checkbox' and @id='multiplier' and @disabled = 'disabled']");
			}

			$this->assertVisible('formula');
			$this->assertAttribute("//input[@id='formula']/@maxlength", 255);
			$this->assertAttribute("//input[@id='formula']/@size", 25);
			$this->assertAttribute("//input[@id='formula']/@value", 1);
			if (!isset($data['form'])) {
				$this->assertAttribute("//input[@id='formula']/@value", 1);
			}
			if (!isset($data['templatedHost'])) {
				$this->assertElementPresent("//input[@id='formula']/@disabled");
			}
			else {
				$this->assertAttribute("//input[@id='formula']/@readonly", 'readonly');
			}
		}
		else {
			$this->zbxTestTextNotPresent('Units');
			$this->assertNotVisible('units');

			$this->zbxTestTextNotPresent('Use custom multiplier');
			$this->assertNotVisible('multiplier');
			$this->assertNotVisible('formula');
		}

		switch ($type) {
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
		if (!isset($data['form'])) {
			$this->assertAttribute("//input[@id='history']/@value", 90);
		}

		if ($value_type == 'Numeric (unsigned)' || $value_type == 'Numeric (float)') {
			$this->zbxTestTextPresent('Keep trends (in days)');
			$this->assertVisible('trends');
			$this->assertAttribute("//input[@id='trends']/@maxlength", 8);
			if (!isset($data['form'])) {
				$this->assertAttribute("//input[@id='trends']/@value", 365);
			}
			$this->assertAttribute("//input[@id='trends']/@size", 8);
		}
		else {
			$this->zbxTestTextNotPresent('Keep trends (in days)');
			$this->assertNotVisible('trends');
		}

		if ($value_type == 'Numeric (float)' || ($value_type == 'Numeric (unsigned)' && $data_type != 'Boolean')) {
			$this->zbxTestTextPresent('Store value');
			if (!isset($data['templatedHost'])) {
				$this->assertVisible('delta');
				$this->zbxTestDropdownHasOptions('delta', array('As is', 'Delta (speed per second)', 'Delta (simple change)'));
				$this->assertAttribute("//*[@id='delta']/option[text()='As is']/@selected", 'selected');
			}
			else {
				$this->assertVisible('delta_name');
				$this->assertAttribute("//input[@id='delta_name']/@maxlength", 255);
				$this->assertAttribute("//input[@id='delta_name']/@readonly", 'readonly');
			}
		}
		else {
			$this->zbxTestTextNotPresent('Store value');
			$this->assertNotVisible('delta');
		}

		if ($value_type == 'Numeric (float)' || $value_type == 'Numeric (unsigned)' || $value_type == 'Character') {
			$this->zbxTestTextPresent(array('Show value', 'show value mappings'));
			if (!isset($data['templatedHost'])) {
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
				$this->assertVisible('valuemap_name');
				$this->assertAttribute("//input[@id='valuemap_name']/@maxlength", 255);
				$this->assertAttribute("//input[@id='valuemap_name']/@size", 25);
				$this->assertAttribute("//input[@id='valuemap_name']/@readonly", 'readonly');
			}
		}
		else {
			$this->zbxTestTextNotPresent(array('Show value', 'show value mappings'));
			$this->assertNotVisible('valuemapid');
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

		$this->assertVisible('save');
		$this->assertAttribute("//input[@id='save']/@value", 'Save');

		$this->assertVisible('cancel');
		$this->assertAttribute("//input[@id='cancel']/@value", 'Cancel');

		if (isset($data['form'])) {
			$this->assertVisible('clone');
			$this->assertAttribute("//input[@id='clone']/@value", 'Clone');
		}
		else {
			$this->assertElementNotPresent('clone');
		}

		if ((isset($data['form']) && !isset($data['templatedHost']))) {
			$this->assertVisible('delete');
			$this->assertAttribute("//input[@id='delete']/@value", 'Delete');
		}
		else {
			$this->assertElementNotPresent('delete');
		}
	}


	// Returns update data
	public static function update() {
		return DBdata("select * from items where hostid = 40001 and key_ LIKE 'item-prototype-form%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testFormItemPrototype_SimpleUpdate($data) {
		$sqlItems = "select itemid, hostid, name, key_, delay from items order by itemid";
		$oldHashItems = DBhash($sqlItems);

		$this->zbxTestLogin('disc_prototypes.php?form=update&itemid='.$data['itemid'].'&parent_discoveryid=33800');
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent(array(
			'Item updated', $data['name'],
			'CONFIGURATION OF ITEM PROTOTYPES',
			'Item prototypes of '.$this->discoveryRule
		));

		$this->assertEquals($oldHashItems, DBhash($sqlItems));
	}

	// Returns create data
	public static function create() {
		return array(
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Checksum of $1',
					'key' => 'vfs.file.cksum[/sbin/shutdown]',
					'dbName' => 'Checksum of /sbin/shutdown',
					'dbCheck' => true,
					'formCheck' =>true
				)
			),
			// Duplicate item
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Checksum of $1',
					'key' => 'vfs.file.cksum[/sbin/shutdown]',
					'errors' => array(
						'ERROR: Cannot add item',
						'Item with key "vfs.file.cksum[/sbin/shutdown]" already exists on'
					)
				)
			),
			// Item name is missing
			array(
				array(
					'expected' => ITEM_BAD,
					'key' =>'item-name-missing',
					'errors' => array(
						'Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.'
					)
				)
			),
			// Item key is missing
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item name',
					'errors' => array(
						'Page received incorrect data',
						'Incorrect value for field "Key": cannot be empty.'
					)
				)
			),
			// Empty formula
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => '',
					'formulaValue' => '',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Field "Custom multiplier" is not decimal number.'
					)
				)
			),
			// Incorrect formula
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => ' ',
					'formulaValue' => '',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Field "Custom multiplier" is not decimal number.'
					)
				)
			),
			// Incorrect formula
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => 'form ula',
					'formulaValue' => 'form ula',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Field "Custom multiplier" is not decimal number.'
					)
				)
			),
			// Incorrect formula
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => ' a1b2 c3 ',
					'formulaValue' => 'a1b2 c3',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Field "Custom multiplier" is not decimal number.'
					)
				)
			),
			// Incorrect formula
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => ' 32 1 abc',
					'formulaValue' => '32 1 abc',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Field "Custom multiplier" is not decimal number.'
					)
				)
			),
			// Incorrect formula
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => '32 1 abc',
					'formulaValue' => '32 1 abc',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Field "Custom multiplier" is not decimal number.'
					)
				)
			),
			// Incorrect formula
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => '321abc',
					'formulaValue' => '321abc',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Field "Custom multiplier" is not decimal number.'
					)
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item formula1',
					'key' => 'item-formula-test',
					'formula' => '5',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			// Empty timedelay
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item delay',
					'key' => 'item-delay-test',
					'delay' => 0,
					'errors' => array(
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Incorrect timedelay
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item delay',
					'key' => 'item-delay-test',
					'delay' => '-30',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Update interval (in sec)": must be between 0 and 86400.'
					)
				)
			),
			// Incorrect timedelay
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item delay',
					'key' => 'item-delay-test',
					'delay' => 86401,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Update interval (in sec)": must be between 0 and 86400.'
					)
				)
			),
			// Empty time flex period
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
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
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
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
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
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
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
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
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
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
					'expected' => ITEM_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
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
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
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
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item flex1',
					'key' => 'item-flex-delay1',
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
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
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
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item flex2',
					'key' => 'item-flex-delay2',
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
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay3',
					'flexPeriod' => array(
						array('flexTime' => '1-5,00:00-24:00'),
						array('flexTime' => '6-7,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay4',
					'delay' => 0,
					'flexPeriod' => array(
						array('flexTime' => '1-7,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay5',
					'flexPeriod' => array(
						array('flexTime' => '1-7,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00'),
						array('flexTime' => '1-5,00:00-24:00'),
						array('flexTime' => '6-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => array(
						array('flexTime' => '1-5,00:00-24:00'),
						array('flexTime' => '6-7,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => array(
						array('flexTime' => '1-7,00:00-24:00'),
						array('flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00'),
						array('flexTime' => '1-7,00:00-24:00')
					),
					'errors' => array(
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay6',
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
					'expected' => ITEM_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay7',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00', 'remove' => true),
						array('flexTime' => '1-7,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item flex Check',
					'key' => 'item-flex-delay8',
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
					'expected' => ITEM_BAD,
					'name' => 'Item flex-maximum entries',
					'key' => 'item-flex-maximum',
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
			// Maximum flexfields allowed reached- error
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item flex-maximum entries',
					'key' => 'item-flex-maximum',
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
					'expected' => ITEM_GOOD,
					'name' => 'Item flex-maximum save OK',
					'key' => 'item-flex-maximum-save',
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
					'expected' => ITEM_BAD,
					'name' => 'Item flex-maximum with remove',
					'key' => 'item-flex-maximum-remove',
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
					'expected' => ITEM_GOOD,
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
					'expected' => ITEM_GOOD,
					'name' => 'Item flex-symbols in flexdelay',
					'key' => 'item-flex-symbols-flexdelay',
					'flexPeriod' => array(
						array('flexDelay' => '50abc', 'flexTime' => '1-7,00:00-24:00')
					)
				)
			),
			// History
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item history',
					'key' => 'item-history-empty',
					'history' => ''
				)
			),
			// History
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item history',
					'key' => 'item-history-test',
					'history' => 65536,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Keep history (in days)": must be between 0 and 65535.'
					)
				)
			),
			// History
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item history',
					'key' => 'item-history-test',
					'history' => '-1',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "Keep history (in days)": must be between 0 and 65535.'
					)
				)
			),
			// History
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item history',
					'key' => 'item-history-test',
					'history' => 'days'
				)
			),
			// Trends
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item trends',
					'key' => 'item-trends-empty',
					'trends' => '',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			// Trends
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item trends',
					'key' => 'item-trends-test',
					'trends' => '-1',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "Keep trends (in days)": must be between 0 and 65535.'
					)
				)
			),
			// Trends
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'Item trends',
					'key' => 'item-trends-test',
					'trends' => 65536,
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "Keep trends (in days)": must be between 0 and 65535.'
					)
				)
			),
			// Trends
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'Item trends Check',
					'key' => 'item-trends-test',
					'trends' => 'trends',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => '!@#$%^&*()_+-=[]{};:"|,./<>?',
					'key' => 'item-symbols-test',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'itemSimple',
					'key' => 'key-template-simple',
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'itemName',
					'key' => 'key-template-item',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'itemTrigger',
					'key' => 'key-template-trigger',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'itemRemove',
					'key' => 'key-template-remove',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true)
			),
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'itemInheritance',
					'key' => 'test-item-reuse',
					'errors' => array(
						'ERROR: Cannot add item',
						'Item with key "test-item-reuse" already exists on "Simple form test host".'
					)
				)
			),
			// List of all item types
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'Zabbix agent',
					'name' => 'Zabbix agent',
					'key' => 'item-zabbix-agent',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'Zabbix agent (active)',
					'name' => 'Zabbix agent (active)',
					'key' => 'item-zabbix-agent-active',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'Simple check',
					'name' => 'Simple check',
					'key' => 'item-simple-check',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'SNMPv1 agent',
					'name' => 'SNMPv1 agent',
					'key' => 'item-snmpv1-agent',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'SNMPv2 agent',
					'name' => 'SNMPv2 agent',
					'key' => 'item-snmpv2-agent',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'SNMPv3 agent',
					'name' => 'SNMPv3 agent',
					'key' => 'item-snmpv3-agent',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'SNMP trap',
					'name' => 'SNMP trap',
					'key' => 'snmptrap.fallback',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'Zabbix internal',
					'name' => 'Zabbix internal',
					'key' => 'item-zabbix-internal',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'Zabbix trapper',
					'name' => 'Zabbix trapper',
					'key' => 'item-zabbix-trapper',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'Zabbix aggregate',
					'name' => 'Zabbix aggregate',
					'key' => 'grpmax[Zabbix servers group,some-item-key,last,0]',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_BAD,
					'type' => 'Zabbix aggregate',
					'name' => 'Zabbix aggregate',
					'key' => 'item-zabbix-aggregate',
					'errors' => array(
						'ERROR: Cannot add item',
						'Key "item-zabbix-aggregate" does not match'
					)
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'External check',
					'name' => 'External check',
					'key' => 'item-external-check',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'Database monitor',
					'name' => 'Database monitor',
					'key' => 'item-database-monitor',
					'dbCheck' => true,
					'params_ap' => 'SELECT * FROM items',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'IPMI agent',
					'name' => 'IPMI agent',
					'key' => 'item-ipmi-agent',
					'ipmi_sensor' => 'ipmi_sensor',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
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
					'expected' => ITEM_GOOD,
					'type' => 'SSH agent',
					'name' => 'SSH agent',
					'key' => 'item-ssh-agent',
					'username' => 'zabbix',
					'params_es' => 'executed script',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'TELNET agent',
					'name' => 'TELNET agent',
					'key' => 'item-telnet-agent',
					'username' => 'zabbix',
					'params_es' => 'executed script',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_BAD,
					'type' => 'IPMI agent',
					'name' => 'IPMI agent error',
					'key' => 'item-ipmi-agent-error',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "IPMI sensor": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => ITEM_BAD,
					'type' => 'SSH agent',
					'name' => 'SSH agent error',
					'key' => 'item-ssh-agent-error',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "User name": cannot be empty.',
							'Incorrect value for field "Executed script": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => ITEM_BAD,
					'type' => 'TELNET agent',
					'name' => 'TELNET agent error',
					'key' => 'item-telnet-agent-error',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "User name": cannot be empty.',
							'Incorrect value for field "Executed script": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'JMX agent',
					'name' => 'JMX agent',
					'key' => 'proto-jmx-agent',
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'type' => 'Calculated',
					'name' => 'Calculated',
					'key' => 'item-calculated',
					'params_f' => 'formula',
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => ITEM_BAD,
					'type' => 'Calculated',
					'name' => 'Calculated',
					'key' => 'item-calculated',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "Formula": cannot be empty.'
					)
				)
			),
			// Empty SQL query
			array(
				array(
					'expected' => ITEM_BAD,
					'type' => 'Database monitor',
					'name' => 'Database monitor',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "SQL query": cannot be empty.'
					)
				)
			),
			// Default
			array(
				array(
					'expected' => ITEM_BAD,
					'type' => 'Database monitor',
					'name' => 'Database monitor',
					'params_ap' => 'SELECT * FROM items',
					'errors' => array(
							'ERROR: Cannot add item',
							'Check the key, please. Default example was passed.'
					)
				)
			),
			// Default
			array(
				array(
					'expected' => ITEM_BAD,
					'type' => 'SSH agent',
					'name' => 'SSH agent',
					'username' => 'zabbix',
					'params_es' => 'script to be executed',
					'errors' => array(
							'ERROR: Cannot add item',
							'Check the key, please. Default example was passed.'
					)
				)
			),
			// Default
			array(
				array(
					'expected' => ITEM_BAD,
					'type' => 'TELNET agent',
					'name' => 'TELNET agent',
					'username' => 'zabbix',
					'params_es' => 'script to be executed',
					'errors' => array(
							'ERROR: Cannot add item',
							'Check the key, please. Default example was passed.'
					)
				)
			),
			// Default
			array(
				array(
					'expected' => ITEM_BAD,
					'type' => 'JMX agent',
					'name' => 'JMX agent',
					'username' => 'zabbix',
					'params_es' => 'script to be executed',
					'errors' => array(
							'ERROR: Cannot add item',
							'Check the key, please. Default example was passed.'
					)
				)
			)
		);
	}

	/**
	 * @dataProvider create
	 */
	public function testFormItemPrototype_SimpleCreate($data) {
		$this->zbxTestLogin('disc_prototypes.php?hostid=40001&parent_discoveryid=33800');

		if (isset($data['name'])) {
			$itemName = $data['name'];
		}
		if (isset($data['key'])) {
			$keyName = $data['key'];
		}

		$this->zbxTestClickWait('form');

		if (isset($data['type'])) {
			$this->zbxTestDropdownSelect('type', $data['type']);
		}
		$type = $this->getSelectedLabel('type');

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

		if (isset($data['params_ap'])) {
			$this->input_type('params_ap', $data['params_ap']);
		}

		if (isset($data['params_es'])) {
			$this->input_type('params_es', $data['params_es']);
		}

		if (isset($data['params_f'])) {
			$this->input_type('params_f', $data['params_f']);
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

		$value_type = $this->getSelectedLabel('value_type');
		$data_type = $this->getSelectedLabel('data_type');

		if ($itemFlexFlag == true) {
			$this->zbxTestClickWait('save');
			$expected = $data['expected'];
			switch ($expected) {
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
					if (isset($data['formula'])) {
						$formulaValue = $this->getValue('formula');
						$this->assertEquals($data['formulaValue'], $formulaValue);
					}
					break;
			}
		}

		if (isset($data['formCheck'])) {
			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait("link=Discovery rules");
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait("link=Item prototypes");

			if (isset ($data['dbName'])) {
				$itemNameDB = $data['dbName'];
				$this->zbxTestTextPresent($itemNameDB);
				$this->zbxTestClickWait("link=$itemNameDB");
			}
			else {
				$this->zbxTestTextPresent($itemName);
				$this->zbxTestClickWait("link=$itemName");
			}

			$this->assertElementValue('name', $itemName);
			$this->assertElementValue('key', $keyName);
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
			$this->assertElementPresent("//select[@id='value_type']/option[text()='$value_type']");
			$this->assertElementPresent("//select[@id='data_type']/option[text()='$data_type']");

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
			$result = DBselect("SELECT name, key_ FROM items where name = '".$itemName."'  AND hostid = ".$this->hostid);

			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $itemName);
				$this->assertEquals($row['key_'], $keyName);
			}
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT name, key_, itemid FROM items where name = '".$itemName."'  AND hostid = ".$this->hostid);
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
			$this->zbxTestTextPresent('Items deleted');
			$this->zbxTestTextNotPresent($itemName);
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormItemPrototype_Teardown() {
		DBrestore_tables('items');
	}
}
