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

/**
 * @backup media_type
 *
 * @dataSource Actions
 *
 * @onBefore prepareActionData
 */
class testPageAdministrationMediaTypes extends CWebTest {

	const ZABBIX_ADMIN_GROUPID = 7;
	const EMAIL_MEDIATYPEID = 1;
	const MEDIA_NAME = 'Email';

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			[
				'class' => CTableBehavior::class,
				'column_names' => ['', 'Name', 'Type', 'Status', 'Count', 'Used in actions', 'Details', 'Actions']
			]
		];
	}

	public static function prepareActionData() {
		CDataHelper::call('action.create', [
			[
				'name' => 'Action with email',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => []
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => ['mediatypeid' => self::EMAIL_MEDIATYPEID],
						'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
					]
				]
			]
		]);
	}

	/**
	 * Check basic elements on page.
	 */
	public function testPageAdministrationMediaTypes_Layout() {
		$this->page->login()->open('zabbix.php?action=mediatype.list')->waitUntilReady();

		$this->page->assertTitle('Configuration of media types');
		$this->page->assertHeader('Media types');

		$buttons = [
			'Create media type' => true,
			'Import' => true,
			'Apply' => true,
			'Reset' => true,
			'Enable' => false,
			'Disable' => false,
			'Export' => false,
			'Delete' => false
		];
		foreach ($buttons as $button => $enabled) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}

		// Check filter fields.
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter_fields = [
			'Name' => '',
			'Status' => 'Any',
			'Display actions' => 'All'
		];
		$filter->checkValue($filter_fields);

		$this->assertEquals(255, $filter->getField('Name')->getAttribute('maxlength'));

		$radio_buttons = [
			'Status' => ['Any', 'Enabled', 'Disabled'],
			'Display actions' => ['All', 'All available', 'Specific']
		];
		foreach ($radio_buttons as $label => $options) {
			$this->assertEquals($options, $filter->getField($label)->asSegmentedRadio()->getLabels()->asText());
		}

		$filter->getLabel('Display actions')->query('xpath:./button[@data-hintbox]')->one()->click();
		$popup = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()->waitUntilPresent()->one();
		$popup_text = "Filter actions by the scope of media type usage:\n".
				"All - display all actions\n".
				"All available - display only actions where All available media types are used in action operation\n".
				"Specific - display only actions where specific media type is used in action operation";

		$this->assertEquals($popup_text, $popup->getText());
		$popup->close();

		// Check table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertSame(['', 'Name', 'Type', 'Status', 'Used in actions', 'Details', 'Action'], $table->getHeadersText());
		$this->assertEquals(['Name', 'Type'], $table->getSortableHeaders()->asText());

		// Check table stats and selected mediatype counter.
		$this->assertTableStats(CDBHelper::getCount('SELECT NULL FROM media_type'));
		$this->assertSelectedCount(0);
	}

	/**
	 * Check sorting of media types in list.
	 *
	 * @onAfterOnce resetFilter
	 */
	public function testPageAdministrationMediaTypes_Sort() {
		$this->page->login()->open('zabbix.php?action=mediatype.list&sortorder=DESC');
		$table = $this->query('class:list-table')->asTable()->one();

		foreach (['Name', 'Type'] as $column) {
			$values = $this->getTableColumnData($column);

			$values_asc = $values;
			$values_desc = $values;

			// Sort column contents ascending.
			usort($values_asc, function($a, $b) {
				return strcasecmp($a, $b);
			});

			// Sort column contents descending.
			usort($values_desc, function($a, $b) {
				return strcasecmp($b, $a);
			});

			// Check ascending and descending sorting in column.
			foreach ([$values_asc, $values_desc] as $reference_values) {
				$table->query('link', $column)->waitUntilClickable()->one()->click();
				$table->waitUntilReloaded();
				$this->assertTableDataColumn($reference_values, $column);
			}
		}
	}

	public static function getFilterData() {
		return [
			// Filter by name.
			[
				[
					'filter' => [
						'Name' => 'SMS'
					],
					'result' => ['SMS']
				]
			],
			[
				[
					'filter' => [
						'Name' => 'Jira '
					],
					'result' => ['Jira ServiceDesk']
				]
			],
			[
				[
					'filter' => [
						'Name' => ' Jira '
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'a S'
					],
					'result' => ['Jira ServiceDesk']
				]
			],
			// Filter by status.
			[
				[
					'filter' => [
						'Status' => 'Enabled'
					],
					'get_db_result' => true
				]
			],
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'get_db_result' => true
				]
			],
			// Filter by name and status.
			[
				[
					'filter' => [
						'Name' => 'Reference webhook',
						'Status' => 'Disabled'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'Email',
						'Status' => 'Disabled'
					],
					'result' => ['Email', 'Email (HTML)']
				]
			],
			// Filter the actions displayed in Used in actions" column.
			[
				[
					'filter' => [
						'Name' => 'Email',
						'Display actions' => 'All'
					],
					'sql_part' => ' WHERE mediatypeid=1 or mediatypeid IS NULL'
				]
			],
			[
				[
					'filter' => [
						'Name' => 'Email',
						'Display actions' => 'All available'
					],
					'sql_part' => ' WHERE mediatypeid IS NULL'
				]
			],
			[
				[
					'filter' => [
						'Name' => 'Email',
						'Display actions' => 'Specific'
					],
					'sql_part' => ' WHERE mediatypeid=1'
				]
			]
		];
	}

	/**
	 * Check media types filtering.
	 *
	 * @dataProvider getFilterData
	 *
	 * @onAfterOnce resetFilter
	 */
	public function testPageAdministrationMediaTypes_Filter($data) {
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->query('button:Reset')->waitUntilClickable()->one()->click();

		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'get_db_result')) {
			$db_status = (CTestArrayHelper::get($data['filter'], 'Status') === 'Enabled')
				? MEDIA_TYPE_STATUS_ACTIVE
				: MEDIA_TYPE_STATUS_DISABLED;

			foreach (CDBHelper::getAll('SELECT name FROM media_type WHERE status='.$db_status.
					' ORDER BY LOWER(name) ASC') as $name) {
				$data['result'][] = $name['name'];
			}
		}

		if (array_key_exists('Display actions', $data['filter'])) {
			// Get the list of expected actions from DB and compare it to the value in the "Used in actions" column.
			$sql = 'SELECT name FROM actions WHERE actionid IN (SELECT DISTINCT actionid FROM operations WHERE'.
					' operationid IN (SELECT operationid FROM opmessage'.$data['sql_part'].')) ORDER BY name';

			$actions = $this->getTable()->findRow('Name', $data['filter']['Name'])->getColumn('Used in actions')->getText();
			$this->assertEquals(CDBHelper::getColumn($sql, 'name'), explode(', ', $actions));
		}
		else {
			$this->assertTableDataColumn(CTestArrayHelper::get($data, 'result', []));
		}
	}

	/**
	 * Disable and enable media type by link in column Status.
	 */
	public function testPageAdministrationMediaTypes_StatusLink() {
		$this->page->login()->open('zabbix.php?action=mediatype.list');

		// Get row by column Name.
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', self::MEDIA_NAME);

		$statuses = ['Disabled', 'Enabled'];
		foreach ($statuses as $old_status) {
			$new_status = array_values(array_diff($statuses, [$old_status]))[0];
			// Change media type status.
			$row->query('link', $old_status)->one()->click();
			$this->page->waitUntilReady();

			// Check result on fronted.
			$this->assertMessage(TEST_GOOD, 'Media type '.lcfirst($new_status));
			CMessageElement::find()->one()->close();

			if ($new_status === 'Enabled') {
				$enabled = true;
				$db_status = MEDIA_TYPE_STATUS_ACTIVE;
			}
			else {
				$enabled = false;
				$db_status = MEDIA_TYPE_STATUS_DISABLED;
			}

			// Check that Test link is disabled.
			$this->assertTrue($row->query('button:Test')->one()->isEnabled($enabled));

			// Check result in DB.
			$this->assertEquals($db_status, CDBHelper::getValue('SELECT status FROM media_type WHERE '.
					'name='.zbx_dbstr(self::MEDIA_NAME))
			);
		}
	}

	public static function getSelectedMediaTypeData() {
		return [
			// Select one.
			[
				[
					'rows' => ['Email'],
					'db_name' => 'Email',
					'used_by_action' => 'Service action'
				]
			],
			// Select several.
			[
				[
					'rows' => ['Discord', 'Email (HTML)'],
					'db_name' => ['Discord', 'Email (HTML)']
				]
			],
			// Select all.
			[
				[
					'select_all' => true,
					// Selected different action names in MySQL and PostgreSQL.
					'used_by_action' => ''
				]
			]
		];
	}

	/**
	 * Test disabling of media types in the list.
	 *
	 * @dataProvider getSelectedMediaTypeData
	 */
	public function testPageAdministrationMediaTypes_Disable($data) {
		$this->checkStatusChangeButton($data);
	}

	/**
	 * Test enabling of media types in the list.
	 *
	 * @dataProvider getSelectedMediaTypeData
	 */
	public function testPageAdministrationMediaTypes_Enable($data) {
		$this->checkStatusChangeButton($data, 'enable');
	}

	/**
	 * Check that status of selected media types is changed when clicking on the corresponding control button.
	 *
	 * @param array		$data		data provider
	 * @param string	$action		action to be performed with the selected media types
	 */
	private function checkStatusChangeButton($data, $action = 'disable') {
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->selectTableRows(CTestArrayHelper::get($data, 'rows', []));

		// Check number of all selected media types.
		if (array_key_exists('select_all', $data)) {
			$this->assertEquals(CDBHelper::getCount('SELECT NULL FROM media_type').' selected',
					$this->query('id:selected_count')->one()->getText()
			);
		}
		else {
			$this->assertEquals(count($data['rows']).' selected', $this->query('id:selected_count')->one()->getText());
		}

		$this->query('button', ucfirst($action))->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check the results in frontend.
		$message_title = (count(CTestArrayHelper::get($data, 'rows', [])) === 1)
			? 'Media type '.$action.'d'
			: (($action === 'enable' && CTestArrayHelper::get($data, 'select_all'))
				? 'Media types '.$action.'d. Not enabled: Gmail, Office365. Incomplete configuration.'
				: 'Media types '.$action.'d'
			);
		$this->assertMessage(TEST_GOOD, $message_title);

		// Check the results in DB.
		$status = ($action === 'enable') ? MEDIA_TYPE_STATUS_ACTIVE : MEDIA_TYPE_STATUS_DISABLED;

		if (array_key_exists('rows', $data)) {
			$this->assertEquals(count($data['rows']), CDBHelper::getCount('SELECT NULL FROM media_type WHERE status='.
					$status.' AND name IN ('.CDBHelper::escape($data['db_name']).')')
			);
		}
		else {
			// Gmail and Office365 media types cannot be mass updated as they have an empty mandatory password by default.
			$expected_count = ($action === 'enable' && CTestArrayHelper::get($data, 'select_all')) ? 2 : 0;
			$this->assertEquals($expected_count, CDBHelper::getCount('SELECT NULL FROM media_type WHERE status<>'.$status));
		}
	}

	public static function getTestFormData() {
		return [
			// Email validation.
			[
				[
					'name' => 'Email',
					'check_title' => true,
					'check_params' => true,
					'error' => 'Incorrect value for field "sendto": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => ' '
					],
					'error' => 'Incorrect value for field "sendto": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbixzabbix.com'
					],
					'error' => 'Invalid email address "zabbixzabbix.com".'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix@zabbixcom'
					],
					'error' => 'Invalid email address "zabbix@zabbixcom".'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix@zabbixcom'
					],
					'error' => 'Invalid email address "zabbix@zabbixcom".'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => '@zabbix.com'
					],
					'error' => 'Invalid email address "@zabbix.com".'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix1@zabbix.com,zabbix2@zabbix.com'
					],
					'error' => 'Invalid email address "zabbix1@zabbix.com,zabbix2@zabbix.com".'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix@zabbix.com',
						'Subject' => ''
					],
					'error' => "Connection to Zabbix server \"localhost:10051\" refused. Possible reasons:\n".
							"1. Incorrect \"NodeAddress\" or \"ListenPort\" in the \"zabbix_server.conf\" or server IP/DNS override in the \"zabbix.conf.php\";\n".
							"2. Security environment (for example, SELinux) is blocking the connection;\n".
							"3. Zabbix server daemon not running;\n".
							"4. Firewall is blocking TCP connection.\n".
							"Connection refused"
				]
			],
			// Message validation.
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix@zabbix.com',
						'Message' => ''
					],
					'error' => 'Incorrect value for field "message": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix@zabbix.com',
						'Message' => ' '
					],
					'error' => 'Incorrect value for field "message": cannot be empty.'
				]
			],
			// SMS media type.
			[
				[
					'name' => 'SMS',
					'form' => [
						'Send to' => 'abcd',
						'Message' => 'new message'
					],
					'error' => "Connection to Zabbix server \"localhost:10051\" refused. Possible reasons:\n".
							"1. Incorrect \"NodeAddress\" or \"ListenPort\" in the \"zabbix_server.conf\" or server IP/DNS override in the \"zabbix.conf.php\";\n".
							"2. Security environment (for example, SELinux) is blocking the connection;\n".
							"3. Zabbix server daemon not running;\n".
							"4. Firewall is blocking TCP connection.\n".
							"Connection refused"
				]
			],
			// 	Script media type.
			[
				[
					'name' => 'Test script',
					'form' => [
						'Script parameters' => '/../"'
					],
					'error' => "Connection to Zabbix server \"localhost:10051\" refused. Possible reasons:\n".
							"1. Incorrect \"NodeAddress\" or \"ListenPort\" in the \"zabbix_server.conf\" or server IP/DNS override in the \"zabbix.conf.php\";\n".
							"2. Security environment (for example, SELinux) is blocking the connection;\n".
							"3. Zabbix server daemon not running;\n".
							"4. Firewall is blocking TCP connection.\n".
							"Connection refused"
				]
			],
			// 	Webhook media type.
			[
				[
					'name' => 'Reference webhook',
					'webhook' => true,
					'parameters' => ['HTTPProxy', 'Message', 'Subject', 'To', 'URL', 'Response'],
					'error' => "Connection to Zabbix server \"localhost:10051\" refused. Possible reasons:\n".
							"1. Incorrect \"NodeAddress\" or \"ListenPort\" in the \"zabbix_server.conf\" or server IP/DNS override in the \"zabbix.conf.php\";\n".
							"2. Security environment (for example, SELinux) is blocking the connection;\n".
							"3. Zabbix server daemon not running;\n".
							"4. Firewall is blocking TCP connection.\n".
							"Connection refused"
				]
			]
		];
	}

	/**
	 * Check media type test form.
	 *
	 * @dataProvider getTestFormData
	 *
	 * @depends testPageAdministrationMediaTypes_Enable
	 */
	public function testPageAdministrationMediaTypes_TestMediaType($data) {
		$this->page->login()->open('zabbix.php?action=mediatype.list');

		// Get row by media Name and click on Test button.
		$this->query('class:list-table')->asTable()->one()->findRow('Name', $data['name'])->query('button:Test')
				->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		if (CTestArrayHelper::get($data, 'check_title')) {
			$this->assertEquals('Test media type "'.$data['name'].'"', $dialog->getTitle());
		}

		$form = $dialog->asForm();

		if (CTestArrayHelper::get($data, 'check_params')) {
			$fields = CTestArrayHelper::get($data, 'parameters', ['Send to', 'Subject', 'Message']);
			$this->assertEquals($fields, $form->getLabels()->asText());
		}

		if (CTestArrayHelper::get($data, 'webhook')) {
			$this->assertTrue($form->getField('Response')->isEnabled(false));
		}

		// Fill and submit testing form.
		if (array_key_exists('form', $data)) {
			$form->fill($data['form']);
		}
		$form->submit();

		// Check error message.
		$this->assertMessage(TEST_BAD, 'Media type test failed.', $data['error']);

		if (CTestArrayHelper::get($data, 'webhook')) {
			$form->checkValue(['Response' => 'false']);
			$this->assertEquals($form->query('id:webhook_response_type')->one()->getText(), 'Response type: String');
		}
	}

	/**
	 * Function removes saved media_type filters in order to avoid dependencies between this class test cases.
	 */
	public function resetFilter() {
		DBexecute('DELETE FROM profiles WHERE idx LIKE \'%web.media_types%\'');
	}

	/**
	 * Check Test form canceling functionality.
	 */
	public function testPageAdministrationMediaTypes_CancelTest() {
		$fields = [
			'Send to' => 'zabbix@zabbix.com',
			'Subject' => 'new subject',
			'Message' => 'new message'
		];

		$this->page->login()->open('zabbix.php?action=mediatype.list');

		// Get row by media Name and click on Test button.
		$this->query('class:list-table')->asTable()->one()->findRow('Name', self::MEDIA_NAME)
				->query('button:Test')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Test media type "'.self::MEDIA_NAME.'"', $dialog->getTitle());
		$dialog->asForm()->fill($fields);

		$dialog->getFooter()->query('button:Cancel')->one()->click();
		$dialog->ensureNotPresent();
	}

	/**
	 * Test deleting of media types in list.
	 *
	 * @dataProvider getSelectedMediaTypeData
	 */
	public function testPageAdministrationMediaTypes_Delete($data) {
		if (array_key_exists('used_by_action', $data)) {
			$sql = 'SELECT NULL FROM media_type';
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->selectTableRows(CTestArrayHelper::get($data, 'rows', []));

		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check the results in frontend and in DB.
		if (array_key_exists('used_by_action', $data)) {
			$message_title = (count(CTestArrayHelper::get($data, 'rows', [])) === 1)
				? 'Cannot delete media type'
				: 'Cannot delete media types';
			$this->assertMessage(TEST_BAD, $message_title, 'Media type "Email" is used by action "'.$data['used_by_action']);

			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}
		else {
			$message_title = (count($data['rows']) === 1) ? 'Media type deleted' : 'Media types deleted';
			$this->assertMessage(TEST_GOOD, $message_title);

			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM media_type WHERE name IN ('.
					CDBHelper::escape($data['db_name']).')')
			);
		}
	}

	/**
	 * Function for getting the id of an Action and update it for changing all operations to one particular Media type,
	 * in case if some action operations were set to -All- media types.
	 */
	protected function getIdAndUpdateAction() {
		$update_info = [
			[
				'operationtype' => OPERATION_TYPE_MESSAGE,
				'opmessage' => ['mediatypeid' => self::EMAIL_MEDIATYPEID],
				'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
			]
		];

		foreach([EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL,
				EVENT_SOURCE_SERVICE] as $sourceid) {
			$actionids = CDBHelper::getColumn('SELECT actionid FROM actions WHERE eventsource='.zbx_dbstr($sourceid), 'actionid');

			foreach ($actionids as $actionid) {
				switch ($sourceid) {
					case EVENT_SOURCE_TRIGGERS:
					case EVENT_SOURCE_SERVICE:
						$update_data = [
							'actionid' => $actionid,
							'operations' => $update_info,
							'recovery_operations' => $update_info,
							'update_operations' => $update_info
						];
						break;

					case EVENT_SOURCE_INTERNAL:
						$update_data = [
							'actionid' => $actionid,
							'operations' => $update_info,
							'recovery_operations' => $update_info
						];
						break;

					case EVENT_SOURCE_DISCOVERY:
					case EVENT_SOURCE_AUTOREGISTRATION:
						$update_data = [
							'actionid' => $actionid,
							'operations' => $update_info
						];
				}

				CDataHelper::call('action.update', $update_data);
			}
		}
	}

	public static function getActionsColumnData() {
		return [
			// #0 Used in no action.
			[
				[
					'name' => 'Brevis.one',
					'expected' => ''
				]
			],
			// #1 Used in action operation directly.
			[
				[
					'name' => 'Github',
					'actions' => [
						[
							'name' => 'Github action operation',
							'operation' => 'operations'
						]
					]
				]
			],
			// #2 Used in action recovery operation directly.
			[
				[
					'name' => 'iTop',
					'actions' => [
						[
							'name' => 'iTop Action recovery operation',
							'operation' => 'recovery_operations'
						]
					]
				]
			],
			// #3 Used in action update operation directly.
			[
				[
					'name' => 'Line',
					'actions' => [
						[
							'name' => 'Line acton update operation',
							'operation' => 'update_operations'
						]
					]
				]
			],
			// #4 Used in two actions operation directly.
			[
				[
					'name' => 'Slack',
					'actions' => [
						[
							'name' => 'Slack acton update operation 1',
							'operation' => 'operations'
						],
						[
							'name' => 'Slack acton update operation 2',
							'operation' => 'operations'
						]
					]
				]
			],
			// #5 Used in two actions update operations directly.
			[
				[
					'name' => 'OTRS',
					'actions' => [
						[
							'name' => 'OTRS acton update operation 1',
							'operation' => 'update_operations'
						],
						[
							'name' => 'OTRS acton update operation 2',
							'operation' => 'update_operations'
						]
					]
				]
			],
			// #6 Used in two actions recovery directly.
			[
				[
					'name' => 'Zendesk',
					'actions' => [
						[
							'name' => 'Zendesk acton update operation 1',
							'operation' => 'recovery_operations'
						],
						[
							'name' => 'Zendesk acton update operation 2',
							'operation' => 'recovery_operations'
						]
					]
				]
			],
			// #7 Used in two actions operation, recovery and update directly.
			[
				[
					'name' => 'PagerDuty',
					'actions' => [
						[
							'name' => 'PagerDuty acton update operation 1',
							'operation' => 'operations'
						],
						[
							'name' => 'PagerDuty acton update operation 2',
							'operation' => 'recovery_operations'
						],
						[
							'name' => 'PagerDuty acton update operation 3',
							'operation' => 'update_operations'
						]
					]
				]
			],
			// #8 Used in action operation by -All-.
			// !Important: last three cases should be run only in this order and always be placed in the end of data provider.
			[
				[
					'name' => 'MantisBT',
					'actions' => [
						[
							'name' => 'MantisBT action operation',
							'operation' => 'operations',
							'mediatypeid' => 0
						]
					]
				]
			],
			// #9 Used in action recovery operation by -All-.
			[
				[
					'name' => 'Express.ms',
					'actions' => [
						[
							'name' => 'Express.ms recovery operation action',
							'operation' => 'recovery_operations',
							'mediatypeid' => 0
						]
					],
					'expected' => 'Express.ms recovery operation action, MantisBT action operation'
				]
			],
			// #10 Used in action update operation by -All-.
			[
				[
					'name' => 'Opsgenie',
					'actions' => [
						[
							'name' => 'Opsgenie update operation action',
							'operation' => 'update_operations',
							'mediatypeid' => 0
						]
					],
					'expected' => 'Express.ms recovery operation action, MantisBT action operation, '.
							'Opsgenie update operation action'
				]
			]
		];
	}

	/**
	 * @onBeforeOnce getIdAndUpdateAction
	 *
	 * @dataProvider getActionsColumnData
	 */
	public function testPageAdministrationMediaTypes_ActionsColumn($data) {
		// Create actions with Media types assigned to operations.
		if (array_key_exists('actions', $data)) {
			$column_actions = [];
			foreach ($data['actions'] as $action) {
				$mediatypeid = CTestArrayHelper::get($action,'mediatypeid',
						CDBHelper::getValue('SELECT mediatypeid FROM media_type WHERE name='.zbx_dbstr($data['name']))
				);

				CDataHelper::call('action.create', [
					[
						'name' => $action['name'],
						'eventsource' => EVENT_SOURCE_TRIGGERS,
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => []
						],
						$action['operation'] => [
							[
								'operationtype' => OPERATION_TYPE_MESSAGE,
								'opmessage' => ['mediatypeid' => $mediatypeid],
								'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
							]
						]
					]
				]);

				// Write actions to array for comparison.
				$column_actions[] = $action['name'];
			}

			$expected = array_key_exists('expected', $data) ? $data['expected'] : implode(', ', $column_actions);
		}
		else {
			$expected = $data['expected'];
		}

		$this->page->login()->open('zabbix.php?action=mediatype.list')->waitUntilReady();
		$row = $this->getTable()->waitUntilPresent()->findRow('Name', $data['name']);
		$this->assertEquals($expected, $row->getColumn('Used in actions')->getText());
		$count = ($expected === '') ? '' : count(explode(', ', $expected));
		$this->assertEquals($count, $row->getColumn('Count')->getText());
	}
}
