<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/../../include/CWebTest.php';

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
			[
				[
					'trigger' => '1_trigger_High',
					'links' => [
						'Problems' => 'zabbix.php?action=problem.view&filter_set=1&triggerids%5B%5D=100035',
						'History' => ['1_item' => 'history.php?action=showgraph&itemids%5B%5D=99086'],
						'Trigger' => 'menu-popup-item',
						'Items' => ['1_item' => 'menu-popup-item'],
						'Mark as cause' => '',
						'Mark selected as symptoms' => '',
						'Trigger URL' => 'menu-popup-item',
						'Unique webhook url' => 'menu-popup-item',
						'Webhook url for all' => 'menu-popup-item'
					],
					'expected_url' => 'tr_events.php?triggerid=100035&eventid=9003',
					'background' => "high-bg"
				]
			],
			[
				[
					'trigger' => '1_trigger_Not_classified',
					'links' => [
						'Problems' => 'zabbix.php?action=problem.view&filter_set=1&triggerids%5B%5D=100032',
						'History' => ['1_item' => 'history.php?action=showgraph&itemids%5B%5D=99086'],
						'Trigger' => 'menu-popup-item',
						'Items' => ['1_item' => 'menu-popup-item'],
						'Mark as cause' => '',
						'Mark selected as symptoms' => '',
						'URL name for menu' => 'menu-popup-item',
						'Webhook url for all' => 'menu-popup-item'
					],
					'expected_url' => 'tr_events.php?triggerid=100032&eventid=9000',
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
		// Prepare data provider.
		unset($data['links']['Mark selected as symptoms']);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1');
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget('Current problems');
		$table = $widget->getContent()->asTable();

		// Find trigger and open trigger overlay dialogue.
		$table->query('link', $data['trigger'])->one()->click();
		$this->checkTriggerUrl($data);
	}

	/**
	 * Check trigger url in Trigger overview widget.
	 *
	 * @dataProvider getTriggerLinkData
	 */
	public function testPageTriggerUrl_TriggerOverviewWidget($data) {
		// Add 'Update problem' menu link to data provider.
		$data['links'] = array_slice($data['links'], 0, 2, true) + ['Update problem' => ''] +
				array_slice($data['links'], 2, count($data['links']) - 2, true);

		// Remove 'cause and symptoms' from data provider.
		unset($data['links']['Mark as cause']);
		unset($data['links']['Mark selected as symptoms']);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1020');
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget('Group to check Overview');

		// Get row of trigger "1_trigger_Not_classified".
		$row = $widget->getContent()->asTable()->findRow('Triggers', $data['trigger']);

		// Open trigger context menu.
		$row->query('xpath://td[contains(@class, "'.$data['background'].'")]')->one()->click();
		$this->checkTriggerUrl($data, ['VIEW', 'ACTIONS', 'CONFIGURATION', 'LINKS']);
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
		$this->checkTriggerUrl($data);
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
		// Prepare data provider.
		unset($data['links']['Mark selected as symptoms']);
		$this->page->login()->open($data['expected_url']);
		$this->query('link', $data['trigger'])->waitUntilPresent()->one()->click();
		$this->checkTriggerUrl($data);
	}

	/**
	 * Follow trigger url and check opened page.
	 *
	 * @param array $data		data provider with fields values
	 * @param array $titles		titles in context menu
	 */
	private function checkTriggerUrl($data, $titles = ['VIEW', 'CONFIGURATION', 'PROBLEM', 'LINKS']) {
		$option = array_key_exists('Trigger URL', $data['links']) ? 'Trigger URL' : self::$custom_name;

		// Check trigger popup menu.
		$trigger_popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$this->assertTrue($trigger_popup->hasTitles($titles));
		$this->assertEquals(array_keys($data['links']), $trigger_popup->getItems()->asText());

		foreach ($data['links'] as $menu => $links) {
			// Check 2-level menu links.
			if (is_array($links)) {
				$item_link = $trigger_popup->getItem($menu)->query('xpath:./../ul//a')->one();
				$this->assertEquals(array_keys($links), [$item_link->getText()]);
				$this->assertStringContainsString(array_values($links)[0],
						$item_link->getAttribute((array_values($links)[0] === 'menu-popup-item') ? 'class' : 'href')
				);
			}
			else {
				// Check 1-level menu links.
				if ($links !== '') {
					$this->assertStringContainsString($links,
							$trigger_popup->getItem($menu)->getAttribute($links === 'menu-popup-item' ? 'class' : 'href')
					);
				}
			}
		}

		// Open trigger link.
		$trigger_popup->fill($option);

		// Check opened page.
		$this->assertEquals('Event details', $this->query('tag:h1')->waitUntilVisible()->one()->getText());
		$this->assertStringContainsString($data['expected_url'], $this->page->getCurrentUrl());
	}
}
