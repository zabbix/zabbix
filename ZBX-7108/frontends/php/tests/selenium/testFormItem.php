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
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormItem_setup() {
		DBsave_tables('items');
	}

	/**
	 * @dataProvider itemTypes
	 */
	public function testFormItem_CheckLayout($itemTypeID, $itemType ) {

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
		$this->assertElementPresent('hostname');
		// this check will fail in case of incorrect maxlength value for this "host" element!!!
////TODO	$this->assertAttribute("//input[@id='hostname']/@maxlength", '64');

		$this->assertElementPresent('btn_host');

		$this->zbxTestDropdownSelect('type', $itemType);

		$this->zbxTestTextPresent('Name');
		$this->assertElementPresent('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", '255');

		$this->zbxTestTextPresent('Key');
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
		// Ok/bad, visible host name, type, name, key, formula, delay, flex period, flex time, history, trends, errors
		return array(
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Checksum of $1',
				'vfs.file.cksum[/sbin/shutdown]',
				null,
				null,
				array(),
				null,
				null,
				null,
				array()
			),
			// Duplicate item
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Checksum of $1',
				'vfs.file.cksum[/sbin/shutdown]',
				null,
				null,
				array(),
				null,
				null,
				null,
				array(
						'ERROR: Cannot add item',
						'Item with key "vfs.file.cksum[/sbin/shutdown]" already exists on'
					)
			),
			// Item name is missing
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'',
				'item-name-missing',
				null,
				null,
				array(),
				null,
				null,
				null,
				array(
						'Page received incorrect data',
						'Warning. Incorrect value for field "Name": cannot be empty.'
					)
			),
			// Item key is missing
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item name',
				'',
				null,
				null,
				array(),
				null,
				null,
				null,
				array(
						'Page received incorrect data',
						'Warning. Incorrect value for field "Key": cannot be empty.'
					)
			),
			// Empty formula
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item formula',
				'item-formula-test',
				' ',
				null,
				array(),
				null,
				null,
				null,
				array(
						'ERROR: Page received incorrect data',
						'Warning. Field "formula" is not decimal number.'
					)
			),
			// Incorrect formula
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item formula',
				'item-formula-test',
				'formula',
				null,
				array(),
				null,
				null,
				null,
				array(
						'ERROR: Page received incorrect data',
						'Warning. Field "formula" is not decimal number.'
					)
			),
			// Incorrect formula
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item formula',
				'item-formula-test',
				'a1b2c3',
				null,
				array(),
				null,
				null,
				null,
				array(
						'ERROR: Page received incorrect data',
						'Warning. Field "formula" is not decimal number.'
					)
			),
			// Incorrect formula
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item formula',
				'item-formula-test',
				'321abc',
				null,
				array(),
				null,
				null,
				null,
				array(
						'ERROR: Page received incorrect data',
						'Warning. Field "formula" is not decimal number.'
					)
			),
			// Empty timedelay
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item delay',
				'item-delay-test',
				null,
				'0',
				array(),
				null,
				null,
				null,
				array(
						'ERROR: Cannot add item',
						'Item will not be refreshed. Please enter a correct update interval.'
					)
			),
			// Incorrect timedelay
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item delay',
				'item-delay-test',
				null,
				'-30',
				array(),
				null,
				null,
				null,
				array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Update interval (in sec)": must be between 0 and 86400.'
					)
			),
			// Incorrect timedelay
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item delay',
				'item-delay-test',
				null,
				'86401',
				array(),
				null,
				null,
				null,
				array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Update interval (in sec)": must be between 0 and 86400.'
					)
			),
			// Empty time flex period
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-test',
				null,
				null,
				array(''),
				null,
				null,
				null,
				array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "New flexible interval": cannot be empty.'
					)
			),
			// Incorrect flex period
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-test',
				null,
				null,
				array('1-11,00:00-24:00'),
				null,
				null,
				null,
				array(
						'ERROR: Invalid time period',
						'Incorrect time period "1-11,00:00-24:00".'
					)
			),
			// Incorrect flex period
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-test',
				null,
				null,
				array('1-7,00:00-25:00'),
				null,
				null,
				null,
				array(
						'ERROR: Invalid time period',
						'Incorrect time period "1-7,00:00-25:00".'
					)
			),
			// Incorrect flex period
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-test',
				null,
				null,
				array('1-7,24:00-00:00'),
				null,
				null,
				null,
				array(
						'ERROR: Invalid time period',
						'Incorrect time period "1-7,24:00-00:00" start time must be less than end time.'
					)
			),
			// Incorrect flex period
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-test',
				null,
				null,
				array('1,00:00-24:00;2,00:00-24:00'),
				null,
				null,
				null,
				array(
						'ERROR: Invalid time period',
						'Incorrect time period "1,00:00-24:00;2,00:00-24:00".'
					)
			),
			// Multiple flex periods
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-test',
				null,
				null,
				array('1,00:00-24:00', '2,00:00-24:00', '1,00:00-24:00', '2,00:00-24:00'),
				null,
				null,
				null,
				array()
			),
			// Delay combined with flex periods
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-delay',
				null,
				'60',
				array(
						'1,00:00-24:00',
						'2,00:00-24:00',
						'3,00:00-24:00',
						'4,00:00-24:00',
						'5,00:00-24:00',
						'6,00:00-24:00',
						'7,00:00-24:00'
					),
				'0',
				null,
				null,
				array('ERROR: Cannot add item', 'Item will not be refreshed. Please enter a correct update interval.')
			),
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-delay1',
				null,
				'60',
				array(
						'1,00:00-24:00',
						'2,00:00-24:00',
						'3,00:00-24:00',
						'4,00:00-24:00',
						'5,00:00-24:00',
						'6,00:00-24:00',
						'7,00:00-24:00'
					),
				'60',
				null,
				null,
				array()
			),
			// Delay combined with flex periods
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-delay',
				null,
				'0',
				array(
						'1,00:00-24:00',
						'2,00:00-24:00',
						'3,00:00-24:00',
						'4,00:00-24:00',
						'5,00:00-24:00',
						'6,00:00-24:00',
						'7,00:00-24:00'
					),
				'0',
				null,
				null,
				array('ERROR: Cannot add item', 'Item will not be refreshed. Please enter a correct update interval.')
			),
			// Delay combined with flex periods
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-delay2',
				null,
				'0',
				array('1-5,00:00-24:00', '6-7,00:00-24:00'),
				'60',
				null,
				null,
				array()
			),
			// Delay combined with flex periods
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-delay',
				null,
				'60',
				array('1-5,00:00-24:00', '6-7,00:00-24:00'),
				'0',
				null,
				null,
				array('ERROR: Cannot add item', 'Item will not be refreshed. Please enter a correct update interval.')
			),
			// Delay combined with flex periods
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-delay3',
				null,
				'60',
				array('1-5,00:00-24:00', '6-7,00:00-24:00'),
				'60',
				null,
				null,
				array()
			),
			// Delay combined with flex periods
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-delay4',
				null,
				'0',
				array('1-7,00:00-24:00'	),
				'60',
				null,
				null,
				array()
			),
			// Delay combined with flex periods
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-delay',
				null,
				'60',
				array('1-7,00:00-24:00'	),
				'0',
				null,
				null,
				array('ERROR: Cannot add item', 'Item will not be refreshed. Please enter a correct update interval.')
			),
			// Delay combined with flex periods
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item flex',
				'item-flex-delay5',
				null,
				'60',
				array('1-7,00:00-24:00'	),
				'60',
				null,
				null,
				array()
			),
			// History
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item history',
				'item-history-empty',
				null,
				null,
				array(),
				null,
				'',
				null,
				array()
			),
			// History
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item history',
				'item-history-test',
				null,
				null,
				array(),
				null,
				'65536',
				null,
				array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Keep history (in days)": must be between 0 and 65535.'
					)
			),
			// History
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item history',
				'item-history-test',
				null,
				null,
				array(),
				null,
				'-1',
				null,
				array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Keep history (in days)": must be between 0 and 65535.'
					)
			),
			// History
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item history',
				'item-history-test',
				null,
				null,
				array(),
				null,
				'days',
				null,
				array()
			),
			// Trends
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item trends',
				'item-trends-empty',
				null,
				null,
				array(),
				null,
				null,
				'',
				array()
			),
			// Trends
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item trends',
				'item-trends-test',
				null,
				null,
				array(),
				null,
				null,
				'-1',
				array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Keep trends (in days)": must be between 0 and 65535.'
					)
			),
			// Trends
			array(
				ITEM_BAD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item trends',
				'item-trends-test',
				null,
				null,
				array(),
				null,
				null,
				'65536',
				array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Keep trends (in days)": must be between 0 and 65535.'
					)
			),
			// Trends
			array(
				ITEM_GOOD,
				'ЗАББИКС Сервер',
				ITEM_TYPE_ZABBIX,
				'Item trends',
				'item-trends-test',
				null,
				null,
				array(),
				null,
				null,
				'trends',
				array()
			)
		);
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormItem_Create($expected, $visibleHostname, $type, $name, $key, $formula, $delay,
				$flexPeriod, $flexDelay, $history, $trends, $errorMsgs) {
		$this->zbxTestLogin('hosts.php');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('CONFIGURATION OF HOSTS');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('CONFIGURATION OF HOSTS');


		$row = DBfetch(DBselect("select hostid from hosts where name='$visibleHostname'"));
		$hostid = $row['hostid'];

		$this->href_click("items.php?filter_set=1&hostid=$hostid&sid=");
		$this->wait();

		$this->checkTitle('Configuration of items');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');

		$this->zbxTestClickWait('form');
		$this->checkTitle('Configuration of items');

		$this->input_type('name', $name);
		$this->input_type('key', $key);

		if ($formula!=null)	{
			$this->zbxTestCheckboxSelect('multiplier');
			$this->input_type('formula', $formula);
		}

		if ($delay!=null)	{
			$this->input_type('delay',$delay);
		}

		if ($flexPeriod!=null)	{
			foreach ($flexPeriod as $period) {
				$this->input_type('new_delay_flex_period', $period);

				if ($flexDelay!=null) {
					$this->input_type('new_delay_flex_delay', $flexDelay);
				}
				$this->zbxTestClickWait('add_delay_flex');
			}
			if ($flexDelay==null) {
					foreach ($errorMsgs as $msg) {
					$this->zbxTestTextPresent($msg);
				}
			}
		}

		if ($history!=null)	{
			$this->input_type('history',$history);
		}

		if ($trends!=null)	{
			$this->input_type('trends',$trends);
		}

		if (($flexPeriod==null) || ($flexDelay!=null)) {
			$this->zbxTestClickWait('save');
			switch ($expected) {
				case ITEM_GOOD:
					$this->zbxTestTextPresent('Item added');
					$this->checkTitle('Configuration of items');
					$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
					break;

				case ITEM_BAD:
					$this->checkTitle('Configuration of items');
					$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
					foreach ($errorMsgs as $msg) {
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent('Host');
					$this->zbxTestTextPresent('Name');
					$this->zbxTestTextPresent('Key');
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
