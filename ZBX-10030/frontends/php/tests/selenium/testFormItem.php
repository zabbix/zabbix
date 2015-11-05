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

class testFormItem extends CWebTest {

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	/**
	 * The name of the item created in the test data set.
	 *
	 * @var string
	 */
	protected $item = 'testFormItem1';


	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormItem_Setup() {
		DBsave_tables('items');
	}

	// Returns layout data
	public static function layout() {
		return [
			[
				['type' => 'Zabbix agent', 'host' => 'Simple form test host']
			],
			[
				['host' => 'Simple form test host', 'key' => 'test-item-form1']
			],
			[
				[
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Hexadecimal',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Octal',
					'host' => 'Simple form test host'
				]
			],
			[
				['type' => 'Zabbix agent', 'value_type' => 'Numeric (float)', 'host' => 'Simple form test host']
			],
			[
				['type' => 'Zabbix agent', 'value_type' => 'Character', 'host' => 'Simple form test host']
			],
			[
				['type' => 'Zabbix agent', 'value_type' => 'Log', 'host' => 'Simple form test host']
			],
			[
				['type' => 'Zabbix agent', 'value_type' => 'Text', 'host' => 'Simple form test host']
			],
			[
				['type' => 'Zabbix agent (active)', 'host' => 'Simple form test host']
			],
			[
				['type' => 'Simple check', 'host' => 'Simple form test host']
			],
			[
				['type' => 'SNMPv1 agent', 'host' => 'Simple form test host']
			],
			[
				['type' => 'SNMPv1 agent', 'value_type' => 'Numeric (float)', 'host' => 'Simple form test host']
			],
			[
				['type' => 'SNMPv2 agent', 'host' => 'Simple form test host']
			],
			[
				['type' => 'SNMPv3 agent', 'host' => 'Simple form test host']
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Hexadecimal',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Octal',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (float)',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Character',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Log',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Text',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authNoPriv',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authPriv',
					'host' => 'Simple form test host'
				]
			],
			[
				['type' => 'SNMP trap', 'host' => 'Simple form test host'
				]
			],
			[
				['type' => 'Zabbix internal', 'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'Zabbix internal',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Simple form test host'
				]
			],
			[
				['type' => 'Zabbix trapper', 'host' => 'Simple form test host']
			],
			[
				['type' => 'Zabbix aggregate', 'host' => 'Simple form test host']
			],
			[
				['type' => 'External check', 'host' => 'Simple form test host']
			],
			[
				['type' => 'Database monitor', 'host' => 'Simple form test host']
			],
			[
				['type' => 'IPMI agent', 'host' => 'Simple form test host']
			],
			[
				['type' => 'SSH agent', 'host' => 'Simple form test host']
			],
			[
				['type' => 'SSH agent', 'authtype' => 'Public key', 'host' => 'Simple form test host']
			],
			[
				['type' => 'SSH agent', 'authtype' => 'Password', 'host' => 'Simple form test host']
			],
			[
				[
					'type' => 'SSH agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'SSH agent',
					'authtype' => 'Password',
					'value_type' => 'Character',
					'host' => 'Simple form test host'
				]
			],
			[
				['type' => 'TELNET agent', 'host' => 'Simple form test host']
			],
			[
				['type' => 'JMX agent', 'host' => 'Simple form test host']
			],
			[
				['type' => 'Calculated', 'host' => 'Simple form test host']
			],
			[
				['type' => 'Zabbix agent', 'host' => 'Inheritance test template']
			],
			[
				[
					'type' => 'Zabbix agent',
					'host' => 'Inheritance test template',
					'key' => 'test-inheritance-item1'
				]
			],
			[
				[
					'type' => 'Zabbix agent',
					'host' => 'Template inheritance test host',
					'key' => 'test-inheritance-item1'
				]
			],
			[
				[
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'Zabbix agent',
					'host' => 'Template inheritance test host',
					'key' => 'test-inheritance-item1'
				]
			],
			[
				[
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Hexadecimal',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'Zabbix agent',
					'host' => 'Template inheritance test host',
					'key' => 'test-inheritance-item1'
				]
			],
			[
				[
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Octal',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'Zabbix agent',
					'value_type' => 'Numeric (float)',
					'host' => 'Inheritance test template'
					]
			],
			[
				[
					'type' => 'Zabbix agent',
					'value_type' => 'Character',
					'host' => 'Inheritance test template'
				]
			],
			[
				['type' => 'Zabbix agent', 'value_type' => 'Log', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'Zabbix agent', 'value_type' => 'Text', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'Zabbix agent (active)', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'Simple check', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'SNMPv1 agent', 'host' => 'Inheritance test template']
			],
			[
				[
					'type' => 'SNMPv1 agent',
					'value_type' => 'Numeric (float)',
					'host' => 'Inheritance test template'
				]
			],
			[
				['type' => 'SNMPv2 agent', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'SNMPv3 agent', 'host' => 'Inheritance test template']
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Hexadecimal',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Octal',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Numeric (float)',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Character',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'value_type' => 'Text',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authNoPriv',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authPriv',
					'host' => 'Inheritance test template'
				]
			],
			[
				['type' => 'SNMP trap', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'Zabbix internal', 'host' => 'Inheritance test template']
			],
			[
				[
					'type' => 'Zabbix internal',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'Zabbix internal',
					'host' => 'Inheritance test template',
					'key' => 'test-inheritance-item1'
				]
			],
			[
				['type' => 'Zabbix trapper', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'Zabbix aggregate', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'External check', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'Database monitor', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'IPMI agent', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'SSH agent', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'SSH agent', 'authtype' => 'Public key', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'SSH agent', 'authtype' => 'Password', 'host' => 'Inheritance test template']
			],
			[
				[
					'type' => 'SSH agent',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean',
					'host' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'SSH agent',
					'authtype' => 'Password',
					'value_type' => 'Character',
					'host' => 'Inheritance test template'
				]
			],
			[
				['type' => 'TELNET agent', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'JMX agent', 'host' => 'Inheritance test template']
			],
			[
				['type' => 'Calculated', 'host' => 'Inheritance test template']
			]
		];
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormItem_CheckLayout($data) {
		$dbResult = DBselect('SELECT hostid,status FROM hosts WHERE host='.zbx_dbstr($data['host']));
		$dbRow = DBfetch($dbResult);

		$this->assertNotEquals($dbRow, null);

		$hostid = $dbRow['hostid'];
		$status = $dbRow['status'];

		if (isset($data['key'])) {
			$dbResult = DBselect(
				'SELECT itemid,templateid'.
				' FROM items'.
				' WHERE hostid='.$hostid.
					' AND key_='.zbx_dbstr($data['key'])
			);
			$dbRow = DBfetch($dbResult);

			$this->assertNotEquals($dbRow, null);

			$itemid = $dbRow['itemid'];
			if (0 != $dbRow['templateid'])
				$templateid = $dbRow['templateid'];
		}

		$this->zbxTestLogin(
			'items.php?form='.(isset($itemid) ? 'update' : 'Create+item').
			'&hostid='.$hostid.(isset($itemid) ? '&itemid='.$itemid : '')
		);

		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
		$this->zbxTestTextPresent('Item');

		if (isset($templateid)) {
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
		if (isset($templateid)) {
			$this->assertAttribute("//input[@id='name']/@readonly", 'readonly');
		}

		$this->zbxTestTextPresent('Type');
		if (!isset($templateid)) {
			$this->assertVisible('type');
			$this->zbxTestDropdownHasOptions('type', [
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
			]);
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
		if (!isset($templateid)) {
			$this->assertElementPresent('keyButton');
		}
		else {
			$this->assertAttribute("//input[@id='key']/@readonly", 'readonly');
		}

		if ($type == 'Database monitor' && !isset($itemid)) {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "db.odbc.select[<unique short description>,<dsn>]");
		}

		if ($type == 'SSH agent' && !isset($itemid)) {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "ssh.run[<unique short description>,<ip>,<port>,<encoding>]");
		}

		if ($type == 'TELNET agent' && !isset($itemid)) {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "telnet.run[<unique short description>,<ip>,<port>,<encoding>]");
		}

		if ($type == 'JMX agent' && !isset($itemid)) {
			$keyValue = $this->getValue('key');
			$this->assertEquals($keyValue, "jmx[<object name>,<attribute name>]");
		}

		if ($type == 'SNMPv3 agent') {
			if (isset($data['snmpv3_securitylevel'])) {
				$this->zbxTestDropdownSelect('snmpv3_securitylevel', $data['snmpv3_securitylevel']);
			}
			$snmpv3_securitylevel = $this->getSelectedLabel('snmpv3_securitylevel');
		}

		if (isset($templateid)) {
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
			if (isset($templateid)) {
				$data_type = $this->getValue('data_type_name');
			}
			else {
				$data_type = $this->getSelectedLabel('data_type');
			}
		}

		if ($type == 'SSH agent') {
			if (isset($data['authtype'])) {
				$this->zbxTestDropdownSelect('authtype', $data['authtype']);
			}
			$authtype = $this->getSelectedLabel('authtype');
		}

		if ($type == 'Database monitor') {
			$this->zbxTestTextPresent('SQL query');
			$this->assertVisible('params_ap');
			$this->assertAttribute("//textarea[@id='params_ap']/@rows", 7);
			$addParams = $this->getValue('params_ap');
			$this->assertEquals($addParams, "");
		}
		else {
			$this->zbxTestTextNotPresent('Additional parameters');
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

		if ($status != HOST_STATUS_TEMPLATE) {
			$interfaceType = itemTypeInterface($this->getValue('type'));
			switch ($interfaceType) {
				case INTERFACE_TYPE_AGENT :
				case INTERFACE_TYPE_SNMP :
				case INTERFACE_TYPE_JMX :
				case INTERFACE_TYPE_IPMI :
				case INTERFACE_TYPE_ANY :
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
					$this->zbxTestTextNotPresent(['Host interface', 'No interface found']);
					$this->assertNotVisible('interfaceid');
					break;
			}
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
			$this->zbxTestDropdownHasOptions('authtype', ['Password', 'Public key']);
		}
		else {
			$this->zbxTestTextNotPresent('Authentication method');
			$this->assertNotVisible('authtype');
		}

		if ($type == 'SSH agent' || $type == 'TELNET agent' || $type == 'JMX agent' || $type == 'Simple check' || $type == 'Database monitor') {
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
			$this->zbxTestTextNotPresent(['User name', 'Password', 'Key passphrase']);
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
			if (!isset($itemid)) {
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
			if (!isset($itemid)) {
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
			$this->zbxTestDropdownHasOptions('snmpv3_securitylevel', ['noAuthNoPriv', 'authNoPriv', 'authPriv']);
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
				if (!isset($itemid)) {
					$this->assertAttribute("//input[@id='delay']/@value", 30);
				}
				break;
			default:
				$this->zbxTestTextNotPresent('Update interval (in sec)');
				$this->assertNotVisible('delay');
		}

		$this->zbxTestTextPresent('Type of information');
		if (!isset($templateid)) {
			$this->assertVisible('value_type');
			$this->zbxTestDropdownHasOptions('value_type', [
				'Numeric (unsigned)',
				'Numeric (float)',
				'Character',
				'Log',
				'Text'
			]);

			$this->assertElementPresent("//*[@id='value_type']/option[text()='Numeric (unsigned)']");
			$this->isEditable("//*[@id='value_type']/option[text()='Numeric (unsigned)']");
			$this->isEditable("//*[@id='value_type']/option[text()='Numeric (float)']");

			if ($type == 'Zabbix aggregate' || $type == 'Calculated') {
				$this->assertAttribute("//*[@id='value_type']/option[text()='Character']/@disabled", 'disabled');
				$this->assertAttribute("//*[@id='value_type']/option[text()='Log']/@disabled", 'disabled');
				$this->assertAttribute("//*[@id='value_type']/option[text()='Text']/@disabled", 'disabled');
			}
			else {
				$this->isEditable("//*[@id='value_type']/option[text()='Character']");
				$this->isEditable("//*[@id='value_type']/option[text()='Log']");
				$this->isEditable("//*[@id='value_type']/option[text()='Text']");
			}
		}
		else {
			$this->assertVisible('value_type_name');
			$this->assertAttribute("//input[@id='value_type_name']/@maxlength", 255);
			$this->assertAttribute("//input[@id='value_type_name']/@size", 50);
			$this->assertAttribute("//input[@id='value_type_name']/@readonly", 'readonly');
		}

		if ($value_type == 'Numeric (unsigned)') {
			$this->zbxTestTextPresent('Data type');
			if (!isset($templateid)) {
				$this->assertVisible('data_type');
				$this->zbxTestDropdownHasOptions('data_type', ['Boolean', 'Octal', 'Decimal', 'Hexadecimal']);
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
			else {
				$this->assertVisible('data_type_name');
				$this->assertAttribute("//input[@id='data_type_name']/@maxlength", 255);
				$this->assertAttribute("//input[@id='data_type_name']/@size", 25);
				$this->assertAttribute("//input[@id='data_type_name']/@readonly", 'readonly');
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
			if(isset($templateid)) {
				$this->assertAttribute("//input[@id='units']/@readonly", 'readonly');
			}

			$this->zbxTestTextPresent('Use custom multiplier');
			$this->assertElementPresent('multiplier');
			$this->assertElementPresent("//input[@type='checkbox' and @id='multiplier']");
			if (isset($templateid)) {
				$this->assertElementPresent("//input[@type='checkbox' and @id='multiplier' and @disabled = 'disabled']");
			}

			$this->assertVisible('formula');
			$this->assertAttribute("//input[@id='formula']/@maxlength", 255);
			$this->assertAttribute("//input[@id='formula']/@size", 25);
			if (!isset($itemid)) {
				$this->assertAttribute("//input[@id='formula']/@value", 1);
			}
			if (!isset($templateid)) {
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
				$this->zbxTestTextPresent(['Flexible intervals', 'Interval', 'Period', 'No flexible intervals defined.']);
				$this->assertVisible('delayFlexTable');

				$this->zbxTestTextPresent('New flexible interval', 'Update interval (in sec)', 'Period');
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
				$this->zbxTestTextNotPresent(['Flexible intervals', 'Interval', 'Period', 'No flexible intervals defined.']);
				$this->assertNotVisible('delayFlexTable');

				$this->zbxTestTextNotPresent('New flexible interval', 'Update interval (in sec)', 'Period');
				$this->assertNotVisible('new_delay_flex_period');
				$this->assertNotVisible('new_delay_flex_delay');
				$this->assertNotVisible('add_delay_flex');
		}

		$this->zbxTestTextPresent('History storage period (in days)');
		$this->assertVisible('history');
		$this->assertAttribute("//input[@id='history']/@maxlength", 8);
		if (!isset($itemid)) {
			$this->assertAttribute("//input[@id='history']/@value", 90);
		}
		$this->assertAttribute("//input[@id='history']/@size", 8);

		if ($value_type == 'Numeric (unsigned)' || $value_type == 'Numeric (float)') {
			$this->zbxTestTextPresent('Trend storage period (in days)');
			$this->assertVisible('trends');
			$this->assertAttribute("//input[@id='trends']/@maxlength", 8);
			if (!isset($itemid)) {
				$this->assertAttribute("//input[@id='trends']/@value", 365);
			}
			$this->assertAttribute("//input[@id='trends']/@size", 8);
		}
		else {
			$this->zbxTestTextNotPresent('Trend storage period (in days)');
			$this->assertNotVisible('trends');
		}

		if ($value_type == 'Numeric (float)' || ($value_type == 'Numeric (unsigned)' && $data_type != 'Boolean')) {
			$this->zbxTestTextPresent('Store value');
			if (!isset($templateid)) {
				$this->assertVisible('delta');
				$this->zbxTestDropdownHasOptions('delta', ['As is', 'Delta (speed per second)', 'Delta (simple change)']);
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
			$this->zbxTestTextPresent(['Show value', 'show value mappings']);
			if (!isset($templateid)) {
				$this->assertVisible('valuemapid');
				$this->assertAttribute("//*[@id='valuemapid']/option[text()='As is']/@selected", 'selected');

				$options = ['As is'];
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
			$this->zbxTestTextNotPresent(['Show value', 'show value mappings']);
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

		$options = ['-None-'];
		$result = DBselect('SELECT name FROM applications WHERE hostid='.$hostid);
		while ($row = DBfetch($result)) {
			$options[] = $row['name'];
		}
		$this->zbxTestDropdownHasOptions('applications_', $options);

		if ($value_type != 'Log') {
			$this->zbxTestTextPresent('Populates host inventory field');
			$this->assertVisible('inventory_link');
			$this->zbxTestDropdownHasOptions('inventory_link', [
				'-None-',
				'Type',
				'Type (Full details)',
				'Name',
				'Alias',
				'OS',
				'OS (Full details)',
				'OS (Short)',
				'Serial number A',
				'Serial number B',
				'Tag',
				'Asset tag',
				'MAC address A',
				'MAC address B',
				'Hardware',
				'Hardware (Full details)',
				'Software',
				'Software (Full details)',
				'Software application A',
				'Software application B',
				'Software application C',
				'Software application D',
				'Software application E',
				'Contact',
				'Location',
				'Location latitude',
				'Location longitude',
				'Notes',
				'Chassis',
				'Model',
				'HW architecture',
				'Vendor',
				'Contract number',
				'Installer name',
				'Deployment status',
				'URL A',
				'URL B',
				'URL C',
				'Host networks',
				'Host subnet mask',
				'Host router',
				'OOB IP address',
				'OOB subnet mask',
				'OOB router',
				'Date HW purchased',
				'Date HW installed',
				'Date HW maintenance expires',
				'Date HW decommissioned',
				'Site address A',
				'Site address B',
				'Site address C',
				'Site city',
				'Site state / province',
				'Site country',
				'Site ZIP / postal',
				'Site rack location',
				'Site notes',
				'Primary POC name',
				'Primary POC email',
				'Primary POC phone A',
				'Primary POC phone B',
				'Primary POC cell',
				'Primary POC screen name',
				'Primary POC notes',
				'Secondary POC name',
				'Secondary POC email',
				'Secondary POC phone A',
				'Secondary POC phone B',
				'Secondary POC cell',
				'Secondary POC screen name',
				'Secondary POC notes'
			]);
			$this->assertAttribute("//*[@id='inventory_link']/option[text()='-None-']/@selected", 'selected');
		}

		$this->zbxTestTextPresent('Description');
		$this->assertVisible('description');
		$this->assertAttribute("//textarea[@id='description']/@rows", 7);

		$this->zbxTestTextPresent('Enabled');
		$this->assertVisible('status');
		$this->assertAttribute("//*[@id='status']/@checked", 'checked');

		$this->assertVisible('update');
		$this->assertAttribute("//input[@id='update']/@value", 'Update');

		$this->assertVisible('cancel');
		$this->assertAttribute("//input[@id='cancel']/@value", 'Cancel');

		if (isset($itemid)) {
			$this->assertVisible('clone');
			$this->assertAttribute("//input[@id='clone']/@value", 'Clone');
		}
		else {
			$this->assertElementNotPresent('clone');
		}

		if (isset($itemid) && $status != HOST_STATUS_TEMPLATE) {
			$this->assertVisible('del_history');
			$this->assertAttribute("//input[@id='del_history']/@value", 'Clear history and trends');
		}
		else {
			$this->assertElementNotPresent('del_history');
		}

		if ((isset($itemid) && !isset($templateid))) {
			$this->assertVisible('delete');
			$this->assertAttribute("//input[@id='delete']/@value", 'Delete');
		}
		else {
			$this->assertElementNotPresent('delete');
		}
	}

	// Returns update data
	public static function update() {
		return DBdata("SELECT * FROM items WHERE hostid = 40001 AND key_ LIKE 'test-item-form%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testFormItem_SimpleUpdate($data) {
		$name = $data['name'];

		$sqlItems = "SELECT * FROM items ORDER BY itemid";
		$oldHashItems = DBhash($sqlItems);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Items']");
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestTextPresent('Item updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestTextPresent('ITEMS');

		$this->assertEquals($oldHashItems, DBhash($sqlItems));
	}

	// Returns create data
	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Checksum of $1',
					'key' => 'vfs.file.cksum[/sbin/shutdown]',
					'dbName' => 'Checksum of /sbin/shutdown',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			// Duplicate item
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Checksum of $1',
					'key' => 'vfs.file.cksum[/sbin/shutdown]',
					'errors' => [
						'ERROR: Cannot add item',
						'Item with key "vfs.file.cksum[/sbin/shutdown]" already exists on'
					]
				]
			],
			// Item name is missing
			[
				[
					'expected' => TEST_BAD,
					'key' =>'item-name-missing',
					'errors' => [
						'Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.'
					]
				]
			],
			// Item key is missing
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item name',
					'errors' => [
						'Page received incorrect data',
						'Incorrect value for field "Key": cannot be empty.'
					]
				]
			],
			// Empty formula
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => ' ',
					'formulaValue' => '',
					'errors' => [
						'ERROR: Page received incorrect data',
						'Value "" of "Custom multiplier" has incorrect decimal format.'
					]
				]
			],
			// Incorrect formula
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => ' value ',
					'formulaValue' => 'value',
					'errors' => [
						'ERROR: Page received incorrect data',
						'Value "value" of "Custom multiplier" has incorrect decimal format.'
					]
				]
			],
			// Incorrect formula
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => '321abc',
					'formulaValue' => '321abc',
					'errors' => [
						'ERROR: Page received incorrect data',
						'Value "321abc" of "Custom multiplier" has incorrect decimal format.'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item formula1',
					'key' => 'item-formula-test',
					'formula' => '5',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			// Empty timedelay
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item delay',
					'key' => 'item-delay-test',
					'delay' => 0,
					'errors' => [
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Incorrect timedelay
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item delay',
					'key' => 'item-delay-test',
					'delay' => '-30',
					'errors' => [
						'ERROR: Page received incorrect data',
						'Incorrect value "-30" for "Update interval (in sec)" field: must be between 0 and 86400.'
					]
				]
			],
			// Incorrect timedelay
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item delay',
					'key' => 'item-delay-test',
					'delay' => 86401,
					'errors' => [
						'ERROR: Page received incorrect data',
						'Incorrect value "86401" for "Update interval (in sec)" field: must be between 0 and 86400.'
					]
				]
			],
			// Empty time flex period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
					'flexPeriod' => [
						['flexDelay' => '', 'flexTime' => '', 'instantCheck' => true]
					],
					'errors' => [
						'ERROR: Page received incorrect data',
						'Incorrect value for field "New flexible interval": cannot be empty.'
					]
				]
			],
			// Incorrect flex period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
					'flexPeriod' => [
						['flexTime' => '1-11,00:00-24:00', 'instantCheck' => true]
					],
					'errors' => [
						'ERROR: Invalid time period',
						'Incorrect time period "1-11,00:00-24:00".'
					]
				]
			],
			// Incorrect flex period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
					'flexPeriod' => [
						['flexTime' => '1-7,00:00-25:00', 'instantCheck' => true]
					],
					'errors' => [
						'ERROR: Invalid time period',
						'Incorrect time period "1-7,00:00-25:00".'
					]
				]
			],
			// Incorrect flex period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
					'flexPeriod' => [
						['flexTime' => '1-7,24:00-00:00', 'instantCheck' => true]
					],
					'errors' => [
						'ERROR: Invalid time period',
						'Incorrect time period "1-7,24:00-00:00" start time must be less than end time.'
					]
				]
			],
			// Incorrect flex period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
					'flexPeriod' => [
						['flexTime' => '1,00:00-24:00;2,00:00-24:00', 'instantCheck' => true]
					],
					'errors' => [
						'ERROR: Invalid time period',
						'Incorrect time period "1,00:00-24:00;2,00:00-24:00".'
					]
				]
			],
			// Multiple flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
					'flexPeriod' => [
						['flexTime' => '1,00:00-24:00'],
						['flexTime' => '2,00:00-24:00'],
						['flexTime' => '1,00:00-24:00'],
						['flexTime' => '2,00:00-24:00']
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '2,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '3,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '4,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '5,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '6,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '7,00:00-24:00']
					],
					'errors' => [
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay1',
					'flexPeriod' => [
						['flexTime' => '1,00:00-24:00'],
						['flexTime' => '2,00:00-24:00'],
						['flexTime' => '3,00:00-24:00'],
						['flexTime' => '4,00:00-24:00'],
						['flexTime' => '5,00:00-24:00'],
						['flexTime' => '6,00:00-24:00'],
						['flexTime' => '7,00:00-24:00']
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'delay' => 0,
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '2,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '3,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '4,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '5,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '6,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '7,00:00-24:00']
					],
					'errors' => [
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex combined with flex periods',
					'key' => 'item-flex-delay2',
					'delay' => 0,
					'flexPeriod' => [
						['flexTime' => '1-5,00:00-24:00'],
						['flexTime' => '6-7,00:00-24:00']
					],
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00']
					],
					'errors' => [
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay3',
					'flexPeriod' => [
						['flexTime' => '1-5,00:00-24:00'],
						['flexTime' => '6-7,00:00-24:00']
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay4',
					'delay' => 0,
					'flexPeriod' => [
						['flexTime' => '1-7,00:00-24:00']
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00']
					],
					'errors' => [
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay5',
					'flexPeriod' => [
						['flexTime' => '1-7,00:00-24:00']
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00'],
						['flexTime' => '1-5,00:00-24:00'],
						['flexTime' => '6-7,00:00-24:00']
					],
					'errors' => [
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => [
						['flexTime' => '1-5,00:00-24:00'],
						['flexTime' => '6-7,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00']
					],
					'errors' => [
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => [
						['flexTime' => '1-7,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00']
					],
					'errors' => [
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00']
					],
					'errors' => [
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay6',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1,00:00-24:00', 'remove' => true],
						['flexDelay' => 0, 'flexTime' => '2,00:00-24:00', 'remove' => true],
						['flexDelay' => 0, 'flexTime' => '3,00:00-24:00', 'remove' => true],
						['flexDelay' => 0, 'flexTime' => '4,00:00-24:00', 'remove' => true],
						['flexDelay' => 0, 'flexTime' => '5,00:00-24:00', 'remove' => true],
						['flexDelay' => 0, 'flexTime' => '6,00:00-24:00', 'remove' => true],
						['flexDelay' => 0, 'flexTime' => '7,00:00-24:00', 'remove' => true],
						['flexTime' => '1,00:00-24:00'],
						['flexTime' => '2,00:00-24:00'],
						['flexTime' => '3,00:00-24:00'],
						['flexTime' => '4,00:00-24:00'],
						['flexTime' => '5,00:00-24:00'],
						['flexTime' => '6,00:00-24:00'],
						['flexTime' => '7,00:00-24:00']
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex',
					'key' => 'item-flex-delay7',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00', 'remove' => true],
						['flexTime' => '1-7,00:00-24:00']
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex Check',
					'key' => 'item-flex-delay8',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00', 'remove' => true],
						['flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00', 'remove' => true],
						['flexTime' => '1-5,00:00-24:00'],
						['flexTime' => '6-7,00:00-24:00']
					],
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			// Maximum flexfields allowed reached- error
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex-maximum entries',
					'key' => 'item-flex-maximum',
					'flexPeriod' => [
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00', 'instantCheck' => true, 'maximumItems' => true]
					],
					'errors' => [
						'Maximum number of flexible intervals added'
					]
				]
			],
			// Maximum flexfields allowed reached- save OK
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex-maximum save OK',
					'key' => 'item-flex-maximum-save',
					'flexPeriod' => [
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00', 'maximumItems' => true]
					],
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			// Maximum flexfields allowed reached- remove one item
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item flex-maximum with remove',
					'key' => 'item-flex-maximum-remove',
					'flexPeriod' => [
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00'],
						['flexTime' => '1-7,00:00-24:00', 'instantCheck' => true, 'maximumItems' => true, 'remove' => true],
						['flexTime' => '1-7,00:00-24:00', 'instantCheck' => true, 'maximumItems' => true]
					],
					'errors' => [
						'Maximum number of flexible intervals added'
					]
				]
			],
			// Flexfields with negative number in flexdelay
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex-negative flexdelay',
					'key' => 'item-flex-negative-flexdelay',
					'flexPeriod' => [
						['flexDelay' => '-50', 'flexTime' => '1-7,00:00-24:00']
					]
				]
			],
			// Flexfields with symbols in flexdelay
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex-symbols in flexdelay',
					'key' => 'item-flex-symbols-flexdelay',
					'flexPeriod' => [
						['flexDelay' => '50abc', 'flexTime' => '1-7,00:00-24:00']
					]
				]
			],
			// History
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item history',
					'key' => 'item-history-empty',
					'history' => ''
				]
			],
			// History
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item history',
					'key' => 'item-history-test',
					'history' => 65536,
					'errors' => [
						'ERROR: Page received incorrect data',
						'Incorrect value "65536" for "History storage period" field: must be between 0 and 65535.'
					]
				]
			],
			// History
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item history',
					'key' => 'item-history-test',
					'history' => '-1',
					'errors' => [
							'ERROR: Page received incorrect data',
							'Incorrect value "-1" for "History storage period" field: must be between 0 and 65535.'
					]
				]
			],
			// History
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item history',
					'key' => 'item-history-test',
					'history' => 'days'
				]
			],
			// Trends
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item trends',
					'key' => 'item-trends-empty',
					'trends' => '',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			// Trends
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item trends',
					'key' => 'item-trends-test',
					'trends' => '-1',
					'errors' => [
							'ERROR: Page received incorrect data',
							'Incorrect value "-1" for "Trend storage period" field: must be between 0 and 65535.'
					]
				]
			],
			// Trends
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item trends',
					'key' => 'item-trends-test',
					'trends' => 65536,
					'errors' => [
							'ERROR: Page received incorrect data',
							'Incorrect value "65536" for "Trend storage period" field: must be between 0 and 65535.'
					]
				]
			],
			// Trends
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item trends Check',
					'key' => 'item-trends-test',
					'trends' => 'trends',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => '!@#$%^&*()_+-=[]{};:"|,./<>?',
					'key' => 'item-symbols-test',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			// List of all item types
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Zabbix agent',
					'name' => 'Zabbix agent',
					'key' => 'item-zabbix-agent',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Zabbix agent (active)',
					'name' => 'Zabbix agent (active)',
					'key' => 'item-zabbix-agent-active',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Simple check',
					'name' => 'Simple check',
					'key' => 'item-simple-check',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'SNMPv1 agent',
					'name' => 'SNMPv1 agent',
					'key' => 'item-snmpv1-agent',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'SNMPv2 agent',
					'name' => 'SNMPv2 agent',
					'key' => 'item-snmpv2-agent',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'SNMPv3 agent',
					'name' => 'SNMPv3 agent',
					'key' => 'item-snmpv3-agent',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'SNMP trap',
					'name' => 'SNMP trap',
					'key' => 'snmptrap.fallback',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Zabbix internal',
					'name' => 'Zabbix internal',
					'key' => 'item-zabbix-internal',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Zabbix trapper',
					'name' => 'Zabbix trapper',
					'key' => 'item-zabbix-trapper',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Zabbix aggregate',
					'name' => 'Zabbix aggregate',
					'key' => 'grpmax[Zabbix servers group,some-item-key,last,0]',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Zabbix aggregate',
					'name' => 'Zabbix aggregate',
					'key' => 'item-zabbix-aggregate',
					'errors' => [
						'ERROR: Cannot add item',
						'Key "item-zabbix-aggregate" does not match'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'External check',
					'name' => 'External check',
					'key' => 'item-external-check',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Database monitor',
					'name' => 'Database monitor',
					'key' => 'item-database-monitor',
					'params_ap' => 'query',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'IPMI agent',
					'name' => 'IPMI agent',
					'key' => 'item-ipmi-agent',
					'ipmi_sensor' => 'ipmi_sensor',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'SSH agent',
					'name' => 'SSH agent',
					'key' => 'item-ssh-agent',
					'username' => 'zabbix',
					'params_es' => 'executed script',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'TELNET agent',
					'name' => 'TELNET agent',
					'key' => 'item-telnet-agent',
					'username' => 'zabbix',
					'params_es' => 'executed script',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'IPMI agent',
					'name' => 'IPMI agent error',
					'key' => 'item-ipmi-agent-error',
					'errors' => [
							'ERROR: Page received incorrect data',
							'Incorrect value for field "IPMI sensor": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'IPMI agent',
					'name' => 'IPMI agent with spaces',
					'key' => 'item-ipmi-agent-spaces',
					'ipmi_sensor' => 'ipmi_sensor',
					'ipmiSpaces' => true,
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'SSH agent',
					'name' => 'SSH agent error',
					'key' => 'item-ssh-agent-error',
					'errors' => [
							'ERROR: Page received incorrect data',
							'Incorrect value for field "User name": cannot be empty.',
							'Incorrect value for field "Executed script": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'TELNET agent',
					'name' => 'TELNET agent error',
					'key' => 'item-telnet-agent-error',
					'errors' => [
							'ERROR: Page received incorrect data',
							'Incorrect value for field "User name": cannot be empty.',
							'Incorrect value for field "Executed script": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'JMX agent',
					'name' => 'JMX agent',
					'key' => 'item-jmx-agent',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Calculated',
					'name' => 'Calculated',
					'key' => 'item-calculated',
					'params_f' => 'formula',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Calculated',
					'name' => 'Calculated',
					'key' => 'item-calculated',
					'errors' => [
							'ERROR: Page received incorrect data',
							'Incorrect value for field "Formula": cannot be empty.'
					]
				]
			],
			// Default
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Database monitor',
					'name' => 'Database monitor',
					'params_ap' => 'query',
					'errors' => [
							'ERROR: Cannot add item',
							'Check the key, please. Default example was passed.'
					]
				]
			],
			// Default
			[
				[
					'expected' => TEST_BAD,
					'type' => 'SSH agent',
					'name' => 'SSH agent',
					'username' => 'zabbix',
					'params_es' => 'script to be executed',
					'errors' => [
							'ERROR: Cannot add item',
							'Check the key, please. Default example was passed.'
					]
				]
			],
			// Default
			[
				[
					'expected' => TEST_BAD,
					'type' => 'TELNET agent',
					'name' => 'TELNET agent',
					'username' => 'zabbix',
					'params_es' => 'script to be executed',
					'errors' => [
							'ERROR: Cannot add item',
							'Check the key, please. Default example was passed.'
					]
				]
			],
			// Default
			[
				[
					'expected' => TEST_BAD,
					'type' => 'JMX agent',
					'name' => 'JMX agent',
					'username' => 'zabbix',
					'params_es' => 'script to be executed',
					'errors' => [
							'ERROR: Cannot add item',
							'Check the key, please. Default example was passed.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormItem_SimpleCreate($data) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Items']");

		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');

		$this->zbxTestClickWait('form');
		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
		$this->zbxTestTextPresent('Item');

		if (isset($data['type'])) {
			$this->zbxTestDropdownSelect('type', $data['type']);
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

		if (isset($data['params_f'])) {
			$this->input_type('params_f', $data['params_f']);
		}

		if (isset($data['params_es'])) {
			$this->input_type('params_es', $data['params_es']);
		}

		if (isset($data['params_ap'])) {
			$this->input_type('params_ap', $data['params_ap']);
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

		$value_type = $this->getSelectedLabel('value_type');
		$data_type = $this->getSelectedLabel('data_type');

		if ($itemFlexFlag == true) {
			$this->zbxTestClickWait('add');
			$expected = $data['expected'];
			switch ($expected) {
				case TEST_GOOD:
					$this->zbxTestTextPresent('Item added');
					$this->zbxTestCheckTitle('Configuration of items');
					$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
					break;

				case TEST_BAD:
					$this->zbxTestCheckTitle('Configuration of items');
					$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
					foreach ($data['errors'] as $msg) {
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent(['Host', 'Name', 'Key']);
					if (isset($data['formula'])) {
						$formulaValue = $this->getValue('formula');
						$this->assertEquals($data['formulaValue'], $formulaValue);
					}
					break;
				}
			}

		if (isset($data['dbCheck'])) {
			$result = DBselect("SELECT name, key_, value_type, data_type FROM items where key_ = '".$key."'");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $name);
				$this->assertEquals($row['key_'], $key);

				switch($row['value_type'])	{
					case ITEM_VALUE_TYPE_FLOAT:
						$value_typeDB = 'Numeric (float)';
						break;
					case ITEM_VALUE_TYPE_STR:
						$value_typeDB = 'Character';
						break;
					case ITEM_VALUE_TYPE_LOG:
						$value_typeDB = 'Log';
						break;
					case ITEM_VALUE_TYPE_UINT64:
						$value_typeDB = 'Numeric (unsigned)';
						break;
					case ITEM_VALUE_TYPE_TEXT:
						$value_typeDB = 'Text';
						break;
				}
				$this->assertEquals($value_typeDB, $value_type);

				switch($row['data_type'])	{
					case ITEM_DATA_TYPE_DECIMAL:
						$data_typeDB = 'Decimal';
						break;
					case ITEM_DATA_TYPE_OCTAL:
						$data_typeDB = 'Octal';
						break;
					case ITEM_DATA_TYPE_HEXADECIMAL:
						$data_typeDB = 'Hexadecimal' ;
						break;
					case ITEM_DATA_TYPE_BOOLEAN:
						$data_typeDB = 'Boolean' ;
						break;
				}
				$this->assertEquals($data_typeDB, $data_type);
			}
		}
		if (isset($data['formCheck'])) {
			if (isset ($data['dbName'])) {
				$dbName = $data['dbName'];
			}
			else {
				$dbName = $name;
			}
			$this->zbxTestClickWait('link='.$dbName);
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
		}

	public function testFormItem_HousekeeperUpdate() {
		$this->zbxTestLogin('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeping');

		$this->zbxTestCheckboxSelect('hk_history_global', false);
		$this->zbxTestCheckboxSelect('hk_trends_global', false);

		$this->zbxTestClickWait('update');

		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Items']");
		$this->zbxTestClickWait('link='.$this->item);

		$this->zbxTestTextNotPresent('Overridden by global housekeeping settings');
		$this->zbxTestTextNotPresent('Overridden by global housekeeping settings');

		$this->zbxTestOpen('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeping');

		$this->zbxTestCheckboxSelect('hk_history_global');
		$this->input_type('hk_history', 99);

		$this->zbxTestCheckboxSelect('hk_trends_global');
		$this->input_type('hk_trends', 455);

		$this->zbxTestClickWait('update');

		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Items']");
		$this->zbxTestClickWait('link='.$this->item);

		$this->zbxTestTextPresent('Overridden by global housekeeping settings (99 days)');
		$this->zbxTestTextPresent('Overridden by global housekeeping settings (455 days)');

		$this->zbxTestOpen('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeping');

		$this->input_type('hk_history', 90);
		$this->zbxTestCheckboxSelect('hk_history_global', false);

		$this->input_type('hk_trends', 365);
		$this->zbxTestCheckboxSelect('hk_trends_global', false);

		$this->zbxTestClickWait('update');

		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Items']");
		$this->zbxTestClickWait('link='.$this->item);

		$this->zbxTestTextNotPresent('Overridden by global housekeeping settings (99 days)');
		$this->zbxTestTextNotPresent('Overridden by global housekeeping settings (455 days)');
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormItem_Teardown() {
		DBrestore_tables('items');
	}
}
