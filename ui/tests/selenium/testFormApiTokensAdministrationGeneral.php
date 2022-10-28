<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/common/testFormApiTokens.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * @backup token
 *
 * @onBefore prepareTokenData
 * @dataSource LoginUsers
 */
class testFormApiTokensAdministrationGeneral extends testFormApiTokens {

	/**
	 * Function creates the given API tokens in the test branch.
	 */
	public static function prepareTokenData() {
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
				'name' => 'user-zabbix token',
				'userid' => 5,
				'description' => 'Token that is generated for user',
				'status' => '0',
				'expires_at' => '1798754399'
			]
		]);

		// Generate token strings for the created tokens.
		foreach ($response['tokenids'] as $tokenid) {
			CDataHelper::call('token.generate', ['tokenids' => $tokenid]);
		}
	}

	public function getTokenData() {
		return [
			// Empty name.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'User' => 'Admin',
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
						'User' => 'Admin',
						'Set expiration date and time' => false
					],
					'error_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// Empty User field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Blank username API token',
						'User' => '',
						'Set expiration date and time' => false
					],
					'error_details' => 'Field "userid" is mandatory.'
				]
			],
			// Empty "Expires at" field if "Set expiration date and time" is true.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty Expires at token',
						'User' => 'Admin',
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
						'User' => 'Admin',
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
						'User' => 'Admin',
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
						'User' => 'Admin',
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
						'User' => 'Admin',
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
						'User' => 'Admin',
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
						'User' => 'Admin',
						'Description' => '',
						'Set expiration date and time' => false,
						'Enabled' => true
					],
					'full_name' => 'Admin (Zabbix Administrator)'
				]
			],
			// API token with expiry date in the past.
			[
				[
					'fields' => [
						'Name' => 'Expired API token',
						'User' => 'Admin',
						'Description' => 'Token that is already expired when created',
						'Set expiration date and time' => true,
						'Expires at' => '1970-01-01 00:00:00',
						'Enabled' => true
					],
					'already_expired' => true,
					'full_name' => 'Admin (Zabbix Administrator)'
				]
			],
			// API token with expiry date in the future.
			[
				[
					'fields' => [
						'Name' => 'API token till 2038',
						'User' => 'Admin',
						'Description' => 'Token that will expire only in 2038',
						'Set expiration date and time' => true,
						'Expires at' => '2038-01-01 00:00:00',
						'Enabled' => true
					],
					'full_name' => 'Admin (Zabbix Administrator)'
				]
			],
			// Disabled API token.
			[
				[
					'fields' => [
						'Name' => 'Disabled API token',
						'User' => 'Admin',
						'Description' => 'Token that is created with status Disabled',
						'Set expiration date and time' => false,
						'Enabled' => false
					],
					'full_name' => 'Admin (Zabbix Administrator)'
				]
			],
			// API token for a different user.
			[
				[
					'fields' => [
						'Name' => 'API token for a different user',
						'User' => 'test-user',
						'Description' => 'Token that is created for the test-user user',
						'Set expiration date and time' => false,
						'Enabled' => true
					]
				]
			]
		];
	}

	public function testFormApiTokensAdministrationGeneral_Layout() {
		$this->checkTokensFormLayout('administration');
	}

	/**
	 * @onBeforeOnce getTokenId
	 */
	public function testFormApiTokensAdministrationGeneral_RegenerationFormLayout() {
		$this->checkTokensRegenerateFormLayout('administration');
	}

	/**
	 * @backupOnce token
	 *
	 * @dataProvider getTokenData
	 */
	public function testFormApiTokensAdministrationGeneral_Create($data) {
		$this->checkTokensAction($data, 'zabbix.php?action=token.edit', 'create');
	}

	/**
	 * @backupOnce token
	 *
	 * @onBeforeOnce getTokenId
	 *
	 * @dataProvider getTokenData
	 */
	public function testFormApiTokensAdministrationGeneral_Update($data) {
		// Skip the case with user name change as this field is disabled in token edit mode.
		if ($data['fields']['User'] !== 'Admin') {
			return;
		}

		$this->checkTokensAction($data, 'zabbix.php?action=token.edit&tokenid='.self::$tokenid, 'update');
	}

	public function testFormApiTokensAdministrationGeneral_UpdateOtherUserToken() {
		$data = [
			'fields' => [
				'Name' => 'Updated user-zabbix token',
				'User' => 'user-zabbix',
				'Description' => 'Updated token that belongs to user-zabbix user',
				'Set expiration date and time' => false,
				'Enabled' => false
			]
		];
		$token_id = $this->getTokenId(self::USER_ZABBIX_TOKEN);
		$this->checkTokensAction($data, 'zabbix.php?action=token.edit&tokenid='.$token_id, 'update');
	}

	/**
	 * @onBeforeOnce getTokenId
	 */
	public function testFormApiTokensAdministrationGeneral_SimpleUpdate() {
		$this->checkTokenSimpleUpdate('zabbix.php?action=token.edit&tokenid='.self::$tokenid);
	}

	/**
	 * @onBeforeOnce getTokenId
	 */
	public function testFormApiTokensAdministrationGeneral_Cancel() {
		$this->checkTokenCancel('zabbix.php?action=token.edit', 'Admin');
		$this->checkTokenCancel('zabbix.php?action=token.edit&tokenid='.self::$tokenid);
	}

	public function testFormApiTokensAdministrationGeneral_Delete() {
		$token_id = $this->getTokenId(self::DELETE_TOKEN);
		$this->checkTokenDelete('zabbix.php?action=token.edit&tokenid='.$token_id, self::DELETE_TOKEN);
	}

	/**
	 * @onBeforeOnce getTokenId
	 */
	public function testFormApiTokensAdministrationGeneral_Regenerate() {
		$data = [
			'fields' => [
				'Name' => 'Admin reference token',
				'User' => 'Admin (Zabbix Administrator)',
				'Description' => 'admin token to be used in update scenarios',
				'Set expiration date and time' => true,
				'Expires at' => '2026-12-31 23:59:59',
				'Enabled' => true
			],
			'tokenid' => self::$tokenid
		];
		$this->checkTokensAction($data, 'zabbix.php?action=token.edit&tokenid='.self::$tokenid, 'regenerate');
	}
}
