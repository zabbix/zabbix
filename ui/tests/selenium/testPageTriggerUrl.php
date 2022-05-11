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
						'Unique webhook url' => 'zabbix.php?action=mediatype.list&ddreset=1',
						'Webhook url for all' => 'zabbix.php?action=mediatype.edit&mediatypeid=101',
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
		$this->checkTriggerUrl(false, $data);
	}

	/**
	 * @dataProvider getTriggerLinkData
	 * Check trigger url in Trigger overview widget.
	 */
	public function testPageTriggerUrl_TriggerOverviewWidget($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=10220');
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

	public function resetFilter() {
		DBexecute('DELETE FROM profiles WHERE idx LIKE \'%web.overview.filter%\'');
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
				$this->assertStringContainsString($url, $popup->getItem($link)->getAttribute('href'));
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
			$hintbox = $this->query('xpath://div[@class="overlay-dialogue"]')->waitUntilVisible()->one();
			$hintbox->query('link', $data['links']['Trigger URL'])->one()->click();
		}

		// Check opened page.
		$this->assertEquals('Event details', $this->query('tag:h1')->waitUntilVisible()->one()->getText());
		$this->assertStringContainsString($data['links']['Trigger URL'], $this->page->getCurrentUrl());
	}
}
