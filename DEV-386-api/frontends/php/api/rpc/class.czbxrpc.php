<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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

class czbxrpc{
	public static $result;

	public static function call($method, $params, $sessionid=null){
		global $USER_DETAILS;

		$process = true;

// List of methods without params
		$notifications = array(
			'apiinfo.version' => 1
		);

		if(is_null($params) && !isset($notifications[$method])){
			self::$result = array('error' => ZBX_API_ERROR_PARAMETERS);
			return self::$result;
		}
//-----

		list($resource, $action) = explode('.', $method);

		if(defined('ZBX_API_REQUEST')){
		
			$without_auth = array('apiinfo.version'); // list of methods which does not require athentication
// Authentication {{{
			if(!str_in_array($method, $without_auth)){
				if(($resource == 'user') && ($action == 'authenticate')){
					$sessionid = null;

					$options = array(
								'users' => $params['user'],
								'extendoutput' => 1,
								'get_access' => 1
							);
					$users = CUser::get($options);
					$user = reset($users);
					if($user['api_access'] != GROUP_API_ACCESS_ENABLED){
						self::$result = array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'No API access');
						return self::$result;
					}
				}

				if(empty($sessionid) && (($resource != 'user') || ($action != 'authenticate'))){
					self::$result = array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'Not authorized');
					return self::$result;
				}
				else if(!empty($sessionid)){
					if(!CUser::checkAuthentication(array('sessionid' => $sessionid))){
						self::$result = array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'Not authorized');
						return self::$result;
					}

					$options = array(
							'userids' => $USER_DETAILS['userid'],
							'extendoutput' => 1,
							'get_access' => 1
						);
					$users = CUser::get($options);
					$user = reset($users);
					if($user['api_access'] != GROUP_API_ACCESS_ENABLED){
						self::$result = array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'No API access');
						return self::$result;
					}
				}
			}
// }}} Authentication
		}

		if(!method_exists('czbxrpc', $resource)){
			self::$result = array('error' => ZBX_API_ERROR_PARAMETERS, 'data' => 'Resource ('.$resource.') does not exist');
			return self::$result;
		}

		$class_name = 'C'.$resource;
		if(!method_exists($class_name, $action)){
			self::$result = array('error' => ZBX_API_ERROR_PARAMETERS, 'data' => 'Action ('.$action.') does not exist');
			return self::$result;
		}

		call_user_func(array('czbxrpc', $resource), $action, $params);

		if(self::$result !== false){
			self::$result = array('result' => self::$result);
		}
		else{
			self::$result = reset(CZBXAPI::$error);
		}

	return self::$result;
	}

// APIINFO
	private static function apiinfo($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIInfo', $action), $params);
		}
		self::$result = $result;
	}
// USER
	private static function user($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIUser', $action), $params);
		}
		self::$result = $result;
	}

// HOST GROUP
	private static function hostgroup($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIHostGroup', $action), $params);
		}
		self::$result = $result;
	}

// TEMPLATE
	private static function template($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPITemplate', $action), $params);
		}
		self::$result = $result;
	}

// HOST
	private static function host($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIHost', $action), $params);
		}
		self::$result = $result;
	}

// ITEM
	private static function item($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIItem', $action), $params);
		}
		self::$result = $result;
	}

// TRIGGER
	private static function trigger($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPITrigger', $action), $params);
		}
		self::$result = $result;
	}

// GRAPH
	private static function graph($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIGraph', $action), $params);
		}
		self::$result = $result;
	}

// ACTION
	private static function action($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIAction', $action), $params);
		}
		self::$result = $result;
	}

// ALERT
	private static function alert($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIAlert', $action), $params);
		}
		self::$result = $result;
	}

// APPLICATION
	private static function application($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIApplication', $action), $params);
		}
		self::$result = $result;
	}

// EVENT
	private static function event($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIEvent', $action), $params);
		}
		self::$result = $result;
	}

// GRAPHITEM
	private static function graphitem($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIGraphItem', $action), $params);
		}
		self::$result = $result;
	}

// MAINTENANCE
	private static function maintenance($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIMaintenance', $action), $params);
		}
		self::$result = $result;
	}

// MAP
	private static function map($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIMap', $action), $params);
		}
		self::$result = $result;
	}

// SCREEN
	private static function screen($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIScreen', $action), $params);
		}
		self::$result = $result;
	}

// SCRIPT
	private static function script($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIScript', $action), $params);
		}
		self::$result = $result;
	}

// USERGROUP
	private static function usergroup($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIUserGroup', $action), $params);
		}
		self::$result = $result;
	}

// USERMACRO
	private static function usermacro($action, $params){
		switch($action){
			default:
			$result = call_user_func(array('CAPIUserMacro', $action), $params);
		}
		self::$result = $result;
	}
}
?>
