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

	private static $profiles = null;
	private static $update = array();
	private static $insert = array();
	private static $stringProfileMaxLength;

	public static function init(){
		global $USER_DETAILS;

		self::$profiles = array();

		$profilesTableSchema = DB::getSchema('profiles');
		self::$stringProfileMaxLength = $profilesTableSchema['fields']['value_str']['length'];

		$sql = 'SELECT * '.
				' FROM profiles '.
				' WHERE userid='.$USER_DETAILS['userid'].
					' AND '.DBin_node('profileid', false).
				' ORDER BY userid ASC, profileid ASC';
		$db_profiles = DBselect($sql);
		while($profile = DBfetch($db_profiles)){
			$value_type = self::getFieldByType($profile['type']);

			if(!isset(self::$profiles[$profile['idx']]))
				self::$profiles[$profile['idx']] = array();

			self::$profiles[$profile['idx']][$profile['idx2']] = $profile[$value_type];
		}
	}

	public static function flush(){

		if(!empty(self::$insert) || !empty(self::$update)){

			DBstart();
			foreach(self::$insert as $idx => $profile){
				foreach($profile as $idx2 => $data){
					self::insertDB($idx, $data['value'], $data['type'], $idx2);
				}
			}

			ksort(self::$update);
			foreach(self::$update as $idx => $profile){
				ksort($profile);
				foreach($profile as $idx2 => $data){
					self::updateDB($idx, $data['value'], $data['type'], $idx2);
				}
			}
			DBend();
		}
	}

	public static function clear(){
		self::$insert= array();
		self::$update= array();
	}

	public static function get($idx, $default_value=null, $idx2=0){
		if(is_null(self::$profiles)){
			self::init();
		}

		if(isset(self::$profiles[$idx][$idx2]))
			return self::$profiles[$idx][$idx2];
		else
			return $default_value;
	}

	public static function update($idx, $value, $type, $idx2=0){
		if(is_null(self::$profiles)){
			self::init();
		}

		if(!self::checkValueType($value, $type)) return false;

		$profile = array(
			'idx' => $idx,
			'value' => $value,
			'type' => $type,
			'idx2' => $idx2,
		);

		$current = CProfile::get($idx, null, $idx2);
		if(is_null($current)){
			if(!isset(self::$insert[$idx])) self::$insert[$idx] = array();

			self::$insert[$idx][$idx2] = $profile;
		}
		else{
			if($current != $value){
				if(!isset(self::$update[$idx]))
					self::$update[$idx] = array();

				self::$update[$idx][$idx2] = $profile;
			}
		}

		if(!isset(self::$profiles[$idx])) self::$profiles[$idx] = array();

		self::$profiles[$idx][$idx2] = $value;
	}

	private static function insertDB($idx, $value, $type, $idx2){
		global $USER_DETAILS;

		$value_type = self::getFieldByType($type);

		$values = array(
			'profileid' => get_dbid('profiles', 'profileid'),
			'userid' => $USER_DETAILS['userid'],
			'idx' => zbx_dbstr($idx),
			$value_type => ($value_type == 'value_str') ? zbx_dbstr($value) : $value,
			'type' => $type,
			'idx2' => $idx2
		);

		$sql = 'INSERT INTO profiles ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';

	return DBexecute($sql);
	}

	private static function updateDB($idx, $value, $type, $idx2){
		global $USER_DETAILS;

		$sql_cond = '';
// dirty fix, but havn't figureout something better
		if($idx != 'web.nodes.switch_node') $sql_cond .= ' AND '.DBin_node('profileid', false);
// ---
		if($idx2 > 0) $sql_cond.= ' AND idx2='.$idx2.' AND '.DBin_node('idx2', false);

		$value_type = self::getFieldByType($type);
		$value = ($value_type == 'value_str') ? zbx_dbstr($value) : $value;

		$sql = 'UPDATE profiles SET '.
					$value_type.'='.$value.','.
					' type='.$type.
				' WHERE userid='.$USER_DETAILS['userid'].
					' AND idx='.zbx_dbstr($idx).
					$sql_cond;

		$result = DBexecute($sql);

	return $result;
	}

	public static function getFieldByType($type){
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
				$result = zbx_is_int($value);
				break;
			case PROFILE_TYPE_STR:
				$result = zbx_strlen($value) <= self::$stringProfileMaxLength;
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
	else if(isset($page['title']) && ($page['title'] != "S_INSTALLATION")){
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

	if($history && !zbx_empty($history['url4']))
		$USER_DETAILS['last_page'] = array('title' => $history['title4'], 'url' => $history['url4']);
	else
		$USER_DETAILS['last_page'] = array('title' => S_DASHBOARD, 'url' => 'dashboard.php');

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
				$url .= url_param($arg, true);
			}
		}
		if(!empty($url)) $url[0] = '?';
		$url = $page['file'].$url;
	}
	else{
		$url = $page['file'];
	}

	// if url length is greater than db field size, skip history update
	$historyTableSchema = DB::getSchema('user_history');
	if (zbx_strlen($url) > $historyTableSchema['fields']['url5']['length']) {
		return false;
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
			return true; // no need to change anything;
	}
	else{ // new page with new title is added
		if($history5 === false){
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
function get_favorites($idx){
	global $USER_DETAILS;

	$result = array();

	$sql = 'SELECT value_id, source '.
			' FROM profiles '.
			' WHERE userid='.$USER_DETAILS['userid'].
				' AND idx='.zbx_dbstr($idx).
			' ORDER BY profileid ASC';
	$db_profiles = DBselect($sql);
	while($profile = DBfetch($db_profiles)){
		$result[] = array('value' => $profile['value_id'], 'source' => $profile['source']);
	}

	return $result;
}

// Author: Aly
function add2favorites($favobj, $favid, $source=null){
	global $USER_DETAILS;

	$favorites = get_favorites($favobj);

	foreach($favorites as $id => $favorite){
		if(($favorite['source'] == $source) && ($favorite['value'] == $favid)){
			return true;
		}
	}

	DBstart();
	$values = array(
		'profileid' => get_dbid('profiles', 'profileid'),
		'userid' => $USER_DETAILS['userid'],
		'idx' => zbx_dbstr($favobj),
		'value_id' =>  $favid,
		'type' => PROFILE_TYPE_ID
	);
	if(!is_null($source)) $values['source'] = zbx_dbstr($source);

	$sql = 'INSERT INTO profiles ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
	$result = DBexecute($sql);

	$result = DBend($result);

return $result;
}

// Author: Aly
function rm4favorites($favobj, $favid=0, $source=null){
	global $USER_DETAILS;

	$sql = 'DELETE FROM profiles '.
		' WHERE userid='.$USER_DETAILS['userid'].
			' AND idx='.zbx_dbstr($favobj).
			(($favid > 0) ? ' AND value_id='.$favid : '').
			(is_null($source) ? '' : ' AND source='.zbx_dbstr($source));

	return DBexecute($sql);
}

// Author: Aly
function infavorites($favobj, $favid, $source=null){

	$favorites = get_favorites($favobj);
	foreach($favorites as $id => $favorite){
		if(bccomp($favid, $favorite['value']) == 0){
			if(is_null($source) || ($favorite['source'] == $source))
				return true;
		}
	}

	return false;
}
/********** END USER FAVORITES ***********/
?>
