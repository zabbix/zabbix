<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup drules
 *
 * @onBefore prepareDiscoveryRulesData
 */

class testPageNetworkDiscovery extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Create discovery rules for testPageNetworkDiscovery autotest.
	 */
	private function prepareDiscoveryRulesData() {
		CDataHelper::call('drule.create', [
			[
				'name' => 'External network',
				'iprange' => '192.168.3.1-255',
				'delay' => 600,
				'status' => 0,
				'dchecks' => [
					[
						'type' => SVC_AGENT,
						'key_' => 'system.uname',
						'ports' => 10050,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_FTP,
						'ports' => '21,1021',
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_HTTP,
						'ports' => '80,8080',
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_ICMPPING,
						'ports' => 0,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_IMAP,
						'ports' => '143-145',
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_LDAP,
						'ports' => 389,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_NNTP,
						'ports' => 119,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_POP,
						'ports' => 110,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_SMTP,
						'ports' => 25,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_SNMPv1,
						'key_' => 'ifIndex0',
						'snmp_community' => 'public',
						'ports' => 161,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_SNMPv2c,
						'key_' => 'ifInOut0',
						'snmp_community' => 'private1',
						'ports' => 162,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_SNMPv3,
						'key_' => 'ifIn0',
						'ports' => 161,
						'snmpv3_securityname' => 'private2',
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_SSH,
						'ports' => 22,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_TCP,
						'ports' => '10000-20000',
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_TELNET,
						'ports' => 23,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_AGENT,
						'key_' => 'agent.uname',
						'ports' => 10050,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					]
				]
			],
			[
				'name' => 'Discovery rule for update',
				'iprange' => '192.168.3.1-255',
				'proxy_hostid' => 20000,
				'delay' => 600,
				'status' => 0,
				'dchecks' => [
					[
						'type' => SVC_ICMPPING,
						'ports' => 0,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					]
				]
			],
			[
				'name' => 'Disabled discovery rule for update',
				'iprange' => '192.168.3.1-255',
				'proxy_hostid' => 20000,
				'delay' => 600,
				'status' => 1,
				'dchecks' => [
					[
						'type' => SVC_ICMPPING,
						'ports' => 0,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					]
				]
			],
			[
				'name' => 'Discovery rule to check delete',
				'iprange' => '192.168.3.1-255',
				'proxy_hostid' => 20000,
				'delay' => 600,
				'status' => 1,
				'dchecks' => [
					[
						'type' => SVC_ICMPPING,
						'ports' => 0,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					]
				]
			]
		]);
		$druleid = CDataHelper::getIds('name');
	}

	public function testPageNetworkDiscovery_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=discovery.list&sort=name&sortorder=DESC');
		$table = $this->query('class:list-table')->asTable()->one();
		$form = $this->query('name:zbx_filter')->asForm()->one();

		$this->page->assertTitle('Configuration of discovery rules');
		$this->page->assertHeader('Discovery rules');
		$this->assertEquals(['', 'Name', 'IP range', 'Proxy', 'Interval', 'Checks', 'Status'], $table->getHeadersText());
		$this->assertEquals(['Name', 'Status'], $form->getLabels()->asText());

		// Check if default enabled buttons are clickable.
		$this->assertEquals(3, $this->query('button', ['Create discovery rule', 'Apply', 'Reset'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		// Check if default disabled buttons are not clickable.
		$this->assertEquals(0, $this->query('button', ['Enable', 'Disable', 'Delete'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		// Check if filter collapses/ expands.
		foreach (['true', 'false'] as $status) {
			$this->assertTrue($this->query('xpath://li[@aria-expanded='.CXPathHelper::escapeQuotes($status).']')
					->one()->isPresent()
			);
			$this->query('xpath://a[@id="ui-id-1"]')->one()->click();
		}

		// Check if fields "Name" length is as expected.
		$this->assertEquals(255, $form->query('xpath:.//input[@name="filter_name"]')
				->one()->getAttribute('maxlength')
		);

		/**
		 * Check if counter displays correct number of rows and check if previously disabled buttons are enabled,
		 * upon selecting discovery rules.
		 */
		$selected_counter = $this->query('id:selected_count')->one();
		$this->assertEquals('0 selected', $selected_counter->getText());
		$this->query('id:all_drules')->asCheckbox()->one()->set(true);
		$this->assertEquals(CDBHelper::getCount('SELECT * FROM drules').' selected', $selected_counter->getText());
		foreach (['Enable', 'Disable', 'Delete'] as $buttons ){
			$this->assertTrue($this->query('button:'.$buttons)->one()->isEnabled());
		}
	}

	public function testPageNetworkDiscovery_CheckSorting() {
		$this->page->login()->open('zabbix.php?action=discovery.list&sort=name&sortorder=DESC');





	}




			/*		$this->zbxTestCheckTitle('Configuration of discovery rules');

					$this->zbxTestCheckHeader('Discovery rules');
					$this->zbxTestTextPresent('Displaying');
					$this->zbxTestTextPresent(['Name', 'IP range', 'Proxy', 'Interval', 'Checks', 'Status']);
					$this->zbxTestTextPresent(['Enable', 'Disable', 'Delete']);*/


/*	// returns all discovery rules
	public static function allRules() {
		return CDBHelper::getDataProvider('SELECT druleid,name FROM drules');
	}*/

	/**
	*
	*/
	public function testPageNetworkDiscovery_SimpleUpdate() {






		/*$sqlDRules = 'SELECT * FROM drules WHERE druleid='.$drule['druleid'];
		$sqlDChecks = 'SELECT * FROM dchecks WHERE druleid='.$drule['druleid'].' ORDER BY dcheckid';
		$oldHashDRules = CDBHelper::getHash($sqlDRules);
		$oldHashDChecks = CDBHelper::getHash($sqlDChecks);

		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestClickLinkText($drule['name']);
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Update')->waitUntilClickable()->one()->click();
		$dialog->ensureNotPresent();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule updated');
		$this->zbxTestTextPresent($drule['name']);

		$this->assertEquals($oldHashDRules, CDBHelper::getHash($sqlDRules));
		$this->assertEquals($oldHashDChecks, CDBHelper::getHash($sqlDChecks));*/
	}

	/**
	 *
	 */
	public function testPageNetworkDiscovery_MassDelete($drule) {



		/*$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('druleids_'.$drule['druleid']);
		$this->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->assertMessage(TEST_GOOD, 'Discovery rule deleted');

		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM drules WHERE druleid='.$drule['druleid']));
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM dchecks WHERE druleid='.$drule['druleid']));*/
	}

	public function testPageNetworkDiscovery_MassDisableAll() {



		/*DBexecute('UPDATE drules SET status='.DRULE_STATUS_ACTIVE);

		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('all_drules');
		$this->query('button:Disable')->waitUntilClickable()->one()->click();
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->assertMessage(TEST_GOOD, 'Discovery rules disabled');

		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM drules WHERE status='.DRULE_STATUS_ACTIVE));*/
	}

	/**
	*
	*/
	public function testPageNetworkDiscovery_MassDisable() {



		/*DBexecute('UPDATE drules SET status='.DRULE_STATUS_ACTIVE.' WHERE druleid='.$drule['druleid']);

		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('druleids_'.$drule['druleid']);
		$this->query('button:Disable')->waitUntilClickable()->one()->click();
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->assertMessage(TEST_GOOD, 'Discovery rule disabled');

		$this->assertEquals(1, CDBHelper::getCount(
			'SELECT *'.
			' FROM drules'.
			' WHERE druleid='.$drule['druleid'].
				' AND status='.DRULE_STATUS_DISABLED
		));*/
	}

	public function testPageNetworkDiscovery_MassEnableAll() {


		/*DBexecute('UPDATE drules SET status='.DRULE_STATUS_DISABLED);

		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('all_drules');
		$this->query('button:Enable')->waitUntilClickable()->one()->click();
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->assertMessage(TEST_GOOD, 'Discovery rules enabled');

		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM drules WHERE status='.DRULE_STATUS_DISABLED));*/
	}

	/**
	*
	*/
	public function testPageNetworkDiscovery_MassEnable() {


		/*DBexecute('UPDATE drules SET status='.DRULE_STATUS_DISABLED.' WHERE druleid='.$drule['druleid']);

		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('druleids_'.$drule['druleid']);
		$this->query('button:Enable')->waitUntilClickable()->one()->click();
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->assertMessage(TEST_GOOD, 'Discovery rule enabled');

		$this->assertEquals(1, CDBHelper::getCount(
			'SELECT *'.
			' FROM drules'.
			' WHERE druleid='.$drule['druleid'].
				' AND status='.DRULE_STATUS_ACTIVE
		));*/
	}
}
