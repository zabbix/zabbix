<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

/**
 * @backup graphs
 */
class testPageHostGraph extends CWebTest {
	public static function getLayoutData() {
		return [
			[
				[
					'host' => 'Host to check graph 1'
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testPageHostGraph_CheckLayout($data) {
		$hostid = $this->openPageHostGraphs($data);
		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestCheckHeader('Graphs');
		$this->zbxTestDropdownHasOptions('groupid', ['all', 'Group for host graph check', 'Host group for tag permissions',
				'Templates', 'Templates/Applications', 'Templates/Databases', 'Templates/Modules', 'Templates/Network Devices',
				'Templates/Operating Systems', 'Templates/Servers Hardware', 'Templates/Virtualization', 'Zabbix servers',
				'ZBX6648 All Triggers', 'ZBX6648 Disabled Triggers', 'ZBX6648 Enabled Triggers']);
		$this->zbxTestDropdownHasOptions('hostid', ['all', 'Form test template', 'Host-layout-test-001', 'Host-map-test-zbx6840',
				'Host for different items types', 'Host for host prototype tests', 'Host for tag permissions',
				'Host for trigger description macros', 'Host to check graph 1', 'Host ZBX6663', 'Inheritance test template',
				'Inheritance test template 2', 'Inheritance test template for unlink', 'Simple form test host', 'Template-layout-test-001',
				'Template App Apache Tomcat JMX', 'Template App FTP Service', 'Template App Generic Java JMX',
				'Template App HTTP Service', 'Template App HTTPS Service']);
		$this->zbxTestAssertElementPresentXpath('//button[@type="button"][text()="Create graph"]');
		$this->zbxTestAssertElementPresentXpath('//a[@href="hosts.php?hostid='.$hostid.'&groupid=0"][text()="All hosts"]');
		$this->zbxTestAssertElementPresentXpath('//a[@href="hosts.php?form=update&hostid='.$hostid.'"][text()="'.$data['host'].'"]');
		$this->zbxTestAssertElementPresentXpath('//span[@class="green"][text()="Enabled"]');
		foreach (['zbx','snmp','jmx','ipmi'] as $text) {
			$this->zbxTestAssertElementPresentXpath('//span[@class="status-grey"][text()="'.$text.'"]');
		}
		$this->zbxTestAssertElementPresentXpath('//a[@href="applications.php?hostid='.$hostid.'"][text()="Applications"]');

		// Check item count
		$items = DBcount('SELECT 1 FROM items WHERE hostid='.$hostid);
		$this->zbxTestAssertElementPresentXpath('//a[@href="items.php?filter_set=1&hostid='.$hostid.'"][text()="Items"]/../sup[text()="'.$items.'"]');

		$this->zbxTestAssertElementPresentXpath('//a[@href="triggers.php?hostid='.$hostid.'"][text()="Triggers"]');
		// Check graph count
		$graph_counter = DBcount('SELECT 1 FROM graphs_items WHERE itemid in (SELECT itemid FROM items WHERE hostid='.zbx_dbstr($hostid).')');
		$this->zbxTestAssertElementPresentXpath('//a[@href="graphs.php?hostid='.$hostid.'"][text()="Graphs"]/../sup[text()="'.$graph_counter.'"]');

		$this->zbxTestAssertElementPresentXpath('//a[@href="host_discovery.php?hostid='.$hostid.'"][text()="Discovery rules"]');
		$this->zbxTestAssertElementPresentXpath('//a[@href="httpconf.php?hostid='.$hostid.'"][text()="Web scenarios"]');

		$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "graphs.php?groupid=0&hostid='.$hostid.'&sort=name")][text()="Name"]');
		$this->zbxTestAssertElementPresentXpath('//table[@class="list-table"]//th[text()="Width"]');
		$this->zbxTestAssertElementPresentXpath('//table[@class="list-table"]//th[text()="Height"]');
		$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "graphs.php?groupid=0&hostid='.$hostid.'&sort=graphtype")][text()="Graph type"]');

		// Check graph configuration parameters.
		$sql='SELECT graphid, name, width, height, graphtype'.
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
							' WHERE host='.zbx_dbstr($data['host']).
							')'.
						')'.
					')'.
				'ORDER BY name';
		$graphs = DBdata($sql);
		$types = ['Normal', 'Stacked', 'Pie', 'Exploded'];

		foreach ($graphs as $graph) {
			$graph = $graph[0];
			// Graph check and row selection.
			$element = $this->webDriver->findElement(WebDriverBy::xpath('//table[@class="list-table"]/tbody//input[@value="'.$graph['graphid'].'"]/../..'));
			// Check name value.
			$this->assertEquals($graph['name'], $element->findElement(WebDriverBy::xpath('./td/a[contains(@href, "graphs.php?form=update&graphid='.$graph['graphid'].'&hostid='.$hostid.'")]'))->getText());
			// Check width value.
			$this->assertEquals($graph['width'], $element->findElement(WebDriverBy::xpath('./td[3]'))->getText());
			// Check height value.
			$this->assertEquals($graph['height'], $element->findElement(WebDriverBy::xpath('./td[4]'))->getText());
			// Check graph type value
			$types[$graph['graphtype']];
			$this->assertEquals($types[$graph['graphtype']], $element->findElement(WebDriverBy::xpath('./td[5]'))->getText());
		}

		$this->zbxTestAssertElementPresentXpath('//div[@class="table-stats"][text()="Displaying '.$graph_counter.' of '.$graph_counter.' found"]');
		$this->zbxTestAssertElementPresentXpath('//span[@id="selected_count"][contains(text(),"selected")]');
		$this->zbxTestAssertElementPresentXpath('//button[@class="btn-alt"][@value="graph.masscopyto"][@disabled=""][text()="Copy"]');
		$this->zbxTestAssertElementPresentXpath('//button[@class="btn-alt"][@value="graph.massdelete"][@disabled=""][text()="Delete"]');
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
					'targets' => [
						''
					],
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
					'targets' => [
						''
					],
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
					'targets' => [
						''
					],
					'error' => 'No target selected.'
				]
			],
			// Copy graph to the same template.
			[
				[
					'host' => 'Template to test graphs',
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
						'Template with item graph[1]'
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
						'Template with item graph[1]'
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
						'Template with item graph[1] for copy all graph'
					]
				]
			],
		];
	}

	/**
	 * @dataProvider getCopyData
	 */
	public function testPageHostGraph_CopySelected($data) {
		$this->selectGraph($data);
		$this->zbxTestClickButtonText('Copy');
		$this->zbxTestDropdownSelectWait('copy_type', $data['target_type']);
		if (array_key_exists('group', $data)) {
			$this->zbxTestDropdownSelectWait('copy_groupid', $data['group']);
		}

		// Select check boxes of defined targets
		foreach ($data['targets'] as $target) {
			// Select host or template id
			if ($data['target_type'] === 'Hosts' || $data['target_type'] === 'Templates') {
				$result = DBselect('SELECT hostid FROM hosts WHERE host='. zbx_dbstr($target));
				while ($row = DBfetch($result)) {
					$this->zbxTestCheckboxSelect('copy_targetid_'.$row['hostid']);
				}
			}
			// Select host group id
			else {
				$result = DBselect('SELECT groupid FROM hstgrp WHERE name='. zbx_dbstr($target));
				while ($row = DBfetch($result)) {
					$this->zbxTestCheckboxSelect('copy_targetid_'.$row['groupid']);
				}
			}
		}

		$this->zbxTestClick('copy');

		if (array_key_exists('error', $data)) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
		}
		else {
			$this->zbxTestAssertElementPresentXpath('//output[@class="msg-good"][contains(text(),"copied")]');
			// DB check, if copy target was host or template.
			if ($data['target_type'] === 'Hosts' || $data['target_type'] === 'Templates') {
				// Save graph data of original host.
				$target_names = [];
				foreach ($data['targets'] as $target) {
					$target_names[] = $target;
				}
				// Save graph data of original host.
				$original = $this->getGraphFromDb($data, $data['host']);
				// Save graph data of copy target.
				$copy = $this->getGraphFromDb($data, $target_names);
				$this->assertEquals(DBhash($original), DBhash($copy));
			}
			// DB check, if copy target is host group
			elseif ($data['target_type'] === 'Host groups') {
				// Save graph data of original host.
				$original = DBhash($this->getGraphFromDb($data, $data['host'], true));
				// Get every host from the group.
				foreach ($data['targets'] as $target) {
					$group_host = DBdata('SELECT host'.
							' FROM hosts'.
							' WHERE hostid IN ('.
								'SELECT hostid'.
								' FROM hosts_groups'.
								' WHERE groupid IN ('.
									'SELECT groupid'.
									' FROM hstgrp'.
									' WHERE name IN ('.zbx_dbstr($target).')))', false);
					// Check DB with every host
					foreach ($group_host as $host) {
						$name = $host[0]['host'];
						// Save graph data of copy target - host group
						$copy = $this->getGraphFromDb($data, $name);
						$this->assertEquals($original, DBhash($copy));
					}
				}
			}
		}
		$this->zbxTestCheckFatalErrors();
	}
	/**
	 * Get data from DB
	 * @param type $data test case data from data provider.
	 * @param type $hosts host or template name, depends on target type.
	 */
	private function getGraphFromDb($data, $hosts) {
		$names = [];
		// Get graph names in string, if need to copy ALL graphs of the host.
		if ($data['graph'] === 'all') {
			$sql = 'SELECT name'.
					' FROM graphs'.
					' WHERE graphid IN ('.
						'SELECT graphid'.
						' FROM graphs_items'.
						' WHERE itemid IN'.
							'(SELECT itemid'.
							' FROM items'.
							' WHERE hostid IN'.
								'(SELECT hostid'.
								' FROM hosts'.
								' WHERE host='.zbx_dbstr($data['host']).
								')'.
							')'.
						')';
			$result = DBdata($sql, false);
			foreach ($result as $graph) {
				$names[] = zbx_dbstr($graph[0]['name']);
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
		$sql = 'SELECT name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype,'.
				' show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type,flags, ymin_itemid, ymax_itemid'.
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
				' ORDER BY name';
		return $sql;
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
			],
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testPageHostGraph_DeleteSelected($data) {
		$this->selectGraph($data);
		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAcceptAlert();

		$this->zbxTestIsElementPresent('//*[@class="msg-good"]');
		$this->zbxTestCheckFatalErrors();
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

		$this->assertEquals(0, DBcount($sql));
	}
	public static function getFilterData() {
		return [
			[
				[
					'group' => 'Group for host graph check',
					'host' => 'Host to delete graphs'
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
					'group' => 'Templates',
					'host' => 'Empty template'
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
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageHostGraph_CheckFilter($data) {
		$this->zbxTestLogin('graphs.php?groupid=0&hostid=0');
		$this->zbxTestAssertElementPresentXpath('//button[@id="form"][@disabled][text()="Create graph (select host first)"]');
		$this->zbxTestDropdownSelect('groupid', $data['group']);
		$this->zbxTestDropdownSelect('hostid', $data['host']);
		if (array_key_exists('graph', $data)) {
			foreach ($data['graph'] as $graph) {
				$this->zbxTestAssertElementPresentXpath('//a[contains(@href,"graphs.php?form=update")][text()="'.$graph.'"]');
			}
		}
		else {
			$this->zbxTestAssertElementPresentXpath('//tr[@class="nothing-to-show"]/td[text()="No data found."]');
		}
	}

	private function openPageHostGraphs($data) {
		$hostid = DBfetch(DBselect('SELECT hostid FROM hosts where host='.zbx_dbstr($data['host'])));
		$hostid=$hostid['hostid'];
		$this->zbxTestLogin('graphs.php?groupid=0&hostid='.$hostid);
		return $hostid;
	}

	/**
	 * Select specified graphs.
	 *
	 * @param array $data	test case data from data provider
	 */
	private function selectGraph($data) {
		$this->openPageHostGraphs($data);

		if ($data['graph'] === 'all') {
			$this->zbxTestCheckboxSelect('all_graphs');
			return;
		}

		foreach ($data['graph'] as $graph) {
			$result = DBselect('SELECT graphid'.
					' FROM graphs'.
					' WHERE name='. zbx_dbstr($graph).
					' AND graphid IN ('.
						'SELECT graphid'.
						' FROM graphs_items'.
						' WHERE itemid IN ('.
							'SELECT itemid'.
							' FROM items'.
							' WHERE hostid IN ('.
								'SELECT hostid'.
								' FROM hosts'.
								' WHERE name='. zbx_dbstr($data['host']).
								')'.
							')'.
						')'.
					' ORDER BY name'
					);

			while ($row = DBfetch($result)) {
				$this->zbxTestCheckboxSelect('group_graphid_'.$row['graphid']);
			}
		}
	}
}
