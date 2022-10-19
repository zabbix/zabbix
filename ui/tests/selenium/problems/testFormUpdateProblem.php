<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup hosts
 *
 * @onBefore prepareProblemsData
 */
class testFormUpdateProblem extends CWebTest {

	/**
	 * Ids of the triggers for problems.
	 *
	 * @var array
	 */
	protected static $triggerids;

	/**
	 * Time when events were created.
	 *
	 * @var string
	 */
	protected static $time;

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
			'groups' => [['groupid' => $groupid]],
			'interfaces' => [
				[
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10550
				]
			]
		]);
		$this->assertArrayHasKey('hostids', $hosts);
		$hostid = $hosts['hostids'][0];

		// Create items on previously created host.
		$item_names = ['float', 'char', 'log', 'unsigned', 'text'];

		$items_data = [];
		foreach ($item_names as $i => $item) {
			$items_data[] = [
				'hostid' => $hostid,
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
				'expression' => '{Host for Problems Update:float.last()}>0',
				'priority' => 0
			],
			[
				'description' => 'Trigger for char',
				'expression' => '{Host for Problems Update:char.last()}>0',
				'priority' => 1,
				'manual_close' => 1
			],
			[
				'description' => 'Trigger for log',
				'expression' => '{Host for Problems Update:log.last()}>0',
				'priority' => 2
			],
			[
				'description' => 'Trigger for unsigned',
				'expression' => '{Host for Problems Update:unsigned.last()}>0',
				'priority' => 3
			],
			[
				'description' => 'Trigger for text',
				'expression' => '{Host for Problems Update:text.last()}>0',
				'priority' => 4
			]
		]);
		$this->assertArrayHasKey('triggerids', $triggers);
		self::$triggerids = CDataHelper::getIds('description');

		// Create events.
		self::$time = time();
		$i=0;
		foreach (self::$triggerids as $name => $id) {
			DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES ('.(100550 + $i).', 0, 0, '.
					zbx_dbstr($id).', '.self::$time.', 0, 1, '.zbx_dbstr($name).', '.zbx_dbstr($i).')'
			);
			$i++;
		}

		// Create problems.
		$j=0;
		foreach (self::$triggerids as $name => $id) {
			DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES ('.(100550 + $j).', 0, 0, '.
					zbx_dbstr($id).', '.self::$time.', 0, '.zbx_dbstr($name).', '.zbx_dbstr($j).')'
			);
			$j++;
		}

		// Change triggers' state to Problem. Manual close is true for the problem: Trigger for char'.
		DBexecute('UPDATE triggers SET value = 1 WHERE description IN ('.zbx_dbstr('Trigger for float').', '.
				zbx_dbstr('Trigger for log').', '.zbx_dbstr('Trigger for unsigned').', '.zbx_dbstr('Trigger for text').')'
		);
		DBexecute('UPDATE triggers SET value = 1, manual_close = 1 WHERE description = '.zbx_dbstr('Trigger for char'));

		// Suppress the problem: 'Trigger for text'.
		DBexecute('INSERT INTO event_suppress (event_suppressid, eventid, maintenanceid, suppress_until) VALUES (10050, 100554, NULL, 0)');

		// Acknowledge the problem: 'Trigger for unsigned'.
		CDataHelper::call('event.acknowledge', [
			'eventids' => 100553,
			'action' => 6,
			'message' => 'Acknowledged event'
		]);
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
								'Status change triggers action update operation.'
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
					// If problem is Aknowledged - label is changed to Unacknowledge.
					'labels' => ['Problem', 'Message', 'History', 'Scope', 'Change severity', 'Unacknowledge', 'Close problem', ''],
					'message' => 'Acknowledged event',
					'Unacknowledge' => true,
					'history' => [' Admin (Zabbix Administrator) Acknowledged event'],
					'hintboxes' => [
						'Suppress' => 'Manual problem suppression. Date-time input accepts relative and absolute time format.',
						'Unsuppress' => 'Deactivates manual suppression.',
						'Unacknowledge' => 'Undo problem acknowledgement.'
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
					// If more than one problem selected - History label is absent.
					'labels' => ['Problem', 'Message', 'Scope', 'Change severity', 'Acknowledge', 'Close problem', ''],
					'close_enabled' => true,
					'Acknowledge' => true,
					'hintboxes' => [
						'Suppress' => 'Manual problem suppression. Date-time input accepts relative and absolute time format.',
						'Unsuppress' => 'Deactivates manual suppression.',
						'Acknowledge' => 'Confirms the problem is noticed (acknowledging user will be recorded). '.
								'Status change triggers action update operation.'
					]
				]
			],
			// Five problems.
			[
				[
					'problems' => ['Trigger for float', 'Trigger for char', 'Trigger for log', 'Trigger for unsigned', 'Trigger for text'],
					// If more than one problems selected - History label is absent.
					'labels' => ['Problem', 'Message', 'Scope', 'Change severity', 'Acknowledge', 'Unacknowledge', 'Close problem', ''],
					'hintboxes' => [
						'Suppress' => 'Manual problem suppression. Date-time input accepts relative and absolute time format.',
						'Unsuppress' => 'Deactivates manual suppression.',
						'Acknowledge' => 'Confirms the problem is noticed (acknowledging user will be recorded). '.
								'Status change triggers action update operation.',
						'Unacknowledge' => 'Undo problem acknowledgement.'
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
		$table = $this->openProblemsList();
		$table->findRows('Problem', $data['problems'])->select();
		$this->query('button:Mass update')->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Update problem', $dialog->getTitle());
		$form = $dialog->query('id:acknowledge_form')->asForm()->one();

		// Check form labels.
		$count = count($data['problems']);
		$default_labels = ['Problem', 'Message', 'History', 'Scope', 'Change severity', 'Acknowledge', 'Close problem', ''];
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

		// Check History field.
		if (array_key_exists('history', $data)) {
			$history = ($data['history'] === []) ? $data['history'] : [date('Y-m-d H:i:s', self::$time).$data['history'][0]];
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
			'id:severity' => ['enabled' => false],
			'Close problem' => ['value' => false, 'enabled' => CTestArrayHelper::get($data, 'close_enabled', false)]
		];

		foreach ($fields as $field => $attributes) {
			$this->assertTrue($form->getField($field)->isEnabled($attributes['enabled']));

			if (array_key_exists('value', $attributes)) {
				$this->assertEquals($attributes['value'], $form->getField($field)->getValue());
			}

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $form->getField($field)->getAttribute('maxlength'));
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

		// Check other buttons in overlay.
		$button_queries = [
			'xpath:.//button[@title="Close"]' => true,
			'button:Update' => true,
			'button:Cancel' => true
		];

		foreach ($button_queries as $query => $clickable) {
			$this->assertEquals($clickable, $dialog->query($query)->one()->isClickable());
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
					'problems' => ['Trigger for log', 'Trigger for char', 'Trigger for float'],
					'fields' => [
						'id:scope_1' => true,
						'id:change_severity' => true,
						'id:severity' => 'Information'
					],
					'db_check' => [
						[
							'name' => 'Trigger for log',
							'db_fields' => ['message' => '', 'action' => 8, 'new_severity' => 1]
						],
						[
							'name' => 'Trigger for char',
							'db_fields' => false
						],
						[
							'name' => 'Trigger for float',
							'db_fields' => ['message' => '', 'action' => 8, 'new_severity' => 1]
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
						'Acknowledge' => true
					],
					'db_check' => [
						[
							'name' => 'Trigger for float',
							'db_fields' => ['message' => 'test message text', 'action' => 14, 'new_severity' => 2]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for text', 'Trigger for log'],
					'fields' => [
						'id:change_severity' => true,
						'id:severity' => 'Not classified'
					],
					'db_check' => [
						[
							'name' => 'Trigger for text',
							'db_fields' => ['message' => '', 'action' => 8, 'new_severity' => 0]
						],
						[
							'name' => 'Trigger for text',
							'db_fields' => ['message' => '', 'action' => 8, 'new_severity' => 0]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for char'],
					'fields' => [
						'id:change_severity' => true,
						'id:severity' => 'Average'
					],
					'db_check' => [
						[
							'name' => 'Trigger for char',
							'db_fields' => ['message' => '', 'action' => 8, 'new_severity' => 3]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for unsigned'],
					'fields' => [
						'id:change_severity' => true,
						'id:severity' => 'High'
					],
					'db_check' => [
						[
							'name' => 'Trigger for unsigned',
							'db_fields' => ['message' => '', 'action' => 8, 'new_severity' => 4]
						]
					]
				]
			],
			[
				[
					'problems' => ['Trigger for log'],
					'fields' => [
						'id:change_severity' => true,
						'id:severity' => 'Disaster'
					],
					'db_check' => [
						[
							'name' => 'Trigger for log',
							'db_fields' => ['message' => '', 'action' => 8, 'new_severity' => 5]
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
						'id:severity' => 'High'
					],
					'db_check' => [
						[
							'name' => 'Trigger for log',
							'db_fields' => ['message' => 'Update all 5 problems', 'action' => 12, 'new_severity' => 4]
						],
						[
							'name' => 'Trigger for char',
							'db_fields' => ['message' => 'Update all 5 problems', 'action' => 12, 'new_severity' => 4]
						],
						[
							'name' => 'Trigger for float',
							'db_fields' => ['message' => 'Update all 5 problems', 'action' => 12, 'new_severity' => 4]
						],
						[
							'name' => 'Trigger for text',
							'db_fields' => ['message' => 'Update all 5 problems', 'action' => 12, 'new_severity' => 4]
						],
						[
							'name' => 'Trigger for unsigned',
							'db_fields' => ['message' => 'Update all 5 problems', 'action' => 4, 'new_severity' => 0]
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
							'db_fields' => ['message' => '', 'action' => 16, 'new_severity' => 0]
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
							'db_fields' => ['message' => '', 'action' => 1, 'new_severity' => 0]
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
		$table = $this->openProblemsList();
		$count = count($data['problems']);
		$table->findRows('Problem', $data['problems']);

		if ($count > 1) {
			$table->findRows('Problem', $data['problems'])->select();
			$this->query('button:Mass update')->waitUntilClickable()->one()->click();
		}
		else {
			$table->findRow('Problem', $data['problems'][0])->getColumn('Ack')->query('tag:a')->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->query('id:acknowledge_form')->asForm()->one();
		$form->fill($data['fields']);
		$form->submit();

		$dialog->ensureNotPresent();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Problems');

		$message = ($count > 1) ? 'Events updated' : 'Event updated';
		$this->assertMessage(TEST_GOOD, $message);

		// Check db change.
		foreach ($data['db_check'] as $event) {
			$sql = CDBHelper::getRow('SELECT message, action, new_severity'.
					' FROM acknowledges'.
					' WHERE eventid=('.
						'SELECT eventid'.
						' FROM events'.
						' WHERE name='.zbx_dbstr($event['name']).
					') ORDER BY acknowledgeid DESC'
			);

			$this->assertEquals($event['db_fields'], $sql);
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
		$this->page->login()->open('zabbix.php?&action=problem.view')->waitUntilReady();
		$this->query('class:list-table')->asTable()->one()->findRow('Problem', 'Trigger for char')->getColumn('Ack')
				->query('tag:a')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('id:acknowledge_form')->asForm()->one()->fill([
				'id:scope_1' => true,
				'id:change_severity' => true,
				'id:severity' => 'Disaster',
				'Acknowledge' => true
		]);

		$dialog->query(($data['case'] === 'Close') ? 'xpath:.//button[@title="Close"]' : 'button:Cancel')->one()
				->waitUntilClickable()->click();

		$dialog->ensureNotPresent();
		$this->page->assertHeader('Problems');
		$this->assertEquals($old_hash, $this->getHash());
	}

	/**
	 * Function for opening problems page and filtering necessary problems.
	 *
	 * @return CTableElement
	 */
	private function openProblemsList() {
		$this->page->login()->open('zabbix.php?action=problem.view')->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		// Filter necessary problems.
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_form->fill(['Hosts' => 'Host for Problems Update', 'id:filter_show_suppressed' => true]);
		$this->query('button:Apply')->one()->click();
		$this->page->waitUntilReady();
		$table->waitUntilReloaded();

		return $table;
	}
}
