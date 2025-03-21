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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * @dataSource ScheduledReports, LoginUsers
 *
 * @backup report
 */
class testFormScheduledReport extends CWebTest {

	const USER = 'user';
	const USER_GROUP = 'user group';
	const UPDATE_REPORT_NAME = 'Report for update';
	const TEST_REPORT_NAME = 'Report for testFormScheduledReport';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public static function getHash() {
		return CDBHelper::getHash('SELECT * FROM report r ORDER by r.reportid').
				CDBHelper::getHash('SELECT * FROM report_param rp ORDER by rp.reportparamid').
				CDBHelper::getHash('SELECT * FROM report_user ru ORDER by ru.reportuserid').
				CDBHelper::getHash('SELECT * FROM report_usrgrp rg ORDER by rg.reportusrgrpid');
	}

	/**
	 * Report default values.
	 *
	 * @var array
	 */
	private $default_values = [
		'fields' => [
			'Owner' => 'Admin (Zabbix Administrator)',
			'Period' => 'Previous day',
			'Cycle' => 'Daily',
			'Enabled' => true
		],
		'Start time' => '00:00',
		'Subscriptions' => [
			'Recipient' => 'Admin (Zabbix Administrator)',
			'Generate report by' => 'Current user',
			'Status' => 'Include'
		]
	];

	public function testFormScheduledReport_Layout() {
		$this->page->login()->open('zabbix.php?action=scheduledreport.list');
		$this->query('button:Create report')->waitUntilClickable()->one()->click();
		$form = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();

		$this->checkFormLayout($form);
	}

	public function testFormScheduledReport_DashboardLayout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=2');
		$this->page->waitUntilReady();
		$this->query('id:dashboard-actions')->one()->waitUntilClickable()->click();
		CPopupMenuElement::find()->waitUntilVisible()->one()->select('Create new report');
		$overlay = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $overlay->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();
		$this->assertFalse($form->query('button:Test')->one(false)->isValid());

		$this->checkFormLayout($form, 'Zabbix server health');
	}

	/**
	 * Check report form layout on page and in overlay dialog in dashboard.
	 *
	 * @param CElement	$form			form element to be checked
	 * @param string	$dashboard		dashboard name
	 */
	private function checkFormLayout($form, $dashboard = null) {
		$subscription_container = $form->getField('Subscriptions')->asTable();

		// Report form fields maxlength attribute.
		$maxlength_fields = ['Name' => 255, 'id:active_since' => 255, 'id:active_till' => 255, 'Subject' => 255,
			'Message' => 65535, 'Description' => 2048
		];
		foreach ($maxlength_fields as $field => $maxlength) {
			$this->assertEquals($maxlength, $form->getField($field)->getAttribute('maxlength'));
		}

		// Check form default values.
		if ($dashboard) {
			$this->default_values['fields']['Dashboard'] = $dashboard;
		}
		$form->checkValue($this->default_values['fields']);
		$time_container = $form->getFieldContainer('Start time');
		$time = explode(':', $this->default_values['Start time']);
		$this->assertEquals($time[0], $time_container->query('id:hours')->one()->getValue());
		$this->assertEquals($time[1], $time_container->query('id:minutes')->one()->getValue());

		// Check that "Repeat on" is visible only for weekly cycle.
		foreach (['Daily', 'Monthly', 'Yearly'] as $cycle) {
			$form->fill(['Cycle' => $cycle]);
			$this->assertFalse($form->getField('Repeat on')->isVisible());
		}

		// Check placeholders in "Start date" and "End date" fields.
		foreach (['Start date', 'End date'] as $date_field) {
			$placeholder = $form->getField($date_field)->query('xpath:./input')->one()->getAttribute('placeholder');
			$this->assertEquals('YYYY-MM-DD', $placeholder);
		}

		// Check default values for current subscriber and when add new subscriptions in overlay dialog.
		foreach ([$this->default_values['Subscriptions'], self::USER, self::USER_GROUP] as $type) {
			if (is_array($type)) {
				$subscription_container->findRow('Recipient', $type['Recipient'])->query('tag:a')->one()->click();
			}
			else {
				$subscription_container->query('button', 'Add '.$type)->one()->click();
			}

			$subscription_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$overlay_form = $subscription_overlay->query('id:subscription-form')->waitUntilVisible()->asForm()->one();
			$overlay_form->checkValue((is_array($type)) ? $type : ['Generate report by' => 'Current user']);

			$buttons = (is_array($type)) ? ['Update', 'Cancel'] : ['Add', 'Cancel'];
			foreach ($buttons as $button) {
				$this->assertTrue($subscription_overlay->query('button', $button)->one()->isClickable());
			}

			if ($type === self::USER) {
				$overlay_form->checkValue(['Status' => 'Include']);
			}

			if ($type === self::USER_GROUP) {
				// "Status" field isn't present for user group.
				$this->assertFalse($overlay_form->query('id:exclude')->one(false)->isValid());
			}

			$subscription_overlay->query('button:Cancel')->one()->click();
			$subscription_overlay->waitUntilNotVisible();
		}

		// Check default subscriber in the Subscription table.
		$this->default_values['Subscriptions']['Generate report by'] = $this->default_values['Subscriptions']['Recipient'];
		$row = $subscription_container->findRow('Recipient', $this->default_values['Subscriptions']['Recipient']);
		foreach ($this->default_values['Subscriptions'] as $column => $value) {
			$this->assertEquals($value, $row->getColumn($column)->getText());
		}

		// Check that changing the status in subscriptions table also changes the status in overlay dialog.
		$row->getColumn('Status')->query('tag:a')->one()->click();
		$this->assertEquals('Exclude', $row->getColumn('Status')->getText());
		$row->getColumn('Recipient')->query('tag:a')->one()->click();
		$subscription_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$overlay_form = $subscription_overlay->query('id:subscription-form')->waitUntilVisible()->asForm()->one();
		$overlay_form->checkValue(['Status' => 'Exclude']);
		$subscription_overlay->query('class:btn-overlay-close')->one()->click()->waitUntilNotVisible();

		// Close report overlay on Dashboard.
		if ($dashboard) {
			COverlayDialogElement::find()->waitUntilReady()->one()->close();
		}
	}

	/**
	 * Common validation data for creating and updating the report.
	 *
	 * @return array
	 */
	public static function getCommonValidationData() {
		return [
			// Empty fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Owner' => '',
						'Name' => 'empty owner'
					],
					'message_details' => 'Field "userid" is mandatory.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'empty repeat on',
						'Cycle' => 'Weekly',
						'Repeat on' => []
					],
					'message_details' => 'Incorrect value for field "Repeat on": at least one day of the week must be selected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'empty Recipient updating default subscription'
					],
					'Subscriptions' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'fields' => [
								'Recipient' => ''
							]
						]
					],
					'subscription_error' => 'Incorrect value for field "Recipient": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'empty Recipient creating new user subscription'
					],
					'Subscriptions' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => self::USER,
							'fields' => [
								'Recipient' => ''
							]
						]
					],
					'subscription_error' => 'Incorrect value for field "Recipient": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'empty Recipient creating new user group subscription'
					],
					'Subscriptions' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => self::USER_GROUP,
							'fields' => [
								'Recipient' => ''
							]
						]
					],
					'subscription_error' => 'Incorrect value for field "Recipient": cannot be empty.'
				]
			],
			// The identical report names, users or user groups recipient.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Report for delete'
					],
					'error_message_part' => 'add',
					'message_details' => 'Report "Report for delete" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'two identical users in subscription'
					],
					'Subscriptions' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => self::USER,
							'fields' => [
								'Recipient' => 'Admin'
							]
						]
					],
					'subscription_error' => 'Recipient already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'two identical groups in subscription'
					],
					'Subscriptions' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => self::USER_GROUP,
							'fields' => [
								'Recipient' => 'Disabled'
							]
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => self::USER_GROUP,
							'fields' => [
								'Recipient' => 'Disabled'
							]
						]
					],
					'subscription_error' => 'Recipient already exists.'
				]
			],
			// Start time field validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'start time 24 hours'
					],
					'Start time' => '24:10',
					'message_details' => 'Incorrect value for field "hours": value must be no greater than "23".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'start time -1 hour'
					],
					'Start time' => '-1:10',
					'message_details' => 'Incorrect value for field "hours": value must be no less than "0".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'start time 60 minutes'
					],
					'Start time' => '00:60',
					'message_details' => 'Incorrect value for field "minutes": value must be no greater than "59".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'start time -1 minutes'
					],
					'Start time' => '00:-1',
					'message_details' => 'Incorrect value for field "minutes": value must be no less than "0".'
				]
			],
			// Date fields validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'start date is later than end date',
						'Start date' => '2021-07-02',
						'End date' => '2021-07-01'
					],
					'error_message_part' => 'add',
					'message_details' => '"active_till" must be an empty string or greater than "active_since".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'start date with symbols',
						'Start date' => 'YYYY-MM-DD'
					],
					'error_message_part' => 'add',
					'message_details' => 'Invalid parameter "/1/active_since": a date in YYYY-MM-DD format is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'end date with symbols',
						'End date' => 'YYYY-MM-DD'
					],
					'error_message_part' => 'add',
					'message_details' => 'Invalid parameter "/1/active_till": a date in YYYY-MM-DD format is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'start date month is 13',
						'Start date' => '2021-13-02'
					],
					'error_message_part' => 'add',
					'message_details' => 'Invalid parameter "/1/active_since": a date in YYYY-MM-DD format is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'end date month is 13',
						'End date' => '2021-13-02'
					],
					'error_message_part' => 'add',
					'message_details' => 'Invalid parameter "/1/active_till": a date in YYYY-MM-DD format is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'start date is february 30',
						'Start date' => '2021-02-30'
					],
					'error_message_part' => 'add',
					'message_details' => 'Invalid parameter "/1/active_since": a date in YYYY-MM-DD format is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'end date february 30',
						'Dashboard' => 'Global view',
						'End date' => '2021-02-30'
					],
					'error_message_part' => 'add',
					'message_details' => 'Invalid parameter "/1/active_till": a date in YYYY-MM-DD format is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'start date invalid',
						'Start date' => '2021/07/02'
					],
					'error_message_part' => 'add',
					'message_details' => 'Invalid parameter "/1/active_since": a date in YYYY-MM-DD format is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'end date invalid',
						'End date' => '02-07-2021'
					],
					'error_message_part' => 'add',
					'message_details' => 'Invalid parameter "/1/active_till": a date in YYYY-MM-DD format is expected.'
				]
			]
		];
	}

	/**
	 * Common data for creating a report on page and in overlay dialog in dashboard.
	 *
	 * @return array
	 */
	public static function getCommonCreateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'message_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'empty subscriptions'
					],
					'Subscriptions' => [
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'Admin (Zabbix Administrator)'
							]
						]
					],
					'error_message_part' => 'add',
					'message_details' => 'At least one user or user group must be specified.'
				]
			],
			// Exclude user from subscriptions.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'exclude user from default subscription'
					],
					'Subscriptions' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'type' => self::USER,
							'fields' => [
								'Status' => 'Exclude'
							]
						]
					],
					'error_message_part' => 'add',
					'message_details' => 'If no user groups are specified, at least one user must be included in the mailing list.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Fill minimum fields'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '  Report with leading and trailing spaces  ',
						'Subject' => '  test.trim  ',
						'Message' => '  test.trim  ',
						'Description' => '  test.trim  '
					],
					'trim' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Remove default subscriber and add new user group',
						'Start date' => '2019-05-06'
					],
					'Start time' => '00:10',
					'Subscriptions' => [
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'Admin (Zabbix Administrator)'
							]
						],
						[
							'type' => self::USER_GROUP,
							'fields' => [
								'Recipient' => 'Enabled debug mode',
								'Generate report by' => 'Recipient'
							]
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Exclude default subscriber and add new user',
						'End date' => '2025-05-06'
					],
					'Start time' => '10:00',
					'Subscriptions' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'type' => self::USER,
							'fields' => [
								'Status' => 'Exclude'
							]
						],
						[
							'type' => self::USER,
							'fields' => [
								'Recipient' => 'test-user',
								'Generate report by' => 'Recipient'
							]
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Owner' => 'admin-zabbix',
						'Name' => 'Fill all fields',
						'Dashboard' => 'Zabbix server health',
						'Period' => 'Previous week',
						'Cycle' => 'Weekly',
						'Repeat on' => ['Tuesday', 'Thursday', 'Sunday'],
						'Start date' => '2021-06-07',
						'End date' => '2021-06-09',
						'Subject' => 'Report from zabbix',
						'Message' => 'weekly report',
						'Description' => 'test',
						// TODO: change status to false after fix ZBX-19693
						'Enabled' => true
					],
					'Start time' => '12:10',
					'Subscriptions' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'type' => self::USER,
							'fields' => [
								'Recipient' => 'admin-zabbix'
							]
						],
						[
							'type' => self::USER,
							'fields' => [
								'Recipient' => 'guest',
								'Generate report by' => 'Recipient',
								'Status' => 'Exclude'
							]
						],
						[
							'type' => self::USER,
							'fields' => [
								'Recipient' => 'test-user'
							]
						],
						[
							'type' => self::USER_GROUP,
							'fields' => [
								'Recipient' => 'Guests'
							]
						],
						[
							'type' => self::USER_GROUP,
							'fields' => [
								'Recipient' => 'Enabled debug mode',
								'Generate report by' => 'Recipient'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * Data for creating a report on the page.
	 */
	public function getCreateData() {
		$data = [];
		$common_data = array_merge($this->getCommonValidationData(), $this->getCommonCreateData());

		// Add 'Dashboard' field value and error message header.
		foreach ($common_data as $report) {
			if (!array_key_exists('Dashboard', $report[0]['fields'])) {
				$report[0]['fields']['Dashboard'] = 'Global view';
			}
			if ($report[0]['expected'] === TEST_BAD) {
				$report[0]['message_header'] = 'Cannot '.CTestArrayHelper::get($report[0], 'error_message_part', 'create').
						' scheduled report';
			}

			$data[] = $report;
		}

		return array_merge($data, [
			// Empty fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'message_header' => 'Cannot create scheduled report',
					'message_details' => ['Incorrect value for field "name": cannot be empty.', 'Field "dashboardid" is mandatory.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'empty dashboard field'
					],
					'message_header' => 'Cannot create scheduled report',
					'message_details' => 'Field "dashboardid" is mandatory.'
				]
			]
		]);
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormScheduledReport_Create($data) {
		$this->page->login()->open('zabbix.php?action=scheduledreport.edit');
		$this->executeAction($data, 'add', 'Scheduled report added');
	}

	/**
	 * Data for creating a report from the dashboard.
	 */
	public function getDashboardCreateData() {
		$data = [];
		$common_data = array_merge($this->getCommonValidationData(), $this->getCommonCreateData());

		foreach ($common_data as $report) {
			// Add prefix to the report name in the common data so that the names do not match with create data on page.
			if (array_key_exists('Name', $report[0]['fields']) && $report[0]['fields']['Name'] !== 'Report for delete') {
				$report[0]['fields']['Name'] = 'From dashboard - '.$report[0]['fields']['Name'];
			}
			// Reports in dashboard do not have an error message header.
			if ($report[0]['expected'] === TEST_BAD) {
				$report[0]['message_header'] = null;
			}
			$data[] = $report;
		}

		return array_merge($data, [
			// Remove values and check empty fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Owner' => '',
						'Dashboard' => ''
					],
					'message_header' => null,
					'message_details' => ['Field "userid" is mandatory.',
						'Incorrect value for field "name": cannot be empty.',
						'Field "dashboardid" is mandatory.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Dashboard' => ''
					],
					'message_header' => null,
					'message_details' => 'Field "dashboardid" is mandatory.'
				]
			]
		]);
	}

	/**
	 * @dataProvider getDashboardCreateData
	 */
	public function testFormScheduledReport_CreateInDashboard($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1')->waitUntilReady();
		$this->query('id:dashboard-actions')->one()->waitUntilClickable()->click();
		CPopupMenuElement::find()->waitUntilVisible()->one()->select('Create new report');

		$this->executeAction($data, 'dashboard', 'Scheduled report created');
	}

	public function testFormScheduledReport_SimpleUpdate() {
		$old_hash = $this->getHash();
		$name = CDBHelper::getRandom('SELECT name FROM report', 1);
		$this->page->login()->open('zabbix.php?action=scheduledreport.list');
		$this->query('link', $name)->waitUntilClickable()->one()->click();
		$this->query('button:Update')->waitUntilClickable()->one()->click();
		$this->assertMessage(TEST_GOOD, 'Scheduled report updated');
		$this->assertEquals($old_hash, $this->getHash());
	}

	public function getUpdateData() {
		$data = [];

		foreach ($this->getCommonValidationData() as $report) {
			$report[0]['message_header'] = 'Cannot update scheduled report';
			$data[] = $report;
		}

		return array_merge($data, [
			// Empty fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Owner' => '',
						'Name' => '',
						'Dashboard' => ''
					],
					'message_header' => 'Cannot update scheduled report',
					'message_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Field "userid" is mandatory.',
						'Field "dashboardid" is mandatory.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => ''
					],
					'message_header' => 'Cannot update scheduled report',
					'message_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Dashboard' => ''
					],
					'message_header' => 'Cannot update scheduled report',
					'message_details' => 'Field "dashboardid" is mandatory.'
				]
			],
			// Remove all subscriptions.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'empty subscriptions'
					],
					'Subscriptions' => [
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'Admin (Zabbix Administrator)'
							]
						],
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'guest'
							]
						],
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'Zabbix administrators'
							]
						]
					],
					'message_header' => 'Cannot update scheduled report',
					'message_details' => 'At least one user or user group must be specified.'
				]
			],
			// Exclude user from subscriptions.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'leave only the user who have exclude status in the report'
					],
					'Subscriptions' => [
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'Admin (Zabbix Administrator)'
							]
						],
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'Zabbix administrators'
							]
						]
					],
					'message_header' => 'Cannot update scheduled report',
					'message_details' => 'If no user groups are specified, at least one user must be included in the mailing list.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'exclude all users from the report'
					],
					'Subscriptions' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'type' => self::USER,
							'fields' => [
								'Status' => 'Exclude'
							]
						],
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'Zabbix administrators'
							]
						]
					],
					'message_header' => 'Cannot update scheduled report',
					'message_details' => 'If no user groups are specified, at least one user must be included in the mailing list.'
				]
			],
			// Remove not required fields and remove subscriptions except user group.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Dashboard' => 'Global view',
						'Repeat on' => [],
						'Cycle' => 'Daily',
						'Start date' => '',
						'End date' => '',
						'Subject' => '',
						'Message' => '',
						'Description' => '',
						'Enabled' => false
					],
					'Start time' => '00:00',
					'Subscriptions' => [
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'Admin (Zabbix Administrator)'
							]
						],
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'guest'
							]
						]
					]
				]
			],
			// Update all fields (update, delete and create new subscriptions).
			[
				[
					'expected' => TEST_GOOD,
					'report' => 'Report to update all fields',
					'fields' => [
						'Owner' => 'admin-zabbix',
						'Name' => 'Update all fields',
						'Repeat on' => [],
						'Period' => 'Previous month',
						'Cycle' => 'Yearly',
						'Start date' => '2022-07-08',
						'End date' => '2022-07-10',
						'Subject' => 'Report from zabbix - update test',
						'Message' => 'monthly report',
						'Description' => 'test update',
						'Enabled' => true
					],
					'Start time' => '01:59',
					'Subscriptions' => [
						[
							'action' => USER_ACTION_UPDATE,
							'type' => self::USER,
							'index' => 0,
							'fields' => [
								'Recipient' => 'user-zabbix',
								'Generate report by' => 'Recipient',
								'Status' => 'Exclude'
							]
						],
						[
							'action' => USER_ACTION_UPDATE,
							'type' => self::USER_GROUP,
							'index' => 2,
							'fields' => [
								'Recipient' => 'Guests',
								'Generate report by' => 'Current user'
							]
						],
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'guest'
							]
						],
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'Zabbix administrators'
							]
						],
						[
							'type' => self::USER_GROUP,
							'fields' => [
								'Recipient' => 'Enabled debug mode',
								'Generate report by' => 'Recipient'
							]
						],
						[
							'type' => self::USER,
							'fields' => [
								'Recipient' => 'admin-zabbix'
							]
						]
					]
				]
			]
		]);
	}

	/**
	 * @dataProvider getUpdateData
	 *
	 * @backupOnce report
	 */
	public function testFormScheduledReport_Update($data) {
		$update_reportid = CDataHelper::get('ScheduledReports.reportids.'.
				CTestArrayHelper::get($data, 'report', self::UPDATE_REPORT_NAME));
		$this->page->login()->open('zabbix.php?action=scheduledreport.edit&reportid='.$update_reportid);

		$this->executeAction($data, 'update', 'Scheduled report updated');
	}

	public static function getCloneData() {
		return [
			[
				[
					'fields' => [
						'Name' => microtime().' clone without changes'
					]
				]
			],
			[
				[
					'fields' => [
						'Owner' => 'user-zabbix',
						'Name' => microtime().' clone with changes',
						'Period' => 'Previous month',
						'Cycle' => 'Daily',
						'Start date' => '2021-07-19',
						'Subject' => 'Cloned report test',
						'Enabled' => true
					],
					'Subscriptions' => [
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'Admin (Zabbix Administrator)'
							]
						],
						[
							'action' => USER_ACTION_REMOVE,
							'fields' => [
								'Recipient' => 'Guests'
							]
						],
						[
							'action' => USER_ACTION_UPDATE,
							'type' => self::USER,
							'index' => 0,
							'fields' => [
								'Recipient' => 'user-zabbix',
								'Generate report by' => 'Current user',
								'Status' => 'Exclude'
							]
						],
						[
							'type' => self::USER,
							'fields' => [
								'Recipient' => 'admin-zabbix'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormScheduledReport_Clone($data) {
		$this->page->login()->open('zabbix.php?action=scheduledreport.edit&reportid='.
				CDataHelper::get('ScheduledReports.reportids.'.self::TEST_REPORT_NAME));
		$form = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();

		// Get field values from form.
		$form->fill($data['fields']);
		$expected_values = $form->getFields()->asValues();

		// If the "Repeat on" field isn't visible due to weekly cycle, then all weekdays will be selected and still received.
		if (CTestArrayHelper::get($data, 'fields.Cycle', 'Weekly') !== 'Weekly') {
			$expected_values['Repeat on'] = ['Friday', 'Monday', 'Saturday', 'Sunday', 'Thursday', 'Tuesday', 'Wednesday'];
		}

		// Start time is complex element, so needs to be checked separately.
		unset($expected_values['Start time']);
		foreach (['hours', 'minutes'] as $value) {
			$expected_start_time[$value] = $form->query('id', $value)->waitUntilVisible()->one()->getValue();
		}

		$this->fillSubscriptions($data);

		// Get values from subscriptions table.
		$expected_subscriptions = $form->getField('Subscriptions')->asTable()->index();

		// Sort new subscriber users alphabetically by 'Recipient'.
		if (array_key_exists('Subscriptions', $data)) {
			array_multisort(array_column($expected_subscriptions, 'Recipient'), SORT_ASC, $expected_subscriptions);
		}

		// Clone report.
		$this->query('button:Clone')->waitUntilClickable()->one()->click();
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Scheduled report added');

		$this->assertEquals(2, CDBHelper::getCount('SELECT NULL FROM report WHERE name IN ('.
				zbx_dbstr($data['fields']['Name']).', '.zbx_dbstr(self::TEST_REPORT_NAME).')'));
		$this->query('link', $data['fields']['Name'])->waitUntilClickable()->one()->click();
		$form->invalidate();

		// Check Start time fields separately.
		foreach (['hours', 'minutes'] as $value) {
			$start_time[$value] = $form->query('id', $value)->waitUntilVisible()->one()->getValue();
		}
		$this->assertEquals($expected_start_time, $start_time);

		$form->checkValue($expected_values);

		$actual_subscriptions = $form->getField('Subscriptions')->asTable()->index();
		$this->assertEquals($expected_subscriptions, $actual_subscriptions);
	}

	public static function getCancelData() {
		return [
			[
				[
					'action' => 'Add'
				]
			],
			[
				[
					'action' => 'Update'
				]
			],
			[
				[
					'action' => 'Clone'
				]
			],
			[
				[
					'action' => 'Delete'
				]
			],
			[
				[
					'action' => 'Dashboard'
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormScheduledReport_Cancel($data) {
		$old_hash = $this->getHash();
		$new_name = microtime(true).' Cancel '.self::TEST_REPORT_NAME;
		$subscriptions = [
			'Subscriptions' => [
				[
					'action' => USER_ACTION_REMOVE,
					'fields' => [
						'Recipient' => 'Admin (Zabbix Administrator)'
					]
				],
				[
					'type' => self::USER_GROUP,
					'fields' => [
						'Recipient' => 'Enabled debug mode'
					]
				]
			]
		];

		if ($data['action'] === 'Add') {
			$this->page->login()->open('zabbix.php?action=scheduledreport.edit');
		}
		elseif ($data['action'] === 'Dashboard') {
			$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1');
			$this->page->waitUntilReady();
			$this->query('id:dashboard-actions')->one()->waitUntilClickable()->click();
			CPopupMenuElement::find()->waitUntilVisible()->one()->select('Create new report');
		}
		else {
			$this->page->login()->open('zabbix.php?action=scheduledreport.edit&reportid='.
					CDataHelper::get('ScheduledReports.reportids.'.self::TEST_REPORT_NAME));
		}

		// Change report data to make sure that the changes are not saved to the database after cancellation.
		$form = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();
		$form->fill(['Name' => $new_name, 'Message' => 'cancel test']);
		$this->fillSubscriptions($subscriptions);

		if ($data['action'] === 'Delete') {
			$this->query('button', $data['action'])->one()->click();
			$this->page->dismissAlert();
		}

		// Check that the report creation page is open after cloning.
		if ($data['action'] === 'Clone') {
			$this->query('button', $data['action'])->one()->click();
			$this->page->waitUntilReady();
			$this->assertFalse($this->query('button', ['Update', 'Delete'])->one(false)->isValid());
			$this->assertTrue($this->query('button', ['Add', 'Cancel'])->one(false)->isValid());
		}

		$this->query('button:Cancel')->waitUntilClickable()->one()->click();

		if ($data['action'] === 'Dashboard') {
			COverlayDialogElement::ensureNotPresent();
		}
		else {
			$this->page->waitUntilReady();
			$this->assertEquals(PHPUNIT_URL.'zabbix.php?action=scheduledreport.list', $this->page->getCurrentUrl());
		}

		// Check invariability of report data in the database.
		$this->assertEquals($old_hash, $this->getHash());
		$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM report WHERE name='.zbx_dbstr($new_name)));
	}

	public static function getTestData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'error' => ['Incorrect value for field "name": cannot be empty.', 'Field "dashboardid" is mandatory.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Test option - report without dashboard'
					],
					'error' => 'Field "dashboardid" is mandatory.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Dashboard' => 'Global view'
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'report' => self::TEST_REPORT_NAME,
					'fields' => [
						'Name' => '',
						'Dashboard' => ''
					],
					'error' => ['Incorrect value for field "name": cannot be empty.', 'Field "dashboardid" is mandatory.']
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'report' => self::TEST_REPORT_NAME
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Test report - all mandatory fields',
						'Dashboard' => 'Global view'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getTestData
	 */
	public function testFormScheduledReport_TestOption($data) {
		if (array_key_exists('report', $data)) {
			$url = 'zabbix.php?action=scheduledreport.edit&reportid='.
					CDataHelper::get('ScheduledReports.reportids.'.$data['report']);
		}
		else {
			$url = 'zabbix.php?action=scheduledreport.edit';
		}
		$this->page->login()->open($url);
		$form = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();
		$form->fill(CTestArrayHelper::get($data, 'fields', []));
		$this->query('button:Test')->waitUntilClickable()->one()->click();
		COverlayDialogElement::find()->waitUntilReady()->one();

		if ($data['expected'] === TEST_GOOD) {
			$this->assertMessage(TEST_BAD, 'Report generating test failed.');
		}
		else {
			$this->assertMessage(TEST_BAD, null, $data['error']);
		}
	}

	public function testFormScheduledReport_Delete() {
		$reportid = CDataHelper::get('ScheduledReports.reportids.Report for delete');
		$this->page->login()->open('zabbix.php?action=scheduledreport.edit&reportid='.$reportid);
		$this->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->page->acceptAlert();

		$this->assertMessage(TEST_GOOD, 'Scheduled report deleted');
		// Check if all report records have been deleted.
		$tables = ['report', 'report_param', 'report_user', 'report_usrgrp'];
		foreach ($tables as $table) {
			$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM '.$table.' WHERE reportid='.$reportid));
		}
	}

	/**
	 * Create or update report.
	 *
	 * @param array $data				data provider
	 * @param string $action			add report on dashboard, add on page or update action
	 * @param string $success_message	success message text
	 */
	private function executeAction($data, $action, $success_message) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = $this->getHash();
		}

		$form = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();
		$form->fill($data['fields']);

		if (CTestArrayHelper::get($data, 'Start time', false)) {
			// Split the time on hours and minutes.
			$time = explode(':', $data['Start time']);
			$container = $form->getFieldContainer('Start time');
			// Don't fill time fields if set default time unit value for create action.
			if ($time[0] !== '00' || $action === 'update') {
				$container->query('id:hours')->one()->fill($time[0]);
			}
			if ($time[1] !== '00' || $action === 'update') {
				$container->query('id:minutes')->one()->fill($time[1]);
			}
		}
		$this->fillSubscriptions($data);

		if (CTestArrayHelper::get($data, 'subscription_error', false) === false) {
			$form->submit();
			$this->page->waitUntilReady();

			if ($data['expected'] === TEST_BAD) {
				$this->assertMessage(TEST_BAD, $data['message_header'], $data['message_details']);
				$this->assertEquals($old_hash, $this->getHash());
			}
		}

		if ($data['expected'] === TEST_GOOD) {
			if ($action === 'dashboard') {
				COverlayDialogElement::ensureNotPresent();
			}
			// Trim trailing and leading spaces in expected values before comparison.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$data['fields'] = CTestArrayHelper::trim($data['fields']);
			}
			$name = CTestArrayHelper::get($data, 'fields.Name', self::UPDATE_REPORT_NAME);
			$this->assertEquals(1, CDBHelper::getCount('SELECT null FROM report WHERE name='.zbx_dbstr($name)));
			$this->assertMessage(TEST_GOOD, $success_message);

			// Trim spaces in the middle of a name after DB check; spaces in links are trimmed.
			$name = CTestArrayHelper::get($data, 'trim', false) ? preg_replace('/\s+/', ' ', $name) : $name;

			if ($action === 'dashboard') {
				// Open report form page from dashboard.
				if (CTestArrayHelper::get($data, 'fields.Dashboard', 'Global view') !== 'Global view') {
					// Check that report does not exist in "Global view" related reports.
					$this->query('id:dashboard-actions')->one()->waitUntilClickable()->click();
					CPopupMenuElement::find()->waitUntilVisible()->one()->select('View related reports');
					$table = COverlayDialogElement::find()->waitUntilReady()->one()->asTable();
					$this->assertFalse($table->query('link', $name)->one(false)->isValid());
					// Open another dashboard and check related reports.
					$this->page->open('zabbix.php?action=dashboard.list')->waitUntilReady();
					$this->query('link', $data['fields']['Dashboard'])->waitUntilClickable()->one()->click();
				}
				$this->query('id:dashboard-actions')->one()->waitUntilClickable()->click();
				CPopupMenuElement::find()->waitUntilVisible()->one()->select('View related reports');
				COverlayDialogElement::find()->waitUntilReady()->one()
						->query('link', $name)->waitUntilClickable()->one()->click();
			}
			else {
				$this->query('link', $name)->waitUntilClickable()->one()->click();
			}
			$this->page->waitUntilReady();
			$form_page = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();

			if (CTestArrayHelper::get($data, 'fields.Repeat on') === []) {
				unset($data['fields']['Repeat on']);
			}
			$form_page->checkValue($data['fields']);

			if (CTestArrayHelper::get($data, 'Start time', false)) {
				$container = $form_page->getFieldContainer('Start time');
				$this->assertEquals($time[0], $container->query('id:hours')->one()->getValue());
				$this->assertEquals($time[1], $container->query('id:minutes')->one()->getValue());
			}
			$this->checkSubscriptions(CTestArrayHelper::get($data, 'Subscriptions', []));
		}
	}

	/**
	 * Add, update or remove subscription in report.
	 *
	 * @param array $data
	 */
	private function fillSubscriptions($data) {
		foreach (CTestArrayHelper::get($data, 'Subscriptions', []) as $i => $subscriber) {
			$report_form = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();
			$container = $report_form->getField('Subscriptions')->asTable();

			$action = CTestArrayHelper::get($subscriber, 'action', USER_ACTION_ADD);
			unset($subscriber['action']);

			if ($action === USER_ACTION_REMOVE) {
				$container->findRow('Recipient', $subscriber['fields']['Recipient'])
						->query('button:Remove')->one()->click()->waitUntilNotPresent();
			}
			else {
				if ($action === USER_ACTION_ADD) {
					$container->query('button', 'Add '.$subscriber['type'])->one()->click();
				}
				else {
					$container->getRow($subscriber['index'])->getColumn('Recipient')->query('tag:a')->one()->click();
					unset($subscriber['index']);
				}
				$overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$form = $overlay->query('id:subscription-form')->waitUntilVisible()->asForm()->one();
				if (array_key_exists('fields', $subscriber)) {
					$form->fill($subscriber['fields']);
				}

				$form->submit();
				$this->query('xpath:.//button[contains(@class, "is-loading")]')->waitUntilNotPresent();

				if (CTestArrayHelper::get($data, 'subscription_error', false)) {
					// Check error in subscription overlay for last subscriber.
					if ($i === count($data['Subscriptions'])) {
						$this->assertMessage(TEST_BAD, null, $data['subscription_error']);
					}
				}
				else {
					$overlay->waitUntilNotVisible();
					// Wait for the subscriber to be added to the subscription table.
					$user = CTestArrayHelper::get($subscriber,
							'fields.Recipient', $this->default_values['Subscriptions']['Recipient']);
					$container->query('link', $user)->waitUntilVisible();
				}
			}
		}
	}

	/**
	 * Check subscription table in report.
	 *
	 * @param array $subscriptions
	 */
	private function checkSubscriptions($subscriptions) {
		foreach ($subscriptions as $i => $subscriber) {
			$report_form = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();
			$table = $report_form->getField('Subscriptions')->asTable();

			$action = CTestArrayHelper::get($subscriber, 'action', USER_ACTION_ADD);
			unset($subscriber['action']);

			if ($action === USER_ACTION_REMOVE){
				$this->assertFalse($table->findRow('Recipient', $subscriber['fields']['Recipient'])->isValid());
			}
			else {
				// Check that subscriber was added to the Subscription table.
				$user = CTestArrayHelper::get($subscriber,
						'fields.Recipient', $this->default_values['Subscriptions']['Recipient']);
				$row = $table->findRow('Recipient', $user);

				$report_by = (CTestArrayHelper::get($subscriber, 'fields.Generate report by', 'Current user') === 'Current user')
						? 'Admin (Zabbix Administrator)'
						: 'Recipient';
				$this->assertEquals($report_by, $row->getColumn('Generate report by')->getText());

				$status = ($subscriber['type'] === self::USER) ? 'Include' : '';
				$this->assertEquals(CTestArrayHelper::get($subscriber, 'fields.Status', $status),
						$row->getColumn('Status')->getText()
				);
			}
		}
	}
}
