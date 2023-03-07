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


require_once dirname(__FILE__).'/../common/testPageGroups.php';

/**
 * @backup hosts
 *
 * @onBefore prepareGroupPageData, prepareTemplateGroupPageData
 *
 * @dataSource HostTemplateGroups
 */
class testPageTemplateGroup extends testPageGroups {

	public $link = 'zabbix.php?action=templategroup.list';
	public $object = 'template';
	const GROUP1 = 'Group with one template testPageTemplateGroup';
	const TEMPLATE1_1 = 'Template for testPageHostGroup';
	const GROUP2 = 'Group with two templates testPageTemplateGroup';
	const TEMPLATE2_1 = 'One template for testPageHostGroup';
	const TEMPLATE2_2 = 'Two template for testPageHostGroup';

	/**
	 * Prepare data for template groups test.
	 */
	public static function prepareTemplateGroupPageData() {
		// Creata three groups with disabled hosts and two groups with enabled hosts for testing.
		CDataHelper::call('templategroup.create', [
			[
				'name' => 'Templates/testPageTemplateGroup'
			],
			[
				'name' => self::GROUP1
			],
			[
				'name' => self::GROUP2
			]
		]);
		$template_groupids = CDataHelper::getIds('name');

		CDataHelper::createTemplates([
			[
				'host' => self::TEMPLATE1_1,
				'groups' => [
					'groupid' => $template_groupids[self::GROUP1]
				]
			],
			[
				'host' => self::TEMPLATE2_1,
				'groups' => [
					'groupid' => $template_groupids[self::GROUP2]
				]
			],
			[
				'host' => self::TEMPLATE2_2,
				'groups' => [
					'groupid' => $template_groupids[self::GROUP2]
				]
			]
		]);
	}

	public static function getLayoutData() {
		return [
			[
				[
					[
						'Name' => 'Templates/testPageTemplateGroup',
						'Count' => '',
						'Templates' => ''
					],
					[
						'Name' => self::GROUP1,
						'Templates' => '1',
						'Templates' => self::TEMPLATE1_1
					],
					[
						'Name' => self::GROUP2,
						'Count' => '2',
						'Templates' => self::TEMPLATE2_1.', '.self::TEMPLATE2_2
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testPageTemplateGroup_Layout($data) {
		$links = [
			'name' => self::GROUP1,
			'count' => '1',
			'host_template' => self::TEMPLATE1_1
		];
		$this->layout($data, $links);
	}

	public function testPageTemplateGroup_Sort() {
		$this->coulmnSorting();
	}

	public static function getFilterData() {
		return [
			// Too many spaces in field.
			[
				[
					'Name' => '  '
				]
			],
			[
				[
					'Name' => '  with'
				]
			],
			[
				[
					'Name' => 'with  '
				]
			],
			// Special symbols.
			[
				[
					'Name' => '☺&<>//\\[]""#@'
				]
			],
			// Exact match.
			[
				[
					'Name' => 'Group with one template testPageTemplateGroup',
					'expected' => ['Group with one template testPageTemplateGroup']
				]
			],
			// Partial match.
			[
				[
					'Name' => 'with one',
					'expected' => ['Group with one template testPageTemplateGroup']
				]
			],
			[
				[
					'Name' => ' with ',
					'expected' => ['Group with one template testPageTemplateGroup',	'Group with two templates testPageTemplateGroup']
				]
			],
			[
				[
					'Name' => 'Group with',
					'expected' => ['Group with one template testPageTemplateGroup',	'Group with two templates testPageTemplateGroup']
				]
			],
			// Not case sensitive.
			[
				[
					'Name' => 'page',
					'expected' => ['Group with one template testPageTemplateGroup',
						'Group with two templates testPageTemplateGroup', 'Templates/testPageTemplateGroup'
					]
				]
			],
			[
				[
					'Name' => 'PAGE',
					'expected' => ['Group with one template testPageTemplateGroup',
						'Group with two templates testPageTemplateGroup', 'Templates/testPageTemplateGroup'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageTemplateGroup_Filter($data) {
		$this->filter($data);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testPageTemplateGroup_Cancel($data) {
		$this->cancel($data);
	}

	public static function getPageTemplateDeleteData() {
		return [
			// Delete all.
			[
				[
					'expected' => TEST_BAD,
					'error' => 'Template "Linux by Zabbix agent" cannot be without template group.'
				]
			],
			// One of the groups can't be deleted.
			[
				[
					'expected' => TEST_BAD,
					'groups' => ['One group for Delete', 'Group empty for Delete test'],
					'error' => 'Template "Template for template group testing" cannot be without template group.'
				]
			],
			// The group can't be deleted.
			[
				[
					'expected' => TEST_BAD,
					'groups' => 'One group for Delete',
					'error' => 'Template "Template for template group testing" cannot be without template group.'
				]
			]
		];
	}

	/**
	 * @dataProvider getPageTemplateDeleteData
	 * @dataProvider getDeleteData
	 */
	public function testPageTemplateGroup_Delete($data) {
		$this->delete($data);
	}
}
