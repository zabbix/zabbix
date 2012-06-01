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
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

define('ITEM_GOOD', 0);
define('ITEM_BAD', 1);

class testFormItem extends CWebTest {

	// Returns all possible item types
	public static function itemTypes() {
		return array(
			array(ITEM_TYPE_ZABBIX, 'Zabbix agent'),
			array(ITEM_TYPE_SNMPV1, 'SNMPv1 agent'),
			array(ITEM_TYPE_TRAPPER, 'Zabbix trapper'),
			array(ITEM_TYPE_SIMPLE, 'Simple check'),
			array(ITEM_TYPE_SNMPV2C, 'SNMPv2 agent'),
			array(ITEM_TYPE_INTERNAL, 'Zabbix internal'),
			array(ITEM_TYPE_SNMPV3, 'SNMPv3 agent'),
			array(ITEM_TYPE_ZABBIX_ACTIVE, 'Zabbix agent (active)'),
			array(ITEM_TYPE_AGGREGATE, 'Zabbix aggregate'),
			array(ITEM_TYPE_EXTERNAL, 'External check'),
			array(ITEM_TYPE_DB_MONITOR, 'Database monitor'),
			array(ITEM_TYPE_IPMI, 'IPMI agent'),
			array(ITEM_TYPE_SSH, 'SSH agent'),
			array(ITEM_TYPE_TELNET, 'TELNET agent'),
			array(ITEM_TYPE_CALCULATED, 'Calculated'),
			array(ITEM_TYPE_JMX, 'JMX agent')
		);
	}

	/**
	 * @dataProvider itemTypes
	 */
	public function testFormItem_CheckLayout($itemTypeID, $itemType ) {

		$this->login('items.php');
		$this->checkTitle('Configuration of items');
		$this->ok('CONFIGURATION OF ITEMS');

		$this->button_click('form');
		$this->wait();
		$this->checkTitle('Configuration of items');

		$this->ok('Host interface');
		$this->ok('Type of information');
		$this->ok('Data type');
		$this->ok('Units');
		$this->ok('Use custom multiplier');
		$this->ok('Update interval (in sec)');
		$this->ok('Flexible intervals');
		$this->ok('Interval');
		$this->ok('Period');
		$this->ok('Action');
		$this->ok('No flexible intervals defined.');
		// $this->ok('New flexible interval');
		$this->ok('Update interval (in sec)');
		$this->ok('Period');
		$this->ok('Keep history (in days)');
		$this->ok('Keep trends (in days)');
		$this->ok('Store value');
		$this->ok('Show value');
		$this->ok('show value mappings');
		$this->ok('New application');
		$this->ok('Applications');
		$this->ok('Populates host inventory field');
		$this->ok('Description');
		$this->ok('Status');

		$this->ok('Host');
		$this->assertElementPresent('hostname');
		// this check will fail in case of incorrect maxlength value for this "host" element!!!
////TODO	$this->assertAttribute("//input[@id='hostname']/@maxlength", '64');

		$this->assertElementPresent('btn_host');

		$this->dropdown_select('type', $itemType);

		$this->ok('Name');
		$this->assertElementPresent('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", '255');

		$this->ok('Key');
		$this->assertElementPresent('key');
		$this->assertAttribute("//input[@id='key']/@maxlength", '255');

		$this->assertElementPresent('type');
		$this->assertElementPresent("//select[@id='type']/option[text()='Zabbix agent']");
		$this->assertElementPresent("//select[@id='type']/option[text()='Zabbix agent (active)']");
		$this->assertElementPresent("//select[@id='type']/option[text()='Simple check']");
		$this->assertElementPresent("//select[@id='type']/option[text()='SNMPv1 agent']");
		$this->assertElementPresent("//select[@id='type']/option[text()='SNMPv2 agent']");
		$this->assertElementPresent("//select[@id='type']/option[text()='SNMPv3 agent']");
		$this->assertElementPresent("//select[@id='type']/option[text()='SNMP trap']");
		$this->assertElementPresent("//select[@id='type']/option[text()='Zabbix internal']");
		$this->assertElementPresent("//select[@id='type']/option[text()='Zabbix trapper']");
		$this->assertElementPresent("//select[@id='type']/option[text()='Zabbix aggregate']");
		$this->assertElementPresent("//select[@id='type']/option[text()='External check']");
		$this->assertElementPresent("//select[@id='type']/option[text()='Database monitor']");
		$this->assertElementPresent("//select[@id='type']/option[text()='IPMI agent']");
		$this->assertElementPresent("//select[@id='type']/option[text()='SSH agent']");
		$this->assertElementPresent("//select[@id='type']/option[text()='TELNET agent']");
		$this->assertElementPresent("//select[@id='type']/option[text()='JMX agent']");
		$this->assertElementPresent("//select[@id='type']/option[text()='Calculated']");

		if (in_array($itemTypeID, array(ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_INTERNAL, ITEM_TYPE_TRAPPER,
						ITEM_TYPE_AGGREGATE, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_CALCULATED))) {
			$this->assertNotVisible('interfaceid');
		} else {
			$this->assertVisible('interfaceid');
		}

		$this->assertElementPresent('value_type');
		$this->assertElementPresent("//select[@id='value_type']/option[text()='Numeric (unsigned)']");
		$this->assertElementPresent("//select[@id='value_type']/option[text()='Numeric (float)']");
		$this->assertElementPresent("//select[@id='value_type']/option[text()='Character']");
		$this->assertElementPresent("//select[@id='value_type']/option[text()='Log']");
		$this->assertElementPresent("//select[@id='value_type']/option[text()='Text']");

		$this->assertElementPresent('data_type');
		$this->assertElementPresent("//select[@id='data_type']/option[text()='Boolean']");
		$this->assertElementPresent("//select[@id='data_type']/option[text()='Octal']");
		$this->assertElementPresent("//select[@id='data_type']/option[text()='Decimal']");
		$this->assertElementPresent("//select[@id='data_type']/option[text()='Hexadecimal']");

		$this->assertElementPresent('units');
		$this->assertAttribute("//input[@id='units']/@maxlength", '255');

		$this->assertElementPresent('multiplier');

		if (in_array($itemTypeID, array(ITEM_TYPE_TRAPPER))) {
			$this->assertNotVisible('delay');
		} else {
			$this->assertVisible('delay');
			$this->assertAttribute("//input[@id='delay']/@maxlength", '5');
		}

		$this->assertElementPresent('new_delay_flex_delay');

		$this->assertElementPresent('history');

		$this->assertElementPresent('trends');

		$this->assertElementPresent('delta');
		$this->assertElementPresent("//select[@id='delta']/option[text()='As is']");
		$this->assertElementPresent("//select[@id='delta']/option[text()='Delta (speed per second)']");
		$this->assertElementPresent("//select[@id='delta']/option[text()='Delta (simple change)']");

		$this->assertElementPresent('valuemapid');
		$result = DBselect('select * from valuemaps');
		while ($row = DBfetch($result)) {
			$this->assertElementPresent("//select[@id='valuemapid']/option[text()='".$row['name']."']");
		}

		$this->assertElementPresent('new_application');
		$this->assertAttribute("//input[@id='new_application']/@maxlength", '255');

		$this->assertElementPresent('applications_');
//		$result = DBselect('select * from valuemaps');
//		while ($row = DBfetch($result)) {
//			$this->assertElementPresent("//select[@id='valuemapid']/option[text()='".$row['name']."']");
//		}

		$this->assertElementPresent('inventory_link');

		$this->assertElementPresent('description');

		$this->assertElementPresent('status');
		$this->assertElementPresent("//select[@id='status']/option[text()='Enabled']");
		$this->assertElementPresent("//select[@id='status']/option[text()='Disabled']");
		$this->assertElementPresent("//select[@id='status']/option[text()='Not supported']");
	}

	// Returns all possible item data
	public static function dataCreate() {
		// Ok/bad, visible host name, name, type, key, errors
		return array(
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				'Checksum of $1',
				ITEM_TYPE_ZABBIX,
				'vfs.file.cksum[/sbin/shutdown]',
				array()
			),
			// Duplicate item
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				'Checksum of $1',
				ITEM_TYPE_ZABBIX,
				'vfs.file.cksum[/sbin/shutdown]',
				array('ERROR: Cannot add item', 'Item with key "vfs.file.cksum[/sbin/shutdown]" already exists on')
			),
			// Item name is missing
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				'',
				ITEM_TYPE_ZABBIX,
				'agent.ping123',
				array('Page received incorrect data', 'Warning. Incorrect value for field "Name": cannot be empty.')
			),
			// Item key is missing
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item name',
				'',
				array('Page received incorrect data', 'Warning. Incorrect value for field "Key": cannot be empty.')
			)
		);
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormItem_Create($expected, $visibleHostname, $name, $type, $key, $errorMsgs) {
		$this->login('hosts.php');
		$this->checkTitle('Configuration of hosts');
		$this->ok('CONFIGURATION OF HOSTS');
		$this->dropdown_select_wait('groupid', 'all');
		$this->checkTitle('Configuration of hosts');
		$this->ok('CONFIGURATION OF HOSTS');


		$row = DBfetch(DBselect("select hostid from hosts where name='$visibleHostname'"));
		$hostid = $row['hostid'];

		$this->href_click("items.php?filter_set=1&hostid=$hostid&sid=");
		$this->wait();

		$this->checkTitle('Configuration of items');
		$this->ok('CONFIGURATION OF ITEMS');

		$this->button_click('form');
		$this->wait();
		$this->checkTitle('Configuration of items');

		$this->input_type('name', $name);
		$this->input_type('key', $key);

		$this->button_click('save');
		$this->wait();
		switch ($expected) {
			case ITEM_GOOD:
				$this->ok('Item added');
				$this->checkTitle('Configuration of items');
				$this->ok('CONFIGURATION OF ITEMS');
				break;

			case ITEM_BAD:
				$this->checkTitle('Configuration of items');
				$this->ok('CONFIGURATION OF ITEMS');
				foreach ($errorMsgs as $msg) {
					$this->ok($msg);
				}
				$this->ok('Host');
				$this->ok('Name');
				$this->ok('Key');
				break;
		}
	}
}
?>
