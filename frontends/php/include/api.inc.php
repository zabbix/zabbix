<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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

class CAPIObject{
	private $_name;

	public function __construct($name){
		$this->_name = $name;
	}

	public function __call($method, $params){
		global $USER_DETAILS;

		$result = czbxrpc::call($this->_name.'.'.$method, $params[0], $USER_DETAILS['sessionid']);
		if(isset($result['result'])){
			return $result['result'];
		}
		else{
			error($result['data']);
			error($result['trace']);
			return false;
		}
	}
}

class API{
	const RETURN_TYPE_API = 'api';
	const RETURN_TYPE_RPC = 'rpc';

	private static $APIobjects = array();
	private static $RPCobjects = array();
	private static $return = self::RETURN_TYPE_RPC;


	public static function setReturnAPI(){
		self::$return = self::RETURN_TYPE_API;
	}

	public static function setReturnRPC(){
		self::$return = self::RETURN_TYPE_RPC;
	}

	private static function getAPIObject($className){
		if(!isset(self::$APIobjects[$className])) self::$APIobjects[$className] = new CAPIObject($className);
			return self::$APIobjects[$className];
	}

	private static function getRPCObject($className){
		if(!isset(self::$RPCobjects[$className])) self::$RPCobjects[$className] = new CAPIObject($className);
			return self::$RPCobjects[$className];
	}

	public static function getObject($className){
		return self::$return == self::RETURN_TYPE_API ? self::getAPIObject($className) : self::getAPIObject($className);
	}

	public static function Action(){
		return self::getObject('action');
	}

	public static function Alert(){
		return self::getObject('alert');
	}

	public static function Application(){
		return self::getObject('application');
	}

	public static function Event(){
		return self::getObject('event');
	}

	public static function Graph(){
		return self::getObject('graph');
	}

	public static function GraphItem(){
		return self::getObject('graphitem');
	}

	public static function Host(){
		return self::getObject('host');
	}

	public static function HostGroup(){
		return self::getObject('hostgroup');
	}

	public static function Item(){
		return self::getObject('item');
	}

	public static function Maintenance(){
		return self::getObject('maintenance');
	}

	public static function Map(){
		return self::getObject('map');
	}

	public static function Screen(){
		return self::getObject('screen');
	}

	public static function Script(){
		return self::getObject('script');
	}

	public static function Template(){
		return self::getObject('template');
	}

	public static function Trigger(){
		return self::getObject('trigger');
	}

	public static function User(){
		return self::getObject('user');
	}

	public static function UserGroup(){
		return self::getObject('usergroup');
	}

	public static function UserMacro(){
		return self::getObject('usermacro');
	}

}

?>
