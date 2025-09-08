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
require_once __DIR__.'/../../../include/items.inc.php';
require_once __DIR__.'/../behaviors/CPreprocessingBehavior.php';

/**
 * @backup items
 *
 * @dataSource GlobalMacros
 *
 * TODO: remove ignoreBrowserErrors after DEV-4233
 * @ignoreBrowserErrors
 */
class testFormPreprocessingTest extends CWebTest {

	/**
	 * Attach PreprocessingBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CPreprocessingBehavior::class];
	}

	const HOST_ID = 40001;		//'Simple form test host'

	private static $key;
	private static $name;

	public $change_types = [
		'Discard unchanged with heartbeat',
		'Simple change',
		'Change per second',
		'Discard unchanged'
	];

	public static function getTestSingleStepData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'preprocessing' => [
						['type' => 'Replace', 'parameter_1' => 'текст', 'parameter_2' => 'замена'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'XML XPath', 'parameter_1' => 'path'],
						['type' => 'JSONPath', 'parameter_1' => 'path'],
						['type' => 'CSV to JSON','parameter_1' => ' ', 'parameter_2' => '\\', 'parameter_3' => true],
						['type' => 'XML to JSON'],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Simple change'],
						['type' => 'Change per second'],
						['type' => 'Boolean to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'In range', 'parameter_1' => '-5', 'parameter_2' => '9.5'],
						['type' => 'Matches regular expression', 'parameter_1' => 'expression'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'expression'],
						['type' => 'Check for error in JSON', 'parameter_1' => 'path'],
						['type' => 'Check for error in XML', 'parameter_1' => 'path'],
						['type' => 'Check for error using regular expression', 'parameter_1' => 'path', 'parameter_2' => 'output'],
						['type' => 'Discard unchanged'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1'],
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system', 'parameter_2' => 'label',
								'parameter_3' => 'label_name'],
						['type' => 'Prometheus to JSON', 'parameter_1' => '']
					],
					'action' => 'Test'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '{$1}', 'parameter_2' => '{$A}'],
						['type' => 'Right trim', 'parameter_1' => '{$DEFAULT_LINUX_IF}'],
						['type' => 'XML XPath', 'parameter_1' => '{$SNMP_COMMUNITY}'],
						['type' => 'JSONPath', 'parameter_1' => '{$WORKING_HOURS}'],
						['type' => 'Custom multiplier', 'parameter_1' => '{$DEFAULT_DELAY}'],
						['type' => 'JavaScript', 'parameter_1' => '{$LOCALIP}'],
						['type' => 'Does not match regular expression', 'parameter_1' => '{$_}']
					],
					'macros' => [
						[
							[
								'macro' => '{$1}',
								'value' => 'Numeric macro'
							],
							[
								'macro' => '{$A}',
								'value' => 'Some text'
							]
						],
						[
							[
								'macro' => '{$DEFAULT_LINUX_IF}',
								'value' => 'eth0'
							]
						],
						[
							[
								'macro' => '{$SNMP_COMMUNITY}',
								'value' => 'public'
							]
						],
						[
							[
								'macro' => '{$WORKING_HOURS}',
								'value' => '1-5,09:00-18:00'
							]
						],
						[
							[
								'macro' => '{$DEFAULT_DELAY}',
								'value' => '30'
							]
						],
						[
							[
								'macro' => '{$LOCALIP}',
								'value' => '127.0.0.1'
							]
						],
						[
							[
								'macro' => '{$_}',
								'value' => 'Underscore'
							]
						]
					],
					'action' => 'Test'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'preprocessing' => [
						['type' => 'Replace', 'parameter_1' => 'test', 'parameter_2' => ''],
						['type' => 'In range', 'parameter_1' => '1', 'parameter_2' => ''],
						['type' => 'In range', 'parameter_1' => '', 'parameter_2' => '2'],
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu', 'parameter_2' => 'value']
					],
					'action' => 'Cancel'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '', 'parameter_2' => ''],
						['type' => 'Trim', 'parameter_1' => ''],
						['type' => 'Right trim', 'parameter_1' => ''],
						['type' => 'Left trim', 'parameter_1' => ''],
						['type' => 'XML XPath', 'parameter_1' => ''],
						['type' => 'JSONPath', 'parameter_1' => ''],
						['type' => 'JavaScript', 'parameter_1' => ''],
						['type' => 'Matches regular expression', 'parameter_1' => ''],
						['type' => 'Does not match regular expression', 'parameter_1' => ''],
						['type' => 'Check for error in JSON', 'parameter_1' => ''],
						['type' => 'Check for error in XML', 'parameter_1' => ''],
						['type' => 'Check for error using regular expression', 'parameter_1' => '', 'parameter_2' => ''],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => ''],
						['type' => 'Prometheus pattern', 'parameter_1' => '', 'parameter_2' => 'value']
					],
					'error' => 'Invalid parameter "/1/params/1": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '', 'parameter_2' => '']

					],
					'error' => 'Invalid parameter "/1/params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => '']

					],
					'error' => 'Invalid parameter "/1/params/1": a floating point value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '1', 'parameter_2' => ''],
						['type' => 'Check for error using regular expression', 'parameter_1' => 'path']

					],
					'error' => 'Invalid parameter "/1/params/2": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'preprocessing' => [
						['type' => 'Replace', 'parameter_1' => '', 'parameter_2' => 'test'],
						['type' => 'Regular expression', 'parameter_1' => '', 'parameter_2' => '1'],
						['type' => 'Prometheus pattern', 'parameter_1' => '', 'parameter_2' => 'label', 'parameter_3' => 'label']
					],
					'error' => 'Invalid parameter "/1/params/1": cannot be empty.'
				]
			]
		];
	}

	/**
	 * @dataProvider getTestSingleStepData
	 */
	public function testFormPreprocessingTest_TestSingleStep($data) {
		$this->openPreprocessing($data);

		foreach ($data['preprocessing'] as $i => $step) {
			$this->addPreprocessingSteps([$step]);
			$this->checkTestOverlay($data, 'name:preprocessing['.$i.'][test]', in_array($step['type'], $this->change_types), $i);
		}
		COverlayDialogElement::find()->one()->close();
	}

	public static function getTestAllStepsData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'macro' => '{$1}',
							'value' => 'Numeric macro'
						],
						[
							'macro' => '{$A}',
							'value' => 'Some text'
						],
						[
							'macro' => '{$_}',
							'value' => 'Underscore'
						]
					],
					'preprocessing' => [
						['type' => 'Check for not supported value'],
						['type' => 'Regular expression', 'parameter_1' => '{$A}', 'parameter_2' => '{$1}'],
						['type' => 'JSONPath', 'parameter_1' => '{$_}']
					],
					'action' => 'Cancel'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'Key' => 'macro.in.key.and.preproc.steps[{$DEFAULT_DELAY}]',
					'macros' => [
						[
							'macro' => '{$1}',
							'value' => 'Numeric macro'
						],
						[
							'macro' => '{$A}',
							'value' => 'Some text'
						],
						[
							'macro' => '{$_}',
							'value' => 'Underscore'
						],
						[
							'macro' => '{$DEFAULT_DELAY}',
							'value' => '30'
						]
					],
					'preprocessing' => [
						['type' => 'Check for not supported value'],
						['type' => 'Regular expression', 'parameter_1' => '{$A}', 'parameter_2' => '{$1}'],
						['type' => 'JSONPath', 'parameter_1' => '{$_}']
					],
					'action' => 'Close'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c']
					],
					'action' => 'Cancel'
				]
			],
						[
				[
					'expected' => TEST_GOOD,
					'preprocessing' => [
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Left trim', 'parameter_1' => 'def']
					],
					'action' => 'Test'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'preprocessing' => [
						['type' => 'Simple change'],
						['type' => 'Discard unchanged'],
						['type' => 'In range', 'parameter_1' => '1', 'parameter_2' => '']
					],
					'action' => 'Test'
				]
			],
						[
				[
					'expected' => TEST_GOOD,
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1'],
						['type' => 'Change per second'],
						['type' => 'CSV to JSON','parameter_1' => ',', 'parameter_2' => '"', 'parameter_3' => false],
						['type' => 'XML to JSON']
					],
					'action' => 'Test'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => ''],
						['type' => 'In range', 'parameter_1' => '1', 'parameter_2' => ''],
						['type' => 'In range', 'parameter_1' => '', 'parameter_2' => '2']
					],
					'action' => 'Close'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'preprocessing' => [
						['type' => 'Simple change'],
						['type' => 'Change per second']
					],
					'error' => 'Invalid parameter "/2": only one object can exist within '.
							'the combinations of (type)=((9, 10)).'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'preprocessing' => [
						['type' => 'Discard unchanged'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					],
					'error' => 'Invalid parameter "/2": only one object can exist within the combinations of (type)=((19, 20)).'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system', 'parameter_2' => 'label',
								'parameter_3' => 'label_name'],
						['type' => 'Prometheus to JSON', 'parameter_1' => '']
					],
					'error' => 'Invalid parameter "/2": only one object can exist within the combinations of (type)=((22, 23)).'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '', 'parameter_2' => ''],
						['type' => 'Trim', 'parameter_1' => ''],
						['type' => 'Right trim', 'parameter_1' => ''],
						['type' => 'Left trim', 'parameter_1' => ''],
						['type' => 'XML XPath', 'parameter_1' => ''],
						['type' => 'JSONPath', 'parameter_1' => ''],
						['type' => 'Custom multiplier', 'parameter_1' => ''],
						['type' => 'JavaScript', 'parameter_1' => ''],
						['type' => 'In range', 'parameter_1' => '', 'parameter_2' => ''],
						['type' => 'Matches regular expression', 'parameter_1' => ''],
						['type' => 'Does not match regular expression', 'parameter_1' => ''],
						['type' => 'Check for error in JSON', 'parameter_1' => ''],
						['type' => 'Check for error in XML', 'parameter_1' => ''],
						['type' => 'Check for error using regular expression', 'parameter_1' => '', 'parameter_2' => ''],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => ''],
						['type' => 'Prometheus pattern', 'parameter_1' => '', 'parameter_2' => 'value']
					],
					'error' => 'Invalid parameter "/1/params/1": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expr', 'parameter_2' => 'output'],
						['type' => 'Trim', 'parameter_1' => '']
					],
					'error' => 'Invalid parameter "/2/params/1": cannot be empty.'
				]
			],
						[
				[
					'expected' => TEST_BAD,
					'preprocessing' => [
						['type' => 'Right trim', 'parameter_1' => '1'],
						['type' => 'Left trim', 'parameter_1' => '2'],
						['type' => 'Custom multiplier', 'parameter_1' => '10'],
						['type' => 'JavaScript', 'parameter_1' => 'Script'],
						['type' => 'Check for error in XML', 'parameter_1' => '']
					],
					'error' => 'Invalid parameter "/5/params/1": cannot be empty.'
				]
			]
		];
	}

	/**
	 * @dataProvider getTestAllStepsData
	 */
	public function testFormPreprocessingTest_TestAllSteps($data) {
		$this->openPreprocessing($data);

		foreach ($data['preprocessing'] as $step) {
			$this->addPreprocessingSteps([$step]);
		}

		$prev_enabled = false;
		foreach ($data['preprocessing'] as $step) {
			if (in_array($step['type'], $this->change_types)) {
				$prev_enabled = true;
				break;
			}
		}
		$this->checkTestOverlay($data, 'button:Test all steps', $prev_enabled);

		COverlayDialogElement::find()->one()->close();
	}

	public static function getSortingData() {
		return [
			[
				[
					'preprocessing' => [
						['type' => 'Check for not supported value'],
						['type' => 'Regular expression', 'parameter_1' => '{$A}', 'parameter_2' => '{$1}'],
						['type' => 'Trim', 'parameter_1' => '1'],
						['type' => 'JSONPath', 'parameter_1' => '{$_}']
					]
				]
			],
			[
				[
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '{$A}', 'parameter_2' => '{$1}'],
						['type' => 'Check for not supported value'],
						['type' => 'Trim', 'parameter_1' => '1'],
						['type' => 'JSONPath', 'parameter_1' => '{$_}']
					]
				]
			],
			[
				[
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '{$A}', 'parameter_2' => '{$1}'],
						['type' => 'Trim', 'parameter_1' => '1'],
						['type' => 'JSONPath', 'parameter_1' => '{$_}'],
						['type' => 'Check for not supported value']
					]
				]
			]
		];
	}

	/**
	 * Test for checking that Not supported value step is always being tested and saved first.
	 *
	 * @dataProvider getSortingData
	 */
	public function testFormPreprocessingTest_Sorting($data) {
		// Result order of steps.
		$preprocessing = [
			['type' => 'Check for not supported value'],
			['type' => 'Regular expression', 'parameter_1' => '{$A}', 'parameter_2' => '{$1}'],
			['type' => 'Trim', 'parameter_1' => '1'],
			['type' => 'JSONPath', 'parameter_1' => '{$_}']
		];

		$form = $this->openPreprocessing($data);

		foreach ($data['preprocessing'] as $step) {
			$this->addPreprocessingSteps([$step]);
		}

		// Test all steps right away after adding.
		$this->query('button:Test all steps')->waitUntilPresent()->one()->click();
		$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$table = $dialog->query('id:preprocessing-steps')->asTable()->waitUntilPresent()->one();

		foreach ($preprocessing as $i => $step) {
			$this->assertEquals(($i+1).': '.$step['type'], $table->getRow($i)->getText());
		}

		$dialog->close();
		$form->submit();
		COverlayDialogElement::ensureNotPresent();

		// Assert right steps order after item saving.
		$this->page->open('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);
		$this->query('link', self::$name)->one()->click();
		$form->selectTab('Preprocessing');
		$this->assertPreprocessingSteps($preprocessing);

		COverlayDialogElement::find()->one()->close();
	}

	private function openPreprocessing($data) {
		$this->page->login()->open('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);
		$this->query('button:Create item')->one()->click();
		$form = COverlayDialogElement::find()->one()->waitUntilReady()->asForm();
		self::$key = CTestArrayHelper::get($data, 'Key', false) ? $data['Key'] : 'test.key'.time();
		self::$name = 'Test name'.time();

		$form->fill(['Name' => self::$name, 'Key' => self::$key]);
		$form->selectTab('Preprocessing');

		return $form;
	}

	/**
	 * Check preprocessing steps testing result.
	 *
	 * @param array $data				data provider
	 * @param string $selector			locator of preprocessing "Test" button
	 * @param boolean $prev_enabled		state of fields "Previous value" and "Prev.time", disabled or enabled
	 * @param int $id					index of preprocessing step
	 */
	private function checkTestOverlay($data, $selector, $prev_enabled, $id = null) {
		$this->query($selector)->waitUntilPresent()->one()->click();
		$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();

		switch ($data['expected']) {
			case TEST_BAD:
				$message = $dialog->query('tag:output')->asMessage()->waitUntilPresent()->one();
				$this->assertTrue($message->isBad());
				$dialog->close();
				break;

			case TEST_GOOD:
				$form = $this->query('id:preprocessing-test-form')->asForm()->waitUntilPresent()->one();
				$this->assertEquals('Test item', $dialog->getTitle());

				$time = $dialog->query('id:time')->one();
				$this->assertTrue($time->getAttribute('readonly') !== null);

				$prev_value = $dialog->query('id:prev_value')->asMultiline()->one();
				$prev_time = $dialog->query('id:prev_time')->one();

				$this->assertTrue($prev_value->isEnabled($prev_enabled));
				$this->assertTrue($prev_time->isEnabled($prev_enabled));

				$radio = $form->query('id:eol')->one()->waitUntilPresent();
				$this->assertTrue($radio->isEnabled());

				$macros = [
					'expected' => ($id === null)
							? CTestArrayHelper::get($data, 'macros')
							: CTestArrayHelper::get($data, 'macros.'.$id),
					'actual' => []
				];

				if ($macros['expected']) {
					foreach ($form->query('class:textarea-flexible-container')->asTable()->one()->getRows() as $row) {
						$columns = $row->getColumns()->asArray();
						/*
						 * Macro columns are represented in following way:
						 * (0)macro (1)=> (2)value
						 */
						$macros['actual'][] = [
							'macro' => $columns[0]->getText(),
							'value' => $columns[2]->getText()
						];
					}

					foreach ($macros as &$array) {
						usort($array, function ($a, $b) {
							return strcmp($a['macro'], $b['macro']);
						});
					}
					unset ($array);

					$this->assertEquals($macros['expected'], $macros['actual']);
				}

				$table = $form->query('id:preprocessing-steps')->asTable()->waitUntilPresent()->one();

				if ($id === null) {
					foreach ($data['preprocessing'] as $i => $step) {
						$this->assertEquals(($i+1).': '.$step['type'], $table->getRow($i)->getText());

						$element = $table->query('id:preproc-test-step-'.$i.'-name')->one();
						$this->assertEquals(1, $element->getCSSValue('opacity'));
						$this->assertTrue($element->isEnabled());
					}
				}
				else {
					$this->assertEquals('1: '.$data['preprocessing'][$id]['type'], $table->getRow(0)->getText());
				}

				$this->chooseDialogActions($data);
				break;
		}
	}

	private function chooseDialogActions($data) {
		$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$form = $this->query('id:preprocessing-test-form')->asForm()->waitUntilPresent()->one();
		switch ($data['action']) {
			case 'Test':
				$value_string = '123';
				$prev_value_string = '100';
				$prev_time_string  = 'now-1s';

				$form->query('id:value')->asMultiline()->waitUntilPresent()->one()->fill($value_string);
				$prev_value = $form->query('id:prev_value')->asMultiline()->waitUntilPresent()->one();
				$prev_time = $form->query('id:prev_time')->waitUntilPresent()->one();

				if ($prev_value->isEnabled(true) && $prev_time->isEnabled(true)) {
					$prev_value->fill($prev_value_string);
					$prev_time->fill($prev_time_string);
				}
				$form->query('id:eol')->asSegmentedRadio()->waitUntilPresent()->one()->fill('CRLF');
				$dialog->query('button:Test')->one()->waitUntilVisible()->click();

				// Check Zabbix server down message.
				$message = $form->getOverlayMessage();
				$this->assertTrue($message->isBad());
				$this->assertTrue($message->hasLine('Connection to Zabbix server "localhost:10051" refused. Possible reasons:'));
				$dialog->close();
				break;

			case 'Cancel':
				$dialog->query('button:Cancel')->one()->click();
				break;

			default:
				$dialog->close();
		}
	}
}
