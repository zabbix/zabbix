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

	public function testFormItem_CheckLayout() {

		$this->login('items.php');
		$this->assertTitle('Configuration of items');
		$this->ok('CONFIGURATION OF ITEMS');

		$this->button_click('form');
		$this->wait();
		$this->assertTitle('Configuration of items');
		// Labels
		$this->ok(
			array(
				'Host',
				'Name',
				'Key',
				'Type',
				'Host interface',
				'Type of information',
				'Data type',
				'Units',
				'Use custom multiplier',
				'Update interval (in sec)',
				'Flexible intervals (sec)',
				'Interval',
				'Period',
				'Action',
				'No flexible intervals defined.',
				'New flexible interval',
				'Period',
				'Keep history (in days)',
				'Keep trends (in days)',
				'Store value',
				'Show value',
				'show value mappings',
				'New application',
				'Applications',
				'Populates host inventory field',
				'Description',
				'Status'
			)
		);

		$this->assertElementPresent('hostname');
		// this check will fail in case of incorrect maxlength value for this "host" element!!!
		$this->assertAttribute("//input[@id='hostname']/@maxlength", '64');

		$this->assertElementPresent('btn_host');

		$this->assertElementPresent('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", '255');

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

		$this->assertElementPresent('interfaceid');

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

		$this->assertElementPresent('delay');
		$this->assertAttribute("//input[@id='delay']/@maxlength", '5');

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
		// Ok/bad, visible host name, name, key, errors
		return array(
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				'Checksum of $1',
				'vfs.file.cksum[/sbin/shutdown]',
				array()
			),
			// Duplicate item
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				'Checksum of $1',
				'vfs.file.cksum[/sbin/shutdown]',
				array('Cannot add item', 'Item with key "vfs.file.cksum[/sbin/shutdown]" already exists on given host.')
			),
			// Item name is missing
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				'',
				'agent.ping123',
				array('Page received incorrect data.', 'Warning. Incorrect value for field "name".')
			),
			// Item key is missing
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				'Item name',
				'',
				array('Page received incorrect data.', 'Warning. Incorrect value for field "key".')
			)
		);
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormItem_Create($expected, $visibleHostname, $name, $key, $errorMsgs) {
		$this->login('hosts.php');
		$this->assertTitle('Hosts');
		$this->ok('HOSTS');
		$this->dropdown_select_wait('groupid', 'all');
		$this->assertTitle('Hosts');
		$this->ok('HOSTS');


		$row = DBfetch(DBselect("select hostid from hosts where name='$visibleHostname'"));
		$hostid = $row['hostid'];

		$this->href_click("items.php?filter_set=1&hostid=$hostid&sid=");
		$this->wait();

		$this->assertTitle('Configuration of items');
		$this->ok('CONFIGURATION OF ITEMS');

		$this->button_click('form');
		$this->wait();
		$this->assertTitle('Configuration of items');

		$this->input_type('name', $name);
		$this->input_type('key', $key);

		$this->button_click('save');
		$this->wait();
		switch ($expected) {
			case ITEM_GOOD:
				$this->ok('Item added');
				$this->assertTitle('Configuration of items');
				$this->ok('CONFIGURATION OF ITEMS');
				break;

			case ITEM_BAD:
				$this->assertTitle('Configuration of items');
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
