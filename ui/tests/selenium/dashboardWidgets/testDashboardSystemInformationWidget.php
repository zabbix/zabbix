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

require_once dirname(__FILE__).'/../common/testSystemInformation.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup dashboard, ha_node, config
 *
 * @backupConfig
 *
 * @onBefore prepareDashboardData
 */
class testDashboardSystemInformationWidget extends testSystemInformation {

	public static $dashboardid;				// Dashboard for checking widget content with enabled and disabled HA cluster.
	public static $widgets_dashboardid;		// Dashboard for checking creation and update of system information widgets.

	/**
	 * Attach MessageBehavior to the test.
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Function creates dashboards with widgets for test and defines the corresponding dashboard IDs.
	 */
	public static function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Sysinfo + HA test',
				'private' => 0,
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'systeminfo',
								'name' => 'System stats view',
								'width' => 36,
								'height' => 8
							],
							[
								'type' => 'systeminfo',
								'name' => 'High availability nodes view',
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 8,
								'fields' => [
									[
										'type' => 0,
										'name' => 'info_type',
										'value' => 1
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for SysInfo widget test',
				'private' => 0,
				'pages' => [
					[
						'name' => 'Page for creating widgets',
						'widgets' => []
					],
					[
						'name' => 'Page for updating widgets',
						'widgets' => [
							[
								'type' => 'systeminfo',
								'name' => 'System stats view',
								'width' => 36,
								'height' => 8
							],
							[
								'type' => 'systeminfo',
								'name' => 'HA nodes view',
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 8,
								'fields' => [
									[
										'type' => 0,
										'name' => 'info_type',
										'value' => 1
									]
								]
							]
						]
					]
				]
			]
		]);

		self::$dashboardid = $response['dashboardids'][0];
		self::$widgets_dashboardid = $response['dashboardids'][1];
	}

	public function testDashboardSystemInformationWidget_checkDisabledHA() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->waitUntilReady();
		// Remove zabbix version due to unstable screenshot which depends on column width with different version length.
		CElementQuery::getDriver()->executeScript("arguments[0].textContent = '';",
				[$this->query('xpath://table[@class="list-table sticky-header"]/tbody/tr[3]/td[1]')->one()]
		);
		$this->assertScreenshot($dashboard, 'widget_without_ha');
	}

	public function testDashboardSystemInformationWidget_Create() {
		$widgets = [
			[
				'fields' => [
					'Name' => 'Widget with default Show',
					'Refresh interval' => '2 minutes'
				]
			],
			[
				'fields' => ['Show' => 'High availability nodes']
			]
		];
		$this->executeWidgetAction($widgets, 'create');
	}

	public function testDashboardSystemInformationWidget_Update() {
		$widgets = [
			[
				'old_name' => 'System stats view',
				'fields' => [
					'Name' => 'Updated to HA nodes view',
					'Show' => 'High availability nodes',
					'Refresh interval' => '30 seconds'
				],
				'not_last' => true
			],
			[
				'old_name' => 'HA nodes view',
				'fields' => [
					'Name' => 'Updated to System Info view',
					'Show' => 'System stats',
					'Refresh interval' => '10 minutes'
				]
			]
		];
		$this->executeWidgetAction($widgets, 'update');
	}

// Commented until Jenkins issue investigated.
//	/**
//	 * @onBefore prepareHANodeData
//	 */
//	public function testDashboardSystemInformationWidget_checkEnabledHA() {
//		$this->assertEnabledHACluster(self::$dashboardid);
//		$this->assertScreenshotExcept(CDashboardElement::find()->one(), self::$skip_fields, 'widgets_with_ha');
//	}

	/**
	 * Function checks that Zabbix server status is updated after failover delay passes and frontend config is re-validated.
	 *
	 * @depends testDashboardSystemInformationWidget_checkEnabledHA
	 *
	 * @onBefore changeFailoverDelay
	 */
	public function testDashboardSystemInformationWidget_checkServerStatus() {
		$this->assertServerStatusAfterFailover(self::$dashboardid);
	}

	public function getUserData() {
		return [
			[
				[
					'user' => 'admin-zabbix',
					'password' => 'zabbix'
				]
			],
			[
				[
					'user' => 'user-zabbix',
					'password' => 'zabbix'
				]
			]
		];
	}

	/**
	 * Function checks that only super-admin users can view HA cluster data.
	 *
	 * @depends testDashboardSystemInformationWidget_checkServerStatus
	 *
	 * @dataProvider getUserData
	 */
	public function testDashboardSystemInformationWidget_checkHAPermissions($data) {
		$this->page->userLogin($data['user'], $data['password']);
		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$nodes_table = $dashboard->getWidget('High availability nodes view')->query('xpath:.//table')->asTable()->one();
		// No content of the widget in High availability nodes view should be visible to User and Admin user roles.
		$this->assertEquals('No permissions to referred object or it does not exist!', $nodes_table->getText());

		// HA cluster status should not be visible to User and Admin role users.
		$info_table = $dashboard->getWidget('System stats view')->asTable();
		$this->assertFalse($info_table->findRow('Parameter', 'High availability cluster')->isValid());
	}

	/**
	 * Function performs widget creation of update with the given widget parameters.
	 *
	 * @param array $widgets	widget related information
	 * @param string $action	operation to be performed with the widget
	 */
	private function executeWidgetAction($widgets, $action) {
		$page_name = ($action === 'update') ? 'Page for updating widgets' : 'Page for creating widgets';
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$widgets_dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->waitUntilReady()->edit();
		// Open the corresponding dashboard page in case of update.
		if ($action === 'update') {
			$this->query('xpath://span[@title='.zbx_dbstr($page_name).']')->one()->click();
		}

		// Execute the required operation for both widgets.
		foreach ($widgets as $widget_data) {
			if ($action === 'update') {
				$form = $dashboard->getWidget($widget_data['old_name'])->edit()->asForm();
			}
			else {
				$form = $dashboard->addWidget()->asForm();
				$form->fill(['Type' => CFormElement::RELOADABLE_FILL('System information')]);
			}
			$form->fill($widget_data['fields']);
			$form->submit();
		}
		// Save the dashboard and check info displayed by the widgets.
		$dashboard->save();
		if ($action === 'update') {
			$this->query('xpath://span[@title='.CXPathHelper::escapeQuotes($page_name).']')->waitUntilClickable()->one()->click();
		}
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$dashboard->waitUntilReady();

		// Remove zabbix version due to unstable screenshot which depends on column width with different version length.
		CElementQuery::getDriver()->executeScript("arguments[0].textContent = '';",
				[$this->query('xpath://table[@class="list-table sticky-header"]/tbody/tr[3]/td[1]')->one()]
		);
		$this->assertScreenshot(CDashboardElement::find()->one()->waitUntilReady(), $action.'_widgets');

		foreach ($widgets as $widget_data) {
			// Check widget refresh interval.
			$refresh_interval = CTestArrayHelper::get($widget_data['fields'], 'Refresh interval', '15 minutes');
			$widget = $dashboard->getWidget(CTestArrayHelper::get($widget_data['fields'], 'Name', 'System information'));
			$this->assertEquals($refresh_interval, $widget->getRefreshInterval());

			// Check that widget with the corresponding name is present in DB.
			$widget_sql = 'SELECT count(widgetid) FROM widget WHERE type='.zbx_dbstr('systeminfo').' AND dashboard_pageid IN'.
					' (SELECT dashboard_pageid from dashboard_page WHERE name='.zbx_dbstr($page_name).')'.
					' AND name='.zbx_dbstr(CTestArrayHelper::get($widget_data['fields'], 'Name', ''));
			$this->assertEquals('1', CDBHelper::getValue($widget_sql));

			// Check field values when opening widget config and exit edit mode.
			$field_values = [
				'Type' => 'System information',
				'Name' => '',
				'Show header' => true,
				'Refresh interval' => 'Default (15 minutes)',
				'Show' => 'System stats'
			];

			$form = $widget->edit()->asForm();
			$this->assertEquals(array_merge($field_values, $widget_data['fields']), $form->getFields()->asValues());
			$form->submit();
			$dashboard->cancelEditing();

			// Reopen the corresponding Dashboard page if more updated widgets need to be checked.
			if ($action === 'update' && CTestArrayHelper::get($widget_data, 'not_last')) {
				$this->query('xpath://span[@title='.zbx_dbstr($page_name).']')->waitUntilClickable()->one()->click();
			}
		}
	}
}
