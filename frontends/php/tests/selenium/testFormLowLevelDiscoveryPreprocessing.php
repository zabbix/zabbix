<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
require_once dirname(__FILE__).'/../../include/items.inc.php';

/**
 * @backup items
 */
class testFormLowLevelDiscoveryPreprocessing extends CWebTest {

	const HOST_ID = 40001;

	public static function getCreateData() {
		return [
			[
				// Validation. Regular expression.
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD empty regular expression',
						'Key' => 'lld-empty-both-parameters',
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '', 'parameter_2' => ''],
					],
					'error_details' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD empty regular expression',
						'Key' => 'lld-empty-first-parameter',
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '', 'parameter_2' => 'test output'],
					],
					'error_details' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD empty regular expression',
						'Key' => 'lld-empty-second-parameter',
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => ''],
					],
					'error_details' => 'Incorrect value for field "params": second parameter is expected.'
				]
			],
			// Validation. JSONPath.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD JSONPath empty',
						'Key' => 'lld-empty-jsonpath',
					],
					'preprocessing' => [
						['type' => 'JSONPath', 'parameter_1' => ''],
					],
					'error_details' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Validation. Regular expressions matching.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD Does not match regular expression empty',
						'Key' => 'lld-does-not-match-regular-expression-empty',
					],
					'preprocessing' => [
						['type' => 'Does not match regular expression', 'parameter_1' => '']
					],
					'error_details' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Validation. Error in JSON.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD error JSON empty',
						'Key' => 'lld-error-json-empty',
					],
					'preprocessing' => [
						['type' => 'Check for error in JSON', 'parameter_1' => '']
					],
					'error_details' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Validation. Throttling.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD two equal discard unchanged with heartbeat',
						'Key' => 'lld-two-equal-discard-uncahnged-with-heartbeat',
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					],
					'error_details' => 'Only one throttling step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD two different discard unchanged with heartbeat',
						'Key' => 'lld-two-different-discard-uncahnged-with-heartbeat',
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '2']
					],
					'error_details' => 'Only one throttling step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD Discard unchanged with heartbeat empty',
						'Key' => 'lld-discard-uncahnged-with-heartbeat-empty',
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '']
					],
					'error_details' => 'Invalid parameter "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD Discard unchanged with heartbeat symbols',
						'Key' => 'lld-discard-uncahnged-with-heartbeat-symbols',
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '3g!@#$%^&*()-=']
					],
					'error_details' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD Discardunchanged with heartbeat letters string',
						'Key' => 'lld-discard-uncahnged-with-heartbeat-letters-string',
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => 'abc']
					],
					'error_details' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD Discard unchanged with heartbeat comma',
						'Key' => 'lld-discard-uncahnged-with-heartbeat-comma',
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1,5']
					],
					'error_details' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD Discard unchanged with heartbeat dot',
						'Key' => 'lld-discard-uncahnged-with-heartbeat-dot',
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1.5']
					],
					'error_details' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD Discard unchanged with heartbeat negative',
						'Key' => 'lld-discard-uncahnged-with-heartbeat-negative',
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '-3']
					],
					'error_details' => 'Invalid parameter "params": value must be one of 1-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD Discard unchanged with heartbeat zero',
						'Key' => 'lld-discard-uncahnged-with-heartbeat-zero',
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '0']
					],
					'error_details' => 'Invalid parameter "params": value must be one of 1-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD Discard unchanged with heartbeat maximum',
						'Key' => 'lld-uncahnged-with-heartbeat-max',
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '788400001']
					],
					'error_details' => 'Invalid parameter "params": value must be one of 1-788400000.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'LLD all preprocessing steps',
						'Key' => 'lld-all-preprocessing-steps',
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => '\1'],
						['type' => 'JSONPath', 'parameter_1' => '$.data.test'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'Pattern'],
						['type' => 'Check for error in JSON', 'parameter_1' => '$.new.path'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '30']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'LLD double preprocessing steps',
						'Key' => 'lld-double-preprocessing-steps',
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression1', 'parameter_2' => '\1'],
						['type' => 'Regular expression', 'parameter_1' => 'expression2', 'parameter_2' => '\2'],
						['type' => 'JSONPath', 'parameter_1' => '$.data.test1'],
						['type' => 'JSONPath', 'parameter_1' => '$.data.test2'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'Pattern1'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'Pattern2'],
						['type' => 'Check for error in JSON', 'parameter_1' => '$.new.path1'],
						['type' => 'Check for error in JSON', 'parameter_1' => '$.new.path2']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'LLD symbols preprocessing steps',
						'Key' => 'lld-symbols-preprocessing-steps',
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '1a!@#$%^&*()-=', 'parameter_2' => '2b!@#$%^&*()-='],
						['type' => 'JSONPath', 'parameter_1' => '3c!@#$%^&*()-='],
						['type' => 'Does not match regular expression', 'parameter_1' => '4d!@#$%^&*()-='],
						['type' => 'Check for error in JSON', 'parameter_1' => '5e!@#$%^&*()-=']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'LLD user macrospreprocessing steps',
						'Key' => 'lld-macros-preprocessing-steps',
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '{$PATTERN}', 'parameter_2' => '{$OUTPUT}'],
						['type' => 'JSONPath', 'parameter_1' => '{$PATH}'],
						['type' => 'Does not match regular expression', 'parameter_1' => '{$PATTERN2}'],
						['type' => 'Check for error in JSON', 'parameter_1' => '{$PATH2}'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '{$HEARTBEAT}']
					]
				]
			]
		];
	}

	/**
	 *  Test creation of a discovery rule with Preprocessing steps.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormLowLevelDiscoveryPreprocessing_Create($data) {
		$sql_items = "SELECT * FROM items ORDER BY itemid";
		$old_hash = CDBHelper::getHash($sql_items);

		$this->page->login()->open('host_discovery.php?hostid='.self::HOST_ID);
		$this->query('button:Create discovery rule')->one()->click();

		$form = $this->query('name:itemForm')->asForm()->one();
		foreach ($data['fields'] as $field => $value) {
			$form->getField($field)->fill($value);
		}
		$form->selectTab('Preprocessing');

		foreach ($data['preprocessing'] as $step_count => $options) {
			$this->selectTypeAndfillParameters($step_count, $options);
		}

		$form->submit();
		$this->page->waitUntilReady();
		// Get global message.
		$message = CMessageElement::find()->one();

		switch ($data['expected']) {
			case TEST_GOOD:
				// Check if message is positive.
				$this->assertTrue($message->isGood());
				// Check message title.
				$this->assertEquals('Discovery rule created', $message->getTitle());
				// Check the results in DB.
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM items WHERE key_='.zbx_dbstr($data['fields']['Key'])));

				// Check result in frontend form.
				$id = CDBHelper::getValue('SELECT itemid FROM items WHERE key_='.zbx_dbstr($data['fields']['Key']));
				$this->page->open('host_discovery.php?form=update&itemid='.$id);
				$form = $this->query('name:itemForm')->asForm()->one();
				$form->selectTab('Preprocessing');

				foreach ($data['preprocessing'] as $step => $options) {
					$type = $this->query('id:preprocessing_'.$step.'_type')->asDropdown()->one()->getText();
					$this->assertEquals($options['type'], $type);

					if (array_key_exists('parameter_1', $options) && array_key_exists('parameter_2', $options)) {

						$parameter_1 = $this->query('id:preprocessing_'.$step.'_params_0')->one()->getValue();
						$this->assertEquals($options['parameter_1'], $parameter_1);

						$parameter_2 = $this->query('id:preprocessing_'.$step.'_params_1')->one()->getValue();
						$this->assertEquals($options['parameter_2'], $parameter_2);

					}
					elseif (array_key_exists('parameter_1', $options) && !array_key_exists('parameter_2', $options)) {
						$parameter_1 = $this->query('id:preprocessing_'.$step.'_params_0')->one()->getValue();
						$this->assertEquals($options['parameter_1'], $parameter_1);
					}
				}
			break;
			case TEST_BAD:
				// Check if message is negative.
				$this->assertTrue($message->isBad());
				// Check message title.
				$this->assertEquals('Cannot add discovery rule', $message->getTitle());
				$this->assertTrue($message->hasLine($data['error_details']));

				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_items));
				break;
		}
	}

	public static function getCustomOnFailData() {
		return [
			[
				[
					'Name' => 'LLD Preprocessing Discard on fail',
					'Key' => 'lld-preprocessing-steps-discard-on-fail',
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => '\1'],
						['type' => 'JSONPath', 'parameter_1' => '$.data.test'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'Pattern'],
						['type' => 'Check for error in JSON', 'parameter_1' => '$.new.path'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '30']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCustomOnFailData
	 */
	public function testFormLowLevelDiscoveryPreprocessing_CustomOnFailDiscard($data) {
		$this->exectueCustomOnFail('discard_value', $data);
	}

	/**
	 * @dataProvider getCustomOnFailData
	 */
	public function testFormLowLevelDiscoveryPreprocessing_CustomOnFailSetValue($data) {
		$this->exectueCustomOnFail('set_value', $data);
	}

	/**
	 * @dataProvider getCustomOnFailData
	 */
	public function testFormLowLevelDiscoveryPreprocessing_CustomOnFailSetError($data) {
		$this->exectueCustomOnFail('set_error', $data);
	}

	/**
	 * Check Custom on fail checkbox.
	 *
	 * @param array $data test case data from data provider
	 */
	private function exectueCustomOnFail($action, $data) {
		$custom_value = 'test_value';
		$custom_error = 'Test error message';

		$this->page->login()->open('host_discovery.php?hostid='.self::HOST_ID);
		$this->query('button:Create discovery rule')->one()->click();

		$form = $this->query('name:itemForm')->asForm()->one();
		$lld_key = $data['Key'].microtime(true);
		$form->getField('Name')->fill($data['Name']);
		$form->getField('Key')->fill($lld_key);

		$form->selectTab('Preprocessing');

		foreach ($data['preprocessing'] as $step_count => $options) {
			$this->selectTypeAndfillParameters($step_count, $options);

			switch ($options['type']) {
				case 'Regular expression':
				case 'JSONPath':
				case 'Does not match regular expression':
					$checkbox = $this->query('id:preprocessing_'.$step_count.'_on_fail')->one()->asCheckbox();
					$this->assertTrue($checkbox->isEnabled());
					$checkbox->check();
					switch ($action) {
						case 'set_value':
							$this->query('id:preprocessing_'.$step_count.'_error_handler')->asSegmentedRadio()->one()->select('Set value to');
							$this->query('id:preprocessing_'.$step_count.'_error_handler_params')->one()->type($custom_value);
							break;
						case 'set_error':
							$this->query('id:preprocessing_'.$step_count.'_error_handler')->asSegmentedRadio()->one()->select('Set error to');
							$this->query('id:preprocessing_'.$step_count.'_error_handler_params')->one()->type($custom_error);
							break;
					}
					break;
				case 'Check for error in JSON':
				case 'Discard unchanged with heartbeat':
					$checkbox = $this->query('id:preprocessing_'.$step_count.'_on_fail')->one()->asCheckbox();
					$this->assertFalse($checkbox->isEnabled());
					break;
			}
		}

		$form->submit();
		$this->page->waitUntilReady();
		// Get global message.
		$message = CMessageElement::find()->one();

		// Check if message is positive.
		$this->assertTrue($message->isGood());
		// Check message title.
		$this->assertEquals('Discovery rule created', $message->getTitle());

		// Get item row from DB.
		$db_item = CDBHelper::getRow('SELECT name,key_,itemid FROM items where key_ = '.zbx_dbstr($lld_key));
		$this->assertEquals($db_item['name'], $data['Name']);
		$itemid = $db_item['itemid'];

		// Check saved preprocessing.
		$this->page->open('host_discovery.php?form=update&itemid='.$itemid);
		$form->selectTab('Preprocessing');
		foreach ($data['preprocessing'] as $step_count => $options) {
			// Get preprocessing from DB.
			$row_preproc = CDBHelper::getRow('SELECT * FROM item_preproc WHERE step='.($step_count + 1).' AND itemid = '.$itemid);
			switch ($options['type']) {
				case 'Regular expression':
				case 'JSONPath':
				case 'Does not match regular expression':
					// Check preprocessing in frontend.
					$checkbox = $this->query('id:preprocessing_'.$step_count.'_on_fail')->one()->asCheckbox();
					$this->assertTrue($checkbox->isSelected());
					$custom_setting=$this->query('id:preprocessing_'.$step_count.'_error_handler')->asSegmentedRadio()->one()->getText();
					if ($action === 'discard_value') {
						$this->assertEquals($custom_setting, 'Discard value');
						// Check preprocessing in DB, where "Discard value" type is equal 1.
						$this->assertEquals(1, $row_preproc['error_handler']);
					}
					elseif ($action === 'set_value') {
						$this->assertEquals($custom_setting, 'Set value to');
						$value_text=$this->query('id:preprocessing_'.$step_count.'_error_handler_params')->one()->getValue();
						$this->assertEquals($value_text, $custom_value);
						// Check preprocessing in DB, where "Set value" type is equal 2.
						$this->assertEquals(2, $row_preproc['error_handler']);
						$this->assertEquals($custom_value, $row_preproc['error_handler_params']);
					}
					elseif ($action === 'set_error') {
						$this->assertEquals($custom_setting, 'Set error to');
						$value_text=$this->query('id:preprocessing_'.$step_count.'_error_handler_params')->one()->getValue();
						$this->assertEquals($value_text, $custom_error);
						// Check preprocessing in DB, where "Set error message" type is equal 3.
						$this->assertEquals(3, $row_preproc['error_handler']);
						$this->assertEquals($custom_error, $row_preproc['error_handler_params']);
					}
					break;
				case 'Check for error in JSON':
				case 'Discard unchanged with heartbeat':
					// Check preprocessing in DB.
					$this->assertEquals(0, $row_preproc['error_handler']);
					$checkbox = $this->query('id:preprocessing_'.$step_count.'_on_fail')->one()->asCheckbox();
					$this->assertFalse($checkbox->isEnabled());
					$this->assertFalse($checkbox->isSelected());
					break;
			}
		}
	}

	public static function getCustomOnFailValidationData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'Set value empty',
						'Key' => 'set-value-empty'
					],
					'custom_on_fail' => [
						['option' => 'Set value to', 'input' => '']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'Set value number',
						'Key' => 'set-value-number'
					],
					'custom_on_fail' => [
						['option' => 'Set value to', 'input' => '500']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'Set value string',
						'Key' => 'set-value-string'
					],
					'custom_on_fail' => [
						['option' => 'Set error to', 'input' => 'String']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'Set value special-symbols',
						'Key' => 'set-value-special-symbols'
					],
					'custom_on_fail' => [
						['option' => 'Set value to', 'input' => '!@#$%^&*()_+<>,.\/']
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'Set error empty',
						'Key' => 'set-error-empty'
					],
					'custom_on_fail' => [
						['option' => 'Set error to', 'input' => '']
					],
					'error_details' => 'Incorrect value for field "error_handler_params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'Set error string',
						'Key' => 'set-error-string'
					],
					'custom_on_fail' => [
						['option' => 'Set error to', 'input' => 'Test error']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'Set error number',
						'Key' => 'set-error-number'
					],
					'custom_on_fail' => [
						['option' => 'Set error to', 'input' => '999']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'Set error special symbols',
						'Key' => 'set-error-special-symbols'
					],
					'custom_on_fail' => [
						['option' => 'Set error to', 'input' => '!@#$%^&*()_+<>,.\/']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCustomOnFailValidationData
	 */
	public function testFormLowLevelDiscoveryPreprocessing_CustomOnFailValidation($data) {
		$preprocessing = [
			['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => '\1'],
			['type' => 'JSONPath', 'parameter_1' => '$.data.test'],
			['type' => 'Does not match regular expression', 'parameter_1' => 'Pattern'],
		];

		$this->page->login()->open('host_discovery.php?hostid='.self::HOST_ID);
		$this->query('button:Create discovery rule')->one()->click();

		$form = $this->query('name:itemForm')->asForm()->one();
		foreach ($data['fields'] as $field => $value) {
			$form->getField($field)->fill($value);
		}
		$form->selectTab('Preprocessing');

		foreach ($preprocessing as $step_count => $options) {
			$this->selectTypeAndfillParameters($step_count, $options);
			$this->query('id:preprocessing_'.$step_count.'_on_fail')->one()->asCheckbox()->check();
			foreach ($data['custom_on_fail'] as $error_type) {
				$this->query('id:preprocessing_'.$step_count.'_error_handler')->asSegmentedRadio()->one()->select($error_type['option']);
				$this->query('id:preprocessing_'.$step_count.'_error_handler_params')->one()->type($error_type['input']);
			}
		}
		$form->submit();
		$this->page->waitUntilReady();
		// Get global message.
		$message = CMessageElement::find()->one();

		switch ($data['expected']) {
			case TEST_GOOD:
				// Check if message is positive.
				$this->assertTrue($message->isGood());
				// Check message title.
				$this->assertEquals('Discovery rule created', $message->getTitle());
				// Check the results in DB.
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM items WHERE key_='.zbx_dbstr($data['fields']['Key'])));
				break;

			case TEST_BAD:
				// Check if message is negative.
				$this->assertTrue($message->isBad());
				// Check message title.
				$this->assertEquals('Cannot add discovery rule', $message->getTitle());
				$this->assertTrue($message->hasLine($data['error_details']));
				// Check that DB.
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM items where key_ = '.zbx_dbstr($data['fields']['Key'])));
				break;
		}
	}

	/**
	 * Add new preprocessing, select preprocessing type and parameters if exist.
	 */
	private function selectTypeAndfillParameters($step, $options) {
			$this->query('id:param_add')->one()->click();
			$this->query('id:preprocessing_'.$step.'_type')->asDropdown()->one()->select($options['type']);

			if (array_key_exists('parameter_1', $options) && array_key_exists('parameter_2', $options)) {
				$this->query('id:preprocessing_'.$step.'_params_0')->one()->type($options['parameter_1']);
				$this->query('id:preprocessing_'.$step.'_params_1')->one()->type($options['parameter_2']);
			}
			elseif (array_key_exists('parameter_1', $options) && !array_key_exists('parameter_2', $options)) {
				$this->query('id:preprocessing_'.$step.'_params_0')->one()->type($options['parameter_1']);
			}
		}
}
