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


require_once __DIR__.'/../common/testWidgets.php';

/**
 * @backup widget, profiles
 *
 * @onBefore prepareData
 */
class testDashboardWebMonitoringWidget extends testWidgets {

	const DEFAULT_DASHBOARD = 'Dashboard for Web monitoring widget test';
	const DASHBOARD_FOR_WIDGET_ACTIONS = 'Dashboard for Web monitoring widget create/update test';
	protected static $dashboardid;
	protected static $groupids;
	protected static $update_widget = 'Update Web monitoring widget';

	/**
	 * Attach MessageBehavior and TagBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			[
				'class' => CTagBehavior::class,
				'tag_selector' => 'id:tags_table_tags'
			]
		];
	}

	// Create data for autotests that use Web monitoring widget.
	public function prepareData() {
		// Create a Dashboard and pages for widgets.
		CDataHelper::call('dashboard.create', [
			[
				'name' => self::DEFAULT_DASHBOARD,
				'pages' => [
					[
						'name' => 'Layout'
					]
				]
			],
			[
				'name' => self::DASHBOARD_FOR_WIDGET_ACTIONS,
				'pages' => [
					[
						'name' => 'Actions',
						'widgets' => [
							[
								'name' => 'Update Web monitoring widget',
								'type' => 'web',
								'x' => 0,
								'y' => 0,
								'width' => 18,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'WBMNT'
									]
								]
							],
							[
								'name' => 'WebMonitoring for delete',
								'type' => 'web',
								'x' => 18,
								'y' => 0,
								'width' => 18,
								'height' => 4
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = CDataHelper::getIds('name');

		// Create hostgroups for hosts.
		CDataHelper::call('hostgroup.create', [
			['name' => 'First Group for Web Monitoring check'],
			['name' => 'Second Group for Web Monitoring check']
		]);
		self::$groupids = CDataHelper::getIds('name');

		// Create hosts.
		CDataHelper::createHosts([
			[
				'host' => 'First host for Web Monitoring widget',
				'groups' => [
					'groupid' => self::$groupids['First Group for Web Monitoring check']
				]
			],
			[
				'host' => 'Second host for Web Monitoring widget',
				'groups' => [
					'groupid' => self::$groupids['Second Group for Web Monitoring check']
				]
			]
		]);
	}

	/**
	 * Check Web monitoring widget layout.
	 */
	public function testDashboardWebMonitoringWidget_Layout() {
		// Open the create form.
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DEFAULT_DASHBOARD])->waitUntilReady();
		$dialog = CDashboardElement::find()->one()->edit()->addWidget();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form = $dialog->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Web monitoring')]);

		// Check fields presence and default values.
		$form->checkValue([
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)',
			'Show header' => true,
			'Host groups'=> '',
			'Exclude host groups'=> '',
			'Hosts'=> '',
			'Scenario tags' => 'And/Or',
			'id:tags_0_tag' => '',
			'id:tags_0_operator' => 'Contains',
			'id:tags_0_value' => '',
			'Show hosts in maintenance' => true
		]);

		// Check attributes of input elements.
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
			'id:exclude_groupids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:tags_0_tag' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'id:tags_0_value' => [
				'maxlength' => 255,
				'placeholder' => 'value'
			]
		];
		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
			}
		}

		// Check dropdown options.
		$this->assertEquals($form->getField('Refresh interval')->getOptions()->asText(), [
				'Default (1 minute)',
				'No refresh',
				'10 seconds',
				'30 seconds',
				'1 minute',
				'2 minutes',
				'10 minutes',
				'15 minutes'
		]);

		foreach (['Host groups', 'Hosts'] as $label) {
			$selector = $form->getField($label);
			$popup_menu = $selector->query('xpath:.//button[contains(@class, "zi-chevron-down")]')->one();

			foreach ([$selector->query('button:Select')->one(), $popup_menu] as $button) {
				$this->assertTrue($button->isClickable());
			}

			$options = ($label === 'Hosts') ? ['Hosts', 'Widget', 'Dashboard'] : ['Host groups', 'Widget'];
			$this->assertEquals($options, $popup_menu->asPopupButton()->getMenu()->getItems()->asText());

			foreach ($options as $title) {
				$popup_menu->asPopupButton()->getMenu()->select($title);

				if ($title === 'Dashboard') {
					$form->checkValue([$label => 'Dashboard']);
					$this->assertTrue($selector->query('xpath:.//span[@data-hintbox-contents="Dashboard is used as data source."]')
							->one()->isVisible()
					);
				}
				else {
					$dialogs = COverlayDialogElement::find()->all()->waitUntilReady();
					$this->assertEquals($title, $dialogs->last()->getTitle());
					$dialogs->last()->close();
				}
			}
		}

		// After clicking on Select button, check overlay dialog appearance and title.
		foreach (['Host groups','Hosts'] as $label) {
			$field = $form->getField($label);
			$field->query('button:Select')->waitUntilCLickable()->one()->click();
			$dialogs = COverlayDialogElement::find()->all();
			$this->assertEquals($label, $dialogs->last()->waitUntilReady()->getTitle());
			$dialogs->last()->close(true);
		}

		// Check that tag Add and Remove buttons are present and clickable.
		$this->assertEquals(2, $form->query('id:tags_table_tags')->one()->query('button', ['Add', 'Remove'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		// Check that close button is present and clickable.
		$this->assertTrue($dialog->query('class:btn-overlay-close')->one()->isClickable());

		// Check if footer buttons are present and clickable.
		$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);
	}

	public static function getWidgetData() {
		return [
			// #0 Case with no changes.
			[
				[
					'fields' => []
				]
			],
			// #1 Name and show header.
			[
				[
					'fields' => [
						'Show header' => true,
						'Name' => 'Name and show header name',
						'Refresh interval' => 'No refresh'
					]
				]
			],
			// #2 Custom refresh interval.
			[
				[
					'fields' => [
						'Refresh interval' => '10 seconds'
					]
				]
			],
			// #3 Tag OR selector and spaces.
			[
				[
					'fields' => [
						'Name' => 'Hello  World',
						'Scenario tags' => 'Or',
						'Refresh interval' => '30 seconds'
					]
				]
			],
			// #4 Show Maintenance and trimming applied.
			[
				[
					'fields' => [
						'Show header' => false,
						'Name' => '   Happy case   ',
						'Refresh interval' => '1 minute',
						'Show hosts in maintenance' => true,
						'Scenario tags' => 'And/Or'
					],
					'tags' => [
						[
							'name' => '  Trim THIS   ️',
							'operator' => 'Equals',
							'value' => ' Warning ⚠ ️'
						]
					],
					'trim' => ['Name', 'id:tags_0_tag', 'id:tags_0_value']
				]
			],
			// #5 Special symbols in name and no maintenance.
			[
				[
					'fields' => [
						'Name' => '🙂,кириллица, 2-4-8 bytes symbols, "],*,a[x=": "],*,a[x="/\|',
						'Refresh interval' => '2 minutes',
						'Show hosts in maintenance' => false
					]
				]
			],
			// #6 Host groups.
			[
				[
					'fields' => [
						'Host groups' => [
							'First Group for Web Monitoring check',
							'Second Group for Web Monitoring check'
						],
						'Refresh interval' => '10 minutes'
					]
				]
			],
			// #7 Host group and exclude host group.
			[
				[
					'fields' => [
						'Host groups' => 'First Group for Web Monitoring check',
						'Exclude host groups' => 'Second Group for Web Monitoring check',
						'Refresh interval' => '15 minutes'
					]
				]
			],
			// #8 Hosts.
			[
				[
					'fields' => [
						'Hosts' => [
							'First host for Web Monitoring widget',
							'Second host for Web Monitoring widget'
						],
						'Refresh interval' => 'Default (1 minute)'
					]
				]
			],
			// #9 Hosts and host groups.
			[
				[
					'fields' => [
						'Name' => STRING_255,
						'Show header' => true,
						'Host groups' => [
							'First Group for Web Monitoring check',
							'Second Group for Web Monitoring check'
						],
						'Hosts' => [
							'First host for Web Monitoring widget',
							'Second host for Web Monitoring widget'
						]
					]
				]
			],
			// #10 Tags.
			[
				[
					'fields' => [
						'Name' => 'Check tags table'
					],
					'tags' => [
						['name' => 'empty value', 'operator' => 'Equals', 'value' => ''],
						['name' => '', 'operator' => 'Does not contain', 'value' => 'empty tag'],
						['name' => 'Check host tag with operator - Equals ⚠️', 'operator' => 'Equals',
							'value' => 'Warning ⚠️'],
						['name' => 'Check host tag with operator - Exists', 'operator' => 'Exists'],
						['name' => 'Check host tag with operator - Contains ❌', 'operator' => 'Contains', 'value' =>
							'tag value ❌'],
						['name' => 'Check host tag with operator - Does not exist', 'operator' => 'Does not exist'],
						['name' => 'Check host tag with operator - Does not equal', 'operator' => 'Does not equal',
							'value' => 'Average'],
						['name' => 'Check host tag with operator - Does not contain', 'operator' => 'Does not contain',
							'value' => 'Disaster']
					]
				]
			]
		];
	}

	/**
	 * Check Web monitoring widget creation scenarios.
	 *
	 * @dataProvider getWidgetData
	 *
	 */
	public function testDashboardWebMonitoringWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardWebMonitoringWidget_Update($data) {
		$this->checkWidgetForm($data, true);
	}

	/**
	 * Perform Web Monitoring widget creation or update and verify the result.
	 *
	 * @param boolean $update	updating is performed
	 */
	protected function checkWidgetForm($data, $update = false) {
		$data['fields']['Name'] = ($data['fields'] === [])
			? ''
			: CTestArrayHelper::get($data, 'fields.Name', 'Web monitoring '.microtime());
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DASHBOARD_FOR_WIDGET_ACTIONS])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->getWidget(self::$update_widget)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Web monitoring')]);

		// Create tags.
		if (array_key_exists('tags', $data)) {
			$this->setTags($data['tags']);
		}

		$form->fill($data['fields']);
		$values = $form->getFields()->filter(CElementFilter::VISIBLE)->asValues();
		$form->submit();

		// Trim leading and trailing spaces from expected results if necessary.
		if (CTestArrayHelper::get($data, 'trim', false)) {
			$data = CTestArrayHelper::trim($data);
		}

		// If name is empty string it is replaced by default widget name "Web monitoring".
		$header = ($data['fields']['Name'] === '') ? 'Web monitoring' : $data['fields']['Name'];
		if ($update) {
			self::$update_widget = $header;
		}

		COverlayDialogElement::ensureNotPresent();
		$widget = $dashboard->getWidget($header);

		// Save Dashboard to ensure that widget is correctly saved.
		$dashboard->save()->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check widgets count.
		$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

		// Check new widget update interval.
		$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (1 minute)')
			? '1 minute'
			: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '1 minute'));
		$this->assertEquals($refresh, $widget->getRefreshInterval());
		CPopupMenuElement::find()->one()->close();

		// Check new widget form fields and values in frontend.
		$saved_form = $widget->edit();
		$this->assertEquals($values, $saved_form->getFields()->filter(CElementFilter::VISIBLE)->asValues());
		$saved_form->checkValue($data['fields']);

		if (array_key_exists('tags', $data)) {
			$this->assertTags($data['tags']);
		}

		// Close widget window and cancel editing the dashboard.
		COverlayDialogElement::find()->one()->close();
		$dashboard->cancelEditing();
	}

	/**
	 * Test opening Web monitoring form and saving with no changes made.
	 */
	public function testDashboardWebMonitoringWidget_SimpleUpdate() {
		$this->checkNoChanges();
	}

	public static function getCancelData() {
		return [
			// #0 Cancel creating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => true,
					'save_dashboard' => true
				]
			],
			// #1 Cancel updating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => false,
					'save_dashboard' => true
				]
			],
			// #2 Create widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => true,
					'save_dashboard' => false
				]
			],
			// #3 Update widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => false,
					'save_dashboard' => false
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testDashboardWebMonitoringWidget_Cancel($data) {
		$this->checkNoChanges($data['cancel_form'], $data['create_widget'], $data['save_dashboard']);
	}

	/**
	 * Function for checking cancelling form or submitting without any changes.
	 *
	 * @param boolean $cancel            true if cancel scenario, false if form is submitted
	 * @param boolean $create            true if create scenario, false if update
	 * @param boolean $save_dashboard    true if dashboard will be saved, false if not
	 */
	protected function checkNoChanges($cancel = false, $create = false, $save_dashboard = true) {
		$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DASHBOARD_FOR_WIDGET_ACTIONS])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->waitUntilReady();
		$old_widget_count = $dashboard->getWidgets()->count();
		$dashboard->edit();

		$form = $create
			? $dashboard->addWidget()->asForm()
			: $dashboard->getWidget(self::$update_widget)->edit();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if ($create) {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Web monitoring')]);
		}
		else {
			$values = $form->getValues();
		}

		if ($cancel || !$save_dashboard) {
			$form->fill(
				[
					'Name' => 'New name',
					'Refresh interval' => '10 minutes'
				]
			);
		}

		if ($cancel) {
			$dialog->query('button:Cancel')->one()->click();
		}
		else {
			$form->submit();
		}

		COverlayDialogElement::ensureNotPresent();

		if (!$cancel) {
			$dashboard->getWidget($save_dashboard ? self::$update_widget : 'New name')->waitUntilReady();
		}

		if ($save_dashboard) {
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
		else {
			$dashboard->cancelEditing();
		}

		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());

		// Check that updating widget form values did not change in frontend.
		if (!$create && !$save_dashboard) {
			$this->assertEquals($values, $dashboard->getWidget(self::$update_widget)->edit()->getValues());
			COverlayDialogElement::find()->one()->close();
		}

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Delete Web monitoring widget check.
	 */
	public function testDashboardWebMonitoringWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DASHBOARD_FOR_WIDGET_ACTIONS])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget('WebMonitoring for delete');
		$dashboard->deleteWidget('WebMonitoring for delete');
		$widget->waitUntilNotPresent();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard.
		$this->assertFalse($dashboard->getWidget('WebMonitoring for delete', false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM widget_field wf'.
				' LEFT JOIN widget w'.
					' ON w.widgetid=wf.widgetid'.
					' WHERE w.name='.zbx_dbstr('WebMonitoring for delete')
			)
		);
	}
}
