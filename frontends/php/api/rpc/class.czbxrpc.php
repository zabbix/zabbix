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
		$process = true;

// List of methods without params
		$notifications = array(
			'host.delete_all'=>1	// example!
		);
		if(is_null($params) && !isset($notifications[$method])){
			self::$result = array('error'=>ZBX_API_ERROR_PARAMETERS);
			return self::$result;
		}
//-----

		list($resource, $action) = explode('.',$method);
// Authentication

		if(($resource == 'user') && ($action == 'authenticate')){
			$sessionid = null;
			
			$user = CUser::get(array('users' => $params['user'], 'extendoutput' => 1, 'get_access' => 1));
			$user = reset($user);
			if(!$user['api_access']){
				self::$result = array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'No API access');
				return self::$result;
			}
		}
		
		if(empty($sessionid) && (($resource != 'user') || ($action != 'authenticate'))){
			self::$result = array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'Not authorized');
			return self::$result;
		}
		else if(!empty($sessionid)){
			if(!CUser::checkAuth(array('sessionid' => $sessionid))){
				self::$result = array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'Not authorized');
				return self::$result;
			}
		}
		
		$class_name = 'C'.$resource; 
		if(!method_exists($class_name, $action)){
			self::$result = array('error' => ZBX_API_ERROR_PARAMETERS, 'data' => 'Action does not exists');
			return self::$result;
		}
		
		call_user_func(array('self', $resource), $action, $params);

	return self::$result;
	}

// USER
	private static function user($action, $params){
	
		CUser::$error = array();
		
		switch($action){
			default:
			$result = call_user_func(array('CUser', $action), $params);
		}
		if($result !== false)
			self::$result = array('result' => $result);
		else
			self::$result = CUser::$error;
	}

// HOST GROUP
	private static function hostgroup($action, $params){

		CHostGroup::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CHostGroup', $action), $params);
		}
		
		if($result !== false)
			self::$result = array('result' => $result);
		else
			self::$result = CUser::$error;
	}

// TEMPLATE
	private static function template($action, $params){

		CTemplate::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CTemplate', $action), $params);
		}
		
		if($result !== false)
			self::$result = array('result' => $result);
		else
			self::$result = CUser::$error;
	}

// HOST
	private static function host($action, $params){

		CHost::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CHost', $action), $params);
		}
		
		if($result !== false)
			self::$result = array('result' => $result);
		else
			self::$result = CUser::$error;
	}

// ITEM
	private static function item($action, $params){

		CItem::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CItem', $action), $params);
		}
		
		if($result !== false)
			self::$result = array('result' => $result);
		else
			self::$result = CUser::$error;
	}

// TRIGGER
	private static function trigger($action, $params){

		CTrigger::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CTrigger', $action), $params);
		}
		
		if($result !== false)
			self::$result = array('result' => $result);
		else
			self::$result = CUser::$error;
	}

// GRAPH
	private static function graph($action, $params){

		CGraph::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CGraph', $action), $params);
		}
		
		if($result !== false)
			self::$result = array('result' => $result);
		else
			self::$result = CUser::$error;
	}

}
?>
