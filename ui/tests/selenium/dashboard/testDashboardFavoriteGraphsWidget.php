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


require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * @backup profiles
 */
class testDashboardFavoriteGraphsWidget extends CWebTest {

	public $graph_cpu = 'CPU usage';
	public $host_name = 'ЗАББИКС Сервер';
	public $graph_cpuid = 910;
	public $graph_memory = 'Memory usage';
	public $graph_memoryid = 919;

	public function testDashboardFavoriteGraphsWidget_AddFavoriteGraphs() {
		$this->page->login()->open('zabbix.php?action=charts.view')->waitUntilReady();
		$this->page->assertHeader('Graphs');
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter_tab = $filter->query('id:tab_2')->one();

		if (!$filter_tab->isVisible()) {
			$this->query('xpath://a[text()="Filter"]')->one()->waitUntilClickable()->click();
			$filter_tab->waitUntilVisible();
		}

		foreach ([$this->graph_cpu, $this->graph_memory] as $graph) {
			$filter->fill(['Host' => $this->host_name, 'Graphs' => $graph]);
			$filter->submit();
			$this->page->waitUntilReady();

			$button = $this->query('xpath://button[@id="addrm_fav"]')->waitUntilVisible()->one();
			$this->assertEquals('Add to favourites', $button->getAttribute('title'));
			$button->waitUntilClickable()->click();
			$button->waitUntilAttributesPresent(['title' => 'Remove from favourites']);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1')->waitUntilReady();
		$widget = CDashboardElement::find()->one()->getWidget('Favourite graphs')->waitUntilReady()->getContent();

		// Check favorite graphs in widget and in DB.
		foreach ([$this->graph_cpu => $this->graph_cpuid, $this->graph_memory => $this->graph_memoryid] as $graph => $id) {
			$this->assertEquals('zabbix.php?action=charts.view&view_as=showgraph&filter_search_type=0&filter_graphids%5B0%5D='.
					$id.'&filter_set=1',
					$widget->query('link', $this->host_name.': '.$graph)->one()->getAttribute('href')
			);
			$this->assertEquals(1, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.
					zbx_dbstr('web.favorite.graphids').' AND value_id='.zbx_dbstr($id))
			);
		}
	}

	public function testDashboardFavoriteGraphsWidget_RemoveFavoriteGraphs() {
		$favorite_graphs = CDBHelper::getAll('SELECT value_id FROM profiles WHERE idx='.zbx_dbstr('web.favorite.graphids'));

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1')->waitUntilReady();
		$widget = CDashboardElement::find()->one()->getWidget('Favourite graphs')->waitUntilReady()->getContent();

		foreach ($favorite_graphs as $graph) {
			// Added variable due to External Hook.
			$xpath = ".//button[@onclick=\"rm4favorites('graphid','".$graph['value_id'];
			$remove_item = $widget->query('xpath', $xpath."')\"]")->waituntilClickable()->one();
			$remove_item->click();
			$remove_item->waitUntilNotVisible();
		}

		$this->assertEquals('No graphs added.', $widget->query('class:nothing-to-show')->one()->getText());
		$this->assertEquals(0, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.
				zbx_dbstr('web.favorite.graphids'))
		);
	}
}
