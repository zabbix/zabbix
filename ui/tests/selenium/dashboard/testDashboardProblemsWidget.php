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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup config, widget
 *
 * @onBefore prepareDashboardData
 */
class testDashboardProblemsWidget extends CWebTest {

	/**
	 * Id of the dashboard where Problem widget is created and updated.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_hostid';

	public function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			'name' => 'Problem widget dashboard',
			'auto_start' => 0,
			'pages' => [
				[
					'name' => 'First Page',
					'display_period' => 3600,
					'widgets' => [
						[
							'type' => 'problems',
							'name' => 'Problem widget for updating',
							'x' => 0,
							'y' => 0,
							'width' => 11,
							'height' => 6,
							'view_mode' => 0,
							'fields' => [
								[
									'type' => 0,
									'name' => 'severities',
									'value' => 0
								],
								[
									'type' => 0,
									'name' => 'severities',
									'value' => 4
								],
								[
									'type' => 0,
									'name' => 'severities',
									'value' => 2
								],
								[
									'type' => 0,
									'name' => 'evaltype',
									'value' => 2
								],
								[
									'type' => 0,
									'name' => 'rf_rate',
									'value' => '900'
								],
								[
									'type' => 0,
									'name' => 'show',
									'value' => 3
								],
								[
									'type' => 0,
									'name' => 'show_lines',
									'value' => 12
								],
								[
									'type' => 0,
									'name' => 'show_opdata',
									'value' => 1
								],
								[
									'type' => 0,
									'name' => 'show_suppressed',
									'value' => 1
								],
								[
									'type' => 0,
									'name' => 'show_tags',
									'value' => 2
								],
								[
									'type' => 0,
									'name' => 'sort_triggers',
									'value' => 15
								],
								[
									'type' => 0,
									'name' => 'show_timeline',
									'value' => 0
								],
								[
									'type' => 0,
									'name' => 'tag_name_format',
									'value' => 1
								],
								[
									'type' => 0,
									'name' => 'tags.operator.0',
									'value' => 1
								],
								[
									'type' => 0,
									'name' => 'tags.operator.1',
									'value' => 1
								],
								[
									'type' => 0,
									'name' => 'unacknowledged',
									'value' => 1
								],
								[
									'type' => 1,
									'name' => 'problem',
									'value' => 'test2'
								],
								[
									'type' => 1,
									'name' => 'tags.value.0',
									'value' => '2'
								],
								[
									'type' => 1,
									'name' => 'tags.value.1',
									'value' => '33'
								],
								[
									'type' => 1,
									'name' => 'tag_priority',
									'value' => '1,2'
								],
								[
									'type' => 1,
									'name' => 'tags.tag.0',
									'value' => 'tag2'
								],
								[
									'type' => 1,
									'name' => 'tags.tag.1',
									'value' => 'tagg33'
								],
								[
									'type' => 2,
									'name' => 'exclude_groupids',
									'value' => 50014
								],
								[
									'type' => 2,
									'name' => 'groupids',
									'value' => 50005
								],
								[
									'type' => 3,
									'name' => 'hostids',
									'value' => 99026
								]
							]
						],
						[
							'type' => 'problem',
							'name' => 'Problem widget for delete',
							'x' => 11,
							'y' => 0,
							'width' => 10,
							'height' => 5,
							'view_mode' => 0
						]
					]
				]
			]
		]);

		$this->assertArrayHasKey('dashboardids', $response);
		self::$dashboardid = $response['dashboardids'][0];
	}

	public function testDashboardProblemsWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$form = CDashboardElement::find()->one()->edit()->addWidget()->asForm();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form->fill(['Type' => 'Problems']);
		$dialog->waitUntilReady();

		$this->assertEquals(['Type', 'Name', 'Refresh interval', 'Show', 'Host groups', 'Exclude host groups', 'Hosts',
				'Problem', 'Severity', 'Tags', '', 'Show tags', 'Tag name', 'Tag display priority', 'Show operational data',
				'Show suppressed problems', 'Show unacknowledged only', 'Sort entries by', 'Show timeline', 'Show lines'],
				$form->getLabels()->asText()
		);

		// Check default fields.
		$fields = [
			'Name' => ['value' => '', 'placeholder' => 'default', 'maxlength' => 255, 'enabled' => true],
			'Refresh interval' => ['value' => 'Default (1 minute)', 'enabled' => true],
			'id:show_header' => ['value' => true, 'enabled' => true],
			'Show' => ['value' => 'Recent problems', 'enabled' => true],
			'Host groups' => ['value' => '', 'enabled' => true],
			'Exclude host groups' => ['value' => '', 'enabled' => true],
			'Hosts' => ['value' => '', 'enabled' => true],
			'Problem' => ['value' => '', 'maxlength' => 255, 'enabled' => true],

			// Severity checkboxes.
			'id:severities_0' => ['value' => false, 'enabled' => true],
			'id:severities_1' => ['value' => false, 'enabled' => true],
			'id:severities_2' => ['value' => false, 'enabled' => true],
			'id:severities_3' => ['value' => false, 'enabled' => true],
			'id:severities_4' => ['value' => false, 'enabled' => true],
			'id:severities_5' => ['value' => false, 'enabled' => true],

			// Tags table.
			'id:evaltype' => ['value' => 'And/Or', 'enabled' => true],
			'id:tags_0_tag' => ['value' => '', 'placeholder' => 'tag', 'enabled' => true, 'maxlength' => 255],
			'id:tags_0_operator' => ['value' => 'Contains', 'enabled' => true],
			'id:tags_0_value' => ['value' => '', 'placeholder' => 'value', 'enabled' => true, 'maxlength' => 255],

			'Show tags' => ['value' => 'None', 'enabled' => true],
			'Tag name' => ['value' => 'Full', 'enabled' => false],
			'Tag display priority' => ['value' => '', 'placeholder' => 'comma-separated list', 'enabled' => false, 'maxlength' => 255],
			'Show operational data' => ['value' => 'None', 'enabled' => true],
			'Show suppressed problems' => ['value' => false, 'enabled' => true],
			'Show unacknowledged only' => ['value' => false, 'enabled' => true],
			'Sort entries by' => ['value' => 'Time (descending)', 'enabled' => true],
			'Show timeline' => ['value' => true, 'enabled' => true],
			'Show lines' => ['value' => 25, 'enabled' => true, 'maxlength' => 3],
		];

		foreach ($fields as $field => $attributes) {
			$this->assertEquals($attributes['value'], $form->getField($field)->getValue());
			$this->assertTrue($form->getField($field)->isEnabled($attributes['enabled']));

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $form->getField($field)->getAttribute('maxlength'));
			}

			if (array_key_exists('placeholder', $attributes)) {
				$this->assertEquals($attributes['placeholder'], $form->getField($field)->getAttribute('placeholder'));
			}
		}

		// Check dropdowns options presence.
		$dropdowns = [
			'Refresh interval' => ['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute', '2 minutes',
					'10 minutes', '15 minutes'
			],
			'id:tags_0_operator' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal', 'Does not contain'],
			'Sort entries by' => ['Time (descending)', 'Time (ascending)', 'Severity (descending)', 'Severity (ascending)',
					'Problem (descending)', 'Problem (ascending)', 'Host (descending)', 'Host (ascending)'
			]
		];

		foreach ($dropdowns as $dropdown => $labels) {
				$this->assertEquals($labels, $form->getField($dropdown)->asDropdown()->getOptions()->asText());
		}

		// Check severities fields.
		$severities = ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'];

		foreach ($severities as $id => $label) {
			$this->assertTrue($form->getField('Severity')->query("xpath:.//label[text()=".
					CXPathHelper::escapeQuotes($label)."]/../input[@id='severities_".$id."']")->exists()
			);
		}

		// Check segmented radiobuttons labels.
		$radios = [
			'Show' => ['Recent problems', 'Problems', 'History'],
			'Tags' => ['And/Or', 'Or'],
			'Show tags' => ['None', '1', '2', '3'],
			'Show operational data' => ['None', 'Separately', 'With problem name']
		];

		foreach ($radios as $radio => $labels) {
				$this->assertEquals($labels, $form->getField($radio)->asSegmentedRadio()->getLabels()->asText());
		}

		// Check Tag display options editability.
		foreach ([1 => true, 2 => true, 3 => true, 'None' => false] as $value => $status) {
			$form->getField('Show tags')->asSegmentedRadio()->select($value);
			$this->assertTrue($form->getField('Tag name')->isEnabled($status));
			$this->assertTrue($form->getField('Tag display priority')->isEnabled($status));
		}

		$dialog->close();
	}
}
