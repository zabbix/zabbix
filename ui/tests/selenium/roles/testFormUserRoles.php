<?php /*
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
 * @backup role
 * @backup role_rule
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
						'Monitoring' => [false],
						'Inventory' => [false],
						'Reports' => [false]
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
						'Monitoring' => [false],
						'Inventory' => [false],
						'Reports' => [false],
						'Configuration' => [false]
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
						'Monitoring' => [false],
						'Inventory' => [false],
						'Reports' => [false],
						'Configuration' => [false],
						'Administration' => [false]
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
					'user_type' => 'Super admin',
					'fields' => [
						'Name' => [
							'super_admin_role'
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
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							'user_1_monitoring'
						],
						'Monitoring' => [
							'Dashboard' => false,
							'Problems' => false,
							'Hosts' => false,
							'Overview' => false,
							'Latest data' => false,
							'Screens' => false,
							'Maps' => false,
							'Services' => true
						],
						'Inventory' => [false],
						'Reports' => [false],
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
							'admin_1_monitoring'
						],
						'Monitoring' => [
							'Dashboard' => false,
							'Problems' => false,
							'Hosts' => false,
							'Overview' => false,
							'Latest data' => false,
							'Screens' => false,
							'Maps' => false,
							'Discovery' => false,
							'Services' => true
						],
						'Inventory' => [false],
						'Reports' => [false],
						'Configuration' => [false]
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
							'super_admin_1_monitoring'
						],
						'Monitoring' => [
							'Dashboard' => false,
							'Problems' => false,
							'Hosts' => false,
							'Overview' => false,
							'Latest data' => false,
							'Screens' => false,
							'Maps' => false,
							'Discovery' => false,
							'Services' => true
						],
						'Inventory' => [false],
						'Reports' => [false],
						'Configuration' => [false],
						'Administration' => [false]
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
							'super_admin_new_elements'
						],
						'Default access to new UI elements' => [false]
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
							'admin_new_elements'
						],
						'Default access to new UI elements' => [false]
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							'user_new_elements'
						],
						'Default access to new UI elements'=> [false]
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
							'super_admin_new_modules'
						],
						'Default access to new modules' => [false]
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
							'admin_new_modules'
						],
						'Default access to new modules' => [false]
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							'user_new_modules'
						],
						'Default access to new modules' => [false]
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
							'super_admin_api_access'
						],
						'Enabled' => [false]
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
							'admin_api_access'
						],
						'Enabled' => [false]
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							'user_api_access'
						],
						'Enabled' => [false]
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
							'super_admin_new_actions'
						],
						'Default access to new actions' => [false]
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
							'admin_new_actions'
						],
						'Default access to new actions' => [false]
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							'user_new_actions'
						],
						'Default access to new actions' => [false]
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							'user_api_methods'
						]
					],
					'api_methods' => [
						'dashboard.create',
						'dashboard.*',
						'*.create'
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_type' => 'User',
					'fields' => [
						'Name' => [
							'admin_api_methods'
						]
					],
					'api_methods' => [
						'dashboard.create',
						'dashboard.*',
						'*.create'
					],
					'message_header' => 'User role created'
				]
			],
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormUserRoles_Create($data) {
		$this->page->login()->open('zabbix.php?action=userrole.edit');

		$this->query('class:js-userrole-usertype')->one()->asZDropdown()->select($data['user_type']);
		$this->fillFluidForm($data['fields']);
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

	// Fill text field.
	public function fillText($section, $value) {
		$prefix = 'xpath:.//'.self::TABLE_FORM;
		$input_text_field = $this->query($prefix.'/label[text()="'.$section.'"]//following::input[@type="text" and @aria-required="true"]')->one();
		$input_text_field->fill($value);
	}

	// Fill checkbox element.
	public function fillCheckbox($section, $element, $status = null) {
		$prefix = 'xpath:.//'.self::TABLE_FORM;
		// this part needed if we want to checkin or checkout ALL checkboxes.
		if ($element === [true] || $element === [false]) {
			$all_checkbox = $this->query($prefix.'/label[text()="'.$section.'"]'.self::TABLE_CONTAINER.'//label/preceding-sibling::input[@type!="hidden"]')->all()->asCheckbox();
			if ($element === [true]) {
				$all_checkbox->check();
			}
			else {
				$all_checkbox->uncheck();
			}
		}
		// this part need if we want to checkin or checkout one checbox.
		else {
			$input_text_field = $this->query($prefix.'/label[text()="'.$section.'"]'.self::TABLE_CONTAINER.'//label[text()="'.$element.'"]/preceding-sibling::input[@type!="hidden"]');
			if ($status !== true) {
				$input_text_field->one()->asCheckbox()->uncheck();
			}
			else {
				$input_text_field->one()->asCheckbox()->check();
			}
		}
	}

	// Fill radio element.
	public function fillRadio($section, $element) {
		$prefix = 'xpath:.//'.self::TABLE_FORM;
		$this->query($prefix.'/label[text()="'.$section.'"]'.self::TABLE_CONTAINER.'//label[text()="'.$element.'"]/preceding-sibling::input[@type!="hidden"]')->one()->click();
	}

	// Find type of input field.
	public function findAttribute($section) {
		$prefix = 'xpath:.//'.self::TABLE_FORM.'/label[text()="'.$section.'"]';
		$finded_container = $this->query($prefix.self::TABLE_CONTAINER.'//input[@type!="hidden"]')->one()->getAttribute('type');
		return $finded_container;
	}

	// fill fluid form.
	public function fillFluidForm($fields) {
		// Here is section and value. Example Monitoring-> Dashboard or API methods.
		foreach ($fields as $section => $elements) {

			// Summon multiselect fill.
			if ($section === 'api_methods') {
				$this->fillMultiselect($elements);
			}

			// This one needed to checkin or checkout ALL elements at once for checbox elements..
			elseif ($elements === [true] || $elements === [false]) {
				$this->fillCheckbox($section, $elements);
			}
			else {
				// Here we find input type, is it - text, checbox or radio.
				$found = $this->findAttribute($section);
				// Here we can choose, what checbox we want to checkout or checkin.
				if ($found === 'checkbox') {
					foreach ($elements as $element => $status) {
						$this->fillCheckbox($section, $element, $status);
					}
				}
				else {
					// radio or text input left.
					foreach ($elements as $element) {
						if ($found === 'text') {
							$this->fillText($section, $element);
						}
						elseif ($found === 'radio') {
							$this->fillRadio($section, $element);
						}
					}
				}
			}
		}
	}

	// Fill multiselect field.
	public function fillMultiselect($methods) {
		$api_field = $this->query('class:multiselect-control')->asMultiselect()->one();
		$api_field->fill($methods);
	}

	// Check/Uncheck Access to actions.
	public function fillActions($elements) {

	}
}
