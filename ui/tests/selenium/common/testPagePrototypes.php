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

	public $single_success;
	public $several_success;
	public $headers;
	public $page_name;
	public $amount;
	public $buttons;
	public $tag;
	public $clickable_headers;

	public function layout() {
		// Checking Title, Header and Column names.
		$this->page->assertTitle('Configuration of '.$this->page_name.' prototypes');
		$capital_name = ucfirst($this->page_name);
		$this->page->assertHeader($capital_name.' prototypes');
		$this->assertSame($this->headers, ($this->query('class:list-table')->asTable()->one())->getHeadersText());

		$this->assertTableStats($this->amount);

		// Check displayed buttons and their default status after opening host prototype page.
		foreach ($this->buttons as $button => $status) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($status));
		}

		// Check tags on the specific host prototype.
		$table = $this->query('class:list-table')->asTable()->one();
		$tags = $table->findRow('Name', $this->tag)->getColumn('Tags')->query('class:tag')->all();
		$this->assertEquals(['name_1: value_1', 'name_2: value_2'], $tags->asText());

		// Check hints for tags that appears after clicking on them.
		foreach ($tags as $tag) {
			$tag->click();
			$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last();
			$this->assertEquals($tag->getText(), $hint->getText());
			$hint->close();
		}

		// Check clickable headers.
		foreach ($this->clickable_headers as $header) {
			$this->assertTrue($table->query('link', $header)->one()->isClickable());
		}

		// Check additional configuration for item prototype page.
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

		// Check Template column.
		if ($this->page_name === 'host') {
			$template_row = $table->findRow('Name', '4 Host prototype monitored not discovered {#H}');
			$template_row->assertValues(['Templates' => 'Template for host prototype']);
			$this->assertTrue($template_row->getColumn('Templates')->isClickable());
		}
	}

	public function executeSorting($data) {
		$table = $this->query('class:list-table')->asTable()->one();
		foreach (['desc', 'asc'] as $sorting) {
			$table->query('link', $data['sort_by'])->one()->click();
			$expected = ($sorting === 'asc') ? $data['result'] : array_reverse($data['result']);
			$this->assertEquals($expected, $this->getTableColumnData($data['sort_by']));
		}
	}

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

		// Check column value for one host prototypes or for them all.
		if (array_key_exists('name', $data)) {
			$this->assertMessage(TEST_GOOD, $this->single_success);
			$this->assertEquals($data['after'], $row->getColumn($data['column_check'])->getText());
		}
		else {
			$this->assertMessage(TEST_GOOD, $this->several_success);
			$this->assertTableDataColumn($data['after'], $data['column_check']);
		}
	}

	public function executeDelete($data) {
		// Check that host prototype exists in DB and displayed in Host prototype table.
		foreach ($data['name'] as $name) {
			$this->assertTrue(in_array($name, $this->getTableColumnData('Name')));
		}

		// Select host prototype and click on Delete button.
		$this->selectTableRows($data['name']);
		$this->query('button:Delete')->one()->click();

		// Check that after canceling Delete, host prototype still exists in DB nad displayed in table.
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

			// Check that host prototype doesn't exist in DB and not displayed in Host prototype table.
			foreach ($data['name'] as $name) {
				$this->assertFalse(in_array($name, $this->getTableColumnData('Name')));
			}
		}
	}
}
