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

			$expression = ($template) ? 'Template' : 'Host';

			$this->assertEquals('last(/'.$expression.' for prototype check/1_key[{#KEY}])=0',
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

	/**
	 * Host prototype sorting.
	 */
	public static function getHostsSortingData() {
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
	 * Item prototype sorting.
	 */
	public static function getItemsSortingData() {
		return [
			// #0 Sort by Name.
			[
				[
					'sort_by' => 'Name',
					'sort' => 'name',
					'result' => [
						'1 Item prototype monitored discovered',
						'2 Item prototype not monitored discovered',
						'3 Item prototype not monitored not discovered',
						'4 Item prototype monitored not discovered',
						'5 Item prototype trapper with text type'
					]
				]
			],
			// #1 Sort by Key.
			[
				[
					'sort_by' => 'Key',
					'sort' => 'key_',
					'result' => [
						'1_key[{#KEY}]',
						'2_key[{#KEY}]',
						'3_key[{#KEY}]',
						'4_key[{#KEY}]',
						'5_key[{#KEY}]'
					]
				]
			],
			// #2 Sort by Interval.
			[
				[
					'sort_by' => 'Interval',
					'sort' => 'delay',
					'result' => [
						'',
						15,
						30,
						45,
						60
					]
				]
			],
			// #3 Sort by History.
			[
				[
					'sort_by' => 'History',
					'sort' => 'history',
					'result' => [
						'0',
						'60d',
						'70d',
						'80d',
						'90d'
					]
				]
			],
			// #4 Sort by Trends.
			[
				[
					'sort_by' => 'Trends',
					'sort' => 'trends',
					'result' => [
						'',
						'200d',
						'250d',
						'300d',
						'350d'
					]
				]
			],
			// #5 Sort by Type.
			[
				[
					'sort_by' => 'Type',
					'sort' => 'type',
					'result' => [
						'Zabbix trapper',
						'Zabbix internal',
						'Zabbix agent (active)',
						'Calculated',
						'HTTP agent'
					]
				]
			],
			// #6 Sort by Create enabled.
			[
				[
					'sort_by' => 'Create enabled',
					'sort' => 'status',
					'result' => [
						'Yes',
						'Yes',
						'Yes',
						'No',
						'No'
					]
				]
			],
			// #7 Sort by Discover.
			[
				[
					'sort_by' => 'Discover',
					'sort' => 'discover',
					'result' => [
						'Yes',
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
	 * Trigger prototype sorting.
	 */
	public static function getTriggersSortingData() {
		return [
			// #0 Sort by Severity.
			[
				[
					'sort_by' => 'Severity',
					'sort' => 'priority',
					'result' => [
						'Not classified',
						'Information',
						'Warning',
						'Average',
						'High',
						'Disaster'
					]
				]
			],
			// #1 Sort by Name.
			[
				[
					'sort_by' => 'Name',
					'sort' => 'description',
					'result' => [
						'1 Trigger prototype monitored discovered_{#KEY}',
						'2 Trigger prototype not monitored discovered_{#KEY}',
						'3 Trigger prototype not monitored not discovered_{#KEY}',
						'4 Trigger prototype monitored not discovered_{#KEY}',
						'5 Trigger prototype for high severity_{#KEY}',
						'6 Trigger prototype for disaster severity_{#KEY}'
					]
				]
			],
			// #2 Sort by Create enabled.
			[
				[
					'sort_by' => 'Create enabled',
					'sort' => 'status',
					'result' => [
						'Yes',
						'Yes',
						'Yes',
						'Yes',
						'No',
						'No'
					]
				]
			],
			// #3 Sort by Discover.
			[
				[
					'sort_by' => 'Discover',
					'sort' => 'discover',
					'result' => [
						'Yes',
						'Yes',
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
	 * Graph prototype sorting.
	 */
	public static function getGraphsSortingData() {
		return [
			// #0 Sort by Name.
			[
				[
					'sort_by' => 'Name',
					'sort' => 'name',
					'result' => [
						'1 Graph prototype discovered_{#KEY}',
						'2 Graph prototype not discovered_{#KEY}',
						'3 Graph prototype pie discovered_{#KEY}',
						'4 Graph prototype exploded not discovered_{#KEY}'
					]
				]
			],
			// #1 Sort by Graph type.
			[
				[
					'sort_by' => 'Graph type',
					'sort' => 'graphtype',
					'result' => [
						'Exploded',
						'Normal',
						'Pie',
						'Stacked'
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

	/**
	 * Host prototype disable/enable by link and button.
	 */
	public static function getHostsButtonLinkData() {
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
	 * Item prototype disable/enable by link and button.
	 */
	public static function getItemsButtonLinkData() {
		return [
			// #0 Click on Create disabled button.
			[
				[
					'name' => '1 Item prototype monitored discovered',
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #1 Click on Create enabled button.
			[
				[
					'name' => '2 Item prototype not monitored discovered',
					'button' => 'Create enabled',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #2 Enabled clicking on link in Create enabled column.
			[
				[
					'name' => '3 Item prototype not monitored not discovered',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #3 Disabled clicking on link in Create enabled column.
			[
				[
					'name' => '4 Item prototype monitored not discovered',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #4 Enable discovering clicking on link in Discover column.
			[
				[
					'name' => '3 Item prototype not monitored not discovered',
					'column_check' => 'Discover',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #5 Disable discovering clicking on link in Discover column.
			[
				[
					'name' => '2 Item prototype not monitored discovered',
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
					'after' => ['Yes', 'Yes', 'Yes', 'Yes', 'Yes']
				]
			],
			// #7 Disable all host prototypes clicking on Create disabled button.
			[
				[
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'after' => ['No', 'No', 'No', 'No', 'No']
				]
			]
		];
	}

	/**
	 * Trigger prototype disable/enable by link and button.
	 */
	public static function getTriggersButtonLinkData() {
		return [
			// #0 Click on Create disabled button.
			[
				[
					'name' => '1 Trigger prototype monitored discovered_{#KEY}',
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #1 Click on Create enabled button.
			[
				[
					'name' => '2 Trigger prototype not monitored discovered_{#KEY}',
					'button' => 'Create enabled',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #2 Enabled clicking on link in Create enabled column.
			[
				[
					'name' => '3 Trigger prototype not monitored not discovered_{#KEY}',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #3 Disabled clicking on link in Create enabled column.
			[
				[
					'name' => '4 Trigger prototype monitored not discovered_{#KEY}',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #4 Enable discovering clicking on link in Discover column.
			[
				[
					'name' => '3 Trigger prototype not monitored not discovered_{#KEY}',
					'column_check' => 'Discover',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #5 Disable discovering clicking on link in Discover column.
			[
				[
					'name' => '2 Trigger prototype not monitored discovered_{#KEY}',
					'column_check' => 'Discover',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #6 Enable all trigger prototypes clicking on Create enabled button.
			[
				[
					'button' => 'Create enabled',
					'column_check' => 'Create enabled',
					'after' => ['Yes', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes']
				]
			],
			// #7 Disable all trigger prototypes clicking on Create disabled button.
			[
				[
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'after' => ['No', 'No', 'No', 'No', 'No', 'No']
				]
			]
		];
	}

	/**
	 * Graph prototype disable/enable by link and button.
	 */
	public static function getGraphsButtonLinkData() {
		return [
			// #0 Enable discovering clicking on link in Discover column.
			[
				[
					'name' => '2 Graph prototype not discovered_{#KEY}',
					'column_check' => 'Discover',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #1 Disable discovering clicking on link in Discover column.
			[
				[
					'name' => '1 Graph prototype discovered_{#KEY}',
					'column_check' => 'Discover',
					'before' => 'Yes',
					'after' => 'No'
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

	/**
	 * Host prototype delete.
	 */
	public static function getHostsDeleteData() {
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
	 * Item prototype delete.
	 */
	public static function getItemsDeleteData() {
		return [
			// #0 Cancel delete.
			[
				[
					'name' => ['1 Item prototype monitored discovered'],
					'cancel' => true
				]
			],
			// #1 Delete one.
			[
				[
					'name' => ['2 Item prototype not monitored discovered'],
					'message' => 'Item prototype deleted'
				]
			],
			// #2 Delete more than 1.
			[
				[
					'name' => [
						'3 Item prototype not monitored not discovered',
						'4 Item prototype monitored not discovered'
					],
					'message' => 'Item prototypes deleted'
				]
			]
		];
	}

	/**
	 * Trigger prototype delete.
	 */
	public static function getTriggersDeleteData() {
		return [
			// #0 Cancel delete.
			[
				[
					'name' => ['1 Trigger prototype monitored discovered_{#KEY}'],
					'cancel' => true
				]
			],
			// #1 Delete one.
			[
				[
					'name' => ['2 Trigger prototype not monitored discovered_{#KEY}'],
					'message' => 'Trigger prototype deleted'
				]
			],
			// #2 Delete more than 1.
			[
				[
					'name' => [
						'3 Trigger prototype not monitored not discovered_{#KEY}',
						'4 Trigger prototype monitored not discovered_{#KEY}'
					],
					'message' => 'Trigger prototypes deleted'
				]
			]
		];
	}

	/**
	 * Graph prototype delete.
	 */
	public static function getGraphsDeleteData() {
		return [
			// #0 Cancel delete.
			[
				[
					'name' => ['1 Graph prototype discovered_{#KEY}'],
					'cancel' => true
				]
			],
			// #1 Delete one.
			[
				[
					'name' => ['2 Graph prototype not discovered_{#KEY}'],
					'message' => 'Graph prototype deleted'
				]
			],
			// #2 Delete more than 1.
			[
				[
					'name' => [
						'3 Graph prototype pie discovered_{#KEY}',
						'4 Graph prototype exploded not discovered_{#KEY}'
					],
					'message' => 'Graph prototypes deleted'
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

	/**
	 * Check value display in table for item prototype page.
	 */
	public static function getItemsNotDisplayedValuesData() {
		return [
			// #0 SNMP trapper without interval.
			[
				[
					'fields' => [
						'Name' => 'Empty SNMP interval',
						'Type' => 'SNMP trap',
						'Key' => 'snmp_interval_[{#KEY}]'
					],
					'check' => [
						'Interval' => ''
					]
				]
			],
			// #1 Zabbix trapper without interval.
			[
				[
					'fields' => [
						'Name' => 'Empty Zabbix trapper interval',
						'Type' => 'Zabbix trapper',
						'Key' => 'zabbix_trapper_interval_[{#KEY}]'
					],
					'check' => [
						'Interval' => ''
					]
				]
			],
			// #2 Dependent item without interval.
			[
				[
					'fields' => [
						'Name' => 'Empty dependent item interval',
						'Type' => 'Dependent item',
						'Master item' => 'Master item',
						'Key' => 'dependent_interval_[{#KEY}]'
					],
					'check' => [
						'Interval' => ''
					]
				]
			],
			// #3 Zabbix agent with type of information - text.
			[
				[
					'fields' => [
						'Name' => 'Text zabbix trapper',
						'Type' => 'Zabbix trapper',
						'Type of information' => 'Text',
						'Key' => 'text_[{#KEY}]'
					],
					'check' => [
						'Trends' => ''
					]
				]
			],
			// #4 Zabbix agent with type of information - character.
			[
				[
					'fields' => [
						'Name' => 'Character zabbix trapper',
						'Type' => 'Zabbix trapper',
						'Type of information' => 'Character',
						'Key' => 'character_[{#KEY}]'
					],
					'check' => [
						'Trends' => ''
					]
				]
			],
			// #5 Zabbix agent with type of information - log.
			[
				[
					'fields' => [
						'Name' => 'Log zabbix trapper',
						'Type' => 'Zabbix trapper',
						'Type of information' => 'Log',
						'Key' => 'log_[{#KEY}]'
					],
					'check' => [
						'Trends' => ''
					]
				]
			]
		];
	}

	/**
	 * Check that empty values displayed in Trends and Interval columns for some item types and types of information.
	 * Only for Item prototype.
	 */
	public function checkNotDisplayedValues($data) {
		$this->query('button:Create item prototype')->one()->click();
		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$form->fill($data['fields']);
		$form->submit()->waitUntilNotVisible();
		$this->page->waitUntilReady();

		$table = $this->query('class:list-table')->asTable()->one();
		$template_row = $table->findRow('Key', $data['fields']['Key']);
		$template_row->assertValues($data['check']);
	}
}
