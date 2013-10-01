<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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

define('DISCOVERY_GOOD', 0);
define('DISCOVERY_BAD', 1);

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testInheritanceDiscoveryRule extends CWebTest {

	/**
	 * The name of the template created in the test data set.
	 *
	 * @var string
	 */
	protected $template  = 'Inheritance test template';

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Template inheritance test host';

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testInheritanceDiscoveryRule_Setup() {
		DBsave_tables('items');
	}

	// Returns update data
	public static function update() {
		return DBdata("select * from items where hostid = 30000 and key_ LIKE 'discovery-rule-inheritance%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceDiscoveryRule_SimpleUpdate($data) {
		$name = $data['name'];

		$sqlDiscovery = 'select itemid, hostid, name, key_, delay from items order by itemid';
		$oldHashDiscovery = DBhash($sqlDiscovery);

		$this->zbxTestLogin('templates.php');
		$this->zbxTestClickWait('link='.$this->template);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestTextPresent('DISCOVERY RULES');
		$newHashDiscovery = DBhash($sqlDiscovery);

		$this->assertEquals($oldHashDiscovery, $newHashDiscovery);

	}

	// Returns create data
	public static function create() {
		return array(
			array(
				array(
					'expected' => DISCOVERY_BAD,
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "Name": cannot be empty.',
							'Incorrect value for field "Key": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => DISCOVERY_BAD,
					'name' => 'discoveryRuleError',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "Key": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => DISCOVERY_BAD,
					'key' => 'discovery-rule-error',
					'errors' => array(
							'ERROR: Page received incorrect data',
							'Incorrect value for field "Name": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'name' => 'discoveryRuleNo1',
					'key' => 'discovery-key-no1',
					'templateCheck' => true,
					'hostCheck' =>true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'name' => 'discoveryRuleNo2',
					'key' => 'discovery-key-no2',
					'templateCheck' => true,
					'hostCheck' =>true,
					'dbCheck' => true,
					'hostRemove' => true,
					'remove' => true
				)
			),
			array(
				array('expected' => DISCOVERY_BAD,
					'name' => 'discoveryRuleNo1',
					'key' => 'discovery-key-no1',
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item with key "discovery-key-no1" already exists on "Inheritance test template".')
				)
			),
			array(
				array('expected' => DISCOVERY_BAD,
					'name' => 'discoveryRuleError',
					'key' => 'discovery-key-no1',
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item with key "discovery-key-no1" already exists on "Inheritance test template".')
				)
			),
			// Empty timedelay
			array(
				array(
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
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
					'expected' => DISCOVERY_BAD,
					'name' => 'Discovery delay',
					'key' => 'discovery-delay-test',
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_GOOD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_GOOD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_GOOD,
					'name' =>'Discovery flex2',
					'key' =>'discovery-flex-delay2',
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_GOOD,
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
					'expected' => DISCOVERY_GOOD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_GOOD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_GOOD,
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
					'expected' => DISCOVERY_GOOD,
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
					'expected' => DISCOVERY_GOOD,
					'name' =>'Discovery flex Check',
					'key' =>'discovery-flex-delay8',
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
			// Flexfields with negative number in flexdelay
			array(
				array(
					'expected' => DISCOVERY_GOOD,
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
					'expected' => DISCOVERY_GOOD,
					'name' => 'Item flex-symbols in flexdelay',
					'key' => 'item-flex-symbols-flexdelay',
					'flexPeriod' => array(
						array('flexDelay' => '50abc', 'flexTime' => '1-7,00:00-24:00')
					)
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'name' =>'!@#$%^&*()_+-=[]{};:"|,./<>?',
					'key' =>'discovery-symbols-test',
					'dbCheck' => true,
					'hostCheck' => true
				)
			),
			// List of all item types
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'type' => 'Zabbix agent',
					'name' => 'Zabbix agent',
					'key' => 'discovery-zabbix-agent',
					'dbCheck' => true,
					'templateCheck' => true
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'type' => 'Zabbix agent (active)',
					'name' => 'Zabbix agent (active)',
					'key' => 'discovery-zabbix-agent-active',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'type' => 'Simple check',
					'name' => 'Simple check',
					'key' => 'discovery-simple-check',
					'dbCheck' => true,
					'templateCheck' => true
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'type' => 'SNMPv1 agent',
					'name' => 'SNMPv1 agent',
					'key' => 'discovery-snmpv1-agent',
					'dbCheck' => true,
					'templateCheck' => true
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'type' => 'SNMPv2 agent',
					'name' => 'SNMPv2 agent',
					'key' => 'discovery-snmpv2-agent',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'type' => 'SNMPv3 agent',
					'name' => 'SNMPv3 agent',
					'key' => 'discovery-snmpv3-agent',
					'dbCheck' => true,
					'templateCheck' => true
				)
			),
			array(
				array(
					'expected' => DISCOVERY_BAD,
					'type' => 'SNMPv1 agent',
					'name' => 'SNMPv1 agent',
					'key' => 'key-test-inheritance',
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Created: Discovery rule "SNMPv1 agent" on "Inheritance test template".',
						'Item with key "key-test-inheritance" already exists on "Template inheritance test host" as an item.'
					)
				)
			),
			array(
				array(
					'expected' => DISCOVERY_BAD,
					'type' => 'SNMPv1 agent',
					'name' => 'SNMPv1 agent',
					'key' => 'key-item-inheritance',
					'errors' => array(
						'ERROR: Cannot add discovery rule',
						'Item with key "key-item-inheritance" already exists on "Inheritance test template".'
					)
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'type' => 'Zabbix internal',
					'name' => 'Zabbix internal',
					'key' => 'discovery-zabbix-internal',
					'dbCheck' => true,
					'templateCheck' => true
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'type' => 'Zabbix trapper',
					'name' => 'Zabbix trapper',
					'key' => 'snmptrap.fallback',
					'dbCheck' => true,
					'templateCheck' => true,
					'hostCheck' => true,
					'hostRemove' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'type' => 'External check',
					'name' => 'External check',
					'key' => 'discovery-external-check',
					'dbCheck' => true,
					'templateCheck' => true
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
					'type' => 'IPMI agent',
					'name' => 'IPMI agent',
					'key' => 'discovery-ipmi-agent',
					'ipmi_sensor' => 'ipmi_sensor',
					'dbCheck' => true,
					'templateCheck' => true,
					'hostCheck' => true,
					'hostRemove' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => DISCOVERY_GOOD,
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
					'expected' => DISCOVERY_GOOD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_GOOD,
					'type' => 'JMX agent',
					'name' => 'JMX agent',
					'key' => 'discovery-jmx-agent',
					'dbCheck' => true,
					'templateCheck' => true,
					'hostCheck' => true,
					'hostRemove' => true,
					'remove' => true
				)
			),
			// Default
			array(
				array(
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
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
					'expected' => DISCOVERY_BAD,
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
	public function testInheritanceDiscoveryRule_SimpleCreate($data) {
		$this->zbxTestLogin('templates.php');

		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('CONFIGURATION OF TEMPLATES');

		// create a discovery rule on template
		$this->zbxTestClickWait('link='.$this->template);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('form');

		$this->checkTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');
		$this->zbxTestTextPresent('Discovery rule');

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

		if (isset($data['params_f'])) {
			$this->input_type('params_f', $data['params_f']);
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
				case DISCOVERY_GOOD:
					$this->zbxTestTextPresent('Discovery rule created');
					$this->checkTitle('Configuration of discovery rules');
					$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');
					$this->zbxTestTextPresent(array('Item prototypes',  'Trigger prototypes', 'Graph prototypes'));
					break;

				case DISCOVERY_BAD:
					$this->checkTitle('Configuration of discovery rules');
					$this->zbxTestTextPresent(array('CONFIGURATION OF DISCOVERY RULES','Discovery rule'));
					foreach ($data['errors'] as $msg) {
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent(array('Name', 'Type', 'Key'));
					break;
			}
		}

		if (isset($data['templateCheck'])) {
			$this->zbxTestOpenWait('templates.php');
			$this->zbxTestClickWait('link='.$this->template);
			$this->zbxTestClickWait('link=Discovery rules');

			$this->zbxTestTextPresent("$name");
			$this->zbxTestTextNotPresent($this->template.": $name");
			$this->zbxTestClickWait("link=$name");

			$this->assertElementValue('name', $name);
			$this->assertElementValue('key', $key);
		}

		if (isset($data['hostCheck'])) {
			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait('link=Discovery rules');

			$this->zbxTestTextPresent($this->template.": $name");
			$this->zbxTestClickWait("link=$name");

			$this->zbxTestTextPresent('Parent discovery rules', $this->template);
			$this->assertElementValue('name', $name);
			$this->assertAttribute("//*[@id='name']/@readonly", 'readonly');
			$this->assertElementValue('key', $key);
			$this->assertAttribute("//*[@id='key']/@readonly", 'readonly');
		}

		if (isset($data['dbCheck'])) {
			// template
			$result = DBselect("SELECT name, key_, hostid FROM items where name = '".$name."' AND value_type = 4 limit 1");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $name);
				$this->assertEquals($row['key_'], $key);
				$hostid = $row['hostid'] + 1;
			}
			// host
			$result = DBselect("SELECT name, key_ FROM items where name = '".$name."'  AND value_type = 4 AND hostid = ".$hostid."");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $name);
				$this->assertEquals($row['key_'], $key);
			}
		}

		if (isset($data['hostRemove'])) {
			$result = DBselect("SELECT hostid FROM items where name = '".$name."' AND value_type = 4 limit 1");
			while ($row = DBfetch($result)) {
				$hostid = $row['hostid'] + 1;
			}
			$result = DBselect("SELECT name, key_, itemid FROM items where name = '".$name."' AND value_type = 4 AND hostid = ".$hostid."");
			while ($row = DBfetch($result)) {
				$itemId = $row['itemid'];
			}

			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait('link=Discovery rules');

			$this->zbxTestTextPresent($this->template.": $name");
			$this->zbxTestCheckboxSelect("g_hostdruleid_$itemId");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent(array('ERROR: Cannot delete discovery rules', 'Cannot delete templated items.'));
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT itemid FROM items where name = '".$name."' AND value_type = 4 limit 1");
			while ($row = DBfetch($result)) {
				$itemId = $row['itemid'];
			}

			$this->zbxTestOpenWait('templates.php');
			$this->zbxTestClickWait('link='.$this->template);
			$this->zbxTestClickWait('link=Discovery rules');

			$this->zbxTestCheckboxSelect("g_hostdruleid_$itemId");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent('Discovery rules deleted');
			$this->zbxTestTextNotPresent($this->template.": $name");
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testInheritanceDiscoveryRule_Teardown() {
		DBrestore_tables('items');
	}
}
