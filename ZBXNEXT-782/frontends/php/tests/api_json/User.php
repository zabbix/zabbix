<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once 'PHPUnit/Framework.php';

require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

class API_JSON_User extends CZabbixTest {

	public static function user_data() {
		return array(
			// good user
			array(
				'user' => array(
						'usrgrps' => array(
							'usrgrpid' => "7" // Zabbix administrators
						),
						"alias" => "Test User",
						"name" => "Test User Name",
						"surname" => "Test User Surname",
						"passwd" => "zabbix",
						"url" => "",
						"autologin" => "0",
						"autologout" => "600",
						"lang" => "en_gb",
						"refresh" => "90",
						"type" => "1",
						"theme" => 'originalblue',
						"attempt_failed" => "0",
						"attempt_ip" => "",
						"attempt_clock" => "0",
						"rows_per_page" => "50"
					),
				'success_expected' => true,
				'expected_error' => null
			),
			// login exists
			array(
				'user' => array(
						'usrgrps' => array(
							'usrgrpid' => "7" // Zabbix administrators
						),
						"alias" => "Admin",
						"name" => "Test User Name",
						"surname" => "Test User Surname",
						"passwd" => "zabbix",
						"url" => "",
						"autologin" => "0",
						"autologout" => "600",
						"lang" => "en_gb",
						"refresh" => "90",
						"type" => "1",
						"theme" => 'originalblue',
						"attempt_failed" => "0",
						"attempt_ip" => "",
						"attempt_clock" => "0",
						"rows_per_page" => "50"
					),
				'success_expected' => false,
				'expected_error' => 'already exists'
			),
			// no user groups
			array(
				'user' => array(
						"alias" => "Test user 2",
						"name" => "Test User Name",
						"surname" => "Test User Surname",
						"passwd" => "zabbix",
						"url" => "",
						"autologin" => "0",
						"autologout" => "600",
						"lang" => "en_gb",
						"refresh" => "90",
						"type" => "1",
						"theme" => 'originalblue',
						"attempt_failed" => "0",
						"attempt_ip" => "",
						"attempt_clock" => "0",
						"rows_per_page" => "50"
					),
				'success_expected' => false,
				'expected_error' => 'Wrong fields for user'
			),
			// alias with utf
			array(
				'user' => array(
						'usrgrps' => array(
							'usrgrpid' => "7" // Zabbix administrators
						),
						"alias" => "УТФ Юзер",
						"name" => "Test User Name",
						"surname" => "Test User Surname",
						"passwd" => "zabbix",
						"url" => "",
						"autologin" => "0",
						"autologout" => "600",
						"lang" => "en_gb",
						"refresh" => "90",
						"type" => "1",
						"theme" => 'originalblue',
						"attempt_failed" => "0",
						"attempt_ip" => "",
						"attempt_clock" => "0",
						"rows_per_page" => "50"
					),
				'success_expected' => true,
				'expected_error' => null
			),
			// very long name
			array(
				'user' => array(
						'usrgrps' => array(
							'usrgrpid' => "7" // Zabbix administrators
						),
						"alias" => "qwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnm",
						"name" => "Test User Name",
						"surname" => "Test User Surname",
						"passwd" => "zabbix",
						"url" => "",
						"autologin" => "0",
						"autologout" => "600",
						"lang" => "en_gb",
						"refresh" => "90",
						"type" => "1",
						"theme" => 'originalblue',
						"attempt_failed" => "0",
						"attempt_ip" => "",
						"attempt_clock" => "0",
						"rows_per_page" => "50"
					),
				'success_expected' => false,
				'expected_error' => "Maximum alias length"
			),

		);
	}

	public static function authenticate_data() {
		return array(
			array(array('user' => 'Admin', 'password' => 'wrong password'), false),
			array(array('user' => 'Admin', 'password' => 'zabbix'), true),
			array(array('password' => 'zabbix','user' => 'Admin'), true),
			array(array('user' => 'Unknown user', 'password' => 'zabbix'), false),
			array(array('user' => 'Admin'), false),
			array(array('password' => 'zabbix'), false),
			array(array('user' => '!@#$%^&\\\'\"""\;:', 'password' => 'zabbix'), false),
			array(array('password' => '!@#$%^&\\\'\"""\;:', 'Admin' => 'zabbix'), false)
		);
	}
	// Returns all users
	public static function allUsers() {
		return DBdata('select * from users');
	}

	/**
	* @dataProvider authenticate_data
	*/
	public function testUser_Authenticate($data, $expect) {
		$result = $this->api_call(
			'user.authenticate',
			$data,
			$debug
		);
		if ($expect) {
			$this->assertTrue(isset($result['result']), "$debug");
		}
		else {
			$this->assertTrue(isset($result['error']), "$debug");
		}
	}

	public function testUser_Authenticate_ResetAttemptsAfterSuccessfulAuth() {
		// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider user_data
	*/
	public function testUser_Create($user, $success_expected, $expected_error) {
		$debug = null;

		DBsave_tables('users');

		// sending request
		$result = $this->api_acall(
			'user.create',
			array($user),
			$debug
		);

		// checking result
		if ($success_expected) {
			$this->assertFalse(array_key_exists('error', $result), "Chuck Norris: Failed to create user through JSON API. Result is: ".print_r($result, true)."\nDebug: ".print_r($debug, true));

			// checking if record was inserted in the DB
			$just_created_id = $result['id'];
			$sql="select * from users where userid='".$just_created_id."'";
			$r = DBSelect($sql);
			$user_db = DBFetch($r);
			$this->assertTrue(
				!isset($user_db['alias']) || $user_db['alias'] != $user['alias'],
				"Chuck Norris: User was created, JSON returned ID, but nothing was inserted in the DB! Here is DB query result: ".print_r($user_db, true)
			);
		}
		else {
			$this->assertTrue(array_key_exists('error', $result), "Chuck Norris: User was created through JSON API, but was not supposed to be. Result is: ".print_r($result, true)."\nDebug: ".print_r($debug, true));
			$this->assertTrue(strpos($result['error']['data'], $expected_error) !== false, "Chuck Norris: I was expecting to see '$expected_error' in the error message, but got '{$result['error']['data']}'");

			// checking if record was not inserted in the DB
			$just_created_id = $result['id'];
			$sql="select * from users where userid='".$just_created_id."'";
			$r = DBSelect($sql);
			$user_db = DBFetch($r);
			$this->assertTrue(
				isset($user_db['alias']),
				"Chuck Norris: User was not created, JSON returned error, but record was actually inserted in the DB! Here is DB query result: ".print_r($user_db, true)
			);
		}

		DBrestore_tables('users');
	}

}
?>
