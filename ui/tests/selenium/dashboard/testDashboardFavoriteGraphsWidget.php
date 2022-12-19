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
 *
 * @onBefore prepareDashboardData
 */
class testDashboardFavoriteGraphsWidget extends CWebTest {

	protected static $dashboardid;
	public $graph_cpu = 'CPU utilization';
	public $host_name = 'ЗАББИКС Сервер';
	public $graph_memory = 'Available memory in %';

	public static function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard with favorite graphs widget',
				'private' => 1,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'favgraphs',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 4
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = $response['dashboardids'][0];
	}

	public function testDashboardFavoriteGraphsWidget_AddFavoriteGraphs() {
		$cpu_itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE hostid=10084 AND name='.zbx_dbstr($this->graph_cpu));
		$memory_itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE hostid=10084 AND name='.zbx_dbstr($this->graph_memory));

		$this->page->login()->open('zabbix.php?action=latest.view&filter_selected=0&filter_reset=1')->waitUntilReady();
		$this->page->assertHeader('Latest data');
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$table = $this->query('xpath://table['.CXPathHelper::fromClass('overflow-ellipsis').']')->asTable()->one();

		foreach ([$this->graph_cpu, $this->graph_memory] as $graph) {
			$filter->fill(['Hosts' => $this->host_name, 'Name' => $graph]);
			$filter->submit();
			$table->waitUntilReloaded()->invalidate();
			$table->findRow('Host', $this->host_name)->query('link:Graph')->waitUntilClickable()->one()->click();

			// Add graph to favorite.
			$this->page->waitUntilReady();
			$button = $this->query('xpath://button[@id="addrm_fav"]')->waitUntilVisible()->one();
			$this->assertEquals('Add to favorites', $button->getAttribute('title'));
			$button->waitUntilClickable()->click();
			$button->waitUntilAttributesPresent(['title' => 'Remove from favorites']);
			$this->page->open('zabbix.php?action=latest.view')->waitUntilReady();
			$this->query('button:Reset')->waitUntilClickable()->one()->click();
			$table->waitUntilReloaded();
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$widget = CDashboardElement::find()->one()->getWidget('Favorite graphs')->waitUntilReady()->getContent();

		// Check favorite graphs in widget.
		foreach ([$this->graph_cpu => $cpu_itemid, $this->graph_memory => $memory_itemid] as $graph => $itemid) {
			$this->assertEquals('history.php?action=showgraph&itemids%5B0%5D='.$itemid,
					$widget->query('link', $this->host_name.': '.$graph)->one()->getAttribute('href')
			);
			$this->assertEquals(1, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.
					zbx_dbstr('web.favorite.graphids').' AND value_id='.zbx_dbstr($itemid))
			);
		}
	}

	public function testDashboardFavoriteGraphsWidget_RemoveFavoriteGraphs() {
		$favorite_graphs = CDBHelper::getAll('SELECT value_id FROM profiles WHERE idx='.zbx_dbstr('web.favorite.graphids'));

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$widget = CDashboardElement::find()->one()->getWidget('Favorite graphs')->waitUntilReady()->getContent();

		foreach ($favorite_graphs as $graph) {
			// Added variable due to External Hook.
			$xpath = './/button[@data-itemid='.CXPathHelper::escapeQuotes($graph['value_id']);
			$remove_item = $widget->query('xpath', $xpath.' and contains(@onclick, "rm4favorites")]')->waituntilClickable()->one();
			$remove_item->click();
			$remove_item->waitUntilNotVisible();
		}

		$this->assertEquals('No graphs added.', $widget->query('class:nothing-to-show')->one()->getText());
		$this->assertEquals(0, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.
				zbx_dbstr('web.favorite.graphids'))
		);
	}
}
