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
require_once dirname(__FILE__).'/../traits/TableTrait.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * Base class for Value mappings function tests.
 */
class testFormValueMappings extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	const HOSTID = 99134;	// ID of the host for valuemap update.
	const TEMPLATEID = 40000;	// ID of the template for valuemap update.
	const UPDATE_VALUEMAP1 = 'Valuemap for update 1';
	const UPDATE_VALUEMAP2 = 'Valuemap for update 2';
	const DELETE_VALUEMAP = 'Valuemap for delete';
	const EXISTING_VALUEMAPS = [
		[
			'Name' => 'Valuemap for delete',
			'Value' => "=oneoneoneoneoneoneoneoneoneoneone\n⇒\n11111111111\n=two\n⇒\n2\n=threethreethreethreethreethree".
					"threethreethreethree\n⇒\n3333333333\n…",
			'Action' => 'Remove'
		],
		[
			'Name' => 'Valuemap for update 1',
			'Value' => "=\n⇒\nreference newvalue",
			'Action' => 'Remove'
		],
		[
			'Name' => 'Valuemap for update 2',
			'Value' => "=\n⇒\nno data\n=1\n⇒\none\n=2\n⇒\ntwo\n…",
			'Action' => 'Remove'
		]
	];

	/**
	 * Function that checks the layout of the Value mappings tab in Host or Template configuration forms.
	 *
	 * @param string $source	Entity (host or template) for which the scenario is executed.
	 */
	public function checkLayout($source) {
		// Open value mapping tab.
		$this->openValueMappingTab($source);

		// Check value mapping table headers and content.
		$table = $this->query('id:valuemap-table')->asTable()->one();
		$this->assertEquals(['Name', 'Value', 'Action'], $table->getHeadersText());
		$this->assertTableData(self::EXISTING_VALUEMAPS, 'id:valuemap-formlist');

		// Check value mapping configuration form layout.
		$this->query('name:valuemap_add')->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('Value mapping', $dialog->getTitle());
		$mapping_form = $dialog->getContent()->asForm();

		$labels = $mapping_form->query('xpath:.//label')->all()->asText();
		$this->assertEquals(['Name', 'Mappings'], $labels);
		$this->assertEquals('64', $mapping_form->query('id:name')->one()->getAttribute('maxlength'));

		// Check mappings table layout.
		$mappings_table = $mapping_form->query('id:mappings_table')->asTable()->one();
		$this->assertEquals(['', 'Type', 'Value', '', 'Mapped to', 'Action', ''], $mappings_table->getHeadersText());
		$row = $mappings_table->getRow(0);
		foreach (['Value', 'Mapped to'] as $mapping_column) {
			$mapping_field = $row->getColumn($mapping_column)->query('xpath:.//input')->one();
			$this->assertEquals('64', $mapping_field->getAttribute('maxlength'));
		}
		$this->assertEquals(1, $row->query('xpath:.//td[text()="⇒"]')->all()->count());
		$this->assertTrue($row->query('button:Remove')->one()->isClickable());

		// Check that both overlay control buttons are clickable.
		$this->assertEquals(2, $dialog->getFooter()->query('button', ['Add', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count());
	}

	/**
	 * Function that create clone and full clone of a host/template and verifies that value mappings were copied.
	 *
	 * @param string $source	Entity (host or template) for which the scenario is executed.
	 */
	public function checkClone($source, $action = 'Clone') {
		// Create a clone and or a full clone of an existing host/template with value mappings.
		$this->openValueMappingTab($source, true, false);
		$this->query('button', $action)->one()->click();
		$form = $this->query('name:'.$source.'sForm')->asForm()->one();
		$form->getField(ucfirst($source).' name')->fill($action.' Valuemap Test');
		$form->submit();

		// Get the id of the created host/template clone.
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr($action.' Valuemap Test'));

		// Check value mappings were copied correctly.
		$this->page->open($source.'s.php?form=update&'.$source.'id='.$hostid);
		$form = $this->query('name:'.$source.'sForm')->asForm()->waitUntilVisible()->one();
		$form->selectTab('Value mapping');

		$this->assertTableData(self::EXISTING_VALUEMAPS, 'id:valuemap-formlist');
	}

	public function getValuemapData() {
		return [
			// Successful creation/update of value mapping with multiple mappings.
			[
				[
					'name' => 'ABC!@#$%^&*()_+=[].абвгдеёжзāīōēūšķļ€‡Œ™£¥©µ¾ÆÖÕæƩƴƵɷʁΔβφψϾֆ۝ܫज',
					'mappings' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => '   ',
							'newvalue' => 'jaunā vērtība'
						],
						[
							'value' => '   2 два   ',
							'newvalue' => 'один + один'
						],
						[
							'value' => 'duplicate newvalue',
							'newvalue' => 'один + один'
						],
						[
							'value' => '1_WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW',
							'newvalue' => 'WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW'
						],
						[
							'value' => 'mapping not shown',
							'newvalue' => '  not shown on screenshot   '
						]

					],
					'trim' => true,
					'update valuemap' => self::UPDATE_VALUEMAP1,
					'screenshot id' => 'ValuemapScreenshot1'
				]
			],
			// TODO: remove the "skip_for_update" flag when ZBX-19105 is fixed.
			// Value mapping with duplicate name.
			[
				[
					'skip_for_update' =>true,
					'expected' => TEST_BAD,
					'name' => '  Valuemap for delete  ',
					'mappings' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'one',
							'newvalue' => '1'
						],
						[
							'value' => 'two',
							'newvalue' => '2'
						],
						[
							'value' => 'three',
							'newvalue' => '3'
						]
					],
					'error_details' => 'Incorrect value for field "Name": value (Valuemap for delete) already exists.'
				]
			],
			// Empty name.
			[
				[
					'expected' => TEST_BAD,
					'name' => '',
					'mappings' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'one',
							'newvalue' => '1'
						]
					],
					'error_details' => 'Incorrect value for field "Name": cannot be empty.'
				]
			],
			// No mappings.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Valuemap without mappings',
					'remove_all' => true,
					'error_details' => 'Incorrect value for field "Mappings": cannot be empty.'
				]
			],
			// Empty Mapped to field.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Empty mapped to',
					'mappings' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'one',
							'newvalue' => '1'
						],
						[
							'value' => 'two',
							'newvalue' => '2'
						],
						[
							'value' => 'three',
							'newvalue' => ''
						]
					],
					'error_details' => 'Incorrect value for field "Mapped to": cannot be empty.'
				]
			],
			// Space in Name field.
			[
				[
					'expected' => TEST_BAD,
					'name' => '   ',
					'mappings' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'one',
							'newvalue' => '1'
						]
					],
					'error_details' => 'Incorrect value for field "Name": cannot be empty.'
				]
			],
			// Space in mapped to field.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Empty mapped to',
					'mappings' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'one',
							'newvalue' => '1'
						],
						[
							'value' => 'two',
							'newvalue' => '2'
						],
						[
							'value' => 'three',
							'newvalue' => '    '
						]
					],
					'error_details' => 'Incorrect value for field "Mapped to": cannot be empty.'
				]
			],
			// Duplicate value field values within the same value mapping.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Valuemap with duplicate values',
					'mappings' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'one',
							'newvalue' => '1'
						],
						[
							'value' => 'two',
							'newvalue' => '2'
						],
						[
							'value' => '  one  ',
							'newvalue' => '1 again'
						]
					],
					'error_details' => 'Incorrect value for field "Value": value (one) already exists.'
				]
			]
		];
	}

	/**
	 * Function that checks the layout of the value mappings tab in Host or Template configuration forms.
	 *
	 * @param array $data		Data provider
	 * @param string $source	Entity (host or template) for which the scenario is executed.
	 * @param string $action	Action to be performed with value mappings.
	 */
	public function checkAction($data, $source, $action) {
		// TODO: Remove the below condition once ZBX-19105 is fixed.
		if (CTestArrayHelper::get($data, 'skip_for_update') && $action === 'update') {
			return;
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$sql = 'SELECT * FROM valuemap v INNER JOIN valuemap_mapping vm ON vm.valuemapid = v.valuemapid';
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->openValueMappingTab($source);

		// Add a new value mapping or open the value mapping to be updated.
		$this->query(($action === 'create')
			? 'name:valuemap_add'
			: 'link:'.CTestArrayHelper::get($data, 'update valuemap', self::UPDATE_VALUEMAP2)
		)->one()->click();

		// Fill in the name of the valuemap and the parameters of its mappings.
		$dialog = COverlayDialogElement::find()->asForm()->waitUntilVisible()->one();
		$dialog->query('xpath:.//input[@id="name"]')->one()->fill($data['name']);

		$mapping_table = $dialog->query('id:mappings_table')->asMultifieldTable()->one();
		if (CTestArrayHelper::get($data, 'remove_all')) {
			$mapping_table->clear();
		}
		else {
			$mapping_table->fill($data['mappings']);
		}
		$dialog->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error_details']);
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}
		else {
			// Save the configuration of the host with created/updated value mappings.
			COverlayDialogElement::ensureNotPresent();
			$this->query('button:Update')->waitUntilClickable()->one()->click();
			$this->assertMessage(TEST_GOOD, ucfirst($source).' updated');

			// Check the screenshot of the whole value mappings tab.
			$this->openValueMappingTab($source, false);
			$this->assertScreenshot($this->query('id:valuemap-tab')->one(), $action.$data['screenshot id']);

			// Check all mappings that belong to the created value mapping.
			$this->query('link', $data['name'])->one()->click();
			$this->checkMappings($data);
		}
	}

	/**
	 * Function that checks configuration of the created/updated value mapping.
	 *
	 * @param array $data	Data provider
	 */
	private function checkMappings($data) {
		$dialog = COverlayDialogElement::find()->asForm()->waitUntilVisible()->one();
		$mappings_table = $this->query('id:mappings_table')->asMultifieldTable()->one();

		// Check value mapping name.
		$this->assertEquals($data['name'], $dialog->query('xpath:.//input[@id="name"]')->one()->getValue());

		// Remove unnecessary mapping parameters from the reference array and trim trailing and leading spaces.
		$mappings = $data['mappings'];
		foreach($mappings as &$mapping) {
			unset($mapping['action'], $mapping['index']);

			if (CTestArrayHelper::get($data, 'trim', false)) {
				$mapping = array_map('trim', $mapping);
			}
		}
		unset($mapping);

		$mappings_table->checkValue($mappings);
	}

	/**
	 * Function that opens the configuration form of a host/template and switches to the value mapping tab.
	 *
	 * @param string $source		Entity (host or template) configuration of which should be opened.
	 * @param boolean $login		Flag that determines if a login is required.
	 * @param boolean $open_tab		Flag that determines if opening the value mapping tab is required.
	 */
	private function openValueMappingTab($source, $login = true, $open_tab = true) {
		$sourceid = ($source === 'host') ? self::HOSTID : self::TEMPLATEID;
		if ($login) {
			$this->page->login();
		}
		$this->page->open($source.'s.php?form=update&'.$source.'id='.$sourceid);
		$form = $this->query('name:'.$source.'sForm')->asForm()->waitUntilVisible()->one();
		if ($open_tab) {
			$form->selectTab('Value mapping');
		}
	}

	/**
	 * Function that checks that no database changes occurred if nothing was actually changed during update.
	 *
	 * @param string $source		Entity (host or template) for which the scenario is executed.
	 */
	public function checkSimpleUpdate($source) {
		$sql = 'SELECT * FROM valuemap v INNER JOIN valuemap_mapping vm ON vm.valuemapid = v.valuemapid ORDER BY v.name, vm.sortorder';
		$old_hash = CDBHelper::getHash($sql);

		// Open configuration of a value mapping and save it without making any changes.
		$this->openValueMappingTab($source);
		$this->query('link', self::UPDATE_VALUEMAP1)->one()->click();
		$dialog = COverlayDialogElement::find()->asForm()->waitUntilVisible()->one();
		$dialog->submit()->waitUntilNotVisible();
		$this->query('button:Update')->one()->click();

		// Check that no changes occurred in the database.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Function that checks that no changes in the database are made in case if value mapping update is cancelled.
	 *
	 * @param string $source		Entity (host or template) for which the scenario is executed.
	 */
	public function checkCancel($source) {
		// New values of value mapping fields.
		$fields = [
			'name' => 'Updated valuemap name',
			'mappings' => [
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 0,
					'value' => 'new value',
					'newvalue' => 'new newvalue'
				],
				[
					'action' => USER_ACTION_REMOVE,
					'index' => 2
				],
				[
					'value' => 'new mapping value',
					'newvalue' => 'new mapping newvalue'
				]
			]
		];

		$sql = 'SELECT * FROM valuemap v INNER JOIN valuemap_mapping vm ON vm.valuemapid = v.valuemapid';
		$old_hash = CDBHelper::getHash($sql);

		// Open value mapping configuration and update its fields.
		$this->openValueMappingTab($source);
		$this->query('link', self::UPDATE_VALUEMAP2)->one()->click();
		$dialog = COverlayDialogElement::find()->asForm()->waitUntilVisible()->one();
		$dialog->query('xpath:.//input[@id="name"]')->one()->fill($fields['name']);
		$dialog->query('id:mappings_table')->asMultifieldTable()->one()->fill($fields['mappings']);

		// Submit the value mapping configuration dialog, but Cancel the update of the host/template.
		$dialog->submit()->waitUntilNotVisible();
		$this->query('button:Cancel')->one()->click();

		// Check that no changes occurred in the database.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Function that checks value mapping deletion.
	 *
	 * @param string $source		Entity (host or template) for which the scenario is executed.
	 */
	public function checkDelete($source) {
		// Get the Database records that correspond to the value mapping to be deleted and its mappings.
		$valuemap_sql = 'SELECT valuemapid FROM valuemap WHERE name=';
		$mappings_sql = 'SELECT valuemap_mappingid FROM valuemap_mapping WHERE valuemapid=';
		$valuemap_id = CDBHelper::getValue($valuemap_sql.zbx_dbstr(self::DELETE_VALUEMAP));

		// Delete the value mapping.
		$this->openValueMappingTab($source);
		$table = $this->query('id:valuemap-table')->asTable()->one();
		$table->findRow('Name', self::DELETE_VALUEMAP)->query('button:Remove')->one()->click();
		$this->query('button:Update')->one()->click();

		// Check that the deleted value mapping and its mappings are not present in the DB.
		$this->assertEquals(0, CDBHelper::getCount($valuemap_sql.zbx_dbstr(self::DELETE_VALUEMAP)));
		$this->assertEquals(0, CDBHelper::getCount($mappings_sql.$valuemap_id));
	}

	/**
	 * Function that checks that valuemap data is not lost if there is an error when saving the host/template.
	 *
	 * @param string $source		Entity (host or template) for which the scenario is executed.
	 */
	public function checkSavingError($source) {
		// Value mapping data to be entered.
		$valuemap = [
			'name' => 'Updated valuemap name',
			'mappings' => [
				'action' => USER_ACTION_UPDATE,
				'index' => 0,
				'value' => 'received value',
				'newvalue' => 'new newvalue'
			]
		];

		// Value mapping data to be visible in the value mapping tab.
		$reference_valuemaps = [
			[
				'Name' => $valuemap['name'],
				'Value' => "=".$valuemap['mappings']['value']."\n⇒\n".$valuemap['mappings']['newvalue'],
				'Action' => 'Remove'
			]
		];

		// Create a new host/template, populate the hosthroup but leave the name empty.
		$this->page->login()->open($source.'s.php?form=create');
		$form = $this->query('name:'.$source.'sForm')->asForm()->waitUntilVisible()->one();
		$form->getField('Groups')->fill('Discovered hosts');

		// Open value mappings tab and add a value mapping.
		$form->selectTab('Value mapping');
		$this->query('name:valuemap_add')->one()->click();
		$dialog = COverlayDialogElement::find()->asForm()->waitUntilVisible()->one();
		$dialog->query('xpath:.//input[@id="name"]')->one()->fill($valuemap['name']);
		$dialog->query('id:mappings_table')->asMultifieldTable()->one()->fill($valuemap['mappings']);
		$dialog->submit()->waitUntilNotVisible();

		// Submit host/template configuration and wait for the error message to appear.
		$form->submit();
		CMessageElement::find()->waitUntilVisible()->one();

		// Check that the value mapping data is still populated.
		$this->assertTableData($reference_valuemaps, 'id:valuemap-formlist');
	}
}
