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

define('ITEM_GOOD', 0);
define('ITEM_BAD', 1);

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testInheritanceItemPrototype extends CWebTest {

	/**
	 * The name of the test template created in the test data set.
	 *
	 * @var string
	 */
	protected $template = 'Inheritance test template';

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Template inheritance test host';

	/**
	 * The name of the test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRule = 'testInheritanceDiscoveryRule';

	/**
	 * The id of the templated test host created in the test data set.
	 *
	 * @var string
	 */
	protected $templateid = 30000;

	/**
	 * The id of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $hostid = 30001;

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testInheritanceItemPrototype_Setup() {
		DBsave_tables('items');
	}

	// Returns update data
	public static function update() {
		return DBdata("select * from items where hostid = 30000 and key_ LIKE 'item-prototype-test%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceItemPrototype_SimpleUpdate($data) {
		$name = $data['name'];

		$sqlItems = "select itemid, hostid, name, key_, delay from items order by itemid";
		$oldHashItems = DBhash($sqlItems);

		$this->zbxTestLogin('templates.php');
		$this->zbxTestClickWait('link='.$this->template);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link='.$this->discoveryRule);
		$this->zbxTestClickWait('link=Item prototypes');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent(array('Item updated', "$name", 'CONFIGURATION OF ITEM PROTOTYPES', 'Item prototypes of '.$this->discoveryRule));

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
					'hostCheck' =>true
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
						'Warning. Incorrect value for field "Name": cannot be empty.'
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
						'Warning. Incorrect value for field "Key": cannot be empty.'
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
						'Warning. Field "Custom multiplier" is not decimal number.'
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
						'Warning. Field "Custom multiplier" is not decimal number.'
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
						'Warning. Field "Custom multiplier" is not decimal number.'
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
						'Warning. Field "Custom multiplier" is not decimal number.'
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
						'Warning. Field "Custom multiplier" is not decimal number.'
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
						'Warning. Field "Custom multiplier" is not decimal number.'
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
						'Warning. Field "Custom multiplier" is not decimal number.'
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
						'Warning. Incorrect value for field "Update interval (in sec)": must be between 0 and 86400.'
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
						'Warning. Incorrect value for field "Update interval (in sec)": must be between 0 and 86400.'
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
						'Warning. Incorrect value for field "New flexible interval": cannot be empty.'
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
					'hostCheck' => true
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
					'hostCheck' => true
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
						'Warning. Incorrect value for field "Keep history (in days)": must be between 0 and 65535.'
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
							'Warning. Incorrect value for field "Keep history (in days)": must be between 0 and 65535.'
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
					'hostCheck' => true
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
							'Warning. Incorrect value for field "Keep trends (in days)": must be between 0 and 65535.'
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
							'Warning. Incorrect value for field "Keep trends (in days)": must be between 0 and 65535.'
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
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => '!@#$%^&*()_+-=[]{};:"|,./<>?',
					'key' => 'item-symbols-test',
					'dbCheck' => true,
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'itemSimple',
					'key' => 'key-template-simple',
					'hostCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'itemName',
					'key' => 'key-template-item',
					'hostCheck' => true)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'itemTrigger',
					'key' => 'key-template-trigger',
					'hostCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => ITEM_GOOD,
					'name' => 'itemRemove',
					'key' => 'key-template-remove',
					'hostCheck' => true,
					'dbCheck' => true,
					'hostRemove' => true,
					'remove' => true)
			),
			array(
				array(
					'expected' => ITEM_BAD,
					'name' => 'itemInheritance',
					'key' => 'key-item-inheritance',
					'errors' => array(
						'ERROR: Cannot add item',
						'Item with key "key-item-inheritance" already exists on "Inheritance test template".')
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
							'Warning. Incorrect value for field "IPMI sensor": cannot be empty.'
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
							'Warning. Incorrect value for field "User name": cannot be empty.',
							'Warning. Incorrect value for field "Executed script": cannot be empty.'
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
							'Warning. Incorrect value for field "User name": cannot be empty.',
							'Warning. Incorrect value for field "Executed script": cannot be empty.'
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
					'formCheck' => true
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
					'formCheck' => true
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
							'Warning. Incorrect value for field "Formula": cannot be empty.'
					)
				)
			),
			// Default
			array(
				array(
					'expected' => ITEM_BAD,
					'type' => 'Database monitor',
					'name' => 'Database monitor',
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
	public function testInheritanceItemPrototype_SimpleCreate($data) {
		$this->zbxTestLogin('templates.php');

		if (isset($data['name'])) {
			$itemName = $data['name'];
		}
		if (isset($data['key'])) {
			$keyName = $data['key'];
		}

		$this->zbxTestClickWait('link='.$this->template);
		$this->zbxTestClickWait("link=Discovery rules");
		$this->zbxTestClickWait('link='.$this->discoveryRule);
		$this->zbxTestClickWait("link=Item prototypes");
		$this->zbxTestClickWait('form');

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
			$this->input_type('ipmi_sensor', $data['ipmi_sensor']);
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
			foreach ($data['flexPeriod'] as $period) {
				$this->input_type('new_delay_flex_period', $period['flexTime']);

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
				if (isset($period['remove'])) {
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

		if (isset($data['hostCheck'])) {
			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait("link=Discovery rules");
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait("link=Item prototypes");


			if (isset ($data['dbName'])) {
				$itemNameDB = $data['dbName'];
				$this->zbxTestTextPresent($this->template.": $itemNameDB");
				$this->zbxTestClickWait("link=$itemNameDB");
			}
			else {
				$this->zbxTestTextPresent($this->template.": $itemName");
				$this->zbxTestClickWait("link=$itemName");
			}

			$this->zbxTestTextPresent('Parent items');
			$this->assertElementPresent('link='.$this->template);
			$this->assertElementValue('name', $itemName);
			$this->assertElementValue('key', $keyName);
		}

		if (isset($data['dbCheck'])) {
			// template
			$result = DBselect("SELECT name, key_, hostid FROM items where name = '".$itemName."' and hostid = ".$this->templateid);
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $itemName);
				$this->assertEquals($row['key_'], $keyName);
			}
			// host
			$result = DBselect("SELECT name, key_ FROM items where name = '".$itemName."'  AND hostid = ".$this->hostid);
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $itemName);
				$this->assertEquals($row['key_'], $keyName);
			}
		}

		if (isset($data['hostRemove'])) {
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
			$this->zbxTestTextPresent(array('ERROR: Cannot delete items', 'Cannot delete templated items'));
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT itemid FROM items where name = '".$itemName."' and hostid = ".$this->templateid);
			while ($row = DBfetch($result)) {
				$itemId = $row['itemid'];
			}

			$this->zbxTestOpenWait('templates.php');
			$this->zbxTestClickWait('link='.$this->template);
			$this->zbxTestClickWait("link=Discovery rules");
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait("link=Item prototypes");

			$this->zbxTestCheckboxSelect("group_itemid_$itemId");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent('Items deleted');
			$this->zbxTestTextNotPresent($this->template.": $itemName");
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testInheritanceItemPrototype_Teardown() {
		DBrestore_tables('items');
	}
}
