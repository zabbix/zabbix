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

	private static function callJSON($method, $params){
		// http bla bla
	}

	private static function callAPI($method, $params){
		unset($params['nopermissions']);

		list($resource, $action) = explode('.', $method);

		$class_name = 'C'.$resource;

		if(!class_exists($class_name)){
			return array('error' => ZBX_API_ERROR_PARAMETERS, 'data' => 'Resource ('.$resource.') does not exist');
		}

		if(!method_exists($class_name, $action)){
			return array('error' => ZBX_API_ERROR_PARAMETERS, 'data' => 'Action ('.$action.') does not exist');
		}

		try{
			DBstart();
			$result = call_user_func(array($class_name, $action), $params);
			DBend(true);

			return array('result' => $result);
		}
		catch(APIException $e){
			DBend(false);
			return array('error' => $e->getCode(), 'data' => $e->getErrors(), 'trace' => $e->getTrace());
		}
	}

	public static function call($method, $params, $sessionid=null){
// List of methods without params
		$notifications = array(
			'apiinfo.version' => 1
		);
//-----

// list of methods which does not require athentication
		$without_auth = array(
			'apiinfo.version' => 1
		);
//-----

		if(is_null($params) && !isset($notifications[$method])){
			return array('error' => ZBX_API_ERROR_PARAMETERS);
		}

		list($resource, $action) = explode('.', $method);
		if(!isset($without_auth[$method])){
// Authentication {{{

// compatibility mode
			if(($resource == 'user') && ($action == 'authenticate')) $action = 'login';
//----------

			if(empty($sessionid) && (($resource != 'user') || ($action != 'login'))){
				return array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'Not authorized');
			}
			else if(!empty($sessionid)){
				if(!CUser::simpleAuth($sessionid)){
					return array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'Not authorized');
				}
			}
// }}} Authentication
		}


		switch($source){
			case DATA_SOURCE_API:
				return self::callAPI($method, $params);
			break;
			case DATA_SOURCE_JSON:
				return self::callJSON($method, $params);
			break;
		}
	}
}
?>
