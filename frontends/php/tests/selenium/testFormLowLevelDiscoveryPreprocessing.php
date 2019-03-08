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
require_once dirname(__FILE__).'/traits/PreprocessingTrait.php';

/**
 * @backup items
 */
class testFormLowLevelDiscoveryPreprocessing extends CWebTest {

	const HOST_ID = 40001;

	use PreprocessingTrait;

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
						['type' => 'Regular expression']
					],
					'error_details' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD empty pattern of regular expression',
						'Key' => 'lld-empty-first-parameter',
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_2' => 'test output']
					],
					'error_details' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD empty output of regular expression',
						'Key' => 'lld-empty-second-parameter',
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression'],
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
						'Key' => 'lld-empty-jsonpath'
					],
					'preprocessing' => [
						['type' => 'JSONPath']
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
						['type' => 'Does not match regular expression']
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
						'Key' => 'lld-error-json-empty'
					],
					'preprocessing' => [
						['type' => 'Check for error in JSON']
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
						'Key' => 'lld-two-equal-discard-uncahnged-with-heartbeat'
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
						'Key' => 'lld-two-different-discard-uncahnged-with-heartbeat'
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
						'Key' => 'lld-discard-uncahnged-with-heartbeat-empty'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat']
					],
					'error_details' => 'Invalid parameter "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>[
						'Name' => 'LLD Discard unchanged with heartbeat symbols',
						'Key' => 'lld-discard-uncahnged-with-heartbeat-symbols'
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
						'Key' => 'lld-discard-uncahnged-with-heartbeat-letters-string'
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
						'Key' => 'lld-discard-uncahnged-with-heartbeat-zero'
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
						'Key' => 'lld-uncahnged-with-heartbeat-max'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '788400001']
					],
					'error_details' => 'Invalid parameter "params": value must be one of 1-788400000.'
				]
			],
			// Successful creation of LLD with preprocessing steps.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>[
						'Name' => 'LLD all preprocessing steps',
						'Key' => 'lld-all-preprocessing-steps'
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
						'Key' => 'lld-double-preprocessing-steps'
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
						'Key' => 'lld-symbols-preprocessing-steps'
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
						'Key' => 'lld-macros-preprocessing-steps'
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
		if ($data['expected'] === TEST_BAD) {
			$sql_items = 'SELECT * FROM items ORDER BY itemid';
			$old_hash = CDBHelper::getHash($sql_items);
		}

		$this->page->login()->open('host_discovery.php?hostid='.self::HOST_ID);
		$this->query('button:Create discovery rule')->one()->click();

		$form = $this->query('name:itemForm')->asForm()->one();
		$form->fill($data['fields']);
		$form->selectTab('Preprocessing');
		$this->addPreprocessingSteps($data['preprocessing']);
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

				// Check result in frontend form.
				$id = CDBHelper::getValue('SELECT itemid FROM items WHERE key_='.zbx_dbstr($data['fields']['Key']));
				$this->page->open('host_discovery.php?form=update&itemid='.$id);
				$form->selectTab('Preprocessing');

				$this->assertPreprocessingSteps($data['preprocessing']);
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
		$options = [
			ZBX_PREPROC_FAIL_DISCARD_VALUE	=> 'Discard value',
			ZBX_PREPROC_FAIL_SET_VALUE		=> 'Set value to',
			ZBX_PREPROC_FAIL_SET_ERROR		=> 'Set error to'
		];

		$data = [];
		foreach ($options as $value => $label) {
			$item = [
				'lld_fields' => [
					'Name' => 'LLD Preprocessing '.$label,
					'Key' => 'lld-preprocessing-steps-discard-on-fail'.$value
				],
				'preprocessing' => [
					[
						'type' => 'Regular expression',
						'parameter_1' => 'expression',
						'parameter_2' => '\1',
						'on_fail' => true
					],
					[
						'type' => 'JSONPath',
						'parameter_1' => '$.data.test',
						'on_fail' => true
					],
					[
						'type' => 'Does not match regular expression',
						'parameter_1' => 'Pattern',
						'on_fail' => true
					],
					[
						'type' => 'Check for error in JSON',
						'parameter_1' => '$.new.path'
					],
					[
						'type' => 'Discard unchanged with heartbeat',
						'parameter_1' => '30'
					]
				],
				'value' => $value
			];

			foreach ($item['preprocessing'] as &$step) {
				if (!array_key_exists('on_fail', $step) || !$step['on_fail']) {
					continue;
				}

				$step['error_handler'] = $label;

				if ($value !== ZBX_PREPROC_FAIL_DISCARD_VALUE) {
					$step['error_handler_params'] = 'handler parameter';
				}
			}

			$data[] = [$item];
		}

		return $data;
	}

	/**
	 * Check Custom on fail checkbox.
	 *
	 * @dataProvider getCustomOnFailData
	 */
	public function testFormLowLevelDiscoveryPreprocessing_CustomOnFail($data) {
		$this->page->login()->open('host_discovery.php?hostid='.self::HOST_ID);
		$this->query('button:Create discovery rule')->one()->click();

		$form = $this->query('name:itemForm')->asForm()->one();
		$form->fill($data['lld_fields']);

		$form->selectTab('Preprocessing');
		$this->addPreprocessingSteps($data['preprocessing']);
		$steps = $this->getPreprocessingSteps();

		foreach ($data['preprocessing'] as $i => $options) {
			if ($options['type'] === 'Check for error in JSON'
					|| $options['type'] === 'Discard unchanged with heartbeat') {

				$this->assertFalse($steps[$i]['on_fail']->isEnabled());
			}
		}

		$form->submit();
		$this->page->waitUntilReady();

		// Check message title and if message is positive.
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Discovery rule created', $message->getTitle());

		// Get item data from DB.
		$db_item = CDBHelper::getRow('SELECT name,key_,itemid FROM items where key_='.
				zbx_dbstr($data['lld_fields']['Key'])
		);
		$this->assertEquals($db_item['name'], $data['lld_fields']['Name']);
		$itemid = $db_item['itemid'];

		// Check saved pre-processing.
		$this->page->open('host_discovery.php?form=update&itemid='.$itemid);
		$form->selectTab('Preprocessing');
		$steps = $this->assertPreprocessingSteps($data['preprocessing']);

		$rows = [];
		foreach (CDBHelper::getAll('SELECT step, error_handler FROM item_preproc WHERE itemid='.$itemid) as $row) {
			$rows[$row['step']] = $row['error_handler'];
		}

		foreach ($data['preprocessing'] as $i => $options) {
			// Validate preprocessing step in DB.
			$expected = (!array_key_exists('on_fail', $options) || !$options['on_fail'])
					? ZBX_PREPROC_FAIL_DEFAULT : $data['value'];

			$this->assertEquals($expected, $rows[$i + 1]);

			switch ($options['type']) {
				case 'Regular expression':
				case 'JSONPath':
				case 'Does not match regular expression':
					// Check preprocessing in frontend.
					$this->assertTrue($steps[$i]['on_fail']->isSelected());
					$this->assertTrue($steps[$i]['on_fail']->isEnabled());
					break;
				case 'Check for error in JSON':
				case 'Discard unchanged with heartbeat':
					// Check pre-processing error handler type in DB.
					$this->assertFalse($steps[$i]['on_fail']->isEnabled());
					$this->assertFalse($steps[$i]['on_fail']->isSelected());

					$this->assertTrue($steps[$i]['error_handler'] === null || !$steps[$i]['error_handler']->isVisible());
					$this->assertTrue($steps[$i]['error_handler_params'] === null
							|| !$steps[$i]['error_handler_params']->isVisible()
					);
					break;
			}
		}
	}

	public static function getCustomOnFailValidationData() {
		$cases = [
			// 'Set value to' validation.
			[
				'expected' => TEST_GOOD,
				'fields' =>[
					'Name' => 'Set value empty',
					'Key' => 'set-value-empty'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set value to',
					'error_handler_params' => ''
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' =>[
					'Name' => 'Set value number',
					'Key' => 'set-value-number'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set value to',
					'error_handler_params' => '500'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' =>[
					'Name' => 'Set value string',
					'Key' => 'set-value-string'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => 'String'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' =>[
					'Name' => 'Set value special-symbols',
					'Key' => 'set-value-special-symbols'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set value to',
					'error_handler_params' => '!@#$%^&*()_+<>,.\/'
				]
			],
			// 'Set error to' validation.
			[
				'expected' => TEST_BAD,
				'fields' =>[
					'Name' => 'Set error empty',
					'Key' => 'set-error-empty'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => ''
				],
				'error_details' => 'Incorrect value for field "error_handler_params": cannot be empty.'
			],
			[
				'expected' => TEST_GOOD,
				'fields' =>[
					'Name' => 'Set error string',
					'Key' => 'set-error-string'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => 'Test error'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' =>[
					'Name' => 'Set error number',
					'Key' => 'set-error-number'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => '999'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' =>[
					'Name' => 'Set error special symbols',
					'Key' => 'set-error-special-symbols'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => '!@#$%^&*()_+<>,.\/'
				]
			]
		];

		$data = [];
		$preprocessing = [
			[
				'type' => 'Regular expression',
				'parameter_1' => 'expression',
				'parameter_2' => '\1',
				'on_fail' => true
			],
			[
				'type' => 'JSONPath',
				'parameter_1' => '$.data.test',
				'on_fail' => true
			],
			[
				'type' => 'Does not match regular expression',
				'parameter_1' => 'Pattern',
				'on_fail' => true
			]
		];

		foreach ($cases as $case) {
			$case['preprocessing'] = [];
			foreach ($preprocessing as $step) {
				$case['preprocessing'][] = array_merge($step, $case['custom_on_fail']);
			}

			$data[] = [$case];
		}

		return $data;
	}

	/**
	 * @dataProvider getCustomOnFailValidationData
	 */
	public function testFormLowLevelDiscoveryPreprocessing_CustomOnFailValidation($data) {
		$this->page->login()->open('host_discovery.php?hostid='.self::HOST_ID);
		$this->query('button:Create discovery rule')->one()->click();

		$form = $this->query('name:itemForm')->asForm()->one();
		$form->fill($data['fields']);
		$form->selectTab('Preprocessing');
		$this->addPreprocessingSteps($data['preprocessing']);
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
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM items WHERE key_='.
						zbx_dbstr($data['fields']['Key']))
				);
				break;

			case TEST_BAD:
				// Check if message is negative.
				$this->assertTrue($message->isBad());
				// Check message title.
				$this->assertEquals('Cannot add discovery rule', $message->getTitle());
				$this->assertTrue($message->hasLine($data['error_details']));
				// Check that DB.
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM items where key_ = '.
						zbx_dbstr($data['fields']['Key']))
				);
				break;
		}
	}
}
