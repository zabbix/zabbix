<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

//require_once dirname(__FILE__).'/../include/class.cwebtest.php';
require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

/**
 * @backup drules
 */
class testFormNetworkDiscovery extends CLegacyWebTest {

	public function getCreateValidationData() {
		return [
			[
				[
					'error' => 'Incorrect value for field "name": cannot be empty.',
				]
			],
			[
				[
					'name' => ' ',
					'proxy' => 'Active proxy 1',
					'iprange' => '192.168.0.1-25',
					'delay' => '1m',
					'checks' => [
						['check_action' => 'New', 'type' => 'HTTP', 'ports' => '7555']
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Local network',
					'checks' => [
						['check_action' => 'New', 'type' => 'HTTPS', 'ports' => '447']
					],
					'error' => 'Discovery rule "Local network" already exists.',
				]
			],
			[
				[
					'name' => 'Discovery rule with empty IP range',
					'iprange' => ' ',
					'checks' => [
						['check_action' => 'New', 'type' => 'FTP', 'ports' => '22']
					],
					'error' => 'Incorrect value for field "iprange": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Discovery rule with incorrect IP range',
					'proxy' => 'Active proxy 1',
					'iprange' => 'text',
					'delay' => '1m',
					'checks' => [
						['check_action' => 'New', 'type' => 'HTTP', 'ports' => '7555']
					],
					'error' => 'Incorrect value for field "iprange": invalid address range "text".'
				]
			],
			[
				[
					'name' => 'Discovery rule with incorrect update interval',
					'proxy' => 'Active proxy 1',
					'delay' => '1G',
					'checks' => [
						['check_action' => 'New', 'type' => 'HTTP', 'ports' => '7555']
					],
					'error' => 'Field "Update interval" is not correct: a time unit is expected'
				]
			],
			// Error in checks.
			[
				[
					'name' => 'Discovery rule without checks',
					'proxy' => 'Active proxy 3',
					'iprange' => '192.168.0.1-25',
					'delay' => '1m',
					'error' => 'Cannot save discovery rule without checks.'
				]
			],
			[
				[
					'name' => 'Discovery rule without checks, add check and then remove it',
					'checks' => [
						['check_action' => 'New', 'type' => 'POP'],
						['check_action' => 'Remove']
					],
					'error' => 'Cannot save discovery rule without checks.'
				]
			],
			[
				[
					'name' => 'Discovery rule with incorrect port range',
					'checks' => [
						['check_action' => 'New', 'type' => 'POP', 'ports' => 'abc']
					],
					'error_in_checks' => ['Incorrect port range.']
				]
			],
			[
				[
					'name' => 'Discovery rule with existen check',
					'checks' => [
						['check_action' => 'New', 'type' => 'ICMP ping'],
						['check_action' => 'New', 'type' => 'ICMP ping']
					],
					'error_in_checks' => ['Check already exists.']
				]
			],
			[
				[
					'name' => 'Discovery rule with incorrect Zabbix agent check',
					'checks' => [
						['check_action' => 'New', 'type' => 'Zabbix agent']
					],
					'error_in_checks' => ['Invalid key "": key is empty.']
				]
			],
			[
				[
					'name' => 'Discovery rule with incorrect SNMP',
					'checks' => [
						['check_action' => 'New', 'type' => 'SNMPv1 agent']
					],
					'error_in_checks' => ['Incorrect SNMP community.', 'Incorrect SNMP OID.']
				]
			]
		];
	}

	/**
	 * Test form validations at creation.
	 *
	 * @dataProvider getCreateValidationData
	 */
	public function testFormNetworkDiscovery_CreateValidation($data) {
		$sql_drules = 'SELECT * FROM drules ORDER BY druleid';
		$old_drules = CDBHelper::getHash($sql_drules);
		$sql_dchecks = 'SELECT * FROM dchecks ORDER BY druleid, dcheckid';
		$old_dchecks = CDBHelper::getHash($sql_dchecks);

		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestClickButtonText('Create discovery rule');
		$this->fillInFields($data);

		if (array_key_exists('error_in_checks', $data)) {
			$this->zbxTestLaunchOverlayDialog('Discovery check error');
			foreach ($data['error_in_checks'] as $error) {
				$this->zbxTestAssertElementPresentXpath("//div[@class='overlay-dialogue-body']//span[text()='".$error."']");
			}
			$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]/button[text()="Cancel"]');
			return;
		}

		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);

		$this->assertEquals($old_drules, CDBHelper::getHash($sql_drules));
		$this->assertEquals($old_dchecks, CDBHelper::getHash($sql_dchecks));
	}

	public static function getCreateData() {
		return [
			[
				[
					'name' => 'Discovery rule with HTTP check',
					'checks' => [
						['check_action' => 'New', 'type' => 'HTTP', 'ports' => '7555']
					]
				]
			],
			[
				[
					'name' => 'Discovery rule with Zabbix agent',
					'checks' => [
						['check_action' => 'New', 'type' => 'Zabbix agent', 'key' => 'ping']
					],
					'radio_buttons' => [
						'Device uniqueness criteria' => 'Zabbix agent "ping"',
						'Host name' => 'IP address',
						'Visible name' => 'DNS name'
					]
				]
			],
			[
				[
					'name' => 'Discovery rule with many checks',
					'proxy' => 'Active proxy 1',
					'iprange' => '192.168.0.1-25',
					'delay' => '1m',
					'checks' => [
						[
							'check_action' => 'New',
							'type' => 'FTP'
						],
						[
							'check_action' => 'New',
							'type' => 'HTTP'
						],
						[
							'check_action' => 'New',
							'type' => 'HTTPS'
						],
						[
							'check_action' => 'New',
							'type' => 'ICMP ping'
						],
						[
							'check_action' => 'New',
							'type' => 'IMAP',
							'ports' => '144'
						],
						[
							'check_action' => 'New',
							'type' => 'LDAP'
						],
						[
							'check_action' => 'New',
							'type' => 'NNTP'
						],
						[
							'check_action' => 'New',
							'type' => 'POP'
						],
						[
							'check_action' => 'New',
							'type' => 'SMTP'
						],
						[
							'check_action' => 'New',
							'type' => 'SSH'
						],
						[
							'check_action' => 'New',
							'type' => 'TCP'
						],
						[
							'check_action' => 'New',
							'type' => 'Telnet'
						],
						[
							'check_action' => 'New',
							'type' => 'Zabbix agent',
							'key' => 'ping'
						],
						[
							'check_action' => 'New',
							'type' => 'SNMPv1 agent',
							'port' => '156',
							'community' => '1',
							'snmp_oid' => '1'
						],
						[
							'check_action' => 'New',
							'type' => 'SNMPv3 agent',
							'port' => '157',
							'snmp_oid' => '1',
							'context_name' => '1',
							'security_name' => '1',
							'security_level' => 'noAuthNoPriv'
						],
						[
							'check_action' => 'New',
							'type' => 'SNMPv3 agent',
							'port' => '158',
							'snmp_oid' => '2',
							'context_name' => '2',
							'security_name' => '2',
							'security_level' => 'authNoPriv',
							'auth_protocol' => 'SHA',
							'auth_passphrase' => '2'
						],
						[
							'check_action' => 'New',
							'type' => 'SNMPv3 agent',
							'port' => '159',
							'snmp_oid' => '3',
							'context_name' => '3',
							'security_name' => '3',
							'security_level' => 'authPriv',
							'auth_protocol' => 'MD5',
							'auth_passphrase' => '3',
							'priv_protocol' => 'AES',
							'priv_passphrase' => '3'
						]
					],
					'radio_buttons' => [
						'Device uniqueness criteria' => 'SNMPv1 agent "1"',
						'Host name' => 'SNMPv3 agent "3"',
						'Visible name' => 'Zabbix agent "ping"'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormNetworkDiscovery_Create($data) {
		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestClickButtonText('Create discovery rule');
		$this->fillInFields($data);

		$this->zbxTestClickWait('add');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Discovery rule created');
		$this->zbxTestTextPresent($data['name']);

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM drules WHERE name='.zbx_dbstr($data['name'])));
		$cheks = 'SELECT NULL FROM dchecks WHERE druleid IN ('.
				'SELECT druleid FROM drules WHERE name='.zbx_dbstr($data['name']).
				')';
		$this->assertEquals(count($data['checks']), CDBHelper::getCount($cheks));
	}

	public function getUpdateValidationData() {
		return [
			[
				[
					'old_name' => 'Discovery rule for update',
					'name' => ' ',
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'old_name' => 'Discovery rule for update',
					'iprange' => 'text',
					'error' => 'Incorrect value for field "iprange": invalid address range "text".'
				]
			],
			[
				[
					'old_name' => 'Discovery rule for update',
					'delay' => 'text',
					'error' => 'Field "Update interval" is not correct: a time unit is expected'
				]
			],
			[
				[
					'old_name' => 'Discovery rule for update',
					'checks' => [
						['check_action' => 'Remove']
					],
					'error' => 'Cannot save discovery rule without checks.'
				]
			],
			[
				[
					'old_name' => 'Local network',
					'checks' => [
						['check_action' => 'New', 'type' => 'Zabbix agent', 'key' => 'system.uname']
					],
					'error_in_checks' => ['Check already exists.']
				]
			],
			[
				[
					'old_name' => 'Local network',
					'checks' => [
						['check_action' => 'Edit', 'ports' => 'abc']
					],
					'error_in_checks' => ['Incorrect port range.']
				]
			]
		];
	}

	/**
	 * Test form validations at update.
	 *
	 * @dataProvider getUpdateValidationData
	 */
	public function testFormNetworkDiscovery_UpdateValidation($data) {
		$sql_drules = 'SELECT * FROM drules ORDER BY druleid';
		$old_drules = CDBHelper::getHash($sql_drules);
		$sql_dchecks = 'SELECT * FROM dchecks ORDER BY druleid, dcheckid';
		$old_dchecks = CDBHelper::getHash($sql_dchecks);

		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestClickLinkText($data['old_name']);
		$this->fillInFields($data);

		if (array_key_exists('error_in_checks', $data)) {
			$this->zbxTestLaunchOverlayDialog('Discovery check error');
			foreach ($data['error_in_checks'] as $error) {
				$this->zbxTestAssertElementPresentXpath("//div[@class='overlay-dialogue-body']//span[text()='".$error."']");
			}
			return;
		}

		$this->zbxTestClick('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);

		$this->assertEquals($old_drules, CDBHelper::getHash($sql_drules));
		$this->assertEquals($old_dchecks, CDBHelper::getHash($sql_dchecks));
	}

	public static function getUpdateData() {
		return [
			// Successful discovery update.
			[
				[
					'old_name' => 'Discovery rule for update',
					'enabled' => false
				]
			],
			[
				[
					'old_name' => 'Disabled discovery rule for update',
					'checks' => [
						['check_action' => 'Remove'],
						['check_action' => 'New', 'type' => 'HTTP', 'ports' => '10500']
					],
					'enabled' => true
				]
			],
			[
				[
					'old_name' => 'Local network',
					'radio_buttons' => [
						'Device uniqueness criteria' => 'Zabbix agent "system.uname"',
						'Host name' => 'IP address',
						'Visible name' => 'DNS name'
					]
				]
			],
			[
				[
					'old_name' => 'Local network',
					'checks' => [
						['check_action' => 'New', 'type' => 'POP', 'ports' => '111']
					]
				]
			],
			[
				[
					'old_name' => 'Discovery rule for update',
					'name' => 'Update name',
					'proxy' => 'Active proxy 3',
					'iprange' => '1.1.0.1-25',
					'delay' => '30s',
					'checks' => [
						['check_action' => 'Edit', 'type' => 'TCP', 'ports' => '9']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateData
	 */
	public function testFormNetworkDiscovery_Update($data) {
		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestClickLinkText($data['old_name']);
		$this->fillInFields($data);

		// Get amount of check rows in discovery form.
		$checks_on_page = count($this->webDriver->findElements(WebDriverBy::xpath('//div[@id="dcheckList"]'.
								'//tr[not(@id="dcheckListFooter")]')));

		$this->zbxTestClick('update');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Discovery rule updated');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckHeader('Discovery rules');

		if (!array_key_exists('name', $data)) {
			$data['name'] = $data['old_name'];
		}

		// Check the results in DB after update.
		$proxy = DBfetch(DBselect('SELECT proxy_hostid FROM drules WHERE name='.zbx_dbstr($data['name'])));
		if ($proxy['proxy_hostid']) {
			$discovery_db_data = CDBHelper::getRow('SELECT hosts.host AS proxy, drules.name, iprange, delay'.
					' FROM drules'.
					' JOIN hosts ON drules.proxy_hostid=hostid'.
					' WHERE drules.name='.zbx_dbstr($data['name']));
		}
		else {
			$discovery_db_data = CDBHelper::getRow('SELECT name, iprange, delay FROM drules WHERE name='
				.zbx_dbstr($data['name']));
		}

		$fields = ['name', 'proxy', 'iprange', 'delay'];
		foreach ($fields as $field) {
			if (array_key_exists($field, $data)) {
				$this->assertEquals($data[$field], $discovery_db_data[$field]);
			}
		}

		// Compare amount of checks on page and in DB.
		$checks_db = CDBHelper::getCount('SELECT dcheckid FROM dchecks WHERE druleid IN ( SELECT druleid FROM drules'
				.' WHERE name='.zbx_dbstr($data['name']).')');
		$this->assertEquals($checks_db, $checks_on_page);
	}

	/**
	 * Test update without any modification of discovery rule data.
	 */
	public function testFormNetworkDiscovery_SimpleUpdate() {
		$sql_drules = 'SELECT * FROM drules ORDER BY druleid';
		$old_drules = CDBHelper::getHash($sql_drules);
		$sql_dchecks = 'SELECT * FROM dchecks ORDER BY druleid, dcheckid';
		$old_dchecks = CDBHelper::getHash($sql_dchecks);

		$this->zbxTestLogin('discoveryconf.php');
		foreach (CDBHelper::getRandom('SELECT name FROM drules', 3) as $discovery) {
			$this->zbxTestClickLinkTextWait($discovery['name']);
			$this->zbxTestClickWait('update');
			// Check the results in frontend.
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Discovery rule updated');
		}

		$this->assertEquals($old_drules, CDBHelper::getHash($sql_drules));
		$this->assertEquals($old_dchecks, CDBHelper::getHash($sql_dchecks));
	}

	private function fillInFields($data) {
		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('name', $data['name']);
		}

		if (array_key_exists('proxy', $data)) {
			$this->zbxTestDropdownSelect('proxy_hostid', $data['proxy']);
		}

		if (array_key_exists('iprange', $data)) {
			$this->zbxTestInputTypeOverwrite('iprange', $data['iprange']);
		}

		if (array_key_exists('delay', $data)) {
			$this->zbxTestInputTypeOverwrite('delay', $data['delay']);
		}

		if (array_key_exists('checks', $data)) {
			foreach ($data['checks'] as $check) {
				foreach ($check as $key => $value) {
					switch ($key) {
						case 'check_action':
							$action = $value;
							$this->zbxTestClickButtonText($action);
							if ($action !== 'Remove') {
								$this->zbxTestWaitUntilElementPresent(WebDriverBy::id('new_check_form'));
							}
							break;
						case 'type':
							$this->zbxTestDropdownSelectWait('type', $value);
							break;
						case 'ports':
							$this->zbxTestInputTypeOverwrite('ports', $value);
							break;
						case 'key':
							$this->zbxTestInputTypeOverwrite('key_', $value);
							break;
						case 'community':
							$this->zbxTestInputTypeOverwrite('snmp_community', $value);
							break;
						case 'snmp_oid':
							$this->zbxTestInputTypeOverwrite('snmp_oid', $value);
							break;
						case 'context_name':
							$this->zbxTestInputTypeOverwrite('snmpv3_contextname', $value);
							break;
						case 'security_name':
							$this->zbxTestInputTypeOverwrite('snmpv3_securityname', $value);
							break;
						case 'security_level':
							$this->zbxTestDropdownSelect('snmpv3_securitylevel', $value);
							break;
						case 'auth_protocol':
							$this->zbxTestClickXpathWait('//input[@name="snmpv3_authprotocol"]/../label[text()="'.$value.'"]');
							break;
						case 'auth_passphrase':
							$this->zbxTestInputTypeOverwrite('snmpv3_authpassphrase', $value);
							break;
						case 'priv_protocol':
							$this->zbxTestClickXpathWait('//input[@name="snmpv3_privprotocol"]/../label[text()="'.$value.'"]');
							break;
						case 'priv_passphrase':
							$this->zbxTestInputTypeOverwrite('snmpv3_privpassphrase', $value);
							break;
					}
				}
				if ($action === 'New' || $action === 'Edit') {
					$this->zbxTestClick('add_new_dcheck');
					if (!array_key_exists('error_in_checks', $data)) {
						$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::id('new_check_form'));
					}
				}
			}
		}

		if (array_key_exists('enabled', $data)) {
			$this->zbxTestCheckboxSelect('status', $data['enabled']);
		}

		if (array_key_exists('radio_buttons', $data)) {
			foreach ($data['radio_buttons'] as $field_name => $label) {
				$prefix = '//div[@class="table-forms-td-left"]//label[text()='.CXPathHelper::escapeQuotes($field_name).']/../..';
				$xpath = $prefix.'//label[text()='.CXPathHelper::escapeQuotes($label).']';
				$this->zbxTestClickXpathWait($xpath);
			}
		}
	}

	public function testFormNetworkDiscovery_Delete() {
		$name = 'Discovery rule to check delete';
		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestClickAndAcceptAlert('delete');
		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Discovery rule deleted');

		// Check the results in DB.
		$sql = 'SELECT * FROM drules WHERE name='.zbx_dbstr($name);
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	public function testFormNetworkDiscovery_Clone() {
		$this->zbxTestLogin('discoveryconf.php');
		foreach (CDBHelper::getRandom('SELECT name FROM drules WHERE druleid IN (2,3)', 2) as $drule) {
			$this->zbxTestClickLinkTextWait($drule['name']);
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestClickWait('clone');
			$this->zbxTestInputType('name', 'CLONE: '.$drule['name']);
			$this->zbxTestClickWait('add');

			$sql_drules = [];
			$sql_dchecks = [];

			$names = [($drule['name']), 'CLONE: '.$drule['name']];
			foreach ($names as $name) {
				$sql_drules[] = CDBHelper::getHash('SELECT proxy_hostid, iprange, delay, nextcheck, status'.
						' FROM drules'.
						' WHERE name='.zbx_dbstr($name).
						' ORDER BY druleid'
				);

				$sql_dchecks[] = CDBHelper::getHash('SELECT type, key_, snmp_community, ports, snmpv3_securityname, '.
						'snmpv3_securitylevel, snmpv3_authpassphrase, uniq, snmpv3_authprotocol, snmpv3_privprotocol, '.
						'snmpv3_contextname'.
						' FROM dchecks'.
						' WHERE druleid IN ('.
						'SELECT druleid'.
						' FROM drules'.
						' WHERE name='.zbx_dbstr($name).
						')'.
						' ORDER BY type, key_'
				);
			}

			$this->assertEquals($sql_drules[0], $sql_drules[1]);
			$this->assertEquals($sql_dchecks[0], $sql_dchecks[1]);
		}
	}

	public function testFormNetworkDiscovery_CancelCreation() {
		$this->executeCancelAction('create');
	}

	public function testFormNetworkDiscovery_CancelUpdating() {
		$this->executeCancelAction('update');
	}

	public function testFormNetworkDiscovery_CancelCloning() {
		$this->executeCancelAction('clone');
	}

	public function testFormNetworkDiscovery_CancelDelete() {
		$this->executeCancelAction('delete');
	}

	/**
	 * Cancel updating, cloning or deleting of discovery rule.
	 */
	private function executeCancelAction($action) {
		$sql_drules = 'SELECT * FROM drules ORDER BY druleid';
		$old_drules = CDBHelper::getHash($sql_drules);
		$sql_dchecks = 'SELECT * FROM dchecks ORDER BY druleid, dcheckid';
		$old_dchecks = CDBHelper::getHash($sql_dchecks);

		$this->zbxTestLogin('discoveryconf.php');

		if ($action === 'create') {
			$discovery = [
				'name' => 'Discovery rule Cancel creation',
				'checks' => [
					['check_action' => 'New', 'type' => 'ICMP ping']
				]
			];
			$this->zbxTestClickButtonText('Create discovery rule');
			$this->fillInFields($discovery);
		}
		else {
			$discovery = CDBHelper::getRandom('SELECT name FROM drules', 1);
			$discovery = $discovery[0];
			$name = $discovery['name'];
			$this->zbxTestClickLinkTextWait($name);

			switch ($action) {
				case 'update':
					$name .= ' (updated)';
					$this->zbxTestInputTypeOverwrite('name', $name);
					break;

				case 'clone':
					$name .= ' (cloned)';
					$this->zbxTestClickWait('clone');
					$this->zbxTestInputTypeOverwrite('name', $name);
					break;

				case 'delete':
					$this->zbxTestClickWait('delete');
					$this->webDriver->switchTo()->alert()->dismiss();
					break;
			}
		}
		$this->zbxTestClickWait('cancel');

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckHeader('Discovery rules');

		$this->assertEquals($old_drules, CDBHelper::getHash($sql_drules));
		$this->assertEquals($old_dchecks, CDBHelper::getHash($sql_dchecks));
	}
}
