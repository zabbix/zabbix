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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * @dataSource Maps, CopyWidgetsDashboards, WidgetCommunication
 *
 * @backup sysmaps
 *
 * @onBefore prepareMapsData
 */
class testPageMaps extends CWebTest {

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

	const SYSMAPS_SQL = 'SELECT * FROM sysmaps ORDER BY sysmapid';
	const SYSMAP_NAME_LOW_NUMBER = '999 for sorting test';
	const SYSMAP_NAME_HIGH_NUMBER = '1111 for sorting test';
	const SYSMAP_NAME_WITH_SYMBOLS = 'Name with 3/4-byte symbols: 🤖 ⃠ Ω⌚Ԏ﨧';
	const SYSMAP_TO_DELETE = 'Sysmap for deletion';
	const SYSMAP_FIRST_A = 'A map to check alphabetical sorting';
	const SYSMAP_FIRST_Z = 'Zabbix sysmap for checking alphabetical sorting';
	const SYSMAP_LOW_WIDTH = 'Map with lowest width';
	const SYSMAP_HIGH_WIDTH = 'Map with highest width';
	const SYSMAP_LOW_HEIGHT = 'Map with lowest height';
	const SYSMAP_HIGH_HEIGHT = 'Map with highest height';
	const SYSMAP_SPACES_NAME = 'Map to check that there is no trim for spaces';
	protected static $sysmapids;

	public function prepareMapsData() {
		CDataHelper::call('map.create', [
			[
				'name' => self::SYSMAP_NAME_LOW_NUMBER,
				'width' => 600,
				'height' => 600
			],
			[
				'name' => self::SYSMAP_NAME_HIGH_NUMBER,
				'width' => 600,
				'height' => 600
			],
			[
				'name' => self::SYSMAP_NAME_WITH_SYMBOLS,
				'width' => 600,
				'height' => 600
			],
			[
				'name' => self::SYSMAP_TO_DELETE,
				'width' => 600,
				'height' => 600
			],
			[
				'name' => self::SYSMAP_FIRST_A,
				'width' => 600,
				'height' => 600
			],
			[
				'name' => self::SYSMAP_FIRST_Z,
				'width' => 600,
				'height' => 600
			],
			[
				'name' => self::SYSMAP_LOW_WIDTH,
				'width' => 10,
				'height' => 600
			],
			[
				'name' => self::SYSMAP_HIGH_WIDTH,
				'width' => 1000,
				'height' => 600
			],
			[
				'name' => self::SYSMAP_LOW_HEIGHT,
				'width' => 600,
				'height' => 10
			],
			[
				'name' => self::SYSMAP_HIGH_HEIGHT,
				'width' => 600,
				'height' => 1000
			],
			[
				'name' => self::SYSMAP_SPACES_NAME,
				'width' => 600,
				'height' => 600
			]
		]);

		self::$sysmapids = CDataHelper::getIds('name');
	}

	public function getMapsData() {
		return [
			[
				[
					[
						'Name' => self::SYSMAP_NAME_LOW_NUMBER,
						'Width' => '600',
						'Height' => '600'
					],
					[
						'Name' => self::SYSMAP_NAME_HIGH_NUMBER,
						'Width' => '600',
						'Height' => '600'
					],
					[
						'Name' => self::SYSMAP_NAME_WITH_SYMBOLS,
						'Width' => '600',
						'Height' => '600'
					],
					[
						'Name' => self::SYSMAP_TO_DELETE,
						'Width' => '600',
						'Height' => '600'
					],
					[
						'Name' => self::SYSMAP_FIRST_A,
						'Width' => '600',
						'Height' => '600'
					],
					[
						'Name' => self::SYSMAP_FIRST_Z,
						'Width' => '600',
						'Height' => '600'
					],
					[
						'Name' => self::SYSMAP_LOW_WIDTH,
						'Width' => '10',
						'Height' => '600'
					],
					[
						'Name' => self::SYSMAP_HIGH_WIDTH,
						'Width' => '1000',
						'Height' => '600'
					],
					[
						'Name' => self::SYSMAP_LOW_HEIGHT,
						'Width' => '600',
						'Height' => '10'
					],
					[
						'Name' => self::SYSMAP_HIGH_HEIGHT,
						'Width' => '600',
						'Height' => '1000'
					],
					[
						'Name' => self::SYSMAP_SPACES_NAME,
						'Width' => '600',
						'Height' => '600'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getMapsData
	 */
	public function testPageMaps_CheckLayout($data) {
		$sysmaps = CDBHelper::getCount(self::SYSMAPS_SQL);
		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$this->page->assertTitle('Configuration of network maps');
		$this->page->assertHeader('Maps');

		// Check buttons.
		$this->assertEquals(4, $this->query('button', ['Create map', 'Import', 'Apply', 'Reset'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		foreach (['Export', 'Delete'] as $button) {
			$element = $this->query('button', $button)->one();
			$this->assertTrue($element->isDisplayed());
			$this->assertFalse($element->isEnabled());
		}

		// Check rows in the table.
		$this->assertTableHasData($data);

		// Check links for created maps.
		foreach (self::$sysmapids as $name => $id) {
			$row = $this->getTable()->findRow('Name', $name);
			$this->assertEquals('sysmaps.php?form=update&sysmapid='.$id, $row->getColumn('Actions')
					->query('link:Properties')->one()->getAttribute('href')
			);
			$this->assertEquals('sysmap.php?sysmapid='.$id, $row->getColumn('Actions')->query('link:Edit')->one()
					->getAttribute('href')
			);
			$this->assertEquals('zabbix.php?action=map.view&sysmapid='.$id, $row->getColumn('Name')
					->query('link', $name)->one()->getAttribute('href')
			);
		}

		// Get filter element.
		$filter = CFilterElement::find()->one();

		// Expand filter if it is collapsed.
		$filter->setContext(CFilterElement::CONTEXT_RIGHT)->expand();

		$form = $filter->getForm();

		$this->assertEquals(['Name'], $form->getLabels()->asText());
		$name_field = $form->getField('Name');
		$this->assertEquals('', $name_field->getValue());
		$this->assertEquals(255, $name_field->getAttribute('maxlength'));

		// Check filter expanding/collapsing.
		$this->assertTrue($filter->isExpanded());
		foreach ([false, true] as $state) {
			$filter->expand($state);

			// Refresh the page to make sure the filter state is still saved.
			$this->page->refresh()->waitUntilReady();
			$this->assertTrue($filter->isExpanded($state));
		}

		// Check table headers and sortable headers.
		$this->assertEquals(['Name', 'Width', 'Height'], $this->getTable()->getSortableHeaders()->asText());
		$this->assertEquals(['', 'Name', 'Width', 'Height', 'Actions'], $this->getTable()->getHeadersText());

		// Check the selected amount.
		$this->assertTableStats($sysmaps);
		$this->assertSelectedCount(0);
		$this->selectTableRows(self::SYSMAP_FIRST_A);
		$this->assertSelectedCount(1);
		$this->selectTableRows();
		$this->assertSelectedCount($sysmaps);

		// Check that delete and export buttons became clickable.
		$this->assertTrue($this->query('button', ['Delete', 'Export'])->one()->isClickable());

		// Check export options.
		$this->assertEquals(['YAML', 'XML', 'JSON'], $this->query('id:export')->one()->asPopupButton()->getMenu()
				->getItems()->asText()
		);
		CPopupMenuElement::find()->one()->close();

		// Reset filter and check that maps are unselected.
		$form->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();
		$this->assertSelectedCount(0);
	}

	public function testPageMaps_Sorting() {
		$this->page->login()->open('sysmaps.php?sort=name&sortorder=DESC');
		$table = $this->getTable();

		foreach (['Name', 'Width', 'Height'] as $column) {
			$values = $this->getTableColumnData($column);
			natcasesort($values);

			foreach ([$values, array_reverse($values)] as $sorted_values) {
				$table->query('link', $column)->waitUntilClickable()->one()->click();
				$table->waitUntilReloaded();
				$this->assertTableDataColumn($sorted_values, $column);
			}
		}
	}

	public function getFilterData() {
		return [
			// #0 View results with empty Name.
			[
				[
					'filter' => [
						'Name' => ''
					],
					'expected' => [
						self::SYSMAP_NAME_LOW_NUMBER,
						self::SYSMAP_NAME_HIGH_NUMBER,
						self::SYSMAP_FIRST_A,
						'Local network',
						'Map for form testing',
						'Map for testing feedback',
						'Map for widget communication test',
						'Map for widget copies',
						self::SYSMAP_SPACES_NAME,
						self::SYSMAP_HIGH_HEIGHT,
						self::SYSMAP_HIGH_WIDTH,
						'Map with icon mapping',
						'Map with links',
						self::SYSMAP_LOW_HEIGHT,
						self::SYSMAP_LOW_WIDTH,
						self::SYSMAP_NAME_WITH_SYMBOLS,
						'Public map with image',
						self::SYSMAP_TO_DELETE,
						'Test map for Properties',
						'testZBX6840',
						self::SYSMAP_FIRST_Z
					]
				]
			],
			// TODO: Uncomment the test cases after issue in ZBX-24652 is fixed. Update the numbering of all test cases.
			/*
			// # View results with multiple spaces for Name.
			[
				[
					'filter' => [
						'Name' => '           '
					]
				]
			],
			// # View results with single space in the name.
			[
				[
					'filter' => [
						'Name' => ' '
					],
					'expected' => [
						self::SYSMAP_NAME_LOW_NUMBER,
						self::SYSMAP_NAME_HIGH_NUMBER,
						self::SYSMAP_FIRST_A,
						'Local network',
						self::SYSMAP_SPACES_NAME,
						self::SYSMAP_HIGH_HEIGHT,
						self::SYSMAP_HIGH_WIDTH,
						'Map with icon mapping',
						self::SYSMAP_LOW_HEIGHT,
						self::SYSMAP_LOW_WIDTH,
						self::SYSMAP_NAME_WITH_SYMBOLS,
						'Public map with image',
						self::SYSMAP_TO_DELETE,
						'Test map 1',
						self::SYSMAP_FIRST_Z
					]
				]
			],
			// # View results if request has trailing spaces.
			[
				[
					'filter' => [
						'Name' => 'spaces   '
					]
				]
			],
			// # View results if request has leading spaces.
			[
				[
					'filter' => [
						'Name' => '   spaces'
					]
				]
			],
			*/
			// #1 View results with request that has spaces separating the words.
			[
				[
					'filter' => [
						'Name' => self::SYSMAP_SPACES_NAME
					],
					'expected' => [
						self::SYSMAP_SPACES_NAME
					]
				]
			],
			// #2 View results with partial name match.
			[
				[
					'filter' => [
						'Name' => 'bix'
					],
					'expected' => [
						self::SYSMAP_FIRST_Z
					]
				]
			],
			// #3 View results with partial name match with space.
			[
				[
					'filter' => [
						'Name' => 'p w'
					],
					'expected' => [
						self::SYSMAP_HIGH_HEIGHT,
						self::SYSMAP_HIGH_WIDTH,
						'Map with icon mapping',
						'Map with links',
						self::SYSMAP_LOW_HEIGHT,
						self::SYSMAP_LOW_WIDTH,
						'Public map with image'
					]
				]
			],
			// #4 View results with partial match, trailing and leading spaces.
			[
				[
					'filter' => [
						'Name' => ' with lowest '
					],
					'expected' => [
						self::SYSMAP_LOW_HEIGHT,
						self::SYSMAP_LOW_WIDTH
					]
				]
			],
			// #5 View results with upper case.
			[
				[
					'filter' => [
						'Name' => 'SORTING'
					],
					'expected' => [
						self::SYSMAP_NAME_LOW_NUMBER,
						self::SYSMAP_NAME_HIGH_NUMBER,
						self::SYSMAP_FIRST_A,
						self::SYSMAP_FIRST_Z
					]
				]
			],
			// #6 View results with lower case.
			[
				[
					'filter' => [
						'Name' => 'sorting'
					],
					'expected' => [
						self::SYSMAP_NAME_LOW_NUMBER,
						self::SYSMAP_NAME_HIGH_NUMBER,
						self::SYSMAP_FIRST_A,
						self::SYSMAP_FIRST_Z
					]
				]
			],
			// #7 View results with non-existing request.
			[
				[
					'filter' => [
						'Name' => 'empty-result'
					]
				]
			],
			// #8 View results if request contains special symbols.
			[
				[
					'filter' => [
						'Name' => '🤖 ⃠ Ω⌚Ԏ﨧'
					],
					'expected' => [
						self::SYSMAP_NAME_WITH_SYMBOLS
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageMaps_Filter($data) {
		$this->page->login()->open('sysmaps.php?sort=name&sortorder=ASC');
		$filter = CFilterElement::find()->one();

		// Expand filter if it is collapsed.
		$filter->setContext(CFilterElement::CONTEXT_RIGHT)->expand();

		$form = $filter->getForm();

		// Fill filter fields if such present in data provider.
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$this->page->waitUntilReady();

		// Check that expected maps are returned in the list.
		$expected_data = CTestArrayHelper::get($data, 'expected', []);
		$this->assertTableDataColumn($expected_data);

		// Check the displaying amount.
		$this->assertTableStats(count($expected_data));

		// Reset filter to not influence further tests.
		$this->query('button:Reset')->one()->click();
	}

	public function testPageMaps_CancelDelete() {
		$this->cancelDelete([self::SYSMAP_FIRST_A]);
	}

	public function testPageMaps_CancelMassDelete() {
		$this->cancelDelete();
	}

	public function getDeleteData() {
		return [
			// Delete 1 map.
			[
				[
					'name' => [self::SYSMAP_TO_DELETE]
				]
			],
			// Delete 2 maps.
			[
				[
					'name' => [self::SYSMAP_FIRST_A, self::SYSMAP_FIRST_Z]
				]
			],
			// Delete all maps.
			[
				[]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testPageMaps_Delete($data) {
		$this->page->login()->open('sysmaps.php')->waitUntilReady();

		// Sysmap count that will be selected before delete action.
		$map_names = CTestArrayHelper::get($data, 'name', []);
		$count_names = count($map_names);
		$plural = ($count_names === 1) ? '' : 's';
		$this->selectTableRows($map_names);
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->assertEquals('Delete selected map'.$plural.'?', $this->page->getAlertText());
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Network map'.$plural.' deleted');
		$this->assertSelectedCount(0);

		$all = CDBHelper::getCount(self::SYSMAPS_SQL);
		$db_check = ($count_names > 0)
			? CDBHelper::getCount('SELECT NULL FROM sysmaps WHERE name IN ('.CDBHelper::escape($data['name']).')')
			: $all;
		$this->assertEquals(0, $db_check);

		$this->assertTableStats($all);
	}

	protected function cancelDelete($sysmaps = []) {
		$old_hash = CDBHelper::getHash(self::SYSMAPS_SQL);

		// Count of the maps that will be selected before delete action.
		$sysmap_count = ($sysmaps === []) ? CDBHelper::getCount(self::SYSMAPS_SQL) : count($sysmaps);

		$this->page->login()->open('sysmaps.php?filter_rst=1')->waitUntilReady();
		$this->selectTableRows($sysmaps);
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->assertEquals('Delete selected map'.(($sysmap_count > 1) ? 's?' : '?'), $this->page->getAlertText());
		$this->page->dismissAlert();
		$this->page->waitUntilReady();
		$this->assertSelectedCount($sysmap_count);
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SYSMAPS_SQL));
	}
}
