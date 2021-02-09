<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup-once role
 * @backup-once role_rule
 */
class testFormUserRoles extends CWebTest {

	const TABLE_FORM = 'div[contains(@class, "form-grid")]';
	const TABLE_CONTAINER = '/following-sibling::div[1][contains(@class, "form-field") and not(contains(@class, "offset-1"))]';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public static function getCreateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							''
						]
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'Admin',
					'fields' => [
						'Name' => [
							''
						]
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'Super admin',
					'fields' => [
						'Name' => [
							''
						]
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							' '
						]
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'Admin',
					'fields' => [
						'Name' => [
							' '
						]
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'Super admin',
					'fields' => [
						'Name' => [
							' '
						]
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							'disabled_monitoring'
						],
						'Monitoring' => ['uncheck_all'],
						'Inventory' => ['uncheck_all'],
						'Reports' => ['uncheck_all']
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be checked.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'Admin',
					'fields' => [
						'Name' => [
							'disabled_monitoring'
						],
						'Monitoring' => ['uncheck_all'],
						'Inventory' => ['uncheck_all'],
						'Reports' => ['uncheck_all'],
						'Configuration' => ['uncheck_all']
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be checked.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'Super admin',
					'fields' => [
						'Name' => [
							'disabled_monitoring'
						],
						'Monitoring' => ['uncheck_all'],
						'Inventory' => ['uncheck_all'],
						'Reports' => ['uncheck_all'],
						'Configuration' => ['uncheck_all'],
						'Administration' => ['uncheck_all']
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be checked.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							'Admin role'
						]
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'User role with name "Admin role" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'Admin',
					'fields' => [
						'Name' => [
							'Admin role'
						]
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'User role with name "Admin role" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'user_type' => 'Super admin',
					'fields' => [
						'Name' => [
							'Admin role'
						]
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'User role with name "Admin role" already exists.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							'user_role'
						]
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_type' => 'Admin',
					'fields' => [
						'Name' => [
							'admin_role'
						]
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_type' => 'Super admin',
					'fields' => [
						'Name' => [
							'super_admin_role'
						]
					],
					'message_header' => 'User role created'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormUserRoles_Create($data) {
		$this->page->login()->open('zabbix.php?action=userrole.edit');

		$this->query('class:js-userrole-usertype')->one()->asZDropdown()->select($data['user_type']);
		$this->fillFluidform($data['fields']);

		$this->query('button:Add')->one()->click();
		switch ($data['expected']) {
			case TEST_BAD:
				$this->assertMessage(TEST_BAD, $data['message_header'], $data['message_details']);
				break;

			case TEST_GOOD:
				$this->assertMessage(TEST_GOOD, $data['message_header']);
				break;
		}

	}

	public function fillText($field, $value) {
		$prefix = 'xpath:.//'.self::TABLE_FORM;
		$input_text_field = $this->query($prefix.'/label[text()="'.$field.'"]//following::input[@type="text" and @aria-required="true"]')->one();
		$input_text_field->fill($value);
	}

	public function fillCheckbox($field, $value, $set = null) {
		$prefix = 'xpath:.//'.self::TABLE_FORM;
		if ($value === 'uncheck_all') {
			$input_text_field[] = $this->query($prefix.'/label[text()="'.$field.'"]'.self::TABLE_CONTAINER.'//label/preceding-sibling::input[@type!="hidden"]')->all()->asCheckbox()->uncheck();
		}
		else {
			$input_text_field = $this->query($prefix.'/label[text()="'.$field.'"]'.self::TABLE_CONTAINER.'//label[text()="'.$value.'"]/preceding-sibling::input[@type!="hidden"]');
			$input_text_field->one()->asCheckbox()->check();
				if ($set !== null) {
				$input_text_field->one()->asCheckbox()->uncheck();
			}
		}
	}

	public function fillRadio($field, $value) {
		$prefix = 'xpath:.//'.self::TABLE_FORM;
		$this->query($prefix.'/label[text()="'.$field.'"]'.self::TABLE_CONTAINER.'//label[text()="'.$value.'"]/preceding-sibling::input[@type!="hidden"]')->one()->click();
	}

	public function findAttribute($label) {
		$prefix = 'xpath:.//'.self::TABLE_FORM.'/label[text()="'.$label.'"]';
		$finded_container = $this->query($prefix.self::TABLE_CONTAINER.'//input[not(@type="hidden")]')->one()->getAttribute('type');
		return $finded_container;
	}

	public function fillFluidform($field, $uncheck = null) {
		foreach ($field as $fieldi => $fields) {
			$found = $this->findAttribute($fieldi);
			foreach ($fields as $fieldss) {
				if ($found === 'text') {
					$this->fillText($fieldi, $fieldss);
				}
				if ($found === 'checkbox') {
					$this->fillCheckbox($fieldi, $fieldss, $uncheck);
				}
				if ($found === 'radio') {
					$this->fillRadio($fieldi, $fieldss);
				}
			}
		}
	}
}
