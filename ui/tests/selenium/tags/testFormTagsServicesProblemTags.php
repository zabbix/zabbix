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

require_once __DIR__.'/../common/testFormTags.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * @dataSource EntitiesTags
 *
 * @backup services
 */
class testFormTagsServicesProblemTags extends testFormTags {

	public $problem_tags = true;
	protected $tags_table = 'id:problem_tags';

	public $update_name = 'Service with tags for updating';
	public $clone_name = 'Service with tags for cloning';
	public $remove_name = 'Service for removing tags';
	public $link = 'zabbix.php?action=service.list.edit';

	public function getCreateProblemTagsData() {
		return [
			[
				[
					'name' => 'With tags',
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '!@#$%^&*()_+<>,.\/',
							'operator' => 'Equals',
							'value' => '!@#$%^&*()_+<>,.\/'
						],
						[
							'tag' => 'tag1',
							'operator' => 'Contains',
							'value' => 'value1'
						],
						[
							'tag' => 'tag2'
						],
						[
							'tag' => '{$MACRO:A}',
							'operator' => 'Contains',
							'value' => '{$MACRO:A}'
						],
						[
							'tag' => '{$MACRO}',
							'operator' => 'Equals',
							'value' => '{$MACRO}'
						],
						[
							'tag' => 'Таг',
							'operator' => 'Contains',
							'value' => 'Значение'
						]
					]
				]
			],
			[
				[
					'name' => 'With equal tag names',
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'tag3',
							'operator' => 'Contains',
							'value' => '3'
						],
						[
							'tag' => 'tag3',
							'operator' => 'Equals',
							'value' => '4'
						]
					]
				]
			],
			[
				[
					'name' => 'With equal tag values',
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'tag4',
							'operator' => 'Equals',
							'value' => '5'
						],
						[
							'tag' => 'tag5',
							'operator' => 'Contains',
							'value' => '5'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'With empty tag name',
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'value1'
						]
					],
					'error_details' => 'Invalid parameter "/1/problem_tags/1/tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'With equal tags',
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'tag',
							'operator' => 'Equals',
							'value' => 'value'
						],
						[
							'tag' => 'tag',
							'operator' => 'Equals',
							'value' => 'value'
						]
					],
					'error_details' => 'Invalid parameter "/1/problem_tags/2": value (tag, value)=(tag, value) already exists.'
				]
			],
			[
				[
					'name' => 'With trailing spaces',
					'trim' => true,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '    trimmed tag    ',
							'operator' => 'Equals',
							'value' => '   trimmed value    '
						]
					]
				]
			],
			[
				[
					'name' => 'Long tag name and value',
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'Long tag name. Long tag name. Long tag name. Long tag name. Long tag name.'
									.' Long tag name. Long tag name. Long tag name.',
							'operator' => 'Equals',
							'value' => 'Long tag value. Long tag value. Long tag value. Long tag value. Long tag value.'
									.' Long tag value. Long tag value. Long tag value. Long tag value.'
						]
					]
				]
			]
		];
	}

	/**
	 * Test creating of Service with problem tags.
	 *
	 * @dataProvider getCreateProblemTagsData
	 */
	public function testFormTagsServicesProblemTags_Create($data) {
		$this->checkTagsCreate($data, 'service');
	}

	public static function getUpdateProblemTagsData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '',
							'operator' => 'Contains',
							'value' => 'value1'
						]
					],
					'error_details'=>'Invalid parameter "/1/problem_tags/1/tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'tag' => 'problem action',
							'operator' => 'Contains',
							'value' => 'problem update'
						]
					],
					'error_details' => 'Invalid parameter "/1/problem_tags/2": value (tag, value)=(problem action,'.
							' problem update) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'tag' => 'problem tag without value',
							'operator' => 'Equals',
							'value' => ''
						]
					],
					'error_details' => 'Invalid parameter "/1/problem_tags/3": value (tag, value)'.
							'=(problem tag without value, ) already exists.'
				]
			],
			[
				[
					'trim' => true,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'new tag       ',
							'operator' => 'Contains',
							'value' => '   trimmed value    '
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'tag' => '    trimmed tag    ',
							'operator' => 'Contains',
							'value' => '        new value'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'tag' => '    trimmed tag2',
							'operator' => 'Equals',
							'value' => 'new value        '
						]
					]
				]
			],
			[
				[
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '!@#$%^&*()_+<>,.\/',
							'operator' => 'Contains',
							'value' => '!@#$%^&*()_+<>,.\/'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'tag' => 'tag1',
							'operator' => 'Equals',
							'value' => 'value1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'tag' => 'tag2',
							'operator' => 'Contains'
						],
						[
							'tag' => '{$MACRO:A}',
							'operator' => 'Equals',
							'value' => '{$MACRO:A}'
						],
						[
							'tag' => '{$MACRO}',
							'operator' => 'Equals',
							'value' => '{$MACRO}'
						],
						[
							'tag' => 'Тег',
							'operator' => 'Contains',
							'value' => 'Значение'
						]
					]
				]
			]
		];
	}

	/**
	 * Test update of Service with problem tags.
	 *
	 * @dataProvider getUpdateProblemTagsData
	 */
	public function testFormTagsServicesProblemTags_Update($data) {
		$this->checkTagsUpdate($data, 'service');
	}

	/**
	 * Test cloning of Service with problem tags.
	 */
	public function testFormTagsServicesProblemTags_Clone() {
		$this->executeCloning('service');
	}

	/**
	 * Test removing problem tags from Service.
	 */
	public function testFormTagsServicesProblemTags_RemoveTags() {
		$this->clearTags('service');
	}
}
