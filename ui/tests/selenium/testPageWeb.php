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

/**
 * @backup hosts, httptest
 */
class testPageWeb extends CWebTest {

	use TableTrait;

	/**
	* Function checks the layout of Web page.
	*/
	public function testPageWeb_CheckLayout() {
		// Logins directly into required page.
		$this->page->login()->open('zabbix.php?action=web.view');
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();

		// Checks Title, Header, and column names.
		$this->page->assertTitle('Web monitoring');
		$this->page->assertHeader('Web monitoring');
        $this->assertEquals(['Host', 'Name', 'Number of steps', 'Last check', 'Status', 'Tags'], $table->getHeadersText());

		// Check if Apply and Reset button are clickable.
		foreach(['Apply', 'Reset'] as $button) {
			$this->assertTrue($form->query('button', $button)->one()->isClickable());
		}

        // Check filter collapse/expand.
        foreach (['true', 'false'] as $status) {
            $this->assertTrue($this->query('xpath://li[@aria-expanded="'.$status.'"]')->one()->isPresent());
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

		// Checks if it's possible to order Host names and Web names by ascending and descending order.
		foreach (['ASC', 'DESC'] as $order) {
		$table->query('xpath://a[@href="zabbix.php?action=web.view&sort=hostname&sortorder='.$order.'"]')
			->one()->click();
			}
		foreach (['ASC', 'DESC'] as $order) {
		$table->query('xpath://a[@href="zabbix.php?action=web.view&sort=name&sortorder='.$order.'"]')
			->one()->click();
		}
	}

    /**
     * Test Web sorting by Name column.
     */
    public function testPageWeb_CheckSorting() {
        $this->page->login()->open('zabbix.php?action=web.view');
        $table = $this->query('class:list-table')->asTable()->one();
        $table_names = $this->getTableResult('Name');

        foreach(['asc', 'desc'] as $sorting) {
            $expected = ($sorting === 'desc') ? $table_names : array_reverse($table_names);
            $values = [];
        }
        foreach ($table->getRows() as $row) {
            $values[] = $row->getColumn('Name')->getText();
        }
        $this->assertEquals($expected, $values);
    }

    /**
     * Test that title field disappears while Kioskmode is active.
     */
    public function testPageWeb_CheckKioskMode() {
        $this->page->login()->open('zabbix.php?action=web.view');
        $this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
        $this->page->waitUntilReady();
        $this->query('xpath://h1[@id="page-title-general"]')->waitUntilNotVisible();
        $this->query('xpath://button[@title="Normal view"]')->waitUntilPresent()->one()->click(true);
        $this->page->waitUntilReady();
    }

    /**
     * Test that links to web service names are working properly and directed to needed form.
     */
    public function testPageWeb_CheckLinks() {
        $this->page->login()->open('zabbix.php?action=web.view');
        $row = $this->query('class:list-table')->asTable()->one()->findRow('Name', 'testFormWeb1');
        $row->query('link', 'testFormWeb1')->one()->click();
        $this->page->waitUntilReady();
        $this->assertEquals('Details of web scenario: testFormWeb1', $this->query('xpath://h1[@id="page-title-general"]')->one()->getText());
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
	public function testPageWeb_CheckFilter($data) {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=name&sortorder=DESC');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
        $form->fill($data['filter'])->submit();
		$this->page->waitUntilReady();
		$this->assertTableDataColumn($data['expected']);
	}
}
