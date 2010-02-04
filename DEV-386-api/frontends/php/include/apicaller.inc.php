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

define('DATA_SOURCE_API', 'api');
define('DATA_SOURCE_JSON', 'json');

class APICaller{
	
	public static function call($method, $params, $source){
		
		$notifications = array(
			'apiinfo.version' => 1
		);
		
		if(is_null($params) && !isset($notifications[$method])){
			return array('error' => ZBX_API_ERROR_PARAMETERS, 'data' => 'Resource ('.$resource.') does not exist');
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
	
	public static function auth($sessionid){
	
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
	
	private static function callJSON($method, $params){	
		global $USER_DETAILS;
		// http bla bla
	}
	
	private static function callAPI($method, $params){
	
		list($resource, $action) = explode('.', $method);
		
		$class_name = 'CAPI'.$resource;
		
		if(!class_exists($class_name)){
			return array('error' => ZBX_API_ERROR_PARAMETERS, 'data' => 'Resource ('.$resource.') does not exist');
		}

		if(!method_exists($class_name, $action)){
			return array('error' => ZBX_API_ERROR_PARAMETERS, 'data' => 'Action ('.$action.') does not exist');
		}

		try{
			$result = call_user_func(array($class_name, $action), $params);
			return array('result' => $result);
		}
		catch(APIException $e){
			return array('error' => $e->getCode(), 'data' => $e->getErrors(), 'trace' => $e->getTrace());
		}		
	}
}

class CAPIObject{
	private $name;
	
	public function __construct($name){
		$this->name = $name;
	}
	
	public function __call($method, $params){
		$result = APICaller::call($this->name.$method, $params, DATA_SOURCE_API);
		if(isset($result['result'])) return $result['result'];
		else{
			$errors = zbx_toArray($result['data']);
			error($result['data']);
			return false;
		}
	}
	
}

class API{
	private static $action = null;
	private static $alert = null;
	private static $application = null;
	private static $event = null;
	private static $graph = null;
	private static $graphitem = null;
	private static $host = null;
	private static $hostgroup = null;
	private static $item = null;
	private static $maintenance = null;
	private static $map = null;
	private static $screen = null;
	private static $script = null;
	private static $template = null;
	private static $trigger = null;
	private static $user = null;
	private static $usergroup = null;
	private static $usermacro = null;
	
	public static function Action(){
		if(is_null(self::$action)) self::$action = new CAPIObject('action');
		return self::$action;
	}
	
	public static function Alert(){
		if(is_null(self::$alert)) self::$alert = new CAPIObject('alert');
		return self::$alert;
	}
	
	public static function Application(){
		if(is_null(self::$application)) self::$application = new CAPIObject('application');
		return self::$application;
	}
	
	public static function Event(){
		if(is_null(self::$event)) self::$event = new CAPIObject('event');
		return self::$event;
	}
	
	public static function Graph(){
		if(is_null(self::$graph)) self::$graph = new CAPIObject('graph');
		return self::$graph;
	}
	
	public static function GraphItem(){
		if(is_null(self::$graphitem)) self::$graphitem = new CAPIObject('graphitem');
		return self::$graphitem;
	}
	
	public static function Host(){
		if(is_null(self::$host)) self::$host = new CAPIObject('host');
		return self::$host;
	}
	
	public static function HostGroup(){
		if(is_null(self::$hostgroup)) self::$hostgroup = new CAPIObject('hostgroup');
		return self::$hostgroup;
	}
	
	public static function Item(){
		if(is_null(self::$item)) self::$item = new CAPIObject('item');
		return self::$item;
	}
	
	public static function Maintenance(){
		if(is_null(self::$maintenance)) self::$maintenance = new CAPIObject('maintenance');
		return self::$maintenance;
	}
	
	public static function Map(){
		if(is_null(self::$map)) self::$map = new CAPIObject('map');
		return self::$map;
	}
	
	public static function Screen(){
		if(is_null(self::$screen)) self::$screen = new CAPIObject('screen');
		return self::$screen;
	}
	
	public static function Script(){
		if(is_null(self::$script)) self::$script = new CAPIObject('script');
		return self::$script;
	}
	
	public static function Template(){
		if(is_null(self::$template)) self::$template = new CAPIObject('template');
		return self::$template;
	}
	
	public static function Trigger(){
		if(is_null(self::$trigger)) self::$trigger = new CAPIObject('trigger');
		return self::$trigger;
	}
	
	public static function User(){
		if(is_null(self::$user)) self::$user = new CAPIObject('user');
		return self::$user;
	}
	
	public static function UserGroup(){
		if(is_null(self::$usergroup)) self::$usergroup = new CAPIObject('usergroup');
		return self::$usergroup;
	}
	
	public static function UserMacro(){
		if(is_null(self::$usermacro)) self::$usermacro = new CAPIObject('usermacro');
		return self::$usermacro;
	}
	
}

?>
