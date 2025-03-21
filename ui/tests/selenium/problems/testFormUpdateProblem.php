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
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * @backup hosts
 *
 * @onBefore prepareProblemsData
 */
class testFormUpdateProblem extends CWebTest {

	/**
	 * Id of the host with problems.
	 *
	 * @var integer
	 */
	protected static $hostid;

	/**
	 * Time when acknowledge was created.
	 *
	 * @var string
	 */
	protected static $acktime;

	/**
	 * Eventid for problem suppression.
	 *
	 * @var integer
	 */
	protected static $eventid_for_icon_test;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Get all events related tables hash values.
	 */
	public static function getHash() {
		return CDBHelper::getHash('SELECT * FROM events').
				CDBHelper::getHash('SELECT * FROM problem').
				CDBHelper::getHash('SELECT * FROM triggers').
				CDBHelper::getHash('SELECT * FROM acknowledges').
				CDBHelper::getHash('SELECT * FROM event_suppress');
	}

	public function prepareProblemsData() {
		// Create hostgroup for hosts with items triggers.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for Problems Update']]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		$groupid = $hostgroups['groupids'][0];

		// Create host for items and triggers.
		$hosts = CDataHelper::call('host.create', [
			'host' => 'Host for Problems Update',
			'groups' => [['groupid' => $groupid]]
		]);
		$this->assertArrayHasKey('hostids', $hosts);
		self::$hostid = $hosts['hostids'][0];

		// Create items on previously created host.
		$item_names = ['float', 'char', 'log', 'unsigned', 'text'];

		$items_data = [];
		foreach ($item_names as $i => $item) {
			$items_data[] = [
				'hostid' => self::$hostid,
				'name' => $item,
				'key_' => $item,
				'type' => 2,
				'value_type' => $i
			];
		}

		$items = CDataHelper::call('item.create', $items_data);
		$this->assertArrayHasKey('itemids', $items);

		// Create triggers based on items.
		$triggers = CDataHelper::call('trigger.create', [
			[
				'description' => 'Trigger for float',
				'expression' => 'last(/Host for Problems Update/float)=0',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			],
			[
				'description' => 'Trigger for char',
				'expression' => 'last(/Host for Problems Update/char)=0',
				'priority' => TRIGGER_SEVERITY_INFORMATION,
				'manual_close' => 1
			],
			[
				'description' => 'Trigger for log',
				'expression' => 'last(/Host for Problems Update/log)=0',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Trigger for unsigned',
				'expression' => 'last(/Host for Problems Update/unsigned)=0',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'Trigger for text',
				'expression' => 'last(/Host for Problems Update/text)=0',
				'priority' => TRIGGER_SEVERITY_HIGH
			],
			[
				'description' => 'Trigger for icon test',
				'expression' => 'last(/Host for Problems Update/log)=0',
				'priority' => TRIGGER_SEVERITY_DISASTER
			]
		]);
		$this->assertArrayHasKey('triggerids', $triggers);

		// Create problems and events.
		$time = time();
		foreach (CDataHelper::getIds('description') as $name => $id) {
			CDBHelper::setTriggerProblem($name, TRIGGER_VALUE_TRUE, $time);
		}

		DBexecute('UPDATE triggers SET value=1, manual_close=1 WHERE description='.zbx_dbstr('Trigger for char'));

		$eventids = [];
		foreach (['Trigger for text', 'Trigger for unsigned', 'Trigger for icon test'] as $event_name) {
			$eventids[$event_name] = CDBHelper::getValue('SELECT eventid FROM events WHERE name='.zbx_dbstr($event_name));
		}

		self::$eventid_for_icon_test = $eventids['Trigger for icon test'];

		// Suppress the problem: 'Trigger for text'.
		DBexecute('INSERT INTO event_suppress (event_suppressid, eventid, maintenanceid, suppress_until) VALUES (10050, '.
				$eventids['Trigger for text'].', NULL, 0)'
		);

		// Acknowledge the problem: 'Trigger for unsigned' and get acknowledge time.
		CDataHelper::call('event.acknowledge', [
			'eventids' => $eventids['Trigger for unsigned'],
			'action' => 6,
			'message' => 'Acknowledged event'
		]);

		$event = CDataHelper::call('event.get', [
			'eventids' => $eventids['Trigger for unsigned'],
			'selectAcknowledges' => ['clock']
		]);
		self::$acktime = CTestArrayHelper::get($event, '0.acknowledges.0.clock');
	}

	public function getLayoutData() {
		return [
			[
				[
					'problems' => ['Trigger for float'],
					'hintboxes' => [
						'Suppress' => 'Manual problem suppression. Date-time input accepts relative and absolute time format.',
						'Unsuppress' => 'Deactivates manual suppression.',
						'Acknowledge' => 'Confirms the problem is noticed (acknowledging user will be recorded). '.
								'Status change triggers action update operation.',
						'Convert to cause' => 'Converts a symptom event back to cause event'
					],
					'history' => [],
					'Acknowledge' => true,
					'check_suppress' => true
				]
			],
			[
				[
					'problems' => ['Trigger for char'],
					'close_enabled' => true,
					'history' => [],
					'Acknowledge' => true
				]
			],
			[
				[
					'problems' => ['Trigger for text'],
					'unsuppress_enabled' => true,
					'history' => [],
					'Acknowledge' => true
				]
			],
			[
				[
					'problems' => ['Trigger for unsigned'],
					// If problem is Acknowledged - label is changed to Unacknowledge.
					'labels' => ['Problem', 'Message', 'History', 'Scope', 'Change severity', 'Suppress',
						'Unsuppress', 'Unacknowledge', 'Convert to cause', 'Close problem', ''
					],
					'message' => 'Acknowledged event',
					'Unacknowledge' => true,
					'history' => [" Admin (Zabbix Administrator)".
							"\nAcknowledged event"
					],
					'hintboxes' => [
						'Suppress' => 'Manual problem suppression. Date-time input accepts relative and absolute time format.',
						'Unsuppress' => 'Deactivates manual suppression.',
						'Unacknowledge' => 'Undo problem acknowledgement.',
						'Convert to cause' => 'Converts a symptom event back to cause event'
					]
				]
			],
			[
				[
					'problems' => ['Trigger for log'],
					'history' => [],
					'Acknowledge' => true
				]
			],
			// Two problems.
			[
				[
					'problems' => ['Trigger for float', 'Trigger for char'],
					// If more than one problems selected - History label is absent.
					'labels' => ['Problem', 'Message', 'Scope', 'Change severity', 'Suppress', 'Unsuppress',
						'Acknowledge', 'Convert to cause', 'Close problem', ''
					],
					'close_enabled' => true,
					'Acknowledge' => true,
					'hintboxes' => [
						'Suppress' => 'Manual problem suppression. Date-time input accepts relative and absolute time format.',
						'Unsuppress' => 'Deactivates manual suppression.',
						'Acknowledge' => 'Confirms the problem is noticed (acknowledging user will be recorded). '.
								'Status change triggers action update operation.',
						'Convert to cause' => 'Converts a symptom event back to cause event'
					]
				]
			],
			// Five problems.
			[
				[
					'problems' => ['Trigger for float', 'Trigger for char', 'Trigger for log', 'Trigger for unsigned', 'Trigger for text'],
					// If more than one problem selected - History label is absent.
					'labels' => ['Problem', 'Message', 'Scope', 'Change severity', 'Suppress', 'Unsuppress',
						'Acknowledge', 'Unacknowledge', 'Convert to cause', 'Close problem', ''
					],
					'hintboxes' => [
						'Suppress' => 'Manual problem suppression. Date-time input accepts relative and absolute time format.',
						'Unsuppress' => 'Deactivates manual suppression.',
						'Acknowledge' => 'Confirms the problem is noticed (acknowledging user will be recorded). '.
								'Status change triggers action update operation.',
						'Unacknowledge' => 'Undo problem acknowledgement.',
						'Convert to cause' => 'Converts a symptom event back to cause event'
					],
					'close_enabled' => true,
					'unsuppress_enabled' => true,
					'Acknowledge' => true,
					'Unacknowledge' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testFormUpdateProblem_Layout($data) {
		// Open filtered Problems list.
		$this->page->login()->open('zabbix.php?&action=problem.view&filter_set=1&show_suppressed=1&hostids%5B%5D='.self::$hostid)->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();
		$table->findRows('Problem', $data['problems'])->select();
		$this->query('button:Mass update')->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Update problem', $dialog->getTitle());
		$form = $dialog->query('id:acknowledge_form')->asForm()->one();

		// Check form labels.
		$count = count($data['problems']);
		$default_labels = ['Problem', 'Message', 'History', 'Scope', 'Change severity', 'Suppress', 'Unsuppress',
				'Acknowledge', 'Convert to cause', 'Close problem', ''];
		$this->assertEquals(CTestArrayHelper::get($data, 'labels', $default_labels), $form->getLabels()->asText());

		// Check "Problem" field value.
		$problem = $count > 1 ? $count.' problems selected.' : $data['problems'][0];
		$this->assertTrue($form->query('xpath:.//div[@class="wordbreak" and text()='.
				CXPathHelper::escapeQuotes($problem).']')->exists()
		);

		// Check first label in Scope field.
		$scope_field = $form->getField('Scope');
		$scope_label_query = $count > 1
			? 'xpath:.//label[text()="Only selected problems"]/sup[text()='.CXPathHelper::escapeQuotes($count.' events').']'
			: 'xpath:.//label[text()="Only selected problem"]';
		$this->assertTrue($scope_field->query($scope_label_query)->exists());

		// Check second label in Scope field.
		$this->assertTrue($scope_field->query("xpath:.//label[text()=".
				"\"Selected and all other problems of related triggers\"]/sup[text()=".
				CXPathHelper::escapeQuotes($count > 1 ? $count.' events' : '1 event')."]")->exists()
		);

		// Check Hintboxes.
		if (CTestArrayHelper::get($data, 'hintboxes')) {
			foreach ($data['hintboxes'] as $field => $text) {
				$form->getLabel($field)->query('xpath:./button[@data-hintbox]')->one()->click();
				$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilPresent()->one();
				$this->assertEquals($text, $hint->getText());
				$hint->query('class:btn-overlay-close')->waitUntilClickable()->one()->click();
			}
		}

		// Check History field.
		if (array_key_exists('history', $data)) {
			$history = ($data['history'] === []) ? $data['history'] : [date('Y-m-d H:i:s', self::$acktime).$data['history'][0]];
			$history_table = $form->getField('History')->asTable();
			$this->assertEquals(['Time', 'User', 'User action', 'Message'], $history_table->getHeadersText());
			$this->assertEquals($history, $history_table->getRows()->asText());

			if ($data['problems'] === ['Trigger for unsigned']) {
				foreach (['Acknowledged', 'Message'] as $icon) {
					$this->assertTrue($history_table->query("xpath:.//span[@class=".CXPathHelper::fromClass('icon-action').
							" and @title=".CXPathHelper::escapeQuotes($icon)."]")->exists()
					);
				}
			}
		}

		// Check fields' default values and attributes.
		$fields = [
			'id:message' => ['value' => '', 'maxlength' => 2048, 'enabled' => true],
			'id:scope_0' => ['value' => true, 'enabled' => true],    // Only selected problem.
			'id:scope_1' => ['value' => false, 'enabled' => true],   // Selected and all other problems of related triggers.
			'id:change_severity' => ['value' => false, 'enabled' => true],
			'id:severity' => ['value' => 'Not classified', 'enabled' => false],
			'id:suppress_problem' => ['value' => false, 'enabled' => true],
			'id:suppress_time_option' => ['value' => 'Until', 'enabled' => false],
			'id:suppress_until_problem' => ['maxlength' => 255, 'value' => 'now+1d', 'enabled' => false, 'placeholder' => 'now+1d'],
			'id:unsuppress_problem' => ['value' => false, 'enabled' => CTestArrayHelper::get($data, 'unsuppress_enabled', false)],
			'id:change_rank' => ['value' => false, 'enabled' => false],
			'Close problem' => ['value' => false, 'enabled' => CTestArrayHelper::get($data, 'close_enabled', false)]
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

		// Check default values for 'Acknowledge' and  'Unacknowledge' fields.
		foreach (['Acknowledge', 'Unacknowledge'] as $label) {
			if (array_key_exists($label, $data)) {
				$field = $form->getField($label);
				$this->assertEquals(false, $field->getValue());
				$this->assertTrue($field->isEnabled());
			}
		}

		// Check Suppress and Unsuppress checkboxes dependency.
		if (CTestArrayHelper::get($data, 'unsuppress_enabled')) {
			$suppress_combinations = [
				['id:suppress_problem', 'id:unsuppress_problem'],
				['id:unsuppress_problem', 'id:suppress_problem']
			];

			foreach ($suppress_combinations as $checkboxes) {
				foreach ([true, false] as $state) {
					$form->fill([$checkboxes[0] => $state]);
					$this->assertTrue($form->getField($checkboxes[1])->isEnabled(!$state));
				}
			}
		}

		// Check other buttons in overlay.
		$button_queries = [
			// Button ? (help) is covered in testDocumentationLinks.
			'xpath:.//button[@title="Close"]' => true,
			'xpath:.//button[@id="suppress_until_problem_calendar"]' => false,
			'button:Update' => true,
			'button:Cancel' => true
		];

		foreach ($button_queries as $query => $clickable) {
			$this->assertEquals($clickable, $dialog->query($query)->one()->isClickable());
		}

		// Check Suppress field.
		if (CTestArrayHelper::get($data, 'check_suppress')) {
			$form->fill(['id:suppress_problem' => true]);

			// Check Until field is enabled.
			$this->assertTrue($form->getField('id:suppress_time_option')->isEnabled());
			$this->assertTrue($form->getField('id:suppress_until_problem')->isEnabled());

			// Check calendar.
			$calendar = $form->query('xpath:.//button[@id="suppress_until_problem_calendar"]')->one();
			$calendar->waitUntilClickable()->click();
			$calendar_overlay = $this->query('xpath://div[@aria-label="Calendar"]');
			$this->assertTrue($calendar_overlay->exists());
			$calendar->click();
			$this->assertFalse($calendar_overlay->exists());

			// Check Until field is disabled.
			$form->fill(['id:suppress_time_option' => 'Indefinitely']);
			$this->assertFalse($form->getField('id:suppress_until_problem')->isEnabled());
			$this->assertEquals(false, $calendar->isClickable());
		}

		// Check Suppress/Unsuppress fields depending on Close problem checkbox.
		if (CTestArrayHelper::get($data, 'close_enabled') && CTestArrayHelper::get($data, 'unsuppress_enabled')) {
			foreach ([true, false] as $state) {
				$form->fill(['id:close_problem' => $state]);
				$this->assertFalse($form->getField('id:suppress_problem')->isEnabled($state));
				$this->assertFalse($form->getField('id:unsuppress_problem')->isEnabled($state));
			}
		}

		// Check asterisk text.
		$this->assertTrue($form->query('xpath:.//label[@class="form-label-asterisk" and '.
				'text()="At least one update operation or message must exist."]')->exists()
		);
		$dialog->close();
	}

	public function getFormData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'problems' => ['Trigger for float'],
					'fields' => [
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => '2020-08-01 00:00:00'
					],
					'error' => 'Incorrect value for field "Suppress": invalid time.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'problems' => ['Trigger for float', 'Trigger for log'],
					'fields' => [
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => '2040-08-01'
					],
					'error' => 'Incorrect value for field "Suppress": invalid time.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'problems' => ['Trigger for text', 'Trigger for log'],
					'fields' => [
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => '2040-08-01 00:00:00'
					],
					'error' => 'Incorrect value for field "Suppress": invalid time.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'problems' => ['Trigger for text'],
					'fields' => [
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => '00:00:00'
					],
					'error' => 'Incorrect value for field "suppress_until_problem": a time is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'problems' => ['Trigger for text'],
					'fields' => [
						'id:message' => 'not showing message 😾',
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => 'now+16y'
					],
					'error' => 'Incorrect value for field "Suppress": invalid time.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'problems' => ['Trigger for text'],
					'fields' => [
						'id:message' => 'not showing message',
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => 'now-1d'
					],
					'error' => 'Incorrect value for field "Suppress": invalid time.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'problems' => ['Trigger for text'],
					'fields' => [
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => '-3d'
					],
					'error' => 'Incorrect value for field "suppress_until_problem": a time is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'problems' => ['Trigger for char'],
					'fields' => [
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => 'text'
					],
					'error' => 'Incorrect value for field "suppress_until_problem": a time is expected.'
				]
			],
			[
				[
					'problems' => ['Trigger for log', 'Trigger for char', 'Trigger for float'],
					'fields' => [
						'id:scope_1' => true,
						'id:change_severity' => true,
						'id:severity' => 'Information',
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => 'now+2h'
					],
					'db_check' => [
						[
							'name' => 'Trigger for log',
							'db_fields' => ['message' => '', 'action' => 40, 'new_severity' => 1, 'suppress_until' => true]
						],
						[
							'name' => 'Trigger for char',
							'db_fields' => ['message' => '', 'action' => 32, 'new_severity' => 0, 'suppress_until' => true]
						],
						[
							'name' => 'Trigger for float',
							'db_fields' => ['message' => '', 'action' => 40, 'new_severity' => 1, 'suppress_until' => true]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for float'],
					'fields' => [
						'id:message' => 'test message text',
						'id:change_severity' => true,
						'id:severity' => 'Warning',
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Indefinitely',
						'Acknowledge' => true
					],
					'db_check' => [
						[
							'name' => 'Trigger for float',
							'db_fields' => ['message' => 'test message text', 'action' => 46, 'new_severity' => 2, 'suppress_until' => 0]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for text', 'Trigger for log'],
					'fields' => [
						'id:change_severity' => true,
						'id:severity' => 'Not classified',
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => 'now+30s'
					],
					'db_check' => [
						[
							'name' => 'Trigger for text',
							'db_fields' => ['message' => '', 'action' => 40, 'new_severity' => 0, 'suppress_until' => true]
						],
						[
							'name' => 'Trigger for text',
							'db_fields' => ['message' => '', 'action' => 40, 'new_severity' => 0, 'suppress_until' => true]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for char'],
					'fields' => [
						'id:change_severity' => true,
						'id:severity' => 'Average',
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => 'now+3d'
					],
					'db_check' => [
						[
							'name' => 'Trigger for char',
							'db_fields' => ['message' => '', 'action' => 40, 'new_severity' => 3, 'suppress_until' => true]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for unsigned'],
					'fields' => [
						'id:message' => '😻 😻 😻',
						'id:change_severity' => true,
						'id:severity' => 'High',
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => 'now+5y'
					],
					'db_check' => [
						[
							'name' => 'Trigger for unsigned',
							'db_fields' => ['message' => '😻 😻 😻', 'action' => 44, 'new_severity' => 4, 'suppress_until' => true]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for log'],
					'fields' => [
						'id:change_severity' => true,
						'id:severity' => 'Disaster',
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => 'now+18w'
					],
					'db_check' => [
						[
							'name' => 'Trigger for log',
							'db_fields' => ['message' => '', 'action' => 40, 'new_severity' => 5, 'suppress_until' => true]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for text', 'Trigger for log'],
					'fields' => [
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => 'now+9M'
					],
					'db_check' => [
						[
							'name' => 'Trigger for text',
							'db_fields' => ['message' => '', 'action' => 32, 'new_severity' => 0, 'suppress_until' => true]
						],
						[
							'name' => 'Trigger for log',
							'db_fields' => ['message' => '', 'action' => 32, 'new_severity' => 0, 'suppress_until' => true]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for log', 'Trigger for char', 'Trigger for float', 'Trigger for text', 'Trigger for unsigned'],
					'fields' => [
						'id:message' => 'Update all 5 problems',
						'id:change_severity' => true,
						'id:severity' => 'High',
						'id:suppress_problem' => true,
						'id:suppress_time_option' => 'Until',
						'id:suppress_until_problem' => 'now+2h'
					],
					'db_check' => [
						[
							'name' => 'Trigger for log',
							'db_fields' => ['message' => 'Update all 5 problems', 'action' => 44, 'new_severity' => 4, 'suppress_until' => true]
						],
						[
							'name' => 'Trigger for char',
							'db_fields' => ['message' => 'Update all 5 problems', 'action' => 44, 'new_severity' => 4, 'suppress_until' => true]
						],
						[
							'name' => 'Trigger for float',
							'db_fields' => ['message' => 'Update all 5 problems', 'action' => 44, 'new_severity' => 4, 'suppress_until' => true]
						],
						[
							'name' => 'Trigger for text',
							'db_fields' => ['message' => 'Update all 5 problems', 'action' => 44, 'new_severity' => 4, 'suppress_until' => true]
						],
						[
							'name' => 'Trigger for unsigned',
							'db_fields' => ['message' => 'Update all 5 problems', 'action' => 36, 'new_severity' => 0, 'suppress_until' => true]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for text'],
					'fields' => [
						'id:unsuppress_problem' => true
					],
					'db_check' => [
						[
							'name' => 'Trigger for text',
							'db_fields' => ['message' => '', 'action' => 64, 'new_severity' => 0, 'suppress_until' => 0]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for unsigned'],
					'fields' => [
						'Unacknowledge' => true
					],
					'db_check' => [
						[
							'name' => 'Trigger for unsigned',
							'db_fields' => ['message' => '', 'action' => 16, 'new_severity' => 0, 'suppress_until' => 0]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for char'],
					'fields' => [
						'Close problem' => true
					],
					'db_check' => [
						[
							'name' => 'Trigger for char',
							'db_fields' => ['message' => '', 'action' => 1, 'new_severity' => 0, 'suppress_until' => 0]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFormData
	 */
	public function testFormUpdateProblem_Form($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = $this->getHash();
		}

		// Open filtered Problems list.
		$this->page->login()->open('zabbix.php?&action=problem.view&show_suppressed=1&hostids%5B%5D='.self::$hostid)->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		$count = count($data['problems']);
		$table->findRows('Problem', $data['problems']);

		if ($count > 1) {
			$table->findRows('Problem', $data['problems'])->select();
			$this->query('button:Mass update')->waitUntilClickable()->one()->click();
		}
		else {
			$table->findRow('Problem', $data['problems'][0])->getColumn('Update')->query('tag:a')->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->query('id:acknowledge_form')->asForm()->one();
		$form->fill($data['fields']);
		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertTrue($dialog->isVisible());
			$this->assertMessage(TEST_BAD, null, $data['error']);
			$this->assertEquals($old_hash, $this->getHash());
			$dialog->close();
		}
		else {
			$dialog->ensureNotPresent();
			$this->page->waitUntilReady();
			$this->page->assertHeader('Problems');

			$message = ($count > 1) ? 'Events updated' : 'Event updated';
			$this->assertMessage(TEST_GOOD, $message);

			// Check db change.
			// DB values "action" and "new_severity" may depend on previous test cases in data provider.
			foreach ($data['db_check'] as $event) {
				$sql = CDBHelper::getRow('SELECT message, action, new_severity, suppress_until'.
						' FROM acknowledges'.
						' WHERE eventid=('.
							'SELECT eventid'.
							' FROM events'.
							' WHERE name='.zbx_dbstr($event['name']).
						') ORDER BY acknowledgeid DESC'
				);

				// Suppress time is always different, so we check only that it is > 0.
				if ($sql['suppress_until'] > 0) {
					$sql['suppress_until'] = true;
				}

				$this->assertEquals($event['db_fields'], $sql);
			}
		}
	}

	public function getCancelData() {
		return [
			[
				[
					'case' => 'Cancel'
				]
			],
			[
				[
					'case' => 'Close'
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormUpdateProblem_Cancel($data) {
		$old_hash = $this->getHash();

		// Open filtered Problems list.
		$this->page->login()->open('zabbix.php?&action=problem.view&show_suppressed=1&hostids%5B%5D='.self::$hostid)->waitUntilReady();
		$this->query('class:list-table')->asTable()->one()->findRow('Problem', 'Trigger for log')->getColumn('Update')
				->query('tag:a')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('id:acknowledge_form')->asForm()->one()->fill([
				'id:scope_1' => true,
				'id:change_severity' => true,
				'id:severity' => 'Disaster',
				'id:suppress_problem' => true,
				'id:suppress_time_option' => 'Until',
				'id:suppress_until_problem' => 'now+2h',
				'Acknowledge' => true
		]);

		$dialog->query(($data['case'] === 'Close') ? 'xpath:.//button[@title="Close"]' : 'button:Cancel')->one()
				->waitUntilClickable()->click();
		$dialog->ensureNotPresent();
		$this->page->assertHeader('Problems');
		$this->assertEquals($old_hash, $this->getHash());
	}

	public function testFormUpdateProblem_CheckSuppressIcon() {
		$this->page->login()->open('zabbix.php?&action=problem.view&show_suppressed=1&hostids%5B%5D='.self::$hostid)->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		$row = $table->findRow('Problem', 'Trigger for icon test');
		$row->getColumn('Update')->query('tag:a')->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one();
		$form = $dialog->waitUntilReady()->query('id:acknowledge_form')->asForm()->one();
		$form->fill(['id:suppress_problem' => true, 'id:suppress_time_option' => 'Indefinitely']);
		$form->submit();
		$dialog->ensureNotPresent();
		$this->page->waitUntilReady();
		$table->waitUntilReloaded();

		// Check suppressed icon and hint.
		$this->checkIconAndHint($row, 'zi-eye-off', "Suppressed till: Indefinitely".
				"\nManually by: Admin (Zabbix Administrator)"
		);

		// Suppress the problem in DB: 'Trigger for icon test'.
		DBexecute('INSERT INTO event_suppress (event_suppressid, eventid, maintenanceid, suppress_until)
				VALUES (10051, '.self::$eventid_for_icon_test.', NULL, 0)'
		);

		// Assert that eye icon stopped blinking.
		$this->page->refresh();
		$this->assertTrue($row->getColumn('Info')->query('xpath:.//button[not(contains(@class, "js-blink"))]')->exists());

		// TODO: Remove sleep after fix ZBX-26128. Sometimes suppress and unsuppress events have the same acknowledege time and
		// are displayed in the wrong order in history table in the acknowledege popup. Failure in checkHistoryTable()
		sleep(1);

		// Unsuppress problem.
		$row->getColumn('Update')->query('tag:a')->waitUntilClickable()->one()->click();
		$dialog->waitUntilReady();
		$form->fill(['id:unsuppress_problem' => true]);
		$form->submit();
		$dialog->ensureNotPresent();
		$this->page->waitUntilReady();
		$table->waitUntilReloaded();

		// Check unsuppressed icon and hint.
		$this->checkIconAndHint($row, 'zi-eye', 'Unsuppressed by: Admin (Zabbix Administrator)');

		// Unsuppress the problem in DB: 'Trigger for icon test'.
		DBexecute('DELETE FROM event_suppress WHERE event_suppressid=10051');
		$this->page->refresh();

		// Check that eye icon disappeared.
		$this->assertFalse($row->getColumn('Info')->query("xpath:.//button[@class=".
				CXPathHelper::fromClass('zi-eye')."]")->exists()
		);

		// Check Suppress/Unsuppress icon in History table.
		$row->getColumn('Update')->query('tag:a')->waitUntilClickable()->one()->click();
		$dialog->waitUntilReady();
		$form->invalidate();
		$this->checkHistoryTable($form->getField('History')->asTable(), 'User', 'User action');
		$dialog->close();
		$this->page->waitUntilReady();

		// Check Actions hint in Problem row.
		$row->invalidate();
		$unsuppress_button = 'xpath:.//button['.CXPathHelper::fromClass('zi-eye').']';
		$row->getColumn('Actions')->query($unsuppress_button)->waitUntilClickable()->one()->click();
		$hint = $this->query('xpath://div[@data-hintboxid and @class="overlay-dialogue wordbreak"]')->asOverlayDialog()
				->one()->waitUntilReady();
		$this->checkHistoryTable($hint->query('class:list-table')->asTable()->one(), 'User', 'Action');
		$hint->close();

		// Check Event details page.
		$row->getColumn('Time')->query('tag:a')->waitUntilClickable()->one()->click();
		$this->page->assertHeader('Event details');
		$this->checkHistoryTable($this->query("xpath://section[@id=\"hat_eventactions\"]//table")->asTable()->one(),
				'User/Recipient', 'Action'
		);

		// Check Actions hint in Event list.
		$event_list_table = $this->query('xpath://section[@id="hat_eventlist"]//table')->asTable()->one();
		$event_list_table->getRow(0)->getColumn('Actions')->query($unsuppress_button)->waitUntilClickable()->one()->click();
		$hint->invalidate();
		$this->checkHistoryTable($hint->query('class:list-table')->asTable()->one(), 'User', 'Action');
		$hint->close();
	}

	/**
	 * Function for testing Suppressed/Unsuppressed icon or button in history tables.
	 *
	 * @param CTableElement $table    problem/event history table
	 * @param string        $user     user table header
	 * @param string        $action   action table header
	 */
	private function checkHistoryTable($table, $user, $action) {
		// Check last two rows.
		foreach ([0, 1] as $i)  {
			$action_row = $table->getRow($i);
			$this->assertEquals('Admin (Zabbix Administrator)', $action_row->getColumn($user)->getText());
			$query = ($i === 0)
				? 'xpath:.//span[@title="Unsuppressed"]'
				: 'xpath:.//*['.CXPathHelper::fromClass('zi-eye-off').']';
			$this->assertTrue($action_row->getColumn($action)->query($query)->exists());
		}
	}

	/**
	 *
	 * @param CTableRowElement $row      table row where necessary problem is found
	 * @param string           $class    suppressed or unsuppressed icon class
	 * @param string           $text     text of suppression/unsuppression info-hint
	 */
	private function checkIconAndHint($row, $class, $text) {
		// Assert blinking icon in Info column.
		$icon = $row->getColumn('Info')->query('class', [$class, 'js-blink'])->waitUntilPresent();
		$this->assertTrue($icon->exists());

		// Check icon hintbox.
		$icon->one()->waitUntilClickable()->click(true);
		$hint = $this->query('xpath://div[@data-hintboxid]')->one();
		$this->assertTrue($hint->isVisible());
		$this->assertEquals($text, $hint->getText());
		$hint->asOverlayDialog()->close();

		// Assert non-blinking icon in Actions column.
		$this->assertTrue($row->getColumn('Actions')->query('xpath:.//button['.CXPathHelper::fromClass($class).']')->exists());
	}
}
