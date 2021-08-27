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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup role, module, users, report
 *
 * @onBefore prepareUserData, prepareReportData
 */
class testUserRolesPermissions extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Id of role that created for future role change for Super admin.
	 *
	 * @var integer
	 */
	protected static $super_roleid;

	/**
	 * Id of user that created for future checks.
	 *
	 * @var integer
	 */
	protected static $super_user;

	/**
	 * Id of created scheduled report.
	 *
	 * @var integer
	 */
	protected static $reportid;

	/**
	 * Function used to create user.
	 */
	public function prepareUserData() {
		$role = CDataHelper::call('role.create', [
			[
				'name' => 'super_role',
				'type' => 3
			]
		]);
		$this->assertArrayHasKey('roleids', $role);
		self::$super_roleid = $role['roleids'][0];

		$user = CDataHelper::call('user.create', [
			[
				'username' => 'user_for_role',
				'passwd' => 'zabbix',
				'roleid' => self::$super_roleid,
				'usrgrps' => [
					[
						'usrgrpid' => '7'
					]
				]
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$super_user = $user['userids'][0];
	}

	/**
	 * Scheduled report.
	 */
	public function prepareReportData() {
		$response = CDataHelper::call('report.create', [
			[
				'userid' => self::$super_user,
				'name' => 'test_report_for_role',
				'dashboardid' => '1',
				'users' => [
					[
						'userid' => self::$super_user,
						'exclude' => '0'
					]
				]
			]
		]);
		$this->assertArrayHasKey('reportids', $response);
		self::$reportid = $response['reportids'][0];
	}

	public static function getPageActionsData() {
		return [
			// Map creation/edit.
			[
				[
					'page_buttons' => [
						'Create map',
						'Import',
						'Delete'
					],
					'form_button' => [
						'Edit map'
					],
					'list_link' => 'sysmaps.php',
					'action_link' => 'zabbix.php?action=map.view&sysmapid=1',
					'action' => 'Create and edit maps',
					'check_links' => ['sysmap.php?sysmapid=1', 'sysmaps.php?form=Create+map']
				]
			],
			// Dashboard creation/edit.
			[
				[
					'page_buttons' => [
						'Create dashboard',
						'Delete'
					],
					'form_button' => [
						'Edit dashboard'
					],
					'list_link' => 'zabbix.php?action=dashboard.list',
					'action_link' => 'zabbix.php?action=dashboard.view&dashboardid=122',
					'action' => 'Create and edit dashboards',
					'check_links' => ['zabbix.php?action=dashboard.view&new=1']
				]
			],
			// Maintenance creation/edit.
			[
				[
					'maintenance' => true,
					'page_buttons' => [
						'Create maintenance period',
						'Delete'
					],
					'form_button' => [
						'Update',
						'Clone',
						'Delete',
						'Cancel'
					],
					'list_link' => 'maintenance.php',
					'action_link' => 'maintenance.php?form=update&maintenanceid=5',
					'action' => 'Create and edit maintenance',
					'check_links' => ['maintenance.php?form=create']
				]
			],
			// Manage scheduled reports.
			[
				[
					'report' => true,
					'page_buttons' => [
						'Create report',
						'Enable',
						'Disable',
						'Delete'
					],
					'form_button' => [
						'Update',
						'Clone',
						'Test',
						'Delete',
						'Cancel'
					],
					'list_link' => 'zabbix.php?action=scheduledreport.list',
					'action' => 'Manage scheduled reports',
					'check_links' => ['zabbix.php?action=scheduledreport.edit']
				]
			]
		];
	}

	/**
	 * Check creation/edit for dashboard, map, reports, maintenance.
	 *
	 * @dataProvider getPageActionsData
	 */
	public function testUserRolesPermissions_PageActions($data) {
		$this->page->userLogin('user_for_role', 'zabbix');

		foreach ([true, false] as $action_status) {
			$this->page->open($data['list_link'])->waitUntilReady();

			$this->query('class:list-table')->asTable()->waitUntilVisible()->one()->getRow(0)->select();
			foreach ($data['page_buttons'] as $button) {
				$this->assertTrue($this->query('button', $button)->one()->isEnabled($action_status));
			}

			$this->page->open(array_key_exists('report', $data) ? 'zabbix.php?action=scheduledreport.edit&reportid='.
					self::$reportid : $data['action_link'])->waitUntilReady();

			foreach ($data['form_button'] as $text) {
				$this->assertTrue($this->query('button', $text)->one()->isEnabled(($text === 'Cancel') ? true : $action_status));
			}

			if ($action_status) {
				$this->changeRoleRule([$data['action'] => false]);
			}
		}

		$this->checkLinks($data['check_links']);
	}

	public static function getProblemActionsData() {
		return [
			// Message.
			[
				[
					'activityid' => 'message',
					'action' => 'Add problem comments',
					'column' => 'Message',
					'value' => 'test_text'
				]
			],
			// Severity.
			[
				[
					'activityid' => 'change_severity',
					'action' => 'Change severity',
					'column' => 'Severity',
					'value' => 'Average'
				]
			],
			// Close problem.
			[
				[
					'activityid' => 'close_problem',
					'action' => 'Close problems',
					'column' => 'Status',
					'value' => 'CLOSING'
				]
			],
			// Acknowledge problem.
			[
				[
					'activityid' => 'acknowledge_problem',
					'action' => 'Acknowledge problems',
					'column' => 'Ack',
					'value' => 'Yes'
				]
			]
		];
	}

	/**
	 * Check problem actions.
	 *
	 * @backupOnce events
	 *
	 * @dataProvider getProblemActionsData
	 */
	public function testUserRolesPermissions_ProblemAction($data) {
		$this->page->userLogin('user_for_role', 'zabbix');

		foreach ([true, false] as $action_status) {
			$this->page->open('zabbix.php?action=problem.view')->waitUntilReady();
			$row = $this->query('class:list-table')->asTable()->one()->findRow('Problem', 'Test trigger with tag');
			$row->getColumn('Ack')->query('link:No')->waitUntilClickable()->one()->click();
			$dialog = COverlayDialogElement::find()->waitUntilVisible()->one();
			$this->assertTrue($dialog->query('id', $data['activityid'])->one()->isEnabled($action_status));
			$this->changeRoleRule([$data['action'] => !$action_status]);

			// Check that problem actions works after they were turned on.
			if ($action_status === false) {
				$this->page->open('zabbix.php?action=problem.view')->waitUntilReady();
				$row->getColumn('Ack')->query('link:No')->waitUntilCLickable()->one()->click();

				if ($data['activityid'] === 'message') {
					$dialog->query('id:message')->one()->fill('test_text');
					$dialog->query('button:Update')->one()->click();
					$this->page->waitUntilReady();
					$row->getColumn('Actions')->query('xpath:.//button[contains(@class, "icon-action-msgs")]')->one()->click();
					$message_hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last();
					$value = $message_hint->query('class:list-table')->asTable()->one()->getRow(0)->getColumn($data['column'])->getText();
					$this->assertEquals($data['value'], $value);
				}
				else {
					$dialog->query('id', $data['activityid'])->asCheckbox()->one()->check();

					if ($data['activityid'] === 'change_severity') {
						$dialog->query('id:severity')->asSegmentedRadio()->one()->fill('Average');
					}

					$dialog->query('button:Update')->one()->click();
					$this->page->waitUntilReady();
					$status = $row->getColumn($data['column'])->getText();
					$this->assertEquals($data['value'], $status);
				}
			}
		}
	}

	/**
	 * Check that Acknowledge link is disabled after all problem actions is disabled.
	 */
	public function testUserRolesPermissions_ProblemsActionsAll() {
		$problem = 'Test trigger with tag';
		$context_before = [
			'Problems',
			'Acknowledge',
			'Configuration',
			'Webhook url for all',
			'1_item'
		];
		$actions = [
			'Add problem comments' => false,
			'Change severity' => false,
			'Acknowledge problems' => false,
			'Close problems' => false
		];
		$this->page->userLogin('user_for_role', 'zabbix');

		foreach ([true, false] as $action_status) {
			// Problem page.
			$this->page->open('zabbix.php?action=problem.view')->waitUntilReady();
			$problem_row = $this->query('class:list-table')->asTable()->one()->findRow('Problem', $problem);
			$this->assertEquals($action_status, $problem_row->getColumn('Ack')->query('xpath:.//*[text()="No"]')
					->one()->isAttributePresent('onclick'));

			// Problem widget in dashboard.
			$this->page->open('zabbix.php?action=dashboard.view&dashboardid=1')->waitUntilReady();
			$table = CDashboardElement::find()->one()->getWidget('Problems')->query('class:list-table')->asTable()->one();
			$this->assertEquals($action_status, $table->findRow('Problem • Severity', $problem)->getColumn('Ack')
					->query('xpath:.//*[text()="No"]')->one()->isAttributePresent('onclick'));

			// Event details page.
			$this->page->open('tr_events.php?triggerid=99251&eventid=93')->waitUntilReady();

			foreach (['Event details', 'Event list [previous 20]'] as $table_name) {
				$table = $this->query('xpath://h4[text()='.CXPathHelper::escapeQuotes($table_name).']/../..//table')->asTable()->one();
				$this->assertEquals($action_status, $table->query('xpath:.//*[text()="No"]')
						->one()->isAttributePresent('onclick'));
			}

			// Overview page.
			$this->page->open('overview.php?type=0')->waitUntilReady();
			$this->query('class:list-table')->asTable()->one()->findRow('Triggers', '1_trigger_Disaster')
					->getColumn('1_Host_to_check_Monitoring_Overview')->query('xpath://td[@class="disaster-bg cursor-pointer"]')
					->one()->click();
			$this->page->waitUntilReady();

			$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
			if ($action_status) {
				$this->assertTrue($popup->hasItems($context_before));
				$this->changeRoleRule($actions);
			}
			else {
				$context_after = array_values(array_diff($context_before, ['Acknowledge']));
				$this->assertTrue($popup->hasItems($context_after));
			}
		}
	}

	public static function getScriptActionData() {
		return [
			// Monitoring problems page.
			[
				[
					'link' => 'zabbix.php?action=problem.view',
					'selector' => 'xpath:(//a[@class="link-action" and text()="ЗАББИКС Сервер"])[1]'
				]
			],
			// Dashboard problem widget.
			[
				[
					'link' => 'zabbix.php?action=dashboard.view&dashboardid=1',
					'selector' => 'link:ЗАББИКС Сервер'
				]
			],
			// Monitoring hosts page.
			[
				[
					'link' => 'zabbix.php?action=host.view',
					'selector' => 'link:3_Host_to_check_Monitoring_Overview'
				]
			],
			// Event detail page.
			[
				[
					'link' => 'tr_events.php?triggerid=99251&eventid=93',
					'selector' => 'xpath:(//*[@class="list-table"])[1]//*[text()="ЗАББИКС Сервер"]'
				]
			],
			// Monitoring maps page.
			[
				[
					'link' => 'zabbix.php?action=map.view&sysmapid=1',
					'selector' => 'xpath://*[name()="g"][@class="map-elements"]/*[name()="image"]'
				]
			]
		];
	}

	/**
	 * Check script actions.
	 *
	 * @dataProvider getScriptActionData
	 */
	public function testUserRolesPermissions_ScriptAction($data) {
		$context_before = [
			'Inventory',
			'Latest data',
			'Problems',
			'Graphs',
			'Dashboards',
			'Web',
			'Configuration',
			'Detect operating system',
			'Ping',
			'Script for Clone',
			'Script for Delete',
			'Script for Update',
			'Traceroute'
		];
		$context_after = [
			'Inventory',
			'Latest data',
			'Problems',
			'Graphs',
			'Dashboards',
			'Web',
			'Configuration'
		];
		$this->page->userLogin('user_for_role', 'zabbix');

		foreach ([true, false] as $action_status) {
			$this->page->open($data['link'])->waitUntilReady();
			$this->query($data['selector'])->waitUntilPresent()->one()->click();

			$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
			if ($action_status) {
				$this->assertTrue($popup->hasItems($context_before));
				$this->assertEquals(['HOST', 'SCRIPTS'], $popup->getTitles()->asText());
				$this->changeRoleRule(['Execute scripts' => false]);
			}
			else {
				$this->assertTrue($popup->hasItems($context_after));
				$this->assertEquals(['HOST'], $popup->getTitles()->asText());
				$this->changeRoleRule(['Execute scripts' => true]);
			}
		}
	}

	/**
	 * Module enable/disable.
	 */
	public function testUserRolesPermissions_Module() {
		$pages_before = [
			'Monitoring',
			'Inventory',
			'Reports',
			'Configuration',
			'Administration',
			'Module 5 menu'
		];
		$this->page->userLogin('user_for_role', 'zabbix');
		$this->page->open('zabbix.php?action=module.list')->waitUntilReady();
		$this->query('button:Scan directory')->one()->click();
		$this->query('class:list-table')->asTable()->one()->findRows('Name', '5th Module')->select();
		$this->query('button:Enable')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		foreach ([true, false] as $action_status) {
			$page_number = $this->query('xpath://ul[@class="menu-main"]/li/a')->count();

			for ($i = 1; $i <= $page_number; ++$i) {
				$all_pages[] = $this->query('xpath:(//ul[@class="menu-main"]/li/a)['.$i.']')->one()->getText();
			}

			if ($action_status) {
				$this->assertEquals($pages_before, $all_pages);
				$this->changeRoleRule(['5th Module' => false]);
				$all_pages = [];
			}
			else {
				$pages_after = array_values(array_diff($pages_before, ['Module 5 menu']));
				$this->assertEquals($pages_after, $all_pages);
			}
		}
	}

	public static function getUIData() {
		return [
			[
				[
					'section' => 'Inventory',
					'page' => 'Overview',
					'displayed_ui' => [
						'Hosts'
					],
					'link' => ['hostinventoriesoverview.php']
				]
			],
			[
				[
					'section' => 'Inventory',
					'page' => 'Hosts',
					'displayed_ui' => [
							'Overview'
					],
					'link' => ['hostinventories.php']
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Availability report',
					'displayed_ui' => [
						'Scheduled reports',
						'System information',
						'Triggers top 100',
						'Audit',
						'Action log',
						'Notifications'
					],
					'link' => ['report2.php']
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'System information',
					'displayed_ui' => [
						'Scheduled reports',
						'Availability report',
						'Triggers top 100',
						'Audit',
						'Action log',
						'Notifications'
					],
					'link' => ['zabbix.php?action=report.status']
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Availability report',
					'displayed_ui' => [
						'System information',
						'Scheduled reports',
						'Triggers top 100',
						'Audit',
						'Action log',
						'Notifications'
					],
					'link' => ['report2.php']
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Triggers top 100',
					'displayed_ui' => [
						'Availability report',
						'System information',
						'Scheduled reports',
						'Audit',
						'Action log',
						'Notifications'
					],
					'link' => ['toptriggers.php']
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Audit',
					'displayed_ui' => [
						'Availability report',
						'System information',
						'Scheduled reports',
						'Triggers top 100',
						'Action log',
						'Notifications'
					],
					'link' => ['zabbix.php?action=auditlog.list']
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Action log',
					'displayed_ui' => [
						'Availability report',
						'System information',
						'Scheduled reports',
						'Triggers top 100',
						'Audit',
						'Notifications'
					],
					'link' => ['auditacts.php']
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Notifications',
					'displayed_ui' => [
						'Availability report',
						'System information',
						'Scheduled reports',
						'Triggers top 100',
						'Audit',
						'Action log'
					],
					'link' => ['report4.php']
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Host groups',
					'displayed_ui' => [
						'Templates',
						'Hosts',
						'Maintenance',
						'Actions',
						'Event correlation',
						'Discovery',
						'Services'
					],
					'link' => ['hostgroups.php']
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Templates',
					'displayed_ui' => [
						'Host groups',
						'Hosts',
						'Maintenance',
						'Actions',
						'Event correlation',
						'Discovery',
						'Services'
					],
					'link' => ['templates.php']
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Hosts',
					'displayed_ui' => [
						'Host groups',
						'Templates',
						'Maintenance',
						'Actions',
						'Event correlation',
						'Discovery',
						'Services'
					],
					'link' => ['hosts.php']
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Maintenance',
					'displayed_ui' => [
						'Host groups',
						'Templates',
						'Hosts',
						'Actions',
						'Event correlation',
						'Discovery',
						'Services'
					],
					'link' => ['maintenance.php']
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Actions',
					'displayed_ui' => [
						'Host groups',
						'Templates',
						'Hosts',
						'Maintenance',
						'Event correlation',
						'Discovery',
						'Services'
					],
					'link' => [
						'actionconf.php?eventsource=0',
						'actionconf.php?eventsource=1',
						'actionconf.php?eventsource=2',
						'actionconf.php?eventsource=3'
					]
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Event correlation',
					'displayed_ui' => [
						'Host groups',
						'Templates',
						'Hosts',
						'Maintenance',
						'Actions',
						'Discovery',
						'Services'
					],
					'link' => ['zabbix.php?action=correlation.list']
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Discovery',
					'displayed_ui' => [
						'Host groups',
						'Templates',
						'Hosts',
						'Maintenance',
						'Actions',
						'Event correlation',
						'Services'
					],
					'link' => ['zabbix.php?action=discovery.list']
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Services',
					'displayed_ui' => [
						'Host groups',
						'Templates',
						'Hosts',
						'Maintenance',
						'Actions',
						'Event correlation',
						'Discovery'
					],
					'link' => ['services.php']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'General',
					'displayed_ui' => [
						'Proxies',
						'Authentication',
						'User groups',
						'User roles',
						'Users',
						'Media types',
						'Scripts',
						'Queue'
					],
					'link' => [
						'zabbix.php?action=gui.edit',
						'zabbix.php?action=autoreg.edit',
						'zabbix.php?action=housekeeping.edit',
						'zabbix.php?action=image.list',
						'zabbix.php?action=iconmap.list',
						'zabbix.php?action=regex.list',
						'zabbix.php?action=macros.edit',
						'zabbix.php?action=token.list',
						'zabbix.php?action=trigdisplay.edit',
						'zabbix.php?action=module.list',
						'zabbix.php?action=miscconfig.edit'
					]
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Proxies',
					'displayed_ui' => [
						'General',
						'Authentication',
						'User groups',
						'User roles',
						'Users',
						'Media types',
						'Scripts',
						'Queue'
					],
					'link' => ['zabbix.php?action=proxy.list']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Authentication',
					'displayed_ui' => [
						'General',
						'Proxies',
						'User groups',
						'User roles',
						'Users',
						'Media types',
						'Scripts',
						'Queue'
					],
					'link' => ['zabbix.php?action=authentication.edit']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'User groups',
					'displayed_ui' => [
						'General',
						'Proxies',
						'Authentication',
						'User roles',
						'Users',
						'Media types',
						'Scripts',
						'Queue'
					],
					'link' => ['zabbix.php?action=usergroup.list']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Users',
					'displayed_ui' => [
						'General',
						'Proxies',
						'Authentication',
						'User roles',
						'User groups',
						'Media types',
						'Scripts',
						'Queue'
					],
					'link' => ['zabbix.php?action=user.list']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Media types',
					'displayed_ui' => [
						'General',
						'Proxies',
						'Authentication',
						'User roles',
						'User groups',
						'Users',
						'Scripts',
						'Queue'
					],
					'link' => ['zabbix.php?action=mediatype.list']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Scripts',
					'displayed_ui' => [
						'General',
						'Proxies',
						'Authentication',
						'User roles',
						'User groups',
						'Users',
						'Media types',
						'Queue'
					],
					'link' => ['zabbix.php?action=script.list']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Queue',
					'displayed_ui' => [
						'General',
						'Proxies',
						'Authentication',
						'User roles',
						'User groups',
						'Users',
						'Media types',
						'Scripts'
					],
					'link' => [
						'zabbix.php?action=queue.overview',
						'zabbix.php?action=queue.overview.proxy',
						'zabbix.php?action=queue.details'
					]
				]
			],
			[
				[
					'section' => 'Administration',
					'user_roles' => true,
					'page' => 'User roles',
					'displayed_ui' => [
						'General',
						'Proxies',
						'Authentication',
						'Queue',
						'User groups',
						'Users',
						'Media types',
						'Scripts'
					],
					'link' => ['zabbix.php?action=userrole.list']
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Problems',
					'displayed_ui' => [
						'Dashboard',
						'Hosts',
						'Overview',
						'Latest data',
						'Maps',
						'Discovery',
						'Services'
					],
					'link' => ['zabbix.php?action=problem.view']
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Hosts',
					'displayed_ui' => [
						'Dashboard',
						'Problems',
						'Overview',
						'Latest data',
						'Maps',
						'Discovery',
						'Services'
					],
					'link' => ['zabbix.php?action=host.view']
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Overview',
					'displayed_ui' => [
						'Dashboard',
						'Problems',
						'Hosts',
						'Latest data',
						'Maps',
						'Discovery',
						'Services'
					],
					'link' => ['overview.php']
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Latest data',
					'displayed_ui' => [
						'Dashboard',
						'Problems',
						'Hosts',
						'Overview',
						'Maps',
						'Discovery',
						'Services'
					],
					'link' => ['zabbix.php?action=latest.view']
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Maps',
					'displayed_ui' => [
						'Dashboard',
						'Problems',
						'Hosts',
						'Overview',
						'Latest data',
						'Discovery',
						'Services'
					],
					'link' => ['sysmaps.php']
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Discovery',
					'displayed_ui' => [
						'Dashboard',
						'Problems',
						'Hosts',
						'Overview',
						'Latest data',
						'Maps',
						'Services'
					],
					'link' => ['zabbix.php?action=discovery.view']
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Services',
					'displayed_ui' => [
						'Dashboard',
						'Problems',
						'Hosts',
						'Overview',
						'Latest data',
						'Maps',
						'Discovery'
					],
					'link' => ['srv_status.php']
				]
			]
		];
	}

	/**
	 * UI permission
	 *
	 * @dataProvider getUIData
	 */
	public function testUserRolesPermissions_UI($data) {
		$user_roles = [
			'Administration' => [
				'General',
				'Proxies',
				'Authentication',
				'User roles',
				'User groups',
				'Users',
				'Media types',
				'Scripts'
			]
		];
		$this->page->userLogin('user_for_role', 'zabbix');

		foreach ([true, false] as $action_status) {
			$menu = CMainMenuElement::find()->one();
			if ($data['section'] !== 'Monitoring') {
				$menu->select($data['section']);
			}

			$this->assertEquals($action_status, $menu->exists($data['page']));

			if ($action_status) {
				if (array_key_exists('user_roles', $data)) {
					$this->signOut();
					$this->page->userLogin('Admin', 'zabbix');
					$this->changeRoleRule([$data['section'] => $data['displayed_ui']]);
					$this->signOut();
					$this->page->userLogin('user_for_role', 'zabbix');
				}
				else {
					$this->changeRoleRule([$data['section'] => $data['displayed_ui']]);
					$this->page->open('zabbix.php?action=dashboard.view')->waitUntilReady();
				}
			}
			else {
				if (array_key_exists('user_roles', $data)) {
					$this->checkLinks($data['link']);
					$this->signOut();
					$this->page->userLogin('Admin', 'zabbix');
					$this->changeRoleRule($user_roles);
					$this->signOut();
				}
				else {
					$this->checkLinks($data['link']);
					$this->signOut();
				}
			}
		}
	}

	public static function getDashboardData() {
		return [
			[
				[
					'page' => 'Dashboard',
					'button' => 'Problems',
					'displayed_ui' => [
						'Problems',
						'Hosts',
						'Overview',
						'Latest data',
						'Maps',
						'Discovery',
						'Services'
					]
				]
			],
			[
				[
					'button' => 'Hosts',
					'displayed_ui' => [
						'Hosts',
						'Overview',
						'Latest data',
						'Maps',
						'Discovery',
						'Services'
					]
				]
			]
//			TODO: uncomment after ZBX-19479 fix.
//			[
//				[
//					'button' => 'Overview',
//					'displayed_ui' => [
//						'Overview',
//						'Latest data',
//						'Maps',
//						'Discovery',
//						'Services'
//					]
//				]
//			]
		];
	}

	/**
	 * Disabling access to Dashboard. Check warning message text and button.
	 *
	 * @dataProvider getDashboardData
	 */
	public function testUserRolesPermissions_Dashboard($data) {
		$this->page->userLogin('user_for_role', 'zabbix');

		foreach ([true, false] as $action_status) {
			$main_section = $this->query('xpath://ul[@class="menu-main"]')->query('link:Monitoring');

			if (array_key_exists('page', $data)) {
				$this->assertEquals($action_status, $main_section->one()->parents('tag:li')->query('link', $data['page'])->exists());
			}

			if ($action_status) {
				$this->changeRoleRule(['Monitoring' => $data['displayed_ui']]);
			}
			else {
				$this->checkLinks(['zabbix.php?action=dashboard.view'], $data['button']);
				$this->changeRoleRule(['Monitoring' => ['Dashboard', 'Problems', 'Hosts', 'Overview', 'Latest data', 'Maps',
						'Discovery', 'Services']]
				);
			}
		}
	}

	/**
	 * Manage API token action check.
	 */
	public function testUserRolesPermissions_ManageApiToken() {
		$this->page->userLogin('user_for_role', 'zabbix');
		$this->page->open('zabbix.php?action=user.token.list')->waitUntilReady();
		$this->assertEquals('TEST_SERVER_NAME: API tokens', $this->page->getTitle());
		$this->changeRoleRule(['Manage API tokens' => false]);
		$this->checkLinks(['zabbix.php?action=user.token.list']);
	}

	/**
	 * Check disabled actions with links.
	 *
	 * @param array $links		checked links after disabling action
	 * @param string $page		page name displayed on error message button
	 */
	private function checkLinks($links, $page = 'Dashboard') {
		foreach ($links as $link) {
			$this->page->open($link)->waitUntilReady();
			$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "user_for_role". '.
					'You have no permissions to access this page.');
			$this->query('button:Go to "'.$page.'"')->one()->waitUntilClickable()->click();

			if ($page === 'Dashboard') {
				$this->assertContains('zabbix.php?action=dashboard', $this->page->getCurrentUrl());
			}
		}
	}

	/**
	 * Enable/disable actions and UI.
	 *
	 * @param array $action		action with true/false status or UI section with page
	 */
	private function changeRoleRule($action) {
		$this->page->open('zabbix.php?action=userrole.edit&roleid='.self::$super_roleid)->waitUntilReady();
		$this->query('id:userrole-form')->waitUntilPresent()->asFluidForm()->one()->fill($action)->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'User role updated');
	}

	/**
	 * Click Sign out button.
	 */
	private function signOut() {
		$this->query('xpath://a[@class="icon-signout"]')->waitUntilPresent()->one()->click();
		$this->page->waitUntilReady();
		$this->query('button:Sign in')->waitUntilVisible();
	}
}
