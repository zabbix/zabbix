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

require_once dirname(__FILE__).'/../common/testFormApiTokens.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup token
 *
 * @onBefore prepareUserTokenData
 */
class testFormApiTokensUserSettings extends testFormApiTokens {

	public $url = 'zabbix.php?action=user.token.list';

	/**
	 * Function creates the given API tokens in the test branch.
	 */
	public static function prepareUserTokenData() {
		$response = CDataHelper::call('token.create', [
			[
				'name' => 'Admin reference token',
				'userid' => 1,
				'description' => 'admin token to be used in update scenarios',
				'status' => '0',
				'expires_at' => '1798754399'
			],
			[
				'name' => 'Token to be deleted',
				'userid' => 1,
				'description' => 'Token to be deleted in the delete scenario',
				'status' => '0',
				'expires_at' => '1798754399'
			],
			[
				'name' => 'Token for cancel or simple update',
				'userid' => 1,
				'description' => 'Token for testing cancelling',
				'status' => '0',
				'expires_at' => '1798754399'
			]
		]);

		// Generate token strings for the created tokens.
		foreach ($response['tokenids'] as $tokenid) {
			CDataHelper::call('token.generate', ['tokenids' => $tokenid]);
		}

		self::$update_token = 'Admin reference token';
	}

	public function getTokenData() {
		return [
			// Empty name.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'Set expiration date and time' => false
					],
					'error_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// Empty spaces used as name.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '   ',
						'Set expiration date and time' => false
					],
					'error_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// Empty "Expires at" field if "Set expiration date and time" is true.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty Expires at token',
						'Set expiration date and time' => true,
						'Expires at' => ''
					],
					'error_details' => 'Incorrect value for field "expires_at": a time is expected.'
				]
			],
			// Incorrect "Expires at" field format.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Incorrect Expires at format token',
						'Set expiration date and time' => true,
						'Expires at' => '01-01-2021 00:00:00'
					],
					'error_details' => 'Incorrect value for field "expires_at": a time is expected.'
				]
			],
			// "Expires at" field value too far in the future.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Expires at too far in the future  token',
						'Set expiration date and time' => true,
						'Expires at' => '2050-01-01 00:00:00'
					],
					'error_details' => 'Invalid parameter "/1/expires_at": a number is too large.'
				]
			],
			// "Expires at" field value too far in the past.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Expires at too far in the past token',
						'Set expiration date and time' => true,
						'Expires at' => '1021-01-01 00:00:00'
					],
					'error_details' => 'Invalid parameter "/1/expires_at": a number is too large.'
				]
			],
			// Correct format but wrong numbers in the "Expires at" field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Wrong time value in Expires at field token',
						'Set expiration date and time' => true,
						'Expires at' => '2021-01-01 24:00:00'
					],
					'error_details' => 'Incorrect value for field "expires_at": a time is expected.'
				]
			],
			// Correct format but wrong numbers in the "Expires at" field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Wrong time value in Expires at field token',
						'Set expiration date and time' => true,
						'Expires at' => '2021-01-01 24:00:00'
					],
					'error_details' => 'Incorrect value for field "expires_at": a time is expected.'
				]
			],
			// API token with special symbols in name and without an expiry date.
			[
				[
					'fields' => [
						'Name' => 'Бесконечно aktīvs token - 頑張って!',
						'Description' => '',
						'Set expiration date and time' => false,
						'Enabled' => true
					]
				]
			],
			// API token with expiry date in the past.
			[
				[
					'fields' => [
						'Name' => 'Expired API token',
						'Description' => 'Token that is already expired when created',
						'Set expiration date and time' => true,
						'Expires at' => '1970-01-01 00:00:00',
						'Enabled' => true
					],
					'already_expired' => true
				]
			],
			// API token with expiry date in the future.
			[
				[
					'fields' => [
						'Name' => 'API token till 2038',
						'Description' => 'Token that will expire only in 2038',
						'Set expiration date and time' => true,
						'Expires at' => '2038-01-01 00:00:00',
						'Enabled' => true
					]
				]
			],
			// Disabled API token.
			[
				[
					'fields' => [
						'Name' => 'Disabled API token',
						'Description' => 'Token that is created with status Disabled',
						'Set expiration date and time' => false,
						'Enabled' => false
					]
				]
			]
		];
	}

	public function testFormApiTokensUserSettings_Layout() {
		$this->checkTokensFormLayout('user settings');
	}

	public function testFormApiTokensUserSettings_RegenerationFormLayout() {
		$this->checkTokensRegenerateFormLayout('user settings');
	}

	/**
	 * @backupOnce token
	 *
	 * @dataProvider getTokenData
	 */
	public function testFormApiTokensUserSettings_Create($data) {
		$this->checkTokensAction($data, 'create');
	}

	/**
	 * @backupOnce token
	 *
	 * @dataProvider getTokenData
	 */
	public function testFormApiTokensUserSettings_Update($data) {
		$this->checkTokensAction($data, 'update', self::$update_token);
	}

	public function testFormApiTokensUserSettings_SimpleUpdate() {
		$this->checkTokenSimpleUpdate();
	}

	public function testFormApiTokensUserSettings_CancelCreate() {
		$this->checkTokenCancel();
	}

	public function testFormApiTokensUserSettings_CancelUpdate() {
		$this->checkTokenCancel('update');
	}

	public function testFormApiTokensUserSettings_Delete() {
		$this->checkTokenDelete();
	}

	public function testFormApiTokensUserSettings_Regenerate() {
		$data = [
			'fields' => [
				'Name' => 'Token for cancel or simple update',
				'Description' => 'Token for testing cancelling',
				'Set expiration date and time' => true,
				'Expires at' => '2026-12-31 23:59:59',
				'Enabled' => true
			]
		];

		$this->checkTokensAction($data, 'regenerate', $data['fields']['Name']);
	}
}
