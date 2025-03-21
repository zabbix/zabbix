<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once __DIR__ . '/../../include/CWebTest.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * Test for checking Proxy groups page.
 *
 * @dataSource Proxies
 *
 * @backup proxy_group
 */
class testPageAdministrationProxyGroups extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	public function testPageAdministrationProxyGroups_Layout() {
		$this->page->login()->open('zabbix.php?action=proxygroup.list')->waitUntilReady();
		$this->page->assertTitle('Configuration of proxy groups');
		$this->page->assertHeader('Proxy groups');

		$this->assertTrue($this->query('button:Create proxy group')->one()->isClickable());
		$filter_form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		// Check default fields and values.
		$filter_fields = [
			'Name' => [
				'default' => '',
				'maxlength' => 255
			],
			'State' => [
				'default' => 'Any',
				'options' => ['Any', 'Online', 'Degrading', 'Offline', 'Recovering']
			]
		];

		foreach ($filter_fields as $field_name => $field_params) {
			$filter_field = $filter_form->getField($field_name);
			$this->assertEquals($field_params['default'], $filter_field->getValue());

			if (array_key_exists('maxlength', $field_params)) {
				$this->assertEquals($field_params['maxlength'], $filter_field->getAttribute('maxlength'));
			}

			if (array_key_exists('options', $field_params)) {
				$this->assertEquals($field_params['options'], $filter_field->getLabels()->asText());
			}
		}

		$this->assertEquals(['Apply', 'Reset'], $filter_form->query('tag:button')->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->asText()
		);

		// Check filter collapse/expand.
		$filter = CFilterElement::find()->one();

		foreach ([false, true] as $visible) {
			$filter->expand($visible);
			$this->assertTrue($filter->isExpanded($visible));
		}

		$table = $this->query('class:list-table')->asTable()->one()->waitUntilPresent();
		$this->assertEquals(['', 'Name', 'State', 'Failover period', 'Online proxies', 'Minimum proxies', 'Proxies'],
				$table->getHeadersText()
		);

		// Column Proxies occupies 2 columns, which is why the proxy list needs to be addressed via index (7).
		$proxy_groups = [
			[
				'Name' => '2nd Online proxy group',
				'State' => 'Online',
				'Failover period' => '666',
				'Online proxies' => '1',
				'Minimum proxies' => '666',
				'Proxies' => '2',
				7 => 'active_proxy3, active_proxy5'
			],
			[
				'Name' => 'Default values - recovering',
				'State' => 'Recovering',
				'Failover period' => '1m',
				'Online proxies' => '0',
				'Minimum proxies' => '1',
				'Proxies' => '1',
				7 => 'passive_proxy7'
			],
			[
				'Name' => 'Degrading proxy group',
				'State' => 'Degrading',
				'Failover period' => '15m',
				'Online proxies' => '0',
				'Minimum proxies' => '100',
				'Proxies' => '3',
				7 => 'Passive proxy 1, passive_proxy1, passive_unsupported'
			],
			[
				'Name' => 'Delete me 1',
				'State' => '',
				'Failover period' => '10',
				'Online proxies' => '0',
				'Minimum proxies' => '1',
				'Proxies' => '',
				7 => ''
			],
			[
				'Name' => 'Delete me 2',
				'State' => '',
				'Failover period' => '10',
				'Online proxies' => '0',
				'Minimum proxies' => '1',
				'Proxies' => '',
				7 => ''
			],
			[
				'Name' => 'Group without proxies',
				'State' => '',
				'Failover period' => '899',
				'Online proxies' => '0',
				'Minimum proxies' => '999',
				'Proxies' => '',
				7 => ''
			],
			[
				'Name' => 'Group without proxies with linked host',
				'State' => '',
				'Failover period' => '10',
				'Online proxies' => '0',
				'Minimum proxies' => '1',
				'Proxies' => '',
				7 => ''
			],
			[
				'Name' => 'Offline group',
				'State' => 'Offline',
				'Failover period' => '900s',
				'Online proxies' => '0',
				'Minimum proxies' => '1',
				'Proxies' => '1',
				7 => 'active_proxy7'
			],
			[
				'Name' => 'Online proxy group',
				'State' => 'Online',
				'Failover period' => '10',
				'Online proxies' => '3',
				'Minimum proxies' => '1',
				'Proxies' => '6',
				7 => 'Active proxy 1, Active proxy 2, Active proxy 3, Active proxy to delete, Proxy_1 for filter,'.
						' Proxy_2 for filter'
			],
			[
				'Name' => '⭐️😀⭐Smiley प्रॉक्सी 团体⭐️😀⭐ - unknown',
				'State' => 'Unknown',
				'Failover period' => '123s',
				'Online proxies' => '0',
				'Minimum proxies' => '123',
				'Proxies' => '1',
				7 => 'passive_outdated'
			]
		];

		$this->assertTableData($proxy_groups);

		$state_classes = [
			'Online' => 'status-green',
			'Offline' => 'status-red',
			'Recovering' => 'status-yellow',
			'Degrading' => 'status-yellow',
			'Unknown' => 'status-grey'
		];
		foreach($state_classes as $state_value => $state_class) {
			$this->assertTrue($table->findRow('State', $state_value)->query('xpath:.//span[text()='.
					CXPathHelper::escapeQuotes($state_value).']')->one()->hasClass($state_class)
			);
		}

		$group_count = count($proxy_groups);
		$this->assertTableStats($group_count);
		$action_buttons = $this->query('xpath://div[@id="action_buttons"]/button')->all();
		$this->assertEquals(['Delete'], $action_buttons->asText());

		$all_checkbox = $this->query('id:all_proxy_groups')->asCheckbox()->one();

		foreach ([true => $group_count, false => 0] as $enable => $selected_count) {
			$all_checkbox->fill($enable);
			$this->assertSelectedCount($selected_count);
			$this->assertEquals($enable, $action_buttons->first()->isClickable());
		}
	}

	public function getProxyGroupFilterData() {
		return [
			// #0 Complete "Name" match.
			[
				[
					'filter' => [
						'Name' => 'Offline group'
					],
					'result' => [
						'Offline group'
					]
				]
			],
			// #1 Partial "Name" match.
			[
				[
					'filter' => [
						'Name' => 'proxy'
					],
					'result' => [
						'2nd Online proxy group',
						'Degrading proxy group',
						'Online proxy group'
					]
				]
			],
			// #2 Search string with space in between.
			[
				[
					'filter' => [
						'Name' => 'e p'
					],
					'result' => [
						'2nd Online proxy group',
						'Online proxy group'
					]
				]
			],
			// #3 Search string with space in the beginning and in the end.
			[
				[
					'filter' => [
						'Name' => ' Online '
					],
					'result' => [
						'2nd Online proxy group'
					]
				]
			],
			// #4 Search string with utf8mb4 smiley.
			[
				[
					'filter' => [
						'Name' => '😀⭐'
					],
					'result' => [
						'⭐️😀⭐Smiley प्रॉक्सी 团体⭐️😀⭐ - unknown'
					]
				]
			],
			// #5 search should be case insensitive.
			[
				[
					'filter' => [
						'Name' => 'GrOuP'
					],
					'result' => [
						'2nd Online proxy group',
						'Degrading proxy group',
						'Group without proxies',
						'Group without proxies with linked host',
						'Offline group',
						'Online proxy group'
					]
				]
			],
			// #6 Return online proxy groups.
			[
				[
					'filter' => [
						'State' => 'Online'
					],
					'result' => [
						'2nd Online proxy group',
						'Online proxy group'
					]
				]
			],
			// #7 Return degrading proxy group.
			[
				[
					'filter' => [
						'State' => 'Degrading'
					],
					'result' => [
						'Degrading proxy group'
					]
				]
			],
			// #8 Return offline proxy group.
			[
				[
					'filter' => [
						'State' => 'Offline'
					],
					'result' => [
						'Offline group'
					]
				]
			],
			// #9 Return recovering proxy group.
			[
				[
					'filter' => [
						'State' => 'Recovering'
					],
					'result' => [
						'Default values - recovering'
					]
				]
			],
			// #10 Filter both by name and state.
			[
				[
					'filter' => [
						'Name' => 'group',
						'State' => 'Online'
					],
					'result' => [
						'2nd Online proxy group',
						'Online proxy group'
					]
				]
			],
			// #11 No matches for the specified filter.
			[
				[
					'filter' => [
						'Name' => 'Online',
						'State' => 'Degrading'
					],
					'result' => []
				]
			]
		];
	}

	/**
	 * @dataProvider getProxyGroupFilterData
	 */
	public function testPageAdministrationProxyGroups_Filter($data) {
		$this->page->login()->open('zabbix.php?action=proxygroup.list')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		// Reset filter in case if some filtering remained before ongoing test case.
		$form->query('button:Reset')->one()->click();

		// Fill filter form with data.
		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();

		// Check filtered result.
		$this->assertTableDataColumn($data['result']);
		$this->assertTableStats(count($data['result']));
	}

	public function testPageAdministrationProxyGroups_Sort() {
		$this->page->login()->open('zabbix.php?action=proxygroup.list')->waitUntilReady();

		// Reset filter to avoid impact from the filter scenario on this test.
		$this->query('button:Reset')->one()->click();

		$table = $this->query('class:list-table')->asTable()->one()->waitUntilPresent();
		$names = $this->getTableColumnData('Name');

		// Sort names ascendingly.
		usort($names, function($a, $b) {
			return strcasecmp($a, $b);
		});
		$names_asc = $names;

		// Sort names descendingly.
		usort($names, function($a, $b) {
			return strcasecmp($b, $a);
		});
		$names_desc = $names;

		// Check ascending and descending sorting of proxy group names.
		foreach ([$names_desc, $names_asc] as $sorted_names) {
			$table->query('link:Name')->waitUntilClickable()->one()->click();
			$table->waitUntilReloaded();
			$this->assertTableDataColumn($sorted_names);
		}
	}

	public static function getDeleteData() {
		return [
			// Attempt to delete a proxy group that has proxies assigned.
			[
				[
					'expected' => TEST_BAD,
					'groups' => ['Default values - recovering'],
					'error' => 'Proxy group "Default values - recovering" is used by proxy "passive_proxy7".'
				]
			],
			// Attempt to delete a proxy group that has no proxies but has an assigned host.
			[
				[
					'expected' => TEST_BAD,
					'groups' => ['Group without proxies with linked host'],
					'error' => 'Host "Host linked to proxy group" is monitored by proxy group "Group without proxies'.
							' with linked host".'
				]
			],
			// Attempt to delete two proxy groups one of which has a linked proxy.
			[
				[
					'expected' => TEST_BAD,
					'groups' => ['Offline group', 'Group without proxies'],
					'error' => 'Proxy group "Offline group" is used by proxy "active_proxy7".'
				]
			],
			// Delete a proxy group that has nothing linked to it.
			[
				[
					'groups' => ['Group without proxies']
				]
			],
			// Delete multiple proxy groups that have nothing linked to them.
			[
				[
					'groups' => ['Delete me 1', 'Delete me 2']
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testPageAdministrationProxyGroups_Delete($data) {
		$sql = 'SELECT * FROM proxy_group';
		$multiple_groups = count($data['groups']) > 1;

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->page->login()->open('zabbix.php?action=proxygroup.list')->waitUntilReady();
		$this->query('class:list-table')->asTable()->one()->findRows('Name', $data['groups'])->select();
		$this->query('button:Delete')->waitUntilClickable()->one()->click();

		$this->assertTrue($this->page->isAlertPresent());
		$expected_alert = ($multiple_groups)
			? 'Delete selected proxy groups?'
			: 'Delete selected proxy group?';
		$this->assertEquals($expected_alert, $this->page->getAlertText());
		$this->page->acceptAlert();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$title = ($multiple_groups)
				? 'Cannot delete proxy groups'
				: 'Cannot delete proxy group';
			$this->assertMessage(TEST_BAD, $title, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));

			foreach ($data['groups'] as $proxy_group) {
				$this->assertTrue($this->query('link', $proxy_group)->exists());
			}
		}
		else {
			$title = ($multiple_groups)
				? 'Proxy groups deleted'
				: 'Proxy group deleted';
			$this->assertMessage(TEST_GOOD, $title);

			foreach ($data['groups'] as $deleted_group) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM proxy_group WHERE name='
						.zbx_dbstr($deleted_group))
				);
				$this->assertFalse($this->query('link', $deleted_group)->exists());
			}
		}
	}
}
