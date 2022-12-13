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


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup graphs
 */
class testPageHostGraph extends CLegacyWebTest {

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
		$breadcrumbs = [
			self::HOST_LIST_PAGE => 'All hosts',
			(new CUrl('zabbix.php'))
				->setArgument('action', 'host.edit')
				->setArgument('hostid', $hostid)
				->getUrl() => $host_name,
			'items.php?filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context=host' => 'Items',
			'triggers.php?filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context=host' => 'Triggers',
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
		$get_headers = $this->webDriver->findElements(WebDriverBy::xpath($xpath));
		foreach ($get_headers as $row) {
			$table_headers[] = $row->getText();
		}
		$this->assertEquals(['Name', 'Width', 'Height', 'Graph type', 'Info'], $table_headers);

		// Check graph configuration parameters.
		$types = ['Normal', 'Stacked', 'Pie', 'Exploded'];

		foreach (CDBHelper::getAll($sql) as $graph) {
			// Get graph row in table.
			$element = $this->webDriver->findElement(
					WebDriverBy::xpath('//table[@class="list-table"]/tbody//input[@value="'.$graph['graphid'].'"]/../..')
			);

			// Check name value.
			$this->assertEquals($graph['name'],
					$element->findElement(WebDriverBy::xpath('./td/a[@href="graphs.php?form=update&graphid='.
							$graph['graphid'].'&context=host&filter_hostids%5B0%5D='.$hostid.'"]'))->getText()
			);

			// Check width value.
			$this->assertEquals($graph['width'], $element->findElement(WebDriverBy::xpath('./td[3]'))->getText());
			// Check height value.
			$this->assertEquals($graph['height'], $element->findElement(WebDriverBy::xpath('./td[4]'))->getText());
			// Check graph type value.
			$this->assertEquals($types[$graph['graphtype']],
					$element->findElement(WebDriverBy::xpath('./td[5]'))->getText()
			);
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
					'error' => 'No target selected.'
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
					'error' => 'Graph "Delete graph 1" already exists on "Host to delete graphs".'
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
					'error' => 'Missing key "graph[1]" for host "Empty host".'
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
					'error' => 'Graph with name "Delete graph 5" already exists in graphs or graph prototypes.'
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
					'error' => 'No target selected.'
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
					'error' => 'Missing key "graph[1]" for host "Empty host".'
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
					'error' => 'Graph with name "Delete graph 3" already exists in graphs or graph prototypes.'
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
					'error' => 'No target selected.'
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
					'error' => 'Graph "Graph to check copy" already exists on "Template to test graphs".'
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
					'error' => 'Missing key "graph[1]" for host "Empty template".'
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
					'error' => 'Graph with name "Delete graph 3" already exists in graphs or graph prototypes.'
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

		$copy_type = 'copy_type_'.array_search($data['target_type'], ['Template groups', 'Host groups', 'Hosts', 'Templates']);
		$this->zbxTestClickXpathWait('//label[@for="'.$copy_type.'"][text()="'.$data['target_type'].'"]');

		// Select check boxes of defined targets.
		if (array_key_exists('targets', $data)) {
			$this->zbxTestClickButtonMultiselect('copy_targetids');
			$this->zbxTestLaunchOverlayDialog($data['target_type']);
			COverlayDialogElement::find()->one()->waitUntilReady();

			// Select hosts or templates.
			if ($data['target_type'] === 'Hosts' || $data['target_type'] === 'Templates') {
				// Select host group.
				COverlayDialogElement::find()->one()->query('class:multiselect-button')->one()->click();
				$this->zbxTestLaunchOverlayDialog(rtrim($data['target_type'], "s").' groups');
				COverlayDialogElement::find()->all()->last()->query('link', $data['group'])->waitUntilVisible()->one()->click();
				COverlayDialogElement::find()->one()->waitUntilReady();
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

		$this->zbxTestClick('copy');

		if (array_key_exists('error', $data)) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
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

		if ($data['host'] === 'all') {
			$this->zbxTestAssertElementPresentXpath(
					'//button[@id="form"][@disabled][text()="Create graph (select host first)"]'
			);
		}

		if (array_key_exists('graph', $data)) {
			foreach ($data['graph'] as $graph) {
				$this->zbxTestAssertElementPresentXpath(
						'//a[contains(@href,"graphs.php?form=update")][text()="'.$graph.'"]'
				);
			}
		}
		else {
			$this->zbxTestAssertElementPresentXpath('//tr[@class="nothing-to-show"]/td[text()="No data found."]');
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
