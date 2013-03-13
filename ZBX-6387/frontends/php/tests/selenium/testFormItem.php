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
require_once dirname(__FILE__).'/../../include/items.inc.php';

define('ITEM_GOOD', 0);
define('ITEM_BAD', 1);

class testFormItem extends CWebTest {

	// Returns all possible item types
	public static function itemTypes() {
		return array(
			array(
				array('type' => 'Zabbix agent')
			),
			array(
				array('type' => 'SNMPv1 agent')
			),
			array(
				array('type' => 'SNMPv1 agent', 'value_type' => 'Numeric (float)')
			),
			array(
				array('type' => 'Zabbix trapper')
			),
			array(
				array('type' => 'Simple check')
			),
			array(
				array('type' => 'SNMPv2 agent')
			),
			array(
				array('type' => 'Zabbix internal')
			),
			array(
				array(
					'type' => 'Zabbix internal',
					'value_type' => 'Numeric (unsigned)',
					'data_type' => 'Boolean'
				)
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
				array('type' => 'SNMPv3 agent', 'snmpv3_securitylevel' => 'noAuthNoPriv', 'value_type' => 'Character')
			),
			array(
				array('type' => 'SNMPv3 agent', 'snmpv3_securitylevel' => 'noAuthNoPriv', 'value_type' => 'Log')
			),
			array(
				array('type' => 'SNMPv3 agent', 'snmpv3_securitylevel' => 'noAuthNoPriv', 'value_type' => 'Text')
			),
			array(
				array('type' => 'SNMPv3 agent', 'snmpv3_securitylevel' => 'authNoPriv')
			),
			array(
				array('type' => 'SNMPv3 agent', 'snmpv3_securitylevel' => 'authPriv')
			),
			array(
				array('type' => 'Zabbix agent (active)'),
			),
			array(
				array('type' => 'Zabbix aggregate'),
			),
			array(
				array('type' => 'External check'),
			),
			array(
				array('type' => 'Database monitor'),
			),
			array(
				array('type' => 'IPMI agent'),
			),
			array(
				array('type' => 'SSH agent'),
			),
			array(
				array('type' => 'SSH agent', 'authtype' => 'Public key'),
			),
			array(
				array('type' => 'SSH agent', 'authtype' => 'Password'),
			),
			array(
				array('type' => 'TELNET agent'),
			),
			array(
				array('type' => 'Calculated'),
			),
			array(
				array('type' => 'JMX agent'),
			),
			array(
				array('type' => 'SNMP trap')
			)
		);
	}

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormItem_setup() {
		DBsave_tables('items');
	}

	/**
	 * @dataProvider itemTypes
	 */
	public function testFormItem_CheckLayout($data) {

		$this->zbxTestLogin('items.php');
		$this->checkTitle('Configuration of items');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');

		$this->zbxTestClickWait('form');
		$this->checkTitle('Configuration of items');

		$this->zbxTestTextPresent('Host interface');
		$this->zbxTestTextPresent('Type of information');
		$this->zbxTestTextPresent('Data type');
		$this->zbxTestTextPresent('Units');
		$this->zbxTestTextPresent('Use custom multiplier');
		$this->zbxTestTextPresent('Update interval (in sec)');
		$this->zbxTestTextPresent('Flexible intervals');
		$this->zbxTestTextPresent('Interval');
		$this->zbxTestTextPresent('Period');
		$this->zbxTestTextPresent('Action');
		$this->zbxTestTextPresent('No flexible intervals defined.');
		// $this->zbxTestTextPresent('New flexible interval');
		$this->zbxTestTextPresent('Update interval (in sec)');
		$this->zbxTestTextPresent('Period');
		$this->zbxTestTextPresent('Keep history (in days)');
		$this->zbxTestTextPresent('Keep trends (in days)');
		$this->zbxTestTextPresent('Store value');
		$this->zbxTestTextPresent('Show value');
		$this->zbxTestTextPresent('show value mappings');
		$this->zbxTestTextPresent('New application');
		$this->zbxTestTextPresent('Applications');
		$this->zbxTestTextPresent('Populates host inventory field');
		$this->zbxTestTextPresent('Description');
		$this->zbxTestTextPresent('Status');

		$this->zbxTestTextPresent('Host');
		$this->assertVisible('hostname');
		$this->assertAttribute("//*[@id='hostname']/@readonly", 'readonly');
		$this->assertAttribute("//input[@id='hostname']/@maxlength", '255');
		$this->assertAttribute("//input[@id='hostname']/@size", '50');
		$this->assertVisible('btn_host');
		$hostid = $this->getValue('hostid');


		$this->zbxTestDropdownSelect('type', $itemType);

		$this->zbxTestTextPresent('Name');
		$this->assertVisible('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", '255');
		$this->assertAttribute("//input[@id='name']/@size", '50');
		$this->assertAttribute("//input[@id='name']/@autofocus", 'autofocus');

		$this->zbxTestTextPresent('Key');
		$this->assertVisible('key');
		$this->assertAttribute("//input[@id='key']/@maxlength", '255');
		$this->assertAttribute("//input[@id='key']/@size", '50');
		$this->assertElementPresent('keyButton');

		if (isset($data['snmpv3_securitylevel'])) {
			$this->dropdown_select('snmpv3_securitylevel', $data['snmpv3_securitylevel']);
		}

		if ($type == 'SNMPv3 agent') {
			$snmpv3_securitylevel = $this->getSelectedLabel('snmpv3_securitylevel');
		}

		if (isset($data['value_type'])) {
			$this->dropdown_select('value_type', $data['value_type']);
		}
		$value_type = $this->getSelectedLabel('value_type');

		if (isset($data['data_type'])) {
			$this->dropdown_select('data_type', $data['data_type']);
		}

		if ($value_type == 'Numeric (unsigned)') {
			$data_type = $this->getSelectedLabel('data_type');
		}
		else {
			$data_type = null;
		}

		if (isset($data['authtype'])) {
			$this->dropdown_select('authtype', $data['authtype']);
		}

		if ($type == 'SSH agent') {
			$authtype = $this->getSelectedLabel('authtype');
		}
		else {
			$authtype = null;
		}

		if ($type == 'Database monitor') {
			$this->ok('Additional parameters');
			$this->assertVisible('params_ap');
			$this->assertAttribute("//textarea[@id='params_ap']/@rows", '7');
		}
		else {
			$this->nok('Additional parameters');
			$this->assertNotVisible('params_ap');
		}

		if ($type == 'SSH agent' || $type == 'TELNET agent' ) {
			$this->ok('Executed script');
			$this->assertVisible('params_es');
			$this->assertAttribute("//textarea[@id='params_es']/@rows", '7');
		}
		else {
			$this->nok('Executed script');
			$this->assertNotVisible('params_es');
		}

		if ($type == 'Calculated') {
			$this->ok('Formula');
			$this->assertVisible('params_f');
			$this->assertAttribute("//textarea[@id='params_f']/@rows", '7');
		}
		else {
			$this->nok('Formula');
			$this->assertNotVisible('params_f');
		}

		$interfaceType = itemTypeInterface($this->getValue('type'));
			switch ($interfaceType) {
				case INTERFACE_TYPE_SNMP :
				case INTERFACE_TYPE_IPMI :
				case INTERFACE_TYPE_AGENT :
				case INTERFACE_TYPE_ANY :
				case INTERFACE_TYPE_JMX :
					$this->ok('Host interface');
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
							$host_interface['ip'].' : '.
							$host_interface['port'].'"]');
						}
					}
					else {
						$this->ok('No interface found');
						$this->assertNotVisible('interfaceid');
					}
					break;
				default:
					$this->nok(array('Host interface', 'No interface found'));
					$this->assertNotVisible('interfaceid');
					break;
		}

		if ($type == 'IPMI agent') {
			$this->ok('IPMI sensor');
			$this->assertVisible('ipmi_sensor');
			$this->assertAttribute("//input[@id='ipmi_sensor']/@maxlength", '128');
			$this->assertAttribute("//input[@id='ipmi_sensor']/@size", '50');
		}
		else {
			$this->nok('IPMI sensor');
			$this->assertNotVisible('ipmi_sensor');
		}

		if ($type == 'SSH agent') {
			$this->ok('Authentication method');
			$this->assertVisible('authtype');
			$this->assertElementPresent("//select[@id='authtype']/option[text()='Password']");
			$this->assertElementPresent("//select[@id='authtype']/option[text()='Public key']");
		}
		else {
			$this->nok('Authentication method');
			$this->assertNotVisible('authtype');
		}

		if ($type == 'SSH agent' || $type == 'TELNET agent' || $type == 'JMX agent') {
			$this->ok('User name');
			$this->assertVisible('username');
			$this->assertAttribute("//input[@id='username']/@maxlength", '64');
			$this->assertAttribute("//input[@id='username']/@size", '25');

			if ($authtype == 'Public key') {
				$this->ok('Key passphrase');
			}
			else {
				$this->ok('Password');
			}
			$this->assertVisible('password');
			$this->assertAttribute("//input[@id='password']/@maxlength", '64');
			$this->assertAttribute("//input[@id='password']/@size", '25');
		}
		else {
			$this->nok('User name');
			$this->assertNotVisible('username');

			$this->nok('Password');
			$this->assertNotVisible('password');
		}

		if	($authtype == 'Public key') {
			$this->ok('Public key file');
			$this->assertVisible('publickey');
			$this->assertAttribute("//input[@id='publickey']/@maxlength", '64');
			$this->assertAttribute("//input[@id='publickey']/@size", '25');

			$this->ok('Private key file');
			$this->assertVisible('privatekey');
			$this->assertAttribute("//input[@id='privatekey']/@maxlength", '64');
			$this->assertAttribute("//input[@id='privatekey']/@size", '25');
		}
		else {
			$this->nok('Public key file');
			$this->assertNotVisible('publickey');

			$this->nok('Private key file');
			$this->assertNotVisible('publickey');
		}

		if	($type == 'SNMPv1 agent' || $type == 'SNMPv2 agent' || $type == 'SNMPv3 agent') {
			$this->ok('SNMP OID');
			$this->assertVisible('snmp_oid');
			$this->assertAttribute("//input[@id='snmp_oid']/@maxlength", '255');
			$this->assertAttribute("//input[@id='snmp_oid']/@size", '50');
		}
		else {
			$this->nok('SNMP OID');
			$this->assertNotVisible('snmp_oid');
		}

		if	($type == 'SNMPv1 agent' || $type == 'SNMPv2 agent') {
			$this->ok('SNMP community');
			$this->assertVisible('snmp_community');
			$this->assertAttribute("//input[@id='snmp_community']/@maxlength", '64');
			$this->assertAttribute("//input[@id='snmp_community']/@size", '50');
		}
		else {
			$this->nok('SNMP community');
			$this->assertNotVisible('snmp_community');
		}

		if	($type == 'SNMPv1 agent' || $type == 'SNMPv2 agent' || $type == 'SNMPv3 agent') {
			$this->ok('Port');
			$this->assertVisible('port');
			$this->assertAttribute("//input[@id='port']/@maxlength", '64');
			$this->assertAttribute("//input[@id='port']/@size", '25');
		}
		else {
			$this->nok('Port');
			$this->assertNotVisible('port');
		}

		if	($type == 'SNMPv3 agent') {
			$this->ok('Security name');
			$this->assertVisible('snmpv3_securityname');
			$this->assertAttribute("//input[@id='snmpv3_securityname']/@maxlength", '64');
			$this->assertAttribute("//input[@id='snmpv3_securityname']/@size", '50');

			$this->ok('Security level');
			$this->assertVisible('snmpv3_securitylevel');
			$this->zbxDropdownHasOptions('snmpv3_securitylevel', array(
				'noAuthNoPriv',
				'authNoPriv',
				'authPriv'
			));
		}
		else {
			$this->nok('Security name');
			$this->assertNotVisible('snmpv3_securityname');

			$this->nok('Security level');
			$this->assertNotVisible('snmpv3_securitylevel');
		}

		if ($type == 'SNMPv3 agent' && $snmpv3_securitylevel != 'noAuthNoPriv') {
			$this->ok('Authentication protocol');
			$this->assertVisible('row_snmpv3_authprotocol');
			$this->assertVisible("//span[text()='MD5']");
			$this->assertVisible("//span[text()='SHA']");

			$this->ok('Authentication passphrase');
			$this->assertVisible('snmpv3_authpassphrase');
			$this->assertAttribute("//input[@id='snmpv3_authpassphrase']/@maxlength", '64');
			$this->assertAttribute("//input[@id='snmpv3_authpassphrase']/@size", '50');
		}
		else {
			$this->nok('Authentication protocol');
			$this->assertNotVisible('row_snmpv3_authprotocol');
			$this->assertNotVisible("//span[text()='MD5']");
			$this->assertNotVisible("//span[text()='SHA']");

			$this->nok('Authentication passphrase');
			$this->assertNotVisible('snmpv3_authpassphrase');
		}

		if ($type == 'SNMPv3 agent' && $snmpv3_securitylevel == 'authPriv') {
			$this->ok('Privacy protocol');
			$this->assertVisible('row_snmpv3_privprotocol');
			$this->assertVisible("//span[text()='DES']");
			$this->assertVisible("//span[text()='AES']");

			$this->ok('Privacy passphrase');
			$this->assertVisible('snmpv3_privpassphrase');
			$this->assertAttribute("//input[@id='snmpv3_privpassphrase']/@maxlength", '64');
			$this->assertAttribute("//input[@id='snmpv3_privpassphrase']/@size", '50');
		}
		else {
			$this->nok('Privacy protocol');
			$this->assertNotVisible('row_snmpv3_privprotocol');
			$this->assertNotVisible("//span[text()='DES']");
			$this->assertNotVisible("//span[text()='AES']");

			$this->nok('Privacy passphrase');
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
				$this->ok('Update interval (in sec)');
				$this->assertVisible('delay');
				$this->assertAttribute("//input[@id='delay']/@maxlength", '5');
				$this->assertAttribute("//input[@id='delay']/@size", '5');
				$this->assertAttribute("//input[@id='delay']/@value", '30');
				break;
			default:
				$this->nok('Update interval (in sec)');
				$this->assertNotVisible('delay');
		}

		$this->ok('Type of information');
		$this->assertVisible('value_type');
		$this->zbxDropdownHasOptions('value_type', array(
			'Numeric (unsigned)',
			'Numeric (float)',
			'Character',
			'Log',
			'Text'
		));
		$this->assertAttribute("//*[@id='value_type']/option[text()='Numeric (unsigned)']/@selected", 'selected');

		if ($value_type == 'Numeric (unsigned)') {
			$this->ok('Data type');
			$this->assertVisible('data_type');
			$this->zbxDropdownHasOptions('data_type', array(
				'Boolean',
				'Octal',
				'Decimal',
				'Hexadecimal'
			));
			$this->assertAttribute("//*[@id='data_type']/option[text()='Decimal']/@selected", 'selected');
		}
		else {
			$this->nok('Data type');
			$this->assertNotVisible('data_type');
		}

		if ($value_type == 'Numeric (float)' || ($value_type == 'Numeric (unsigned)' && $data_type != 'Boolean')) {
			$this->ok('Units');
			$this->assertVisible('units');
			$this->assertAttribute("//input[@id='units']/@maxlength", '255');
			$this->assertAttribute("//input[@id='units']/@size", '50');
		}
		else {
			$this->nok('Units');
			$this->assertNotVisible('units');
		}

		if ($value_type == 'Numeric (float)' || ($value_type == 'Numeric (unsigned)' && $data_type != 'Boolean')) {
			$this->ok('Use custom multiplier');
			$this->assertVisible('multiplier');
			$this->assertAttribute("//input[@id='multiplier']/@type", 'checkbox');

			$this->assertVisible('formula');
			$this->assertAttribute("//input[@id='formula']/@maxlength", '255');
			$this->assertAttribute("//input[@id='formula']/@size", '25');
			$this->assertAttribute("//input[@id='formula']/@value", '1');
		}
		else {
			$this->nok('Use custom multiplier');
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
				$this->ok(array('Flexible intervals', 'Interval', 'Period', 'No flexible intervals defined.'));
				$this->assertVisible('delayFlexTable');

				$this->ok('New flexible interval', 'Interval (in sec)', 'Period');
				$this->assertVisible('new_delay_flex_delay');
				$this->assertAttribute("//input[@id='new_delay_flex_delay']/@maxlength", '5');
				$this->assertAttribute("//input[@id='new_delay_flex_delay']/@size", '5');
				$this->assertAttribute("//input[@id='new_delay_flex_delay']/@value", '50');

				$this->assertVisible('new_delay_flex_period');
				$this->assertAttribute("//input[@id='new_delay_flex_period']/@maxlength", '255');
				$this->assertAttribute("//input[@id='new_delay_flex_period']/@size", '20');
				$this->assertAttribute("//input[@id='new_delay_flex_period']/@value", '1-7,00:00-24:00');
				$this->assertVisible('add_delay_flex');
				break;
			default:
				$this->nok(array('Flexible intervals', 'Interval', 'Period', 'No flexible intervals defined.'));
				$this->assertNotVisible('delayFlexTable');

				$this->nok('New flexible interval', 'Interval (in sec)', 'Period');
				$this->assertNotVisible('new_delay_flex_period');
				$this->assertNotVisible('new_delay_flex_delay');
				$this->assertNotVisible('add_delay_flex');
		}

		$this->ok('Keep history (in days)');
		$this->assertVisible('history');
		$this->assertAttribute("//input[@id='history']/@maxlength", '8');
		$this->assertAttribute("//input[@id='history']/@value", '90');
		$this->assertAttribute("//input[@id='history']/@size", '8');

		if ($value_type == 'Numeric (unsigned)' || $value_type == 'Numeric (float)') {
			$this->ok('Keep trends (in days)');
			$this->assertVisible('trends');
			$this->assertAttribute("//input[@id='trends']/@maxlength", '8');
			$this->assertAttribute("//input[@id='trends']/@value", '365');
			$this->assertAttribute("//input[@id='trends']/@size", '8');
		}
		else {
			$this->nok('Keep trends (in days)');
			$this->assertNotVisible('trends');
		}

		if ($value_type == 'Numeric (float)' || ($value_type == 'Numeric (unsigned)' && $data_type != 'Boolean')) {
			$this->ok('Store value');
			$this->assertVisible('delta');
			$this->zbxDropdownHasOptions('delta', array(
				'As is',
				'Delta (speed per second)',
				'Delta (simple change)'
			));
			$this->assertAttribute("//*[@id='delta']/option[text()='As is']/@selected", 'selected');
		}
		else {
			$this->nok('Store value');
			$this->assertNotVisible('delta');
		}

		if ($value_type != 'Log' && $value_type != 'Text') {
			$this->ok(array('Show value', 'show value mappings'));
			$this->assertVisible('valuemapid');
			$this->assertElementPresent("//select[@id='valuemapid']/option[text()='As is']");
			$result = DBselect('SELECT name FROM valuemaps');
			while ($row = DBfetch($result)) {
				$this->assertElementPresent("//select[@id='valuemapid']/option[text()='".$row['name']."']");
			}
			$this->assertAttribute("//*[@id='valuemapid']/option[text()='As is']/@selected", 'selected');
		}
		else {
			$this->nok(array('Show value', 'show value mappings'));
			$this->assertNotVisible('valuemapid');
		}

		if ($type == 'Zabbix trapper') {
			$this->ok('Allowed hosts');
			$this->assertVisible('trapper_hosts');
			$this->assertAttribute("//input[@id='trapper_hosts']/@maxlength", '255');
			$this->assertAttribute("//input[@id='trapper_hosts']/@size", '50');
		}
		else {
			$this->nok('Allowed hosts');
			$this->assertNotVisible('trapper_hosts');
		}

		if ($value_type == 'Log') {
			$this->ok('Log time format');
			$this->assertVisible('logtimefmt');
			$this->assertAttribute("//input[@id='logtimefmt']/@maxlength", '64');
			$this->assertAttribute("//input[@id='logtimefmt']/@size", '25');
		}
		else {
			$this->nok('Log time format');
			$this->assertNotVisible('logtimefmt');
		}

		$this->ok('New application');
		$this->assertVisible('new_application');
		$this->assertAttribute("//input[@id='new_application']/@maxlength", '255');
		$this->assertAttribute("//input[@id='new_application']/@size", '50');

		$this->ok('Applications');
		$this->assertVisible('applications_');
		$this->assertElementPresent("//select[@id='applications_']/option[text()='-None-']");
		$result = DBselect("SELECT name FROM applications WHERE hostid='.$hostid.'");
		while ($row = DBfetch($result)) {
			$this->assertElementPresent("//select[@id='applications_']/option[text()='".$row['name']."']");
		}
		$this->assertAttribute("//*[@id='applications_']/option[text()='-None-']/@selected", 'selected');

		if ($value_type != 'Log') {
			$this->ok('Populates host inventory field');
			$this->assertVisible('inventory_link');
			$this->zbxDropdownHasOptions('inventory_link', array(
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
			));
			$this->assertAttribute("//*[@id='inventory_link']/option[text()='-None-']/@selected", 'selected');
		}

		$this->ok('Description');
		$this->assertVisible('description');
		$this->assertAttribute("//textarea[@id='description']/@rows", '7');

		$this->ok('Status');
		$this->assertVisible('status');
		$this->zbxDropdownHasOptions('status', array(
			'Enabled',
			'Disabled',
			'Not supported'
		));
		$this->assertAttribute("//*[@id='status']/option[text()='Enabled']/@selected", 'selected');
	}

	// Returns all possible item data
	public static function dataCreate() {
		return array(
			array(
				array(
					'expected' => ITEM_GOOD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Checksum of $1',
					'key' => 'vfs.file.cksum[/sbin/shutdown]'
				)
			),
			// Duplicate item
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'key' =>'item-name-missing',
					'errors' => array(
						'Page received incorrect data',
						'Warning. Incorrect value for field "Name": cannot be empty.'
					)
				)
			),
			// Item key is missing
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item name',
					'errors' => array(
						'Page received incorrect data',
						'Warning. Incorrect value for field "Key": cannot be empty.'
					)
				)
			),
			// Empty formula
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => '',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Field "Custom multiplier" is not decimal number.'
					)
				)
			),
			// Incorrect formula
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => 'formula',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Field "Custom multiplier" is not decimal number.'
					)
				)
			),
			// Incorrect formula
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => 'a1b2c3',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Field "Custom multiplier" is not decimal number.'
					)
				)
			),
			// Incorrect formula
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item formula',
					'key' => 'item-formula-test',
					'formula' => '321abc',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Field "Custom multiplier" is not decimal number.'
					)
				)
			),
			// Empty timedelay
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item delay',
					'key' => 'item-delay-test',
					'delay' => '-30',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Update interval (in sec)": must be between 0 and 86400.'
					)
				)
			),
			// Incorrect timedelay
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item delay',
					'key' => 'item-delay-test',
					'delay' => 86401,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Update interval (in sec)": must be between 0 and 86400.'
					)
				)
			),
			// Empty time flex period
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item flex',
					'key' => 'item-flex-test',
					'flexPeriod' => array(
						array('flexDelay' => '', 'flexTime' => '', 'instantCheck' => true)
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "New flexible interval": cannot be empty.'
					)
				)
			),
			// Incorrect flex period
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item flex',
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item flex',
					'key' => 'item-flex-delay2',
					'delay' => 0,
					'flexPeriod' => array(
						array('flexTime' => '1-5,00:00-24:00'),
						array('flexTime' => '6-7,00:00-24:00')
					)
				)
			),
			// Delay combined with flex periods
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
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
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item flex',
					'key' => 'item-flex-delay8',
					'flexPeriod' => array(
						array('flexDelay' => 0, 'flexTime' => '1-5,00:00-24:00', 'remove' => true),
						array('flexDelay' => 0, 'flexTime' => '6-7,00:00-24:00', 'remove' => true),
						array('flexTime' => '1-5,00:00-24:00'),
						array('flexTime' => '6-7,00:00-24:00')
					)
				)
			),
			// History
			array(
				array(
					'expected' => ITEM_GOOD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item history',
					'key' => 'item-history-empty',
					'history' => ''
				)
			),
			// History
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item history',
					'key' => 'item-history-test',
					'history' => 65536,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Keep history (in days)": must be between 0 and 65535.'
					)
				)
			),
			// History
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item history',
					'key' => 'item-history-test',
					'history' => '-1',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Warning. Incorrect value for field "Keep history (in days)": must be between 0 and 65535.'
					)
				)
			),
			// History
			array(
				array(
					'expected' => ITEM_GOOD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item history',
					'key' => 'item-history-test',
					'history' => 'days'
				)
			),
			// Trends
			array(
				array(
					'expected' => ITEM_GOOD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item trends',
					'key' => 'item-trends-empty',
					'trends' => ''
				)
			),
			// Trends
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item trends',
					'key' => 'item-trends-test',
					'trends' => '-1',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Warning. Incorrect value for field "Keep trends (in days)": must be between 0 and 65535.'
					)
				)
			),
			// Trends
			array(
				array(
					'expected' => ITEM_BAD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item trends',
					'key' => 'item-trends-test',
					'trends' => 65536,
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Warning. Incorrect value for field "Keep trends (in days)": must be between 0 and 65535.'
					)
				)
			),
			// Trends
			array(
				array(
					'expected' => ITEM_GOOD,
					'host' => 'ЗАББИКС Сервер',
					'type' => ITEM_TYPE_ZABBIX,
					'name' => 'Item trends',
					'key' => 'item-trends-test',
					'trends' => 'trends'
				)
			)
		);
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormItem_Create($data) {
		$this->zbxTestLogin('hosts.php');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('CONFIGURATION OF HOSTS');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('CONFIGURATION OF HOSTS');

		$host=$data['host'];
		$row = DBfetch(DBselect("select hostid from hosts where name='$host'"));
		$hostid = $row['hostid'];

		$this->href_click("items.php?filter_set=1&hostid=$hostid&sid=");
		$this->wait();

		$this->checkTitle('Configuration of items');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');

		$this->zbxTestClickWait('form');
		$this->checkTitle('Configuration of items');

		if (isset($data['name'])) {
			$this->input_type('name', $data['name']);
		}

		if (isset($data['key'])) {
			$this->input_type('key', $data['key']);
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
			foreach ($data['flexPeriod'] as $period) {
				$this->input_type('new_delay_flex_period', $period['flexTime']);

				if (isset($period['flexDelay'])) {
					$this->input_type('new_delay_flex_delay', $period['flexDelay']);
				}
				$this->zbxTestClickWait('add_delay_flex');
			}
			if ($flexDelay==null) {
					foreach ($errorMsgs as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				if (isset($period['remove'])) {
					$this->button_click('remove');
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
				case ITEM_GOOD:
					$this->zbxTestTextPresent('Item added');
					$this->checkTitle('Configuration of items');
					$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
					break;

				case ITEM_BAD:
					$this->checkTitle('Configuration of items');
					$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
					foreach ($data['errors'] as $msg) {
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent('Host');
					$this->zbxTestTextPresent('Name');
					$this->zbxTestTextPresent('Key');
					if (isset($data['formula'])) {
						$formulaValue = $this->getValue('formula');
						$this->assertEquals($data['formula'], $formulaValue);
					}
					break;
			}
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormItem_teardown() {
		DBrestore_tables('items');
	}
}
