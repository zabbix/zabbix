<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

class testPagePrototypes extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	public $headers;
	public $page_name;
	public $amount;
	public $buttons;
	public $tag;
	public $clickable_headers;

	public function layout($template = false) {
		// Checking Title, Header and Column names.
		$this->page->assertTitle('Configuration of '.$this->page_name.' prototypes');
		$capital_name = ucfirst($this->page_name);
		$this->page->assertHeader($capital_name.' prototypes');
		$this->assertSame($this->headers, ($this->query('class:list-table')->asTable()->one())->getHeadersText());
		$this->assertTableStats($this->amount);
		$table = $this->query('class:list-table')->asTable()->one();

		// Check that Breadcrumbs exists.
		$links = ($template) ? ['All templates', 'Template for prototype check'] : ['All hosts', 'Host for prototype check'];
		$breadcrumbs = [
			'Discovery list',
			'Drule for prototype check',
			'Item prototypes',
			'Trigger prototypes',
			'Graph prototypes',
			'Host prototypes'
		];
		$this->assertEquals(array_merge($links, $breadcrumbs),
				$this->query('xpath://div[@class="header-navigation"]//a')->all()->asText()
		);

		// Check number amount near breadcrumbs.
		$this->assertEquals($this->amount, $this->query('xpath://div[@class="header-navigation"]//a[text()='.
				CXPathHelper::escapeQuotes($capital_name.' prototypes').']/following-sibling::sup')->one()->getText()
		);

		// Check displayed buttons and their default status after opening prototype page.
		foreach ($this->buttons as $button => $status) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($status));
		}

		// Check tags (Graph prototypes doesn't have any tags).
		if ($this->page_name !== 'graph') {
			$tags = $table->findRow('Name', $this->tag)->getColumn('Tags')->query('class:tag')->all();
			$this->assertEquals(['name_1: value_1', 'name_2: value_2'], $tags->asText());

			foreach ($tags as $tag) {
				$tag->click();
				$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last();
				$this->assertEquals($tag->getText(), $hint->getText());
				$hint->close();
			}
		}

		// Check clickable headers.
		foreach ($this->clickable_headers as $header) {
			$this->assertTrue($table->query('link', $header)->one()->isClickable());
		}

		// Check additional popup configuration for item prototype page.
		if ($this->page_name === 'item') {
			$table->getRow(0)->query('xpath:.//button')->one()->click();
			$this->page->waitUntilReady();
			$popup_menu = CPopupMenuElement::find()->one()->waitUntilPresent();
			$this->assertEquals(['CONFIGURATION'], $popup_menu->getTitles()->asText());
			$this->assertEquals(['Item prototype', 'Trigger prototypes', 'Create trigger prototype', 'Create dependent item'],
					$popup_menu->getItems()->asText()
			);

			$items = [
				'Item prototype' => true,
				'Trigger prototypes' => false,
				'Create trigger prototype' => true,
				'Create dependent item' => true
			];

			foreach ($items as $item => $enabled) {
				$this->assertTrue($popup_menu->getItem($item)->isEnabled($enabled));
			}
		}

		// Check Template column for host prototype page.
		if ($this->page_name === 'host') {
			$template_row = $table->findRow('Name', '4 Host prototype monitored not discovered {#H}');
			$template_row->assertValues(['Templates' => 'Template for host prototype']);
			$this->assertTrue($template_row->getColumn('Templates')->isClickable());
		}

		// Check Operational data and expression column - values should be displayed, on trigger prototype page.
		if ($this->page_name === 'trigger') {
			$opdata = [
				'12345',
				'{#PROT_MAC}',
				'test',
				'!@#$%^&*',
				'{$TEST}',
				'🙂🙃'
			];
			$this->assertTableDataColumn($opdata, 'Operational data');

			$trigger_row = $table->getRow(0);
			$this->assertEquals('last(/Host for prototype check/1_key[{#KEY}])=0',
					$trigger_row->getColumn('Expression')->getText()
			);
			$this->assertTrue($trigger_row->getColumn('Expression')->isClickable());
		}

		// Check Width and Height columns for graph prototype page.
		if ($this->page_name === 'graph') {
			foreach (['Width', 'Height'] as $column) {
				$this->assertTableDataColumn([100, 200, 300, 400], $column);
			}
		}
	}

	public static function getHostSortingData() {
		return [
			// #0 Sort by Name.
			[
				[
					'sort_by' => 'Name',
					'sort' => 'name',
					'result' => [
						'1 Host prototype monitored discovered {#H}',
						'2 Host prototype not monitored discovered {#H}',
						'3 Host prototype not monitored not discovered {#H}',
						'4 Host prototype monitored not discovered {#H}'
					]
				]
			],
			// #1 Sort by Create enabled.
			[
				[
					'sort_by' => 'Create enabled',
					'sort' => 'status',
					'result' => [
						'Yes',
						'Yes',
						'No',
						'No'
					]
				]
			],
			// #2 Sort by Discover.
			[
				[
					'sort_by' => 'Discover',
					'sort' => 'discover',
					'result' => [
						'Yes',
						'Yes',
						'No',
						'No'
					]
				]
			]
		];
	}

	/**
	 * Check available sorting on prototype page.
	 *
	 * @param $data		data from data provider
	 */
	public function executeSorting($data) {
		$table = $this->query('class:list-table')->asTable()->one();
		foreach (['desc', 'asc'] as $sorting) {
			$table->query('link', $data['sort_by'])->one()->click();
			$expected = ($sorting === 'asc') ? $data['result'] : array_reverse($data['result']);
			$this->assertEquals($expected, $this->getTableColumnData($data['sort_by']));
		}
	}

	public static function getHostButtonLinkData() {
		return [
			// #0 Click on Create disabled button.
			[
				[
					'name' => '1 Host prototype monitored discovered {#H}',
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #1 Click on Create enabled button.
			[
				[
					'name' => '2 Host prototype not monitored discovered {#H}',
					'button' => 'Create enabled',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #2 Enabled clicking on link in Create enabled column.
			[
				[
					'name' => '3 Host prototype not monitored not discovered {#H}',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #3 Disabled clicking on link in Create enabled column.
			[
				[
					'name' => '4 Host prototype monitored not discovered {#H}',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #4 Enable discovering clicking on link in Discover column.
			[
				[
					'name' => '3 Host prototype not monitored not discovered {#H}',
					'column_check' => 'Discover',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #5 Disable discovering clicking on link in Discover column.
			[
				[
					'name' => '2 Host prototype not monitored discovered {#H}',
					'column_check' => 'Discover',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #6 Enable all host prototypes clicking on Create enabled button.
			[
				[
					'button' => 'Create enabled',
					'column_check' => 'Create enabled',
					'after' => ['Yes', 'Yes', 'Yes', 'Yes']
				]
			],
			// #7 Disable all host prototypes clicking on Create disabled button.
			[
				[
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'after' => ['No', 'No', 'No', 'No']
				]
			]
		];
	}

	/**
	 * Check Create enabled/disabled buttons and links from Create enabled and Discover columns.
	 *
	 * @param $data		data from data provider
	 */
	public function executeDiscoverEnable($data) {
		$table = $this->query('class:list-table')->asTable()->one();

		// Find host prototype in table by name and check column data before update.
		if (array_key_exists('name', $data)) {
			$row = $table->findRow('Name', $data['name']);
			$this->assertEquals($data['before'], $row->getColumn($data['column_check'])->getText());
		}

		// Click on button or on link in column (Create enabled or Discover).
		if (array_key_exists('button', $data)) {
			// If no Host prototype name in data provider, then select all existing in table host prototypes.
			$selected = (array_key_exists('name', $data)) ? $data['name'] : null;
			$this->selectTableRows($selected);
			$this->query('button', $data['button'])->one()->click();
			$this->page->acceptAlert();
			$this->page->waitUntilReady();
		}
		else {
			// Click on link in table.
			$row->getColumn($data['column_check'])->query('link', $data['before'])->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
		}

		// Check column value for one prototype or for them all.
		if (array_key_exists('name', $data)) {
			$this->assertMessage(TEST_GOOD, ucfirst($this->page_name).' prototype updated');
			$this->assertEquals($data['after'], $row->getColumn($data['column_check'])->getText());
		}
		else {
			$this->assertMessage(TEST_GOOD, ucfirst($this->page_name).' prototypes updated');
			$this->assertTableDataColumn($data['after'], $data['column_check']);
		}
	}

	public static function getHostDeleteData() {
		return [
			// #0 Cancel delete.
			[
				[
					'name' => ['1 Host prototype monitored discovered {#H}'],
					'cancel' => true
				]
			],
			// #1 Delete one.
			[
				[
					'name' => ['2 Host prototype not monitored discovered {#H}'],
					'message' => 'Host prototype deleted'
				]
			],
			// #2 Delete more than 1.
			[
				[
					'name' => [
						'3 Host prototype not monitored not discovered {#H}',
						'4 Host prototype monitored not discovered {#H}'
					],
					'message' => 'Host prototypes deleted'
				]
			]
		];
	}

	/**
	 * Check Delete scenarios.
	 *
	 * @param $data		data from data provider
	 */
	public function executeDelete($data) {
		// Check that prototype exists and displayed in prototype table.
		foreach ($data['name'] as $name) {
			$this->assertTrue(in_array($name, $this->getTableColumnData('Name')));
		}

		// Select prototype and click on Delete button.
		$this->selectTableRows($data['name']);
		$this->query('button:Delete')->one()->click();

		// Check that after canceling Delete, prototype still exists in DB nad displayed in table.
		if (array_key_exists('cancel', $data)) {
			$this->page->dismissAlert();
			foreach ($data['name'] as $name) {
				$this->assertTrue(in_array($name, $this->getTableColumnData('Name')));
			}
		}
		else {
			$this->page->acceptAlert();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, $data['message']);

			// Check that prototype doesn't exist and not displayed in prototype table.
			foreach ($data['name'] as $name) {
				$this->assertFalse(in_array($name, $this->getTableColumnData('Name')));
			}
		}
	}
}
