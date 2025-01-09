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


require_once dirname(__FILE__).'/../common/testFormTags.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @dataSource EntitiesTags
 *
 * @backup connector
 */
class testFormTagsConnectors extends testFormTags {

	public $link = 'zabbix.php?action=connector.list';
	public $update_name = 'Connector with tags for updating';
	public $clone_name = 'Connector with tags for cloning';
	public $remove_name = 'Connector for removing tags';
	protected $tags_table = 'id:tags';

	public function getCreateConnectorTagsData() {
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
					'error_details' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
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
					'error_details' => 'Invalid parameter "/1/tags/2": value (tag, operator, value)=(tag, 0, value) already exists.'
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
	 * Test creating of Connector with tags.
	 *
	 * @dataProvider getCreateConnectorTagsData
	 */
	public function testFormTagsConnectors_Create($data) {
		$this->checkTagsCreate($data, 'connector');
	}

	public static function getUpdateConnectorTagsData() {
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
					'error_details'=>'Invalid parameter "/1/tags/1/tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'tag' => 'connector action',
							'operator' => 'Contains',
							'value' => 'connector update'
						]
					],
					'error_details' => 'Invalid parameter "/1/tags/2": value (tag, operator, value)=(connector action,'.
						' 2, connector update) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'tag' => 'connector tag without value',
							'operator' => 'Equals',
							'value' => ''
						]
					],
					'error_details' => 'Invalid parameter "/1/tags/3": value (tag, operator, value)'.
						'=(connector tag without value, 0, ) already exists.'
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
	 * Test update of Connector with tags.
	 *
	 * @dataProvider getUpdateConnectorTagsData
	 */
	public function testFormTagsConnectors_Update($data) {
		$this->checkTagsUpdate($data, 'connector');
	}

	/**
	 * Test cloning of Connector with tags.
	 */
	public function testFormTagsConnectors_Clone() {
		$this->executeCloning('connector', 'Clone');
	}

	/**
	 * Test removing tags from Connector.
	 */
	public function testFormTagsConnectors_RemoveTags() {
		$this->clearTags('connector');
	}
}
