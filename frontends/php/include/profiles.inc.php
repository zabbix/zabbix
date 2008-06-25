<?php
/*
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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

//---------- GET USER VALUE -------------

function get_profile($idx,$default_value=null,$type=PROFILE_TYPE_UNKNOWN,$idx2=null,$source=null){
	global $USER_DETAILS;

	$result = $default_value;

	if($USER_DETAILS["alias"]!=ZBX_GUEST_USER){
		$sql_cond = '';
		if(profile_type($type,'id'))	$sql_cond.= ' AND '.DBin_node('value_id');
		if(zbx_numeric($idx2)) 			$sql_cond.= ' AND idx2='.$idx2.' AND '.DBin_node('idx2');
		if(!is_null($source)) 			$sql_cond.= ' AND source='.zbx_dbstr($source);
		
		$sql = 'SELECT value_id, value_int, value_str, type '.
				' FROM profiles '.
				' WHERE userid='.$USER_DETAILS["userid"].
					' AND idx='.zbx_dbstr($idx).
					$sql_cond.
				' ORDER BY profileid ASC';
		$db_profiles = DBselect($sql);

		if($profile=DBfetch($db_profiles)){
		
			if(profile_type($type,'unknown')) $type = $profile['type'];
			$value_type = profile_field_by_type($type);

			if(profile_type($type,'array')){
				$result[] = $profile[$value_type];
				while($profile=DBfetch($db_profiles)){
					$result[] = $profile[$value_type];
				}
			}
			else{
				$result = $profile[$value_type];
			}
		}
	}

return $result;
}


// multi value
function get_source_profile($idx,$default_value=array(),$type=PROFILE_TYPE_UNKNOWN,$idx2=null,$source=null){
	global $USER_DETAILS;

	$result = array();

	if($USER_DETAILS["alias"]!=ZBX_GUEST_USER){
		$sql_cond = '';
		if(profile_type($type,'id'))	$sql_cond.= ' AND '.DBin_node('value_id');
		if(zbx_numeric($idx2)) 			$sql_cond.= ' AND idx2='.$idx2.' AND '.DBin_node('idx2');
		if(!is_null($source)) 			$sql_cond.= ' AND source='.zbx_dbstr($source);
		
		$sql = 'SELECT value_id,value_int,value_str,source,type '.
				' FROM profiles '.
				' WHERE userid='.$USER_DETAILS["userid"].
					' AND idx='.zbx_dbstr($idx).
					$sql_cond.
				' ORDER BY profileid ASC';

		$db_profiles = DBselect($sql);
		if($profile=DBfetch($db_profiles)){
			if(profile_type($type,'unknown')) $type = $profile['type'];
			$value_type = profile_field_by_type($type);
			
			if(profile_type($type,'array')){
				$result[] = array('value'=>$profile[$value_type], 'source'=>$profile['source']);
				
				while($profile=DBfetch($db_profiles)){
					$result[] = array('value'=>$profile[$value_type], 'source'=>$profile['source']);
				}
			}
			else{
				$result = array('value'=>$profile[$value_type], 'source'=>$profile['source']);
			}

		}
	}
	$result = count($result)?$result:$default_value;
	
return $result;
}

//----------- ADD/EDIT USERPROFILE -------------
function update_profile($idx,$value,$type=PROFILE_TYPE_UNKNOWN,$idx2=null,$source=null){
	global $USER_DETAILS;
	if($USER_DETAILS["alias"]==ZBX_GUEST_USER) return false;

	if(profile_type($type,'unknown')) $type = profile_type_by_value($value);
	else $value = profile_value_by_type($value,$type);

	if($value === false) return false;

	$sql_cond = '';
	if(zbx_numeric($idx2)) 	$sql_cond = ' AND idx2='.$idx2.' AND '.DBin_node('idx2');

	DBstart();	
	if(profile_type($type,'array')){
		
		$sql='DELETE FROM profiles '.
			' WHERE userid='.$USER_DETAILS["userid"].
				' AND idx='.zbx_dbstr($idx).
				$sql_cond;

		DBexecute($sql);
		foreach($value as $id => $val){
			insert_profile($idx,$val,$type,$idx2,$source);
		}
	}
	else{
		$sql = 'SELECT profileid '.
				' FROM profiles '.
				' WHERE userid='.$USER_DETAILS['userid'].
					' AND idx='.zbx_dbstr($idx).
					$sql_cond;
					
		$row = DBfetch(DBselect($sql));

		if(!$row){
			insert_profile($idx,$value,$type,$idx2,$source);
		}
		else{
			$val = array();
			$value_type = profile_field_by_type($type);
			
			$val['value_id'] = 0;
			$val['value_int'] = 0;
			$val['value_str'] = '';
			
			$val[$value_type] = $value;	

			$idx2 = zbx_numeric($idx2)?$idx2:0;
			$src = is_null($source)?'':$source;

			if(is_array($value)){
				$val[$value_type] = isset($value['value'])?$value['value']:'';
				$src = isset($value['source'])?$value['source']:$src;
			}
			if(is_null($val[$value_type])) return false;

			$sql='UPDATE profiles '.
				' SET value_id='.$val['value_id'].','.
					' value_int='.$val['value_int'].','.
					' value_str='.zbx_dbstr($val['value_str']).','.
					' type='.$type.','.
					' source='.zbx_dbstr($src).
				' WHERE userid='.$USER_DETAILS["userid"].
					' AND idx='.zbx_dbstr($idx).
					$sql_cond;
					
			DBexecute($sql);
		}
	}
	
	$result = DBend();
	
return $result;
}


// Author: Aly
function insert_profile($idx,$value,$type,$idx2,$source){
	global $USER_DETAILS;

	$profileid = get_dbid('profiles', 'profileid');
	$value_type = profile_field_by_type($type);
	
	$val['value_id'] = 0;
	$val['value_int'] = 0;
	$val['value_str'] = '';
	
	$val[$value_type] = $value;	
	
	$idx2 = zbx_numeric($idx2)?$idx2:0;
	$src = is_null($source)?'':$source;

	if(is_array($value)){
		$val[$value_type] = isset($value['value'])?$value['value']:'';
		$src = isset($value['source'])?$value['source']:$src;
	}
	
	if(is_null($val[$value_type])) return false;
	
	$sql='INSERT INTO profiles (profileid,userid,idx,idx2,value_id,value_int,value_str,source,type)'.
		' VALUES ('.$profileid.','.
					$USER_DETAILS['userid'].','.
					zbx_dbstr($idx).','.
					$idx2.','.
					$val['value_id'].','.
					$val['value_int'].','.
					zbx_dbstr($val['value_str']).','.
					zbx_dbstr($src).','.
					$type.')';

	$result = DBexecute($sql);
	
return $result;
}

// ----------- MISC PROFILE FUNCTIONS -----------
function profile_type($type,$profile_type){
	$profile_type = strtolower($profile_type);
	switch($profile_type){
		case 'array':
			$result = uint_in_array($type,array(PROFILE_TYPE_ARRAY_ID,PROFILE_TYPE_ARRAY_INT,PROFILE_TYPE_ARRAY_STR));
			break;
		case 'id':
			$result = uint_in_array($type,array(PROFILE_TYPE_ID,PROFILE_TYPE_ARRAY_ID));
			break;
		case 'int':
			$result = uint_in_array($type,array(PROFILE_TYPE_INT,PROFILE_TYPE_ARRAY_INT));
			break;
		case 'str':
			$result = uint_in_array($type,array(PROFILE_TYPE_STR,PROFILE_TYPE_ARRAY_STR));
			break;
		case 'unknown':
			$result = ($type == PROFILE_TYPE_UNKNOWN);
			break;
		default:
			$result = false;
	}
return $result;
}

function profile_field_by_type($type){
	switch($type){	
		case PROFILE_TYPE_INT:
		case PROFILE_TYPE_ARRAY_INT:
			$field = 'value_int';
		break;
		case PROFILE_TYPE_STR:
		case PROFILE_TYPE_ARRAY_STR:
			$field = 'value_str';
		break;
		case PROFILE_TYPE_ID:
		case PROFILE_TYPE_ARRAY_ID:
		case PROFILE_TYPE_UNKNOWN:
		default:
			$field = 'value_id';
	}
return $field;
}

function profile_type_by_value($value,$type=PROFILE_TYPE_UNKNOWN){
	if(is_array($value)){
		$value = $value[0];
		
		if(is_array($value)){
			if(isset($value['value'])) 
				$type=zbx_numeric($value['value'])?PROFILE_TYPE_ARRAY_ID:PROFILE_TYPE_ARRAY_STR;
		}
		else{
			$type=zbx_numeric($value)?PROFILE_TYPE_ARRAY_ID:PROFILE_TYPE_ARRAY_STR;
		}
	}
	else{
		if(zbx_numeric($value)) $type = PROFILE_TYPE_ID;
		else $type = PROFILE_TYPE_STR;
	}
return $type;
}

function profile_value_by_type(&$value,$type){
	
	if(profile_type($type,'array')){
		$result = is_array($value)?$value:array($value);
	}
	else if(is_array($value)){
		if(!isset($value['value'])) return false;
		
		$result = $value;
		switch($type){	
			case PROFILE_TYPE_ID:
			case PROFILE_TYPE_INT:
				if(zbx_numeric($value['value'])){
					$result['value'] = intval($value['value']);
				}
				else{
					$result = false;
				}
			break;
			case PROFILE_TYPE_STR:
				$result['value'] = strval($value['value']);
			break;
			default:
				$result = false;
		}
	}
	else{			
		switch($type){	
			case PROFILE_TYPE_ID:
			case PROFILE_TYPE_INT:
				$result = zbx_numeric($value)?intval($value):false;
			break;
			case PROFILE_TYPE_STR:
				$result = strval($value);
			break;
			default:
				$result = false;
		}
	}
return $result;
}

/********** END MISC ***********/

/************ CONFIG **************/

function select_config(){
	$row=DBfetch(DBselect("select * from config where ".DBin_node("configid", get_current_nodeid(false))));
	if($row){
		return	$row;
	}
	else{
		error("Unable to select configuration");
	}
	return	$row;
}

function update_config($configs){
	$update = array();
	
	if(isset($configs['work_period']) && !is_null($configs['work_period'])){
		if(!validate_period($configs['work_period'])){
			error(S_ICORRECT_WORK_PERIOD);
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

return	DBexecute('update config set '.implode(',',$update).' where '.DBin_node('configid', get_current_nodeid(false)));
}
/************ END CONFIG **************/

/************ HISTORY **************/
// Author: Aly
function get_user_history(){
	$history=array();
	$delimiter = new CSpan('&raquo;','delimiter');
	for($i = 0; $i < ZBX_HISTORY_COUNT; $i++){
		if($rows = get_source_profile('web.history.'.$i,false)){
			if($i>0){
				array_push($history,$delimiter);
			}
			$url = new CLink($rows['source'],$rows['value'],'history');
			array_push($history,array(SPACE,$url,SPACE));
		}
	}
return $history;
}

function get_last_history_page($same_page=false){
	global $page;
	$title = explode('[',$page['title']);
	$title = $title[0];
	
	$rows=false;
	for($i = 0; $i < ZBX_HISTORY_COUNT; $i++){
		$new_rows = get_source_profile('web.history.'.$i,false);
		
		if(!$same_page && ($title == $new_rows['source'])) continue;
		$rows = $new_rows;
	}
	
	if(is_array($rows)){
		$rows['page'] = $rows['source'];
		$rows['url'] = $rows['value'];
	}
	
return $rows;
}

// Author: Aly
function add_user_history($page){

	$title = explode('[',$page['title']);
	$title = $title[0];

	if(!(isset($page['hist_arg']) && is_array($page['hist_arg']))){
		return FALSE;
	}
	
	$url = '';
	foreach($page['hist_arg'] as $key => $arg){
		if(isset($_REQUEST[$arg]) && !empty($_REQUEST[$arg])){
			$url.=((empty($url))?('?'):('&')).$arg.'='.$_REQUEST[$arg];
		}
	}
	$url = $page['file'].$url;


	$curr = 0;
	$profile = array();
	for($i = 0; $i < ZBX_HISTORY_COUNT; $i++){
		if($history = get_source_profile('web.history.'.$i,false)){
			if($history['source'] != $title){
				$profile[$curr] = $history;
				$curr++;
			}
		}
	}

	$history = array('source' => $title, 
					'value' => $url);
				
	if($curr < ZBX_HISTORY_COUNT){
		for($i = 0; $i < $curr; $i++){
			update_profile('web.history.'.$i,$profile[$i], PROFILE_TYPE_STR);
		}
		$result = update_profile('web.history.'.$curr,$history, PROFILE_TYPE_STR);
	} 
	else {
		for($i = 1; $i < ZBX_HISTORY_COUNT; $i++){
			update_profile('web.history.'.($i-1),$profile[$i], PROFILE_TYPE_STR);
		}
		$result = update_profile('web.history.'.(ZBX_HISTORY_COUNT-1),$history, PROFILE_TYPE_STR);
	}

return $result;
}
/********* END USER HISTORY **********/

/********** USER FAVORITES ***********/
// Author: Aly
function get_favorites($favobj,$nodeid=null){
	$fav = get_source_profile($favobj,array(),PROFILE_TYPE_ARRAY_ID);
return $fav;
}

// Author: Aly
function add2favorites($favobj,$favid,$source=null){
	$favorites = get_favorites($favobj,get_current_nodeid(true));

	$favorites[] = array('value' => $favid);
	
	$result = update_profile($favobj,$favorites,null,null,$source);
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

	$result = update_profile($favobj,$favorites);
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