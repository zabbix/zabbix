<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

/**
 * @backup applications
 */
class testPageApplications extends CLegacyWebTest {

	public static function select_host_group() {
		return [
			[
				[
					'filter' => [
						'Hosts' => 'ЗАББИКС Сервер'
					],
					'Host name' => 'Test host'
				]
			],
			[
				[
					'filter' => [
						'Hosts' => 'Template App Apache Tomcat JMX'
					]
				]
			],
			[
				[
					'filter' => [
						'Hosts' => ['ЗАББИКС Сервер', 'Empty host']
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => 'Templates/Applications'
					]
				]
			],
			[
				[
					'filter' => []
				]
			]
		];
	}

	/**
	 * @dataProvider select_host_group
	 *
	 * Test application list when select host and/or host group.
	 */
	public function testPageApplications_CheckApplicationList($data) {
		// Open hosts page.
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait('ЗАББИКС Сервер');

		// Navigate to host applications.
		$this->zbxTestClickLinkTextWait('Applications');
		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestCheckHeader('Applications');

		// Check selected host and group.
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->checkValue([
			'Hosts' => 'ЗАББИКС Сервер',
			'Host groups' => ''
		]);

		$filter->getField('Hosts')->clear();
		$filter->fill($data['filter']);
		$filter->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'filter.Hosts', false) && !is_array($data['filter']['Hosts'])) {
			// Get host id
			$host = CTestArrayHelper::get($data, 'Host name', $data['filter']['Hosts']);
			$sql_host_id = DBfetch(DBselect("SELECT hostid FROM hosts WHERE host='".$host."'"));
			$host_id= $sql_host_id['hostid'];

			// Check the application names in frontend
			$host_app = [];
			$sql_all_applications = "SELECT applicationid, name FROM applications WHERE hostid=".$host_id;
			$result = DBselect($sql_all_applications);
			while ($row = DBfetch($result)) {
				$host_app[$row['applicationid']] = $row['name'];
			}
			$this->zbxTestTextPresent($host_app);

			// Check items number in frontend
			foreach ($host_app as $appid => $app_name) {
				$sql_count_item = CDBHelper::getCount('SELECT NULL FROM items WHERE flags<>2 AND itemid IN'
						.'(SELECT itemid FROM items_applications WHERE applicationid='.$appid.')');
				$xpath = '//input[@id="applications_'.$appid.'"]/../..//sup';

				if ($sql_count_item === 0) {
					$this->zbxTestAssertElementNotPresentXpath($xpath);
				}
				else {
					$items = $this->zbxTestGetText($xpath);
					$this->assertEquals($sql_count_item, $items);
				}
			}
		}
		else {
			// Check disabled creation button of application
			$this->zbxTestAssertElementText("//button[@id='form']", 'Create application (select host first)');
			$this->zbxTestAssertAttribute("//button[@id='form']",'disabled','true');
			$this->zbxTestAssertElementNotPresentXpath("//ul[contains(@class, 'object-group')]");
		}

		if (CTestArrayHelper::get($data['filter'], 'Host groups', false)) {
			$filter = $this->query('name:zbx_filter')->asForm()->one();
			$filter->getField('Hosts')->clear();
			$filter->submit();

			$group_app= [];
			$sql_all_applications = "SELECT a.name FROM hosts_groups hg LEFT JOIN applications a ON hg.hostid=a.hostid"
					. " WHERE hg.groupid=(SELECT groupid FROM hstgrp WHERE name='".$data['filter']['Host groups']."')";
			$result = DBselect($sql_all_applications);
			while ($row = DBfetch($result)) {
				$group_app[] = $row['name'];
			}
			$this->zbxTestTextPresent($group_app);
		}
	}

	public function selectApplications($app_names, $host, $hostgroup) {
		$this->zbxTestLogin('applications.php?groupid=0&hostid=0');
		$this->zbxTestWaitForPageToLoad();
		// Filter applications by host
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->getField('Hosts')->fill([
			'values' => $host,
			'context' => $hostgroup
		]);
		$filter->submit();

		$result = [];
		$hosts = DBfetch(DBselect('SELECT hostid FROM hosts WHERE name=' . zbx_dbstr($host)));
		$this->assertFalse(empty($hosts));

		$result['hostid'] = $hosts['hostid'];
		$result['apps'] = [];

		// Select applications
		if ($app_names === 'all') {
			$this->zbxTestCheckboxSelect('all_applications');
		}
		else {
			$sql = 'SELECT applicationid, name FROM applications WHERE '.dbConditionString('name', $app_names)
					. ' AND hostid=' . $result['hostid'];
			$cursor = DBselect($sql);
			while ($row = DBfetch($cursor)) {
				$result['apps'][$row['applicationid']] = $row['name'];
			}

			$this->assertEquals([], array_diff($result['apps'], $app_names));

			foreach (array_keys($result['apps']) as $appid) {
				$this->zbxTestCheckboxSelect('applications_'.$appid);
			}
		}

		return $result;
	}

	/**
	 * Test deactivation of selected applications.
	 */
	public function testPageApplications_DisableSelected() {
		$result = $this->selectApplications(['General','OS'], 'ЗАББИКС Сервер', 'Zabbix servers');

		$this->zbxTestClickButtonText('Disable');
		$this->zbxTestAcceptAlert();

		// Check the result in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items disabled');

		// Check the results in DB, that selected application items disabled.
		$sql='SELECT NULL FROM items i INNER JOIN items_applications ia ON ia.itemid=i.itemid WHERE '
				. dbConditionInt('ia.applicationid', array_keys($result['apps'])) . ' AND i.flags<>2 AND i.status='
				. ITEM_STATUS_ACTIVE;
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Test deactivation of all applications in host.
	 */
	public function testPageApplications_DisableAll() {
		$result = $this->selectApplications('all', 'ЗАББИКС Сервер', 'Zabbix servers');

		$this->zbxTestClickButtonText('Disable');
		$this->zbxTestAcceptAlert();

		// Check the result in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items disabled');

		// Check the results in DB, that all application items disabled.
		$sql = 'SELECT NULL FROM items i LEFT JOIN items_applications ia ON ia.itemid=i.itemid '
				. 'WHERE i.hostid=' . $result['hostid'] . ' AND i.flags=0 AND i.status='.ITEM_STATUS_ACTIVE;
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Test activation of selected applications.
	 */
	public function testPageApplications_EnableSelected() {
		$result = $this->selectApplications(['General','OS'], 'ЗАББИКС Сервер', 'Zabbix servers');

		$this->zbxTestClickButtonText('Enable');
		$this->zbxTestAcceptAlert();

		// Check the result in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items enabled');

		// Check the results in DB, that selected application items enabled.
		$sql='SELECT NULL FROM items i INNER JOIN items_applications ia ON ia.itemid=i.itemid WHERE '
				. dbConditionInt('ia.applicationid', array_keys($result['apps'])) . ' AND i.flags<>2 AND i.status='
				. ITEM_STATUS_DISABLED;
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Test activation of all applications in host.
	 */
	public function testPageApplications_EnableAll() {
		$result = $this->selectApplications('all', 'ЗАББИКС Сервер', 'Zabbix servers');

		$this->zbxTestClickButtonText('Enable');
		$this->zbxTestAcceptAlert();

		// Check the result in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items enabled');

		// Check the results in DB, that all application items enabled.
		$sql = 'SELECT NULL FROM items i LEFT JOIN items_applications ia ON ia.itemid=i.itemid '
				. 'WHERE i.hostid=' . $result['hostid'] . ' AND i.flags=0 AND i.status='.ITEM_STATUS_DISABLED;
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Test deleting of application.
	 */
	public function testPageApplications_DeleteSelected() {
		$result = $this->selectApplications(['Selenium test application'], 'ЗАББИКС Сервер', 'Zabbix servers');
		$items = CDBHelper::getCount('SELECT NULL FROM items');

		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAcceptAlert();

		// Check the result in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Application deleted');

		// Check the result in DB.
		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM applications WHERE ' . dbConditionInt('applicationid', array_keys($result['apps']))));
		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM items_applications WHERE ' . dbConditionInt('applicationid', array_keys($result['apps']))));
		$this->assertEquals($items, CDBHelper::getCount('SELECT NULL FROM items'));
	}

	/**
	 * Test impossible deleting of templated application.
	 */
	public function testPageApplications_CannotDelete() {
		$sql_hash = 'SELECT * FROM applications ORDER BY applicationid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$result = $this->selectApplications('all', 'ЗАББИКС Сервер', 'Zabbix servers');

		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAcceptAlert();

		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete applications');
		$this->zbxTestTextPresent('Cannot delete templated application.');

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}
}
