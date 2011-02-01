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
