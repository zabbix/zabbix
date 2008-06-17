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
function get_profile($idx,$default_value=null,$type=PROFILE_TYPE_UNKNOWN,$source=null){
	global $USER_DETAILS;

	$result = $default_value;

	if($USER_DETAILS["alias"]!=ZBX_GUEST_USER){
		$sql = 'SELECT value, valuetype '.
				' FROM profiles '.
				' WHERE userid='.$USER_DETAILS["userid"].
					' AND idx='.zbx_dbstr($idx).
					(is_null($source)?'':' AND source='.zbx_dbstr($source)).
				' ORDER BY profileid ASC';
		$db_profiles = DBselect($sql);

		if($profile=DBfetch($db_profiles)){
		
			if(PROFILE_TYPE_UNKNOWN == $type) $type = $profile["valuetype"];
	
			if(PROFILE_TYPE_ARRAY == $type){
				$result[] = $profile['value'];
				while($profile=DBfetch($db_profiles)){
					$result[] = $profile['value'];
				}
			}
			else{
				$result = strval($profile["value"]);
			}
		}
	}

return $result;
}


// multi value
function get_multi_profile($idx,$default_value=array(),$type=PROFILE_TYPE_UNKNOWN,$source=null){
	global $USER_DETAILS;

	$result = $default_value;

	if($USER_DETAILS["alias"]!=ZBX_GUEST_USER){
		$sql = 'SELECT value,value2,source,valuetype '.
				' FROM profiles '.
				' WHERE userid='.$USER_DETAILS["userid"].
					' AND idx='.zbx_dbstr($idx).
					(is_null($source)?'':' AND source='.zbx_dbstr($source)).
				' ORDER BY profileid ASC';
		$db_profiles = DBselect($sql);

		if($profile=DBfetch($db_profiles)){
		
			if(PROFILE_TYPE_UNKNOWN == $type) $type = $profile["valuetype"];
	
			if(PROFILE_TYPE_MULTI_ARRAY == $type){
				$result[] = $profile;
				while($profile=DBfetch($db_profiles)){
					$result[] = $profile;
				}
			}
			else{
				$result = $profile;
			}
		}
	}

return $result;
}

//----------- ADD/EDIT USERPROFILE -------------
function update_profile($idx,$value,$type=PROFILE_TYPE_UNKNOWN,$source=null){
	global $USER_DETAILS;

	if($USER_DETAILS["alias"]==ZBX_GUEST_USER){
		return false;
	}
	
	if($type==PROFILE_TYPE_UNKNOWN && is_array($value))	$type = PROFILE_TYPE_ARRAY;
	if(($type==PROFILE_TYPE_ARRAY) && !is_array($value)) $value = array($value);

	DBstart();	

	if(PROFILE_TYPE_ARRAY == $type){
		
		$sql='DELETE FROM profiles '.
			' WHERE userid='.$USER_DETAILS["userid"].
				' AND idx='.zbx_dbstr($idx);
		DBExecute($sql);
		
		foreach($value as $id => $val){
			insert_profile($idx,$val,$type,$source);
		}
	}
	else{
		$sql = 'SELECT profileid '.
				' FROM profiles '.
				' WHERE userid='.$USER_DETAILS["userid"].
					' AND idx='.zbx_dbstr($idx).
					(is_null($source)?'':' AND source='.zbx_dbstr($source));
					
		$row = DBfetch(DBselect($sql));

		if(!$row){
			insert_profile($idx,$value,$type,$source);
		}
		else{
			$sql='UPDATE profiles SET value='.zbx_dbstr($value).',valuetype='.$type.
				' WHERE userid='.$USER_DETAILS["userid"].
					' AND idx='.zbx_dbstr($idx).
					(is_null($source)?'':' AND source='.zbx_dbstr($source));
			DBexecute($sql);
		}
	}
	
	$result = DBend();
	
return $result;
}

function update_multi_profile($idx,$value,$type=PROFILE_TYPE_UNKNOWN,$source=null){
	global $USER_DETAILS;

	if($USER_DETAILS["alias"]==ZBX_GUEST_USER){
		return false;
	}
	
	if(empty($value)) $type = PROFILE_TYPE_MULTI_ARRAY;
	if(!is_array($value))	$value = array('value' => $value);
	
	if($type==PROFILE_TYPE_UNKNOWN && isset($value['value'])) $type = PROFILE_TYPE_MULTI;
	if($type==PROFILE_TYPE_UNKNOWN && isset($value[0]['value'])) $type = PROFILE_TYPE_MULTI_ARRAY;
		
	if(($type==PROFILE_TYPE_MULTI_ARRAY) && isset($value['value']))	$value = array($value); 

	DBstart();	

	if(PROFILE_TYPE_MULTI_ARRAY == $type){
		$sql='DELETE FROM profiles '.
			' WHERE userid='.$USER_DETAILS["userid"].
				' AND idx='.zbx_dbstr($idx);
		DBExecute($sql);

		foreach($value as $id => $val){
			insert_profile($idx,$val,$type,$source);
		}
	}
	else {
		$sql = 'SELECT profileid '.
				' FROM profiles '.
				' WHERE userid='.$USER_DETAILS["userid"].
					' AND idx='.zbx_dbstr($idx).
					(is_null($source)?'':' AND source='.zbx_dbstr($source));
					
		$row = DBfetch(DBselect($sql));

		if(!$row){
			insert_profile($idx,$value,$type,$source);
		}
		else{
			$val1 = isset($value['value'])?$value['value']:'';
			$val2 = isset($value['value2'])?$value['value2']:'';
			$rsrc = isset($value['source'])?$value['source']:(is_null($source)?'':source);

			$sql='UPDATE profiles '.
				' SET value='.zbx_dbstr($val1).
						',value2='.zbx_dbstr($val2).
						',source='.zbx_dbstr($rsrc).
						',valuetype='.$type.
				' WHERE userid='.$USER_DETAILS["userid"].
					' AND idx='.zbx_dbstr($idx).
					(is_null($source)?'':' AND source='.zbx_dbstr($source));
			DBexecute($sql);
		}
	}
	
	$result = DBend();
	
return $result;
}


// Author: Aly
function insert_profile($idx,$value,$type,$source=null){
	global $USER_DETAILS;

	$profileid = get_dbid('profiles', 'profileid');

	$val1 = $value;
	$val2 = '';
	$rsrc = is_null($source)?'':$source;
	
	if(($type == PROFILE_TYPE_MULTI_ARRAY) || 
		($type == PROFILE_TYPE_MULTI) ||
		is_array($value))
	{
		$val1 = isset($value['value'])?$value['value']:'';
		$val2 = isset($value['value2'])?$value['value2']:'';
		$rsrc = isset($value['source'])?$value['source']:$rsrc;
	}

	if(is_null($val1)) return false;
	
	$sql='INSERT INTO profiles (profileid,userid,idx,value,value2,source,valuetype)'.
		' VALUES ('.$profileid.','.
					$USER_DETAILS["userid"].','.
					zbx_dbstr($idx).','.
					zbx_dbstr($val1).','.
					zbx_dbstr($val2).','.
					zbx_dbstr($rsrc).','.
					$type.')';

	$result = DBexecute($sql);
	
return $result;
}

/***********************************/

/************ HISTORY **************/
// Author: Aly
function get_user_history(){
	$history=array();
	$delimiter = new CSpan('&raquo;','delimiter');
	for($i = 0; $i < ZBX_HISTORY_COUNT; $i++){
		if($rows = get_multi_profile('web.history.'.$i,false,PROFILE_TYPE_MULTI)){
			if($i>0){
				array_push($history,$delimiter);
			}
			$url = new CLink($rows['value'],$rows['value2'],'history');
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
		$new_rows = get_multi_profile('web.history.'.$i,false,PROFILE_TYPE_MULTI);
		
		if(!$same_page && ($title == $new_rows['value'])) continue;
		$rows = $new_rows;
	}
	
	if(is_array($rows)){
		$rows['page'] = $rows['value'];
		$rows['url'] = $rows['value2'];
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
		if($history = get_multi_profile('web.history.'.$i,false)){
			if($history['value'] != $title){
				$profile[$curr] = $history;
				$curr++;
			}
		}
	}

	$history = array('value' => $title, 
					'value2' => $url);
				
	if($curr < ZBX_HISTORY_COUNT){
		for($i = 0; $i < $curr; $i++){
			update_multi_profile('web.history.'.$i,$profile[$i]);
		}
		$result = update_multi_profile('web.history.'.$curr,$history);
	} 
	else {
		for($i = 1; $i < ZBX_HISTORY_COUNT; $i++){
			update_multi_profile('web.history.'.($i-1),$profile[$i]);
		}
		$result = update_multi_profile('web.history.'.(ZBX_HISTORY_COUNT-1),$history);
	}

return $result;
}
/********* END USER HISTORY **********/

/********** USER FAVORITES ***********/
/********** USER FAVORITES ***********/
// Author: Aly
function get_favorites($favobj,$nodeid=null){
	$fav = get_multi_profile($favobj);
	
	if(is_null($nodeid))
		$nodeid = get_current_nodeid();

	if(!is_array($nodeid))
		$nodeid = array($nodeid);

	foreach($fav as $key => $favorite){
		if(!uint_in_array(id2nodeid($favorite['value']),$nodeid)) unset($fav[$key]);
	}

return $fav;
}
// Author: Aly
function add2favorites($favobj,$favid,$source=null){
	$favorites = get_favorites($favobj);

	$favorites[] = array('value' => $favid);
	
	$result = update_multi_profile($favobj,$favorites,PROFILE_TYPE_MULTI_ARRAY,$source);
return $result;
}

// Author: Aly
function rm4favorites($favobj,$favid,$favcnt=null,$source=null){
	$favorites = get_favorites($favobj);

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

	$result = update_multi_profile($favobj,$favorites,PROFILE_TYPE_MULTI_ARRAY);
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