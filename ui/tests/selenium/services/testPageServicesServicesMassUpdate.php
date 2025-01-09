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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup services
 *
 * @dataSource Services
 */
class testPageServicesServicesMassUpdate extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	private static $service_sql = 'SELECT * FROM services ORDER BY serviceid';

	public function getTagsData() {
		return [
			// Empty tag name.
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'Service with problem',
						'Service with problem tags'
					],
					'Tags' => [
						'action' => 'Add',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'value' => 'value1'
							]
						]
					],
					'details' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
				]
			],
			// TODO: Uncomment this case when ZBX-19263 is fixed.
			// Equal tags.
			/*
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'Service with multiple service tags',
						'Service for duplicate check'
					],
					'Tags' => [
						'action' => 'Add',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'tag',
								'value' => 'value'
							],
							[
								'tag' => 'tag',
								'value' => 'value'
							]
						]
					],
					'details' => 'Invalid parameter "/1/tags/2": value (tag, value)=(tag, value) already exists.'
				]
			],
			 */
			[
				[
					'names' => [
						'Service with problem',
						'Service with multiple service tags'
					],
					'Tags' => [
						'action' => 'Add',
						'tags' => []
					],
					'expected_tags' => [
						'Service with problem' => [
							[
								'tag' => 'old_tag_1',
								'value' => 'old_value_1'
							]
						],
						'Service with multiple service tags' => [
							[
								'tag' => 'problem',
								'value' => 'true'
							],
							[
								'tag' => 'test',
								'value' => 'test456'
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'Service with problem',
						'Service with multiple service tags'
					],
					'Tags' => [
						'action' => 'Add',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'added_tag_1',
								'value' => 'added_value_1'
							]
						]
					],
					'expected_tags' => [
						'Service with problem' => [
							[
								'tag' => 'added_tag_1',
								'value' => 'added_value_1'
							],
							[
								'tag' => 'old_tag_1',
								'value' => 'old_value_1'
							]
						],
						'Service with multiple service tags' => [
							[
								'tag' => 'added_tag_1',
								'value' => 'added_value_1'
							],
							[
								'tag' => 'problem',
								'value' => 'true'
							],
							[
								'tag' => 'test',
								'value' => 'test456'
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'Service for mass update',
						'Update service'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => []
					],
					'expected_tags' => [
						'Service for mass update' => [
							[
								'tag' => '',
								'value' => ''
							]
						],
						'Update service' => [
							[
								'tag' => '',
								'value' => ''
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'Service for mass update',
						'Update service'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'replaced_tag',
								'value' => 'replaced_value'
							]
						]
					],
					'expected_tags' => [
						'Service for mass update' => [
							[
								'tag' => 'replaced_tag',
								'value' => 'replaced_value'
							]
						],
						'Update service' => [
							[
								'tag' => 'replaced_tag',
								'value' => 'replaced_value'
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'Service for delete 2',
						'Service for delete'
					],
					'Tags' => [
						'action' => 'Remove',
						'tags' => [
							[
								'tag' => '',
								'value' => ''
							]
						]
					],
					'expected_tags' => [
						'Service for delete 2' => [
							[
								'tag' => '3rd_tag',
								'value' => '3rd_value'
							],
							[
								'tag' => '4th_tag',
								'value' => '4th_value'
							],
							[
								'tag' => 'remove_tag_1',
								'value' => 'remove_value_1'
							],
							[
								'tag' => 'remove_tag_2',
								'value' => 'remove_value_2'
							]
						],
						'Service for delete' => [
							[
								'tag' => 'remove_tag_2',
								'value' => 'remove_value_2'
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'Service for delete 2',
						'Service for delete',
						'Parent for child creation'
					],
					'Tags' => [
						'action' => 'Remove',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'remove_tag_2',
								'value' => 'remove_value_2'
							]
						]
					],
					'expected_tags' => [
						'Service for delete 2' => [
							[
								'tag' => '3rd_tag',
								'value' => '3rd_value'
							],
							[
								'tag' => '4th_tag',
								'value' => '4th_value'
							],
							[
								'tag' => 'remove_tag_1',
								'value' => 'remove_value_1'
							]
						],
						'Service for delete' => [
							[
								'tag' => '',
								'value' => ''
							]
						],
						'Parent for child creation' => [
							[
								'tag' => 'remove_tag_3',
								'value' => 'remove_value_3'
							]
						]
					]
				]
			],
			// Different symbols in tag names and values.
			[
				[
					'names' => [
						'Service with problem tags',
						'Service for duplicate check'
					],
					'Tags' => [
						'action' => 'Add',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => '!@#$%^&*()_+<>,.\/',
								'value' => '!@#$%^&*()_+<>,.\/'
							],
							[
								'tag' => 'tag1',
								'value' => 'value1'
							],
							[
								'tag' => 'tag2'
							],
							[
								'tag' => '{$MACRO:A}',
								'value' => '{$MACRO:A}'
							],
							[
								'tag' => '{$MACRO}',
								'value' => '{$MACRO}'
							],
							[
								'tag' => 'Таг',
								'value' => 'Значение'
							]
						]
					]
				]
			],
			// Two tags with equal tag names.
			[
				[
					'names' => [
						'Service with problem tags',
						'Service for duplicate check'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'tag3',
								'value' => '3'
							],
							[
								'tag' => 'tag3',
								'value' => '4'
							]
						]
					]
				]
			],
			// Two tags with equal tag values.
			[
				[
					'names' => [
						'Service with problem tags',
						'Service for duplicate check'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'tag4',
								'value' => '5'
							],
							[
								'tag' => 'tag5',
								'value' => '5'
							]
						]
					]
				]
			],
			// Tag with trailing spaces.
			[
				[
					'names' => [
						'Service with problem tags',
						'Service for duplicate check'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => '    trimmed tag    ',
								'value' => '   trimmed value    '
							]
						]
					],
					'trim' => true
				]
			],
			// Tag with long name and value.
			[
				[
					'names' => [
						'Service with problem tags',
						'Service for duplicate check'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'Long tag name. Long tag name. Long tag name. Long tag name. Long tag name.'.
										' Long tag name. Long tag name. Long tag name.',
								'value' => 'Long tag value. Long tag value. Long tag value. Long tag value. Long tag value.'.
										' Long tag value. Long tag value. Long tag value. Long tag value.'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * Mass update Tags in Services.
	 *
	 * @dataProvider getTagsData
	 */
	public function testPageServicesServicesMassUpdate_Tags($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::$service_sql);
		}

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$this->selectTableRows($data['names']);
		$this->query('button:Mass update')->one()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$dialog->getLabel('Tags')->click();
		$dialog->query('id:mass_update_tags')->asSegmentedRadio()->one()->fill($data['Tags']['action']);

		if ($data['Tags']['tags'] !== []) {
			$this->query('class:tags-table')->asMultifieldTable()->one()->fill($data['Tags']['tags']);
		}

		$dialog->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot update services', $data['details']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::$service_sql));
		}
		else {
			COverlayDialogElement::ensureNotPresent();
			$this->assertMessage(TEST_GOOD, 'Services updated');

			// Check changed fields in saved service form.
			foreach ($data['names'] as $name) {
				$table = $this->query('id:service-list')->asTable()->one();
				$table->findRow('Name', $name)->query('xpath:.//button[@title="Edit"]')->waitUntilClickable()
						->one()->click();

				$service_dialog = COverlayDialogElement::find()->waitUntilReady()->one();
				$service_dialog->asForm()->selectTab('Tags');

				$expected = $data['Tags']['tags'];
				if (!array_key_exists('expected_tags', $data)) {
					// Remove action and index fields for asserting expected result.
					foreach ($expected as &$tag) {
						unset($tag['action'], $tag['index']);

						if (CTestArrayHelper::get($data, 'trim', false) === false) {
							continue;
						}

						// Removing trailing and leading spaces from tag and value for asserting expected result.
						foreach ($expected as $i => &$options) {
							foreach (['tag', 'value'] as $parameter) {
								if (array_key_exists($parameter, $options)) {
									$options[$parameter] = trim($options[$parameter]);
								}
							}
						}
						unset($options);
					}
					unset($tag);
				}

				$expected_tags = array_key_exists('expected_tags', $data) ? $data['expected_tags'][$name] : $expected;
				$this->query('class:tags-table')->asMultifieldTable()->one()->checkValue($expected_tags);

				$service_dialog->close();
			}
		}
	}

	/**
	 * Cancel Mass updating of Services.
	 */
	public function testPageServicesServicesMassUpdate_Cancel() {
		$old_hash = CDBHelper::getHash(self::$service_sql);

		$this->page->login()->open('zabbix.php?action=service.list.edit');

		$services  = [
			'Service with problem',
			'Service with multiple service tags',
			'Service with problem tags',
			'Service for duplicate check'
		];

		$new_tags = [
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 0,
				'tag' => 'new_tag_1',
				'value' => 'new_value_1'
			],
			[
				'tag' => 'new_tag_2',
				'value' => 'new_value_2'
			]
		];

		$this->selectTableRows($services);
		$this->query('button:Mass update')->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->asForm()->getLabel('Tags')->click();
		$this->query('class:tags-table')->asMultifieldTable()->one()->fill($new_tags);

		$dialog->query('button:Cancel')->waitUntilClickable()->one()->click();
		COverlayDialogElement::ensureNotPresent();

		// Check that UI returned to previous page and hash remained unchanged.
		$this->page->waitUntilReady();
		$this->page->assertHeader('Services');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$service_sql));
	}
}
