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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

/**
 * @backup items
 */
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

	// Returns layout data
	public static function layout() {
		return [
			[
				['type' => 'Zabbix agent', 'host' => 'Simple form test host']
			],
			[
				['host' => 'Simple form test host', 'form' => 'testFormDiscoveryRule1']
			],
			[
				['type' => 'Zabbix agent (active)', 'host' => 'Simple form test host'],
			],
			[
				['type' => 'Simple check', 'host' => 'Simple form test host']
			],
			[
				['type' => 'SNMPv1 agent', 'host' => 'Simple form test host']
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
				['type' => 'Zabbix internal', 'host' => 'Simple form test host']
			],
			[
				['type' => 'Zabbix trapper', 'host' => 'Simple form test host']
			],
			[
				['type' => 'External check', 'host' => 'Simple form test host']
			],
			[
				['type' => 'IPMI agent', 'host' => 'Simple form test host']
			],
			[
				['type' => 'SSH agent', 'host' => 'Simple form test host']
			],
			[
				['type' => 'SSH agent',
				'authtype' => 'Public key',
				'host' => 'Simple form test host'
				]
			],
			[
				[
					'type' => 'SSH agent',
					'authtype' => 'Password',
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
				['type' => 'Zabbix agent', 'template' => 'Inheritance test template']
			],
			[
				[
					'type' => 'Zabbix agent',
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceDiscoveryRule1'
				]
			],
			[
				['type' => 'Zabbix agent (active)', 'template' => 'Inheritance test template']
			],
			[
				['type' => 'Simple check', 'template' => 'Inheritance test template']
			],
			[
				['type' => 'SNMPv1 agent', 'template' => 'Inheritance test template']
			],
			[
				['type' => 'SNMPv2 agent', 'template' => 'Inheritance test template']
			],
			[
				['type' => 'SNMPv3 agent', 'template' => 'Inheritance test template']
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'noAuthNoPriv',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authNoPriv',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'SNMPv3 agent',
					'snmpv3_securitylevel' => 'authPriv',
					'template' => 'Inheritance test template'
				]
			],
			[
				['type' => 'Zabbix internal', 'template' => 'Inheritance test template']
			],
			[
				['type' => 'Zabbix trapper', 'template' => 'Inheritance test template']
			],
			[
				['type' => 'External check', 'template' => 'Inheritance test template']
			],
			[
				['type' => 'IPMI agent', 'template' => 'Inheritance test template']
			],
			[
				['type' => 'SSH agent', 'template' => 'Inheritance test template']
			],
			[
				[
					'type' => 'SSH agent',
					'authtype' => 'Public key',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'type' => 'SSH agent',
					'authtype' => 'Password',
					'template' => 'Inheritance test template'
				]
			],
			[
				['type' => 'TELNET agent', 'template' => 'Inheritance test template']
			],
			[
				['type' => 'JMX agent', 'template' => 'Inheritance test template']
			]
		];
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormDiscoveryRule_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickLinkTextWait($data['template']);
			$hostid = 30000;
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickLinkTextWait($data['host']);
			if (isset($data['templatedHost'])) {
				$hostid = 30001;
			}
			else {
				$hostid = 40001;
			}
		}

		$this->zbxTestClickLinkTextWait('Discovery rules');

		if (isset($data['form'])) {
			$this->zbxTestClickLinkTextWait($data['form']);
		}
		else {
			$this->zbxTestClickButtonText('Create discovery rule');
		}

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckHeader('Discovery rules');

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
		$this->zbxTestAssertVisibleId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='name']", 'size', 20);
		$this->zbxTestAssertAttribute("//input[@id='name']", 'autofocus');
			if(isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//input[@id='name']", 'readonly');
			}

		$this->zbxTestTextPresent('Type');
		if (!isset($data['templatedHost'])) {
			$this->zbxTestAssertVisibleId('type');
			$this->zbxTestDropdownHasOptions('type', [
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
		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertAttribute("//input[@id='key']", 'readonly');
		}

		if (isset($data['host']) && isset($data['form']) && !isset($data['templatedHost'])) {
			$this->zbxTestAssertElementValue('key', $this->keyForm);
		}

		if (isset($data['template']) && isset($data['form'])) {
			$this->zbxTestAssertElementValue('key', $this->keyInheritance);
		}

		if (isset($data['host']) && isset($data['templatedHost'])) {
			$this->zbxTestAssertElementValue('key', $this->keyInheritance);
		}

		if (!isset($data['form'])) {
			switch($type) {
				case 'SSH agent':
					$this->zbxTestAssertElementValue('key', 'ssh.run[<unique short description>,<ip>,<port>,<encoding>]');
					break;
				case 'TELNET agent':
					$this->zbxTestAssertElementValue('key', 'telnet.run[<unique short description>,<ip>,<port>,<encoding>]');
					break;
				default:
					$this->zbxTestAssertElementValue('key', '');
					break;
				}
		}

		if (!isset($data['template'])){
			$interfaceType = itemTypeInterface($this->zbxTestGetValue('//*[@id="type"]'));
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
							($interfaceType == INTERFACE_TYPE_ANY ? '' : ' AND type='.$interfaceType), false
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
		if ($type == 'SNMPv3 agent') {
			if (isset($data['snmpv3_securitylevel'])) {
				$this->zbxTestDropdownSelect('snmpv3_securitylevel', $data['snmpv3_securitylevel']);
				$snmpv3_securitylevel = $data['snmpv3_securitylevel'];
			}
			else {
				$snmpv3_securitylevel = $this->zbxTestGetSelectedLabel('snmpv3_securitylevel');
			}
		}

		$this->zbxTestTextNotPresent('Additional parameters');
		$this->zbxTestAssertNotVisibleId('params_ap');

		if ($type == 'SSH agent' || $type == 'TELNET agent' ) {
			$this->zbxTestTextPresent('Executed script');
			$this->zbxTestAssertVisibleId('params_es');
			$this->zbxTestAssertAttribute("//textarea[@id='params_es']", 'rows', 7);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage('Executed script');
			$this->zbxTestAssertNotVisibleId('params_es');
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

		if ($type == 'SSH agent' || $type == 'TELNET agent' || $type == 'JMX agent' || $type == 'Simple check') {
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
			$this->zbxTestAssertAttribute("//input[@id='snmp_oid']", 'maxlength', 512);
			$this->zbxTestAssertAttribute("//input[@id='snmp_oid']", 'size', 20);
			$this->zbxTestAssertElementValue('snmp_oid', 'interfaces.ifTable.ifEntry.ifInOctets.1');

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
			$this->zbxTestAssertElementValue('snmp_community', 'public');
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
			case 'External check':
			case 'IPMI agent':
			case 'SSH agent':
			case 'TELNET agent':
			case 'JMX agent':
				$this->zbxTestTextPresent('Update interval');
				$this->zbxTestAssertVisibleId('delay');
				$this->zbxTestAssertAttribute("//input[@id='delay']", 'maxlength', 255);
				$this->zbxTestAssertAttribute("//input[@id='delay']", 'size', 20);
				if (!isset($data['form'])) {
					$this->zbxTestAssertElementValue('delay', '30s');
				}
				break;
			default:
				$this->zbxTestTextNotVisibleOnPage('Update interval');
				$this->zbxTestAssertNotVisibleId('delay');
		}

		$this->zbxTestTextPresent('Keep lost resources period');
		$this->zbxTestAssertVisibleId('lifetime');
		$this->zbxTestAssertAttribute("//input[@id='lifetime']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='lifetime']", 'size', 20);
		$this->zbxTestAssertElementValue('lifetime', '30d');

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
				$this->zbxTestTextPresent(['Custom intervals', 'Interval',  'Period', 'Action']);
				$this->zbxTestAssertVisibleId('delayFlexTable');

				$this->zbxTestTextPresent(['Flexible', 'Scheduling']);
				$this->zbxTestAssertVisibleId('delay_flex_0_delay');
				$this->zbxTestAssertAttribute("//input[@id='delay_flex_0_delay']", 'maxlength', 255);
				$this->zbxTestAssertAttribute("//input[@id='delay_flex_0_delay']", 'size', 20);
				$this->zbxTestAssertAttribute("//input[@id='delay_flex_0_delay']", 'placeholder', '50s');

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

		$this->zbxTestTextPresent('Description');
		$this->zbxTestAssertVisibleId('description');
		$this->zbxTestAssertAttribute("//textarea[@id='description']", 'rows', 7);

		$this->zbxTestTextPresent('Enabled');
		$this->zbxTestAssertElementPresentId('status');
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));

		$this->zbxTestClickWait('tab_macroTab');
		if ($this->zbxTestGetText("//li[contains(@class, 'ui-tabs-active')]/a") != 'Filters') {
			$this->zbxTestTabSwitch('Filters');
		}

		$this->zbxTestTextPresent('Filters');
		$this->zbxTestTextPresent('Type of calculation');
		$this->zbxTestTextPresent('Macro');
		$this->zbxTestAssertVisibleId('conditions_0_macro');
		$this->zbxTestAssertAttribute("//input[@id='conditions_0_macro']", 'maxlength', 64);
		$this->zbxTestAssertAttribute("//input[@id='conditions_0_macro']", 'size', 20);

		$this->zbxTestTextPresent('Regular expression');
		$this->zbxTestAssertVisibleId('conditions_0_value');
		$this->zbxTestAssertAttribute("//input[@id='conditions_0_value']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='conditions_0_value']", 'size', 20);
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
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Discovery rule updated');
		$this->zbxTestTextPresent("$name");

		$this->assertEquals($oldHashDiscovery, DBhash($sqlDiscovery));
	}

	// Returns create data
	public static function create() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'error_msg' => 'Page received incorrect data',
					'errors' => [
							'Incorrect value for field "Name": cannot be empty.',
							'Incorrect value for field "Key": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'discoveryRuleError',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
							'Incorrect value for field "Key": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'key' => 'discovery-rule-error',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
							'Incorrect value for field "Name": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'discoveryRuleNo1',
					'key' => 'discovery-key-no1',
					'formCheck' =>true,
					'dbCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'discoveryRuleNo2',
					'key' => 'discovery-key-no2',
					'formCheck' =>true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				['expected' => TEST_BAD,
					'name' => 'discoveryRuleNo1',
					'key' => 'discovery-key-no1',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item with key "discovery-key-no1" already exists on "Simple form test host".']
				]
			],
			[
				['expected' => TEST_BAD,
					'name' => 'discoveryRuleError',
					'key' => 'discovery-key-no1',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item with key "discovery-key-no1" already exists on "Simple form test host".']
				]
			],
			// Empty keep lost resources period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery lifetime',
					'key' => 'discovery-lifetime-test',
					'lifetime' => ' ',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "lifetime": a time unit is expected.'
					]
				]
			],
			// Incorrect keep lost resources period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery lifetime',
					'key' => 'discovery-lifetime-test',
					'lifetime' => '-30',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "lifetime": a time unit is expected.'
					]
				]
			],
			// Incorrect keep lost resources period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery lifetime',
					'key' => 'discovery-lifetime-test',
					'lifetime' => 1,
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "lifetime": must be between "3600" and "788400000".'
					]
				]
			],
			// Incorrect keep lost resources period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery lifetime',
					'key' => 'discovery-lifetime-test',
					'lifetime' => 3599,
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "lifetime": must be between "3600" and "788400000".'
					]
				]
			],
			// Incorrect keep lost resources period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery lifetime',
					'key' => 'discovery-lifetime-test',
					'lifetime' => '59m',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "lifetime": must be between "3600" and "788400000".'
					]
				]
			],
			// Incorrect keep lost resources period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery lifetime',
					'key' => 'discovery-lifetime-test',
					'lifetime' => '1304w',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "lifetime": must be between "3600" and "788400000".'
					]
				]
			],
			// Incorrect keep lost resources period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery lifetime',
					'key' => 'discovery-lifetime-test',
					'lifetime' => '9126d',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "lifetime": must be between "3600" and "788400000".'
					]
				]
			],
			// Incorrect keep lost resources period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery lifetime',
					'key' => 'discovery-lifetime-test',
					'lifetime' => '219001h',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "lifetime": must be between "3600" and "788400000".'
					]
				]
			],
			// Incorrect keep lost resources period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery lifetime',
					'key' => 'discovery-lifetime-test',
					'lifetime' => '13140001m',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "lifetime": must be between "3600" and "788400000".'
					]
				]
			],
			// Incorrect keep lost resources period
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery lifetime',
					'key' => 'discovery-lifetime-test',
					'lifetime' => 788400001,
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "lifetime": must be between "3600" and "788400000".'
					]
				]
			],
			// Empty timedelay
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
					'delay' => 0,
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Incorrect timedelay
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
					'delay' => '-30',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Field "Update interval" is not correct: a time unit is expected'
					]
				]
			],
			// Incorrect timedelay
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
					'delay' => 86401,
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Incorrect timedelay
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
					'delay' => '1w',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Incorrect timedelay
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
					'delay' => '2d',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Incorrect timedelay
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
					'delay' => '25h',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Incorrect timedelay
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
					'delay' => '1441m',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Empty time flex period
			[
				[
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
					'flexPeriod' => [
						['flexDelay' => 50, 'flexTime' => '']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "delay": invalid delay'
					]
				]
			],
			// Incorrect flex period
			[
				[
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
					'flexPeriod' => [
						['flexDelay' => 50, 'flexTime' => '1-11,00:00-24:00']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "delay": invalid delay'
					]
				]
			],
			// Incorrect flex period
			[
				[
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
					'flexPeriod' => [
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-25:00']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "delay": invalid delay'
					]
				]
			],
			// Incorrect flex period
			[
				[
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
					'flexPeriod' => [
						['flexDelay' => 50, 'flexTime' => '1-7,24:00-00:00']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Incorrect value for field "delay": invalid delay'
					]
				]
			],
			// Incorrect flex period
			[
				[
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
					'flexPeriod' => [
						['flexDelay' => 50, 'flexTime' => '1,00:00-24:00;2,00:00-24:00']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Invalid interval "1,00:00-24:00;2,00:00-24:00".'
					]
				]
			],
			// Multiple flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-test',
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
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '2,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '3,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '4,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '5,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '6,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '7,00:00-24:00']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex1',
					'key' =>'discovery-flex-delay1',
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
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
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
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex2',
					'key' =>'discovery-flex-delay2',
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
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay3',
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
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay4',
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
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay5',
					'flexPeriod' => [
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00']
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '6-7,00:00-24:00']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 50, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '6-7,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00'],
						['flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_BAD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay',
					'flexPeriod' => [
						['flexDelay' => 0, 'flexTime' => '1-7,00:00-24:00'],
						['flexDelay' => 50, 'flexTime' => '1-7,00:00-24:00']
					],
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item will not be refreshed. Please enter a correct update interval.'
					]
				]
			],
			// Delay combined with flex periods
			[
				[
					'expected' => TEST_GOOD,
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay6',
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
					'name' =>'Discovery flex',
					'key' =>'discovery-flex-delay7',
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
					'name' =>'Discovery flex Check',
					'key' =>'discovery-flex-delay8',
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
			[
				[
					'expected' => TEST_GOOD,
					'name' =>'!@#$%^&*()_+-=[]{};:"|,./<>?',
					'key' =>'discovery-symbols-test',
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
					'key' => 'discovery-zabbix-agent',
					'dbCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Zabbix agent (active)',
					'name' => 'Zabbix agent (active)',
					'key' => 'discovery-zabbix-agent-active',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Simple check',
					'name' => 'Simple check',
					'key' => 'discovery-simple-check',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'SNMPv1 agent',
					'name' => 'SNMPv1 agent',
					'key' => 'discovery-snmpv1-agent',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'SNMPv2 agent',
					'name' => 'SNMPv2 agent',
					'key' => 'discovery-snmpv2-agent',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'SNMPv3 agent',
					'name' => 'SNMPv3 agent',
					'key' => 'discovery-snmpv3-agent',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'SNMPv1 agent',
					'name' => 'SNMPv1 agent',
					'key' => 'test-item-reuse',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item with key "test-item-reuse" already exists on "Simple form test host".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'SNMPv1 agent',
					'name' => 'SNMPv1 agent',
					'key' => 'test-item-form1',
					'error_msg' => 'Cannot add discovery rule',
					'errors' => [
						'Item with key "test-item-form1" already exists on "Simple form test host".'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Zabbix internal',
					'name' => 'Zabbix internal',
					'key' => 'discovery-zabbix-internal',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Zabbix trapper',
					'name' => 'Zabbix trapper',
					'key' => 'snmptrap.fallback',
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'External check',
					'name' => 'External check',
					'key' => 'discovery-external-check',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'IPMI agent',
					'name' => 'IPMI agent',
					'key' => 'discovery-ipmi-agent',
					'ipmi_sensor' => 'ipmi_sensor',
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'IPMI agent',
					'name' => 'IPMI agent with spaces',
					'key' => 'item-ipmi-agent-spaces',
					'ipmi_sensor' => '    ipmi_sensor    ',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'SSH agent',
					'name' => 'SSH agent',
					'key' => 'discovery-ssh-agent',
					'username' => 'zabbix',
					'params_es' => 'executed script',
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'TELNET agent',
					'name' => 'TELNET agent',
					'key' => 'discovery-telnet-agent',
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
					'key' => 'discovery-ipmi-agent-error',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "IPMI sensor": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'SSH agent',
					'name' => 'SSH agent error',
					'key' => 'discovery-ssh-agent-error',
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
					'key' => 'discovery-telnet-agent-error',
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
					'key' => 'discovery-jmx-agent',
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
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
					'error_msg' => 'Cannot add discovery rule',
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
					'error_msg' => 'Cannot add discovery rule',
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
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Key": cannot be empty.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormDiscoveryRule_SimpleCreate($data) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestCheckHeader('Hosts');

		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickButtonText('Create discovery rule');

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckHeader('Discovery rules');

		if (isset($data['type'])) {
			$this->zbxTestDropdownSelect('type', $data['type']);
			$type = $data['type'];
		}
		else {
			$type = $this->zbxTestGetSelectedLabel('type');
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

		if (isset($data['params_es'])) {
			$this->zbxTestInputType('params_es', $data['params_es']);
		}

		if (isset($data['lifetime']))	{
			$this->zbxTestInputTypeOverwrite('lifetime', $data['lifetime']);
		}

		if (isset($data['delay']))	{
			$this->zbxTestInputTypeOverwrite('delay', $data['delay']);
		}

		$itemFlexFlag = true;
		if (isset($data['flexPeriod'])) {

			$itemCount = 0;
			foreach ($data['flexPeriod'] as $period) {
				if (isset($period['flexDelay'])) {
					$this->zbxTestInputType('delay_flex_'.$itemCount.'_delay', $period['flexDelay']);
				}

				$this->zbxTestInputType('delay_flex_'.$itemCount.'_period', $period['flexTime']);

				$itemCount ++;
				$this->zbxTestClickWait('interval_add');

				$this->zbxTestAssertVisibleId('delay_flex_'.$itemCount.'_delay');
				$this->zbxTestAssertVisibleId('delay_flex_'.$itemCount.'_period');

				if (isset($period['remove'])) {
					$this->zbxTestClickWait('delay_flex_'.($itemCount-1).'_remove');
				}
			}
		}

		if ($itemFlexFlag == true) {
			$this->zbxTestClickWait('add');
			$expected = $data['expected'];
			switch ($expected) {
				case TEST_GOOD:
					$this->zbxTestCheckTitle('Configuration of discovery rules');
					$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Discovery rule created');
					$this->zbxTestTextPresent(['Item prototypes',  'Trigger prototypes', 'Graph prototypes']);
					break;

				case TEST_BAD:
					$this->zbxTestCheckTitle('Configuration of discovery rules');
					$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
					foreach ($data['errors'] as $msg) {
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent(['Name', 'Type', 'Key']);
					break;
			}
		}

		if (isset($data['formCheck'])) {
			$this->zbxTestOpen('hosts.php');
			$this->zbxTestClickLinkTextWait($this->host);
			$this->zbxTestClickLinkTextWait('Discovery rules');

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

			// "Check now" button availability
			if (in_array($type, ['Zabbix agent', 'Simple check', 'SNMPv1 agent', 'SNMPv2 agent', 'SNMPv3 agent',
					'Zabbix internal', 'External check', 'Database monitor', 'IPMI agent', 'SSH agent', 'TELNET agent',
					'JMX agent'])) {
				$this->zbxTestClick('check_now');
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Request sent successfully');
				$this->zbxTestCheckFatalErrors();
			}
			else {
				$this->zbxTestAssertElementPresentXpath("//button[@id='check_now'][@disabled]");
			}

			if (isset($data['ipmi_sensor'])) {
				$ipmiValue = $this->zbxTestGetValue("//input[@id='ipmi_sensor']");
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

			$this->zbxTestOpen('hosts.php');
			$this->zbxTestClickLinkTextWait($this->host);
			$this->zbxTestClickLinkTextWait('Discovery rules');

			$this->zbxTestCheckboxSelect("g_hostdruleid_$itemId");
			$this->zbxTestClickButton('discoveryrule.massdelete');

			$this->zbxTestAcceptAlert();
			$this->zbxTestWaitUntilMessageTextPresent('msg-good' ,'Discovery rules deleted');

			$sql = "SELECT itemid FROM items WHERE name = '".$name."' and hostid = ".$this->hostid;
			$this->assertEquals(0, DBcount($sql), 'Discovery rule has not been deleted from DB.');
		}
	}
}
