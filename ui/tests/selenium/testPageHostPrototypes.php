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
require_once dirname(__FILE__).'/traits/TableTrait.php';

/**
 * @backup hosts
 */
class testPageHostPrototypes extends CLegacyWebTest {

	use TableTrait;

	const DICROVERY_RULE_ID = 90001;
	const HOST_PROTOTYPES_COUNT = 8;

	public function testPageHostPrototypes_CheckLayout() {
		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DICROVERY_RULE_ID.'&context=host');
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		$this->zbxTestCheckHeader('Host prototypes');

		$table = $this->query('xpath://form[@name="hosts"]/table[@class="list-table"]')->asTable()->one();
		$headers = ['', 'Name', 'Templates', 'Create enabled', 'Discover', 'Tags'];
		$this->assertSame($headers, $table->getHeadersText());

		foreach (['Create enabled', 'Create disabled', 'Delete'] as $button) {
			$element = $this->query('button', $button)->one();
			$this->assertTrue($element->isPresent());
			$this->assertFalse($element->isEnabled());
		}

		$this->assertTableStats(self::HOST_PROTOTYPES_COUNT);

		// Check tags on the specific host prototype.
		$tags = $table->findRow('Name', 'Host prototype {#1}')->getColumn('Tags')->query('class:tag')->all();
		$this->assertEquals(['host_proto_tag_1: value1', 'host_proto_tag_2: value2'], $tags->asText());

		foreach ($tags as $tag) {
			$tag->click();
			$hint = $this->query('xpath://div[@data-hintboxid]')
					->asOverlayDialog()->waitUntilPresent()->all()->last();
			$this->assertEquals($tag->getText(), $hint->getText());
			$hint->close();
		}
	}

	public static function getSelectedData() {
		return [
			[
				[
					'item' => 'Discovery rule 1',
					'hosts' => [
						'Host prototype {#1}'
					]
				]
			],
			[
				[
					'item' => 'Discovery rule 2',
					'hosts' => 'all'
				]
			],
			[
				[
					'item' => 'Discovery rule 3',
					'hosts' => [
						'Host prototype {#7}',
						'Host prototype {#9}',
						'Host prototype {#10}'
					]
				]
			]
		];
	}

	/**
	 * Select specified hosts from host prototype page.
	 *
	 * @param array $data	test case data from data provider
	 */
	private function selectHostPrototype($data) {
		$discoveryid = DBfetch(DBselect("SELECT itemid FROM items WHERE name=".zbx_dbstr($data['item'])));
		$this->zbxTestLogin("host_prototypes.php?parent_discoveryid=".$discoveryid['itemid'].'&context=host');

		if ($data['hosts'] === 'all') {
			$this->zbxTestCheckboxSelect('all_hosts');
			return;
		}

		foreach ($data['hosts'] as $host) {
			$result = DBselect('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host));
			while ($row = DBfetch($result)) {
				$this->zbxTestCheckboxSelect('group_hostid_'.$row['hostid']);
			}
		}
	}

	/**
	 * Check specific page action.
	 * Actions are defined by buttons pressed on page.
	 *
	 * @param array  $data		test case data from data provider
	 * @param string $action	button text (action to be executed)
	 * @param int    $status	host status to be checked in DB
	 */
	protected function checkPageAction($data, $action, $status = null) {
		// Click on button with required action.
		if ($action === 'Click on state') {
			foreach ($data['hosts'] as $host) {
				$id = DBfetch(DBselect('SELECT hostid FROM hosts WHERE name='.zbx_dbstr($host)));
				$this->zbxTestClickXpathWait("//a[contains(@onclick,'group_hostid=".$id['hostid']."')]");
			}
		}
		else {
			$this->selectHostPrototype($data);
			$this->zbxTestClickButtonText($action);
			$this->zbxTestAcceptAlert();
		}

		$this->zbxTestIsElementPresent('//*[@class="msg-good"]');
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		$this->zbxTestCheckHeader('Host prototypes');

		// Create query part for status (if any).
		$status_criteria = ($status !== null) ? (' AND status='.$status) : '';

		// Check the results in DB.
		if ($data['hosts'] === 'all') {
			$sql = 'SELECT NULL'.
						' FROM hosts'.
						' WHERE hostid IN ('.
							'SELECT hostid'.
							' FROM host_discovery'.
							' WHERE parent_itemid IN ('.
								'SELECT itemid'.
								' FROM items'.
								' WHERE name='.zbx_dbstr($data['item']).
							')'.
						')';
		}
		else {
			$names = [];
			foreach ($data['hosts'] as $host) {
				$names[] = zbx_dbstr($host);
			}

			$sql = 'SELECT NULL'.
					' FROM hosts'.
					' WHERE host IN ('.implode(',', $names).')';
		}

		$this->assertEquals(0, CDBHelper::getCount($sql.$status_criteria));
	}

	/**
	 * @dataProvider getSelectedData
	 */
	public function testPageHostPrototypes_DisableSelected($data) {
		$this->checkPageAction($data, 'Create disabled', HOST_STATUS_MONITORED);
	}

	/**
	 * @dataProvider getSelectedData
	 */
	public function testPageHostPrototypes_EnableSelected($data) {
		$this->checkPageAction($data, 'Create enabled', HOST_STATUS_NOT_MONITORED);
	}

	/**
	 * @dataProvider getSelectedData
	 */
	public function testPageHostPrototypes_DeleteSelected($data) {
		$this->checkPageAction($data, 'Delete');
	}

	public static function getHostPrototypeData() {
		return [
			[
				[
					'item' => 'Discovery rule 1',
					'hosts' => [
						'Host prototype {#2}'
					],
					'status' => HOST_STATUS_NOT_MONITORED
				]
			],
			[
				[
					'item' => 'Discovery rule 1',
					'hosts' => [
						'Host prototype {#3}'
					],
					'status' => HOST_STATUS_MONITORED
				]
			]
		];
	}

	/**
	 * @dataProvider getHostPrototypeData
	 */
	public function testPageHostPrototypes_SingleEnableDisable($data) {
		$discoveryid = DBfetch(DBselect("SELECT itemid FROM items WHERE name=".zbx_dbstr($data['item'])));
		$this->zbxTestLogin("host_prototypes.php?parent_discoveryid=".$discoveryid['itemid'].'&context=host');

		$this->checkPageAction($data, 'Click on state', $data['status']);
	}
}
