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
			self::$result = array('error' => ZBX_API_ERROR_PARAMETERS);
			return self::$result;
		}

		list($resource, $action) = explode('.', $method);
		if(!isset($without_auth[$method])){
// Authentication {{{

// compatibility mode
			if(($resource == 'user') && ($action == 'authenticate')) $action = 'login';
//----------

			if(empty($sessionid) && (($resource != 'user') || ($action != 'login'))){
				self::$result = array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'Not authorized');
				return self::$result;
			}
			else if(!empty($sessionid)){
				if(!CUser::simpleAuth($sessionid)){
					self::$result = array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'Not authorized');
					return self::$result;
				}

				$options = array(
					'userids' => $USER_DETAILS['userid'],
					'output' => API_OUTPUT_EXTEND,
					'get_access' => 1
				);

				$users = CUser::get($options);
				$user = reset($users);
				if($user['api_access'] != GROUP_API_ACCESS_ENABLED){
					self::$result = array('error' => ZBX_API_ERROR_NO_AUTH, 'data' => 'No API access');
					return self::$result;
				}
			}
// }}} Authentication
		}
		unset($params['nopermissions']);

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
		if(self::$result !== false || $action == 'exists'){
			self::$result = array('result' => self::$result);
		}
		else{
			self::$result = reset(CZBXAPI::$error);
		}

	return self::$result;
	}

// APIINFO
	private static function apiinfo($action, $params){

		CAPIInfo::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CAPIInfo', $action), $params);
		}

		self::$result = $result;
	}

// ACTION
	private static function action($action, $params){

		CAction::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CAction', $action), $params);
		}

		self::$result = $result;
	}

// ALERT
	private static function alert($action, $params){

		CAlert::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CAlert', $action), $params);
		}

		self::$result = $result;
	}

// APPLICATION
	private static function application($action, $params){

		CApplication::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CApplication', $action), $params);
		}

		self::$result = $result;
	}

// DCheck
	private static function dcheck($action, $params){

		CDCheck::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CDCheck', $action), $params);
		}

		self::$result = $result;
	}

// DHost
	private static function dhost($action, $params){

		CDHost::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CDHost', $action), $params);
		}

		self::$result = $result;
	}

// DRule
	private static function drule($action, $params){

		CDRule::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CDRule', $action), $params);
		}

		self::$result = $result;
	}

// DService
	private static function dservice($action, $params){

		CDService::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CDService', $action), $params);
		}

		self::$result = $result;
	}

// EVENT
	private static function event($action, $params){

		CEvent::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CEvent', $action), $params);
		}

		self::$result = $result;
	}

// GRAPH
	private static function graph($action, $params){

		CGraph::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CGraph', $action), $params);
		}

		self::$result = $result;
	}

// GRAPHITEM
	private static function graphitem($action, $params){

		CGraphItem::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CGraphItem', $action), $params);
		}

		self::$result = $result;
	}


// HISTORY
	private static function history($action, $params){

		CHistory::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CHistory', $action), $params);
		}

		self::$result = $result;
	}

// HOST
	private static function host($action, $params){

		CHost::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CHost', $action), $params);
		}

		self::$result = $result;
	}

// HOST GROUP
	private static function hostgroup($action, $params){

		CHostGroup::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CHostGroup', $action), $params);
		}

		self::$result = $result;
	}

// IMAGE
	private static function image($action, $params){

		CImage::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CImage', $action), $params);
		}

		self::$result = $result;
	}

// ITEM
	private static function item($action, $params){

		CItem::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CItem', $action), $params);
		}

		self::$result = $result;
	}

// MAINTENANCE
	private static function maintenance($action, $params){

		CMaintenance::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CMaintenance', $action), $params);
		}

		self::$result = $result;
	}

// MAP
	private static function map($action, $params){

		CMap::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CMap', $action), $params);
		}

		self::$result = $result;
	}

// MEDIATYPE
	private static function mediatype($action, $params){

		CMediatype::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CMediatype', $action), $params);
		}

		self::$result = $result;
	}

// PROXY
	private static function proxy($action, $params){

		CProxy::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CProxy', $action), $params);
		}

		self::$result = $result;
	}

// SCREEN
	private static function screen($action, $params){

		CScreen::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CScreen', $action), $params);
		}

		self::$result = $result;
	}

// SCRIPT
	private static function script($action, $params){

		CScript::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CScript', $action), $params);
		}

		self::$result = $result;
	}

// TEMPLATE
	private static function template($action, $params){

		CTemplate::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CTemplate', $action), $params);
		}

		self::$result = $result;
	}


// TRIGGER
	private static function trigger($action, $params){

		CTrigger::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CTrigger', $action), $params);
		}

		self::$result = $result;
	}


// USER
	private static function user($action, $params){

		CUser::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CUser', $action), $params);
		}

		self::$result = $result;
	}

// USERGROUP
	private static function usergroup($action, $params){

		CUserGroup::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CUserGroup', $action), $params);
		}

		self::$result = $result;
	}

// USERMACRO
	private static function usermacro($action, $params){

		CUserMacro::$error = array();

		switch($action){
			default:
			$result = call_user_func(array('CUserMacro', $action), $params);
		}

		self::$result = $result;
	}
}
?>
