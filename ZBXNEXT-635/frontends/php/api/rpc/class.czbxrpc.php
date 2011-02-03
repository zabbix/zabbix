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

	private static $transactionStarted = false;


	public static function call($method, $params, $sessionid=null){
// List of methods without params
		$notifications = array(
			'apiinfo.version' => 1
		);
//-----

// list of methods which does not require authentication
		$without_auth = array(
			'apiinfo.version' => 1
		);
//-----

		if(is_null($params) && !isset($notifications[$method])){
			return array('error' => ZBX_API_ERROR_PARAMETERS, 'message' => _('Empty parameters'));
		}

		list($resource, $action) = explode('.', $method);

// Authentication {{{
		if(!isset($without_auth[$method]) && empty($sessionid)){

// compatibility mode
			if(($resource == 'user') && ($action == 'authenticate')) $action = 'login';

			if(empty($sessionid) && (($resource != 'user') || ($action != 'login'))){
				return array('error' => ZBX_API_ERROR_NO_AUTH, 'message' => 'Not authorized');
			}
			else if(!empty($sessionid)){
				if(!self::callAPI('user.checkAuthentication', $sessionid)){
					return array('error' => ZBX_API_ERROR_NO_AUTH, 'message' => 'Not authorized');
				}
			}
		}
// }}} Authentication

		return self::callAPI($method, $params);
	}

	private static function transactionBegin(){
		global $DB;

		if($DB['TRANSACTIONS'] == 0){
			DBstart();
			self::$transactionStarted = true;
		}
	}

	private static function transactionEnd($result){
		if(self::$transactionStarted){
			self::$transactionStarted = false;
			DBend($result);
		}
	}

	private static function callJSON($method, $params){
		// http bla bla
	}

	private static function callAPI($method, $params){
		if(is_array($params))
			unset($params['nopermissions']);

		list($resource, $action) = explode('.', $method);

		$class_name = 'C'.$resource;
		if(!class_exists($class_name)){
			return array('error' => ZBX_API_ERROR_PARAMETERS, 'message' => 'Resource ('.$resource.') does not exist');
		}

		if(!method_exists($class_name, $action)){
			return array('error' => ZBX_API_ERROR_PARAMETERS, 'message' => 'Action ('.$action.') does not exist');
		}

		try{
			self::transactionBegin();
			API::setReturnAPI();

			$result = call_user_func(array(API::getObject($resource), $action), $params);

			API::setReturnRPC();
			self::transactionEnd(true);

			return array('result' => $result);
		}
		catch(APIException $e){
			$result = ($method === 'user.login');
			self::transactionEnd($result);

			return array('error' => $e->getCode(), 'message' => $e->getMessage(), 'data' => $e->getTrace());
		}
	}

}
?>
