<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

/**
 * @backup dashboard
 *
 * @onBefore prepareData
 */
class testDashboardHostNavigatorWidget extends CWebTest {

	/**
	 * Attach MessageBehavior, TableBehavior and TagBehavior to the test.
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class,
			[
				'class' => CTagBehavior::class,
				'tag_selector' => 'id:tags_table_host_tags'
			]
		];
	}

	protected static $dashboardid;

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	const SQL = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid';

	public static function prepareData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Host navigator widget test',
				'pages' => [
					[
						'name' => 'Page with default widgets',
						'widgets' => [
							[
								'type' => 'hostnavigator',
								'name' => 'some widget',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = $response['dashboardids'][0];
	}

	public function testDashboardHostNavigatorWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dialog = $dashboard->edit()->addWidget();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form = $dialog->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Host navigator')]);

		// Check default state.
		$default_state = [
			'Type' => 'Host navigator',
			'Name' => '',
			'Show header' => true,
			'Refresh interval' => 'Default (1 minute)',
			'Host groups' => '',
			'Hosts' => '',
			'Host status' => 'Any',
			'Host tags' => 'And/Or',
			'id:host_tags_0_tag' => '',
			'id:host_tags_0_operator' => 'Contains',
			'id:host_tags_0_value' => '',
			'Not classified' => false,
			'Information' => false,
			'Warning' => false,
			'Average' => false,
			'High' => false,
			'Disaster' => false,
			'Show hosts in maintenance' => false,
			'Show problems' => 'Unsuppressed',
			'Group by' => [],
			'Host limit' => 100
		];

		$form->checkValue($default_state);
		$this->assertEquals(['Host limit'], $form->getRequiredLabels());

		// Check dropdown options.
		$form->getField('Group by')->query('button:Add')->one()->click();

		$options = [
			'Refresh interval' => ['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute',
				'2 minutes', '10 minutes', '15 minutes'
			],
			'id:host_tags_0_operator' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'
			],
			'Group by' => ['Host group', 'Tag value', 'Severity']
		];
		foreach ($options as $field => $values) {
			$this->assertEquals($values, $form->getField($field)->asDropdown()->getOptions()->asText());
		}

		// Necessary for inputs check.
		$form->fill(['id:group_by_0_attribute' => 'Tag value']);

		$inputs = [
			'Name' => [
				'maxlength' => 255,
				'placeholder' => 'default'
			],
			'id:groupids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:hosts__ms' => [
				'placeholder' => 'host pattern'
			],
			'id:host_tags_0_tag' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'id:host_tags_0_value' => [
				'maxlength' => 255,
				'placeholder' => 'value'
			],
			'id:group_by_0_tag_name' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'Host limit' => [
				'maxlength' => 4
			]
		];
		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
			}
		}

		// Check radio buttons and checkboxes.
		$selection_elements = [
			'Host status' => ['Any', 'Enabled', 'Disabled'],
			'Host tags' => ['And/Or', 'Or'],
			'Severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
			'Show problems' => ['All', 'Unsuppressed', 'None']
		];
		foreach ($selection_elements as $name => $labels) {
			$this->assertEquals($labels, $form->getField($name)->getLabels()->asText());
		}

		// Check 'Host tags' and 'Group by' table buttons.
		foreach (['id:tags_table_host_tags', 'id:group_by-table'] as $locator) {
			$this->assertEquals(2, $form->query($locator)->one()->query('button', ['Add', 'Remove'])->all()
					->filter((CElementFilter::CLICKABLE))->count()
			);
		}

		// Check if footer buttons present and clickable.
		$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);
	}
}
