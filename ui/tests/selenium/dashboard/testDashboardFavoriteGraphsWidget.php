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
 * @backup profiles
 */
class testDashboardFavoriteGraphsWidget extends CLegacyWebTest {

	public $graphCpu = 'CPU usage';
	public $hostGroup = 'Zabbix servers';
	public $hostName = 'ЗАББИКС Сервер';
	public $graphCpuId = 910;
	public $graphMemory = 'Memory usage';
	public $graphMemoryId = 919;

	public function testDashboardFavoriteGraphsWidget_AddFavouriteGraphs() {
		$this->zbxTestLogin('zabbix.php?action=charts.view');
		$this->zbxTestCheckHeader('Graphs');
		$this->query('xpath://a[text()="Filter"]')->one()->click();
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->fill([
			'Host' => [
				'values' => $this->hostName,
				'context' => $this->hostGroup
			]
		]);
		$filter->getField('Graphs')->select($this->graphCpu);
		$filter->submit();
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favourites');
		$this->zbxTestClickWait('addrm_fav');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//button[@id="addrm_fav" and @title="Remove from favourites"]'));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favourites');

		$filter->query('button:Reset')->one()->click();
		$filter->invalidate();
		$filter->getField('Graphs')->select($this->graphMemory);
		$filter->submit();
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favourites');
		$this->zbxTestClickWait('addrm_fav');
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favourites']);
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favourites');

		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[9]//a[@href='zabbix.php?action=charts.view&view_as=showgraph&filter_search_type=0&filter_graphids%5B0%5D=$this->graphCpuId&filter_set=1']", 'ЗАББИКС Сервер: '.$this->graphCpu);
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[9]//a[@href='zabbix.php?action=charts.view&view_as=showgraph&filter_search_type=0&filter_graphids%5B0%5D=$this->graphMemoryId&filter_set=1']", 'ЗАББИКС Сервер: '.$this->graphMemory);
		$this->assertEquals(1, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids' AND value_id=$this->graphCpuId"));
		$this->assertEquals(1, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids' AND value_id=$this->graphMemoryId"));
	}

	public function testDashboardFavoriteGraphsWidget_RemoveFavouriteGraphs() {
		$exception = null;

		try {
			$this->zbxTestLogin('zabbix.php?action=dashboard.view');
			$FavouriteGraphs = DBfetchArray(DBselect("SELECT value_id FROM profiles WHERE idx='web.favorite.graphids'"));
			foreach ($FavouriteGraphs as $FavouriteGraph) {
				$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[9]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]"));
				$this->zbxTestClickXpathWait("//div[@class='dashbrd-grid-container']/div[9]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]");
				$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[9]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]"));
			}
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[9]//tr[@class='nothing-to-show']/td", 'No graphs added.');
			$this->assertEquals(0, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids'"));
		}
		catch (Exception $e) {
			$exception = $e;
		}

		if ($exception !== null) {
			throw $exception;
		}
	}
}
