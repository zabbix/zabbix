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


require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

class testPageWeb extends CWebTest {

	use TableTrait;

	public function testPageWeb_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=web.view')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();

		// Check Title, Header, Column names.
		$this->page->assertTitle('Web monitoring');
		$this->page->assertHeader('Web monitoring');
		$headers = ['Host', 'Name', 'Number of steps', 'Last check', 'Status', 'Tags'];
		$this->assertSame($headers, ($this->query('class:list-table')->asTable()->one())->getHeadersText());

		// Check if Apply and Reset button are clickable.
		foreach(['Apply', 'Reset'] as $option) {
			$this->assertTrue($form->query('button', $option)->one()->isClickable());
		}

		// Check filter collapse/expand.
		foreach ([true, false] as $status) {
			$this->assertTrue($this->query('xpath://li[contains(@class, "expanded")]')->one()->isPresent($status));
			$this->query('xpath://a[@class="filter-trigger ui-tabs-anchor"]')->one()->click();
		}

		// Check fields maximum length.
		foreach(['filter_tags[0][tag]', 'filter_tags[0][value]'] as $field) {
			$this->assertEquals(255, $form->query('xpath:.//input[@name="'.$field.'"]')
				->one()->getAttribute('maxlength'));
		}

		// Check if links to Hosts and to Web scenarios are clickable.
		foreach (['Host', 'Name'] as $field) {
			$this->assertTrue($table->getRow(0)->getColumn($field)->query('xpath:.//a')->one()->isClickable());
		}

		// Check if Kioskmode button is clickable.
		$this->assertTrue($this->query('class:header-controls')->one()->isClickable());

		// Check if rows are correctly displayed.
		$this->assertTableStats($table->getRows()->count());
	}

	public function testPageWeb_CheckSorting() {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1')->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		foreach (['Host', 'Name'] as $listing) {
			$query = $table->query('xpath:.//a[@href and text()="'.$listing.'"]');
			$query->one()->click();
			$this->page->waitUntilReady();
			$after_listing = $this->getTableResult($listing);
			$query->one()->click();
			$this->page->waitUntilReady();
			$this->assertEquals(array_reverse($after_listing), $this->getTableResult($listing));
		}
	}

	public static function getCheckFilterData() {
		return [
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers'
					],
					'expected' => [
							'Web ZBX6663 Second',
							'Web ZBX6663',
							'testInheritanceWeb4',
							'testInheritanceWeb3',
							'testInheritanceWeb2',
							'testInheritanceWeb1',
							'testFormWeb4',
							'testFormWeb3',
							'testFormWeb2',
							'testFormWeb1'
					]
				]
			],
			[
				[
					'filter' => [
						'Hosts' => 'Simple form test host'
					],
					'expected' => [
							'testFormWeb4',
							'testFormWeb3',
							'testFormWeb2',
							'testFormWeb1'
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'Host ZBX6663'
					],
					'expected' => [
							'Web ZBX6663 Second',
							'Web ZBX6663'
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers',
						'Hosts' => [
							'Host ZBX6663',
							'Simple form test host'
						]
					],
					'expected' => [
							'Web ZBX6663 Second',
							'Web ZBX6663',
							'testFormWeb4',
							'testFormWeb3',
							'testFormWeb2',
							'testFormWeb1'
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers',
						'Hosts' => [
							'Host ZBX6663',
							'Simple form test host',
							'Template inheritance test host'
						]
					],
					'expected' => [
						'Web ZBX6663 Second',
							'Web ZBX6663',
							'testInheritanceWeb4',
							'testInheritanceWeb3',
							'testInheritanceWeb2',
							'testInheritanceWeb1',
							'testFormWeb4',
							'testFormWeb3',
							'testFormWeb2',
							'testFormWeb1'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckFilterData
	 */
	public function testPageWeb_CheckFiltering($data) {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1');
		$table = $this->query('class:list-table')->asTable()->one();
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$form->fill($data['filter']);
		//$result_table = $this->query('xpath:.//form[@name="web.view"]')->one();
		$this->query('button:Apply')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		//$result_table->waitUntilReloaded();
		$this->assertTableDataColumn($data['expected']);
	}
}
