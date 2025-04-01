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


require_once __DIR__ . '/../include/CAPITest.php';
require_once __DIR__ . '/../include/helpers/CTestDataHelper.php';
require_once __DIR__ . '/../../include/classes/helpers/CMediatypeHelper.php';

/**
 *
 *
 * @onBefore prepareTestData
 *
 * @onAfter cleanTestData
 */
class testMediatype extends CAPITest {

	public static function updateInvalidDataProvider(): array {
		return [
			'Email media type gmail do not support SMTP_AUTHENTICATION_NONE' =>
			[
				[[
					'mediatypeid' => ':media_type:Email media type gmail',
					'smtp_authentication' => SMTP_AUTHENTICATION_NONE
				]],
				'Invalid parameter "/1/smtp_authentication": value must be one of 1, 2.'
			],
			'Provider with SMTP_AUTHENTICATION_NONE change requires smtp_authnetication change' =>
			[
				[[
					'mediatypeid' => ':media_type:Email media type SMTP',
					'provider' => CMediatypeHelper::EMAIL_PROVIDER_GMAIL
				]],
				'Invalid parameter "/1": the parameter "smtp_authentication" is missing.'
			],
			'tokens_status update OAUTH_ACCESS_TOKEN_VALID require access_token' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth without tokens',
					'tokens_status' => OAUTH_ACCESS_TOKEN_VALID,
					'access_expires_in' => 600
				]],
				'Invalid parameter "/1": the parameter "access_token" is missing.'
			],
			'tokens_status update OAUTH_ACCESS_TOKEN_VALID require access_expires_in' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth without tokens',
					'tokens_status' => OAUTH_ACCESS_TOKEN_VALID,
					'access_token' => 'token'
				]],
				'Invalid parameter "/1": the parameter "access_expires_in" is missing.'
			],
			'OAuth is not supported for Office relay' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth with tokens',
					'provider' => CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY,
					'smtp_authentication' => SMTP_AUTHENTICATION_OAUTH,
				]],
				'Invalid parameter "/1/smtp_authentication": value must be one of 0, 1.'
			],
			'OAuth not supported for SMTP_AUTHENTICATION_NONE' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth with tokens',
					'provider' => CMediatypeHelper::EMAIL_PROVIDER_SMTP,
					'smtp_authentication' => SMTP_AUTHENTICATION_NONE,
					'redirection_url' => 'http://updated.example.com'
				]],
				'Invalid parameter "/1/redirection_url": value must be empty.'
			],
			'OAuth not supported for SMTP_AUTHENTICATION_PASSWORD' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth with tokens',
					'provider' => CMediatypeHelper::EMAIL_PROVIDER_SMTP,
					'smtp_authentication' => SMTP_AUTHENTICATION_PASSWORD,
					'redirection_url' => 'http://example.com'
				]],
				'Invalid parameter "/1/redirection_url": value must be empty.'
			],
			'OAuth not supported for script media type' =>
			[
				[[
					'mediatypeid' => ':media_type:Script media type',
					'smtp_authentication' => SMTP_AUTHENTICATION_PASSWORD
				]],
				'Invalid parameter "/1/smtp_authentication": value must be 0.'
			]
		];
	}

	public static function updateValidDataProvider(): array {
		return [
			'Provider with SMTP_AUTHENTICATION_NONE change requires smtp_authnetication change' =>
			[
				[[
					'mediatypeid' => ':media_type:Email media type SMTP',
					'provider' => CMediatypeHelper::EMAIL_PROVIDER_GMAIL,
					'smtp_authentication' => SMTP_AUTHENTICATION_PASSWORD,
					'passwd' => 'passwordpassword'
				]],
				null
			],
			'Update access token' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth without tokens',
					'tokens_status' => OAUTH_ACCESS_TOKEN_VALID,
					'access_token' => 'token',
					'access_expires_in' => 600
				]],
				null
			],
			'Enable access token' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth with tokens invalid',
					'tokens_status' => OAUTH_ACCESS_TOKEN_VALID
				]],
				null
			],
			'Update refresh token' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth without tokens',
					'tokens_status' => OAUTH_REFRESH_TOKEN_VALID,
					'refresh_token' => 'token'
				]],
				null
			],
			'Enable refresh token' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth with tokens invalid',
					'tokens_status' => OAUTH_REFRESH_TOKEN_VALID
				]],
				null
			],
			'Update access and refresh token' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth without tokens',
					'tokens_status' => OAUTH_ACCESS_TOKEN_VALID | OAUTH_REFRESH_TOKEN_VALID,
					'access_token' => 'token',
					'access_expires_in' => 600,
					'refresh_token' => 'token'
				]],
				null
			],
			'Update token_status to invalidate both tokens' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth without tokens',
					'tokens_status' => 0
				]],
				null
			]
		];
	}

	/**
	 * @dataProvider updateInvalidDataProvider
	 * @dataProvider updateValidDataProvider
	 */
	public function testUpdate(array $mediatypes, $expected_error) {
		CTestDataHelper::convertMediatypesReferences($mediatypes);
		$this->call('mediatype.update', $mediatypes, $expected_error);
	}

	public static function updateAccessTokenUpdatedDataProvider(): array {
		return [
			'access_token change updates access_token_updated' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth access_token_updated',
					'access_token' => 'updated',
					'access_expires_in' => SEC_PER_HOUR
				]],
				true
			],
			'One of token reset fields change resets access_token_updated' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth access_token_updated',
					'client_id' => 'updated',
					'client_secret' => 'updated',
					'token_url' => 'updated'
				]],
				true
			],
			'refresh_token change do not affect access_token_updated' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth access_token_updated',
					'refresh_token' => 'updated'
				]],
				false
			],
			'token_status change do not affect access_token_updated' =>
			[
				[[
					'mediatypeid' => ':media_type:OAuth access_token_updated',
					'tokens_status' => 0
				]],
				false
			]
		];
	}

	/**
	 * @dataProvider updateAccessTokenUpdatedDataProvider
	 */
	public function testUpdateAccessTokenUpdated(array $mediatypes, bool $should_change) {
		$db_access_token_updated = [];
		CTestDataHelper::convertMediatypesReferences($mediatypes);

		$result = $this->call('mediatype.get', [
			'output' => ['mediatypeid', 'access_token_updated'],
			'mediatypeids' => array_column($mediatypes, 'mediatypeid')
		])['result'];
		$db_access_token_updated[] = array_column($result, 'access_token_updated', 'mediatypeid');

		$this->call('mediatype.update', $mediatypes);

		$result = $this->call('mediatype.get', [
			'output' => ['mediatypeid', 'access_token_updated'],
			'mediatypeids' => array_column($mediatypes, 'mediatypeid')
		])['result'];
		$db_access_token_updated[] = array_column($result, 'access_token_updated', 'mediatypeid');

		if ($should_change) {
			$this->assertNotEquals($db_access_token_updated[0], $db_access_token_updated[1],
				'Field access_token_updated should be changed.'
			);
		}
		else {
			$this->assertEquals($db_access_token_updated[0], $db_access_token_updated[1],
				'Field access_token_updated should stay unchanged.'
			);
		}
	}

	public static function createInvalidDataProvider(): array {
		return [
			'OAuth is not supported for Office relay' =>
			[
				[[
					'name' => 'OAuth is not supported for Office relay',
					'type' => MEDIA_TYPE_EMAIL,
					'provider' => CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY,
					'smtp_server' => 'smtp.gmail.com',
					'smtp_helo' => 'example.com',
					'smtp_email' => 'zabbix@example.com',
					'smtp_authentication' => SMTP_AUTHENTICATION_OAUTH,
					'redirection_url' => 'http://example.com',
					'client_id' => 'client_id',
					'client_secret' => 'client'
				]],
				'Invalid parameter "/1/smtp_authentication": value must be one of 0, 1.'
			],
			'OAuth not supported for SMTP_AUTHENTICATION_NONE' =>
			[
				[[
					'name' => 'OAuth not supported for SMTP_AUTHENTICATION_NONE',
					'type' => MEDIA_TYPE_EMAIL,
					'provider' => CMediatypeHelper::EMAIL_PROVIDER_SMTP,
					'smtp_server' => 'smtp.gmail.com',
					'smtp_helo' => 'example.com',
					'smtp_email' => 'zabbix@example.com',
					'smtp_authentication' => SMTP_AUTHENTICATION_NONE,
					'redirection_url' => 'http://example.com'
				]],
				'Invalid parameter "/1/redirection_url": value must be empty.'
			],
			'OAuth not supported for SMTP_AUTHENTICATION_PASSWORD' =>
			[
				[[
					'name' => 'OAuth not supported for SMTP_AUTHENTICATION_PASSWORD',
					'type' => MEDIA_TYPE_EMAIL,
					'provider' => CMediatypeHelper::EMAIL_PROVIDER_SMTP,
					'smtp_server' => 'smtp.gmail.com',
					'smtp_helo' => 'example.com',
					'smtp_email' => 'zabbix@example.com',
					'smtp_authentication' => SMTP_AUTHENTICATION_PASSWORD,
					'redirection_url' => 'http://example.com'
				]],
				'Invalid parameter "/1/redirection_url": value must be empty.'
			]
		];
	}

	/**
	 * @dataProvider createInvalidDataProvider
	 */
	public function testCreate(array $mediatypes, $expected_error) {
		$this->call('mediatype.create', $mediatypes, $expected_error);
	}

	public static function createAccessTokenUpdatedDataProvider(): array {
		return [
			'access_token set access_token_updated' =>
			[
				[[
					'redirection_url' => 'http://example.com',
					'client_id' => 'clientid',
					'client_secret' => 'clientsecret',
					'authorization_url' => 'http://example.com',
					'token_url' => 'http://example.com',
					'access_token' => 'updated',
					'access_expires_in' => SEC_PER_HOUR
				]],
				true
			],
			'refresh_token do not set access_token_updated' =>
			[
				[[
					'redirection_url' => 'http://example.com',
					'client_id' => 'clientid',
					'client_secret' => 'clientsecret',
					'authorization_url' => 'http://example.com',
					'token_url' => 'http://example.com',
					'tokens_status' => OAUTH_REFRESH_TOKEN_VALID,
					'refresh_token' => 'refreshtoken'
				]],
				false
			],
			'SMTP_AUTHENTICATION_NONE or SMTP_AUTHENTICATION_PASSWORD do not set access_token_updated' =>
			[
				[[
					'smtp_authentication' => SMTP_AUTHENTICATION_NONE
				]],
				false
			]
		];
	}

	/**
	 * @dataProvider createAccessTokenUpdatedDataProvider
	 */
	public function testCreateAccessTokenUpdated(array $mediatypes, bool $should_change) {
		static $i = 0;

		$db_access_token_updated = [];
		$mediatype_defaults = [
			'name' => 'testCreateAccessTokenUpdated'.($i++),
			'type' => MEDIA_TYPE_EMAIL,
			'provider' => CMediatypeHelper::EMAIL_PROVIDER_SMTP,
			'smtp_server' => 'smtp.gmail.com',
			'smtp_helo' => 'example.com',
			'smtp_email' => 'zabbix@example.com',
			'smtp_authentication' => SMTP_AUTHENTICATION_OAUTH
		];

		foreach ($mediatypes as &$mediatype) {
			$mediatype += $mediatype_defaults;
		}

		$mediatypeids = $this->call('mediatype.create', $mediatypes)['result']['mediatypeids'];
		// database column media_type_oauth.access_token_updated default value
		$db_access_token_updated[] = array_fill_keys($mediatypeids, 0);

		$result = $this->call('mediatype.get', [
			'output' => ['mediatypeid', 'access_token_updated'],
			'mediatypeids' => $mediatypeids
		])['result'];
		$db_access_token_updated[] = array_column($result, 'access_token_updated', 'mediatypeid');

		$this->call('mediatype.delete', $mediatypeids);

		if ($should_change) {
			$this->assertNotEquals($db_access_token_updated[0], $db_access_token_updated[1],
				'Field access_token_updated should be changed.'
			);
		}
		else {
			$this->assertEquals($db_access_token_updated[0], $db_access_token_updated[1],
				'Field access_token_updated should contain default value.'
			);
		}
	}

	public function prepareTestData() {
		CTestDataHelper::createMediatypes([
			[
				'name' => 'Script media type',
				'type' => MEDIA_TYPE_EXEC,
				'exec_path' => 'testscript'
			],
			[
				'name' => 'Email media type gmail',
				'type' => MEDIA_TYPE_EMAIL,
				'provider' => CMediatypeHelper::EMAIL_PROVIDER_GMAIL,
				'smtp_server' => 'smtp.gmail.com',
				'smtp_helo' => 'example.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_authentication' => SMTP_AUTHENTICATION_PASSWORD,
				'passwd' => 'passwordpassword'
			],
			[
				'name' => 'Email media type SMTP',
				'type' => MEDIA_TYPE_EMAIL,
				'provider' => CMediatypeHelper::EMAIL_PROVIDER_SMTP,
				'smtp_server' => 'smtp.generic.com',
				'smtp_helo' => 'example.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_authentication' => SMTP_AUTHENTICATION_NONE
			],
			[
				'name' => 'OAuth access_token_updated',
				'type' => MEDIA_TYPE_EMAIL,
				'provider' => CMediatypeHelper::EMAIL_PROVIDER_GMAIL,
				'smtp_server' => 'smtp.gmail.com',
				'smtp_helo' => 'example.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_authentication' => SMTP_AUTHENTICATION_OAUTH,
				'redirection_url' => 'http://example.com',
				'client_id' => 'clientid',
				'client_secret' => 'clientsecret',
				'authorization_url' => 'http://example.com',
				'token_url' => 'http://example.com',
				'tokens_status' => OAUTH_ACCESS_TOKEN_VALID | OAUTH_REFRESH_TOKEN_VALID,
				'access_token' => 'accesstoken',
				'access_expires_in' => 600,
				'refresh_token' => 'refreshtoken'
			],
			[
				'name' => 'OAuth with tokens',
				'type' => MEDIA_TYPE_EMAIL,
				'provider' => CMediatypeHelper::EMAIL_PROVIDER_GMAIL,
				'smtp_server' => 'smtp.gmail.com',
				'smtp_helo' => 'example.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_authentication' => SMTP_AUTHENTICATION_OAUTH,
				'redirection_url' => 'http://example.com',
				'client_id' => 'clientid',
				'client_secret' => 'clientsecret',
				'authorization_url' => 'http://example.com',
				'token_url' => 'http://example.com',
				'tokens_status' => OAUTH_ACCESS_TOKEN_VALID | OAUTH_REFRESH_TOKEN_VALID,
				'access_token' => 'accesstoken',
				'access_expires_in' => 600,
				'refresh_token' => 'refreshtoken'
			],
			[
				'name' => 'OAuth with tokens invalid',
				'type' => MEDIA_TYPE_EMAIL,
				'provider' => CMediatypeHelper::EMAIL_PROVIDER_GMAIL,
				'smtp_server' => 'smtp.gmail.com',
				'smtp_helo' => 'example.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_authentication' => SMTP_AUTHENTICATION_OAUTH,
				'redirection_url' => 'http://example.com',
				'client_id' => 'clientid',
				'client_secret' => 'clientsecret',
				'authorization_url' => 'http://example.com',
				'token_url' => 'http://example.com',
				'tokens_status' => 0,
				'access_token' => 'accesstoken',
				'access_expires_in' => 600,
				'refresh_token' => 'refreshtoken'
			],
			[
				'name' => 'OAuth without tokens',
				'type' => MEDIA_TYPE_EMAIL,
				'provider' => CMediatypeHelper::EMAIL_PROVIDER_GMAIL,
				'smtp_server' => 'smtp.gmail.com',
				'smtp_helo' => 'example.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_authentication' => SMTP_AUTHENTICATION_OAUTH,
				'redirection_url' => 'http://example.com',
				'client_id' => 'clientid',
				'client_secret' => 'clientsecret',
				'authorization_url' => 'http://example.com',
				'token_url' => 'http://example.com',
				'tokens_status' => 0
			]
		]);
	}

	public static function cleanTestData() {
		CTestDataHelper::cleanUp();
	}
}
