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

/**
 * @backup profiles
 *
 * @dataSource DynamicItemWidgets
 *
 * @onBefore prepareData
 */
class testDashboardDynamicItemWidgets extends CWebTest {

	protected static $dashboardids;
	protected static $itemids;

	public static function prepareData() {
		self::$dashboardids = CDataHelper::get('DynamicItemWidgets.dashboardids');
		self::$itemids = CDataHelper::get('DynamicItemWidgets.itemids');

		$items_data = [
			'Dynamic widgets H1I1' => 11,
			'Dynamic widgets H1I2' => 12,
			'Dynamic widgets H2I1' => 21,
			'Dynamic widgets H3I1' => 31
		];
		foreach ($items_data as $name => $value) {
			CDataHelper::addItemData(self::$itemids[$name], $value);
		}
	}

	public static function getWidgetsData() {
		return [
			// #0.
			[
				[
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I1'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G1 (I1)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G3 (I1 and I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1 G4 (H1I1 and H3I1)'],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I1'],
						[
							'type' => 'URL',
							'header' => 'Dynamic URL',
							'empty' => true
						],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP1'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP2 (I1, IP1, H1I2)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP3 (H1IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP4 (H1IP1 and H2I1)'],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I2 - without host override',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I1 - with host override',
							'expected' => ['Dynamic widgets H1I1' => '11']
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I2 - with host override',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I1 & Dynamic widgets H1I2 - with host override',
							'expected' => [
								'Dynamic widgets H1I1' => '11',
								'Dynamic widgets H1I2' => '12'
							]
						]
					]
				]
			],
			// #1.
			[
				[
					'host_filter' => [
						'values' => 'Dynamic widgets H1',
						'context' => 'Dynamic widgets HG1 (H1 and H2)'
					],
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I1'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G1 (I1)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G3 (I1 and I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G4 (H1I1 and H3I1)'],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I1'],
						[
							'type' => 'URL',
							'header' => 'Dynamic URL',
							'host' => 'Dynamic widgets H1'
						],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP1'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP2 (I1, IP1, H1I2)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP3 (H1IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP4 (H1IP1 and H2I1)'],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I2 - without host override',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I1 - with host override',
							'expected' => ['Dynamic widgets H1I1' => '11']
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I2 - with host override',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I1 & Dynamic widgets H1I2 - with host override',
							'expected' => [
								'Dynamic widgets H1I1' => '11',
								'Dynamic widgets H1I2' => '12'
							]
						]
					]
				]
			],
			// #2.
			[
				[
					'host_filter' => 'Dynamic widgets H2',
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H2: Dynamic widgets H2I1'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H2: Dynamic widgets H1 G1 (I1)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H2: Dynamic widgets H1 G3 (I1 and I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H2: Dynamic widgets H1 G4 (H1I1 and H3I1)'],
						['type' => 'Gauge', 'header' => 'Gauge'],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H2: Dynamic widgets H2I1'],
						[
							'type' => 'URL',
							'header' => 'Dynamic URL',
							'host' => 'Dynamic widgets H2'
						],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H2: Dynamic widgets H2IP1'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H2: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H2: Dynamic widgets GP2 (I1, IP1, H1I2)'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I2 - without host override',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I1 - with host override',
							'expected' => ['Dynamic widgets H1I1' => '21']
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I2 - with host override',
							'empty' => true
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I1 & Dynamic widgets H1I2 - with host override',
							'expected' => ['Dynamic widgets H1I1' => '21']
						]
					]
				]
			],
			// #3.
			[
				[
					'host_filter' => 'Dynamic widgets H3',
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H3: Dynamic widgets H3I1'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H3: Dynamic widgets H1 G1 (I1)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H3: Dynamic widgets H1 G3 (I1 and I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H3: Dynamic widgets H1 G4 (H1I1 and H3I1)'],
						['type' => 'Gauge', 'header' => 'Gauge'],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H3: Dynamic widgets H3I1'],
						[
							'type' => 'URL',
							'header' => 'Dynamic URL',
							'host' => 'Dynamic widgets H3'
						],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I2 - without host override',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I1 - with host override',
							'expected' => ['Dynamic widgets H1I1' => '31']
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I2 - with host override',
							'empty' => true
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I1 & Dynamic widgets H1I2 - with host override',
							'expected' => ['Dynamic widgets H1I1' => '31']
						]
					]
				]
			],
			// #4.
			[
				[
					'host_filter' => 'Host for suppression',
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Gauge', 'header' => 'Gauge'],
						['type' => 'Gauge', 'header' => 'Gauge'],
						[
							'type' => 'URL',
							'header' => 'Dynamic URL',
							'host' => 'Host for suppression'
						],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I2 - without host override',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I1 - with host override',
							'empty' => true
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I2 - with host override',
							'empty' => true
						],
						[
							'type' => 'Item history',
							'header' => 'Dynamic widgets H1I1 & Dynamic widgets H1I2 - with host override',
							'empty' => true
						]
					]
				]
			]
		];
	}

	/**
	 * @onBefore createTestFile
	 * @onAfter removeTestFile
	 *
	 * @dataProvider getWidgetsData
	 */
	public function testDashboardDynamicItemWidgets_Layout($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardids['Dashboard for Dynamic item']);
		$dashboard = CDashboardElement::find()->one();

		if (CTestArrayHelper::get($data, 'host_filter', false)) {
			$filter = $dashboard->getControls()->waitUntilVisible();
			$host = $filter->query('class:multiselect-control')->asMultiselect()->one();
			if (is_array($data['host_filter'])) {
				$host->setFillMode(CMultiselectElement::MODE_SELECT)->fill($data['host_filter']);
			}
			else {
				$host->clear()->type($data['host_filter']);
			}
			$this->page->waitUntilReady();
		}
		$this->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();

		// Show hidden headings of graph prototype.
		$this->page->getDriver()->executeScript('var elements = document.getElementsByClassName("dashboard-grid-iterator");'.
				' for (var i = 0; i < elements.length; i++) elements[i].className+=" dashboard-grid-iterator-focus";'
		);

		$this->assertWidgetContent($data['widgets']);

		// Check that after page refresh widgets remain the same.
		$this->page->refresh();
		$this->page->waitUntilReady();
		$this->assertWidgetContent($data['widgets']);
	}

	private function assertWidgetContent($data) {
		$dashboard = CDashboardElement::find()->one();
		$widgets = $dashboard->getWidgets();
		$this->assertEquals(count($data), $widgets->count());

		foreach ($data as $key => $expected) {
			$widget = $widgets->get($key);
			$widget->waitUntilReady();
			$widget_content = $widget->getContent();
			$this->assertEquals($expected['header'], $widget->getHeaderText());

			// Check widget empty content, because the host doesn't match dynamic option criteria.
			if ($expected['header'] === '' || $expected['header'] === $expected['type']
						|| CTestArrayHelper::get($expected, 'empty', false)) {
				$content = $widget_content->query('class:no-data-message')->one()->getText();

				switch ($expected['type']) {
					case 'Item history':
						$message = 'No data found';
						break;
					case 'URL':
						$message = 'No host selected.';
						break;
					default:
						$message = 'No permissions to referred object or it does not exist!';
				}

				$this->assertEquals($message, $content);
				continue;
			}

			// Check widget content when the host match dynamic option criteria.
			$this->assertFalse($widget_content->query('class:no-data-message')->one(false)->isValid());

			switch ($expected['type']) {
				case 'Item history':
					$data = $widget_content->asTable()->index('Name');

					foreach ($expected['expected'] as $item => $value) {
						$row = $data[$item];
						$this->assertEquals($value, $row['Value']);
					}
					break;

				case 'URL':
					$this->page->switchTo($widget_content->query('id:iframe')->one());
					$params = json_decode($this->query('xpath://body')->one()->getText(), true);
					$this->assertEquals($expected['host'], $params['name']);
					$this->page->switchTo();
					break;
			}
		}
	}

	public function createTestFile() {
		if (file_put_contents(PHPUNIT_BASEDIR.'/ui/iframe.php', '<?php echo json_encode($_GET);') === false) {
			throw new Exception('Failed to create iframe test file.');
		}
	}

	public function removeTestFile() {
		@unlink(PHPUNIT_BASEDIR.'/ui/iframe.php');
	}
}
