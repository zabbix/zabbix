<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

	public static $dashboardid;				// Deshboard for checking widget content with enabled and disabled HA cluster.
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
								'width' => 12,
								'height' => 8
							],
							[
								'type' => 'systeminfo',
								'name' => 'High availability nodes view',
								'x' => 12,
								'y' => 0,
								'width' => 12,
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
								'width' => 12,
								'height' => 8
							],
							[
								'type' => 'systeminfo',
								'name' => 'HA nodes view',
								'x' => 12,
								'y' => 0,
								'width' => 12,
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
		$this->assertScreenshot(CDashboardElement::find()->waitUntilReady()->one(), 'widget_without_ha');
	}

	public function testDashboardSystemInformationWidget_Create() {
		$widgets = [
			[
				'fields' => ['Name' => 'Widget with default Show']
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
					'Show' => 'High availability nodes'
				]
			],
			[
				'old_name' => 'HA nodes view',
				'fields' => [
					'Name' => 'Updated to Sysem Info view',
					'Show' => 'System stats'
				]
			]
		];
		$this->executeWidgetAction($widgets, 'update');
	}

	/**
	 * @onBefore prepareHANodeData
	 */
	public function testDashboardSystemInformationWidget_checkEnabledHA() {
		$skip_fields = $this->checkEnabledHACluster(self::$dashboardid);
		$this->assertScreenshotExcept(CDashboardElement::find()->one(), $skip_fields, 'widgets_with_ha');
	}

	/**
	 * Function checks that zabbix server status is updated after failover delay passes and frontend config is re-validated.
	 *
	 * @depends testDashboardSystemInformationWidget_checkEnabledHA
	 *
	 * @onBefore changeFailoverDelay
	 */
	public function testDashboardSystemInformationWidget_checkServerStatus() {
		$this->checkServerStatusAfterFailover(self::$dashboardid);
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

		// HA cluster satus should not be visible to User and Admin role users.
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$widgets_dashboardid);
		$dashboard = CDashboardElement::find()->one()->waitUntilReady();
		$dashboard->edit();
		// Open the corresponding dashboard page in case of update.
		if ($action === 'update') {
			$this->query('xpath://span[@title="Page for updating widgets"]')->one()->click();
		}

		// Execute the required operation for both widgets.
		foreach ($widgets as $widget_data) {
			if ($action === 'update') {
				$form = $dashboard->getWidget($widget_data['old_name'])->edit()->asForm();
			}
			else {
				$form = $dashboard->addWidget()->asForm();
				$form->getField('Type')->fill('System information');
			}
			$form->fill($widget_data['fields']);
			$form->submit();
		}
		// Save the dashboard and check info displayed by the widgets.
		$dashboard->save();
		if ($action === 'update') {
			$this->query('xpath://span[@title="Page for updating widgets"]')->waitUntilClickable()->one()->click();
		}
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertScreenshot($dashboard, $action.'_widgets');
	}
}
