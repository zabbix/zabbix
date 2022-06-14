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

require_once dirname(__FILE__) . '/../../include/CWebTest.php';

/**
 * @backup profiles
 */
class testDashboardDynamicItemWidgets extends CWebTest {

	public static function getWidgetsData() {
		return [
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
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: Dynamic widgets H1I1',
							'expected' => ['Dynamic widgets H1I1' => '11']
						],
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: 2 items',
							'expected' => [
								'Dynamic widgets H1I1' => '11',
								'Dynamic widgets H1I2' => '12'
							]
						],
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
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP4 (H1IP1 and H2I1)']
					]
				]
			],
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
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: Dynamic widgets H1I1',
							'expected' => ['Dynamic widgets H1I1' => '11']
						],
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: 2 items',
							'expected' => [
								'Dynamic widgets H1I1' => '11',
								'Dynamic widgets H1I2' => '12'
							]
						],
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
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP4 (H1IP1 and H2I1)']
					]
				]
			],
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
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H2: Dynamic widgets H2I1',
							'expected' => ['Dynamic widgets H2I1' => '21']
						],
						[
							'type' => 'Plain text',
							'header' => 'Plain text'
						],
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H2: Dynamic widgets H2I1',
							'expected' => ['Dynamic widgets H2I1' => '21']
						],
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
						['type' => 'Graph prototype', 'header' => 'Graph prototype']
					]
				]
			],
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
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H3: Dynamic widgets H3I1',
							'expected' => ['Dynamic widgets H3I1' => '31']
						],
						[
							'type' => 'Plain text',
							'header' => 'Plain text'
						],
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H3: Dynamic widgets H3I1',
							'expected' => ['Dynamic widgets H3I1' => '31']
						],
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
						['type' => 'Graph prototype', 'header' => 'Graph prototype']
					]
				]
			],
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
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Plain text',
							'header' => 'Plain text'
						],
						[
							'type' => 'Plain text',
							'header' => 'Plain text'
						],
						[
							'type' => 'Plain text',
							'header' => 'Plain text'
						],
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
						['type' => 'Graph prototype', 'header' => 'Graph prototype']
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1050');
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
				$content = $widget_content->query('class:nothing-to-show')->one()->getText();
				$message = ($expected['type'] === 'URL')
						? 'No host selected.'
						: 'No permissions to referred object or it does not exist!';
				$this->assertEquals($message, $content);
				continue;
			}

			// Check widget content when the host match dynamic option criteria.
			$this->assertFalse($widget_content->query('class:nothing-to-show')->one(false)->isValid());
			switch ($expected['type']) {
				case 'Plain text':
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
