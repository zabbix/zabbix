<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * @backup dashboard
 *
 * @onBefore prepareData
 */
class testDashboardItemNavigatorWidget extends testWidgets {

	/**
	 * Attach MessageBehavior, TableBehavior and TagBehavior to the test.
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class,
			[
				'class' => CTagBehavior::class,
				'tag_selector' => 'id:tags_table_item_tags'
			]
		];
	}

	protected static $dashboardid;
	protected static $groupids;
	protected static $update_widget = 'Update Item navigator widget';
	const DEFAULT_WIDGET = 'Default Item navigator widget';
	const DELETE_WIDGET = 'Widget for delete';
	const DEFAULT_DASHBOARD = 'Dashboard for Item navigator widget test';
	const DASHBOARD_FOR_WIDGET_CREATE = 'Dashboard for Item navigator widget create/update test';

	/**
	 * Get 'Group by' table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getGroupByTable() {
		return $this->query('id:group_by-table')->asMultifieldTable([
			'mapping' => [
				'2' => [
					'name' => 'attribute',
					'selector' => 'xpath:./z-select',
					'class' => 'CDropdownElement'
				],
				'3' => [
					'name' => 'tag',
					'selector' => 'xpath:./input',
					'class' => 'CElement'
				]
			]
		])->waitUntilVisible()->one();
	}

	public static function prepareData() {
		CDataHelper::call('dashboard.create', [
			[
				'name' => self::DEFAULT_DASHBOARD,
				'pages' => [
					[
						'name' => 'Page with default widgets',
						'widgets' => [
							[
								'type' => 'itemnavigator',
								'name' => self::DEFAULT_WIDGET,
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 5
							],
							[
								'type' => 'itemnavigator',
								'name' => self::DELETE_WIDGET,
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 5
							]
						]
					]
				]
			],
			[
				'name' => self::DASHBOARD_FOR_WIDGET_CREATE,
				'pages' => [
					[
						'name' => 'Page with created/updated widgets',
						'widgets' => [
							[
								'type' => 'itemnavigator',
								'name' => self::$update_widget,
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'ZBXIN'
									]
								]
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = CDataHelper::getIds('name');

		// Create hostgroups for hosts.
		CDataHelper::call('hostgroup.create', [
			['name' => 'First Group for Item navigator check'],
			['name' => 'Second Group for Item navigator check']
		]);
		self::$groupids = CDataHelper::getIds('name');

		// Create hosts.
		CDataHelper::createHosts([
			[
				'host' => 'First host for Item navigator widget',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.1',
						'dns' => '',
						'port' => '11197'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['First Group for Item navigator check']
				]
			],
			[
				'host' => 'Second host for Item navigator widget',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.2',
						'dns' => '',
						'port' => '11198'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['Second Group for Item navigator check']
				]
			]
		]);
	}

	public function testDashboardItemNavigatorWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DEFAULT_DASHBOARD])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dialog = $dashboard->edit()->addWidget();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form = $dialog->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item navigator')]);

		// Check default state.
		$default_state = [
			'Type' => 'Item navigator',
			'Name' => '',
			'Show header' => true,
			'Refresh interval' => 'Default (1 minute)',
			'Host groups' => '',
			'Hosts' => '',
			'Host tags' => 'And/Or',
			'id:host_tags_0_tag' => '',
			'id:host_tags_0_operator' => 'Contains',
			'id:host_tags_0_value' => '',
			'Item patterns' => '',
			'Item tags' => 'And/Or',
			'id:item_tags_0_tag' => '',
			'id:item_tags_0_operator' => 'Contains',
			'id:item_tags_0_value' => '',
			'State' => 'All',
			'Show problems' => 'Unsuppressed',
			'Group by' => [],
			'Item limit' => 100
		];

		$form->checkValue($default_state);
		$this->assertEquals(['Item limit'], $form->getRequiredLabels());

		// Check dropdown options.
		$this->getGroupByTable()->fill(['attribute' => 'Host group']);

		$options = [
			'Refresh interval' => ['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute',
				'2 minutes', '10 minutes', '15 minutes'
			],
			'id:host_tags_0_operator' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'
			],
			'id:item_tags_0_operator' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'
			],
			'Group by' => ['Host group', 'Host name', 'Host tag value', 'Item tag value']
		];
		foreach ($options as $field => $values) {
			$this->assertEquals($values, $form->getField($field)->asDropdown()->getOptions()->asText());
		}

		$inputs = [
			'Name' => [
				'maxlength' => 255,
				'placeholder' => 'default'
			],
			'id:groupids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:hostids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:host_tags_0_tag' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'id:host_tags_0_value' => [
				'maxlength' => 255,
				'placeholder' => 'value'
			],
			'id:items__ms' => [
				'placeholder' => 'patterns'
			],
			'id:item_tags_0_tag' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'id:item_tags_0_value' => [
				'maxlength' => 255,
				'placeholder' => 'value'
			],
			'id:group_by_0_tag_name' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'Item limit' => [
				'maxlength' => 4
			]
		];
		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
			}
		}

		// Check radio buttons.
		$selection_elements = [
			'Host tags' => ['And/Or', 'Or'],
			'Item tags' => ['And/Or', 'Or'],
			'State' => ['All', 'Normal', 'Not supported'],
			'Show problems' => ['All', 'Unsuppressed', 'None']
		];
		foreach ($selection_elements as $name => $labels) {
			$this->assertEquals($labels, $form->getField($name)->getLabels()->asText());
		}

		// Check 'Host tags', 'Item tags' and 'Group by' table buttons.
		foreach (['id:tags_table_host_tags', 'id:tags_table_item_tags', 'id:group_by-table'] as $locator) {
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
