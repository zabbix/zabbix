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

class testFormEventCorelation extends CWebTest {

	public static function create() {
		return [
			[
				[
					'name' => 'Test create with all fields',
					'select_tag' => 'New event tag',
					'tag' => 'Test tag',
					'description' => 'Event corelation with description',
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
	 * @dataProvider create
	 */
	public function testFormEventCorelation_Create($data) {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestDropdownSelectWait('new_condition_type', $data['select_tag']);
		$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');

		if (array_key_exists('description', $data)) {
		$this->zbxTestInputTypeOverwrite('description', $data['description']);
		}

		$this->zbxTestTabSwitch('Operations');

		if (array_key_exists('operation', $data)) {
			$this->zbxTestDropdownSelect('new_operation_type', $data['operation']);
		}

		$this->zbxTestClickXpathWait('//button[contains(@onclick, \'add_operation\')]');
		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation added');

		$this->zbxTestCheckFatalErrors();
		$sql = "SELECT NULL FROM correlation WHERE name='".$data['name']."'";
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
	 * @dataProvider validation
	 */
	public function testFormEventCorelation_CreateValidation($data) {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputType('name', $data['name']);
		}

		if (array_key_exists('tag', $data)) {
			$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
			$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');
		}

		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_header']);
		$error = $this->zbxTestGetText('//ul[@class=\'msg-details-border\']');
		$this->assertContains($data['error_message'], $error);

		$this->zbxTestCheckFatalErrors();

		if 	(array_key_exists('name', $data) and $data['name'] == 'Event correlation for update') {
			$sql = "SELECT NULL FROM correlation WHERE name='".$data['name']."'";
			$this->assertEquals(1, DBcount($sql));
		}

		if (array_key_exists('name', $data) and $data['name'] != 'Event correlation for update') {
			$sql = "SELECT NULL FROM correlation WHERE name='".$data['name']."'";
			$this->assertEquals(0, DBcount($sql));
		}
	}

	public function testFormEventCorelation_LongNameValidation() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', 'Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name');
		$this->zbxTestInputTypeOverwrite('new_condition_tag', 'Test tag');
		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');

		$this->zbxTestTabSwitch('Operations');
		$this->zbxTestClickXpathWait('//button[contains(@onclick, \'add_operation\')]');
		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation added');

		$this->zbxTestCheckFatalErrors();
		$sql = "SELECT NULL FROM correlation WHERE name='Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_Name_Test_With_Long_'";
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
					'name' => 'Test create with New event host group <>',
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
					'name' => 'Test create with Old event tag value <> tag',
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
					'name' => 'Test create with New event tag value <> tag',
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
	 * @dataProvider tags
	 */
	public function testFormEventCorelation_TestTags($data) {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestDropdownSelectWait('new_condition_type', $data['select_tag']);
		$select_tag = $this->zbxTestGetSelectedLabel('new_condition_type');
		$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);
		$operator = $this->zbxTestGetSelectedLabel('new_condition_operator');

		if ($select_tag == 'New event host group' and $operator == '=') {
			$this->zbxTestClickXpath('.//*[@class=\'btn-grey\']');
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('overlay_dialogue'));
			$this->zbxTestClickWait('spanid4');
		}

		if ($select_tag == 'New event host group' and $operator == '<>') {
			$this->zbxTestClickXpath('.//*[@class=\'btn-grey\']');
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('overlay_dialogue'));
			$this->zbxTestClickWait('spanid4');
		}

		if ($select_tag == 'Event tag pair') {
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestInputTypeOverwrite('new_condition_oldtag', $data['oldtag']);
			$this->zbxTestInputTypeOverwrite('new_condition_newtag', $data['newtag']);
		}

		if ($select_tag == 'Old event tag value' and $operator == '=') {
			$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
			$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);
			$this->zbxTestInputTypeOverwrite('new_condition_value', $data['value']);
		}

		if ($select_tag == 'Old event tag value' and $operator == '<>') {
			$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
			$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);
			$this->zbxTestInputTypeOverwrite('new_condition_value', $data['value']);
		}

		if ($select_tag == 'Old event tag value' and $operator == 'like') {
			$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
			$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);
			$this->zbxTestInputTypeOverwrite('new_condition_value', $data['value']);
		}

		if ($select_tag == 'Old event tag value' and $operator == 'not like') {
			$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
			$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);
			$this->zbxTestInputTypeOverwrite('new_condition_value', $data['value']);
		}

		if ($select_tag == 'New event tag value' and $operator == '=') {
			$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
			$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);
			$this->zbxTestInputTypeOverwrite('new_condition_value', $data['value']);
		}

		if ($select_tag == 'New event tag value' and $operator == '<>') {
			$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
			$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);
			$this->zbxTestInputTypeOverwrite('new_condition_value', $data['value']);
		}

		if ($select_tag == 'New event tag value' and $operator == 'like') {
			$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
			$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);
			$this->zbxTestInputTypeOverwrite('new_condition_value', $data['value']);
		}

		if ($select_tag == 'New event tag value' and $operator == 'not like') {
			$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
			$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);
			$this->zbxTestInputTypeOverwrite('new_condition_value', $data['value']);
		}

		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');

		$this->zbxTestTabSwitch('Operations');
		$this->zbxTestClickXpathWait('//button[contains(@onclick, \'add_operation\')]');
		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation added');

		$this->zbxTestCheckFatalErrors();
		$sql = "SELECT NULL FROM correlation WHERE name='".$data['name']."'";
		$this->assertEquals(1, DBcount($sql));
	}

	public static function tagsValidation() {
		return [
			[
				[
					'name' => 'Test empty New event tag',
					'select_tag' => 'New event tag',
					'error_header'=> 'Cannot add correlation condition',
					'error_message' => 'Incorrect value for field "tag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty New event host group',
					'select_tag' => 'New event host group',
					'error_header'=> 'Cannot add correlation condition',
					'error_message' => 'Incorrect value for field "groupid": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty Old tag in Event tag pair',
					'select_tag' => 'Event tag pair',
					'newtag' => 'New tag',
					'error_header'=> 'Cannot add correlation condition',
					'error_message' => 'Incorrect value for field "oldtag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty New tag in Event tag pair',
					'select_tag' => 'Event tag pair',
					'oldtag' => 'Old tag',
					'error_header'=> 'Cannot add correlation condition',
					'error_message' => 'Incorrect value for field "newtag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty tag in Old event tag value',
					'select_tag' => 'Old event tag value',
					'error_header'=> 'Cannot add correlation condition',
					'error_message' => 'Incorrect value for field "tag": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Test empty tag in New event tag value',
					'select_tag' => 'New event tag value',
					'error_header'=> 'Cannot add correlation condition',
					'error_message' => 'Incorrect value for field "tag": cannot be empty.'
				]
			]
		];
	}

	/**
	 * @dataProvider tagsValidation
	 */
	public function testFormEventCorelation_TagsValidation($data) {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestDropdownSelectWait('new_condition_type', $data['select_tag']);
		$this->zbxTestWaitForPageToLoad();

		$select_tag = $this->zbxTestGetSelectedLabel('new_condition_type');

		if ($select_tag == 'Event tag pair' and array_key_exists('newtag', $data)) {
			$this->zbxTestInputTypeOverwrite('new_condition_newtag', $data['newtag']);
		}

		if ($select_tag == 'Event tag pair' and array_key_exists('oldtag', $data)) {
			$this->zbxTestInputTypeOverwrite('new_condition_oldtag', $data['oldtag']);
		}

		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');

		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_header']);
		$this->zbxTestAssertElementText('//ul[@class=\'msg-details-border\']', $data['error_message']);
		$this->zbxTestCheckFatalErrors();
	}

	public static function calculation() {
		return [
			[
				[
					'name' => 'Test create with calculation And/Or',
					'select_tag1' => 'Old event tag',
					'tag1' => 'Test tag1',
					'select_tag2' => 'New event tag',
					'tag2' => 'Test tag2',
					'select_tag3' => 'Old event tag value',
					'tag' => 'Tag3',
					'operator' => 'like',
					'value' => 'Value3',
					'operation' => 'Close new event',
				]
			],
			[
				[
					'name' => 'Test create with calculation And',
					'select_tag1' => 'Old event tag',
					'tag1' => 'Test tag1',
					'select_tag2' => 'New event tag',
					'tag2' => 'Test tag2',
					'select_tag3' => 'Old event tag value',
					'tag' => 'Tag3',
					'operator' => 'like',
					'value' => 'Value3',
					'calculation' => 'And',
					'operation' => 'Close new event',
				]
			],
			[
				[
					'name' => 'Test create with calculation Or',
					'select_tag1' => 'Old event tag',
					'tag1' => 'Test tag1',
					'select_tag2' => 'New event tag',
					'tag2' => 'Test tag2',
					'select_tag3' => 'Old event tag value',
					'tag' => 'Tag3',
					'operator' => 'like',
					'value' => 'Value3',
					'calculation' => 'Or',
					'operation' => 'Close new event',
				]
			],
			[
				[
					'name' => 'Test create with calculation Custom',
					'select_tag1' => 'Old event tag',
					'tag1' => 'Test tag1',
					'select_tag2' => 'New event tag',
					'tag2' => 'Test tag2',
					'select_tag3' => 'Old event tag value',
					'tag' => 'Tag3',
					'operator' => 'like',
					'value' => 'Value3',
					'calculation' => 'Custom expression',
					'formula'=> 'A or (B and C)',
					'operation' => 'Close new event',
				]
			]
		];
	}

	/**
	 * @dataProvider calculation
	 */
	public function testFormEventCorelation_CreateCalculation($data) {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestDropdownSelectWait('new_condition_type', $data['select_tag1']);
		$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag1']);
		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');

		$this->zbxTestDropdownSelectWait('new_condition_type', $data['select_tag2']);
		$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag2']);
		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');

		$this->zbxTestDropdownSelectWait('new_condition_type', $data['select_tag3']);
		$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
		$this->zbxTestDropdownSelectWait('new_condition_operator', $data['operator']);
		$this->zbxTestInputTypeOverwrite('new_condition_value', $data['value']);
		$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');

		if (array_key_exists('calculation', $data)) {
			$this->zbxTestDropdownSelect('evaltype', $data['calculation']);
		}

		$calculation = $this->zbxTestGetSelectedLabel('evaltype');

		if ($calculation == 'Custom expression') {
			$this->zbxTestInputTypeOverwrite('formula', $data['formula']);
		}

		$this->zbxTestTabSwitch('Operations');
		$this->zbxTestClickXpathWait('//button[contains(@onclick, \'add_operation\')]');
		$this->zbxTestClick('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation added');

		$this->zbxTestCheckFatalErrors();
		$sql = "SELECT NULL FROM correlation WHERE name='".$data['name']."'";
		$this->assertEquals(1, DBcount($sql));
	}

	public static function update() {
		return [
			[
				[
					'select_tag' => 'New event tag',
					'tag' => 'NEW update tag'
				]
			],
			[
				[
					'description' => 'NEW Test description update'
				]
			],
			[
				[
					'operation' => 'Close new event'
				]
			],
			[
				[
					'name' => 'NEW Event correlation for update'
				]
			]
		];
	}

	/**
	 * @dataProvider update
	 */
	public function testFormEventCorelation_Update($data) {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickLinkTextWait('Event correlation for update');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		if (array_key_exists('select_tag', $data)) {
			$this->zbxTestClick('remove');
			$this->zbxTestDropdownSelectWait('new_condition_type', $data['select_tag']);
			$this->zbxTestInputTypeOverwrite('new_condition_tag', $data['tag']);
			$this->zbxTestClickXpath('//button[contains(@onclick, \'add_condition\')]');
		}

		if (array_key_exists('description', $data)) {
			$this->zbxTestInputTypeOverwrite('description', $data['description']);
		}

		if (array_key_exists('operation', $data)) {
			$this->zbxTestTabSwitch('Operations');
			$this->zbxTestClickXpathWait('//button[contains(@onclick, \'removeOperation\')]');
			$this->zbxTestDropdownSelect('new_operation_type', $data['operation']);
			$this->zbxTestClickXpathWait('//button[contains(@onclick, \'add_operation\')]');
		}

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('name', $data['name']);
		}

		$this->zbxTestClick('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation updated');

		$this->zbxTestCheckFatalErrors();

		if (array_key_exists('select_tag', $data)) {
			$sql = "SELECT * FROM corr_condition_tag WHERE tag='update tag'";
			$this->assertEquals(0, DBcount($sql));

			$sql = "SELECT * FROM corr_condition_tag WHERE tag='NEW update tag'";
			$this->assertEquals(1, DBcount($sql));
		}

		if (array_key_exists('description', $data)) {
			$sql = "SELECT NULL FROM correlation WHERE description='Test description update'";
			$this->assertEquals(0, DBcount($sql));

			$sql = "SELECT NULL FROM correlation WHERE description='".$data['description']."'";
			$this->assertEquals(1, DBcount($sql));
		}

		if (array_key_exists('operation', $data)) {
			$sql = "SELECT * FROM corr_operation WHERE correlationid='99001' and type='0'";
			$this->assertEquals(0, DBcount($sql));

			$sql = "SELECT * FROM corr_operation WHERE correlationid='99001' and type='1'";
			$this->assertEquals(1, DBcount($sql));
		}

		if (array_key_exists('name', $data)) {
			$sql = "SELECT * FROM correlation WHERE name='Event correlation for update'";
			$this->assertEquals(0, DBcount($sql));

			$sql = "SELECT * FROM correlation WHERE name='".$data['name']."'";
			$this->assertEquals(1, DBcount($sql));
		}
	}

	public static function updateValidation() {
		return [
			[
				[
					'name'=> ' ',
					'error_header' => 'Page received incorrect data',
					'error_message' => 'Incorrect value for field "Name": cannot be empty.'
				]
			],
			[
				[
					'error_header' => 'Cannot update correlation',
					'error_message' => 'No "conditions" given for correlation "Event correlation for update validation".'
				]
			],
			[
				[
					'error_header' => 'Cannot update correlation',
					'error_message' => 'No "operations" given for correlation "Event correlation for update validation".'
				]
			]
		];
	}

	/**
	 * @dataProvider updateValidation
	 */
	public function testFormEventCorelation_UpdateValidation($data) {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickLinkTextWait('Event correlation for update validation');
		$this->zbxTestCheckHeader('Event correlation rules');
		$this->zbxTestCheckTitle('Event correlation rules');

		if ($data['error_message'] == 'Incorrect value for field "Name": cannot be empty.') {
			$this->zbxTestInputTypeOverwrite('name', $data['name']);
		}

		if ($data['error_message'] == 'No "conditions" given for correlation "Event correlation for update validation".') {
			$this->zbxTestClick('remove');
		}

		if ($data['error_message'] == 'No "operations" given for correlation "Event correlation for update validation".') {
			$this->zbxTestTabSwitch('Operations');
			$this->zbxTestClickXpathWait('//button[contains(@onclick, \'removeOperation\')]');
		}

		$this->zbxTestClick('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_header']);
		$error = $this->zbxTestGetText('//ul[@class=\'msg-details-border\']');
		$this->assertContains($data['error_message'], $error);

		$this->zbxTestCheckFatalErrors();

		if ($data['error_message'] == 'Incorrect value for field "Name": cannot be empty.') {
			$sql = "SELECT * FROM correlation WHERE name=''";
			$this->assertEquals(0, DBcount($sql));

			$sql = "SELECT * FROM correlation WHERE name='Event correlation for update validation'";
			$this->assertEquals(1, DBcount($sql));
		}

		if ($data['error_message'] == 'No "conditions" given for correlation "Event correlation for update".') {
			$sql = "SELECT * FROM corr_condition WHERE corr_conditionid IS NULL";
			$this->assertEquals(0, DBcount($sql));
		}

		if ($data['error_message'] == 'No "operations" given for correlation "Event correlation for update".') {
			$sql = "SELECT * FROM corr_operation WHERE corr_operationid IS NULL";
			$this->assertEquals(0, DBcount($sql));
		}
	}
}
