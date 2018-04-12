<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

/**
 * @backup correlation
 */

class testFormEventCorrelation extends CWebTest {

	public static function create() {
		return [
			[
				[
					'name' => 'Test create with all fields',
					'select_tag' => 'New event tag',
					'tag' => 'Test tag',
					'description' => 'Event correlation with description',
					'operation' => 'Close new event'
				]
			],
			[
				[
					'name' => 'Test create with minimum fields',
					'select_tag' => 'Old event tag',
					'tag' => 'Test tag'
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
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestDropdownSelectWait('new_condition_type', $data['select_tag']);
		$this->zbxTestInputType('new_condition_tag', $data['tag']);
		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');

		if (array_key_exists('description', $data)) {
			$this->zbxTestInputType('description', $data['description']);
		}

		$this->zbxTestTabSwitch('Operations');

		if (array_key_exists('operation', $data)) {
			$this->zbxTestDropdownSelect('new_operation_type', $data['operation']);
		}

		$this->zbxTestClickXpathWait('//button[contains(@onclick, \'add_operation\')]');
		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation added');
		$this->zbxTestTextPresent($data['name']);

		$this->zbxTestCheckFatalErrors();
		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
		$this->assertEquals(1, DBcount($sql));
	}

	public static function validation() {
		return [
			[
				[
					'error_header' => 'Page received incorrect data',
					'error_message' => 'Incorrect value for field "Name": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Event correlation for update',
					'error_header' => 'Cannot add correlation',
					'error_message' => 'Correlation "Event correlation for update" already exists.'
				]
			],
			[
				[
					'name' => 'Without conditions',
					'error_header' => 'Cannot add correlation',
					'error_message' => 'No "conditions" given for correlation "Without conditions".'
				]
			],
			[
				[
					'name' => 'Without operation',
					'tag' => 'tag name',
					'error_header' => 'Cannot add correlation',
					'error_message' => 'No "operations" given for correlation "Without operation".'
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
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputType('name', $data['name']);
		}

		if (array_key_exists('tag', $data)) {
			$this->zbxTestInputType('new_condition_tag', $data['tag']);
			$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');
		}

		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_header']);
		$error = $this->zbxTestGetText('//ul[@class=\'msg-details-border\']');
		$this->assertContains($data['error_message'], $error);

		$this->zbxTestCheckFatalErrors();

		if 	(array_key_exists('name', $data) && $data['name'] === 'Event correlation for update') {
			$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
			$this->assertEquals(1, DBcount($sql));
		}

		if (array_key_exists('name', $data) && $data['name'] != 'Event correlation for update') {
			$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
			$this->assertEquals(0, DBcount($sql));
		}
	}

	public function testFormEventCorrelation_LongNameValidation() {
		$name = 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_'
				. 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_'
				. 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name';
		$db_name = 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_'
				. 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_'
				. 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_';

		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', $name);
		$this->zbxTestInputType('new_condition_tag', 'Test tag');
		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');

		$this->zbxTestTabSwitch('Operations');
		$this->zbxTestClickXpathWait('//button[contains(@onclick, \'add_operation\')]');
		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation added');

		$this->zbxTestCheckFatalErrors();
		// Name longer than 255 symbols is truncated on frontend, check the shortened name in DB
		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($db_name);
		$this->assertEquals(1, DBcount($sql));
	}

	public static function tags() {
		return [
			[
				[
					'name' => 'Test create with New event host group =',
					'select_tag' => 'New event host group',
					'operator' => '='
				]
			],
			[
				[
					'name' => 'Test create with New event host group !=',
					'select_tag' => 'New event host group',
					'operator' => '<>'
				]
			],
			[
				[
					'name' => 'Test create with Event tag pair',
					'select_tag' => 'Event tag pair',
					'oldtag' => 'Old tag',
					'newtag' => 'New tag',
					'operator' => '='
				]
			],
			[
				[
					'name' => 'Test create with Old event tag value = tag',
					'select_tag' => 'Old event tag value',
					'tag' => 'TagTag',
					'operator' => '=',
					'value' => 'TagValue'
				]
			],
			[
				[
					'name' => 'Test create with Old event tag value = Empty',
					'select_tag' => 'Old event tag value',
					'tag' => 'TagTag',
					'operator' => '=',
					'value' => ''
				]
			],
			[
				[
					'name' => 'Test create with Old event tag value != tag',
					'select_tag' => 'Old event tag value',
					'tag' => 'TagTag',
					'operator' => '<>',
					'value' => 'TagValue'
				]
			],
			[
				[
					'name' => 'Test create with Old event tag value like tag',
					'select_tag' => 'Old event tag value',
					'tag' => 'TagTag',
					'operator' => 'like',
					'value' => 'TagValue'
				]
			],
			[
				[
					'name' => 'Test create with Old event tag value not like tag',
					'select_tag' => 'Old event tag value',
					'tag' => 'TagTag',
					'operator' => 'not like',
					'value' => 'TagValue'
				]
			],
			[
				[
					'name' => 'Test create with New event tag value = tag',
					'select_tag' => 'New event tag value',
					'tag' => 'TagTag',
					'operator' => '=',
					'value' => 'TagValue'
				]
			],
			[
				[
					'name' => 'Test create with New event tag value = Empty',
					'select_tag' => 'New event tag value',
					'tag' => 'TagTag',
					'operator' => '=',
					'value' => ''
				]
			],
			[
				[
					'name' => 'Test create with New event tag value != tag',
					'select_tag' => 'New event tag value',
					'tag' => 'TagTag',
					'operator' => '<>',
					'value' => 'TagValue'
				]
			],
			[
				[
					'name' => 'Test create with New event tag value like tag',
					'select_tag' => 'New event tag value',
					'tag' => 'TagTag',
					'operator' => 'like',
					'value' => 'TagValue'
				]
			],
			[
				[
					'name' => 'Test create with New event tag value not like tag',
					'select_tag' => 'New event tag value',
					'tag' => 'TagTag',
					'operator' => 'not like',
					'value' => 'TagValue'
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
		$host_group = 'Zabbix servers';

		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestDropdownSelectWait('new_condition_type', $data['select_tag']);
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);

		if ($data['select_tag'] === 'New event host group') {
			$this->zbxTestClickButtonMultiselect('new_condition_groupids_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkText($host_group);
		}

		if ($data['select_tag'] === 'Event tag pair') {
			$this->zbxTestInputTypeWait('new_condition_oldtag', $data['oldtag']);
			$this->zbxTestInputType('new_condition_newtag', $data['newtag']);
		}

		if ($data['select_tag'] === 'Old event tag value' || $data['select_tag'] === 'New event tag value') {
			$this->zbxTestInputType('new_condition_tag', $data['tag']);
			$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);
			$this->zbxTestInputType('new_condition_value', $data['value']);
		}

		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');
		$this->zbxTestWaitForPageToLoad();

		$this->zbxTestTabSwitch('Operations');
		$this->zbxTestClickXpathWait('//button[contains(@onclick, \'add_operation\')]');
		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation added');

		$this->zbxTestCheckFatalErrors();
		$this->zbxTestTextPresent($data['name']);
		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
		$this->assertEquals(1, DBcount($sql));
	}

	public static function tagsValidation() {
		return [
			[
				[
					'name' => 'Test empty New event tag',
					'select_tag' => 'New event tag',
					'error_message' => 'Incorrect value for field "tag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty New event host group',
					'select_tag' => 'New event host group',
					'error_message' => 'Incorrect value for field "groupid": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty Old tag in Event tag pair',
					'select_tag' => 'Event tag pair',
					'newtag' => 'New tag',
					'error_message' => 'Incorrect value for field "oldtag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty New tag in Event tag pair',
					'select_tag' => 'Event tag pair',
					'oldtag' => 'Old tag',
					'error_message' => 'Incorrect value for field "newtag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty tag in Old event tag value',
					'select_tag' => 'Old event tag value',
					'error_message' => 'Incorrect value for field "tag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty tag in New event tag value',
					'select_tag' => 'New event tag value',
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
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestDropdownSelectWait('new_condition_type', $data['select_tag']);
		$this->zbxTestWaitForPageToLoad();

		if ($data['select_tag'] === 'Event tag pair' && array_key_exists('newtag', $data)) {
			$this->zbxTestInputType('new_condition_newtag', $data['newtag']);
		}

		if ($data['select_tag'] === 'Event tag pair' && array_key_exists('oldtag', $data)) {
			$this->zbxTestInputType('new_condition_oldtag', $data['oldtag']);
		}

		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');

		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add correlation condition');
		$this->zbxTestAssertElementText('//ul[@class=\'msg-details-border\']', $data['error_message']);
		$this->zbxTestCheckFatalErrors();

		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
		$this->assertEquals(0, DBcount($sql));
	}

	public static function calculation() {
		return [
			[
				[
					'name' => 'Test create with calculation And/Or',
					'tags'=>[
						['select_tag' => 'Old event tag', 'tag_name' => 'Test tag1'],
						['select_tag' => 'New event tag', 'tag_name' => 'Test tag2'],
					]
				]
			],
			[
				[
					'name' => 'Test create with calculation And',
					'tags'=>[
						['select_tag' => 'Old event tag', 'tag_name' => 'Test tag1'],
						['select_tag' => 'New event tag', 'tag_name' => 'Test tag2']
					],
					'calculation' => 'And'
				]
			],
			[
				[
					'name' => 'Test create with calculation OR',
					'tags'=>[
						['select_tag' => 'Old event tag', 'tag_name' => 'Test tag1'],
						['select_tag' => 'New event tag', 'tag_name' => 'Test tag2'],
					],
					'calculation' => 'Or'
				]
			],
			[
				[
					'name' => 'Test create with calculation Custom',
					'tags'=>[
						['select_tag' => 'Old event tag', 'tag_name' => 'Test tag1'],
						['select_tag' => 'New event tag', 'tag_name' => 'Test tag2'],
						['select_tag' => 'New event tag', 'tag_name' => 'Test tag3'],

					],
				'calculation' => 'Custom expression',
				'formula' => 'A or (B and C)'
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
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', $data['name']);

		foreach ($data['tags'] as $tag) {
			$this->zbxTestDropdownSelectWait('new_condition_type', $tag['select_tag']);
			$this->zbxTestInputType('new_condition_tag', $tag['tag_name']);
			$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');
		}

		if (array_key_exists('calculation', $data)) {
			$this->zbxTestDropdownSelect('evaltype', $data['calculation']);
			if ($data['calculation'] === 'Custom expression') {
				$this->zbxTestInputType('formula', $data['formula']);
			}
		}

		$this->zbxTestTabSwitch('Operations');
		$this->zbxTestClickXpathWait('//button[contains(@onclick, \'add_operation\')]');
		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation added');

		$this->zbxTestCheckFatalErrors();
		$this->zbxTestTextPresent($data['name']);
		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
		$this->assertEquals(1, DBcount($sql));
	}

	public static function formulaValidation() {
		return [
			[
				[
					'name' => 'Test create with empty expression',
					'tags'=>[
						['select_tag' => 'Old event tag', 'tag_name' => 'Test tag1' ],
						['select_tag' => 'New event tag', 'tag_name' => 'Test tag2' ],
					],
					'formula'=> '',
					'error_message' => 'Incorrect custom expression "Test create with empty expression" for correlation "": expression is empty.'
				]
			],
			[
				[
					'name' => 'Test create with missing argument',
					'tags'=>[
						['select_tag' => 'Old event tag', 'tag_name' => 'Test tag1' ],
						['select_tag' => 'New event tag', 'tag_name' => 'Test tag2' ],
						['select_tag' => 'Old event tag value', 'tag_name' => 'Test tag3', 'operator' => 'like','value' => 'Value']
					],
					'formula'=> 'A or B',
					'error_message' => 'Condition "C" is not used in formula "A or B" for correlation "Test create with missing argument".'
				]
			],
			[
				[
					'name' => 'Test create with extra argument',
					'tags'=>[
						['select_tag' => 'Old event tag', 'tag_name' => 'Test tag1' ],
						['select_tag' => 'New event tag', 'tag_name' => 'Test tag2' ],
						['select_tag' => 'Old event tag value', 'tag_name' => 'Test tag3', 'operator' => 'like','value' => 'Value']
					],
					'formula'=> '(A or B) and (C or D)',
					'error_message' => 'Condition "D" used in formula "(A or B) and (C or D)" for correlation "Test create with extra argument" is not defined.'
				]
			],
			[
				[
					'name' => 'Test create with wrong formula',
					'tags'=>[
						['select_tag' => 'Old event tag', 'tag_name' => 'Test tag1' ],
						['select_tag' => 'New event tag', 'tag_name' => 'Test tag2' ],
						['select_tag' => 'Old event tag value', 'tag_name' => 'Test tag3', 'operator' => 'like','value' => 'Value']
					],
					'formula'=> 'Wrong formula',
					'error_message' => 'Incorrect custom expression "Test create with wrong formula" for correlation "Wrong formula": check expression starting from "Wrong formula".'
				]
			],
		];
	}

	/**
	 * Test custom expression field validation.
	 *
	 * @dataProvider formulaValidation
	 */
	public function testFormEventCorrelation_FormulaValidation($data) {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', $data['name']);

		foreach ($data['tags'] as $tag) {
			$this->zbxTestDropdownSelectWait('new_condition_type', $tag['select_tag']);
			$this->zbxTestInputType('new_condition_tag', $tag['tag_name']);

			if (array_key_exists('operator', $tag)) {
				$this->zbxTestDropdownSelectWait('new_condition_operator', $tag['operator']);
				$this->zbxTestInputType('new_condition_value', $tag['value']);
			}

			$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');
		}

		$this->zbxTestDropdownSelectWait('evaltype', 'Custom expression');
		$this->zbxTestInputType('formula', $data['formula']);
		$this->zbxTestClick('add');

		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add correlation');
		$error = $this->zbxTestGetText('//ul[@class=\'msg-details-border\']');
		$this->assertContains($data['error_message'], $error);
		$this->zbxTestCheckFatalErrors();

		$sql = 'SELECT NULL FROM correlation WHERE name='.zbx_dbstr($data['name']);
		$this->assertEquals(0, DBcount($sql));
	}

	public function testFormEventCorrelation_Clone() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickLinkTextWait('Event correlation for clone');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestClick('clone');
		$this->zbxTestInputType('name', 'Cloned correlation');
		$this->zbxTestClick('add');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation added');
		$this->zbxTestTextPresent('Cloned correlation');
		$this->zbxTestCheckFatalErrors();

		$sql = "SELECT NULL FROM correlation WHERE name='Cloned correlation' AND description='Test description clone'";
		$this->assertEquals(1, DBcount($sql));

		$sql = "SELECT NULL FROM correlation WHERE name='Event correlation for clone' AND description='Test description clone'";
		$this->assertEquals(1, DBcount($sql));

		$sql = "SELECT NULL FROM corr_condition_tag WHERE tag='clone tag'";
		$this->assertEquals(2, DBcount($sql));
	}

	/**
	 * Test update without any modification of event correlation data.
	 */
	public function testFormEventCorrelation_UpdateNone() {
		$sql_hash = 'SELECT * FROM correlation ORDER BY correlationid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickLinkTextWait('Event correlation for update');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestClick('update');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation updated');
		$this->zbxTestTextPresent('Event correlation for update');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function testFormEventCorrelation_UpdateAllFields() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickLinkTextWait('Event correlation for update');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputTypeOverwrite('name', 'New event correlation for update');

		$this->zbxTestClickXpathWait('//tr[@id=\'conditions_0\']//button[text()=\'Remove\']');
		$this->zbxTestDropdownSelectWait('new_condition_type', 'New event tag');
		$this->zbxTestInputTypeOverwrite('new_condition_tag', 'New update tag');
		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');

		$this->zbxTestInputTypeOverwrite('description', 'New test description update');

		$this->zbxTestTabSwitch('Operations');
		$this->zbxTestClickXpathWait('//button[contains(@onclick, \'removeOperation\')]');;
		$this->zbxTestDropdownSelect('new_operation_type', 'Close new event');
		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_operation\')]');
		$this->zbxTestClick('update');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation updated');
		$this->zbxTestTextPresent('New event correlation for update');
		$this->zbxTestCheckFatalErrors();

		$sql = "SELECT NULL FROM correlation WHERE name='New event correlation for update' AND description='New test description update'";
		$this->assertEquals(1, DBcount($sql));

		$sql = "SELECT NULL FROM correlation WHERE name='Event correlation for update'";
		$this->assertEquals(0, DBcount($sql));

		$sql = "SELECT NULL FROM corr_condition_tag WHERE tag='New update tag'";
		$this->assertEquals(1, DBcount($sql));

		$sql = "SELECT NULL FROM corr_condition_tag WHERE tag='update tag'";
		$this->assertEquals(0, DBcount($sql));
	}

	public function testFormEventCorrelation_Delete() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickLinkTextWait('Event correlation for delete');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestClickAndAcceptAlert('delete');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation deleted');
		$this->zbxTestTextNotPresent('Event correlation for delete');
		$this->zbxTestCheckFatalErrors();

		$sql = "SELECT NULL FROM correlation WHERE name='Event correlation for delete'";
		$this->assertEquals(0, DBcount($sql));
	}

	public function testFormEventCorrelation_Cancel() {
		$sql_hash = 'SELECT * FROM correlation ORDER BY correlationid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickLinkTextWait('Event correlation for cancel');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestClick('cancel');

		$this->zbxTestTextPresent('Event correlation for cancel');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}
}
