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

require_once dirname(__FILE__).'/../include/CWebTest.php';

class testPageMonitoringHostsGraph extends CWebTest {

	public function testPageMonitoringHostsGraph_Layout() {
		$this->page->login()->open('zabbix.php?view_as=showgraph&action=charts.view&from=now-1h&to'.
				'=now&filter_search_type=0&filter_set=1');
		$this->page->assertHeader('Graphs');
		$this->page->assertTitle('Custom graphs');

		// If the filter is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		$form = $this->query('name:zbx_filter')->asform()->one();

		// There is 2 graphs because second appears after you click pattern.
		$this->assertEquals(['Host', 'Search type', 'Graphs', 'Graphs'], $form->getLabels()->asText());

		// Check that Strict search type is selected first.
		$this->assertEquals('Strict', $this->query('id:filter_search_type')->asSegmentedRadio()->one()->getText());

		// Check placeholders.
		foreach (['filter_hostids__ms', 'filter_graphids__ms', 'filter_graph_patterns__ms'] as $id) {
			if ($id === 'filter_graph_patterns__ms') {
				$form->fill(['Search type' => 'Pattern']);
				$placeholder = 'graph pattern';
			}
			else {
				$placeholder = 'type here to search';
			}
			$this->assertEquals($placeholder, $form->query('id', $id)->one()->getAttribute('placeholder'));
		}

		$this->assertEquals('Specify host to see the graphs.',
			$this->query('xpath://table[@class="list-table"]//td')->one()->getText()
		);
	}

	public static function getCheckFilterData() {
		return [
			[
				[
					'filter' => [
						'Host' => 'Dynamic widgets H1'
					],
					'graphs_amount' => 4,
					'items_names' => ['Dynamic widgets H1I1', 'Dynamic widgets H1I2', 'Dynamic widgets H3I1']
				]
			],
			[
				[
					'filter' => [
						'Search type' => 'Strict',
						'Graphs' => 'Graph ZBX6663 Second'
					],
					'graphs_amount' => 1,
					'items_names' => ['Item ZBX6663 Second']
				]
			],
			[
				[
					'filter' => [
						'Host' => 'Host to delete graphs',
						'Search type' => 'Pattern'
					],
					'graphs_amount' => 5,
					'items_names' => ['Item to delete graph']
				]
			],
			[
				[
					'filter' => [
						'Host' => 'Host to delete graphs',
						'Search type' => 'Pattern',
						'Graphs' => 'Delete graph 1'
					],
					'graphs_amount' => 1,
					'items_names' => ['Item to delete graph']
				]
			],
			[
				[
					'filter' => [
						'Host' => 'Dynamic widgets H1',
						'Search type' => 'Strict',
						'Graphs' => 'Dynamic widgets H1 G1 (I1)'
					],
					'graphs_amount' => 1,
					'items_names' => ['Dynamic widgets H1I1']
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckFilterData
	 */
	public function testPageMonitoringHostsGraph_CheckFilter($data) {
		$this->page->login()->open('zabbix.php?view_as=showgraph&action=charts.view&from=now-1h&to=now&filter_search_type=0&filter_set=1');

		// Checking that graph filter is activated and visible.
		if ($this->query('xpath://li[@aria-controls="tab_2"]')->one()->getAttribute('aria-selected') == 'false') {
			$this->query('xpath://ul[@role="tablist"]/li[@aria-controls="tab_2"]/a')->one()->click();
		}

		$form = $this->query('name:zbx_filter')->one()->asForm();
		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();
		$graphs_count = $this->query('xpath://tbody/tr/div[@class="flickerfreescreen"]')->all()->count();
		$this->assertEquals($data['graphs_amount'], $graphs_count);

		// Checking from Values view.
		$this->query('id:view_as')->asDropdown()->one()->select('Values');
		$this->page->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();
		foreach ($data['items_names'] as $item) {
			$this->assertTrue($table->query('xpath://tr/th[@title="'.$item.'"]')->exists());
		}
	}
}
