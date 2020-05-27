<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
class testDynamicItemWidgets extends CWebTest {

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
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP4 (H1IP1 and H2I2)']
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
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP4 (H1IP1 and H2I2)']
					]
				]
			],
			[
				[
					'host_filter' => 'Dynamic widgets H2',
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H2: Dynamic widgets H2I1'],
						['type' => 'Graph (classic)', 'header' => ''],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H2: Dynamic widgets H1 G1 (I1)'],
						['type' => 'Graph (classic)', 'header' => ''],
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
						// TODO: change after fix ZBX-17825, should be 'Dynamic widgets H2: Dynamic widgets H1 GP4 (H1 IP1 and H2 I1)'
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP4 (H1IP1 and H2I2)']
					]
				]
			],
			[
				[
					'host_filter' => 'Dynamic widgets H3',
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H3: Dynamic widgets H3I1'],
						['type' => 'Graph (classic)', 'header' => ''],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H3: Dynamic widgets H1 G1 (I1)'],
						['type' => 'Graph (classic)', 'header' => ''],
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
						['type' => 'Graph (classic)', 'header' => ''],
						['type' => 'Graph (classic)', 'header' => ''],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => ''],
						['type' => 'Graph (classic)', 'header' => ''],
						['type' => 'Graph (classic)', 'header' => ''],
						['type' => 'Graph (classic)', 'header' => ''],
						[
							'type' => 'Plain text',
							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
							'expected' => ['Dynamic widgets H1I2' => '12']
						],
						[
							'type' => 'Plain text',
							'header' => 'Plain text',
						],
						[
							'type' => 'Plain text',
							'header' => 'Plain text'
						],
						[
							'type' => 'Plain text',
							'header' => 'Plain text',
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
	 * @dataProvider getWidgetsData
	 */
	public function testDynamicItemWidgets_Layout($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=105');
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
			// TODO: remove after fix ZBX-17821
			CElementQuery::getDriver()->navigate()->refresh();
			$this->page->waitUntilReady();
		}

		$this->checkWidgetContent($data['widgets']);
		// Check that after page refresh widgets remain the same.
		CElementQuery::getDriver()->navigate()->refresh();
		$this->page->waitUntilReady();
		$this->checkWidgetContent($data['widgets']);
	}

	private function checkWidgetContent($data) {
		$dashboard = CDashboardElement::find()->one();
		$all_widgets = $dashboard->getWidgets();
		$this->assertEquals(count($data), $all_widgets->count());

		foreach ($data as $key => $widget) {
			$get_widget = $all_widgets->get($key);
			$widget_content = $get_widget->getContent();
			$this->assertEquals($widget['header'], $get_widget->getHeaderText());

			// Check widget empty content, because the host doesn't match dynamic option criteria.
			if ($widget['header'] === '' || $widget['header'] === $widget['type']
					|| CTestArrayHelper::get($widget, 'empty', false)) {
				$content = $widget_content->query('class:nothing-to-show')->one()->getText();
				$message = ($widget['type'] === 'URL')
						? 'No host selected.'
						: 'No permissions to referred object or it does not exist!';
				$this->assertEquals($message, $content);
				continue;
			}

			// Check widget content when the host match dynamic option criteria.
			$this->assertFalse($widget_content->query('class:nothing-to-show')->one(false)->isValid());
			switch ($widget['type']) {
				case 'Plain text':
					$data = $widget_content->asTable()->index('Name');
					foreach ($widget['expected'] as $item => $value) {
						$row = $data[$item];
						$this->assertEquals($value, $row['Value']);
					}
					break;

				case 'URL':
					CElementQuery::getDriver()->switchTo()->frame($widget_content->query('id:iframe')->one());
					$form = $this->query('xpath://form[@action="hostinventories.php"]')->asForm()->one();
					$this->assertEquals($widget['host'], $form->getFieldContainer('Host name')->getText());
					CElementQuery::getDriver()->switchTo()->defaultContent();
					break;
			}
		}
	}
}
