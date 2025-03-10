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


require_once __DIR__.'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup graphs
 */
class testPageHostGraph extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	public function testPageHostGraph_CheckLayout() {
		$host_name = 'Host to check graph 1';

		// Get graphs data on host.
		$sql = 'SELECT graphid, name, width, height, graphtype'.
				' FROM graphs'.
				' WHERE graphid IN'.
					'(SELECT graphid'.
					' FROM graphs_items'.
					' WHERE itemid IN'.
						'(SELECT itemid'.
						' FROM items'.
						' WHERE hostid IN'.
							'(SELECT hostid'.
							' FROM hosts'.
							' WHERE host='.zbx_dbstr($host_name).
							')'.
						')'.
					')'.
				'ORDER BY name';

		$hostid = $this->openPageHostGraphs($host_name, 'host');
		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestCheckHeader('Graphs');

		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->checkValue(['Hosts' => $host_name]);

		$this->zbxTestAssertElementPresentXpath('//button[@type="button"][text()="Create graph"]');
		$this->zbxTestAssertElementPresentXpath('//span[@class="green"][text()="Enabled"]');
		$this->zbxTestAssertElementPresentXpath('//span[@class="status-grey"][text()="ZBX"]');

		// Check host breadcrumbs text and url.
		$filter->getField('Hosts')->fill($host_name);
		$filter->submit();
		$this->page->waitUntilReady();
		$breadcrumbs = [
			self::HOST_LIST_PAGE => 'All hosts',
			'zabbix.php?action=popup&popup=host.edit&hostid='.$hostid => $host_name,
			'zabbix.php?action=item.list&filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context=host' => 'Items',
			'zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context=host' => 'Triggers',
			'graphs.php?filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context=host' => 'Graphs',
			'host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context=host' => 'Discovery rules',
			'httpconf.php?filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context=host' => 'Web scenarios'
		];
		$count_items = CDBHelper::getValue('SELECT COUNT(*) FROM items WHERE hostid='.$hostid);
		$count_graphs = CDBHelper::getCount($sql);

		foreach ($breadcrumbs as $url => $text) {
			$this->zbxTestAssertElementPresentXpath('//a[@href="'.$url.'"][text()="'.$text.'"]');

			// Check item and graph count.
			if ($text === 'Items' || $text === 'Graphs') {
				$get_number = $this->zbxTestGetText('//a[@href="'.$url.'"]/..//sup');
				$this->assertEquals($get_number, ($text === 'Items') ? $count_items : $count_graphs);
			}
		}

		// Check table headers on page.
		$xpath = '//form[@name="graphForm"]//thead/tr/th[not(@class)]';
		$get_headers = $this->query('xpath', $xpath)->all();
		foreach ($get_headers as $row) {
			$table_headers[] = $row->getText();
		}
		$this->assertEquals(['Name', 'Width', 'Height', 'Graph type', 'Info'], $table_headers);

		// Check graph configuration parameters.
		$types = ['Normal', 'Stacked', 'Pie', 'Exploded'];

		foreach (CDBHelper::getAll($sql) as $graph) {
			// Get graph row in table.
			$element = $this->query('xpath://table[@class="list-table"]/tbody//input[@value="'.
					$graph['graphid'].'"]/../..')->one();

			// Check name value.
			$this->assertEquals($graph['name'],
					$element->query('xpath:./td/a[@href="graphs.php?form=update&graphid='.
							$graph['graphid'].'&context=host&filter_hostids%5B0%5D='.$hostid.'"]')->one()->getText()
			);

			// Check width value.
			$this->assertEquals($graph['width'], $element->query('xpath:./td[3]')->one()->getText());
			// Check height value.
			$this->assertEquals($graph['height'], $element->query('xpath:./td[4]')->one()->getText());
			// Check graph type value.
			$this->assertEquals($types[$graph['graphtype']], $element->query('xpath:./td[5]')->one()->getText());
		}

		// Check table footer.
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying '.$count_graphs.' of '
				.$count_graphs.' found'
		);

		$this->zbxTestAssertElementText('//span[@id="selected_count"]', '0 selected');
	}

	public static function getCopyData() {
		return [
			// Copy to host.
			// Copy without target.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 3',
						'Delete graph 4'
					],
					'target_type' => 'Hosts',
					'group' => 'Group for host graph check',
					'error' => 'Field "copy_targetids" is mandatory.'
				]
			],
			// Copy graph to the same host.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 1'
					],
					'target_type' => 'Hosts',
					'group' => 'Group for host graph check',
					'targets' => [
						'Host to delete graphs'
					],
					'error' => 'Graph "Delete graph 1" already exists on the host "Host to delete graphs".'
				]
			],
			// Copy graph to host without item.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 3'
					],
					'target_type' => 'Hosts',
					'group' => 'Empty group',
					'targets' => [
						'Empty host'
					],
					'error' => 'Cannot copy graph "Delete graph 3", because the item with key "graph[1]" does not '.
							'exist on the host "Empty host".'
				]
			],
			// Copy several graphs to host.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 5',
						'Delete graph 2'
					],
					'target_type' => 'Hosts',
					'group' => 'Group for host graph check',
					'targets' => [
						'Host to check graph 1'
					]
				]
			],
			// Graph already exist at target host.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 5'
					],
					'target_type' => 'Hosts',
					'group' => 'Group for host graph check',
					'targets' => [
						'Host to check graph 1'
					],
					'error' => 'Graph "Delete graph 5" already exists on the host "Host to check graph 1".'
				]
			],
			// Copy all graphs to host.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => 'all',
					'target_type' => 'Hosts',
					'group' => 'Group for host graph check',
					'targets' => [
						'Host to check graph 2'
					]
				]
			],
			// Copy graphs to hosts.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 4'
					],
					'target_type' => 'Hosts',
					'group' => 'Group for host graph check',
					'targets' => [
						'Host to check graph 3',
						'Host to check graph 4',
						'Host to check graph 5'
					]
				]
			],
			// Copy to host group.
			// Copy without target selection.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 3',
						'Delete graph 4'
					],
					'target_type' => 'Host groups',
					'error' => 'Field "copy_targetids" is mandatory.'
				]
			],
			// Copy graph to host group without item.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 3'
					],
					'target_type' => 'Host groups',
					'targets' => [
						'Empty group'
					],
					'error' => 'Cannot copy graph "Delete graph 3", because the item with key "graph[1]" does not '.
							'exist on the host "Empty host".'
				]
			],
			// Copy several graphs to host group.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 3',
						'Delete graph 4'
					],
					'target_type' => 'Host groups',
					'targets' => [
						'Group to copy graph'
					]
				]
			],
			// Graph already exist at target host group.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 3',
						'Delete graph 4'
					],
					'target_type' => 'Host groups',
					'targets' => [
						'Group to copy graph'
					],
					'error' => 'Graph "Delete graph 3" already exists on the host "Host with item and without graph 1".'
				]
			],
			// Copy all graphs to host group.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => 'all',
					'target_type' => 'Host groups',
					'targets' => [
						'Group to copy all graph'
					]
				]
			],
			// Copy graph to host groups.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 1'
					],
					'target_type' => 'Host groups',
					'targets' => [
						'Copy graph to several groups 1',
						'Copy graph to several groups 2'
					]
				]
			],
			// Copy to template.
			// Copy without target selection.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 3',
						'Delete graph 4'
					],
					'target_type' => 'Templates',
					'group' => 'Templates',
					'error' => 'Field "copy_targetids" is mandatory.'
				]
			],
			// Copy graph to the same template.
			[
				[
					'host' => 'Template to test graphs',
					'copy_from_template' => true,
					'graph' => [
						'Graph to check copy'
					],
					'target_type' => 'Templates',
					'group' => 'Templates',
					'targets' => [
						'Template to test graphs'
					],
					'error' => 'Graph "Graph to check copy" already exists on the template "Template to test graphs".'
				]
			],
			// Copy graph to template without item.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 3',
						'Delete graph 4'
					],
					'target_type' => 'Templates',
					'group' => 'Templates',
					'targets' => [
						'Empty template'
					],
					'error' => 'Cannot copy graph "Delete graph 3", because the item with key "graph[1]" does not '.
							'exist on the template "Empty template".'
				]
			],
			// Copy several graphs to template.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 3',
						'Delete graph 4'
					],
					'target_type' => 'Templates',
					'group' => 'Templates',
					'targets' => [
						'Template with item graph'
					]
				]
			],
			// Graph already exist at target template.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 3',
						'Delete graph 4'
					],
					'target_type' => 'Templates',
					'group' => 'Templates',
					'targets' => [
						'Template with item graph'
					],
					'error' => 'Graph "Delete graph 3" already exists on the template "Template with item graph".'
				]
			],
			// Copy all graphs to host group.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => 'all',
					'target_type' => 'Templates',
					'group' => 'Templates',
					'targets' => [
						'Template with item graph for copy all graph'
					]
				]
			],
			// Copy graph to several templates.
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 2'
					],
					'target_type' => 'Templates',
					'group' => 'Templates',
					'targets' => [
						'Template to copy graph to several templates 1',
						'Template to copy graph to several templates 2'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCopyData
	 */
	public function testPageHostGraph_CopySelected($data) {
		$this->selectGraph($data);
		$this->zbxTestClickButtonText('Copy');

		$dialog = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$copy_type = 'copy_type_'.array_search($data['target_type'], ['Template groups', 'Host groups', 'Templates', 'Hosts']);
		$this->zbxTestClickXpathWait('//label[@for="'.$copy_type.'"][text()="'.$data['target_type'].'"]');

		// Select check boxes of defined targets.
		if (array_key_exists('targets', $data)) {
			$this->zbxTestClickButtonMultiselect('copy_targetids');
			$this->zbxTestLaunchOverlayDialog($data['target_type']);
			$hosts_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();

			// Select hosts or templates.
			if ($data['target_type'] === 'Hosts' || $data['target_type'] === 'Templates') {
				// Select host group.
				$hosts_dialog->query('button:Select')->one()->click();
				$this->zbxTestLaunchOverlayDialog(rtrim($data['target_type'], "s").' groups');
				COverlayDialogElement::find()->all()->last()->waitUntilReady()->query('link', $data['group'])->waitUntilVisible()->one()->click();
				COverlayDialogElement::find()->all()->waitUntilReady();
				foreach ($data['targets'] as $target) {
					$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($target));
					$this->zbxTestCheckboxSelect('item_'.$hostid);
				}
			}
			// Select host groups.
			else {
				foreach ($data['targets'] as $target) {
					$groupid = CDBHelper::getValue('SELECT groupid FROM hstgrp WHERE name='.zbx_dbstr($target));
					$this->zbxTestCheckboxSelect('item_'.$groupid);
				}
			}

			$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');
		}

		$dialog->submit();

		if (array_key_exists('error', $data)) {
			$this->assertMessage(TEST_BAD, null, $data['error']);
		}
		else {
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::className('msg-good'));
			$this->zbxTestAssertElementPresentXpath('//output[@class="msg-good"]/span[contains(text(),"copied")]');

			// DB check, if copy target was host or template.
			if ($data['target_type'] === 'Hosts' || $data['target_type'] === 'Templates') {
				// Save graph data of original host.
				$original = $this->getGraphHash($data, $data['host']);
				// Save graph data of copy target.
				foreach ($data['targets'] as $target) {
					$this->assertEquals($original, $this->getGraphHash($data, $target));
				}
			}
			// DB check, if copy target is host group.
			elseif ($data['target_type'] === 'Host groups') {
				// Save graph data of original host.
				$original = $this->getGraphHash($data, $data['host']);
				// Get every host from the group.
				foreach ($data['targets'] as $target) {
					$group_host = CDBHelper::getAll(
						'SELECT host'.
						' FROM hosts'.
						' WHERE hostid IN ('.
							'SELECT hostid'.
							' FROM hosts_groups'.
							' WHERE groupid IN ('.
								'SELECT groupid'.
								' FROM hstgrp'.
								' WHERE name='.zbx_dbstr($target).
							')'.
						')'
					);

					// Check DB with every host
					foreach ($group_host as $host) {
						$name = $host['host'];
						// Save graph data of copy target - host group
						$this->assertEquals($original, $this->getGraphHash($data, $name));
					}
				}
			}
		}
	}

	/**
	 * Get data from DB
	 * @param type $data test case data from data provider.
	 * @param type $hosts host or template name, depends on target type.
	 */
	private function getGraphHash($data, $hosts) {
		$names = [];
		// Get graph names in string, if need to copy ALL graphs of the host.
		if ($data['graph'] === 'all') {
			$graphs = CDBHelper::getAll(
				'SELECT name'.
				' FROM graphs'.
				' WHERE graphid IN ('.
					'SELECT graphid'.
					' FROM graphs_items'.
					' WHERE itemid IN ('.
						'SELECT itemid'.
						' FROM items'.
						' WHERE hostid IN ('.
							'SELECT hostid'.
							' FROM hosts'.
							' WHERE host='.zbx_dbstr($data['host']).
						')'.
					')'.
				')'
			);

			foreach ($graphs as $graph) {
				$names[] = zbx_dbstr($graph['name']);
			}
		}
		// Get graph names in string.
		else {
			foreach ($data['graph'] as $graph) {
				$names[] = zbx_dbstr($graph);
			}
		}
		// If $host is not array, then remake it in array.
		if (!is_array($hosts)) {
			$hosts = [$hosts];
		}

		// Execute zbx_dbstr to every element of array.
		array_walk($hosts, function (&$host) {
			$host = zbx_dbstr($host);
		});

		// Select graphs by hostid or templateid.
		return CDBHelper::getHash(
			'SELECT name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers,'.
				' graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, flags,'.
				' ymin_itemid, ymax_itemid'.
			' FROM graphs'.
			' WHERE name IN ('.implode(',', $names).')'.
				' AND graphid IN ('.
					'SELECT graphid'.
					' FROM graphs_items'.
					' WHERE itemid IN ('.
						'SELECT itemid'.
						' FROM items'.
						' WHERE hostid IN ('.
							'SELECT hostid'.
							' FROM hosts'.
							' WHERE host IN ('.implode(',', $hosts).')'.
						')'.
					')'.
				')'.
			' ORDER BY name'
		);
	}

	public static function getDeleteData() {
		return [
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => [
						'Delete graph 3',
						'Delete graph 4'
					]
				]
			],
			[
				[
					'host' => 'Host to delete graphs',
					'graph' => 'all'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testPageHostGraph_DeleteSelected($data) {
		$this->selectGraph($data);
		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAcceptAlert();

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graphs deleted');
		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestCheckHeader('Graphs');

		if ($data['graph'] === 'all') {
			$sql = 'SELECT NULL'.
					' FROM graphs_items'.
					' WHERE itemid IN'.
						'(SELECT itemid'.
						' FROM items'.
						' WHERE hostid IN'.
							'(SELECT hostid'.
							' FROM hosts'.
							' WHERE host='.zbx_dbstr($data['host']).
							')'.
						')';
		}
		else {
			$names = [];
			foreach ($data['graph'] as $graph) {
				$names[] = zbx_dbstr($graph);
			}

			$sql = 'SELECT graphid'.
					' FROM graphs'.
					' WHERE name IN ('.implode(',', $names).')'.
					' AND graphid IN ('.
						'SELECT graphid'.
						' FROM graphs_items'.
						' WHERE itemid IN ('.
							'SELECT itemid'.
							' FROM items'.
							' WHERE hostid IN ('.
								'SELECT hostid'.
								' FROM hosts'.
								' WHERE host IN ('.zbx_dbstr($data['host']).')'.
							')'.
						')'.
					')'.
					' ORDER BY name';
		}

		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	public static function getFilterData() {
		return [
			[
				[
					'group' => 'Empty group',
					'host' => 'Empty host'
				]
			],
			[
				[
					'group' => 'Templates',
					'host' => 'Empty template',
					'context' => 'template'
				]
			],
			[
				[
					'group' => 'Empty group',
					'host' => 'all'
				]
			],
			[
				[
					'group' => 'all',
					'host' => 'Host to check graph 1',
					'graph' => [
						'Check graph 1',
						'Check graph 2'
					]
				]
			],
			[
				[
					'host' => 'Host to check graph 1',
					'change_group' => 'Empty group'
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageHostGraph_CheckFilter($data) {
		$context = CTestArrayHelper::get($data, 'context', 'host');
		$group_field = ($context === 'template') ? 'Template groups' : 'Host groups';
		$this->openPageHostGraphs($data['host'], $context);

		$table = $this->query('class:list-table')->one();
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		if (array_key_exists('group', $data)) {
			if ($data['group'] === 'all') {
				$filter->getField($group_field)->clear();
			}
			else {
				$filter->getField($group_field)->select($data['group']);
			}
		}

		$field_label = ucfirst($context).'s';

		if (array_key_exists('host', $data)) {
			if ($data['host'] === 'all') {
				$filter->getField($field_label)->clear();
			}
			else {
				$filter->getField($field_label)->fill($data['host']);
			}
			if (array_key_exists('change_group', $data)) {
				$filter->getField($group_field)->clear();
				$filter->getField($group_field)->select($data['change_group']);
			}
		}
		$filter->submit();
		$table->waitUntilReloaded();

		if ($data['host'] === 'all') {
			$this->zbxTestAssertElementPresentXpath(
					'//button[@id="form"][@disabled][text()="Create graph (select host first)"]'
			);
		}

		if (array_key_exists('graph', $data)) {
			foreach ($data['graph'] as $graph) {
				$this->assertTrue($this->query('xpath://a[contains(@href,"graphs.php?form=update")][text()="'.$graph.'"]')
						->one()->isVisible()
				);
			}
		}
		else {
			$this->assertEquals('No data found', $this->query('class:no-data-message')->one()->getText());
		}
	}

	private function openPageHostGraphs($host, $context) {
		$hostid = ($host !== 'all') ? CDBHelper::getValue('SELECT hostid FROM hosts where host='.zbx_dbstr($host)) : 0;

		$this->zbxTestLogin('graphs.php?filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context='.$context);

		return $hostid;
	}

	/**
	 * Select specified graphs.
	 *
	 * @param array $data	test case data from data provider
	 */
	private function selectGraph($data) {
		$context = (CTestArrayHelper::get($data, 'copy_from_template')) ? 'template' : 'host';
		$hostid = $this->openPageHostGraphs($data['host'], $context);

		if ($data['graph'] === 'all') {
			$this->zbxTestCheckboxSelect('all_graphs');
			return;
		}

		$result = DBselect(
			'SELECT graphid'.
			' FROM graphs'.
			' WHERE '.dbConditionString('name', $data['graph']).
			' AND graphid IN ('.
				'SELECT graphid'.
				' FROM graphs_items'.
				' WHERE itemid IN ('.
					'SELECT itemid'.
					' FROM items'.
					' WHERE hostid='.$hostid.
				')'.
			')'
		);

		while ($row = DBfetch($result)) {
			$this->zbxTestCheckboxSelect('group_graphid_'.$row['graphid']);
		}
	}
}
