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
							'host:delete_all'=>1	// example!
						);	
		if(is_null($params) && !isset($notifications[$method])){
			self::$result = array('error'=>'-32602');
			return self::$result;
		}
//-----

		list($resource, $action) = explode(':',$method);
		
// Authentication
		if(is_null($sessionid) && (($resource != 'user') || ($action != 'authenticate'))){
			self::$result = array('error'=>'-32602', 'data'=>'Not authorized');
		}
		else if(!self::auth($sessionid)){
			self::$result = array('error'=>'-32602', 'data'=>'Not authorized');
		}
		else{
			switch($resource){
				case 'user':
					self::user($action, $params);
				case 'hostgroup':
					self::hostgroup($action, $params);
					break;
				case 'template':
					self::template($action, $params);
					break;
				case 'host':
					self::host($action, $params);
					break;
				case 'item':
					self::item($action, $params);
					break;
				case 'trigger':
					self::trigger($action, $params);
					break;
				default:
					self::$result = array('error'=>'-32601');
			}
		}
		
		
	return self::$result;
	}
	
	private static function auth($sessionid){
		return check_authentication($sessionid);
	}
	
	private static function user($action, $params){
		switch($action){
			case 'authenticate':
				$login = user_login($params['user'], $params['password'], ZBX_AUTH_INTERNAL);

				if($login){
					self::$result = array('result' => $login);
				}
				else{
					self::$result = array('error' => '-32602', 'data' => 'Given login or password is incorrect.');
				}
				
				break;
		}	
	}
	
	private static function hostgroup($action, $params){
		switch($action){
			case 'add':
				self::$result = array('result' => 'CHostGroup::add($params);');
				break;
		}	
	}
		
	private static function template($action, $params){
		switch($action){
			case 'add':
				self::$result = array('result' => 'CTemplate::add($params);');
				break;
		}	
	}
	
	private static function host($action, $params){
		switch($action){
			case 'add':
				self::$result = array('result' => 'CHost::add($params);');
				break;
		}	
	}
	
	private static function item($action, $params){
		switch($action){
			case 'add':
				self::$result = array('result' => 'CItem::add($params);');
				break;
		}	
	}
	
	private static function trigger($action, $params){
		switch($action){
			case 'add':
				self::$result = array('result' => 'CTrigger::add($params);');
				break;
		}	
	}
}
?>