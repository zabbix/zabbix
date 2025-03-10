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


require_once __DIR__ . '/../../include/CWebTest.php';

/**
 * @backup profiles
 *
 * @onBefore prepareAlarmData
 *
 * @onAfterEach closeAndAcknowledgeEvents
 */
class testAlarmNotification extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CTableBehavior::class
		];
	}

	protected static $maintenanceid;
	protected static $eventids;
	protected static $hostid;
	const DEFAULT_COLORPICKER = 'xpath:./following::div[@class="color-picker"]';

	/**
	 * Trigger names.
	 */
	const ALL_TRIGGERS = [
		'Average_trigger',
		'Disaster_trigger',
		'High_trigger',
		'Information_trigger',
		'Not_classified_trigger',
		'Warning_trigger'
	];

	const HOST_NAME = 'Host for alarm item';

	public static function prepareAlarmData() {
		$response = CDataHelper::createHosts([
			[
				'host' => self::HOST_NAME,
				'groups' => [['groupid' => 4]], // Zabbix server
				'items' => [
					[
						'name' => 'Not classified',
						'key_' => 'not_classified',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Information',
						'key_' => 'information',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Warning',
						'key_' => 'warning',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Average',
						'key_' => 'average',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'High',
						'key_' => 'high',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Disaster',
						'key_' => 'disaster',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Multiple errors',
						'key_' => 'multiple_errors',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host for maintenance alarm',
				'groups' => [['groupid' => 4]], // Zabbix server
				'items' => [
					[
						'name' => 'Suppressed item',
						'key_' => 'suppressed_item',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);
		self::$hostid = $response['hostids'];

		CDataHelper::call('trigger.create', [
			[
				'description' => 'Not_classified_trigger',
				'expression' => 'last(/Host for alarm item/not_classified)=0',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
				'manual_close' => 1
			],
			[
				'description' => 'Not_classified_trigger_2',
				'expression' => 'last(/Host for alarm item/not_classified)=1',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
				'manual_close' => 1
			],
			[
				'description' => 'Not_classified_trigger_3',
				'expression' => 'last(/Host for alarm item/not_classified)=1',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
				'manual_close' => 1
			],
			[
				'description' => 'Not_classified_trigger_4',
				'expression' => 'last(/Host for alarm item/not_classified)=2',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
				'manual_close' => 1
			],
			[
				'description' => 'Information_trigger',
				'expression' => 'last(/Host for alarm item/information)=1',
				'priority' => TRIGGER_SEVERITY_INFORMATION,
				'manual_close' => 1
			],
			[
				'description' => 'Warning_trigger',
				'expression' => 'last(/Host for alarm item/warning)=2',
				'priority' => TRIGGER_SEVERITY_WARNING,
				'manual_close' => 1
			],
			[
				'description' => 'Average_trigger',
				'expression' => 'last(/Host for alarm item/average)=3',
				'priority' => TRIGGER_SEVERITY_AVERAGE,
				'manual_close' => 1
			],
			[
				'description' => 'High_trigger',
				'expression' => 'last(/Host for alarm item/high)=4',
				'priority' => TRIGGER_SEVERITY_HIGH,
				'manual_close' => 1
			],
			[
				'description' => 'Disaster_trigger',
				'expression' => 'last(/Host for alarm item/disaster)=5',
				'priority' => TRIGGER_SEVERITY_DISASTER,
				'manual_close' => 1
			],
			[
				'description' => 'Multiple_errors',
				'expression' => 'last(/Host for alarm item/multiple_errors)=0',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
				'manual_close' => 1,
				'type' => 1
			],
			[
				'description' => 'Suppressed_error',
				'expression' => 'last(/Host for maintenance alarm/suppressed_item)=0',
				'priority' => TRIGGER_SEVERITY_DISASTER,
				'manual_close' => 1,
				'type' => 1
			]
		]);

		// Enable Alarm Notification display for user.
		DBexecute('INSERT INTO profiles (profileid, userid, idx, value_str, source, type)'.
				' VALUES (555,1,'.zbx_dbstr('web.messages').',1,'.zbx_dbstr('enabled').',3)');
		DBexecute('INSERT INTO profiles (profileid, userid, idx, value_str, source, type)'.
				' VALUES (556,1,'.zbx_dbstr('web.messages').',180,'.zbx_dbstr('timeout').',3)');

		// Create Maintenance and host in maintenance.
		$maintenance = CDataHelper::call('maintenance.create', [
			[
				'name' => 'Alarm notification maintenance',
				'active_since' => time() - 1000,
				'active_till' => time() + 31536000,
				'hosts' => [['hostid' => self::$hostid['Host for maintenance alarm']]],
				'timeperiods' => [[]]
			]
		]);
		self::$maintenanceid = $maintenance['maintenanceids'][0];

		DBexecute('UPDATE hosts SET maintenanceid='.zbx_dbstr(self::$maintenanceid).
			', maintenance_status='.HOST_MAINTENANCE_STATUS_ON.', maintenance_type='.MAINTENANCE_TYPE_NORMAL.', maintenance_from='.zbx_dbstr(time()-1000).
			' WHERE hostid='.zbx_dbstr(self::$hostid['Host for maintenance alarm'])
		);
	}

	/**
	 * Check Alarm notification overlay dialog layout.
	 *
	 * @onAfter openResetedPage
	 */
	public function testAlarmNotification_Layout() {
		// Trigger problem.
		$time = time();
		$event_time = date('Y-m-d H:i:s', $time);
		self::$eventids = CDBHelper::setTriggerProblem('Not_classified_trigger_4', TRIGGER_VALUE_TRUE, ['clock' => $time]);

		$this->page->login()->open('zabbix.php?action=problem.view')->waitUntilReady();

		// Find appeared Alarm notification overlay dialog.
		$alarm_dialog = $this->getAlarmOverlay();

		// Check that Problem on text exists.
		$this->assertEquals('Problem on Host for alarm item', $alarm_dialog->query('xpath:.//h4')->one()->getText());

		// Check that link for host and trigger filtering works.
		foreach (['Hosts' => self::HOST_NAME, 'Triggers' => 'Not_classified_trigger_4'] as $field => $name) {
			$alarm_dialog->query('link', $name)->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();

			// Check that opens Monitoring->Problems page and correct values filtered.
			$this->page->assertTitle('Problems');
			$this->page->assertHeader('Problems');
			$form = $this->query('name:zbx_filter')->asForm()->one();

			if ($field === 'Triggers') {
				$name = 'Host for alarm item: '.$name;
			}

			$form->checkValue([$field => $name]);
		}

		// Check that after clicking on time - Event page opens.
		$alarm_dialog->query('link', $event_time)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertTitle('Event details');
		$this->page->assertHeader('Event details');

		// Check that events details opened for correct trigger/host.
		$table = $this->query('xpath://section[@id="hat_triggerdetails"]/div/table')->asTable()->one();
		$this->assertEquals(['Host', 'Host for alarm item'], $table->getRow(0)->getColumns()->asText());
		$this->assertEquals(['Trigger', 'Not_classified_trigger_4'], $table->getRow(1)->getColumns()->asText());

		// Check displayed icons.
		foreach (['Mute for Admin' => 'btn-icon zi-speaker', 'Snooze for Admin' => 'btn-icon zi-bell'] as $button => $class) {
			$selector = 'xpath:.//button[@title='.CXPathHelper::escapeQuotes($button).']';

			// Check that buttons exists and class says that button is ON.
			$this->assertTrue($alarm_dialog->query($selector)->exists());
			$this->assertEquals($class, $alarm_dialog->query($selector)->one()->getAttribute('class'));

			if ($button === 'Mute for Admin') {
				// After clicking on button it changes status to off and become Unmute.
				$alarm_dialog->query($selector)->one()->click();
				$alarm_dialog->query('xpath:.//button[@title="Unmute for Admin"]')->waitUntilVisible()->one();
				$this->assertEquals($class.'-off', $alarm_dialog->query('xpath:.//button[@title="Unmute for Admin"]')
					->one()->getAttribute('class')
				);

				// Check that after clicking on Unmute button, Mute icon changed back.
				$alarm_dialog->query('xpath:.//button[@title="Unmute for Admin"]')->one()->click();
				$alarm_dialog->query($selector)->waitUntilVisible()->one();
				$this->assertEquals($class, $alarm_dialog->query($selector)->one()->getAttribute('class'));
			}
			else {
				// Check that after clicking second time on already Snoozed button, it doesn't change status.
				for ($i = 0; $i <=1; $i++) {
					$alarm_dialog->query($selector)->one()->click();
					$this->page->refresh()->waitUntilReady();
					$this->assertTrue($alarm_dialog->query($selector)->exists());
					$this->assertEquals($class.'-off', $alarm_dialog->query($selector)->one()->getAttribute('class'));
				}
			}
		}

		// Close problem and open Problem page.
		CDBHelper::setTriggerProblem('Not_classified_trigger_4', TRIGGER_VALUE_FALSE);
		$this->page->open('zabbix.php?action=problem.view')->waitUntilReady();

		// Check that problem resolved and problem color is green now.
		$this->assertEquals('Resolved Host for alarm item', $alarm_dialog->query('xpath:.//h4')->one()->getText());
		$this->assertEquals('rgba(89, 219, 143, 1)', $alarm_dialog->query('class:notif-indic')
				->one()->getCSSValue('background-color')
		);

		// Check close button.
		$alarm_dialog->query('xpath:.//button[@title="Close"]')->one()->click()->waitUntilNotVisible();
	}

	/**
	 * Check that colors displayed in alarm notification overlay are the same as in configuration.
	 */
	public function testAlarmNotification_CheckColorChange() {
		// Trigger problem.
		self::$eventids = CDBHelper::setTriggerProblem(self::ALL_TRIGGERS);

		$severity_names = [
			'Disaster' => '00FF00',
			'High' => '00FF00',
			'Average' => '00FFFF',
			'Warning' => '00FFFF',
			'Information' => 'FF0080',
			'Not classified' => 'FF0080'
		];

		// Open Trigger displaying options page for color check and change.
		$this->page->login()->open('zabbix.php?action=trigdisplay.edit')->waitUntilReady();
		$form = $this->query('id:trigdisplay-form')->asForm()->one();

		// Find actual colors for all severity levels.
		$default_colors = [];
		foreach ($severity_names as $severity_name => $hexa_color) {
			$field = $form->getField($severity_name);
			$default_colors[] = $field->query(self::DEFAULT_COLORPICKER.'/button')->one()->getCSSValue('background-color');
		}

		// Compare colors in alarm and in form.
		$alarm_colors = $this->getAlarmColors();
		$this->assertEquals($default_colors, $alarm_colors);

		// Change color for every severity.
		foreach ($severity_names as $severity_name => $color) {
			$form->getField($severity_name)->query(self::DEFAULT_COLORPICKER)->asColorPicker()->one()->fill($color);
		}

		$form->submit();
		$this->page->waitUntilReady();
		$form->invalidate();

		$changed_colors = [];
		foreach ($severity_names as $severity_name => $color) {
			$field = $form->getField($severity_name);
			$changed_colors[] = $field->query(self::DEFAULT_COLORPICKER.'/button')->one()->getCSSValue('background-color');
		}

		// Compare colors in alarm and in form after change.
		$alarm_colors_changed = $this->getAlarmColors();
		$this->assertEquals($changed_colors, $alarm_colors_changed);

		// Check close button and close the problems.
		$alarm_dialog = $this->getAlarmOverlay();
		$alarm_dialog->query('xpath:.//button[@title="Close"]')->one()->click()->waitUntilNotVisible();
	}

	public static function getDisplayedProblemsData() {
		return [
			// #0 Not classified.
			[
				[
					'trigger_name' => ['Not_classified_trigger']
				]
			],
			// #1 Two problems at once.
			[
				[
					'trigger_name' => ['Not_classified_trigger_2', 'Not_classified_trigger_3']
				]
			],
			// #2 Information.
			[
				[
					'trigger_name' => ['Information_trigger']
				]
			],
			//#3 Warning.
			[
				[
					'trigger_name' => ['Warning_trigger']
				]
			],
			//#4 Average.
			[
				[
					'trigger_name' => ['Average_trigger']
				]
			],
			//#5 High.
			[
				[
					'trigger_name' => ['High_trigger']
				]
			],
			//#6 Disaster.
			[
				[
					'trigger_name' => ['Disaster_trigger']
				]
			],
			// #7 All together.
			[
				[
					'trigger_name' => [
						'Average_trigger',
						'Disaster_trigger',
						'High_trigger',
						'Information_trigger',
						'Not_classified_trigger',
						'Warning_trigger'
					]
				]
			],
			//#8 Multiple same error for one trigger.
			[
				[
					'trigger_name' => [
						'Disaster_trigger',
						'Disaster_trigger',
						'Disaster_trigger',
						'Disaster_trigger'
					]
				]
			]
		];
	}

	/**
	 * Check that correct problems displayed in alarm notification overlay.
	 *
	 * @dataProvider getDisplayedProblemsData
	 */
	public function testAlarmNotification_DisplayedProblems($data) {
		// Trigger problem.
		self::$eventids = CDBHelper::setTriggerProblem($data['trigger_name']);

		// Open problem page and filter with correct host.
		$this->page->login()->open('zabbix.php?action=problem.view&acknowledgement_status=1&sort=name&sortorder=ASC&hostids%5B%5D='.
				self::$hostid[self::HOST_NAME])->waitUntilReady();

		// Check that problems displayed in table.
		$this->assertTableDataColumn($data['trigger_name'], 'Problem');

		// Find appeared Alarm notification overlay dialog and check triggered problems by trigger name.
		$alarm_dialog = $this->getAlarmOverlay();
		$triggered_alarms = $alarm_dialog->query('xpath:.//ul[@class="notif-body"]/li//a[contains(@href, "triggerids")]')
				->all()->asText();
		sort($triggered_alarms);
		$this->assertEquals($data['trigger_name'], $triggered_alarms);

		// Check close button and close the problems.
		$alarm_dialog->query('xpath:.//button[@title="Close"]')->one()->click()->waitUntilNotVisible();
	}

	public static function getNotificationSettingsData() {
		return [
			// #0 Not classified turned off.
			[
				[
					'profile_setting' => ['Not classified' => false],
					'trigger_name' => [
						'Average_trigger',
						'Disaster_trigger',
						'High_trigger',
						'Information_trigger',
						'Warning_trigger'
					]
				]
			],
			// #1 Information turned off.
			[
				[
					'profile_setting' => ['Information' => false],
					'trigger_name' => [
						'Average_trigger',
						'Disaster_trigger',
						'High_trigger',
						'Not_classified_trigger',
						'Warning_trigger'
					]
				]
			],
			// #2 Warning turned off.
			[
				[
					'profile_setting' => ['Warning' => false],
					'trigger_name' => [
						'Average_trigger',
						'Disaster_trigger',
						'High_trigger',
						'Information_trigger',
						'Not_classified_trigger'
					]
				]
			],
			// #3 Average turned off.
			[
				[
					'profile_setting' => ['Average' => false],
					'trigger_name' => [
						'Disaster_trigger',
						'High_trigger',
						'Information_trigger',
						'Not_classified_trigger',
						'Warning_trigger'
					]
				]
			],
			// #4 High turned off.
			[
				[
					'profile_setting' => ['High' => false],
					'trigger_name' => [
						'Average_trigger',
						'Disaster_trigger',
						'Information_trigger',
						'Not_classified_trigger',
						'Warning_trigger'
					]
				]
			],
			// #5 Disaster turned off.
			[
				[
					'profile_setting' => ['Disaster' => false],
					'trigger_name' => [
						'Average_trigger',
						'High_trigger',
						'Information_trigger',
						'Not_classified_trigger',
						'Warning_trigger'
					]
				]
			],
			// #6 Not classified and High severities turned off.
			[
				[
					'profile_setting' => [
						'Not classified' => false,
						'High' => false
					],
					'trigger_name' => [
						'Average_trigger',
						'Disaster_trigger',
						'Information_trigger',
						'Warning_trigger'
					]
				]
			],
			// #7 Display suppressed problems.
			[
				[
					'profile_setting' => ['Show suppressed problems' => true],
					'suppressed_problem' => ['Suppressed_error'],
					'trigger_name' => ['Suppressed_error']
				]
			],
			// #8 Don't display suppressed problems.
			[
				[
					'profile_setting' => ['Show suppressed problems' => false],
					'suppressed_problem' => ['Suppressed_error'],
					'trigger_name' => ''
				]
			],
			// #9 All turned off.
			[
				[
					'profile_setting' => [
						'Not classified' => false,
						'Information' => false,
						'Warning' => false,
						'Average' => false,
						'High' => false,
						'Disaster' => false
					],
					'trigger_name' => ''
				]
			],
			// #10 Message notification turned off.
			[
				[
					'profile_setting' => ['Frontend notifications' => false],
					'trigger_name' => ''
				]
			]
		];
	}

	/**
	 * Check notification display after changing user Frontend notification settings.
	 *
	 * @onBefore resetTriggerSeverities
	 *
	 * @dataProvider getNotificationSettingsData
	 */
	public function testAlarmNotification_NotificationSettings($data) {
		// Set checked trigger severity in messaging settings.
		$this->page->login()->open('zabbix.php?action=userprofile.edit')->waitUntilReady();
		$form = $this->query('id:user-form')->asForm()->one();
		$form->selectTab('Frontend notifications');
		$form->fill($data['profile_setting']);
		$form->submit();
		$this->page->waitUntilReady();

		// Trigger problem.
		if (array_key_exists('suppressed_problem', $data)) {
			self::$eventids = CDBHelper::setTriggerProblem($data['suppressed_problem']);
			$time = time()+10000;

			// To check that suppressed notification can be visible after profile settings change.
			DBexecute('INSERT INTO event_suppress (event_suppressid, eventid, maintenanceid, suppress_until) VALUES '.
					'('.zbx_dbstr(self::$eventids[0]).', '.zbx_dbstr(self::$eventids[0]).', '.
					zbx_dbstr(self::$maintenanceid).', '.zbx_dbstr($time).')');
		}
		else {
			self::$eventids = CDBHelper::setTriggerProblem(self::ALL_TRIGGERS);
		}

		$this->page->login()->open('zabbix.php?action=problem.view&acknowledgement_status=1&show_suppressed=1&sort=name&sortorder=ASC&hostids%5B%5D='.
				self::$hostid[self::HOST_NAME].'&hostids%5B%5D='.self::$hostid['Host for maintenance alarm'])->waitUntilReady();

		// Check that problems displayed in table.
		$triggered_problems = (array_key_exists('suppressed_problem', $data)) ? $data['suppressed_problem'] : self::ALL_TRIGGERS;
		$this->assertTableDataColumn($triggered_problems, 'Problem');

		if ($data['trigger_name'] === '') {
			$this->assertFalse($this->query('xpath://div[@class="overlay-dialogue notif ui-draggable"]')->one()->isDisplayed());
		}
		else {
			// Find appeared Alarm notification overlay dialog and check triggered problems by trigger name.
			$alarm_dialog = $this->getAlarmOverlay();
			$triggered_alarms = $alarm_dialog->query('xpath:.//ul[@class="notif-body"]/li//a[contains(@href, "triggerids")]')
					->waitUntilVisible()->all()->asText();
			sort($triggered_alarms);
			$this->assertEquals($data['trigger_name'], $triggered_alarms);

			// Check close button.
			$alarm_dialog->query('xpath:.//button[@title="Close"]')->one()->click()->waitUntilNotVisible();
		}

		// Delete the events so they don't appear in the next test case.
		DB::delete('events', ['eventid' => self::$eventids]);
	}

	/**
	 * Update Frontend notifications settings, set all severities checkboxes => true.
	 */
	protected function resetTriggerSeverities() {
		// Delete old setting whatever it was.
		DBexecute('DELETE FROM profiles WHERE source='.zbx_dbstr('triggers.severities').' AND userid=1');

		// Insert new row where value_str field means that all severities are checked.
		DBexecute('INSERT INTO profiles (profileid, userid, idx, value_str, source, type)'.
				' VALUES (9950, 1, '.zbx_dbstr('web.messages').', '.
				zbx_dbstr('a:6:{i:0;s:1:"1";i:1;s:1:"1";i:2;s:1:"1";i:3;s:1:"1";i:4;s:1:"1";i:5;s:1:"1";}').', '.
				zbx_dbstr('triggers.severities').', 3)'
		);
	}

	/**
	 * Get color value from alarm notification overlay.
	 *
	 * @return array
	 */
	protected function getAlarmColors() {
		$notification_color_class = ['disaster-bg', 'high-bg', 'average-bg', 'warning-bg', 'info-bg', 'na-bg'];

		// Find appeared Alarm notification overlay dialog.
		$alarm_dialog = $this->getAlarmOverlay();

		// Get alarm color codes.
		$alarm_colors = [];
		foreach ($notification_color_class as $color_class) {
			$bg_color = $alarm_dialog->query('class', $color_class)->one()->getCSSValue('background-color');
			$alarm_colors[] = $bg_color;
		}

		return $alarm_colors;
	}

	protected function getAlarmOverlay() {
		return $this->query('xpath://div['.CXPathHelper::fromClass('overlay-dialogue notif').']')->waitUntilVisible()->one();
	}

	/**
	 * Acknowledge and close triggered problem.
	 */
	protected function closeAndAcknowledgeEvents() {
		CDataHelper::call('event.acknowledge', [
			'eventids' => self::$eventids,
			'action' => 3
		]);
	}

	/**
	 * Open problem page with filter reset.
	 */
	protected function openResetedPage() {
		$this->page->login()->open('zabbix.php?action=problem.view&filter_reset=1')->waitUntilReady();
	}
}
