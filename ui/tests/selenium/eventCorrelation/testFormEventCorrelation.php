<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup correlation
 *
 * @onBefore prepareEventData
 */
class testFormEventCorrelation extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public function prepareEventData() {
		CDataHelper::call('correlation.create', [
			[
				'name' => 'Event correlation for cancel',
				'description' => 'Test description cancel',
				'status' => ZBX_CORRELATION_DISABLED,
				'filter' => [
					'evaltype' => 1,
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
							'tag' => 'cancel tag'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			],
			[
				'name' => 'Event correlation for clone',
				'description' => 'Test description clone',
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
							'tag' => 'clone tag'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			],
			[
				'name' => 'Event correlation for delete',
				'description' => 'Test description delete',
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
							'tag' => 'delete tag'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			],
			[
				'name' => 'Event correlation for update',
				'description' => 'Test description update',
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
							'tag' => 'update tag'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			]
		]);
	}

	public static function create() {
		return [
			[
				[
					'fields' => [
						'Name' => 'Test create with all fields',
						'Description' => 'Event correlation with description',
						'Enabled' => false,
						'Close new event' => true

					],
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
						'Tag' => 'Test tag'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Test create with minimum fields',
						'Close old events' => true
					],
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
						'Tag' => 'Test tag'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Test create with both operations selected',
						'Close old events' => true,
						'Close new event' => true
					],
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
						'Tag' => 'Test tag'
					]
				]
			]
		];
	}

	/**
	 * Test creation of a event correlation with all possible fields and with default values.
	 *
	 * @dataProvider create
	 */
	public function testFormEventCorrelation_Create($data) {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->query('button:Create event correlation')->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		$this->assertEquals('New event correlation', $dialog->getTitle());
		$form->fill($data['fields']);

		$form->getField('Conditions')->query('button:Add')->one()->click();
		$condition_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$condition_form = $condition_dialog->query('id:correlation-condition-form')->asForm()->one();
		$this->assertEquals('New condition', $condition_dialog->getTitle());

		$condition_form->fill($data['tag_fields']);

		$condition_form->submit();
		$condition_dialog->waitUntilNotVisible();

		$form->submit();
		$dialog->ensureNotPresent();

		$this->assertMessage(TEST_GOOD, 'Event correlation created');
		$this->zbxTestTextPresent($data['fields']['Name']);

		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['fields']['Name']);
		$this->assertEquals(1, CDBHelper::getCount($sql));
	}

	public static function validation() {
		return [
			[
				[
					'error_header' => 'Cannot create event correlation',
					'error_messages' => [
						'Incorrect value for field "name": cannot be empty.',
						'Field "conditions" is mandatory.'
					]
				]
			],
			[
				[
					'name' => 'Without conditions',
					'error_header' => 'Cannot create event correlation',
					'error_messages' => [
						'Field "conditions" is mandatory.'
					]
				]
			],
			[
				[
					'name' => 'Without operation',
					'tag' => 'tag name',
					'error_header' => 'Cannot create event correlation',
					'error_messages' => [
						'Invalid parameter "/1/operations": cannot be empty.'
					]
				]
			]
		];
	}

	/**
	 * Test form validations.
	 *
	 * @dataProvider validation
	 */
	public function testFormEventCorrelation_CreateValidation($data) {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->query('button:Create event correlation')->one()->click();
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		if (array_key_exists('name', $data)) {
			$form->getField('Name')->fill($data['name']);
		}

		if (array_key_exists('tag', $data)) {
			$form->getField('Conditions')->query('button:Add')->one()->click();
			$condition_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
			$condition_form = $condition_dialog->query('id:correlation-condition-form')->asForm()->one();

			$condition_form->getField('Tag')->fill($data['tag']);
			$condition_form->submit();
			$condition_dialog->waitUntilNotVisible();
		}

		$form->submit();

		$this->assertMessage(TEST_BAD, $data['error_header'], $data['error_messages']);
		$dialog->close();

		if (array_key_exists('name', $data) && $data['name'] === 'Event correlation for update') {
			$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
			$this->assertEquals(1, CDBHelper::getCount($sql));
		}

		if (array_key_exists('name', $data) && $data['name'] != 'Event correlation for update') {
			$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
			$this->assertEquals(0, CDBHelper::getCount($sql));
		}
	}

	public function testFormEventCorrelation_LongNameValidation() {
		$name = 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_'
				. 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_'
				. 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name';
		$db_name = 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_'
				. 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_'
				. 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_';

		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->query('button:Create event correlation')->one()->click();
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->getField('Name')->fill($name);

		$form->getField('Conditions')->query('button:Add')->one()->click();
		$condition_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$condition_form = $condition_dialog->query('id:correlation-condition-form')->asForm()->one();
		$condition_form->getField('Tag')->fill('Test tag');
		$condition_form->submit();
		$condition_dialog->waitUntilNotVisible();

		$form->getField('Close old events')->fill(true);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Event correlation created');

		// Name longer than 255 symbols is truncated on frontend, check the shortened name in DB
		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($db_name);
		$this->assertEquals(1, CDBHelper::getCount($sql));
	}

	public static function tags() {
		return [
			[
				[
					'name' => 'Test create with New event host group equals',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('New event host group'),
						'Operator' => 'equals',
						'Host groups' => 'Zabbix servers'
					]

				]
			],
			[
				[
					'name' => 'Test create with New event host group does not equal',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('New event host group'),
						'Operator' => 'does not equal',
						'Host groups' => 'Zabbix servers'
					]
				]
			],
			[
				[
					'name' => 'Test create with Event tag pair',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Event tag pair'),
						'Old tag name' => 'Old tag',
						'New tag name' => 'New tag'
					]
				]
			],
			[
				[
					'name' => 'Test create with Old event tag value equals tag',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Old event tag value'),
						'Tag' => 'TagTag',
						'Operator' => 'equals',
						'Value' => 'TagValue'
					]
				]
			],
			[
				[
					'name' => 'Test create with Old event tag value equals Empty',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Old event tag value'),
						'Tag' => 'TagTag',
						'Operator' => 'equals',
						'Value' => ''
					]
				]
			],
			[
				[
					'name' => 'Test create with Old event tag value does not equal tag',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Old event tag value'),
						'Tag' => 'TagTag',
						'Operator' => 'does not equal',
						'Value' => 'TagValue'
					]
				]
			],
			[
				[
					'name' => 'Test create with Old event tag value contains tag',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Old event tag value'),
						'Tag' => 'TagTag',
						'Operator' => 'contains',
						'Value' => 'TagValue'
					]
				]
			],
			[
				[
					'name' => 'Test create with Old event tag value does not contain tag',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Old event tag value'),
						'Tag' => 'TagTag',
						'Operator' => 'does not contain',
						'Value' => 'TagValue'
					]
				]
			],
			[
				[
					'name' => 'Test create with New event tag value equals tag',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('New event tag value'),
						'Tag' => 'TagTag',
						'Operator' => 'equals',
						'Value' => 'TagValue'
					]
				]
			],
			[
				[
					'name' => 'Test create with New event tag value equals Empty',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('New event tag value'),
						'Tag' => 'TagTag',
						'Operator' => 'equals',
						'Value' => ''
					]
				]
			],
			[
				[
					'name' => 'Test create with New event tag value does not equal tag',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('New event tag value'),
						'Tag' => 'TagTag',
						'Operator' => 'does not equal',
						'Value' => 'TagValue'
					]
				]
			],
			[
				[
					'name' => 'Test create with New event tag value contains tag',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('New event tag value'),
						'Tag' => 'TagTag',
						'Operator' => 'contains',
						'Value' => 'TagValue'
					]
				]
			],
			[
				[
					'name' => 'Test create with New event tag value does not contain tag',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('New event tag value'),
						'Tag' => 'TagTag',
						'Operator' => 'does not contain',
						'Value' => 'TagValue'
					]
				]
			]
		];
	}

	/**
	 * Test creation with different conditions.
	 *
	 * @dataProvider tags
	 */
	public function testFormEventCorrelation_TestTags($data) {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->query('button:Create event correlation')->one()->click();
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->getField('Name')->fill($data['name']);

		$form->getField('Conditions')->query('button:Add')->one()->click();
		$condition_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$condition_form = $condition_dialog->query('id:correlation-condition-form')->asForm()->one();

		$condition_form->fill($data['tag_fields']);
		$condition_form->submit();
		$condition_dialog->waitUntilNotVisible();

		$form->getField('Close old events')->fill(true);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Event correlation created');

		$this->zbxTestTextPresent($data['name']);
		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
		$this->assertEquals(1, CDBHelper::getCount($sql));
	}

	public static function tagsValidation() {
		return [
			[
				[
					'name' => 'Test empty New event tag',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('New event tag name')
					],
					'error_message' => 'Incorrect value for field "tag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty New event host group',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('New event host group')
					],
					'error_message' => 'Incorrect value for field "groupid": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty Old tag in Event tag pair',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Event tag pair'),
						'New tag name' => 'New tag'
					],
					'error_message' => 'Incorrect value for field "oldtag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty New tag in Event tag pair',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Event tag pair'),
						'Old tag name' => 'Old tag'
					],
					'error_message' => 'Incorrect value for field "newtag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty tag in Old event tag value',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('Old event tag value')
					],
					'error_message' => 'Incorrect value for field "tag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty tag in New event tag value',
					'tag_fields' => [
						'Type' => CFormElement::RELOADABLE_FILL('New event tag value')
					],
					'error_message' => 'Incorrect value for field "tag": cannot be empty.'
				]
			]
		];
	}

	/**
	 * Test condition value validations.
	 *
	 * @dataProvider tagsValidation
	 */
	public function testFormEventCorrelation_CheckEmptyTagsValue($data) {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->query('button:Create event correlation')->one()->click();
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->getField('Name')->fill($data['name']);

		$form->getField('Conditions')->query('button:Add')->one()->click();
		$condition_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$condition_form = $condition_dialog->query('id:correlation-condition-form')->asForm()->one();

		$condition_form->fill($data['tag_fields']);

		$condition_form->submit();
		$this->assertMessage(TEST_BAD, null, $data['error_message']);
		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	public static function calculation() {
		return [
			[
				[
					'name' => 'Test create with calculation And/Or',
					'tags' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
							'Tag' => 'Test tag1'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag2'
						]
					]
				]
			],
			[
				[
					'name' => 'Test create with calculation And',
					'tags' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
							'Tag' => 'Test tag1'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag2'
						]
					],
					'calculation' => 'And'
				]
			],
			[
				[
					'name' => 'Test create with calculation OR',
					'tags' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
							'Tag' => 'Test tag1'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag2'
						]
					],
					'calculation' => 'Or'
				]
			],
			[
				[
					'name' => 'Test create with calculation Custom',
					'tags' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
							'Tag' => 'Test tag1'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag2'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag3'
						]
					],
					'calculation' => 'Custom expression',
					'formula' => 'A or (B and not C)'
				]
			]
		];
	}

	/**
	 * Test all types of calculation.
	 *
	 * @dataProvider calculation
	 */
	public function testFormEventCorrelation_CreateCalculation($data) {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->query('button:Create event correlation')->one()->click();
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->getField('Name')->fill($data['name']);

		foreach ($data['tags'] as $tag) {
			$form->getField('Conditions')->query('button:Add')->one()->click();
			$condition_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
			$condition_form = $condition_dialog->query('id:correlation-condition-form')->asForm()->one();

			$condition_form->fill($tag);

			$condition_form->submit();
			$condition_dialog->waitUntilNotVisible();
		}

		if (array_key_exists('calculation', $data)) {
			$form->getField('id:evaltype')->fill($data['calculation']);
			if ($data['calculation'] === 'Custom expression') {
				$form->query('id:formula')->waitUntilPresent()->one()->fill($data['formula']);
			}
		}

		$form->getField('Close old events')->fill(true);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Event correlation created');

		$this->zbxTestTextPresent($data['name']);
		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
		$this->assertEquals(1, CDBHelper::getCount($sql));
	}

	public static function formulaValidation() {
		return [
			// #0
			[
				[
					'name' => 'Test create with empty expression',
					'tags' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
							'Tag' => 'Test tag1'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag2'
						]
					],
					'formula' => '',
					'error_message' => 'Invalid parameter "/1/filter/formula": cannot be empty.'
				]
			],
			// #1
			[
				[
					'name' => 'Test create with missing argument',
					'tags' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
							'Tag' => 'Test tag1'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag2'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag value'),
							'Tag' => 'Test tag3',
							'Operator' => 'contains',
							'Value' => 'Value'
						]
					],
					'formula' => 'A or B',
					'error_message' => 'Invalid parameter "/1/filter/conditions": incorrect number of conditions.'
				]
			],
			// #2
			[
				[
					'name' => 'Test create with extra argument',
					'tags' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
							'Tag' => 'Test tag1'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag2'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag value'),
							'Tag' => 'Test tag3',
							'Operator' => 'contains',
							'Value' => 'Value'
						]
					],
					'formula' => '(A or B) and (C or D)',
					'error_message' => 'Invalid parameter "/1/filter/conditions": incorrect number of conditions.'
				]
			],
			// #3
			[
				[
					'name' => 'Test create with wrong formula',
					'tags' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
							'Tag' => 'Test tag1'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag2'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag value'),
							'Tag' => 'Test tag3',
							'Operator' => 'contains',
							'Value' => 'Value'
						]
					],
					'formula' => 'Wrong formula',
					'error_message' => 'Invalid parameter "/1/filter/formula": incorrect syntax near "Wrong formula".'
				]
			],
			// #4
			[
				[
					'name' => 'Check case sensitive of operator in formula',
					'tags' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
							'Tag' => 'Test tag1'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag2'
						]
					],
					'formula' => 'A and Not B',
					'error_message' => 'Invalid parameter "/1/filter/formula": incorrect syntax near "Not B".'
				]
			],
			// #5
			[
				[
					'name' => 'Check case sensitive of first operator in formula',
					'tags' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
							'Tag' => 'Test tag1'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag2'
						]
					],
					'formula' => 'NOT A and not B',
					'error_message' => 'Invalid parameter "/1/filter/formula": incorrect syntax near " A and not B".'
				]
			],
			// #6
			[
				[
					'name' => 'Test create with only NOT in formula',
					'tags' => [
						[
							'Type' => CFormElement::RELOADABLE_FILL('Old event tag name'),
							'Tag' => 'Test tag1'
						],
						[
							'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
							'Tag' => 'Test tag2'
						]
					],
					'formula' => 'not A not B',
					'error_message' => 'Invalid parameter "/1/filter/formula": incorrect syntax near " not B".'
				]
			]
		];
	}

	/**
	 * Test custom expression field validation.
	 *
	 * @dataProvider formulaValidation
	 */
	public function testFormEventCorrelation_FormulaValidation($data) {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->query('button:Create event correlation')->one()->click();
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->getField('Name')->fill($data['name']);

		foreach ($data['tags'] as $tag) {
			$form->getField('Conditions')->query('button:Add')->one()->click();
			$condition_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
			$condition_form = $condition_dialog->query('id:correlation-condition-form')->asForm()->one();
			$condition_form->fill($tag);

			$condition_form->submit();
			$condition_dialog->waitUntilNotVisible();
		}

		$form->getField('id:evaltype')->fill('Custom expression');
		$form->query('id:formula')->waitUntilPresent()->one()->fill($data['formula']);

		$form->getField('Close old events')->fill(true);
		$form->submit();

		$this->assertMessage(TEST_BAD, 'Cannot create event correlation', $data['error_message']);
		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	public function testFormEventCorrelation_Clone() {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->zbxTestClickLinkTextWait('Event correlation for clone');
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog->query('button:Clone')->one()->click();

		$form = $dialog->asForm();
		$form->fill(['Name' => 'Cloned correlation']);
		$form->submit();

		$this->assertMessage(TEST_GOOD, 'Event correlation created');
		$this->zbxTestTextPresent('Cloned correlation');

		$sql = "SELECT NULL FROM correlation WHERE name='Cloned correlation' AND description='Test description clone'";
		$this->assertEquals(1, CDBHelper::getCount($sql));

		$sql = "SELECT NULL FROM correlation WHERE name='Event correlation for clone' AND description='Test description clone'";
		$this->assertEquals(1, CDBHelper::getCount($sql));

		$sql = "SELECT NULL FROM corr_condition_tag WHERE tag='clone tag'";
		$this->assertEquals(2, CDBHelper::getCount($sql));
	}

	/**
	 * Test update without any modification of event correlation data.
	 */
	public function testFormEventCorrelation_UpdateNone() {
		$sql_hash = 'SELECT * FROM correlation ORDER BY correlationid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->zbxTestClickLinkTextWait('Event correlation for update');
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->query('button:Update')->waitUntilClickable()->one()->click();

		$this->assertMessage(TEST_GOOD, 'Event correlation updated');
		$this->zbxTestTextPresent('Event correlation for update');

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	public function testFormEventCorrelation_UpdateAllFields() {
		$fields = [
			'Name' => 'New event correlation for update',
			'Description' => 'New test description update',
			'Close old events' => false,
			'Close new event' => true,
			'Enabled' => false
		];
		$tag = [
			'Type' => CFormElement::RELOADABLE_FILL('New event tag name'),
			'Tag' => 'New update tag'
		];

		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->zbxTestClickLinkTextWait('Event correlation for update');
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->fill($fields);

		$conditions_field = $form->getField('Conditions');
		$conditions_field->query('button:Remove')->one()->click();
		$conditions_field->query('button:Add')->one()->click();
		$condition_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$condition_form = $condition_dialog->query('id:correlation-condition-form')->asForm()->one();

		$condition_form->fill($tag);
		$condition_form->submit();
		$condition_dialog->waitUntilNotVisible();

		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Event correlation updated');
		$this->zbxTestTextPresent('New event correlation for update');

		$sql = "SELECT NULL FROM correlation WHERE name='New event correlation for update' AND description='New test description update'";
		$this->assertEquals(1, CDBHelper::getCount($sql));

		$sql = "SELECT NULL FROM correlation WHERE name='Event correlation for update'";
		$this->assertEquals(0, CDBHelper::getCount($sql));

		$sql = "SELECT NULL FROM corr_condition_tag WHERE tag='New update tag'";
		$this->assertEquals(1, CDBHelper::getCount($sql));

		$sql = "SELECT NULL FROM corr_condition_tag WHERE tag='update tag'";
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	public function testFormEventCorrelation_Delete() {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->zbxTestClickLinkTextWait('Event correlation for delete');
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		COverlayDialogElement::find()->waitUntilReady()->one()->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->page->acceptAlert();

		$this->assertMessage(TEST_GOOD, 'Event correlation deleted');
		$this->zbxTestTextNotVisible('Event correlation for delete');

		$sql = "SELECT NULL FROM correlation WHERE name='Event correlation for delete'";
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	public function testFormEventCorrelation_Cancel() {
		$sql_hash = 'SELECT * FROM correlation ORDER BY correlationid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->zbxTestClickLinkTextWait('Event correlation for cancel');
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestCheckTitle('Event correlation rules');

		COverlayDialogElement::find()->waitUntilReady()->one()->query('button:Cancel')->waitUntilClickable()->one()->click();

		$this->zbxTestTextPresent('Event correlation for cancel');

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}
}
