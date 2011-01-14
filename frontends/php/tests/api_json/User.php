<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once 'PHPUnit/Framework.php';

require_once(dirname(__FILE__).'/../include/class.czabbixtest.php');

class API_JSON_User extends CZabbixTest
{
	public static function authenticate_data()
	{
		return array(
			array(array('user'=>'Admin', 'password'=>'wrong password'), false),
			array(array('user'=>'Admin', 'password'=>'zabbix'), true),
			array(array('password'=>'zabbix','user'=>'Admin'), true),
			array(array('user'=>'Unknown user', 'password'=>'zabbix'), false),
			array(array('user'=>'Admin'), false),
			array(array('password'=>'zabbix'), false),
			array(array('user'=>'!@#$%^&\\\'\"""\;:', 'password'=>'zabbix'), false),
			array(array('password'=>'!@#$%^&\\\'\"""\;:', 'Admin'=>'zabbix'), false)
		);
	}
	// Returns all users
	public static function allUsers()
	{
		return DBdata('select * from users');
	}

	/**
	* @dataProvider authenticate_data
	*/
	public function testUser_Authenticate($data,$expect)
	{
		$result = $this->call_api(
			'user.authenticate',
			$data,
			&$debug);
		if($expect)
		{
			$this->assertTrue(isset($result['result']),"$debug");
		}
		else
		{
			$this->assertTrue(isset($result['error']),"$debug");
		}
	}

	/**
	* @dataProvider allUsers
	*/
	public function testPageUsers_SimpleTest($user)
	{
	}


	public function testUser_Authenticate_Documented()
	{
		$this->assertTrue(false,"Chuck Norris: API method 'user.authenticate' is not documented");
	}
}
?>
