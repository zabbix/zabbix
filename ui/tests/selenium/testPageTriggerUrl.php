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
 * @onBefore prepareTriggerData
 *
 * @backup profiles, problem
 */
class testPageTriggerUrl extends CWebTest {

	private static $custom_name = 'URL name for menu';

	/**
	 * Add URL name for trigger.
	 */
	public function prepareTriggerData() {
		$response = CDataHelper::call('trigger.update', [
			[
				'triggerid' => '100032',
				'url_name' => 'URL name for menu'
			]
		]);
	}

	public function getTriggerLinkData() {
		return [
			// Check tag priority.
			[
				[
					'trigger' => '1_trigger_High',
					'links' => [
						'Problems' => 'zabbix.php?action=problem.view&filter_name=&triggerids%5B%5D=100035',
						'Acknowledge' => 'zabbix.php?action=popup&popup_action=acknowledge.edit&eventids%5B%5D=9004',
						'History' => ['1_item' => 'history.php?action=showgraph&itemids%5B%5D=99086'],
						'Trigger' => 'triggers.php?form=update&triggerid=100035&context=host',
						'Items' => ['1_item' => 'items.php?form=update&itemid=99086&context=host'],
						'Trigger URL' => 'tr_events.php?triggerid=100035&eventid=9003',
						'Unique webhook url' => 'zabbix.php?action=mediatype.list&ddreset=1',
						'Webhook url for all' => 'zabbix.php?action=mediatype.edit&mediatypeid=101'
					],
					'background' => "high-bg"
				]
			],
			[
				[
					'trigger' => '1_trigger_Not_classified',
					'links' => [
						'Problems' => 'zabbix.php?action=problem.view&filter_name=&triggerids%5B%5D=100032',
						'Acknowledge' => 'zabbix.php?action=popup&popup_action=acknowledge.edit&eventids%5B%5D=9000',
						'History' => ['1_item' => 'history.php?action=showgraph&itemids%5B%5D=99086'],
						'Trigger' => 'triggers.php?form=update&triggerid=100032&context=host',
						'Items' => ['1_item' => 'items.php?form=update&itemid=99086&context=host'],
						'URL name for menu' => 'tr_events.php?triggerid=100032&eventid=9000',
						'Webhook url for all' => 'zabbix.php?action=mediatype.edit&mediatypeid=101'
					],
					'background' => 'na-bg'
				]
			]
		];
	}

	/**
	 * Check trigger url in Problems widget.
	 *
	 * @dataProvider getTriggerLinkData
	 */
	public function testPageTriggerUrl_ProblemsWidget($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1');
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget('Current problems');
		$table = $widget->getContent()->asTable();

		// Find trigger and open trigger overlay dialogue.
		$table->query('link', $data['trigger'])->one()->click();
		$this->checkTriggerUrl(false, $data);
	}

	/**
	 * Check trigger url in Trigger overview widget.
	 *
	 * @dataProvider getTriggerLinkData
	 */
	public function testPageTriggerUrl_TriggerOverviewWidget($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1020');
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget('Group to check Overview');

		// Get row of trigger "1_trigger_Not_classified".
		$row = $widget->getContent()->asTable()->findRow('Triggers', $data['trigger']);

		// Open trigger context menu.
		$row->query('xpath://td[contains(@class, "'.$data['background'].'")]')->one()->click();
		$this->checkTriggerUrl(true, $data);
	}

	/**
	 * Check trigger url on Problems page.
	 *
	 * @dataProvider getTriggerLinkData
	 */
	public function testPageTriggerUrl_ProblemsPage($data) {
		$this->page->login()->open('zabbix.php?action=problem.view');

		// Open trigger context menu.
		$this->query('class:list-table')->asTable()->one()->query('link', $data['trigger'])->one()->click();
		$this->checkTriggerUrl(false, $data);
	}

	public function resetFilter() {
		DBexecute('DELETE FROM profiles WHERE idx LIKE \'%web.overview.filter%\'');
	}

	/**
	 * Check trigger url on Event details page.
	 *
	 * @dataProvider getTriggerLinkData
	 */
	public function testPageTriggerUrl_EventDetails($data) {
		$option = array_key_exists('Trigger URL', $data['links']) ? 'Trigger URL' : self::$custom_name;

		$this->page->login()->open($data['links'][$option]);
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
		$option = array_key_exists('Trigger URL', $data['links']) ? 'Trigger URL' : self::$custom_name;

		if ($popup_menu) {
			// Check trigger popup menu.
			$trigger_popup = $this->query('xpath://ul[@role="menu" and @tabindex="0"]')->asPopupMenu()
					->waitUntilPresent()->one();
			$this->assertTrue($trigger_popup->hasTitles(['VIEW', 'CONFIGURATION', 'LINKS']));

			// Check Url for main links.
			if ($trigger_overview) {
				$array = $data['links'];
				array_shift($array);
				$data['links'] = ['Problems' => $data['links']['Problems'],	'Acknowledge' => ''] + $array;
			}

			$this->assertEquals(array_keys($data['links']), $trigger_popup->getItems()->asText());

			foreach ($data['links'] as $menu => $links) {
				// Check 2-level menu links.
				if (is_array($links)) {
					$item_link = $trigger_popup->getItem($menu)->query('xpath:./../ul//a')->one();
					$this->assertEquals(array_keys($links), [$item_link->getText()]);
					$this->assertStringContainsString(array_values($links)[0], $item_link->getAttribute('href'));
				}
				// Check 1-level menu links.
				else {
					if ($menu !== 'Acknowledge') {
						$this->assertStringContainsString($links, $trigger_popup->getItem($menu)->getAttribute('href'));
					}
				}
			}

			// Open trigger link.
			$trigger_popup->fill($option);
		}
		else {
			// Follow trigger link in overlay dialogue.
			$hintbox = $this->query('xpath://div[@class="overlay-dialogue"]')->waitUntilVisible()->one();
			$hintbox->query('link', $data['links'][$option])->one()->click();
		}

		// Check opened page.
		$this->assertEquals('Event details', $this->query('tag:h1')->waitUntilVisible()->one()->getText());
		$this->assertStringContainsString($data['links'][$option], $this->page->getCurrentUrl());
	}
}
