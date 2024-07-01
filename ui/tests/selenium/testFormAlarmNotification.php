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


require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @backup events, problem, hosts, profiles
 *
 * @onBefore prepareAlarmData
 */
class testFormAlarmNotification extends CWebTest {

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

	protected static $hostid;

	/**
	 * Trigger names.
	 */
	protected $all_triggers = [
		'Average_trigger',
		'Disaster_trigger',
		'High_trigger',
		'Information_trigger',
		'Not_classified_trigger',
		'Warning_trigger'
	];

	public static function prepareAlarmData() {
		$response = CDataHelper::createHosts([
			[
				'host' => 'Host for alarm item',
				'groups' => [['groupid' => 4]], // Zabbix server
				'items' => [
					[
						'name' => 'Not classified',
						'key_' => 'not_classified',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					],
					[
						'name' => 'Information',
						'key_' => 'information',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					],
					[
						'name' => 'Warning',
						'key_' => 'warning',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					],
					[
						'name' => 'Average',
						'key_' => 'average',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					],
					[
						'name' => 'High',
						'key_' => 'high',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					],
					[
						'name' => 'Disaster',
						'key_' => 'disaster',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					],
					[
						'name' => 'Multiple errors',
						'key_' => 'multiple_errors',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					]
				]
			]
		]);
		self::$hostid = $response['hostids']['Host for alarm item'];

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
			]
		]);

		// Enable Alarm Notification display for user.
		DBexecute('INSERT INTO profiles (profileid, userid, idx, value_str, source, type)'.
				' VALUES (555,1,'.zbx_dbstr('web.messages').',1,'.zbx_dbstr('enabled').',3)');
	}

	/**
	 * Check Alarm notification overlay dialog layout.
	 */
	public function testFormAlarmNotification_Layout() {
		// Trigger problem.
		CDBHelper::setTriggerProblem('Not_classified_trigger_4');

		$this->page->login()->open('zabbix.php?action=problem.view')->waitUntilReady();
		$this->page->assertTitle('Problems');
		$this->page->assertHeader('Problems');

		// Find appeared Alarm notification overlay dialog.
		$alarm_dialog = $this->query('xpath://div[@class="overlay-dialogue notif ui-draggable"]')->asOverlayDialog()->
				waitUntilPresent()->one();

		// Check that Problem on text exists.
		$this->assertEquals('Problem on Host for alarm item', $alarm_dialog->query('xpath:.//h4')->one()->getText());

		// Check that link for host and trigger filtering works.
		foreach (['Hosts' => 'Host for alarm item', 'Triggers' => 'Not_classified_trigger_4'] as $field => $name) {
			$this->assertTrue($alarm_dialog->query('link', $name)->one()->isClickable());
			$alarm_dialog->query('link', $name)->one()->click();
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
		$alarm_dialog->query('xpath:.//a[contains(@href, "tr_events")]')->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertTitle('Event details');
		$this->page->assertHeader('Event details');

		// Check displayed icons.
		foreach (['Mute' => 'btn-sound', 'Snooze' => 'btn-alarm'] as $button => $class) {
			$selector = 'xpath:.//button[@title='.CXPathHelper::escapeQuotes($button).']';

			// Check that buttons exists and class says that button is ON.
			$this->assertTrue($alarm_dialog->query($selector)->exists());
			$this->assertEquals($class.'-on', $alarm_dialog->query($selector)->one()->getAttribute('class'));

			if ($button === 'Mute') {
				// After clicking on button it changes status to off and become Unmute.
				$alarm_dialog->query($selector)->one()->click();
				$alarm_dialog->query('xpath:.//button[@title="Unmute"]')->waitUntilVisible()->one();
				$this->assertEquals($class.'-off', $alarm_dialog->query('xpath:.//button[@title="Unmute"]')->
						one()->getAttribute('class')
				);

				// Check that after clicking on Unmute button, Mute icon changed back.
				$alarm_dialog->query('xpath:.//button[@title="Unmute"]')->one()->click();
				$alarm_dialog->query($selector)->waitUntilVisible()->one();
				$this->assertEquals($class.'-on', $alarm_dialog->query($selector)->one()->getAttribute('class'));
			}
			else {
				// Check that after clicking second time on already Snoozed button, it doesn't change status.
				for ($i = 0; $i <=1; $i++) {
					$alarm_dialog->query($selector)->one()->click();
					$this->assertTrue($alarm_dialog->query($selector)->exists());
					$this->assertEquals($class.'-off', $alarm_dialog->query($selector)->one()->getAttribute('class'));
				}
			}
		}

		$this->page->open('zabbix.php?action=problem.view')->waitUntilReady();

		// Close problem.
		CDBHelper::setTriggerProblem('Not_classified_trigger_4', TRIGGER_VALUE_FALSE);
		sleep(1);
		$this->page->refresh()->waitUntilReady();

		// Check that problem resolved and problem color is green now.
		$this->assertEquals('Resolved Host for alarm item', $alarm_dialog->query('xpath:.//h4')->one()->getText());
		$this->assertEquals('rgba(89, 219, 143, 1)', $alarm_dialog->query('xpath:.//div[contains(@class, '.
				CXPathHelper::escapeQuotes('notif-indic normal-bg').')]')->one()->getCSSValue('background-color')
		);

		// Check close button.
		$alarm_dialog->query('xpath:.//button[@title="Close"]')->one()->click();
		$alarm_dialog->ensureNotPresent();
	}

	/**
	 * Check that colors displayed in alarm notification overlay are the same as in configuration.
	 */
	public function testFormAlarmNotification_checkColorChange() {
		$severity_names = [
			'Disaster' => '00FF00',
			'High' => '00FF00',
			'Average' => '00FFFF',
			'Warning' => '00FFFF',
			'Information' => 'FF0080',
			'Not classified' => 'FF0080'
		];

		$this->page->login()->open('zabbix.php?action=problem.view&unacknowledged=1&sort=name&sortorder=ASC&hostids%5B%5D='.
				self::$hostid)->waitUntilReady();

		// In case some scenarios failed and problems didn't closed at the end.
		if ($this->query('class:list-table')->asTable()->one()->getRows()->asText() !== ['No data found.']) {
			$this->closeProblem();
		}

		// Trigger problem.
		foreach ($this->all_triggers as $trigger_name) {
			CDBHelper::setTriggerProblem($trigger_name);
		}

		// Open Trigger displaying options page for color check and change.
		$this->page->open('zabbix.php?action=trigdisplay.edit')->waitUntilReady();
		$form = $this->query('id:trigdisplay-form')->asForm()->one();

		// Find actual colors for all severity levels. They are in HEXA format.
		$default_colors = [];
		foreach ($severity_names as $severity_name => $hexa_color) {
			$field = $form->getField($severity_name);
			$color_value = $field->query('xpath:./following::div[@class="color-picker"]')->asColorPicker()->one()->getText();
			$default_colors[] = '#'.$color_value;
		}

		// Refresh page for alarm overlay to appear.
		$this->page->refresh()->waitUntilReady();
		$form->invalidate();

		// Compare colors in alarm and in form.
		$hexa_alarm_colors = $this->getAlarmColorsAndConvert();
		$this->assertEquals($default_colors, $hexa_alarm_colors);

		// Change color for every severity.
		$changed_colors = [];
		foreach ($severity_names as $severity_name => $hexa_color) {
			$field = $form->getField($severity_name);
			$field->query('xpath:./following::div[@class="color-picker"]')->asColorPicker()->one()->fill($hexa_color);
			$changed_colors[] = '#'.$hexa_color;
		}

		$form->submit();
		$this->page->waitUntilReady();

		// Compare colors in alarm and in form after change.
		$hexa_alarm_colors_changed = $this->getAlarmColorsAndConvert();
		$this->assertEquals($changed_colors, $hexa_alarm_colors_changed);

		// Navigate to problem page for problems closing.
		$this->page->open('zabbix.php?action=problem.view&unacknowledged=1&sort=name&sortorder=ASC&hostids%5B%5D='.
				self::$hostid)->waitUntilReady();

		if ($this->query('class:list-table')->asTable()->one()->getRows()->asText() !== ['No data found.']) {
			$this->closeProblem();
		}
	}

	public static function getDisplayedAlarmsData() {
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
					'trigger_name' => [
						'Not_classified_trigger_2',
						'Not_classified_trigger_3'
					]
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
						'Warning_trigger',
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
					],
					'multiple_check' => true
				]
			]
		];
	}

	/**
	 * Check that alarms displayed in alarm notification overlay.
	 *
	 * @dataProvider getDisplayedAlarmsData
	 */
	public function testFormAlarmNotification_DisplayedAlarms($data) {
		$this->page->login()->open('zabbix.php?action=problem.view&filter_reset=1')->waitUntilReady();
		$this->page->open('zabbix.php?action=problem.view&unacknowledged=1&sort=name&sortorder=ASC&hostids%5B%5D='.
				self::$hostid)->waitUntilReady();

		// In case some scenarios failed and problems didn't closed at the end.
		if ($this->query('class:list-table')->asTable()->one()->getRows()->asText() !== ['No data found.']) {
			$this->closeProblem();
		}

		// Trigger problem.
		foreach ($data['trigger_name'] as $trigger_name) {
			CDBHelper::setTriggerProblem($trigger_name);
		}

		// Filter problems by Hosts and refresh page for alarm overlay to appear.
		$this->query('name:zbx_filter')->asForm()->one()->fill(['Hosts' => 'Host for alarm item'])->submit();
		$this->query('class:list-table')->asTable()->one()->waitUntilReloaded();
		$this->page->refresh()->waitUntilReady();

		// Check that problems displayed in table.
		$this->assertTableDataColumn($data['trigger_name'], 'Problem');

		// Find appeared Alarm notification overlay dialog.
		$alarm_dialog = $this->query('xpath://div[@class="overlay-dialogue notif ui-draggable"]')->asOverlayDialog()->
				waitUntilPresent()->one();

		// Multiple problems for one trigger or one problem for one trigger.
		if (CTestArrayHelper::get($data, 'multiple_check', false)) {
			for ($i = 1; $i <= 4; $i++) {
				$this->assertTrue($alarm_dialog->query('xpath:(//p/a[text()="Disaster_trigger"])['.$i.']')->one()->isClickable());
			}
		} else {
			foreach ($data['trigger_name'] as $trigger_name) {
				$this->assertTrue($alarm_dialog->query('link', $trigger_name)->one()->isClickable());
			}
		}

		// Check close button and close the problems.
		$alarm_dialog->query('xpath:.//button[@title="Close"]')->one()->click();
		$alarm_dialog->ensureNotPresent();
		$this->closeProblem();
	}

	public static function getNotDisplayedAlarmsData(){
		return [
			// #0 Not classified turned off.
			[
				[
					'severity_status' => ['Not classified' => false],
					'trigger_name' => ['Not_classified_trigger']
				]
			],
			// #1 Information turned off.
			[
				[
					'severity_status' => ['Information' => false],
					'trigger_name' => ['Information_trigger']
				]
			],
			// #2 Warning turned off.
			[
				[
					'severity_status' => ['Warning' => false],
					'trigger_name' => ['Warning_trigger']
				]
			],
			// #3 Average turned off.
			[
				[
					'severity_status' => ['Average' => false],
					'trigger_name' => ['Average_trigger']
				]
			],
			// #4 High turned off.
			[
				[
					'severity_status' => ['High' => false],
					'trigger_name' => ['High_trigger']
				]
			],
			// #5 Disaster turned off.
			[
				[
					'severity_status' => ['Disaster' => false],
					'trigger_name' => ['Disaster_trigger']
				]
			],
			// #6 All turned off.
			[
				[
					'severity_status' => [
						'Not classified' => false,
						'Information' => false,
						'Warning' => false,
						'Average' => false,
						'High' => false,
						'Disaster' => false
					],
					'trigger_name' => [
						'Not_classified_trigger',
						'Information_trigger',
						'Warning_trigger',
						'Average_trigger',
						'High_trigger',
						'Disaster_trigger'
					],
					'all_off' => true
				]
			],
			// #7 Not classified and High severities turned off.
			[
				[
					'severity_status' => [
						'Not classified' => false,
						'High' => false
					],
					'trigger_name' => [
						'Not_classified_trigger',
						'High_trigger'
					]
				]
			]
		];
	}

	/**
	 * Check that turning off alarms for severity, they are not displayed in alarm notification overlay.
	 *
	 * @dataProvider getNotDisplayedAlarmsData
	 */
	public function testFormAlarmNotification_NotDisplayedAlarms($data) {
		$this->updateSeverity($data['severity_status']);
		$this->page->open('zabbix.php?action=problem.view&filter_reset=1')->waitUntilReady();
		$this->page->open('zabbix.php?action=problem.view&unacknowledged=1&sort=name&sortorder=ASC&hostids%5B%5D='.
				self::$hostid)->waitUntilReady();

		// In case some scenarios failed and problems didn't closed at the end.
		if ($this->query('class:list-table')->asTable()->one()->getRows()->asText() !== ['No data found.']) {
			$this->closeProblem();
		}

		// Trigger problem.
		foreach ($this->all_triggers as $trigger_name) {
			CDBHelper::setTriggerProblem($trigger_name);
		}

		// Filter problems by Hosts and refresh page for alarm overlay to appear.
		$this->query('name:zbx_filter')->asForm()->one()->fill(['Hosts' => 'Host for alarm item'])->submit();
		$this->query('class:list-table')->asTable()->one()->waitUntilReloaded();
		$this->page->refresh()->waitUntilReady();

		// Check that problems displayed in table.
		$this->assertTableDataColumn($this->all_triggers, 'Problem');

		if (CTestArrayHelper::get($data, 'all_off', false)) {
			$this->assertFalse($this->query('xpath://div[@class="overlay-dialogue notif ui-draggable"]')->one()->isDisplayed());
		}
		else {
			// Create new trigger name array without turned off severity.
			$new_severity = array_diff($this->all_triggers, $data['trigger_name']);

			// Find appeared Alarm notification overlay dialog.
			$alarm_dialog = $this->query('xpath://div[@class="overlay-dialogue notif ui-draggable"]')->asOverlayDialog()->
					waitUntilPresent()->one();

			foreach ($new_severity as $trigger_name) {
				$this->assertTrue($alarm_dialog->query('link', $trigger_name)->one()->isClickable());
			}

			foreach ($data['trigger_name'] as $trigger_name) {
				$this->assertFalse($alarm_dialog->query('link', $trigger_name)->exists());
			}

			// Check close button.
			$alarm_dialog->query('xpath:.//button[@title="Close"]')->one()->click();
			$alarm_dialog->ensureNotPresent();
		}

		$this->closeProblem();
	}

	/**
	 * Update Problems severity display from user profile page (disable/enable).
	 */
	protected function updateSeverity($parameters = null) {
		$all_severity = [
			'Not classified' => true,
			'Information' => true,
			'Warning' => true,
			'Average' => true,
			'High' => true,
			'Disaster' => true
		];

		$this->page->login()->open('zabbix.php?action=userprofile.edit')->waitUntilReady();
		$form = $this->query('id:user-form')->asForm()->one();
		$form->selectTab('Messaging');
		$form->fill($all_severity);

		if ($parameters !== null) {
			$form->fill($parameters);
		}

		$form->submit();
		$this->page->waitUntilReady();
	}

	/**
	 * Manually close problem from Problems page.
	 */
	protected function closeProblem() {
		$this->selectTableRows();
		$this->query('button:Mass update')->one()->click();

		// Find appeared Alarm notification overlay dialog.
		$problem_form = COverlayDialogElement::find()->waitUntilReady()->one()->asForm();
		$problem_form->fill(['Close problem' => true, 'Acknowledge' => true])->submit();

		COverlayDialogElement::ensureNotPresent();
	}

	/**
	 * Get color value from alarm notification overlay. Values collected as array in RGBA format. After collecting
	 * values convert them to HEX and save again as array.
	 *
	 * @return array
	 */
	protected function getAlarmColorsAndConvert() {
		$notification_color_class = [
			'disaster-bg',
			'high-bg',
			'average-bg',
			'warning-bg',
			'info-bg',
			'na-bg'
		];

		// Find appeared Alarm notification overlay dialog.
		$alarm_dialog = $this->query('xpath://div[@class="overlay-dialogue notif ui-draggable"]')->asOverlayDialog()->
				waitUntilPresent()->one();

		// Get alarm color codes. It will be in RGBA format.
		$rgba_alarm_colors = [];
		foreach ($notification_color_class as $color_class) {
			$bg_color = $alarm_dialog->query('xpath:.//div[contains(@class, '.CXPathHelper::escapeQuotes($color_class).')]')->
					one()->getCSSValue('background-color');
			$rgba_alarm_colors[] = $bg_color;
		}

		// Convert RGBA colors to hexa
		$hexa_alarm_colors = [];
		foreach ($rgba_alarm_colors as $rgba_color) {
			if (preg_match('/^rgba?\((\d+),[ ]*(\d+),[ ]*(\d+)[, )]+/', $rgba_color, $matches) === 1) {
				$rgba_color = sprintf('#%02X%02X%02X', $matches[1], $matches[2], $matches[3]);
			}

			$hexa_alarm_colors[] = $rgba_color;
		}

		return $hexa_alarm_colors;
	}
}
