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

class czbxrpc{

	public static function call($method, $params, $source){
		
		$notifications = array(
			'apiinfo.version' => 1
		);
		
		if(is_null($params) && !isset($notifications[$method])){
			return array('error' => ZBX_API_ERROR_PARAMETERS, 'data' => 'Empty parameters');
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
	
	public static function auth($method, $params, $sessionid){
		global $USER_DETAILS;
		
		list($resource, $action) = explode('.', $method);
		
		$without_auth = array('info.version'); // list of methods which does not require athentication
		
		if(!str_in_array($method, $without_auth)){
			if(($resource == 'user') && ($action == 'authenticate')){
				$sessionid = null;

				$options = array(
					'users' => $params['user'],
					'extendoutput' => 1,
					'get_access' => 1
				);
				$users = API::User()->get($options);
				$user = reset($users);
				if($user['api_access'] != GROUP_API_ACCESS_ENABLED){
					return array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'No API access');
				}
			}

			if(empty($sessionid) && (($resource != 'user') || ($action != 'authenticate'))){
				return array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'Not authorized');
			}
			else if(!empty($sessionid)){
				if(!API::User()->checkAuthentication(array('sessionid' => $sessionid))){
					return array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'Not authorized');
				}

				$options = array(
					'userids' => $USER_DETAILS['userid'],
					'extendoutput' => 1,
					'get_access' => 1
				);
				$users = API::User()->get($options);
				$user = reset($users);
				if($user['api_access'] != GROUP_API_ACCESS_ENABLED){
					return array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'No API access');
				}
			}
		}
		
		return true;
	}
	
	private static function callJSON($method, $params){	
		// http bla bla
	}
	
	private static function callAPI($method, $params){	
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
	
}
?>