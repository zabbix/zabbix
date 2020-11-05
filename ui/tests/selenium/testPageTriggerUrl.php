<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/CWebTest.php';

/**
 * Test checks link from trigger URL field on different pages.
 *
 * @backup profiles
 * @backup problem
 */
class testPageTriggerUrl extends CWebTest {

	public function getTriggerLinkData() {
		return [
			// Check tag priority.
			[
				[
					'trigger' => '1_trigger_High',
					'links' => [
						'Problems' => 'zabbix.php?action=problem.view&filter_name=&triggerids%5B%5D=100035',
						'Configuration' => 'triggers.php?form=update&triggerid=100035',
						'Trigger URL' => 'tr_events.php?triggerid=100035&eventid=9003',
						'Webhook url for all' => 'zabbix.php?action=mediatype.edit&mediatypeid=101',
						'Unique webhook url' => 'zabbix.php?action=mediatype.list&ddreset=1',
						'1_item' => 'history.php?action=showgraph&itemids%5B%5D=99086'
					],
					'background' => "high-bg"
				]
			],
			[
				[
					'trigger' => '1_trigger_Not_classified',
					'links' => [
						'Problems' => 'zabbix.php?action=problem.view&filter_name=&triggerids%5B%5D=100032',
						'Configuration' => 'triggers.php?form=update&triggerid=100032',
						'Trigger URL' => 'tr_events.php?triggerid=100032&eventid=9000',
						'Webhook url for all' => 'zabbix.php?action=mediatype.edit&mediatypeid=101',
						'1_item' => 'history.php?action=showgraph&itemids%5B%5D=99086'
					],
					'background' => 'na-bg'
				]
			]
		];
	}

	/**
	 * @dataProvider getTriggerLinkData
	 * Check trigger url in Problems widget.
	 */
	public function testPageTriggerUrl_ProblemsWidget($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1');
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget('Problems');
		$table = $widget->getContent()->asTable();

		// Find trigger and open trigger overlay dialogue.
		$table->query('link', $data['trigger'])->one()->click();
		$this->checkTriggerUrl(false, $data, false);
	}

	/**
	 * @dataProvider getTriggerLinkData
	 * Check trigger url in Trigger overview widget.
	 */
	public function testPageTriggerUrl_TriggerOverviewWidget($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=102');
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget('Group to check Overview');

		$table = $widget->getContent()->asTable();
		// Get row of trigger "1_trigger_Not_classified".
		$row = $table->findRow('Triggers', $data['trigger']);
		// Open trigger context menu.
		$row->query('xpath://td[contains(@class, "'.$data['background'].'")]')->one()->click();
		$this->checkTriggerUrl(true, $data);
	}

	/**
	 * @dataProvider getTriggerLinkData
	 * Check trigger url on Problems page.
	 */
	public function testPageTriggerUrl_ProblemsPage($data) {
		$this->page->login()->open('zabbix.php?action=problem.view');
		$table = $this->query('class:list-table')->asTable()->one();
		// Open trigger context menu.
		$table->query('link', $data['trigger'])->one()->click();
		$this->checkTriggerUrl(false, $data);
	}

	/**
	 * @dataProvider getTriggerLinkData
	 * Check trigger url on Overview page.
	 */
	public function testPageTriggerUrl_OverviewPage($data) {
		$this->page->login()->open('overview.php');

		$table = $this->query('class:list-table')->asTable()->one();
		// Get row of trigger "1_trigger_Not_classified".
		$row = $table->findRow('Triggers', $data['trigger']);

		// Open trigger context menu.
		$row->query('xpath://td[contains(@class, "'.$data['background'].'")]')->one()->click();
		$this->checkTriggerUrl(true, $data);
	}

	/**
	 * Check resolved trigger context menu on overview page, when {EVENT.ID} macro can't be resolved.
	 *
	 * @on-after resetFilter
	 */
	public function testPageTriggerUrl_OverviewPageResolvedTrigger() {
		$filter = [
			'Show' => 'Any',
			'Name' => '1_trigger_Average'
		];

		// Make trigger in resolved state.
		CDBHelper::setTriggerProblem($filter['Name'], TRIGGER_VALUE_FALSE);
		$this->page->login()->open('overview.php');
		// Apply filter options.
		$form = $this->query('xpath://form[@name="zbx_filter"]')->asForm()->one();
		$form->fill($filter);
		$form->submit();
		$this->page->waitUntilReady();

		$table = $this->query('class:list-table')->asTable()->one();
		// Get row of trigger "1_trigger_Average".
		$row = $table->findRow('Triggers', '1_trigger_Average');

		// Open trigger context menu.
		$row->query('xpath://td[contains(@class, "normal-bg")]')->one()->click();
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();

		// Make sure trigger url not visible, when EVENT.ID macro can't be resolved.
		$this->assertEquals(['TRIGGER', 'HISTORY'], $popup->getTitles()->asText());
	}

	public function resetFilter() {
		DBexecute('DELETE FROM profiles WHERE idx LIKE \'%web.overview.filter%\'');
	}

	/*
	 * Check resolved trigger context menu on overview page, if trigger url without {EVENT.ID} macro.
	 */
	public function testPageTriggerUrl_OverviewPageTriggerWithoutEventId() {
		$url = 'triggers.php?form=update&triggerid=100039';
		$filter = [
			'Show' => 'Any',
			'Name' => '3_trigger_Disaster'
		];

		$this->page->login()->open('overview.php');
		// Apply filter options.
		$form = $this->query('xpath://form[@name="zbx_filter"]')->asForm()->one();
		$form->fill($filter);
		$form->submit();
		$this->page->waitUntilReady();

		$table = $this->query('class:list-table')->asTable()->one();
		// Get row of trigger "3_trigger_Disaster".
		$row = $table->findRow('Triggers', '3_trigger_Disaster');

		// Open trigger context menu.
		$row->query('xpath://td[contains(@class, "normal-bg")]')->one()->click();
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();

		// Make sure trigger url is visible.
		$this->assertTrue($popup->hasTitles(['TRIGGER', 'LINKS', 'HISTORY']));
		$popup->fill('Trigger URL');
		// Check opened page.
		$this->assertEquals('Triggers', $this->query('tag:h1')->waitUntilVisible()->one()->getText());
		$this->assertContains($url, $this->page->getCurrentUrl());
	}

	/**
	 * @dataProvider getTriggerLinkData
	 * Check trigger url in screen item Trigger overview.
	 */
	public function testPageTriggerUrl_ScreensTriggerOverview($data) {
		$name = 'Trigger overview';
		$this->page->login()->open('screens.php?elementid=200021');
		$screen_item = $this->query('xpath://div[contains(@class, "dashbrd-widget-head")]/h4[text()='.
				CXPathHelper::escapeQuotes($name).']/../../..')->one()->waitUntilPresent();
		$table = $screen_item->query('class:list-table')->asTable()->one();
		// Get row of trigger "1_trigger_Not_classified".
		$row = $table->findRow('Triggers', $data['trigger']);

		// Open trigger context menu.
		$row->query('xpath://td[contains(@class, "'.$data['background'].'")]')->one()->click();
		$this->checkTriggerUrl(true, $data);
	}

	/**
	 * @dataProvider getTriggerLinkData
	 * Check trigger url in screen item Host issues.
	 */
	public function testPageTriggerUrl_ScreensHostIssues($data) {
		$name = 'Host issues';
		$this->page->login()->open('screens.php?elementid=200021');
		$screen_item = $this->query('xpath://div[contains(@class, "dashbrd-widget-head")]/h4[text()='.
				CXPathHelper::escapeQuotes($name).']/../../..')->one()->waitUntilPresent();
		$table = $screen_item->query('class:list-table')->asTable()->one();

		// Find trigger and open trigger overlay.
		$table->query('link', $data['trigger'])->one()->click();
		$this->checkTriggerUrl(false, $data, false);
	}

	/**
	 * @dataProvider getTriggerLinkData
	 * Check trigger url in screen item Host group issues.
	 */
	public function testPageTriggerUrl_ScreensHostGroupIssues($data) {
		$name = 'Host issues';
		$this->page->login()->open('screens.php?elementid=200021');
		$screen_item = $this->query('xpath://div[contains(@class, "dashbrd-widget-head")]/h4[text()='.
				CXPathHelper::escapeQuotes($name).']/../../..')->one()->waitUntilPresent();
		$table = $screen_item->query('class:list-table')->asTable()->one();

		// Find trigger and open trigger overlay.
		$table->query('link', $data['trigger'])->one()->click();
		$this->checkTriggerUrl(false, $data, false);
	}

	/**
	 * @dataProvider getTriggerLinkData
	 * Check trigger url on Event details page.
	 */
	public function testPageTriggerUrl_EventDetails($data) {
		$this->page->login()->open($data['links']['Trigger URL']);
		$this->query('link', $data['trigger'])->waitUntilPresent()->one()->click();
		$this->checkTriggerUrl(false, $data);
	}

	/**
	 * Follow trigger url and check opened page.
	 *
	 * @param boolean $trigger_overview		the check is made for a trigger overview instance
	 * @param array $data
	 * @param boolean $popup_menu			trigger context menu popup exist
	 */
	private function checkTriggerUrl($trigger_overview, $data, $popup_menu = true) {
		if ($popup_menu) {
			// Check trigger popup menu.
			$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
			$this->assertTrue($popup->hasTitles(['TRIGGER', 'LINKS', 'HISTORY']));
			// Check Url of each link.
			foreach ($data['links'] as $link => $url) {
				$this->assertTrue($popup->hasItems($link));
				$this->assertContains($url, $popup->getItem($link)->getAttribute('href'));
			}
			if ($trigger_overview) {
				$this->assertTrue($popup->hasItems('Acknowledge'));
				// Check that only the links from data provider plus Acknowledge link persist in the popup.
				$this->assertEquals(count($data['links'])+1, $popup->getItems()->count());
			}
			else {
				// Check that only the expected links ar present in the popup.
				$this->assertEquals(count($data['links']), $popup->getItems()->count());
			}
			// Open trigger link.
			$popup->fill('Trigger URL');
		}
		else {
			// Follow trigger link in overlay dialogue.
			$hintbox = $this->query('xpath://div[@class="overlay-dialogue"]')->one();
			$hintbox->query('link', $data['links']['Trigger URL'])->one()->click();
		}

		// Check opened page.
		$this->assertEquals('Event details', $this->query('tag:h1')->waitUntilVisible()->one()->getText());
		$this->assertContains($data['links']['Trigger URL'], $this->page->getCurrentUrl());
	}
}
