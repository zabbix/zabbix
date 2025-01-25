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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

use Facebook\WebDriver\WebDriverKeys;

/**
 * @backup role, module, users, report, services
 * @dataSource ExecuteNowAction
 * @onBefore prepareUserData, prepareReportData, prepareServiceData, prepareCauseAndSymptomData
 */
class testUserRolesPermissions extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	/**
	 * Id of role that created for future role change for Super admin.
	 *
	 * @var integer
	 */
	protected static $super_roleid;
	protected static $super_roleid2;

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
				'type' => 3,
				'rules' => [
					'services.write.mode' => 1
				]
			],
			[
				'name' => 'super_role_for_problem_ranking',
				'type' => 3
			]
		]);
		$this->assertArrayHasKey('roleids', $role);
		self::$super_roleid = $role['roleids'][0];
		self::$super_roleid2 = $role['roleids'][1];

		$user = CDataHelper::call('user.create', [
			[
				'username' => 'user_for_role',
				'passwd' => 'zabbixzabbix',
				'roleid' => self::$super_roleid,
				'usrgrps' => [
					[
						'usrgrpid' => '7'
					]
				]
			],
			[
				'username' => 'problem_ranking',
				'passwd' => 'zabbixzabbix',
				'roleid' => self::$super_roleid2,
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

	public function prepareServiceData() {
		// Remove all unnecessary services before proceeding with execution.
		DBExecute('DELETE FROM services');

		// Create services for Service permission checks.
		CDataHelper::call('service.create', [
			[
				'name' => 'Parent 1',
				'algorithm' => 1,
				'sortorder' => 1
			],
			[
				'name' => 'Parent 2',
				'algorithm' => 2,
				'sortorder' => 2
			],
			[
				'name' => 'Child of parent 1',
				'algorithm' => 2,
				'sortorder' => 1,
				'tags' => [
					[
						'tag' => 'test',
						'value' => 'test123'
					]
				]
			],
			[
				'name' => 'Child of child 1',
				'algorithm' => 2,
				'sortorder' => 1
			],
			[
				'name' => 'Child of parent 2',
				'algorithm' => 2,
				'sortorder' => 1
			]
		]);

		$services = CDataHelper::getIds('name');

		CDataHelper::call('service.update', [
			[
				'serviceid' =>  $services['Child of parent 1'],
				'parents' => [
					[
						'serviceid' => $services['Parent 1']
					]
				],
				'children' => [
					[
						'serviceid' => $services['Child of child 1']
					]
				]
			],
			[
				'serviceid' => $services['Child of parent 2'],
				'parents' => [
					[
						'serviceid' => $services['Parent 2']
					]
				]
			]
		]);
	}

	public function prepareCauseAndSymptomData() {
		// Create host group for host.
		CDataHelper::call('hostgroup.create', [
			['name' => 'Group for ChangeProblemRanking access check']
		]);
		$groupids = CDataHelper::getIds('name');

		// Create host and trapper item.
		CDataHelper::createHosts([
			[
				'host' => 'Host for ChangeProblemRanking access check',
				'groups' => [
					'groupid' => $groupids['Group for ChangeProblemRanking access check']
				],
				'items' => [
					[
						'name' => 'Consumed energy',
						'key_' => 'kWh',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			]
		]);

		// Create triggers.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'Problem trap>10 [Symptom]',
				'expression' => 'last(/Host for ChangeProblemRanking access check/kWh)>10',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Problem trap>150 [Cause]',
				'expression' => 'last(/Host for ChangeProblemRanking access check/kWh)>150',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			]
		]);

		// Create problems.
		CDBHelper::setTriggerProblem(['Problem trap>10 [Symptom]', 'Problem trap>150 [Cause]']);

		// Set cause and symptom(s) for predefined problems.
		foreach (['Problem trap>150 [Cause]' => ['Problem trap>10 [Symptom]']] as $cause => $symptoms) {
			$causeid = CDBHelper::getValue('SELECT eventid FROM problem WHERE name='.zbx_dbstr($cause));

			foreach ($symptoms as $symptom) {
				$symptomid = CDBHelper::getValue('SELECT eventid FROM problem WHERE name='.zbx_dbstr($symptom));
				DBexecute('UPDATE problem SET cause_eventid='.$causeid.' WHERE name='.zbx_dbstr($symptom));
				DBexecute('INSERT INTO event_symptom (eventid, cause_eventid) VALUES ('.$symptomid.','.$causeid.')');
				DBexecute('UPDATE event_symptom SET cause_eventid='.$causeid.' WHERE eventid='.$symptomid);
			}
		}
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
					'action_link' => 'zabbix.php?action=dashboard.view&dashboardid=1220',
					'action' => 'Create and edit dashboards',
					'check_links' => ['zabbix.php?action=dashboard.view&new=1']
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
	 * Check creation/edit for dashboard, map, reports.
	 *
	 * @dataProvider getPageActionsData
	 */
	public function testUserRolesPermissions_PageActions($data) {
		$this->page->userLogin('user_for_role', 'zabbixzabbix');

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

	/**
	 * Check creation/edit for maintenance.
	 */
	public function testUserRolesPermissions_MaintenanceActions() {
		$form_button = [ 'Update', 'Clone', 'Delete', 'Cancel'];
		$headers = ['', 'Name', 'Type', 'Active since', 'Active till', 'State', 'Description'];
		$this->page->userLogin('user_for_role', 'zabbixzabbix');

		foreach ([true, false] as $action_status) {
			$this->page->open('zabbix.php?action=maintenance.list')->waitUntilReady();
			$this->assertTrue($this->query('button', 'Create maintenance period')->one()->isEnabled($action_status));

			$table = $this->query('class:list-table')->asTable()->waitUntilVisible()->one();
			if ($action_status) {
				$table->getRow(0)->select();
				$this->assertTrue($this->query('button', 'Delete')->one()->isEnabled());
			}
			else {
				// Checkboxes and the Delete button are not visible.
				$this->assertFalse($this->query('button', 'Delete')->one(false)->isValid());
				$this->assertFalse($this->query('id:maintenanceids_1')->one(false)->isValid());
				array_shift($headers);
			}
			$this->assertEquals($headers, $table->getHeadersText());

			$table->getRow(0)->getColumn('Name')->query('tag:a')->one()->click();
			$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
			foreach ($form_button as $text) {
				$this->assertTrue($dialog->getFooter()->query('button', $text)->one()->isEnabled(($text === 'Cancel') ? true : $action_status));
			}
			$dialog->close();

			if ($action_status) {
				$this->changeRoleRule(['Create and edit maintenance' => false]);
			}
		}
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
					'column' => 'Update',
					'value' => 'Update'
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
		$this->page->userLogin('user_for_role', 'zabbixzabbix');

		foreach ([true, false] as $action_status) {
			$this->page->open('zabbix.php?action=problem.view')->waitUntilReady();
			$row = $this->query('class:list-table')->asTable()->one()->findRow('Problem', 'Test trigger with tag');
			$row->getColumn('Update')->query('link:Update')->waitUntilClickable()->one()->click();
			$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
			$this->assertTrue($dialog->query('id', $data['activityid'])->one()->isEnabled($action_status));
			$this->changeRoleRule([$data['action'] => !$action_status]);

			// Check that problem actions works after they were turned on.
			if ($action_status === false) {
				$this->page->open('zabbix.php?action=problem.view')->waitUntilReady();
				$row->getColumn('Update')->query('link:Update')->waitUntilCLickable()->one()->click();
				COverlayDialogElement::find()->waitUntilReady()->one();

				if ($data['activityid'] === 'message') {
					$dialog->query('id:message')->one()->fill('test_text');
					$dialog->query('button:Update')->one()->click();
					$dialog->ensureNotPresent();
					$this->page->waitUntilReady();
					$row->getColumn('Actions')->query("xpath:.//button[".
							CXPathHelper::fromClass('zi-alert-with-content')."]")->one()->click();
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

	public static function getCauseAndSymptomData() {
		return [
			// User role flag 'Change problem ranking' => false.
			[
				[
					'state' => false
				]
			],
			// User role flag 'Change problem ranking' => true.
			[
				[
					'state' => true
				]
			]
		];
	}

	/**
	 * Check cause and symptom related options when 'Change problem ranking' flag is disabled/enabled on 'User roles' page.
	 *
	 * @dataProvider getCauseAndSymptomData
	 */
	public function testUserRolesPermissions_ChangeProblemRanking($data) {
		$this->page->userLogin('problem_ranking', 'zabbixzabbix');
		$this->changeRoleRule(['Change problem ranking' => $data['state']], self::$super_roleid2);
		$this->page->open('zabbix.php?action=problem.view&name=Problem trap>150 [Cause]');

		// Check context menu 'Mark as cause' & 'Mark selected as symptoms' options accessibility.
		$table = $this->getTable();
		$table->query('link', 'Problem trap>150 [Cause]')->waitUntilVisible()->one()->click();
		$context_menu = CPopupMenuElement::find()->waitUntilVisible()->one();

		if ($data['state'] === false) {
			$this->assertFalse($context_menu->hasItems(['Mark as cause', 'Mark selected as symptoms']));
			$this->assertFalse($context_menu->hasTitles(['PROBLEM']));
		}
		else {
			$this->assertTrue($context_menu->hasItems(['Mark as cause', 'Mark selected as symptoms']));
			$this->assertTrue($context_menu->hasTitles(['PROBLEM']));
		}

		// Check 'Convert to cause' checkbox state for symptom event.
		$context_menu->close();
		$table->findRow('Problem', 'Problem trap>150 [Cause]')->query('xpath:.//button[@title="Expand"]')->one()->click();
		$table->findRow('Problem', 'Problem trap>10 [Symptom]')->query('link:Update')->waitUntilClickable()->one()->click();
		$this->assertTrue(COverlayDialogElement::find()->waitUntilReady()->one()->asForm()
				->getField('Convert to cause')->isEnabled($data['state'])
		);
		COverlayDialogElement::closeAll();

		// Check 'Convert to cause' checkbox state via mass update form.
		$this->selectTableRows();
		$this->query('button:Mass update')->waitUntilClickable()->one()->click();
		$this->assertTrue(COverlayDialogElement::find()->waitUntilReady()->one()->asForm()
				->getField('Convert to cause')->isEnabled($data['state'])
		);
		COverlayDialogElement::closeAll();
	}

	/**
	 * Check that Acknowledge link is disabled after all problem actions is disabled.
	 */
	public function testUserRolesPermissions_ProblemsActionsAll() {
		$problem = 'Test trigger with tag';
		$actions = [
			'Add problem comments' => false,
			'Change severity' => false,
			'Acknowledge problems' => false,
			'Suppress problems' => false,
			'Close problems' => false,
			'Change problem ranking' => false
		];
		$this->page->userLogin('user_for_role', 'zabbixzabbix');

		foreach ([true, false] as $action_status) {
			// Problem page.
			$this->page->open('zabbix.php?action=problem.view')->waitUntilReady();
			$problem_row = $this->query('class:list-table')->asTable()->one()->findRow('Problem', $problem);
			$this->assertEquals($action_status, $problem_row->getColumn('Update')->query('xpath:.//*[text()="Update"]')
					->one()->isAttributePresent('href'));

			// Problem widget in dashboard.
			$this->page->open('zabbix.php?action=dashboard.view&dashboardid=1')->waitUntilReady();
			$table = CDashboardElement::find()->one()->getWidget('Current problems')->query('class:list-table')->asTable()->one();
			$this->assertEquals($action_status, $table->findRow('Problem • Severity', $problem)->getColumn('Update')
					->query('xpath:.//*[text()="Update"]')->one()->isAttributePresent('href'));

			// Event details page.
			$this->page->open('tr_events.php?triggerid=99251&eventid=93')->waitUntilReady();

			$table = $this->query('xpath://h4[text()='.CXPathHelper::escapeQuotes('Event list [previous 20]').
					']/../..//table')->asTable()->one();
			$this->assertEquals($action_status, $table->query('xpath:(.//*[text()="Update"])[2]')
					->one()->isAttributePresent('href'));

			if ($action_status) {
				$this->changeRoleRule($actions);
			}
		}
	}

	public static function getScriptActionData() {
		return [
			// Monitoring problems page.
			[
				[
					'link' => 'zabbix.php?action=problem.view',
					'selector' => 'xpath:(//a[@class="link-action wordbreak" and text()="ЗАББИКС Сервер"])[1]'
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
			'Dashboards',
			'Problems',
			'Latest data',
			'Graphs',
			'Web',
			'Inventory',
			'Host',
			'Items',
			'Triggers',
			'Discovery',
			'Web',
			'Detect operating system',
			'Ping',
			'Traceroute'
		];
		$context_after = [
			'Dashboards',
			'Problems',
			'Latest data',
			'Graphs',
			'Web',
			'Inventory',
			'Host',
			'Items',
			'Triggers',
			'Discovery',
			'Web'
		];
		$this->page->userLogin('user_for_role', 'zabbixzabbix');

		foreach ([true, false] as $action_status) {
			$this->page->open($data['link'])->waitUntilReady();
			$this->query($data['selector'])->waitUntilPresent()->one()->click();

			$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
			if ($action_status) {
				$this->assertTrue($popup->hasItems($context_before));
				$this->assertEquals(['VIEW', 'CONFIGURATION', 'SCRIPTS'], $popup->getTitles()->asText());
				$this->changeRoleRule(['Execute scripts' => false]);
			}
			else {
				$this->assertTrue($popup->hasItems($context_after));
				$this->assertEquals(['VIEW', 'CONFIGURATION'], $popup->getTitles()->asText());
				$this->changeRoleRule(['Execute scripts' => true]);
			}
		}
	}

	/**
	 * Module enable/disable.
	 */
	public function testUserRolesPermissions_Module() {
		$pages_before = [
			'Dashboards',
			'Monitoring',
			'Services',
			'Inventory',
			'Reports',
			'Data collection',
			'Alerts',
			'Users',
			'Administration',
			'Module 5 menu'
		];
		$this->page->userLogin('user_for_role', 'zabbixzabbix');
		$this->page->open('zabbix.php?action=module.list')->waitUntilReady();
		$this->query('button:Scan directory')->one()->click();
		$this->assertMessage(TEST_GOOD, 'Modules updated');
		CMessageElement::find()->one()->close();
		$this->query('class:list-table')->asTable()->one()->findRows('Name', '5th Module')->select();
		$this->query('button:Enable')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Module enabled');

		foreach ([true, false] as $action_status) {
			$page_number = $this->query('xpath://ul[@class="menu-main"]/li/a')->count();
			$all_pages = [];

			for ($i = 1; $i <= $page_number; ++$i) {
				$all_pages[] = $this->query('xpath:(//ul[@class="menu-main"]/li/a)['.$i.']')->one()->getText();
			}

			if ($action_status) {
				$this->assertEquals($pages_before, $all_pages);
				$this->changeRoleRule(['5th Module' => false]);
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
						'Top 100 triggers',
						'Audit log',
						'Action log',
						'Notifications'
					],
					'link' => ['zabbix.php?action=availabilityreport.list']
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'System information',
					'displayed_ui' => [
						'Scheduled reports',
						'Availability report',
						'Top 100 triggers',
						'Audit log',
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
						'Top 100 triggers',
						'Audit log',
						'Action log',
						'Notifications'
					],
					'link' => ['zabbix.php?action=availabilityreport.list']
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Top 100 triggers',
					'displayed_ui' => [
						'Availability report',
						'System information',
						'Scheduled reports',
						'Audit log',
						'Action log',
						'Notifications'
					],
					'link' => ['zabbix.php?action=toptriggers.list']
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Audit log',
					'displayed_ui' => [
						'Availability report',
						'System information',
						'Scheduled reports',
						'Top 100 triggers',
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
						'Top 100 triggers',
						'Audit log',
						'Notifications'
					],
					'link' => ['zabbix.php?action=actionlog.list']
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
						'Top 100 triggers',
						'Audit log',
						'Action log'
					],
					'link' => ['report4.php']
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Template groups',
					'displayed_ui' => [
						'Host groups',
						'Templates',
						'Hosts',
						'Maintenance',
						'Event correlation',
						'Discovery'
					],
					'link' => ['zabbix.php?action=templategroup.list']
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Host groups',
					'displayed_ui' => [
						'Template groups',
						'Templates',
						'Hosts',
						'Maintenance',
						'Event correlation',
						'Discovery'
					],
					'link' => ['zabbix.php?action=hostgroup.list']
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Templates',
					'displayed_ui' => [
						'Template groups',
						'Host groups',
						'Hosts',
						'Maintenance',
						'Event correlation',
						'Discovery'
					],
					'link' => ['zabbix.php?action=template.list']
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Hosts',
					'displayed_ui' => [
						'Template groups',
						'Host groups',
						'Templates',
						'Maintenance',
						'Event correlation',
						'Discovery'
					],
					'link' => ['zabbix.php?action=host.list']
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Maintenance',
					'displayed_ui' => [
						'Template groups',
						'Host groups',
						'Templates',
						'Hosts',
						'Event correlation',
						'Discovery'
					],
					'link' => ['zabbix.php?action=maintenance.list']
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Event correlation',
					'displayed_ui' => [
						'Template groups',
						'Host groups',
						'Templates',
						'Hosts',
						'Maintenance',
						'Discovery'
					],
					'link' => ['zabbix.php?action=correlation.list']
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Discovery',
					'displayed_ui' => [
						'Template groups',
						'Host groups',
						'Templates',
						'Hosts',
						'Maintenance',
						'Event correlation'
					],
					'link' => ['zabbix.php?action=discovery.list']
				]
			],
			[
				[
					'section' => 'Alerts',
					'page' => 'Trigger actions',
					'actions' => true,
					'displayed_ui' => [
						'Service actions',
						'Discovery actions',
						'Autoregistration actions',
						'Internal actions',
						'Media types',
						'Scripts'
					],
					'link' => ['zabbix.php?action=action.list&eventsource=0']
				]
			],
			[
				[
					'section' => 'Alerts',
					'page' => 'Service actions',
					'actions' => true,
					'displayed_ui' => [
						'Trigger actions',
						'Discovery actions',
						'Autoregistration actions',
						'Internal actions',
						'Media types',
						'Scripts'
					],
					'link' => ['zabbix.php?action=action.list&eventsource=4']
				]
			],
			[
				[
					'section' => 'Alerts',
					'page' => 'Discovery actions',
					'actions' => true,
					'displayed_ui' => [
						'Trigger actions',
						'Service actions',
						'Autoregistration actions',
						'Internal actions',
						'Media types',
						'Scripts'
					],
					'link' => ['zabbix.php?action=action.list&eventsource=1']
				]
			],
			[
				[
					'section' => 'Alerts',
					'page' => 'Autoregistration actions',
					'actions' => true,
					'displayed_ui' => [
						'Trigger actions',
						'Service actions',
						'Discovery actions',
						'Internal actions',
						'Media types',
						'Scripts'
					],
					'link' => ['zabbix.php?action=action.list&eventsource=2']
				]
			],
			[
				[
					'section' => 'Alerts',
					'page' => 'Internal actions',
					'actions' => true,
					'displayed_ui' => [
						'Trigger actions',
						'Service actions',
						'Discovery actions',
						'Autoregistration actions',
						'Media types',
						'Scripts'
					],
					'link' => ['zabbix.php?action=action.list&eventsource=3']
				]
			],
			[
				[
					'section' => 'Alerts',
					'page' => 'Actions',
					'displayed_ui' => [
						'Media types',
						'Scripts'
					],
					'link' => [
						'zabbix.php?action=action.list&eventsource=0',
						'zabbix.php?action=action.list&eventsource=1',
						'zabbix.php?action=action.list&eventsource=2',
						'zabbix.php?action=action.list&eventsource=3',
						'zabbix.php?action=action.list&eventsource=4'
					]
				]
			],
			[
				[
					'section' => 'Alerts',
					'page' => 'Media types',
					'displayed_ui' => [
						'Trigger actions',
						'Service actions',
						'Discovery actions',
						'Autoregistration actions',
						'Internal actions',
						'Scripts'
					],
					'link' => ['zabbix.php?action=mediatype.list']
				]
			],
			[
				[
					'section' => 'Alerts',
					'page' => 'Scripts',
					'displayed_ui' => [
						'Trigger actions',
						'Service actions',
						'Discovery actions',
						'Autoregistration actions',
						'Internal actions',
						'Media types'
					],
					'link' => ['zabbix.php?action=script.list']
				]
			],
			[
				[
					'section' => 'Users',
					'page' => 'User groups',
					'displayed_ui' => [
						'User roles',
						'Users',
						'API tokens',
						'Authentication'
					],
					'link' => ['zabbix.php?action=usergroup.list']
				]
			],
			[
				[
					'section' => 'Users',
					'user_roles' => true,
					'page' => 'User roles',
					'displayed_ui' => [
						'User groups',
						'Users',
						'API tokens',
						'Authentication'
					],
					'link' => ['zabbix.php?action=userrole.list']
				]
			],
			[
				[
					'section' => 'Users',
					'page' => 'Users',
					'displayed_ui' => [
						'User groups',
						'User roles',
						'API tokens',
						'Authentication'
					],
					'link' => ['zabbix.php?action=user.list']
				]
			],
			[
				[
					'section' => 'Users',
					'page' => 'API tokens',
					'displayed_ui' => [
						'User groups',
						'User roles',
						'Users',
						'Authentication'
					],
					'link' => ['zabbix.php?action=token.list']
				]
			],
			[
				[
					'section' => 'Users',
					'page' => 'Authentication',
					'displayed_ui' => [
						'User groups',
						'User roles',
						'Users',
						'API tokens'
					],
					'link' => ['zabbix.php?action=authentication.edit']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'General',
					'displayed_ui' => [
						'Audit log',
						'Housekeeping',
						'Proxies',
						'Macros',
						'Queue'
					],
					'link' => [
						'zabbix.php?action=gui.edit',
						'zabbix.php?action=autoreg.edit',
						'zabbix.php?action=image.list',
						'zabbix.php?action=iconmap.list',
						'zabbix.php?action=regex.list',
						'zabbix.php?action=trigdisplay.edit',
						'zabbix.php?action=geomaps.edit',
						'zabbix.php?action=module.list',
						'zabbix.php?action=miscconfig.edit'
					]
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Audit log',
					'displayed_ui' => [
						'General',
						'Proxies',
						'Housekeeping',
						'Macros',
						'Queue'
					],
					'link' => ['zabbix.php?action=audit.settings.edit']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Housekeeping',
					'displayed_ui' => [
						'General',
						'Audit log',
						'Proxies',
						'Macros',
						'Queue'
					],
					'link' => ['zabbix.php?action=housekeeping.edit']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Proxies',
					'displayed_ui' => [
						'General',
						'Audit log',
						'Housekeeping',
						'Macros',
						'Queue'
					],
					'link' => ['zabbix.php?action=proxy.list']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Macros',
					'displayed_ui' => [
						'General',
						'Audit log',
						'Housekeeping',
						'Proxies',
						'Queue'
					],
					'link' => ['zabbix.php?action=macros.edit']
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Queue',
					'displayed_ui' => [
						'General',
						'Audit log',
						'Housekeeping',
						'Proxies',
						'Macros'
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
					'section' => 'Monitoring',
					'page' => 'Problems',
					'displayed_ui' => [
						'Hosts',
						'Latest data',
						'Maps',
						'Discovery'
					],
					'link' => ['zabbix.php?action=problem.view']
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Hosts',
					'displayed_ui' => [
						'Problems',
						'Latest data',
						'Maps',
						'Discovery'
					],
					'link' => ['zabbix.php?action=host.view']
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Latest data',
					'displayed_ui' => [
						'Problems',
						'Hosts',
						'Maps',
						'Discovery'
					],
					'link' => ['zabbix.php?action=latest.view']
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Maps',
					'displayed_ui' => [
						'Problems',
						'Hosts',
						'Latest data',
						'Discovery'
					],
					'link' => ['sysmaps.php']
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Discovery',
					'displayed_ui' => [
						'Problems',
						'Hosts',
						'Latest data',
						'Maps'
					],
					'link' => ['zabbix.php?action=discovery.view']
				]
			],
			[
				[
					'section' => 'Services',
					'page' => 'Services',
					'displayed_ui' => [
						'SLA',
						'SLA report'
					],
					'link' => ['zabbix.php?action=service.list']
				]
			],
			[
				[
					'section' => 'Services',
					'page' => 'SLA',
					'displayed_ui' => [
						'Services',
						'SLA report'
					],
					'link' => ['zabbix.php?action=sla.list']
				]
			],
			[
				[
					'section' => 'Services',
					'page' => 'SLA report',
					'displayed_ui' => [
						'Services',
						'SLA'
					],
					'link' => ['zabbix.php?action=slareport.list']
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
			'Users' => [
				'User groups',
				'User roles',
				'Users',
				'API tokens',
				'Authentication'
			]
		];
		$this->page->userLogin('user_for_role', 'zabbixzabbix');

		foreach ([true, false] as $action_status) {
			$menu = CMainMenuElement::find()->one();

			if ($data['section'] !== 'Dashboards') {
				$menu->select($data['section']);
			}

			if ($data['page'] === $data['section']) {
				$submenu = $menu->query("xpath:.//a[text()=".CXPathHelper::escapeQuotes($data['section']).
						"]/../ul[@class='submenu']")->one();
				$this->assertEquals($action_status, $submenu->query('link', $data['page'])->one(false)->isValid());
			}
			else {
				if (array_key_exists('actions', $data)) {
					$menu->query('xpath:.//ul/li/a[text()="Actions"]')->waitUntilClickable()->one()->click();
				}

				$this->assertEquals($action_status, $menu->exists($data['page']));
			}

			if ($action_status) {
				if (array_key_exists('user_roles', $data)) {
					$this->signOut();
					$this->page->userLogin('Admin', 'zabbix');
					$this->changeRoleRule([$data['section'] => $data['displayed_ui']]);
					$this->signOut();
					$this->page->userLogin('user_for_role', 'zabbixzabbix');
				}
				else {
					$this->changeRoleRule([$data['section'] => $data['displayed_ui']]);
					$this->page->open('zabbix.php?action=dashboard.view')->waitUntilReady();
				}

				if (array_key_exists('actions', $data)) {
					$this->changeRoleRule([$data['section'] => $data['displayed_ui']]);
					$this->page->open('zabbix.php?action=action.list'.(($data['page'] === 'Trigger actions') ?
							'&eventsource=1' : '&eventsource=0'))->waitUntilReady();
					$popup_menu = $this->query('id:page-title-general')->asPopupButton()->one()->getMenu();
					$this->assertNotContains($data['page'], $popup_menu->getItems()->asText());
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

	/**
	 * Manage API token action check.
	 */
	public function testUserRolesPermissions_ManageApiToken() {
		$this->page->userLogin('user_for_role', 'zabbixzabbix');
		$this->page->open('zabbix.php?action=user.token.list')->waitUntilReady();
		$this->assertEquals('TEST_SERVER_NAME: API tokens', $this->page->getTitle());
		$this->changeRoleRule(['Manage API tokens' => false]);
		$this->checkLinks(['zabbix.php?action=user.token.list']);
		$this->page->logout();
	}

	/**
	 * Disabling access to Dashboard. Check warning message text and button.
	 */
	public function testUserRolesPermissions_Dashboard() {
		$this->page->userLogin('user_for_role', 'zabbixzabbix');
		$this->page->open('zabbix.php?action=dashboard.view')->waitUntilReady();
		$this->changeRoleRule(['Dashboards' => false]);
		$this->checkLinks(['zabbix.php?action=dashboard.view'], 'Problems');
	}

	public static function getRoleServiceData() {
		return [
			[
				[
					'role_config' => [
						'Read-write access to services' => 'None',
						'Read-only access to services' => 'None'
					],
					'services' => null
				]
			],
			[
				[
					'role_config' => [
						'Read-write access to services' => 'All',
						'Read-only access to services' => 'None'
					],
					'services' => [
						'Child of child 1' => 'write',
						'Child of parent 1' => 'write',
						'Child of parent 2' => 'write',
						'Parent 1' => 'write',
						'Parent 2' => 'write'
					]
				]
			],
			[
				[
					'role_config' => [
						'Read-write access to services' => 'None',
						'Read-only access to services' => 'All'
					],
					'services' => [
						'Child of child 1' => 'read',
						'Child of parent 1' => 'read',
						'Child of parent 2' => 'read',
						'Parent 1' => 'read',
						'Parent 2' => 'read'
					]
				]
			],
			[
				[
					'role_config' => [
						'Read-write access to services' => 'All',
						'Read-only access to services' => 'All'
					],
					'services' => [
						'Child of child 1' => 'write',
						'Child of parent 1' => 'write',
						'Child of parent 2' => 'write',
						'Parent 1' => 'write',
						'Parent 2' => 'write'
					]
				]
			],
			[
				[
					'role_config' => [
						'Read-write access to services' => 'Service list',
						'Read-only access to services' => 'None'
					],
					'service_list' => [
						'Read-write access to services with tag' => [
							'service-write-tag-tag' => 'test',
							'service_write_tag_value' => 'test123'
						]
					],
					'services' => [
						'Child of child 1' => 'write',
						'Child of parent 1' => 'write'
					]
				]
			],
			[
				[
					'role_config' => [
						'Read-write access to services' => 'None',
						'Read-only access to services' => 'Service list'
					],
					'service_list' => [
						'Read-only access to services with tag' => [
							'service-read-tag-tag' => 'test',
							'service_read_tag_value' => 'test123'
						]
					],
					'services' => [
						'Child of child 1' => 'read',
						'Child of parent 1' => 'read'
					]
				]
			],
			[
				[
					'role_config' => [
						'Read-write access to services' => 'Service list',
						'Read-only access to services' => 'None'
					],
					'service_list' => [
						'xpath:(//div[@class="multiselect-control"])[1]' => 'Child of parent 1'
					],
					'services' => [
						'Child of child 1' => 'write',
						'Child of parent 1' => 'write'
					]
				]
			],
			[
				[
					'role_config' => [
						'Read-write access to services' => 'None',
						'Read-only access to services' => 'Service list',
						// added element 'API methods' with default value for page scroll
						'API methods' => 'Deny list'
					],
					'service_list' => [
						'xpath:(//div[@class="multiselect-control"])[2]' => 'Child of parent 1'
					],
					'services' => [
						'Child of child 1' => 'read',
						'Child of parent 1' => 'read'
					]
				]
			],
			[
				[
					'role_config' => [
						'Read-write access to services' => 'Service list',
						'Read-only access to services' => 'All'
					],
					'service_list' => [
						'xpath:(//div[@class="multiselect-control"])[1]' => 'Child of parent 1'
					],
					'services' => [
						'Child of child 1' => 'write',
						'Child of parent 1' => 'write',
						'Child of parent 2' => 'read',
						'Parent 1' => 'read',
						'Parent 2' => 'read'
					]
				]
			]
		];
	}

	/**
	 * Check permissions to services based on user role configuration.
	 *
	 * @dataProvider getRoleServiceData
	 */
	public function testUserRolesPermissions_ServicePermissions($data) {
		// Prepare a combination of service name and the number of child services for service for further comparison.
		if ($data['services'] !== null) {
			$child_services = [
				'Child of parent 1' => 1,
				'Parent 1' => 1,
				'Parent 2' => 1
			];
			$column_content = [];

			foreach (array_keys($data['services']) as $service) {
				$column_content[] = array_key_exists($service, $child_services)
					? $service.' '.$child_services[$service]
					: $service;
			}
		}

		// Configure the role according to the data provider.
		$this->page->login()->open('zabbix.php?action=userrole.edit&roleid='.self::$super_roleid)->waitUntilReady();
		$form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();
		$form->fill($data['role_config']);

		if (array_key_exists('service_list', $data)) {
			$form->fill($data['service_list']);
		}
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'User role updated');
		$this->page->logout();

		// Login as user that belongs to the updated row and check access to services based on applied configuration.
		$this->page->userLogin('user_for_role', 'zabbixzabbix');
		$this->page->open('zabbix.php?action=service.list')->waitUntilReady();
		$this->assertEquals('user_for_role', $this->query('xpath://a[text()="User settings"]')->one()->getAttribute('title'));

		$services_mode = $this->query('id:list_mode')->asSegmentedRadio()->one(false);

		// Check that table service list content and edit mode in not available if the user doest have permissions.
		if ($data['services'] === null) {
			$this->assertTableData();
			$this->assertFalse($services_mode->isValid());

			return;
		}
		elseif ($data['role_config']['Read-write access to services'] !== 'None') {
			// Open edit mode if user has write permissions to at least one service.
			$services_mode->select('Edit');
			$this->page->waituntilReady();
		}

		// Filter out unnecessary services.
		$this->query('id:filter_tags_0_tag')->waitUntilVisible()->one()->fill('action');
		$this->query('id:filter_tags_0_operator')->asDropdown()->waitUntilVisible()->one()->fill('Does not exist');

		// Apply filter in order to see the list of available services.
		$this->query('name:filter_set')->waitUntilClickable()->one()->click();
		$this->page->waituntilReady();

		$this->assertTableDataColumn($column_content, 'Name');
		$table = $this->query('class:list-table')->asTable()->one();

		// Check buttons are not visible for user with no permissions, otherwise, check edit permissions per service.
		if ($data['role_config']['Read-write access to services'] === 'None') {
			foreach ($table->getRows() as $row) {
				$this->assertEquals(0, $row->query('xpath:.//button')->all(false)->count());
			}
		}
		else {
			foreach ($data['services'] as $service => $permissions) {
				$property = ($permissions === 'write') ? CElementFilter::CLICKABLE : CElementFilter::NOT_CLICKABLE;
				$row = $table->findRow('Name', $service, true);
				// Check that all three action buttons in the row are clickable.
				$this->assertEquals(3, $row->query("xpath:.//button")->all()
						->filter(new CElementFilter($property))->count()
				);
			}
		}
	}

	public static function getExecuteNowButtonData() {
		return [
			[
				[
					'user' => 'U1-r-on',
					'test_cases' => [
						// Simple items.
						[
							'items' => ['I4-trap-log']
						],
						[
							'expected' => TEST_GOOD,
							'items' => ['I5-agent-txt', 'I4-trap-log'],
							'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
						],
						// Dependent items.
						[
							'expected' => TEST_GOOD,
							'items' => ['I1-lvl2-dep-log'],
							'message' => 'Request sent successfully'
						],
						[
							'expected' => TEST_BAD,
							'items' => ['I2-lvl2-dep-log'],
							'message' => 'Cannot send request: wrong master item type.'
						]
					]
				]
			],
			[
				[
					'user' => 'U2-r-off',
					'test_cases' => [
						// Simple items.
						[
							'items' => ['I4-trap-log']
						],
						[
							'items' => ['I5-agent-txt', 'I4-trap-log']
						],
						// Dependent items.
						[
							'items' => ['I1-lvl2-dep-log']
						],
						[
							'items' => ['I2-lvl2-dep-log']
						]
					]
				]
			],
			[
				[
					'user' => 'U3-rw-off',
					'test_cases' => [
						// Simple items.
						[
							'items' => ['I4-trap-log']
						],
						// Dependent items.
						[
							'expected' => TEST_GOOD,
							'items' => ['I1-lvl2-dep-log', 'I4-trap-log'],
							'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
						],
						[
							'expected' => TEST_BAD,
							'items' => ['I2-lvl2-dep-log'],
							'message' => 'Cannot send request: wrong master item type.'
						]
					]
				]
			]
		];
	}

	/**
	 * Check permissions to "Execute now" button on Latest data page based on user role.
	 *
	 * @dataProvider getExecuteNowButtonData
	 */
	public function testUserRolesPermissions_ExecuteNowButton($data) {
		// Login and select host group for testing.
		$this->page->userLogin($data['user'], 'zabbixzabbix');
		$this->page->open('zabbix.php?action=latest.view')->waitUntilReady();
		$table = $this->query('xpath://table['.CXPathHelper::fromClass('list-table fixed').']')->asTable()->one();
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_form->fill(['Host groups' => 'HG-for-executenow']);
		$filter_form->submit();
		$table->waitUntilReloaded();

		$selected_count = $this->query('id:selected_count')->one();
		$select_all = $this->query('id:all_items')->asCheckbox()->one();

		foreach ($data['test_cases'] as $test_case) {
			$table->findRows('Name', $test_case['items'])->select();
			$this->assertEquals(count($test_case['items']).' selected', $selected_count->getText());

			// Disabled "Execute now" button.
			if (!array_key_exists('expected', $test_case)) {
				$this->assertTrue($this->query('button:Execute now')->one()->isEnabled(false));
				// Reset selected items.
				$select_all->check();
				$select_all->uncheck();
				$this->assertEquals('0 selected', $selected_count->getText());
				continue;
			}

			$this->query('button:Execute now')->one()->click();

			switch (CTestArrayHelper::get($test_case, 'expected')) {
				case TEST_GOOD:
					$this->assertMessage(TEST_GOOD, $test_case['message']);
					// After a successful "Execute now" action, the item selection is reset.
					$this->assertEquals('0 selected', $selected_count->getText());
					break;

				case TEST_BAD:
					$this->assertMessage(TEST_BAD, 'Cannot execute operation', $test_case['message']);
					// Reset selected items after a failed "Execute now" action.
					$this->assertEquals(count($test_case['items']).' selected', $selected_count->getText());
					$select_all->check();
					$select_all->uncheck();
					$this->assertEquals('0 selected', $selected_count->getText());
					break;
			}

			CMessageElement::find()->waitUntilVisible()->one()->close();
		}
	}

	public static function getExecuteNowContextMenuData() {
		return [
			[
				[
					'user' => 'U1-r-on',
					'test_cases' => [
						[
							'items' => ['I4-trap-log', 'I2-lvl1-trap-num']
						],
						[
							'expected' => TEST_GOOD,
							'items' => 'I1-lvl2-dep-log'
						],
						[
							'expected' => TEST_BAD,
							'items' => 'I2-lvl2-dep-log',
							'message' => 'Cannot send request: wrong master item type.'
						]
					]
				]
			],
			[
				[
					'user' => 'U2-r-off',
					'test_cases' => [
						[
							'items' => ['I4-trap-log', 'I5-agent-txt', 'I1-lvl2-dep-log', 'I2-lvl2-dep-log']
						]
					]
				]
			],
			[
				[
					'user' => 'U3-rw-off',
					'test_cases' => [
						[
							'items' => ['I4-trap-log', 'I2-lvl1-trap-num']
						],
						[
							'expected' => TEST_GOOD,
							'items' => 'I1-lvl2-dep-log'
						],
						[
							'expected' => TEST_BAD,
							'items' => 'I2-lvl2-dep-log',
							'message' => 'Cannot send request: wrong master item type.'
						]
					]
				]
			]
		];
	}

	/**
	 * Check permissions to "Execute now" link in context menu on Latest data page based on user role.
	 *
	 * @dataProvider getExecuteNowContextMenuData
	 */
	public function testUserRolesPermissions_ExecuteNowContextMenu($data) {
		// Login and select host group for testing.
		$this->page->userLogin($data['user'], 'zabbixzabbix');
		$this->page->open('zabbix.php?action=latest.view')->waitUntilReady();
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_form->fill(['Host groups' => 'HG-for-executenow']);
		$filter_form->submit();
		$this->page->waitUntilReady();

		foreach ($data['test_cases'] as $test_case) {
			// Disabled "Execute now" option in context menu.
			if (!array_key_exists('expected', $test_case)) {
				foreach ($test_case['items'] as $item) {
					$this->query('link', $item)->waitUntilClickable()->one()->click();
					$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
					$this->assertFalse($popup->getItem('Execute now')->isEnabled());
					$this->page->pressKey(WebDriverKeys::ESCAPE);
				}

				continue;
			}

			$this->query('link', $test_case['items'])->waitUntilClickable()->one()->click();
			$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
			$popup->fill('Execute now');

			if ($test_case['expected'] === TEST_GOOD) {
				$this->assertMessage(TEST_GOOD, 'Request sent successfully');
			}
			else {
				$this->assertMessage(TEST_BAD, 'Cannot execute operation', $test_case['message']);
			}

			CMessageElement::find()->waitUntilVisible()->one()->close();
		}
	}

	/**
	 * Check disabled actions with links.
	 *
	 * @param array $links		checked links after disabling action
	 * @param string $page		page name displayed on error message button
	 */
	private function checkLinks($links, $page = 'Dashboards') {
		foreach ($links as $link) {
			$this->page->open($link)->waitUntilReady();
			$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "user_for_role". '.
					'You have no permissions to access this page.');
			$this->query('button:Go to "'.$page.'"')->one()->waitUntilClickable()->click();

			if ($page === 'Dashboards') {
				$this->assertStringContainsString('zabbix.php?action=dashboard', $this->page->getCurrentUrl());
			}
		}
	}

	/**
	 * Enable/disable actions and UI.
	 *
	 * @param array $action		action with true/false status or UI section with page
	 * @param string $roleid    Id of role that is created for access changes
	 */
	private function changeRoleRule($action, $roleid = null) {
		if ($roleid === null) {
			$roleid = self::$super_roleid;
		}

		$this->page->open('zabbix.php?action=userrole.edit&roleid='.$roleid)->waitUntilReady();
		$this->query('id:userrole-form')->waitUntilPresent()->asForm()->one()->fill($action)->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'User role updated');
	}

	/**
	 * Click Sign out button.
	 */
	private function signOut() {
		$this->query('xpath://a[@class="zi-sign-out"]')->waitUntilPresent()->one()->click();
		$this->page->waitUntilReady();
		$this->query('button:Sign in')->waitUntilVisible();
	}
}
