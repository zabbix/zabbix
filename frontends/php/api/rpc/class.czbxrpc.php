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

		if(is_null($sessionid)){
			if(($resource != 'user') || ($action != 'authenticate')){
				self::$result = array('error'=>ZBX_API_ERROR_NO_AUTH, 'data'=>'Not authorized');
				return self::$result;
			}
			else if(!CUser::apiAccess($params)){
				self::$result = array('error'=>ZBX_API_ERROR_NO_AUTH, 'data'=>'Not authorized');
				return self::$result;
			}
		}
		else if(!CUser::checkAuth(array('sessionid' => $sessionid))){
			self::$result = array('error'=>ZBX_API_ERROR_NO_AUTH, 'data'=>'Not authorized');
			return self::$result;
		}

		switch($resource){
			case 'user':
				self::user($action, $params);
				break;
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
			case 'graph':
				self::graph($action, $params);
				break;
			default:
				self::$result = array('error'=>ZBX_API_ERROR_PARAMETERS);
			break;
		}

	return self::$result;
	}

// USER
	private static function user($action, $params){
		switch($action){
			case 'authenticate':
				$result = CUser::authenticate($params);

				break;
			case 'apiAccess':
				$result = CUser::apiAccess($params);
				break;
			default:
				$result = array('error' => ZBX_API_ERROR_NO_METHOD, 'data' => 'Method: "'.$action.'" doesn\'t exist.');
				return; //exit function
			break;
		}

		if($result !== false){
			self::$result = array('result' => $result);
		}
		else{
			self::$result = CUser::$error;
		}
	}

// HOST GROUP
	private static function hostgroup($action, $params){

		CHostGroup::$error = array();

		switch($action){
			case 'add':
				$result = CHostGroup::add($params);
				break;
			case 'get':
				$result = CHostGroup::get($params);
				break;
			case 'getById':
				$result = CHostGroup::getById($params);
				break;
			case 'getId':
				$result = CHostGroup::getId($params);
				break;
			case 'update':
				$result = CHostGroup::update($params);
				break;
			case 'addHosts':
				$result = CHostGroup::addHosts($params);
				break;
			case 'removeHosts':
				$result = CHostGroup::removeHosts($params);
				break;
			case 'addGroupsToHost':
				$result = CHostGroup::addGroupsToHost($params);
				break;
			case 'updateGroupsToHost':
				$result = CHostGroup::updateGroupsToHost($params);
				break;
			case 'delete':
				$result = CHostGroup::delete($params);
				break;
			default:
				self::$result = array('error' => ZBX_API_ERROR_NO_METHOD, 'data' => 'Method: "'.$action.'" doesn\'t exist.');
				return; //exit function
		}

		if($result !== false){
			self::$result = array('result' => $result);
		}
		else{
			self::$result = CHostGroup::$error;
		}
	}

// TEMPLATE
	private static function template($action, $params){

		CTemplate::$error = array();

		switch($action){
			case 'add':
				$result = CTemplate::add($params);
				break;
			case 'get':
				$result = CTemplate::get($params);
				break;
			case 'getById':
				$result = CTemplate::getById($params);
				break;
			case 'getId':
				$result = CTemplate::getId($params);
				break;
			case 'update':
				$result = CTemplate::update($params);
				break;
			case 'linkHosts':
				$result = CTemplate::linkHosts($params);
				break;
			case 'unlinkHosts':
				$result = CTemplate::unlinkHosts($params);
				break;
			case 'linkTemplates':
				$result = CTemplate::linkTemplates($params);
				break;
			case 'unlinkTemplates':
				$result = CTemplate::unlinkTemplates($params);
				break;
			case 'delete':
				$result = CTemplate::delete($params);
				break;
			default:
				self::$result = array('error' => ZBX_API_ERROR_NO_METHOD, 'data' => 'Method: "'.$action.'" doesn\'t exist.');
				return; //exit function
		}

		if($result !== false){
			self::$result = array('result' => $result);
		}
		else{
			self::$result = CTemplate::$error;
		}
	}

// HOST
	private static function host($action, $params){

		CHost::$error = array();

		switch($action){
			case 'add':
				$result = CHost::add($params);
				break;
			case 'get':
				$result = CHost::get($params);
				break;
			case 'getById':
				$result = CHost::getById($params);
				break;
			case 'getId':
				$result = CHost::getId($params);
				break;
			case 'update':
				$result = CHost::update($params);
				break;
			case 'massUpdate':
				$result = CHost::massUpdate($params);
				break;
			case 'delete':
				$result = CHost::delete($params);
				break;
			default:
				self::$result = array('error' => ZBX_API_ERROR_NO_METHOD, 'data' => 'Method: "'.$action.'" doesn\'t exist.');
				return; //exit function
		}

		if($result !== false){
			self::$result = array('result' => $result);
		}
		else{
			self::$result = CHost::$error;
		}
	}

// ITEM
	private static function item($action, $params){

		CItem::$error = array();

		switch($action){
			case 'add':
				$result = CItem::add($params);
				break;
			case 'get':
				$result = CItem::get($params);
				break;
			case 'getById':
				$result = CItem::getById($params);
				break;
			case 'getId':
				$result = CItem::getId($params);
				break;
			case 'update':
				$result = CItem::update($params);
				break;
			case 'delete':
				$result = CItem::delete($params);
				break;
			default:
				self::$result = array('error' => ZBX_API_ERROR_NO_METHOD, 'data' => 'Method: "'.$action.'" doesn\'t exist.');
				return; //exit function
		}

		if($result !== false){
			self::$result = array('result' => $result);
		}
		else{
			self::$result = CItem::$error;
		}
	}

// TRIGGER
	private static function trigger($action, $params){

		CTrigger::$error = array();

		switch($action){
			case 'add':
				$result = CTrigger::add($params);
				break;
			case 'get':
				$result = CTrigger::get($params);
				break;
			case 'getById':
				$result = CTrigger::getById($params);
				break;
			case 'getId':
				$result = CTrigger::getId($params);
				break;
			case 'update':
				$result = CTrigger::update($params);
				break;
			case 'delete':
				$result = CTrigger::delete($params);
				break;
			default:
				self::$result = array('error' => ZBX_API_ERROR_NO_METHOD, 'data' => 'Method: "'.$action.'" doesn\'t exist.');
				return; //exit function
		}

		if($result !== false){
			self::$result = array('result' => $result);
		}
		else{
			self::$result = array_shift(CTrigger::$error);
		}
	}

// GRAPH
	private static function graph($action, $params){

		CGraph::$error = array();

		switch($action){
			case 'add':
				$result = CGraph::add($params);
				break;
			case 'get':
				$result = CGraph::get($params);
				break;
			case 'getById':
				$result = CGraph::getById($params);
				break;
			case 'getId':
				$result = CGraph::getId($params);
				break;
			case 'update':
				$result = CGraph::update($params);
				break;
			case 'addItems':
				$result = CGraph::addItems($params);
				break;
			case 'deleteItems':
				$result = CGraph::deleteItems($params);
				break;
			case 'delete':
				$result = CGraph::delete($params);
				break;
			default:
				self::$result = array('error' => ZBX_API_ERROR_NO_METHOD, 'data' => 'Method: "'.$action.'" doesn\'t exist.');
				return; //exit function
		}

		if($result !== false){
			self::$result = array('result' => $result);
		}
		else{
			self::$result = CGraph::$error;
		}
	}
}
?>
