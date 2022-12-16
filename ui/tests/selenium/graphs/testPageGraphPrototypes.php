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
class testPageGraphPrototypes extends CLegacyWebTest {

	/**
	 * Discovery rule "testFormDiscoveryRule" id used in test.
	 */
	const DISCOVERY_RULE_ID = 133800;

	/**
	 * Item prototype "testFormItemReuse" id used in test belong to discovery rule "testFormDiscoveryRule".
	 */
	const ITEM_PROTOTYPE_ID = 23804;

	/**
	 * Get all graph prototypes from Discovery rule "testFormDiscoveryRule"
	 * with item prototype "testFormItemReuse".
	 */
	private $sql_graph_prototypes;

	/**
	 * @inheritdoc
	 */
	public function __construct($name = null, array $data = [], $data_name = '') {
		parent::__construct($name, $data, $data_name);

		$this->sql_graph_prototypes = 'SELECT name, graphid, width, height, graphtype FROM graphs WHERE graphid IN (SELECT graphid FROM graphs_items WHERE itemid='.self::ITEM_PROTOTYPE_ID.')';
	}

	/**
	 * Get text of elements by xpath.
	 *
	 * @param string $xpath	xpath selector
	 *
	 * @return array
	 */
	private function getTextOfElements($xpath) {
		$result = [];

		foreach ($this->webDriver->findElements(WebDriverBy::xpath($xpath)) as $element) {
			$result[] = $element->getText();
		}

		return $result;
	}

	public function testPageGraphPrototypes_CheckLayout() {
		$this->zbxTestLogin('graphs.php?parent_discoveryid='.self::DISCOVERY_RULE_ID.'&context=host');
		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestCheckHeader('Graph prototypes');
		// Check create button.
		$this->zbxTestAssertElementText('//button[contains(@data-url, "form")]', 'Create graph prototype');

		// Check table headers.
		$this->assertEquals(['Name', 'Width', 'Height', 'Graph type', 'Discover'],
				$this->getTextOfElements("//form[@name=\"graphForm\"]//thead/tr/th[not(@class)]")
		);

		// Check graph prototype number in breadcrumb.
		$graphs = CDBHelper::getAll($this->sql_graph_prototypes);
		$count = count($graphs);
		$xpath = '//div[@class="header-navigation"]//a[text()="Graph prototypes"]/..//sup';
		$get_number = $this->zbxTestGetText($xpath);
		$this->assertEquals($get_number, $count);

		// Check graph prototype configuration parameters in table.
		$types = ['Normal', 'Stacked', 'Pie', 'Exploded'];
		foreach ($graphs as $graph) {
			// Check the graph names and get graph row in table.
			$element = $this->webDriver->findElement(WebDriverBy::xpath('//table[@class="list-table"]/tbody'
					.'//a[text()="'.$graph['name'].'"]/../..')
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

		// Check table footer to make sure that results are found
		$this->zbxTestAssertElementText("//span[@id='selected_count']", '0 selected');
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying '.$count.' of '.$count.' found');
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	/**
	 * Returns graph prototype ids.
	 */
	public static function getDeleteData() {
		return [
			[
				[
					'graphs' => 2
				]
			],
			[
				[
					'graphs' => 'all'
				]
			]
		];
	}

	/**
	 * Delete certain count of graph prototypes or all.
	 *
	 * @dataProvider getDeleteData
	 */
	public function testPageGraphPrototypes_Delete($data) {
		$this->zbxTestLogin('graphs.php?parent_discoveryid='.self::DISCOVERY_RULE_ID.'&context=host');
		$this->zbxTestCheckTitle('Configuration of graph prototypes');

		if ($data['graphs'] != 'all') {
			// Get random N graph prototypes, where N is count.
			$values = DBfetchArray(DBselect($this->sql_graph_prototypes));
			shuffle($values);

			// Select obtained graph prototypes.
			foreach (array_slice($values, 0, (int)$data['graphs']) as $graph) {
				$this->zbxTestCheckboxSelect('group_graphid_'.$graph['graphid']);
				$graphids[] = $graph['graphid'];
			}

			$this->zbxTestAssertElementText("//span[@id='selected_count']", $data['graphs'].' selected');

			$sql = 'SELECT NULL FROM graphs_items WHERE graphid IN ('.implode(',', $graphids).')';
		}
		else {
			$this->zbxTestCheckboxSelect('all_graphs');
			$sql = $this->sql_graph_prototypes;
			$this->zbxTestAssertElementText("//span[@id='selected_count']", CDBHelper::getCount($sql).' selected');
		}

		$this->zbxTestClickButton('graph.massdelete');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestCheckHeader('Graph prototypes');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph prototypes deleted');

		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Test impossible deleting of templated graph.
	 */
	public function testPageGraphPrototypes_CannotDelete() {
		$item_id = 15026;
		$parent_discovery_id = 15016;

		$sql_hash = 'SELECT *'.
				' FROM graphs'.
				' WHERE graphid IN ('.
					'SELECT graphid'.
					' FROM graphs_items'.
					' WHERE itemid='.$item_id.
				') ORDER BY graphid';

		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('graphs.php?parent_discoveryid='.$parent_discovery_id.'&context=host');
		$this->zbxTestCheckboxSelect('all_graphs');
		$this->zbxTestClickButton('graph.massdelete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete graph prototypes');
		$this->zbxTestTextPresentInMessageDetails('Cannot delete templated graph prototype.');

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}
}
