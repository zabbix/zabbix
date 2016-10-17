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
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Hexadecimal',
					'host' => 'Inheritance test template'
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
		$this->zbxTestCheckHeader('Items');

		if (isset($templateid)) {
			$this->zbxTestTextPresent('Parent items');
			if (isset($data['hostTemplate'])) {
				$this->zbxTestAssertElementPresentXpath("//a[text()='".$data['hostTemplate']."']");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Parent items');
		}

		$this->zbxTestTextPresent('Name');
		$this->zbxTestAssertVisibleId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='name']", 'size', 20);
		$this->zbxTestAssertAttribute("//input[@id='name']", 'autofocus');
		if (isset($templateid)) {
			$this->zbxTestAssertAttribute("//input[@id='name']", 'readonly');
		}

		$this->zbxTestTextPresent('Type');
		if (!isset($templateid)) {
			$this->zbxTestAssertVisibleId('type');
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
				$type = $data['type'];
			}
			else {
				$type = $this->zbxTestGetSelectedLabel('type');
			}
		}
		else {
			$this->zbxTestAssertVisibleId('typename');
			$this->zbxTestAssertAttribute("//input[@id='typename']", 'maxlength', 255);
			$this->zbxTestAssertAttribute("//input[@id='typename']", 'size', 20);
			$this->zbxTestAssertAttribute("//input[@id='typename']", 'readonly');

			$type = $this->zbxTestGetValue("//input[@id='typename']");
		}

		$this->zbxTestTextPresent('Key');
		$this->zbxTestAssertVisibleId('key');
		$this->zbxTestAssertAttribute("//input[@id='key']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='key']", 'size', 20);
		if (!isset($templateid)) {
			$this->zbxTestAssertElementPresentId('keyButton');
		}
		else {
			$this->zbxTestAssertAttribute("//input[@id='key']", 'readonly');
		}

		if ($type == 'Database monitor' && !isset($itemid)) {
			$this->zbxTestAssertElementValue('key', 'db.odbc.select[<unique short description>,<dsn>]');
		}

		if ($type == 'SSH agent' && !isset($itemid)) {
			$this->zbxTestAssertElementValue('key', 'ssh.run[<unique short description>,<ip>,<port>,<encoding>]');
		}

		if ($type == 'TELNET agent' && !isset($itemid)) {
			$this->zbxTestAssertElementValue('key', 'telnet.run[<unique short description>,<ip>,<port>,<encoding>]');
		}

		if ($type == 'JMX agent' && !isset($itemid)) {
			$this->zbxTestAssertElementValue('key', 'jmx[<object name>,<attribute name>]');
		}

		if ($type == 'SNMPv3 agent') {
			if (isset($data['snmpv3_securitylevel'])) {
				$this->zbxTestDropdownSelect('snmpv3_securitylevel', $data['snmpv3_securitylevel']);
				$snmpv3_securitylevel = $data['snmpv3_securitylevel'];
			}
			else {
				$snmpv3_securitylevel = $this->zbxTestGetSelectedLabel('snmpv3_securitylevel');
			}
		}

		if (isset($templateid)) {
			$value_type = $this->zbxTestGetValue("//input[@id='value_type_name']");
		}
		elseif (isset($data['value_type'])) {
			$this->zbxTestDropdownSelect('value_type', $data['value_type']);
			$value_type = $data['value_type'];
		}
		else {
			$value_type = $this->zbxTestGetSelectedLabel('value_type');
		}

		if ($value_type == 'Numeric (unsigned)') {
			if (isset($data['data_type'])) {
				$this->zbxTestDropdownSelect('data_type', $data['data_type']);
				$data_type = $data['data_type'];
			}
			elseif (isset($templateid)) {
				$data_type = $this->zbxTestGetValue("//input[@id='data_type_name']");
			}
			else {
				$data_type = $this->zbxTestGetSelectedLabel('data_type');
			}
		}

		if ($type == 'SSH agent') {
			if (isset($data['authtype'])) {
				$this->zbxTestDropdownSelect('authtype', $data['authtype']);
				$authtype = $data['authtype'];
			}
			else {
				$authtype = $this->zbxTestGetSelectedLabel('authtype');
			}
		}

		if ($type == 'Database monitor') {
			$this->zbxTestTextPresent('SQL query');
			$this->zbxTestAssertVisibleId('params_ap');
			$this->zbxTestAssertAttribute("//textarea[@id='params_ap']", 'rows', 7);
			$this->zbxTestAssertElementValue('params_ap', '');
		}
		else {
			$this->zbxTestTextNotPresent('Additional parameters');
			$this->zbxTestAssertNotVisibleId('params_ap');
		}

		if ($type == 'SSH agent' || $type == 'TELNET agent' ) {
			$this->zbxTestTextPresent('Executed script');
			$this->zbxTestAssertVisibleId('params_es');
			$this->zbxTestAssertAttribute("//textarea[@id='params_es']", 'rows', 7);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Executed script');
			$this->zbxTestAssertNotVisibleId('params_es');
		}

		if ($type == 'Calculated') {
			$this->zbxTestTextPresent('Formula');
			$this->zbxTestAssertVisibleId('params_f');
			$this->zbxTestAssertAttribute("//textarea[@id='params_f']", 'rows', 7);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Formula');
			$this->zbxTestAssertNotVisibleId('params_f');
		}

		if ($status != HOST_STATUS_TEMPLATE) {
			$interfaceType = itemTypeInterface($this->zbxTestGetValue('//*[@id="type"]'));
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
							$this->zbxTestAssertElementPresentXpath('//select[@id="interfaceid"]/optgroup/option[text()="'.
							$host_interface['ip'].' : '.$host_interface['port'].'"]');
						}
					}
					else {
						$this->zbxTestTextPresent('No interface found');
						$this->zbxTestAssertNotVisibleId('interfaceid');
					}
					break;
				default:
					$this->zbxTestTextNotVisibleOnPage(['Host interface', 'No interface found']);
					$this->zbxTestAssertNotVisibleId('interfaceid');
					break;
			}
		}

		if ($type == 'IPMI agent') {
			$this->zbxTestTextPresent('IPMI sensor');
			$this->zbxTestAssertVisibleId('ipmi_sensor');
			$this->zbxTestAssertAttribute("//input[@id='ipmi_sensor']", 'maxlength', 128);
			$this->zbxTestAssertAttribute("//input[@id='ipmi_sensor']", 'size', 20);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('IPMI sensor');
			$this->zbxTestAssertNotVisibleId('ipmi_sensor');
		}

		if ($type == 'SSH agent') {
			$this->zbxTestTextPresent('Authentication method');
			$this->zbxTestAssertVisibleId('authtype');
			$this->zbxTestDropdownHasOptions('authtype', ['Password', 'Public key']);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Authentication method');
			$this->zbxTestAssertNotVisibleId('authtype');
		}

		if ($type == 'SSH agent' || $type == 'TELNET agent' || $type == 'JMX agent' || $type == 'Simple check' || $type == 'Database monitor') {
			$this->zbxTestTextPresent('User name');
			$this->zbxTestAssertVisibleId('username');
			$this->zbxTestAssertAttribute("//input[@id='username']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='username']", 'size', 20);

			if (isset($authtype) && $authtype == 'Public key') {
				$this->zbxTestTextPresent('Key passphrase');
			}
			else {
				$this->zbxTestTextPresent('Password');
			}
			$this->zbxTestAssertVisibleId('password');
			$this->zbxTestAssertAttribute("//input[@id='password']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='password']", 'size', 20);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage(['User name', 'Password', 'Key passphrase']);
			$this->zbxTestAssertNotVisibleId('username');
			$this->zbxTestAssertNotVisibleId('password');
		}

		if	(isset($authtype) && $authtype == 'Public key') {
			$this->zbxTestTextPresent('Public key file');
			$this->zbxTestAssertVisibleId('publickey');
			$this->zbxTestAssertAttribute("//input[@id='publickey']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='publickey']", 'size', 20);

			$this->zbxTestTextPresent('Private key file');
			$this->zbxTestAssertVisibleId('privatekey');
			$this->zbxTestAssertAttribute("//input[@id='privatekey']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='privatekey']", 'size', 20);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Public key file');
			$this->zbxTestAssertNotVisibleId('publickey');

			$this->zbxTestTextNotVisibleOnPage('Private key file');
			$this->zbxTestAssertNotVisibleId('publickey');
		}

		if	($type == 'SNMPv1 agent' || $type == 'SNMPv2 agent' || $type == 'SNMPv3 agent') {
			$this->zbxTestTextPresent('SNMP OID');
			$this->zbxTestAssertVisibleId('snmp_oid');
			$this->zbxTestAssertAttribute("//input[@id='snmp_oid']", 'maxlength', 255);
			$this->zbxTestAssertAttribute("//input[@id='snmp_oid']", 'size', 20);
			if (!isset($itemid)) {
				$this->zbxTestAssertElementValue('snmp_oid', 'interfaces.ifTable.ifEntry.ifInOctets.1');
			}

			$this->zbxTestTextPresent('Port');
			$this->zbxTestAssertVisibleId('port');
			$this->zbxTestAssertAttribute("//input[@id='port']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='port']", 'size', 20);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('SNMP OID');
			$this->zbxTestAssertNotVisibleId('snmp_oid');

			$this->zbxTestTextNotVisibleOnPage('Port');
			$this->zbxTestAssertNotVisibleId('port');
		}

		if	($type == 'SNMPv1 agent' || $type == 'SNMPv2 agent') {
			$this->zbxTestTextPresent('SNMP community');
			$this->zbxTestAssertVisibleId('snmp_community');
			$this->zbxTestAssertAttribute("//input[@id='snmp_community']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='snmp_community']", 'size', 20);
			if (!isset($itemid)) {
				$this->zbxTestAssertElementValue('snmp_community', 'public');
			}
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('SNMP community');
			$this->zbxTestAssertNotVisibleId('snmp_community');
		}

		if	($type == 'SNMPv3 agent') {
			$this->zbxTestTextPresent('Security name');
			$this->zbxTestAssertVisibleId('snmpv3_securityname');
			$this->zbxTestAssertAttribute("//input[@id='snmpv3_securityname']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='snmpv3_securityname']", 'size', 20);

			$this->zbxTestTextPresent('Security level');
			$this->zbxTestAssertVisibleId('snmpv3_securitylevel');
			$this->zbxTestDropdownHasOptions('snmpv3_securitylevel', ['noAuthNoPriv', 'authNoPriv', 'authPriv']);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Security name');
			$this->zbxTestAssertNotVisibleId('snmpv3_securityname');

			$this->zbxTestTextNotVisibleOnPage('Security level');
			$this->zbxTestAssertNotVisibleId('snmpv3_securitylevel');
		}

		if (isset($snmpv3_securitylevel) && $snmpv3_securitylevel != 'noAuthNoPriv') {
			$this->zbxTestTextPresent('Authentication protocol');
			$this->zbxTestAssertVisibleId('row_snmpv3_authprotocol');
			$this->zbxTestAssertVisibleXpath("//label[text()='MD5']");
			$this->zbxTestAssertVisibleXpath("//label[text()='SHA']");

			$this->zbxTestTextPresent('Authentication passphrase');
			$this->zbxTestAssertVisibleId('snmpv3_authpassphrase');
			$this->zbxTestAssertAttribute("//input[@id='snmpv3_authpassphrase']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='snmpv3_authpassphrase']", 'size', 20);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Authentication protocol');
			$this->zbxTestAssertNotVisibleId('row_snmpv3_authprotocol');
			$this->zbxTestAssertNotVisibleXpath("//label[text()='MD5']");
			$this->zbxTestAssertNotVisibleXpath("//label[text()='SHA']");

			$this->zbxTestTextNotVisibleOnPage('Authentication passphrase');
			$this->zbxTestAssertNotVisibleId('snmpv3_authpassphrase');
		}

		if (isset($snmpv3_securitylevel) && $snmpv3_securitylevel == 'authPriv') {
			$this->zbxTestTextPresent('Privacy protocol');
			$this->zbxTestAssertVisibleId('row_snmpv3_privprotocol');
			$this->zbxTestAssertVisibleXpath("//label[text()='DES']");
			$this->zbxTestAssertVisibleXpath("//label[text()='AES']");

			$this->zbxTestTextPresent('Privacy passphrase');
			$this->zbxTestAssertVisibleId('snmpv3_privpassphrase');
			$this->zbxTestAssertAttribute("//input[@id='snmpv3_privpassphrase']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='snmpv3_privpassphrase']", 'size', 20);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Privacy protocol');
			$this->zbxTestAssertNotVisibleId('row_snmpv3_privprotocol');
			$this->zbxTestAssertNotVisibleXpath("//label[text()='DES']");
			$this->zbxTestAssertNotVisibleXpath("//label[text()='AES']");

			$this->zbxTestTextNotVisibleOnPage('Privacy passphrase');
			$this->zbxTestAssertNotVisibleId('snmpv3_privpassphrase');
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
				$this->zbxTestAssertVisibleId('delay');
				$this->zbxTestAssertAttribute("//input[@id='delay']", 'maxlength', 5);
				$this->zbxTestAssertAttribute("//input[@id='delay']", 'size', 20);
				if (!isset($itemid)) {
					$this->zbxTestAssertElementValue('delay', 30);
				}
				break;
			default:
				$this->zbxTestTextNotVisibleOnPage('Update interval (in sec)');
				$this->zbxTestAssertNotVisibleId('delay');
		}

		$this->zbxTestTextPresent('Type of information');
		if (!isset($templateid)) {
			$this->zbxTestAssertVisibleId('value_type');
			$this->zbxTestDropdownHasOptions('value_type', [
				'Numeric (unsigned)',
				'Numeric (float)',
				'Character',
				'Log',
				'Text'
			]);

			$this->zbxTestIsEnabled("//*[@id='value_type']/option[text()='Numeric (unsigned)']");
			$this->zbxTestIsEnabled("//*[@id='value_type']/option[text()='Numeric (float)']");

			if ($type == 'Zabbix aggregate' || $type == 'Calculated') {
				$this->zbxTestAssertAttribute("//*[@id='value_type']/option[text()='Character']", 'disabled');
				$this->zbxTestAssertAttribute("//*[@id='value_type']/option[text()='Log']", 'disabled');
				$this->zbxTestAssertAttribute("//*[@id='value_type']/option[text()='Text']", 'disabled');
			}
			else {
				$this->zbxTestIsEnabled("//*[@id='value_type']/option[text()='Character']");
				$this->zbxTestIsEnabled("//*[@id='value_type']/option[text()='Log']");
				$this->zbxTestIsEnabled("//*[@id='value_type']/option[text()='Text']");
			}
		}
		else {
			$this->zbxTestAssertVisibleId('value_type_name');
			$this->zbxTestAssertAttribute("//input[@id='value_type_name']", 'maxlength', 255);
			$this->zbxTestAssertAttribute("//input[@id='value_type_name']", 'size', 20);
			$this->zbxTestAssertAttribute("//input[@id='value_type_name']", 'readonly');
		}

		if ($value_type == 'Numeric (unsigned)') {
			$this->zbxTestTextPresent('Data type');
			if (!isset($templateid)) {
				$this->zbxTestAssertVisibleId('data_type');
				$this->zbxTestDropdownHasOptions('data_type', ['Boolean', 'Octal', 'Decimal', 'Hexadecimal']);
				$this->zbxTestIsEnabled("//*[@id='data_type']/option[text()='Decimal']");

				if ($type == 'Zabbix aggregate' || $type == 'Calculated') {
					$this->zbxTestAssertAttribute("//*[@id='data_type']/option[text()='Boolean']", 'disabled');
					$this->zbxTestAssertAttribute("//*[@id='data_type']/option[text()='Octal']", 'disabled');
					$this->zbxTestAssertAttribute("//*[@id='data_type']/option[text()='Hexadecimal']", 'disabled');
				}
				else {
					$this->zbxTestIsEnabled("//*[@id='data_type']/option[text()='Boolean']");
					$this->zbxTestIsEnabled("//*[@id='data_type']/option[text()='Octal']");
					$this->zbxTestIsEnabled("//*[@id='data_type']/option[text()='Hexadecimal']");
				}
			}
			else {
				$this->zbxTestAssertVisibleId('data_type_name');
				$this->zbxTestAssertAttribute("//input[@id='data_type_name']", 'maxlength', 255);
				$this->zbxTestAssertAttribute("//input[@id='data_type_name']", 'size', 20);
				$this->zbxTestAssertAttribute("//input[@id='data_type_name']", 'readonly');
			}
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Data type');
			$this->zbxTestAssertNotVisibleId('data_type');
		}

		if ($value_type == 'Numeric (float)' || ($value_type == 'Numeric (unsigned)' && $data_type != 'Boolean')) {
			$this->zbxTestTextPresent('Units');
			$this->zbxTestAssertVisibleId('units');
			$this->zbxTestAssertAttribute("//input[@id='units']", 'maxlength', 255);
			$this->zbxTestAssertAttribute("//input[@id='units']", 'size', 20);
			if(isset($templateid)) {
				$this->zbxTestAssertAttribute("//input[@id='units']", 'readonly');
			}

			$this->zbxTestTextPresent('Use custom multiplier');
			$this->zbxTestAssertElementPresentId('multiplier');
			$this->zbxTestAssertVisibleXpath("//input[@type='checkbox' and @id='multiplier']");
			if (isset($templateid)) {
				$this->zbxTestAssertElementPresentXpath("//input[@type='checkbox' and @id='multiplier' and @disabled = 'disabled']");
			}

			$this->zbxTestAssertVisibleId('formula');
			$this->zbxTestAssertAttribute("//input[@id='formula']", 'maxlength', 255);
			$this->zbxTestAssertAttribute("//input[@id='formula']", 'size', 20);
			if (!isset($itemid)) {
				$this->zbxTestAssertElementValue('formula', 1);
			}
			if (!isset($templateid)) {
				$this->zbxTestAssertElementPresentXpath("//input[@id='formula'][@disabled]");
			}
			else {
				$this->zbxTestAssertAttribute("//input[@id='formula']", 'readonly');
			}

		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Units');
			$this->zbxTestAssertNotVisibleId('units');

			$this->zbxTestTextNotVisibleOnPage('Use custom multiplier');
			$this->zbxTestAssertNotVisibleId('multiplier');
			$this->zbxTestAssertNotVisibleId('formula');
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
				$this->zbxTestTextPresent(['Custom intervals', 'Interval',  'Period', 'Action']);
				$this->zbxTestAssertVisibleId('delayFlexTable');

				$this->zbxTestTextPresent(['Flexible', 'Scheduling', 'Update interval (in sec)']);
				$this->zbxTestAssertVisibleId('delay_flex_0_delay');
				$this->zbxTestAssertAttribute("//input[@id='delay_flex_0_delay']", 'maxlength', 5);
				$this->zbxTestAssertAttribute("//input[@id='delay_flex_0_delay']", 'size', 20);
				$this->zbxTestAssertAttribute("//input[@id='delay_flex_0_delay']", 'placeholder', 50);

				$this->zbxTestAssertVisibleId('delay_flex_0_period');
				$this->zbxTestAssertAttribute("//input[@id='delay_flex_0_period']", 'maxlength', 255);
				$this->zbxTestAssertAttribute("//input[@id='delay_flex_0_period']", 'size', 20);
				$this->zbxTestAssertAttribute("//input[@id='delay_flex_0_period']", 'placeholder', '1-7,00:00-24:00');
				$this->zbxTestAssertVisibleId('interval_add');
				break;
			default:
				$this->zbxTestTextNotVisibleOnPage(['Custom intervals', 'Interval',  'Period', 'Action']);
				$this->zbxTestAssertNotVisibleId('delayFlexTable');

				$this->zbxTestTextNotVisibleOnPage(['Flexible', 'Scheduling']);
				$this->zbxTestAssertNotVisibleId('delay_flex_0_delay');
				$this->zbxTestAssertNotVisibleId('delay_flex_0_period');
				$this->zbxTestAssertNotVisibleId('interval_add');
		}

		$this->zbxTestTextPresent('History storage period (in days)');
		$this->zbxTestAssertVisibleId('history');
		$this->zbxTestAssertAttribute("//input[@id='history']", 'maxlength', 8);
		if (!isset($itemid)) {
			$this->zbxTestAssertElementValue('history', 90);
		}
		$this->zbxTestAssertAttribute("//input[@id='history']", 'size', 20);

		if ($value_type == 'Numeric (unsigned)' || $value_type == 'Numeric (float)') {
			$this->zbxTestTextPresent('Trend storage period (in days)');
			$this->zbxTestAssertVisibleId('trends');
			$this->zbxTestAssertAttribute("//input[@id='trends']", 'maxlength', 8);
			if (!isset($itemid)) {
				$this->zbxTestAssertElementValue('trends', 365);
			}
			$this->zbxTestAssertAttribute("//input[@id='trends']", 'size', 20);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Trend storage period (in days)');
			$this->zbxTestAssertNotVisibleId('trends');
		}

		if ($value_type == 'Numeric (float)' || ($value_type == 'Numeric (unsigned)' && $data_type != 'Boolean')) {
			$this->zbxTestTextPresent('Store value');
			if (!isset($templateid)) {
				$this->zbxTestAssertVisibleId('delta');
				$this->zbxTestDropdownHasOptions('delta', ['As is', 'Delta (speed per second)', 'Delta (simple change)']);
				$this->zbxTestDropdownAssertSelected('delta', 'As is');
			}
			else {
				$this->zbxTestAssertVisibleId('delta_name');
				$this->zbxTestAssertAttribute("//input[@id='delta_name']", 'maxlength', 255);
				$this->zbxTestAssertAttribute("//input[@id='delta_name']", 'readonly');
			}
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Store value');
			$this->zbxTestAssertNotVisibleId('delta');
		}

		if ($value_type == 'Numeric (float)' || $value_type == 'Numeric (unsigned)' || $value_type == 'Character') {
			$this->zbxTestTextPresent(['Show value', 'show value mappings']);
			if (!isset($templateid)) {
				$this->zbxTestAssertVisibleId('valuemapid');
				$this->zbxTestDropdownAssertSelected('valuemapid', 'As is');

				$options = ['As is'];
				$result = DBselect('SELECT name FROM valuemaps');
				while ($row = DBfetch($result)) {
					$options[] = $row['name'];
				}
				$this->zbxTestDropdownHasOptions('valuemapid', $options);
			}
			else {
				$this->zbxTestAssertVisibleId('valuemap_name');
				$this->zbxTestAssertAttribute("//input[@id='valuemap_name']", 'maxlength', 255);
				$this->zbxTestAssertAttribute("//input[@id='valuemap_name']", 'size', 20);
				$this->zbxTestAssertAttribute("//input[@id='valuemap_name']", 'readonly');
			}
		}
		else {
			$this->zbxTestTextNotVisibleOnPage(['Show value', 'show value mappings']);
			$this->zbxTestAssertNotVisibleId('valuemapid');
		}

		if ($type == 'Zabbix trapper') {
			$this->zbxTestTextPresent('Allowed hosts');
			$this->zbxTestAssertVisibleId('trapper_hosts');
			$this->zbxTestAssertAttribute("//input[@id='trapper_hosts']", 'maxlength', 255);
			$this->zbxTestAssertAttribute("//input[@id='trapper_hosts']", 'size', 20);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Allowed hosts');
			$this->zbxTestAssertNotVisibleId('trapper_hosts');
		}

		if ($value_type == 'Log') {
			$this->zbxTestTextPresent('Log time format');
			$this->zbxTestAssertVisibleId('logtimefmt');
			$this->zbxTestAssertAttribute("//input[@id='logtimefmt']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='logtimefmt']", 'size', 20);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Log time format');
			$this->zbxTestAssertNotVisibleId('logtimefmt');
		}

		$this->zbxTestTextPresent('New application');
		$this->zbxTestAssertVisibleId('new_application');
		$this->zbxTestAssertAttribute("//input[@id='new_application']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='new_application']", 'size', 20);

		$this->zbxTestTextPresent('Applications');
		$this->zbxTestAssertVisibleId('applications_');
		$this->zbxTestDropdownAssertSelected('applications[]', '-None-');

		$options = ['-None-'];
		$result = DBselect('SELECT name FROM applications WHERE hostid='.$hostid);
		while ($row = DBfetch($result)) {
			$options[] = $row['name'];
		}
		$this->zbxTestDropdownHasOptions('applications_', $options);

		if ($value_type != 'Log') {
			$this->zbxTestTextPresent('Populates host inventory field');
			$this->zbxTestAssertVisibleId('inventory_link');
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
			$this->zbxTestAssertAttribute("//select[@id='inventory_link']//option[text()='-None-']", 'selected');
		}

		$this->zbxTestTextPresent('Description');
		$this->zbxTestAssertVisibleId('description');
		$this->zbxTestAssertAttribute("//textarea[@id='description']", 'rows', 7);

		$this->zbxTestTextPresent('Enabled');
		$this->zbxTestAssertVisibleId('status');
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));

		$this->zbxTestAssertVisibleId('cancel');
		$this->zbxTestAssertElementText("//button[@id='cancel']", 'Cancel');

		if (isset($itemid)) {
			$this->zbxTestAssertVisibleId('clone');
			$this->zbxTestAssertElementValue('clone', 'Clone');
		}
		else {
			$this->zbxTestAssertElementNotPresentId('clone');
		}

		if (isset($itemid) && $status != HOST_STATUS_TEMPLATE) {
			$this->zbxTestAssertVisibleId('del_history');
			$this->zbxTestAssertElementValue('del_history', 'Clear history and trends');
		}
		else {
			$this->zbxTestAssertElementNotPresentId('del_history');
		}

		if ((isset($itemid) && !isset($templateid))) {
			$this->zbxTestAssertVisibleId('delete');
			$this->zbxTestAssertElementValue('delete', 'Delete');
			$this->zbxTestAssertVisibleId('update');
			$this->zbxTestAssertElementValue('update', 'Update');
		}
		else {
			$this->zbxTestAssertElementNotPresentId('delete');
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
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickXpathWait("//ul[@class='object-group']//a[text()='Items']");
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item updated');
		$this->zbxTestTextPresent($name);
		$this->zbxTestCheckHeader('Items');

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
					'error_msg' => 'Cannot add item',
					'errors' => [
						'Item with key "vfs.file.cksum[/sbin/shutdown]" already exists on'
					]
				]
			],
			// Item name is missing
			[
				[
					'expected' => TEST_BAD,
					'key' =>'item-name-missing',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Name": cannot be empty.'
					]
				]
			],
			// Item key is missing
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item name',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
					'error_msg' => 'Cannot add item',
					'errors' => [
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
						['flexDelay' => 50, 'flexTime' => '']
					],
					'error_msg' => 'Cannot add item',
					'errors' => [
						'Invalid interval "50/": unexpected end of interval.'
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
						['flexDelay' => 50, 'flexTime' => '1-11,00:00-24:00']
					],
					'error_msg' => 'Cannot add item',
					'errors' => [
						'Invalid interval "50/1-11,00:00-24:00": incorrect syntax near "1,00:00-24:00"'
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
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-25:00']
					],
					'error_msg' => 'Cannot add item',
					'errors' => [
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
						['flexDelay' => 50, 'flexTime' => '1-7,24:00-00:00']
					],
					'error_msg' => 'Cannot add item',
					'errors' => [
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
						['flexDelay' => 50, 'flexTime' => '1,00:00-24:00;2,00:00-24:00']
					],
					'error_msg' => 'Cannot add item',
					'errors' => [
						'Invalid interval "1,00:00-24:00;2,00:00-24:00".'
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
						['flexDelay' => 50, 'flexTime' => '1,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '2,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '1,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '2,00:00-24:00']
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
					'error_msg' => 'Cannot add item',
					'errors' => [
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
						['flexDelay' => 50, 'flexTime' => '1,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '2,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '3,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '4,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '5,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '6,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '7,00:00-24:00']
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
					'error_msg' => 'Cannot add item',
					'errors' => [
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
						['flexDelay' => 50, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '6-7,00:00-24:00']
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
					'error_msg' => 'Cannot add item',
					'errors' => [
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
						['flexDelay' => 50, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '6-7,00:00-24:00']
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
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00']
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
					'error_msg' => 'Cannot add item',
					'errors' => [
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
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00']
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
						['flexDelay' => 50, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '6-7,00:00-24:00']
					],
					'error_msg' => 'Cannot add item',
					'errors' => [
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
						['flexDelay' => 50, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '6-7,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00']
					],
					'error_msg' => 'Cannot add item',
					'errors' => [
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
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00']
					],
					'error_msg' => 'Cannot add item',
					'errors' => [
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
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00']
					],
					'error_msg' => 'Cannot add item',
					'errors' => [
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
						['flexDelay' => 50, 'flexTime' => '1,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '2,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '3,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '4,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '5,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '6,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '7,00:00-24:00']
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
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00']
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
						['flexDelay' => 50, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '6-7,00:00-24:00']
					],
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			// Seven flexfields - save OK
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item flex-maximum save OK',
					'key' => 'item-flex-maximum-save',
					'flexPeriod' => [
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00']
					],
					'dbCheck' => true,
					'formCheck' => true
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
							'Incorrect value "-1" for "History storage period" field: must be between 0 and 65535.'
					]
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
							'Incorrect value "65536" for "Trend storage period" field: must be between 0 and 65535.'
					]
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
					'error_msg' => 'Cannot add item',
					'errors' => [
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
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
					'error_msg' => 'Cannot add item',
					'errors' => [
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
					'error_msg' => 'Cannot add item',
					'errors' => [
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
					'error_msg' => 'Cannot add item',
					'errors' => [
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
					'error_msg' => 'Cannot add item',
					'errors' => [
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
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickXpathWait("//ul[@class='object-group']//a[text()='Items']");

		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestCheckHeader('Items');

		$this->zbxTestClickWait('form');
		$this->zbxTestCheckTitle('Configuration of items');

		if (isset($data['type'])) {
			$this->zbxTestDropdownSelect('type', $data['type']);
			$type = $data['type'];
		}
		else {
			$type = $this->zbxTestGetSelectedLabel('type');
		}

		if (isset($data['name'])) {
			$this->zbxTestInputType('name', $data['name']);
		}
		$name = $this->zbxTestGetValue("//input[@id='name']");

		if (isset($data['key'])) {
			$this->zbxTestInputType('key', $data['key']);
		}
		$key = $this->zbxTestGetValue("//input[@id='key']");

		if (isset($data['username'])) {
			$this->zbxTestInputType('username', $data['username']);
		}

		if (isset($data['ipmi_sensor'])) {
				$this->zbxTestInputType('ipmi_sensor', $data['ipmi_sensor']);
				$ipmi_sensor = $this->zbxTestGetValue("//input[@id='ipmi_sensor']");
		}

		if (isset($data['params_f'])) {
			$this->zbxTestInputType('params_f', $data['params_f']);
		}

		if (isset($data['params_es'])) {
			$this->zbxTestInputTypeWait('params_es', $data['params_es']);
		}

		if (isset($data['params_ap'])) {
			$this->zbxTestInputTypeWait('params_ap', $data['params_ap']);
		}

		if (isset($data['formula'])) {
			$this->zbxTestCheckboxSelect('multiplier');
			$this->zbxTestInputType('formula', $data['formula']);
		}

		if (isset($data['delay']))	{
			$this->zbxTestInputTypeOverwrite('delay', $data['delay']);
		}

		$itemFlexFlag = true;
		if (isset($data['flexPeriod'])) {

			$itemCount = 0;
			foreach ($data['flexPeriod'] as $period) {
				$this->zbxTestInputType('delay_flex_'.$itemCount.'_period', $period['flexTime']);

				if (isset($period['flexDelay'])) {
					$this->zbxTestInputType('delay_flex_'.$itemCount.'_delay', $period['flexDelay']);
				}
				$itemCount ++;
				$this->zbxTestClickWait('interval_add');

				$this->zbxTestAssertVisibleId('delay_flex_'.$itemCount.'_delay');
				$this->zbxTestAssertVisibleId('delay_flex_'.$itemCount.'_period');

				if (isset($period['remove'])) {
					$this->zbxTestClick('delay_flex_'.($itemCount-1).'_remove');
				}
			}
		}

		if (isset($data['history'])) {
			$this->zbxTestInputTypeOverwrite('history', $data['history']);
		}

		if (isset($data['trends'])) {
			$this->zbxTestInputTypeOverwrite('trends', $data['trends']);
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
				$interfaceid = $this->zbxTestGetText("//select[@id='interfaceid']/optgroup/option[not(@disabled)]");
				break;
			default:
				$this->zbxTestAssertNotVisibleId('interfaceid');
		}

		$value_type = $this->zbxTestGetSelectedLabel('value_type');
		$data_type = $this->zbxTestGetSelectedLabel('data_type');

		if ($itemFlexFlag == true) {
			$this->zbxTestClickWait('add');
			$expected = $data['expected'];
			switch ($expected) {
				case TEST_GOOD:
					$this->zbxTestCheckTitle('Configuration of items');
					$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item added');
					break;

				case TEST_BAD:
					$this->zbxTestCheckTitle('Configuration of items');
					$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
					foreach ($data['errors'] as $msg) {
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent(['Host', 'Name', 'Key']);
					if (isset($data['formula'])) {
						$this->zbxTestAssertElementValue('formula', $data['formulaValue']);
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
			$this->zbxTestClickLinkTextWait($dbName);
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('name'));
			$this->zbxTestAssertElementValue('name', $name);
			$this->zbxTestAssertElementValue('key', $key);
			$this->zbxTestAssertElementPresentXpath("//select[@id='type']/option[text()='$type']");
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
			$this->zbxTestAssertElementPresentXpath("//select[@id='interfaceid']/optgroup/option[text()='".$interfaceid."']");
					break;
				default:
					$this->zbxTestAssertNotVisibleId('interfaceid');
			}
			$this->zbxTestAssertElementPresentXpath("//select[@id='value_type']/option[text()='$value_type']");
			$this->zbxTestAssertElementPresentXpath("//select[@id='data_type']/option[text()='$data_type']");

			if (isset($data['ipmi_sensor'])) {
				$ipmiValue = $this->zbxTestGetValue("//input[@id='ipmi_sensor']");
				$this->assertEquals($ipmi_sensor, $ipmiValue);
				}
			}
		}

	public function testFormItem_HousekeeperUpdate() {
		$this->zbxTestLogin('adm.gui.php');
		$this->zbxTestAssertElementPresentId('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeping');

		$this->zbxTestCheckboxSelect('hk_history_global', false);
		$this->zbxTestCheckboxSelect('hk_trends_global', false);

		$this->zbxTestClickWait('update');

		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickXpathWait("//ul[@class='object-group']//a[text()='Items']");
		$this->zbxTestClickLinkTextWait($this->item);

		$this->zbxTestTextNotPresent('Overridden by global housekeeping settings');
		$this->zbxTestTextNotPresent('Overridden by global housekeeping settings');

		$this->zbxTestOpen('adm.gui.php');
		$this->zbxTestAssertElementPresentId('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeping');

		$this->zbxTestCheckboxSelect('hk_history_global');
		$this->zbxTestInputType('hk_history', 99);

		$this->zbxTestCheckboxSelect('hk_trends_global');
		$this->zbxTestInputType('hk_trends', 455);

		$this->zbxTestClickWait('update');

		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickXpathWait("//ul[@class='object-group']//a[text()='Items']");
		$this->zbxTestClickLinkTextWait($this->item);

		$this->zbxTestAssertElementText("//li[30]/div[@class='table-forms-td-right']", 'Overridden by global housekeeping settings (99 days)');
		$this->zbxTestAssertElementText("//li[@id='row_trends']/div[@class='table-forms-td-right']", 'Overridden by global housekeeping settings (455 days)');

		$this->zbxTestOpen('adm.gui.php');
		$this->zbxTestAssertElementPresentId('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeping');

		$this->zbxTestInputType('hk_history', 90);
		$this->zbxTestCheckboxSelect('hk_history_global', false);

		$this->zbxTestInputType('hk_trends', 365);
		$this->zbxTestCheckboxSelect('hk_trends_global', false);

		$this->zbxTestClickWait('update');

		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickXpathWait("//ul[@class='object-group']//a[text()='Items']");
		$this->zbxTestClickLinkTextWait($this->item);

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
