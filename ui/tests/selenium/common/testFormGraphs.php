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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

class testFormGraphs extends CWebTest {

	/**
	 * Flag for graph prototype.
	 */
	public $prototype = false;

	/**
	 * URL for opening graph or graph prototype form.
	 */
	public $url;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function getLayoutData() {
		return [
			[
				[
					'check_defaults' => true, // Check tabs, empty item table and preview only for one time.
					'change_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Normal'),
					],
					'visible_fields' => [
						['field' => 'id:name', 'value' => '', 'maxlength' => 255],
						['field' => 'id:width', 'value' => '900', 'maxlength' => 5],
						['field' => 'id:height', 'value' => '200', 'maxlength' => 5],
						['field' => 'id:graphtype', 'value' => 'Normal'],
						['field' => 'id:show_legend', 'value' => true],
						['field' => 'id:show_work_period', 'value' => true],
						['field' => 'id:show_triggers', 'value' => true],
						['field' => 'id:visible_percent_left', 'value' => false], // Percentile line (left) checkbox.
						['field' => 'id:visible_percent_right', 'value' => false], // Percentile line (right) checkbox.
						['field' => 'id:percent_left', 'visible' => false], // Percentile line (left) input.
						['field' => 'id:percent_right', 'visible' => false], // Percentile line (right) input.
						['field' => 'id:ymin_type', 'value' => 'Calculated'], // Y axis MIN value dropdown.
						['field' => 'id:ymax_type', 'value' => 'Calculated'], // Y axis MAX value dropdown.
						['field' => 'id:yaxismin', 'visible' => false], // Y axis MIN fixed value input.
						['field' => 'id:yaxismax', 'visible' => false], // Y axis MAX fixed value input.
						['field' => 'id:ymin_name', 'visible' => false], // Y axis MIN item input.
						['field' => 'id:ymax_name', 'visible' => false], // Y axis MAX item input.
						['field' => 'id:itemsTable', 'visible' => true]
					]
				]
			],
			[
				[
					'check_defaults' => true,
					'change_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Stacked'),
					],
					'visible_fields' => [
						['field' => 'id:name', 'value' => ''],
						['field' => 'id:width', 'value' => '900'],
						['field' => 'id:height', 'value' => '200'],
						['field' => 'id:graphtype', 'value' => 'Stacked'],
						['field' => 'id:show_legend', 'value' => true],
						['field' => 'id:show_work_period', 'value' => true],
						['field' => 'id:show_triggers', 'value' => true],
						['field' => 'id:visible_percent_left', 'exists' => false], // Percentile line (left) checkbox.
						['field' => 'id:visible_percent_right', 'exists' => false], // Percentile line (right) checkbox.
						['field' => 'id:percent_left', 'exists' => false], // Percentile line (left) input.
						['field' => 'id:percent_right', 'exists' => false], // Percentile line (right) input.
						['field' => 'id:ymin_type', 'value' => 'Calculated'], // Y axis MIN value dropdown.
						['field' => 'id:ymax_type', 'value' => 'Calculated'], // Y axis MAX value dropdown.
						['field' => 'id:yaxismin', 'visible' => false], // Y axis MIN fixed value input.
						['field' => 'id:yaxismax', 'visible' => false], // Y axis MAX fixed value input.
						['field' => 'id:ymin_name', 'visible' => false], // Y axis MIN item input.
						['field' => 'id:ymax_name', 'visible' => false], // Y axis MAX item input.
						['field' => 'id:itemsTable', 'visible' => true]
					]
				]
			],
			[
				[
					'change_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Pie'),
					],
					'visible_fields' => [
						['field' => 'id:name', 'value' => ''],
						['field' => 'id:width', 'value' => '900'],
						['field' => 'id:height', 'value' => '200'],
						['field' => 'id:graphtype', 'value' => 'Pie'],
						['field' => 'id:show_legend', 'value' => true],
						['field' => 'id:show_work_period', 'exists' => false],
						['field' => 'id:show_triggers', 'exists' => false],
						['field' => 'id:visible_percent_left', 'exists' => false], // Percentile line (left) checkbox.
						['field' => 'id:visible_percent_right', 'exists' => false], // Percentile line (right) checkbox.
						['field' => 'id:percent_left', 'exists' => false], // Percentile line (left) input.
						['field' => 'id:percent_right', 'exists' => false], // Percentile line (right) input.
						['field' => 'id:ymin_type', 'exists' => false], // Y axis MIN value dropdown.
						['field' => 'id:ymax_type', 'exists' => false], // Y axis MAX value dropdown.
						['field' => 'id:yaxismin', 'exists' => false], // Y axis MIN fixed value input.
						['field' => 'id:yaxismax', 'exists' => false], // Y axis MAX fixed value input.
						['field' => 'id:ymin_name', 'exists' => false], // Y axis MIN item input.
						['field' => 'id:ymax_name', 'exists' => false], // Y axis MAX item input.
						['field' => 'id:show_3d', 'value' => false],
						['field' => 'id:itemsTable', 'visible' => true]
					]
				]
			],
			[
				[
					'change_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Exploded'),
					],
					'visible_fields' => [
						['field' => 'id:name', 'value' => ''],
						['field' => 'id:width', 'value' => '900'],
						['field' => 'id:height', 'value' => '200'],
						['field' => 'id:graphtype', 'value' => 'Exploded'],
						['field' => 'id:show_legend', 'value' => true],
						['field' => 'id:show_work_period', 'exists' => false],
						['field' => 'id:show_triggers', 'exists' => false],
						['field' => 'id:visible_percent_left', 'exists' => false], // Percentile line (left) checkbox.
						['field' => 'id:visible_percent_right', 'exists' => false], // Percentile line (right) checkbox.
						['field' => 'id:percent_left', 'exists' => false], // Percentile line (left) input.
						['field' => 'id:percent_right', 'exists' => false], // Percentile line (right) input.
						['field' => 'id:ymin_type', 'exists' => false], // Y axis MIN value dropdown.
						['field' => 'id:ymax_type', 'exists' => false], // Y axis MAX value dropdown.
						['field' => 'id:yaxismin', 'exists' => false], // Y axis MIN fixed value input.
						['field' => 'id:yaxismax', 'exists' => false], // Y axis MAX fixed value input.
						['field' => 'id:ymin_name', 'exists' => false], // Y axis MIN item input.
						['field' => 'id:ymax_name', 'exists' => false], // Y axis MAX item input.
						['field' => 'id:show_3d', 'value' => false],
						['field' => 'id:itemsTable', 'visible' => true]
					]
				]
			],
			[
				[
					'change_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Normal'),
						'id:visible_percent_left' => true, // Percentile line (left) checkbox.
						'id:visible_percent_right' => true, // Percentile line (right) checkbox.
					],
					'visible_fields' => [
						['field' => 'id:percent_left', 'value' => 0, 'visible' => true], // Percentile line (left) input.
						['field' => 'id:percent_right', 'value' => 0, 'visible' => true] // Percentile line (right) input.
					]
				]
			],
			[
				[
					'change_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Normal'),
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Fixed'), // Y axis MIN value dropdown.
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Fixed'), // Y axis MAX value dropdown.
					],
					'visible_fields' => [
						['field' => 'id:yaxismin', 'value' => 0, 'visible' => true], // Y axis MIN fixed value input.
						['field' => 'id:yaxismax', 'value' => 100, 'visible' => true] // Y axis MAX fixed value input.
					]
				]
			],
			[
				[
					'change_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Normal'),
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Item'), // Y axis MIN value dropdown.
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Item'), // Y axis MAX value dropdown.
					],
					'visible_fields' => [
						['field' => 'id:ymin_name', 'value' => '', 'visible' => true], // Y axis MIN item input.
						['field' => 'id:ymax_name', 'value' => '', 'visible' => true] // Y axis MAX item input.
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function checkGraphLayout($data) {
		$this->page->login()->open($this->url)->waitUntilReady();
		$this->query('button', ($this->prototype ? 'Create graph prototype' : 'Create graph'))->waitUntilClickable()
				->one()->click();
		$form = $this->query('name:graphForm')->waitUntilVisible()->asForm()->one();

		if (CTestArrayHelper::get($data, 'check_defaults', false)) {
			$this->assertEquals([($this->prototype ? 'Graph prototype' : 'Graph'),'Preview'], $form->getTabs());
			$this->assertFalse($form->query('xpath:.//table[@id="itemsTable"]//div[@class="drag-icon"]')->exists());

			$form->selectTab('Preview');
			$this->page->waitUntilReady();
			$this->assertTrue($this->query('xpath://div[@id="previewChart"]/img')->waitUntilPresent()->one()->isVisible());

			$form->selectTab($this->prototype ? 'Graph prototype' : 'Graph');
			$this->page->waitUntilReady();
		}

		$form->fill($data['change_fields']);

		foreach ($data['visible_fields'] as $visible_field) {
			if (array_key_exists('exists', $visible_field)) {
				$this->assertEquals($visible_field['exists'], $form->query($visible_field['field'])->exists());
			}

			if (array_key_exists('visible', $visible_field)) {
				$this->assertTrue($form->query($visible_field['field'])->one(false)->isVisible($visible_field['visible']));
			}

			if (array_key_exists('value', $visible_field)) {
				$this->assertEquals($visible_field['value'], $form->getField($visible_field['field'])->getValue());
			}

			if (array_key_exists('maxlength', $visible_field)) {
				$this->assertEquals($visible_field['maxlength'], $form->getField($visible_field['field'])->getAttribute('maxlength'));
			}
		}
	}
}
