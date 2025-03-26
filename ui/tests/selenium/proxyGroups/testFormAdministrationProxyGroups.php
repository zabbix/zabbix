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
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * Test for checking Proxy group form.
 *
 * @dataSource Proxies
 *
 * @backup proxy
 */
class testFormAdministrationProxyGroups extends CWebTest {

	CONST SQL = 'SELECT * FROM proxy_group pg LEFT JOIN proxy_group_rtdata pgr ON pgr.proxy_groupid = pg.proxy_groupid';
	CONST CLONE_GROUP = '⭐️😀⭐Smiley प्रॉक्सी 团体⭐️😀⭐ - unknown';

	protected static $update_group = '2nd Online proxy group';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function testFormAdministrationProxyGroups_Layout() {
		$this->page->login()->open('zabbix.php?action=proxygroup.list')->waitUntilReady();

		// Open proxy group configuration form in create mode and check layout.
		$this->query('button:Create proxy group')->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('New proxy group', $dialog->getTitle());
		$form = $this->query('id:proxy-group-form')->asForm()->one();

		// Check that proxies field is not displayed in new proxy group configuration form.
		$this->assertEquals(['Name', 'Failover period', 'Minimum number of proxies', 'Description'],
				$form->getLabels()->asText()
		);

		// Check length, default values of form fields and if they are mandatory or not.
		$field_params = [
			'Name' => [
				'maxlength' => 255,
				'mandatory' => true,
				'value' => ''
			],
			'Failover period' => [
				'maxlength' => 255,
				'mandatory' => true,
				'value' => '1m'
			],
			'Minimum number of proxies' => [
				'maxlength' => 255,
				'mandatory' => true,
				'value' => '1'
			],
			'Description' => [
				'maxlength' => 65535,
				'mandatory' => false,
				'value' => ''
			]
		];

		foreach ($field_params as $field_name => $params) {
			$field = $form->getField($field_name);
			$this->assertEquals($params['maxlength'], $field->getAttribute('maxlength'));
			$this->assertEquals($params['value'], $field->getValue());
			$this->assertEquals($params['mandatory'], $form->isRequired($field_name));
		}

		$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('tag:button')->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->asText()
		);

		// Close dialog and open configuration form in edit mode to check Proxies field and control buttons.
		$dialog->close();

		$this->query('link:Online proxy group')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Proxy group', $dialog->getTitle());
		$form->invalidate();

		$this->assertEquals(['Update', 'Clone', 'Delete', 'Cancel'], $dialog->getFooter()->query('tag:button')->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->asText()
		);
		$proxies_field = $form->getField('Proxies');
		$this->assertEquals('Active proxy 1, Active proxy 2, Active proxy 3, Active proxy to delete, Proxy_1 for filter, …',
				$proxies_field->getText()
		);

		foreach ($proxies_field->query('tag:a')->all() as $proxy_link) {
			$this->assertTrue($proxy_link->isClickable());
		}

		$dialog->close();
	}

	public function getProxyGroupData() {
		return [
			# 0.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => ''
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			# 1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty failover period',
						'Failover period' => ''
					],
					'error' => 'Incorrect value for field "failover_delay": cannot be empty.'
				]
			],
			# 2.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Failover period below minimum',
						'Failover period' => 9
					],
					'error' => 'Invalid parameter "/1/failover_delay": value must be one of 10-900.'
				]
			],
			# 3.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Failover period above maximum',
						'Failover period' => 901
					],
					'error' => 'Invalid parameter "/1/failover_delay": value must be one of 10-900.'
				]
			],
			# 4.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Failover period above maximum via suffix',
						'Failover period' => '1h'
					],
					'error' => 'Invalid parameter "/1/failover_delay": value must be one of 10-900.'
				]
			],
			# 5.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Float in failover period',
						'Failover period' => '11.1'
					],
					'error' => 'Invalid parameter "/1/failover_delay": a time unit is expected.'
				]
			],
			# 6.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Non-numeric failover period',
						'Failover period' => '10a'
					],
					'error' => 'Invalid parameter "/1/failover_delay": a time unit is expected.'
				]
			],
			# 7.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'LLD macros in Failover period',
						'Failover period' => '{#MACRO}'
					],
					'error' => 'Invalid parameter "/1/failover_delay": a time unit is expected.'
				]
			],
			# 8.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Build-in macros in Failover period',
						'Failover period' => '{PROXY.DESCRIPTION}',
						'Description' => '22'
					],
					'error' => 'Invalid parameter "/1/failover_delay": a time unit is expected.'
				]
			],
			# 9.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty minimum number of proxies',
						'Minimum number of proxies' => ''
					],
					'error' => 'Incorrect value for field "min_online": cannot be empty.'
				]
			],
			# 10.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Minimum number of proxies below minimum',
						'Minimum number of proxies' => 0
					],
					'error' => 'Invalid parameter "/1/min_online": value must be one of 1-1000.'
				]
			],
			# 11.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Minimum number of proxies above maximum',
						'Minimum number of proxies' => 1001
					],
					'error' => 'Invalid parameter "/1/min_online": value must be one of 1-1000.'
				]
			],
			# 12.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Minimum number of proxies - should be no suffix support',
						'Minimum number of proxies' => '1k'
					],
					'error' => 'Invalid parameter "/1/min_online": incorrect syntax near "1k".'
				]
			],
			# 13.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Float in minimum number of proxies',
						'Minimum number of proxies' => '1.1'
					],
					'error' => 'Invalid parameter "/1/min_online": incorrect syntax near "1.1".'
				]
			],
			# 14.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'LLD macros in Minimum number of proxies',
						'Minimum number of proxies' => '{#MACRO}'
					],
					'error' => 'Invalid parameter "/1/min_online": incorrect syntax near "#MACRO}".'
				]
			],
			# 15.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Build-in macros in Minimum number of proxies',
						'Minimum number of proxies' => '{PROXY.DESCRIPTION}',
						'Description' => '33'
					],
					'error' => 'Invalid parameter "/1/min_online": incorrect syntax near "PROXY.DESCRIPTION}".'
				]
			],
			# 16.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Degrading proxy group'
					],
					'error' => 'Proxy group "Degrading proxy group" already exists.'
				]
			],
			# 17.
			[
				[
					'fields' => [
						'Name' => '!@#$%^&**(()_+'
					]
				]
			],
			# 18.
			[
				[
					'fields' => [
						'Name' => 'All fields specified',
						'Failover period' => 900,
						'Minimum number of proxies' => 1000,
						'Description' => 'Proxy group with all possible fields specified'
					]
				]
			],
			# 19.
			[
				[
					'fields' => [
						'Name' => '    Trimming trailing and leading spaces   ',
						'Failover period' => '   900   ',
						'Minimum number of proxies' => '    1000    ',
						'Description' => '   Trim trailing and leading spaces in fields   '
					],
					'trim' => true
				]
			],
			# 20.
			[
				[
					'fields' => [
						'Name' => 'Macros support {$MACRO}',
						'Failover period' => '{$MACRO}',
						'Minimum number of proxies' => '{$MACRO}',
						'Description' => '{$MACRO}'
					]
				]
			],
			# 21.
			[
				[
					'fields' => [
						'Name' => STRING_255,
						'Failover period' => '{$'.str_repeat('MACRO', 50).'}',
						'Minimum number of proxies' => '{$'.str_repeat('MACRO', 50).'}',
						'Description' => STRING_6000
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getProxyGroupData
	 */
	public function testFormAdministrationProxyGroups_Create($data) {
		$this->checkForm($data);
	}

	/**
	 * @dataProvider getProxyGroupData
	 */
	public function testFormAdministrationProxyGroups_Update($data) {
		$this->checkForm($data, true);
	}

	/**
	 * Function for testing create or update proxy form.
	 *
	 * @param array      $data      given data provider
	 * @param boolean    $update    flag that determined whether case is from update scenario
	 */
	private function checkForm($data, $update = false) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);
		$trim = CTestArrayHelper::get($data, 'trim');
		if ($expected === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$this->page->login()->open('zabbix.php?action=proxygroup.list')->waitUntilReady();
		$this->query(($update ? 'link:'.self::$update_group : 'button:Create proxy group'))->one()->waitUntilClickable()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:proxy-group-form')->asForm()->one();

		/**
		 * A prefix is added to TEST_GOOD update scenarios in order to avoid name duplication with create TEST_GOOD scenarios.
		 * In the trimming case the first four symbols (spaces) are replaced with such prefix with leading spaces.
		 * In the 255 symbol long name case the first eight symbols are replaced with such prefix.
		 */
		if ($update && $expected === TEST_GOOD) {
			$data['fields']['Name'] = ($trim)
				? '   Update: '.substr($data['fields']['Name'], 4)
				: (($data['fields']['Name'] === STRING_255)
					? 'Update: '.substr($data['fields']['Name'], 8)
					: 'Update: '.$data['fields']['Name']);
		}

		$form->fill($data['fields']);
		$filled_data = $form->getFields()->asValues();

		// Proxies field doesn't have a value attribute, so getFields always returns NULL, so field text is checked instead.
		if ($update) {
			$filled_data['Proxies'] = $form->getField('Proxies')->getText();
		}

		$form->submit();
		$this->page->waitUntilReady();

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, ($update ? 'Cannot update proxy group' : 'Cannot add proxy group'), $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
			$dialog->close();
		}
		else {
			$dialog->ensureNotPresent();
			$this->assertMessage(TEST_GOOD, $update ? 'Proxy group updated' : 'Proxy group added');

			// Remove leading and trailing spaces from data for assertion.
			if ($trim) {
				$data['fields'] = array_map('trim', $data['fields']);
				$filled_data = array_map('trim', $filled_data);
			}

			if ($update) {
				self::$update_group = $data['fields']['Name'];
			}

			// Check values in frontend form.
			$this->query('link', $data['fields']['Name'])->waitUntilClickable()->one()->click();
			$form->invalidate();

			// Proxies field doesn't have a value attribute, so getFields always returns NULL, so field text is checked instead.
			$all_updated_fields = $form->getFields()->asValues();

			if ($update) {
				$all_updated_fields['Proxies'] = $form->getField('Proxies')->getText();
			}

			$this->assertEquals($filled_data, $all_updated_fields);
			$form->checkValue($data['fields']);
			$dialog->close();

			// Check DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM proxy_group WHERE name='
					.zbx_dbstr($data['fields']['Name']))
			);
		}
	}

	public function testFormAdministrationProxyGroups_Clone() {
		$this->page->login()->open('zabbix.php?action=proxygroup.list')->waitUntilReady();
		$this->query('link', self::CLONE_GROUP)->one()->waitUntilClickable()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:proxy-group-form')->asForm()->one();
		$original_fields = $form->getFields()->asValues();
		unset($original_fields['Proxies']);

		$new_name = 'Clone:'.self::CLONE_GROUP;

		// Clone proxy group.
		$dialog->query('button:Clone')->waitUntilClickable()->one()->click();
		$form->invalidate();
		$form->fill(['Name' => $new_name]);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Proxy group added');

		// Check cloned proxy group form fields.
		$this->query('link', $new_name)->one()->waitUntilClickable()->click();
		$dialog->waitUntilReady();
		$form->invalidate();
		$original_fields['Name'] = $new_name;
		$this->assertEquals($original_fields, $form->getFields()->asValues());

		$dialog->close();

		foreach ([self::CLONE_GROUP, $new_name] as $proxy_group_name) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM proxy_group WHERE name='.zbx_dbstr($proxy_group_name)));
		}
	}

	public function testFormAdministrationProxyGroups_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::SQL);
		$this->page->login()->open('zabbix.php?action=proxygroup.list')->waitUntilReady();
		$this->query('link', self::CLONE_GROUP)->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Update')->waitUntilClickable()->one()->click();
		$dialog->ensureNotPresent();

		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Proxy group updated');

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	public function getCancelData() {
		return [
			[
				[
					'action' => 'Create'
				]
			],
			[
				[
					'action' => 'Update'
				]
			],
			[
				[
					'action' => 'Delete'
				]
			],
			[
				[
					'action' => 'Clone'
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormAdministrationProxyGroups_Cancel($data) {
		$old_hash = CDBHelper::getHash(self::SQL);
		$this->page->login()->open('zabbix.php?action=proxygroup.list');

		$new_fields = [
			'Name' => 'New name',
			'Failover period' => '333s',
			'Minimum number of proxies' => 444,
			'Description' => 'Updated value that should not be saved'
		];

		if ($data['action'] === 'Create') {
			$this->query('button:Create proxy group')->one()->waitUntilClickable()->click();
		}
		else {
			$this->query('link', self::CLONE_GROUP)->one()->waitUntilClickable()->click();
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if ($data['action'] === 'Delete') {
			$dialog->query('button', $data['action'])->waitUntilClickable()->one()->click();
			$this->assertTrue($this->page->isAlertPresent());
			$this->page->dismissAlert();
			$dialog->close();
		}
		else {
			if ($data['action'] === 'Clone') {
				$dialog->query('button:Clone')->waitUntilClickable()->one()->click();
				$dialog->invalidate();
			}

			$form = $dialog->asForm();
			$form->fill($new_fields);

			$dialog->query('button:Cancel')->one()->waitUntilClickable()->click();
			$dialog->ensureNotPresent();
		}

		$this->assertTrue($this->query('link', self::CLONE_GROUP)->exists());

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	public static function getDeleteData() {
		return [
			// Attempt to delete a proxy group that has proxies assigned.
			[
				[
					'expected' => TEST_BAD,
					'group' => 'Default values - recovering',
					'error' => 'Proxy group "Default values - recovering" is used by proxy "passive_proxy7".'
				]
			],
			// Attempt to delete a proxy group that has no proxies but has an assigned host.
			[
				[
					'expected' => TEST_BAD,
					'group' => 'Group without proxies with linked host',
					'error' => 'Host "Host linked to proxy group" is monitored by proxy group "Group without proxies'.
							' with linked host".'
				]
			],
			// Delete a proxy group that has nothing linked to it.
			[
				[
					'group' => 'Group without proxies'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormAdministrationProxyGroups_Delete($data) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);
		if ($expected === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$this->page->login()->open('zabbix.php?action=proxygroup.list')->waitUntilReady();
		$this->query('link', $data['group'])->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Delete')->waitUntilClickable()->one()->click();

		// Check alert.
		$this->assertTrue($this->page->isAlertPresent());
		$this->assertEquals('Delete selected proxy group?', $this->page->getAlertText());
		$this->page->acceptAlert();

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot delete proxy group', $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));

			// Close dialog.
			$dialog->close();
		}
		else {
			$dialog->ensureNotPresent();
			$this->assertMessage(TEST_GOOD, 'Proxy group deleted');

			// Check DB.
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM proxy_group WHERE name='.zbx_dbstr($data['group'])));
		}

		// Check proxy group presence/absence in frontend.
		$this->assertEquals($expected, $this->query('link', $data['group'])->exists());
	}
}
