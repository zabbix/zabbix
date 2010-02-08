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
/********** USER PROFILE ***********/

class CProfile{

	private static $profiles = array();
	private static $update = array();
	private static $insert = array();
	
	public static function init(){
		global $USER_DETAILS;
		
		self::$profiles = array();
		
		$sql = 'SELECT * '.
				' FROM profiles '.
				' WHERE userid='.$USER_DETAILS['userid'].
					' AND '.DBin_node('profileid', false).
				' ORDER BY userid ASC, profileid ASC';
		$db_profiles = DBselect($sql);
		while($profile = DBfetch($db_profiles)){
			$value_type = self::getFieldByType($profile['type']);
			
			self::$profiles[$profile['idx']] = $profile[$value_type];
		}
	}
	
	public static function flush(){
		foreach(self::$insert as $profile){
			$result = self::insertDB($profile);
		}
		
		foreach(self::$update as $profile){			
			self::updateDB($profile);
		}		
	}
	
	public static function clear(){
		self::$insert= array();
		self::$update= array();
	}
	
	public static function get($idx, $default_value=null){
		if(is_null(self::$profiles)){	
			self::init();
		}
		
		if(isset(self::$profiles[$idx]))
			return self::$profiles[$idx];
		else
			return $default_value;
	}
		
	public static function update($idx, $value, $type){
		global $USER_DETAILS;
		
		if($USER_DETAILS['alias'] == ZBX_GUEST_USER) return false;		
		if(!self::checkValueType($value, $type)) return false;

		
		$profile = array(
			'idx' => $idx,
			'value' => $value,
			'type' => $type,
		);
		if(get_profile($idx, false) === false){
			self::$insert[] = $profile;
		}
		else{
			self::$update[] = $profile;
		}
		
		self::$profiles[$profile['idx']] = $profile[$value_type];
	}
	
	private static function insertDB($idx, $value, $type){
		$value_type = self::getFieldByType($type);
		
		$values = array(
			'profileid' => get_dbid('profiles', 'profileid'),
			'userid' => $USER_DETAILS['userid'],
			'idx' => zbx_dbstr($idx),
			$value_type => ($value_type == 'value_str') ? zbx_dbstr($value) : $value,
			'type' => $type
		);
		$sql = 'INSERT INTO profiles ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
		return DBexecute($sql);
	}
		
	private static function updateDB($idx, $value, $type){
		global $USER_DETAILS;
		
		$sql_cond = '';
// dirty fix, but havn't figureout something better
		if($idx != 'web.nodes.switch_node') $sql_cond.= ' AND '.DBin_node('profileid', false);
// ---
		
		$value_type = self::getFieldByType($type);
		$value = ($value_type == 'value_str') ? zbx_dbstr($value) : $value;

		$sql='UPDATE profiles SET '.
				$value_type.'='.$value.','.
				' type='.$type.','.
			' WHERE userid='.$USER_DETAILS['userid'].
				' AND idx='.zbx_dbstr($idx).
				$sql_cond;
		$result = DBexecute($sql);
	}
		
	private static function getFieldByType($type){
		switch($type){
			case PROFILE_TYPE_INT:
				$field = 'value_int';
			break;
			case PROFILE_TYPE_STR:
				$field = 'value_str';
			break;
			case PROFILE_TYPE_ID:
			default:
				$field = 'value_id';
		}
		return $field;
	}
		
	private static function checkValueType($value, $type){
		switch($type){
			case PROFILE_TYPE_ID:
				$result = zbx_ctype_digit($value);
				break;
			case PROFILE_TYPE_INT:
				$result = zbx_numeric($value);
				break;
			default:
				$result = true;
		}
		
		return $result;
	}
}

/************ CONFIG **************/

function select_config($cache = true){
	global $page;
	static $config;

	if($cache && isset($config)) return $config;

	$row = DBfetch(DBselect('SELECT * FROM config WHERE '.DBin_node('configid', get_current_nodeid(false))));

	if($row){
		$config = $row;
		return $row;
	}
	elseif($page['title'] != S_INSTALLATION){
		error(S_UNABLE_TO_SELECT_CONFIGURATION);
	}
return $row;
}

function update_config($configs){
	$update = array();

	if(isset($configs['work_period']) && !is_null($configs['work_period'])){
		if(!validate_period($configs['work_period'])){
			error(S_INCORRECT_WORK_PERIOD);
			return NULL;
		}
	}
	if(isset($configs['alert_usrgrpid']) && !is_null($configs['alert_usrgrpid'])){
		if(($configs['alert_usrgrpid'] != 0) && !DBfetch(DBselect('select usrgrpid from usrgrp where usrgrpid='.$configs['alert_usrgrpid']))){
			error(S_INCORRECT_GROUP);;
			return NULL;
		}
	}

	foreach($configs as $key => $value){
		if(!is_null($value))
			$update[] = $key.'='.zbx_dbstr($value);
	}

	if(count($update) == 0){
		error(S_NOTHING_TO_DO);
		return NULL;
	}

return	DBexecute('update config set '.implode(',',$update).' where '.DBin_node('configid', false));
}
/************ END CONFIG **************/

/************ HISTORY **************/
function get_user_history(){
	global $USER_DETAILS;

	$result = array();
	$delimiter = new CSpan('&raquo;','delimiter');

	$sql = 'SELECT title1, url1, title2, url2, title3, url3, title4, url4, title5, url5
			FROM user_history WHERE userid='.$USER_DETAILS['userid'];
	$history = DBfetch(DBSelect($sql));

	if($history)
		$USER_DETAILS['last_page'] = array('title' => $history['title4'], 'url' => $history['url4']);
	else
		$USER_DETAILS['last_page'] = false;

	for($i = 1; $i<6; $i++){
		if(defined($history['title'.$i])){
			$url = new CLink(constant($history['title'.$i]), $history['url'.$i], 'history');
			array_push($result, array(SPACE, $url, SPACE));
			array_push($result, $delimiter);
		}
	}
	array_pop($result);

	return $result;
}

function add_user_history($page){
	global $USER_DETAILS;

	$userid = $USER_DETAILS['userid'];
	$title = $page['title'];

	if(isset($page['hist_arg']) && is_array($page['hist_arg'])){
		$url = '';
		foreach($page['hist_arg'] as $arg){
			if(isset($_REQUEST[$arg])){
				$url .= ((empty($url))? '?' : '&').$arg.'='.$_REQUEST[$arg];
			}
		}
		$url = $page['file'].$url;
	}
	else{
		$url = $page['file'];
	}

	$sql = 'SELECT title5, url5
			FROM user_history WHERE userid='.$userid;
	$history5 = DBfetch(DBSelect($sql));

	if($history5 && ($history5['title5'] == $title)){ //title is same
		if($history5['url5'] != $url){ // title same, url isnt, change only url
			$sql = 'UPDATE user_history '.
					' SET url5='.zbx_dbstr($url).
					' WHERE userid='.$userid;
		}
		else
			return; // no need to change anything;
	}
	else{ // new page with new title is added
		if(!$USER_DETAILS['last_page']){
			$userhistoryid = get_dbid('user_history', 'userhistoryid');
			$sql = 'INSERT INTO user_history (userhistoryid, userid, title5, url5)'.
					' VALUES('.$userhistoryid.', '.$userid.', '.zbx_dbstr($title).', '.zbx_dbstr($url).')';
		}
		else{
			$sql = 'UPDATE user_history '.
					' SET title1=title2, '.
						' url1=url2, '.
						' title2=title3, '.
						' url2=url3, '.
						' title3=title4, '.
						' url3=url4, '.
						' title4=title5, '.
						' url4=url5, '.
						' title5='.zbx_dbstr($title).', '.
						' url5='.zbx_dbstr($url).
					' WHERE userid='.$userid;
		}
	}
	$result = DBexecute($sql);

return $result;
}
/********* END USER HISTORY **********/

/********** USER FAVORITES ***********/
// Author: Aly
function get_favorites($idx,$nodeid=null){
	global $USER_DETAILS;

	$result = array();
	if($USER_DETAILS['alias']!=ZBX_GUEST_USER){
		$sql = 'SELECT value_id,value_int,value_str,source,type '.
				' FROM profiles '.
				' WHERE userid='.$USER_DETAILS['userid'].
					' AND idx='.zbx_dbstr($idx).
				' ORDER BY profileid ASC';
		$db_profiles = DBselect($sql);
		if($profile=DBfetch($db_profiles)){
			$value_type = self::getFieldByType($profile['type']);

			$result[] = array('value'=>$profile[$value_type], 'source'=>$profile['source']);
			while($profile=DBfetch($db_profiles)){
				$result[] = array('value'=>$profile[$value_type], 'source'=>$profile['source']);
			}
		}
	}

	$result = count($result)?$result:array();

return $result;
}

// Author: Aly
function add2favorites($favobj,$favid,$source=null){
	$favorites = get_favorites($favobj,get_current_nodeid(true));

	foreach($favorites as $id => $favorite){
		if(($favorite['source'] == $source) && ($favorite['value'] == $favid)){
			return true;
		}
	}

	$favorites[] = array('value' => $favid);

	$result = update_profile($favobj,$favorites,PROFILE_TYPE_ARRAY_ID,null,$source);
return $result;
}

// Author: Aly
function rm4favorites($favobj,$favid,$favcnt=null,$source=null){
	$favorites = get_favorites($favobj,get_current_nodeid(true));

	$favcnt = (is_null($favcnt))?0:$favcnt;
	if($favid == 0) $favcnt = ZBX_FAVORITES_ALL;

	foreach($favorites as $key => $favorite){
		if(((bccomp($favid,$favorite['value']) == 0) || ($favid == 0)) && ($favorite['source'] == $source)){
			if($favcnt < 1){
				unset($favorites[$key]);
				if($favcnt > ZBX_FAVORITES_ALL) break;  // foreach
			}
		}
		$favcnt--;
	}

	$result = update_profile($favobj,$favorites,PROFILE_TYPE_ARRAY_ID);
return $result;
}

// Author: Aly
function infavorites($favobj,$favid,$source=null){

	$favorites = get_favorites($favobj);
	if(!empty($favorites)){
		foreach($favorites as $id => $favorite){
			if(bccomp($favid,$favorite['value']) == 0){
				if(is_null($source) || ($favorite['source'] == $source))
					return true;
			}
		}
	}
return false;
}
/********** END USER FAVORITES ***********/
?>
