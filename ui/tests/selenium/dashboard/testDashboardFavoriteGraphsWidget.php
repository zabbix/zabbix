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

	public $graphCpu = 'CPU utilization';
	public $hostName = 'ЗАББИКС Сервер';
	public $graphMemory = 'Available memory in %';

	public static function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard with favorite graphs widget',
				'private' => 1,
				'pages' => [
					[
						'name' => 'Page 1',
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

	public function testDashboardFavoriteGraphsWidget_AddFavouriteGraphs() {
		$cpu_itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE hostid=10084 AND name='.zbx_dbstr($this->graphCpu));
		$memory_itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE hostid=10084 AND name='.zbx_dbstr($this->graphMemory));

		$this->zbxTestLogin('zabbix.php?action=latest.view&filter_selected=0&filter_reset=1');
		$this->zbxTestCheckHeader('Latest data');

		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->getField('Hosts')->fill($this->hostName);
		$filter->getField('Name')->fill($this->graphCpu);
		$filter->submit();
		$cpu_link = $this->query('link:Graph')->one();
		$cpu_link->click();

		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favorites');
		$this->zbxTestClickWait('addrm_fav');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//button[@id="addrm_fav" and @title="Remove from favorites"]'));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favorites');

		$this->page->open('zabbix.php?action=latest.view');
		$filter->invalidate();
		$filter->getField('Hosts')->fill($this->hostName);
		$filter->getField('Name')->fill($this->graphMemory);
		$filter->submit();
		$memory_link = $this->query('link:Graph')->one();
		$memory_link->click();

		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favorites');
		$this->zbxTestClickWait('addrm_fav');
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favorites']);
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favorites');

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$url_xpath = '//div[@class="dashboard-grid"]/div[2]//a[@href=';

		foreach ([$this->graphCpu => $cpu_itemid, $this->graphMemory => $memory_itemid] as $graph => $itemid) {
			$graph_url = 'history.php?action=showgraph&itemids%5B0%5D='.$itemid;
			$this->zbxTestAssertElementText($url_xpath.CXPathHelper::escapeQuotes($graph_url).']', 'ЗАББИКС Сервер: '.$graph);
			$this->assertEquals(1, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='
					.zbx_dbstr('web.favorite.graphids').' AND value_id='.$itemid)
			);
		}
	}

	public function testDashboardFavoriteGraphsWidget_RemoveFavouriteGraphs() {
		$exception = null;

		try {
			$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
			$FavouriteGraphs = DBfetchArray(DBselect('SELECT value_id FROM profiles WHERE idx='.zbx_dbstr('web.favorite.graphids')));
			foreach ($FavouriteGraphs as $FavouriteGraph) {
				$remove_item = $this->query('xpath://button[@data-itemid='.zbx_dbstr($FavouriteGraph['value_id']).
						' and contains(@onclick, "rm4favorites")]')->waituntilClickable()->one();
				$remove_item->click();
				$remove_item->waitUntilNotVisible();
			}
			$this->zbxTestAssertElementText('//div[@class="dashboard-grid-widget-container"]//tr[@class="nothing-to-show"]/td', 'No graphs added.');
			$this->assertEquals(0, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.zbx_dbstr('web.favorite.graphids')));
		}
		catch (Exception $e) {
			$exception = $e;
		}

		if ($exception !== null) {
			throw $exception;
		}
	}
}
