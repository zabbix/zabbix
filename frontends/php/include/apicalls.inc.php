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

class APICaller{

	public static function call($class, $method, $params){
		global $USER_DETAILS

		return czbxrpc::call($class.$method, $params);
	}

	public static function result($result){

	}
}


class CAction{

	public static function get($options=array()){

	}

	public static function add($actions){

	}

	public static function update($actions){

	}

	public static function addConditions($conditions){

	}

	public static function addOperations($operations){

	}

	public static function delete($actions){

	}
}

class CAlert{

	public static function get($options=array()){

	}

	public static function add($alerts){

	}

	public static function delete($alertids){

	}
}

class CApplication extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function getObjects($app_data){

	}

	public static function add($applications){

	}

	public static function update($applications){

	}

	public static function delete($applications){

	}

	public static function addItems($data){

	}
}

class CEvent extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function add($events){

	}

	public static function delete($events){

	}

	public static function deleteByTriggerIDs($triggerids){

	}

	public static function acknowledge($events_data){

	}
}

class CGraph extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function getObjects($graph_data){

	}

	public static function add($graphs){

	}

	public static function update($graphs){

	}

	public static function delete($graphs){

	}

	public static function addItems($items){

	}

	protected static function addItems_rec($graphid, $items, $tpl_graph=false){


	}

	public static function deleteItems($item_list, $force=false){

	}
}

class CGraphItem extends CZBXAPI{

	public static function get($options = array()){

	}

	public static function getObjects($gitem_data){
	}
}


class CHost extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function getObjects($host_data){

	}

	public static function create($hosts){

	}

	public static function update($hosts){

	}

	public static function massUpdate($data){

	}

	public static function massAdd($data){

	}

	public static function massRemove($data){

	}

	public static function delete($hosts){

	}
}

class CHostGroup extends CZBXAPI{

	public static function get($params){

	}

	public static function getObjects($data){

	}

	public static function create($groups){

	}

	public static function update($groups){

	}

	public static function delete($groups){

	}

	public static function massAdd($data){

	}

	public static function massRemove($data){

	}

	public static function massUpdate($data){

	}
}

class CItem extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function getObjects($item_data){

	}

	public static function add($items){

	}

	public static function update($items){

	}

	public static function delete($items){

	}
}

class CMaintenance extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function getObjects($maintenance){

	}

	public static function add($maintenances){

	}

	public static function update($maintenances){

	}

	public static function delete($maintenances){

	}

}

class CMap extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function add($maps){

	}

	public static function update($maps){

	}

	public static function delete($sysmaps){

	}

	public static function addLinks($links){

	}

	public static function addElements($selements){

	}

	public static function addLinkTrigger($linktriggers){

	}

}

class CScreen extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function add($screens){

	}

	public static function update($screens){

	}

	public static function delete($screens){

	}

	public static function addItems($data){

	}

	public static function deleteItems($data){

	}
}

class Cscript extends CZBXAPI{

	public static function get($options = array()){

	}

	public static function getObjects($script){

	}

	public static function add($scripts){

	}

	public static function update($scripts){

	}

	public static function delete($scripts){

	}

	public static function execute($scriptid,$hostid){

	}

	public static function getCommand($scriptid,$hostid){

	}

	public static function getScriptsByHosts($hostids){

	}
}

class CTemplate extends CZBXAPI{
	public static function get($options = array()) {

	}

	public static function getObjects($template_data){

	}

	public static function create($templates){

	}

	public static function update($templates){

	}

	private static function checkCircularLink($id, $templateids){

	}

	public static function delete($templates){

	}

	public static function massUpdate($data){

	}

	public static function massAdd($data){

	}

	public static function massRemove($data){

	}

	public static function linkTemplates($data){

	}

	private static function link($templateids, $targetids){

	}
}


class CTrigger extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function getObjects($trigger){

	}

	public static function add($triggers){

	}

	public static function update($triggers){

	}

	public static function delete($triggers){

	}

	public static function addDependencies($triggers_data){

	}

	public static function deleteDependencies($triggers){

	}
}

class CUser extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function authenticate($user){

	}

	public static function checkAuth($session){

	}

	public static function getObjects($user_data){

	}

	public static function add($users){

	}

	public static function update($users){

	}

	public static function updateProfile($user){

	}

	public static function delete($users){

	}

	public static function addMedia($media_data){

	}

	public static function deleteMedia($media_data){

	}

	public static function updateMedia($data){

	}
}

class CUserGroup extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function getObjects($group_data){

	}

	public static function add($usrgrps){

	}

	public static function update($usrgrps){

	}

	public static function updateRights($rights){

	}

	public static function addRights($rights){

	}

	public static function updateUsers($data){

	}

	public static function removeUsers($data){

	}

	public static function delete($usrgrps){

	}
}

class CUserMacro extends CZBXAPI{

	public static function get($options=array()){

	}

	public static function getHostMacroObjects($macro_data){

	}

	public static function add($macros){

	}

	public static function update($macros){

	}

	public static function updateValue($macros){

	}

	public static function deleteHostMacro($hostmacros){

	}

	public static function createGlobal($macros){

	}

	public static function deleteGlobalMacro($globalmacros){

	}

	public static function validate($macros){

	}

	public static function getGlobalMacroObjects($macro_data){

	}

	public static function getHostMacroId($macro_data){

	}

	public static function massAdd($data){

	}

	public static function massRemove($data){

	}

	public static function getGlobalMacroId($macro_data){

	}

	public static function getMacros($macros, $options){

	}

	public static function resolveTrigger(&$triggers){

	}

	public static function resolveItem(&$items){

	}
}

?>
